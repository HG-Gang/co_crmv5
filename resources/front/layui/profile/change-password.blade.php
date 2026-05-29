@extends('front_layui::layouts.app')

@section('title', __('front.change_password'))
@section('breadcrumb', __('breadcrumb.front_change_pwd'))

@section('content')
<div class="layui-row layui-col-space15">
    <div class="layui-col-md6 layui-col-md-offset3">
        <div class="layui-card">
            <div class="layui-card-header" data-translate="profile.changePassword">{{ __('front.change_password') }}</div>
            <div class="layui-card-body">
                <form class="layui-form" lay-filter="passwordForm">
                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="profile.currentPassword">{{ __('auth.old_password') }}</label>
                        <div class="layui-input-block">
                            <input type="password" name="old_password" required lay-verify="required" class="layui-input">
                        </div>
                    </div>
                    
                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="auth.newPassword">{{ __('auth.new_password') }}</label>
                        <div class="layui-input-block">
                            <input type="password" name="password" required lay-verify="required|password" id="new_password" class="layui-input">
                        </div>
                    </div>
                    
                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="auth.confirmPassword">{{ __('auth.confirm_password') }}</label>
                        <div class="layui-input-block">
                            <input type="password" name="password_confirmation" required lay-verify="required|confirmPass" class="layui-input">
                        </div>
                    </div>
                    
                    <div class="layui-form-item form-actions">
                        <button class="layui-btn layui-bg-blue" lay-submit lay-filter="passwordSubmit" data-translate="common.save">{{ __('common.save') }}</button>
                        <a href="/front/profile" class="layui-btn layui-btn-primary" data-translate="common.back">{{ __('common.back') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('/js/front/layui/profile/change-password.js') }}"></script>
@endsection
