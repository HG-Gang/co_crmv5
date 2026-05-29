layui.use(['element', 'layer', 'jquery'], function () {
    var element = layui.element;
    var layer = layui.layer;
    var $ = layui.jquery;
    var cachedMenus = [];
    var activeTheme = window.CrmTheme ? CrmTheme.get() : (localStorage.getItem('front_theme') || localStorage.getItem('crm_theme') || localStorage.getItem('crm_naive_skin') || 'light');
    var activeStyle = localStorage.getItem('crm_ui_style') || localStorage.getItem('admin_ui_style') || 'layui';

    applyTheme(activeTheme, false);
    applyStyleState(activeStyle);

    var langReady = CrmLang.loadLanguage(CrmLang.getLocale());
    if (langReady && typeof langReady.then === 'function') {
        langReady.then(boot).catch(boot);
    } else {
        boot();
    }

    function boot() {
        var token = CrmAjax.getToken('admin');
        if (!token && window.location.pathname !== '/admin/login') {
            window.location.href = '/admin/login';
            return;
        }

        CrmAjax.request({
            guard: 'admin',
            url: '/api/admin/menus',
            success: function (res) {
                if (res.code === 1000) {
                    $('#adminUsername').text(res.data.admin_name || (res.data.user && res.data.user.username) || 'Admin');
                    cachedMenus = numericObjectToArray(res.data.menus || res.data || []);
                    renderMenus(cachedMenus);
                    element.render('nav', 'adminMenu');
                }
            }
        });
    }

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
        var style = $(this).data('style') || 'layui';
        localStorage.setItem('crm_ui_style', style);
        localStorage.setItem('admin_ui_style', style);
        applyStyleState(style);
        if (style === 'naive') {
            window.location.href = '/admin-naive/dashboard';
        }
    });

    $('.crm-naive-skin-switch').on('click', function () {
        applyTheme($(this).data('skin') || 'light', true);
        layer.msg(CrmLang.t('common.success') || '皮肤已应用');
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
        window.location.href = '/admin/login';
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
        $('#adminThemeBadge').text(themeText(activeTheme));

        if (persist && !window.CrmTheme) {
            localStorage.setItem('front_theme', activeTheme);
            localStorage.setItem('crm_theme', activeTheme);
            localStorage.setItem('crm_naive_skin', activeTheme);
        }
    }

    function applyStyleState(style) {
        activeStyle = style || 'layui';
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

    function themeText(theme) {
        var map = {
            light: '浅色',
            dark: '深色',
            sea: '海蓝',
            warm: '暖色',
            contrast: '高对比'
        };

        return map[theme] || map.light;
    }

    var isSideCollapsed = false;
    $('#toggleSidebar').on('click', function () {
        if (isSideCollapsed) {
            $('.layui-side').animate({width: '220px'}, 100);
            $('.layui-body, .layui-footer').animate({left: '220px'}, 100);
            $('.layui-side span').show();
            $(this).find('i').removeClass('layui-icon-spread-left').addClass('layui-icon-shrink-right');
        } else {
            $('.layui-side').animate({width: '60px'}, 100);
            $('.layui-body, .layui-footer').animate({left: '60px'}, 100);
            $('.layui-side span').hide();
            $(this).find('i').removeClass('layui-icon-shrink-right').addClass('layui-icon-spread-left');
        }
        isSideCollapsed = !isSideCollapsed;
    });
});
