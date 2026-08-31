{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/08
Time: 01:10
--}}
@extends('front_crmui::layouts.auth')

@section('content')
<div class="crmui-auth-card" data-crmui-auth="front-register">
    <div class="crmui-auth-head">
        <h1>{{ $page['title'] }}</h1>
        <p>{{ $page['subtitle'] }}</p>
    </div>

    <form class="crmui-form" data-crmui-auth-form data-success-url="{{ $page['dashboardUrl'] }}" data-action-url="{{ $page['submitUrl'] }}">
        <input class="crmui-input" type="email" name="email" autocomplete="email" placeholder="{{ __('crmui.fields.email') }}">
        <input class="crmui-input" type="text" name="user_name" autocomplete="name" placeholder="{{ __('crmui.fields.name') }}">
        <input class="crmui-input" type="text" name="phone" autocomplete="tel" placeholder="{{ __('crmui.fields.phone') }}">
        <input class="crmui-input" type="password" name="password" autocomplete="new-password" placeholder="{{ __('crmui.fields.password') }}">
        <input class="crmui-input" type="password" name="password_confirmation" autocomplete="new-password" placeholder="{{ __('crmui.fields.password_confirmation') }}">
        @if(!empty($inviterId))
            <input type="hidden" name="inviter_id" value="{{ $inviterId }}">
        @endif
        <button class="crmui-button is-primary is-block" type="submit">{{ __('auth.register') }}</button>
    </form>

    <div class="crmui-auth-links">
        <a href="{{ $page['loginUrl'] }}">{{ __('auth.login') }}</a>
    </div>
</div>
@endsection
