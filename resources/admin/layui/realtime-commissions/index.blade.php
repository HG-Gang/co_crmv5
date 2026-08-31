{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/28
Time: 01:36
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.realtime_commissions'))

@section('content')
{{-- 实时返佣页面：Blade 负责渲染筛选表单、汇总指标和表格容器，真实数据由 admin_api_realtimeCommissionList 按旧 COMMENT 关键词、权限与数据范围返回。 --}}
<div class="layui-card crm-admin-panel">
    <div class="layui-card-header">{{ __('admin.realtime_commissions') }}</div>
    <div class="layui-card-body">
        <blockquote class="layui-elem-quote layui-text">
            {{ __('admin.realtime_commissions_desc') }}
        </blockquote>

        <form class="layui-form layui-form-pane" id="realtimeCommissionSearchForm">
            <div class="layui-form-item">
                <div class="layui-inline"><div class="layui-input-inline">
                        <input type="text" name="user_id" autocomplete="off" class="layui-input" placeholder="{{ __('admin.user_id') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        <input type="text" name="ticket" autocomplete="off" class="layui-input" placeholder="{{ __('admin.ticket') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        <input type="text" name="start_date" id="realtimeStartDate" autocomplete="off" class="layui-input" placeholder="{{ __('admin.start_date') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        <input type="text" name="end_date" id="realtimeEndDate" autocomplete="off" class="layui-input" placeholder="{{ __('admin.end_date') }}">
                    </div>
                </div>
                <div class="layui-inline">
                    <button class="layui-btn" lay-submit lay-filter="searchRealtimeCommissions">{{ __('common.search') }}</button>
                    <button type="reset" class="layui-btn layui-btn-primary">{{ __('common.reset') }}</button>
                    <button type="button" class="layui-btn layui-btn-normal" id="exportRealtimeCommissions" data-permission="admin_realtime_commission_export">{{ __('common.export') }}</button>
                </div>
            </div>
        </form>

        <table class="layui-hide" id="realtimeCommissionTable" lay-filter="realtimeCommissionTable"></table>
    </div>
</div>

{{-- 需求 9：统计独立成一个 div 区块，放在表格卡片之外，默认靠左对齐。 --}}
<section class="crm-admin-stats-block" id="realtimeCommissionSummary" aria-labelledby="realtimeCommissionSummaryTitle">
    <div class="crm-admin-stats-heading">
        <span class="crm-admin-stats-title" id="realtimeCommissionSummaryTitle" data-translate="admin.table_statistics">{{ __('admin.table_statistics') }}</span>
        <span class="crm-admin-stats-desc" data-translate="admin.table_statistics_desc">{{ __('admin.table_statistics_desc') }}</span>
    </div>
    <div class="crm-table-summary">
        <div class="crm-table-summary-item">
            <span data-translate="admin.total_records">{{ __('admin.total_records') }}</span>
            <strong data-summary-field="total_records">0</strong>
        </div>
        <div class="crm-table-summary-item">
            <span data-translate="admin.total_profit">{{ __('admin.total_profit') }}</span>
            <strong data-summary-field="total_profit">0.00</strong>
        </div>
        <div class="crm-table-summary-item">
            <span data-translate="admin.total_commission">{{ __('admin.total_commission') }}</span>
            <strong data-summary-field="total_commission">0.00</strong>
        </div>
    </div>
</section>

{{-- 需求 16：统计图表容器默认折叠，点击 》 形箭头展开。
     使用 button + aria-expanded 保证键盘可达（Enter/Space 由原生 button 语义提供），
     展开动画完全由 CSS grid-template-rows 过渡实现，不写任何 JS 动画。 --}}
<section class="crm-collapse-panel" id="realtimeCommissionCharts" data-realtime-chart-panel>
    <button type="button"
            class="crm-collapse-toggle"
            id="realtimeCommissionChartsToggle"
            data-realtime-chart-toggle
            aria-expanded="false"
            aria-controls="realtimeCommissionChartsBody"
            title="{{ __('admin.expand_statistics') }}">
        {{-- 箭头字形由 crm-design-system.css 的 ::before content 提供，模板只保留语义容器。 --}}
        <span class="crm-collapse-chevron" aria-hidden="true"></span>
        <span data-translate="admin.realtime_commission_charts">{{ __('admin.realtime_commission_charts') }}</span>
        <span class="crm-sr-only" data-realtime-chart-toggle-label data-translate="admin.expand_statistics">{{ __('admin.expand_statistics') }}</span>
    </button>
    <div class="crm-collapse-body" id="realtimeCommissionChartsBody" role="region" aria-labelledby="realtimeCommissionChartsToggle">
        <div class="crm-collapse-inner">
            <div class="crm-chart-grid">
                <div class="crm-chart-card">
                    <div class="crm-chart-head">
                        <span data-translate="admin.rebate_daily_records">{{ __('admin.rebate_daily_records') }}</span>
                        <div class="crm-chart-controls" role="group" aria-label="{{ __('admin.chart_view_mode') }}">
                            <button type="button" class="crm-chart-type is-active" data-chart-target="rebateRecordsChart" data-chart-type="bar" title="{{ __('admin.chart_bar') }}" aria-label="{{ __('admin.chart_bar') }}" aria-pressed="true"><i data-lucide="chart-column" aria-hidden="true"></i><span class="crm-sr-only">{{ __('admin.chart_bar') }}</span></button>
                            <button type="button" class="crm-chart-type" data-chart-target="rebateRecordsChart" data-chart-type="line" title="{{ __('admin.chart_line') }}" aria-label="{{ __('admin.chart_line') }}" aria-pressed="false"><i data-lucide="chart-line" aria-hidden="true"></i><span class="crm-sr-only">{{ __('admin.chart_line') }}</span></button>
                            <button type="button" class="crm-chart-type" data-chart-target="rebateRecordsChart" data-chart-type="area" title="{{ __('admin.chart_area') }}" aria-label="{{ __('admin.chart_area') }}" aria-pressed="false"><i data-lucide="chart-area" aria-hidden="true"></i><span class="crm-sr-only">{{ __('admin.chart_area') }}</span></button>
                            <button type="button" class="crm-chart-type" data-chart-target="rebateRecordsChart" data-chart-type="pie" title="{{ __('admin.chart_pie') }}" aria-label="{{ __('admin.chart_pie') }}" aria-pressed="false"><i data-lucide="chart-pie" aria-hidden="true"></i><span class="crm-sr-only">{{ __('admin.chart_pie') }}</span></button>
                        </div>
                    </div>
                    <div class="crm-chart-canvas" id="rebateRecordsChart"></div>
                </div>
                <div class="crm-chart-card">
                    <div class="crm-chart-head">
                        <span data-translate="admin.rebate_daily_profit">{{ __('admin.rebate_daily_profit') }}</span>
                        <div class="crm-chart-controls" role="group" aria-label="{{ __('admin.chart_view_mode') }}">
                            <button type="button" class="crm-chart-type" data-chart-target="rebateProfitChart" data-chart-type="bar" title="{{ __('admin.chart_bar') }}" aria-label="{{ __('admin.chart_bar') }}" aria-pressed="false"><i data-lucide="chart-column" aria-hidden="true"></i><span class="crm-sr-only">{{ __('admin.chart_bar') }}</span></button>
                            <button type="button" class="crm-chart-type is-active" data-chart-target="rebateProfitChart" data-chart-type="line" title="{{ __('admin.chart_line') }}" aria-label="{{ __('admin.chart_line') }}" aria-pressed="true"><i data-lucide="chart-line" aria-hidden="true"></i><span class="crm-sr-only">{{ __('admin.chart_line') }}</span></button>
                            <button type="button" class="crm-chart-type" data-chart-target="rebateProfitChart" data-chart-type="area" title="{{ __('admin.chart_area') }}" aria-label="{{ __('admin.chart_area') }}" aria-pressed="false"><i data-lucide="chart-area" aria-hidden="true"></i><span class="crm-sr-only">{{ __('admin.chart_area') }}</span></button>
                            <button type="button" class="crm-chart-type" data-chart-target="rebateProfitChart" data-chart-type="pie" title="{{ __('admin.chart_pie') }}" aria-label="{{ __('admin.chart_pie') }}" aria-pressed="false"><i data-lucide="chart-pie" aria-hidden="true"></i><span class="crm-sr-only">{{ __('admin.chart_pie') }}</span></button>
                        </div>
                    </div>
                    <div class="crm-chart-canvas" id="rebateProfitChart"></div>
                </div>
                <div class="crm-chart-card">
                    <div class="crm-chart-head">
                        <span data-translate="admin.rebate_source_distribution">{{ __('admin.rebate_source_distribution') }}</span>
                        <div class="crm-chart-controls" role="group" aria-label="{{ __('admin.chart_view_mode') }}">
                            <button type="button" class="crm-chart-type" data-chart-target="rebateSourceChart" data-chart-type="bar" title="{{ __('admin.chart_bar') }}" aria-label="{{ __('admin.chart_bar') }}" aria-pressed="false"><i data-lucide="chart-column" aria-hidden="true"></i><span class="crm-sr-only">{{ __('admin.chart_bar') }}</span></button>
                            <button type="button" class="crm-chart-type" data-chart-target="rebateSourceChart" data-chart-type="line" title="{{ __('admin.chart_line') }}" aria-label="{{ __('admin.chart_line') }}" aria-pressed="false"><i data-lucide="chart-line" aria-hidden="true"></i><span class="crm-sr-only">{{ __('admin.chart_line') }}</span></button>
                            <button type="button" class="crm-chart-type" data-chart-target="rebateSourceChart" data-chart-type="area" title="{{ __('admin.chart_area') }}" aria-label="{{ __('admin.chart_area') }}" aria-pressed="false"><i data-lucide="chart-area" aria-hidden="true"></i><span class="crm-sr-only">{{ __('admin.chart_area') }}</span></button>
                            <button type="button" class="crm-chart-type is-active" data-chart-target="rebateSourceChart" data-chart-type="pie" title="{{ __('admin.chart_pie') }}" aria-label="{{ __('admin.chart_pie') }}" aria-pressed="true"><i data-lucide="chart-pie" aria-hidden="true"></i><span class="crm-sr-only">{{ __('admin.chart_pie') }}</span></button>
                        </div>
                    </div>
                    <div class="crm-chart-canvas" id="rebateSourceChart"></div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('scripts')
<script src="{{ asset('/js/vendor/echarts/echarts.common.min.js') }}"></script>
<div hidden data-layui-page="realtime-commissions/index"></div>
@endsection
