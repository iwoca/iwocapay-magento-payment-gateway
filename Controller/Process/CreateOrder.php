<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Controller\Process;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Iwoca\Iwocapay\Api\Request\CreateOrderPayloadInterfaceFactory;
use Iwoca\Iwocapay\Api\Response\CreateOrderInterface;
use Iwoca\Iwocapay\Api\Response\CreateOrderInterfaceFactory;
use Iwoca\Iwocapay\Model\Config;
use Iwoca\Iwocapay\Model\IwocaClientFactory;
use Iwoca\Iwocapay\Model\Request\CreateOrderPayload;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class CreateOrder implements HttpGetActionInterface
{
    private ResultFactory $resultFactory;
    private CreateOrderPayloadInterfaceFactory $createOrderPayloadFactory;
    private IwocaClientFactory $iwocaClientFactory;
    private Config $config;
    private CreateOrderInterfaceFactory $createOrderInterfaceFactory;
    private Json $jsonSerializer;
    private OrderRepositoryInterface $orderRepository;
    private Session $checkoutSession;
    private ManagerInterface $messageManager;
    private CartRepositoryInterface $quoteRepository;
    private StoreManagerInterface $storeManager;
    private LoggerInterface $logger;

    /**
     * @param ResultFactory $resultFactory
     * @param CreateOrderPayloadInterfaceFactory $createOrderPayloadFactory
     * @param IwocaClientFactory $iwocaClientFactory
     * @param Config $config
     * @param CreateOrderInterfaceFactory $createOrderInterfaceFactory
     * @param Json $jsonSerializer
     * @param OrderRepositoryInterface $orderRepository
     * @param Session $checkoutSession
     * @param ManagerInterface $messageManager
     * @param CartRepositoryInterface $quoteRepository
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ResultFactory $resultFactory,
        CreateOrderPayloadInterfaceFactory $createOrderPayloadFactory,
        IwocaClientFactory $iwocaClientFactory,
        Config $config,
        CreateOrderInterfaceFactory $createOrderInterfaceFactory,
        Json $jsonSerializer,
        OrderRepositoryInterface $orderRepository,
        Session $checkoutSession,
        ManagerInterface $messageManager,
        CartRepositoryInterface $quoteRepository,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->resultFactory = $resultFactory;
        $this->createOrderPayloadFactory = $createOrderPayloadFactory;
        $this->iwocaClientFactory = $iwocaClientFactory;
        $this->config = $config;
        $this->createOrderInterfaceFactory = $createOrderInterfaceFactory;
        $this->jsonSerializer = $jsonSerializer;
        $this->orderRepository = $orderRepository;
        $this->checkoutSession = $checkoutSession;
        $this->messageManager = $messageManager;
        $this->quoteRepository = $quoteRepository;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $order = $this->checkoutSession->getLastRealOrder();

        $order->setState(Order::STATE_PENDING_PAYMENT);
        $order->setStatus(Order::STATE_PENDING_PAYMENT);
        $order->addCommentToStatusHistory(__('Creating order in iwocaPay.'));

        $this->orderRepository->save($order);

        try {
            $rawResponse = $this->createIwocaOrder($order);
        } catch (LocalizedException $e) {
            return $this->handleFailure($redirect, $order, $e->getMessage());
        }
        $orderResponse = $this->getCreateOrderResponse($rawResponse);

        $redirectUrl = $orderResponse->getOrderUrl();

        $order->addCommentToStatusHistory(
            __(
                'Order with ID "%1" has been created in Iwoca. Redirecting user to %2 to continue payment.',
                $orderResponse->getId(),
                $redirectUrl
            )
        );
        $order->setCanSendNewEmailFlag(false);

        $this->orderRepository->save($order);

        return $redirect->setUrl($redirectUrl);
    }

    /**
     * @param OrderInterface $order
     * @return CreateOrderPayload
     */
    private function getPayload(OrderInterface $order): CreateOrderPayload
    {
        /**@var CreateOrderPayload $createOrder */
        $createOrder = $this->createOrderPayloadFactory->create();


        $createOrder->setAmount((float)$order->getGrandTotal())
            ->setReference($order->getIncrementId())
            ->setAllowedPaymentTerms($this->config->getAllowedPaymentTerms())
            ->setSource($this->config->getSource())
            ->setRedirectUrl($this->getRedirectUrl());

        return $createOrder;
    }

    /**
     * @param ResponseInterface $rawResponse
     * @return CreateOrderInterface
     */
    private function getCreateOrderResponse(ResponseInterface $rawResponse): CreateOrderInterface
    {
        $jsonResponse = $rawResponse->getBody()->getContents();
        $responseData = $this->jsonSerializer->unserialize($jsonResponse);

        $createOrderResponse = $this->createOrderInterfaceFactory->create();
        $createOrderResponse->setData($responseData['data']);

        return $createOrderResponse;
    }

    /**
     * @param OrderInterface $order
     * @return ResponseInterface
     * @throws LocalizedException
     */
    private function createIwocaOrder(OrderInterface $order): ResponseInterface
    {
        $iwocaClient = $this->iwocaClientFactory->create();

        $payload = $this->getPayload($order);

        $this->addDebugLog(
            sprintf(
                'Payload for creating Iwoca order with increment ID %s. %s',
                $order->getIncrementId(),
                $payload->toJson()
            )
        );

        try {
            $rawResponse = $iwocaClient->post(
                $this->config->getApiEndpoint(Config::CONFIG_TYPE_CREATE_ORDER_ENDPOINT),
                [
                    RequestOptions::JSON => ['data' => $payload->toArray()]
                ]
            );
        } catch (GuzzleException|LocalizedException $e) {
            $this->addDebugLog(
                sprintf(
                    'Error occured while creating order in Iwoca for order with increment ID %s. Received exception: %s',
                    $order->getIncrementId(),
                    $e->getMessage()
                )
            );
            $errorMessage = __('Unable to create the order in iwocaPay. %1', $e->getMessage());
            $order->addCommentToStatusHistory($errorMessage);
            throw new LocalizedException($errorMessage, $e);
        }

        if ($rawResponse->getStatusCode() !== 201) {
            $this->addDebugLog(
                sprintf(
                    'Received status code %s, but expected 201 (Order Created) for order with increment ID %s',
                    $rawResponse->getStatusCode(),
                    $order->getIncrementId()
                )
            );
            $errorMessage = __('Unable to create the order in iwocaPay. %1', $rawResponse);
            $order->addCommentToStatusHistory($errorMessage);
            throw new LocalizedException($errorMessage);
        }

        return $rawResponse;
    }

    /**
     * @param Redirect $redirect
     * @param OrderInterface|null $order
     * @param string|null $message
     * @return Redirect
     * @throws NoSuchEntityException
     */
    private function handleFailure(Redirect $redirect, ?OrderInterface $order = null, ?string $message = null): Redirect
    {
        if ($order) {
            $order->addCommentToStatusHistory(
                __(
                    'Unable to create Iwoca order. Failed with message %1. Canceling order, recreating quote and redirecting user to cart to retry',
                    $message
                )
            );

            $order->setState(Order::STATE_CANCELED);
            $order->setStatus(Order::STATE_CANCELED);

            $this->orderRepository->save($order);

            $this->activateQuoteFromOrder($order);
        }

        $this->messageManager->addErrorMessage(__('Something went wrong while placing your order.'));
        $redirect->setUrl('/checkout/cart');

        return $redirect;
    }

    /**
     * @param OrderInterface $order
     * @return void
     * @throws NoSuchEntityException
     */
    private function activateQuoteFromOrder(OrderInterface $order): void
    {
        $quoteId = $order->getQuoteId();

        $quote = $this->quoteRepository->get($quoteId);
        $quote->setIsActive(1);
        $this->quoteRepository->save($quote);

        $this->checkoutSession->setQuoteId($quoteId);
    }

    /**
     * @return string
     */
    private function getRedirectUrl(): string
    {
        try {
            $baseUrl = rtrim($this->storeManager->getStore()->getBaseUrl(), '/');
        } catch (NoSuchEntityException $e) {
            $baseUrl = '';
        }

        return $baseUrl . '/' . ltrim($this->config->getRedirectPath(), '/');
    }

    /**
     * @param string $logMessage
     * @return void
     */
    private function addDebugLog(string $logMessage): void
    {
        if (!$this->config->isDebugModeEnabled()) {
            return;
        }

        $this->logger->debug($logMessage);
    }
}
