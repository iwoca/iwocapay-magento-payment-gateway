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
    'Magento_Checkout/js/model/step-navigator',
    'Iwoca_Iwocapay/js/cart-pricing'
], function (Component, ko, quote, totalsModel, stepNavigator, cartPricing) {
    'use strict';

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
            var eligible = cartPricing.isEligible(principal);
            this.visible(eligible);
            if (!eligible) {
                return;
            }

            el.setAttribute('theme', this.config.theme);
            el.setAttribute('includes-vat', this.config.includesVat ? 'true' : 'false');

            cartPricing.applyTerms(el, this.config.terms, principal, this.requests);

            // Fires CUSTOMER_VIEWED_CART_BANNER once the banner is on-screen,
            // not merely rendered — the sidebar instance can be below the fold.
            cartPricing.fireViewOnVisible(el);
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
