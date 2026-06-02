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
        },

        /**
         * Update redirect URL to handle order creation and redirect to Iwoca payment platform.
         */
        afterPlaceOrder: function () {
            this._super();
            redirectOnSuccessAction.redirectUrl = window.checkoutConfig.payment.iwocapay.iwocaCreateOrderUrl;
        },

        /**
         * Retrieve the iwocapay icon from the config.
         * @return string
         */
        getIwocapayIconSrc: function () {
            return window.checkoutConfig.payment.iwocapay.iconSrc;
        },

        /**
         * Retrieve the payment option title from the config.
         * @return string
         */
        getPaymentOptionTitle: function () {
            return window.checkoutConfig.payment[this.getCode()].title;
        },

        /**
         * Retrieve the payment option subtitle from the config.
         * @return string
         */
        getPaymentOptionSubtitle: function () {
            return window.checkoutConfig.payment[this.getCode()].subtitle;
        },

        /**
         * Retrieve the payment option button title from the config.
         * @return string
         */
        getPaymentOptionCallToAction: function () {
            return window.checkoutConfig.payment[this.getCode()].call_to_action;
        }
    });
});
