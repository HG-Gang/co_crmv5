# UI重写项目 - 组件使用文档

## 概述

本文档介绍新版UI系统中所有可复用Blade组件的使用方法、Props说明和实际案例。

---

## 数据表格组件 (Data Table)

### 基本用法

```blade
<x-data-table
    :columns="[
        ['key' => 'id', 'label' => 'ID', 'type' => 'text'],
        ['key' => 'name', 'label' => '姓名', 'type' => 'text'],
        ['key' => 'status', 'label' => '状态', 'type' => 'badge'],
        ['key' => 'created_at', 'label' => '创建时间', 'type' => 'date']
    ]"
    :data="$users"
    :striped="true"
    :hover="true"
/>
```

### Props说明

| Prop | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| columns | Array | [] | 列定义数组 |
| data | Array | [] | 数据数组 |
| striped | Boolean | true | 是否显示斑马纹 |
| hover | Boolean | true | 是否显示悬停效果 |
| tableClass | String | '' | 自定义表格类名 |

### 列类型支持

- **text**: 普通文本
- **badge**: 徽章样式
- **date**: 日期格式化
- **currency**: 货币格式
- **actions**: 操作按钮列

### 完整示例

```blade
<x-data-table
    :columns="[
        ['key' => 'login', 'label' => '账号', 'type' => 'text'],
        ['key' => 'name', 'label' => '姓名', 'type' => 'text'],
        ['key' => 'balance', 'label' => '余额', 'type' => 'currency'],
        ['key' => 'status', 'label' => '状态', 'type' => 'badge'],
        ['key' => 'actions', 'label' => '操作', 'type' => 'actions']
    ]"
    :data="$users"
    striped
    hover
/>

<script>
function viewUser(id) {
    window.location.href = `/admin-tailwind/users/${id}`;
}

function editUser(id) {
    // 打开编辑模态框
    const modal = new bootstrap.Modal(document.getElementById('editModal'));
    modal.show();
}

function deleteUser(id) {
    if (confirm('确认删除该用户？')) {
        fetch(`/api/admin/users/delete`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ id })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('删除成功');
                window.location.reload();
            }
        });
    }
}
</script>
```

---

## 搜索过滤组件 (Search Filter)

### 基本用法

```blade
<x-search-filter
    :filters="[
        ['key' => 'keyword', 'label' => '关键词', 'type' => 'text', 'placeholder' => '请输入关键词'],
        ['key' => 'status', 'label' => '状态', 'type' => 'select', 'options' => [
            ['value' => '', 'label' => '全部'],
            ['value' => '1', 'label' => '启用'],
            ['value' => '0', 'label' => '禁用']
        ]],
        ['key' => 'date_range', 'label' => '日期范围', 'type' => 'daterange']
    ]"
    searchButtonText="搜索"
    resetButtonText="重置"
    onSearch="handleSearch"
/>
```

### Props说明

| Prop | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| filters | Array | [] | 过滤器配置数组 |
| searchButtonText | String | '搜索' | 搜索按钮文本 |
| resetButtonText | String | '重置' | 重置按钮文本 |
| onSearch | String | 'handleSearch' | 搜索回调函数名 |

### 过滤器类型

- **text**: 文本输入框
- **select**: 下拉选择
- **date**: 日期选择
- **daterange**: 日期范围选择

### 完整示例

