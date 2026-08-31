// Created by PhpStorm.
// Project name co_crmv5.
// User: Huang Gang
// Date: 2026/08/17
// Time: 22:09
/**
 * 全局语言与主题图标菜单交互。
 * 主题状态继续由 CrmTheme 管理；本脚本只管理菜单和语言跳转。
 */
(function (window, document) {
    'use strict';

    function menuTrigger(menu) {
        return menu ? menu.querySelector('[data-crm-preference-trigger]') : null;
    }

    function menuPopover(menu) {
        return menu ? menu.querySelector('.crm-preference-popover') : null;
    }

    function closeMenu(menu, restoreFocus) {
        var trigger = menuTrigger(menu);
        var popover = menuPopover(menu);

        if (!menu || !trigger || !popover) {
            return;
        }

        menu.classList.remove('is-open');
        trigger.setAttribute('aria-expanded', 'false');
        popover.hidden = true;
        if (restoreFocus) {
            trigger.focus();
        }
    }

    function closeAll(except, restoreFocus) {
        var menus = document.querySelectorAll('[data-crm-preference-menu]');
        var i;

        for (i = 0; i < menus.length; i += 1) {
            if (menus[i] !== except) {
                closeMenu(menus[i], false);
            }
        }
        if (!except && restoreFocus && menus.length === 1) {
            closeMenu(menus[0], true);
        }
    }

    function openMenu(menu) {
        var trigger = menuTrigger(menu);
        var popover = menuPopover(menu);
        var current;

        if (!menu || !trigger || !popover) {
            return;
        }

        closeAll(menu, false);
        menu.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');
        popover.hidden = false;
        current = popover.querySelector('[aria-current="true"]') || popover.querySelector('.crm-preference-item');
        if (current) {
            window.requestAnimationFrame(function () {
                current.focus();
            });
        }
    }

    function selectLocale(locale) {
        var next = locale === 'en' ? 'en' : 'zh-CN';
        var url;

        try {
            window.localStorage.setItem('crm_locale', next);
            window.localStorage.setItem('front_lang', next);
        } catch (error) {}

        url = new URL(window.location.href);
        url.searchParams.set('locale', next);
        window.location.assign(url.toString());
    }

    function syncLocaleItems() {
        var stored = '';
        var htmlLocale = document.documentElement.getAttribute('lang') || '';
        var current;
        var items = document.querySelectorAll('[data-crm-locale]');
        var i;
        var itemLocale;
        var isCurrent;

        try {
            stored = window.localStorage.getItem('crm_locale') || '';
        } catch (error) {}
        current = stored === 'en' || htmlLocale.toLowerCase().indexOf('en') === 0 ? 'en' : 'zh-CN';

        for (i = 0; i < items.length; i += 1) {
            itemLocale = items[i].getAttribute('data-crm-locale') === 'en' ? 'en' : 'zh-CN';
            isCurrent = itemLocale === current;
            items[i].classList.toggle('is-current', isCurrent);
            items[i].setAttribute('aria-current', isCurrent ? 'true' : 'false');
            items[i].setAttribute('aria-checked', isCurrent ? 'true' : 'false');
        }
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest ? event.target.closest('[data-crm-preference-trigger]') : null;
        var localeItem = event.target.closest ? event.target.closest('[data-crm-locale]') : null;
        var themeItem = event.target.closest ? event.target.closest('[data-theme], [data-skin]') : null;
        var menu;

        if (trigger) {
            event.preventDefault();
            menu = trigger.closest('[data-crm-preference-menu]');
            if (menu && menu.classList.contains('is-open')) {
                closeMenu(menu, false);
            } else {
                openMenu(menu);
            }
            return;
        }

        if (localeItem) {
            event.preventDefault();
            menu = localeItem.closest('[data-crm-preference-menu]');
            closeMenu(menu, true);
            selectLocale(localeItem.getAttribute('data-crm-locale'));
            return;
        }

        if (themeItem) {
            menu = themeItem.closest('[data-crm-preference-menu]');
            if (menu) {
                closeMenu(menu, true);
            }
            return;
        }

        if (!event.target.closest || !event.target.closest('[data-crm-preference-menu]')) {
            closeAll(null, false);
        }
    });

    document.addEventListener('keydown', function (event) {
        var menu;

        if (event.key === 'Escape') {
            menu = event.target.closest ? event.target.closest('[data-crm-preference-menu]') : null;
            if (menu && menu.classList.contains('is-open')) {
                event.preventDefault();
                closeMenu(menu, true);
                return;
            }
            closeAll(null, false);
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', syncLocaleItems);
    } else {
        syncLocaleItems();
    }
})(window, document);
