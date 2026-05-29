@extends('front_layui::layouts.app')

@section('title', __('front.edit_profile'))
@section('breadcrumb', __('breadcrumb.front_profile_edit'))

@section('content')
<div class="layui-row layui-col-space15">
    <div class="layui-col-md12">
        <div class="layui-card">
            <div class="layui-card-header" data-translate="profile.editProfile">{{ __('front.edit_profile') }}</div>
            <div class="layui-card-body">
                <form class="layui-form" lay-filter="profileForm">
                    <div class="avatar-upload-panel">
                        <img id="avatarPreview" src="{{ asset('/images/default-avatar.svg') }}" class="avatar-preview" alt="">
                        <div class="avatar-upload-actions">
                            <button type="button" class="layui-btn layui-btn-sm" id="uploadAvatar" data-translate="profile.uploadAvatar">{{ __('user.upload_avatar') }}</button>
                            <button type="button" class="layui-btn layui-btn-sm layui-btn-primary" id="submitAvatar" data-translate="common.save">{{ __('common.save') }}</button>
                        </div>
                    </div>
                    
                    <div class="layui-row layui-col-space10">
                        <div class="layui-col-md6">
                            <div class="layui-form-item">
                                <label class="layui-form-label" data-translate="profile.userName">{{ __('front.user_name') }}</label>
                                <div class="layui-input-block">
                                    <input type="text" name="user_name" required lay-verify="required" class="layui-input">
                                </div>
                            </div>
                        </div>
                        <div class="layui-col-md6">
                            <div class="layui-form-item">
                                <label class="layui-form-label" data-translate="profile.phoneNo">{{ __('front.phone') }}</label>
                                <div class="layui-input-block">
                                    <input type="text" name="phone" class="layui-input">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="layui-row layui-col-space10">
                        <div class="layui-col-md6">
                            <div class="layui-form-item">
                                <label class="layui-form-label" data-translate="profile.genderLabel">{{ __('register.gender') }}</label>
                                <div class="layui-input-block">
                                    <input type="radio" name="gender" value="1" data-translate-title="register.male" title="{{ __('register.male') }}">
                                    <input type="radio" name="gender" value="2" data-translate-title="register.female" title="{{ __('register.female') }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="profile.addressLabel">{{ __('auth.address') }}</label>
                        <div class="layui-input-block">
                            <textarea name="address" class="layui-textarea"></textarea>
                        </div>
                    </div>

                    <div class="layui-form-item form-actions">
                        <button class="layui-btn layui-bg-blue" lay-submit lay-filter="profileSubmit" data-translate="common.save">{{ __('common.save') }}</button>
                        <a href="/front/profile" class="layui-btn layui-btn-primary" data-translate="common.back">{{ __('common.back') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('/js/front/layui/profile/edit.js') }}?v=2026052907"></script>
@endsection
