# CRM权限系统完整实施文档

## 📋 项目概述

本文档记录CRM V5权限系统的完整实施方案，包括RBAC权限模型、数据权限控制、前端UI改进建议等完整内容。

---

## 📁 文件清单

### 1. 核心文件

| 文件路径 | 功能说明 | 状态 |
|---------|---------|------|
| `app/Http/Middleware/CheckPermission.php` | 接口权限验证中间件 | ✅ 已存在 |
| `app/Traits/HasDataScope.php` | 数据权限过滤Trait | ✅ 已创建 |
| `app/Models/DataScope.php` | 数据权限配置模型 | ✅ 已创建 |
| `app/Http/Controllers/Admin/DataScopeController.php` | 数据权限配置控制器 | ✅ 已存在 |
| `app/Http/Controllers/Admin/RoleController.php` | 角色管理控制器 | ✅ 已存在 |
| `app/Http/Controllers/Admin/PermissionController.php` | 权限管理控制器 | ✅ 已存在 |
| `database/权限系统补充SQL.sql` | 数据库补充脚本 | ✅ 已创建 |

### 2. 文档文件

| 文件路径 | 功能说明 | 状态 |
|---------|---------|------|
| `CRM权限系统技术方案.md` | 完整技术方案文档 | ✅ 已创建 |
| `CRM权限系统实施文档.md` | 本文档 | ✅ 已创建 |

---

## 🔧 实施步骤

### 第一步：数据库初始化

1. **执行SQL脚本**
```bash
# 连接数据库
mysql -u root -p hank_zl_data

# 执行补充SQL
source D:\Software\PhpProject\Demo\co_crmv5\database\权限系统补充SQL.sql
```

2. **验证表结构**
```sql
-- 检查 data_scopes 表是否创建成功
DESC data_scopes;

-- 检查示例数据是否插入
SELECT * FROM data_scopes LIMIT 10;

-- 检查权限字典是否补充完整
SELECT * FROM permissions WHERE guard_type='admin' ORDER BY id;
```

### 第二步：注册中间件

**文件：** `app/Http/Kernel.php`

```php
protected $routeMiddleware = [
    // ... 其他中间件
    'check.permission' => \App\Http\Middleware\CheckPermission::class,
];
```

### 第三步：配置路由权限

**文件：** `routes/admin.php`

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\AgentController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\DataScopeController;

// 需要权限验证的路由组
Route::middleware(['auth:admin', 'check.permission:admin'])->group(function () {
    
    // 用户管理
    Route::prefix('users')->name('admin_api_')->group(function () {
        Route::get('/', [UserController::class, 'userList'])->name('userList');
        Route::post('/', [UserController::class, 'createUser'])->name('createUser');
        Route::put('/{id}', [UserController::class, 'updateUser'])->name('updateUser');
        Route::delete('/{id}', [UserController::class, 'deleteUser'])->name('deleteUser');
        Route::get('/{id}', [UserController::class, 'userDetail'])->name('userDetail');
        Route::get('/export', [UserController::class, 'exportUsers'])->name('exportUsers');
    });
    
    // 代理管理
    Route::prefix('agents')->name('admin_api_')->group(function () {
        Route::get('/', [AgentController::class, 'agentList'])->name('agentList');
        Route::post('/', [AgentController::class, 'createAgent'])->name('createAgent');
        Route::put('/{id}', [AgentController::class, 'updateAgent'])->name('updateAgent');
        Route::delete('/{id}', [AgentController::class, 'deleteAgent'])->name('deleteAgent');
        Route::get('/{id}', [AgentController::class, 'agentDetail'])->name('agentDetail');
    });
    
    // 角色管理
    Route::prefix('roles')->name('admin_api_')->group(function () {
        Route::get('/', [RoleController::class, 'roleList'])->name('roleList');
        Route::post('/', [RoleController::class, 'createRole'])->name('createRole');
        Route::put('/{id}', [RoleController::class, 'updateRole'])->name('updateRole');
        Route::delete('/{id}', [RoleController::class, 'deleteRole'])->name('deleteRole');
        Route::post('/assign', [RoleController::class, 'assignPermissions'])->name('assignPermissions');
    });
    
    // 权限管理
    Route::prefix('permissions')->name('admin_api_')->group(function () {
        Route::get('/tree', [PermissionController::class, 'permissionTree'])->name('permissionTree');
        Route::post('/', [PermissionController::class, 'createPermission'])->name('createPermission');
        Route::put('/{id}', [PermissionController::class, 'updatePermission'])->name('updatePermission');
        Route::delete('/{id}', [PermissionController::class, 'deletePermission'])->name('deletePermission');
    });
    
    // 数据权限管理
    Route::prefix('data-scopes')->name('admin_api_')->group(function () {
        Route::get('/', [DataScopeController::class, 'dataScopeList'])->name('dataScopeList');
        Route::post('/', [DataScopeController::class, 'configureDataScope'])->name('configureDataScope');
        Route::post('/batch', [DataScopeController::class, 'batchConfigureDataScope'])->name('batchConfigureDataScope');
        Route::delete('/{id}', [DataScopeController::class, 'deleteDataScope'])->name('deleteDataScope');
        Route::get('/types', [DataScopeController::class, 'dataScopeTypes'])->name('dataScopeTypes');
        Route::get('/resources', [DataScopeController::class, 'resourceList'])->name('resourceList');
    });
});
```

### 第四步：在Model中使用数据权限

**示例：UserInfo Model**

```php
<?php

