<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Controller\Tracking;

use Iwoca\Iwocapay\Model\IntegrationEventService;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;

class Event implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const ALLOWED_EVENTS = [
        'CUSTOMER_VIEWED_PRICING_BANNER',
        'CUSTOMER_VIEWED_BLOCK_BANNER',
        'CUSTOMER_VIEWED_SPENDING_LIMIT_BANNER',
        'CUSTOMER_CLICKED_SPENDING_LIMIT_BANNER',
    ];

    private RequestInterface $request;
    private JsonFactory $jsonFactory;
    private IntegrationEventService $eventService;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        IntegrationEventService $eventService
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->eventService = $eventService;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $eventType = $this->request->getParam('event_type', '');

        if (!in_array($eventType, self::ALLOWED_EVENTS, true)) {
            $result->setHttpResponseCode(400);
            $result->setData(['error' => 'Invalid event type']);
            return $result;
        }

        $this->eventService->send($eventType);

        $result->setData(['success' => true]);
        return $result;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return $request->getHeader('X-Requested-With') === 'XMLHttpRequest';
    }
}
