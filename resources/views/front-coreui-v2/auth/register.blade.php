<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>用户注册 - CRM 系统</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- CoreUI CSS -->
    <link href="https://cdn.jsdelivr.net/npm/@coreui/coreui@5.0.0/dist/css/coreui.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --cui-primary: #321fdb;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 0;
        }

        .register-container {
            width: 100%;
            max-width: 550px;
            margin: 0 auto;
            padding: 20px;
        }

        .register-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .register-header {
            background: linear-gradient(135deg, var(--cui-primary), #5856d6);
            padding: 30px;
            text-align: center;
            color: white;
        }

        .logo-circle {
            width: 70px;
            height: 70px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }

        .register-body {
            padding: 40px 30px;
        }

        .form-control:focus {
            border-color: var(--cui-primary);
            box-shadow: 0 0 0 0.25rem rgba(50, 31, 219, 0.25);
        }

        .btn-register {
            background: linear-gradient(135deg, var(--cui-primary), #5856d6);
            border: none;
            color: white;
            font-weight: 600;
            padding: 12px;
            transition: all 0.3s;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(50, 31, 219, 0.3);
            color: white;
        }

        .btn-register:disabled {
            opacity: 0.6;
            transform: none;
        }

        .input-group-text {
            background: transparent;
            border-right: none;
        }

        .input-group .form-control {
            border-left: none;
        }

        .input-group:focus-within .input-group-text {
            border-color: var(--cui-primary);
        }

        .btn-send-code {
            white-space: nowrap;
        }

        .password-strength {
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            overflow: hidden;
            margin-top: 8px;
        }

        .password-strength-bar {
            height: 100%;
            transition: all 0.3s;
            width: 0;
        }

        .strength-weak { background: #dc3545; width: 33%; }
        .strength-medium { background: #ffc107; width: 66%; }
        .strength-strong { background: #28a745; width: 100%; }
    </style>
</head>
<body>
    <div class="register-container">
        <!-- Register Card -->
        <div class="register-card">
            <!-- Header -->
            <div class="register-header">
                <div class="logo-circle">
                    <i class="fas fa-user-plus fa-2x"></i>
                </div>
                <h3 class="mb-0 fw-bold">创建账户</h3>
                <p class="mb-0 mt-2 opacity-75">开始您的交易之旅</p>
            </div>

            <!-- Body -->
            <div class="register-body">
                <form id="registerForm" action="{{ route('front_api_auth_register') }}" method="POST">
                    @csrf

                    @if($inviterId ?? null)
                        <input type="hidden" name="inviter_id" value="{{ $inviterId }}">
                        <div class="alert alert-info mb-4">
                            <i class="fas fa-info-circle me-2"></i>
                            您通过邀请链接注册，邀请码: <strong>{{ $inviterId }}</strong>
                        </div>
                    @endif

                    <!-- Username -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">用户名 <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-user text-muted"></i>
                            </span>
                            <input type="text"
                                   name="username"
                                   id="username"
                                   class="form-control"
                                   placeholder="4-20个字符，字母、数字、下划线"
                                   required
                                   pattern="[a-zA-Z0-9_]{4,20}"
                                   autocomplete="username">
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">邮箱 <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-envelope text-muted"></i>
                            </span>
                            <input type="email"
                                   name="email"
                                   id="email"
                                   class="form-control"
                                   placeholder="请输入邮箱地址"
                                   required
                                   autocomplete="email">
                        </div>
                    </div>

                    <!-- Phone -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">手机号 <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-phone text-muted"></i>
                            </span>
                            <input type="tel"
                                   name="phone"
                                   id="phone"
                                   class="form-control"
                                   placeholder="请输入手机号"
                                   required
                                   autocomplete="tel">
                            <button type="button" id="sendCodeBtn" class="btn btn-outline-primary btn-send-code">
                                <span id="codeText">获取验证码</span>
                            </button>
                        </div>
                    </div>

                    <!-- Verification Code -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">验证码 <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-shield-alt text-muted"></i>
                            </span>
                            <input type="text"
                                   name="code"
                                   id="code"
                                   class="form-control"
                                   placeholder="请输入验证码"
                                   required
                                   maxlength="6"
                                   autocomplete="off">
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">密码 <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock text-muted"></i>
                            </span>
                            <input type="password"
                                   name="password"
                                   id="password"
                                   class="form-control"
                                   placeholder="至少8个字符"
                                   required
                                   minlength="8"
                                   autocomplete="new-password"
                                   onkeyup="checkPasswordStrength()">
                            <button type="button" onclick="togglePassword('password', 'toggleIcon1')" class="btn btn-outline-secondary">
                                <i id="toggleIcon1" class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength">
                            <div id="strengthBar" class="password-strength-bar"></div>
                        </div>
                        <small id="strengthText" class="text-muted"></small>
                    </div>

                    <!-- Confirm Password -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">确认密码 <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock text-muted"></i>
                            </span>
                            <input type="password"
                                   name="password_confirmation"
                                   id="password_confirmation"
                                   class="form-control"
                                   placeholder="请再次输入密码"
                                   required
                                   autocomplete="new-password">
                            <button type="button" onclick="togglePassword('password_confirmation', 'toggleIcon2')" class="btn btn-outline-secondary">
                                <i id="toggleIcon2" class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Agreement -->
                    <div class="mb-4">
                        <div class="form-check">
                            <input type="checkbox" name="agreement" class="form-check-input" id="agreement" required>
                            <label class="form-check-label small" for="agreement">
                                我已阅读并同意 <a href="#" class="text-decoration-none">用户协议</a> 和 <a href="#" class="text-decoration-none">隐私政策</a>
                            </label>
                        </div>
                    </div>

                    <!-- Error Message -->
                    <div id="errorMessage" class="alert alert-danger d-none">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <span id="errorText"></span>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" id="submitBtn" class="btn btn-register w-100 mb-3">
                        <i class="fas fa-user-plus me-2"></i>
                        <span id="submitText">立即注册</span>
                    </button>

                    <!-- Login Link -->
                    <div class="text-center">
                        <span class="text-muted small">已有账户? </span>
                        <a href="{{ route('front_coreui_v2_page_login') }}" class="text-decoration-none small fw-semibold">立即登录</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-4">
            <p class="text-white small mb-0">© 2026 CRM System. All rights reserved.</p>
        </div>
    </div>

    <!-- Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let countdown = 0;
        let countdownTimer = null;

        function togglePassword(fieldId, iconId) {
            const passwordInput = document.getElementById(fieldId);
            const toggleIcon = document.getElementById(iconId);

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');

            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;

            strengthBar.className = 'password-strength-bar';
            if (strength === 0) {
                strengthText.textContent = '';
            } else if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
                strengthText.textContent = '密码强度: 弱';
                strengthText.className = 'text-danger small';
            } else if (strength === 3) {
                strengthBar.classList.add('strength-medium');
                strengthText.textContent = '密码强度: 中';
                strengthText.className = 'text-warning small';
            } else {
                strengthBar.classList.add('strength-strong');
                strengthText.textContent = '密码强度: 强';
                strengthText.className = 'text-success small';
            }
        }

        document.getElementById('sendCodeBtn').addEventListener('click', function() {
            const phone = document.getElementById('phone').value;

            if (!phone) {
                showError('请先输入手机号');
                return;
            }

            if (countdown > 0) {
                return;
            }

            const btn = this;
            const codeText = document.getElementById('codeText');

            btn.disabled = true;

            fetch('{{ route("front_api_auth_send_code") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ phone: phone })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success || data.code === 200) {
                    countdown = 60;
                    countdownTimer = setInterval(function() {
                        countdown--;
                        codeText.textContent = countdown + 's 后重发';
                        if (countdown <= 0) {
                            clearInterval(countdownTimer);
                            btn.disabled = false;
                            codeText.textContent = '获取验证码';
                        }
                    }, 1000);
                } else {
                    showError(data.message || data.msg || '发送验证码失败');
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Send code error:', error);
                showError('网络错误，请稍后重试');
                btn.disabled = false;
            });
        });

        document.getElementById('registerForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const password = document.getElementById('password').value;
            const passwordConfirmation = document.getElementById('password_confirmation').value;

            if (password !== passwordConfirmation) {
                showError('两次输入的密码不一致');
                return;
            }

            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submitText');

            submitBtn.disabled = true;
            submitText.textContent = '注册中...';
            hideError();

            const formData = new FormData(this);

            fetch(this.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success || data.code === 200) {
                    window.location.href = data.redirect || '{{ route("front_coreui_v2_page_dashboard") }}';
                } else {
                    showError(data.message || data.msg || '注册失败，请检查输入信息');
                    submitBtn.disabled = false;
                    submitText.textContent = '立即注册';
                }
            })
            .catch(error => {
                console.error('Register error:', error);
                showError('网络错误，请稍后重试');
                submitBtn.disabled = false;
                submitText.textContent = '立即注册';
            });
        });

        function showError(message) {
            const errorMessage = document.getElementById('errorMessage');
            const errorText = document.getElementById('errorText');
            errorText.textContent = message;
            errorMessage.classList.remove('d-none');
        }

        function hideError() {
            document.getElementById('errorMessage').classList.add('d-none');
        }

        document.getElementById('username').focus();
    </script>
</body>
</html>
