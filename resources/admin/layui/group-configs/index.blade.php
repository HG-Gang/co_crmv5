{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/28
Time: 23:44
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.group_configs'))

@section('content')
{{-- 组别配置管理页面：列表读取 admin_api_groupConfigList，新增、编辑、删除分别走 admin_api_createGroupConfig、admin_api_updateGroupConfig、admin_api_deleteGroupConfig。 --}}
{{-- data-permission 来自 permissions.slug，前端按钮显隐只做体验控制，后端 check.permission:admin 才是最终接口安全边界。 --}}
<div class="layui-card crm-admin-panel">
    <div class="layui-card-header">{{ __('admin.group_configs') }}</div>
    <div class="layui-card-body">
        <form class="layui-form layui-form-pane" id="groupConfigSearchForm">
            <div class="layui-form-item">
                <div class="layui-inline"><div class="layui-input-inline">
                        {{-- keyword 表示组别名称搜索关键字，提交给 groupConfigList 用于过滤 group_configs.name。 --}}
                        <input type="text" name="keyword" autocomplete="off" class="layui-input" placeholder="{{ __('admin.keyword') }}">
                    </div>
                </div>
                <div class="layui-inline">
                    <button class="layui-btn" lay-submit lay-filter="searchGroupConfigs">{{ __('common.search') }}</button>
                    <button type="reset" class="layui-btn layui-btn-primary">{{ __('common.reset') }}</button>
                    <button type="button" class="layui-btn" id="addGroupConfig" data-permission="admin_group_config_create">
                        <i data-lucide="plus"></i> {{ __('common.add') }}
                    </button>
                </div>
            </div>
        </form>
        <table class="layui-hide" id="groupConfigTable" lay-filter="groupConfigTable"></table>
        <script type="text/html" id="groupConfigActions">
            <a class="layui-btn layui-btn-xs" lay-event="edit" data-permission="admin_group_config_update">{{ __('common.edit') }}</a>
            <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="delete" data-permission="admin_group_config_delete">{{ __('common.delete') }}</a>
        </script>
    </div>
</div>

<div id="groupConfigModal" class="admin-dialog-body" style="display: none;">
    {{-- 组别配置表单：group_name 映射到 group_configs.name；category 取值 1=代理组、2=用户组。 --}}
    {{-- has_commission、is_enabled、is_ecn、is_default 都是 group_configs 的 1/0 开关字段，JS 会在提交前补齐未勾选值。 --}}
    <form class="layui-form" id="groupConfigForm" lay-filter="groupConfigForm">
        <input type="hidden" name="id">

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.group_name') }}</label>
            <div class="layui-input-block">
                <input type="text" name="group_name" required lay-verify="required" autocomplete="off" class="layui-input">
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.radix') }}</label>
            <div class="layui-input-block">
                <input type="number" name="radix" autocomplete="off" class="layui-input" value="50">
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.category') }}</label>
            <div class="layui-input-block">
                <select name="category" lay-verify="required">
                    <option value="1">{{ __('admin.agent_group') }}</option>
                    <option value="2">{{ __('admin.user_group') }}</option>
                </select>
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.group_flags') }}</label>
            <div class="layui-input-block">
                <input type="checkbox" name="has_commission" value="1" title="{{ __('admin.has_commission') }}">
                <input type="checkbox" name="is_enabled" value="1" title="{{ __('common.enabled') }}" checked>
                <input type="checkbox" name="is_ecn" value="1" title="{{ __('admin.is_ecn') }}">
                <input type="checkbox" name="is_default" value="1" title="{{ __('admin.is_default') }}">
            </div>
        </div>

        <div class="layui-form-item">
            <div class="layui-input-block">
                <button class="layui-btn" lay-submit lay-filter="saveGroupConfig">{{ __('common.save') }}</button>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="group-configs/index"></div>
@endsection
