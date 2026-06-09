<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Observer;

use Iwoca\Iwocapay\Model\IntegrationEventService;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class TrackBannerContentChange implements ObserverInterface
{
    private const BANNER_TYPES = [
        'signposting-hero',
        'signposting-block',
        'signposting-side',
        'signposting-small',
        'spending-limit-wide',
        'spending-limit-block',
        'spending-limit-side',
        'spending-limit-small',
    ];

    private IntegrationEventService $eventService;

    public function __construct(IntegrationEventService $eventService)
    {
        $this->eventService = $eventService;
    }

    public function execute(Observer $observer): void
    {
        $model = $observer->getEvent()->getData('object');
        if (!$model || !$model->dataHasChangedFor('content')) {
            return;
        }

        $oldContent = (string) $model->getOrigData('content');
        $newContent = (string) $model->getData('content');

        foreach (self::BANNER_TYPES as $bannerType) {
            $wasPresent = $this->hasBannerType($oldContent, $bannerType);
            $isPresent = $this->hasBannerType($newContent, $bannerType);

            if (!$wasPresent && $isPresent) {
                $this->eventService->send('SELLER_ADDED_BANNER', ['banner_type' => $bannerType]);
            } elseif ($wasPresent && !$isPresent) {
                $this->eventService->send('SELLER_REMOVED_BANNER', ['banner_type' => $bannerType]);
            }
        }
    }

    private function hasBannerType(string $content, string $bannerType): bool
    {
        return str_contains($content, 'data-banner-type="' . $bannerType . '"');
    }
}
