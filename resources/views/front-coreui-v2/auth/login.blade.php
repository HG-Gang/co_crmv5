<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>用户登录 - CRM 系统</title>

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

        .login-container {
            width: 100%;
            max-width: 450px;
            padding: 20px;
        }

        .login-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(135deg, var(--cui-primary), #5856d6);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }

        .logo-circle {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .login-body {
            padding: 40px 30px;
        }

        .form-control:focus {
            border-color: var(--cui-primary);
            box-shadow: 0 0 0 0.25rem rgba(50, 31, 219, 0.25);
        }

        .btn-login {
            background: linear-gradient(135deg, var(--cui-primary), #5856d6);
            border: none;
            color: white;
            font-weight: 600;
            padding: 12px;
            transition: all 0.3s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(50, 31, 219, 0.3);
            color: white;
        }

        .btn-login:disabled {
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
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Login Card -->
        <div class="login-card">
            <!-- Header -->
            <div class="login-header">
                <div class="logo-circle">
                    <i class="fas fa-chart-line fa-2x"></i>
                </div>
                <h3 class="mb-0 fw-bold">CRM 系统</h3>
                <p class="mb-0 mt-2 opacity-75">欢迎登录</p>
            </div>

            <!-- Body -->
            <div class="login-body">
                <form id="loginForm" action="{{ route('front_api_auth_login') }}" method="POST">
                    @csrf

                    <!-- Username -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">用户名 / 邮箱 / 手机号</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-user text-muted"></i>
                            </span>
                            <input type="text"
                                   name="account"
                                   id="account"
                                   class="form-control"
                                   placeholder="请输入邮箱或用户ID"
                                   required
                                   autocomplete="username">
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">密码</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock text-muted"></i>
                            </span>
                            <input type="password"
                                   name="password"
                                   id="password"
                                   class="form-control"
                                   placeholder="请输入密码"
                                   required
                                   autocomplete="current-password">
                            <button type="button"
                                    onclick="togglePassword()"
                                    class="btn btn-outline-secondary">
                                <i id="toggleIcon" class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Remember Me & Forgot Password -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input type="checkbox" name="remember" class="form-check-input" id="remember">
                            <label class="form-check-label" for="remember">记住我</label>
                        </div>
                        <a href="{{ route('front_page_forgot_password') }}" class="text-decoration-none small">忘记密码?</a>
                    </div>

                    <!-- Error Message -->
                    <div id="errorMessage" class="alert alert-danger d-none">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <span id="errorText"></span>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" id="submitBtn" class="btn btn-login w-100 mb-3">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        <span id="submitText">登录</span>
                    </button>

                    <!-- Register Link -->
                    <div class="text-center">
                        <span class="text-muted small">还没有账户? </span>
                        <a href="{{ route('front_page_register') }}" class="text-decoration-none small fw-semibold">立即注册</a>
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
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');

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

        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submitText');
            const errorMessage = document.getElementById('errorMessage');
            const errorText = document.getElementById('errorText');

            // Disable button
            submitBtn.disabled = true;
            submitText.textContent = '登录中...';
            errorMessage.classList.add('d-none');

            // Get form data
            const formData = new FormData(this);

            // Submit via fetch
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
                    // Success - redirect to dashboard
                    window.location.href = data.redirect || '{{ route("front_coreui_v2_page_dashboard") }}';
                } else {
                    // Error - show message
                    errorText.textContent = data.message || data.msg || '登录失败，请检查用户名和密码';
                    errorMessage.classList.remove('d-none');
                    submitBtn.disabled = false;
                    submitText.textContent = '登录';
                }
            })
            .catch(error => {
                console.error('Login error:', error);
                errorText.textContent = '网络错误，请稍后重试';
                errorMessage.classList.remove('d-none');
                submitBtn.disabled = false;
                submitText.textContent = '登录';
            });
        });

        // Auto-focus account field
        document.getElementById('account').focus();
    </script>
</body>
</html>
