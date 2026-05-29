/**
 * Front Layout JS
 * Handles sidebar menus, user info, and logout.
 */
$(function() {
    'use strict';

    /**
     * Load User Profile Info
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
     * Load Sidebar Menus
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
     * Render sidebar menus dynamically
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
     * Handle Logout
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
     * Handle UI Style Toggle
     */
    $('#ui-style-switcher').on('click', function(e) {
        e.preventDefault();
        var newStyle = CURRENT_UI_STYLE === 'dark' ? 'light' : 'dark';
        CRM.switchStyle(newStyle);
    });

    // Initialize layout data
    if (CRM.getToken()) {
        loadUserInfo();
        loadMenus();
    } else if (window.location.pathname !== '/front/login' && window.location.pathname !== '/front/register') {
        window.location.href = '/front/login';
    }
});
