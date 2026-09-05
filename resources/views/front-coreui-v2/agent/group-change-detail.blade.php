@extends('front-coreui-v2.layouts.app')

@section('title', '组变更详情')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('front_coreui_v2_page_agent_group_change') }}">组变更申请</a></li>
                    <li class="breadcrumb-item active">申请详情</li>
                </ol>
            </nav>
            <h2 class="mb-2">组变更申请详情</h2>
            <p class="text-body-secondary mb-0">查看组变更申请的详细信息和审核状态</p>
        </div>
    </div>

    <div class="row">
        <!-- Left Column -->
        <div class="col-lg-8">
            <!-- Application Info -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-gradient-primary text-white">
                    <h5 class="mb-0">
                        <i class="cil-file me-2"></i>申请信息
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="border-start border-primary border-4 ps-3">
                                <label class="text-body-secondary small mb-1">申请编号</label>
                                <h5 class="mb-0" id="applicationId">-</h5>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border-start border-secondary border-4 ps-3">
                                <label class="text-body-secondary small mb-1">申请时间</label>
                                <h5 class="mb-0" id="createdAt">-</h5>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border-start border-info border-4 ps-3">
                                <label class="text-body-secondary small mb-1">当前状态</label>
                                <h5 class="mb-0" id="status">-</h5>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border-start border-warning border-4 ps-3">
                                <label class="text-body-secondary small mb-1">处理时间</label>
                                <h5 class="mb-0" id="processedAt">-</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer Info -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0">
                        <i class="cil-user me-2"></i>客户信息
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <span class="text-body-secondary">客户姓名</span>
                                <strong id="customerName">-</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <span class="text-body-secondary">联系邮箱</span>
                                <strong id="customerEmail">-</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <span class="text-body-secondary">MT4账号</span>
                                <strong id="mt4Account">-</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                                <span class="text-body-secondary">账户余额</span>
                                <strong class="text-success" id="balance">$0.00</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Group Change Details -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0">
                        <i class="cil-swap-horizontal me-2"></i>变更详情
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-5">
                            <div class="text-center p-4 border rounded">
                                <p class="text-body-secondary mb-2 small">当前组别</p>
                                <h3 class="mb-0 fw-bold" id="currentGroup">-</h3>
                                <p class="text-body-secondary small mt-2" id="currentGroupDesc">-</p>
                            </div>
                        </div>
                        <div class="col-md-2 d-flex align-items-center justify-content-center">
                            <i class="cil-arrow-right text-primary" style="font-size: 2rem;"></i>
                        </div>
                        <div class="col-md-5">
                            <div class="text-center p-4 border rounded border-primary bg-primary bg-opacity-10">
                                <p class="text-body-secondary mb-2 small">目标组别</p>
                                <h3 class="mb-0 fw-bold text-primary" id="targetGroup">-</h3>
                                <p class="text-body-secondary small mt-2" id="targetGroupDesc">-</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="form-label fw-semibold">变更原因</label>
                        <div class="p-3 bg-light rounded" id="reason">-</div>
                    </div>
                </div>
            </div>

            <!-- Approval Info -->
            <div class="card shadow-sm border-0" id="approvalCard" style="display: none;">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0">
                        <i class="cil-info me-2"></i>审核信息
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                <span class="text-body-secondary">审核人</span>
                                <strong id="approver">-</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                <span class="text-body-secondary">审核时间</span>
                                <strong id="approvedAt">-</strong>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label fw-semibold">审核备注</label>
                        <div class="p-3 border rounded" id="approvalNote">-</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-lg-4">
            <!-- Status Card -->
            <div class="card shadow-sm border-0 mb-4 text-white" id="statusCard">
                <div class="card-body text-center py-4">
                    <div class="mb-3">
                        <i class="cil-clock" id="statusIcon" style="font-size: 3rem;"></i>
                    </div>
                    <h3 class="mb-2 fw-bold" id="statusText">待审核</h3>
                    <p class="mb-0 opacity-75 small" id="statusDesc">申请已提交，等待审核</p>
                </div>
            </div>

            <!-- Timeline -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">
                        <i class="cil-list me-2"></i>处理时间线
                    </h6>
                </div>
                <div class="card-body">
                    <div class="timeline" id="timeline">
                        <div class="spinner-border spinner-border-sm"></div>
                    </div>
                </div>
            </div>

            <!-- Group Comparison -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">
                        <i class="cil-compare me-2"></i>组别对比
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-body-secondary small">杠杆比例</span>
                            <div>
                                <span class="text-body-secondary" id="currentLeverage">-</span>
                                <i class="cil-arrow-right mx-2"></i>
                                <strong class="text-primary" id="targetLeverage">-</strong>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-body-secondary small">点差类型</span>
                            <div>
                                <span class="text-body-secondary" id="currentSpread">-</span>
                                <i class="cil-arrow-right mx-2"></i>
                                <strong class="text-primary" id="targetSpread">-</strong>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-body-secondary small">佣金设置</span>
                            <div>
                                <span class="text-body-secondary" id="currentCommission">-</span>
                                <i class="cil-arrow-right mx-2"></i>
                                <strong class="text-primary" id="targetCommission">-</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">
                        <i class="cil-settings me-2"></i>操作
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary" onclick="viewCustomer()">
                            <i class="cil-user me-2"></i>查看客户
                        </button>
                        <button class="btn btn-outline-secondary" onclick="goBack()">
                            <i class="cil-arrow-left me-2"></i>返回列表
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let applicationId = '';
let customerId = '';

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    applicationId = urlParams.get('id');

    if (applicationId) {
        loadApplicationDetail();
    } else {
        alert('申请ID不能为空');
        window.location.href = '{{ route("front_coreui_v2_page_agent_group_change") }}';
    }
});

