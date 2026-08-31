{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/04
Time: 18:05
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.permissions'))

@section('content')
<div class="layui-card crm-admin-panel">
    <div class="layui-card-header" data-translate="permissions.title">{{ __('admin.permissions') }}</div>
    <div class="layui-card-body">
        <div id="permissionTree"></div>
        <div class="admin-form-actions">
            <button class="layui-btn" id="savePermissions" data-permission="admin_permission_update" data-translate="common.save">{{ __('common.save') }}</button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="permissions/index"></div>
@endsection
