{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/13
Time: 00:29
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.withdraw_flows'))

@section('content')
{{-- 出金流水页面：页面只渲染筛选条件和 Layui 表格容器，真实数据由 admin_api_withdrawFlowList 按权限与数据范围返回。 --}}
<div class="layui-card crm-admin-panel">
    <div class="layui-card-header">{{ __('admin.withdraw_flows') }}</div>
    <div class="layui-card-body">
        <div class="layui-btn-container">
            <button class="layui-btn layui-btn-primary" id="reloadWithdrawFlows">{{ __('common.refresh') }}</button>
            <button class="layui-btn layui-btn-normal" id="exportWithdrawFlows" data-permission="admin_withdraw_flow_export">
                {{ __('common.export') }}
            </button>
        </div>

        <form class="layui-form layui-form-pane" id="withdrawFlowSearchForm">
            <div class="layui-form-item">
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- user_id：业务用户 ID，对应 mt4_trades.login，用于按用户过滤出金流水。 --}}
                        <input type="text" name="user_id" autocomplete="off" class="layui-input" placeholder="{{ __('admin.user_id') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- ticket：MT4 订单号，对应 mt4_trades.ticket，用于定位单笔余额类交易。 --}}
                        <input type="text" name="ticket" autocomplete="off" class="layui-input" placeholder="{{ __('admin.ticket') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- withdraw_source：MT4 comment 来源关键字，例如 WBIN 或 WBAD，用于按旧项目出金类型过滤。 --}}
                        <input type="text" name="withdraw_source" autocomplete="off" class="layui-input" placeholder="{{ __('admin.withdraw_source') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- start_date：开始日期，接口会转换为当天 00:00:00 的时间戳并过滤 close_time。 --}}
                        <input type="text" name="start_date" id="withdrawFlowStartDate" autocomplete="off" class="layui-input" placeholder="{{ __('admin.start_date') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- end_date：结束日期，接口会转换为当天 23:59:59 的时间戳并过滤 close_time。 --}}
                        <input type="text" name="end_date" id="withdrawFlowEndDate" autocomplete="off" class="layui-input" placeholder="{{ __('admin.end_date') }}">
                    </div>
                </div>
                <div class="layui-inline">
                    <button class="layui-btn" lay-submit lay-filter="searchWithdrawFlows">{{ __('common.search') }}</button>
                    <button type="reset" class="layui-btn layui-btn-primary">{{ __('common.reset') }}</button>
                </div>
            </div>
        </form>

        <table class="layui-hide" id="withdrawFlowTable" lay-filter="withdrawFlowTable"></table>
    </div>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="withdraw-flows/index"></div>
@endsection
