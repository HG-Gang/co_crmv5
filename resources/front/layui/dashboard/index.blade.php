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
<style>
    .dashboard-page { display: grid; gap: 10px; }
    .dashboard-page .layui-card { margin-bottom: 0; border-radius: 8px; overflow: hidden; }
    /* 紧凑化：卡片正文内边距统一收紧，避免大面积空白。 */
    .dashboard-page .layui-card-body { padding: 9px 10px; }
    .dashboard-page .layui-card-header { min-height: 34px; line-height: 34px; padding: 0 10px; font-size: 13px; }
    .dashboard-hero {
        display: grid;
        grid-template-columns: minmax(0, 1.4fr) minmax(280px, .9fr);
        gap: 14px;
        align-items: stretch;
        min-height: 158px;
    }
    .dashboard-hero-main {
        position: relative;
        padding: 20px;
        color: var(--front-side-text);
        border: 1px solid var(--front-line);
        border-radius: 8px;
        overflow: hidden;
        background: var(--front-side);
    }
    .dashboard-hero-title { position: relative; z-index: 1; font-size: 22px; line-height: 32px; font-weight: 800; }
    .dashboard-hero-title strong { margin: 0 8px; }
    .dashboard-hero-sub { position: relative; z-index: 1; margin-top: 8px; color: var(--front-side-muted); }
    .dashboard-auth-guide { position: relative; z-index: 1; display: inline-flex; align-items: center; gap: 6px; min-height: 38px; margin-top: 14px; padding: 0 12px; border: 1px solid var(--front-line); border-radius: 6px; color: var(--front-strong); background: var(--front-panel); }
    .dashboard-auth-guide:hover { color: var(--front-blue); }
    .dashboard-title-badge { margin-left: 8px; vertical-align: middle; }
    .dashboard-hero-mini { position: relative; z-index: 1; display: flex; flex-wrap: wrap; gap: 8px; margin-top: 22px; }
    .dashboard-mini-pill { padding: 6px 10px; border: 1px solid var(--front-side-muted); border-radius: 4px; background: var(--front-side-soft); color: var(--front-side-text); font-size: 12px; }
    .dashboard-control-panel { padding: 12px; border: 1px solid var(--front-line); border-radius: 8px; background: var(--front-panel); overflow: visible; }
    .dashboard-actions { position: relative; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; overflow: visible; }
    .dashboard-control-panel .crm-theme-picker { height: 38px; color: var(--front-blue); }
    /* 语言 / 皮肤 / 声音入口一律只显示图标，文案保留在 DOM 内供读屏与自动化使用。 */
    .dashboard-switch-control { position: relative; width: 38px; min-width: 38px; height: 38px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--front-line); border-radius: 8px; background: var(--front-panel); color: var(--front-blue); cursor: pointer; transition: border-color .2s, background .2s, box-shadow .2s; }
    .dashboard-switch-control:hover,
    .dashboard-switch-control:focus-visible,
    .dashboard-switch-control.is-open { border-color: var(--front-blue); background: var(--front-hover); box-shadow: 0 0 0 3px var(--front-focus-ring); }
    .dashboard-switch-control.is-open { z-index: 1200; }
    .dashboard-switch-control i { flex: 0 0 auto; font-size: 18px; }
    /* 当前值只作为无障碍文本存在，不占据视觉空间。 */
    .dashboard-switch-current { position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden; clip: rect(0 0 0 0); clip-path: inset(50%); white-space: nowrap; border: 0; }
    .dashboard-option-menu { position: absolute; top: calc(100% + 8px); right: 0; z-index: 1201; display: none; min-width: 148px; padding: 6px; border: 1px solid var(--front-line); border-radius: 8px; background: var(--front-panel); box-shadow: 0 12px 30px var(--front-shadow); pointer-events: auto; }
    /* 鼠标悬停即展开下拉；点击与键盘仍由 is-open 驱动，触屏不依赖 hover。 */
    .dashboard-switch-control:hover .dashboard-option-menu,
    .dashboard-switch-control:focus-within .dashboard-option-menu,
    .dashboard-switch-control.is-open .dashboard-option-menu { display: grid; gap: 4px; }
    /* 悬停热区向上延伸，避免指针在按钮与菜单之间的缝隙里丢失 hover。 */
    .dashboard-switch-control .dashboard-option-menu::before { content: ''; position: absolute; top: -10px; left: 0; right: 0; height: 10px; }
    .dashboard-option-menu button { display: flex; align-items: center; gap: 7px; width: 100%; min-height: 34px; padding: 0 8px; border: 0; border-radius: 6px; background: transparent; color: var(--front-text); text-align: left; cursor: pointer; pointer-events: auto; }
    .dashboard-option-menu button:hover,
    .dashboard-option-menu button.is-active { color: var(--front-blue); background: var(--front-soft-accent); }
    .dashboard-current-label { flex: 0 0 auto; padding: 2px 7px; border-radius: 4px; color: var(--front-blue); background: var(--front-chip-bg); font-weight: 700; }
    .dashboard-downloads { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
    .dashboard-downloads .layui-btn { margin: 0; }
    .dashboard-metric-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 10px; }
    .dashboard-metric-card .layui-card-body { min-height: 58px; padding: 8px 10px; }
    .dashboard-metric-label { margin-bottom: 4px; color: var(--front-muted); font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .dashboard-value { min-height: 26px; font-size: 19px; line-height: 26px; font-weight: 800; font-variant-numeric: tabular-nums; }
    .dashboard-value.blue { color: var(--front-blue); }
    .dashboard-value.green { color: var(--front-accent); }
    .dashboard-value.orange { color: var(--front-warn); }
    .dashboard-value.red { color: var(--front-danger); }
    .dashboard-value.cyan { color: var(--front-cyan); }
    .dashboard-section-grid { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(320px, .75fr); gap: 14px; }
    .dashboard-chart-grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 14px; align-items: stretch; }
    .dashboard-chart-card { grid-column: span 6; }
    .dashboard-chart-card.is-funds { grid-column: span 6; }
    .dashboard-chart-card.is-network { grid-column: span 6; }
    .dashboard-chart-card.is-orders { grid-column: span 5; }
    .dashboard-chart-card.is-commission { grid-column: span 7; }
    .dashboard-chart { width: 100%; height: 212px; }
    .dashboard-chart-card.is-funds .dashboard-chart { height: 220px; }
    /* 日粒度趋势图区：两列栅格，与快照图表区保持同一密度。 */
    .dashboard-trend-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .dashboard-trend-card .dashboard-chart { height: 200px; }
    .dashboard-trend-card .layui-card-body { min-height: 216px; padding: 8px 10px 10px; }
    @media screen and (max-width: 900px) { .dashboard-trend-grid { grid-template-columns: 1fr; } }
    .dashboard-chart-card .layui-card-body { min-height: 228px; padding: 8px 10px 10px; }
    .dashboard-chart-card.is-funds .layui-card-body { min-height: 236px; }
    .dashboard-range-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 44px; }
    .dashboard-range-title { color: var(--front-strong); font-weight: 700; }
    .dashboard-range-controls { display: inline-flex; align-items: center; border: 1px solid var(--front-line); border-radius: 8px; overflow: hidden; background: var(--front-panel); }
    .dashboard-range-button { min-width: 76px; min-height: 44px; padding: 0 12px; border: 0; border-right: 1px solid var(--front-line); background: transparent; color: var(--front-text); cursor: pointer; }
    .dashboard-range-button:last-child { border-right: 0; }
    .dashboard-range-button:hover { background: var(--front-hover); }
    .dashboard-range-button.is-active { background: var(--front-blue); color: var(--front-panel); font-weight: 700; }
    .dashboard-range-button:focus-visible,
    .dashboard-chart-type:focus-visible { position: relative; z-index: 1; outline: 2px solid var(--front-blue); outline-offset: -2px; }
    .dashboard-chart-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
    .dashboard-chart-head span { font-weight: 600; color: var(--front-strong); }
    .dashboard-chart-controls { display: inline-flex; flex: 0 0 auto; gap: 4px; }
    .dashboard-chart-type { display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; padding: 0; border: 1px solid var(--front-line); border-radius: 6px; background: var(--front-input); color: var(--front-text); cursor: pointer; }
    .dashboard-chart-type:hover { border-color: var(--front-blue); color: var(--front-blue); background: var(--front-hover); }
    .dashboard-chart-type.is-active { border-color: var(--front-blue); color: var(--front-panel); background: var(--front-blue); }
    .dashboard-chart-type svg { width: 18px; height: 18px; }
    .dashboard-share-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .dashboard-share-item { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px; align-items: center; padding: 10px; border: 1px solid var(--front-line); border-radius: 8px; background: var(--front-table-head); }
    .dashboard-share-label { margin-bottom: 5px; color: var(--front-strong); font-weight: 700; }
    .dashboard-share-url { color: var(--front-blue); word-break: break-all; }
    .dashboard-news-title { display: block; color: var(--front-strong); font-weight: 700; line-height: 20px; }
    .dashboard-news-meta { margin-top: 4px; color: var(--front-muted); font-size: 12px; line-height: 18px; }
    .dashboard-news-excerpt { margin-top: 6px; color: var(--front-text); line-height: 20px; word-break: break-word; }
    @media screen and (max-width: 1180px) { .dashboard-metric-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } .dashboard-chart-card, .dashboard-chart-card.is-funds, .dashboard-chart-card.is-network, .dashboard-chart-card.is-orders, .dashboard-chart-card.is-commission { grid-column: span 6; } }
    @media screen and (max-width: 900px) { .dashboard-hero, .dashboard-section-grid { grid-template-columns: 1fr; } .dashboard-chart-grid { grid-template-columns: 1fr; } .dashboard-chart-card, .dashboard-chart-card.is-funds, .dashboard-chart-card.is-network, .dashboard-chart-card.is-orders, .dashboard-chart-card.is-commission { grid-column: 1; } }
    @media screen and (max-width: 560px) { .dashboard-metric-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .dashboard-share-list { grid-template-columns: 1fr; } .dashboard-downloads { grid-template-columns: 1fr; } .dashboard-range-toolbar { align-items: stretch; flex-direction: column; } .dashboard-range-controls { display: flex; width: 100%; } .dashboard-range-button { flex: 1 1 0; min-width: 0; } .dashboard-chart { height: 196px; } }
    /* 触屏设备把图标入口放大到 44px 触控标准。 */
    @media (pointer: coarse) { .dashboard-switch-control { width: 44px; min-width: 44px; height: 44px; } }
