<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Model;

class EventReportPolicy
{
    public const EVENT_ORDER_CREATE_FAILED = 'ORDER_CREATE_FAILED';
    public const EVENT_ORDER_FAILED_TO_RECONCILE = 'ORDER_FAILED_TO_RECONCILE';
    public const EVENT_API_CALL_FAILED = 'API_CALL_FAILED';
    public const EVENT_UNHANDLED_EXCEPTION = 'UNHANDLED_EXCEPTION';

    public const MAX_EVENTS_PER_REQUEST = 100;

    public const MAX_BUFFERED_EVENTS = 500;

    public const MAX_DETAIL_LENGTH = 500;

    private const ERROR_EVENTS = [
        self::EVENT_ORDER_CREATE_FAILED,
        self::EVENT_ORDER_FAILED_TO_RECONCILE,
        self::EVENT_API_CALL_FAILED,
        self::EVENT_UNHANDLED_EXCEPTION,
    ];

    private const KNOWN_ORDER_STATUSES = ['CREATED', 'PENDING', 'APPROVED', 'SUCCESSFUL', 'UNSUCCESSFUL'];

    public function isErrorEvent(string $eventType): bool
    {
        return in_array($eventType, self::ERROR_EVENTS, true);
    }

    public function isKnownOrderStatus(?string $status): bool
    {
        return $status !== null && in_array($status, self::KNOWN_ORDER_STATUSES, true);
    }

    public function logLevelFor(string $eventType): string
    {
        return $this->isErrorEvent($eventType) ? 'error' : 'info';
    }

    public function shouldSendForStatus(?int $statusCode): bool
    {
        return $statusCode !== null && $statusCode >= 400 && $statusCode < 500;
    }

    public function extractErrorDetail(?string $responseBody): ?string
    {
        if ($responseBody === null || $responseBody === '') {
            return null;
        }

        $decoded = json_decode($responseBody, true);
        if (is_array($decoded) && isset($decoded['errors'][0]['detail']) && is_string($decoded['errors'][0]['detail'])) {
            return mb_substr($decoded['errors'][0]['detail'], 0, self::MAX_DETAIL_LENGTH);
        }

        return mb_substr($responseBody, 0, self::MAX_DETAIL_LENGTH);
    }

    public function isBufferFull(int $bufferedCount): bool
    {
        return $bufferedCount >= self::MAX_BUFFERED_EVENTS;
    }

    public function chunk(array $events, int $size = self::MAX_EVENTS_PER_REQUEST): array
    {
        if (empty($events)) {
            return [];
        }

        return array_chunk($events, max(1, $size));
    }
}
