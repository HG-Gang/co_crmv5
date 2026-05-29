(function (window, document) {
    'use strict';

    if (window.CrmTheme) {
        window.CrmTheme.apply(window.CrmTheme.get(), { broadcast: false });
        return;
    }

    var values = ['light', 'dark', 'sea', 'warm', 'contrast'];
    var storageKeys = [
        'front_theme',
        'crm_theme',
        'crm_naive_skin',
        'crm_color_mode',
        'color_mode',
        'colorMode',
        'tozo_color_mode',
        'naive_color_mode',
        'ui_style'
    ];
    var writeKeys = ['front_theme', 'crm_theme', 'crm_naive_skin', 'crm_color_mode'];
    var legacyMap = {
        emerald: 'light',
        green: 'light',
        blue: 'sea',
        cyan: 'sea',
        amber: 'warm',
        orange: 'warm',
        violet: 'contrast',
        purple: 'contrast',
        black: 'dark',
        night: 'dark',
        'dark-mode': 'dark',
        'light-mode': 'light'
    };
    var currentTheme = '';

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

    function storageGet(key) {
        try {
            return window.localStorage ? window.localStorage.getItem(key) : '';
        } catch (e) {
            return '';
        }
    }

    function storageSet(key, value) {
        try {
            if (window.localStorage) {
                window.localStorage.setItem(key, value);
            }
        } catch (e) {}
    }

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

    function writeStored(theme) {
        var i;

        for (i = 0; i < writeKeys.length; i += 1) {
            storageSet(writeKeys[i], theme);
        }

        document.cookie = 'ui_style=' + (theme === 'dark' ? 'dark' : 'light') + '; path=/; max-age=31536000; SameSite=Lax';
    }

    function syncSkinClasses(theme) {
        var roots = document.querySelectorAll('.crm-root, [data-crm-theme-root]');
        var i;

        for (i = 0; i < roots.length; i += 1) {
            roots[i].className = roots[i].className
                .replace(/\bcrm-skin-(light|dark|sea|warm|contrast|emerald|blue|amber|violet)\b/g, '')
                .replace(/\s{2,}/g, ' ')
                .trim() + ' crm-skin-' + theme;
        }
    }

    function syncControls(theme) {
        var controls = document.querySelectorAll('#crmSkinSelect, [data-crm-skin-select], [data-theme-select]');
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
            switches[i].classList.toggle('is-current', switchTheme === theme);
            if (switches[i].parentElement && switches[i].parentElement.tagName.toLowerCase() === 'dd') {
                switches[i].parentElement.classList.toggle('layui-this', switchTheme === theme);
            }
        }
    }

    function setDomTheme(theme) {
        var docEl = document.documentElement;

        docEl.setAttribute('data-front-theme', theme);
        docEl.setAttribute('data-crm-theme', theme);
        docEl.style.colorScheme = theme === 'dark' || theme === 'contrast' ? 'dark' : 'light';

        if (document.body) {
            document.body.setAttribute('data-front-theme', theme);
        }

        syncSkinClasses(theme);
        syncControls(theme);
    }

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

    function apply(value, options) {
        var theme = normalize(value) || readStored();
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
            return apply(value, { persist: true, force: true });
        }
    };

    apply(readStored(), { broadcast: false });

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
