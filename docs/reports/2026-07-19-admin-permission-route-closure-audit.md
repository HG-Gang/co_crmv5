# 后台命名路由与权限字典闭环审计

审计日期：2026-07-19  
审计范围：`routes/admin.php` 中全部 `admin_api_*` 命名路由、`permissions.api_route`、`CheckPermission` 白名单，以及 legacy 管理后台新增转发目标。

## 1. 审计结论

- `admin_api_*` 唯一路由名：168 个。
- 启用且未软删除的后台 `permissions.api_route`：160 个。
- 无独立权限记录但有明确认证边界的路由：8 个。
- 未分类的受保护路由缺口：0 个。
- 指向不存在命名路由的启用后台权限：0 个。
- `/api/admin/permissionTree` 与 `/api/admin/permissions/tree` 现在统一使用 `admin_api_permissionTree`，避免兼容 URI 产生第二个权限名。

## 2. Public 与认证白名单

| 路由名 | 分类 | 执行链 |
| --- | --- | --- |
| `admin_api_login` | Public | `api -> AuthController@login`，不挂 `check.permission:admin` |
| `admin_api_logout` | 登录基础能力 | `api -> jwt.auth:admin -> sso:admin -> check.permission:admin(白名单) -> AuthController@logout` |
| `admin_api_refreshToken` | 登录基础能力 | `api -> jwt.auth:admin -> sso:admin -> check.permission:admin(白名单) -> AuthController@refreshToken` |
| `admin_api_menus` | 登录基础能力 | `api -> jwt.auth:admin -> sso:admin -> check.permission:admin(白名单) -> MenuController@adminMenus` |
| `admin_api_profileInfo` | 当前账号能力 | `api -> jwt.auth:admin -> sso:admin -> check.permission:admin(白名单) -> AuthController@profileInfo` |
| `admin_api_updateProfile` | 当前账号能力 | `api -> jwt.auth:admin -> sso:admin -> check.permission:admin(白名单) -> AuthController@updateProfile` |
| `admin_api_changePassword` | 当前账号能力 | `api -> jwt.auth:admin -> sso:admin -> check.permission:admin(白名单) -> AuthController@changePassword` |
| `admin_api_uploadAvatar` | 当前账号能力 | `api -> jwt.auth:admin -> sso:admin -> check.permission:admin(白名单) -> AuthController@uploadAvatar` |

上述 8 项不写入独立按钮权限。它们要么是公开登录入口，要么是已登录管理员维持会话和维护自身账号所需的基础能力。

## 3. Follow-up Migration 补齐项

迁移：`database/migrations/2026_07_19_000001_ensure_protected_admin_route_permissions.php`

| slug | api_route | 父级 |
| --- | --- | --- |
| `admin_agent_detail` | `admin_api_agentDetail` | `admin_agents` |
| `admin_admin_status` | `admin_api_changeAdminStatus` | `admin_admins` |
| `admin_big_agent_status` | `admin_api_changeBigAgentStatus` | `admin_big_agents` |
| `admin_agent_create` | `admin_api_createAgent` | `admin_agents` |
| `admin_permission_create` | `admin_api_createPermission` | `admin_permissions` |
| `admin_production_create` | `admin_api_createProduction` | `admin_productions` |
| `admin_user_create` | `admin_api_createUser` | 活跃 `/admin/users` 页面 |
| `admin_menu_delete` | `admin_api_deleteMenu` | `admin_menus` |
| `admin_permission_delete` | `admin_api_deletePermission` | `admin_permissions` |
| `admin_production_delete` | `admin_api_deleteProduction` | `admin_productions` |
| `admin_deposit_detail` | `admin_api_depositDetail` | `admin_deposits` |
| `admin_deposit_export` | `admin_api_exportDeposits` | `admin_deposits` |
| `admin_gift_export` | `admin_api_exportGiftShipments` | `admin_gifts` |
| `admin_position_summary_export` | `admin_api_exportPositionSummary` | `admin_position_summary` |
| `admin_production_export` | `admin_api_exportProductions` | `admin_productions` |
| `admin_realtime_commission_export` | `admin_api_exportRealtimeCommissions` | `admin_realtime_commissions` |
| `admin_withdraw_export` | `admin_api_exportWithdrawals` | `admin_withdrawals` |
| `admin_operation_logs` | `admin_api_operationLogs` | 顶级 `/admin/operation-logs` 页面 |
| `admin_user_reset_password` | `admin_api_resetUserPassword` | 活跃 `/admin/users` 页面 |
| `admin_gift_update_shipment` | `admin_api_updateGiftShipment` | `admin_gifts` |
| `admin_production_update` | `admin_api_updateProduction` | `admin_productions` |
| `admin_user_update` | `admin_api_updateUser` | 活跃 `/admin/users` 页面 |
| `admin_upload_file` | `admin_api_uploadFile` | 顶级后台动作 |
| `admin_user_detail` | `admin_api_userDetail` | 活跃 `/admin/users` 页面 |
| `admin_whs_exp_zero_records` | `admin_api_whsExpZeroRecords` | `admin_page_whs_exp_zero` |

