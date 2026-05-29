@extends('front_layui::layouts.app')

@section('title', __('front.sub_agents'))
@section('breadcrumb', __('breadcrumb.front_agent_sub'))

@section('content')
@include('front_layui::partials.module-page', [
    'titleKey' => 'front.sub_agents',
    'descriptionKey' => 'front.sub_agents_desc',
    'api' => '/api/front/agentSubList',
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
        ['key' => 'user_id', 'label' => 'front.user_id', 'action' => 'showUserInfo', 'api' => '/api/front/userDetail', 'idField' => 'user_id', 'linkClass' => 'module-link-user'],
        ['key' => 'user_name', 'label' => 'front.user_name', 'action' => 'showUserDetail', 'idField' => 'user_id', 'linkClass' => 'module-link-user'],
        ['key' => 'agentsTotal', 'label' => 'front.agent_count'],
        ['key' => 'accountTotal', 'label' => 'front.customer_count'],
        ['key' => 'user_money', 'label' => 'front.balance', 'format' => 'money'],
        ['key' => 'cust_eqy', 'label' => 'front.customer_equity', 'format' => 'money'],
        ['key' => 'fy_money', 'label' => 'front.total_rebate', 'format' => 'money'],
        ['key' => 'rj_money', 'label' => 'front.total_deposit', 'format' => 'money'],
        ['key' => 'qk_money', 'label' => 'front.total_withdraw', 'format' => 'money'],
        ['key' => 'mt4MarginLevel', 'label' => 'front.margin_level', 'format' => 'money'],
        ['key' => 'commprop', 'label' => 'front.commission_rate'],
        ['key' => 'rec_crt_date', 'label' => 'common.created_at'],
    ],
])
@endsection

@section('scripts')
<script src="{{ asset('/js/common/echarts.common.min.js') }}"></script>
<script src="{{ asset('/js/front/layui/module-page.js') }}?v=2026052907"></script>
@endsection
