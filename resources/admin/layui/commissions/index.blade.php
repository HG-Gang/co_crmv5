{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/21
Time: 01:44
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.commission'))

@section('content')
{{-- 返佣结算管理页面：列表读取 admin_api_commissionList；agent_id 筛选返佣归属代理，settle_status 筛选结算状态；结算动作调用 admin_api_commissionSettle。 --}}
<div class="layui-card crm-admin-panel">
    <div class="layui-card-header">{{ __('admin.commission') }}</div>
    <div class="layui-card-body">
        <form class="layui-form layui-form-pane" id="commissionSearchForm">
            <div class="layui-form-item">
                <div class="layui-inline"><div class="layui-input-inline">
                        <input type="text" name="agent_id" autocomplete="off" class="layui-input" placeholder="{{ __('admin.agent_id') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        <select name="settle_status">
                            <option value="">{{ __('admin.status') }}</option>
                            <option value="1">{{ __('admin.pending') }}</option>
                            <option value="2">{{ __('admin.settled') }}</option>
                        </select>
                    </div>
                </div>
                <div class="layui-inline">
                    <button class="layui-btn" lay-submit lay-filter="searchCommissions">{{ __('common.search') }}</button>
                    <button type="reset" class="layui-btn layui-btn-primary">{{ __('common.reset') }}</button>
                </div>
            </div>
        </form>

        <table class="layui-hide" id="commissionTable" lay-filter="commissionTable"></table>

        {{-- data-permission 来自 permissions.slug，按钮只做体验显隐，真正安全边界仍由后端 check.permission:admin 和数据范围校验控制。 --}}
        <script type="text/html" id="commissionActions">
            <a class="layui-btn layui-btn-xs" lay-event="settle" data-permission="admin_commission_settle">{{ __('admin.settle') }}</a>
        </script>
    </div>
</div>

<div class="layui-card crm-admin-panel" data-permission="admin_commission_transfer_reconciliation_list">
    <div class="layui-card-header">{{ __('admin.commission') }} / {{ __('admin.review_status') }}</div>
    <div class="layui-card-body">
        <table class="layui-hide" id="commissionTransferReconciliationTable" lay-filter="commissionTransferReconciliationTable"></table>

        <script type="text/html" id="commissionTransferReconciliationActions">
            <a class="layui-btn layui-btn-xs" lay-event="detail" data-permission="admin_commission_transfer_reconciliation_detail">{{ __('common.detail') }}</a>
            <a class="layui-btn layui-btn-xs layui-btn-normal" lay-event="reconcile" data-permission="admin_commission_transfer_reconcile">{{ __('admin.review_status') }}</a>
        </script>
    </div>
</div>

<div id="commissionTransferReconciliationModal" class="admin-dialog-body" style="display: none;">
    <form class="layui-form" id="commissionTransferReconciliationForm" lay-filter="commissionTransferReconciliationForm">
    <input type="hidden" name="transfer_id">
    <div class="layui-form-item">
        <label class="layui-form-label">Decision</label>
        <div class="layui-input-block">
            <select name="decision" lay-verify="required">
                <option value="">Select decision</option>
                <option value="confirmed_completed">confirmed_completed</option>
                <option value="confirmed_compensated">confirmed_compensated</option>
                <option value="confirmed_rejected">confirmed_rejected</option>
            </select>
        </div>
    </div>
    <div class="layui-form-item">
        <label class="layui-form-label">External reference</label>
        <div class="layui-input-block">
            <input type="text" name="external_reference" maxlength="100" required lay-verify="required" autocomplete="off" class="layui-input">
        </div>
    </div>
    @foreach ([
        ['status' => 'withdraw_status', 'reference' => 'withdraw_reference', 'label' => 'Withdraw'],
        ['status' => 'deposit_status', 'reference' => 'deposit_reference', 'label' => 'Deposit'],
        ['status' => 'compensation_status', 'reference' => 'compensation_reference', 'label' => 'Compensation'],
    ] as $command)
    <div class="layui-form-item">
        <label class="layui-form-label">{{ $command['label'] }} status</label>
        <div class="layui-input-block">
            <select name="{{ $command['status'] }}" lay-verify="required">
                <option value="confirmed_not_processed">confirmed_not_processed</option>
                <option value="confirmed_processed">confirmed_processed</option>
                <option value="confirmed_rejected">confirmed_rejected</option>
            </select>
        </div>
    </div>
    <div class="layui-form-item">
        <label class="layui-form-label">{{ $command['label'] }} reference</label>
        <div class="layui-input-block">
            <input type="text" name="{{ $command['reference'] }}" maxlength="100" autocomplete="off" class="layui-input">
        </div>
    </div>
    @endforeach
    <div class="layui-form-item">
        <label class="layui-form-label">Source balance after</label>
        <div class="layui-input-block">
            <input type="text" name="source_balance_after" maxlength="32" autocomplete="off" class="layui-input">
        </div>
    </div>
    <div class="layui-form-item">
        <label class="layui-form-label">Target balance after</label>
        <div class="layui-input-block">
            <input type="text" name="target_balance_after" maxlength="32" autocomplete="off" class="layui-input">
        </div>
    </div>
    <div class="layui-form-item">
        <div class="layui-input-block">
            <button class="layui-btn" lay-submit lay-filter="saveCommissionTransferReconciliation" data-permission="admin_commission_transfer_reconcile">Submit</button>
        </div>
    </div>
    </form>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="commissions/index"></div>
@endsection
