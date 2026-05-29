@extends('admin_layui::layouts.app')

@section('title', __('admin.user_detail'))

@section('content')
<div class="layui-card">
    <div class="layui-card-header">{{ __('admin.user_detail') }}</div>
    <div class="layui-card-body">
        <form class="layui-form" id="user-form">
            <div class="layui-form-item">
                <label class="layui-form-label">{{ __('common.name') }}</label>
                <div class="layui-input-block">
                    <input type="text" name="name" value="John Doe" class="layui-input">
                </div>
            </div>
            <div class="layui-form-item">
                <label class="layui-form-label">{{ __('auth.email') }}</label>
                <div class="layui-input-block">
                    <input type="text" name="email" value="john@example.com" readonly class="layui-input">
                </div>
            </div>
            <div class="layui-form-item">
                <div class="layui-input-block">
                    <button class="layui-btn" lay-submit lay-filter="save">{{ __('messages.save') }}</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
