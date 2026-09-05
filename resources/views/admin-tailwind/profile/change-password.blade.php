@extends('admin-tailwind.layouts.app')

@section('title', '修改密码 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">修改密码</h1>
        <p class="text-slate-600 mt-2">定期修改密码以保护账户安全</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Security Tips -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-bold text-slate-800 mb-4">
                <i class="fas fa-shield-alt text-blue-600 mr-2"></i>安全提示
            </h3>
            <div class="space-y-3">
                <div class="flex items-start gap-2">
                    <i class="fas fa-check-circle text-green-500 mt-1"></i>
                    <p class="text-sm text-slate-600">密码长度至少8位</p>
                </div>
                <div class="flex items-start gap-2">
                    <i class="fas fa-check-circle text-green-500 mt-1"></i>
                    <p class="text-sm text-slate-600">包含大小写字母、数字和特殊字符</p>
                </div>
                <div class="flex items-start gap-2">
                    <i class="fas fa-check-circle text-green-500 mt-1"></i>
                    <p class="text-sm text-slate-600">不要使用常见密码或个人信息</p>
                </div>
                <div class="flex items-start gap-2">
                    <i class="fas fa-check-circle text-green-500 mt-1"></i>
                    <p class="text-sm text-slate-600">建议每3个月更换一次密码</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6 mt-6">
            <h3 class="text-lg font-bold text-slate-800 mb-4">
                <i class="fas fa-history text-indigo-600 mr-2"></i>修改记录
            </h3>
            <div id="passwordHistory" class="space-y-3">
                <div class="flex items-center justify-center py-8 text-slate-400">
                    <i class="fas fa-spinner fa-spin mr-2"></i>加载中...
                </div>
            </div>
        </div>
    </div>

    <!-- Password Form -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-bold text-slate-800 mb-6">修改密码</h3>
            <form id="passwordForm" class="space-y-6">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">
                        当前密码 <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="password" id="currentPassword" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="请输入当前密码">
                        <button type="button" onclick="togglePassword('currentPassword')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">
                        新密码 <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="password" id="newPassword" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="请输入新密码" oninput="checkPasswordStrength()">
                        <button type="button" onclick="togglePassword('newPassword')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="mt-2">
                        <div class="flex items-center gap-1 mb-1">
                            <div id="strength1" class="h-1 flex-1 bg-slate-200 rounded"></div>
                            <div id="strength2" class="h-1 flex-1 bg-slate-200 rounded"></div>
                            <div id="strength3" class="h-1 flex-1 bg-slate-200 rounded"></div>
                            <div id="strength4" class="h-1 flex-1 bg-slate-200 rounded"></div>
                        </div>
                        <p id="strengthText" class="text-xs text-slate-500">密码强度</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">
                        确认新密码 <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="password" id="confirmPassword" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="请再次输入新密码">
                        <button type="button" onclick="togglePassword('confirmPassword')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                    <div class="flex items-start gap-2">
                        <i class="fas fa-exclamation-triangle text-amber-600 mt-1"></i>
                        <div class="text-sm text-amber-800">
                            <p class="font-semibold mb-1">重要提示</p>
                            <p>修改密码后，您将被自动退出登录，需要使用新密码重新登录。</p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" onclick="resetPasswordForm()" class="px-6 py-2 bg-slate-200 text-slate-700 font-semibold rounded-lg hover:bg-slate-300 transition">
                        重置
                    </button>
                    <button type="button" onclick="changePassword()" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                        <i class="fas fa-key mr-2"></i>修改密码
                    </button>
                </div>
            </form>
        </div>

        <!-- Two-Factor Authentication -->
        <div class="bg-white rounded-xl shadow-lg p-6 mt-6">
            <h3 class="text-lg font-bold text-slate-800 mb-6">
                <i class="fas fa-mobile-alt text-green-600 mr-2"></i>双因素认证
            </h3>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-slate-800">启用双因素认证</p>
                    <p class="text-xs text-slate-500 mt-1">通过手机验证码增强账户安全性</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="twoFactorAuth" class="sr-only peer" onchange="toggleTwoFactor(this.checked)">
                    <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                </label>
            </div>
            <div id="twoFactorSetup" class="mt-4 hidden">
                <div class="bg-slate-50 rounded-lg p-4">
                    <p class="text-sm text-slate-600 mb-3">绑定手机号以接收验证码</p>
                    <div class="flex gap-2">
                        <input type="tel" id="phoneNumber" class="flex-1 px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="请输入手机号">
                        <button type="button" onclick="sendVerifyCode()" class="px-6 py-2 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                            发送验证码
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadPasswordHistory();
    loadTwoFactorStatus();
});

function loadPasswordHistory() {
    fetch('{{ route("admin_api_password_history") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.history && data.history.length > 0) {
            const html = data.history.slice(0, 5).map(h => `
                <div class="py-2 border-b border-slate-100 last:border-0">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-600">${h.created_at}</span>
                        <span class="text-xs px-2 py-0.5 bg-green-100 text-green-700 rounded-full">成功</span>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">IP: ${h.ip || '-'}</p>
                </div>
            `).join('');
            document.getElementById('passwordHistory').innerHTML = html;
        } else {
            document.getElementById('passwordHistory').innerHTML = `
                <div class="flex items-center justify-center py-8 text-slate-400">
                    <i class="fas fa-inbox mr-2"></i>暂无修改记录
                </div>
            `;
        }
    })
    .catch(err => {
        console.error('Load history error:', err);
        document.getElementById('passwordHistory').innerHTML = `
            <div class="flex items-center justify-center py-8 text-slate-400">
                <i class="fas fa-inbox mr-2"></i>暂无修改记录
            </div>
        `;
    });
}

