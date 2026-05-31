/**
 * Naive/Admin 单页脚本加载器。
 * 只负责防重复加载并注入 front-plain.js，实际路由、渲染和交互逻辑都在目标脚本中。
 */
(function () {
    'use strict';

    // 同一页面可能被 Blade 或局部刷新重复引入，使用全局标记避免重复注册事件。
    if (window.__CRM_PLAIN_NAIVE_LOADED__) {
        return;
    }

    window.__CRM_PLAIN_NAIVE_LOADED__ = true;

    // 通过动态脚本保持 Naive 前后台共用入口，并用版本号控制浏览器缓存。
    var script = document.createElement('script');
    script.src = '/js/naive-admin/front-plain.js?v=2026053110';
    script.defer = false;
    document.head.appendChild(script);
})();
