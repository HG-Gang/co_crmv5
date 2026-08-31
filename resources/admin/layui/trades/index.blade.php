{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/28
Time: 01:13
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.trades'))

@section('content')
{{-- 交易订单：mode 控制列表接口，all=全部订单，open=当前持仓，closed=历史平仓。 --}}
<div class="layui-card crm-admin-panel">
    <div class="layui-card-header">{{ __('admin.trades') }}</div>
    <div class="layui-card-body">
        <form class="layui-form layui-form-pane" id="tradeSearchForm">
            <div class="layui-form-item">
                {{-- 用户 ID：对应 mt4_trades.login，同时后端兼容旧项目 userId 参数。 --}}
                <div class="layui-inline"><div class="layui-input-inline">
                        <input type="text" name="user_id" autocomplete="off" class="layui-input" placeholder="{{ __('admin.user_id') }}">
                    </div>
                </div>
                {{-- 订单号：对应 mt4_trades.ticket，同时后端兼容旧项目 orderId 参数。 --}}
                <div class="layui-inline"><div class="layui-input-inline">
                        <input type="text" name="ticket" autocomplete="off" class="layui-input" placeholder="{{ __('admin.ticket') }}">
                    </div>
                </div>
                {{-- 交易品种：对应 mt4_trades.symbol，同时后端兼容旧项目 sym_symbol 参数。 --}}
                <div class="layui-inline"><div class="layui-input-inline">
                        <input type="text" name="symbol" autocomplete="off" class="layui-input" placeholder="{{ __('admin.symbol') }}">
                    </div>
                </div>
                {{-- 日期范围：全部/持仓按 open_time，历史平仓按 close_time，后端同时兼容旧项目 startdate/enddate。 --}}
                <div class="layui-inline"><div class="layui-input-inline">
                        <input type="text" name="start_date" id="tradeStartDate" autocomplete="off" class="layui-input" placeholder="{{ __('admin.start_date') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        <input type="text" name="end_date" id="tradeEndDate" autocomplete="off" class="layui-input" placeholder="{{ __('admin.end_date') }}">
                    </div>
                </div>
                {{-- 强平筛选：仅历史平仓接口生效，Yes 表示 COMMENT 以 so 开头，No 表示排除该类订单。 --}}
                <div class="layui-inline"><div class="layui-input-inline">
                        <select name="is_coercion">
                            <option value="">{{ __('admin.all_force_close_status') }}</option>
                            <option value="Yes">{{ __('admin.force_close_order') }}</option>
                            <option value="No">{{ __('admin.non_force_close_order') }}</option>
                        </select>
                    </div>
                </div>
                {{-- 盘型筛选：按 user_infos.mt4_group 是否以 -TEST/-TEST-P 结尾区分旧项目真实盘与测试盘。 --}}
                <div class="layui-inline"><div class="layui-input-inline">
                        <select name="orderType">
                            <option value="">{{ __('admin.all_order_types') }}</option>
                            <option value="real_disk">{{ __('admin.real_disk') }}</option>
                            <option value="test_disk">{{ __('admin.test_disk') }}</option>
                        </select>
                    </div>
                </div>
                <div class="layui-inline">
                    <button class="layui-btn" lay-submit lay-filter="searchTrades"><span data-lucide="search" aria-hidden="true"></span>{{ __('common.search') }}</button>
                    <button type="reset" class="layui-btn layui-btn-primary"><span data-lucide="rotate-ccw" aria-hidden="true"></span>{{ __('common.reset') }}</button>
                    <button type="button" class="layui-btn layui-btn-primary trade-mode" data-mode="all"><span data-lucide="list" aria-hidden="true"></span>{{ __('admin.all_trades') }}</button>
                    <button type="button" class="layui-btn layui-btn-primary trade-mode" data-mode="open"><span data-lucide="activity" aria-hidden="true"></span>{{ __('admin.open_positions') }}</button>
                    <button type="button" class="layui-btn layui-btn-primary trade-mode" data-mode="closed"><span data-lucide="archive" aria-hidden="true"></span>{{ __('admin.closed_positions') }}</button>
                    {{-- 导出历史平仓：只导出当前筛选条件命中的 closedPositions 结果，权限由 admin_closed_positions_export 控制。 --}}
                    <button type="button" class="layui-btn layui-btn-primary" id="exportClosedPositions" data-permission="admin_closed_positions_export"><span data-lucide="download" aria-hidden="true"></span>{{ __('common.export') }}</button>
                </div>
            </div>
        </form>
        <table class="layui-hide" id="tradeTable" lay-filter="tradeTable"></table>
    </div>
</div>

{{-- 需求 9：统计独立成块，放到表格卡片之外，默认靠左对齐。
     数值仍由 /api/admin/tradeList、/api/admin/openPositions、/api/admin/closedPositions 返回的 summary 填充。 --}}
<section class="crm-admin-stats-block" id="tradeSummaryCards" aria-labelledby="tradeStatsTitle">
    <div class="crm-admin-stats-heading">
        <span class="crm-admin-stats-title" id="tradeStatsTitle" data-translate="admin.table_statistics">{{ __('admin.table_statistics') }}</span>
        <span class="crm-admin-stats-desc" data-translate="admin.table_statistics_desc">{{ __('admin.table_statistics_desc') }}</span>
    </div>
    <div class="crm-table-summary">
        <div class="crm-table-summary-item"><span data-translate="admin.total_orders">{{ __('admin.total_orders') }}</span><strong data-summary-field="total_orders">0</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.total_volume">{{ __('admin.total_volume') }}</span><strong data-summary-field="total_volume">0.00</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.total_profit">{{ __('admin.total_profit') }}</span><strong data-summary-field="total_profit">0.00</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.total_swaps">{{ __('admin.total_swaps') }}</span><strong data-summary-field="total_swaps">0.00</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.total_trade_commission">{{ __('admin.total_trade_commission') }}</span><strong data-summary-field="total_commission">0.00</strong></div>
    </div>
</section>
@endsection

@section('scripts')
{{-- URL 默认筛选：从持仓汇总行跳入交易订单页时，先把查询参数落到页面标记，再由 Layui 脚本写入筛选表单。 --}}
<div hidden
     data-layui-page="trades/index"
     data-default-trade-user-id="{{ request('user_id') }}"
     data-default-trade-start-date="{{ request('start_date') }}"
     data-default-trade-end-date="{{ request('end_date') }}"
     data-default-trade-mode="{{ request('mode', 'all') }}"></div>
@endsection
