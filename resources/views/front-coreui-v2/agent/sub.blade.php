@extends('front-coreui-v2.layouts.app')

@section('title', '下级代理')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-2">下级代理管理</h2>
            <p class="text-body-secondary mb-0">查看和管理直属下级代理列表</p>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-body-secondary mb-2">下级代理总数</p>
                            <h4 class="mb-0 fw-bold text-primary" id="totalAgents">0</h4>
                        </div>
                        <div class="avatar avatar-sm bg-primary bg-opacity-10 text-primary">
                            <i class="cil-people"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-body-secondary mb-2">活跃代理</p>
                            <h4 class="mb-0 fw-bold text-success" id="activeAgents">0</h4>
                        </div>
                        <div class="avatar avatar-sm bg-success bg-opacity-10 text-success">
                            <i class="cil-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-body-secondary mb-2">总客户数</p>
                            <h4 class="mb-0 fw-bold text-info" id="totalCustomers">0</h4>
                        </div>
                        <div class="avatar avatar-sm bg-info bg-opacity-10 text-info">
                            <i class="cil-user"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-body-secondary mb-2">本月新增</p>
                            <h4 class="mb-0 fw-bold text-warning" id="monthlyNew">0</h4>
                        </div>
                        <div class="avatar avatar-sm bg-warning bg-opacity-10 text-warning">
                            <i class="cil-plus"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">代理等级</label>
                    <select id="filterLevel" class="form-select">
                        <option value="">全部等级</option>
                        <option value="1">一级代理</option>
                        <option value="2">二级代理</option>
                        <option value="3">三级代理</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">代理状态</label>
                    <select id="filterStatus" class="form-select">
                        <option value="">全部状态</option>
                        <option value="active">活跃</option>
                        <option value="inactive">未激活</option>
                        <option value="suspended">暂停</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">注册时间</label>
                    <select id="filterTime" class="form-select">
                        <option value="">全部时间</option>
                        <option value="today">今天</option>
                        <option value="week">本周</option>
                        <option value="month">本月</option>
                        <option value="year">本年</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">搜索代理</label>
                    <input type="text" id="filterKeyword" class="form-control" placeholder="姓名/邮箱/账号">
                </div>
                <div class="col-md-2">
                    <div class="d-flex gap-2">
                        <button onclick="loadAgents()" class="btn btn-primary flex-grow-1">
                            <i class="cil-search me-2"></i>查询
                        </button>
                        <button onclick="resetFilters()" class="btn btn-outline-secondary">
                            <i class="cil-reload"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Agents Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="cil-layers me-2"></i>下级代理列表
                </h5>
                <div class="d-flex align-items-center gap-3">
                    <button onclick="exportData()" class="btn btn-sm btn-outline-secondary">
                        <i class="cil-cloud-download me-1"></i>导出
                    </button>
                    <button onclick="inviteAgent()" class="btn btn-sm btn-primary">
                        <i class="cil-plus me-1"></i>邀请代理
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>代理信息</th>
                            <th>代理等级</th>
                            <th>下级客户</th>
                            <th>团队业绩</th>
                            <th>累计佣金</th>
                            <th>注册时间</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="agentsTable">
                        <tr>
                            <td colspan="8" class="text-center py-5 text-body-secondary">
                                <div class="spinner-border spinner-border-sm me-2"></div>
                                加载中...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-transparent border-top">
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-body-secondary small">
                    共 <span id="totalRecords">0</span> 条代理记录
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="pagination">
                        <li class="page-item disabled">
                            <a class="page-link" href="#">上一页</a>
                        </li>
                        <li class="page-item active">
                            <a class="page-link" href="#">1</a>
                        </li>
                        <li class="page-item disabled">
                            <a class="page-link" href="#">下一页</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Invite Agent Modal -->
<div class="modal fade" id="inviteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="cil-envelope-closed me-2"></i>邀请代理
                </h5>
                <button type="button" class="btn-close" data-coreui-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">邀请链接</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="inviteLink" readonly>
                        <button class="btn btn-outline-secondary" onclick="copyInviteLink()">
                            <i class="cil-copy"></i>
                        </button>
                    </div>
                    <small class="text-body-secondary">分享此链接邀请新代理注册</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">邀请码</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="inviteCode" readonly>
                        <button class="btn btn-outline-secondary" onclick="copyInviteCode()">
                            <i class="cil-copy"></i>
                        </button>
                    </div>
                    <small class="text-body-secondary">新代理注册时填写此邀请码</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-coreui-dismiss="modal">关闭</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = 1;

document.addEventListener('DOMContentLoaded', function() {
    loadSummary();
    loadAgents();
    loadInviteInfo();
});

function loadSummary() {
    fetch('{{ route("front_api_agent_sub_summary") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.summary) {
            document.getElementById('totalAgents').textContent = data.summary.total_agents || 0;
            document.getElementById('activeAgents').textContent = data.summary.active_agents || 0;
            document.getElementById('totalCustomers').textContent = data.summary.total_customers || 0;
            document.getElementById('monthlyNew').textContent = data.summary.monthly_new || 0;
        }
    })
    .catch(err => {
        console.error('Load summary error:', err);
    });
}

function loadAgents(page = 1) {
    currentPage = page;
    const params = getFilterParams();
    params.append('page', page);

    fetch('{{ route("front_api_agent_sub_list") }}?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.agents) {
            renderAgents(data.agents);
            renderPagination(data.pagination);
            document.getElementById('totalRecords').textContent = data.pagination?.total || 0;
        }
    })
    .catch(err => {
        console.error('Load agents error:', err);
        showError('加载失败，请稍后重试');
    });
}

