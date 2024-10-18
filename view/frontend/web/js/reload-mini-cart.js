require(["jquery", "Magento_Customer/js/customer-data"], function (
  $,
  customerData
) {
  "use strict";

  $(document).ready(function () {
    const isCartPage = window.location.href.includes("checkout/cart");
    const cartElementExists = $(".cart-container").length > 0;
    const isCart = isCartPage || cartElementExists;

    if (!isCart) return;

    customerData.reload(["cart"], true);
  });
});
