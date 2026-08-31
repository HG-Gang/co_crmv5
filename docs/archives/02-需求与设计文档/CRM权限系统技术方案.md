# CRM权限系统技术方案

## 一、现状分析

### 1.1 数据库表结构
根据分析，系统已具备完整的RBAC权限表结构：

**核心权限表：**
- `admins` - 后台管理员表
- `roles` - 角色表（支持 guard_type 区分前后台）
- `permissions` - 权限字典表（菜单、按钮、接口权限）
- `role_permissions` - 角色权限关联表
- `menus` - 菜单表

**用户体系表：**
- `user_logins` - 用户登录表（区分代理/客户）
- `user_infos` - 用户详细信息表
- `agent_descendants` - 代理层级关系表
- `agent_levels` - 代理等级表

### 1.2 已实现的功能
- ✅ 基础RBAC权限模型
- ✅ 角色管理Controller（RoleController）
- ✅ 权限管理Controller（PermissionController）
- ✅ 菜单管理Controller（MenuController）
- ✅ 管理员登录Controller（AdminAuthController）

### 1.3 需要补充的功能
- ❌ 权限验证中间件
- ❌ 数据权限过滤（不同角色看到不同数据范围）
- ❌ 按钮权限控制
- ❌ 前端权限指令/组件

---

## 二、RBAC权限方案设计

### 2.1 权限模型架构

```
用户(Admin/User) → 角色(Role) → 权限(Permission) → 资源(Menu/API/Button/Data)
                      ↓
                  数据范围(Data Scope)
```

### 2.2 权限类型划分

#### 2.2.1 菜单权限（Menu Permission）
- **作用范围：** 控制左侧导航菜单显示
- **数据来源：** `menus` 表 + `permissions` 表
- **实现方式：** 后端返回当前用户有权限的菜单树

#### 2.2.2 按钮权限（Button Permission）
- **作用范围：** 控制页面内按钮/操作显示
- **数据来源：** `permissions` 表（type=3）
- **实现方式：** 
  - 后端：返回用户权限slug数组
  - 前端：使用 v-permission 指令或权限判断函数

#### 2.2.3 接口权限（API Permission）
- **作用范围：** 控制API接口访问
- **数据来源：** `permissions.api_route` 字段
- **实现方式：** 中间件拦截路由，校验用户权限

#### 2.2.4 数据权限（Data Scope）
- **作用范围：** 控制数据可见范围
- **数据来源：** 业务规则配置
- **权限级别：**
  - **全部数据权限** - 超级管理员
  - **本级及下级数据** - 普通管理员
  - **本级数据** - 代理查看自己的数据
  - **仅本人数据** - 客服只能看自己创建的

---

## 三、权限方案详细设计

### 3.1 数据库设计补充

现有表结构已经完整，建议新增一个数据权限配置表：

```sql
CREATE TABLE `data_scopes` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `role_id` bigint(20) NOT NULL COMMENT '角色ID',
  `resource_name` varchar(50) NOT NULL COMMENT '资源名称：user、agent、order等',
  `scope_type` tinyint(4) NOT NULL DEFAULT 1 COMMENT '数据范围类型：1=全部 2=本级及下级 3=仅本级 4=仅本人 5=自定义',
  `scope_rule` json NULL COMMENT '自定义规则JSON',
  `created_at` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` int(10) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_resource_unique` (`role_id`, `resource_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='数据权限范围配置表';
```

### 3.2 权限验证流程

#### 3.2.1 登录流程
```
1. 用户提交账号密码
2. 验证账号密码正确性
3. 查询用户角色（admins.role_id）
4. 查询角色权限（role_permissions → permissions）
5. 生成JWT Token，包含：
   - user_id
   - role_id
   - permissions（权限slug数组）
   - data_scopes（数据权限配置）
6. 返回Token和用户信息
```

#### 3.2.2 接口访问流程
```
1. 请求携带JWT Token
2. 中间件验证Token有效性
3. 中间件提取当前路由名称
4. 查询 permissions 表，匹配 api_route
5. 检查用户权限列表是否包含该权限
6. 通过则放行，否则返回403
```

#### 3.2.3 数据过滤流程
```
1. 控制器获取当前用户数据权限配置
2. 根据 scope_type 构建查询条件：
   - 全部：无过滤
   - 本级及下级：parent_id IN (user_id, descendants)
   - 仅本级：parent_id = user_id
   - 仅本人：created_by = user_id
