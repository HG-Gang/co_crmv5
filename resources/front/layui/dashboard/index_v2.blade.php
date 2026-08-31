{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/28
Time: 00:55
--}}
@extends('front_layui::layouts.app')

@section('title', __('front.dashboard'))
@section('breadcrumb', __('breadcrumb.front_dashboard'))
@section('frame-theme-picker-provided', '1')

@section('styles')
<link rel="stylesheet" href="{{ asset('/css/front/v2.css') }}?v=2026061401">
<style>
    .front-v2-dashboard .layui-card { margin-bottom: 0; border: 0; box-shadow: none; }
    .front-v2-dashboard .layui-card-header { height: auto; min-height: 48px; line-height: 22px; padding: 14px 16px; border-bottom-color: var(--v2-line-soft); font-weight: 800; }
    .front-v2-dashboard .layui-card-body { padding: 16px; }
    .front-v2-dashboard .layui-btn { border-radius: 8px; }
    .front-v2-dashboard .dashboard-share-list { display: grid; gap: 10px; }
    .front-v2-dashboard .dashboard-share-item { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 10px; align-items: center; padding: 12px; border: 1px solid var(--v2-line-soft); border-radius: 8px; background: var(--v2-surface-soft); }
    .front-v2-dashboard .dashboard-share-label { margin-bottom: 4px; color: var(--v2-ink); font-weight: 800; }
    .front-v2-dashboard .dashboard-share-url { color: var(--v2-primary); word-break: break-all; }
    .front-v2-dashboard .dashboard-news-link { color: var(--v2-ink); font-weight: 700; }
    .front-v2-dashboard .dashboard-news-link:hover { color: var(--v2-primary); }
    .front-v2-dashboard .dashboard-range-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 44px; }
    .front-v2-dashboard .dashboard-range-title { color: var(--v2-ink); font-weight: 800; }
    .front-v2-dashboard .dashboard-range-controls { display: inline-flex; align-items: center; border: 1px solid var(--v2-line); border-radius: 8px; overflow: hidden; background: var(--v2-surface); }
    .front-v2-dashboard .dashboard-range-button { min-width: 76px; min-height: 44px; padding: 0 12px; border: 0; border-right: 1px solid var(--v2-line); background: transparent; color: var(--v2-ink); cursor: pointer; }
    .front-v2-dashboard .dashboard-range-button:last-child { border-right: 0; }
    .front-v2-dashboard .dashboard-range-button:hover { background: var(--v2-surface-soft); }
    .front-v2-dashboard .dashboard-range-button.is-active { background: var(--v2-primary); color: var(--v2-surface); font-weight: 800; }
    .front-v2-dashboard .dashboard-range-button:focus-visible,
    .front-v2-dashboard .dashboard-chart-type:focus-visible { position: relative; z-index: 1; outline: 2px solid var(--v2-primary); outline-offset: -2px; }
    .front-v2-dashboard .front-v2-dashboard-charts .layui-card-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
    .front-v2-dashboard .dashboard-chart-controls { display: inline-flex; flex: 0 0 auto; gap: 4px; }
    .front-v2-dashboard .dashboard-chart-type { display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; padding: 0; border: 1px solid var(--v2-line); border-radius: 8px; background: var(--v2-surface); color: var(--v2-ink); cursor: pointer; }
    .front-v2-dashboard .dashboard-chart-type:hover { border-color: var(--v2-primary); color: var(--v2-primary); background: var(--v2-surface-soft); }
    .front-v2-dashboard .dashboard-chart-type.is-active { border-color: var(--v2-primary); color: var(--v2-surface); background: var(--v2-primary); }
    .front-v2-dashboard .dashboard-chart-type svg { width: 18px; height: 18px; }
    .front-v2-dashboard .front-v2-panel,
    .front-v2-dashboard .front-v2-panel-body,
    .front-v2-dashboard .front-v2-dashboard-actions { overflow: visible; }
    .front-v2-dashboard .front-v2-dashboard-actions { position: relative; }
    .front-v2-dashboard .crm-theme-picker { height: 38px; color: var(--v2-primary); }
    .front-v2-dashboard .dashboard-switch-control { position: relative; min-width: 108px; height: 38px; padding: 0 10px; display: inline-flex; align-items: center; gap: 8px; border: 1px solid var(--v2-line); border-radius: 8px; background: var(--v2-surface); color: var(--v2-primary); cursor: pointer; }
    .front-v2-dashboard .dashboard-switch-control.is-open { z-index: 1200; border-color: var(--v2-primary); }
    .front-v2-dashboard .dashboard-switch-current { max-width: 88px; overflow: hidden; color: var(--v2-ink); font-size: 12px; font-weight: 800; text-overflow: ellipsis; white-space: nowrap; }
    .front-v2-dashboard .dashboard-option-menu { position: absolute; top: calc(100% + 8px); right: 0; z-index: 1201; display: none; min-width: 140px; padding: 6px; border: 1px solid var(--v2-line); border-radius: 8px; background: var(--v2-surface); box-shadow: var(--v2-shadow); pointer-events: auto; }
    .front-v2-dashboard .dashboard-switch-control.is-open .dashboard-option-menu { display: grid; gap: 4px; }
    .front-v2-dashboard .dashboard-option-menu button { display: flex; align-items: center; gap: 8px; width: 100%; min-height: 34px; padding: 0 9px; border: 0; border-radius: 8px; background: transparent; color: var(--v2-ink); text-align: left; cursor: pointer; pointer-events: auto; }
    .front-v2-dashboard .dashboard-option-menu button:hover,
    .front-v2-dashboard .dashboard-option-menu button.is-active { background: var(--v2-surface-soft); color: var(--v2-primary); }
    @media screen and (max-width: 640px) {
        .front-v2-dashboard .dashboard-share-item { grid-template-columns: 1fr; }
        .front-v2-dashboard .dashboard-switch-control { flex: 1 1 calc(50% - 8px); }
        .front-v2-dashboard .dashboard-range-toolbar { align-items: stretch; flex-direction: column; }
        .front-v2-dashboard .dashboard-range-controls { display: flex; width: 100%; }
        .front-v2-dashboard .dashboard-range-button { flex: 1 1 0; min-width: 0; }
    }
