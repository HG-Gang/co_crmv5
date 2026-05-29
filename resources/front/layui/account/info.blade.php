@extends('front_layui::layouts.app')

@section('title', __('front.account_overview'))
@section('breadcrumb', __('breadcrumb.front_account_info'))

@section('content')
@include('front_layui::partials.module-page', [
    'titleKey' => 'front.account_overview',
    'descriptionKey' => 'front.account_overview_desc',
    'api' => '/api/front/accountInfo',
    'summaryFields' => [
        ['key' => 'user_id', 'label' => 'front.user_id'],
        ['key' => 'user_name', 'label' => 'front.user_name'],
        ['key' => 'email', 'label' => 'front.email'],
        ['key' => 'total_funds', 'label' => 'front.total_funds'],
        ['key' => 'equity', 'label' => 'front.equity'],
        ['key' => 'used_margin', 'label' => 'front.used_margin'],
        ['key' => 'avail_margin', 'label' => 'front.avail_margin'],
        ['key' => 'effective_credit', 'label' => 'front.effective_credit'],
        ['key' => 'risk_ratio', 'label' => 'front.risk_ratio'],
        ['key' => 'leverage', 'label' => 'front.leverage'],
        ['key' => 'group_id', 'label' => 'front.group_id'],
    ],
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
                ['key' => 'direct_customers', 'label' => 'front.direct_customers'],
                ['key' => 'indirect_customers', 'label' => 'front.indirect_customers'],
                ['key' => 'relation_amount', 'label' => 'front.relation_amount'],
            ],
        ],
    ],
])
@endsection

@section('scripts')
<script src="{{ asset('/js/common/echarts.common.min.js') }}"></script>
<script src="{{ asset('/js/front/layui/module-page.js') }}?v=2026052913"></script>
@endsection
