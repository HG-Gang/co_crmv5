@extends('front-coreui-v2.layouts.app')

@section('title', '大户登录')

@section('content')
<div class="min-vh-100 d-flex align-items-center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7">
                <div class="card shadow-lg border-0" style="border-radius: 16px; overflow: hidden;">
                    <!-- Header -->
                    <div class="card-header bg-gradient-primary text-white text-center py-4 border-0">
                        <div class="mb-3">
                            <i class="cil-diamond" style="font-size: 3rem;"></i>
                        </div>
                        <h3 class="mb-1 fw-bold">大户专属登录</h3>
                        <p class="mb-0 opacity-75">VIP Client Login</p>
                    </div>

                    <!-- Body -->
                    <div class="card-body p-5">
                        <div class="alert alert-info border-0 mb-4">
                            <i class="cil-info me-2"></i>
                            <strong>尊贵提示：</strong>大户登录通道为高净值客户专享，请使用您的专属凭证登录
                        </div>

                        <form id="bigNumberLoginForm">
                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    <i class="cil-user me-2"></i>大户账号
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="bigNumber" class="form-control form-control-lg" placeholder="请输入您的大户专属账号" required autofocus>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    <i class="cil-lock-locked me-2"></i>登录密码
                                    <span class="text-danger">*</span>
                                </label>
                                <div class="input-group input-group-lg">
                                    <input type="password" id="password" class="form-control" placeholder="请输入登录密码" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                                        <i class="cil-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    <i class="cil-shield-alt me-2"></i>验证码
                                    <span class="text-danger">*</span>
                                </label>
                                <div class="row g-2">
                                    <div class="col-7">
                                        <input type="text" id="captcha" class="form-control form-control-lg" placeholder="请输入验证码" required>
                                    </div>
                                    <div class="col-5">
                                        <img id="captchaImage" src="{{ route('front_api_captcha') }}" class="img-fluid rounded cursor-pointer" onclick="refreshCaptcha()" alt="验证码" style="height: 48px; width: 100%; object-fit: cover;">
                                    </div>
                                </div>
                                <div class="form-text">
                                    <a href="javascript:void(0)" onclick="refreshCaptcha()">
                                        <i class="cil-reload me-1"></i>看不清？点击刷新
                                    </a>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="rememberMe">
                                    <label class="form-check-label" for="rememberMe">
                                        记住我的登录状态
                                    </label>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary-gradient btn-lg w-100 mb-3">
                                <i class="cil-account-logout me-2"></i>立即登录
                            </button>
                        </form>

                        <div class="text-center">
                            <a href="{{ route('front_coreui_v2_page_login') }}" class="text-decoration-none">
                                <i class="cil-arrow-left me-1"></i>返回普通登录
                            </a>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="card-footer bg-light border-0 text-center py-3">
                        <p class="mb-2 text-body-secondary small">
                            <i class="cil-headphones me-1"></i>如需协助，请联系您的专属客户经理
                        </p>
                        <div class="d-flex justify-content-center gap-3 small">
                            <span class="badge bg-warning text-dark">
                                <i class="cil-phone me-1"></i>VIP热线: 400-xxx-xxxx
                            </span>
                            <span class="badge bg-success">
                                <i class="cil-chat-bubble me-1"></i>7x24在线
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Features -->
                <div class="row g-3 mt-4">
                    <div class="col-4">
                        <div class="card border-0 bg-white bg-opacity-25 text-white text-center p-3">
                            <i class="cil-shield-alt mb-2" style="font-size: 2rem;"></i>
                            <small>专属通道</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="card border-0 bg-white bg-opacity-25 text-white text-center p-3">
                            <i class="cil-speedometer mb-2" style="font-size: 2rem;"></i>
                            <small>极速交易</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="card border-0 bg-white bg-opacity-25 text-white text-center p-3">
                            <i class="cil-star mb-2" style="font-size: 2rem;"></i>
                            <small>尊享服务</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('bigNumberLoginForm').addEventListener('submit', function(e) {
        e.preventDefault();
        handleLogin();
    });
});

function togglePassword() {
    const field = document.getElementById('password');
    if (field.type === 'password') {
        field.type = 'text';
    } else {
        field.type = 'password';
    }
}

function refreshCaptcha() {
    const img = document.getElementById('captchaImage');
    img.src = '{{ route("front_api_captcha") }}?' + Date.now();
}

function handleLogin() {
    const bigNumber = document.getElementById('bigNumber').value.trim();
    const password = document.getElementById('password').value;
    const captcha = document.getElementById('captcha').value.trim();
    const rememberMe = document.getElementById('rememberMe').checked;

    if (!bigNumber) {
        alert('请输入大户账号');
        return;
    }

    if (!password) {
        alert('请输入登录密码');
        return;
    }

    if (!captcha) {
        alert('请输入验证码');
        return;
    }

    const data = {
        big_number: bigNumber,
        password: password,
        captcha: captcha,
        remember_me: rememberMe
    };

    fetch('{{ route("front_api_big_number_login") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            window.location.href = data.redirect_url || '{{ route("front_coreui_v2_page_dashboard") }}';
        } else {
            alert(data.message || '登录失败');
            refreshCaptcha();
        }
    })
    .catch(err => {
        console.error('Login error:', err);
        alert('网络错误，请稍后重试');
        refreshCaptcha();
    });
}
</script>

<style>
.cursor-pointer {
    cursor: pointer;
}

.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.btn-primary-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-primary-gradient:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
    color: white;
}
</style>
@endsection
