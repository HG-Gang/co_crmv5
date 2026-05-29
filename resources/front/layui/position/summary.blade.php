@extends('front_layui::layouts.app')

@section('title', __('front.position_summary'))
@section('breadcrumb', __('breadcrumb.front_position_summary'))

@section('content')
@include('front_layui::partials.module-page', [
    'titleKey' => 'front.position_summary',
    'descriptionKey' => 'front.position_summary_desc',
    'api' => '/api/front/positionSummary',
    'listKey' => 'list',
    'showChain' => true,
    'filters' => [
        ['name' => 'userId', 'label' => 'front.user_id', 'type' => 'text'],
        ['name' => 'userName', 'label' => 'front.user_name', 'type' => 'text'],
        ['name' => 'symbol', 'label' => 'front.symbol', 'type' => 'text'],
        ['name' => 'startdate', 'label' => 'front.date_from', 'type' => 'date'],
        ['name' => 'enddate', 'label' => 'front.date_to', 'type' => 'date'],
    ],
    'columns' => [
        ['key' => 'user_id', 'label' => 'front.user_id', 'action' => 'positionSummaryDrill', 'actionIf' => 'can_drill', 'levelClassKey' => 'agent_level_rank'],
        ['key' => 'agent_level_name', 'label' => 'front.agent_level', 'format' => 'agentLevel', 'rankKey' => 'agent_level_rank'],
        ['key' => 'user_name', 'label' => 'front.user_name', 'action' => 'showUserInfo', 'api' => '/api/front/userDetail', 'idField' => 'user_id', 'linkClass' => 'module-link-user'],
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
<script src="{{ asset('/js/front/layui/module-page.js') }}?v=2026052907"></script>
@endsection
