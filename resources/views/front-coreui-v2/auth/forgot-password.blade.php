<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>忘记密码 - CRM 系统</title>

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
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .forgot-container {
            width: 100%;
            max-width: 500px;
            padding: 20px;
        }

        .forgot-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .forgot-header {
            background: linear-gradient(135deg, var(--cui-primary), #5856d6);
            padding: 35px 30px;
            text-align: center;
            color: white;
        }

        .logo-circle {
            width: 75px;
            height: 75px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }

        .forgot-body {
            padding: 40px 30px;
        }

        .form-control:focus {
            border-color: var(--cui-primary);
            box-shadow: 0 0 0 0.25rem rgba(50, 31, 219, 0.25);
        }

        .btn-reset {
            background: linear-gradient(135deg, var(--cui-primary), #5856d6);
            border: none;
            color: white;
            font-weight: 600;
            padding: 12px;
            transition: all 0.3s;
        }

        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(50, 31, 219, 0.3);
            color: white;
        }

        .btn-reset:disabled {
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

        .step {
            display: none;
        }

        .step.active {
            display: block;
        }

        .steps-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }

        .step-item {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #6c757d;
            margin: 0 10px;
            position: relative;
        }

        .step-item.active {
            background: var(--cui-primary);
            color: white;
        }

        .step-item.completed {
            background: #28a745;
            color: white;
        }

        .step-item::after {
            content: '';
            position: absolute;
            width: 50px;
            height: 2px;
            background: #e9ecef;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
        }

        .step-item:last-child::after {
            display: none;
        }

        .step-item.completed::after {
            background: #28a745;
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <!-- Forgot Password Card -->
        <div class="forgot-card">
            <!-- Header -->
            <div class="forgot-header">
                <div class="logo-circle">
                    <i class="fas fa-key fa-2x"></i>
                </div>
                <h3 class="mb-0 fw-bold">找回密码</h3>
                <p class="mb-0 mt-2 opacity-75">重置您的账户密码</p>
            </div>

            <!-- Body -->
            <div class="forgot-body">
                <!-- Steps Indicator -->
                <div class="steps-indicator">
                    <div class="step-item active" id="indicator1">1</div>
                    <div class="step-item" id="indicator2">2</div>
                    <div class="step-item" id="indicator3">3</div>
                </div>

                <!-- Step 1: Verify Identity -->
                <div class="step active" id="step1">
                    <h5 class="mb-3 text-center">验证身份</h5>
                    <form id="verifyForm">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">用户名 / 邮箱 / 手机号</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-user text-muted"></i>
                                </span>
                                <input type="text"
                                       id="identifier"
                                       class="form-control"
                                       placeholder="请输入用户名、邮箱或手机号"
                                       required>
                            </div>
                        </div>

                        <div id="step1Error" class="alert alert-danger d-none mb-3">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <span id="step1ErrorText"></span>
                        </div>

                        <button type="submit" id="verifyBtn" class="btn btn-reset w-100">
                            <i class="fas fa-arrow-right me-2"></i>
                            <span id="verifyText">下一步</span>
                        </button>
                    </form>
                </div>

                <!-- Step 2: Verify Code -->
                <div class="step" id="step2">
                    <h5 class="mb-3 text-center">验证码验证</h5>
                    <p class="text-muted text-center small mb-4">验证码已发送至您的手机 <strong id="maskedPhone"></strong></p>
                    <form id="codeForm">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">验证码</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-shield-alt text-muted"></i>
                                </span>
                                <input type="text"
                                       id="code"
                                       class="form-control"
                                       placeholder="请输入6位验证码"
                                       required
                                       maxlength="6">
                                <button type="button" id="resendBtn" class="btn btn-outline-primary">
                                    <span id="resendText">重新发送</span>
                                </button>
                            </div>
                        </div>

                        <div id="step2Error" class="alert alert-danger d-none mb-3">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <span id="step2ErrorText"></span>
                        </div>

                        <button type="submit" id="codeBtn" class="btn btn-reset w-100">
                            <i class="fas fa-arrow-right me-2"></i>
                            <span id="codeText">下一步</span>
                        </button>
                    </form>
                </div>

                <!-- Step 3: Reset Password -->
                <div class="step" id="step3">
                    <h5 class="mb-3 text-center">设置新密码</h5>
                    <form id="resetForm">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">新密码</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock text-muted"></i>
                                </span>
                                <input type="password"
                                       id="newPassword"
                                       class="form-control"
                                       placeholder="至少8个字符"
                                       required
                                       minlength="8">
                                <button type="button" onclick="togglePassword('newPassword', 'toggleIcon1')" class="btn btn-outline-secondary">
                                    <i id="toggleIcon1" class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">确认新密码</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock text-muted"></i>
                                </span>
                                <input type="password"
                                       id="confirmPassword"
                                       class="form-control"
                                       placeholder="请再次输入新密码"
                                       required>
                                <button type="button" onclick="togglePassword('confirmPassword', 'toggleIcon2')" class="btn btn-outline-secondary">
                                    <i id="toggleIcon2" class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div id="step3Error" class="alert alert-danger d-none mb-3">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <span id="step3ErrorText"></span>
                        </div>

                        <button type="submit" id="resetBtn" class="btn btn-reset w-100">
                            <i class="fas fa-check me-2"></i>
                            <span id="resetText">完成重置</span>
                        </button>
                    </form>
                </div>

                <!-- Back to Login -->
                <div class="text-center mt-4">
                    <a href="{{ route('front_coreui_v2_page_login') }}" class="text-decoration-none small">
                        <i class="fas fa-arrow-left me-1"></i> 返回登录
                    </a>
                </div>
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
        let currentStep = 1;
        let userToken = '';
        let countdown = 0;
        let countdownTimer = null;

        function togglePassword(fieldId, iconId) {
            const input = document.getElementById(fieldId);
            const icon = document.getElementById(iconId);

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        function showStep(step) {
            document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
            document.getElementById('step' + step).classList.add('active');

            document.querySelectorAll('.step-item').forEach((item, index) => {
                item.classList.remove('active', 'completed');
                if (index + 1 < step) {
                    item.classList.add('completed');
                } else if (index + 1 === step) {
                    item.classList.add('active');
                }
            });

            currentStep = step;
        }

        function showError(step, message) {
            const errorDiv = document.getElementById('step' + step + 'Error');
            const errorText = document.getElementById('step' + step + 'ErrorText');
            errorText.textContent = message;
            errorDiv.classList.remove('d-none');
        }

        function hideError(step) {
            document.getElementById('step' + step + 'Error').classList.add('d-none');
        }

        // Step 1: Verify Identity
        document.getElementById('verifyForm').addEventListener('submit', function(e) {
            e.preventDefault();
            hideError(1);

            const identifier = document.getElementById('identifier').value;
            const btn = document.getElementById('verifyBtn');
            const btnText = document.getElementById('verifyText');

            btn.disabled = true;
            btnText.textContent = '验证中...';

            fetch('{{ route("front_api_auth_password_verify") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ identifier: identifier })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success || data.code === 200) {
                    userToken = data.token || '';
                    document.getElementById('maskedPhone').textContent = data.masked_phone || '***';
                    showStep(2);
                    startCountdown();
                } else {
                    showError(1, data.message || data.msg || '用户不存在');
                    btn.disabled = false;
                    btnText.textContent = '下一步';
                }
            })
            .catch(error => {
                console.error('Verify error:', error);
                showError(1, '网络错误，请稍后重试');
                btn.disabled = false;
                btnText.textContent = '下一步';
            });
        });

        // Step 2: Verify Code
        document.getElementById('codeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            hideError(2);

            const code = document.getElementById('code').value;
            const btn = document.getElementById('codeBtn');
            const btnText = document.getElementById('codeText');

            btn.disabled = true;
            btnText.textContent = '验证中...';

            fetch('{{ route("front_api_auth_password_verify_code") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ token: userToken, code: code })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success || data.code === 200) {
                    showStep(3);
                } else {
                    showError(2, data.message || data.msg || '验证码错误');
                    btn.disabled = false;
                    btnText.textContent = '下一步';
                }
            })
            .catch(error => {
                console.error('Code verify error:', error);
                showError(2, '网络错误，请稍后重试');
                btn.disabled = false;
                btnText.textContent = '下一步';
            });
        });

        // Step 3: Reset Password
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            e.preventDefault();
            hideError(3);

            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            if (newPassword !== confirmPassword) {
                showError(3, '两次输入的密码不一致');
                return;
            }

            const btn = document.getElementById('resetBtn');
            const btnText = document.getElementById('resetText');

            btn.disabled = true;
            btnText.textContent = '重置中...';

            fetch('{{ route("front_api_auth_password_reset") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ token: userToken, password: newPassword, password_confirmation: confirmPassword })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success || data.code === 200) {
                    alert('密码重置成功，即将跳转到登录页面');
                    window.location.href = '{{ route("front_coreui_v2_page_login") }}';
                } else {
                    showError(3, data.message || data.msg || '密码重置失败');
                    btn.disabled = false;
                    btnText.textContent = '完成重置';
                }
            })
            .catch(error => {
                console.error('Reset error:', error);
                showError(3, '网络错误，请稍后重试');
                btn.disabled = false;
                btnText.textContent = '完成重置';
            });
        });

        // Resend Code
        document.getElementById('resendBtn').addEventListener('click', function() {
            if (countdown > 0) return;
            startCountdown();
        });

        function startCountdown() {
            const btn = document.getElementById('resendBtn');
            const text = document.getElementById('resendText');

            countdown = 60;
            btn.disabled = true;

            countdownTimer = setInterval(function() {
                countdown--;
                text.textContent = countdown + 's 后重发';
                if (countdown <= 0) {
                    clearInterval(countdownTimer);
                    btn.disabled = false;
                    text.textContent = '重新发送';
                }
            }, 1000);
        }

        document.getElementById('identifier').focus();
    </script>
</body>
</html>
