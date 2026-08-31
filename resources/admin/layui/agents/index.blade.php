{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/01
Time: 03:16
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.agents'))

@section('content')
{{-- 代理管理：筛选参数提交给 admin_api_agentList，后端继续按 permissions 和数据范围服务过滤可见代理。 --}}
<div class="layui-card crm-admin-panel">
    <div class="layui-card-header">{{ __('admin.agents') }}</div>
    <div class="layui-card-body">
        <form class="layui-form layui-form-pane" id="agentSearchForm">
            {{-- 旧后台 agents_edit_info/{uid} 直接落页到代理列表：uid 路由参数透传给页面，
                 前端可用 data-legacy-uid 定位目标代理行，保持旧导航零改造。 --}}
            <input type="hidden" name="legacy_uid" value="{{ $uid ?? '' }}" data-legacy-uid>
            <div class="layui-form-item">
                <div class="layui-inline"><div class="layui-input-inline">
                        <input type="text" name="agent_id" autocomplete="off" class="layui-input" placeholder="{{ __('admin.agent_id') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        <input type="text" name="user_name" autocomplete="off" class="layui-input" placeholder="{{ __('admin.user_name') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        <input type="text" name="start_date" id="agentStartDate" autocomplete="off" class="layui-input" placeholder="{{ __('admin.start_date') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        <input type="text" name="end_date" id="agentEndDate" autocomplete="off" class="layui-input" placeholder="{{ __('admin.end_date') }}">
                    </div>
                </div>
                <div class="layui-inline">
                    <button class="layui-btn" lay-submit lay-filter="searchAgents">{{ __('common.search') }}</button>
                    <button type="reset" class="layui-btn layui-btn-primary">{{ __('common.reset') }}</button>
                    <button type="button" class="layui-btn layui-btn-normal" id="loadAgentStats" data-permission="admin_agent_stats">{{ __('admin.agent_stats') }}</button>
                    <button type="button" class="layui-btn layui-btn-warm" id="exportAgents" data-permission="admin_agent_export" data-translate="common.export">{{ __('common.export') }}</button>
                </div>
            </div>
        </form>

        <table class="layui-hide" id="agentTable" lay-filter="agentTable"></table>

        <script type="text/html" id="agentActions">
            <a class="layui-btn layui-btn-primary layui-btn-xs" lay-event="descendants" data-permission="admin_agent_descendants">{{ __('admin.descendants') }}</a>
            <a class="layui-btn layui-btn-xs" lay-event="confirmAgent" data-permission="admin_agent_confirm">{{ __('admin.approve') }}</a>
            <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="rejectAgentConfirmation" data-permission="admin_agent_reject_confirmation">{{ __('admin.reject') }}</a>
            <a class="layui-btn layui-btn-xs" lay-event="updateLevel" data-permission="admin_agent_update_level">{{ __('admin.update_agent_level') }}</a>
            <a class="layui-btn layui-btn-normal layui-btn-xs" lay-event="updateCommission" data-permission="admin_agent_update_commission">{{ __('admin.update_agent_commission') }}</a>
        </script>
    </div>
</div>

<div id="agentLevelUpdateModal" class="admin-dialog-body" style="display: none;">
    {{-- 代理等级表单：agent_id 为业务代理用户 ID，level 写入 user_infos.level_id。 --}}
    <form class="layui-form" id="agentLevelUpdateForm" lay-filter="agentLevelUpdateForm">
        <input type="hidden" name="agent_id">

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.agent_id') }}</label>
            <div class="layui-input-block">
                <input type="text" name="agent_id_display" readonly class="layui-input layui-disabled">
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.level') }}</label>
            <div class="layui-input-block">
                <input type="number" name="level" required lay-verify="required|number" autocomplete="off" class="layui-input">
            </div>
        </div>

        <div class="layui-form-item">
            <div class="layui-input-block">
                <button class="layui-btn" lay-submit lay-filter="saveAgentLevelUpdate">{{ __('common.save') }}</button>
            </div>
        </div>
    </form>
</div>

<div id="agentCommissionUpdateModal" class="admin-dialog-body" style="display: none;">
    {{-- 代理佣金表单：comm_rate 为 0 到 100 的整数百分数（例如 85 表示 85%），与 user_infos.comm_rate 整数列、佣金引擎 /100 计算及旧后台 max:100 验证一致；0..1 小数口径为历史缺陷，已于 2026-08-29 修正。 --}}
    <form class="layui-form" id="agentCommissionUpdateForm" lay-filter="agentCommissionUpdateForm">
        <input type="hidden" name="agent_id">

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.agent_id') }}</label>
            <div class="layui-input-block">
                <input type="text" name="agent_id_display" readonly class="layui-input layui-disabled">
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.commissionRate') }}</label>
            <div class="layui-input-block">
                <input type="number" name="comm_rate" required lay-verify="required|number" autocomplete="off" class="layui-input" step="1" min="0" max="100" placeholder="0-100">
            </div>
        </div>

        <div class="layui-form-item">
            <div class="layui-input-block">
                <button class="layui-btn" lay-submit lay-filter="saveAgentCommissionUpdate">{{ __('common.save') }}</button>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="agents/index"></div>
@endsection