function loadApplicationDetail() {
    fetch(`{{ route("front_api_agent_group_change_detail") }}?id=${applicationId}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.application) {
            renderApplicationDetail(data.application);
        } else {
            alert(data.message || '申请不存在');
            window.location.href = '{{ route("front_coreui_v2_page_agent_group_change") }}';
        }
    })
    .catch(err => {
        console.error('Load application detail error:', err);
    });
}

function renderApplicationDetail(app) {
    customerId = app.customer_id;

    // Application Info
    document.getElementById('applicationId').textContent = '#' + (app.id || '-');
    document.getElementById('createdAt').textContent = app.created_at || '-';
    document.getElementById('status').innerHTML = getStatusBadge(app.status);
    document.getElementById('processedAt').textContent = app.processed_at || '未处理';

    // Customer Info
    document.getElementById('customerName').textContent = app.customer_name || '-';
    document.getElementById('customerEmail').textContent = app.customer_email || '-';
    document.getElementById('mt4Account').textContent = app.mt4_account || '-';
    document.getElementById('balance').textContent = formatCurrency(app.balance);

    // Group Change Details
    document.getElementById('currentGroup').textContent = app.current_group || '-';
    document.getElementById('currentGroupDesc').textContent = app.current_group_desc || '';
    document.getElementById('targetGroup').textContent = app.target_group || '-';
    document.getElementById('targetGroupDesc').textContent = app.target_group_desc || '';
    document.getElementById('reason').textContent = app.reason || '-';

    // Status Card
    updateStatusCard(app.status);

    // Approval Info
    if (app.status !== 'pending') {
        document.getElementById('approvalCard').style.display = 'block';
        document.getElementById('approver').textContent = app.approver_name || '-';
        document.getElementById('approvedAt').textContent = app.approved_at || '-';
        document.getElementById('approvalNote').textContent = app.approval_note || '-';
    }

    // Timeline
    renderTimeline(app.timeline || []);

    // Group Comparison
    document.getElementById('currentLeverage').textContent = app.current_leverage || '-';
    document.getElementById('targetLeverage').textContent = app.target_leverage || '-';
    document.getElementById('currentSpread').textContent = app.current_spread || '-';
    document.getElementById('targetSpread').textContent = app.target_spread || '-';
    document.getElementById('currentCommission').textContent = app.current_commission || '-';
    document.getElementById('targetCommission').textContent = app.target_commission || '-';
}

function updateStatusCard(status) {
    const statusCard = document.getElementById('statusCard');
    const statusIcon = document.getElementById('statusIcon');
    const statusText = document.getElementById('statusText');
    const statusDesc = document.getElementById('statusDesc');

    if (status === 'pending') {
        statusCard.style.background = 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)';
        statusIcon.className = 'cil-clock';
        statusText.textContent = '待审核';
        statusDesc.textContent = '申请已提交，等待审核';
    } else if (status === 'approved') {
        statusCard.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
        statusIcon.className = 'cil-check-circle';
        statusText.textContent = '已批准';
        statusDesc.textContent = '申请已通过审核';
    } else if (status === 'rejected') {
        statusCard.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
        statusIcon.className = 'cil-x-circle';
        statusText.textContent = '已拒绝';
        statusDesc.textContent = '申请未通过审核';
    }
}

function renderTimeline(timeline) {
    const timelineEl = document.getElementById('timeline');

    if (timeline.length === 0) {
        timelineEl.innerHTML = '<p class="text-body-secondary small mb-0">暂无时间线记录</p>';
        return;
    }

    const html = timeline.map(t => `
        <div class="timeline-item mb-3">
            <div class="d-flex align-items-start">
                <div class="flex-shrink-0">
                    <div class="avatar avatar-xs ${t.type === 'success' ? 'bg-success' : 'bg-secondary'} bg-opacity-10 text-${t.type === 'success' ? 'success' : 'secondary'}">
                        <i class="${t.icon || 'cil-circle'}"></i>
                    </div>
                </div>
                <div class="flex-grow-1 ms-3">
                    <p class="mb-1 small fw-semibold">${t.title || ''}</p>
                    <p class="mb-0 text-body-secondary small">${t.time || ''}</p>
                </div>
            </div>
        </div>
    `).join('');

    timelineEl.innerHTML = html;
}

function viewCustomer() {
    window.location.href = `{{ route('front_coreui_v2_page_agent_customers_detail') }}?id=${customerId}`;
}

function goBack() {
    window.location.href = '{{ route("front_coreui_v2_page_agent_group_change") }}';
}

function getStatusBadge(status) {
    if (status === 'pending') {
        return '<span class="badge bg-warning">待审核</span>';
    } else if (status === 'approved') {
        return '<span class="badge bg-success">已批准</span>';
    } else if (status === 'rejected') {
        return '<span class="badge bg-danger">已拒绝</span>';
    }
    return '<span class="badge bg-secondary">-</span>';
}

function formatCurrency(value) {
    if (!value && value !== 0) return '-';
    return '$' + parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.avatar {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 1.25rem;
}

.avatar-xs {
    width: 24px;
    height: 24px;
    font-size: 0.875rem;
}
</style>
@endsection
