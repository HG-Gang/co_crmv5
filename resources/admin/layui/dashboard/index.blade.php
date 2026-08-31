{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/28
Time: 23:44
--}}
@extends('admin_layui::layouts.app')

@section('content')
<div class="layui-row layui-col-space15">
    <div class="layui-col-md4">
        <div class="stat-item">
            <div class="stat-icon"><i data-lucide="user-round"></i></div>
            <div class="stat-number" id="totalUsers">0</div>
            <div class="stat-label" data-translate="dashboard.totalUsers">{{ __('key.total_users') }}</div>
        </div>
    </div>
    <div class="layui-col-md4">
        <div class="stat-item">
            <div class="stat-icon"><i data-lucide="users-round"></i></div>
            <div class="stat-number" id="totalAgents">0</div>
            <div class="stat-label" data-translate="dashboard.totalAgents">{{ __('key.total_agents') }}</div>
        </div>
    </div>
    <div class="layui-col-md4">
        <div class="stat-item">
            <div class="stat-icon"><i data-lucide="network"></i></div>
            <div class="stat-number" id="totalCustomers">0</div>
            <div class="stat-label" data-translate="dashboard.totalCustomers">{{ __('key.total_customers') }}</div>
        </div>
    </div>
    <div class="layui-col-md4">
        <div class="stat-item">
            <div class="stat-icon"><i data-lucide="badge-dollar-sign"></i></div>
            <div class="stat-number" id="pendingDeposits">0</div>
            <div class="stat-label" data-translate="dashboard.pendingDeposits">{{ __('key.pending_deposits') }}</div>
        </div>
    </div>
    <div class="layui-col-md4">
        <div class="stat-item">
            <div class="stat-icon"><i data-lucide="circle-dollar-sign"></i></div>
            <div class="stat-number" id="pendingWithdraws">0</div>
            <div class="stat-label" data-translate="dashboard.pendingWithdraws">{{ __('key.pending_withdrawals') }}</div>
        </div>
    </div>
    <div class="layui-col-md4">
        <div class="stat-item">
            <div class="stat-icon"><i data-lucide="plus"></i></div>
            <div class="stat-number" id="todayNew">0</div>
            <div class="stat-label" data-translate="dashboard.todayNew">{{ __('key.today_new') }}</div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="dashboard/index"></div>
@endsection
