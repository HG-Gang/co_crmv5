{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/28
Time: 01:08
--}}
﻿@extends('front_layui::layouts.app')

{{--
    前台账户综合与交易账户类型页面。
    页面只输出 Blade 结构和 public 静态资源；账户类型区消费通用资料接口的单次加载结果，
    提交成功后通过模块刷新事件重新读取服务端状态，不引入 Node、Vite 或 SPA 运行时。
--}}

@section('title', __('front.account_overview'))
@section('breadcrumb', __('breadcrumb.front_account_info'))

@section('styles')
<link rel="stylesheet" href="{{ asset('/css/front/account-type.css') }}?v=2026072802">
@endsection

@section('content')
@include('partials.front-account-type-switch', [
    'accountTypeChangeUrl' => route('legacy_user_change_account_save', [], false),
    'accountTypeChangeMethod' => 'POST',
    'accountTypeButtonClass' => 'layui-btn',
])

@include('front_layui::partials.module-page', [
    'titleKey' => 'front.account_overview',
    'descriptionKey' => 'front.account_overview_desc',
    'pageClass' => 'crm-visual-page crm-account-overview-page',
    'api' => '/api/front/account/profile',
    'method' => 'GET',
    'summaryFields' => [
        ['key' => 'user_id', 'label' => 'front.user_id'],
        ['key' => 'user_name', 'label' => 'front.user_name'],
        ['key' => 'email', 'label' => 'front.email'],
        ['key' => 'used_margin', 'label' => 'front.used_margin'],
        ['key' => 'avail_margin', 'label' => 'front.avail_margin'],
        ['key' => 'effective_credit', 'label' => 'front.effective_credit'],
        ['key' => 'risk_ratio', 'label' => 'front.risk_ratio'],
        ['key' => 'leverage', 'label' => 'front.leverage'],
        ['key' => 'group_name', 'label' => 'front.group_name'],
        ['key' => 'commission_rate', 'label' => 'front.commission_rate'],
    ],
    'comparisonTable' => 'funds_comparison',
    // 账户综合图表：入金 / 返佣 / 出金 / 订单 / 代理 / 客户 / 间接客户画像及其相关金额，
    // 每组都提供柱状图、折线图、面积图、饼图四种查看方式，数据全部来自 /api/front/account/profile 的真实聚合。
    'chartGroups' => [
        [
            'title' => 'front.funds_profile',
            'target' => 'accountFundsChart',
            'defaultType' => 'bar',
            'fields' => [
                ['key' => 'total_deposit', 'label' => 'front.total_deposit'],
                ['key' => 'total_rebate', 'label' => 'front.total_rebate'],
                ['key' => 'total_withdraw', 'label' => 'front.total_withdraw'],
                ['key' => 'total_funds', 'label' => 'front.total_funds'],
                ['key' => 'equity', 'label' => 'front.equity'],
            ],
        ],
        [
            'title' => 'front.order_profile',
            'target' => 'accountOrdersChart',
            'defaultType' => 'line',
            'fields' => [
                ['key' => 'open_order_count', 'label' => 'front.open_order_count'],
                ['key' => 'closed_order_count', 'label' => 'front.closed_order_count'],
                ['key' => 'profit_7d', 'label' => 'front.profit_7d'],
                ['key' => 'profit_15d', 'label' => 'front.profit_15d'],
                ['key' => 'profit_30d', 'label' => 'front.profit_30d'],
            ],
        ],
        [
            'title' => 'front.client_profile',
            'target' => 'accountClientsChart',
            'defaultType' => 'pie',
            'fields' => [
                ['key' => 'direct_agents', 'label' => 'front.direct_agents'],
                ['key' => 'indirect_agents', 'label' => 'front.indirect_agents'],
                ['key' => 'direct_customers', 'label' => 'front.direct_customers'],
                ['key' => 'indirect_customers', 'label' => 'front.indirect_customers'],
            ],
        ],
        [
            'title' => 'front.relation_deposit_profile',
            'target' => 'accountRelationDepositChart',
            'defaultType' => 'bar',
            'fields' => [
                ['key' => 'direct_agents_deposit', 'label' => 'front.direct_agents_deposit_amount'],
                ['key' => 'indirect_agents_deposit', 'label' => 'front.indirect_agents_deposit_amount'],
                ['key' => 'direct_customers_deposit', 'label' => 'front.direct_customers_deposit_amount'],
                ['key' => 'indirect_customers_deposit', 'label' => 'front.indirect_customers_deposit_amount'],
                ['key' => 'relation_amount', 'label' => 'front.relation_amount'],
            ],
        ],
        [
            'title' => 'front.relation_withdraw_profile',
            'target' => 'accountRelationWithdrawChart',
            'defaultType' => 'area',
            'fields' => [
                ['key' => 'direct_agents_withdraw', 'label' => 'front.direct_agents_withdraw_amount'],
                ['key' => 'indirect_agents_withdraw', 'label' => 'front.indirect_agents_withdraw_amount'],
                ['key' => 'direct_customers_withdraw', 'label' => 'front.direct_customers_withdraw_amount'],
                ['key' => 'indirect_customers_withdraw', 'label' => 'front.indirect_customers_withdraw_amount'],
            ],
        ],
        [
            'title' => 'front.relation_rebate_profile',
            'target' => 'accountRelationRebateChart',
            'defaultType' => 'bar',
            'fields' => [
                ['key' => 'total_rebate', 'label' => 'front.total_rebate'],
                ['key' => 'direct_agents_rebate', 'label' => 'front.direct_agents_rebate_amount'],
                ['key' => 'indirect_agents_rebate', 'label' => 'front.indirect_agents_rebate_amount'],
            ],
        ],
        [
            'title' => 'front.client_gender_profile',
            'target' => 'accountGenderChart',
            'defaultType' => 'pie',
            'fields' => [
                ['key' => 'customer_gender_profile.male.ratio', 'label' => 'register.male'],
                ['key' => 'customer_gender_profile.female.ratio', 'label' => 'register.female'],
                ['key' => 'customer_gender_profile.unknown.ratio', 'label' => 'response.unknown'],
            ],
        ],
    ],
])
@endsection

@section('scripts')
<script src="{{ asset('/js/vendor/echarts/echarts.common.min.js') }}"></script>
<script src="{{ asset('/js/apps/front/layui/module-page.js') }}?v=2026060701"></script>
<script src="{{ asset('/js/apps/front/layui/account-type.js') }}?v=2026081801"></script>
@endsection
