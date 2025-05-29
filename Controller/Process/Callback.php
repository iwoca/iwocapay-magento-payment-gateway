<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Controller\Process;

use GuzzleHttp\Exception\GuzzleException;
use Iwoca\Iwocapay\Api\Response\GetOrderInterface;
use Iwoca\Iwocapay\Api\Response\GetOrderInterfaceFactory;
use Iwoca\Iwocapay\Model\Config;
use Iwoca\Iwocapay\Model\IwocaClientFactory;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Psr\Log\LoggerInterface;

class Callback implements HttpGetActionInterface
{
    public const IWOCA_ORDER_ID_PARAM = 'orderId';

    private Context $context;
    private ResultFactory $resultFactory;
    private Config $config;
    private IwocaClientFactory $iwocaClientFactory;
    private Json $jsonSerializer;
    private GetOrderInterfaceFactory $getOrderFactory;
    private Session $checkoutSession;
    private OrderFactory $orderFactory;
    private OrderRepositoryInterface $orderRepository;
    private ManagerInterface $messageManager;
    private CartRepositoryInterface $quoteRepository;
    private InvoiceService $invoiceService;
    private InvoiceRepositoryInterface $invoiceRepository;
    private InvoiceSender $invoiceSender;
    private OrderSender $orderSender;
    private LoggerInterface $logger;

    /**
     * @param Context $context
     * @param ResultFactory $resultFactory
     * @param Config $config
     * @param IwocaClientFactory $iwocaClientFactory
     * @param Json $jsonSerializer
     * @param GetOrderInterfaceFactory $getOrderFactory
     * @param Session $checkoutSession
     * @param OrderFactory $orderFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param ManagerInterface $messageManager
     * @param CartRepositoryInterface $quoteRepository
     * @param InvoiceService $invoiceService
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param OrderSender $orderSender
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        ResultFactory $resultFactory,
        Config $config,
        IwocaClientFactory $iwocaClientFactory,
        Json $jsonSerializer,
        GetOrderInterfaceFactory $getOrderFactory,
        Session $checkoutSession,
        OrderFactory $orderFactory,
        OrderRepositoryInterface $orderRepository,
        ManagerInterface $messageManager,
        CartRepositoryInterface $quoteRepository,
        InvoiceService $invoiceService,
        InvoiceRepositoryInterface $invoiceRepository,
        InvoiceSender $invoiceSender,
        OrderSender $orderSender,
        LoggerInterface $logger
    ) {
        $this->context = $context;
        $this->resultFactory = $resultFactory;
        $this->config = $config;
        $this->iwocaClientFactory = $iwocaClientFactory;
        $this->jsonSerializer = $jsonSerializer;
        $this->getOrderFactory = $getOrderFactory;
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->orderRepository = $orderRepository;
        $this->messageManager = $messageManager;
        $this->quoteRepository = $quoteRepository;
        $this->invoiceService = $invoiceService;
        $this->invoiceRepository = $invoiceRepository;
        $this->invoiceSender = $invoiceSender;
        $this->orderSender = $orderSender;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     * @throws LocalizedException
     */
    public function execute()
    {
        $iwocaOrderId = $this->context->getRequest()->getParam(self::IWOCA_ORDER_ID_PARAM);

        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        if (!$iwocaOrderId) {
            return $this->handleFailure($redirect);
        }

        try {
            $orderResponse = $this->getIwocaOrderResponse($iwocaOrderId);
        } catch (GuzzleException|LocalizedException $e) {
            return $this->handleFailure($redirect);
        }

        $magentoOrder = $this->orderFactory->create();
        $magentoOrder->loadByIncrementId($orderResponse->getReference());
        $magentoOrder->addCommentToStatusHistory(__('Iwoca order callback initiated.'));

        $statusCode = $orderResponse->getStatus();
        if ($statusCode !== GetOrderInterface::STATUS_CODE_SUCCESSFUL) {
            $this->addDebugLog(
                sprintf(
                    'Received status %s for order with increment ID %s',
                    $statusCode,
                    $orderResponse->getReference()
                )
            );
            return $this->handleFailure($redirect, $magentoOrder, $statusCode);
        }

        $this->handleSuccess($magentoOrder, $orderResponse);

        $redirect->setUrl('/checkout/onepage/success');

        return $redirect;
    }

