(function () {
    'use strict';

    if (window.__CRM_PLAIN_NAIVE_LOADED__) {
        return;
    }

    window.__CRM_PLAIN_NAIVE_LOADED__ = true;

    var script = document.createElement('script');
    script.src = '/js/naive-admin/front-plain.js?v=2026052907';
    script.defer = false;
    document.head.appendChild(script);
})();