</style>
@endsection

@section('content')
<div class="front-v2-page front-v2-page-shell front-v2-dashboard crm-visual-page crm-dashboard-page">
    <section class="front-v2-dashboard-hero">
        <div class="front-v2-hero">
            <h1>
                <span data-translate="common.welcome">{{ __('common.welcome') }}</span>
                <strong id="welcomeUser">-</strong>
                <span id="customerTitle" class="layui-badge layui-bg-green"></span>
            </h1>
            <p>
                <span data-translate="front.monthly_period">{{ __('front.monthly_period') }}</span>
                <span id="periodRange">-</span>
            </p>
            <div class="front-v2-stat-grid" style="margin-top:18px;">
                <dl class="front-v2-stat"><dt data-translate="front.direct_agents">{{ __('front.direct_agents') }}</dt><dd id="directAgentsCount">0</dd></dl>
                <dl class="front-v2-stat"><dt data-translate="front.indirect_agents">{{ __('front.indirect_agents') }}</dt><dd id="indirectAgentsCount">0</dd></dl>
                <dl class="front-v2-stat"><dt data-translate="front.direct_customers">{{ __('front.direct_customers') }}</dt><dd id="directCustomersCount">0</dd></dl>
                <dl class="front-v2-stat"><dt data-translate="front.indirect_customers">{{ __('front.indirect_customers') }}</dt><dd id="indirectCustomersCount">0</dd></dl>
            </div>
            <a class="layui-btn layui-btn-primary layui-hide" id="identityGuideBtn" href="{{ route('front_page_profile', ['frame' => 1]) }}#identityForm" style="margin-top:16px;">
                <i data-lucide="badge-check"></i>
                <span data-translate="front.identity_upload_guide">{{ __('front.identity_upload_guide') }}</span>
            </a>
        </div>

        <div class="front-v2-panel">
            <div class="front-v2-panel-title">
                <h2>{{ app()->getLocale() === 'en' ? 'Workspace' : '工作台设置' }}</h2>
                <p>{{ app()->getLocale() === 'en' ? 'Theme, language, downloads, and display mode.' : '主题、语言、下载与显示模式。' }}</p>
            </div>
            <div class="front-v2-panel-body">
                <div class="front-v2-dashboard-actions">
                    <div class="dashboard-switch-control" data-dashboard-switch="style" role="button" title="{{ __('front.ui_style') }}" tabindex="0" aria-haspopup="menu" aria-expanded="false">
                        <i data-lucide="wallet-cards"></i>
                        <span class="dashboard-switch-current" data-dashboard-style-current>Layui</span>
                        <div class="dashboard-option-menu" role="menu">
                            <button type="button" role="menuitemradio" aria-checked="true" aria-pressed="true" data-dashboard-style-option="layui"><i data-lucide="wallet-cards"></i><span>{{ __('front.layout_classic') }}</span></button>
                            <button type="button" role="menuitemradio" aria-checked="false" aria-pressed="false" data-dashboard-style-option="crmui"><i data-lucide="gauge"></i><span>{{ __('front.layout_crmui') }}</span></button>
                            <button type="button" role="menuitemradio" aria-checked="false" aria-pressed="false" data-dashboard-style-option="naive"><i data-lucide="sparkles"></i><span>{{ __('front.layout_naive') }}</span></button>
                        </div>
                    </div>
                    @include('partials.theme-picker', ['themePickerCompact' => true])
                    <div class="dashboard-switch-control" data-dashboard-switch="locale" role="button" title="{{ __('common.language') }}" tabindex="0" aria-haspopup="menu" aria-expanded="false">
                        <i data-lucide="languages"></i>
                        <span class="dashboard-switch-current" data-dashboard-locale-current>{{ app()->getLocale() === 'en' ? 'EN' : 'ZH' }}</span>
                        <div class="dashboard-option-menu" role="menu">
                            <button type="button" role="menuitemradio" aria-checked="{{ app()->getLocale() === 'zh-CN' ? 'true' : 'false' }}" aria-pressed="{{ app()->getLocale() === 'zh-CN' ? 'true' : 'false' }}" data-dashboard-locale-option="zh-CN"><span>ZH</span><span>{{ __('common.lang_zh') }}</span></button>
                            <button type="button" role="menuitemradio" aria-checked="{{ app()->getLocale() === 'en' ? 'true' : 'false' }}" aria-pressed="{{ app()->getLocale() === 'en' ? 'true' : 'false' }}" data-dashboard-locale-option="en"><span>EN</span><span>{{ __('common.lang_en') }}</span></button>
                        </div>
                    </div>
                    <div class="dashboard-switch-control" data-dashboard-switch="sound" role="button" title="{{ __('front.sound_mode') }}" tabindex="0" aria-haspopup="menu" aria-expanded="false">
                        <i data-lucide="volume-2"></i>
                        <span class="dashboard-switch-current" data-dashboard-sound-current>{{ __('front.sound_on') }}</span>
                        <div class="dashboard-option-menu" role="menu">
                            <button type="button" role="menuitemradio" aria-checked="true" aria-pressed="true" data-dashboard-sound-option="on"><span>ON</span><span>{{ __('front.sound_on') }}</span></button>
                            <button type="button" role="menuitemradio" aria-checked="false" aria-pressed="false" data-dashboard-sound-option="off"><span>OFF</span><span>{{ __('front.sound_off') }}</span></button>
                        </div>
                    </div>
                    <a id="pcDownloadLink" class="layui-btn layui-btn-primary layui-btn-sm" href="#" target="_blank" rel="noopener"><i data-lucide="circle-arrow-down"></i> <span data-translate="front.pc_download">{{ __('front.pc_download') }}</span></a>
                    <a id="mobileDownloadLink" class="layui-btn layui-btn-primary layui-btn-sm" href="#" target="_blank" rel="noopener"><i data-lucide="smartphone"></i> <span data-translate="front.mobile_download">{{ __('front.mobile_download') }}</span></a>
                </div>
            </div>
        </div>
    </section>

    <section class="front-v2-stat-grid">
        <dl class="front-v2-stat"><dt data-translate="front.commission_rate">{{ __('front.commission_rate') }}</dt><dd id="commissionRate">0%</dd></dl>
        <dl class="front-v2-stat"><dt data-translate="front.total_commission">{{ __('front.total_commission') }}</dt><dd id="totalCommission">0.00</dd></dl>
        <dl class="front-v2-stat"><dt data-translate="front.total_funds">{{ __('front.total_funds') }}</dt><dd id="accountBalance">0.00</dd></dl>
        <dl class="front-v2-stat"><dt data-translate="front.equity">{{ __('front.equity') }}</dt><dd id="accountEquity">0.00</dd></dl>
        <dl class="front-v2-stat"><dt data-translate="front.effective_credit">{{ __('front.effective_credit') }}</dt><dd id="effectiveCredit">0.00</dd></dl>
        <dl class="front-v2-stat"><dt data-translate="front.open_orders">{{ __('front.open_orders') }}</dt><dd id="openOrdersCount">0</dd></dl>
    </section>

    <section class="dashboard-range-toolbar" aria-label="{{ __('front.statistics_range') }}">
        <span class="dashboard-range-title" data-translate="front.statistics_range">{{ __('front.statistics_range') }}</span>
        <div class="dashboard-range-controls" role="group" aria-label="{{ __('front.statistics_range') }}">
            <button type="button" class="dashboard-range-button" data-dashboard-range="7" aria-pressed="false"><span data-translate="front.range_days_7">{{ __('front.range_days_7') }}</span></button>
            <button type="button" class="dashboard-range-button" data-dashboard-range="15" aria-pressed="false"><span data-translate="front.range_days_15">{{ __('front.range_days_15') }}</span></button>
            <button type="button" class="dashboard-range-button is-active" data-dashboard-range="30" aria-pressed="true"><span data-translate="front.range_days_30">{{ __('front.range_days_30') }}</span></button>
        </div>
    </section>

    <section class="front-v2-dashboard-charts">
        <div class="front-v2-panel"><div class="layui-card-header"><span data-translate="front.funds_chart">{{ __('front.funds_chart') }}</span><div class="dashboard-chart-controls" role="group" aria-label="{{ __('front.chart_view_mode') }}"><button type="button" class="dashboard-chart-type is-active" data-chart-target="fundsChart" data-chart-type="bar" title="{{ __('front.chart_bar') }}" aria-label="{{ __('front.chart_bar') }}" aria-pressed="true"><i data-lucide="chart-column" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_bar') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="fundsChart" data-chart-type="line" title="{{ __('front.chart_line') }}" aria-label="{{ __('front.chart_line') }}" aria-pressed="false"><i data-lucide="chart-line" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_line') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="fundsChart" data-chart-type="area" title="{{ __('front.chart_area') }}" aria-label="{{ __('front.chart_area') }}" aria-pressed="false"><i data-lucide="chart-area" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_area') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="fundsChart" data-chart-type="pie" title="{{ __('front.chart_pie') }}" aria-label="{{ __('front.chart_pie') }}" aria-pressed="false"><i data-lucide="chart-pie" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_pie') }}</span></button></div></div><div class="layui-card-body"><div id="fundsChart" class="front-v2-chart"></div></div></div>
        <div class="front-v2-panel"><div class="layui-card-header"><span data-translate="front.network_chart">{{ __('front.network_chart') }}</span><div class="dashboard-chart-controls" role="group" aria-label="{{ __('front.chart_view_mode') }}"><button type="button" class="dashboard-chart-type" data-chart-target="networkChart" data-chart-type="bar" title="{{ __('front.chart_bar') }}" aria-label="{{ __('front.chart_bar') }}" aria-pressed="false"><i data-lucide="chart-column" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_bar') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="networkChart" data-chart-type="line" title="{{ __('front.chart_line') }}" aria-label="{{ __('front.chart_line') }}" aria-pressed="false"><i data-lucide="chart-line" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_line') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="networkChart" data-chart-type="area" title="{{ __('front.chart_area') }}" aria-label="{{ __('front.chart_area') }}" aria-pressed="false"><i data-lucide="chart-area" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_area') }}</span></button><button type="button" class="dashboard-chart-type is-active" data-chart-target="networkChart" data-chart-type="pie" title="{{ __('front.chart_pie') }}" aria-label="{{ __('front.chart_pie') }}" aria-pressed="true"><i data-lucide="chart-pie" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_pie') }}</span></button></div></div><div class="layui-card-body"><div id="networkChart" class="front-v2-chart"></div></div></div>
        <div class="front-v2-panel"><div class="layui-card-header"><span data-translate="front.order_chart">{{ __('front.order_chart') }}</span><div class="dashboard-chart-controls" role="group" aria-label="{{ __('front.chart_view_mode') }}"><button type="button" class="dashboard-chart-type is-active" data-chart-target="orderChart" data-chart-type="bar" title="{{ __('front.chart_bar') }}" aria-label="{{ __('front.chart_bar') }}" aria-pressed="true"><i data-lucide="chart-column" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_bar') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="orderChart" data-chart-type="line" title="{{ __('front.chart_line') }}" aria-label="{{ __('front.chart_line') }}" aria-pressed="false"><i data-lucide="chart-line" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_line') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="orderChart" data-chart-type="area" title="{{ __('front.chart_area') }}" aria-label="{{ __('front.chart_area') }}" aria-pressed="false"><i data-lucide="chart-area" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_area') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="orderChart" data-chart-type="pie" title="{{ __('front.chart_pie') }}" aria-label="{{ __('front.chart_pie') }}" aria-pressed="false"><i data-lucide="chart-pie" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_pie') }}</span></button></div></div><div class="layui-card-body"><div id="orderChart" class="front-v2-chart"></div></div></div>
        <div class="front-v2-panel"><div class="layui-card-header"><span data-translate="front.commission_chart">{{ __('front.commission_chart') }}</span><div class="dashboard-chart-controls" role="group" aria-label="{{ __('front.chart_view_mode') }}"><button type="button" class="dashboard-chart-type" data-chart-target="commissionChart" data-chart-type="bar" title="{{ __('front.chart_bar') }}" aria-label="{{ __('front.chart_bar') }}" aria-pressed="false"><i data-lucide="chart-column" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_bar') }}</span></button><button type="button" class="dashboard-chart-type is-active" data-chart-target="commissionChart" data-chart-type="line" title="{{ __('front.chart_line') }}" aria-label="{{ __('front.chart_line') }}" aria-pressed="true"><i data-lucide="chart-line" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_line') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="commissionChart" data-chart-type="area" title="{{ __('front.chart_area') }}" aria-label="{{ __('front.chart_area') }}" aria-pressed="false"><i data-lucide="chart-area" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_area') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="commissionChart" data-chart-type="pie" title="{{ __('front.chart_pie') }}" aria-label="{{ __('front.chart_pie') }}" aria-pressed="false"><i data-lucide="chart-pie" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_pie') }}</span></button></div></div><div class="layui-card-body"><div id="commissionChart" class="front-v2-chart"></div></div></div>
    </section>

    {{-- 日粒度趋势图：数据取自 /api/front/dashboard 的 series 字段，随统计范围 7/15/30 天联动。 --}}
    <section class="front-v2-dashboard-charts">
        <div class="front-v2-panel"><div class="layui-card-header"><span data-translate="front.flow_trend_chart">{{ __('front.flow_trend_chart') }}</span><div class="dashboard-chart-controls" role="group" aria-label="{{ __('front.chart_view_mode') }}"><button type="button" class="dashboard-chart-type" data-chart-target="flowTrendChart" data-chart-type="bar" title="{{ __('front.chart_bar') }}" aria-label="{{ __('front.chart_bar') }}" aria-pressed="false"><i data-lucide="chart-column" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_bar') }}</span></button><button type="button" class="dashboard-chart-type is-active" data-chart-target="flowTrendChart" data-chart-type="line" title="{{ __('front.chart_line') }}" aria-label="{{ __('front.chart_line') }}" aria-pressed="true"><i data-lucide="chart-line" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_line') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="flowTrendChart" data-chart-type="area" title="{{ __('front.chart_area') }}" aria-label="{{ __('front.chart_area') }}" aria-pressed="false"><i data-lucide="chart-area" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_area') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="flowTrendChart" data-chart-type="pie" title="{{ __('front.chart_pie') }}" aria-label="{{ __('front.chart_pie') }}" aria-pressed="false"><i data-lucide="chart-pie" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_pie') }}</span></button></div></div><div class="layui-card-body"><div id="flowTrendChart" class="front-v2-chart"></div></div></div>
        <div class="front-v2-panel"><div class="layui-card-header"><span data-translate="front.order_trend_chart">{{ __('front.order_trend_chart') }}</span><div class="dashboard-chart-controls" role="group" aria-label="{{ __('front.chart_view_mode') }}"><button type="button" class="dashboard-chart-type is-active" data-chart-target="orderTrendChart" data-chart-type="bar" title="{{ __('front.chart_bar') }}" aria-label="{{ __('front.chart_bar') }}" aria-pressed="true"><i data-lucide="chart-column" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_bar') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="orderTrendChart" data-chart-type="line" title="{{ __('front.chart_line') }}" aria-label="{{ __('front.chart_line') }}" aria-pressed="false"><i data-lucide="chart-line" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_line') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="orderTrendChart" data-chart-type="area" title="{{ __('front.chart_area') }}" aria-label="{{ __('front.chart_area') }}" aria-pressed="false"><i data-lucide="chart-area" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_area') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="orderTrendChart" data-chart-type="pie" title="{{ __('front.chart_pie') }}" aria-label="{{ __('front.chart_pie') }}" aria-pressed="false"><i data-lucide="chart-pie" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_pie') }}</span></button></div></div><div class="layui-card-body"><div id="orderTrendChart" class="front-v2-chart"></div></div></div>
        <div class="front-v2-panel"><div class="layui-card-header"><span data-translate="front.profit_trend_chart">{{ __('front.profit_trend_chart') }}</span><div class="dashboard-chart-controls" role="group" aria-label="{{ __('front.chart_view_mode') }}"><button type="button" class="dashboard-chart-type" data-chart-target="profitTrendChart" data-chart-type="bar" title="{{ __('front.chart_bar') }}" aria-label="{{ __('front.chart_bar') }}" aria-pressed="false"><i data-lucide="chart-column" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_bar') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="profitTrendChart" data-chart-type="line" title="{{ __('front.chart_line') }}" aria-label="{{ __('front.chart_line') }}" aria-pressed="false"><i data-lucide="chart-line" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_line') }}</span></button><button type="button" class="dashboard-chart-type is-active" data-chart-target="profitTrendChart" data-chart-type="area" title="{{ __('front.chart_area') }}" aria-label="{{ __('front.chart_area') }}" aria-pressed="true"><i data-lucide="chart-area" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_area') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="profitTrendChart" data-chart-type="pie" title="{{ __('front.chart_pie') }}" aria-label="{{ __('front.chart_pie') }}" aria-pressed="false"><i data-lucide="chart-pie" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_pie') }}</span></button></div></div><div class="layui-card-body"><div id="profitTrendChart" class="front-v2-chart"></div></div></div>
        <div class="front-v2-panel"><div class="layui-card-header"><span data-translate="front.commission_trend_chart">{{ __('front.commission_trend_chart') }}</span><div class="dashboard-chart-controls" role="group" aria-label="{{ __('front.chart_view_mode') }}"><button type="button" class="dashboard-chart-type" data-chart-target="commissionTrendChart" data-chart-type="bar" title="{{ __('front.chart_bar') }}" aria-label="{{ __('front.chart_bar') }}" aria-pressed="false"><i data-lucide="chart-column" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_bar') }}</span></button><button type="button" class="dashboard-chart-type is-active" data-chart-target="commissionTrendChart" data-chart-type="line" title="{{ __('front.chart_line') }}" aria-label="{{ __('front.chart_line') }}" aria-pressed="true"><i data-lucide="chart-line" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_line') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="commissionTrendChart" data-chart-type="area" title="{{ __('front.chart_area') }}" aria-label="{{ __('front.chart_area') }}" aria-pressed="false"><i data-lucide="chart-area" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_area') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="commissionTrendChart" data-chart-type="pie" title="{{ __('front.chart_pie') }}" aria-label="{{ __('front.chart_pie') }}" aria-pressed="false"><i data-lucide="chart-pie" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_pie') }}</span></button></div></div><div class="layui-card-body"><div id="commissionTrendChart" class="front-v2-chart"></div></div></div>
    </section>

    <section class="front-v2-stat-grid">
        <dl class="front-v2-stat"><dt data-translate="front.monthly_deposit">{{ __('front.monthly_deposit') }}</dt><dd id="monthlyDeposit">0.00</dd></dl>
        <dl class="front-v2-stat"><dt data-translate="front.monthly_withdraw">{{ __('front.monthly_withdraw') }}</dt><dd id="monthlyWithdraw">0.00</dd></dl>
        <dl class="front-v2-stat"><dt data-translate="front.monthly_open_orders">{{ __('front.monthly_open_orders') }}</dt><dd id="monthlyOpenOrders">0</dd></dl>
        <dl class="front-v2-stat"><dt data-translate="front.monthly_closed_orders">{{ __('front.monthly_closed_orders') }}</dt><dd id="monthlyClosedOrders">0</dd></dl>
        <dl class="front-v2-stat"><dt data-translate="front.monthly_commission">{{ __('front.monthly_commission') }}</dt><dd id="monthlyCommission">0.00</dd></dl>
        <dl class="front-v2-stat"><dt data-translate="front.monthly_net_flow">{{ __('front.monthly_net_flow') }}</dt><dd id="monthlyNetFlow">0.00</dd></dl>
    </section>

    <section class="front-v2-two-col">
        <div class="front-v2-panel">
            <div class="front-v2-panel-title"><h2 data-translate="front.share_url">{{ __('front.share_url') }}</h2><p>{{ app()->getLocale() === 'en' ? 'Invitation links generated for agent workflows.' : '代理业务可用的邀请注册链接。' }}</p></div>
            <div class="front-v2-panel-body">
                <div class="dashboard-share-list" id="shareUrlList">
                    <div class="front-v2-empty-state" data-empty-placeholder>
                        {{-- 空状态使用 Lucide 语义图标，避免表情符号在不同系统呈现不一致。 --}}
                        <div class="front-v2-empty-icon"><i data-lucide="clipboard-list" aria-hidden="true"></i></div>
                        <p class="front-v2-empty-title">{{ app()->getLocale() === 'en' ? 'No share links' : '暂无分享链接' }}</p>
                        <p class="front-v2-empty-text">{{ app()->getLocale() === 'en' ? 'Agent accounts can view invitation registration links here' : '代理账户可以在此查看邀请注册链接' }}</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="front-v2-panel">
            <div class="front-v2-panel-title"><h2 data-translate="front.news_list">{{ __('front.news_list') }}</h2><p>{{ app()->getLocale() === 'en' ? 'Latest announcements and platform notices.' : '最新公告与平台通知。' }}</p></div>
            <div class="front-v2-panel-body">
                <ul class="layui-timeline" id="dashboardNews">
                    <li class="front-v2-empty-state" data-empty-placeholder style="list-style:none;">
                        {{-- 新闻空状态沿用共享 Lucide 渲染链，动态主题下保持同一笔画与颜色。 --}}
                        <div class="front-v2-empty-icon"><i data-lucide="newspaper" aria-hidden="true"></i></div>
                        <p class="front-v2-empty-title">{{ app()->getLocale() === 'en' ? 'No news yet' : '暂无新闻' }}</p>
                        <p class="front-v2-empty-text">{{ app()->getLocale() === 'en' ? 'Latest platform announcements will appear here' : '最新平台公告将显示在这里' }}</p>
                    </li>
                </ul>
            </div>
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script type="application/json" id="crm-dashboard-routes">
{"crmuiDashboard": @json(route('front_crmui_app', ['path' => 'dashboard'])), "naiveDashboard": @json(route('front_naive_app', ['path' => 'dashboard']))}
</script>
<script src="{{ asset('/js/vendor/echarts/echarts.common.min.js') }}"></script>
<div hidden data-layui-page="dashboard/index"></div>
@endsection