function renderAgents(agents) {
    const tbody = document.getElementById('agentsTable');

    if (agents.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-5 text-body-secondary">
                    <i class="cil-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p class="mt-3">暂无下级代理</p>
                </td>
            </tr>
        `;
        return;
    }

    const html = agents.map(a => `
        <tr>
            <td>
                <div class="d-flex align-items-center">
                    <div class="avatar avatar-sm bg-primary bg-opacity-10 text-primary me-3">
                        <i class="cil-user"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">${a.name || '-'}</div>
                        <small class="text-body-secondary">${a.email || ''}</small>
                        <div><small class="badge bg-secondary mt-1">${a.agent_code || ''}</small></div>
                    </div>
                </div>
            </td>
            <td>
                <span class="badge ${getLevelBadge(a.level)}">${getLevelText(a.level)}</span>
            </td>
            <td class="fw-semibold">${a.customer_count || 0} 人</td>
            <td class="text-success fw-semibold">${formatCurrency(a.team_volume)}</td>
            <td class="text-primary fw-semibold">${formatCurrency(a.total_commission)}</td>
            <td class="text-body-secondary small">${a.created_at || '-'}</td>
            <td>${getStatusBadge(a.status)}</td>
            <td>
                <div class="btn-group btn-group-sm">
                    <a href="{{ route('front_coreui_v2_page_agent_customers_detail') }}?agent=${a.id}" class="btn btn-outline-primary">
                        详情
                    </a>
                    <button onclick="viewCustomers(${a.id})" class="btn btn-outline-secondary">
                        客户
                    </button>
                </div>
            </td>
        </tr>
    `).join('');

    tbody.innerHTML = html;
}

function renderPagination(pagination) {
    if (!pagination) return;

    const paginationEl = document.getElementById('pagination');
    let html = '';

    html += `<li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadAgents(${pagination.current_page - 1}); return false;">上一页</a>
    </li>`;

    for (let i = 1; i <= pagination.last_page; i++) {
        if (i === 1 || i === pagination.last_page || (i >= pagination.current_page - 2 && i <= pagination.current_page + 2)) {
            html += `<li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                <a class="page-link" href="#" onclick="loadAgents(${i}); return false;">${i}</a>
            </li>`;
        } else if (i === pagination.current_page - 3 || i === pagination.current_page + 3) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }

    html += `<li class="page-item ${pagination.current_page === pagination.last_page ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadAgents(${pagination.current_page + 1}); return false;">下一页</a>
    </li>`;

    paginationEl.innerHTML = html;
}

function getFilterParams() {
    const params = new URLSearchParams();
    const level = document.getElementById('filterLevel').value;
    const status = document.getElementById('filterStatus').value;
    const time = document.getElementById('filterTime').value;
    const keyword = document.getElementById('filterKeyword').value;

    if (level) params.append('level', level);
    if (status) params.append('status', status);
    if (time) params.append('time', time);
    if (keyword) params.append('keyword', keyword);

    return params;
}

function resetFilters() {
    document.getElementById('filterLevel').value = '';
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterTime').value = '';
    document.getElementById('filterKeyword').value = '';
    loadAgents();
}

function loadInviteInfo() {
    fetch('{{ route("front_api_agent_invite_info") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.invite) {
            document.getElementById('inviteLink').value = data.invite.link || '';
            document.getElementById('inviteCode').value = data.invite.code || '';
        }
    })
    .catch(err => {
        console.error('Load invite info error:', err);
    });
}

function inviteAgent() {
    const modal = new coreui.Modal(document.getElementById('inviteModal'));
    modal.show();
}

function copyInviteLink() {
    const input = document.getElementById('inviteLink');
    input.select();
    document.execCommand('copy');
    alert('邀请链接已复制');
}

function copyInviteCode() {
    const input = document.getElementById('inviteCode');
    input.select();
    document.execCommand('copy');
    alert('邀请码已复制');
}

function viewCustomers(agentId) {
    window.location.href = `{{ route('front_coreui_v2_page_agent_customers') }}?agent=${agentId}`;
}

function exportData() {
    const params = getFilterParams();
    window.location.href = '{{ route("front_api_agent_sub_export") }}?' + params.toString();
}

function getLevelBadge(level) {
    const badges = {
        1: 'bg-success',
        2: 'bg-info',
        3: 'bg-warning'
    };
    return badges[level] || 'bg-secondary';
}

function getLevelText(level) {
    const texts = {
        1: '一级代理',
        2: '二级代理',
        3: '三级代理'
    };
    return texts[level] || '未知';
}

function getStatusBadge(status) {
    if (status === 'active') {
        return '<span class="badge bg-success">活跃</span>';
    } else if (status === 'inactive') {
        return '<span class="badge bg-secondary">未激活</span>';
    } else if (status === 'suspended') {
        return '<span class="badge bg-danger">暂停</span>';
    }
    return '<span class="badge bg-secondary">-</span>';
}

function formatCurrency(value) {
    if (!value && value !== 0) return '-';
    return '$' + parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function showError(message) {
    document.getElementById('agentsTable').innerHTML = `
        <tr>
            <td colspan="8" class="text-center py-5">
                <i class="cil-warning text-danger" style="font-size: 3rem;"></i>
                <p class="text-danger mt-3">${message}</p>
            </td>
        </tr>
    `;
}
</script>

<style>
.avatar {
    width: 40px;
    height: 40px;
    display: flex;
    align-items-center;
    justify-content: center;
    border-radius: 8px;
    font-size: 1.25rem;
}

.avatar-sm {
    width: 36px;
    height: 36px;
    font-size: 1.1rem;
}
</style>
@endsection
