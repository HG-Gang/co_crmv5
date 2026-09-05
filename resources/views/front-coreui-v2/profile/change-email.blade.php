@extends('front-coreui-v2.layouts.app')

@section('title', '修改邮箱')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('front_coreui_v2_page_profile') }}">个人资料</a></li>
                    <li class="breadcrumb-item active">修改邮箱</li>
                </ol>
            </nav>
            <h2 class="mb-2">修改邮箱</h2>
            <p class="text-body-secondary mb-0">更换登录邮箱地址</p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0">
                        <i class="cil-envelope-closed me-2"></i>修改邮箱
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form id="changeEmailForm">
                        <div class="mb-4">
                            <label class="form-label">当前邮箱</label>
                            <input type="email" id="currentEmail" class="form-control" disabled>
                            <div class="form-text">您的当前登录邮箱</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">新邮箱地址 <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="cil-envelope-closed"></i></span>
                                <input type="email" id="newEmail" class="form-control" placeholder="请输入新邮箱地址" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">确认新邮箱 <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="cil-envelope-closed"></i></span>
                                <input type="email" id="confirmEmail" class="form-control" placeholder="请再次输入新邮箱地址" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">验证码 <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="cil-shield-alt"></i></span>
                                <input type="text" id="verifyCode" class="form-control" placeholder="请输入验证码" required>
                                <button class="btn btn-outline-primary" type="button" id="sendCodeBtn" onclick="sendVerifyCode()">
                                    发送验证码
                                </button>
                            </div>
                            <div class="form-text">验证码将发送到新邮箱地址</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">当前密码 <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="cil-lock-locked"></i></span>
                                <input type="password" id="password" class="form-control" placeholder="请输入当前密码以确认身份" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                    <i class="cil-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary-gradient px-4">
                                <i class="cil-save me-2"></i>确认修改
                            </button>
                            <a href="{{ route('front_coreui_v2_page_profile') }}" class="btn btn-outline-secondary px-4">
                                <i class="cil-x me-2"></i>取消
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h6 class="mb-3">
                        <i class="cil-info me-2"></i>重要提示
                    </h6>
                    <ul class="mb-0 text-body-secondary small">
                        <li class="mb-2">修改邮箱后，新邮箱将成为您的登录账号</li>
                        <li class="mb-2">请确保新邮箱地址有效且可以正常接收邮件</li>
                        <li class="mb-2">验证码有效期为10分钟，请及时填写</li>
                        <li class="mb-2">修改成功后，系统会自动退出，请使用新邮箱重新登录</li>
                        <li class="mb-2">如遇问题，请联系客服协助处理</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let countdown = 0;
let timer = null;

document.addEventListener('DOMContentLoaded', function() {
    loadCurrentEmail();

    document.getElementById('changeEmailForm').addEventListener('submit', function(e) {
        e.preventDefault();
        changeEmail();
    });
});

function loadCurrentEmail() {
    fetch('{{ route("front_api_profile_detail") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.profile) {
            document.getElementById('currentEmail').value = data.profile.email || '';
        }
    })
    .catch(err => {
        console.error('Load email error:', err);
    });
}

function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    if (field.type === 'password') {
        field.type = 'text';
    } else {
        field.type = 'password';
    }
}

function sendVerifyCode() {
    const newEmail = document.getElementById('newEmail').value.trim();

    if (!newEmail) {
        alert('请先输入新邮箱地址');
        return;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(newEmail)) {
        alert('邮箱地址格式不正确');
        return;
    }

    const btn = document.getElementById('sendCodeBtn');
    btn.disabled = true;

    fetch('{{ route("front_api_profile_send_email_code") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ email: newEmail })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('验证码已发送，请查收邮件');
            startCountdown();
        } else {
            alert(data.message || '发送失败');
            btn.disabled = false;
        }
    })
    .catch(err => {
        console.error('Send code error:', err);
        alert('网络错误，请稍后重试');
        btn.disabled = false;
    });
}

function startCountdown() {
    countdown = 60;
    const btn = document.getElementById('sendCodeBtn');

    timer = setInterval(function() {
        countdown--;
        btn.textContent = countdown + '秒后重发';

        if (countdown <= 0) {
            clearInterval(timer);
            btn.textContent = '发送验证码';
            btn.disabled = false;
        }
    }, 1000);
}

function changeEmail() {
    const newEmail = document.getElementById('newEmail').value.trim();
    const confirmEmail = document.getElementById('confirmEmail').value.trim();
    const verifyCode = document.getElementById('verifyCode').value.trim();
    const password = document.getElementById('password').value;

    if (!newEmail) {
        alert('请输入新邮箱地址');
        return;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(newEmail)) {
        alert('邮箱地址格式不正确');
        return;
    }

    if (newEmail !== confirmEmail) {
        alert('两次输入的邮箱地址不一致');
        return;
    }

    if (!verifyCode) {
        alert('请输入验证码');
        return;
    }

    if (!password) {
        alert('请输入当前密码');
        return;
    }

    const data = {
        new_email: newEmail,
        confirm_email: confirmEmail,
        verify_code: verifyCode,
        password: password
    };

    fetch('{{ route("front_api_profile_change_email") }}', {
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
            alert('邮箱修改成功，请使用新邮箱重新登录');
            window.location.href = '{{ route("front_coreui_v2_page_login") }}';
        } else {
            alert(data.message || '修改失败');
        }
    })
    .catch(err => {
        console.error('Change email error:', err);
        alert('网络错误，请稍后重试');
    });
}
</script>
@endsection
