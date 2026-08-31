{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/07/04
Time: 18:05
--}}
@extends('admin_layui::layouts.app')

@section('title', __('front.edit_profile'))

@section('content')
{{-- 后台个人资料编辑页面：当前登录管理员通过 admin_api_profileInfo 回填资料，通过 admin_api_updateProfile 保存；username 只读，email 可更新，mobile 可更新。 --}}
<div class="layui-card crm-admin-panel">
    <div class="layui-card-header" data-translate="profile.editProfile">{{ __('front.edit_profile') }}</div>
    <div class="layui-card-body">
        <form class="layui-form" lay-filter="profileForm">
            <div class="layui-form-item">
                <label class="layui-form-label" data-translate="profile.userName">{{ __('auth.username') }}</label>
                <div class="layui-input-block">
                    <input type="text" name="username" readonly class="layui-input layui-disabled">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label" data-translate="auth.email">{{ __('auth.email') }}</label>
                <div class="layui-input-block">
                    <input type="text" name="email" required lay-verify="required|email" class="layui-input">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label" data-translate="profile.phoneNo">{{ __('front.phone') }}</label>
                <div class="layui-input-block">
                    <input type="text" name="mobile" class="layui-input">
                </div>
            </div>
            <div class="layui-form-item">
                <div class="layui-input-block">
                    <button class="layui-btn" lay-submit lay-filter="updateProfile" data-translate="common.save">{{ __('common.save') }}</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="profile/edit"></div>
@endsection
