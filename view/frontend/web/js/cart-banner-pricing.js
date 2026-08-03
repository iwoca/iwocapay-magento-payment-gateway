/**
 * Populates seller-placed cart banners from the live basket total.
 *
 * A PageBuilder "iwocaPay Cart Banner" tile renders a bare marker
 * (<div data-iwocapay-cart-banner>). This script injects the banner web
 * component into each marker, then fills its per-term amounts from the live
 * cart subtotal (VAT-inclusive) and the banner config emitted by
 * banner-script.phtml. It re-runs whenever the cart changes, and hides the
 * banner when the basket falls outside iwocaPay's eligible range.
 */
require([
    'jquery',
    'Magento_Customer/js/customer-data',
    'Iwoca_Iwocapay/js/cart-pricing'
], function ($, customerData, cartPricing) {
    'use strict';

    /**
     * Ensure each marker holds a banner element, returning them all.
     */
    function ensureElements() {
        var elements = [];
        document.querySelectorAll('[data-iwocapay-cart-banner]').forEach(function (marker) {
            var el = marker.querySelector(cartPricing.TAG);
            if (!el) {
                el = document.createElement(cartPricing.TAG);
                marker.appendChild(el);
            }
            elements.push(el);
        });
        return elements;
    }

    function render(config, subtotal) {
        var elements = ensureElements();
        if (!elements.length) {
            return;
        }

        var eligible = cartPricing.isEligible(subtotal) && config.terms.length > 0;
        var requests = {};

        elements.forEach(function (el) {
            var host = el.parentElement;
            if (!eligible) {
                host.style.display = 'none';
                return;
            }
            host.style.display = '';
            el.setAttribute('theme', config.theme);
            // Label follows the seller's VAT toggle so it agrees with the
            // financed figure (we finance the incl- or excl-tax subtotal to
            // match — see the subtotalField selection below).
            el.setAttribute('includes-vat', config.includesVat ? 'true' : 'false');
            cartPricing.applyTerms(el, config.terms, subtotal, requests);

            // Fires CUSTOMER_VIEWED_CART_BANNER once the banner is actually
            // on-screen (e.g. the mini-cart is opened), not just eligible.
            cartPricing.fireViewOnVisible(el);
        });
    }

    $(function () {
        var config = window.iwocapayBannerConfig;
        if (!config || !document.querySelector('[data-iwocapay-cart-banner]')) {
            return;
        }

        var cart = customerData.get('cart');

        // Finance the subtotal matching the seller's VAT setting so the figure
        // and the "inc/excl VAT" label agree.
        var subtotalField = config.includesVat
            ? 'iwocapay_subtotal_incl_tax'
            : 'iwocapay_subtotal_excl_tax';

        function update() {
            var data = cart();
            var subtotal = data && typeof data[subtotalField] !== 'undefined'
                ? parseFloat(data[subtotalField])
                : NaN;
            render(config, isNaN(subtotal) ? 0 : subtotal);
        }

        cart.subscribe(update);
        update();

        // The cart section is populated lazily on first load; ensure we have it.
        if (!cart() || typeof cart()[subtotalField] === 'undefined') {
            customerData.reload(['cart'], false);
        }
    });
});
