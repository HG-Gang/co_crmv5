@extends('admin_layui::layouts.app')

@section('title', __('admin.menus'))

@section('content')
<div class="layui-card">
    <div class="layui-card-header" data-translate="menus.title">{{ __('admin.menus') }}</div>
    <div class="layui-card-body">
        <div class="layui-btn-group">
            <button class="layui-btn" id="addMenu" data-translate="common.add">{{ __('common.add') }}</button>
        </div>
        <div id="menuTree"></div>
    </div>
</div>

<div id="menuModal" class="admin-dialog-body" style="display: none;">
    <form class="layui-form" id="menuForm" lay-filter="menuForm">
        <input type="hidden" name="id">
        <input type="hidden" name="parent_id">
        <input type="hidden" name="guard_type" value="admin">
        <div class="layui-form-item">
            <label class="layui-form-label" data-translate="menus.title">{{ __('menus.title') }}</label>
            <div class="layui-input-block">
                <input type="text" name="title" required lay-verify="required" class="layui-input">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label" data-translate="menus.url">{{ __('menus.url') }}</label>
            <div class="layui-input-block">
                <input type="text" name="url" required lay-verify="required" class="layui-input">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label" data-translate="menus.icon">{{ __('menus.icon') }}</label>
            <div class="layui-input-block">
                <input type="text" name="icon" class="layui-input">
            </div>
        </div>
        <div class="layui-form-item">
            <div class="layui-input-block">
                <button class="layui-btn" lay-submit lay-filter="saveMenu" data-translate="common.save">{{ __('common.save') }}</button>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script src="{{ asset('/js/admin/layui/menus/index.js') }}"></script>
@endsection
