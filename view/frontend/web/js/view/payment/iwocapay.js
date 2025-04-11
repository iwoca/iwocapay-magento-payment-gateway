define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list',
    'Magento_Checkout/js/model/quote'
],
function (Component, rendererList, quote) {
    'use strict';

    let config = window.checkoutConfig.payment;

    // - do we have all the payment keys that we need?
    if (!['iwocapay', 'iwocapay_paylater', 'iwocapay_paynow'].every(key => key in config)) {
        return Component.extend({});
    }

    // - is iwocapay enabled?
    let payLater = config.iwocapay_paylater,
        payNow = config.iwocapay_paynow,
        shared = config.iwocapay,
        isActive = shared.isActive;

    if (!isActive) {
        return Component.extend({});
    }

    // - verify max and min amounts
    let quoteGrandTotal = quote.totals()['grand_total'],
        minPayLater = payLater.minAmount,
        maxPayLater = payLater.maxAmount,
        minPayNow = payNow.minAmount,
        maxPayNow = payNow.maxAmount;

    // - pay later
    if (quoteGrandTotal >= minPayLater && quoteGrandTotal <= maxPayLater) {
        rendererList.push(
            {
                type: 'iwocapay_paylater',
                component: 'Iwoca_Iwocapay/js/view/payment/method-renderer/iwocapay'
            }
        );
    }

    // - pay now
    if (!config.iwocapay.isPayLaterOnly && quoteGrandTotal >= minPayNow && quoteGrandTotal <= maxPayNow) {
        rendererList.push(
            {
                type: 'iwocapay_paynow',
                component: 'Iwoca_Iwocapay/js/view/payment/method-renderer/iwocapay'
            }
        );
    }

    return Component.extend({});
});
