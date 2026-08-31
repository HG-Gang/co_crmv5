{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/28
Time: 01:23
--}}
@extends('front_layui::layouts.app')

@section('title', __('front.edit_profile'))
@section('breadcrumb', __('breadcrumb.front_profile_edit'))

@section('content')
<div class="profile-page crm-profile-shell">
    <div class="layui-card">
        <div class="layui-card-body">
            <div class="profile-head crm-profile-hero">
                <div data-crm-upload="avatar" data-upload-accept="images" data-upload-exts="jpg|jpeg|png|gif|webp" data-upload-max-size="10240" data-upload-auto="0" data-upload-multiple="0" class="profile-upload-field crm-profile-upload-field crm-profile-avatar-block crm-upload-shell" data-upload-field="avatar">
                    <div class="crm-profile-avatar-wrap">
                        <img id="avatarPreview" src="{{ asset('/images/default-avatar.svg') }}" class="avatar-preview crm-profile-avatar" data-upload-preview="avatar" alt="">
                        <button type="button" class="layui-upload-drag profile-upload-card profile-avatar-upload-card crm-profile-upload-card crm-profile-avatar-upload-card crm-profile-avatar-action" id="uploadAvatar" aria-label="{{ __('user.upload_avatar') }}"><i data-lucide="camera"></i><span data-translate="profile.uploadShort">{{ __('profile.upload_short') }}</span></button>
                    </div>
                    <button type="button" class="profile-upload-clear crm-profile-upload-clear" id="clearAvatar" data-upload-clear="avatar" title="{{ __('common.reset') }}"><i data-lucide="x"></i></button>
                    <span class="profile-upload-meta crm-profile-upload-meta" id="avatarUploadMeta">
                        <b data-upload-name="avatar">-</b>
                        <em data-upload-size="avatar">-</em>
                        <em class="profile-upload-status crm-profile-upload-status crm-upload-status" data-upload-status="avatar" data-translate="front.no_file_selected">{{ __('front.no_file_selected') }}</em>
                        {{-- 头像即时上传：进度条与行内错误由共享上传组件统一渲染。 --}}
                    </span>
                    <div class="crm-upload-progress" data-upload-progress role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="{{ __('front.upload_uploading') }}"><div class="crm-upload-progress-bar" data-upload-progress-bar></div></div>
                    <p class="crm-field-error" data-error-for="avatar" role="alert" aria-live="assertive"></p>
                </div>
                <div class="profile-head-main crm-profile-hero-main">
                    <h2 class="profile-name" id="profileName" data-translate="profile.editProfile">{{ __('front.edit_profile') }}</h2>
                    <div class="profile-sensitive crm-profile-sensitive">
                        <span><span data-translate="front.user_id">{{ __('front.user_id') }}</span>: <strong id="profileUserId">-</strong></span>
                        <span><span data-translate="front.phone">{{ __('front.phone') }}</span>: <strong id="profilePhoneMasked">-</strong></span>
                        <span><span data-translate="front.email">{{ __('front.email') }}</span>: <strong id="profileEmailMasked">-</strong></span>
                        <span><span data-translate="front.id_card_no">{{ __('front.id_card_no') }}</span>: <strong id="profileIdCardMasked">-</strong></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="layui-card">
        <div class="layui-card-body">
            <div class="profile-section-title" data-translate="profile.editProfile">{{ __('front.edit_profile') }}</div>
                <form class="layui-form layui-form-pane" lay-filter="profileForm">
                    <div class="layui-row layui-col-space10">
                        <div class="layui-col-md6 layui-col-sm12">
                            <div class="layui-form-item">
                                <label class="layui-form-label" data-translate="profile.userName">{{ __('front.user_name') }}</label>
                                <div class="layui-input-block">
                                    <input type="text" name="user_name" required lay-verify="profileRequired" class="layui-input">
                                </div>
                            </div>
                        </div>
                        <div class="layui-col-md6 layui-col-sm12">
                            <div class="layui-form-item">
                                <label class="layui-form-label" data-translate="profile.phoneNo">{{ __('front.phone') }}</label>
                                <div class="layui-input-block">
                                    <input type="text" name="phone" class="layui-input">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="layui-row layui-col-space10">
                        <div class="layui-col-md6 layui-col-sm12">
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
                        <a href="{{ route('front_page_profile') }}" class="layui-btn layui-btn-primary" data-translate="common.back">{{ __('common.back') }}</a>
                    </div>
                </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="profile/edit"></div>
@endsection
