@extends('admin_layui::layouts.app')

@section('content')
<div class="layui-row layui-col-space15">
    <div class="layui-col-md4">
        <div class="stat-item">
            <div class="stat-icon"><i class="layui-icon layui-icon-user"></i></div>
            <div class="stat-number" id="totalUsers">0</div>
            <div class="stat-label" data-translate="dashboard.totalUsers">{{ __('key.total_users') }}</div>
        </div>
    </div>
    <div class="layui-col-md4">
        <div class="stat-item">
            <div class="stat-icon"><i class="layui-icon layui-icon-team"></i></div>
            <div class="stat-number" id="totalAgents">0</div>
            <div class="stat-label" data-translate="dashboard.totalAgents">{{ __('key.total_agents') }}</div>
        </div>
    </div>
    <div class="layui-col-md4">
        <div class="stat-item">
            <div class="stat-icon"><i class="layui-icon layui-icon-group"></i></div>
            <div class="stat-number" id="totalCustomers">0</div>
            <div class="stat-label" data-translate="dashboard.totalCustomers">{{ __('key.total_customers') }}</div>
        </div>
    </div>
    <div class="layui-col-md4">
        <div class="stat-item">
            <div class="stat-icon"><i class="layui-icon layui-icon-rmb"></i></div>
            <div class="stat-number" id="pendingDeposits">0</div>
            <div class="stat-label" data-translate="dashboard.pendingDeposits">{{ __('key.pending_deposits') }}</div>
        </div>
    </div>
    <div class="layui-col-md4">
        <div class="stat-item">
            <div class="stat-icon"><i class="layui-icon layui-icon-dollar"></i></div>
            <div class="stat-number" id="pendingWithdraws">0</div>
            <div class="stat-label" data-translate="dashboard.pendingWithdraws">{{ __('key.pending_withdrawals') }}</div>
        </div>
    </div>
    <div class="layui-col-md4">
        <div class="stat-item">
            <div class="stat-icon"><i class="layui-icon layui-icon-add-1"></i></div>
            <div class="stat-number" id="todayNew">0</div>
            <div class="stat-label" data-translate="dashboard.todayNew">{{ __('key.today_new') }}</div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('/js/admin/layui/dashboard/index.js') }}"></script>
@endsection
