<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:27
 */

/**
 * 跨域资源共享（CORS）配置（fruitcake/laravel-cors）。
 *
 * 配置用途：
 * - 定义浏览器跨域请求的放行范围：路径、HTTP 方法、来源域名、请求头与响应头。
 * - 当前项目前后端分离，api/* 与 sanctum/csrf-cookie 默认全部放行。
 *
 * 注意：
 * - allowed_origins=* 且 supports_credentials=true 的组合浏览器会拒绝（不能同时使用），
 *   如后续开启凭证模式必须显式列出可信域名。
 * - 生产环境建议收紧 allowed_origins，避免任意站点跨域调用后台接口。
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    // 需要应用 CORS 中间件的请求路径（api/* 全部接口与 sanctum CSRF cookie 接口）。
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // 允许的 HTTP 方法（* 表示全部：GET/POST/PUT/PATCH/DELETE/OPTIONS 等）。
    'allowed_methods' => ['*'],

    // 允许的来源域名（* 表示任意来源；生产建议改为具体域名列表）。
    'allowed_origins' => ['*'],

    // 允许来源的正则匹配列表（与 allowed_origins 二选一使用，当前为空）。
    'allowed_origins_patterns' => [],

    // 允许请求携带的 Header（* 表示全部，含 Authorization、Content-Type 等）。
    'allowed_headers' => ['*'],

    // 允许浏览器读取的响应头白名单（当前为空，按需添加如 Content-Disposition 用于下载）。
    'exposed_headers' => [],

    // 预检请求（OPTIONS）结果缓存秒数：0 表示不缓存，每次请求都重新预检。
    'max_age' => 0,

    // 是否允许跨域携带 Cookie/凭证：false=不允许（与 allowed_origins=* 兼容）。
    'supports_credentials' => false,

];
