# 后台鉴权与数据范围执行清单

> 执行日期：2026-06-06  
> 项目目录：`D:\Software\PhpProject\Demo\co_crmv5`  
> 目标：落地后台接口鉴权、菜单/按钮权限、后端多语言、Blade 后台外壳、数据库配置驱动的数据范围，并补齐数据范围管理页面与接口。

## 1. 本次核心结论

后台鉴权方案已经形成闭环：

- 菜单、按钮、接口权限统一来自 `permissions` 与 `role_permissions`。
- 数据范围统一来自 `role_data_scopes` 与 `admin_agent_bindings`。
- 后台 API 统一经过 `jwt.auth:admin`、`sso:admin`、`check.permission:admin`。
- 前端 Blade 页面只做展示控制，真正安全边界在后端中间件和 `AdminDataScopeService`。
- 数据范围管理页已经使用 Laravel Blade + Layui + JS 实现，未改成前后端分离。

## 2. 已落地模块

### 2.1 权限唯一来源

- `App\Models\Role::hasPermission($slug)` 从 `role_permissions` 中间表读取 `permissions.slug`。
- `App\Models\Admin::getAllPermissions()` 返回当前管理员角色拥有的权限 slug 数组。
- `roles.permissions` JSON 不再作为有效授权来源，避免双数据源。

### 2.2 接口鉴权

- `App\Http\Middleware\CheckPermission` 支持 `check.permission:admin`。
- 后台接口按 `permissions.guard_type=admin`、`permissions.api_route`、`permissions.status=1` 校验。
- 超级管理员仍需通过 JWT 与 SSO，只跳过权限表授权检查。
- 登录后基础白名单保留：菜单、个人资料、改密、头像上传、退出等。

### 2.3 菜单与按钮权限

- `Admin\MenuController@adminMenus` 返回：
  - `menus`：当前管理员可见菜单树。
  - `permissions`：当前管理员拥有的权限 slug 数组。
  - `admin_name`：当前管理员展示名。
- 前端可根据 `permissions` 控制按钮显示。
- 后端接口仍由 `check.permission:admin` 做最终校验。

### 2.4 数据范围基础

已创建并执行迁移：

- `role_data_scopes`
- `admin_agent_bindings`

已新增/整理模型：

- `App\Models\RoleDataScope`
- `App\Models\AdminAgentBinding`
- `App\Models\Role::dataScope()`

已新增服务：

- `App\Services\AdminDataScopeService`

服务能力：

- `apply($query, $admin, $targetType, $userIdColumn, $agentIdColumn)`：给列表查询追加数据范围。
- `canAccessUser($admin, $userId, $targetType)`：判断详情、更新、审核、财务处理等单条动作是否允许访问目标用户或代理。
- `getRoleScope($admin)`：读取当前管理员角色启用的数据范围配置。
- `getBoundAgentIds($admin)`：读取当前管理员绑定的代理业务用户 ID。

支持的数据范围类型：

- `all`：全部数据。
- `self`：本人数据，当前作为收紧范围处理。
- `created`：本人创建数据。
- `agent_tree`：管理员绑定代理树数据。
- `custom_agents`：指定代理集合及代理树数据。
- `custom_users`：指定业务用户集合。

### 2.5 数据范围接入的列表接口

- `AdminUserController@userList`
- `AgentController@index`
- `DepositController@index`
- `WithdrawController@index`
- `CommissionController@index`

### 2.6 数据范围接入的单条动作

- 用户：详情、更新、实名认证审核、状态变更。
- 代理：详情、下级、等级更新、返佣比例更新。
- 入金：详情、通过、拒绝。
- 出金：详情、处理中、完成、拒绝。
- 返佣：详情、结算、批量结算。

无权访问统一返回：

```php
return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
```

## 3. 本次新增数据范围管理页面

### 3.1 页面路由

| 方法 | 路径 | 路由名 | 说明 |
| --- | --- | --- | --- |
| GET | `/admin/data-scopes` | `admin_page_data_scopes` | 数据范围管理 Blade 页面 |

页面文件：

- `resources/admin/layui/data-scopes/index.blade.php`
- `public/js/admin/layui/data-scopes/index.js`

