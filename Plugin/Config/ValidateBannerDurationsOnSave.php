<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Plugin\Config;

use Magento\Config\Model\Config as AdminConfig;
use Magento\Framework\Exception\LocalizedException;

/**
 * Blocks saving the iwocaPay config with a banner surface enabled but no
 * payment duration selected. The durations are shared by every banner (product
 * & listing pages and checkout), and a banner with zero durations renders
 * nothing — so the seller must pick at least one of 30 days / 3 months /
 * 12 months whenever any banner surface is turned on.
 *
 * Runs on the same Magento\Config\Model\Config::save() as the credential check
 * (see ValidateCredentialsOnSave); throwing a LocalizedException aborts the
 * save and the System Config controller redirects back with the message.
 */
class ValidateBannerDurationsOnSave
{
    private const IWOCAPAY_SECTION = 'payment';
    private const IWOCAPAY_GROUP = 'iwocapay';

    /**
     * The banner-surface on/off toggles. Both share the duration settings, so
     * turning on either one requires at least one duration.
     */
    private const SURFACE_FIELDS = [
        'price_banner_enabled',
        'price_banner_checkout_enabled',
    ];
    private const DURATION_FIELDS = [
        'price_banner_30d_enabled',
        'price_banner_3m_enabled',
        'price_banner_12m_enabled',
    ];

    /**
     * @param AdminConfig $subject
     * @return void
     * @throws LocalizedException
     */
    public function beforeSave(AdminConfig $subject): void
    {
        if ($subject->getSection() !== self::IWOCAPAY_SECTION) {
            return;
        }

        $groups = (array) $subject->getGroups();
        if (!isset($groups[self::IWOCAPAY_GROUP])) {
            return;
        }

        // Only validate when a banner surface was part of this save and is being
        // turned on — editing unrelated settings must not be blocked.
        $surfaceEnabled = false;
        foreach (self::SURFACE_FIELDS as $field) {
            if ((string) $this->findPostedValue($groups, $field) === '1') {
                $surfaceEnabled = true;
                break;
            }
        }
        if (!$surfaceEnabled) {
            return;
        }

        // Block only the unambiguous misconfiguration: every duration explicitly
        // posted as off. A field left "Use Default" (not posted) inherits the
        // parent scope, whose default already has a duration enabled — so an
        // absent value must not trip the guard.
        foreach (self::DURATION_FIELDS as $field) {
            $value = $this->findPostedValue($groups, $field);
            if ($value === null || (string) $value === '1') {
                return;
            }
        }

        throw new LocalizedException(
            __('Please enable at least one payment duration (30 days, 3 months or 12 months) for your iwocaPay banners.')
        );
    }

    /**
     * Recursively find a posted field value by its field id within the groups
     * structure. Returns null if the field wasn't part of this save.
     *
     * @param array $node
     * @param string $fieldId
     * @return string|null
     */
    private function findPostedValue(array $node, string $fieldId): ?string
    {
        if (isset($node['fields'][$fieldId]) && array_key_exists('value', $node['fields'][$fieldId])) {
            return (string) $node['fields'][$fieldId]['value'];
        }

        foreach ($node as $child) {
            if (is_array($child)) {
                $found = $this->findPostedValue($child, $fieldId);
                if (null !== $found) {
                    return $found;
                }
            }
        }

        return null;
    }
}
