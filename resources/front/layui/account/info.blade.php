@extends('front_layui::layouts.app')

@section('title', __('front.account_overview'))
@section('breadcrumb', __('breadcrumb.front_account_info'))

@section('styles')
<style>
    .account-charts-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin-bottom: 14px; }
    .account-charts-grid .layui-card { margin-bottom: 0; border-radius: 8px; }
    .account-charts-grid .layui-card-body { padding: 10px 12px; }
    .account-chart { width: 100%; height: 260px; }
    .account-chart-toolbar { display: flex; align-items: center; gap: 6px; float: right; margin-top: -2px; }
    .account-chart-toolbar .chart-type-btn { padding: 2px 8px; border: 1px solid var(--front-line, #dce3ec); border-radius: 4px; background: transparent; color: var(--front-muted, #6b7280); font-size: 11px; cursor: pointer; }
    .account-chart-toolbar .chart-type-btn.active { background: var(--front-blue, #2080f0); color: #fff; border-color: var(--front-blue, #2080f0); }
    @media screen and (max-width: 768px) { .account-charts-grid { grid-template-columns: 1fr; } }
</style>
@endsection

@section('content')
{{-- Req 6: Account overview charts --}}
<div class="account-charts-grid">
    <div class="layui-card">
        <div class="layui-card-header" data-translate="front.deposit_withdraw_chart">出入金趋势
            <div class="account-chart-toolbar">
                <button type="button" class="chart-type-btn active" data-chart="acctDepositChart" data-type="bar" data-translate="front.chart_bar">{{ __('front.chart_bar') }}</button>
                <button type="button" class="chart-type-btn" data-chart="acctDepositChart" data-type="line" data-translate="front.chart_line">{{ __('front.chart_line') }}</button>
                <button type="button" class="chart-type-btn" data-chart="acctDepositChart" data-type="pie" data-translate="front.chart_pie">{{ __('front.chart_pie') }}</button>
            </div>
        </div>
        <div class="layui-card-body"><div id="acctDepositChart" class="account-chart"></div></div>
    </div>
    <div class="layui-card">
        <div class="layui-card-header" data-translate="front.commission_chart">返佣概览
            <div class="account-chart-toolbar">
                <button type="button" class="chart-type-btn active" data-chart="acctCommChart" data-type="line" data-translate="front.chart_line">{{ __('front.chart_line') }}</button>
                <button type="button" class="chart-type-btn" data-chart="acctCommChart" data-type="bar" data-translate="front.chart_bar">{{ __('front.chart_bar') }}</button>
                <button type="button" class="chart-type-btn" data-chart="acctCommChart" data-type="pie" data-translate="front.chart_pie">{{ __('front.chart_pie') }}</button>
            </div>
        </div>
        <div class="layui-card-body"><div id="acctCommChart" class="account-chart"></div></div>
    </div>
    <div class="layui-card">
        <div class="layui-card-header" data-translate="front.order_chart">订单概览
            <div class="account-chart-toolbar">
                <button type="button" class="chart-type-btn active" data-chart="acctOrderChart" data-type="bar" data-translate="front.chart_bar">{{ __('front.chart_bar') }}</button>
                <button type="button" class="chart-type-btn" data-chart="acctOrderChart" data-type="line" data-translate="front.chart_line">{{ __('front.chart_line') }}</button>
                <button type="button" class="chart-type-btn" data-chart="acctOrderChart" data-type="pie" data-translate="front.chart_pie">{{ __('front.chart_pie') }}</button>
            </div>
        </div>
        <div class="layui-card-body"><div id="acctOrderChart" class="account-chart"></div></div>
    </div>
    <div class="layui-card">
        <div class="layui-card-header" data-translate="front.agent_customer_chart">代理 / 客户画像</div>
        <div class="layui-card-body"><div id="acctAgentChart" class="account-chart"></div></div>
    </div>
</div>

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
])
@endsection

@section('scripts')
<script src="{{ asset('/js/common/echarts.common.min.js') }}"></script>
<script src="{{ asset('/js/front/layui/module-page.js') }}?v=2026052907"></script>
<script src="{{ asset('/js/front/layui/account/info-charts.js') }}?v=2026052918"></script>
@endsection
