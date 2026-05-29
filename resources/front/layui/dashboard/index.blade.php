@extends('front_layui::layouts.app')

@section('title', __('front.dashboard'))
@section('breadcrumb', __('breadcrumb.front_dashboard'))

@section('styles')
<style>
    .dashboard-page { display: grid; gap: 14px; }
    .dashboard-page .layui-card { margin-bottom: 0; border-radius: 10px; overflow: hidden; }
    .dashboard-hero {
        display: grid;
        grid-template-columns: minmax(0, 1.4fr) minmax(280px, .9fr);
        gap: 14px;
        align-items: stretch;
        min-height: 158px;
    }
    .dashboard-hero-main {
        position: relative;
        padding: 22px;
        color: #fff;
        border-radius: 12px;
        overflow: hidden;
        background: linear-gradient(135deg, var(--front-side, #151b23), var(--front-blue, #2080f0));
    }
    .dashboard-hero-main:after {
        content: '';
        position: absolute;
        right: -50px;
        top: -60px;
        width: 220px;
        height: 220px;
        border-radius: 50%;
        background: rgba(255,255,255,.13);
    }
    .dashboard-hero-title { position: relative; z-index: 1; font-size: 22px; line-height: 32px; font-weight: 800; }
    .dashboard-hero-title strong { margin: 0 8px; }
    .dashboard-hero-sub { position: relative; z-index: 1; margin-top: 8px; color: rgba(255,255,255,.78); }
    .dashboard-title-badge { margin-left: 8px; vertical-align: middle; }
    .dashboard-hero-mini { position: relative; z-index: 1; display: flex; flex-wrap: wrap; gap: 8px; margin-top: 22px; }
    .dashboard-mini-pill { padding: 6px 10px; border: 1px solid rgba(255,255,255,.26); border-radius: 999px; background: rgba(255,255,255,.12); color: #fff; font-size: 12px; }
    .dashboard-control-panel { padding: 16px; border: 1px solid var(--front-line, #dde4ec); border-radius: 8px; background: var(--front-panel, #fff); }
    .dashboard-actions { display: grid; gap: 10px; }
    .dashboard-switch-control { min-height: 36px; display: flex; align-items: center; gap: 8px; padding: 0 10px; border: 1px solid var(--front-line, #dce3ec); border-radius: 6px; background: var(--front-input, #fff); color: var(--front-muted, #4b5563); font-size: 12px; }
    .dashboard-switch-control i { color: var(--front-blue, #1677ff); font-size: 16px; }
    .dashboard-switch-control select { flex: 1; height: 32px; border: 0; background: transparent; color: inherit; outline: 0; cursor: pointer; }
    .dashboard-current-label { flex: 0 0 auto; padding: 2px 7px; border-radius: 4px; color: var(--front-blue, #1677ff); background: rgba(22, 119, 255, .08); font-weight: 700; }
    .dashboard-downloads { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
    .dashboard-downloads .layui-btn { margin: 0; }
    .dashboard-metric-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 12px; }
    .dashboard-metric-card .layui-card-body { min-height: 78px; padding: 14px; }
    .dashboard-metric-label { margin-bottom: 8px; color: var(--front-muted, #6b7280); font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .dashboard-value { min-height: 30px; font-size: 22px; line-height: 30px; font-weight: 800; font-variant-numeric: tabular-nums; }
    .dashboard-value.blue { color: var(--front-blue, #2080f0); }
    .dashboard-value.green { color: var(--front-accent, #18a058); }
    .dashboard-value.orange { color: var(--front-warn, #d97706); }
    .dashboard-value.red { color: var(--front-danger, #d03050); }
    .dashboard-value.cyan { color: var(--front-cyan, #0e7a83); }
    .dashboard-section-grid { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(320px, .75fr); gap: 14px; }
    .dashboard-chart-grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 14px; align-items: stretch; }
    .dashboard-chart-card { grid-column: span 6; }
    .dashboard-chart-card.is-funds { grid-column: span 8; }
    .dashboard-chart-card.is-network { grid-column: span 4; }
    .dashboard-chart-card.is-orders { grid-column: span 5; }
    .dashboard-chart-card.is-commission { grid-column: span 7; }
    .dashboard-chart { width: 100%; height: 282px; }
    .dashboard-chart-card.is-funds .dashboard-chart { height: 330px; }
    .dashboard-chart-card .layui-card-body { min-height: 314px; }
    .dashboard-chart-card.is-funds .layui-card-body { min-height: 362px; }
    .dashboard-share-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .dashboard-share-item { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px; align-items: center; padding: 10px; border: 1px solid var(--front-line, #edf0f3); border-radius: 8px; background: var(--front-table-head, #f8fafc); }
    .dashboard-share-label { margin-bottom: 5px; color: var(--front-strong, #1f2937); font-weight: 700; }
    .dashboard-share-url { color: var(--front-blue, #1677ff); word-break: break-all; }
    .dashboard-news-link { color: var(--front-text, #1f2937); cursor: pointer; }
    .dashboard-news-link:hover { color: var(--front-blue, #1677ff); text-decoration: underline; }
    @media screen and (max-width: 1180px) { .dashboard-metric-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } .dashboard-chart-card, .dashboard-chart-card.is-funds, .dashboard-chart-card.is-network, .dashboard-chart-card.is-orders, .dashboard-chart-card.is-commission { grid-column: span 6; } }
    @media screen and (max-width: 900px) { .dashboard-hero, .dashboard-section-grid { grid-template-columns: 1fr; } .dashboard-chart-grid { grid-template-columns: 1fr; } .dashboard-chart-card, .dashboard-chart-card.is-funds, .dashboard-chart-card.is-network, .dashboard-chart-card.is-orders, .dashboard-chart-card.is-commission { grid-column: 1; } }
    @media screen and (max-width: 560px) { .dashboard-metric-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .dashboard-share-list { grid-template-columns: 1fr; } .dashboard-downloads { grid-template-columns: 1fr; } .dashboard-chart { height: 240px; } }
</style>
@endsection

@section('content')
<div class="dashboard-page">
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
            <div class="dashboard-hero-mini">
                <span class="dashboard-mini-pill"><span data-translate="front.direct_agents">{{ __('front.direct_agents') }}</span>: <b id="directAgentsCount">0</b></span>
                <span class="dashboard-mini-pill"><span data-translate="front.indirect_agents">{{ __('front.indirect_agents') }}</span>: <b id="indirectAgentsCount">0</b></span>
                <span class="dashboard-mini-pill"><span data-translate="front.direct_customers">{{ __('front.direct_customers') }}</span>: <b id="directCustomersCount">0</b></span>
                <span class="dashboard-mini-pill"><span data-translate="front.indirect_customers">{{ __('front.indirect_customers') }}</span>: <b id="indirectCustomersCount">0</b></span>
            </div>
        </div>
        <div class="dashboard-control-panel">
            <div class="dashboard-actions">
                <label class="dashboard-switch-control">
                    <i class="layui-icon layui-icon-template-1"></i>
                    <select id="dashboardStyleSelect" aria-label="UI 风格">
                        <option value="layui">▣ Layui 风格</option>
                        <option value="naive">□ Naive 风格</option>
                    </select>
                </label>
                <label class="dashboard-switch-control">
                    <i class="layui-icon layui-icon-theme"></i>
                    <select id="dashboardThemeSelect" aria-label="皮肤">
                        <option value="light">☀ 浅色</option>
                        <option value="dark">☾ 深色</option>
                        <option value="sea">≋ 海蓝</option>
                        <option value="warm">◐ 暖色</option>
                        <option value="contrast">▣ 高对比</option>
                    </select>
                    <span id="dashboardThemeLabel" class="dashboard-current-label"></span>
                </label>
                <div class="dashboard-downloads">
                    <a id="pcDownloadLink" class="layui-btn layui-btn-primary layui-btn-sm" href="#" target="_blank" rel="noopener"><i class="layui-icon layui-icon-download-circle"></i> <span data-translate="front.pc_download">{{ __('front.pc_download') }}</span></a>
                    <a id="mobileDownloadLink" class="layui-btn layui-btn-primary layui-btn-sm" href="#" target="_blank" rel="noopener"><i class="layui-icon layui-icon-cellphone"></i> <span data-translate="front.mobile_download">{{ __('front.mobile_download') }}</span></a>
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

    <section class="dashboard-chart-grid">
        <div class="layui-card dashboard-chart-card is-funds"><div class="layui-card-header" data-translate="front.funds_chart">资金健康度</div><div class="layui-card-body"><div id="fundsChart" class="dashboard-chart"></div></div></div>
        <div class="layui-card dashboard-chart-card is-network"><div class="layui-card-header" data-translate="front.network_chart">客户 / 代理结构</div><div class="layui-card-body"><div id="networkChart" class="dashboard-chart"></div></div></div>
        <div class="layui-card dashboard-chart-card is-orders"><div class="layui-card-header" data-translate="front.order_chart">订单活跃度</div><div class="layui-card-body"><div id="orderChart" class="dashboard-chart"></div></div></div>
        <div class="layui-card dashboard-chart-card is-commission"><div class="layui-card-header" data-translate="front.commission_chart">返佣表现</div><div class="layui-card-body"><div id="commissionChart" class="dashboard-chart"></div></div></div>
    </section>

    <section class="dashboard-metric-grid">
        <div class="layui-card dashboard-metric-card"><div class="layui-card-body"><div class="dashboard-metric-label" data-translate="front.monthly_deposit">{{ __('front.monthly_deposit') }}</div><div class="dashboard-value green" id="monthlyDeposit">0.00</div></div></div>
        <div class="layui-card dashboard-metric-card"><div class="layui-card-body"><div class="dashboard-metric-label" data-translate="front.monthly_withdraw">{{ __('front.monthly_withdraw') }}</div><div class="dashboard-value red" id="monthlyWithdraw">0.00</div></div></div>
        <div class="layui-card dashboard-metric-card"><div class="layui-card-body"><div class="dashboard-metric-label" data-translate="front.monthly_open_orders">{{ __('front.monthly_open_orders') }}</div><div class="dashboard-value cyan" id="monthlyOpenOrders">0</div></div></div>
        <div class="layui-card dashboard-metric-card"><div class="layui-card-body"><div class="dashboard-metric-label" data-translate="front.monthly_closed_orders">{{ __('front.monthly_closed_orders') }}</div><div class="dashboard-value blue" id="monthlyClosedOrders">0</div></div></div>
        <div class="layui-card dashboard-metric-card"><div class="layui-card-body"><div class="dashboard-metric-label" data-translate="front.monthly_commission">{{ __('front.monthly_commission') }}</div><div class="dashboard-value orange" id="monthlyCommission">0.00</div></div></div>
        <div class="layui-card dashboard-metric-card"><div class="layui-card-body"><div class="dashboard-metric-label">Net</div><div class="dashboard-value green" id="monthlyNetFlow">0.00</div></div></div>
    </section>

    <section class="dashboard-section-grid">
        <div class="layui-card"><div class="layui-card-header" data-translate="front.share_url">{{ __('front.share_url') }}</div><div class="layui-card-body"><div class="dashboard-share-list" id="shareUrlList"></div></div></div>
        <div class="layui-card"><div class="layui-card-header" data-translate="front.news_list">{{ __('front.news_list') }}</div><div class="layui-card-body"><ul class="layui-timeline" id="dashboardNews"></ul></div></div>
    </section>
</div>
@endsection

@section('scripts')
<script src="{{ asset('/js/common/echarts.common.min.js') }}"></script>
<script src="{{ asset('/js/front/layui/dashboard/index.js') }}?v=2026052912"></script>
@endsection
