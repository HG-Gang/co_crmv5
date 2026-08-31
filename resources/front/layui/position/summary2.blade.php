{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/25
Time: 18:50
--}}
{{--
    旧前台本人交易汇总页面。

    页面功能：
    - 对应旧项目 user/position/summary2，仅查询当前登录用户自己的 MT4 交易汇总。
    - 仅提交旧协议中的 startdate、enddate，避免把代理树钻取条件误传给本人汇总接口。

    数据链路：
    - 浏览器使用 POST /user/position/positionSummary2Search 请求数据。
    - 控制器返回 data.list 分页结构和 totalRow 汇总数据，module-page.js 负责渲染表格。
--}}
@extends('front_layui::layouts.app')

@section('title', __('front.position_summary'))
@section('breadcrumb', __('breadcrumb.front_position_summary'))

@section('content')
{{--
    不复用 position.summary：后者会请求代理树汇总接口，并允许按下级用户、姓名和品种钻取。
    本页面完整保留旧 Blade 的本人资金、盈亏、手续费、库存费与六类品种手数展示字段。
--}}
@include('front_layui::partials.module-page', [
    'titleKey' => 'front.position_summary',
    'descriptionKey' => 'front.position_summary_desc',
    'api' => '/user/position/positionSummary2Search',
    'method' => 'POST',
    'listKey' => 'list',
    'filters' => [
        ['name' => 'startdate', 'label' => 'front.date_from', 'type' => 'date'],
        ['name' => 'enddate', 'label' => 'front.date_to', 'type' => 'date'],
    ],
    'columns' => [
        ['key' => 'user_id', 'label' => 'front.user_id'],
        ['key' => 'user_name', 'label' => 'front.user_name'],
        ['key' => 'total_yuerj', 'label' => 'front.total_deposit', 'format' => 'money'],
        ['key' => 'total_yuecj', 'label' => 'front.total_withdraw', 'format' => 'money'],
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
{{-- module-page.js 统一处理旧 session/JWT 请求头、日期控件、分页与负数金额样式。 --}}
<script src="{{ asset('/js/apps/front/layui/module-page.js') }}?v=2026072501"></script>
@endsection