页面结构：

- `roleScopeTable`：角色数据范围表。
- `adminAgentBindingTable`：管理员代理绑定表。
- `roleScopeModal`：角色数据范围配置弹窗。
- `adminAgentBindingModal`：管理员代理绑定配置弹窗。

### 3.2 后台 API

| 方法 | 路径 | 路由名 | 控制器方法 | 说明 |
| --- | --- | --- | --- | --- |
| POST | `/api/admin/roleDataScopeList` | `admin_api_roleDataScopeList` | `DataScopeController@roleDataScopeList` | 获取后台角色及其数据范围配置 |
| POST | `/api/admin/saveRoleDataScope` | `admin_api_saveRoleDataScope` | `DataScopeController@saveRoleDataScope` | 保存角色数据范围 |
| POST | `/api/admin/adminAgentBindingList` | `admin_api_adminAgentBindingList` | `DataScopeController@adminAgentBindingList` | 获取管理员代理绑定列表 |
| POST | `/api/admin/saveAdminAgentBinding` | `admin_api_saveAdminAgentBinding` | `DataScopeController@saveAdminAgentBinding` | 保存管理员代理绑定 |
| POST | `/api/admin/deleteAdminAgentBinding` | `admin_api_deleteAdminAgentBinding` | `DataScopeController@deleteAdminAgentBinding` | 删除管理员代理绑定 |

### 3.3 请求参数说明

`saveRoleDataScope`：

| 参数 | 含义 |
| --- | --- |
| `role_id` | 角色 ID，对应 `roles.id` |
| `scope_type` | 数据范围类型：`all`、`self`、`created`、`agent_tree`、`custom_agents`、`custom_users` |
| `agent_ids` | 指定代理 ID 集合，支持数组或英文逗号字符串 |
| `user_ids` | 指定用户 ID 集合，支持数组或英文逗号字符串 |
| `status` | 1 启用，0 禁用 |

`saveAdminAgentBinding`：

| 参数 | 含义 |
| --- | --- |
| `admin_id` | 后台管理员 ID，对应 `admins.id` |
| `agent_id` | 代理业务用户 ID，对应 `user_infos.user_id` 且 `account_type=1` |
| `binding_type` | `primary` 主绑定，`extra` 额外授权 |
| `status` | 1 启用，0 禁用 |

`deleteAdminAgentBinding`：

| 参数 | 含义 |
| --- | --- |
| `id` | `admin_agent_bindings.id` |

## 4. 权限配置节点

新增迁移：

- `database/migrations/2026_06_06_000003_add_admin_data_scope_permissions.php`

写入 `permissions`：

| slug | 类型 | route/api_route | 说明 |
| --- | --- | --- | --- |
| `admin_data_scopes` | 菜单 | `/admin/data-scopes` | 数据范围管理页面 |
| `admin_data_scope_role_list` | 按钮/API | `admin_api_roleDataScopeList` | 查看角色数据范围 |
| `admin_data_scope_role_save` | 按钮/API | `admin_api_saveRoleDataScope` | 保存角色数据范围 |
| `admin_data_scope_binding_list` | 按钮/API | `admin_api_adminAgentBindingList` | 查看管理员代理绑定 |
| `admin_data_scope_binding_save` | 按钮/API | `admin_api_saveAdminAgentBinding` | 保存管理员代理绑定 |
| `admin_data_scope_binding_delete` | 按钮/API | `admin_api_deleteAdminAgentBinding` | 删除管理员代理绑定 |

迁移已执行：

```bash
php artisan migrate
```

迁移状态已确认：

```text
2026_06_06_000001_create_role_data_scopes_table        Yes
2026_06_06_000002_create_admin_agent_bindings_table    Yes
2026_06_06_000003_add_admin_data_scope_permissions     Yes
```

## 5. 后端多语言

已重写并补齐：

- `resources/lang/zh-CN/admin.php`
- `resources/lang/en/admin.php`

新增关键消息：

