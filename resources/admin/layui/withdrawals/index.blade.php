{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/28
Time: 01:16
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.withdraw'))

@section('content')
@php
    $defaultStatus = (string) ($defaultStatus ?? '');
@endphp
{{-- 出金管理页面：列表读取 admin_api_withdrawList，处理按钮分别调用 admin_api_withdrawProcess、admin_api_withdrawComplete、admin_api_withdrawReject，真实状态流转必须经过后端权限与数据范围校验。 --}}
<div class="layui-card crm-admin-panel" data-visual-c-reference="admin-withdrawals">
    <div class="layui-card-header">{{ __('admin.withdraw') }}</div>
    <div class="layui-card-body">
        <form class="layui-form layui-form-pane" id="withdrawSearchForm">
            <div class="layui-form-item">
                <div class="layui-inline">
                    <div class="layui-input-inline">
                        <label class="crm-sr-only" for="withdrawLocalOrderNo">{{ __('admin.local_order_no') }}</label>
                        <input type="text" id="withdrawLocalOrderNo" name="local_order_no" autocomplete="off" class="layui-input" placeholder="{{ __('admin.local_order_no') }}" aria-label="{{ __('admin.local_order_no') }}">
                    </div>
                </div>
                <div class="layui-inline">
                    <div class="layui-input-inline">
                        <label class="crm-sr-only" for="withdrawMt4Ticket">{{ __('admin.mt4_ticket') }}</label>
                        <input type="text" id="withdrawMt4Ticket" name="mt4_ticket" autocomplete="off" class="layui-input" placeholder="{{ __('admin.mt4_ticket') }}" aria-label="{{ __('admin.mt4_ticket') }}">
                    </div>
                </div>
                <div class="layui-inline">
                    <div class="layui-input-inline">
                        <label class="crm-sr-only" for="withdrawUserId">{{ __('admin.user_id') }}</label>
                        <input type="number" id="withdrawUserId" name="user_id" min="1" step="1" autocomplete="off" class="layui-input" placeholder="{{ __('admin.user_id') }}" aria-label="{{ __('admin.user_id') }}">
                    </div>
                </div>
                @if($defaultStatus !== '')
                    <input type="hidden" name="status" value="{{ $defaultStatus }}">
                @else
                    <div class="layui-inline">
                        <div class="layui-input-inline">
                            <label class="crm-sr-only" for="withdrawStatus">{{ __('admin.status') }}</label>
                            <select id="withdrawStatus" name="status" aria-label="{{ __('admin.status') }}">
                                <option value="">{{ __('common.all') }}</option>
                                <option value="0">{{ __('admin.pending') }}</option>
                                <option value="1">{{ __('admin.processing') }}</option>
                                <option value="2">{{ __('admin.completed') }}</option>
                                <option value="3">{{ __('admin.rejected') }}</option>
                            </select>
                        </div>
                    </div>
                @endif
                <div class="layui-inline">
                    <div class="layui-input-inline">
                        <label class="crm-sr-only" for="withdrawStartDate">{{ __('admin.start_date') }}</label>
                        <input type="text" id="withdrawStartDate" name="start_date" autocomplete="off" class="layui-input" placeholder="{{ __('admin.start_date') }}" aria-label="{{ __('admin.start_date') }}">
                    </div>
                </div>
                <div class="layui-inline">
                    <div class="layui-input-inline">
                        <label class="crm-sr-only" for="withdrawEndDate">{{ __('admin.end_date') }}</label>
                        <input type="text" id="withdrawEndDate" name="end_date" autocomplete="off" class="layui-input" placeholder="{{ __('admin.end_date') }}" aria-label="{{ __('admin.end_date') }}">
                    </div>
                </div>
            </div>
            <div class="layui-form-item crm-form-actions">
                <button class="layui-btn" lay-submit lay-filter="searchWithdraws">
                    <i data-lucide="search" aria-hidden="true"></i>{{ __('common.search') }}
                </button>
                <button type="reset" class="layui-btn layui-btn-primary">
                    <i data-lucide="refresh-cw" aria-hidden="true"></i>{{ __('common.reset') }}
                </button>
                <button type="button" class="layui-btn layui-btn-normal" id="exportWithdrawals" data-permission="admin_withdraw_export">
                    <i data-lucide="file-down" aria-hidden="true"></i>{{ __('common.export') }}
                </button>
                {{-- 批量审核入口：对齐旧四个出金状态页各自的「批量操作」按钮。
                     旧后台每个状态页都有一份，新后台合并成单页 + status 筛选，因此只保留一个入口。
                     权限沿用 admin_withdraw_process：后端按 payload.status 动态改判为
                     admin_api_withdrawProcess/Complete/Reject，三种目标状态各自再校验一次，
                     因此按钮上标注的 slug 只用于隐藏无处理权限的管理员，不是最终安全边界。 --}}
                <button type="button" class="layui-btn layui-btn-warm" id="batchWithdrawButton" data-permission="admin_withdraw_process">
                    <i data-lucide="list-checks" aria-hidden="true"></i>{{ __('admin.batch_operation') }}
                </button>
            </div>
        </form>

        <table class="layui-hide" id="withdrawTable" lay-filter="withdrawTable"></table>

        <script type="text/html" id="withdrawActions">
            <a class="layui-btn layui-btn-primary layui-btn-xs" lay-event="detail">{{ __('common.view') }}</a>
            <a class="layui-btn layui-btn-xs" lay-event="process" data-permission="admin_withdraw_process">{{ __('admin.process') }}</a>
            <a class="layui-btn layui-btn-normal layui-btn-xs" lay-event="complete" data-permission="admin_withdraw_complete">{{ __('admin.complete') }}</a>
            <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="reject" data-permission="admin_withdraw_reject">{{ __('admin.reject') }}</a>
        </script>
    </div>
</div>

{{-- 批量审核弹窗骨架：默认 hidden，由 layer.open({type:1}) 引用后显示。
     目标状态取值与 withdraw_records.status 一致（1=处理中、2=已完成、3=已拒绝）；
     旧逻辑的跃迁约束由 JS 在打开弹窗时按来源状态禁用非法项，后端仍会独立校验一次。
     备注在目标状态为 3（拒绝）时必填，与单条拒绝的 reason 走同一后端字段与 500 字上限。 --}}
<div id="batchWithdrawModal" class="admin-dialog-body" style="display: none;">
    <form class="layui-form" id="batchWithdrawForm" lay-filter="batchWithdrawForm">
        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.batch_target_status') }}</label>
            <div class="layui-input-block">
                <input type="radio" name="target_status" value="1" title="{{ __('admin.processing') }}">
                <input type="radio" name="target_status" value="2" title="{{ __('admin.completed') }}">
                <input type="radio" name="target_status" value="3" title="{{ __('admin.rejected') }}">
            </div>
        </div>
        <div class="layui-form-item layui-form-text">
            <label class="layui-form-label" for="batchWithdrawRemark">{{ __('admin.remark') }}</label>
            <div class="layui-input-block">
                <textarea id="batchWithdrawRemark" name="remark" maxlength="500" class="layui-textarea"
                          placeholder="{{ __('admin.remark') }}" aria-label="{{ __('admin.remark') }}"></textarea>
            </div>
        </div>
        <div class="layui-form-item crm-form-actions">
            <button type="button" class="layui-btn" id="batchWithdrawSubmit">{{ __('common.confirm') }}</button>
            <button type="button" class="layui-btn layui-btn-primary" id="batchWithdrawCancel">{{ __('common.cancel') }}</button>
        </div>
    </form>
</div>

{{-- 需求 9：出金统计独立成块，放到表格卡片之外，默认靠左对齐。
     数值由 admin_api_withdrawList 返回的 summary 填充，与列表共用同一份筛选条件。 --}}
<section class="crm-admin-stats-block" id="withdrawSummaryCards" aria-labelledby="withdrawStatsTitle">
    <div class="crm-admin-stats-heading">
        <span class="crm-admin-stats-title" id="withdrawStatsTitle" data-translate="admin.table_statistics">{{ __('admin.table_statistics') }}</span>
        <span class="crm-admin-stats-desc" data-translate="admin.table_statistics_desc">{{ __('admin.table_statistics_desc') }}</span>
    </div>
    <div class="crm-table-summary">
        <div class="crm-table-summary-item"><span data-translate="admin.total_records">{{ __('admin.total_records') }}</span><strong data-summary-field="total_records">0</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.total_withdraw_amount">{{ __('admin.total_withdraw_amount') }}</span><strong data-summary-field="total_withdraw_amount">0.00</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.total_actual_amount">{{ __('admin.total_actual_amount') }}</span><strong data-summary-field="total_actual_amount">0.00</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.total_fee">{{ __('admin.total_fee') }}</span><strong data-summary-field="total_fee">0.00</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.completed_records">{{ __('admin.completed_records') }}</span><strong data-summary-field="completed_records">0</strong></div>
    </div>
</section>
@endsection

@section('scripts')
<div hidden data-layui-page="withdrawals/index"></div>
@endsection
