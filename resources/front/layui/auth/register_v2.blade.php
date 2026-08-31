{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/28
Time: 00:49
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>{{ __('auth.register') }} - {{ __('common.system_name') }}</title>
    {{-- 新版注册独立页加载共享 Lucide，并由桥接器接管遗留 Layui 图标。 --}}
    @include('partials.lucide-assets')
    <link rel="stylesheet" href="{{ asset('/js/vendor/layui-v2.13.5/layui/css/layui.css') }}">
    <link rel="stylesheet" href="{{ asset('/css/front/style.css') }}?v=2026060306">
    <link rel="stylesheet" href="{{ asset('/css/front/v2.css') }}?v=2026061401">
    <link rel="stylesheet" href="{{ asset('/css/common/crm-design-system.css') }}?v=2026070403">
    @include('partials.theme-assets')
</head>
<body class="front-v2-page front-v2-auth-body crm-ui-auth-body" data-ui-family="layui" data-ui-surface="front" data-visual-direction="c">
    <div class="crm-theme-picker-host">
        @include('partials.theme-picker', ['themePickerCompact' => true, 'themePickerLabel' => false])
    </div>
    <main class="front-v2-auth is-register">
        <section class="front-v2-auth-panel">
            <div class="front-v2-auth-mark">
                <i data-lucide="circle-plus"></i>
                <span data-translate="system_name">{{ __('common.system_name') }}</span>
            </div>
            <div class="front-v2-auth-copy">
                <h1 data-translate="register_title">{{ __('auth.register') }}</h1>
                <p>{{ app()->getLocale() === 'en' ? 'Create an account with the information required for secure CRM access.' : '按安全访问要求填写资料，创建前台账号。' }}</p>
            </div>
            <p class="front-v2-auth-foot">{{ app()->getLocale() === 'en' ? 'Fields are grouped to help you finish without losing context.' : '字段按业务关系分组，减少长表单的压迫感。' }}</p>
        </section>

        <section class="front-v2-auth-card">
            <div class="front-v2-auth-heading">
                <h2 data-translate="register_title">{{ __('auth.register') }}</h2>
                <p>{{ app()->getLocale() === 'en' ? 'Complete identity, contact, and verification details.' : '完善身份、联系方式与验证信息。' }}</p>
            </div>

            <input type="hidden" id="inviterIdFromUrl" value="{{ $inviterId ?? '' }}">
            <input type="hidden" id="legacyAccountType" value="{{ $legacyAccountType ?? '' }}">

            <div class="layui-form" lay-filter="registerForm">
                <input type="hidden" name="commission_mode" value="{{ $legacyCommissionMode ?? '' }}">
                <div class="front-v2-register-grid">
                    <div class="layui-form-item is-wide">
                        <label class="layui-form-label" data-translate="email">{{ __('auth.email') }}</label>
                        <div class="layui-input-block">
                            <input type="email" name="email" required lay-verify="required|email"
                                   data-translate-placeholder="email" placeholder="{{ __('auth.email') }}" class="layui-input">
                        </div>
                    </div>

                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="password">{{ __('auth.password') }}</label>
                        <div class="layui-input-block">
                            <input type="password" name="password" required lay-verify="required|password"
                                   data-translate-placeholder="password" placeholder="{{ __('auth.password') }}" class="layui-input">
                        </div>
                    </div>

                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="confirm_password">{{ __('auth.confirm_password') }}</label>
                        <div class="layui-input-block">
                            <input type="password" name="password_confirmation" required lay-verify="required|confirmPass" class="layui-input">
                        </div>
                    </div>

                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="username">{{ __('auth.username') }}</label>
                        <div class="layui-input-block">
                            <input type="text" name="user_name" required lay-verify="required" class="layui-input">
                        </div>
                    </div>

                    {{-- 手机号独占整行：号码输入框必须能完整容纳并显示长于 11 位的国际号码。 --}}
                    <div class="layui-form-item is-wide">
                        <label class="layui-form-label" data-translate="phone">{{ __('front.phone') }}</label>
                        <div class="layui-input-block">
                            <div class="front-v2-code-row register-phone-row is-wide">
                                <select name="phone_code" lay-verify="required" aria-label="Phone country code">
                                    <option value="86">+86 (China)</option>
                                    <option value="852">+852 (Hong Kong)</option>
                                    <option value="853">+853 (Macau)</option>
                                    <option value="886">+886 (Taiwan)</option>
                                    <option value="1">+1 (USA)</option>
                                    <option value="44">+44 (UK)</option>
                                    <option value="81">+81 (Japan)</option>
                                    <option value="82">+82 (Korea)</option>
                                    <option value="60">+60 (Malaysia)</option>
                                    <option value="65">+65 (Singapore)</option>
                                </select>
                                <input type="tel" name="phone_number" required lay-verify="required|phoneNumber" class="layui-input register-phone-input" minlength="11" maxlength="20" size="20" inputmode="numeric" autocomplete="tel" data-translate-placeholder="phone_number_placeholder" placeholder="{{ __('register.phone_number_placeholder') }}">
                            </div>
                            <div class="register-phone-hint" data-translate="phone_length_hint">{{ __('register.phone_length_hint') }}</div>
                        </div>
                    </div>

                    <div class="layui-form-item is-wide">
                        <label class="layui-form-label" data-translate="id_card_no">{{ __('front.id_card_no') }}</label>
                        <div class="layui-input-block">
                            <input type="text" name="id_card_no" required lay-verify="required|idCardNo" class="layui-input">
                        </div>
                    </div>

                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="gender">{{ __('register.gender') }}</label>
                        <div class="layui-input-block">
                            <input type="radio" name="gender" value="1" data-translate-title="male" title="{{ __('register.male') }}" checked>
                            <input type="radio" name="gender" value="2" data-translate-title="female" title="{{ __('register.female') }}">
                        </div>
                    </div>

                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="account_type">{{ __('register.account_type') }}</label>
                        <div class="layui-input-block">
                            <input type="radio" name="account_type" value="1" data-translate-title="agent" title="{{ __('register.agent') }}" {{ (int) ($legacyAccountType ?? 2) === 1 ? 'checked' : '' }} lay-filter="accountType">
                            <input type="radio" name="account_type" value="2" data-translate-title="customer" title="{{ __('register.customer') }}" {{ (int) ($legacyAccountType ?? 2) === 2 ? 'checked' : '' }} lay-filter="accountType">
                        </div>
                    </div>

                    <div class="layui-form-item is-wide" id="inviterGroup">
                        <label class="layui-form-label" data-translate="invitation_code">{{ __('register.inviter_id') }}</label>
                        <div class="layui-input-block">
                            <input type="text" name="inviter_id" id="inviterId" value="{{ $inviterId ?? '' }}"
                                   data-translate-placeholder="invitation_code" placeholder="{{ __('register.inviter_id') }}" class="layui-input">
                            <span id="inviterInfo" class="inviter-info"></span>
                        </div>
                    </div>

                    <input type="hidden" name="captcha_key" id="captchaKey">
                    <div class="layui-form-item is-wide">
                        <label class="layui-form-label" data-translate="captcha_code">Captcha</label>
                        <div class="layui-input-block front-v2-code-row">
                            <input type="text" name="captcha_code" required lay-verify="required|captchaCode"
                                   data-translate-placeholder="captcha_code" class="layui-input">
                            <img id="registerCaptchaImg" class="front-v2-captcha" alt="captcha">
                            <button type="button" class="layui-btn layui-btn-primary" id="refreshCaptcha">
                                <i data-lucide="refresh-cw"></i>
                            </button>
                        </div>
                    </div>

                    <div class="layui-form-item is-wide">
                        <div class="layui-input-block">
                            <input type="checkbox" name="agree_terms" lay-verify="required" lay-skin="primary"
                                   data-translate-title="agree_terms" title="{{ __('register.terms_agree') }}">
                            <div class="register-terms-links">
                                <a href="{{ asset('/terms/customer_agreement.pdf') }}" data-zh-href="{{ asset('/terms/customer_agreement_zh.pdf') }}" data-en-href="{{ asset('/terms/customer_agreement.pdf') }}" target="_blank" data-translate="term_customer_agreement">Customer Agreement</a>
                                <a href="{{ asset('/terms/disclaimer.pdf') }}" data-zh-href="{{ asset('/terms/disclaimer_zh.pdf') }}" data-en-href="{{ asset('/terms/disclaimer.pdf') }}" target="_blank" data-translate="term_disclaimer">Disclaimer</a>
                                <a href="{{ asset('/terms/privacy_policy.pdf') }}" data-zh-href="{{ asset('/terms/privacy_policy_zh.pdf') }}" data-en-href="{{ asset('/terms/privacy_policy.pdf') }}" target="_blank" data-translate="term_privacy">Privacy Policy</a>
                                <a href="{{ asset('/terms/risk_statement.pdf') }}" data-zh-href="{{ asset('/terms/risk_statement_zh.pdf') }}" data-en-href="{{ asset('/terms/risk_statement.pdf') }}" target="_blank" data-translate="term_risk">Risk Statement</a>
                            </div>
                        </div>
                    </div>

                    <div class="layui-form-item is-wide">
                        <div class="layui-input-block">
                            <button class="layui-btn layui-btn-fluid front-v2-primary-btn" lay-submit lay-filter="registerSubmit"
                                    data-translate="register_btn">{{ __('auth.register') }}</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="front-v2-auth-links">
                <span data-translate="has_account">{{ __('auth.has_account') }}</span>
                <a href="{{ route('front_page_login') }}" data-translate="go_login">{{ __('auth.go_login') }}</a>
            </div>
            <div class="front-v2-lang-row">
                <a href="javascript:;" class="lang-switch" data-lang="zh-CN" data-translate="lang_zh">{{ __('common.lang_zh') }}</a>
                <span>|</span>
                <a href="javascript:;" class="lang-switch" data-lang="en" data-translate="lang_en">{{ __('common.lang_en') }}</a>
            </div>
        </section>
    </main>

    <script src="{{ asset('/js/vendor/jquery/jquery-3.6.0.min.js') }}"></script>
    <script src="{{ asset('/js/vendor/layui-v2.13.5/layui/layui.js') }}"></script>
    @include('partials.frontend-routes')
    <script src="{{ asset('/js/shared/ajax.js') }}?v=2026060702"></script>
    {{-- 注册页复用共享字段级校验提示，保证手机号等字段的错误锚定在输入框旁边。 --}}
    <script src="{{ asset('/js/shared/form-field-errors.js') }}?v=2026082801"></script>
    <script src="{{ asset('/js/apps/front/layui/common.js') }}"></script>
    <script src="{{ asset('/js/apps/front/layui/pages.js') }}?v=2026082801"></script>
    <div hidden data-layui-page="auth/register"></div>
</body>
</html>

