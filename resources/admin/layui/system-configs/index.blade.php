{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/04
Time: 18:05
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.system_configs'))

@section('content')
{{-- 系统配置：参数含义由 system_configs 表定义，页面只维护 key/value/group/description，避免在前端硬编码业务含义。 --}}
<div class="layui-card crm-admin-panel">
    <div class="layui-card-header">{{ __('admin.system_configs') }}</div>
    <div class="layui-card-body">
        <div class="layui-btn-container">
            <button class="layui-btn layui-btn-primary" id="reloadSystemConfigs">{{ __('common.refresh') }}</button>
        </div>
        <table class="layui-hide" id="systemConfigTable" lay-filter="systemConfigTable"></table>
        <script type="text/html" id="systemConfigActions">
            <a class="layui-btn layui-btn-xs" lay-event="edit" data-permission="admin_system_config_update">{{ __('common.edit') }}</a>
        </script>
    </div>
</div>

<div id="systemConfigModal" class="admin-dialog-body" style="display: none;">
    {{-- 系统配置表单：key 只用于识别配置项，不允许在页面直接改名；value/group/description 可维护。 --}}
    <form class="layui-form" id="systemConfigForm" lay-filter="systemConfigForm">
        <input type="hidden" name="id">

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.configKey') }}</label>
            <div class="layui-input-block">
                <input type="text" name="key" readonly class="layui-input layui-disabled">
            </div>
        </div>

        <div class="layui-form-item layui-form-text">
            <label class="layui-form-label">{{ __('admin.configValue') }}</label>
            <div class="layui-input-block">
                <textarea name="value" class="layui-textarea"></textarea>
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.group') }}</label>
            <div class="layui-input-block">
                <input type="text" name="group" autocomplete="off" class="layui-input">
            </div>
        </div>

        <div class="layui-form-item layui-form-text">
            <label class="layui-form-label">{{ __('admin.description') }}</label>
            <div class="layui-input-block">
                <textarea name="description" class="layui-textarea"></textarea>
            </div>
        </div>

        <div class="layui-form-item">
            <div class="layui-input-block">
                <button class="layui-btn" lay-submit lay-filter="saveSystemConfig">{{ __('common.save') }}</button>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="system-configs/index"></div>
@endsection
