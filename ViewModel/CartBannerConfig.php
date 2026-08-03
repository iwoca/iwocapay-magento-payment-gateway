<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\ViewModel;

use Iwoca\Iwocapay\Model\Config;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Exposes the presentation-only banner config to the storefront hydration
 * script so the client-side cart banner can render enabled terms, per-term
 * interest and theme without a round trip.
 *
 * Deliberately narrow: only the fields the banner displays (all already public
 * on the rendered banner). No credentials, pricing secrets or PII.
 */
class CartBannerConfig implements ArgumentInterface
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * The enabled terms (with per-term interest kebab-cased for the component),
     * theme and VAT setting. Shape:
     *   {
     *     "terms":[{"key":"3m","duration":"3_months","months":3,"interest":"seller-pays"}, ...],
     *     "theme":"dark",
     *     "includesVat":true
     *   }
     *
     * `includesVat` follows the seller's VAT setting: the cart banner finances
     * the matching basket subtotal (incl- or excl-tax) and labels it to match,
     * so both the figure and the label are consistent with how the seller
     * presents prices elsewhere.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        $terms = array_map(
            static fn (array $term): array => [
                'key' => $term['key'],
                'duration' => $term['duration'],
                'months' => $term['months'],
                'interest' => str_replace('_', '-', $term['interest']),
            ],
            $this->config->getEnabledBannerTerms()
        );

        return [
            'terms' => $terms,
            'theme' => $this->config->getPriceBannerTheme(),
            'includesVat' => $this->config->getPriceBannerVat() === 'including',
            'checkoutEnabled' => $this->config->isCheckoutBannerEnabled(),
        ];
    }
}