3. 应用到查询构建器
4. 返回过滤后的数据
```

---

## 四、实现方案

### 4.1 中间件实现

#### CheckPermission 中间件
```php
// app/Http/Middleware/CheckPermission.php
<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Permission;

/**
 * 权限验证中间件
 * 功能：验证当前用户是否有权限访问该API路由
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, $guard = 'admin')
    {
        // 获取当前用户
        $user = Auth::guard($guard)->user();
        if (!$user) {
            return response()->json(['code' => 401, 'message' => '未登录'], 401);
        }

        // 获取当前路由名称
        $routeName = $request->route()->getName();
        
        // 超级管理员跳过权限检查
        if ($user->role_id === 1) {
            return $next($request);
        }

        // 查询该路由需要的权限
        $permission = Permission::where('api_route', $routeName)
            ->where('guard_type', $guard)
            ->where('status', 1)
            ->first();

        // 该路由未配置权限，默认允许访问
        if (!$permission) {
            return $next($request);
        }

        // 检查用户角色是否拥有该权限
        $hasPermission = $user->role->permissions()->where('permissions.id', $permission->id)->exists();
        
        if (!$hasPermission) {
            return response()->json(['code' => 403, 'message' => '无权限访问'], 403);
        }

        return $next($request);
    }
}
```

### 4.2 数据权限Trait

```php
// app/Traits/HasDataScope.php
<?php
namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * 数据权限过滤Trait
 * 功能：根据用户角色自动过滤数据范围
 */
trait HasDataScope
{
    /**
     * 应用数据权限过滤
     * 
     * @param Builder $query 查询构建器
     * @param string $resource 资源名称
     * @param string $guard 守卫类型
     * @return Builder
     */
    public function scopeWithDataScope(Builder $query, string $resource = 'user', string $guard = 'admin')
    {
        $user = Auth::guard($guard)->user();
        if (!$user) {
            return $query->whereRaw('1 = 0'); // 未登录返回空
        }

        // 超级管理员查看全部
        if ($user->role_id === 1) {
            return $query;
        }

        // 获取角色的数据权限配置
        $dataScope = $user->role->dataScopes()->where('resource_name', $resource)->first();
        
        if (!$dataScope) {
            // 未配置数据权限，默认只能看自己创建的
            return $query->where('created_by', $user->id);
        }

        switch ($dataScope->scope_type) {
            case 1: // 全部数据
                return $query;
            
            case 2: // 本级及下级
                // 代理场景：可以查看自己及下级代理的数据
                if ($resource === 'agent' || $resource === 'user') {
                    $descendants = $this->getDescendantIds($user->id);
                    return $query->whereIn('parent_id', array_merge([$user->id], $descendants));
                }
                return $query->where('created_by', $user->id);
            
            case 3: // 仅本级
                return $query->where('parent_id', $user->id);
            
            case 4: // 仅本人
                return $query->where('created_by', $user->id);
            
            case 5: // 自定义规则
                return $this->applyCustomScope($query, $dataScope->scope_rule);
            
            default:
                return $query->where('created_by', $user->id);
        }
    }

    /**
     * 获取下级代理ID列表
     */
    private function getDescendantIds($userId)
    {
        return \App\Models\AgentDescendant::where('agent_id', $userId)
            ->pluck('descendant_id')
            ->toArray();
    }

