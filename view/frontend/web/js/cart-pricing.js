/**
 * Shared cart-banner pricing helpers, used by both the seller-placed cart tile
 * (cart-banner-pricing.js) and the checkout banner (view/checkout-banner.js).
 *
 * Keeps the eligibility range, the banner tag, GBP formatting and the per-term
 * amount logic in one place so the two surfaces can't drift apart.
 */
define([], function () {
    'use strict';

    // iwocaPay's lending range — the banner only shows for totals within it.
    var MIN_AMOUNT = 150;
    var MAX_AMOUNT = 30000;
    var TAG = 'iwocapay-price-calculator-cart-banner';

    function formatGbp(value) {
        return new Intl.NumberFormat('en-GB', {
            style: 'currency',
            currency: 'GBP'
        }).format(value);
    }

    function isEligible(amount) {
        return !isNaN(amount) && amount >= MIN_AMOUNT && amount <= MAX_AMOUNT;
    }

    // A cart banner can sit in a collapsed mini-cart or below the fold — present
    // and eligible in the DOM long before the customer sees it. Fire the view
    // event only once the element actually intersects the viewport (mini-cart
    // opened / scrolled into view). Where IntersectionObserver is unavailable,
    // fall back to firing immediately. The 24h + in-memory dedup in
    // integration-events.js keeps this to one view per customer per day.
    var viewObserver = window.IntersectionObserver
        ? new IntersectionObserver(function (entries, observer) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    observer.unobserve(entry.target);
                    if (window.fireIwocapayBannerEvent) {
                        window.fireIwocapayBannerEvent('CUSTOMER_VIEWED_CART_BANNER');
                    }
                }
            });
        })
        : null;

    function fireViewOnVisible(el) {
        if (viewObserver) {
            viewObserver.observe(el);
        } else if (window.fireIwocapayBannerEvent) {
            window.fireIwocapayBannerEvent('CUSTOMER_VIEWED_CART_BANNER');
        }
    }

    /**
     * Set the per-term amount + interest attributes on the banner element,
     * financing `principal`. Interest-free terms (incl. the interest-free
     * 30-day offer, where months === 1 makes this the full total) are pure
     * division, and interest-bearing terms — 30-day included — fetch (and
     * cache) a repayment figure from the pricing endpoint.
     *
     * `requests` is a caller-owned cache object that dedupes identical fetches
     * across re-renders — pass the same object each time and reset it (to {})
     * whenever the financed amount changes.
     */
    function applyTerms(el, terms, principal, requests) {
        terms.forEach(function (term) {
            // Component attrs use the duration enum with underscores -> hyphens,
            // e.g. "3_months" -> "amount-3-months" / "interest-3-months".
            var suffix = term.duration.replace(/_/g, '-');
            var amountAttr = 'amount-' + suffix;
            el.setAttribute('interest-' + suffix, term.interest);

            // Interest-free terms are pure division — compute client-side.
            // (For the 30-day term months === 1, so this is the full total.)
            if (term.interest !== 'buyer-pays') {
                el.setAttribute(amountAttr, formatGbp(principal / term.months));
                return;
            }

            var key = principal + '_' + term.months + '_' + term.interest;
            if (!requests[key]) {
                var url = '/iwocapay/banner/pricing?amount=' + encodeURIComponent(principal)
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

    return {
        MIN_AMOUNT: MIN_AMOUNT,
        MAX_AMOUNT: MAX_AMOUNT,
        TAG: TAG,
        formatGbp: formatGbp,
        isEligible: isEligible,
        applyTerms: applyTerms,
        fireViewOnVisible: fireViewOnVisible
    };
});
