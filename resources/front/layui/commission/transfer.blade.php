{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/19
Time: 22:34
--}}
﻿@extends('front_layui::layouts.app')

@section('title', __('front.commission_transfer'))
@section('breadcrumb', __('breadcrumb.front_commission_transfer'))

@section('content')
@php
    $isLegacyCommissionTransfer = !empty($legacyFormIntentNonce);
    $commissionTransferFields = $isLegacyCommissionTransfer
        ? [
            ['name' => 'depositId', 'label' => 'front.target_user_id', 'type' => 'select', 'verify' => 'required', 'dynamicOptions' => 'direct_agents'],
            ['name' => 'comm_money', 'label' => 'front.amount', 'type' => 'number', 'verify' => 'required|number'],
            ['name' => 'password', 'label' => 'auth.password', 'type' => 'password', 'verify' => 'required'],
            ['name' => 'remark', 'label' => 'common.remark', 'type' => 'textarea', 'width' => 12],
        ]
        : [
            ['name' => 'sub_agent_id', 'label' => 'front.sub_agent_id', 'type' => 'select', 'verify' => 'required', 'dynamicOptions' => 'direct_agents'],
            ['name' => 'amount', 'label' => 'front.amount', 'type' => 'number', 'verify' => 'required|number'],
            ['name' => 'password', 'label' => 'auth.password', 'type' => 'password', 'verify' => 'required'],
            ['name' => 'remark', 'label' => 'common.remark', 'type' => 'textarea', 'width' => 12],
        ];
@endphp
<input
    type="hidden"
    name="idempotency_key"
    value="{{ $legacyFormIntentNonce ?? '' }}"
    data-commission-transfer-intent
>
@include('front_layui::partials.module-page', [
    'pageClass' => 'commission-module commission-transfer-module',
    'titleKey' => 'front.commission_transfer',
    'descriptionKey' => 'front.commission_transfer_desc',
    'api' => '/api/front/commissions/history',
    'method' => 'GET',
    'submitApi' => $isLegacyCommissionTransfer
        ? route('legacy_user_proxy_commission_transfer')
        : '/api/front/commissions/transfers',
    'defaultFilters' => [
        'dataType' => 'transfer',
    ],
    'filters' => [
        ['name' => 'orderId', 'label' => 'front.order_no', 'type' => 'text'],
        ['name' => 'date_from', 'label' => 'front.date_from', 'type' => 'date'],
        ['name' => 'date_to', 'label' => 'front.date_to', 'type' => 'date'],
    ],
    'formFields' => $commissionTransferFields,
    'legacyTargetUserId' => $legacyTargetUserId ?? null,
    'summaryFields' => [
        ['key' => 'commission_amount', 'label' => 'front.amount'],
        ['key' => 'real_amount', 'label' => 'front.real_amount'],
    ],
    'chartGroups' => [
        [
            'target' => 'commissionTransferTrendChart',
            'title' => 'front.commission_transfer_trend',
            'defaultType' => 'bar',
            'fields' => [
                ['key' => 'analytics.ranges.3.commission_amount', 'label' => 'front.last_3_days'],
                ['key' => 'analytics.ranges.7.commission_amount', 'label' => 'front.last_7_days'],
                ['key' => 'analytics.ranges.15.commission_amount', 'label' => 'front.last_15_days'],
                ['key' => 'analytics.ranges.30.commission_amount', 'label' => 'front.last_30_days'],
            ],
        ],
        [
            'target' => 'commissionTransferGenderChart',
            'title' => 'front.commission_transfer_gender_profile',
            'defaultType' => 'pie',
            'fields' => [
                ['key' => 'analytics.gender.male.count_percentage', 'label' => 'register.male'],
                ['key' => 'analytics.gender.female.count_percentage', 'label' => 'register.female'],
                ['key' => 'analytics.gender.unknown.count_percentage', 'label' => 'response.unknown'],
            ],
        ],
        [
            'target' => 'commissionTransferGenderAmountChart',
            'title' => 'front.commission_transfer_amount_profile',
            'defaultType' => 'pie',
            'fields' => [
                ['key' => 'analytics.gender.male.commission_amount', 'label' => 'register.male'],
                ['key' => 'analytics.gender.female.commission_amount', 'label' => 'register.female'],
                ['key' => 'analytics.gender.unknown.commission_amount', 'label' => 'response.unknown'],
            ],
        ],
    ],
    'columns' => [
        ['key' => 'unique_id', 'label' => 'front.unique_id'],
        ['key' => 'agent_id', 'label' => 'front.user_id'],
        ['key' => 'commission_amount', 'label' => 'front.amount', 'format' => 'money'],
        ['key' => 'real_amount', 'label' => 'front.real_amount', 'format' => 'money'],
        ['key' => 'settle_status_text', 'label' => 'front.settle_status'],
        ['key' => 'data_type', 'label' => 'front.flow_type'],
        ['key' => 'remarks', 'label' => 'common.remark'],
        ['key' => 'created_time', 'label' => 'common.created_at'],
    ],
])
@endsection

@section('scripts')
<script src="{{ asset('/js/vendor/echarts/echarts.common.min.js') }}"></script>
<script src="{{ asset('/js/apps/front/layui/module-page.js') }}?v=2026060701"></script>
@endsection