```blade
<x-search-filter
    :filters="[
        ['key' => 'login', 'label' => '账号', 'type' => 'text', 'placeholder' => '请输入账号'],
        ['key' => 'group', 'label' => '组别', 'type' => 'select', 'options' => $groups],
        ['key' => 'status', 'label' => '状态', 'type' => 'select', 'options' => [
            ['value' => '', 'label' => '全部'],
            ['value' => 'active', 'label' => '活跃'],
            ['value' => 'inactive', 'label' => '非活跃']
        ]],
        ['key' => 'created_date', 'label' => '注册日期', 'type' => 'daterange']
    ]"
    onSearch="searchUsers"
/>

<script>
function searchUsers() {
    const login = document.getElementById('filter_login').value;
    const group = document.getElementById('filter_group').value;
    const status = document.getElementById('filter_status').value;
    const startDate = document.getElementById('filter_created_date_start').value;
    const endDate = document.getElementById('filter_created_date_end').value;

    const params = new URLSearchParams({
        login,
        group,
        status,
        start_date: startDate,
        end_date: endDate,
        page: 1
    });

    fetch(`/api/admin/users?${params}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // 更新表格数据
                updateTableData(data.data);
            }
        });
}

function resetFilters() {
    document.getElementById('filter_login').value = '';
    document.getElementById('filter_group').value = '';
    document.getElementById('filter_status').value = '';
    document.getElementById('filter_created_date_start').value = '';
    document.getElementById('filter_created_date_end').value = '';
    searchUsers();
}
</script>
```

---

## 分页组件 (Pagination)

### 基本用法

```blade
<x-pagination
    :currentPage="1"
    :lastPage="10"
    :total="200"
    onPageChange="loadData"
    :showInfo="true"
/>
```

### Props说明

| Prop | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| currentPage | Integer | 1 | 当前页码 |
| lastPage | Integer | 1 | 总页数 |
| total | Integer | null | 总记录数 |
| onPageChange | String | 'loadData' | 页码变化回调函数 |
| showInfo | Boolean | true | 是否显示统计信息 |

### 完整示例

```blade
<div id="userTable">
    <x-data-table :columns="$columns" :data="$users" />
</div>

<x-pagination
    :currentPage="$currentPage"
    :lastPage="$lastPage"
    :total="$total"
    onPageChange="loadPage"
    showInfo
/>

<script>
let currentPage = {{ $currentPage }};

function loadPage(page) {
    currentPage = page;
    
    fetch(`/api/admin/users?page=${page}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                updateTable(data.data);
                updatePagination(data.pagination);
            }
        });
}

function updateTable(users) {
    // 更新表格内容
    const tableBody = document.querySelector('#userTable tbody');
    tableBody.innerHTML = users.map(user => `
        <tr>
            <td>${user.id}</td>
            <td>${user.login}</td>
            <td>${user.name}</td>
            <td><span class="badge bg-success">${user.status}</span></td>
        </tr>
    `).join('');
}

function updatePagination(pagination) {
    // 动态更新分页组件
    currentPage = pagination.current_page;
    // 重新渲染分页组件或直接操作DOM
}
</script>
```

---

## 模态框组件 (Modal)

### 基本用法

```blade
<x-modal
    id="editModal"
    title="编辑用户"
    :showFooter="true"
    confirmText="保存"
    cancelText="取消"
    onConfirm="saveUser"
    size="md"
/>
```

### Props说明

| Prop | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| id | String | 必需 | 模态框ID |
| title | String | 必需 | 标题 |
| showFooter | Boolean | true | 是否显示底部按钮 |
| confirmText | String | '确认' | 确认按钮文本 |
| cancelText | String | '取消' | 取消按钮文本 |
| onConfirm | String | null | 确认回调函数 |
| size | String | 'md' | 尺寸 (sm/md/lg/xl) |
| headerClass | String | '' | 自定义头部类名 |

### 完整示例

```blade
<button class="btn btn-primary" onclick="openEditModal(123)">
    编辑用户
</button>

<x-modal
    id="editUserModal"
    title="编辑用户"
    confirmText="保存"
    cancelText="取消"
    onConfirm="saveUser"
    size="lg"
>
    <x-form-input
        id="edit_name"
        label="姓名"
        placeholder="请输入姓名"
        :required="true"
    />
    
    <x-form-input
        id="edit_email"
        label="邮箱"
        type="email"
        placeholder="请输入邮箱"
        :required="true"
    />
    
    <x-form-select
        id="edit_status"
        label="状态"
        :options="[
            ['value' => '1', 'label' => '启用'],
            ['value' => '0', 'label' => '禁用']
        ]"
        :required="true"
    />
</x-modal>

<script>
let currentUserId = null;

function openEditModal(userId) {
    currentUserId = userId;
    
    // 获取用户数据
    fetch(`/api/admin/users/${userId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('edit_name').value = data.data.name;
                document.getElementById('edit_email').value = data.data.email;
                document.getElementById('edit_status').value = data.data.status;
                
                const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
                modal.show();
            }
        });
}

