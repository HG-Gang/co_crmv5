{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/05
Time: 17:44
--}}
@extends('front_crmui::layouts.auth')

@section('content')
<div class="crmui-auth-card" data-crmui-auth="front-forgot-password">
    <div class="crmui-auth-head">
        <h1>{{ $page['title'] }}</h1>
        <p>{{ $page['subtitle'] }}</p>
    </div>

    <form class="crmui-form" data-crmui-auth-form data-success-url="{{ $page['loginUrl'] }}" data-action-url="{{ $page['submitUrl'] }}">
        <input class="crmui-input" type="email" name="email" autocomplete="email" placeholder="{{ __('crmui.fields.email') }}">
        <div class="crmui-inline-action">
            <input class="crmui-input" type="text" name="code" placeholder="{{ __('crmui.fields.email_code') }}">
            <button class="crmui-button" type="button" data-crmui-secondary-action="send-email-code" data-action-url="{{ route('front_api_auth_password_email_code') }}">{{ __('crmui.actions.send_email_code') }}</button>
        </div>
        <input class="crmui-input" type="password" name="password" autocomplete="new-password" placeholder="{{ __('crmui.fields.password') }}">
        <input class="crmui-input" type="password" name="password_confirmation" autocomplete="new-password" placeholder="{{ __('crmui.fields.password_confirmation') }}">
        <button class="crmui-button is-primary is-block" type="submit">{{ __('crmui.actions.submit') }}</button>
    </form>

    <div class="crmui-auth-links">
        <a href="{{ $page['loginUrl'] }}">{{ __('auth.login') }}</a>
    </div>
</div>
@endsection
