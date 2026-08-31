{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/29
Time: 15:13
--}}
{{--
    旧前台资料操作专用页面。

    文件职责：
    - 根据控制器传入的固定 action 只渲染一类资料表单，避免旧弹层重复加载整张个人中心。
    - 保留旧项目字段名、Web Session、CSRF 和 POST 地址，使旧菜单入口可以继续完成业务。
    - 敏感验证码、当前密码、审核状态和重复资料仍由后端校验；前端校验只负责尽早提示，不代替授权。

    返回与失败场景：
    - 提交成功时由统一脚本关闭弹层或跳转登录页。
    - 后端返回旧 err/col 时，统一脚本把中文业务含义显示在对应字段附近。
--}}
@extends('front_layui::layouts.app')

@section('title', $legacyProfileTitle)
@section('breadcrumb', $legacyProfileTitle)

@section('styles')
<link rel="stylesheet" href="{{ asset('/css/front/profile-legacy-action.css') }}?v=2026072901">
@endsection

@section('content')
@php
    $isEnglish = app()->getLocale() === 'en';
    $bankAuth = $legacyProfileAuth;
@endphp
<main class="legacy-profile-action"
      data-legacy-profile-action="{{ $legacyProfileAction }}"
      data-submit-url="{{ $legacyProfileSubmitUrl }}"
      data-verify-url="{{ $legacyProfileVerifyUrl }}"
      data-code-url="{{ $legacyProfileCodeUrl }}"
      data-success-url="{{ $legacyProfileAction === 'password' ? '/user/loginOut' : '/user/index' }}">
    <header class="legacy-profile-header">
        <span class="legacy-profile-header-icon" aria-hidden="true">
            <i data-lucide="{{ $legacyProfileIcon }}"></i>
        </span>
        <div>
            <h1>{{ $legacyProfileTitle }}</h1>
            <p>{{ $isEnglish ? 'Account security settings' : '账户安全设置' }}</p>
        </div>
    </header>

    <form class="layui-form legacy-profile-form"
          id="legacyProfileActionForm"
          enctype="multipart/form-data"
          autocomplete="off">
        @csrf

        @if($legacyProfileAction === 'identity')
            <div class="legacy-profile-fields legacy-profile-fields-two">
                <div class="legacy-profile-field">
                    <label for="legacyIdentityName">{{ $isEnglish ? 'Legal name' : '真实姓名' }}</label>
                    <input type="text" id="legacyIdentityName" name="username" value="{{ $legacyProfileUserName }}"
                           maxlength="100" required class="layui-input">
                    <p class="legacy-profile-field-error" data-error-for="username"></p>
                </div>
                <div class="legacy-profile-field">
                    <label for="legacyIdentityNumber">{{ $isEnglish ? 'ID card number' : '身份证号码' }}</label>
                    <input type="text" id="legacyIdentityNumber" name="userIdcardNo" maxlength="50" required class="layui-input">
                    <p class="legacy-profile-field-error" data-error-for="userIdcardNo"></p>
                </div>
            </div>
            <div class="legacy-profile-files legacy-profile-files-two">
                <div class="legacy-file-field">
                    <label>{{ $isEnglish ? 'ID card front' : '身份证正面' }}</label>
                    {{-- 共享 layui 上传组件（deferred 模式）：本地校验/预览并缓存 File，提交时按旧字段名组装 FormData，保留旧 Session/CSRF 契约。 --}}
                    <div class="crm-upload-shell legacy-upload-shell" data-crm-upload="Idphoto1" data-upload-field="Idphoto1" data-upload-accept="images" data-upload-exts="jpg|jpeg|png|gif" data-upload-max-size="10240" data-upload-auto="0" data-upload-multiple="0">
                        <button type="button" class="layui-upload-drag crm-upload-action" data-upload-trigger aria-label="{{ $isEnglish ? 'Select image' : '选择图片' }}">
                            <i data-lucide="cloud-upload" aria-hidden="true"></i>
                            <span>{{ $isEnglish ? 'Select image' : '选择图片' }}</span>
                        </button>
                        <button type="button" class="legacy-upload-clear" data-upload-clear="Idphoto1" title="{{ $isEnglish ? 'Remove' : '移除' }}"><i data-lucide="x" aria-hidden="true"></i></button>
                        <span class="legacy-upload-meta">
                            <b data-upload-name="Idphoto1">-</b>
                            <em data-upload-size-text="Idphoto1">-</em>
                            <em class="crm-upload-status" data-upload-status="Idphoto1" data-translate="front.no_file_selected">{{ $isEnglish ? 'No file selected' : '未选择文件' }}</em>
                        </span>
                        <figure class="legacy-file-preview" data-upload-preview="Idphoto1" hidden><img src="" alt=""></figure>
                        <div class="crm-upload-progress" data-upload-progress role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="{{ $isEnglish ? 'Uploading' : '上传中' }}"><div class="crm-upload-progress-bar" data-upload-progress-bar></div></div>
                    </div>
                    <p class="legacy-profile-field-error" data-error-for="Idphoto1"></p>
                </div>
                <div class="legacy-file-field">
                    <label>{{ $isEnglish ? 'ID card back' : '身份证反面' }}</label>
                    <div class="crm-upload-shell legacy-upload-shell" data-crm-upload="Idphoto2" data-upload-field="Idphoto2" data-upload-accept="images" data-upload-exts="jpg|jpeg|png|gif" data-upload-max-size="10240" data-upload-auto="0" data-upload-multiple="0">
                        <button type="button" class="layui-upload-drag crm-upload-action" data-upload-trigger aria-label="{{ $isEnglish ? 'Select image' : '选择图片' }}">
                            <i data-lucide="cloud-upload" aria-hidden="true"></i>
                            <span>{{ $isEnglish ? 'Select image' : '选择图片' }}</span>
                        </button>
                        <button type="button" class="legacy-upload-clear" data-upload-clear="Idphoto2" title="{{ $isEnglish ? 'Remove' : '移除' }}"><i data-lucide="x" aria-hidden="true"></i></button>
                        <span class="legacy-upload-meta">
                            <b data-upload-name="Idphoto2">-</b>
                            <em data-upload-size-text="Idphoto2">-</em>
                            <em class="crm-upload-status" data-upload-status="Idphoto2" data-translate="front.no_file_selected">{{ $isEnglish ? 'No file selected' : '未选择文件' }}</em>
                        </span>
                        <figure class="legacy-file-preview" data-upload-preview="Idphoto2" hidden><img src="" alt=""></figure>
                        <div class="crm-upload-progress" data-upload-progress role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="{{ $isEnglish ? 'Uploading' : '上传中' }}"><div class="crm-upload-progress-bar" data-upload-progress-bar></div></div>
                    </div>
                    <p class="legacy-profile-field-error" data-error-for="Idphoto2"></p>
                </div>
            </div>
        @elseif($legacyProfileAction === 'bank' || $legacyProfileAction === 'bank-change')
            @if($legacyProfileAction === 'bank')
                <input type="hidden" name="username" value="{{ $legacyProfileUserName }}">
            @else
                <input type="hidden" name="uploadType" value="{{ $legacyProfileType }}">
            @endif
            <div class="legacy-profile-fields legacy-profile-fields-two">
                <div class="legacy-profile-field">
                    <label for="legacyBankName">{{ $isEnglish ? 'Bank name' : '开户银行' }}</label>
                    <input type="text" id="legacyBankName" name="bankclass"
                           value="{{ $legacyProfileAction === 'bank' ? (string) ($bankAuth->bank_name ?? '') : '' }}"
                           maxlength="255" required class="layui-input">
                    <p class="legacy-profile-field-error" data-error-for="bankclass"></p>
                </div>
                <div class="legacy-profile-field">
                    <label for="legacyBankNumber">{{ $isEnglish ? 'Bank card number' : '银行卡号' }}</label>
                    <input type="text" id="legacyBankNumber" name="bankno" inputmode="numeric" maxlength="50" required class="layui-input">
                    <p class="legacy-profile-field-error" data-error-for="bankno"></p>
                </div>
                <div class="legacy-profile-field legacy-profile-field-wide">
                    <label for="legacyBankAddress">{{ $isEnglish ? 'Branch address' : '开户地址' }}</label>
                    <input type="text" id="legacyBankAddress" name="bankinfo" maxlength="500" required class="layui-input">
                    <p class="legacy-profile-field-error" data-error-for="bankinfo"></p>
                </div>
                @if($legacyProfileAction === 'bank-change')
                    <div class="legacy-profile-field">
                        <label for="legacyBankEmail">{{ $isEnglish ? 'Current email' : '当前邮箱' }}</label>
                        <input type="email" id="legacyBankEmail" name="useremail" maxlength="100" required class="layui-input">
                        <p class="legacy-profile-field-error" data-error-for="useremail"></p>
                    </div>
                    <div class="legacy-profile-field">
                        <label for="legacyBankCode">{{ $isEnglish ? 'Verification code' : '邮箱验证码' }}</label>
                        <div class="legacy-profile-code-row">
                            <input type="text" id="legacyBankCode" name="userverfcode" inputmode="numeric"
                                   maxlength="6" required class="layui-input">
                            <button type="button" class="layui-btn legacy-profile-code-button" data-send-code>
                                <i data-lucide="send" aria-hidden="true"></i>
                                <span data-code-label aria-live="polite" aria-atomic="true">{{ $isEnglish ? 'Send code' : '发送验证码' }}</span>
                            </button>
                        </div>
                        <p class="legacy-profile-field-error" data-error-for="userverfcode"></p>
                    </div>
                    <div class="legacy-profile-field legacy-profile-field-wide">
                        <label for="legacyBankPassword">{{ $isEnglish ? 'Current password' : '当前密码' }}</label>
                        <input type="password" id="legacyBankPassword" name="password" required class="layui-input">
                        <p class="legacy-profile-field-error" data-error-for="password"></p>
                    </div>
                @endif
            </div>
            <div class="legacy-profile-files">
                <div class="legacy-file-field">
                    <label>{{ $isEnglish ? 'Bank card front' : '银行卡正面' }}</label>
                    <div class="crm-upload-shell legacy-upload-shell" data-crm-upload="bankimg" data-upload-field="bankimg" data-upload-accept="images" data-upload-exts="jpg|jpeg|png|gif" data-upload-max-size="10240" data-upload-auto="0" data-upload-multiple="0">
                        <button type="button" class="layui-upload-drag crm-upload-action" data-upload-trigger aria-label="{{ $isEnglish ? 'Select image' : '选择图片' }}">
                            <i data-lucide="cloud-upload" aria-hidden="true"></i>
                            <span>{{ $isEnglish ? 'Select image' : '选择图片' }}</span>
                        </button>
                        <button type="button" class="legacy-upload-clear" data-upload-clear="bankimg" title="{{ $isEnglish ? 'Remove' : '移除' }}"><i data-lucide="x" aria-hidden="true"></i></button>
                        <span class="legacy-upload-meta">
                            <b data-upload-name="bankimg">-</b>
                            <em data-upload-size-text="bankimg">-</em>
                            <em class="crm-upload-status" data-upload-status="bankimg" data-translate="front.no_file_selected">{{ $isEnglish ? 'No file selected' : '未选择文件' }}</em>
                        </span>
                        <figure class="legacy-file-preview" data-upload-preview="bankimg" hidden><img src="" alt=""></figure>
                        <div class="crm-upload-progress" data-upload-progress role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="{{ $isEnglish ? 'Uploading' : '上传中' }}"><div class="crm-upload-progress-bar" data-upload-progress-bar></div></div>
                    </div>
                    <p class="legacy-profile-field-error" data-error-for="bankimg"></p>
                </div>
            </div>
        @elseif($legacyProfileAction === 'avatar')
            <div class="legacy-profile-files legacy-profile-avatar-file">
                <div class="legacy-file-field">
                    <label>{{ $isEnglish ? 'Avatar image' : '头像图片' }}</label>
                    <div class="crm-upload-shell legacy-upload-shell" data-crm-upload="headimg" data-upload-field="headimg" data-upload-accept="images" data-upload-exts="jpg|jpeg|png|gif" data-upload-max-size="10240" data-upload-auto="0" data-upload-multiple="0">
                        <button type="button" class="layui-upload-drag crm-upload-action" data-upload-trigger aria-label="{{ $isEnglish ? 'Select image' : '选择图片' }}">
                            <i data-lucide="image-up" aria-hidden="true"></i>
                            <span>{{ $isEnglish ? 'Select image' : '选择图片' }}</span>
                        </button>
                        <button type="button" class="legacy-upload-clear" data-upload-clear="headimg" title="{{ $isEnglish ? 'Remove' : '移除' }}"><i data-lucide="x" aria-hidden="true"></i></button>
                        <span class="legacy-upload-meta">
                            <b data-upload-name="headimg">-</b>
                            <em data-upload-size-text="headimg">-</em>
                            <em class="crm-upload-status" data-upload-status="headimg" data-translate="front.no_file_selected">{{ $isEnglish ? 'No file selected' : '未选择文件' }}</em>
                        </span>
                        <figure class="legacy-file-preview legacy-avatar-preview" data-upload-preview="headimg" hidden><img src="" alt=""></figure>
                        <div class="crm-upload-progress" data-upload-progress role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="{{ $isEnglish ? 'Uploading' : '上传中' }}"><div class="crm-upload-progress-bar" data-upload-progress-bar></div></div>
                    </div>
                    <p class="legacy-profile-field-error" data-error-for="headimg"></p>
                </div>
            </div>
        @elseif($legacyProfileAction === 'contact-phone')
            <input type="hidden" name="type" value="phone">
            <div class="legacy-profile-current">
                <i data-lucide="smartphone" aria-hidden="true"></i>
                <span>{{ $isEnglish ? 'Current phone' : '当前手机号' }}</span>
                <strong>{{ $legacyProfilePhoneMasked ?: '-' }}</strong>
            </div>
            <div class="legacy-profile-fields legacy-profile-fields-two">
                <div class="legacy-profile-field">
                    <label for="legacyOldPhone">{{ $isEnglish ? 'Confirm current phone' : '确认当前手机号' }}</label>
                    <input type="text" id="legacyOldPhone" name="oldphonefill" maxlength="30" required class="layui-input">
                    <p class="legacy-profile-field-error" data-error-for="oldphonefill"></p>
                </div>
                <div class="legacy-profile-field">
                    <label for="legacyNewPhone">{{ $isEnglish ? 'New phone' : '新手机号' }}</label>
                    <input type="text" id="legacyNewPhone" name="userphoneNo" maxlength="30" required class="layui-input">
                    <p class="legacy-profile-field-error" data-error-for="userphoneNo"></p>
                </div>
                <div class="legacy-profile-field">
                    <label for="legacyConfirmPhone">{{ $isEnglish ? 'Confirm new phone' : '确认新手机号' }}</label>
                    <input type="text" id="legacyConfirmPhone" name="newuserphoneNo" maxlength="30" required class="layui-input">
                    <p class="legacy-profile-field-error" data-error-for="newuserphoneNo"></p>
                </div>
                <div class="legacy-profile-field">
                    <label for="legacyPhonePassword">{{ $isEnglish ? 'Current password' : '当前密码' }}</label>
                    <input type="password" id="legacyPhonePassword" name="password" required class="layui-input">
                    <p class="legacy-profile-field-error" data-error-for="password"></p>
                </div>
            </div>
        @elseif($legacyProfileAction === 'contact-email')
            <input type="hidden" name="type" value="email">
            <input type="hidden" name="oldemail" value="{{ $legacyProfileEmail }}">
            <div class="legacy-profile-current">
                <i data-lucide="mail" aria-hidden="true"></i>
                <span>{{ $isEnglish ? 'Current email' : '当前邮箱' }}</span>
                <strong>{{ $legacyProfileEmailMasked ?: '-' }}</strong>
            </div>
            <div class="legacy-profile-fields legacy-profile-fields-two">
                <div class="legacy-profile-field legacy-profile-field-wide">
                    <label for="legacyNewEmail">{{ $isEnglish ? 'New email' : '新邮箱' }}</label>
                    <input type="email" id="legacyNewEmail" name="useremail" maxlength="100" required class="layui-input">
                    <p class="legacy-profile-field-error" data-error-for="useremail"></p>
                </div>
                <div class="legacy-profile-field">
                    <label for="legacyEmailCode">{{ $isEnglish ? 'Verification code' : '邮箱验证码' }}</label>
                    <div class="legacy-profile-code-row">
                        <input type="text" id="legacyEmailCode" name="updVerifyCode" inputmode="numeric"
                               maxlength="6" required class="layui-input">
                        <button type="button" class="layui-btn legacy-profile-code-button" data-send-code>
                            <i data-lucide="send" aria-hidden="true"></i>
                            <span data-code-label aria-live="polite" aria-atomic="true">{{ $isEnglish ? 'Send code' : '发送验证码' }}</span>
                        </button>
                    </div>
                    <p class="legacy-profile-field-error" data-error-for="updVerifyCode"></p>
                </div>
                <div class="legacy-profile-field">
                    <label for="legacyEmailPassword">{{ $isEnglish ? 'Current password' : '当前密码' }}</label>
                    <input type="password" id="legacyEmailPassword" name="password" required class="layui-input">
                    <p class="legacy-profile-field-error" data-error-for="password"></p>
                </div>
            </div>
        @elseif($legacyProfileAction === 'password')
            <div class="legacy-profile-fields legacy-profile-password-fields">
                <div class="legacy-profile-field">
                    <label for="legacyOldPassword">{{ $isEnglish ? 'Current password' : '当前密码' }}</label>
                    <input type="password" id="legacyOldPassword" name="olduserpsw" required class="layui-input">
                    <p class="legacy-profile-field-error" data-error-for="olduserpsw"></p>
                </div>
                <div class="legacy-profile-field">
                    <label for="legacyNewPassword">{{ $isEnglish ? 'New password' : '新密码' }}</label>
                    <input type="password" id="legacyNewPassword" name="newuserpsw" minlength="6" required class="layui-input">
                    <p class="legacy-profile-field-error" data-error-for="newuserpsw"></p>
                </div>
                <div class="legacy-profile-field">
                    <label for="legacyConfirmPassword">{{ $isEnglish ? 'Confirm new password' : '确认新密码' }}</label>
                    <input type="password" id="legacyConfirmPassword" name="confirmuserpsw" minlength="6" required class="layui-input">
                    <p class="legacy-profile-field-error" data-error-for="confirmuserpsw"></p>
                </div>
            </div>
        @endif

        <div class="legacy-profile-form-error" data-form-error role="alert" hidden></div>
        <footer class="legacy-profile-actions">
            <button type="submit" class="layui-btn legacy-profile-submit" data-submit-button>
                <i data-lucide="check" aria-hidden="true"></i>
                <span data-submit-label>{{ $isEnglish ? 'Confirm' : '确认提交' }}</span>
            </button>
        </footer>
    </form>
</main>
@endsection

@section('scripts')
<div hidden data-layui-page="legacy/profile/action"></div>
@endsection