    /**
     * 应用自定义数据权限规则
     */
    private function applyCustomScope(Builder $query, $rules)
    {
        // 根据自定义JSON规则构建查询条件
        // 示例：{"field": "department_id", "value": [1,2,3]}
        if (is_string($rules)) {
            $rules = json_decode($rules, true);
        }
        
        foreach ($rules as $rule) {
            $query->whereIn($rule['field'], $rule['value']);
        }
        
        return $query;
    }
}
```

---

## 五、前端UI改进建议

### 5.1 当前UI问题分析
- Layui: 界面风格陈旧，组件功能有限
- Naive UI: 现代化，但需要Vue3，学习成本高

### 5.2 推荐UI方案

#### 方案一：Ant Design Vue（推荐）⭐⭐⭐⭐⭐
**优点：**
- 企业级UI设计规范，专业美观
- 组件丰富完整（200+组件）
- 中文文档完善，社区活跃
- 支持Vue 2/3，兼容性好
- 内置权限指令 v-auth

**参考案例：**
- 阿里云控制台
- 钉钉管理后台

#### 方案二：Element Plus（稳妥选择）⭐⭐⭐⭐
**优点：**
- 国内使用最广泛，生态成熟
- 中文文档详细，上手快
- 组件稳定，bug少
- 大量开源后台模板可参考

**参考案例：**
- vue-element-admin
- RuoYi-Vue

#### 方案三：Arco Design（新锐之选）⭐⭐⭐⭐
**优点：**
- 字节跳动出品，设计精美
- 性能优秀，体积小
- 暗黑模式支持好
- 组件动画流畅

**参考案例：**
- 飞书管理后台

### 5.3 推荐开源模板

#### 1. Vue Vben Admin（基于Ant Design Vue）
```
https://github.com/vbenjs/vue-vben-admin
```
- ✅ 完整的权限系统
- ✅ 动态路由
- ✅ 多标签页
- ✅ TypeScript支持
- ✅ 响应式布局

#### 2. Soybean Admin（基于Naive UI）
```
https://github.com/soybeanjs/soybean-admin
```
- ✅ 现代化设计
- ✅ 清爽简洁
- ✅ 性能优秀
- ✅ 适合CRM后台

#### 3. Geeker Admin（基于Element Plus）
```
https://github.com/HalseySpicy/Geeker-Admin
```
- ✅ 代码规范
- ✅ 上手简单
- ✅ 适合快速开发

### 5.4 布局改进建议

**推荐布局结构：**
```
┌─────────────────────────────────────────┐
│  Logo     导航菜单                  用户  │ ← 顶部导航栏
├─────────┬───────────────────────────────┤
│         │  面包屑                        │
│         ├───────────────────────────────┤
│  侧边   │                               │
│  菜单   │      内容区域                  │
│  树     │                               │
│         │                               │
└─────────┴───────────────────────────────┘
```

**配色方案建议：**
- **主色调：** #1890ff（专业蓝）
- **成功色：** #52c41a（绿色）
- **警告色：** #faad14（橙色）
- **危险色：** #f5222d（红色）
- **文字色：** #000000d9（深灰）
- **背景色：** #f0f2f5（浅灰）

---

## 六、接口权限配置示例

### 6.1 权限字典配置

```sql
-- 用户管理模块
INSERT INTO `permissions` VALUES 
(1, '用户管理', 'user_manage', 'admin', 0, 1, 'UserOutlined', 100, '/user', NULL, 1),
(2, '用户列表', 'user_list', 'admin', 1, 2, NULL, 101, '/user/list', 'admin_api_userList', 1),
(3, '添加用户', 'user_create', 'admin', 1, 3, NULL, 102, NULL, 'admin_api_createUser', 1),
(4, '编辑用户', 'user_edit', 'admin', 1, 3, NULL, 103, NULL, 'admin_api_updateUser', 1),
(5, '删除用户', 'user_delete', 'admin', 1, 3, NULL, 104, NULL, 'admin_api_deleteUser', 1),
(6, '导出用户', 'user_export', 'admin', 1, 3, NULL, 105, NULL, 'admin_api_exportUsers', 1);

-- 角色管理模块
INSERT INTO `permissions` VALUES 
(10, '角色管理', 'role_manage', 'admin', 0, 1, 'TeamOutlined', 200, '/role', NULL, 1),
(11, '角色列表', 'role_list', 'admin', 10, 2, NULL, 201, '/role/list', 'admin_api_roleList', 1),
(12, '添加角色', 'role_create', 'admin', 10, 3, NULL, 202, NULL, 'admin_api_createRole', 1),
(13, '编辑角色', 'role_edit', 'admin', 10, 3, NULL, 203, NULL, 'admin_api_updateRole', 1),
(14, '删除角色', 'role_delete', 'admin', 10, 3, NULL, 204, NULL, 'admin_api_deleteRole', 1),
(15, '分配权限', 'role_assign', 'admin', 10, 3, NULL, 205, NULL, 'admin_api_assignPermissions', 1);
```

### 6.2 路由配置示例

```php
// routes/admin.php

