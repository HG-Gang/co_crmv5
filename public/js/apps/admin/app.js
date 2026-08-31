// Created by PhpStorm.
// Project name co_crmv5.
// User: Huang Gang
// Date: 2026/06/05
// Time: 09:56
/**
 * 后台旧版入口兼容脚本。
 *
 * 这里只绑定旧后台侧边栏的展开收起行为，不输出调试日志，
 * 避免登录页或框架页加载时污染控制台。
 */
CRM.use(['jquery'], function() {
    var $ = CRM.$;

    // 侧边栏展开和收起。
    $('.sidebar-toggle').on('click', function() {
        $('.layui-side').toggleClass('collapsed');
    });
});
