@extends('admin-tailwind.layouts.app')

@section('title', '大代理管理 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">大代理管理</h1>
        <p class="text-slate-600 mt-2">管理核心大代理及其业务数据</p>
    </div>
    <button onclick="openAddModal()" class="px-6 py-3 bg-gradient-to-r from-yellow-500 to-amber-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
        <i class="fas fa-crown mr-2"></i>添加大代理
    </button>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">大代理总数</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="totalBigAgents">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-yellow-500 to-amber-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-crown"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">活跃大代理</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="activeBigAgents">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-chart-line"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">下级代理数</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="totalSubAgents">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-pink-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-users"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">总客户数</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="totalCustomers">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-user-friends"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">本月总佣金</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="monthCommission">$0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-red-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-dollar-sign"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">关键词</label>
            <input type="text" id="filterKeyword" placeholder="用户名、邮箱、手机号" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500">
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">状态</label>
            <select id="filterStatus" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500">
                <option value="">全部状态</option>
                <option value="active">正常</option>
                <option value="frozen">冻结</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">业绩排序</label>
            <select id="filterSort" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500">
                <option value="">默认排序</option>
                <option value="commission_desc">佣金从高到低</option>
                <option value="commission_asc">佣金从低到高</option>
                <option value="customers_desc">客户从多到少</option>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button onclick="searchBigAgents()" class="flex-1 px-4 py-2 bg-gradient-to-r from-yellow-500 to-amber-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-search mr-2"></i>搜索
            </button>
            <button onclick="resetFilters()" class="px-4 py-2 bg-slate-200 text-slate-700 font-semibold rounded-lg hover:bg-slate-300 transition">
                <i class="fas fa-redo"></i>
            </button>
        </div>
    </div>
</div>

<!-- Big Agents Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6" id="bigAgentsGrid">
    <div class="col-span-full flex items-center justify-center py-12 text-slate-400">
        <i class="fas fa-spinner fa-spin mr-2"></i>加载中...
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
<div id="bigAgentModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-slate-800">
                <i class="fas fa-crown text-yellow-600 mr-2"></i><span id="modalTitle">添加大代理</span>
            </h3>
            <button onclick="closeBigAgentModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <input type="hidden" id="bigAgentId">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">用户名 <span class="text-red-500">*</span></label>
                    <input type="text" id="bigAgentUsername" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500" placeholder="请输入用户名">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">邮箱 <span class="text-red-500">*</span></label>
                    <input type="email" id="bigAgentEmail" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500" placeholder="请输入邮箱">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">手机号 <span class="text-red-500">*</span></label>
                    <input type="text" id="bigAgentPhone" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500" placeholder="请输入手机号">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">真实姓名 <span class="text-red-500">*</span></label>
                    <input type="text" id="bigAgentRealName" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500" placeholder="请输入真实姓名">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">公司名称</label>
                    <input type="text" id="bigAgentCompany" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500" placeholder="请输入公司名称">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">业务区域</label>
                    <input type="text" id="bigAgentRegion" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500" placeholder="例如：亚太区">
                </div>
            </div>

            <div id="passwordFields" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">密码 <span class="text-red-500" id="passwordRequired">*</span></label>
                    <input type="password" id="bigAgentPassword" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500" placeholder="请输入密码">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">确认密码 <span class="text-red-500" id="confirmRequired">*</span></label>
                    <input type="password" id="bigAgentPasswordConfirm" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500" placeholder="请再次输入密码">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">佣金比例 (%) <span class="text-red-500">*</span></label>
                    <input type="number" id="bigAgentCommissionRate" min="0" max="100" step="0.01" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500" placeholder="例如: 10.5">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">信用额度 ($)</label>
                    <input type="number" id="bigAgentCreditLimit" min="0" step="1000" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500" placeholder="例如: 100000">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-2">备注</label>
                <textarea id="bigAgentRemark" rows="3" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500" placeholder="备注信息"></textarea>
            </div>

            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex items-start gap-2">
                    <i class="fas fa-crown text-yellow-600 mt-1"></i>
                    <div class="text-sm text-yellow-800">
                        <p class="font-semibold mb-1">大代理特权</p>
                        <ul class="list-disc list-inside space-y-1 text-xs">
                            <li>可以发展无限层级的下级代理</li>
                            <li>享有最高级别的佣金比例和结算优先级</li>
                            <li>拥有独立的管理后台和数据看板</li>
                            <li>可设定专属信用额度和业务政策</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="sticky bottom-0 bg-slate-50 px-6 py-4 flex justify-end gap-3 border-t border-slate-200">
            <button onclick="closeBigAgentModal()" class="px-6 py-2 bg-slate-200 text-slate-700 font-semibold rounded-lg hover:bg-slate-300 transition">
                取消
            </button>
            <button onclick="saveBigAgent()" class="px-6 py-2 bg-gradient-to-r from-yellow-500 to-amber-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
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
    loadBigAgents();
});