// 需要权限验证的路由
Route::middleware(['auth:admin', 'check.permission:admin'])->group(function () {
    
    // 用户管理
    Route::name('admin_api_')->prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'userList'])->name('userList');
        Route::post('/', [UserController::class, 'createUser'])->name('createUser');
        Route::put('/{id}', [UserController::class, 'updateUser'])->name('updateUser');
        Route::delete('/{id}', [UserController::class, 'deleteUser'])->name('deleteUser');
        Route::get('/export', [UserController::class, 'exportUsers'])->name('exportUsers');
    });
    
    // 角色管理
    Route::name('admin_api_')->prefix('roles')->group(function () {
        Route::get('/', [RoleController::class, 'roleList'])->name('roleList');
        Route::post('/', [RoleController::class, 'createRole'])->name('createRole');
        Route::put('/{id}', [RoleController::class, 'updateRole'])->name('updateRole');
        Route::delete('/{id}', [RoleController::class, 'deleteRole'])->name('deleteRole');
        Route::post('/assign', [RoleController::class, 'assignPermissions'])->name('assignPermissions');
    });
});
```

---

## 七、前端权限控制

### 7.1 权限指令（Vue 3）

```javascript
// src/directives/permission.js

/**
 * 权限指令 v-permission
 * 用法：<el-button v-permission="'user_create'">添加</el-button>
 */
export default {
  mounted(el, binding) {
    const { value } = binding;
    const permissions = store.getters.permissions || [];
    
    if (value) {
      const hasPermission = permissions.includes(value);
      if (!hasPermission) {
        el.parentNode && el.parentNode.removeChild(el);
      }
    }
  }
};
```

### 7.2 权限判断函数

```javascript
// src/utils/permission.js

/**
 * 检查是否有权限
 * @param {string|array} permission 权限slug
 * @returns {boolean}
 */
export function hasPermission(permission) {
  const permissions = store.getters.permissions || [];
  
  if (Array.isArray(permission)) {
    return permission.some(p => permissions.includes(p));
  }
  
  return permissions.includes(permission);
}

/**
 * 检查是否有任一权限
 */
export function hasAnyPermission(permissionArray) {
  return permissionArray.some(p => hasPermission(p));
}

/**
 * 检查是否有全部权限
 */
export function hasAllPermissions(permissionArray) {
  return permissionArray.every(p => hasPermission(p));
}
```

### 7.3 路由守卫

```javascript
// src/router/permission.js

router.beforeEach(async (to, from, next) => {
  const token = getToken();
  
  if (token) {
    if (to.path === '/login') {
      next({ path: '/' });
    } else {
      const hasPermissions = store.getters.permissions && store.getters.permissions.length > 0;
      
      if (hasPermissions) {
        next();
      } else {
        try {
          // 获取用户信息和权限
          await store.dispatch('user/getInfo');
          
          // 根据权限生成动态路由
          const accessRoutes = await store.dispatch('permission/generateRoutes');
          
          // 动态添加路由
          accessRoutes.forEach(route => {
            router.addRoute(route);
          });
          
          next({ ...to, replace: true });
        } catch (error) {
          await store.dispatch('user/logout');
          next(`/login?redirect=${to.path}`);
        }
      }
    }
  } else {
    if (whiteList.indexOf(to.path) !== -1) {
      next();
    } else {
      next(`/login?redirect=${to.path}`);
    }
  }
});
```

---

## 八、完整接口文档

### 8.1 登录接口

**接口地址：** `POST /api/admin/login`

**请求参数：**
```json
{
  "email": "admin@example.com",
  "password": "123456"
}
```

**响应数据：**
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
      "role_id": 1,
      "role_name": "超级管理员"
    },
    "permissions": [
      "user_manage", "user_list", "user_create", "user_edit", "user_delete",
      "role_manage", "role_list", "role_create", "role_edit", "role_delete"
    ],
    "menus": [
      {
        "id": 1,
        "title": "用户管理",
        "path": "/user",
        "icon": "UserOutlined",
        "children": [
          {
            "id": 2,
            "title": "用户列表",
            "path": "/user/list"
          }
        ]
      }
    ]
  }
}
```

### 8.2 获取用户信息

**接口地址：** `GET /api/admin/user/info`

**请求头：**
```
Authorization: Bearer {token}
```

