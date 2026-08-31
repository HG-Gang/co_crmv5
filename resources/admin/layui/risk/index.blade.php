{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/28
Time: 01:14
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.risk_control'))

@section('content')
@php
    $allowedRiskModes = ['profit', 'positions', 'marginCalls', 'ipRisk'];
    $requestedRiskMode = $defaultRiskMode ?? request()->query('mode', 'positions');
    $resolvedRiskMode = is_string($requestedRiskMode) && in_array($requestedRiskMode, $allowedRiskModes, true)
        ? $requestedRiskMode
        : 'positions';
    $fixedRiskMode = isset($defaultRiskMode) && in_array($resolvedRiskMode, ['profit', 'positions', 'ipRisk'], true)
        ? $resolvedRiskMode
        : null;
    $riskModeButtons = [
        'positions' => ['icon' => 'shield-alert', 'label' => __('admin.risk_positions')],
        'profit' => ['icon' => 'trending-up', 'label' => __('admin.total_profit')],
        'marginCalls' => ['icon' => 'gauge', 'label' => __('admin.margin_calls')],
        'ipRisk' => ['icon' => 'network', 'label' => __('admin.risk_ip')],
    ];
@endphp
{{-- 风控管理页面：Blade 只渲染筛选表单、汇总卡片和 Layui 表格容器，真实数据由后台权限 API 返回。 --}}
<div class="layui-card crm-admin-workbench crm-admin-panel">
    <div class="layui-card-header">
        <div class="crm-page-title">{{ __('admin.risk_control') }}</div>
        <div class="crm-page-desc">{{ __('admin.risk_control_desc') }}</div>
    </div>
    <div class="layui-card-body">
        <form class="layui-form layui-form-pane" id="riskSearchForm">
            <div class="layui-form-item">
                <div class="layui-inline">
                    <label class="crm-sr-only" for="risk-filter-user-id">{{ __('admin.user_id') }}</label>
                    <div class="layui-input-inline">
                        {{-- user_id：业务用户 ID；持仓风险由后端通过 user_infos.mt4_code 映射真实 mt4_trades.login。 --}}
                        <input id="risk-filter-user-id" type="text" name="user_id" autocomplete="off" class="layui-input" aria-label="{{ __('admin.user_id') }}" placeholder="{{ __('admin.user_id') }}">
                    </div>
                </div>
                <div class="layui-inline">
                    <label class="crm-sr-only" for="risk-filter-user-name">{{ __('admin.user_name') }}</label>
                    <div class="layui-input-inline">
                        <input id="risk-filter-user-name" type="text" name="user_name" autocomplete="off" class="layui-input" aria-label="{{ __('admin.user_name') }}" placeholder="{{ __('admin.user_name') }}">
                    </div>
                </div>
                <div class="layui-inline">
                    <label class="crm-sr-only" for="risk-filter-ticket">{{ __('admin.ticket') }}</label>
                    <div class="layui-input-inline">
                        {{-- ticket：MT4 订单号，只用于当前持仓风险列表筛选。 --}}
                        <input id="risk-filter-ticket" type="text" name="ticket" autocomplete="off" class="layui-input" aria-label="{{ __('admin.ticket') }}" placeholder="{{ __('admin.ticket') }}">
                    </div>
                </div>
                <div class="layui-inline">
                    <label class="crm-sr-only" for="risk-filter-symbol">{{ __('admin.symbol') }}</label>
                    <div class="layui-input-inline">
                        {{-- symbol：交易品种，只用于当前持仓风险列表筛选。 --}}
                        <input id="risk-filter-symbol" type="text" name="symbol" autocomplete="off" class="layui-input" aria-label="{{ __('admin.symbol') }}" placeholder="{{ __('admin.symbol') }}">
                    </div>
                </div>
                <div class="layui-inline">
                    <label class="crm-sr-only" for="risk-filter-order-type">{{ __('admin.order_type') }}</label>
                    <div class="layui-input-inline">
                        <select id="risk-filter-order-type" name="order_type" aria-label="{{ __('admin.order_type') }}">
                            <option value="">{{ __('admin.all_order_types') }}</option>
                            <option value="real_disk">{{ __('admin.real_disk') }}</option>
                            <option value="test_disk">{{ __('admin.test_disk') }}</option>
                        </select>
                    </div>
                </div>
                <div class="layui-inline">
                    <label class="crm-sr-only" for="risk-filter-max-margin-level">{{ __('admin.max_margin_level') }}</label>
                    <div class="layui-input-inline">
                        {{-- max_margin_level：追保预警阈值，表示允许展示的最高保证金比例。 --}}
                        <input id="risk-filter-max-margin-level" type="number" name="max_margin_level" value="100" autocomplete="off" class="layui-input" aria-label="{{ __('admin.max_margin_level') }}" placeholder="{{ __('admin.max_margin_level') }}">
                    </div>
                </div>
                <div class="layui-inline">
                    <label class="crm-sr-only" for="risk-filter-login-ip">{{ __('admin.login_ip') }}</label>
                    <div class="layui-input-inline">
                        {{-- login_ip：登录 IP，用于异常 IP 风控列表模糊筛选。 --}}
                        <input id="risk-filter-login-ip" type="text" name="login_ip" autocomplete="off" class="layui-input" aria-label="{{ __('admin.login_ip') }}" placeholder="{{ __('admin.login_ip') }}">
                    </div>
                </div>
                <div class="layui-inline">
                    <label class="crm-sr-only" for="risk-filter-min-user-count">{{ __('admin.min_user_count') }}</label>
                    <div class="layui-input-inline">
                        {{-- min_user_count：同一 IP 至少关联多少个不同用户才判定为异常，默认 2。 --}}
                        <input id="risk-filter-min-user-count" type="number" name="min_user_count" value="2" autocomplete="off" class="layui-input" aria-label="{{ __('admin.min_user_count') }}" placeholder="{{ __('admin.min_user_count') }}">
                    </div>
                </div>
                <div class="layui-inline">
                    <label class="crm-sr-only" for="risk-filter-start-date">{{ __('admin.start_date') }}</label>
                    <div class="layui-input-inline">
                        {{-- start_date：风险持仓按开仓时间、异常 IP 按登录时间应用同一个查询下限。 --}}
                        <input id="risk-filter-start-date" type="date" name="start_date" autocomplete="off" class="layui-input" aria-label="{{ __('admin.start_date') }}" placeholder="{{ __('admin.start_date') }}">
                    </div>
                </div>
                <div class="layui-inline">
                    <label class="crm-sr-only" for="risk-filter-end-date">{{ __('admin.end_date') }}</label>
                    <div class="layui-input-inline">
                        {{-- end_date：风险持仓按开仓时间、异常 IP 按登录时间应用同一个查询上限。 --}}
                        <input id="risk-filter-end-date" type="date" name="end_date" autocomplete="off" class="layui-input" aria-label="{{ __('admin.end_date') }}" placeholder="{{ __('admin.end_date') }}">
                    </div>
                </div>
                <div class="layui-inline">
                    <button class="layui-btn" lay-submit lay-filter="searchRisk">
                        <span data-lucide="search"></span>
                        {{ __('common.search') }}
                    </button>
                    <button type="reset" class="layui-btn layui-btn-primary" id="resetRiskSearch">
                        <span data-lucide="rotate-ccw"></span>
                        {{ __('common.reset') }}
                    </button>
                </div>
            </div>
        </form>

        <div class="layui-btn-container">
            <button class="layui-btn" id="reloadRisk">
                <span data-lucide="refresh-cw"></span>
                {{ __('common.refresh') }}
            </button>
            <span role="tablist" aria-label="{{ __('admin.risk_control') }}">
                @foreach($riskModeButtons as $mode => $button)
                    @if($fixedRiskMode === null || $fixedRiskMode === $mode)
                        <button type="button"
                                id="risk-tab-{{ $mode }}"
                                class="layui-btn layui-btn-primary risk-mode"
                                data-mode="{{ $mode }}"
                                role="tab"
                                aria-controls="risk-panel-{{ $mode }}"
                                aria-selected="{{ $resolvedRiskMode === $mode ? 'true' : 'false' }}">
                            <span data-lucide="{{ $button['icon'] }}"></span>
                            {{ $button['label'] }}
                        </button>
                    @endif
                @endforeach
            </span>
        </div>

        {{-- 盈利风险真实查询由后续只读 API 接入；当前保持稳定空表，禁止回退到持仓接口。 --}}
        <div id="risk-panel-profit" role="tabpanel" aria-labelledby="risk-tab-profit">
            <table class="layui-hide" id="profitRiskTable" lay-filter="profitRiskTable"></table>
        </div>

        {{-- 当前持仓风险表：显示 mt4_trades 未平仓订单和扣除手续费后的 risk_value。 --}}
        <div id="risk-panel-positions" role="tabpanel" aria-labelledby="risk-tab-positions">
            <table class="layui-hide" id="riskTable" lay-filter="riskTable"></table>
        </div>

        {{-- 追保预警表：显示 mt4_users 资金快照中保证金比例低于阈值的用户。 --}}
        <div id="risk-panel-marginCalls" role="tabpanel" aria-labelledby="risk-tab-marginCalls">
            <table class="layui-hide" id="marginCallTable" lay-filter="marginCallTable"></table>
        </div>

        {{-- 异常 IP 表：显示 user_login_logs 中同一 IP 登录多个业务账号的风险聚合结果。 --}}
        <div id="risk-panel-ipRisk" role="tabpanel" aria-labelledby="risk-tab-ipRisk">
            <table class="layui-hide" id="riskIpTable" lay-filter="riskIpTable"></table>
        </div>

        {{-- 异常 IP 详情弹层：列表行点击详情后，JS 使用 login_ip 请求 riskIpDetail 接口并渲染该 IP 下的用户明细。 --}}
        <div id="riskIpDetailDialog" style="display:none;padding:16px;">
            <table class="layui-hide" id="riskIpDetailTable" lay-filter="riskIpDetailTable"></table>
        </div>

        <script type="text/html" id="riskActions">
            @verbatim
            {{# if (d.force_close_id) { }}
            @endverbatim
                <button type="button" class="layui-btn layui-btn-danger risk-action-button" lay-event="forceClose" data-permission="admin_risk_force_close">
                    <span data-lucide="octagon-x"></span>
                    {{ __('admin.force_close') }}
                </button>
            @verbatim
            {{# } }}
            @endverbatim
        </script>

        <script type="text/html" id="riskIpActions">
            <button type="button" class="layui-btn layui-btn-normal risk-action-button" lay-event="ipDetail" data-permission="admin_risk_ip_detail">
                <span data-lucide="search"></span>
                {{ __('common.detail') }}
            </button>
        </script>
    </div>
</div>

{{-- 需求 9：统计独立成块，放到表格卡片之外，默认靠左对齐。
     positions 与 marginCalls 接口都会返回 summary，JS 按当前视图刷新这些数值。 --}}
<section class="crm-admin-stats-block" id="riskSummaryCards" aria-labelledby="riskStatsTitle">
    <div class="crm-admin-stats-heading">
        <span class="crm-admin-stats-title" id="riskStatsTitle" data-translate="admin.table_statistics">{{ __('admin.table_statistics') }}</span>
        <span class="crm-admin-stats-desc" data-translate="admin.table_statistics_desc">{{ __('admin.table_statistics_desc') }}</span>
    </div>
    <div class="crm-table-summary">
        <div class="crm-table-summary-item"><span data-translate="admin.total_records">{{ __('admin.total_records') }}</span><strong data-summary-field="total_records">0</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.total_profit">{{ __('admin.total_profit') }}</span><strong data-summary-field="total_profit">0.00</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.total_volume">{{ __('admin.total_volume') }}</span><strong data-summary-field="total_volume">0.00</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.total_risk_value">{{ __('admin.total_risk_value') }}</span><strong data-summary-field="total_risk_value">0.00</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.total_margin">{{ __('admin.total_margin') }}</span><strong data-summary-field="total_margin">0.00</strong></div>
    </div>
</section>
@endsection

@section('scripts')
{{-- 下钻默认值只作为首屏筛选输入，JS 会校验 mode 并把业务 user_id 交给后端执行 MT4 账号映射。 --}}
<div hidden
     data-layui-page="risk/index"
     data-default-risk-user-id="{{ request()->query('user_id', '') }}"
     data-default-risk-start-date="{{ request()->query('start_date', '') }}"
     data-default-risk-end-date="{{ request()->query('end_date', '') }}"
     data-default-risk-mode="{{ $resolvedRiskMode }}"
     data-fixed-risk-mode="{{ $fixedRiskMode ?? '' }}"></div>
@endsection
