{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/04
Time: 18:05
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.roles'))

@section('content')
{{-- 角色管理页面：roles 表保存角色基础信息，role_permissions 表保存角色与权限的授权关系。 --}}
<div class="layui-card crm-admin-panel">
    <div class="layui-card-header" data-translate="roles.title">{{ __('admin.roles') }}</div>
    <div class="layui-card-body">
        <div class="layui-btn-group">
            <button class="layui-btn" id="addRole" data-permission="admin_role_create" data-translate="common.add">{{ __('common.add') }}</button>
        </div>
        <table class="layui-hide" id="roleTable" lay-filter="roleTable"></table>
        <script type="text/html" id="roleActions">
            <a class="layui-btn layui-btn-warm layui-btn-xs" lay-event="assignPermissions" data-permission="admin_role_assign_permissions" data-translate="role.assignPermissions">{{ __('role.assign_permissions') }}</a>
            <a class="layui-btn layui-btn-xs" lay-event="edit" data-permission="admin_role_update" data-translate="common.edit">{{ __('common.edit') }}</a>
            <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="delete" data-permission="admin_role_delete" data-translate="common.delete">{{ __('common.delete') }}</a>
        </script>
    </div>
</div>

<div id="rolePermissionModal" class="admin-dialog-body" style="display: none;">
    {{-- 权限树来自 permissions 表；保存时只提交 role_id 与 permissions.id 数组，由后端写入 role_permissions 表。 --}}
    <form class="layui-form" id="rolePermissionForm" lay-filter="rolePermissionForm">
        <input type="hidden" name="role_id" id="rolePermissionRoleId">
        <input type="hidden" name="guard_type" id="rolePermissionGuardType">
        <div class="layui-form-item">
            <blockquote class="layui-elem-quote layui-text" id="rolePermissionHint"></blockquote>
        </div>
        <div class="layui-form-item">
            <div id="permissionTreeBox"></div>
        </div>
        <div class="layui-form-item admin-form-actions">
            <button type="button" class="layui-btn" id="saveRolePermissions" data-translate="common.save">{{ __('common.save') }}</button>
        </div>
    </form>
</div>

<div id="roleModal" class="admin-dialog-body" style="display: none;">
    <form class="layui-form" id="roleForm" lay-filter="roleForm">
        <input type="hidden" name="id">
        <input type="hidden" name="guard_type" value="admin">
        <div class="layui-form-item">
            <label class="layui-form-label" data-translate="roles.name">{{ __('role.name') }}</label>
            <div class="layui-input-block">
                <input type="text" name="name" required lay-verify="required" class="layui-input">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label" data-translate="roles.description">{{ __('role.description') }}</label>
            <div class="layui-input-block">
                <textarea name="description" class="layui-textarea"></textarea>
            </div>
        </div>
        <div class="layui-form-item">
            <div class="layui-input-block">
                <button class="layui-btn" lay-submit lay-filter="saveRole" data-translate="common.save">{{ __('common.save') }}</button>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="roles/index"></div>
@endsection
