define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list',
    'Magento_Checkout/js/model/quote'
],
function (Component, rendererList, quote) {
    'use strict';

    let config = window.checkoutConfig.payment,
        methodCode = 'iwocapay',
        isActive = config[methodCode] && config[methodCode].isActive,
        isAllowed = true;

    if (isActive) {
        if (window.checkoutConfig.payment.iwocapay.isPayLaterOnly) {
            let quoteGrandTotal = quote.totals()['grand_total'],
                minAmount = window.checkoutConfig.payment.iwocapay.minAmount,
                maxAmount = window.checkoutConfig.payment.iwocapay.maxAmount;
            if (quoteGrandTotal <= minAmount || quoteGrandTotal >= maxAmount) {
                isAllowed = false;
            }
        }

        if (isAllowed) {
            rendererList.push(
                {
                    type: methodCode,
                    component: 'Iwoca_Iwocapay/js/view/payment/method-renderer/iwocapay'
                }
            );
        }
    }

    return Component.extend({});
});
