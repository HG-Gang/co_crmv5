{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/04
Time: 17:09
--}}
﻿@extends('front_layui::layouts.app')

@section('title', __('front.voucher'))
@section('breadcrumb', __('breadcrumb.front_voucher'))

@section('content')
@include('front_layui::partials.module-page', [
    'titleKey' => 'front.voucher',
    'descriptionKey' => 'front.voucher_desc',
    'api' => '/api/front/account/vouchers',
    'method' => 'GET',
    'submitApi' => '/api/front/account/voucher-submissions',
    'filters' => [
        ['name' => 'review_status', 'label' => 'front.review_status', 'type' => 'select', 'options' => [
            ['value' => '0', 'label' => 'front.status_pending'],
            ['value' => '1', 'label' => 'front.status_approved'],
            ['value' => '2', 'label' => 'front.status_rejected'],
        ]],
        ['name' => 'startdate', 'label' => 'front.date_from', 'type' => 'date'],
        ['name' => 'enddate', 'label' => 'front.date_to', 'type' => 'date'],
    ],
    'formFields' => [
        ['name' => 'images[]', 'label' => 'front.voucher_images', 'type' => 'file', 'accept' => 'image/*', 'multiple' => true, 'verify' => 'required', 'width' => 12],
        ['name' => 'remarks', 'label' => 'front.remarks', 'type' => 'textarea', 'width' => 12],
    ],
    'columns' => [
        ['key' => 'user_id', 'label' => 'front.user_id'],
        ['key' => 'images', 'label' => 'front.voucher_images'],
        ['key' => 'remarks', 'label' => 'front.remarks'],
        ['key' => 'review_msg', 'label' => 'front.review_message'],
        ['key' => 'review_status', 'label' => 'front.review_status'],
        ['key' => 'rec_crt_date', 'label' => 'common.created_at'],
        ['key' => 'rec_upd_date', 'label' => 'common.updated_at'],
    ],
])
@endsection

@section('scripts')
<script src="{{ asset('/js/apps/front/layui/module-page.js') }}?v=2026060701"></script>
@endsection
