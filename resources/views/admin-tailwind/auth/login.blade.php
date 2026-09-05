<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>管理员登录 - CRM Admin</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo & Title -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl mb-4">
                <i class="fas fa-chart-line text-white text-2xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">CRM Admin</h1>
            <p class="text-slate-400">管理员登录</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <form id="loginForm" action="{{ route('admin_api_login') }}" method="POST">
                @csrf

                <!-- Username -->
                <div class="mb-6">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">
                        <i class="fas fa-user w-5 text-slate-400"></i> 用户名
                    </label>
                    <input type="text"
                           name="username"
                           id="username"
                           class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                           placeholder="请输入用户名"
                           required
                           autocomplete="username">
                </div>

                <!-- Password -->
                <div class="mb-6">
                    <label class="block text-sm font-semibold text-slate-700 mb-2">
                        <i class="fas fa-lock w-5 text-slate-400"></i> 密码
                    </label>
                    <div class="relative">
                        <input type="password"
                               name="password"
                               id="password"
                               class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all pr-12"
                               placeholder="请输入密码"
                               required
                               autocomplete="current-password">
                        <button type="button"
                                onclick="togglePassword()"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                            <i id="toggleIcon" class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Remember Me -->
                <div class="flex items-center justify-between mb-6">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox"
                               name="remember"
                               class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-2 focus:ring-blue-500">
                        <span class="ml-2 text-sm text-slate-600">记住我</span>
                    </label>
                    <a href="#" class="text-sm text-blue-600 hover:text-blue-700 font-medium">忘记密码?</a>
                </div>

                <!-- Error Message -->
                <div id="errorMessage" class="hidden mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-exclamation-circle text-red-600"></i>
                        <span id="errorText" class="text-sm text-red-600"></span>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit"
                        id="submitBtn"
                        class="w-full bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold py-3 px-4 rounded-lg hover:from-blue-600 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all shadow-lg hover:shadow-xl disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    <span id="submitText">登录</span>
                </button>
            </form>
        </div>

        <!-- Footer -->
        <div class="text-center mt-6">
            <p class="text-slate-400 text-sm">
                © 2026 CRM Admin System. All rights reserved.
            </p>
        </div>
    </div>

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
            errorMessage.classList.add('hidden');

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
                    window.location.href = data.redirect || '{{ route("admin_tailwind_page_dashboard") }}';
                } else {
                    // Error - show message
                    errorText.textContent = data.message || data.msg || '登录失败，请检查用户名和密码';
                    errorMessage.classList.remove('hidden');
                    submitBtn.disabled = false;
                    submitText.textContent = '登录';
                }
            })
            .catch(error => {
                console.error('Login error:', error);
                errorText.textContent = '网络错误，请稍后重试';
                errorMessage.classList.remove('hidden');
                submitBtn.disabled = false;
                submitText.textContent = '登录';
            });
        });

        // Auto-focus username field
        document.getElementById('username').focus();
    </script>
</body>
</html>
