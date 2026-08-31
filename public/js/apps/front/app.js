// Created by PhpStorm.
// Project name co_crmv5.
// User: Huang Gang
// Date: 2026/06/05
// Time: 09:56
/**
 * 前台旧版入口兼容脚本。
 *
 * 旧模板仍可能引用 front/app.js。这里只保留 CRM.use 初始化链路，
 * 真实页面逻辑由各业务模块脚本负责，避免缺少入口文件导致页面报错。
 */
CRM.use(['jquery'], function() {
    // 保留空初始化，兼容仍引用该文件的旧前台模板。
});
