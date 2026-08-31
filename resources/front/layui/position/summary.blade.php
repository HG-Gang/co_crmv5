{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/25
Time: 19:29
--}}
﻿@extends('front_layui::layouts.app')

@section('title', __('front.position_summary'))
@section('breadcrumb', __('breadcrumb.front_position_summary'))

@section('content')
{{--
    旧详情路由会注入 legacyTargetUserId；首次请求将该 ID 作为 userId 传给已授权的代理持仓汇总接口。
    没有详情目标时保持空筛选，页面加载当前登录代理的全网汇总，避免在 Blade 中复制任何权限判断。
--}}
@include('front_layui::partials.module-page', [
    'titleKey' => 'front.position_summary',
    'descriptionKey' => 'front.position_summary_desc',
    'api' => '/api/front/positions/summary',
    'method' => 'GET',
    'listKey' => 'list',
    'showChain' => true,
    'defaultFilters' => !empty($legacyTargetUserId) ? ['userId' => (int) $legacyTargetUserId] : [],
    'filters' => [
        ['name' => 'userId', 'label' => 'front.user_id', 'type' => 'text'],
        ['name' => 'userName', 'label' => 'front.user_name', 'type' => 'text'],
        ['name' => 'symbol', 'label' => 'front.symbol', 'type' => 'select', 'dynamicOptions' => 'symbols'],
        ['name' => 'startdate', 'label' => 'front.date_from', 'type' => 'date'],
        ['name' => 'enddate', 'label' => 'front.date_to', 'type' => 'date'],
    ],
    'columns' => [
        ['key' => 'user_id', 'label' => 'front.user_id', 'action' => 'positionSummaryDrill', 'actionIf' => 'can_drill', 'levelClassKey' => 'agent_level_rank', 'chainAction' => true],
        ['key' => 'agent_level_name', 'label' => 'front.agent_level', 'format' => 'agentLevel', 'rankKey' => 'agent_level_rank'],
        ['key' => 'user_name', 'label' => 'front.user_name'],
        ['key' => 'total_yuerj', 'label' => 'front.total_deposit', 'format' => 'money'],
        ['key' => 'total_yuecj', 'label' => 'front.total_withdraw', 'format' => 'money'],
        ['key' => 'total_rebate', 'label' => 'front.total_rebate', 'format' => 'money'],
        ['key' => 'total_net_worth', 'label' => 'front.net_worth', 'format' => 'money'],
        ['key' => 'total_comm', 'label' => 'front.commission', 'format' => 'money'],
        ['key' => 'total_profit', 'label' => 'front.total_profit', 'format' => 'money'],
        ['key' => 'total_noble_metal', 'label' => 'front.noble_metal', 'format' => 'lots', 'group' => 'front.product_type'],
        ['key' => 'total_for_exca', 'label' => 'front.forex', 'format' => 'lots', 'group' => 'front.product_type'],
        ['key' => 'total_crud_oil', 'label' => 'front.crude_oil', 'format' => 'lots', 'group' => 'front.product_type'],
        ['key' => 'total_index', 'label' => 'front.index_products', 'format' => 'lots', 'group' => 'front.product_type'],
        ['key' => 'total_currency', 'label' => 'front.currency_products', 'format' => 'lots', 'group' => 'front.product_type'],
        ['key' => 'total_stock', 'label' => 'front.stock_products', 'format' => 'lots', 'group' => 'front.product_type'],
        ['key' => 'total_volume', 'label' => 'front.total_volume', 'format' => 'lots'],
        ['key' => 'total_swaps', 'label' => 'front.swaps', 'format' => 'money'],
    ],
])
@endsection

@section('scripts')
<script src="{{ asset('/js/apps/front/layui/module-page.js') }}?v=2026060701"></script>
@endsection
