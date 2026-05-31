/**
 * AdminLTE 前台布局脚本。
 * 负责加载用户信息、菜单树、退出登录和旧版亮暗风格切换。
 */
$(function() {
    'use strict';

    /**
     * 加载当前登录用户资料并写入侧边栏头像、昵称、角色和加入时间。
     */
    function loadUserInfo() {
        CRM.ajax({
            url: '/api/front/profileInfo',
            type: 'POST',
            success: function(res) {
                if (res.code === 200) {
                    var user = res.data;
                    $('.user-display-name').text(user.nickname || user.username);
                    $('.user-role').text(user.role_name || '');
                    $('.user-join-date').text(CRM.t('joined_at', {date: user.created_at}));
                    if (user.avatar) {
                        $('.user-avatar-img').attr('src', user.avatar);
                    }
                }
            }
        });
    }

    /**
     * 从接口读取前台菜单，成功后交给 renderMenus 生成树形导航。
     */
    function loadMenus() {
        CRM.ajax({
            url: '/api/front/menus',
            type: 'POST',
            success: function(res) {
                if (res.code === 200) {
                    renderMenus(res.data);
                }
            }
        });
    }

    /**
     * 动态渲染侧边栏菜单，并根据当前路径给父子菜单加 active/menu-open 状态。
     */
    function renderMenus(menus) {
        var $menu = $('#sidebar-menu');
        $menu.empty();
        
        var currentPath = window.location.pathname;

        $.each(menus, function(i, m) {
            var activeClass = (currentPath === m.path) ? 'active' : '';
            var hasChildren = m.children && m.children.length > 0;
            
            var li = $('<li class="nav-item"></li>');
            if (hasChildren) li.addClass('has-treeview');

            var a = $('<a href="' + (m.path || '#') + '" class="nav-link ' + activeClass + '"></a>');
            a.append('<i class="nav-icon ' + (m.icon || 'fas fa-circle') + '"></i>');
            var p = $('<p>' + m.title + '</p>');
            if (hasChildren) p.append('<i class="right fas fa-angle-left"></i>');
            a.append(p);
            li.append(a);

            if (hasChildren) {
                var ul = $('<ul class="nav nav-treeview"></ul>');
                $.each(m.children, function(j, child) {
                    var childActive = (currentPath === child.path) ? 'active' : '';
                    if (childActive) {
                        li.addClass('menu-open');
                        a.addClass('active');
                    }
                    var childLi = $('<li class="nav-item"></li>');
                    var childA = $('<a href="' + child.path + '" class="nav-link ' + childActive + '"></a>');
                    childA.append('<i class="far fa-circle nav-icon"></i>');
                    childA.append('<p>' + child.title + '</p>');
                    childLi.append(childA);
                    ul.append(childLi);
                });
                li.append(ul);
            }
            $menu.append(li);
        });
    }

    /**
     * 退出登录时先确认，再调用接口清理服务端状态和本地 token。
     */
    $('#btn-logout').on('click', function(e) {
        e.preventDefault();
        if (confirm(CRM.t('confirm_logout'))) {
            CRM.ajax({
                url: '/api/front/logout',
                success: function() {
                    CRM.removeToken();
                    window.location.href = '/front/login';
                }
            });
        }
    });

    /**
     * 兼容旧版 AdminLTE 亮暗风格切换，实际新皮肤由统一主题脚本负责。
     */
    $('#ui-style-switcher').on('click', function(e) {
        e.preventDefault();
        var newStyle = CURRENT_UI_STYLE === 'dark' ? 'light' : 'dark';
        CRM.switchStyle(newStyle);
    });

    // 页面初始化时优先加载登录态数据；未登录用户统一跳回登录/注册之外的入口。
    if (CRM.getToken()) {
        loadUserInfo();
        loadMenus();
    } else if (window.location.pathname !== '/front/login' && window.location.pathname !== '/front/register') {
        window.location.href = '/front/login';
    }
});
