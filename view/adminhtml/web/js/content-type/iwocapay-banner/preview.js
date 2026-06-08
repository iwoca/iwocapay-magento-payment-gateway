define([
    'Magento_PageBuilder/js/content-type/preview'
], function (Preview) {
    'use strict';

    function IwocapayBannerPreview(contentType, config, observableUpdater) {
        Preview.call(this, contentType, config, observableUpdater);
    }

    IwocapayBannerPreview.prototype = Object.create(Preview.prototype);
    IwocapayBannerPreview.prototype.constructor = IwocapayBannerPreview;

    return IwocapayBannerPreview;
});
