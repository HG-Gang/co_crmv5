// Created by PhpStorm.
// Project name co_crmv5.
// User: Huang Gang
// Date: 2026/08/29
// Time: 15:18
/**
 * Aggregated Layui page module.
 * Generated from individual page entry scripts so Blade pages load one maintainable module.
 */
layui.define(function (exports) {
    'use strict';

    var registry = {};

    function once(fn) {
        var initialized = false;

        return function () {
            if (initialized) {
                return;
            }
            initialized = true;
            fn();
        };
    }

    function run(page) {
        if (!registry[page]) {
            throw new Error('Unknown Layui page module: ' + page);
        }

        registry[page]();
    }

    function has(page) {
        return !!registry[page];
    }

    function readJsonConfig(id) {
        var script = document.getElementById(id);

        if (!script) {
            return {};
        }

        try {
            return JSON.parse(script.textContent || '{}');
        } catch (error) {
            return {};
        }
    }

    function onReady(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
            return;
        }
        callback();
    }

    function runMarkedPages() {
        var nodes = document.querySelectorAll('[data-layui-page]');

        Array.prototype.forEach.call(nodes, function (node) {
            var page = node.getAttribute('data-layui-page');

            if (page && has(page)) {
                run(page);
            }
        });
    }

    function initFramePageBridge() {
        var config = readJsonConfig('crm-frame-page-config');
        var $ = layui.jquery || window.jQuery;
        var framePage;

        if (!config || !config.title) {
            return;
        }

        framePage = {
            title: config.title,
            breadcrumb: config.breadcrumb || [],
            path: window.location.pathname
        };
        window.__CRM_FRAME_PAGE = framePage;

        if (window.CrmLang && CrmLang.loadLanguage) {
            CrmLang.loadLanguage(CrmLang.getLocale());
        }

        if (window.parent && window.parent !== window) {
            window.parent.postMessage({
                type: 'crm:frame-page',
                title: framePage.title,
                breadcrumb: framePage.breadcrumb,
                path: framePage.path
            }, window.location.origin);
        }

        if (!$) {
            return;
        }

        $(document).on('click', 'a[href]', function (event) {
            var href = $(this).attr('href');
            var url;

            if (!href || href === 'javascript:;' || href.indexOf('#') === 0) {
                return;
            }

            url = new URL(href, window.location.origin);
            if (url.origin !== window.location.origin || url.pathname.indexOf((config.frontPagePrefix || '/front') + '/') !== 0) {
                return;
            }
            if (url.pathname === config.frontLoginPath || url.pathname === config.frontRegisterPath) {
                return;
            }

            if (window.parent && window.parent !== window) {
                event.preventDefault();
                window.parent.postMessage({
                    type: 'crm:frame-navigate',
                    url: href,
                    title: $.trim($(this).text())
                }, window.location.origin);
            }
        });
    }

    registry['auth/big-number-login'] = once(function () {
        // Source: auth/big-number-login.js
        /**
         * 大账户号登录页脚本。
         * 负责加载语言包、提交登录表单、保存前台 token，并在成功后进入控制台。
         */
        layui.use(['form', 'layer', 'jquery'], function () {
            var form = layui.form;
            var layer = layui.layer;
            var $ = layui.jquery;
            var $pageMarker = $('[data-layui-page="auth/big-number-login"]').first();
            var isLegacyBigAgent = $pageMarker.attr('data-legacy-big-agent') === '1';
            var loginEndpoint = $pageMarker.attr('data-login-endpoint')
                || (isLegacyBigAgent ? '/user/agents/signIn' : '/api/front/auth/big-number/login');
            var successUrl = $pageMarker.attr('data-success-url')
                || (isLegacyBigAgent ? '/user/agents/index' : crmRoute('front_page_dashboard'));

            // 登录页也要先加载当前语言，确保失败提示和按钮文案一致。
            if (typeof CrmLang !== 'undefined') {
                CrmLang.loadLanguage(CrmLang.getLocale());
            }

            // 统一读取登录提示文案，语言模块不可用时保留 key 方便排查。
            function t(key) {
                return typeof CrmLang !== 'undefined' && CrmLang.t ? CrmLang.t(key) : key;
            }

            // 旧大代理入口使用 session；现代入口才保存 front user JWT，两个协议分开处理。
            form.on('submit(bigNumberLoginSubmit)', function (data) {
                var onSuccess = function (res) {
                    if (isLegacyBigAgent) {
                        if (res && res.loginStatus === 200 && res.msg === 'OK') {
                            window.location.href = successUrl;
                            return;
                        }
                        layer.msg((res && (res.errpsw || res.notactive || res.errcptcode)) || t('auth.loginFailed'), {icon: 2});
                        return;
                    }
                    if (res.code === 1000 || res.code === 2000) {
                        CrmAjax.setToken('front', res.data.access_token);
                        window.location.href = successUrl;
                        return;
                    }
                    layer.msg(res.message || t('auth.loginFailed'), {icon: 2});
                };
                var onError = function (res) {
                    layer.msg((res && (res.message || res.errpsw || res.notactive)) || t('common.error'), {icon: 2});
                };

                if (isLegacyBigAgent) {
                    $.ajax({
                        url: loginEndpoint,
                        type: 'POST',
                        dataType: 'json',
                        data: data.field,
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || '',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }).done(onSuccess).fail(function (xhr) {
                        onError(xhr.responseJSON || {});
                    });
                } else {
                    CrmAjax.request({
                        url: loginEndpoint,
                        authRedirect: false,
                        data: data.field,
                        success: onSuccess,
                        error: onError
                    });
                }
                return false;
            });
        });
    });

    registry['auth/forgot-password'] = once(function () {
        // Source: auth/forgot-password.js
        /**
         * 找回密码页面脚本。
         * 先通过公开邮箱接口发送验证码，再提交验证码和新密码完成重置。
         */
        layui.use(['form', 'layer', 'jquery'], function () {
            var form = layui.form;
            var layer = layui.layer;
            var $ = layui.jquery;
            var $sendResetCode = $('#sendResetCode');
            var resetCodeTimer = null;

            // 先加载语言包，避免异步提交回调里出现未翻译的提示。
            if (typeof CrmLang !== 'undefined') {
                CrmLang.loadLanguage(CrmLang.getLocale());
            }

            /**
             * 恢复发送验证码按钮，供接口失败和倒计时结束时复用。
             *
             * @returns {void} 按钮恢复可点击，并显示当前语言的发送验证码文案。
             */
            function restoreResetCodeButton() {
                if (resetCodeTimer) {
                    clearInterval(resetCodeTimer);
                    resetCodeTimer = null;
                }

                $sendResetCode
                    .removeClass('layui-btn-disabled')
                    .prop('disabled', false)
                    .text(CrmLang.t('auth.send_reset_code'));
            }

            /**
             * 启动 60 秒发送倒计时，避免用户重复触发邮箱发送和后端限流。
             *
             * @returns {void} 倒计时期间按钮禁用，结束后自动恢复。
             */
            function startResetCodeCountdown() {
                var seconds = 60;

                if (resetCodeTimer) {
                    clearInterval(resetCodeTimer);
                }

                $sendResetCode
                    .addClass('layui-btn-disabled')
                    .prop('disabled', true)
                    .text(seconds + 's');

                resetCodeTimer = setInterval(function () {
                    seconds -= 1;
                    if (seconds <= 0) {
                        restoreResetCodeButton();
                        return;
                    }

                    $sendResetCode.text(seconds + 's');
                }, 1000);
            }

            /**
             * 校验邮箱并调用真实找回密码验证码接口。
             *
             * @returns {void} 成功后进入倒计时；业务失败或网络失败时恢复按钮并显示原因。
             */
            function sendResetCode() {
                var email = $.trim($('input[name="email"]').val() || '');

                if (!email) {
                    layer.msg(CrmLang.t('validation.required'), {icon: 2});
                    return;
                }
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    layer.msg(CrmLang.t('validation.email'), {icon: 2});
                    return;
                }
                if ($sendResetCode.prop('disabled')) {
                    return;
                }

                $sendResetCode.addClass('layui-btn-disabled').prop('disabled', true);
                CrmAjax.request({
                    url: '/api/front/auth/password/email-code',
                    authRedirect: false,
                    data: {email: email},
                    success: function (res) {
                        if (res.code >= 1000 && res.code < 4000) {
                            layer.msg(res.message || CrmLang.t('auth.reset_code_sent'), {icon: 1});
                            startResetCodeCountdown();
                            return;
                        }

                        restoreResetCodeButton();
                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    },
                    error: function (res) {
                        restoreResetCodeButton();
                        layer.msg((res && res.message) || CrmLang.t('common.error'), {icon: 2});
                    }
                });
            }

            // type=button 不提交重置表单，只触发验证码发送链路。
            $sendResetCode.on('click', sendResetCode);

            // 提交忘记密码表单，成功后跳回登录页，失败保留接口返回提示。
            form.on('submit(forgotSubmit)', function (data) {
                CrmAjax.request({
                    url: '/api/front/auth/password/reset',
                    authRedirect: false,
                    data: data.field,
                    success: function (res) {
                        if (res.code >= 1000 && res.code < 4000) {
                            layer.msg(res.message || CrmLang.t('auth.password_reset_success'), {icon: 1}, function () {
                                window.location.href = crmRoute('front_page_login');
                            });
                            return;
                        }
                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    },
                    error: function (res) {
                        layer.msg((res && res.message) || CrmLang.t('common.error'), {icon: 2});
                    }
                });
                return false;
            });
        });
    });

    registry['auth/login'] = once(function () {
        // Source: auth/login.js
        /**
         * 前台登录页脚本。
         *
         * 功能：
         * - 后端自动识别 Email 或 UserID。
         * - 纯 JS 语言切换，不刷新页面。
         * - 通过 layui.use 加载 common 模块。
         */
        layui.config({
            base: '/js/apps/front/layui/',   // common.js 所在目录。
            version: '20260527-login-fix'
        }).use(['form', 'layer', 'jquery', 'common'], function () {
            var form   = layui.form;       // 表单模块。
            var layer  = layui.layer;      // 弹层模块。
            var $      = layui.jquery;     // Layui 自带的 jQuery。
            var CRM    = layui.common;     // CRM 公共模块。
            var $pageMarker = $('[data-layui-page="auth/login"]').first();
            var isLegacyLogin = $pageMarker.attr('data-login-mode') === 'legacy';
            var loginEndpoint = $pageMarker.attr('data-login-endpoint') || '/api/front/auth/login';
            var successUrl = $pageMarker.attr('data-success-url') || '';
            var csrfToken = $('meta[name="csrf-token"]').attr('content') || '';
            var $legacyCaptcha = $('#legacyLoginCaptchaImg');

            function refreshLegacyCaptcha() {
                if (!$legacyCaptcha.length) return;

                var source = $legacyCaptcha.attr('data-captcha-src') || '/user/captcha';
                var separator = source.indexOf('?') === -1 ? '?' : '&';
                $legacyCaptcha.attr('src', source + separator + '_=' + Date.now());
            }

            if (isLegacyLogin && $legacyCaptcha.length) {
                $legacyCaptcha.on('click', refreshLegacyCaptcha);
                refreshLegacyCaptcha();
            }

            /**
             * 读取两套登录协议的失败提示。
             *
             * @param {Object} response 登录接口返回值；旧协议可能返回 errpsw 或 notactive。
             * @returns {string} 可直接交给 Layui layer 展示的中文或当前语言提示。
             */
            function loginFailureMessage(response) {
                var message = response && (response.errcptcode || response.errpsw || response.notactive || response.message || response.msg);

                return CRM.message(message, 'login_failed');
            }

            /**
             * 按当前登录入口跳转到对应的 Blade 页面。
             *
             * @returns {void} 旧入口进入 /user/index，现代入口进入带登录时间的 /front/dashboard。
             */
            function redirectAfterLogin() {
                var dashboardUrl = isLegacyLogin
                    ? successUrl
                    : CRM.route('front_page_dashboard', {_query: {login_at: Date.now()}});

                if (window.top && window.top !== window) {
                    window.top.location.replace(dashboardUrl);
                    return;
                }
                window.location.replace(dashboardUrl);
            }

            // =========================================================
            // 1. 表单提交。
            // =========================================================
            form.on('submit(doLogin)', function (formData) {
                var fields = formData.field || {};
                var pwd = isLegacyLogin ? fields.loginPassword : fields.password;
                if (!pwd) {
                    layer.msg(CRM.t('password_required'), { icon: 2 });
                    return false;
                }

                // 旧接口读取 loginUid，现代接口读取 account；两者都允许邮箱或 user_id。
                var account = $.trim(isLegacyLogin ? fields.loginUid : fields.account);
                if (!account) {
                    layer.msg(CRM.t('account_required'), { icon: 2 });
                    return false;
                }
                var captcha = $.trim(fields.cptcode || '');
                if (isLegacyLogin && !captcha) {
                    layer.msg(CRM.t('captcha_required'), { icon: 2 });
                    return false;
                }
                var postData = isLegacyLogin
                    ? {loginUid: account, loginPassword: pwd, cptcode: captcha}
                    : {account: account, password: pwd};
                var headers = {};

                // /user/signIn 属于 web 中间件，必须携带当前 Session 对应的 CSRF 令牌。
                if (isLegacyLogin && csrfToken) {
                    headers['X-CSRF-TOKEN'] = csrfToken;
                }

                CRM.ajax({
                    url: loginEndpoint,
                    auth: false,
                    authRedirect: false,
                    headers: headers,
                    data: postData,
                    success: function (res) {
                        var loginSucceeded = isLegacyLogin
                            ? Number(res.loginStatus) === 200 && res.msg === 'OK'
                            : Number(res.code) === 1000 || Number(res.code) === 2000;
                        var accessToken = isLegacyLogin
                            ? res.access_token
                            : (res.data && res.data.access_token);

                        if (loginSucceeded) {
                            if (accessToken) {
                                CRM.setToken(accessToken);
                            }
                            layer.msg(CRM.message(res.message, 'login_success'), { icon: 1 });
                            setTimeout(redirectAfterLogin, 250);
                        } else {
                            if (isLegacyLogin && res && res.errcptcode) refreshLegacyCaptcha();
                            layer.msg(loginFailureMessage(res), { icon: 2 });
                        }
                    },
                    error: function (xhr) {
                        var response = xhr && xhr.responseJSON ? xhr.responseJSON : xhr;

                        if (isLegacyLogin && response && response.errcptcode) refreshLegacyCaptcha();
                        layer.msg(loginFailureMessage(response) || CRM.t('network_error'), { icon: 2 });
                    }
                });
                return false;   // 阻止表单默认提交。
            });

            // =========================================================
            // 2. 纯 JS 语言切换，不刷新页面，不重复加载业务脚本。
            // =========================================================
            $('.J_langSwitch').on('click', function () {
                var lang = $(this).data('lang');
                // switchLang 内部使用 XHR 加载新语言文件并 eval,
                // 然后调用 applyTranslations 更新所有 data-translate 元素
                // 不创建新 script 标签, 不刷新页面
                CRM.switchLang(lang);
            });

            CRM.initLang();
        });
    });

    registry['auth/register'] = once(function () {
        // Source: auth/register.js
        /**
         * 前台 Layui 注册页控制脚本。
         *
         * 这里负责邀请上下文、图形验证码和旧项目注册字段契约。
         */
        layui.config({
            base: '/js/apps/front/layui/'
        }).use(['form', 'layer', 'jquery', 'common'], function() {
            var form = layui.form, 
                layer = layui.layer, 
                $ = layui.jquery,
                CRM = layui.common;
            
            var urlInviterId = $('#inviterIdFromUrl').val();
            var lockedAccountType = $('#legacyAccountType').val();
            var accountTypeFromUrl = queryValue('account_type');
            var commissionMode = $('input[name="commission_mode"]').val()
                || queryValue('commission_mode')
                || queryValue('comm_type');
            var initialAccountType = lockedAccountType || accountTypeFromUrl || '2';

            $('input[name="commission_mode"]').val(commissionMode);
            if (initialAccountType === '1' || initialAccountType === '2') {
                $('input[name="account_type"][value="' + initialAccountType + '"]').prop('checked', true);
            }
            if (lockedAccountType === '1' || lockedAccountType === '2') {
                $('input[name="account_type"]').prop('disabled', true);
            }
            updateInviterRequirement(initialAccountType);
            form.render('radio');
            refreshCaptcha();
            updateTermsLinks();

            if (urlInviterId) {
                $('#inviterId').val(urlInviterId);
                validateInviter(urlInviterId);
            }
            
            form.on('radio(accountType)', function(data) {
                updateInviterRequirement(data.value);
            });

            function selectedAccountType() {
                return lockedAccountType || $('input[name="account_type"]:checked').val() || '2';
            }

            function updateInviterRequirement(accountType) {
                if (accountType === '1') {
                    $('#inviterId').attr('lay-verify', 'required');
                    return;
                }
                $('#inviterId').removeAttr('lay-verify');
            }
            
            $('#inviterId').on('blur', function() {
                var inviterId = $(this).val().trim();
                if (inviterId) validateInviter(inviterId);
            });
            
            function validateInviter(inviterId) {
                $('#inviterInfo').text(CRM.t('loading')).css('color', '#666').show();
                
                CRM.ajax({
                    url: '/api/front/auth/inviter',
                    type: 'GET',
                    auth: false,
                    authRedirect: false,
                    data: {
                        inviter_id: inviterId,
                        account_type: selectedAccountType(),
                        commission_mode: commissionMode
                    },
                    success: function(res) {
                        if (res.code === 1000 || res.code === 2000) {
                            $('#inviterInfo').text(res.data.inviter_name || CRM.t('invitation_invalid')).css('color', 'green').show();
                        } else {
                            $('#inviterInfo').text(res.message || CRM.t('invitation_invalid')).css('color', 'red').show();
                        }
                    },
                    error: function() {
                        $('#inviterInfo').text(CRM.t('network_error')).css('color', 'red').show();
                    }
                });
            }

            function queryValue(name) {
                var params = new URLSearchParams(window.location.search || '');

                return params.get(name) || '';
            }
            
            form.verify({
                email: function(value) {
                    if (!/^[\w.-]+@[\w.-]+\.\w+$/.test(value)) return CRM.t('email_invalid');
                },
                password: function(value) {
                    if (value.length < 6) return CRM.t('password_min');
                    if (!/^[a-zA-Z][\s\S]*\d$/.test(value)) return CRM.t('password_format');
                },
                confirmPass: function(value) {
                    var pwd = $('input[name=password]').val();
                    if (value !== pwd) return CRM.t('password_confirm');
                },
                phoneNumber: function(value) {
                    // 手机号口径与 Blade maxlength 和 AuthController 保持一致：纯数字 11-20 位，长于 11 位的国际号码同样放行。
                    if (!/^[0-9]{11,20}$/.test(value)) return CRM.t('phone_invalid');
                },
                idCardNo: function(value) {
                    if ($.trim(value).length < 4) return CRM.t('id_card_required');
                },
                captchaCode: function(value) {
                    if ($.trim(value).length < 4) return CRM.t('captcha_required');
                }
            });

            $('#refreshCaptcha, #registerCaptchaImg').on('click', function() {
                refreshCaptcha();
            });

            form.on('submit(registerSubmit)', function(data) {
                var payload = $.extend({}, data.field, {
                    account_type: selectedAccountType(),
                    commission_mode: commissionMode
                });
                CRM.ajax({
                    url: '/api/front/auth/register',
                    auth: false,
                    authRedirect: false,
                    data: payload,
                    success: function(res) {
                        if (res.code === 1000 || res.code === 2000) {
                            layer.msg(CRM.message(res.message, 'register_success'), {icon: 1});
                            if (res.data && res.data.access_token) {
                                CRM.setToken(res.data.access_token);
                            }
                            setTimeout(function() {
                                window.location.href = CRM.route('front_page_dashboard');
                            }, 1000);
                        } else {
                            layer.msg(CRM.message(res.message, 'network_error'), {icon: 2});
                            refreshCaptcha();
                        }
                    },
                    error: function() {
                        layer.msg(CRM.t('network_error'), {icon: 2});
                        refreshCaptcha();
                    }
                });
                return false;
            });
            
            $('.lang-switch').on('click', function() {
                var lang = $(this).data('lang');
                CRM.switchLang(lang);
                updateTermsLinks(lang);
            });

            function refreshCaptcha() {
                var key = Date.now().toString(36) + Math.random().toString(36).slice(2);
                $('#captchaKey').val(key);
                $('#registerCaptchaImg').attr('src', '/api/front/auth/register/captcha?key=' + encodeURIComponent(key) + '&_=' + Date.now());
            }

            function updateTermsLinks(lang) {
                var current = lang || CRM.getLang();
                $('.register-terms-links a').each(function() {
                    var $link = $(this);
                    $link.attr('href', current === 'en' ? $link.attr('data-en-href') : $link.attr('data-zh-href'));
                });
            }
        });
    });

    registry['dashboard/index'] = once(function () {
        // Source: dashboard/index.js
        layui.use(['layer', 'jquery'], function() {
            // Layui 控制台页面入口：负责读取仪表盘数据、绑定顶部切换器，并统一维护本页图表实例。
            var layer = layui.layer;
            var $ = layui.jquery;
            var charts = {};
            var lastChartStats = null;
            // 趋势图日粒度序列快照：切换图表类型时直接复用，不再请求接口。
            var lastChartSeries = null;
            var lastChartProfile = null;
            var activeRange = 30;
            var dashboardRequestSequence = 0;
            var dashboardRoutes = readJsonConfig('crm-dashboard-routes') || window.CrmDashboardRoutes || {};
            var chartTypes = {
                fundsChart: 'bar',
                networkChart: 'pie',
                orderChart: 'bar',
                commissionChart: 'line',
                // 日粒度趋势图：数据来自 /api/front/dashboard 的 series 字段。
                flowTrendChart: 'line',
                orderTrendChart: 'bar',
                profitTrendChart: 'area',
                commissionTrendChart: 'line'
            };

            // 页面跳转使用 PHP 注入的 Laravel 路由清单，后端 API 保持显式 /api/front/... URL。
            function routeUrl(name, params, fallback) {
                return window.crmRoute ? window.crmRoute(name, params || {}, fallback || '') : (fallback || '');
            }

            CrmLang.switchUI();
            bindDashboardSwitches();
            loadDashboardData();

            $('#shareUrlList').on('click', '.J_copyShareUrl', function() {
                copyText($(this).attr('data-url') || '');
            });

            function loadDashboardData() {
                var requestId = ++dashboardRequestSequence;

                renderDashboardRangeControls();
                CrmAjax.request({
                    guard: 'front',
                    url: '/api/front/dashboard',
                    method: 'GET',
                    data: {days: activeRange},
                    success: function(res) {
                        if (requestId !== dashboardRequestSequence) {
                            return;
                        }
                        if (res.code === 1000 || res.code === 2000) {
                            renderDashboard(res.data || {});
                        } else {
                            layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                        }
                    },
                    error: function(res) {
                        if (requestId !== dashboardRequestSequence) {
                            return;
                        }
                        layer.msg((res && res.message) || CrmLang.t('common.error'), {icon: 2});
                    }
                });
            }

            function renderDashboard(data) {
                // 接口返回的用户、统计、画像数据会同时驱动数字卡片、实名认证引导、分享链接和图表。
                var user = data.user || {};
                var stats = data.stats || {};
                var profile = data.profile || {};
                var downloads = data.downloads || {};
                var period = data.period || {};
                var responseRange = Number(period.days);

                if ([7, 15, 30].indexOf(responseRange) !== -1) {
                    activeRange = responseRange;
                }
                renderDashboardRangeControls();

                $('#welcomeUser').text(user.user_name || user.email || '-');
                $('#customerTitle').text(user.title || '');
                $('#periodRange').text((period.from || '-') + ' - ' + (period.to || '-'));
                $('#identityGuideBtn').toggleClass('layui-hide', Number(profile.auth_status || user.auth_status || 0) === 1);

                $('#commissionRate').text(formatRate(profile.commission_rate));
                $('#totalCommission').text(formatMoney(stats.total_commission));
                $('#accountBalance').text(formatMoney(stats.account_balance));
                $('#accountEquity').text(formatMoney(profile.equity));
                $('#effectiveCredit').text(formatMoney(profile.effective_credit));
                $('#openOrdersCount').text(stats.open_orders_count || 0);

                $('#directAgentsCount').text(stats.direct_agents || 0);
                $('#indirectAgentsCount').text(stats.indirect_agents || 0);
                $('#directCustomersCount').text(stats.direct_customers || 0);
                $('#indirectCustomersCount').text(stats.indirect_customers || 0);
                $('#monthlyDeposit').text(formatMoney(stats.monthly_deposit));
                $('#monthlyWithdraw').text(formatMoney(stats.monthly_withdraw));
                $('#monthlyOpenOrders').text(stats.monthly_open_orders || 0);
                $('#monthlyClosedOrders').text(stats.monthly_closed_orders || 0);
                $('#monthlyCommission').text(formatMoney(stats.monthly_commission));
                $('#monthlyNetFlow').text(formatMoney(numeric(stats.monthly_deposit) - numeric(stats.monthly_withdraw)));

                renderShareUrls(resolveShareUrls(profile, user));
                renderNews(data.news || []);
                lastChartStats = stats;
                lastChartProfile = profile;
                lastChartSeries = data.series || null;
                renderCharts(stats, profile);
                scheduleChartResize();

                bindDownload('#pcDownloadLink', downloads.pc);
                bindDownload('#mobileDownloadLink', downloads.mobile);
            }

            function renderNews(news) {
                var html = '';
                $.each(news || [], function (_, item) {
                    var title = item.title || item.news_title || '-';
                    var meta = item.created_at || item.rec_crt_date || '';
                    var excerpt = stripHtml(item.summary || item.content || item.news_content || '');
                    html += '<li class="layui-timeline-item">';
                    // 动态新闻列表直接输出 Lucide 节点，插入 DOM 后由共享桥接器统一刷新。
                    html += '<i data-lucide="circle" class="layui-timeline-axis"></i>';
                    html += '<div class="layui-timeline-content layui-text">';
                    html += '<span class="dashboard-news-title">' + escapeHtml(title) + '</span>';
                    html += '<div class="dashboard-news-meta">' + escapeHtml(meta) + '</div>';
                    if (excerpt) {
                        html += '<div class="dashboard-news-excerpt">' + escapeHtml(truncateText(excerpt, 90)) + '</div>';
                    }
                    html += '</div></li>';
                });

                if (!html) {
                    html = '<li class="layui-timeline-item"><div class="layui-timeline-content layui-text layui-font-gray">' + CrmLang.t('common.noData') + '</div></li>';
                }
                $('#dashboardNews').html(html);
            }

            function stripHtml(value) {
                return String(value || '').replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim();
            }

            function truncateText(value, maxLength) {
                value = String(value || '');
                if (value.length <= maxLength) {
                    return value;
                }

                return value.substring(0, maxLength) + '...';
            }

            function renderShareUrls(items) {
                var html = '';

                $.each(items || [], function (_, item) {
                    if (!item || !item.url) {
                        return;
                    }

                    html += '<div class="dashboard-share-item">';
                    html += '<div>';
                    html += '<div class="dashboard-share-label">' + escapeHtml(labelText(item)) + '</div>';
                    html += '<a class="dashboard-share-url" href="' + escapeHtml(item.url) + '" target="_blank" rel="noopener">' + escapeHtml(item.url) + '</a>';
                    html += '</div>';
                    html += '<button type="button" class="layui-btn layui-btn-primary layui-btn-sm J_copyShareUrl" data-url="' + escapeHtml(item.url) + '">';
                    html += '<i data-lucide="layout-template"></i> ' + escapeHtml(CrmLang.t('common.copy'));
                    html += '</button>';
                    html += '</div>';
                });

                if (!html) {
                    html = '<div class="layui-font-gray">' + escapeHtml(CrmLang.t('front.no_share_url')) + '</div>';
                }

                $('#shareUrlList').html(html);
            }

            function resolveShareUrls(profile, user) {
                var userId = user && user.user_id;
                var base;
                var items = profile.share_urls || [];

                if (items.length) {
                    return items;
                }
                if (profile.share_url) {
                    return [{label: CrmLang.t('front.share_url'), url: profile.share_url}];
                }
                if (!userId) {
                    return [];
                }

                base = routeUrl('front_page_register', {inviter_id: userId});
                return [
                    {label_key: 'front.register_agent', url: base + '?account_type=1'},
                    {label_key: 'front.register_agent_zero', url: base + '?account_type=1&commission_mode=A'},
                    {label_key: 'front.register_member', url: base + '?account_type=2'},
                    {label_key: 'front.register_member_zero', url: base + '?account_type=2&commission_mode=A'}
                ];
            }

            function bindDashboardSwitches() {
                var style = localStorage.getItem('crm_ui_style') || localStorage.getItem('front_ui_style') || 'layui';
                var sound = localStorage.getItem('crm_sound_enabled') || localStorage.getItem('front_sound_enabled') || 'on';

                style = normalizeDashboardStyle(style);
                sound = sound === 'off' ? 'off' : 'on';
                if (!localStorage.getItem('crm_ui_style')) {
                    localStorage.setItem('crm_ui_style', style);
                }
                if (!localStorage.getItem('front_ui_style')) {
                    localStorage.setItem('front_ui_style', style);
                }
                if (!localStorage.getItem('crm_sound_enabled')) {
                    localStorage.setItem('crm_sound_enabled', sound);
                }
                if (!localStorage.getItem('front_sound_enabled')) {
                    localStorage.setItem('front_sound_enabled', sound);
                }

                renderDashboardSwitchLabels();

                $('.dashboard-switch-control').off('click.dashboardSwitchMenu').on('click.dashboardSwitchMenu', function (event) {
                    // 点击控制器外层时切换菜单展开状态，点击菜单按钮时由按钮事件处理
                    if ($(event.target).closest('button[data-dashboard-style-option], button[data-dashboard-locale-option], button[data-dashboard-sound-option]').length) {
                        return;
                    }
                    event.preventDefault();
                    event.stopPropagation();
                    toggleDashboardSwitchMenu($(this));
                });

                $('.dashboard-switch-control').off('keydown.dashboardSwitchMenu').on('keydown.dashboardSwitchMenu', function (event) {
                    if ($(event.target).closest('button[data-dashboard-style-option], button[data-dashboard-locale-option], button[data-dashboard-sound-option]').length) {
                        return;
                    }
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        event.stopPropagation();
                        toggleDashboardSwitchMenu($(this));
                    }
                });

                $('.dashboard-option-menu').off('click.dashboardSwitchMenu').on('click.dashboardSwitchMenu', function (event) {
                    event.stopPropagation();
                });

                $(document).off('click.dashboardSwitchMenu').on('click.dashboardSwitchMenu', function () {
                    closeDashboardSwitchMenus();
                });

                $(document).off('keydown.dashboardSwitchMenu').on('keydown.dashboardSwitchMenu', function (event) {
                    if (event.key === 'Escape') {
                        closeDashboardSwitchMenus();
                    }
                });

                $('[data-dashboard-style-option], [data-dashboard-style]').off('click.dashboardSwitch').on('click.dashboardSwitch', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    applyDashboardStyle($(this).attr('data-dashboard-style-option') || $(this).attr('data-dashboard-style') || 'layui');
                    closeDashboardSwitchMenus();
                });

                $('[data-dashboard-locale-option], [data-dashboard-locale]').off('click.dashboardSwitch').on('click.dashboardSwitch', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    applyDashboardLocale($(this).attr('data-dashboard-locale-option') || $(this).attr('data-dashboard-locale') || 'zh-CN');
                    closeDashboardSwitchMenus();
                });

                $('[data-dashboard-sound-option], [data-dashboard-sound]').off('click.dashboardSwitch').on('click.dashboardSwitch', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    applyDashboardSound($(this).attr('data-dashboard-sound-option') || $(this).attr('data-dashboard-sound') || 'on');
                    closeDashboardSwitchMenus();
                });

                applyDashboardSound(sound);
                renderChartSelectors();

                $('[data-dashboard-range]').off('click.dashboardRange').on('click.dashboardRange', function () {
                    var days = Number($(this).attr('data-dashboard-range'));

                    if ([7, 15, 30].indexOf(days) === -1 || days === activeRange) {
                        return;
                    }
                    activeRange = days;
                    renderDashboardRangeControls();
                    loadDashboardData();
                });

                $('.dashboard-chart-type').off('click.dashboardChart').on('click.dashboardChart', function () {
                    var target = $(this).attr('data-chart-target');
                    var type = $(this).attr('data-chart-type');

                    if (!target || ['bar', 'line', 'area', 'pie'].indexOf(type) === -1) {
                        return;
                    }
                    chartTypes[target] = type;
                    renderChartSelectors();
                    if (lastChartStats && lastChartProfile) {
                        renderCharts(lastChartStats, lastChartProfile);
                    }
                });

                window.addEventListener('crm:theme-change', function () {
                    if (lastChartStats && lastChartProfile) {
                        renderCharts(lastChartStats, lastChartProfile);
                        return;
                    }
                    scheduleChartResize();
                });
            }

            function toggleDashboardSwitchMenu($control) {
                var isOpen = $control.hasClass('is-open');

                closeDashboardSwitchMenus();
                if (isOpen) {
                    return;
                }
                $control.addClass('is-open').attr('aria-expanded', 'true');
            }

            function closeDashboardSwitchMenus() {
                $('.dashboard-switch-control.is-open').removeClass('is-open').attr('aria-expanded', 'false');
            }

            function currentDashboardLocale() {
                return CrmLang.getLocale ? CrmLang.getLocale() : (localStorage.getItem('crm_locale') || 'zh-CN');
            }

            function currentDashboardSound() {
                return (localStorage.getItem('crm_sound_enabled') || localStorage.getItem('front_sound_enabled') || 'on') === 'off' ? 'off' : 'on';
            }

            function applyDashboardStyle(nextStyle) {
                if (nextStyle === 'crmui') {
                    localStorage.setItem('crm_ui_style', nextStyle);
                    localStorage.setItem('front_ui_style', nextStyle);
                    renderDashboardSwitchLabels();
                    window.top.location.href = routeUrl('front_crmui_app', {path: 'dashboard'}, dashboardRoutes.crmuiDashboard || '/front-crmui/dashboard');
                    return;
                }
                if (nextStyle === 'naive') {
                    localStorage.setItem('crm_ui_style', nextStyle);
                    localStorage.setItem('front_ui_style', nextStyle);
                    renderDashboardSwitchLabels();
                    window.top.location.href = routeUrl('front_naive_app', {path: 'dashboard'}, dashboardRoutes.naiveDashboard || '/front-naive/dashboard');
                    return;
                }
                // 仅持久化服务端已实现的前台样式入口。
                nextStyle = normalizeDashboardStyle(nextStyle);
                localStorage.setItem('crm_ui_style', nextStyle);
                localStorage.setItem('front_ui_style', nextStyle);
                renderDashboardSwitchLabels();
            }

            function applyDashboardLocale(nextLocale) {
                // 语言切换后刷新当前 iframe，使服务端 Blade 文案和前端 JS 文案都重新对齐。
                nextLocale = nextLocale === 'en' ? 'en' : 'zh-CN';
                if (CrmLang.loadLanguage) {
                    CrmLang.loadLanguage(nextLocale).then(function () {
                        window.location.reload();
                    });
                    return;
                }
                localStorage.setItem('crm_locale', nextLocale);
                localStorage.setItem('front_lang', nextLocale);
                document.documentElement.setAttribute('lang', nextLocale);
                window.location.reload();
            }

            function applyDashboardSound(nextSound) {
                nextSound = nextSound === 'off' ? 'off' : 'on';
                localStorage.setItem('crm_sound_enabled', nextSound);
                localStorage.setItem('front_sound_enabled', nextSound);
                renderDashboardSwitchLabels();
            }

            function renderDashboardSwitchLabels() {
                var isEn = (CrmLang.getLocale && CrmLang.getLocale() === 'en') || localStorage.getItem('crm_locale') === 'en';
                var currentStyle = localStorage.getItem('crm_ui_style') || localStorage.getItem('front_ui_style') || 'layui';
                var currentLocale = currentDashboardLocale();
                var currentSound = currentDashboardSound();

                // 控制台图标下拉菜单统一读取 *-option 属性；旧属性仅用于兼容，避免出现两套选中状态。
                setOptionLabel($('[data-dashboard-style-option="layui"], [data-dashboard-style="layui"]'), isEn ? 'Layui Classic' : 'Layui 经典');
                setOptionLabel($('[data-dashboard-style-option="crmui"], [data-dashboard-style="crmui"]'), isEn ? 'CrmUI Focus' : 'CrmUI 专注');
                setOptionLabel($('[data-dashboard-style-option="naive"], [data-dashboard-style="naive"]'), isEn ? 'Naive Clean' : 'Naive 清爽');
                $('[data-dashboard-style-current]').text(styleShortLabel(currentStyle));
                $('[data-dashboard-style-option], [data-dashboard-style]').each(function () {
                    var value = $(this).attr('data-dashboard-style-option') || $(this).attr('data-dashboard-style');
                    var isActive = value === currentStyle;
                    $(this).toggleClass('is-active', isActive)
                        .removeClass('active')
                        .attr('aria-pressed', isActive ? 'true' : 'false')
                        .attr('aria-checked', isActive ? 'true' : 'false');
                });

                $('[data-dashboard-locale-option], [data-dashboard-locale]').each(function () {
                    var value = $(this).attr('data-dashboard-locale-option') || $(this).attr('data-dashboard-locale');
                    var label = value === 'en' ? CrmLang.t('common.lang_en') : CrmLang.t('common.lang_zh');
                    var isActive = value === currentLocale;
                    setOptionLabel($(this), label);
                    $(this).toggleClass('is-active', isActive)
                        .removeClass('active')
                        .attr('aria-pressed', isActive ? 'true' : 'false')
                        .attr('aria-checked', isActive ? 'true' : 'false');
                });
                $('[data-dashboard-locale-current]').text(currentLocale === 'en' ? 'EN' : '中文');
                $('[data-dashboard-sound-option], [data-dashboard-sound]').each(function () {
                    var value = $(this).attr('data-dashboard-sound-option') || $(this).attr('data-dashboard-sound');
                    var label = value === 'off' ? (isEn ? 'Sound Off' : '声音关闭') : (isEn ? 'Sound On' : '声音开启');
                    var isActive = value === currentSound;
                    setOptionLabel($(this), label);
                    $(this).toggleClass('is-active', isActive)
                        .removeClass('active')
                        .attr('aria-pressed', isActive ? 'true' : 'false')
                        .attr('aria-checked', isActive ? 'true' : 'false');
                });
                $('[data-dashboard-sound-current]').text(currentSound === 'off' ? (isEn ? 'Off' : '关闭') : (isEn ? 'On' : '开启'));
                renderDashboardRangeControls();
                renderChartSelectors();
            }

            function normalizeDashboardStyle(style) {
                return style === 'crmui' || style === 'naive' ? style : 'layui';
            }

            function styleShortLabel(style) {
                if (style === 'crmui') {
                    return 'CrmUI';
                }
                if (style === 'naive') {
                    return 'Naive';
                }
                return 'Layui';
            }

            function setOptionLabel($items, label) {
                // 菜单按钮的最后一个 span 是文案位置；如果没有 span，才整体替换按钮文本。
                $items.each(function () {
                    var $label = $(this).children('span').last();
                    if ($label.length) {
                        $label.text(label);
                        return;
                    }
                    $(this).text(label);
                });
            }

            function labelText(item) {
                if (item.label_key) {
                    return CrmLang.t(item.label_key);
                }

                return item.label || '';
            }

            function copyText(value) {
                var $input;

                if (!value) {
                    layer.msg(CrmLang.t('front.no_share_url'), {icon: 0});
                    return;
                }

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(value).then(function() {
                        layer.msg(CrmLang.t('common.success'), {icon: 1});
                    }, function() {
                        layer.msg(CrmLang.t('front.share_url_selected'), {icon: 0});
                    });
                    return;
                }

                $input = $('<input>').val(value).appendTo('body');
                $input.select();
                document.execCommand('copy');
                $input.remove();
                layer.msg(CrmLang.t('front.share_url_selected'), {icon: 0});
            }

            function escapeHtml(value) {
                if (typeof CrmTable !== 'undefined' && CrmTable.escapeHtml) {
                    return CrmTable.escapeHtml(value);
                }

                return String(value || '').replace(/[&<>"']/g, '');
            }

            function bindDownload(selector, config) {
                var url = config && config.url ? config.url : '#';
                var disabled = !url || url === '#' || isObsoleteVersionProbe(url);

                $(selector)
                    .attr('href', disabled ? 'javascript:;' : url)
                    .toggleClass('layui-btn-disabled', disabled);
            }

            function isObsoleteVersionProbe(url) {
                var normalized = String(url || '').toLowerCase().trim();

                return normalized.indexOf('xapi.yhchj.com/version') !== -1 || /\/version([/?#].*)?$/.test(normalized);
            }

            function formatMoney(value) {
                var numberValue = Number(value || 0);
                if (isNaN(numberValue)) numberValue = 0;
                return numberValue.toFixed(2);
            }

            function formatRate(value) {
                var numberValue = Number(value || 0);
                if (isNaN(numberValue)) numberValue = 0;
                if (numberValue > 0 && numberValue <= 1) {
                    numberValue = numberValue * 100;
                }
                return numberValue.toFixed(2) + '%';
            }

            function renderChartSelectors() {
                var labels = {
                    bar: CrmLang.t('front.chart_bar'),
                    line: CrmLang.t('front.chart_line'),
                    area: CrmLang.t('front.chart_area'),
                    pie: CrmLang.t('front.chart_pie')
                };

                $('.dashboard-chart-type').each(function () {
                    var $button = $(this);
                    var target = $button.attr('data-chart-target');
                    var type = $button.attr('data-chart-type') || 'bar';
                    var current = chartTypes[target] || 'bar';
                    var label = labels[type] || labels.bar;
                    var isActive = type === current;

                    $button.toggleClass('is-active', isActive)
                        .attr('aria-pressed', isActive ? 'true' : 'false')
                        .attr('aria-label', label)
                        .attr('title', label);
                    $button.find('.crm-sr-only').text(label);
                });
            }

            function renderDashboardRangeControls() {
                var rangeLabelKeys = {
                    7: 'front.range_days_7',
                    15: 'front.range_days_15',
                    30: 'front.range_days_30'
                };

                $('[data-dashboard-range]').each(function () {
                    var $button = $(this);
                    var days = Number($button.attr('data-dashboard-range'));
                    var isActive = days === activeRange;
                    var label = CrmLang.t(rangeLabelKeys[days] || 'front.range_days_30');

                    $button.toggleClass('is-active', isActive)
                        .attr('aria-pressed', isActive ? 'true' : 'false');
                    $button.children('span').text(label);
                });
            }

            function chartSeries(name, values, type, labelColor) {
                if (type === 'pie') {
                    return [{
                        name: name,
                        type: 'pie',
                        radius: ['30%', '64%'],
                        center: ['50%', '52%'],
                        roseType: 'radius',
                        avoidLabelOverlap: true,
                        label: {show: true, formatter: '{b}\n{d}%', color: labelColor, fontSize: 11, fontWeight: 500},
                        labelLine: {smooth: true, length: 10, length2: 8, lineStyle: {width: 1.5}},
                        emphasis: {scale: true, scaleSize: 8, itemStyle: {shadowBlur: 10, shadowColor: 'rgba(0, 0, 0, 0.3)'}},
                        data: values.map(function (item) {
                            return {name: item.name, value: item.value};
                        })
                    }];
                }
                var baseBarSeries = {
                    itemStyle: {borderRadius: [8, 8, 2, 2]}
                };
                baseBarSeries.itemStyle.shadowBlur = 4;
                baseBarSeries.itemStyle.shadowColor = 'rgba(0, 0, 0, 0.1)';

                return [{
                    name: name,
                    type: type === 'area' ? 'line' : type,
                    smooth: type !== 'bar',
                    showSymbol: type !== 'bar',
                    symbolSize: 7,
                    areaStyle: type === 'area' ? {opacity: 0.18} : null,
                    barWidth: type === 'bar' ? 22 : null,
                    itemStyle: baseBarSeries.itemStyle,
                    label: {show: true, position: 'top', color: labelColor, fontSize: 11, fontWeight: 500},
                    lineStyle: {width: 3, shadowBlur: 4, shadowColor: 'rgba(0, 0, 0, 0.15)'},
                    emphasis: {focus: 'series', itemStyle: {shadowBlur: 10, shadowColor: 'rgba(0, 0, 0, 0.3)'}},
                    data: values.map(function (item) {
                        return item.value;
                    })
                }];
            }

            function chartThemeTokens() {
                var styles = getComputedStyle(document.documentElement);
                var readToken = function (name, fallback) {
                    return styles.getPropertyValue(name).trim() || fallback;
                };

                return {
                    palette: [
                        readToken('--front-blue', '#2563eb'),
                        readToken('--front-accent', '#0f9f8f'),
                        readToken('--front-warn', '#b7791f'),
                        readToken('--front-danger', '#d14343'),
                        readToken('--front-cyan', '#0891b2')
                    ],
                    panel: readToken('--front-panel', '#ffffff'),
                    text: readToken('--front-text', '#334155'),
                    strong: readToken('--front-strong', '#0f172a'),
                    line: readToken('--front-line', '#dbe3ee'),
                    splitLine: readToken('--front-table-head', '#f4f7fb')
                };
            }

            function chartOption(name, values, type) {
                var theme = chartThemeTokens();

                var option = {
                    color: theme.palette,
                    tooltip: {trigger: 'item', confine: true, backgroundColor: theme.panel, borderColor: theme.line, borderWidth: 1, textStyle: {color: theme.strong}, padding: [8, 12], borderRadius: 6},
                    legend: {bottom: 0, icon: 'roundRect', itemWidth: 10, itemHeight: 10, textStyle: {color: theme.text}},
                    animationDuration: 450,
                    animationEasing: 'cubicOut'
                };
                if (type !== 'pie') {
                    option.tooltip = {trigger: 'axis', confine: true, axisPointer: {type: type === 'bar' ? 'shadow' : 'line', lineStyle: {type: 'dashed', width: 1.5}}, backgroundColor: theme.panel, borderColor: theme.line, borderWidth: 1, textStyle: {color: theme.strong}, padding: [8, 12], borderRadius: 6};
                    option.grid = {left: 56, right: 24, top: 42, bottom: 42, containLabel: true};
                    option.xAxis = {
                        type: 'category',
                        data: values.map(function (item) { return item.name; }),
                        axisTick: {show: false},
                        axisLine: {lineStyle: {color: theme.line, width: 1.5}},
                        axisLabel: {color: theme.text, interval: 0, fontSize: 11}
                    };
                    option.yAxis = {
                        type: 'value',
                        axisLabel: {color: theme.text, fontSize: 11},
                        splitLine: {lineStyle: {color: theme.splitLine, type: 'dashed', width: 1}}
                    };
                    option.legend = null;
                }
                option.series = chartSeries(name, values, type, theme.strong);
                return option;
            }

            function renderCharts(stats, profile) {
                // 图表数据全部从最新统计快照生成，切换柱状图/折线图/面积图/饼图时不会重新请求接口。
                if (typeof echarts === 'undefined') {
                    return;
                }

                var funds = [
                    {name: CrmLang.t('front.total_funds'), value: numeric(stats.account_balance || profile.total_funds)},
                    {name: CrmLang.t('front.equity'), value: numeric(profile.equity)},
                    {name: CrmLang.t('front.effective_credit'), value: numeric(profile.effective_credit)},
                    {name: CrmLang.t('front.monthly_deposit'), value: numeric(stats.monthly_deposit)},
                    {name: CrmLang.t('front.monthly_withdraw'), value: numeric(stats.monthly_withdraw)}
                ];
                setChart('fundsChart', chartOption(CrmLang.t('front.funds_chart'), funds, chartTypes.fundsChart));

                var network = [
                    {name: CrmLang.t('front.direct_agents'), value: numeric(stats.direct_agents)},
                    {name: CrmLang.t('front.indirect_agents'), value: numeric(stats.indirect_agents)},
                    {name: CrmLang.t('front.direct_customers'), value: numeric(stats.direct_customers)},
                    {name: CrmLang.t('front.indirect_customers'), value: numeric(stats.indirect_customers)}
                ];
                setChart('networkChart', chartOption(CrmLang.t('front.network_chart'), network, chartTypes.networkChart));

                var orders = [
                    {name: CrmLang.t('front.open_orders'), value: numeric(stats.open_orders_count)},
                    {name: CrmLang.t('front.monthly_open_orders'), value: numeric(stats.monthly_open_orders)},
                    {name: CrmLang.t('front.monthly_closed_orders'), value: numeric(stats.monthly_closed_orders)}
                ];
                setChart('orderChart', chartOption(CrmLang.t('front.order_chart'), orders, chartTypes.orderChart));

                var commission = [
                    {name: CrmLang.t('front.monthly_commission'), value: numeric(stats.monthly_commission)},
                    {name: CrmLang.t('front.total_commission'), value: numeric(stats.total_commission)}
                ];
                setChart('commissionChart', chartOption(CrmLang.t('front.commission_chart'), commission, chartTypes.commissionChart));

                renderTrendCharts(lastChartSeries);
            }

            /**
             * renderTrendCharts 渲染四张日粒度趋势图。
             * 数据来自接口 series 字段；无 series 时保持占位不报错。
             */
            function renderTrendCharts(series) {
                if (!series || !series.dates || !series.dates.length) {
                    return;
                }

                setChart('flowTrendChart', multiSeriesOption(series.dates, [
                    {name: CrmLang.t('front.monthly_deposit'), values: series.deposit || []},
                    {name: CrmLang.t('front.monthly_withdraw'), values: series.withdraw || []}
                ], chartTypes.flowTrendChart));

                setChart('orderTrendChart', multiSeriesOption(series.dates, [
                    {name: CrmLang.t('front.monthly_open_orders'), values: series.open_orders || []},
                    {name: CrmLang.t('front.monthly_closed_orders'), values: series.closed_orders || []}
                ], chartTypes.orderTrendChart));

                setChart('profitTrendChart', multiSeriesOption(series.dates, [
                    {name: CrmLang.t('front.profit_trend_chart'), values: series.profit || []}
                ], chartTypes.profitTrendChart));

                setChart('commissionTrendChart', multiSeriesOption(series.dates, [
                    {name: CrmLang.t('front.monthly_commission'), values: series.commission || []}
                ], chartTypes.commissionTrendChart));
            }

            /**
             * multiSeriesOption 构建多序列 ECharts 配置，支持柱状/折线/面积/饼图四种查看方式。
             * 饼图口径为各序列在整个周期内的合计占比（日粒度饼图无业务含义）。
             */
            function multiSeriesOption(dates, seriesList, type) {
                var theme = chartThemeTokens();
                var option = {
                    color: theme.palette,
                    animationDuration: 450,
                    animationEasing: 'cubicOut'
                };

                if (type === 'pie') {
                    option.tooltip = {trigger: 'item', confine: true, backgroundColor: theme.panel, borderColor: theme.line, borderWidth: 1, textStyle: {color: theme.strong}, padding: [8, 12], borderRadius: 6};
                    option.legend = {bottom: 0, icon: 'roundRect', itemWidth: 10, itemHeight: 10, textStyle: {color: theme.text}};
                    option.series = [{
                        type: 'pie',
                        radius: ['30%', '64%'],
                        center: ['50%', '48%'],
                        avoidLabelOverlap: true,
                        label: {show: true, formatter: '{b}\n{d}%', color: theme.strong, fontSize: 11, fontWeight: 500},
                        labelLine: {smooth: true, length: 10, length2: 8, lineStyle: {width: 1.5}},
                        data: seriesList.map(function (item) {
                            var total = 0;
                            var i;
                            for (i = 0; i < item.values.length; i += 1) {
                                total += numeric(item.values[i]);
                            }
                            return {name: item.name, value: Number(total.toFixed(2))};
                        })
                    }];
                    return option;
                }

                option.tooltip = {trigger: 'axis', confine: true, axisPointer: {type: type === 'bar' ? 'shadow' : 'line', lineStyle: {type: 'dashed', width: 1.5}}, backgroundColor: theme.panel, borderColor: theme.line, borderWidth: 1, textStyle: {color: theme.strong}, padding: [8, 12], borderRadius: 6};
                option.legend = {bottom: 0, icon: 'roundRect', itemWidth: 10, itemHeight: 10, textStyle: {color: theme.text}};
                option.grid = {left: 46, right: 18, top: 18, bottom: 34, containLabel: true};
                option.xAxis = {
                    type: 'category',
                    data: dates,
                    axisTick: {show: false},
                    axisLine: {lineStyle: {color: theme.line, width: 1.5}},
                    axisLabel: {color: theme.text, fontSize: 11, hideOverlap: true}
                };
                option.yAxis = {
                    type: 'value',
                    axisLabel: {color: theme.text, fontSize: 11},
                    splitLine: {lineStyle: {color: theme.splitLine, type: 'dashed', width: 1}}
                };
                option.series = seriesList.map(function (item) {
                    return {
                        name: item.name,
                        type: type === 'area' ? 'line' : type,
                        smooth: type !== 'bar',
                        showSymbol: false,
                        symbolSize: 6,
                        areaStyle: type === 'area' ? {opacity: 0.18} : null,
                        barMaxWidth: type === 'bar' ? 18 : null,
                        itemStyle: type === 'bar' ? {borderRadius: [4, 4, 0, 0]} : null,
                        lineStyle: {width: 2},
                        emphasis: {focus: 'series'},
                        data: (item.values || []).map(function (value) {
                            return numeric(value);
                        })
                    };
                });

                return option;
            }

            function setChart(id, option) {
                // 每个 DOM 只初始化一次 ECharts 实例，后续仅 setOption，减少控制台反复切换时的重绘成本。
                var el = document.getElementById(id);

                if (!el) {
                    return;
                }
                if (!charts[id]) {
                    charts[id] = echarts.init(el);
                }
                try {
                    charts[id].setOption(option, true);
                    charts[id].resize();
                } catch (e) {
                    $(el).html('<div class="front-inline-notice">' + CrmLang.t('front.chart_render_failed') + '</div>');
                }
            }

            function resizeCharts() {
                $.each(charts, function(_, chart) {
                    if (chart && chart.resize) {
                        chart.resize();
                    }
                });
            }

            function scheduleChartResize() {
                var run = function () {
                    resizeCharts();
                    if (lastChartStats) {
                        resizeCharts();
                    }
                };

                if (window.requestAnimationFrame) {
                    window.requestAnimationFrame(run);
                }
                setTimeout(run, 80);
                setTimeout(run, 260);
            }

            function numeric(value) {
                var numberValue = Number(value || 0);
                return isNaN(numberValue) ? 0 : Number(numberValue.toFixed(2));
            }

            $(window).on('resize', function() {
                resizeCharts();
            });

            $(window).on('load', function () {
                if (lastChartStats) {
                    renderCharts(lastChartStats, lastChartProfile || {});
                }
                scheduleChartResize();
            });
        });
    });

    registry['deposit/index'] = once(function () {
        // Source: deposit/index.js
        /**
         * Layui 前台入金页入口脚本。
         * 初始化支付通道 Tab、日期筛选、入金提交和历史表格汇总。
         */
        layui.use(['jquery', 'form', 'table', 'layer', 'element'], function () {
            var $ = layui.jquery;

            // 支付通道展示、选择和金额联动统一交给公共管理器。
            var manager = PayChannelManager.create({
                container: '#depositChannelList',
                input: '#depositChannel',
                payChannelInput: '#pay_channel',
                passagewayInput: '#passageway',
                amountInput: '#deposit_amt_usd',
                actualInput: '#deposit_pay_amt_rmb',
                rateInput: '#depositExchangeRate'
            });
            // 先加载语言和日期控件，再初始化页面配置、表格和提交事件。
            function boot() {
                if (typeof CrmLang !== 'undefined') {
                    CrmLang.updateUI();
                }
                if (typeof CrmDateRange !== 'undefined') {
                    CrmDateRange.init($('.deposit-page'));
                }

                DepositPageCore.init({
                    manager: manager,
                    pageApi: '/api/front/deposits/form-options',
                    pageMethod: 'GET',
                    submitApi: '/api/front/deposits/submissions',
                    historyApi: '/api/front/deposits/history',
                    historyMethod: 'GET',
                    formSelector: '#depositForm',
                    filterForm: '#depositSearchForm',
                    tableElem: '#depositHistoryTable',
                    summaryElem: '#depositHistorySummary',
                    amountInput: '#deposit_amt_usd',
                    userIdInput: '#depositUserId',
                    disabledNotice: '#depositDisabledNotice',
                    submitButton: '#depositBtn',
                    retryButton: '#openBlankBtn',
                    resetButton: '#depositSearchReset',
                    columns: [
                        {field: 'order_no', title: CrmLang.t('front.order_no'), minWidth: 180},
                        {field: 'userId', title: CrmLang.t('front.user_id'), width: 120},
                        {field: 'depositType', title: CrmLang.t('front.deposit_type'), width: 150},
                        {field: 'exchange_rate', title: CrmLang.t('front.exchange_rate'), width: 120},
                        {field: 'depositActProfit', title: CrmLang.t('front.deposit_amount'), width: 140, format: 'money'},
                        {field: 'status_text', title: CrmLang.t('common.status'), width: 120},
                        {field: 'depositComment', title: CrmLang.t('front.deposit_comment'), minWidth: 180},
                        {field: 'modify_time', title: CrmLang.t('front.flow_time'), minWidth: 170}
                    ]
                });

            }

            if (typeof CrmLang !== 'undefined' && CrmLang.loadLanguage) {
                CrmLang.loadLanguage(CrmLang.getLocale()).then(boot).catch(boot);
            } else {
                boot();
            }
        });
    });

    registry['flow/index'] = once(function () {
        // Source: flow/index.js
        layui.use(['jquery', 'form', 'table', 'element', 'layer'], function () {
            var $ = layui.jquery;
            var form = layui.form;
            var table = layui.table;
            var element = layui.element;
            var activeType = 'all';
            var renderedTables = {};

            var flowEndpoints = {
                'all': '/api/front/flows/account',
                'deposit': '/api/front/flows/deposits',
                'withdraw': '/api/front/flows/withdrawals',
                'withdraw_apply': '/api/front/flows/withdrawal-applications',
                'direct_deposit': '/api/front/flows/direct-deposits',
                'direct_withdraw': '/api/front/flows/direct-withdrawals',
                'direct_agents_deposit': '/api/front/flows/direct-agent-deposits',
                'direct_agents_withdraw': '/api/front/flows/direct-agent-withdrawals'
            };

            function t(key) {
                return typeof CrmLang !== 'undefined' && CrmLang.t ? CrmLang.t(key) : key;
            }

            function money(value) {
                var numberValue = Number(value || 0);
                return isNaN(numberValue) ? '-' : numberValue.toFixed(2);
            }

            function bankNo(value) {
                value = String(value || '');
                return value.length > 4 ? value.replace(/.(?=.{4})/g, '*') : value;
            }

            function column(field, titleKey, width, templet, format) {
                var config = {
                    field: field,
                    title: t(titleKey),
                    minWidth: width || 120,
                    align: 'center',
                    format: format || ''
                };

                if (templet) {
                    config.templet = templet;
                }

                return config;
            }

            var columns = {
                all: [
                    {type: 'numbers', title: '#', width: 70},
                    column('order_no', 'front.order_no', 180),
                    column('user_id', 'front.user_id', 120),
                    column('user_name', 'front.user_name', 140),
                    column('flow_type_text', 'front.flow_type', 140),
                    column('amount', 'front.amount', 140, function (d) {
                        return money(d.amount);
                    }, 'money'),
                    column('actual_amount', 'front.actual_amount', 140, function (d) {
                        return money(d.actual_amount);
                    }, 'money'),
                    column('status', 'common.status', 120),
                    column('created_at', 'front.flow_time', 170)
                ],
                deposit: [
                    {type: 'numbers', title: '#', width: 70},
                    column('order_no', 'front.order_no', 180),
                    column('userId', 'front.user_id', 120),
                    column('depositType', 'front.deposit_type', 140),
                    column('depositComment', 'front.deposit_comment', 180),
                    column('depositActProfit', 'front.deposit_amount', 140, function (d) {
                        return money(d.depositActProfit);
                    }, 'money'),
                    column('modify_time', 'front.flow_time', 170)
                ],
                withdraw: [
                    {type: 'numbers', title: '#', width: 70},
                    column('order_no', 'front.order_no', 180),
                    column('userId', 'front.user_id', 120),
                    column('withdrawalType', 'front.withdraw_type', 140),
                    column('withdrawalType2', 'front.apply_status', 160),
                    column('withdrawalActProfit', 'front.withdraw_amount', 140, function (d) {
                        return money(d.withdrawalActProfit);
                    }, 'money'),
                    column('withdrawalDate', 'front.flow_time', 170)
                ],
                withdraw_apply: [
                    {type: 'numbers', title: '#', width: 70},
                    column('order_no', 'front.order_no', 180),
                    column('userId', 'front.user_id', 120),
                    column('userName', 'front.user_name', 140),
                    column('applyamount', 'front.apply_amount', 130, function (d) {
                        return money(d.applyamount);
                    }, 'money'),
                    column('actdraw', 'front.actual_amount', 130, function (d) {
                        return money(d.actdraw);
                    }, 'money'),
                    column('drawpoundage', 'front.fee', 120, function (d) {
                        return money(d.drawpoundage);
                    }, 'money'),
                    column('drawrate', 'front.exchange_rate', 120),
                    column('drawbankno', 'front.bank_no', 160, function (d) {
                        return bankNo(d.drawbankno);
                    }),
                    column('drawbankclass', 'front.bank_name', 160),
                    column('applystatus', 'front.apply_status', 120),
                    column('applyremark', 'front.reject_reason', 180),
                    column('rec_crt_date', 'front.flow_time', 170)
                ],
                direct_deposit: [
                    {type: 'numbers', title: '#', width: 70},
                    column('order_no', 'front.order_no', 180),
                    column('userId', 'front.user_id', 120),
                    column('directType', 'front.deposit_type', 140),
                    column('directProfit', 'front.deposit_amount', 140, function (d) {
                        return money(d.directProfit);
                    }, 'money'),
                    column('directComment', 'front.deposit_source', 180),
                    column('directModifyTime', 'front.flow_time', 170)
                ],
                direct_withdraw: [
                    {type: 'numbers', title: '#', width: 70},
                    column('order_no', 'front.order_no', 180),
                    column('userId', 'front.user_id', 120),
                    column('directdrawalComment', 'front.withdraw_type', 160),
                    column('directdrawalActProfit', 'front.withdraw_amount', 140, function (d) {
                        return money(d.directdrawalActProfit);
                    }, 'money'),
                    column('directdrawalModifyTime', 'front.flow_time', 170)
                ],
                direct_agents_deposit: [
                    {type: 'numbers', title: '#', width: 70},
                    column('order_no', 'front.order_no', 180),
                    column('userId', 'front.user_id', 120),
                    column('directType', 'front.deposit_type', 140),
                    column('directProfit', 'front.deposit_amount', 140, function (d) {
                        return money(d.directProfit);
                    }, 'money'),
                    column('directComment', 'front.deposit_source', 180),
                    column('directModifyTime', 'front.flow_time', 170)
                ],
                direct_agents_withdraw: [
                    {type: 'numbers', title: '#', width: 70},
                    column('order_no', 'front.order_no', 180),
                    column('userId', 'front.user_id', 120),
                    column('directdrawalComment', 'front.withdraw_type', 160),
                    column('directdrawalActProfit', 'front.withdraw_amount', 140, function (d) {
                        return money(d.directdrawalActProfit);
                    }, 'money'),
                    column('directdrawalModifyTime', 'front.flow_time', 170)
                ]
            };

            function formFor(type) {
                return $('.J_flowForm[data-flow-type="' + type + '"]');
            }

            function collect(type) {
                var params = {flow_type: type};

                $.each(formFor(type).serializeArray(), function (_, item) {
                    if (item.value !== null && item.value !== '') {
                        params[item.name] = item.value;
                    }
                });

                return params;
            }

            function syncWithdrawSource(type) {
                var show = ['withdraw', 'withdraw_apply', 'direct_withdraw', 'direct_agents_withdraw'].indexOf(type) !== -1;
                formFor(type).find('.J_withdrawSource').toggleClass('is-hidden', !show);
            }

            function renderTable(type) {
                var tableId = 'flowTable_' + type;
                var config = {
                    elem: '#' + tableId,
                    id: tableId,
                    url: flowEndpoints[type] || flowEndpoints.all,
                    method: 'GET',
                    where: collect(type),
                    cols: [columns[type] || columns.all],
                    height: 420,
                    summaryElem: '#flowSummary_' + type
                };

                syncWithdrawSource(type);
                if (renderedTables[type]) {
                    table.reloadData(tableId, {
                        url: config.url,
                        method: 'GET',
                        where: config.where,
                        page: {curr: 1}
                    });
                    return;
                }

                table.render(CrmTable.layuiConfig('front', config));
                renderedTables[type] = true;
            }

            function preRenderAllTables() {
                // 预渲染所有 tab 的表格，避免首次点击时 DOM 重建导致页面抖动
                var types = ['all', 'deposit', 'withdraw', 'withdraw_apply', 'direct_deposit', 'direct_withdraw', 'direct_agents_deposit', 'direct_agents_withdraw'];
                types.forEach(function (type) {
                    if (!renderedTables[type]) {
                        var tableId = 'flowTable_' + type;
                        var endpoint = flowEndpoints[type] || flowEndpoints.all;
                        var config = {
                            elem: '#' + tableId,
                            id: tableId,
                            method: 'GET',
                            where: collect(type),
                            cols: [columns[type] || columns.all],
                            height: 420,
                            summaryElem: '#flowSummary_' + type
                        };

                        if (type === activeType) {
                            config.url = endpoint;
                        } else {
                            config.data = [];
                        }

                        table.render(CrmTable.layuiConfig('front', config));
                        renderedTables[type] = true;
                    }
                });
            }

            form.on('submit(flowSearch)', function (data) {
                var type = $(data.form).attr('data-flow-type') || activeType;
                renderTable(type);
                return false;
            });

            $('.J_flowReset').on('click', function () {
                var $form = $(this).closest('.J_flowForm');
                var type = $form.attr('data-flow-type') || activeType;

                $form[0].reset();
                form.render();
                renderTable(type);
            });

            element.on('tab(frontFlowTabs)', function () {
                activeType = $(this).attr('lay-id') || activeType;
                syncWithdrawSource(activeType);
                // 切换 tab 时只重载数据，不重新渲染表格 DOM
                if (renderedTables[activeType]) {
                    table.reloadData('flowTable_' + activeType, {
                        url: flowEndpoints[activeType],
                        method: 'GET',
                        where: collect(activeType),
                        page: {curr: 1}
                    });
                }
            });

            function boot() {
                if (typeof CrmLang !== 'undefined') {
                    CrmLang.switchUI();
                }
                if (typeof CrmDateRange !== 'undefined') {
                    CrmDateRange.init($('.flow-page'));
                }
                form.render();
                $('.J_flowForm').each(function () {
                    syncWithdrawSource($(this).attr('data-flow-type'));
                });
                preRenderAllTables();
            }

            if (typeof CrmLang !== 'undefined' && CrmLang.loadLanguage) {
                CrmLang.loadLanguage(CrmLang.getLocale()).then(boot).catch(boot);
            } else {
                boot();
            }
        });
    });

    registry['profile/change-email'] = once(function () {
        // Source: profile/change-email.js
        layui.use(['form', 'layer', 'jquery'], function() {
            var form = layui.form, layer = layui.layer, $ = layui.jquery;
            
            // 初始化当前页面的多语言文案。
            CrmLang.switchUI();

            form.verify({
                profileRequired: function(value, elem) {
                    if (!$.trim(value || '')) {
                        return requiredMessage(elem);
                    }
                }
            });

            function translateOr(key, fallback) {
                var value = CrmLang.t(key);
                return value && value !== key ? value : fallback;
            }

            // 生成带表单名和字段名的必填提示，让邮箱页提交时能准确指出缺少的新邮箱。
            function requiredTemplateMessage(formTitle, fieldTitle) {
                var template = translateOr('front.profile_required_message', '请填写【{form}】的【{field}】');
                return template
                    .replace('{form}', $.trim(formTitle || translateOr('profile.changeEmail', '修改邮箱')))
                    .replace('{field}', $.trim(fieldTitle || ''));
            }

            // 从当前输入框上溯卡片和标签，确保提示语对应用户点击的邮箱表单。
            function requiredMessage(elem) {
                var $elem = $(elem);
                var formTitle = $.trim($elem.closest('.layui-card').find('.layui-card-header').first().text()) || translateOr('profile.changeEmail', '修改邮箱');
                var fieldTitle = $.trim($elem.closest('.layui-form-item').find('.layui-form-label').first().text()) || $elem.attr('name') || '';

                return requiredTemplateMessage(formTitle, fieldTitle);
            }

            form.on('submit(emailSubmit)', function(data) {
                CrmAjax.request({
                    guard: 'front',
                    url: '/api/front/profile/email',
                    method: 'POST',
                    data: data.field,
                    success: function(res) {
                        if (res.code === 1000 || res.code === 2000) {
                            layer.msg(CrmLang.t('profile.emailChanged'), {icon: 1});
                            setTimeout(function() {
                                window.location.href = crmRoute('front_page_profile');
                            }, 1500);
                        } else {
                            layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                        }
                    },
                    error: function() {
                        layer.msg(CrmLang.t('common.error'), {icon: 2});
                    }
                });
                return false;
            });
        });
    });

    registry['profile/change-password'] = once(function () {
        // Source: profile/change-password.js
        layui.use(['form', 'layer', 'jquery'], function() {
            var form = layui.form, layer = layui.layer, $ = layui.jquery;
            var $pageMarker = $('[data-layui-page="profile/change-password"]').first();
            var isLegacyBigAgent = $pageMarker.attr('data-legacy-big-agent') === '1';
            var passwordEndpoint = $pageMarker.attr('data-password-endpoint') || '/api/front/profile/password';
            var loginUrl = $pageMarker.attr('data-login-url') || crmRoute('front_page_login');
            
            // 初始化当前页面的多语言文案。
            CrmLang.switchUI();

            form.verify({
                profileRequired: function(value, elem) {
                    if (!$.trim(value || '')) {
                        return requiredMessage(elem);
                    }
                },
                password: function(value) {
                    if (value.length < 6) return CrmLang.t('register.passwordRule');
                },
                confirmPass: function(value) {
                    var pwd = $('#new_password').val();
                    if (value !== pwd) return CrmLang.t('register.passwordMismatch');
                }
            });

            function translateOr(key, fallback) {
                var value = CrmLang.t(key);
                return value && value !== key ? value : fallback;
            }

            // 生成“当前表单 + 当前字段”的必填提示，避免修改密码页多个密码框提示不清楚。
            function requiredTemplateMessage(formTitle, fieldTitle) {
                var template = translateOr('front.profile_required_message', '请填写【{form}】的【{field}】');
                return template
                    .replace('{form}', $.trim(formTitle || translateOr('profile.changePassword', '修改密码')))
                    .replace('{field}', $.trim(fieldTitle || ''));
            }

            // 根据触发校验的输入框反查卡片标题和字段标签，让提示对应到用户刚点击提交的表单。
            function requiredMessage(elem) {
                var $elem = $(elem);
                var formTitle = $.trim($elem.closest('.layui-card').find('.layui-card-header').first().text()) || translateOr('profile.changePassword', '修改密码');
                var fieldTitle = $.trim($elem.closest('.layui-form-item').find('.layui-form-label').first().text()) || $elem.attr('name') || '';

                return requiredTemplateMessage(formTitle, fieldTitle);
            }

            function passwordPayload(fields) {
                if (!isLegacyBigAgent) {
                    return fields;
                }

                return {
                    _token: fields._token,
                    olduserpsw: fields.old_password || '',
                    newuserpsw: fields.password || '',
                    confirmuserpsw: fields.password_confirmation || ''
                };
            }

            form.on('submit(passwordSubmit)', function(data) {
                var requestData = passwordPayload(data.field);
                var onSuccess = function(res) {
                        if (isLegacyBigAgent) {
                            if (Number(res.code) === 0) {
                                layer.msg(CrmLang.t('profile.passwordChanged'), {icon: 1});
                                setTimeout(function() {
                                    window.location.href = loginUrl;
                                }, 1500);
                                return;
                            }
                            if ([1000, 1010, 1011].indexOf(Number(res.code)) !== -1) {
                                layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                                return;
                            }
                            layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                            return;
                        }

                        if (res.code === 1000 || res.code === 2000) {
                            layer.msg(CrmLang.t('profile.passwordChanged'), {icon: 1});
                            CrmAjax.removeToken('front');
                            setTimeout(function() {
                                window.location.href = crmRoute('front_page_login');
                            }, 1500);
                        } else {
                            layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                        }
                };
                var onError = function(res) {
                    layer.msg((res && (res.message || res.msg)) || CrmLang.t('common.error'), {icon: 2});
                };

                if (isLegacyBigAgent) {
                    // legacy 改密依赖 session + CSRF；不能让 CrmAjax 注入普通 user Bearer。
                    $.ajax({
                        url: passwordEndpoint,
                        type: 'POST',
                        dataType: 'json',
                        data: requestData,
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || '',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }).done(onSuccess).fail(function(xhr) {
                        onError(xhr.responseJSON || {});
                    });
                } else {
                    CrmAjax.request({
                        guard: 'front',
                        authRedirect: false,
                        url: passwordEndpoint,
                        method: 'POST',
                        data: requestData,
                        success: onSuccess,
                        error: onError
                    });
                }
                return false;
            });
        });
    });

    registry['profile/edit'] = once(function () {
        // Source: profile/edit.js
        /**
         * Front Layui profile edit page.
         *
         * Loads editable profile fields, uploads the avatar immediately after file
         * selection, and submits only the basic profile form fields.
         */
        layui.use(['form', 'layer', 'jquery', 'upload'], function () {
            var form = layui.form;
            var layer = layui.layer;
            var $ = layui.jquery;
            var upload = layui.upload;
            var selectedAvatar = null;
            var avatarDefault = '/images/default-avatar.svg';

            CrmLang.switchUI();
            loadProfileInfo();

            form.verify({
                profileRequired: function (value, elem) {
                    if (!$.trim(value || '')) {
                        return requiredMessage(elem);
                    }
                }
            });

            function translateOr(key, fallback) {
                var value = CrmLang.t(key);
                return value && value !== key ? value : fallback;
            }

            function requiredTemplateMessage(formTitle, fieldTitle) {
                var template = translateOr('front.profile_required_message', 'Please fill [{form}] [{field}]');
                return template
                    .replace('{form}', $.trim(formTitle || translateOr('profile.editProfile', 'Edit Profile')))
                    .replace('{field}', $.trim(fieldTitle || ''));
            }

            function requiredMessage(elem) {
                var $elem = $(elem);
                var formTitle = $.trim($elem.closest('.layui-card').find('.layui-card-header').first().text()) || translateOr('profile.editProfile', 'Edit Profile');
                var fieldTitle = $.trim($elem.closest('.layui-form-item').find('.layui-form-label').first().text()) || $elem.attr('name') || '';

                return requiredTemplateMessage(formTitle, fieldTitle);
            }

            function loadProfileInfo() {
                CrmAjax.request({
                    guard: 'front',
                    url: '/api/front/profile',
                    method: 'GET',
                    success: function (res) {
                        if (res.code === 1000 || res.code === 2000) {
                            var user = (res.data && res.data.info) || {};
                            var login = (res.data && res.data.login) || {};
                            var auth = (res.data && res.data.auth) || {};
                            form.val('profileForm', {
                                user_name: user.user_name,
                                phone: user.phone,
                                gender: user.gender ? user.gender.toString() : '1',
                                address: user.address
                            });
                            avatarDefault = user.avatar_url || user.avatar || '/images/default-avatar.svg';
                            $('#avatarPreview').attr('src', avatarDefault);
                            $('#profileName').text(user.user_name || login.email || translateOr('profile.editProfile', 'Edit Profile'));
                            $('#profileUserId').text(user.user_id || login.user_id || '-');
                            $('#profilePhoneMasked').text(user.phone_masked || user.phone || '-');
                            $('#profileEmailMasked').text(login.email_masked || login.email || user.email || '-');
                            $('#profileIdCardMasked').text(auth.id_card_no_masked || user.id_card_no_masked || user.id_card_no || '-');
                            resetAvatarUpload(true);
                            form.render();
                            CrmLang.switchUI();
                        }
                    }
                });
            }

            function uploadAvatarFile(file) {
                if (!file) {
                    layer.msg(requiredTemplateMessage(translateOr('profile.uploadAvatar', 'Avatar Upload'), translateOr('front.avatar', 'Avatar')), {icon: 2});
                    return;
                }

                var formData = new FormData();
                formData.append('avatar', file);

                CrmAjax.upload({
                    guard: 'front',
                    url: '/api/front/profile/avatar',
                    formData: formData,
                    success: function (res) {
                        if (res.code === 1000 || res.code === 2000) {
                            avatarDefault = (res.data && res.data.url) || avatarDefault;
                            $('#avatarPreview').attr('src', avatarDefault);
                            selectedAvatar = null;
                            resetAvatarUpload(true);
                            notifyParentAvatar(avatarDefault);
                            layer.msg(CrmLang.t('common.success'), {icon: 1});
                            return;
                        }

                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    },
                    error: function () {
                        layer.msg(CrmLang.t('common.error'), {icon: 2});
                    }
                });
            }

            upload.render({
                elem: '#uploadAvatar',
                accept: 'images',
                exts: 'jpg|jpeg|png|gif|webp',
                size: 4096,
                drag: true,
                multiple: false,
                choose: function (obj) {
                    var files = obj.pushFile();
                    var keys = Object.keys(files);
                    var latestKey = keys.length ? keys[keys.length - 1] : '';
                    selectedAvatar = latestKey ? files[latestKey] : null;

                    if (!selectedAvatar) {
                        return;
                    }

                    $.each(keys, function (_, key) {
                        if (key !== latestKey) {
                            delete files[key];
                        }
                    });

                    obj.preview(function (index, file, result) {
                        $('#avatarPreview').attr('src', result);
                        updateAvatarUpload(file || selectedAvatar);
                        uploadAvatarFile(file || selectedAvatar);
                    });
                }
            });

            $('#clearAvatar').on('click', function () {
                selectedAvatar = null;
                resetAvatarUpload(false);
            });

            form.on('submit(profileSubmit)', function (data) {
                var payload = $.extend({}, data.field);
                if (!$.trim(payload.phone || '') || payload.phone.indexOf('*') !== -1) {
                    delete payload.phone;
                }

                CrmAjax.request({
                    guard: 'front',
                    url: '/api/front/profile',
                    method: 'PATCH',
                    data: payload,
                    success: function (res) {
                        if (res.code === 1000 || res.code === 2000) {
                            layer.msg(CrmLang.t('profile.saveSuccess'), {icon: 1});
                            return;
                        }

                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    },
                    error: function () {
                        layer.msg(CrmLang.t('common.error'), {icon: 2});
                    }
                });

                return false;
            });

            function notifyParentAvatar(url) {
                if (!url || !window.parent || window.parent === window) {
                    return;
                }

                window.parent.postMessage({
                    type: 'crm:avatar-updated',
                    url: url
                }, window.location.origin);
            }

            function updateAvatarUpload(file) {
                var selectedText = translateOr('front.selected_files', 'Selected {count} file').replace('{count}', '1');

                $('[data-upload-name="avatar"]').text(file.name || '-');
                $('[data-upload-size="avatar"]').text(formatFileSize(file.size || 0));
                $('[data-upload-status="avatar"]').text(selectedText).removeAttr('data-translate').addClass('has-file');
                $('[data-upload-clear="avatar"]').addClass('is-visible');
            }

            function resetAvatarUpload(keepPreview) {
                $('[data-upload-name="avatar"]').text('-');
                $('[data-upload-size="avatar"]').text('-');
                $('[data-upload-status="avatar"]')
                    .text(translateOr('front.no_file_selected', 'No file selected'))
                    .attr('data-translate', 'front.no_file_selected')
                    .removeClass('has-file');
                $('[data-upload-clear="avatar"]').removeClass('is-visible');
                if (!keepPreview) {
                    $('#avatarPreview').attr('src', avatarDefault);
                }
            }

            function formatFileSize(size) {
                if (!size) {
                    return '0 KB';
                }
                if (size < 1024 * 1024) {
                    return (size / 1024).toFixed(1) + ' KB';
                }
                return (size / 1024 / 1024).toFixed(2) + ' MB';
            }
        });
    });

    registry['profile/index'] = once(function () {
        // Source: profile/index.js
        /**
         * 前台 Layui 资料页交互脚本。
         *
         * 这个文件把头像、资料、密码、邮箱、手机号、实名和银行卡验证
         * 统一放在一起，方便每个提交入口只校验自己对应卡片里的字段。
         */
        layui.use(['form', 'layer', 'jquery', 'upload'], function() {
            var form = layui.form;
            var layer = layui.layer;
            var $ = layui.jquery;
            var upload = layui.upload;
            var uploadFiles = {};
            var uploadPreviews = {};
            var uploadPreviewDefaults = {};
            var avatarUploadSerial = 0;

            CrmLang.switchUI();
            loadProfileInfo();
            // 所有上传入口都走同一个共享 Layui 上传组件；这里只声明字段与预览目标，
            // 上传地址、字段名、类型和体积上限全部保持原有接口契约不变。
            bindPreviewUpload('#selectAvatar', '#avatarPreview', 'avatar');
            bindPreviewUpload('#idCardFrontBtn', '#idCardFrontPreview', 'id_card_front');
            bindPreviewUpload('#idCardBackBtn', '#idCardBackPreview', 'id_card_back');
            bindPreviewUpload('#bankCardImgBtn', '#bankCardImgPreview', 'bank_card_img');
            bindPreviewUpload('#bankCardBackImgBtn', '#bankCardBackImgPreview', 'bank_card_img_back');
            bindPreviewUpload('#bankChangeCardImgBtn', '#bankChangeCardImgPreview', 'bank_change_card_img');
            bindPreviewUpload('#bankChangeCardBackImgBtn', '#bankChangeCardBackImgPreview', 'bank_change_card_img_back');

            $(document).on('click', '.crm-profile-upload-preview[data-image-preview]', function(event) {
                event.preventDefault();
                openProfileUploadPreview($(this).attr('data-image-preview') || $(this).attr('src'));
            });
            $(document).on('keydown', '.crm-profile-upload-preview[data-image-preview]', function(event) {
                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }
                event.preventDefault();
                openProfileUploadPreview($(this).attr('data-image-preview') || $(this).attr('src'));
            });

            form.verify({
                password: function(value) {
                    if (value.length < 6) {
                        return CrmLang.t('register.passwordRule');
                    }
                },
                confirmPass: function(value) {
                    if (value !== $('#new_password').val()) {
                        return CrmLang.t('register.passwordMismatch');
                    }
                },
                profileRequired: function(value, elem) {
                    if (!$.trim(value || '')) {
                        return requiredMessage(elem);
                    }
                }
            });

            // 每个提交按钮所属表单需要额外校验的上传字段，键为 lay-filter，值为“上传字段 -> 文案语言键”。
            var formUploadRequirements = {
                identityForm: {
                    id_card_front: 'profile.idCardFront',
                    id_card_back: 'profile.idCardBack'
                },
                bankForm: {
                    bank_card_img: 'profile.bankCardFront',
                    bank_card_img_back: 'profile.bankCardBack'
                },
                bankChangeForm: {
                    bank_change_card_img: 'profile.bankCardFront',
                    bank_change_card_img_back: 'profile.bankCardBack'
                }
            };

            // 需要在必填之后继续检查的格式规则，全部在点击按钮时就地判定，
            // 这样提示可以锚定到具体输入框，而不是弹出与按钮无关的全局提示。
            var formatRules = {
                email: function(value) {
                    return /^[\w.+-]+@[\w-]+\.[\w.-]+$/.test(value) ? '' : translateOr('profile.emailInvalid', CrmLang.t('common.error'));
                },
                password: function(value) {
                    return value.length >= 6 ? '' : CrmLang.t('register.passwordRule');
                },
                confirmPass: function(value, form) {
                    var reference = $(form).find('[name="password"], [name="new_password"]').first();

                    return value === $.trim(reference.val() || '') ? '' : CrmLang.t('register.passwordMismatch');
                }
            };

            bindProfileFieldValidation();

            /**
             * 让每个提交按钮只校验它自己所属的表单。
             *
             * 使用捕获阶段监听：Layui 的提交动作绑定在 document 的冒泡阶段委托上，
             * 因此这里先于 Layui 执行，校验不通过时直接阻断事件，Layui 不会再弹出全局提示。
             *
             * @return {void} 校验结果通过行内提示与聚焦交付。
             */
            function bindProfileFieldValidation() {
                if (!window.CrmFieldErrors) {
                    return;
                }

                $('form[lay-filter]').each(function() {
                    window.CrmFieldErrors.bindAutoClear(this);
                });

                $('[lay-submit]').each(function() {
                    var button = this;

                    button.addEventListener('click', function(event) {
                        var form = $(button).closest('form')[0];

                        if (!form || validateProfileForm(form)) {
                            return;
                        }
                        event.preventDefault();
                        event.stopPropagation();
                        if (event.stopImmediatePropagation) {
                            event.stopImmediatePropagation();
                        }
                    }, true);
                });
            }

            /**
             * 校验单个表单：先按 DOM 顺序检查必填项，再检查格式规则，最后检查必传上传项。
             *
             * @param {Element} form 被点击提交按钮所属的表单。
             * @return {boolean} true 表示该表单可以继续提交。
             */
            function validateProfileForm(form) {
                var filter = String($(form).attr('lay-filter') || '');
                var uploads = formUploadRequirements[filter] || {};

                if (!window.CrmFieldErrors.validateRequired(form, {
                    uploads: uploads,
                    hasUpload: function(field) {
                        return !!uploadFiles[field];
                    },
                    messageFor: function(label) {
                        return requiredTemplateMessage(formTitle(form), label);
                    },
                    uploadMessageFor: function(label) {
                        return requiredTemplateMessage(formTitle(form), label);
                    }
                })) {
                    return false;
                }

                return validateProfileFormats(form);
            }

            /**
             * 按 lay-verify 声明的格式规则校验表单，并把第一个错误锚定到对应输入框。
             *
             * @param {Element} form 待校验表单。
             * @return {boolean} true 表示格式全部通过。
             */
            function validateProfileFormats(form) {
                var valid = true;

                $(form).find('[lay-verify]').each(function() {
                    var control = this;
                    var name = String($(control).attr('name') || '');
                    var value = $.trim($(control).val() || '');
                    var rules = String($(control).attr('lay-verify') || '').split('|');
                    var message = '';

                    if (!name || !value) {
                        return true;
                    }
                    $.each(rules, function(_, rule) {
                        if (!message && formatRules[rule]) {
                            message = formatRules[rule](value, form);
                        }
                    });
                    if (message) {
                        window.CrmFieldErrors.show(form, name, message);
                        valid = false;

                        return false;
                    }

                    return true;
                });

                return valid;
            }

            function uploadAvatarFile(file) {
                var requestSerial;

                if (!file) {
                    layer.msg(requiredTemplateMessage(translateOr('front.avatar_upload', '头像上传'), translateOr('front.avatar', '头像')), {icon: 2});
                    return;
                }

                var formData = new FormData();
                formData.append('avatar', file);

                requestSerial = ++avatarUploadSerial;
                // 头像是唯一即时上传的字段：进度条与状态文案交给共享上传组件统一渲染。
                setUploadProgress('avatar', 30, true, translateOr('front.upload_uploading', '正在上传...'));
                CrmAjax.upload({
                    guard: 'front',
                    url: '/api/front/profile/avatar',
                    formData: formData,
                    success: function(res) {
                        if (requestSerial !== avatarUploadSerial) {
                            return;
                        }
                        setUploadProgress('avatar', 100, false, translateOr('front.upload_done', '上传完成'));
                        if (res.code === 1000 || res.code === 1004 || res.code === 2000) {
                            var avatarUrl = (res.data && res.data.url) || '/images/default-avatar.svg';
                            $('#avatarPreview').attr('src', avatarUrl);
                            uploadPreviewDefaults.avatar = avatarUrl;
                            layer.msg(CrmLang.t('profile.saveSuccess'), {icon: 1});
                            notifyParentAvatar(avatarUrl);
                            delete uploadFiles.avatar;
                            resetUploadVisual('avatar', true);
                            loadProfileInfo();
                            return;
                        }
                        failUpload('avatar', res.message || CrmLang.t('common.error'));
                    },
                    error: function(res) {
                        if (requestSerial !== avatarUploadSerial) {
                            return;
                        }
                        failUpload('avatar', (res && res.message) || translateOr('front.upload_error_network', CrmLang.t('common.error')));
                    }
                });
            }

            form.on('submit(profileSubmit)', function(data) {
                var payload = $.extend({}, data.field);
                if (!$.trim(payload.phone || '')) {
                    delete payload.phone;
                }
                if (!$.trim(payload.id_card_no || '')) {
                    delete payload.id_card_no;
                }

                CrmAjax.request({
                    guard: 'front',
                    url: '/api/front/profile',
                    method: 'PATCH',
                    data: payload,
                    success: function(res) {
                        if (res.code === 1000 || res.code === 1002 || res.code === 2000) {
                            layer.msg(res.message || CrmLang.t('profile.saveSuccess'), {icon: 1});
                            loadProfileInfo();
                            return;
                        }
                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    },
                    error: function(res) {
                        layer.msg((res && res.message) || CrmLang.t('common.error'), {icon: 2});
                    }
                });
                return false;
            });

            form.on('submit(passwordSubmit)', function(data) {
                CrmAjax.request({
                    guard: 'front',
                    url: '/api/front/profile/password',
                    data: data.field,
                    success: function(res) {
                        if (res.code === 1000 || res.code === 1002 || res.code === 2000) {
                            layer.msg(res.message || CrmLang.t('profile.passwordChanged'), {icon: 1});
                            CrmAjax.removeToken('front');
                            setTimeout(function() {
                                window.location.href = crmRoute('front_page_login');
                            }, 1200);
                            return;
                        }
                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    },
                    error: function(res) {
                        layer.msg((res && res.message) || CrmLang.t('common.error'), {icon: 2});
                    }
                });
                return false;
            });

            form.on('submit(emailSubmit)', function(data) {
                CrmAjax.request({
                    guard: 'front',
                    url: '/api/front/profile/email',
                    data: data.field,
                    success: function(res) {
                        if (res.code === 1000 || res.code === 1002 || res.code === 2000) {
                            layer.msg(res.message || CrmLang.t('profile.emailChanged'), {icon: 1});
                            $('[lay-filter="emailForm"]')[0].reset();
                            loadProfileInfo();
                            return;
                        }
                        layer.msg(contactChangeErrorMessage(res), {icon: 2});
                    },
                    error: function(res) {
                        layer.msg(contactChangeErrorMessage(res), {icon: 2});
                    }
                });
                return false;
            });

            form.on('submit(phoneSubmit)', function(data) {
                submitJson('/api/front/profile/phone', data.field, function() {
                    layer.msg(CrmLang.t('profile.phoneChanged'), {icon: 1});
                    $('[lay-filter="phoneForm"]')[0].reset();
                    loadProfileInfo();
                }, contactChangeErrorMessage);
                return false;
            });

            form.on('submit(identitySubmit)', function(data) {
                if (!validateRequired($(data.form), {
                    id_card_front: 'profile.idCardFront',
                    id_card_back: 'profile.idCardBack'
                })) {
                    return false;
                }
                submitMultipart('/api/front/profile/identity', data.form, {
                    id_card_front: 'id_card_front',
                    id_card_back: 'id_card_back'
                }, function() {
                    layer.msg(CrmLang.t('profile.saveSuccess'), {icon: 1});
                    data.form.reset();
                    clearUploadPreview(['id_card_front', 'id_card_back']);
                    loadProfileInfo();
                });
                return false;
            });

            form.on('submit(bankSubmit)', function(data) {
                if (!validateRequired($(data.form), {
                    bank_card_img: 'profile.bankCardFront',
                    bank_card_img_back: 'profile.bankCardBack'
                })) {
                    return false;
                }
                submitMultipart('/api/front/profile/bank-card', data.form, {
                    bank_card_img: 'bank_card_img',
                    bank_card_back_img: 'bank_card_img_back'
                }, function() {
                    layer.msg(CrmLang.t('profile.saveSuccess'), {icon: 1});
                    data.form.reset();
                    clearUploadPreview(['bank_card_img', 'bank_card_img_back']);
                    loadProfileInfo();
                });
                return false;
            });

            /**
             * 把银行卡换绑后端错误码转换为用户能直接处理的中文或英文提示。
             * 未识别错误继续使用统一 message，避免吞掉服务端返回的真实失败原因。
             */
            function bankChangeErrorMessage(response) {
                var error = response && response.data && response.data.error;
                var errorKeys = {
                    'errbankpendingauth': 'profile.bankChangeUnapproved',
                    'errisapplying': 'profile.bankChangeWithdrawalPending',
                    'erruserverfcode': 'profile.bankChangeCodeInvalid',
                    'errpassword': 'profile.bankChangePasswordInvalid',
                    'erruseremail': 'profile.bankChangeEmailInvalid',
                    'NETWORKFAIL': 'profile.networkUnavailable'
                };

                return errorKeys[error]
                    ? translateOr(errorKeys[error], response.message || CrmLang.t('common.error'))
                    : ((response && response.message) || CrmLang.t('common.error'));
            }

            /**
             * 把联系方式修改错误码转换为用户可以直接处理的提示。
             * 后端仍保留旧错误码，前端只负责翻译，不改变业务成功判定。
             */
            function contactChangeErrorMessage(response) {
                var error = response && response.data && response.data.error;
                var errorKeys = {
                    codeErr: 'profile.bankChangeCodeInvalid',
                    emailErr: 'profile.bankChangeEmailInvalid',
                    phoneErr: 'profile.phoneVerifyFailed',
                    pswErr: 'profile.bankChangePasswordInvalid',
                    'NETWORKFAIL': 'profile.networkUnavailable'
                };

                return errorKeys[error]
                    ? translateOr(errorKeys[error], response.message || CrmLang.t('common.error'))
                    : ((response && response.message) || CrmLang.t('common.error'));
            }

            /**
             * 绑定资料敏感操作的邮箱验证码按钮。
             *
             * endpoint 决定验证码用途缓存键；emailField 决定验证码绑定的目标邮箱。
             * 登录主体始终由 JWT 确定，请求不提交 user_id，避免页面字段改变验证对象。
             */
            function bindProfileCodeSender(buttonSelector, endpoint, emailField, phoneField, type) {
                $(buttonSelector).on('click', function() {
                    var $button = $(this);
                    var $form = $button.closest('form');
                    var $label = $button.find('span');
                    var $emailInput = $form.find('[name="' + emailField + '"]');
                    var emailInput = $emailInput[0];
                    var email = $.trim($emailInput.val() || '');
                    var phone = $.trim($form.find('[name="' + phoneField + '"]').val() || '');

                    if ($button.prop('disabled')) {
                        return;
                    }
                    if (!email || (emailInput && !emailInput.checkValidity())) {
                        layer.msg(requiredMessage(emailInput), {icon: 2});
                        if (emailInput) {
                            emailInput.focus();
                        }
                        return;
                    }

                    $button.prop('disabled', true).addClass('layui-btn-disabled');
                    $label.text(translateOr('profile.sendingCode', '发送中'));

                    CrmAjax.request({
                        guard: 'front',
                        url: endpoint,
                        method: 'POST',
                        data: {
                            useremail: email,
                            userphoneNo: phone,
                            type: type
                        },
                        success: function(response) {
                            var seconds = 60;
                            var timer;

                            if (!response || response.status !== true) {
                                $button.prop('disabled', false).removeClass('layui-btn-disabled');
                                $label.text(translateOr('profile.sendCode', '发送验证码'));
                                layer.msg(translateOr('profile.codeSendFailed', '验证码发送失败，请稍后重试'), {icon: 2});
                                return;
                            }

                            layer.msg(translateOr('profile.codeSent', '验证码已发送，请查收邮箱'), {icon: 1});
                            $label.text(seconds + 's');
                            timer = window.setInterval(function() {
                                seconds -= 1;
                                if (seconds <= 0) {
                                    window.clearInterval(timer);
                                    $button.prop('disabled', false).removeClass('layui-btn-disabled');
                                    $label.text(translateOr('profile.sendCode', '发送验证码'));
                                    return;
                                }
                                $label.text(seconds + 's');
                            }, 1000);
                        },
                        error: function() {
                            $button.prop('disabled', false).removeClass('layui-btn-disabled');
                            $label.text(translateOr('profile.sendCode', '发送验证码'));
                            layer.msg(translateOr('profile.codeSendFailed', '验证码发送失败，请稍后重试'), {icon: 2});
                        }
                    });
                });
            }

            bindProfileCodeSender(
                '#sendEmailChangeCodeBtn',
                '/api/front/profile/verification-password/verification-codes',
                'new_email',
                'verify_phone',
                'email'
            );
            bindProfileCodeSender(
                '#sendBankChangeCodeBtn',
                '/api/front/profile/bank-card-change/verification-codes',
                'verify_email',
                'verify_phone',
                'bank-change'
            );

            form.on('submit(bankChangeSubmit)', function(data) {
                if (!validateRequired($(data.form), {
                    bank_change_card_img: 'profile.bankCardFront',
                    bank_change_card_img_back: 'profile.bankCardBack'
                })) {
                    return false;
                }
                submitMultipart('/api/front/profile/bank-card-change', data.form, {
                    bank_card_img: 'bank_change_card_img',
                    bank_card_back_img: 'bank_change_card_img_back'
                }, function() {
                    layer.msg(CrmLang.t('profile.saveSuccess'), {icon: 1});
                    data.form.reset();
                    clearUploadPreview(['bank_change_card_img', 'bank_change_card_img_back']);
                    loadProfileInfo();
                }, function(response) {
                    layer.msg(bankChangeErrorMessage(response), {icon: 2});
                });
                return false;
            });

            /**
             * 提交资料页 JSON 表单，并允许敏感操作注入业务错误翻译器。
             *
             * @param {string} endpoint 请求地址。
             * @param {Object} payload 表单字段。
             * @param {Function} done 成功后的页面回调。
             * @param {Function=} errorMessageResolver 可选错误消息解析器。
             * @return {void} 结果通过页面提示和成功回调交付。
             */
            function submitJson(endpoint, payload, done, errorMessageResolver) {
                CrmAjax.request({
                    guard: 'front',
                    url: endpoint,
                    data: payload,
                    success: function(res) {
                        if (res.code === 1000 || res.code === 1002 || res.code === 2000) {
                            if (done) done(res);
                            return;
                        }
                        layer.msg(
                            errorMessageResolver
                                ? errorMessageResolver(res)
                                : (res.message || CrmLang.t('common.error')),
                            {icon: 2}
                        );
                    },
                    error: function(res) {
                        layer.msg(
                            errorMessageResolver
                                ? errorMessageResolver(res)
                                : ((res && res.message) || CrmLang.t('common.error')),
                            {icon: 2}
                        );
                    }
                });
            }

            function translateOr(key, fallback) {
                var value = CrmLang.t(key);
                return value && value !== key ? value : fallback;
            }

            function requiredTemplateMessage(formTitle, fieldTitle) {
                var template = translateOr('front.profile_required_message', '请填写【{form}】的【{field}】');
                return template
                    .replace('{form}', $.trim(formTitle || translateOr('front.profile', '个人中心')))
                    .replace('{field}', $.trim(fieldTitle || ''));
            }

            function formTitle(elemOrForm) {
                var $form = $(elemOrForm).is('form') ? $(elemOrForm) : $(elemOrForm).closest('form');
                var $title = $form.closest('.layui-card-body').find('.profile-section-title').first().clone();

                $title.find('.layui-badge').remove();
                return $.trim($title.text()) || translateOr('front.profile', '个人中心');
            }

            function requiredMessage(elem) {
                var label = $(elem).closest('.layui-form-item').find('.layui-form-label').first().text() || $(elem).attr('name') || '';
                return requiredTemplateMessage(formTitle(elem), label);
            }

            function uploadRequiredMessage(labelKey, formEl) {
                return requiredTemplateMessage(formTitle(formEl), CrmLang.t(labelKey));
            }

            // 把缓存字段映射到用户能看懂的上传文案，保证校验提示能精确指向
            // 对应按钮，不会让错误信息和实际操作脱节。
            function uploadLabelKey(fieldName) {
                var labels = {
                    id_card_front: 'profile.idCardFront',
                    id_card_back: 'profile.idCardBack',
                    bank_card_img: 'profile.bankCardFront',
                    bank_card_img_back: 'profile.bankCardBack',
                    bank_change_card_img: 'profile.bankCardFront',
                    bank_change_card_img_back: 'profile.bankCardBack'
                };

                return labels[fieldName] || fieldName;
            }

            // 提交前的最后一道校验：只看传入表单自己的必填字段和必传上传项，
            // 提示一律锚定到出错控件旁边（行内文案 + aria-invalid + 聚焦 + 滚动到可视区域）。
            function validateRequired($form, fileMap) {
                var form = $form[0];
                var valid = true;

                if (!form) {
                    return true;
                }
                if (window.CrmFieldErrors) {
                    return window.CrmFieldErrors.validateRequired(form, {
                        uploads: fileMap || {},
                        hasUpload: function (field) {
                            return !!uploadFiles[field];
                        },
                        messageFor: function (label) {
                            return requiredTemplateMessage(formTitle(form), label);
                        },
                        uploadMessageFor: function (label) {
                            return requiredTemplateMessage(formTitle(form), label);
                        }
                    }) && validateProfileFormats(form);
                }

                $form.find('[lay-verify*="required"],[lay-verify*="profileRequired"]').each(function () {
                    if (!$.trim($(this).val() || '')) {
                        layer.msg(requiredMessage(this), {icon: 2});
                        this.focus();
                        valid = false;
                        return false;
                    }
                });
                if (!valid) {
                    return false;
                }
                $.each(fileMap || {}, function (fieldName, labelKey) {
                    if (!uploadFiles[fieldName]) {
                        layer.msg(uploadRequiredMessage(labelKey, $form), {icon: 2});
                        valid = false;
                        return false;
                    }
                });
                return valid;
            }

            // 把已选预览文件按后端字段名塞进 FormData。通过这层映射，同一
            // 个按钮可以对应不同的上传接口。
            function submitMultipart(endpoint, formEl, fileMap, done, fail) {
                var formData = new FormData(formEl);
                var requestField;
                var cacheField;

                fileMap = fileMap || {};
                for (requestField in fileMap) {
                    if (Object.prototype.hasOwnProperty.call(fileMap, requestField)) {
                        cacheField = fileMap[requestField];
                        if (!uploadFiles[cacheField]) {
                            // 缺少必传图片时把提示锚定到对应上传块，避免用户看不出是哪一张图没选。
                            if (window.CrmFieldErrors) {
                                window.CrmFieldErrors.showUpload(formEl, cacheField, uploadRequiredMessage(uploadLabelKey(cacheField), formEl));
                            } else {
                                layer.msg(uploadRequiredMessage(uploadLabelKey(cacheField), formEl), {icon: 2});
                            }
                            return;
                        }
                        formData.append(requestField, uploadFiles[cacheField]);
                    }
                }

                CrmAjax.upload({
                    guard: 'front',
                    url: endpoint,
                    formData: formData,
                    success: function(res) {
                        if (res.code === 1000 || res.code === 1002 || res.code === 2000) {
                            if (done) done(res);
                            return;
                        }
                        if (fail) {
                            fail(res);
                            return;
                        }
                        layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                    },
                    error: function(res) {
                        if (fail) {
                            fail(res || {});
                            return;
                        }
                        layer.msg((res && res.message) || CrmLang.t('common.error'), {icon: 2});
                    }
                });
            }

            function notifyParentAvatar(url) {
                if (!url || !window.parent || window.parent === window) {
                    return;
                }

                window.parent.postMessage({
                    type: 'crm:avatar-updated',
                    url: url
                }, window.location.origin);
            }

            function openProfileUploadPreview(url) {
                if (!url || url === '#') {
                    return;
                }

                layer.open({
                    type: 1,
                    title: translateOr('front.images', '图片'),
                    area: [Math.min(860, Math.max(320, window.innerWidth - 32)) + 'px', 'auto'],
                    skin: 'crm-responsive-layer',
                    shade: 0.25,
                    content: $('<div class="crm-responsive-layer-body crm-image-preview-layer"></div>').append(
                        $('<img>', {src: url, alt: ''})
                    )
                });
            }

            function loadProfileInfo() {
                CrmAjax.request({
                    guard: 'front',
                    url: '/api/front/profile',
                    method: 'GET',
                    success: function(res) {
                        if (res.code !== 1000 && res.code !== 2000 && res.code !== 3000) {
                            layer.msg(res.message || CrmLang.t('common.error'), {icon: 2});
                            return;
                        }

                        var info = res.data.info || {};
                        var login = res.data.login || {};
                        var auth = res.data.auth || {};
                        var avatar = info.avatar_url || info.avatar || '/images/default-avatar.svg';

                        $('#profileName').text(info.user_name || login.email || '-');
                        $('#avatarPreview').attr('src', avatar);
                        uploadPreviewDefaults.avatar = avatar;
                        $('#profileUserId').text(info.user_id || login.user_id || '-');
                        $('#profilePhoneMasked').text(info.phone_masked || info.phone || '-');
                        $('#profileEmailMasked').text(login.email_masked || login.email || info.email || '-');
                        $('#profileIdCardMasked').text(auth.id_card_no_masked || info.id_card_no_masked || info.id_card_no || '-');
                        $('#profilePhoneReadonly').val(info.phone_masked || '-');
                        $('#profileIdCardReadonly').val(auth.id_card_no_masked || info.id_card_no_masked || '-');
                        $('#idCardStatusText').text((auth && auth.id_card_status_text) || '-');
                        $('#bankStatusText').text((auth && auth.bank_status_text) || '-');

                        form.val('profileForm', {
                            user_name: info.user_name || '',
                            gender: info.gender ? String(info.gender) : '1',
                            address: info.address || ''
                        });

                        form.val('identityForm', {id_card_no: ''});
                        $('[lay-filter="identityForm"] input[name="id_card_no"]').attr('placeholder', auth.id_card_no_masked || CrmLang.t('profile.fullIdCardPlaceholder'));
                        form.val('bankForm', {
                            bank_name: auth.bank_name || '',
                            bank_no: '',
                            bank_addr: auth.bank_addr || ''
                        });
                        // 当前联系方式可以预填；密码、验证码和新联系方式必须始终由用户本次输入。
                        form.val('emailForm', {
                            verify_phone: info.phone || '',
                            current_email: login.email || '',
                            new_email: '',
                            password: '',
                            verification_code: ''
                        });
                        form.val('phoneForm', {
                            verify_phone: info.phone || '',
                            verify_email: login.email || '',
                            new_phone: '',
                            password: ''
                        });
                        form.val('bankChangeForm', {
                            verify_phone: info.phone || '',
                            verify_email: login.email || '',
                            password: '',
                            verification_code: ''
                        });
                        $('[lay-filter="bankForm"] input[name="bank_no"]').attr('placeholder', auth.bank_no_masked || '');
                        CrmLang.switchUI();
                        form.render();
                    }
                });
            }

            // 绑定 Layui upload 最新选择器能力：只在前端预览和缓存文件，不自动上传。
            // 这样多个表单可以先做字段级校验，再把对应文件按接口字段名一次性提交。
            function bindPreviewUpload(elem, preview, fieldName) {
                var $trigger = $(elem);

                if (!$trigger.length) {
                    return;
                }
                uploadPreviews[fieldName] = preview;
                uploadPreviewDefaults[fieldName] = $(preview).attr('src') || uploadPreviewDefaults[fieldName] || '';
                bindUploadClear(fieldName);
                resetUploadVisual(fieldName, true);

                upload.render({
                    elem: elem,
                    auto: false,
                    accept: 'images',
                    exts: 'jpg|jpeg|png|gif|webp',
                    // 与旧版用户中心保持一致：证件、银行卡图片单张最大 10MB。
                    size: 10240,
                    drag: true,
                    multiple: false,
                    choose: function(obj) {
                        var files = obj.pushFile();
                        var keys = Object.keys(files);
                        var latestKey = keys.length ? keys[keys.length - 1] : '';
                        var file = latestKey ? files[latestKey] : null;

                        if (!file) {
                            return;
                        }

                        $.each(keys, function(_, key) {
                            if (key !== latestKey) {
                                delete files[key];
                            }
                        });

                        uploadFiles[fieldName] = file;
                        // 选中文件后立即清除该上传块上一次的行内错误，避免过期提示留在界面上。
                        if (window.CrmFieldErrors) {
                            window.CrmFieldErrors.clearUpload(document, fieldName);
                        }
                        obj.preview(function(index, selectedFile, result) {
                            updateUploadVisual(fieldName, selectedFile || file, result);
                            if (fieldName === 'avatar') {
                                uploadAvatarFile(file);
                            }
                        });
                    }
                });
            }

            // 清空某个上传字段的缓存和界面状态。表单提交成功后调用它，避免旧文件被下一次提交误带上。
            function clearUploadPreview(fields, keepPreview) {
                $.each(fields || [], function(_, fieldName) {
                    delete uploadFiles[fieldName];
                    resetUploadVisual(fieldName, keepPreview);
                });
            }

            // 绑定清空按钮，用户点错文件时可以立即撤销当前选择，不需要刷新整页。
            function bindUploadClear(fieldName) {
                $('[data-upload-clear="' + fieldName + '"]').off('click.profileUpload').on('click.profileUpload', function() {
                    clearUploadPreview([fieldName]);
                });
            }

            // 选中文件后同步按钮旁状态、预览缩略图、文件名和大小，让上传结果可被直接确认。
            function updateUploadVisual(fieldName, file, result) {
                var $field = uploadField(fieldName);
                var selectedText = translateOr('front.selected_files', '已选择 {count} 个文件').replace('{count}', '1');

                $field.addClass('has-file');
                $field.find('[data-upload-status="' + fieldName + '"]').text(selectedText).addClass('has-file').removeClass('is-error').removeAttr('data-translate');
                $field.find('[data-upload-clear="' + fieldName + '"]').addClass('is-visible');
                $field.find('[data-upload-name="' + fieldName + '"]').text(file.name || '-');
                $field.find('[data-upload-size="' + fieldName + '"]').text(formatFileSize(file.size || 0));

                if (uploadPreviews[fieldName]) {
                    $(uploadPreviews[fieldName])
                        .attr('src', result || uploadPreviewDefaults[fieldName] || '')
                        .attr('data-image-preview', result || uploadPreviewDefaults[fieldName] || '')
                        .show();
                }
                $field.find('[data-upload-preview="' + fieldName + '"]').addClass('is-visible').show();
            }

            // 恢复上传块的空状态；头像保留当前头像预览，其它证件类图片隐藏缩略图。
            function resetUploadVisual(fieldName, keepPreview) {
                var $field = uploadField(fieldName);
                var emptyText = translateOr('front.no_file_selected', '未选择文件');

                $field.removeClass('has-file');
                $field.find('[data-upload-status="' + fieldName + '"]')
                    .text(emptyText)
                    .removeClass('has-file is-uploading is-error')
                    .attr('data-translate', 'front.no_file_selected');
                $field.find('[data-upload-clear="' + fieldName + '"]').removeClass('is-visible');
                $field.find('[data-upload-name="' + fieldName + '"]').text('-');
                $field.find('[data-upload-size="' + fieldName + '"]').text('-');
                // 复位共享进度条，避免上一次上传的进度残留在界面上。
                $field.find('[data-upload-progress]').removeClass('is-visible').attr('aria-valuenow', '0');
                $field.find('[data-upload-progress-bar]').css('width', '0%');
                if (window.CrmFieldErrors) {
                    window.CrmFieldErrors.clearUpload(document, fieldName);
                }

                if (fieldName === 'avatar') {
                    if (!keepPreview && uploadPreviews[fieldName]) {
                        $(uploadPreviews[fieldName]).attr('src', uploadPreviewDefaults[fieldName] || '/images/default-avatar.svg');
                    }
                    return;
                }

                $field.find('[data-upload-preview="' + fieldName + '"]').removeClass('is-visible').hide();
                if (uploadPreviews[fieldName]) {
                    $(uploadPreviews[fieldName]).attr('src', '').removeAttr('data-image-preview');
                }
            }

            // 统一取上传字段容器，后续状态更新都只影响当前字段，避免多个表单互相串状态。
            function uploadField(fieldName) {
                return $('[data-upload-field="' + fieldName + '"]');
            }

            /**
             * 驱动共享上传组件的进度条与状态文案。
             *
             * @param {string} fieldName 上传字段标识。
             * @param {number} percent 进度百分比，0-100。
             * @param {boolean} visible 是否显示进度条。
             * @param {string} statusText 状态文案。
             * @return {void} 结果直接反映在上传块上。
             */
            function setUploadProgress(fieldName, percent, visible, statusText) {
                var $field = uploadField(fieldName);
                var value = Math.max(0, Math.min(100, Math.round(percent || 0)));

                $field.find('[data-upload-progress]').toggleClass('is-visible', !!visible).attr('aria-valuenow', String(value));
                $field.find('[data-upload-progress-bar]').css('width', value + '%');
                if (statusText) {
                    $field.find('[data-upload-status="' + fieldName + '"]')
                        .text(statusText)
                        .removeAttr('data-translate')
                        .toggleClass('is-uploading', !!visible)
                        .toggleClass('has-file', !visible);
                }
            }

            /**
             * 上传失败时把提示锚定到对应上传块，并清空该字段的进度与缓存文件。
             *
             * @param {string} fieldName 上传字段标识。
             * @param {string} message 已翻译的失败原因。
             * @return {void} 结果通过上传块的行内提示交付。
             */
            function failUpload(fieldName, message) {
                var $field = uploadField(fieldName);

                setUploadProgress(fieldName, 0, false, message);
                $field.find('[data-upload-status="' + fieldName + '"]').removeClass('has-file is-uploading').addClass('is-error');
                if (window.CrmFieldErrors) {
                    window.CrmFieldErrors.showUpload($field.closest('form')[0] || document.body, fieldName, message);
                }
            }

            // 以 KB/MB 展示文件大小，用户能在提交前判断是否选错大文件。
            function formatFileSize(size) {
                if (!size) {
                    return '0 KB';
                }
                if (size < 1024 * 1024) {
                    return (size / 1024).toFixed(1) + ' KB';
                }
                return (size / 1024 / 1024).toFixed(2) + ' MB';
            }
        });
    });

    registry['withdraw/index'] = once(function () {
        // Source: withdraw/index.js
        /**
         * Layui 前台出金页入口脚本。
         * 读取出金限制、计算手续费和到账金额，并渲染历史表格汇总。
         */
        layui.use(['jquery', 'form', 'table', 'layer'], function () {
            var $ = layui.jquery;
            var form = layui.form;
            var table = layui.table;
            var layer = layui.layer;
            var pageData = {
                isAllowed: true,
                min: 0,
                max: 0,
                feeRate: 0,
                fixedFee: 0,
                availableAmount: 0
            };
            var historyRendered = false;
            var withdrawIdempotencyKey = null;
            var withdrawIdempotencyAmount = null;
            var withdrawIdempotencyUserId = null;
            var withdrawIdempotencyStorageReady = true;
            var withdrawIdempotencyFailureReason = null;
            var withdrawSubmitting = false;
            var withdrawConfigReady = false;

            function createWithdrawIdempotencyKey() {
                if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                    return 'wdr-' + window.crypto.randomUUID();
                }

                return 'wdr-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 14);
            }

            function normalizeWithdrawAmount(value) {
                var amount = String(value === undefined || value === null ? '' : value).trim();
                var parts;
                var whole;
                var fraction;

                if (!/^[0-9]+(?:\.[0-9]{1,2})?$/.test(amount)) {
                    return null;
                }
                parts = amount.split('.');
                whole = parts[0].replace(/^0+(?=\d)/, '');
                fraction = parts[1] || '';

                return whole + '.' + (fraction + '00').slice(0, 2);
            }

            function withdrawIdempotencyStorageKey(userId) {
                return 'crm:front:withdraw:idempotency:v1:' + encodeURIComponent(String(userId));
            }

            function renderedWithdrawIntentKey() {
                var value = String($('[name="idempotency_key"]').val() || '').trim();

                return /^[a-f0-9]{64}$/.test(value) ? value : '';
            }

            function restoreWithdrawIdempotencyState(userId) {
                var normalizedUserId = String(userId === undefined || userId === null ? '' : userId).trim();
                var serialized = null;
                var state = null;
                var valid = false;
                var storageKey;

                withdrawIdempotencyKey = null;
                withdrawIdempotencyAmount = null;
                withdrawIdempotencyUserId = normalizedUserId || null;
                withdrawIdempotencyFailureReason = null;

                if (!normalizedUserId) {
                    withdrawIdempotencyStorageReady = false;
                    withdrawIdempotencyFailureReason = 'storage_error';
                    return false;
                }

                storageKey = withdrawIdempotencyStorageKey(normalizedUserId);
                try {
                    serialized = window.localStorage.getItem(storageKey);
                } catch (error) {
                    withdrawIdempotencyStorageReady = false;
                    withdrawIdempotencyFailureReason = 'storage_error';
                    return false;
                }

                if (!serialized) {
                    withdrawIdempotencyStorageReady = true;
                    return true;
                }

                try {
                    state = JSON.parse(serialized);
                    valid = !!(
                        state
                        && state.version === 1
                        && String(state.userId) === normalizedUserId
                        && typeof state.key === 'string'
                        && /^[A-Za-z0-9._:-]{1,100}$/.test(state.key)
                        && typeof state.normalizedAmount === 'string'
                        && normalizeWithdrawAmount(state.normalizedAmount) === state.normalizedAmount
                    );
                } catch (error) {
                    valid = false;
                }

                if (!valid) {
                    try {
                        window.localStorage.removeItem(storageKey);
                    } catch (error) {
                        withdrawIdempotencyStorageReady = false;
                        withdrawIdempotencyFailureReason = 'storage_error';
                        return false;
                    }
                    withdrawIdempotencyStorageReady = true;
                    return true;
                }

                withdrawIdempotencyKey = state.key;
                withdrawIdempotencyAmount = state.normalizedAmount;
                withdrawIdempotencyStorageReady = true;

                return true;
            }

            function persistWithdrawIdempotencyState(userId, key, normalizedAmount) {
                var normalizedUserId = String(userId === undefined || userId === null ? '' : userId).trim();
                var state;

                if (!normalizedUserId || !key || !normalizedAmount) {
                    withdrawIdempotencyStorageReady = false;
                    withdrawIdempotencyFailureReason = 'storage_error';
                    return false;
                }

                state = {
                    version: 1,
                    userId: normalizedUserId,
                    key: key,
                    normalizedAmount: normalizedAmount
                };
                try {
                    window.localStorage.setItem(
                        withdrawIdempotencyStorageKey(normalizedUserId),
                        JSON.stringify(state)
                    );
                } catch (error) {
                    withdrawIdempotencyStorageReady = false;
                    withdrawIdempotencyFailureReason = 'storage_error';
                    return false;
                }

                withdrawIdempotencyUserId = normalizedUserId;
                withdrawIdempotencyKey = key;
                withdrawIdempotencyAmount = normalizedAmount;
                withdrawIdempotencyStorageReady = true;
                withdrawIdempotencyFailureReason = null;

                return true;
            }

            function prepareWithdrawIdempotencyKey(userId, amount) {
                var normalizedUserId = String(userId === undefined || userId === null ? '' : userId).trim();
                var normalizedAmount = normalizeWithdrawAmount(amount);
                var key;

                if (!normalizedAmount) {
                    return {status: 'invalid_amount'};
                }
                if (!normalizedUserId) {
                    withdrawIdempotencyStorageReady = false;
                    withdrawIdempotencyFailureReason = 'storage_error';
                    return {status: 'storage_error'};
                }
                if (withdrawIdempotencyUserId !== normalizedUserId
                    && !restoreWithdrawIdempotencyState(normalizedUserId)) {
                    return {status: 'storage_error'};
                }
                if (!withdrawIdempotencyStorageReady) {
                    return {status: 'storage_error'};
                }
                if (withdrawIdempotencyKey) {
                    if (withdrawIdempotencyAmount !== normalizedAmount) {
                        return {status: 'amount_conflict'};
                    }
                    return {status: 'ready', key: withdrawIdempotencyKey};
                }

                key = createWithdrawIdempotencyKey();
                if (!persistWithdrawIdempotencyState(normalizedUserId, key, normalizedAmount)) {
                    return {status: 'storage_error'};
                }

                return {status: 'ready', key: key};
            }

            function currentWithdrawIdempotencyKey(amount) {
                var prepared = prepareWithdrawIdempotencyKey($('#withdrawUserId').val(), amount);

                withdrawIdempotencyFailureReason = prepared.status;

                return prepared.status === 'ready' ? prepared.key : null;
            }

            function clearWithdrawIdempotencyKey() {
                if (withdrawIdempotencyUserId) {
                    try {
                        window.localStorage.removeItem(
                            withdrawIdempotencyStorageKey(withdrawIdempotencyUserId)
                        );
                    } catch (error) {
                        withdrawIdempotencyStorageReady = false;
                        withdrawIdempotencyFailureReason = 'storage_error';
                        return false;
                    }
                }

                withdrawIdempotencyKey = null;
                withdrawIdempotencyAmount = null;
                withdrawIdempotencyStorageReady = true;
                withdrawIdempotencyFailureReason = null;
                $('[name="idempotency_key"]').val('');

                return true;
            }

            function setWithdrawSubmitting(submitting) {
                withdrawSubmitting = !!submitting;
                renderAllowedState();
            }

            // 统一读取多语言文案；缺少语言模块时返回 key，便于定位缺失项。
            function t(key) {
                return typeof CrmLang !== 'undefined' && CrmLang.t ? CrmLang.t(key) : key;
            }

            // 出金提交只接受 1xxx 成功码，避免把分页等 2xxx 响应误判为创建成功。
            function isSuccess(res) {
                return !!(res && res.code >= 1000 && res.code < 2000);
            }

            // 金额统一保留两位小数，避免异常值破坏布局。
            function money(value) {
                var numberValue = Number(value || 0);
                return isNaN(numberValue) ? '0.00' : numberValue.toFixed(2);
            }
            // 银行卡只显示末四位，详情和表格不直接暴露完整卡号。
            function bankNo(value) {
                value = String(value || '');
                return value.length > 4 ? value.replace(/.(?=.{4})/g, '*') : value;
            }

            // 收集历史记录筛选条件，只提交用户实际填写的字段。
            function collectFilters() {
                var params = {};

                $('#withdrawSearchForm').find('input[name], select[name]').each(function () {
                    var $field = $(this);
                    var value = $field.val();

                    if (value !== null && value !== '') {
                        params[$field.attr('name')] = value;
                    }
                });

                return params;
            }

            // 根据接口返回的出金开关控制表单状态，并展示禁用原因。
            function renderAllowedState(message) {
                var $notice = $('#withdrawDisabledNotice');
                var disabled = !pageData.isAllowed
                    || withdrawSubmitting
                    || !withdrawConfigReady
                    || !withdrawIdempotencyStorageReady;
                var disabledMessage = !withdrawIdempotencyStorageReady
                    ? t('front.withdraw_idempotency_storage_unavailable')
                    : (message || t('front.withdraw_disabled'));

                $('.withdraw-page').toggleClass('is-disabled', disabled);
                $('#withdrawBtn').prop('disabled', disabled).toggleClass('layui-btn-disabled', disabled);
                if (!$notice.length) {
                    return;
                }

                if (!disabled) {
                    $notice.addClass('layui-hide').text('');
                    return;
                }

                $notice.removeClass('layui-hide').text(disabledMessage);
            }

            // 将页面配置接口返回的余额、银行卡、限额和汇率写入表单，并重新计算金额。
            function fillPageFields(data) {
                var user = data.user || {};
                var bank = data.bank || {};
                var limits = data.withdraw_limits || {};
                var rates = data.exchange_rates || {};

                pageData.isAllowed = !(data.is_allowed === false || data.is_allowed === 0 || data.is_allowed === '0');
                pageData.min = Number(limits.min || 0);
                pageData.max = Number(limits.max || 0);
                pageData.feeRate = Number(data.fee_rate || 0);
                pageData.fixedFee = Number(data.fixed_fee || 0);
                pageData.availableAmount = Number(user.available_amount || 0);

                $('#withdrawUserId').val(user.user_id || '');
                if (typeof renderedWithdrawIntentKey === 'function' && renderedWithdrawIntentKey()) {
                    withdrawIdempotencyStorageReady = true;
                    withdrawIdempotencyFailureReason = null;
                    withdrawIdempotencyKey = null;
                    withdrawIdempotencyAmount = null;
                    withdrawIdempotencyUserId = String(user.user_id || '');
                } else {
                    restoreWithdrawIdempotencyState(user.user_id || '');
                }
                $('#withdrawBalance').val(money(user.balance));
                $('#withdrawAvailable').val(money(user.available_amount));
                $('#withdrawExchangeRate').val(rates.CNY || rates.cny || '');
                $('#withdrawBankName').val(bank.bank_name ? bank.bank_name + ' / ' + bankNo(bank.bank_no) : bankNo(bank.bank_no));
                calculateAmount();
                renderAllowedState(data.disabled_message || '');
            }

            // 加载出金页面配置；失败时明确提示，不做静默兜底。
            function loadPageConfig(completion) {
                function complete(success) {
                    if (typeof completion === 'function') {
                        completion(success);
                        return;
                    }
                    setWithdrawSubmitting(false);
                }

                withdrawConfigReady = false;
                setWithdrawSubmitting(withdrawSubmitting);
                CrmAjax.request({
                    guard: 'front',
                    method: 'GET',
                    url: '/api/front/withdrawals/form-options',
                    success: function (res) {
                        if (!isSuccess(res)) {
                            layer.msg(res.message || t('common.error'), {icon: 2});
                            complete(false);
                            return;
                        }

                        withdrawConfigReady = true;
                        fillPageFields(res.data || {});
                        form.render();
                        complete(true);
                    },
                    error: function (res) {
                        layer.msg((res && res.message) || t('common.error'), {icon: 2});
                        complete(false);
                    }
                });
            }

            // 根据申请金额和费率实时计算手续费与实际到账金额。
            function calculateAmount() {
                var amount = Number($('#withdrawAmount').val() || 0);
                var fee = 0;
                var actual = 0;

                if (amount > 0) {
                    fee = pageData.fixedFee + amount * (pageData.feeRate / 100);
                    actual = Math.max(0, amount - fee);
                }

                $('#withdrawFee').val(money(fee));
                $('#withdrawActualAmount').val(money(actual));
            }

            function clearWithdrawEditableFields() {
                $('#withdrawAmount').val('');
                $('#withdrawPassword').val('');
                $('#withdrawAgree').prop('checked', false);
                calculateAmount();
                form.render();
            }

            // 首次渲染历史表格，之后只 reload 数据，避免重复创建 Layui table 实例。
            function renderHistoryTable() {
                var columns = [
                    {field: 'order_no', title: t('front.order_no'), minWidth: 180},
                    {field: 'userId', title: t('front.user_id'), width: 120},
                    {field: 'userName', title: t('front.user_name'), width: 140},
                    {field: 'applyamount', title: t('front.apply_amount'), width: 130, format: 'money', templet: function (d) { return money(d.applyamount); }},
                    {field: 'actdraw', title: t('front.actual_amount'), width: 130, format: 'money', templet: function (d) { return money(d.actdraw); }},
                    {field: 'drawpoundage', title: t('front.fee'), width: 120, format: 'money', templet: function (d) { return money(d.drawpoundage); }},
                    {field: 'drawrate', title: t('front.exchange_rate'), width: 120},
                    {field: 'drawbankno', title: t('front.bank_no'), minWidth: 160, templet: function (d) { return bankNo(d.drawbankno); }},
                    {field: 'drawbankclass', title: t('front.bank_name'), minWidth: 160},
                    {field: 'status_text', title: t('common.status'), width: 120},
                    {field: 'funding_status_text', title: t('front.funding_status'), width: 140},
                    {field: 'applyremark', title: t('front.reject_reason'), minWidth: 180},
                    {field: 'withdrawalDate', title: t('front.flow_time'), minWidth: 170}
                ];

                if (historyRendered) {
                    table.reloadData('withdrawHistoryTable', {
                        method: 'GET',
                        where: collectFilters(),
                        page: {curr: 1}
                    });
                    return;
                }

                table.render(CrmTable.layuiConfig('front', {
                    elem: '#withdrawHistoryTable',
                    id: 'withdrawHistoryTable',
                    url: '/api/front/withdrawals/history',
                    method: 'GET',
                    where: collectFilters(),
                    cols: [columns],
                    summaryElem: '#withdrawHistorySummary'
                }));
                historyRendered = true;
            }

            // 出金提交前做必填和金额边界校验。
            function validateSubmit(field) {
                var amount = Number(field.amount || 0);

                if (!pageData.isAllowed) {
                    layer.msg(t('front.withdraw_disabled'), {icon: 2});
                    return false;
                }
                if (!amount || amount <= 0) {
                    layer.msg(t('validation.numeric'), {icon: 2});
                    return false;
                }
                if (pageData.min && amount < pageData.min) {
                    layer.msg(t('front.withdraw_amount_below_min'), {icon: 2});
                    return false;
                }
                if (pageData.max && amount > pageData.max) {
                    layer.msg(t('front.withdraw_amount_above_max'), {icon: 2});
                    return false;
                }
                if (!field.password) {
                    layer.msg(t('front.withdraw_password_placeholder'), {icon: 2});
                    return false;
                }
                if (!$('#withdrawAgree').is(':checked')) {
                    layer.msg(t('front.withdrawal_terms_required'), {icon: 2});
                    return false;
                }

                return true;
            }

            // 提交出金申请后保留用户 ID 和页面配置，再刷新配置与历史记录。
            function submitWithdraw(field) {
                var amount = normalizeWithdrawAmount(field.amount);
                var requestKey;
                var renderedKey;

                if (!validateSubmit(field)) {
                    return;
                }
                if (withdrawSubmitting) {
                    return;
                }

                renderedKey = typeof renderedWithdrawIntentKey === 'function'
                    ? renderedWithdrawIntentKey()
                    : '';
                requestKey = renderedKey || currentWithdrawIdempotencyKey(amount);
                if (!requestKey) {
                    if (withdrawIdempotencyFailureReason === 'storage_error') {
                        renderAllowedState();
                        layer.msg(t('front.withdraw_idempotency_storage_unavailable'), {icon: 2});
                        return;
                    }
                    layer.msg(
                        t(withdrawIdempotencyFailureReason === 'invalid_amount'
                            ? 'validation.numeric'
                            : 'front.withdraw_retry_original_amount'),
                        {icon: 2}
                    );
                    return;
                }
                setWithdrawSubmitting(true);

                CrmAjax.request({
                    guard: 'front',
                    url: '/api/front/withdrawals/submissions',
                    headers: {'Idempotency-Key': requestKey},
                    data: {
                        amount: amount,
                        withdraw_amt: amount,
                        password: field.password,
                        withdraw_password: field.password,
                        withdraw_psw: field.password,
                        idempotency_key: requestKey,
                        agree: $('#withdrawAgree').is(':checked') ? 1 : 0
                    },
                    success: function (res) {
                        if (!isSuccess(res)) {
                            setWithdrawSubmitting(false);
                            if (res && res.code >= 1000 && res.code < 5000) {
                                if (!clearWithdrawIdempotencyKey()) {
                                    renderAllowedState();
                                    layer.msg(t('front.withdraw_idempotency_storage_unavailable'), {icon: 2});
                                    return;
                                }
                            }
                            layer.msg(res.message || t('common.error'), {icon: 2});
                            return;
                        }

                        if (!clearWithdrawIdempotencyKey()) {
                            setWithdrawSubmitting(false);
                            renderAllowedState();
                            layer.msg(t('front.withdraw_idempotency_storage_unavailable'), {icon: 2});
                            return;
                        }
                        layer.msg(res.message || t('common.success'), {icon: 1});
                        clearWithdrawEditableFields();
                        loadPageConfig(function () {
                            setWithdrawSubmitting(false);
                        });
                        renderHistoryTable();
                    },
                    error: function (res) {
                        setWithdrawSubmitting(false);
                        if (res && res.code >= 1000 && res.code < 5000) {
                            if (!clearWithdrawIdempotencyKey()) {
                                renderAllowedState();
                                layer.msg(t('front.withdraw_idempotency_storage_unavailable'), {icon: 2});
                                return;
                            }
                        }
                        layer.msg((res && res.message) || t('common.error'), {icon: 2});
                    }
                });
            }

            // 用户输入金额时立即刷新手续费和到账金额。
            $('#withdrawAmount').on('input propertychange', calculateAmount);

            form.on('submit(withdrawSubmit)', function (data) {
                submitWithdraw(data.field || {});
                return false;
            });

            form.on('submit(withdrawSearch)', function () {
                renderHistoryTable();
                return false;
            });

            $('#withdrawSearchReset').on('click', function () {
                $('#withdrawSearchForm')[0].reset();
                form.render();

                renderHistoryTable();
            });

            // 页面启动顺序：语言、日期、表单、页面配置和历史表格依次初始化。
            function boot() {
                if (typeof CrmLang !== 'undefined') {
                    CrmLang.updateUI();
                }
                if (typeof CrmDateRange !== 'undefined') {
                    CrmDateRange.init($('.withdraw-page'));
                }
                form.render();

                loadPageConfig();
                renderHistoryTable();
            }

            if (typeof CrmLang !== 'undefined' && CrmLang.loadLanguage) {
                CrmLang.loadLanguage(CrmLang.getLocale()).then(boot).catch(boot);
            } else {
                boot();
            }
        });
    });

    registry['legacy/forgot-password'] = once(function () {
        layui.use(['jquery'], function () {
            var $ = layui.jquery;
            var langPackCache = {};

            function applyTranslations(data) {
                data = data || {};
                $('[data-translate]').each(function () {
                    var key = $(this).data('translate');
                    if (data[key]) {
                        $(this).text(data[key]);
                    }
                });
                $('[data-translate-placeholder]').each(function () {
                    var key = $(this).data('translate-placeholder');
                    if (data[key]) {
                        $(this).attr('placeholder', data[key]);
                    }
                });
            }

            function activate(lang, data) {
                localStorage.setItem('front_lang', lang);
                localStorage.setItem('crm_locale', lang);
                applyTranslations(data);
            }

            function loadLang(lang) {
                if (langPackCache[lang]) {
                    activate(lang, langPackCache[lang]);
                    return;
                }

                $.ajax({
                    url: '/js/apps/front/i18n/' + lang + '.js?v=2026060403',
                    type: 'GET',
                    dataType: 'text',
                    cache: true,
                    success: function (text) {
                        var data = {};

                        try {
                            data = (new Function(text + '; return (typeof LANG_DATA !== "undefined" ? LANG_DATA : {});')).call(window) || {};
                        } catch (error) {
                            data = {};
                        }
                        langPackCache[lang] = data;
                        activate(lang, data);
                    }
                });
            }

            loadLang(localStorage.getItem('front_lang') || 'en');
            $(document).on('click', '.lang-switch', function () {
                loadLang($(this).data('lang') || 'zh-CN');
            });
        });
    });

    registry['legacy/profile/index'] = once(function () {
        layui.use(['jquery', 'layer'], function () {
            var $ = layui.jquery;
            var layer = layui.layer;
            var $input = $('#avatar-input');
            var uploadUrl = $input.data('upload-url') || '';
            var failedMessage = $input.data('failed-message') || 'Upload failed';

            $('#avatarUploadTrigger').on('click', function () {
                $input.trigger('click');
            });

            $input.on('change', function () {
                var file = this.files && this.files[0];
                var formData = new FormData();

                if (!file || !uploadUrl) {
                    return;
                }

                formData.append('avatar', file);
                formData.append('_token', $('meta[name=csrf-token]').attr('content') || '');

                $.ajax({
                    url: uploadUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    success: function (data) {
                        var $preview = $('#avatar-preview');

                        if (data.code === 0) {
                            if ($preview.is('img')) {
                                $preview.attr('src', data.data.url);
                            } else {
                                $preview.replaceWith('<img id="avatar-preview" src="' + data.data.url + '" alt="avatar" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid #f0f0f0;">');
                            }
                            layer.msg(data.msg);
                            return;
                        }
                        layer.msg(data.msg || failedMessage);
                    },
                    error: function () {
                        layer.msg(failedMessage);
                    }
                });
            });
        });
    });

    registry['legacy/profile/action'] = once(function () {
        /**
         * 旧前台资料操作页使用 Web Session 和 CSRF 提交旧路由。
         *
         * 文件职责：
         * - 统一处理身份证、银行卡、换绑、头像、联系方式和密码表单。
         * - 文件选择由共享 layui 上传组件（deferred 模式）校验图片格式及 10MB 上限，提交前再按缓存做必填校验。
         * - 银行卡换绑和邮箱修改先调用校验端点，再调用验证码发送端点并启动倒计时。
         *
         * 返回与失败场景：
         * - 仅 msg=SUC 或 msg=SUCCESS 代表旧协议业务成功，其他结果都按 err/col 显示明确错误。
         * - HTTP、CSRF 或无法解析的响应显示统一网络错误，不创建前端假成功分支。
         */
        layui.use(['jquery', 'layer'], function () {
            var $ = layui.jquery;
            var layer = layui.layer;
            var $page = $('[data-legacy-profile-action]').first();
            var $form = $('#legacyProfileActionForm');

            if (!$page.length || !$form.length) {
                return;
            }

            var action = String($page.attr('data-legacy-profile-action') || '');
            var isEnglish = String(document.documentElement.lang || '').toLowerCase().indexOf('en') === 0;

            // 共享上传组件与状态节点使用 CrmLang 文案，先按当前语言重译 data-translate 节点。
            if (window.CrmLang && typeof CrmLang.switchUI === 'function') {
                CrmLang.switchUI();
            }

            var messages = isEnglish ? {
                required: 'Please complete all required fields.',
                unsupportedImage: 'Only JPG, PNG and GIF images are supported.',
                imageTooLarge: 'Each image must not exceed 10 MB.',
                phoneMismatch: 'The two new phone numbers do not match.',
                passwordMismatch: 'The two new passwords do not match.',
                invalidEmail: 'Please enter a valid email address.',
                network: 'The request failed. Please check the network and try again.',
                sending: 'Sending...',
                sendCode: 'Send code',
                retrySuffix: 's',
                codeSent: 'Verification code sent.',
                submitting: 'Submitting...',
                submit: 'Confirm',
                success: 'Saved successfully.'
            } : {
                required: '请完整填写所有必填项。',
                unsupportedImage: '仅支持 JPG、PNG 和 GIF 图片。',
                imageTooLarge: '单张图片不能超过 10MB。',
                phoneMismatch: '两次填写的新手机号不一致。',
                passwordMismatch: '两次填写的新密码不一致。',
                invalidEmail: '请输入有效的邮箱地址。',
                network: '请求失败，请检查网络后重试。',
                sending: '发送中...',
                sendCode: '发送验证码',
                retrySuffix: '秒',
                codeSent: '验证码已发送。',
                submitting: '提交中...',
                submit: '确认提交',
                success: '资料已保存。'
            };
            var errorMessages = isEnglish ? {
                username: 'Please enter the legal name.',
                userIdcardNo: 'Please enter the ID card number.',
                IdcardNoExiste: 'This ID card number is already in use.',
                POSERRORFORMAT1: 'The front ID image is invalid or exceeds 10 MB.',
                POSERRORFORMAT2: 'The back ID image is invalid or exceeds 10 MB.',
                POSERRORFORMAT: 'The image format is unsupported or the file exceeds 10 MB.',
                POSOVERSIZE1: 'The image must not exceed 10 MB.',
                bankclass: 'Please enter the bank name.',
                bankno: 'Please enter the bank card number.',
                bankinfo: 'Please enter the branch address.',
                errbankpendingauth: 'The current bank card must be approved before it can be changed.',
                errisapplying: 'A pending withdrawal must finish before the bank card can be changed.',
                errpassword: 'The current password is incorrect.',
                erruseremail: 'The email does not match the current account.',
                useremail: 'The email does not match the current account.',
                erruserverfcode: 'The verification code is incorrect or expired.',
                codeErr: 'The verification code is incorrect or expired.',
                phoneErr: 'The phone information does not match the current account.',
                emailErr: 'The email information does not match the verification target.',
                pswErr: 'The current password is incorrect.',
                emailExists: 'This email address is already in use.',
                olduserpsw: 'Please enter the current password.',
                newuserpsw: 'The new password must contain at least 6 characters.',
                confirmuserpsw: 'The two new passwords do not match.',
                localpswerr: 'The current password is incorrect.',
                apipswerr: 'The trading account rejected the current password.',
                NETWORKFAIL: 'The MT4 result is unknown because the service is unavailable. No changes were saved.',
                FATALCANOTCONNECT: 'The MT4 result is unknown because the service is unavailable. No changes were saved.',
                uploadErr: 'The image upload failed.',
                UPDATEFAIL: 'The update failed. Please try again later.',
                userNotFound: 'The current session is invalid. Please sign in again.',
                typeErr: 'The requested profile action is invalid.'
            } : {
                username: '请输入真实姓名。',
                userIdcardNo: '请输入身份证号码。',
                IdcardNoExiste: '该身份证号码已被其他账户使用。',
                POSERRORFORMAT1: '身份证正面图片格式不支持或超过 10MB。',
                POSERRORFORMAT2: '身份证反面图片格式不支持或超过 10MB。',
                POSERRORFORMAT: '图片格式不支持或文件超过 10MB。',
                POSOVERSIZE1: '图片不能超过 10MB。',
                bankclass: '请输入开户银行。',
                bankno: '请输入银行卡号。',
                bankinfo: '请输入开户地址。',
                errbankpendingauth: '当前银行卡尚未审核通过，不能发起换绑。',
                errisapplying: '存在待处理的出金申请，暂时不能更换银行卡。',
                errpassword: '当前密码错误。',
                erruseremail: '邮箱与当前账户不一致。',
                useremail: '邮箱与当前账户不一致。',
                erruserverfcode: '验证码错误或已失效。',
                codeErr: '验证码错误或已失效。',
                phoneErr: '手机号信息与当前账户不一致。',
                emailErr: '邮箱信息与验证码接收目标不一致。',
                pswErr: '当前密码错误。',
                emailExists: '该邮箱已被其他账户使用。',
                olduserpsw: '请输入当前密码。',
                newuserpsw: '新密码不能少于 6 位。',
                confirmuserpsw: '两次填写的新密码不一致。',
                localpswerr: '当前密码错误。',
                apipswerr: '交易账户明确拒绝了当前密码。',
                NETWORKFAIL: 'MT4 服务不可用，结果未知，本次未保存任何更改。',
                FATALCANOTCONNECT: 'MT4 服务不可用，结果未知，本次未保存任何更改。',
                uploadErr: '图片上传失败。',
                UPDATEFAIL: '更新失败，请稍后重试。',
                userNotFound: '当前会话已失效，请重新登录。',
                typeErr: '资料操作类型无效。'
            };
            var fieldAliases = {
                verfyCode: 'updVerifyCode',
                file_img1: 'Idphoto1',
                file_img2: 'Idphoto2',
                file_img: action === 'avatar' ? 'headimg' : 'bankimg'
            };
            // 共享上传组件（deferred 模式）托管的文件字段：键与旧表单字段名保持一致。
            var legacyFileFields = action === 'identity'
                ? ['Idphoto1', 'Idphoto2']
                : (action === 'bank' || action === 'bank-change'
                    ? ['bankimg']
                    : (action === 'avatar' ? ['headimg'] : []));
            var emptyFileLabel = isEnglish ? 'No file selected' : '未选择文件';

            /** 为异步字段错误建立输入关联，使读屏工具能定位本次失败原因。 */
            function wireAccessibleStatus() {
                $form.find('[data-error-for]').each(function () {
                    var $error = $(this);
                    var field = String($error.attr('data-error-for') || '');
                    var errorId;

                    if (!field) {
                        return;
                    }
                    errorId = 'legacy-profile-error-' + field;
                    $error.attr({'id': errorId, 'role': 'status', 'aria-live': 'polite'});
                    $form.find('[name="' + field + '"]').attr('aria-describedby', errorId);
                });
            }

            wireAccessibleStatus();

            // 共享 layui 上传组件（deferred 模式）：类型/体积校验、预览与 File 缓存统一交给 CrmUpload；
            // 组件拒绝非法文件时同步映射到旧 err/col 错误位，保持与后端响应一致的提示位置。
            if (window.CrmUpload) {
                CrmUpload.init(document, {
                    onError: function (block, config) {
                        showError(config.field, messages.unsupportedImage);
                    }
                });
            }

            /** 清空上一次后端或前端校验错误，保证本次结果不会与旧状态混淆。 */
            function clearErrors() {
                $form.find('[aria-invalid="true"]').removeAttr('aria-invalid');
                $form.find('[data-error-for]').text('');
                $form.find('[data-form-error]').prop('hidden', true).text('');
            }

            /**
             * 在字段附近显示错误；无法对应字段时写入表单级错误区。
             * @param {string} field 旧响应 col 或表单字段名。
             * @param {string} message 已翻译的错误内容。
             * @return {void}
             */
            function showError(field, message) {
                var normalizedField = fieldAliases[field] || field || '';
                var $field = normalizedField ? $form.find('[name="' + normalizedField + '"]').first() : $();
                var $target = normalizedField ? $form.find('[data-error-for="' + normalizedField + '"]').first() : $();

                if ($field.length && $target.length) {
                    $field.attr('aria-invalid', 'true');
                    $target.text(message);
                    $field.trigger('focus');
                } else {
                    $form.find('[data-form-error]').prop('hidden', false).text(message);
                }
                layer.msg(message);
            }

            /** 将旧响应 err/col 转换为页面可读错误，未知错误严格走失败提示。 */
            function showResponseError(data) {
                data = data || {};
                var errorCode = String(data.err || 'UPDATEFAIL');
                var field = String(data.col || '');
                var message = errorMessages[errorCode] || String(data.message || data.msg || messages.network);

                if (errorCode === 'codeErr' && action === 'contact-email') {
                    field = 'updVerifyCode';
                }
                if (errorCode === 'erruserverfcode') {
                    field = 'userverfcode';
                }
                if (field === 'nocol' || field === 'NOCOL' || field === 'FATALCANOTCONNECT') {
                    field = '';
                }
                showError(field, message);
            }

            /**
             * 校验单个上传文件。
             * @param {File} file 浏览器选择的文件。
             * @return {string} 空字符串表示通过，否则返回用户可读错误。
             */
            function fileValidationMessage(file) {
                // 类型与体积已由共享上传组件按 data-upload-exts / data-upload-max-size 校验，
                // 非法文件会被组件复位缓存；此处仅保留必填语义供提交前统一检查。
                return file ? '' : messages.required;
            }

            /** 选择图片后由共享上传组件更新文件名、体积和本地预览；非法文件会被组件复位缓存。 */

            /**
             * 在提交前重新验证原生必填项、文件和两个确认字段。
             * @return {boolean} true 表示可以发起请求，false 表示页面已展示失败原因。
             */
            function validateForm() {
                clearErrors();

                if (!$form[0].checkValidity()) {
                    $form[0].reportValidity();
                    showError('', messages.required);
                    return false;
                }

                var fileError = '';
                var fileField = '';
                // 共享上传组件缓存即为提交事实来源：未选文件按必填拦截，非法文件已被组件复位缓存。
                legacyFileFields.forEach(function (field) {
                    if (fileError) {
                        return;
                    }
                    fileField = field;
                    fileError = fileValidationMessage(window.CrmUpload ? CrmUpload.file(field) : null);
                });
                if (fileError) {
                    showError(fileField, fileError);
                    return false;
                }

                if (action === 'contact-phone'
                    && $.trim($form.find('[name="userphoneNo"]').val()) !== $.trim($form.find('[name="newuserphoneNo"]').val())) {
                    showError('newuserphoneNo', messages.phoneMismatch);
                    return false;
                }

                if (action === 'password'
                    && String($form.find('[name="newuserpsw"]').val() || '') !== String($form.find('[name="confirmuserpsw"]').val() || '')) {
                    showError('confirmuserpsw', messages.passwordMismatch);
                    return false;
                }

                return true;
            }

            /** 生成验证码前置校验和发码接口共同使用的旧字段数据。 */
            function verificationPayload() {
                return {
                    _token: String($form.find('[name="_token"]').val() || ''),
                    type: String($form.find('[name="type"]').val() || $form.find('[name="uploadType"]').val() || ''),
                    useremail: $.trim(String($form.find('[name="useremail"]').val() || '')),
                    oldemail: $.trim(String($form.find('[name="oldemail"]').val() || '')),
                    userphoneNo: $.trim(String($form.find('[name="userphoneNo"]').val() || ''))
                };
            }

            /** 验证码发送成功后锁定按钮 60 秒，防止用户重复触发邮件。 */
            function startCodeCountdown($button) {
                var seconds = 60;
                var $label = $button.find('[data-code-label]');
                $button.prop('disabled', true);
                $label.text(seconds + messages.retrySuffix);

                var timer = window.setInterval(function () {
                    seconds -= 1;
                    if (seconds <= 0) {
                        window.clearInterval(timer);
                        $button.prop('disabled', false);
                        $label.text(messages.sendCode);
                        return;
                    }
                    $label.text(seconds + messages.retrySuffix);
                }, 1000);
            }

            /** 前置校验成功后发送验证码；任一步失败都恢复按钮并显示后端真实结果。 */
            $form.on('click', '[data-send-code]', function () {
                var $button = $(this);
                var $label = $button.find('[data-code-label]');
                var verifyUrl = String($page.attr('data-verify-url') || '');
                var codeUrl = String($page.attr('data-code-url') || '');
                var payload = verificationPayload();

                clearErrors();
                if (!verifyUrl || !codeUrl) {
                    showError('', messages.network);
                    return;
                }
                if (!payload.useremail || $form.find('[name="useremail"]')[0].validity.typeMismatch) {
                    showError('useremail', messages.invalidEmail);
                    return;
                }

                $button.prop('disabled', true);
                $label.text(messages.sending);
                $.ajax({
                    url: verifyUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: payload,
                    headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
                    success: function (verifyResponse) {
                        if (!verifyResponse || verifyResponse.msg !== 'SUC') {
                            $button.prop('disabled', false);
                            $label.text(messages.sendCode);
                            if (verifyResponse && verifyResponse._eml === 'useremail') {
                                showError('useremail', errorMessages.emailExists);
                                return;
                            }
                            if (verifyResponse && verifyResponse._tel === 'userphoneNo') {
                                showError('userphoneNo', errorMessages.phoneErr);
                                return;
                            }
                            showResponseError(verifyResponse);
                            return;
                        }

                        $.ajax({
                            url: codeUrl,
                            type: 'POST',
                            dataType: 'json',
                            data: payload,
                            headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
                            success: function (codeResponse) {
                                if (!codeResponse || codeResponse.status !== true) {
                                    $button.prop('disabled', false);
                                    $label.text(messages.sendCode);
                                    showError('', messages.network);
                                    return;
                                }
                                layer.msg(messages.codeSent);
                                startCodeCountdown($button);
                            },
                            error: function () {
                                $button.prop('disabled', false);
                                $label.text(messages.sendCode);
                                showError('', messages.network);
                            }
                        });
                    },
                    error: function () {
                        $button.prop('disabled', false);
                        $label.text(messages.sendCode);
                        showError('', messages.network);
                    }
                });
            });

            /** 成功后关闭旧弹层并刷新顶层入口；密码修改因会话失效转到登录页。 */
            function completeSuccess() {
                var successUrl = String($page.attr('data-success-url') || '/user/index');

                layer.msg(messages.success);
                window.setTimeout(function () {
                    try {
                        if (window.parent !== window && window.parent.layer) {
                            window.parent.layer.closeAll();
                        }
                        window.top.location.href = successUrl;
                    } catch (error) {
                        window.location.href = successUrl;
                    }
                }, 650);
            }

            /** 以 FormData 或 URL 编码提交旧 Session 路由，分别兼容文件表单和普通表单。 */
            $form.on('submit', function (event) {
                event.preventDefault();
                if (!validateForm()) {
                    return;
                }

                var $button = $form.find('[data-submit-button]');
                var $label = $button.find('[data-submit-label]');
                var submitUrl = String($page.attr('data-submit-url') || '');
                var hasFileInputs = legacyFileFields.length > 0;
                var requestData = hasFileInputs ? new FormData($form[0]) : $form.serialize();

                // 共享上传组件以 deferred 模式缓存所选文件，提交前按旧字段名补进 FormData，
                // 保证旧 Session 路由接收的字段口径（Idphoto1/Idphoto2/bankimg/headimg）完全不变。
                if (hasFileInputs && window.CrmUpload) {
                    legacyFileFields.forEach(function (field) {
                        var file = CrmUpload.file(field);
                        if (file) {
                            requestData.append(field, file, file.name);
                        }
                    });
                }

                if (!submitUrl) {
                    showError('', messages.network);
                    return;
                }

                $button.prop('disabled', true);
                $label.text(messages.submitting);
                $.ajax({
                    url: submitUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: requestData,
                    processData: !hasFileInputs,
                    contentType: hasFileInputs ? false : 'application/x-www-form-urlencoded; charset=UTF-8',
                    headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
                    success: function (data) {
                        if (data && (data.msg === 'SUC' || data.msg === 'SUCCESS')) {
                            completeSuccess();
                            return;
                        }
                        $button.prop('disabled', false);
                        $label.text(messages.submit);
                        showResponseError(data);
                    },
                    error: function (xhr) {
                        $button.prop('disabled', false);
                        $label.text(messages.submit);
                        if (xhr && xhr.responseJSON && (xhr.responseJSON.err || xhr.responseJSON.msg)) {
                            showResponseError(xhr.responseJSON);
                            return;
                        }
                        showError('', messages.network);
                    }
                });
            });
        });
    });

    registry['legacy/profile/show'] = once(function () {
        layui.use(['jquery'], function () {
            var $ = layui.jquery;
            var $input = $('#avatarFile');
            var uploadUrl = $input.data('upload-url') || '';
            var failedMessage = $input.data('failed-message') || 'Upload failed';
            var loadingMessage = $input.data('loading-message') || 'Loading';

            $('#avatarZone').on('click', function () {
                $input.trigger('click');
            });

            $input.on('change', function () {
                var file = this.files && this.files[0];
                var formData = new FormData();

                if (!file || !uploadUrl) {
                    return;
                }

                formData.append('avatar', file);
                formData.append('_token', $('meta[name=csrf-token]').attr('content') || '');
                $('#uploadMsg').text(loadingMessage).css('color', '#888');

                $.ajax({
                    url: uploadUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (data) {
                        if (data.code === 0) {
                            $('#avatarZone').html('<img src="' + data.url + '" class="upload-preview" id="avatarPreview">');
                            $('#uploadMsg').text(data.message).css('color', '#166534');
                            return;
                        }
                        $('#uploadMsg').text(failedMessage).css('color', '#e03131');
                    },
                    error: function () {
                        $('#uploadMsg').text(failedMessage).css('color', '#e03131');
                    }
                });
            });
        });
    });

    onReady(function () {
        initFramePageBridge();
        runMarkedPages();
    });

    exports('frontPages', {
        run: run,
        has: has,
        registry: registry
    });
});
