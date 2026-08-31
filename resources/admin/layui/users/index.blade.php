{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/07
Time: 12:27
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.users'))

@section('content')
{{-- 用户管理页面：列表读取 admin_api_userList；user_id 筛选业务用户 ID，email 筛选登录邮箱，account_type 区分代理和客户；状态按钮调用 admin_api_changeUserStatus，真实访问必须经过后端权限与数据范围校验。 --}}
<div class="layui-card crm-admin-panel" data-visual-c-reference="admin-users">
    <div class="layui-card-header" data-translate="menu.userManagement">{{ __('admin.users') }}</div>
    <div class="layui-card-body">
        <form class="layui-form layui-form-pane" id="userSearchForm">
            <div class="layui-form-item">
                <div class="layui-inline"><div class="layui-input-inline">
                        <input type="text" name="user_id" autocomplete="off" class="layui-input" data-translate-placeholder="user.userId" placeholder="{{ __('front.user_id') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        <input type="text" name="email" autocomplete="off" class="layui-input" data-translate-placeholder="user.email" placeholder="{{ __('auth.email') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        <input type="text" name="user_name" autocomplete="off" class="layui-input" data-translate-placeholder="user.userName" placeholder="{{ __('front.user_name') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        <select name="account_type">
                            <option value="" data-translate="user.accountType">{{ __('front.account_type') }}</option>
                            <option value="1" data-translate="user.agentType">{{ __('register.agent') }}</option>
                            <option value="2" data-translate="user.customerType">{{ __('register.customer') }}</option>
                        </select>
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        <input type="text" id="userStartDate" name="start_date" autocomplete="off" class="layui-input" data-translate-placeholder="front.date_from" placeholder="{{ __('front.date_from') }}">
                    </div>
                </div>
                <div class="layui-inline"><div class="layui-input-inline">
                        <input type="text" id="userEndDate" name="end_date" autocomplete="off" class="layui-input" data-translate-placeholder="front.date_to" placeholder="{{ __('front.date_to') }}">
                    </div>
                </div>
                <div class="layui-inline">
                    <button class="layui-btn" lay-submit lay-filter="searchUsers" data-translate="common.search">{{ __('common.search') }}</button>
                    <button type="reset" class="layui-btn layui-btn-primary" data-translate="common.reset">{{ __('common.reset') }}</button>
                    <button type="button" class="layui-btn layui-btn-normal" id="exportUsers" data-permission="admin_user_export" data-translate="common.export">{{ __('common.export') }}</button>
                </div>
            </div>
        </form>

        <table class="layui-hide" id="userTable" lay-filter="userTable"></table>

        <script type="text/html" id="userActions">
            <a class="layui-btn layui-btn-xs" lay-event="detail" data-translate="common.view">{{ __('common.view') }}</a>
            <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="status" data-permission="admin_user_status" data-translate="common.status">{{ __('common.status') }}</a>
        </script>
    </div>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="users/index"></div>
@endsection
