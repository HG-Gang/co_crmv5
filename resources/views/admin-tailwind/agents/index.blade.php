@extends('admin-tailwind.layouts.app')

@section('title', '代理列表 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">代理列表</h1>
        <p class="text-slate-600 mt-2">管理代理账户及其业绩信息</p>
    </div>
    <button onclick="openAddModal()" class="px-6 py-3 bg-gradient-to-r from-purple-500 to-pink-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
        <i class="fas fa-plus mr-2"></i>添加代理
    </button>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">总代理数</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="totalAgents">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-pink-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-user-tie"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">今日新增</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="todayAgents">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-user-plus"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">活跃代理</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="activeAgents">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-chart-line"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">总客户数</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="totalCustomers">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-red-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-users"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">本月佣金</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="monthCommission">$0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-yellow-500 to-amber-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-dollar-sign"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">关键词</label>
            <input type="text" id="filterKeyword" placeholder="用户名、邮箱、手机号" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">代理等级</label>
            <select id="filterLevel" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                <option value="">全部等级</option>
                <option value="1">一级代理</option>
                <option value="2">二级代理</option>
                <option value="3">三级代理</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">状态</label>
            <select id="filterStatus" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                <option value="">全部状态</option>
                <option value="active">正常</option>
                <option value="frozen">冻结</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">注册时间</label>
            <input type="date" id="filterDate" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
        </div>
        <div class="flex items-end gap-2">
            <button onclick="searchAgents()" class="flex-1 px-4 py-2 bg-gradient-to-r from-purple-500 to-pink-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-search mr-2"></i>搜索
            </button>
            <button onclick="resetFilters()" class="px-4 py-2 bg-slate-200 text-slate-700 font-semibold rounded-lg hover:bg-slate-300 transition">
                <i class="fas fa-redo"></i>
            </button>
        </div>
    </div>
</div>

<!-- Agents Table -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase">代理信息</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase">等级</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase">上级代理</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase">客户数</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase">本月佣金</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase">状态</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase">注册时间</th>
                    <th class="px-6 py-4 text-right text-xs font-semibold text-slate-600 uppercase">操作</th>
                </tr>
            </thead>
            <tbody id="agentsTableBody" class="divide-y divide-slate-100">
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center text-slate-400">
                        <i class="fas fa-spinner fa-spin mr-2"></i>加载中...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<div class="mt-6 flex items-center justify-between">
    <div class="text-sm text-slate-600">
        显示第 <span id="pageStart">0</span> 至 <span id="pageEnd">0</span> 条，共 <span id="pageTotal">0</span> 条
    </div>
    <div id="pagination" class="flex gap-2"></div>
</div>

<!-- Add/Edit Modal -->
<div id="agentModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-slate-800">
                <i class="fas fa-user-tie text-purple-600 mr-2"></i><span id="modalTitle">添加代理</span>
            </h3>
            <button onclick="closeAgentModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <input type="hidden" id="agentId">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">用户名 <span class="text-red-500">*</span></label>
                    <input type="text" id="agentUsername" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500" placeholder="请输入用户名">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">邮箱 <span class="text-red-500">*</span></label>
                    <input type="email" id="agentEmail" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500" placeholder="请输入邮箱">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">手机号</label>
                    <input type="text" id="agentPhone" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500" placeholder="请输入手机号">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">真实姓名</label>
                    <input type="text" id="agentRealName" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500" placeholder="请输入真实姓名">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">代理等级 <span class="text-red-500">*</span></label>
                    <select id="agentLevel" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        <option value="">请选择等级</option>
                        <option value="1">一级代理</option>
                        <option value="2">二级代理</option>
                        <option value="3">三级代理</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">上级代理</label>
                    <select id="agentParent" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        <option value="">无上级（顶级代理）</option>
                        <option value="1">代理A</option>
                        <option value="2">代理B</option>
                    </select>
                </div>
            </div>

            <div id="passwordFields" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">密码 <span class="text-red-500" id="passwordRequired">*</span></label>
                    <input type="password" id="agentPassword" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500" placeholder="请输入密码">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">确认密码 <span class="text-red-500" id="confirmRequired">*</span></label>
                    <input type="password" id="agentPasswordConfirm" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500" placeholder="请再次输入密码">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-2">佣金比例 (%)</label>
                <input type="number" id="agentCommissionRate" min="0" max="100" step="0.01" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500" placeholder="例如: 5.5">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-2">备注</label>
                <textarea id="agentRemark" rows="3" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500" placeholder="备注信息"></textarea>
            </div>

            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                <div class="flex items-start gap-2">
                    <i class="fas fa-info-circle text-purple-600 mt-1"></i>
                    <div class="text-sm text-purple-800">
                        <p class="font-semibold mb-1">提示</p>
                        <ul class="list-disc list-inside space-y-1 text-xs">
                            <li>代理等级决定了佣金结算层级和权限范围</li>
                            <li>上级代理关系一旦确定，请谨慎修改</li>
                            <li>佣金比例应根据代理等级和业绩设定</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="sticky bottom-0 bg-slate-50 px-6 py-4 flex justify-end gap-3 border-t border-slate-200">
            <button onclick="closeAgentModal()" class="px-6 py-2 bg-slate-200 text-slate-700 font-semibold rounded-lg hover:bg-slate-300 transition">
                取消
            </button>
            <button onclick="saveAgent()" class="px-6 py-2 bg-gradient-to-r from-purple-500 to-pink-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-save mr-2"></i>保存
            </button>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let totalPages = 1;

document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadAgents();
});

