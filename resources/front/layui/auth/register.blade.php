<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title data-translate="register_title">{{ __('auth.register') }}</title>
    <script src="{{ asset('/js/common/theme-sync.js') }}?v=2026052908"></script>
    <link rel="stylesheet" href="{{ asset('/js/common/layui-v2.13.5/layui/css/layui.css') }}">
    <link rel="stylesheet" href="{{ asset('/css/front/style.css') }}?v=2026052908">
</head>
<body class="auth-wrapper">
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
                <label class="layui-form-label" data-translate="email_code">Email Code</label>
                <div class="layui-input-block register-code-row">
                    <input type="text" name="email_code" required lay-verify="required|emailCode"
                           data-translate-placeholder="email_code" class="layui-input">
                    <button type="button" class="layui-btn layui-btn-primary" id="sendEmailCode" data-translate="send_email_code">Send Code</button>
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
                <div class="layui-col-md6">
                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="username">{{ __('auth.username') }}</label>
                        <div class="layui-input-block">
                            <input type="text" name="user_name" required lay-verify="required" class="layui-input">
                        </div>
                    </div>
                </div>
                <div class="layui-col-md6">
                    <div class="layui-form-item">
                        <label class="layui-form-label" data-translate="phone">{{ __('front.phone') }}</label>
                        <div class="layui-input-block register-phone-row">
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
                            <input type="tel" name="phone_number" required lay-verify="required|phoneNumber" class="layui-input register-phone-input" minlength="12" maxlength="20" inputmode="numeric" autocomplete="tel">
                        </div>
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
                        <i class="layui-icon layui-icon-refresh"></i>
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
                <a href="{{ url('/front/login') }}" data-translate="go_login">{{ __('auth.go_login') }}</a>
            </p>
            <p>
                <a href="javascript:;" class="lang-switch" data-lang="zh-CN" data-translate="lang_zh">{{ __('common.lang_zh') }}</a> |
                <a href="javascript:;" class="lang-switch" data-lang="en" data-translate="lang_en">{{ __('common.lang_en') }}</a>
            </p>
        </div>
    </div>

    <script src="{{ asset('/js/common/jquery/jquery-3.6.0.min.js') }}"></script>
    <script src="{{ asset('/js/common/layui-v2.13.5/layui/layui.js') }}"></script>
    <script src="{{ asset('/js/front/layui/common.js') }}"></script>
    <script src="{{ asset('/js/front/layui/auth/register.js') }}?v=2026052913"></script>
</body>
</html>
