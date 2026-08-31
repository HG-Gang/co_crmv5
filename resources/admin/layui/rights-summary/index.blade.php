{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/28
Time: 01:14
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.rights_summary'))

@section('content')
{{-- 权益汇总页面：只渲染筛选表单、汇总卡片和 Layui 表格，真实数据由 admin_api_rightsSummaryList 按权限和数据范围返回。 --}}
<div class="layui-card crm-admin-workbench crm-admin-panel">
    <div class="layui-card-header">{{ __('admin.rights_summary') }}</div>
    <div class="layui-card-body">
        <div class="layui-btn-container">
            <button class="layui-btn layui-btn-primary" id="reloadRightsSummary">{{ __('common.refresh') }}</button>
            <button class="layui-btn" id="exportRightsSummary" data-permission="admin_rights_summary_export">{{ __('common.export') }}</button>
        </div>

        <form class="layui-form layui-form-pane" id="rightsSummarySearchForm">
            <div class="layui-form-item">
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- user_id：业务用户 ID，对应 user_infos.user_id，用于按 CRM 用户编号筛选权益数据。 --}}
                        <input type="text" name="user_id" autocomplete="off" class="layui-input" placeholder="{{ __('admin.user_id') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- login：MT4 登录账号，对应 mt4_users.login，用于定位交易账户资金快照。 --}}
                        <input type="text" name="login" autocomplete="off" class="layui-input" placeholder="{{ __('admin.mt4_login') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- user_name：业务用户名，对应 user_infos.user_name，用于模糊搜索客户或代理。 --}}
                        <input type="text" name="user_name" autocomplete="off" class="layui-input" placeholder="{{ __('admin.user_name') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- mt4_group：MT4 分组，对应 mt4_users.group，用于按交易组筛选权益数据。 --}}
                        <input type="text" name="mt4_group" autocomplete="off" class="layui-input" placeholder="{{ __('admin.mt4_group') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- min_equity：最小净值，对应 mt4_users.equity 下限。 --}}
                        <input type="number" step="0.01" name="min_equity" autocomplete="off" class="layui-input" placeholder="{{ __('admin.min_equity') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- max_equity：最大净值，对应 mt4_users.equity 上限。 --}}
                        <input type="number" step="0.01" name="max_equity" autocomplete="off" class="layui-input" placeholder="{{ __('admin.max_equity') }}">
                    </div>
                </div>
                <div class="layui-inline">
                    <button class="layui-btn" lay-submit lay-filter="searchRightsSummary">{{ __('common.search') }}</button>
                    <button type="reset" class="layui-btn layui-btn-primary">{{ __('common.reset') }}</button>
                </div>
            </div>
        </form>

        <table class="layui-hide" id="rightsSummaryTable" lay-filter="rightsSummaryTable"></table>

        <script type="text/html" id="rightsSummaryActions">
            {{-- manualConfirmRightsSettlement：只对 rights_settlements.status=0 的待处理权益结算记录做人工确认，不触发 MT4 自动入出金。 --}}
            <a class="layui-btn layui-btn-xs layui-btn-warm" lay-event="manualConfirmRightsSettlement" data-permission="admin_rights_summary_manual_confirm">{{ __('admin.manual_confirm') }}</a>
        </script>
    </div>
</div>

{{-- 需求 9：统计独立成块，放到表格卡片之外，默认靠左对齐。
     数值由 admin_api_rightsSummaryList 返回的 summary 填充，键名保持不变。 --}}
<section class="crm-admin-stats-block" id="rightsSummaryCards" aria-labelledby="rightsSummaryStatsTitle">
    <div class="crm-admin-stats-heading">
        <span class="crm-admin-stats-title" id="rightsSummaryStatsTitle" data-translate="admin.table_statistics">{{ __('admin.table_statistics') }}</span>
        <span class="crm-admin-stats-desc" data-translate="admin.table_statistics_desc">{{ __('admin.table_statistics_desc') }}</span>
    </div>
    <div class="crm-table-summary">
        <div class="crm-table-summary-item"><span data-translate="admin.total_accounts">{{ __('admin.total_accounts') }}</span><strong data-summary-field="total_accounts">0</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.total_balance">{{ __('admin.total_balance') }}</span><strong data-summary-field="total_balance">0.00</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.total_equity">{{ __('admin.total_equity') }}</span><strong data-summary-field="total_equity">0.00</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.total_margin">{{ __('admin.total_margin') }}</span><strong data-summary-field="total_margin">0.00</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.total_margin_free">{{ __('admin.total_margin_free') }}</span><strong data-summary-field="total_margin_free">0.00</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.online_settlement_deposit_amount">{{ __('admin.online_settlement_deposit_amount') }}</span><strong data-summary-field="online_settlement_deposit_amount">0.00</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.online_settlement_withdraw_amount">{{ __('admin.online_settlement_withdraw_amount') }}</span><strong data-summary-field="online_settlement_withdraw_amount">0.00</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.online_settlement_commission_amount">{{ __('admin.online_settlement_commission_amount') }}</span><strong data-summary-field="online_settlement_commission_amount">0.00</strong></div>
        {{-- online_settlement_net_amount：已支付入金 - 已完成出金 + 已结算返佣，用于快速核对当前筛选范围的线上净结算额。 --}}
        <div class="crm-table-summary-item"><span data-translate="admin.online_settlement_net_amount">{{ __('admin.online_settlement_net_amount') }}</span><strong data-summary-field="online_settlement_net_amount">0.00</strong></div>
    </div>
</section>

<div id="rightsManualConfirmModal" class="admin-dialog-body" style="display: none;">
    {{-- 手动确认表单：settlement_id 为 rights_settlements.id；manual_confirm_reason 为财务人工确认原因，写入 remark 供审计。 --}}
    <form class="layui-form" id="rightsManualConfirmForm" lay-filter="rightsManualConfirmForm">
        <input type="hidden" name="settlement_id">
        <div class="layui-form-item layui-form-text">
            <label class="layui-form-label">{{ __('admin.manual_confirm_reason') }}</label>
            <div class="layui-input-block">
                <textarea name="manual_confirm_reason" required lay-verify="required" class="layui-textarea"></textarea>
            </div>
        </div>
        <div class="layui-form-item">
            <div class="layui-input-block">
                <button class="layui-btn" lay-submit lay-filter="submitRightsManualConfirm">{{ __('admin.manual_confirm') }}</button>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="rights-summary/index"></div>
@endsection
