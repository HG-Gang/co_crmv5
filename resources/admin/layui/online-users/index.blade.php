{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/28
Time: 23:44
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.online_users'))

@section('content')
{{-- 在线用户页面：渲染筛选表单、Layui 表格和强制下线按钮；列表和下线动作均按 permissions.api_route 鉴权。 --}}
<div class="crm-admin-workbench">
    <div class="crm-page-head">
        <div>
            <h1>{{ __('admin.online_users') }}</h1>
            <p>{{ __('admin.online_users_desc') }}</p>
        </div>
        <button class="layui-btn layui-btn-primary" id="reloadOnlineUser">
            <i data-lucide="refresh-cw"></i>{{ __('common.refresh') }}
        </button>
    </div>

    <div class="layui-card crm-admin-panel">
        <div class="layui-card-header">{{ __('admin.online_users') }}</div>
        <div class="layui-card-body">
            <form class="layui-form layui-form-pane" id="onlineUserSearchForm">
                <div class="layui-form-item">
                    <div class="layui-inline"><div class="layui-input-inline">
                            {{-- user_id：业务用户 ID，对应 user_onlines.user_id，用于定位某个前台用户的在线记录。 --}}
                            <input type="text" name="user_id" autocomplete="off" class="layui-input" placeholder="{{ __('admin.user_id') }}">
                        </div>
                    </div>
                    <div class="layui-inline"><div class="layui-input-inline">
                            {{-- ip_address：登录或活跃 IP 地址，后端按 LIKE 模糊匹配。 --}}
                            <input type="text" name="ip_address" autocomplete="off" class="layui-input" placeholder="{{ __('admin.ip_address') }}">
                        </div>
                    </div>
                    <div class="layui-inline"><div class="layui-input-inline">
                            {{-- start_date：最后活跃开始日期，后端转为当天 00:00:00 时间戳过滤 last_activity。 --}}
                            <input type="text" name="start_date" id="onlineUserStartDate" autocomplete="off" class="layui-input" placeholder="{{ __('admin.start_date') }}">
                        </div>
                    </div>
                    <div class="layui-inline"><div class="layui-input-inline">
                            {{-- end_date：最后活跃结束日期，后端转为当天 23:59:59 时间戳过滤 last_activity。 --}}
                            <input type="text" name="end_date" id="onlineUserEndDate" autocomplete="off" class="layui-input" placeholder="{{ __('admin.end_date') }}">
                        </div>
                    </div>
                    <div class="layui-inline">
                        <button class="layui-btn" lay-submit lay-filter="searchOnlineUser">
                            <i data-lucide="search"></i>{{ __('common.search') }}
                        </button>
                        <button type="reset" class="layui-btn layui-btn-primary">{{ __('common.reset') }}</button>
                    </div>
                </div>
            </form>

            <table class="layui-hide" id="onlineUserTable" lay-filter="onlineUserTable"></table>
            <script type="text/html" id="onlineUserActions">
                <button class="layui-btn layui-btn-xs layui-btn-danger" lay-event="forceOffline" data-permission="admin_online_user_force_offline">
                    <i data-lucide="x"></i>{{ __('admin.force_offline') }}
                </button>
            </script>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="online-users/index"></div>
@endsection
