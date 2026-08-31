{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/28
Time: 23:44
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.admins'))

@section('content')
{{-- 管理员账号管理页面：列表读取 admin_api_adminList，新增调用 admin_api_createAdmin，编辑调用 admin_api_updateAdmin，重置密码调用 admin_api_resetAdminPassword，删除调用 admin_api_deleteAdmin。 --}}
{{-- data-permission 对应 permissions.slug，按钮只做体验显隐，真正安全边界仍由后端 check.permission:admin 控制。 --}}
<div class="layui-card crm-admin-panel">
    <div class="layui-card-header">{{ __('admin.admins') }}</div>
    <div class="layui-card-body">
        <div class="layui-btn-container">
            <button class="layui-btn layui-btn-primary" id="reloadAdmins">{{ __('common.refresh') }}</button>
            <button class="layui-btn" id="addAdmin" data-permission="admin_admin_create">
                <i data-lucide="plus"></i> {{ __('common.add') }}
            </button>
        </div>
        <table class="layui-hide" id="adminTable" lay-filter="adminTable"></table>
        <script type="text/html" id="adminActions">
            <a class="layui-btn layui-btn-xs" lay-event="edit" data-permission="admin_admin_update">{{ __('common.edit') }}</a>
            <a class="layui-btn layui-btn-warm layui-btn-xs" lay-event="resetPassword" data-permission="admin_admin_reset_password">{{ __('admin.reset_password') }}</a>
            <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="delete" data-permission="admin_admin_delete">{{ __('common.delete') }}</a>
        </script>
    </div>
</div>

<div id="adminModal" class="admin-dialog-body" style="display: none;">
    {{-- 管理员账号表单：password 新增时必填；password 留空表示编辑时保留原密码。 --}}
    <form class="layui-form" id="adminForm" lay-filter="adminForm">
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
            <label class="layui-form-label">{{ __('admin.phone') }}</label>
            <div class="layui-input-block">
                <input type="text" name="mobile" autocomplete="off" class="layui-input">
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.role') }}</label>
            <div class="layui-input-block">
                <input type="number" name="role_id" autocomplete="off" class="layui-input">
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.status') }}</label>
            <div class="layui-input-block">
                <select name="status">
                    <option value="1">{{ __('admin.enabled') }}</option>
                    <option value="0">{{ __('admin.disabled') }}</option>
                </select>
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.password') }}</label>
            <div class="layui-input-block">
                <input type="password" name="password" autocomplete="new-password" class="layui-input" placeholder="{{ __('admin.password_keep_placeholder') }}">
            </div>
        </div>

        <div class="layui-form-item">
            <div class="layui-input-block">
                <button class="layui-btn" lay-submit lay-filter="saveAdmin">{{ __('common.save') }}</button>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="admins/index"></div>
@endsection
