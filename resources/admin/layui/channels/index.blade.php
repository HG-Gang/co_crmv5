{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/28
Time: 00:58
--}}
@extends('admin_layui::layouts.app')

@section('title', __('admin.payment_channels'))

@section('content')
{{-- 支付通道：通道状态影响资金流程，编辑和启停都必须由后台 API 再次鉴权。 --}}
{{-- 需求 8：通道按启用状态分成 layui-tab 页签展示。页签只负责收窄 status 筛选条件，
     新增、编辑、启停、删除仍走同一套 channelTable 事件与同一批后台接口，CRUD 行为零变化。 --}}
<div class="layui-card crm-admin-panel">
    <div class="layui-card-header">{{ __('admin.payment_channels') }}</div>
    <div class="layui-card-body">
        <div class="layui-btn-container">
            <button class="layui-btn layui-btn-primary" id="reloadChannels">{{ __('common.refresh') }}</button>
            {{-- data-permission 来自 permissions.slug；布局脚本会根据 /api/admin/menus 返回的授权结果隐藏无权限按钮。 --}}
            <button class="layui-btn" id="addChannel" data-permission="admin_channel_create">
                <i data-lucide="plus"></i> {{ __('common.add') }}
            </button>
        </div>

        <div class="layui-tab layui-tab-brief crm-channel-tab" id="channelTabs" lay-filter="channelTabs">
            {{-- data-channel-status 为空表示全部通道，1=已启用，0=已停用；切换页签时写入表单隐藏域后重载表格。 --}}
            <ul class="layui-tab-title" role="tablist" aria-label="{{ __('admin.channel_groups') }}">
                <li class="layui-this" data-channel-status="" data-translate="admin.channel_tab_all">{{ __('admin.channel_tab_all') }}</li>
                <li data-channel-status="1" data-translate="admin.channel_tab_enabled">{{ __('admin.channel_tab_enabled') }}</li>
                <li data-channel-status="0" data-translate="admin.channel_tab_disabled">{{ __('admin.channel_tab_disabled') }}</li>
            </ul>
            <div class="layui-tab-content">
                <div class="layui-tab-item layui-show">
                    <form class="layui-form layui-form-pane" id="channelSearchForm">
                        {{-- status 由当前页签驱动，保留隐藏域让筛选参数与旧接口字段名保持一致。 --}}
                        <input type="hidden" name="status" value="" data-channel-status-input>
                        <div class="layui-form-item">
                            <div class="layui-inline"><div class="layui-input-inline">
                                    <input type="text" name="name" autocomplete="off" class="layui-input"
                                           placeholder="{{ __('admin.name') }}" data-translate-placeholder="admin.name">
                                </div>
                            </div>
                            <div class="layui-inline"><div class="layui-input-inline">
                                    <input type="text" name="channel_code" autocomplete="off" class="layui-input"
                                           placeholder="{{ __('admin.code') }}" data-translate-placeholder="admin.code">
                                </div>
                            </div>
                            <div class="layui-inline">
                                <button class="layui-btn" lay-submit lay-filter="searchChannels">{{ __('common.search') }}</button>
                                <button type="reset" class="layui-btn layui-btn-primary">{{ __('common.reset') }}</button>
                            </div>
                        </div>
                    </form>

                    <table class="layui-hide" id="channelTable" lay-filter="channelTable"></table>
                </div>
            </div>
        </div>

        <script type="text/html" id="channelActions">
            <a class="layui-btn layui-btn-xs" lay-event="edit" data-permission="admin_channel_update">{{ __('common.edit') }}</a>
            <a class="layui-btn layui-btn-warm layui-btn-xs" lay-event="toggle" data-permission="admin_channel_toggle">{{ __('common.status') }}</a>
            <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="delete" data-permission="admin_channel_delete">{{ __('common.delete') }}</a>
        </script>
    </div>
</div>

{{-- 需求 9：统计独立成块，放在表格卡片之外，默认靠左对齐，视觉上与表格区分。 --}}
<section class="crm-admin-stats-block" aria-labelledby="channelStatisticsTitle">
    <div class="crm-admin-stats-heading">
        <span class="crm-admin-stats-title" id="channelStatisticsTitle" data-translate="admin.table_statistics">{{ __('admin.table_statistics') }}</span>
        <span class="crm-admin-stats-desc" data-translate="admin.table_statistics_desc">{{ __('admin.table_statistics_desc') }}</span>
    </div>
    <div class="crm-table-summary" id="channelStatistics" data-admin-table-statistics></div>
</section>

<div id="channelModal" class="admin-dialog-body" style="display: none;">
    {{-- 支付通道表单：字段直接对应 payment_channels 表；is_enabled 控制通道是否可在业务流程中使用。 --}}
    <form class="layui-form" id="channelForm" lay-filter="channelForm">
        <input type="hidden" name="id">

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.name') }}</label>
            <div class="layui-input-block">
                <input type="text" name="name" required lay-verify="required" autocomplete="off" class="layui-input">
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.code') }}</label>
            <div class="layui-input-block">
                <input type="text" name="channel_code" required lay-verify="required" autocomplete="off" class="layui-input">
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.exchange_rate') }}</label>
            <div class="layui-input-block">
                <input type="number" name="exchange_rate" autocomplete="off" class="layui-input" value="1" step="0.0001">
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.sort') }}</label>
            <div class="layui-input-block">
                <input type="number" name="sort" autocomplete="off" class="layui-input" value="0">
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.status') }}</label>
            <div class="layui-input-block">
                <select name="is_enabled">
                    <option value="1">{{ __('admin.enabled') }}</option>
                    <option value="0">{{ __('admin.disabled') }}</option>
                </select>
            </div>
        </div>

        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('admin.config') }}</label>
            <div class="layui-input-block">
                <textarea name="config" class="layui-textarea" placeholder="{{ __('admin.config_json_placeholder') }}"></textarea>
            </div>
        </div>

        <div class="layui-form-item">
            <div class="layui-input-block">
                <button class="layui-btn" lay-submit lay-filter="saveChannel">{{ __('common.save') }}</button>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="channels/index"></div>
@endsection
