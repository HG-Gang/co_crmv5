{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/28
Time: 23:44
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.blacklist'))

@section('content')
{{-- 黑名单管理页面：列表读取 admin_api_blacklistList，新增调用 admin_api_createBlacklist，编辑调用 admin_api_updateBlacklist，删除调用 admin_api_deleteBlacklist；keyword 匹配姓名、证件、邮箱和手机号。 --}}
<div class="layui-card crm-admin-panel">
    <div class="layui-card-header">{{ __('admin.blacklist') }}</div>
    <div class="layui-card-body">
        <form class="layui-form layui-form-pane" id="blacklistSearchForm">
            <div class="layui-form-item">
                <div class="layui-inline"><div class="layui-input-inline">
                        <input type="text" name="keyword" autocomplete="off" class="layui-input" placeholder="{{ __('admin.keyword') }}">
                    </div>
                </div>
                <div class="layui-inline">
                    <button class="layui-btn" lay-submit lay-filter="searchBlacklist">{{ __('common.search') }}</button>
                    <button type="reset" class="layui-btn layui-btn-primary">{{ __('common.reset') }}</button>
                    {{-- data-permission 来自 permissions.slug，按钮只做体验显隐，真正安全边界仍由后端 check.permission:admin 控制。 --}}
                    <button type="button" class="layui-btn" id="addBlacklist" data-permission="admin_blacklist_create">
                        <i data-lucide="plus"></i> {{ __('common.add') }}
                    </button>
                </div>
            </div>
        </form>
        <table class="layui-hide" id="blacklistTable" lay-filter="blacklistTable"></table>
        <script type="text/html" id="blacklistActions">
            <a class="layui-btn layui-btn-xs" lay-event="edit" data-permission="admin_blacklist_update">{{ __('common.edit') }}</a>
            <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="delete" data-permission="admin_blacklist_delete">{{ __('common.delete') }}</a>
        </script>
    </div>
</div>

<div id="blacklistModal" class="admin-dialog-body" style="display: none;">
    {{-- 黑名单表单：id 为空时创建记录，id 有值时更新对应记录；字段名与 BlacklistController 入参保持一致。 --}}
    <form class="layui-form" id="blacklistForm" lay-filter="blacklistForm">
        <input type="hidden" name="id">

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.name') }}</label>
            <div class="layui-input-block">
                <input type="text" name="name" required lay-verify="required" autocomplete="off" class="layui-input">
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.idCard') }}</label>
            <div class="layui-input-block">
                <input type="text" name="id_card" autocomplete="off" class="layui-input">
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('user.email') }}</label>
            <div class="layui-input-block">
                <input type="text" name="email" autocomplete="off" class="layui-input">
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.phone') }}</label>
            <div class="layui-input-block">
                <input type="text" name="phone" autocomplete="off" class="layui-input">
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('common.remark') }}</label>
            <div class="layui-input-block">
                <textarea name="remark" class="layui-textarea"></textarea>
            </div>
        </div>

        <div class="layui-form-item">
            <div class="layui-input-block">
                <button class="layui-btn" lay-submit lay-filter="saveBlacklist">{{ __('common.save') }}</button>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="blacklist/index"></div>
@endsection
