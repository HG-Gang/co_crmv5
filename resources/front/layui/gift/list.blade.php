{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/05
Time: 05:12
--}}
﻿@extends('front_layui::layouts.app')

@section('title', __('front.gift_list'))
@section('breadcrumb', __('breadcrumb.front_gift_list'))

@section('content')
@include('front_layui::partials.module-page', [
    'titleKey' => 'front.gift_list',
    'descriptionKey' => 'front.gift_list_desc',
    'api' => '/api/front/gifts',
    'method' => 'GET',
    'listKey' => 'shipped_gifts',
    'filters' => [
        ['name' => 'recipient_name', 'label' => 'front.recipient_name', 'type' => 'text'],
        ['name' => 'gift_name', 'label' => 'front.gift_name', 'type' => 'text'],
        ['name' => 'startdate', 'label' => 'front.date_from', 'type' => 'date'],
        ['name' => 'enddate', 'label' => 'front.date_to', 'type' => 'date'],
    ],
    'columns' => [
        ['key' => 'gift_name', 'label' => 'front.gift_name'],
        ['key' => 'recipient_name', 'label' => 'front.recipient_name'],
        ['key' => 'recipient_phone', 'label' => 'front.recipient_phone', 'total' => false],
        ['key' => 'recipient_address', 'label' => 'front.recipient_address', 'total' => false],
        ['key' => 'sender_name', 'label' => 'front.sender_name'],
        ['key' => 'gift_quantity', 'label' => 'front.gift_quantity'],
        ['key' => 'remark', 'label' => 'front.remark', 'total' => false],
        ['key' => 'shipped_at', 'label' => 'front.shipped_at', 'total' => false],
    ],
])
@endsection

@section('scripts')
<script src="{{ asset('/js/apps/front/layui/module-page.js') }}?v=2026060701"></script>
@endsection