- `admin.data_scopes`
- `admin.data_scope_list_fetched`
- `admin.data_scope_saved`
- `admin.admin_agent_bindings_fetched`
- `admin.admin_agent_binding_saved`
- `admin.admin_agent_binding_deleted`
- `admin.admin_agent_binding_not_found`

接口统一响应格式：

```json
{
  "code": 1000,
  "message": "操作成功",
  "data": {}
}
```

权限不足：

- 中文：`response.permission_denied`
- 英文：`response.permission_denied`
- 状态码：`ResponseCode::PERMISSION_DENIED`，值为 `4006`

## 6. UI 实现说明

后台外壳：

- `resources/admin/layui/layouts/app.blade.php`

本次保留 Laravel Blade 渲染方式，并参考 Vben Admin、Vue Naive Admin、Naive UI Admin、Ant Design Pro、Arco Design Pro 的后台信息密度与工作台风格：

- 顶部导航、左侧菜单、内容区工作台结构。
- 页面主体以表格和弹窗配置为主，不做营销式大卡片。
- 数据范围页面采用双表格布局，便于同时维护角色范围和管理员代理绑定。

## 7. 新增和修改文件

新增：

- `app/Http/Controllers/Admin/DataScopeController.php`
- `resources/admin/layui/data-scopes/index.blade.php`
- `public/js/admin/layui/data-scopes/index.js`
- `database/migrations/2026_06_06_000003_add_admin_data_scope_permissions.php`
- `tests/Feature/AdminDataScopeManagementTest.php`

重点修改：

- `routes/admin.php`
- `routes/web.php`
- `app/Models/Role.php`
- `app/Models/RoleDataScope.php`
- `app/Models/AdminAgentBinding.php`
- `resources/admin/layui/layouts/app.blade.php`
- `resources/lang/zh-CN/admin.php`
- `resources/lang/en/admin.php`
- `docs/admin-auth-permission-execution-checklist.md`

此前已完成并保留：

- `App\Http\Middleware\CheckPermission`
- `App\Services\AdminDataScopeService`
- `App\Models\Admin`
- `App\Models\CommissionRecord`
- 用户、代理、入金、出金、返佣相关后台控制器的数据范围接入。

## 8. 已执行验证

语法检查：

```bash
php -l app\Http\Controllers\Admin\DataScopeController.php
php -l app\Models\AdminAgentBinding.php
php -l app\Models\RoleDataScope.php
php -l app\Models\Role.php
php -l database\migrations\2026_06_06_000003_add_admin_data_scope_permissions.php
php -l resources\admin\layui\layouts\app.blade.php
php -l resources\admin\layui\data-scopes\index.blade.php
php -l resources\lang\zh-CN\admin.php
php -l resources\lang\en\admin.php
```

结果：全部 `No syntax errors detected`。

PHPUnit：

```bash
vendor\bin\phpunit tests\Feature\AdminDataScopeManagementTest.php
vendor\bin\phpunit tests\Feature\AdminPermissionPlanTest.php tests\Feature\AdminLocalizationTest.php tests\Feature\AdminBladeUiTest.php
vendor\bin\phpunit tests\Feature\AdminDataScopeServiceTest.php tests\Feature\AdminDataScopeControllerWiringTest.php
```

结果：

- `AdminDataScopeManagementTest`：4 tests，14 assertions，通过。
- 权限、多语言、Blade UI 回归：3 tests，6 assertions，通过。
- 数据范围服务和控制器接线：4 tests，6 assertions，通过。

说明：PHPUnit 输出中存在本机 Xdebug 旧配置提醒，不影响测试退出码和功能验证。

## 9. 后续接入规则

新增后台列表接口时：

```php
$query = $this->adminDataScopeService->apply(
    $query,
    $request->user('admin'),
    'user',
    'user_id',
    null
);
```

新增后台详情、审核、更新、财务处理等单条动作时：

```php
if (! $this->adminDataScopeService->canAccessUser($admin, $userId, 'user')) {
    return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
}
```

注意：

- 每个新接口必须同时配置 `permissions.api_route` 和角色授权关系。
- 前端按钮隐藏只是体验优化，不能作为安全边界。
- 列表范围和单条动作范围必须同时接入，不能只限制列表。
