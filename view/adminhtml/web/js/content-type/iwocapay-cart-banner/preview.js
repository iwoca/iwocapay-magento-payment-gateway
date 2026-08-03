define([
    'Magento_PageBuilder/js/content-type/preview'
], function (Preview) {
    'use strict';

    function IwocapayCartBannerPreview(contentType, config, observableUpdater) {
        Preview.call(this, contentType, config, observableUpdater);
    }

    IwocapayCartBannerPreview.prototype = Object.create(Preview.prototype);
    IwocapayCartBannerPreview.prototype.constructor = IwocapayCartBannerPreview;

    return IwocapayCartBannerPreview;
});
