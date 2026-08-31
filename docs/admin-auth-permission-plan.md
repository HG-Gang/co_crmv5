# co_crmv5 后台鉴权与权限控制方案

> 编写日期：2026-06-06  
> 新项目后端目录：`D:\Software\PhpProject\Demo\co_crmv5\app\Http\Controllers\Admin`  
> 旧项目后端目录：`D:\Php-project\Php\new_co_gmtk_crmV3\app\Http\Controllers\Admin`  
> 数据库连接信息：`co_crmv5` / `hank_zl_data` / `root` / `123456` / `3307`  
> 本次实际变更：新增本方案文档，未修改业务代码、路由、中间件或数据表。

## 1. 当前结论

推荐采用“JWT 登录认证 + 数据表驱动 RBAC + 数据范围服务”的方案。

这套方案的核心原则是：前端菜单、前端按钮、后端接口、页面数据范围都不能写死在前端或控制器里，必须从数据库表配置中得到。前端只负责展示后端返回的授权结果，后端必须在接口入口和业务查询层再次校验，避免只隐藏按钮但接口仍可直接调用。

当前新项目已经具备可复用基础：

- `admins`：后台管理员账号表。
- `roles`：角色表，区分 `guard_type=admin/front`。
- `permissions`：权限表，已有 `type`、`slug`、`api_route`、`route`、`parent_id`、`guard_type` 等字段。
- `role_permissions`：角色权限中间表。
- `menus`：菜单表，但当前后台菜单接口实际主要读取 `permissions` 表。
- `CheckPermission`：权限校验中间件已存在并注册为 `check.permission`。
- `MenuService`：菜单树生成服务已存在。

当前需要优先修正的设计风险：

- `roles.permissions` JSON 与 `role_permissions` 中间表同时存在，容易形成两个权限来源。后续应以 `role_permissions` 作为唯一生效来源，`roles.permissions` 只保留兼容或废弃。
- `check.permission` 已在 `Kernel.php` 注册，但 `routes/admin.php` 的后台受保护路由组当前只挂了 `jwt.auth:admin` 和 `sso:admin`，还没有统一挂上接口权限校验。
- 旧项目大量数据范围逻辑散落在控制器内，例如代理树、下级用户、订单汇总等查询，不适合继续复制到新项目每个控制器里。

## 2. 推荐权限模型

### 2.1 登录认证

登录认证只解决“是谁”的问题，不解决“能做什么”的问题。

后台管理员登录后，`AuthController@login` 通过 `JwtService` 生成 JWT，JWT 载荷包含：

- `sub`：登录主体 ID，对后台来说是 `admins.id`。
- `guard`：登录守卫类型，后台固定为 `admin`。
- `jti`：JWT 唯一编号，用于 SSO 单点登录控制。

请求进入后台接口时，执行顺序建议固定为：

1. `jwt.auth:admin`：解析 Bearer Token，设置当前登录管理员。
2. `sso:admin`：校验当前 Token 是否仍是该账号有效登录。
3. `check.permission:admin`：按当前路由名匹配权限表，判断角色是否拥有该接口权限。
4. 控制器业务逻辑：只处理已认证且已授权的请求。

### 2.2 RBAC 权限

权限采用三层结构：

- 菜单权限：控制左侧菜单、顶部导航、页面入口是否展示。
- 页面权限：控制某个页面或业务模块是否允许访问。
- 按钮权限：控制新增、编辑、删除、审核、导出、结算等细粒度动作。

`permissions.type` 建议含义如下：

- `1`：目录或菜单权限，用于动态菜单树。
- `2`：页面权限，用于页面入口和页面级接口。
- `3`：按钮或动作权限，用于操作按钮和敏感接口。

`permissions.api_route` 建议绑定 Laravel 路由名称，例如：

- `admin_api_userList`
- `admin_api_reviewAuth`
- `admin_api_depositApprove`
- `admin_api_withdrawReject`
- `admin_api_assignPermissions`

`permissions.route` 建议绑定前端页面路径，例如：

- `/admin-naive/users`
- `/admin-naive/roles`
- `/admin-naive/deposits`
- `/admin-naive/withdraws`

### 2.3 超级管理员

超级管理员建议只用一条明确规则：

