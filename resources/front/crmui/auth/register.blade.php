{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/28
Time: 00:29
--}}
@extends('front_crmui::layouts.auth')

@section('content')
<div class="crmui-auth-card" data-crmui-auth="front-register">
    <div class="crmui-auth-head">
        <h1>{{ $page['title'] }}</h1>
        <p>{{ $page['subtitle'] }}</p>
    </div>

    <form class="crmui-form" data-crmui-auth-form data-crmui-register-form data-password-mismatch="{{ __('validation.confirmed', ['attribute' => __('crmui.fields.password')]) }}" data-success-url="{{ $page['dashboardUrl'] }}" data-action-url="{{ $page['submitUrl'] }}">
        <div class="crmui-choice-row" role="group" aria-label="{{ __('register.account_type') }}">
            <label class="crmui-check">
                <input type="radio" name="account_type" value="1" {{ (int) $accountType === 1 ? 'checked' : '' }}>
                <span>{{ __('register.agent') }}</span>
            </label>
            <label class="crmui-check">
                <input type="radio" name="account_type" value="2" {{ (int) $accountType === 2 ? 'checked' : '' }}>
                <span>{{ __('register.customer') }}</span>
            </label>
        </div>
        <input type="hidden" name="commission_mode" value="{{ $commissionMode }}">
        <input class="crmui-input" type="email" name="email" autocomplete="email" placeholder="{{ __('crmui.fields.email') }}" required>
        <input class="crmui-input" type="text" name="user_name" autocomplete="name" placeholder="{{ __('crmui.fields.name') }}" required>
        <div class="crmui-inline-action">
            <input class="crmui-input" type="text" name="phone_code" autocomplete="tel-country-code" placeholder="{{ __('crmui.fields.phone_code') }}" value="86" required>
            <input class="crmui-input" type="text" name="phone_number" autocomplete="tel-national" placeholder="{{ __('crmui.fields.phone_number') }}" inputmode="numeric" minlength="11" maxlength="20" size="20" pattern="[0-9]{11,20}" required>
        </div>
        <input class="crmui-input" type="text" name="id_card_no" autocomplete="off" placeholder="{{ __('crmui.fields.id_card_no') }}" required>
        <input class="crmui-input" type="password" name="password" autocomplete="new-password" placeholder="{{ __('crmui.fields.password') }}" minlength="6" pattern="[A-Za-z].*[0-9]" required>
        <input class="crmui-input" type="password" name="password_confirmation" autocomplete="new-password" placeholder="{{ __('crmui.fields.password_confirmation') }}" minlength="6" required>
        <input type="hidden" name="captcha_key" data-crmui-captcha-key>
        <div class="crmui-inline-action">
            <input class="crmui-input" type="text" name="captcha_code" placeholder="{{ __('crmui.fields.captcha_code') }}" required>
            <img class="crmui-captcha-image" alt="{{ __('crmui.fields.captcha_code') }}" data-crmui-captcha data-captcha-src="{{ route('front_api_auth_register_captcha') }}">
            <button class="crmui-button" type="button" data-crmui-refresh-captcha>{{ __('crmui.actions.refresh') }}</button>
        </div>
        <input class="crmui-input" type="text" name="inviter_id" value="{{ $inviterId }}" inputmode="numeric" placeholder="{{ __('register.inviter_id') }}" data-crmui-inviter {{ (int) $accountType === 1 ? 'required' : '' }}>
        <label class="crmui-check">
            <input type="checkbox" name="agree_terms" value="1">
            <span>{{ __('crmui.common.agree_terms') }}</span>
        </label>
        <button class="crmui-button is-primary is-block" type="submit">{{ __('auth.register') }}</button>
    </form>

    <div class="crmui-auth-links">
        <a href="{{ $page['loginUrl'] }}">{{ __('auth.login') }}</a>
    </div>
</div>
@endsection
