@extends('admin-tailwind.layouts.app')

@section('title', '数据范围 - 管理后台')

@section('content')
<!-- Page Header -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold text-slate-800">数据范围</h1>
        <p class="text-slate-600 mt-2">配置角色的数据访问权限范围</p>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">总配置数</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="totalScopes">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-database"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">全部数据</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="allDataScopes">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-globe"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">部门数据</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="deptScopes">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-pink-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-building"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-slate-600 text-sm font-semibold">个人数据</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2" id="selfScopes">0</h3>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-red-600 rounded-full flex items-center justify-center text-white">
                <i class="fas fa-user"></i>
            </div>
        </div>
    </div>
</div>

<!-- Data Scopes Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6" id="scopesGrid">
    <div class="col-span-full flex items-center justify-center py-12 text-slate-400">
        <i class="fas fa-spinner fa-spin mr-2"></i>加载中...
    </div>
</div>

<!-- Config Modal -->
<div id="configModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-bold text-slate-800">
                <i class="fas fa-cog text-blue-600 mr-2"></i>配置数据范围 - <span id="configRoleName"></span>
            </h3>
            <button onclick="closeConfigModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <input type="hidden" id="configRoleId">

            <div class="mb-6">
                <label class="block text-sm font-semibold text-slate-700 mb-3">数据权限范围 <span class="text-red-500">*</span></label>
                <div class="space-y-3">
                    <label class="flex items-start gap-3 p-4 border border-slate-300 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-500 transition">
                        <input type="radio" name="scopeType" value="all" class="mt-1 w-4 h-4 text-blue-600">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">全部数据权限</p>
                            <p class="text-xs text-slate-500 mt-1">可以访问系统中的所有数据</p>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-4 border border-slate-300 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-500 transition">
                        <input type="radio" name="scopeType" value="custom" class="mt-1 w-4 h-4 text-blue-600">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">自定义数据权限</p>
                            <p class="text-xs text-slate-500 mt-1">根据指定条件访问数据</p>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-4 border border-slate-300 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-500 transition">
                        <input type="radio" name="scopeType" value="dept" class="mt-1 w-4 h-4 text-blue-600">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">本部门数据权限</p>
                            <p class="text-xs text-slate-500 mt-1">只能访问本部门及下属部门的数据</p>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-4 border border-slate-300 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-500 transition">
                        <input type="radio" name="scopeType" value="dept_self" class="mt-1 w-4 h-4 text-blue-600">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">本部门数据权限（不含下属）</p>
                            <p class="text-xs text-slate-500 mt-1">只能访问本部门的数据，不包括下属部门</p>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-4 border border-slate-300 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-500 transition">
                        <input type="radio" name="scopeType" value="self" class="mt-1 w-4 h-4 text-blue-600">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">仅本人数据权限</p>
                            <p class="text-xs text-slate-500 mt-1">只能访问本人创建的数据</p>
                        </div>
                    </label>
                </div>
            </div>

            <div id="customScopeFields" class="hidden">
                <div class="bg-slate-50 rounded-lg p-4 mb-4">
                    <h4 class="text-sm font-semibold text-slate-800 mb-3">自定义条件</h4>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">指定部门</label>
                            <select id="customDepts" multiple class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" size="5">
                                <option value="1">技术部</option>
                                <option value="2">市场部</option>
                                <option value="3">财务部</option>
                                <option value="4">人事部</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">指定用户</label>
                            <input type="text" id="customUsers" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" placeholder="用户ID，多个用逗号分隔">
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                <div class="flex items-start gap-2">
                    <i class="fas fa-info-circle text-amber-600 mt-1"></i>
                    <div class="text-sm text-amber-800">
                        <p class="font-semibold mb-1">说明</p>
                        <ul class="list-disc list-inside space-y-1 text-xs">
                            <li>数据范围配置后立即生效，请谨慎操作</li>
                            <li>权限范围从大到小依次为：全部 > 自定义 > 本部门 > 本部门（不含下属）> 仅本人</li>
                            <li>自定义权限可以指定特定的部门或用户</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="sticky bottom-0 bg-slate-50 px-6 py-4 flex justify-end gap-3 border-t border-slate-200">
            <button onclick="closeConfigModal()" class="px-6 py-2 bg-slate-200 text-slate-700 font-semibold rounded-lg hover:bg-slate-300 transition">
                取消
            </button>
            <button onclick="saveConfig()" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-save mr-2"></i>保存配置
            </button>
        </div>
    </div>
</div>

<script>
let currentRoleId = null;

document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadScopes();

    // Listen to scope type changes
    document.querySelectorAll('input[name="scopeType"]').forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'custom') {
                document.getElementById('customScopeFields').classList.remove('hidden');
            } else {
                document.getElementById('customScopeFields').classList.add('hidden');
            }
        });
    });
});

function loadStats() {
    fetch('{{ route("admin_api_data_scopes_stats") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.stats) {
            document.getElementById('totalScopes').textContent = data.stats.total || 0;
            document.getElementById('allDataScopes').textContent = data.stats.all || 0;
            document.getElementById('deptScopes').textContent = data.stats.dept || 0;
            document.getElementById('selfScopes').textContent = data.stats.self || 0;
        }
    })
    .catch(err => console.error('Load stats error:', err));
}