namespace App\Models;

use App\Traits\HasDataScope;
use Illuminate\Database\Eloquent\Model;

class UserInfo extends Model
{
    use HasDataScope;
    
    protected $table = 'user_infos';
    
    // ... 其他代码
}
```

**在Controller中应用数据权限：**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Models\UserInfo;
use Illuminate\Http\Request;

class UserController extends AdminBaseController
{
    /**
     * 获取用户列表（自动应用数据权限过滤）
     */
    public function userList(Request $request)
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 15);
        
        // 使用 withDataScope 自动过滤数据
        $users = UserInfo::query()
            ->withDataScope('user', 'admin') // 应用数据权限
            ->paginate($perPage, ['*'], 'page', $page);
        
        return $this->success([
            'list' => $users->items(),
            'total' => $users->total(),
        ], '获取用户列表成功');
    }
}
```

### 第五步：前端集成（可选）

根据 `CRM权限系统技术方案.md` 的第五章节，选择合适的UI框架进行前端改造。

**推荐方案：Ant Design Vue + Vue Vben Admin**

---

## 🧪 测试验证

### 1. 权限中间件测试

```bash
# 使用Postman或curl测试接口权限
curl -X GET http://localhost:8000/api/admin/users \
  -H "Authorization: Bearer {token}"

# 预期结果：
# - 超级管理员：返回所有用户
# - 普通角色：返回403或过滤后的数据
```

### 2. 数据权限测试

```php
// 创建测试角色和用户
$role = Role::create([
    'name' => 'test_role',
    'guard_type' => 'admin',
    'status' => 1,
]);

// 配置数据权限：仅本人
DataScope::create([
    'role_id' => $role->id,
    'resource_name' => 'user',
    'scope_type' => 4, // 仅本人
]);

// 创建测试管理员
$admin = Admin::create([
    'username' => 'test_admin',
    'email' => 'test@example.com',
    'password' => bcrypt('123456'),
    'role_id' => $role->id,
]);

// 使用该管理员登录后查询用户列表
// 预期：只能看到自己创建的用户
```

### 3. 菜单权限测试

```bash
# 测试获取菜单接口
curl -X GET http://localhost:8000/api/admin/menus \
  -H "Authorization: Bearer {token}"

# 预期结果：
# - 返回当前用户有权限的菜单树
# - 不同角色返回不同的菜单结构
```

---

## 📊 接口文档

### 1. 登录接口

**接口地址：** `POST /api/admin/login`

**请求参数：**
```json
{
  "email": "admin@example.com",
  "password": "123456"
}
```

**响应示例：**
```json
{
  "code": 200,
  "message": "登录成功",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "user": {
      "id": 1,
      "username": "admin",
      "email": "admin@example.com",
      "role_id": 1
    },
    "permissions": ["user_manage", "user_list", "user_create"],
    "menus": [...]
  }
}
```

### 2. 获取角色列表

**接口地址：** `GET /api/admin/roles`

**请求参数：**
- `page`: 页码（默认1）
- `per_page`: 每页数量（默认15）

