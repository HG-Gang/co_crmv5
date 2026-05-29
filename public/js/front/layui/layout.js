layui.use(['element', 'layer', 'jquery'], function () {
    var element = layui.element;
    var layer = layui.layer;
    var $ = layui.jquery;
    var cachedMenus = [];
    var defaultAvatar = '/images/default-avatar.svg';
    var $frame = $('#contentFrame');
    var $pageTitle = $('#framePageTitle');
    var $breadcrumb = $('#frameBreadcrumb');
    var activeTheme = window.CrmTheme ? CrmTheme.get() : (localStorage.getItem('front_theme') || localStorage.getItem('crm_theme') || localStorage.getItem('crm_naive_skin') || 'light');
    var activeStyle = localStorage.getItem('crm_ui_style') || localStorage.getItem('front_ui_style') || 'layui';

    var slugIconMap = {
        front_dashboard: 'layui-icon layui-icon-console',
        front_profile: 'layui-icon layui-icon-username',
        front_profile_info: 'layui-icon layui-icon-user',
        front_profile_edit: 'layui-icon layui-icon-edit',
        front_change_pwd: 'layui-icon layui-icon-password',
        front_change_email: 'layui-icon layui-icon-email',
        front_account: 'layui-icon layui-icon-template-1',
        front_account_info: 'layui-icon layui-icon-about',
        front_account_balance: 'layui-icon layui-icon-rmb',
        front_voucher: 'layui-icon layui-icon-note',
        front_cancel: 'layui-icon layui-icon-close-fill',
        front_deposit_withdraw: 'layui-icon layui-icon-dollar',
        front_deposit: 'layui-icon layui-icon-add-circle',
        front_withdraw: 'layui-icon layui-icon-reduce-circle',
        front_flow: 'layui-icon layui-icon-list',
        front_trading: 'layui-icon layui-icon-chart',
        front_position_summary: 'layui-icon layui-icon-table',
        front_open_orders: 'layui-icon layui-icon-play',
        front_closed_orders: 'layui-icon layui-icon-log',
        front_agent: 'layui-icon layui-icon-group',
        front_agent_sub: 'layui-icon layui-icon-friends',
        front_agent_customers: 'layui-icon layui-icon-user',
        front_agent_confirm: 'layui-icon layui-icon-ok-circle',
        front_group_change: 'layui-icon layui-icon-transfer',
        front_commission: 'layui-icon layui-icon-diamond',
        front_commission_rt: 'layui-icon layui-icon-light',
        front_commission_hist: 'layui-icon layui-icon-date',
        front_commission_transfer: 'layui-icon layui-icon-release',
        front_gift: 'layui-icon layui-icon-gift',
        front_gift_address: 'layui-icon layui-icon-location',
        front_gift_list: 'layui-icon layui-icon-cart',
        front_news: 'layui-icon layui-icon-notice'
    };

    var faIconMap = {
        'fa-tachometer-alt': 'layui-icon layui-icon-console',
        'fa-user': 'layui-icon layui-icon-username',
        'fa-id-card': 'layui-icon layui-icon-user',
        'fa-user-edit': 'layui-icon layui-icon-edit',
        'fa-key': 'layui-icon layui-icon-password',
        'fa-envelope': 'layui-icon layui-icon-email',
        'fa-wallet': 'layui-icon layui-icon-template-1',
        'fa-info-circle': 'layui-icon layui-icon-about',
        'fa-coins': 'layui-icon layui-icon-rmb',
        'fa-receipt': 'layui-icon layui-icon-note',
        'fa-user-times': 'layui-icon layui-icon-close-fill',
        'fa-dollar-sign': 'layui-icon layui-icon-dollar',
        'fa-plus-circle': 'layui-icon layui-icon-add-circle',
        'fa-minus-circle': 'layui-icon layui-icon-reduce-circle',
        'fa-stream': 'layui-icon layui-icon-list',
        'fa-chart-bar': 'layui-icon layui-icon-chart',
        'fa-chart-pie': 'layui-icon layui-icon-table',
        'fa-play-circle': 'layui-icon layui-icon-play',
        'fa-history': 'layui-icon layui-icon-log',
        'fa-sitemap': 'layui-icon layui-icon-group',
        'fa-user-friends': 'layui-icon layui-icon-friends',
        'fa-users': 'layui-icon layui-icon-user',
        'fa-check-circle': 'layui-icon layui-icon-ok-circle',
        'fa-exchange-alt': 'layui-icon layui-icon-transfer',
        'fa-money-bill-wave': 'layui-icon layui-icon-diamond',
        'fa-bolt': 'layui-icon layui-icon-light',
        'fa-paper-plane': 'layui-icon layui-icon-release',
        'fa-gift': 'layui-icon layui-icon-gift',
        'fa-map-marker-alt': 'layui-icon layui-icon-location',
        'fa-box': 'layui-icon layui-icon-cart'
    };


    applyTheme(activeTheme);
    applyStyleState(activeStyle);

    var langReady = CrmLang.loadLanguage(CrmLang.getLocale());
    if (langReady && typeof langReady.then === 'function') {
        langReady.then(boot).catch(boot);
    } else {
        boot();
    }

    function boot() {
        var token = CrmAjax.getToken('front');

        if (!token && !['/front/login', '/front/register'].includes(window.location.pathname)) {
            window.location.href = '/front/login';
            return;
        }

        if (token) {
            loadUserInfo();
            loadMenus();
        }

        bindFrameNavigation();
        updateFrameMetaFromCurrentUrl();
    }

    $('#logoutBtn').on('click', function () {
        layer.confirm(CrmLang.t('common.confirm'), {icon: 3, title: CrmLang.t('common.logout')}, function () {
            CrmAjax.request({
                guard: 'front',
                url: '/api/front/logout',
                success: afterLogout,
                error: afterLogout
            });
        });
    });

    $('.lang-switch').on('click', function () {
        var lang = $(this).data('lang');

        CrmLang.loadLanguage(lang).then(function () {
            if (cachedMenus.length) {
                renderMenus(cachedMenus);
            }
            applyTheme(activeTheme, false);
            applyStyleState(activeStyle);
            reloadFrame();
        });
    });

    $('.theme-switch').on('click', function () {
        applyTheme($(this).data('theme') || 'light', true);
    });

    $('.crm-style-switch').on('click', function () {
        var style = $(this).data('style') || 'layui';
        localStorage.setItem('crm_ui_style', style);
        localStorage.setItem('front_ui_style', style);
        applyStyleState(style);
        if (style === 'naive') {
            window.location.href = '/front-naive/dashboard';
        }
    });

    $('.crm-naive-skin-switch').on('click', function () {
        var skin = $(this).data('skin') || $(this).data('theme') || 'light';
        if ($(this).hasClass('theme-switch')) {
            return;
        }
        applyTheme(skin, true);
        layer.msg(CrmLang.t('common.success') || '已保存');
    });

    $('#frameRefreshBtn').on('click', reloadFrame);
    $frame.on('load', function () {
        activeTheme = window.CrmTheme ? CrmTheme.get() : activeTheme;
        applyTheme(activeTheme, false);
    });

    window.addEventListener('crm:theme-change', function (event) {
        var theme = event.detail && event.detail.theme;
        if (theme && theme !== activeTheme) {
            applyTheme(theme, false);
        }
    });

    window.addEventListener('message', function (event) {
        if (event.origin !== window.location.origin || !event.data) {
            return;
        }

        if (event.data.type === 'crm:avatar-updated' && event.data.url) {
            $('#userAvatarHeader').attr('src', event.data.url);
            return;
        }

        if (event.data.type === 'crm:frame-page') {
            setFrameMeta(event.data.title, event.data.breadcrumb);
            setActiveMenu(event.data.path || currentFramePath());
        } else if (event.data.type === 'crm:frame-navigate' && event.data.url) {
            navigateFrame(event.data.url, {
                title: event.data.title || '',
                breadcrumb: event.data.breadcrumb || ''
            });
        }
    });

    window.addEventListener('popstate', function () {
        navigateFrame(window.location.pathname + window.location.search, {push: false});
    });

    function afterLogout() {
        CrmAjax.removeToken('front');
        window.location.href = '/front/login';
    }

    function bindFrameNavigation() {
        $(document).on('click', 'a.J_frameLink, #sideMenu a[href^="/front/"]', function (event) {
            var href = $(this).attr('href');

            if (!href || href === 'javascript:;' || href.indexOf('/front/login') === 0 || href.indexOf('/front/register') === 0) {
                return;
            }

            event.preventDefault();
            navigateFrame(href, {
                title: $(this).data('title') || $.trim($(this).text()),
                breadcrumb: $(this).data('breadcrumb') || $(this).attr('data-breadcrumb')
            });
        });
    }

    function loadUserInfo() {
        CrmAjax.request({
            guard: 'front',
            url: '/api/front/profileInfo',
            success: function (res) {
                var info;
                var login;

                if (res.code === 1000 || res.code === 2000) {
                    info = res.data.info || res.data;
                    login = res.data.login || {};
                    $('#userNameLabel').text(info.user_name || login.email || 'User');
                    $('#userAvatarHeader').attr('src', info.avatar_url || info.avatar || defaultAvatar);
                }
            }
        });
    }

    function loadMenus() {
        CrmAjax.request({
            guard: 'front',
            url: '/api/front/menus',
            success: function (res) {
                if (res.code === 1000 || res.code === 2000) {
                    cachedMenus = numericObjectToArray(res.data.menus || res.data || []);
                    renderMenus(cachedMenus);
                }
            }
        });
    }

    function renderMenus(menus) {
        var $menu = $('#sideMenu');

        $menu.empty();
        if (!menus || !menus.length) {
            menus = [{
                slug: 'front_dashboard',
                title: CrmLang.t('menu.front_dashboard'),
                url: '/front/dashboard',
                path: '/front/dashboard',
                icon: 'layui-icon layui-icon-console'
            }];
        }

        $.each(menus, function (_, menu) {
            var hasChild = menu.children && menu.children.length > 0;
            var menuUrl = hasChild ? 'javascript:;' : resolveMenuUrl(menu);
            var menuKey = resolveMenuKey(menu);
            var html = '<li class="layui-nav-item">';

            html += '<a href="' + escapeAttr(menuUrl) + '" data-menu-slug="' + escapeAttr(menu.slug || '') + '"';
            if (!hasChild) {
                html += ' data-title="' + escapeAttr(resolveMenuText(menu, menuKey)) + '"';
                html += ' data-breadcrumb="' + escapeAttr(resolveBreadcrumb(menu)) + '"';
            }
            html += '>';
            html += '<i class="' + escapeAttr(resolveMenuIcon(menu)) + '"></i>';
            html += '<span data-translate="' + escapeAttr(menuKey) + '">' + escapeHtml(resolveMenuText(menu, menuKey)) + '</span>';
            html += '</a>';

            if (hasChild) {
                html += '<dl class="layui-nav-child">';
                $.each(menu.children, function (_, child) {
                    var childKey = resolveMenuKey(child);
                    html += '<dd><a href="' + escapeAttr(resolveMenuUrl(child)) + '" data-menu-slug="' + escapeAttr(child.slug || '') + '"';
                    html += ' data-title="' + escapeAttr(resolveMenuText(child, childKey)) + '"';
                    html += ' data-breadcrumb="' + escapeAttr(resolveBreadcrumb(child, menu)) + '">';
                    html += '<i class="' + escapeAttr(resolveMenuIcon(child)) + '"></i>';
                    html += '<span data-translate="' + escapeAttr(childKey) + '">' + escapeHtml(resolveMenuText(child, childKey)) + '</span>';
                    html += '</a></dd>';
                });
                html += '</dl>';
            }

            html += '</li>';
            $menu.append(html);
        });

        element.render('nav', 'sideMenu');
        setActiveMenu(currentFramePath());
    }

    function navigateFrame(rawUrl, options) {
        var options = options || {};
        var targetUrl = normalizeContentUrl(rawUrl);
        var displayUrl = stripFrameQuery(targetUrl);

        if (!$frame.length) {
            window.location.href = displayUrl;
            return;
        }

        $frame.attr('src', targetUrl);
        if (options.push !== false && displayUrl !== window.location.pathname + window.location.search) {
            history.pushState({frameUrl: displayUrl}, '', displayUrl);
        }

        setFrameMeta(options.title || inferTitle(displayUrl), options.breadcrumb || inferBreadcrumb(displayUrl));
        setActiveMenu(urlPath(displayUrl));
    }

    function reloadFrame() {
        if ($frame.length && $frame[0].contentWindow) {
            $frame[0].contentWindow.location.reload();
        }
    }

    function applyTheme(theme, persist) {
        if (window.CrmTheme) {
            activeTheme = persist ? CrmTheme.set(theme) : CrmTheme.apply(theme, {broadcast: false});
        } else {
            activeTheme = theme || 'light';
        }
        updateThemeSwitchLabels();
        document.documentElement.setAttribute('data-front-theme', activeTheme);
        $('.theme-switch').parent().removeClass('layui-this');
        $('.theme-switch').removeClass('is-current');
        $('.theme-switch[data-theme="' + activeTheme + '"]').parent().addClass('layui-this');
        $('.theme-switch[data-theme="' + activeTheme + '"]').addClass('is-current');
        $('#frontThemeBadge').text(themeText(activeTheme));
        if (persist && !window.CrmTheme) {
            localStorage.setItem('front_theme', activeTheme);
            localStorage.setItem('crm_theme', activeTheme);
            localStorage.setItem('crm_naive_skin', activeTheme);
        }
        try {
            if ($frame.length && $frame[0].contentDocument) {
                $frame[0].contentDocument.documentElement.setAttribute('data-front-theme', activeTheme);
            }
        } catch (e) {}
    }

    function applyStyleState(style) {
        activeStyle = style || 'layui';
        updateStyleSwitchLabels();
        $('.crm-style-switch').parent().removeClass('layui-this');
        $('.crm-style-switch').removeClass('is-current');
        $('.crm-style-switch[data-style="' + activeStyle + '"]').parent().addClass('layui-this');
        $('.crm-style-switch[data-style="' + activeStyle + '"]').addClass('is-current');
    }

    function updateStyleSwitchLabels() {
        $('.crm-style-switch[data-style="layui"]').text('▣ ' + styleText('layui'));
        $('.crm-style-switch[data-style="naive"]').text('□ ' + styleText('naive'));
    }

    function updateThemeSwitchLabels() {
        var labels = {
            light: '☀ ' + themeText('light'),
            dark: '☾ ' + themeText('dark'),
            sea: '≋ ' + themeText('sea'),
            warm: '◐ ' + themeText('warm'),
            contrast: '▣ ' + themeText('contrast')
        };

        $('.theme-switch').each(function () {
            var theme = $(this).data('theme') || 'light';
            $(this).text(labels[theme] || theme);
        });
    }

    function styleText(style) {
        if (style === 'layui') {
            return CrmLang.getLocale && CrmLang.getLocale() === 'en' ? 'Layui Style' : 'Layui 风格';
        }

        return CrmLang.getLocale && CrmLang.getLocale() === 'en' ? 'Naive Style' : 'Naive 风格';
    }

    function themeText(theme) {
        var map = {
            light: CrmLang.t('front.theme_light'),
            dark: CrmLang.t('front.theme_dark'),
            sea: CrmLang.t('front.theme_sea'),
            warm: CrmLang.t('front.theme_warm'),
            contrast: CrmLang.t('front.theme_contrast')
        };

        return map[theme] || map.light || theme;
    }

    function normalizeContentUrl(rawUrl) {
        var url = new URL(rawUrl, window.location.origin);

        url.searchParams.set('frame', '1');
        return url.pathname + url.search + url.hash;
    }

    function stripFrameQuery(rawUrl) {
        var url = new URL(rawUrl, window.location.origin);

        url.searchParams.delete('frame');
        url.searchParams.delete('iframe');
        return url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : '') + url.hash;
    }

    function currentFramePath() {
        var src = $frame.length ? $frame.attr('src') : window.location.pathname;
        return urlPath(stripFrameQuery(src || window.location.pathname));
    }

    function urlPath(rawUrl) {
        return new URL(rawUrl, window.location.origin).pathname;
    }

    function setFrameMeta(title, breadcrumb) {
        if (title) {
            $pageTitle.text(title);
            document.title = CrmLang.t('common.systemName') + ' - ' + title;
        }
        if (breadcrumb) {
            $breadcrumb.text(normalizeBreadcrumb(breadcrumb));
        }
    }

    function updateFrameMetaFromCurrentUrl() {
        setFrameMeta(inferTitle(window.location.pathname), inferBreadcrumb(window.location.pathname));
    }

    function setActiveMenu(path) {
        var $menu = $('#sideMenu');

        $menu.find('.layui-this').removeClass('layui-this');
        $menu.find('a').each(function () {
            var $link = $(this);
            var href = $link.attr('href');

            if (href && href !== 'javascript:;' && urlPath(href) === path) {
                $link.parent().addClass('layui-this');
                $link.closest('.layui-nav-item').addClass('layui-nav-itemed');
            }
        });
    }

    function inferTitle(rawUrl) {
        var path = urlPath(rawUrl);
        var $link = $('#sideMenu a').filter(function () {
            return urlPath($(this).attr('href') || '/') === path;
        }).first();

        return $.trim($link.text()) || $pageTitle.text();
    }

    function inferBreadcrumb(rawUrl) {
        var path = urlPath(rawUrl);
        var $link = $('#sideMenu a').filter(function () {
            return urlPath($(this).attr('href') || '/') === path;
        }).first();

        return normalizeBreadcrumb($link.data('breadcrumb') || $breadcrumb.text());
    }

    function resolveMenuKey(menu) {
        if (menu.translation_key) {
            return menu.translation_key;
        }
        if (menu.slug) {
            return 'menu.' + menu.slug;
        }
        return 'menu.dashboard';
    }

    function resolveMenuText(menu, key) {
        var translated = CrmLang.t(key);

        if (translated !== key) {
            return translated;
        }

        return menu.title || menu.name || menu.slug || '';
    }

    function resolveMenuUrl(menu) {
        return menu.url || menu.path || 'javascript:;';
    }

    function resolveMenuIcon(menu) {
        var icon = menu.icon || '';
        var slug = menu.slug || '';
        var key;

        if (slugIconMap[slug]) {
            return slugIconMap[slug];
        }

        if (icon.indexOf('layui-icon') !== -1) {
            return icon.indexOf('layui-icon ') === 0 ? icon : 'layui-icon ' + icon;
        }

        for (key in faIconMap) {
            if (Object.prototype.hasOwnProperty.call(faIconMap, key) && icon.indexOf(key) !== -1) {
                return faIconMap[key];
            }
        }

        return 'layui-icon layui-icon-app';
    }

    function resolveBreadcrumb(menu, parent) {
        var breadcrumbKey;
        var translated;

        if (menu.breadcrumb) {
            return normalizeBreadcrumb(menu.breadcrumb);
        }

        breadcrumbKey = menu.breadcrumb_key || ('breadcrumb.' + (menu.slug || ''));
        translated = CrmLang.t(breadcrumbKey);
        if (translated !== breadcrumbKey) {
            return normalizeBreadcrumb(translated);
        }

        if (!parent) {
            return resolveMenuText(menu, resolveMenuKey(menu));
        }

        return resolveMenuText(parent, resolveMenuKey(parent)) + ' / ' + resolveMenuText(menu, resolveMenuKey(menu));
    }

    function normalizeBreadcrumb(value) {
        var decoded = $('<textarea>').html(value || '').text();

        return decoded
            .replace(/\s*(?:-&gt;|->|&gt;|＞|›|»)\s*/g, ' / ')
            .replace(/\s*\/\s*/g, ' / ')
            .replace(/\s{2,}/g, ' ')
            .trim();
    }

    function escapeHtml(value) {
        return $('<div>').text(value || '').html();
    }

    function escapeAttr(value) {
        return $('<div>').text(value || '').html();
    }

    function numericObjectToArray(value) {
        var keys;
        var result = [];
        var i;

        if (!value || $.isArray(value) || typeof value !== 'object') {
            return value || [];
        }

        keys = Object.keys(value);
        for (i = 0; i < keys.length; i++) {
            if (String(parseInt(keys[i], 10)) !== keys[i]) {
                return value;
            }
            result.push(value[keys[i]]);
        }

        return result;
    }
});
