{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/04
Time: 21:00
--}}
@extends('front_crmui::layouts.auth')

@section('content')
<div class="crmui-auth-card" data-crmui-auth="front-big-number-login">
    <div class="crmui-auth-head">
        <h1>{{ $page['title'] }}</h1>
        <p>{{ $page['subtitle'] }}</p>
    </div>

    <form class="crmui-form" data-crmui-auth-form data-success-url="{{ $page['dashboardUrl'] }}" data-action-url="{{ $page['submitUrl'] }}">
        <input class="crmui-input" type="text" name="account" autocomplete="username" placeholder="{{ __('crmui.fields.account') }}">
        <input class="crmui-input" type="password" name="password" autocomplete="current-password" placeholder="{{ __('crmui.fields.password') }}">
        <button class="crmui-button is-primary is-block" type="submit">{{ __('auth.login') }}</button>
    </form>

    <div class="crmui-auth-links">
        <a href="{{ $page['loginUrl'] }}">{{ __('auth.login') }}</a>
    </div>
</div>
@endsection
