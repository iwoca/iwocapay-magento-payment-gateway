define([
    'jquery',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/url-builder',
    'Magento_Checkout/js/model/full-screen-loader',
    'mage/storage',
    'mage/mage'
], function ($, Component, quote, urlBuilder, fullScreenLoader, storage) {
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
         * Retrieve the iwocapay icon from the config.
         * @return string
         */
        getIwocapayIconSrc: function () {
            return window.checkoutConfig.payment.iwocapay.iconSrc;
        },

        /**
         * Redirect to the Iwoca website to continue the payment process.
         */
        iwocaRedirect: function () {
            let iwocaOrderData = this.createIwocaOrder();
            console.log('iwocaOrderData:', iwocaOrderData);
        },

        /**
         * Create an order in Iwoca through the Magento API.
         */
        createIwocaOrder: function () {
            fullScreenLoader.startLoader();

            let createOrderUrl = urlBuilder.createUrl('/iwoca/create-order', {});

            return storage.post(
                createOrderUrl,
                JSON.stringify({
                    cartId: quote.getQuoteId()
                })
            ).done(
                function (response) {
                    console.log('Success!, Redirecting to Iwoca');
                    $.mage.redirect(response.order_url);
                }
            ).fail(
                function (response) {
                    /** @todo: handle failure */
                    console.log('It broke!', response);
                    fullScreenLoader.stopLoader();
                }
            );
        }
    });
});
