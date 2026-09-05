@extends('front-coreui-v2.layouts.app')

@section('title', '确认代理等级')

@section('content')
<div class="container-lg px-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-2">确认代理等级</h2>
            <p class="text-body-secondary mb-0">为新注册的代理确认等级并激活账户</p>
        </div>
    </div>

    <!-- Pending Confirmations -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-transparent border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="cil-clock me-2"></i>待确认列表
                </h5>
                <span class="badge bg-warning" id="pendingCount">0</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>申请信息</th>
                            <th>MT4账号</th>
                            <th>申请等级</th>
                            <th>推荐人</th>
                            <th>申请时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="pendingTable">
                        <tr>
                            <td colspan="6" class="text-center py-5 text-body-secondary">
                                <div class="spinner-border spinner-border-sm me-2"></div>
                                加载中...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Confirmed History -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-transparent border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="cil-check me-2"></i>确认历史
                </h5>
                <div class="d-flex align-items-center gap-2">
                    <select id="filterStatus" class="form-select form-select-sm" onchange="loadHistory()">
                        <option value="">全部状态</option>
                        <option value="approved">已批准</option>
                        <option value="rejected">已拒绝</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>代理信息</th>
                            <th>MT4账号</th>
                            <th>申请等级</th>
                            <th>确认等级</th>
                            <th>确认时间</th>
                            <th>状态</th>
                            <th>备注</th>
                        </tr>
                    </thead>
                    <tbody id="historyTable">
                        <tr>
                            <td colspan="7" class="text-center py-5 text-body-secondary">
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
                    共 <span id="totalRecords">0</span> 条记录
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

<!-- Confirm Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="cil-check-circle me-2"></i>确认代理等级
                </h5>
                <button type="button" class="btn-close" data-coreui-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">代理姓名</label>
                    <input type="text" class="form-control" id="confirmName" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">申请等级</label>
                    <input type="text" class="form-control" id="confirmRequestLevel" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">确认等级 <span class="text-danger">*</span></label>
                    <select class="form-select" id="confirmLevel">
                        <option value="">请选择等级</option>
                        <option value="1">一级代理</option>
                        <option value="2">二级代理</option>
                        <option value="3">三级代理</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">备注说明</label>
                    <textarea class="form-control" id="confirmNote" rows="3" placeholder="选填"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-coreui-dismiss="modal">取消</button>
                <button type="button" class="btn btn-success" onclick="submitConfirm()">
                    <i class="cil-check me-2"></i>确认批准
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="cil-x-circle me-2"></i>拒绝申请
                </h5>
                <button type="button" class="btn-close" data-coreui-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">代理姓名</label>
                    <input type="text" class="form-control" id="rejectName" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">拒绝原因 <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="rejectReason" rows="4" placeholder="请说明拒绝原因"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-coreui-dismiss="modal">取消</button>
                <button type="button" class="btn btn-danger" onclick="submitReject()">
                    <i class="cil-x me-2"></i>确认拒绝
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let currentApplicationId = null;

document.addEventListener('DOMContentLoaded', function() {
    loadPending();
    loadHistory();
});

function loadPending() {
    fetch('{{ route("front_api_agent_confirm_level_pending") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.applications) {
            renderPending(data.applications);
            document.getElementById('pendingCount').textContent = data.applications.length;
        }
    })
    .catch(err => {
        console.error('Load pending error:', err);
    });
}