function saveUser() {
    const name = document.getElementById('edit_name').value;
    const email = document.getElementById('edit_email').value;
    const status = document.getElementById('edit_status').value;
    
    if (!name || !email) {
        alert('请填写必填项');
        return;
    }
    
    fetch('/api/admin/users/update', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            id: currentUserId,
            name,
            email,
            status
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('保存成功');
            bootstrap.Modal.getInstance(document.getElementById('editUserModal')).hide();
            window.location.reload();
        } else {
            alert(data.message || '保存失败');
        }
    });
}
</script>
```

---

## 统计卡片组件 (Stats Card)

### 基本用法

```blade
<x-stats-card
    title="总用户数"
    value="1,234"
    icon="cil-user"
    trend="up"
    trendValue="+12.5%"
    color="primary"
    subtitle="较上月增长"
/>
```

### Props说明

| Prop | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| title | String | 必需 | 卡片标题 |
| value | String | 必需 | 统计数值 |
| icon | String | null | CoreUI图标名称 |
| trend | String | null | 趋势 (up/down/flat) |
| trendValue | String | null | 趋势数值 |
| color | String | 'primary' | 主题颜色 |
| subtitle | String | null | 副标题 |

### 完整示例

```blade
<div class="row g-3">
    <div class="col-md-3">
        <x-stats-card
            title="总用户数"
            value="8,524"
            icon="cil-user"
            trend="up"
            trendValue="+8.2%"
            color="primary"
            subtitle="较上月"
        />
    </div>
    
    <div class="col-md-3">
        <x-stats-card
            title="入金总额"
            value="$125,430"
            icon="cil-dollar"
            trend="up"
            trendValue="+15.3%"
            color="success"
            subtitle="本月累计"
        />
    </div>
    
    <div class="col-md-3">
        <x-stats-card
            title="出金总额"
            value="$98,260"
            icon="cil-arrow-thick-from-bottom"
            trend="down"
            trendValue="-5.1%"
            color="warning"
            subtitle="本月累计"
        />
    </div>
    
    <div class="col-md-3">
        <x-stats-card
            title="活跃用户"
            value="6,248"
            icon="cil-chart-line"
            trend="flat"
            trendValue="0.0%"
            color="info"
            subtitle="今日在线"
        />
    </div>
</div>
```

---

## 表单组件集 (Form Components)

### 文本输入框 (Form Input)

```blade
<x-form-input
    id="username"
    label="用户名"
    type="text"
    placeholder="请输入用户名"
    :required="true"
    helpText="用户名长度为3-20个字符"
    :disabled="false"
    :readonly="false"
/>
```

### 下拉选择 (Form Select)

```blade
<x-form-select
    id="user_group"
    label="用户组"
    :options="[
        ['value' => '1', 'label' => 'VIP组'],
        ['value' => '2', 'label' => '普通组'],
        ['value' => '3', 'label' => '测试组']
    ]"
    selected="1"
    :required="true"
    onChange="handleGroupChange"
/>
```

### 文本域 (Form Textarea)

```blade
<x-form-textarea
    id="description"
    label="描述"
    placeholder="请输入描述信息"
    :rows="4"
    :required="false"
    helpText="最多500个字符"
    maxlength="500"
