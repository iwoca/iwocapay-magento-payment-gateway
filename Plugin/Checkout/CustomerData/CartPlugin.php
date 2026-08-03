<?php
declare(strict_types=1);

namespace Iwoca\Iwocapay\Plugin\Checkout\CustomerData;

use Magento\Checkout\CustomerData\Cart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Psr\Log\LoggerInterface;

/**
 * Adds the basket subtotal as raw floats (both VAT-inclusive and
 * VAT-exclusive) to the `cart` customer-data section.
 *
 * The core section only exposes formatted currency strings for the incl/excl
 * subtotals (via the tax module plugin); the raw `subtotalAmount` has an
 * ambiguous VAT basis (store tax-display config). The cart banner finances the
 * subtotal that matches the seller's VAT setting, so it needs reliable raw
 * numbers for both bases — hence these explicit fields.
 */
class CartPlugin
{
    private CheckoutSession $checkoutSession;
    private LoggerInterface $logger;

    public function __construct(
        CheckoutSession $checkoutSession,
        LoggerInterface $logger
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
    }

    /**
     * @param Cart $subject
     * @param array $result
     * @return array
     */
    public function afterGetSectionData(Cart $subject, array $result): array
    {
        // Decorates the core cart section, which the mini-cart syncs on every
        // storefront page. A throw here (locked/corrupt quote, DB hiccup during
        // totals collection) would break the whole section site-wide, so degrade
        // to a 0.0 subtotal — that falls outside the £150–£30k gate, hiding the
        // banner cleanly.
        try {
            $totals = $this->checkoutSession->getQuote()->getTotals();
            $subtotal = $totals['subtotal'] ?? null;
            $result['iwocapay_subtotal_incl_tax'] = $subtotal
                ? (float) ($subtotal->getValueInclTax() ?: $subtotal->getValue())
                : 0.0;
            $result['iwocapay_subtotal_excl_tax'] = $subtotal
                ? (float) ($subtotal->getValueExclTax() ?: $subtotal->getValue())
                : 0.0;
        } catch (\Throwable $e) {
            $this->logger->error('iwocaPay cart banner subtotal error: ' . $e->getMessage());
            $result['iwocapay_subtotal_incl_tax'] = 0.0;
            $result['iwocapay_subtotal_excl_tax'] = 0.0;
        }

        return $result;
    }
}
