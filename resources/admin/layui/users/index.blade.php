@extends('admin_layui::layouts.app')

@section('title', __('admin.users'))

@section('content')
<div class="layui-card">
    <div class="layui-card-header" data-translate="menu.userManagement">{{ __('admin.users') }}</div>
    <div class="layui-card-body">
        <form class="layui-form layui-form-pane" id="userSearchForm">
            <div class="layui-form-item">
                <div class="layui-inline">
                    <label class="layui-form-label" data-translate="user.userId">{{ __('front.user_id') }}</label>
                    <div class="layui-input-inline">
                        <input type="text" name="user_id" autocomplete="off" class="layui-input">
                    </div>
                </div>
                <div class="layui-inline">
                    <label class="layui-form-label" data-translate="user.email">{{ __('auth.email') }}</label>
                    <div class="layui-input-inline">
                        <input type="text" name="email" autocomplete="off" class="layui-input">
                    </div>
                </div>
                <div class="layui-inline">
                    <label class="layui-form-label" data-translate="user.accountType">{{ __('front.account_type') }}</label>
                    <div class="layui-input-inline">
                        <select name="account_type">
                            <option value="" data-translate="common.all">{{ __('common.all') }}</option>
                            <option value="1" data-translate="user.agentType">{{ __('register.agent') }}</option>
                            <option value="2" data-translate="user.customerType">{{ __('register.customer') }}</option>
                        </select>
                    </div>
                </div>
                <div class="layui-inline">
                    <button class="layui-btn" lay-submit lay-filter="searchUsers" data-translate="common.search">{{ __('common.search') }}</button>
                    <button type="reset" class="layui-btn layui-btn-primary" data-translate="common.reset">{{ __('common.reset') }}</button>
                </div>
            </div>
        </form>

        <table class="layui-hide" id="userTable" lay-filter="userTable"></table>

        <script type="text/html" id="userActions">
            <a class="layui-btn layui-btn-xs" lay-event="detail" data-translate="common.view">{{ __('common.view') }}</a>
            <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="status" data-translate="common.status">{{ __('common.status') }}</a>
        </script>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('/js/admin/layui/users/index.js') }}"></script>
@endsection
