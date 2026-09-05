@extends('admin-tailwind.layouts.app')

@section('title', '风控管理 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">风控管理</h1>
        <p class="text-slate-600 mt-2">实时监控交易风险和异常行为</p>
    </div>
    <div class="flex gap-3">
        <button onclick="refreshData()" class="px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-sync-alt mr-2"></i>刷新
        </button>
        <button onclick="openRuleModal()" class="px-6 py-3 bg-gradient-to-r from-purple-500 to-pink-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
            <i class="fas fa-cog mr-2"></i>规则配置
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
        <p class="text-sm text-slate-600 mb-2">高风险用户</p>
        <p class="text-3xl font-bold text-red-600" id="highRiskCount">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500">
        <p class="text-sm text-slate-600 mb-2">中风险用户</p>
        <p class="text-3xl font-bold text-yellow-600" id="mediumRiskCount">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
        <p class="text-sm text-slate-600 mb-2">今日触发预警</p>
        <p class="text-3xl font-bold text-orange-600" id="todayAlerts">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
        <p class="text-sm text-slate-600 mb-2">异常交易</p>
        <p class="text-3xl font-bold text-purple-600" id="abnormalTrades">0</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <p class="text-sm text-slate-600 mb-2">启用规则数</p>
        <p class="text-3xl font-bold text-blue-600" id="activeRules">0</p>
    </div>
</div>

<!-- Filter Bar -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">搜索</label>
            <input type="text" id="searchKeyword" placeholder="用户名/MT4账号/IP" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">风险等级</label>
            <select id="filterRiskLevel" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部</option>
                <option value="high">高风险</option>
                <option value="medium">中风险</option>
                <option value="low">低风险</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">触发类型</label>
            <select id="filterTriggerType" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部</option>
                <option value="frequent_trade">频繁交易</option>
                <option value="large_amount">大额交易</option>
                <option value="abnormal_profit">异常盈利</option>
                <option value="suspicious_ip">可疑IP</option>
                <option value="multi_account">多账户关联</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">时间范围</label>
            <select id="filterTimeRange" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="today">今天</option>
                <option value="week">最近7天</option>
                <option value="month">最近30天</option>
            </select>
        </div>
        <div class="flex items-end">
            <button onclick="searchRecords()" class="w-full px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-search mr-2"></i>搜索
            </button>
        </div>
    </div>
</div>

<!-- Risk Records Table -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gradient-to-r from-red-500 to-pink-600 text-white">
                <tr>
                    <th class="px-6 py-4 text-left text-sm font-semibold">用户信息</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">MT4账号</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">风险等级</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">触发类型</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">触发详情</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">风险评分</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold">触发时间</th>
                    <th class="px-6 py-4 text-center text-sm font-semibold">状态</th>
                    <th class="px-6 py-4 text-center text-sm font-semibold">操作</th>
                </tr>
            </thead>
            <tbody id="recordsTable">
                <tr>
                    <td colspan="9" class="px-6 py-12 text-center">
                        <i class="fas fa-spinner fa-spin text-3xl text-slate-500 mb-3"></i>
                        <p class="text-slate-600">加载中...</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Detail Modal -->
<div id="detailModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-gradient-to-r from-red-500 to-pink-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-white">风控详情</h3>
            <button onclick="closeDetailModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="detailContent" class="p-6"></div>
    </div>
</div>

<!-- Rule Config Modal -->
<div id="ruleModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-gradient-to-r from-purple-500 to-pink-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-white">风控规则配置</h3>
            <button onclick="closeRuleModal()" class="text-white hover:text-slate-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="ruleContent" class="p-6">
            <div class="space-y-4" id="rulesList">
                <i class="fas fa-spinner fa-spin text-3xl text-slate-500"></i>
            </div>
        </div>
    </div>
</div>

<script>
let autoRefreshInterval = null;

document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadRecords();

    // Auto refresh every 30 seconds
    autoRefreshInterval = setInterval(() => {
        loadStats();
        loadRecords();
    }, 30000);
});

