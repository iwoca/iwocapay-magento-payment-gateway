/**
 * Checkout order banner. Renders the iwocaPay cart banner web component in a
 * checkout region (order-summary sidebar and/or payment step) and keeps its
 * per-term amounts in sync with the live quote total.
 *
 * Finances the grand total (incl VAT, shipping and discount), minus tax when
 * the seller's prices exclude VAT, and re-computes whenever the quote totals
 * change (address, shipping method, coupon). Hidden when the order falls
 * outside iwocaPay's eligible range or the checkout banner is disabled.
 */
define([
    'uiComponent',
    'ko',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/totals',
    'Magento_Checkout/js/model/step-navigator'
], function (Component, ko, quote, totalsModel, stepNavigator) {
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

    return Component.extend({
        defaults: {
            template: 'Iwoca_Iwocapay/checkout-banner',
            region: 'payment'
        },

        initialize: function () {
            this._super();
            // Delivered via the checkout ConfigProvider (window.checkoutConfig),
            // which is CSP-safe — checkout enforces CSP and blocks inline scripts.
            var checkoutConfig = window.checkoutConfig || {};
            this.config = checkoutConfig.iwocapayBanner || null;
            this.visible = ko.observable(false);
            this.requests = {};
            return this;
        },

        /**
         * Whether the checkout banner is configured and has at least one term.
         */
        isEnabled: function () {
            return !!(this.config && this.config.checkoutEnabled && this.config.terms.length);
        },

        /**
         * The sidebar instance is persistent across steps; suppress it on the
         * payment step so it doesn't sit alongside the under-payment instance.
         */
        isSuppressedForStep: function () {
            return this.region === 'sidebar' && stepNavigator.isProcessed('shipping');
        },

        /**
         * The financed principal: grand total, less tax when prices exclude VAT.
         */
        getPrincipal: function () {
            var totals = quote.getTotals()();
            if (!totals) {
                return NaN;
            }
            var grand = parseFloat(totals.grand_total);
            if (this.config.includesVat) {
                return grand;
            }
            var taxSegment = totalsModel.getSegment('tax');
            var tax = taxSegment ? parseFloat(taxSegment.value) : 0;
            return grand - tax;
        },

        /**
         * Called from the template once the banner element is in the DOM.
         */
        registerElement: function (el) {
            this.element = el;
            this.applyToElement();
        },

        applyToElement: function () {
            var el = this.element;
            if (!el || !this.isEnabled() || this.isSuppressedForStep()) {
                this.visible(false);
                return;
            }

            var principal = this.getPrincipal();
            var eligible = !isNaN(principal) && principal >= MIN_AMOUNT && principal <= MAX_AMOUNT;
            this.visible(eligible);
            if (!eligible) {
                return;
            }

            el.setAttribute('theme', this.config.theme);
            el.setAttribute('includes-vat', this.config.includesVat ? 'true' : 'false');

            var self = this;
            this.config.terms.forEach(function (term) {
                var suffix = term.duration.replace(/_/g, '-');
                var amountAttr = 'amount-' + suffix;
                el.setAttribute('interest-' + suffix, term.interest);

                if (term.months === 1) {
                    el.setAttribute(amountAttr, formatGbp(principal));
                    return;
                }
                if (term.interest !== 'buyer-pays') {
                    el.setAttribute(amountAttr, formatGbp(principal / term.months));
                    return;
                }

                var key = principal + '_' + term.months + '_' + term.interest;
                if (!self.requests[key]) {
                    var url = '/iwocapay/banner/pricing?amount=' + encodeURIComponent(principal)
                        + '&months=' + encodeURIComponent(term.months)
                        + '&pricing=' + encodeURIComponent(term.interest);
                    self.requests[key] = fetch(url, {credentials: 'omit'}).then(function (res) { return res.json(); });
                }
                self.requests[key].then(function (data) {
                    if (data && data.repayment_amount) {
                        el.setAttribute(amountAttr, formatGbp(data.repayment_amount));
                    }
                }).catch(function () {});
            });
        },

        /**
         * KO afterRender hook: capture the element and re-apply on every totals
         * change so the banner tracks address / shipping / coupon updates.
         */
        afterRender: function (el) {
            var self = this;
            this.registerElement(el);

            // Re-render on totals change (address / shipping / coupon).
            quote.totals.subscribe(function () {
                self.requests = {};
                self.applyToElement();
            });

            // The sidebar instance must re-evaluate when the step changes so it
            // can hide on the payment step. isProcessed() reads each step's
            // isVisible observable, so a computed calling it re-runs on step
            // transitions.
            if (this.region === 'sidebar') {
                // Intentionally undisposed: afterRender fires once and this
                // component lives for the whole checkout, so the computed is
                // kept alive by the step observables it subscribes to — no leak.
                ko.computed(function () {
                    self.isSuppressedForStep(); // subscribes to step isVisible observables
                    self.applyToElement();
                });
            }
        }
    });
});
