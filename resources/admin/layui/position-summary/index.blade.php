{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/28
Time: 01:09
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.position_summary'))

@section('content')
{{-- 持仓汇总页面：按代理/用户维度汇总 mt4_trades 交易数据，真实列表由 admin_api_positionSummaryList 按权限和数据范围返回。 --}}
<div class="layui-card crm-admin-workbench crm-admin-panel">
    <div class="layui-card-header">
        <div class="crm-page-title">{{ __('admin.position_summary') }}</div>
        <div class="crm-page-desc">{{ __('admin.position_summary_desc') }}</div>
    </div>
    <div class="layui-card-body">
        <div class="layui-btn-container">
            <button class="layui-btn layui-btn-primary" id="reloadPositionSummary">
                <span data-lucide="refresh-cw"></span>
                {{ __('common.refresh') }}
            </button>
            <button class="layui-btn" id="exportPositionSummary" data-permission="admin_position_summary_export">
                <span data-lucide="download"></span>
                {{ __('common.export') }}
            </button>
        </div>

        {{-- 旧后台代理钻取路径：searchtype 与 userPId 由 JS 写入，后端据此进入 subAgentsSearch 兼容分支并返回当前代理及直属下级代理汇总。 --}}
        <div class="layui-btn-container" id="positionSummaryPath" data-position-drilldown-root hidden>
            <button type="button" class="layui-btn layui-btn-primary layui-btn-sm" id="resetPositionSummaryDrilldown">
                <span data-lucide="corner-up-left"></span>
                {{ __('common.back') }}
            </button>
            <span class="layui-badge layui-bg-blue" data-position-drilldown-label>{{ __('admin.position_summary') }}</span>
        </div>

        {{-- 需求 12：链路默认不显示（.crm-chain-path 的 display:none）。
             只有点击表格里的用户ID 才通过 is-visible 展示，并且只展开到被点击的那一层；
             再点下一级用户ID 才继续向下展开一层，依次类推。链路节点只输出用户ID，
             不带用户名、也不带代理等级标签。 --}}
        <div class="crm-chain-path" id="positionSummaryChain" data-position-chain hidden>
            <span class="crm-chain-title" data-translate="admin.current_chain">{{ __('admin.current_chain') }}</span>
            <span class="crm-chain-nodes" data-position-chain-nodes></span>
            <button type="button" class="crm-chain-reset" data-position-chain-reset data-translate="admin.chain_reset">{{ __('admin.chain_reset') }}</button>
        </div>

        <form class="layui-form layui-form-pane" id="positionSummarySearchForm">
            {{-- searchtype/userPId：兼容旧项目 subAgentsListSearchV2 的核心入参；为空表示普通筛选，subAgentsSearch 表示按父代理钻取。 --}}
            <input type="hidden" name="searchtype" value="">
            <input type="hidden" name="userPId" value="">
            <div class="layui-form-item">
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- user_id：业务用户 ID，对应 user_infos.user_id；查询交易时由后端通过 user_infos.mt4_code 映射 mt4_trades.login。 --}}
                        <input type="text" name="user_id" autocomplete="off" class="layui-input" placeholder="{{ __('admin.user_id') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- user_name：业务用户名称，对应 user_infos.user_name，支持模糊查询。 --}}
                        <input type="text" name="user_name" autocomplete="off" class="layui-input" placeholder="{{ __('admin.user_name') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- parent_id：直属上级代理 ID，对应 user_infos.parent_id，用于查看某个代理的直属下级。 --}}
                        <input type="text" name="parent_id" autocomplete="off" class="layui-input" placeholder="{{ __('admin.parent_id') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- account_type：账户类型，1=代理，2=普通客户；为空时查询全部类型。 --}}
                        <select name="account_type">
                            <option value="">{{ __('admin.account_type') }}</option>
                            <option value="1">{{ __('admin.account_type_agent') }}</option>
                            <option value="2">{{ __('admin.account_type_customer') }}</option>
                        </select>
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- start_date：交易统计开始日期，后端按 mt4_trades.close_time 下限过滤。 --}}
                        <input type="date" name="start_date" autocomplete="off" class="layui-input" placeholder="{{ __('admin.start_date') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- end_date：交易统计结束日期，后端按 mt4_trades.close_time 上限过滤。 --}}
                        <input type="date" name="end_date" autocomplete="off" class="layui-input" placeholder="{{ __('admin.end_date') }}">
                    </div>
                </div>
                <div class="layui-inline">
                    <button class="layui-btn" lay-submit lay-filter="searchPositionSummary">
                        <span data-lucide="search"></span>
                        {{ __('common.search') }}
                    </button>
                    <button type="reset" class="layui-btn layui-btn-primary" id="resetPositionSummarySearch">
                        <span data-lucide="rotate-ccw"></span>
                        {{ __('common.reset') }}
                    </button>
                </div>
            </div>
        </form>

        <table class="layui-hide" id="positionSummaryTable" lay-filter="positionSummaryTable"></table>
    </div>
</div>

{{-- 需求 9：汇总统计独立成块，放到表格卡片之外，默认靠左对齐，与表格形成视觉区分。
     数值仍由 positionSummaryList 返回的 summary 填充，data-summary-field 键名保持不变。 --}}
<section class="crm-admin-stats-block" id="positionSummaryCards" aria-labelledby="positionSummaryStatsTitle">
    <div class="crm-admin-stats-heading">
        <span class="crm-admin-stats-title" id="positionSummaryStatsTitle" data-translate="admin.table_statistics">{{ __('admin.table_statistics') }}</span>
        <span class="crm-admin-stats-desc" data-translate="admin.table_statistics_desc">{{ __('admin.table_statistics_desc') }}</span>
    </div>
    <div class="crm-table-summary">
        <div class="crm-table-summary-item"><span data-translate="admin.total_accounts">{{ __('admin.total_accounts') }}</span><strong data-summary-field="total_accounts">0</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.total_orders">{{ __('admin.total_orders') }}</span><strong data-summary-field="total_orders">0</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.total_volume">{{ __('admin.total_volume') }}</span><strong data-summary-field="total_volume">0.00</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.total_profit">{{ __('admin.total_profit') }}</span><strong data-summary-field="total_profit">0.00</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.total_trade_commission">{{ __('admin.total_trade_commission') }}</span><strong data-summary-field="total_comm">0.00</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.total_swaps">{{ __('admin.total_swaps') }}</span><strong data-summary-field="total_swaps">0.00</strong></div>
        {{-- MT4 快照汇总：这些字段来自 user_infos.mt4_code = mt4_users.login 的真实账户快照，只累计当前筛选命中的展示行。 --}}
        <div class="crm-table-summary-item"><span data-translate="admin.total_mt4_accounts">{{ __('admin.total_mt4_accounts') }}</span><strong data-summary-field="total_mt4_accounts">0</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.total_balance">{{ __('admin.total_balance') }}</span><strong data-summary-field="total_balance">0.00</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.total_equity">{{ __('admin.total_equity') }}</span><strong data-summary-field="total_equity">0.00</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.total_margin">{{ __('admin.total_margin') }}</span><strong data-summary-field="total_margin">0.00</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.total_margin_free">{{ __('admin.total_margin_free') }}</span><strong data-summary-field="total_margin_free">0.00</strong></div>
    </div>
</section>
@endsection

@section('scripts')
{{-- 行级下钻标记：交易明细与风险联动共用当前业务 user_id 和日期筛选，目标页再按各自职责解释参数。 --}}
<div hidden
     data-layui-page="position-summary/index"
     data-position-trade-detail-root
     data-position-risk-detail-root></div>
@endsection
