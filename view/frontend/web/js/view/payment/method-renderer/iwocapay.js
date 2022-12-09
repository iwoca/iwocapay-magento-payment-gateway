define([
    'jquery',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/action/redirect-on-success',
    'mage/mage'
], function ($, Component, redirectOnSuccessAction) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Iwoca_Iwocapay/payment/iwocapay/form'
        },

        /**
         * Initialize view.
         *
         * @return {exports}
         */
        initialize: function () {
            this._super();
            redirectOnSuccessAction.redirectUrl = window.checkoutConfig.payment.iwocapay.iwocaCreateOrderUrl;
        },

        /**
         * Retrieve the iwocapay icon from the config.
         * @return string
         */
        getIwocapayIconSrc: function () {
            return window.checkoutConfig.payment.iwocapay.iconSrc;
        }
    });
});