function loadStats() {
    fetch('{{ route("admin_api_agents_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.stats) {
            document.getElementById('totalAgents').textContent = data.stats.total || 0;
            document.getElementById('todayAgents').textContent = data.stats.today || 0;
            document.getElementById('activeAgents').textContent = data.stats.active || 0;
            document.getElementById('totalCustomers').textContent = data.stats.customers || 0;
            document.getElementById('monthCommission').textContent = '$' + (data.stats.commission || 0);
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadAgents(page = 1) {
    const keyword = document.getElementById('filterKeyword').value;
    const level = document.getElementById('filterLevel').value;
    const status = document.getElementById('filterStatus').value;
    const date = document.getElementById('filterDate').value;

    const params = new URLSearchParams({
        page: page,
        keyword: keyword,
        level: level,
        status: status,
        date: date
    });

    fetch(`{{ route("admin_api_agents_list") }}?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.agents) {
            renderAgents(data.agents);
            renderPagination(data.pagination);
        }
    })
    .catch(err => {
        console.error('Load agents error:', err);
        document.getElementById('agentsTableBody').innerHTML = `
            <tr><td colspan="8" class="px-6 py-12 text-center text-slate-400">
                <i class="fas fa-exclamation-circle mr-2"></i>加载失败
            </td></tr>
        `;
    });
}

function renderAgents(agents) {
    if (agents.length === 0) {
        document.getElementById('agentsTableBody').innerHTML = `
            <tr><td colspan="8" class="px-6 py-12 text-center text-slate-400">
                <i class="fas fa-inbox mr-2"></i>暂无数据
            </td></tr>
        `;
        return;
    }

    const html = agents.map(a => `
        <tr class="hover:bg-slate-50">
            <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-600 rounded-full flex items-center justify-center text-white font-semibold">
                        ${a.username ? a.username.charAt(0).toUpperCase() : 'A'}
                    </div>
                    <div>
                        <p class="font-semibold text-slate-800">${a.username}</p>
                        <p class="text-xs text-slate-500">${a.real_name || '-'}</p>
                        <p class="text-xs text-slate-400">${a.email || '-'}</p>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4">
                ${getLevelBadge(a.level)}
            </td>
            <td class="px-6 py-4">
                <p class="text-sm text-slate-800">${a.parent_username || '-'}</p>
                ${a.parent_id ? `<p class="text-xs text-slate-500">ID: ${a.parent_id}</p>` : ''}
            </td>
            <td class="px-6 py-4">
                <p class="text-sm font-semibold text-slate-800">${a.customers_count || 0}</p>
                <p class="text-xs text-slate-500">直属客户</p>
            </td>
            <td class="px-6 py-4">
                <p class="text-sm font-semibold text-green-600">$${a.month_commission || 0}</p>
                <p class="text-xs text-slate-500">${a.commission_rate || 0}%</p>
            </td>
            <td class="px-6 py-4">
                ${getStatusBadge(a.status)}
            </td>
            <td class="px-6 py-4">
                <p class="text-sm text-slate-600">${a.created_at || '-'}</p>
            </td>
            <td class="px-6 py-4">
                <div class="flex items-center justify-end gap-2">
                    <button onclick="viewAgent(${a.id})" class="px-3 py-1 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="查看">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button onclick="editAgent(${a.id})" class="px-3 py-1 text-purple-600 hover:bg-purple-50 rounded-lg transition" title="编辑">
                        <i class="fas fa-edit"></i>
                    </button>
                    ${a.status === 'active' ? `
                        <button onclick="freezeAgent(${a.id})" class="px-3 py-1 text-orange-600 hover:bg-orange-50 rounded-lg transition" title="冻结">
                            <i class="fas fa-snowflake"></i>
                        </button>
                    ` : `
                        <button onclick="unfreezeAgent(${a.id})" class="px-3 py-1 text-green-600 hover:bg-green-50 rounded-lg transition" title="解冻">
                            <i class="fas fa-check-circle"></i>
                        </button>
                    `}
                    <button onclick="deleteAgent(${a.id})" class="px-3 py-1 text-red-600 hover:bg-red-50 rounded-lg transition" title="删除">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');

    document.getElementById('agentsTableBody').innerHTML = html;
}

function getLevelBadge(level) {
    const badges = {
        '1': '<span class="px-2 py-1 bg-yellow-100 text-yellow-700 text-xs font-semibold rounded-full"><i class="fas fa-star mr-1"></i>一级代理</span>',
        '2': '<span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full"><i class="fas fa-star mr-1"></i>二级代理</span>',
        '3': '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full"><i class="fas fa-star mr-1"></i>三级代理</span>'
    };
    return badges[level] || '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">-</span>';
}

function getStatusBadge(status) {
    const badges = {
        'active': '<span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full"><i class="fas fa-check-circle mr-1"></i>正常</span>',
        'frozen': '<span class="px-2 py-1 bg-orange-100 text-orange-700 text-xs font-semibold rounded-full"><i class="fas fa-snowflake mr-1"></i>冻结</span>'
    };
    return badges[status] || '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">未知</span>';
}

function renderPagination(pagination) {
    if (!pagination) return;

    currentPage = pagination.current_page;
    totalPages = pagination.last_page;

    document.getElementById('pageStart').textContent = pagination.from || 0;
    document.getElementById('pageEnd').textContent = pagination.to || 0;
    document.getElementById('pageTotal').textContent = pagination.total || 0;

    let html = '';
    if (currentPage > 1) {
        html += `<button onclick="loadAgents(${currentPage - 1})" class="px-3 py-1 border border-slate-300 rounded-lg hover:bg-slate-50">上一页</button>`;
    }

    for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
        if (i === currentPage) {
            html += `<button class="px-3 py-1 bg-purple-600 text-white rounded-lg">${i}</button>`;
        } else {
            html += `<button onclick="loadAgents(${i})" class="px-3 py-1 border border-slate-300 rounded-lg hover:bg-slate-50">${i}</button>`;
        }
    }

    if (currentPage < totalPages) {
        html += `<button onclick="loadAgents(${currentPage + 1})" class="px-3 py-1 border border-slate-300 rounded-lg hover:bg-slate-50">下一页</button>`;
    }

    document.getElementById('pagination').innerHTML = html;
}

function searchAgents() {
    loadAgents(1);
}

function resetFilters() {
    document.getElementById('filterKeyword').value = '';
    document.getElementById('filterLevel').value = '';
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterDate').value = '';
    loadAgents(1);
}

function openAddModal() {
    document.getElementById('modalTitle').textContent = '添加代理';
    document.getElementById('agentId').value = '';
    document.getElementById('agentUsername').value = '';
    document.getElementById('agentEmail').value = '';
    document.getElementById('agentPhone').value = '';
    document.getElementById('agentRealName').value = '';
    document.getElementById('agentLevel').value = '';
    document.getElementById('agentParent').value = '';
    document.getElementById('agentPassword').value = '';
    document.getElementById('agentPasswordConfirm').value = '';
    document.getElementById('agentCommissionRate').value = '';
    document.getElementById('agentRemark').value = '';

    document.getElementById('passwordFields').classList.remove('hidden');
    document.getElementById('passwordRequired').classList.remove('hidden');
    document.getElementById('confirmRequired').classList.remove('hidden');

    document.getElementById('agentModal').classList.remove('hidden');
}

function editAgent(id) {
    fetch(`{{ route("admin_api_agents_detail") }}?id=${id}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.agent) {
            const a = data.agent;
            document.getElementById('modalTitle').textContent = '编辑代理';
            document.getElementById('agentId').value = a.id;
            document.getElementById('agentUsername').value = a.username || '';
            document.getElementById('agentEmail').value = a.email || '';
            document.getElementById('agentPhone').value = a.phone || '';
            document.getElementById('agentRealName').value = a.real_name || '';
            document.getElementById('agentLevel').value = a.level || '';
            document.getElementById('agentParent').value = a.parent_id || '';
            document.getElementById('agentCommissionRate').value = a.commission_rate || '';
            document.getElementById('agentRemark').value = a.remark || '';

            document.getElementById('passwordFields').classList.remove('hidden');
            document.getElementById('passwordRequired').classList.add('hidden');
            document.getElementById('confirmRequired').classList.add('hidden');

            document.getElementById('agentModal').classList.remove('hidden');
        }
    })
    .catch(err => console.error('Load agent detail error:', err));
}

function viewAgent(id) {
    window.location.href = `{{ url('/admin-tailwind/agents') }}/${id}`;
}

function closeAgentModal() {
    document.getElementById('agentModal').classList.add('hidden');
}

function saveAgent() {
    const id = document.getElementById('agentId').value;
    const username = document.getElementById('agentUsername').value.trim();
    const email = document.getElementById('agentEmail').value.trim();
    const phone = document.getElementById('agentPhone').value.trim();
    const realName = document.getElementById('agentRealName').value.trim();
    const level = document.getElementById('agentLevel').value;
    const parentId = document.getElementById('agentParent').value;
    const password = document.getElementById('agentPassword').value;
    const passwordConfirm = document.getElementById('agentPasswordConfirm').value;
    const commissionRate = document.getElementById('agentCommissionRate').value;
    const remark = document.getElementById('agentRemark').value.trim();

    if (!username) {
        alert('请输入用户名');
        return;
    }

    if (!email) {
        alert('请输入邮箱');
        return;
    }

    if (!level) {
        alert('请选择代理等级');
        return;
    }

    if (!id) {
        if (!password) {
            alert('请输入密码');
            return;
        }
        if (password !== passwordConfirm) {
            alert('两次密码输入不一致');
            return;
        }
    } else {
        if (password && password !== passwordConfirm) {
            alert('两次密码输入不一致');
            return;
        }
    }

    const data = {
        id: id || undefined,
        username: username,
        email: email,
        phone: phone,
        real_name: realName,
        level: level,
        parent_id: parentId || undefined,
        password: password || undefined,
        commission_rate: commissionRate || undefined,
        remark: remark
    };

    fetch('{{ route("admin_api_agents_save") }}', {
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
            alert(id ? '更新成功' : '添加成功');
            closeAgentModal();
            loadStats();
            loadAgents(currentPage);
        } else {
            alert(data.message || '保存失败');
        }
    })
    .catch(err => {
        console.error('Save agent error:', err);
        alert('网络错误，请稍后重试');
    });
}

function freezeAgent(id) {
    if (!confirm('确定要冻结该代理吗？冻结后将无法登录和操作。')) return;

    fetch('{{ route("admin_api_agents_freeze") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ id: id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('冻结成功');
            loadStats();
            loadAgents(currentPage);
        } else {
            alert(data.message || '冻结失败');
        }
    })
    .catch(err => {
        console.error('Freeze agent error:', err);
        alert('网络错误，请稍后重试');
    });
}

function unfreezeAgent(id) {
    if (!confirm('确定要解冻该代理吗？')) return;

    fetch('{{ route("admin_api_agents_unfreeze") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ id: id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('解冻成功');
            loadStats();
            loadAgents(currentPage);
        } else {
            alert(data.message || '解冻失败');
        }
    })
    .catch(err => {
        console.error('Unfreeze agent error:', err);
        alert('网络错误，请稍后重试');
    });
}

function deleteAgent(id) {
    if (!confirm('确定要删除该代理吗？此操作不可恢复！')) return;

    fetch('{{ route("admin_api_agents_delete") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ id: id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            alert('删除成功');
            loadStats();
            loadAgents(currentPage);
        } else {
            alert(data.message || '删除失败');
        }
    })
    .catch(err => {
        console.error('Delete agent error:', err);
        alert('网络错误，请稍后重试');
    });
}
</script>
@endsection
