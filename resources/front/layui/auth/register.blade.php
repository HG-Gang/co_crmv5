{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/28
Time: 00:48
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title data-translate="register_title">{{ __('auth.register') }}</title>
    {{-- 旧注册独立页接入共享 Lucide，兼容原有 Layui 结构并统一图标来源。 --}}
    @include('partials.lucide-assets')
    <link rel="stylesheet" href="{{ asset('/js/vendor/layui-v2.13.5/layui/css/layui.css') }}">
    <link rel="stylesheet" href="{{ asset('/css/front/style.css') }}?v=2026060306">
    <link rel="stylesheet" href="{{ asset('/css/common/crm-design-system.css') }}?v=2026070403">
    @include('partials.theme-assets')
</head>
<body class="auth-wrapper crm-ui-auth-body" data-ui-family="layui" data-ui-surface="front" data-visual-direction="c">
    <div class="crm-theme-picker-host">
        @include('partials.theme-picker', ['themePickerCompact' => true, 'themePickerLabel' => false])
    </div>
    <div class="auth-card register-card">
        <div class="auth-logo">
            <h1 data-translate="system_name">{{ __('common.system_name') }}</h1>
            <h2 data-translate="register_title">{{ __('auth.register') }}</h2>
        </div>

        <input type="hidden" id="inviterIdFromUrl" value="{{ $inviterId ?? '' }}">

        <div class="layui-form" lay-filter="registerForm">
            <div class="layui-form-item">
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

            <div class="layui-row layui-col-space10">
                <div class="layui-col-md12">
                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="username">{{ __('auth.username') }}</label>
                        <div class="layui-input-block">
                            <input type="text" name="user_name" required lay-verify="required" class="layui-input">
                        </div>
                    </div>
                </div>
                {{-- 手机号独占整行：国家区号与号码分列，输入框需要完整容纳并显示长于 11 位的国际号码。 --}}
                <div class="layui-col-md12">
                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="phone">{{ __('front.phone') }}</label>
                        <div class="layui-input-block register-phone-row is-wide">
                            <select name="phone_code" lay-verify="required">
                                <option value="86">+86</option>
                                <option value="852">+852</option>
                                <option value="853">+853</option>
                                <option value="886">+886</option>
                                <option value="1">+1</option>
                                <option value="44">+44</option>
                                <option value="81">+81</option>
                                <option value="82">+82</option>
                                <option value="60">+60</option>
                                <option value="65">+65</option>
                                <option value="66">+66</option>
                                <option value="84">+84</option>
                                <option value="63">+63</option>
                                <option value="62">+62</option>
                            </select>
                            <input type="tel" name="phone_number" required lay-verify="required|phoneNumber" class="layui-input register-phone-input" minlength="11" maxlength="20" size="20" inputmode="numeric" autocomplete="tel" data-translate-placeholder="phone_number_placeholder" placeholder="{{ __('register.phone_number_placeholder') }}">
                        </div>
                        <div class="register-phone-hint" data-translate="phone_length_hint">{{ __('register.phone_length_hint') }}</div>
                    </div>
                </div>
            </div>

            <div class="layui-form-item">
                <label class="layui-form-label" data-translate="id_card_no">{{ __('front.id_card_no') }}</label>
                <div class="layui-input-block">
                    <input type="text" name="id_card_no" required lay-verify="required|idCardNo" class="layui-input">
                </div>
            </div>

            <div class="layui-row layui-col-space10">
                <div class="layui-col-md6">
                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="gender">{{ __('register.gender') }}</label>
                        <div class="layui-input-block">
                            <input type="radio" name="gender" value="1" data-translate-title="male" title="{{ __('register.male') }}" checked>
                            <input type="radio" name="gender" value="2" data-translate-title="female" title="{{ __('register.female') }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="layui-form-item">
                <label class="layui-form-label" data-translate="account_type">{{ __('register.account_type') }}</label>
                <div class="layui-input-block">
                    <input type="radio" name="account_type" value="1" data-translate-title="agent" title="{{ __('register.agent') }}" lay-filter="accountType">
                    <input type="radio" name="account_type" value="2" data-translate-title="customer" title="{{ __('register.customer') }}" checked lay-filter="accountType">
                </div>
            </div>

            <div class="layui-form-item" id="inviterGroup">
                <label class="layui-form-label" data-translate="invitation_code">{{ __('register.inviter_id') }}</label>
                <div class="layui-input-block">
                    <input type="text" name="inviter_id" id="inviterId" lay-verify="required"
                           data-translate-placeholder="invitation_code" placeholder="{{ __('register.inviter_id') }}" class="layui-input">
                    <span id="inviterInfo" class="inviter-info"></span>
                </div>
            </div>

            <input type="hidden" name="captcha_key" id="captchaKey">
            <div class="layui-form-item">
                <label class="layui-form-label" data-translate="captcha_code">Captcha</label>
                <div class="layui-input-block register-code-row">
                    <input type="text" name="captcha_code" required lay-verify="required|captchaCode"
                           data-translate-placeholder="captcha_code" class="layui-input">
                    <img id="registerCaptchaImg" class="register-captcha-img" alt="captcha">
                    <button type="button" class="layui-btn layui-btn-primary" id="refreshCaptcha">
                        <i data-lucide="refresh-cw"></i>
                    </button>
                </div>
            </div>

            <div class="layui-form-item">
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

            <div class="layui-form-item">
                <div class="layui-input-block">
                    <button class="layui-btn layui-btn-fluid layui-bg-blue" lay-submit lay-filter="registerSubmit"
                            data-translate="register_btn">{{ __('auth.register') }}</button>
                </div>
            </div>
        </div>

        <div class="auth-footer">
            <p>
                <span data-translate="has_account">{{ __('auth.has_account') }}</span>
                <a href="{{ route('front_page_login') }}" data-translate="go_login">{{ __('auth.go_login') }}</a>
            </p>
            <p>
                <a href="javascript:;" class="lang-switch" data-lang="zh-CN" data-translate="lang_zh">{{ __('common.lang_zh') }}</a> |
                <a href="javascript:;" class="lang-switch" data-lang="en" data-translate="lang_en">{{ __('common.lang_en') }}</a>
            </p>
        </div>
    </div>

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

