{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/28
Time: 01:24
--}}
@extends('front_layui::layouts.app')

@section('title', __('front.profile'))
@section('breadcrumb', __('breadcrumb.front_profile_info'))

@section('styles')
<link rel="stylesheet" href="{{ asset('/css/front/v2.css') }}?v=2026061401">
@endsection

@section('content')
<div class="front-v2-page front-v2-page-shell front-v2-profile crm-visual-page crm-profile-shell">
    <div class="front-v2-hero crm-profile-hero front-v2-profile-hero">
        <div data-crm-upload="avatar" data-upload-accept="images" data-upload-exts="jpg|jpeg|png|gif|webp" data-upload-max-size="10240" data-upload-auto="0" data-upload-multiple="0" class="profile-upload-field crm-profile-upload-field crm-profile-avatar-block crm-upload-shell" data-upload-field="avatar">
            <div class="crm-profile-avatar-wrap">
                <img id="avatarPreview" src="{{ asset('/images/default-avatar.svg') }}" class="front-v2-avatar" data-upload-preview="avatar" alt="{{ __('user.upload_avatar') }}">
                <button type="button" class="layui-upload-drag profile-upload-card profile-avatar-upload-card crm-profile-upload-card crm-profile-avatar-upload-card crm-profile-avatar-action" id="selectAvatar" aria-label="{{ __('user.upload_avatar') }}">
                    <i data-lucide="camera"></i>
                    <span data-translate="profile.uploadShort">{{ __('profile.upload_short') }}</span>
                </button>
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
        <div class="front-v2-profile-hero-main crm-profile-hero-main">
                <h1 id="profileName">{{ __('common.loading') }}</h1>
                <div class="front-v2-profile-sensitive crm-profile-sensitive">
                    <span><span data-translate="front.user_id">{{ __('front.user_id') }}</span>: <strong id="profileUserId">-</strong></span>
                    <span><span data-translate="front.phone">{{ __('front.phone') }}</span>: <strong id="profilePhoneMasked">-</strong></span>
                    <span><span data-translate="front.email">{{ __('front.email') }}</span>: <strong id="profileEmailMasked">-</strong></span>
                </div>
            </div>
    </div>

    <div class="front-v2-panel">
        <div class="front-v2-panel-title">
            <h2 data-translate="profile.title">{{ __('front.profile') }}</h2>
            <p>{{ app()->getLocale() === 'en' ? 'Update your account information and contact details.' : '更新您的账户信息和联系方式。' }}</p>
        </div>
        <div class="front-v2-panel-body">
            <form class="layui-form layui-form-pane" lay-filter="profileForm">
                <div class="front-v2-form-grid">
                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="profile.userName">{{ __('front.user_name') }}</label>
                        <div class="layui-input-block">
                            <input type="text" name="user_name" required lay-verify="profileRequired" class="layui-input">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="profile.phoneNo">{{ __('front.phone') }}</label>
                        <div class="layui-input-block">
                            <input type="text" id="profilePhoneReadonly" class="layui-input" readonly autocomplete="off">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="front.id_card_no">{{ __('front.id_card_no') }}</label>
                        <div class="layui-input-block">
                            <input type="text" id="profileIdCardReadonly" class="layui-input" readonly autocomplete="off">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="profile.genderLabel">{{ __('register.gender') }}</label>
                        <div class="layui-input-block">
                            <input type="radio" name="gender" value="1" data-translate-title="register.male" title="{{ __('register.male') }}">
                            <input type="radio" name="gender" value="2" data-translate-title="register.female" title="{{ __('register.female') }}">
                        </div>
                    </div>
                    <div class="layui-form-item is-wide">
                        <label class="layui-form-label" data-translate="profile.addressLabel">{{ __('auth.address') }}</label>
                        <div class="layui-input-block">
                            <textarea name="address" class="layui-textarea" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="profile-actions">
                    <button class="layui-btn layui-bg-blue" lay-submit lay-filter="profileSubmit" data-translate="common.save">{{ __('common.save') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="front-v2-two-col">
        <div class="front-v2-panel">
            <div class="front-v2-panel-title">
                <h2 data-translate="profile.changePassword">{{ __('front.change_password') }}</h2>
                <p>{{ app()->getLocale() === 'en' ? 'Update your password regularly for security.' : '定期更新密码保障账户安全。' }}</p>
            </div>
            <div class="front-v2-panel-body">
                <form class="layui-form layui-form-pane" lay-filter="passwordForm">
                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="profile.currentPassword">{{ __('auth.old_password') }}</label>
                        <div class="layui-input-block">
                            <input type="password" name="old_password" required lay-verify="profileRequired" class="layui-input">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="profile.newPassword">{{ __('auth.new_password') }}</label>
                        <div class="layui-input-block">
                            <input type="password" name="new_password" id="new_password" required lay-verify="profileRequired|password" class="layui-input">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="profile.confirmPassword">{{ __('auth.confirm_password') }}</label>
                        <div class="layui-input-block">
                            <input type="password" name="confirm_password" required lay-verify="profileRequired|confirmPass" class="layui-input">
                        </div>
                    </div>
                    <div class="profile-actions">
                        <button class="layui-btn layui-bg-blue" lay-submit lay-filter="passwordSubmit" data-translate="common.save">{{ __('common.save') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="front-v2-panel">
            <div class="front-v2-panel-title">
                <h2 data-translate="profile.changeEmail">{{ __('front.email_settings') }}</h2>
                <p>{{ app()->getLocale() === 'en' ? 'Update your primary email address.' : '更新您的主要邮箱地址。' }}</p>
            </div>
            <div class="front-v2-panel-body">
                <form class="layui-form layui-form-pane" lay-filter="emailForm">
                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="profile.newEmail">{{ __('auth.email') }}</label>
                        <div class="layui-input-block">
                            <input type="email" name="new_email" required lay-verify="profileRequired|email" class="layui-input">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="profile.emailCode">Email Code</label>
                        <div class="layui-input-block front-v2-code-row">
                            <input type="text" name="email_code" required lay-verify="profileRequired" class="layui-input">
                            <button type="button" class="layui-btn layui-btn-sm layui-btn-primary" id="sendEmailCodeBtn" data-translate="auth.send_code">Send Code</button>
                        </div>
                    </div>
                    <div class="profile-actions">
                        <button class="layui-btn layui-bg-blue" lay-submit lay-filter="emailSubmit" data-translate="common.save">{{ __('common.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="front-v2-two-col">
        <div class="front-v2-panel">
            <div class="front-v2-panel-title">
                <h2 data-translate="profile.phoneSettings">{{ __('front.phone_settings') }}</h2>
                <p>{{ app()->getLocale() === 'en' ? 'Update your phone number for authentication.' : '更新您的认证手机号。' }}</p>
            </div>
            <div class="front-v2-panel-body">
                <form class="layui-form layui-form-pane" lay-filter="phoneForm">
                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="profile.newPhone">{{ __('front.phone') }}</label>
                        <div class="layui-input-block">
                            <input type="text" name="new_phone" required lay-verify="profileRequired" class="layui-input">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="profile.phoneCode">Phone Code</label>
                        <div class="layui-input-block front-v2-code-row">
                            <input type="text" name="phone_code" required lay-verify="profileRequired" class="layui-input">
                            <button type="button" class="layui-btn layui-btn-sm layui-btn-primary" id="sendPhoneCodeBtn" data-translate="auth.send_code">Send Code</button>
                        </div>
                    </div>
                    <div class="profile-actions">
                        <button class="layui-btn layui-bg-blue" lay-submit lay-filter="phoneSubmit" data-translate="common.save">{{ __('common.save') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="front-v2-panel">
            <div class="front-v2-panel-title">
                <h2 data-translate="profile.identityVerification">{{ __('front.identity_verification') }}</h2>
                <p>{{ app()->getLocale() === 'en' ? 'Upload ID card for account verification.' : '上传身份证完成账户认证。' }}</p>
            </div>
            <div class="front-v2-panel-body">
                <form class="layui-form layui-form-pane" lay-filter="identityForm" id="identityForm">
                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="profile.idCardNo">{{ __('front.id_card_no') }}</label>
                        <div class="layui-input-block">
                            <input type="text" name="id_card_no" required lay-verify="profileRequired" class="layui-input">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="profile.realName">{{ __('front.real_name') }}</label>
                        <div class="layui-input-block">
                            <input type="text" name="real_name" required lay-verify="profileRequired" class="layui-input">
                        </div>
                    </div>
                    {{-- 身份证正反面成对上传：v2 视觉家族复用同一套共享 Layui 上传组件。 --}}
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
                                <img id="idCardFrontPreview" class="profile-upload-preview crm-profile-upload-preview" src="" data-upload-preview="id_card_front" data-image-preview="" role="button" tabindex="0" alt="">
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
                                <img id="idCardBackPreview" class="profile-upload-preview crm-profile-upload-preview" src="" data-upload-preview="id_card_back" data-image-preview="" role="button" tabindex="0" alt="">
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
                        <button class="layui-btn layui-bg-blue" lay-submit lay-filter="identitySubmit" data-translate="common.save">{{ __('common.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="front-v2-panel">
        <div class="front-v2-panel-title">
            <h2 data-translate="profile.bankCardInfo">{{ __('front.bank_card_info') }}</h2>
            <p>{{ app()->getLocale() === 'en' ? 'Add or update your bank account for withdrawals.' : '添加或更新提现银行账户信息。' }}</p>
        </div>
        <div class="front-v2-panel-body">
            <form class="layui-form layui-form-pane" lay-filter="bankForm">
                <div class="front-v2-form-grid">
                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="profile.bankName">{{ __('front.bank_name') }}</label>
                        <div class="layui-input-block">
                            <input type="text" name="bank_name" required lay-verify="profileRequired" class="layui-input">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="profile.bankCardNo">{{ __('front.bank_card_no') }}</label>
                        <div class="layui-input-block">
                            <input type="text" name="bank_card_no" required lay-verify="profileRequired" class="layui-input">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="profile.bankBranch">{{ __('front.bank_branch') }}</label>
                        <div class="layui-input-block">
                            <input type="text" name="bank_branch" class="layui-input">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="profile.bankAccountName">{{ __('front.bank_account_name') }}</label>
                        <div class="layui-input-block">
                            <input type="text" name="bank_account_name" required lay-verify="profileRequired" class="layui-input">
                        </div>
                    </div>
                </div>
                {{-- 银行卡正反面成对上传：v2 视觉家族复用同一套共享 Layui 上传组件。 --}}
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
                            <img id="bankCardImgPreview" class="profile-upload-preview crm-profile-upload-preview" src="" data-upload-preview="bank_card_img" data-image-preview="" role="button" tabindex="0" alt="">
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
                            <img id="bankCardBackImgPreview" class="profile-upload-preview crm-profile-upload-preview" src="" data-upload-preview="bank_card_img_back" data-image-preview="" role="button" tabindex="0" alt="">
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
                    <button class="layui-btn layui-bg-blue" lay-submit lay-filter="bankSubmit" data-translate="common.save">{{ __('common.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<div hidden data-layui-page="profile/index"></div>
@endsection
