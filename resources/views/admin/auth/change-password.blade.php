@extends('admin.layouts.app')

@section('title', __('auth.change_password'))

@section('content')
<div class="page-header"><h2>{{ __('auth.change_password') }}</h2></div>
<div class="content-card" style="max-width: 500px;">
    <form class="layui-form" action="{{ route('admin.password.update') }}" method="POST">
        @csrf
        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('auth.old_password') }}</label>
            <div class="layui-input-block">
                <input type="password" name="old_password" required lay-verify="required" class="layui-input" autocomplete="current-password">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('auth.new_password') }}</label>
            <div class="layui-input-block">
                <input type="password" name="password" required lay-verify="required" class="layui-input" autocomplete="new-password">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">{{ __('auth.new_password_confirm') }}</label>
            <div class="layui-input-block">
                <input type="password" name="password_confirmation" required lay-verify="required" class="layui-input" autocomplete="new-password">
            </div>
        </div>
        <div class="layui-form-item">
            <div class="layui-input-block">
                <button class="layui-btn" lay-submit>{{ __('auth.submit') }}</button>
            </div>
        </div>
    </form>
</div>
@endsection
