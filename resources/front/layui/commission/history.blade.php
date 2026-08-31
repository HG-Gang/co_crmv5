{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/04
Time: 17:09
--}}
﻿@extends('front_layui::layouts.app')

@section('title', __('front.commission_history'))
@section('breadcrumb', __('breadcrumb.front_commission_hist'))

@section('content')
@include('front_layui::partials.module-page', [
    'pageClass' => 'commission-module commission-history-module',
    'titleKey' => 'front.commission_history',
    'descriptionKey' => 'front.commission_history_desc',
    'api' => '/api/front/commissions/history',
    'method' => 'GET',
    'filters' => [
        ['name' => 'orderId', 'label' => 'front.order_no', 'type' => 'text'],
        ['name' => 'startdate', 'label' => 'front.date_from', 'type' => 'date'],
        ['name' => 'enddate', 'label' => 'front.date_to', 'type' => 'date'],
    ],
    'summaryFields' => [
        ['key' => 'commission_amount', 'label' => 'front.commission'],
        ['key' => 'returned_amount', 'label' => 'front.returned_amount'],
        ['key' => 'real_amount', 'label' => 'front.real_amount'],
        ['key' => 'agent_volume', 'label' => 'front.total_volume'],
    ],
    'chartGroups' => [
        [
            'target' => 'commissionTrendChart',
            'title' => 'front.commission_trend',
            'defaultType' => 'bar',
            'fields' => [
                ['key' => 'analytics.ranges.3.commission_amount', 'label' => 'front.last_3_days'],
                ['key' => 'analytics.ranges.7.commission_amount', 'label' => 'front.last_7_days'],
                ['key' => 'analytics.ranges.15.commission_amount', 'label' => 'front.last_15_days'],
                ['key' => 'analytics.ranges.30.commission_amount', 'label' => 'front.last_30_days'],
            ],
        ],
        [
            'target' => 'commissionGenderChart',
            'title' => 'front.commission_gender_count_profile',
            'defaultType' => 'pie',
            'fields' => [
                ['key' => 'analytics.gender.male.count_percentage', 'label' => 'register.male'],
                ['key' => 'analytics.gender.female.count_percentage', 'label' => 'register.female'],
                ['key' => 'analytics.gender.unknown.count_percentage', 'label' => 'response.unknown'],
            ],
        ],
        [
            'target' => 'commissionGenderAmountChart',
            'title' => 'front.commission_gender_amount_profile',
            'defaultType' => 'pie',
            'fields' => [
                ['key' => 'analytics.gender.male.commission_amount', 'label' => 'register.male'],
                ['key' => 'analytics.gender.female.commission_amount', 'label' => 'register.female'],
                ['key' => 'analytics.gender.unknown.commission_amount', 'label' => 'response.unknown'],
            ],
        ],
        [
            'target' => 'commissionGenderAmountRatioChart',
            'title' => 'front.commission_gender_profile',
            'defaultType' => 'area',
            'fields' => [
                ['key' => 'analytics.gender.male.commission_percentage', 'label' => 'register.male'],
                ['key' => 'analytics.gender.female.commission_percentage', 'label' => 'register.female'],
                ['key' => 'analytics.gender.unknown.commission_percentage', 'label' => 'response.unknown'],
            ],
        ],
    ],
    'columns' => [
        ['key' => 'unique_id', 'label' => 'front.unique_id'],
        ['key' => 'agent_id', 'label' => 'front.user_id'],
        ['key' => 'order_no', 'label' => 'front.order_no'],
        ['key' => 'commission_amount', 'label' => 'front.commission', 'format' => 'money'],
        ['key' => 'returned_amount', 'label' => 'front.returned_amount', 'format' => 'money'],
        ['key' => 'real_amount', 'label' => 'front.real_amount', 'format' => 'money'],
        ['key' => 'settle_status_text', 'label' => 'front.settle_status'],
        ['key' => 'comment', 'label' => 'common.remark'],
        ['key' => 'data_type', 'label' => 'front.flow_type'],
        ['key' => 'created_time', 'label' => 'common.created_at'],
        ['key' => 'modify_time', 'label' => 'common.updated_at'],
    ],
])
@endsection

@section('scripts')
<script src="{{ asset('/js/vendor/echarts/echarts.common.min.js') }}"></script>
<script src="{{ asset('/js/apps/front/layui/module-page.js') }}?v=2026060701"></script>
@endsection
