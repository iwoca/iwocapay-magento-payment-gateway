// Don't add js/integration-events.js here: banner-script.phtml calls its
// window.fireIwocapayBannerEvent behind a truthiness guard, so as an async dep
// it would lose that race and silently stop view events.
var config = {
    deps: [
        'Iwoca_Iwocapay/js/reload-mini-cart',
        'Iwoca_Iwocapay/js/cart-banner-pricing'
    ]
};