/>
```

### 表单完整示例

```blade
<form id="userForm" onsubmit="submitForm(event)">
    <x-form-input
        id="login"
        label="账号"
        type="text"
        placeholder="请输入账号"
        :required="true"
    />
    
    <x-form-input
        id="name"
        label="姓名"
        type="text"
        placeholder="请输入姓名"
        :required="true"
    />
    
    <x-form-input
        id="email"
        label="邮箱"
        type="email"
        placeholder="请输入邮箱"
        :required="true"
    />
    
    <x-form-select
        id="group"
        label="用户组"
        :options="$groups"
        :required="true"
    />
    
    <x-form-textarea
        id="remark"
        label="备注"
        placeholder="请输入备注信息"
        :rows="3"
    />
    
    <button type="submit" class="btn btn-primary">
        <span class="btn-text">提交</span>
    </button>
</form>

<script>
function submitForm(event) {
    event.preventDefault();
    
    const btn = event.target.querySelector('button[type="submit"]');
    const btnText = btn.querySelector('.btn-text');
    
    // 禁用按钮
    btn.disabled = true;
    btnText.textContent = '提交中...';
    
    const formData = {
        login: document.getElementById('login').value,
        name: document.getElementById('name').value,
        email: document.getElementById('email').value,
        group: document.getElementById('group').value,
        remark: document.getElementById('remark').value
    };
    
    fetch('/api/admin/users/create', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify(formData)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('创建成功');
            window.location.href = '/admin-tailwind/users';
        } else {
            alert(data.message || '创建失败');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        alert('网络错误，请稍后重试');
    })
    .finally(() => {
        btn.disabled = false;
        btnText.textContent = '提交';
    });
}
</script>
```

---

## 加载状态组件 (Loading Spinner)

### 基本用法

```blade
<x-loading-spinner
    text="加载中..."
    size="md"
    color="primary"
    :center="true"
/>
```

### Props说明

| Prop | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| text | String | '加载中...' | 加载文本 |
| size | String | 'md' | 尺寸 (sm/md/lg) |
| color | String | 'primary' | 颜色主题 |
| center | Boolean | true | 是否居中显示 |

### 完整示例

```blade
<div id="dataContainer">
    <x-loading-spinner
        text="正在加载数据..."
        size="lg"
        color="primary"
    />
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadData();
});

function loadData() {
    fetch('/api/admin/users')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // 移除加载状态
                document.getElementById('dataContainer').innerHTML = renderTable(data.data);
            }
        });
}

function renderTable(users) {
    return `
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>账号</th>
                    <th>姓名</th>
                </tr>
            </thead>
            <tbody>
                ${users.map(user => `
                    <tr>
                        <td>${user.id}</td>
                        <td>${user.login}</td>
                        <td>${user.name}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}
</script>
```

---

## 空状态组件 (Empty State)

### 基本用法

```blade
<x-empty-state
    icon="cil-inbox"
    message="暂无数据"
    description="当前没有任何记录"
    buttonText="添加记录"
    buttonLink="/admin-tailwind/users/create"
/>
```

### Props说明

| Prop | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| icon | String | 'cil-inbox' | CoreUI图标 |
| message | String | 必需 | 主要提示信息 |
| description | String | null | 详细描述 |
| buttonText | String | null | 按钮文本 |
| buttonLink | String | null | 按钮链接 |
| buttonOnclick | String | null | 按钮点击事件 |

### 完整示例

```blade
<div id="userList">
    <x-loading-spinner text="加载中..." />
</div>

<script>
function loadUsers() {
    fetch('/api/admin/users')
        .then(res => res.json())
        .then(data => {
            const container = document.getElementById('userList');
            
            if (data.success) {
                if (data.data.length === 0) {
                    // 显示空状态
                    container.innerHTML = `
                        <div class="text-center py-5">
                            <i class="cil-inbox text-body-secondary" style="font-size: 4rem; opacity: 0.3;"></i>
                            <p class="text-body-secondary mt-3 mb-1 fw-semibold">暂无用户数据</p>
                            <p class="text-body-secondary small mb-3">系统中还没有任何用户记录</p>
                            <a href="/admin-tailwind/users/create" class="btn btn-primary btn-sm mt-2">
                                <i class="cil-plus me-2"></i>添加用户
                            </a>
                        </div>
                    `;
                } else {
                    // 渲染数据表格
                    container.innerHTML = renderTable(data.data);
                }
            }
        });
}

