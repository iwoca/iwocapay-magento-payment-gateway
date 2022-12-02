<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Model\Request;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Iwoca\Iwocapay\Api\Request\CreateOrderPayloadInterfaceFactory;
use Iwoca\Iwocapay\Api\Response\CreateOrderInterface;
use Iwoca\Iwocapay\Api\Response\CreateOrderInterfaceFactory;
use Iwoca\Iwocapay\Model\Config;
use Iwoca\Iwocapay\Model\IwocaClientFactory;
use Iwoca\Iwocapay\Model\Response\CreateOrder as CreateOrderResponse;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Http\Message\ResponseInterface;

class CreateOrder
{

    private CartRepositoryInterface $quoteRepository;
    private QuoteManagement $quoteManagement;
    private CreateOrderPayloadInterfaceFactory $createOrderPayloadFactory;
    private IwocaClientFactory $iwocaClientFactory;
    private Config $config;
    private CreateOrderInterfaceFactory $createOrderInterfaceFactory;
    private Json $jsonSerializer;
    private OrderRepositoryInterface $orderRepository;

    /**
     * @param CartRepositoryInterface $quoteRepository
     * @param QuoteManagement $quoteManagement
     * @param CreateOrderPayloadInterfaceFactory $createOrderPayloadFactory
     * @param IwocaClientFactory $iwocaClientFactory
     * @param Config $config
     * @param CreateOrderInterfaceFactory $createOrderInterfaceFactory
     * @param Json $jsonSerializer
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        QuoteManagement $quoteManagement,
        CreateOrderPayloadInterfaceFactory $createOrderPayloadFactory,
        IwocaClientFactory $iwocaClientFactory,
        Config $config,
        CreateOrderInterfaceFactory $createOrderInterfaceFactory,
        Json $jsonSerializer,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->quoteManagement = $quoteManagement;
        $this->createOrderPayloadFactory = $createOrderPayloadFactory;
        $this->iwocaClientFactory = $iwocaClientFactory;
        $this->config = $config;
        $this->createOrderInterfaceFactory = $createOrderInterfaceFactory;
        $this->jsonSerializer = $jsonSerializer;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Create an order in Iwoca.
     *
     * @param string $cartId
     * @return CreateOrderResponse
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(string $cartId): CreateOrderResponse
    {
        $order = $this->createMagentoOrder($cartId);
        $rawResponse = $this->createIwocaOrder($order);
        $orderResponse = $this->getCreateOrderResponse($rawResponse);

        $order->addCommentToStatusHistory(
            __(
                'Order with ID "%1" has been created in Iwoca. Redirecting user to %2 to continue payment.',
                $orderResponse->getId(),
                $orderResponse->getOrderUrl()
            )
        );

        $this->orderRepository->save($order);

        return $orderResponse;
    }

    /**
     * @param string $cartId
     * @return OrderInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function createMagentoOrder(string $cartId): OrderInterface
    {
        $quote = $this->quoteRepository->get($cartId);
        $order = $this->quoteManagement->submit($quote);

        $order->setState(Order::STATE_PENDING_PAYMENT);
        $order->setStatus(Order::STATE_PENDING_PAYMENT);

        $order->addCommentToStatusHistory(__('Creating order in Iwocapay.'));

        $this->orderRepository->save($order);

        return $order;
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

        /** @var CreateOrderInterface $createOrderResponse */
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
}
