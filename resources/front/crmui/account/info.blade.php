{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/18
Time: 10:32
--}}
@extends('front_crmui::layouts.app')

@section('title', $page['title'] ?? '')

@section('styles')
<link rel="stylesheet" href="{{ asset('/css/front/account-type.css') }}?v=2026072802">
@endsection

@section('content')
@include('partials.front-account-type-switch', [
    'accountTypeChangeUrl' => route('front_api_account_trading_profile_update'),
    'accountTypeChangeMethod' => 'PATCH',
    'accountTypeButtonClass' => 'crmui-button is-primary',
])
@include('front_crmui::partials.module-page', ['page' => $page])
@endsection

@section('scripts')
<script src="{{ asset('/js/apps/front/layui/account-type.js') }}?v=2026081801"></script>
@endsection
