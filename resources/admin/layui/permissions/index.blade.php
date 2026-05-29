@extends('admin_layui::layouts.app')

@section('title', __('admin.permissions'))

@section('content')
<div class="layui-card">
    <div class="layui-card-header" data-translate="permissions.title">{{ __('admin.permissions') }}</div>
    <div class="layui-card-body">
        <div id="permissionTree"></div>
        <div class="admin-form-actions">
            <button class="layui-btn" id="savePermissions" data-translate="common.save">{{ __('common.save') }}</button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('/js/admin/layui/permissions/index.js') }}"></script>
@endsection
