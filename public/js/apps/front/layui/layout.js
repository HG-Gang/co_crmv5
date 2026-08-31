// Created by PhpStorm.
// Project name co_crmv5.
// User: Huang Gang
// Date: 2026/08/08
// Time: 00:46
layui.use(['element', 'layer', 'jquery'], function () {
    var element = layui.element;
    var layer = layui.layer;
    var $ = layui.jquery;
    var cachedMenus = [];
    var defaultAvatar = '/images/default-avatar.svg';
    var $frame = $('#contentFrame');
    var $pageTitle = $('#framePageTitle');
    var $breadcrumb = $('#frameBreadcrumb');
    var activeTheme = window.CrmTheme ? CrmTheme.get() : (localStorage.getItem('front_theme') || localStorage.getItem('crm_theme') || 'light');
    var activeStyle = normalizeUiStyle(localStorage.getItem('crm_ui_style') || localStorage.getItem('front_ui_style') || 'layui');
    var frontLoginUrl = routeUrl('front_page_login');
    var frontRegisterUrl = routeUrl('front_page_register');
    var frontDashboardUrl = routeUrl('front_page_dashboard');
    var frontPagePrefix = pagePrefixFromUrl(frontDashboardUrl);
    var sidebarMedia = window.matchMedia('(max-width: 768px)');
    var $layoutShell = $('#frontLayuiShell');
    var $sidebarToggle = $('[data-layui-sidebar-toggle]');

    function syncSidebarAria() {
        var expanded = sidebarMedia.matches
            ? $layoutShell.hasClass('is-sidebar-open')
            : ! $layoutShell.hasClass('is-sidebar-collapsed');

        $sidebarToggle.attr('aria-expanded', expanded ? 'true' : 'false');
    }

    function closeMobileSidebar() {
        $layoutShell.removeClass('is-sidebar-open');
        syncSidebarAria();
    }

    $sidebarToggle.on('click.visualCSidebar', function () {
        if (sidebarMedia.matches) {
            $layoutShell.toggleClass('is-sidebar-open');
        } else {
            $layoutShell.toggleClass('is-sidebar-collapsed');
        }
        syncSidebarAria();
    });

    $('[data-layui-sidebar-dismiss]').on('click.visualCSidebar', closeMobileSidebar);
    $(document).on('click.visualCSidebar', '#sideMenu a', function () {
        if (sidebarMedia.matches) {
            closeMobileSidebar();
        }
    });
    $(document).on('keydown.visualCSidebar', function (event) {
        if (event.key === 'Escape') {
            closeMobileSidebar();
        }
    });

    function handleSidebarBreakpoint() {
        closeMobileSidebar();
        syncSidebarAria();
    }

    if (typeof sidebarMedia.addEventListener === 'function') {
        sidebarMedia.addEventListener('change', handleSidebarBreakpoint);
    } else {
        sidebarMedia.addListener(handleSidebarBreakpoint);
    }
    syncSidebarAria();

    // 页面跳转使用 PHP 注入的 Laravel 路由清单，后端 API 保持显式 /api/front/... URL。
    function routeUrl(name, params, fallback) {
        return window.crmRoute ? window.crmRoute(name, params || {}, fallback || '') : (fallback || '');
    }

    // 从命名路由生成的控制台地址里解析前台页面前缀，避免菜单绑定继续写死 /front。
    function pagePrefixFromUrl(rawUrl) {
        var url = new URL(rawUrl || '/', window.location.origin);
        var segments = url.pathname.split('/').filter(function (segment) {
            return segment !== '';
        });

        return segments[0] ? '/' + segments[0] : '';
    }

    function frontCrmUiPageUrl(page) {
        return routeUrl('front_crmui_app', {path: page || 'dashboard'}, '/front-crmui/' + (page || 'dashboard'));
    }

    function currentCrmUiPageUrl() {
        return frontCrmUiPageUrl(currentLayuiPagePath());
    }

    function frontNaivePageUrl(page) {
        return routeUrl('front_naive_app', {path: page || 'dashboard'}, '/front-naive/' + (page || 'dashboard'));
    }

    function currentNaivePageUrl() {
        return frontNaivePageUrl(currentLayuiPagePath());
    }

    function currentLayuiPagePath() {
        var path = currentFramePath();
        var prefix = frontPagePrefix || '/front';

        if (path.indexOf(prefix + '/') === 0) {
            path = path.slice(prefix.length + 1);
        } else {
            path = path.replace(/^\/+/, '');
        }

        return normalizeCompatiblePagePath(path || 'dashboard');
    }

    /**
     * 将旧别名归一为 CrmUI/Naive 兼容入口真实支持的页面路径。
     *
     * CrmUI 和 Naive 当前都由同一个服务端 Blade 控制器输出，必须使用 PageController
     * pages() 中存在的 path，避免切换后落到 dashboard 兜底页。
     */
    function normalizeCompatiblePagePath(path) {
        var map = {
            account: 'account/info',
            'account-balance': 'account/balance',
            vouchers: 'account/voucher',
            'cancel-account': 'account/cancel',
            deposits: 'deposit',
            withdrawals: 'withdraw',
            'position-summary': 'position/summary',
            'open-orders': 'order/open',
            'closed-orders': 'order/closed',
            'agent-sub': 'agent/sub',
            'agent-customers': 'agent/customers',
            'agent-confirm': 'agent/confirm-level',
            'group-change': 'agent/group-change',
            'commission-realtime': 'commission/realtime',
            'commission-history': 'commission/history',
            'commission-transfer': 'commission/transfer',
            'gift-address': 'gift/address',
            'gift-list': 'gift/list'
        };

        return map[path] || path || 'dashboard';
    }

    // 将菜单 slug 和旧 FontAwesome 图标统一映射为 Layui 图标。
    // 这样壳层导航可以稳定显示图标，不再依赖后端拼接好的图标 HTML。
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

    // 在壳层启动前先应用缓存的主题和风格，避免首屏先闪旧样式。
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

        if (!token && ![frontLoginUrl, frontRegisterUrl].includes(window.location.pathname)) {
            window.location.href = frontLoginUrl;
            return;
        }

        // 已登录用户直接拉取头部信息和菜单树；登录/注册页则跳过这些
        // 请求，保持页面尽量轻。
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
                url: '/api/front/auth/logout',
                success: afterLogout,
                error: afterLogout
            });
        });
    });

    $('.lang-switch').on('click', function () {
        var lang = $(this).data('lang');

        // 切换语言时同步刷新缓存菜单和当前 frame 文案，保证壳层和
        // iframe 的语言状态一致。
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
        // 所有 Blade 页面共用主题同步器，保证壳层和 iframe 的皮肤状态一致。
        applyTheme($(this).data('theme') || 'light', true);
    });

    $('.crm-style-switch').on('click', function () {
        var style = normalizeUiStyle($(this).data('style') || 'layui');
        localStorage.setItem('crm_ui_style', style);
        localStorage.setItem('front_ui_style', style);
        applyStyleState(style);
        if (style === 'crmui') {
            window.location.href = currentCrmUiPageUrl();
        } else if (style === 'naive') {
            window.location.href = currentNaivePageUrl();
        }
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
        window.location.href = frontLoginUrl;
    }

    function bindFrameNavigation() {
        $(document).on('click', buildFrameLinkSelector(), function (event) {
            var href = $(this).attr('href');

            if (!href || href === 'javascript:;' || href.indexOf(frontLoginUrl) === 0 || href.indexOf(frontRegisterUrl) === 0) {
                return;
            }

            event.preventDefault();
            navigateFrame(href, {
                title: $(this).data('title') || $.trim($(this).text()),
                breadcrumb: $(this).data('breadcrumb') || $(this).attr('data-breadcrumb')
            });
        });
    }

    // frame 菜单链接的选择器也从命名路由前缀推导，路由文件改前缀时不需要全局替换 JS。
    function buildFrameLinkSelector() {
        return 'a.J_frameLink, #sideMenu a[href^="' + frontPagePrefix + '/"]';
    }

    function loadUserInfo() {
        CrmAjax.request({
            guard: 'front',
            url: '/api/front/profile',
            method: 'GET',
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
            url: '/api/front/navigation/menus',
            method: 'GET',
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
                url: frontDashboardUrl,
                path: frontDashboardUrl,
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
        if (persist && !window.CrmTheme) {
            localStorage.setItem('front_theme', activeTheme);
            localStorage.setItem('crm_theme', activeTheme);
        }
        try {
            if ($frame.length && $frame[0].contentDocument) {
                $frame[0].contentDocument.documentElement.setAttribute('data-front-theme', activeTheme);
            }
        } catch (e) {}
    }

    function applyStyleState(style) {
        activeStyle = normalizeUiStyle(style);
        updateStyleSwitchLabels();
        $('.crm-style-switch').parent().removeClass('layui-this');
        $('.crm-style-switch').removeClass('is-current');
        $('.crm-style-switch[data-style="' + activeStyle + '"]').parent().addClass('layui-this');
        $('.crm-style-switch[data-style="' + activeStyle + '"]').addClass('is-current');
    }

    function updateStyleSwitchLabels() {
        $('.crm-style-switch[data-style="layui"]').html('<i data-lucide="wallet-cards"></i> ' + escapeHtml(styleText('layui')));
        $('.crm-style-switch[data-style="crmui"]').html('<i data-lucide="gauge"></i> ' + escapeHtml(styleText('crmui')));
        $('.crm-style-switch[data-style="naive"]').html('<i data-lucide="sparkles"></i> ' + escapeHtml(styleText('naive')));
    }

    /**
     * 只允许服务端已实现的前台样式入口。
     *
     * Naive 当前是服务端兼容入口，不再加载已删除的客户端 SPA。
     *
     * @param {string} style 浏览器缓存或点击控件提供的样式值。
     * @returns {string} 可安全使用的前台样式值。
     */
    function normalizeUiStyle(style) {
        return style === 'crmui' || style === 'naive' ? style : 'layui';
    }

    function updateThemeSwitchLabels() {
        var labels = {
            light: '<i data-lucide="zap"></i> ' + escapeHtml(themeText('light')),
            dark: '<i data-lucide="moon"></i> ' + escapeHtml(themeText('dark')),
            sea: '<i data-lucide="waves"></i> ' + escapeHtml(themeText('sea')),
            warm: '<i data-lucide="git-branch"></i> ' + escapeHtml(themeText('warm')),
            contrast: '<i data-lucide="gem"></i> ' + escapeHtml(themeText('contrast'))
        };

        $('.theme-switch').each(function () {
            var theme = $(this).data('theme') || 'light';
            $(this).html(labels[theme] || escapeHtml(theme));
        });
    }

    function styleText(style) {
        if (style === 'crmui') {
            return CrmLang.t('front.layout_crmui');
        }
        if (style === 'naive') {
            return CrmLang.t('front.layout_naive');
        }

        return CrmLang.t('front.layout_classic');
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