function loadStats() {
    fetch('{{ route("admin_api_big_agents_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.stats) {
            document.getElementById('totalBigAgents').textContent = data.stats.total || 0;
            document.getElementById('activeBigAgents').textContent = data.stats.active || 0;
            document.getElementById('totalSubAgents').textContent = data.stats.sub_agents || 0;
            document.getElementById('totalCustomers').textContent = data.stats.customers || 0;
            document.getElementById('monthCommission').textContent = '$' + (data.stats.commission || 0);
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadBigAgents(page = 1) {
    const keyword = document.getElementById('filterKeyword').value;
    const status = document.getElementById('filterStatus').value;
    const sort = document.getElementById('filterSort').value;

    const params = new URLSearchParams({
        page: page,
        keyword: keyword,
        status: status,
        sort: sort
    });

    fetch(`{{ route("admin_api_big_agents_list") }}?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.big_agents) {
            renderBigAgents(data.big_agents);
            renderPagination(data.pagination);
        }
    })
    .catch(err => {
        console.error('Load big agents error:', err);
        document.getElementById('bigAgentsGrid').innerHTML = `
            <div class="col-span-full flex items-center justify-center py-12 text-slate-400">
                <i class="fas fa-exclamation-circle mr-2"></i>加载失败
            </div>
        `;
    });
}

function renderBigAgents(bigAgents) {
    if (bigAgents.length === 0) {
        document.getElementById('bigAgentsGrid').innerHTML = `
            <div class="col-span-full flex items-center justify-center py-12 text-slate-400">
                <i class="fas fa-inbox mr-2"></i>暂无数据
            </div>
        `;
        return;
    }

    const html = bigAgents.map(ba => `
        <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-gradient-to-br from-yellow-500 to-amber-600 rounded-full flex items-center justify-center text-white">
                        <i class="fas fa-crown text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">${ba.username}</h3>
                        <p class="text-xs text-slate-500">${ba.real_name || '-'}</p>
                    </div>
                </div>
                ${getStatusBadge(ba.status)}
            </div>

            <div class="space-y-2 mb-4">
                <p class="text-sm text-slate-600"><i class="fas fa-envelope text-slate-400 mr-2 w-4"></i>${ba.email || '-'}</p>
                <p class="text-sm text-slate-600"><i class="fas fa-phone text-slate-400 mr-2 w-4"></i>${ba.phone || '-'}</p>
                ${ba.company ? `<p class="text-sm text-slate-600"><i class="fas fa-building text-slate-400 mr-2 w-4"></i>${ba.company}</p>` : ''}
                ${ba.region ? `<p class="text-sm text-slate-600"><i class="fas fa-map-marker-alt text-slate-400 mr-2 w-4"></i>${ba.region}</p>` : ''}
            </div>

            <div class="grid grid-cols-2 gap-4 py-4 border-t border-b border-slate-100 mb-4">
                <div>
                    <p class="text-xs text-slate-500 mb-1">下级代理</p>
                    <p class="text-lg font-bold text-purple-600">${ba.sub_agents_count || 0}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 mb-1">客户数</p>
                    <p class="text-lg font-bold text-blue-600">${ba.customers_count || 0}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 mb-1">佣金比例</p>
                    <p class="text-lg font-bold text-orange-600">${ba.commission_rate || 0}%</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 mb-1">本月佣金</p>
                    <p class="text-lg font-bold text-green-600">$${ba.month_commission || 0}</p>
                </div>
            </div>

            ${ba.credit_limit ? `
                <div class="bg-slate-50 rounded-lg p-3 mb-4">
                    <p class="text-xs text-slate-600 mb-1">信用额度</p>
                    <p class="text-sm font-semibold text-slate-800">$${ba.credit_limit.toLocaleString()}</p>
                </div>
            ` : ''}

            <div class="flex gap-2">
                <button onclick="viewBigAgent(${ba.id})" class="flex-1 px-4 py-2 bg-blue-50 text-blue-600 font-semibold rounded-lg hover:bg-blue-100 transition">
                    <i class="fas fa-eye mr-2"></i>查看
                </button>
                <button onclick="editBigAgent(${ba.id})" class="flex-1 px-4 py-2 bg-yellow-50 text-yellow-600 font-semibold rounded-lg hover:bg-yellow-100 transition">
                    <i class="fas fa-edit mr-2"></i>编辑
                </button>
                <button onclick="manageBigAgent(${ba.id})" class="px-4 py-2 bg-purple-50 text-purple-600 font-semibold rounded-lg hover:bg-purple-100 transition">
                    <i class="fas fa-cog"></i>
                </button>
            </div>
        </div>
    `).join('');

    document.getElementById('bigAgentsGrid').innerHTML = html;
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
        html += `<button onclick="loadBigAgents(${currentPage - 1})" class="px-3 py-1 border border-slate-300 rounded-lg hover:bg-slate-50">上一页</button>`;
    }

    for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
        if (i === currentPage) {
            html += `<button class="px-3 py-1 bg-yellow-600 text-white rounded-lg">${i}</button>`;
        } else {
            html += `<button onclick="loadBigAgents(${i})" class="px-3 py-1 border border-slate-300 rounded-lg hover:bg-slate-50">${i}</button>`;
        }
    }

    if (currentPage < totalPages) {
        html += `<button onclick="loadBigAgents(${currentPage + 1})" class="px-3 py-1 border border-slate-300 rounded-lg hover:bg-slate-50">下一页</button>`;
    }

    document.getElementById('pagination').innerHTML = html;
}

function searchBigAgents() {
    loadBigAgents(1);
}

function resetFilters() {
    document.getElementById('filterKeyword').value = '';
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterSort').value = '';
    loadBigAgents(1);
}

function openAddModal() {
    document.getElementById('modalTitle').textContent = '添加大代理';
    document.getElementById('bigAgentId').value = '';
    document.getElementById('bigAgentUsername').value = '';
    document.getElementById('bigAgentEmail').value = '';
    document.getElementById('bigAgentPhone').value = '';
    document.getElementById('bigAgentRealName').value = '';
    document.getElementById('bigAgentCompany').value = '';
    document.getElementById('bigAgentRegion').value = '';
    document.getElementById('bigAgentPassword').value = '';
    document.getElementById('bigAgentPasswordConfirm').value = '';
    document.getElementById('bigAgentCommissionRate').value = '';
    document.getElementById('bigAgentCreditLimit').value = '';
    document.getElementById('bigAgentRemark').value = '';

    document.getElementById('passwordFields').classList.remove('hidden');
    document.getElementById('passwordRequired').classList.remove('hidden');
    document.getElementById('confirmRequired').classList.remove('hidden');

    document.getElementById('bigAgentModal').classList.remove('hidden');
}

function editBigAgent(id) {
    fetch(`{{ route("admin_api_big_agents_detail") }}?id=${id}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.big_agent) {
            const ba = data.big_agent;
            document.getElementById('modalTitle').textContent = '编辑大代理';
            document.getElementById('bigAgentId').value = ba.id;
            document.getElementById('bigAgentUsername').value = ba.username || '';
            document.getElementById('bigAgentEmail').value = ba.email || '';
            document.getElementById('bigAgentPhone').value = ba.phone || '';
            document.getElementById('bigAgentRealName').value = ba.real_name || '';
            document.getElementById('bigAgentCompany').value = ba.company || '';
            document.getElementById('bigAgentRegion').value = ba.region || '';
            document.getElementById('bigAgentCommissionRate').value = ba.commission_rate || '';
            document.getElementById('bigAgentCreditLimit').value = ba.credit_limit || '';
            document.getElementById('bigAgentRemark').value = ba.remark || '';

            document.getElementById('passwordFields').classList.remove('hidden');
            document.getElementById('passwordRequired').classList.add('hidden');
            document.getElementById('confirmRequired').classList.add('hidden');

            document.getElementById('bigAgentModal').classList.remove('hidden');
        }
    })
    .catch(err => console.error('Load big agent detail error:', err));
}

