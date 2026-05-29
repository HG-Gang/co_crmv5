@extends('front_layui::layouts.app')

@section('title', __('front.customers'))
@section('breadcrumb', __('breadcrumb.front_agent_customers'))

@section('content')
@include('front_layui::partials.module-page', [
    'titleKey' => 'front.customers',
    'descriptionKey' => 'front.customers_desc',
    'api' => '/api/front/agentCustomerList',
    'filters' => [
        ['name' => 'userId', 'label' => 'front.user_id', 'type' => 'text'],
        ['name' => 'username', 'label' => 'front.user_name', 'type' => 'text'],
        ['name' => 'userstatus', 'label' => 'front.auth_status', 'type' => 'select', 'options' => [
            ['value' => '0', 'label' => 'front.status_unverified'],
            ['value' => '1', 'label' => 'front.status_verified'],
        ]],
        ['name' => 'startdate', 'label' => 'front.date_from', 'type' => 'date'],
        ['name' => 'enddate', 'label' => 'front.date_to', 'type' => 'date'],
    ],
    'columns' => [
        ['key' => 'mt4_login', 'label' => 'front.user_id', 'action' => 'showUserInfo', 'api' => '/api/front/userDetail', 'idField' => 'user_id', 'linkClass' => 'module-link-user'],
        ['key' => 'user_name', 'label' => 'front.user_name'],
        ['key' => 'mt4_balance', 'label' => 'front.balance', 'format' => 'money'],
        ['key' => 'cust_eqy', 'label' => 'front.equity', 'format' => 'money'],
        ['key' => 'total_yuerj', 'label' => 'front.total_deposit', 'format' => 'money'],
        ['key' => 'total_yuecj', 'label' => 'front.total_withdraw', 'format' => 'money'],
        ['key' => 'total_net_worth', 'label' => 'front.net_worth', 'format' => 'money'],
        ['key' => 'total_comm', 'label' => 'front.commission', 'format' => 'money'],
        ['key' => 'total_profit', 'label' => 'front.total_profit', 'format' => 'money'],
        ['key' => 'mt4MarginLevel', 'label' => 'front.margin_level', 'format' => 'money'],
        ['key' => 'total_noble_metal', 'label' => 'front.noble_metal', 'format' => 'lots', 'group' => 'front.product_type'],
        ['key' => 'total_for_exca', 'label' => 'front.forex', 'format' => 'lots', 'group' => 'front.product_type'],
        ['key' => 'total_crud_oil', 'label' => 'front.crude_oil', 'format' => 'lots', 'group' => 'front.product_type'],
        ['key' => 'total_index', 'label' => 'front.index_products', 'format' => 'lots', 'group' => 'front.product_type'],
        ['key' => 'total_currency', 'label' => 'front.currency_products', 'format' => 'lots', 'group' => 'front.product_type'],
        ['key' => 'total_stock', 'label' => 'front.stock_products', 'format' => 'lots', 'group' => 'front.product_type'],
        ['key' => 'total_volume', 'label' => 'front.total_volume', 'format' => 'lots'],
        ['key' => 'total_swaps', 'label' => 'front.swaps', 'format' => 'money'],
        ['key' => 'rec_crt_date', 'label' => 'common.created_at'],
        ['key' => 'comm_trans', 'label' => 'front.commission_transfer'],
        ['key' => 'change_group', 'label' => 'front.group_change'],
    ],
])
@endsection

@section('scripts')
<script src="{{ asset('/js/front/layui/module-page.js') }}?v=2026052913"></script>
@endsection
