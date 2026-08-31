<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/01
 * Time: 12:20
 */
/**
 * 代理商模块路由（独立路由文件）。
 *
 * 文件功能：
 * - 承载代理商模块独有的新路由，避免多个模块同时编辑 routes/front.php 或 routes/admin.php 产生冲突。
 * - 内部可声明 api/front、api/admin 与 web 三种分组，命名空间必须显式写全。
 *
 * 适用场景：
 * - 普通用户端代理商页面（下级代理、直属客户、级别确认、组别变更等）。
 * - 后台管理员代理商管理（大代理、代理等级、代理列表与统计等）。
 *
 * 注意：
 * - 路由与权限绑定请沿用新项目既有约定（jwt.auth / sso / check.permission）。
 * - 新增路由必须配套中文注释与真实测试。
 */
