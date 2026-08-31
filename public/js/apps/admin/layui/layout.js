// Created by PhpStorm.
// Project name co_crmv5.
// User: Huang Gang
// Date: 2026/08/07
// Time: 12:28
/**
 * 后台 Layui 布局运行时脚本。
 *
 * 功能逻辑说明：
 * - 负责后台 Blade/Layui 外壳的菜单加载、按钮权限显隐、语言切换、主题切换、侧边栏收起和退出登录。
 * - 菜单和按钮权限均来自 /api/admin/menus 返回的 permissions 数据，前端只做显示体验控制。
 * - 真正安全边界仍是后端 check.permission:admin 中间件和 permissions.api_route 配置。
 */
layui.use(['element', 'layer', 'jquery'], function () {
    var element = layui.element;
    var layer = layui.layer;
    var $ = layui.jquery;
    var cachedMenus = [];
    var permissionSlugs = loadCachedPermissions();
    var activeTheme = window.CrmTheme ? CrmTheme.get() : (localStorage.getItem('front_theme') || localStorage.getItem('crm_theme') || 'light');
    var activeStyle = normalizeUiStyle(localStorage.getItem('crm_ui_style') || localStorage.getItem('admin_ui_style') || 'layui');
    var adminLoginUrl = routeUrl('admin_page_login');
    var adminCrmUiDashboardUrl = routeUrl('admin_crmui_app', {path: 'dashboard'}, '/admin-crmui/dashboard');

    /**
     * 后台布局壳层的接口和跳转都从 PHP 注入的 Laravel 路由清单读取。
     *
     * @param {string} name Laravel 命名路由，例如 admin_page_login。
     * @param {Object=} params 路由参数对象。
     * @param {string=} fallback 未注入路由清单时使用的兜底 URL。
     * @returns {string} 可用于跳转或请求的 URL。
     */
    function routeUrl(name, params, fallback) {
        return window.crmRoute ? window.crmRoute(name, params || {}, fallback || '') : (fallback || '');
    }

    // 后台按钮权限控制器只负责前端显示体验，真正安全边界仍是 check.permission:admin 中间件。
    window.CrmAdminPermissions = {
        /**
         * 判断当前管理员是否拥有指定权限。
         *
         * @param {string} slug 对应 permissions.slug，按钮和接口共同使用的稳定权限标识。
         * @returns {boolean} true=允许显示该按钮，false=隐藏该按钮。
         */
        can: function (slug) {
            return !slug || permissionSlugs.indexOf(slug) !== -1;
        },

        /**
         * 重新扫描当前页面中的 data-permission 元素。
         *
         * @returns {void}
         */
        refresh: function () {
            applyPermissionVisibility();
        }
    };

    applyTheme(activeTheme, false);
    applyStyleState(activeStyle);

    var langReady = CrmLang.loadLanguage(CrmLang.getLocale());
    if (langReady && typeof langReady.then === 'function') {
        langReady.then(boot).catch(boot);
    } else {
        boot();
    }

    /**
     * 初始化后台布局数据。
     *
     * 逻辑说明：
     * - 未登录时跳转后台登录页。
     * - 已登录时读取 /api/admin/menus，返回菜单树和 permissions.slug 数组。
     * - 菜单渲染完成后立即应用按钮权限显隐。
     *
     * @returns {void}
     */
    function boot() {
        var token = CrmAjax.getToken('admin');
        if (!token && window.location.pathname !== adminLoginUrl) {
            window.location.href = adminLoginUrl;
            return;
        }

        CrmAjax.request({
            guard: 'admin',
            url: '/api/admin/menus',
            success: function (res) {
                if (res.code === 1000) {
                    $('#adminUsername').text(res.data.admin_name || (res.data.user && res.data.user.username) || 'Admin');
                    cachedMenus = numericObjectToArray(res.data.menus || res.data || []);
                    setPermissions(res.data.permissions || []);
                    renderMenus(cachedMenus);
                    element.render('nav', 'adminMenu');
                    applyPermissionVisibility();
                }
            }
        });
    }

    /**
     * 读取本地缓存的权限 slug 数组。
     *
     * 业务说明：
     * - 页面首次渲染时菜单接口还未返回，先使用缓存减少按钮闪烁。
     * - 菜单接口返回后会覆盖该缓存，确保当前登录账号权限是最新值。
     *
     * @returns {Array<string>} 当前管理员拥有的 permissions.slug 数组。
     */
    function loadCachedPermissions() {
        try {
            var cached = JSON.parse(localStorage.getItem('crm_admin_permissions') || '[]');
            return $.isArray(cached) ? cached : [];
        } catch (e) {
            return [];
        }
    }

    /**
     * 写入当前管理员权限 slug，并同步到 localStorage。
     *
     * @param {Array<string>} slugs 菜单接口返回的权限 slug 数组。
     * @returns {void}
     */
    function setPermissions(slugs) {
        permissionSlugs = numericObjectToArray(slugs || []);
        localStorage.setItem('crm_admin_permissions', JSON.stringify(permissionSlugs));
    }

    /**
     * 根据 data-permission 控制按钮显隐。
     *
     * 业务说明：
     * - data-permission 对应 permissions.slug。
     * - 无权限按钮直接隐藏；如果权限后续更新，再调用 refresh 可恢复显示。
     * - 这里只做前端体验控制，接口权限必须继续依赖后端中间件校验。
     *
     * @returns {void}
     */
    function applyPermissionVisibility() {
        $('[data-permission]').each(function () {
            var slug = $(this).data('permission');
            $(this).toggle(window.CrmAdminPermissions.can(slug));
        });
    }

    /**
     * 渲染后台侧边菜单。
     *
     * @param {Array<Object>} menus /api/admin/menus 返回的菜单树。
     * @returns {void}
     */
    function renderMenus(menus) {
        var html = '';

        menus.forEach(function (menu) {
            var hasChild = menu.children && menu.children.length > 0;
            var menuKey = resolveMenuKey(menu);

            html += '<li class="layui-nav-item">';
            if (hasChild) {
                html += '<a href="javascript:;"><i class="' + escapeAttr(resolveMenuIcon(menu.icon, 'layui-icon-set')) + '"></i>';
                html += '<span data-translate="' + escapeAttr(menuKey) + '">' + escapeHtml(resolveMenuText(menu, menuKey)) + '</span></a>';
                html += '<dl class="layui-nav-child">';
                menu.children.forEach(function (child) {
                    var childKey = resolveMenuKey(child);
                    html += '<dd><a href="' + escapeAttr(resolveMenuUrl(child)) + '"><i class="' + escapeAttr(resolveMenuIcon(child.icon, 'layui-icon-circle')) + '"></i>';
                    html += '<span data-translate="' + escapeAttr(childKey) + '">' + escapeHtml(resolveMenuText(child, childKey)) + '</span></a></dd>';
                });
                html += '</dl>';
            } else {
                html += '<a href="' + escapeAttr(resolveMenuUrl(menu)) + '"><i class="' + escapeAttr(resolveMenuIcon(menu.icon, 'layui-icon-set')) + '"></i>';
                html += '<span data-translate="' + escapeAttr(menuKey) + '">' + escapeHtml(resolveMenuText(menu, menuKey)) + '</span></a>';
            }
            html += '</li>';
        });

        $('#adminMenu').html(html);
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

    function resolveMenuIcon(icon, fallback) {
        if (!icon || icon.indexOf('fa') !== -1) {
            return 'layui-icon ' + fallback;
        }
        return icon.indexOf('layui-icon') === -1 ? ('layui-icon ' + icon) : icon;
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

    $('.lang-switch').on('click', function () {
        var lang = $(this).data('lang');

        CrmLang.loadLanguage(lang).then(function () {
            if (cachedMenus.length) {
                renderMenus(cachedMenus);
                element.render('nav', 'adminMenu');
            }
        });
    });

    $('.crm-style-switch').on('click', function () {
        var style = normalizeUiStyle($(this).data('style') || 'layui');
        localStorage.setItem('crm_ui_style', style);
        localStorage.setItem('admin_ui_style', style);
        applyStyleState(style);
        if (style === 'crmui') {
            window.location.href = adminCrmUiDashboardUrl;
        }
    });

    $('.crm-theme-switch').on('click', function () {
        applyTheme($(this).data('skin') || 'light', true);
        layer.msg(CrmLang.t('common.success'));
    });

    window.addEventListener('crm:theme-change', function (event) {
        var theme = event.detail && event.detail.theme;
        if (theme && theme !== activeTheme) {
            applyTheme(theme, false);
        }
    });

    $('#logoutBtn').on('click', function () {
        layer.confirm(CrmLang.t('common.confirm'), {icon: 3, title: CrmLang.t('common.logout')}, function (index) {
            CrmAjax.request({
                guard: 'admin',
                url: '/api/admin/logout',
                success: afterLogout,
                error: afterLogout
            });
            layer.close(index);
        });
    });

    function afterLogout() {
        CrmAjax.removeToken('admin');
        window.location.href = adminLoginUrl;
    }

    function applyTheme(theme, persist) {
        if (window.CrmTheme) {
            activeTheme = persist ? CrmTheme.set(theme) : CrmTheme.apply(theme, {broadcast: false});
        } else {
            activeTheme = normalizeTheme(theme);
        }

        document.documentElement.setAttribute('data-front-theme', activeTheme);
        $('.crm-theme-switch').parent().removeClass('layui-this');
        $('.crm-theme-switch').removeClass('is-current');
        $('.crm-theme-switch[data-skin="' + activeTheme + '"]').parent().addClass('layui-this');
        $('.crm-theme-switch[data-skin="' + activeTheme + '"]').addClass('is-current');
        if (persist && !window.CrmTheme) {
            localStorage.setItem('front_theme', activeTheme);
            localStorage.setItem('crm_theme', activeTheme);
        }
    }

    function applyStyleState(style) {
        activeStyle = normalizeUiStyle(style);
        $('.crm-style-switch').parent().removeClass('layui-this');
        $('.crm-style-switch').removeClass('is-current');
        $('.crm-style-switch[data-style="' + activeStyle + '"]').parent().addClass('layui-this');
        $('.crm-style-switch[data-style="' + activeStyle + '"]').addClass('is-current');
    }

    function normalizeTheme(theme) {
        var map = {emerald: 'light', blue: 'sea', amber: 'warm', violet: 'contrast'};
        theme = map[theme] || theme || 'light';
        return ['light', 'dark', 'sea', 'warm', 'contrast'].indexOf(theme) === -1 ? 'light' : theme;
    }

    /**
     * 只允许已由服务端 Blade 实现的后台样式值。
     *
     * 历史缓存中的 naive 会回退到 layui，避免用户进入已删除的客户端渲染入口。
     *
     * @param {string} style localStorage 或控件传入的样式名称。
     * @returns {string} 可渲染的 Blade 样式名称。
     */
    function normalizeUiStyle(style) {
        return style === 'crmui' ? 'crmui' : 'layui';
    }

    var sidebarMedia = window.matchMedia('(max-width: 768px)');
    var $layoutShell = $('#adminLayuiShell');
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
    $(document).on('click.visualCSidebar', '#adminMenu a', function () {
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

    // Layui table 工具栏由模板异步渲染，监听 DOM 变化后自动重新应用按钮权限。
    if (window.MutationObserver) {
        new MutationObserver(function () {
            applyPermissionVisibility();
        }).observe(document.body, {childList: true, subtree: true});
    }

    applyPermissionVisibility();
});
