@extends('admin_layui::layouts.app')

@section('title', __('admin.roles'))

@section('content')
<div class="layui-card">
    <div class="layui-card-header" data-translate="roles.title">{{ __('admin.roles') }}</div>
    <div class="layui-card-body">
        <div class="layui-btn-group">
            <button class="layui-btn" id="addRole" data-translate="common.add">{{ __('common.add') }}</button>
        </div>
        <table class="layui-hide" id="roleTable" lay-filter="roleTable"></table>
        <script type="text/html" id="roleActions">
            <a class="layui-btn layui-btn-xs" lay-event="edit" data-translate="common.edit">{{ __('common.edit') }}</a>
            <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="delete" data-translate="common.delete">{{ __('common.delete') }}</a>
        </script>
    </div>
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
<script src="{{ asset('/js/admin/layui/roles/index.js') }}"></script>
@endsection
