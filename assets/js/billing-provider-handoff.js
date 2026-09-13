(function () {
    'use strict';

    var link = document.getElementById(
        'billing-provider-handoff-link'
    );

    if (!link) {
        return;
    }

    /*
     * The server already canonicalized the provider checkout URL. Using
     * replace() keeps the transient POST-response handoff page out of normal
     * browser back-navigation while preserving the visible fallback link.
     */
    window.location.replace(link.href);
})();
