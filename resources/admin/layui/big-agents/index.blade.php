{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/28
Time: 23:44
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.big_agents'))

@section('content')
{{-- 大代理管理页面：列表读取 admin_api_bigAgentList，新增调用 admin_api_createBigAgent，编辑调用 admin_api_updateBigAgent，删除调用 admin_api_deleteBigAgent。 --}}
{{-- data-permission 对应 permissions.slug，按钮只做体验显隐，真正安全边界仍由后端 check.permission:admin 控制。 --}}
<div class="layui-card crm-admin-panel">
    <div class="layui-card-header">{{ __('admin.big_agents') }}</div>
    <div class="layui-card-body">
        <div class="layui-btn-container">
            <button class="layui-btn" id="reloadBigAgents">{{ __('common.refresh') }}</button>
            <button class="layui-btn" id="addBigAgent" data-permission="admin_big_agent_create">
                <i data-lucide="plus"></i> {{ __('common.add') }}
            </button>
        </div>
        <table class="layui-hide" id="bigAgentTable" lay-filter="bigAgentTable"></table>
        <script type="text/html" id="bigAgentActions">
            <a class="layui-btn layui-btn-xs" lay-event="edit" data-permission="admin_big_agent_update">{{ __('common.edit') }}</a>
            <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="delete" data-permission="admin_big_agent_delete">{{ __('common.delete') }}</a>
        </script>
    </div>
</div>

<div id="bigAgentModal" class="admin-dialog-body" style="display: none;">
    {{-- 大代理表单：id 为空表示新增；password 留空表示编辑时保留原密码；is_enabled 对应 big_agents.is_enabled。 --}}
    <form class="layui-form" id="bigAgentForm" lay-filter="bigAgentForm">
        <input type="hidden" name="id">

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.username') }}</label>
            <div class="layui-input-block">
                <input type="text" name="username" required lay-verify="required" autocomplete="off" class="layui-input">
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('user.email') }}</label>
            <div class="layui-input-block">
                <input type="email" name="email" required lay-verify="required|email" autocomplete="off" class="layui-input">
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('front.sub_agents') }}</label>
            <div class="layui-input-block">
                <input type="text" name="sub_agent_ids" placeholder="880301,880302" autocomplete="off" class="layui-input">
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.password') }}</label>
            <div class="layui-input-block">
                <input type="password" name="password" autocomplete="new-password" class="layui-input">
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('common.status') }}</label>
            <div class="layui-input-block">
                <input type="checkbox" name="is_enabled" value="1" lay-skin="switch" lay-text="{{ __('common.enabled') }}|{{ __('common.disabled') }}" checked>
            </div>
        </div>

        <div class="layui-form-item">
            <div class="layui-input-block">
                <button class="layui-btn" lay-submit lay-filter="saveBigAgent">{{ __('common.save') }}</button>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="big-agents/index"></div>
@endsection
