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
use Psr\Http\Message\ResponseInterface;

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
        CartRepositoryInterface $quoteRepository
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
        $order->addCommentToStatusHistory(__('Creating order in Iwocapay.'));

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

        $createOrder->setAmount($order->getTotalDue())
            ->setReference($order->getIncrementId());

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

        try {
            $rawResponse = $iwocaClient->post(
                $this->config->getApiEndpoint(Config::CONFIG_TYPE_CREATE_ORDER_ENDPOINT),
                [
                    RequestOptions::JSON => ['data' => $payload->toArray()]
                ]
            );
        } catch (GuzzleException|LocalizedException $e) {
            $errorMessage = __('Unable to create the order in Iwocapay. %1', $e->getMessage());
            $order->addCommentToStatusHistory($errorMessage);
            throw new LocalizedException($errorMessage, $e);
        }

        if ($rawResponse->getStatusCode() !== 201) {
            $errorMessage = __('Unable to create the order in Iwocapay. %1', $rawResponse);
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
}
