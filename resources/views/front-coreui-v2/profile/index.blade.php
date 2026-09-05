@extends('front-coreui-v2.layouts.app')

@section('title', '个人资料')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-2">个人资料</h2>
                    <p class="text-body-secondary mb-0">查看和管理您的个人信息</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Profile Card -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-body text-center p-4">
                    <div class="avatar avatar-xl mb-3 mx-auto" style="width: 100px; height: 100px;">
                        <div class="avatar-initial rounded-circle bg-gradient-primary text-white fs-1" id="avatarPreview">
                            U
                        </div>
                    </div>
                    <h4 class="mb-1" id="profileUsername">-</h4>
                    <p class="text-body-secondary mb-3" id="profileEmail">-</p>
                    <div class="d-flex justify-content-center gap-2 mb-3">
                        <span class="badge bg-primary-gradient rounded-pill" id="profileType">普通用户</span>
                        <span class="badge bg-success-gradient rounded-pill" id="profileStatus">正常</span>
                    </div>
                    <div class="border-top pt-3 mt-3">
                        <div class="row text-center">
                            <div class="col-6 border-end">
                                <h5 class="mb-1" id="accountBalance">$0.00</h5>
                                <small class="text-body-secondary">账户余额</small>
                            </div>
                            <div class="col-6">
                                <h5 class="mb-1" id="joinDays">0</h5>
                                <small class="text-body-secondary">加入天数</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 mt-4">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class="cil-shield-alt me-2"></i>安全设置
                    </h6>
                    <div class="list-group list-group-flush">
                        <a href="{{ route('front_coreui_v2_page_profile_change_password') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <span><i class="cil-lock-locked me-2"></i>修改密码</span>
                            <i class="cil-chevron-right"></i>
                        </a>
                        <a href="{{ route('front_coreui_v2_page_profile_change_email') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <span><i class="cil-envelope-closed me-2"></i>修改邮箱</span>
                            <i class="cil-chevron-right"></i>
                        </a>
                        <a href="{{ route('front_coreui_v2_page_account_cancel') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center text-danger">
                            <span><i class="cil-account-logout me-2"></i>注销账户</span>
                            <i class="cil-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Details -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0">
                        <i class="cil-user me-2"></i>基本信息
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-body-secondary small">用户名</label>
                            <p class="mb-0 fw-semibold" id="detailUsername">-</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-body-secondary small">真实姓名</label>
                            <p class="mb-0 fw-semibold" id="detailRealName">-</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-body-secondary small">邮箱地址</label>
                            <p class="mb-0 fw-semibold" id="detailEmail">-</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-body-secondary small">手机号码</label>
                            <p class="mb-0 fw-semibold" id="detailPhone">-</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-body-secondary small">国家/地区</label>
                            <p class="mb-0 fw-semibold" id="detailCountry">-</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-body-secondary small">注册时间</label>
                            <p class="mb-0 fw-semibold" id="detailCreatedAt">-</p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="{{ route('front_coreui_v2_page_profile_edit') }}" class="btn btn-primary-gradient">
                            <i class="cil-pencil me-2"></i>编辑资料
                        </a>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0">
                        <i class="cil-chart-line me-2"></i>账户统计
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-3 col-6">
                            <div class="text-center">
                                <div class="h2 mb-1 text-primary" id="statDeposits">0</div>
                                <small class="text-body-secondary">入金次数</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="text-center">
                                <div class="h2 mb-1 text-success" id="statWithdraws">0</div>
                                <small class="text-body-secondary">出金次数</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="text-center">
                                <div class="h2 mb-1 text-info" id="statTrades">0</div>
                                <small class="text-body-secondary">交易笔数</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="text-center">
                                <div class="h2 mb-1 text-warning" id="statMt4Accounts">0</div>
                                <small class="text-body-secondary">MT4账户</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0">
                        <i class="cil-history me-2"></i>最近活动
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" id="recentActivities">
                        <div class="list-group-item text-center py-4 text-body-secondary">
                            <i class="cil-reload spinner-border spinner-border-sm me-2"></i>加载中...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadProfileData();
});

function loadProfileData() {
    fetch('{{ route("front_api_profile_detail") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.profile) {
            const p = data.profile;

            // Profile Card
            if (p.username) {
                document.getElementById('avatarPreview').textContent = p.username.charAt(0).toUpperCase();
            }
            document.getElementById('profileUsername').textContent = p.username || '-';
            document.getElementById('profileEmail').textContent = p.email || '-';
            document.getElementById('profileType').textContent = getUserTypeText(p.user_type);
            document.getElementById('profileStatus').textContent = getStatusText(p.status);
            document.getElementById('accountBalance').textContent = '$' + (p.balance || 0);
            document.getElementById('joinDays').textContent = p.join_days || 0;

            // Details
            document.getElementById('detailUsername').textContent = p.username || '-';
            document.getElementById('detailRealName').textContent = p.real_name || '-';
            document.getElementById('detailEmail').textContent = p.email || '-';
            document.getElementById('detailPhone').textContent = p.phone || '-';
            document.getElementById('detailCountry').textContent = p.country || '-';
            document.getElementById('detailCreatedAt').textContent = p.created_at || '-';

            // Stats
            document.getElementById('statDeposits').textContent = p.stats?.deposits || 0;
            document.getElementById('statWithdraws').textContent = p.stats?.withdraws || 0;
            document.getElementById('statTrades').textContent = p.stats?.trades || 0;
            document.getElementById('statMt4Accounts').textContent = p.stats?.mt4_accounts || 0;

            // Activities
            if (p.activities && p.activities.length > 0) {
                renderActivities(p.activities);
            } else {
                document.getElementById('recentActivities').innerHTML = `
                    <div class="list-group-item text-center py-4 text-body-secondary">
                        <i class="cil-inbox me-2"></i>暂无活动记录
                    </div>
                `;
            }
        }
    })
    .catch(err => {
        console.error('Load profile error:', err);
    });
}

function renderActivities(activities) {
    const html = activities.map(a => `
        <div class="list-group-item">
            <div class="d-flex align-items-start">
                <div class="flex-shrink-0 me-3">
                    <div class="avatar avatar-sm">
                        <div class="avatar-initial rounded-circle ${getActivityColor(a.type)}">
                            <i class="${getActivityIcon(a.type)}"></i>
                        </div>
                    </div>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1">${a.title}</h6>
                            <p class="mb-0 text-body-secondary small">${a.description}</p>
                        </div>
                        <small class="text-body-secondary">${a.time}</small>
                    </div>
                </div>
            </div>
        </div>
    `).join('');

    document.getElementById('recentActivities').innerHTML = html;
}

function getUserTypeText(type) {
    const types = {
        'user': '普通用户',
        'agent': '代理',
        'big_agent': '大代理'
    };
    return types[type] || '普通用户';
}

function getStatusText(status) {
    const statuses = {
        'active': '正常',
        'frozen': '冻结'
    };
    return statuses[status] || '未知';
}

function getActivityIcon(type) {
    const icons = {
        'login': 'cil-account-logout',
        'deposit': 'cil-arrow-thick-to-bottom',
        'withdraw': 'cil-arrow-thick-from-bottom',
        'trade': 'cil-chart-line',
        'profile': 'cil-user'
    };
    return icons[type] || 'cil-info';
}

function getActivityColor(type) {
    const colors = {
        'login': 'bg-info',
        'deposit': 'bg-success',
        'withdraw': 'bg-warning',
        'trade': 'bg-primary',
        'profile': 'bg-secondary'
    };
    return colors[type] || 'bg-secondary';
}
</script>
@endsection