    /**
     * @param string $iwocaOrderId
     * @return GetOrderInterface
     * @throws GuzzleException
     * @throws LocalizedException
     */
    private function getIwocaOrderResponse(string $iwocaOrderId): GetOrderInterface
    {
        $iwocaClient = $this->iwocaClientFactory->create();
        try {
            $rawResponse = $iwocaClient->get(
                $this->config->getApiEndpoint(
                    Config::CONFIG_TYPE_GET_ORDER_ENDPOINT,
                    [':' . self::IWOCA_ORDER_ID_PARAM => $iwocaOrderId]
                )
            );
        } catch (GuzzleException $e) {
            $this->addDebugLog(
                sprintf(
                    'Error occurred while retrieving Iwoca order response for order with Iwoca ID %s. Received exception %s',
                    $iwocaOrderId,
                    $e->getMessage()
                )
            );
            throw $e;
        } catch (LocalizedException $e) {
            $this->addDebugLog(
                sprintf(
                    'Error occurred while getting API endpoint of type "%s". Exception: %s',
                    Config::CONFIG_TYPE_GET_ORDER_ENDPOINT,
                    $e->getMessage()
                )
            );
            throw $e;
        }

        $responseJson = $rawResponse->getBody()->getContents();
        $responseData = $this->jsonSerializer->unserialize($responseJson);

        $orderResponse = $this->getOrderFactory->create();
        $orderResponse->setData($responseData['data']);

        return $orderResponse;
    }

    /**
     * @param Redirect $redirect
     * @param OrderInterface|null $order
     * @param string|null $statusCode
     * @return Redirect
     * @throws NoSuchEntityException
     */
    private function handleFailure(Redirect $redirect, ?OrderInterface $order = null, ?string $statusCode = null): Redirect
    {
        if ($order) {
            $order->addCommentToStatusHistory(
                __(
                    'Received status code %1. Canceling order, recreating quote and redirecting user to cart to retry',
                    $statusCode
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
     * @param Order $order
     * @param GetOrderInterface $orderResponse
     * @return void
     * @throws LocalizedException
     */
    private function handleSuccess(Order $order, GetOrderInterface $orderResponse): void
    {
        $this->createOrderInvoice($order, $orderResponse);
        $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
        $this->checkoutSession->setLastQuoteId($order->getQuoteId());
        $this->checkoutSession->setLastOrderId($order->getId());
        $this->checkoutSession->setLastRealOrderId($order->getRealOrderId());

        $order->addCommentToStatusHistory(__(
            'Iwoca payment with amount "%1" was successful. Redirecting customer to success page.',
            $orderResponse->getAmount()
        ));

        $order->setState(Order::STATE_PROCESSING);
        $order->setStatus(Order::STATE_PROCESSING);
        $order->setCanSendNewEmailFlag(true);
        $this->orderSender->send($order);
        $this->orderRepository->save($order);
    }

    /**
     * @param Order $order
     * @param GetOrderInterface $orderResponse
     * @return void
     * @throws LocalizedException
     */
    private function createOrderInvoice(Order $order, GetOrderInterface $orderResponse): void
    {
        if (!$order->canInvoice()) {
            $order->addCommentToStatusHistory(__('Order cannot be invoiced'));
            throw new LocalizedException(__('Order cannot be invoiced'));
        }

        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
        $invoice->setTransactionId($orderResponse->getPayLinkId());
        $invoice->register();
        $invoice->setEmailSent(true);
        $this->invoiceSender->send($invoice);

        $this->invoiceRepository->save($invoice);

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