其中 7 个 legacy 新目标为：`admin_api_changeAdminStatus`、`admin_api_changeBigAgentStatus`、`admin_api_createUser`、`admin_api_createAgent`、`admin_api_resetUserPassword`、`admin_api_exportDeposits`、`admin_api_exportWithdrawals`，现均有真实、启用的 `permissions.api_route` 记录。

## 4. 重复 URI / 别名处理

- `/api/admin/permissionTree` 是旧前端兼容 URI；`/api/admin/permissions/tree` 是资源化 URI。两个 URI 调用同一个 Controller 方法并共享 `admin_api_permissionTree`。
- `routes/admin.php` 其余重复命名路由是同一业务能力的资源化 URI 和 legacy URI，权限中间件按共同的命名路由匹配同一条 `permissions.api_route`，无需重复权限记录。
- `admin_api_permissionTreeLegacy` 已移除，不再作为独立权限字符串存在。

## 5. 迁移与回滚语义

- `up()` 以 `slug` 执行 `updateOrInsert`，重复执行不会新增重复记录。
- `up()` 会把历史软删除或停用记录恢复为 `status=1`、`deleted_at=NULL`，保留原主键。
- `down()` 不物理删除权限行，而是写入 `status=0` 和 `deleted_at`。
- 软回滚保留 `permissions.id` 与现有 `role_permissions.permission_id`，避免回滚造成授权孤儿；重新 `up()` 会以相同 ID 恢复权限。
- 迁移只维护权限字典，不自动给普通角色授权；具体授权仍由 `role_permissions` 控制。

## 6. 日期过滤缺陷闭环

`deposit_records.created_at` 与 `withdraw_records.created_at` 是 unsigned Unix timestamp。旧实现把 `Y-m-d H:i:s` 字符串直接与整数列比较，MySQL 会发生数值转换，日期范围内记录全部无法命中。

修复后的链路：

1. 请求校验 `start_date/end_date` 为 `Y-m-d`。
2. `start_date` 转换为当天 `00:00:00` 的 Unix 秒。
3. `end_date` 转换为当天 `23:59:59` 的 Unix 秒。
4. 查询对 `created_at` 使用整数上下界。
5. 入金和出金 CSV 只返回范围内记录。

## 7. 验证证据

### RED

- `php vendor/bin/phpunit tests/Feature/AdminLegacyExportDateFilterClosureModuleTest.php`
  - `2 tests / 4 assertions / 2 failures`，入金和出金均只返回 CSV 表头。
- `php vendor/bin/phpunit tests/Feature/AdminProtectedRoutePermissionClosureModuleTest.php`
  - `4 tests / 27 assertions / 3 failures`，分别证明 canonical alias 错误和 follow-up migration 缺失。

### GREEN

- 日期回归：`2 tests / 6 assertions`。
- 权限闭环：`4 tests / 378 assertions`。
- 受影响模块联合回归：`60 tests / 920 assertions`。
- PHP 语法检查：导出控制器、迁移、两份新增测试全部通过。
- 真实 MySQL 迁移：`2026_07_19_000001_ensure_protected_admin_route_permissions` 成功，耗时约 52 ms。
- 真实库差集：未分类缺口 `[]`，孤儿权限 `[]`。

命令输出存在本地 Xdebug 旧配置告警，但 PHPUnit、Laravel migration 和 route:list 均成功完成；告警与本次业务逻辑无关。