**响应数据：**
```json
{
  "code": 200,
  "message": "获取成功",
  "data": {
    "id": 1,
    "username": "admin",
    "email": "admin@example.com",
    "role": {
      "id": 1,
      "name": "超级管理员",
      "guard_type": "admin"
    },
    "permissions": ["user_manage", "role_manage"],
    "data_scopes": {
      "user": {
        "scope_type": 1,
        "scope_name": "全部数据"
      },
      "agent": {
        "scope_type": 2,
        "scope_name": "本级及下级"
      }
    }
  }
}
```

### 8.3 获取权限菜单树

**接口地址：** `GET /api/admin/menus`

**请求参数：**
```
guard_type: admin
```

**响应数据：**
```json
{
  "code": 200,
  "message": "获取成功",
  "data": [
    {
      "id": 1,
      "title": "用户管理",
      "title_en": "User Management",
      "icon": "UserOutlined",
      "path": "/user",
      "component": "Layout",
      "permission_id": 1,
      "type": 1,
      "sort": 100,
      "children": [
        {
          "id": 2,
          "title": "用户列表",
          "title_en": "User List",
          "path": "/user/list",
          "component": "user/list",
          "permission_id": 2,
          "type": 2,
          "sort": 101,
          "buttons": [
            {
              "id": 3,
              "title": "添加",
              "slug": "user_create",
              "type": 3
            },
            {
              "id": 4,
              "title": "编辑",
              "slug": "user_edit",
              "type": 3
            }
          ]
        }
      ]
    }
  ]
}
```

### 8.4 角色列表

**接口地址：** `GET /api/admin/roles`

**请求参数：**
```
page: 1
per_page: 15
```

**响应数据：**
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
        "description": "拥有所有权限",
        "status": 1,
        "permission_ids": [1,2,3,4,5,10,11,12,13,14,15],
        "created_at": 1704067200,
        "updated_at": 1704067200
      }
    ],
    "total": 1
  }
}
```

### 8.5 分配权限

**接口地址：** `POST /api/admin/roles/assign`

**请求参数：**
```json
{
  "role_id": 2,
  "permissions": [1, 2, 3, 10, 11]
}
```

**响应数据：**
```json
{
  "code": 200,
  "message": "权限分配成功",
  "data": []
}
```

---

## 九、实施步骤

### 第一阶段：基础权限（1-2天）
1. ✅ 创建权限中间件 `CheckPermission`
2. ✅ 创建数据权限Trait `HasDataScope`
3. ✅ 补充权限字典数据
4. ✅ 配置路由权限验证
5. ✅ 测试基础权限流程

### 第二阶段：数据权限（2-3天）
1. ✅ 创建 `data_scopes` 表
2. ✅ 实现数据范围配置接口
3. ✅ 在业务Controller中应用数据过滤
4. ✅ 测试不同角色数据可见性

### 第三阶段：前端集成（3-5天）
1. ✅ 选择UI框架（推荐Ant Design Vue）
2. ✅ 实现权限指令和工具函数
3. ✅ 实现动态路由生成
4. ✅ 实现按钮权限控制
5. ✅ 优化整体UI布局

### 第四阶段：测试优化（2-3天）
1. ✅ 编写权限测试用例
2. ✅ 进行安全测试
3. ✅ 性能优化
4. ✅ 文档完善

---

## 十、安全建议

### 10.1 Token安全
- ✅ 使用JWT存储权限信息
- ✅ 设置合理过期时间（建议2小时）
- ✅ 实现Token刷新机制
- ✅ 支持单点登录（踢出旧Token）

### 10.2 接口安全
- ✅ 所有接口必须经过权限中间件
- ✅ 敏感操作二次验证密码
- ✅ 记录操作日志（operation_logs表）
- ✅ IP白名单限制（高危操作）

### 10.3 数据安全
- ✅ 严格数据权限过滤
- ✅ 防止越权访问
- ✅ 敏感数据脱敏
- ✅ 定期权限审计

---

## 十一、总结

本方案提供了一套完整的RBAC权限系统，包括：

✅ **清晰的权限模型** - 基于角色的访问控制
✅ **完整的权限类型** - 菜单、按钮、接口、数据四维权限
✅ **灵活的数据范围** - 支持多种数据权限配置
✅ **易于维护** - 权限配置化，代码解耦
✅ **安全可靠** - 多层防护，操作留痕

建议优先实施基础权限和数据权限，前端UI可以渐进式升级。
