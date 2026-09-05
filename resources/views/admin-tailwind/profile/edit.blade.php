@extends('admin-tailwind.layouts.app')

@section('title', '编辑资料 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">编辑资料</h1>
        <p class="text-slate-600 mt-2">修改个人信息和账户设置</p>
    </div>
</div>

<!-- Profile Form -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Avatar Section -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-bold text-slate-800 mb-4">头像设置</h3>
            <div class="flex flex-col items-center">
                <div class="w-32 h-32 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-4xl font-bold mb-4">
                    <span id="avatarPreview">A</span>
                </div>
                <input type="file" id="avatarInput" accept="image/*" class="hidden" onchange="previewAvatar(this)">
                <button onclick="document.getElementById('avatarInput').click()" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                    <i class="fas fa-camera mr-2"></i>更换头像
                </button>
                <p class="text-xs text-slate-500 mt-2">支持JPG、PNG格式，不超过2MB</p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg p-6 mt-6">
            <h3 class="text-lg font-bold text-slate-800 mb-4">账户信息</h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="text-sm text-slate-600">用户ID</span>
                    <span class="text-sm font-semibold text-slate-800" id="userId">-</span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="text-sm text-slate-600">角色</span>
                    <span class="text-sm font-semibold text-slate-800" id="userRole">-</span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-slate-100">
                    <span class="text-sm text-slate-600">注册时间</span>
                    <span class="text-sm text-slate-600" id="registerTime">-</span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-slate-600">最后登录</span>
                    <span class="text-sm text-slate-600" id="lastLogin">-</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Profile Info Section -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-bold text-slate-800 mb-6">基本信息</h3>
            <form id="profileForm" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">用户名 <span class="text-red-500">*</span></label>
                        <input type="text" id="username" readonly class="w-full px-4 py-2 border border-slate-300 rounded-lg bg-slate-50 text-slate-500 cursor-not-allowed">
                        <p class="text-xs text-slate-500 mt-1">用户名不可修改</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">邮箱 <span class="text-red-500">*</span></label>
                        <input type="email" id="email" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">真实姓名</label>
                        <input type="text" id="realName" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">手机号</label>
                        <input type="tel" id="phone" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">部门</label>
                        <input type="text" id="department" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">职位</label>
                        <input type="text" id="position" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">个人简介</label>
                    <textarea id="bio" rows="4" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="请输入个人简介..."></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" onclick="resetForm()" class="px-6 py-2 bg-slate-200 text-slate-700 font-semibold rounded-lg hover:bg-slate-300 transition">
                        重置
                    </button>
                    <button type="button" onclick="saveProfile()" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                        <i class="fas fa-save mr-2"></i>保存修改
                    </button>
                </div>
            </form>
        </div>

        <!-- Preferences Section -->
        <div class="bg-white rounded-xl shadow-lg p-6 mt-6">
            <h3 class="text-lg font-bold text-slate-800 mb-6">偏好设置</h3>
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">邮件通知</p>
                        <p class="text-xs text-slate-500">接收系统邮件通知</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="emailNotification" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">短信通知</p>
                        <p class="text-xs text-slate-500">接收重要操作短信提醒</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="smsNotification" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">双因素认证</p>
                        <p class="text-xs text-slate-500">增强账户安全性</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="twoFactorAuth" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">语言偏好</label>
                    <select id="language" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="zh-CN">简体中文</option>
                        <option value="zh-TW">繁体中文</option>
                        <option value="en-US">English</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">时区</label>
                    <select id="timezone" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="Asia/Shanghai">北京时间 (UTC+8)</option>
                        <option value="Asia/Hong_Kong">香港时间 (UTC+8)</option>
                        <option value="Asia/Tokyo">东京时间 (UTC+9)</option>
                        <option value="America/New_York">纽约时间 (UTC-5)</option>
                        <option value="Europe/London">伦敦时间 (UTC+0)</option>
                    </select>
                </div>

                <div class="flex justify-end pt-4">
                    <button type="button" onclick="savePreferences()" class="px-6 py-2 bg-gradient-to-r from-purple-500 to-pink-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                        <i class="fas fa-save mr-2"></i>保存设置
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadProfile();
});

function loadProfile() {
    fetch('{{ route("admin_api_profile_info") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.profile) {
            const p = data.profile;

            // Basic info
            document.getElementById('userId').textContent = p.id || '-';
            document.getElementById('userRole').textContent = p.role_name || '-';
            document.getElementById('registerTime').textContent = p.created_at || '-';
            document.getElementById('lastLogin').textContent = p.last_login || '-';

            // Avatar
            if (p.username) {
                document.getElementById('avatarPreview').textContent = p.username.charAt(0).toUpperCase();
            }

            // Form fields
            document.getElementById('username').value = p.username || '';
            document.getElementById('email').value = p.email || '';
            document.getElementById('realName').value = p.real_name || '';
            document.getElementById('phone').value = p.phone || '';
            document.getElementById('department').value = p.department || '';
            document.getElementById('position').value = p.position || '';
            document.getElementById('bio').value = p.bio || '';

            // Preferences
            document.getElementById('emailNotification').checked = p.email_notification || false;
            document.getElementById('smsNotification').checked = p.sms_notification || false;
            document.getElementById('twoFactorAuth').checked = p.two_factor_auth || false;
            document.getElementById('language').value = p.language || 'zh-CN';
            document.getElementById('timezone').value = p.timezone || 'Asia/Shanghai';
        }
    })
    .catch(err => console.error('Load profile error:', err));
}

function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];

        if (file.size > 2 * 1024 * 1024) {
            alert('图片大小不能超过2MB');
            return;
        }

        const formData = new FormData();
        formData.append('avatar', file);

        fetch('{{ route("admin_api_profile_upload_avatar") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('头像上传成功');
                loadProfile();
            } else {
                alert(data.message || '上传失败');
            }
        })
        .catch(err => {
            console.error('Upload error:', err);
            alert('上传失败，请稍后重试');
        });
    }
}

function saveProfile() {
    const email = document.getElementById('email').value.trim();
    const realName = document.getElementById('realName').value.trim();
    const phone = document.getElementById('phone').value.trim();
    const department = document.getElementById('department').value.trim();
    const position = document.getElementById('position').value.trim();
    const bio = document.getElementById('bio').value.trim();

    if (!email) {
        alert('请输入邮箱');
        return;
    }

    fetch('{{ route("admin_api_profile_update") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            email: email,
            real_name: realName,
            phone: phone,
            department: department,
            position: position,
            bio: bio
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('保存成功');
            loadProfile();
        } else {
            alert(data.message || '保存失败');
        }
    })
    .catch(err => {
        console.error('Save error:', err);
        alert('网络错误，请稍后重试');
    });
}

function savePreferences() {
    const emailNotification = document.getElementById('emailNotification').checked;
    const smsNotification = document.getElementById('smsNotification').checked;
    const twoFactorAuth = document.getElementById('twoFactorAuth').checked;
    const language = document.getElementById('language').value;
    const timezone = document.getElementById('timezone').value;

    fetch('{{ route("admin_api_profile_update_preferences") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            email_notification: emailNotification,
            sms_notification: smsNotification,
            two_factor_auth: twoFactorAuth,
            language: language,
            timezone: timezone
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('设置已保存');
        } else {
            alert(data.message || '保存失败');
        }
    })
    .catch(err => {
        console.error('Save error:', err);
        alert('网络错误，请稍后重试');
    });
}

function resetForm() {
    loadProfile();
}
</script>
@endsection