**响应示例：**
```json
{
  "code": 200,
  "message": "获取成功",
  "data": {
    "list": [
      {
        "id": 1,
        "name": "超级管理员",
        "guard_type": "admin",
        "status": 1,
        "permission_ids": [1,2,3,4,5]
      }
    ],
    "total": 1
  }
}
```

### 3. 配置数据权限

**接口地址：** `POST /api/admin/data-scopes`

**请求参数：**
```json
{
  "role_id": 2,
  "resource_name": "user",
  "scope_type": 2,
  "scope_rule": null
}
```

**响应示例：**
```json
{
  "code": 200,
  "message": "数据权限配置成功",
  "data": {
    "id": 1,
    "role_id": 2,
    "resource_name": "user",
    "scope_type": 2,
    "scope_rule": null
  }
}
```

### 4. 获取数据权限类型

**接口地址：** `GET /api/admin/data-scopes/types`

**响应示例：**
```json
{
  "code": 200,
  "message": "获取成功",
  "data": [
    {
      "value": 1,
      "label": "全部数据",
      "description": "可以查看所有数据，不受限制"
    },
    {
      "value": 2,
      "label": "本级及下级",
      "description": "可以查看自己及所有下级的数据"
    }
  ]
}
```

---

## 🐛 常见问题

### Q1: 中间件报错 "未登录或登录已过期"

**解决方案：**
1. 检查JWT Token是否正确传递
2. 检查Token是否过期
3. 检查guard类型是否匹配（admin/front）

### Q2: 数据权限不生效

**解决方案：**
1. 检查Model是否use了HasDataScope Trait
2. 检查查询时是否调用了withDataScope()方法
3. 检查data_scopes表中是否有对应配置
4. 检查scope_type是否正确

### Q3: 权限验证总是返回403

**解决方案：**
1. 检查permissions表中api_route是否正确配置
2. 检查role_permissions表中是否有授权记录
3. 检查路由名称是否与api_route匹配
4. 检查用户的role_id是否正确

### Q4: 菜单不显示

**解决方案：**
1. 检查menus表中permission_id是否正确关联
2. 检查permissions表中status是否为1
3. 检查role_permissions是否有对应权限
4. 检查前端权限指令是否正确实现

---

## 📈 性能优化建议

### 1. 使用缓存

```php
// 缓存角色权限，避免频繁查询数据库
$permissions = Cache::remember("role_{$roleId}_permissions", 3600, function () use ($roleId) {
    return Role::find($roleId)->permissions()->pluck('slug')->toArray();
});
```

### 2. 预加载关联关系

```php
// 避免N+1查询问题
$users = UserInfo::query()
    ->with(['role', 'role.permissions'])
    ->withDataScope('user', 'admin')
    ->paginate(15);
```

### 3. 索引优化

```sql
-- 为常用查询字段添加索引
ALTER TABLE `permissions` ADD INDEX `idx_api_route` (`api_route`);
ALTER TABLE `permissions` ADD INDEX `idx_guard_status` (`guard_type`, `status`);
ALTER TABLE `role_permissions` ADD INDEX `idx_role_permission` (`role_id`, `permission_id`);
```

---

## 🔒 安全建议

### 1. Token安全
- ✅ JWT过期时间设置为2小时
- ✅ 实现Token刷新机制
- ✅ 支持单点登录（踢出旧Token）
- ✅ 使用HTTPS传输Token

### 2. 接口安全
- ✅ 所有接口必须经过权限中间件
- ✅ 敏感操作二次验证密码
- ✅ 记录操作日志
- ✅ IP白名单限制

### 3. 数据安全
- ✅ 严格数据权限过滤
- ✅ 防止越权访问
- ✅ 敏感数据脱敏
- ✅ 定期权限审计

---

## 📝 更新日志

### 2026-06-13
- ✅ 完成权限系统技术方案设计
- ✅ 创建HasDataScope Trait
- ✅ 创建DataScope Model
- ✅ 创建数据库补充SQL脚本
- ✅ 编写完整实施文档
- ✅ 提供接口文档和测试用例

---

## 📞 技术支持

如有疑问，请参考：
1. `CRM权限系统技术方案.md` - 详细技术方案
2. Laravel官方文档：https://laravel.com/docs
3. 项目源代码注释

---

**文档版本：** V1.0  
**最后更新：** 2026-06-13  
**维护人员：** CRM开发团队