document.addEventListener('DOMContentLoaded', loadUsers);
</script>
```

---

## 组件组合示例

### 完整列表页面

```blade
@extends('admin-tailwind.layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>用户管理</h2>
        <button class="btn btn-primary" onclick="openCreateModal()">
            <i class="cil-plus me-2"></i>添加用户
        </button>
    </div>
    
    <!-- 搜索过滤 -->
    <div class="card mb-4">
        <div class="card-body">
            <x-search-filter
                :filters="$filters"
                onSearch="searchUsers"
            />
        </div>
    </div>
    
    <!-- 数据表格 -->
    <div class="card">
        <div class="card-body">
            <div id="tableContainer">
                <x-loading-spinner />
            </div>
        </div>
    </div>
    
    <!-- 分页 -->
    <div class="mt-3" id="paginationContainer"></div>
</div>

<!-- 创建/编辑模态框 -->
<x-modal
    id="userModal"
    title="用户信息"
    onConfirm="saveUser"
    size="lg"
>
    <x-form-input id="modal_login" label="账号" :required="true" />
    <x-form-input id="modal_name" label="姓名" :required="true" />
    <x-form-input id="modal_email" label="邮箱" type="email" :required="true" />
    <x-form-select id="modal_group" label="用户组" :options="$groups" :required="true" />
</x-modal>

<script>
let currentPage = 1;

function searchUsers() {
    currentPage = 1;
    loadUsers();
}

function loadUsers() {
    const params = new URLSearchParams({
        page: currentPage,
        keyword: document.getElementById('filter_keyword')?.value || '',
        status: document.getElementById('filter_status')?.value || ''
    });
    
    fetch(`/api/admin/users?${params}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderTable(data.data);
                renderPagination(data.pagination);
            }
        });
}

function renderTable(users) {
    const container = document.getElementById('tableContainer');
    
    if (users.length === 0) {
        container.innerHTML = `
            <div class="text-center py-5">
                <i class="cil-inbox text-body-secondary" style="font-size: 4rem; opacity: 0.3;"></i>
                <p class="text-body-secondary mt-3">暂无数据</p>
            </div>
        `;
        return;
    }
    
    // 渲染表格内容
    container.innerHTML = `
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>账号</th>
                    <th>姓名</th>
                    <th>邮箱</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                ${users.map(user => `
                    <tr>
                        <td>${user.id}</td>
                        <td>${user.login}</td>
                        <td>${user.name}</td>
                        <td>${user.email}</td>
                        <td><span class="badge bg-success">${user.status}</span></td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="editUser(${user.id})">编辑</button>
                            <button class="btn btn-sm btn-danger" onclick="deleteUser(${user.id})">删除</button>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

function renderPagination(pagination) {
    // 渲染分页组件
    // 省略实现...
}

document.addEventListener('DOMContentLoaded', loadUsers);
</script>
@endsection
```

---

## 最佳实践

### 1. 组件命名规范
- 使用kebab-case: `<x-data-table>`
- Props使用camelCase: `:currentPage="1"`

### 2. 数据传递
- 简单数据直接传递: `title="用户列表"`
- 复杂数据使用冒号绑定: `:data="$users"`

### 3. 事件处理
- 回调函数名使用字符串: `onSearch="handleSearch"`
- 在页面中定义对应的JavaScript函数

### 4. 样式定制
- 使用组件提供的Props控制样式
- 必要时通过自定义类名扩展

### 5. 错误处理
- 始终处理API请求的错误情况
- 提供友好的错误提示信息
