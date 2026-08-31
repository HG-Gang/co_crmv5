{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/05
Time: 15:46
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.undeposit_flows'))

@section('content')
{{-- 未入金流水页面：页面只渲染筛选条件和 Layui 表格容器，真实数据由 admin_api_undepositFlowList 按权限与数据范围返回。 --}}
<div class="layui-card crm-admin-panel">
    <div class="layui-card-header">{{ __('admin.undeposit_flows') }}</div>
    <div class="layui-card-body">
        <div class="layui-btn-container">
            <button class="layui-btn layui-btn-primary" id="reloadUndepositFlows">{{ __('common.refresh') }}</button>
            <button class="layui-btn layui-btn-normal" id="exportUndepositFlows" data-permission="admin_undeposit_flow_export">
                {{ __('common.export') }}
            </button>
        </div>

        <form class="layui-form layui-form-pane" id="undepositFlowSearchForm">
            <div class="layui-form-item">
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- user_id：业务用户 ID，对应 deposit_records.user_id，用于按用户过滤未入金记录。 --}}
                        <input type="text" name="user_id" autocomplete="off" class="layui-input" placeholder="{{ __('admin.user_id') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- local_order_no：本地订单号，对应 deposit_records.local_order_no。 --}}
                        <input type="text" name="local_order_no" autocomplete="off" class="layui-input" placeholder="{{ __('admin.local_order_no') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- channel_order_no：通道订单号，对应 deposit_records.channel_order_no。 --}}
                        <input type="text" name="channel_order_no" autocomplete="off" class="layui-input" placeholder="{{ __('admin.channel_order_no') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- start_date：开始日期，接口会转换为当天 00:00:00 的时间戳并过滤 created_at。 --}}
                        <input type="text" name="start_date" id="undepositFlowStartDate" autocomplete="off" class="layui-input" placeholder="{{ __('admin.start_date') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- end_date：结束日期，接口会转换为当天 23:59:59 的时间戳并过滤 created_at。 --}}
                        <input type="text" name="end_date" id="undepositFlowEndDate" autocomplete="off" class="layui-input" placeholder="{{ __('admin.end_date') }}">
                    </div>
                </div>
                <div class="layui-inline">
                    <button class="layui-btn" lay-submit lay-filter="searchUndepositFlows">{{ __('common.search') }}</button>
                    <button type="reset" class="layui-btn layui-btn-primary">{{ __('common.reset') }}</button>
                </div>
            </div>
        </form>

        <table class="layui-hide" id="undepositFlowTable" lay-filter="undepositFlowTable"></table>
    </div>
</div>

{{-- 从未入金用户页面块：用于运营跟进注册后没有任何成功入金记录的普通客户。 --}}
<div class="layui-card crm-admin-panel">
    <div class="layui-card-header">{{ __('admin.never_deposit_users') }}</div>
    <div class="layui-card-body">
        <div class="layui-btn-container">
            <button class="layui-btn layui-btn-primary" id="reloadNeverDepositUsers">{{ __('common.refresh') }}</button>
        </div>

        <form class="layui-form layui-form-pane" id="neverDepositUserSearchForm">
            <div class="layui-form-item">
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- user_id：业务用户 ID，对应 user_infos.user_id，用于精确定位从未入金客户。 --}}
                        <input type="text" name="user_id" autocomplete="off" class="layui-input" placeholder="{{ __('admin.user_id') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- user_name：业务用户名，对应 user_infos.user_name，用于模糊搜索客户。 --}}
                        <input type="text" name="user_name" autocomplete="off" class="layui-input" placeholder="{{ __('admin.user_name') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- min_days：最少未入金天数，接口会换算为注册时间上限。 --}}
                        <input type="number" name="min_days" min="0" autocomplete="off" class="layui-input" placeholder="{{ __('admin.min_days') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- start_date：注册开始日期，对应 user_infos.created_at。 --}}
                        <input type="text" name="start_date" id="neverDepositStartDate" autocomplete="off" class="layui-input" placeholder="{{ __('admin.start_date') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- end_date：注册结束日期，对应 user_infos.created_at。 --}}
                        <input type="text" name="end_date" id="neverDepositEndDate" autocomplete="off" class="layui-input" placeholder="{{ __('admin.end_date') }}">
                    </div>
                </div>
                <div class="layui-inline">
                    <button class="layui-btn" lay-submit lay-filter="searchNeverDepositUsers">{{ __('common.search') }}</button>
                    <button type="reset" class="layui-btn layui-btn-primary">{{ __('common.reset') }}</button>
                </div>
            </div>
        </form>

        <table class="layui-hide" id="neverDepositUserTable" lay-filter="neverDepositUserTable"></table>
    </div>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="undeposit-flows/index"></div>
@endsection