function renderPending(applications) {
    const tbody = document.getElementById('pendingTable');

    if (applications.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center py-5 text-body-secondary">
                    <i class="cil-check-circle" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p class="mt-3">暂无待确认申请</p>
                </td>
            </tr>
        `;
        return;
    }

    const html = applications.map(a => `
        <tr>
            <td>
                <div class="d-flex align-items-center">
                    <div class="avatar avatar-sm bg-primary bg-opacity-10 text-primary me-3">
                        <i class="cil-user"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">${a.name || '-'}</div>
                        <small class="text-body-secondary">${a.email || ''}</small>
                    </div>
                </div>
            </td>
            <td class="fw-semibold">${a.mt4_account || '-'}</td>
            <td><span class="badge ${getLevelBadge(a.requested_level)}">${getLevelText(a.requested_level)}</span></td>
            <td class="text-body-secondary small">${a.referrer_name || '-'}</td>
            <td class="text-body-secondary small">${a.created_at || '-'}</td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button onclick="openConfirmModal(${a.id}, '${a.name}', ${a.requested_level})" class="btn btn-outline-success">
                        <i class="cil-check me-1"></i>批准
                    </button>
                    <button onclick="openRejectModal(${a.id}, '${a.name}')" class="btn btn-outline-danger">
                        <i class="cil-x me-1"></i>拒绝
                    </button>
                </div>
            </td>
        </tr>
    `).join('');

    tbody.innerHTML = html;
}

function loadHistory(page = 1) {
    currentPage = page;
    const status = document.getElementById('filterStatus').value;
    const params = new URLSearchParams();
    if (status) params.append('status', status);
    params.append('page', page);

    fetch('{{ route("front_api_agent_confirm_level_history") }}?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.history) {
            renderHistory(data.history);
            renderPagination(data.pagination);
            document.getElementById('totalRecords').textContent = data.pagination?.total || 0;
        }
    })
    .catch(err => {
        console.error('Load history error:', err);
    });
}

function renderHistory(history) {
    const tbody = document.getElementById('historyTable');

    if (history.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-5 text-body-secondary">
                    <i class="cil-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p class="mt-3">暂无确认记录</p>
                </td>
            </tr>
        `;
        return;
    }

    const html = history.map(h => `
        <tr>
            <td>
                <div class="d-flex align-items-center">
                    <div class="avatar avatar-sm bg-primary bg-opacity-10 text-primary me-3">
                        <i class="cil-user"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">${h.name || '-'}</div>
                        <small class="text-body-secondary">${h.email || ''}</small>
                    </div>
                </div>
            </td>
            <td class="fw-semibold">${h.mt4_account || '-'}</td>
            <td><span class="badge bg-secondary">${getLevelText(h.requested_level)}</span></td>
            <td><span class="badge ${getLevelBadge(h.confirmed_level)}">${getLevelText(h.confirmed_level)}</span></td>
            <td class="text-body-secondary small">${h.confirmed_at || '-'}</td>
            <td>${getStatusBadge(h.status)}</td>
            <td class="text-body-secondary small">${h.note || '-'}</td>
        </tr>
    `).join('');

    tbody.innerHTML = html;
}

function renderPagination(pagination) {
    if (!pagination) return;

    const paginationEl = document.getElementById('pagination');
    let html = '';

    html += `<li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadHistory(${pagination.current_page - 1}); return false;">上一页</a>
    </li>`;

    for (let i = 1; i <= pagination.last_page; i++) {
        if (i === 1 || i === pagination.last_page || (i >= pagination.current_page - 2 && i <= pagination.current_page + 2)) {
            html += `<li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                <a class="page-link" href="#" onclick="loadHistory(${i}); return false;">${i}</a>
            </li>`;
        } else if (i === pagination.current_page - 3 || i === pagination.current_page + 3) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }

    html += `<li class="page-item ${pagination.current_page === pagination.last_page ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadHistory(${pagination.current_page + 1}); return false;">下一页</a>
    </li>`;

    paginationEl.innerHTML = html;
}

function openConfirmModal(id, name, requestedLevel) {
    currentApplicationId = id;
    document.getElementById('confirmName').value = name;
    document.getElementById('confirmRequestLevel').value = getLevelText(requestedLevel);
    document.getElementById('confirmLevel').value = requestedLevel;
    document.getElementById('confirmNote').value = '';

    const modal = new coreui.Modal(document.getElementById('confirmModal'));
    modal.show();
}

function openRejectModal(id, name) {
    currentApplicationId = id;
    document.getElementById('rejectName').value = name;
    document.getElementById('rejectReason').value = '';

    const modal = new coreui.Modal(document.getElementById('rejectModal'));
    modal.show();
}

function submitConfirm() {
    const level = document.getElementById('confirmLevel').value;
    const note = document.getElementById('confirmNote').value;

    if (!level) {
        alert('请选择确认等级');
        return;
    }

    fetch('{{ route("front_api_agent_confirm_level_approve") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            application_id: currentApplicationId,
            level: level,
            note: note
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('确认成功');
            coreui.Modal.getInstance(document.getElementById('confirmModal')).hide();
            loadPending();
            loadHistory();
        } else {
            alert(data.message || '确认失败');
        }
    })
    .catch(err => {
        console.error('Confirm error:', err);
        alert('网络错误，请稍后重试');
    });
}

function submitReject() {
    const reason = document.getElementById('rejectReason').value;

    if (!reason.trim()) {
        alert('请填写拒绝原因');
        return;
    }

    fetch('{{ route("front_api_agent_confirm_level_reject") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            application_id: currentApplicationId,
            reason: reason
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('已拒绝申请');
            coreui.Modal.getInstance(document.getElementById('rejectModal')).hide();
            loadPending();
            loadHistory();
        } else {
            alert(data.message || '操作失败');
        }
    })
    .catch(err => {
        console.error('Reject error:', err);
        alert('网络错误，请稍后重试');
    });
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
    if (status === 'approved') {
        return '<span class="badge bg-success">已批准</span>';
    } else if (status === 'rejected') {
        return '<span class="badge bg-danger">已拒绝</span>';
    }
    return '<span class="badge bg-secondary">待处理</span>';
}
</script>

<style>
.avatar {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
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
