define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
],
function (Component, rendererList) {
    'use strict';

    let config = window.checkoutConfig.payment,
        methodCode = 'iwocapay';

    if (config[methodCode] && config[methodCode].isActive) {
        rendererList.push(
            {
                type: methodCode,
                component: 'Iwoca_Iwocapay/js/view/payment/method-renderer/iwocapay'
            }
        );
    }

    return Component.extend({});
});
