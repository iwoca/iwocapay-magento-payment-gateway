require(["jquery", "Magento_Customer/js/customer-data", "domReady!"], function ($, customerData) {
    "use strict";

    const isCartPage = window.location.href.includes("checkout/cart");                                                                                                                                                         
    const cartElementExists = $(".cart-container").length > 0;                                                                                                                                                                 
    const isCart = isCartPage || cartElementExists;                                                                                                                                                                            
                                                                                                                                                                                                                               
    if (!isCart) {
        return;
    }
                                                
    customerData.getInitCustomerData().done(function () {                                                                                                                                                                      
        customerData.reload(["cart"], true);                                                                                                                                                                                     
    });
});
