@extends('front_layui::layouts.app')

@section('title', __('front.account_flow'))
@section('breadcrumb', __('breadcrumb.front_flow'))

@section('styles')
<style>
    .flow-page .layui-card-header { font-weight: 600; }
    .flow-page .flow-toolbar { margin-bottom: 12px; }
    .flow-page .flow-table-wrap { width: 100%; overflow-x: auto; }
    .flow-page .flow-table-wrap .layui-table-view { min-width: 980px; }
</style>
@endsection

@section('content')
@php
    $flowTabs = [
        ['type' => 'deposit', 'label' => 'front.flow_deposit'],
        ['type' => 'withdraw', 'label' => 'front.flow_withdraw'],
        ['type' => 'withdraw_apply', 'label' => 'front.flow_withdraw_apply'],
        ['type' => 'direct_deposit', 'label' => 'front.flow_direct_deposit'],
        ['type' => 'direct_withdraw', 'label' => 'front.flow_direct_withdraw'],
        ['type' => 'direct_agents_deposit', 'label' => 'front.flow_direct_agents_deposit'],
        ['type' => 'direct_agents_withdraw', 'label' => 'front.flow_direct_agents_withdraw'],
    ];
@endphp

<div class="flow-page">
    <div class="layui-card">
        <div class="layui-card-header" data-translate="front.account_flow">{{ __('front.account_flow') }}</div>
        <div class="layui-card-body">
            <div class="layui-tab layui-tab-brief" lay-filter="frontFlowTabs">
                <ul class="layui-tab-title">
                    @foreach($flowTabs as $index => $tab)
                        <li class="{{ $index === 0 ? 'layui-this' : '' }}" lay-id="{{ $tab['type'] }}" data-translate="{{ $tab['label'] }}">{{ __($tab['label']) }}</li>
                    @endforeach
                </ul>
                <div class="layui-tab-content">
                    @foreach($flowTabs as $index => $tab)
                        <div class="layui-tab-item {{ $index === 0 ? 'layui-show' : '' }}">
                            <form class="layui-form layui-form-pane flow-toolbar J_flowForm" lay-filter="flowForm_{{ $tab['type'] }}" data-flow-type="{{ $tab['type'] }}">
                                <div class="layui-row layui-col-space10">
                                    <div class="layui-col-md3 layui-col-sm6">
                                        <div class="layui-form-item">
                                            <label class="layui-form-label" data-translate="front.user_id">{{ __('front.user_id') }}</label>
                                            <div class="layui-input-block">
                                                <input type="text" name="userId" class="layui-input" placeholder="{{ __('front.user_id') }}" data-translate-placeholder="front.user_id" autocomplete="off">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="layui-col-md3 layui-col-sm6">
                                        <div class="layui-form-item">
                                            <label class="layui-form-label" data-translate="front.date_from">{{ __('front.date_from') }}</label>
                                            <div class="layui-input-block">
                                                <input type="text" name="startdate" class="layui-input J_layDate" autocomplete="off">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="layui-col-md3 layui-col-sm6">
                                        <div class="layui-form-item">
                                            <label class="layui-form-label" data-translate="front.date_to">{{ __('front.date_to') }}</label>
                                            <div class="layui-input-block">
                                                <input type="text" name="enddate" class="layui-input J_layDate" autocomplete="off">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="layui-col-md3 layui-col-sm6 J_withdrawSource">
                                        <div class="layui-form-item">
                                            <label class="layui-form-label" data-translate="front.withdraw_source">{{ __('front.withdraw_source') }}</label>
                                            <div class="layui-input-block">
                                                <input type="text" name="withdraw_source" class="layui-input" placeholder="{{ __('front.withdraw_source') }}" data-translate-placeholder="front.withdraw_source" autocomplete="off">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="layui-col-md3 layui-col-sm6">
                                        <button class="layui-btn" lay-submit lay-filter="flowSearch" data-translate="common.search">{{ __('common.search') }}</button>
                                        <button type="button" class="layui-btn layui-btn-primary J_flowReset" data-translate="common.reset">{{ __('common.reset') }}</button>
                                    </div>
                                </div>
                            </form>
                            <div class="crm-table-summary" id="flowSummary_{{ $tab['type'] }}"></div>
                            <div class="flow-table-wrap">
                                <table id="flowTable_{{ $tab['type'] }}" lay-filter="flowTable_{{ $tab['type'] }}"></table>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('/js/front/layui/flow/index.js') }}"></script>
@endsection