- `admins.id = 1` 或角色 `roles.name = super_admin` 视为超级管理员。
- 超级管理员跳过 `role_permissions` 权限检查。
- 超级管理员仍然需要 JWT 和 SSO 校验，不能绕过登录认证。

后续如要更严谨，可在 `roles` 表增加 `is_super` 字段，避免依赖名称。

## 3. 数据表设计建议

### 3.1 `roles`

角色表负责定义一组权限的业务身份。

关键字段建议：

- `id`：角色 ID。
- `name`：角色名称，例如超级管理员、财务审核、客服、风控、运营。
- `guard_type`：守卫类型，后台为 `admin`，前台为 `front`。
- `description`：角色说明。
- `status`：是否启用，`1=启用`，`0=禁用`。

建议处理：

- `roles.permissions` JSON 不再作为权限判断来源。
- 后续接口返回角色详情时，可以根据 `role_permissions` 动态附带 `permission_ids`。

### 3.2 `permissions`

权限表是菜单、页面、按钮和接口鉴权的核心配置表。

关键字段建议：

- `id`：权限 ID。
- `parent_id`：父权限 ID，`0` 表示顶级节点。
- `name`：权限显示名称。
- `slug`：权限唯一标识，建议使用稳定英文，例如 `admin_user_review`。
- `guard_type`：守卫类型，后台为 `admin`，前台为 `front`。
- `type`：权限类型，`1=菜单`、`2=页面`、`3=按钮`。
- `route`：前端路由路径。
- `api_route`：后端路由名称。
- `icon`：菜单图标。
- `sort`：排序值。
- `status`：是否启用。

配置规则：

- 每个需要鉴权的后台接口必须在 `permissions.api_route` 中存在一条记录。
- 如果接口没有配置到权限表，开发期可以允许通过，但生产建议改为拒绝或只对白名单接口放行。
- 一个页面可以有多个按钮权限子节点，例如用户列表下挂“编辑用户”“审核实名”“禁用用户”。

### 3.3 `role_permissions`

角色权限中间表是唯一生效的权限分配来源。

关键字段：

- `role_id`：角色 ID。
- `permission_id`：权限 ID。

配置规则：

- 给角色授权时只写 `role_permissions`。
- 删除权限前必须检查是否有子权限和角色绑定。
- 禁用权限时不删除授权关系，只让 `permissions.status=0` 使其失效。

### 3.4 数据范围配置表建议

仅靠 RBAC 只能判断“能不能进接口”，不能判断“能看哪些数据”。后台还需要数据范围。

建议新增一张角色数据范围表：

```sql
CREATE TABLE `role_data_scopes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `role_id` BIGINT UNSIGNED NOT NULL COMMENT '角色ID，对应 roles.id',
  `scope_type` VARCHAR(30) NOT NULL COMMENT '数据范围类型：all=全部，self=本人，created=本人创建，agent_tree=指定代理树，custom_agents=指定代理集合，custom_users=指定用户集合',
  `agent_ids` JSON NULL COMMENT '指定代理ID数组，仅 scope_type=custom_agents 时使用',
  `user_ids` JSON NULL COMMENT '指定用户ID数组，仅 scope_type=custom_users 时使用',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态：1=启用，0=禁用',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间，10位时间戳',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间，10位时间戳',
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_data_scopes_role_id_unique` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='角色数据范围配置表';
```

字段逻辑说明：

- `scope_type=all`：可查看全部后台数据，通常只给超级管理员或总后台。
- `scope_type=self`：只查看与当前管理员自己绑定的数据。
- `scope_type=created`：只查看当前管理员创建的数据。
- `scope_type=agent_tree`：查看当前管理员绑定代理及其下级代理、客户数据。
- `scope_type=custom_agents`：查看配置的代理 ID 集合及其下级数据。
- `scope_type=custom_users`：只查看配置的用户 ID 集合。

如后台管理员需要绑定代理身份，建议再新增管理员代理绑定表：

