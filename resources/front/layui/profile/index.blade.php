@extends('front_layui::layouts.app')

@section('title', __('front.profile'))
@section('breadcrumb', __('breadcrumb.front_profile_info'))

@section('styles')
<style>
    .profile-page { display: flex; flex-direction: column; gap: 15px; }
    .profile-page .layui-card { margin-bottom: 0; }
    .profile-head { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
    .profile-head .avatar-preview { width: 86px; height: 86px; margin-bottom: 0; }
    .profile-head-main { min-width: 220px; }
    .profile-name { margin: 0 0 8px; font-size: 20px; font-weight: 600; color: #111827; }
    .profile-sensitive { display: flex; flex-wrap: wrap; gap: 8px 14px; color: #6b7280; }
    .profile-sensitive span { white-space: nowrap; }
    .profile-section-title { margin-bottom: 12px; font-size: 15px; font-weight: 600; color: #111827; }
    .profile-page .layui-form-label { width: 122px; }
    .profile-page .layui-input-block { margin-left: 122px; }
    .profile-actions { margin-left: 122px; }
    .profile-upload-field { display: flex; align-items: center; gap: 10px; }
    .profile-upload-preview { display: none; width: 120px; height: 76px; object-fit: cover; border: 1px solid #dde4ec; border-radius: 6px; background: #f8fafc; }
    @media screen and (max-width: 768px) {
        .profile-head { align-items: flex-start; }
        .profile-page .layui-form-label { width: 96px; }
        .profile-page .layui-input-block,
        .profile-actions { margin-left: 96px; }
    }
    @media screen and (max-width: 520px) {
        .profile-page .layui-form-label { float: none; display: block; width: auto; padding: 0 0 6px; background: transparent; border: 0; text-align: left; }
        .profile-page .layui-input-block,
        .profile-actions { margin-left: 0; }
    }
</style>
@endsection

@section('content')
<div class="profile-page">
    <div class="layui-card">
        <div class="layui-card-body">
            <div class="profile-head">
                <img id="avatarPreview" src="{{ asset('/images/default-avatar.svg') }}" class="avatar-preview" alt="">
                <div class="profile-head-main">
                    <h2 class="profile-name" id="profileName">{{ __('common.loading') }}</h2>
                    <div class="profile-sensitive">
                        <span><span data-translate="front.user_id">{{ __('front.user_id') }}</span>: <strong id="profileUserId">-</strong></span>
                        <span><span data-translate="front.phone">{{ __('front.phone') }}</span>: <strong id="profilePhoneMasked">-</strong></span>
                        <span><span data-translate="front.email">{{ __('front.email') }}</span>: <strong id="profileEmailMasked">-</strong></span>
                        <span><span data-translate="front.id_card_no">{{ __('front.id_card_no') }}</span>: <strong id="profileIdCardMasked">-</strong></span>
                    </div>
                </div>
                <button type="button" class="layui-btn layui-btn-sm layui-btn-primary" id="selectAvatar" data-translate="profile.uploadAvatar">{{ __('user.upload_avatar') }}</button>
                <button type="button" class="layui-btn layui-btn-sm layui-bg-blue" id="submitAvatar" data-translate="common.submit">{{ __('common.submit') }}</button>
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
                                <input type="text" name="user_name" required lay-verify="required" class="layui-input">
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
                                <input type="text" name="verify_phone" required lay-verify="required" class="layui-input" autocomplete="off">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="profile.currentEmail">{{ __('profile.current_email') }}</label>
                            <div class="layui-input-block">
                                <input type="email" name="current_email" required lay-verify="required|email" class="layui-input" autocomplete="off">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="profile.newEmail">{{ __('profile.new_email') }}</label>
                            <div class="layui-input-block">
                                <input type="email" name="new_email" required lay-verify="required|email" class="layui-input" autocomplete="off">
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
                                <input type="text" name="verify_phone" required lay-verify="required" class="layui-input" autocomplete="off">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="profile.currentEmail">{{ __('profile.current_email') }}</label>
                            <div class="layui-input-block">
                                <input type="email" name="verify_email" required lay-verify="required|email" class="layui-input" autocomplete="off">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="profile.newPhone">{{ __('front.phone') }}</label>
                            <div class="layui-input-block">
                                <input type="text" name="new_phone" required lay-verify="required" class="layui-input" autocomplete="off">
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
                    <form class="layui-form layui-form-pane" lay-filter="identityForm" enctype="multipart/form-data">
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="front.id_card_no">{{ __('front.id_card_no') }}</label>
                            <div class="layui-input-block">
                                <input type="text" name="id_card_no" required lay-verify="required" class="layui-input" autocomplete="off">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="profile.idCardFront">{{ __('profile.id_card_front') }}</label>
                            <div class="layui-input-block profile-upload-field">
                                <button type="button" class="layui-btn layui-btn-primary" id="idCardFrontBtn"><i class="layui-icon layui-icon-upload"></i></button>
                                <img id="idCardFrontPreview" class="profile-upload-preview" alt="">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="profile.idCardBack">{{ __('profile.id_card_back') }}</label>
                            <div class="layui-input-block profile-upload-field">
                                <button type="button" class="layui-btn layui-btn-primary" id="idCardBackBtn"><i class="layui-icon layui-icon-upload"></i></button>
                                <img id="idCardBackPreview" class="profile-upload-preview" alt="">
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
                                <input type="text" name="bank_name" required lay-verify="required" class="layui-input" autocomplete="off">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="front.bank_no">{{ __('front.bank_no') }}</label>
                            <div class="layui-input-block">
                                <input type="text" name="bank_no" required lay-verify="required" class="layui-input" autocomplete="off">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="profile.bankAddress">{{ __('auth.address') }}</label>
                            <div class="layui-input-block">
                                <input type="text" name="bank_addr" required lay-verify="required" class="layui-input" autocomplete="off">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="profile.bankCardImage">{{ __('profile.bank_card_image') }}</label>
                            <div class="layui-input-block profile-upload-field">
                                <button type="button" class="layui-btn layui-btn-primary" id="bankCardImgBtn"><i class="layui-icon layui-icon-upload"></i></button>
                                <img id="bankCardImgPreview" class="profile-upload-preview" alt="">
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
                                <input type="text" name="verify_phone" required lay-verify="required" class="layui-input" autocomplete="off">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="profile.currentEmail">{{ __('profile.current_email') }}</label>
                            <div class="layui-input-block">
                                <input type="email" name="verify_email" required lay-verify="required|email" class="layui-input" autocomplete="off">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="front.bank_name">{{ __('front.bank_name') }}</label>
                            <div class="layui-input-block">
                                <input type="text" name="bank_name" required lay-verify="required" class="layui-input" autocomplete="off">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="front.bank_no">{{ __('front.bank_no') }}</label>
                            <div class="layui-input-block">
                                <input type="text" name="bank_no" required lay-verify="required" class="layui-input" autocomplete="off">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="profile.bankAddress">{{ __('auth.address') }}</label>
                            <div class="layui-input-block">
                                <input type="text" name="bank_addr" required lay-verify="required" class="layui-input" autocomplete="off">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label" data-translate="profile.bankCardImage">{{ __('profile.bank_card_image') }}</label>
                            <div class="layui-input-block profile-upload-field">
                                <button type="button" class="layui-btn layui-btn-primary" id="bankChangeCardImgBtn"><i class="layui-icon layui-icon-upload"></i></button>
                                <img id="bankChangeCardImgPreview" class="profile-upload-preview" alt="">
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
<script src="{{ asset('/js/front/layui/profile/index.js') }}"></script>
@endsection
