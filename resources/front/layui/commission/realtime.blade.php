{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/08
Time: 00:30
--}}
﻿@extends('front_layui::layouts.app')

@section('title', __('front.realtime_commission'))
@section('breadcrumb', __('breadcrumb.front_commission_rt'))

@section('content')
@include('front_layui::partials.module-page', [
    'pageClass' => 'commission-module commission-realtime-module',
    'titleKey' => 'front.realtime_commission',
    'descriptionKey' => 'front.realtime_commission_desc',
    'api' => '/api/front/commissions/realtime',
    'method' => 'GET',
    'listKey' => 'list',
    'showSummary' => true,
    'filters' => [
        ['name' => 'userId', 'label' => 'front.user_id', 'type' => 'text'],
        ['name' => 'orderId', 'label' => 'front.order_no', 'type' => 'text'],
        ['name' => 'startdate', 'label' => 'front.date_from', 'type' => 'date'],
        ['name' => 'enddate', 'label' => 'front.date_to', 'type' => 'date'],
    ],
    'summaryFields' => [
        ['key' => 'total_commission', 'label' => 'front.total_commission'],
        ['key' => 'total_volume', 'label' => 'front.total_volume'],
        ['key' => 'profit_gain', 'label' => 'front.profit_gain'],
        ['key' => 'profit_loss', 'label' => 'front.profit_loss'],
        ['key' => 'profit_net', 'label' => 'front.profit_net'],
    ],
    'columns' => [
        ['key' => 'ticket', 'label' => 'front.ticket', 'action' => 'showOrderInfo', 'linkClass' => 'module-link-order'],
        ['key' => 'login', 'label' => 'front.user_id', 'action' => 'showUserInfo', 'api' => '/api/front/users/{user}', 'method' => 'GET', 'routeParams' => ['user' => '{login}'], 'idField' => 'login', 'linkClass' => 'module-link-user'],
        ['key' => 'volume_lots', 'label' => 'front.volume', 'format' => 'lots'],
        ['key' => 'current_commission_amount', 'label' => 'front.current_commission_amount', 'format' => 'money'],
        ['key' => 'current_commission_status_text', 'label' => 'front.current_commission_status_text'],
        ['key' => 'rebate_ratio', 'label' => 'front.rebate_ratio'],
        ['key' => 'profit_gain', 'label' => 'front.profit_gain', 'format' => 'money'],
        ['key' => 'profit_loss', 'label' => 'front.profit_loss', 'format' => 'money'],
        ['key' => 'profit_net', 'label' => 'front.profit_net', 'format' => 'money'],
        ['key' => 'commission_updated_at', 'label' => 'front.commission_updated_at'],
        ['key' => 'order_created_at', 'label' => 'front.order_created_at'],
        ['key' => 'order_closed_at', 'label' => 'front.order_closed_at'],
        ['key' => 'comment', 'label' => 'common.remark'],
        ['key' => 'modify_time', 'label' => 'common.updated_at'],
    ],
])
@endsection

@section('scripts')
<script src="{{ asset('/js/apps/front/layui/module-page.js') }}?v=2026060701"></script>
@endsection