function loadScopes() {
    fetch('{{ route("admin_api_data_scopes_list") }}', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.scopes) {
            renderScopes(data.scopes);
        }
    })
    .catch(err => {
        console.error('Load scopes error:', err);
        document.getElementById('scopesGrid').innerHTML = `
            <div class="col-span-full flex items-center justify-center py-12 text-slate-400">
                <i class="fas fa-exclamation-circle mr-2"></i>加载失败
            </div>
        `;
    });
}

function renderScopes(scopes) {
    if (scopes.length === 0) {
        document.getElementById('scopesGrid').innerHTML = `
            <div class="col-span-full flex items-center justify-center py-12 text-slate-400">
                <i class="fas fa-inbox mr-2"></i>暂无数据
            </div>
        `;
        return;
    }

    const html = scopes.map(s => `
        <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">${s.role_name}</h3>
                        <p class="text-xs text-slate-500">${s.role_slug || '-'}</p>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                ${getScopeTypeBadge(s.scope_type)}
            </div>

            <div class="py-3 border-t border-b border-slate-100 mb-4">
                <p class="text-xs text-slate-600">${getScopeDescription(s.scope_type)}</p>
                ${s.scope_type === 'custom' && s.custom_config ? `
                    <div class="mt-2 text-xs text-slate-500">
                        ${s.custom_config.depts ? `<p>部门: ${s.custom_config.depts.join(', ')}</p>` : ''}
                        ${s.custom_config.users ? `<p>用户: ${s.custom_config.users}</p>` : ''}
                    </div>
                ` : ''}
            </div>

            <div class="flex items-center justify-between text-xs text-slate-500 mb-4">
                <span><i class="fas fa-users mr-1"></i>关联用户: ${s.users_count || 0}</span>
                <span><i class="fas fa-clock mr-1"></i>${s.updated_at || '-'}</span>
            </div>

            <button onclick="configScope(${s.role_id}, '${s.role_name}', '${s.scope_type}')" class="w-full px-4 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg hover:shadow-lg transition">
                <i class="fas fa-cog mr-2"></i>配置权限
            </button>
        </div>
    `).join('');

    document.getElementById('scopesGrid').innerHTML = html;
}

function getScopeTypeBadge(type) {
    const badges = {
        'all': '<span class="px-3 py-1 bg-green-100 text-green-700 text-sm font-semibold rounded-full"><i class="fas fa-globe mr-1"></i>全部数据</span>',
        'custom': '<span class="px-3 py-1 bg-blue-100 text-blue-700 text-sm font-semibold rounded-full"><i class="fas fa-sliders-h mr-1"></i>自定义</span>',
        'dept': '<span class="px-3 py-1 bg-purple-100 text-purple-700 text-sm font-semibold rounded-full"><i class="fas fa-building mr-1"></i>本部门</span>',
        'dept_self': '<span class="px-3 py-1 bg-indigo-100 text-indigo-700 text-sm font-semibold rounded-full"><i class="fas fa-building mr-1"></i>本部门（不含下属）</span>',
        'self': '<span class="px-3 py-1 bg-orange-100 text-orange-700 text-sm font-semibold rounded-full"><i class="fas fa-user mr-1"></i>仅本人</span>'
    };
    return badges[type] || '<span class="px-3 py-1 bg-slate-100 text-slate-700 text-sm font-semibold rounded-full">未配置</span>';
}

function getScopeDescription(type) {
    const descriptions = {
        'all': '可以访问系统中的所有数据，适用于超级管理员角色',
        'custom': '根据自定义条件访问数据，可以指定特定的部门或用户',
        'dept': '只能访问本部门及下属部门的数据',
        'dept_self': '只能访问本部门的数据，不包括下属部门',
        'self': '只能访问本人创建的数据'
    };
    return descriptions[type] || '未配置数据权限';
}

function configScope(roleId, roleName, currentType) {
    currentRoleId = roleId;
    document.getElementById('configRoleName').textContent = roleName;
    document.getElementById('configRoleId').value = roleId;

    // Reset form
    document.querySelectorAll('input[name="scopeType"]').forEach(r => r.checked = false);
    document.getElementById('customScopeFields').classList.add('hidden');

    // Set current value
    const radio = document.querySelector(`input[name="scopeType"][value="${currentType}"]`);
    if (radio) {
        radio.checked = true;
        if (currentType === 'custom') {
            document.getElementById('customScopeFields').classList.remove('hidden');
        }
    }

    document.getElementById('configModal').classList.remove('hidden');
}

function closeConfigModal() {
    document.getElementById('configModal').classList.add('hidden');
    currentRoleId = null;
}

function saveConfig() {
    const roleId = document.getElementById('configRoleId').value;
    const scopeType = document.querySelector('input[name="scopeType"]:checked');

    if (!scopeType) {
        alert('请选择数据权限范围');
        return;
    }

    const data = {
        role_id: roleId,
        scope_type: scopeType.value
    };

    if (scopeType.value === 'custom') {
        const depts = Array.from(document.getElementById('customDepts').selectedOptions).map(o => o.value);
        const users = document.getElementById('customUsers').value.trim();

        data.custom_config = {
            depts: depts,
            users: users
        };
    }

    fetch('{{ route("admin_api_data_scopes_update") }}', {
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
            alert('配置已保存');
            closeConfigModal();
            loadStats();
            loadScopes();
        } else {
            alert(data.message || '保存失败');
        }
    })
    .catch(err => {
        console.error('Save config error:', err);
        alert('网络错误，请稍后重试');
    });
}
</script>
@endsection
