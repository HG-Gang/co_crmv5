@extends('front-coreui-v2.layouts.app')

@section('title', '编辑资料')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('front_coreui_v2_page_profile') }}">个人资料</a></li>
                    <li class="breadcrumb-item active">编辑资料</li>
                </ol>
            </nav>
            <h2 class="mb-2">编辑资料</h2>
            <p class="text-body-secondary mb-0">更新您的个人信息</p>
        </div>
    </div>

    <div class="row">
        <!-- Avatar Section -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-body text-center p-4">
                    <h6 class="card-title mb-3">头像</h6>
                    <div class="avatar avatar-xl mb-3 mx-auto" style="width: 120px; height: 120px;">
                        <div class="avatar-initial rounded-circle bg-gradient-primary text-white" style="font-size: 3rem;" id="avatarPreview">
                            U
                        </div>
                    </div>
                    <input type="file" id="avatarFile" accept="image/*" class="d-none">
                    <button onclick="document.getElementById('avatarFile').click()" class="btn btn-outline-primary btn-sm mb-2">
                        <i class="cil-cloud-upload me-2"></i>上传头像
                    </button>
                    <p class="text-body-secondary small mb-0">支持 JPG、PNG 格式，最大 2MB</p>
                </div>
            </div>

            <div class="card shadow-sm border-0 mt-4">
                <div class="card-body">
                    <div class="alert alert-info border-0 mb-0">
                        <i class="cil-info me-2"></i>
                        <strong>提示：</strong>
                        <ul class="mb-0 mt-2 small">
                            <li>用户名和邮箱一旦设置不可修改</li>
                            <li>真实姓名需与身份证件一致</li>
                            <li>完善资料有助于提升账户安全</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Form -->
        <div class="col-lg-8">
            <form id="editForm">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-transparent border-bottom">
                        <h5 class="mb-0">
                            <i class="cil-user me-2"></i>基本信息
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">用户名 <span class="text-danger">*</span></label>
                                <input type="text" id="username" class="form-control" disabled>
                                <div class="form-text">用户名不可修改</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">邮箱地址 <span class="text-danger">*</span></label>
                                <input type="email" id="email" class="form-control" disabled>
                                <div class="form-text">如需修改请前往<a href="{{ route('front_coreui_v2_page_profile_change_email') }}">修改邮箱</a></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">真实姓名</label>
                                <input type="text" id="realName" class="form-control" placeholder="请输入真实姓名">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">手机号码</label>
                                <input type="text" id="phone" class="form-control" placeholder="请输入手机号码">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">国家/地区</label>
                                <select id="country" class="form-select">
                                    <option value="">请选择</option>
                                    <option value="CN">中国</option>
                                    <option value="HK">中国香港</option>
                                    <option value="TW">中国台湾</option>
                                    <option value="US">美国</option>
                                    <option value="UK">英国</option>
                                    <option value="JP">日本</option>
                                    <option value="SG">新加坡</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">出生日期</label>
                                <input type="date" id="birthday" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label">详细地址</label>
                                <input type="text" id="address" class="form-control" placeholder="请输入详细地址">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-transparent border-bottom">
                        <h5 class="mb-0">
                            <i class="cil-contact me-2"></i>联系方式
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">微信号</label>
                                <input type="text" id="wechat" class="form-control" placeholder="请输入微信号">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">QQ号</label>
                                <input type="text" id="qq" class="form-control" placeholder="请输入QQ号">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Telegram</label>
                                <input type="text" id="telegram" class="form-control" placeholder="请输入Telegram">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">WhatsApp</label>
                                <input type="text" id="whatsapp" class="form-control" placeholder="请输入WhatsApp">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-transparent border-bottom">
                        <h5 class="mb-0">
                            <i class="cil-settings me-2"></i>偏好设置
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">语言</label>
                                <select id="language" class="form-select">
                                    <option value="zh-CN">简体中文</option>
                                    <option value="zh-TW">繁体中文</option>
                                    <option value="en">English</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">时区</label>
                                <select id="timezone" class="form-select">
                                    <option value="Asia/Shanghai">中国标准时间 (GMT+8)</option>
                                    <option value="Asia/Hong_Kong">香港时间 (GMT+8)</option>
                                    <option value="Asia/Tokyo">日本标准时间 (GMT+9)</option>
                                    <option value="America/New_York">美国东部时间 (GMT-5)</option>
                                    <option value="Europe/London">英国时间 (GMT+0)</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="emailNotification" checked>
                                    <label class="form-check-label" for="emailNotification">
                                        接收邮件通知
                                    </label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="smsNotification">
                                    <label class="form-check-label" for="smsNotification">
                                        接收短信通知
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary-gradient px-4">
                        <i class="cil-save me-2"></i>保存修改
                    </button>
                    <a href="{{ route('front_coreui_v2_page_profile') }}" class="btn btn-outline-secondary px-4">
                        <i class="cil-x me-2"></i>取消
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadProfileData();

    document.getElementById('editForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveProfile();
    });

    document.getElementById('avatarFile').addEventListener('change', function(e) {
        if (e.target.files && e.target.files[0]) {
            uploadAvatar(e.target.files[0]);
        }
    });
});

