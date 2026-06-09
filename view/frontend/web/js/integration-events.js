(function () {
    var fired = {};

    window.fireIwocapayBannerEvent = function (eventType) {
        if (fired[eventType]) return;
        fired[eventType] = true;

        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/iwocapay/tracking/event', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.send('event_type=' + encodeURIComponent(eventType));
    };
})();
