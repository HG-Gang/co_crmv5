@extends('admin_layui::layouts.app')

@section('title', __('front.change_password'))

@section('content')
<div class="layui-card">
    <div class="layui-card-header" data-translate="profile.changePassword">{{ __('front.change_password') }}</div>
    <div class="layui-card-body">
        <form class="layui-form">
            <div class="layui-form-item">
                <label class="layui-form-label" data-translate="auth.oldPassword">{{ __('auth.old_password') }}</label>
                <div class="layui-input-block">
                    <input type="password" name="old_password" required lay-verify="required" class="layui-input">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label" data-translate="auth.newPassword">{{ __('auth.new_password') }}</label>
                <div class="layui-input-block">
                    <input type="password" name="new_password" required lay-verify="required" class="layui-input">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label" data-translate="auth.confirmPassword">{{ __('auth.confirm_password') }}</label>
                <div class="layui-input-block">
                    <input type="password" name="confirm_password" required lay-verify="required" class="layui-input">
                </div>
            </div>
            <div class="layui-form-item">
                <div class="layui-input-block">
                    <button class="layui-btn" lay-submit lay-filter="changePassword" data-translate="common.save">{{ __('common.save') }}</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('/js/admin/layui/profile/change-password.js') }}"></script>
@endsection
