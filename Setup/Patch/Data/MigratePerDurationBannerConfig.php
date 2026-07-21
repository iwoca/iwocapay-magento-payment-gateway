<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Setup\Patch\Data;

use Iwoca\Iwocapay\Model\Config;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Migrates the legacy single-duration banner config onto the new per-duration
 * structure introduced with the cart banner.
 *
 * Old:  price_banner_months  (a combo string, e.g. "3_and_12_months")
 *       price_banner_pricing (one interest for all durations)
 * New:  price_banner_{30d,3m,12m}_enabled + _interest, per duration.
 *
 * Runs once (tracked by class name in patch_list). Reads and writes at every
 * stored scope so website/store overrides carry over, not just the default.
 */
class MigratePerDurationBannerConfig implements DataPatchInterface
{
    private const OLD_PATH_MONTHS = 'payment/iwocapay/price_banner_months';
    private const OLD_PATH_PRICING = 'payment/iwocapay/price_banner_pricing';

    /**
     * Which durations each legacy combo enables.
     * Order: [30d, 3m, 12m].
     */
    private const COMBO_MAP = [
        '30_days' => [true, false, false],
        '30_days_and_3_months' => [true, true, false],
        '3_months' => [false, true, false],
        '12_months' => [false, false, true],
        '3_and_12_months' => [false, true, true],
        '1_3_and_12_months' => [true, true, true],
    ];

    private ModuleDataSetupInterface $moduleDataSetup;
    private WriterInterface $configWriter;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        WriterInterface $configWriter
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->configWriter = $configWriter;
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('core_config_data');

        $select = $connection->select()
            ->from($table, ['scope', 'scope_id', 'value'])
            ->where('path = ?', self::OLD_PATH_MONTHS);

        foreach ($connection->fetchAll($select) as $row) {
            $combo = (string) $row['value'];
            if (!isset(self::COMBO_MAP[$combo])) {
                continue;
            }

            $scope = (string) $row['scope'];
            $scopeId = (int) $row['scope_id'];
            [$has30d, $has3m, $has12m] = self::COMBO_MAP[$combo];

            $interest = $this->readPricingForScope($scope, $scopeId);

            $this->write(Config::XML_CONFIG_PATH_PRICE_BANNER_30D_ENABLED, $has30d ? '1' : '0', $scope, $scopeId);
            $this->write(Config::XML_CONFIG_PATH_PRICE_BANNER_3M_ENABLED, $has3m ? '1' : '0', $scope, $scopeId);
            $this->write(Config::XML_CONFIG_PATH_PRICE_BANNER_12M_ENABLED, $has12m ? '1' : '0', $scope, $scopeId);

            $this->write(Config::XML_CONFIG_PATH_PRICE_BANNER_30D_INTEREST, $interest, $scope, $scopeId);
            $this->write(Config::XML_CONFIG_PATH_PRICE_BANNER_3M_INTEREST, $interest, $scope, $scopeId);
            $this->write(Config::XML_CONFIG_PATH_PRICE_BANNER_12M_INTEREST, $interest, $scope, $scopeId);
        }

        // Legacy paths are no longer referenced anywhere; drop them.
        $connection->delete($table, ['path IN (?)' => [self::OLD_PATH_MONTHS, self::OLD_PATH_PRICING]]);

        return $this;
    }

    /**
     * The stored interest at a scope, resolving up to the parent scope when the
     * scope has no explicit value (mirrors config inheritance), defaulting to
     * seller_pays — the legacy default and the interest-free/no-API path.
     */
    private function readPricingForScope(string $scope, int $scopeId): string
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('core_config_data');

        $select = $connection->select()
            ->from($table, ['value'])
            ->where('path = ?', self::OLD_PATH_PRICING)
            ->where('scope = ?', $scope)
            ->where('scope_id = ?', $scopeId);

        $value = $connection->fetchOne($select);
        if ($value === false || $value === null || $value === '') {
            $defaultSelect = $connection->select()
                ->from($table, ['value'])
                ->where('path = ?', self::OLD_PATH_PRICING)
                ->where('scope = ?', 'default')
                ->where('scope_id = ?', 0);
            $value = $connection->fetchOne($defaultSelect);
        }

        return $value === 'buyer_pays' ? 'buyer_pays' : 'seller_pays';
    }

    private function write(string $path, string $value, string $scope, int $scopeId): void
    {
        $this->configWriter->save($path, $value, $scope, $scopeId);
    }

    public function getAliases(): array
    {
        return [];
    }

    public static function getDependencies(): array
    {
        return [];
    }
}