function viewBigAgent(id) {
    window.location.href = `{{ url('/admin-tailwind/big-agents') }}/${id}`;
}

function manageBigAgent(id) {
    window.location.href = `{{ url('/admin-tailwind/big-agents') }}/${id}/manage`;
}

function closeBigAgentModal() {
    document.getElementById('bigAgentModal').classList.add('hidden');
}

function saveBigAgent() {
    const id = document.getElementById('bigAgentId').value;
    const username = document.getElementById('bigAgentUsername').value.trim();
    const email = document.getElementById('bigAgentEmail').value.trim();
    const phone = document.getElementById('bigAgentPhone').value.trim();
    const realName = document.getElementById('bigAgentRealName').value.trim();
    const company = document.getElementById('bigAgentCompany').value.trim();
    const region = document.getElementById('bigAgentRegion').value.trim();
    const password = document.getElementById('bigAgentPassword').value;
    const passwordConfirm = document.getElementById('bigAgentPasswordConfirm').value;
    const commissionRate = document.getElementById('bigAgentCommissionRate').value;
    const creditLimit = document.getElementById('bigAgentCreditLimit').value;
    const remark = document.getElementById('bigAgentRemark').value.trim();

    if (!username) {
        alert('请输入用户名');
        return;
    }

    if (!email) {
        alert('请输入邮箱');
        return;
    }

    if (!phone) {
        alert('请输入手机号');
        return;
    }

    if (!realName) {
        alert('请输入真实姓名');
        return;
    }

    if (!commissionRate) {
        alert('请输入佣金比例');
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
        company: company || undefined,
        region: region || undefined,
        password: password || undefined,
        commission_rate: commissionRate,
        credit_limit: creditLimit || undefined,
        remark: remark
    };

    fetch('{{ route("admin_api_big_agents_save") }}', {
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
            closeBigAgentModal();
            loadStats();
            loadBigAgents(currentPage);
        } else {
            alert(data.message || '保存失败');
        }
    })
    .catch(err => {
        console.error('Save big agent error:', err);
        alert('网络错误，请稍后重试');
    });
}
</script>
@endsection