function loadTwoFactorStatus() {
    fetch('{{ route("admin_api_two_factor_status") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('twoFactorAuth').checked = data.enabled || false;
        }
    })
    .catch(err => console.error('Load 2FA status error:', err));
}

function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const btn = field.nextElementSibling.querySelector('i');
    if (field.type === 'password') {
        field.type = 'text';
        btn.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        field.type = 'password';
        btn.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

function checkPasswordStrength() {
    const password = document.getElementById('newPassword').value;
    const bars = [
        document.getElementById('strength1'),
        document.getElementById('strength2'),
        document.getElementById('strength3'),
        document.getElementById('strength4')
    ];
    const text = document.getElementById('strengthText');

    bars.forEach(bar => {
        bar.className = 'h-1 flex-1 bg-slate-200 rounded';
    });

    if (password.length === 0) {
        text.textContent = '密码强度';
        text.className = 'text-xs text-slate-500';
        return;
    }

    let strength = 0;
    if (password.length >= 8) strength++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
    if (/\d/.test(password)) strength++;
    if (/[^a-zA-Z0-9]/.test(password)) strength++;

    const colors = ['bg-red-500', 'bg-orange-500', 'bg-yellow-500', 'bg-green-500'];
    const labels = ['弱', '中等', '较强', '强'];
    const textColors = ['text-red-600', 'text-orange-600', 'text-yellow-600', 'text-green-600'];

    for (let i = 0; i < strength; i++) {
        bars[i].className = `h-1 flex-1 ${colors[strength - 1]} rounded`;
    }

    text.textContent = `密码强度: ${labels[strength - 1]}`;
    text.className = `text-xs ${textColors[strength - 1]}`;
}

function changePassword() {
    const currentPassword = document.getElementById('currentPassword').value.trim();
    const newPassword = document.getElementById('newPassword').value.trim();
    const confirmPassword = document.getElementById('confirmPassword').value.trim();

    if (!currentPassword) {
        alert('请输入当前密码');
        return;
    }

    if (!newPassword) {
        alert('请输入新密码');
        return;
    }

    if (newPassword.length < 8) {
        alert('新密码长度至少8位');
        return;
    }

    if (newPassword !== confirmPassword) {
        alert('两次输入的新密码不一致');
        return;
    }

    if (newPassword === currentPassword) {
        alert('新密码不能与当前密码相同');
        return;
    }

    if (!confirm('确定要修改密码吗？修改后将自动退出登录。')) {
        return;
    }

    fetch('{{ route("admin_api_change_password") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            current_password: currentPassword,
            new_password: newPassword,
            confirm_password: confirmPassword
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('密码修改成功，请重新登录');
            setTimeout(() => {
                window.location.href = '{{ route("admin_login") }}';
            }, 1000);
        } else {
            alert(data.message || '修改失败');
        }
    })
    .catch(err => {
        console.error('Change password error:', err);
        alert('网络错误，请稍后重试');
    });
}

function toggleTwoFactor(enabled) {
    if (enabled) {
        document.getElementById('twoFactorSetup').classList.remove('hidden');
    } else {
        if (confirm('确定要关闭双因素认证吗？这可能降低账户安全性。')) {
            fetch('{{ route("admin_api_disable_two_factor") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success || data.code === 200) {
                    alert('双因素认证已关闭');
                    document.getElementById('twoFactorSetup').classList.add('hidden');
                } else {
                    alert(data.message || '操作失败');
                    document.getElementById('twoFactorAuth').checked = true;
                }
            })
            .catch(err => {
                console.error('Disable 2FA error:', err);
                alert('网络错误，请稍后重试');
                document.getElementById('twoFactorAuth').checked = true;
            });
        } else {
            document.getElementById('twoFactorAuth').checked = true;
        }
    }
}

function sendVerifyCode() {
    const phone = document.getElementById('phoneNumber').value.trim();

    if (!phone) {
        alert('请输入手机号');
        return;
    }

    if (!/^1[3-9]\d{9}$/.test(phone)) {
        alert('请输入正确的手机号');
        return;
    }

    fetch('{{ route("admin_api_send_two_factor_code") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ phone: phone })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('验证码已发送');
        } else {
            alert(data.message || '发送失败');
        }
    })
    .catch(err => {
        console.error('Send code error:', err);
        alert('网络错误，请稍后重试');
    });
}

function resetPasswordForm() {
    document.getElementById('passwordForm').reset();
    const bars = [
        document.getElementById('strength1'),
        document.getElementById('strength2'),
        document.getElementById('strength3'),
        document.getElementById('strength4')
    ];
    bars.forEach(bar => {
        bar.className = 'h-1 flex-1 bg-slate-200 rounded';
    });
    document.getElementById('strengthText').textContent = '密码强度';
    document.getElementById('strengthText').className = 'text-xs text-slate-500';
}
</script>
@endsection
