@extends('admin_layui::layouts.app')

@section('title', __('admin.user_detail'))

@section('content')
<div class="layui-fluid">
    <div class="layui-card">
        <div class="layui-card-header" data-translate="common.view">{{ __('admin.user_detail') }}</div>
        <div class="layui-card-body">
            <form class="layui-form" id="user-form" lay-filter="user-form">
                <input type="hidden" name="user_id" id="user-id" value="{{ $userId ?? '' }}">
                <div class="layui-form-item">
                    <label class="layui-form-label" data-translate="user.userName">{{ __('auth.username') }}</label>
                    <div class="layui-input-block">
                        <input type="text" name="user_name" required lay-verify="required" data-translate-placeholder="auth.usernamePlaceholder" placeholder="{{ __('auth.username') }}" autocomplete="off" class="layui-input">
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label" data-translate="user.email">{{ __('auth.email') }}</label>
                    <div class="layui-input-block">
                        <input type="email" name="email" required lay-verify="required|email" data-translate-placeholder="auth.emailPlaceholder" placeholder="{{ __('auth.email') }}" autocomplete="off" class="layui-input">
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label" data-translate="user.phone">{{ __('front.phone') }}</label>
                    <div class="layui-input-block">
                        <input type="text" name="phone" placeholder="{{ __('front.phone') }}" autocomplete="off" class="layui-input">
                    </div>
                </div>
                <div class="layui-form-item">
                    <label class="layui-form-label" data-translate="user.status">{{ __('common.status') }}</label>
                    <div class="layui-input-block">
                        <select name="status" lay-filter="status-select">
                            <option value="1" data-translate="user.enabled">{{ __('common.enabled') }}</option>
                            <option value="0" data-translate="user.disabled">{{ __('common.disabled') }}</option>
                        </select>
                    </div>
                </div>
                <div class="layui-form-item">
                    <div class="layui-input-block">
                        <button class="layui-btn layui-btn-normal" lay-submit lay-filter="user-save" data-translate="common.save">{{ __('common.save') }}</button>
                        <button type="button" class="layui-btn layui-btn-primary" id="cancel-btn" data-translate="common.cancel">{{ __('common.cancel') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('/js/admin/layui/users/detail.js') }}"></script>
@endsection
