define([
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list',
        'Magento_Checkout/js/model/quote'
    ],
    function (Component, rendererList, quote) {
        'use strict';

        var config = window.checkoutConfig.payment;

        // - do we have all the payment keys that we need?
        var requiredKeys = ['iwocapay', 'iwocapay_paylater', 'iwocapay_paynow'];
        for (var i = 0; i < requiredKeys.length; i++) {
            if (!(requiredKeys[i] in config)) {
                return Component.extend({});
            }
        }

        // - is iwocapay enabled?
        var payLater = config.iwocapay_paylater;
        var payNow = config.iwocapay_paynow;
        var shared = config.iwocapay;
        var isActive = shared.isActive;

        if (!isActive) {
            return Component.extend({});
        }

        // - verify max and min amounts
        var quoteGrandTotal = quote.totals()['grand_total'];
        var minPayLater = payLater.minAmount;
        var maxPayLater = payLater.maxAmount;
        var minPayNow = payNow.minAmount;
        var maxPayNow = payNow.maxAmount;

        // - pay later
        if (quoteGrandTotal >= minPayLater && quoteGrandTotal <= maxPayLater) {
            rendererList.push({
                type: 'iwocapay_paylater',
                component: 'Iwoca_Iwocapay/js/view/payment/method-renderer/iwocapay'
            });
        }

        // - pay now
        if (!shared.isPayLaterOnly && quoteGrandTotal >= minPayNow && quoteGrandTotal <= maxPayNow) {
            rendererList.push({
                type: 'iwocapay_paynow',
                component: 'Iwoca_Iwocapay/js/view/payment/method-renderer/iwocapay'
            });
        }

        return Component.extend({});
    }
);
