{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/01
Time: 10:41
--}}
﻿@extends('front_layui::layouts.app')

@section('title', __('front.gift_address'))
@section('breadcrumb', __('breadcrumb.front_gift_address'))

@section('content')
@include('front_layui::partials.module-page', [
    'titleKey' => 'front.gift_address',
    'descriptionKey' => 'front.gift_address_desc',
    'api' => '/api/front/gift-addresses',
    'method' => 'GET',
    'submitApi' => '/api/front/gift-addresses',
    'editApi' => '/api/front/gift-addresses/{id}',
    'editMethod' => 'PATCH',
    'legacyAddressId' => $legacyAddressId ?? 0,
    'filters' => [
        ['name' => 'recipient_name', 'label' => 'front.receiver_name', 'type' => 'text'],
        ['name' => 'recipient_phone', 'label' => 'front.phone', 'type' => 'text'],
        ['name' => 'is_default', 'label' => 'front.default_address', 'type' => 'select', 'options' => [
            ['value' => '1', 'label' => 'front.yes'],
            ['value' => '0', 'label' => 'front.no'],
        ]],
        ['name' => 'startdate', 'label' => 'front.date_from', 'type' => 'date'],
        ['name' => 'enddate', 'label' => 'front.date_to', 'type' => 'date'],
    ],
    'formFields' => [
        ['name' => 'recipient_name', 'label' => 'front.receiver_name', 'type' => 'text', 'verify' => 'required'],
        ['name' => 'recipient_phone', 'label' => 'front.phone', 'type' => 'text', 'verify' => 'required'],
        ['name' => 'recipient_address', 'label' => 'front.address', 'type' => 'textarea', 'verify' => 'required', 'width' => 12],
        ['name' => 'is_default', 'label' => 'front.default_address', 'title' => 'front.default_address', 'type' => 'checkbox', 'width' => 12],
    ],
    'columns' => [
        ['key' => 'recipient_name', 'label' => 'front.receiver_name'],
        ['key' => 'recipient_phone', 'label' => 'front.phone', 'total' => false],
        ['key' => 'recipient_address', 'label' => 'front.address', 'total' => false],
        ['key' => 'is_default', 'label' => 'front.default_address'],
    ],
    'rowActions' => [
        ['type' => 'edit', 'label' => 'common.edit', 'style' => 'normal'],
        ['api' => '/api/front/gift-addresses/{id}', 'method' => 'PATCH', 'label' => 'front.set_default', 'confirm' => 'front.confirm_set_default', 'payload' => ['is_default' => 1]],
        ['api' => '/api/front/gift-addresses/{id}', 'method' => 'DELETE', 'label' => 'common.delete', 'confirm' => 'common.confirm_delete', 'style' => 'danger'],
    ],
])
@endsection

@section('scripts')
<script src="{{ asset('/js/apps/front/layui/module-page.js') }}?v=2026060701"></script>
@endsection
