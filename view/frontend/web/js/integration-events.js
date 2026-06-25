(function () {
    var fired = {};

    var VIEW_PREFIX = 'iwocapay_viewed:';
    var VIEW_TTL_MS = 24 * 60 * 60 * 1000; // 24h — one view event per customer per day

    // localStorage is scoped per-origin, so the seller's domain already
    // namespaces these keys: a buyer moving across seller sites re-fires.
    function dedupKey(eventType) {
        return VIEW_PREFIX + eventType;
    }

    // Returns true if this view event was already sent within the TTL window.
    // Fails open when storage is unavailable so events are never dropped.
    function hasFiredRecently(eventType) {
        try {
            var raw = window.localStorage.getItem(dedupKey(eventType));
            if (!raw) return false;
            var entry = JSON.parse(raw);
            if (!entry || Date.now() - entry.ts > VIEW_TTL_MS) {
                window.localStorage.removeItem(dedupKey(eventType));
                return false;
            }
            return true;
        } catch (e) {
            return false;
        }
    }

    function markFired(eventType) {
        try {
            window.localStorage.setItem(
                dedupKey(eventType),
                JSON.stringify({ ts: Date.now() })
            );
        } catch (e) {
            /* storage full or unavailable — skip persisting */
        }
    }

    window.fireIwocapayBannerEvent = function (eventType) {
        if (fired[eventType]) return;
        fired[eventType] = true;

        // Persistently dedup view events across page loads/navigations.
        // Clicks and other intent signals fire every time.
        if (eventType.indexOf('CUSTOMER_VIEWED_') === 0) {
            if (hasFiredRecently(eventType)) return;
            markFired(eventType);
        }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/iwocapay/tracking/event', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.send('event_type=' + encodeURIComponent(eventType));
    };
})();
