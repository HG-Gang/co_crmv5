<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:27
 */

/**
 * Blade 视图配置。
 *
 * 配置用途：
 * - 定义模板查找路径与编译后模板存放目录。
 * - 项目使用命名空间视图（front_layui/admin_layui），模板目录在 AppServiceProvider 注册，
 *   本配置的 paths 提供默认查找兜底。
 *
 * 注意：
 * - compiled 目录必须可写；多机部署时建议保持同一路径或清理编译缓存。
 */

return [

    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    |
    | Most templating systems load templates from disk. Here you may specify
    | an array of paths that should be checked for your views. Of course
    | the usual Laravel view path has already been registered for you.
    |
    */

    // 模板查找路径列表（按顺序匹配）：resources/views 与 resources/ 根目录。
    'paths' => [
        resource_path('views'),
        resource_path(''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    |
    | This option determines where all the compiled Blade templates will be
    | stored for your application. Typically, this is within the storage
    | directory. However, as usual, you are free to change this value.
    |
    */

    // 编译后 Blade 模板缓存目录（默认 storage/framework/views，可经环境变量覆盖）。
    'compiled' => env(
        'VIEW_COMPILED_PATH',
        realpath(storage_path('framework/views'))
    ),

];