function loadProfileData() {
    fetch('{{ route("front_api_profile_detail") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.profile) {
            const p = data.profile;

            if (p.username) {
                document.getElementById('avatarPreview').textContent = p.username.charAt(0).toUpperCase();
            }

            document.getElementById('username').value = p.username || '';
            document.getElementById('email').value = p.email || '';
            document.getElementById('realName').value = p.real_name || '';
            document.getElementById('phone').value = p.phone || '';
            document.getElementById('country').value = p.country || '';
            document.getElementById('birthday').value = p.birthday || '';
            document.getElementById('address').value = p.address || '';

            document.getElementById('wechat').value = p.wechat || '';
            document.getElementById('qq').value = p.qq || '';
            document.getElementById('telegram').value = p.telegram || '';
            document.getElementById('whatsapp').value = p.whatsapp || '';

            document.getElementById('language').value = p.language || 'zh-CN';
            document.getElementById('timezone').value = p.timezone || 'Asia/Shanghai';
            document.getElementById('emailNotification').checked = p.email_notification !== false;
            document.getElementById('smsNotification').checked = p.sms_notification === true;
        }
    })
    .catch(err => {
        console.error('Load profile error:', err);
    });
}

function saveProfile() {
    const data = {
        real_name: document.getElementById('realName').value.trim(),
        phone: document.getElementById('phone').value.trim(),
        country: document.getElementById('country').value,
        birthday: document.getElementById('birthday').value,
        address: document.getElementById('address').value.trim(),
        wechat: document.getElementById('wechat').value.trim(),
        qq: document.getElementById('qq').value.trim(),
        telegram: document.getElementById('telegram').value.trim(),
        whatsapp: document.getElementById('whatsapp').value.trim(),
        language: document.getElementById('language').value,
        timezone: document.getElementById('timezone').value,
        email_notification: document.getElementById('emailNotification').checked,
        sms_notification: document.getElementById('smsNotification').checked
    };

    fetch('{{ route("front_api_profile_update") }}', {
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
            alert('保存成功');
            window.location.href = '{{ route("front_coreui_v2_page_profile") }}';
        } else {
            alert(data.message || '保存失败');
        }
    })
    .catch(err => {
        console.error('Save profile error:', err);
        alert('网络错误，请稍后重试');
    });
}

function uploadAvatar(file) {
    if (file.size > 2 * 1024 * 1024) {
        alert('文件大小不能超过2MB');
        return;
    }

    const formData = new FormData();
    formData.append('avatar', file);

    fetch('{{ route("front_api_profile_upload_avatar") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('头像上传成功');
            if (data.avatar_url) {
                const preview = document.getElementById('avatarPreview');
                preview.style.backgroundImage = `url(${data.avatar_url})`;
                preview.style.backgroundSize = 'cover';
                preview.textContent = '';
            }
        } else {
            alert(data.message || '上传失败');
        }
    })
    .catch(err => {
        console.error('Upload avatar error:', err);
        alert('网络错误，请稍后重试');
    });
}
</script>
@endsection
