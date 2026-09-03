<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/28
 * Time: 01:26
 */

/**
 * 演示数据播种开关配置。
 *
 * 文件功能：
 * - 定义 Seeder 的演示业务数据开关：所有开关默认关闭，且必须同时满足安全环境条件才会写入演示数据。
 * - front_demo_enabled 控制前台演示业务数据；admin_demo_statistics_enabled 控制后台统计演示数字
 *   （出入金统计、实时返佣统计图表）。
 * - 注意事项：正式环境不得开启，否则数据库会混入非真实业务数据，影响报表与对账。
 */

return [
    // 演示业务数据必须显式启用，并且 DatabaseSeeder 还会限制运行环境。
    'front_demo_enabled' => env('FRONT_DEMO_SEEDER_ENABLED', false),

    // 后台统计演示数据（出入金统计、实时返佣统计图表）同样是双重闸门：
    // 只有 local/testing 环境 + 本开关显式为 true 才会写入，正式环境永远拿不到演示数字。
    'admin_demo_statistics_enabled' => env('ADMIN_DEMO_STATISTICS_SEEDER_ENABLED', false),

    // 后台页面演示数据（大代理、黑名单、销户申请、在线用户、数据范围绑定、佣金转账 Saga）：
    // 这几张表默认为空，对应后台页只能渲染空态，长文本溢出与斑马纹对比度这类排版缺陷
    // 在空态下无法暴露，浏览器验收结论不可信。本开关专供 UI 验收补齐主列表数据。
    // 注意：admin_agent_bindings 同时是授权过滤源，写入会放大对应管理员的可见范围，
    // 因此正式环境必须保持关闭。
    'admin_page_demo_enabled' => env('ADMIN_PAGE_DEMO_SEEDER_ENABLED', false),
];
