<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/01
 * Time: 23:37
 */

/**
 * 请求链路追踪与接口日志模块配置。
 *
 * 文件功能：
 * - 定义前后端 22 个重要模块：URL 路径前缀 -> 模块标识（日志 channel 名）。
 * - 中间件 RequestTraceMiddleware 据此判定当前请求归属模块，
 *   并决定是否记录“请求参数 + 响应”日志。
 *
 * 适用场景：
 * - 所有 web/api 请求的日志落盘与响应链路标识注入。
 *
 * 说明：
 * - 模块日志通道见 config/logging.php 中同名 daily channel。
 * - 匹配规则：请求 path 按前缀顺序匹配，命中即归属该模块；
 *   未命中的请求仅注入 request_id/trace_id，不记录接口日志。
 */
return [

    // 是否启用接口请求/响应日志记录（关闭时仍注入链路标识）。
    'log_requests_enabled' => env('TRACE_LOG_REQUESTS', true),

    // 请求参数日志中需要脱敏的 Header 键（小写）。
    'masked_headers' => [
        'authorization',
        'cookie',
        'x-csrf-token',
        'x-xsrf-token',
        'password',
    ],

    // 响应日志正文最大记录字节数（防止大响应撑爆日志）。
    'response_body_limit' => 2048,

    // 模块清单：模块名 => URL 路径前缀数组（按顺序匹配）。
    // 22 个模块 = 前台 10 + 后台 12（已按确认排除 front_gift / admin_news）。
    'modules' => [
        // ---------- 前台普通用户与代理商 ----------
        'front_auth' => [
            'user/login', 'user/index/login', 'user/captcha', 'user/index',
            'user/register', 'user/signIn', 'user/index/signIn', 'user/indexreg',
            'user/loginOut', 'user/forget', 'user/check_user_info', 'user/change_password',
            'agents/login', 'user/agents/signIn', 'user/agents/loginOut',
            'api/front/auth', 'front-crmui/login', 'front-crmui/register',
        ],
        // 前台个人资料：账户中心、改密、上传等入口。
        'front_profile' => [
            'user/center', 'user/editpsw', 'user/upload', 'user/multiple',
            'user/agents/editpsw', 'api/front/profile', 'api/front/upload',
            'front-crmui/profile', 'front-crmui/account',
        ],
        // 前台入金：入金申请、支付渠道回调与跳转入口。
        'front_deposit' => [
            'user/deposit', 'user/deposit_request', 'api/front/deposit',
            'front-crmui/deposit', 'user/deposit_notfiy', 'user/deposit_return',
            'user/deposit_tigerpay', 'user/deposit_wppay', 'user/deposit_exlink',
            'user/deposit_btb', 'user/deposit_passto', 'user/deposit_switch',
        ],
        // 前台出金：出金申请、校验与回调入口。
        'front_withdraw' => [
            'user/withdraw', 'user/withdraw_request', 'user/withdraw_verify',
            'api/front/withdraw', 'front-crmui/withdraw', 'user/withdraw_notfiy',
        ],
        // 前台账户流水：资金/佣金流水查询入口。
        'front_flow' => [
            'user/flow', 'api/front/flow', 'front-crmui/flow',
        ],
        // 前台交易：开仓/平仓与订单详情入口。
        'front_trade' => [
            'user/close', 'user/open', 'close/order_detail', 'open/order_detail',
            'api/front/order', 'front-crmui/order',
        ],
        // 前台持仓：持仓列表查询入口。
        'front_position' => [
            'user/position', 'api/front/position', 'front-crmui/position',
        ],
        // 前台返佣：实时佣金、佣金汇总与佣金转账入口。
        'front_commission' => [
            'user/realtime', 'user/position/comm_summary', 'user/proxy/direct_user_commTrans',
            'api/front/commission', 'front-crmui/commission',
        ],
        // 前台代理：代理/客户关系与家族树查询入口。
        'front_agent' => [
            'user/proxy', 'user/cust', 'user/agents', 'user/relationShip',
            'api/front/agent', 'api/front/customer', 'front-crmui/agent',
        ],
        // ---------- 后台管理员 ----------
        'admin_auth' => [
            'index/admin/login', 'index/admin/logon', 'index/admin/logout',
            'index/admin/captcha', 'api/admin/login', 'api/admin/logout',
        ],
        // 后台客户：客户列表、新增与编辑入口。
        'admin_user' => [
            'index/admin/cust', 'index/admin/customer', 'index/admin/agents_save',
            'index/admin/cust_save', 'api/admin/user', 'admin-crmui/users',
        ],
        // 后台代理：代理列表、大代理与推荐关系入口。
        'admin_agent' => [
            'index/admin/agent', 'index/admin/agents', 'index/admin/agents_',
            'index/admin/send', 'index/admin/bigAgents', 'index/admin/big_agents',
            'api/admin/agent', 'api/admin/big-agent', 'admin-crmui/agents',
        ],
        // 后台入金管理：入金流水与结算入口。
        'admin_deposit' => [
            'index/admin/amount/deposit', 'api/admin/deposit', 'admin-crmui/deposits',
        ],
        // 后台出金管理：出金申请与打款入口。
        'admin_withdraw' => [
            'index/admin/amount/withdraw', 'index/admin/amount/order',
            'api/admin/withdraw', 'admin-crmui/withdrawals',
        ],
        // 后台资金权益：权益结算、批量调整、信用额与渠道配置入口。
        'admin_fund' => [
            'index/admin/amount/rights', 'index/admin/amount/batch', 'index/admin/amount/undeposit',
            'index/admin/amount/whpj', 'index/admin/amount/show_channel', 'index/admin/amount/channel',
            'index/admin/credit', 'api/admin/fund', 'admin-crmui/never-deposit-users',
        ],
        // 后台交易管理：订单查询与持仓汇总入口。
        'admin_trade' => [
            'index/admin/order', 'api/admin/trade', 'api/admin/position-summary',
            'admin-crmui/trades', 'admin-crmui/position-summary',
        ],
        // 后台风控：风控盈利与风险查询入口。
        'admin_risk' => [
            'index/admin/fengXian', 'api/admin/risk', 'admin-crmui/risk',
        ],
        // 后台实名审核：身份证/银行卡审核与凭证入口。
        'admin_auth_review' => [
            'index/admin/auth', 'api/admin/authentication', 'api/admin/voucher',
            'admin-crmui/authentications',
        ],
        // 后台销户：注销申请审核入口。
        'admin_cancel' => [
            'index/admin/cancel', 'api/admin/cancel', 'admin-crmui/cancel-applies',
        ],
        // 后台权限：角色、管理员、菜单与数据范围入口。
        'admin_permission' => [
            'index/admin/role', 'index/admin/Administrators', 'index/admin/Administrator',
            'api/admin/role', 'api/admin/permission', 'api/admin/menu',
            'api/admin/data-scope', 'admin-crmui/roles', 'admin-crmui/permissions',
            'admin-crmui/menus', 'admin-crmui/data-scopes',
        ],
        // 后台在线用户：在线列表与强制下线入口。
        'admin_online' => [
            'index/admin/online', 'api/admin/online', 'admin-crmui/online-users',
        ],
        // 后台系统：系统配置、组配置、汇率与操作日志入口。
        'admin_system' => [
            'index/admin/userinfo', 'index/admin/userpwd', 'index/admin/group',
            'api/admin/system-config', 'api/admin/group-config', 'api/admin/exchange-rate',
            'api/admin/operation-log', 'admin-crmui/system-configs', 'admin-crmui/group-configs',
        ],
    ],
];