function loadStats() {
    fetch('{{ route("admin_api_risk_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            document.getElementById('highRiskCount').textContent = data.highRisk || 0;
            document.getElementById('mediumRiskCount').textContent = data.mediumRisk || 0;
            document.getElementById('todayAlerts').textContent = data.todayAlerts || 0;
            document.getElementById('abnormalTrades').textContent = data.abnormalTrades || 0;
            document.getElementById('activeRules').textContent = data.activeRules || 0;
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadRecords() {
    const keyword = document.getElementById('searchKeyword').value;
    const riskLevel = document.getElementById('filterRiskLevel').value;
    const triggerType = document.getElementById('filterTriggerType').value;
    const timeRange = document.getElementById('filterTimeRange').value;

    const params = new URLSearchParams({
        keyword: keyword,
        risk_level: riskLevel,
        trigger_type: triggerType,
        time_range: timeRange
    });

    fetch(`{{ route('admin_api_risk_records') }}?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            renderRecords(data.records || []);
        } else {
            showError(data.message || '加载失败');
        }
    })
    .catch(err => {
        console.error('Load error:', err);
        showError('网络错误，请稍后重试');
    });
}

function renderRecords(records) {
    const table = document.getElementById('recordsTable');

    if (records.length === 0) {
        table.innerHTML = `
            <tr>
                <td colspan="9" class="px-6 py-12 text-center">
                    <i class="fas fa-inbox text-5xl text-slate-300 mb-4"></i>
                    <p class="text-slate-600">暂无风控记录</p>
                </td>
            </tr>
        `;
        return;
    }

    table.innerHTML = records.map((r, index) => `
        <tr class="border-b border-slate-200 hover:bg-slate-50 transition ${index % 2 === 0 ? 'bg-white' : 'bg-slate-50'}">
            <td class="px-6 py-4">
                <div>
                    <p class="text-sm font-semibold text-slate-800">${r.username || 'N/A'}</p>
                    <p class="text-xs text-slate-500">${r.email || '-'}</p>
                </div>
            </td>
            <td class="px-6 py-4">
                <span class="text-sm font-mono font-semibold text-slate-800">${r.mt4_account || '-'}</span>
            </td>
            <td class="px-6 py-4">
                ${getRiskLevelBadge(r.risk_level)}
            </td>
            <td class="px-6 py-4">
                ${getTriggerTypeBadge(r.trigger_type)}
            </td>
            <td class="px-6 py-4">
                <p class="text-sm text-slate-600">${r.trigger_detail || '-'}</p>
            </td>
            <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-bold text-slate-800">${r.risk_score || 0}</span>
                    <div class="flex-1 bg-slate-200 rounded-full h-2 max-w-[80px]">
                        <div class="bg-gradient-to-r ${getRiskScoreColor(r.risk_score)} h-2 rounded-full" style="width: ${Math.min(r.risk_score || 0, 100)}%"></div>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4">
                <span class="text-sm text-slate-600">${r.trigger_time || '-'}</span>
            </td>
            <td class="px-6 py-4 text-center">
                ${getStatusBadge(r.status)}
            </td>
            <td class="px-6 py-4">
                <div class="flex items-center justify-center gap-2">
                    <button onclick="viewDetail(${r.id})" class="px-3 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded hover:bg-blue-200 transition">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button onclick="handleRecord(${r.id}, '${r.username}')" class="px-3 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded hover:bg-green-200 transition">
                        <i class="fas fa-check"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function getRiskLevelBadge(level) {
    const badges = {
        'high': '<span class="px-2 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-full"><i class="fas fa-exclamation-triangle mr-1"></i>高风险</span>',
        'medium': '<span class="px-2 py-1 bg-yellow-100 text-yellow-700 text-xs font-semibold rounded-full"><i class="fas fa-exclamation-circle mr-1"></i>中风险</span>',
        'low': '<span class="px-2 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full"><i class="fas fa-check-circle mr-1"></i>低风险</span>'
    };
    return badges[level] || '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">未知</span>';
}

function getTriggerTypeBadge(type) {
    const badges = {
        'frequent_trade': '<span class="px-2 py-1 bg-purple-100 text-purple-700 text-xs font-semibold rounded-full">频繁交易</span>',
        'large_amount': '<span class="px-2 py-1 bg-orange-100 text-orange-700 text-xs font-semibold rounded-full">大额交易</span>',
        'abnormal_profit': '<span class="px-2 py-1 bg-pink-100 text-pink-700 text-xs font-semibold rounded-full">异常盈利</span>',
        'suspicious_ip': '<span class="px-2 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-full">可疑IP</span>',
        'multi_account': '<span class="px-2 py-1 bg-indigo-100 text-indigo-700 text-xs font-semibold rounded-full">多账户关联</span>'
    };
    return badges[type] || '<span class="px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">其他</span>';
}

function getRiskScoreColor(score) {
    if (score >= 80) return 'from-red-500 to-pink-600';
    if (score >= 50) return 'from-yellow-500 to-orange-600';
    return 'from-green-500 to-emerald-600';
}

function getStatusBadge(status) {
    const badges = {
        'pending': '<span class="px-3 py-1 bg-yellow-100 text-yellow-700 text-xs font-semibold rounded-full">待处理</span>',
        'handled': '<span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">已处理</span>',
        'ignored': '<span class="px-3 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">已忽略</span>'
    };
    return badges[status] || '<span class="px-3 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full">未知</span>';
}

function viewDetail(id) {
    fetch(`{{ route('admin_api_risk_detail', ['id' => '__ID__']) }}`.replace('__ID__', id), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.record) {
            const r = data.record;
            const history = data.history || [];

            document.getElementById('detailContent').innerHTML = `
                <div class="mb-6">
                    <h4 class="text-lg font-bold text-slate-800 mb-4">基本信息</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">用户名</p>
                            <p class="text-sm font-semibold text-slate-800">${r.username}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">MT4账号</p>
                            <p class="text-sm font-mono font-semibold text-slate-800">${r.mt4_account}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">风险等级</p>
                            ${getRiskLevelBadge(r.risk_level)}
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">风险评分</p>
                            <p class="text-sm font-bold text-slate-800">${r.risk_score}/100</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">触发类型</p>
                            ${getTriggerTypeBadge(r.trigger_type)}
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">触发时间</p>
                            <p class="text-sm font-semibold text-slate-800">${r.trigger_time}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4 col-span-2">
                            <p class="text-xs text-slate-600 mb-1">触发详情</p>
                            <p class="text-sm text-slate-800">${r.trigger_detail || '-'}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">IP地址</p>
                            <p class="text-sm font-mono font-semibold text-slate-800">${r.ip_address || '-'}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4">
                            <p class="text-xs text-slate-600 mb-1">设备信息</p>
                            <p class="text-sm text-slate-800">${r.device_info || '-'}</p>
                        </div>
                    </div>
                </div>

                <div>
                    <h4 class="text-lg font-bold text-slate-800 mb-4">处理历史 (${history.length})</h4>
                    ${history.length === 0 ? `
                        <div class="text-center py-8 bg-slate-50 rounded-lg">
                            <i class="fas fa-inbox text-3xl text-slate-300 mb-2"></i>
                            <p class="text-slate-600 text-sm">暂无处理记录</p>
                        </div>
                    ` : `
                        <div class="space-y-2 max-h-96 overflow-y-auto">
                            ${history.map(h => `
                                <div class="flex items-start gap-3 p-3 bg-slate-50 rounded-lg">
                                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-${h.action === 'handle' ? 'check' : 'times'} text-blue-600 text-xs"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-semibold text-slate-800">${h.action_name}</p>
                                        <p class="text-xs text-slate-500">${h.remark || ''}</p>
                                        <p class="text-xs text-slate-400 mt-1">操作人：${h.operator} | ${h.created_at}</p>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    `}
                </div>
            `;
            document.getElementById('detailModal').classList.remove('hidden');
        }
    })
    .catch(err => console.error('Load detail error:', err));
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.add('hidden');
}

function handleRecord(id, username) {
    if (!confirm(`确定将用户 "${username}" 的风控记录标记为已处理吗？`)) {
        return;
    }

    fetch(`{{ route('admin_api_risk_handle', ['id' => '__ID__']) }}`.replace('__ID__', id), {
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
            alert('处理成功');
            loadStats();
            loadRecords();
        } else {
            alert(data.message || '操作失败');
        }
    })
    .catch(err => {
        console.error('Handle error:', err);
        alert('网络错误，请稍后重试');
    });
}

function openRuleModal() {
    fetch('{{ route("admin_api_risk_rules") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.rules) {
            renderRules(data.rules);
            document.getElementById('ruleModal').classList.remove('hidden');
        }
    })
    .catch(err => console.error('Load rules error:', err));
}

function renderRules(rules) {
    const container = document.getElementById('rulesList');

    container.innerHTML = rules.map(rule => `
        <div class="bg-slate-50 rounded-lg p-4">
            <div class="flex items-center justify-between mb-2">
                <h5 class="text-sm font-bold text-slate-800">${rule.name}</h5>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" ${rule.enabled ? 'checked' : ''} class="sr-only peer" onchange="toggleRule(${rule.id}, this.checked)">
                    <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
            </div>
            <p class="text-xs text-slate-600 mb-3">${rule.description}</p>
            <div class="grid grid-cols-2 gap-2 text-xs">
                <div class="bg-white rounded p-2">
                    <p class="text-slate-500">阈值</p>
                    <p class="font-semibold text-slate-800">${rule.threshold}</p>
                </div>
                <div class="bg-white rounded p-2">
                    <p class="text-slate-500">风险评分</p>
                    <p class="font-semibold text-slate-800">${rule.risk_score}</p>
                </div>
            </div>
        </div>
    `).join('');
}

function toggleRule(id, enabled) {
    fetch(`{{ route('admin_api_risk_rule_toggle', ['id' => '__ID__']) }}`.replace('__ID__', id), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ enabled: enabled })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success || data.code === 200) {
            loadStats();
        }
    })
    .catch(err => console.error('Toggle rule error:', err));
}

function closeRuleModal() {
    document.getElementById('ruleModal').classList.add('hidden');
}

function refreshData() {
    loadStats();
    loadRecords();
}

function searchRecords() {
    loadRecords();
}

function showError(message) {
    document.getElementById('recordsTable').innerHTML = `
        <tr>
            <td colspan="9" class="px-6 py-12 text-center">
                <i class="fas fa-exclamation-circle text-5xl text-red-500 mb-4"></i>
                <p class="text-red-600">${message}</p>
            </td>
        </tr>
    `;
}
</script>
@endsection
