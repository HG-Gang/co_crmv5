{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/05
Time: 05:49
--}}
﻿@extends('front_layui::layouts.app')

@section('title', __('front.account_cancel'))
@section('breadcrumb', __('breadcrumb.front_cancel'))

@section('content')
@include('front_layui::partials.module-page', [
    'titleKey' => 'front.account_cancel',
    'descriptionKey' => 'front.account_cancel_desc',
    'api' => '/api/front/account/cancellation',
    'method' => 'GET',
    'submitApi' => '/api/front/account/cancellation-applications',
    'verificationApi' => '/api/front/profile/verification-cancellation-checks',
    'verificationCodeApi' => '/api/front/profile/verification-cancellation/verification-codes',
    'formFields' => [
        ['name' => 'userIdcardNo', 'label' => 'front.id_card_no', 'verify' => 'required'],
        ['name' => 'userphoneNo', 'label' => 'front.phone', 'verify' => 'required'],
        ['name' => 'useremail', 'label' => 'front.email', 'type' => 'email', 'verify' => 'required'],
        ['name' => 'userverfcode', 'label' => 'front.email_code', 'type' => 'verification_code', 'verify' => 'required'],
        ['name' => 'password', 'label' => 'front.password', 'type' => 'password', 'verify' => 'required'],
    ],
    'summaryFields' => [
        ['key' => 'status', 'label' => 'common.status'],
        ['key' => 'cancel_remark', 'label' => 'front.apply_reason'],
        ['key' => 'reject_reason', 'label' => 'front.reject_reason'],
        ['key' => 'created_at', 'label' => 'common.created_at'],
        ['key' => 'updated_at', 'label' => 'common.updated_at'],
    ],
])
@endsection

@section('scripts')
<script src="{{ asset('/js/apps/front/layui/module-page.js') }}?v=2026060701"></script>
@endsection
