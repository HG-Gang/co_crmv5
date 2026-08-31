{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/28
Time: 01:16
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.deposit'))

@section('content')
{{-- 入金管理：页面参数只负责筛选，审核安全性由 admin_api_depositApprove/admin_api_depositReject 再校验。 --}}
<div class="layui-card crm-admin-panel">
    <div class="layui-card-header">{{ __('admin.deposit') }}</div>
    <div class="layui-card-body">
        <form class="layui-form layui-form-pane" id="depositSearchForm">
            <div class="layui-form-item">
                <div class="layui-inline"><div class="layui-input-inline">
                        <input type="text" name="user_id" autocomplete="off" class="layui-input" placeholder="{{ __('admin.user_id') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        <select name="status">
                            <option value="">{{ __('admin.status') }}</option>
                            <option value="0">{{ __('admin.pending') }}</option>
                            <option value="1">{{ __('admin.approved') }}</option>
                            <option value="2">{{ __('admin.rejected') }}</option>
                        </select>
                    </div>
                </div>
                <div class="layui-inline">
                    <button class="layui-btn" lay-submit lay-filter="searchDeposits">{{ __('common.search') }}</button>
                    <button type="reset" class="layui-btn layui-btn-primary">{{ __('common.reset') }}</button>
                </div>
            </div>
        </form>

        <table class="layui-hide" id="depositTable" lay-filter="depositTable"></table>

        <script type="text/html" id="depositActions">
            <a class="layui-btn layui-btn-xs" lay-event="approve" data-permission="admin_deposit_approve">{{ __('admin.approve') }}</a>
            <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="reject" data-permission="admin_deposit_reject">{{ __('admin.reject') }}</a>
        </script>
    </div>
</div>

{{-- 需求 9：入金统计独立成块，放到表格卡片之外，默认靠左对齐。
     数值由 admin_api_depositList 返回的 summary 填充，与列表共用同一份筛选条件。 --}}
<section class="crm-admin-stats-block" id="depositSummaryCards" aria-labelledby="depositStatsTitle">
    <div class="crm-admin-stats-heading">
        <span class="crm-admin-stats-title" id="depositStatsTitle" data-translate="admin.table_statistics">{{ __('admin.table_statistics') }}</span>
        <span class="crm-admin-stats-desc" data-translate="admin.table_statistics_desc">{{ __('admin.table_statistics_desc') }}</span>
    </div>
    <div class="crm-table-summary">
        <div class="crm-table-summary-item"><span data-translate="admin.total_records">{{ __('admin.total_records') }}</span><strong data-summary-field="total_records">0</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.total_deposit_amount">{{ __('admin.total_deposit_amount') }}</span><strong data-summary-field="total_deposit_amount">0.00</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.total_actual_amount">{{ __('admin.total_actual_amount') }}</span><strong data-summary-field="total_actual_amount">0.00</strong></div>
        <div class="crm-table-summary-item"><span data-translate="admin.approved_records">{{ __('admin.approved_records') }}</span><strong data-summary-field="approved_records">0</strong></div>
    </div>
</section>
@endsection

@section('scripts')
<div hidden data-layui-page="deposits/index"></div>
@endsection