```sql
CREATE TABLE `admin_agent_bindings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `admin_id` BIGINT UNSIGNED NOT NULL COMMENT '管理员ID，对应 admins.id',
  `agent_id` INT NOT NULL COMMENT '代理用户ID，对应代理或用户体系中的代理ID',
  `binding_type` VARCHAR(20) NOT NULL DEFAULT 'primary' COMMENT '绑定类型：primary=主绑定，extra=额外绑定',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态：1=启用，0=禁用',
  `created_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '创建时间，10位时间戳',
  `updated_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '更新时间，10位时间戳',
  PRIMARY KEY (`id`),
  KEY `admin_agent_bindings_admin_id_index` (`admin_id`),
  KEY `admin_agent_bindings_agent_id_index` (`agent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员与代理绑定关系表';
```

## 4. 后端鉴权流程

### 4.1 路由层

后台受保护路由组建议调整为：

```php
Route::middleware(['jwt.auth:admin', 'sso:admin', 'check.permission:admin'])->group(function () {
    // 后台接口
});
```

中间件参数逻辑：

- `jwt.auth:admin`：指定从后台管理员表读取登录用户。
- `sso:admin`：指定后台管理员账号执行单点登录校验。
- `check.permission:admin`：指定只匹配 `permissions.guard_type=admin` 的权限配置。

白名单接口建议：

- `/login`
- `/logout`
- `/refreshToken`
- `/profileInfo`
- `/menus`

说明：

- `/menus` 本身需要登录，但不建议要求菜单权限，否则首次进入后台无法加载授权菜单。
- `/profileInfo` 只返回当前登录人信息，可只要求登录。
- `/changePassword` 是否需要按钮权限可按业务决定，推荐只要求登录。

### 4.2 接口权限校验

`CheckPermission` 推荐逻辑：

1. 从当前请求获取登录管理员。
2. 如果未登录，返回 `4001` 或 `4006`。
3. 判断是否超级管理员，是则放行。
4. 获取当前 Laravel 路由名，例如 `admin_api_userList`。
5. 用 `guard_type + api_route + status` 查询 `permissions`。
6. 如果该接口是白名单，放行。
7. 如果权限表不存在该接口配置，生产环境应拒绝，开发环境可记录日志后放行。
8. 查询当前管理员角色是否通过 `role_permissions` 绑定该权限。
9. 无权限返回 `4006 Permission denied`。
10. 有权限则进入控制器。

### 4.3 数据范围校验

数据范围不建议写在权限中间件里，因为权限中间件只知道接口，不知道具体业务表。

建议新增 `AdminDataScopeService`，所有涉及用户、代理、入金、出金、交易、返佣、认证审核、风控、报表的控制器统一调用。

服务建议提供方法：

```php
/**
 * 根据当前管理员的数据范围配置，给业务查询追加可见数据条件。
 *
 * @param \Illuminate\Database\Eloquent\Builder $query 业务查询对象，用于追加 where / whereIn 条件。
 * @param \App\Models\Admin $admin 当前登录管理员，用于读取角色、绑定代理和数据范围。
 * @param string $targetType 数据对象类型：user=普通用户，agent=代理，deposit=入金，withdraw=出金，trade=交易。
 * @param string $userIdColumn 查询表中的用户ID字段名，例如 user_id。
 * @param string|null $agentIdColumn 查询表中的代理ID字段名，无代理字段时传 null。
 * @return \Illuminate\Database\Eloquent\Builder 已追加数据范围条件的查询对象。
 */
public function apply($query, Admin $admin, string $targetType, string $userIdColumn = 'user_id', ?string $agentIdColumn = null)
```

核心逻辑：

- 超级管理员：不追加任何范围条件。
- `scope_type=all`：不追加任何范围条件。
- `scope_type=self`：只匹配当前管理员绑定的用户或管理员创建的数据。
- `scope_type=agent_tree`：读取 `admin_agent_bindings.agent_id`，再通过 `agent_descendants` 查下级代理和客户。
- `scope_type=custom_agents`：读取配置的代理 ID，扩展为代理树后过滤。
- `scope_type=custom_users`：只允许配置的用户 ID。

这样可以替代旧项目中大量散落的 `admin_get_current_*_id_list`、多层代理 SQL 和重复汇总查询。

## 5. 前端菜单与按钮控制

### 5.1 菜单接口

现有后台菜单接口：

- `POST /api/admin/menus`
- 路由名：`admin_api_menus`
- 控制器：`Admin\MenuController@adminMenus`

建议返回结构：

```json
{
  "code": 1000,
  "message": "Query successful",
  "data": {
    "menus": [
      {
        "id": 1,
        "slug": "admin_users",
        "title": "用户管理",
        "icon": "User",
        "path": "/admin-naive/users",
        "api_route": "admin_api_userList",
        "type": 1,
        "children": []
      }
    ],
    "permissions": [
      "admin_user_list",
      "admin_user_update",
      "admin_user_review"
    ],
    "admin_name": "admin"
  }
}
```

字段逻辑说明：

- `menus`：当前管理员可见菜单树，用于 Layui 与 Naive UI 两套后台共同渲染。
- `permissions`：当前管理员拥有的 `slug` 数组，用于前端按钮级判断。
- `slug`：前端权限判断的稳定 key。
- `path`：前端页面路径。
- `api_route`：该菜单或按钮绑定的后端接口路由名。
- `type`：权限类型，前端可根据 `type=3` 提取按钮权限。

### 5.2 按钮控制

前端按钮只做体验控制，不能作为安全边界。

按钮示例：

- 用户编辑按钮：`admin_user_update`
- 实名审核按钮：`admin_user_review`
- 入金审核按钮：`admin_deposit_approve`
- 出金驳回按钮：`admin_withdraw_reject`
- 权限分配按钮：`admin_role_assign_permissions`

Naive UI 和 Layui 都应使用同一份后端权限数据：

```js
function can(permissionSlug) {
  return currentUserPermissions.includes(permissionSlug)
}
```

页面按钮展示逻辑：

- 有权限：展示并允许点击。
- 无权限：隐藏按钮；如业务需要也可展示禁用态，但点击仍不能请求接口。

## 6. 后台接口消息清单

统一响应格式来自 `ApiResponse`：

```json
{
  "code": 1000,
  "message": "Operation successful",
  "data": {}
}
```

常用状态码：

- `1000`：成功。
- `1001`：创建成功。
- `1002`：更新成功。
- `1003`：删除成功。
- `4001`：认证失败、Token 无效或登录失效。
- `4002`：Token 过期。
- `4003`：SSO 冲突，账号已在其他地方登录。
- `4004`：Token 缺失。
- `4005`：参数验证失败。
- `4006`：权限不足。
- `2019`：数据不存在。
- `2021`：操作不允许。
- `5000`：服务端内部错误。

### 6.1 认证接口

| 接口 | 路由名 | 权限要求 | 说明 |
| --- | --- | --- | --- |
| `POST /api/admin/login` | `admin_api_login` | 无 | 后台登录，返回 JWT |
| `POST /api/admin/logout` | `admin_api_logout` | 登录 | 注销当前 Token |
| `POST /api/admin/refreshToken` | `admin_api_refreshToken` | 登录 | 刷新 Token |
| `POST /api/admin/profileInfo` | `admin_api_profileInfo` | 登录 | 当前管理员资料 |
| `POST /api/admin/updateProfile` | `admin_api_updateProfile` | 登录或按钮权限 | 更新当前管理员资料 |
| `POST /api/admin/changePassword` | `admin_api_changePassword` | 登录 | 修改密码并使当前 Token 失效 |

### 6.2 权限与菜单接口

| 接口 | 路由名 | 建议权限 slug | 说明 |
| --- | --- | --- | --- |
| `POST /api/admin/menus` | `admin_api_menus` | 登录 | 返回当前管理员授权菜单和按钮权限 |
| `POST /api/admin/roleList` | `admin_api_roleList` | `admin_role_list` | 角色列表 |
| `POST /api/admin/createRole` | `admin_api_createRole` | `admin_role_create` | 创建角色 |
| `POST /api/admin/updateRole` | `admin_api_updateRole` | `admin_role_update` | 更新角色 |
| `POST /api/admin/deleteRole` | `admin_api_deleteRole` | `admin_role_delete` | 删除角色 |
| `POST /api/admin/assignPermissions` | `admin_api_assignPermissions` | `admin_role_assign_permissions` | 分配角色权限 |
| `POST /api/admin/permissionTree` | `admin_api_permissionTree` | `admin_permission_tree` | 权限树 |
| `POST /api/admin/createPermission` | `admin_api_createPermission` | `admin_permission_create` | 创建权限 |
| `POST /api/admin/updatePermission` | `admin_api_updatePermission` | `admin_permission_update` | 更新权限 |
| `POST /api/admin/deletePermission` | `admin_api_deletePermission` | `admin_permission_delete` | 删除权限 |
| `POST /api/admin/menuTree` | `admin_api_menuTree` | `admin_menu_tree` | 菜单树管理 |
| `POST /api/admin/createMenu` | `admin_api_createMenu` | `admin_menu_create` | 创建菜单 |
| `POST /api/admin/updateMenu` | `admin_api_updateMenu` | `admin_menu_update` | 更新菜单 |
| `POST /api/admin/deleteMenu` | `admin_api_deleteMenu` | `admin_menu_delete` | 删除菜单 |

### 6.3 业务接口

| 模块 | 接口 | 路由名 | 建议权限 slug |
| --- | --- | --- | --- |
| 用户 | `POST /api/admin/userList` | `admin_api_userList` | `admin_user_list` |
| 用户 | `POST /api/admin/userDetail` | `admin_api_userDetail` | `admin_user_detail` |
| 用户 | `POST /api/admin/updateUser` | `admin_api_updateUser` | `admin_user_update` |
| 用户 | `POST /api/admin/changeUserStatus` | `admin_api_changeUserStatus` | `admin_user_status` |
| 用户 | `POST /api/admin/reviewAuth` | `admin_api_reviewAuth` | `admin_user_review_auth` |
| 代理 | `POST /api/admin/agentList` | `admin_api_agentList` | `admin_agent_list` |
| 代理 | `POST /api/admin/agentDetail` | `admin_api_agentDetail` | `admin_agent_detail` |
| 代理 | `POST /api/admin/agentDescendants` | `admin_api_agentDescendants` | `admin_agent_descendants` |
| 代理 | `POST /api/admin/updateAgentLevel` | `admin_api_updateAgentLevel` | `admin_agent_update_level` |
| 代理 | `POST /api/admin/updateAgentCommission` | `admin_api_updateAgentCommission` | `admin_agent_update_commission` |
| 入金 | `POST /api/admin/depositList` | `admin_api_depositList` | `admin_deposit_list` |
| 入金 | `POST /api/admin/depositApprove` | `admin_api_depositApprove` | `admin_deposit_approve` |
| 入金 | `POST /api/admin/depositReject` | `admin_api_depositReject` | `admin_deposit_reject` |
| 出金 | `POST /api/admin/withdrawList` | `admin_api_withdrawList` | `admin_withdraw_list` |
| 出金 | `POST /api/admin/withdrawProcess` | `admin_api_withdrawProcess` | `admin_withdraw_process` |
| 出金 | `POST /api/admin/withdrawComplete` | `admin_api_withdrawComplete` | `admin_withdraw_complete` |
| 出金 | `POST /api/admin/withdrawReject` | `admin_api_withdrawReject` | `admin_withdraw_reject` |
| 返佣 | `POST /api/admin/commissionList` | `admin_api_commissionList` | `admin_commission_list` |
| 返佣 | `POST /api/admin/commissionSettle` | `admin_api_commissionSettle` | `admin_commission_settle` |
| 系统 | `POST /api/admin/systemConfigList` | `admin_api_systemConfigList` | `admin_system_config_list` |
| 系统 | `POST /api/admin/updateSystemConfig` | `admin_api_updateSystemConfig` | `admin_system_config_update` |
| 系统 | `POST /api/admin/operationLogs` | `admin_api_operationLogs` | `admin_operation_logs` |
| 新闻 | `POST /api/admin/newsList` | `admin_api_newsList` | `admin_news_list` |
| 新闻 | `POST /api/admin/createNews` | `admin_api_createNews` | `admin_news_create` |
| 新闻 | `POST /api/admin/updateNews` | `admin_api_updateNews` | `admin_news_update` |
| 新闻 | `POST /api/admin/deleteNews` | `admin_api_deleteNews` | `admin_news_delete` |
| 管理员 | `POST /api/admin/adminList` | `admin_api_adminList` | `admin_admin_list` |
| 管理员 | `POST /api/admin/createAdmin` | `admin_api_createAdmin` | `admin_admin_create` |
| 管理员 | `POST /api/admin/updateAdmin` | `admin_api_updateAdmin` | `admin_admin_update` |
| 管理员 | `POST /api/admin/deleteAdmin` | `admin_api_deleteAdmin` | `admin_admin_delete` |

## 7. UI 参考建议

当前后台不是营销站，应该做成高密度、低干扰、可长时间操作的运营后台。

推荐参考方向：

- Vben Admin：适合作为 Naive UI 后台的信息架构参考，重点看布局、标签页、权限路由、主题配置和表格页面密度。
- Vue Naive Admin：适合作为 Naive UI 视觉参考，整体更轻、更现代，适合新后台。
- Naive UI Admin：适合参考动态菜单、按钮权限、路由权限和典型业务模型。
- Ant Design Pro：适合参考中后台信息架构和复杂表单、列表、详情页组织方式。
- Arco Design Pro：适合参考简洁企业后台视觉和组件密度。

落地建议：

- 两套 UI 共用同一套后端菜单和权限接口，不要为 Layui 与 Naive UI 各维护一份菜单配置。
- 表格页优先做好筛选、列密度、批量操作、固定操作列、状态标签、详情抽屉。
- 详情页优先采用“基础信息 + 账户信息 + 审核记录 + 操作日志”的分区布局。
- 财务、出入金、风控页面避免大面积装饰卡片，优先让数字、状态和操作路径清晰。

## 8. 后续落地顺序

第一步：统一权限数据来源。

- 将 `Role::hasPermission()` 改为读取 `role_permissions` 关联权限。
- `roles.permissions` JSON 不再参与实际鉴权。
- 角色列表接口返回 `permission_ids`，方便前端编辑回显。

第二步：补全后台路由权限中间件。

- 在 `routes/admin.php` 受保护路由组追加 `check.permission:admin`。
- 配置登录、菜单、个人资料等白名单。
- 所有需要鉴权的接口补齐 `permissions.api_route`。

第三步：改造菜单接口。

- `POST /api/admin/menus` 同时返回 `menus` 和 `permissions`。
- 菜单节点与按钮节点都来自 `permissions` 或统一后的菜单权限表。
- Layui 与 Naive UI 两套前端只消费同一个接口。

第四步：建设数据范围服务。

- 新增 `role_data_scopes` 与 `admin_agent_bindings`。
- 新增 `AdminDataScopeService`。
- 用户、代理、入金、出金、交易、返佣、认证审核、报表接口统一接入该服务。

第五步：补齐权限管理界面。

- 角色管理：角色列表、创建、编辑、删除、启禁用、分配权限。
- 权限管理：权限树、菜单/页面/按钮节点维护、接口路由名绑定。
- 管理员管理：管理员角色绑定、状态控制、代理绑定。
- 数据范围管理：角色数据范围配置、指定代理和指定用户配置。

## 9. 验证建议

后续代码落地后，至少验证以下场景：

- 未登录访问后台接口，返回认证失败。
- 普通角色访问未授权接口，返回 `4006`。
- 普通角色前端不展示未授权菜单和按钮。
- 普通角色直接调用隐藏按钮对应接口，仍返回 `4006`。
- 禁用某个权限后，菜单和接口同时失效。
- 角色只授权某个代理树后，用户、订单、入金、出金、返佣列表只返回该范围数据。
- 超级管理员能访问全部菜单、按钮和数据。
- SSO 登录冲突时旧 Token 返回 `4003`。

## 10. 本次未执行的事项

本次只输出方案文档，未直接修改以下内容：

- 未修改 `routes/admin.php` 中间件。
- 未修改 `CheckPermission`。
- 未修改 `Role`、`Permission`、`MenuService` 模型或服务。
- 未新增迁移文件。
- 未执行数据库迁移。
- 未运行接口自动化测试。

原因：当前任务要求先给出一套合理且容易维护的鉴权方案，并最终输出 MD 文档。后台鉴权涉及接口、菜单、按钮、数据范围和两套前端联动，建议确认方案后再进入代码落地阶段。
