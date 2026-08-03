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
    'Magento_Customer/js/customer-data'
], function ($, customerData) {
    'use strict';

    var MIN_AMOUNT = 150;
    var MAX_AMOUNT = 30000;
    var TAG = 'iwocapay-price-calculator-cart-banner';

    function formatGbp(value) {
        return new Intl.NumberFormat('en-GB', {
            style: 'currency',
            currency: 'GBP'
        }).format(value);
    }

    /**
     * Ensure each marker holds a banner element, returning them all.
     */
    function ensureElements() {
        var elements = [];
        document.querySelectorAll('[data-iwocapay-cart-banner]').forEach(function (marker) {
            var el = marker.querySelector(TAG);
            if (!el) {
                el = document.createElement(TAG);
                marker.appendChild(el);
            }
            elements.push(el);
        });
        return elements;
    }

    /**
     * Set the per-term amount + interest attributes on the banner element,
     * fetching interest-bearing repayments and computing interest-free ones.
     * A shared request cache dedupes identical fetches across banners.
     */
    function applyTerms(el, config, subtotal, requests) {
        config.terms.forEach(function (term) {
            // Component attrs use the duration enum with underscores -> hyphens,
            // e.g. "3_months" -> "amount-3-months" / "interest-3-months".
            var suffix = term.duration.replace(/_/g, '-');
            var amountAttr = 'amount-' + suffix;
            var interestAttr = 'interest-' + suffix;

            el.setAttribute(interestAttr, term.interest);

            // The 30-day offer shows "Pay nothing for 30 days" — no figure to fetch.
            if (term.months === 1) {
                el.setAttribute(amountAttr, formatGbp(subtotal));
                return;
            }

            // Interest-free terms are pure division — compute client-side.
            if (term.interest !== 'buyer-pays') {
                el.setAttribute(amountAttr, formatGbp(subtotal / term.months));
                return;
            }

            var key = subtotal + '_' + term.months + '_' + term.interest;
            if (!requests[key]) {
                var url = '/iwocapay/banner/pricing?amount=' + encodeURIComponent(subtotal)
                    + '&months=' + encodeURIComponent(term.months)
                    + '&pricing=' + encodeURIComponent(term.interest);
                requests[key] = fetch(url, {credentials: 'omit'}).then(function (res) { return res.json(); });
            }
            requests[key].then(function (data) {
                if (data && data.repayment_amount) {
                    el.setAttribute(amountAttr, formatGbp(data.repayment_amount));
                }
            }).catch(function () {});
        });
    }

    function render(config, subtotal) {
        var elements = ensureElements();
        if (!elements.length) {
            return;
        }

        var eligible = subtotal >= MIN_AMOUNT && subtotal <= MAX_AMOUNT && config.terms.length > 0;
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
            applyTerms(el, config, subtotal, requests);
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
