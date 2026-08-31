{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/28
Time: 01:23
--}}
@extends('front_layui::layouts.app')

@section('title', __('front.profile'))
@section('breadcrumb', __('breadcrumb.front_profile_info'))

@section('content')
<div class="profile-page crm-profile-shell crm-visual-page">
    <div class="layui-card">
        <div class="layui-card-body">
            <div class="profile-head crm-profile-hero">
                <div data-crm-upload="avatar" data-upload-accept="images" data-upload-exts="jpg|jpeg|png|gif|webp" data-upload-max-size="10240" data-upload-auto="0" data-upload-multiple="0" class="profile-upload-field crm-profile-upload-field crm-profile-avatar-block crm-upload-shell" data-upload-field="avatar">
                    <div class="crm-profile-avatar-wrap">
                        <img id="avatarPreview" src="{{ asset('/images/default-avatar.svg') }}" class="avatar-preview crm-profile-avatar" data-upload-preview="avatar" alt="">
                        <button type="button" class="layui-upload-drag profile-upload-card profile-avatar-upload-card crm-profile-upload-card crm-profile-avatar-upload-card crm-profile-avatar-action" id="selectAvatar" aria-label="{{ __('user.upload_avatar') }}"><i data-lucide="camera"></i><span data-translate="profile.uploadShort">{{ __('profile.upload_short') }}</span></button>
                    </div>
                    <button type="button" class="profile-upload-clear crm-profile-upload-clear" data-upload-clear="avatar" title="{{ __('common.reset') }}"><i data-lucide="x"></i></button>
                    <span class="profile-upload-meta crm-profile-upload-meta">
                        <b data-upload-name="avatar">-</b>
                        <em data-upload-size="avatar">-</em>
                        <em class="profile-upload-status crm-profile-upload-status crm-upload-status" data-upload-status="avatar" data-translate="front.no_file_selected">{{ __('front.no_file_selected') }}</em>
                        {{-- 头像即时上传：进度条与行内错误由共享上传组件统一渲染。 --}}
                    </span>
                    <div class="crm-upload-progress" data-upload-progress role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="{{ __('front.upload_uploading') }}"><div class="crm-upload-progress-bar" data-upload-progress-bar></div></div>
                    <p class="crm-field-error" data-error-for="avatar" role="alert" aria-live="assertive"></p>
                </div>
                <div class="profile-head-main crm-profile-hero-main">
                    <h2 class="profile-name" id="profileName">{{ __('common.loading') }}</h2>
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
            <div class="profile-section-title" data-translate="profile.title">{{ __('front.profile') }}</div>
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
                                <input type="text" id="profilePhoneReadonly" class="layui-input" readonly autocomplete="off">
                            </div>
                        </div>
                    </div>
                    <div class="layui-col-md6 layui-col-sm12">
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="front.id_card_no">{{ __('front.id_card_no') }}</label>
                            <div class="layui-input-block">
                                <input type="text" id="profileIdCardReadonly" class="layui-input" readonly autocomplete="off">
                            </div>
                        </div>
                    </div>
                    <div class="layui-col-md6 layui-col-sm12">
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="profile.genderLabel">{{ __('register.gender') }}</label>
                            <div class="layui-input-block">
                                <input type="radio" name="gender" value="1" data-translate-title="register.male" title="{{ __('register.male') }}">
                                <input type="radio" name="gender" value="2" data-translate-title="register.female" title="{{ __('register.female') }}">
                            </div>
                        </div>
                    </div>
                    <div class="layui-col-md12">
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="profile.addressLabel">{{ __('auth.address') }}</label>
                            <div class="layui-input-block">
                                <textarea name="address" class="layui-textarea"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="profile-actions">
                    <button class="layui-btn layui-bg-blue" lay-submit lay-filter="profileSubmit" data-translate="common.save">{{ __('common.save') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="layui-row layui-col-space15">
        <div class="layui-col-md6 layui-col-sm12">
            <div class="layui-card">
                <div class="layui-card-body">
                    <div class="profile-section-title" data-translate="profile.changePassword">{{ __('front.change_password') }}</div>
                    <form class="layui-form layui-form-pane" lay-filter="passwordForm">
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="profile.currentPassword">{{ __('auth.old_password') }}</label>
                            <div class="layui-input-block">
                                <input type="password" name="old_password" required lay-verify="profileRequired" class="layui-input">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="auth.newPassword">{{ __('auth.new_password') }}</label>
                            <div class="layui-input-block">
                                <input type="password" name="password" required lay-verify="profileRequired|password" id="new_password" class="layui-input">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="auth.confirmPassword">{{ __('auth.confirm_password') }}</label>
                            <div class="layui-input-block">
                                <input type="password" name="password_confirmation" required lay-verify="profileRequired|confirmPass" class="layui-input">
                            </div>
                        </div>
                        <div class="profile-actions">
                            <button class="layui-btn layui-bg-blue" lay-submit lay-filter="passwordSubmit" data-translate="common.save">{{ __('common.save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="layui-col-md6 layui-col-sm12">
            <div class="layui-card">
                <div class="layui-card-body">
                    <div class="profile-section-title" data-translate="profile.changeEmail">{{ __('front.change_email') }}</div>
                    <form class="layui-form layui-form-pane" lay-filter="emailForm">
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="profile.fullPhone">{{ __('profile.full_phone') }}</label>
                            <div class="layui-input-block">
                                <input type="text" name="verify_phone" required lay-verify="profileRequired" class="layui-input" autocomplete="off">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="profile.currentEmail">{{ __('profile.current_email') }}</label>
                            <div class="layui-input-block">
                                <input type="email" name="current_email" required lay-verify="profileRequired|email" class="layui-input" autocomplete="off">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="profile.newEmail">{{ __('profile.new_email') }}</label>
                            <div class="layui-input-block">
                                <input type="email" name="new_email" required lay-verify="profileRequired|email" class="layui-input" autocomplete="off">
                            </div>
                        </div>
                        {{-- 邮箱属于登录凭据，修改时必须同时提交当前密码和绑定新邮箱的一次性验证码。 --}}
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="profile.currentPassword">{{ __('profile.current_password') }}</label>
                            <div class="layui-input-block">
                                <input type="password" name="password" required lay-verify="profileRequired" class="layui-input" autocomplete="current-password">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="profile.verificationCode">{{ __('profile.verification_code') }}</label>
                            <div class="layui-input-block">
                                <div class="layui-input-group">
                                    <input type="text" name="verification_code" required lay-verify="profileRequired" class="layui-input" inputmode="numeric" maxlength="6" autocomplete="one-time-code">
                                    <div class="layui-input-suffix">
                                        <button type="button" class="layui-btn layui-btn-primary" id="sendEmailChangeCodeBtn">
                                            <i data-lucide="send" aria-hidden="true"></i>
                                            <span data-translate="profile.sendCode">{{ __('profile.send_code') }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="profile-actions">
                            <button class="layui-btn layui-bg-blue" lay-submit lay-filter="emailSubmit" data-translate="common.save">{{ __('common.save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="layui-row layui-col-space15">
        <div class="layui-col-md6 layui-col-sm12">
            <div class="layui-card">
                <div class="layui-card-body">
                    <div class="profile-section-title">
                        <span data-translate="profile.changePhone">{{ __('front.phone') }}</span>
                    </div>
                    <form class="layui-form layui-form-pane" lay-filter="phoneForm">
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="profile.fullPhone">{{ __('profile.full_phone') }}</label>
                            <div class="layui-input-block">
                                <input type="text" name="verify_phone" required lay-verify="profileRequired" class="layui-input" autocomplete="off">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="profile.currentEmail">{{ __('profile.current_email') }}</label>
                            <div class="layui-input-block">
                                <input type="email" name="verify_email" required lay-verify="profileRequired|email" class="layui-input" autocomplete="off">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="profile.currentPassword">{{ __('profile.current_password') }}</label>
                            <div class="layui-input-block">
                                <input type="password" name="password" required lay-verify="profileRequired" class="layui-input" autocomplete="current-password">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="profile.newPhone">{{ __('front.phone') }}</label>
                            <div class="layui-input-block">
                                <input type="text" name="new_phone" required lay-verify="profileRequired" class="layui-input" autocomplete="off">
                            </div>
                        </div>
                        <div class="profile-actions">
                            <button class="layui-btn layui-bg-blue" lay-submit lay-filter="phoneSubmit" data-translate="common.save">{{ __('common.save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="layui-col-md6 layui-col-sm12">
            <div class="layui-card">
                <div class="layui-card-body">
                    <div class="profile-section-title">
                        <span data-translate="profile.identityAudit">{{ __('front.id_card_no') }}</span>
                        <span class="layui-badge layui-bg-gray" id="idCardStatusText">-</span>
                    </div>
                    <form id="identityForm" class="layui-form layui-form-pane" lay-filter="identityForm" enctype="multipart/form-data">
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="front.id_card_no">{{ __('front.id_card_no') }}</label>
                            <div class="layui-input-block">
                                <input type="text" name="id_card_no" required lay-verify="profileRequired" class="layui-input" autocomplete="off">
                            </div>
                        </div>
                        {{-- 身份证正反面成对上传：与银行卡共用同一套共享 Layui 上传组件。 --}}
                        <div class="layui-form-item crm-upload-pair" role="group" aria-label="{{ __('profile.id_card_images') }}">
                            <div class="crm-upload-pair-slot">
                                <label class="layui-form-label" data-translate="profile.idCardFront">{{ __('profile.id_card_front') }}</label>
                                <div class="layui-input-block profile-upload-field crm-profile-upload-field is-card-upload crm-upload-shell" data-upload-field="id_card_front" data-crm-upload="id_card_front" data-upload-accept="images" data-upload-exts="jpg|jpeg|png|gif|webp" data-upload-max-size="10240" data-upload-auto="0" data-upload-multiple="0">
                                    <button type="button" class="layui-upload-drag profile-upload-card crm-profile-upload-card crm-upload-action" id="idCardFrontBtn" data-upload-trigger aria-label="{{ __('profile.upload_front') }}">
                                        <i data-lucide="cloud-upload" aria-hidden="true"></i>
                                        <span class="crm-upload-drag-text" data-translate="profile.uploadFront">{{ __('profile.upload_front') }}</span>
                                        <span class="crm-upload-drag-hint" data-translate="front.upload_choose_or_drag">{{ __('front.upload_choose_or_drag') }}</span>
                                    </button>
                                    <button type="button" class="profile-upload-clear crm-profile-upload-clear crm-upload-clear" data-upload-clear="id_card_front" title="{{ __('front.upload_remove') }}" aria-label="{{ __('front.upload_remove') }}"><i data-lucide="x" aria-hidden="true"></i></button>
                                    <img id="idCardFrontPreview" class="profile-upload-preview crm-profile-upload-preview" data-upload-preview="id_card_front" data-image-preview="" role="button" tabindex="0" alt="">
                                    <div class="crm-upload-progress" data-upload-progress role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="{{ __('front.upload_uploading') }}"><div class="crm-upload-progress-bar" data-upload-progress-bar></div></div>
                                    <span class="profile-upload-meta crm-profile-upload-meta crm-upload-meta">
                                        <b data-upload-name="id_card_front">-</b>
                                        <em data-upload-size="id_card_front" data-upload-size-text="id_card_front">-</em>
                                        <em class="profile-upload-status crm-profile-upload-status crm-upload-status" data-upload-status="id_card_front" data-translate="front.no_file_selected">{{ __('front.no_file_selected') }}</em>
                                    </span>
                                    <p class="crm-field-error" data-error-for="id_card_front" role="alert" aria-live="assertive"></p>
                                </div>
                            </div>
                            <div class="crm-upload-pair-slot">
                                <label class="layui-form-label" data-translate="profile.idCardBack">{{ __('profile.id_card_back') }}</label>
                                <div class="layui-input-block profile-upload-field crm-profile-upload-field is-card-upload crm-upload-shell" data-upload-field="id_card_back" data-crm-upload="id_card_back" data-upload-accept="images" data-upload-exts="jpg|jpeg|png|gif|webp" data-upload-max-size="10240" data-upload-auto="0" data-upload-multiple="0">
                                    <button type="button" class="layui-upload-drag profile-upload-card crm-profile-upload-card crm-upload-action" id="idCardBackBtn" data-upload-trigger aria-label="{{ __('profile.upload_back') }}">
                                        <i data-lucide="cloud-upload" aria-hidden="true"></i>
                                        <span class="crm-upload-drag-text" data-translate="profile.uploadBack">{{ __('profile.upload_back') }}</span>
                                        <span class="crm-upload-drag-hint" data-translate="front.upload_choose_or_drag">{{ __('front.upload_choose_or_drag') }}</span>
                                    </button>
                                    <button type="button" class="profile-upload-clear crm-profile-upload-clear crm-upload-clear" data-upload-clear="id_card_back" title="{{ __('front.upload_remove') }}" aria-label="{{ __('front.upload_remove') }}"><i data-lucide="x" aria-hidden="true"></i></button>
                                    <img id="idCardBackPreview" class="profile-upload-preview crm-profile-upload-preview" data-upload-preview="id_card_back" data-image-preview="" role="button" tabindex="0" alt="">
                                    <div class="crm-upload-progress" data-upload-progress role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="{{ __('front.upload_uploading') }}"><div class="crm-upload-progress-bar" data-upload-progress-bar></div></div>
                                    <span class="profile-upload-meta crm-profile-upload-meta crm-upload-meta">
                                        <b data-upload-name="id_card_back">-</b>
                                        <em data-upload-size="id_card_back" data-upload-size-text="id_card_back">-</em>
                                        <em class="profile-upload-status crm-profile-upload-status crm-upload-status" data-upload-status="id_card_back" data-translate="front.no_file_selected">{{ __('front.no_file_selected') }}</em>
                                    </span>
                                    <p class="crm-field-error" data-error-for="id_card_back" role="alert" aria-live="assertive"></p>
                                </div>
                            </div>
                        </div>
                        <div class="profile-actions">
                            <button class="layui-btn layui-bg-blue" lay-submit lay-filter="identitySubmit" data-translate="common.submit">{{ __('common.submit') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="layui-row layui-col-space15">
        <div class="layui-col-md6 layui-col-sm12">
            <div class="layui-card">
                <div class="layui-card-body">
                    <div class="profile-section-title">
                        <span data-translate="profile.bankAudit">{{ __('front.bank_name') }}</span>
                        <span class="layui-badge layui-bg-gray" id="bankStatusText">-</span>
                    </div>
                    <form class="layui-form layui-form-pane" lay-filter="bankForm" enctype="multipart/form-data">
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="front.bank_name">{{ __('front.bank_name') }}</label>
                            <div class="layui-input-block">
                                <input type="text" name="bank_name" required lay-verify="profileRequired" class="layui-input" autocomplete="off">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="front.bank_no">{{ __('front.bank_no') }}</label>
                            <div class="layui-input-block">
                                <input type="text" name="bank_no" required lay-verify="profileRequired" class="layui-input" autocomplete="off">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="profile.bankAddress">{{ __('auth.address') }}</label>
                            <div class="layui-input-block">
                                <input type="text" name="bank_addr" required lay-verify="profileRequired" class="layui-input" autocomplete="off">
                            </div>
                        </div>
                        {{-- 银行卡正反面成对上传：两个槽位共用 public/js/shared/layui-upload.js 与 public/css/common/crm-upload.css，
                             包含拖拽区、缩略图预览、上传进度条、移除/重选按钮和行内错误提示。 --}}
                        <div class="layui-form-item crm-upload-pair" role="group" aria-label="{{ __('profile.bank_card_images') }}">
                            <div class="crm-upload-pair-slot">
                                <label class="layui-form-label" data-translate="profile.bankCardFront">{{ __('profile.bank_card_front') }}</label>
                                <div class="layui-input-block profile-upload-field crm-profile-upload-field is-card-upload crm-upload-shell" data-upload-field="bank_card_img" data-crm-upload="bank_card_img" data-upload-accept="images" data-upload-exts="jpg|jpeg|png|gif|webp" data-upload-max-size="10240" data-upload-auto="0" data-upload-multiple="0">
                                    <button type="button" class="layui-upload-drag profile-upload-card crm-profile-upload-card crm-upload-action" id="bankCardImgBtn" data-upload-trigger aria-label="{{ __('profile.upload_front') }}">
                                        <i data-lucide="cloud-upload" aria-hidden="true"></i>
                                        <span class="crm-upload-drag-text" data-translate="profile.uploadFront">{{ __('profile.upload_front') }}</span>
                                        <span class="crm-upload-drag-hint" data-translate="front.upload_choose_or_drag">{{ __('front.upload_choose_or_drag') }}</span>
                                    </button>
                                    <button type="button" class="profile-upload-clear crm-profile-upload-clear crm-upload-clear" data-upload-clear="bank_card_img" title="{{ __('front.upload_remove') }}" aria-label="{{ __('front.upload_remove') }}"><i data-lucide="x" aria-hidden="true"></i></button>
                                    <img id="bankCardImgPreview" class="profile-upload-preview crm-profile-upload-preview" data-upload-preview="bank_card_img" data-image-preview="" role="button" tabindex="0" alt="">
                                    <div class="crm-upload-progress" data-upload-progress role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="{{ __('front.upload_uploading') }}"><div class="crm-upload-progress-bar" data-upload-progress-bar></div></div>
                                    <span class="profile-upload-meta crm-profile-upload-meta crm-upload-meta">
                                        <b data-upload-name="bank_card_img">-</b>
                                        <em data-upload-size="bank_card_img" data-upload-size-text="bank_card_img">-</em>
                                        <em class="profile-upload-status crm-profile-upload-status crm-upload-status" data-upload-status="bank_card_img" data-translate="front.no_file_selected">{{ __('front.no_file_selected') }}</em>
                                    </span>
                                    <p class="crm-field-error" data-error-for="bank_card_img" role="alert" aria-live="assertive"></p>
                                </div>
                            </div>
                            <div class="crm-upload-pair-slot">
                                <label class="layui-form-label" data-translate="profile.bankCardBack">{{ __('profile.bank_card_back') }}</label>
                                <div class="layui-input-block profile-upload-field crm-profile-upload-field is-card-upload crm-upload-shell" data-upload-field="bank_card_img_back" data-crm-upload="bank_card_img_back" data-upload-accept="images" data-upload-exts="jpg|jpeg|png|gif|webp" data-upload-max-size="10240" data-upload-auto="0" data-upload-multiple="0">
                                    <button type="button" class="layui-upload-drag profile-upload-card crm-profile-upload-card crm-upload-action" id="bankCardBackImgBtn" data-upload-trigger aria-label="{{ __('profile.upload_back') }}">
                                        <i data-lucide="cloud-upload" aria-hidden="true"></i>
                                        <span class="crm-upload-drag-text" data-translate="profile.uploadBack">{{ __('profile.upload_back') }}</span>
                                        <span class="crm-upload-drag-hint" data-translate="front.upload_choose_or_drag">{{ __('front.upload_choose_or_drag') }}</span>
                                    </button>
                                    <button type="button" class="profile-upload-clear crm-profile-upload-clear crm-upload-clear" data-upload-clear="bank_card_img_back" title="{{ __('front.upload_remove') }}" aria-label="{{ __('front.upload_remove') }}"><i data-lucide="x" aria-hidden="true"></i></button>
                                    <img id="bankCardBackImgPreview" class="profile-upload-preview crm-profile-upload-preview" data-upload-preview="bank_card_img_back" data-image-preview="" role="button" tabindex="0" alt="">
                                    <div class="crm-upload-progress" data-upload-progress role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="{{ __('front.upload_uploading') }}"><div class="crm-upload-progress-bar" data-upload-progress-bar></div></div>
                                    <span class="profile-upload-meta crm-profile-upload-meta crm-upload-meta">
                                        <b data-upload-name="bank_card_img_back">-</b>
                                        <em data-upload-size="bank_card_img_back" data-upload-size-text="bank_card_img_back">-</em>
                                        <em class="profile-upload-status crm-profile-upload-status crm-upload-status" data-upload-status="bank_card_img_back" data-translate="front.no_file_selected">{{ __('front.no_file_selected') }}</em>
                                    </span>
                                    <p class="crm-field-error" data-error-for="bank_card_img_back" role="alert" aria-live="assertive"></p>
                                </div>
                            </div>
                        </div>
                        <div class="profile-actions">
                            <button class="layui-btn layui-bg-blue" lay-submit lay-filter="bankSubmit" data-translate="common.submit">{{ __('common.submit') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="layui-col-md6 layui-col-sm12">
            <div class="layui-card">
                <div class="layui-card-body">
                    <div class="profile-section-title" data-translate="profile.changeBank">{{ __('profile.change_bank') }}</div>
                    <form class="layui-form layui-form-pane" lay-filter="bankChangeForm" enctype="multipart/form-data">
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="profile.fullPhone">{{ __('profile.full_phone') }}</label>
                            <div class="layui-input-block">
                                <input type="text" name="verify_phone" required lay-verify="profileRequired" class="layui-input" autocomplete="off">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="profile.currentEmail">{{ __('profile.current_email') }}</label>
                            <div class="layui-input-block">
                                <input type="email" name="verify_email" required lay-verify="profileRequired|email" class="layui-input" autocomplete="off">
                            </div>
                        </div>
                        {{-- 银行卡换绑会改变出金收款账户，密码和邮箱验证码必须随同本表单提交。 --}}
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="profile.currentPassword">{{ __('profile.current_password') }}</label>
                            <div class="layui-input-block">
                                <input type="password" name="password" required lay-verify="profileRequired" class="layui-input" autocomplete="current-password">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="profile.verificationCode">{{ __('profile.verification_code') }}</label>
                            <div class="layui-input-block">
                                <div class="layui-input-group">
                                    <input type="text" name="verification_code" required lay-verify="profileRequired" class="layui-input" inputmode="numeric" maxlength="6" autocomplete="one-time-code">
                                    <div class="layui-input-suffix">
                                        <button type="button" class="layui-btn layui-btn-primary" id="sendBankChangeCodeBtn">
                                            <i data-lucide="send" aria-hidden="true"></i>
                                            <span data-translate="profile.sendCode">{{ __('profile.send_code') }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="front.bank_name">{{ __('front.bank_name') }}</label>
                            <div class="layui-input-block">
                                <input type="text" name="bank_name" required lay-verify="profileRequired" class="layui-input" autocomplete="off">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="front.bank_no">{{ __('front.bank_no') }}</label>
                            <div class="layui-input-block">
                                <input type="text" name="bank_no" required lay-verify="profileRequired" class="layui-input" autocomplete="off">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="profile.bankAddress">{{ __('auth.address') }}</label>
                            <div class="layui-input-block">
                                <input type="text" name="bank_addr" required lay-verify="profileRequired" class="layui-input" autocomplete="off">
                            </div>
                        </div>
                        {{-- 银行卡换绑同样要求正反面：复用同一套共享 Layui 上传组件。 --}}
                        <div class="layui-form-item crm-upload-pair" role="group" aria-label="{{ __('profile.bank_card_images') }}">
                            <div class="crm-upload-pair-slot">
                                <label class="layui-form-label" data-translate="profile.bankCardFront">{{ __('profile.bank_card_front') }}</label>
                                <div class="layui-input-block profile-upload-field crm-profile-upload-field is-card-upload crm-upload-shell" data-upload-field="bank_change_card_img" data-crm-upload="bank_change_card_img" data-upload-accept="images" data-upload-exts="jpg|jpeg|png|gif|webp" data-upload-max-size="10240" data-upload-auto="0" data-upload-multiple="0">
                                    <button type="button" class="layui-upload-drag profile-upload-card crm-profile-upload-card crm-upload-action" id="bankChangeCardImgBtn" data-upload-trigger aria-label="{{ __('profile.upload_front') }}">
                                        <i data-lucide="cloud-upload" aria-hidden="true"></i>
                                        <span class="crm-upload-drag-text" data-translate="profile.uploadFront">{{ __('profile.upload_front') }}</span>
                                        <span class="crm-upload-drag-hint" data-translate="front.upload_choose_or_drag">{{ __('front.upload_choose_or_drag') }}</span>
                                    </button>
                                    <button type="button" class="profile-upload-clear crm-profile-upload-clear crm-upload-clear" data-upload-clear="bank_change_card_img" title="{{ __('front.upload_remove') }}" aria-label="{{ __('front.upload_remove') }}"><i data-lucide="x" aria-hidden="true"></i></button>
                                    <img id="bankChangeCardImgPreview" class="profile-upload-preview crm-profile-upload-preview" data-upload-preview="bank_change_card_img" data-image-preview="" role="button" tabindex="0" alt="">
                                    <div class="crm-upload-progress" data-upload-progress role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="{{ __('front.upload_uploading') }}"><div class="crm-upload-progress-bar" data-upload-progress-bar></div></div>
                                    <span class="profile-upload-meta crm-profile-upload-meta crm-upload-meta">
                                        <b data-upload-name="bank_change_card_img">-</b>
                                        <em data-upload-size="bank_change_card_img" data-upload-size-text="bank_change_card_img">-</em>
                                        <em class="profile-upload-status crm-profile-upload-status crm-upload-status" data-upload-status="bank_change_card_img" data-translate="front.no_file_selected">{{ __('front.no_file_selected') }}</em>
                                    </span>
                                    <p class="crm-field-error" data-error-for="bank_change_card_img" role="alert" aria-live="assertive"></p>
                                </div>
                            </div>
                            <div class="crm-upload-pair-slot">
                                <label class="layui-form-label" data-translate="profile.bankCardBack">{{ __('profile.bank_card_back') }}</label>
                                <div class="layui-input-block profile-upload-field crm-profile-upload-field is-card-upload crm-upload-shell" data-upload-field="bank_change_card_img_back" data-crm-upload="bank_change_card_img_back" data-upload-accept="images" data-upload-exts="jpg|jpeg|png|gif|webp" data-upload-max-size="10240" data-upload-auto="0" data-upload-multiple="0">
                                    <button type="button" class="layui-upload-drag profile-upload-card crm-profile-upload-card crm-upload-action" id="bankChangeCardBackImgBtn" data-upload-trigger aria-label="{{ __('profile.upload_back') }}">
                                        <i data-lucide="cloud-upload" aria-hidden="true"></i>
                                        <span class="crm-upload-drag-text" data-translate="profile.uploadBack">{{ __('profile.upload_back') }}</span>
                                        <span class="crm-upload-drag-hint" data-translate="front.upload_choose_or_drag">{{ __('front.upload_choose_or_drag') }}</span>
                                    </button>
                                    <button type="button" class="profile-upload-clear crm-profile-upload-clear crm-upload-clear" data-upload-clear="bank_change_card_img_back" title="{{ __('front.upload_remove') }}" aria-label="{{ __('front.upload_remove') }}"><i data-lucide="x" aria-hidden="true"></i></button>
                                    <img id="bankChangeCardBackImgPreview" class="profile-upload-preview crm-profile-upload-preview" data-upload-preview="bank_change_card_img_back" data-image-preview="" role="button" tabindex="0" alt="">
                                    <div class="crm-upload-progress" data-upload-progress role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="{{ __('front.upload_uploading') }}"><div class="crm-upload-progress-bar" data-upload-progress-bar></div></div>
                                    <span class="profile-upload-meta crm-profile-upload-meta crm-upload-meta">
                                        <b data-upload-name="bank_change_card_img_back">-</b>
                                        <em data-upload-size="bank_change_card_img_back" data-upload-size-text="bank_change_card_img_back">-</em>
                                        <em class="profile-upload-status crm-profile-upload-status crm-upload-status" data-upload-status="bank_change_card_img_back" data-translate="front.no_file_selected">{{ __('front.no_file_selected') }}</em>
                                    </span>
                                    <p class="crm-field-error" data-error-for="bank_change_card_img_back" role="alert" aria-live="assertive"></p>
                                </div>
                            </div>
                        </div>
                        <div class="profile-actions">
                            <button class="layui-btn layui-bg-blue" lay-submit lay-filter="bankChangeSubmit" data-translate="common.submit">{{ __('common.submit') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="profile/index"></div>
@endsection