</style>
@endsection

@section('content')
<div class="dashboard-page crm-visual-page crm-dashboard-page" data-visual-c-reference="front-dashboard">
    <section class="dashboard-hero">
        <div class="dashboard-hero-main">
            <div class="dashboard-hero-title">
                <span data-translate="common.welcome">{{ __('common.welcome') }}</span>
                <strong id="welcomeUser"></strong>
                <span id="customerTitle" class="layui-badge layui-bg-green dashboard-title-badge"></span>
            </div>
            <div class="dashboard-hero-sub">
                <span data-translate="front.monthly_period">{{ __('front.monthly_period') }}</span>
                <span id="periodRange"></span>
            </div>
            <a class="dashboard-auth-guide layui-hide" id="identityGuideBtn" href="{{ route('front_page_profile', ['frame' => 1]) }}#identityForm">
                <i data-lucide="badge-check"></i>
                <span data-translate="front.identity_upload_guide">{{ __('front.identity_upload_guide') }}</span>
            </a>
            <div class="dashboard-hero-mini">
                <span class="dashboard-mini-pill"><span data-translate="front.direct_agents">{{ __('front.direct_agents') }}</span>: <b id="directAgentsCount">0</b></span>
                <span class="dashboard-mini-pill"><span data-translate="front.indirect_agents">{{ __('front.indirect_agents') }}</span>: <b id="indirectAgentsCount">0</b></span>
                <span class="dashboard-mini-pill"><span data-translate="front.direct_customers">{{ __('front.direct_customers') }}</span>: <b id="directCustomersCount">0</b></span>
                <span class="dashboard-mini-pill"><span data-translate="front.indirect_customers">{{ __('front.indirect_customers') }}</span>: <b id="indirectCustomersCount">0</b></span>
            </div>
        </div>
        <div class="dashboard-control-panel">
            <div class="dashboard-actions">
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
                <div class="dashboard-downloads">
                    <a id="pcDownloadLink" class="layui-btn layui-btn-primary layui-btn-sm" href="#" target="_blank" rel="noopener"><i data-lucide="circle-arrow-down"></i> <span data-translate="front.pc_download">{{ __('front.pc_download') }}</span></a>
                    <a id="mobileDownloadLink" class="layui-btn layui-btn-primary layui-btn-sm" href="#" target="_blank" rel="noopener"><i data-lucide="smartphone"></i> <span data-translate="front.mobile_download">{{ __('front.mobile_download') }}</span></a>
                </div>
            </div>
        </div>
    </section>

    <section class="dashboard-metric-grid">
        <div class="layui-card dashboard-metric-card"><div class="layui-card-body"><div class="dashboard-metric-label" data-translate="front.commission_rate">{{ __('front.commission_rate') }}</div><div class="dashboard-value blue" id="commissionRate">0%</div></div></div>
        <div class="layui-card dashboard-metric-card"><div class="layui-card-body"><div class="dashboard-metric-label" data-translate="front.total_commission">{{ __('front.total_commission') }}</div><div class="dashboard-value green" id="totalCommission">0.00</div></div></div>
        <div class="layui-card dashboard-metric-card"><div class="layui-card-body"><div class="dashboard-metric-label" data-translate="front.total_funds">{{ __('front.total_funds') }}</div><div class="dashboard-value cyan" id="accountBalance">0.00</div></div></div>
        <div class="layui-card dashboard-metric-card"><div class="layui-card-body"><div class="dashboard-metric-label" data-translate="front.equity">{{ __('front.equity') }}</div><div class="dashboard-value orange" id="accountEquity">0.00</div></div></div>
        <div class="layui-card dashboard-metric-card"><div class="layui-card-body"><div class="dashboard-metric-label" data-translate="front.effective_credit">{{ __('front.effective_credit') }}</div><div class="dashboard-value blue" id="effectiveCredit">0.00</div></div></div>
        <div class="layui-card dashboard-metric-card"><div class="layui-card-body"><div class="dashboard-metric-label" data-translate="front.open_orders">{{ __('front.open_orders') }}</div><div class="dashboard-value red" id="openOrdersCount">0</div></div></div>
    </section>

    <section class="dashboard-range-toolbar" aria-label="{{ __('front.statistics_range') }}">
        <span class="dashboard-range-title" data-translate="front.statistics_range">{{ __('front.statistics_range') }}</span>
        <div class="dashboard-range-controls" role="group" aria-label="{{ __('front.statistics_range') }}">
            <button type="button" class="dashboard-range-button" data-dashboard-range="7" aria-pressed="false"><span data-translate="front.range_days_7">{{ __('front.range_days_7') }}</span></button>
            <button type="button" class="dashboard-range-button" data-dashboard-range="15" aria-pressed="false"><span data-translate="front.range_days_15">{{ __('front.range_days_15') }}</span></button>
            <button type="button" class="dashboard-range-button is-active" data-dashboard-range="30" aria-pressed="true"><span data-translate="front.range_days_30">{{ __('front.range_days_30') }}</span></button>
        </div>
    </section>

    <section class="dashboard-chart-grid">
        <div class="layui-card dashboard-chart-card is-funds"><div class="layui-card-header dashboard-chart-head"><span data-translate="front.funds_chart">{{ __('front.funds_chart') }}</span><div class="dashboard-chart-controls" role="group" aria-label="{{ __('front.chart_view_mode') }}"><button type="button" class="dashboard-chart-type is-active" data-chart-target="fundsChart" data-chart-type="bar" title="{{ __('front.chart_bar') }}" aria-label="{{ __('front.chart_bar') }}" aria-pressed="true"><i data-lucide="chart-column" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_bar') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="fundsChart" data-chart-type="line" title="{{ __('front.chart_line') }}" aria-label="{{ __('front.chart_line') }}" aria-pressed="false"><i data-lucide="chart-line" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_line') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="fundsChart" data-chart-type="area" title="{{ __('front.chart_area') }}" aria-label="{{ __('front.chart_area') }}" aria-pressed="false"><i data-lucide="chart-area" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_area') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="fundsChart" data-chart-type="pie" title="{{ __('front.chart_pie') }}" aria-label="{{ __('front.chart_pie') }}" aria-pressed="false"><i data-lucide="chart-pie" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_pie') }}</span></button></div></div><div class="layui-card-body"><div id="fundsChart" class="dashboard-chart"></div></div></div>
        <div class="layui-card dashboard-chart-card is-network"><div class="layui-card-header dashboard-chart-head"><span data-translate="front.network_chart">{{ __('front.network_chart') }}</span><div class="dashboard-chart-controls" role="group" aria-label="{{ __('front.chart_view_mode') }}"><button type="button" class="dashboard-chart-type" data-chart-target="networkChart" data-chart-type="bar" title="{{ __('front.chart_bar') }}" aria-label="{{ __('front.chart_bar') }}" aria-pressed="false"><i data-lucide="chart-column" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_bar') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="networkChart" data-chart-type="line" title="{{ __('front.chart_line') }}" aria-label="{{ __('front.chart_line') }}" aria-pressed="false"><i data-lucide="chart-line" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_line') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="networkChart" data-chart-type="area" title="{{ __('front.chart_area') }}" aria-label="{{ __('front.chart_area') }}" aria-pressed="false"><i data-lucide="chart-area" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_area') }}</span></button><button type="button" class="dashboard-chart-type is-active" data-chart-target="networkChart" data-chart-type="pie" title="{{ __('front.chart_pie') }}" aria-label="{{ __('front.chart_pie') }}" aria-pressed="true"><i data-lucide="chart-pie" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_pie') }}</span></button></div></div><div class="layui-card-body"><div id="networkChart" class="dashboard-chart"></div></div></div>
        <div class="layui-card dashboard-chart-card is-orders"><div class="layui-card-header dashboard-chart-head"><span data-translate="front.order_chart">{{ __('front.order_chart') }}</span><div class="dashboard-chart-controls" role="group" aria-label="{{ __('front.chart_view_mode') }}"><button type="button" class="dashboard-chart-type is-active" data-chart-target="orderChart" data-chart-type="bar" title="{{ __('front.chart_bar') }}" aria-label="{{ __('front.chart_bar') }}" aria-pressed="true"><i data-lucide="chart-column" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_bar') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="orderChart" data-chart-type="line" title="{{ __('front.chart_line') }}" aria-label="{{ __('front.chart_line') }}" aria-pressed="false"><i data-lucide="chart-line" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_line') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="orderChart" data-chart-type="area" title="{{ __('front.chart_area') }}" aria-label="{{ __('front.chart_area') }}" aria-pressed="false"><i data-lucide="chart-area" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_area') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="orderChart" data-chart-type="pie" title="{{ __('front.chart_pie') }}" aria-label="{{ __('front.chart_pie') }}" aria-pressed="false"><i data-lucide="chart-pie" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_pie') }}</span></button></div></div><div class="layui-card-body"><div id="orderChart" class="dashboard-chart"></div></div></div>
        <div class="layui-card dashboard-chart-card is-commission"><div class="layui-card-header dashboard-chart-head"><span data-translate="front.commission_chart">{{ __('front.commission_chart') }}</span><div class="dashboard-chart-controls" role="group" aria-label="{{ __('front.chart_view_mode') }}"><button type="button" class="dashboard-chart-type" data-chart-target="commissionChart" data-chart-type="bar" title="{{ __('front.chart_bar') }}" aria-label="{{ __('front.chart_bar') }}" aria-pressed="false"><i data-lucide="chart-column" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_bar') }}</span></button><button type="button" class="dashboard-chart-type is-active" data-chart-target="commissionChart" data-chart-type="line" title="{{ __('front.chart_line') }}" aria-label="{{ __('front.chart_line') }}" aria-pressed="true"><i data-lucide="chart-line" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_line') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="commissionChart" data-chart-type="area" title="{{ __('front.chart_area') }}" aria-label="{{ __('front.chart_area') }}" aria-pressed="false"><i data-lucide="chart-area" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_area') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="commissionChart" data-chart-type="pie" title="{{ __('front.chart_pie') }}" aria-label="{{ __('front.chart_pie') }}" aria-pressed="false"><i data-lucide="chart-pie" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_pie') }}</span></button></div></div><div class="layui-card-body"><div id="commissionChart" class="dashboard-chart"></div></div></div>
    </section>

    {{-- 日粒度趋势图：数据取自 /api/front/dashboard 的 series 字段，随统计范围 7/15/30 天联动。 --}}
    <section class="dashboard-trend-grid">
        <div class="layui-card dashboard-trend-card"><div class="layui-card-header dashboard-chart-head"><span data-translate="front.flow_trend_chart">{{ __('front.flow_trend_chart') }}</span><div class="dashboard-chart-controls" role="group" aria-label="{{ __('front.chart_view_mode') }}"><button type="button" class="dashboard-chart-type" data-chart-target="flowTrendChart" data-chart-type="bar" title="{{ __('front.chart_bar') }}" aria-label="{{ __('front.chart_bar') }}" aria-pressed="false"><i data-lucide="chart-column" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_bar') }}</span></button><button type="button" class="dashboard-chart-type is-active" data-chart-target="flowTrendChart" data-chart-type="line" title="{{ __('front.chart_line') }}" aria-label="{{ __('front.chart_line') }}" aria-pressed="true"><i data-lucide="chart-line" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_line') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="flowTrendChart" data-chart-type="area" title="{{ __('front.chart_area') }}" aria-label="{{ __('front.chart_area') }}" aria-pressed="false"><i data-lucide="chart-area" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_area') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="flowTrendChart" data-chart-type="pie" title="{{ __('front.chart_pie') }}" aria-label="{{ __('front.chart_pie') }}" aria-pressed="false"><i data-lucide="chart-pie" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_pie') }}</span></button></div></div><div class="layui-card-body"><div id="flowTrendChart" class="dashboard-chart"></div></div></div>
        <div class="layui-card dashboard-trend-card"><div class="layui-card-header dashboard-chart-head"><span data-translate="front.order_trend_chart">{{ __('front.order_trend_chart') }}</span><div class="dashboard-chart-controls" role="group" aria-label="{{ __('front.chart_view_mode') }}"><button type="button" class="dashboard-chart-type is-active" data-chart-target="orderTrendChart" data-chart-type="bar" title="{{ __('front.chart_bar') }}" aria-label="{{ __('front.chart_bar') }}" aria-pressed="true"><i data-lucide="chart-column" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_bar') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="orderTrendChart" data-chart-type="line" title="{{ __('front.chart_line') }}" aria-label="{{ __('front.chart_line') }}" aria-pressed="false"><i data-lucide="chart-line" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_line') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="orderTrendChart" data-chart-type="area" title="{{ __('front.chart_area') }}" aria-label="{{ __('front.chart_area') }}" aria-pressed="false"><i data-lucide="chart-area" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_area') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="orderTrendChart" data-chart-type="pie" title="{{ __('front.chart_pie') }}" aria-label="{{ __('front.chart_pie') }}" aria-pressed="false"><i data-lucide="chart-pie" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_pie') }}</span></button></div></div><div class="layui-card-body"><div id="orderTrendChart" class="dashboard-chart"></div></div></div>
        <div class="layui-card dashboard-trend-card"><div class="layui-card-header dashboard-chart-head"><span data-translate="front.profit_trend_chart">{{ __('front.profit_trend_chart') }}</span><div class="dashboard-chart-controls" role="group" aria-label="{{ __('front.chart_view_mode') }}"><button type="button" class="dashboard-chart-type" data-chart-target="profitTrendChart" data-chart-type="bar" title="{{ __('front.chart_bar') }}" aria-label="{{ __('front.chart_bar') }}" aria-pressed="false"><i data-lucide="chart-column" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_bar') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="profitTrendChart" data-chart-type="line" title="{{ __('front.chart_line') }}" aria-label="{{ __('front.chart_line') }}" aria-pressed="false"><i data-lucide="chart-line" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_line') }}</span></button><button type="button" class="dashboard-chart-type is-active" data-chart-target="profitTrendChart" data-chart-type="area" title="{{ __('front.chart_area') }}" aria-label="{{ __('front.chart_area') }}" aria-pressed="true"><i data-lucide="chart-area" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_area') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="profitTrendChart" data-chart-type="pie" title="{{ __('front.chart_pie') }}" aria-label="{{ __('front.chart_pie') }}" aria-pressed="false"><i data-lucide="chart-pie" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_pie') }}</span></button></div></div><div class="layui-card-body"><div id="profitTrendChart" class="dashboard-chart"></div></div></div>
        <div class="layui-card dashboard-trend-card"><div class="layui-card-header dashboard-chart-head"><span data-translate="front.commission_trend_chart">{{ __('front.commission_trend_chart') }}</span><div class="dashboard-chart-controls" role="group" aria-label="{{ __('front.chart_view_mode') }}"><button type="button" class="dashboard-chart-type" data-chart-target="commissionTrendChart" data-chart-type="bar" title="{{ __('front.chart_bar') }}" aria-label="{{ __('front.chart_bar') }}" aria-pressed="false"><i data-lucide="chart-column" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_bar') }}</span></button><button type="button" class="dashboard-chart-type is-active" data-chart-target="commissionTrendChart" data-chart-type="line" title="{{ __('front.chart_line') }}" aria-label="{{ __('front.chart_line') }}" aria-pressed="true"><i data-lucide="chart-line" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_line') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="commissionTrendChart" data-chart-type="area" title="{{ __('front.chart_area') }}" aria-label="{{ __('front.chart_area') }}" aria-pressed="false"><i data-lucide="chart-area" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_area') }}</span></button><button type="button" class="dashboard-chart-type" data-chart-target="commissionTrendChart" data-chart-type="pie" title="{{ __('front.chart_pie') }}" aria-label="{{ __('front.chart_pie') }}" aria-pressed="false"><i data-lucide="chart-pie" aria-hidden="true"></i><span class="crm-sr-only">{{ __('front.chart_pie') }}</span></button></div></div><div class="layui-card-body"><div id="commissionTrendChart" class="dashboard-chart"></div></div></div>
    </section>

    <section class="dashboard-metric-grid">
        <div class="layui-card dashboard-metric-card"><div class="layui-card-body"><div class="dashboard-metric-label" data-translate="front.monthly_deposit">{{ __('front.monthly_deposit') }}</div><div class="dashboard-value green" id="monthlyDeposit">0.00</div></div></div>
        <div class="layui-card dashboard-metric-card"><div class="layui-card-body"><div class="dashboard-metric-label" data-translate="front.monthly_withdraw">{{ __('front.monthly_withdraw') }}</div><div class="dashboard-value red" id="monthlyWithdraw">0.00</div></div></div>
        <div class="layui-card dashboard-metric-card"><div class="layui-card-body"><div class="dashboard-metric-label" data-translate="front.monthly_open_orders">{{ __('front.monthly_open_orders') }}</div><div class="dashboard-value cyan" id="monthlyOpenOrders">0</div></div></div>
        <div class="layui-card dashboard-metric-card"><div class="layui-card-body"><div class="dashboard-metric-label" data-translate="front.monthly_closed_orders">{{ __('front.monthly_closed_orders') }}</div><div class="dashboard-value blue" id="monthlyClosedOrders">0</div></div></div>
        <div class="layui-card dashboard-metric-card"><div class="layui-card-body"><div class="dashboard-metric-label" data-translate="front.monthly_commission">{{ __('front.monthly_commission') }}</div><div class="dashboard-value orange" id="monthlyCommission">0.00</div></div></div>
        <div class="layui-card dashboard-metric-card"><div class="layui-card-body"><div class="dashboard-metric-label" data-translate="front.monthly_net_flow">{{ __('front.monthly_net_flow') }}</div><div class="dashboard-value green" id="monthlyNetFlow">0.00</div></div></div>
    </section>

    <section class="dashboard-section-grid">
        <div class="layui-card"><div class="layui-card-header" data-translate="front.share_url">{{ __('front.share_url') }}</div><div class="layui-card-body"><div class="dashboard-share-list" id="shareUrlList"></div></div></div>
        <div class="layui-card"><div class="layui-card-header" data-translate="front.news_list">{{ __('front.news_list') }}</div><div class="layui-card-body"><ul class="layui-timeline" id="dashboardNews"></ul></div></div>
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
