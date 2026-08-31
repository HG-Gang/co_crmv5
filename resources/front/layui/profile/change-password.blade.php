{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/23
Time: 05:08
--}}
@extends(!empty($legacyBigAgent) ? 'front_layui::legacy-big-agent.layout' : 'front_layui::layouts.app')

@php
    $isLegacyBigAgent = !empty($legacyBigAgent);
    $passwordEndpoint = $isLegacyBigAgent ? url('/user/agents/changePassword') : '/api/front/profile/password';
    $loginUrl = $isLegacyBigAgent ? route('agentsLogin') : route('front_page_login');
    $backUrl = $isLegacyBigAgent ? route('legacy_user_agents_index') : route('front_page_profile');
@endphp

@section('title', __('front.change_password'))
@section('breadcrumb', __('breadcrumb.front_change_pwd'))

@section('content')
<div class="layui-row layui-col-space15">
    <div class="layui-col-md6 layui-col-md-offset3">
        <div class="layui-card">
            <div class="layui-card-header" data-translate="profile.changePassword">{{ __('front.change_password') }}</div>
            <div class="layui-card-body">
                <form class="layui-form" lay-filter="passwordForm">
                    @csrf
                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="profile.currentPassword">{{ __('auth.old_password') }}</label>
                        <div class="layui-input-block">
                            <input type="password" name="old_password" required lay-verify="profileRequired" class="layui-input">
                        </div>
                    </div>
                    
                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="auth.newPassword">{{ __('auth.new_password') }}</label>
                        <div class="layui-input-block">
                            <input type="password" name="password" required lay-verify="profileRequired|password" id="new_password" class="layui-input">
                        </div>
                    </div>
                    
                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="auth.confirmPassword">{{ __('auth.confirm_password') }}</label>
                        <div class="layui-input-block">
                            <input type="password" name="password_confirmation" required lay-verify="profileRequired|confirmPass" class="layui-input">
                        </div>
                    </div>
                    
                    <div class="layui-form-item form-actions">
                        <button class="layui-btn layui-bg-blue" lay-submit lay-filter="passwordSubmit" data-translate="common.save">{{ __('common.save') }}</button>
                        <a href="{{ $backUrl }}" class="layui-btn layui-btn-primary" data-translate="common.back">{{ __('common.back') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<div hidden
     data-layui-page="profile/change-password"
     data-password-endpoint="{{ $passwordEndpoint }}"
     data-legacy-big-agent="{{ $isLegacyBigAgent ? '1' : '0' }}"
     data-login-url="{{ $loginUrl }}"></div>
@endsection
