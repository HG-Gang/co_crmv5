{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/21
Time: 21:46
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.cancel_applies'))

@section('content')
{{-- 注销申请管理页面：列表读取 admin_api_cancelApplyList，审核通过调用 admin_api_cancelApplyApprove，审核拒绝调用 admin_api_cancelApplyReject；默认展示待处理申请，status 为空表示全部申请。 --}}
<div class="layui-card crm-admin-panel">
    <div class="layui-card-header">{{ __('admin.cancel_applies') }}</div>
    <div class="layui-card-body">
        <form class="layui-form layui-form-pane cancel-apply-filter-grid" id="cancelApplySearchForm" lay-filter="cancelApplySearchForm">
            <div class="layui-form-item">
                <div class="layui-inline">
                    <div class="layui-input-inline">
                        <input class="layui-input" name="user_id" type="number" min="1" placeholder="{{ __('admin.user_id') }}" aria-label="{{ __('admin.user_id') }}" autocomplete="off">
                    </div>
                </div>
                <div class="layui-inline">
                    <div class="layui-input-inline">
                        <select name="status" aria-label="{{ __('admin.status') }}">
                            <option value="">{{ __('admin.status') }}</option>
                            <option value="0" selected>{{ __('admin.pending') }}</option>
                            <option value="1">{{ __('admin.approved') }}</option>
                            <option value="-1">{{ __('admin.rejected') }}</option>
                        </select>
                    </div>
                </div>
                <div class="layui-inline">
                    <div class="layui-input-inline">
                        <input class="layui-input" name="start_date" type="date" placeholder="{{ __('admin.start_date') }}" aria-label="{{ __('admin.start_date') }}" autocomplete="off">
                    </div>
                </div>
                <div class="layui-inline">
                    <div class="layui-input-inline">
                        <input class="layui-input" name="end_date" type="date" placeholder="{{ __('admin.end_date') }}" aria-label="{{ __('admin.end_date') }}" autocomplete="off">
                    </div>
                </div>
                <div class="layui-inline">
                    <button class="layui-btn" lay-submit lay-filter="searchCancelApplies">{{ __('common.search') }}</button>
                    <button type="button" class="layui-btn layui-btn-primary" id="resetCancelApplySearch">{{ __('common.reset') }}</button>
                </div>
            </div>
        </form>
        <table class="layui-hide" id="cancelApplyTable" lay-filter="cancelApplyTable"></table>
        {{-- data-permission 来自 permissions.slug，按钮只做体验显隐，真正安全边界仍由后端 check.permission:admin 控制。 --}}
        <script type="text/html" id="cancelApplyActions">
            @{{# if (Number(d.status) === 0) { }}
                <a class="layui-btn layui-btn-xs" lay-event="approve" data-permission="admin_cancel_apply_approve">{{ __('admin.approve') }}</a>
                <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="reject" data-permission="admin_cancel_apply_reject">{{ __('admin.reject') }}</a>
            @{{# } }}
        </script>
    </div>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="cancel-applies/index"></div>
@endsection
