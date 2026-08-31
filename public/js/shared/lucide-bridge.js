// Created by PhpStorm.
// Project name co_crmv5.
// User: Huang Gang
// Date: 2026/08/01
// Time: 21:27
(function (window, document) {
    'use strict';

    var layuiMap = {
        'layui-icon-about': 'circle-help',
        'layui-icon-add-1': 'plus',
        'layui-icon-add-circle': 'circle-plus',
        'layui-icon-app': 'panels-top-left',
        'layui-icon-auz': 'badge-check',
        'layui-icon-camera': 'camera',
        'layui-icon-cart': 'package',
        'layui-icon-cellphone': 'smartphone',
        'layui-icon-chart': 'chart-no-axes-column-increasing',
        'layui-icon-chart-screen': 'presentation',
        'layui-icon-circle': 'circle',
        'layui-icon-close': 'x',
        'layui-icon-close-fill': 'circle-x',
        'layui-icon-component': 'boxes',
        'layui-icon-console': 'gauge',
        'layui-icon-date': 'calendar-days',
        // 验证码/数据范围类菜单图标：映射为钥匙图标，语义为“校验/凭据”。
        'layui-icon-vercode': 'key-round',
        'layui-icon-diamond': 'gem',
        'layui-icon-dollar': 'circle-dollar-sign',
        // 当前 Lucide vendor 暴露的是 circle-arrow-down，不再使用旧别名 circle-down，避免下载类图标无法渲染。
        'layui-icon-download-circle': 'circle-arrow-down',
        'layui-icon-edit': 'square-pen',
        'layui-icon-email': 'mail',
        // 组别配置/标签页类菜单图标：映射为面板顶部图标，语义为“分页/分组导航”。
        'layui-icon-tabs': 'panel-top',
        // 凭证审核/表单类菜单图标：映射为文件文本图标，语义为“单据/凭证”。
        'layui-icon-form': 'file-text',
        // 注销申请/删除类菜单图标：映射为垃圾桶图标，语义为“移除/注销”。
        'layui-icon-delete': 'trash-2',
        'layui-icon-export': 'file-down',
        'layui-icon-file-b': 'file-text',
        'layui-icon-friends': 'user-round-plus',
        'layui-icon-gift': 'gift',
        'layui-icon-group': 'network',
        'layui-icon-home': 'house',
        'layui-icon-light': 'zap',
        'layui-icon-list': 'list',
        'layui-icon-location': 'map-pin',
        'layui-icon-log': 'history',
        'layui-icon-moon': 'moon',
        'layui-icon-note': 'notebook-tabs',
        'layui-icon-notice': 'bell',
        'layui-icon-ok': 'check',
        'layui-icon-ok-circle': 'circle-check-big',
        'layui-icon-password': 'key-round',
        'layui-icon-play': 'circle-play',
        'layui-icon-reduce-circle': 'circle-minus',
        'layui-icon-refresh': 'refresh-cw',
        'layui-icon-release': 'send',
        'layui-icon-rmb': 'badge-dollar-sign',
        'layui-icon-search': 'search',
        'layui-icon-set': 'settings',
        'layui-icon-shrink-right': 'panel-left-close',
        'layui-icon-speaker': 'volume-2',
        'layui-icon-spread-left': 'panel-left-open',
        'layui-icon-table': 'table-2',
        'layui-icon-team': 'users-round',
        'layui-icon-template': 'layout-template',
        'layui-icon-template-1': 'wallet-cards',
        'layui-icon-theme': 'palette',
        'layui-icon-transfer': 'arrow-left-right',
        'layui-icon-tree': 'git-branch',
        'layui-icon-upload': 'upload',
        'layui-icon-upload-drag': 'cloud-upload',
        'layui-icon-user': 'user-round',
        'layui-icon-username': 'circle-user-round',
        'layui-icon-water': 'waves',
        'layui-icon-website': 'languages'
    };

    var fontAwesomeMap = {
        'fa-angle-left': 'chevron-left',
        'fa-bars': 'menu',
        'fa-bolt': 'zap',
        'fa-box': 'package',
        'fa-chart-bar': 'chart-no-axes-column-increasing',
        'fa-chart-pie': 'chart-pie',
        'fa-check-circle': 'circle-check-big',
        'fa-circle': 'circle',
        'fa-coins': 'badge-dollar-sign',
        'fa-dollar-sign': 'circle-dollar-sign',
        'fa-envelope': 'mail',
        'fa-exchange-alt': 'arrow-left-right',
        'fa-gift': 'gift',
        'fa-globe': 'languages',
        'fa-history': 'history',
        'fa-id-card': 'contact-round',
        'fa-info-circle': 'circle-help',
        'fa-key': 'key-round',
        'fa-lock': 'lock-keyhole',
        'fa-map-marker-alt': 'map-pin',
        'fa-minus-circle': 'circle-minus',
        'fa-money-bill-wave': 'banknote',
        'fa-moon': 'moon',
        'fa-paper-plane': 'send',
        'fa-play-circle': 'circle-play',
        'fa-plus-circle': 'circle-plus',
        'fa-receipt': 'receipt-text',
        'fa-sitemap': 'network',
        'fa-stream': 'list-tree',
        'fa-sun': 'sun',
        'fa-tachometer-alt': 'gauge',
        'fa-user': 'user-round',
        'fa-user-edit': 'user-round-pen',
        'fa-user-friends': 'users-round',
        'fa-user-times': 'user-round-x',
        'fa-users': 'users-round',
        'fa-wallet': 'wallet-cards'
    };

    var frameRequested = false;

    function iconNameFor(element) {
        var classes = Array.prototype.slice.call(element.classList || []);
        var i;

        for (i = 0; i < classes.length; i += 1) {
            if (layuiMap[classes[i]]) {
                return layuiMap[classes[i]];
            }
        }
        for (i = 0; i < classes.length; i += 1) {
            if (fontAwesomeMap[classes[i]]) {
                return fontAwesomeMap[classes[i]];
            }
        }

        return element.getAttribute('data-lucide') || '';
    }

    function prepareIcons(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var candidates = scope.querySelectorAll(
            '[data-lucide], .layui-icon, .fas, .far, .fab, [class*=" layui-icon-"], [class^="layui-icon-"]'
        );

        Array.prototype.forEach.call(candidates, function (element) {
            if (element.tagName && element.tagName.toLowerCase() === 'svg') {
                return;
            }

            var iconName = iconNameFor(element);
            if (!iconName) {
                return;
            }

            Array.prototype.slice.call(element.classList || []).forEach(function (className) {
                if (className === 'layui-icon'
                    || className === 'fas'
                    || className === 'far'
                    || className === 'fab'
                    || className.indexOf('layui-icon-') === 0
                    || className.indexOf('fa-') === 0) {
                    element.classList.remove(className);
                }
            });
            element.classList.add('crm-lucide-icon');
            element.setAttribute('data-lucide', iconName);
            if (!element.hasAttribute('aria-label') && !element.hasAttribute('title')) {
                element.setAttribute('aria-hidden', 'true');
            }
        });
    }

    function renderIcons(root) {
        if (!window.lucide || typeof window.lucide.createIcons !== 'function') {
            return;
        }

        prepareIcons(root || document);
        window.lucide.createIcons({
            attrs: {
                'stroke-width': 1.8,
                'focusable': 'false'
            }
        });
    }

    function scheduleRefresh(root) {
        if (frameRequested) {
            return;
        }
        frameRequested = true;
        window.requestAnimationFrame(function () {
            frameRequested = false;
            renderIcons(root || document);
        });
    }

    function installObserver() {
        if (!window.MutationObserver || !document.body) {
            return;
        }

        new MutationObserver(function (mutations) {
            var changed = mutations.some(function (mutation) {
                return mutation.addedNodes && mutation.addedNodes.length > 0;
            });
            if (changed) {
                scheduleRefresh(document);
            }
        }).observe(document.body, {childList: true, subtree: true});
    }

    function installPointerLight() {
        if (!window.matchMedia || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        var pending = false;
        var x = '50%';
        var y = '20%';
        document.addEventListener('pointermove', function (event) {
            x = event.clientX + 'px';
            y = event.clientY + 'px';
            if (pending) {
                return;
            }
            pending = true;
            window.requestAnimationFrame(function () {
                pending = false;
                document.documentElement.style.setProperty('--crm-pointer-x', x);
                document.documentElement.style.setProperty('--crm-pointer-y', y);
            });
        }, {passive: true});
    }

    function boot() {
        renderIcons(document);
        installObserver();
        installPointerLight();
    }

    window.CrmIcons = {
        refresh: function (root) {
            scheduleRefresh(root || document);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, {once: true});
    } else {
        boot();
    }
}(window, document));
