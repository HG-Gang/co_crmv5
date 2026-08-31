{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/25
Time: 13:56
--}}
@extends('front_crmui::layouts.auth')

@section('content')
<div class="crmui-auth-card" data-crmui-auth="front-big-agent-login">
    <div class="crmui-auth-head"><h1>{{ $page['title'] }}</h1><p>{{ $page['subtitle'] }}</p></div>
    <form class="crmui-form" data-crmui-auth-form data-crmui-auth-legacy-session="1" data-success-url="{{ $page['dashboardUrl'] }}" data-action-url="{{ $page['submitUrl'] }}">
        <input class="crmui-input" type="text" name="loginUid" autocomplete="username" placeholder="{{ __('crmui.fields.account') }}">
        <input class="crmui-input" type="password" name="loginPassword" autocomplete="current-password" placeholder="{{ __('crmui.fields.password') }}">
        <input type="hidden" name="captcha_key" data-crmui-captcha-key>
        <div class="crmui-inline-action">
            <input class="crmui-input" type="text" name="captcha_code" placeholder="{{ __('crmui.fields.captcha_code') }}">
            <img class="crmui-captcha-image" alt="{{ __('crmui.fields.captcha_code') }}" data-crmui-captcha data-captcha-src="{{ $page['captchaUrl'] }}">
            <button class="crmui-button" type="button" data-crmui-refresh-captcha><i data-lucide="refresh-cw"></i><span>{{ __('crmui.actions.refresh') }}</span></button>
        </div>
        <button class="crmui-button is-primary is-block" type="submit">{{ __('auth.login') }}</button>
    </form>
</div>
@endsection
