@extends('admin_layui::layouts.app')

@section('title', __('front.edit_profile'))

@section('content')
<div class="layui-card">
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
<script src="{{ asset('/js/admin/layui/profile/edit.js') }}"></script>
@endsection
