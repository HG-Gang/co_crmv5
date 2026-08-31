// Created by PhpStorm.
// Project name co_crmv5.
// User: Huang Gang
// Date: 2026/08/17
// Time: 22:06
/**
 * 全局皮肤同步器。
 *
 * 作用：
 * 1. 统一 Layui、Naive UI 与旧前端页面的皮肤 key，避免不同模块各存一份主题状态。
 * 2. 兼容旧项目曾经使用过的颜色名，把旧值映射到当前支持的主题 key。
 * 3. 同步 html/body 属性、crm-skin-* 根节点类名、图标按钮和 Layui 菜单选中状态。
 */
(function (window, document) {
    'use strict';

    // 如果页面已经提前注入 CrmTheme，只重新应用当前主题，避免重复注册 storage 监听。
    if (window.CrmTheme) {
        window.CrmTheme.apply(window.CrmTheme.get(), { broadcast: false });
        return;
    }

    var fallbackValues = [
        'light', 'dark', 'sea', 'warm', 'contrast',
        'clear-blue', 'mint-ledger', 'cloud-minimal', 'sea-salt', 'indigo-order',
        'coral-note', 'celadon-ops', 'sunlit-mark', 'steel-table', 'ink-sidebar'
    ];
    function readThemeCatalog() {
        var catalog = document.getElementById('crm-theme-values');
        var parsed;

        if (catalog) {
            try {
                parsed = JSON.parse(catalog.textContent || '[]');
                if (Array.isArray(parsed) && parsed.length) {
                    return parsed.slice();
                }
            } catch (e) {}
        }

        if (Array.isArray(window.CRM_THEME_VALUES) && window.CRM_THEME_VALUES.length) {
            return window.CRM_THEME_VALUES.slice();
        }

        return fallbackValues.slice();
    }
    var values = readThemeCatalog();
    var storageKeys = [
        'front_theme',
        'crm_theme',
        'crm_color_mode',
        'color_mode',
        'colorMode',
        'tozo_color_mode',
        'ui_style'
    ];
    var writeKeys = ['front_theme', 'crm_theme', 'crm_color_mode'];
    var legacyMap = {
        emerald: 'warm',
        green: 'warm',
        blue: 'sea',
        cyan: 'sea',
        amber: 'contrast',
        orange: 'contrast',
        violet: 'contrast',
        purple: 'contrast',
        black: 'dark',
        night: 'dark',
        graphite: 'dark',
        slate: 'dark',
        'dark-mode': 'dark',
        'light-mode': 'light',
        azure: 'clear-blue',
        sky: 'clear-blue',
        mint: 'mint-ledger',
        minimal: 'cloud-minimal',
        salt: 'sea-salt',
        indigo: 'indigo-order',
        coral: 'coral-note',
        celadon: 'celadon-ops',
        sunlit: 'sunlit-mark',
        steel: 'steel-table',
        ink: 'ink-sidebar'
    };
    var currentTheme = '';

    /**
     * 将任意来源的皮肤值标准化为当前支持的主题 key。
     * 这里保留 key 不改名，是为了兼容已保存的 localStorage、后端 cookie 和旧页面 CSS。
     */
    function normalize(value) {
        value = String(value || '').trim().toLowerCase();
        if (!value || value === 'layui' || value === 'naive') {
            return '';
        }
        if (value === 'auto' || value === 'system') {
            return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        value = legacyMap[value] || value;
        return values.indexOf(value) === -1 ? '' : value;
    }

    // localStorage 在隐私模式或异常环境可能不可用，所以读取必须显式兜底。
    function storageGet(key) {
        try {
            return window.localStorage ? window.localStorage.getItem(key) : '';
        } catch (e) {
            return '';
        }
    }

    // 写入失败不影响页面主流程，主题切换最多退化为当前页面生效。
    function storageSet(key, value) {
        try {
            if (window.localStorage) {
                window.localStorage.setItem(key, value);
            }
        } catch (e) {}
    }

    // 从多个历史 key 中读取第一个有效皮肤，兼容旧 Layui、旧 Naive 和当前统一 key。
    function readStored() {
        var i;
        var theme;

        for (i = 0; i < storageKeys.length; i += 1) {
            theme = normalize(storageGet(storageKeys[i]));
            if (theme) {
                return theme;
            }
        }

        return 'light';
    }

    function resolveTheme(value) {
        if (value === undefined || value === null || String(value).trim() === '') {
            return readStored();
        }

        return normalize(value) || 'light';
    }

    // 写入当前标准 key，并同步 cookie 给服务端 Blade 判断明暗模式。
    function writeStored(theme) {
        var i;

        for (i = 0; i < writeKeys.length; i += 1) {
            storageSet(writeKeys[i], theme);
        }

        document.cookie = 'ui_style=' + (theme === 'dark' ? 'dark' : 'light') + '; path=/; max-age=31536000; SameSite=Lax';
    }

    // 根节点只保留一套 crm-skin-* 类名，避免多次切换后旧皮肤类互相叠加。
    function syncSkinClasses(theme) {
        var roots = document.querySelectorAll('.crm-root, [data-crm-theme-root]');
        var i;

        for (i = 0; i < roots.length; i += 1) {
            roots[i].className = roots[i].className
                .replace(/\b(?:crm-skin|skin|theme)-[a-z0-9-]+\b/g, '')
                .replace(/\s{2,}/g, ' ')
                .trim() + ' crm-skin-' + theme;
        }
    }

    // 同步图标按钮和 Layui dd 选中状态，让不同皮肤入口显示一致。
    function syncControls(theme) {
        var controls = document.querySelectorAll('[data-crm-skin-select]');
        var switches = document.querySelectorAll('[data-theme], [data-skin]');
        var i;
        var option;
        var switchTheme;

        for (i = 0; i < controls.length; i += 1) {
            controls[i].value = theme;
            option = controls[i].querySelector('option[value="' + theme + '"]');
            if (option) {
                option.selected = true;
            }
        }

        for (i = 0; i < switches.length; i += 1) {
            switchTheme = normalize(switches[i].getAttribute('data-theme') || switches[i].getAttribute('data-skin'));
            if (!switchTheme) {
                continue;
            }
            var isCurrent = switchTheme === theme;
            switches[i].classList.toggle('is-current', isCurrent);
            if (switches[i].getAttribute('role') === 'menuitemradio') {
                switches[i].setAttribute('aria-current', isCurrent ? 'true' : 'false');
                switches[i].setAttribute('aria-checked', isCurrent ? 'true' : 'false');
            }
            if (switches[i].parentElement && switches[i].parentElement.tagName.toLowerCase() === 'dd') {
                switches[i].parentElement.classList.toggle('layui-this', isCurrent);
            }
        }
    }

    // 将主题写到 html/body 属性和根节点类名，CSS 通过这些属性切换变量。
    function setDomTheme(theme) {
        var docEl = document.documentElement;
        var mode = theme === 'dark' ? 'dark' : 'light';

        docEl.setAttribute('data-front-theme', theme);
        docEl.setAttribute('data-crm-theme', theme);
        docEl.setAttribute('data-crmui-theme-mode', mode);
        docEl.style.colorScheme = mode;

        if (document.body) {
            document.body.setAttribute('data-front-theme', theme);
            document.body.setAttribute('data-crm-theme', theme);
            document.body.setAttribute('data-crmui-theme-mode', mode);
            document.body.style.colorScheme = mode;
        }

        syncSkinClasses(theme);
        syncControls(theme);
    }

    // 广播主题变化，Naive 单页脚本和 Layui 壳层都通过该事件刷新局部状态。
    function broadcast(theme) {
        var event;

        try {
            event = new CustomEvent('crm:theme-change', { detail: { theme: theme } });
        } catch (e) {
            event = document.createEvent('CustomEvent');
            event.initCustomEvent('crm:theme-change', false, false, { theme: theme });
        }
        window.dispatchEvent(event);
    }

    // 应用皮肤时可选择是否持久化和广播；初始化阶段只写 DOM，不触发重复刷新。
    function apply(value, options) {
        var theme = resolveTheme(value);
        var shouldBroadcast;

        options = options || {};
        shouldBroadcast = options.broadcast !== false && (options.force || theme !== currentTheme);
        currentTheme = theme;
        setDomTheme(theme);

        if (options.persist) {
            writeStored(theme);
        }
        if (shouldBroadcast) {
            broadcast(theme);
        }

        return theme;
    }

    window.CrmTheme = {
        values: values.slice(),
        keys: storageKeys.slice(),
        normalize: normalize,
        get: function () {
            return currentTheme || readStored();
        },
        apply: apply,
        set: function (value) {
            var theme = apply(value, { persist: true, force: true });

            // A picker may live inside a same-origin Layui iframe. Delegate through
            // the parent API for immediate shell synchronization instead of waiting
            // for asynchronous storage propagation.
            try {
                if (window.parent
                    && window.parent !== window
                    && window.parent.CrmTheme
                    && window.parent.CrmTheme.get() !== theme) {
                    window.parent.CrmTheme.set(theme);
                }
            } catch (e) {}

            return theme;
        }
    };

    function bindThemeControls() {
        if (document.__crmThemeControlsBound) {
            return;
        }

        document.__crmThemeControlsBound = true;
        document.addEventListener('change', function (event) {
            var target = event.target;

            if (!target || !target.getAttribute || !target.hasAttribute('data-crm-skin-select')) {
                return;
            }

            window.CrmTheme.set(target.value);
        });

        document.addEventListener('click', function (event) {
            var target = event.target;
            var themeControl;
            var theme;

            if (!target || !target.closest) {
                return;
            }

            themeControl = target.closest('[data-theme], [data-skin], [data-crmui-theme]');
            if (!themeControl) {
                return;
            }

            theme = normalize(themeControl.getAttribute('data-theme') || themeControl.getAttribute('data-skin'));
            if (theme) {
                event.preventDefault();
                window.CrmTheme.set(theme);
                return;
            }

            if (themeControl.hasAttribute('data-crmui-theme')) {
                event.preventDefault();
                window.CrmTheme.set(window.CrmTheme.get() === 'dark' ? 'light' : 'dark');
            }
        });
    }

    apply(readStored(), { broadcast: false });
    bindThemeControls();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            apply(readStored(), { broadcast: false });
        });
    }

    window.addEventListener('storage', function (event) {
        if (storageKeys.indexOf(event.key) === -1) {
            return;
        }
        apply(event.newValue, { broadcast: true });
    });
})(window, document);
