@extends('front_layui::layouts.app')

@section('title', __('front.change_email'))
@section('breadcrumb', __('breadcrumb.front_change_email'))

@section('content')
<div class="layui-row layui-col-space15">
    <div class="layui-col-md6 layui-col-md-offset3">
        <div class="layui-card">
            <div class="layui-card-header" data-translate="profile.changeEmail">{{ __('front.change_email') }}</div>
            <div class="layui-card-body">
                <form class="layui-form" lay-filter="emailForm">
                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="profile.newEmail">{{ __('profile.new_email') }}</label>
                        <div class="layui-input-block">
                            <input type="email" name="email" required lay-verify="required|email" class="layui-input">
                        </div>
                    </div>
                    
                    <div class="layui-form-item form-actions">
                        <button class="layui-btn layui-bg-blue" lay-submit lay-filter="emailSubmit" data-translate="common.save">{{ __('common.save') }}</button>
                        <a href="/front/profile" class="layui-btn layui-btn-primary" data-translate="common.back">{{ __('common.back') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('/js/front/layui/profile/change-email.js') }}"></script>
@endsection
