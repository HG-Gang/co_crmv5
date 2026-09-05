@extends('front-coreui-v2.layouts.app')

@section('title', '修改密码')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('front_coreui_v2_page_profile') }}">个人资料</a></li>
                    <li class="breadcrumb-item active">修改密码</li>
                </ol>
            </nav>
            <h2 class="mb-2">修改密码</h2>
            <p class="text-body-secondary mb-0">定期修改密码，保护账户安全</p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0">
                        <i class="cil-lock-locked me-2"></i>修改密码
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form id="changePasswordForm">
                        <div class="mb-4">
                            <label class="form-label">当前密码 <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="cil-lock-locked"></i></span>
                                <input type="password" id="currentPassword" class="form-control" placeholder="请输入当前密码" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('currentPassword')">
                                    <i class="cil-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">新密码 <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="cil-lock-unlocked"></i></span>
                                <input type="password" id="newPassword" class="form-control" placeholder="请输入新密码" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('newPassword')">
                                    <i class="cil-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">密码长度8-20位，需包含大小写字母、数字</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">密码强度</label>
                            <div class="d-flex gap-2 mb-2">
                                <div class="flex-fill bg-secondary rounded" style="height: 6px;" id="strengthBar1"></div>
                                <div class="flex-fill bg-secondary rounded" style="height: 6px;" id="strengthBar2"></div>
                                <div class="flex-fill bg-secondary rounded" style="height: 6px;" id="strengthBar3"></div>
                                <div class="flex-fill bg-secondary rounded" style="height: 6px;" id="strengthBar4"></div>
                            </div>
                            <small class="text-body-secondary" id="strengthText">请输入密码</small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">确认新密码 <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="cil-lock-unlocked"></i></span>
                                <input type="password" id="confirmPassword" class="form-control" placeholder="请再次输入新密码" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirmPassword')">
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
                        <i class="cil-shield-alt me-2"></i>安全提示
                    </h6>
                    <ul class="mb-0 text-body-secondary small">
                        <li class="mb-2">密码应包含大小写字母、数字和特殊字符，长度至少8位</li>
                        <li class="mb-2">不要使用过于简单的密码，如生日、手机号等</li>
                        <li class="mb-2">定期更换密码，建议每3个月更换一次</li>
                        <li class="mb-2">不要在多个平台使用相同的密码</li>
                        <li class="mb-2">修改密码后，请重新登录所有设备</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
        e.preventDefault();
        changePassword();
    });

    document.getElementById('newPassword').addEventListener('input', function() {
        checkPasswordStrength();
    });
});

function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    if (field.type === 'password') {
        field.type = 'text';
    } else {
        field.type = 'password';
    }
}

function checkPasswordStrength() {
    const password = document.getElementById('newPassword').value;
    let strength = 0;

    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
    if (/\d/.test(password)) strength++;
    if (/[^a-zA-Z0-9]/.test(password)) strength++;

    const bars = ['strengthBar1', 'strengthBar2', 'strengthBar3', 'strengthBar4'];
    const colors = ['bg-danger', 'bg-warning', 'bg-info', 'bg-success'];
    const texts = ['弱', '一般', '中等', '强'];

    bars.forEach((bar, index) => {
        const element = document.getElementById(bar);
        element.className = 'flex-fill rounded';
        if (index < Math.min(strength, 4)) {
            element.classList.add(colors[Math.min(strength - 1, 3)]);
        } else {
            element.classList.add('bg-secondary');
        }
    });

    if (strength === 0) {
        document.getElementById('strengthText').textContent = '请输入密码';
        document.getElementById('strengthText').className = 'text-body-secondary';
    } else {
        document.getElementById('strengthText').textContent = '密码强度：' + texts[Math.min(strength - 1, 3)];
        document.getElementById('strengthText').className = 'text-' + colors[Math.min(strength - 1, 3)].replace('bg-', '');
    }
}

function changePassword() {
    const currentPassword = document.getElementById('currentPassword').value;
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;

    if (!currentPassword) {
        alert('请输入当前密码');
        return;
    }

    if (!newPassword) {
        alert('请输入新密码');
        return;
    }

    if (newPassword.length < 8) {
        alert('新密码长度不能少于8位');
        return;
    }

    if (!/[a-z]/.test(newPassword) || !/[A-Z]/.test(newPassword)) {
        alert('新密码必须包含大小写字母');
        return;
    }

    if (!/\d/.test(newPassword)) {
        alert('新密码必须包含数字');
        return;
    }

    if (newPassword !== confirmPassword) {
        alert('两次输入的新密码不一致');
        return;
    }

    if (currentPassword === newPassword) {
        alert('新密码不能与当前密码相同');
        return;
    }

    const data = {
        current_password: currentPassword,
        new_password: newPassword,
        confirm_password: confirmPassword
    };

    fetch('{{ route("front_api_profile_change_password") }}', {
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
            alert('密码修改成功，请重新登录');
            window.location.href = '{{ route("front_coreui_v2_page_login") }}';
        } else {
            alert(data.message || '修改失败');
        }
    })
    .catch(err => {
        console.error('Change password error:', err);
        alert('网络错误，请稍后重试');
    });
}
</script>
@endsection
