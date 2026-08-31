{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/28
Time: 23:44
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.agent_levels'))

@section('content')
{{-- 代理等级：配置结果由 admin_api_agentLevelList 读取，新增/更新/删除动作均通过 permissions.slug 控制入口显隐。 --}}
<div class="layui-card crm-admin-panel">
    <div class="layui-card-header">{{ __('admin.agent_levels') }}</div>
    <div class="layui-card-body">
        <div class="layui-btn-container">
            <button class="layui-btn" id="reloadAgentLevels">{{ __('common.refresh') }}</button>
            {{-- data-permission 来自 permissions.slug，布局脚本会按 /api/admin/menus 返回的授权结果隐藏无权限按钮。 --}}
            <button class="layui-btn" id="addAgentLevel" data-permission="admin_agent_level_create">
                <i data-lucide="plus"></i> {{ __('common.add') }}
            </button>
        </div>
        <table class="layui-hide" id="agentLevelTable" lay-filter="agentLevelTable"></table>
        <script type="text/html" id="agentLevelActions">
            <a class="layui-btn layui-btn-xs" lay-event="edit" data-permission="admin_agent_level_update">{{ __('common.edit') }}</a>
            <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="delete" data-permission="admin_agent_level_delete">{{ __('common.delete') }}</a>
        </script>
    </div>
</div>

<div id="agentLevelModal" class="admin-dialog-body" style="display: none;">
    {{-- 代理等级表单：level 映射到 agent_levels.level_code；佣金字段按真实表字段提交。 --}}
    <form class="layui-form" id="agentLevelForm" lay-filter="agentLevelForm">
        <input type="hidden" name="id">

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.level') }}</label>
            <div class="layui-input-block">
                <input type="number" name="level" required lay-verify="required|number" autocomplete="off" class="layui-input">
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.name') }}</label>
            <div class="layui-input-block">
                <input type="text" name="name" required lay-verify="required" autocomplete="off" class="layui-input">
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.max_commission') }}</label>
            <div class="layui-input-block">
                <input type="number" name="max_commission" autocomplete="off" class="layui-input" value="0">
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.min_commission') }}</label>
            <div class="layui-input-block">
                <input type="number" name="min_commission" autocomplete="off" class="layui-input" value="0">
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.user_commission') }}</label>
            <div class="layui-input-block">
                <input type="number" name="user_commission" autocomplete="off" class="layui-input" value="0">
            </div>
        </div>

        <div class="layui-form-item">
            <div class="layui-input-block">
                <button class="layui-btn" lay-submit lay-filter="saveAgentLevel">{{ __('common.save') }}</button>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="agent-levels/index"></div>
@endsection
