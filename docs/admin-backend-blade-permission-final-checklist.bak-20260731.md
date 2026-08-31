# 后台 Blade 页面与权限配置执行清�?

> 更新时间�?2026-06-06  
> 项目：`D:\Software\PhpProject\Demo\co_crmv5`  
> 范围：后台鉴权�?�Blade 后台页面、多语言、权限表配置、数据范围阶段成果�??

## 1. 本次新增后台 Blade 页面

以下页面已从 `/admin/{path?}` �? Naive 兜底页面中拆出，改为 Laravel Blade 模板直接渲染�?

| 页面 | 路由名称 | Blade 文件 | JS 文件 | 主表�? ID |
| --- | --- | --- | --- | --- |
| `/admin/agents` | `admin_page_agents` | `resources/admin/layui/agents/index.blade.php` | `public/js/admin/layui/agents/index.js` | `agentTable` |
| `/admin/deposits` | `admin_page_deposits` | `resources/admin/layui/deposits/index.blade.php` | `public/js/admin/layui/deposits/index.js` | `depositTable` |
| `/admin/withdrawals` | `admin_page_withdrawals` | `resources/admin/layui/withdrawals/index.blade.php` | `public/js/admin/layui/withdrawals/index.js` | `withdrawTable` |
| `/admin/commissions` | `admin_page_commissions` | `resources/admin/layui/commissions/index.blade.php` | `public/js/admin/layui/commissions/index.js` | `commissionTable` |
| `/admin/agent-levels` | `admin_page_agent_levels` | `resources/admin/layui/agent-levels/index.blade.php` | `public/js/admin/layui/agent-levels/index.js` | `agentLevelTable` |
| `/admin/group-configs` | `admin_page_group_configs` | `resources/admin/layui/group-configs/index.blade.php` | `public/js/admin/layui/group-configs/index.js` | `groupConfigTable` |
| `/admin/system-configs` | `admin_page_system_configs` | `resources/admin/layui/system-configs/index.blade.php` | `public/js/admin/layui/system-configs/index.js` | `systemConfigTable` |
| `/admin/channels` | `admin_page_channels` | `resources/admin/layui/channels/index.blade.php` | `public/js/admin/layui/channels/index.js` | `channelTable` |
| `/admin/admins` | `admin_page_admins` | `resources/admin/layui/admins/index.blade.php` | `public/js/admin/layui/admins/index.js` | `adminTable` |
| `/admin/news` | `admin_page_news` | `resources/admin/layui/news/index.blade.php` | `public/js/admin/layui/news/index.js` | `newsTable` |
| `/admin/vouchers` | `admin_page_vouchers` | `resources/admin/layui/vouchers/index.blade.php` | `public/js/admin/layui/vouchers/index.js` | `voucherTable` |
| `/admin/risk` | `admin_page_risk` | `resources/admin/layui/risk/index.blade.php` | `public/js/admin/layui/risk/index.js` | `riskTable` |
| `/admin/blacklist` | `admin_page_blacklist` | `resources/admin/layui/blacklist/index.blade.php` | `public/js/admin/layui/blacklist/index.js` | `blacklistTable` |
| `/admin/cancel-applies` | `admin_page_cancel_applies` | `resources/admin/layui/cancel-applies/index.blade.php` | `public/js/admin/layui/cancel-applies/index.js` | `cancelApplyTable` |
| `/admin/trades` | `admin_page_trades` | `resources/admin/layui/trades/index.blade.php` | `public/js/admin/layui/trades/index.js` | `tradeTable` |
| `/admin/big-agents` | `admin_page_big_agents` | `resources/admin/layui/big-agents/index.blade.php` | `public/js/admin/layui/big-agents/index.js` | `bigAgentTable` |

## 2. 页面接口绑定

| 页面模块 | 列表接口 | 操作接口 |
| --- | --- | --- |
| 代理管理 | `admin_api_agentList` | `admin_api_agentDescendants` |
| 入金管理 | `admin_api_depositList` | `admin_api_depositApprove`、`admin_api_depositReject` |
| 出金管理 | `admin_api_withdrawList` | `admin_api_withdrawProcess`、`admin_api_withdrawComplete`、`admin_api_withdrawReject` |
| 返佣管理 | `admin_api_commissionList` | `admin_api_commissionSettle` |
| 代理等级 | `admin_api_agentLevelList` | 后续继续补新�?/编辑按钮 |
| 组别配置 | `admin_api_groupConfigList` | 后续继续补新�?/编辑按钮 |
| 系统配置 | `admin_api_systemConfigList` | 后续继续补编辑按�? |
| 支付通道 | `admin_api_channelList` | 后续继续补启�?/禁用按钮 |
| 管理员账�? | `admin_api_adminList` | 后续继续补新�?/编辑/删除按钮 |
| 新闻公告 | `admin_api_newsList` | 后续继续补新�?/编辑/删除按钮 |
| 凭证审核 | `admin_api_voucherList` | `admin_api_voucherApprove`、`admin_api_voucherReject` |
| 风控管理 | `admin_api_riskPositions` | `admin_api_riskMarginCalls`、`admin_api_riskForceClose` |
| 黑名�? | `admin_api_blacklistList` | `admin_api_createBlacklist`、`admin_api_updateBlacklist`、`admin_api_deleteBlacklist` |
| 注销申请 | `admin_api_cancelApplyList` | `admin_api_cancelApplyApprove`、`admin_api_cancelApplyReject` |
| 交易订单 | `admin_api_tradeList` | `admin_api_openPositions`、`admin_api_closedPositions`、`admin_api_tradeSummary` |
| 大代�? | `admin_api_bigAgentList` | `admin_api_createBigAgent`、`admin_api_updateBigAgent`、`admin_api_deleteBigAgent` |

## 3. 权限表配�?

新增迁移�?

- `database/migrations/2026_06_06_000004_add_admin_business_module_permissions.php`
- `database/migrations/2026_06_06_000005_add_admin_second_batch_module_permissions.php`
- `database/migrations/2026_06_06_000006_add_admin_core_button_permissions.php`
- `database/migrations/2026_06_06_000007_add_admin_config_crud_permissions.php`

该迁移已执行到当前数据库，执行结果：

```text
Migrating: 2026_06_06_000004_add_admin_business_module_permissions
Migrated:  2026_06_06_000004_add_admin_business_module_permissions
Migrating: 2026_06_06_000005_add_admin_second_batch_module_permissions
Migrated:  2026_06_06_000005_add_admin_second_batch_module_permissions
Migrating: 2026_06_06_000006_add_admin_core_button_permissions
Migrated:  2026_06_06_000006_add_admin_core_button_permissions
Migrating: 2026_06_06_000007_add_admin_config_crud_permissions
Migrated:  2026_06_06_000007_add_admin_config_crud_permissions
```

## 13. 2026-06-07 批量入金/出金导入后台闭环

本轮继续按迁移缺口审计中�? P0 项推进，补齐旧项�? `BatchAmountController` 的第�?阶段新项目落点：批量入金/出金导入记录的后台页面�?�API、权限配置�?�多语言和中文注释�??

本轮新增文件�?

- `app/Http/Controllers/Admin/BatchAmountImportController.php`
- `resources/admin/layui/deposit-imports/index.blade.php`
- `resources/admin/layui/withdraw-imports/index.blade.php`
- `public/js/admin/layui/deposit-imports/index.js`
- `public/js/admin/layui/withdraw-imports/index.js`
- `database/migrations/2026_06_07_000004_add_admin_batch_amount_import_permissions.php`
- `tests/Feature/AdminBatchAmountImportModuleTest.php`
- `tests/Feature/AdminBatchAmountImportPermissionMigrationTest.php`

本轮修改文件�?

- `routes/admin.php`：新增批量入�?/出金导入 API 路由�?
- `routes/web.php`：新�? `/admin/deposit-imports`、`/admin/withdraw-imports` Blade 页面路由，放�? `/admin/{path?}` 兜底前�??
- `app/Models/DepositImport.php`、`app/Models/WithdrawImport.php`、`app/Models/CreditImport.php`：重写为可读中文功能注释和字段�?�辑说明�?
- `resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`：新增后端多语言消息�?
- `public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`：新增前端表格�?�弹窗和状�?�多语言文案�?
- `tests/Feature/AdminChineseCommentReadabilityTest.php`：扩大本轮新增文件的中文注释可读性覆盖�??

新增后台页面�?

| 页面 | 路由�? | Blade 文件 | JS 文件 | 表格 ID |
| --- | --- | --- | --- | --- |
| `/admin/deposit-imports` | `admin_page_deposit_imports` | `resources/admin/layui/deposit-imports/index.blade.php` | `public/js/admin/layui/deposit-imports/index.js` | `depositImportTable` |
| `/admin/withdraw-imports` | `admin_page_withdraw_imports` | `resources/admin/layui/withdraw-imports/index.blade.php` | `public/js/admin/layui/withdraw-imports/index.js` | `withdrawImportTable` |

新增后台 API�?

| 接口 | 路由�? | 控制器方�? | 参数说明 |
| --- | --- | --- | --- |
| `POST /api/admin/depositImportList` | `admin_api_depositImportList` | `BatchAmountImportController@depositImportList` | `user_id` 按业务用户筛选；`batch_no` 按批次号模糊筛�?�；`is_synced` 按同步状态筛选；`page/per_page/limit` 控制分页�? |
| `POST /api/admin/createDepositImport` | `admin_api_createDepositImport` | `BatchAmountImportController@createDepositImport` | `user_id` 必填且必须存在于 `user_infos.user_id`；`amount` 必填且大�? 0；`batch_no` 必填；`user_name` 可留空由后端按用�? ID 自动补全�? |
| `POST /api/admin/withdrawImportList` | `admin_api_withdrawImportList` | `BatchAmountImportController@withdrawImportList` | 参数含义同入金导入列表，但读�? `withdraw_imports` 表�?? |
| `POST /api/admin/createWithdrawImport` | `admin_api_createWithdrawImport` | `BatchAmountImportController@createWithdrawImport` | 参数含义同入金导入新增，但写�? `withdraw_imports` 表�?? |

新增权限配置�?

| slug | type | route/api_route | 功能 |
| --- | ---: | --- | --- |
| `admin_deposit_imports` | 1 | `/admin/deposit-imports` | 批量入金导入页面入口�? |
| `admin_batch_deposit_import_list` | 3 | `admin_api_depositImportList` | 批量入金导入列表接口权限�? |
| `admin_batch_deposit_import_create` | 3 | `admin_api_createDepositImport` | 新增批量入金导入记录按钮与接口权限�?? |
| `admin_withdraw_imports` | 1 | `/admin/withdraw-imports` | 批量出金导入页面入口�? |
| `admin_batch_withdraw_import_list` | 3 | `admin_api_withdrawImportList` | 批量出金导入列表接口权限�? |
| `admin_batch_withdraw_import_create` | 3 | `admin_api_createWithdrawImport` | 新增批量出金导入记录按钮与接口权限�?? |

实现边界�?

- 已完成：导入记录列表、筛选�?�新增单条导入记录�?�权限表配置、按钮权限�?�多语言、数据范围过滤�?�中文注释覆盖�??
- 暂未完成：旧项目 Excel/CSV 文件解析、导入失败重试�?�MT4 同步、导入模板下载�?�批量导出�?�这些属�? `BatchAmountController` 深层业务逻辑，下�?轮继续迁移�??

本轮验证命令�?

```text
php artisan migrate --force
结果�?2026_06_07_000004_add_admin_batch_amount_import_permissions 已成功执�?

vendor\bin\phpunit tests\Feature\AdminBatchAmountImportModuleTest.php
结果：OK (4 tests, 30 assertions)

vendor\bin\phpunit tests\Feature\AdminBatchAmountImportPermissionMigrationTest.php
结果：OK (1 test, 19 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
结果：OK (2 tests, 312 assertions)

php -l app\Http\Controllers\Admin\BatchAmountImportController.php
php -l database\migrations\2026_06_07_000004_add_admin_batch_amount_import_permissions.php
php -l app\Models\DepositImport.php
php -l app\Models\WithdrawImport.php
php -l app\Models\CreditImport.php
php -l routes\admin.php
php -l routes\web.php
php -l resources\lang\zh-CN\admin.php
php -l resources\lang\en\admin.php
结果：均�? No syntax errors detected

node --check public\js\admin\layui\deposit-imports\index.js
node --check public\js\admin\layui\withdraw-imports\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
结果：全部�??出码 0
```

写入的页面权限：

| slug | route |
| --- | --- |
| `admin_agents` | `/admin/agents` |
| `admin_deposits` | `/admin/deposits` |
| `admin_withdrawals` | `/admin/withdrawals` |
| `admin_commissions` | `/admin/commissions` |
| `admin_agent_levels` | `/admin/agent-levels` |
| `admin_group_configs` | `/admin/group-configs` |
| `admin_system_configs` | `/admin/system-configs` |
| `admin_channels` | `/admin/channels` |
| `admin_admins` | `/admin/admins` |
| `admin_news` | `/admin/news` |
| `admin_vouchers` | `/admin/vouchers` |
| `admin_risk` | `/admin/risk` |
| `admin_blacklist` | `/admin/blacklist` |
| `admin_cancel_applies` | `/admin/cancel-applies` |
| `admin_trades` | `/admin/trades` |
| `admin_big_agents` | `/admin/big-agents` |

写入�? API 权限�?

| slug | api_route |
| --- | --- |
| `admin_agent_list` | `admin_api_agentList` |
| `admin_agent_descendants` | `admin_api_agentDescendants` |
| `admin_deposit_list` | `admin_api_depositList` |
| `admin_deposit_approve` | `admin_api_depositApprove` |
| `admin_deposit_reject` | `admin_api_depositReject` |
| `admin_withdraw_list` | `admin_api_withdrawList` |
| `admin_withdraw_process` | `admin_api_withdrawProcess` |
| `admin_withdraw_complete` | `admin_api_withdrawComplete` |
| `admin_withdraw_reject` | `admin_api_withdrawReject` |
| `admin_commission_list` | `admin_api_commissionList` |
| `admin_commission_settle` | `admin_api_commissionSettle` |
| `admin_agent_level_list` | `admin_api_agentLevelList` |
| `admin_agent_level_create` | `admin_api_createAgentLevel` |
| `admin_agent_level_update` | `admin_api_updateAgentLevel2` |
| `admin_agent_level_delete` | `admin_api_deleteAgentLevel` |
| `admin_group_config_list` | `admin_api_groupConfigList` |
| `admin_group_config_create` | `admin_api_createGroupConfig` |
| `admin_group_config_update` | `admin_api_updateGroupConfig` |
| `admin_group_config_delete` | `admin_api_deleteGroupConfig` |
| `admin_system_config_list` | `admin_api_systemConfigList` |
| `admin_channel_list` | `admin_api_channelList` |
| `admin_admin_list` | `admin_api_adminList` |
| `admin_news_list` | `admin_api_newsList` |
| `admin_voucher_list` | `admin_api_voucherList` |
| `admin_voucher_approve` | `admin_api_voucherApprove` |
| `admin_voucher_reject` | `admin_api_voucherReject` |
| `admin_risk_positions` | `admin_api_riskPositions` |
| `admin_risk_margin_calls` | `admin_api_riskMarginCalls` |
| `admin_risk_force_close` | `admin_api_riskForceClose` |
| `admin_blacklist_list` | `admin_api_blacklistList` |
| `admin_blacklist_create` | `admin_api_createBlacklist` |
| `admin_blacklist_update` | `admin_api_updateBlacklist` |
| `admin_blacklist_delete` | `admin_api_deleteBlacklist` |
| `admin_cancel_apply_list` | `admin_api_cancelApplyList` |
| `admin_cancel_apply_approve` | `admin_api_cancelApplyApprove` |
| `admin_cancel_apply_reject` | `admin_api_cancelApplyReject` |
| `admin_trade_list` | `admin_api_tradeList` |
| `admin_open_positions` | `admin_api_openPositions` |
| `admin_closed_positions` | `admin_api_closedPositions` |
| `admin_trade_summary` | `admin_api_tradeSummary` |
| `admin_big_agent_list` | `admin_api_bigAgentList` |
| `admin_big_agent_create` | `admin_api_createBigAgent` |
| `admin_big_agent_update` | `admin_api_updateBigAgent` |
| `admin_big_agent_delete` | `admin_api_deleteBigAgent` |
| `admin_user_status` | `admin_api_changeUserStatus` |
| `admin_role_create` | `admin_api_createRole` |
| `admin_role_update` | `admin_api_updateRole` |
| `admin_role_delete` | `admin_api_deleteRole` |
| `admin_permission_update` | `admin_api_updatePermission` |
| `admin_menu_create` | `admin_api_createMenu` |
| `admin_menu_update` | `admin_api_updateMenu` |

## 4. 前端按钮权限控制

统一实现文件�?

- `public/js/admin/layui/layout.js`

实现规则�?

- `/api/admin/menus` 返回�? `permissions` 数组会写�? `window.CrmAdminPermissions` �? `localStorage.crm_admin_permissions`�?
- Blade 页面敏感按钮通过 `data-permission="permissions.slug"` 声明权限�?
- `layout.js` 统一扫描 `[data-permission]` 元素，当前管理员没有对应 slug 时自动隐藏�??
- 前端隐藏只做体验控制，后端接口仍�? `check.permission:admin` �? `permissions.api_route` 二次鉴权�?

已接入按钮权限的页面�?

- `/admin/users`
- `/admin/roles`
- `/admin/permissions`
- `/admin/menus`
- `/admin/data-scopes`
- `/admin/deposits`
- `/admin/withdrawals`
- `/admin/commissions`
- `/admin/vouchers`
- `/admin/risk`
- `/admin/cancel-applies`
- `/admin/blacklist`
- `/admin/big-agents`

本次补齐�? CRUD 入口�?

| 页面 | 新增按钮 | 编辑按钮权限 | 删除按钮权限 | 弹窗表单 |
| --- | --- | --- | --- | --- |
| `/admin/blacklist` | `id="addBlacklist"` / `admin_blacklist_create` | `admin_blacklist_update` | `admin_blacklist_delete` | `id="blacklistModal"`，参数：`id`、`name`、`id_card`、`email`、`phone`、`remark` |
| `/admin/big-agents` | `id="addBigAgent"` / `admin_big_agent_create` | `admin_big_agent_update` | `admin_big_agent_delete` | `id="bigAgentModal"`，参数：`id`、`username`、`password`、`status` |
| `/admin/agent-levels` | `id="addAgentLevel"` / `admin_agent_level_create` | `admin_agent_level_update` | `admin_agent_level_delete` | `id="agentLevelModal"`，参数：`id`、`level`、`name`、`max_commission`、`min_commission`、`user_commission` |
| `/admin/group-configs` | `id="addGroupConfig"` / `admin_group_config_create` | `admin_group_config_update` | `admin_group_config_delete` | `id="groupConfigModal"`，参数：`id`、`group_name`、`radix`、`category`、`has_commission`、`is_enabled`、`is_ecn`、`is_default` |

接口参数说明�?
- `admin_api_createBlacklist`：创建黑名单记录，`name` 为必填；`id_card`、`email`、`phone`、`remark` 用于补充识别和备注信息�??
- `admin_api_updateBlacklist`：更新黑名单记录，Laravel 路由参数 `id` 通过 `routeParams.id` 传入，表�? `id` 仅用于前端判断新�?/编辑模式�?
- `admin_api_deleteBlacklist`：删除黑名单记录，Laravel 路由参数 `id` 通过 `routeParams.id` 传入�?
- `admin_api_createBigAgent`：创建大代理账号，`username`、`password` 为必填，`status` 表示启用状�?��??
- `admin_api_updateBigAgent`：更新大代理账号，Laravel 路由参数 `id` 通过 `routeParams.id` 传入；`password` 留空时后端保留原密码�?
- `admin_api_deleteBigAgent`：删除大代理账号，Laravel 路由参数 `id` 通过 `routeParams.id` 传入�?
- `admin_api_createAgentLevel`：创建代理等级，`level` 映射到真实字�? `level_code`；`max_commission`、`min_commission`、`user_commission` 写入佣金配置�?
- `admin_api_updateAgentLevel2`：更新代理等级，Laravel 路由参数 `id` 通过 `routeParams.id` 传入�?
- `admin_api_deleteAgentLevel`：删除代理等级，Laravel 路由参数 `id` 通过 `routeParams.id` 传入�?
- `admin_api_createGroupConfig`：创建组别配置，`group_name` 映射到真实字�? `name`；`category` 表示 1=代理组�??2=用户组�??
- `admin_api_updateGroupConfig`：更新组别配置，Laravel 路由参数 `id` 通过 `routeParams.id` 传入�?
- `admin_api_deleteGroupConfig`：删除组别配置，Laravel 路由参数 `id` 通过 `routeParams.id` 传入�?

## 5. 多语�?

已补充：

- `resources/lang/zh-CN/admin.php`
- `resources/lang/en/admin.php`
- `public/js/common/lang/zh-CN.js`
- `public/js/common/lang/en.js`
- `public/js/common/i18n.js`

新增语言键覆盖：

- 页面标题：`admins`、`agent_levels`、`group_configs`、`system_configs`
- 筛�?�字段：`user_id`、`agent_id`、`user_name`、`keyword`
- 表格字段：`amount`、`status`、`name`、`code`、`level`、`configKey`、`configValue`、`username`、`title`、`publishStatus`、`updatedAt`
- 状�?�与动作：`pending`、`approved`、`rejected`、`processing`、`completed`、`settled`、`enabled`、`disabled`、`approve`、`reject`、`process`、`complete`、`settle`
- 第二批模块：`vouchers`、`review_status`、`reviewStatus`、`margin_calls`、`force_close`、`cancel_applies`、`trades`、`symbol`、`ticket`、`volume`、`profit`、`openTime`、`open_positions`、`closed_positions`、`idCard`、`phone`
- CRUD 弹窗：`password`、`create_blacklist`、`edit_blacklist`、`create_big_agent`、`edit_big_agent`
- 配置�? CRUD：`create_agent_level`、`edit_agent_level`、`max_commission`、`min_commission`、`user_commission`、`create_group_config`、`edit_group_config`、`group_name`、`radix`、`category`、`agent_group`、`user_group`

## 6. 交易模型修复

新增文件变更�?

- `app/Models/UserTrade.php`

新增查询作用域：

| scope | 作用 | 业务规则 |
| --- | --- | --- |
| `scopeOpen()` | 查询当前持仓 | `close_time = 1970-01-01 00:00:00` |
| `scopeClosed()` | 查询历史平仓 | `close_time != 1970-01-01 00:00:00` |

## 7. 已验证命�?

```text
vendor\bin\phpunit tests\Feature\AdminBladeModuleCoverageTest.php
结果：OK (20 tests, 60 assertions)

vendor\bin\phpunit tests\Feature\AdminLocalizationTest.php
结果：OK (2 tests, 10 assertions)

vendor\bin\phpunit tests\Feature\AdminBusinessPermissionMigrationTest.php
结果：OK (1 test, 163 assertions)

php artisan migrate --force
结果：第�?批与第二批后台业务模块权限迁移已执行成功

vendor\bin\phpunit tests\Feature\AdminSecondBatchModuleCoverageTest.php
结果：OK (34 tests, 80 assertions)

vendor\bin\phpunit tests\Feature\AdminSecondBatchPermissionMigrationTest.php
结果：OK (1 test, 163 assertions)

vendor\bin\phpunit tests\Feature\AdminButtonPermissionVisibilityTest.php
结果：OK (2 tests, 36 assertions)

vendor\bin\phpunit tests\Feature\AdminCorePermissionMigrationTest.php
结果：OK (1 test, 36 assertions)

php -l database\migrations\2026_06_06_000005_add_admin_second_batch_module_permissions.php
结果：No syntax errors detected

php -l database\migrations\2026_06_06_000006_add_admin_core_button_permissions.php
结果：No syntax errors detected

php -l database\migrations\2026_06_06_000007_add_admin_config_crud_permissions.php
结果：No syntax errors detected

php -l app\Http\Controllers\Admin\AgentLevelController.php
php -l app\Http\Controllers\Admin\GroupConfigController.php
php -l routes\admin.php
结果：No syntax errors detected

php -l app\Models\UserTrade.php
结果：No syntax errors detected

node --check public\js\admin\layui\vouchers\index.js
node --check public\js\admin\layui\risk\index.js
node --check public\js\admin\layui\blacklist\index.js
node --check public\js\admin\layui\cancel-applies\index.js
node --check public\js\admin\layui\trades\index.js
node --check public\js\admin\layui\big-agents\index.js
结果：全部�??出码 0

node --check public\js\admin\layui\layout.js
结果：�??出码 0

vendor\bin\phpunit tests\Feature\AdminCrudUiControlsTest.php
结果：OK (4 tests, 35 assertions)

vendor\bin\phpunit tests\Feature\AdminConfigCrudPermissionMigrationTest.php
结果：OK (2 tests, 39 assertions)

node --check public\js\admin\layui\blacklist\index.js
结果：�??出码 0

node --check public\js\admin\layui\big-agents\index.js
结果：�??出码 0

node --check public\js\admin\layui\agent-levels\index.js
node --check public\js\admin\layui\group-configs\index.js
结果：�??出码 0

node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
node --check public\js\common\i18n.js
结果：全部�??出码 0
```

## 8. 2026-06-07 内容与账号类 CRUD 补齐

本轮已补�? `/admin/channels`、`/admin/admins`、`/admin/news` 三个后台 Blade 页面，继续保持�?�Blade 渲染页面 + JS 调用接口 + permissions 数据表驱动按钮显�? + 后端中间件二次鉴权�?�的实现方式�?

本轮补齐�? CRUD 入口�?

| 页面 | 新增按钮 | 编辑按钮权限 | 删除按钮权限 | 弹窗表单 |
| --- | --- | --- | --- | --- |
| `/admin/channels` | `id="addChannel"` / `admin_channel_create` | `admin_channel_update` | `admin_channel_delete` | `id="channelModal"`，参数：`id`、`name`、`channel_code`、`exchange_rate`、`sort`、`is_enabled`、`config` |
| `/admin/admins` | `id="addAdmin"` / `admin_admin_create` | `admin_admin_update` | `admin_admin_delete` | `id="adminModal"`，参数：`id`、`username`、`email`、`password` |
| `/admin/news` | `id="addNews"` / `admin_news_create` | `admin_news_update` | `admin_news_delete` | `id="newsModal"`，参数：`id`、`title`、`is_published`、`content` |

本轮新增或调整的接口�?

- `admin_api_createChannel`：`POST /api/admin/createChannel`，创建支付�?�道；`name`、`channel_code` 为必填，`exchange_rate`、`sort`、`is_enabled`、`config` 为�?�道扩展参数�?
- `admin_api_updateChannel`：`POST /api/admin/updateChannel/{id}`，更新指定支付�?�道；Laravel 路由参数 `id` 通过 `routeParams.id` 传入�?
- `admin_api_deleteChannel`：`POST /api/admin/deleteChannel/{id}`，删除指定支付�?�道；删除入口必须具�? `admin_channel_delete`�?
- `admin_api_createAdmin`：`POST /api/admin/createAdmin`，创建后台管理员；`username`、`email`、`password` 为必填�??
- `admin_api_updateAdmin`：`POST /api/admin/updateAdmin/{id}`，更新后台管理员；编辑时 `password` 留空表示不修改密码�??
- `admin_api_deleteAdmin`：`POST /api/admin/deleteAdmin/{id}`，删除后台管理员；删除入口必须具�? `admin_admin_delete`�?
- `admin_api_createNews`：`POST /api/admin/createNews`，创建新闻公告；`title`、`content` 为必填，`is_published` 控制发布状�?��??
- `admin_api_updateNews`：`POST /api/admin/updateNews/{id}`，更新指定新闻公告；Laravel 路由参数 `id` 通过 `routeParams.id` 传入�?
- `admin_api_deleteNews`：`POST /api/admin/deleteNews/{id}`，删除指定新闻公告；删除入口必须具备 `admin_news_delete`�?

本轮新增权限迁移�?

- `database/migrations/2026_06_07_000001_add_admin_content_crud_permissions.php`

写入�? `permissions.slug`�?

- `admin_channel_create`、`admin_channel_update`、`admin_channel_delete`
- `admin_admin_create`、`admin_admin_update`、`admin_admin_delete`
- `admin_news_create`、`admin_news_update`、`admin_news_delete`

本轮补充多语�?键：

- Laravel 后端语言包：`resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`
- 前端运行时语�?包：`public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`
- 新增键覆盖：`exchange_rate`、`sort`、`config`、`config_json_placeholder`、`create_channel`、`edit_channel`、`create_admin`、`edit_admin`、`password_keep_placeholder`、`content`、`published`、`unpublished`、`create_news`、`edit_news`

本轮新增测试�?

- `tests/Feature/AdminContentCrudPermissionMigrationTest.php`
- `tests/Feature/AdminCrudUiControlsTest.php` 新增 channels/admins/news 三个页面控件覆盖断言�?

本轮验证命令�?

```text
php artisan migrate --force
结果�?2026_06_07_000001_add_admin_content_crud_permissions 已执行成�?

vendor\bin\phpunit tests\Feature\AdminCrudUiControlsTest.php
结果：OK (7 tests, 62 assertions)

vendor\bin\phpunit tests\Feature\AdminContentCrudPermissionMigrationTest.php
结果：OK (2 tests, 64 assertions)

vendor\bin\phpunit tests\Feature\AdminBladeModuleCoverageTest.php
结果：OK (20 tests, 60 assertions)

vendor\bin\phpunit tests\Feature\AdminButtonPermissionVisibilityTest.php
结果：OK (2 tests, 36 assertions)

vendor\bin\phpunit tests\Feature\AdminLocalizationTest.php
结果：OK (2 tests, 10 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
结果：OK (1 test, 72 assertions)

php -l routes\admin.php
php -l database\migrations\2026_06_07_000001_add_admin_content_crud_permissions.php
php -l resources\lang\zh-CN\admin.php
php -l resources\lang\en\admin.php
结果：No syntax errors detected

node --check public\js\admin\layui\channels\index.js
node --check public\js\admin\layui\admins\index.js
node --check public\js\admin\layui\news\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
结果：全部�??出码 0
```

## 9. 2026-06-07 系统配置编辑闭环补齐

本轮已补�? `/admin/system-configs` 的编辑入口，修正页面使用的字段与真实 `system_configs` 表字段一致：`key`、`value`、`group`、`description`�?

本轮补齐的操作入口：

| 页面 | 编辑按钮权限 | 弹窗表单 | 说明 |
| --- | --- | --- | --- |
| `/admin/system-configs` | `admin_system_config_update` | `id="systemConfigModal"`，参数：`id`、`key`、`value`、`group`、`description` | `key` 只读用于识别配置项，`value/group/description` 可维�? |

本轮接口行为�?

- `admin_api_systemConfigList`：`POST /api/admin/systemConfigList`；传�? `page/per_page/limit` 时返回平铺分页数据，便于 Layui 表格渲染；不传分页参数时仍兼容旧版按 `group` 分组返回�?
- `admin_api_updateSystemConfig`：`POST /api/admin/updateSystemConfig`；支持单行编辑参�? `id/key/value/group/description`，也保留旧版 `configs[key]=value` 批量更新格式�?

本轮新增权限迁移�?

- `database/migrations/2026_06_07_000002_add_admin_system_config_update_permission.php`

写入�? `permissions.slug`�?

- `admin_system_config_update`，绑�? `api_route=admin_api_updateSystemConfig`

本轮补充多语�?键：

- Laravel 后端语言包：`system_config_not_found`、`group`、`description`、`edit_system_config`
- 前端运行时语�?包：`group`、`description`、`edit_system_config`

本轮新增测试�?

- `tests/Feature/AdminSystemConfigUpdatePermissionMigrationTest.php`
- `tests/Feature/AdminSystemConfigUpdateControllerTest.php`
- `tests/Feature/AdminCrudUiControlsTest.php` 新增系统配置编辑控件覆盖断言�?

本轮验证命令�?

```text
php artisan migrate --force
结果�?2026_06_07_000002_add_admin_system_config_update_permission 已执行成�?

vendor\bin\phpunit tests\Feature\AdminCrudUiControlsTest.php
结果：OK (8 tests, 69 assertions)

vendor\bin\phpunit tests\Feature\AdminSystemConfigUpdatePermissionMigrationTest.php
结果：OK (1 test, 6 assertions)

vendor\bin\phpunit tests\Feature\AdminSystemConfigUpdateControllerTest.php
结果：OK (2 tests, 4 assertions)

vendor\bin\phpunit tests\Feature\AdminBladeModuleCoverageTest.php
结果：OK (20 tests, 60 assertions)

vendor\bin\phpunit tests\Feature\AdminButtonPermissionVisibilityTest.php
结果：OK (2 tests, 36 assertions)

vendor\bin\phpunit tests\Feature\AdminLocalizationTest.php
结果：OK (2 tests, 10 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
结果：OK (1 test, 72 assertions)

php -l app\Http\Controllers\Admin\SystemConfigController.php
php -l database\migrations\2026_06_07_000002_add_admin_system_config_update_permission.php
php -l resources\lang\zh-CN\admin.php
php -l resources\lang\en\admin.php
结果：No syntax errors detected

node --check public\js\admin\layui\system-configs\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
结果：全部�??出码 0
```

## 10. 2026-06-07 代理操作入口补齐

本轮已补�? `/admin/agents` 的业务操作入口，代理列表行内提供下级查看、等级调整�?�佣金调整，并全部绑�? `permissions.slug`�?

本轮补齐的操作入口：

| 页面 | 下级查看权限 | 等级调整权限 | 佣金调整权限 | 弹窗表单 |
| --- | --- | --- | --- | --- |
| `/admin/agents` | `admin_agent_descendants` | `admin_agent_update_level` | `admin_agent_update_commission` | `agentLevelUpdateModal` 参数：`agent_id`、`level`；`agentCommissionUpdateModal` 参数：`agent_id`、`comm_rate` |

本轮接口行为�?

- `admin_api_agentDescendants`：`POST /api/admin/agentDescendants`；参�? `agent_id` 表示业务代理用户 ID，接口继续执行数据范围校验�??
- `admin_api_updateAgentLevel`：`POST /api/admin/updateAgentLevel`；参�? `agent_id`、`level`，用于更�? `user_infos.agent_level`�?
- `admin_api_updateAgentCommission`：`POST /api/admin/updateAgentCommission`；参�? `agent_id`、`comm_rate`，`comm_rate` �? 0 �? 1 的佣金比例�??

本轮新增权限迁移�?

- `database/migrations/2026_06_07_000003_add_admin_agent_operation_permissions.php`

写入�? `permissions.slug`�?

- `admin_agent_update_level`，绑�? `api_route=admin_api_updateAgentLevel`
- `admin_agent_update_commission`，绑�? `api_route=admin_api_updateAgentCommission`

本轮补充多语�?键：

- `update_agent_level`
- `update_agent_commission`

本轮新增测试�?

- `tests/Feature/AdminAgentOperationPermissionMigrationTest.php`
- `tests/Feature/AdminCrudUiControlsTest.php` 新增代理操作控件覆盖断言�?

本轮验证命令�?

```text
php artisan migrate --force
结果�?2026_06_07_000003_add_admin_agent_operation_permissions 已执行成�?

vendor\bin\phpunit tests\Feature\AdminCrudUiControlsTest.php
结果：OK (9 tests, 78 assertions)

vendor\bin\phpunit tests\Feature\AdminAgentOperationPermissionMigrationTest.php
结果：OK (1 test, 11 assertions)

vendor\bin\phpunit tests\Feature\AdminBladeModuleCoverageTest.php
结果：OK (20 tests, 60 assertions)

vendor\bin\phpunit tests\Feature\AdminButtonPermissionVisibilityTest.php
结果：OK (2 tests, 36 assertions)

vendor\bin\phpunit tests\Feature\AdminLocalizationTest.php
结果：OK (2 tests, 10 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
结果：OK (1 test, 72 assertions)

php -l database\migrations\2026_06_07_000003_add_admin_agent_operation_permissions.php
php -l resources\lang\zh-CN\admin.php
php -l resources\lang\en\admin.php
结果：No syntax errors detected

node --check public\js\admin\layui\agents\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
结果：全部�??出码 0
```

## 11. 后续仍需继续补齐

- 继续�? `docs/admin-auth-permission-plan.md` 审计剩余已接入页面，优先补齐仍只有列表壳、缺少业务操作弹窗或缺少细粒度按钮权限的模块�?
- 继续把旧文件中残留的乱码注释分批改成可读中文，优先处理本次直接涉及的控制器和中间件�??
- 继续细化按钮权限：前端隐藏按钮只做体验优化，后端 `check.permission:admin` 仍是安全边界�?
## 12. 2026-06-07 新旧项目迁移缺口审计与真�? DB 测试数据

本轮按用户要求继续深入对比旧项目后台控制器与新项目后台模块，并新增可验证审计文档�?

- 新增审计文档：`docs/admin-legacy-migration-gap-audit.md`
- 新增测试：`tests/Feature/AdminLegacyMigrationGapAuditTest.php`
- 重写并扩展中文注释可读�?�测试：`tests/Feature/AdminChineseCommentReadabilityTest.php`

审计结论�?

| 状�?? | 说明 |
| --- | --- |
| 已迁�? | 登录、管理员、角色�?�权限�?�菜单�?�用户�?�代理基�?列表、入金审核�?�出金审核�?�返佣列表�?�系统配置�?�支付�?�道、新闻�?�黑名单、注�?申请、交易列表�?�凭证审核�?�大代理基础管理�? |
| 部分迁移 | 代理 V3 复杂统计、持�?/平仓统计、权益汇总�?�风控�?�认证审核�?�出入金流水�? |
| 未迁�? | 批量入金/出金导入、批量信用导入�?�汇率维护�?�礼品发放与发货、在线用户�?�未入金流水、产�?/交易品种管理、大编号后台、实时返佣明细�?? |

真实 DB 测试数据来源�?

- 采样连接：`127.0.0.1:3307 / co_crmv5`
- 采样方式：�?�过 `php artisan tinker` 直接读取当前 DB 表�??
- 采样时间：`2026-06-07 Asia/Shanghai`

真实数量�?

| 表或业务口径 | 数量 |
| --- | ---: |
| `admins` | 41 |
| `user_infos` | 40 |
| `agents`，来�? `user_infos.account_type = 1` | 5 |
| `customers`，来�? `user_infos.account_type <> 1` | 35 |
| `permissions` | 113 |
| `roles` | 50 |
| `system_configs` | 4 |
| `payment_channels` | 0 |

本轮发现的重要字段差异：

- 当前真实 DB �? `user_infos` 表使�? `level_id` 表示代理等级，不存在 `agent_level` 字段�?
- `payment_channels` 当前为空表，因此支付通道模块后续测试只能验证空列表�?�新增和编辑流程，不能编造已有支付�?�道样本�?

本轮新增验证命令�?

```text
vendor\bin\phpunit tests\Feature\AdminLegacyMigrationGapAuditTest.php
结果：OK (2 tests, 30 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
结果：OK (2 tests, 204 assertions)
```

## 14. 2026-06-07 批量信用导入后台闭环

本轮继续对比旧项�? `BatchCreditController` 与新项目后台模块，完成批量信用导入第�?阶段迁移闭环。当前阶段先实现列表、筛选�?�手工新增�?�页面入口�?�权限配置�?�多语言和数据范围控制；Excel/CSV 文件解析、MT4 同步、失败重试和模板下载仍保留为后续深层迁移任务�?

### 新增和维护文�?

- `app/Http/Controllers/Admin/BatchCreditImportController.php`：批量信用导入后台控制器，参�? `user_id`、`credit_type`、`amount`、`batch_no`、`is_synced` 均有中文逻辑注释�?
- `app/Models/CreditImport.php`：批量信用导入模型，对应真实数据�? `credit_imports`�?
- `resources/admin/layui/credit-imports/index.blade.php`：Laravel Blade 页面，渲染筛选区、列表表格和新增弹窗�?
- `public/js/admin/layui/credit-imports/index.js`：Layui 页面脚本，负责表格渲染�?�筛选�?�新增弹窗和按钮权限刷新�?
- `database/migrations/2026_06_07_000005_add_admin_batch_credit_import_permissions.php`：从数据表写入页面和按钮/API 权限配置�?
- `tests/Feature/AdminBatchCreditImportModuleTest.php`：验证页面路由�?�Blade 控件�? API 权限中间件�??
- `tests/Feature/AdminBatchCreditImportPermissionMigrationTest.php`：验证权限迁移写�? `permissions.slug` �? `permissions.api_route`�?
- `tests/Feature/AdminChineseCommentReadabilityTest.php`：扩大中文注释可读�?�覆盖，防止本轮维护文件再次出现乱码�?

### 页面与接�?

| 类型 | 名称 | 地址或路由名 | 说明 |
| --- | --- | --- | --- |
| Blade 页面 | 批量信用导入 | `/admin/credit-imports` / `admin_page_credit_imports` | 当前项目非前后端分离，页面由 Laravel Blade 渲染�? |
| API | 列表查询 | `POST /api/admin/creditImportList` / `admin_api_creditImportList` | 支持 `page`、`per_page`、`limit`、`user_id`、`batch_no`、`credit_type`、`is_synced`�? |
| API | 新增记录 | `POST /api/admin/createCreditImport` / `admin_api_createCreditImport` | 写入真实 `credit_imports` 表，`user_id` 必须存在�? `user_infos.user_id`�? |

### 数据表权限配�?

本轮新增权限全部来自 `permissions` 表配置，不在代码中硬编码角色授权�?

| permissions.slug | permissions.api_route | 用�?? |
| --- | --- | --- |
| `admin_credit_imports` | �? | 批量信用导入页面/菜单节点�? |
| `admin_batch_credit_import_list` | `admin_api_creditImportList` | 批量信用导入列表查询接口权限�? |
| `admin_batch_credit_import_create` | `admin_api_createCreditImport` | 新增批量信用导入记录接口权限和页面按钮权限�?? |

### 多语�?支持

已补�? Laravel 后端语言包和前端运行时语�?包：

- `resources/lang/zh-CN/admin.php`
- `resources/lang/en/admin.php`
- `public/js/common/lang/zh-CN.js`
- `public/js/common/lang/en.js`

新增键包括：`credit_imports`、`credit_imports_fetched`、`credit_import_created`、`credit_type`、`credit_type_temp`、`credit_type_permanent`、`credit_type_reward`、`credit_type_other`、`create_credit_import`�?

### 真实 DB 测试数据来源

- 连接：`127.0.0.1:3307 / co_crmv5 / root / 123456`
- 当前真实表：`credit_imports`
- 字段：`id`、`user_id`、`user_name`、`credit_type`、`mt4_order_id`、`amount`、`batch_no`、`is_synced`、`fail_reason`、`remarks`、`created_by`、`updated_by`、`created_at`、`updated_at`、`deleted_at`
- 当前真实记录数：`0`

说明：由�? `credit_imports` 当前为空表，本轮测试不伪造已有信用导入样本，只验证空列表、页面入口�?�接口注册�?�权限配置和手工新增入口。后续做 Excel/CSV 导入�? MT4 同步时再基于真实导入数据扩展测试样本�?

### 已完成边�?

- 已完成批量信用导�? Blade 页面�? Layui 脚本�?
- 已完成列表查询接口和手工新增接口�?
- 已完�? `permissions` 数据表驱动的页面、按钮和 API 权限配置�?
- 已完成后端与前端多语�?键�??
- 已完成中文注释乱码修复，覆盖本轮信用导入、批量入�?/出金导入、系统配置�?�代理操作相�? JS 文件�?
- 已接入后台数据范围过滤，列表按当前管理员角色和绑定代理过滤可见业务用户数据�??

### 后续未完成边�?

- 未完�? Excel/CSV 上传解析�?
- 未完�? MT4 信用同步�?
- 未完成失败导入重试�??
- 未完成导入模板下载�??
- 未完成旧项目其它 P0 模块：出入金流水、持仓汇总�?�权益汇总等�?

### 本轮验证命令

```text
php artisan migrate --force
vendor\bin\phpunit tests\Feature\AdminBatchCreditImportModuleTest.php
vendor\bin\phpunit tests\Feature\AdminBatchCreditImportPermissionMigrationTest.php
vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
php -l app\Http\Controllers\Admin\BatchCreditImportController.php
php -l app\Models\CreditImport.php
php -l database\migrations\2026_06_07_000005_add_admin_batch_credit_import_permissions.php
php -l routes\admin.php
php -l routes\web.php
php -l resources\lang\zh-CN\admin.php
php -l resources\lang\en\admin.php
node --check public\js\admin\layui\deposit-imports\index.js
node --check public\js\admin\layui\withdraw-imports\index.js
node --check public\js\admin\layui\credit-imports\index.js
node --check public\js\admin\layui\system-configs\index.js
node --check public\js\admin\layui\agents\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
```

### 本轮实际验证结果

```text
php artisan migrate --force
结果：Migrated 2026_06_07_000005_add_admin_batch_credit_import_permissions (14.75ms)

vendor\bin\phpunit tests\Feature\AdminBatchCreditImportModuleTest.php
结果：OK (3 tests, 16 assertions)

vendor\bin\phpunit tests\Feature\AdminBatchCreditImportPermissionMigrationTest.php
结果：OK (1 test, 10 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
结果：OK (2 tests, 544 assertions)

php -l app\Http\Controllers\Admin\BatchCreditImportController.php
php -l app\Models\CreditImport.php
php -l database\migrations\2026_06_07_000005_add_admin_batch_credit_import_permissions.php
php -l routes\admin.php
php -l routes\web.php
php -l resources\lang\zh-CN\admin.php
php -l resources\lang\en\admin.php
结果：全�? No syntax errors detected

node --check public\js\admin\layui\deposit-imports\index.js
node --check public\js\admin\layui\withdraw-imports\index.js
node --check public\js\admin\layui\credit-imports\index.js
node --check public\js\admin\layui\system-configs\index.js
node --check public\js\admin\layui\agents\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
结果：全部�??出码 0

真实 DB 只读采样
结果�?
credit_imports: 0
permissions: 122
admin_credit_imports => �? api_route
admin_batch_credit_import_list => admin_api_creditImportList
admin_batch_credit_import_create => admin_api_createCreditImport
```

## 15. 2026-06-07 前台代理商菜单父级保留修�?

本次针对前台 Layui 风格�? agent 账号登录后菜单可能丢失的问题，审计了前台布局、菜单接口和 `MenuService` 的权限过滤�?�辑�?

### 问题原因

- 前台 Layui 布局文件 `resources/front/layui/layouts/app.blade.php` 中的左侧菜单容器 `#sideMenu` 不是写死菜单，�?�是�? `public/js/front/layui/layout.js` 登录后调�? `POST /api/front/navigation/menus` 动�?�渲染�??
- 该接口最终进�? `App\Http\Controllers\Front\MenuController@userMenus`，再调用 `App\Services\MenuService::getUserMenus('front', $permissionIds)`�?
- 原�?�辑在传�? `$permissionIds` 时只保留 `id in $permissionIds` 的顶级菜单�?�如果角色只授权�? `front_agent_sub`、`front_agent_customers` 等子菜单，但没有显式授权父级 `front_agent`，父级容器会被过滤掉，子菜单也无法展示�??

### 本次修复

- 修改 `app/Services/MenuService.php`�?
  - 父级菜单自身被授权时继续显示�?
  - 父级菜单未直接授权，但存在已授权子菜单时，也保留父级菜单容器�?
  - 子菜单列表仍然按 `$permissionIds` 过滤，避免未授权页面被展示�??

### 新增回归测试

- 修改 `tests/Feature/AdminPermissionPlanTest.php`，新增：
  - `test_front_menu_tree_keeps_parent_when_only_child_permission_is_granted`
  - 覆盖“前台角色只授权子菜单时必须保留父级菜单”的场景�?

### 后台登录信息复核

- 后台登录页面路由：`GET /admin/login`
- 后台登录接口：`POST /api/admin/login`
- `database/seeders/InitialDataSeeder.php` 中默认超级管理员�?
  - 账号：`admin@crmv5.com`
  - 用户名：`superadmin`
  - 密码：`Admin@123456`

### 本次验证结果

```text
php -l app\Services\MenuService.php
结果：No syntax errors detected

php -l tests\Feature\AdminPermissionPlanTest.php
结果：No syntax errors detected

vendor\bin\phpunit tests\Feature\AdminPermissionPlanTest.php --filter front_menu_tree_keeps_parent
结果：未进入业务断言，当前本�? MySQL 127.0.0.1:3307 拒绝连接�?
错误：SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接�?
```

### 待数据库恢复后复�?

数据库连接恢复后，需要继续用真实 DB 复核以下数据�?

- `permissions` �? `guard_type=front` 的菜单树是否包含代理商父级与子级菜单�?
- agent 登录账号对应的角色是否�?�过 `role_permissions` 授权了前台代理商菜单�?
- `POST /api/front/navigation/menus` �? agent token 下是否返�? `front_agent` 及其子菜单�??

## 16. 2026-06-07 后台资金流水模块第一阶段

本轮继续按迁移缺口审计中�? P0/P1 资金核对链路推进，补齐旧项目 `WithdrawFlowController` �? `UnDepositAmountController` 的第�?阶段新项目落点�?�当前阶段先实现后台 Blade 页面、Layui 脚本、列�? API、权限配置�?�多语言文案、中文�?�辑注释和测试覆盖；导出、MT4 COMMENT 细分分类、失败重试和复杂财务复核流程保留为后续深层迁移任务�??

### 新增和维护文�?

- `app/Http/Controllers/Admin/FundFlowController.php`：后台资金流水控制器，负责出金流水和未入金流水列表�?�方法注释说明了 `user_id`、`ticket`、`local_order_no`、`channel_order_no`、`start_date`、`end_date`、`page`、`limit` 等参数的来源、含义和筛�?�作用�??
- `app/Models/Mt4Trade.php`：MT4 交易模型，对应真实交易表 `mt4_trades`，用于出金流水按余额类交易口径读取�??
- `resources/admin/layui/withdraw-flows/index.blade.php`：后台出金流�? Blade 页面，包含筛选区、表格区和页面模块说明注释�??
- `resources/admin/layui/undeposit-flows/index.blade.php`：后台未入金流水 Blade 页面，包含待支付入金记录筛�?�与表格展示�?
- `public/js/admin/layui/withdraw-flows/index.js`：出金流�? Layui 页面脚本，负责读取筛选参数�?�渲染表格�?�统�?多语�?文案和刷新动作�??
- `public/js/admin/layui/undeposit-flows/index.js`：未入金流水 Layui 页面脚本，负责订单号、�?�道单号、用�? ID 和时间范围筛选�??
- `database/migrations/2026_06_07_000006_add_admin_fund_flow_permissions.php`：资金流水权限迁移，�? `permissions` 表写入页面入口和 API 权限配置�?
- `tests/Feature/AdminFundFlowModuleTest.php`：资金流水模块页面�?�路由�?�控制器和数据筛选的功能测试�?
- `tests/Feature/AdminFundFlowPermissionMigrationTest.php`：资金流水权限迁移测试，验证 `permissions.slug` �? `permissions.api_route` 配置�?
- `tests/Feature/AdminChineseCommentReadabilityTest.php`：扩大中文注释可读�?�覆盖，防止新增资金流水文件缺少中文逻辑说明�?

### 页面与接�?

| 类型 | 名称 | 地址或路由名 | 说明 |
| --- | --- | --- | --- |
| Blade 页面 | 出金流水 | `/admin/withdraw-flows` / `admin_page_withdraw_flows` | 用于核对 MT4 余额类出金交易，页面�? Laravel Blade 渲染�? |
| Blade 页面 | 未入金流�? | `/admin/undeposit-flows` / `admin_page_undeposit_flows` | 用于核对待支付或未完成入金记录，页面�? Laravel Blade 渲染�? |
| API | 出金流水列表 | `POST /api/admin/withdrawFlowList` / `admin_api_withdrawFlowList` | 读取 `mt4_trades`，按 `cmd=6`、`open_price=0`、`profit<0` 识别出金流水�? |
| API | 未入金流水列�? | `POST /api/admin/undepositFlowList` / `admin_api_undepositFlowList` | 读取 `deposit_records`，按 `status='01'` 识别未入金记录�?? |

### 数据表权限配�?

本轮权限仍然坚持“数据表配置驱动鉴权”，页面入口、按�?/API 权限均写�? `permissions` 表，后续�? `role_permissions` 分配给不同后台管理员角色�?

| permissions.slug | permissions.api_route | 类型 | 用�?? |
| --- | --- | ---: | --- |
| `admin_withdraw_flows` | �? | 1 | 后台出金流水页面/菜单节点�? |
| `admin_withdraw_flow_list` | `admin_api_withdrawFlowList` | 3 | 出金流水列表接口权限�? |
| `admin_undeposit_flows` | �? | 1 | 后台未入金流水页�?/菜单节点�? |
| `admin_undeposit_flow_list` | `admin_api_undepositFlowList` | 3 | 未入金流水列表接口权限�?? |

### 数据范围与业务口�?

- 出金流水列表使用 `AdminDataScopeService->apply(..., 'trade', 'login')` 追加后台管理员数据可见范围�?�`login` 参数对应 MT4 登录账号，用于把资金流水限制在当前角色允许查看的客户或代理范围内�?
- 未入金流水列表使�? `AdminDataScopeService->apply(..., 'deposit', 'user_id')` 追加数据范围。`user_id` 参数对应业务用户 ID，避免普通财务或客服角色看到未授权客户的入金记录�?
- 出金流水当前�? MT4 余额类交易识别：`cmd=6` 表示余额类记录，`open_price=0` 表示非持仓交易，`profit<0` 表示资金流出�?
- 未入金流水当前按 `deposit_records.status='01'` 识别，后续如旧项目存在更多未入金状�?�，�?要继续基于真�? DB 状�?�字典扩展�??

### 多语�?支持

已补�? Laravel 后端语言包和前端运行时语�?包：

- `resources/lang/zh-CN/admin.php`
- `resources/lang/en/admin.php`
- `public/js/common/lang/zh-CN.js`
- `public/js/common/lang/en.js`

新增键包含：`withdraw_flows`、`undeposit_flows`、`withdraw_flows_fetched`、`undeposit_flows_fetched`、`ticket`、`channel_order_no`、`local_order_no`、`profit`、`close_time` 等资金流水相关文案�??

### 真实 DB 验证状�??

当前本机 `127.0.0.1:3307` 仍拒绝连接，因此本轮不能完成真实 DB 的迁移写入复核和接口真实数据抽样。已确认阻塞不是业务断言失败，�?�是数据库端口不可达�?

```text
Test-NetConnection 127.0.0.1 -Port 3307
结果：TcpTestSucceeded=False
```
数据库恢复后必须继续执行�?

```text
php artisan migrate --force
vendor\bin\phpunit tests\Feature\AdminFundFlowPermissionMigrationTest.php
vendor\bin\phpunit tests\Feature\AdminPermissionPlanTest.php --filter front_menu_tree_keeps_parent
```

并用真实 DB 复核�?

- `permissions` 中是否存�? `admin_withdraw_flows`、`admin_withdraw_flow_list`、`admin_undeposit_flows`、`admin_undeposit_flow_list`�?
- `role_permissions` 是否已按后台管理员角色分配资金流水页面和列表接口权限�?
- `POST /api/admin/withdrawFlowList` 是否能按真实 `mt4_trades` 返回出金流水�?
- `POST /api/admin/undepositFlowList` 是否能按真实 `deposit_records` 返回未入金流水�??

### 本轮已完成边�?

- 已完成出金流水和未入金流�? Blade 页面入口�?
- 已完成两个列�? API，并接入后台数据范围过滤�?
- 已完�? `permissions` 数据表驱动的页面�? API 权限配置迁移�?
- 已完成后端与前端多语�?文案�?
- 已完成新增文件的中文逻辑注释覆盖�?

### 后续未完成边�?

- 未完成出金流水导出�??
- 未完成未入金流水导出�?
- 未完成旧项目 MT4 COMMENT 分类、人工复核和财务导出格式的深度迁移�??
- 未完成真�? DB 迁移写入复核，原因是当前 MySQL `3307` 端口拒绝连接�?

### 本轮验证命令

```text
php -l app\Http\Controllers\Admin\FundFlowController.php
php -l app\Models\Mt4Trade.php
php -l database\migrations\2026_06_07_000006_add_admin_fund_flow_permissions.php
php -l routes\web.php
php -l routes\admin.php
php -l resources\lang\zh-CN\admin.php
php -l resources\lang\en\admin.php
vendor\bin\phpunit tests\Feature\AdminFundFlowModuleTest.php
vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
node --check public\js\admin\layui\withdraw-flows\index.js
node --check public\js\admin\layui\undeposit-flows\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
```

### 本轮实际验证结果

```text
php -l app\Http\Controllers\Admin\FundFlowController.php
php -l app\Models\Mt4Trade.php
php -l database\migrations\2026_06_07_000006_add_admin_fund_flow_permissions.php
php -l routes\web.php
php -l routes\admin.php
php -l resources\lang\zh-CN\admin.php
php -l resources\lang\en\admin.php
结果：全�? No syntax errors detected

node --check public\js\admin\layui\withdraw-flows\index.js
node --check public\js\admin\layui\undeposit-flows\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
结果：全部�??出码 0

vendor\bin\phpunit tests\Feature\AdminFundFlowModuleTest.php
结果：OK (4 tests, 20 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
结果：OK (2 tests, 663 assertions)

php artisan route:list --path=withdrawFlowList
结果：POST api/admin/withdrawFlowList 已注册，路由�? admin_api_withdrawFlowList，中间件包含 jwt.auth:admin、sso:admin、check.permission:admin

php artisan route:list --path=undepositFlowList
结果：POST api/admin/undepositFlowList 已注册，路由�? admin_api_undepositFlowList，中间件包含 jwt.auth:admin、sso:admin、check.permission:admin

vendor\bin\phpunit tests\Feature\AdminFundFlowPermissionMigrationTest.php
结果：ERROR，数据库连接失败；SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接�?

Test-NetConnection 127.0.0.1 -Port 3307
结果：TcpTestSucceeded=False
```

## 17. 2026-06-07 后台权益汇�?�模块第�?阶段

本轮继续按迁移缺口审计中�? P0 财务统计链路推进，补齐旧项目 `RightsSummaryController` 的第�?阶段新项目落点�?�当前阶段先实现权益汇�?�只读列表�?�页面顶部汇总卡片�?�后�? API、权限配置�?�多语言文案、中文�?�辑注释和测试覆盖；旧项目中的自�?/手动确认出入金�?�导出等复杂流程保留为后续深层迁移任务，在线结算金额统计已在�? 367 节补齐闭环�??

### 新增和维护文�?

- `app/Http/Controllers/Admin/RightsSummaryController.php`：后台权益汇总控制器，读�? `mt4_users` 并�?�过 `user_infos.mt4_code` 映射业务用户�?
- `app/Models/Mt4User.php`：MT4 用户资金模型，对应真实表 `mt4_users`�?
- `resources/admin/layui/rights-summary/index.blade.php`：后台权益汇�? Blade 页面�?
- `public/js/admin/layui/rights-summary/index.js`：权益汇�? Layui 页面脚本�?
- `database/migrations/2026_06_07_000007_add_admin_rights_summary_permissions.php`：权益汇总权限迁移�??
- `tests/Feature/AdminRightsSummaryModuleTest.php`：权益汇总模块页面�?�API 路由和权限中间件测试�?
- `tests/Feature/AdminRightsSummaryPermissionMigrationTest.php`：权益汇总权限迁移测试�??
- `tests/Feature/AdminChineseCommentReadabilityTest.php`：扩大中文注释可读�?�覆盖�??
- `public/css/admin/style.css`：新增权益汇总统计卡片样式�??

### 页面与接�?

| 类型 | 名称 | 地址或路由名 | 说明 |
| --- | --- | --- | --- |
| Blade 页面 | 权益汇�?? | `/admin/rights-summary` / `admin_page_rights_summary` | 查看 MT4 账户余额、净值�?�保证金、可用保证金和账户数汇�?��?? |
| API | 权益汇�?�列�? | `POST /api/admin/rightsSummaryList` / `admin_api_rightsSummaryList` | 读取 `mt4_users`，�?�过 `user_infos.mt4_code` 关联业务用户并应用后台数据范围�?? |

### 数据表权限配�?

| permissions.slug | permissions.api_route | permissions.route | 类型 | 用�?? |
| --- | --- | --- | ---: | --- |
| `admin_rights_summary` | �? | `/admin/rights-summary` | 1 | 后台权益汇�?�页�?/菜单节点�? |
| `admin_rights_summary_list` | `admin_api_rightsSummaryList` | �? | 3 | 权益汇�?�列表接口权限�?? |

### 数据范围与业务口�?

- 主数据来源为 `mt4_users`，第�?阶段使用 `balance`、`equity`、`margin`、`margin_free`、`leverage` 做权益列表和汇�?��??
- 业务用户映射使用 `user_infos.mt4_code = mt4_users.login`，从而把 MT4 账号映射�? `user_infos.user_id`�?
- 后台数据范围使用 `AdminDataScopeService->apply(..., 'user', 'user_infos.user_id')`，不同管理员角色只能看到权限范围内的业务用户权益�?
- 当前接口返回 `records` �? `summary`：`records` �? Laravel paginator，`summary` 为账户数、余额合计�?�净值合计�?�保证金合计和可用保证金合计�?

### 多语�?支持

已补�? Laravel 后端语言包和前端运行时语�?包：

- `resources/lang/zh-CN/admin.php`
- `resources/lang/en/admin.php`
- `public/js/common/lang/zh-CN.js`
- `public/js/common/lang/en.js`

新增键包含：`rights_summary`、`rights_summary_fetched`、`total_accounts`、`total_balance`、`total_equity`、`total_margin`、`total_margin_free`、`mt4_login`、`mt4_name`、`mt4_group`、`margin_free`、`min_equity`、`max_equity`�?

### 本轮验证命令与结�?

```text
php -l app\Http\Controllers\Admin\RightsSummaryController.php
php -l app\Models\Mt4User.php
php -l database\migrations\2026_06_07_000007_add_admin_rights_summary_permissions.php
php -l routes\admin.php
php -l routes\web.php
php -l resources\lang\zh-CN\admin.php
php -l resources\lang\en\admin.php
结果：全�? No syntax errors detected

node --check public\js\admin\layui\rights-summary\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
结果：全部�??出码 0

vendor\bin\phpunit tests\Feature\AdminRightsSummaryModuleTest.php
结果：OK (3 tests, 11 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
结果：OK (2 tests, 782 assertions)

php artisan route:list --path=rights-summary
结果：GET admin/rights-summary 已注册，路由�? admin_page_rights_summary

php artisan route:list --path=rightsSummaryList
结果：POST api/admin/rightsSummaryList 已注册，路由�? admin_api_rightsSummaryList，中间件包含 jwt.auth:admin、sso:admin、check.permission:admin

vendor\bin\phpunit tests\Feature\AdminRightsSummaryPermissionMigrationTest.php
结果：ERROR，数据库连接失败；SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接�?

Test-NetConnection 127.0.0.1 -Port 3307
结果：TcpTestSucceeded=False
```

### 后续未完成边�?

- 未完成旧项目 `ConfirmWithdrawOrdeposit`、`ManualConfirmWithdrawOrdeposit` 自动/手动确认出入金�?�辑�?
- 未完�? `rightsSumExport` 导出格式迁移�?
- 在线结算金额统计已在�? 367 节补齐当前筛选范围的只读汇�?�闭环；剩余边界只保留自动确认出入金与真�? MT4 自动同步�?
- 未完成真�? DB 权限写入复核，原因是当前 MySQL `3307` 端口拒绝连接�?

## 18. 2026-06-07 �?终清单结构修复与权益汇�?�复�?

本轮只调整文档结构和复核验证结果，不改动权益汇�?�业务代码�?�此�? `## 17. 2026-06-07 后台权益汇�?�模块第�?阶段` 被插入到�? 16 节资金流水模块中间，导致�? 16 节�?�数据库恢复后必须继续执行�?�和验证结果被拆�?。现在已把第 17 节完整移动到�? 16 节之后，保证�?终清单按模块顺序阅读和交接�??

### 本轮文档修复
- `docs/admin-backend-blade-permission-final-checklist.md`：调整章节顺序，确保�? 16 节后台资金流水模块完整闭合，�? 17 节后台权益汇总模块位于第 16 节之后的独立位置�?
- 本次调整不改变接口�?�控制器、模型�?�迁移�?�Blade 页面、JS 脚本和语�?包，仅修复最终交付清单的结构�?

### 本轮复验命令与结�?
```text
php -l app\Http\Controllers\Admin\RightsSummaryController.php
php -l app\Models\Mt4User.php
php -l database\migrations\2026_06_07_000007_add_admin_rights_summary_permissions.php
php -l routes\admin.php
php -l routes\web.php
php -l resources\lang\zh-CN\admin.php
php -l resources\lang\en\admin.php
结果：全�? No syntax errors detected

node --check public\js\admin\layui\rights-summary\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
结果：全部�??出码 0

vendor\bin\phpunit tests\Feature\AdminRightsSummaryModuleTest.php
结果：OK (3 tests, 11 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
结果：OK (2 tests, 782 assertions)

php artisan route:list --path=rights-summary
结果：GET admin/rights-summary 已注册，路由�? admin_page_rights_summary

php artisan route:list --path=rightsSummaryList
结果：POST api/admin/rightsSummaryList 已注册，路由�? admin_api_rightsSummaryList，中间件包含 jwt.auth:admin、sso:admin、check.permission:admin

Test-NetConnection 127.0.0.1 -Port 3307
结果：TcpTestSucceeded=False

vendor\bin\phpunit tests\Feature\AdminRightsSummaryPermissionMigrationTest.php
结果：ERROR，SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接�?
```

### 数据库恢复后必须继续执行
```text
php artisan migrate --force
vendor\bin\phpunit tests\Feature\AdminRightsSummaryPermissionMigrationTest.php
vendor\bin\phpunit tests\Feature\AdminFundFlowPermissionMigrationTest.php
vendor\bin\phpunit tests\Feature\AdminPermissionPlanTest.php --filter front_menu_tree_keeps_parent
```

并用真实 DB 复核�?
- `permissions` 中是否存�? `admin_rights_summary`、`admin_rights_summary_list`、`admin_withdraw_flows`、`admin_withdraw_flow_list`、`admin_undeposit_flows`、`admin_undeposit_flow_list`�?
- `role_permissions` 是否已按后台管理员角色分配权益汇总�?�出金流水�?�未入金流水页面和接口权限�??
- `POST /api/admin/rightsSummaryList` 是否能按真实 `mt4_users`、`user_infos.mt4_code` 和后台数据范围返回权益汇总数据�??

## 19. 2026-06-07 后台交易 MT4 持仓/平仓第一阶段

本轮继续按迁移缺口审计中�? P0“持�?/平仓/持仓汇�?��?�推进�?�旧项目 `AdminOpenOrderController`、`AdminCloseOrderController` 的核心数据源�? MT4_TRADES；新项目此前 `TradeController` 仍读�? `user_trades`，只能覆盖基�?订单列表，不能证明后台交易页面已经按当前真实 `mt4_trades` 表迁移�?�本轮先完成第一阶段：交易类订单列表、持仓列表�?�平仓列表�?�筛选�?�分页和汇�?�卡片�??

### 本轮新增和维护文�?
- `app/Http/Controllers/Admin/TradeController.php`：重写为可读 UTF-8 中文注释，后台交易列表统�?读取 `Mt4Trade`；参数注释说�? `user_id`、`ticket`、`symbol`、`start_date`、`end_date`、`page`、`per_page`、`limit` 的来源�?�含义和作用�?
- `resources/admin/layui/trades/index.blade.php`：新增订单数、�?�手数�?��?�盈亏�?�库存费合计、手续费合计汇�?�卡片，继续使用 Laravel Blade 渲染�?
- `public/js/admin/layui/trades/index.js`：重写为 UTF-8，新�? `records + summary` 解析、汇总卡片更新�?�辑和字段级中文逻辑注释�?
- `resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`：补充后台交易汇总卡片多语言键�??
- `public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`：补充前端运行时交易汇�?�多语言键�??
- `tests/Feature/AdminTradeMt4PositionModuleTest.php`：新增后�? MT4 持仓/平仓第一阶段契约测试。由于当�? MySQL 3307 不可达且 PHP 未启�? SQLite PDO，本测试采用源码契约验证，不访问外部 DB�?

### 当前接口契约
| 接口 | 路由�? | 数据�? | 第一阶段口径 |
| --- | --- | --- | --- |
| `POST /api/admin/tradeList` | `admin_api_tradeList` | `mt4_trades` | `cmd in (0..5)` 的全部交易类订单，按 `open_time` 范围筛�?��?? |
| `POST /api/admin/openPositions` | `admin_api_openPositions` | `mt4_trades` | `cmd in (0..5)` �? `close_time is null or close_time = 0` 表示当前持仓�? |
| `POST /api/admin/closedPositions` | `admin_api_closedPositions` | `mt4_trades` | `cmd in (0..5)` �? `close_time > 0` 表示历史平仓�? |
| `POST /api/admin/tradeSummary` | `admin_api_tradeSummary` | `mt4_trades` | 当前持仓�? `symbol` 分组统计 `total_volume` �? `count`�? |

### 返回结构
```json
{
  "code": 1000,
  "message": "...",
  "data": {
    "records": {
      "data": [],
      "total": 0
    },
    "summary": {
      "total_orders": 0,
      "total_volume": 0,
      "total_profit": 0,
      "total_swaps": 0,
      "total_commission": 0
    }
  }
}
```

字段含义�?
- `records`：Laravel paginator，用�? Layui 表格分页渲染�?
- `summary.total_orders`：当前筛选条件下的订单数量�??
- `summary.total_volume`：当前筛选条件下的交易手�?/成交量合计，第一阶段直接沿用当前真实�? `volume` 数�?��??
- `summary.total_profit`：当前筛选条件下的盈亏合计�??
- `summary.total_swaps`：当前筛选条件下的库存费合计�?
- `summary.total_commission`：当前筛选条件下的手续费合计�?

### 本轮验证命令与结�?
```text
vendor\bin\phpunit tests\Feature\AdminTradeMt4PositionModuleTest.php
结果：OK (3 tests, 17 assertions)

php -l app\Http\Controllers\Admin\TradeController.php
php -l resources\lang\zh-CN\admin.php
php -l resources\lang\en\admin.php
结果：全�? No syntax errors detected

node --check public\js\admin\layui\trades\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
结果：全部�??出码 0

vendor\bin\phpunit tests\Feature\AdminSecondBatchModuleCoverageTest.php
结果：OK (34 tests, 80 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
结果：OK (2 tests, 782 assertions)

php artisan route:list --path=openPositions
结果：POST api/admin/openPositions 已注册，路由�? admin_api_openPositions，中间件包含 jwt.auth:admin、sso:admin、check.permission:admin

php artisan route:list --path=closedPositions
结果：POST api/admin/closedPositions 已注册，路由�? admin_api_closedPositions，中间件包含 jwt.auth:admin、sso:admin、check.permission:admin

php artisan route:list --path=tradeList
结果：POST api/admin/tradeList 已注册，路由�? admin_api_tradeList，中间件包含 jwt.auth:admin、sso:admin、check.permission:admin
```

### 后续未完成边�?
- 旧项目基�? `MARGIN_RATE` 的实�?/测试盘区分和特殊 MT4 口径仍需继续迁移；`COMMENT` �? `MODIFY_TIME` 已在后续�? 364 节补齐历史平仓强平筛选和展示�?
- 后台交易列表数据范围已在�? 20 节接�? `AdminDataScopeService`，当前保留此条作为历史边界关闭记录�??
- 真实 DB 查询验证已在后续交易模块测试中�?�过受控 `mt4_trades` 夹具覆盖，当前剩余的是旧项目更深层导出和 `orderType` 口径�?

## 20. 2026-06-07 后台交易数据范围补齐

本轮补齐�? 19 节留下的�?个明确边界：后台交易列表已经读取 `mt4_trades`，但上一轮尚未接�? `AdminDataScopeService`。现�? `tradeList`、`openPositions`、`closedPositions`、`tradeSummary` 都会在存�? admin 登录用户时，�? `targetType=trade` �? `userIdColumn=login` 追加数据范围过滤，避免普通后台管理员看到超出角色/代理绑定范围�? MT4 交易记录�?

### 本轮维护文件
- `app/Http/Controllers/Admin/TradeController.php`：注�? `AdminDataScopeService`，新�? `applyDataScope()` 方法；方法注释明�? `targetType=trade` �? `login` 字段的业务含义�??
- `tests/Feature/AdminTradeMt4PositionModuleTest.php`：补充交易控制器必须注入和调�? `AdminDataScopeService` 的契约断�?�?
- `docs/admin-backend-blade-permission-final-checklist.md`：记录本轮补齐的数据范围边界和验证结果�??

### 数据范围口径
```text
AdminDataScopeService->apply($query, $admin, 'trade', 'login')
```

参数含义�?
- `$query`：当�? `mt4_trades` 查询对象，调用前已经追加全部交易、持仓或平仓基础条件�?
- `$admin`：当�? admin guard 下的后台管理员�??
- `trade`：数据范围目标类型，表示当前业务对象是交易订单�??
- `login`：`mt4_trades.login`，对应业务用�? ID/MT4 登录账号，用于把交易记录限制在当前管理员角色允许查看的用户集合内�?

### 本轮验证命令与结�?
```text
vendor\bin\phpunit tests\Feature\AdminTradeMt4PositionModuleTest.php
结果：OK (3 tests, 21 assertions)

php -l app\Http\Controllers\Admin\TradeController.php
结果：No syntax errors detected

vendor\bin\phpunit tests\Feature\AdminSecondBatchModuleCoverageTest.php
结果：OK (34 tests, 80 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
结果：OK (2 tests, 782 assertions)

php artisan route:list --path=tradeSummary
结果：POST api/admin/tradeSummary 已注册，路由�? admin_api_tradeSummary，中间件包含 jwt.auth:admin、sso:admin、check.permission:admin

vendor\bin\phpunit tests\Feature\AdminDataScopeControllerWiringTest.php
结果：ERROR，测试启�? DatabaseTransactions 时连�? MySQL 失败：SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接�?
```

### 数据库恢复后必须继续执行
```text
vendor\bin\phpunit tests\Feature\AdminDataScopeControllerWiringTest.php
vendor\bin\phpunit tests\Feature\AdminDataScopeServiceTest.php
```

并用真实 DB 复核�?
- 不同后台管理员角色访�? `POST /api/admin/tradeList` 时，是否只能看到 `role_data_scopes` �? `admin_agent_bindings` 配置范围内的 `mt4_trades.login`�?
- `POST /api/admin/openPositions` �? `POST /api/admin/closedPositions` 的汇总卡片是否与数据范围过滤后的列表�?致�??
- 超级管理员是否仍可查看全�? `mt4_trades` 交易记录�?

## 21. 2026-06-07 后台汇率配置模块第一阶段

本轮继续按后�? Blade 页面、权限表配置、多语言和中文�?�辑注释要求推进，补齐旧项目汇率配置的第�?阶段新项目落点�?�旧项目中入金汇率和出金汇率属于后台运营配置，本阶段在新项目中统�?落到 `system_configs` �? key/value 模式，不再新增业务表，也不把汇率写死在前端或控制器常量之外的散落位置�?

### 本轮新增和维护文�?

- `app/Http/Controllers/Admin/ExchangeRateController.php`：后台汇率配置控制器，维�? `sys_deposit_rate` �? `sys_draw_rate` 两个配置 key，并使用中文注释说明参数含义、保存边界和 system_configs 数据来源�?
- `resources/admin/layui/exchange-rates/index.blade.php`：后台汇率配�? Blade 页面，只渲染入金汇率、出金汇率和保存按钮，页面数据由后台 API 读取�?
- `public/js/admin/layui/exchange-rates/index.js`：汇率配�? Layui 页面脚本，负责读取接口回填表单�?�提交保存和刷新按钮权限�?
- `database/migrations/2026_06_07_000008_add_admin_exchange_rate_permissions.php`：写入汇率配置页面权限�?�查看接口权限和更新接口权限�?
- `tests/Feature/AdminExchangeRateModuleTest.php`：汇率配置模块契约测试，覆盖页面路由、Blade 控件、API 权限中间件�?�控制器 key/value 保存契约和权限迁移声明�??
- `tests/Feature/AdminChineseCommentReadabilityTest.php`：把本轮新增控制器�?�Blade、JS、迁移和测试纳入中文注释可读性检查�??
- `routes/admin.php`：新增两个受保护 API 路由�?
- `routes/web.php`：新�? `/admin/exchange-rates` Blade 页面路由�?
- `resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`：新增后端页面标题�?�接口消息和字段文案�?
- `resources/lang/zh-CN/menus.php`、`resources/lang/en/menus.php`：新增菜单标题翻译�??
- `public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`：新增前端运行时语言 key�?

### 页面与接口清�?

| 类型 | 地址或路由名 | 说明 |
| --- | --- | --- |
| Blade 页面 | `GET /admin/exchange-rates` / `admin_page_exchange_rates` | 汇率配置页面，使�? Laravel Blade + Layui 表单渲染�? |
| API | `POST /api/admin/exchangeRateInfo` / `admin_api_exchangeRateInfo` | 读取 `system_configs` 中的 `sys_deposit_rate` �? `sys_draw_rate`�? |
| API | `POST /api/admin/updateExchangeRate` / `admin_api_updateExchangeRate` | 保存入金汇率和出金汇率，使用 `SystemConfig::updateOrCreate` 写入配置表�?? |

### 参数和数据来�?

| 参数 | 数据�? key | 逻辑含义 |
| --- | --- | --- |
| `sys_deposit_rate` | `system_configs.key=sys_deposit_rate` | 入金换算汇率，要求为大于 0 的数字�?? |
| `sys_draw_rate` | `system_configs.key=sys_draw_rate` | 出金/取款换算汇率，要求为大于 0 的数字�?? |
| `group` | `exchange_rate` | 系统配置分组，用于在系统配置列表中归类显示汇率配置�?? |

### 权限配置

| permissions.slug | permissions.type | route/api_route | 功能 |
| --- | ---: | --- | --- |
| `admin_exchange_rates` | 1 | `/admin/exchange-rates` | 汇率配置页面/菜单入口�? |
| `admin_exchange_rate_info` | 3 | `admin_api_exchangeRateInfo` | 查看汇率配置接口权限�? |
| `admin_exchange_rate_update` | 3 | `admin_api_updateExchangeRate` | 保存汇率配置按钮与接口权限�?? |

### 本轮验证命令与结�?

```text
vendor\bin\phpunit tests\Feature\AdminExchangeRateModuleTest.php
结果：OK (5 tests, 23 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
结果：OK (2 tests, 867 assertions)

php -l app\Http\Controllers\Admin\ExchangeRateController.php
php -l database\migrations\2026_06_07_000008_add_admin_exchange_rate_permissions.php
php -l routes\admin.php
php -l routes\web.php
php -l resources\lang\zh-CN\admin.php
php -l resources\lang\en\admin.php
结果：全�? No syntax errors detected

node --check public\js\admin\layui\exchange-rates\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
结果：全部�??出码 0

php artisan route:list --path=exchange-rates
结果：GET admin/exchange-rates 已注册，路由�? admin_page_exchange_rates

php artisan route:list --path=exchangeRate
结果：POST api/admin/exchangeRateInfo 已注册，路由�? admin_api_exchangeRateInfo，中间件包含 jwt.auth:admin、sso:admin、CheckPermission:admin

php artisan route:list --path=updateExchangeRate
结果：POST api/admin/updateExchangeRate 已注册，路由�? admin_api_updateExchangeRate，中间件包含 jwt.auth:admin、sso:admin、CheckPermission:admin

Test-NetConnection 127.0.0.1 -Port 3307
结果：TcpTestSucceeded=False，真�? DB 迁移落库与数据复核暂不可执行
```

### 数据库恢复后必须继续执行

```text
php artisan migrate --force
```

并用真实 DB 复核�?

- `permissions` 中是否存�? `admin_exchange_rates`、`admin_exchange_rate_info`、`admin_exchange_rate_update`�?
- `role_permissions` 是否已按后台管理员角色分配汇率配置页面�?�查看接口和保存接口权限�?
- `system_configs` 中是否能正确写入或更�? `sys_deposit_rate`、`sys_draw_rate`，且 `group` �? `exchange_rate`�?
## 22. 2026-06-07 后台在线用户模块第一阶段

本轮继续按旧项目后台迁移缺口推进，补齐旧项目 `UserLoginOnlineController` 在新项目中的第一阶段落点。当前实现以新项目真实表结构为准，读�? `user_onlines`，并通过 `user_infos` 关联补充业务用户名称和账号类型；页面继续使用 Laravel Blade + Layui + JS 渲染，接口鉴权继续来�? `permissions.api_route` 数据表配置�??

### 本轮新增和维护文�?
- `app/Models/UserOnline.php`：新增在线用户模型，对应真实�? `user_onlines`；该表没�? `deleted_at` 字段，因此模型不继承带软删除逻辑�? `BaseModel`�?
- `app/Http/Controllers/Admin/OnlineUserController.php`：新增后台在线用户控制器，提供只读列表接�? `onlineUserList`，筛选参数包�? `user_id`、`ip_address`、`start_date`、`end_date`、`page`、`per_page`、`limit`�?
- `resources/admin/layui/online-users/index.blade.php`：新增后台在线用�? Blade 页面，包�? `onlineUserSearchForm`、`onlineUserTable` 和日期筛选控件�??
- `public/js/admin/layui/online-users/index.js`：新�? Layui 页面脚本，负责渲染表格�?�提交筛选条件�?�格式化账号类型和最后活跃时间�??
- `database/migrations/2026_06_07_000009_add_admin_online_user_permissions.php`：新增页面权�? `admin_online_users` �? API 权限 `admin_online_user_list`�?
- `routes/web.php`：新�? `GET /admin/online-users`，放在后�? Layui 兜底路由之前，避免被 `/admin/{path?}` 转到 Naive 页面�?
- `routes/admin.php`：新�? `POST /api/admin/onlineUserList`，继承后�? JWT、SSO、权限中间件�?
- `resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`、`public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`：补齐在线用户模块中英文文案�?
- `tests/Feature/AdminOnlineUserModuleTest.php`：新增在线用户模块契约测试，覆盖页面路由、Blade 控件、API 权限中间件�?�真实表读取和权限迁移声明�??
- `tests/Feature/AdminChineseCommentReadabilityTest.php`：纳入本轮新增文件，并增加连续问号占位检查，避免中文注释或中文文案被系统编码吞掉�?

### 页面与接口清�?
| 类型 | 地址或路由名 | 说明 |
| --- | --- | --- |
| Blade 页面 | `GET /admin/online-users` / `admin_page_online_users` | 在线用户页面，使�? Laravel Blade + Layui 表格渲染�? |
| API | `POST /api/admin/onlineUserList` / `admin_api_onlineUserList` | 读取 `user_onlines` �?近活跃记录，并关�? `user_infos` 返回用户名称和账号类型�?? |

### 参数和数据来�?
| 参数 | 数据表字�? | 逻辑含义 |
| --- | --- | --- |
| `user_id` | `user_onlines.user_id` | 前台业务用户 ID，用于定位某个代理商或普通客户的在线记录�? |
| `ip_address` | `user_onlines.ip_address` | 登录或活�? IP，后端按 `LIKE` 模糊筛�?��?? |
| `start_date` | `user_onlines.last_activity` | �?后活跃开始日期，后端转换为当�? 00:00:00 秒级时间戳�?? |
| `end_date` | `user_onlines.last_activity` | �?后活跃结束日期，后端转换为当�? 23:59:59 秒级时间戳�?? |
| `page` | Laravel paginator | 当前页码，兼�? Layui 表格分页�? |
| `per_page` / `limit` | Laravel paginator | 每页条数，`limit` 兼容 Layui 默认参数名�?? |

### 权限配置
| permissions.slug | permissions.type | route/api_route | 功能 |
| --- | ---: | --- | --- |
| `admin_online_users` | 1 | `/admin/online-users` | 在线用户页面/菜单入口�? |
| `admin_online_user_list` | 3 | `admin_api_onlineUserList` | 在线用户列表接口权限�? |

### 本轮验证命令与结�?
```text
php -l app\Http\Controllers\Admin\OnlineUserController.php
php -l app\Models\UserOnline.php
php -l database\migrations\2026_06_07_000009_add_admin_online_user_permissions.php
php -l routes\admin.php
php -l routes\web.php
php -l resources\lang\zh-CN\admin.php
php -l resources\lang\en\admin.php
结果：全�? No syntax errors detected

node --check public\js\admin\layui\online-users\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
结果：全部�??出码 0

php artisan route:list --path=online-users
结果：GET admin/online-users 已注册，路由�? admin_page_online_users

php artisan route:list --path=onlineUserList
结果：POST api/admin/onlineUserList 已注册，路由�? admin_api_onlineUserList，中间件包含 jwt.auth:admin、sso:admin、CheckPermission:admin

vendor\bin\phpunit tests\Feature\AdminOnlineUserModuleTest.php
结果：OK (5 tests, 20 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
结果：OK (3 tests, 977 assertions)

Test-NetConnection 127.0.0.1 -Port 3307
结果：TcpTestSucceeded=False，真�? DB 迁移落库与真实在线用户样本复核暂不可执行
```

### 数据库恢复后必须继续执行
```text
php artisan migrate --force
```

并用真实 DB 复核�?
- `permissions` 中是否存�? `admin_online_users`、`admin_online_user_list`�?
- 目标后台角色是否通过 `role_permissions` 授权了在线用户页面和列表接口�?
- `POST /api/admin/onlineUserList` 是否能按真实 `user_onlines` 数据返回列表�?
- `user_infos.account_type=1` 的代理商�? `account_type=2` 的普通客户在页面中是否能正确显示账号类型�?

## 23. 2026-06-07 后台产品/交易品种模块第一阶段

本轮继续按迁移缺口审计中�? P1“产�?/交易品种管理”推进，补齐旧项�? `AdminProductionController` 在新项目中的第一阶段落点。旧控制器主要基�? `symbol_prices` �? `MT4_TRADES` 统计交易品种当前持仓；新项目当前阶段以真实表 `symbol_prices` �? `mt4_trades` 为准，先实现只读列表、当前持仓汇总�?�后�? Blade 页面、权限表配置、多语言文案、中文�?�辑注释和测试覆盖�??

### 本轮新增和维护文�?
- `app/Http/Controllers/Admin/ProductionController.php`：新增后台产�?/交易品种控制器，读取 `SymbolPrice::query()`，�?�过 `leftJoin('mt4_trades')` 汇�?�买入手数�?�卖出手数�?�净持仓和浮动盈亏�??
- `resources/admin/layui/productions/index.blade.php`：新增后台产�?/交易品种 Blade 页面，包�? `productionSearchForm`、`productionTable` 和顶部汇总卡片�??
- `public/js/admin/layui/productions/index.js`：新�? Layui 页面脚本，负责调�? `admin_api_productionList`、渲染表格�?�刷新汇总卡片和提交筛�?�条件�??
- `database/migrations/2026_06_07_000010_add_admin_production_permissions.php`：新增页面权�? `admin_productions` �? API 权限 `admin_production_list`�?
- `routes/web.php`：新�? `GET /admin/productions`，放在后�? Layui 兜底路由之前，避免被 `/admin/{path?}` 转到 Naive 页面�?
- `routes/admin.php`：新�? `POST /api/admin/productionList`，继承后�? JWT、SSO、权限中间件�?
- `resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`、`resources/lang/zh-CN/menus.php`、`resources/lang/en/menus.php`、`public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`：补齐产�?/交易品种模块中英文文案�??
- `tests/Feature/AdminProductionModuleTest.php`：新增产�?/交易品种模块契约测试，覆盖页面路由�?�Blade 控件、API 权限中间件�?�真实表读取和权限迁移声明�??
- `tests/Feature/AdminChineseCommentReadabilityTest.php`：纳入本轮新增产品模块文件，继续�?查中文�?�辑注释和编码占位�??

### 页面与接口清�?
| 类型 | 地址或路由名 | 说明 |
| --- | --- | --- |
| Blade 页面 | `GET /admin/productions` / `admin_page_productions` | 产品/交易品种页面，使�? Laravel Blade + Layui 表格渲染�? |
| API | `POST /api/admin/productionList` / `admin_api_productionList` | 读取 `symbol_prices`，并汇�?? `mt4_trades` 当前未平仓买卖方向数据�?? |

### 参数和数据来�?
| 参数 | 数据表字�? | 逻辑含义 |
| --- | --- | --- |
| `symbol` | `symbol_prices.symbol` | 交易品种编码，例�? XAUUSD，后端按 `LIKE` 模糊筛�?��?? |
| `group_id` | `symbol_prices.group_id` | 品种分组 ID，用于按贵金属�?�能源�?�外汇等业务类别筛�?��?? |
| `status` | `symbol_prices.status` | 品种状�?�，1 表示启用�?0 表示停用�? |
| `page` | Laravel paginator | 当前页码，兼�? Layui 表格分页�? |
| `per_page` / `limit` | Laravel paginator | 每页条数，`limit` 兼容 Layui 默认参数名�?? |
| `total_buy_volume` | `mt4_trades.volume where cmd=0` | 当前未平仓买入方向�?�手数�?? |
| `total_sell_volume` | `mt4_trades.volume where cmd=1` | 当前未平仓卖出方向�?�手数�?? |
| `net_volume` | 买入总手�? - 卖出总手�? | 延续旧项目产品净持仓展示口径�? |
| `float_profit_loss` | `mt4_trades.profit` | 当前未平仓订单浮动盈亏合计�?? |

### 权限配置
| permissions.slug | permissions.type | route/api_route | 功能 |
| --- | ---: | --- | --- |
| `admin_productions` | 1 | `/admin/productions` | 产品/交易品种页面/菜单入口�? |
| `admin_production_list` | 3 | `admin_api_productionList` | 产品/交易品种列表接口权限�? |

### 本轮验证命令与结�?
```text
php -l app\Http\Controllers\Admin\ProductionController.php
php -l database\migrations\2026_06_07_000010_add_admin_production_permissions.php
php -l routes\admin.php
php -l routes\web.php
php -l resources\lang\zh-CN\admin.php
php -l resources\lang\en\admin.php
结果：全�? No syntax errors detected

node --check public\js\admin\layui\productions\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
结果：全部�??出码 0

php artisan route:list --path=productions
结果：GET admin/productions 已注册，路由�? admin_page_productions

php artisan route:list --path=productionList
结果：POST api/admin/productionList 已注册，路由�? admin_api_productionList，中间件包含 jwt.auth:admin、sso:admin、CheckPermission:admin

vendor\bin\phpunit tests\Feature\AdminProductionModuleTest.php
结果：OK (5 tests, 21 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
结果：OK (3 tests, 1062 assertions)

Test-NetConnection 127.0.0.1 -Port 3307
结果：TcpTestSucceeded=False，真�? DB 迁移落库与真实产�?/交易品种样本复核暂不可执�?
```

### 数据库恢复后必须继续执行
```text
php artisan migrate --force
```

并用真实 DB 复核�?
- `permissions` 中是否存�? `admin_productions`、`admin_production_list`�?
- 目标后台角色是否通过 `role_permissions` 授权了产�?/交易品种页面和列表接口�??
- `POST /api/admin/productionList` 是否能按真实 `symbol_prices` �? `mt4_trades` 数据返回列表�?
- 当前未平仓交易中 `cmd=0`、`cmd=1` 的手数�?�净持仓和浮动盈亏是否与旧项�? `AdminProductionController` 统计口径�?致�??

## 24. 2026-06-07 后台礼品发放/发货模块第一阶段

本轮继续按迁移缺口审计中�? P1“礼品发放与发货”推进，补齐旧项�? `GiftController` 在新项目中的第一阶段落点。旧控制器包含礼品发放�?�发货列表�?�用户地�?列表和导出；新项目当前阶段以真实�? `gift_shipments`、`user_addresses`、`user_infos` 为准，先实现后台 Blade 页面、发货列�? API、可发放地址 API、发放写�? API、权限表配置、多语言文案、中文�?�辑注释和测试覆盖�?�当时导出能力只先声明权限；当前状�?�已在第 160 节校准为已实�? `admin_api_exportGiftShipments` 当前筛�?? CSV 导出�?

### 本轮新增和维护文�?
- `app/Http/Controllers/Admin/GiftController.php`：新增后台礼品控制器，提�? `shipmentList`、`addressList`、`sendGift` 三个方法；发放动作使�? `DB::transaction` 批量写入 `gift_shipments`�?
- `resources/admin/layui/gifts/index.blade.php`：新增后台礼�? Blade 页面，包�? `giftShipmentSearchForm`、`giftShipmentTable`、`giftAddressTable`、`sendGiftForm`�?
- `public/js/admin/layui/gifts/index.js`：新�? Layui 页面脚本，负责发货列表�?�地�?列表、勾选地�?、组�? `recipients` 参数和提交发放�??
- `database/migrations/2026_06_07_000011_add_admin_gift_permissions.php`：新增页面权限�?�发货列表权限�?�地�?列表权限、发放权限和导出权限�?
- `routes/web.php`：新�? `GET /admin/gifts`，放在后�? Layui 兜底路由之前�?
- `routes/admin.php`：新�? `POST /api/admin/giftShipmentList`、`POST /api/admin/giftAddressList`、`POST /api/admin/sendGift`，全部继承后�? JWT、SSO、权限中间件�?
- `resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`、`resources/lang/zh-CN/menus.php`、`resources/lang/en/menus.php`、`public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`：补齐礼品模块中英文文案�?
- `tests/Feature/AdminGiftModuleTest.php`：新增礼品模块契约测试，覆盖页面路由、Blade 控件、API 权限中间件�?�真实表读取、事务写入和权限迁移声明�?
- `tests/Feature/AdminChineseCommentReadabilityTest.php`：纳入本轮新增礼品模块文件，继续�?查中文�?�辑注释和编码占位�??

### 页面与接口清�?
| 类型 | 地址或路由名 | 说明 |
| --- | --- | --- |
| Blade 页面 | `GET /admin/gifts` / `admin_page_gifts` | 礼品发放/发货页面，使�? Laravel Blade + Layui 表格和弹窗渲染�?? |
| API | `POST /api/admin/giftShipmentList` / `admin_api_giftShipmentList` | 读取 `gift_shipments` 发货记录，并关联 `admins.username` 显示后台操作人�?? |
| API | `POST /api/admin/giftAddressList` / `admin_api_giftAddressList` | 读取 `user_addresses`，并关联 `user_infos` 限制 `is_gift_allowed=1` 的可发放用户�? |
| API | `POST /api/admin/sendGift` / `admin_api_sendGift` | 根据勾�?�地�?批量写入 `gift_shipments` 发货记录�? |

### 参数和数据来�?
| 参数 | 数据表字�? | 逻辑含义 |
| --- | --- | --- |
| `user_id` | `gift_shipments.user_id` / `user_addresses.user_id` | 业务用户 ID，用于筛选发货记录或可发放地�?�? |
| `gift_name` | `gift_shipments.gift_name` | 礼品名称，发货列表按 `LIKE` 筛�?�，发放时写入发货记录�?? |
| `recipient_name` | `gift_shipments.recipient_name` / `user_addresses.recipient_name` | 收件人姓名，用于发货记录展示和地�?筛�?��?? |
| `recipient_phone` | `user_addresses.recipient_phone` | 收件人联系电话，用于地址筛�?�和发放写入�? |
| `recipient_address` | `user_addresses.recipient_address` | 收件地址，发放时写入 `gift_shipments.recipient_address`�? |
| `sender_name` | `gift_shipments.sender_name` | 发件人名称，由后台发放表单提交�?? |
| `gift_quantity` | `gift_shipments.gift_quantity` | 礼品数量，必须大于等�? 1�? |
| `tracking_number` | `gift_shipments.tracking_number` | 物流单号；为空时状�?�为待处理，有单号时状�?�为已发货�?? |
| `recipients` | 多个地址行组成的数组 | JS 根据 `giftAddressTable` 勾�?�项生成，后端�?�条写入发货记录�? |

### 权限配置
| permissions.slug | permissions.type | route/api_route | 功能 |
| --- | ---: | --- | --- |
| `admin_gifts` | 1 | `/admin/gifts` | 礼品后台页面/菜单入口�? |
| `admin_gift_shipments` | 3 | `admin_api_giftShipmentList` | 礼品发货列表接口权限�? |
| `admin_gift_addresses` | 3 | `admin_api_giftAddressList` | 可发放地�?列表接口权限�? |
| `admin_gift_send` | 3 | `admin_api_sendGift` | 发放礼品按钮与接口权限�?? |
| `admin_gift_export` | 3 | `admin_api_exportGiftShipments` | 当前筛�?�礼品发�? CSV 导出接口权限�? |

### 本轮验证命令与结�?
```text
php -l app\Http\Controllers\Admin\GiftController.php
php -l database\migrations\2026_06_07_000011_add_admin_gift_permissions.php
php -l routes\admin.php
php -l routes\web.php
php -l resources\lang\zh-CN\admin.php
php -l resources\lang\en\admin.php
结果：全�? No syntax errors detected

node --check public\js\admin\layui\gifts\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
结果：全部�??出码 0

php artisan route:list --path=gifts
结果：GET admin/gifts 已注册，路由�? admin_page_gifts

php artisan route:list --path=giftShipmentList
结果：POST api/admin/giftShipmentList 已注册，路由�? admin_api_giftShipmentList，中间件包含 jwt.auth:admin、sso:admin、CheckPermission:admin

php artisan route:list --path=giftAddressList
结果：POST api/admin/giftAddressList 已注册，路由�? admin_api_giftAddressList，中间件包含 jwt.auth:admin、sso:admin、CheckPermission:admin

php artisan route:list --path=sendGift
结果：POST api/admin/sendGift 已注册，路由�? admin_api_sendGift，中间件包含 jwt.auth:admin、sso:admin、CheckPermission:admin

vendor\bin\phpunit tests\Feature\AdminGiftModuleTest.php
结果：OK (5 tests, 30 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
结果：OK (3 tests, 1147 assertions)

Test-NetConnection 127.0.0.1 -Port 3307
结果：TcpTestSucceeded=False，真�? DB 迁移落库与真实礼品样本复核暂不可执行
```

### 数据库恢复后必须继续执行
```text
php artisan migrate --force
```

并用真实 DB 复核�?
- `permissions` 中是否存�? `admin_gifts`、`admin_gift_shipments`、`admin_gift_addresses`、`admin_gift_send`、`admin_gift_export`�?
- 目标后台角色是否通过 `role_permissions` 授权了礼品页面�?�发货列表�?�地�?列表和发放接口�??
- `POST /api/admin/giftAddressList` 是否只返�? `user_infos.is_gift_allowed=1` 且存在收货地�?的用户�??
- `POST /api/admin/sendGift` 是否能按真实地址批量写入 `gift_shipments`，并正确记录 `admin_id`、`gift_quantity`、`tracking_number`、`shipped_at`�?
- 旧项�? `shipment_list_export` 对应的新项目当前筛�?? CSV 导出已由 `admin_api_exportGiftShipments` 承接；兑换扣库存、真实兑换规则和积分消�?�联动仍�?继续迁移�?

## 25. 2026-06-07 后台实名认证审核模块第一阶段

本轮继续按旧项目后台迁移缺口推进，补齐旧项目 `AuthenticationController` 在新项目中的第一阶段落点。旧项目认证审核包含待审列表、已审列表�?�认证详情�?�身份证审核、银行卡审核和拒绝原因记录；新项目当前先以真实表 `user_auths` �? `user_infos` 为准，完成后�? Blade 页面、待审列�? API、已审列�? API、复用审核动作�?�权限表配置、多语言文案、中文�?�辑注释和契约测试�??

### 本轮新增和维护文�?
- `app/Http/Controllers/Admin/AuthenticationController.php`：新增实名认证审核控制器，读�? `UserAuth::query()`，关�? `user_infos`，并通过 `AdminDataScopeService` 套用后台数据范围�?
- `app/Http/Controllers/Admin/AdminUserController.php`：修�? `reviewAuth` 的真实落库字段，避免继续写不存在或不符合当前表结构的 `status/memo` 字段；当前按 `id_card_status`、`bank_status`、`id_card_remarks`、`bank_remarks` �? `user_infos.auth_status` 写入�?
- `resources/admin/layui/authentications/index.blade.php`：新增后台认证审�? Blade 页面，包含待审列表�?�已审列表和审核弹窗�?
- `public/js/admin/layui/authentications/index.js`：新�? Layui 页面脚本，负责渲染待�?/已审表格、筛选参数�?�审核弹窗和提交 `reviewAuth`�?
- `database/migrations/2026_06_07_000012_add_admin_authentication_permissions.php`：新增页面�?�待审列表�?�已审列表和审核动作权限字典�?
- `routes/web.php`：新�? `GET /admin/authentications`，放在后�? Layui 兜底路由之前�?
- `routes/admin.php`：新�? `POST /api/admin/authPendingList` �? `POST /api/admin/authCertifiedList`，继续挂载后�? JWT、SSO、权限中间件�?
- `resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`、`resources/lang/zh-CN/menus.php`、`resources/lang/en/menus.php`、`public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`：补齐认证审核模块中英文文案�?
- `tests/Feature/AdminAuthenticationModuleTest.php`：新增认证审核模块契约测试�??
- `tests/Feature/AdminChineseCommentReadabilityTest.php`：纳入本轮认证审核新增文件，继续�?查中文注释和编码占位�?

### 页面与接口清�?
| 类型 | 地址或路由名 | 说明 |
| --- | --- | --- |
| Blade 页面 | `GET /admin/authentications` / `admin_page_authentications` | 实名认证审核页面，使�? Laravel Blade + Layui 表格和弹窗渲染�?? |
| API | `POST /api/admin/authPendingList` / `admin_api_authPendingList` | 查询 `user_auths` 中身份证或银行卡待审记录，并�? `user_infos` 补充用户信息�? |
| API | `POST /api/admin/authCertifiedList` / `admin_api_authCertifiedList` | 查询身份证和银行卡均已�?�过的认证记录�?? |
| API | `POST /api/admin/reviewAuth` / `admin_api_reviewAuth` | 复用原审核动作，当前已修正为写入真实认证字段�? |

### 权限配置
| permissions.slug | permissions.type | route/api_route | 功能 |
| --- | ---: | --- | --- |
| `admin_authentications` | 1 | `/admin/authentications` | 实名认证审核页面/菜单入口�? |
| `admin_auth_pending_list` | 3 | `admin_api_authPendingList` | 待审核认证列表接口权限�?? |
| `admin_auth_certified_list` | 3 | `admin_api_authCertifiedList` | 已审核认证列表接口权限�?? |
| `admin_user_review_auth` | 3 | `admin_api_reviewAuth` | 执行认证审核动作权限�? |

### 本轮验证命令与结�?
```text
php -l app\Http\Controllers\Admin\AuthenticationController.php
php -l app\Http\Controllers\Admin\AdminUserController.php
php -l database\migrations\2026_06_07_000012_add_admin_authentication_permissions.php
php -l routes\admin.php
php -l routes\web.php
php -l resources\lang\zh-CN\admin.php
php -l resources\lang\en\admin.php
结果：全�? No syntax errors detected

node --check public\js\admin\layui\authentications\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
结果：全部�??出码 0

php artisan route:list --path=authentications
结果：GET admin/authentications 已注册，路由�? admin_page_authentications

php artisan route:list --path=authPendingList
结果：POST api/admin/authPendingList 已注册，路由�? admin_api_authPendingList，中间件包含 jwt.auth:admin、sso:admin、CheckPermission:admin

php artisan route:list --path=authCertifiedList
结果：POST api/admin/authCertifiedList 已注册，路由�? admin_api_authCertifiedList，中间件包含 jwt.auth:admin、sso:admin、CheckPermission:admin

vendor\bin\phpunit tests\Feature\AdminAuthenticationModuleTest.php
结果：OK (5 tests, 30 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
结果：OK (3 tests, 1249 assertions)

Test-NetConnection 127.0.0.1 -Port 3307
结果：TcpTestSucceeded=False，真�? DB 迁移落库与认证审核样本复核暂不可执行
```

### 数据库恢复后必须继续执行
```text
php artisan migrate --force
```

并用真实 DB 复核�?
- `permissions` 中是否存�? `admin_authentications`、`admin_auth_pending_list`、`admin_auth_certified_list`、`admin_user_review_auth`�?
- 目标后台角色是否通过 `role_permissions` 授权了认证审核页面�?�待审列表�?�已审列表和审核动作�?
- `POST /api/admin/authPendingList` 是否�? `user_auths.id_card_status=1`、`bank_status=1/3` 返回待审数据�?
- `POST /api/admin/authCertifiedList` 是否只返�? `id_card_status=2` �? `bank_status=2` 的已审核数据�?
- `POST /api/admin/reviewAuth` 是否按真实表字段正确更新 `user_auths.id_card_status`、`user_auths.bank_status`、拒绝原因和 `user_infos.auth_status`�?
## 26. 2026-06-07 后台实时返佣模块第一阶段

本轮继续按旧项目后台迁移缺口推进，补齐旧项目 `AdminRealCommissionController` 在新项目中的第一阶段落点。旧项目实时返佣依赖 `MT4_TRADES.COMMENT` 关键字和 `MODIFY_TIME` 字段做精确分类；新项目当前真实表 `mt4_trades` 只有 `ticket`、`login`、`symbol`、`cmd`、`volume`、`commission`、`swaps`、`profit`、`open_time`、`close_time` 等字段，暂不具备 COMMENT 正则筛�?�条件�?�因此本阶段只实现真实表可支撑的后台只读列表、筛选�?�汇总�?�权限配置�?�多语言和中文�?�辑注释，当前返佣�?��?�口径为 `cmd=6` �? `profit>0` 的余额类正向记录�?

### 本轮新增和维护文�?

- `app/Http/Controllers/Admin/RealtimeCommissionController.php`：新增后台实时返佣控制器，读�? `Mt4Trade::query()`，按 `cmd=6`、`profit>0` 查询，并通过 `AdminDataScopeService` �? `trade/login` 套用后台数据范围�?
- `resources/admin/layui/realtime-commissions/index.blade.php`：新增实时返�? Blade 页面，包含筛选表单�?�汇总指标区�? `realtimeCommissionTable` 表格容器�?
- `public/js/admin/layui/realtime-commissions/index.js`：新�? Layui 页面脚本，负责日期筛选�?�表格渲染�?�接口解析和汇�?�指标更新�??
- `database/migrations/2026_06_07_000013_add_admin_realtime_commission_permissions.php`：新增权限字典迁移，维护页面入口和列表接口权限�??
- `routes/web.php`：新�? `GET /admin/realtime-commissions`，路由名�? `admin_page_realtime_commissions`，放在后�? Layui 兜底路由之前�?
- `routes/admin.php`：新�? `POST /api/admin/realtimeCommissionList`，路由名�? `admin_api_realtimeCommissionList`，继续继承后�? JWT、SSO、权限中间件�?
- `resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`、`resources/lang/zh-CN/menus.php`、`resources/lang/en/menus.php`、`public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`：补齐实时返佣中英文文案�?
- `tests/Feature/AdminRealtimeCommissionModuleTest.php`：新增实时返佣契约测试，覆盖页面路由、Blade 控件、API 中间件�?�控制器真实数据源和权限迁移声明�?
- `tests/Feature/AdminChineseCommentReadabilityTest.php`：纳入本轮新增文件，继续�?查中文�?�辑注释和编码占位问题�??

### 页面与接口清�?

| 类型 | 地址或路由名 | 说明 |
| --- | --- | --- |
| Blade 页面 | `GET /admin/realtime-commissions` / `admin_page_realtime_commissions` | 实时返佣后台页面，使�? Laravel Blade + Layui 表格渲染�? |
| API | `POST /api/admin/realtimeCommissionList` / `admin_api_realtimeCommissionList` | 查询 `mt4_trades` �? `cmd=6` �? `profit>0` 的余额类正向记录，并返回分页与汇总�?? |

### 参数和数据来�?

| 参数 | 数据表字�? | 逻辑含义 |
| --- | --- | --- |
| `user_id` | `mt4_trades.login` | 业务用户�? MT4 登录号，用于定位指定账户的返佣�?��?�记录�?? |
| `ticket` | `mt4_trades.ticket` | MT4 订单号，支持模糊筛�?��?? |
| `order_id` | `mt4_trades.ticket` | 兼容旧项目参数命名，逻辑等同 `ticket`�? |
| `start_date` | `mt4_trades.close_time` | 平仓时间起始日期，后端转换为当天 `00:00:00` �? 10 位时间戳�? |
| `end_date` | `mt4_trades.close_time` | 平仓时间结束日期，后端转换为当天 `23:59:59` �? 10 位时间戳�? |
| `page` | Laravel paginator | 当前页码，兼�? Layui 表格分页�? |
| `per_page` / `limit` | Laravel paginator | 每页条数，`limit` 用于兼容 Layui 默认参数�? |
| `summary.total_records` | 查询结果计数 | 当前筛�?�条件下的返佣�?��?�记录数�? |
| `summary.total_profit` | `sum(mt4_trades.profit)` | 当前筛�?�条件下的正向余额金额合计�?? |
| `summary.total_commission` | `sum(mt4_trades.profit)` | 第一阶段等同返佣金额合计；后�? COMMENT 字段补齐后可升级为精确返佣口径�?? |

### 权限配置

| permissions.slug | permissions.type | route/api_route | 功能 |
| --- | ---: | --- | --- |
| `admin_realtime_commissions` | 1 | `/admin/realtime-commissions` | 实时返佣页面/菜单入口�? |
| `admin_realtime_commission_list` | 3 | `admin_api_realtimeCommissionList` | 实时返佣列表接口权限�? |

### 本轮验证命令与结�?

```text
vendor\bin\phpunit tests\Feature\AdminRealtimeCommissionModuleTest.php
结果：OK (5 tests, 21 assertions)，已确认�? RED �? GREEN�?

php -l app\Http\Controllers\Admin\RealtimeCommissionController.php
php -l database\migrations\2026_06_07_000013_add_admin_realtime_commission_permissions.php
php -l routes\admin.php
php -l routes\web.php
php -l resources\lang\zh-CN\admin.php
php -l resources\lang\en\admin.php
php -l resources\lang\zh-CN\menus.php
php -l resources\lang\en\menus.php
结果：全�? No syntax errors detected

node --check public\js\admin\layui\realtime-commissions\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
结果：全部�??出码 0

php artisan route:list --path=realtime-commissions
结果：GET admin/realtime-commissions 已注册，路由�? admin_page_realtime_commissions

php artisan route:list --path=realtimeCommissionList
结果：POST api/admin/realtimeCommissionList 已注册，路由�? admin_api_realtimeCommissionList，中间件包含 jwt.auth:admin、sso:admin、CheckPermission:admin

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
结果：OK (3 tests, 1334 assertions)

rg -n "\?\?\?" 本轮业务文件、路由�?�语�?文件和测试文�?
结果：无命中

Test-NetConnection 127.0.0.1 -Port 3307
结果：TcpTestSucceeded=False，真�? DB 迁移落库和真实返佣样本复核暂不可执行
```

### 数据库恢复后必须继续执行

```text
php artisan migrate --force
```

并用真实 DB 复核�?

- `permissions` 中是否存�? `admin_realtime_commissions`、`admin_realtime_commission_list`�?
- 目标后台角色是否通过 `role_permissions` 授权了实时返佣页面和列表接口�?
- `POST /api/admin/realtimeCommissionList` 是否能按真实 `mt4_trades` 数据返回分页、汇总和数据范围裁剪后的记录�?
- 若后续同步补齐旧项目 `COMMENT` 或等价字段，�?要把当前 `cmd=6 + profit>0` 的�?��?�口径升级为旧项目返佣关键字精确口径�?

## 27. 2026-06-07 默认后台账号与前�? Layui 菜单角色授权修复

本轮继续修复两个真实测试缺口：后台默认超级管理员必须写入当前登录控制器实际读取的 `admins` 表；前台 agent/customer �? Layui 菜单必须�? `permissions`、`roles`、`role_permissions` �? `user_logins.role_id` 数据表配置驱动�??

### 本轮新增和维护文�?

- `database/migrations/2026_06_07_000014_fix_default_admin_and_front_menu_roles.php`：补�? `user_logins.role_id` 字段、写�? `superadmin / Admin@123456`、补齐前台菜单权限字典�?�绑�? `agent_role` �? `customer_role` 菜单授权�?
- `tests/Feature/DefaultAdminAndFrontMenuRoleMigrationTest.php`：新增回归测试，防止默认后台账号继续落错表，也防止前�? Layui 菜单角色授权缺失�?
- `tests/Feature/AdminChineseCommentReadabilityTest.php`：纳入本轮新增迁移和测试文件，继续检查中文�?�辑注释和编码占位问题�??

### 登录与菜单入�?

| 类型 | 地址或账�? | 说明 |
| --- | --- | --- |
| 后台登录�? | `GET /admin/login` | Laravel Blade + Layui 后台登录页面�? |
| 后台登录 API | `POST /api/admin/login` | 读取 `admins.username` �? `admins.password`�? |
| 超级管理员账�? | `superadmin` | 本轮迁移写入 `admins` 表�?? |
| 超级管理员初始密�? | `Admin@123456` | 本轮迁移使用 `Hash::make` 写入，不明文保存�? |
| 前台菜单 API | `POST /api/front/navigation/menus` | 前台 Layui 侧栏菜单读取接口�? |
| 前台 agent 测试账号 | `agent@test.com / agent123` | 本轮迁移把演�? agent 绑定�? `agent_role`�? |

### 本轮数据表职�?

| 数据�? | 功能 |
| --- | --- |
| `admins` | 后台管理员登录账号表，当前后台登录控制器实际读取该表�? |
| `roles` | 前后台角色表，使�? `guard_type` 区分 `admin` �? `front`�? |
| `permissions` | 前后台菜单�?�页面�?�按钮和 API 权限字典表�?? |
| `role_permissions` | 角色实际拥有权限的唯�?生效来源�? |
| `user_logins.role_id` | 前台用户绑定角色，用于菜单接口按角色过滤�? |

### 本轮验证命令与结�?

```text
vendor\bin\phpunit tests\Feature\DefaultAdminAndFrontMenuRoleMigrationTest.php
结果：OK (1 test, 33 assertions)，已确认�? RED �? GREEN�?

php -l database\migrations\2026_06_07_000014_fix_default_admin_and_front_menu_roles.php
结果：No syntax errors detected

rg -n "\?\?\?" tests\Feature\DefaultAdminAndFrontMenuRoleMigrationTest.php database\migrations\2026_06_07_000014_fix_default_admin_and_front_menu_roles.php
结果：无命中
```

### 数据库恢复后必须复核

```text
php artisan migrate --force
```

真实 DB 恢复后继续确认：

- `admins` 表存�? `username=superadmin` �? `status=1`�?
- `/admin/login` 使用 `superadmin / Admin@123456` 可登录�??
- `user_logins` 表存�? `role_id` 字段�?
- `agent@test.com` 绑定�? `agent_role`�?
- `agent_role` 通过 `role_permissions` 拥有 `front_agent`、`front_agent_sub`、`front_commission` 等前台菜单权限�??
- `POST /api/front/navigation/menus` �? agent 登录后返回非�? Layui 菜单树�??

## 28. 2026-06-07 后台持仓汇�?�第�?阶段

本轮继续按旧项目后台 `PositionSummaryController` 缺口推进。旧项目持仓汇�?�依�? `agents`、`data_list`、`MT4_TRADES`、`MT4_USERS`、`symbol_prices`、`family_tree`、`COMMENT REGEXP` �? `MARGIN_RATE` 等数据�?�新项目当时真实 `mt4_trades` 表暂不具备旧字段 `COMMENT`、`MARGIN_RATE`、`MODIFY_TIME`，因此第�?阶段只迁移当前真实表可支撑的持仓交易统计口径，不伪�?�入金�?�出金�?�返佣精确分类；后续 `COMMENT` �? `MODIFY_TIME` 已补齐，`MARGIN_RATE` 仍作为剩余边界继续跟进�??

### 本轮新增和维护文�?

- `app/Http/Controllers/Admin/PositionSummaryController.php`：新增后台持仓汇总控制器，按 `user_infos.user_id = mt4_trades.login` 聚合订单数�?�手数�?�盈亏�?�手续费、库存费和品种分类手数�??
- `resources/admin/layui/position-summary/index.blade.php`：新�? Laravel Blade + Layui 后台页面，提供汇总卡片�?�筛选表单和列表容器�?
- `public/js/admin/layui/position-summary/index.js`：新�? Layui 表格脚本，请�? `POST /api/admin/positionSummaryList` 并渲染分页列表与汇�?�卡片�??
- `database/migrations/2026_06_07_000015_add_admin_position_summary_permissions.php`：新增后台持仓汇总页面权限和列表 API 权限字典�?
- `routes/web.php`：新�? `GET /admin/position-summary`，路由名 `admin_page_position_summary`，并放在 `/admin/{path?}` 兜底前�??
- `routes/admin.php`：新�? `POST /api/admin/positionSummaryList`，路由名 `admin_api_positionSummaryList`，位于后�? JWT、SSO、权限中间件组内�?
- `resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`：新增后台持仓汇�? Blade 与接口多语言文案�?
- `public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`：新�? Layui JS 运行时多语言字段�?
- `tests/Feature/AdminPositionSummaryModuleTest.php`：新增模块契约测试，覆盖页面路由、Blade 控件、API 权限中间件�?�真实数据源和权限迁移�??
- `tests/Feature/AdminChineseCommentReadabilityTest.php`：纳入本轮新增文件，继续�?查中文�?�辑注释与乱码占位�??

### 页面与接�?

| 类型 | 地址/路由�? | 功能 |
| --- | --- | --- |
| 后台页面 | `GET /admin/position-summary` / `admin_page_position_summary` | Blade 渲染持仓汇�?�页面�?? |
| 后台接口 | `POST /api/admin/positionSummaryList` / `admin_api_positionSummaryList` | 返回持仓汇�?�分页列表和汇�?�卡片数据�?? |
| 页面 JS | `/js/admin/layui/position-summary/index.js` | Layui 表格渲染、筛选提交�?�汇总卡片刷新�?? |

### 请求参数含义

| 参数 | 数据来源/字段 | 功能 |
| --- | --- | --- |
| `user_id` | `user_infos.user_id`、`mt4_trades.login` | 定位单个代理或客户�?? |
| `user_name` | `user_infos.user_name` | 按用户名模糊查询�? |
| `parent_id` | `user_infos.parent_id` | 查询某个上级代理的直属下级�?? |
| `account_type` | `user_infos.account_type` | 账户类型筛�?�，`1=代理`，`2=普�?�客户`�? |
| `start_date` | `mt4_trades.close_time` | 交易统计�?始日期，兼容未平�? `close_time` 为空�? 0 的记录�?? |
| `end_date` | `mt4_trades.close_time` | 交易统计结束日期，兼容未平仓 `close_time` 为空�? 0 的记录�?? |
| `page` | Laravel paginator | 当前页码�? |
| `per_page` / `limit` | Laravel paginator | 每页条数，`limit` 用于兼容 Layui 默认分页参数�? |

### 返回字段与数据来�?

| 字段 | 数据来源 | 功能 |
| --- | --- | --- |
| `records.data[].user_id` | `user_infos.user_id` | 业务用户 ID�? |
| `records.data[].user_name` | `user_infos.user_name` | 业务用户名�?? |
| `records.data[].parent_id` | `user_infos.parent_id` | 上级代理 ID�? |
| `records.data[].account_type` | `user_infos.account_type` | 账户类型�? |
| `records.data[].mt4_group` | `user_infos.mt4_group` | MT4 分组�? |
| `records.data[].total_orders` | `COUNT(mt4_trades.*)` | 交易订单数�?? |
| `records.data[].total_volume` | `SUM(mt4_trades.volume)` | 总交易手数�?? |
| `records.data[].total_profit` | `SUM(mt4_trades.profit)` | 总盈亏�?? |
| `records.data[].total_comm` | `SUM(mt4_trades.commission)` | 手续费合计�?? |
| `records.data[].total_swaps` | `SUM(mt4_trades.swaps)` | 库存费合计�?? |
| `records.data[].total_noble_metal` | `symbol_prices.group_id = 1` | 贵金属手数�?? |
| `records.data[].total_crud_oil` | `symbol_prices.group_id = 2` | 原油手数�? |
| `records.data[].total_for_exca` | `symbol_prices.group_id = 3` | 外汇手数�? |
| `records.data[].total_index` | `symbol_prices.group_id = 4` | 指数手数�? |
| `records.data[].total_currency` | `symbol_prices.group_id = 5` | 货币手数�? |
| `records.data[].total_stock` | `symbol_prices.group_id = 6` | 股票手数�? |
| `summary.total_accounts` | 当前筛�?�后的用户行�? | 汇�?�卡片账户数�? |
| `summary.total_orders` | 当前筛�?�后的订单合�? | 汇�?�卡片订单数�? |
| `summary.total_volume` | 当前筛�?�后的手数合�? | 汇�?�卡片�?�手数�?? |
| `summary.total_profit` | 当前筛�?�后的盈亏合�? | 汇�?�卡片�?�盈亏�?? |
| `summary.total_comm` | 当前筛�?�后的手续费合计 | 汇�?�卡片手续费�? |
| `summary.total_swaps` | 当前筛�?�后的库存费合计 | 汇�?�卡片库存费�? |

### 权限配置

| permissions.slug | permissions.type | route/api_route | 功能 |
| --- | ---: | --- | --- |
| `admin_position_summary` | 1 | `/admin/position-summary` | 后台持仓汇�?�页�?/菜单入口�? |
| `admin_position_summary_list` | 3 | `admin_api_positionSummaryList` | 后台持仓汇�?�列表接口权限�?? |

### 数据范围控制

后台接口先经�? `jwt.auth:admin`、`sso:admin`、`check.permission:admin`，再�? `PositionSummaryController::applyDataScope()` 调用 `AdminDataScopeService`。实际可见数据来自数据表配置�?

- `role_data_scopes`：角色级数据范围配置�?
- `admin_agent_bindings`：管理员可管理代理节点绑定�??
- `permissions`：页面�?�按钮和接口权限字典�?
- `role_permissions`：角色实际拥有权限的唯一生效来源�?

### 当前边界

- 当前第一阶段不使用旧项目 `COMMENT REGEXP`、`MARGIN_RATE`、`MODIFY_TIME`，因为新项目真实 `mt4_trades` 表暂未确认具备这些字段�??
- 当前统计只覆盖交易类 `cmd in (0,1,2,3,4,5)`，不�? `cmd=6` 余额流水混入持仓汇�?��??
- 真实 DB `127.0.0.1:3307` 当前不可连�?�，因此本轮可以完成静�?��?�路由�?�Blade 契约和单测验证，不能声称真实库迁移已执行�?

### 数据库恢复后必须继续执行

```text
php artisan migrate --force
```

并用真实 DB 复核�?

- `permissions` 中是否存�? `admin_position_summary`、`admin_position_summary_list`�?
- 目标后台角色是否通过 `role_permissions` 授权了持仓汇总页面和列表接口�?
- `POST /api/admin/positionSummaryList` 是否按真�? `user_infos`、`mt4_trades`、`symbol_prices` 返回分页、汇总和数据范围裁剪后的记录�?
- 如后续补齐旧项目字段或等价字段，再把入金、出金�?�返佣和保证金口径升级为旧项目精确口径�??

## 29. 2026-06-07 后台风控 MT4 第一阶段

本轮继续按旧项目 `FengXianManageController` 缺口推进。旧项目风控包含盈利风险、持仓风险�?�异�? IP、追保和强平入口；新项目此前 `RiskController` 仍读�? `UserTrade`，且 `marginCalls()` 返回空数组占位，不能证明后台风控已经按当前真�? MT4 表迁移�?�本轮先完成第一阶段：当前持仓风险列表�?�追保预警列表�?�强平信号前置校验�?�Blade 双表视图、运行时多语�?和测试覆盖�??

### 本轮新增和维护文�?

- `app/Http/Controllers/Admin/RiskController.php`：重写为可读 UTF-8 中文注释版本，读�? `Mt4Trade`、`Mt4User`、`UserInfo`，并接入 `AdminDataScopeService`�?
- `resources/admin/layui/risk/index.blade.php`：升级为 Laravel Blade + Layui 双视图页面，包含风险持仓表�?�追保预警表、筛选表单和汇�?�卡片�??
- `public/js/admin/layui/risk/index.js`：重写为 UTF-8，解�? `records + summary` 统一响应结构，支持风险持�?/追保预警切换和强平信号按钮�??
- `resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`：补充风控页面描述�?�风险�?��?�最高保证金比例等后端多语言 key�?
- `public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`：补�? Layui JS 运行时风控字段多语言 key�?
- `tests/Feature/AdminRiskMt4ModuleTest.php`：新�? RED-GREEN 契约测试，约束风控必须从真实 MT4 表读取，不能继续使用 `UserTrade` 占位或空数组追保�?
- `tests/Feature/AdminChineseCommentReadabilityTest.php`：纳入本轮风控新�?/重写文件，继续检查中文�?�辑注释与编码占位�??

### 页面与接�?

| 类型 | 地址/路由�? | 功能 |
| --- | --- | --- |
| 后台页面 | `GET /admin/risk` / `admin_page_risk` | Blade 渲染风控管理页面�? |
| 风险持仓接口 | `POST /api/admin/riskPositions` / `admin_api_riskPositions` | 返回当前 MT4 未平仓订单风险分页列表和汇�?��?? |
| 追保预警接口 | `POST /api/admin/riskMarginCalls` / `admin_api_riskMarginCalls` | 返回保证金比例低于阈值的 MT4 用户资金快照�? |
| 强平信号接口 | `POST /api/admin/riskForceClose/{id}` / `admin_api_riskForceClose` | 校验当前未平仓订单和数据范围后返回强平信号结果�?? |

### 请求参数含义

| 参数 | 数据来源/字段 | 功能 |
| --- | --- | --- |
| `user_id` | `mt4_trades.login`、`user_infos.user_id` | 风险持仓�? MT4 登录账号筛�?�，追保预警按业务用�? ID 筛�?��?? |
| `ticket` | `mt4_trades.ticket` | 当前持仓风险列表�? MT4 订单号模糊查询�?? |
| `symbol` | `mt4_trades.symbol` | 当前持仓风险列表按交易品种筛选�?? |
| `start_date` / `end_date` | `mt4_trades.open_time` | 当前持仓风险列表按开仓时间戳范围筛�?��?? |
| `login` | `mt4_users.login` | 追保预警�? MT4 登录账号筛�?��?? |
| `user_name` | `user_infos.user_name` | 追保预警按业务用户名模糊查询�? |
| `max_margin_level` | 计算字段 `equity / margin * 100` | 追保预警阈�?�，默认 100，比例越低风险越高�?? |
| `page` | Laravel paginator | 当前页码�? |
| `per_page` / `limit` | Laravel paginator | 每页条数，`limit` 用于兼容 Layui 默认分页参数�? |

### 返回字段与数据来�?

| 字段 | 数据来源 | 功能 |
| --- | --- | --- |
| `records.data[].ticket` | `mt4_trades.ticket` | MT4 订单号�?? |
| `records.data[].login` | `mt4_trades.login`、`mt4_users.login` | MT4 登录账号�? |
| `records.data[].symbol` | `mt4_trades.symbol` | 交易品种�? |
| `records.data[].volume` | `mt4_trades.volume` | 当前持仓手数�? |
| `records.data[].profit` | `mt4_trades.profit` | 当前浮动盈亏�? |
| `records.data[].risk_value` | `profit - abs(commission)` | 第一阶段风险收益值�?? |
| `records.data[].margin_level` | `mt4_users.equity / mt4_users.margin * 100` | 追保预警保证金比例�?? |
| `summary.total_records` | 当前筛�?�后的行�? | 汇�?�卡片记录数�? |
| `summary.total_profit` | 当前筛�?�后的盈亏合�? | 风险持仓盈亏合计�? |
| `summary.total_volume` | 当前筛�?�后的手数合�? | 风险持仓手数合计�? |
| `summary.total_margin` | 当前筛�?�后的保证金合计 | 追保预警保证金合计�?? |
| `summary.total_risk_value` | 当前筛�?�后的风险�?�合�? | 风险值合计�?? |

### 权限与数据范�?

已有权限迁移 `2026_06_06_000005_add_admin_second_batch_module_permissions.php` 继续生效�?

| permissions.slug | permissions.type | route/api_route | 功能 |
| --- | ---: | --- | --- |
| `admin_risk` | 1 | `/admin/risk` | 后台风控页面/菜单入口�? |
| `admin_risk_positions` | 3 | `admin_api_riskPositions` | 风险持仓列表接口权限�? |
| `admin_risk_margin_calls` | 3 | `admin_api_riskMarginCalls` | 追保预警接口权限�? |
| `admin_risk_force_close` | 3 | `admin_api_riskForceClose` | 强平信号接口权限�? |

数据范围仍来自数据表配置�?

- `permissions`：页面�?�按钮�?�接口权限字典�??
- `role_permissions`：角色实际拥有权限的唯一生效来源�?
- `role_data_scopes`：角色级数据范围配置�?
- `admin_agent_bindings`：管理员绑定代理节点�?

### 当前边界

- 当前第一阶段不迁移旧项目异常 IP 明细，因为旧项目依赖 `system_login_log`，新项目当前主要登录日志表需要再做字段级确认�?
- 当前第一阶段不直接调�? MT4 服务器执行强平，只完成订单存在�?�未平仓和数据范围校验，并返回强平信号结果�??
- 当前真实 `mt4_trades` 表暂未确认旧项目 `COMMENT`、`MARGIN_RATE`、`MODIFY_TIME` 字段完整存在，因此不伪�?�旧项目精确盈利风险和实�?/测试盘特殊口径�??
- 真实 DB `127.0.0.1:3307` 当前不可连�?�，不能声称真实库迁移或真实样本查询已执行�??

### 数据库恢复后必须继续执行

```text
php artisan migrate --force
```

并用真实 DB 复核�?

- `permissions` 中是否存�? `admin_risk`、`admin_risk_positions`、`admin_risk_margin_calls`、`admin_risk_force_close`�?
- 目标后台角色是否通过 `role_permissions` 授权了风控页面�?�追保预警和强平信号接口�?
- `POST /api/admin/riskPositions` 是否按真�? `mt4_trades` 未平仓记录返回分页和汇�?��??
- `POST /api/admin/riskMarginCalls` 是否按真�? `mt4_users` 资金快照返回保证金比例低于阈值的用户�?
- `POST /api/admin/riskForceClose/{id}` 是否拒绝已平仓或数据范围外订单�??

## 30. 2026-06-07 后台风控异常 IP 第一阶段

本轮继续补齐旧项�? `FengXianManageController::fengXian_Ipaddress_list` 缺口。旧项目通过 `system_login_log` 聚合同一 IP 登录多个账号的风险；新项目当前真实表�? `user_login_logs`，因此第�?阶段基于 `user_login_logs.login_ip` 聚合�? IP 多账号登录风险，并继续使用后台权限与数据范围控制�?

### 本轮新增和维护文�?

- `app/Http/Controllers/Admin/RiskController.php`：新�? `riskIpList()`，读�? `UserLoginLog::query()`，按 `user_login_logs.login_ip` 聚合异常 IP�?
- `routes/admin.php`：新�? `POST /api/admin/riskIpList`，路由名 `admin_api_riskIpList`，位于后�? JWT、SSO、权限中间件组内�?
- `database/migrations/2026_06_06_000005_add_admin_second_batch_module_permissions.php`：在风控模块下新�? `admin_risk_ip_list` 接口权限字典�?
- `resources/admin/layui/risk/index.blade.php`：新增异�? IP 筛�?�项 `login_ip`、`min_user_count` �? `riskIpTable` 表格容器�?
- `public/js/admin/layui/risk/index.js`：新�? `ipRisk` 视图、`riskIpTable` 表格�? `/api/admin/riskIpList` 请求�?
- `resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`：新增异�? IP 后端多语�? key�?
- `public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`：新增异�? IP Layui 运行时多语言 key�?
- `tests/Feature/AdminRiskIpModuleTest.php`：新�? RED-GREEN 契约测试，约束路由�?�控制器数据源�?�Blade、JS 和权限迁移�??
- `tests/Feature/AdminChineseCommentReadabilityTest.php`：纳入本轮新增测试文件�??

### 页面与接�?

| 类型 | 地址/路由�? | 功能 |
| --- | --- | --- |
| 后台页面 | `GET /admin/risk` / `admin_page_risk` | 风控页面第三个视图：异常 IP�? |
| 异常 IP 接口 | `POST /api/admin/riskIpList` / `admin_api_riskIpList` | 返回同一 IP 登录多个业务账号的聚合风险列表�?? |

### 请求参数含义

| 参数 | 数据来源/字段 | 功能 |
| --- | --- | --- |
| `login_ip` | `user_login_logs.login_ip` | 按登�? IP 模糊查询�? |
| `user_id` | `user_login_logs.user_id` | 查询某个业务用户参与过的异常 IP�? |
| `min_user_count` | `COUNT(DISTINCT user_login_logs.user_id)` | 同一 IP 至少关联多少个不同用户才判定为风险，默认 2�? |
| `start_date` / `end_date` | `user_login_logs.created_at` | 登录时间戳范围筛选�?? |
| `page` | Laravel paginator | 当前页码�? |
| `per_page` / `limit` | Laravel paginator | 每页条数，兼�? Layui 默认分页参数�? |

### 返回字段与数据来�?

| 字段 | 数据来源 | 功能 |
| --- | --- | --- |
| `records.data[].login_ip` | `user_login_logs.login_ip` | 异常登录 IP�? |
| `records.data[].login_count` | `COUNT(*)` | �? IP 总登录次数�?? |
| `records.data[].distinct_user_count` | `COUNT(DISTINCT user_id)` | �? IP 关联的不同业务用户数量�?? |
| `records.data[].latest_login_at` | `MAX(user_login_logs.created_at)` | �?近一次登录时间戳�? |
| `records.data[].sample_user_name` | `MIN(user_infos.user_name)` | 示例用户名，用于快�?�识别风险来源�?? |
| `summary.total_records` | 当前筛�?�后�? IP 聚合行数 | 异常 IP 数量�? |

### 权限配置

| permissions.slug | permissions.type | route/api_route | 功能 |
| --- | ---: | --- | --- |
| `admin_risk_ip_list` | 3 | `admin_api_riskIpList` | 查看异常 IP 风控列表�? |

该权限仍挂在 `admin_risk` 页面权限下，角色是否拥有访问能力只由 `role_permissions` 决定�?

### 当前边界

- 第一阶段只做 IP 聚合列表，不展开 IP 下全部用户明细；后续可新增详情弹窗�??
- 当前 `user_login_logs.created_at` �? 10 位时间戳，页面暂直接展示原�?�；后续 UI 优化可统�?格式化日期�??
- 真实 DB `127.0.0.1:3307` 当前不可连�?�，因此不能声称真实异常 IP 样本已查询�??

### 数据库恢复后必须继续执行

```text
php artisan migrate --force
```

并用真实 DB 复核�?

- `permissions` 中是否存�? `admin_risk_ip_list`，且挂在 `admin_risk` 页面权限下�??
- 目标后台角色是否通过 `role_permissions` 授权 `admin_api_riskIpList`�?
- `POST /api/admin/riskIpList` 是否按真�? `user_login_logs` 返回�? IP 多账号聚合数据�??

## 31. 2026-06-07 后台风控异常 IP 详情第一阶段

本轮继续补齐旧项�? `FengXianManageController::fengXian_Ipaddress_detail` 缺口。旧项目详情会按登录 IP 展开账号明细，并补充用户名�?�上级�?�注册时间�?�开平仓数量和入出金统计；新项目本阶段基于当前真实表实现可维护版本：`user_login_logs` 负责登录明细，`user_infos` 负责用户资料和代理关系，`mt4_trades` 负责�?平仓数量，`deposit_records` �? `withdraw_records` 负责资金统计�?

### 本轮新增和维护文�?

- `tests/Feature/AdminRiskIpDetailModuleTest.php`：先�? RED 契约测试，约束路由�?�控制器、Blade、JS 和权限迁移必须完整�??
- `app/Http/Controllers/Admin/RiskController.php`：新�? `riskIpDetail()` �? `baseRiskIpDetailQuery()`，按 `login_ip` 精确展开异常 IP 下的账号详情�?
- `routes/admin.php`：新�? `POST /api/admin/riskIpDetail`，路由名 `admin_api_riskIpDetail`，继续位于后�? JWT、SSO、权限中间件组�??
- `database/migrations/2026_06_06_000005_add_admin_second_batch_module_permissions.php`：新�? `admin_risk_ip_detail` 权限字典，`api_route` 绑定 `admin_api_riskIpDetail`�?
- `resources/admin/layui/risk/index.blade.php`：新增异�? IP 详情按钮模板 `riskIpActions` 和详情弹�? `riskIpDetailDialog`、`riskIpDetailTable`�?
- `public/js/admin/layui/risk/index.js`：新增异�? IP 详情表格、`ipDetail` 行工具事件和 `/api/admin/riskIpDetail` 请求�?
- `resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`：新增后端多语言 key：`risk_ip_detail`、`risk_ip_detail_fetched`�?
- `public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`：新�? Layui 运行时多语言 key：`risk_ip_detail`、`risk_ip_detail_fetched`�?

### 页面与接�?

| 类型 | 地址/路由�? | 功能 |
| --- | --- | --- |
| 后台页面 | `GET /admin/risk` / `admin_page_risk` | 异常 IP 列表中点击�?�详情�?�打�? Blade + Layui 弹层�? |
| 异常 IP 列表接口 | `POST /api/admin/riskIpList` / `admin_api_riskIpList` | 返回同一 IP 登录多个业务账号的聚合风险列表�?? |
| 异常 IP 详情接口 | `POST /api/admin/riskIpDetail` / `admin_api_riskIpDetail` | �? `login_ip` 展开�? IP 下的业务账号、登录次数�?�交易统计和资金统计�? |

### 请求参数含义

| 参数 | 数据来源/字段 | 功能 |
| --- | --- | --- |
| `login_ip` | `user_login_logs.login_ip` | 必填，精确匹配异�? IP 聚合行，用于展开详情�? |
| `user_id` | `user_login_logs.user_id` | 可�?�，在详情弹层内定位某个业务用户�? |
| `start_date` / `end_date` | `user_login_logs.created_at` | 可�?�，按登录时间戳范围筛�?�详情记录�?? |
| `page` | Laravel paginator | 当前页码�? |
| `per_page` / `limit` | Laravel paginator | 每页条数，`limit` 用于兼容 Layui 默认分页参数�? |

### 返回字段与数据来�?

| 字段 | 数据来源 | 功能 |
| --- | --- | --- |
| `records.data[].login_ip` | `user_login_logs.login_ip` | 当前展开的异常登�? IP�? |
| `records.data[].user_id` | `user_login_logs.user_id` | �? IP 下登录过的业务用�? ID�? |
| `records.data[].user_name` | `user_infos.user_name` | 业务用户名�?? |
| `records.data[].parent_id` | `user_infos.parent_id` | 上级代理 ID，用于追踪代理链路风险�?? |
| `records.data[].account_type` | `user_infos.account_type` | 账号类型，便于区分代理与普�?�客户�?? |
| `records.data[].registered_at` | `user_infos.created_at` | 用户注册时间戳�?? |
| `records.data[].login_count` | `COUNT(*)` | 该用户在�? IP 下的登录次数�? |
| `records.data[].latest_login_at` | `MAX(user_login_logs.created_at)` | 该用户在�? IP 下最近一次登录时间戳�? |
| `records.data[].open_order_count` | `mt4_trades` 聚合 | 当前未平仓订单数量，口径为交易类 `cmd in (0..5)` �? `close_time IS NULL OR close_time = 0`�? |
| `records.data[].closed_order_count` | `mt4_trades` 聚合 | 历史平仓订单数量，口径为交易�? `cmd in (0..5)` �? `close_time > 0`�? |
| `records.data[].total_deposit` | `deposit_records.amount` | 当前业务入金表中的入金金额合计�?? |
| `records.data[].total_withdraw` | `withdraw_records.apply_amount` | 当前业务出金表中的申请出金金额合计�?? |

### 权限配置

| permissions.slug | permissions.type | route/api_route | 功能 |
| --- | ---: | --- | --- |
| `admin_risk_ip_detail` | 3 | `admin_api_riskIpDetail` | 查看异常 IP 详情�? |

该权限挂�? `admin_risk` 页面权限下，前端按钮�? `data-permission="admin_risk_ip_detail"` 控制显示，后端接口用 `permissions.api_route = admin_api_riskIpDetail` �? `check.permission:admin` 做强制鉴权�?�角色是否拥有能力仍只由 `role_permissions` 中间表决定�??

### 数据范围

- 异常 IP 详情接口继续调用 `AdminDataScopeService->apply($query, $admin, 'user', 'user_login_logs.user_id')`�?
- 不同后台管理员只能看到数据表配置允许的数据范围；数据范围来源仍是 `role_data_scopes` �? `admin_agent_bindings`�?
- 详情接口不会绕过异常 IP 列表权限，必须单独拥�? `admin_risk_ip_detail` 对应接口权限�?

### 当前边界

- 本阶段不执行旧项目里被注�?/关闭�? MT4 同步动作，不伪�?? MT4 服务端实时刷新�??
- 本阶段资金统计使用新项目真实业务�? `deposit_records` �? `withdraw_records`，不强行复刻旧项目依�? `MT4_TRADES.COMMENT REGEXP` 的资金识别口径�??
- 真实 DB `127.0.0.1:3307` 当前不可连�?�，因此不能声称真实库样本已经查出；本轮已完成代码�?�路由�?�Blade、JS、语�?包和�? DB 契约验证�?

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminRiskIpDetailModuleTest.php
OK (5 tests, 26 assertions)

vendor\bin\phpunit tests\Feature\AdminRiskIpModuleTest.php
OK (5 tests, 22 assertions)

vendor\bin\phpunit tests\Feature\AdminRiskMt4ModuleTest.php
OK (4 tests, 31 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
OK (3 tests, 1521 assertions)

php -l app\Http\Controllers\Admin\RiskController.php
No syntax errors detected

php -l routes\admin.php
No syntax errors detected

php -l database\migrations\2026_06_06_000005_add_admin_second_batch_module_permissions.php
No syntax errors detected

php -l resources\lang\zh-CN\admin.php
No syntax errors detected

php -l resources\lang\en\admin.php
No syntax errors detected

node --check public\js\admin\layui\risk\index.js
通过，无语法输出

node --check public\js\common\lang\zh-CN.js
通过，无语法输出

node --check public\js\common\lang\en.js
通过，无语法输出

php artisan route:list --path=risk
已确�? admin_api_riskIpDetail 位于 api、jwt.auth:admin、sso:admin、check.permission:admin 中间件组�?
```

�? DB 连接影响的验证：

```text
vendor\bin\phpunit tests\Feature\AdminSecondBatchPermissionMigrationTest.php
ERROR: SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接�?

Test-NetConnection 127.0.0.1 -Port 3307
TcpTestSucceeded: False
```

### 数据库恢复后必须继续执行

```text
php artisan migrate --force
vendor\bin\phpunit tests\Feature\AdminSecondBatchPermissionMigrationTest.php
```

并用真实 DB 复核�?
- `permissions` 中是否存�? `admin_risk_ip_detail`，且 `api_route` �? `admin_api_riskIpDetail`�?
- 目标后台角色是否通过 `role_permissions` 授权 `admin_risk_ip_detail`�?
- `POST /api/admin/riskIpDetail` 是否按真�? `user_login_logs`、`user_infos`、`mt4_trades`、`deposit_records`、`withdraw_records` 返回正确详情�?
- 数据范围配置是否能限制普通管理员只看到授权代理或用户范围内的 IP 详情�?

## 32. 2026-06-07 后台批量入金/出金导入失败重试第一阶段

本轮继续按迁移缺口审计中�? P0 资金导入链路推进，补齐旧项目 `BatchAmountController::againDepositAmount` �? `BatchAmountController::againWithdrawAmount` 的第�?阶段新项目落点�?�当前阶段不伪�?? MT4 同步结果，只把失败导入记录重新放回待处理队列，供后续真实同步或人工处理链路继续执行�??

### 本轮新增和维护文�?

- `tests/Feature/AdminBatchAmountImportRetryModuleTest.php`：先�? RED 契约测试，约束失败重试路由�?�控制器、Blade、JS 和权限迁移必须完整�??
- `app/Http/Controllers/Admin/BatchAmountImportController.php`：新�? `retryDepositImport()`、`retryWithdrawImport()` �? `retryImportRecord()`，只允许 `is_synced=2` 的失败记录回到待处理状�?��??
- `routes/admin.php`：新�? `POST /api/admin/retryDepositImport/{id}` �? `POST /api/admin/retryWithdrawImport/{id}`，继续位于后�? JWT、SSO、权限中间件组�??
- `database/migrations/2026_06_07_000004_add_admin_batch_amount_import_permissions.php`：新�? `admin_batch_deposit_import_retry` �? `admin_batch_withdraw_import_retry` 权限字典�?
- `tests/Feature/AdminBatchAmountImportPermissionMigrationTest.php`：同步补充两�? retry 权限断言，数据库恢复后会继续验证权限是否真正写入 `permissions` 表�??
- `resources/admin/layui/deposit-imports/index.blade.php`：新增入金导入重试按钮模�? `depositImportActions`�?
- `resources/admin/layui/withdraw-imports/index.blade.php`：新增出金导入重试按钮模�? `withdrawImportActions`�?
- `public/js/admin/layui/deposit-imports/index.js`：新�? `retryDepositImport` 行工具事件，调用 `/api/admin/retryDepositImport/{id}`�?
- `public/js/admin/layui/withdraw-imports/index.js`：新�? `retryWithdrawImport` 行工具事件，调用 `/api/admin/retryWithdrawImport/{id}`�?
- `resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`：新增后端重试成功�?�记录不存在、非失败记录不可重试等多语言 key�?
- `public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`：新增前端按钮和确认提示多语�? key�?

### 页面与接�?

| 类型 | 地址/路由�? | 功能 |
| --- | --- | --- |
| 入金导入页面 | `GET /admin/deposit-imports` / `admin_page_deposit_imports` | 在入金导入列表中提供失败记录重试按钮�? |
| 出金导入页面 | `GET /admin/withdraw-imports` / `admin_page_withdraw_imports` | 在出金导入列表中提供失败记录重试按钮�? |
| 入金重试接口 | `POST /api/admin/retryDepositImport/{id}` / `admin_api_retryDepositImport` | 将失败的入金导入记录重新置为待处理�?? |
| 出金重试接口 | `POST /api/admin/retryWithdrawImport/{id}` / `admin_api_retryWithdrawImport` | 将失败的出金导入记录重新置为待处理�?? |

### 请求参数含义

| 参数 | 数据来源/字段 | 功能 |
| --- | --- | --- |
| `id` | `deposit_imports.id` �? `withdraw_imports.id` | 必填，导入记录主键，用于定位要重试的单条失败记录�? |

### 重试业务规则

| 字段/条件 | 规则 |
| --- | --- |
| `is_synced = 2` | 只有同步失败的记录可以重试�?? |
| `is_synced = 0` | 重试后回到待处理状�?�，等待后续真实同步流程处理�? |
| `fail_reason = ''` | 重试后清空旧失败原因，避免页面继续展示过期错误�?? |
| `updated_by` | 写入当前后台管理�? ID；若测试或无登录上下文则�? 0�? |
| 数据范围 | 重试前继续调�? `AdminDataScopeService`，管理员只能重试自己可见范围内的导入记录�? |

### 权限配置

| permissions.slug | permissions.type | route/api_route | 功能 |
| --- | ---: | --- | --- |
| `admin_batch_deposit_import_retry` | 3 | `admin_api_retryDepositImport` | 重试失败的批量入金导入记录�?? |
| `admin_batch_withdraw_import_retry` | 3 | `admin_api_retryWithdrawImport` | 重试失败的批量出金导入记录�?? |

前端按钮使用 `data-permission` 控制可见性，后端接口继续使用 `permissions.api_route` �? `check.permission:admin` 强制鉴权。角色是否拥有重试能力仍只由 `role_permissions` 中间表决定�??

### 当前边界

- 本阶段不执行 Excel/CSV 文件解析�?
- 本阶段不直接执行 MT4 入金、出金或同步动作�?
- 本阶段不生成导入模板和导出文件�??
- 本阶段只提供失败导入记录重新入队能力，为后续真实同步链路留出清晰状�?��??

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminBatchAmountImportRetryModuleTest.php
OK (5 tests, 27 assertions)

vendor\bin\phpunit tests\Feature\AdminBatchAmountImportModuleTest.php
OK (4 tests, 30 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
OK (3 tests, 1521 assertions)

php -l app\Http\Controllers\Admin\BatchAmountImportController.php
No syntax errors detected

php -l routes\admin.php
No syntax errors detected

php -l database\migrations\2026_06_07_000004_add_admin_batch_amount_import_permissions.php
No syntax errors detected

php -l resources\lang\zh-CN\admin.php
No syntax errors detected

php -l resources\lang\en\admin.php
No syntax errors detected

node --check public\js\admin\layui\deposit-imports\index.js
通过，无语法输出

node --check public\js\admin\layui\withdraw-imports\index.js
通过，无语法输出

node --check public\js\common\lang\zh-CN.js
通过，无语法输出

node --check public\js\common\lang\en.js
通过，无语法输出

php artisan route:list --path=Import
已确�? admin_api_retryDepositImport �? admin_api_retryWithdrawImport 位于 api、jwt.auth:admin、sso:admin、check.permission:admin 中间件组�?
```

�? DB 连接影响的验证：

```text
vendor\bin\phpunit tests\Feature\AdminBatchAmountImportPermissionMigrationTest.php
ERROR: SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接�?

Test-NetConnection 127.0.0.1 -Port 3307
TcpTestSucceeded: False
```

### 数据库恢复后必须继续执行

```text
php artisan migrate --force
vendor\bin\phpunit tests\Feature\AdminBatchAmountImportPermissionMigrationTest.php
```

并用真实 DB 复核�?
- `permissions` 中是否存�? `admin_batch_deposit_import_retry`，且 `api_route` �? `admin_api_retryDepositImport`�?
- `permissions` 中是否存�? `admin_batch_withdraw_import_retry`，且 `api_route` �? `admin_api_retryWithdrawImport`�?
- 目标后台角色是否通过 `role_permissions` 授权两个重试权限�?
- 失败记录 `is_synced=2` 调用重试后是否变�? `is_synced=0` �? `fail_reason` 清空�?
- 待处理或成功记录调用重试时是否返�? `import_retry_only_failed`，避免重复进入队列�??

## 33. 2026-06-07 后台批量信用导入失败重试第一阶段

本轮继续补齐旧项�? `BatchCreditController::againCreditAmount` 缺口。当前阶段保持与批量入金/出金导入重试�?致的安全边界：失败重试只把失败记录重新放回待处理队列，不伪�?? MT4 信用同步成功，也不直接改动用户信用额度�??

### 本轮新增和维护文�?

- `tests/Feature/AdminBatchCreditImportRetryModuleTest.php`：先�? RED 契约测试，约束信用导入失败重试路由�?�控制器、Blade、JS 和权限迁移�??
- `app/Http/Controllers/Admin/BatchCreditImportController.php`：新�? `retryCreditImport()`，只允许 `is_synced=2` 的失败记录回到待处理状�?��??
- `routes/admin.php`：新�? `POST /api/admin/retryCreditImport/{id}`，路由名 `admin_api_retryCreditImport`�?
- `database/migrations/2026_06_07_000005_add_admin_batch_credit_import_permissions.php`：新�? `admin_batch_credit_import_retry` 权限字典�?
- `tests/Feature/AdminBatchCreditImportPermissionMigrationTest.php`：同步补�? retry 权限断言，数据库恢复后会验证权限是否真正写入 `permissions` 表�??
- `resources/admin/layui/credit-imports/index.blade.php`：新增信用导入重试按钮模�? `creditImportActions`�?
- `public/js/admin/layui/credit-imports/index.js`：新�? `retryCreditImport` 行工具事件，调用 `/api/admin/retryCreditImport/{id}`�?
- `resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`：新�? `credit_import_retry_success` 后端多语�? key�?

### 页面与接�?

| 类型 | 地址/路由�? | 功能 |
| --- | --- | --- |
| 信用导入页面 | `GET /admin/credit-imports` / `admin_page_credit_imports` | 在信用导入列表中提供失败记录重试按钮�? |
| 信用重试接口 | `POST /api/admin/retryCreditImport/{id}` / `admin_api_retryCreditImport` | 将失败的信用导入记录重新置为待处理�?? |

### 请求参数含义

| 参数 | 数据来源/字段 | 功能 |
| --- | --- | --- |
| `id` | `credit_imports.id` | 必填，信用导入记录主键，用于定位要重试的单条失败记录�? |

### 重试业务规则

| 字段/条件 | 规则 |
| --- | --- |
| `is_synced = 2` | 只有同步失败的信用导入记录可以重试�?? |
| `is_synced = 0` | 重试后回到待处理状�?�，等待后续真实 MT4 信用同步或人工处理�?? |
| `fail_reason = ''` | 重试后清空旧失败原因，避免页面展示过期错误�?? |
| `updated_by` | 写入当前后台管理�? ID；无登录上下文时�? 0�? |
| 数据范围 | 重试前继续调�? `AdminDataScopeService`，管理员只能重试自己可见范围内的信用导入记录�? |

### 权限配置

| permissions.slug | permissions.type | route/api_route | 功能 |
| --- | ---: | --- | --- |
| `admin_batch_credit_import_retry` | 3 | `admin_api_retryCreditImport` | 重试失败的批量信用导入记录�?? |

前端按钮使用 `data-permission="admin_batch_credit_import_retry"` 控制显示，后端接口继续使�? `permissions.api_route = admin_api_retryCreditImport` �? `check.permission:admin` 强制鉴权。角色是否拥有重试能力仍只由 `role_permissions` 中间表决定�??

### 当前边界

- 本阶段不执行 Excel/CSV 文件解析�?
- 本阶段不执行 MT4 信用额度同步�?
- 本阶段不直接修改 `user_infos` 的信用�?�权益或保证金字段�??
- 本阶段只提供失败信用导入记录重新入队能力，为后续真实同步链路保留清晰状�?��??

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminBatchCreditImportRetryModuleTest.php
OK (5 tests, 16 assertions)

vendor\bin\phpunit tests\Feature\AdminBatchCreditImportModuleTest.php
OK (3 tests, 16 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
OK (3 tests, 1521 assertions)

php -l app\Http\Controllers\Admin\BatchCreditImportController.php
No syntax errors detected

php -l routes\admin.php
No syntax errors detected

php -l database\migrations\2026_06_07_000005_add_admin_batch_credit_import_permissions.php
No syntax errors detected

php -l resources\lang\zh-CN\admin.php
No syntax errors detected

php -l resources\lang\en\admin.php
No syntax errors detected

node --check public\js\admin\layui\credit-imports\index.js
通过，无语法输出

node --check public\js\common\lang\zh-CN.js
通过，无语法输出

node --check public\js\common\lang\en.js
通过，无语法输出

php artisan route:list --path=CreditImport
已确�? admin_api_retryCreditImport 位于 api、jwt.auth:admin、sso:admin、check.permission:admin 中间件组�?
```

�? DB 连接影响的验证：

```text
vendor\bin\phpunit tests\Feature\AdminBatchCreditImportPermissionMigrationTest.php
ERROR: SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接�?

Test-NetConnection 127.0.0.1 -Port 3307
TcpTestSucceeded: False
```

### 数据库恢复后必须继续执行

```text
php artisan migrate --force
vendor\bin\phpunit tests\Feature\AdminBatchCreditImportPermissionMigrationTest.php
```

并用真实 DB 复核�?
- `permissions` 中是否存�? `admin_batch_credit_import_retry`，且 `api_route` �? `admin_api_retryCreditImport`�?
- 目标后台角色是否通过 `role_permissions` 授权 `admin_batch_credit_import_retry`�?
- 失败信用导入记录 `is_synced=2` 调用重试后是否变�? `is_synced=0` �? `fail_reason` 清空�?
- 待处理或成功信用导入记录调用重试时是否返�? `import_retry_only_failed`，避免重复进入队列�??

## 34. 2026-06-07 后台权益汇�?�手动确认第�?阶段

本轮继续对比旧项�? `RightsSummaryController::ManualConfirmWithdrawOrdeposit`，补齐新项目权益汇�?�模块中可安全落库的手动确认能力。自�? MT4 入出金确认仍保持未迁移状态，避免�? MT4 链路未完整验证前伪�?�结算成功�??

### 本轮新增和维护文�?

- `tests/Feature/AdminRightsSummaryManualConfirmModuleTest.php`：按 TDD 先写 RED 测试，约束手动确�? API、控制器、Blade、JS 和权限迁移�??
- `app/Http/Controllers/Admin/RightsSummaryController.php`：新�? `manualConfirmRightsSettlement()`，并在权益汇总列表中关联每个用户�?新一�? `rights_settlements` 记录�?
- `routes/admin.php`：新�? `POST /api/admin/manualConfirmRightsSettlement/{id}`，路由名 `admin_api_manualConfirmRightsSettlement`�?
- `database/migrations/2026_06_07_000007_add_admin_rights_summary_permissions.php`：新�? `admin_rights_summary_manual_confirm` 权限字典，`api_route` 指向 `admin_api_manualConfirmRightsSettlement`�?
- `resources/admin/layui/rights-summary/index.blade.php`：新增行操作按钮模板 `rightsSummaryActions` 和手动确认弹窗表单�??
- `public/js/admin/layui/rights-summary/index.js`：新增操作列、结算状态展示�?�手动确认弹窗提交和权限刷新�?
- `resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`：新增手动确认后端多语言消息�?
- `public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`：新增手动确认前端多语言文案�?

### 页面与接�?

| 类型 | 地址/路由�? | 功能 |
| --- | --- | --- |
| 权益汇�?�页�? | `GET /admin/rights-summary` / `admin_page_rights_summary` | 展示 MT4 权益汇�?��?�最新权益结算金额和结算状�?�，并提供手动确认入口�?? |
| 手动确认接口 | `POST /api/admin/manualConfirmRightsSettlement/{id}` / `admin_api_manualConfirmRightsSettlement` | �? `rights_settlements.status=0` 的待处理记录人工确认为已处理�? |

### 请求参数含义

| 参数 | 数据来源/字段 | 功能 |
| --- | --- | --- |
| `id` | `rights_settlements.id` | 必填，权益结算记录主键，用于定位待确认记录�?? |
| `manual_confirm_reason` | 写入 `rights_settlements.remark` | 必填，人工确认原因或财务备注，用于后续审计追踪�?? |

### 业务规则

| 字段/条件 | 规则 |
| --- | --- |
| `rights_settlements.status = 0` | 只有待处理记录可以手动确认�?? |
| `rights_settlements.status = 1` | 手动确认后置为已处理�? |
| `rights_settlements.remark` | 写入 `manual_confirm_reason`，保留人工确认原因�?? |
| `updated_at` | 写入当前 Unix 时间戳�?? |
| 数据范围 | 确认前调�? `AdminDataScopeService::canAccessUser()`，管理员只能确认自己可见业务用户的权益结算记录�?? |
| MT4 边界 | 本阶段不调用 MT4 入金/出金接口，不�? `mt4_trades`，不伪�?�自动结算成功�?? |

### 权限配置

| permissions.slug | permissions.type | route/api_route | 功能 |
| --- | ---: | --- | --- |
| `admin_rights_summary_manual_confirm` | 3 | `admin_api_manualConfirmRightsSettlement` | 手动确认权益结算记录�? |

前端按钮使用 `data-permission="admin_rights_summary_manual_confirm"` 控制显示，后端接口继续使�? `permissions.api_route = admin_api_manualConfirmRightsSettlement` �? `check.permission:admin` 强制鉴权。角色是否拥有该能力仍只�? `role_permissions` 中间表决定�??

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminRightsSummaryManualConfirmModuleTest.php
OK (5 tests, 17 assertions)

vendor\bin\phpunit tests\Feature\AdminRightsSummaryModuleTest.php
OK (3 tests, 11 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
OK (3 tests, 1521 assertions)

php -l app\Http\Controllers\Admin\RightsSummaryController.php
No syntax errors detected

php -l routes\admin.php
No syntax errors detected

php -l database\migrations\2026_06_07_000007_add_admin_rights_summary_permissions.php
No syntax errors detected

php -l resources\lang\zh-CN\admin.php
No syntax errors detected

php -l resources\lang\en\admin.php
No syntax errors detected

node --check public\js\admin\layui\rights-summary\index.js
通过，无语法输出

node --check public\js\common\lang\zh-CN.js
通过，无语法输出

node --check public\js\common\lang\en.js
通过，无语法输出

php artisan route:list --path=Rights
已确�? admin_api_manualConfirmRightsSettlement 位于 api、jwt.auth:admin、sso:admin、check.permission:admin 中间件组�?

rg -n "\?\?\?" 本轮触碰文件
无命�?
```

�? DB 连接影响的验证：

```text
Test-NetConnection 127.0.0.1 -Port 3307
TcpTestSucceeded: False
```

### 数据库恢复后必须继续执行

```text
php artisan migrate --force
```

并用真实 DB 复核�?
- `permissions` 中是否存�? `admin_rights_summary_manual_confirm`，且 `api_route` �? `admin_api_manualConfirmRightsSettlement`�?
- 目标后台角色是否通过 `role_permissions` 授权 `admin_rights_summary_manual_confirm`�?
- `rights_settlements.status=0` 记录调用手动确认后是否变�? `status=1`，且 `remark` 写入确认原因�?
- `rights_settlements.status=1` 记录再次调用是否返回 `rights_settlement_only_pending`�?
- 非当前管理员数据范围内的 `user_id` 是否无法被确认�??

## 35. 2026-06-07 后台 zh-CN 语言包可读�?�修�?

本轮继续推进“后端必须支持多语言”的目标，重点修�? `resources/lang/zh-CN/admin.php` 中历史编码错解产生的不可读中文�?�该文件虽然此前可以通过 `php -l`，但 Laravel 运行时读�? `__('admin.dashboard')`、`__('admin.rights_summary')` �? key 时返回乱码，导致后台页面标题、接口提示和权限相关消息不可读�??

### 本轮维护文件

- `resources/lang/zh-CN/admin.php`：重建后台中文语�?包，key 数量�? `resources/lang/en/admin.php` 保持�?致�??
- `tests/Feature/AdminZhCnLanguageReadabilityTest.php`：作为本�? RED 测试，约束后台高频中�? key 和常见乱码片段�??
- `docs/admin-backend-blade-permission-final-checklist.md`：记录本轮修复�?�验证结果和仍需后续继续精翻的边界�??

### 语言包规�?

| 项目 | 规则 |
| --- | --- |
| key 来源 | �? `resources/lang/en/admin.php` 为权�? key 集合，当�? `zh-CN/admin.php` 已对�? 453 �? key�? |
| 中文覆盖 | 后台登录、菜单�?�权限�?�数据范围�?�资金�?�导入�?�权益汇总�?�持仓汇总�?�在线用户�?�产品�?�礼品�?�认证审核和风控等重点模块已恢复可读中文�? |
| 临时兜底 | 少量暂未精翻 key 保留英文可读文案，避免再次出现乱码；后续可以按模块继续补全中文精翻�?? |
| 编码边界 | 不再通过 PowerShell 管道直接写中文语�?包，避免控制台编码链路再次污�? UTF-8 内容�? |

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminZhCnLanguageReadabilityTest.php
OK (2 tests, 25 assertions)

vendor\bin\phpunit tests\Feature\AdminLocalizationTest.php
OK (2 tests, 10 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
OK (3 tests, 1521 assertions)

vendor\bin\phpunit tests\Feature\AdminRightsSummaryManualConfirmModuleTest.php
OK (5 tests, 17 assertions)

vendor\bin\phpunit tests\Feature\AdminRightsSummaryModuleTest.php
OK (3 tests, 11 assertions)

php -l resources\lang\zh-CN\admin.php
No syntax errors detected in resources\lang\zh-CN\admin.php

php -l resources\lang\en\admin.php
No syntax errors detected in resources\lang\en\admin.php
```

### key 对齐结果

```text
en=453
zh=453
missing=0
extra=0
dashboard=控制�?
risk_ip_detail=异常IP详情
```

### 后续待继�?

- 继续按模块把少量英文兜底 key 精翻为中文，但必须保�? `AdminZhCnLanguageReadabilityTest` 通过�?
- 前端运行时语�?�? `public/js/common/lang/zh-CN.js` 仍需要单独审计，不能把它作为后端中文修复的数据源�?
- 当前 MySQL `127.0.0.1:3307` 仍需恢复后再执行真实 DB 迁移落库、权限配置和真实样本接口验证�?

## 36. 2026-06-07 后台 Blade UI 外壳测试可读性修�?

本轮继续推进“后�? UI 必须使用 Laravel Blade + JS + CSS，并参�?? Vben Admin、Vue Naive Admin、Naive UI Admin、Ant Design Pro、Arco Design Pro 的现代后台风格�?�的目标。先审计 `public/js/common/lang/zh-CN.js`，�?�过 Node 运行时解析确�? `admin.rights_summary`、`admin.risk_ip_detail` 等后台运行时文案已经是可读中文，未发现常见乱码片段，因此本轮没有强行修改运行时语�?包�??

随后发现 `tests/Feature/AdminLayoutUiModernizationTest.php` 本身存在不可读中文注释和断言消息。虽然测试可执行，但不符合�?�所有模块文件及参数必须有详细中文注释和逻辑注释”的要求。本轮已将该测试重写为可读中文注释，并继续保留对后台总布�?和公�? CSS 的现代化约束�?

### 本轮维护文件

- `tests/Feature/AdminLayoutUiModernizationTest.php`：重写乱码注释和断言消息，继续约束后台统�? Blade 外壳、CSS 设计变量、Layui 组件覆盖和常见乱码风险�??
- `tests/Feature/AdminLayoutShellReadabilityTest.php`：新增后台�?�布�?外壳可读性测试，约束 `data-shell-label`、页�? kicker、菜单加载说明�?�主�?/界面切换等静态中文文案�??
- `docs/admin-backend-blade-permission-final-checklist.md`：记录本�? UI 外壳测试可读性修复和验证结果�?

### UI 外壳约束�?

| 约束�? | 文件 | 作用 |
| --- | --- | --- |
| `crm-admin-workbench`、`crm-admin-shell`、`crm-admin-topbar`、`crm-admin-sidebar` | `resources/admin/layui/layouts/app.blade.php` | 保证�?有后�? Blade 页面继续挂统�?工作台外壳�?? |
| `crm-admin-page-head`、`data-shell-label="后台工作�?"` | `resources/admin/layui/layouts/app.blade.php` | 保证页面头部与外壳语义可读�?? |
| `--admin-radius`、`--admin-sidebar-width`、`--admin-header-height`、`--admin-shadow` | `public/css/admin/style.css` | 保证后台 CSS 继续具备现代管理台设计变量�?? |
| `.layui-card`、`.layui-form-pane`、`.layui-table-view`、`.layui-layer`、`.layui-laypage` | `public/css/admin/style.css` | 保证 Layui 组件被统�?覆盖为更接近现代中后台的信息密度和视觉风格�?? |

### 本轮验证记录

```text
node -e 解析 public/js/common/lang/zh-CN.js
locale=zh-CN
admin.rights_summary=权益汇�??
admin.risk_ip_detail=异常IP详情
bad=

vendor\bin\phpunit tests\Feature\AdminLayoutUiModernizationTest.php
OK (2 tests, 33 assertions)

vendor\bin\phpunit tests\Feature\AdminLayoutShellReadabilityTest.php
OK (2 tests, 18 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
OK (3 tests, 1521 assertions)

vendor\bin\phpunit tests\Feature\AdminLocalizationTest.php
OK (2 tests, 10 assertions)

vendor\bin\phpunit tests\Feature\AdminZhCnLanguageReadabilityTest.php
OK (2 tests, 25 assertions)

php -l tests\Feature\AdminLayoutUiModernizationTest.php
No syntax errors detected

php -l tests\Feature\AdminLayoutShellReadabilityTest.php
No syntax errors detected
```

### 后续待继�?

- 继续逐页审计后台 Blade 页面内部卡片、表单�?�表格�?�弹窗和操作区是否符合现代中后台布局密度�?
- `public/js/common/lang/zh-CN.js` 当前关键后台运行时文案可读，但后续新�? key 仍必须�?�过 Node 解析验证，不能只�? PowerShell 终端输出�?
- 真实 DB `127.0.0.1:3307` 恢复后，仍需继续验证后台菜单权限、按钮权限和数据范围在真实角色下的页面表现�??
## 37. 2026-06-07 后台公共 CSS 中文逻辑注释回归保护
本轮继续推进“所有模块文件及参数必须有详细中文注释和逻辑注释”的目标，重点审计后�? Blade 页面共享样式文件 `public/css/admin/style.css`。该文件承担后台工作台外壳�?�Layui 卡片、表单�?�表格�?�弹窗等公共视觉组件的统�?样式职责，因此注释需要解释组件用途和设计边界，不能只依赖样式选择器本身表达意图�??

排查时发�? PowerShell `Get-Content` 输出会把部分 UTF-8 中文显示成乱码，但�?�过专门测试直接读取文件内容后，`public/css/admin/style.css` 中关键中文注释实际已经是可读文本，没有命中常�? UTF-8/GBK 错误解码片段。因此本轮没有改�? CSS 样式行为，也没有重写样式文件，只新增回归测试把这�?状�?�固定下来�??

### 本轮维护文件

- `tests/Feature/AdminCssCommentReadabilityTest.php`：新增后台公�? CSS 注释可读性测试，约束 `.crm-admin-main`、Layui 卡片、Layui 表单、Layui 表格、Layui 弹窗等关键注释必须保持可读中文�??
- `docs/admin-backend-blade-permission-final-checklist.md`：记录本轮审计结论�?�验证命令和后续边界�?

### 测试约束说明

| 约束�? | 作用 |
| --- | --- |
| `.crm-admin-main：后台业务页面内容容器` | 确认后台业务页面主内容容器注释可读，后续维护者能理解页面留白和最大阅读宽度的用�?��?? |
| `Layui 卡片：用于列表筛选区、表格区和详情区` | 确认后台常见信息分区组件的注释可读，避免卡片样式被误当成装饰性容器�?? |
| `Layui 表单：统�?输入框�?��?�择框�?�日期框` | 确认表单组件统一高度、圆角和聚焦反馈的设计意图可读�?? |
| `Layui 表格：后台核心列表组件` | 确认后台核心列表组件的表头�?�边框�?�分页和滚动区域说明可读�? |
| `Layui 弹窗：用于新增�?�编辑�?�确认等后台操作` | 确认弹窗用于后台关键操作场景的说明可读�?? |
| 常见乱码片段黑名�? | 防止后续把中文注释错误写�? `鐨`、`鏉`、`锛`、`鍙` 等不可读片段�? |

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminCssCommentReadabilityTest.php
OK (2 tests, 15 assertions)

vendor\bin\phpunit tests\Feature\AdminLayoutUiModernizationTest.php tests\Feature\AdminLayoutShellReadabilityTest.php
OK (2 tests, 33 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
OK (3 tests, 1521 assertions)

php -l tests\Feature\AdminCssCommentReadabilityTest.php
No syntax errors detected in tests\Feature\AdminCssCommentReadabilityTest.php
```

### 后续待继�?

- `routes/web.php`、`routes/admin.php`、`MenuController`、`MenuService` 等历史文件仍存在 PowerShell 输出层面或真实源码层面的中文乱码风险，需要继续分批用测试和运行时解析区分，不能仅凭终端显示判断文件损坏�??
- 真实 DB `127.0.0.1:3307` 当前仍未完成本轮连�?�验证；数据库恢复后，需要继续使用真�? `permissions`、`roles`、`role_permissions`、`user_logins.role_id` 数据验证前后台菜单权限�??

## 38. 2026-06-07 默认后台账号与前台菜单角色边界测试增�?
本轮继续推进“前后台�?有菜单可控，前台分代理商和普通客户两个菜单配置�?�的目标，重点审�? `database/migrations/2026_06_07_000014_fix_default_admin_and_front_menu_roles.php` �? `tests/Feature/DefaultAdminAndFrontMenuRoleMigrationTest.php`。该迁移负责写入默认超级管理员�?�前�? agent/customer 角色、前台菜单权限字典，以及 `role_permissions` 授权关系，是解决 agent 登录�? Layui 菜单为空问题的核心配置来源之�?�?

本轮没有修改迁移源码，因为当前迁移语法可解析，且源码�? agent/customer 菜单集合已经分离；本轮新增测试约束，直接通过反射读取迁移里的 `agentMenuSlugs()` �? `customerMenuSlugs()` 私有配置，确认两套前台菜单授权边界不会被后续改坏�?

### 本轮维护文件

- `tests/Feature/DefaultAdminAndFrontMenuRoleMigrationTest.php`：新�? `test_front_agent_and_customer_roles_declare_different_menu_scopes`，约束代理商菜单必须包含代理管理和返佣管理，普�?�客户菜单不能包含代�?/返佣专属权限�?
- `docs/admin-backend-blade-permission-final-checklist.md`：记录本轮权限边界测试增强�?�验证结果和真实 DB 待验证边界�??

### 权限边界规则

| 角色 | 必须包含 | 必须排除 |
| --- | --- | --- |
| `agent_role` | `front_agent`、`front_agent_sub`、`front_agent_customers`、`front_commission`、`front_commission_rt` 等代�?/返佣菜单，以及控制台、个人中心�?�账户�?�资金�?�交易�?�礼品�?�公告等通用菜单�? | 无，本角色用于代理商，允许查看代理与返佣菜单�? |
| `customer_role` | `front_dashboard`、`front_profile`、`front_account`、`front_deposit_withdraw`、`front_trading`、`front_gift`、`front_news` 等普通客户�?�用菜单�? | `front_agent`、`front_agent_sub`、`front_agent_customers`、`front_commission`、`front_commission_rt` 等代�?/返佣专属菜单�? |

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\DefaultAdminAndFrontMenuRoleMigrationTest.php
OK (2 tests, 57 assertions)

php -l tests\Feature\DefaultAdminAndFrontMenuRoleMigrationTest.php
No syntax errors detected in tests\Feature\DefaultAdminAndFrontMenuRoleMigrationTest.php

php -l database\migrations\2026_06_07_000014_fix_default_admin_and_front_menu_roles.php
No syntax errors detected in database\migrations\2026_06_07_000014_fix_default_admin_and_front_menu_roles.php
```

### 后续待继�?

- 真实 DB `127.0.0.1:3307` 恢复后，必须执行或确�? `php artisan migrate` 已落库，并查�? `roles`、`permissions`、`role_permissions`、`user_logins.role_id`，验�? `agent_role` �? `customer_role` 授权关系真实存在�?
- 用真�? agent 账号登录后，请求 `/api/front/navigation/menus`，确认返回的 `data` 中包含代理菜单；用普通客户账号登录同接口，确认不包含代理和返佣菜单�??

## 39. 2026-06-07 前后台菜单中文语�?包运行时可读性回归保�?
本轮继续推进“后端必须支持多语言”和“前后台�?有菜单可控�?�的目标，重点审�? `MenuService::buildTree()` 依赖�? `resources/lang/zh-CN/menus.php`。菜单服务会通过 `__('menus.' . $menu->slug)` 给前�? Layui、后�? Layui �? Blade 页面返回菜单标题，因此菜单语�?包的可读性会直接影响代理商�?�普通客户和后台管理员看到的导航文案�?

排查�? PowerShell `Get-Content` 会把 `zh-CN/menus.php` 显示成乱码，�? Laravel/PHP 运行时读取结果为可读中文。本轮新增专门测试，约束中文菜单包与英文菜单�? key 对齐，并锁定前后台高频菜单标题，例如 `front_dashboard=控制台`、`front_agent=代理管理`、`admin_system=系统管理`，避免后续误写乱码或�? key�?

### 本轮维护文件

- `tests/Feature/MenuZhCnLanguageReadabilityTest.php`：新增菜单中文语�?包可读�?�测试，覆盖 key 对齐、高频菜单中文标题和典型乱码片段黑名单�??
- `docs/admin-backend-blade-permission-final-checklist.md`：记录本轮菜单多语言验证、真�? DB 边界和后续实测要求�??

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\MenuZhCnLanguageReadabilityTest.php
OK (3 tests, 26 assertions)

php -l tests\Feature\MenuZhCnLanguageReadabilityTest.php
No syntax errors detected in tests\Feature\MenuZhCnLanguageReadabilityTest.php

php artisan tinker --execute="echo __('menus.front_dashboard') . PHP_EOL; echo __('menus.front_agent') . PHP_EOL; echo __('menus.admin_system') . PHP_EOL;"
控制�?
代理管理
系统管理
```

### 受真�? DB 影响的验�?

```text
Test-NetConnection 127.0.0.1 -Port 3307
TcpTestSucceeded: False

vendor\bin\phpunit tests\Feature\AdminPermissionPlanTest.php tests\Feature\DefaultAdminAndFrontMenuRoleMigrationTest.php
AdminPermissionPlanTest �? SQLSTATE[HY000] [2002] 目标计算机积极拒绝连接�?�失败�??
DefaultAdminAndFrontMenuRoleMigrationTest 本身此前已单独�?�过；本次组合命令失败来�? AdminPermissionPlanTest �? DatabaseTransactions 连接真实 MySQL�?
```

### 后续待继�?

- 真实 DB `127.0.0.1:3307` 恢复后，�?要重新运�? `vendor\bin\phpunit tests\Feature\AdminPermissionPlanTest.php tests\Feature\DefaultAdminAndFrontMenuRoleMigrationTest.php`�?
- 真实 DB 可用后，�?要登�? agent 和普通客户账号分别请�? `/api/front/navigation/menus`，确认接口返回的 `title` 是可读中文，且菜单集合符�? agent/customer 授权边界�?

## 40. 2026-06-07 MenuService 中文逻辑注释与参数说明修�?
本轮继续推进“所有模块的文件及参数必须有详细中文注释和�?�辑注释”的目标，重点维护前后台菜单统一入口 `app/Services/MenuService.php`。该服务负责�? `permissions` 表读取菜单�?�按角色权限过滤菜单、保留父级菜单容器�?�构造树形数组，并�?�过语言包返回可读菜单标题，是前台代理商、普通客户和后台管理员菜单展示的核心服务�?

本轮先新�? `tests/Feature/MenuServiceCommentReadabilityTest.php`，确认原文件虽然不是运行时乱码，但参数说明仍不够完整；随后补�? `MenuService` 的中文职责说明�?�`$guardType`、`$permissionIds`、`$menus`、`$locale` 参数含义、返回结构说明，以及父级菜单保留和多语言标题来源说明。同时移除未使用�? `App\Models\Menu` �? `Cache` 引用，减少核心服务的无效依赖�?

### 本轮维护文件

- `app/Services/MenuService.php`：补齐菜单服务职责�?�权限过滤�?�多语言标题和参数返回说明�??
- `tests/Feature/MenuServiceCommentReadabilityTest.php`：新增菜单服务注释可读�?�测试，约束核心中文说明和常见乱码片段黑名单�?
- `docs/admin-backend-blade-permission-final-checklist.md`：记录本轮核心服务注释修复和验证结果�?

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\MenuServiceCommentReadabilityTest.php
OK (2 tests, 19 assertions)

vendor\bin\phpunit tests\Feature\MenuZhCnLanguageReadabilityTest.php
OK (3 tests, 26 assertions)

vendor\bin\phpunit tests\Feature\DefaultAdminAndFrontMenuRoleMigrationTest.php
OK (2 tests, 57 assertions)

php -l app\Services\MenuService.php
No syntax errors detected in app\Services\MenuService.php

php -l tests\Feature\MenuServiceCommentReadabilityTest.php
No syntax errors detected in tests\Feature\MenuServiceCommentReadabilityTest.php
```

### 后续待继�?

- `Front\MenuController`、`Admin\MenuController`、`routes/front.php`、`routes/admin.php` 仍属于菜单权限链路，�?要继续分批补齐可读中文参数说明和接口边界说明�?
- 真实 DB `127.0.0.1:3307` 恢复后，仍需重新验证 `MenuService::getUserMenus()` 在真�? `permissions`、`role_permissions`、`user_logins.role_id` 数据下的 agent/customer 菜单返回�?

## 41. 2026-06-07 前后�? MenuController 中文逻辑注释与接口边界修�?
本轮继续推进“所有模块的文件及参数必须有详细中文注释和�?�辑注释”的目标，重点维护菜单权限链路中的两个控制器：`app/Http/Controllers/Front/MenuController.php` �? `app/Http/Controllers/Admin/MenuController.php`。这两个控制器分别负责前�? agent/customer 菜单树返回�?�后台管理员菜单树和按钮权限返回，以及后台菜单权限字�? CRUD，是权限配置�? DB �? Blade/Layui 页面展示的直接接口层�?

本轮先新�? `tests/Feature/MenuControllerCommentReadabilityTest.php`，确认原控制器注释缺少角色权限来源�?�请求参数�?�返回结构�?�安全边界和字段映射说明；随后补齐前台控制器�? `role_permissions`、`permissions.id`、`data` 菜单树的说明，并补齐后台控制器对 `data.menus`、`data.permissions`、`check.permission:admin`、`guard_type`、`slug`、CRUD 参数和唯�? slug 生成逻辑的说明�?�本轮只改注释和无行为说明，不改变接口返回结构和数据库查询�?�辑�?

### 本轮维护文件

- `app/Http/Controllers/Front/MenuController.php`：补齐前台菜单接口职责�?�角色权限来源�?�参数含义和返回结构说明�?
- `app/Http/Controllers/Admin/MenuController.php`：补齐后台菜单树、按钮权限�?�菜单管�? CRUD、字段映射和 slug 唯一性说明�??
- `tests/Feature/MenuControllerCommentReadabilityTest.php`：新增前后台菜单控制器注释可读�?�测试，约束核心中文说明和乱码片段黑名单�?
- `docs/admin-backend-blade-permission-final-checklist.md`：记录本轮菜单控制器注释修复和验证结果�??

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\MenuControllerCommentReadabilityTest.php
OK (3 tests, 37 assertions)

vendor\bin\phpunit tests\Feature\MenuServiceCommentReadabilityTest.php
OK (2 tests, 19 assertions)

vendor\bin\phpunit tests\Feature\MenuZhCnLanguageReadabilityTest.php
OK (3 tests, 26 assertions)

vendor\bin\phpunit tests\Feature\DefaultAdminAndFrontMenuRoleMigrationTest.php
OK (2 tests, 57 assertions)

php -l app\Http\Controllers\Front\MenuController.php
No syntax errors detected in app\Http\Controllers\Front\MenuController.php

php -l app\Http\Controllers\Admin\MenuController.php
No syntax errors detected in app\Http\Controllers\Admin\MenuController.php

php -l tests\Feature\MenuControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\MenuControllerCommentReadabilityTest.php
```

### 后续待继�?

- `routes/front.php` �? `routes/admin.php` 仍是菜单权限接口注册入口，需要继续补齐可读中文路由分组�?�参数和中间件边界说明�??
- 真实 DB `127.0.0.1:3307` 恢复后，�?要实�? `POST /api/front/navigation/menus`、`POST /api/admin/menus`、`POST /api/admin/menuTree` 的真实返回结构和角色过滤效果�?

## 42. 2026-06-07 前后台菜�? API 路由中文注释与中间件边界修复
本轮继续推进“前后台�?有菜单可控�?�和“所有模块文件及参数必须有详细中文注释�?�的目标，重点维�? `routes/front.php` �? `routes/admin.php`。这两个文件是菜单权限接口的注册入口：前台菜单接口决�? agent/customer �? Layui/Blade 菜单树从哪里加载，后台菜单接口决定管理员菜单树�?�按钮权�? slug 和菜单权限字典管理是否处于正确中间件保护下�??

本轮先新�? `tests/Feature/MenuRouteCommentReadabilityTest.php`，确认路由文件虽然语法正确，但缺少可读的路由前缀、中间件、菜单接口用途和权限边界说明；随后补�? `routes/front.php` 顶部说明�? `/navigation/menus`、`/menus` 菜单接口注释，并补齐 `routes/admin.php` 顶部说明、后�? JWT/SSO/check.permission:admin 分组说明、`/menus` 当前管理员菜单接口和 `/menuTree` 菜单管理接口说明。本轮只改注释，不改变任何路�? URI、控制器方法或路由名称�??

### 本轮维护文件

- `routes/front.php`：补�? `api/front` 路由前缀、前台控制器命名空间、JWT/SSO 保护边界，以及前台菜单接口用途说明�??
- `routes/admin.php`：补�? `api/admin` 路由前缀、后台控制器命名空间、JWT/SSO/check.permission:admin 权限边界，以及后台菜单和菜单管理接口用�?�说明�??
- `tests/Feature/MenuRouteCommentReadabilityTest.php`：新增前后台菜单 API 路由注释可读性测试，约束关键中文说明和乱码片段黑名单�?
- `docs/admin-backend-blade-permission-final-checklist.md`：记录本轮路由入口注释修复和验证结果�?

### 权限边界说明

| 路由 | 路由名称 | 中间件边�? | 用�?? |
| --- | --- | --- | --- |
| `POST /api/front/navigation/menus` | `front_api_navigation_menus` | `jwt.auth:user`、`sso:user` | 返回当前前台用户可见�? Layui/Blade 菜单树，用于 agent/customer 两套菜单配置�? |
| `POST /api/front/menus` | `front_api_menus` | `jwt.auth:user`、`sso:user` | 前台菜单兼容接口，复用同�?�? `MenuController@userMenus`�? |
| `POST /api/admin/menus` | `admin_api_menus` | `jwt.auth:admin`、`sso:admin`、`check.permission:admin` | 返回 `data.menus` �? `data.permissions`，供后台 Blade/Layui 渲染菜单和按钮�?? |
| `POST /api/admin/menuTree` | `admin_api_menuTree` | `jwt.auth:admin`、`sso:admin`、`check.permission:admin` | 后台菜单管理接口，读取完整菜单树以维�? `permissions` 表菜单权限字典�?? |

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\MenuRouteCommentReadabilityTest.php
OK (3 tests, 38 assertions)

vendor\bin\phpunit tests\Feature\MenuControllerCommentReadabilityTest.php tests\Feature\MenuServiceCommentReadabilityTest.php
OK (3 tests, 37 assertions)

php -l routes\front.php
No syntax errors detected in routes\front.php

php -l routes\admin.php
No syntax errors detected in routes\admin.php
```

### 后续待继�?

- 真实 DB `127.0.0.1:3307` 恢复后，�?要使用真�? token 实测 `POST /api/front/navigation/menus`、`POST /api/admin/menus`、`POST /api/admin/menuTree`�?
- 继续审计后台 Blade 页面内部按钮权限 `data-permission` 与后�? `permissions.api_route` 是否逐项�?致�??

## 43. 2026-06-07 后台 Blade 按钮权限与迁移声明覆盖测�?
本轮继续推进“后台不同管理员角色拥有不同菜单权限和按钮权限�?�的目标，重点审计后�? Blade 页面中的 `data-permission`。这些标识用于前端根�? `/api/admin/menus` 返回�? `data.permissions` 隐藏无权限按钮，但真正安全边界仍在后�? `check.permission:admin` 中间件和 `permissions.api_route` 配置。本轮先做静态覆盖约束，确保页面上出现的每一个按钮权�? slug 都能在迁移源码里找到对应 `permissions.slug` 声明，避免页面写了一个数据库永远不会有的权限�?

### 本轮维护文件

- `tests/Feature/AdminBladeButtonPermissionCoverageTest.php`：新增后�? Blade 按钮权限覆盖测试，扫�? `resources/admin/layui/**/*.blade.php` 中的 `data-permission`，并确认每个 slug 都在 `database/migrations/**/*.php` 中以 `slug` 形式声明�?
- `docs/admin-backend-blade-permission-final-checklist.md`：记录本轮按钮权限覆盖验证和后续真实 DB 验证边界�?

### 覆盖规则

| 规则 | 说明 |
| --- | --- |
| `data-permission` 必须使用 `admin_` 前缀 | 确保后台按钮权限不会混入前台 guard 命名空间�? |
| 每个按钮权限 slug 必须在迁移中声明 | 确保按钮显隐配置有对�? DB 权限字典来源�? |
| 本测试不连接真实 DB | 当前 3307 MySQL 不可用时仍可做静态权限配置质量约束�?? |

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminBladeButtonPermissionCoverageTest.php
OK (2 tests, 115 assertions)

vendor\bin\phpunit tests\Feature\AdminCrudUiControlsTest.php
OK (9 tests, 78 assertions)

vendor\bin\phpunit tests\Feature\MenuControllerCommentReadabilityTest.php tests\Feature\MenuRouteCommentReadabilityTest.php
OK (3 tests, 37 assertions)

php -l tests\Feature\AdminBladeButtonPermissionCoverageTest.php
No syntax errors detected in tests\Feature\AdminBladeButtonPermissionCoverageTest.php
```

### 后续待继�?

- 当前测试确认“页面按�? slug 有迁移声明�?�，后续仍需继续核对每个按钮 slug 是否拥有正确�? `api_route`，以及对应命名路由是否挂�? `check.permission:admin`�?
- 真实 DB `127.0.0.1:3307` 恢复后，�?要查�? `permissions` �? `role_permissions`，确认迁移声明已经落库并按角色授权�??

## 44. 2026-06-07 后台 Blade 按钮权限 api_route 与中间件覆盖测试
本轮继续推进“后台按钮权限必须由数据表配置驱动，并由后端再次鉴权”的目标。在�? 43 节已确认 Blade 中的 `data-permission` 都能在迁移源码中找到 `permissions.slug` 声明后，本轮继续向后端安全边界推进：确认每个按钮权限 slug 都绑定了非空 `permissions.api_route`，对�? Laravel 命名路由真实存在，并且该路由挂载 `check.permission:admin`�?

本轮新增 `tests/Feature/AdminBladeButtonPermissionRouteCoverageTest.php`。测试从 `resources/admin/layui/**/*.blade.php` 提取�?�? `data-permission`，从 `database/migrations/**/*.php` 提取 `slug => api_route` 映射，再通过 Laravel 路由表校验命名路由与中间件�?�该测试不连接真�? MySQL，因此在 `127.0.0.1:3307` 不可用时仍可验证代码层权限链路�??

### 本轮维护文件

- `tests/Feature/AdminBladeButtonPermissionRouteCoverageTest.php`：新增按钮权�? slug �? `api_route` �? `check.permission:admin` 的覆盖测试�??
- `docs/admin-backend-blade-permission-final-checklist.md`：记录本轮按钮权限后端路由覆盖验证和真实 DB 待验证边界�??

### 覆盖规则

| 规则 | 说明 |
| --- | --- |
| 每个 `data-permission` 必须有非�? `api_route` | 按钮代表可执行动作，不能只做前端显隐而没有后端权限入口�?? |
| `api_route` 必须是已注册 Laravel 命名路由 | 防止权限表配置指向不存在的接口�?? |
| 命名路由必须挂载 `check.permission:admin` | 确保后端接口�? `permissions.api_route` 强制鉴权�? |

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminBladeButtonPermissionRouteCoverageTest.php
OK (1 test, 229 assertions)

vendor\bin\phpunit tests\Feature\AdminBladeButtonPermissionCoverageTest.php
OK (2 tests, 115 assertions)

vendor\bin\phpunit tests\Feature\AdminCrudUiControlsTest.php
OK (9 tests, 78 assertions)

vendor\bin\phpunit tests\Feature\MenuControllerCommentReadabilityTest.php tests\Feature\MenuRouteCommentReadabilityTest.php
OK (3 tests, 37 assertions)

php -l tests\Feature\AdminBladeButtonPermissionRouteCoverageTest.php
No syntax errors detected in tests\Feature\AdminBladeButtonPermissionRouteCoverageTest.php
```

### 后续待继�?

- 当前测试证明代码层的按钮权限、迁移声明�?�命名路由和中间件一致；真实 DB 恢复后仍�?确认这些迁移已执行到 `permissions` 表�??
- 后续可继续对后台 JS 操作事件�? `data-permission` 做一致�?�审计，确认表格操作列刷新后仍会重新应用按钮权限显隐�?

## 45. 2026-06-07 后台 Layui 表格操作列权限刷新覆�?
本轮继续推进“后台不同管理员角色拥有不同菜单权限和按钮权限�?�的目标，重点补�? Layui 表格重载后的前端按钮显隐链路。后台行内操作按钮使�? `data-permission` 绑定 `permissions.slug`，首次页面加载会�? `layout.js` �? `/api/admin/menus` 返回�? `data.permissions` 隐藏无权限按钮；�? Layui 表格在搜索�?�分页�?�审核�?�重载后会重新生成操作列 DOM，如果业�? JS 没有再次调用 `CrmAdminPermissions.refresh()`，行内按钮可能重新显示�??

### 本轮维护文件

- `tests/Feature/AdminTablePermissionRefreshTest.php`：新增静态回归测试，扫描后台 Blade 中含 `data-permission` �? Layui 操作列模板，并要求对应业�? JS 调用 `CrmAdminPermissions.refresh()`�?
- `public/js/admin/layui/withdrawals/index.js`、`deposits/index.js`、`cancel-applies/index.js`、`commissions/index.js`、`roles/index.js`、`users/index.js`、`vouchers/index.js`、`data-scopes/index.js`、`risk/index.js`：补�? `refreshPermissions()` 辅助函数与表�? `done` 回调调用�?

### 逻辑说明

| 规则 | 作用 |
| --- | --- |
| 操作列模板含 `data-permission` | 表示该表格行内按钮受 `permissions.slug` 控制�? |
| 对应 JS 必须调用 `CrmAdminPermissions.refresh()` | 确保搜索、分页�?�审核�?�重载后新生成的行内按钮继续按权限隐藏�?? |
| 后端仍以 `check.permission:admin` 为最终边�? | 前端隐藏只改善体验，真正安全控制仍由权限中间件和 `permissions.api_route` 完成�? |

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminTablePermissionRefreshTest.php
OK (1 test, 2 assertions)

vendor\bin\phpunit tests\Feature\AdminBladeButtonPermissionCoverageTest.php
OK (2 tests, 115 assertions)

vendor\bin\phpunit tests\Feature\AdminBladeButtonPermissionRouteCoverageTest.php
OK (1 test, 229 assertions)

vendor\bin\phpunit tests\Feature\AdminCrudUiControlsTest.php
OK (9 tests, 78 assertions)

php -l tests\Feature\AdminTablePermissionRefreshTest.php
No syntax errors detected in tests\Feature\AdminTablePermissionRefreshTest.php

node --check public\js\admin\layui\withdrawals\index.js
node --check public\js\admin\layui\deposits\index.js
node --check public\js\admin\layui\cancel-applies\index.js
node --check public\js\admin\layui\commissions\index.js
node --check public\js\admin\layui\roles\index.js
node --check public\js\admin\layui\users\index.js
node --check public\js\admin\layui\vouchers\index.js
node --check public\js\admin\layui\data-scopes\index.js
node --check public\js\admin\layui\risk\index.js
以上 node --check 命令�? exit 0�?
```

### 后续待继�?

- 真实 DB `127.0.0.1:3307` 恢复后，仍需要用不同后台管理员角色登录并实测 `/api/admin/menus`、页面按钮显隐和接口二次鉴权�?
- 继续按计划审计后�? Blade 页面布局密度、CSS 现代化和多语�?运行时文案，尤其是仍未被专门测试覆盖的业务模块�??

## 46. 2026-06-07 后台 Blade 业务面板统一现代�?
本轮继续推进“后�? UI 参�?? Vben Admin、Vue Naive Admin、Naive UI Admin、Ant Design Pro、Arco Design Pro，但必须使用 Laravel Blade + JS + CSS 渲染”的目标。此前后台�?�外壳和公共 CSS 已经具备现代工作台结构，但很多业务列表页仍使用裸 `layui-card`，页面内部缺少统�?业务面板语义类�??

本轮将后�? Blade 业务卡片统一补齐�? `layui-card crm-admin-panel`，并在公�? CSS 中新�? `crm-admin-panel` 样式钩子。该类用于承接列表页、表单页、双表格页的统一边框、背景�?�阴影和溢出边界，方便后续继续按现代中后台的信息密度维护页面，�?�不�?要每个模块单独散落样式�??

### 本轮维护文件

- `tests/Feature/AdminBladePagePanelModernizationTest.php`：新增后�? Blade 页面内部布局测试，扫描含 `<table class="layui-hide"` 的业务列表页，要求使�? `crm-admin-panel`�?
- `public/css/admin/style.css`：新�? `crm-admin-panel` 业务面板样式和中文�?�辑注释�?
- `resources/admin/layui/**/*.blade.php`：批量为后台业务卡片补齐 `crm-admin-panel` 类，保留原有 Blade 渲染、表格�?�表单�?�按钮权限和接口逻辑不变�?

### UI 约束说明

| 约束 | 作用 |
| --- | --- |
| �? Layui 表格的后台业务页必须使用 `crm-admin-panel` | 保证列表、筛选�?�表格和操作区都有统�?业务面板语义�? |
| `crm-admin-panel` 在公�? CSS 中集中维�? | 后续 UI 调整可�?�过统一类完成，避免每个 Blade 页面重复写样式�?? |
| 不改变路由�?�接口和权限 slug | 本轮只做布局语义和样式钩子补齐，不改变后台权限与业务数据流�?? |

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminBladePagePanelModernizationTest.php
OK (1 test, 1 assertion)

vendor\bin\phpunit tests\Feature\AdminLayoutUiModernizationTest.php tests\Feature\AdminLayoutShellReadabilityTest.php tests\Feature\AdminCssCommentReadabilityTest.php
OK (2 tests, 33 assertions)

vendor\bin\phpunit tests\Feature\AdminCssCommentReadabilityTest.php
OK (2 tests, 15 assertions)

vendor\bin\phpunit tests\Feature\AdminBladeModuleCoverageTest.php
OK (20 tests, 60 assertions)

vendor\bin\phpunit tests\Feature\AdminCrudUiControlsTest.php
OK (9 tests, 78 assertions)

php -l tests\Feature\AdminBladePagePanelModernizationTest.php
No syntax errors detected in tests\Feature\AdminBladePagePanelModernizationTest.php
```

### 后续待继�?

- 继续逐页审计后台 Blade 页面内部筛�?�区、按钮区、弹窗表单和统计卡片是否有统�?结构类与中文逻辑注释�?
- 真实 DB `127.0.0.1:3307` 恢复后，仍需结合不同管理员角色实测页面菜单�?�按钮显隐�?�数据范围和接口鉴权�?

## 47. 2026-06-07 数据范围页面运行时多语言补齐
本轮继续推进“后端也必须支持多语�?”和“所有页面必须使�? Laravel Blade + JS + CSS 渲染”的目标，重点修复后台数据范围页面的运行时文案�?�该页面的静态表单文案已经�?�过 Blade �? `__('admin.xxx')` 读取 Laravel 语言包，�? Layui 表格列名、状态徽标�?�弹窗标题和数据范围标签�? `public/js/admin/layui/data-scopes/index.js` 动�?�生成，原先仍有硬编码英文，中文界面下会出现英文表头和弹窗标题�??

本轮将数据范�? JS 的运行时文案统一改为 `CrmLang.t('admin.xxx')`，并�? `public/js/common/lang/zh-CN.js` �? `public/js/common/lang/en.js` �? `admin` 段补齐对�? key。这�? Blade 静�?�文案�?�Layui 表格运行时文案和弹窗文案都能跟随当前语言切换�?

### 本轮维护文件

- `tests/Feature/AdminDataScopeRuntimeLocalizationTest.php`：新增数据范围运行时多语�?测试，约�? JS 不能硬编码英文，并要求中英文 common/lang 文件都具备对�? key�?
- `public/js/admin/layui/data-scopes/index.js`：表格列名�?�状态徽标�?�弹窗标题�?�删除确认和范围标签改为 `CrmLang.t` 读取�?
- `public/js/common/lang/zh-CN.js`：补齐数据范围运行时中文 key�?
- `public/js/common/lang/en.js`：补齐数据范围运行时英文 key�?

### 多语�? key 说明

| key | 用�?? |
| --- | --- |
| `admin.role_data_scope_role_name`、`admin.scope_type` | 角色数据范围表格列名�? |
| `admin.agent_ids`、`admin.user_ids` | 指定代理和指定用户范围字段�?? |
| `admin.admin_id`、`admin.admin_name`、`admin.agent_id`、`admin.agent_name` | 管理员代理绑定表格列名�?? |
| `admin.binding_type`、`admin.binding_primary`、`admin.binding_extra` | 代理绑定类型展示�? |
| `admin.scope_all`、`admin.scope_self`、`admin.scope_created`、`admin.scope_agent_tree`、`admin.scope_custom_agents`、`admin.scope_custom_users` | `role_data_scopes.scope_type` 的运行时标签�? |
| `admin.data_scope_saved`、`admin.admin_agent_binding_deleted`、`admin.admin_agent_binding_delete_confirm` | 保存、删除和确认提示�? |
| `admin.role_data_scope_modal_title`、`admin.admin_agent_binding_modal_title` | Layui 弹窗标题�? |

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminDataScopeRuntimeLocalizationTest.php
OK (2 tests, 86 assertions)

node --check public\js\admin\layui\data-scopes\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
以上 node --check 命令�? exit 0�?

vendor\bin\phpunit tests\Feature\AdminLocalizationTest.php tests\Feature\AdminZhCnLanguageReadabilityTest.php
OK (2 tests, 10 assertions)

php -l tests\Feature\AdminDataScopeRuntimeLocalizationTest.php
No syntax errors detected in tests\Feature\AdminDataScopeRuntimeLocalizationTest.php

vendor\bin\phpunit tests\Feature\AdminTablePermissionRefreshTest.php
OK (1 test, 2 assertions)
```

### 真实 DB 验证边界

```text
vendor\bin\phpunit tests\Feature\AdminDataScopeManagementTest.php
ERRORS: 4，均�? SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接�?

vendor\bin\phpunit tests\Feature\AdminDataScopeControllerWiringTest.php
ERRORS: 4，均�? SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接�?
```

以上失败来自真实 MySQL `127.0.0.1:3307` 不可连接，不是本轮运行时多语�?静�?�契约失败�?�数据库恢复后仍�?重跑这两个数据范围测试，并使用真实管理员角色验证页面文案、数据范围过滤和接口鉴权�?

### 后续待继�?

- 继续扫描其他后台 Layui JS 是否仍有会显示给用户的硬编码英文或乱码文案�??
- 继续补齐后台业务模块的中文参数注释，尤其是弹窗表单�?�筛选参数和 JS helper 函数�?

## 48. 2026-06-07 凭证审核图片预览运行时多语言修复
本轮继续推进“后�?/后台必须支持多语�?”的目标，重点审计后台凭证审核页面的运行时图片预览文案�?�`resources/admin/layui/vouchers/index.blade.php` 的静态页面文案已经�?�过 Laravel Blade 语言包渲染，�? `public/js/admin/layui/vouchers/index.js` 动�?�生成图片查看链接和 Layui 预览弹窗标题，原先保留了 `|| 'View'` �? `|| 'Voucher Images'` 英文兜底。中文后台界面下，如果运行时语言包加载正常，不应再落回英文兜底�??

本轮新增专门测试并移除英文兜底，凭证图片链接文案统一读取 `CrmLang.t('common.view')`，弹窗标题统�?读取 `CrmLang.t('front.voucher_images')`。中英文运行时语�?包中这两�? key 已存在，因此本轮不新增重复语�? key�?

### 本轮维护文件

- `tests/Feature/AdminVoucherRuntimeLocalizationTest.php`：新增凭证审核运行时多语�?测试，约束图片预览链接和弹窗标题不能写死英文兜底�?
- `public/js/admin/layui/vouchers/index.js`：移�? `View` �? `Voucher Images` 英文兜底，并补充图片链接文案来源的中文�?�辑注释�?

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminVoucherRuntimeLocalizationTest.php
OK (2 tests, 8 assertions)

node --check public\js\admin\layui\vouchers\index.js
exit 0

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter=voucher_images
OK (1 test, 15 assertions)

vendor\bin\phpunit tests\Feature\AdminTablePermissionRefreshTest.php
OK (1 test, 2 assertions)

vendor\bin\phpunit tests\Feature\AdminBladeButtonPermissionCoverageTest.php
OK (2 tests, 115 assertions)

vendor\bin\phpunit tests\Feature\AdminBladeButtonPermissionRouteCoverageTest.php
OK (1 test, 229 assertions)

php -l tests\Feature\AdminVoucherRuntimeLocalizationTest.php
No syntax errors detected in tests\Feature\AdminVoucherRuntimeLocalizationTest.php
```

### 后续待继�?

- 继续扫描其他后台 Layui JS 中会显示给用户的英文兜底文案，优先处理弹窗标题�?�确认提示�?�状态标签和表格列名�?
- 真实 DB `127.0.0.1:3307` 恢复后，仍需用真实管理员角色打开凭证审核页，验证图片预览文案、按钮权限显隐和审核接口鉴权�?
## 49. 2026-06-07 数据范围 JS 中文注释可读性修�?

本轮继续推进“所有模块的文件及参数必须有详细中文注释和�?�辑注释”的目标，重点修�? `public/js/admin/layui/data-scopes/index.js` 中残留的中文注释乱码。数据范围页面涉及后台角色数据范围�?�管理员代理绑定、Layui 表格重载后的按钮权限刷新等核心权限�?�辑，注释必须能清楚说明参数来源和业务边界，不能出现 `�`、`锟` 等不可读字符�?

本轮只修复注释可读�?�，不改变接口地�?、表格列、提交参数�?�权�? slug、数据范围计算或 Layui 交互逻辑�?

### 本轮维护文件

- `tests/Feature/AdminDataScopeJsCommentReadabilityTest.php`：新增数据范�? JS 中文注释可读性测试，要求关键注释片段存在，并拦截常见乱码片段�?
- `public/js/admin/layui/data-scopes/index.js`：修复角色数据范围弹窗�?�管理员代理绑定弹窗和相关参数说明中的乱码注释�??

### 注释覆盖�?

| 注释片段 | 功能作用 |
| --- | --- |
| `表格列名、状态徽标和弹窗标题都从运行时语�?包读取` | 说明数据范围页面的运行时文案来源，避免后台中文界面出现硬编码英文�? |
| `Layui 表格重载会重新生成操作列按钮` | 说明为什么表�? `done` 后必须再次执�? `CrmAdminPermissions.refresh()`�? |
| `row.data_scope 是后�? role_data_scopes 关联配置` | 说明角色数据范围弹窗参数来自后端角色关联数据�? |
| `新增时传入空字段对象` | 说明管理员代理绑定弹窗新增场景的参数边界�? |
| `role_data_scopes.scope_type` | 说明范围标签值与后端数据范围表字段对应�?? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminDataScopeJsCommentReadabilityTest.php
RED: 2 failures
- 缺少 `row.data_scope 是后�? role_data_scopes 关联配置`
- 仍包含乱码片�? `�`

php -l tests\Feature\AdminDataScopeJsCommentReadabilityTest.php
No syntax errors detected in tests\Feature\AdminDataScopeJsCommentReadabilityTest.php
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminDataScopeJsCommentReadabilityTest.php
OK (2 tests, 17 assertions)

node --check public\js\admin\layui\data-scopes\index.js
exit 0

vendor\bin\phpunit tests\Feature\AdminDataScopeRuntimeLocalizationTest.php
OK (2 tests, 86 assertions)

vendor\bin\phpunit tests\Feature\AdminTablePermissionRefreshTest.php
OK (1 test, 2 assertions)
```

### 真实 DB 验证边界

本轮是静态注释可读�?�修复，不需要连接数据库。真�? DB `127.0.0.1:3307` 当前仍需恢复后再继续执行数据范围管理相关的数据库集成测试，并用不同后台管理员角色实测菜单、按钮�?�数据范围和接口鉴权�?
## 50. 2026-06-07 后台公共运行时提示多语言兜底修复

本轮继续推进“后�?/后台必须支持多语�?”和“所有页面必须使�? Laravel Blade + JS + CSS 渲染”的目标，重点处理后台公�? Layui 脚本中的运行时提示�?�`public/js/admin/layui/common.js` 负责旧版后台 AJAX 登录过期处理，`public/js/admin/layui/layout.js` 负责后台总布�?、主题切换�?�菜单和按钮权限刷新；这两个文件的提示会在所有后台页面扩散，因此不能保留不可切换的英文兜底�??

本轮移除 `Session expired` �? `Theme applied` 英文兜底，登录过期提示统�?读取旧版后台 i18n �? `login_expired`，主题切换提示统�?读取 `CrmLang.t('common.success')`。本轮只调整运行时提示文案来源，不改变登录跳转�?�菜单加载�?�主题切换�?�权限刷新或接口调用逻辑�?

### 本轮维护文件

- `tests/Feature/AdminCommonRuntimeLocalizationTest.php`：新增后台公共运行时多语�?测试，约束公共脚本不能保留英文兜底，并确�? `login_expired` 存在于中英文 admin i18n 文件�?
- `public/js/admin/layui/common.js`：移除登录过期提示的 `Session expired` 英文兜底�?
- `public/js/admin/layui/layout.js`：移除主题切换提示的 `Theme applied` 英文兜底�?

### 多语�?边界说明

| 位置 | 文案来源 | 功能作用 |
| --- | --- | --- |
| `common.js` 登录过期处理 | `CRM.t('login_expired')` | 处理 `4001`、`4002`、`4003`、`4004` 后提示用户重新登录�?? |
| `layout.js` 主题切换提示 | `CrmLang.t('common.success')` | 后台总布�?切换皮肤后显示统�?成功提示�? |
| `public/js/admin/i18n/zh-CN.js` | `login_expired` | 旧版 admin common 模块读取的中文登录过期提示�?? |
| `public/js/admin/i18n/en.js` | `login_expired` | 旧版 admin common 模块读取的英文登录过期提示�?? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminCommonRuntimeLocalizationTest.php
RED 1: 后台登录过期提示仍包�? `Session expired`

vendor\bin\phpunit tests\Feature\AdminCommonRuntimeLocalizationTest.php
RED 2: 后台主题切换提示仍包�? `Theme applied`

php -l tests\Feature\AdminCommonRuntimeLocalizationTest.php
No syntax errors detected in tests\Feature\AdminCommonRuntimeLocalizationTest.php
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminCommonRuntimeLocalizationTest.php
OK (2 tests, 6 assertions)

node --check public\js\admin\layui\common.js
exit 0

node --check public\js\admin\layui\layout.js
exit 0

vendor\bin\phpunit tests\Feature\AdminLayoutUiModernizationTest.php tests\Feature\AdminLayoutShellReadabilityTest.php
OK (2 tests, 33 assertions)
```

### 后续待继�?

- 继续扫描后台公共 JS 与业�? JS 中其他用户可见英文兜底，尤其是弹窗标题�?�确认提示�?�错误提示和动�?�表格列名�??
- `public/js/admin/layui/common.js` �? `layout.js` 仍含历史编码注释问题，后续应在更小范围内继续用测试驱动修复可读中文注释，避免�?次�?�大改影响旧后台脚本�?
## 51. 2026-06-07 后台菜单管理 JS 参数注释可读性修�?

本轮继续推进“前后台�?有菜单可控�?�和“所有模块的文件及参数必须有详细中文注释”的目标，重点维�? `public/js/admin/layui/menus/index.js`。后台菜单管理页通过 `POST /api/admin/menuTree` 读取 `permissions` 表中的菜单权限字典，再�?�过新增、编辑�?�删除接口维护菜单节点；它是后台菜单、页面入口和按钮权限配置闭环中的核心页面�?

本轮只修�? `showModal(data)` 附近的中文�?�辑注释，明确说�? tree 节点来自 `permissions` 表�?�弹窗表单回�? `route`、`icon`、`name`，以�? `guard_type` 用于区分 `admin/front` 菜单命名空间。不改变菜单树加载�?�表单字段�?�接口地�?、权�? slug �? Layui 交互逻辑�?

### 本轮维护文件

- `tests/Feature/AdminMenuJsCommentReadabilityTest.php`：新增菜单管�? JS 注释可读性测试，约束菜单弹窗参数说明和乱码片段黑名单�?
- `public/js/admin/layui/menus/index.js`：修复菜单弹窗参数注释，补充 `guard_type` 命名空间边界说明�?

### 注释覆盖�?

| 注释片段 | 功能作用 |
| --- | --- |
| `tree 节点来自 permissions 表` | 说明菜单管理页维护的是权限表中的菜单字典，�?�不是独立静态菜单�?? |
| `弹窗表单只暴露常用菜单字段` | 说明弹窗表单的字段边界，避免把权限表�?有字段直接暴露给页面�? |
| `route、icon、name` | 说明菜单弹窗提交后会回写的核心菜单展示字段�?? |
| `guard_type 用于区分 admin/front 菜单命名空间` | 说明前后台菜单权限不能混用，符合前后台菜单均由数据表配置控制的方案�?? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminMenuJsCommentReadabilityTest.php
RED: 1 failure
- 缺少 `route、icon、name` 可读中文参数注释

php -l tests\Feature\AdminMenuJsCommentReadabilityTest.php
No syntax errors detected in tests\Feature\AdminMenuJsCommentReadabilityTest.php
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminMenuJsCommentReadabilityTest.php
OK (2 tests, 13 assertions)

node --check public\js\admin\layui\menus\index.js
exit 0

vendor\bin\phpunit tests\Feature\AdminBladeButtonPermissionCoverageTest.php tests\Feature\AdminBladeButtonPermissionRouteCoverageTest.php
OK (2 tests, 115 assertions)

vendor\bin\phpunit tests\Feature\AdminBladeButtonPermissionRouteCoverageTest.php
OK (1 test, 229 assertions)

vendor\bin\phpunit tests\Feature\MenuRouteCommentReadabilityTest.php tests\Feature\MenuControllerCommentReadabilityTest.php tests\Feature\MenuServiceCommentReadabilityTest.php
OK (3 tests, 38 assertions)
```

### 验证边界

本轮是静态注释可读�?�修复，不连接真实数据库。真�? DB `127.0.0.1:3307` 恢复后，仍需要�?�过后台菜单管理页实测新增�?�编辑�?�删除菜单节点，并确�? `permissions.guard_type`、`route`、`api_route` 与角色授权数据真实写入后能影响菜单显示和接口鉴权�?
## 52. 2026-06-07 后台公共 common.js 中文注释 UTF-8 修复

本轮继续推进“所有模块的文件及参数必须有详细中文注释和�?�辑注释”的目标，重点维�? `public/js/admin/layui/common.js`。该文件是旧版后�? Layui 页面共用模块，负责路由生成�?�Token 读写、AJAX 请求封装、登录过期处理和旧版 admin i18n 语言包加载；如果注释继续乱码，后续维护登录页、旧后台页面和公共请求�?�辑时很容易误解参数边界�?

本轮�? `common.js` 重写�? UTF-8 可读中文注释，保留原�? `CRM.t`、`getToken`、`setToken`、`removeToken`、`route`、`ajax`、`applyTranslations`、`switchLang`、`getLang`、`initLang` 接口。业务�?�辑保持不变：登录过期响应码仍会清理 Token 并跳转后台登录页，旧页面仍从 `public/js/admin/i18n/{lang}.js` 加载语言包�??

### 本轮维护文件

- `tests/Feature/AdminCommonJsCommentReadabilityTest.php`：新增后台公�? JS 中文注释可读性测试，约束核心注释片段和乱码黑名单�?
- `public/js/admin/layui/common.js`：重写中文注释，说明路由、Token、AJAX、登录过期�?�data-translate 和旧�? i18n 加载逻辑�?

### 注释覆盖�?

| 注释片段 | 功能作用 |
| --- | --- |
| `后台 Layui 公共模块` | 说明文件是旧版后�? Layui 公共模块，不是单�?页面脚本�? |
| `通过 PHP 导出�? Laravel 路由名称生成 URL` | 说明 `routeUrl(name, params, fallback)` 的参数边界�?? |
| `admin_token 是布�?�? CrmAjax 使用的统�?键名` | 说明新旧 Token 键名兼容关系�? |
| `登录过期响应码会清理 Token 并跳回后台登录页` | 说明 `4001`、`4002`、`4003`、`4004` 的公共处理边界�?? |
| `�? data-translate 属�?�应用旧版后台语�?包` | 说明旧版后台页面的运行时多语�?替换入口�? |
| `�? public/js/admin/i18n 加载旧版后台语言包` | 说明旧版 admin i18n 文件来源�? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminCommonJsCommentReadabilityTest.php
RED: 1 failure
- 缺少 `后台 Layui 公共模块` 可读中文注释，common.js 仍显示历史乱码注�?

php -l tests\Feature\AdminCommonJsCommentReadabilityTest.php
No syntax errors detected in tests\Feature\AdminCommonJsCommentReadabilityTest.php
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminCommonJsCommentReadabilityTest.php
OK (2 tests, 15 assertions)

node --check public\js\admin\layui\common.js
exit 0

vendor\bin\phpunit tests\Feature\AdminCommonRuntimeLocalizationTest.php
OK (2 tests, 6 assertions)

node --check public\js\admin\layui\auth\login.js
exit 0

vendor\bin\phpunit tests\Feature\AdminLayoutUiModernizationTest.php tests\Feature\AdminLayoutShellReadabilityTest.php
OK (2 tests, 33 assertions)

php -l tests\Feature\AdminCommonJsCommentReadabilityTest.php
No syntax errors detected in tests\Feature\AdminCommonJsCommentReadabilityTest.php
```

### 后续待继�?

- 继续分批修复 `public/js/admin/layui/layout.js` 等后台公共脚本中的历史乱码注释�??
- 真实 DB `127.0.0.1:3307` 恢复后，仍需要用真实后台账号验证登录过期跳转、旧�? i18n 切换和后台菜单加载流程�??
## 53. 2026-06-07 后台布局 layout.js 权限与菜单注�? UTF-8 修复

本轮继续推进“后�? UI 必须使用 Laravel Blade + JS + CSS 渲染”�?�前后台�?有菜单可控�?�和“所有模块文件及参数必须有详细中文注释�?�的目标，重点维�? `public/js/admin/layui/layout.js`。该文件是后�? Blade/Layui 外壳的运行时核心，负�? `/api/admin/menus` 菜单加载、`permissions.slug` 按钮显隐、语�?切换、主题切换�?�侧边栏交互和�??出登录�??

本轮�? `layout.js` 重写�? UTF-8 可读中文注释，明确前端按钮显隐只负责体验，真正安全边界仍�? `check.permission:admin` 中间件与 `permissions.api_route`。业务�?�辑保持不变：仍�? `/api/admin/menus` 读取菜单树和按钮权限，仍缓存 `crm_admin_permissions`，仍通过 `CrmAdminPermissions.refresh()` �? `MutationObserver` 处理 Layui 表格异步工具栏�??

### 本轮维护文件

- `tests/Feature/AdminLayoutJsCommentReadabilityTest.php`：新增后台布�? JS 中文注释可读性测试，约束权限边界、菜单权限缓存�?�按钮显隐和 DOM 监听说明�?
- `public/js/admin/layui/layout.js`：重写布�?外壳中文注释，保留菜单�?�权限�?�主题�?�侧栏和�?出登录�?�辑不变�?

### 注释覆盖�?

| 注释片段 | 功能作用 |
| --- | --- |
| `后台布局壳层的接口和跳转都从 PHP 注入�? Laravel 路由清单读取` | 说明 `routeUrl` 的路由来源，避免后台路径硬编码�?? |
| `后台按钮权限控制器只负责前端显示体验` | 明确 `CrmAdminPermissions` 不是安全边界�? |
| `真正安全边界仍是 check.permission:admin 中间件` | 对齐计划中的后端二次鉴权要求�? |
| `slug 对应 permissions.slug` | 说明按钮 `data-permission` 与权限表字段的对应关系�?? |
| `菜单接口返回后会覆盖该缓存` | 说明 `crm_admin_permissions` 只是减少闪烁的临时缓存�?? |
| `接口权限必须继续依赖后端中间件校验` | 防止后续误把隐藏按钮当成接口鉴权�? |
| `Layui table 工具栏由模板异步渲染` | 说明为什么需要监�? DOM 变化后重新应用按钮权限�?? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminLayoutJsCommentReadabilityTest.php
RED: 1 failure
- 缺少 `后台布局壳层的接口和跳转都从 PHP 注入�? Laravel 路由清单读取` 可读中文注释，layout.js 仍显示历史乱码注�?

php -l tests\Feature\AdminLayoutJsCommentReadabilityTest.php
No syntax errors detected in tests\Feature\AdminLayoutJsCommentReadabilityTest.php
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminLayoutJsCommentReadabilityTest.php
OK (2 tests, 18 assertions)

node --check public\js\admin\layui\layout.js
exit 0

vendor\bin\phpunit tests\Feature\AdminButtonPermissionVisibilityTest.php tests\Feature\AdminTablePermissionRefreshTest.php
OK (2 tests, 36 assertions)

vendor\bin\phpunit tests\Feature\AdminCommonRuntimeLocalizationTest.php tests\Feature\AdminLayoutUiModernizationTest.php tests\Feature\AdminLayoutShellReadabilityTest.php
OK (2 tests, 6 assertions)

vendor\bin\phpunit tests\Feature\AdminBladeButtonPermissionCoverageTest.php tests\Feature\AdminBladeButtonPermissionRouteCoverageTest.php
OK (2 tests, 115 assertions)

php -l tests\Feature\AdminLayoutJsCommentReadabilityTest.php
No syntax errors detected in tests\Feature\AdminLayoutJsCommentReadabilityTest.php
```

### 后续待继�?

- 继续按模块扫描后台业�? JS 中的历史乱码注释和用户可见英文兜底文案�??
- 真实 DB `127.0.0.1:3307` 恢复后，仍需使用不同后台管理员角色实�? `/api/admin/menus`、侧边菜单�?�按钮显隐�?�表格重载后的权限刷新和接口二次鉴权�?

## 54. 2026-06-07 VoucherInfo 模型中文注释�? UTF-8 编码修复

本轮继续推进“所有模块的文件及参数必须有详细中文注释和�?�辑注释”的目标，重点修�? `app/Models/VoucherInfo.php`。该模型�? `voucher_infos` 表的 Eloquent 映射，服务于前台凭证上传和后台凭证审核链路；原文件存�? UTF-16 空字节和历史中文乱码，导致源码注释不可读，也不利于后续维护凭证图片�?�用户关联和审核逻辑�?

本轮只修复编码和注释，不改变业务行为：`protected $table = 'voucher_infos'` 保持不变，`user()` 仍然通过 `belongsTo(UserInfo::class, 'user_id', 'user_id')` 关联前台用户资料�?

### 本轮维护文件

- `tests/Feature/VoucherInfoCommentReadabilityTest.php`：新增模型注释可读�?�测试，约束 UTF-8 编码、中文职责说明�?�表名说明�?�`$table` 参数含义、`user_id` �? `images` 字段含义、`user()` 关联说明�?
- `app/Models/VoucherInfo.php`：重写为 UTF-8 文件，补齐凭证信息模型职责�?�`voucher_infos` 表名、`user_id`、`images`、`$table` �? `user()` 关联参数的中文�?�辑注释�?

### 注释覆盖�?

| 注释片段 | 功能作用 |
| --- | --- |
| `凭证信息模型 | Voucher Info Model` | 明确模型职责和英文辅助名称�?? |
| `管理用户上传的交易凭证�?�认证凭证或后台审核凭证` | 说明该模型服务的业务范围�? |
| `数据表名称：voucher_infos 表` | 明确 Eloquent 映射的数据表来源�? |
| `$table：当前模型映射的数据表名称` | 说明模型参数 `$table` 的含义和作用�? |
| `user_id 表示上传凭证的前台用�? ID` | 说明凭证与前台用户的业务外键�? |
| `images 存储凭证图片路径�? JSON 图片列表` | 说明凭证图片字段的存储边界�?? |
| `user() 关联上传凭证的前台用户资料` | 说明模型关联方法的业务意义�?? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\VoucherInfoCommentReadabilityTest.php
RED: 2 failures
- 缺少 `凭证信息模型 | Voucher Info Model` 可读中文逻辑注释�?
- 文件包含 UTF-16 空字�? `\0`，证明原文件存在错误编码片段�?
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\VoucherInfoCommentReadabilityTest.php
OK (2 tests, 14 assertions)

php -l app\Models\VoucherInfo.php
No syntax errors detected in app\Models\VoucherInfo.php

php -l tests\Feature\VoucherInfoCommentReadabilityTest.php
No syntax errors detected in tests\Feature\VoucherInfoCommentReadabilityTest.php
```

### 验证边界

本轮为模型源码编码和中文注释修复，不连接真实数据库，也不修改凭证审核、图片解析或用户关联查询逻辑。真�? DB `127.0.0.1:3307` 恢复后，仍需结合后台凭证审核页面和前台凭证上传接口做端到端数据验证�??

## 55. 2026-06-07 后台登录 login.js 中文注释�? UTF-8 编码修复

本轮继续推进后台 Blade + Layui 登录入口的可维护性，重点修复 `public/js/admin/layui/auth/login.js`。该脚本负责后台管理员登录表单提交�?�`/api/admin/login` 请求、JWT Token 缓存、登录成功跳转和登录页语�?切换；原文件存在�? UTF-8 字节和历史乱码注释，影响后续排查默认超级管理员登录�?�后台菜单加载和多语�?切换问题�?

本轮只修复编码和中文逻辑注释，不改变登录业务行为：仍然提�? `username` �? `password`，仍然在 `res.code === 1000` 时保�? `res.data.access_token`，仍然跳�? `CRM.route('admin_page_dashboard')`，仍然�?�过 `CRM.switchLang(lang)` 切换旧版 admin i18n�?

### 本轮维护文件

- `tests/Feature/AdminLoginJsCommentReadabilityTest.php`：新增后台登�? JS 注释可读性测试，约束登录脚本职责、`username`、`password`、`access_token`、`admin_page_dashboard` 和语�?切换说明�?
- `public/js/admin/layui/auth/login.js`：重写为 UTF-8 文件，补齐后台登录参数�?�Token 保存、登录成功跳转和语言切换的中文�?�辑注释�?

### 注释覆盖�?

| 注释片段 | 功能作用 |
| --- | --- |
| `后台登录页脚本` | 明确文件是后台登录页入口脚本�? |
| `username 表示后台管理员登录名` | 对齐 `AuthController@login` 的请求参数�?? |
| `password 表示后台管理员登录密码` | 说明登录密码参数边界�? |
| `access_token 是后台登录接口返回的 JWT` | 说明 Token 来源和后续后台接口复用关系�?? |
| `admin_page_dashboard 是登录成功后的后台首页路由` | 说明登录成功跳转依赖后端命名路由�? |
| `切换后台登录页语�?` | 说明登录页运行时多语�?入口�? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminLoginJsCommentReadabilityTest.php
RED: 1 failure
- 缺少 `后台登录页脚本` 可读中文逻辑注释，原脚本仍保留历史乱码注释�??
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminLoginJsCommentReadabilityTest.php
OK (2 tests, 12 assertions)

node --check public\js\admin\layui\auth\login.js
exit 0

php -l tests\Feature\AdminLoginJsCommentReadabilityTest.php
No syntax errors detected in tests\Feature\AdminLoginJsCommentReadabilityTest.php

vendor\bin\phpunit tests\Feature\AdminCommonRuntimeLocalizationTest.php
OK (2 tests, 6 assertions)
```

### 验证边界

本轮未连接真实数据库，因此没有验�? `superadmin / Admin@123456` 的真实登录响应�?�真�? DB `127.0.0.1:3307` 恢复后，仍需通过 `/admin/login` 页面实测登录、Token 写入、跳�? `/admin/dashboard` 和后台菜单加载�??

## 56. 2026-06-07 后台管理员账�? admins/index.js 中文注释与权限边界修�?

本轮继续推进后台 Blade + Layui 页面源码可维护�?�，重点修复 `public/js/admin/layui/admins/index.js`。该脚本负责后台管理员账号列表�?�新增�?�编辑�?�删除�?�编辑时密码留空不更新，以及 Layui 表格重载后的按钮权限刷新；管理员账号属于高敏后台资源，页面入口显隐必须继续按 `permissions.slug` 控制，接口安全边界仍由后�? `check.permission:admin` 中间件保证�??

本轮只修复编码和中文逻辑注释，不改变业务行为：仍然读�? `/api/admin/adminList`，仍然调�? `/api/admin/createAdmin`、`/api/admin/updateAdmin/{id}`、`/api/admin/deleteAdmin/{id}`，仍然在编辑管理员且 `password` 为空时删除该字段，避免覆盖旧密码�?

### 本轮维护文件

- `tests/Feature/AdminAdminsJsCommentReadabilityTest.php`：新增后台管理员账号 JS 注释可读性测试，约束账号安全边界、`username`、`password`、`id`、`permissions.slug` 和权限刷新说明�??
- `public/js/admin/layui/admins/index.js`：重写为 UTF-8 文件，补齐管理员账号列表、表单弹窗参数�?�密码留空边界和 `CrmAdminPermissions.refresh()` 权限刷新注释�?

### 注释覆盖�?

| 注释片段 | 功能作用 |
| --- | --- |
| `管理员账号列表` | 明确脚本维护后台管理员账号表格�?? |
| `管理员账号属于高敏后台资源` | 标注该模块属于敏感后台资源�?? |
| `username 表示管理员登录名` | 对齐后台登录读取�? `admins.username`�? |
| `password 留空表示编辑时不修改旧密码` | 防止编辑时误提交空密码覆盖旧密码�? |
| `id 为空表示新增管理员` | 说明新增/编辑分支判断依据�? |
| `重新应用按钮权限` | 说明表格重载后需要重新处理按钮显隐�?? |
| `permissions.slug` | 说明前端按钮权限与权限表 slug 的对应关系�?? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminAdminsJsCommentReadabilityTest.php
RED: 1 failure
- 缺少 `管理员账号属于高敏后台资源` 可读中文逻辑注释，原脚本仍保留历史乱码注释�??
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminAdminsJsCommentReadabilityTest.php
OK (2 tests, 15 assertions)

node --check public\js\admin\layui\admins\index.js
exit 0

php -l tests\Feature\AdminAdminsJsCommentReadabilityTest.php
No syntax errors detected in tests\Feature\AdminAdminsJsCommentReadabilityTest.php

vendor\bin\phpunit tests\Feature\AdminBladeModuleCoverageTest.php
OK (20 tests, 60 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_admin_layui_scripts_use_hardcoded_api_urls_for_backend_requests
OK (1 test, 1 assertion)
```

### 验证边界

本轮未连接真实数据库，因此没有验证管理员账号新增、编辑�?�删除接口的真实写入结果。真�? DB `127.0.0.1:3307` 恢复后，仍需使用超级管理员登录后台，进入 `/admin/admins` 页面实测账号列表、新增管理员、编辑管理员、密码留空不更新、删除管理员和按钮权限显隐�??

## 57. 2026-06-07 后台权限字典 permissions/index.js 中文注释与权限树边界修复

本轮继续推进后台权限配置链路的可维护性，重点修复 `public/js/admin/layui/permissions/index.js`。该脚本负责读取 `/api/admin/permissionTree`，按 `guard_type=admin` 渲染后台权限树，用于核对 `permissions` 表中的后台菜单�?�按钮和接口权限字典是否完整；该页面只做权限树预览，角色授权仍在角色模块通过 `assignPermissions` 完成�?

本轮只修复编码和中文逻辑注释，不改变业务行为：仍然请�? `/api/admin/permissionTree`，仍然传�? `guard_type: 'admin'`，仍然用 Layui `tree.render` 渲染 `#permissionTree`，仍然�?�过 `normalizeTree` 把后端节点转换为 Layui tree �?�?字段�?

### 本轮维护文件

- `tests/Feature/AdminPermissionsJsCommentReadabilityTest.php`：新增权限字�? JS 注释可读性测试，约束权限树来源�?�`guard_type` 参数含义、权限预览边界�?�角色授权边界和 `normalizeTree` 参数说明�?
- `public/js/admin/layui/permissions/index.js`：重写为 UTF-8 文件，补齐权限树加载、`permissions` 表来源�?�前后台守卫隔离、角色授权归属和树节点字段转换的中文逻辑注释�?

### 注释覆盖�?

| 注释片段 | 功能作用 |
| --- | --- |
| `加载后台权限树` | 明确页面读取后台权限字典�? |
| `guard_type 表示权限�?属守卫` | 说明请求参数用于区分 admin/front 权限�? |
| `permissions 表中的后台菜单�?�按钮和接口权限字典` | 对齐“鉴权数据必须从数据表配置得到�?�的目标�? |
| `当前页面只做权限树预览` | 明确该页面不直接保存角色授权�? |
| `角色授权在角色模块�?�过 assignPermissions 完成` | 说明授权写入入口归属角色模块�? |
| `normalizeTree 将后端权限节点转换为 Layui tree �?要的字段` | 说明树形数据转换函数的职责�?? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminPermissionsJsCommentReadabilityTest.php
RED: 1 failure
- 缺少 `guard_type 表示权限�?属守卫` 可读中文逻辑注释，原脚本仍保留历史乱码注释�??
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminPermissionsJsCommentReadabilityTest.php
OK (2 tests, 13 assertions)

node --check public\js\admin\layui\permissions\index.js
exit 0

php -l tests\Feature\AdminPermissionsJsCommentReadabilityTest.php
No syntax errors detected in tests\Feature\AdminPermissionsJsCommentReadabilityTest.php

vendor\bin\phpunit tests\Feature\AdminBladeModuleCoverageTest.php
OK (20 tests, 60 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_admin_layui_scripts_use_hardcoded_api_urls_for_backend_requests
OK (1 test, 1 assertion)
```

### 验证边界

本轮未连接真实数据库，因此没有验�? `/api/admin/permissionTree` 的真实返回数据�?�真�? DB `127.0.0.1:3307` 恢复后，仍需使用超级管理员进�? `/admin/permissions` 页面，确认权限树能读�? `permissions.guard_type=admin` 的菜单�?�按钮和接口权限配置，并确认角色模块�? `assignPermissions` 能把授权写入 `role_permissions`�?

## 58. 2026-06-07 后台代理等级 agent-levels/index.js 中文注释与佣金字段边界修�?

本轮继续推进后台配置页面源码可维护�?�，重点修复 `public/js/admin/layui/agent-levels/index.js`。该脚本负责代理等级列表、新增�?�编辑�?�删除和佣金比例字段维护；代理等级配置会影响前台代理体系、等级确认和返佣计算，因此页面注释必须明确真实字段来源�?�表单参数含义和权限刷新边界�?

本轮只修复编码和中文逻辑注释，不改变业务行为：仍然读�? `/api/admin/agentLevelList`，仍然调�? `/api/admin/createAgentLevel`、`/api/admin/updateAgentLevel2/{id}`、`/api/admin/deleteAgentLevel/{id}`，仍然使�? `level_code`/`level` 兼容旧表单字段，仍然在表格重载后调用 `CrmAdminPermissions.refresh()`�?

### 本轮维护文件

- `tests/Feature/AdminAgentLevelsJsCommentReadabilityTest.php`：新增代理等�? JS 注释可读性测试，约束 `level_code`、`max_commission`、`min_commission`、`user_commission`、新�?/编辑分支�? `permissions.slug` 权限刷新说明�?
- `public/js/admin/layui/agent-levels/index.js`：重写为 UTF-8 文件，补齐代理等级字段�?�佣金参数�?�表单兼容字段和按钮权限刷新边界的中文�?�辑注释�?

### 注释覆盖�?

| 注释片段 | 功能作用 |
| --- | --- |
| `代理等级参数由后端模型定义` | 明确页面字段来源于后端真实数据结构�?? |
| `level_code 表示等级编码` | 说明等级编码与旧表单 `level` 字段兼容关系�? |
| `max_commission 表示该等级允许的�?大佣金比例` | 说明�?大佣金字段含义�?? |
| `min_commission 表示该等级允许的�?小佣金比例` | 说明�?小佣金字段含义�?? |
| `user_commission 表示客户侧佣金比例` | 说明客户侧佣金字段含义及旧字段兜底关系�?? |
| `id 为空表示新增代理等级` | 说明新增/编辑接口分支依据�? |
| `重新应用按钮权限` | 说明表格重载后必须刷新按钮显隐�?? |
| `permissions.slug` | 说明按钮显隐来自权限�? slug�? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminAgentLevelsJsCommentReadabilityTest.php
RED: 1 failure
- 缺少 `level_code 表示等级编码` 可读中文逻辑注释，原脚本仍保留历史乱码注释�??
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminAgentLevelsJsCommentReadabilityTest.php
OK (2 tests, 16 assertions)

node --check public\js\admin\layui\agent-levels\index.js
exit 0

php -l tests\Feature\AdminAgentLevelsJsCommentReadabilityTest.php
No syntax errors detected in tests\Feature\AdminAgentLevelsJsCommentReadabilityTest.php

vendor\bin\phpunit tests\Feature\AdminCrudUiControlsTest.php --filter test_agent_level_page_contains_crud_controls
OK (1 test, 9 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_admin_layui_scripts_use_hardcoded_api_urls_for_backend_requests
OK (1 test, 1 assertion)

vendor\bin\phpunit tests\Feature\AdminBladeModuleCoverageTest.php
OK (20 tests, 60 assertions)
```

### 验证边界

本轮未连接真实数据库，因此没有验证代理等级新增�?�编辑�?�删除接口的真实写入结果。真�? DB `127.0.0.1:3307` 恢复后，仍需使用超级管理员进�? `/admin/agent-levels` 页面，实测代理等级列表�?�创建等级�?�修改佣金比例�?�删除等级，以及表格重载后的按钮权限显隐�?

## 59. 2026-06-07 后台支付通道 channels/index.js 中文注释与扩展配置边界修�?

本轮继续推进后台配置页面源码可维护�?�，重点修复 `public/js/admin/layui/channels/index.js`。该脚本负责支付通道列表、状态筛选�?�新增�?�编辑�?�删除和通道扩展配置格式化；支付通道会影响前台入金�?�出金和支付渠道展示，因此必须明确�?�道编码、汇率�?�启用状态和扩展 JSON 配置的含义�??

本轮只修复编码和中文逻辑注释，不改变业务行为：仍然读�? `/api/admin/channelList`，仍然调�? `/api/admin/createChannel`、`/api/admin/updateChannel/{id}`、`/api/admin/deleteChannel/{id}`，仍然用 `normalizeConfig()` 防止对象配置直接显示�? `[object Object]`，仍然在表格重载后调�? `CrmAdminPermissions.refresh()`�?

### 本轮维护文件

- `tests/Feature/AdminChannelsJsCommentReadabilityTest.php`：新增支付�?�道 JS 注释可读性测试，约束 `status`、`channel_code`、`exchange_rate`、`is_enabled`、`config`、新�?/编辑分支、`normalizeConfig` �? `permissions.slug` 权限刷新说明�?
- `public/js/admin/layui/channels/index.js`：重写为 UTF-8 文件，补齐支付�?�道字段、启用状态筛选�?�扩展配置格式化和按钮权限刷新边界的中文逻辑注释�?

### 注释覆盖�?

| 注释片段 | 功能作用 |
| --- | --- |
| `status 表示支付通道启用状�?�筛选` | 说明列表筛�?�参数含义�?? |
| `channel_code 表示支付通道编码` | 说明后端识别支付网关或�?�道实现的关键字段�?? |
| `exchange_rate 表示该�?�道使用的汇率` | 说明入金/出金展示和换算读取的配置�? |
| `is_enabled 表示通道是否启用` | 说明通道启停状�?�字段�?? |
| `config 表示通道扩展配置` | 说明商户号�?�网关参数和回调配置�? JSON 文本边界�? |
| `id 为空表示新增支付通道` | 说明新增/编辑接口分支依据�? |
| `normalizeConfig 将�?�道扩展配置转换�? textarea 文本` | 说明对象配置格式化目的�?? |
| `重新应用按钮权限` | 说明表格重载后必须刷新按钮显隐�?? |
| `permissions.slug` | 说明按钮显隐来自权限�? slug�? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminChannelsJsCommentReadabilityTest.php
RED: 1 failure
- 缺少 `status 表示支付通道启用状�?�筛选` 可读中文逻辑注释，原脚本仍保留历史乱码注释�??
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminChannelsJsCommentReadabilityTest.php
OK (2 tests, 17 assertions)

node --check public\js\admin\layui\channels\index.js
exit 0

php -l tests\Feature\AdminChannelsJsCommentReadabilityTest.php
No syntax errors detected in tests\Feature\AdminChannelsJsCommentReadabilityTest.php

vendor\bin\phpunit tests\Feature\AdminCrudUiControlsTest.php --filter test_channel_page_contains_crud_controls
OK (1 test, 9 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_admin_layui_scripts_use_hardcoded_api_urls_for_backend_requests
OK (1 test, 1 assertion)

vendor\bin\phpunit tests\Feature\AdminBladeModuleCoverageTest.php
OK (20 tests, 60 assertions)
```

### 验证边界

本轮未连接真实数据库，因此没有验证支付�?�道新增、编辑�?�删除接口的真实写入结果。真�? DB `127.0.0.1:3307` 恢复后，仍需使用超级管理员进�? `/admin/channels` 页面，实测支付�?�道列表、状态筛选�?�创建�?�道、修改汇率和扩展配置、删除�?�道，以及表格重载后的按钮权限显隐�??


## 60. 2026-06-07 后台入金审核 deposits/index.js 中文注释与审核权限边界修�?

本轮继续推进后台资金审核页面的源码可维护性，重点修复 `public/js/admin/layui/deposits/index.js`。该脚本负责后台入金审核列表、用�? ID 和状态筛选�?�入金金额展示�?�审核�?�过、审核驳回，以及 Layui 表格重载后的按钮权限刷新；入金审核直接影响客户资金数据，因此页面参数、审核动作和记录主键必须有清晰中文�?�辑注释�?

本轮只修复编码和中文逻辑注释，不改变业务行为：仍然读�? `POST /api/admin/depositList`，仍然�?�过 `POST /api/admin/depositApprove` 审核通过入金记录，仍然�?�过 `POST /api/admin/depositReject` 驳回入金记录，仍然在表格 `done` 回调中执�? `CrmAdminPermissions.refresh()`，按钮显隐继续由 `permissions.slug` 和当前管理员角色授权控制�?

### 本轮维护文件

- `tests/Feature/AdminDepositsJsCommentReadabilityTest.php`：新增入金审�? JS 注释可读性测试，约束入金审核列表、`user_id`、`status`、`amount`、`approve`、`reject`、`id`、`permissions.slug` 和乱码黑名单�?
- `public/js/admin/layui/deposits/index.js`：重写为 UTF-8 可读中文注释，补齐入金审核列表职责�?�搜索参数含义�?�入金金额展示边界�?�审核动作含义�?�记录主键用途和按钮权限刷新说明�?

### 注释覆盖�?

| 注释片段 | 功能作用 |
| --- | --- |
| `入金审核列表` | 说明列表数据来自 `/api/admin/depositList`，并由后端按管理员角色和数据范围过滤�? |
| `user_id 表示入金�?属用户` | 说明筛�?�参数对应入金记录中的业务用�? ID�? |
| `status 表示入金审核状�?�` | 说明状�?�筛选参数含义，空字符串表示不限制状态�?? |
| `amount 表示入金申请金额` | 说明金额字段只做列表展示，真实审核仍由后端接口校验�?? |
| `approve 表示审核通过入金记录` | 说明操作列�?�过按钮对应审核通过接口�? |
| `reject 表示驳回入金记录` | 说明操作列驳回按钮对应审核驳回接口�?? |
| `id 表示入金记录主键` | 说明审核接口参数用于后端读取记录并校验数据范围�?? |
| `重新应用按钮权限` | 说明 Layui 表格重载后必须重新隐藏无权限按钮�? |
| `permissions.slug` | 说明按钮显隐来自权限�? slug 与角色授权配置�?? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminDepositsJsCommentReadabilityTest.php
RED: 1 failure
- 缺少 `入金审核列表` 可读中文逻辑注释，原脚本仍保留历史乱码注释�??
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminDepositsJsCommentReadabilityTest.php
OK (2 tests, 17 assertions)

node --check public\js\admin\layui\deposits\index.js
exit 0

php -l tests\Feature\AdminDepositsJsCommentReadabilityTest.php
No syntax errors detected in tests\Feature\AdminDepositsJsCommentReadabilityTest.php

vendor\bin\phpunit tests\Feature\AdminButtonPermissionVisibilityTest.php
OK (2 tests, 36 assertions)

vendor\bin\phpunit tests\Feature\AdminBladeModuleCoverageTest.php
OK (20 tests, 60 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_admin_layui_scripts_use_hardcoded_api_urls_for_backend_requests
OK (1 test, 1 assertion)

Node 乱码扫描
No garbled fragments found in deposits/index.js
```

### 验证边界

本轮未连接真实数据库，因此没有验�? `deposit_records` 的真实列表数据�?�审核�?�过写入结果、审核驳回写入结果和不同管理员数据范围过滤效果�?�真�? DB `127.0.0.1:3307` 恢复后，仍需使用超级管理员进�? `/admin/deposits` 页面，实测入金列表�?�`user_id` 筛�?��?�`status` 筛�?��?�审核�?�过、审核驳回，以及低权限管理员�? `role_permissions` 和数据范围配置下的按钮显隐与数据隔离�?


## 61. 2026-06-07 后台出金审核 withdrawals Blade/JS 中文注释与状态流转边界修�?

本轮继续推进后台资金审核页面的源码可维护性，重点修复 `resources/admin/layui/withdrawals/index.blade.php` �? `public/js/admin/layui/withdrawals/index.js`。该模块负责出金审核页面结构、筛选表单�?�处理按钮�?�出金列表加载�?�状态筛选�?�标记处理中、完成出金�?�拒绝出金，以及 Layui 表格重载后的按钮权限刷新；出金审核直接影响客户资金安全，因此页面注释必须说明参数含义、接口来源�?�状态动作和后端权限边界�?

本轮只修复编码和中文逻辑注释，不改变业务行为：仍然读�? `POST /api/admin/withdrawList`，仍然�?�过 `POST /api/admin/withdrawProcess` 标记处理中，仍然通过 `POST /api/admin/withdrawComplete` 完成出金，仍然�?�过 `POST /api/admin/withdrawReject` 拒绝出金，按钮显隐继续由 `admin_withdraw_process`、`admin_withdraw_complete`、`admin_withdraw_reject` �? `permissions.slug` 与角色授权配置控制�??

### 本轮维护文件

- `tests/Feature/AdminWithdrawalsCommentReadabilityTest.php`：新增出金审�? Blade �? JS 注释可读性测试，约束页面边界、接口来源�?�`user_id`、`status`、`amount`、`process`、`complete`、`reject`、`id`、`permissions.slug` 和乱码黑名单�?
- `resources/admin/layui/withdrawals/index.blade.php`：重写页面顶部中文注释，说明列表接口、处理接口和后端权限与数据范围校验边界�??
- `public/js/admin/layui/withdrawals/index.js`：重写为 UTF-8 可读中文注释，补齐出金审核列表职责�?�搜索参数含义�?�出金金额展示边界�?�状态流转动作含义�?�记录主键用途和按钮权限刷新说明�?

### 注释覆盖�?

| 注释片段 | 功能作用 |
| --- | --- |
| `出金管理页面` | 说明 Blade 页面负责出金审核页面结构和操作按钮展示�?? |
| `admin_api_withdrawList` | 说明列表读取接口由后�? API 路由名配置�?? |
| `admin_api_withdrawProcess` | 说明“处理中”动作对应的后端权限接口�? |
| `admin_api_withdrawComplete` | 说明“完成出金�?�动作对应的后端权限接口�? |
| `admin_api_withdrawReject` | 说明“拒绝出金�?�动作对应的后端权限接口�? |
| `后端权限与数据范围校验` | 说明前端按钮不是�?终安全边界，真实权限由后端二次校验�?? |
| `出金审核列表` | 说明列表数据来自 `/api/admin/withdrawList`，并由后端按角色和数据范围过滤�?? |
| `user_id 表示出金申请人` | 说明筛�?�参数对应出金记录中的业务用�? ID�? |
| `status 表示出金处理状�?�` | 说明状�?�筛选参数和状�?�流转最终以后端校验为准�? |
| `amount 表示出金申请金额` | 说明金额字段只做列表展示，真实处理仍由后端接口校验�?? |
| `process 表示标记出金处理中` | 说明操作列处理中按钮对应状�?�流转入口�?? |
| `complete 表示完成出金记录` | 说明操作列完成按钮对应状态流转入口�?? |
| `reject 表示拒绝出金记录` | 说明操作列拒绝按钮对应状态流转入口�?? |
| `id 表示出金申请主键` | 说明处理接口参数用于后端读取记录、校验数据范围和判断状�?�流转�?? |
| `重新应用按钮权限` | 说明 Layui 表格重载后必须重新隐藏无权限按钮�? |
| `permissions.slug` | 说明按钮显隐来自权限�? slug 与角色授权配置�?? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminWithdrawalsCommentReadabilityTest.php
RED: 2 failures
- JS 缺少 `出金审核列表` 可读中文逻辑注释�?
- Blade 缺少 `出金管理页面` 可读中文逻辑注释�?
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminWithdrawalsCommentReadabilityTest.php
OK (2 tests, 32 assertions)

node --check public\js\admin\layui\withdrawals\index.js
exit 0

php -l tests\Feature\AdminWithdrawalsCommentReadabilityTest.php
No syntax errors detected in tests\Feature\AdminWithdrawalsCommentReadabilityTest.php

vendor\bin\phpunit tests\Feature\AdminButtonPermissionVisibilityTest.php
OK (2 tests, 36 assertions)

vendor\bin\phpunit tests\Feature\AdminBladeModuleCoverageTest.php
OK (20 tests, 60 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_admin_layui_scripts_use_hardcoded_api_urls_for_backend_requests
OK (1 test, 1 assertion)

Node 乱码扫描
No garbled fragments found in withdrawals JS/Blade
```

### 验证边界

本轮未连接真实数据库，因此没有验�? `withdraw_records` 的真实列表数据�?�标记处理中、完成出金�?�拒绝出金写入结果和不同管理员数据范围过滤效果�?�真�? DB `127.0.0.1:3307` 恢复后，仍需使用超级管理员进�? `/admin/withdrawals` 页面，实测出金列表�?�`user_id` 筛�?��?�`status` 筛�?��?�处理中、完成�?�拒绝，以及低权限管理员�? `role_permissions` 和数据范围配置下的按钮显隐与数据隔离�?


## 62. 2026-06-07 后台用户列表 users Blade/JS 中文注释与数据范围边界修�?

本轮继续推进后台用户管理页面的源码可维护性，重点修复 `resources/admin/layui/users/index.blade.php` �? `public/js/admin/layui/users/index.js`。该模块负责后台用户列表页面结构、用�? ID/邮箱/账号类型筛�?��?�代理与客户展示、认证状态展示�?�详情弹窗�?�账号启停状态切换，以及 Layui 表格重载后的按钮权限刷新；用户列表是后台数据范围控制的核心入口，因此页面注释必须说明筛�?�参数�?�接口来源�?�账号类型�?�状态按钮和后端权限边界�?

本轮只修复编码和中文逻辑注释，不改变业务行为：仍然读�? `POST /api/admin/userList`，仍然�?�过 `POST /api/admin/changeUserStatus` 切换登录账号启停状�?�，详情仍然使用 `crmRoute('admin_page_users_detail', {id: data.user_id})` 打开 `/admin/users/{id}` Blade 页面，状态按钮显隐继续由 `admin_user_status` 对应�? `permissions.slug` 与角色授权配置控制�??

### 本轮维护文件

- `tests/Feature/AdminUsersIndexCommentReadabilityTest.php`：新增用户列�? Blade �? JS 注释可读性测试，约束页面职责、接口来源�?�`user_id`、`email`、`account_type`、`auth_status`、`detail`、`status`、`is_enabled`、`permissions.slug` 和乱码黑名单�?
- `resources/admin/layui/users/index.blade.php`：补齐页面顶部中文注释，说明用户列表接口、状态修改接口�?�筛选字段和后端权限与数据范围校验边界�??
- `public/js/admin/layui/users/index.js`：重写为 UTF-8 可读中文注释，补齐用户列表数据来源�?�搜索参数�?�账号类型�?�认证状态�?�详情弹窗�?�状态切换和按钮权限刷新说明�?

### 注释覆盖�?

| 注释片段 | 功能作用 |
| --- | --- |
| `用户管理页面` | 说明 Blade 页面负责后台用户列表结构和操作按钮展示�?? |
| `admin_api_userList` | 说明列表读取接口由后�? API 路由名配置�?? |
| `admin_api_changeUserStatus` | 说明状�?�按钮对应的后端权限接口�? |
| `user_id 筛�?�业务用�? ID` | 说明筛�?�表单的业务用户 ID 参数含义�? |
| `email 筛�?�登录邮箱` | 说明筛�?�表单的登录邮箱参数含义�? |
| `account_type 区分代理和客户` | 说明账号类型用于区分代理商和普�?�客户�?? |
| `后端权限与数据范围校验` | 说明前端按钮不是�?终安全边界，真实权限由后端二次校验�?? |
| `用户列表` | 说明列表数据来自 `/api/admin/userList`，并由后端按角色和数据范围过滤�?? |
| `user_id 表示业务用户 ID` | 说明列表主键用于详情页面和状态修改接口�?? |
| `email 表示登录邮箱` | 说明邮箱字段来自 `user_logins` 关联对象�? |
| `account_type 表示账号类型` | 说明 `1=代理`、`2=客户` 的展示�?�辑�? |
| `auth_status 表示认证状�?�` | 说明认证状�?�枚举展示�?�辑�? |
| `detail 表示打开用户详情` | 说明详情按钮打开后台 Blade 详情页�?? |
| `status 表示切换用户启停状�?�` | 说明状�?�按钮的业务动作�? |
| `is_enabled 表示登录账号是否启用` | 说明状�?�修改接口参数含义�?? |
| `重新应用按钮权限` | 说明 Layui 表格重载后必须重新隐藏无权限按钮�? |
| `permissions.slug` | 说明按钮显隐来自权限�? slug 与角色授权配置�?? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminUsersIndexCommentReadabilityTest.php
RED: 2 failures
- JS 缺少 `用户列表` 可读中文逻辑注释�?
- Blade 缺少 `用户管理页面` 可读中文逻辑注释�?
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminUsersIndexCommentReadabilityTest.php
OK (2 tests, 33 assertions)

node --check public\js\admin\layui\users\index.js
exit 0

php -l tests\Feature\AdminUsersIndexCommentReadabilityTest.php
No syntax errors detected in tests\Feature\AdminUsersIndexCommentReadabilityTest.php

vendor\bin\phpunit tests\Feature\AdminButtonPermissionVisibilityTest.php
OK (2 tests, 36 assertions)

vendor\bin\phpunit tests\Feature\AdminBladeModuleCoverageTest.php
OK (20 tests, 60 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_admin_layui_scripts_use_hardcoded_api_urls_for_backend_requests
OK (1 test, 1 assertion)

vendor\bin\phpunit tests\Feature\FrontendRouteManifestTest.php --filter test_legacy_admin_user_table_detail_link_uses_global_route_manifest
OK (1 test, 2 assertions)

Node 乱码扫描
No garbled fragments found in users index JS/Blade
```

### 验证边界

本轮未连接真实数据库，因此没有验�? `user_infos`、`user_logins` 的真实列表数据�?�账号启停写入结果和不同管理员数据范围过滤效果�?�真�? DB `127.0.0.1:3307` 恢复后，仍需使用超级管理员进�? `/admin/users` 页面，实测用户列表�?�`user_id` 筛�?��?�`email` 筛�?��?�`account_type` 筛�?��?�详情弹窗�?�状态启停，以及低权限管理员�? `role_permissions`、`role_data_scopes` �? `admin_agent_bindings` 配置下的按钮显隐与数据隔离�??


## 63. 2026-06-08 后台用户详情 users/detail Blade/JS 中文注释与保存边界修�?

本轮继续推进后台用户管理页面的源码可维护性，重点修复 `resources/admin/layui/users/detail.blade.php` �? `public/js/admin/layui/users/detail.js`。该模块负责后台用户详情页面结构、隐藏用户主键�?�详情读取�?�基�?资料回填、用户姓�?/手机号保存�?�登录启停状态同步和保存成功后返回用户列表；用户详情是后台数据范围校验的重要单条入口，因此页面注释必须说�? `user_id` 来源、真实表关系、保存字段�?�状态字段和后端权限边界�?

本轮只修复编码和中文逻辑注释，不改变业务行为：仍然读�? `POST /api/admin/userDetail`，仍然�?�过 `POST /api/admin/updateUser` 更新 `user_infos` 基础资料，仍然�?�过 `POST /api/admin/changeUserStatus` 更新 `user_logins.is_enabled`，保存成功后仍然跳转 `crmRoute('admin_page_users')` 返回后台用户列表�?

### 本轮维护文件

- `tests/Feature/AdminUsersDetailCommentReadabilityTest.php`：新增用户详�? Blade �? JS 注释可读性测试，约束页面职责、隐藏主键�?�`admin_api_userDetail`、`admin_api_updateUser`、`admin_api_changeUserStatus`、`user_id`、`user_name`、`phone`、`status`、`is_enabled`、真实表关系和乱码黑名单�?
- `resources/admin/layui/users/detail.blade.php`：补齐页面顶部中文注释，说明 `/admin/users/{id}` 路由参数、隐藏字段�?�详情读取接口�?�保存接口�?�状态接口和后端权限与数据范围校验边界�??
- `public/js/admin/layui/users/detail.js`：重写为 UTF-8 可读中文注释，补齐详情读取�?�表单回填�?�`user_infos`、`user_logins`、基�?资料保存和启停状态同步说明�??

### 注释覆盖�?

| 注释片段 | 功能作用 |
| --- | --- |
| `用户详情页面` | 说明 Blade 页面负责后台用户单条详情编辑结构�? |
| `user_id 隐藏字段` | 说明隐藏主键来自 `/admin/users/{id}` 路由参数�? |
| `admin_api_userDetail` | 说明详情读取接口来源�? |
| `admin_api_updateUser` | 说明基础资料保存接口来源�? |
| `admin_api_changeUserStatus` | 说明登录启停状�?�同步接口来源�?? |
| `后端权限与数据范围校验` | 说明前端页面不是�?终安全边界，真实权限由后端二次校验�?? |
| `用户详情` | 说明 JS 负责读取并回填单条用户详情�?? |
| `user_id 表示业务用户 ID` | 说明详情读取和保存接口的主键含义�? |
| `user_infos` | 说明基础资料字段来源于业务用户表�? |
| `user_logins` | 说明登录邮箱和启停状态来自登录账号表�? |
| `user_name 表示用户姓名` | 说明保存�? `user_infos.user_name` 的字段含义�?? |
| `phone 表示用户手机号` | 说明保存�? `user_infos.phone` 的字段含义�?? |
| `status 表示页面选择的启停状态` | 说明页面表单状�?�和后端启停参数之间的关系�?? |
| `is_enabled 表示登录账号是否启用` | 说明写入 `user_logins.is_enabled` 的参数含义�?? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminUsersDetailCommentReadabilityTest.php
RED: 2 failures
- JS 缺少 `user_id 表示业务用户 ID` 可读中文逻辑注释�?
- Blade 缺少 `用户详情页面` 可读中文逻辑注释�?
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminUsersDetailCommentReadabilityTest.php
OK (2 tests, 33 assertions)

node --check public\js\admin\layui\users\detail.js
exit 0

php -l tests\Feature\AdminUsersDetailCommentReadabilityTest.php
No syntax errors detected in tests\Feature\AdminUsersDetailCommentReadabilityTest.php

vendor\bin\phpunit tests\Feature\AdminBladeModuleCoverageTest.php
OK (20 tests, 60 assertions)

vendor\bin\phpunit tests\Feature\FrontendRouteManifestTest.php --filter test_legacy_admin_user_table_detail_link_uses_global_route_manifest
OK (1 test, 2 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_admin_layui_scripts_use_hardcoded_api_urls_for_backend_requests
OK (1 test, 1 assertion)

Node 乱码扫描
No garbled fragments found in users detail JS/Blade
```

### 验证边界

本轮未连接真实数据库，因此没有验�? `user_infos`、`user_logins` 的真实详情读取�?�基�?资料保存、启停状态写入和不同管理员数据范围过滤效果�?�真�? DB `127.0.0.1:3307` 恢复后，仍需使用超级管理员进�? `/admin/users/{id}` 页面，实测详情回填�?�用户姓名保存�?�手机号保存、启停状态同步，以及低权限管理员�? `role_permissions`、`role_data_scopes` �? `admin_agent_bindings` 配置下的单条访问拦截�?


## 64. 2026-06-08 后台个人资料 profile/edit Blade/JS 中文注释与字段边界修�?

本轮继续推进后台管理员个人中心页面的源码可维护�?�，重点修复 `resources/admin/layui/profile/edit.blade.php` �? `public/js/admin/layui/profile/edit.js`。该模块负责当前登录管理员资料读取�?�用户名只读展示、邮箱编辑�?�手机号编辑和保存结果提示；个人资料接口虽然是当前管理员自服务入口，但仍属于后台认证链路，因此页面注释必须说明当前登录管理员、接口来源�?�字段可编辑边界和后端校验边界�??

本轮只修复编码和中文逻辑注释，不改变业务行为：仍然读�? `POST /api/admin/profileInfo`，仍然提�? `POST /api/admin/updateProfile`，`username` 继续只读展示，`email` �? `mobile` 继续由后�? `AuthController@updateProfile` 校验并写入当前管理员记录�?

### 本轮维护文件

- `tests/Feature/AdminProfileEditCommentReadabilityTest.php`：新增个人资料编�? Blade �? JS 注释可读性测试，约束当前管理员�?�`admin_api_profileInfo`、`admin_api_updateProfile`、`username`、`email`、`mobile`、可更新字段边界和乱码黑名单�?
- `resources/admin/layui/profile/edit.blade.php`：补齐页面顶部中文注释，说明当前登录管理员资料读取�?�保存接口和字段可编辑边界�??
- `public/js/admin/layui/profile/edit.js`：重写为 UTF-8 可读中文注释，补齐资料读取�?�表单回填�?�邮�?/手机号保存和后端校验说明�?

### 注释覆盖�?

| 注释片段 | 功能作用 |
| --- | --- |
| `后台个人资料编辑页面` | 说明 Blade 页面负责后台当前管理员个人资料编辑结构�?? |
| `当前登录管理员` | 说明读取和保存对象来�? admin guard 当前用户�? |
| `admin_api_profileInfo` | 说明资料读取接口来源�? |
| `admin_api_updateProfile` | 说明资料保存接口来源�? |
| `username 只读` | 说明用户名只展示，不通过本页面修改�?? |
| `email 可更新` | 说明邮箱是允许提交保存的字段�? |
| `mobile 可更新` | 说明手机号是允许提交保存的字段�?? |
| `username 表示管理员登录名` | 说明 JS 表单字段含义�? |
| `email 表示管理员邮箱` | 说明提交参数含义�? |
| `mobile 表示管理员手机号` | 说明提交参数含义�? |
| `只允许更�? email �? mobile` | 说明页面和后端保存字段边界�?? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminProfileEditCommentReadabilityTest.php
RED: 2 failures
- JS 缺少 `admin_api_profileInfo` 可读中文逻辑注释�?
- Blade 缺少 `后台个人资料编辑页面` 可读中文逻辑注释�?
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminProfileEditCommentReadabilityTest.php
OK (2 tests, 31 assertions)

node --check public\js\admin\layui\profile\edit.js
exit 0

php -l tests\Feature\AdminProfileEditCommentReadabilityTest.php
No syntax errors detected in tests\Feature\AdminProfileEditCommentReadabilityTest.php

vendor\bin\phpunit tests\Feature\AdminBladeModuleCoverageTest.php
OK (20 tests, 60 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_admin_layui_scripts_use_hardcoded_api_urls_for_backend_requests
OK (1 test, 1 assertion)

Node 乱码扫描
No garbled fragments found in profile edit JS/Blade
```

### 验证边界

本轮未连接真实数据库，因此没有验�? `admins` 表中当前管理员邮箱和手机号的真实读取、格式校验和写入结果。真�? DB `127.0.0.1:3307` 恢复后，仍需使用后台管理员进�? `/admin/profile/edit` 页面，实测资料回填�?�邮箱格式校验�?�手机号保存和保存成功提示�??


## 65. 2026-06-08 后台大代�? big-agents Blade/JS 中文注释�? CRUD 权限边界修复

本轮继续推进后台二批业务模块的源码可维护性，重点修复 `resources/admin/layui/big-agents/index.blade.php` �? `public/js/admin/layui/big-agents/index.js`。该模块负责大代理列表�?�刷新�?�新增�?�编辑�?�删除�?�弹窗表单和表格重载后的按钮权限刷新；大代理账号会影响前台大客户/大代理登录链路，因此页面注释必须说明 `big_agents` 数据来源、CRUD 接口、字段含义和 `permissions.slug` 按钮权限边界�?

本轮只修复编码和中文逻辑注释，不改变业务行为：仍然读�? `POST /api/admin/bigAgentList`，仍然�?�过 `POST /api/admin/createBigAgent` 新增，仍然�?�过 `POST /api/admin/updateBigAgent/{id}` 编辑，仍然�?�过 `POST /api/admin/deleteBigAgent/{id}` 删除；新增�?�编辑�?�删除按钮继续使�? `admin_big_agent_create`、`admin_big_agent_update`、`admin_big_agent_delete` 对应�? `permissions.slug` 控制显隐�?

### 本轮维护文件

- `tests/Feature/AdminBigAgentsCommentReadabilityTest.php`：新增大代理 Blade �? JS 注释可读性测试，约束 `big_agents`、`admin_api_bigAgentList`、`admin_api_createBigAgent`、`admin_api_updateBigAgent`、`admin_api_deleteBigAgent`、`id`、`username`、`password`、`status`、`permissions.slug` 和乱码黑名单�?
- `resources/admin/layui/big-agents/index.blade.php`：补齐页面顶部和弹窗表单中文注释，说�? CRUD 接口、按钮权限�?�安全边界�?�`id`、`password` �? `status` 字段含义�?
- `public/js/admin/layui/big-agents/index.js`：重写为 UTF-8 可读中文注释，补齐列表来源�?�表格字段�?�创�?/编辑分支、删除接口�?�密码留空边界和按钮权限刷新说明�?

### 注释覆盖�?

| 注释片段 | 功能作用 |
| --- | --- |
| `大代理管理页面` | 说明 Blade 页面负责大代�? CRUD 入口�? |
| `admin_api_bigAgentList` | 说明列表读取接口来源�? |
| `admin_api_createBigAgent` | 说明新增接口来源�? |
| `admin_api_updateBigAgent` | 说明编辑接口来源�? |
| `admin_api_deleteBigAgent` | 说明删除接口来源�? |
| `data-permission 对应 permissions.slug` | 说明按钮显隐来自权限�? slug�? |
| `后端 check.permission:admin` | 说明前端按钮不是�?终安全边界�?? |
| `id 为空表示新增` | 说明新增/编辑分支依据�? |
| `password 可留空` | 说明编辑时不修改密码的表单边界�?? |
| `big_agents` | 说明列表数据对应真实数据表�?? |
| `username 表示大代理登录名` | 说明登录名字段含义�?? |
| `password 表示大代理登录密码` | 说明密码字段含义�? |
| `status 表示大代理启停状态` | 说明状�?�字段含义�?? |
| `重新应用按钮权限` | 说明表格重载后必须重新隐藏无权限按钮�? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminBigAgentsCommentReadabilityTest.php
RED: 2 failures
- JS 缺少 `big_agents` 可读中文逻辑注释�?
- Blade 缺少 `大代理管理页面` 可读中文逻辑注释�?
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminBigAgentsCommentReadabilityTest.php
OK (2 tests, 37 assertions)

node --check public\js\admin\layui\big-agents\index.js
exit 0

php -l tests\Feature\AdminBigAgentsCommentReadabilityTest.php
No syntax errors detected in tests\Feature\AdminBigAgentsCommentReadabilityTest.php

vendor\bin\phpunit tests\Feature\AdminCrudUiControlsTest.php --filter test_big_agent_page_contains_crud_controls
OK (1 test, 8 assertions)

vendor\bin\phpunit tests\Feature\AdminSecondBatchModuleCoverageTest.php --filter big-agent
OK (6 tests, 14 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_admin_layui_scripts_use_hardcoded_api_urls_for_backend_requests
OK (1 test, 1 assertion)

Node 乱码扫描
No garbled fragments found in big-agents JS/Blade
```

### 验证边界

本轮未连接真实数据库，因此没有验�? `big_agents` 表中的真实列表数据�?�新增写入�?�编辑更新�?�删除结果和权限中间件真实拦截效果�?�真�? DB `127.0.0.1:3307` 恢复后，仍需使用后台管理员进�? `/admin/big-agents` 页面，实测列表�?�创建大代理、编辑大代理、编辑时密码留空、删除大代理，以及低权限管理员在 `role_permissions` 配置下的按钮显隐和接口拦截�??


## 66. 2026-06-08 后台黑名�? blacklist Blade/JS 中文注释�? CRUD 权限边界修复

本轮继续推进后台二批风控配置模块的源码可维护性，重点修复 `resources/admin/layui/blacklist/index.blade.php` �? `public/js/admin/layui/blacklist/index.js`。该模块负责黑名单列表�?�关键词搜索、新增�?�编辑�?�删除�?�弹窗表单和表格重载后的按钮权限刷新；黑名单会影响业务用户准入和风控判断，因此页面注释必须说�? `blacklists` 数据来源、关键词匹配范围、CRUD 接口、字段含义和 `permissions.slug` 按钮权限边界�?

本轮只修复编码和中文逻辑注释，不改变业务行为：仍然读�? `POST /api/admin/blacklistList`，仍然�?�过 `POST /api/admin/createBlacklist` 新增，仍然�?�过 `POST /api/admin/updateBlacklist/{id}` 编辑，仍然�?�过 `POST /api/admin/deleteBlacklist/{id}` 删除；新增�?�编辑�?�删除按钮继续使�? `admin_blacklist_create`、`admin_blacklist_update`、`admin_blacklist_delete` 对应�? `permissions.slug` 控制显隐�?

### 本轮维护文件

- `tests/Feature/AdminBlacklistCommentReadabilityTest.php`：新增黑名单 Blade �? JS 注释可读性测试，约束 `blacklists`、`keyword`、`name`、`id_card`、`email`、`phone`、`remark`、CRUD 接口、`permissions.slug` 和乱码黑名单�?
- `resources/admin/layui/blacklist/index.blade.php`：补齐页面顶部和弹窗表单中文注释，说明列表接口�?�搜索范围�?�CRUD 接口、按钮权限�?�安全边界和表单字段来源�?
- `public/js/admin/layui/blacklist/index.js`：重写为 UTF-8 可读中文注释，补齐列表来源�?�关键词搜索、表格字段�?�新�?/编辑分支、删除接口�?�备注字段和按钮权限刷新说明�?

### 注释覆盖�?

| 注释片段 | 功能作用 |
| --- | --- |
| `黑名单管理页面` | 说明 Blade 页面负责黑名�? CRUD 入口�? |
| `admin_api_blacklistList` | 说明列表读取接口来源�? |
| `admin_api_createBlacklist` | 说明新增接口来源�? |
| `admin_api_updateBlacklist` | 说明编辑接口来源�? |
| `admin_api_deleteBlacklist` | 说明删除接口来源�? |
| `keyword 匹配姓名` | 说明搜索关键字覆盖姓名�?�证件�?�邮箱和手机号�?? |
| `data-permission 来自 permissions.slug` | 说明按钮显隐来自权限�? slug�? |
| `后端 check.permission:admin` | 说明前端按钮不是�?终安全边界�?? |
| `字段名与 BlacklistController 入参保持�?致` | 说明表单字段与后端控制器入参对齐�? |
| `blacklists` | 说明列表数据对应真实数据表�?? |
| `keyword 表示统一搜索关键字` | 说明 JS 搜索参数含义�? |
| `name 表示黑名单姓名` | 说明姓名字段含义�? |
| `id_card 表示证件号码` | 说明证件字段含义�? |
| `email 表示邮箱` | 说明邮箱字段含义�? |
| `phone 表示手机号` | 说明手机号字段含义�?? |
| `remark 表示备注` | 说明备注字段含义�? |
| `重新应用按钮权限` | 说明表格重载后必须重新隐藏无权限按钮�? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminBlacklistCommentReadabilityTest.php
RED: 2 failures
- JS 缺少 `黑名单列表` 可读中文逻辑注释�?
- Blade 缺少 `黑名单管理页面` 可读中文逻辑注释�?
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminBlacklistCommentReadabilityTest.php
OK (2 tests, 41 assertions)

node --check public\js\admin\layui\blacklist\index.js
exit 0

php -l tests\Feature\AdminBlacklistCommentReadabilityTest.php
No syntax errors detected in tests\Feature\AdminBlacklistCommentReadabilityTest.php

vendor\bin\phpunit tests\Feature\AdminCrudUiControlsTest.php --filter test_blacklist_page_contains_crud_controls
OK (1 test, 9 assertions)

vendor\bin\phpunit tests\Feature\AdminSecondBatchModuleCoverageTest.php --filter blacklist
OK (6 tests, 14 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_admin_layui_scripts_use_hardcoded_api_urls_for_backend_requests
OK (1 test, 1 assertion)

Node 乱码扫描
No garbled fragments found in blacklist JS/Blade
```

### 验证边界

本轮未连接真实数据库，因此没有验�? `blacklists` 表中的真实列表数据�?�关键词搜索、新增写入�?�编辑更新�?�删除结果和权限中间件真实拦截效果�?�真�? DB `127.0.0.1:3307` 恢复后，仍需使用后台管理员进�? `/admin/blacklist` 页面，实测搜索�?�新增黑名单、编辑黑名单、删除黑名单，以及低权限管理员在 `role_permissions` 配置下的按钮显隐和接口拦截�??


## 67. 2026-06-08 后台注销申请 cancel-applies Blade/JS 中文注释与审核权限边界修�?

本轮继续推进后台二批审核模块的源码可维护性，重点修复 `resources/admin/layui/cancel-applies/index.blade.php` �? `public/js/admin/layui/cancel-applies/index.js`。该模块负责注销申请列表、状态筛选�?�审核�?�过、审核拒绝和表格重载后的按钮权限刷新；注�?申请会影响客户账号生命周期，因此页面注释必须说明 `cancel_applies` 数据来源、状态枚举�?�审核接口�?�记录主键和 `permissions.slug` 按钮权限边界�?

本轮只修复编码和中文逻辑注释，不改变业务行为：仍然读�? `POST /api/admin/cancelApplyList`，仍然�?�过 `POST /api/admin/cancelApplyApprove/{id}` 审核通过，仍然�?�过 `POST /api/admin/cancelApplyReject/{id}` 审核拒绝；审核按钮继续使�? `admin_cancel_apply_approve`、`admin_cancel_apply_reject` 对应�? `permissions.slug` 控制显隐�?

### 本轮维护文件

- `tests/Feature/AdminCancelAppliesCommentReadabilityTest.php`：新增注�?申请 Blade �? JS 注释可读性测试，约束 `cancel_applies`、`status`、`0=待处理`、`1=通过`、`-1=拒绝`、审核接口�?�`id`、`approve`、`reject`、`permissions.slug` 和乱码黑名单�?
- `resources/admin/layui/cancel-applies/index.blade.php`：补齐页面顶部和操作列中文注释，说明列表接口、审核接口�?�状态筛选�?�按钮权限和后端安全边界�?
- `public/js/admin/layui/cancel-applies/index.js`：重写为 UTF-8 可读中文注释，补齐列表来源�?�状态枚举�?�申请主键�?�审核�?�过/拒绝动作和按钮权限刷新说明�??

### 注释覆盖�?

| 注释片段 | 功能作用 |
| --- | --- |
| `注销申请管理页面` | 说明 Blade 页面负责注销申请审核入口�? |
| `admin_api_cancelApplyList` | 说明列表读取接口来源�? |
| `admin_api_cancelApplyApprove` | 说明审核通过接口来源�? |
| `admin_api_cancelApplyReject` | 说明审核拒绝接口来源�? |
| `status 为空表示全部申请` | 说明筛�?�表单状态参数边界�?? |
| `data-permission 来自 permissions.slug` | 说明按钮显隐来自权限�? slug�? |
| `后端 check.permission:admin` | 说明前端按钮不是�?终安全边界�?? |
| `注销申请列表` | 说明 JS 列表读取职责�? |
| `cancel_applies` | 说明数据对应真实注销申请表�?? |
| `status 表示注销申请状�?�` | 说明状�?�字段含义�?? |
| `0=待处理`、`1=通过`、`-1=拒绝` | 说明状�?�枚举�?? |
| `id 表示注销申请主键` | 说明审核接口路径参数含义�? |
| `approve 表示通过注销申请` | 说明操作列�?�过按钮业务动作�? |
| `reject 表示拒绝注销申请` | 说明操作列拒绝按钮业务动作�?? |
| `重新应用按钮权限` | 说明表格重载后必须重新隐藏无权限按钮�? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminCancelAppliesCommentReadabilityTest.php
RED: 2 failures
- JS 缺少 `注销申请列表` 可读中文逻辑注释�?
- Blade 缺少 `注销申请管理页面` 可读中文逻辑注释�?
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminCancelAppliesCommentReadabilityTest.php
OK (2 tests, 37 assertions)

node --check public\js\admin\layui\cancel-applies\index.js
exit 0

php -l tests\Feature\AdminCancelAppliesCommentReadabilityTest.php
No syntax errors detected in tests\Feature\AdminCancelAppliesCommentReadabilityTest.php

vendor\bin\phpunit tests\Feature\AdminButtonPermissionVisibilityTest.php
OK (2 tests, 36 assertions)

vendor\bin\phpunit tests\Feature\AdminSecondBatchModuleCoverageTest.php --filter cancel
OK (5 tests, 12 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_admin_layui_scripts_use_hardcoded_api_urls_for_backend_requests
OK (1 test, 1 assertion)

Node 乱码扫描
No garbled fragments found in cancel-applies JS/Blade
```

### 验证边界

本轮未连接真实数据库，因此没有验�? `cancel_applies` 表中的真实列表数据�?�状态筛选�?�审核�?�过、审核拒绝和权限中间件真实拦截效果�?�真�? DB `127.0.0.1:3307` 恢复后，仍需使用后台管理员进�? `/admin/cancel-applies` 页面，实测列表�?�状态筛选�?��?�过注销申请、拒绝注�?申请，以及低权限管理员在 `role_permissions` 配置下的按钮显隐和接口拦截�??


## 68. 2026-06-08 后台返佣结算 commissions Blade/JS 中文注释�? settle_status 字段修复

本轮继续推进后台资金与代理链路模块的源码可维护�?�，重点修复 `resources/admin/layui/commissions/index.blade.php` �? `public/js/admin/layui/commissions/index.js`。该模块负责返佣结算列表、代理筛选�?�结算状态筛选�?�单条返佣结算和表格重载后的按钮权限刷新；返佣记录�?�过 `agent_id` 与代理数据范围绑定，因此页面注释必须说明 `commission_records` 数据来源、`AdminDataScopeService` 裁剪边界、结算状态字段和 `permissions.slug` 按钮权限边界�?

本轮除修复编码和中文逻辑注释外，还修复了前端字段与后端控制器不一致的问题：后�? `CommissionController@index` 使用 `settle_status` 筛�?�，页面原来提交 `status` 且表格读�? `status`，本轮已统一改为 `settle_status`，避免结算状态筛选和展示字段失效。业务接口保持不变：仍然读取 `POST /api/admin/commissionList`，仍然�?�过 `POST /api/admin/commissionSettle` 结算单条返佣记录，结算按钮继续使�? `admin_commission_settle` 对应�? `permissions.slug` 控制显隐�?

### 本轮维护文件

- `tests/Feature/AdminCommissionsCommentReadabilityTest.php`：新增返佣结�? Blade �? JS 注释可读性测试，约束 `commission_records`、`agent_id`、`user_id`、`amount`、`settle_status`、`AdminDataScopeService`、接口来源�?�`id`、`settle`、`permissions.slug`、字段对齐和乱码黑名单�??
- `resources/admin/layui/commissions/index.blade.php`：补齐页面顶部和操作列中文注释，说明列表接口、结算接口�?�代理筛选�?�`settle_status` 筛�?�和后端安全边界�?
- `public/js/admin/layui/commissions/index.js`：重写为 UTF-8 可读中文注释，补齐列表来源�?�数据范围�?�表格字段�?�结算动作和按钮权限刷新说明�?

### 注释覆盖�?

| 注释片段 | 功能作用 |
| --- | --- |
| `返佣结算管理页面` | 说明 Blade 页面负责返佣结算入口�? |
| `admin_api_commissionList` | 说明列表读取接口来源�? |
| `admin_api_commissionSettle` | 说明单条结算接口来源�? |
| `agent_id 筛�?�返佣归属代理` | 说明代理筛�?�参数含义�?? |
| `settle_status 筛�?�结算状态` | 说明结算状�?�筛选参数必须与后端�?致�?? |
| `data-permission 来自 permissions.slug` | 说明按钮显隐来自权限�? slug�? |
| `后端 check.permission:admin` | 说明前端按钮不是�?终安全边界�?? |
| `返佣结算列表` | 说明 JS 列表读取职责�? |
| `commission_records` | 说明数据对应真实返佣记录表�?? |
| `agent_id 表示返佣归属代理` | 说明数据范围归属字段�? |
| `user_id 表示产生返佣的客户` | 说明客户字段展示含义�? |
| `amount 表示返佣金额` | 说明金额列展示含义�?? |
| `1=待结算`、`2=已结算` | 说明结算状�?�枚举�?? |
| `AdminDataScopeService` | 说明列表会按管理员角�?/代理范围裁剪�? |
| `id 表示返佣记录主键` | 说明结算接口参数含义�? |
| `settle 表示结算返佣记录` | 说明操作列按钮业务动作�?? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminCommissionsCommentReadabilityTest.php
RED: 2 failures
- JS 缺少 `返佣结算列表` 可读中文逻辑注释�?
- Blade 缺少 `返佣结算管理页面` 可读中文逻辑注释�?
- 测试同时约束 `name="settle_status"` �? `field: 'settle_status'`，用于暴露前端原 `status` 与后�? `settle_status` 不一致问题�??
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminCommissionsCommentReadabilityTest.php
OK (2 tests, 40 assertions)

node --check public\js\admin\layui\commissions\index.js
exit 0

php -l tests\Feature\AdminCommissionsCommentReadabilityTest.php
No syntax errors detected in tests\Feature\AdminCommissionsCommentReadabilityTest.php

vendor\bin\phpunit tests\Feature\AdminButtonPermissionVisibilityTest.php
OK (2 tests, 36 assertions)

vendor\bin\phpunit tests\Feature\AdminBladeModuleCoverageTest.php
OK (20 tests, 60 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_admin_layui_scripts_use_hardcoded_api_urls_for_backend_requests
OK (1 test, 1 assertion)

Node 乱码扫描
No garbled fragments found in commissions JS/Blade
```

### 验证边界

本轮未连接真实数据库，因此没有验�? `commission_records` 表中的真实列表数据�?�`settle_status` 筛�?�结果�?�单条结算写入和管理员代理数据范围裁剪效果�?�真�? DB `127.0.0.1:3307` 恢复后，仍需使用后台管理员进�? `/admin/commissions` 页面，实测代理筛选�?�待结算/已结算筛选�?�单条结算，以及低权限管理员�? `role_permissions`、`role_data_scopes` �? `admin_agent_bindings` 配置下的按钮显隐和数据隔离�??
## 69. 2026-06-08 后台组别配置 group-configs Blade/JS 中文注释与字段边界修�?

本轮继续推进后台配置类模块的 Blade + Layui 可维护�?�，重点修复 `resources/admin/layui/group-configs/index.blade.php` �? `public/js/admin/layui/group-configs/index.js`。该模块负责组别配置列表、关键字搜索、新增�?�编辑�?�删除�?�开关字段归�?化和表格重载后的按钮权限刷新；组别配置数据来自真�? `group_configs` 表，会影响代理组、用户组、交易基数�?�返佣开关�?�ECN 标记和默认组配置，因此页面与脚本必须明确字段来源、接口来源和权限边界�?

本轮只修复编码和中文逻辑注释，不改变现有业务接口：列表仍读取 `POST /api/admin/groupConfigList`，新增仍调用 `POST /api/admin/createGroupConfig`，编辑仍调用 `POST /api/admin/updateGroupConfig/{id}`，删除仍调用 `POST /api/admin/deleteGroupConfig/{id}`。新增�?�编辑�?�删除按钮继续使�? `admin_group_config_create`、`admin_group_config_update`、`admin_group_config_delete` 对应�? `permissions.slug` 控制显隐，后端最终仍�? `check.permission:admin` 鉴权�?

### 本轮维护文件

- `tests/Feature/AdminGroupConfigsCommentReadabilityTest.php`：新增组别配�? Blade �? JS 注释可读性测试，约束 `group_configs`、`keyword`、`name`、`group_name`、`radix`、`category`、`has_commission`、`is_enabled`、`is_ecn`、`is_default`、CRUD 接口、`id`、`permissions.slug` 和乱码黑名单�?
- `resources/admin/layui/group-configs/index.blade.php`：重写为 UTF-8 可读中文注释，说明页面职责�?�接口来源�?�`group_name` �? `group_configs.name` 的映射�?�`category=1/2` 业务含义、开关字段和权限边界�?
- `public/js/admin/layui/group-configs/index.js`：重写为 UTF-8 可读中文注释，说明列表来源�?�搜索参数�?�表格字段�?�弹窗参数�?�CRUD 接口、复选框 1/0 归一化和按钮权限刷新逻辑�?

### 注释覆盖�?

| 注释片段 | 功能作用 |
| --- | --- |
| `组别配置管理页面` | 说明 Blade 页面负责组别配置 CRUD 入口�? |
| `组别配置列表` | 说明 JS 表格读取职责�? |
| `group_configs` | 说明数据对应真实组别配置表�?? |
| `keyword 表示组别名称搜索关键字` | 说明搜索参数含义�? |
| `name 表示组别名称` | 说明列表字段来自 `group_configs.name`�? |
| `group_name 表示页面表单提交的组别名称` | 说明页面字段与后�? `normalizePayload()` 的映射关系�?? |
| `radix 表示交易组别基数` | 说明交易组基数字段含义�?? |
| `category 表示组别分类` | 说明分类字段含义�? |
| `1=代理组`、`2=用户组` | 说明 `category` 枚举边界�? |
| `has_commission 表示是否参与返佣` | 说明返佣�?关字段�?? |
| `is_enabled 表示是否启用` | 说明启用状�?�字段�?? |
| `is_ecn 表示是否 ECN 组` | 说明 ECN 标记字段�? |
| `is_default 表示是否默认组` | 说明默认组字段�?? |
| `admin_api_groupConfigList` | 说明列表接口来源�? |
| `admin_api_createGroupConfig` | 说明新增接口来源�? |
| `admin_api_updateGroupConfig` | 说明编辑接口来源�? |
| `admin_api_deleteGroupConfig` | 说明删除接口来源�? |
| `id 表示组别配置主键` | 说明编辑和删除路由参数含义�?? |
| `重新应用按钮权限` | 说明表格重载后必须重新隐藏无权限按钮�? |
| `permissions.slug` | 说明按钮权限来源�? |
| `后端 check.permission:admin` | 说明前端隐藏不是�?终安全边界�?? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminGroupConfigsCommentReadabilityTest.php
RED: 2 failures
- JS 缺少 `组别配置列表` 可读中文逻辑注释�?
- Blade 缺少 `组别配置管理页面` 可读中文逻辑注释�?
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminGroupConfigsCommentReadabilityTest.php
OK (2 tests, 59 assertions)

node --check public\js\admin\layui\group-configs\index.js
exit 0

php -l tests\Feature\AdminGroupConfigsCommentReadabilityTest.php
No syntax errors detected in tests\Feature\AdminGroupConfigsCommentReadabilityTest.php

vendor\bin\phpunit tests\Feature\AdminCrudUiControlsTest.php --filter test_group_config_page_contains_crud_controls
OK (1 test, 9 assertions)

vendor\bin\phpunit tests\Feature\AdminBladeModuleCoverageTest.php --filter group-configs
OK (2 tests, 6 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_admin_layui_scripts_use_hardcoded_api_urls_for_backend_requests
OK (1 test, 1 assertion)

Node 乱码扫描
group-configs JS/Blade 已无乱码命中；后�? Layui 剩余乱码文件�? `public/js/admin/layui/news/index.js`�?
```

### 验证边界

本轮尝试运行 `vendor\bin\phpunit tests\Feature\AdminConfigCrudPermissionMigrationTest.php`，但该测试需要连接真�? MySQL，当�? `127.0.0.1:3307` 返回 `SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接`，因此不能声明真�? DB 权限迁移验证通过。真�? DB 恢复后，仍需进入 `/admin/group-configs` 页面实测列表、关键字搜索、新增�?�编辑�?�删除，以及低权限管理员�? `role_permissions` 配置下的按钮显隐和接口拦截效果�??

## 70. 2026-06-08 后台新闻公告 news Blade/JS 中文注释与搜索字段修�?

本轮继续推进后台内容管理模块�? Blade + Layui 可维护�?�，重点修复 `resources/admin/layui/news/index.blade.php` �? `public/js/admin/layui/news/index.js`。该模块负责新闻公告列表、刷新�?�标题搜索�?�新增�?�编辑�?�删除�?�发布状态展示和表格重载后的按钮权限刷新；新闻公告数据来自真�? `news` 表，并�?�过 `is_published` 决定前台是否可见，因此页面与脚本必须明确字段来源、接口来源�?�发布状态枚举和权限边界�?

本轮除修复编码和中文逻辑注释外，还修复了前后端搜索字段不�?致的问题：`NewsController@index` 读取 `title` 作为搜索入参，原 Blade 搜索框提�? `keyword` 会导致搜索条件不生效。本轮已把搜索框改为 `name="title"`，与后端控制器保持一致�?�业务接口保持不变：列表仍读�? `POST /api/admin/newsList`，新增仍调用 `POST /api/admin/createNews`，编辑仍调用 `POST /api/admin/updateNews/{id}`，删除仍调用 `POST /api/admin/deleteNews/{id}`。新增�?�编辑�?�删除按钮继续使�? `admin_news_create`、`admin_news_update`、`admin_news_delete` 对应�? `permissions.slug` 控制显隐，后端最终仍�? `check.permission:admin` 鉴权�?

### 本轮维护文件

- `tests/Feature/AdminNewsCommentReadabilityTest.php`：新增新闻公�? Blade �? JS 注释可读性测试，约束 `news`、`title`、`content`、`is_published`、`1=已发布`、`0=未发布`、CRUD 接口、`id`、`permissions.slug`、搜索字段对齐和乱码黑名单�??
- `resources/admin/layui/news/index.blade.php`：重写为 UTF-8 可读中文注释，说明页面职责�?�接口来源�?�`title` 搜索参数、表单字段�?�发布状态和权限边界�?
- `public/js/admin/layui/news/index.js`：重写为 UTF-8 可读中文注释，说明列表来源�?�表格字段�?�弹窗参数�?�CRUD 接口、发布状态枚举和按钮权限刷新逻辑�?

### 注释覆盖�?

| 注释片段 | 功能作用 |
| --- | --- |
| `新闻公告管理页面` | 说明 Blade 页面负责新闻公告 CRUD 入口�? |
| `新闻公告列表` | 说明 JS 表格读取职责�? |
| `news` | 说明数据对应真实新闻公告表�?? |
| `title 搜索参数�? NewsController@index 保持�?致` | 说明搜索入参必须与后端控制器�?致�?? |
| `title 表示新闻标题` | 说明标题字段含义�? |
| `content 表示新闻正文` | 说明正文内容字段含义�? |
| `is_published 表示发布状�?�` | 说明发布状�?�字段含义�?? |
| `1=已发布`、`0=未发布` | 说明发布状�?�枚举边界�?? |
| `admin_api_newsList` | 说明列表接口来源�? |
| `admin_api_createNews` | 说明新增接口来源�? |
| `admin_api_updateNews` | 说明编辑接口来源�? |
| `admin_api_deleteNews` | 说明删除接口来源�? |
| `id 表示新闻公告主键` | 说明编辑和删除路由参数含义�?? |
| `重新应用按钮权限` | 说明表格重载后必须重新隐藏无权限按钮�? |
| `permissions.slug` | 说明按钮权限来源�? |
| `后端 check.permission:admin` | 说明前端隐藏不是�?终安全边界�?? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminNewsCommentReadabilityTest.php
RED: 2 failures
- JS 缺少 `新闻公告列表` 可读中文逻辑注释�?
- Blade 缺少 `新闻公告管理页面` 可读中文逻辑注释�?
- 测试同时约束搜索框必须提�? `title`，用于暴露原 `keyword` �? NewsController@index 不一致的问题�?
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminNewsCommentReadabilityTest.php
OK (2 tests, 55 assertions)

node --check public\js\admin\layui\news\index.js
exit 0

php -l tests\Feature\AdminNewsCommentReadabilityTest.php
No syntax errors detected in tests\Feature\AdminNewsCommentReadabilityTest.php

vendor\bin\phpunit tests\Feature\AdminCrudUiControlsTest.php --filter test_news_page_contains_crud_controls
OK (1 test, 9 assertions)

vendor\bin\phpunit tests\Feature\AdminBladeModuleCoverageTest.php --filter news
OK (2 tests, 6 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_admin_layui_scripts_use_hardcoded_api_urls_for_backend_requests
OK (1 test, 1 assertion)

Node 后台 Layui 全量乱码扫描
[]
```

### 验证边界

本轮未连接真实数据库，因此没有验�? `news` 表中的真实列表数据�?�标题搜索结果�?�新增写入�?�编辑更新�?�删除结果�?�发布状态对前台展示的影响，以及低权限管理员�? `role_permissions` 配置下的按钮显隐和接口拦截效果�?�真�? DB `127.0.0.1:3307` 恢复后，仍需进入 `/admin/news` 页面实测刷新、标题搜索�?�新增新闻公告�?�编辑新闻公告�?�删除新闻公告，并用前台新闻公告页面确认 `is_published=1` 的可见�?�边界�??

## 71. 2026-06-08 后台认证 AuthController 中文参数注释与旧密码错误多语�?修复

本轮继续推进后端控制器层“所有模块文件及参数必须有详细中文注释�?�的目标，重点维�? `app/Http/Controllers/Admin/AuthController.php`。该控制器是后台认证链路入口，负�? `admin_api_login`、`admin_api_logout`、`admin_api_refreshToken`、`admin_api_profileInfo`、`admin_api_updateProfile`、`admin_api_changePassword`、`admin_api_uploadAvatar` 等基�?接口；它决定后台管理员是谁�?�JWT 如何签发与失效�?�登录审计如何记录，以及改密后当�? Token 是否立即失效�?

本轮补齐控制器类、构造函数�?�登录�?�登出�?�资料读取�?�资料更新�?�改密�?�头像上传和刷新 Token 的中文�?�辑注释，明确请求参数和安全边界。同时修复一个后端多语言缺口：`changePassword()` 原来在旧密码错误时返回英文硬编码 `Old password incorrect`，本轮改�? `__('response.old_password_wrong')`，中英文语言包实际返回分别为“旧密码不正确�?�和“Old password is incorrect”�??

### 本轮维护文件

- `tests/Feature/AdminAuthControllerCommentReadabilityTest.php`：新增后台认证控制器注释可读性测试，约束登录参数、JWT 载荷、登录日志�?�Token 失效、资料字段�?�改密字段�?�头像字段�?�接口名、多语言键和乱码黑名单�??
- `app/Http/Controllers/Admin/AuthController.php`：补齐中文类注释、方法注释�?�参数注释和安全边界说明；旧密码错误响应从英文硬编码改为 `__('response.old_password_wrong')`�?

### 注释覆盖�?

| 注释片段 | 功能作用 |
| --- | --- |
| `后台管理员认证控制器` | 说明控制器职责�?? |
| `username 表示后台管理员登录名` | 说明登录账号参数含义�? |
| `password 表示后台管理员登录密码` | 说明登录密码参数含义�? |
| `sub 表示 admins.id` | 说明 JWT 主体字段含义�? |
| `guard 固定�? admin` | 说明后台 JWT guard 边界�? |
| `AdminLoginLog 记录登录审计信息` | 说明登录成功后的审计日志写入�? |
| `jwt_token 表示当前请求解析出的后台 JWT` | 说明登出、改密�?�刷�? Token �? Token 来源�? |
| `profileInfo 返回当前登录管理员资料` | 说明资料接口只返回当前管理员�? |
| `email 表示管理员邮箱` | 说明资料更新字段�? |
| `mobile 表示管理员手机号` | 说明资料更新字段�? |
| `old_password 表示当前旧密码` | 说明改密校验字段�? |
| `password_confirmation 表示新密码确认�?�` | 说明 Laravel confirmed 规则字段�? |
| `修改密码成功后使当前 Token 失效` | 说明改密安全边界�? |
| `avatar 表示上传的管理员头像文件` | 说明头像上传字段�? |
| `refreshToken 使用当前有效 Token 换取�? Token` | 说明刷新 Token 行为�? |
| `admin_api_login`、`admin_api_refreshToken` | 说明认证接口来源�? |
| `check.permission:admin` | 说明认证基础接口和业务权限中间件边界�? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminAuthControllerCommentReadabilityTest.php
RED: 1 failure
- AuthController 缺少 `username 表示后台管理员登录名` 等可读中文参数注释�??
- 测试同时约束旧密码错误必须使�? `__('response.old_password_wrong')`，不能保�? `'Old password incorrect'` 英文硬编码�??
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminAuthControllerCommentReadabilityTest.php
OK (1 test, 35 assertions)

php -l app\Http\Controllers\Admin\AuthController.php
No syntax errors detected in app\Http\Controllers\Admin\AuthController.php

php -l tests\Feature\AdminAuthControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\AdminAuthControllerCommentReadabilityTest.php

vendor\bin\phpunit tests\Feature\MenuRouteCommentReadabilityTest.php
OK (3 tests, 38 assertions)

php artisan tinker --execute="echo __('response.old_password_wrong', [], 'zh-CN') . PHP_EOL; echo __('response.old_password_wrong', [], 'en') . PHP_EOL;"
旧密码不正确
Old password is incorrect

Node AuthController 乱码扫描
[]
```

### 验证边界

本轮未连接真实数据库，因此没有使用真�? `admins` 账号实测 `/api/admin/login`、`/api/admin/changePassword`、`/api/admin/refreshToken`、`/api/admin/profileInfo`、`/api/admin/updateProfile` �? `/api/admin/uploadAvatar`。真�? DB `127.0.0.1:3307` 恢复后，仍需用后台管理员账号完整验证登录成功、登录日志写入�?�旧密码错误多语�?响应、改密后当前 Token 失效、刷�? Token 和资料更新�??

## 72. 2026-06-08 后台支付通道 PaymentChannelController 中文参数注释补齐

本轮继续推进后端控制器层“所有模块文件及参数必须有详细中文注释�?�的目标，重点维�? `app/Http/Controllers/Admin/PaymentChannelController.php`。该控制器负责后台支付�?�道列表、新增�?�编辑�?�删除和启用状�?�切换；支付通道数据来自真实 `payment_channels` 表，会影响前台入金�?�道展示和后台资金配置维护，因此控制器注释必须明确字段含义�?�兼容字段映射�?�接口权限边界和路由参数�?

本轮只补齐中文�?�辑注释，不改变 CRUD 行为：列表仍读取 `POST /api/admin/channelList`，新增仍调用 `POST /api/admin/createChannel`，编辑仍调用 `POST /api/admin/updateChannel/{id}`，删除仍调用 `POST /api/admin/deleteChannel/{id}`。页面按钮显隐仍来自 `permissions.slug`，接口最终仍�? `check.permission:admin` �? `permissions.api_route` 鉴权�?

### 本轮维护文件

- `tests/Feature/AdminPaymentChannelControllerCommentReadabilityTest.php`：新增支付�?�道控制器注释可读�?�测试，约束 `payment_channels`、分页参数�?��?�道字段、兼容字段�?�CRUD 接口、权限边界和乱码黑名单�??
- `app/Http/Controllers/Admin/PaymentChannelController.php`：补齐控制器类�?�列表�?�新增�?�编辑�?�删除�?�启用状态切换方法的中文参数说明和�?�辑边界说明�?

### 注释覆盖�?

| 注释片段 | 功能作用 |
| --- | --- |
| `支付通道管理控制器` | 说明控制器职责�?? |
| `payment_channels` | 说明数据来源表�?? |
| `admin_api_channelList` | 说明列表接口来源�? |
| `admin_api_createChannel` | 说明新增接口来源�? |
| `admin_api_updateChannel` | 说明编辑接口来源�? |
| `admin_api_deleteChannel` | 说明删除接口来源�? |
| `page 表示当前页码` | 说明分页入参�? |
| `per_page 表示每页数量` | 说明标准分页入参�? |
| `limit 表示 Layui 表格每页数量` | 说明 Layui 兼容分页入参�? |
| `name 表示支付通道名称` | 说明真实通道名称字段�? |
| `channel_name 表示旧页面提交的通道名称` | 说明旧字段到 `name` 的兼容映射�?? |
| `channel_code 表示支付通道编码` | 说明通道唯一编码字段�? |
| `exchange_rate 表示支付通道汇率` | 说明汇率字段�? |
| `is_enabled 表示通道是否启用` | 说明业务可用状�?�字段�?? |
| `sort 表示后台排序值` | 说明排序字段�? |
| `config 表示支付通道扩展配置` | 说明扩展配置字段�? |
| `id 表示支付通道主键` | 说明编辑、删除�?�切换状态路由参数�?? |
| `check.permission:admin` | 说明后端鉴权边界�? |
| `permissions.api_route` | 说明接口权限配置来源�? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminPaymentChannelControllerCommentReadabilityTest.php
RED: 1 failure
- PaymentChannelController 缺少 `支付通道管理控制器` 及字段参数可读中文�?�辑注释�?
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminPaymentChannelControllerCommentReadabilityTest.php
OK (1 test, 34 assertions)

php -l app\Http\Controllers\Admin\PaymentChannelController.php
No syntax errors detected in app\Http\Controllers\Admin\PaymentChannelController.php

php -l tests\Feature\AdminPaymentChannelControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\AdminPaymentChannelControllerCommentReadabilityTest.php

vendor\bin\phpunit tests\Feature\AdminCrudUiControlsTest.php --filter test_channel_page_contains_crud_controls
OK (1 test, 9 assertions)

vendor\bin\phpunit tests\Feature\AdminBladeModuleCoverageTest.php --filter channels
OK (2 tests, 6 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_admin_layui_scripts_use_hardcoded_api_urls_for_backend_requests
OK (1 test, 1 assertion)

Node PaymentChannelController 乱码扫描
[]
```

### 验证边界

本轮未连接真实数据库，因此没有验�? `payment_channels` 表中的真实列表数据�?�新增写入�?�编辑更新�?�删除结果�?��?�道启用状�?�对前台入金流程的影响，以及低权限管理员�? `role_permissions` 配置下的按钮显隐和接口拦截效果�?�真�? DB `127.0.0.1:3307` 恢复后，仍需进入 `/admin/channels` 页面实测刷新、新增支付�?�道、编辑支付�?�道、删除支付�?�道，并在前台入金流程确�? `is_enabled=1` 的支付�?�道可见性�??

## 73. 2026-06-08 后台大代�? BigAgent 字段对齐、乱码注释与控制器参数说明修�?

本轮继续推进“大代理模块”从 Blade/JS 到后端控制器和模型的字段�?致�?��?�排查发�? `big_agents` 表和前台大代理登录�?�辑使用 `is_enabled` 表示账号是否启用，但后台 Blade/JS 原来仍使�? `status` 字段，导致后台启停状态可能无法正确写入真实业务字段�?�本轮已统一改为 `is_enabled`，并补齐 `BigAgentController`、`BigAgent` 模型、`big-agents` Blade/JS 的中文�?�辑注释�?

本轮保留原接口不变：列表仍读�? `POST /api/admin/bigAgentList`，新增仍调用 `POST /api/admin/createBigAgent`，编辑仍调用 `POST /api/admin/updateBigAgent/{id}`，删除仍调用 `POST /api/admin/deleteBigAgent/{id}`。控制器兼容�? `status` 入参，但真实写入字段统一�? `big_agents.is_enabled`。编辑大代理时，`password` 留空现在通过 `nullable|string|min:6` 校验并保留原密码，避免空密码触发校验失败或覆盖旧密码�?

### 本轮维护文件

- `tests/Feature/AdminBigAgentBackendFieldAlignmentTest.php`：新增大代理后台字段对齐测试，约束控制器、模型�?�Blade、JS 全链路使�? `is_enabled`，并�?查乱码黑名单�?
- `tests/Feature/AdminBigAgentsCommentReadabilityTest.php`：重写为 UTF-8 可读中文测试，并把旧 `status` 断言改为 `is_enabled`�?
- `app/Http/Controllers/Admin/BigAgentController.php`：补齐接口�?�参数�?�权限边界和密码留空逻辑说明；新�? `normalizePayload()`，统�?�? `is_enabled/status` 兼容入参写入 `big_agents.is_enabled`�?
- `app/Models/BigAgent.php`：补齐模型职责�?�`sub_agent_ids`、`is_enabled` 和登录日志关联说明�??
- `resources/admin/layui/big-agents/index.blade.php`：表单字段从 `status` 改为 `is_enabled`，并补齐可读中文注释�?
- `public/js/admin/layui/big-agents/index.js`：表格字段和提交字段�? `status` 改为 `is_enabled`，并补齐可读中文注释�?

### 关键字段修复

| 字段 | 修复�? | 修复�? |
| --- | --- | --- |
| 启停状�?�表单字�? | `status` | `is_enabled` |
| 表格展示字段 | `status` | `is_enabled` |
| 控制器真实写入字�? | 可能�? `$request->all()` 写入 `status` | 统一写入 `big_agents.is_enabled` |
| 编辑密码留空 | `sometimes|string|min:6` 可能让空字符串参与校�? | `nullable|string|min:6`，留空保留原密码 |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminBigAgentBackendFieldAlignmentTest.php
RED: 1 failure
- BigAgentController 缺少 `admin_api_bigAgentList` 等控制器中文逻辑注释�?
- 测试同时约束 Blade/JS 必须使用 `is_enabled`，不能继续使�? `status`�?
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminBigAgentBackendFieldAlignmentTest.php
OK (1 test, 38 assertions)

vendor\bin\phpunit tests\Feature\AdminBigAgentsCommentReadabilityTest.php
OK (2 tests, 52 assertions)

php -l app\Http\Controllers\Admin\BigAgentController.php
No syntax errors detected in app\Http\Controllers\Admin\BigAgentController.php

php -l app\Models\BigAgent.php
No syntax errors detected in app\Models\BigAgent.php

php -l tests\Feature\AdminBigAgentsCommentReadabilityTest.php
No syntax errors detected in tests\Feature\AdminBigAgentsCommentReadabilityTest.php

php -l tests\Feature\AdminBigAgentBackendFieldAlignmentTest.php
No syntax errors detected in tests\Feature\AdminBigAgentBackendFieldAlignmentTest.php

node --check public\js\admin\layui\big-agents\index.js
exit 0

vendor\bin\phpunit tests\Feature\AdminCrudUiControlsTest.php --filter test_big_agent_page_contains_crud_controls
OK (1 test, 8 assertions)

vendor\bin\phpunit tests\Feature\AdminSecondBatchModuleCoverageTest.php --filter big-agent
OK (6 tests, 14 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_admin_layui_scripts_use_hardcoded_api_urls_for_backend_requests
OK (1 test, 1 assertion)

Node 大代理生产文件乱码扫�?
[]
```

### 验证边界

本轮未连接真实数据库，因此没有验�? `big_agents` 表中的真实列表数据�?�新增写入�?�编辑更新�?�删除结果�?�`is_enabled` 对前台大代理登录的真实拦截效果，以及低权限管理员�? `role_permissions` 配置下的按钮显隐和接口拦截效果�?�真�? DB `127.0.0.1:3307` 恢复后，仍需进入 `/admin/big-agents` 页面实测创建大代理�?�编辑大代理、编辑时密码留空、启停状态保存�?�删除大代理，并用前台大代理登录入口确认 `is_enabled=0` 的账号不可登录�??

## 74. 2026-06-08 后台角色权限分配 UI、权限控制器注释�? guard 边界修复

本轮继续推进后台“菜单�?�按钮�?�接口权限全部来自数据表配置”的目标，重点修复角色与权限管理链路。此前后台角色页只能新增、编辑�?�删除角色，没有直接分配菜单/按钮/接口权限�? UI；虽然后端已�? `POST /api/admin/assignPermissions`，但页面无法维护 `role_permissions`，会导致“多种管理员角色拥有不同菜单权限”的配置闭环不完整�??

本轮已在 `/admin/roles` �? Blade + Layui JS 中新增�?�分配权限�?�入口：操作列按钮使�? `admin_role_assign_permissions`，点击后加载 `POST /api/admin/permissionTree` 权限树，按当前角�? `permission_ids` 回显勾�?�状态，保存时提�? `role_id` �? `permissions` 数组�? `POST /api/admin/assignPermissions`，最终由后端写入 `role_permissions` 表�??

### 本轮维护文件

- `app/Http/Controllers/Admin/RoleController.php`：重写中文�?�辑注释，补�? `page`、`per_page`、`role_id`、`permissions` 等参数说明；角色列表返回 `permission_ids`；`assignPermissions()` 增加�? `guard_type` 权限校验，只允许后台角色绑定 admin 权限、前台角色绑�? front 权限�?
- `app/Http/Controllers/Admin/PermissionController.php`：重写中文�?�辑注释，明�? `permissions` 表是前后台菜单�?�页面�?�按钮和接口权限的唯�?配置来源；新�?/更新权限改为显式字段白名单�??
- `resources/admin/layui/roles/index.blade.php`：新�? `assignPermissions` 操作按钮、`rolePermissionForm` 表单、`permissionTreeBox` 权限树容器和 `saveRolePermissions` 保存按钮�?
- `public/js/admin/layui/roles/index.js`：新增权限树加载、已授权节点回显、`tree.getChecked()` 收集权限 ID、保存授权�?�按钮权限刷新和详细中文参数注释�?
- `database/migrations/2026_06_06_000006_add_admin_core_button_permissions.php`：新�? `admin_role_assign_permissions => admin_api_assignPermissions`，确保授权按钮权限也来自 `permissions` 表�??
- `resources/lang/zh-CN/role.php`、`resources/lang/en/role.php`：新�? Blade 侧�?�分配权限�?�文案�??
- `public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`：新�? JS �? `role.assignPermissions`、`role.assignPermissionHint` 文案�?
- `tests/Feature/AdminRolePermissionAssignmentUiTest.php`：新增角色授�? UI 覆盖测试，约�? Blade/JS 必须存在授权入口和真�? API 调用�?
- `tests/Feature/AdminRolePermissionControllerReadabilityTest.php`：新增角�?/权限控制器中文注释与乱码黑名单测试�??
- `tests/Feature/AdminCorePermissionMigrationTest.php`、`tests/Feature/AdminButtonPermissionVisibilityTest.php`：补�? `admin_role_assign_permissions` 期望�?

### 本轮接口与权限消�?

| 页面/接口 | 方法 | 作用 | 权限来源 |
| --- | --- | --- | --- |
| `/admin/roles` | GET | 角色管理 Blade 页面，渲染角色列表和权限分配弹窗 | 页面入口来自后台菜单权限 |
| `/api/admin/roleList` | POST | 返回角色列表、分页�?�数�? `permission_ids` | `permissions.api_route=admin_api_roleList` |
| `/api/admin/permissionTree` | POST | �? `guard_type` 返回权限�? | `permissions.api_route=admin_api_permissionTree` |
| `/api/admin/assignPermissions` | POST | 写入 `role_permissions` 授权关系 | `admin_role_assign_permissions` / `admin_api_assignPermissions` |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminRolePermissionControllerReadabilityTest.php
RED: RoleController 缺少 `roles 表保存角色基�?信息` 等中文�?�辑注释�?

vendor\bin\phpunit tests\Feature\AdminRolePermissionAssignmentUiTest.php
RED: 角色 Blade 缺少 `admin_role_assign_permissions`，说明页面没有权限分配入口�??
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminRolePermissionAssignmentUiTest.php
OK (1 test, 27 assertions)

vendor\bin\phpunit tests\Feature\AdminRolePermissionControllerReadabilityTest.php
OK (1 test, 27 assertions)

vendor\bin\phpunit tests\Feature\AdminButtonPermissionVisibilityTest.php
OK (2 tests, 37 assertions)

vendor\bin\phpunit tests\Feature\AdminBladeButtonPermissionRouteCoverageTest.php
OK (1 test, 233 assertions)

vendor\bin\phpunit tests\Feature\AdminTablePermissionRefreshTest.php
OK (1 test, 2 assertions)

node --check public\js\admin\layui\roles\index.js
exit 0

php -l app\Http\Controllers\Admin\RoleController.php
No syntax errors detected in app\Http\Controllers\Admin\RoleController.php

php -l app\Http\Controllers\Admin\PermissionController.php
No syntax errors detected in app\Http\Controllers\Admin\PermissionController.php

php -l database\migrations\2026_06_06_000006_add_admin_core_button_permissions.php
No syntax errors detected in database\migrations\2026_06_06_000006_add_admin_core_button_permissions.php

Node 本轮文件乱码扫描
app/Http/Controllers/Admin/RoleController.php: OK
app/Http/Controllers/Admin/PermissionController.php: OK
resources/admin/layui/roles/index.blade.php: OK
public/js/admin/layui/roles/index.js: OK
resources/lang/zh-CN/role.php: OK
resources/lang/en/role.php: OK
public/js/common/lang/zh-CN.js: OK
public/js/common/lang/en.js: OK
database/migrations/2026_06_06_000006_add_admin_core_button_permissions.php: OK
```

### 验证边界

`vendor\bin\phpunit tests\Feature\AdminCorePermissionMigrationTest.php` 本轮运行时仍因真�? MySQL `127.0.0.1:3307` 拒绝连接失败，错误为 `SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接。` 因此本轮没有声明真实 DB 权限迁移写入已验证�??

真实 DB 恢复后必须继续执行：运行核心权限迁移，确�? `permissions.slug=admin_role_assign_permissions` �? `api_route=admin_api_assignPermissions`；使用超级管理员进入 `/admin/roles`，打�?“分配权限�?�弹窗，勾�??/取消权限后保存；再用低权限管理员登录，验�? `/api/admin/menus` 返回的菜单与按钮权限确实�? `role_permissions` 改变�?

## 75. 2026-06-08 角色与权限模型中文注释�?�字段含义和授权来源补齐

本轮继续推进后台权限链路的模型层维护，重点处�? `app/Models/Role.php` �? `app/Models/Permission.php`。这两个模型�? `roles`、`permissions`、`role_permissions` 三张权限核心表的入口，如果模型注释不可读或字段边界不清晰，后续控制器、菜单服务和 Blade 页面即使已经接入权限表，也容易再次出现�?�双权限来源”或前后台权限混用问题�??

本轮不改变业务行为：`Role::hasPermission($slug)` 仍然通过 `permissionsRelation()` 读取 `role_permissions` 中间表，并按 `permissions.slug` �? `permissions.status=1` 判断；`Permission` 模型仍保留原�? `admin/front/menu/page/button/active` scope，只补齐中文逻辑注释和字段参数说明�??

### 本轮维护文件

- `tests/Feature/AdminRolePermissionModelReadabilityTest.php`：新增模型层中文注释可读性测试，约束 Role/Permission 模型必须说明字段含义、关联关系�?�授权来源和乱码黑名单�??
- `app/Models/Role.php`：补�? `roles`、`guard_type`、`role_permissions`、`roles.permissions JSON`、`role_data_scopes`、`permissionsRelation()`、`hasPermission($slug)` �? `admins()` 的中文说明�??
- `app/Models/Permission.php`：补�? `permissions` 表字段�?�`slug`、`api_route`、`guard_type`、`type=1/2/3`、父子权限�?�角色关联和 scope 参数说明�?

### 模型字段边界

| 模型 | 字段/关系 | 当前说明 |
| --- | --- | --- |
| `Role` | `guard_type` | `admin` 表示后台管理员角色，`front` 表示前台代理商或普�?�客户角色�?? |
| `Role` | `permissions()` | 通过 `role_permissions.role_id` �? `role_permissions.permission_id` 关联权限�? |
| `Role` | `permissionsRelation()` | 兼容旧调用名，仍返回 `permissions()`，不引入第二套授权来源�?? |
| `Role` | `permissions` JSON | 仅保留兼容字段，不作为真实鉴权来源�?? |
| `Permission` | `slug` | 前端 `data-permission` 和后端权限判断共同使用的稳定标识�? |
| `Permission` | `api_route` | Laravel 命名路由，供 `check.permission:admin` 匹配接口权限�? |
| `Permission` | `type` | `1=菜单`、`2=页面`、`3=按钮或接口动作`�? |
| `Permission` | `guard_type` | 区分后台 `admin` 与前�? `front` 权限，避免两端混用�?? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminRolePermissionModelReadabilityTest.php
RED: Role 模型缺少 `roles 表保存后台管理员、前台代理商和普通客户可绑定的角色` 等中文�?�辑注释�?
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminRolePermissionModelReadabilityTest.php
OK (1 test, 29 assertions)

php -l app\Models\Role.php
No syntax errors detected in app\Models\Role.php

php -l app\Models\Permission.php
No syntax errors detected in app\Models\Permission.php

php -l tests\Feature\AdminRolePermissionModelReadabilityTest.php
No syntax errors detected in tests\Feature\AdminRolePermissionModelReadabilityTest.php

Node 生产模型文件乱码扫描
app/Models/Role.php: OK
app/Models/Permission.php: OK
```

### 验证边界

`vendor\bin\phpunit tests\Feature\AdminPermissionPlanTest.php --filter test_role_permission_check_uses_role_permissions_table` 本轮运行时仍因真�? MySQL `127.0.0.1:3307` 拒绝连接失败，错误为 `SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接。` 因此本轮只完成源码与静�?�测试验证，没有声明真实 DB �? `roles`、`permissions`、`role_permissions` 的运行时查询已验证�??

真实 DB 恢复后必须继续执行：创建或确认一个普通后台角色，给它写入部分 `role_permissions`，调�? `Role::hasPermission()` 或访问受保护后台接口，验证已授权 slug 返回允许、未授权 slug 返回拒绝；同时确�? `roles.permissions` JSON 不参与真实鉴权判断�??

## 76. 2026-06-08 CheckPermission 中间件中文注释与多语�?权限响应边界补齐

本轮继续推进后台权限强制鉴权链路，重点维�? `app/Http/Middleware/CheckPermission.php`。该中间件是后台接口权限安全边界：`jwt.auth:admin` 只能确认“是谁�?�，`sso:admin` 只能确认“当�? token 是否仍有效�?�，真正判断当前管理员能不能访问当前接口，必须�?�过 `permissions.api_route`、`permissions.guard_type` �? `role_permissions` 完成�?

本轮不改变鉴权行为，只补齐中间件中文逻辑注释、参数含义�?�白名单边界和多语言响应说明。当前�?�辑仍保持：未登录返�? `__('response.auth_failed')`；无路由名�?�无角色、权限未配置、角色未授权时返�? `__('response.permission_denied')`；超级管理员只跳过权限表校验，不跳过 JWT �? SSO�?

### 本轮维护文件

- `tests/Feature/AdminCheckPermissionMiddlewareReadabilityTest.php`：新增中间件中文注释可读性测试，约束鉴权顺序、`$guardType`、`$routeName`、`permissions.api_route`、`permissions.guard_type`、`role_permissions`、白名单和多语言响应�?
- `app/Http/Middleware/CheckPermission.php`：补齐类注释、`handle()` 参数说明、鉴权顺序�?�白名单说明和超级管理员边界说明�?

### 核心权限边界

| 步骤 | 当前职责 |
| --- | --- |
| `jwt.auth:admin` | 解析后台管理�? token，确认当前请求是谁�?? |
| `sso:admin` | 校验当前 token 是否仍是该账号有效登录�?? |
| `check.permission:admin` | 按当前命名路由匹�? `permissions.api_route`，再�? `role_permissions` 判断当前角色是否授权�? |
| 白名单接�? | 菜单、个人资料�?�改密�?�头像�?��??出登录�?�刷�? token，只要求登录�? SSO 有效�? |
| 超级管理�? | 只跳过权限表校验，不能跳过登录认证和 SSO�? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminCheckPermissionMiddlewareReadabilityTest.php
RED: CheckPermission 缺少 `后台接口权限�?查中间件` 等中文�?�辑注释�?
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminCheckPermissionMiddlewareReadabilityTest.php
OK (1 test, 23 assertions)

vendor\bin\phpunit tests\Feature\AdminLocalizationTest.php
OK (2 tests, 10 assertions)

php -l app\Http\Middleware\CheckPermission.php
No syntax errors detected in app\Http\Middleware\CheckPermission.php

php -l tests\Feature\AdminCheckPermissionMiddlewareReadabilityTest.php
No syntax errors detected in tests\Feature\AdminCheckPermissionMiddlewareReadabilityTest.php

Node 生产文件乱码扫描
app/Http/Middleware/CheckPermission.php: OK
resources/lang/zh-CN/response.php: OK
resources/lang/en/response.php: OK
```

### 验证边界

`vendor\bin\phpunit tests\Feature\AdminPermissionPlanTest.php --filter test_admin_protected_routes_include_permission_middleware` 本轮运行时仍因真�? MySQL `127.0.0.1:3307` 拒绝连接失败，错误为 `SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接。` 因此本轮没有声明真实数据库事务场景下的完整权限中间件测试通过�?

真实 DB 恢复后必须继续执行：用普通后台管理员访问�?个未授权的受保护接口，确认返�? `4006` 与当前语�?�? `response.permission_denied`；给该角色写入对�? `role_permissions` 后再次访问，确认接口放行；再用超级管理员确认同接口可跳过权限表校验但仍必须携带有�? token�?

## 77. 2026-06-08 前台 Layui 菜单权限配置与真实路由一致�?�补�?

本轮针对 `agent` 账号登录后前�? Layui 菜单缺失的问题继续收口�?�当前前台菜单不�? Blade 静�?�写死，而是�? `public/js/front/layui/layout.js` 调用 `POST /api/front/navigation/menus` 后动态渲染；后端 `Front\MenuController@userMenus` 再根�? `user_logins.role_id`、`roles`、`role_permissions` �? `permissions` 返回当前账号可见菜单树�??

排查时发�? `database/migrations/2026_06_07_000014_fix_default_admin_and_front_menu_roles.php` 中两个前台返佣菜单的 `permissions.api_route` 写成了不存在的命名路由：`front_api_commissionRealTime`、`front_api_commissionHistory`。这会导致菜单权限字典和真实接口路由不一致，后续如果�? `api_route` 做接口级鉴权或配置审计，会出现�?�菜单已授权但接口路由不可匹配�?�的隐患�?

### 本轮维护文件

- `tests/Feature/FrontMenuPermissionRouteConsistencyTest.php`：新增前台菜单权限配置一致�?�测试，直接读取前台菜单修复迁移里的 `frontMenuTree()`，并逐条校验非空 `api_route` 是否存在�? Laravel 当前命名路由表�??
- `database/migrations/2026_06_07_000014_fix_default_admin_and_front_menu_roles.php`：修�? `front_commission_rt` �? `api_route` �? `front_api_commissions_realtime`，修�? `front_commission_hist` �? `api_route` �? `front_api_commissions_history`�?

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontMenuPermissionRouteConsistencyTest.php
RED: 前台菜单 permissions.api_route 存在未注册的命名路由�?
- front_commission_rt => front_api_commissionRealTime
- front_commission_hist => front_api_commissionHistory
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\FrontMenuPermissionRouteConsistencyTest.php
OK (1 test, 2 assertions)

vendor\bin\phpunit tests\Feature\DefaultAdminAndFrontMenuRoleMigrationTest.php tests\Feature\FrontMenuPermissionRouteConsistencyTest.php
OK (2 tests, 57 assertions)

php -l database\migrations\2026_06_07_000014_fix_default_admin_and_front_menu_roles.php
No syntax errors detected in database\migrations\2026_06_07_000014_fix_default_admin_and_front_menu_roles.php

php -l tests\Feature\FrontMenuPermissionRouteConsistencyTest.php
No syntax errors detected in tests\Feature\FrontMenuPermissionRouteConsistencyTest.php

Node UTF-8/乱码片段扫描
database/migrations/2026_06_07_000014_fix_default_admin_and_front_menu_roles.php: OK
tests/Feature/FrontMenuPermissionRouteConsistencyTest.php: OK
```

### 验证边界

本轮验证的是源码层面的菜单权限配置与当前 Laravel 命名路由表一致�?�，没有声明真实 MySQL �? `permissions`、`roles`、`role_permissions`、`user_logins.role_id` 已经写入成功。真�? DB 恢复后仍�?执行迁移，并�? `agent@test.com / agent123` 登录前台 Layui，确�? `POST /api/front/navigation/menus` 返回代理菜单树，且返佣实时�?�返佣历史菜单对应页面能正常调用当前真实接口�?

## 78. 2026-06-08 后台用户控制器参数注释与前台资料路由别名补齐

本轮继续围绕 plan.md 中�?�所有模块文件及参数必须有详细中文注释�?�和“前后台�?有菜单�?�按钮�?�接口权限从数据表配置得到�?�的要求推进。优先维护后台用户管理控制器，因为它同时涉及用户列表、详情�?�资料更新�?�实名认证审核�?�登录账号启停和数据范围校验，是后台数据查看权限链路中的核心入口�?

同时在复跑数据范围接入测试时发现应用启动阶段存在前台资料接口兼容别名错误：`routes/web.php` 仍把 `front.password.update`、`front.profile.update`、`front.profile.avatar` 等旧 Blade 兼容别名指向不存在的 `front_api_changePassword`、`front_api_updateProfile`、`front_api_uploadAvatar`。当前真实路由名已经�? `front_api_profile_password`、`front_api_profile_update`、`front_api_profile_avatar`。本轮已同步修正别名�? `CheckPermission` 基础白名单，避免应用 boot 时因别名目标缺失直接报错�?

### 本轮维护文件

- `tests/Feature/AdminUserControllerCommentReadabilityTest.php`：新增后台用户控制器中文注释可读性测试，要求保留 `userList()`、`reviewAuth()`、`userDetail()`、`updateUser()`、`changeUserStatus()` 的参数含义�?�表来源、数据范围和权限边界说明�?
- `app/Http/Controllers/Admin/AdminUserController.php`：补齐控制器类注释和核心接口参数说明，明�? `user_id`、`email`、`account_type`、`status`、`reason`、`is_enabled` 的业务含义，并说明接口权限来�? `permissions.api_route`，数据范围来�? `AdminDataScopeService` 读取 `role_data_scopes` �? `admin_agent_bindings`�?
- `routes/web.php`：修正旧 Blade 路由别名目标，将前台资料更新、改密�?�头像上传别名指向当前真实前�? API 路由名�??
- `app/Http/Middleware/CheckPermission.php`：同步更新前台资料类基础白名单，使用当前真实路由名，避免白名单继续引用已不存在的旧接口名�?

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminUserControllerCommentReadabilityTest.php
RED: AdminUserController 缺少中文参数或�?�辑注释：userList() 参数说明
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminUserControllerCommentReadabilityTest.php
OK (1 test, 25 assertions)

php artisan route:list --columns=method,uri,name
已确认当前真实前台资料路由存在：
- front_api_profile_avatar
- front_api_profile_password
- front_api_profile_update

php -l app\Http\Controllers\Admin\AdminUserController.php
No syntax errors detected in app\Http\Controllers\Admin\AdminUserController.php

php -l routes\web.php
No syntax errors detected in routes\web.php

php -l app\Http\Middleware\CheckPermission.php
No syntax errors detected in app\Http\Middleware\CheckPermission.php

生产文件 UTF-8/乱码片段扫描
app/Http/Controllers/Admin/AdminUserController.php: OK
routes/web.php: OK
app/Http/Middleware/CheckPermission.php: OK
```

### 验证边界

`vendor\bin\phpunit tests\Feature\AdminDataScopeControllerWiringTest.php` 在路由别名修复后已经不再因为 `front.password.update -> front_api_changePassword` 目标缺失而中断，当前失败原因推进为真�? MySQL `127.0.0.1:3307` 拒绝连接：`SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接。` 因此本轮没有声明真实数据库事务场景下的数据范围接入测试�?�过。真�? DB 恢复后仍�?继续执行该测试，并验证普通管理员�? `role_data_scopes` �? `admin_agent_bindings` 限制下只能访问授权范围内的用户�?�代理�?�入金�?�出金和返佣数据�?

## 79. 2026-06-08 �? Blade 路由兼容别名�?致�?�测试与中文参数说明补齐

本轮继续处理前后端不分离场景下的 Blade 路由稳定性问题�?�当前项目同时保�? `resources/views` 历史 Blade、`resources/front/layui`、`resources/admin/layui` �? Naive 页面入口，因�? `routes/web.php` 末尾维护了一组旧 Blade 路由兼容别名。该别名配置如果指向不存在的真实命名路由，Laravel 应用会在 boot 阶段直接抛出 `Route alias target is missing`，导致后台页面�?�前台页面和自动化测试全部被阻断�?

### 本轮维护文件

- `tests/Feature/BladeRouteAliasCompatibilityTest.php`：新增旧 Blade 路由兼容别名�?致�?�测试，静�?�解�? `$crmBladeRouteAliases`，并校验每个 `targetName` 与每�? `alias` 都已经注册到 Laravel 路由表�??
- `routes/web.php`：补�? `crm_alias_named_route()` �? `$crmBladeRouteAliases` 配置块的中文逻辑注释，说�? `alias`、`targetName`、目标路由存在�?�校验和兼容边界�?

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\BladeRouteAliasCompatibilityTest.php
RED: routes/web.php 兼容别名配置缺少中文说明：旧 Blade 路由兼容别名
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\BladeRouteAliasCompatibilityTest.php
OK (2 tests, 66 assertions)

php artisan route:list --columns=method,uri,name
已确认应用可正常 boot，并能列�? admin_page_dashboard、front_page_dashboard、front_api_profile_password、front_api_profile_update、front_api_profile_avatar 等真实路由�??

php -l routes\web.php
No syntax errors detected in routes\web.php

php -l tests\Feature\BladeRouteAliasCompatibilityTest.php
No syntax errors detected in tests\Feature\BladeRouteAliasCompatibilityTest.php

基础 UTF-8/乱码片段扫描
routes/web.php: OK
tests/Feature/BladeRouteAliasCompatibilityTest.php: OK
```

### 验证边界

本轮验证的是路由别名配置与当�? Laravel 命名路由表的�?致�?�，未连接真�? MySQL，也未声明业务接口权限�?�菜单授权或数据范围配置已经在数据库中执行成功�?�真�? DB 恢复后仍�?继续执行数据范围、菜单权限和用户登录场景测试�?

## 80. 2026-06-08 后台用户控制器响应文案多语言�?

本轮继续推进 plan.md 中�?�后端必须支持多语言”和“所有模块文件及参数必须有详细中文注释�?�的要求，优先修�? `app/Http/Controllers/Admin/AdminUserController.php` 中仍然硬编码英文响应文案的问题�?�该控制器负责后台用户列表�?�删除�?�详情�?�更新和启停状�?�接口，如果响应文案直接写死英文，会导致后台 Blade 页面、Layui 弹层提示和后续接口错误提示无法按 Laravel 当前语言环境切换�?

### 本轮维护文件

- `tests/Feature/AdminUserControllerLocalizationTest.php`：新增后台用户控制器多语�?测试，验证控制器不再包含 `User list fetched`、`User not found`、`User deleted`、`User detail fetched`、`User updated`、`User status updated` 等硬编码英文响应，并验证 `resources/lang/zh-CN/admin.php` �? `resources/lang/en/admin.php` 均存在对应语�? key�?
- `app/Http/Controllers/Admin/AdminUserController.php`：将用户列表、用户不存在、删除成功�?�详情获取成功�?�更新成功和状�?�更新成功的响应文案全部改为 `__('admin.xxx')` 语言包调用�?�接口参数�?�数据范围和权限边界的中文�?�辑注释保持不变�?

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminUserControllerLocalizationTest.php
RED: 控制器仍存在硬编码英文响应：User list fetched
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminUserControllerLocalizationTest.php
OK (2 tests, 36 assertions)

vendor\bin\phpunit tests\Feature\AdminUserControllerCommentReadabilityTest.php
OK (1 test, 25 assertions)

php -l app\Http\Controllers\Admin\AdminUserController.php
No syntax errors detected in app\Http\Controllers\Admin\AdminUserController.php

php -l tests\Feature\AdminUserControllerLocalizationTest.php
No syntax errors detected in tests\Feature\AdminUserControllerLocalizationTest.php
```

### 验证边界

本轮验证的是后台用户控制器源码层面的多语�?调用和语�?�? key 完整性，未连接真�? MySQL，因此没有使用真实管理员 Token 调用 `/api/admin/users`、用户详情�?�更新�?�删除和启停接口。真�? DB `127.0.0.1:3307 / co_crmv5` 恢复后，仍需�? `zh-CN` �? `en` 两种语言环境下分别调用接口，确认 JSON 响应 message �? Blade/Layui 前端提示文案按当前语�?正确显示�?

## 81. 2026-06-08 后台系统统计控制器多语言与中文注释补�?

本轮继续推进 plan.md 中�?�后端必须支持多语言”和“所有模块文件及参数必须有详细中文注释�?�的要求，重点维�? `app/Http/Controllers/Admin/AdminDashboardController.php`。该控制器是旧后台系统统计入口之�?，为 Blade + Layui 仪表盘返回用户�?�代理�?�客户�?�待审核入金、待处理出金和今日新增用户统计�?�原实现中接口响�? message 直接写死 `System statistics fetched`，并且文件内存在较多英文临时说明，不利于后台多语�?和后续维护�??

### 本轮维护文件

- `tests/Feature/AdminDashboardControllerLocalizationTest.php`：新增后台统计控制器多语�?测试，要求响应文案必须使�? `__('admin.system_statistics_fetched')`，并校验 `resources/lang/zh-CN/admin.php` �? `resources/lang/en/admin.php` 均存�? `system_statistics_fetched`�?
- `tests/Feature/AdminDashboardControllerCommentReadabilityTest.php`：新增中文注释可读�?�测试，要求控制器包含类职责、`dashboardData()` 参数说明、统计字段含义�?�`account_type` 业务含义�? `created_at` 时间戳说明�??
- `app/Http/Controllers/Admin/AdminDashboardController.php`：将响应文案改为语言包调用；补齐中文逻辑注释和参数注释；清理未使用的 `ResponseCode`、`DB` 引入；删除旧英文临时说明，保留统计�?�辑不变�?
- `resources/lang/zh-CN/admin.php`：新�? `system_statistics_fetched => 系统统计获取成功`�?
- `resources/lang/en/admin.php`：新�? `system_statistics_fetched => System statistics fetched`�?

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminDashboardControllerLocalizationTest.php
RED:
- 后台统计控制器仍存在硬编码英文响应：System statistics fetched
- zh-CN/admin.php 缺少 system_statistics_fetched

vendor\bin\phpunit tests\Feature\AdminDashboardControllerCommentReadabilityTest.php
RED: 后台统计控制器缺少中文注释：后台系统统计控制�?
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminDashboardControllerLocalizationTest.php
OK (2 tests, 6 assertions)

vendor\bin\phpunit tests\Feature\AdminDashboardControllerCommentReadabilityTest.php
OK (1 test, 11 assertions)

php -l app\Http\Controllers\Admin\AdminDashboardController.php
No syntax errors detected in app\Http\Controllers\Admin\AdminDashboardController.php

php -l tests\Feature\AdminDashboardControllerLocalizationTest.php
No syntax errors detected in tests\Feature\AdminDashboardControllerLocalizationTest.php

php -l tests\Feature\AdminDashboardControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\AdminDashboardControllerCommentReadabilityTest.php

php -l resources\lang\zh-CN\admin.php
No syntax errors detected in resources\lang\zh-CN\admin.php

php -l resources\lang\en\admin.php
No syntax errors detected in resources\lang\en\admin.php
```

### 验证边界

本轮验证的是后台统计控制器源码层面的多语�?调用、语�?�? key 完整性�?�PHP 语法和中文�?�辑注释完整性，未连接真�? MySQL，因此没有使用真实超级管理员 Token 调用后台统计接口。真�? DB `127.0.0.1:3307 / co_crmv5` 恢复后，仍需登录 `/admin/login`，进入后台仪表盘并确认统计接口返回的 `total_users`、`total_agents`、`total_customers`、`pending_deposits`、`pending_withdrawals`、`today_new_users` 与真实数据一致，同时确认 `zh-CN` �? `en` 两种语言�? message 正确切换�?

## 82. 2026-06-08 旧入金导入占位接口多语言与中文边界说明补�?

本轮继续推进 plan.md 中�?�后端必须支持多语言”和“所有模块文件及参数必须有详细中文注释�?�的要求，重点处�? `app/Http/Controllers/Admin/DepositController.php` 中的旧入金导入占位入口�?�当前真实批量入金导入功能已经迁移到 `BatchAmountImportController`，并通过 `deposit_imports` 表和 `/api/admin/createDepositImport` 等接口承载；�? `DepositController#import` 仍保留兼容入口，但原实现直接返回硬编码英�? `Import feature coming soon`，不符合后端多语�?要求，也缺少“不要在此处继续新增真实导入逻辑”的边界说明�?

### 本轮维护文件

- `tests/Feature/DepositControllerImportLocalizationTest.php`：新增旧入金导入占位接口测试，要�? `DepositController#import` 不再包含硬编码英文响应，必须调用 `__('admin.deposit_import_feature_coming_soon')`，并要求中英文语�?包存在同�? key�?
- `app/Http/Controllers/Admin/DepositController.php`：将旧占位响应改为语�?包调用；补齐 `import()` 方法中文参数说明，明�? `$request` 的旧上传入口含义、真实批量导入已迁移�? `BatchAmountImportController`、当前方法只保留兼容响应�?
- `resources/lang/zh-CN/admin.php`：新�? `deposit_import_feature_coming_soon => 入金导入功能即将�?放`�?
- `resources/lang/en/admin.php`：新�? `deposit_import_feature_coming_soon => Deposit import feature coming soon`�?

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\DepositControllerImportLocalizationTest.php
RED:
- DepositController#import 仍存在硬编码英文占位响应
- zh-CN/admin.php 缺少 deposit_import_feature_coming_soon
- DepositController#import 缺少中文说明：旧入金导入占位入口
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\DepositControllerImportLocalizationTest.php
OK (3 tests, 11 assertions)

php -l app\Http\Controllers\Admin\DepositController.php
No syntax errors detected in app\Http\Controllers\Admin\DepositController.php

php -l tests\Feature\DepositControllerImportLocalizationTest.php
No syntax errors detected in tests\Feature\DepositControllerImportLocalizationTest.php

php -l resources\lang\zh-CN\admin.php
No syntax errors detected in resources\lang\zh-CN\admin.php

php -l resources\lang\en\admin.php
No syntax errors detected in resources\lang\en\admin.php

生产控制器硬编码扫描
rg -n "success\([^\n]*'Import feature coming soon'|error\([^\n]*'Import feature coming soon'" app\Http\Controllers\Admin -S
未发现匹配项�?
```

### 验证边界

本轮没有实现新的 CSV/Excel 入金导入功能，也没有修改 `BatchAmountImportController` 的真实批量导入链路；只修复旧兼容入口的多语言响应与中文边界注释�?�真�? DB 恢复后，仍需分别验证 `/api/admin/createDepositImport` 写入 `deposit_imports` 表�?�`/api/admin/depositImportList` 列表展示、失败记录重试以及旧 `DepositController#import` 兼容入口�? `zh-CN` �? `en` 语言环境下返回正�? message�?

## 83. 2026-06-08 旧后台用户控制器中文逻辑注释补齐

本轮继续推进 plan.md 中�?�所有模块文件及参数必须有详细中文注释�?�的要求，重点维�? `app/Http/Controllers/Admin/UserController.php`。该控制器是旧后台用户管理入口之�?，响应文案此前已经改为语�?包调用，但文件内仍保�? `User Management Controller`、`List all users`、`Filter by user_id`、`Review identity verification` 等英文注释，�? `page`、`per_page`、`account_type`、`auth_status`、`comm_rate`、`is_enabled`、`status`、`reason`、`is_cancelled` 等参数缺少清晰中文业务含义说明�??

### 本轮维护文件

- `tests/Feature/UserControllerCommentReadabilityTest.php`：新增旧后台用户控制器中文注释可读�?�测试，要求类职责�?�列表筛选参数�?�详情参数�?�更新字段�?�状态切换参数�?�实名认证审核参数和注销字段均有中文说明�?
- `app/Http/Controllers/Admin/UserController.php`：补齐类级中文�?�辑说明，明�? `user_id` 对应 `user_infos.user_id`、`user_logins.user_id` �? `user_auths.user_id`；补�? `index()`、`show()`、`update()`、`updateStatus()`、`reviewAuth()`、`destroy()` 的中文参数说明；将英文行内注释替换为中文；清理未使用�? `DB` 引入�?

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\UserControllerCommentReadabilityTest.php
RED: UserController 缺少中文注释：后台用户管理控制器
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\UserControllerCommentReadabilityTest.php
OK (1 test, 19 assertions)

php -l app\Http\Controllers\Admin\UserController.php
No syntax errors detected in app\Http\Controllers\Admin\UserController.php

php -l tests\Feature\UserControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\UserControllerCommentReadabilityTest.php

旧英文注释扫�?
rg -n "User Management Controller|List all users|Filter by|Get user detail|Update user info|Enable/disable|Review identity|Soft delete user|Approved|Rejected|If SoftDeletes|resources/lang/\*/admin" app\Http\Controllers\Admin\UserController.php
未发现匹配项�?
```

### 验证边界

本轮没有改动 `UserController` 的业务查询�?�更新�?�审核和删除逻辑，只补齐中文逻辑注释并清理未使用引入。真�? DB 恢复后，仍需用后台管理员 Token 调用旧用户管理接口，验证列表筛�?��?�详情�?�资料更新�?�启停�?�实名认证审核和注销流程在真实数据上的行为是否与新后台权限和数据范围要求�?致�??

## 84. 2026-06-08 返佣结算控制器中文�?�辑注释补齐

本轮继续推进 plan.md 中�?�所有模块文件及参数必须有详细中文注释�?�的要求，重点维�? `app/Http/Controllers/Admin/CommissionController.php`。该控制器负责后台返佣记录列表�?�详情�?�单笔结算和批量结算，并且已经�?�过 `AdminDataScopeService` �? `commission_records.agent_id` 做管理员数据范围限制。原文件仍保�? `Commission Settlement Controller`、`List commission settlement records`、`Settle single commission record`、`Batch settle multiple records` �? `// Settled` 等英文注释，�? `agent_id`、`settle_status`、`ids`、数据范围校验边界没有完整中文说明�??

### 本轮维护文件

- `tests/Feature/CommissionControllerCommentReadabilityTest.php`：新增返佣结算控制器中文注释可读性测试，要求类职责�?�列表筛选参数�?�详情参数�?�单笔结算参数�?�批量结算参数和数据范围判断字段均有中文说明�?
- `app/Http/Controllers/Admin/CommissionController.php`：补齐类级中文�?�辑说明，明确返佣权限归属字段为 `commission_records.agent_id`；补�? `index()`、`show()`、`settle()`、`batchSettle()`、`denyCommissionAccessIfNeeded()` 的中文参数说明；明确 `settle_status=2` 表示已结算；�? `// Settled` 等英文注释替换为中文；清理未使用�? `Validator` 引入�?

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\CommissionControllerCommentReadabilityTest.php
RED: CommissionController 缺少中文注释：后台返佣结算控制器
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\CommissionControllerCommentReadabilityTest.php
OK (1 test, 14 assertions)

php -l app\Http\Controllers\Admin\CommissionController.php
No syntax errors detected in app\Http\Controllers\Admin\CommissionController.php

php -l tests\Feature\CommissionControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\CommissionControllerCommentReadabilityTest.php

旧英文注释扫�?
rg -n "Commission Settlement Controller|List commission|Get commission|Settle single|Batch settle|Settled" app\Http\Controllers\Admin\CommissionController.php
未发现匹配项�?
```

### 验证边界

本轮没有改动返佣查询、详情�?�单笔结算�?�批量结算或数据范围判断逻辑，只补齐中文逻辑注释并清理未使用引入。真�? DB 恢复后，仍需用不同角色后台管理员分别调用返佣列表、详情�?�单笔结算和批量结算接口，验�? `role_data_scopes`、`admin_agent_bindings` �? `agent_id` 数据范围限制是否阻止越权查看或结算�??

## 85. 2026-06-08 后台仪表盘统计控制器中文逻辑注释补齐

本轮继续推进 plan.md 中�?�所有模块文件及参数必须有详细中文注释�?�的要求，重点维�? `app/Http/Controllers/Admin/DashboardController.php`。该控制器为后台 Blade + Layui 首页统计卡片和趋势图提供数据，原文件保留 `Dashboard Statistics Controller`、`Dashboard overview statistics`、`Detailed statistics with date range`、`Total users`、`Paid`、`Completed` 等英文注释，�? `total_users`、`pending_deposits`、`start_date`、`end_date`、`user_stats`、`deposit_stats`、`withdraw_stats` 等统计字段缺少完整中文业务说明�??

### 本轮维护文件

- `tests/Feature/DashboardControllerCommentReadabilityTest.php`：新增仪表盘统计控制器中文注释可读�?�测试，要求类职责�?�概览统计字段�?�趋势统计字段�?�日期参数和入金/出金状�?��?�均有中文说明�??
- `app/Http/Controllers/Admin/DashboardController.php`：补齐类级中文�?�辑说明，明�? `index()` 返回统计卡片数据、`stats()` 返回趋势图数据；补齐 `total_users`、`total_agents`、`total_customers`、`pending_deposits`、`pending_withdrawals`、`start_date`、`end_date`、`user_stats`、`deposit_stats`、`withdraw_stats`、`status=02`、`status=2` 的中文说明；清理未使用的 `UserLogin` 引入和旧英文注释�?

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\DashboardControllerCommentReadabilityTest.php
RED: DashboardController 缺少中文注释：后台仪表盘统计控制�?
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\DashboardControllerCommentReadabilityTest.php
OK (1 test, 15 assertions)

php -l app\Http\Controllers\Admin\DashboardController.php
No syntax errors detected in app\Http\Controllers\Admin\DashboardController.php

php -l tests\Feature\DashboardControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\DashboardControllerCommentReadabilityTest.php

旧英文统计注释扫�?
rg -n "Dashboard Statistics Controller|Dashboard overview|Detailed statistics|Total users|Total agents|Total customers|Pending deposits|Pending withdrawals|User registration stats|Deposit amount stats|Withdraw amount stats|Paid|Completed" app\Http\Controllers\Admin\DashboardController.php
未发现匹配项�?
```

### 验证边界

本轮没有改动仪表盘统�? SQL、日期范围默认�?��?�入金状态或出金状�?��?�辑，只补齐中文逻辑注释并清理未使用引入。真�? DB 恢复后，仍需登录后台首页验证统计卡片和趋势图数据是否与真�? `user_infos`、`deposit_records`、`withdraw_records` 数据�?致，并确认日期筛选参数在 Blade 页面中能正确传�?�到 `stats()` 接口�?

## 86. 2026-06-08 代理等级控制器中文�?�辑注释补齐

本轮继续推进 plan.md 中�?�所有模块文件及参数必须有详细中文注释�?�的要求，重点维�? `app/Http/Controllers/Admin/AgentLevelController.php`。该控制器负责代理等级列表�?�新增�?�更新和删除，等级编码与返佣字段会影响后台代理配置�?�前台代理资料展示和返佣规则维护。原文件仍保�? `Agent Level Management Controller`、`List all agent levels`、`Create agent level`、`Update agent level`、`Delete agent level` 等英文标题，�? `level_code`、`name`、`max_commission`、`min_commission`、`user_commission`、`level`、`commission_rate` 等字段缺少完整中文说明�??

### 本轮维护文件

- `tests/Feature/AgentLevelControllerCommentReadabilityTest.php`：新增代理等级控制器中文注释可读性测试，要求类职责�?�CRUD 参数、真实表字段和旧页面兼容字段映射均有中文说明�?
- `app/Http/Controllers/Admin/AgentLevelController.php`：补齐类级中文�?�辑说明，明确数据写�? `agent_levels` 表；补齐 `index()`、`store()`、`update()`、`destroy()`、`normalizePayload()` 的中文参数说明；说明 `level_code` 是代理等级编码，`level` 是旧页面等级编码字段，`commission_rate` 会映射为 `user_commission`�?

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AgentLevelControllerCommentReadabilityTest.php
RED: AgentLevelController 缺少中文注释：后台代理等级管理控制器
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AgentLevelControllerCommentReadabilityTest.php
OK (1 test, 14 assertions)

php -l app\Http\Controllers\Admin\AgentLevelController.php
No syntax errors detected in app\Http\Controllers\Admin\AgentLevelController.php

php -l tests\Feature\AgentLevelControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\AgentLevelControllerCommentReadabilityTest.php

旧英�? CRUD 标题扫描
rg -n "Agent Level Management Controller|List all agent levels|Create agent level|Update agent level|Delete agent level" app\Http\Controllers\Admin\AgentLevelController.php
未发现匹配项�?
```

### 验证边界

本轮没有改动代理等级新增、更新�?�删除�?�辑，也没有调整 `agent_levels` 表结构或返佣字段计算方式，只补齐中文逻辑注释。真�? DB 恢复后，仍需在后台代理等级页面验证新增�?�更新�?�删除和旧字段兼容映射是否能正确写入 `level_code`、`max_commission`、`min_commission`、`user_commission`�?

## 87. 2026-06-08 注销申请控制器中文�?�辑注释补齐

本轮继续推进 plan.md 中�?�所有模块文件及参数必须有详细中文注释�?�的要求，重点维�? `app/Http/Controllers/Admin/CancelApplyController.php`。该控制器负责后台查看�?��?�过和拒绝客户账号注�?申请，�?�过申请时会标记 `user_infos.is_cancelled` 并调用用户模�? `delete()`。原文件仍保�? `Account Cancellation Management Controller`、`List cancel applications`、`Approve cancellation`、`Reject cancellation`、`// Soft delete` 等英文注释，�? `status`、`reason`、`reject_reason`、`is_cancelled` 和软删除边界缺少完整中文说明�?

### 本轮维护文件

- `tests/Feature/CancelApplyControllerCommentReadabilityTest.php`：新增注�?申请控制器中文注释可读�?�测试，要求类职责�?�列表筛选参数�?�审核�?�过参数、拒绝参数�?�状态�?�和用户注销标记均有中文说明�?
- `app/Http/Controllers/Admin/CancelApplyController.php`：补齐类级中文�?�辑说明，明�? `cancel_applies.status` 状�?��?�含义：`0=待处理`、`1=已�?�过`、`-1=已拒绝`；补�? `index()`、`approve()`、`reject()` 的中文参数说明；说明 `reason` 写入 `cancel_applies.reject_reason`，`is_cancelled` 写入 `user_infos.is_cancelled`，`delete()` 执行用户软删除；清理未使用的 `Validator` 引入�?

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\CancelApplyControllerCommentReadabilityTest.php
RED: CancelApplyController 缺少中文注释：后台注�?申请管理控制�?
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\CancelApplyControllerCommentReadabilityTest.php
OK (1 test, 11 assertions)

php -l app\Http\Controllers\Admin\CancelApplyController.php
No syntax errors detected in app\Http\Controllers\Admin\CancelApplyController.php

php -l tests\Feature\CancelApplyControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\CancelApplyControllerCommentReadabilityTest.php

旧英文标题扫�?
rg -n "Account Cancellation Management Controller|List cancel applications|Approve cancellation|Reject cancellation|Soft delete" app\Http\Controllers\Admin\CancelApplyController.php
未发现匹配项�?
```

### 验证边界

本轮没有改动注销申请审核通过、拒绝或用户删除逻辑，只补齐中文逻辑注释并清理未使用引入。真�? DB 恢复后，仍需在后台注�?申请页面验证状�?�筛选�?��?�过申请、拒绝申请�?�拒绝原因保存和用户软删除行为是否符合真实业务预期�??

## 88. 2026-06-08 黑名单控制器中文逻辑注释补齐

本轮继续推进 plan.md 中�?�所有模块文件及参数必须有详细中文注释�?�的要求，重点维�? `app/Http/Controllers/Admin/BlacklistController.php`。该控制器是后台风控黑名单配置入口，负责黑名单列表查询�?�新增�?�更新和删除。原文件仍保�? `Blacklist Management Controller`、`List all blacklist entries`、`Add entry to blacklist`、`Update blacklist entry`、`Delete from blacklist` 等英文注释，�? `keyword`、`name`、`id_card`、`email`、`phone`、`reason`、`status` �? `$request->all()` 写入边界缺少完整中文说明�?

### 本轮维护文件

- `tests/Feature/BlacklistControllerCommentReadabilityTest.php`：新增黑名单控制器中文注释可读�?�测试，要求类职责�?�列表关键字、可匹配字段、新增字段�?�更新字段�?�删除主键和模型写入边界均有中文说明�?
- `app/Http/Controllers/Admin/BlacklistController.php`：补齐类级中文�?�辑说明；补�? `index()`、`store()`、`update()`、`destroy()` 的中文参数说明；明确 `keyword` 同时匹配 `name`、`id_card`、`email`、`phone`；明�? `$request->all()` 写入字段�? `Blacklist` 模型 `fillable` 白名单控制�??

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\BlacklistControllerCommentReadabilityTest.php
RED: BlacklistController 缺少中文注释：后台黑名单管理控制�?
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\BlacklistControllerCommentReadabilityTest.php
OK (1 test, 12 assertions)

php -l app\Http\Controllers\Admin\BlacklistController.php
No syntax errors detected in app\Http\Controllers\Admin\BlacklistController.php

php -l tests\Feature\BlacklistControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\BlacklistControllerCommentReadabilityTest.php

旧英文标题扫�?
rg -n "Blacklist Management Controller|List all blacklist entries|Add entry to blacklist|Update blacklist entry|Delete from blacklist" app\Http\Controllers\Admin\BlacklistController.php
未发现匹配项�?
```

### 验证边界

本轮没有改动黑名单查询�?�新增�?�更新�?�删除�?�辑，也没有调整 `Blacklist` 模型可写字段，只补齐中文逻辑注释。真�? DB 恢复后，仍需在后台黑名单页面验证关键字搜索�?�新增�?�更新�?�删除，以及模型 `fillable` 与页面表单字段是否一致�??

## 89. 2026-06-08 出金控制器中文�?�辑注释补齐

本轮继续推进 plan.md 中�?�所有模块文件及参数必须有详细中文注释�?�的要求，重点维�? `app/Http/Controllers/Admin/WithdrawController.php`。该控制器负责后台出金申请列表�?�详情�?�标记处理中、标记完成和拒绝出金，并通过 `AdminDataScopeService` �? `withdraw_records.user_id` 做管理员数据范围限制。原文件仍保�? `Withdrawal Management Controller`、`List all withdrawal applications`、`Mark as processing`、`Mark as completed`、`Reject with reason`、`// Processing`、`// Completed`、`// Failed/Rejected` 等英文注释，�? `status`、`user_id`、`local_order_no`、`reason` 和数据范围字段缺少完整中文说明�??

### 本轮维护文件

- `tests/Feature/WithdrawControllerCommentReadabilityTest.php`：新增出金控制器中文注释可读性测试，要求类职责�?�列表筛选参数�?�详情主键�?�处理中状�?��?�完成状态�?�拒绝原因和数据范围判断字段均有中文说明�?
- `app/Http/Controllers/Admin/WithdrawController.php`：补齐类级中文�?�辑说明，明确出金状态�?�：`0=待处理`、`1=处理中`、`2=已完成`、`3=已拒绝或失败`；补�? `index()`、`show()`、`process()`、`complete()`、`reject()`、`denyWithdrawAccessIfNeeded()` 的中文参数说明；明确 `user_id` 是数据范围判断字段；清理未使用的 `Validator` 引入�?

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\WithdrawControllerCommentReadabilityTest.php
RED: WithdrawController 缺少中文注释：后台出金管理控制器
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\WithdrawControllerCommentReadabilityTest.php
OK (1 test, 16 assertions)

php -l app\Http\Controllers\Admin\WithdrawController.php
No syntax errors detected in app\Http\Controllers\Admin\WithdrawController.php

php -l tests\Feature\WithdrawControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\WithdrawControllerCommentReadabilityTest.php

旧英文状态注释扫�?
rg -n "Withdrawal Management Controller|List all withdrawal applications|Get withdrawal detail|Mark as processing|Mark as completed|Reject with reason|Processing|Completed|Failed/Rejected" app\Http\Controllers\Admin\WithdrawController.php
未发现匹配项�?
```

### 验证边界

本轮没有改动出金列表、详情�?�处理中、完成�?�拒绝或数据范围判断逻辑，只补齐中文逻辑注释并清理未使用引入。真�? DB 恢复后，仍需用不同角色后台管理员验证出金列表、详情�?�处理�?�完成和拒绝接口的数据范围限制，以及 `status` 状�?�流转是否符合真实业务流程�??

## 90. 2026-06-08 凭证审核控制器中文�?�辑注释补齐

本轮继续推进 plan.md 中�?�所有模块文件及参数必须有详细中文注释和逻辑注释”的要求，重点维�? `app/Http/Controllers/Admin/VoucherController.php`。该控制器负责后台凭证提交列表�?�审核�?�过和审核拒绝，真实数据来源�? `voucher_infos` 表，审核状�?�由 `review_status` 字段表达。原文件仍保�? `Voucher Management Controller`、`List voucher submissions`、`Approve voucher`、`Reject voucher` 等英文标题注释，并且 `review_status`、`id`、`reason`、拒绝原因写入边界缺少完整中文说明�??

### 本轮维护文件

- `tests/Feature/VoucherControllerCommentReadabilityTest.php`：新增凭证审核控制器中文注释可读性测试，要求类职责�?�列表筛选参数�?�审核状态�?��?�审核�?�过参数、审核拒绝参数�?�拒绝原因保存字段和旧英文标题清理均有可验证约束�?
- `app/Http/Controllers/Admin/VoucherController.php`：补齐类级中文�?�辑说明，明�? `review_status` 状�?��?�为 `0=待审核，1=审核通过�?2=审核拒绝`；补�? `index()`、`approve()`、`reject()` 的中文参数说明；明确 `id` 表示 `voucher_infos.id`，`reason` 表示审核拒绝原因并写�? `voucher_infos.review_message`；清理未使用�? `Validator` 引入�?

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\VoucherControllerCommentReadabilityTest.php
RED: VoucherController 缺少中文注释：后台凭证管理控制器
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\VoucherControllerCommentReadabilityTest.php
OK (1 test, 10 assertions)

php -l app\Http\Controllers\Admin\VoucherController.php
No syntax errors detected in app\Http\Controllers\Admin\VoucherController.php

php -l tests\Feature\VoucherControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\VoucherControllerCommentReadabilityTest.php

旧英文标题扫描：rg -n "Voucher Management Controller|List voucher submissions|Approve voucher|Reject voucher" app\Http\Controllers\Admin\VoucherController.php
未发现匹配项�?
```

### 验证边界

本轮没有改动凭证列表查询、审核�?�过、审核拒绝或拒绝原因保存的业务�?�辑，只补齐中文逻辑注释并清理未使用引入。真�? DB 恢复后，仍需在后台凭证审核页面验证凭证列表筛选�?�审核�?�过、审核拒绝�?�`review_status=2` 表示审核拒绝，以�? `reason` 是否正确保存�? `voucher_infos.review_message`�?

## 91. 2026-06-08 新闻公告控制器中文�?�辑注释补齐

本轮继续推进 plan.md 中�?�所有模块文件及参数必须有详细中文注释和逻辑注释”的要求，重点维�? `app/Http/Controllers/Admin/NewsController.php`。该控制器负责后台新闻公告的列表查询、新增�?�更新�?�删除和发布状�?�切换，数据来源�? `news` 表，响应文案继续使用 `admin` 语言包保证后端多语言。原文件仍保�? `News and Announcement Controller`、`List all news`、`Create news`、`Update news`、`Delete news`、`Toggle publish status` 等旧英文标题注释，并�? `title`、`content`、`page`、`per_page`、`id`、`is_published` 的业务含义说明不完整�?

### 本轮维护文件

- `tests/Feature/NewsControllerCommentReadabilityTest.php`：新增新闻公告控制器中文注释可读性测试，要求类职责�?�列表筛选参数�?�创�?/更新字段、删除主键�?�发布状态字段�?�真实表来源和旧英文标题清理均有可验证约束�??
- `app/Http/Controllers/Admin/NewsController.php`：补齐类级中文�?�辑说明，明确数据来源为 `news` 表；补齐 `index()`、`store()`、`update()`、`destroy()`、`togglePublish()` 的中文参数说明；明确 `title` 对应 `news.title`，`content` 对应 `news.content`，`id` 对应 `news.id`，`is_published` 表示是否发布�?

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\NewsControllerCommentReadabilityTest.php
RED: NewsController 缺少中文注释：后台新闻公告控制器
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\NewsControllerCommentReadabilityTest.php
OK (1 test, 15 assertions)

php -l app\Http\Controllers\Admin\NewsController.php
No syntax errors detected in app\Http\Controllers\Admin\NewsController.php

php -l tests\Feature\NewsControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\NewsControllerCommentReadabilityTest.php

旧英文标题扫描：rg -n "News and Announcement Controller|List all news|Create news|Update news|Delete news|Toggle publish status" app\Http\Controllers\Admin\NewsController.php
未发现匹配项�?
```

### 验证边界

本轮没有改动新闻公告列表、新增�?�更新�?�删除或发布状�?�切换业务�?�辑，只补齐中文逻辑注释。真�? DB 恢复后，仍需在后台新闻公告页面验证标题筛选�?�新增公告�?�更新公告�?�删除公告�?�发�?/取消发布，以�? `is_published` 状�?�取反后前后台公告展示是否符合真实业务预期�??

## 92. 2026-06-08 组别配置控制器中文�?�辑注释补齐

本轮继续推进 plan.md 中�?�所有模块文件及参数必须有详细中文注释和逻辑注释”的要求，重点维�? `app/Http/Controllers/Admin/GroupConfigController.php`。该控制器负责后台组别配置列表�?�新增�?�详情�?�更新和删除，数据来源为 `group_configs` 表，后台 Layui 页面提交�? `group_name` 会在控制器内映射为真实入库字�? `name`。原文件仍保�? `Group Configuration Controller`、`List group configurations`、`Create group configuration`、`Get group configuration detail`、`Update group configuration`、`Delete group configuration` 等旧英文标题注释，并�? `page`、`per_page`、`id`、`name`、`group_name`、`radix`、`category`、`has_commission`、`is_enabled`、`is_ecn`、`is_default` 的字段含义说明不完整�?

### 本轮维护文件

- `tests/Feature/GroupConfigControllerCommentReadabilityTest.php`：新增组别配置控制器中文注释可读性测试，要求控制器职责�?�真实表来源、分页参数�?�主键参数�?�页面字段映射�?�组别分类和�?关字段均有中文说明，并清理旧英文标题�?
- `app/Http/Controllers/Admin/GroupConfigController.php`：补齐类级中文�?�辑说明，明确数据来源为 `group_configs` 表；补齐 `index()`、`store()`、`show()`、`update()`、`destroy()`、`normalizePayload()` 的中文参数说明；明确 `group_name 映射�? group_configs.name`，`category 取�?? 1=代理组�??2=用户组`，以及各�?关字段含义�??

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\GroupConfigControllerCommentReadabilityTest.php
RED: GroupConfigController 缺少中文注释：后台组别配置控制器
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\GroupConfigControllerCommentReadabilityTest.php
OK (1 test, 20 assertions)

php -l app\Http\Controllers\Admin\GroupConfigController.php
No syntax errors detected in app\Http\Controllers\Admin\GroupConfigController.php

php -l tests\Feature\GroupConfigControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\GroupConfigControllerCommentReadabilityTest.php

旧英文标题扫描：rg -n "Group Configuration Controller|List group configurations|Create group configuration|Get group configuration detail|Update group configuration|Delete group configuration" app\Http\Controllers\Admin\GroupConfigController.php
未发现匹配项�?
```

### 验证边界

本轮没有改动组别配置列表、新增�?�详情�?�更新�?�删除或 `normalizePayload()` 参数归一化业务�?�辑，只补齐中文逻辑注释。真�? DB 恢复后，仍需在后台组别配置页面验证列表分页�?�新增组别�?�编辑组别�?�删除组别�?�`group_name` �? `group_configs.name` 的映射�?�`category` 代理�?/用户组分类，以及 `has_commission`、`is_enabled`、`is_ecn`、`is_default` �?关保存是否符合真实业务预期�??

## 93. 2026-06-08 支付通道控制器中文�?�辑注释补齐

本轮继续推进 plan.md 中�?�所有模块文件及参数必须有详细中文注释和逻辑注释”的要求，重点维�? `app/Http/Controllers/Admin/PaymentChannelController.php`。该控制器负责后台支付�?�道列表、新增�?�更新�?�删除和预留启用状�?�切换，数据来源�? `payment_channels` 表�?�原文件仍保�? `Payment Channel Management Controller` 旧英文标题注释，并且 `channel_name` 到真实字�? `payment_channels.name` 的映射�?�`id` 主键含义�? `toggleEnable` 启用切换边界�?要更明确的中文说明�??

### 本轮维护文件

- `tests/Feature/PaymentChannelControllerCommentReadabilityTest.php`：新增支付�?�道控制器中文注释可读�?�测试，要求控制器职责�?�真实表来源、分页参数�?�主键参数�?��?�道名称兼容字段映射、编码�?�汇率�?�启用状态�?�扩展配置和启用切换说明均可验证�?
- `app/Http/Controllers/Admin/PaymentChannelController.php`：清理旧英文标题注释，补齐�?�后台支付�?�道管理控制器�?�类级说明；明确数据来源�? `payment_channels` 表；补充 `channel_name 映射�? payment_channels.name`、`id 表示 payment_channels.id`、`toggleEnable 用于切换支付通道启用状�?�` 等中文�?�辑说明�?

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\PaymentChannelControllerCommentReadabilityTest.php
RED: PaymentChannelController 缺少中文注释：后台支付�?�道管理控制�?

vendor\bin\phpunit tests\Feature\PaymentChannelControllerCommentReadabilityTest.php
RED: PaymentChannelController 缺少中文注释：id 表示 payment_channels.id
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\PaymentChannelControllerCommentReadabilityTest.php
OK (1 test, 15 assertions)

php -l app\Http\Controllers\Admin\PaymentChannelController.php
No syntax errors detected in app\Http\Controllers\Admin\PaymentChannelController.php

php -l tests\Feature\PaymentChannelControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\PaymentChannelControllerCommentReadabilityTest.php

旧英文标题扫描：rg -n "Payment Channel Management Controller" app\Http\Controllers\Admin\PaymentChannelController.php
未发现匹配项�?
```

### 验证边界

本轮没有改动支付通道列表、新增�?�更新�?�删除或 `toggleEnable()` 业务逻辑，只补齐中文逻辑注释。真�? DB 恢复后，仍需在后台支付�?�道页面验证列表排序、新增�?�道、编辑�?�道、删除�?�道、`channel_name` �? `payment_channels.name` 的兼容映射�?�`channel_code` 唯一校验、`exchange_rate` 数�?�校验，以及启用状�?�是否影响前台入金�?�道展示�?

## 94. 2026-06-08 管理员账号控制器中文逻辑注释补齐

本轮继续推进 plan.md 中�?�所有模块文件及参数必须有详细中文注释和逻辑注释”的要求，重点维�? `app/Http/Controllers/Admin/AdminController.php`。该控制器负责后台管理员账号列表、新增�?�更新�?�密码重置和删除，数据来源为 `admins` 表，并�?�过 `role_id` 关联 `roles.id` 参与菜单、按钮�?�接口权限和数据范围控制。原文件仍保�? `Admin User Management Controller` 旧英文标题注释，并且 `role_id`、`roles`、`admins.id`、密码留空编辑边界和 `resetPassword` 重置密码入口�?要更明确的中文说明�??

### 本轮维护文件

- `tests/Feature/AdminControllerCommentReadabilityTest.php`：新增管理员账号控制器中文注释可读�?�测试，要求控制器职责�?�真实表来源、主键参数�?�账号字段�?�密码新�?/编辑/重置边界、角色绑定字段和删除权限边界均有中文说明�?
- `app/Http/Controllers/Admin/AdminController.php`：清理旧英文标题注释，补齐�?�后台管理员账号管理控制器�?�类级说明；明确数据来源�? `admins` 表；补充 `id 表示 admins.id`、`role_id 表示绑定的后台角色`、`roles.id`、`password 留空表示编辑时保留原密码`、`resetPassword 用于重置管理员登录密码` 等中文�?�辑说明�?

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminControllerCommentReadabilityTest.php
RED: AdminController 缺少中文注释：后台管理员账号管理控制�?

vendor\bin\phpunit tests\Feature\AdminControllerCommentReadabilityTest.php
RED: AdminController 缺少中文注释：password 留空表示编辑时保留原密码
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminControllerCommentReadabilityTest.php
OK (1 test, 13 assertions)

php -l app\Http\Controllers\Admin\AdminController.php
No syntax errors detected in app\Http\Controllers\Admin\AdminController.php

php -l tests\Feature\AdminControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\AdminControllerCommentReadabilityTest.php

旧英文标题扫描：rg -n "Admin User Management Controller" app\Http\Controllers\Admin\AdminController.php
未发现匹配项�?
```

### 验证边界

本轮没有改动管理员账号列表�?�新增�?�更新�?�密码重置�?�删除或角色同步业务逻辑，只补齐中文逻辑注释。真�? DB 恢复后，仍需在后台管理员页面验证列表分页、新增管理员、编辑管理员、编辑时密码留空不覆盖旧密码、重置密码�?�删除管理员，以�? `role_id`/`roles.id` 绑定后菜单权限�?�按钮权限和数据范围是否符合真实角色配置�?

## 95. 2026-06-08 大代理控制器中文逻辑注释补齐

本轮继续推进 plan.md 中�?�所有模块的文件及参数必须有详细中文注释和�?�辑注释”的要求，重点维�? `app/Http/Controllers/Admin/BigAgentController.php`。该控制器负责后台大代理列表、新增�?�编辑和删除，数据来源为 `big_agents` 表；`username` 表示大代理登录名，`password` 表示大代理登录密码，`is_enabled` 表示大代理账号是否启用，`status` 是旧页面历史字段，仅用于兼容映射�? `is_enabled`�?

### 本轮维护文件

- `tests/Feature/BigAgentControllerCommentReadabilityTest.php`：新增大代理控制器中文注释可读�?�测试，约束控制器职责�?�真实表来源、主键参数�?�登录名、密码�?�编辑密码留空边界�?�启用状态�?�旧字段兼容�? `normalizePayload` 保存字段规范化说明�??
- `app/Http/Controllers/Admin/BigAgentController.php`：清理旧英文标题 `Big Agent Management Controller`，补齐�?�后台大代理管理控制器�?��?�数据来源为 big_agents 表�?��?�id 表示 big_agents.id”�?�password 留空表示编辑时保留原密码”�?�normalizePayload 用于规范化大代理保存字段”等精确中文逻辑说明�?

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\BigAgentControllerCommentReadabilityTest.php
RED: BigAgentController 缺少中文注释：后台大代理管理控制�?
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\BigAgentControllerCommentReadabilityTest.php
OK (1 test, 10 assertions)

php -l app\Http\Controllers\Admin\BigAgentController.php
No syntax errors detected in app\Http\Controllers\Admin\BigAgentController.php

php -l tests\Feature\BigAgentControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\BigAgentControllerCommentReadabilityTest.php

关键词复核：rg -n "Big Agent Management Controller|后台大代理管理控制器|normalizePayload 用于规范化大代理保存字段|id 表示 big_agents.id|password 留空表示编辑时保留原密码" app\Http\Controllers\Admin\BigAgentController.php tests\Feature\BigAgentControllerCommentReadabilityTest.php
生产控制器已包含本轮要求的中文短句，旧英文标题只保留在测试黑名单断言中�??
```

### 验证边界

本轮没有改动大代理列表�?�新增�?�更新�?�删除或 `normalizePayload()` 业务逻辑，只补齐中文逻辑注释。真�? DB 恢复后，仍需在后台大代理页面验证列表分页、新增大代理、编辑大代理、删除大代理、编辑时密码留空保留旧密码�?�`is_enabled` 影响前台大代理登录，以及旧页面提�? `status` 时是否正确兼容映射到 `big_agents.is_enabled`�?

## 96. 2026-06-08 代理控制器中文�?�辑注释补齐

本轮继续推进 plan.md 中�?�所有模块文件及参数必须有详细中文注释�?�的要求，重点维�? `app/Http/Controllers/Admin/AgentController.php`。该控制器负责后台代理列表�?�代理详情�?�下级关系�?�代理等级更新和代理佣金比例更新，数据来源为 `user_infos` 表，其中 `account_type=1` 表示代理账号；同时�?�过 `AdminDataScopeService` 对不同管理员的数据查看范围做二次限制�?

### 本轮维护文件

- `tests/Feature/AgentControllerCommentReadabilityTest.php`：新增代理控制器中文注释可读性测试，约束代理数据来源、筛选参数�?�层级关系�?�等级字段�?�佣金字段和数据范围鉴权说明，且禁止保留旧英文标题注释�??
- `app/Http/Controllers/Admin/AgentController.php`：清�? `Agent Management Controller`、`List agents only`、`Get agent detail with hierarchy info`、`Get all direct/indirect sub-agents and customers`、`Update agent level`、`Update agent commission rate` 等旧英文标题；补�? `agent_id`、`user_name`、`level`、`comm_rate`、`AgentDescendant`、`denyAgentAccessIfNeeded` 的中文参数和业务边界说明�?

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AgentControllerCommentReadabilityTest.php
RED: AgentController 缺少中文注释：后台代理管理控制器
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AgentControllerCommentReadabilityTest.php
OK (1 test, 17 assertions)

php -l app\Http\Controllers\Admin\AgentController.php
No syntax errors detected in app\Http\Controllers\Admin\AgentController.php

php -l tests\Feature\AgentControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\AgentControllerCommentReadabilityTest.php

关键词复核：rg -n "Agent Management Controller|List agents only|Get agent detail with hierarchy info|Get all direct/indirect sub-agents and customers|Update agent level|Update agent commission rate|后台代理管理控制器|AdminDataScopeService 用于限制不同管理员可查看的代理数据范围|comm_rate 表示代理佣金比例" app\Http\Controllers\Admin\AgentController.php tests\Feature\AgentControllerCommentReadabilityTest.php
生产控制器已包含本轮要求的中文短句，旧英文标题只保留在测试黑名单断言中�??
```

### 验证边界

本轮没有改动代理列表、代理详情�?�下级关系�?�代理等级更新�?�代理佣金比例更新或数据范围判断业务逻辑，只补齐中文逻辑注释并修正注释可读�?��?�真�? DB 恢复后，仍需在后台代理管理页面验证代理列表分页�?�`agent_id` 精确筛�?��?�`user_name` 模糊筛�?��?�不同管理员数据范围隔离、代理详情查看�?�下级关系展�?、代理等级更新和佣金比例更新是否符合真实角色与代理链路配置�??

## 97. 2026-06-08 入金控制器中文�?�辑注释补齐

本轮继续推进 plan.md 中�?�后端必须支持多语言”和“所有模块文件及参数必须有详细中文注释�?�的要求，重点维�? `app/Http/Controllers/Admin/DepositController.php`。该控制器负责后台入金记录列表�?�入金详情�?�审核�?�过、审核驳回和旧入金导入占位入口，数据来源�? `deposit_records` 表；入金审核属于资金敏感操作，因此必须明�? `status`、`user_id`、`local_order_no`、`id`、审核�?�过/驳回状�?�码和数据范围鉴权边界�??

### 本轮维护文件

- `tests/Feature/DepositControllerCommentReadabilityTest.php`：新增入金控制器中文注释可读性测试，约束列表筛�?�参数�?�详情主键�?�审核�?�过、审核驳回�?�状态码、驳回原因和数据范围判断说明，且禁止保留旧英文标题注释�??
- `app/Http/Controllers/Admin/DepositController.php`：清�? `Deposit Management Controller`、`List all deposit records`、`Get deposit detail`、`Approve deposit`、`Reject deposit`、`Further logic to update user balance can be added here`、`Failed/Rejected` 等旧英文注释；补�? `status 表示入金审核状�?�`、`status=02 表示入金已审核�?�过`、`status=09 表示入金审核驳回或失败`、`payment_time`、`reason` �? `denyDepositAccessIfNeeded` 的中文�?�辑说明�?

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\DepositControllerCommentReadabilityTest.php
RED: DepositController 缺少中文注释：后台入金管理控制器
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\DepositControllerCommentReadabilityTest.php
OK (1 test, 20 assertions)

vendor\bin\phpunit tests\Feature\DepositControllerImportLocalizationTest.php
OK (3 tests, 11 assertions)

php -l app\Http\Controllers\Admin\DepositController.php
No syntax errors detected in app\Http\Controllers\Admin\DepositController.php

php -l tests\Feature\DepositControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\DepositControllerCommentReadabilityTest.php

关键词复核：rg -n "Deposit Management Controller|List all deposit records|Get deposit detail|Approve deposit|Further logic to update user balance can be added here|Reject deposit|Failed/Rejected|后台入金管理控制器|status=02 表示入金已审核�?�过|status=09 表示入金审核驳回或失败|denyDepositAccessIfNeeded 用于" app\Http\Controllers\Admin\DepositController.php tests\Feature\DepositControllerCommentReadabilityTest.php
生产控制器已包含本轮要求的中文短句，旧英文标题只保留在测试黑名单断言中�??
```

### 验证边界

本轮没有改动入金列表、详情�?�审核�?�过、审核驳回�?�旧导入占位入口或数据范围判断业务�?�辑，只补齐中文逻辑注释并保持旧导入占位接口继续从语�?包读取响应�?�真�? DB 恢复后，仍需在后台入金审核页面验证列表分页�?�`status` 筛�?��?�`user_id` 筛�?��?�`local_order_no` 模糊筛�?��?�详情查看�?�审核�?�过写入 `status=02` �? `payment_time`、审核驳回写�? `status=09` �? `remarks`，以及不同管理员在数据范围配置下的数据隔离和按钮权限�?

## 98. 2026-06-08 前台认证注册验证码多语言补齐

本轮继续推进 plan.md 中�?�后端也必须支持多语�?”的要求，重点维�? `app/Http/Controllers/Front/AuthController.php` 的注册验证码链路。该控制器原先在注册图形验证码错误�?�邮箱验证码错误、注册预�?查兜底�?�邮箱验证码发�?�频率限制�?�邮件发送失败，以及验证码邮件标�?/正文中直接写入英文文案，导致前台 Layui/Blade 页面切换语言后接口提示仍可能显示硬编码英文�??

### 本轮维护文件

- `tests/Feature/FrontAuthControllerLocalizationTest.php`：新增前台认证控制器多语�?测试，约�? `Invalid captcha`、`Invalid email verification code`、`Validation failed`、`Please request the email code later`、`Email send failed`、邮件标题和邮件正文不再硬编码在控制器中，并要求中英文语�?包存在对�? key�?
- `app/Http/Controllers/Front/AuthController.php`：将注册验证码错误改�? `auth.invalid_captcha`，邮箱验证码错误改为 `auth.invalid_email_code`，注册预�?查兜底改�? `response.validation_failed`，频率限制改�? `response.rate_limited`，邮件发送失败改�? `response.email_send_failed`，验证码邮件标题和正文改�? `auth.registration_verification_mail_subject` �? `auth.registration_verification_mail_body`�?
- `resources/lang/zh-CN/auth.php`：新�? `id_card_exists`、`invalid_captcha`、`invalid_email_code`、`registration_verification_mail_subject`、`registration_verification_mail_body`�?
- `resources/lang/en/auth.php`：新增同名英文语�? key�?

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontAuthControllerLocalizationTest.php
RED:
- Front AuthController 仍存在硬编码英文文案：Invalid captcha
- zh-CN/auth.php 缺少语言 key：invalid_captcha
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\FrontAuthControllerLocalizationTest.php
OK (2 tests, 36 assertions)

php -l app\Http\Controllers\Front\AuthController.php
No syntax errors detected in app\Http\Controllers\Front\AuthController.php

php -l tests\Feature\FrontAuthControllerLocalizationTest.php
No syntax errors detected in tests\Feature\FrontAuthControllerLocalizationTest.php

php -l resources\lang\zh-CN\auth.php
No syntax errors detected in resources\lang\zh-CN\auth.php

php -l resources\lang\en\auth.php
No syntax errors detected in resources\lang\en\auth.php

关键词复核：rg -n "Invalid captcha|Invalid email verification code|Validation failed|Please request the email code later|Email send failed|Your registration verification code is:|Registration verification code|auth\.invalid_captcha|auth\.registration_verification_mail_body" app\Http\Controllers\Front\AuthController.php resources\lang\zh-CN\auth.php resources\lang\en\auth.php tests\Feature\FrontAuthControllerLocalizationTest.php
生产控制器已改为语言�? key；硬编码英文仅保留在测试黑名单和英文语言包翻译�?�中�?
```

### 验证边界

本轮没有改动注册校验、验证码缓存、邮箱验证码缓存、发送频率限制或邮件发�?�业务�?�辑，只替换响应文案和邮件文案来源�?�真�? SMTP 与真�? DB 恢复后，仍需在前台注册页验证图形验证码错误�?�邮箱验证码错误、重复邮�?/手机�?/证件号预�?查�?�频率限制�?�邮件发送失败和邮件正文�? `zh-CN` �? `en` 语言环境下均能显示正确文案�??

## 99. 2026-06-08 前台认证控制器中文�?�辑注释补齐

本轮继续推进 plan.md 中�?�所有模块的文件及参数必须有详细中文注释和�?�辑注释包括参数的注释及功能作用”的要求，重点维�? `app/Http/Controllers/Front/AuthController.php`。该控制器承担前台登录�?�注册�?�旧登录注册兼容、验证码、邮箱验证码、注�?、刷�? Token、改密和�?请人校验等入口，属于前台代理商和普�?�客户登录后的权限菜单链路前置入口�?�原文件仍保�? `Front User Authentication Controller`、`Show login page`、`User Login`、`Refresh Token`、`Change Password` 等英文标题注释，并且 `account_type`、`captcha_key`、`captcha_code`、`email_code`、`loginUid`、`loginPassword` 等参数缺少成体系的中文业务说明�??

### 本轮维护文件

- `tests/Feature/FrontAuthControllerCommentReadabilityTest.php`：新增前台认证控制器中文注释可读性测试，静�?�读取源码，不连接真实数据库；约束登录�?�注册�?�旧接口兼容、验证码、邮箱验证码�? Token 相关参数必须保留中文逻辑说明，并禁止保留旧英文标题注释�??
- `app/Http/Controllers/Front/AuthController.php`：补齐类级�?�属性�?�构造函数�?�登录页、注册页、旧注册链接、注册提交�?�新版登录�?�旧版登录�?�注�?、刷�? Token、改密�?�邀请人校验、邮箱检查�?�图形验证码、注册预�?查�?�邮箱验证码发�?��?�字段标准化、验证码缓存键等中文逻辑注释；明�? `registrationService`、`jwtService`、`account_type=1`、`account_type=2`、`captcha_key`、`captcha_code`、`email_code`、`loginUid`、`loginPassword` 等参数含义�??
- `app/Http/Controllers/Front/AuthController.php`：删�? `showLogin()` �? `showRegister()` 中重复的不可�? `return view(...)`，不改变实际返回页面，只清理注释补齐过程中发现的死代码�??

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontAuthControllerCommentReadabilityTest.php
RED:
- Front AuthController 缺少中文逻辑注释：处理前台用户登录�?�注册�?�注�?、令牌刷�?
- Front AuthController 仍残留旧英文注释标题：Front User Authentication Controller
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\FrontAuthControllerCommentReadabilityTest.php
OK (2 tests, 34 assertions)

vendor\bin\phpunit tests\Feature\FrontAuthControllerLocalizationTest.php
OK (2 tests, 36 assertions)

php -l app\Http\Controllers\Front\AuthController.php
No syntax errors detected in app\Http\Controllers\Front\AuthController.php

php -l tests\Feature\FrontAuthControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\FrontAuthControllerCommentReadabilityTest.php
```

### 验证边界

本轮没有改动前台注册、登录�?�旧登录兼容、验证码缓存、邮箱验证码缓存、邮箱发送�?�JWT 签发、SSO、邀请人规则或密码修改业务�?�辑，只补齐中文逻辑注释并清理两个不可达重复 return。真�? DB、SMTP 和浏览器环境恢复后，仍需用代理账号和普�?�客户账号分别登�? Layui 前台，验证登录后 `POST /api/front/navigation/menus` 能按 `user_logins.role_id` 返回代理/客户菜单树，并验证注册页图形验证码�?�邮箱验证码发�?��?�旧登录接口和新版登录接口在 `zh-CN` �? `en` 语言下的提示与跳转行为�??

## 100. 2026-06-08 前台账户控制器中文�?�辑注释补齐

本轮继续推进 plan.md 中�?�所有模块的文件及参数必须有详细中文注释和�?�辑注释包括参数的注释及功能作用”的要求，重点维�? `app/Http/Controllers/Front/AccountController.php`。该控制器承载前台账户综合�?�账户余额�?�凭证提交�?�旧凭证上传、旧账户类型切换和凭证列表接口，是代理商和普通客户登录后账户菜单页面的核心数据来源�?�原文件仍保�? `Front Account Management Controller`、`Get current user account info`、`Get detailed balance breakdown`、`Upload voucher images for review`、`List submitted vouchers` 等英文标题注释，并且 `total_funds`、`equity`、`used_margin`、`avail_margin`、`comm_rate`、`voucherimg`、`is_enc`、`review_status` 等参数缺少完整中文业务说明�??

### 本轮维护文件

- `tests/Feature/FrontAccountControllerCommentReadabilityTest.php`：新增前台账户控制器中文注释可读性测试，静�?�读取源码，不连接真实数据库；约束账户概览�?�余额�?�凭证�?�旧接口兼容和账户类型切换参数必须保留中文�?�辑说明，并禁止保留旧英文标题注释�??
- `app/Http/Controllers/Front/AccountController.php`：补齐类级�?�账户综合�?�余额明细�?�当前用户解析�?�账户指标组装�?�客户�?�别统计、新版凭证提交�?�旧凭证上传、旧账户类型切换、凭证列表�?�旧成功响应和旧失败响应的中文�?�辑注释�?
- `app/Http/Controllers/Front/AccountController.php`：明�? `user_id`、`total_funds`、`equity`、`used_margin`、`avail_margin`、`comm_rate`、`images`、`remarks`、`voucherimg`、`voucherremark`、`is_enc`、`review_status`、`msg`、`err`、`col` 等参数含义�??

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontAccountControllerCommentReadabilityTest.php
RED:
- Front AccountController 缺少中文逻辑注释：处理账户信息�?�余额明细�?�凭证提交和旧前台账户接口兼�?
- Front AccountController 仍残留旧英文注释标题：Front Account Management Controller
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\FrontAccountControllerCommentReadabilityTest.php
OK (2 tests, 32 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_account_overview_exposes_comparison_table_group_name_rebate_and_gender_profile
OK (1 test, 21 assertions)

php -l app\Http\Controllers\Front\AccountController.php
No syntax errors detected in app\Http\Controllers\Front\AccountController.php

php -l tests\Feature\FrontAccountControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\FrontAccountControllerCommentReadabilityTest.php
```

### 验证边界

本轮没有改动账户综合、余额明细�?�凭证上传�?�旧凭证上传、账户类型切换�?�凭证列表�?�订单统计�?�客户�?�别统计或旧响应结构业务逻辑，只补齐中文逻辑注释并替换旧英文标题注释。真�? DB、文件上传和浏览器环境恢复后，仍�?分别用代理账号和普�?�客户账号进�? Layui 前台账户综合、账户余额�?�提交凭证页面，验证 `/api/front/account/profile`、`/api/front/account/balance`、`/api/front/account/vouchers`、`/api/front/account/voucher-submissions`、`user/user_voucher_save`、`user/change_account_save` 在真实数据下返回字段、上传文件�?�审核状态筛选和旧页面错误码均符合现有业务�??

## 101. 2026-06-08 前台入金控制器中文�?�辑注释补齐

本轮继续推进 plan.md 中�?�所有模块文件及参数必须有详细中文注释和逻辑注释，包括参数的注释及功能作用�?�的要求，重点维�? `app/Http/Controllers/Front/DepositController.php`。该控制器负责前台入金页初始化数据�?�新版入金申请�?�旧前台入金申请兼容、OTC 旧入口兼容�?�入金历史列表�?�支付�?�道解析、入金限额�?�入金可用状态和系统配置读取，是代理商与普�?�客户前台资金菜单的核心链路之一�?

### 本轮维护文件

- `tests/Feature/FrontDepositControllerCommentReadabilityTest.php`：新增前台入金控制器中文注释可读性测试，静�?�读取源码，不连接真实数据库；约束入金页面�?�入金提交�?�旧接口兼容、支付�?�道、限额�?�系统配置�?�状态码和旧英文标题注释清理�?
- `app/Http/Controllers/Front/DepositController.php`：补�? `depositPage`、`submitDeposit`、`deposit_request`、`deposit_request_otc`、`depositHistory`、`store`、`records`、`frontChannels`、`resolvePaymentChannel`、`amountLimits`、`depositAvailability`、`fallbackChannels`、`fallbackChannel`、`legacyChannelMeta`、`configValue` 的中文�?�辑注释；明�? `amount`、`deposit_amt_usd`、`channel`、`pay_channel`、`passageway`、`local_order_no`、`status=01`、`deposit_limits`、`exchange_rates`、系统开关和时间窗口的业务含义�??

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontDepositControllerCommentReadabilityTest.php
RED:
- Front DepositController 缺少中文逻辑注释：前台入金管理控制器
- Front DepositController 仍残留旧英文注释标题：Front Deposit Management Controller
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\FrontDepositControllerCommentReadabilityTest.php
OK (2 tests, 36 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_layui_deposit_payment_channels_use_layui_tabs
OK (1 test, 17 assertions)

php -l app\Http\Controllers\Front\DepositController.php
No syntax errors detected in app\Http\Controllers\Front\DepositController.php

php -l tests\Feature\FrontDepositControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\FrontDepositControllerCommentReadabilityTest.php
```

### 验证边界

本轮没有改动前台入金页面初始化�?�新版入金提交�?�旧版入金提交�?�OTC 兼容入口、入金历史�?�支付�?�道解析、限额计算�?�系统开关或时间窗口业务逻辑，只补齐中文逻辑注释并清理旧英文标题注释。真�? DB 恢复后，仍需用代理账号和普�?�客户账号分别验�? `/api/front/deposits/form-options`、`/api/front/deposits/submissions`、`/api/front/deposits/history`、`user/deposit_request`、`user/deposit_request_otc`，确认支付�?�道来自 `payment_channels` 或兼容配置�?�`status=01` 待审核记录写入正确�?�`local_order_no` 唯一、入金限额和系统�?关生效，以及前台 Layui 入金页支付�?�道 Tab 渲染和历史列表字段均符合真实业务数据�?

## 102. 2026-06-08 前台出金控制器中文�?�辑注释补齐

本轮继续推进 plan.md 中�?�所有模块文件及参数必须有详细中文注释和逻辑注释，包括参数的注释及功能作用�?�的要求，重点维�? `app/Http/Controllers/Front/WithdrawController.php`。该控制器负责前台出金页初始化数据�?�新版出金申请�?�旧前台出金申请兼容、OTC 旧入口兼容�?�出金历史列表�?�出金手续费、可出金金额和出金可用�?�判断，是代理商与普通客户前台资金菜单的核心链路之一�?

### 本轮维护文件

- `tests/Feature/FrontWithdrawControllerCommentReadabilityTest.php`：新增前台出金控制器中文注释可读性测试，静�?�读取源码，不连接真实数据库；约束出金页面�?�出金提交�?�旧接口兼容、OTC 入口、出金历史�?�手续费、银行卡、状态码、可出金余额和时间窗口说明必须存在�??
- `app/Http/Controllers/Front/WithdrawController.php`：清�? `Front Withdraw Management Controller`、`Get withdraw page data (bank info, rates, limits)`、`Submit withdrawal request`、`List withdrawal records`、`Legacy method for store`、`Legacy method for records`、`Check Risk Ratio`、`Calculate fee`、`Pending` 等旧英文标题或短注释；补�? `withdrawPage`、`submitWithdraw`、`withdraw_request`、`withdraw_request_OTC`、`withdrawHistory`、`store`、`records`、`withdrawDisplayOrderNo`、`withdrawSourceText`、`withdrawableAmount`、`withdrawAvailability`、`isNowInTimeWindow` 的中文�?�辑注释�?
- `app/Http/Controllers/Front/WithdrawController.php`：明�? `amount`、`withdraw_amt`、`password`、`withdraw_password`、`withdraw_psw`、`agree`、`bank_no`、`withdraw_limits`、`fee`、`status=0`、`local_order_no`、`applystatus`、`total_funds`、`avail_margin`、`auth_status=1`、`withdrawal_enabled`、`withdrawal_weekend_enabled` 和出金时间窗口的业务含义�?

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontWithdrawControllerCommentReadabilityTest.php
RED:
- Front WithdrawController 缺少中文逻辑注释：处理出金页面配置�?�出金申请�?�旧前台出金接口兼容和出金历史记�?
- Front WithdrawController 仍残留旧英文注释标题：Front Withdraw Management Controller
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\FrontWithdrawControllerCommentReadabilityTest.php
OK (2 tests, 31 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_withdraw_order_number_and_type_fields_do_not_reuse_reject_reason_or_status
OK (1 test, 29 assertions)

php -l app\Http\Controllers\Front\WithdrawController.php
No syntax errors detected in app\Http\Controllers\Front\WithdrawController.php

php -l tests\Feature\FrontWithdrawControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\FrontWithdrawControllerCommentReadabilityTest.php

关键词复核：rg -n "Front Withdraw Management Controller|Handles withdrawal page data|Get withdraw page data|Submit withdrawal request|List withdrawal records|Legacy method for store|Legacy method for records|Check Risk Ratio|Calculate fee|Pending|前台出金管理控制器|withdrawPage 用于返回前台出金页初始化数据|status=0 表示出金申请待后台审�?" app\Http\Controllers\Front\WithdrawController.php tests\Feature\FrontWithdrawControllerCommentReadabilityTest.php
生产控制器已包含本轮要求的中文短语，旧英文标题只保留在测试黑名单断言中�??
```

### 验证边界

本轮没有改动前台出金页面初始化�?�新版出金提交�?�旧版出金提交�?�OTC 兼容入口、出金历史�?�手续费计算、持仓风险检查�?�可出金余额、实名状态检查�?�系统开关或时间窗口业务逻辑，只补齐中文逻辑注释并清理旧英文标题注释。真�? DB 恢复后，仍需用代理账号和普�?�客户账号分别验�? `/api/front/withdrawals/form-options`、`/api/front/withdrawals/submissions`、`/api/front/withdrawals/history`、`user/withdraw_request`、`user/withdraw_request_OTC`，确认银行卡信息来自 `user_auths`、`status=0` 待审核记录写入正确�?�`local_order_no` 唯一、固定手续费和比例手续费计算正确、金额上下限和系统开关生效，以及前台 Layui 出金页表单�?�历史列表和旧表格字段均符合真实业务数据�?

## 103. 2026-06-08 前台订单控制器中文�?�辑注释补齐

本轮继续推进 plan.md 中�?�所有模块文件及参数必须有详细中文注释和逻辑注释，包括参数的注释及功能作用�?�的要求，重点维�? `app/Http/Controllers/Front/OrderController.php`。该控制器负责前台当前持仓订单�?�历史平仓订单�?�旧前台订单搜索入口、旧前台订单详情弹层、订单所属用户资料�?�代理链路和订单返佣明细展示，是代理商与普�?�客户前台交易菜单的核心链路之一�?

### 本轮维护文件

- `tests/Feature/FrontOrderControllerCommentReadabilityTest.php`：新增前台订单控制器中文注释可读性测试，静�?�读取源码，不连接真实数据库；约束持仓订单�?�历史订单�?�旧搜索入口、订单详情�?�订单号筛�?��?�交易品种筛选�?�强平筛选�?�代理链路和返佣明细说明必须存在�?
- `app/Http/Controllers/Front/OrderController.php`：清�? `Front Order Management Controller`、`Handles open and closed trading orders for users.`、`List current open orders`、`List historical closed orders` 等旧英文标题注释；补�? `openOrders`、`openOrderSearch`、`openOrder2Search`、`closedOrders`、`closeOrderSearch`、`closeOrderSearchV2`、`closeOrder2Search`、`openOrderDetail`、`closeOrderDetail`、`userDetail`、`orderChain`、`legacyOrderDetailHtml`、`legacyOrderChainHtml`、`legacyCommissionDetailsHtml`、`legacyDetailItem` 的中文�?�辑注释�?
- `app/Http/Controllers/Front/OrderController.php`：明�? `orderId`、`ticket`、`symbol`、`date_from`、`date_to`、`open_time`、`close_time`、`is_coercion`、`reason`、`commission_details`、`account_type=1`、`account_type=2`、`family_tree`、`viewerAgentId`、`orderType`、`role`、`title` 等字段和参数的业务含义�??

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontOrderControllerCommentReadabilityTest.php
RED:
- Front OrderController 缺少中文逻辑注释：处理当前持仓订单�?�历史平仓订单�?�旧前台订单搜索入口和订单详情弹�?
- Front OrderController 仍残留旧英文注释标题：Front Order Management Controller
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\FrontOrderControllerCommentReadabilityTest.php
OK (2 tests, 21 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_order_lists_use_ticket_and_user_id_links_without_duplicate_detail_buttons
OK (1 test, 40 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_front_open_and_closed_trade_rules_reuse_user_trade_scopes
OK (1 test, 20 assertions)

php -l app\Http\Controllers\Front\OrderController.php
No syntax errors detected in app\Http\Controllers\Front\OrderController.php

php -l tests\Feature\FrontOrderControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\FrontOrderControllerCommentReadabilityTest.php

关键词复核：rg -n "Front Order Management Controller|Handles open and closed trading orders|List current open orders|List historical closed orders|前台订单管理控制器|openOrders 用于返回当前用户可见的持仓订单列表|closedOrders 用于返回当前用户可见的历史平仓订单列表|orderChain 用于�? family_tree" app\Http\Controllers\Front\OrderController.php tests\Feature\FrontOrderControllerCommentReadabilityTest.php
生产控制器已包含本轮要求的中文短语，旧英文标题只保留在测试黑名单断言中�??
```

### 验证边界

本轮没有改动前台持仓订单查询、历史订单查询�?�旧搜索入口、订单详�? HTML、代理链路�?�返佣明细�?�数据可见范围过滤或分页汇�?�业务�?�辑，只补齐中文逻辑注释并清理旧英文标题注释。真�? DB 恢复后，仍需用代理账号和普�?�客户账号分别验�? `/api/front/orders/open`、`/api/front/orders/closed`、`user/order/openOrderSearch`、`user/order/closeOrderSearch`、旧订单详情弹层入口，确认订单号筛�?��?�交易品种筛选�?�开�?/平仓日期筛�?��?�强平筛选�?�代理树数据隔离、普通客户仅看自身订单�?�代理订单返佣明细和旧页面字段均符合真实业务数据�?

## 104. 2026-06-08 前台礼品中心控制器中文�?�辑注释补齐

本轮继续推进 plan.md 中�?�所有模块文件及参数必须有详细中文注释和逻辑注释，包括参数的注释及功能作用�?�的要求，重点维�? `app/Http/Controllers/Front/GiftController.php`。该控制器负责前台收货地�?列表、旧前台地址分页搜索、地�?新增、地�?更新、旧前台地址新增或编辑统�?入口、地�?删除、可兑换礼品列表和礼品发货历史搜索，是代理商与普通客户前台礼品中心菜单的核心链路之一�?

### 本轮维护文件

- `tests/Feature/FrontGiftControllerCommentReadabilityTest.php`：新增前台礼品中心控制器中文注释可读性测试，静�?�读取源码，不连接真实数据库；约束收货地�?字段、旧字段别名、默认地�?唯一规则、礼品列表�?�可兑换礼品、已发货礼品和旧英文标题注释清理�?
- `app/Http/Controllers/Front/GiftController.php`：清�? `Front Gift Center Controller`、`Handles user addresses and gift redemption/history.`、`List user addresses`、`Add new address`、`Update address`、`Build an update payload from the fields that were actually submitted.`、`Delete address`、`List available gifts / shipped gifts`、`Shipped gifts (from GiftShipment)`、`Available gifts (dummy list if no GiftInfo model exists)` 等旧英文标题或短注释�?
- `app/Http/Controllers/Front/GiftController.php`：补�? `addressList`、`addressSearch`、`addAddress`、`updateAddress`、`addressUpdate`、`deleteAddress`、`giftList`、`giftSearch` 的中文�?�辑注释，明�? `recipient_name`、`receiver_name`、`recipient_phone`、`phone`、`recipient_address`、`address`、`is_default`、`rec_id`、`available_gifts`、`shipped_gifts`、`gift_name`、`shipped_at`、`page/limit/per_page` 等参数或返回字段的业务含义�??

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontGiftControllerCommentReadabilityTest.php
RED:
- Front GiftController 缺少中文逻辑注释：处理收货地�?列表、地�?新增、地�?更新、地�?删除、礼品列表和礼品发货历史
- Front GiftController 仍残留旧英文注释标题：Front Gift Center Controller
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\FrontGiftControllerCommentReadabilityTest.php
OK (2 tests, 29 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_front_agent_gift_and_cancel_legacy_operations_have_resource_style_api_routes
OK (1 test, 66 assertions)

php -l app\Http\Controllers\Front\GiftController.php
No syntax errors detected in app\Http\Controllers\Front\GiftController.php

php -l tests\Feature\FrontGiftControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\FrontGiftControllerCommentReadabilityTest.php
```

### 验证边界

本轮没有改动收货地址查询、新增�?�更新�?�删除�?�旧前台地址统一保存入口、礼品可兑换列表、礼品发货历史搜索�?�分页结构�?�默认地�?唯一规则或当前用户数据隔离业务�?�辑，只补齐中文逻辑注释并清理旧英文标题注释。真�? DB 恢复后，仍需用代理账号和普�?�客户账号分别验�? `/api/front/gift-addresses`、`/api/front/gifts`、`user/address/search`、`user/address/update`、`user/gift/search`，确�? `user_addresses` �? `gift_shipments` 真实数据下的字段映射、默认地�?唯一、旧字段别名兼容、发货时间筛选�?�礼品名称筛选和前台 Layui/Naive 页面表格字段均符合现有业务�??

## 105. 2026-06-08 前台返佣控制器中文�?�辑注释补齐

本轮继续推进 plan.md 中�?�所有模块文件及参数必须有详细中文注释和逻辑注释，包括参数的注释及功能作用�?�的要求，重点维�? `app/Http/Controllers/Front/CommissionController.php`。该控制器负责前台实时返佣列表�?�旧前台实时返佣搜索、旧前台返佣详情弹层、返佣历史�?�返佣趋势统计�?��?�别维度统计、直属下级代理转账�?�项和佣金转账，是代理商前台返佣菜单和数据范围隔离的核心链路之一�?

### 本轮维护文件

- `tests/Feature/FrontCommissionControllerCommentReadabilityTest.php`：新增前台返佣控制器中文注释可读性测试，静�?�读取源码，不连接真实数据库；约束实时返佣�?�旧返佣详情、返佣历史�?�统计分析�?�转账�?�项、佣金转账和旧英文标题注释清理�??
- `app/Http/Controllers/Front/CommissionController.php`：清�? `Front Commission Management Controller`、`Handles real-time commission calculation, history, and transfers.`、`Calculate real-time commission for current agent`、`Get settled commission history`、`Commission transfer to sub-agent`、`Verify sub-agent belongs to current agent`、`Handle transfer...`、`Deduct from agent`、`Add to sub-agent`、`Receiver deposit side: DBCT...` 等旧英文标题或短注释�?
- `app/Http/Controllers/Front/CommissionController.php`：补�? `commissionService`、`realTime`、`realtimeRebateSearch`、`realtimeRebateDetail`、`userDetail`、`orderChain`、`legacyDetailItem`、`currentAgentOrderCommission`、`history`、`commissionHistoryAnalytics`、`transferAgentOptions`、`transfer` 的中文�?�辑注释，明�? `userId`、`orderId`、`detail_commission`、`current_commission_amount`、`orderNo`、`role`、`date_from`、`date_to`、`dataType`、`sub_agent_id`、`amount`、`remark`、`DBCT`、`WBCT` 等参数和字段的业务含义�??

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontCommissionControllerCommentReadabilityTest.php
RED:
- Front CommissionController 缺少中文逻辑注释：处理实时返佣计算�?�返佣历史�?�返佣统计分析�?�旧前台返佣详情和佣金转�?
- Front CommissionController 仍残留旧英文注释标题：Front Commission Management Controller
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\FrontCommissionControllerCommentReadabilityTest.php
OK (2 tests, 38 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_commission_realtime_and_history_expose_rebate_metrics_and_charts
OK (1 test, 89 assertions)

php -l app\Http\Controllers\Front\CommissionController.php
No syntax errors detected in app\Http\Controllers\Front\CommissionController.php

php -l tests\Feature\FrontCommissionControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\FrontCommissionControllerCommentReadabilityTest.php
```

### 验证边界

本轮没有改动实时返佣查询、旧前台返佣详情、返佣历史查询�?�返佣统计分析�?�直属下级代理�?�项、佣金转账�?�余额扣增�?�事务写入或数据范围业务逻辑，只补齐中文逻辑注释并清理旧英文标题注释。真�? DB 恢复后，仍需用代理账号验�? `/api/front/commissions/realtime`、`/api/front/commissions/history`、`/api/front/commissions/transfer-agent-options`、`/api/front/commissions/transfers`、`user/realtime/realtimeRebateSearch` 等接口，确认代理树范围�?�订单关闭时间筛选�?�当前代理返佣金额�?��?�级返佣明细、返佣趋势统计�?��?�别维度统计、直属代理转账�?�项、`DBCT/WBCT` 双流水和余额变更均符合真实业务数据�??

## 106. 2026-06-08 前台代理管理控制器中文�?�辑注释补齐

本轮继续推进 plan.md 中�?�所有模块文件及参数必须有详细中文注释和逻辑注释，包括参数的注释及功能作用�?�的要求，重点维�? `app/Http/Controllers/Front/AgentController.php`。该控制器负责下级代理列表�?�直属客户列表�?�直属客户佣金转账�?�代理等级�?��?��?�代理层级路径�?�直属客户明细�?�代理统计�?�用户详情�?�登录历史�?�代理等级确认�?�客户组别变更申请和旧前台兼容入口，是前台代理商菜单、按钮操作和数据范围隔离的核心链路之�?�?

### 本轮维护文件

- `tests/Feature/FrontAgentControllerCommentReadabilityTest.php`：新增前台代理管理控制器中文注释可读性测试，静�?�读取源码，不连接真实数据库；约束代理树、直属客户�?�佣金转账�?�等级确认�?�组别变更�?�用户详情和旧前台兼容入口必须有中文逻辑说明�?
- `app/Http/Controllers/Front/AgentController.php`：清�? `Front Agent Management Controller`、`Provides sub-agent and customer lists, and statistics for agents.`、`List all sub-agents (direct and indirect)`、`List all customers (direct and indirect)`、`Add hierarchy and trade stats for each agent`、`Get agent statistics`、`View/confirm agent level`、`Request customer group change`、`Verify the target exists...`、`Confirm the requested group...`、`Verify target user is descendant.`、`Base columns follow...` 等旧英文标题或短注释�?
- `app/Http/Controllers/Front/AgentController.php`：补�? `familyTreeService`、`subList`、`proxyListSearch`、`customerList`、`directCustListSearch`、`directUserCommTrans`、`getSubAgentsGrpIdList`、`getParentPath`、`directCustDetailList`、`statistics`、`userDetail`、`agentLevelDetailPayload`、`userLoginHistory`、`confirmLevel`、`confirmLevelChange`、`groupChangeList`、`groupChange`、`canViewUser`、`isDirectTransferTarget`、`availableGroupOptions` 等中文�?�辑注释�?
- `app/Http/Controllers/Front/AgentController.php`：明�? `parent_id`、`direct_only`、`descendant_type=1`、`descendant_type=2`、`available_groups`、`depositId`、`comm_money`、`password`、`DBCT`、`WBCT`、`agentGId`、`event_name`、`puid`、`agent_gId`、`target_user_id`、`new_group_id` 等旧前台和新版接口参数含义�??

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontAgentControllerCommentReadabilityTest.php
RED:
- Front AgentController 缺少中文逻辑注释：处理下级代理列表�?�直属客户列表�?�代理统计�?�等级确认�?�客户组别变更�?�用户详情和旧前台兼容入�?
- Front AgentController 仍残留旧英文注释标题：Front Agent Management Controller
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\FrontAgentControllerCommentReadabilityTest.php
OK (2 tests, 50 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_agent_counts_can_open_direct_agent_and_customer_lists
OK (1 test, 18 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_agent_level_confirm_payload_uses_backend_choice_gid_in_layui_and_naive
OK (1 test, 29 assertions)

php -l app\Http\Controllers\Front\AgentController.php
No syntax errors detected in app\Http\Controllers\Front\AgentController.php

php -l tests\Feature\FrontAgentControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\FrontAgentControllerCommentReadabilityTest.php
```

### 验证边界

本轮没有改动下级代理列表、客户列表�?�直属客户转账�?�代理等级�?��?��?�层级路径�?�直属客户明细�?�代理统计�?�用户详情�?�登录历史�?�等级确认�?�组别变更申请�?�数据范围校验或旧前台响应结构，只补齐中文�?�辑注释并清理旧英文标题注释。真�? DB 恢复后，仍需用代理账号验�? `/api/front/agents/direct`、`/api/front/agents/direct-customers`、`/api/front/agents/level-confirmation`、`/api/front/agents/level-confirmation/changes`、`/api/front/agents/group-changes`、`/api/front/customers/commission-transfers`、`/api/front/users/{user}`、`user/proxy/proxyListSearch`、`user/proxy/direct_cust_detail_list`、`user/proxy/parentPath`、`user/proxy/confirmLevelChange`、`user/cust/change/group_edit` 等接口，确认代理树权限�?�直�?/非直属筛选�?�客户组别�?��?��?�等级确认比例来源�?�旧前台字段映射�? DB 真实数据隔离均符合业务�??

## 107. 2026-06-08 前台资金流水控制器中文�?�辑注释补齐

本轮继续推进 plan.md 中�?�所有模块的文件及参数必须有详细中文注释和�?�辑注释，包括参数的注释及功能作用�?�的要求，重点维�? `app/Http/Controllers/Front/FlowController.php`。该控制器负责前台入金流水�?�出金流水�?�返佣流水聚合查询，兼容旧前台入�?/出金/出金申请/直属客户入出金搜索入口，并提供直属客户入金流�? CSV 导出和下载，是前台资金菜单�?�代理数据范围和旧项目迁移兼容的核心链路之一�?

### 本轮维护文件

- `tests/Feature/FrontFlowControllerCommentReadabilityTest.php`：新增前台资金流水控制器中文注释可读性测试，静�?�读取源码，不连接真实数据库；约�? `flow_type`、`flowType`、`date_from`、`date_to`、`deposit_records`、`withdraw_records`、`commission_records`、`local_order_no`、`third_order_no`、`flow_type_text`、`totalRow`、`withdraw_source`、直属客户导出参数�?�下载参数和旧前台搜索入口必须有中文逻辑说明�?
- `app/Http/Controllers/Front/FlowController.php`：清�? `Front Account Flow Controller`、`Lists all account transactions including deposits, withdrawals, and commissions.`、`List all account transactions (deposits, withdrawals, commissions)`、`Query deposits`、`Query withdrawals`、`Query commissions.`、`Combine and paginate`、`commission_records uses the rebuilt schema field commission_amount`、`All three source tables use integer timestamps`、`Assume 02 is completed` 等旧英文标题或短注释�?
- `app/Http/Controllers/Front/FlowController.php`：补�? `accountFlow`、`typedFlow`、`applyWithdrawSourceFilter`、`depositExport`、`downloadFile`、`depositFlowSearch`、`withdrawalFlowSearch`、`withdrawApplyFlowSearch`、`directDepositFlowSearch`、`directWithdrawalFlowSearch`、`legacyTypedFlow`、`legacyCurrentUserId`、`withdrawDisplayOrderNo`、`withdrawSourceText`、`flowScopeUserIds` 的中文功能说明�?�参数含义和数据边界说明�?

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontFlowControllerCommentReadabilityTest.php
RED:
- Front FlowController 缺少中文逻辑注释：处理入金流水�?�出金流水�?�返佣流水�?�直属客户流水�?�直属代理流水�?�旧前台流水搜索和导出下�?
- Front FlowController 仍残留旧英文注释标题：Front Account Flow Controller
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\FrontFlowControllerCommentReadabilityTest.php
OK (2 tests, 46 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_layui_flow_tabs_use_backend_routes_and_summary_above_every_table
OK (1 test, 25 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_layui_flow_filters_use_dropdown_for_withdraw_source_and_no_remark_filter
OK (1 test, 7 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_naive_flow_tabs_keep_each_tab_request_cache_and_backend_data_isolated
OK (1 test, 11 assertions)

php -l app\Http\Controllers\Front\FlowController.php
No syntax errors detected in app\Http\Controllers\Front\FlowController.php

php -l tests\Feature\FrontFlowControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\FrontFlowControllerCommentReadabilityTest.php
```

### 相关接口消息

- `POST /api/front/flows/account`：新版账户流水聚合入口，`flow_type=all` 时聚�? `deposit_records`、`withdraw_records`、`commission_records`，非 all 时按类型分流到单类流水查询�??
- `POST /user/flow/depositFlowSearch`：旧前台入金流水搜索入口，内部写�? `flow_type=deposit` 后复�? `accountFlow`�?
- `POST /user/flow/withdrawalFlowSearch`：旧前台出金流水搜索入口，内部写�? `flow_type=withdraw` 后复�? `accountFlow`�?
- `POST /user/flow/withdrawApplyFlowSearch`：旧前台出金申请流水搜索入口，内部写�? `flow_type=withdraw_apply` 后复�? `accountFlow`�?
- `POST /user/flow/directDepositFlowSearch`：旧前台直属客户入金流水搜索入口，内部写�? `flow_type=direct_deposit` 后复�? `accountFlow`�?
- `POST /user/flow/directWithdrawalFlowSearch`：旧前台直属客户出金流水搜索入口，内部写�? `flow_type=direct_withdraw` 后复�? `accountFlow`�?
- `GET /user/flow/directDepositExport` 与下载路由：继续通过 `depositExport` 生成直属客户入金 CSV，�?�过 `downloadFile` 下载 `storage/app/front_exports` 下的安全文件名�??

### 验证边界

本轮没有改动入金流水查询、出金流水查询�?�返佣流水查询�?�直属客�?/直属代理可见范围、日期筛选�?�出金来源筛选�?�分页汇总�?�CSV 导出或下载业务�?�辑，只补齐中文逻辑注释并清理旧英文标题注释。当前本机真�? MySQL `127.0.0.1:3307` 仍未连接验证，因此未声明真实 DB 数据已经覆盖；真�? DB 恢复后，仍需�? `agent@test.com / agent123` 登录前台 Layui �? Naive，分别验�? `/api/front/flows/account`、旧前台流水搜索接口、直属客户入金导出�?�`withdraw_source=bank_transfer/crypto_currency`、`date_from/date_to`、`direct_deposit_userId`、`direct_deposit_id` 等真实数据筛选结果和菜单权限数据隔离�?

## 108. 2026-06-08 前台�?户申请控制器中文逻辑注释补齐与礼品列�? GET 路由对齐

本轮继续推进 plan.md 中�?�所有模块的文件及参数必须有详细中文注释和�?�辑注释，包括参数的注释及功能作用�?�的要求，重点维�? `app/Http/Controllers/Front/CancelController.php`。该控制器负责当前前台用户提交销户申请�?�旧前台 `ajaxCancelAccount` 兼容入口和最近一次销户申请状态查询；提交前会校验重复待审申请、未平仓订单、账户�?�资金和账户�?值，是前台账户注�?页面与后台注�?审核页面之间的关键链路�??

### 本轮维护文件

- `tests/Feature/FrontCancelControllerCommentReadabilityTest.php`：新增前台销户申请控制器中文注释可读性测试，静�?�读取源码，不连接真实数据库；约�? `reason`、`cancel_applies`、`status=0`、`cancel_remark`、`reject_reason`、重复待审校验�?�`UserTrade::open`、`total_funds`、`equity`、`cancelRemark`、`remark` 和最近一次状态查询必须有中文逻辑说明�?
- `app/Http/Controllers/Front/CancelController.php`：清�? `Front account cancellation controller.`、`This controller rebuilds the old front-office cancellation workflow:`、`Submit an account cancellation request for the current front user.`、`Prevent duplicate pending requests.`、`Open orders must be closed before cancellation`、`Compatibility fallback...` 等旧英文标题或短注释�?
- `routes/front.php`：将礼品列表接口�? `POST /api/front/gifts` 对齐�? `GET /api/front/gifts`，与 Layui 礼品列表页面、Naive 礼品模块和现有回归测试的资源风格接口约束�?致�??
- `resources/front/layui/gift/list.blade.php`：补�? `'method' => 'GET'`，确�? Layui 礼品列表页面�? GET 请求 `/api/front/gifts`�?

### 本轮 TDD 与调试记�?

```text
vendor\bin\phpunit tests\Feature\FrontCancelControllerCommentReadabilityTest.php
RED:
- Front CancelController 缺少中文逻辑注释：前台销户申请控制器
- Front CancelController 仍残留旧英文注释标题：Front account cancellation controller.

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_front_agent_gift_and_cancel_legacy_operations_have_resource_style_api_routes
RED:
- Route::get('/gifts', 'GiftController@giftList') route is missing.
- 礼品列表 Blade 缺少 'method' => 'GET'

根因�?
- 前端 Layui �? Naive 均按 GET /api/front/gifts 调用礼品列表，但 routes/front.php 仍注册为 POST，Layui Blade 也没有显式声�? GET 方法�?
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\FrontCancelControllerCommentReadabilityTest.php
OK (2 tests, 26 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_naive_account_cancel_route_has_own_module_and_submit_flow
OK (1 test, 11 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_front_agent_gift_and_cancel_legacy_operations_have_resource_style_api_routes
OK (1 test, 71 assertions)

php -l app\Http\Controllers\Front\CancelController.php
No syntax errors detected in app\Http\Controllers\Front\CancelController.php

php -l tests\Feature\FrontCancelControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\FrontCancelControllerCommentReadabilityTest.php

php -l routes\front.php
No syntax errors detected in routes\front.php

php -l resources\front\layui\gift\list.blade.php
No syntax errors detected in resources\front\layui\gift\list.blade.php
```

### 相关接口消息

- `POST /api/front/account/cancellation`：返回当前前台用户最近一次销户申请状态，页面用于展示审核状�?��?�申请原因�?�拒绝原因和时间�?
- `POST /api/front/account/cancellation-applications`：提交当前前台用户销户申请，参数 `reason` 表示�?户原因�??
- `POST /user/center/ajaxCancelAccount`：旧前台�?户提交兼容入口，支持 `reason`、`cancelRemark`、`remark` 原因字段，内部复�? `apply`�?
- `GET /api/front/gifts`：礼品列表资源风格接口，返回可兑换礼品和已发货礼品；本轮已与 Layui/Naive 前端调用方式对齐�?

### 验证边界

本轮没有改动�?户申请创建�?�重复待审判断�?�未平仓订单判断、资�?/�?值判断�?�后台审核�?�用户注�?标记或真实礼品列表业务�?�辑，只补齐中文逻辑注释，并修复礼品列表前端调用方法与路由方法不�?致的问题。当前本机真�? MySQL `127.0.0.1:3307` 仍未连接验证，因此未声明真实 DB 数据已经覆盖；真�? DB 恢复后，仍需用代理账号和普�?�客户账号分别验�? `/api/front/account/cancellation`、`/api/front/account/cancellation-applications`、`/user/center/ajaxCancelAccount`、`GET /api/front/gifts`，确认重复待审�?�未平仓、余�?/�?值�?�原因字段写入�?�后台审核状态展示和礼品列表数据均符合真实业务�??
## 109. 2026-06-08 前台个人资料控制器中文�?�辑注释补齐

本轮继续推进 plan.md 中�?�所有模块的文件及参数必须有详细中文注释和�?�辑注释，包括参数的注释及功能作用�?�的要求，重点维�? `app/Http/Controllers/Front/ProfileController.php`。该控制器承载前台资料读取�?�资料更新�?�密码修改�?�邮箱修改�?�头像上传�?�实名认证�?�银行卡认证、银行卡换绑、销户前身份校验、验证码发�?��?�代理关系链查询和旧前台资料接口兼容，是前台 Layui/Naive 资料页与旧项目前台资料入口共用的核心控制器�??

### 本轮维护文件

- `app/Http/Controllers/Front/ProfileController.php`：补齐底部私有工具方法的中文功能说明、参数含义和返回值说明，覆盖 `resolveFileUrl`、`storeProfileFile`、`verifiedContactUser`、`currentProfileContext`、`normalizeChinaPhone`、`phoneMatches`、`sendLegacyProfileCode`、`relationshipText`、`relationshipIds`、`legacyBankCardUpload`、`firstUploadedField`、`legacySuccess`、`legacyFail`、`idCardStatusText`、`bankStatusText`、`mirrorPublicDiskFile`、`deletePublicMirror`、`maskPhone`、`maskEmail`、`maskIdCard`、`maskBankNo`�?
- `tests/Feature/FrontProfileControllerCommentReadabilityTest.php`：继续作为前台个人资料控制器中文注释可读性约束，静�?�读取源码，不连接真实数据库�?
- `docs/admin-backend-blade-permission-final-checklist.md`：追加本节记录本次维护内容�?�验证命令�?�相关接口和真实 DB 验证边界�?

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\FrontProfileControllerCommentReadabilityTest.php
OK (2 tests, 51 assertions)

php -l app\Http\Controllers\Front\ProfileController.php
No syntax errors detected in app\Http\Controllers\Front\ProfileController.php

php -l tests\Feature\FrontProfileControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\FrontProfileControllerCommentReadabilityTest.php

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_front_profile_legacy_operations_have_resource_style_api_routes
OK (1 test, 95 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_layui_avatar_upload_is_automatic_after_file_selection
OK (1 test, 12 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_layui_profile_upload_uses_shared_polished_component_styles
OK (1 test, 33 assertions)

vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_profile_ajax_routes_do_not_return_server_errors_without_guard_login
OK (1 test, 14 assertions)
```

### 相关接口消息

- `GET /api/front/profile`：读取当前前台用户资料，返回 `login`、`info`、`auth`、`avatar_url`、脱敏手机号、脱敏邮箱�?�脱敏身份证号和认证状�?�文案�??
- `PATCH /api/front/profile`：更新当前前台用户基�?资料，参数包�? `user_name`、`phone`、`id_card_no`、`gender`、`address`�?
- `POST /api/front/profile/password`：修改当前前台用户登录密码，参数包含 `old_password`、`password`、`password_confirmation`�?
- `POST /api/front/profile/email`：修改当前前台登录邮箱，参数包含 `verify_phone`、`current_email`、`new_email`�?
- `POST /api/front/profile/avatar`：上传当前前台用户头像，参数 `avatar` 为新版头像上传文件字段�??
- `POST /api/front/profile/identity`：提交实名认证资料，参数包含 `id_card_no`、`id_card_front`、`id_card_back`�?
- `POST /api/front/profile/bank-card`：提交银行卡认证资料，参数包�? `bank_name`、`bank_no`、`bank_addr`、`bank_card_img`、`bank_card_back_img`�?
- `POST /api/front/profile/bank-card-change`：提交银行卡换绑资料，写�? `bank_name_tmp`、`bank_no_tmp`、`bank_addr_tmp`、`bank_card_img_tmp`、`bank_card_back_img_tmp`，并设置 `bank_status=3` 表示银行卡换绑待审核�?
- `POST /api/front/profile/verification-cancellation-checks` 与旧路由 `POST /user/center/cancelVerifyInfo`：校验销户前的手机号、邮箱和身份证号�?
- `POST /api/front/profile/verification-cancellation/verification-codes` 与旧路由 `POST /user/center/cancelVerifyPassSendCode`：发送销户验证邮件验证码�?
- `POST /api/front/profile/relationship-path`、`POST /api/front/profile/relationship-path/html`、`POST /api/front/profile/relationship-tree/html` 与旧关系链路由：返回代理关系链文本或旧前�? HTML 兼容格式�?

### 验证边界

本轮没有改动个人资料业务逻辑，只补齐中文逻辑注释和参数说明�?�当前本机真�? MySQL `127.0.0.1:3307` 仍然连接失败，`php artisan migrate:status` �? `SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接`，因此未声明真实 DB 数据验证完成�?

�? DB 连接失败影响，以下两个旧前台 session 回归测试无法作为通过证据，失败根因均�? `FrontBaseController::legacyFrontUserLogin()` 查询 `user_logins` 时数据库连接被拒绝：

```text
vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_profile_ajax_routes_do_not_return_server_errors_for_stale_legacy_session_user
FAIL: /user/center/cancelVerifyInfo returned 500 because MySQL refused connection.

vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_profile_ajax_routes_resolve_real_legacy_session_user
FAIL: expected 200 but received 500 because MySQL refused connection while resolving user_id=1001.
```

真实 DB 恢复后，�?要继续使用代理账号和普�?�客户账号分别验�? `GET /api/front/profile`、`PATCH /api/front/profile`、头像上传�?�实名认证上传�?�银行卡认证、银行卡换绑、销户校验�?�关系链接口和旧前台 `user/center/*` 兼容入口，确认真实数据�?�脱敏字段�?�文�? URL、邮件验证码缓存和旧响应结构均符合业务预期�??
## 110. 2026-06-08 前台仪表盘控制器中文逻辑注释补齐

本轮继续推进 plan.md 中�?�后端支持多语言、所有模块文件及参数必须有详细中文注释和逻辑注释”的要求，重点维�? `app/Http/Controllers/Front/DashboardController.php`。该控制器负责前台首�? Blade 视图、账户摘要�?�代理层级统计�?�入金出金交易月度统计�?�新闻公告�?�旧前台热点新闻、注册页热点新闻和礼品提示状态，是前�? Layui/Naive 首页和旧前台首页兼容入口共用的数据控制器�?

### 本轮维护文件

- `tests/Feature/FrontDashboardControllerCommentReadabilityTest.php`：新增前台仪表盘控制器中文注释可读�?�测试，静�?�读取源码，不连接真实数据库；约束首页统计字段�?�新闻多语言、旧前台热点新闻、礼品提示和�?请注册链接必须有中文逻辑说明�?
- `app/Http/Controllers/Front/DashboardController.php`：清�? `Front Dashboard Controller`、`Provides dashboard views and account summary data.`、`Dashboard view`、`Account summary data`、`Trading records preserve MT4 open_time/close_time.`、`Get first configured value from possible old/new keys.` 等旧英文注释，补齐中文功能说明�?�参数含义和返回值说明�??
- `docs/admin-backend-blade-permission-final-checklist.md`：追加本节记录本轮变更�?�接口消息�?�验证结果和数据库迁移边界�??

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontDashboardControllerCommentReadabilityTest.php
RED:
- Front DashboardController 缺少中文逻辑注释：处理前台首�? Blade 视图、账户摘要�?�代理层级统计�?�入金出金交易月度统计�?�新闻公告�?�旧前台热点新闻和礼品提示状�?
- Front DashboardController 仍残留旧英文注释标题：Front Dashboard Controller
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\FrontDashboardControllerCommentReadabilityTest.php
OK (2 tests, 31 assertions)

php -l app\Http\Controllers\Front\DashboardController.php
No syntax errors detected in app\Http\Controllers\Front\DashboardController.php

php -l tests\Feature\FrontDashboardControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\FrontDashboardControllerCommentReadabilityTest.php

rg -n "Front Dashboard Controller|Provides dashboard views|Dashboard view|Account summary data|Trading records preserve|Get first configured" app\Http\Controllers\Front\DashboardController.php -S
未命中旧英文注释�?

vendor\bin\phpunit tests\Feature\FrontendRouteManifestTest.php --filter test_front_dashboard_controller_uses_named_routes_for_generated_links
OK (1 test, 4 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter dashboard
OK (8 tests, 145 assertions)

vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_user_web_routes_are_registered
OK (1 test, 185 assertions)

vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_user_module_routes_are_registered
OK (1 test, 482 assertions)

vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_named_route_aliases_are_registered
OK (1 test, 156 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_naive_modules_do_not_generate_local_mock_rows_or_dashboard_data
OK (1 test, 8 assertions)

php artisan migrate:status
已可连接数据库；�? 2026_06_07_000006 �? 2026_06_07_000015 中多条后台权�?/前台菜单角色迁移仍为 No�?

php -l docs\admin-backend-blade-permission-final-checklist.md
No syntax errors detected in docs\admin-backend-blade-permission-final-checklist.md
```

### 相关接口消息

- `GET /api/front/dashboard`：返回当前前台用户首页账户摘要，包含 `user`、`profile`、`downloads`、`stats`、`news`、`period`�?
- `GET /front/dashboard`：前�? Layui 仪表�? Blade 页面路由，页面只渲染容器，真实数据由 `/api/front/dashboard` 返回�?
- `GET /user/front/message`：旧前台消息面板占位入口，返回空消息面板 HTML�?
- `POST /user/main/hot/news`：旧前台首页热点新闻 HTML 列表接口，返�? `code`、`msg`、`page`、`count`、`dataHtml`�?
- `POST /user/main/hot/newsV2` �? `GET /user/register/hotnews`：旧前台注册页热点新闻表格接口，返回 `code`、`msg`、`count`、`data`、`totalRow`�?
- `POST /user/main/hasShowGiftTips`：旧前台礼品提示已读接口，按当前登录用户写入 `gift_tips_shown_{user_id}` 缓存键�??

### 验证边界

本轮只补齐中文�?�辑注释和参数说明，没有改动首页统计、新闻查询�?�下载配置�?�邀请链接�?�礼品提示或旧前台响应结构�?�`php artisan migrate:status` 本轮已能连接数据库，但以下迁移仍未执行：`2026_06_07_000006_add_admin_fund_flow_permissions`、`2026_06_07_000007_add_admin_rights_summary_permissions`、`2026_06_07_000008_add_admin_exchange_rate_permissions`、`2026_06_07_000009_add_admin_online_user_permissions`、`2026_06_07_000010_add_admin_production_permissions`、`2026_06_07_000011_add_admin_gift_permissions`、`2026_06_07_000012_add_admin_authentication_permissions`、`2026_06_07_000013_add_admin_realtime_commission_permissions`、`2026_06_07_000014_fix_default_admin_and_front_menu_roles`、`2026_06_07_000015_add_admin_position_summary_permissions`。因此后台新增权限�?�默认超级管理员修复和前�? Layui 菜单角色授权仍需在后续真�? DB 阶段执行或复核�??

真实 DB 业务验证仍需要继续使用代理账号和普�?�客户账号分别访�? `/api/front/dashboard`，确认代理可聚合后代入金/出金/交易统计，客户只查看自身统计；同时验�? `news_langs` 多语�?标题、下载地�?候�?�键、注册链�? `account_type` �? `commission_mode` 参数，以及旧前台热点新闻接口返回结构�?
## 111. 2026-06-09 后台默认超级管理员与前台 Layui 菜单权限真实 DB 落库验证

本轮针对测试时发现的“前�? Layui 风格菜单没有了�?�和“后台超级管理员账号不可登录/未知”问题，直接在当前真�? MySQL `co_crmv5` 数据库执行未落库迁移，并用真实接口验证登录�?�后台菜单�?�前�? agent 菜单返回结果。根因是 `2026_06_07_000014_fix_default_admin_and_front_menu_roles` �? 10 条迁移此前处�? `No` 状�?�，导致 `superadmin` 未写入当前登录控制器读取�? `admins` 表，前台 `agent_role`、`customer_role` �? `role_permissions` 菜单授权也未完整写入�?

### 本轮执行内容

- 执行 `php artisan migrate`，完�? `2026_06_07_000006` �? `2026_06_07_000015` �? 10 条后台权限�?�前台菜单角色和默认后台账号迁移�?
- 确认 `admins.username=superadmin` 已存在，初始登录密码�? `Admin@123456`，当前后台页面登录入口为 `GET /admin/login`，后台登�? API �? `POST /api/admin/login`�?
- 确认前台 agent 演示账号 `agent@test.com / agent123` 已绑�? `roles.name=agent_role`，`GET /api/front/navigation/menus` 返回代理专属菜单�?
- 确认 `customer_role` 已写�? 17 条普通客户菜单授权，且未包含 `front_agent`、`front_commission` 等代�?/返佣专属菜单�?

### 本轮真实 DB 验证记录

```text
php artisan migrate
Migrated:
- 2026_06_07_000006_add_admin_fund_flow_permissions
- 2026_06_07_000007_add_admin_rights_summary_permissions
- 2026_06_07_000008_add_admin_exchange_rate_permissions
- 2026_06_07_000009_add_admin_online_user_permissions
- 2026_06_07_000010_add_admin_production_permissions
- 2026_06_07_000011_add_admin_gift_permissions
- 2026_06_07_000012_add_admin_authentication_permissions
- 2026_06_07_000013_add_admin_realtime_commission_permissions
- 2026_06_07_000014_fix_default_admin_and_front_menu_roles
- 2026_06_07_000015_add_admin_position_summary_permissions

php artisan migrate:status
2026_06_07_000006 �? 2026_06_07_000015 均为 Yes，Batch=13�?

POST /api/admin/login
username=superadmin
password=Admin@123456
结果：HTTP 200，code=1000，user.username=superadmin�?

POST /api/admin/menus
Authorization=Bearer {superadmin token}
结果：HTTP 200，code=1000，后台菜单数�?=74，后台权�? slug 数量=150�?

POST /api/front/auth/login
email=agent@test.com
password=agent123
结果：HTTP 200，code=1000，user_id=1001�?

GET /api/front/navigation/menus
Authorization=Bearer {agent token}
结果：HTTP 200，code=1000，根菜单数量=9，菜�? slug 数量=26�?
关键菜单：front_agent、front_agent_sub、front_agent_customers、front_commission、front_commission_rt 均存在�??

DB 权限范围复核
roles:
- super_admin：admin 角色，id=51
- agent_role：前台代理角色，id=52
- customer_role：前台客户角色，id=53
front permissions 启用数量=28
agent_role 授权数量=26，包含代理管理和返佣管理
customer_role 授权数量=17，不包含 front_agent �? front_commission，包�? front_dashboard
```

### 本轮自动化测试记�?

```text
vendor\bin\phpunit tests\Feature\DefaultAdminAndFrontMenuRoleMigrationTest.php
OK (2 tests, 57 assertions)

vendor\bin\phpunit tests\Feature\FrontMenuPermissionRouteConsistencyTest.php
OK (1 test, 2 assertions)

vendor\bin\phpunit tests\Feature\AdminPermissionPlanTest.php --filter test_admin_menus_response_contains_permission_slugs
OK (1 test, 3 assertions)

vendor\bin\phpunit tests\Feature\AdminPermissionPlanTest.php --filter test_front_menu_tree_keeps_parent_when_only_child_permission_is_granted
OK (1 test, 3 assertions)
```

### 相关接口消息

- `GET /admin/login`：后�? Layui 登录页面入口�?
- `POST /api/admin/login`：后台登录接口，参数 `username` 表示 `admins.username`，参�? `password` 表示后台登录密码；当前默认超级管理员�? `superadmin / Admin@123456`�?
- `POST /api/admin/menus`：后台当前管理员菜单与按钮权限接口，返回 `data.menus` �? `data.permissions`，超级管理员返回全部启用后台菜单和权�? slug�?
- `POST /api/front/auth/login`：前台登录接口，参数 `email` �? `password` 用于登录 `user_logins` 表账号；本轮�? `agent@test.com / agent123` 验证通过�?
- `GET /api/front/navigation/menus`：前�? Layui/Blade 菜单接口，按当前登录用户 `role_id` 读取 `roles`、`role_permissions`、`permissions` 表配置；agent 返回代理管理和返佣管理，customer_role 配置不包含代理专属菜单�??
- `GET /api/front/menus`：前台菜单兼容接口，�? `/api/front/navigation/menus` 复用同一控制器方法�??

### 验证边界

本轮已经修复并验证此前清单第 110 节记录的“迁移未执行”边界；当前真实 DB �? 6 �? 7 日末�? 10 条迁移已全部执行。仍�?注意：当前真�? DB 暂未发现 `account_type=2` 的普通客户登录账号，因此本轮只能验证 `customer_role` 的菜单授权配置正确，不能完成普�?�客户账号登录后的菜单接口冒烟�?�后续补充真实普通客户测试数据后，需要再用普通客户账号调�? `POST /api/front/auth/login` �? `GET /api/front/navigation/menus`，确认返回菜单不包含 `front_agent`、`front_commission` 等代理专属节点�??
## 112. 2026-06-09 前台持仓管理控制器中文�?�辑注释补齐

本轮继续推进 plan.md 中�?�所有模块的文件及参数必须有详细中文注释和�?�辑注释，包括参数的注释及功能作用�?�的要求，重点维�? `app/Http/Controllers/Front/PositionController.php`。该控制器承载前台持仓汇总�?�本�? MT4 汇�?��?�下级代理汇总�?�交易明细�?�旧前台点击明细、代理网络权限校验�?�返佣汇总和平仓品种分类统计，是前台 Layui/Naive 持仓页面与旧前台 `user/position/*` 入口共用的核心控制器�?

### 本轮维护文件

- `tests/Feature/FrontPositionControllerCommentReadabilityTest.php`：新增静态中文注释可读�?�测试，不连接真实数据库，约�? PositionController 必须包含持仓汇�?��?�本�? MT4 汇�?��?�返佣汇总�?�平仓筛选�?�品种组、入出金备注、代理链路�?�钻取权限和旧前台入口等中文逻辑说明�?
- `app/Http/Controllers/Front/PositionController.php`：移除旧英文注释标题，补齐类说明、入口方法说明�?�私有工具方法说明�?�参数含义�?�变量含义和返回值边界；本轮只改注释，不改动查询、汇总�?�权限或响应结构�?
- `docs/admin-backend-blade-permission-final-checklist.md`：追加本节，记录本次变更、接口消息�?�验证命令和剩余边界�?

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontPositionControllerCommentReadabilityTest.php
RED:
- Front PositionController 缺少中文逻辑注释：处理持仓汇总�?�本�? MT4 汇�?��?�下级代理汇总�?�交易明细�?�旧前台搜索入口、代理关系权限校验和品种分类统计
- Front PositionController 仍残留旧英文注释标题：Front Position Management Controller
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\FrontPositionControllerCommentReadabilityTest.php
OK (2 tests, 40 assertions)

php -l app\Http\Controllers\Front\PositionController.php
No syntax errors detected in app\Http\Controllers\Front\PositionController.php

php -l tests\Feature\FrontPositionControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\FrontPositionControllerCommentReadabilityTest.php

rg -n "Front Position Management Controller|Provides position summary|Position summary with date range filter|Strict port of old User\\PositionSummary2Controller|It returns current login user's own MT4-style|Search position summary with filters|Get all descendants and self IDs|Search sub-user position summary|Show trade details for a specific user|Verify the user is in current agent's network|Legacy method for clickSearch" app\Http\Controllers\Front\PositionController.php -S
未命中旧英文注释标题�?

vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_resource_api_routes_are_registered_and_legacy_module_aliases_are_removed
OK (1 test, 73 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_position_summary_total_rebate_uses_real_commission_records
OK (1 test, 9 assertions)

vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_user_module_routes_are_registered
OK (1 test, 482 assertions)

vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_named_route_aliases_are_registered
OK (1 test, 156 assertions)

vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_main_ajax_routes_do_not_return_server_errors_without_guard_login
OK (1 test, 43 assertions)

php artisan migrate:status
当前真实 DB 可连接，�?有迁移均�? Yes�?2026_06_07_000006 �? 2026_06_07_000015 均已落库�?
```

### 相关接口消息

- `GET /api/front/positions/summary`：新前台持仓汇�?�接口，返回当前代理可见的持仓汇总；参数 `userPId`、`target_id` �? `user_id` 表示钻取目标，必须属于当前代理网络�??
- `GET /api/front/positions/direct-agent-summaries`：新前台直属代理持仓汇�?�接口，返回当前代理下级代理资金与交易汇总�??
- `GET /api/front/positions/trades`：新前台交易明细接口，参�? `user_id` 表示被查看用户，`ticket`/`orderId` 表示订单号，`status` 表示订单状�?�，1=历史平仓�?0=当前持仓�?
- `POST /user/position/positionSummarySearch`：旧前台持仓汇�?�搜索入口，内部复用 `positionSummary`�?
- `POST /user/position/positionSummary2Search`：旧前台本人 MT4 汇�?�入口，返回入金、出金�?�盈亏�?�手续费、库存费和品种手数�??
- `POST /user/position/v2/subAgentsListSearchV2`：旧前台下级代理持仓汇�?�入口，内部复用 `subPositionSummary`�?
- `POST /user/position/v2/positionSummaryClickSearch`：旧前台点击持仓明细入口，内部复�? `positionDetail`�?

### 验证边界

本轮只补齐中文�?�辑注释和参数说明，没有改动持仓汇�?�业务�?�辑。`FrontUiRegressionTest.php --filter positions`、`--filter positionSummary2Search`、`--filter positionSummaryClickSearch` 当前没有匹配到测试方法，因此不作为�?�过证据；本轮已用命中的路由注册、旧前台模块路由、旧命名路由、未登录 Ajax 入口和真实返佣字段回归测试覆盖核心行为�?�后续若要进�?步加强运行时验证，应补充带登�? token 的真�? DB 接口测试，使�? agent 账号访问 `GET /api/front/positions/summary`、`GET /api/front/positions/direct-agent-summaries` �? `GET /api/front/positions/trades`，并构�?�普通客户或越权目标验证 `resolveSummaryTargetId` �? `positionDetail` 的权限边界�??
## 113. 2026-06-09 前台上传控制器中文�?�辑注释补齐

本轮继续推进 plan.md 中�?�所有模块的文件及参数必须有详细中文注释和�?�辑注释，包括参数的注释及功能作用�?�的要求，重点维�? `app/Http/Controllers/Front/UploadController.php`。该控制器承载新前台 `/api/front/uploads/*` 上传入口、旧前台 `user/upload/file` 单文件上传和 `user/multiple/file` 多文件上传入口，返回结构直接影响头像、身份证、银行卡、资料认证和�? Layui 上传回调�?

### 本轮维护文件

- `tests/Feature/FrontUploadControllerCommentReadabilityTest.php`：新增静态中文注释可读�?�测试，不连接真实数据库、不写入真实上传文件；约束新前台上传、旧前台单文件上传�?�旧前台多文件上传和 legacy 返回字段必须有中文�?�辑说明�?
- `app/Http/Controllers/Front/UploadController.php`：移�? `Generic Upload Controller`、`Generic upload method` 等旧英文注释标题，补齐类说明、公�?上传入口说明、私有保存方法说明�?�参数含义�?�返回字段含义和旧前台兼容边界�??
- `docs/admin-backend-blade-permission-final-checklist.md`：追加本节，记录本轮上传模块注释补齐、接口消息�?�验证命令和边界说明�?

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontUploadControllerCommentReadabilityTest.php
RED:
- Front UploadController 缺少中文逻辑注释：前台上传控制器
- Front UploadController 仍残留旧英文注释标题：Generic Upload Controller
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\FrontUploadControllerCommentReadabilityTest.php
OK (2 tests, 25 assertions)

php -l app\Http\Controllers\Front\UploadController.php
No syntax errors detected in app\Http\Controllers\Front\UploadController.php

php -l tests\Feature\FrontUploadControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\FrontUploadControllerCommentReadabilityTest.php

rg -n "Generic Upload Controller|Handles generic file uploads|Generic upload method|Define storage path" app\Http\Controllers\Front\UploadController.php -S
未命中旧英文注释标题�?

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_front_upload_apis_use_readable_resource_style_routes
OK (1 test, 14 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_layui_profile_upload_components_have_latest_status_nodes
OK (1 test, 48 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_layui_avatar_upload_is_automatic_after_file_selection
OK (1 test, 12 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_layui_profile_upload_uses_shared_polished_component_styles
OK (1 test, 33 assertions)

vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_named_route_aliases_are_registered
OK (1 test, 156 assertions)

直接调用 UploadController 无文件失败结构：
singleFileUpload status=200 body={"code":500,"msg":"FAIL","data":{}}
multipleFileUpload status=200 body={"code":200,"msg":"SUC","data":[]}
```

### 相关接口消息

- `POST /api/front/uploads`：新前台通用上传入口，由 Common UploadController 处理，保留资源风格路由�??
- `POST /api/front/uploads/single`：新前台单文件上传入口，内部调用 `Front\UploadController@singleFileUpload`，返回旧前台兼容结构�?
- `POST /api/front/uploads/multiple`：新前台多文件上传入口，内部调用 `Front\UploadController@multipleFileUpload`，返回旧前台兼容结构�?
- `POST /user/upload/file`：旧前台单文件上传入口，`file` 表示上传文件字段；成功返�? `code=200,msg=SUC,data={name,path,url}`，失败返�? `code=500`�?
- `POST /user/multiple/file`：旧前台多文件上传入口，`file` 表示上传文件集合；返�? `code=200,msg=SUC,data=[]` 或成功文件列表�??

### 验证边界

本轮只补齐中文�?�辑注释和参数说明，没有改变上传校验规则、上传目录�?�文件命名规则或响应结构。直接�?�过 HTTP Kernel 调用�? web 上传路由时会先被 CSRF 中间件拦截并返回 419，这�? web 路由安全边界，不代表控制器�?�辑失败；因此本轮用直接调用控制器方法验证无文件时旧上传响应结构仍保持不变�?�真实文件上传仍建议在后续用 Laravel UploadedFile 构�?�带图片的接口测试，验证 `uploads/Bank`、`uploads/IdCard`、`/storage/{path}` 与浏览器访问 URL 是否符合旧前台和新前台页面预期�??

## 114. 2026-06-09 ǰ̨ƾ֤�����������߼�ע�Ͳ���

���ּ����ƽ� plan.md �С�����ģ����ļ���������������ϸ����ע�ͺ��߼�ע�ͣ�����������ע�ͼ��������á���Ҫ��ά��? `app/Http/Controllers/Front/VoucherController.php`���ÿ���������ǰ̨�û��ύƾ֤ͼƬ��д�� `voucher_infos` �����¼�����״̬���Լ�����ǰ��¼�û���ѯ�Լ���ƾ֤��ҳ��¼��ֱ��Ӱ��ǰ̨ Layui ƾ֤ҳ��ͺ�̨ƾ֤�����·��

### ����ά���ļ�

- `tests/Feature/FrontVoucherControllerCommentReadabilityTest.php`��������̬����ע�Ϳɶ��Բ��ԣ�ֻ��ȡ������Դ�룬��������ʵ���ݿ⣬��д���ϴ��ļ���
- `app/Http/Controllers/Front/VoucherController.php`����дΪ UTF-8 �ɶ�Դ�룬������˵����`store`��`records` ������ҵ���߼����������塢���״̬���塢�ϴ�·������ͷ��ؽṹ˵����ͬʱ�Ƴ���Ӣ��ע�ͱ��⡣
- `docs/admin-backend-blade-permission-final-checklist.md`��׷�ӱ��ڣ���¼���ֱ�����ӿ���Ϣ����֤�������֤�߽硣

### ���� TDD ��¼

```text
vendor\bin\phpunit tests\Feature\FrontVoucherControllerCommentReadabilityTest.php
RED:
- Front VoucherController ȱ�������߼�ע�ͣ�ǰ̨ƾ֤������
- Front VoucherController �Բ����Ӣ��ע�ͱ��⣺Submit voucher
```

### ������֤��¼

```text
vendor\bin\phpunit tests\Feature\FrontVoucherControllerCommentReadabilityTest.php
OK (2 tests, 21 assertions)

php -l app\Http\Controllers\Front\VoucherController.php
No syntax errors detected in app\Http\Controllers\Front\VoucherController.php

php -l tests\Feature\FrontVoucherControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\FrontVoucherControllerCommentReadabilityTest.php

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter voucher
OK (2 tests, 25 assertions)

rg -n "Submit voucher|Get current user's voucher submissions|Store to storage|Pending" app\Http\Controllers\Front\VoucherController.php -S
δ���о�Ӣ��ע�ͱ��⡣

vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter voucher
No tests executed!
˵�����ù���������ǰû��ƥ�䵽���Է�������˲�����Ϊ��·��ͨ��֤�ݡ�?
```

### ��ؽӿ����?

- `POST /api/front/voucherSubmit`����ʷǰ̨ƾ֤�ύ��������˵������ǰ��Դ���ӿ���Ҫ���˻�ģ�� `POST /api/front/account/voucher-submissions` �е���
- `GET /api/front/voucherRecords`����ʷǰ̨ƾ֤��¼��������˵������ǰ��Դ���ӿ���Ҫ���˻�ģ�� `GET /api/front/account/vouchers` �е���
- `POST /api/front/account/voucher-submissions`��ǰ̨ƾ֤�ύ�ӿڣ����� `images[]` ��ʾһ��ƾ֤ͼƬ��`remarks` ��ʾ�û���ע��д�� `voucher_infos.images`��`voucher_infos.remarks`��`review_status=0`��
- `GET /api/front/account/vouchers`��ǰ̨��ǰ�û�ƾ֤��¼�ӿڣ����� `review_status` ��ʾ���״̬ɸѡ��`date_from` �� `date_to` ��ʾ�������ڷ�Χ��`per_page` ��ʾ��ҳ��С��
- `POST /user/user_voucher_save`����ǰ̨ƾ֤�ύ�ӿڣ���ǰ�� `Front\AccountController@userVoucherSave` ���ݴ����?
- `POST /user/voucher/voucherSearch`����ǰ̨ƾ֤��ѯ�ӿڣ���ǰ�� `Front\AccountController@voucherList` ���ݴ����?

### ��֤�߽�

����ֻ���� `Front\VoucherController` �������߼�ע�ͺͱ���ɶ��ԣ�û�иı�ƾ֤�ύ���ļ����桢���״̬����ҳ��ѯ�򷵻ؽṹ����ǰǰ̨��ʵҳ��·�ɺ� API ��Ҫ���� `Front\AccountController`����˱����Ծ�̬�ɶ��Բ��Ժ�ƾ�? UI �ع���Ϊ��Ҫ֤�ݣ�������Ҫ��֤ `Front\VoucherController` ����ʱ��Ϊ��Ӧ�����? `UploadedFile` �ĵ�¼̬�ӿڲ��ԣ�ȷ�� `images[]` ��ͼ�ϴ���`remarks` ��ע��`review_status=0` �� `voucher_infos.images` ·��ƴ�Ӿ�������ʵ DB Ԥ�ڡ�

## 115. 2026-06-09 ǰ̨���Ź�������������߼�ע���������˵������

���ּ����ƽ� plan.md �С���˱���֧�ֶ����ԡ��͡�����ģ����ļ���������������ϸ����ע�ͺ��߼�ע�͡���Ҫ��ά�� `app/Http/Controllers/Front/NewsController.php`���ÿ�����������ǰ̨���Ź����ҳ�ӿڡ���ǰ̨�����б������ӿڡ���ǰ̨��������? HTML����ͨ�� `X-Locale` ����ͷ���ȶ�ȡ `news_langs` ��ǰ���Լ�¼��

### ����ά���ļ�

- `tests/Feature/FrontNewsControllerCommentReadabilityTest.php`��������̬����ע�Ϳɶ��Բ��ԣ�ֻ��ȡ `Front\NewsController` Դ�룬��������ʵ���ݿ⣬������ `news` �� `news_langs` ��ʵ���ݡ�
- `app/Http/Controllers/Front/NewsController.php`����дΪ UTF-8 �ɶ�Դ�룬���� `newsList`��`newsListSearch`��`newsDetail` ������ҵ���߼����������塢�����Ի��ˡ���ǰ̨��Ӧ�ֶκ� HTML ����˵����
- `docs/admin-backend-blade-permission-final-checklist.md`��׷�ӱ��ڣ���¼���ֶ���������ģ��ά�����ӿ���Ϣ����֤�������֤�߽�?

### ���� TDD ��¼

```text
vendor\bin\phpunit tests\Feature\FrontNewsControllerCommentReadabilityTest.php
RED:
- Front NewsController ȱ�������߼�ע�ͣ�����ǰ̨���Ź����б����ǰ̨������������ǰ̨��������? HTML �� news_langs �����Ի���
- Front NewsController �Բ����Ӣ��ע�ͱ��⣺Front News Controller
```

### ������֤��¼

```text
vendor\bin\phpunit tests\Feature\FrontNewsControllerCommentReadabilityTest.php
OK (2 tests, 20 assertions)

php -l app\Http\Controllers\Front\NewsController.php
No syntax errors detected in app\Http\Controllers\Front\NewsController.php

php -l tests\Feature\FrontNewsControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\FrontNewsControllerCommentReadabilityTest.php

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter news
OK (1 test, 15 assertions)

rg -n "Front News Controller|Paginated published news list" app\Http\Controllers\Front\NewsController.php -S
δ���о�Ӣ��ע�ͱ��⡣

vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter news
No tests executed!
˵�����ù���������ǰû��ƥ�䵽���Է�������˲�����Ϊ��·��ͨ��֤�ݡ�?

vendor\bin\phpunit tests\Feature\FrontendRouteManifestTest.php --filter news
No tests executed!
˵�����ù���������ǰû��ƥ�䵽���Է�������˲�����Ϊ·��? Manifest ͨ��֤�ݡ�
```

### ��ؽӿ����?

- `GET /api/front/news`����ǰ̨���Ź����ҳ�ӿڣ�����? `page` ��ʾ��ǰҳ�룬`per_page` ��ʾÿҳ����������`title` ��ʾ���ű���ɸѡ�ؼ��֣�`author_name` ��ʾ��������ɸѡ��`X-Locale` ����ͷ��ʾ��ǰ���ԡ�
- `POST /user/newsListSearch`����ǰ̨�����б������ӿڣ����� `rows` �� `total`��`rows` �б��� `news_id`��`news_title`��`news_content`��`rec_upd_date` �Ⱦ�ҳ���ֶΡ�
- `GET /user/news/news_detail/{newsId}`����ǰ̨�������� HTML �ӿڣ�`newsId` ��ʾ `news.id`������ `crm-legacy-news` HTML ����������ͳһ JSON��
- `news_langs` �����Զ�ȡ���򣺵� `news_langs.news_id + lang_code` ������ `title/content` ��Ϊ��ʱ�����ȷ��ط����ֶΣ��������? `news.title` �� `news.content`��
- `Schema::hasTable('news_langs')`�������ھ�����ҳ����ȱ�ٶ����Ա�Ĳ��𻷾�������Ǩ��δ���ʱ����ҳֱ�ӱ����?

### ��֤�߽�

����ֻ���� `Front\NewsController` �������߼�ע�͡�������˵���ͱ���ɶ��ԣ�û�иı�����ɸѡ����ҳ��������ˡ���ǰ̨ `rows/total` �ṹ������ HTML ��������ڱ����������Ǿ�̬�ɶ��Բ��ԣ���δʹ�����? DB ���� `news/news_langs` ����������������ʱ���ԣ�����Ӧ��������? `news_langs` �Ľӿڲ��ԣ��ֱ���֤���ġ�Ӣ�ġ�ȱʧ����Ϳշ����ֶ�ʱ�Ļ�����Ϊ��?

## 116. 2026-06-09 ǰ̨�ͻ������������߼�ע����������ݷ�Χ˵������?

���ּ����ƽ� plan.md �С���ͬ�������ͨ�û�����������ʾ���ݡ��Լ�������ģ����ļ���������������ϸ����ע�ͺ��߼�ע�͡���Ҫ��ά�� `app/Http/Controllers/Front/CustomerController.php`���ÿ����������ǰ����ɼ��ͻ��б�Ϳͻ�ͳ��ժҪ��������ѯ��Χ����? `agent_descendants`�����Ե�ǰ��¼���� `user_id` ��Ϊ���ݱ߽硣

### ����ά���ļ�

- `tests/Feature/FrontCustomerControllerCommentReadabilityTest.php`��������̬����ע�Ϳɶ��Բ��ԣ�ֻ��ȡ `Front\CustomerController` Դ�룬��������ʵ���ݿ⡣
- `app/Http/Controllers/Front/CustomerController.php`����дΪ UTF-8 �ɶ�Դ�룬���� `myCustomers`��`stats` ������ҵ���߼����������塢�ͻ���Χ��ֱ��ɸѡ���ͻ�����ɸѡ������ͳ�ƺͻ�Ծ�ͻ�ͳ��˵����
- `docs/admin-backend-blade-permission-final-checklist.md`��׷�ӱ��ڣ���¼���ֿͻ�������ά�����ӿڱ߽硢��֤�����ʣ������ʱ��֤����?

### ���� TDD ��¼

```text
vendor\bin\phpunit tests\Feature\FrontCustomerControllerCommentReadabilityTest.php
RED:
- Front CustomerController ȱ�������߼�ע�ͣ�ǰ̨�ͻ�������
- Front CustomerController �Բ����Ӣ��ע�ͱ��⣺List current agent's direct and indirect customers
```

### ������֤��¼

```text
vendor\bin\phpunit tests\Feature\FrontCustomerControllerCommentReadabilityTest.php
OK (2 tests, 23 assertions)

php -l app\Http\Controllers\Front\CustomerController.php
No syntax errors detected in app\Http\Controllers\Front\CustomerController.php

php -l tests\Feature\FrontCustomerControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\FrontCustomerControllerCommentReadabilityTest.php

vendor\bin\phpunit tests\Feature\FrontAgentControllerCommentReadabilityTest.php
OK (2 tests, 50 assertions)

rg -n "List current agent's direct and indirect customers|Add trade stats for each customer|Customer statistics summary|Active customers \(traded in last month\)|Total volume" app\Http\Controllers\Front\CustomerController.php -S
δ���о�Ӣ��ע�ͱ��⡣

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter customers
No tests executed!
˵�����ù���������ǰû��ƥ�䵽���Է�������˲�����Ϊ�ͻ�ҳ��? UI �ع�ͨ��֤�ݡ�
```

### ��ؽӿ���Ϣ��·�ɱ߽�?

- `Front\CustomerController@myCustomers`�������ǰ̨�ͻ��б�����������? `direct_only` ��ʾ�Ƿ�ֻ��ֱ��ͻ���`user_name` ��ʾ�ͻ�����ģ��ɸѡ��`per_page` ��ʾ��ҳ��С����ǰ��Ŀ���ͻ��б�·�ɲ�δֱ�Ӱ󶨸÷�����
- `Front\CustomerController@stats`������ĵ�ǰ����ͻ�ͳ��ժҪ���������� `total_customers`��`active_customers`��`inactive_customers` �� `total_volume`��
- `GET /api/front/agents/direct-customers`����ǰǰ̨�ͻ�ҳ��ʵ��ʹ�õ����ӿڣ��� `Front\AgentController@customerList`������ͨ�� `FrontAgentControllerCommentReadabilityTest` ������ע�͸�����Ȼͨ����
- `agent_descendants.descendant_type=2`����ʾ�ͻ��ڵ㣬����ע����ȷ���������ڱ�����¼��������ͻ��б��
- `agent_descendants.is_direct=1`����ʾֱ���ϵ��ֻ��? `direct_only=1` ʱ׷�Ӹ�ɸѡ��

### ��֤�߽�

����ֻ���� `Front\CustomerController` �������߼�ע�͡�����˵���ͱ���ɶ��ԣ�û�иı����ͻ���ѯ������ͳ�ƻ�ͳ��ժҪ���ؽṹ�����ڵ��? Layui �ͻ�ҳ��ʵ�ʵ��� `Front\AgentController@customerList`������û�а� `Front\CustomerController` ������ҳ������ʱ֤�ݣ��������������ÿ�������Ӧ������ʽ·�ɻ�ɾ��δʹ����ڣ���ʹ�����? agent token ���ÿͻ��б��ͳ��ժҪ����֤����ֻ�ܲ鿴�Լ�? `agent_descendants` ��Χ�ڵĿͻ����ݡ�

## 117. 2026-06-09 ǰ̨�ֲֻ��ܱ��ÿ����������߼�ע����Ȩ�ޱ߽�˵������

���ּ����ƽ� plan.md �С���ͬ�������ͨ�û�����������ʾ���ݡ��Լ�������ģ����ļ���������������ϸ����ע�ͺ��߼�ע�͡���Ҫ��ά�� `app/Http/Controllers/Front/PositionSummaryController.php`���ÿ����������ǰ����ֱ��ڵ�ֲָ������ֲ�ɸѡ���ܡ��¼������ѯ��ָ���û�������ϸ�������ص�����ȷ�����������ݷ�Χ�͵����ϸԽȨУ��?

### ����ά���ļ�

- `tests/Feature/FrontPositionSummaryControllerCommentReadabilityTest.php`��������̬����ע�Ϳɶ��Բ��ԣ�ֻ��ȡ `Front\PositionSummaryController` Դ�룬��������ʵ���ݿ⡣
- `app/Http/Controllers/Front/PositionSummaryController.php`����дΪ UTF-8 �ɶ�Դ�룬���� `index`��`search`��`subSearch`��`clickSearch` ������ҵ���߼����������塢ֱ��ڵ㷶Χ����������Χ������״̬ɸѡ��ԽȨУ��˵����?
- `docs/admin-backend-blade-permission-final-checklist.md`��׷�ӱ��ڣ���¼���ֱֲֳ��ÿ�����ά�����ӿڱ߽硢��֤�����ʣ������ʱ��֤����?

### ���� TDD ��¼

```text
vendor\bin\phpunit tests\Feature\FrontPositionSummaryControllerCommentReadabilityTest.php
RED:
- Front PositionSummaryController ȱ�������߼�ע�ͣ�ǰ̨�ֲֻ��ܱ��ÿ�����
- Front PositionSummaryController �Բ����Ӣ��ע�ͱ��⣺Return position summary overview for current agent
```

### ������֤��¼

```text
vendor\bin\phpunit tests\Feature\FrontPositionSummaryControllerCommentReadabilityTest.php
OK (2 tests, 30 assertions)

php -l app\Http\Controllers\Front\PositionSummaryController.php
No syntax errors detected in app\Http\Controllers\Front\PositionSummaryController.php

php -l tests\Feature\FrontPositionSummaryControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\FrontPositionSummaryControllerCommentReadabilityTest.php

vendor\bin\phpunit tests\Feature\FrontPositionControllerCommentReadabilityTest.php
OK (2 tests, 40 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter position
OK (1 test, 9 assertions)

rg -n "Return position summary overview for current agent|Get direct descendants|Get this descendant and all their own descendants|Aggregate positions for this node|Search position summary with filters|Get all descendants and self IDs|Search sub-agent position summary|Show trade details for a specific user|Verify the user is in current agent's network" app\Http\Controllers\Front\PositionSummaryController.php -S
δ���о�Ӣ��ע�ͱ��⡣

vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter position
No tests executed!
˵�����ù���������ǰû��ƥ�䵽���Է�������˲�����Ϊ��·��ͨ��֤�ݡ�?
```

### ��ؽӿ���Ϣ��·�ɱ߽�?

- `Front\PositionSummaryController@index`������ĵ�ǰ����ֱ��ڵ�ֲָ���������ͳ��? `is_direct=1` ��ֱ��ڵ㼰����δƽ�ֶ�����?
- `Front\PositionSummaryController@search`������ĵ�ǰ��������ֲ�ɸѡ���������� `date_from`��`date_to`��`symbol` �� `per_page` ����ɸѡ���ҳ��?
- `Front\PositionSummaryController@subSearch`��������¼������ѯ������`descendant_type=1` ��ʾֻ��ѯ����ڵ�?
- `Front\PositionSummaryController@clickSearch`�������ָ���û�������ϸ����������? `user_id` ��ʾ���鿴�û���`symbol` ��ʾ����Ʒ�֣�`ticket` ��ʾ�����ţ�`status=1` ��ʾ��ƽ�֣�`status=0` ��ʾδƽ�֣�Ŀ���û������ڵ�ǰ���������Ҳ��ǵ�ǰ�������ʱ����? `PERMISSION_DENIED`��
- `GET /api/front/positions/summary`����ǰǰ̨�ֲֻ������ӿڣ��� `Front\PositionController@positionSummary`������ͨ�� `FrontPositionControllerCommentReadabilityTest` �ͳֲ� UI �ع鸴������·δ��Ӱ�졣
- `GET /api/front/positions/direct-agent-summaries`����ǰǰ̨�¼�����ֲֻ������ӿڣ���? `Front\PositionController@subPositionSummary`��

### ��֤�߽�

����ֻ���� `Front\PositionSummaryController` �������߼�ע�͡�����˵���ͱ���ɶ��ԣ�û�иı�ֲֻ��ܡ�ɸѡ���¼������ѯ�������ϸ��ԽȨУ�鷵�ؽṹ����ǰǰ̨�ֲ�ҳ��ʵ������·ʹ�� `Front\PositionController`������û�аѱ��ÿ���������������ʱ֤�ݣ��������������ÿ�������Ӧ������ʽ·�ɻ�����δʹ����ڣ���ʹ�����? agent token ����Ŀ���û��ڴ���������/��������ԣ����? `clickSearch` �� `PERMISSION_DENIED` �߽硣

## 118. 2026-06-09 前台基础控制器中文�?�辑注释与旧登录兼容边界复核

本轮继续推进 plan.md 中�?�后端必须支持多语言”�?�所有模块的文件及参数必须有详细中文注释和�?�辑注释”的要求，维�? `app/Http/Controllers/Front/FrontBaseController.php`。该基础控制器是前台控制器共用父类，统一复用 `ApiResponse` 返回结构，并兼容�? JWT `user guard` 与旧前台 session `suser` 登录态，因此它的注释必须说明清楚统一响应、多语言消息 key、登录�?�解析和认证错误边界�?

### 本轮维护文件

- `tests/Feature/FrontBaseControllerCommentReadabilityTest.php`：新增静态中文注释可读�?�测试，只读�? `FrontBaseController` 源码，不连接真实数据库�??
- `app/Http/Controllers/Front/FrontBaseController.php`：补齐前台基�?控制器�?�`legacyFrontUserId`、`legacyFrontUserLogin`、`legacyFrontUserInfo`、`legacyFrontAuthError` 的中文业务�?�辑说明和参数含义说明�??
- `docs/admin-backend-blade-permission-final-checklist.md`：追加本节，记录本轮基础控制器维护�?�接口响应边界�?�验证命令和剩余真实登录验证建议�?

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontBaseControllerCommentReadabilityTest.php
RED:
- FrontBaseController 缺少“前台基�?控制器�?��?�ApiResponse 多语�?消息 key”�?�JWT user guard”�?�旧 session suser”�?�USER_NOT_FOUND / AUTH_FAILED 边界”等中文逻辑注释�?
- FrontBaseController 仍残留旧英文注释标题：Front Base Controller、All front controllers extend this class�?
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\FrontBaseControllerCommentReadabilityTest.php
OK (2 tests, 16 assertions)

php -l app\Http\Controllers\Front\FrontBaseController.php
No syntax errors detected in app\Http\Controllers\Front\FrontBaseController.php

php -l tests\Feature\FrontBaseControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\FrontBaseControllerCommentReadabilityTest.php

vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_main_ajax_routes_do_not_return_server_errors_without_guard_login
OK (1 test, 43 assertions)

vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_named_route_aliases_are_registered
OK (1 test, 156 assertions)

rg -n "Front Base Controller|All front controllers extend this class" app\Http\Controllers\Front\FrontBaseController.php -S
未命中旧英文注释标题�?
```

### 相关接口消息与兼容边�?

- `legacyFrontUserId(Request $request)`：参�? `request` 表示当前 HTTP 请求；优先读�? `$request->user('user')` 中的 JWT 前台登录记录，其次读取旧前台 session `suser`，最终返回业务用�? ID，无法识别时返回 `0`�?
- `legacyFrontUserLogin(Request $request)`：参�? `request` 表示当前 HTTP 请求；优先返�? `user guard` 已解析的 `UserLogin`，旧 session 场景下按 `user_id` 查询 `user_logins`�?
- `legacyFrontUserInfo(Request $request)`：参�? `request` 表示当前 HTTP 请求；按当前登录记录或旧 session 用户 ID 查询 `user_infos`，用于前台业务控制器复用统一的用户资料解析�??
- `legacyFrontAuthError(Request $request)`：参�? `request` 表示当前 HTTP 请求；能识别 `user_id` 但缺少业务资料时返回 `auth.user_info_not_found` �? `USER_NOT_FOUND`，完全无法识别登录�?�时返回 `response.auth_failed` �? `AUTH_FAILED`�?
- `ApiResponse`：本基础控制器统�?使用 `success` �? `error` 响应，消息参数传�? `response.*`、`auth.*` �? Laravel 多语�? key，保证后端响应支�? `zh-CN` �? `en` 等语�?包切换�??

### 验证边界

本轮只补�? `FrontBaseController` 的中文�?�辑注释、参数说明和编码可读性，没有改变前台 JWT 登录态�?�旧 session 兼容、用户资料查询或认证错误返回结构。本轮已通过旧前�? Ajax 主路由和命名路由别名精准回归，说明基�?控制器维护没有破坏旧前台兼容入口。真�? DB 与浏览器环境下仍�?继续�? `agent@test.com / agent123` 登录 Layui 前台，确认登录后 `GET /api/front/navigation/menus` 能携�? token 正常返回代理菜单树，并用普�?�客户账号补测客户菜单边界�??

## 119. 2026-06-09 前台大代�? BigNumberController 中文逻辑注释与多语言响应补齐

本轮继续推进 plan.md 中�?�后端必须支持多语言”�?�所有模块的文件及参数必须有详细中文注释和�?�辑注释”的要求，维�? `app/Http/Controllers/Front/BigNumberController.php`。该控制器同时承载旧前台 `legacy /user/agents/*` 大代理入口和新前�? `/api/front/auth/big-number/login` 登录接口，直接影响大代理账号登录、下级代理范围�?�持仓汇总和订单查询边界�?

### 本轮维护文件

- `tests/Feature/FrontBigNumberControllerCommentReadabilityTest.php`：新增静态中文注释与多语�?响应可读性测试，只读取控制器源码和语�?包，不连接真实数据库�?
- `app/Http/Controllers/Front/BigNumberController.php`：补齐控制器类�?�构造函数�?�旧大代理登录�?�旧页面渲染、旧 Ajax 列表、旧订单查询、新 big-number API 登录、直属代理查询和私有范围方法的中文�?�辑注释与参数说明�??
- `docs/admin-backend-blade-permission-final-checklist.md`：追加本节，记录本轮大代理入口维护�?�接口消息�?�验证命令和真实 DB 验证边界�?

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontBigNumberControllerCommentReadabilityTest.php
RED:
- BigNumberController 缺少中文逻辑注释：前台大代理控制器�??
- BigNumberController 仍残留旧英文注释标题：Big-number agent portal (legacy /user/agents/*)�?
- 旧前�? agentsSignIn 中仍存在账号、密码�?�禁用等面向用户的硬编码提示，没有统�?�? Laravel 多语�? key�?
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\FrontBigNumberControllerCommentReadabilityTest.php
OK (3 tests, 44 assertions)

php -l app\Http\Controllers\Front\BigNumberController.php
No syntax errors detected in app\Http\Controllers\Front\BigNumberController.php

php -l tests\Feature\FrontBigNumberControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\FrontBigNumberControllerCommentReadabilityTest.php

vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_named_route_aliases_are_registered
OK (1 test, 156 assertions)

vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_main_ajax_routes_do_not_return_server_errors_without_guard_login
OK (1 test, 43 assertions)

rg -n "Big-number agent portal|账号或密码不能为空|无效账号|账号已被禁用|密码错误|璐﹀彿|鏃犳晥|瀵嗙爜|绂佺敤|閿欒" app\Http\Controllers\Front\BigNumberController.php -S
未命中旧英文标题、旧硬编码提示和典型乱码片段�?

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter big_number
No tests executed!

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter big
No tests executed!
说明：当�? FrontUiRegressionTest 没有匹配 big_number/big 过滤条件的方法名，因此不能作为大代理 UI 回归通过证据�?
```

### 相关接口消息与参数边�?

- `GET /agents/login`：旧前台大代理登录页面入口，绑定 `BigNumberController@agentsLogin`，参�? `langId` 表示旧系统语�?编号�?
- `POST /user/agents/signIn`：旧前台大代理登录接口，参数 `loginUid` 表示旧前台提交的大代理登录名，也兼容 `email`、`user_id`；参�? `loginPassword` 表示旧前台提交的大代理登录密码，也兼�? `password`�?
- `POST /api/front/auth/big-number/login`：新前台 big-number 登录接口，参�? `email` �? `user_id` 至少传一个，参数 `password` 表示登录密码；只�? `user_infos.account_type=1` 的代理账号允许登录，普�?�客户返�? `response.permission_denied`�?
- `POST /user/agents/proxy/proxySearch`：旧前台大代理直属代理列表接口，数据范围来自 `big_agents.sub_agent_ids`�?
- `POST /user/agents/proxy/proxySearchBySub`、`POST /user/agents/position/positionSummarySearch`、`POST /user/agents/position/subAgentsListSearch`：旧前台大代理代理网络列表和持仓汇�?�接口，`includeDescendants=true` 时会把直属代理的下级代理纳入查询�?
- `POST /user/agents/close/closeOrderSearch` �? `POST /user/agents/open/openOrderSearch`：旧前台大代理订单接口，参数 `open` 表示是否查询未平仓订单；订单客户范围只来自当前大代理可见代理网络�? `agent_descendants.descendant_type=2` 的客户节点�??
- `POST /user/agents/changePassword`：旧前台大代理修改密码接口，参数 `old_password` / `oldPassword` / `old_psw` 表示旧密码，参数 `password` / `new_password` / `newPassword` 表示新密码�??

### 多语�?响应调整

- 旧大代理登录账号或密码缺失：`errpsw = __('auth.password_required')`�?
- 旧大代理账号不存在或密码错误：`notactive` / `errpsw = __('auth.failed')`�?
- 旧大代理账号禁用：`notactive = __('auth.account_disabled')`�?
- 旧大代理改密旧密码错误：`msg = __('auth.old_password_error')`，同时保留旧前台识别�? `errorType=OLD_PASSWORD`�?
- �? big-number API 登录失败：继续�?�过统一 `ApiResponse` 返回多语�?后的 `__('auth.failed')`�?

### 验证边界

本轮只补�? `BigNumberController` 的中文�?�辑注释、参数说明和用户可见错误提示多语�?来源，没有改变旧路由 URI、旧 JSON 字段名�?�分页结构�?�`big_agents.sub_agent_ids` 范围、`UserTrade::open()` / `UserTrade::closed()` 查询规则或新 API 登录权限判断。由于本轮未连接真实 MySQL，没有用真实 `big_agents` 账号测试登录、禁用拦截�?�登录日志写入�?�token 写入、下级代理列表�?�持仓汇总和订单查询；真�? DB 恢复后仍�?用启�?/禁用大代理账号分别实测旧入口和新入口�?

## 120. 2026-06-09 前台找回密码 ForgotPasswordController 中文逻辑注释与多语言 key 修复

本轮继续推进 plan.md 中�?�后端必须支持多语言”�?�所有模块的文件及参数必须有详细中文注释和�?�辑注释”的要求，维�? `app/Http/Controllers/Front/ForgotPasswordController.php`。该控制器同时承载新前台找回密码接口和旧前台找回密码兼容接口，直接影响验证码缓存、邮箱账号校验�?�密码重置�?�旧页面错误码和登录链路恢复�?

### 本轮维护文件

- `tests/Feature/FrontForgotPasswordControllerCommentReadabilityTest.php`：新增静态中文注释与多语�?响应可读性测试，只读取控制器源码和语�?包，不连接真实数据库�?
- `app/Http/Controllers/Front/ForgotPasswordController.php`：补齐控制器类�?�发送验证码、重置密码�?�旧用户信息校验、旧验证码校验�?�旧保存新密码�?�旧成功响应和旧失败响应的中文�?�辑注释与参数说明�??
- `docs/admin-backend-blade-permission-final-checklist.md`：追加本节，记录本轮找回密码入口维护、接口消息�?�验证命令和真实 DB/邮件验证边界�?

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontForgotPasswordControllerCommentReadabilityTest.php
RED:
- ForgotPasswordController 缺少中文逻辑注释：前台找回密码控制器�?
- 用户不存在响应仍使用 auth.user_not_found，但当前 auth.php 没有 user_not_found 语言 key�?
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\FrontForgotPasswordControllerCommentReadabilityTest.php
OK (3 tests, 37 assertions)

php -l app\Http\Controllers\Front\ForgotPasswordController.php
No syntax errors detected in app\Http\Controllers\Front\ForgotPasswordController.php

php -l tests\Feature\FrontForgotPasswordControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\FrontForgotPasswordControllerCommentReadabilityTest.php

vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_named_route_aliases_are_registered
OK (1 test, 156 assertions)

vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_main_ajax_routes_do_not_return_server_errors_without_guard_login
OK (1 test, 43 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_naive_public_front_pages_are_independent_from_layui_and_dashboard
OK (1 test, 27 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_public_front_auth_apis_use_readable_hardcoded_resource_urls
OK (1 test, 20 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_public_front_auth_api_legacy_aliases_are_removed
OK (1 test, 61 assertions)
```

### 相关接口消息与参数边�?

- `GET /user/forget_password`：旧前台找回密码页面入口，绑�? `ForgotPasswordController@showForgotPassword`�?
- `POST /api/front/auth/password/email-code`：新前台发�?�找回密码验证码接口，参�? `email` 表示接收验证码的登录邮箱；旧参数 `useremail` 会归�?化为 `email`�?
- `POST /api/front/auth/password/reset`：新前台密码重置接口，参�? `email` 表示登录邮箱，参�? `code` 表示验证码，旧参�? `codedata` 会归�?化为 `code`，参�? `password_confirmation` 表示 Laravel `confirmed` 规则使用的确认密码�??
- `POST /user/check_user_info`：旧前台找回密码第一步校验接口，参数 `userId/user_id` 表示业务用户 ID，参�? `useremail/email` 表示登录邮箱；返回旧页面脚本识别�? `IDerror`、`UserDisable`、`emailerror`�?
- `POST /user/forgetpswSendCode`：旧前台发�?�验证码接口，复�? `sendResetCode`，旧请求会返�? `{status: true}` 以兼容历史脚本�??
- `POST /user/forgetPasswordInfoVerification`：旧前台验证码校验接口，参数 `codedata/code` 表示验证码；验证码错误返回旧错误�? `errorCodedate`�?
- `POST /user/change_password`：旧前台保存新密码接口，参数 `userId/user_id/accountno` 表示业务用户 ID，参�? `password/newPsw` 表示新密码，参数 `codedata/code` 表示验证码�??

### 多语�?响应调整

- 新接口用户不存在：从不存在的 `auth.user_not_found` 改为真实存在�? `response.user_not_found`�?
- 新接口验证码发�?�成功：继续使用 `auth.reset_code_sent`�?
- 新接口验证码无效或过期：继续使用 `auth.reset_code_invalid`�?
- 新接口密码重置成功：继续使用 `auth.password_reset_success`�?
- 参数校验失败：继续�?�过 Laravel Validator 返回具体验证错误，并使用 `ResponseCode::VALIDATION_FAILED`�?

### 验证边界

本轮没有改变找回密码路由 URI、旧前台 `msg/err/col` 响应字段、验证码缓存 key、验证码有效期�?�密�? Hash 写入方式或旧页面错误码，只补齐中文�?�辑注释和修正不存在的多语言 key。由于本轮未连接真实 MySQL、SMTP 或浏览器环境，没有声明真实邮箱发送�?�验证码收取、真实账号密码重置已经端到端通过；真实环境恢复后仍需使用真实 `user_logins.email` 请求验证码�?�读取邮件或测试缓存验证码�?�完�? `/api/front/auth/password/reset` 和旧 `/user/change_password` 两条链路验证�?

## 121. 2026-06-09 前台交易品种 TradeSymbolController 中文逻辑注释与真实下拉数据来源补�?

本轮继续推进 plan.md 中�?�所有模块的文件及参数必须有详细中文注释和�?�辑注释”�?�前端页面数据必须来自真实后端配置�?�的要求，维�? `app/Http/Controllers/Front/TradeSymbolController.php`。该控制器负�? `GET /api/front/trade-symbols`，为 Layui Blade �? Naive 风格页面提供交易品种动�?�下拉�?�项，直接影响持仓�?�订单等模块�? `symbol` 精确筛�?��??

### 本轮维护文件

- `tests/Feature/FrontTradeSymbolControllerCommentReadabilityTest.php`：新增静态中文注释与真实数据来源测试，只读取控制器源码，不连接真实数据库�?
- `app/Http/Controllers/Front/TradeSymbolController.php`：补齐控制器类�?�接口用途�?�真实表来源、新旧字段兼容�?�启用状态字段和返回结构的中文�?�辑注释�?
- `docs/admin-backend-blade-permission-final-checklist.md`：追加本节，记录本轮交易品种接口维护、接口消息�?�验证命令和真实 DB 验证边界�?

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontTradeSymbolControllerCommentReadabilityTest.php
RED:
- TradeSymbolController 缺少中文逻辑注释：前台交易品种控制器�?
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\FrontTradeSymbolControllerCommentReadabilityTest.php
OK (2 tests, 19 assertions)

php -l app\Http\Controllers\Front\TradeSymbolController.php
No syntax errors detected in app\Http\Controllers\Front\TradeSymbolController.php

php -l tests\Feature\FrontTradeSymbolControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\FrontTradeSymbolControllerCommentReadabilityTest.php

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_trade_symbol_filters_use_real_dynamic_options_in_layui_and_naive
OK (1 test, 34 assertions)

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_layui_module_pages_do_not_generate_local_mock_rows
OK (1 test, 9 assertions)
```

### 相关接口消息与参数边�?

- `GET /api/front/trade-symbols`：前台交易品种动态下拉接口，路由�? `front_api_trade_symbols`�?
- `symbol_prices`：交易品种真实数据表，是本接口唯�?数据来源�?
- `sym_symbol`：旧表结构中的交易品种字段�??
- `symbol`：新表结构中的交易品种字段�??
- `voided`：旧表结构中的启用状态字段，当前逻辑�? `voided=1` 过滤�?
- `status`：新表结构中的启用状态字段，当前逻辑�? `status=1` 过滤�?
- `list`：前�? select 组件使用的�?�项数组�?
- `value` / `label`：都使用交易品种编码，保证前端展示�?�与提交给后端筛选的 `symbol` 值一致�??
- `response.query_success`：查询成功多语言消息 key，由 `ApiResponse` 统一翻译�?

### 验证边界

本轮没有改变交易品种查询行为、路由�?�返回结构或前端动�?�下拉配置，只补齐中文�?�辑注释和专项测试�?�由于本轮未连接真实 MySQL，没有验证当�? `symbol_prices` 表中真实品种数量、`sym_symbol/symbol` 实际列名、`voided/status` 实际启用状�?�和页面下拉实际选项；真�? DB 恢复后仍�?请求 `/api/front/trade-symbols`，确认返回的 `list` 与真�? `symbol_prices` 启用品种�?致，并在持仓、订单页面�?�择某一品种后验证后端按同一 `symbol` 精确筛�?��??

## 122. 2026-06-09 前台支付回调 PaymentNotifyController 中文逻辑注释与旧回调边界补齐

本轮继续推进 plan.md 中�?�所有模块的文件及参数必须有详细中文注释和�?�辑注释”的要求，维�? `app/Http/Controllers/Front/PaymentNotifyController.php`。该控制器承载旧前台多条入金/出金支付回调路径，也承载新前�? `/api/front/payment/notify/{gateway}` �? `/api/front/payment/return/{gateway}`，属于资金链路的安全敏感入口�?

### 本轮维护文件

- `tests/Feature/FrontPaymentNotifyControllerCommentReadabilityTest.php`：新增静态中文注释与兼容边界测试，只读取控制器源码，不连接真实数据库、不触发真实支付通道�?
- `app/Http/Controllers/Front/PaymentNotifyController.php`：补齐控制器类�?�旧回调入口、异步�?�知、同步返回�?�旧网关映射的中文�?�辑注释与参数说明�??
- `docs/admin-backend-blade-permission-final-checklist.md`：追加本节，记录本轮支付回调入口维护、接口消息�?�验证命令和真实支付通道验证边界�?

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontPaymentNotifyControllerCommentReadabilityTest.php
RED:
- PaymentNotifyController 缺少中文逻辑注释：前台支付回调控制器�?
- PaymentNotifyController 仍残留旧英文注释标题：Payment gateway notify/return endpoints�?
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\FrontPaymentNotifyControllerCommentReadabilityTest.php
OK (2 tests, 16 assertions)

php -l app\Http\Controllers\Front\PaymentNotifyController.php
No syntax errors detected in app\Http\Controllers\Front\PaymentNotifyController.php

php -l tests\Feature\FrontPaymentNotifyControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\FrontPaymentNotifyControllerCommentReadabilityTest.php

vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_payment_callback_routes_are_registered
OK (1 test, 90 assertions)

vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_named_route_aliases_are_registered
OK (1 test, 156 assertions)

vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_public_smoke_routes_do_not_crash
OK (1 test, 17 assertions)
```

### 相关接口消息与参数边�?

- `POST /api/front/payment/notify/{gateway}`：新前台支付异步通知入口，参�? `gateway` 表示支付网关标识�?
- `GET /api/front/payment/return/{gateway}`：新前台支付同步返回入口，参�? `gateway` 表示支付网关标识，`status` 默认 `pending`�?
- `user/deposit_notfiy`、`user/deposit_notfiy2`、`user/deposit_tigerpay_notify`、`user/deposit_wppay_notify`、`user/deposit_exlink_*`、`user/deposit_btb_*`、`user/deposit_passto_notify`、`user/deposit_switch_notify`、`user/deposit_notfiy_otc`：旧前台入金回调兼容路径，统�?进入 `legacyCallback`�?
- `user/withdraw_notfiy_otc`、`user/withdraw_verify_otc`：旧前台出金回调兼容路径，当前只记录日志并返�? `success`，避免在未完成验签和出金确认规则迁移前误改出金状态�??
- `payload`：第三方支付平台回传的完整参数，当前通过 `Log::info` 记录�?
- `order_no / local_order_no / out_trade_no`：不同�?�道可能回传的本地订单号字段，当前按顺序兼容读取�?
- `DepositRecord`：对�? `deposit_records` 入金记录，当前按 `local_order_no` 定位记录�?
- `status=success`：第三方通知支付成功时才更新入金记录�?
- `status=02`：当前项目中入金记录已支付或待后台确认的状�?��?�，本轮不改变业务枚举�??
- `legacyGatewayName`：把旧路由路径映射为统一网关标识，例�? `wppay`、`exlink_bb`、`btb`、`otc_deposit`�?

### 验证边界

本轮没有改变支付回调路由、返�? `success` 字符串�?�同步返回重定向、入金状态更新字段或旧网关映射，只补齐中文�?�辑注释和专项测试�?�由于本轮未连接真实 MySQL、未配置真实支付通道、未执行第三方签名验签，不能声明真实通道回调已经安全完成；真实环境恢复后仍需�? `payment_channels` 配置逐个通道补齐验签、幂等处理�?�金额校验�?�订单归属校验和重复回调测试�?

## 123. 2026-06-09 旧前台页�? LegacyPageController 中文逻辑注释与反馈多语言修复

### 本次维护文件
- `app/Http/Controllers/Front/LegacyPageController.php`
- `tests/Feature/FrontLegacyPageControllerCommentReadabilityTest.php`
- `docs/admin-backend-blade-permission-final-checklist.md`

### TDD RED 依据
- 新增 `FrontLegacyPageControllerCommentReadabilityTest` 约束旧前台页面控制器必须具备中文功能逻辑注释�?
- 测试要求旧页面参�? `legacyParentUserId`、`legacyTargetUserId`、`legacyAddressId` �? `offweb_feedbacks` 写入边界必须有中文说明�??
- 测试要求旧意见反馈成功消息必须使用后端多语言 key `__('response.success')`，不能继续保留硬编码 `发�?�成功`�?

### 生产代码调整
- �? `LegacyPageController` 补齐旧前�? `legacy user/*` 页面入口�? `front_layui::*` Blade 模板的职责说明�??
- 为控制台、个人中心�?�账户�?�入金�?�出金�?�流水�?�代理�?�返佣�?�礼品�?�新闻等旧页面映射补充中文�?�辑注释�?
- 为旧路由透传参数补充参数含义：`legacyParentUserId` 表示直属客户页面的上级代理用�? ID，`legacyTargetUserId` 表示返佣转账或组别变更目标用�? ID，`legacyAddressId` 表示地址编辑记录 ID�?
- �? `feedback()` 补充 `email`、`username`、`phone`、`remarks` �? `offweb_feedbacks` 表写入边界说明�??
- 将旧意见反馈成功响应从硬编码文案改为 `__('response.success')`，保证后端多语言输出�?
- �? `logout()` 补充清理�? `user guard` 与旧 session `suser` 的�?�辑说明�?

### 接口与页面边�?
- `GET user/index`、`GET user/index/index`、`POST user/indexreg`、`GET user/main/home` 继续映射�? `front_layui::dashboard.index`�?
- `GET user/center*`、`GET user/editpsw`、`GET user/agents/editpsw` 继续映射�? `front_layui::profile.index`�?
- `GET user/account`、`GET user/voucher`、`GET user/deposit`、`GET user/withdraw`、`GET user/flow/main` 继续映射到账户�?�凭证�?�入金�?�出金与流水 Blade 页面�?
- `GET user/proxy/direct_cust_detail/{puid}` 将旧参数写入 `legacyParentUserId`�?
- `GET user/proxy/direct_user_commTrans_browse/{uid}` �? `GET user/cust/change/group/{uid}` 将旧参数写入 `legacyTargetUserId`�?
- `GET user/address/info/{recId}` 将旧地址记录 ID 写入 `legacyAddressId`�?
- `POST user/offweb/feedback` 继续写入 `offweb_feedbacks`，成功消息由 `resources/lang/*/response.php` �? `success` key 输出�?
- `GET user/loginOut` 继续清理 Laravel `user` guard 与旧前台 session `suser`�?

### 验证记录
- `vendor\bin\phpunit tests\Feature\FrontLegacyPageControllerCommentReadabilityTest.php`：�?�过�?2 tests / 19 assertions�?
- `php -l app\Http\Controllers\Front\LegacyPageController.php`：�?�过，无语法错误�?
- `php -l tests\Feature\FrontLegacyPageControllerCommentReadabilityTest.php`：�?�过，无语法错误�?
- `vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_user_module_routes_are_registered`：�?�过�?1 test / 482 assertions�?
- `vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_named_route_aliases_are_registered`：�?�过�?1 test / 156 assertions�?
- `vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_page_routes_render_without_crashing`：�?�过�?1 test / 46 assertions�?
- `vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_public_smoke_routes_do_not_crash`：�?�过�?1 test / 17 assertions�?
- `rg -n "发�?�成功|鍙戦€佹垚鍔|�|旧前台页面控制器|legacyParentUserId|legacyTargetUserId|legacyAddressId|response\.success" ...`：确�? `response.success` 和关键参数注释存在，未发现硬编码 `发�?�成功`�?

### 本轮边界
- 本轮不改旧页面路由�?�Blade 映射�? `offweb_feedbacks` 表结构，只完成旧页面控制器注释可读�?�与反馈成功消息多语�?修复�?
- 未执行真实浏览器表单提交；真�? DB 联调时仍�?用旧页面 `POST user/offweb/feedback` 做一次人工提�? smoke�?

## 124. 2026-06-09 旧维护入�? LegacyMaintenanceController 中文逻辑注释与禁用响应多语言修复

### 本次维护文件
- `app/Http/Controllers/Front/LegacyMaintenanceController.php`
- `tests/Feature/FrontLegacyMaintenanceControllerCommentReadabilityTest.php`
- `resources/lang/zh-CN/response.php`
- `resources/lang/en/response.php`
- `docs/admin-backend-blade-permission-final-checklist.md`

### TDD RED 依据
- 新增 `FrontLegacyMaintenanceControllerCommentReadabilityTest`，约束旧维护入口控制器必须说明导入用户�?�同步到 MT4、测试入金�?�测试出金等公开维护路由的禁用边界�??
- RED 阶段失败点：`LegacyMaintenanceController` 缺少 `旧维护入口控制器`、`legacyAction 表示旧项目维护入口动作名` 等中文�?�辑注释�?
- RED 阶段失败点：旧维护入口禁用消息仍硬编码英�? `Legacy maintenance action is disabled...`，未使用后端语言�? key�?

### 生产代码调整
- �? `LegacyMaintenanceController` 补充类级中文逻辑注释，明确旧项目公开维护入口迁移后只能保留兼容路由，不能继续公开执行导入、同步或测试写入动作�?
- �? `importUser`、`importAgents`、`syncToT4ByLocalAgents`、`syncToT4ByLocalUser`、`localRegisterNotifyByAgents`、`syncAgents`、`syncUser`、`syncDisableUserToT4`、`importLang`、`testDeposit`、`testWithdraw` 等入口补充中文用途和禁用边界注释�?
- �? `testSearch(Request $request, $id)` 补充 `$id` 参数含义，说明该参数只保留旧路由签名兼容�?
- �? `disabledMaintenanceResponse()` 补充 `$request`、`$legacyAction`、`action`、`path`、`legacy_action` 的中文�?�辑含义�?
- 将禁用响应消息改�? `__('response.legacy_maintenance_disabled')`，新增中英文语言�? key�?

### 接口与页面边�?
- `GET /importUser`、`GET /importAgents`、`GET /syncToT4ByLocalAgents` 继续返回 423，不恢复公开维护执行逻辑�?
- `POST /syncToT4ByLocalUser`、`POST /localRegisterNotifyByAgents`、`POST /syncAgents`、`POST /syncUser`、`POST /syncDisableUserToT4` 继续返回 423�?
- `GET /importLang`、`GET /test`、`POST /test/deposit`、`POST /test/withdraw`、`GET /test_rights_sum`、`GET /test_serach/{id}` 等旧测试入口继续返回 423�?
- 响应 `data.legacy_action` 保留旧动作名，`data.path` 保留触发路径，方便旧调用方和测试定位命中的禁用入口�??
- 禁用日志仍写�? `front.legacy_maintenance.disabled`，字段包�? `action`、`path`、`ip`�?

### 验证记录
- `vendor\bin\phpunit tests\Feature\FrontLegacyMaintenanceControllerCommentReadabilityTest.php`：�?�过�?2 tests / 13 assertions�?
- `vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_user_maintenance_and_big_agent_routes_are_registered`：�?�过�?1 test / 190 assertions�?
- `vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_public_smoke_routes_do_not_crash`：�?�过�?1 test / 17 assertions�?
- `php -l app\Http\Controllers\Front\LegacyMaintenanceController.php`：�?�过，无语法错误�?
- `php -l tests\Feature\FrontLegacyMaintenanceControllerCommentReadabilityTest.php`：�?�过，无语法错误�?
- `php -l resources\lang\zh-CN\response.php`：�?�过，无语法错误�?
- `php -l resources\lang\en\response.php`：�?�过，无语法错误�?
- Laravel HTTP Kernel smoke：`GET /importUser` 返回 `status=423`，`data.legacy_action=importUser`，确认旧维护入口仍保持禁用响应�??

### 本轮边界
- 本轮不恢复任何旧维护、导入�?�同步�?�测试写入动作，只提升注释可读�?�和禁用响应多语�?维护性�??
- 后续如需实现真实导入或同步，必须迁移到受保护�? Artisan 命令或后台任务，并重新设计权限�?�审计�?�幂等和真实 DB 测试数据�?

## 125. 2026-06-09 后台 Blade 总布�? UI 参�?�标记与信息密度增强

### 本次维护文件
- `resources/admin/layui/layouts/app.blade.php`
- `public/css/admin/style.css`
- `tests/Feature/AdminLayoutUiReferenceDensityTest.php`
- `docs/admin-backend-blade-permission-final-checklist.md`

### TDD RED 依据
- 新增 `AdminLayoutUiReferenceDensityTest`，约束后�? Blade 总布�?必须显式声明 UI 参�?�来源：Vben Admin、Vue Naive Admin、Naive UI Admin、Ant Design Pro、Arco Design Pro�?
- RED 阶段失败点：后台总布�?缺少 `data-ui-reference`，无法从结构上证明当�? Blade/Layui 外壳仍按 plan.md �? 7 节参考体系维护�??
- RED 阶段失败点：后台公共 CSS 缺少 `--admin-content-gap`、`--admin-panel-padding`、`--admin-toolbar-height`、吸顶页头�?�页头工具区和统�?工具条等现代中后台信息密度规则�??

### 生产代码调整
- 在后台�?�布�? `<body>` 增加 `data-ui-reference="Vben Admin, Vue Naive Admin, Naive UI Admin, Ant Design Pro, Arco Design Pro"`，同时保�? `data-render-mode="blade"`，明确当前项目仍�? Laravel Blade 渲染�?
- 将后台页头拆分为 `crm-admin-page-head-main` �? `crm-admin-page-head-tools`，左侧承载页面标题，右侧承载面包屑和后续工具按钮�?
- �? `public/css/admin/style.css` 增加后台 UI 参�?�层中文注释，说明在 Blade + Layui 约束下吸�? Vben/Naive/Ant/Arco 的信息密度和组件秩序�?
- 新增密度变量：`--admin-content-gap`、`--admin-panel-padding`、`--admin-toolbar-height`，供紧凑/舒�?�模式和业务面板统一复用�?
- �? `crm-admin-page-head` 增加 `position: sticky`、`top: 0`、�?�明面板背景�? `backdrop-filter`，提升后台长表格页面的页头可见�?��??
- 新增 `crm-admin-toolbar`、`crm-admin-density-compact`、`crm-admin-density-comfortable`，为后续模块统一工具条和密度切换预留稳定 CSS 入口�?
- 移动端下�? `crm-admin-page-head-tools` �? `crm-admin-toolbar` 自动换行，避免面包屑、筛选工具和按钮拥挤溢出�?

### 页面边界
- `GET /admin/dashboard` 仍使�? Laravel Blade + Layui 总布�?渲染，HTTP Kernel smoke 返回 200，并能看�? `data-ui-reference`�?
- `GET /admin/login` 是独立登录页模板，未继承后台工作台�?�布�?，因此不强制包含 `data-ui-reference`�?
- 本轮只调整后台全�?外壳与公�? CSS，不改任何后台业务接口�?�菜单权限�?�按钮权限和表格数据逻辑�?

### 验证记录
- `vendor\bin\phpunit tests\Feature\AdminLayoutUiReferenceDensityTest.php`：�?�过�?2 tests / 25 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminLayoutUiModernizationTest.php`：�?�过�?2 tests / 33 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminLayoutShellReadabilityTest.php`：�?�过�?2 tests / 18 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminBladePagePanelModernizationTest.php`：�?�过�?1 test / 1 assertion�?
- `php -l resources\admin\layui\layouts\app.blade.php`：�?�过，无语法错误�?
- `php -l tests\Feature\AdminLayoutUiReferenceDensityTest.php`：�?�过，无语法错误�?
- `rg -n "data-ui-reference|crm-admin-page-head-main|crm-admin-page-head-tools|后台 UI 参�?�层|--admin-content-gap|--admin-panel-padding|--admin-toolbar-height|crm-admin-density-compact|crm-admin-toolbar|position: sticky" ...`：确认布�?�? CSS 关键片段存在�?
- Laravel HTTP Kernel smoke：`GET /admin/dashboard` 返回 200 且包�? `data-ui-reference`；`GET /admin/login` 返回 200，登录页保持独立模板边界�?

### 本轮边界
- 本轮没有启动浏览器截图验证；属于 Blade/CSS 静�?�和 HTTP smoke 改进。后续如继续深入 UI，需要启动本地服务后用浏览器�?查桌面和移动端实际视觉效果�??
- 后台仍需继续审计各业务页面是否充分使用统�?工具条�?�面板密度和按钮权限刷新，本轮只是给全局外壳建立稳定规则�?

## 126. 2026-06-09 后台 Blade 菜单页面真实 DB 权限覆盖修复

### 本次维护文件
- `database/migrations/2026_06_09_000001_fix_admin_page_menu_permission_routes.php`
- `tests/Feature/AdminPageMenuPermissionCoverageTest.php`
- `docs/admin-backend-blade-permission-final-checklist.md`

### TDD RED 依据
- 新增 `AdminPageMenuPermissionCoverageTest`，直接读取真�? DB �? `permissions` 表，验证后台 `admin_page_*` Blade 页面菜单入口必须存在唯一启用�? `permissions.route` 配置�?
- RED 阶段失败点：`/admin/dashboard`、`/admin/menus`、`/admin/permissions`、`/admin/roles` 已注�? Blade 页面路由，但真实 DB 缺少 `permissions.route` 菜单权限配置�?
- 真实 DB 额外审计发现：`/admin/users` 存在 19 条启用的重复菜单权限 slug，会导致菜单树重复�?�角色授权混乱和后续页面权限审计误判�?

### 生产代码调整
- 新增迁移 `FixAdminPageMenuPermissionRoutes`�?
- 通过 `upsertAdminMenuPermission()` 补齐 4 个后台核心页面菜单权限：
  - `admin_dashboard`：`route=/admin/dashboard`，`api_route=admin_api_dashboardData`�?
  - `admin_roles`：`route=/admin/roles`，`api_route=admin_api_roleList`�?
  - `admin_permissions`：`route=/admin/permissions`，`api_route=admin_api_permissionTree`�?
  - `admin_menus`：`route=/admin/menus`，`api_route=admin_api_menuTree`�?
- 通过 `mergeDuplicateAdminRoute('/admin/users')` 合并重复用户菜单权限：保留最早启用的 `permissions.id=3`，把重复权限上的 `role_permissions` 授权迁移到保留权限，再禁用重复权限并写入 `deleted_at`�?
- 回滚边界：只禁用本迁移补齐的 4 个核心页�? slug，不重新制�?�历史重�? `/admin/users` 权限�?

### 参数与数据表字段含义
- `slug`：权限稳定标识，后台菜单、按钮显隐和角色授权共同依赖该字段�??
- `route`：后�? Blade 页面访问路径，用于左侧菜单跳转和页面权限覆盖审计�?
- `api_route`：该页面主要读取或维护接口的 Laravel 命名路由，后端接口最终由 `check.permission:admin` 按该字段鉴权�?
- `guard_type=admin`：表示该权限只属于后台管理员体系，不能与前台代理�?/普�?�客户菜单混用�??
- `role_permissions.role_id`：被授权角色 ID，对�? `roles.id`�?
- `role_permissions.permission_id`：被授权权限 ID，对�? `permissions.id`；重复权限合并时会迁移到保留权限 ID�?

### 相关接口与页面消�?
- `GET /admin/dashboard`：后台控制台 Blade 页面，菜单权�? slug �? `admin_dashboard`�?
- `GET /admin/roles`：后台角色管�? Blade 页面，菜单权�? slug �? `admin_roles`�?
- `GET /admin/permissions`：后台权限管�? Blade 页面，菜单权�? slug �? `admin_permissions`�?
- `GET /admin/menus`：后台菜单管�? Blade 页面，菜单权�? slug �? `admin_menus`�?
- `GET /admin/users`：后台用户管�? Blade 页面，当前真�? DB 只保留一条启用菜单权�? `admin_users_6a23fb27413fd`�?
- `POST /api/admin/menus`：后台菜单接口会继续�? `permissions` �? `role_permissions` 读取菜单和按钮权限，本迁移补齐的数据会被该接口消费�??

### 验证记录
- `vendor\bin\phpunit tests\Feature\AdminPageMenuPermissionCoverageTest.php`：RED 阶段失败，缺�? `/admin/dashboard`、`/admin/menus`、`/admin/permissions`、`/admin/roles` 的真�? DB 权限配置�?
- `php -l database\migrations\2026_06_09_000001_fix_admin_page_menu_permission_routes.php`：�?�过，无语法错误�?
- `php -l tests\Feature\AdminPageMenuPermissionCoverageTest.php`：�?�过，无语法错误�?
- `php artisan migrate`：已执行 `2026_06_09_000001_fix_admin_page_menu_permission_routes`�?
- `vendor\bin\phpunit tests\Feature\AdminPageMenuPermissionCoverageTest.php`：�?�过�?1 test / 2 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminBladeButtonPermissionRouteCoverageTest.php`：�?�过�?1 test / 233 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminBladeButtonPermissionCoverageTest.php`：�?�过�?2 tests / 117 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminButtonPermissionVisibilityTest.php`：�?�过�?2 tests / 37 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminPermissionPlanTest.php`：�?�过�?4 tests / 9 assertions�?
- 真实 DB 复查：`/admin/dashboard`、`/admin/menus`、`/admin/permissions`、`/admin/roles` 已存在启用权限；`/admin/users` 只剩 1 条启用权限�??

### 本轮边界
- 本轮修复的是后台 Blade 页面菜单权限字典完整性与重复 route 数据问题，没有改动前台菜单�?�后台按�? `data-permission`、接口中间件白名单或业务数据范围查询逻辑�?
- 根目录未发现用户提到�? `plan.md`，本轮按当前项目内的 `docs/admin-auth-permission-plan.md` �? `docs/admin-auth-permission-execution-checklist.md` 继续推进权限闭环�?

### 126 补充修复：测试污染源与全量重�? route 清理
- 复跑 `AdminPageMenuPermissionCoverageTest` 时发�? `/admin/users` 仍出现第二条启用权限 `admin_users_6a26fad1ecbd9`�?
- 根因定位�? `tests/Feature/AdminPermissionPlanTest.php` 会在真实 MySQL 中创建随�? `admin_users_*` �? route �? `/admin/users` 的测试菜单权限，测试结束后会污染真实权限字典�?
- 已修�? `AdminPermissionPlanTest`：临时菜�? slug 改为 `test_admin_users_*`，临�? route 改为 `/admin/__test-users`，并�? `tearDown()` 中清�? `test_admin_users_*` �? `test_admin_user_review_auth_*` 测试权限�?
- 新增迁移 `database/migrations/2026_06_09_000002_merge_duplicate_admin_permission_routes.php`，扫描所�? `guard_type=admin`、`status=1` 的重复页�? route，保留最早权限�?�迁�? `role_permissions`、禁用重复记录�??
- `php artisan migrate` 已执�? `2026_06_09_000002_merge_duplicate_admin_permission_routes`，当前迁移状态为 Yes�?
- 复查真实 DB：`duplicate_enabled_admin_routes=0`，表示后台启用页�? route 已无重复�?
- 补充验证�?
  - `php -l database\migrations\2026_06_09_000002_merge_duplicate_admin_permission_routes.php`：�?�过，无语法错误�?
  - `php -l tests\Feature\AdminPermissionPlanTest.php`：�?�过，无语法错误�?
  - `vendor\bin\phpunit tests\Feature\AdminPageMenuPermissionCoverageTest.php`：�?�过�?1 test / 2 assertions�?
  - `vendor\bin\phpunit tests\Feature\AdminPermissionPlanTest.php`：�?�过�?4 tests / 9 assertions�?
  - `vendor\bin\phpunit tests\Feature\AdminBladeButtonPermissionRouteCoverageTest.php`：�?�过�?1 test / 233 assertions�?
  - `vendor\bin\phpunit tests\Feature\AdminBladeButtonPermissionCoverageTest.php`：�?�过�?2 tests / 117 assertions�?
  - `vendor\bin\phpunit tests\Feature\AdminButtonPermissionVisibilityTest.php`：�?�过�?2 tests / 37 assertions�?

## 127. 2026-06-09 JWT 鉴权中间件错误响应多语言修复

### 本次维护文件
- `app/Http/Middleware/JwtAuthMiddleware.php`
- `tests/Feature/JwtAuthMiddlewareLocalizationTest.php`
- `docs/admin-backend-blade-permission-final-checklist.md`

### TDD RED 依据
- 新增 `JwtAuthMiddlewareLocalizationTest`，约�? JWT 鉴权中间件不能继续硬编码英文错误消息�?
- RED 阶段失败点：缺少 Token 时仍返回 `'Authorization token not found'`，用户不存在时仍返回 `'User not found'`�?
- RED 阶段失败点：中间件缺�? `$guard`、`$header`、`$token`、`$payload`、`$decodedGuard` 等核心参数的中文逻辑含义说明�?

### 生产代码调整
- `JwtAuthMiddleware` 引入 `App\Constants\ResponseCode`，认证错误统�?使用项目状�?�码常量�?
- 缺少 `Authorization: Bearer ...` 请求头时返回 `__('response.token_missing')` �? `ResponseCode::TOKEN_MISSING`�?
- JWT 载荷中的用户 ID 无法在当�? guard 下找到用户时返回 `__('response.user_not_found')` �? `ResponseCode::USER_NOT_FOUND`�?
- JWT 解析异常统一返回 `__('response.auth_failed')` �? `ResponseCode::AUTH_FAILED`，避免把内部异常文本直接暴露给前端�??
- �? `$guard`、`$header`、`$token`、`$payload`、`$decodedGuard` 补充中文逻辑注释，说明前�? user 与后�? admin 双守卫下的参数用途�??

### 参数与接口边�?
- `$guard`：当前认证守卫，`user` 表示前台用户，`admin` 表示后台管理员�??
- `$header`：HTTP `Authorization` 请求头，必须符合 `Bearer {token}` 格式�?
- `$token`：Bearer 后面�? JWT 字符串，只用于解析身份，不写入响应�??
- `$payload`：JWT 解析后的载荷，包�? `sub`、`guard`、`jti` 等认证与单点登录字段�?
- `$decodedGuard`：令牌载荷中的守卫类型，用于兼容前台与后台登录入口�??
- `POST /api/admin/profileInfo`、`POST /api/admin/menus`、前台受保护接口等所有经�? `jwt.auth` 的接口都会消费本次修复后的多语言认证失败消息�?

### 验证记录
- `vendor\bin\phpunit tests\Feature\JwtAuthMiddlewareLocalizationTest.php`：RED 阶段失败，命中硬编码英文响应与缺少中文参数说明�??
- `php -l app\Http\Middleware\JwtAuthMiddleware.php`：�?�过，无语法错误�?
- `php -l tests\Feature\JwtAuthMiddlewareLocalizationTest.php`：�?�过，无语法错误�?
- `vendor\bin\phpunit tests\Feature\JwtAuthMiddlewareLocalizationTest.php`：�?�过�?2 tests / 9 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminCheckPermissionMiddlewareReadabilityTest.php`：�?�过�?1 test / 23 assertions�?
- Laravel HTTP Kernel smoke：无 Token 请求 `POST /api/admin/profileInfo`，`zh-CN` 返回 `code=4004,message=令牌缺失`；`en` 返回 `code=4004,message=Token is missing`�?

### 本轮边界
- 本轮只修�? JWT 认证入口的多语言响应和中文参数注释，没有改动 JWT 签发、刷新�?�SSO 校验、角色权限和业务数据范围逻辑�?
- 仍需继续审计其他服务类中的英文日志或第三方接口内部消息，但本轮已覆盖前后�? API �?核心的认证失败响应入口�??

## 128. 2026-06-09 MT4 Manager 服务错误响应多语�?与中文参数注释修�?

### 本次维护文件
- `app/Services/Mt4ManagerService.php`
- `resources/lang/zh-CN/response.php`
- `resources/lang/en/response.php`
- `tests/Feature/Mt4ManagerServiceLocalizationTest.php`
- `docs/admin-backend-blade-permission-final-checklist.md`

### TDD RED 依据
- 新增 `Mt4ManagerServiceLocalizationTest`，约�? MT4 服务返回数组中的用户可见 `message` 不能继续写死英文�?
- RED 阶段失败点：连接失败仍返�? `'Connection failed'`，读取超时仍返回 `'Read timeout or empty response'`�?
- RED 阶段失败点：`Mt4ManagerService` 缺少 `$host`、`$port`、`$apiKey`、`$apiVersion`、`$timeout`、`$cmd`、`$params`、`$paramStr`、`$fullCmd`、`$response`、`$parts`、`$status` 等核心参数和解析变量的中文�?�辑说明�?

### 生产代码调整
- `Mt4ManagerService` 增加类级中文说明，明确该服务负责把开户注册�?�入金�?�出金�?�改密�?�锁定�?�组别变更等动作转换�? MT4 Manager Socket 命令�?
- 给构造参数补齐中文含义：`$host` 表示 MT4 Manager API 主机地址，`$port` 表示端口，`$apiKey` 表示授权密钥，`$apiVersion` 表示协议版本，`$timeout` 表示 Socket 连接和读取超时时间�??
- �? `sendCommand($cmd, $params = [])` 补齐中文逻辑说明，明�? `$cmd` �? MT4 命令名称，`$params` 是命令参数键值对，`$paramStr` 是协议参数片段，`$fullCmd` 是最终写�? Socket 的完整命令字符串，`$response`、`$parts`、`$status` �? MT4 响应解析链路�?
- 将连接失败响应改�? `__('response.mt4_connection_failed')`�?
- 将读取失败或超时响应改为 `__('response.mt4_read_timeout')`�?
- 中英�? `response.php` 新增 `mt4_connection_failed` �? `mt4_read_timeout`，保证后端响应可以随当前 locale 输出�?

### 相关接口和消�?
- `Mt4ManagerService::registerUser()`：写�? `USER_RECORD_NEW` 命令，用�? MT4 �?户�??
- `Mt4ManagerService::deposit()`：写�? `USER_DEPOSIT` 命令，用�? MT4 入金�?
- `Mt4ManagerService::withdrawal()`：写�? `USER_WITHDRAW` 命令，用�? MT4 出金�?
- `Mt4ManagerService::getAccountInfo()`：写�? `USER_INFO_GET` 命令，用于读取余额�?�净值�?�保证金和杠杆等账户信息�?
- `Mt4ManagerService::changePassword()`：写�? `USER_PASS_CHANGE` 命令，用�? MT4 密码修改�?
- `Mt4ManagerService::lockUser()` / `unlockUser()`：写�? `USER_LOCK` 命令，用于禁用或恢复交易�?
- `Mt4ManagerService::changeGroup()`：写�? `USER_GROUP_CHANGE` 命令，用于调�? MT4 组别�?
- `Mt4ManagerService::updateComment()`：写�? `USER_COMMENT_UPDATE` 命令，用于更�? MT4 备注字段�?
- `response.mt4_connection_failed`：MT4 连接失败，中文为 `MT4连接失败`，英文为 `MT4 connection failed`�?
- `response.mt4_read_timeout`：MT4 读取超时或响应为空，中文�? `MT4读取超时或响应为空`，英文为 `MT4 read timeout or empty response`�?

### 验证记录
- `vendor\bin\phpunit tests\Feature\Mt4ManagerServiceLocalizationTest.php`：RED 阶段失败，命中硬编码英文响应和中文参数注释缺口�??
- `php -l app\Services\Mt4ManagerService.php`：�?�过，无语法错误�?
- `php -l resources\lang\zh-CN\response.php`：�?�过，无语法错误�?
- `php -l resources\lang\en\response.php`：�?�过，无语法错误�?
- `php -l tests\Feature\Mt4ManagerServiceLocalizationTest.php`：�?�过，无语法错误�?
- `vendor\bin\phpunit tests\Feature\Mt4ManagerServiceLocalizationTest.php`：�?�过�?2 tests / 20 assertions�?

### 本轮边界
- 本轮只修�? MT4 Manager 服务自身返回错误的多语言文案和中文参数�?�辑注释，没有实际连�? MT4 服务器，也没有改变任何命令名称�?�参数编码�?�Socket 写入、响应解析或上层资金业务流程�?
- `Log::warning('MT4 API is disabled in config.')`、`Log::error("MT4 Connection Error...")` �? `Log::error("MT4 Read Error...")` 仍保留为运维日志文本；本轮重点是用户可见响应 message 的多语言化�??

## 129. 2026-06-09 JWT 服务异常响应多语�?与中文参数注释修�?

### 本次维护文件
- `app/Services/JwtService.php`
- `resources/lang/zh-CN/response.php`
- `resources/lang/en/response.php`
- `tests/Feature/JwtServiceLocalizationTest.php`
- `docs/admin-backend-blade-permission-final-checklist.md`

### TDD RED 依据
- 新增 `JwtServiceLocalizationTest`，约�? JWT 服务层不能继续硬编码英文异常文案�?
- RED 阶段失败点：`parseToken()` 中黑名单命中仍抛�? `'Token has been invalidated'`�?
- RED 阶段失败点：`refreshToken()` 超出刷新窗口仍抛�? `'Token cannot be refreshed, refresh window expired'`�?
- RED 阶段失败点：刷新失败仍拼�? `'Token refresh failed: '` 英文前缀�?
- RED 阶段失败点：`JwtService` 缺少 `$secret`、`$ttl`、`$refreshTtl`、`$algo`、`$payload`、`$jti`、`$mergedPayload`、`$decoded`、`$cacheKey`、`$token`、`$newPayload` 等核心认证参数中文�?�辑说明�?

### 生产代码调整
- �? `JwtService.php` 重写�? UTF-8 可读中文注释版本，保留原有方法和认证流程：`generateToken()`、`parseToken()`、`refreshToken()`、`invalidateToken()`、`getPayload()`�?
- 补齐类级说明，明确该服务是前�? `user` 与后�? `admin` 共用�? JWT 签发、解析�?�刷新和失效服务�?
- 补齐安全字段说明：`sub` 表示登录主体 ID，`guard` 表示认证守卫，`jti` 表示令牌唯一编号，SSO 缓存只保存当前有�? `jti`�?
- `parseToken()` 中黑名单命中改为 `__('response.jwt_token_invalidated')`�?
- `refreshToken()` 超出刷新窗口改为 `__('response.jwt_refresh_window_expired')`�?
- `refreshToken()` 捕获异常后的错误前缀改为 `__('response.jwt_refresh_failed')`�?
- 中英�? `response.php` 新增 `jwt_token_invalidated`、`jwt_refresh_window_expired`、`jwt_refresh_failed`，保证服务层异常可被上层按当�? locale 输出�?

### 相关接口和消�?
- `JwtService::generateToken(array $payload)`：生成前台或后台 JWT，写�? `iss`、`iat`、`exp`、`nbf`、`jti` 与业务载荷，并把当前 `jti` 写入 SSO 缓存�?
- `JwtService::parseToken(string $token)`：解析并校验 JWT，黑名单命中时返回多语言�? `response.jwt_token_invalidated`�?
- `JwtService::refreshToken(string $token)`：在刷新窗口内刷�? JWT，超出窗口时返回 `response.jwt_refresh_window_expired`，刷新失败统�?使用 `response.jwt_refresh_failed` 前缀�?
- `JwtService::invalidateToken(string $token)`：把当前 JWT �? `jti` 写入黑名单，并在当前 token �? SSO �?�? token 时清�? SSO 缓存�?
- `JwtService::getPayload(string $token)`：在�?出和刷新场景读取 JWT 载荷，不按普通访�? token 过期时间拦截�?
- `response.jwt_token_invalidated`：中文为 `令牌已失效`，英文为 `Token has been invalidated`�?
- `response.jwt_refresh_window_expired`：中文为 `令牌已超过刷新窗口，请重新登录`，英文为 `Token cannot be refreshed, refresh window expired`�?
- `response.jwt_refresh_failed`：中文为 `令牌刷新失败`，英文为 `Token refresh failed`�?

### 验证记录
- `vendor\bin\phpunit tests\Feature\JwtServiceLocalizationTest.php`：RED 阶段失败，命中英文硬编码异常和中文参数注释缺口�??
- `php -l app\Services\JwtService.php`：�?�过，无语法错误�?
- `php -l resources\lang\zh-CN\response.php`：�?�过，无语法错误�?
- `php -l resources\lang\en\response.php`：�?�过，无语法错误�?
- `php -l tests\Feature\JwtServiceLocalizationTest.php`：�?�过，无语法错误�?
- `vendor\bin\phpunit tests\Feature\JwtServiceLocalizationTest.php`：�?�过�?2 tests / 23 assertions�?
- `vendor\bin\phpunit tests\Feature\JwtAuthMiddlewareLocalizationTest.php`：�?�过�?2 tests / 9 assertions，确认上�? JWT 中间件多语言响应未被破坏�?

### 本轮边界
- 本轮只修�? `JwtService` 服务层异常文案多语言化和中文参数逻辑注释，没有改�? JWT 签名算法、TTL、刷新窗口�?�SSO 缓存键�?�黑名单键�?�Token 载荷结构或中间件鉴权流程�?
- `refreshToken()` 仍保留原有异常包装模式，只把错误前缀改为多语�? key；底层异常详情仍追加在冒号后，便于调试定位�??

## 130. 2026-06-09 用户注册服务身份证重复提示多语言与中文参数注释修�?

### 本次维护文件
- `app/Services/UserRegistrationService.php`
- `resources/lang/zh-CN/response.php`
- `resources/lang/en/response.php`
- `tests/Feature/UserRegistrationServiceLocalizationTest.php`
- `docs/admin-backend-blade-permission-final-checklist.md`

### TDD RED 依据
- 新增 `UserRegistrationServiceLocalizationTest`，约束注册服务不能继续把 `__('front.id_card_no')` 与英�? `' already exists'` 拼接成半中半英提示�??
- RED 阶段失败点：`validateRegistrationData()` �? `validateRegistration()` 中身份证号重复提示仍使用英文拼接�?
- RED 阶段失败点：注册服务缺少 `$data`、`$parentId`、`$accountType`、`$commissionMode`、`$userId`、`$userLogin`、`$userInfo`、`$parentInfo`、`$familyTree`、`$treeIds` 等核心参数和中间变量的中文�?�辑说明�?

### 生产代码调整
- 将正式注册校�? `validateRegistrationData()` 中的身份证号重复提示改为 `__('response.id_card_exists')`�?
- 将注册前置验�? `validateRegistration()` 中的身份证号重复提示改为 `__('response.id_card_exists')`�?
- 中英�? `response.php` 新增 `id_card_exists`，中文为 `证件号码已存在`，英文为 `ID card number already exists`�?
- 补齐 `register()` 参数说明，明�? `$data` 是注册表单数据，`$parentId` 是邀请人业务 user_id，`$accountType` 是注册账号类型，`$commissionMode` 是注册返佣模式�??
- 补齐注册写库过程说明，明�? `$userId`、`$userLogin`、`$userInfo`、`$parentInfo` 的数据表来源和用途�??
- 补齐 `createUserInfo()` �? `createAgentDescendantRows()` 说明，明�? `$familyTree` 是代理家族链，`$treeIds` 是拆分后的用户链路�??

### 相关接口和消�?
- `UserRegistrationService::register(array $data, ?int $parentId, ?int $accountType)`：前台代理商/普�?�客户注册写库入口，创建 `user_logins`、`user_infos`、`user_auths`，并同步 `agent_descendants`�?
- `UserRegistrationService::validateRegistration($data, $parentId, int $accountType, string $commissionMode)`：注册前置验证入口，供控制器在真正写库前复用�?
- `response.id_card_exists`：身份证号或证件号码重复提示，避免中文字段名拼接英文 `already exists`�?

### 验证记录
- `vendor\bin\phpunit tests\Feature\UserRegistrationServiceLocalizationTest.php`：RED 阶段失败，命中半中半英文案和中文参数注释缺口�?
- `php -l app\Services\UserRegistrationService.php`：�?�过，无语法错误�?
- `php -l resources\lang\zh-CN\response.php`：�?�过，无语法错误�?
- `php -l resources\lang\en\response.php`：�?�过，无语法错误�?
- `php -l tests\Feature\UserRegistrationServiceLocalizationTest.php`：�?�过，无语法错误�?
- `vendor\bin\phpunit tests\Feature\UserRegistrationServiceLocalizationTest.php`：�?�过�?2 tests / 14 assertions�?
- `vendor\bin\phpunit tests\Feature\FrontAuthControllerLocalizationTest.php`：�?�过�?2 tests / 36 assertions，确认前台认证本地化契约未被破坏�?

### 本轮边界
- 本轮只修复身份证重复提示的多语言 key 和注册服务参数注释，没有改变注册事务、账号类型判断�?�邀请人规则、ID 生成、user_logins/user_infos/user_auths 写入�? agent_descendants 关系同步逻辑�?
- 其它旧前台兼容接口中返回 `FAIL`、`CLASSINVALID` 等历�? Ajax 状�?�码仍保留，后续�?要结合旧前端协议逐个判断是否可多语言化，不能�?单替换�??

## 131. 2026-06-09 后台 Session 鉴权中间件多语言与中文参数注释修�?

### 本次处理目标
- 继续�? `plan.md` / `docs/admin-auth-permission-plan.md` 推进后台鉴权、多语言和中文�?�辑注释要求�?
- 修复 `app/Http/Middleware/AdminAuthenticate.php` �? JSON 未认证响应硬编码英文 `Unauthenticated.` 的问题�??
- 补齐后台 Session guard 鉴权边界的中文功能注释�?�参数含义注释和分支逻辑说明�?

### 修改文件
- `app/Http/Middleware/AdminAuthenticate.php`
  - 类注释说明该中间件用于后�? Blade 页面或兼容入口的 Session guard 鉴权�?
  - `handle(Request $request, Closure $next)` 方法补充 `$request`、`$next`、`expectsJson`、`admin_page_login` 的中文�?�辑含义�?
  - JSON 未登录响应从硬编码英文改�? `__('response.auth_failed')`，继续保�? HTTP 401�?
  - 普�?�页面未登录仍跳�? `admin_page_login`，不改变现有后台登录入口�?
- `tests/Feature/AdminAuthenticateMiddlewareLocalizationTest.php`
  - 新增测试覆盖多语�?响应 key、禁止硬编码英文和关键中文注释要求�??

### 接口/响应影响
- 适用边界：使�? `AdminAuthenticate` 中间件保护的后台页面或兼容路由�??
- JSON 未认证响应：
```json
{
  "message": "认证失败"
}
```
- 英文 locale 下返回：
```json
{
  "message": "Authentication failed"
}
```
- 页面请求仍重定向�? `/admin/login` 对应�? `admin_page_login` 命名路由�?

### 验证记录
- `php -l app\Http\Middleware\AdminAuthenticate.php`：�?�过�?
- `php -l tests\Feature\AdminAuthenticateMiddlewareLocalizationTest.php`：�?�过�?
- `vendor\bin\phpunit tests\Feature\AdminAuthenticateMiddlewareLocalizationTest.php`：�?�过�?2 tests / 8 assertions�?
- `vendor\bin\phpunit tests\Feature\JwtAuthMiddlewareLocalizationTest.php`：�?�过�?2 tests / 9 assertions�?
- 本机 PHP 输出仍包含历�? Xdebug 配置提示，不影响命令�?出码和测试结果�??


## 132. 2026-06-09 用户服务 UserService 中文逻辑注释与参数含义补�?

### 本次处理目标
- 继续执行 `plan.md` / `docs/admin-auth-permission-plan.md` 中�?�所有模块文件及参数必须有详细中文�?�辑注释”的要求�?
- 清理 `app/Services/UserService.php` 中遗留英文功能注释，避免后续后台用户迁移时误读字段含义�??
- 不改变用户详情�?�资料更新�?�状态更新和注销标记的现有业务行为�??

### 修改文件
- `app/Services/UserService.php`
  - 重写�? UTF-8 中文注释版本，保留原有四个公�?方法�?
  - 补充 `UserLogin`、`UserInfo`、`UserAuth` 三个模型在用户资料链路中的职责说明�??
  - 补充 `$userId`、`$data`、`is_enabled`、`auth_status`、`is_cancelled` 的真实数据表含义�?
  - 移除未使用的 `Hash` 引用，减少无效依赖�??
- `tests/Feature/UserServiceCommentReadabilityTest.php`
  - 新增测试覆盖英文注释残留�?查和核心参数中文含义�?查�??

### 业务边界说明
- `getUserDetail(int $userId)`：读�? `user_logins`、`user_infos`、`user_auths` 组合详情；登录记录不存在时返回空数组�?
- `updateUserInfo(int $userId, array $data)`：只更新 `user_infos` 表，调用方仍�?负责字段白名单�??
- `updateUserStatus(int $userId, array $data)`：事务内兼容更新 `user_logins.is_enabled` �? `user_auths.status`�?
- `deleteUser(int $userId)`：只写入 `user_logins.is_cancelled=1`，不物理删除业务资料�?

### 验证记录
- `php -l app\Services\UserService.php`：�?�过�?
- `php -l tests\Feature\UserServiceCommentReadabilityTest.php`：�?�过�?
- `vendor\bin\phpunit tests\Feature\UserServiceCommentReadabilityTest.php`：�?�过�?2 tests / 12 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminUserControllerCommentReadabilityTest.php tests\Feature\UserControllerCommentReadabilityTest.php`：�?�过�?1 test / 25 assertions�?
- 本机 PHP 输出仍包含历�? Xdebug 配置提示，不影响命令�?出码和测试结果�??


## 133. 2026-06-09 公共上传控制�? Common UploadController 中文注释与多语言响应修复

### 本次处理目标
- 继续执行后端多语�?与所有模块中文�?�辑注释要求�?
- 清理 `app/Http/Controllers/Common/UploadController.php` 中英文注释残留�??
- 统一上传成功文案�? `response.uploaded`，保持前后台 API 响应口径�?致�??

### 修改文件
- `app/Http/Controllers/Common/UploadController.php`
  - 重写�? UTF-8 中文注释版本�?
  - 补充 `file`、`type`、`avatar`、`id_card`、`bank_card`、`voucher`、`general`、`allowedMimes` 的参数含义�??
  - 成功消息�? `messages.upload_success` 改为 `response.uploaded`�?
- `tests/Feature/CommonUploadControllerCommentReadabilityTest.php`
  - 新增公共上传控制器注释与多语�?响应测试�?

### 业务边界说明
- 响应结构仍为旧前端兼容格式：`code`、`msg`、`data.url/path/name/size`�?
- 上传目录仍为 public disk �? `{type}/{Ymd}`�?
- 图片类业务只允许 `jpeg/png/jpg/gif`�?
- `general` 额外允许 `pdf/doc/docx/xls/xlsx`�?

### 验证记录
- `php -l app\Http\Controllers\Common\UploadController.php`：�?�过�?
- `php -l tests\Feature\CommonUploadControllerCommentReadabilityTest.php`：�?�过�?
- `vendor\bin\phpunit tests\Feature\CommonUploadControllerCommentReadabilityTest.php`：�?�过�?3 tests / 15 assertions�?
- `vendor\bin\phpunit tests\Feature\FrontUploadControllerCommentReadabilityTest.php --filter test_front_upload_controller_contains_required_chinese_logic_comments`：�?�过�?1 test / 21 assertions�?
- `vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_front_upload_apis_use_readable_resource_style_routes`：�?�过�?1 test / 14 assertions�?
- 本机 PHP 输出仍包含历�? Xdebug 配置提示，不影响命令�?出码和测试结果�??


## 134. 2026-06-09 后台权限名称、字符串与功能作用中文说�? MD 补齐

### 本次处理目标
- 响应“后台所有权限名称必须在 MD 文件中有中文注释、对应字符串和功能作用�?�的要求�?
- 直接读取当前真实数据�? `permissions` 表中 `guard_type=admin` 的后台权限，生成可维护的中文说明文档�?
- 增加自动化测试，防止后续新增后台权限后遗漏文档说明�??

### 新增/修改文件
- `docs/admin-permission-name-reference.md`
  - 新增后台权限名称说明文档�?
  - 数据来源为真�? `permissions` 表，当前共记�? `195` 条后台权限�??
  - 每条权限包含 `ID`、`parent_id`、`类型`、`权限名称`、`权限字符�? slug`、`接口路由字符�? api_route`、`页面路由 route`、`状�?�` �? `功能作用`�?
  - 对历�? DB 中已出现�? mojibake 权限名称做只读还原，文档中展示可读中文，不直接修改数据库原�?��??
- `tests/Feature/AdminPermissionNameReferenceDocumentationTest.php`
  - 新增后台权限说明文档覆盖测试�?
  - 测试直接读取真实 `permissions.guard_type=admin` 记录，并逐条断言 MD 中包�? `slug`、非�? `api_route`、非�? `route` 和可读权限名称�??
  - 测试内补�? `$documentPath`、`$document`、`$permissions`、`$name`、`$slug`、`$apiRoute`、`$pageRoute` 的中文参数含义注释�??

### 文档字段说明
- `权限名称`：来�? `permissions.name`，用于后台权限管理页面展示�??
- `权限字符串`：来�? `permissions.slug`，用于角色授权�?�Blade/JS `data-permission` 和按钮显隐判断�??
- `接口路由字符串`：来�? `permissions.api_route`，由 `check.permission:admin` 用于接口层鉴权�??
- `页面路由`：来�? `permissions.route`，用于后�? Blade 菜单或页面跳转�??
- `功能作用`：按菜单权限、页面权限�?�按�?/接口权限分别说明该权限控制的业务边界�?

### 验证记录
- `php artisan tinker --execute="echo DB::table('permissions')->where('guard_type','admin')->count();"`：返�? `195`�?
- `php -l tests\Feature\AdminPermissionNameReferenceDocumentationTest.php`：�?�过�?
- `php -l docs\admin-permission-name-reference.md`：�?�过�?
- `vendor\bin\phpunit tests\Feature\AdminPermissionNameReferenceDocumentationTest.php`：�?�过�?1 test / 810 assertions�?

### 本轮边界
- 本轮只新增后台权限中文说�? MD 和覆盖测试，没有修改 `permissions`、`roles`、`role_permissions` 真实数据�?
- 文档中对历史乱码权限名做可读展示，但未执行数据库修复；如后续要清�? DB 原始 `permissions.name` 编码，需要单独迁移并备份验证�?
- 当前仍保留真实数据库里已存在的停用权限与历史重复权限记录，因为用户要求覆盖�?�后台所有权限名称�?�，本轮不擅自删除历史权限数据�??


## 135. 2026-06-09 Permission 权限模型中文注释可读性与参数含义补齐

### 本次处理目标
- 继续执行“所有模块文件及参数必须有详细中文注释和逻辑注释”的要求�?
- 维护 `app/Models/Permission.php`，该模型是后台菜单�?�页面�?�按钮和接口鉴权的核心数据源�?
- 统一 `slug`、`api_route`、`guard_type`、`parent_id`、`type`、`status`、`$query` 等核心字�?/参数的中文含义说明�??

### 修改文件
- `app/Models/Permission.php`
  - 补强类级说明：`permissions` 表保存前后台菜单、页面�?�按钮和接口权限字典�?
  - 明确 `slug` 表示稳定权限字符串，供前端按钮显隐和后端授权判断使用�?
  - 明确 `api_route` 表示 Laravel 命名路由，供 `check.permission:admin` 做接口鉴权�??
  - 明确 `guard_type` 用于区分 `admin` �? `front`，避免前后台权限混用�?
  - 统一 `parent_id`、`name`、`route`、`icon`、`type`、`sort`、`status` 的字段含义注释�??
  - 调整 `scopeButton()` 注释为�?�限定按钮或接口动作权限”，与后台权限分类口径一致�??
- `tests/Feature/PermissionModelCommentReadabilityTest.php`
  - 新增 TDD 可读性测试，要求 Permission 模型包含可读中文功能注释和参数含义�??
  - 禁止历史 mojibake 乱码片段重新出现在权限模型注释中�?

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\PermissionModelCommentReadabilityTest.php` 首次失败，提示缺�? `slug 表示稳定权限字符串` 等明确中文说明�??
- GREEN：补齐字段说明和 scope 标题后，专项测试通过�?

### 验证记录
- `php -l app\Models\Permission.php`：�?�过�?
- `php -l tests\Feature\PermissionModelCommentReadabilityTest.php`：�?�过�?
- `vendor\bin\phpunit tests\Feature\PermissionModelCommentReadabilityTest.php`：�?�过�?1 test / 21 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminPermissionNameReferenceDocumentationTest.php`：�?�过�?1 test / 810 assertions�?

### 本轮边界
- 本轮只维护权限模型源码注释和专项测试，没有修�? `permissions` 表结构�?�模型字段�?�关联关系�?�scope 查询行为或鉴权中间件逻辑�?
- 后台权限名称说明 MD 仍以真实 DB `permissions.guard_type=admin` 为数据来源，本轮回归确认其覆盖契约未被破坏�??


## 136. 2026-06-09 Role 角色模型与角色权限模型可读�?�测试升�?

### 本次处理目标
- 继续执行“所有模块文件及参数必须有详细中文注释和逻辑注释”的要求�?
- 维护 `app/Models/Role.php`，该模型是后台管理员角色、前台代�?/客户角色、`role_permissions` 授权关系�? `role_data_scopes` 数据范围的核心入口�??
- 升级旧的 `AdminRolePermissionModelReadabilityTest`，移除历史乱码断�?，改为可读中文质量门�?

### 修改文件
- `app/Models/Role.php`
  - �? `guard_type 用于区分 admin �? front` 统一�? `guard_type 用于区分 admin �? front`，与权限说明文档口径�?致�??
  - �? `name`、`guard_type`、`description`、`permissions`、`status` 字段说明统一为�?�字段名 表示 …�?��?�格式�??
  - �? `$slug` 参数说明统一�? `$slug 表示 permissions.slug`，明确它是前端菜单�?�前端按钮和后端接口共用的稳定权限标识�??
  - 未修改模型字段�?�关联关系�?�`hasPermission()` 行为或任何数据库读写逻辑�?
- `tests/Feature/AdminRolePermissionModelReadabilityTest.php`
  - 重写�? UTF-8 可读中文测试�?
  - 同时约束 `Role` �? `Permission` 两个核心权限模型�?
  - 要求源码说明 `roles`、`permissions`、`role_permissions`、`role_data_scopes` 的职责边界�??
  - 禁止常见 UTF-8/GBK 错误解码后的乱码片段重新出现�?

### TDD 记录
- RED：旧测试运行失败，原因是仍断�?旧的 `slug 是前端按钮和后端接口共同使用的稳定权限标识` 文案，无法�?�配当前统一后的 `slug 表示稳定权限字符串` 注释契约�?
- GREEN：重写测试为可读中文契约，并按失败点补齐 `Role.php` 字段/参数说明后�?�过�?

### 验证记录
- `php -l app\Models\Role.php`：�?�过�?
- `php -l tests\Feature\AdminRolePermissionModelReadabilityTest.php`：�?�过�?
- `vendor\bin\phpunit tests\Feature\AdminRolePermissionModelReadabilityTest.php`：�?�过�?1 test / 33 assertions�?
- `vendor\bin\phpunit tests\Feature\PermissionModelCommentReadabilityTest.php`：�?�过�?1 test / 21 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminPermissionNameReferenceDocumentationTest.php`：�?�过�?1 test / 810 assertions�?

### 本轮边界
- 本轮只处理角色与权限模型的注释可读�?��?�参数含义和测试质量门，不修�? `roles`、`permissions`、`role_permissions`、`role_data_scopes` 数据�?
- `roles.permissions` JSON 仍仅作为历史兼容字段保留，真实授权来源继续是 `role_permissions` 中间表�??
- 超级权限判断仍保持原逻辑：`hasPermission('*')` 仅在角色名为 `super_admin` 时返�? true�?


## 137. 2026-06-09 后台管理员认证模型中文注释与参数含义补齐

### 本次处理目标
- 继续执行“所有模块文件及参数必须有详细中文注释和逻辑注释”的要求�?
- 修复后台认证链路模型 `Admin`、`AdminRole`、`AdminLoginLog` 中的历史乱码注释和英文占位注释�??
- 明确后台管理员账号�?�角色绑定�?�JWT 标识、权�? slug、登录日志字段的业务含义�?

### 修改文件
- `app/Models/Admin.php`
  - 补充 `admins` 表职责：保存后台管理员登录账号�?�角色绑定和登录状�?��??
  - 补充 `role_id`、`jwt_token_id`、`username`、`email`、`password`、`status`、`last_login_ip`、`last_login_at`、`login_count` 等字段含义�??
  - 补充 `hasPermission($slug)` 参数说明：`$slug` 表示 `permissions.slug`，后台菜单�?�按钮和接口共用的稳定权限字符串�?
  - 明确 `getAllPermissions()` 的权限唯�?来源�? `role_permissions` 中间表，不读�? `roles.permissions` JSON�?
  - 补充 `role()`、`loginLogs()`、`isActive()` 的中文�?�辑边界�?
- `app/Models/AdminRole.php`
  - 改为“管理员角色兼容模型”说明，明确底层数据表仍�? `roles`�?
  - 明确该模型只兼容旧代码调用，新权限链路优先使�? `Role` 模型�? `role_permissions` 中间表�??
  - 补充 `name`、`guard_type`、`description`、`permissions`、`status` 字段含义�?
- `app/Models/AdminLoginLog.php`
  - 补充 `admin_login_logs` 表职责：记录后台管理员登录审计信息�??
  - 补充 `admin_id`、`login_ip`、`ip_location`、`user_agent` 字段含义�?
  - 明确 `admin()` 关联用于审计页面展示登录账号、邮箱和角色信息�?
- `tests/Feature/AdminAuthModelCommentReadabilityTest.php`
  - 新增后台管理员认证模型可读�?�测试�??
  - �?查三个模型必须包含中文职责�?�字段含义�?�参数说明，并禁止常见乱码和英文占位片段�?

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AdminAuthModelCommentReadabilityTest.php` 首次失败，提�? `Admin` 模型缺少 `admins 表保存后台管理员登录账号、角色绑定和登录状�?�` 等中文说明�??
- GREEN：补齐三个模型注释后测试通过�?

### 验证记录
- `php -l app\Models\Admin.php`：�?�过�?
- `php -l app\Models\AdminRole.php`：�?�过�?
- `php -l app\Models\AdminLoginLog.php`：�?�过�?
- `php -l tests\Feature\AdminAuthModelCommentReadabilityTest.php`：�?�过�?
- `vendor\bin\phpunit tests\Feature\AdminAuthModelCommentReadabilityTest.php`：�?�过�?1 test / 32 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminRolePermissionModelReadabilityTest.php`：�?�过�?1 test / 33 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminPermissionNameReferenceDocumentationTest.php`：�?�过�?1 test / 810 assertions�?
- `rg` 扫描 `Admin.php`、`AdminRole.php`、`AdminLoginLog.php` 的历史乱码和英文占位片段：无命中�?

### 本轮边界
- 本轮只修改注释与测试，不改变后台登录、JWT、角色关联�?�权限判断�?�登录日志写入或数据库结构�??
- `AdminRole` 继续作为旧代码兼容模型保留，真实角色权限授权仍以 `Role` �? `role_permissions` 为准�?


## 138. 2026-06-09 前台用户认证与业务资料模型中文注释补�?

### 本次处理目标
- 继续执行“所有模块文件及参数必须有详细中文注释和逻辑注释”的要求�?
- 修复前台用户认证链路模型 `UserLogin`、`UserInfo`、`User`、`UserLoginLog` 中的历史乱码注释和英文占位注释�??
- 明确前台代理�?/普�?�客户账号�?�角色绑定�?�JWT 标识、业务资料�?�代理层级和登录日志字段含义�?

### 修改文件
- `app/Models/UserLogin.php`
  - 补充 `user_logins` 表职责：保存前台登录账号、密码哈希�?�角色绑定和登录状�?��??
  - 补充 `user_id`、`email`、`password`、`account_type`、`role_id`、`is_enabled`、`is_cancelled`、`source_type`、`jwt_token_id`、`last_login_ip`、`last_login_at` 字段含义�?
  - 明确 `role_id` 对应 `roles.id`，前台代理商和普通客户菜单权限都通过该角色读�? `role_permissions`�?
  - 补充 `role()`、`userInfo()`、`loginLogs()`、`isAgent()`、`isCustomer()`、`isActive()` 的中文�?�辑边界�?
- `app/Models/UserInfo.php`
  - 补充 `user_infos` 表职责：保存前台业务用户资料、代理层级�?�资金字段和 MT4 状�?��??
  - 按身份字段�?�层级字段�?�资金字段�?�交易字段�?�审核字段�?�MT4 字段、地�?字段、审计字段分组说�? `$fillable`�?
  - 明确 `user_id`、`login_id`、`parent_id`、`family_tree`、`account_type`、`auth_status` 的业务含义�??
  - 补充 `getAncestorIds()`、直属代�?/直属客户、实名认证�?�代理等级�?�组配置等关系说明�??
- `app/Models/User.php`
  - 改为 Laravel 默认前台用户兼容模型说明�?
  - 明确当前业务登录主体优先使用 `UserLogin`，该模型只保�? Laravel 默认用户体系兼容能力�?
  - 补充 `role_id` �? `$slug` 权限参数说明，避免误�? `User` 作为当前主业务登录表�?
- `app/Models/UserLoginLog.php`
  - 补充 `user_login_logs` 表职责：记录前台用户登录审计信息�?
  - 补充 `login_id`、`user_id`、`login_ip`、`ip_location`、`user_agent` 字段含义�?
- `tests/Feature/FrontUserAuthModelCommentReadabilityTest.php`
  - 新增前台用户认证模型可读性测试�??
  - �?查四个模型必须包含中文职责�?�字段含义�?�参数说明，并禁止常见乱码和英文占位片段�?

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\FrontUserAuthModelCommentReadabilityTest.php` 首次失败，提�? `UserLogin` 模型缺少 `user_logins 表保存前台登录账号�?�密码哈希�?�角色绑定和登录状�?�` 等中文说明�??
- GREEN：补齐四个模型注释后测试通过�?

### 验证记录
- `php -l app\Models\UserLogin.php`：�?�过�?
- `php -l app\Models\UserInfo.php`：�?�过�?
- `php -l app\Models\User.php`：�?�过�?
- `php -l app\Models\UserLoginLog.php`：�?�过�?
- `php -l tests\Feature\FrontUserAuthModelCommentReadabilityTest.php`：�?�过�?
- `vendor\bin\phpunit tests\Feature\FrontUserAuthModelCommentReadabilityTest.php`：�?�过�?1 test / 45 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminRolePermissionModelReadabilityTest.php`：�?�过�?1 test / 33 assertions�?
- `vendor\bin\phpunit tests\Feature\DefaultAdminAndFrontMenuRoleMigrationTest.php`：�?�过�?2 tests / 57 assertions�?
- `vendor\bin\phpunit tests\Feature\UserServiceCommentReadabilityTest.php`：�?�过�?2 tests / 12 assertions�?
- `rg` 扫描 `UserLogin.php`、`UserInfo.php`、`User.php`、`UserLoginLog.php` 的历史乱码和英文占位片段：无命中�?

### 本轮边界
- 本轮只修改模型注释与专项测试，不改变前台登录、JWT、角色授权�?�代理树、资金字段�?�MT4 状�?��?�登录日志写入或数据库结构�??
- 前台菜单权限仍按 `user_logins.role_id -> roles -> role_permissions -> permissions` 读取，代理商和普通客户菜单边界不变�??


## 139. 2026-06-09 资金入金/出金模型中文逻辑注释与真�? DB 字段核对

### 本次处理目标
- 继续执行“所有模块文件及参数必须有详细中文注释和逻辑注释”的要求�?
- 清理资金相关模型中残留的英文占位注释和历史编码残留，避免后台资金审核、批量导入和数据范围�?发时误读字段含义�?
- 使用真实数据库字段和记录作为注释依据，确保注释中的字段名称与当前 `co_crmv5` 数据库一致�??

### 修改文件
- `app/Models/DepositRecord.php`
  - 改为“入金记录模型�?�说明，明确 `deposit_records` 表保存前台用户入金申请和后台审核结果�?
  - 补充 `user_id`、`user_name`、`mt4_ticket`、`amount`、`actual_amount`、`exchange_rate`、`channel_name`、`channel_order_no`、`local_order_no`、`status`、`payment_time`、`remarks`、`created_by`、`updated_by` 的中文含义�??
  - 补充 `user()` 关联中外�? `deposit_records.user_id` 和目标键 `user_infos.user_id` 的�?�辑说明�?
- `app/Models/WithdrawRecord.php`
  - 改为“出金记录模型�?�说明，明确 `withdraw_records` 表保存前台用户出金申请和后台处理结果�?
  - 根据真实表结构使�? `apply_amount` 说明出金申请金额，避免误写为不存在的 `amount` 字段�?
  - 补充银行卡�?�手续费、拒绝原因�?�第三方订单号�?�MT4 返回状�?�和管理员审计字段的中文含义�?
- `app/Models/DepositImport.php`
  - 补充“批量入金导入模型�?�说明，明确 `deposit_imports` 表用�? Excel/CSV 批量入金导入记录�?
  - 补充 `user_id`、`user_name`、`amount`、`remarks`、`mt4_order_id`、`batch_no`、`is_synced`、`fail_reason`、`created_by`、`updated_by` 的中文含义�??
- `app/Models/WithdrawImport.php`
  - 补充“批量出金导入模型�?�说明，明确 `withdraw_imports` 表用�? Excel/CSV 批量出金导入记录�?
  - 补充 `amount` 字段为导入记录出金金额�?�`is_synced` 为后续出金处理或资金系统同步状�?�等中文说明�?
- `tests/Feature/AdminFundModelCommentReadabilityTest.php`
  - 新增资金模型中文注释质量门禁�?
  - �?查四个模型必须包含真实数据表职责、关键字段含义和用户关联说明�?
  - 禁止 `Table Name`、`Relation:`、`Maintains user deposit transaction history`、`Records the withdrawal transaction details` 等旧英文占位注释回流�?

### 真实 DB 数据来源
- `Schema::getColumnListing('deposit_records')` 返回字段包含：`id,user_id,user_name,mt4_ticket,amount,actual_amount,exchange_rate,channel_name,channel_order_no,local_order_no,status,payment_time,remarks,created_by,updated_by,created_at,updated_at,deleted_at`�?
- `Schema::getColumnListing('withdraw_records')` 返回字段包含：`id,user_id,user_name,mt4_ticket,apply_amount,actual_amount,fee,exchange_rate,rmb_fee,bank_no,bank_name,bank_addr,status,local_order_no,third_order_no,reject_reason,mt4_return_status,created_by,updated_by,created_at,updated_at,deleted_at`�?
- `Schema::getColumnListing('deposit_imports')` �? `Schema::getColumnListing('withdraw_imports')` 返回字段均包含：`id,user_id,user_name,amount,remarks,mt4_order_id,batch_no,is_synced,fail_reason,created_by,updated_by,created_at,updated_at,deleted_at`�?
- 当前真实数据量：
  - `deposit_records`�?18 条；样例 `id=54,user_id=600106,local_order_no=pas600115260325009381,status=01`�?
  - `withdraw_records`�?12 条；样例 `id=35,user_id=600106,local_order_no=WDR202603240050,status=2`�?
  - `deposit_imports`�?0 条；当前无样例记录�??
  - `withdraw_imports`�?0 条；当前无样例记录�??

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AdminFundModelCommentReadabilityTest.php` 首次失败，提�? `DepositRecord.php` 缺少“入金记录模型�?�，且仍包含 `Table Name` 英文占位注释�?
- GREEN：补齐四个资金模型中文�?�辑注释并按真实 DB 字段修正测试期望后，专项测试通过�?

### 验证记录
- `php -l app\Models\DepositRecord.php`：�?�过�?
- `php -l app\Models\WithdrawRecord.php`：�?�过�?
- `php -l app\Models\DepositImport.php`：�?�过�?
- `php -l app\Models\WithdrawImport.php`：�?�过�?
- `php -l tests\Feature\AdminFundModelCommentReadabilityTest.php`：�?�过�?
- `vendor\bin\phpunit tests\Feature\AdminFundModelCommentReadabilityTest.php`：�?�过�?2 tests / 52 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminBatchAmountImportModuleTest.php`：�?�过�?4 tests / 30 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminBatchAmountImportRetryModuleTest.php`：�?�过�?5 tests / 27 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminBatchAmountImportPermissionMigrationTest.php`：�?�过�?1 test / 25 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php`：�?�过�?3 tests / 1521 assertions�?
- `rg "鍏呭€|鍑洪噾|鏁版嵁|鍏宠仈|Table Name|Relation:|Maintains user deposit transaction history|Records the withdrawal transaction details" app\Models\DepositRecord.php app\Models\WithdrawRecord.php app\Models\DepositImport.php app\Models\WithdrawImport.php`：无命中�?

### 本轮边界
- 本轮只修改资金相关模型注释和新增注释质量测试，没有改变入金�?�出金�?�批量导入�?�同步状态�?�用户关联或数据库结构�??
- `withdraw_records` 的申请金额字段以真实 DB 字段 `apply_amount` 为准；`deposit_imports`、`withdraw_imports` 当前真实表为空，已记录表结构作为测试数据依据�?
## 140. 2026-06-09 菜单、审计日志与邮件配置模型中文注释补齐

### 本次处理目标
- 继续执行“所有模块文件及参数必须有详细中文注释和逻辑注释”的要求�?
- 优先清理与后台权限菜单�?�审计追踪�?�系统邮件配置相关的模型注释�?
- 保持业务行为不变，仅补充真实表职责�?�字段含义�?�关联参数和本地化�?�辑说明�?

### 修改文件
- `app/Models/Menu.php`
  - 补充 `menus` 表保存前后台 Blade 页面可见动�?�菜单配置的职责说明�?
  - 补充 `title`、`title_en`、`icon`、`path`、`component`、`parent_id`、`permission_id`、`guard_type`、`type`、`is_visible`、`is_external`、`sort`、`status` 的中文含义�??
  - 补充 `parent()`、`children()`、`permission()` 关联外键说明�?
  - 补充 `scopeAdmin()`、`scopeFront()`、`scopeVisible()`、`scopeActive()`、`scopeRoot()` 查询作用域参数说明�??
  - 补充 `getLocalizedTitleAttribute()` 按当�? locale 返回中文或英文菜单标题的多语�?逻辑说明�?
- `app/Models/OperationLog.php`
  - 补充 `operation_logs` 表保存后台管理员业务操作审计记录的职责说明�??
  - 补充 `admin_id`、`admin_name`、`target_user_id`、`order_no`、`content`、`ip`、`action_type` 字段含义�?
  - 补充 `admin()` �? `targetUser()` 关联外键和目标键说明�?
- `app/Models/DataOperationLog.php`
  - 补充 `data_operation_logs` 表保存模型数据变更前后审计快照的职责说明�?
  - 补充 `model_type`、`model_id`、`before_data`、`after_data`、`operator_id` 字段含义�?
  - 补充 `$casts` �? JSON 快照字段自动转数组的参数说明�?
- `app/Models/MailSetting.php`
  - 补充 `mail_settings` 表保存系统邮件发送配置的职责说明�?
  - 补充 `driver`、`host`、`port`、`username`、`password`、`encryption`、`from_address`、`from_name` 字段含义�?
- `tests/Feature/AdminMenuAuditConfigModelCommentReadabilityTest.php`
  - 新增菜单、审计日志�?�邮件配置模型中文注释质量测试�??
  - 禁止 `Table Name`、`Relation:`、`Fillable attributes`、`Attribute Casting` 及旧英文说明回流�?

### 真实 DB 数据来源
- `Schema::getColumnListing('menus')` 返回字段：`id,title,title_en,icon,path,component,parent_id,permission_id,guard_type,type,is_visible,is_external,sort,status,created_at,updated_at,deleted_at`�?
- `Schema::getColumnListing('operation_logs')` 返回字段：`id,admin_id,admin_name,target_user_id,order_no,content,ip,action_type,created_at,updated_at,deleted_at`�?
- `Schema::getColumnListing('data_operation_logs')` 返回字段：`id,model_type,model_id,before_data,after_data,operator_id,created_at,updated_at,deleted_at`�?
- `Schema::getColumnListing('mail_settings')` 返回字段：`id,driver,host,port,username,password,encryption,from_address,from_name,created_at,updated_at,deleted_at`�?
- 当前真实数据量：
  - `menus`�?0 条；当前无样例记录�??
  - `operation_logs`�?0 条；当前无样例记录�??
  - `data_operation_logs`�?0 条；当前无样例记录�??
  - `mail_settings`�?0 条；当前无样例记录�??

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AdminMenuAuditConfigModelCommentReadabilityTest.php` 首次失败，提�? `Menu.php` 缺少 `menus 表保存前后台 Blade 页面可见的动态菜单配置`，且仍包�? `Table Name` 英文占位注释�?
- GREEN：补�? `Menu`、`OperationLog`、`DataOperationLog`、`MailSetting` 四个模型注释后，专项测试通过�?

### 验证记录
- `php -l app\Models\Menu.php`：�?�过�?
- `php -l app\Models\OperationLog.php`：�?�过�?
- `php -l app\Models\DataOperationLog.php`：�?�过�?
- `php -l app\Models\MailSetting.php`：�?�过�?
- `php -l tests\Feature\AdminMenuAuditConfigModelCommentReadabilityTest.php`：�?�过�?
- `vendor\bin\phpunit tests\Feature\AdminMenuAuditConfigModelCommentReadabilityTest.php`：�?�过�?2 tests / 68 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminRolePermissionModelReadabilityTest.php`：�?�过�?1 test / 33 assertions�?
- `vendor\bin\phpunit tests\Feature\DefaultAdminAndFrontMenuRoleMigrationTest.php`：�?�过�?2 tests / 57 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminPermissionNameReferenceDocumentationTest.php`：�?�过�?1 test / 810 assertions�?

### 本轮边界
- 本轮只修改模型注释和新增注释质量测试，没有改变菜单树加载、权限绑定�?�日志关联�?�邮件配置读写或数据库结构�??
- `menus` 当前真实表为空，后台实际授权菜单仍以 `permissions` / `role_permissions` 链路为主；本轮仅补齐历史 `Menu` 模型说明，避免后续维护误用�??
## 141. 2026-06-09 代理层级、代理节点统计与代理等级模型中文注释补齐

### 本次处理目标
- 继续执行“所有模块文件及参数必须有详细中文注释和逻辑注释”的要求�?
- 优先清理与后台数据范围�?�前台代理客户列表�?�注册代理关系和返佣配置相关的代理层级模型�??
- 保持业务行为不变，仅补充真实表职责�?�字段含义�?�查询作用域参数和当前数据库边界说明�?

### 修改文件
- `app/Models/AgentDescendant.php`
  - 补充 `agent_descendants` 表保存代理与下级代理或客户之间层级闭包关系的职责说明�?
  - 补充 `agent_id`、`descendant_id`、`descendant_type`、`is_direct`、`depth` 的中文含义�??
  - 补充 `agent()`、`descendant()` 关联外键和目标键说明�?
  - 补充 `scopeDirectAgents()`、`scopeAllAgents()`、`scopeDirectCustomers()`、`scopeAllCustomers()` �? `$query` �? `$agentId` 的参数含义�??
- `app/Models/AgentNodeStats.php`
  - 补充“代理节点统计模型�?�说明，明确 `agent_node_stats` 用于保存代理节点统计快照�?
  - 明确当前数据库未�? `agent_node_stats` 表时不得在业务查询中直接依赖该模型，应继续以 `agent_descendants` 实时关系表为准�??
  - 补充 `agent_id`、`direct_agent_count`、`indirect_agent_count`、`direct_customer_count`、`indirect_customer_count`、`last_calculated_at` 的中文含义�??
- `app/Models/AgentLevel.php`
  - 补充 `agent_levels` 表保存代理等级与返佣比例配置的职责说明�??
  - 补充 `level_code`、`name`、`max_commission`、`min_commission`、`user_commission` 的中文含义�??
- `tests/Feature/AgentHierarchyModelCommentReadabilityTest.php`
  - 新增代理层级相关模型中文注释质量测试�?
  - 禁止 `Table Name`、`Relation:`、`Scope:`、`Attribute Casting` 和旧英文说明回流�?

### 真实 DB 数据来源
- `Schema::getColumnListing('agent_descendants')` 返回字段：`id,agent_id,descendant_id,descendant_type,is_direct,depth,created_at,updated_at,deleted_at`�?
- `Schema::getColumnListing('agent_levels')` 返回字段：`id,level_code,name,max_commission,min_commission,user_commission,created_at,updated_at,deleted_at`�?
- `Schema::hasTable('agent_node_stats')` 返回 `false`，说明当前数据库尚未创建代理节点统计表�??
- 当前真实数据量：
  - `agent_descendants`�?85 条；样例 `id=1,agent_id=620001,descendant_id=620101,descendant_type=2,is_direct=1,depth=1`�?
  - `agent_levels`�?5 条；样例 `id=1,level_code=1,name=�?级代�?,max_commission=85,min_commission=85,user_commission=0`�?
  - `agent_node_stats`：当前未建表，无样例记录�?

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AgentHierarchyModelCommentReadabilityTest.php` 首次失败，提�? `AgentDescendant.php` 缺少 `agent_descendants 表保存代理与下级代理或客户之间的层级闭包关系`，且仍包�? `Table Name` 英文占位注释�?
- GREEN：补齐三个代理层级模型中文�?�辑注释并保留原查询逻辑后，专项测试通过�?

### 验证记录
- `php -l app\Models\AgentDescendant.php`：�?�过�?
- `php -l app\Models\AgentNodeStats.php`：�?�过�?
- `php -l app\Models\AgentLevel.php`：�?�过�?
- `php -l tests\Feature\AgentHierarchyModelCommentReadabilityTest.php`：�?�过�?
- `vendor\bin\phpunit tests\Feature\AgentHierarchyModelCommentReadabilityTest.php`：�?�过�?2 tests / 46 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminDataScopeServiceTest.php`：�?�过�?4 tests / 6 assertions�?
- `vendor\bin\phpunit tests\Feature\AgentLevelControllerCommentReadabilityTest.php`：�?�过�?1 test / 14 assertions�?
- `vendor\bin\phpunit tests\Feature\UserRegistrationServiceLocalizationTest.php`：�?�过�?2 tests / 14 assertions�?
- `vendor\bin\phpunit tests\Feature\DefaultAdminAndFrontMenuRoleMigrationTest.php`：�?�过�?2 tests / 57 assertions�?
- `rg "Table Name|Relation:|Scope:|Attribute Casting|Maintains hierarchical relationships|Stores statistical data|Defines different agent levels" app\Models\AgentDescendant.php app\Models\AgentNodeStats.php app\Models\AgentLevel.php`：无命中�?

### 本轮边界
- 本轮只修改代理层级相关模型注释和新增注释质量测试，没有改变代理树查询、后台数据范围过滤�?�前台代理客户列表�?�注册关系写入�?�返佣计算或数据库结构�??
- `agent_node_stats` 当前是未建表的统计预留模型，后续若要启用必须先补迁移、数据生成任务和回归测试�?
## 142. 2026-06-09 返佣记录、组配置与支付�?�道模型中文注释补齐

### 本次处理目标
- 继续执行“所有模块文件及参数必须有详细中文注释和逻辑注释”的要求�?
- 清理返佣、交易组配置和支付�?�道模型中的旧英文占位注释与历史编码残留�?
- 使用真实数据库字段和样例数据作为注释依据，确保资金配置相关字段说明可维护�?

### 修改文件
- `app/Models/CommissionRecord.php`
  - 补充 `commission_records` 表保存代理返佣结算和人工调整记录的职责说明�??
  - 补充 `unique_id`、`agent_id`、`parent_id`、`agent_profit`、`agent_volume`、`equity_value`、`equity_diff`、`settle_cycle`、`mt4_order_id`、`date_range`、`settle_status`、`fee`、`swap`、`commission_amount`、`returned_amount`、`deposit`、`real_amount`、`data_type`、`manual_reason`、`remarks`、`created_by`、`updated_by` 的中文含义�??
  - 补充 `agent()` �? `parent()` 关联外键和目标键说明�?
- `app/Models/GroupConfig.php`
  - 补充 `group_configs` 表保存代理组和客户交易组配置的职责说明�??
  - 补充 `pair_id`、`name`、`radix`、`category`、`has_commission`、`is_enabled`、`is_ecn`、`is_default`、`created_by`、`updated_by` 的中文含义�??
  - 补充 `pairedGroup()` 关联说明，以�? `scopeAgent()`、`scopeUser()`、`scopeEnabled()`、`scopeDefault()` �? `$query` 参数含义�?
- `app/Models/PaymentChannel.php`
  - 补充 `payment_channels` 表保存后台可用支付�?�道配置的职责说明�??
  - 补充 `name`、`channel_code`、`exchange_rate`、`is_enabled`、`sort`、`config` 的中文含义�??
  - 补充 `$casts` �? `config` JSON 自动转数组的说明，以�? `scopeEnabled()` �? `$query` 参数含义�?
- `tests/Feature/AdminFinanceConfigModelCommentReadabilityTest.php`
  - 新增返佣、组配置、支付�?�道模型中文注释质量测试�?
  - 禁止 `Table Name`、`Relation:`、`Scope:`、`Attribute Casting` 和旧英文说明回流�?

### 真实 DB 数据来源
- `Schema::getColumnListing('commission_records')` 返回字段：`id,unique_id,agent_id,parent_id,agent_profit,agent_volume,equity_value,equity_diff,settle_cycle,mt4_order_id,date_range,settle_status,fee,swap,commission_amount,returned_amount,deposit,real_amount,data_type,manual_reason,remarks,created_by,updated_by,created_at,updated_at,deleted_at`�?
- `Schema::getColumnListing('group_configs')` 返回字段：`id,pair_id,name,radix,category,has_commission,is_enabled,is_ecn,is_default,created_by,updated_by,created_at,updated_at,deleted_at`�?
- `Schema::getColumnListing('payment_channels')` 返回字段：`id,name,channel_code,exchange_rate,is_enabled,sort,config,created_at,updated_at,deleted_at`�?
- 当前真实数据量：
  - `commission_records`�?4 条；样例 `id=17,agent_id=1001,parent_id=0,commission_amount=880.55,settle_status=2`�?
  - `group_configs`�?15 条；样例 `id=1,pair_id=null,name=Agent Standard,category=1,is_enabled=1,is_default=1`�?
  - `payment_channels`�?3 条；样例 `id=1,name=Bank Transfer,channel_code=bank_transfer,exchange_rate=7.1200,is_enabled=1`�?

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AdminFinanceConfigModelCommentReadabilityTest.php` 首次失败，提�? `CommissionRecord.php` 缺少 `commission_records 表保存代理返佣结算和人工调整记录`，且仍包�? `Table Name` 英文占位注释�?
- GREEN：补齐三个模型中文�?�辑注释并保留原模型行为后，专项测试通过�?

### 验证记录
- `php -l app\Models\CommissionRecord.php`：�?�过�?
- `php -l app\Models\GroupConfig.php`：�?�过�?
- `php -l app\Models\PaymentChannel.php`：�?�过�?
- `php -l tests\Feature\AdminFinanceConfigModelCommentReadabilityTest.php`：�?�过�?
- `vendor\bin\phpunit tests\Feature\AdminFinanceConfigModelCommentReadabilityTest.php`：�?�过�?2 tests / 51 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminCommissionsCommentReadabilityTest.php`：�?�过�?2 tests / 40 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminGroupConfigsCommentReadabilityTest.php`：�?�过�?2 tests / 59 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminPaymentChannelControllerCommentReadabilityTest.php`：�?�过�?1 test / 34 assertions�?
- `vendor\bin\phpunit tests\Feature\PaymentChannelControllerCommentReadabilityTest.php`：�?�过�?1 test / 15 assertions�?
- `rg "Table Name|Relation:|Scope:|Attribute Casting|Records details of commissions|Stores configuration parameters|Manages available payment channels" app\Models\CommissionRecord.php app\Models\GroupConfig.php app\Models\PaymentChannel.php`：无命中�?

### 本轮边界
- 本轮只修改返佣�?�组配置、支付�?�道模型注释和新增注释质量测试，没有改变返佣结算、交易组筛�?��?�支付�?�道启用筛�?��?�JSON cast 或数据库结构�?
- 三个模型文件被重写为干净 UTF-8 注释版本，原有类名�?�表名�?�关联关系和 scope 查询行为保持�?致�??
## 143. 2026-06-09 新闻公告、实名认证�?�收货地�?与礼品发货模型中文注释补�?

### 本次处理目标
- 继续执行“所有模块文件及参数必须有详细中文注释和逻辑注释”的要求�?
- 清理公告、实名认证�?�收货地�?和礼品发货模型中的旧英文占位注释与历史编码残留�??
- 使用真实数据库字段和样例数据作为注释依据，确保前后台页面展示字段含义可维护�??

### 修改文件
- `app/Models/News.php`
  - 补充 `news` 表保存后台发布新闻公告内容的职责说明�?
  - 补充 `title`、`content`、`image`、`author_id`、`author_name`、`is_published` 的中文含义�??
  - 补充 `scopePublished()` �? `$query` 参数含义�? `is_published=1` 筛�?��?�辑�?
- `app/Models/UserAuth.php`
  - 补充 `user_auths` 表保存前台用户实名和银行卡认证资料的职责说明�?
  - 补充 `user_id`、`bank_no`、`bank_no_tmp`、`bank_name`、`bank_name_tmp`、`bank_addr`、`bank_addr_tmp`、`bank_card_img`、`bank_card_back_img`、`bank_status`、`bank_remarks`、`id_card_no`、`id_card_front`、`id_card_back`、`id_card_status`、`id_card_remarks`、`is_bank_synced` 的中文含义�??
  - 补充 `$fillable` 的兼容边界：旧字段保留给旧表单和旧接口兼容，调用方仍应按真实表结构过滤后写入�?
  - 补充 `userInfo()` 关联外键和目标键说明�?
- `app/Models/UserAddress.php`
  - 补充 `user_addresses` 表保存前台用户礼品收货地�?的职责说明�??
  - 补充 `user_id`、`recipient_name`、`recipient_phone`、`recipient_address`、`is_default` 的中文含义�??
  - 补充 `user()` 关联外键和目标键说明�?
- `app/Models/GiftShipment.php`
  - 补充 `gift_shipments` 表保存礼品兑换发货和物流记录的职责说明�??
  - 补充 `user_id`、`address_id`、`recipient_name`、`recipient_phone`、`recipient_address`、`sender_name`、`tracking_number`、`gift_name`、`gift_quantity`、`status`、`remark`、`admin_id`、`shipped_at` 的中文含义�??
  - 补充 `user()` 关联外键和目标键说明�?
- `tests/Feature/AdminContentAuthGiftModelCommentReadabilityTest.php`
  - 新增公告、实名认证�?�收货地�?和礼品发货模型中文注释质量测试�??
  - 禁止 `Table Name`、`Relation:`、`Scope:`、`mass assignable` 和旧英文说明回流�?

### 真实 DB 数据来源
- `Schema::getColumnListing('news')` 返回字段：`id,title,content,image,author_id,author_name,is_published,created_at,updated_at,deleted_at`�?
- `Schema::getColumnListing('user_auths')` 返回字段：`id,user_id,bank_no,bank_no_tmp,bank_name,bank_name_tmp,bank_card_img,bank_card_back_img,bank_card_img_tmp,bank_card_back_img_tmp,bank_addr,bank_addr_tmp,bank_status,bank_remarks,id_card_no,id_card_status,id_card_front,id_card_back,id_card_remarks,is_bank_synced,created_at,updated_at,deleted_at`�?
- `Schema::getColumnListing('user_addresses')` 返回字段：`id,user_id,recipient_name,recipient_phone,recipient_address,is_default,created_at,updated_at,deleted_at`�?
- `Schema::getColumnListing('gift_shipments')` 返回字段：`id,user_id,address_id,recipient_name,recipient_phone,recipient_address,sender_name,tracking_number,gift_name,gift_quantity,status,remark,admin_id,shipped_at,created_at,updated_at,deleted_at`�?
- 当前真实数据量：
  - `news`�?2 条；样例 `id=1,title=Codex Runtime News Check,author_id=0,is_published=1`�?
  - `user_auths`�?9 条；样例 `id=1,user_id=1001,bank_status=2,id_card_status=2,is_bank_synced=0`�?
  - `user_addresses`�?1 条；样例 `id=5,user_id=1001,recipient_name=Demo Root Agent,is_default=1`�?
  - `gift_shipments`�?1 条；样例 `id=5,user_id=1001,address_id=5,gift_name=VIP Gift Box,status=2`�?

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AdminContentAuthGiftModelCommentReadabilityTest.php` 首次失败，提�? `News.php` 缺少“新闻公告模型�?�，且仍包含 `Table Name` 英文占位注释�?
- GREEN：补齐四个模型中文�?�辑注释并保留原模型行为后，专项测试通过�?

### 验证记录
- `php -l app\Models\News.php`：�?�过�?
- `php -l app\Models\UserAuth.php`：�?�过�?
- `php -l app\Models\UserAddress.php`：�?�过�?
- `php -l app\Models\GiftShipment.php`：�?�过�?
- `php -l tests\Feature\AdminContentAuthGiftModelCommentReadabilityTest.php`：�?�过�?
- `vendor\bin\phpunit tests\Feature\AdminContentAuthGiftModelCommentReadabilityTest.php`：�?�过�?2 tests / 69 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminNewsCommentReadabilityTest.php`：�?�过�?2 tests / 55 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminAuthenticationModuleTest.php`：�?�过�?5 tests / 30 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminGiftModuleTest.php`：�?�过�?5 tests / 30 assertions�?
- `vendor\bin\phpunit tests\Feature\FrontNewsControllerCommentReadabilityTest.php tests\Feature\FrontGiftControllerCommentReadabilityTest.php`：�?�过�?2 tests / 20 assertions�?
- `rg "Table Name|Relation:|Scope:|mass assignable|Manages news and announcements|Manages user|shipping address information|shipping process and logistics" app\Models\News.php app\Models\UserAuth.php app\Models\UserAddress.php app\Models\GiftShipment.php`：无命中�?

### 本轮边界
- 本轮只修改公告�?�认证�?�地�?、礼品发货模型注释和新增注释质量测试，没有改变公告发布筛选�?�实名认证字段白名单、用户地�?关联、礼品发货关联或数据库结构�??
- `UserAuth::$fillable` 保留旧项目兼容字段；这些字段不全部代表当�? `user_auths` 表真实字段，实际写入仍应由控制器或服务层按真实表结构过滤�?
## 144. 2026-06-09 注销申请、黑名单、大代理登录日志�? ID 序列模型中文注释补齐

### 本次处理目标
- 继续执行“所有模块文件及参数必须有详细中文注释和逻辑注释”的要求�?
- 清理注销申请、黑名单、大代理登录日志�? ID 序列模型中的旧英文占位注释与历史编码残留�?
- 使用真实数据库字段和样例数据作为注释依据，确保安全风控�?�账号注�?、登录审计和编号生成逻辑可维护�??

### 修改文件
- `app/Models/CancelApply.php`
  - 补充 `cancel_applies` 表保存前台用户提交账号注�?申请的职责说明�??
  - 补充 `user_id`、`user_name`、`status`、`cancel_remark`、`reject_reason`、`created_by`、`updated_by` 的中文含义�??
  - 补充 `user()` 关联外键和目标键说明�?
- `app/Models/Blacklist.php`
  - 补充 `blacklists` 表保存被限制注册或操作的用户身份信息的职责说明�??
  - 补充 `name`、`id_card`、`email`、`phone` 的中文含义�??
- `app/Models/BigAgentLoginLog.php`
  - 补充 `big_agent_login_logs` 表保存大代理账号登录审计记录的职责说明�??
  - 补充 `big_agent_id`、`login_ip`、`login_at` 的中文含义�??
  - 补充 `bigAgent()` 关联外键说明�?
- `app/Models/IdSequence.php`
  - 补充 `id_sequences` 表保存业务用户编号生成状态的职责说明�?
  - 补充 `type`、`current_value`、`prefix`、`step` 的中文含义�??
  - 补充 `nextId(string $type)` 的参数含义�?�返回�?�含义和 `lockForUpdate()` 并发锁定目的�?
- `tests/Feature/AdminSecuritySequenceModelCommentReadabilityTest.php`
  - 新增注销申请、黑名单、大代理登录日志�? ID 序列模型中文注释质量测试�?
  - 禁止 `Table Name`、`Relation:`、`Handles account cancellation`、`Manages blocked users`、`Records login history`、`Used for generating unique ID sequences`、`Initialize if not exists` 等旧说明回流�?

### 真实 DB 数据来源
- `Schema::getColumnListing('cancel_applies')` 返回字段：`id,user_id,user_name,status,cancel_remark,reject_reason,created_by,updated_by,created_at,updated_at,deleted_at`�?
- `Schema::getColumnListing('blacklists')` 返回字段：`id,name,id_card,email,phone,created_at,updated_at,deleted_at`�?
- `Schema::getColumnListing('big_agent_login_logs')` 返回字段：`id,big_agent_id,login_ip,login_at,created_at,updated_at,deleted_at`�?
- `Schema::getColumnListing('id_sequences')` 返回字段：`id,type,current_value,prefix,step,created_at,updated_at,deleted_at`�?
- 当前真实数据量：
  - `cancel_applies`�?0 条；当前无样例记录�??
  - `blacklists`�?0 条；当前无样例记录�??
  - `big_agent_login_logs`�?0 条；当前无样例记录�??
  - `id_sequences`�?1 条；样例 `id=1,type=agent,current_value=1001,prefix=,step=1`�?

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AdminSecuritySequenceModelCommentReadabilityTest.php` 首次失败，提�? `CancelApply.php` 缺少 `cancel_applies 表保存前台用户提交的账号注销申请`，且仍包�? `Table Name` 英文占位注释�?
- GREEN：补齐四个模型中文�?�辑注释并保留原模型行为后，专项测试通过�?

### 验证记录
- `php -l app\Models\CancelApply.php`：�?�过�?
- `php -l app\Models\Blacklist.php`：�?�过�?
- `php -l app\Models\BigAgentLoginLog.php`：�?�过�?
- `php -l app\Models\IdSequence.php`：�?�过�?
- `php -l tests\Feature\AdminSecuritySequenceModelCommentReadabilityTest.php`：�?�过�?
- `vendor\bin\phpunit tests\Feature\AdminSecuritySequenceModelCommentReadabilityTest.php`：�?�过�?2 tests / 65 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminCancelAppliesCommentReadabilityTest.php`：�?�过�?2 tests / 37 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminBlacklistCommentReadabilityTest.php`：�?�过�?2 tests / 41 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminBigAgentsCommentReadabilityTest.php`：�?�过�?2 tests / 52 assertions�?
- `vendor\bin\phpunit tests\Feature\UserRegistrationServiceLocalizationTest.php`：�?�过�?2 tests / 14 assertions�?
- `rg "Table Name|Relation:|Handles account cancellation|Manages blocked users|Records login history|Used for generating unique ID sequences|Initialize if not exists" app\Models\CancelApply.php app\Models\Blacklist.php app\Models\BigAgentLoginLog.php app\Models\IdSequence.php`：无命中�?

### 本轮边界
- 本轮只修改注�?申请、黑名单、大代理登录日志�? ID 序列模型注释，并新增注释质量测试，没有改变注�?审核、黑名单风控、大代理登录审计、注册编号生成或数据库结构�??
- `IdSequence::nextId()` 的事务�?�`lockForUpdate()` 行锁、起始�?�和递增逻辑保持原样，仅将并发编号生成意图写成中文注释�??
## 145. 2026-06-09 交易订单、余额信用清零�?�转组申请与品种行情模型中文注释补齐

### 本次处理目标
- 继续执行“所有模块文件及参数必须有详细中文注释和逻辑注释”的要求�?
- 清理交易订单、余额信用清零�?�转组申请日志和交易品种价格模型中的旧英文占位注释与历史编码残留�?
- 使用真实数据库字段和样例数据作为注释依据，确保后台交易风控�?�持�?/平仓、清零�?�转组审核和行情展示逻辑可维护�??

### 修改文件
- `app/Models/UserTrade.php`
  - 补充 `user_trades` 表保存用�? MT4 交易订单数据的职责说明�??
  - 补充 `user_id`、`ticket`、`symbol`、`digits`、`cmd`、`volume`、`open_time`、`open_price`、`stop_loss`、`take_profit`、`close_time`、`close_price`、`profit`、`commission`、`commission_agent`、`swaps`、`settlement_status`、`settled_at`、`comment`、`internal_id`、`magic` 等字段含义�??
  - 补充 `user()` 关联外键和目标键说明�?
  - 补充 `scopeOpen()`、`scopeClosed()` �? `$query` 参数含义，以及旧 MT4 未平仓订�? `close_time=1970-01-01 00:00:00` 的业务规则�??
- `app/Models/WhsExpZero.php`
  - 补充 `whs_exp_zeros` 表保存用户余额或信用额度清零操作记录的职责说明�??
  - 补充 `user_id`、`user_name`、`balance`、`credit`、`status`、`md5_key`、`created_by`、`updated_by` 的中文含义�??
  - 补充 `user()` 关联外键和目标键说明�?
- `app/Models/TransApplyLog.php`
  - 补充 `trans_apply_logs` 表保存用户申请变更交易组审核记录的职责说明�??
  - 补充 `user_id`、`origin_group_id`、`group_id`、`group_name`、`applicant_id`、`applicant_name`、`status`、`apply_reason`、`reject_reason`、`created_by`、`updated_by` 的中文含义�??
  - 补充 `user()` 关联外键和目标键说明�?
- `app/Models/SymbolPrice.php`
  - 补充 `symbol_prices` 表保存交易品种实时或历史报价的职责说明�??
  - 补充 `symbol`、`time`、`bid`、`ask`、`low`、`high`、`direction`、`digits`、`spread`、`group_id`、`status`、`modify_time` 的中文含义�??
- `tests/Feature/AdminTradingModelCommentReadabilityTest.php`
  - 新增交易订单、清零�?�转组和行情模型中文注释质量测试�?
  - 禁止 `Table Name`、`Relation:`、`Records user`、`Records the operation`、`Records the history`、`Stores real-time` 等旧说明回流�?

### 真实 DB 数据来源
- `Schema::getColumnListing('user_trades')` 返回字段：`id,user_id,ticket,symbol,digits,cmd,volume,open_time,open_price,stop_loss,take_profit,close_time,expiration,reason,conv_rate1,conv_rate2,commission,commission_agent,swaps,close_price,profit,taxes,comment,internal_id,margin_rate,timestamp_val,magic,gw_volume,gw_open_price,gw_close_price,modify_time,settlement_status,settled_at,created_at,updated_at,deleted_at`�?
- `Schema::getColumnListing('whs_exp_zeros')` 返回字段：`id,user_id,user_name,balance,credit,status,md5_key,created_by,updated_by,created_at,updated_at,deleted_at`�?
- `Schema::getColumnListing('trans_apply_logs')` 返回字段：`id,user_id,origin_group_id,group_id,group_name,applicant_id,applicant_name,status,apply_reason,reject_reason,created_by,updated_by,created_at,updated_at,deleted_at`�?
- `Schema::getColumnListing('symbol_prices')` 返回字段：`id,symbol,time,bid,ask,low,high,direction,digits,spread,group_id,status,modify_time,created_at,updated_at,deleted_at`�?
- 当前真实数据量：
  - `user_trades`�?36 条；样例 `id=288,user_id=600106,ticket=900135,symbol=XAUUSD.G,close_time=2026-05-22 10:36:33,settlement_status=1`�?
  - `whs_exp_zeros`�?0 条；当前无样例记录�??
  - `trans_apply_logs`�?1 条；样例 `id=8,user_id=600103,origin_group_id=2,group_id=3,status=0`�?
  - `symbol_prices`�?8 条；样例 `id=1,symbol=AUDJPY.G,bid=100,ask=100.25,spread=25,status=1`�?

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AdminTradingModelCommentReadabilityTest.php` 首次失败，提�? `UserTrade.php` 缺少 `user_trades 表保存用�? MT4 交易订单数据`，且仍包�? `Table Name` 英文占位注释�?
- GREEN：补齐四个模型中文�?�辑注释并保留原模型行为后，专项测试通过�?
- 测试调整：初版禁止词 `Model` 过宽，误伤正�? `BaseModel` 继承；已收窄为旧英文说明片段�?

### 验证记录
- `php -l app\Models\UserTrade.php`：�?�过�?
- `php -l app\Models\WhsExpZero.php`：�?�过�?
- `php -l app\Models\TransApplyLog.php`：�?�过�?
- `php -l app\Models\SymbolPrice.php`：�?�过�?
- `php -l tests\Feature\AdminTradingModelCommentReadabilityTest.php`：�?�过�?
- `vendor\bin\phpunit tests\Feature\AdminTradingModelCommentReadabilityTest.php`：�?�过�?2 tests / 67 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminRiskMt4ModuleTest.php`：�?�过�?4 tests / 31 assertions�?
- `vendor\bin\phpunit tests\Feature\FrontPositionControllerCommentReadabilityTest.php`：�?�过�?2 tests / 40 assertions�?
- `vendor\bin\phpunit tests\Feature\FrontTradeSymbolControllerCommentReadabilityTest.php`：�?�过�?2 tests / 19 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php`：�?�过�?3 tests / 1521 assertions�?
- `rg "Table Name|Relation:|Records user|Records the operation|Records the history|Stores real-time" app\Models\UserTrade.php app\Models\WhsExpZero.php app\Models\TransApplyLog.php app\Models\SymbolPrice.php`：无命中�?

### 本轮边界
- 本轮只修改交易订单�?�余额信用清零�?�转组申请日志和交易品种价格模型注释，并新增注释质量测试，没有改变交易订单开平仓判断、清零处理�?�转组审核�?�行情读取或数据库结构�??
- `scopeOpen()` �? `scopeClosed()` 仍然沿用�? MT4 `close_time=1970-01-01 00:00:00` 的未平仓判定规则�?
## 146. 2026-06-09 系统配置、点差配置�?�历史用户组、认证备份与凭证模型中文注释补齐

### 本次处理目标
- 继续执行“所有模块文件及参数必须有详细中文注释和逻辑注释”的要求�?
- 清理系统配置、点差配置�?�历史用户组、用户认证备份和凭证模型中的旧英文占位注释与历史编码残留�?
- 明确 `user_groups` �? `user_auth_info` 当前未建表的兼容边界，避免后续业务误直接依赖历史模型�?

### 修改文件
- `app/Models/SystemConfig.php`
  - 补充 `system_configs` 表保存后台全�?配置项的职责说明�?
  - 补充 `key`、`value`、`group`、`description` 字段含义�?
  - 补充 `getVal($key, $default)` �? `$key`、`$default` 参数含义和返回�?�说明�??
- `app/Models/SpreadConfig.php`
  - 补充 `spread_configs` 表保存交易产品或代理组点差配置的职责说明�?
  - 补充 `spread`、`agent_group_id`、`spread_ratio`、`status` 字段含义�?
- `app/Models/UserGroup.php`
  - 改为“用户组兼容模型”说明�??
  - 明确当前数据库未�? `user_groups` 表时不得在业务查询中直接依赖该模型，应优先使�? `group_configs`�?
- `app/Models/UserAuthInfo.php`
  - 改为“用户认证信息备份模型�?�说明�??
  - 明确当前数据库未�? `user_auth_info` 表时不得在业务查询中直接依赖该模型，应以 `user_auths` 作为真实认证数据源�??
- `app/Models/VoucherInfo.php`
  - 补充 `voucher_infos` 表保存前台用户上传入金或审核凭证的职责说明�??
  - 补充 `user_id`、`images`、`remarks`、`review_status`、`review_message`、`created_by`、`updated_by` 字段含义�?
  - 补充 `user()` 关联外键和目标键说明�?
- `tests/Feature/AdminConfigVoucherModelCommentReadabilityTest.php`
  - 新增系统配置、点差配置�?�历史用户组、认证备份和凭证模型中文注释质量测试�?
- `tests/Feature/VoucherInfoCommentReadabilityTest.php`
  - 从旧乱码断言升级为真正的 UTF-8 可读中文断言�?
  - 禁止 `Voucher Info Model`、`鏁版嵁`、`鍏宠仈`、`鍑瘉` 等旧片段回流�?

### 真实 DB 数据来源
- `Schema::getColumnListing('system_configs')` 返回字段：`id,key,value,group,description,created_at,updated_at,deleted_at`�?
- `Schema::getColumnListing('spread_configs')` 返回字段：`id,spread,agent_group_id,spread_ratio,status,created_at,updated_at,deleted_at`�?
- `Schema::getColumnListing('voucher_infos')` 返回字段：`id,user_id,images,remarks,review_status,review_message,created_by,updated_by,created_at,updated_at,deleted_at`�?
- `Schema::hasTable('user_groups')` 返回 `false`�?
- `Schema::hasTable('user_auth_info')` 返回 `false`�?
- 当前真实数据量：
  - `system_configs`�?41 条；样例 `id=1,key=unit_test_single_config,value=old,group=general`�?
  - `spread_configs`�?0 条；当前无样例记录�??
  - `voucher_infos`�?1 条；样例 `id=10,user_id=1001,review_status=1,review_message=已经处理到账了`�?
  - `user_groups`：当前未建表�?
  - `user_auth_info`：当前未建表�?

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AdminConfigVoucherModelCommentReadabilityTest.php tests\Feature\VoucherInfoCommentReadabilityTest.php` 首次失败，提�? `SystemConfig.php` 缺少 `system_configs 表保存后台全�?配置项`，且仍包�? `Table Name` 英文占位注释�?
- GREEN：补齐五个模型中文�?�辑注释，并升级 VoucherInfo 旧测试断�?后，专项测试通过�?

### 验证记录
- `php -l app\Models\SystemConfig.php`：�?�过�?
- `php -l app\Models\SpreadConfig.php`：�?�过�?
- `php -l app\Models\UserGroup.php`：�?�过�?
- `php -l app\Models\UserAuthInfo.php`：�?�过�?
- `php -l app\Models\VoucherInfo.php`：�?�过�?
- `php -l tests\Feature\AdminConfigVoucherModelCommentReadabilityTest.php`：�?�过�?
- `php -l tests\Feature\VoucherInfoCommentReadabilityTest.php`：�?�过�?
- `vendor\bin\phpunit tests\Feature\AdminConfigVoucherModelCommentReadabilityTest.php tests\Feature\VoucherInfoCommentReadabilityTest.php`：�?�过�?2 tests / 81 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminSystemConfigUpdateControllerTest.php`：�?�过�?2 tests / 4 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminExchangeRateModuleTest.php`：�?�过�?5 tests / 23 assertions�?
- `vendor\bin\phpunit tests\Feature\VoucherInfoCommentReadabilityTest.php tests\Feature\VoucherControllerCommentReadabilityTest.php tests\Feature\FrontVoucherControllerCommentReadabilityTest.php`：�?�过�?2 tests / 18 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php`：�?�过�?3 tests / 1521 assertions�?
- `rg "Table Name|Get Config Value|Manages various global configuration|Manages spread configurations|Defines different user groups|Stores backups or historical records|Voucher Info Model|鏁版嵁|鍏宠仈|绯荤粺|鐐瑰樊|鍑�?" app\Models\SystemConfig.php app\Models\SpreadConfig.php app\Models\UserGroup.php app\Models\UserAuthInfo.php app\Models\VoucherInfo.php`：无命中�?

### 本轮边界
- 本轮只修改模型注释和注释质量测试，没有改变系统配置读取�?�汇率配置�?�点差配置�?�凭证审核�?�凭证上传或数据库结构�??
- `UserGroup` �? `UserAuthInfo` 当前是历史兼容模型，因真实数据库未建表，后续若要启用必须先补迁移、数据来源和回归测试�?
## 147. 2026-06-09 后台权限名称、权限字符串与功能作�? MD 逐项注释补强

### 本次处理目标
- 响应“后台所有权限名称，�? MD 文件中必须有中文注释当前权限名称、对应字符串以及功能作用”的要求�?
- 基于真实数据�? `permissions` 表中 `guard_type=admin` 的后台权限记录校验文档，不使用手写猜测清单�??
- 增加逐行完整性测试，确保每条后台权限都在同一张表格行中同时包含权限名称�?�权限字符串、接口路由�?�页面路由�?�状态和功能作用�?

### 修改文件
- `docs/admin-permission-name-reference.md`
  - 新增 `权限名称中文注释规则` 小节�?
  - 明确每条后台权限必须独立成行，且 `权限名称`、`权限字符串`、`接口路由字符串`、`页面路由`、`功能作用` 必须逐项说明�?
  - 明确无独立接口路由或页面路由时使�? `-`，避免维护�?�误以为空缺字段遗漏�?
- `tests/Feature/AdminPermissionNameReferenceRowCompletenessTest.php`
  - 新增真实 DB 驱动的权限文档�?�行完整性测试�??
  - 参数注释说明 `$documentPath`、`$document`、`$rows`、`$permission`、`$slug`、`$name` 的�?�辑含义及功能作用�??
  - 校验每条后台权限必须拥有独立表格行，并在同一行内说明中文权限名称、`permissions.slug`、`permissions.api_route`、`permissions.route` 和功能作用�??

### 真实 DB 数据来源
- 查询来源：`permissions` 表，筛�?�条�? `guard_type=admin`�?
- 当前后台权限总数：`195`�?
- 当前启用后台权限数量：`176`�?
- 当前停用后台权限数量：`19`�?
- 类型分布：菜单权�? `54` 条，其中启用 `35` 条�?�停�? `19` 条；按钮/接口权限 `141` 条，全部启用�?
- 样例权限：`id=2,name=入金审核,slug=admin_deposit_approve_6a23fb27093ea,api_route=admin_api_depositApprove,type=3,status=1`�?

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AdminPermissionNameReferenceRowCompletenessTest.php` 首次失败，提�? `docs/admin-permission-name-reference.md` 缺少 `## 权限名称中文注释规则`�?
- GREEN：补充文档规则小节后，新增测试�?�过，证�? 195 条真实后台权限均�? MD 中拥有独立完整说明行�?

### 验证记录
- `php -l tests\Feature\AdminPermissionNameReferenceRowCompletenessTest.php`：�?�过�?
- `vendor\bin\phpunit tests\Feature\AdminPermissionNameReferenceRowCompletenessTest.php`：�?�过�?1 test / 1757 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminPermissionNameReferenceDocumentationTest.php`：�?�过�?1 test / 810 assertions�?

### 本轮边界
- 本轮只补强后台权限说明文档与文档完整性测试，没有修改业务鉴权中间件�?�角色授权�?�辑、菜单渲染�?�辑或数据库权限数据�?
- 当前文档覆盖范围为后�? `guard_type=admin` 权限；前台代理商和普通客户菜单权限仍由前台权限文�?/菜单审计继续跟进�?
## 148. 2026-06-09 基础模型、大代理、MT4 与批量信用导入模型中文注释补�?

### 本次处理目标
- 继续执行“所有模块的文件及参数必须有详细中文注释和�?�辑注释包括参数的注释及功能作用”的要求�?
- 清理 `BaseModel`、`BigAgent`、`Mt4User`、`Mt4Trade`、`CreditImport` 中残留的旧英文占位注释和历史编码乱码�?
- 保持模型行为不变，只补充可读中文逻辑说明、字段含义�?�参数含义和关联关系边界�?

### 修改文件
- `app/Models/BaseModel.php`
  - 改为干净 UTF-8 中文注释�?
  - 补充基础模型职责：软删除、主键�?�批量赋值�?�序列化隐藏字段�? Unix 时间戳日期格式�??
  - 补充 `$guarded`、`$hidden`、`$primaryKey`、`$dateFormat`、`serializeDate()` 的参数含义及功能作用�?
- `app/Models/BigAgent.php`
  - 补充 `big_agents` 表职责说明�??
  - 补充 `username`、`sub_agent_ids`、`is_enabled`、`jwt_token_id` 的业务含义�??
  - 补充 `loginLogs()` �? `big_agent_login_logs.big_agent_id` 的关联说明�??
- `app/Models/Mt4User.php`
  - 补充 `mt4_users` 表保�? MT4 资金快照的职责说明�??
  - 补充 `login`、`name`、`group`、`balance/equity/margin/margin_free`、`leverage` 字段含义�?
  - 补充 `$casts` 的金额精度和整数字段转换说明�?
- `app/Models/Mt4Trade.php`
  - 补充 `mt4_trades` 表保�? MT4 交易订单的职责说明�??
  - 补充 `ticket`、`login`、`symbol`、`cmd`、`profit`、`commission`、`swaps`、`open_time`、`close_time` 字段含义�?
  - 明确 `cmd=6` 表示余额类交易，`user()` 当前是历史兼容关系，严格归属判断应优先确�? `user_infos.mt4_code` 映射�?
- `app/Models/CreditImport.php`
  - 补充 `credit_imports` 表保存后台批量信用额度导入记录的职责说明�?
  - 补充 `user_id`、`user_name`、`credit_type`、`amount`、`batch_no`、`is_synced`、`fail_reason` 字段含义�?
  - 补充 `user()` �? `user_infos.user_id` 的关联说明�??
- `tests/Feature/AdminCoreMt4ModelCommentReadabilityTest.php`
  - 新增基础模型、大代理、MT4 用户资金、MT4 交易记录和批量信用导入模型中文注释可读�?�测试�??
  - 禁止 `Base Model Class`、`All business models extend this class`、`Table Name`、`Relation:` 以及常见 mojibake 片段回流�?

### 真实 DB 数据来源
- `big_agents` 字段：`id,email,username,password,sub_agent_ids,is_enabled,jwt_token_id,created_by,created_at,updated_at,deleted_at`；当前真实记录数 `0`，暂无样例记录�??
- `mt4_users` 字段：`id,login,name,group,balance,equity,margin,margin_free,leverage,created_at,updated_at,deleted_at`；当前真实记录数 `0`，暂无样例记录�??
- `mt4_trades` 字段：`id,ticket,login,symbol,cmd,volume,open_price,close_price,commission,swaps,profit,open_time,close_time,created_at,updated_at`；当前真实记录数 `0`，暂无样例记录�??
- `credit_imports` 字段：`id,user_id,user_name,credit_type,mt4_order_id,amount,batch_no,is_synced,fail_reason,remarks,created_by,updated_by,created_at,updated_at,deleted_at`；当前真实记录数 `0`，暂无样例记录�??
- `BaseModel` 是基�?父类，不对应单独数据表�??

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AdminCoreMt4ModelCommentReadabilityTest.php` 首次失败，提�? `BaseModel.php` 缺少 `$guarded 表示批量赋�?�黑名单`，且仍包�? `Base Model Class` 英文占位说明�?
- GREEN：重�? 5 个模型注释为可读中文并保留原行为后，专项测试通过�?

### 验证记录
- `php -l app\Models\BaseModel.php`：�?�过�?
- `php -l app\Models\BigAgent.php`：�?�过�?
- `php -l app\Models\Mt4User.php`：�?�过�?
- `php -l app\Models\Mt4Trade.php`：�?�过�?
- `php -l app\Models\CreditImport.php`：�?�过�?
- `php -l tests\Feature\AdminCoreMt4ModelCommentReadabilityTest.php`：�?�过�?
- `vendor\bin\phpunit tests\Feature\AdminCoreMt4ModelCommentReadabilityTest.php`：�?�过�?2 tests / 108 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminBatchCreditImportModuleTest.php`：�?�过�?3 tests / 16 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminBatchCreditImportPermissionMigrationTest.php`：�?�过�?1 test / 13 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminBigAgentsCommentReadabilityTest.php`：�?�过�?2 tests / 52 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminRiskMt4ModuleTest.php`：�?�过�?4 tests / 31 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php`：�?�过�?3 tests / 1521 assertions�?
- `rg "Base Model Class|All business models extend this class|Use \$guarded blacklist|Fields hidden by default|Primary key column name|Timestamp storage format|Serialize dates to ISO format|Table Name|Relation:|鏁版嵁|鍏宠仈|鐢ㄦ埛|淇＄敤|澶т唬|浜ゆ槗|妯�?��??" app\Models\BaseModel.php app\Models\BigAgent.php app\Models\Mt4User.php app\Models\Mt4Trade.php app\Models\CreditImport.php`：无命中�?

### 本轮边界
- 本轮只修改模型注释和新增注释质量测试，没有改变表名�?�字段白名单、类型转换�?�软删除、日期序列化、模型关联或后台业务逻辑�?
- 四张业务表当前真实数据均�? 0 条，因此清单记录真实表结构与当前无样例状态；后续若导入真实数据，可再补充样例记录�?
## 149. 2026-06-09 后台数据范围模型与迁移中文注释补�?

### 本次处理目标
- 继续执行“后台不同管理员角色拥有不同菜单权限和数据查看权限�?�的目标中数据查看权限部分�??
- 清理 `RoleDataScope`、`AdminAgentBinding` 以及对应建表迁移中的历史编码乱码�?
- 明确 `role_data_scopes` �? `admin_agent_bindings` 是后台数据范围从数据表配置得到的核心来源�?

### 修改文件
- `app/Models/RoleDataScope.php`
  - 补充 `role_data_scopes` 表保存角色级数据查看范围配置的职责说明�??
  - 补充 `role_id`、`scope_type`、`agent_ids`、`user_ids`、`status` 字段的业务含义�??
  - 补充 `$casts` 中数组字段和整数字段的转换目的�??
  - 补充 `role()` �? `roles.id` 的关联说明�??
- `app/Models/AdminAgentBinding.php`
  - 补充 `admin_agent_bindings` 表保存后台管理员与代理节点绑定关系的职责说明�?
  - 补充 `admin_id`、`agent_id`、`binding_type`、`status` 字段的业务含义�??
  - 补充 `admin()` �? `agent()` 关系说明，明�? `agent_id` 对应 `user_infos.user_id`�?
- `database/migrations/2026_06_06_000001_create_role_data_scopes_table.php`
  - 重写为可读中文注释，说明 RBAC 控制接口访问，本表控制进入接口后的可见数据范围�??
  - 补充 `scope_type` 枚举含义：`all=全部数据`、`self=本人数据`、`created=本人创建`、`agent_tree=绑定代理树`、`custom_agents=指定代理集合`、`custom_users=指定用户集合`�?
  - 明确 `role_id` 唯一约束保证每个角色�?多只有一条数据范围配置来源�??
- `database/migrations/2026_06_06_000002_create_admin_agent_bindings_table.php`
  - 重写为可读中文注释，说明管理员到代理节点的绑定关系用�? `agent_tree` �? `custom_agents` 数据范围�?
  - 补充 `binding_type` 枚举含义：`primary=主绑定`、`extra=额外绑定`�?
- `tests/Feature/AdminDataScopeSchemaCommentReadabilityTest.php`
  - 新增数据范围模型和迁移中文注释可读�?�测试�??
  - 禁止 `鏁版嵁`、`鍏宠仈`、`瑙掕壊`、`绠＄悊`、`浠ｇ悊`、`鐢ㄦ埛` 等历史乱码片段回流�??

### 真实 DB 数据来源
- `role_data_scopes` 当前真实记录数：`100`�?
- `role_data_scopes` 样例：`id=1,role_id=7,scope_type=custom_users,agent_ids=null,user_ids=[610001],status=1`�?
- `admin_agent_bindings` 当前真实记录数：`58`�?
- `admin_agent_bindings` 样例：`id=1,admin_id=5,agent_id=620001,binding_type=primary,status=1`�?
- 说明：记录数来自本轮验证后的当前 DB 状�?�，数据范围测试会写入测试记录，因此以最终取样为准�??

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AdminDataScopeSchemaCommentReadabilityTest.php` 首次失败，提�? `RoleDataScope.php` 缺少 `role_data_scopes 表保存角色级数据查看范围配置`�?
- GREEN：补齐两个模型和两份迁移的中文�?�辑注释后，专项测试通过�?

### 验证记录
- `php -l app\Models\RoleDataScope.php`：�?�过�?
- `php -l app\Models\AdminAgentBinding.php`：�?�过�?
- `php -l database\migrations\2026_06_06_000001_create_role_data_scopes_table.php`：�?�过�?
- `php -l database\migrations\2026_06_06_000002_create_admin_agent_bindings_table.php`：�?�过�?
- `php -l tests\Feature\AdminDataScopeSchemaCommentReadabilityTest.php`：�?�过�?
- `vendor\bin\phpunit tests\Feature\AdminDataScopeSchemaCommentReadabilityTest.php`：�?�过�?2 tests / 60 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminDataScopeServiceTest.php`：�?�过�?4 tests / 6 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminDataScopeManagementTest.php tests\Feature\AdminDataScopeRuntimeLocalizationTest.php`：�?�过�?4 tests / 14 assertions�?
- `rg "鏁版嵁|鍏宠仈|瑙掕壊|绠＄悊|浠ｇ悊|鐢ㄦ埛|Table Name|Relation:|Attribute Casting" app\Models\RoleDataScope.php app\Models\AdminAgentBinding.php database\migrations\2026_06_06_000001_create_role_data_scopes_table.php database\migrations\2026_06_06_000002_create_admin_agent_bindings_table.php`：无命中�?

### 本轮边界
- 本轮只修改数据范围模型和迁移注释，并新增注释质量测试，没有改变表结构、索引�?�模型关联�?�数据范围计算�?�辑或管理员授权规则�?
- 当前数据范围的运行�?�辑仍由 `AdminDataScopeService`、`role_data_scopes`、`admin_agent_bindings` �? `agent_descendants` 共同决定；本轮确保这些数据表来源的注释和参数含义可维护�??
## 150. 2026-06-09 公共 API 响应多语�?契约补齐

### 本次处理目标
- 继续执行“后端必须支持多语言”的目标，优先补齐所有接口共用的响应消息入口�?
- 保证 `ApiResponse` 未显式传�? message 时，可以根据 `ResponseCode` 自动返回当前语言环境下的 `response.*` 文案�?
- 清理 `ApiResponse` �? `ResponseCode` 中历史编码乱码和英文占位注释，补充参数�?�辑说明�?

### 修改文件
- `app/Traits/ApiResponse.php`
  - 重写为干�? UTF-8 中文注释�?
  - 明确�?有前后台 API 统一返回 `code`、`message`、`data` 三个字段�?
  - 明确 `$message` 可传 Laravel 多语�? key；为空时根据 `$code` 通过 `ResponseCode::messageKey()` 自动读取语言包�??
  - 补充 `success($data, $message, $code)` �? `error($message, $code, $data)` 的参数含义及功能作用�?
- `app/Constants/ResponseCode.php`
  - 重写为干�? UTF-8 中文注释�?
  - 保留原有响应码数值和别名常量不变�?
  - 补齐 `messageKey()` 映射，覆�? `INVALID_AGENT_LEVEL`、`INVALID_AUDIT_STATUS`、`WITHDRAWAL_NOT_ALLOWED`、`DEPOSIT_NOT_ALLOWED`、`INVALID_AMOUNT`、`INSUFFICIENT_BALANCE`、`RISK_RATE_EXCEEDED`、`CANCEL_APPLY_EXISTS`、`BLACKLISTED`、`DATA_ALREADY_EXISTS`、`SETTLEMENT_NOT_FOUND`、`ORDER_NOT_FOUND`、`MT4_SYNC_FAILED`、`QUERY_SUCCESS`、`QUERY_FAILED`、`IMPORT_SUCCESS`、`IMPORT_FAILED`、`EXPORT_SUCCESS`、`BATCH_SUCCESS`、`BATCH_PARTIAL_FAILED`、`ACCOUNT_LOCKED`、`RATE_LIMITED`、`EMAIL_SEND_FAILED`、`THIRD_PARTY_ERROR` 等此前可能回�?�? `response.unknown` 的状态码�?
- `tests/Feature/ApiResponseLocalizationContractTest.php`
  - 新增公共 API 响应多语�?契约测试�?
  - 使用反射读取 `ResponseCode` 中全部整数状态码，并校验每个状�?�码都映射到明确�? `response.*` key�?
  - 校验 `resources/lang/zh-CN/response.php` �? `resources/lang/en/response.php` 同时存在对应语言包键�?
  - 禁止公共响应层继续出�? `Standard JSON Response Trait`、`Unified Response Status Code Constants` 和常�? mojibake 乱码片段�?

### 数据与配置来�?
- 响应码来源：`app/Constants/ResponseCode.php` 中定义的整数常量，别名常量去重后由测试反射读取�??
- 多语�?来源：`resources/lang/zh-CN/response.php` �? `resources/lang/en/response.php`�?
- 示例映射：`ResponseCode::SUCCESS` -> `response.success`�?
- 示例映射：`ResponseCode::INVALID_AGENT_LEVEL` -> `response.invalid_agent_level`�?
- 示例映射：`ResponseCode::THIRD_PARTY_ERROR` -> `response.third_party_error`�?

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\ApiResponseLocalizationContractTest.php` 首次失败，提示状态码 `2007` 仍回�?�? `response.unknown`，且 `ApiResponse.php` 缺少“参数�?�辑说明”�??
- GREEN：补齐所有公共响应码�? `response.*` 的映射，并重写公共响应层中文注释后，专项测试通过�?

### 验证记录
- `php -l app\Traits\ApiResponse.php`：�?�过�?
- `php -l app\Constants\ResponseCode.php`：�?�过�?
- `php -l tests\Feature\ApiResponseLocalizationContractTest.php`：�?�过�?
- `vendor\bin\phpunit tests\Feature\ApiResponseLocalizationContractTest.php`：�?�过�?2 tests / 241 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminCommonRuntimeLocalizationTest.php`：�?�过�?2 tests / 6 assertions�?
- `vendor\bin\phpunit tests\Feature\JwtServiceLocalizationTest.php tests\Feature\JwtAuthMiddlewareLocalizationTest.php`：�?�过�?2 tests / 23 assertions�?
- `vendor\bin\phpunit tests\Feature\AdminCheckPermissionMiddlewareReadabilityTest.php tests\Feature\AdminAuthenticateMiddlewareLocalizationTest.php`：�?�过�?1 test / 23 assertions�?
- `rg "Standard JSON Response Trait|Unified Response Status Code Constants|All APIs return unified format|Get the i18n message key|supports i18n key|鐘舶|鎴愬|鏁版嵁|璁よ瘉|鏉冮檺|澶辫触|鍝嶅�?" app\Traits\ApiResponse.php app\Constants\ResponseCode.php`：无命中�?

### 本轮边界
- 本轮只修改公共响应层注释和状态码到语�?�? key 的映射，没有改变 JSON 响应结构、HTTP 状�?�码、控制器调用方式或语�?包文案内容�??
- 后续仍需继续扫描具体后台控制器中直接传入硬编码中�?/英文 message 的接口，并�?�步迁移�? `admin.*`、`response.*` 或模块语�?�? key�?
## 151. 2026-06-09 用户注册服务硬编码消息迁移到多语�? key

### 本次处理目标
- 继续执行“后端必须支持多语言”的目标，将 `UserRegistrationService` 中直接返回给前台页面的硬编码中文业务消息迁移到语�?包�??
- 覆盖注册成功、账号类型错误�?�普通客户缺少邀请人、普通客户缺少有效邀请人四类注册链路消息�?
- 保证前台 Layui/Blade 注册页在中文和英文环境下都可以从后端获得可切换文案�??

### 修改文件
- `app/Services/UserRegistrationService.php`
  - �? `注册成功` 改为 `__('register.success')`�?
  - �? `账户类型无效` 改为 `__('register.invalid_account_type')`�?
  - �? `普�?�客户必须填写邀请人ID` 改为 `__('register.customer_inviter_required')`�?
  - �? `普�?�客户必须提供有效邀请人ID` 改为 `__('register.customer_valid_inviter_required')`�?
- `resources/lang/zh-CN/register.php`
  - 新增 `success`、`invalid_account_type`、`customer_inviter_required`、`customer_valid_inviter_required` 中文文案�?
- `resources/lang/en/register.php`
  - 新增同名英文文案，并补齐 `email_exists`、`inviter_*`、`invalid_commission_mode`、`inviter_valid` 等注册规�? key，避免英文环境读取注册规则时回�??�? key�?
- `tests/Feature/UserRegistrationServiceMessageKeyTest.php`
  - 新增注册服务消息 key 契约测试�?
  - 静�?�检查注册服务不再保留上述硬编码中文消息�?
  - 校验中英�? register 语言包同时存在注册服务依�? key�?

### 多语�? key 清单
- `register.success`：注册成功�??
- `register.invalid_account_type`：账号类型不在代理商/普�?�客户范围内�?
- `register.customer_inviter_required`：普通客户注册必须填写邀请人 ID�?
- `register.customer_valid_inviter_required`：普通客户注册必须提供有效邀请人 ID�?

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\UserRegistrationServiceMessageKeyTest.php` 首次失败，提�? `UserRegistrationService.php` 缺少 `__('register.success')`，且中文 register 语言包缺�? `success` key�?
- GREEN：迁移服务消息并补齐中英文语�?包后，专项测试�?�过�?

### 验证记录
- `php -l app\Services\UserRegistrationService.php`：�?�过�?
- `php -l resources\lang\zh-CN\register.php`：�?�过�?
- `php -l resources\lang\en\register.php`：�?�过�?
- `php -l tests\Feature\UserRegistrationServiceMessageKeyTest.php`：�?�过�?
- `vendor\bin\phpunit tests\Feature\UserRegistrationServiceMessageKeyTest.php`：�?�过�?2 tests / 24 assertions�?
- `vendor\bin\phpunit tests\Feature\UserRegistrationServiceLocalizationTest.php`：�?�过�?2 tests / 14 assertions�?
- `vendor\bin\phpunit tests\Feature\UserRegistrationServiceLocalizationTest.php tests\Feature\DefaultAdminAndFrontMenuRoleMigrationTest.php`：�?�过�?2 tests / 14 assertions�?
- `rg "注册成功|账户类型无效|普�?�客户必须填写邀请人ID|普�?�客户必须提供有效邀请人ID" app\Services\UserRegistrationService.php`：无命中�?

### 本轮边界
- 本轮只迁移注册服务返回消息和补齐语言�? key，没有改变注册事务�?�用户编号生成�?�登录账号创建�?�用户资料创建�?�实名认证创建或代理后代关系写入逻辑�?
- 后续仍需继续扫描后台控制器中�? `$validator->errors()->first()`、`$e->getMessage()` 以及旧控制器硬编码验证消息，逐步迁移到可控的语言�? key 或异常包装策略�??
## 152. 2026-06-09 后台权限名称中文注释与权限字符串文档复核

### 本次处理目标
- 响应“后台所有权限名称，�? MD 文件中必须有中文注释当前权限名称、对应字符串和功能作用�?�的要求�?
- 复核 `docs/admin-permission-name-reference.md` 是否已经以真�? DB 权限数据为准，�?�条写明后台权限名称、`permissions.slug` 权限字符串�?�`permissions.api_route` 接口路由字符串�?�`permissions.route` 页面路由和功能作用�??
- 用现有测试证明文档不是手写零散清单，而是能覆盖当前真实数据库 `permissions` 表中 `guard_type=admin` 的全部后台权限记录�??

### 复核文件
- `docs/admin-permission-name-reference.md`
  - 已包含�?�权限名称中文注释规则�?�小节，明确每条后台权限必须独立成行�?
  - “权限明细�?�表格已逐条列出 `权限名称`、`权限字符串`、`接口路由字符串`、`页面路由`、`状�?�` �? `功能作用`�?
  - `功能作用` 文本会点名当前权限名称，并说明该权限用于菜单显示、按钮显隐�?�页面入口或接口鉴权的控制边界�??
- `tests/Feature/AdminPermissionNameReferenceRowCompletenessTest.php`
  - 直接读取真实 DB `permissions` 表，筛�?? `guard_type=admin`，校验每�?条后台权限都必须在同�?�? MD 表格行中写明名称、字符串和功能作用�??
- `tests/Feature/AdminPermissionNameReferenceDocumentationTest.php`
  - 校验权限说明文档覆盖全部后台权限�? `slug`、`api_route` �? `route` 字段，避免遗漏真实数据库权限配置�?

### 真实 DB 数据来源
- 数据表：`permissions`�?
- 筛�?�条件：`guard_type=admin`�?
- 当前后台权限总数：`195`�?
- 当前测试断言总数：�?�行完整�? `1757` 个断�?，文档覆盖�?? `810` 个断�?�?

### 验证记录
- `vendor\bin\phpunit tests\Feature\AdminPermissionNameReferenceRowCompletenessTest.php`：�?�过，`1 test / 1757 assertions`�?
- `vendor\bin\phpunit tests\Feature\AdminPermissionNameReferenceDocumentationTest.php`：�?�过，`1 test / 810 assertions`�?

### 本轮结论
- 当前后台权限名称说明 MD 已满足：�?有后台权限名称均有中文说明，�?有权限字符串均以反引号�?�字列出，并且每条权限都有对应功能作用说明�??
- 本轮没有修改业务鉴权逻辑、菜单渲染�?�辑、角色授权�?�辑或数据库权限数据，只补充�?终清单中的审计记录�??
## 153. 2026-06-09 后台 Blade 登录控制器多语言验证与中文参数注释补�?

### 本次处理目标
- 继续执行“后端必须支持多语言”和“所有模块文件及参数必须有详细中文�?�辑注释”的要求�?
- 修复 `AdminAuthController` 中后�? Blade 登录表单验证消息固定 `zh_CN` 和硬编码中文提示的问题�??
- 补齐后台 Blade 登录入口、登录动作�?��??出动作以�? `email`、`password`、`remember`、`$request` 参数的中文�?�辑说明�?

### 修改文件
- `app/Http/Controllers/Admin/AdminAuthController.php`
  - 新增“后�? Blade 登录控制器�?�类级中文说明，明确该控制器服务传统 Blade 登录页，不负�? JWT API 登录�?
  - �? `showLogin()` 补充中文说明：已登录管理员跳转控制台，未登录管理员返回登录页�?
  - �? `doLogin(Request $request)` 补充中文参数说明：`$request`、`email`、`password`、`remember` 的来源�?�含义和功能作用�?
  - �? `email.required` 改为 `__('validation.required', ['attribute' => __('auth.email')])`，跟随当前语�?环境返回验证文案�?
  - �? `password.required` 改为 `__('validation.required', ['attribute' => __('auth.password_label')])`，移除硬编码“不能为空�?��??
  - 引入 `App\Models\AdminLoginLog`，并为后台登录审计日志写入补充中文说明�??
  - �? `logout(Request $request)` 补充 Session 失效与重新生�? CSRF Token 的安全边界说明�??
- `resources/lang/zh-CN/auth.php`
  - 新增 `auth.password_label`，中文�?�为“密码�?��??
- `resources/lang/en/auth.php`
  - 新增 `auth.password_label`，英文�?�为 `Password`�?
- `tests/Feature/AdminBladeLoginControllerLocalizationTest.php`
  - 新增后台 Blade 登录控制器多语言与中文注释契约测试�??
  - 约束控制器不得再出现 `__('common.required', [], 'zh_CN')`、硬编码“不能为空�?�或 mojibake 空�?�提示�??
  - 约束控制器必须使用当�? locale �? `validation.required` �? `auth.*` 语言 key�?

### 多语�? key 清单
- `validation.required`：Laravel 表单必填验证文案，使用当前语�?环境�?
- `auth.email`：管理员登录邮箱字段名称�?
- `auth.password_label`：管理员登录密码字段名称，本轮补齐中英文语言包�??
- `auth.failed`：管理员登录失败提示，保留原有多语言 key�?

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AdminBladeLoginControllerLocalizationTest.php` 首次失败，提�? `AdminAuthController` 缺少“后�? Blade 登录控制器�?�中文�?�辑注释，同时源码仍保留固定 `zh_CN` 和硬编码中文验证提示�?
- GREEN：补齐控制器中文注释、改�? `validation.required` 当前语言环境文案、补�? `auth.password_label` 中英�? key 后，专项测试通过�?

### 验证记录
- `php -l app\Http\Controllers\Admin\AdminAuthController.php`：�?�过�?
- `php -l resources\lang\zh-CN\auth.php`：�?�过�?
- `php -l resources\lang\en\auth.php`：�?�过�?
- `php -l tests\Feature\AdminBladeLoginControllerLocalizationTest.php`：�?�过�?
- `vendor\bin\phpunit tests\Feature\AdminBladeLoginControllerLocalizationTest.php`：�?�过，`1 test / 19 assertions`�?
- `vendor\bin\phpunit tests\Feature\AdminCommonRuntimeLocalizationTest.php`：�?�过，`2 tests / 6 assertions`�?
- `vendor\bin\phpunit tests\Feature\AdminAuthenticateMiddlewareLocalizationTest.php`：�?�过，`2 tests / 8 assertions`�?
- `vendor\bin\phpunit tests\Feature\AdminAuthControllerCommentReadabilityTest.php`：�?�过，`1 test / 35 assertions`�?
- `rg "common\.required|zh_CN|不能为空|不能為空|涓嶒兘涓虹┖" app\Http\Controllers\Admin\AdminAuthController.php`：无命中�?

### 本轮边界
- 本轮只修复传�? Blade 后台登录控制器的表单验证多语�?和中文注释，不修�? JWT API 登录、管理员账号密码规则、Session guard 配置、登录路由或数据库结构�??
- 后续仍需继续扫描其他后台 Blade 控制器和页面中是否存在硬编码提示、固�? locale、参数注释缺失或 UI 风格未统�?的问题�??
## 154. 2026-06-09 后台控制器异常消息统�?多语�?响应

### 本次处理目标
- 继续执行“后端必须支持多语言”的要求，收敛后台控制器 `catch` 分支直接返回 `$e->getMessage()` 的问题�??
- 避免数据库异常�?�文件路径�?�第三方接口异常或内部类名被原样返回给后台页�?/API 前端�?
- 将未预期服务端异常统�?改为 `response.server_error` 当前语言环境文案�?

### 修改文件
- `app/Http/Controllers/Admin/AdminBaseController.php`
  - 重写为可读中文�?�辑注释，说明后台控制器统一复用 `ApiResponse`�?
  - 新增 `serverErrorResponse()` 方法�?
  - `serverErrorResponse()` 固定返回 `__('response.server_error')` �? `ResponseCode::SERVER_ERROR`，确保服务端异常响应跟随当前语言环境�?
  - 参数说明明确：该方法不接收异常对象作为返回内容，避免泄露 SQL、文件路径�?�第三方接口细节或内部类名�??
- 批量替换以下后台控制器中�? `$this->error($e->getMessage(), ResponseCode::SERVER_ERROR)` �? `$this->serverErrorResponse()`�?
  - `AdminController.php`
  - `AgentController.php`
  - `AgentLevelController.php`
  - `BigAgentController.php`
  - `BlacklistController.php`
  - `CancelApplyController.php`
  - `CommissionController.php`
  - `DashboardController.php`
  - `DepositController.php`
  - `GroupConfigController.php`
  - `NewsController.php`
  - `PaymentChannelController.php`
  - `SystemConfigController.php`
  - `UserController.php`
  - `VoucherController.php`
  - `WithdrawController.php`
- `tests/Feature/AdminExceptionMessageLocalizationTest.php`
  - 新增后台异常消息多语�?契约测试�?
  - 静�?�扫�? `app/Http/Controllers/Admin`，禁止后台控制器继续直接外泄 `$e->getMessage()`�?
  - 校验 `AdminBaseController` 必须提供 `serverErrorResponse()`，并使用 `response.server_error` �? `ResponseCode::SERVER_ERROR`�?

### 多语�? key 清单
- `response.server_error`：服务端未预期异常统�?提示�?
  - `resources/lang/zh-CN/response.php`：服务器内部错误�?
  - `resources/lang/en/response.php`：Internal server error�?
- `ResponseCode::SERVER_ERROR`：服务端错误状�?�码，统�?�? `ResponseCode::messageKey()` 映射�? `response.server_error`�?

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AdminExceptionMessageLocalizationTest.php` 首次失败，提�? `AdminController.php` 仍直接返�? `$e->getMessage()`，且 `AdminBaseController` 缺少“后台服务端异常响应”与 `serverErrorResponse()`�?
- GREEN：新增基类统�?异常响应方法并替�? 16 个后台控制器的异常外泄调用后，专项测试�?�过�?

### 验证记录
- `php -l app\Http\Controllers\Admin\AdminBaseController.php`：�?�过�?
- 批量 `php -l` 以下控制器：`WithdrawController.php`、`VoucherController.php`、`UserController.php`、`SystemConfigController.php`、`NewsController.php`、`BigAgentController.php`、`CancelApplyController.php`、`AgentController.php`、`DashboardController.php`、`AdminController.php`、`BlacklistController.php`、`CommissionController.php`、`AgentLevelController.php`、`DepositController.php`、`GroupConfigController.php`、`PaymentChannelController.php`：全部�?�过�?
- `vendor\bin\phpunit tests\Feature\AdminExceptionMessageLocalizationTest.php`：�?�过，`2 tests / 43 assertions`�?
- `vendor\bin\phpunit tests\Feature\ApiResponseLocalizationContractTest.php`：�?�过，`2 tests / 241 assertions`�?
- `vendor\bin\phpunit tests\Feature\AdminCommonRuntimeLocalizationTest.php`：�?�过，`2 tests / 6 assertions`�?
- `vendor\bin\phpunit tests\Feature\AdminDashboardControllerLocalizationTest.php`：�?�过，`2 tests / 6 assertions`�?
- `vendor\bin\phpunit tests\Feature\AdminUserControllerLocalizationTest.php`：�?�过，`2 tests / 36 assertions`�?
- `rg "getMessage\(\)|serverErrorResponse" app\Http\Controllers\Admin`：只�? `serverErrorResponse()` 调用和基类方法说明，未再发现后台控制器直接返�? `$e->getMessage()`�?

### 本轮边界
- 本轮只统�?未预期异常的服务端错误响应，不改变业务可预期错误、表单校验失败�?�权限不足�?�数据不存在等具体业务提示�??
- 当前 `catch (\Exception $e)` 仍保留原控制流，仅将前端可见 message 改为多语�?安全文案；如后续�?要记录异常详情，应接入日志�?�不是返回给前端�?
## 155. 2026-06-09 后台 Blade 登录链路统一到现�? Layui 视图

### 本次处理目标
- 继续执行“后�? UI 必须参�?? Vben Admin、Vue Naive Admin、Naive UI Admin、Ant Design Pro、Arco Design Pro，并使用 Laravel Blade 模板渲染”的要求�?
- 修复 `AdminAuthController` 仍返回旧 `resources/views/admin/auth/login.blade.php` 的问题，避免后台登录入口绕开 `resources/admin/layui` 现代后台视图体系�?
- 修复现代后台登录页表单字段与控制器校验不�?致的问题：页面提�? `username`，控制器校验 `email`，会导致传统 Blade 登录链路不可用�??

### 修改文件
- `app/Http/Controllers/Admin/AdminAuthController.php`
  - `showLogin()` �? `view('admin.auth.login')` 改为 `view('admin_layui::auth.login')`�?
  - 中文注释同步说明未登录管理员返回 `admin_layui::auth.login` 现代 Layui Blade 模板�?
  - 继续保留 `email`、`password`、`remember` 参数说明和多语言验证逻辑�?
- `resources/admin/layui/auth/login.blade.php`
  - 表单 action 改为 `{{ route('admin.login.post') }}`，method 改为 `POST`，并加入 `@csrf`�?
  - 登录账号字段�? `name="username"` 改为 `name="email"`，与 `AdminAuthController::doLogin` �? `email|required|email` 校验保持�?致�??
  - 密码字段使用 `data-translate-placeholder="auth.password_label"` �? `__('auth.password_label')`�?
  - 新增 `remember` 复�?�框，与控制器中�? `$request->boolean('remember')` 对齐�?
  - 补充 `email`、`password`、`remember` 的中文�?�辑注释和功能作用说明�??
- `public/js/admin/layui/auth/login.js`
  - 重写�? Blade 表单登录脚本，不再拦截表单请�? `/api/admin/login`�?
  - 保留 Layui form 正常提交，返�? `true` 交给浏览�? POST �? `admin.login.post`�?
  - 保留语言切换逻辑，并补充 `email`、`password`、`remember`、`CRM.switchLang` 的中文注释�??
- `tests/Feature/AdminBladeLoginViewConsistencyTest.php`
  - 新增后台 Blade 登录视图�?致�?�测试�??
  - 约束控制器必须使�? `admin_layui::auth.login`，并禁止回�??到旧 `admin.auth.login`�?
  - 约束现代登录页必须提�? `email`、`password`、`remember` 字段�?
- `tests/Feature/AdminLoginJsCommentReadabilityTest.php`
  - 按当�? Blade 登录目标重写测试契约�?
  - 禁止登录脚本回�??�? `/api/admin/login` �? `CrmAjax.setToken` �? JWT API 登录模式�?

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AdminBladeLoginViewConsistencyTest.php` 首次失败，提�? `AdminAuthController` 仍返�? `admin.auth.login`，且现代登录页缺�? `admin.login.post` 表单 action、`email` 字段�? `remember` 字段�?
- GREEN：切换控制器视图、修复现代登录页字段、重写登�? JS �? Blade 表单提交后，专项测试通过�?
- 兼容修复：`AdminLoginJsCommentReadabilityTest` 原先�? JWT API 登录脚本设计，已改为当前 Blade 登录目标，防止未来回�?�? API 拦截登录�?

### 验证记录
- `php -l app\Http\Controllers\Admin\AdminAuthController.php`：�?�过�?
- `php -l tests\Feature\AdminBladeLoginViewConsistencyTest.php`：�?�过�?
- `php -l tests\Feature\AdminLoginJsCommentReadabilityTest.php`：�?�过�?
- `vendor\bin\phpunit tests\Feature\AdminBladeLoginViewConsistencyTest.php`：�?�过，`2 tests / 14 assertions`�?
- `vendor\bin\phpunit tests\Feature\AdminLoginJsCommentReadabilityTest.php`：�?�过，`2 tests / 16 assertions`�?
- `vendor\bin\phpunit tests\Feature\AdminBladeLoginControllerLocalizationTest.php`：�?�过，`1 test / 19 assertions`�?
- `vendor\bin\phpunit tests\Feature\AdminBladeUiTest.php`：�?�过，`1 test / 5 assertions`�?
- `vendor\bin\phpunit tests\Feature\AdminLayoutUiReferenceDensityTest.php`：�?�过，`2 tests / 25 assertions`�?
- `vendor\bin\phpunit tests\Feature\AdminLayoutShellReadabilityTest.php`：�?�过，`2 tests / 18 assertions`�?
- `vendor\bin\phpunit tests\Feature\AdminLayuiLayoutReadableChineseTest.php`：�?�过，`1 test / 20 assertions`�?
- `rg "admin.auth.login" app routes tests`：生产代码无旧视图引用，仅测试说明中保留回归约束�?
- `rg "/api/admin/login|CrmAjax.setToken" public\js\admin\layui\auth\login.js tests\Feature\AdminLoginJsCommentReadabilityTest.php`：登录脚本无实际 API/JWT 调用残留，仅测试中保留禁止回�?断言�?

### 本轮边界
- 本轮只统�?后台 Blade 登录页入口与表单字段，不修改 `/api/admin/login` JWT 登录接口本身�?
- �? `resources/views/admin/auth/login.blade.php` 文件仍保留在仓库中，但当前后台登录控制器已不再引用；后续可继续审�? `resources/views/admin` 旧视图目录是否需要删除�?�归档或增加禁止引用测试�?
## 156. 2026-06-10 旧后�? Blade 视图归档�? admin_layui 回�??保护

### 本次处理目标
- 继续执行“后�? UI 必须使用 Laravel Blade 模板渲染，并参�?? Vben Admin、Vue Naive Admin、Naive UI Admin、Ant Design Pro、Arco Design Pro”的要求�?
- 防止后台页面再次回�??到历�? `resources/views/admin` 旧视图目录�??
- 修复 `Admin\AuthController@showLogin()` 中仍存在的错误旧视图引用 `view("admin.layui.auth.login")`，统�?改为 `admin_layui::auth.login`�?

### 修改文件
- `app/Http/Controllers/Admin/AuthController.php`
  - `showLogin()` �? `view("admin.layui.auth.login")` 改为 `view("admin_layui::auth.login")`�?
  - 中文注释同步说明该旧控制器路径即使保留，也必须返回现�? Layui Blade 登录页�??
- `resources/views/admin/README.md`
  - 新增旧后�? Blade 视图归档说明�?
  - 明确 `resources/views/admin` 只用于迁移对照和排查旧页面差异�??
  - 明确当前后台页面必须统一使用 `resources/admin/layui` �? `admin_layui::` 视图命名空间�?
  - 明确禁止在生产路由或控制器中继续引用 `view('admin.*')`、`view("admin.*")`、`@extends('admin.layouts.app')` 或旧目录�?部视图�??
- `tests/Feature/AdminLegacyViewNamespaceGuardTest.php`
  - 新增旧后台视图命名空间回�?保护测试�?
  - 扫描 `app`、`routes`、`resources/admin/layui` 下的 PHP/Blade 文件，禁止旧 `admin.*` 视图引用回流�?
  - 校验旧视图目录必须保留归档说明，避免维护者误以为它仍是当前后台入口�??

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AdminLegacyViewNamespaceGuardTest.php` 首次失败，发�? `app\Http\Controllers\Admin\AuthController.php` 仍包�? `view("admin.layui.auth.login")`，并�? `resources/views/admin/README.md` 不存在�??
- GREEN：修复旧控制器登录视图引用并新增归档说明后，专项测试通过�?

### 验证记录
- `php -l app\Http\Controllers\Admin\AuthController.php`：�?�过�?
- `php -l tests\Feature\AdminLegacyViewNamespaceGuardTest.php`：�?�过�?
- `vendor\bin\phpunit tests\Feature\AdminLegacyViewNamespaceGuardTest.php`：�?�过，`2 tests / 1222 assertions`�?
- `vendor\bin\phpunit tests\Feature\AdminBladeLoginViewConsistencyTest.php`：�?�过，`2 tests / 14 assertions`�?
- `vendor\bin\phpunit tests\Feature\AdminAuthControllerCommentReadabilityTest.php`：�?�过，`1 test / 35 assertions`�?
- `vendor\bin\phpunit tests\Feature\AdminBladeUiTest.php`：�?�过，`1 test / 5 assertions`�?
- `vendor\bin\phpunit tests\Feature\AdminBladeModuleCoverageTest.php`：�?�过，`20 tests / 60 assertions`�?
- `vendor\bin\phpunit tests\Feature\AdminPageMenuPermissionCoverageTest.php`：�?�过，`1 test / 2 assertions`�?
- `rg "view\('admin\." app routes resources\admin\layui`：无命中�?
- `AdminLegacyViewNamespaceGuardTest` 已完整扫�? `app`、`routes`、`resources/admin/layui`，证明生产代码不再引用旧 `resources/views/admin` 后台视图�?

### 本轮边界
- 本轮没有删除 `resources/views/admin` 旧视图文件，只新增归档说明并禁止生产代码继续引用�?
- 旧视图目录中仍可能存在历史乱码和旧布�?代码，但它们现在被定义为迁移对照资料；当前后台页面入口必须走 `resources/admin/layui`�?
## 157. 2026-06-10 菜单服务中文逻辑与参数注释补�?

### 本次变更文件
- `app/Services/MenuService.php`
  - 补齐菜单服务类�?�守卫类型�?�权限编号集合�?�菜单集合�?�语�?环境等关键参数的中文逻辑注释�?
  - 清理 `Menu Service`、`Function`、`Parameter`、`Returns`、`Table Name`、`Relation:` 等英文占位式注释，避免后续维护时出现中英混杂和含义不明确的问题�??
  - 保留权限菜单�? `permissions` 表配置生成的核心逻辑，未改变菜单鉴权行为�?
- `tests/Feature/MenuServiceCommentReadabilityTest.php`
  - 强化菜单服务中文注释可读性测试�??
  - 增加英文占位片段和常见乱码片段的禁止断言，用测试约束后续新增注释质量�?

### TDD 执行记录
- RED：先调整 `MenuServiceCommentReadabilityTest`，要求存�? `菜单服务。` 等中文说明，并禁�? `Menu Service` 等英文占位片段；测试按预期失败，证明旧注释未满足当前中文注释规范�?
- GREEN：补齐并清理 `MenuService.php` 注释后，重新运行测试通过�?

### 验证命令
- `php -l app\Services\MenuService.php`
- `php -l tests\Feature\MenuServiceCommentReadabilityTest.php`
- `vendor\bin\phpunit tests\Feature\MenuServiceCommentReadabilityTest.php`
- `vendor\bin\phpunit tests\Feature\MenuControllerCommentReadabilityTest.php`
- `vendor\bin\phpunit tests\Feature\AdminBladeButtonPermissionCoverageTest.php`
- `vendor\bin\phpunit tests\Feature\AdminPageMenuPermissionCoverageTest.php`
- `rg -n "Menu Service|Function|Parameter|Returns|Table Name|Relation:" app\Services\MenuService.php tests\Feature\MenuServiceCommentReadabilityTest.php`
- `rg -n "閻|閺|闁|濞|缁|缂|濠|閿|閸|�?" app\Services\MenuService.php tests\Feature\MenuServiceCommentReadabilityTest.php`

### 验证结果
- `MenuServiceCommentReadabilityTest`�?2 个测试�??26 个断�?通过�?
- `MenuControllerCommentReadabilityTest`�?3 个测试�??37 个断�?通过�?
- `AdminBladeButtonPermissionCoverageTest`�?2 个测试�??117 个断�?通过�?
- `AdminPageMenuPermissionCoverageTest`�?1 个测试�??2 个断�?通过�?
- 生产文件 `app/Services/MenuService.php` 未再发现英文占位注释或常见中文乱码片段�??

### 对目标的推进
- 菜单服务是前后台菜单与按钮权限从数据表配置生成的核心服务之一，本次补齐了其关键参数中文注释，并用测试固定注释规范�?
- 本次未改动数据库结构、菜单权限数据和运行时鉴权�?�辑，仅提升维护可读性与文档�?致�?��??
## 158. 2026-06-11 用户注册服务中文逻辑注释与多语言约束复核

### 本次变更文件
- `app/Services/UserRegistrationService.php`
  - 将类标题从中英混�? `用户注册服务 | User Registration Service` 改为纯中�? `用户注册服务。`�?
  - 补齐注册主入口�?�注册数据校验�?�邮箱重复检查�?�邀请人规则校验、业�? user_id 生成、登录账号创建�?�业务资料创建�?�实名认证资料创建�?�代理后代关系同步�?��?�别标准化�?�注册前置验证等方法的中文�?�辑注释�?
  - 明确 `$data`、`$parentId`、`$accountType`、`$commissionMode`、`$userId`、`$userLogin`、`$userInfo`、`$parentInfo`、`$familyTree`、`$treeIds`、`$ancestorIds` 等参数或中间变量的业务含义和功能作用�?
  - 说明 `agent_descendants` 表保存代理与下级用户的祖先后代关系，用于后续数据权限、团队统计和返佣查询�?
  - 保留原有注册校验、事务写库�?�语�?�? key、家族链和代理后代关系写入�?�辑，未改变业务行为�?
- `tests/Feature/UserRegistrationServiceLocalizationTest.php`
  - 重写为干�? UTF-8 中文注释测试�?
  - 要求注册服务源码必须包含可读中文参数说明，并禁止 `User Registration Service`、`鐢`、`璇`、`鍙`、`閭`、`缁`、`锟`、`�??` 等英文标题或历史编码残留�?

### TDD 执行记录
- RED：先更新 `UserRegistrationServiceLocalizationTest`，要求注册服务包�? `用户注册服务。`、核心参数中文说明和 `agent_descendants` 表用途说明；首次运行失败，提示生产文件仍缺少 `用户注册服务。`，且保留 `User Registration Service`�?
- GREEN：只重写 `UserRegistrationService.php` 注释块，不改注册业务代码；重新运行专项测试�?�过�?

### 验证命令
- `php -l app\Services\UserRegistrationService.php`
- `php -l tests\Feature\UserRegistrationServiceLocalizationTest.php`
- `vendor\bin\phpunit tests\Feature\UserRegistrationServiceLocalizationTest.php`
- `vendor\bin\phpunit tests\Feature\UserRegistrationServiceMessageKeyTest.php`
- `vendor\bin\phpunit tests\Feature\UserRegistrationServiceLocalizationTest.php tests\Feature\FrontAuthControllerLocalizationTest.php`
- `rg -n "User Registration Service|鐢|璇|鍙|閭|缁|锟|€\?" app\Services\UserRegistrationService.php tests\Feature\UserRegistrationServiceLocalizationTest.php`

### 验证结果
- `UserRegistrationServiceLocalizationTest`�?3 个测试�??24 个断�?通过�?
- `UserRegistrationServiceMessageKeyTest`�?2 个测试�??24 个断�?通过�?
- `UserRegistrationServiceLocalizationTest + FrontAuthControllerLocalizationTest`�?3 个测试�??24 个断�?通过�?
- 生产文件 `app/Services/UserRegistrationService.php` 未再发现注册服务英文标题或常见乱码片段；搜索命中仅保留在测试的禁止片段清单中�?

### 对目标的推进
- 用户注册服务同时影响前台代理商和普�?�客户注册，本次补齐了多表写入�?�邀请人规则、数据权限基�?关系和多语言消息的中文维护说明�??
- 本轮没有改动数据库结构�?�注册返回结构�?�注册校验规则�?�密码加密方式或代理关系写入逻辑，仅提升注释可维护�?�和测试约束�?
## 159. 2026-06-11 前台注册规则与代理家族链服务中文注释补齐

### 本次变更文件
- `app/Services/FrontRegisterRuleService.php`
  - 将旧英文说明 `Port of legacy RegisterEnMiddleware / RegisterGmtkCnEnMiddleware invite rules.` 改为中文类注释�??
  - 补齐前台注册�?请规则服务的功能说明，明确代理商和普通客户注册都�?要校验邀请人是否存在、是否启用�?�是否为代理账号�?
  - 补齐 `validate()` 方法参数注释，说�? `$inviterId`、`$accountType`、`$commissionMode`、`$login`、`$info` 的业务含义�??
  - 明确 `message` 返回的是 `register` 语言�? key，上层再通过 `__()` 转成当前语言文案�?
- `app/Services/FamilyTreeService.php`
  - 将英文标题和历史编码乱码注释重写为干�? UTF-8 中文注释�?
  - 补齐代理家族链服务类说明，明�? `user_infos.family_tree` �? `agent_descendants` 表分别承担链路存储�?�团队统计�?�返佣汇总�?�数据范围过滤等作用�?
  - 补齐 `getAncestors()`、`getDirectChildren()`、`getAllDescendants()`、`getSubAgentStats()`、`getAgentStats()`、`getNetworkTree()`、`rebuildFamilyTree()`、`rebuildDescendants()` 的参数和中间变量中文注释�?
  - 明确 `$userId`、`$agentId`、`$dateFrom`、`$dateTo`、`$descendantIds`、`$treeIds`、`$depth`、`$isDirect` 等参数或变量的�?�辑含义�?
  - 保留原有查询条件、返回字段�?�事务边界和代理关系重建逻辑，仅把少量单行早返回改为带花括号的等价写法�??
- `tests/Feature/FrontRegistrationRuleAndFamilyTreeServiceCommentReadabilityTest.php`
  - 新增前台注册规则与代理家族链服务中文注释可读性测试�??
  - 要求两个服务必须包含关键中文参数说明，并禁止旧英文占位标题与常见历史编码乱码片段�?

### TDD 执行记录
- RED：先新增 `FrontRegistrationRuleAndFamilyTreeServiceCommentReadabilityTest`，首次运�? 3 个测试全部失败，分别提示 `FrontRegisterRuleService` 缺少 `前台注册�?请规则服务�?�`、`FamilyTreeService` 缺少 `代理家族链服务�?�`，并保留 `Port of legacy` 等英文占位注释�??
- GREEN：只补齐两个服务的中文注释和参数说明，不改业务判断；重新运行专项测试通过�?

### 验证命令
- `php -l app\Services\FrontRegisterRuleService.php`
- `php -l app\Services\FamilyTreeService.php`
- `php -l tests\Feature\FrontRegistrationRuleAndFamilyTreeServiceCommentReadabilityTest.php`
- `vendor\bin\phpunit tests\Feature\FrontRegistrationRuleAndFamilyTreeServiceCommentReadabilityTest.php`
- `vendor\bin\phpunit tests\Feature\UserRegistrationServiceLocalizationTest.php tests\Feature\UserRegistrationServiceMessageKeyTest.php tests\Feature\FrontAuthControllerLocalizationTest.php`
- `vendor\bin\phpunit tests\Feature\FrontDashboardControllerCommentReadabilityTest.php tests\Feature\FrontAgentControllerCommentReadabilityTest.php`
- `rg -n "Port of legacy|Get the full ancestor chain|Get all direct children|Get all descendants|Get agent|Get comprehensive statistics|Get full network tree|Rebuild family_tree|Rebuild agent_descendants|Remove self from the chain|Recursively rebuild children|Delete existing records|Find all users whose family_tree contains this agent|鐢|璇|鍙|閭|缁|锟|€\?" app\Services\FrontRegisterRuleService.php app\Services\FamilyTreeService.php tests\Feature\FrontRegistrationRuleAndFamilyTreeServiceCommentReadabilityTest.php`

### 验证结果
- `FrontRegistrationRuleAndFamilyTreeServiceCommentReadabilityTest`�?3 个测试�??36 个断�?通过�?
- `UserRegistrationServiceLocalizationTest + UserRegistrationServiceMessageKeyTest + FrontAuthControllerLocalizationTest`�?3 个测试�??24 个断�?通过�?
- `FrontDashboardControllerCommentReadabilityTest + FrontAgentControllerCommentReadabilityTest`�?2 个测试�??31 个断�?通过�?
- 生产文件 `FrontRegisterRuleService.php` �? `FamilyTreeService.php` 未再命中旧英文占位标题或常见乱码片段；搜索命中仅保留在测试禁止片段清单中�?

### 对目标的推进
- 前台代理商和普�?�客户注册�?�邀请关系�?�代理团队树、数据范围基�?链路的服务层中文注释已补齐�??
- 本轮没有改动注册�?请校验规则�?�团队统�? SQL、返佣汇总字段�?�代理网络树返回结构或数据库结构�?
## 160. 2026-07-07 后台礼品导出状态校准与注释回归保护

### 本次处理目标
- 校准后台礼品模块当前状�?�，避免继续把已实现�? `shipment_list_export` 新项目落点误写成待补导出�?
- 明确 `admin_gift_export` 当前绑定 `admin_api_exportGiftShipments`，导出文件名�? `gift_shipments_export.csv`�?
- 保留真实边界：兑换扣库存/积分消耗联动与旧项目一致不迁移：旧 send_gift 只写 gift_shipments，无 gift_items 目录表，gift_items 仅用于前台 available_gifts 展示，不能把当前发放 CSV 导出等同于完整礼品兑换规则闭环（详见 ## 381.锛夈€

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Admin/GiftController.php`
  - 椤堕儴妯″潡璇存槑鏀逛负褰撳墠浜嬪疄锛氬彂璐у垪琛ㄣ?佸彲鍙戞斁鍦板潃鍒楄〃銆佹壒閲忓彂鏀俱?佺墿娴佹洿鏂般?佺ぜ鍝侀厤缃? CRUD 鍜屽綋鍓嶇瓫閫? CSV 瀵煎嚭宸茶惤鍦般??
  - 鍒犻櫎鍘熷厛鎶婂鍑鸿兘鍔涙弿杩颁负浠呭０鏄庢潈闄愬拰寰呰ˉ鎺ュ彛鐨勮繃鏈熻娉曘??
  - 灏? `writeGiftOperationLog()` 鐨勬敞閲婃敼鍥炵ぜ鍝佸彂鏀?/鐗╂祦鏇存柊鎿嶄綔鏃ュ織锛屾妸鈥滅敓鎴? CSV 涓嬭浇鍝嶅簲鈥濇敞閲婃斁鍒? `csvDownload()`銆?
  - 灏嗗彂璐у垪琛ㄧ瓫閫夋潯浠舵敞閲婃斁鍥? `applyShipmentFilters()`锛岄伩鍏嶈创鍦? `updateShipment()` 鍓嶈瀵肩淮鎶ゃ??
- `tests/Feature/AdminGiftModuleTest.php`
  - 鏂板 `test_gift_controller_comments_match_export_implementation`锛岄潤鎬佺害鏉熸帶鍒跺櫒娉ㄩ噴蹇呴』鍖归厤褰撳墠瀵煎嚭瀹炵幇銆?
  - 鏂板 `test_final_checklist_records_current_gift_export_closure`锛岀害鏉熸湰娓呭崟璁板綍绗? 160 鑺傚苟绂佹鏃у鍑哄緟琛ヨ娉曞洖娴併??
- `docs/admin-backend-blade-permission-final-checklist.md`
  - 淇绗? 24 鑺傚綋鍓嶇姸鎬佽鏄庛?乣admin_gift_export` 鏉冮檺钀界偣鍜屾暟鎹簱鎭㈠鍚庣殑澶嶆牳杈圭晫銆?
  - 杩藉姞鏈妭璁板綍褰撳墠鏍″噯璇佹嵁銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\AdminGiftModuleTest.php --filter test_gift_controller_comments_match_export_implementation` 棣栨澶辫触锛屽懡涓? `GiftController` 椤堕儴浠嶅寘鍚繃鏈熷鍑哄緟琛ヨ鏄庛??
- GREEN锛氬彧淇敼 `GiftController` 娉ㄩ噴鍜岄敊浣嶈鏄庯紝涓嶆敼绀煎搧瀵煎嚭銆佸彂鏀俱?佺墿娴佹洿鏂版垨閰嶇疆 CRUD 涓氬姟浠ｇ爜锛涗笓椤规祴璇曢?氳繃銆?
- RED锛歚vendor\bin\phpunit tests\Feature\AdminGiftModuleTest.php --filter test_final_checklist_records_current_gift_export_closure` 棣栨澶辫触锛屽懡涓渶缁堟竻鍗曠己灏戠 160 鑺傘??
- GREEN锛氳拷鍔犵 160 鑺傚苟淇绗? 24 鑺傚綋鍓嶇姸鎬佹弿杩板悗锛屼笓椤规祴璇曢?氳繃銆?

### 楠岃瘉鍛戒护
- `php -l app\Http\Controllers\Admin\GiftController.php`
- `php -l tests\Feature\AdminGiftModuleTest.php`
- `vendor\bin\phpunit tests\Feature\AdminGiftModuleTest.php --filter test_gift_controller_comments_match_export_implementation`
- `vendor\bin\phpunit tests\Feature\AdminGiftModuleTest.php --filter test_final_checklist_records_current_gift_export_closure`

### 褰撳墠璇佹嵁
- `routes/admin.php` 宸叉敞鍐? `POST /api/admin/exportGiftShipments`锛岃矾鐢卞悕 `admin_api_exportGiftShipments`銆?
- `database/migrations/2026_06_07_000011_add_admin_gift_permissions.php` 涓? `admin_gift_export` 鐨? `api_route` 宸叉寚鍚? `admin_api_exportGiftShipments`銆?
- `resources/admin/layui/gifts/index.blade.php` 宸叉彁渚? `id="exportGiftShipments"` 鎸夐挳骞剁粦瀹? `data-permission="admin_gift_export"`銆?
- `public/js/apps/naive-admin/front-plain.js` 宸查厤缃? `exportEndpoint: '/api/admin/exportGiftShipments'` 涓? `exportFileName: 'gift_shipments_export.csv'`銆?
- `app/Http/Controllers/CrmUi/Admin/PageController.php` 宸蹭负绀煎搧妯″潡閰嶇疆 `exportActions('admin_api_exportGiftShipments', 'gift_shipments_export.csv')`銆?
- `AdminGiftModuleTest::test_gift_shipment_export_endpoint_returns_current_filter_csv` 宸茶鐩栧綋鍓嶇瓫閫夊彂璐ц褰? CSV 鍝嶅簲銆?
- `AdminGiftModuleTest::test_gift_controller_comments_match_export_implementation` 宸茶鐩栨湰娆℃敞閲婂洖褰掕竟鐣屻??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩绀煎搧鍙戞斁銆佺墿娴佹洿鏂般?佺ぜ鍝侀厤缃? CRUD銆佸墠鍙板彲鍏戞崲绀煎搧鍒楄〃鎴栨暟鎹簱缁撴瀯銆?
- 鍏戞崲鎵ｅ簱瀛?/绉垎娑堣?楄仈鍔ㄤ粛鏈縼绉伙紱鍚庣画搴斿熀浜庣湡瀹炲厬鎹㈣鍒欍?佸簱瀛樻墸鍑忋?佺Н鍒嗘祦姘村拰澶辫触鍥炴粴琛ョ嫭绔嬮棴鐜祴璇曘??
- 褰撳墠鏈満 MySQL `127.0.0.1:3307` 浠嶄笉鍙繛鎺ワ紱鐪熷疄 DB 鎭㈠鍚庯紝闇?瑕佸啀杩愯瀹屾暣 `AdminGiftModuleTest` 鍜屾祻瑙堝櫒渚у鍑烘寜閽啋鐑熴??
## 161. 2026-07-07 鍦ㄧ嚎鐢ㄦ埛寮哄埗涓嬬嚎 JWT 澶辨晥闂幆

### 鏈澶勭悊鐩爣
- 淇鍚庡彴鍦ㄧ嚎鐢ㄦ埛寮哄埗涓嬬嚎鍙垹闄? `user_onlines` 璁板綍锛屼絾鏃у墠鍙? JWT 浠嶅彲鑳界户缁闂帴鍙ｇ殑闂銆?
- 鏄庣‘鍓嶅彴 JWT 鐨? `sub` 瀵瑰簲 `user_logins.id`锛岃?? `user_onlines.user_id` 鏄笟鍔＄敤鎴风紪鍙凤紝寮哄埗涓嬬嚎鏃跺繀椤诲厛鎸変笟鍔＄敤鎴风紪鍙锋壘鍒扮櫥褰曚富浣撳啀娓呯悊 SSO 鐘舵?併??
- 灏嗗綋鍓嶅彲钀藉湴鑼冨洿鍥哄畾涓烘暣璐﹀彿褰撳墠鍓嶅彴 JWT 澶辨晥锛涘崟璁惧涓嬬嚎銆佽澶囩淮搴﹀睍绀哄拰缂撳瓨/蹇冭烦绮剧粏鍙ｅ緞浠嶉渶缁х画杩佺Щ銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Middleware/SingleSignOn.php`
  - SSO 缂撳瓨缂哄け鎴? jti 涓嶅尮閰嶆椂缁熶竴杩斿洖 `response.sso_conflict` 鍜? `ResponseCode::SSO_CONFLICT`銆?
  - 涓嶅畬鏁? JWT payload 杩斿洖璁よ瘉澶辫触锛岄伩鍏嶇己灏? `guard`銆乣sub` 鎴? `jti` 鐨勮姹傝繘鍏ヤ笟鍔℃帶鍒跺櫒銆?
- `app/Http/Controllers/Admin/OnlineUserController.php`
  - 寮哄埗涓嬬嚎浜嬪姟鍐呭厛鍐欐搷浣滃璁★紝鍐嶆竻鐞嗗墠鍙? SSO 缂撳瓨鍜岀櫥褰曡〃 token 鏍囪瘑锛屾渶鍚庡垹闄ゅ湪绾胯褰曘??
  - 閫氳繃 `UserLogin::where('user_id', (int) $online->user_id)` 鎵惧埌 `user_logins.id`锛屾竻鐞? `sso:user:{login_id}` 骞舵竻绌? `user_logins.jwt_token_id`銆?
- `tests/Feature/AdminOnlineUserForceOfflineSessionInvalidationTest.php`
  - 鏂板 SSO 缂撳瓨缂哄け鎷掔粷鏃? token 鐨勮涓烘祴璇曘??
  - 鏂板鍚庡彴寮哄埗涓嬬嚎蹇呴』娓呯悊鍓嶅彴 SSO 鐘舵?佸拰鐧诲綍琛? token 鏍囪瘑鐨勯潤鎬佸绾︽祴璇曘??
  - 鏂板鏈?缁堟竻鍗曢棴鐜褰曟祴璇曘??
- `docs/admin-legacy-migration-gap-audit.md`
  - 鏇存柊 `UserLoginOnlineController` 褰撳墠璇佹嵁锛岄伩鍏嶇户缁妸鐪熷疄 JWT 澶辨晥鎻忚堪涓哄畬鍏ㄦ湭杩佺Щ銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\AdminOnlineUserForceOfflineSessionInvalidationTest.php --filter test_single_sign_on_rejects_token_when_active_jti_cache_is_missing` 棣栨澶辫触锛屾毚闇? `SingleSignOn` 鍦? `sso:user:{login_id}` 缂撳瓨缂哄け鏃朵粛浼氭斁琛屾棫 token銆?
- GREEN锛氫慨鏀? `SingleSignOn`锛岃姹傜紦瀛樼己澶辨垨 jti 涓嶅尮閰嶉兘鎸? SSO 鍐茬獊鎷掔粷銆?
- RED锛歚vendor\bin\phpunit tests\Feature\AdminOnlineUserForceOfflineSessionInvalidationTest.php --filter test_force_offline_controller_clears_front_user_sso_state` 棣栨澶辫触锛屾毚闇插己鍒朵笅绾挎帶鍒跺櫒鏈煡 `UserLogin`銆佹湭 `Cache::forget`銆佹湭娓呯┖ `user_logins.jwt_token_id`銆?
- GREEN锛氬己鍒朵笅绾挎寜 `user_onlines.user_id` 鎵惧埌 `user_logins.id`锛屾竻鐞? `sso:user:{login_id}` 骞舵竻绌? `user_logins.jwt_token_id`銆?
- RED锛歚vendor\bin\phpunit tests\Feature\AdminOnlineUserForceOfflineSessionInvalidationTest.php --filter test_final_checklist_records_online_user_force_offline_session_invalidation` 棣栨澶辫触锛屽懡涓渶缁堟竻鍗曠己灏戞湰鑺傘??
- GREEN锛氳拷鍔犳湰鑺傝褰曞綋鍓嶉棴鐜瘉鎹拰鍓╀綑杈圭晫銆?

### 褰撳墠璇佹嵁
- `SingleSignOn` 宸插湪 SSO 缂撳瓨缂哄け鏃舵嫆缁濇棫 JWT銆?
- `OnlineUserController::forceOffline()` 宸插湪鍒犻櫎鍦ㄧ嚎璁板綍鍓嶆竻鐞? `sso:user:{login_id}`銆?
- `OnlineUserController::forceOffline()` 宸叉竻绌? `user_logins.jwt_token_id`锛岄伩鍏嶇淮鎶よ?呰鍒よ璐﹀彿浠嶄繚鐣欏綋鍓嶆湁鏁? token銆?
- `AdminOnlineUserForceOfflineSessionInvalidationTest` 瑕嗙洊 SSO 缂撳瓨缂哄け鎷掔粷銆佹帶鍒跺櫒娓呯悊 SSO 鐘舵?佸拰鏈?缁堟竻鍗曡褰曘??

### 鍓╀綑杈圭晫
- `user_onlines` 褰撳墠浠嶆棤 session_id銆乨evice_id 鎴? token 缁村害瀛楁锛屾墍浠ユ湰杞笉鑳藉０鏄庡凡缁忔敮鎸佸崟璁惧涓嬬嚎銆?
- 鍗曡澶囦笅绾裤?佽澶囩淮搴﹀睍绀哄拰缂撳瓨/蹇冭烦绮剧粏鍙ｅ緞浠嶉渶缁х画杩佺Щ銆?
- 鏈満 MySQL `127.0.0.1:3307` 褰撳墠涓嶅彲杩炴帴锛屽畬鏁? DB 闂幆鎭㈠鍚庨渶瑕佸啀琛ョ湡瀹炴暟鎹簱鍦烘櫙涓嬬殑寮哄埗涓嬬嚎鎺ュ彛娴嬭瘯銆?
## 162. 2026-07-07 鍓嶅彴浠ｇ悊 parent_id 浣滅敤鍩熷厹搴曢棴鐜?

### 鏈澶勭悊鐩爣
- 琛ラ綈鍓嶅彴浠ｇ悊鍟嗘ā鍧楀湪鏃ф暟鎹縼绉诲満鏅笅鐨勫彲瑙佽寖鍥村厹搴曪細涓嶈兘鍙緷璧? `agent_descendants` 闂寘琛ㄣ??
- 褰撴棫椤圭洰瀵煎叆鏁版嵁鍙湁 `user_infos.parent_id` 鍏崇郴鏃讹紝鍓嶅彴浠ｇ悊涓嬬骇銆佺洿灞炲鎴枫?佽祫閲戞祦姘淬?佽繑浣ｅ拰鎸佷粨绛夊叡浜綔鐢ㄥ煙浠嶅簲鑳借瘑鍒綋鍓嶄唬鐞嗘爲銆?
- 淇濇寔鐜版湁 `agent_descendants` 涓轰紭鍏堟潵婧愶紝鍚屾椂鎶? `user_infos.parent_id` 閫掑綊缁撴灉鍚堝苟杩涘幓锛岄伩鍏嶅悓涓?浠ｇ悊鏍戣鍒欏湪澶氫釜鎺у埗鍣ㄩ噷閲嶅瀹炵幇銆?

### 鏈鍙樻洿鏂囦欢
- `app/Support/FrontLegacyData.php`
  - 灏? `FrontLegacyData::userScopeIds` 鎷嗘垚 `descendantScopeIds` 涓? `parentTreeScopeIds` 涓や釜鏉ユ簮銆?
  - `descendantScopeIds` 缁х画璇诲彇 `agent_descendants`锛屼繚鐣? `descendant_type` 鍜? `is_direct` 杩囨护銆?
  - `parentTreeScopeIds` 閫掑綊璇诲彇 `user_infos.parent_id`锛屽苟鎸? `account_type`銆佺洿灞?/闂存帴灞傜骇鍙ｅ緞琛ュ厖鍏滃簳 ID銆?
  - 鏈?缁堜綔鐢ㄥ煙閫氳繃 `array_merge($ids, $fallbackIds)` 鍚堝苟骞跺幓閲嶃??
- `app/Http/Controllers/Front/AgentController.php`
  - `canViewUser()` 鏀逛负澶嶇敤 `FrontLegacyData::userScopeIds($currentUserId, false)`锛岃鐢ㄦ埛璇︽儏銆佸眰绾ц矾寰勩?佺櫥褰曞巻鍙插拰瀹㈡埛缁勫埆鍙樻洿鍏变韩鍚屼竴濂椾唬鐞嗘爲杈圭晫銆?
  - 瀹㈡埛缁勫埆鍙樻洿鎻愪氦鏀逛负璋冪敤 `canViewUser()`锛岄伩鍏嶅彧鏌? `agent_descendants` 瀵艰嚧 parent_id 杩佺Щ鏁版嵁鏃犳硶鎻愪氦銆?
- `tests/Feature/FrontAgentScopeFallbackModuleTest.php`
  - 鏂板鍓嶅彴浠ｇ悊浣滅敤鍩熷厹搴曞绾︽祴璇曘??
  - 瑕嗙洊鍏变韩 helper 鍚堝苟 `agent_descendants` 涓? `user_infos.parent_id`锛屼互鍙婁唬鐞嗘帶鍒跺櫒鍙鎬у垽鏂鐢ㄥ叡浜綔鐢ㄥ煙銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentScopeFallbackModuleTest.php --filter test_front_legacy_data_scope_merges_descendant_table_with_parent_tree_fallback` 棣栨澶辫触锛屽懡涓? `FrontLegacyData::userScopeIds` 浠嶅彧璇诲彇 `agent_descendants`銆?
- GREEN锛氳ˉ鍏? `descendantScopeIds`銆乣parentTreeScopeIds` 鍜? `collectParentTreeScopeIds`锛岃鍏变韩浣滅敤鍩熷悓鏃跺悎骞堕棴鍖呰〃鍜? `user_infos.parent_id` 閫掑綊缁撴灉銆?
- GREEN锛歚vendor\bin\phpunit tests\Feature\FrontAgentScopeFallbackModuleTest.php --filter test_front_agent_visibility_uses_shared_scope_fallback` 閫氳繃锛岀‘璁? `AgentController::canViewUser()` 鍜岀粍鍒彉鏇存潈闄愬垽鏂凡澶嶇敤鍏变韩浣滅敤鍩熴??
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentScopeFallbackModuleTest.php --filter test_final_checklist_records_front_agent_parent_tree_fallback` 棣栨澶辫触锛屽懡涓渶缁堟竻鍗曠己灏戞湰鑺傘??
- GREEN锛氳拷鍔犳湰鑺傝褰曞綋鍓嶅墠鍙颁唬鐞嗕綔鐢ㄥ煙闂幆鍜屽墿浣欒竟鐣屻??

### 褰撳墠璇佹嵁
- `FrontLegacyData::userScopeIds` 宸蹭繚鐣? `agent_descendants` 鍙ｅ緞锛屽苟鏂板 `user_infos.parent_id` 閫掑綊鍏滃簳銆?
- `FrontLegacyData::userScopeIds` 瀵? `descendant_type` 涓? `directOnly` 鐨勮繃婊ゅ悓鏃朵綔鐢ㄤ簬闂寘琛ㄥ拰 parent_id 鍏滃簳缁撴灉銆?
- `AgentController::canViewUser()` 宸蹭娇鐢ㄥ叡浜綔鐢ㄥ煙锛岄伩鍏嶇敤鎴疯鎯呫?佺粍鍒彉鏇寸瓑鍏ュ彛缁х画鍙緷璧栧崟涓?鍏崇郴琛ㄣ??
- `FrontAgentScopeFallbackModuleTest` 瑕嗙洊鏈鍏变韩 helper 涓庝唬鐞嗘帶鍒跺櫒闈欐?佸绾︺??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁淇敼鏁版嵁搴撶粨鏋勶紝涔熸病鏈夐噸寤? `agent_descendants` 鎴? `family_tree` 鏁版嵁銆?
- 鐪熷疄鏁版嵁搴撴仮澶嶅悗浠嶉渶琛ュ厖浠ｇ悊涓嬬骇鍒楄〃銆佺洿灞炲鎴峰垪琛ㄣ?佽祫閲戞祦姘村拰鎸佷粨姹囨?荤殑鎺ュ彛绾у洖褰掋??
- 褰撳墠鏈満 MySQL `127.0.0.1:3307` 浠嶄笉鍙繛鎺ワ紝鏆備笉鑳借繍琛屼緷璧栫湡瀹炰唬鐞嗘爲鏁版嵁鐨勫畬鏁撮棴鐜祴璇曘??
## 163. 2026-07-07 鍓嶅彴鎸佷粨姹囨?? parent_id 浣滅敤鍩熷厹搴曢棴鐜?

### 鏈澶勭悊鐩爣
- 缁х画琛ラ綈绗? 162 鑺傜暀涓嬬殑鎸佷粨姹囨?昏竟鐣岋細鍓嶅彴澶囩敤 `FrontPositionSummaryController` 涓嶈兘缁х画鍙緷璧? `agent_descendants` 闂寘琛ㄣ??
- 褰撴棫椤圭洰瀵煎叆鏁版嵁鍙湁 `user_infos.parent_id` 鍏崇郴鏃讹紝鎸佷粨鐩村睘鑺傜偣銆佹眹鎬荤瓫閫夈?佷笅绾т唬鐞嗘悳绱㈠拰鐐瑰嚮鏄庣粏鏉冮檺涔熷簲澶嶇敤鍏变韩浠ｇ悊鏍戜綔鐢ㄥ煙銆?
- 淇濇寔 `FrontLegacyData::userScopeIds` 涓哄敮涓?鍓嶅彴鍏变韩浣滅敤鍩熷叆鍙ｏ紝閬垮厤璧勯噾娴佹按銆佷唬鐞嗘ā鍧楀拰鎸佷粨姹囨?诲悇鑷淮鎶や笉鍚岀殑浠ｇ悊鏍戣鍒欍??

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/PositionSummaryController.php`
  - 寮曞叆 `FrontLegacyData::userScopeIds`銆?
  - `index()` 鐨勭洿灞炶妭鐐规潵婧愭敼涓? `FrontLegacyData::userScopeIds($agentId, false, null, true)`銆?
  - 姣忎釜鐩村睘鑺傜偣鐨勬眹鎬昏寖鍥存敼涓? `FrontLegacyData::userScopeIds((int) $child->user_id, true)`銆?
  - `search()` 鐨勫叏閲忓彲瑙佽寖鍥存敼涓? `FrontLegacyData::userScopeIds($agentId, true)`銆?
  - `subSearch()` 鐨勪笅绾т唬鐞嗚寖鍥存敼涓? `FrontLegacyData::userScopeIds($agentId, false, 1)`銆?
  - `clickSearch()` 鐨勬槑缁嗘潈闄愬垽鏂敼涓? `in_array((int) $targetUserId, FrontLegacyData::userScopeIds($agentId, false), true)`銆?
- `tests/Feature/FrontPositionSummaryScopeFallbackModuleTest.php`
  - 鏂板鍓嶅彴鎸佷粨姹囨?讳綔鐢ㄥ煙鍏滃簳濂戠害娴嬭瘯銆?
  - 瑕嗙洊鎺у埗鍣ㄥ繀椤诲鐢ㄥ叡浜? helper锛屽苟绂佹鍥為??鍒? `AgentDescendant::where('agent_id', $agentId)` 鍗曚竴璺緞銆?
  - 鏂板鏈?缁堟竻鍗曢棴鐜褰曟祴璇曘??

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontPositionSummaryScopeFallbackModuleTest.php --filter test_front_position_summary_uses_shared_parent_tree_scope_fallback` 棣栨澶辫触锛屽懡涓? `PositionSummaryController` 鏈鍏? `FrontLegacyData`锛屼粛鐩存帴鏌ヨ `AgentDescendant`銆?
- RED锛歚vendor\bin\phpunit tests\Feature\FrontPositionSummaryScopeFallbackModuleTest.php --filter test_final_checklist_records_front_position_summary_scope_fallback` 棣栨澶辫触锛屽懡涓渶缁堟竻鍗曠己灏戠 163 鑺傘??
- GREEN锛氭帶鍒跺櫒鏀逛负澶嶇敤 `FrontLegacyData::userScopeIds`锛屽苟杩藉姞鏈妭璁板綍褰撳墠闂幆璇佹嵁鍜屽墿浣欒竟鐣屻??

### 褰撳墠璇佹嵁
- `FrontPositionSummaryController` 鐨勭洿灞炶妭鐐广?佸瓙鏍戞眹鎬汇?佸叏閲忔悳绱€?佷笅绾т唬鐞嗘悳绱㈠拰鐐瑰嚮鏄庣粏鏉冮檺宸插鐢? `FrontLegacyData::userScopeIds`銆?
- `FrontLegacyData::userScopeIds` 鍚屾椂鍚堝苟 `agent_descendants` 鍜? `user_infos.parent_id`锛屽洜姝ゅ鐢ㄦ寔浠撴眹鎬绘帶鍒跺櫒涔熻幏寰楁棫杩佺Щ鏁版嵁鍏滃簳銆?
- `FrontPositionSummaryScopeFallbackModuleTest` 瑕嗙洊鏈鎺у埗鍣ㄩ潤鎬佸绾﹀拰鏈?缁堟竻鍗曡褰曘??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁淇敼鐪熷疄鎸佷粨鑱氬悎 SQL銆佸垎椤电粨鏋勩?佽繑鍥炲瓧娈点?佹暟鎹簱缁撴瀯鎴栨棫鍓嶅彴璺敱銆?
- 褰撳墠鏈満 MySQL `127.0.0.1:3307` 浠嶄笉鍙繛鎺ワ紝鐪熷疄鏁版嵁搴撴仮澶嶅悗浠嶉渶鐢ㄤ唬鐞嗚处鍙烽獙璇佸鐢ㄦ寔浠撴眹鎬绘帴鍙ｇ殑鐪熷疄鏁版嵁闅旂銆佺洿灞炶妭鐐广?佷笅绾т唬鐞嗗拰鐐瑰嚮鏄庣粏銆?
## 164. 2026-07-07 鍓嶅彴涓绘寔浠撴帶鍒跺櫒 parent_id 浣滅敤鍩熷厹搴曢棴鐜?

### 鏈澶勭悊鐩爣
- 缁х画琛ラ綈鍓嶅彴涓绘寔浠? `Front\PositionController` 鐨勪唬鐞嗘爲杈圭晫锛岄伩鍏嶆牳蹇冩寔浠撴眹鎬诲叆鍙ｄ粛鍙鍙? `agent_descendants`銆?
- 褰撴棫椤圭洰瀵煎叆鏁版嵁鍙湁 `user_infos.parent_id` 鍏崇郴鏃讹紝鎸佷粨姹囨?婚捇鍙栥?佺洿灞炰唬鐞嗐?佺洿灞炰笅绾с?佹棫鎼滅储鑼冨洿鍜岀偣鍑绘槑缁嗘潈闄愪篃蹇呴』澶嶇敤鍏变韩浣滅敤鍩熴??
- 淇濇寔鐪熷疄浜ゆ槗鏌ヨ銆佹眹鎬诲瓧娈点?佸垎椤电粨鏋勫拰鏃у墠鍙拌矾鐢变笉鍙橈紝鍙粺涓?浠ｇ悊/瀹㈡埛鍙鑼冨洿鏉ユ簮銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/PositionController.php`
  - 鍒犻櫎 `use App\Models\AgentDescendant;`銆?
  - `resolveSummaryTargetId()` 鏀逛负 `in_array($targetId, FrontLegacyData::userScopeIds($agentId, false, 1), true)`锛屾牎楠岀洰鏍囦唬鐞嗘槸鍚﹀睘浜庡綋鍓嶄唬鐞嗘爲銆?
  - `directAgentIds()` 鏀逛负 `FrontLegacyData::userScopeIds($agentId, false, 1, true)`銆?
  - `directDescendantIds()` 鏀逛负 `FrontLegacyData::userScopeIds($agentId, false, null, true)`銆?
  - `search()` 鐨勮仛鍚堣寖鍥存敼涓? `FrontLegacyData::userScopeIds($agentId, true)`銆?
  - `positionDetail()` 鐨勬槑缁嗘潈闄愬垽鏂敼涓? `in_array((int) $targetUserId, FrontLegacyData::userScopeIds($agentId, false), true)`銆?
- `tests/Feature/FrontPositionScopeFallbackModuleTest.php`
  - 鏂板鍓嶅彴涓绘寔浠撴帶鍒跺櫒浣滅敤鍩熷厹搴曞绾︽祴璇曘??
  - 瑕嗙洊閽诲彇銆佺洿灞炰唬鐞嗐?佺洿灞炰笅绾с?佹棫鎼滅储銆佺偣鍑绘槑缁嗕簲鏉¤矾寰勫繀椤诲鐢ㄥ叡浜? helper锛屽苟绂佹 `AgentDescendant::where` 鍥炴祦銆?
  - 鏂板鏈?缁堟竻鍗曢棴鐜褰曟祴璇曘??

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontPositionScopeFallbackModuleTest.php --filter test_front_position_controller_uses_shared_parent_tree_scope_fallback` 棣栨澶辫触锛屽懡涓? `PositionController` 浠嶄繚鐣? `AgentDescendant::where` 鐩存帴鏌ヨ銆?
- RED锛歚vendor\bin\phpunit tests\Feature\FrontPositionScopeFallbackModuleTest.php --filter test_final_checklist_records_front_position_controller_scope_fallback` 棣栨澶辫触锛屽懡涓渶缁堟竻鍗曠己灏戠 164 鑺傘??
- GREEN锛氭帶鍒跺櫒缁熶竴澶嶇敤 `FrontLegacyData::userScopeIds`锛屽苟杩藉姞鏈妭璁板綍褰撳墠闂幆璇佹嵁鍜屽墿浣欒竟鐣屻??

### 褰撳墠璇佹嵁
- `Front\PositionController` 鐨勯捇鍙栫洰鏍囨牎楠屻?佺洿灞炰唬鐞嗐?佺洿灞炰笅绾с?佹棫鎼滅储鑼冨洿鍜岀偣鍑绘槑缁嗘潈闄愬凡澶嶇敤 `FrontLegacyData::userScopeIds`銆?
- `FrontLegacyData::userScopeIds` 宸插悎骞? `agent_descendants` 涓? `user_infos.parent_id`锛屽洜姝や富鎸佷粨鎺у埗鍣ㄤ篃鑳藉吋瀹规棫椤圭洰 parent_id 杩佺Щ鏁版嵁銆?
- `FrontPositionScopeFallbackModuleTest` 瑕嗙洊鏈鎺у埗鍣ㄩ潤鎬佸绾﹀拰鏈?缁堟竻鍗曡褰曘??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浜ゆ槗鑱氬悎 SQL銆丮T4 COMMENT 璇嗗埆銆佸搧绉嶅垎缁勩?佽繑浣ｆ眹鎬汇?佸垎椤电粨鏋勩?佽繑鍥炲瓧娈垫垨鏁版嵁搴撶粨鏋勩??
- 褰撳墠鏈満 MySQL `127.0.0.1:3307` 浠嶄笉鍙繛鎺ワ紝鐪熷疄鏁版嵁搴撴仮澶嶅悗浠嶉渶鐢ㄤ唬鐞嗚处鍙峰拰鏅?氬鎴疯处鍙烽獙璇? `/api/front/positions/summary`銆乣/api/front/positions/direct-agent-summaries`銆乣/api/front/positions/trades` 鍜屾棫 `user/position/*` 璺敱鐨勬暟鎹殧绂汇??
## 165. 2026-07-07 鍓嶅彴瀹㈡埛鍒楄〃 parent_id 浣滅敤鍩熷厹搴曢棴鐜?

### 鏈澶勭悊鐩爣
- 缁х画琛ラ綈鍓嶅彴瀹㈡埛鍏煎鎺у埗鍣ㄧ殑浠ｇ悊鏍戣竟鐣岋紝閬垮厤瀹㈡埛鍒楄〃鍜屽鎴风粺璁″彧渚濊禆 `agent_descendants`銆?
- 褰撴棫椤圭洰瀵煎叆鏁版嵁鍙湁 `user_infos.parent_id` 鍏崇郴鏃讹紝褰撳墠浠ｇ悊浠嶈兘鐪嬪埌鍏变韩浣滅敤鍩熷唴鐨勬櫘閫氬鎴凤紝骞剁户缁敮鎸佺洿灞炲鎴风瓫閫夊拰瀹㈡埛鍚嶇О绛涢?夈??
- 淇濇寔瀹㈡埛浜ゆ槗缁熻鍙ｅ緞涓嶅彉锛屽彧缁熶竴瀹㈡埛 ID 鑼冨洿鏉ユ簮銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/CustomerController.php`
  - 鍒犻櫎 `AgentDescendant` 鐩存帴渚濊禆銆?
  - 寮曞叆 `UserInfo` 涓? `FrontLegacyData`銆?
  - `myCustomers()` 鏀逛负閫氳繃 `FrontLegacyData::userScopeIds($agentId, false, 2, $directOnly ? true : null)` 鑾峰彇瀹㈡埛鑼冨洿锛屽啀浠? `user_infos` 璇诲彇瀹㈡埛璧勬枡銆?
  - `stats()` 鏀逛负閫氳繃 `FrontLegacyData::userScopeIds($agentId, false, 2)` 鑾峰彇瀹㈡埛缁熻鑼冨洿銆?
  - 鍒嗛〉璁板綍缁х画杩藉姞 `descendant_id`銆乣descendant_type`銆乣is_direct`銆乣descendant` 鍜? `trade_stats`锛屽吋瀹规棫瀹㈡埛鍏崇郴鍒楄〃甯歌瀛楁銆?
- `tests/Feature/FrontCustomerScopeFallbackModuleTest.php`
  - 鏂板鍓嶅彴瀹㈡埛鎺у埗鍣ㄤ綔鐢ㄥ煙鍏滃簳濂戠害娴嬭瘯銆?
  - 瑕嗙洊鎺у埗鍣ㄥ繀椤诲鐢? `FrontLegacyData::userScopeIds`锛屽苟绂佹 `AgentDescendant::where('agent_id', $agentId)` 鍥炴祦銆?
  - 鏂板鏈?缁堟竻鍗曢棴鐜褰曟祴璇曘??

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontCustomerScopeFallbackModuleTest.php --filter test_front_customer_controller_uses_shared_parent_tree_scope_fallback` 棣栨澶辫触锛屽懡涓? `CustomerController` 浠嶄繚鐣? `AgentDescendant` 鐩存帴鏌ヨ銆?
- RED锛歚vendor\bin\phpunit tests\Feature\FrontCustomerScopeFallbackModuleTest.php --filter test_final_checklist_records_front_customer_scope_fallback` 棣栨澶辫触锛屽懡涓渶缁堟竻鍗曠己灏戠 165 鑺傘??
- GREEN锛氭帶鍒跺櫒瀹㈡埛鍒楄〃涓庣粺璁＄粺涓?澶嶇敤 `FrontLegacyData::userScopeIds`锛屽苟杩藉姞鏈妭璁板綍褰撳墠闂幆璇佹嵁鍜屽墿浣欒竟鐣屻??

### 褰撳墠璇佹嵁
- `Front\CustomerController` 鐨勫鎴峰垪琛ㄥ拰瀹㈡埛缁熻宸插鐢? `FrontLegacyData::userScopeIds`銆?
- `FrontLegacyData::userScopeIds` 宸插悎骞? `agent_descendants` 涓? `user_infos.parent_id`锛屽洜姝ゅ墠鍙板鎴峰吋瀹规帶鍒跺櫒涔熻兘鍏煎鏃ч」鐩? parent_id 杩佺Щ鏁版嵁銆?
- `FrontCustomerScopeFallbackModuleTest` 瑕嗙洊鏈鎺у埗鍣ㄩ潤鎬佸绾﹀拰鏈?缁堟竻鍗曡褰曘??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鐪熷疄浜ゆ槗缁熻 SQL銆佸垎椤靛瓧娈靛悕銆佹棫鍓嶅彴璺敱缁戝畾銆佹暟鎹簱缁撴瀯鎴? `AgentController@customerList` 涓诲鎴峰垪琛ㄥ叆鍙ｃ??
- 褰撳墠鏈満 MySQL `127.0.0.1:3307` 浠嶄笉鍙繛鎺ワ紝鐪熷疄鏁版嵁搴撴仮澶嶅悗浠嶉渶鐢ㄤ唬鐞嗚处鍙烽獙璇? `CustomerController@myCustomers`銆乣CustomerController@stats` 浠ュ強涓诲鎴峰垪琛? `/api/front/agents/direct-customers` 鐨勬暟鎹殧绂汇??
## 166. 2026-07-07 鍓嶅彴棣栭〉 parent_id 浣滅敤鍩熷厹搴曢棴鐜?

### 鏈澶勭悊鐩爣
- 缁х画琛ラ綈鍓嶅彴棣栭〉浠ｇ悊鏍戣竟鐣岋紝閬垮厤棣栭〉鏈堝害鍏ラ噾銆佸嚭閲戙?佽鍗曡仛鍚堝拰浠ｇ悊/瀹㈡埛鏁伴噺缁熻鍙緷璧? `agent_descendants`銆?
- 褰撴棫椤圭洰瀵煎叆鏁版嵁鍙湁 `user_infos.parent_id` 鍏崇郴鏃讹紝浠ｇ悊棣栭〉浠嶈兘鎶婂綋鍓嶄唬鐞嗙綉缁滃唴鐨勭洿鎺?/闂存帴涓嬬骇绾冲叆缁熻銆?
- 淇濇寔棣栭〉杩斿洖瀛楁銆佺粺璁＄獥鍙ｃ?佹柊闂诲叕鍛娿?佷笅杞介厤缃拰娉ㄥ唽閾炬帴缁撴瀯涓嶅彉锛屽彧缁熶竴浠ｇ悊鏍戜綔鐢ㄥ煙鏉ユ簮銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/DashboardController.php`
  - 鍒犻櫎 `AgentDescendant` 鐩存帴渚濊禆銆?
  - 寮曞叆 `FrontLegacyData`銆?
  - `dashboardData()` 鐨? `$descendantIds` 鏀逛负閫氳繃 `FrontLegacyData::userScopeIds($userId, false)` 鑾峰彇锛岀户缁笌褰撳墠鐢ㄦ埛 ID 鍚堝苟涓? `$scopeUserIds` 鍚庣敤浜庢湀搴﹀叆閲戙?佸嚭閲戝拰璁㈠崟鑱氬悎銆?
- `app/Services/FamilyTreeService.php`
  - 寮曞叆 `FrontLegacyData`銆?
  - `FamilyTreeService::getSubAgentStats()` 鐨勭洿灞?/鍏ㄩ儴浠ｇ悊鍜岀洿灞?/鍏ㄩ儴瀹㈡埛鏁伴噺鏀逛负閫氳繃 `FrontLegacyData::userScopeIds` 缁熻銆?
  - 杩斿洖閿? `direct_agents`銆乣indirect_agents`銆乣total_agents`銆乣direct_customers`銆乣indirect_customers`銆乣total_customers` 淇濇寔涓嶅彉銆?
- `tests/Feature/FrontDashboardScopeFallbackModuleTest.php`
  - 鏂板鍓嶅彴棣栭〉浣滅敤鍩熷厹搴曞绾︽祴璇曘??
  - 瑕嗙洊鎺у埗鍣ㄦ湀搴﹁仛鍚堣寖鍥村拰棣栭〉浠ｇ悊/瀹㈡埛鏁伴噺缁熻蹇呴』澶嶇敤 `FrontLegacyData::userScopeIds`銆?
  - 鏂板鏈?缁堟竻鍗曢棴鐜褰曟祴璇曘??

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontDashboardScopeFallbackModuleTest.php --filter test_front_dashboard_uses_shared_parent_tree_scope_for_monthly_metrics` 棣栨澶辫触锛屽懡涓? `DashboardController` 浠嶄繚鐣? `AgentDescendant` 鐩存帴鏌ヨ銆?
- RED锛歚vendor\bin\phpunit tests\Feature\FrontDashboardScopeFallbackModuleTest.php --filter test_family_tree_dashboard_stats_use_shared_parent_tree_scope_fallback` 棣栨澶辫触锛屽懡涓? `FamilyTreeService::getSubAgentStats` 浠嶇洿鎺ユ寜 `agent_descendants` 璁℃暟銆?
- RED锛歚vendor\bin\phpunit tests\Feature\FrontDashboardScopeFallbackModuleTest.php --filter test_final_checklist_records_front_dashboard_scope_fallback` 棣栨澶辫触锛屽懡涓渶缁堟竻鍗曠己灏戠 166 鑺傘??
- GREEN锛氶椤垫湀搴﹁仛鍚堣寖鍥村拰灞傜骇缁熻缁熶竴澶嶇敤 `FrontLegacyData::userScopeIds`锛屽苟杩藉姞鏈妭璁板綍褰撳墠闂幆璇佹嵁鍜屽墿浣欒竟鐣屻??

### 褰撳墠璇佹嵁
- `Front\DashboardController` 鐨勯椤垫湀搴﹁祫閲戝拰璁㈠崟缁熻鑼冨洿宸插鐢? `FrontLegacyData::userScopeIds`銆?
- `FamilyTreeService::getSubAgentStats` 鐨勪唬鐞?/瀹㈡埛鏁伴噺缁熻宸插鐢? `FrontLegacyData::userScopeIds`銆?
- `FrontLegacyData::userScopeIds` 宸插悎骞? `agent_descendants` 涓? `user_infos.parent_id`锛屽洜姝ゅ墠鍙伴椤典篃鑳藉吋瀹规棫椤圭洰 parent_id 杩佺Щ鏁版嵁銆?
- `FrontDashboardScopeFallbackModuleTest` 瑕嗙洊鏈鎺у埗鍣ㄣ?佹湇鍔＄粺璁″拰鏈?缁堟竻鍗曡褰曘??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩棣栭〉鏂伴椈銆佸璇█銆佷笅杞介厤缃?佹敞鍐岄摼鎺ャ?佽繑浣ｉ噾棰濈粺璁°?佹暟鎹簱缁撴瀯鎴栫湡瀹炰氦鏄撹仛鍚? SQL銆?
- `FamilyTreeService` 鍏朵粬缃戠粶鏍戙?佸洟闃熺粺璁″拰閲嶅缓鑳藉姏浠嶄繚鐣欏師鏈夐棴鍖呰〃鑱岃矗锛涘悗缁簲鎸夊叿浣撳叆鍙ｇ户缁ˉ鐙珛闂幆銆?
- 褰撳墠鏈満 MySQL `127.0.0.1:3307` 浠嶄笉鍙繛鎺ワ紝鐪熷疄鏁版嵁搴撴仮澶嶅悗浠嶉渶鐢ㄤ唬鐞嗚处鍙烽獙璇? `/api/front/dashboard` 棣栭〉缁熻鍗＄墖鐨勬暟鎹殧绂诲拰 parent_id 杩佺Щ鏁版嵁鍏煎銆?
## 167. 2026-07-07 鍓嶅彴杩斾剑杞处 parent_id 浣滅敤鍩熷厹搴曢棴鐜?

### 鏈澶勭悊鐩爣
- 琛ラ綈鍓嶅彴杩斾剑杞处鐨勪唬鐞嗘爲杈圭晫锛岄伩鍏嶄笅鎷夐?夐」鍜屾彁浜ゆ牎楠屽彧渚濊禆 `agent_descendants`銆?
- 褰撴棫椤圭洰瀵煎叆鏁版嵁鍙湁 `user_infos.parent_id` 鍏崇郴鏃讹紝褰撳墠浠ｇ悊浠嶅彧鑳藉悜鐩村睘涓嬬骇浠ｇ悊杞处锛屽苟鑳芥甯哥湅鍒扮洿灞炰唬鐞嗗悕绉板拰绛夌骇銆?
- 淇濇寔杞处娴佹按銆佷綑棰濇墸澧炪?佽繑鍥炲瓧娈点?佸墠绔矾鐢卞拰鏃у墠鍙拌〃鍗曠粨鏋勪笉鍙橈紝鍙粺涓?鐩村睘浠ｇ悊浣滅敤鍩熸潵婧愩??

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/CommissionController.php`
  - 鍒犻櫎 `AgentDescendant` 鐩存帴渚濊禆銆?
  - `transferAgentOptions` 鏀逛负閫氳繃 `FrontLegacyData::userScopeIds($agentId, false, 1, true)` 鑾峰彇鐩村睘浠ｇ悊 ID锛屽啀浠? `user_infos` 璇诲彇涓嬫媺灞曠ず璧勬枡銆?
  - 閫夐」缁х画杩斿洖 `value`銆乣label`銆乣user_id`銆乣user_name` 鍜? `agent_level_name`锛屽吋瀹? Layui 涓? Naive 鍓嶇鍔ㄦ?侀?夐」銆?
  - `transfer()` 鐨勭洿灞炰笅绾т唬鐞嗘牎楠屾敼涓哄鐢ㄥ悓涓?缁勫叡浜綔鐢ㄥ煙 ID锛岄伩鍏嶄笅鎷夊拰鎻愪氦浣跨敤涓ゅ浠ｇ悊鏍戣鍒欍??
- `tests/Feature/FrontCommissionScopeFallbackModuleTest.php`
  - 鏂板鍓嶅彴杩斾剑杞处浣滅敤鍩熷厹搴曞绾︽祴璇曘??
  - 瑕嗙洊涓嬫媺閫夐」鍜屾彁浜ゆ牎楠屽繀椤诲鐢? `FrontLegacyData::userScopeIds`锛屽苟绂佹 `AgentDescendant::where('agent_id', $agentId)` 鍥炴祦銆?
  - 鏂板鏈?缁堟竻鍗曢棴鐜褰曟祴璇曘??
- `tests/Feature/FrontUiRegressionTest.php`
  - 鍚屾杩斾剑杞处鍔ㄦ?佷笅鎷夐潤鎬佹柇瑷?锛岀户缁害鏉熷墠绔矾鐢便?佸姩鎬侀?夐」瀛楁鍜屾爣绛剧粍鎴愶紝鍚屾椂鎺ュ彈鍏变韩浣滅敤鍩熷疄鐜般??

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontCommissionScopeFallbackModuleTest.php --filter test_front_commission_transfer_uses_shared_parent_tree_scope_fallback` 棣栨澶辫触锛屽懡涓? `CommissionController` 浠嶄繚鐣? `AgentDescendant` import 鍜岀洿鎺ユ煡璇€??
- RED锛歚vendor\bin\phpunit tests\Feature\FrontCommissionScopeFallbackModuleTest.php --filter test_final_checklist_records_front_commission_transfer_scope_fallback` 棣栨澶辫触锛屽懡涓渶缁堟竻鍗曠己灏戠 167 鑺傘??
- GREEN锛氳繑浣ｈ浆璐︿笅鎷夐?夐」鍜屾彁浜ゆ牎楠岀粺涓?澶嶇敤 `FrontLegacyData::userScopeIds`锛屽苟杩藉姞鏈妭璁板綍褰撳墠闂幆璇佹嵁鍜屽墿浣欒竟鐣屻??

### 褰撳墠璇佹嵁
- `Front\CommissionController::transferAgentOptions` 宸查?氳繃 `FrontLegacyData::userScopeIds($agentId, false, 1, true)` 鑾峰彇鐩村睘浠ｇ悊浣滅敤鍩熴??
- 涓嬫媺灞曠ず璧勬枡宸蹭粠 `UserInfo::with('level')` 璇诲彇锛屽苟淇濈暀 `value`銆乣label`銆乣user_id`銆乣user_name`銆乣agent_level_name` 瀛楁銆?
- `Front\CommissionController::transfer` 宸茬敤鍚屼竴缁勭洿灞炰唬鐞? ID 鍋? `sub_agent_id` 鏉冮檺鏍￠獙銆?
- `FrontCommissionScopeFallbackModuleTest` 瑕嗙洊鏈鎺у埗鍣ㄩ潤鎬佸绾﹀拰鏈?缁堟竻鍗曡褰曘??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浣ｉ噾杞处浜嬪姟銆佷綑棰濇墸澧炪?乣commission_records` 鍐欏叆瀛楁銆佸墠绔〃鍗曠粨鏋勩?佹暟鎹簱缁撴瀯鎴栫湡瀹炰剑閲戠粺璁″彛寰勩??
- 褰撳墠鏈満 MySQL `127.0.0.1:3307` 浠嶄笉鍙繛鎺ワ紝鐪熷疄鏁版嵁搴撴仮澶嶅悗浠嶉渶鐢ㄤ唬鐞嗚处鍙烽獙璇? `/api/front/commissions/transfer-agent-options` 鍜? `/api/front/commissions/transfers` 鐨勭湡瀹炴暟鎹殧绂讳笌杞处闂幆銆?
- 鎺у埗鍣ㄦ爣璁帮細`Front\CommissionController`

## 168. 2026-07-07 鍓嶅彴澶т唬鐞? parent_id 浣滅敤鍩熷厹搴曢棴鐜?

### 鏈澶勭悊鐩爣
- 琛ラ綈鍓嶅彴澶т唬鐞嗘棫鍏ュ彛鐨勪唬鐞嗘爲杈圭晫锛岄伩鍏嶅ぇ浠ｇ悊浠ｇ悊缃戠粶鍜岃鍗曞鎴疯寖鍥村彧渚濊禆 `agent_descendants`銆?
- 褰撴棫椤圭洰瀵煎叆鏁版嵁鍙湁 `user_infos.parent_id` 鍏崇郴鏃讹紝`big_agents.sub_agent_ids` 鎸囧畾鐨勭洿灞炰唬鐞嗕粛鑳藉睍寮?鍏朵笅绾т唬鐞嗙綉缁滃拰瀹㈡埛璁㈠崟鑼冨洿銆?
- 淇濇寔澶т唬鐞嗙櫥褰曘?佹棫鍓嶅彴椤甸潰銆佸垪琛ㄨ繑鍥炵粨鏋勩?佽鍗曠姸鎬? scope銆佸垎椤靛瓧娈靛拰缁熻 footer 涓嶅彉锛屽彧缁熶竴浠ｇ悊/瀹㈡埛 ID 浣滅敤鍩熸潵婧愩??

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/BigNumberController.php`锛坄Front\BigNumberController`锛?
  - `subAgentIdsForRequest` 鍦ㄩ渶瑕佸寘鍚笅绾т唬鐞嗙綉缁滄椂锛屾敼涓哄 `big_agents.sub_agent_ids` 涓殑鐩村睘浠ｇ悊閫愪釜璋冪敤 `FrontLegacyData::userScopeIds($subAgentId, false, 1)`銆?
  - `legacyOrderListResponse` 鐨勫鎴疯鍗曡寖鍥存敼涓哄鍙浠ｇ悊 ID 璋冪敤 `FrontLegacyData::userScopeIds($agentId, false, 2)`銆?
  - 鍒犻櫎澶т唬鐞嗘棫鍏ュ彛涓袱澶? `\App\Models\AgentDescendant::whereIn` 鐩存帴鏌ヨ锛岃闂寘琛ㄥ拰 `user_infos.parent_id` 鍏滃簳缁熶竴璧板叡浜? helper銆?
- `tests/Feature/FrontBigNumberScopeFallbackModuleTest.php`
  - 鏂板鍓嶅彴澶т唬鐞嗕綔鐢ㄥ煙鍏滃簳濂戠害娴嬭瘯銆?
  - 瑕嗙洊浠ｇ悊缃戠粶灞曞紑鍜岃鍗曞鎴疯寖鍥村繀椤诲鐢? `FrontLegacyData::userScopeIds`锛屽苟绂佹澶т唬鐞嗘帶鍒跺櫒鍥為??鍒? `AgentDescendant::whereIn`銆?
  - 鏂板鏈?缁堟竻鍗曢棴鐜褰曟祴璇曘??

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontBigNumberScopeFallbackModuleTest.php --filter test_front_big_number_controller_uses_shared_parent_tree_scope_fallback` 棣栨澶辫触锛屽懡涓? `BigNumberController::subAgentIdsForRequest` 浠嶇洿鎺ユ煡璇? `AgentDescendant::whereIn`銆?
- RED锛歚vendor\bin\phpunit tests\Feature\FrontBigNumberScopeFallbackModuleTest.php --filter test_final_checklist_records_front_big_number_scope_fallback` 棣栨澶辫触锛屽懡涓渶缁堟竻鍗曠己灏戠 168 鑺傘??
- GREEN锛氬ぇ浠ｇ悊浠ｇ悊缃戠粶灞曞紑鍜岃鍗曞鎴疯寖鍥寸粺涓?澶嶇敤 `FrontLegacyData::userScopeIds`锛屽苟杩藉姞鏈妭璁板綍褰撳墠闂幆璇佹嵁鍜屽墿浣欒竟鐣屻??

### 褰撳墠璇佹嵁
- `Front\BigNumberController::subAgentIdsForRequest` 宸查?氳繃 `FrontLegacyData::userScopeIds($subAgentId, false, 1)` 灞曞紑鐩村睘浠ｇ悊鐨勪笅绾т唬鐞嗙綉缁溿??
- `Front\BigNumberController::legacyOrderListResponse` 宸查?氳繃 `FrontLegacyData::userScopeIds($agentId, false, 2)` 鑾峰彇鍙瀹㈡埛璁㈠崟鑼冨洿銆?
- `big_agents.sub_agent_ids` 浠嶆槸澶т唬鐞嗗彲瑙佺洿灞炰唬鐞嗙殑鍏ュ彛閰嶇疆锛岃寖鍥村睍寮?缁熶竴鍏煎 `agent_descendants` 鍜? `user_infos.parent_id`銆?
- `FrontBigNumberScopeFallbackModuleTest` 瑕嗙洊鏈鎺у埗鍣ㄩ潤鎬佸绾﹀拰鏈?缁堟竻鍗曡褰曘??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩澶т唬鐞嗙櫥褰曘?佸瘑鐮佷慨鏀广?佸垪琛ㄨ繑鍥炵粨鏋勩?佺湡瀹炶鍗曡仛鍚堛?乣UserTrade::open()` / `UserTrade::closed()` 鐘舵?? scope銆佹暟鎹簱缁撴瀯鎴? `big_agents.sub_agent_ids` 閰嶇疆鏉ユ簮銆?
- 褰撳墠鏈満 MySQL `127.0.0.1:3307` 浠嶄笉鍙繛鎺ワ紝鐪熷疄鏁版嵁搴撴仮澶嶅悗浠嶉渶鐢ㄥぇ浠ｇ悊璐﹀彿楠岃瘉鏃? `/user/agents/*` 浠ｇ悊鍒楄〃銆佹寔浠撴眹鎬汇?佹湭骞充粨璁㈠崟鍜屽凡骞充粨璁㈠崟鐨勬暟鎹殧绂汇??
## 169. 2026-07-07 鍓嶅彴浠ｇ悊/瀹㈡埛涓诲垪琛? parent_id 浣滅敤鍩熷厹搴曢棴鐜?

### 鏈澶勭悊鐩爣
- 琛ラ綈 `Front\AgentController` 涓讳唬鐞嗗垪琛ㄥ拰涓诲鎴峰垪琛ㄧ殑浠ｇ悊鏍戣竟鐣岋紝閬垮厤 `/api/front/agents/direct` 涓? `/api/front/agents/direct-customers` 鍙緷璧? `agent_descendants`銆?
- 褰撴棫椤圭洰瀵煎叆鏁版嵁鍙湁 `user_infos.parent_id` 鍏崇郴鏃讹紝褰撳墠浠ｇ悊浠嶈兘灞曞紑涓嬬骇浠ｇ悊銆佺洿灞炰唬鐞嗐?佸鎴峰垪琛ㄥ拰鐩村睘瀹㈡埛绛涢?夈??
- 淇濇寔鏃у墠鍙板瓧娈? `depth`銆乣is_direct`銆乣descendant`銆乣can_drill_agents`銆乣can_drill_customers`銆乣comm_trans`銆乣change_group`銆乣available_groups` 涓庡垎椤?/姹囨?荤粨鏋勪笉鍙樸??

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/AgentController.php`锛坄Front\AgentController`锛?
  - `subList` 鏀逛负閫氳繃 `FrontLegacyData::userScopeIds($queryAgentId, false, 1, $directOnly ? true : null)` 鑾峰彇浠ｇ悊鑼冨洿锛屽啀浠? `user_infos` 璇诲彇浠ｇ悊璧勬枡銆?
  - `customerList` 鏀逛负閫氳繃 `FrontLegacyData::userScopeIds($queryAgentId, false, 2, $directOnly ? true : null)` 鑾峰彇瀹㈡埛鑼冨洿锛屽啀浠? `user_infos` 璇诲彇瀹㈡埛璧勬枡銆?
  - `scopeDepth` 鐢? `user_infos.family_tree` 鎺ㄥ鏃у瓧娈? `depth`锛岀己閾捐矾鏃堕潪鐩村睘鎸? 2 鍏滃簳锛岄伩鍏嶄负浜嗗睍绀哄瓧娈电户缁緷璧栭棴鍖呰〃銆?
  - 涓や釜涓诲垪琛ㄧ户缁繚鐣欐棫鍓嶅彴鍜? Naive 渚濊禆鐨勫吋瀹瑰瓧娈点?佺粺璁″瓧娈点?佸鎴风粍鍒?夐」鍜屾眹鎬? footer銆?
- `tests/Feature/FrontAgentMainListScopeFallbackModuleTest.php`
  - 鏂板鍓嶅彴浠ｇ悊/瀹㈡埛涓诲垪琛ㄤ綔鐢ㄥ煙鍏滃簳濂戠害娴嬭瘯銆?
  - 瑕嗙洊 `subList` 涓? `customerList` 蹇呴』澶嶇敤 `FrontLegacyData::userScopeIds`锛屽苟绂佹杩欎袱涓柟娉曞洖閫?鍒? `AgentDescendant::query()`銆?
  - 鏂板鏈?缁堟竻鍗曢棴鐜褰曟祴璇曘??

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentMainListScopeFallbackModuleTest.php --filter test_front_agent_main_lists_use_shared_parent_tree_scope_fallback` 棣栨澶辫触锛屽懡涓? `subList` 鍜? `customerList` 浠嶇洿鎺ヤ娇鐢? `AgentDescendant::query()`銆?
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentMainListScopeFallbackModuleTest.php --filter test_final_checklist_records_front_agent_main_list_scope_fallback` 棣栨澶辫触锛屽懡涓渶缁堟竻鍗曠己灏戠 169 鑺傘??
- GREEN锛氫唬鐞?/瀹㈡埛涓诲垪琛ㄧ粺涓?澶嶇敤 `FrontLegacyData::userScopeIds`锛屽苟杩藉姞鏈妭璁板綍褰撳墠闂幆璇佹嵁鍜屽墿浣欒竟鐣屻??

### 褰撳墠璇佹嵁
- `Front\AgentController::subList` 宸查?氳繃 `FrontLegacyData::userScopeIds` 鍚屾椂鍏煎 `agent_descendants` 鍜? `user_infos.parent_id` 鐨勪笅绾т唬鐞嗚寖鍥淬??
- `Front\AgentController::customerList` 宸查?氳繃 `FrontLegacyData::userScopeIds` 鍚屾椂鍏煎 `agent_descendants` 鍜? `user_infos.parent_id` 鐨勫鎴疯寖鍥淬??
- `scopeDepth` 淇濈暀鏃у垪琛? `depth` 瀛楁锛宍is_direct` 鐢? `user_infos.parent_id` 鍒ゅ畾锛岄伩鍏嶄富鍒楄〃涓哄睍绀哄瓧娈电户缁粦瀹氬崟涓?鍏崇郴琛ㄣ??
- `FrontAgentMainListScopeFallbackModuleTest` 瑕嗙洊鏈鎺у埗鍣ㄩ潤鎬佸绾﹀拰鏈?缁堟竻鍗曡褰曘??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鍓嶅彴绛夌骇纭銆佹棫浣ｉ噾杞处銆佸眰绾ц矾寰勩?佺櫥褰曞巻鍙层?佽鎯呭脊灞傘?佹暟鎹簱缁撴瀯鎴? `FamilyTreeService` 閲嶅缓闂寘琛ㄨ兘鍔涖??
- 褰撳墠鏈満 MySQL `127.0.0.1:3307` 浠嶄笉鍙繛鎺ワ紝鐪熷疄鏁版嵁搴撴仮澶嶅悗浠嶉渶鐢ㄤ唬鐞嗚处鍙烽獙璇? `/api/front/agents/direct`銆乣/api/front/agents/direct-customers` 鍜屾棫 `user/proxy/*`銆乣user/cust/*` 璺敱鐨勬暟鎹殧绂讳笌鐐瑰嚮閽诲彇銆?
## 170. 2026-07-08 鍓嶅彴浠ｇ悊绛夌骇纭 parent_id 浣滅敤鍩熷厹搴曢棴鐜?

### 鏈澶勭悊鐩爣
- 琛ラ綈 `Front\AgentController` 绛夌骇纭鍒楄〃鍜岀‘璁ゆ彁浜ょ殑鐩村睘浠ｇ悊杈圭晫锛岄伩鍏? `confirmLevel` 涓? `confirmLevelChange` 鍦? `agent_descendants` 瀛樺湪閮ㄥ垎鏃ф暟鎹椂閬斀 `user_infos.parent_id` 杩佺Щ鍏崇郴銆?
- 褰撴棫椤圭洰瀵煎叆鏁版嵁鍙湁 `user_infos.parent_id` 鍏崇郴鎴栭棴鍖呰〃涓嶅畬鏁存椂锛屽綋鍓嶄唬鐞嗕粛鑳界湅鍒板苟纭鐪熷疄鐩村睘涓嬬骇浠ｇ悊绛夌骇銆?
- 淇濇寔绛夌骇鍊欓?夈?佽繑浣ｆ瘮渚嬭绠椼?佹彁浜ゅ瓧娈点?佹棫鍓嶅彴璺敱鍜? Naive/Layui 鍓嶇缁撴瀯涓嶅彉锛屽彧缁熶竴鐩村睘浠ｇ悊 ID 鏉ユ簮銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/AgentController.php`锛坄Front\AgentController`锛?
  - `confirmLevel` 鏀逛负閫氳繃 `FrontLegacyData::userScopeIds((int) $userInfo->user_id, false, 1, true)` 鑾峰彇鐩村睘浠ｇ悊鑼冨洿銆?
  - `confirmLevelChange` 鏀逛负閫氳繃 `FrontLegacyData::userScopeIds($agentId, false, 1, true)` 鏍￠獙寰呯‘璁や唬鐞嗭紝閬垮厤闂寘琛ㄥ拰 parent_id 浣跨敤涓ゅ瑙勫垯銆?
  - 缁х画浠? `agent_levels.user_commission` 璁＄畻纭鍚庣殑鐪熷疄杩斾剑姣斾緥锛屼笉淇′换鍓嶇鎻愪氦鐨? `comm_prop`銆?
- `tests/Feature/FrontAgentLevelConfirmationScopeFallbackModuleTest.php`
  - 鏂板鍓嶅彴浠ｇ悊绛夌骇纭浣滅敤鍩熷厹搴曞绾︽祴璇曘??
  - 瑕嗙洊 `confirmLevel` 涓? `confirmLevelChange` 蹇呴』澶嶇敤 `FrontLegacyData::userScopeIds`锛屽苟绂佹杩欎袱涓柟娉曞洖閫?鍒板崟鐙煡璇? `AgentDescendant` 鎴? `user_infos.parent_id`銆?
  - 鏂板鏈?缁堟竻鍗曢棴鐜褰曟祴璇曘??

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentLevelConfirmationScopeFallbackModuleTest.php` 棣栨澶辫触锛屽懡涓? `confirmLevel` 浠嶇洿鎺ユ煡璇? `AgentDescendant` 涓旀竻鍗曠己灏戠 170 鑺傘??
- GREEN锛氱瓑绾х‘璁ゅ垪琛ㄥ拰鎻愪氦鏍￠獙缁熶竴澶嶇敤 `FrontLegacyData::userScopeIds`锛屽苟杩藉姞鏈妭璁板綍褰撳墠闂幆璇佹嵁鍜屽墿浣欒竟鐣屻??

### 褰撳墠璇佹嵁
- `Front\AgentController::confirmLevel` 宸查?氳繃 `FrontLegacyData::userScopeIds` 鍚屾椂鍏煎 `agent_descendants` 鍜? `user_infos.parent_id` 鐨勭洿灞炰笅绾т唬鐞嗚寖鍥淬??
- `Front\AgentController::confirmLevelChange` 宸茬敤鍚屼竴缁勭洿灞炰唬鐞? ID 鍋? `userId` 鏉冮檺鏍￠獙銆?
- `FrontAgentLevelConfirmationScopeFallbackModuleTest` 瑕嗙洊鏈鎺у埗鍣ㄩ潤鎬佸绾﹀拰鏈?缁堟竻鍗曡褰曘??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩绛夌骇鍊欓?夊垪琛ㄣ?佺瓑绾х‘璁ゅ啓鍏ュ瓧娈点?佽繑浣ｆ瘮渚嬭绠楁潵婧愩?佸墠绔彁浜ょ粨鏋勩?佹暟鎹簱缁撴瀯鎴? `FamilyTreeService` 閲嶅缓闂寘琛ㄨ兘鍔涖??
- 褰撳墠鏈満 MySQL `127.0.0.1:3307` 浠嶄笉鍙繛鎺ワ紝鐪熷疄鏁版嵁搴撴仮澶嶅悗浠嶉渶鐢ㄤ唬鐞嗚处鍙烽獙璇? `/api/front/agents/level-confirmation`銆乣/api/front/agents/level-confirmation/changes` 鍜屾棫 `user/proxy/proxyConfirmSearch`銆乣user/proxy/confirmLevelChange` 鐨勭湡瀹炴暟鎹殧绂讳笌纭鎻愪氦闂幆銆?
## 171. 2026-07-08 鍓嶅彴鏃т剑閲戣浆璐︾洰鏍囨牎楠? parent_id 浣滅敤鍩熷厹搴曢棴鐜?

### 鏈澶勭悊鐩爣
- 琛ラ綈 `Front\AgentController` 鏃у墠鍙扮洿灞炵敤鎴蜂剑閲戣浆璐︾洰鏍囨牎楠岋紝閬垮厤 `directUserCommTrans` 浠嶉?氳繃鐙珛鐨? `AgentDescendant` 鏌ヨ鍜? `user_infos.parent_id` 鏌ヨ缁存姢绗簩濂楃洿灞炲叧绯昏鍒欍??
- 褰撴棫椤圭洰瀵煎叆鏁版嵁鍙湁 `user_infos.parent_id` 鍏崇郴鎴? `agent_descendants` 涓嶅畬鏁存椂锛屾棫鍓嶅彴浣ｉ噾杞处浠嶅彧鍏佽褰撳墠浠ｇ悊鍚戠湡瀹炵洿灞炰笅绾ц浆璐︺??
- 淇濇寔杞处閲戦鏍￠獙銆佸瘑鐮佹牎楠屻?佷綑棰濇墸澧炪?乣commission_records` 鍐欏叆瀛楁鍜屾棫鍓嶅彴鍝嶅簲缁撴瀯涓嶅彉锛屽彧缁熶竴鐩爣鐢ㄦ埛鏉冮檺杈圭晫銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/AgentController.php`锛坄Front\AgentController`锛?
  - 鍒犻櫎 `AgentDescendant` import銆?
  - `isDirectTransferTarget` 鏀逛负閫氳繃 `FrontLegacyData::userScopeIds($agentId, false, null, true)` 鍒ゆ柇鐩爣鐢ㄦ埛鏄惁涓虹洿灞炰笅绾с??
  - `directUserCommTrans` 缁х画澶嶇敤 `isDirectTransferTarget`锛屼繚鎸佸師鏈夎浆璐︿簨鍔″拰鍝嶅簲鏍煎紡銆?
- `tests/Feature/FrontAgentDirectTransferScopeFallbackModuleTest.php`
  - 鏂板鍓嶅彴鏃т剑閲戣浆璐︾洰鏍囨牎楠屼綔鐢ㄥ煙鍏滃簳濂戠害娴嬭瘯銆?
  - 瑕嗙洊 `AgentController` 涓嶅啀 import `AgentDescendant`锛屼笖 `isDirectTransferTarget` 蹇呴』澶嶇敤 `FrontLegacyData::userScopeIds`銆?
  - 鏂板鏈?缁堟竻鍗曢棴鐜褰曟祴璇曘??

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentDirectTransferScopeFallbackModuleTest.php` 棣栨澶辫触锛屽懡涓? `AgentController` 浠? import `AgentDescendant` 涓旀竻鍗曠己灏戠 171 鑺傘??
- GREEN锛氭棫浣ｉ噾杞处鐩爣鏍￠獙缁熶竴澶嶇敤 `FrontLegacyData::userScopeIds`锛屽苟杩藉姞鏈妭璁板綍褰撳墠闂幆璇佹嵁鍜屽墿浣欒竟鐣屻??

### 褰撳墠璇佹嵁
- `Front\AgentController::isDirectTransferTarget` 宸查?氳繃 `FrontLegacyData::userScopeIds($agentId, false, null, true)` 鍚屾椂鍏煎 `agent_descendants` 鍜? `user_infos.parent_id` 鐨勭洿灞炰笅绾ц寖鍥淬??
- `Front\AgentController::directUserCommTrans` 淇濇寔璋冪敤 `isDirectTransferTarget` 浣滀负鐩爣鐢ㄦ埛鏉冮檺闂ㄧ銆?
- `FrontAgentDirectTransferScopeFallbackModuleTest` 瑕嗙洊鏈鎺у埗鍣ㄩ潤鎬佸绾﹀拰鏈?缁堟竻鍗曡褰曘??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鏃т剑閲戣浆璐︿簨鍔°?佷綑棰濇墸澧炪?佸瘑鐮佹牎楠屻?乣commission_records` 鍐欏叆瀛楁銆佸墠绔〃鍗曠粨鏋勬垨鏁版嵁搴撶粨鏋勩??
- 褰撳墠 MySQL `127.0.0.1:3307` 宸叉仮澶嶈繛閫氾紱鏈妭浠嶉渶缁撳悎鐪熷疄浠ｇ悊璐﹀彿缁х画鎵╁睍鏃? `user/proxy/directUserCommTrans` 鎴愬姛/鎷掔粷鍐欏叆绾ч棴鐜祴璇曘??
## 172. 2026-07-09 鍓嶅彴杩斾剑璁＄畻鏈嶅姟 parent_id 浣滅敤鍩熷厹搴曢棴鐜?

### 鏈澶勭悊鐩爣
- 琛ラ綈 `CommissionService` 鐨勮繑浣ｈ绠楄寖鍥达紝閬垮厤 `calculateRealTimeCommission` 涓? `calculateSettlement` 缁х画鍙鍙? `agent_descendants`銆?
- 褰撴棫椤圭洰瀵煎叆鏁版嵁鍙湁 `user_infos.parent_id` 鍏崇郴鎴? `agent_descendants` 涓嶅畬鏁存椂锛屽疄鏃惰繑浣ｅ拰寰呯粨绠楄繑浣ｄ粛鑳界撼鍏ュ綋鍓嶄唬鐞嗘爲涓嬬湡瀹炵敤鎴疯鍗曘??
- 淇濇寔杩斾剑鍏紡銆佽鍗曞紑骞充粨 scope銆佺粨绠楃姸鎬佽繃婊ゃ?乣commission_records` 鍐欏叆瀛楁鍜岀幇鏈夎繑鍥炵粨鏋勪笉鍙橈紝鍙粺涓?涓嬬骇鐢ㄦ埛 ID 鑼冨洿鏉ユ簮銆?

### 鏈鍙樻洿鏂囦欢
- `app/Services/CommissionService.php`锛坄CommissionService`锛?
  - `calculateRealTimeCommission` 鏀逛负閫氳繃 `FrontLegacyData::userScopeIds($agentId, false)` 鑾峰彇褰撳墠浠ｇ悊鍙涓嬬骇鐢ㄦ埛鑼冨洿銆?
  - `calculateSettlement` 鏀逛负閫氳繃鍚屼竴鍏变韩鑼冨洿鑾峰彇寰呯粨绠楀钩浠撹鍗曠敤鎴疯寖鍥淬??
  - 缁х画澶嶇敤 `UserTrade::open()` 涓? `UserTrade::closed()`锛屼笉閲嶅缁存姢 MT4 寮?骞充粨鍝ㄥ叺鏉′欢銆?
- `tests/Feature/FrontCommissionServiceScopeFallbackModuleTest.php`
  - 鏂板鍓嶅彴杩斾剑璁＄畻鏈嶅姟浣滅敤鍩熷厹搴曞绾︽祴璇曘??
  - 瑕嗙洊瀹炴椂杩斾剑涓庣粨绠楄绠楀繀椤诲鐢? `FrontLegacyData::userScopeIds`锛屽苟绂佹鍥為??鍒? `DB::table('agent_descendants')`銆?
  - 鏂板鏈?缁堟竻鍗曢棴鐜褰曟祴璇曘??

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontCommissionServiceScopeFallbackModuleTest.php` 棣栨澶辫触锛屽懡涓? `CommissionService` 浠嶇洿鎺ユ煡璇? `agent_descendants` 涓旀竻鍗曠己灏戠 172 鑺傘??
- GREEN锛氳繑浣ｈ绠楁湇鍔＄殑瀹炴椂杩斾剑鍜岀粨绠楄寖鍥寸粺涓?澶嶇敤 `FrontLegacyData::userScopeIds`锛屽苟杩藉姞鏈妭璁板綍褰撳墠闂幆璇佹嵁鍜屽墿浣欒竟鐣屻??

### 褰撳墠璇佹嵁
- `CommissionService::calculateRealTimeCommission` 宸查?氳繃 `FrontLegacyData::userScopeIds($agentId, false)` 鑾峰彇鐢ㄦ埛鑼冨洿锛屽苟缁х画璋冪敤 `UserTrade::open()`銆?
- `CommissionService::calculateSettlement` 宸查?氳繃 `FrontLegacyData::userScopeIds($agentId, false)` 鑾峰彇鐢ㄦ埛鑼冨洿锛屽苟缁х画璋冪敤 `UserTrade::closed()` 涓? `settlement_status=0`銆?
- `FrontCommissionServiceScopeFallbackModuleTest` 瑕嗙洊鏈鏈嶅姟闈欐?佸绾﹀拰鏈?缁堟竻鍗曡褰曘??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩杩斾剑鍏紡銆佺偣宸厤缃鍙栥?佺粨绠楄褰曞啓鍏ュ瓧娈点?佽鍗曠姸鎬? scope銆佹暟鎹簱缁撴瀯鎴栧墠绔睍绀哄瓧娈点??
- 褰撳墠 MySQL `127.0.0.1:3307` 宸叉仮澶嶈繛閫氾紱鍚庣画浠嶉渶鎵╁睍鐪熷疄璁㈠崟绾ц绠楁牱渚嬶紝瑕嗙洊 parent_id-only 浠ｇ悊鏍戜笅瀹炴椂杩斾剑閲戦涓庣粨绠楄褰曞啓鍏ラ棴鐜??
## 173. 2026-07-09 鍚庡彴绠＄悊鍛樻暟鎹寖鍥? parent_id 浣滅敤鍩熷厹搴曢棴鐜?
### 鏈澶勭悊鐩爣
- 琛ラ綈鍚庡彴绠＄悊鍛? `agent_tree` / `custom_agents` 鏁版嵁鑼冨洿鐨勪唬鐞嗘爲鍏煎鎬э紝閬垮厤鍙鍙? `agent_descendants` 瀵艰嚧鏃ч」鐩鍏ョ殑 `user_infos.parent_id` 鍏崇郴涓嶅彲瑙併??
- 褰撶粦瀹氫唬鐞嗘爲鍙湁 `user_infos.parent_id` 灞傜骇銆佹病鏈夐棴鍖呰〃璁板綍鏃讹紝鍚庡彴鐢ㄦ埛鍒楄〃鑼冨洿鍜屽崟鏉¤鎯?/瀹℃牳/澶勭悊鏉冮檺鍒ゆ柇浠嶅簲鍙斁琛岃浠ｇ悊鏍戜笅鐪熷疄瀹㈡埛锛屾嫆缁濆叾瀹冧唬鐞嗘爲瀹㈡埛銆?
- 淇濇寔鍘熸湁鏁版嵁鑼冨洿璇箟锛氳捣濮嬩唬鐞? ID 浠嶅苟鍏ュ彲瑙? ID锛宍targetType=agent` 浠嶅彇浠ｇ悊鍚庝唬锛屽叾瀹冪敤鎴?/璧勯噾/浜ゆ槗鐩爣浠嶅彇瀹㈡埛鍚庝唬锛涙湰杞彧缁熶竴鍚庝唬鏉ユ簮銆?

### 鏈鍙樻洿鏂囦欢
- `app/Services/AdminDataScopeService.php`
  - 鍒犻櫎瀵? `AgentDescendant` 鐨勭洿鎺ユ煡璇緷璧栵紝寮曞叆 `FrontLegacyData::userScopeIds` 浣滀负浠ｇ悊鏍戝悗浠ｈВ鏋愬叆鍙ｃ??
  - `resolveAgentTreeUserIds()` 鍏堣繃婊ゆ鏁存暟浠ｇ悊 ID锛岄伩鍏嶆妸閰嶇疆涓殑 `0` 褰撲綔 `parent_id=0` 鏍硅妭鐐瑰睍寮?銆?
  - 姣忎釜璧峰浠ｇ悊閫氳繃 `FrontLegacyData::userScopeIds($agentId, false, $descendantType)` 鍚堝苟闂寘琛ㄥ拰 `user_infos.parent_id` 鍚庝唬锛屽啀淇濇寔涓庤捣濮嬩唬鐞? ID 鍚堝苟杩斿洖銆?
- `tests/Feature/AdminDataScopeServiceTest.php`
  - 鏂板 parent_id-only 鍚庡彴鏁版嵁鑼冨洿 RED/GREEN 鐢ㄤ緥锛岃鐩栧垪琛? `apply()` 涓庡崟鏉? `canAccessUser()` 涓ゆ潯璺緞銆?
  - 鎵╁睍 `createUserInfo()` 娴嬭瘯 helper锛屾敮鎸佹瀯閫? `account_type` 涓? `parent_id` fixture銆?
- `tests/Feature/AdminDataScopeControllerWiringTest.php`
  - 淇 `AdminUserController` 娴嬭瘯妗╂瀯閫犲弬鏁帮紝鎸夌湡瀹炴瀯閫犲嚱鏁颁紶鍏? `UserStatisticsService`锛岄伩鍏嶆帴绾挎祴璇曞洜杩囨湡娴嬭瘯妗╄鎶ャ??

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\AdminDataScopeServiceTest.php --filter parent_id` 棣栨澶辫触锛屽垪琛ㄨ矾寰勮繑鍥炵┖鏁扮粍锛屽崟鏉¤闂矾寰勮繑鍥? false锛岃瘉鏄庢棫鏈嶅姟鍙緷璧? `agent_descendants`銆?
- GREEN锛歚AdminDataScopeService` 鏀逛负澶嶇敤 `FrontLegacyData::userScopeIds` 鍚庯紝鍚屼竴 parent_id-only 鐢ㄤ緥閫氳繃銆?
- 璋冭瘯淇锛歚AdminDataScopeControllerWiringTest` 鏆撮湶娴嬭瘯妗╀粛鎸夋棫鏋勯?犲嚱鏁板疄渚嬪寲 `AdminUserController`锛屽凡鎸夌湡瀹炰緷璧栬ˉ榻愶紝涓嶆敼鐢熶骇涓氬姟閫昏緫銆?

### 褰撳墠璇佹嵁
- `AdminDataScopeService::resolveAgentTreeUserIds` 宸插悓鏃跺吋瀹? `agent_descendants` 涓? `user_infos.parent_id`锛屽悗鍙拌鑹茬粦瀹氫唬鐞嗘爲銆佸崟鏉℃潈闄愭牎楠屽拰鑷畾涔変唬鐞嗚寖鍥村叡鐢ㄥ悓涓?瑙ｆ瀽璺緞銆?
- `AdminDataScopeServiceTest` 瑕嗙洊闂寘琛ㄨ矾寰勫拰 parent_id-only 杩佺Щ鏁版嵁璺緞銆?
- 宸查獙璇佸悗鍙版暟鎹寖鍥淬?佹帶鍒跺櫒鎺ョ嚎銆佽縼绉荤己鍙ｅ璁°?佺敤鎴风粺璁°?佷唬鐞嗙粺璁°?佸疄鏃惰繑浣ｅ拰鎸佷粨姹囨?荤浉鍏虫娊鏍枫??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鍚庡彴鎺у埗鍣ㄦ煡璇㈠瓧娈点?佽鑹叉暟鎹寖鍥撮厤缃〃銆佺鐞嗗憳浠ｇ悊缁戝畾琛ㄣ?侀棴鍖呰〃閲嶅缓閫昏緫銆佽祫閲?/浜ゆ槗鑱氬悎 SQL 鎴栧墠绔睍绀哄瓧娈点??
- `FamilyTreeService` 鐨勭綉缁滄爲銆侀棴鍖呰〃閲嶅缓鍜屽悗鍙颁唬鐞? descendants 灞曠ず浠嶄繚鐣欏叾闂寘琛ㄨ亴璐ｏ紱鍚庣画鍙湪鍏蜂綋涓氬姟鍏ュ彛闇?瑕? parent_id 鍏滃簳鏃剁户缁ˉ鐙珛闂幆銆?
- 褰撳墠 MySQL `127.0.0.1:3307` 宸叉仮澶嶈繛閫氾紱鍚庣画浠嶅簲鎸夌湡瀹炲悗鍙拌处鍙风户缁墿灞曠鍒扮鎺ュ彛绾ф暟鎹殧绂绘牱渚嬨??
## 174. 2026-07-09 鍓嶅彴浠ｇ悊缁熻 parent_id 浣滅敤鍩熷厹搴曢棴鐜?
### 鏈澶勭悊鐩爣
- 琛ラ綈 `FamilyTreeService::getAgentStats` 鐨勪笅绾х敤鎴风粺璁¤寖鍥达紝閬垮厤鍓嶅彴浠ｇ悊鍒楄〃鍜屼唬鐞嗙粺璁¤鎯呯户缁彧璇诲彇 `agent_descendants`銆?
- 褰撴棫椤圭洰瀵煎叆鏁版嵁鍙湁 `user_infos.parent_id` 鍏崇郴銆佹病鏈夐棴鍖呰〃璁板綍鏃讹紝浠ｇ悊缁熻浠嶈兘绾冲叆褰撳墠浠ｇ悊鏍戜笅鐪熷疄涓嬬骇浜ゆ槗銆佹椿璺冪敤鎴峰拰鏂板娉ㄥ唽銆?
- 淇濇寔浜ゆ槗閲忋?佺泩浜忋?佽繑浣ｉ噾棰濄?佹椿璺冪敤鎴枫?佹柊澧炴敞鍐岃繑鍥炲瓧娈典笉鍙橈紱鏈疆鍙粺涓?涓嬬骇鐢ㄦ埛 ID 鑼冨洿鏉ユ簮銆?

### 鏈鍙樻洿鏂囦欢
- `app/Services/FamilyTreeService.php`
  - `getAgentStats()` 鐨? `$descendantIds` 鏀逛负 `FrontLegacyData::userScopeIds($agentId, false)`銆?
  - 缁х画淇濈暀 `getAllDescendants()`銆乣getNetworkTree()` 鍜? `rebuildDescendants()` 鐨勯棴鍖呰〃鑱岃矗锛屼笉璇垹鐢ㄤ簬灞曠ず/閲嶅缓鐨勫叧绯昏〃鑳藉姏銆?
  - 鏂规硶娉ㄩ噴鍚屾璇存槑缁熻鑼冨洿鍏煎闂寘琛ㄤ笌 `user_infos.parent_id` 瀵煎叆鍏崇郴銆?
- `tests/Feature/FrontFamilyTreeAgentStatsScopeFallbackModuleTest.php`
  - 鏂板 parent_id-only 鏈嶅姟绾ч棴鐜祴璇曪紝鏋勯?犳棤 `agent_descendants` 琛岀殑浠ｇ悊鏍戯紝骞堕獙璇? `getAgentStats()` 鑳界粺璁′笅绾т氦鏄撳拰娉ㄥ唽銆?
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontFamilyTreeAgentStatsScopeFallbackModuleTest.php` 棣栨澶辫触锛宍total_volume` 浠庢湡鏈? `300.0` 鍙樻垚 `0.0`锛岃瘉鏄庢棫 `getAgentStats()` 鍦ㄩ棴鍖呰〃缂哄け鏃舵紡鎺? parent_id-only 涓嬬骇浜ゆ槗銆?
- GREEN锛歚FamilyTreeService::getAgentStats()` 鏀逛负澶嶇敤 `FrontLegacyData::userScopeIds($agentId, false)` 鍚庯紝鍚屼竴鏈嶅姟绾ф祴璇曢?氳繃銆?
- RED锛歚vendor\bin\phpunit tests\Feature\FrontFamilyTreeAgentStatsScopeFallbackModuleTest.php --filter final_checklist` 棣栨澶辫触锛屽懡涓渶缁堟竻鍗曠己灏戠 174 鑺傘??
- GREEN锛氳拷鍔犳湰鑺傝褰曞悗锛屾竻鍗曟祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FamilyTreeService::getAgentStats` 宸插悓鏃跺吋瀹? `agent_descendants` 涓? `user_infos.parent_id` 鐨勪笅绾х敤鎴疯寖鍥淬??
- `FrontFamilyTreeAgentStatsScopeFallbackModuleTest` 瑕嗙洊 parent_id-only 浠ｇ悊鏍戜笅鐨勪氦鏄撻噺銆佺泩浜忋?佽繑浣ｉ噾棰濄?佹椿璺冪敤鎴峰拰鏂板娉ㄥ唽缁熻銆?
- 宸查獙璇佸墠鍙伴椤典綔鐢ㄥ煙銆佸墠鍙颁唬鐞嗕綔鐢ㄥ煙銆佸墠鍙颁唬鐞嗕富鍒楄〃銆佹棫鍓嶅彴璺敱鍏煎銆丗amilyTreeService 娉ㄩ噴鍙鎬у拰杩佺Щ缂哄彛瀹¤鐩稿叧娴嬭瘯銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `FamilyTreeService::getAllDescendants()`銆乣getNetworkTree()`銆乣rebuildFamilyTree()`銆乣rebuildDescendants()`銆佺湡瀹炰氦鏄撹仛鍚堝瓧娈点?佽繑浣ｈ褰曞啓鍏ュ彛寰勩?佹暟鎹簱缁撴瀯鎴栧墠绔睍绀哄瓧娈点??
- `FrontLegacyData::userScopeIds` 浠嶆槸褰撳墠鍏变韩浣滅敤鍩熷叆鍙ｏ紱鍚庣画鑻ヨ鎶? `FamilyTreeService` 鍓╀綑缃戠粶鏍戝睍绀哄叆鍙ｅ畬鍏? parent_id 鍖栵紝搴旀寜鍏蜂綋璋冪敤鍏ュ彛琛ュ崟鐙? RED/GREEN銆?
- 褰撳墠 MySQL `127.0.0.1:3307` 宸叉仮澶嶈繛閫氾紱鍚庣画浠嶅簲琛ョ湡瀹炲墠鍙颁唬鐞嗚处鍙锋帴鍙ｇ骇缁熻闅旂鏍蜂緥銆?
## 175. 2026-07-09 鍓嶅彴璧勬枡鍏崇郴閾? parent_id 绁栧厛閾惧厹搴曢棴鐜?
### 鏈澶勭悊鐩爣
- 琛ラ綈 `ProfileController::relationshipIds` 鐨勭鍏堥摼鍏滃簳锛岄伩鍏嶈祫鏂欓〉鍏崇郴閾炬帴鍙ｅ湪 `family_tree` 鍜? `agent_descendants` 閮界己澶辨椂鍙繑鍥炵洰鏍囩敤鎴疯嚜韬??
- 褰撴棫椤圭洰瀵煎叆鏁版嵁鍙湁 `user_infos.parent_id` 鍏崇郴鏃讹紝`/api/front/profile/relationship-path`銆佹棫 `user/relationShipHtml` 鍜屼唬鐞嗗叧绯婚摼 HTML 鍏ュ彛浠嶈兘杩斿洖浠庝笂绾т唬鐞嗗埌鐩爣鐢ㄦ埛鐨勫畬鏁? ID 閾俱??
- 淇濇寔鏃㈡湁浼樺厛绾э細`user_infos.family_tree` 浼樺厛锛屽叾娆′繚鐣欐棫闂寘琛ㄥ洖閫?锛涘彧鏈夐棴鍖呰〃涔熸棤绁栧厛琛屾椂锛屾墠娌? `user_infos.parent_id` 鍚戜笂缁勯摼銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/ProfileController.php`
  - `relationshipIds()` 鍦ㄩ棴鍖呰〃鏃犵鍏堣鏃跺洖閫?鍒? `parentRelationshipIds()`銆?
  - 鏂板 `parentRelationshipIds()`锛屾部 `parent_id` 鍚戜笂鏀堕泦绁栧厛 ID锛屽苟浣跨敤 visited 闃叉鑴忔暟鎹惊鐜??
  - 鍏崇郴閾捐繑鍥炴牸寮忓拰涓変釜鍏紑鍏ュ彛 `relationShip`銆乣relationShipHtml`銆乣relationShipHtmlV2` 淇濇寔涓嶅彉銆?
- `tests/Feature/FrontProfileRelationshipScopeFallbackModuleTest.php`
  - 鏂板 parent_id-only 鍏崇郴閾鹃棴鐜祴璇曪紝鏋勯?犳棤 `family_tree`銆佹棤 `agent_descendants` 鐨勪唬鐞嗘爲锛岄獙璇佸叧绯婚摼杩斿洖 `root -> sub -> customer`銆?
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontProfileRelationshipScopeFallbackModuleTest.php --filter parent_id_tree` 棣栨澶辫触锛屽疄闄呰繑鍥炵洰鏍囩敤鎴疯嚜韬? ID锛岃瘉鏄庢棫閫昏緫娌℃湁 parent_id 绁栧厛閾惧厹搴曘??
- GREEN锛歚ProfileController::relationshipIds()` 澧炲姞 `parentRelationshipIds()` 鍥為??鍚庯紝鍚屼竴 parent_id-only 鐢ㄤ緥閫氳繃銆?
- RED锛歚vendor\bin\phpunit tests\Feature\FrontProfileRelationshipScopeFallbackModuleTest.php --filter final_checklist` 棣栨澶辫触锛屽懡涓渶缁堟竻鍗曠己灏戠 175 鑺傘??
- GREEN锛氳拷鍔犳湰鑺傝褰曞悗锛屾竻鍗曟祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `ProfileController::relationshipIds` 宸插吋瀹? `family_tree`銆乣agent_descendants` 鍜? `user_infos.parent_id` 涓夌鍏崇郴閾炬潵婧愩??
- `FrontProfileRelationshipScopeFallbackModuleTest` 瑕嗙洊 parent_id-only 瀵煎叆鏁版嵁涓嬬殑璧勬枡鍏崇郴閾捐緭鍑恒??
- 宸查獙璇? ProfileController 娉ㄩ噴鍙鎬с?佹棫鍓嶅彴璺敱鍏煎銆佽祫鏂欒矾鐢遍潤鎬佸绾﹀拰杩佺Щ缂哄彛瀹¤鐩稿叧娴嬭瘯銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩澶村儚銆佽祫鏂欐洿鏂般?佸疄鍚?/閾惰鍗′笂浼犮?侀攢鎴烽獙璇併?佽矾鐢卞畾涔夈?佸搷搴? JSON 瀛楁鎴栧墠绔睍绀洪?昏緫銆?
- 闂寘琛ㄦ湁绁栧厛琛屾椂浠嶄繚鐣欐棫鍥為??椤哄簭锛涘鍚庣画瑕佺粺涓?闂寘琛ㄥ叧绯婚摼椤哄簭锛屽簲鍗曠嫭琛ュ巻鍙插吋瀹规祴璇曞悗鍐嶆敼銆?
- 褰撳墠 MySQL `127.0.0.1:3307` 宸叉仮澶嶈繛閫氾紱鍚庣画浠嶅彲琛ョ櫥褰曟?佹帴鍙ｇ骇鏍蜂緥锛岃鐩栫湡瀹炲墠鍙拌处鍙疯皟鐢ㄥ叧绯婚摼鎺ュ彛銆?
## 176. 2026-07-09 鍓嶅彴鐩村睘浠ｇ悊娴佹按璺敱浣滅敤鍩熼棴鐜?
### 鏈澶勭悊鐩爣
- 淇鏂扮増 `/api/front/flows/direct-agent-deposits`銆乣/api/front/flows/direct-agent-withdrawals` 涓庢棫 `user/flow/directAgents*FlowSearch` 鍏ュ彛澶嶇敤鍚屼竴鎺у埗鍣ㄦ柟娉曟椂浠嶅浐瀹氭煡璇㈢洿灞炲鎴锋祦姘寸殑闂銆?
- 褰撴棫椤圭洰瀵煎叆鏁版嵁鍙湁 `user_infos.parent_id` 鍏崇郴銆佹病鏈? `agent_descendants` 闂寘琛ㄨ褰曟椂锛岀洿灞炰唬鐞嗘祦姘村繀椤昏繘鍏? `direct_agents_deposit` / `direct_agents_withdraw` 浣滅敤鍩燂紝涓嶈兘涓插埌鐩村睘瀹㈡埛娴佹按銆?
- 淇濇寔鐩村睘瀹㈡埛娴佹按銆佸垎椤电粨鏋勩?佹眹鎬诲瓧娈点?佹棫鍓嶅彴鍝嶅簲瀛楁鍜屽墠绔? tab 璺敱涓嶅彉锛屽彧琛ラ綈璺敱鍒版祦姘寸被鍨嬬殑鍒嗘祦銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/FlowController.php`
  - `FlowController::directDepositFlowSearch` 鎸夊綋鍓嶈矾鐢卞悕/璺緞鍒ゆ柇鐩村睘浠ｇ悊鍏ュ彛锛屽垎鍒啓鍏? `direct_agents_deposit` 鎴? `direct_deposit`銆?
  - `FlowController::directWithdrawalFlowSearch` 鎸夊悓涓?瑙勫垯鍐欏叆 `direct_agents_withdraw` 鎴? `direct_withdraw`銆?
  - 鏂板 `isDirectAgentFlowRequest()`锛屽悓鏃跺吋瀹规柊鐗? `front_api_flows_direct_agent_*` 璺敱銆佹棫 `legacy_user_flow_direct_agents_*` 璺敱鍜屽巻鍙查┘宄拌矾寰勩??
- `tests/Feature/FrontFlowDirectAgentRouteScopeModuleTest.php`
  - 鏂板鎺ュ彛绾ч棴鐜祴璇曪紝鏋勯?? parent_id-only 鐩村睘浠ｇ悊鍜岀洿灞炲鎴凤紝骞跺啓鍏ョ湡瀹? `deposit_records`銆乣withdraw_records`銆?
  - 楠岃瘉鐩村睘浠ｇ悊璺敱鍙繑鍥炰唬鐞嗘祦姘达紝鐩村睘瀹㈡埛璺敱鍙繑鍥炲鎴锋祦姘淬??
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontFlowDirectAgentRouteScopeModuleTest.php --filter direct_agent` 棣栨澶辫触锛岀洿灞炰唬鐞嗗叆閲戝拰鍑洪噾璺敱鍒嗗埆杩斿洖 `DCDEP-*`銆乣DCWDR-*` 鐩村睘瀹㈡埛璁㈠崟鍙凤紝璇佹槑璺敱鍒嗘祦缂哄け銆?
- GREEN锛歚FlowController` 鏍规嵁璺敱鍚?/璺緞閫夋嫨 `direct_agents_deposit` 涓? `direct_agents_withdraw` 鍚庯紝鐩村睘浠ｇ悊鍜岀洿灞炲鎴蜂袱绫绘帴鍙ｇ骇鏍蜂緥鍧囬?氳繃銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 176 鑺傘??

### 褰撳墠璇佹嵁
- `FlowController::directDepositFlowSearch` 宸茶兘鍖哄垎鐩村睘瀹㈡埛鍏ラ噾涓庣洿灞炰唬鐞嗗叆閲戝叆鍙ｃ??
- `FlowController::directWithdrawalFlowSearch` 宸茶兘鍖哄垎鐩村睘瀹㈡埛鍑洪噾涓庣洿灞炰唬鐞嗗嚭閲戝叆鍙ｃ??
- `FrontFlowDirectAgentRouteScopeModuleTest` 瑕嗙洊 parent_id-only 鍏崇郴涓嬬殑鐪熷疄鎺ュ彛銆佺湡瀹炴祦姘磋〃鍜屽墠绔娇鐢ㄧ殑鏂扮増 API 璺緞銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `typedFlow()` 鐨勫垎椤点?佹眹鎬汇?佹棩鏈熺瓫閫夈?佺敤鎴风瓫閫夈?佸鍑恒?佹棫鍓嶅彴瀛楁鏄犲皠鎴栨暟鎹簱缁撴瀯銆?
- 鏃? web 璺敱閫氳繃鍚屼竴涓? route name/path 鍒ゆ柇閫昏緫鍏煎锛涘悗缁瑕佷负鐩村睘浠ｇ悊娴佹按鎷嗙嫭绔嬫帶鍒跺櫒鏂规硶锛屽簲鍏堟洿鏂拌矾鐢卞吋瀹规祴璇曞悗鍐嶈縼绉汇??
## 177. 2026-07-09 鍓嶅彴璐︽埛缁煎悎 parent_id 瀹㈡埛鑼冨洿鍏滃簳闂幆

### 鏈澶勭悊鐩爣
- 琛ラ綈 `AccountController::accountOverviewData` 鐨勫鎴疯寖鍥寸粺璁★紝閬垮厤璐︽埛缁煎悎椤靛湪 `family_tree` 鍜? `agent_descendants` 缂哄け鏃舵紡鎺? parent_id-only 鐨勯棿鎺ュ鎴枫??
- 褰撴棫椤圭洰瀵煎叆鏁版嵁鍙湁 `user_infos.parent_id` 鍏崇郴鏃讹紝鐩村睘浠ｇ悊鏁般?佺洿灞炲鎴锋暟銆侀棿鎺ュ鎴锋暟銆佸鎴锋?у埆鐢诲儚鍜屽叧绯诲叆閲戦噾棰濅粛鎸夊悓涓?浠ｇ悊鏍戣繑鍥炪??
- 淇濇寔璐︽埛璧勯噾鎸囨爣銆佽鍗曠粺璁°?佷綑棰濋〉澶嶇敤缁撴瀯銆佸墠绔瓧娈靛悕鍜屽搷搴旀牸寮忎笉鍙橈紝鍙粺涓?瀹㈡埛鑼冨洿鏉ユ簮銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/AccountController.php`
  - `AccountController::accountOverviewData` 鏀逛负閫氳繃 `FrontLegacyData::userScopeIds` 鑾峰彇鐩村睘浠ｇ悊銆佺洿灞炲鎴峰拰鍏ㄩ儴瀹㈡埛 ID銆?
  - `indirect_customers` 鏀逛负鐢ㄥ叏閮ㄥ鎴? ID 鎵ｉ櫎鐩村睘瀹㈡埛 ID锛岄伩鍏嶄緷璧? `family_tree like`銆?
  - `relation_amount` 鏀逛负鎸夊叏閮ㄥ鎴? ID 姹囨?? `deposit_records.amount`銆?
  - `AccountController::customerGenderProfile` 鏀逛负鎺ユ敹瀹㈡埛 ID 鏁扮粍锛屽苟鎸夊悓涓?鑼冨洿缁熻鐢峰コ鍜屾湭鐭ユ?у埆鍗犳瘮銆?
- `tests/Feature/FrontAccountOverviewScopeFallbackModuleTest.php`
  - 鏂板鎺ュ彛绾ч棴鐜祴璇曪紝鏋勯?犳棤 `agent_descendants`銆佹棤 `family_tree` 鐨? parent_id-only 浠ｇ悊鏍戙??
  - 楠岃瘉 `/api/front/account/profile` 鑳借繑鍥炵洿灞炰唬鐞嗐?佺洿灞炲鎴枫?侀棿鎺ュ鎴枫?佸叧绯诲叆閲戦噾棰濆拰瀹㈡埛鎬у埆鐢诲儚銆?
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAccountOverviewScopeFallbackModuleTest.php` 棣栨澶辫触锛宍indirect_customers` 瀹為檯涓? `0`锛岃瘉鏄庢棫璐︽埛缁煎悎椤靛彧闈犵洿灞? `parent_id` 鍜? `family_tree` 浼氭紡鎺夐棿鎺ュ鎴枫??
- GREEN锛氳处鎴风患鍚堥〉缁熶竴澶嶇敤 `FrontLegacyData::userScopeIds` 鍚庯紝parent_id-only 鎺ュ彛鏍蜂緥閫氳繃銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 177 鑺傘??

### 褰撳墠璇佹嵁
- `AccountController::accountOverviewData` 宸查?氳繃 `FrontLegacyData::userScopeIds` 鍚屾椂鍏煎 `agent_descendants` 涓? `user_infos.parent_id` 鐨勫鎴疯寖鍥淬??
- `AccountController::customerGenderProfile` 涓? `relation_amount` 宸插鐢ㄥ悓涓?鎵瑰叏閮ㄥ鎴? ID锛岄伩鍏嶆?у埆鐢诲儚鍜屽叧绯婚噾棰濅娇鐢ㄧ浜屽鑼冨洿銆?
- `FrontAccountOverviewScopeFallbackModuleTest` 瑕嗙洊 parent_id-only 鍏崇郴涓嬬殑鐪熷疄鎺ュ彛銆佺湡瀹炵敤鎴疯〃鍜岀湡瀹炲叆閲戣〃銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩璐︽埛璧勯噾瀛楁銆佸紑骞充粨璁㈠崟缁熻銆佽璇佺姸鎬併?佺粍鍒睍绀恒?佷綑棰濋〉璺敱銆佸墠绔睍绀哄瓧娈垫垨鏁版嵁搴撶粨鏋勩??
- `FrontLegacyData::userScopeIds` 浠嶆槸鍓嶅彴鍏变韩鑼冨洿鍏ュ彛锛涘悗缁鍙戠幇鍏跺畠璐︽埛椤靛叆鍙ｄ粛鎵嬪啓 `family_tree` 鎴? `parent_id` 鑼冨洿锛屽簲缁х画鎸夌嫭绔? RED/GREEN 琛ラ綈銆?
- 褰撳墠 MySQL `127.0.0.1:3307` 宸叉仮澶嶈繛閫氾紱鍚庣画浠嶅彲琛ョ湡瀹炵櫥褰曟?佽处鍙风殑璐︽埛缁煎悎椤电鍒扮闅旂鏍蜂緥銆?

## 178. 2026-07-09 鍓嶅彴璁㈠崟閾捐矾 parent_id 鍏滃簳闂幆

### 鏈澶勭悊鐩爣
- 琛ラ綈 `OrderController::orderChain` 鐨勯摼璺睍绀哄厹搴曪紝閬垮厤璁㈠崟鍒楄〃鑼冨洿宸茶兘閫氳繃 `FrontLegacyData::userScopeIds` 鍛戒腑 parent_id-only 瀹㈡埛璁㈠崟锛屼絾杩斿洖鐨? `order_chain` 浠嶅洜缂哄皯 `family_tree` 鍙樻垚绌洪摼銆?
- 褰撴棫椤圭洰瀵煎叆鏁版嵁鍙湁 `user_infos.parent_id` 鍏崇郴銆佹病鏈? `family_tree` 鍜? `agent_descendants` 鏃讹紝鍓嶅彴浠ｇ悊鏌ョ湅涓嬬骇璁㈠崟浠嶈兘鐪嬪埌褰撳墠浠ｇ悊鍒颁笅绾т唬鐞嗗啀鍒板鎴风殑瀹屾暣璁㈠崟閾捐矾銆?
- 淇濇寔璁㈠崟鏌ヨ鑼冨洿銆佸紑骞充粨 scope銆佸垎椤垫眹鎬汇?佽鍗曞瓧娈点?佽鎯呭脊灞傚拰杩斾剑鏄庣粏缁撴瀯涓嶅彉锛屽彧琛ラ綈閾捐矾灞曠ず ID 鏉ユ簮銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/OrderController.php`
  - `OrderController::orderChain` 鏀逛负鍏堥?氳繃 `orderChainIds()` 鑾峰彇閾捐矾 ID锛屽啀鎸夊綋鍓嶆煡鐪嬩唬鐞嗘埅鍙栧彲瑙侀摼璺??
  - 鏂板 `orderChainIds()`锛屼繚鐣? `family_tree` 浼樺厛锛涗粎褰? `family_tree` 涓虹┖鏃舵墠鍥為?? parent 閾俱??
  - 鏂板 `parentOrderChainIds()`锛屾部 `user_infos.parent_id` 鍚戜笂琛ラ綈绁栧厛 ID锛屽苟浣跨敤 visited 闃叉鑴忔暟鎹惊鐜??
- `tests/Feature/FrontOrderChainScopeFallbackModuleTest.php`
  - 鏂板鎺ュ彛绾ч棴鐜祴璇曪紝鏋勯?犳棤 `agent_descendants`銆佹棤 `family_tree` 鐨? root agent -> sub agent -> customer 涓夊眰鍏崇郴銆?
  - 楠岃瘉 `/api/front/orders/closed` 鑳借繑鍥炵湡瀹炶鍗曪紝骞跺湪 `order_chain` 涓緭鍑? root -> sub -> customer銆?
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontOrderChainScopeFallbackModuleTest.php` 棣栨鏈夋晥澶辫触锛岃鍗曟帴鍙ｈ繑鍥炵湡瀹炶鍗曪紝浣? `order_chain` 瀹為檯涓虹┖鏁扮粍锛岃瘉鏄庢棫 `OrderController::orderChain` 鍙緷璧? `family_tree`銆?
- GREEN锛歚OrderController::orderChain` 澧炲姞 parent 閾惧厹搴曞悗锛屽悓涓? parent_id-only 璁㈠崟鎺ュ彛鏍蜂緥閫氳繃銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 178 鑺傘??

### 褰撳墠璇佹嵁
- 璁㈠崟鍒楄〃鍙鑼冨洿浠嶇敱 `FrontLegacyData::userScopeIds` 閫氳繃 `FrontLegacyData::applyAllowedUserFilter` 绾︽潫銆?
- `OrderController::orderChain` 宸插吋瀹? `family_tree` 涓? `user_infos.parent_id` 涓ょ閾捐矾鏉ユ簮锛屼笖鍙繑鍥炲綋鍓嶆煡鐪嬩唬鐞嗚妭鐐逛箣鍚庣殑鍙閾捐矾銆?
- `FrontOrderChainScopeFallbackModuleTest` 瑕嗙洊 parent_id-only 鍏崇郴涓嬬殑鐪熷疄鐧诲綍鎬併?佺湡瀹炶鍗曡〃鍜岀湡瀹炶鍗曢摼璺搷搴斻??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩璁㈠崟鏌ヨ杩囨护銆佸紑浠?/骞充粨鍒ゆ柇銆佽鍗曟眹鎬汇?佸墠绔瓧娈垫槧灏勩?佽鎯呭脊灞? HTML銆佽繑浣ｆ媶鍒嗚绠楁垨鏁版嵁搴撶粨鏋勩??
- `CommissionController::orderChain` 浠嶆湁鐙珛閾捐矾灞曠ず閫昏緫锛涘悗缁簲鎸夊疄鏃惰繑浣ｅ垪琛ㄥ叆鍙ｅ崟鐙ˉ RED/GREEN锛岄伩鍏嶆妸璁㈠崟鍒楄〃鏀瑰姩鎵╁ぇ鍒拌繑浣ｆā鍧椼??
- 褰撳墠 MySQL `127.0.0.1:3307` 宸叉仮澶嶈繛閫氾紱鍚庣画浠嶅彲鐢ㄧ湡瀹炰唬鐞嗚处鍙疯ˉ璁㈠崟璇︽儏寮瑰眰 HTML 鐨? parent_id-only 閾捐矾灞曠ず鏍蜂緥銆?

## 179. 2026-07-09 鍓嶅彴瀹炴椂杩斾剑璁㈠崟閾捐矾 parent_id 鍏滃簳闂幆

### 鏈澶勭悊鐩爣
- 琛ラ綈 `CommissionController::orderChain` 鐨勯摼璺睍绀哄厹搴曪紝閬垮厤瀹炴椂杩斾剑鍒楄〃鑼冨洿宸茶兘閫氳繃 `FrontLegacyData::userScopeIds` 鍛戒腑 parent_id-only 瀹㈡埛璁㈠崟锛屼絾杩斿洖鐨? `order_chain` 浠嶅洜缂哄皯 `family_tree` 鍙樻垚绌洪摼銆?
- 褰撴棫椤圭洰瀵煎叆鏁版嵁鍙湁 `user_infos.parent_id` 鍏崇郴銆佹病鏈? `family_tree` 鍜? `agent_descendants` 鏃讹紝鍓嶅彴浠ｇ悊鏌ョ湅瀹炴椂杩斾剑璁㈠崟浠嶈兘鐪嬪埌褰撳墠浠ｇ悊鍒颁笅绾т唬鐞嗗啀鍒板鎴风殑瀹屾暣璁㈠崟閾捐矾銆?
- 淇濇寔瀹炴椂杩斾剑鏌ヨ銆佸钩浠撹鍗? scope銆佸綋鍓嶄唬鐞嗚繑浣ｉ噾棰濄?佺粨绠楃姸鎬併?佽繑浣ｆ瘮渚嬪拰璇︽儏寮瑰眰缁撴瀯涓嶅彉锛屽彧琛ラ綈鍒楄〃閾捐矾灞曠ず ID 鏉ユ簮銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/CommissionController.php`
  - `CommissionController::orderChain` 鏀逛负鍏堥?氳繃 `orderChainIds()` 鑾峰彇閾捐矾 ID锛屽啀鎸夊綋鍓嶆煡鐪嬩唬鐞嗘埅鍙栧彲瑙侀摼璺??
  - 鏂板 `orderChainIds()`锛屼繚鐣? `family_tree` 浼樺厛锛涗粎褰? `family_tree` 涓虹┖鏃舵墠鍥為?? parent 閾俱??
  - 鏂板 `parentOrderChainIds()`锛屾部 `user_infos.parent_id` 鍚戜笂琛ラ綈绁栧厛 ID锛屽苟浣跨敤 visited 闃叉鑴忔暟鎹惊鐜??
- `tests/Feature/FrontCommissionOrderChainScopeFallbackModuleTest.php`
  - 鏂板鎺ュ彛绾ч棴鐜祴璇曪紝鏋勯?犳棤 `agent_descendants`銆佹棤 `family_tree` 鐨? root agent -> sub agent -> customer 涓夊眰鍏崇郴銆?
  - 楠岃瘉 `/api/front/commissions/realtime` 鑳借繑鍥炵湡瀹炲钩浠撹鍗曪紝骞跺湪 `order_chain` 涓緭鍑? root -> sub -> customer銆?
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontCommissionOrderChainScopeFallbackModuleTest.php` 棣栨澶辫触锛屽疄鏃惰繑浣ｆ帴鍙ｈ繑鍥炵湡瀹炶鍗曪紝浣? `order_chain` 瀹為檯涓虹┖鏁扮粍锛岃瘉鏄庢棫 `CommissionController::orderChain` 鍙緷璧? `family_tree`銆?
- GREEN锛歚CommissionController::orderChain` 澧炲姞 parent 閾惧厹搴曞悗锛屽悓涓? parent_id-only 瀹炴椂杩斾剑鎺ュ彛鏍蜂緥閫氳繃銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 179 鑺傘??

### 褰撳墠璇佹嵁
- 瀹炴椂杩斾剑鍒楄〃鍙鑼冨洿浠嶇敱 `FrontLegacyData::userScopeIds` 鐩存帴绾︽潫銆?
- `CommissionController::orderChain` 宸插吋瀹? `family_tree` 涓? `user_infos.parent_id` 涓ょ閾捐矾鏉ユ簮锛屼笖鍙繑鍥炲綋鍓嶆煡鐪嬩唬鐞嗚妭鐐逛箣鍚庣殑鍙閾捐矾銆?
- `FrontCommissionOrderChainScopeFallbackModuleTest` 瑕嗙洊 parent_id-only 鍏崇郴涓嬬殑鐪熷疄鐧诲綍鎬併?佺湡瀹炶鍗曡〃鍜岀湡瀹炲疄鏃惰繑浣ｉ摼璺搷搴斻??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩瀹炴椂杩斾剑閲戦璁＄畻銆佽繑浣ｈ鎯呮媶鍒嗐?佺粨绠楃姸鎬併?佸巻鍙茶繑浣ｅ垪琛ㄣ?佷剑閲戣浆璐︺?佸墠绔瓧娈垫槧灏勬垨鏁版嵁搴撶粨鏋勩??
- 瀹炴椂杩斾剑璇︽儏寮瑰眰褰撳墠涓昏灞曠ず杩斾剑鏄庣粏琛紱濡傚悗缁鍦ㄥ脊灞備腑灞曠ず璁㈠崟閾捐矾锛屽簲鎸? HTML 杈撳嚭鍗曠嫭琛? RED/GREEN銆?
- 褰撳墠 MySQL `127.0.0.1:3307` 宸叉仮澶嶈繛閫氾紱鍚庣画浠嶅彲鐢ㄧ湡瀹炰唬鐞嗚处鍙疯ˉ瀹炴椂杩斾剑璇︽儏寮瑰眰鐨? parent_id-only 灞曠ず鏍蜂緥銆?

## 180. 2026-07-09 鍓嶅彴浠ｇ悊鍒楄〃 depth 瀛楁 parent_id 鍏滃簳闂幆

### 鏈澶勭悊鐩爣
- 琛ラ綈 `AgentController::scopeDepth` 鐨勫眰绾ц绠楀厹搴曪紝閬垮厤鍓嶅彴浠ｇ悊鍒楄〃鍦? `family_tree` 鍜? `agent_descendants` 缂哄け鏃舵妸澶氬眰 parent_id-only 涓嬬骇浠ｇ悊缁熶竴鏄剧ず涓? depth=2銆?
- 褰撴棫椤圭洰瀵煎叆鏁版嵁鍙繚鐣? `user_infos.parent_id` 灞傜骇鏃讹紝`/api/front/agents/direct` 浠嶅簲鎸夊綋鍓嶄唬鐞嗗彲瑙佽寖鍥磋繑鍥炲叏閮ㄤ笅绾т唬鐞嗭紝骞剁粰鍑虹浉瀵瑰綋鍓嶄唬鐞嗙殑鐪熷疄 depth銆?
- 淇濇寔浠ｇ悊鍒楄〃鏌ヨ鑼冨洿銆佸垎椤电粨鏋勩?佺粺璁″瓧娈点?佺洿灞炵瓫閫夊拰鍓嶇瀛楁鍚嶄笉鍙橈紱鏈疆鍙ˉ depth 灞曠ず鍊兼潵婧愩??

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/AgentController.php`
  - `AgentController::scopeDepth` 鍦? `family_tree` 鏃犳硶瑙ｆ瀽灞傜骇鏃跺洖閫?鍒? parent 閾捐绠椼??
  - 鏂板 `AgentController::parentScopeDepth`锛屾部 `user_infos.parent_id` 鍚戜笂鏌ユ壘褰撳墠鏌ョ湅浠ｇ悊锛屼娇鐢? visited 闃叉鑴忔暟鎹惊鐜??
  - 浠ｇ悊鍙鑼冨洿缁х画澶嶇敤 `FrontLegacyData::userScopeIds`锛屽彧缁熶竴鍒楄〃 depth 瀛楁鐨勫厹搴曡涔夈??
- `tests/Feature/FrontAgentScopeDepthFallbackModuleTest.php`
  - 鏂板 parent_id-only 浠ｇ悊鏍戦棴鐜祴璇曪紝鏋勯?? root -> level1 -> level2 -> level3 涓旀竻绌? `agent_descendants` 鍜? `family_tree`銆?
  - 楠岃瘉 `/api/front/agents/direct` 杩斿洖 level1/level2/level3 鐨? depth 鍒嗗埆涓? 1銆?2銆?3銆?
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentScopeDepthFallbackModuleTest.php` 棣栨澶辫触锛岀涓夊眰浠ｇ悊 depth 瀹為檯涓? `2`銆佹湡鏈涗负 `3`锛岃瘉鏄庢棫 `scopeDepth` 鍦? parent_id-only 澶氬眰浠ｇ悊鏍戜笅鍙兘缁欓潪鐩村睘涓嬬骇鍥哄畾鍏滃簳鍊笺??
- GREEN锛歚AgentController::scopeDepth` 澧炲姞 `parentScopeDepth` 鍥為??鍚庯紝鍚屼竴涓氬姟鏂█閫氳繃銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 180 鑺傘??

### 褰撳墠璇佹嵁
- `AgentController::scopeDepth` 宸插吋瀹? `family_tree` 涓? `user_infos.parent_id` 涓ょ灞傜骇鏉ユ簮銆?
- `AgentController::parentScopeDepth` 鍙湪 `family_tree` 鏃犳硶缁欏嚭褰撳墠浠ｇ悊鐩稿灞傜骇鏃惰Е鍙戯紝涓嶆敼鍙樺凡鏈? family_tree 浼樺厛绾с??
- `FrontAgentScopeDepthFallbackModuleTest` 瑕嗙洊 parent_id-only 鍏崇郴涓嬬殑鐪熷疄鐧诲綍鎬併?佺湡瀹炵敤鎴疯〃鍜岀湡瀹炲墠鍙颁唬鐞嗗垪琛ㄦ帴鍙ｃ??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊鍒楄〃鏌ヨ鑼冨洿銆佺洿灞炲鎴锋槑缁嗐?佷唬鐞嗙粺璁¤仛鍚堛?侀棴鍖呰〃閲嶅缓銆佸墠绔瓧娈垫槧灏勬垨鏁版嵁搴撶粨鏋勩??
- `FrontLegacyData::userScopeIds` 浠嶆槸鍓嶅彴浠ｇ悊鍙鑼冨洿鍏ュ彛锛涘悗缁嫢鍙戠幇鍏跺畠鍒楄〃瀛楁浠嶆墜鍐? `family_tree` 灞傜骇锛屽簲缁х画鎸夌嫭绔? RED/GREEN 琛ラ棴鐜??
- 褰撳墠 MySQL `127.0.0.1:3307` 宸叉仮澶嶈繛閫氾紱鍚庣画缁х画鐢ㄧ湡瀹炴暟鎹簱浜嬪姟琛ュ墿浣欐ā鍧楁祴璇曘??
## 181. 2026-07-09 鍓嶅彴鎸佷粨姹囨?婚摼璺? parent_id 鍏滃簳闂幆

### 鏈澶勭悊鐩爣
- 琛ラ綈 `PositionController::summaryChain` 鐨勯潰鍖呭睉閾捐矾鍏滃簳锛岄伩鍏嶅墠鍙版寔浠撴眹鎬婚捇鍙栦笅绾т唬鐞嗘椂鍦? `family_tree` 缂哄け鐨? parent_id-only 鏁版嵁涓嬫紡鎺変腑闂翠唬鐞嗐??
- 褰撴棫椤圭洰瀵煎叆鏁版嵁鍙湁 `user_infos.parent_id` 鍏崇郴銆佹病鏈? `family_tree` 鍜? `agent_descendants` 鏃讹紝`/api/front/positions/summary?target_id=...` 浠嶅簲杩斿洖褰撳墠浠ｇ悊鍒扮洰鏍囦唬鐞嗙殑瀹屾暣閾捐矾銆?
- 淇濇寔鎸佷粨姹囨?绘煡璇㈣寖鍥淬?佸垎椤电粨鏋勩?佽祫閲?/鎸佷粨鑱氬悎銆佺洰鏍囦唬鐞嗘潈闄愭牎楠屽拰鍓嶇瀛楁鍚嶄笉鍙橈紱鏈疆鍙ˉ `data.chain` 灞曠ず閾捐矾鏉ユ簮銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/PositionController.php`
  - `PositionController::summaryChain` 淇濈暀 `family_tree` 浼樺厛锛涗粎褰撶洰鏍囦唬鐞? `family_tree` 涓虹┖鏃跺洖閫?鍒? parent 閾俱??
  - 鏂板 `PositionController::parentSummaryChainIds`锛屾部 `user_infos.parent_id` 鍚戜笂鏀堕泦绁栧厛浠ｇ悊 ID锛屽苟浣跨敤 visited 闃叉鑴忔暟鎹惊鐜??
  - 鐩爣浠ｇ悊鍙鎬т粛鐢? `FrontLegacyData::userScopeIds($agentId, false, 1)` 绾︽潫锛屼笉鏀瑰彉鍘熸湁鏁版嵁闅旂瑙勫垯銆?
- `tests/Feature/FrontPositionSummaryChainScopeFallbackModuleTest.php`
  - 鏂板鎺ュ彛绾? parent_id-only 閾捐矾闂幆娴嬭瘯锛屾瀯閫? root -> level1 -> level2锛屾竻绌? `agent_descendants` 鍜? `family_tree`銆?
  - 楠岃瘉 `/api/front/positions/summary` 閽诲彇 level2 鏃? `data.chain` 杩斿洖 root -> level1 -> level2銆?
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontPositionSummaryChainScopeFallbackModuleTest.php` 棣栨鏈夋晥澶辫触锛宍data.chain` 瀹為檯涓? `[root, level2]`锛屾湡鏈涗负 `[root, level1, level2]`锛岃瘉鏄庢棫 `summaryChain` 鍦? parent_id-only 澶氬眰浠ｇ悊鏍戜笅婕忔帀涓棿鑺傜偣銆?
- GREEN锛歚PositionController::summaryChain` 澧炲姞 `parentSummaryChainIds` 鍥為??鍚庯紝鍚屼竴涓氬姟鏂█閫氳繃銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 181 鑺傘??

### 褰撳墠璇佹嵁
- `PositionController::summaryChain` 宸插吋瀹? `family_tree` 涓? `user_infos.parent_id` 涓ょ閾捐矾鏉ユ簮銆?
- `PositionController::parentSummaryChainIds` 鍙湪 `family_tree` 涓虹┖鏃惰Е鍙戯紝涓嶆敼鍙樻棫閾捐矾浼樺厛绾с??
- `FrontPositionSummaryChainScopeFallbackModuleTest` 瑕嗙洊 parent_id-only 鍏崇郴涓嬬殑鐪熷疄鐧诲綍鎬併?佺湡瀹炵敤鎴疯〃鍜岀湡瀹炲墠鍙版寔浠撴眹鎬绘帴鍙ｃ??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鎸佷粨姹囨?昏仛鍚堛?佷氦鏄撴槑缁嗐?佺洿灞炰唬鐞嗘眹鎬汇?佹寔浠?/骞充粨 scope銆佷剑閲戠粺璁°?佸墠绔瓧娈垫槧灏勬垨鏁版嵁搴撶粨鏋勩??
- `FrontLegacyData::userScopeIds` 浠嶆槸鍓嶅彴鎸佷粨妯″潡鐨勬暟鎹寖鍥村叆鍙ｏ紱鍚庣画濡傚彂鐜板叾瀹冩寔浠撻摼璺垨寮瑰眰浠嶆墜鍐? `family_tree`锛屽簲缁х画鎸夌嫭绔? RED/GREEN 琛ラ綈銆?
- 褰撳墠 MySQL `127.0.0.1:3307` 宸叉仮澶嶈繛閫氾紱鍚庣画缁х画鐢ㄧ湡瀹炴暟鎹簱浜嬪姟琛ュ墿浣欐ā鍧楁祴璇曘??
## 182. 2026-07-09 鍓嶅彴鏃т唬鐞? parentPath 閾捐矾 parent_id 鍏滃簳闂幆

### 鏈澶勭悊鐩爣
- 琛ラ綈 `AgentController::getParentPath` 鐨勬棫浠ｇ悊灞傜骇璺緞鍏滃簳锛岄伩鍏? `user/proxy/parentPath` 鍦? `family_tree` 缂哄け鐨? parent_id-only 澶氬眰浠ｇ悊鏍戜笅婕忔帀涓棿浠ｇ悊銆?
- 褰撴棫椤圭洰瀵煎叆鏁版嵁鍙湁 `user_infos.parent_id` 鍏崇郴銆佹病鏈? `family_tree` 鍜? `agent_descendants` 鏃讹紝褰撳墠浠ｇ悊鏌ョ湅涓嬬骇浠ｇ悊璺緞浠嶅簲杩斿洖 root -> level1 -> target 鐨勫畬鏁? HTML 鑺傜偣閾俱??
- 淇濇寔鍙鎬ф牎楠屻?佸搷搴斿瓧娈? `path/tree`銆丩ayui 浜嬩欢鍚嶃?侀鑹叉槧灏勫拰鏃ц矾鐢变笉鍙橈紱鏈疆鍙ˉ璺緞 ID 鏉ユ簮銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/AgentController.php`
  - `AgentController::getParentPath` 淇濈暀 `family_tree` 浼樺厛锛涗粎褰撶洰鏍囩敤鎴? `family_tree` 涓虹┖鏃跺洖閫?鍒? parent 閾俱??
  - 鏂板 `AgentController::parentPathIds`锛屾部 `user_infos.parent_id` 鍚戜笂鏀堕泦绁栧厛 ID锛屽苟浣跨敤 visited 闃叉鑴忔暟鎹惊鐜??
  - 褰撳墠浠ｇ悊鏄惁鍙鐩爣鐢ㄦ埛浠嶇敱 `FrontLegacyData::userScopeIds` 闂存帴绾︽潫锛屼笉鏀瑰彉鍘熸湁鏉冮檺杈圭晫銆?
- `tests/Feature/FrontAgentParentPathScopeFallbackModuleTest.php`
  - 鏂板鏃ц矾鐢辨帴鍙ｇ骇 parent_id-only 閾捐矾闂幆娴嬭瘯锛屾瀯閫? root -> level1 -> level2锛屾竻绌? `agent_descendants` 鍜? `family_tree`銆?
  - 楠岃瘉 `POST /user/proxy/parentPath` 杩斿洖鐨? `data.tree` 鑺傜偣 ID 涓? root -> level1 -> level2銆?
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentParentPathScopeFallbackModuleTest.php` 棣栨澶辫触锛宍data.tree` 瀹為檯涓? `[root, level2]`锛屾湡鏈涗负 `[root, level1, level2]`锛岃瘉鏄庢棫 `getParentPath` 鍦? parent_id-only 澶氬眰浠ｇ悊鏍戜笅婕忔帀涓棿鑺傜偣銆?
- GREEN锛歚AgentController::getParentPath` 澧炲姞 `parentPathIds` 鍥為??鍚庯紝鍚屼竴涓氬姟鏂█閫氳繃銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 182 鑺傘??

### 褰撳墠璇佹嵁
- `AgentController::getParentPath` 宸插吋瀹? `family_tree` 涓? `user_infos.parent_id` 涓ょ璺緞鏉ユ簮銆?
- `AgentController::parentPathIds` 鍙湪 `family_tree` 涓虹┖鏃惰Е鍙戯紝涓嶆敼鍙樻棫閾捐矾浼樺厛绾с??
- `FrontAgentParentPathScopeFallbackModuleTest` 瑕嗙洊 parent_id-only 鍏崇郴涓嬬殑鐪熷疄鐧诲綍鎬併?佺湡瀹炵敤鎴疯〃鍜屾棫鍓嶅彴浠ｇ悊璺緞鎺ュ彛銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊鍒楄〃鑼冨洿銆佷唬鐞?/瀹㈡埛鏄庣粏銆佺瓑绾х‘璁ゃ?佷剑閲戣浆璐︺?佸墠绔摼璺覆鏌? JS銆侀棴鍖呰〃閲嶅缓鎴栨暟鎹簱缁撴瀯銆?
- `FrontLegacyData::userScopeIds` 浠嶆槸鍓嶅彴浠ｇ悊鍙鎬у叆鍙ｏ紱鍚庣画濡傚彂鐜板叾瀹冩棫浠ｇ悊寮瑰眰浠嶆墜鍐? `family_tree`锛屽簲缁х画鎸夌嫭绔? RED/GREEN 琛ラ綈銆?
- 褰撳墠 MySQL `127.0.0.1:3307` 宸叉仮澶嶈繛閫氾紱鍚庣画缁х画鐢ㄧ湡瀹炴暟鎹簱浜嬪姟琛ュ墿浣欐ā鍧楁祴璇曘??
## 183. 2026-07-09 鍓嶅彴鏃т剑閲戣浆璐﹀啓鍏ョ骇闂幆

### 鏈澶勭悊鐩爣
- 琛ラ綈鏃у墠鍙? `user/proxy/directUserCommTrans` 鍦ㄧ湡瀹? DB 涓嬬殑鎴愬姛/鎷掔粷鍐欏叆绾ч棴鐜祴璇曘??
- 楠岃瘉 parent_id-only 鐩村睘鐩爣鍦ㄧ己灏? `agent_descendants` 鏃朵粛鍙畬鎴愯浆璐︼紝骞跺悓姝ユ洿鏂拌浆鍑烘柟/鎺ユ敹鏂? `user_infos.total_funds`銆?
- 楠岃瘉闈炵洿灞炵洰鏍囪鎷掔粷鏃朵笉鍙樻洿浣欓銆佷笉鍐欏叆 `commission_records`锛岄伩鍏嶈法灞傜骇鎴栧閮ㄧ敤鎴疯鏃ф帴鍙ｈ浆璐︺??
- 琛ラ綈鏃ф帴鍙ｆ垚鍔熷啓鍏ユ椂鐨勫璁″娉紝淇濊瘉 DBCT/WBCT 涓ゆ潯娴佹按閮借褰曠敤鎴锋彁浜ょ殑 `remark` 鍒? `manual_reason` 鍜? `remarks`銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/AgentController.php`
  - `directUserCommTrans` 鏂板璇诲彇 `remark` 鍙傛暟銆?
  - DBCT 鎺ユ敹鏂瑰叆璐︽祦姘翠笌 WBCT 褰撳墠浠ｇ悊鍑鸿处娴佹按閮藉啓鍏? `manual_reason`銆?
  - DBCT/WBCT `remarks` 淇濈暀鏃ц鍗曞彿鍓嶇紑锛屽苟鍦ㄥ瓨鍦ㄥ娉ㄦ椂杩藉姞鐢ㄦ埛鎻愪氦鐨勫娉ㄥ唴瀹广??
- `tests/Feature/FrontAgentDirectTransferWriteClosureModuleTest.php`
  - 鏂板鏃ц矾鐢卞啓鍏ョ骇鎴愬姛鏍蜂緥锛屾瀯閫? parent_id-only 鐩村睘瀹㈡埛锛岄獙璇佷綑棰濇墸澧炪?丏BCT/WBCT 鍙屾祦姘淬?佹璐熶剑閲戦噾棰濄?佺埗瀛? ID 鍜屽娉ㄥ瓧娈点??
  - 鏂板鏃ц矾鐢辨嫆缁濇牱渚嬶紝鏋勯?犻棿鎺ュ鎴凤紝楠岃瘉 `NOTALLOW` 涓斾綑棰濆拰娴佹按鍧囨棤鍐欏叆銆?
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentDirectTransferWriteClosureModuleTest.php` 棣栨澶辫触锛宍manual_reason` 瀹為檯涓虹┖瀛楃涓诧紝鏈熸湜涓虹敤鎴锋彁浜ょ殑 `legacy direct transfer write closure`锛岃瘉鏄庢棫 `directUserCommTrans` 鎴愬姛杞处鍚庢病鏈夊畬鏁磋褰曞璁″娉ㄣ??
- GREEN锛歚directUserCommTrans` 灏? `remark` 鍐欏叆 DBCT/WBCT 涓ゆ潯 `commission_records` 鍚庯紝鎴愬姛鍐欏叆鏍蜂緥鍜屾嫆缁濇棤鍐欏叆鏍蜂緥鍧囬?氳繃銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 183 鑺傘??

### 褰撳墠璇佹嵁
- `FrontAgentDirectTransferWriteClosureModuleTest` 瑕嗙洊鐪熷疄鐧诲綍鎬併?佺湡瀹? `user_infos` 浣欓鏇存柊銆佺湡瀹? `commission_records` 鍐欏叆鍜屾棫璺敱 `POST /user/proxy/directUserCommTrans`銆?
- 鎴愬姛璺緞宸查獙璇? parent_id-only 鐩村睘鐩爣鍙浆璐︼紝骞跺啓鍏ヤ竴姝ｄ竴璐? DBCT/WBCT 娴佹按銆?
- 鎷掔粷璺緞宸查獙璇侀棿鎺ョ洰鏍囪繑鍥? `NOTALLOW`锛屼笖涓嶄細鍙樻洿浣欓鎴栧啓鍏ヨ浆璐︽祦姘淬??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩瀵嗙爜鏍￠獙銆佷綑棰濅笉瓒虫牎楠屻?佽浆璐﹂噾棰濇牎楠屻?佹棫鍓嶅彴琛ㄥ崟缁撴瀯銆佺幇浠? `/api/front/commissions/transfers`銆佹暟鎹簱缁撴瀯鎴栧叡浜綔鐢ㄥ煙瀹炵幇銆?
- 鏃ф帴鍙ｅ紓甯告崟鑾蜂粛淇濇寔鍘熸湁 `MT4_data_no_sync` 鍝嶅簲锛涘鍚庣画瑕佺粏鍖栧紓甯哥被鍨嬶紝搴旀寜鐙珛 RED/GREEN 瑕嗙洊浜嬪姟澶辫触鍦烘櫙銆?
## 184. 2026-07-09 鍓嶅彴杩斾剑璁＄畻鏈嶅姟鐪熷疄璁㈠崟绾? parent_id-only 閲戦闂幆

### 鏈澶勭悊鐩爣
- 琛ラ綈 `CommissionService::calculateRealTimeCommission` 涓? `CommissionService::calculateSettlement` 鐨勭湡瀹炶鍗曠骇璁＄畻鏍蜂緥銆?
- 楠岃瘉 parent_id-only 涓夊眰浠ｇ悊鏍? root agent -> sub agent -> customer 鍦ㄧ己灏? `agent_descendants` 涓? `family_tree` 鏃讹紝褰撳墠浠ｇ悊鍙绠楄嚜宸变笌閾捐矾涓嬩竴鑺傜偣鐨勪剑閲戠巼宸銆?
- 閬垮厤涓婄骇浠ｇ悊鎸夆?滃綋鍓嶄唬鐞嗕剑閲戠巼 - 鏈?缁堝鎴蜂剑閲戠巼鈥濊绠楋紝鎶婄洿灞炰笅绾т唬鐞嗗簲寰楀樊棰濅竴骞惰鍏ヤ笂绾у疄鏃惰繑浣ｆ垨缁撶畻璁板綍銆?
- 瑕嗙洊瀹炴椂杩斾剑閲戦鍜岀粨绠楀啓鍏? `commission_records` 涓ゆ潯闂幆銆?

### 鏈鍙樻洿鏂囦欢
- `app/Services/CommissionService.php`
  - `calculateRealTimeCommission` 鏀逛负閫氳繃鍏变韩鐨勯摼璺樊棰濊绠楁柟娉曡幏鍙栨瘡绗旇鍗曞綋鍓嶄唬鐞嗗簲寰楅噾棰濄??
  - `calculateSettlement` 鏀逛负澶嶇敤鍚屼竴閾捐矾宸璁＄畻鏂规硶锛屽啓鍏ョ粨绠楄褰曟椂閲戦涓庡疄鏃惰繑浣ｅ彛寰勪竴鑷淬??
  - 鏂板 `commissionAmountForTrade`锛屾部 `family_tree` 鎴? parent 閾炬壘鍒板綋鍓嶄唬鐞嗗悗鐨勪笅涓?鑺傜偣锛屽苟鎸夆?滃綋鍓嶄唬鐞嗕剑閲戠巼 - 涓嬩竴鑺傜偣浣ｉ噾鐜団?濊绠楅噾棰濄??
  - 褰撲唬鐞嗕笉瀛樺湪銆佷氦鏄撶敤鎴蜂笉瀛樺湪鎴栧綋鍓嶄唬鐞嗕笉鍦ㄨ鍗曢摼璺腑鏃惰繑鍥? 0锛屼笉鎵╁ぇ鍙鑼冨洿銆?
- `tests/Feature/FrontCommissionServiceOrderCalculationClosureModuleTest.php`
  - 鏂板瀹炴椂杩斾剑鐪熷疄璁㈠崟鏍蜂緥锛屾瀯閫? parent_id-only root -> sub-agent -> customer 鍜? 1 鎵嬫湭骞充粨璁㈠崟锛岄獙璇? root 鍙緱鍒? 1.00 杩斾剑銆?
  - 鏂板缁撶畻鍐欏叆鏍蜂緥锛屾瀯閫? 1 鎵嬪凡骞充粨鏈粨绠楄鍗曪紝楠岃瘉 `calculateSettlement` 鍐欏叆 root 鐨? `commission_records.commission_amount=1.00`銆乣real_amount=1.00`銆乣agent_volume=1.00`銆?
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontCommissionServiceOrderCalculationClosureModuleTest.php` 棣栨澶辫触锛屽疄鏃惰繑浣? `total` 鍜岀粨绠楄褰? `commission_amount` 閮藉疄闄呬负 `3.0`锛屾湡鏈涗负 `1.0`锛岃瘉鏄庢棫鏈嶅姟鎶婃渶缁堝鎴峰樊棰濆叏閮ㄧ畻缁? root 浠ｇ悊銆?
- GREEN锛歚CommissionService` 鏀逛负鎸夐摼璺笅涓?鑺傜偣浣ｉ噾鐜囪绠楀悗锛屽疄鏃惰繑浣ｉ噾棰濆拰缁撶畻鍐欏叆閲戦鍧囪浆涓? `1.0`銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 184 鑺傘??

### 褰撳墠璇佹嵁
- `FrontCommissionServiceOrderCalculationClosureModuleTest` 瑕嗙洊鐪熷疄 `user_infos`銆佺湡瀹? `user_trades`銆佺湡瀹? `group_configs`銆佺湡瀹? `spread_configs` 鍜岀湡瀹? `commission_records` 鍐欏叆銆?
- `CommissionService::calculateRealTimeCommission` 涓? `CommissionService::calculateSettlement` 鐜板湪鍏变韩鍚屼竴閫愮骇宸鍙ｅ緞锛岄伩鍏嶅疄鏃堕噾棰濆拰缁撶畻閲戦涓嶄竴鑷淬??
- parent_id-only 浠ｇ悊鏍戜笅涓嶄緷璧? `agent_descendants` 鍗冲彲鎵惧埌璁㈠崟閾捐矾涓殑涓嬩竴鑺傜偣浣ｉ噾鐜囥??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `orderCommissionDetails`銆佸悗鍙扮粨绠楀鏍搞?乣settleCommission`銆佷氦鏄撹鍗? settlement_status 鏇存柊銆佸墠绔繑浣ｅ垪琛ㄥ瓧娈点?佹暟鎹簱缁撴瀯鎴栫偣宸厤缃鐞嗐??
- 褰撳墠缁撶畻浠嶄繚鎸佸師鏈夎仛鍚堣褰曞啓鍏ョ瓥鐣ワ紱濡傚悗缁鏀规垚閫愯鍗曠粨绠楄褰曟垨鍥炲啓璁㈠崟缁撶畻鐘舵?侊紝搴旀寜鐙珛 RED/GREEN 缁х画琛ラ綈銆?
## 185. 2026-07-09 鍓嶅彴瀹㈡埛缁勫埆鍙樻洿鐢宠鍐欏叆杈圭晫闂幆

### 鏈澶勭悊鐩爣
- 琛ラ綈鍓嶅彴 `AgentController::groupChange` 鐨勭湡瀹? DB 鍐欏叆绾ч棴鐜祴璇曪紝纭繚瀹㈡埛缁勫埆鍙樻洿鐢宠鍙厑璁告彁浜ゆ櫘閫氬鎴枫??
- 楠岃瘉 parent_id-only 鐩村睘瀹㈡埛鍦ㄧ己灏? `agent_descendants` 鏃朵粛鍙彁浜よ浆缁勭敵璇凤紝骞跺啓鍏? `trans_apply_logs` 鐨勭洰鏍囩敤鎴枫?佸師缁勩?佺洰鏍囩粍銆佺敵璇蜂汉鍜岀敵璇峰師鍥犮??
- 楠岃瘉鐩村睘涓嬬骇浠ｇ悊涓嶈兘琚綋浣滃鎴锋彁浜ょ粍鍒彉鏇寸敵璇凤紝鎷掔粷鏃朵笉鍐欏叆 `trans_apply_logs`锛岄伩鍏嶄唬鐞嗚处鍙疯繘鍏ュ鎴疯浆缁勫鏍告祦銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/AgentController.php`
  - `groupChange` 鍦ㄧ洰鏍囩敤鎴峰瓨鍦ㄥ悗澧炲姞 `account_type=2` 闄愬埗锛岄潪鏅?氬鎴风洿鎺ヨ繑鍥炴潈闄愭嫆缁濄??
  - 淇濇寔鐩爣缁勫埆鏍￠獙銆佷唬鐞嗘爲鍙鎬ф牎楠屻?佺敵璇峰瓧娈靛啓鍏ュ拰鏃у吋瀹瑰叆鍙ｅ鐢ㄥ叧绯讳笉鍙樸??
- `tests/Feature/FrontAgentGroupChangeWriteClosureModuleTest.php`
  - 鏂板鐩村睘瀹㈡埛鎴愬姛鐢宠鏍蜂緥锛岃鐩栫湡瀹? `user_infos`銆乣user_logins`銆乣group_configs` 鍜? `trans_apply_logs` 鍐欏叆銆?
  - 鏂板鐩村睘浠ｇ悊鎷掔粷鏍蜂緥锛岄獙璇佽繑鍥? `ResponseCode::PERMISSION_DENIED` 涓旀棤鐢宠璁板綍鍐欏叆銆?
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentGroupChangeWriteClosureModuleTest.php` 棣栨澶辫触锛岀洿灞炰唬鐞嗚浆缁勭敵璇峰疄闄呰繑鍥? `1000`锛屾湡鏈? `4006`锛岃瘉鏄庢棫 `groupChange` 鍙牎楠屼唬鐞嗘爲鑼冨洿锛屾病鏈夐檺鍒剁洰鏍囧繀椤绘槸鏅?氬鎴枫??
- GREEN锛歚groupChange` 澧炲姞 `account_type=2` 杈圭晫鍚庯紝鐩村睘瀹㈡埛鎴愬姛鍐欏叆涓庣洿灞炰唬鐞嗘嫆缁濇棤鍐欏叆鏍蜂緥鍧囬?氳繃銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 185 鑺傘??

### 褰撳墠璇佹嵁
- `FrontAgentGroupChangeWriteClosureModuleTest` 瑕嗙洊鐪熷疄鐧诲綍鎬併?佺湡瀹? parent_id-only 鍏崇郴銆佺湡瀹炲鎴风粍鍒厤缃拰 `trans_apply_logs` 鍐欏叆缁撴灉銆?
- 鎴愬姛璺緞宸查獙璇佹櫘閫氬鎴风敵璇疯褰曞啓鍏? `origin_group_id`銆乣group_id`銆乣group_name`銆乣applicant_id`銆乣applicant_name` 鍜? `apply_reason`銆?
- 鎷掔粷璺緞宸查獙璇佷笅绾т唬鐞嗚处鍙疯繑鍥炴潈闄愭嫆缁濓紝涓斾笉浼氱敓鎴愬鎴疯浆缁勭敵璇疯褰曘??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩杞粍瀹℃牳閫氳繃/鎷掔粷娴佺▼銆佸悗鍙板鏍搁〉闈€?佹棫鍓嶅彴琛ㄥ崟缁撴瀯銆佸鎴峰垪琛ㄥ彲瑙佽寖鍥淬?佸鎴风粍鍒笅鎷夋潵婧愭垨鏁版嵁搴撶粨鏋勩??
- `changeDirectCustGroupInfo` 缁х画澶嶇敤 `groupChange`锛屾棫瀛楁鏄犲皠鍏ュ彛鑷劧缁ф壙鍚屼竴璐﹀彿绫诲瀷杈圭晫锛涘鍚庣画琛ユ棫璺敱閫傞厤鍣紝搴旀寜鐙珛 RED/GREEN 瑕嗙洊璺敱灞傘??
## 186. 2026-07-09 鍓嶅彴瀹㈡埛杞粍鐩爣缁勫埆绫诲埆闂幆

### 鏈澶勭悊鐩爣
- 琛ラ綈鍓嶅彴瀹㈡埛缁勫埆鍙樻洿鐢宠鐨勭洰鏍囩粍鍒被鍒竟鐣岋紝纭繚瀹㈡埛杞粍鍙兘鎻愪氦鍒? `group_configs.category=2` 鐨勫鎴风粍銆?
- 楠岃瘉鐜颁唬 `/api/front/agents/group-change-applications` 鍗充娇浼犲叆鍚敤鐨勪唬鐞嗙粍 ID锛屼篃蹇呴』杩斿洖鍙傛暟鏍￠獙澶辫触涓斾笉鍐欏叆 `trans_apply_logs`銆?
- 楠岃瘉鏃? Web `changeDirectCustGroupEdit` 閫氳繃 `grpName` 鍛戒腑浠ｇ悊缁勫悕鏃讹紝鍚屾牱涓嶈兘鍐欏叆瀹㈡埛杞粍鐢宠锛岄伩鍏嶆棫鍏ュ彛缁曡繃鐜颁唬涓嬫媺閫夐」闄愬埗銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/AgentController.php`
  - `groupChange` 鍦ㄧ‘璁ょ洰鏍囩粍鍒瓨鍦ㄤ笖鍚敤鍚庯紝缁х画鏍￠獙 `group_configs.category=2`銆?
  - 缂哄皯 `category` 瀛楁鐨勬棫杩佺Щ鐜浠嶄繚鐣欏師鏈夊惎鐢ㄧ粍鍏煎閫昏緫锛屼笉鎵╁ぇ杩佺Щ鐜椋庨櫓銆?
- `tests/Feature/FrontAgentGroupChangeGroupCategoryClosureModuleTest.php`
  - 鏂板鐜颁唬鎺ュ彛浠ｇ悊缁? ID 鎷掔粷鏍蜂緥锛岃鐩栫湡瀹? `user_infos`銆乣user_logins`銆乣group_configs` 鍜? `trans_apply_logs`銆?
  - 鏂板鏃? `user/cust/change/group_edit` 浠ｇ悊缁勫悕鎷掔粷鏍蜂緥锛岀‘璁ゆ棫鍝嶅簲涓? `FAIL` 涓旀棤鐢宠璁板綍鍐欏叆銆?
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentGroupChangeGroupCategoryClosureModuleTest.php` 棣栨澶辫触锛岀幇浠ｆ帴鍙ｅ疄闄呰繑鍥? `1000`銆佹棫鍏ュ彛瀹為檯杩斿洖 `SUCCESS`锛岃瘉鏄庢棫閫昏緫鍙牎楠岀粍鍒惎鐢紝娌℃湁闄愬埗鐩爣缁勫埆蹇呴』鏄鎴风粍銆?
- GREEN锛歚groupChange` 澧炲姞 `group_configs.category=2` 鏍￠獙鍚庯紝鐜颁唬鎺ュ彛鍜屾棫鍏ュ彛鍧囨嫆缁濅唬鐞嗙粍鐩爣锛屽苟涓斾笉鍐欏叆 `trans_apply_logs`銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 186 鑺傘??

### 褰撳墠璇佹嵁
- `FrontAgentGroupChangeGroupCategoryClosureModuleTest` 瑕嗙洊鐜颁唬 API 鍜屾棫 Web 鍏ュ彛涓ょ鎻愪氦璺緞銆?
- 鎴愬姛鎷掔粷璺緞宸查獙璇佸惎鐢ㄧ殑浠ｇ悊缁勪笉鑳借繘鍏ュ鎴疯浆缁勭敵璇锋祦锛屾嫆缁濆悗娌℃湁浜х敓 `trans_apply_logs` 璁板綍銆?
- 绗? 185 鑺傛櫘閫氬鎴风洰鏍囪处鍙疯竟鐣屼笌鏈妭瀹㈡埛缁勫埆绫诲埆杈圭晫鍏卞悓鏀剁揣瀹㈡埛杞粍鍐欏叆闈€??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩瀹㈡埛缁勫埆涓嬫媺灞曠ず銆佸悗鍙扮粍鍒厤缃? CRUD銆佽浆缁勫鏍搁?氳繃鍚庡疄闄呮洿鏂板鎴风粍鍒殑鍚庡彴娴佺▼鎴栨暟鎹簱缁撴瀯銆?
- `changeDirectCustGroupEdit` 浠嶄繚鎸佹棫 `CLASSINVALID` 涓? `SUCCESS/FAIL` 鍝嶅簲缁撴瀯锛涘鍚庣画鍙戠幇鏃ч〉闈㈣繕鏈夊叾瀹冨娉ㄥ瓧娈靛悕锛屽簲鎸夌嫭绔? RED/GREEN 琛ユ棫鍙傛暟鏄犲皠銆?
## 187. 2026-07-09 鏃у墠鍙? group_edit 鐢宠鍘熷洜瀛楁鍐欏叆闂幆

### 鏈澶勭悊鐩爣
- 琛ラ綈鏃? Web `user/cust/change/group_edit` 鐨勭敵璇峰師鍥犲瓧娈垫槧灏勶紝閬垮厤鏃ц〃鍗曟彁浜? `trans_apply_reason` 鏃跺啓鍏? `trans_apply_logs.apply_reason` 涓虹┖銆?
- 楠岃瘉鏃у叆鍙ｄ粛鎸? `grpName` 鏌ユ壘瀹㈡埛缁勩?佹寜 `userId` 瀹氫綅鐩村睘瀹㈡埛锛屽苟鎶婃棫瀛楁鐢宠鍘熷洜瀹屾暣钀藉簱銆?
- 淇濇寔鐜颁唬 `groupChange` 鍙傛暟缁撴瀯鍜屾棫 `SUCCESS/FAIL` 鍝嶅簲缁撴瀯涓嶅彉锛屽彧琛ユ棫瀛楁鍚嶅吋瀹广??

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/AgentController.php`
  - `changeDirectCustGroupEdit` 鐨? `reason` 鍚堝苟閫昏緫鎵╁睍涓? `reason -> apply_reason -> trans_apply_reason`銆?
  - 鍐欏叆浠嶅鐢? `groupChange`锛岀户缁户鎵胯处鍙风被鍨嬨?佸鎴风粍绫诲埆鍜屼唬鐞嗘爲鍙鎬ц竟鐣屻??
- `tests/Feature/FrontAgentGroupChangeLegacyReasonClosureModuleTest.php`
  - 鏂板鏃ц矾鐢辩湡瀹炲啓鍏ユ牱渚嬶紝鎻愪氦 `trans_apply_reason` 鍚庨獙璇? `trans_apply_logs.apply_reason` 淇濆瓨鍘熷鍘熷洜銆?
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentGroupChangeLegacyReasonClosureModuleTest.php` 棣栨澶辫触锛屾暟鎹簱涓浉鍚岀敵璇疯褰曠殑 `apply_reason` 瀹為檯涓虹┖锛岃瘉鏄庢棫 `changeDirectCustGroupEdit` 鍙鍙? `reason`锛屼涪澶辨棫瀛楁 `trans_apply_reason`銆?
- GREEN锛氭棫鍏ュ彛鍘熷洜瀛楁鍚堝苟鍚庯紝鍚屼竴鏃ц矾鐢卞啓鍏ユ牱渚嬮?氳繃锛宍apply_reason` 姝ｇ‘淇濆瓨鏃у瓧娈靛?笺??
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 187 鑺傘??

### 褰撳墠璇佹嵁
- `FrontAgentGroupChangeLegacyReasonClosureModuleTest` 瑕嗙洊鐪熷疄鐧诲綍鎬併?佺湡瀹? parent_id-only 瀹㈡埛銆佺湡瀹炲鎴风粍鍚嶆煡鎵惧拰 `trans_apply_logs` 鍐欏叆銆?
- 鏃? `group_edit` 缁х画閫氳繃 `groupChange` 鎵ц缁熶竴鏍￠獙锛屽洜姝ょ 185銆?186 鑺傛柊澧炵殑璐﹀彿绫诲瀷鍜屽鎴风粍绫诲埆杈圭晫浠嶆湁鏁堛??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鏃ч〉闈? HTML銆佺幇浠? `/api/front/agents/group-change-applications`銆佺粍鍒笅鎷夋潵婧愩?佽浆缁勫鏍稿悗鍙版垨鏁版嵁搴撶粨鏋勩??
- 濡傚悗缁彂鐜版棫椤圭洰杩樻湁 `remark`銆乣memo` 绛夐澶栧師鍥犲瓧娈碉紝搴旂户缁寜鐙珛 RED/GREEN 鎵╁睍瀛楁鏄犲皠銆?
## 188. 2026-07-09 鍓嶅彴瀹㈡埛杞粍鐢宠浜鸿处鍙风被鍨嬭竟鐣岄棴鐜?

### 鏈澶勭悊鐩爣
- 琛ラ綈 `AgentController::groupChange` 鐨勭敵璇蜂汉璐﹀彿绫诲瀷杈圭晫锛岀‘淇濆彧鏈変唬鐞嗚处鍙? `account_type=1` 鑳芥彁浜ゅ鎴风粍鍒彉鏇寸敵璇枫??
- 楠岃瘉鏅?氬鎴峰嵆浣挎妸 `target_user_id` 鍐欐垚鑷繁锛屼篃涓嶈兘鍒╃敤 `canViewUser` 鍚? ID 鍒嗘敮鑷姪鎻愪氦杞粍鐢宠銆?
- 閬垮厤鏅?氱敤鎴锋ā鍧楄秺鏉冭繘鍏ヤ唬鐞嗗晢瀹㈡埛绠＄悊鍐欏叆娴侊紝鎷掔粷鏃朵笉鍐欏叆 `trans_apply_logs`銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/AgentController.php`
  - `groupChange` 鍦ㄨВ鏋愬綋鍓嶇櫥褰曡处鍙峰悗澧炲姞鐢宠浜? `account_type=1` 鏍￠獙銆?
  - 鍚庣画鐩爣鐢ㄦ埛蹇呴』涓烘櫘閫氬鎴枫?佺洰鏍囩粍鍒繀椤讳负瀹㈡埛缁勩?佺洰鏍囧鎴峰繀椤诲湪浠ｇ悊鏍戝唴鐨勮竟鐣屼繚鎸佷笉鍙樸??
- `tests/Feature/FrontAgentGroupChangeApplicantBoundaryClosureModuleTest.php`
  - 鏂板鏅?氬鎴疯嚜鎻愪氦杞粍鐢宠鎷掔粷鏍蜂緥锛岃鐩栫湡瀹? `user_logins`銆乣user_infos`銆乣group_configs` 鍜? `trans_apply_logs`銆?
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentGroupChangeApplicantBoundaryClosureModuleTest.php` 棣栨澶辫触锛屾櫘閫氬鎴疯嚜鎻愪氦杞粍鐢宠瀹為檯杩斿洖 `1000`锛屾湡鏈? `4006`锛岃瘉鏄庢棫 `groupChange` 娌℃湁闄愬埗鐢宠浜哄繀椤绘槸浠ｇ悊銆?
- GREEN锛歚groupChange` 澧炲姞鐢宠浜鸿处鍙风被鍨嬫牎楠屽悗锛屾櫘閫氬鎴疯嚜鎻愪氦琚潈闄愭嫆缁濅笖涓嶅啓鍏? `trans_apply_logs`銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 188 鑺傘??

### 褰撳墠璇佹嵁
- `FrontAgentGroupChangeApplicantBoundaryClosureModuleTest` 瑕嗙洊鏅?氬鎴风櫥褰曟?佷笅鐨勭湡瀹? API 璇锋眰鍜屾嫆缁濇棤鍐欏叆缁撴灉銆?
- 绗? 185-188 鑺傚叡鍚岃鐩栧鎴疯浆缁勫啓鍏ユ祦鐨勭敵璇蜂汉銆佺洰鏍囩敤鎴枫?佺洰鏍囩粍鍒拰鏃у瓧娈垫槧灏勮竟鐣屻??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鍓嶅彴鑿滃崟鏉冮檺銆佽鑹叉潈闄愬垎閰嶃?佸鎴风粍鍒笅鎷夊睍绀恒?佹棫椤甸潰妯℃澘銆佽浆缁勫鏍稿悗鍙版垨鏁版嵁搴撶粨鏋勩??
- 濡傛灉鍚庣画瑕佸湪璺敱鎴栦腑闂翠欢灞傝繘涓?姝ラ殧绂讳唬鐞嗕笓灞? API锛屽簲鎸夌嫭绔? RED/GREEN 瑕嗙洊鍓嶅彴瑙掕壊鏉冮檺涓庢帴鍙ｉ壌鏉冮摼璺??
## 189. 2026-07-09 鍓嶅彴瀹㈡埛杞粍鍒楄〃鐢宠浜鸿处鍙风被鍨嬭竟鐣岄棴鐜?

### 鏈澶勭悊鐩爣
- 琛ラ綈 `AgentController::groupChangeList` 鐨勮鍙栬竟鐣岋紝纭繚鍙湁浠ｇ悊璐﹀彿 `account_type=1` 鑳借鍙栧鎴疯浆缁勭敵璇峰垪琛ㄥ拰 `available_groups`銆?
- 楠岃瘉鏅?氬鎴峰嵆浣垮凡鏈夊巻鍙? `trans_apply_logs` 璁板綍锛屼篃涓嶈兘璁块棶浠ｇ悊鍟嗗鎴疯浆缁勫垪琛ㄦ帴鍙ｃ??
- 閬垮厤鏅?氱敤鎴锋ā鍧楄秺鏉冭鍙栦唬鐞嗗晢瀹㈡埛绠＄悊鍒楄〃閰嶇疆鍜屽鎴风粍鍊欓?夐」銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/AgentController.php`
  - `groupChangeList` 鍦ㄨВ鏋愬綋鍓嶇櫥褰曡处鍙峰悗澧炲姞 `account_type=1` 鏍￠獙銆?
  - `directCustChangeListSearch` 缁х画澶嶇敤 `groupChangeList`锛屽洜姝ゆ棫瀹㈡埛杞粍鎼滅储鍏ュ彛缁ф壙鍚屼竴璇诲彇杈圭晫銆?
- `tests/Feature/FrontAgentGroupChangeListApplicantBoundaryClosureModuleTest.php`
  - 鏂板鏅?氬鎴疯闂唬鐞嗚浆缁勫垪琛ㄦ嫆缁濇牱渚嬶紝棰勭疆鐪熷疄 `trans_apply_logs` 鍚庨獙璇佹帴鍙ｈ繑鍥炴潈闄愭嫆缁濄??
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentGroupChangeListApplicantBoundaryClosureModuleTest.php` 棣栨澶辫触锛屾櫘閫氬鎴疯闂? `/api/front/agents/group-changes` 瀹為檯杩斿洖 `1000`锛屾湡鏈? `4006`锛岃瘉鏄庢棫 `groupChangeList` 娌℃湁闄愬埗鐢宠浜哄繀椤绘槸浠ｇ悊銆?
- GREEN锛歚groupChangeList` 澧炲姞鐢宠浜鸿处鍙风被鍨嬫牎楠屽悗锛屾櫘閫氬鎴疯鍙栦唬鐞嗚浆缁勫垪琛ㄨ鏉冮檺鎷掔粷銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 189 鑺傘??

### 褰撳墠璇佹嵁
- `FrontAgentGroupChangeListApplicantBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹炲巻鍙茬敵璇疯褰曞拰浠ｇ悊杞粍鍒楄〃 API銆?
- 绗? 188 鑺傛敹绱у啓鍏ュ叆鍙ｏ紝绗? 189 鑺傛敹绱ц鍙栧叆鍙ｏ紝瀹㈡埛杞粍妯″潡鐨勬櫘閫氱敤鎴疯秺鏉冮潰鍚屾闂悎銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊姝ｅ父鍒楄〃鍒嗛〉銆佹棩鏈熺瓫閫夈?佸鎴风粍涓嬫媺鏉ユ簮銆佸墠绔〉闈㈡覆鏌撱?佹棫 Web 椤甸潰鎴栨暟鎹簱缁撴瀯銆?
- 濡傚悗缁户缁帹杩涗唬鐞嗕笓灞? API 涓棿浠跺寲锛屽簲鎶? `groupChangeList` 涓? `groupChange` 绾冲叆缁熶竴瑙掕壊/鑿滃崟鏉冮檺鍥炲綊銆?
## 190. 2026-07-09 鍓嶅彴浠ｇ悊绛夌骇鍊欓?夋帴鍙ｇ敵璇蜂汉杈圭晫闂幆

### 鏈澶勭悊鐩爣
- 琛ラ綈 `AgentController::getSubAgentsGrpIdList` 鐨勭敵璇蜂汉璐﹀彿绫诲瀷杈圭晫锛岄伩鍏嶆櫘閫氬鎴疯鍙栦唬鐞嗙瓑绾у?欓?? `agentList`銆?
- 楠岃瘉鐜颁唬 `/api/front/agents/direct-level-options` 鍜屾棫 Web `user/proxy/getSubAgentsGrpIdList` 涓や釜鍏ュ彛閮藉繀椤昏姹備唬鐞嗚处鍙? `account_type=1`銆?
- 閬垮厤鏅?氱敤鎴锋ā鍧楄秺鏉冭鍙栦唬鐞嗙瓑绾у悕绉板拰杩斾剑姣斾緥閰嶇疆銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/AgentController.php`
  - `getSubAgentsGrpIdList` 鍦ㄨ鍙? `agent_levels` 鍓嶅鍔犵櫥褰曟?佸拰 `account_type=1` 鏍￠獙銆?
  - 淇濇寔浠ｇ悊璐﹀彿姝ｅ父杩斿洖鏃у墠鍙板吋瀹? `agentList` 缁撴瀯锛屼笉鏀瑰彉鍊欓?夌瓑绾у瓧娈靛悕銆?
- `tests/Feature/FrontAgentLevelOptionsApplicantBoundaryClosureModuleTest.php`
  - 鏂板鐜颁唬鎺ュ彛鏅?氬鎴锋嫆缁濇牱渚嬨??
  - 鏂板鏃? Web 鍏ュ彛鏅?氬鎴锋嫆缁濇牱渚嬨??
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentLevelOptionsApplicantBoundaryClosureModuleTest.php` 棣栨澶辫触锛屼袱涓叆鍙ｅ搷搴斾腑 `code` 鍧囦负 `null`锛岃瘉鏄庢棫 `getSubAgentsGrpIdList` 鐩存帴杩斿洖 `agentList`锛屾病鏈夌櫥褰曟?佹垨浠ｇ悊韬唤杈圭晫銆?
- GREEN锛歚getSubAgentsGrpIdList` 澧炲姞鐧诲綍鎬佸拰浠ｇ悊璐﹀彿鏍￠獙鍚庯紝鏅?氬鎴疯闂幇浠ｅ拰鏃у叆鍙ｅ潎杩斿洖 `ResponseCode::PERMISSION_DENIED`銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 190 鑺傘??

### 褰撳墠璇佹嵁
- `FrontAgentLevelOptionsApplicantBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹? `agent_levels` 閰嶇疆鍜岀幇浠?/鏃т袱涓唬鐞嗙瓑绾у?欓?夊叆鍙ｃ??
- 绗? 190 鑺傛妸浠ｇ悊绛夌骇鍊欓?夎鍙栫撼鍏ヤ唬鐞嗕笓灞炶竟鐣岋紝鍜岀 188-189 鑺傚叡鍚屾敹绱ф櫘閫氱敤鎴疯闂唬鐞嗘ā鍧楃殑鍏ュ彛闈€??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊绛夌骇纭鍐欏叆銆佷唬鐞嗙瓑绾у垪琛ㄦ帓搴忋?佹棫鍓嶅彴瀛楁鍚嶃?佸悗鍙颁唬鐞嗙瓑绾ч厤缃垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画鍙户缁寜鍚屼竴妯″紡瀹¤ `subList`銆乣customerList`銆乣statistics`銆乣userLoginHistory` 绛変唬鐞嗕笓灞炶鎺ュ彛鐨勬櫘閫氱敤鎴疯竟鐣屻??
## 191. 2026-07-09 鍓嶅彴浠ｇ悊鐩村睘鍒楄〃鐢宠浜鸿竟鐣岄棴鐜?

### 鏈澶勭悊鐩爣
- 琛ラ綈 `AgentController::subList` 涓? `AgentController::customerList` 鐨勭敵璇蜂汉璐﹀彿绫诲瀷杈圭晫锛岀‘淇濆彧鏈変唬鐞嗚处鍙? `account_type=1` 鑳借鍙栫洿灞炰唬鐞嗗垪琛ㄥ拰鐩村睘瀹㈡埛鍒楄〃銆?
- 楠岃瘉鏅?氬鎴疯闂幇浠? `/api/front/agents/direct` 涓? `/api/front/agents/direct-customers` 鍧囪繑鍥? `ResponseCode::PERMISSION_DENIED`銆?
- 閬垮厤鏅?氱敤鎴锋ā鍧楄秺鏉冭鍙栦唬鐞嗗晢涓嬬骇浠ｇ悊銆佺洿灞炲鎴枫?佺粺璁￠捇鍙栧叆鍙ｅ拰瀹㈡埛鍙浆缁勫?欓?夐厤缃??

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/AgentController.php`
  - `subList` 鍦ㄨ鍙栦唬鐞嗘爲涓嬬骇浠ｇ悊鍓嶅鍔犵櫥褰曟?佸拰 `account_type=1` 鏍￠獙銆?
  - `customerList` 鍦ㄨ鍙栫洿灞炲鎴峰拰 `available_groups` 鍓嶅鍔犵櫥褰曟?佸拰 `account_type=1` 鏍￠獙銆?
  - `proxyListSearch` 涓? `directCustListSearch` 缁х画澶嶇敤瀵瑰簲鍒楄〃鏂规硶锛屽洜姝ゆ棫鍏煎鍏ュ彛缁ф壙鍚屼竴浠ｇ悊韬唤杈圭晫銆?
- `tests/Feature/FrontAgentMainListApplicantBoundaryClosureModuleTest.php`
  - 鏂板鏅?氬鎴疯闂洿灞炰唬鐞嗗垪琛ㄦ嫆缁濇牱渚嬨??
  - 鏂板鏅?氬鎴疯闂洿灞炲鎴峰垪琛ㄦ嫆缁濇牱渚嬨??
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentMainListApplicantBoundaryClosureModuleTest.php` 棣栨澶辫触锛屼袱涓垪琛ㄦ帴鍙ｅ疄闄呰繑鍥? `1000`锛屾湡鏈? `4006`锛岃瘉鏄庢棫 `subList` 鍜? `customerList` 鍙牎楠岀櫥褰曠敤鎴? ID锛屾病鏈夐檺鍒剁敵璇蜂汉蹇呴』鏄唬鐞嗐??
- GREEN锛歚subList` 涓? `customerList` 澧炲姞鐧诲綍鎬佸拰浠ｇ悊璐﹀彿鏍￠獙鍚庯紝鏅?氬鎴疯闂洿灞炰唬鐞嗗垪琛ㄥ拰鐩村睘瀹㈡埛鍒楄〃鍧囪繑鍥? `ResponseCode::PERMISSION_DENIED`銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 191 鑺傘??

### 褰撳墠璇佹嵁
- `FrontAgentMainListApplicantBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?佸拰鐜颁唬鐩村睘浠ｇ悊銆佺洿灞炲鎴峰垪琛? API銆?
- 绗? 190 鑺傛敹绱т唬鐞嗙瓑绾у?欓?夎鍙栵紝绗? 191 鑺傜户缁敹绱т唬鐞嗕富鍒楄〃璇诲彇锛屾櫘閫氬鎴疯繘鍏ヤ唬鐞嗗晢绠＄悊璇绘帴鍙ｇ殑瓒婃潈闈㈣繘涓?姝ラ棴鍚堛??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊璐﹀彿姝ｅ父鍒楄〃鍒嗛〉銆乣parent_id` 閽诲彇鍙鎬с?乣FrontLegacyData::userScopeIds`銆乣available_groups` 鐢熸垚銆佹棫鍓嶅彴椤甸潰妯℃澘鎴栨暟鎹簱缁撴瀯銆?
- 鍚庣画鍙户缁寜鍚屼竴妯″紡瀹¤ `statistics`銆乣userLoginHistory`銆佽祫閲戝拰浜ゆ槗鏄庣粏绛変唬鐞嗕笓灞炶鎺ュ彛鐨勬櫘閫氱敤鎴疯竟鐣屻??
## 192. 2026-07-09 鍓嶅彴浠ｇ悊缁熻鐢宠浜鸿竟鐣岄棴鐜?

### 鏈澶勭悊鐩爣
- 琛ラ綈 `AgentController::statistics` 鐨勭敵璇蜂汉璐﹀彿绫诲瀷杈圭晫锛岀‘淇濆彧鏈変唬鐞嗚处鍙? `account_type=1` 鑳借鍙栦唬鐞嗙粺璁℃暟鎹??
- 楠岃瘉鏅?氬鎴疯闂幇浠? `/api/front/agents/statistics` 杩斿洖 `ResponseCode::PERMISSION_DENIED`銆?
- 閬垮厤鏅?氱敤鎴锋ā鍧楄秺鏉冭鍙栦唬鐞嗕氦鏄撶粺璁°?佸眰绾х粺璁″拰浠ｇ悊鏍戞眹鎬讳俊鎭??

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/AgentController.php`
  - `statistics` 鍦ㄨ皟鐢? `FamilyTreeService::getAgentStats` 涓? `getSubAgentStats` 鍓嶅鍔犵櫥褰曟?佸拰 `account_type=1` 鏍￠獙銆?
  - 淇濇寔浠ｇ悊璐﹀彿姝ｅ父鐨? `date_from`銆乣date_to` 缁熻鍙傛暟鍜屽搷搴旂粨鏋勪笉鍙樸??
- `tests/Feature/FrontAgentStatisticsApplicantBoundaryClosureModuleTest.php`
  - 鏂板鏅?氬鎴疯闂唬鐞嗙粺璁℃嫆缁濇牱渚嬨??
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentStatisticsApplicantBoundaryClosureModuleTest.php` 棣栨澶辫触锛屼唬鐞嗙粺璁℃帴鍙ｅ疄闄呰繑鍥? `1000`锛屾湡鏈? `4006`锛岃瘉鏄庢棫 `statistics` 鍙牎楠岀櫥褰曠敤鎴? ID锛屾病鏈夐檺鍒剁敵璇蜂汉蹇呴』鏄唬鐞嗐??
- GREEN锛歚statistics` 澧炲姞鐧诲綍鎬佸拰浠ｇ悊璐﹀彿鏍￠獙鍚庯紝鏅?氬鎴疯闂? `/api/front/agents/statistics` 杩斿洖 `ResponseCode::PERMISSION_DENIED`銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 192 鑺傘??

### 褰撳墠璇佹嵁
- `FrontAgentStatisticsApplicantBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?佸拰鐜颁唬浠ｇ悊缁熻 API銆?
- 绗? 191 鑺傛敹绱т唬鐞嗕富鍒楄〃璇诲彇锛岀 192 鑺傜户缁敹绱т唬鐞嗙粺璁¤鍙栵紝鏅?氬鎴疯繘鍏ヤ唬鐞嗗晢绠＄悊璇绘帴鍙ｇ殑瓒婃潈闈㈣繘涓?姝ラ棴鍚堛??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊璐﹀彿姝ｅ父缁熻鍙ｅ緞銆佹棩鏈熺瓫閫夊弬鏁般?乣FamilyTreeService` 鑱氬悎閫昏緫銆佸墠绔粺璁″崱鐗囨垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画鍙户缁寜鍚屼竴妯″紡瀹¤ `userLoginHistory`銆佺敤鎴疯鎯呫?佽祫閲戝拰浜ゆ槗鏄庣粏绛変唬鐞嗕笓灞炶鎺ュ彛鐨勬櫘閫氱敤鎴疯竟鐣屻??
## 193. 2026-07-09 鍓嶅彴鐢ㄦ埛鐧诲綍鍘嗗彶鐢宠浜鸿竟鐣岄棴鐜?

### 鏈澶勭悊鐩爣
- 琛ラ綈 `AgentController::userLoginHistory` 涓? `AgentController::legacyLoginHistorySearch` 鐨勭敵璇蜂汉璐﹀彿绫诲瀷杈圭晫锛岀‘淇濆彧鏈変唬鐞嗚处鍙? `account_type=1` 鑳借鍙栧彲瑙佺敤鎴风櫥褰曞巻鍙层??
- 楠岃瘉鏅?氬鎴疯闂幇浠? `/api/front/users/login-history` 鍗充娇鏌ヨ鑷繁锛屼篃杩斿洖 `ResponseCode::PERMISSION_DENIED`銆?
- 楠岃瘉鏃? Web `user/cust/loginHistorySearch/{uid}` 鍦ㄦ櫘閫氬鎴峰凡鏈夌湡瀹炵櫥褰曟棩蹇楁椂浠嶈繑鍥炵┖琛ㄦ牸锛岄伩鍏嶆棫琛ㄦ牸鍏ュ彛娉勬紡鐧诲綍 IP銆佸湴鍖哄拰娴忚鍣ㄦ爣璇嗐??

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/AgentController.php`
  - `userLoginHistory` 鍦ㄥ彲瑙佹?у垽鏂拰璇诲彇 `user_login_logs` 鍓嶅鍔犵櫥褰曟?佸拰 `account_type=1` 鏍￠獙銆?
  - `legacyLoginHistorySearch` 鍦ㄦ棫琛ㄦ牸鏌ヨ鍓嶅鍔犲悓涓?浠ｇ悊璐﹀彿鏍￠獙锛涢潪浠ｇ悊鎴栨湭鐧诲綍鏃惰繑鍥炴棫鍏煎绌鸿〃鏍肩粨鏋勩??
  - 淇濇寔浠ｇ悊璐﹀彿姝ｅ父鏌ヨ涓嬬骇鐢ㄦ埛鐧诲綍鍘嗗彶銆佺幇浠ｅ搷搴旂粨鏋勫拰鏃? `rows/total` 鍝嶅簲缁撴瀯涓嶅彉銆?
- `tests/Feature/FrontUserLoginHistoryApplicantBoundaryClosureModuleTest.php`
  - 鏂板鏅?氬鎴疯闂幇浠ｇ櫥褰曞巻鍙叉嫆缁濇牱渚嬨??
  - 鏂板鏅?氬鎴疯闂棫鐧诲綍鍘嗗彶琛ㄦ牸绌虹粨鏋滄牱渚嬶紝棰勭疆鐪熷疄 `user_login_logs` 楠岃瘉涓嶄細娉勬紡銆?
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontUserLoginHistoryApplicantBoundaryClosureModuleTest.php` 棣栨澶辫触锛岀幇浠ｅ叆鍙ｅ疄闄呰繑鍥? `1000`锛屾棫鍏ュ彛 `total` 瀹為檯涓? `1`锛岃瘉鏄庢棫 `userLoginHistory` 鍜? `legacyLoginHistorySearch` 鍙緷璧? `canViewUser`锛屽厑璁告櫘閫氬鎴疯鍙栬嚜宸辩殑浠ｇ悊璇︽儏鐧诲綍鍘嗗彶鎺ュ彛銆?
- GREEN锛氫袱涓叆鍙ｅ鍔犱唬鐞嗚处鍙锋牎楠屽悗锛岀幇浠ｅ叆鍙ｈ繑鍥? `ResponseCode::PERMISSION_DENIED`锛屾棫鍏ュ彛杩斿洖 `rows=[]`銆乣total=0`銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 193 鑺傘??

### 褰撳墠璇佹嵁
- `FrontUserLoginHistoryApplicantBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹? `user_login_logs` 璁板綍銆佺幇浠? API 鍜屾棫 Web 琛ㄦ牸鍏ュ彛銆?
- 绗? 191-193 鑺傝繛缁敹绱т唬鐞嗕富鍒楄〃銆佺粺璁″拰鐧诲綍鍘嗗彶璇诲彇闈紝鏅?氬鎴蜂笉鑳藉啀閫氳繃浠ｇ悊璇︽儏閾捐矾璇诲彇浠ｇ悊鍟嗙鐞嗕俊鎭??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊璐﹀彿姝ｅ父鏌ョ湅涓嬬骇鐢ㄦ埛鐧诲綍鍘嗗彶銆佺櫥褰曟棩蹇楀啓鍏ャ?侀闄? IP 鍚庡彴妯″潡銆佸墠绔鎯呭脊灞傛垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画鍙户缁寜鍚屼竴妯″紡瀹¤ `userDetail`銆乣showUser`銆乣directCustDetailList`銆佽祫閲戝拰浜ゆ槗鏄庣粏绛変唬鐞嗕笓灞炶鎺ュ彛鐨勬櫘閫氱敤鎴疯竟鐣屻??
## 194. 2026-07-09 鍓嶅彴浠ｇ悊鐢ㄦ埛璇︽儏鐢宠浜鸿竟鐣岄棴鐜?

### 鏈澶勭悊鐩爣
- 琛ラ綈 `AgentController::userDetail`銆乣showUser` 鍜? `legacyUserDetailPage` 鐨勭敵璇蜂汉璐﹀彿绫诲瀷杈圭晫锛岀‘淇濆彧鏈変唬鐞嗚处鍙? `account_type=1` 鑳介?氳繃浠ｇ悊璇︽儏鍏ュ彛鏌ョ湅鍙鐢ㄦ埛璧勬枡銆?
- 楠岃瘉鏅?氬鎴疯闂幇浠? `/api/front/users/{user}` 鍗充娇鏌ヨ鑷繁锛屼篃杩斿洖 `ResponseCode::PERMISSION_DENIED`銆?
- 楠岃瘉鏅?氬鎴疯闂棫 Web `show/user_detail/{userId}/{role}` 鍗充娇鏌ヨ鑷繁锛屼篃杩斿洖 HTTP 403锛岄伩鍏嶆棫璇︽儏寮瑰眰娉勬紡浠ｇ悊绠＄悊鍙ｅ緞涓嬬殑璧勯噾銆佽鍗曞拰杩斾剑姹囨?诲瓧娈点??

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/AgentController.php`
  - `userDetail` 鍦ㄤ唬鐞嗘爲鍙鎬у垽鏂墠澧炲姞鐧诲綍鎬佸拰 `account_type=1` 鏍￠獙銆?
  - `showUser` 缁х画澶嶇敤 `userDetail`锛屽洜姝? REST 椋庢牸璇︽儏鍏ュ彛缁ф壙鍚屼竴浠ｇ悊韬唤杈圭晫銆?
  - `legacyUserDetailPage` 鍦ㄦ覆鏌撴棫 HTML 鍓嶅鍔犲悓涓?浠ｇ悊璐﹀彿鏍￠獙锛涢潪浠ｇ悊鎴栨湭鐧诲綍鏃剁洿鎺? 403銆?
  - 淇濇寔浠ｇ悊璐﹀彿姝ｅ父鏌ョ湅涓嬬骇鐢ㄦ埛璇︽儏銆佸鎴疯处鍙蜂笉灞曠ず浠ｇ悊绛夌骇瀛楁銆佹棫 HTML 缁撴瀯鍜岀幇浠ｅ搷搴旂粨鏋勪笉鍙樸??
- `tests/Feature/FrontUserDetailApplicantBoundaryClosureModuleTest.php`
  - 鏂板鏅?氬鎴疯闂幇浠ｄ唬鐞嗙敤鎴疯鎯呮嫆缁濇牱渚嬨??
  - 鏂板鏅?氬鎴疯闂棫浠ｇ悊璇︽儏椤? 403 鏍蜂緥銆?
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontUserDetailApplicantBoundaryClosureModuleTest.php` 棣栨澶辫触锛岀幇浠ｅ叆鍙ｅ疄闄呰繑鍥? `1000`锛屾棫璇︽儏椤靛疄闄呰繑鍥? `200`锛岃瘉鏄庢棫 `userDetail` 鍜? `legacyUserDetailPage` 鍙緷璧? `canViewUser`锛屽厑璁告櫘閫氬鎴烽?氳繃浠ｇ悊璇︽儏鍏ュ彛鏌ョ湅鑷繁銆?
- GREEN锛氫袱涓叆鍙ｅ鍔犱唬鐞嗚处鍙锋牎楠屽悗锛岀幇浠ｅ叆鍙ｈ繑鍥? `ResponseCode::PERMISSION_DENIED`锛屾棫璇︽儏椤佃繑鍥? HTTP 403銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 194 鑺傘??

### 褰撳墠璇佹嵁
- `FrontUserDetailApplicantBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺幇浠? REST 璇︽儏 API 鍜屾棫 Web 璇︽儏椤点??
- 绗? 193 鑺傛敹绱х櫥褰曞巻鍙茶鍙栵紝绗? 194 鑺傜户缁敹绱ц鎯呰鍙栵紝鏅?氬鎴蜂笉鑳藉啀閫氳繃浠ｇ悊璇︽儏閾捐矾璇诲彇浠ｇ悊鍟嗙鐞嗗彛寰勭殑鐢ㄦ埛淇℃伅銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鏅?氱敤鎴疯嚜宸辩殑 `/api/front/profile` 璧勬枡鍏ュ彛銆佷唬鐞嗘甯告煡鐪嬩笅绾х敤鎴疯鎯呫?佷唬鐞嗘爲鑼冨洿鍒ゆ柇銆佸墠绔鎯呭脊灞傛垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画鍙户缁寜鍚屼竴妯″紡瀹¤ `directCustDetailList`銆佽祫閲戞祦姘淬?佹寔浠撳拰璁㈠崟鏄庣粏绛変唬鐞嗕笓灞炶鎺ュ彛鐨勬櫘閫氱敤鎴疯竟鐣屻??
## 195. 2026-07-09 鍓嶅彴鐩村睘瀹㈡埛鏄庣粏鏃ц〃鏍肩敵璇蜂汉杈圭晫闂幆

### 鏈澶勭悊鐩爣
- 琛ラ綈 `AgentController::directCustDetailList` 鐨勭敵璇蜂汉璐﹀彿绫诲瀷杈圭晫锛岀‘淇濆彧鏈変唬鐞嗚处鍙? `account_type=1` 鑳借鍙栨寚瀹氫唬鐞嗙殑鐩村睘瀹㈡埛鏄庣粏琛ㄦ牸銆?
- 楠岃瘉鏅?氬鎴疯闂棫 Web `user/proxy/direct_cust_detail_list` 鍗充娇鎶? `puid` 浼犳垚鑷繁锛屼笖鍚嶄笅瀛樺湪鐪熷疄 `parent_id` 瀛愬鎴凤紝涔熷彧鑳借繑鍥炵┖琛ㄦ牸銆?
- 閬垮厤鏅?氱敤鎴锋ā鍧楄秺鏉冭鍙栦唬鐞嗗晢鐩村睘瀹㈡埛鐨勮祫閲戞眹鎬汇?佺敤鎴峰埆鍚嶅瓧娈靛拰鏃ц〃鏍兼槑缁嗘暟鎹??

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/AgentController.php`
  - `directCustDetailList` 鍦ㄨ鍙栨寚瀹氱埗绾х洿灞炲鎴峰墠澧炲姞鐧诲綍鎬佸拰 `account_type=1` 鏍￠獙銆?
  - 闈炰唬鐞嗘垨鏈櫥褰曟椂淇濇寔鏃у吋瀹? `code/msg/count/data/totalRow` 鍝嶅簲缁撴瀯锛岃繑鍥? `count=0`銆乣data=[]`銆?
  - 淇濇寔浠ｇ悊璐﹀彿姝ｅ父鏌ヨ鍙鐖剁骇銆佺瓫閫夊瓧娈点?佸垎椤电粨鏋勫拰 `totalRow` 姹囨?婚?昏緫涓嶅彉銆?
- `tests/Feature/FrontDirectCustomerDetailApplicantBoundaryClosureModuleTest.php`
  - 鏂板鏅?氬鎴疯闂棫鐩村睘瀹㈡埛鏄庣粏绌虹粨鏋滄牱渚嬶紝鏋勯?犵湡瀹? `parent_id` 瀛愬鎴烽獙璇佷笉浼氭硠婕忋??
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontDirectCustomerDetailApplicantBoundaryClosureModuleTest.php` 棣栨澶辫触锛屾棫琛ㄦ牸鍏ュ彛瀹為檯杩斿洖 `count=1`锛岃瘉鏄庢棫 `directCustDetailList` 鍙緷璧? `canViewUser`锛屽厑璁告櫘閫氬鎴风敤鑷韩 ID 璇诲彇鐩村睘瀹㈡埛鏄庣粏銆?
- GREEN锛歚directCustDetailList` 澧炲姞浠ｇ悊璐﹀彿鏍￠獙鍚庯紝鏅?氬鎴疯闂悓涓?鏃у叆鍙ｈ繑鍥? `count=0`銆乣data=[]`銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 195 鑺傘??

### 褰撳墠璇佹嵁
- `FrontDirectCustomerDetailApplicantBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹炵埗瀛愬鎴锋暟鎹拰鏃? `user/proxy/direct_cust_detail_list` 琛ㄦ牸鍏ュ彛銆?
- 绗? 194 鑺傛敹绱ц鎯呴〉锛岀 195 鑺傜户缁敹绱ф棫鐩村睘瀹㈡埛鏄庣粏琛ㄦ牸锛屼唬鐞嗚鎯呭懆杈硅鍙栭潰缁х画闂悎銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊璐﹀彿姝ｅ父鏌ョ湅鐩村睘瀹㈡埛鏄庣粏銆乣parent_id`/`userId` 鏃у弬鏁板吋瀹广?乣FrontLegacyData::financialTotalRowForUserIds`銆佸墠绔〃鏍兼ā鏉挎垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画鍙户缁寜鍚屼竴妯″紡瀹¤灞傜骇璺緞銆佽祫閲戞祦姘淬?佹寔浠撳拰璁㈠崟鏄庣粏绛変唬鐞嗕笓灞炶鎺ュ彛鐨勬櫘閫氱敤鎴疯竟鐣屻??
## 196. 2026-07-09 鍓嶅彴浠ｇ悊灞傜骇璺緞鐢宠浜鸿竟鐣岄棴鐜?

### 鏈澶勭悊鐩爣
- 琛ラ綈 `AgentController::getParentPath` 鐨勭敵璇蜂汉璐﹀彿绫诲瀷杈圭晫锛岀‘淇濆彧鏈変唬鐞嗚处鍙? `account_type=1` 鑳借鍙栦唬鐞嗗眰绾ц矾寰? HTML銆?
- 楠岃瘉鏅?氬鎴疯闂幇浠? `/api/front/agents/hierarchy-path` 鍗充娇鏌ヨ鑷繁锛屼篃鍙兘杩斿洖绌? `path/tree`銆?
- 楠岃瘉鏅?氬鎴疯闂棫 Web `user/proxy/parentPath` 鍗充娇鏌ヨ鑷繁锛屼篃鍙兘杩斿洖绌? `path/tree`锛岄伩鍏嶆棫灞傜骇璺緞缁勪欢娉勬紡浠ｇ悊鏍戣妭鐐? HTML銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/AgentController.php`
  - `getParentPath` 鍦ㄤ唬鐞嗘爲鍙鎬у拰璺緞鎷兼帴鍓嶅鍔犵櫥褰曟?佸拰 `account_type=1` 鏍￠獙銆?
  - 闈炰唬鐞嗐?佹湭鐧诲綍銆佹棤鏁堢洰鏍囨垨涓嶅彲瑙佺洰鏍囩粺涓?娌跨敤鏃у吋瀹圭┖璺緞鍝嶅簲缁撴瀯銆?
  - 淇濇寔浠ｇ悊璐﹀彿姝ｅ父灞傜骇璺緞銆乣event_name`銆乣family_tree` 涓? parent_id fallback 閫昏緫涓嶅彉銆?
- `tests/Feature/FrontAgentParentPathApplicantBoundaryClosureModuleTest.php`
  - 鏂板鏅?氬鎴疯闂幇浠ｅ眰绾ц矾寰勭┖缁撴灉鏍蜂緥銆?
  - 鏂板鏅?氬鎴疯闂棫灞傜骇璺緞绌虹粨鏋滄牱渚嬨??
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentParentPathApplicantBoundaryClosureModuleTest.php` 棣栨澶辫触锛岀幇浠ｅ拰鏃у叆鍙ｉ兘杩斿洖浜嗗寘鍚櫘閫氬鎴疯嚜韬? ID 涓庡悕绉扮殑璺緞 HTML锛岃瘉鏄庢棫 `getParentPath` 鍙緷璧? `canViewUser`锛屽厑璁告櫘閫氬鎴疯鍙栬嚜韬唬鐞嗗眰绾ц矾寰勩??
- GREEN锛歚getParentPath` 澧炲姞浠ｇ悊璐﹀彿鏍￠獙鍚庯紝鏅?氬鎴疯闂幇浠ｅ拰鏃у叆鍙ｅ潎杩斿洖绌? `path` 涓庣┖ `tree`銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 196 鑺傘??

### 褰撳墠璇佹嵁
- `FrontAgentParentPathApplicantBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺幇浠? `/api/front/agents/hierarchy-path` 鍜屾棫 `user/proxy/parentPath` 涓や釜鍏ュ彛銆?
- 绗? 195 鑺傛敹绱х洿灞炲鎴锋槑缁嗚〃鏍硷紝绗? 196 鑺傜户缁敹绱у眰绾ц矾寰勭粍浠讹紝浠ｇ悊鏍戝懆杈硅鍙栭潰缁х画闂悎銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊璐﹀彿姝ｅ父鏌ョ湅灞傜骇璺緞銆佽矾寰勯鑹叉槧灏勩?乣parentPathIds` fallback銆佸墠绔眰绾х粍浠舵垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画鍙户缁寜鍚屼竴妯″紡瀹¤纭浠ｇ悊绛夌骇銆佽祫閲戞祦姘淬?佹寔浠撳拰璁㈠崟鏄庣粏绛変唬鐞嗕笓灞炶鍐欐帴鍙ｇ殑鏅?氱敤鎴疯竟鐣屻??
## 197. 2026-07-09 鍓嶅彴浠ｇ悊绛夌骇纭鍒楄〃鐢宠浜鸿竟鐣岄棴鐜?

### 鏈澶勭悊鐩爣
- 琛ラ綈 `AgentController::confirmLevel` 涓? `proxyConfirmSearch` 鐨勭敵璇蜂汉璐﹀彿绫诲瀷杈圭晫锛岀‘淇濆彧鏈変唬鐞嗚处鍙? `account_type=1` 鑳借鍙栧緟纭涓嬬骇浠ｇ悊绛夌骇鍒楄〃銆?
- 楠岃瘉鏅?氬鎴疯闂幇浠? `/api/front/agents/level-confirmation` 杩斿洖 `ResponseCode::PERMISSION_DENIED`銆?
- 楠岃瘉鏅?氬鎴疯闂棫 Web `user/proxy/proxyConfirmSearch` 鍚屾牱杩斿洖 `ResponseCode::PERMISSION_DENIED`锛岄伩鍏嶈鍙栦唬鐞嗙瓑绾х‘璁ゆ憳瑕併?佸?欓?夌瓑绾у拰寰呯‘璁や笅绾у垪琛ㄩ厤缃??

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/AgentController.php`
  - `confirmLevel` 鍦ㄨ鍙栧綋鍓嶇敤鎴疯祫鏂欍?乣agent_levels` 鍜岀洿灞炰笅绾т唬鐞嗗墠澧炲姞鐧诲綍鎬佸拰 `account_type=1` 鏍￠獙銆?
  - `proxyConfirmSearch` 缁х画澶嶇敤 `confirmLevel`锛屽洜姝ゆ棫绛夌骇纭鎼滅储鍏ュ彛缁ф壙鍚屼竴浠ｇ悊韬唤杈圭晫銆?
  - 淇濇寔浠ｇ悊璐﹀彿姝ｅ父璇诲彇 `summary`銆乣available_levels`銆乣range_list` 鍜? parent_id scope fallback 閫昏緫涓嶅彉銆?
- `tests/Feature/FrontAgentLevelConfirmationApplicantBoundaryClosureModuleTest.php`
  - 鏂板鏅?氬鎴疯闂幇浠ｇ瓑绾х‘璁ゅ垪琛ㄦ嫆缁濇牱渚嬨??
  - 鏂板鏅?氬鎴疯闂棫绛夌骇纭鎼滅储鎷掔粷鏍蜂緥銆?
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentLevelConfirmationApplicantBoundaryClosureModuleTest.php` 棣栨澶辫触锛岀幇浠ｅ拰鏃у叆鍙ｅ疄闄呴兘杩斿洖 `1000`锛屾湡鏈? `4006`锛岃瘉鏄庢棫 `confirmLevel` 鍙鍙栧綋鍓嶇敤鎴疯祫鏂欙紝娌℃湁闄愬埗鐢宠浜哄繀椤绘槸浠ｇ悊銆?
- GREEN锛歚confirmLevel` 澧炲姞鐧诲綍鎬佸拰浠ｇ悊璐﹀彿鏍￠獙鍚庯紝鏅?氬鎴疯闂幇浠ｅ拰鏃у叆鍙ｅ潎杩斿洖 `ResponseCode::PERMISSION_DENIED`銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 197 鑺傘??

### 褰撳墠璇佹嵁
- `FrontAgentLevelConfirmationApplicantBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺幇浠? `/api/front/agents/level-confirmation` 鍜屾棫 `user/proxy/proxyConfirmSearch` 涓や釜鍏ュ彛銆?
- 绗? 197 鑺傛妸浠ｇ悊绛夌骇纭璇诲彇鍏ュ彛绾冲叆浠ｇ悊涓撳睘杈圭晫锛屽拰绗? 190 鑺備唬鐞嗙瓑绾у?欓?夋帴鍙ｅ叡鍚屾敹绱х瓑绾ч厤缃鍙栭潰銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `confirmLevelChange` 绛夌骇纭鍐欏叆銆佷唬鐞嗚处鍙锋甯歌鍙栫瓑绾х‘璁ゅ垪琛ㄣ?佸墠绔‘璁ょ瓑绾ч〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夌嫭绔? RED/GREEN 瀹¤ `confirmLevelChange` 鐨勬櫘閫氱敤鎴峰啓鍏ヨ竟鐣屻??
## 198. 2026-07-09 鍓嶅彴浠ｇ悊绛夌骇纭鍐欏叆鐢宠浜鸿竟鐣岄棴鐜?

### 鏈澶勭悊鐩爣
- 琛ラ綈 `AgentController::confirmLevelChange` 鐨勭敵璇蜂汉璐﹀彿绫诲瀷杈圭晫锛岀‘淇濆彧鏈変唬鐞嗚处鍙? `account_type=1` 鑳界‘璁ょ洿灞炰笅绾т唬鐞嗙瓑绾с??
- 楠岃瘉鏅?氬鎴峰嵆浣垮悕涓嬪瓨鍦? `parent_id` 鐩村睘浠ｇ悊瀛愯处鍙凤紝涔熶笉鑳介?氳繃鐜颁唬 `/api/front/agents/level-confirmation/changes` 鍐欏叆绛夌骇纭銆?
- 楠岃瘉鏃? Web `user/proxy/confirmLevelChange` 鍚屾牱鎷掔粷鏅?氬鎴凤紝閬垮厤鍐欏叆 `user_infos.is_agent_confirmed`銆乣level_id` 鍜? `comm_rate`銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/AgentController.php`
  - `confirmLevelChange` 鍦ㄦ牎楠屽弬鏁板悗鏀逛负閫氳繃 `legacyFrontUserLogin` 璇诲彇褰撳墠鐧诲綍璁板綍銆?
  - 鏈櫥褰曚粛璧版棫鍏煎璁よ瘉閿欒锛涢潪浠ｇ悊璐﹀彿杩斿洖 `ResponseCode::PERMISSION_DENIED`銆?
  - 淇濇寔浠ｇ悊璐﹀彿姝ｅ父鍐欏叆娴佺▼銆佺洿灞炰唬鐞嗚寖鍥淬?乣FrontLegacyData::userScopeIds`銆乣agent_levels.user_commission` 鍜? `extra_val` 璁＄畻鍙ｅ緞涓嶅彉銆?
- `tests/Feature/FrontAgentLevelConfirmationChangeApplicantBoundaryClosureModuleTest.php`
  - 鏂板鏅?氬鎴疯闂幇浠ｇ瓑绾х‘璁ゅ啓鍏ュ彛鎷掔粷鏍蜂緥銆?
  - 鏂板鏅?氬鎴疯闂棫绛夌骇纭鍐欏叆鍙ｆ嫆缁濇牱渚嬨??
  - 涓や釜鏍蜂緥鍧囨柇瑷?鐩爣鐩村睘浠ｇ悊鐨? `is_agent_confirmed`銆乣level_id`銆乣comm_rate` 鏈鍐欏叆銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentLevelConfirmationChangeApplicantBoundaryClosureModuleTest.php` 棣栨澶辫触锛岀幇浠ｅ拰鏃у啓鍏ュ彛瀹為檯閮借繑鍥? `1000`锛屾湡鏈? `4006`锛屽苟涓斾細杩涘叆绛夌骇纭鍐欏叆璺緞銆?
- GREEN锛歚confirmLevelChange` 澧炲姞鐧诲綍鎬佸拰浠ｇ悊璐﹀彿 `account_type=1` 鏍￠獙鍚庯紝鏅?氬鎴疯闂幇浠ｅ拰鏃у叆鍙ｅ潎杩斿洖 `ResponseCode::PERMISSION_DENIED`锛岀洰鏍囦唬鐞嗙瓑绾х‘璁ゅ瓧娈典繚鎸佹湭鍙樸??
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 198 鑺傘??

### 褰撳墠璇佹嵁
- `FrontAgentLevelConfirmationChangeApplicantBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹? `agent_levels` 閰嶇疆銆佺湡瀹? `parent_id` 鐩村睘浠ｇ悊瀛愯处鍙峰拰涓や釜鍐欏叆鍙ｃ??
- 绗? 197 鑺傚凡鏀剁揣绛夌骇纭璇诲彇鍏ュ彛锛岀 198 鑺傜户缁棴鍚堢瓑绾х‘璁ゅ啓鍏ュ叆鍙ｏ紝鏅?氬鎴蜂笉鑳藉?熺敤浠ｇ悊鏍戝厹搴曞叧绯讳慨鏀逛唬鐞嗚处鍙风瓑绾х姸鎬併??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊璐﹀彿姝ｅ父纭鐩村睘浠ｇ悊绛夌骇銆佷唬鐞嗙瓑绾у?欓?夈?佸墠绔‘璁ょ瓑绾ч〉闈€?乣agent_levels.user_commission` 鐪熷疄姣斾緥鏉ユ簮鎴栨暟鎹簱缁撴瀯銆?
- 鍚庣画缁х画鎸夌嫭绔? RED/GREEN 瀹¤鍏跺畠浠ｇ悊涓撳睘鍐欏叆鍙ｇ殑鏅?氱敤鎴风敵璇蜂汉杈圭晫銆?
## 199. 2026-07-09 鍓嶅彴鐩村睘浣ｉ噾杞处鐢宠浜鸿竟鐣岄棴鐜?

### 鏈澶勭悊鐩爣
- 琛ラ綈 `AgentController::directUserCommTrans` 鐨勭敵璇蜂汉璐﹀彿绫诲瀷杈圭晫锛岀‘淇濆彧鏈変唬鐞嗚处鍙? `account_type=1` 鑳藉彂璧风洿灞炲鎴蜂剑閲戣浆璐︺??
- 楠岃瘉鏅?氬鎴峰嵆浣垮悕涓嬪瓨鍦? `parent_id` 鐩村睘瀛愬鎴凤紝涔熶笉鑳介?氳繃鐜颁唬 `/api/front/customers/commission-transfers` 鎵ｅ噺鑷韩浣欓骞剁粰瀛愬鎴峰叆璐︺??
- 楠岃瘉鏃? Web `user/proxy/directUserCommTrans` 鍚屾牱鎷掔粷鏅?氬鎴凤紝閬垮厤鍐欏叆 DBCT/WBCT 涓ゆ潯 `commission_records` 杞处娴佹按銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/AgentController.php`
  - `directUserCommTrans` 鍦ㄨВ鏋愮櫥褰曡褰曞悗澧炲姞 `account_type=1` 鏍￠獙銆?
  - 闈炰唬鐞嗚处鍙锋部鐢ㄦ棫鍓嶅彴鍝嶅簲缁撴瀯杩斿洖 `msg=FAIL`銆乣errorType=NOTALLOW`銆?
  - 淇濇寔浠ｇ悊璐﹀彿姝ｅ父瀵嗙爜鏍￠獙銆佷綑棰濇墸澧炪?佺洿灞炵洰鏍囨牎楠屻?佷簨鍔″啓鍏ュ拰 DBCT/WBCT 瀹¤瀛楁涓嶅彉銆?
- `tests/Feature/FrontDirectTransferApplicantBoundaryClosureModuleTest.php`
  - 鏂板鏅?氬鎴疯闂幇浠ｄ剑閲戣浆璐﹀叆鍙ｆ嫆缁濇牱渚嬨??
  - 鏂板鏅?氬鎴疯闂棫浣ｉ噾杞处鍏ュ彛鎷掔粷鏍蜂緥銆?
  - 涓や釜鏍蜂緥鍧囨柇瑷?杞嚭鏂逛綑棰濄?佹帴鏀舵柟浣欓鍜? `commission_records` 鏈鍐欏叆銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontDirectTransferApplicantBoundaryClosureModuleTest.php` 棣栨澶辫触锛岀幇浠ｅ拰鏃у叆鍙ｅ疄闄呴兘杩斿洖 `SUCCESS`锛岃瘉鏄庢櫘閫氬鎴峰彲鍊? `parent_id` 鐩村睘瀛愯处鍙疯繘鍏ヤ剑閲戣浆璐﹀啓鍏ヨ矾寰勩??
- GREEN锛歚directUserCommTrans` 澧炲姞浠ｇ悊璐﹀彿 `account_type=1` 鏍￠獙鍚庯紝鏅?氬鎴疯闂幇浠ｅ拰鏃у叆鍙ｅ潎杩斿洖 `NOTALLOW`锛屼綑棰濆拰杞处娴佹按淇濇寔鏈彉銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 199 鑺傘??

### 褰撳墠璇佹嵁
- `FrontDirectTransferApplicantBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹? `parent_id` 瀛愬鎴枫?佺湡瀹炰綑棰濆瓧娈点?佺幇浠? `/api/front/customers/commission-transfers` 鍜屾棫 `user/proxy/directUserCommTrans` 涓や釜鍏ュ彛銆?
- 绗? 183 鑺傝鐩栦唬鐞嗘甯稿啓鍏ュ拰闈炵洿灞炴嫆缁濓紝绗? 199 鑺傜户缁棴鍚堢敵璇蜂汉瑙掕壊杈圭晫锛屾櫘閫氬鎴蜂笉鑳借繘鍏ヤ唬鐞嗕笓灞炰剑閲戣浆璐﹀啓鍏ユ祦銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊璐﹀彿姝ｅ父浣ｉ噾杞处銆佸瘑鐮侀敊璇搷搴斻?佷綑棰濅笉瓒冲搷搴斻?佺洿灞炶寖鍥村厹搴曘?佸墠绔〃鍗曞瓧娈垫垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夌嫭绔? RED/GREEN 瀹¤鍏跺畠浠ｇ悊璧勯噾銆佹寔浠撳拰璁㈠崟鐩稿叧鎺ュ彛鐨勬櫘閫氱敤鎴风敵璇蜂汉杈圭晫銆?
## 200. 2026-07-09 鍓嶅彴杩斾剑杞处鐢宠浜鸿竟鐣岄棴鐜?

### 鏈澶勭悊鐩爣
- 琛ラ綈 `CommissionController::transfer` 鐨勭敵璇蜂汉璐﹀彿绫诲瀷杈圭晫锛岀‘淇濆彧鏈変唬鐞嗚处鍙? `account_type=1` 鑳借皟鐢ㄧ幇浠? `/api/front/commissions/transfers`銆?
- 楠岃瘉鏅?氬鎴峰嵆浣垮悕涓嬪瓨鍦? `parent_id` 鐩村睘浠ｇ悊瀛愯处鍙凤紝涔熶笉鑳芥墸鍑忚嚜韬綑棰濆苟鍚戠洿灞炰唬鐞嗗瓙璐﹀彿鍏ヨ处銆?
- 閬垮厤鏅?氱敤鎴锋ā鍧楄秺鏉冨啓鍏ヨ繑浣ｈ浆璐? DBCT/WBCT 娴佹按鍜屼綑棰濆瓧娈点??

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/CommissionController.php`
  - `transfer` 鍦ㄨВ鏋愬綋鍓嶇櫥褰曡褰曞悗澧炲姞 `account_type=1` 鏍￠獙銆?
  - 闈炰唬鐞嗚处鍙疯繑鍥? `ResponseCode::PERMISSION_DENIED`銆?
  - 淇濇寔浠ｇ悊璐﹀彿姝ｅ父鍙傛暟鏍￠獙銆佺洿灞炰笅绾т唬鐞嗚寖鍥淬?佷綑棰濅笉瓒冲垽鏂?佷簨鍔″啓鍏ュ拰 DBCT/WBCT 瀹¤瀛楁涓嶅彉銆?
- `tests/Feature/FrontCommissionTransferApplicantBoundaryClosureModuleTest.php`
  - 鏂板鏅?氬鎴疯闂? `/api/front/commissions/transfers` 鎷掔粷鏍蜂緥銆?
  - 鏂█杞嚭鏂逛綑棰濄?佹帴鏀舵柟浣欓鍜? `commission_records` 鍧囨湭琚啓鍏ャ??
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontCommissionTransferApplicantBoundaryClosureModuleTest.php` 棣栨澶辫触锛岃繑浣ｈ浆璐﹀叆鍙ｅ疄闄呰繑鍥? `1000`锛屾湡鏈? `4006`锛岃瘉鏄庢櫘閫氬鎴峰彲鍊熺洿灞炰唬鐞嗗瓙璐﹀彿杩涘叆鍐欏叆璺緞銆?
- GREEN锛歚CommissionController::transfer` 澧炲姞浠ｇ悊璐﹀彿 `account_type=1` 鏍￠獙鍚庯紝鏅?氬鎴疯闂鍏ュ彛杩斿洖 `ResponseCode::PERMISSION_DENIED`锛屼綑棰濆拰杞处娴佹按淇濇寔鏈彉銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 200 鑺傘??

### 褰撳墠璇佹嵁
- `FrontCommissionTransferApplicantBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹? `parent_id` 鐩村睘浠ｇ悊瀛愯处鍙枫?佺湡瀹炰綑棰濆瓧娈靛拰鐜颁唬杩斾剑杞处 API銆?
- 绗? 199 鑺傞棴鍚? `AgentController::directUserCommTrans` 鐩村睘瀹㈡埛杞处鐢宠浜鸿竟鐣岋紝绗? 200 鑺傜户缁棴鍚? `CommissionController::transfer` 鐩村睘浠ｇ悊杩斾剑杞处鍐欏叆鍙ｃ??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊璐﹀彿姝ｅ父杩斾剑杞处銆佽浆璐︿笅绾т唬鐞嗛?夐」銆佽繑浣ｅ巻鍙插垪琛ㄣ?佸疄鏃惰繑浣ｅ垪琛ㄣ?佸墠绔〃鍗曞瓧娈垫垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夌嫭绔? RED/GREEN 瀹¤杩斾剑妯″潡璇诲彇鍏ュ彛鍜岃浆璐︿笅绾т唬鐞嗛?夐」鐨勬櫘閫氱敤鎴风敵璇蜂汉杈圭晫銆?
## 201. 2026-07-09 鍓嶅彴杩斾剑杞处涓嬬骇浠ｇ悊閫夐」鐢宠浜鸿竟鐣岄棴鐜?

### 鏈澶勭悊鐩爣
- 琛ラ綈 `CommissionController::transferAgentOptions` 鐨勭敵璇蜂汉璐﹀彿绫诲瀷杈圭晫锛岀‘淇濆彧鏈変唬鐞嗚处鍙? `account_type=1` 鑳借鍙栬繑浣ｈ浆璐︾洿灞炰笅绾т唬鐞嗛?夐」銆?
- 楠岃瘉鏅?氬鎴峰嵆浣垮悕涓嬪瓨鍦? `parent_id` 鐩村睘浠ｇ悊瀛愯处鍙凤紝涔熶笉鑳介?氳繃 `/api/front/commissions/transfer-agent-options` 璇诲彇瀛愯处鍙? ID銆佸悕绉板拰绛夌骇鍊欓?夈??
- 閬垮厤鏅?氱敤鎴锋ā鍧楄秺鏉冩灇涓句唬鐞嗚浆璐﹀?欓?夛紝涓虹 200 鑺傚啓鍏ュ彛杈圭晫鎻愪緵鍓嶇疆璇诲彇闂幆銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/CommissionController.php`
  - `transferAgentOptions` 鏀逛负閫氳繃 `legacyFrontUserLogin` 璇诲彇褰撳墠鐧诲綍璁板綍銆?
  - 鏈櫥褰曚粛璧版棫鍏煎璁よ瘉閿欒锛涢潪浠ｇ悊璐﹀彿杩斿洖 `ResponseCode::PERMISSION_DENIED`銆?
  - 淇濇寔浠ｇ悊璐﹀彿姝ｅ父閫夐」瀛楁銆乣FrontLegacyData::userScopeIds` 鐩村睘浠ｇ悊鑼冨洿鍜? `account_type=1` 鍊欓?夎繃婊や笉鍙樸??
- `tests/Feature/FrontCommissionTransferOptionsApplicantBoundaryClosureModuleTest.php`
  - 鏂板鏅?氬鎴疯闂繑浣ｈ浆璐︿笅绾т唬鐞嗛?夐」鎷掔粷鏍蜂緥銆?
  - 鏂█鍝嶅簲涓笉鍖呭惈鐩村睘浠ｇ悊瀛愯处鍙? ID 鍜屽悕绉般??
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontCommissionTransferOptionsApplicantBoundaryClosureModuleTest.php` 棣栨澶辫触锛岄?夐」鎺ュ彛瀹為檯杩斿洖 `1000`锛屾湡鏈? `4006`锛岃瘉鏄庢櫘閫氬鎴峰彲璇诲彇鐩村睘浠ｇ悊瀛愯处鍙峰?欓?夈??
- GREEN锛歚transferAgentOptions` 澧炲姞浠ｇ悊璐﹀彿 `account_type=1` 鏍￠獙鍚庯紝鏅?氬鎴疯闂鍏ュ彛杩斿洖 `ResponseCode::PERMISSION_DENIED`锛屽搷搴斾笉鍐嶆硠婕忕洿灞炰唬鐞嗗?欓?夈??
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 201 鑺傘??

### 褰撳墠璇佹嵁
- `FrontCommissionTransferOptionsApplicantBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹? `parent_id` 鐩村睘浠ｇ悊瀛愯处鍙峰拰鐜颁唬閫夐」 API銆?
- 绗? 200 鑺傞棴鍚堣繑浣ｈ浆璐﹀啓鍏ュ彛锛岀 201 鑺傞棴鍚堣浆璐﹀?欓?夎鍙栧叆鍙ｏ紝鏅?氬鎴蜂笉鑳藉厛鏋氫妇鍐嶆彁浜や唬鐞嗚繑浣ｈ浆璐︺??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊璐﹀彿姝ｅ父璇诲彇涓嬬骇浠ｇ悊閫夐」銆佽繑浣ｈ浆璐﹀啓鍏ャ?佽繑浣ｅ巻鍙插垪琛ㄣ?佸疄鏃惰繑浣ｅ垪琛ㄣ?佸墠绔笅鎷夊瓧娈垫垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夌嫭绔? RED/GREEN 瀹¤杩斾剑瀹炴椂鍒楄〃銆佽繑浣ｅ巻鍙插拰鏃ц繑浣ｈ鎯呭叆鍙ｇ殑鏅?氱敤鎴风敵璇蜂汉杈圭晫銆?
## 202. 2026-07-09 鍓嶅彴杩斾剑瀹炴椂鍒楄〃鐢宠浜鸿竟鐣岄棴鐜?
### 鏈澶勭悊鐩爣
- 琛ラ綈 `CommissionController::realTime` 鐨勭敵璇蜂汉璐﹀彿绫诲瀷杈圭晫锛岀‘淇濆彧鏈変唬鐞嗚处鍙? `account_type=1` 鑳借鍙栧疄鏃惰繑浣ｅ垪琛ㄣ??
- 楠岃瘉鏅?氬鎴峰嵆浣垮浜庣湡瀹炵櫥褰曟?侊紝涔熶笉鑳介?氳繃鐜颁唬 `/api/front/commissions/realtime` 璇诲彇杩斾剑瀹炴椂鍒楄〃銆佽鍗曠瓫閫夌粨鏋溿?乣detail_commission` 鎴栬繑浣ｆ槑缁嗗瓧娈点??
- 楠岃瘉鏃? Web `user/realtime/realtimeRebateSearch` 澶嶇敤鍚屼竴杈圭晫锛屽悓鏍锋嫆缁濇櫘閫氬鎴疯鍙栧疄鏃惰繑浣ｆ暟鎹??

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/CommissionController.php`
  - `realTime` 鏀逛负閫氳繃 `legacyFrontUserLogin` 璇诲彇褰撳墠鐧诲綍璁板綍銆?
  - 鏈櫥褰曚粛璧版棫鍏煎璁よ瘉閿欒锛涢潪浠ｇ悊璐﹀彿杩斿洖 `ResponseCode::PERMISSION_DENIED`銆?
  - 淇濇寔浠ｇ悊璐﹀彿姝ｅ父瀹炴椂杩斾剑鍙ｅ緞銆佽鍗曠瓫閫夈?乣detail_commission`銆佽繑浣ｆ槑缁嗗拰鍒嗛〉鍝嶅簲缁撴瀯涓嶅彉銆?
- `tests/Feature/FrontCommissionRealtimeApplicantBoundaryClosureModuleTest.php`
  - 鏂板鏅?氬鎴疯闂幇浠ｅ疄鏃惰繑浣ｅ垪琛ㄦ嫆缁濇牱渚嬨??
  - 鏂板鏅?氬鎴疯闂棫瀹炴椂杩斾剑鎼滅储鍏ュ彛鎷掔粷鏍蜂緥銆?
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontCommissionRealtimeApplicantBoundaryClosureModuleTest.php` 棣栨澶辫触锛岀幇浠? `/api/front/commissions/realtime` 鍜屾棫 `user/realtime/realtimeRebateSearch` 瀵规櫘閫氬鎴峰疄闄呰繑鍥? `1000`锛屾湡鏈? `4006`锛岃瘉鏄庡疄鏃惰繑浣ｅ垪琛ㄥ彧鎸夌櫥褰曠敤鎴? ID 鍙栨暟锛屾湭闄愬埗鐢宠浜哄繀椤绘槸浠ｇ悊銆?
- GREEN锛歚realTime` 澧炲姞浠ｇ悊璐﹀彿 `account_type=1` 鏍￠獙鍚庯紝鏅?氬鎴疯闂幇浠ｅ拰鏃у叆鍙ｅ潎杩斿洖 `ResponseCode::PERMISSION_DENIED`銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 202 鑺傘??

### 褰撳墠璇佹嵁
- `FrontCommissionRealtimeApplicantBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺幇浠? `/api/front/commissions/realtime` 鍜屾棫 `user/realtime/realtimeRebateSearch` 涓や釜鍏ュ彛銆?
- 绗? 201 鑺傞棴鍚堣繑浣ｈ浆璐﹀?欓?夎鍙栧叆鍙ｏ紝绗? 202 鑺傜户缁棴鍚堝疄鏃惰繑浣ｈ鍙栧叆鍙ｏ紝鏅?氬鎴蜂笉鑳借鍙栦唬鐞嗕笓灞炶繑浣ｅ疄鏃跺垪琛ㄣ??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊璐﹀彿姝ｅ父瀹炴椂杩斾剑鍒楄〃銆佽繑浣ｅ巻鍙插垪琛ㄣ?佹棫杩斾剑璇︽儏椤点?佸墠绔垪琛ㄥ瓧娈点?佽鍗曠瓫閫夊彛寰勬垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夌嫭绔? RED/GREEN 瀹¤杩斾剑鍘嗗彶鍒楄〃鍜屾棫杩斾剑璇︽儏鍏ュ彛鐨勬櫘閫氱敤鎴风敵璇蜂汉杈圭晫銆?
## 203. 2026-07-09 鍓嶅彴杩斾剑鍘嗗彶鍒楄〃鐢宠浜鸿竟鐣岄棴鐜?
### 鏈澶勭悊鐩爣
- 琛ラ綈 `CommissionController::history` 鐨勭敵璇蜂汉璐﹀彿绫诲瀷杈圭晫锛岀‘淇濆彧鏈変唬鐞嗚处鍙? `account_type=1` 鑳借鍙栬繑浣ｅ巻鍙插垪琛ㄣ??
- 楠岃瘉鏅?氬鎴峰嵆浣垮悕涓嬪瓨鍦? `commission_records.agent_id` 褰掑睘璁板綍锛屼篃涓嶈兘閫氳繃鐜颁唬 `/api/front/commissions/history` 璇诲彇鍘嗗彶杩斾剑鍒嗛〉銆佹眹鎬诲拰缁熻鍒嗘瀽銆?
- 閬垮厤鏅?氱敤鎴锋ā鍧楄秺鏉冭鍙栦唬鐞嗕笓灞炲巻鍙茶繑浣ｉ噾棰濄?佽鍗曞彿銆佺粨绠楃姸鎬併?乣analytics` 瓒嬪娍鍜屾?у埆缁村害缁熻銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/CommissionController.php`
  - `history` 鏀逛负閫氳繃 `legacyFrontUserLogin` 璇诲彇褰撳墠鐧诲綍璁板綍銆?
  - 鏈櫥褰曚粛璧版棫鍏煎璁よ瘉閿欒锛涢潪浠ｇ悊璐﹀彿杩斿洖 `ResponseCode::PERMISSION_DENIED`銆?
  - 淇濇寔浠ｇ悊璐﹀彿姝ｅ父鍘嗗彶杩斾剑鏌ヨ銆佹棩鏈熺瓫閫夈?佽鍗曞彿绛涢?夈?乣dataType` 绛涢?夈?佸垎椤点?乣totalRow` 鍜? `analytics` 缁熻鍙ｅ緞涓嶅彉銆?
- `tests/Feature/FrontCommissionHistoryApplicantBoundaryClosureModuleTest.php`
  - 鏂板鏅?氬鎴疯闂幇浠ｅ巻鍙茶繑浣ｅ垪琛ㄦ嫆缁濇牱渚嬶紝骞舵瀯閫犵湡瀹? `commission_records` 璁板綍璇佹槑鎷掔粷鍙戠敓鍦ㄦ煡璇㈠墠銆?
  - 鏂█鎷掔粷鍝嶅簲涓嶅寘鍚櫘閫氬鎴峰悕涓嬪巻鍙茶繑浣ｈ褰曠殑 `unique_id`銆?
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontCommissionHistoryApplicantBoundaryClosureModuleTest.php` 棣栨澶辫触锛屽巻鍙茶繑浣ｅ叆鍙ｅ疄闄呰繑鍥? `1000`锛屾湡鏈? `4006`锛岃瘉鏄? `history` 鍙寜鐧诲綍鐢ㄦ埛 ID 鏌ヨ `commission_records.agent_id`锛屾湭闄愬埗鐢宠浜哄繀椤绘槸浠ｇ悊銆?
- GREEN锛歚history` 澧炲姞浠ｇ悊璐﹀彿 `account_type=1` 鏍￠獙鍚庯紝鏅?氬鎴疯闂? `/api/front/commissions/history` 杩斿洖 `ResponseCode::PERMISSION_DENIED`锛屽搷搴斾笉鍐嶅寘鍚叾鍚嶄笅鍘嗗彶杩斾剑璁板綍銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 203 鑺傘??

### 褰撳墠璇佹嵁
- `FrontCommissionHistoryApplicantBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹? `commission_records` 鍘嗗彶杩斾剑璁板綍鍜岀幇浠? `/api/front/commissions/history` 鍏ュ彛銆?
- 绗? 202 鑺傞棴鍚堝疄鏃惰繑浣ｈ鍙栧叆鍙ｏ紝绗? 203 鑺傜户缁棴鍚堝巻鍙茶繑浣ｈ鍙栧叆鍙ｏ紝鏅?氬鎴蜂笉鑳借鍙栦唬鐞嗕笓灞炶繑浣ｅ巻鍙叉暟鎹??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊璐﹀彿姝ｅ父鍘嗗彶杩斾剑鍒楄〃銆佸疄鏃惰繑浣ｅ垪琛ㄣ?佹棫瀹炴椂杩斾剑璇︽儏椤点?佸墠绔垪琛ㄥ瓧娈点?佸巻鍙茬粺璁″浘琛ㄦ垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夌嫭绔? RED/GREEN 瀹¤鏃у疄鏃惰繑浣ｈ鎯呭叆鍙ｇ殑鏅?氱敤鎴风敵璇蜂汉杈圭晫銆?
## 204. 2026-07-09 鍓嶅彴鏃у疄鏃惰繑浣ｈ鎯呯敵璇蜂汉杈圭晫闂幆
### 鏈澶勭悊鐩爣
- 琛ラ綈 `CommissionController::realtimeRebateDetail` 鐨勭敵璇蜂汉璐﹀彿绫诲瀷杈圭晫锛岀‘淇濆彧鏈変唬鐞嗚处鍙? `account_type=1` 鑳芥墦寮?鏃у疄鏃惰繑浣ｈ鎯? HTML 寮瑰眰銆?
- 楠岃瘉鏅?氬鎴峰嵆浣垮悕涓嬪瓨鍦? `parent_id` 鐩村睘瀛愬鎴凤紝涓斿瓙瀹㈡埛鏈夌湡瀹? `user_trades` 宸插钩浠撹鍗曪紝涔熶笉鑳介?氳繃鏃? Web `user/realtime/rebate_detail/{orderNo}/{role}` 璇诲彇璁㈠崟璇︽儏銆?
- 閬垮厤鏅?氱敤鎴锋ā鍧楀?? `FrontLegacyData::userScopeIds` 鐖跺瓙鏍戝厹搴曡鍙栦唬鐞嗕笓灞炶鍗曡繑浣ｈ鎯呫?佽鍗曞彿銆佺泩浜忋?佹柟鍚戝拰褰撳墠璐﹀彿杩斾剑瀛楁銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/CommissionController.php`
  - `realtimeRebateDetail` 鏀逛负閫氳繃 `legacyFrontUserLogin` 璇诲彇褰撳墠鐧诲綍璁板綍銆?
  - 鏈櫥褰曟垨闈炰唬鐞嗚处鍙风粺涓? `abort(403)`锛屼繚鎸佹棫璇︽儏 HTML 鍏ュ彛鐨勬嫆缁濇柟寮忋??
  - 淇濇寔浠ｇ悊璐﹀彿姝ｅ父鏃ц鎯呮覆鏌撱?佽鍗曡寖鍥淬?乣FrontLegacyData::userScopeIds` 鍙閾捐矾銆乣currentAgentOrderCommission` 鍜? `orderCommissionDetails` 鍙ｅ緞涓嶅彉銆?
- `tests/Feature/FrontCommissionRealtimeDetailApplicantBoundaryClosureModuleTest.php`
  - 鏂板鏅?氬鎴疯闂棫瀹炴椂杩斾剑璇︽儏鎷掔粷鏍蜂緥锛屾瀯閫犵湡瀹炴櫘閫氬鎴枫?佺洿灞炲瓙瀹㈡埛鍜屽凡骞充粨 `user_trades` 璁㈠崟銆?
  - 鏂█鎷掔粷鍝嶅簲涓嶅寘鍚洰鏍囪鍗曞彿銆?
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontCommissionRealtimeDetailApplicantBoundaryClosureModuleTest.php` 棣栨澶辫触锛屾棫璇︽儏鍏ュ彛瀹為檯杩斿洖 `200`锛屾湡鏈? `403`锛岃瘉鏄庢櫘閫氬鎴峰彲鍊熺洿灞炲瓙瀹㈡埛璁㈠崟杩涘叆鏃ц繑浣ｈ鎯? HTML 娓叉煋璺緞銆?
- GREEN锛歚realtimeRebateDetail` 澧炲姞鐧诲綍璁板綍鍜屼唬鐞嗚处鍙? `account_type=1` 鏍￠獙鍚庯紝鏅?氬鎴疯闂? `user/realtime/rebate_detail/{orderNo}/{role}` 杩斿洖 `403`銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 204 鑺傘??

### 褰撳墠璇佹嵁
- `FrontCommissionRealtimeDetailApplicantBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹? `parent_id` 鐩村睘瀛愬鎴枫?佺湡瀹? `user_trades` 宸插钩浠撹鍗曞拰鏃? `user/realtime/rebate_detail/{orderNo}/{role}` HTML 鍏ュ彛銆?
- 绗? 202 鑺傞棴鍚堝疄鏃惰繑浣ｅ垪琛紝绗? 203 鑺傞棴鍚堝巻鍙茶繑浣ｅ垪琛紝绗? 204 鑺傜户缁棴鍚堟棫瀹炴椂杩斾剑璇︽儏寮瑰眰锛岃繑浣ｈ鍙栭潰瀹屾垚杩欎竴缁勪笁涓叆鍙ｇ殑鏅?氬鎴疯竟鐣屾敹绱с??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊璐﹀彿姝ｅ父鏌ョ湅鏃у疄鏃惰繑浣ｈ鎯呫?佺幇浠ｅ疄鏃惰繑浣ｅ垪琛ㄣ?佸巻鍙茶繑浣ｅ垪琛ㄣ?佸墠绔鎯呰烦杞?佽繑浣ｆ槑缁嗚绠楁垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夌嫭绔? RED/GREEN 瀹¤涓嬩竴涓墠鍙颁唬鐞嗕笓灞炶鍙栨垨鍐欏叆鍏ュ彛鐨勬櫘閫氱敤鎴风敵璇蜂汉杈圭晫銆?
## 205. 2026-07-09 鍓嶅彴涓嬬骇浠ｇ悊鎸佷粨姹囨?荤敵璇蜂汉杈圭晫闂幆
### 鏈澶勭悊鐩爣
- 琛ラ綈 `PositionController::subPositionSummary` 鐨勭敵璇蜂汉璐﹀彿绫诲瀷杈圭晫锛岀‘淇濆彧鏈変唬鐞嗚处鍙? `account_type=1` 鑳借鍙栦笅绾т唬鐞嗘寔浠撴眹鎬汇??
- 楠岃瘉鏅?氬鎴峰嵆浣垮悕涓嬪瓨鍦? `parent_id` 鐩村睘浠ｇ悊瀛愯处鍙凤紝涔熶笉鑳介?氳繃鐜颁唬 `/api/front/positions/direct-agent-summaries` 鏋氫妇璇ヤ唬鐞嗗瓙璐﹀彿鐨勬寔浠撴眹鎬昏銆?
- 楠岃瘉鏃? Web `user/position/v2/subAgentsListSearchV2` 鍚屾牱鎷掔粷鏅?氬鎴凤紝閬垮厤鍊? `FrontLegacyData::userScopeIds` 鐖跺瓙鏍戝厹搴曡鍙栦笅绾т唬鐞? ID銆佸悕绉板拰璧勯噾鎸佷粨姹囨?汇??

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/PositionController.php`
  - `subPositionSummary` 鏀逛负閫氳繃 `legacyFrontUserLogin` 璇诲彇褰撳墠鐧诲綍璁板綍銆?
  - 鏈櫥褰曚粛璧版棫鍏煎璁よ瘉閿欒锛涢潪浠ｇ悊璐﹀彿杩斿洖 `ResponseCode::PERMISSION_DENIED`銆?
  - 淇濇寔浠ｇ悊璐﹀彿姝ｅ父涓嬬骇浠ｇ悊鎸佷粨姹囨?汇?乣FrontLegacyData::userScopeIds($agentId, false, 1)` 鑼冨洿銆佺敤鎴峰悕绛涢?夈?佸垎椤靛拰璐㈠姟姹囨?诲彛寰勪笉鍙樸??
- `tests/Feature/FrontPositionDirectAgentSummaryApplicantBoundaryClosureModuleTest.php`
  - 鏂板鏅?氬鎴疯闂幇浠ｄ笅绾т唬鐞嗘寔浠撴眹鎬绘嫆缁濇牱渚嬨??
  - 鏂板鏅?氬鎴疯闂棫涓嬬骇浠ｇ悊鎸佷粨姹囨?绘嫆缁濇牱渚嬨??
  - 涓や釜鏍蜂緥鍧囨瀯閫犵湡瀹? `parent_id` 鐩村睘浠ｇ悊瀛愯处鍙凤紝骞舵柇瑷?鎷掔粷鍝嶅簲涓嶅寘鍚瓙璐﹀彿 ID 鍜屽悕绉般??

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontPositionDirectAgentSummaryApplicantBoundaryClosureModuleTest.php` 棣栨澶辫触锛岀幇浠ｅ拰鏃т笅绾т唬鐞嗘寔浠撴眹鎬诲叆鍙ｅ疄闄呴兘杩斿洖 `1000`锛屾湡鏈? `4006`锛岃瘉鏄庢櫘閫氬鎴峰彲鍊熺洿灞炰唬鐞嗗瓙璐﹀彿杩涘叆涓嬬骇浠ｇ悊姹囨?昏鍙栬矾寰勩??
- GREEN锛歚subPositionSummary` 澧炲姞鐧诲綍璁板綍鍜屼唬鐞嗚处鍙? `account_type=1` 鏍￠獙鍚庯紝鏅?氬鎴疯闂幇浠ｅ拰鏃у叆鍙ｅ潎杩斿洖 `ResponseCode::PERMISSION_DENIED`锛屽搷搴斾笉鍐嶅寘鍚洿灞炰唬鐞嗗瓙璐﹀彿銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 205 鑺傘??

### 褰撳墠璇佹嵁
- `FrontPositionDirectAgentSummaryApplicantBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹? `parent_id` 鐩村睘浠ｇ悊瀛愯处鍙枫?佺幇浠? `/api/front/positions/direct-agent-summaries` 鍜屾棫 `user/position/v2/subAgentsListSearchV2` 涓や釜鍏ュ彛銆?
- 绗? 205 鑺傚彧鏀剁揣涓嬬骇浠ｇ悊姹囨?诲叆鍙ｏ紱鏅?氬鎴锋湰浜? MT4 姹囨?? `positionSummary2Search` 鍜? `/api/front/positions/summary` 鐨勮嚜鏌ヨ涔変笉鍦ㄦ湰杞敼鍔ㄨ寖鍥村唴銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊璐﹀彿姝ｅ父璇诲彇涓嬬骇浠ｇ悊鎸佷粨姹囨?汇?佹櫘閫氬鎴锋湰浜烘寔浠撴眹鎬汇?佹寔浠撲氦鏄撴槑缁嗐?佺偣鍑绘槑缁嗐?佸墠绔寔浠撴眹鎬婚〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夌嫭绔? RED/GREEN 瀹¤鎸佷粨浜ゆ槗鏄庣粏銆佹棫鐐瑰嚮鏄庣粏绛変唬鐞嗕笓灞炶鍙栧叆鍙ｇ殑鏅?氱敤鎴风敵璇蜂汉杈圭晫銆?
## 206. 2026-07-09 鍓嶅彴鎸佷粨浜ゆ槗鏄庣粏涓嬬骇璇诲彇鐢宠浜鸿竟鐣岄棴鐜?
### 鏈澶勭悊鐩爣
- 琛ラ綈 `PositionController::positionDetail` 涓? `clickSearch` 鐨勬櫘閫氬鎴蜂笅绾ц鍙栬竟鐣岋細鏅?氬鎴峰彧鑳借鍙栨湰浜轰氦鏄撴槑缁嗭紝涓嶈兘鍊? `parent_id` 鐖跺瓙鏍戣鍙栦笅绾х敤鎴蜂氦鏄撱??
- 楠岃瘉鏅?氬鎴峰嵆浣垮悕涓嬪瓨鍦ㄧ洿灞炲瓙瀹㈡埛锛屼笖瀛愬鎴锋湁鐪熷疄 `user_trades` 宸插钩浠撹鍗曪紝涔熶笉鑳介?氳繃鐜颁唬 `/api/front/positions/trades` 璇诲彇璇ュ瓙瀹㈡埛浜ゆ槗鏄庣粏銆?
- 楠岃瘉鏃? Web `user/position/v2/positionSummaryClickSearch` 鍚屾牱鎷掔粷鏅?氬鎴疯鍙栦笅绾т氦鏄撴槑缁嗭紝閬垮厤娉勬紡璁㈠崟鍙枫?佸搧绉嶃?佺泩浜忋?佸紑骞充粨鏃堕棿鍜屼氦鏄撶姸鎬併??

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/PositionController.php`
  - `positionDetail` 鏀逛负閫氳繃 `legacyFrontUserLogin` 璇诲彇褰撳墠鐧诲綍璁板綍銆?
  - 鏈櫥褰曚粛璧版棫鍏煎璁よ瘉閿欒锛涢潪浠ｇ悊璐﹀彿浠呭厑璁? `targetUserId` 绛変簬褰撳墠鐧诲綍涓氬姟鐢ㄦ埛 ID銆?
  - 浠ｇ悊璐﹀彿缁х画娌跨敤鍘熸湁 `FrontLegacyData::userScopeIds($agentId, false)` 涓嬬骇鑼冨洿鍜屾湰浜鸿寖鍥存牎楠屻??
  - `clickSearch` 缁х画澶嶇敤 `positionDetail`锛屽洜姝ゆ棫鐐瑰嚮鏄庣粏鍏ュ彛缁ф壙鍚屼竴杈圭晫銆?
- `tests/Feature/FrontPositionTradeDetailApplicantBoundaryClosureModuleTest.php`
  - 鏂板鏅?氬鎴疯闂幇浠ｅ瓙瀹㈡埛浜ゆ槗鏄庣粏鎷掔粷鏍蜂緥銆?
  - 鏂板鏅?氬鎴疯闂棫鐐瑰嚮鏄庣粏鍏ュ彛鎷掔粷鏍蜂緥銆?
  - 涓や釜鏍蜂緥鍧囨瀯閫犵湡瀹炵洿灞炲瓙瀹㈡埛鍜? `user_trades` 宸插钩浠撹鍗曪紝骞舵柇瑷?鎷掔粷鍝嶅簲涓嶅寘鍚洰鏍囪鍗曞彿鍜屽瓙瀹㈡埛鍚嶇О銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontPositionTradeDetailApplicantBoundaryClosureModuleTest.php` 棣栨澶辫触锛岀幇浠ｅ拰鏃т氦鏄撴槑缁嗗叆鍙ｅ疄闄呴兘杩斿洖 `1000`锛屾湡鏈? `4006`锛岃瘉鏄庢櫘閫氬鎴峰彲鍊熺洿灞炲瓙瀹㈡埛杩涘叆浜ゆ槗鏄庣粏璇诲彇璺緞銆?
- GREEN锛歚positionDetail` 鍖哄垎浠ｇ悊涓庢櫘閫氬鎴峰悗锛屾櫘閫氬鎴疯鍙栦笅绾т氦鏄撴槑缁嗚繑鍥? `ResponseCode::PERMISSION_DENIED`锛沗clickSearch` 澶嶇敤璇ユ柟娉曞悓姝ユ敹绱с??
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 206 鑺傘??

### 褰撳墠璇佹嵁
- `FrontPositionTradeDetailApplicantBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹? `parent_id` 鐩村睘瀛愬鎴枫?佺湡瀹? `user_trades` 宸插钩浠撹鍗曘?佺幇浠? `/api/front/positions/trades` 鍜屾棫 `user/position/v2/positionSummaryClickSearch` 涓や釜鍏ュ彛銆?
- 绗? 205 鑺傛敹绱т笅绾т唬鐞嗘寔浠撴眹鎬伙紝绗? 206 鑺傜户缁敹绱ф寔浠撲氦鏄撴槑缁嗕笅閽伙紝鏅?氬鎴蜂笉鑳介?氳繃浠ｇ悊鏍戝厹搴曡鍙栦笅绾т氦鏄撴槑缁嗐??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊璐﹀彿姝ｅ父鏌ョ湅涓嬬骇浜ゆ槗鏄庣粏銆佹櫘閫氬鎴锋湰浜轰氦鏄撴槑缁嗐?佷笅绾т唬鐞嗘寔浠撴眹鎬汇?佹湰浜? MT4 姹囨?汇?佸墠绔寔浠撴眹鎬婚〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夌嫭绔? RED/GREEN 瀹¤鏃ф寔浠撹仛鍚堟悳绱€?佽祫閲戞祦姘寸瓑浠ｇ悊涓撳睘璇诲彇鍏ュ彛鐨勬櫘閫氱敤鎴风敵璇蜂汉杈圭晫銆?

## 207. 2026-07-09 鍓嶅彴澶т唬鐞嗘寔浠撴悳绱㈠弬鏁板啋鍏呯敵璇蜂汉杈圭晫闂幆

### 鏈澶勭悊鐩爣
- 琛ラ綈 `BigNumberController::currentBigAgent` 鐨勬棫澶т唬鐞嗚韩浠芥潵婧愯竟鐣岋紝纭繚鏃уぇ浠ｇ悊 Ajax 鍙兘淇′换鐧诲綍鍚庡啓鍏ョ殑 `bigAgents` session銆?
- 楠岃瘉鏅?氬鎴峰嵆浣跨煡閬撶湡瀹? `big_agents.id`锛屼篃涓嶈兘閫氳繃璇锋眰鍙傛暟 `big_agent_id` 鎴? `bigAgentId` 鍐掑厖澶т唬鐞嗚鍙栨寔浠撹仛鍚堟悳绱㈢粨鏋溿??
- 瑕嗙洊鏃? Web `user/agents/position/positionSummarySearch` 鍜? `user/agents/position/subAgentsListSearch` 涓や釜鎸佷粨鍏ュ彛锛岄伩鍏嶆硠婕? `big_agents.sub_agent_ids` 涓嬬殑浠ｇ悊 ID銆佸悕绉板拰璧勯噾姹囨?诲瓧娈点??

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/BigNumberController.php`
  - `currentBigAgent` 绉婚櫎璇锋眰鍙傛暟 `big_agent_id` / `bigAgentId` 鐩存煡澶т唬鐞嗚处鍙风殑鍏滃簳銆?
  - 淇濈暀鏃уぇ浠ｇ悊鐧诲綍鎴愬姛鍚庡啓鍏? session `bigAgents` 鐨勬甯歌鍙栬矾寰勩??
  - 淇濇寔澶т唬鐞嗚处鍙锋甯歌鍙栨寔浠撴眹鎬汇?佷笅绾т唬鐞嗚寖鍥淬?乣FrontLegacyData::userScopeIds` 鍏滃簳鍜屾棫琛ㄦ牸 `rows/total/footer` 鍝嶅簲缁撴瀯涓嶅彉銆?
- `tests/Feature/FrontBigNumberPositionApplicantBoundaryClosureModuleTest.php`
  - 鏂板鏅?氬鎴锋惡甯? `big_agent_id` 璁块棶鏃уぇ浠ｇ悊鎸佷粨鎼滅储鍏ュ彛鐨勬嫆缁濇牱渚嬨??
  - 鏂板鏅?氬鎴锋惡甯? `bigAgentId` 璁块棶鏃уぇ浠ｇ悊涓嬬骇鎸佷粨缁熻鍏ュ彛鐨勬嫆缁濇牱渚嬨??
  - 涓や釜鏍蜂緥鍧囨瀯閫犵湡瀹炴櫘閫氬鎴枫?佺湡瀹炲ぇ浠ｇ悊璐﹀彿鍜屽彲瑙佷唬鐞嗗瓙璐﹀彿锛屽苟鏂█鍝嶅簲 `rows=[]`銆乣total=0` 涓斾笉鍖呭惈鍙浠ｇ悊 ID 鍜屽悕绉般??

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontBigNumberPositionApplicantBoundaryClosureModuleTest.php` 棣栨澶辫触锛屼袱涓棫澶т唬鐞嗘寔浠撳叆鍙ｉ兘浼氳繑鍥炲彲瑙佷唬鐞嗚锛岃瘉鏄庢櫘閫氬鎴峰彲閫氳繃 `big_agent_id` / `bigAgentId` 鍙傛暟鍐掑厖澶т唬鐞嗚韩浠姐??
- GREEN锛歚currentBigAgent` 鍙俊浠? `bigAgents` session 鍚庯紝鏅?氬鎴峰弬鏁板啋鍏呰闂袱涓棫鎸佷粨鍏ュ彛鍧囪繑鍥炵┖ `rows` 鍜? `total=0`銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 207 鑺傘??

### 褰撳墠璇佹嵁
- `FrontBigNumberPositionApplicantBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹? `big_agents` 璁板綍銆佺湡瀹炲彲瑙佷唬鐞嗗瓙璐﹀彿銆乣user/agents/position/positionSummarySearch` 鍜? `user/agents/position/subAgentsListSearch` 涓や釜鏃у叆鍙ｃ??
- 绗? 207 鑺傛敹绱ф棫澶т唬鐞嗘寔浠撴悳绱㈢殑韬唤鏉ユ簮锛屾櫘閫氬鎴蜂笉鑳藉啀闈犺姹傚弬鏁版灇涓惧ぇ浠ｇ悊鍙鐞嗕唬鐞嗚寖鍥淬??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩澶т唬鐞嗚处鍙锋甯哥櫥褰曘?乻ession 璇诲彇銆佷唬鐞嗗垪琛ㄣ?佽鍗曞垪琛ㄣ?佹敼瀵嗗叆鍙ｆ垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夌嫭绔? RED/GREEN 瀹¤鏃уぇ浠ｇ悊浠ｇ悊鍒楄〃銆佽鍗曞垪琛ㄣ?佽祫閲戞祦姘寸瓑鍏ュ彛鏄惁杩樺瓨鍦ㄥ弬鏁板啋鍏呮垨鏅?氱敤鎴风敵璇蜂汉杈圭晫缂哄彛銆?

## 208. 2026-07-09 鍓嶅彴鐩村睘瀹㈡埛鍏ラ噾娴佹按鐢宠浜鸿竟鐣岄棴鐜?

### 鏈澶勭悊鐩爣
- 琛ラ綈 `FlowController::accountFlow` 瀵圭洿灞炲鎴锋祦姘寸被鍨嬬殑鐢宠浜鸿处鍙风被鍨嬭竟鐣岋紝纭繚鍙湁浠ｇ悊璐﹀彿 `account_type=1` 鑳借鍙栫洿灞炲鎴峰叆閲戞祦姘淬??
- 楠岃瘉鏅?氬鎴峰嵆浣垮悕涓嬪瓨鍦? `parent_id` 鐩村睘瀛愬鎴凤紝涓斿瓙瀹㈡埛鏈夌湡瀹? `deposit_records` 鍏ラ噾璁板綍锛屼篃涓嶈兘閫氳繃鐜颁唬 `/api/front/flows/direct-deposits` 璇诲彇璇ュ瓙瀹㈡埛鍏ラ噾娴佹按銆?
- 楠岃瘉鏃? Web `user/flow/directDepositFlowSearch` 鍚屾牱鎷掔粷鏅?氬鎴疯鍙栫洿灞炲鎴峰叆閲戞祦姘达紝閬垮厤娉勬紡鍏ラ噾璁㈠崟鍙枫?佺敤鎴峰悕绉般?侀噾棰濄?佹笭閬撳拰鏀粯鏃堕棿銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/FlowController.php`
  - `accountFlow` 鍦ㄨВ鏋? `flow_type` 鍚庯紝瀵? `direct_deposit`銆乣direct_withdraw`銆乣direct_agents_deposit`銆乣direct_agents_withdraw` 澧炲姞鐢宠浜? `account_type=1` 鏍￠獙銆?
  - 闈炰唬鐞嗚处鍙疯鍙栫洿灞炲鎴锋垨鐩村睘浠ｇ悊娴佹按鏃惰繑鍥? `ResponseCode::PERMISSION_DENIED`銆?
  - 淇濇寔鏅?氬鎴锋湰浜哄叆閲?/鍑洪噾/鍑洪噾鐢宠娴佹按銆佷唬鐞嗚处鍙锋甯歌鍙栫洿灞炴祦姘淬?佹棩鏈熺瓫閫夈?佸垎椤靛拰 `totalRow` 姹囨?诲彛寰勪笉鍙樸??
- `tests/Feature/FrontFlowDirectDepositApplicantBoundaryClosureModuleTest.php`
  - 鏂板鏅?氬鎴疯闂幇浠ｇ洿灞炲鎴峰叆閲戞祦姘存嫆缁濇牱渚嬨??
  - 鏂板鏅?氬鎴疯闂棫鐩村睘瀹㈡埛鍏ラ噾娴佹按鎷掔粷鏍蜂緥銆?
  - 涓や釜鏍蜂緥鍧囨瀯閫犵湡瀹炴櫘閫氬鎴枫?佺洿灞炲瓙瀹㈡埛鍜? `deposit_records` 鍏ラ噾娴佹按锛屽苟鏂█鎷掔粷鍝嶅簲涓嶅寘鍚洰鏍囪鍗曞彿鍜屽瓙瀹㈡埛鍚嶇О銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontFlowDirectDepositApplicantBoundaryClosureModuleTest.php` 棣栨澶辫触锛岀幇浠ｅ拰鏃х洿灞炲鎴峰叆閲戞祦姘村叆鍙ｅ疄闄呴兘杩斿洖 `1000`锛屾湡鏈? `4006`锛岃瘉鏄庢櫘閫氬鎴峰彲鍊熺洿灞炲瓙瀹㈡埛杩涘叆浠ｇ悊涓撳睘鍏ラ噾娴佹按璇诲彇璺緞銆?
- GREEN锛歚accountFlow` 瀵圭洿灞炲鎴?/鐩村睘浠ｇ悊娴佹按绫诲瀷澧炲姞浠ｇ悊璐﹀彿鏍￠獙鍚庯紝鏅?氬鎴疯闂幇浠ｅ拰鏃х洿灞炲鎴峰叆閲戞祦姘村叆鍙ｅ潎杩斿洖 `ResponseCode::PERMISSION_DENIED`銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 208 鑺傘??

### 褰撳墠璇佹嵁
- `FrontFlowDirectDepositApplicantBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹? `parent_id` 鐩村睘瀛愬鎴枫?佺湡瀹? `deposit_records` 鍏ラ噾娴佹按銆佺幇浠? `/api/front/flows/direct-deposits` 鍜屾棫 `user/flow/directDepositFlowSearch` 涓や釜鍏ュ彛銆?
- 绗? 208 鑺傚彧鏀剁揣鐩村睘瀹㈡埛鍏ラ噾娴佹按璇诲彇鍏ュ彛锛涙櫘閫氬鎴锋湰浜烘祦姘村拰浠ｇ悊璐﹀彿姝ｅ父鐩村睘瀹㈡埛娴佹按涓嶅湪鏈疆鏀瑰姩鑼冨洿鍐呫??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鏅?氬鎴锋湰浜鸿祫閲戞祦姘淬?佷唬鐞嗚处鍙锋甯哥洿灞炲鎴峰叆閲戞祦姘淬?佺洿灞炲鎴峰嚭閲戞祦姘淬?佺洿灞炰唬鐞嗗叆鍑洪噾娴佹按銆佺洿灞炲鎴峰叆閲戝鍑恒?佸墠绔祦姘撮〉绛炬垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夌嫭绔? RED/GREEN 瀹¤鐩村睘瀹㈡埛鍑洪噾銆佺洿灞炰唬鐞嗗叆鍑洪噾鍜岀洿灞炲鎴峰叆閲戝鍑虹瓑璧勯噾娴佹按鍏ュ彛鐨勬櫘閫氱敤鎴风敵璇蜂汉杈圭晫銆?

## 209. 2026-07-09 鍓嶅彴鐩村睘瀹㈡埛鍏ラ噾瀵煎嚭鐢宠浜鸿竟鐣岄棴鐜?

### 鏈澶勭悊鐩爣
- 琛ラ綈 `FlowController::depositExport` 鐨勭敵璇蜂汉璐﹀彿绫诲瀷杈圭晫锛岀‘淇濆彧鏈変唬鐞嗚处鍙? `account_type=1` 鑳藉鍑虹洿灞炲鎴峰叆閲戞祦姘? CSV銆?
- 楠岃瘉鏅?氬鎴峰嵆浣垮悕涓嬪瓨鍦? `parent_id` 鐩村睘瀛愬鎴凤紝涓斿瓙瀹㈡埛鏈夌湡瀹? `deposit_records` 鍏ラ噾璁板綍锛屼篃涓嶈兘閫氳繃鏃? Web `user/flow/depositExport` 鐢熸垚鐩村睘瀹㈡埛鍏ラ噾瀵煎嚭鏂囦欢銆?
- 閬垮厤鏅?氱敤鎴锋ā鍧楀?熺洿灞炲瓙瀹㈡埛鍏崇郴瀵煎嚭鍏ラ噾璁㈠崟鍙枫?佺敤鎴? ID銆佹笭閬撱?侀噾棰濆拰鍏ラ噾鏃堕棿銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/FlowController.php`
  - `depositExport` 鏀逛负閫氳繃 `legacyFrontUserInfo` 璇诲彇褰撳墠鐧诲綍鐢ㄦ埛璧勬枡銆?
  - 鏈櫥褰曟垨闈炰唬鐞嗚处鍙疯繑鍥炴棫鍓嶅彴鍏煎鐨? `msg=FAIL`锛屼笉鍐嶈繘鍏ョ洿灞炲鎴? scope 鍜? CSV 鐢熸垚閫昏緫銆?
  - 绉婚櫎鍙繑鍥炲綋鍓嶄笟鍔＄敤鎴? ID 鐨? `legacyCurrentUserId` 绉佹湁鏂规硶锛岄伩鍏嶄繚鐣欑粫杩囪处鍙风被鍨嬬殑鏃у垽鏂??
  - 淇濇寔浠ｇ悊璐﹀彿姝ｅ父瀵煎嚭鐩村睘瀹㈡埛鍏ラ噾娴佹按銆佽鍗曠瓫閫夈?佹棩鏈熺瓫閫夈?丆SV 鏂囦欢鍚嶅拰涓嬭浇璺緞涓嶅彉銆?
- `tests/Feature/FrontFlowDirectDepositExportApplicantBoundaryClosureModuleTest.php`
  - 鏂板鏅?氬鎴疯闂? `user/flow/depositExport` 鎷掔粷鏍蜂緥銆?
  - 鏍蜂緥鏋勯?犵湡瀹炴櫘閫氬鎴枫?佺洿灞炲瓙瀹㈡埛鍜? `deposit_records` 鍏ラ噾璁板綍锛汻ED 闃舵鑻ョ敓鎴愪复鏃? CSV锛屼細鍦ㄦ柇瑷?鍓嶆竻鐞嗚鏂囦欢銆?
- `tests/Feature/FrontFlowControllerCommentReadabilityTest.php`
  - 绉婚櫎瀵瑰凡鍒犻櫎 `legacyCurrentUserId` 娉ㄩ噴鐨勯潤鎬佺害鏉熴??

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontFlowDirectDepositExportApplicantBoundaryClosureModuleTest.php` 棣栨澶辫触锛屽鍑哄叆鍙ｅ疄闄呰繑鍥? `direct_deposit_transactions_*` 鏂囦欢鏍囪瘑锛屾湡鏈? `FAIL`锛岃瘉鏄庢櫘閫氬鎴峰彲鍊熺洿灞炲瓙瀹㈡埛鐢熸垚鍏ラ噾娴佹按 CSV銆?
- GREEN锛歚depositExport` 澧炲姞鐧诲綍璧勬枡鍜屼唬鐞嗚处鍙? `account_type=1` 鏍￠獙鍚庯紝鏅?氬鎴疯闂鍑哄叆鍙ｈ繑鍥? `msg=FAIL`銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 209 鑺傘??

### 褰撳墠璇佹嵁
- `FrontFlowDirectDepositExportApplicantBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹? `parent_id` 鐩村睘瀛愬鎴枫?佺湡瀹? `deposit_records` 鍏ラ噾娴佹按鍜屾棫 `user/flow/depositExport` 瀵煎嚭鍏ュ彛銆?
- 绗? 208 鑺傛敹绱х洿灞炲鎴峰叆閲戞祦姘磋鍙栧叆鍙ｏ紝绗? 209 鑺傜户缁敹绱у悓涓?鏁版嵁闈㈢殑 CSV 瀵煎嚭鍙ｃ??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊璐﹀彿姝ｅ父瀵煎嚭鐩村睘瀹㈡埛鍏ラ噾娴佹按銆佹櫘閫氬鎴锋湰浜鸿祫閲戞祦姘淬?佺洿灞炲鎴峰嚭閲戞祦姘淬?佺洿灞炰唬鐞嗗叆鍑洪噾娴佹按銆佷笅杞芥枃浠跺畨鍏ㄨ矾寰勬垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夌嫭绔? RED/GREEN 瀹¤鐩村睘瀹㈡埛鍑洪噾銆佺洿灞炰唬鐞嗗叆鍑洪噾鍜屾棫涓嬭浇鏂囦欢璁块棶绛夎祫閲戞祦姘村叆鍙ｇ殑鏅?氱敤鎴风敵璇蜂汉杈圭晫銆?

## 210. 2026-07-09 鍓嶅彴鏃т笅杞芥枃浠惰闂敵璇蜂汉杈圭晫闂幆

### 鏈澶勭悊鐩爣
- 琛ラ綈 `FlowController::downloadFile` 鐨勭敵璇蜂汉璐﹀彿绫诲瀷杈圭晫锛岀‘淇濆彧鏈変唬鐞嗚处鍙? `account_type=1` 鑳戒笅杞藉墠鍙扮洿灞炲鎴峰叆閲戝鍑? CSV銆?
- 楠岃瘉鏅?氬鎴峰嵆浣跨煡閬撶湡瀹? `storage/app/front_exports` 瀵煎嚭鏂囦欢鍚嶏紝涔熶笉鑳介?氳繃鏃? Web `user/flow/downloadfile/{file}/{role}` 鐩存帴涓嬭浇鏂囦欢鍐呭銆?
- 閬垮厤鏅?氱敤鎴锋ā鍧楃粫杩囩 209 鑺傜殑瀵煎嚭鍏ュ彛鏍￠獙锛屽?熸棫涓嬭浇璺敱璇诲彇宸叉湁 CSV 涓殑璁㈠崟鍙枫?佺敤鎴? ID銆佹笭閬撱?侀噾棰濆拰鍏ラ噾鏃堕棿銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/FlowController.php`
  - `downloadFile` 澧炲姞 `Request` 鍙傛暟锛岄?氳繃 `legacyFrontUserInfo` 璇诲彇褰撳墠鐧诲綍鐢ㄦ埛璧勬枡銆?
  - 鏈櫥褰曟垨闈炰唬鐞嗚处鍙风粺涓? `abort(403)`锛屼笉鍐嶈繘鍏ユ枃浠跺悕娓呮礂銆乣front_exports` 璺緞瑙ｆ瀽鍜屼簩杩涘埗涓嬭浇閫昏緫銆?
  - 淇濇寔浠ｇ悊璐﹀彿姝ｅ父涓嬭浇璺緞銆佹枃浠跺悕瀹夊叏瀛楃杩囨护銆佸己鍒? `.csv` 鍚庣紑鍜? 404 鏂囦欢涓嶅瓨鍦ㄥ搷搴斾笉鍙樸??
- `tests/Feature/FrontFlowDownloadFileApplicantBoundaryClosureModuleTest.php`
  - 鏂板鏅?氬鎴疯闂棫涓嬭浇鏂囦欢璺敱鎷掔粷鏍蜂緥銆?
  - 鏍蜂緥鍐欏叆鐪熷疄 `storage/app/front_exports/*.csv` 涓存椂鏂囦欢锛屾柇瑷?鏅?氬鎴疯繑鍥? 403 涓斿搷搴斾笉鍖呭惈鐩爣 CSV 鍐呭銆?
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontFlowDownloadFileApplicantBoundaryClosureModuleTest.php` 棣栨澶辫触锛屼笅杞藉叆鍙ｅ疄闄呰繑鍥? `200`锛屾湡鏈? `403`锛岃瘉鏄庢櫘閫氬鎴峰彲鐩存帴涓嬭浇鐪熷疄瀛樺湪鐨勫墠鍙版祦姘村鍑? CSV銆?
- GREEN锛歚downloadFile` 澧炲姞鐧诲綍璧勬枡鍜屼唬鐞嗚处鍙? `account_type=1` 鏍￠獙鍚庯紝鏅?氬鎴疯闂? `user/flow/downloadfile/{file}/{role}` 杩斿洖 `403`锛屽搷搴斾笉鍐嶅寘鍚? CSV 鍐呭銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 210 鑺傘??

### 褰撳墠璇佹嵁
- `FrontFlowDownloadFileApplicantBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹? `front_exports` CSV 鏂囦欢鍜屾棫 `user/flow/downloadfile/{file}/{role}` 涓嬭浇鍏ュ彛銆?
- 绗? 209 鑺傛敹绱х洿灞炲鎴峰叆閲? CSV 鐢熸垚鍏ュ彛锛岀 210 鑺傜户缁敹绱у悓涓?瀵煎嚭鏂囦欢鐨勬棫涓嬭浇鍏ュ彛锛屾櫘閫氬鎴蜂笉鑳界粫杩囩敓鎴愬叆鍙ｇ洿鎺ヨ鍙栧凡鏈夋枃浠躲??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊璐﹀彿姝ｅ父涓嬭浇瀵煎嚭鏂囦欢銆丆SV 鏂囦欢鍚嶇敓鎴愯鍒欍?佹枃浠朵笉瀛樺湪 404 鍝嶅簲銆佹櫘閫氬鎴锋湰浜鸿祫閲戞祦姘存垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夌嫭绔? RED/GREEN 瀹¤鐩村睘瀹㈡埛鍑洪噾鍜岀洿灞炰唬鐞嗗叆鍑洪噾绛夎祫閲戞祦姘村叆鍙ｇ殑鏅?氱敤鎴风敵璇蜂汉杈圭晫銆?

## 211. 2026-07-09 鍓嶅彴鏃т笅杞芥枃浠跺綊灞炶竟鐣岄棴鐜?

### 鏈澶勭悊鐩爣
- 琛ラ綈 `FlowController::depositExport` 涓? `downloadFile` 涔嬮棿鐨勫鍑烘枃浠跺綊灞炶竟鐣岋紝纭繚鏂扮敓鎴愮殑鐩村睘瀹㈡埛鍏ラ噾 CSV 鍙兘鐢辩敓鎴愬畠鐨勪唬鐞嗚处鍙蜂笅杞姐??
- 楠岃瘉浠ｇ悊 A 鐢熸垚 `storage/app/front_exports` 瀵煎嚭鏂囦欢鍚庯紝浠ｇ悊 B 鍗充娇鐭ラ亾鏂囦欢鏍囪瘑锛屼篃涓嶈兘閫氳繃鏃? Web `user/flow/downloadfile/{file}/{role}` 涓嬭浇璇? CSV銆?
- 閬垮厤浠ｇ悊璐﹀彿涔嬮棿闈犳枃浠跺悕鐚滄祴璺ㄨ处鍙疯鍙栫洿灞炲鎴峰叆閲戣鍗曞彿銆佺敤鎴? ID銆佹笭閬撱?侀噾棰濆拰鍏ラ噾鏃堕棿銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/FlowController.php`
  - `depositExport` 鍦? CSV 鐢熸垚鎴愬姛鍚庯紝鍚屾鍐欏叆鍚屽悕 `.meta.json` 褰掑睘鍏冩暟鎹紝璁板綍鐢熸垚浠ｇ悊 `user_id`銆佹枃浠跺悕鍜屽垱寤烘椂闂淬??
  - `downloadFile` 鍦ㄧ洰鏍? CSV 瀛樺湪涓旀娴嬪埌 `.meta.json` 鏃讹紝鏍￠獙鍏冩暟鎹腑鐨? `user_id` 蹇呴』绛変簬褰撳墠鐧诲綍浠ｇ悊璐﹀彿銆?
  - 鍏冩暟鎹己澶辩殑鍘嗗彶瀵煎嚭鏂囦欢浠嶆部鐢ㄧ 210 鑺傜殑浠ｇ悊璐﹀彿鏍￠獙锛岄伩鍏嶇洿鎺ユ墦鏂棫鏂囦欢涓嬭浇鍏煎璺緞銆?
  - 鍏冩暟鎹啓鍏ュけ璐ユ椂鍒犻櫎鍒氱敓鎴愮殑 CSV 骞惰繑鍥? `msg=FAIL`锛岄伩鍏嶄骇鐢熸棤娉曞綊灞炴牎楠岀殑鏂板鍑烘枃浠躲??
- `tests/Feature/FrontFlowDownloadFileOwnerBoundaryClosureModuleTest.php`
  - 鏂板浠ｇ悊 A 鐢熸垚 CSV 鍚庡彲姝ｅ父涓嬭浇鐨勬牱渚嬨??
  - 鏂板浠ｇ悊 B 璁块棶鍚屼竴 CSV 琚? 403 鎷掔粷鐨勬牱渚嬨??
  - 鏂板鏈?缁堟竻鍗曡褰曟祴璇曪紝绾︽潫鏈妭蹇呴』鐣欐。銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontFlowDownloadFileOwnerBoundaryClosureModuleTest.php` 棣栨澶辫触锛屼唬鐞? B 涓嬭浇浠ｇ悊 A 鍒氱敓鎴愮殑 CSV 瀹為檯杩斿洖 `200`锛屾湡鏈? `403`锛岃瘉鏄庢棫涓嬭浇鍏ュ彛娌℃湁瀵煎嚭鏂囦欢褰掑睘鏍￠獙銆?
- GREEN锛歚depositExport` 鍐欏叆 `.meta.json` 涓? `downloadFile` 鎸夊厓鏁版嵁鏍￠獙褰撳墠浠ｇ悊鍚庯紝鐢熸垚浠ｇ悊涓嬭浇杩斿洖 `200`锛屽叾瀹冧唬鐞嗚闂悓涓?鏂囦欢杩斿洖 `403`銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 211 鑺傘??

### 褰撳墠璇佹嵁
- `FrontFlowDownloadFileOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄浠ｇ悊鐧诲綍鎬併?佺湡瀹炵洿灞炲瓙瀹㈡埛銆佺湡瀹? `deposit_records` 鍏ラ噾娴佹按銆佺湡瀹? `depositExport` 鐢熸垚 CSV 鍜屾棫 `user/flow/downloadfile/{file}/{role}` 涓嬭浇鍏ュ彛銆?
- 绗? 210 鑺傛敹绱ф櫘閫氬鎴蜂笅杞藉叆鍙ｏ紝绗? 211 鑺傜户缁敹绱т唬鐞嗚处鍙蜂箣闂寸殑瀵煎嚭鏂囦欢褰掑睘杈圭晫銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 CSV 琛ㄥご鍜屽唴瀹广?佹枃浠跺悕闅忔満瑙勫垯銆佸巻鍙叉棤鍏冩暟鎹鍑烘枃浠剁殑浠ｇ悊涓嬭浇鍏煎銆佹櫘閫氬鎴锋湰浜鸿祫閲戞祦姘存垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夌嫭绔? RED/GREEN 瀹¤鐩村睘瀹㈡埛鍑洪噾鍜岀洿灞炰唬鐞嗗叆鍑洪噾绛夎祫閲戞祦姘村叆鍙ｇ殑鍓╀綑闂幆娴嬭瘯銆?

## 212. 2026-07-09 鍓嶅彴鐩村睘瀹㈡埛鍑洪噾娴佹按鐢宠浜鸿竟鐣屾祴璇曢棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `FlowController::directWithdrawalFlowSearch` 琛ラ綈鐙珛闂幆娴嬭瘯锛岀‘璁ょ洿灞炲鎴峰嚭閲戞祦姘村叆鍙ｅ悓鏍峰彧鍏佽浠ｇ悊璐﹀彿 `account_type=1` 璇诲彇銆?
- 楠岃瘉鏅?氬鎴峰嵆浣垮悕涓嬪瓨鍦? `parent_id` 鐩村睘瀛愬鎴凤紝涓斿瓙瀹㈡埛鏈夌湡瀹? `withdraw_records` 鍑洪噾璁板綍锛屼篃涓嶈兘閫氳繃鐜颁唬 `/api/front/flows/direct-withdrawals` 璇诲彇璇ュ瓙瀹㈡埛鍑洪噾娴佹按銆?
- 楠岃瘉鏃? Web `user/flow/directWithdrawalFlowSearch` 鍚屾牱鎷掔粷鏅?氬鎴疯鍙栫洿灞炲鎴峰嚭閲戞祦姘达紝閬垮厤娉勬紡鍑洪噾璁㈠崟鍙枫?佺敤鎴峰悕绉般?侀摱琛屽崱鍜屽嚭閲戦噾棰濄??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontFlowDirectWithdrawApplicantBoundaryClosureModuleTest.php`
  - 鏂板鏅?氬鎴疯闂幇浠ｇ洿灞炲鎴峰嚭閲戞祦姘存嫆缁濇牱渚嬨??
  - 鏂板鏅?氬鎴疯闂棫鐩村睘瀹㈡埛鍑洪噾娴佹按鎷掔粷鏍蜂緥銆?
  - 涓や釜鏍蜂緥鍧囨瀯閫犵湡瀹炴櫘閫氬鎴枫?佺洿灞炲瓙瀹㈡埛鍜? `withdraw_records` 鍑洪噾娴佹按锛屽苟鏂█鎷掔粷鍝嶅簲涓嶅寘鍚洰鏍囪鍗曞彿鍜屽瓙瀹㈡埛鍚嶇О銆?

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontFlowDirectWithdrawApplicantBoundaryClosureModuleTest.php` 鍦ㄦ柊澧炴竻鍗曟柇瑷?鍓嶇洿鎺ラ?氳繃锛岃瘉鏄庣 208 鑺傚凡鍔犲叆鐨? `accountFlow` 鍏变韩 `account_type=1` 鏍￠獙瑕嗙洊浜? `direct_withdraw` 绫诲瀷銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 212 鑺傘??
- GREEN锛氳拷鍔犵 212 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontFlowDirectWithdrawApplicantBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹? `parent_id` 鐩村睘瀛愬鎴枫?佺湡瀹? `withdraw_records` 鍑洪噾娴佹按銆佺幇浠? `/api/front/flows/direct-withdrawals` 鍜屾棫 `user/flow/directWithdrawalFlowSearch` 涓や釜鍏ュ彛銆?
- 绗? 208 鑺傛敹绱у叡浜鍙栭?昏緫锛岀 212 鑺傛妸鐩村睘瀹㈡埛鍑洪噾娴佹按浣滀负鐙珛鍏ュ彛琛ラ綈闂幆娴嬭瘯璇佹嵁銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `FlowController` 鐢熶骇閫昏緫銆佹櫘閫氬鎴锋湰浜哄嚭閲戞祦姘淬?佷唬鐞嗚处鍙锋甯哥洿灞炲鎴峰嚭閲戞祦姘淬?佺洿灞炰唬鐞嗗叆鍑洪噾娴佹按鎴栨暟鎹簱缁撴瀯銆?
- 鍚庣画缁х画鎸夌嫭绔嬮棴鐜祴璇曞璁＄洿灞炰唬鐞嗗叆閲戝拰鐩村睘浠ｇ悊鍑洪噾娴佹按鍏ュ彛銆?

## 213. 2026-07-09 鍓嶅彴鐩村睘浠ｇ悊鍏ュ嚭閲戞祦姘寸敵璇蜂汉杈圭晫娴嬭瘯闂幆

### 鏈澶勭悊鐩爣
- 涓? `FlowController::directDepositFlowSearch` 鍜? `directWithdrawalFlowSearch` 琛ラ綈鐩村睘浠ｇ悊娴佹按鐙珛闂幆娴嬭瘯锛岀‘璁? `direct_agents_deposit` 涓? `direct_agents_withdraw` 鍧囧彧鍏佽浠ｇ悊璐﹀彿 `account_type=1` 璇诲彇銆?
- 楠岃瘉鏅?氬鎴峰嵆浣垮悕涓嬪瓨鍦? `parent_id` 鐩村睘浠ｇ悊瀛愯处鍙凤紝涓旇浠ｇ悊瀛愯处鍙锋湁鐪熷疄 `deposit_records` 鎴? `withdraw_records` 璁板綍锛屼篃涓嶈兘閫氳繃鐜颁唬鐩村睘浠ｇ悊娴佹按鎺ュ彛璇诲彇銆?
- 楠岃瘉鏃? Web `user/flow/directAgentsDepositFlowSearch` 涓? `user/flow/directAgentsWithdrawalFlowSearch` 鍚屾牱鎷掔粷鏅?氬鎴疯鍙栫洿灞炰唬鐞嗗叆鍑洪噾娴佹按銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontFlowDirectAgentApplicantBoundaryClosureModuleTest.php`
  - 鏂板鏅?氬鎴疯闂幇浠ｄ笌鏃х洿灞炰唬鐞嗗叆閲戞祦姘存嫆缁濇牱渚嬨??
  - 鏂板鏅?氬鎴疯闂幇浠ｄ笌鏃х洿灞炰唬鐞嗗嚭閲戞祦姘存嫆缁濇牱渚嬨??
  - 涓や釜鏍蜂緥鍒嗗埆鏋勯?犵湡瀹炴櫘閫氬鎴枫?佺洿灞炰唬鐞嗗瓙璐﹀彿銆佺湡瀹? `deposit_records` 鎴? `withdraw_records` 娴佹按锛屽苟鏂█鎷掔粷鍝嶅簲涓嶅寘鍚洰鏍囪鍗曞彿鍜屼唬鐞嗗瓙璐﹀彿鍚嶇О銆?

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontFlowDirectAgentApplicantBoundaryClosureModuleTest.php` 鍦ㄦ柊澧炴竻鍗曟柇瑷?鍓嶇洿鎺ラ?氳繃锛岃瘉鏄庣 208 鑺傚凡鍔犲叆鐨? `accountFlow` 鍏变韩 `account_type=1` 鏍￠獙瑕嗙洊浜? `direct_agents_deposit` 鍜? `direct_agents_withdraw` 涓ょ被鍏ュ彛銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 213 鑺傘??
- GREEN锛氳拷鍔犵 213 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontFlowDirectAgentApplicantBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹? `parent_id` 鐩村睘浠ｇ悊瀛愯处鍙枫?佺湡瀹炲叆閲戝拰鍑洪噾娴佹按銆佺幇浠? `/api/front/flows/direct-agent-deposits`銆乣/api/front/flows/direct-agent-withdrawals` 浠ュ強鏃? `user/flow/directAgentsDepositFlowSearch`銆乣user/flow/directAgentsWithdrawalFlowSearch` 鍥涗釜鍏ュ彛銆?
- 绗? 212 鑺傝ˉ榻愮洿灞炲鎴峰嚭閲戞祦姘存祴璇曪紝绗? 213 鑺傝ˉ榻愮洿灞炰唬鐞嗗叆鍑洪噾娴佹按娴嬭瘯锛岃祫閲戞祦姘磋鍙栧叆鍙ｇ殑鏅?氬鎴风敵璇蜂汉杈圭晫娴嬭瘯瑕嗙洊缁х画鏀舵嫝銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `FlowController` 鐢熶骇閫昏緫銆佹櫘閫氬鎴锋湰浜鸿祫閲戞祦姘淬?佷唬鐞嗚处鍙锋甯哥洿灞炰唬鐞嗗叆鍑洪噾娴佹按銆佸墠绔祦姘撮〉绛炬垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤涓嬩竴涓墠鍙颁唬鐞?/鏅?氱敤鎴?/鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 214. 2026-07-09 鍓嶅彴鏃уぇ浠ｇ悊浠ｇ悊鍒楄〃涓庤鍗曞垪琛ㄥ弬鏁板啋鍏呮祴璇曢棴鐜?

### 鏈澶勭悊鐩爣
- 涓烘棫澶т唬鐞嗕唬鐞嗗垪琛ㄤ笌璁㈠崟鍒楄〃琛ラ綈鍙傛暟鍐掑厖闂幆娴嬭瘯锛岀‘璁ょ 207 鑺傜殑 `currentBigAgent` session-only 韬唤鏉ユ簮瑕嗙洊 `proxySearch`銆乣proxySearchBySub`銆乣closeOrderSearch` 鍜? `openOrderSearch`銆?
- 楠岃瘉鏅?氬鎴峰嵆浣挎惡甯︾湡瀹? `big_agent_id` 鎴? `bigAgentId`锛屼篃涓嶈兘璇诲彇澶т唬鐞? `sub_agent_ids` 涓嬬殑浠ｇ悊鍒楄〃銆?
- 楠岃瘉鏅?氬鎴峰嵆浣跨煡閬撶湡瀹炲ぇ浠ｇ悊 ID锛屼笖澶т唬鐞嗗彲瑙佷唬鐞嗙綉缁滀笅瀛樺湪鐪熷疄瀹㈡埛浜ゆ槗璁㈠崟锛屼篃涓嶈兘璇诲彇鏃уぇ浠ｇ悊宸插钩浠撴垨鏈钩浠撹鍗曞垪琛ㄣ??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontBigNumberListAndOrderApplicantBoundaryClosureModuleTest.php`
  - 鏂板鏅?氬鎴峰弬鏁板啋鍏呰闂? `user/agents/proxy/proxySearch` 涓? `user/agents/proxy/proxySearchBySub` 鐨勬嫆缁濇牱渚嬨??
  - 鏂板鏅?氬鎴峰弬鏁板啋鍏呰闂? `user/agents/close/closeOrderSearch` 涓? `user/agents/open/openOrderSearch` 鐨勬嫆缁濇牱渚嬨??
  - 鏍蜂緥鏋勯?犵湡瀹? `big_agents` 璁板綍銆佸彲瑙佷唬鐞嗗瓙璐﹀彿銆佸彲瑙佸鎴峰拰鐪熷疄 `user_trades` 寮?骞充粨璁㈠崟锛屽苟鏂█鍝嶅簲涓虹┖涓斾笉鍖呭惈鐩爣浠ｇ悊銆佸鎴锋垨璁㈠崟鍙枫??

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontBigNumberListAndOrderApplicantBoundaryClosureModuleTest.php` 鍦ㄦ柊澧炴竻鍗曟柇瑷?鍓嶇洿鎺ラ?氳繃锛岃瘉鏄庣 207 鑺傚凡绉婚櫎璇锋眰鍙傛暟鍏滃簳鍚庣殑 `currentBigAgent` 瑕嗙洊鏃уぇ浠ｇ悊浠ｇ悊鍒楄〃鍜岃鍗曞垪琛ㄣ??
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 214 鑺傘??
- GREEN锛氳拷鍔犵 214 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontBigNumberListAndOrderApplicantBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹? `big_agents` 璁板綍銆佺湡瀹炲彲瑙佷唬鐞嗗拰瀹㈡埛銆佺湡瀹炲紑骞充粨 `user_trades` 璁㈠崟锛屼互鍙婂洓涓棫澶т唬鐞? Ajax 鍏ュ彛銆?
- 绗? 207 鑺傛敹绱ф棫澶т唬鐞嗘寔浠撴悳绱㈣韩浠芥潵婧愶紝绗? 214 鑺傝ˉ榻愬悓涓?韬唤鏉ユ簮瑙勫垯鍦ㄤ唬鐞嗗垪琛ㄥ拰璁㈠崟鍒楄〃涓婄殑鍓╀綑娴嬭瘯璇佹嵁銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `BigNumberController` 鐢熶骇閫昏緫銆佸ぇ浠ｇ悊璐﹀彿姝ｅ父鐧诲綍銆乻ession 璇诲彇銆佷唬鐞嗗垪琛ㄣ?佽鍗曞垪琛ㄣ?佹寔浠撴悳绱㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤涓嬩竴涓墠鍙颁唬鐞?/鏅?氱敤鎴?/鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 215. 2026-07-09 鍓嶅彴鎸佷粨涓绘眹鎬婚捇鍙栫敵璇蜂汉杈圭晫闂幆

### 鏈澶勭悊鐩爣
- 琛ラ綈 `PositionController::positionSummary` 鐨勯捇鍙栫敵璇蜂汉璐﹀彿绫诲瀷杈圭晫锛岀‘淇濆彧鏈変唬鐞嗚处鍙? `account_type=1` 鑳芥寜 `target_id`銆乣userPId` 鎴? `user_id` 灞曞紑涓嬬骇浠ｇ悊鎸佷粨姹囨?汇??
- 楠岃瘉鏅?氬鎴峰嵆浣垮悕涓嬪瓨鍦? `parent_id` 鐩村睘浠ｇ悊瀛愯处鍙凤紝涔熶笉鑳介?氳繃鐜颁唬 `/api/front/positions/summary` 璇诲彇璇ュ瓙浠ｇ悊鎸佷粨姹囨?汇??
- 楠岃瘉鏃? Web `user/position/positionSummarySearch` 鍚屾牱鎷掔粷鏅?氬鎴烽捇鍙栫洿灞炰唬鐞嗘寔浠撴眹鎬伙紝閬垮厤娉勬紡浠ｇ悊瀛愯处鍙? ID銆佸悕绉板拰姹囨?绘暟鎹??

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/PositionController.php`
  - `positionSummary` 鏀逛负閫氳繃 `legacyFrontUserLogin` 璇诲彇褰撳墠鐧诲綍璁板綍锛屼繚鐣欐湭鐧诲綍鍏煎璁よ瘉閿欒銆?
  - 褰撹姹傜洰鏍囦笉鏄綋鍓嶇敤鎴锋湰浜烘椂锛岄潪浠ｇ悊璐﹀彿杩斿洖 `ResponseCode::PERMISSION_DENIED`锛屼笉鍐嶈繘鍏? `FrontLegacyData::userScopeIds` 鐨勪笅绾т唬鐞嗛捇鍙栭?昏緫銆?
  - 淇濇寔鏅?氬鎴锋湰浜? MT4 姹囨?汇?佷唬鐞嗚处鍙锋甯搁捇鍙栦笅绾т唬鐞嗐?乣parent_id` fallback銆侀潰鍖呭睉閾捐矾銆佸垎椤靛拰姹囨?诲彛寰勪笉鍙樸??
- `tests/Feature/FrontPositionSummaryApplicantBoundaryClosureModuleTest.php`
  - 鏂板鏅?氬鎴疯闂幇浠ｆ寔浠撲富姹囨?婚捇鍙栧叆鍙ｆ嫆缁濇牱渚嬨??
  - 鏂板鏅?氬鎴疯闂棫鎸佷粨涓绘眹鎬绘悳绱㈠叆鍙ｆ嫆缁濇牱渚嬨??
  - 涓や釜鏍蜂緥鍧囨瀯閫犵湡瀹炴櫘閫氬鎴峰拰 `parent_id` 鐩村睘浠ｇ悊瀛愯处鍙凤紝骞舵柇瑷?鎷掔粷鍝嶅簲涓嶅寘鍚洰鏍囧瓙浠ｇ悊 ID 鎴栧悕绉般??

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontPositionSummaryApplicantBoundaryClosureModuleTest.php` 棣栨澶辫触锛岀幇浠ｅ拰鏃т富鎸佷粨姹囨?诲叆鍙ｅ疄闄呴兘杩斿洖 `1000`锛屾湡鏈? `4006`锛岃瘉鏄庢櫘閫氬鎴峰彲鍊熺洿灞炰唬鐞嗚繘鍏ヤ唬鐞嗕笓灞炴寔浠撴眹鎬婚捇鍙栬矾寰勩??
- GREEN锛歚positionSummary` 澧炲姞闈炴湰浜洪捇鍙栫殑浠ｇ悊璐﹀彿 `account_type=1` 鏍￠獙鍚庯紝鏅?氬鎴疯闂幇浠ｅ拰鏃ф寔浠撴眹鎬婚捇鍙栧叆鍙ｅ潎杩斿洖 `ResponseCode::PERMISSION_DENIED`銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 215 鑺傘??
- GREEN锛氳拷鍔犵 215 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontPositionSummaryApplicantBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹? `parent_id` 鐩村睘浠ｇ悊瀛愯处鍙枫?佺幇浠? `/api/front/positions/summary` 鍜屾棫 `user/position/positionSummarySearch` 涓や釜鍏ュ彛銆?
- 绗? 205 鑺傚凡鏀剁揣鐩村睘浠ｇ悊鎸佷粨姹囨?诲叆鍙ｏ紝绗? 206 鑺傚凡鏀剁揣鎸佷粨浜ゆ槗璇︽儏鍏ュ彛锛岀 215 鑺傜户缁棴鍚堜富鎸佷粨姹囨?婚捇鍙栧叆鍙ｇ殑鏅?氬鎴风敵璇蜂汉杈圭晫銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鏅?氬鎴锋湰浜烘寔浠撴眹鎬汇?佷唬鐞嗚处鍙锋甯告寔浠撴眹鎬婚捇鍙栥?佹寔浠撹鎯呫?佹棫椤甸潰鍏ュ彛銆佸墠绔寔浠撻〉鎴栨暟鎹簱缁撴瀯銆?
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤涓嬩竴涓墠鍙颁唬鐞?/鏅?氱敤鎴?/鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 216. 2026-07-09 鍓嶅彴璧勬枡鍏崇郴閾剧敵璇蜂汉杈圭晫闂幆

### 鏈澶勭悊鐩爣
- 琛ラ綈 `ProfileController::relationshipText` 鐨勭敵璇蜂汉鍙鑼冨洿杈圭晫锛岀‘淇濆叧绯婚摼鏌ヨ鍙兘璇诲彇褰撳墠鐢ㄦ埛鏈汉鎴栧綋鍓嶄唬鐞嗗彲瑙佷笅绾с??
- 楠岃瘉鏅?氬鎴峰嵆浣跨煡閬撳叾瀹冪湡瀹炵敤鎴? ID锛屼篃涓嶈兘閫氳繃鐜颁唬 `/api/front/profile/relationship-path` 璇诲彇鏃犲叧鐢ㄦ埛浠ｇ悊閾俱??
- 楠岃瘉鏃? Web `user/relationShipHtml` 鍚屾牱鎷掔粷鏅?氬鎴疯鍙栨棤鍏崇敤鎴峰叧绯婚摼锛岄伩鍏嶆硠婕忎笂绾т唬鐞? ID 鍜岀洰鏍囩敤鎴? ID銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/ProfileController.php`
  - `relationshipText` 鍦ㄨ鍙栫洰鏍囩敤鎴峰墠澧炲姞褰撳墠鐧诲綍浜哄彲瑙佹?ф牎楠屻??
  - 鏂板 `canViewRelationshipTarget`锛氭湰浜哄彲璇诲彇鏈汉鍏崇郴閾撅紱浠ｇ悊璐﹀彿 `account_type=1` 鍙鍙? `FrontLegacyData::userScopeIds` 鑼冨洿鍐呬笅绾э紱鏅?氬鎴疯鍙栭潪鏈汉鐩爣鏃惰繑鍥炵┖鍏崇郴閾俱??
  - 淇濇寔鍏崇郴閾剧敓鎴愰『搴忋?乣family_tree` 浼樺厛銆乣agent_descendants` 涓? `user_infos.parent_id` fallback銆佺幇浠ｅ拰鏃? `real` 瀛楁鍝嶅簲缁撴瀯涓嶅彉銆?
- `tests/Feature/FrontProfileRelationshipApplicantBoundaryClosureModuleTest.php`
  - 鏂板鏅?氬鎴疯闂幇浠ｆ棤鍏崇敤鎴峰叧绯婚摼鎷掔粷鏍蜂緥銆?
  - 鏂板鏅?氬鎴疯闂棫鏃犲叧鐢ㄦ埛鍏崇郴閾? HTML 鎷掔粷鏍蜂緥銆?
  - 涓や釜鏍蜂緥鍧囨瀯閫犵湡瀹炴櫘閫氬鎴枫?佹棤鍏充笂绾т唬鐞嗗拰鐩爣瀹㈡埛锛屽苟鏂█鍝嶅簲 `real` 涓虹┖涓斾笉鍖呭惈鐩爣閾捐矾 ID銆?

### TDD 鎵ц璁板綍
- RED锛歚vendor\bin\phpunit tests\Feature\FrontProfileRelationshipApplicantBoundaryClosureModuleTest.php` 棣栨澶辫触锛岀幇浠ｅ叆鍙ｈ繑鍥? `412160101 -> 412160102`锛屾棫鍏ュ彛杩斿洖 `412160201->412160202`锛岃瘉鏄庢櫘閫氬鎴峰彲鏋氫妇鏃犲叧鐢ㄦ埛浠ｇ悊閾俱??
- GREEN锛歚relationshipText` 澧炲姞鐧诲綍浜哄彲瑙佽寖鍥存牎楠屽悗锛屾櫘閫氬鎴疯闂幇浠ｅ拰鏃ф棤鍏崇敤鎴峰叧绯婚摼鍏ュ彛鍧囪繑鍥炵┖ `real`銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 216 鑺傘??
- GREEN锛氳拷鍔犵 216 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontProfileRelationshipApplicantBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹炴棤鍏充唬鐞嗛摼銆佺幇浠? `/api/front/profile/relationship-path` 鍜屾棫 `user/relationShipHtml` 涓や釜鍏ュ彛銆?
- 绗? 175 鑺傚凡瑕嗙洊鍏崇郴閾? `parent_id` fallback锛岀 216 鑺傜户缁敹绱у叧绯婚摼璇诲彇鍏ュ彛鐨勭敵璇蜂汉鍙鑼冨洿銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鏈汉鍏崇郴閾惧睍绀恒?佷唬鐞嗚处鍙锋甯歌鍙栦笅绾у叧绯婚摼銆佸叧绯婚摼鏂囨湰鏍煎紡銆佽祫鏂欓〉鍏跺畠涓婁紶/淇敼鍏ュ彛鎴栨暟鎹簱缁撴瀯銆?
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤涓嬩竴涓墠鍙颁唬鐞?/鏅?氱敤鎴?/鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 217. 2026-07-09 鍓嶅彴绀煎搧鏀惰揣鍦板潃褰掑睘杈圭晫娴嬭瘯闂幆

### 鏈澶勭悊鐩爣
- 涓? `GiftController::updateAddress` 涓? `GiftController::deleteAddress` 琛ラ綈鐙珛闂幆娴嬭瘯锛岀‘璁ょぜ鍝佹敹璐у湴鍧?鏇存柊鍜屽垹闄ゅ彧鑳戒綔鐢ㄤ簬褰撳墠鐧诲綍鐢ㄦ埛鑷繁鐨勫湴鍧?銆?
- 楠岃瘉鏅?氬鎴峰嵆浣跨煡閬撳叾瀹冪敤鎴风湡瀹炲湴鍧? ID锛屼篃涓嶈兘閫氳繃鐜颁唬 `/api/front/gift-addresses/{address}` 淇敼鎴栧垹闄や粬浜烘敹璐у湴鍧?銆?
- 楠岃瘉鏃? Web `user/address/update` 浼犲叆浠栦汉 `rec_id` 鏃跺悓鏍疯繑鍥炴湭鎵惧埌锛岄伩鍏嶆棫鍙傛暟鍏煎璺緞缁曡繃褰掑睘杩囨护銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontGiftAddressOwnerBoundaryClosureModuleTest.php`
  - 鏂板鏅?氬鎴疯闂幇浠ｇぜ鍝佸湴鍧?鏇存柊鍏ュ彛淇敼浠栦汉鍦板潃鐨勬嫆缁濇牱渚嬨??
  - 鏂板鏅?氬鎴疯闂幇浠ｇぜ鍝佸湴鍧?鍒犻櫎鍏ュ彛鍒犻櫎浠栦汉鍦板潃鐨勬嫆缁濇牱渚嬨??
  - 鏂板鏅?氬鎴烽?氳繃鏃? `user/address/update` 涓? `rec_id` 淇敼浠栦汉鍦板潃鐨勬嫆缁濇牱渚嬨??
  - 鏍蜂緥鍧囨瀯閫犵湡瀹炵櫥褰曞鎴枫?佺湡瀹炲湴鍧?褰掑睘鐢ㄦ埛鍜岀湡瀹? `user_addresses` 璁板綍锛屽苟鏂█鎷掔粷鍚庢暟鎹簱鍘熻褰曟湭琚慨鏀规垨杞垹闄ゃ??

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontGiftAddressOwnerBoundaryClosureModuleTest.php` 鐨勮涓烘牱渚嬪湪鏂板娓呭崟鏂█鍓嶅凡閫氳繃锛岃瘉鏄庣幇鏈? `GiftController::updateAddress` 涓? `GiftController::deleteAddress` 宸查?氳繃 `user_id + id` 鏌ヨ闄愬埗鍦板潃褰掑睘銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 217 鑺傘??
- GREEN锛氳拷鍔犵 217 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontGiftAddressOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹炰粬浜? `user_addresses` 璁板綍銆佺幇浠? `/api/front/gift-addresses/{address}` 鏇存柊/鍒犻櫎鍏ュ彛鍜屾棫 `user/address/update` 鏇存柊鍏ュ彛銆?
- `addressUpdate` 鏃у叆鍙ｇ户缁鐢ㄧ幇浠? `updateAddress` 褰掑睘鏌ヨ锛屽洜姝ゆ棫 `rec_id` 鍏煎鍙傛暟涓嶈兘瓒婃潈淇敼浠栦汉鍦板潃銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `GiftController` 鐢熶骇閫昏緫銆佹湰浜哄湴鍧?鏂板/鏇存柊/鍒犻櫎娴佺▼銆佺ぜ鍝佺敵璇锋祦绋嬨?佸墠绔ぜ鍝佸湴鍧?琛ㄥ崟鎴栨暟鎹簱缁撴瀯銆?
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤涓嬩竴涓墠鍙颁唬鐞?/鏅?氱敤鎴?/鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 218. 2026-07-09 鍓嶅彴绀煎搧鍙戣揣鍘嗗彶褰掑睘杈圭晫娴嬭瘯闂幆

### 鏈澶勭悊鐩爣
- 涓? `GiftController::giftList` 涓? `GiftController::giftSearch` 琛ラ綈鐙珛闂幆娴嬭瘯锛岀‘璁ょぜ鍝佸彂璐у巻鍙插彧鑳借鍙栧綋鍓嶇櫥褰曠敤鎴疯嚜宸辩殑 `gift_shipments` 璁板綍銆?
- 楠岃瘉鐜颁唬 `/api/front/gifts` 鍦ㄥ瓨鍦ㄥ悓鍚嶆敹浠朵汉銆佸悓鍚嶇ぜ鍝佸拰鍚屾棩鏈熷尯闂寸殑浠栦汉鍙戣揣璁板綍鏃讹紝鍙繑鍥炲綋鍓嶇敤鎴疯嚜宸辩殑 `shipped_gifts`銆?
- 楠岃瘉鏃? Web `user/gift/search` 鍚屾牱鍙繑鍥炲綋鍓嶇敤鎴疯嚜宸辩殑鍙戣揣鍘嗗彶锛岄伩鍏嶆棫绀煎搧鍒楄〃閫氳繃绛涢?夋潯浠惰鍒板叾瀹冪敤鎴风墿娴佸崟鍙峰拰鍙戣揣璁板綍銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontGiftShipmentOwnerBoundaryClosureModuleTest.php`
  - 鏂板鐜颁唬绀煎搧鍒楄〃鍙戣揣鍘嗗彶褰掑睘鏍蜂緥銆?
  - 鏂板鏃хぜ鍝佸彂璐ф悳绱㈠綊灞炴牱渚嬨??
  - 涓や釜鏍蜂緥鍧囨瀯閫犵湡瀹炵櫥褰曞鎴枫?佺湡瀹炲叾瀹冨鎴峰拰鍚岀瓫閫夋潯浠朵笅鐨勪袱缁? `gift_shipments` 璁板綍锛屽苟鏂█鍝嶅簲鍙寘鍚湰浜虹墿娴佸崟鍙凤紝涓嶅寘鍚粬浜虹墿娴佸崟鍙枫??

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontGiftShipmentOwnerBoundaryClosureModuleTest.php` 鐨勮涓烘牱渚嬪湪娓呭崟琛ュ綍鍓嶅凡閫氳繃锛岃瘉鏄庣幇鏈? `giftShipmentQuery($userId, $request)` 宸查?氳繃 `gift_shipments.user_id` 闄愬埗鍙戣揣鍘嗗彶褰掑睘銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 218 鑺傘??
- GREEN锛氳拷鍔犵 218 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontGiftShipmentOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹炴湰浜哄拰浠栦汉 `gift_shipments` 璁板綍銆佺幇浠? `/api/front/gifts` 鍜屾棫 `user/gift/search` 涓や釜鍏ュ彛銆?
- 鐜颁唬缁勫悎鎺ュ彛涓殑 `data.shipped_gifts.data` 涓庢棫鍒嗛〉鎺ュ彛涓殑 `data.list.data` 鍧囧彧杩斿洖褰撳墠鐢ㄦ埛鍙戣揣鍘嗗彶銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `GiftController` 鐢熶骇閫昏緫銆佸彲鍏戞崲绀煎搧鐩綍銆佹湰浜虹ぜ鍝佸彂璐у巻鍙茬瓫閫夈?佸悗鍙扮ぜ鍝佸彂鏀?/鍙戣揣娴佺▼鎴栨暟鎹簱缁撴瀯銆?
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤涓嬩竴涓墠鍙颁唬鐞?/鏅?氱敤鎴?/鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 219. 2026-07-09 鍓嶅彴閿?鎴风敵璇峰綊灞炶竟鐣屾祴璇曢棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `CancelController::status` 涓? `CancelController::apply` 琛ラ綈鍙傛暟鍐掑厖褰掑睘杈圭晫娴嬭瘯锛岀‘璁ら攢鎴风姸鎬佽鍙栧拰閿?鎴风敵璇峰垱寤洪兘鍙娇鐢ㄥ綋鍓嶇櫥褰曠敤鎴疯韩浠姐??
- 楠岃瘉鐜颁唬 `/api/front/account/cancellation` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 `user_id` 鎴? `userId` 鍙傛暟锛屼篃鍙繑鍥炲綋鍓嶇敤鎴疯嚜宸辩殑鏈?杩戜竴娆? `cancel_applies` 璁板綍銆?
- 楠岃瘉鐜颁唬 `/api/front/account/cancellation-applications` 鎻愪氦鏃跺嵆浣挎惡甯﹀叾瀹冪敤鎴? ID锛屼篃鍙兘涓哄綋鍓嶇櫥褰曠敤鎴峰垱寤洪攢鎴风敵璇凤紝涓嶈兘鍐欏叆浠栦汉璐﹀彿銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontCancelApplyOwnerBoundaryClosureModuleTest.php`
  - 鏂板閿?鎴风姸鎬佽鍙栧拷鐣ヤ吉閫犵敤鎴? ID 鐨勬牱渚嬨??
  - 鏂板閿?鎴风敵璇峰垱寤哄拷鐣ヤ吉閫犵敤鎴? ID 鐨勬牱渚嬨??
  - 鏍蜂緥鍧囨瀯閫犵湡瀹炲綋鍓嶅鎴枫?佺湡瀹炲叾瀹冨鎴峰拰鐪熷疄 `cancel_applies` 璁板綍锛屽苟鏂█鍝嶅簲鎴栨暟鎹簱鍐欏叆鍙惤鍦ㄥ綋鍓嶇櫥褰曠敤鎴峰悕涓嬨??

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontCancelApplyOwnerBoundaryClosureModuleTest.php` 鐨勮涓烘牱渚嬪湪娓呭崟琛ュ綍鍓嶅凡閫氳繃锛岃瘉鏄庣幇鏈? `legacyFrontUserInfo($request)` 韬唤鏉ユ簮宸茶鐩栫姸鎬佽鍙栧拰鐢宠鍒涘缓銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 219 鑺傘??
- GREEN锛氳拷鍔犵 219 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontCancelApplyOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹炲叾瀹冨鎴枫?佺湡瀹? `cancel_applies` 璁板綍銆佺幇浠? `/api/front/account/cancellation` 鍜? `/api/front/account/cancellation-applications` 涓や釜鍏ュ彛銆?
- 鐘舵?佽鍙栦笉浼氭硠婕忎粬浜洪攢鎴峰師鍥狅紝鐢宠鍒涘缓涓嶄細鍥犺姹傚弬鏁颁腑鐨? `user_id` 鎴? `userId` 鍐欏叆浠栦汉閿?鎴风敵璇枫??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `CancelController` 鐢熶骇閫昏緫銆佹棫閿?鎴烽獙璇佺爜鏍￠獙銆侀噸澶嶅緟瀹℃牎楠屻?佹湭骞充粨/璧勯噾/鍑?鍊兼嫤鎴?佸悗鍙伴攢鎴峰鏍告垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤涓嬩竴涓墠鍙颁唬鐞?/鏅?氱敤鎴?/鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 220. 2026-07-09 鍓嶅彴鏃ч攢鎴锋彁浜ゅ綊灞炶竟鐣屾祴璇曢棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `CancelController::ajaxCancelAccount` 琛ラ綈鏃у墠鍙伴攢鎴锋彁浜ゅ綊灞炶竟鐣屾祴璇曪紝纭鏃? `user/center/ajaxCancelAccount` 鍏ュ彛涔熷彧浣跨敤褰撳墠鐧诲綍鐢ㄦ埛韬唤銆?
- 楠岃瘉鏃у叆鍙ｅ湪韬唤璇併?佹墜鏈哄彿銆侀偖绠便?佸瘑鐮佸拰 `cancelCode` 鍧囧尮閰嶅綋鍓嶇敤鎴锋椂锛屽嵆浣胯姹傛惡甯﹀叾瀹冪敤鎴? `user_id` 鎴? `userId`锛屼篃鍙兘涓哄綋鍓嶇敤鎴峰垱寤洪攢鎴风敵璇枫??
- 楠岃瘉鏃у師鍥犲瓧娈? `cancelRemark` 缁х画鍐欏叆褰撳墠鐢ㄦ埛 `cancel_applies.cancel_remark`锛屼笉鑳借鍙傛暟鍐掑厖鍐欏叆浠栦汉璐﹀彿銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontCancelLegacyApplyOwnerBoundaryClosureModuleTest.php`
  - 鏂板鏃ч攢鎴锋彁浜ゅ拷鐣ヤ吉閫犵敤鎴? ID 鐨勬牱渚嬨??
  - 鏍蜂緥鏋勯?犵湡瀹炲綋鍓嶅鎴枫?佺湡瀹炲叾瀹冨鎴枫?佺湡瀹? `user_auths` 韬唤璇佽褰曞拰鏃? session `cancelCode`锛屽苟鏂█鏃у搷搴旀垚鍔熷悗鏁版嵁搴撳彧鍐欏叆褰撳墠鐢ㄦ埛閿?鎴风敵璇枫??

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontCancelLegacyApplyOwnerBoundaryClosureModuleTest.php` 鐨勮涓烘牱渚嬪湪娓呭崟琛ュ綍鍓嶅凡閫氳繃锛岃瘉鏄庢棫 `ajaxCancelAccount` 宸查?氳繃 `legacyFrontUserInfo($request)` 涓庡綋鍓嶇櫥褰曡褰曞畬鎴愬綊灞炵粦瀹氥??
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 220 鑺傘??
- GREEN锛氳拷鍔犵 220 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontCancelLegacyApplyOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹炲叾瀹冨鎴枫?佺湡瀹? `user_auths` 韬唤璁板綍銆佹棫 session `cancelCode` 鍜屾棫 `user/center/ajaxCancelAccount` 鎻愪氦鍏ュ彛銆?
- 鏃у叆鍙ｅ搷搴? `msg=SUC` 鍚庯紝`cancel_applies` 鍙柊澧炲綋鍓嶇櫥褰曠敤鎴疯褰曪紝涓嶄細鏍规嵁璇锋眰鍙傛暟涓殑 `user_id` 鎴? `userId` 鍐欏叆浠栦汉閿?鎴风敵璇枫??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `CancelController` 鐢熶骇閫昏緫銆佺幇浠ｉ攢鎴锋彁浜ゃ?佹棫楠岃瘉鐮佸彂閫併?佹棫韬唤鏍￠獙瀛楁銆侀噸澶嶅緟瀹℃牎楠屻?佹湭骞充粨/璧勯噾/鍑?鍊兼嫤鎴垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤涓嬩竴涓墠鍙颁唬鐞?/鏅?氱敤鎴?/鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 221. 2026-07-09 鍓嶅彴閿?鎴疯韩浠芥牎楠屽綊灞炶竟鐣屾祴璇曢棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `ProfileController::cancelVerifyInfo` 琛ラ綈閿?鎴峰墠韬唤鏍￠獙褰掑睘杈圭晫娴嬭瘯锛岀‘璁ょ幇浠ｅ拰鏃ц韩浠芥牎楠屽叆鍙ｉ兘鍙牎楠屽綋鍓嶇櫥褰曠敤鎴疯祫鏂欍??
- 楠岃瘉鐜颁唬 `/api/front/profile/verification-cancellation-checks` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 `user_id` 鎴? `userId`锛屽綋鍓嶇敤鎴锋彁浜よ嚜宸辩殑鎵嬫満鍙枫?侀偖绠卞拰韬唤璇佸彿浠嶆寜褰撳墠鐢ㄦ埛閫氳繃銆?
- 楠岃瘉鏃? Web `user/center/cancelVerifyInfo` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 ID锛屾彁浜ゅ叾瀹冪敤鎴锋墜鏈哄彿銆侀偖绠卞拰韬唤璇佸彿涔熶細鎸夊綋鍓嶇敤鎴峰け璐ワ紝涓嶈兘鎶婂叾瀹冪敤鎴疯祫鏂欏綋浣滃綋鍓嶇敤鎴疯祫鏂欓?氳繃銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontCancelVerificationOwnerBoundaryClosureModuleTest.php`
  - 鏂板鐜颁唬閿?鎴疯韩浠芥牎楠屽拷鐣ヤ吉閫犵敤鎴? ID 鐨勯?氳繃鏍蜂緥銆?
  - 鏂板鏃ч攢鎴疯韩浠芥牎楠屾嫆缁濅吉閫犲叾瀹冪敤鎴疯祫鏂欑殑澶辫触鏍蜂緥銆?
  - 鏍蜂緥鍧囨瀯閫犵湡瀹炲綋鍓嶅鎴枫?佺湡瀹炲叾瀹冨鎴峰拰鐪熷疄 `user_auths` 韬唤璇佽褰曪紝骞舵柇瑷?鏍￠獙缁撴灉鍙彈褰撳墠鐧诲綍鐢ㄦ埛璧勬枡褰卞搷銆?

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontCancelVerificationOwnerBoundaryClosureModuleTest.php` 鐨勮涓烘牱渚嬪湪娓呭崟琛ュ綍鍓嶅凡閫氳繃锛岃瘉鏄庣幇鏈? `currentProfileContext($request)` 涓? `UserAuth::where('user_id', $userInfo->user_id)` 宸叉寜褰撳墠鐧诲綍鐢ㄦ埛缁戝畾韬唤鏍￠獙銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 221 鑺傘??
- GREEN锛氳拷鍔犵 221 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontCancelVerificationOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹炲叾瀹冨鎴枫?佺湡瀹? `user_auths` 韬唤璁板綍銆佺幇浠? `/api/front/profile/verification-cancellation-checks` 鍜屾棫 `user/center/cancelVerifyInfo` 涓や釜鍏ュ彛銆?
- 鐜颁唬鍜屾棫閿?鎴疯韩浠芥牎楠岄兘涓嶄細淇′换璇锋眰鍙傛暟涓殑 `user_id` 鎴? `userId` 鏉ュ垏鎹㈡牎楠屽璞°??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `ProfileController` 鐢熶骇閫昏緫銆侀攢鎴烽獙璇佺爜鍙戦?併?佽祫鏂欎慨鏀?/閾惰鍗℃崲缁戦獙璇佺爜銆侀攢鎴锋彁浜ゃ?佸悗鍙伴攢鎴峰鏍告垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤涓嬩竴涓墠鍙颁唬鐞?/鏅?氱敤鎴?/鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 222. 2026-07-09 鍓嶅彴閿?鎴烽獙璇佺爜鍙戦?佸綊灞炶竟鐣屾祴璇曢棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `ProfileController::cancelVerifyPassSendCode` 琛ラ綈閿?鎴烽獙璇佺爜鍙戦?佸綊灞炶竟鐣屾祴璇曪紝纭楠岃瘉鐮佺紦瀛樺彧鎸夊綋鍓嶇櫥褰曠敤鎴峰啓鍏ャ??
- 楠岃瘉鐜颁唬 `/api/front/profile/verification-cancellation/verification-codes` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 `user_id` 鎴? `userId`锛屽彧瑕侀偖绠卞尮閰嶅綋鍓嶇敤鎴凤紝灏卞彧鍐欏叆褰撳墠鐢ㄦ埛 `front_profile_cancel_code:{user_id}` 缂撳瓨銆?
- 楠岃瘉鏃? Web `user/center/cancelVerifyPassSendCode` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 ID锛屾彁浜ゅ叾瀹冪敤鎴烽偖绠变篃浼氬け璐ワ紝涓嶄細缁欏綋鍓嶇敤鎴锋垨鍏跺畠鐢ㄦ埛鍐欏叆閿?鎴烽獙璇佺爜缂撳瓨銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontCancelVerificationCodeOwnerBoundaryClosureModuleTest.php`
  - 鏂板鐜颁唬閿?鎴烽獙璇佺爜鍙戦?佸拷鐣ヤ吉閫犵敤鎴? ID 鐨勭紦瀛樺綊灞炴牱渚嬨??
  - 鏂板鏃ч攢鎴烽獙璇佺爜鍙戦?佹嫆缁濅吉閫犲叾瀹冪敤鎴烽偖绠辩殑鏍蜂緥銆?
  - 鏍蜂緥浣跨敤 `Mail::fake()` 閬垮厤鐪熷疄鍙戜俊锛屽苟鐩存帴鏂█ `front_profile_cancel_code:{user_id}` 缂撳瓨鍙惤鍦ㄥ綋鍓嶇櫥褰曠敤鎴峰悕涓嬨??

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontCancelVerificationCodeOwnerBoundaryClosureModuleTest.php` 鐨勮涓烘牱渚嬪湪娓呭崟琛ュ綍鍓嶅凡閫氳繃锛岃瘉鏄庣幇鏈? `sendLegacyProfileCode($request, 'cancel')` 宸查?氳繃 `currentProfileContext($request)` 鍜屽綋鍓嶇櫥褰曢偖绠卞畬鎴愬綊灞炵粦瀹氥??
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 222 鑺傘??
- GREEN锛氳拷鍔犵 222 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontCancelVerificationCodeOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹炲叾瀹冨鎴枫?佺幇浠? `/api/front/profile/verification-cancellation/verification-codes` 鍜屾棫 `user/center/cancelVerifyPassSendCode` 涓や釜鍏ュ彛銆?
- 鐜颁唬鍏ュ彛鍙啓鍏ュ綋鍓嶇敤鎴? `front_profile_cancel_code:{褰撳墠鐢ㄦ埛 ID}`锛涙棫鍏ュ彛浣跨敤鍏跺畠鐢ㄦ埛閭鏃惰繑鍥? `status=false`锛屼笖涓嶄細鍐欏叆褰撳墠鐢ㄦ埛鎴栧叾瀹冪敤鎴烽獙璇佺爜缂撳瓨銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `ProfileController` 鐢熶骇閫昏緫銆佺湡瀹為偖浠跺彂閫併?佽祫鏂欎慨鏀?/閾惰鍗℃崲缁戦獙璇佺爜銆侀攢鎴疯韩浠芥牎楠屻?侀攢鎴锋彁浜ゆ垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤涓嬩竴涓墠鍙颁唬鐞?/鏅?氱敤鎴?/鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 223. 2026-07-09 鍓嶅彴璧勬枡鏇存柊褰掑睘杈圭晫娴嬭瘯闂幆

### 鏈澶勭悊鐩爣
- 涓? `ProfileController::updateProfile` 琛ラ綈璧勬枡鏇存柊褰掑睘杈圭晫娴嬭瘯锛岀‘璁ょ幇浠ｈ祫鏂欎繚瀛樺叆鍙ｅ彧鏇存柊褰撳墠鐧诲綍鐢ㄦ埛璧勬枡銆?
- 楠岃瘉鐜颁唬 `PATCH /api/front/profile` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 `user_id` 鎴? `userId`锛屼篃鍙兘鍐欏叆褰撳墠鐢ㄦ埛鐨? `user_infos` 鍩虹璧勬枡銆?
- 楠岃瘉韬唤璇佸彿鏇存柊鍚屾牱鍙啓鍏ュ綋鍓嶇敤鎴风殑 `user_auths.id_card_no`锛屼笉鑳介?氳繃璇锋眰鍙傛暟鍐掑厖鏇存柊鍏跺畠鐢ㄦ埛璁よ瘉璧勬枡銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontProfileUpdateOwnerBoundaryClosureModuleTest.php`
  - 鏂板璧勬枡鏇存柊蹇界暐浼?犵敤鎴? ID 鐨勬牱渚嬨??
  - 鏍蜂緥鏋勯?犵湡瀹炲綋鍓嶅鎴枫?佺湡瀹炲叾瀹冨鎴枫?佺湡瀹? `user_infos` 鍜? `user_auths` 璁板綍锛屽苟鏂█鏇存柊鍚庡彧鏈夊綋鍓嶇櫥褰曠敤鎴疯祫鏂欏彉鍖栵紝鍏跺畠鐢ㄦ埛璧勬枡淇濇寔鍘熷?笺??

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontProfileUpdateOwnerBoundaryClosureModuleTest.php` 鐨勮涓烘牱渚嬪湪娓呭崟琛ュ綍鍓嶅凡閫氳繃锛岃瘉鏄庣幇鏈? `request->user('user')` 涓? `$userLogin->userInfo` 宸叉妸璧勬枡鏇存柊缁戝畾鍒板綋鍓嶇櫥褰曠敤鎴枫??
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 223 鑺傘??
- GREEN锛氳拷鍔犵 223 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontProfileUpdateOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹炲叾瀹冨鎴枫?佺幇浠? `PATCH /api/front/profile` 璧勬枡鏇存柊鍏ュ彛锛屼互鍙? `user_infos` 涓? `user_auths` 涓ゅ紶鍐欏叆琛ㄣ??
- 璇锋眰鍙傛暟涓殑 `user_id` 鎴? `userId` 涓嶄細鍒囨崲璧勬枡鏇存柊瀵硅薄锛涘鍚嶃?佺數璇濄?佹?у埆銆佸湴鍧?鍜岃韩浠借瘉鍙峰潎鍙惤鍦ㄥ綋鍓嶇櫥褰曠敤鎴枫??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `ProfileController` 鐢熶骇閫昏緫銆佹棫璧勬枡涓婁紶/璁よ瘉/鑱旂郴鏂瑰紡鍏ュ彛銆佽祫鏂欓〉鍓嶇琛ㄥ崟銆佸瘑鐮佷慨鏀广?侀偖绠?/鎵嬫満鍙蜂慨鏀规垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤涓嬩竴涓墠鍙颁唬鐞?/鏅?氱敤鎴?/鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 224. 2026-07-09 鍓嶅彴鑱旂郴鏂瑰紡鏇存柊褰掑睘杈圭晫娴嬭瘯闂幆

### 鏈澶勭悊鐩爣
- 涓? `ProfileController::updatePhoneEmailInfo` 琛ラ綈鑱旂郴鏂瑰紡鏇存柊褰掑睘杈圭晫娴嬭瘯锛岀‘璁ゆ墜鏈哄彿鍜岄偖绠变慨鏀归兘鍙綔鐢ㄤ簬褰撳墠鐧诲綍鐢ㄦ埛銆?
- 楠岃瘉鐜颁唬 `/api/front/profile/contact-info` 淇敼閭鏃讹紝鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 `user_id` 鎴? `userId`锛屼篃鍙兘鏇存柊褰撳墠鐧诲綍鐢ㄦ埛鐨? `user_logins.email`銆?
- 楠岃瘉鏃? Web `user/center/updatePhoneEmailInfo` 淇敼鎵嬫満鍙锋椂锛屽嵆浣挎惡甯﹀叾瀹冪敤鎴? ID锛屼篃鍙兘鏇存柊褰撳墠鐧诲綍鐢ㄦ埛鐨? `user_infos.phone`銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontProfileContactInfoOwnerBoundaryClosureModuleTest.php`
  - 鏂板鐜颁唬鑱旂郴鏂瑰紡閭淇敼蹇界暐浼?犵敤鎴? ID 鐨勬牱渚嬨??
  - 鏂板鏃ц仈绯绘柟寮忔墜鏈哄彿淇敼蹇界暐浼?犵敤鎴? ID 鐨勬牱渚嬨??
  - 涓や釜鏍蜂緥鍧囨瀯閫犵湡瀹炲綋鍓嶅鎴峰拰鐪熷疄鍏跺畠瀹㈡埛锛屽苟鏂█鍐欏叆鍙惤鍦ㄥ綋鍓嶇櫥褰曠敤鎴凤紝鍏跺畠鐢ㄦ埛閭鍜屾墜鏈哄彿淇濇寔鍘熷?笺??

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontProfileContactInfoOwnerBoundaryClosureModuleTest.php` 鐨勮涓烘牱渚嬪湪娓呭崟琛ュ綍鍓嶅凡閫氳繃锛岃瘉鏄庣幇鏈? `request->user('user')` 涓? `$userLogin->userInfo` 宸叉妸鑱旂郴鏂瑰紡鏇存柊缁戝畾鍒板綋鍓嶇櫥褰曠敤鎴枫??
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 224 鑺傘??
- GREEN锛氳拷鍔犵 224 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontProfileContactInfoOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹炲叾瀹冨鎴枫?佺幇浠? `/api/front/profile/contact-info` 鍜屾棫 `user/center/updatePhoneEmailInfo` 涓や釜鍏ュ彛銆?
- 璇锋眰鍙傛暟涓殑 `user_id` 鎴? `userId` 涓嶄細鍒囨崲鑱旂郴鏂瑰紡鏇存柊瀵硅薄锛涢偖绠卞彧鏇存柊褰撳墠鐢ㄦ埛鐧诲綍璁板綍锛屾墜鏈哄彿鍙洿鏂板綋鍓嶇敤鎴疯祫鏂欒褰曘??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `ProfileController` 鐢熶骇閫昏緫銆佽仈绯绘柟寮忛獙璇佺爜鍙戦?併?佽祫鏂欏熀纭?瀛楁鏇存柊銆佷笂浼犺璇佸叆鍙ｃ?佽祫鏂欓〉鍓嶇琛ㄥ崟鎴栨暟鎹簱缁撴瀯銆?
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤涓嬩竴涓墠鍙颁唬鐞?/鏅?氱敤鎴?/鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 225. 2026-07-09 鍓嶅彴璧勬枡淇敼楠岃瘉鐮佸彂閫佸綊灞炶竟鐣屾祴璇曢棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `ProfileController::updVerifyPassSendCode` 琛ラ綈璧勬枡淇敼楠岃瘉鐮佸彂閫佸綊灞炶竟鐣屾祴璇曪紝纭楠岃瘉鐮佺紦瀛樺彧鎸夊綋鍓嶇櫥褰曠敤鎴峰啓鍏ャ??
- 楠岃瘉鐜颁唬 `/api/front/profile/verification-password/verification-codes` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 `user_id` 鎴? `userId`锛屽彧瑕侀偖绠卞尮閰嶅綋鍓嶇敤鎴凤紝灏卞彧鍐欏叆褰撳墠鐢ㄦ埛 `front_profile_updverify_code:{user_id}` 缂撳瓨銆?
- 楠岃瘉鏃? Web `user/center/updVerifyPassSendCode` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 ID锛屾彁浜ゅ叾瀹冪敤鎴烽偖绠变篃浼氬け璐ワ紝涓嶄細缁欏綋鍓嶇敤鎴锋垨鍏跺畠鐢ㄦ埛鍐欏叆璧勬枡淇敼楠岃瘉鐮佺紦瀛樸??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontProfileUpdateVerificationCodeOwnerBoundaryClosureModuleTest.php`
  - 鏂板鐜颁唬璧勬枡淇敼楠岃瘉鐮佸彂閫佸拷鐣ヤ吉閫犵敤鎴? ID 鐨勭紦瀛樺綊灞炴牱渚嬨??
  - 鏂板鏃ц祫鏂欎慨鏀归獙璇佺爜鍙戦?佹嫆缁濅吉閫犲叾瀹冪敤鎴烽偖绠辩殑鏍蜂緥銆?
  - 鏍蜂緥浣跨敤 `Mail::fake()` 閬垮厤鐪熷疄鍙戜俊锛屽苟鐩存帴鏂█ `front_profile_updverify_code:{user_id}` 缂撳瓨鍙惤鍦ㄥ綋鍓嶇櫥褰曠敤鎴峰悕涓嬨??

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontProfileUpdateVerificationCodeOwnerBoundaryClosureModuleTest.php` 鐨勮涓烘牱渚嬪湪娓呭崟琛ュ綍鍓嶅凡閫氳繃锛岃瘉鏄庣幇鏈? `sendLegacyProfileCode($request, 'updverify')` 宸查?氳繃 `currentProfileContext($request)` 鍜屽綋鍓嶇櫥褰曢偖绠卞畬鎴愬綊灞炵粦瀹氥??
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 225 鑺傘??
- GREEN锛氳拷鍔犵 225 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontProfileUpdateVerificationCodeOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹炲叾瀹冨鎴枫?佺幇浠? `/api/front/profile/verification-password/verification-codes` 鍜屾棫 `user/center/updVerifyPassSendCode` 涓や釜鍏ュ彛銆?
- 鐜颁唬鍏ュ彛鍙啓鍏ュ綋鍓嶇敤鎴? `front_profile_updverify_code:{褰撳墠鐢ㄦ埛 ID}`锛涙棫鍏ュ彛浣跨敤鍏跺畠鐢ㄦ埛閭鏃惰繑鍥? `status=false`锛屼笖涓嶄細鍐欏叆褰撳墠鐢ㄦ埛鎴栧叾瀹冪敤鎴烽獙璇佺爜缂撳瓨銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `ProfileController` 鐢熶骇閫昏緫銆佺湡瀹為偖浠跺彂閫併?佽仈绯绘柟寮忔洿鏂般?佽祫鏂欏熀纭?瀛楁鏇存柊銆佷笂浼犺璇佸叆鍙ｆ垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤涓嬩竴涓墠鍙颁唬鐞?/鏅?氱敤鎴?/鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 226. 2026-07-09 鍓嶅彴閾惰鍗℃崲缁戦獙璇佺爜鍙戦?佸綊灞炶竟鐣屾祴璇曢棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `ProfileController::changeBankCardSendCode` 琛ラ綈閾惰鍗℃崲缁戦獙璇佺爜鍙戦?佸綊灞炶竟鐣屾祴璇曪紝纭楠岃瘉鐮佺紦瀛樺彧鎸夊綋鍓嶇櫥褰曠敤鎴峰啓鍏ャ??
- 楠岃瘉鐜颁唬 `/api/front/profile/bank-card-change/verification-codes` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 `user_id` 鎴? `userId`锛屽彧瑕侀偖绠卞尮閰嶅綋鍓嶇敤鎴凤紝灏卞彧鍐欏叆褰撳墠鐢ㄦ埛 `front_profile_change_code:{user_id}` 缂撳瓨銆?
- 楠岃瘉鏃? Web `user/center/changeBankCardSendCode` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 ID锛屾彁浜ゅ叾瀹冪敤鎴烽偖绠变篃浼氬け璐ワ紝涓嶄細缁欏綋鍓嶇敤鎴锋垨鍏跺畠鐢ㄦ埛鍐欏叆閾惰鍗℃崲缁戦獙璇佺爜缂撳瓨銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontProfileBankChangeVerificationCodeOwnerBoundaryClosureModuleTest.php`
  - 鏂板鐜颁唬閾惰鍗℃崲缁戦獙璇佺爜鍙戦?佸拷鐣ヤ吉閫犵敤鎴? ID 鐨勭紦瀛樺綊灞炴牱渚嬨??
  - 鏂板鏃ч摱琛屽崱鎹㈢粦楠岃瘉鐮佸彂閫佹嫆缁濅吉閫犲叾瀹冪敤鎴烽偖绠辩殑鏍蜂緥銆?
  - 鏍蜂緥浣跨敤 `Mail::fake()` 閬垮厤鐪熷疄鍙戜俊锛屽苟鐩存帴鏂█ `front_profile_change_code:{user_id}` 缂撳瓨鍙惤鍦ㄥ綋鍓嶇櫥褰曠敤鎴峰悕涓嬨??

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontProfileBankChangeVerificationCodeOwnerBoundaryClosureModuleTest.php` 鐨勮涓烘牱渚嬪湪娓呭崟琛ュ綍鍓嶅凡閫氳繃锛岃瘉鏄庣幇鏈? `sendLegacyProfileCode($request, 'change')` 宸查?氳繃 `currentProfileContext($request)` 鍜屽綋鍓嶇櫥褰曢偖绠卞畬鎴愬綊灞炵粦瀹氥??
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 226 鑺傘??
- GREEN锛氳拷鍔犵 226 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontProfileBankChangeVerificationCodeOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹炲叾瀹冨鎴枫?佺幇浠? `/api/front/profile/bank-card-change/verification-codes` 鍜屾棫 `user/center/changeBankCardSendCode` 涓や釜鍏ュ彛銆?
- 鐜颁唬鍏ュ彛鍙啓鍏ュ綋鍓嶇敤鎴? `front_profile_change_code:{褰撳墠鐢ㄦ埛 ID}`锛涙棫鍏ュ彛浣跨敤鍏跺畠鐢ㄦ埛閭鏃惰繑鍥? `status=false`锛屼笖涓嶄細鍐欏叆褰撳墠鐢ㄦ埛鎴栧叾瀹冪敤鎴烽獙璇佺爜缂撳瓨銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `ProfileController` 鐢熶骇閫昏緫銆佺湡瀹為偖浠跺彂閫併?侀摱琛屽崱鎹㈢粦璧勬枡鎻愪氦銆侀摱琛屽崱鎹㈢粦鏍￠獙銆佽祫鏂欏熀纭?瀛楁鏇存柊鎴栨暟鎹簱缁撴瀯銆?
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤涓嬩竴涓墠鍙颁唬鐞?/鏅?氱敤鎴?/鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 227. 2026-07-09 鍓嶅彴閾惰鍗℃崲缁戦偖绠辨牎楠屽綊灞炶竟鐣屾祴璇曢棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `ProfileController::changeBankCardVerifyCode` 琛ラ綈閾惰鍗℃崲缁戝墠閭鏍￠獙褰掑睘杈圭晫娴嬭瘯锛岀‘璁ゆ牎楠屽璞″彧鏉ヨ嚜褰撳墠鐧诲綍鐢ㄦ埛銆?
- 楠岃瘉鐜颁唬 `/api/front/profile/bank-card-change/verification-checks` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 `user_id` 鎴? `userId`锛屾彁浜ゅ綋鍓嶇敤鎴烽偖绠变粛鎸夊綋鍓嶇敤鎴烽?氳繃銆?
- 楠岃瘉鏃? Web `user/center/changeBankCardVerifyCode` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 ID锛屾彁浜ゅ叾瀹冪敤鎴烽偖绠变篃浼氭寜褰撳墠鐢ㄦ埛澶辫触锛屼笉鑳芥妸鍏跺畠鐢ㄦ埛閭褰撲綔褰撳墠鐢ㄦ埛璧勬枡閫氳繃銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontProfileBankChangeVerificationCheckOwnerBoundaryClosureModuleTest.php`
  - 鏂板鐜颁唬閾惰鍗℃崲缁戦偖绠辨牎楠屽拷鐣ヤ吉閫犵敤鎴? ID 鐨勯?氳繃鏍蜂緥銆?
  - 鏂板鏃ч摱琛屽崱鎹㈢粦閭鏍￠獙鎷掔粷浼?犲叾瀹冪敤鎴烽偖绠辩殑澶辫触鏍蜂緥銆?
  - 涓や釜鏍蜂緥鍧囨瀯閫犵湡瀹炲綋鍓嶅鎴峰拰鐪熷疄鍏跺畠瀹㈡埛锛屽苟鏂█鏍￠獙缁撴灉鍙彈褰撳墠鐧诲綍鐢ㄦ埛閭褰卞搷銆?

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontProfileBankChangeVerificationCheckOwnerBoundaryClosureModuleTest.php` 鐨勮涓烘牱渚嬪湪娓呭崟琛ュ綍鍓嶅凡閫氳繃锛岃瘉鏄庣幇鏈? `currentProfileContext($request)` 宸叉妸閾惰鍗℃崲缁戦偖绠辨牎楠岀粦瀹氬埌褰撳墠鐧诲綍鐢ㄦ埛銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 227 鑺傘??
- GREEN锛氳拷鍔犵 227 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontProfileBankChangeVerificationCheckOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹炲叾瀹冨鎴枫?佺幇浠? `/api/front/profile/bank-card-change/verification-checks` 鍜屾棫 `user/center/changeBankCardVerifyCode` 涓や釜鍏ュ彛銆?
- 璇锋眰鍙傛暟涓殑 `user_id` 鎴? `userId` 涓嶄細鍒囨崲閭鏍￠獙瀵硅薄锛涙棫鍏ュ彛浣跨敤鍏跺畠鐢ㄦ埛閭鏃惰繑鍥? `msg=FAIL`銆乣err=useremail`銆乣col=useremail`銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `ProfileController` 鐢熶骇閫昏緫銆侀摱琛屽崱鎹㈢粦楠岃瘉鐮佸彂閫併?侀摱琛屽崱鎹㈢粦璧勬枡鎻愪氦銆佺湡瀹為偖浠跺彂閫佹垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤涓嬩竴涓墠鍙颁唬鐞?/鏅?氱敤鎴?/鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 228. 2026-07-09 鍓嶅彴鑱旂郴鏂瑰紡鍞竴鎬ф牎楠屽綊灞炶竟鐣屾祴璇曢棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `ProfileController::updateVerifyInfo` 琛ラ綈鎵嬫満鍙峰拰閭鍞竴鎬ф牎楠岀殑褰掑睘杈圭晫娴嬭瘯锛岀‘璁ゆ帓闄ゅ璞″缁堟槸褰撳墠鐧诲綍鐢ㄦ埛銆?
- 楠岃瘉鐜颁唬 `/api/front/profile/verification-checks` 鏍￠獙褰撳墠鐢ㄦ埛鑷繁鐨勬墜鏈哄彿鏃讹紝鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 `user_id` 鎴? `userId`锛屼篃涓嶄細鎶婂綋鍓嶆墜鏈哄彿璇垽涓洪噸澶嶃??
- 楠岃瘉鏃? Web `user/center/updateVerifyInfo` 鏍￠獙鍏跺畠鐢ㄦ埛閭鏃讹紝鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 ID锛屼篃浼氭寜褰撳墠鐢ㄦ埛鎺掗櫎瑙勫垯杩斿洖閲嶅锛屼笉鑳介?氳繃浼?犲弬鏁版妸鍏跺畠鐢ㄦ埛鎺掗櫎鎺夈??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontProfileContactUniquenessOwnerBoundaryClosureModuleTest.php`
  - 鏂板鐜颁唬鎵嬫満鍙峰敮涓?鎬ф牎楠屽拷鐣ヤ吉閫犵敤鎴? ID 鐨勯?氳繃鏍蜂緥銆?
  - 鏂板鏃ч偖绠卞敮涓?鎬ф牎楠屾嫆缁濅吉閫犲叾瀹冪敤鎴锋帓闄ゅ璞＄殑澶辫触鏍蜂緥銆?
  - 涓や釜鏍蜂緥鍧囨瀯閫犵湡瀹炲綋鍓嶅鎴峰拰鐪熷疄鍏跺畠瀹㈡埛锛屽苟鏂█鍞竴鎬ф牎楠屽彧鎺掗櫎褰撳墠鐧诲綍鐢ㄦ埛銆?

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontProfileContactUniquenessOwnerBoundaryClosureModuleTest.php` 鐨勮涓烘牱渚嬪湪娓呭崟琛ュ綍鍓嶅凡閫氳繃锛岃瘉鏄庣幇鏈? `currentProfileContext($request)` 宸叉妸鎵嬫満鍙峰拰閭鍞竴鎬ф牎楠岀殑鎺掗櫎瀵硅薄缁戝畾鍒板綋鍓嶇櫥褰曠敤鎴枫??
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 228 鑺傘??
- GREEN锛氳拷鍔犵 228 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontProfileContactUniquenessOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹炲叾瀹冨鎴枫?佺幇浠? `/api/front/profile/verification-checks` 鍜屾棫 `user/center/updateVerifyInfo` 涓や釜鍏ュ彛銆?
- 璇锋眰鍙傛暟涓殑 `user_id` 鎴? `userId` 涓嶄細鍒囨崲鍞竴鎬ф牎楠岀殑鎺掗櫎瀵硅薄锛涘綋鍓嶇敤鎴疯嚜宸辩殑鎵嬫満鍙峰彲閫氳繃锛屽叾瀹冪敤鎴烽偖绠变粛杩斿洖閲嶅銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `ProfileController` 鐢熶骇閫昏緫銆佽仈绯绘柟寮忔洿鏂般?佽祫鏂欎慨鏀归獙璇佺爜銆侀摱琛屽崱鎹㈢粦楠岃瘉鐮併?佽祫鏂欓〉鍓嶇琛ㄥ崟鎴栨暟鎹簱缁撴瀯銆?
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤涓嬩竴涓墠鍙颁唬鐞?/鏅?氱敤鎴?/鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 229. 2026-07-09 鍓嶅彴澶村儚涓婁紶褰掑睘杈圭晫娴嬭瘯闂幆

### 鏈澶勭悊鐩爣
- 涓? `ProfileController::uploadAvatar` 涓? `ProfileController::uploadHeadImg` 琛ラ綈澶村儚涓婁紶褰掑睘杈圭晫娴嬭瘯锛岀‘璁ゅご鍍忔枃浠跺拰 `user_infos.avatar` 鍙啓鍏ュ綋鍓嶇櫥褰曠敤鎴枫??
- 楠岃瘉鐜颁唬 `/api/front/profile/avatar` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 `user_id` 鎴? `userId`锛屼笂浼犳枃浠朵篃鍙繚瀛樺埌褰撳墠鐢ㄦ埛 `avatars/{user_id}` 鐩綍骞舵洿鏂板綋鍓嶇敤鎴峰ご鍍忋??
- 楠岃瘉鏃? Web `user/center/uploadHeadImg` 浣跨敤鏃у瓧娈? `headimg` 鏃跺悓鏍峰彧鏇存柊褰撳墠鐢ㄦ埛澶村儚锛屼笉浼氳鐩栧叾瀹冪敤鎴峰ご鍍忚矾寰勩??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontProfileAvatarOwnerBoundaryClosureModuleTest.php`
  - 鏂板鐜颁唬澶村儚涓婁紶蹇界暐浼?犵敤鎴? ID 鐨勬牱渚嬨??
  - 鏂板鏃уご鍍忎笂浼犲拷鐣ヤ吉閫犵敤鎴? ID 鐨勬牱渚嬨??
  - 鏍蜂緥浣跨敤 `Storage::fake('public')` 鍜屾祴璇曞悗闀滃儚鐩綍娓呯悊锛屾柇瑷?涓婁紶璺緞鍙惤鍦ㄥ綋鍓嶇櫥褰曠敤鎴风洰褰曪紝鍏跺畠鐢ㄦ埛澶村儚淇濇寔鍘熷?笺??

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontProfileAvatarOwnerBoundaryClosureModuleTest.php` 鐨勮涓烘牱渚嬪湪娓呭崟琛ュ綍鍓嶅凡閫氳繃锛岃瘉鏄庣幇鏈? `request->user('user')` 涓? `$userLogin->userInfo` 宸叉妸鐜颁唬鍜屾棫澶村儚涓婁紶缁戝畾鍒板綋鍓嶇櫥褰曠敤鎴枫??
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 229 鑺傘??
- GREEN锛氳拷鍔犵 229 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontProfileAvatarOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹炲叾瀹冨鎴枫?佺幇浠? `/api/front/profile/avatar` 鍜屾棫 `user/center/uploadHeadImg` 涓や釜鍏ュ彛銆?
- 璇锋眰鍙傛暟涓殑 `user_id` 鎴? `userId` 涓嶄細鍒囨崲澶村儚鏇存柊瀵硅薄锛涚幇浠ｅ拰鏃у叆鍙ｄ笂浼犲悗鐨勫ご鍍忚矾寰勫潎浠ュ綋鍓嶇櫥褰曠敤鎴? `avatars/{褰撳墠鐢ㄦ埛 ID}/` 寮?澶淬??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `ProfileController` 鐢熶骇閫昏緫銆佸ご鍍忓墠绔鍓垨棰勮銆佽祫鏂欏熀纭?瀛楁鏇存柊銆佷笂浼犺璇佸叆鍙ｃ?乸ublic disk 閰嶇疆鎴栨暟鎹簱缁撴瀯銆?
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤涓嬩竴涓墠鍙颁唬鐞?/鏅?氱敤鎴?/鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 230. 2026-07-09 鍓嶅彴鐜颁唬鑱旂郴鏂瑰紡淇敼褰掑睘杈圭晫娴嬭瘯闂幆

### 鏈澶勭悊鐩爣
- 涓? `ProfileController::changePhone` 鍜? `ProfileController::changeEmail` 琛ラ綈鐜颁唬鑱旂郴鏂瑰紡淇敼褰掑睘杈圭晫娴嬭瘯锛岀‘璁ゆ墜鏈哄彿涓庨偖绠变慨鏀归兘鍙綔鐢ㄤ簬褰撳墠鐧诲綍鐢ㄦ埛銆?
- 楠岃瘉鐜颁唬 `/api/front/profile/phone` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 `user_id` 鎴? `userId`锛屼篃鍙兘鏇存柊褰撳墠鐧诲綍鐢ㄦ埛鐨? `user_infos.phone`銆?
- 楠岃瘉鐜颁唬 `/api/front/profile/email` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 `user_id` 鎴? `userId`锛屼篃鍙兘鏇存柊褰撳墠鐧诲綍鐢ㄦ埛鐨? `user_logins.email`銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontProfileModernContactChangeOwnerBoundaryClosureModuleTest.php`
  - 鏂板鐜颁唬鎵嬫満鍙蜂慨鏀瑰拷鐣ヤ吉閫犵敤鎴? ID 鐨勬牱渚嬨??
  - 鏂板鐜颁唬閭淇敼蹇界暐浼?犵敤鎴? ID 鐨勬牱渚嬨??
  - 涓や釜鏍蜂緥鍧囨瀯閫犵湡瀹炲綋鍓嶅鎴峰拰鐪熷疄鍏跺畠瀹㈡埛锛屽苟鏂█鍐欏叆鍙惤鍦ㄥ綋鍓嶇櫥褰曠敤鎴疯褰曪紝鍏跺畠鐢ㄦ埛鎵嬫満鍙峰拰閭淇濇寔鍘熷?笺??

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontProfileModernContactChangeOwnerBoundaryClosureModuleTest.php` 鐨勮涓烘牱渚嬪湪娓呭崟琛ュ綍鍓嶅凡閫氳繃锛岃瘉鏄庣幇鏈? `changePhone` 鍜? `changeEmail` 宸茬粡缁戝畾鍒板綋鍓嶇櫥褰曠敤鎴枫??
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 230 鑺傘??
- GREEN锛氳拷鍔犵 230 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontProfileModernContactChangeOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹炲叾瀹冨鎴枫?佺幇浠? `/api/front/profile/phone` 鍜? `/api/front/profile/email` 涓や釜鍏ュ彛銆?
- 璇锋眰鍙傛暟涓殑 `user_id` 鎴? `userId` 涓嶄細鍒囨崲鎵嬫満鍙锋垨閭淇敼瀵硅薄锛涙墜鏈哄彿鍙洿鏂板綋鍓嶇敤鎴疯祫鏂欒褰曪紝閭鍙洿鏂板綋鍓嶇敤鎴风櫥褰曡褰曘??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `ProfileController` 鐢熶骇閫昏緫銆佹棫鑱旂郴鏂瑰紡鏇存柊鍏ュ彛銆侀獙璇佺爜鍙戦??/鏍￠獙銆佸ご鍍忎笂浼犮?佽祫鏂欏熀纭?瀛楁鏇存柊鎴栨暟鎹簱缁撴瀯銆?
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤涓嬩竴涓墠鍙颁唬鐞嗐?佹櫘閫氱敤鎴锋垨鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 231. 2026-07-09 鍓嶅彴瀹炲悕璁よ瘉鎻愪氦褰掑睘杈圭晫涓? real_name 瀛楁鍏煎娴嬭瘯闂幆

### 鏈澶勭悊鐩爣
- 涓? `ProfileController::submitIdentity` 涓? `ProfileController::uploadIdCard` 琛ラ綈瀹炲悕璁よ瘉鎻愪氦褰掑睘杈圭晫娴嬭瘯锛岀‘璁ょ幇浠ｅ拰鏃т笂浼犲叆鍙ｉ兘鍙啓鍏ュ綋鍓嶇櫥褰曠敤鎴风殑璁よ瘉璧勬枡銆?
- 楠岃瘉鐜颁唬 `/api/front/profile/identity` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 `user_id` 鎴? `userId`锛屼篃鍙兘鍐欏叆褰撳墠鐧诲綍鐢ㄦ埛鐨? `user_auths.id_card_no`銆佽韩浠借瘉姝ｅ弽闈㈠浘鐗囧拰瀹℃牳鐘舵?併??
- 楠岃瘉鏃? Web `user/center/uploadIdCard` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 ID锛屼篃鍙兘鏇存柊褰撳墠鐧诲綍鐢ㄦ埛鐨? `user_infos.user_name` 涓? `user_auths` 瀹炲悕璁よ瘉璧勬枡锛屼笉浼氳鐩栧叾瀹冪敤鎴疯璇佽褰曘??
- 淇鏃у疄鍚嶈璇佷笂浼犲拰娉ㄥ唽鏈嶅姟鍚戝綋鍓嶇湡瀹? `user_auths` 琛ㄤ笉瀛樺湪鐨? `real_name` 鍒楀啓鍏ュ鑷? 500 鐨勫吋瀹圭己鍙ｃ??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontProfileIdentityOwnerBoundaryClosureModuleTest.php`
  - 鏂板鐜颁唬瀹炲悕璁よ瘉鎻愪氦蹇界暐浼?犵敤鎴? ID 鐨勬牱渚嬨??
  - 鏂板鏃у疄鍚嶈璇佷笂浼犲拷鐣ヤ吉閫犵敤鎴? ID 鐨勬牱渚嬨??
  - 鏍蜂緥浣跨敤 `Storage::fake('public')` 鍜屾祴璇曞悗闀滃儚鐩綍娓呯悊锛屾柇瑷?璁よ瘉鍥剧墖璺緞鍙惤鍦ㄥ綋鍓嶇櫥褰曠敤鎴? `auth/{user_id}/identity` 鐩綍锛屽叾瀹冪敤鎴疯璇佽褰曚繚鎸佸師鍊笺??
- `app/Http/Controllers/Front/ProfileController.php`
  - `uploadIdCard` 鍐欏叆 `user_auths` 鍓嶆寜 `Schema::hasColumn('user_auths', 'real_name')` 杩囨护鏃у吋瀹瑰瓧娈碉紝閬垮厤褰撳墠鐪熷疄搴撶己鍒楁椂鎶? SQL 500銆?
- `app/Services/UserRegistrationService.php`
  - 娉ㄥ唽閾捐矾鍒涘缓 `user_auths` 鏃跺悓鏍锋寜鐪熷疄琛ㄧ粨鏋勮繃婊? `real_name` 鍏煎瀛楁锛屼繚鎸佸悓涓?瀛楁瑙勫垯涓?鑷淬??

### TDD 鎵ц璁板綍
- RED锛氭柊澧炵洰鏍囨祴璇曢娆¤繍琛屾椂锛岀幇浠ｅ疄鍚嶈璇佸綊灞炴牱渚嬮?氳繃锛涙棫 `uploadIdCard` 鏆撮湶 `Unknown column 'real_name'` 鐢熶骇缂哄彛锛涙竻鍗曟柇瑷?涔熷洜缂哄皯绗? 231 鑺傚け璐ャ??
- GREEN锛氭寜鐪熷疄 `user_auths` 琛ㄧ粨鏋勮繃婊? `real_name` 鍚庯紝琛屼负鏍蜂緥閫氳繃锛涜拷鍔犵 231 鑺傛竻鍗曡褰曞悗鐩爣娴嬭瘯閫氳繃銆?

### 褰撳墠璇佹嵁
- `FrontProfileIdentityOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹炲叾瀹冨鎴枫?佺幇浠? `/api/front/profile/identity` 鍜屾棫 `user/center/uploadIdCard` 涓や釜鍏ュ彛銆?
- 璇锋眰鍙傛暟涓殑 `user_id` 鎴? `userId` 涓嶄細鍒囨崲瀹炲悕璁よ瘉鎻愪氦瀵硅薄锛涜韩浠借瘉鍙枫?佽韩浠借瘉鍥剧墖銆佸鏍哥姸鎬佸拰鏃у叆鍙ｅ鍚嶆洿鏂板潎鍙惤鍦ㄥ綋鍓嶇櫥褰曠敤鎴枫??
- 褰撳墠鐪熷疄鏁版嵁搴撴病鏈? `user_auths.real_name` 鏃讹紝鏃т笂浼犲叆鍙ｅ拰娉ㄥ唽鏈嶅姟涓嶄細鍐嶅悜涓嶅瓨鍦ㄥ垪鍐欏叆锛涘鏋滄棫搴撳瓨鍦ㄨ鍒楋紝浠嶅彲淇濈暀鍏煎鍐欏叆銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩閾惰鍗¤璇併?侀摱琛屽崱鎹㈢粦鎻愪氦銆佸悗鍙板疄鍚嶈璇佸鏍搞?佽璇佸浘鐗囧墠绔瑙堟垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤閾惰鍗¤璇併?侀摱琛屽崱鎹㈢粦鎻愪氦鍙婂叾瀹冨墠鍙颁唬鐞?/鏅?氱敤鎴?/鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 232. 2026-07-09 鍓嶅彴閾惰鍗¤璇佹彁浜ゅ綊灞炶竟鐣屾祴璇曢棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `ProfileController::submitBankCard` 涓? `ProfileController::uploadBankCard` 琛ラ綈閾惰鍗¤璇佹彁浜ゅ綊灞炶竟鐣屾祴璇曪紝纭鐜颁唬鍜屾棫涓婁紶鍏ュ彛閮藉彧鍐欏叆褰撳墠鐧诲綍鐢ㄦ埛鐨勯摱琛屽崱璁よ瘉璧勬枡銆?
- 楠岃瘉鐜颁唬 `/api/front/profile/bank-card` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 `user_id` 鎴? `userId`锛屼篃鍙兘鍐欏叆褰撳墠鐧诲綍鐢ㄦ埛鐨? `user_auths.bank_name`銆乣bank_no`銆乣bank_addr`銆侀摱琛屽崱姝ｅ弽闈㈠浘鐗囧拰瀹℃牳鐘舵?併??
- 楠岃瘉鏃? Web `user/center/uploadBankCard` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 ID锛屼篃鍙兘鏇存柊褰撳墠鐧诲綍鐢ㄦ埛鐨? `user_auths` 閾惰鍗¤璇佽祫鏂欙紝涓嶄細瑕嗙洊鍏跺畠鐢ㄦ埛璁よ瘉璁板綍銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontProfileBankCardOwnerBoundaryClosureModuleTest.php`
  - 鏂板鐜颁唬閾惰鍗¤璇佹彁浜ゅ拷鐣ヤ吉閫犵敤鎴? ID 鐨勬牱渚嬨??
  - 鏂板鏃ч摱琛屽崱璁よ瘉涓婁紶蹇界暐浼?犵敤鎴? ID 鐨勬牱渚嬨??
  - 鏍蜂緥浣跨敤 `Storage::fake('public')` 鍜屾祴璇曞悗闀滃儚鐩綍娓呯悊锛屾柇瑷?閾惰鍗″浘鐗囪矾寰勫彧钀藉湪褰撳墠鐧诲綍鐢ㄦ埛 `auth/{user_id}/bank` 鐩綍锛屽叾瀹冪敤鎴烽摱琛屽崱璁よ瘉璁板綍淇濇寔鍘熷?笺??

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontProfileBankCardOwnerBoundaryClosureModuleTest.php` 鐨勮涓烘牱渚嬪湪娓呭崟琛ュ綍鍓嶅凡閫氳繃锛岃瘉鏄庣幇鏈? `request->user('user')`銆乣currentProfileContext($request)` 涓? `legacyBankCardUpload($request, false)` 宸叉妸閾惰鍗¤璇佹彁浜ょ粦瀹氬埌褰撳墠鐧诲綍鐢ㄦ埛銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 232 鑺傘??
- GREEN锛氳拷鍔犵 232 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontProfileBankCardOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹炲叾瀹冨鎴枫?佺幇浠? `/api/front/profile/bank-card` 鍜屾棫 `user/center/uploadBankCard` 涓や釜鍏ュ彛銆?
- 璇锋眰鍙傛暟涓殑 `user_id` 鎴? `userId` 涓嶄細鍒囨崲閾惰鍗¤璇佹彁浜ゅ璞★紱寮?鎴疯銆侀摱琛屽崱鍙枫?佸紑鎴峰湴鍧?銆侀摱琛屽崱鍥剧墖鍜屽鏍哥姸鎬佸潎鍙惤鍦ㄥ綋鍓嶇櫥褰曠敤鎴枫??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `ProfileController` 鐢熶骇閫昏緫銆侀摱琛屽崱鎹㈢粦鎻愪氦銆侀摱琛屽崱鎹㈢粦楠岃瘉鐮?/閭鏍￠獙銆佸悗鍙伴摱琛屽崱瀹℃牳銆侀摱琛屽崱鍥剧墖鍓嶇棰勮鎴栨暟鎹簱缁撴瀯銆?
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤閾惰鍗℃崲缁戞彁浜ゅ強鍏跺畠鍓嶅彴浠ｇ悊銆佹櫘閫氱敤鎴锋垨鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 233. 2026-07-09 鍓嶅彴閾惰鍗℃崲缁戞彁浜ゅ綊灞炶竟鐣屾祴璇曢棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `ProfileController::submitBankChange` 涓? `ProfileController::uploadChangeBankCard` 琛ラ綈閾惰鍗℃崲缁戞彁浜ゅ綊灞炶竟鐣屾祴璇曪紝纭鐜颁唬鍜屾棫涓婁紶鍏ュ彛閮藉彧鍐欏叆褰撳墠鐧诲綍鐢ㄦ埛鐨勯摱琛屽崱涓存椂鎹㈢粦璧勬枡銆?
- 楠岃瘉鐜颁唬 `/api/front/profile/bank-card-change` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 `user_id` 鎴? `userId`锛屼篃鍙兘鍐欏叆褰撳墠鐧诲綍鐢ㄦ埛鐨? `user_auths.bank_name_tmp`銆乣bank_no_tmp`銆乣bank_addr_tmp`銆侀摱琛屽崱涓存椂姝ｅ弽闈㈠浘鐗囧拰 `bank_status=3`銆?
- 楠岃瘉鏃? Web `user/center/uploadChangeBankCard` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 ID锛屼篃鍙兘鏇存柊褰撳墠鐧诲綍鐢ㄦ埛鐨? `user_auths` 涓存椂鎹㈢粦瀛楁锛屼笉浼氳鐩栧叾瀹冪敤鎴疯璇佽褰曘??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontProfileBankChangeSubmitOwnerBoundaryClosureModuleTest.php`
  - 鏂板鐜颁唬閾惰鍗℃崲缁戞彁浜ゅ拷鐣ヤ吉閫犵敤鎴? ID 鐨勬牱渚嬨??
  - 鏂板鏃ч摱琛屽崱鎹㈢粦涓婁紶蹇界暐浼?犵敤鎴? ID 鐨勬牱渚嬨??
  - 鏍蜂緥浣跨敤 `Storage::fake('public')` 鍜屾祴璇曞悗闀滃儚鐩綍娓呯悊锛屾柇瑷?閾惰鍗℃崲缁戝浘鐗囪矾寰勫彧钀藉湪褰撳墠鐧诲綍鐢ㄦ埛 `auth/{user_id}/bank-change` 鐩綍锛屽叾瀹冪敤鎴蜂复鏃舵崲缁戣褰曚繚鎸佸師鍊笺??

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontProfileBankChangeSubmitOwnerBoundaryClosureModuleTest.php` 鐨勮涓烘牱渚嬪湪娓呭崟琛ュ綍鍓嶅凡閫氳繃锛岃瘉鏄庣幇鏈? `verifiedContactUser($request)`銆乣currentProfileContext($request)` 涓? `legacyBankCardUpload($request, true)` 宸叉妸閾惰鍗℃崲缁戞彁浜ょ粦瀹氬埌褰撳墠鐧诲綍鐢ㄦ埛銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 233 鑺傘??
- GREEN锛氳拷鍔犵 233 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontProfileBankChangeSubmitOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹炲叾瀹冨鎴枫?佺幇浠? `/api/front/profile/bank-card-change` 鍜屾棫 `user/center/uploadChangeBankCard` 涓や釜鍏ュ彛銆?
- 璇锋眰鍙傛暟涓殑 `user_id` 鎴? `userId` 涓嶄細鍒囨崲閾惰鍗℃崲缁戞彁浜ゅ璞★紱涓存椂寮?鎴疯銆佷复鏃堕摱琛屽崱鍙枫?佷复鏃跺紑鎴峰湴鍧?銆佷复鏃堕摱琛屽崱鍥剧墖鍜屾崲缁戠姸鎬佸潎鍙惤鍦ㄥ綋鍓嶇櫥褰曠敤鎴枫??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `ProfileController` 鐢熶骇閫昏緫銆侀摱琛屽崱璁よ瘉鎻愪氦銆侀摱琛屽崱鎹㈢粦楠岃瘉鐮?/閭鏍￠獙銆佸悗鍙伴摱琛屽崱瀹℃牳銆侀摱琛屽崱鍥剧墖鍓嶇棰勮鎴栨暟鎹簱缁撴瀯銆?
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍏跺畠鍓嶅彴浠ｇ悊銆佹櫘閫氱敤鎴锋垨鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 234. 2026-07-09 鍓嶅彴璐︽埛鍑瘉鎻愪氦涓庢煡璇㈠綊灞炶竟鐣屾祴璇曢棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `AccountController::submitVoucher`銆乣AccountController::userVoucherSave` 涓? `AccountController::voucherList` 琛ラ綈鍑瘉鎻愪氦鍜屾煡璇㈠綊灞炶竟鐣屾祴璇曪紝纭鐜颁唬鍜屾棫鍓嶅彴鍑瘉鍏ュ彛閮藉彧璇诲啓褰撳墠鐧诲綍鐢ㄦ埛鏁版嵁銆?
- 楠岃瘉鐜颁唬 `/api/front/account/voucher-submissions` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 `user_id` 鎴? `userId`锛屼篃鍙兘涓哄綋鍓嶇櫥褰曠敤鎴峰垱寤? `voucher_infos` 璁板綍锛屽浘鐗囪矾寰勮惤鍦? `vouchers/{褰撳墠鐢ㄦ埛 ID}`銆?
- 楠岃瘉鏃? Web `user/user_voucher_save` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 ID锛屼篃鍙兘涓哄綋鍓嶇櫥褰曠敤鎴峰垱寤哄嚟璇佽褰曘??
- 楠岃瘉鐜颁唬 `/api/front/account/vouchers` 鏌ヨ鏃跺嵆浣挎惡甯﹀叾瀹冪敤鎴? ID锛屼篃鍙繑鍥炲綋鍓嶇櫥褰曠敤鎴疯嚜宸辩殑鍑瘉鍒楄〃锛屼笉娉勯湶鍏跺畠鐢ㄦ埛鍑瘉澶囨敞鎴栧浘鐗囥??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontAccountVoucherOwnerBoundaryClosureModuleTest.php`
  - 鏂板鐜颁唬鍑瘉鎻愪氦蹇界暐浼?犵敤鎴? ID 鐨勬牱渚嬨??
  - 鏂板鏃у嚟璇佹彁浜ゅ拷鐣ヤ吉閫犵敤鎴? ID 鐨勬牱渚嬨??
  - 鏂板鍑瘉鍒楄〃鏌ヨ蹇界暐浼?犵敤鎴? ID 鐨勬牱渚嬨??
  - 涓婁紶鏍蜂緥浣跨敤 `Storage::fake('public')`锛岀洿鎺ユ柇瑷? `voucher_infos.user_id` 鍜屽嚟璇佸浘鐗囪矾寰勫綊灞炲綋鍓嶇櫥褰曠敤鎴枫??

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontAccountVoucherOwnerBoundaryClosureModuleTest.php` 鐨勮涓烘牱渚嬪湪娓呭崟琛ュ綍鍓嶅凡閫氳繃锛岃瘉鏄庣幇鏈? `currentUserInfo($request)` 涓? `legacyFrontUserInfo($request)` 宸叉妸鍑瘉鎻愪氦鍜屾煡璇㈢粦瀹氬埌褰撳墠鐧诲綍鐢ㄦ埛銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 234 鑺傘??
- GREEN锛氳拷鍔犵 234 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontAccountVoucherOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹炲叾瀹冨鎴枫?佺幇浠? `/api/front/account/voucher-submissions`銆佺幇浠? `/api/front/account/vouchers` 鍜屾棫 `user/user_voucher_save` 涓変釜鍏ュ彛銆?
- 璇锋眰鍙傛暟涓殑 `user_id` 鎴? `userId` 涓嶄細鍒囨崲鍑瘉鎻愪氦鎴栨煡璇㈠璞★紱鏂板鍑瘉銆佸嚟璇佸浘鐗囪矾寰勩?佸娉ㄥ拰鍒楄〃杩斿洖鍧囧彧钀藉湪褰撳墠鐧诲綍鐢ㄦ埛銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `AccountController` 鐢熶骇閫昏緫銆佸悗鍙板嚟璇佸鏍搞?佸嚟璇佸浘鐗囧墠绔瑙堛?佽处鎴蜂綑棰?/璐︽埛缁煎悎銆佹棫璐︽埛绫诲瀷鍒囨崲鎴栨暟鎹簱缁撴瀯銆?
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤璐︽埛绫诲瀷鍒囨崲銆佽处鎴风患鍚?/浣欓鍙婂叾瀹冨墠鍙颁唬鐞嗐?佹櫘閫氱敤鎴锋垨鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 235. 2026-07-09 鍓嶅彴璐︽埛缁煎悎浣欓涓庤处鎴风被鍨嬪垏鎹㈠綊灞炶竟鐣屾祴璇曢棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `AccountController::accountInfo`銆乣AccountController::accountBalance` 涓? `AccountController::changeAccountSave` 琛ラ綈璐︽埛璇诲啓褰掑睘杈圭晫娴嬭瘯锛岀‘璁ょ幇浠ｈ处鎴锋暟鎹鍙栧拰鏃ц处鎴风被鍨嬪垏鎹㈤兘鍙綔鐢ㄤ簬褰撳墠鐧诲綍鐢ㄦ埛銆?
- 楠岃瘉鐜颁唬 `/api/front/account/profile` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 `user_id` 鎴? `userId`锛屼篃鍙繑鍥炲綋鍓嶇櫥褰曠敤鎴风殑璐︽埛缁煎悎鏁版嵁锛屼笉娉勯湶鍏跺畠鐢ㄦ埛濮撳悕鎴栬祫閲戝瓧娈点??
- 楠岃瘉鐜颁唬 `/api/front/account/balance` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 ID锛屼篃鍙繑鍥炲綋鍓嶇櫥褰曠敤鎴蜂綑棰濇暟鎹??
- 楠岃瘉鏃? Web `user/change_account_save` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 ID锛屼篃鍙洿鏂板綋鍓嶇櫥褰曠敤鎴风殑 `user_infos.is_ecn` 涓? `leverage`銆?
- 淇鏃ц处鎴风被鍨嬪垏鎹㈠悜鏁村瀷 `user_infos.updated_by` 鍐欏叆鐢ㄦ埛鍚嶅瓧绗︿覆瀵艰嚧 500 鐨勫吋瀹圭己鍙ｃ??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontAccountProfileOwnerBoundaryClosureModuleTest.php`
  - 鏂板璐︽埛缁煎悎璇诲彇蹇界暐浼?犵敤鎴? ID 鐨勬牱渚嬨??
  - 鏂板璐︽埛浣欓璇诲彇蹇界暐浼?犵敤鎴? ID 鐨勬牱渚嬨??
  - 鏂板鏃ц处鎴风被鍨嬪垏鎹㈠拷鐣ヤ吉閫犵敤鎴? ID 鐨勬牱渚嬨??
- `app/Http/Controllers/Front/AccountController.php`
  - `changeAccountSave` 鏇存柊 `user_infos` 鏃跺皢 `updated_by` 鏀逛负褰撳墠涓氬姟 `user_id`锛屽尮閰嶇湡瀹炴暣鍨嬪垪瀹氫箟锛岄伩鍏嶆棫鍏ュ彛鎻愪氦鏃舵姤 SQL 绫诲瀷閿欒銆?

### TDD 鎵ц璁板綍
- RED锛氭柊澧炵洰鏍囨祴璇曢娆¤繍琛屾椂锛岃处鎴风患鍚堜笌浣欓琛屼负鍙洜 JSON `user_id` 搴忓垪鍖栦负瀛楃涓茶?岄渶璋冩暣鏂█锛涙棫 `changeAccountSave` 鏆撮湶 `updated_by` 鍐欏叆鐢ㄦ埛鍚嶅鑷? 500锛涙竻鍗曟柇瑷?涔熷洜缂哄皯绗? 235 鑺傚け璐ャ??
- GREEN锛歚changeAccountSave` 鏀逛负鍐欏叆褰撳墠涓氬姟鐢ㄦ埛 ID 鍚庯紝琛屼负鏍蜂緥閫氳繃锛涜拷鍔犵 235 鑺傛竻鍗曡褰曞悗鐩爣娴嬭瘯閫氳繃銆?

### 褰撳墠璇佹嵁
- `FrontAccountProfileOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹炲叾瀹冨鎴枫?佺幇浠? `/api/front/account/profile`銆佺幇浠? `/api/front/account/balance` 鍜屾棫 `user/change_account_save` 涓変釜鍏ュ彛銆?
- 璇锋眰鍙傛暟涓殑 `user_id` 鎴? `userId` 涓嶄細鍒囨崲璐︽埛璇诲彇鎴栬处鎴风被鍨嬪垏鎹㈠璞★紱璧勯噾銆佸噣鍊笺?佸鍚嶃?丒CN 鏍囪瘑鍜屾潬鏉嗗潎鍙睘浜庡綋鍓嶇櫥褰曠敤鎴枫??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鍑瘉鎻愪氦/鏌ヨ銆佸叆鍑洪噾銆佹祦姘淬?佸悗鍙拌处鎴峰鏍搞?佽处鎴烽〉闈㈠墠绔睍绀烘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍏ュ嚭閲戙?佹祦姘村強鍏跺畠鍓嶅彴浠ｇ悊銆佹櫘閫氱敤鎴锋垨鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 236. 2026-07-09 鍓嶅彴鍏ラ噾鐢宠涓庡巻鍙插綊灞炶竟鐣屾祴璇曢棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `DepositController::submitDeposit`銆乣DepositController::deposit_request` 涓? `DepositController::depositHistory` 琛ラ綈鍏ラ噾鐢宠鍜屽巻鍙叉煡璇㈠綊灞炶竟鐣屾祴璇曪紝纭鐜颁唬鍜屾棫鍓嶅彴鍏ラ噾鍏ュ彛閮藉彧璇诲啓褰撳墠鐧诲綍鐢ㄦ埛鏁版嵁銆?
- 楠岃瘉鐜颁唬 `/api/front/deposits/submissions` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 `user_id` 鎴? `userId`锛屼篃鍙兘涓哄綋鍓嶇櫥褰曠敤鎴峰垱寤? `deposit_records` 璁板綍銆?
- 楠岃瘉鏃? Web `user/deposit_request` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 ID锛屼篃鍙兘涓哄綋鍓嶇櫥褰曠敤鎴峰垱寤哄叆閲戠敵璇疯褰曘??
- 楠岃瘉鐜颁唬 `/api/front/deposits/history` 鏌ヨ鏃跺嵆浣挎惡甯﹀叾瀹冪敤鎴? ID锛屼篃鍙繑鍥炲綋鍓嶇櫥褰曠敤鎴疯嚜宸辩殑鍏ラ噾鍘嗗彶锛屼笉娉勯湶鍏跺畠鐢ㄦ埛璁㈠崟鍙枫?佸鍚嶆垨閲戦銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontDepositOwnerBoundaryClosureModuleTest.php`
  - 鏂板鐜颁唬鍏ラ噾鎻愪氦蹇界暐浼?犵敤鎴? ID 鐨勬牱渚嬨??
  - 鏂板鏃у叆閲戞彁浜ゅ拷鐣ヤ吉閫犵敤鎴? ID 鐨勬牱渚嬨??
  - 鏂板鍏ラ噾鍘嗗彶鏌ヨ蹇界暐浼?犵敤鎴? ID 鐨勬牱渚嬨??
  - 鏍蜂緥鏄惧紡鍥哄畾鍏ラ噾绯荤粺寮?鍏炽?佹椂闂寸獥鍙ｃ?侀檺棰濆拰娴嬭瘯鏀粯閫氶亾锛岄伩鍏嶇湡瀹炲簱閰嶇疆褰卞搷褰掑睘杈圭晫鍒ゆ柇銆?

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontDepositOwnerBoundaryClosureModuleTest.php` 鐨勪笁涓涓烘牱渚嬪湪娓呭崟琛ュ綍鍓嶅凡閫氳繃锛岃瘉鏄庣幇鏈? `legacyFrontUserInfo($request)` 宸叉妸鐜颁唬鍏ラ噾鎻愪氦銆佹棫鍏ラ噾鎻愪氦鍜屽巻鍙叉煡璇㈢粦瀹氬埌褰撳墠鐧诲綍鐢ㄦ埛銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 236 鑺傘??
- GREEN锛氳拷鍔犵 236 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontDepositOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹炲叾瀹冨鎴枫?佺幇浠? `/api/front/deposits/submissions`銆佺幇浠? `/api/front/deposits/history` 鍜屾棫 `user/deposit_request` 涓変釜鍏ュ彛銆?
- 璇锋眰鍙傛暟涓殑 `user_id` 鎴? `userId` 涓嶄細鍒囨崲鍏ラ噾鍒涘缓鎴栧巻鍙叉煡璇㈠璞★紱鏂板鍏ラ噾璁板綍銆佽鍗曞娉ㄣ?佸巻鍙插垪琛ㄥ拰姹囨?婚噾棰濆潎鍙睘浜庡綋鍓嶇櫥褰曠敤鎴枫??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `DepositController` 鐢熶骇閫昏緫銆佹敮浠橀?氶亾瑙ｆ瀽銆佹敮浠樼綉鍏宠烦杞?佸叆閲戝洖璋冦?佸悗鍙板叆閲戝鏍搞?佸叆閲戦〉闈㈠墠绔睍绀烘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍑洪噾銆佽祫閲戞祦姘村強鍏跺畠鍓嶅彴浠ｇ悊銆佹櫘閫氱敤鎴锋垨鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 237. 2026-07-09 鍓嶅彴鍑洪噾鐢宠涓庡巻鍙插綊灞炶竟鐣屾祴璇曢棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `WithdrawController::submitWithdraw`銆乣WithdrawController::withdraw_request` 涓? `WithdrawController::withdrawHistory` 琛ラ綈鍑洪噾鐢宠鍜屽巻鍙叉煡璇㈠綊灞炶竟鐣屾祴璇曪紝纭鐜颁唬鍜屾棫鍓嶅彴鍑洪噾鍏ュ彛閮藉彧璇诲啓褰撳墠鐧诲綍鐢ㄦ埛鏁版嵁銆?
- 楠岃瘉鐜颁唬 `/api/front/withdrawals/submissions` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 `user_id` 鎴? `userId`锛屼篃鍙兘涓哄綋鍓嶇櫥褰曠敤鎴峰垱寤? `withdraw_records` 璁板綍锛屽苟浣跨敤褰撳墠鐢ㄦ埛鐨勯摱琛屽崱璁よ瘉璧勬枡銆?
- 楠岃瘉鏃? Web `user/withdraw_request` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 ID锛屼篃鍙兘涓哄綋鍓嶇櫥褰曠敤鎴峰垱寤哄嚭閲戠敵璇疯褰曘??
- 楠岃瘉鐜颁唬 `/api/front/withdrawals/history` 鏌ヨ鏃跺嵆浣挎惡甯﹀叾瀹冪敤鎴? ID锛屼篃鍙繑鍥炲綋鍓嶇櫥褰曠敤鎴疯嚜宸辩殑鍑洪噾鍘嗗彶锛屼笉娉勯湶鍏跺畠鐢ㄦ埛璁㈠崟鍙枫?佸鍚嶃?侀摱琛屽崱鎴栭噾棰濄??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontWithdrawOwnerBoundaryClosureModuleTest.php`
  - 鏂板鐜颁唬鍑洪噾鎻愪氦蹇界暐浼?犵敤鎴? ID 鐨勬牱渚嬨??
  - 鏂板鏃у嚭閲戞彁浜ゅ拷鐣ヤ吉閫犵敤鎴? ID 鐨勬牱渚嬨??
  - 鏂板鍑洪噾鍘嗗彶鏌ヨ蹇界暐浼?犵敤鎴? ID 鐨勬牱渚嬨??
  - 鏍蜂緥鏄惧紡鍥哄畾鍑洪噾绯荤粺寮?鍏炽?佹椂闂寸獥鍙ｃ?侀檺棰濄?佹墜缁垂銆佹眹鐜囥?佹寔浠撴鏌ャ?佸疄鍚嶇姸鎬佸拰浣欓锛岄伩鍏嶇湡瀹炲簱閰嶇疆褰卞搷褰掑睘杈圭晫鍒ゆ柇銆?

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontWithdrawOwnerBoundaryClosureModuleTest.php` 鐨勪笁涓涓烘牱渚嬪湪娓呭崟琛ュ綍鍓嶅凡閫氳繃锛岃瘉鏄庣幇鏈? `legacyFrontUserLogin($request)`銆乣legacyFrontUserInfo($request)` 宸叉妸鐜颁唬鍑洪噾鎻愪氦銆佹棫鍑洪噾鎻愪氦鍜屽巻鍙叉煡璇㈢粦瀹氬埌褰撳墠鐧诲綍鐢ㄦ埛銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 237 鑺傘??
- GREEN锛氳拷鍔犵 237 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontWithdrawOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹炲叾瀹冨鎴枫?佺幇浠? `/api/front/withdrawals/submissions`銆佺幇浠? `/api/front/withdrawals/history` 鍜屾棫 `user/withdraw_request` 涓変釜鍏ュ彛銆?
- 璇锋眰鍙傛暟涓殑 `user_id` 鎴? `userId` 涓嶄細鍒囨崲鍑洪噾鍒涘缓鎴栧巻鍙叉煡璇㈠璞★紱鏂板鍑洪噾璁板綍銆侀摱琛屽崱璧勬枡銆佸巻鍙插垪琛ㄥ拰姹囨?婚噾棰濆潎鍙睘浜庡綋鍓嶇櫥褰曠敤鎴枫??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `WithdrawController` 鐢熶骇閫昏緫銆佸瘑鐮佹牎楠屻?佸嚭閲戞墜缁垂瑙勫垯銆侀闄╃巼鏍￠獙銆佹寔浠撴牎楠屻?佸悗鍙板嚭閲戝鏍搞?佸嚭閲戦〉闈㈠墠绔睍绀烘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤璧勯噾娴佹按鍙婂叾瀹冨墠鍙颁唬鐞嗐?佹櫘閫氱敤鎴锋垨鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 238. 2026-07-09 鍓嶅彴鏈汉璧勯噾娴佹按鏌ヨ褰掑睘杈圭晫娴嬭瘯闂幆

### 鏈澶勭悊鐩爣
- 涓? `FlowController::accountFlow`銆乣FlowController::depositFlowSearch`銆乣FlowController::withdrawalFlowSearch` 涓? `FlowController::withdrawApplyFlowSearch` 琛ラ綈鏈汉璧勯噾娴佹按褰掑睘杈圭晫娴嬭瘯銆?
- 楠岃瘉鐜颁唬 `/api/front/flows/account` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 `user_id` 鎴? `userId`锛岃仛鍚堟祦姘翠篃鍙繑鍥炲綋鍓嶇櫥褰曠敤鎴疯嚜宸辩殑鍏ラ噾鍜屽嚭閲戞祦姘淬??
- 楠岃瘉鐜颁唬 `/api/front/flows/deposits` 鍦ㄦ惡甯︿笉鍙鐢ㄦ埛 ID 绛涢?夋椂涓嶄細鍒囨崲鍒板叾瀹冪敤鎴锋暟鎹紝鑰屾槸杩斿洖绌虹粨鏋溿??
- 楠岃瘉鏃? Web `user/flow/withdrawalFlowSearch` 涓? `user/flow/withdrawApplyFlowSearch` 鍦ㄦ惡甯︿笉鍙鐢ㄦ埛 ID 绛涢?夋椂鍚屾牱杩斿洖绌虹粨鏋滐紝涓嶆硠闇插叾瀹冪敤鎴峰嚭閲戞祦姘淬??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontFlowOwnScopeOwnerBoundaryClosureModuleTest.php`
  - 鏂板鑱氬悎璐︽埛娴佹按蹇界暐浼?犵敤鎴? ID 骞跺彧杩斿洖褰撳墠鐢ㄦ埛娴佹按鐨勬牱渚嬨??
  - 鏂板鐜颁唬鏈汉鍏ラ噾娴佹按鎷掔粷瓒婃潈鐢ㄦ埛绛涢?変笖涓嶆硠闇插叾瀹冪敤鎴疯褰曠殑鏍蜂緥銆?
  - 鏂板鏃ф湰浜哄嚭閲戞祦姘村拰鍑洪噾鐢宠娴佹按鎷掔粷瓒婃潈鐢ㄦ埛绛涢?変笖涓嶆硠闇插叾瀹冪敤鎴疯褰曠殑鏍蜂緥銆?

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontFlowOwnScopeOwnerBoundaryClosureModuleTest.php` 鐨勮涓烘牱渚嬪湪娓呭崟琛ュ綍鍓嶅凡閫氳繃锛岃瘉鏄庣幇鏈? `accountFlow()` 涓? `typedFlow()` 宸叉妸鏈汉娴佹按鑼冨洿闄愬埗鍦ㄥ綋鍓嶇櫥褰曠敤鎴枫??
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 238 鑺傘??
- GREEN锛氳拷鍔犵 238 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontFlowOwnScopeOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹炲叾瀹冨鎴枫?佺湡瀹? `deposit_records`銆佺湡瀹? `withdraw_records`銆佺幇浠? `/api/front/flows/account`銆佺幇浠? `/api/front/flows/deposits` 浠ュ強鏃? `user/flow/withdrawalFlowSearch`銆乣user/flow/withdrawApplyFlowSearch`銆?
- 鑱氬悎娴佹按璺緞涓嶄娇鐢ㄨ姹傞噷鐨勪吉閫犵敤鎴? ID 鍒囨崲鏌ヨ瀵硅薄锛涘崟绫绘湰浜烘祦姘磋矾寰勫涓嶅彲瑙佺敤鎴? ID 杩藉姞绌虹粨鏋滄潯浠讹紝閬垮厤鎶婂叾瀹冪敤鎴疯褰曟毚闇茬粰褰撳墠鐢ㄦ埛銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `FlowController` 鐢熶骇閫昏緫銆佺洿灞炲鎴?/鐩村睘浠ｇ悊娴佹按銆佹祦姘村鍑轰笅杞姐?佸墠绔祦姘撮〉绛炬垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤浠ｇ悊璧勯噾娴佹按銆佽鍗曟槑缁嗗強鍏跺畠鍓嶅彴浠ｇ悊銆佹櫘閫氱敤鎴锋垨鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 239. 2026-07-09 鍓嶅彴浜ゆ槗璁㈠崟鏈汉鏌ヨ褰掑睘杈圭晫娴嬭瘯闂幆

### 鏈澶勭悊鐩爣
- 涓? `OrderController::openOrders`銆乣OrderController::openOrderSearch`銆乣OrderController::closedOrders` 涓? `OrderController::closeOrderSearch` 琛ラ綈鏈汉浜ゆ槗璁㈠崟鏌ヨ褰掑睘杈圭晫娴嬭瘯銆?
- 楠岃瘉鐜颁唬 `/api/front/orders/open` 鍦ㄦ惡甯︿笉鍙鐢ㄦ埛 `user_id` 鎴? `userId` 绛涢?夋椂涓嶄細鍒囨崲鍒板叾瀹冪敤鎴疯鍗曪紝鑰屾槸杩斿洖绌虹粨鏋溿??
- 楠岃瘉鏃? Web `user/open/openOrderSearch` 涓? `user/close/closeOrderSearch` 鍦ㄦ惡甯︿笉鍙鐢ㄦ埛 ID 绛涢?夋椂鍚屾牱杩斿洖绌虹粨鏋滐紝涓嶆硠闇插叾瀹冪敤鎴锋寔浠撳崟鎴栧钩浠撳崟銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontOrderOwnScopeOwnerBoundaryClosureModuleTest.php`
  - 鏂板鐜颁唬鏈汉鎸佷粨璁㈠崟鎷掔粷瓒婃潈鐢ㄦ埛绛涢?変笖涓嶆硠闇插叾瀹冪敤鎴疯鍗曠殑鏍蜂緥銆?
  - 鏂板鏃ф湰浜烘寔浠撹鍗曟煡璇㈡嫆缁濊秺鏉冪敤鎴风瓫閫変笖涓嶆硠闇插叾瀹冪敤鎴疯鍗曠殑鏍蜂緥銆?
  - 鏂板鏃ф湰浜哄钩浠撹鍗曟煡璇㈡嫆缁濊秺鏉冪敤鎴风瓫閫変笖涓嶆硠闇插叾瀹冪敤鎴疯鍗曠殑鏍蜂緥銆?

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontOrderOwnScopeOwnerBoundaryClosureModuleTest.php` 鐨勪笁涓涓烘牱渚嬪湪娓呭崟琛ュ綍鍓嶅凡閫氳繃锛岃瘉鏄庣幇鏈夎鍗曟煡璇細閫氳繃鏈汉鍙鑼冨洿鎷掔粷涓嶅彲瑙佺敤鎴风瓫閫夈??
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 239 鑺傘??
- GREEN锛氳拷鍔犵 239 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontOrderOwnScopeOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹炲叾瀹冨鎴枫?佺湡瀹? `user_trades`銆佺幇浠? `/api/front/orders/open` 浠ュ強鏃? `user/open/openOrderSearch`銆乣user/close/closeOrderSearch`銆?
- 鏈汉璁㈠崟璺緞瀵逛笉鍙 `user_id` 鎴? `userId` 杩藉姞绌虹粨鏋滄潯浠讹紝閬垮厤鎶婂叾瀹冪敤鎴锋寔浠撳崟銆佸钩浠撳崟銆佺エ鎹彿銆佽处鎴峰悕鎴栬鍗曞娉ㄦ毚闇茬粰褰撳墠鐢ㄦ埛銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `OrderController` 鐢熶骇閫昏緫銆佽鍗曡鎯呫?佽鍗曞鍑恒?佽鍗曞墠绔〉绛炬垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤璁㈠崟璇︽儏銆佷唬鐞嗚祫閲戞祦姘村強鍏跺畠鍓嶅彴浠ｇ悊銆佹櫘閫氱敤鎴锋垨鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 240. 2026-07-09 鍓嶅彴浜ゆ槗璁㈠崟璇︽儏寮瑰眰褰掑睘杈圭晫娴嬭瘯闂幆

### 鏈澶勭悊鐩爣
- 涓? `OrderController::openOrderDetail` 涓? `OrderController::closeOrderDetail` 琛ラ綈鏃у墠鍙拌鍗曡鎯呭脊灞傚綊灞炶竟鐣屾祴璇曘??
- 楠岃瘉鏃? Web `open/order_detail/{orderId}/{orderType}/{role}` 鍙兘鎵撳紑褰撳墠鐧诲綍鐢ㄦ埛鍙鐨勬寔浠撹鍗曡鎯呫??
- 楠岃瘉鏃? Web `close/order_detail/{orderId}/{orderType}/{role}` 鍦ㄨ闂叾瀹冪敤鎴峰钩浠撹鍗曟椂杩斿洖 404锛屼笉娉勯湶鍏跺畠鐢ㄦ埛璁㈠崟鍐呭銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontOrderDetailOwnerBoundaryClosureModuleTest.php`
  - 鏂板褰撳墠鐢ㄦ埛鎸佷粨璁㈠崟璇︽儏鍙甯告覆鏌撶殑鏍蜂緥銆?
  - 鏂板鍏跺畠鐢ㄦ埛鎸佷粨璁㈠崟璇︽儏璁块棶琚嫆缁濅笖涓嶆硠闇茬エ鎹彿銆佺敤鎴峰悕鍜屽娉ㄧ殑鏍蜂緥銆?
  - 鏂板鍏跺畠鐢ㄦ埛骞充粨璁㈠崟璇︽儏璁块棶琚嫆缁濅笖涓嶆硠闇茬エ鎹彿銆佺敤鎴峰悕鍜屽娉ㄧ殑鏍蜂緥銆?

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontOrderDetailOwnerBoundaryClosureModuleTest.php` 鐨勪笁涓涓烘牱渚嬪湪娓呭崟琛ュ綍鍓嶅凡閫氳繃锛岃瘉鏄庣幇鏈夎鎯呮煡璇細閫氳繃 `FrontLegacyData::applyAllowedUserFilter()` 闄愬埗褰撳墠鐧诲綍鐢ㄦ埛鍙鑼冨洿銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 240 鑺傘??
- GREEN锛氳拷鍔犵 240 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontOrderDetailOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹炲叾瀹冨鎴枫?佺湡瀹? `user_trades`銆佹棫 `open/order_detail/{orderId}/{orderType}/{role}` 涓庢棫 `close/order_detail/{orderId}/{orderType}/{role}`銆?
- 鏃ц鎯呭脊灞傛寜褰撳墠鐧诲綍鐢ㄦ埛杩囨护璁㈠崟锛涗笉鍙璁㈠崟杩斿洖 404锛岄伩鍏嶆妸鍏跺畠鐢ㄦ埛鎸佷粨鍗曘?佸钩浠撳崟銆佺エ鎹彿銆佽处鎴峰悕鎴栬鍗曞娉ㄦ覆鏌撳埌 HTML銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `OrderController` 鐢熶骇閫昏緫銆佽鍗曞垪琛ㄣ?佷唬鐞嗚鍗曡鎯呫?佽鍗曞墠绔脊灞傛牱寮忋?佽鍗曞鍑烘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤浠ｇ悊璧勯噾娴佹按銆佷唬鐞嗚鍗曟槑缁嗗強鍏跺畠鍓嶅彴浠ｇ悊銆佹櫘閫氱敤鎴锋垨鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 241. 2026-07-09 鍓嶅彴浠ｇ悊鐩村睘璧勯噾娴佹按褰掑睘杈圭晫娴嬭瘯闂幆

### 鏈澶勭悊鐩爣
- 涓? `FlowController::directDepositFlowSearch` 涓? `FlowController::directWithdrawalFlowSearch` 琛ラ綈浠ｇ悊鐩村睘瀹㈡埛鍜岀洿灞炰唬鐞嗚祫閲戞祦姘村綊灞炶竟鐣屾祴璇曘??
- 楠岃瘉鐜颁唬 `/api/front/flows/direct-deposits` 涓? `/api/front/flows/direct-agent-deposits` 鍗充娇鎼哄甫鍏跺畠浠ｇ悊鏍戠殑 `user_id` 鎴? `userId`锛屼篃鍙繑鍥炵┖缁撴灉锛屼笉鍒囨崲鍒板叾瀹冨垎鏀祦姘淬??
- 楠岃瘉鏃? Web `user/flow/directWithdrawalFlowSearch` 涓? `user/flow/directAgentsWithdrawalFlowSearch` 鍦ㄦ惡甯﹀叾瀹冧唬鐞嗘爲鐢ㄦ埛 ID 绛涢?夋椂鍚屾牱杩斿洖绌虹粨鏋滐紝涓嶆硠闇插叾瀹冨垎鏀嚭閲戞祦姘淬??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontFlowDirectOwnerBoundaryClosureModuleTest.php`
  - 鏂板鐜颁唬鐩村睘瀹㈡埛鍏ラ噾娴佹按鎷掔粷璺ㄤ唬鐞嗘爲鐢ㄦ埛绛涢?夌殑鏍蜂緥銆?
  - 鏂板鏃х洿灞炲鎴峰嚭閲戞祦姘存嫆缁濊法浠ｇ悊鏍戠敤鎴风瓫閫夌殑鏍蜂緥銆?
  - 鏂板鐜颁唬鐩村睘浠ｇ悊鍏ラ噾娴佹按鎷掔粷璺ㄤ唬鐞嗘爲浠ｇ悊绛涢?夌殑鏍蜂緥銆?
  - 鏂板鏃х洿灞炰唬鐞嗗嚭閲戞祦姘存嫆缁濊法浠ｇ悊鏍戜唬鐞嗙瓫閫夌殑鏍蜂緥銆?

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontFlowDirectOwnerBoundaryClosureModuleTest.php` 鐨勫洓涓涓烘牱渚嬪湪娓呭崟琛ュ綍鍓嶅凡閫氳繃锛岃瘉鏄庣幇鏈? `flowScopeUserIds()` 涓? `requestedUserId()` 浼氭妸鐩村睘瀹㈡埛鍜岀洿灞炰唬鐞嗘祦姘撮檺鍒跺湪褰撳墠浠ｇ悊鏍戝唴銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 241 鑺傘??
- GREEN锛氳拷鍔犵 241 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontFlowDirectOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄浠ｇ悊鐧诲綍鎬併?佸悓鏍戠洿灞炲鎴枫?佸悓鏍戠洿灞炰唬鐞嗐?佸叾瀹冧唬鐞嗘爲瀹㈡埛鍜屽叾瀹冧唬鐞嗘爲浠ｇ悊锛屼互鍙婄湡瀹? `deposit_records`銆乣withdraw_records`銆?
- 鐩村睘瀹㈡埛涓庣洿灞炰唬鐞嗘祦姘磋矾寰勯兘瀵逛笉鍙 `user_id` 鎴? `userId` 杩藉姞绌虹粨鏋滄潯浠讹紝閬垮厤鎶婂叾瀹冧唬鐞嗘爲鐨勮鍗曞彿銆佺敤鎴峰悕銆佸叆閲戞垨鍑洪噾璁板綍鏆撮湶缁欏綋鍓嶄唬鐞嗐??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `FlowController` 鐢熶骇閫昏緫銆佹祦姘村鍑轰笅杞姐?佹櫘閫氬鎴锋湰浜烘祦姘淬?佸墠绔祦姘撮〉绛炬垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤浠ｇ悊璁㈠崟鏄庣粏銆佸ぇ浠ｇ悊鍏ュ彛鍙婂叾瀹冨墠鍙颁唬鐞嗐?佹櫘閫氱敤鎴锋垨鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 242. 2026-07-09 鍓嶅彴澶т唬鐞嗗垪琛ㄤ笌璁㈠崟褰掑睘杈圭晫娴嬭瘯闂幆

### 鏈澶勭悊鐩爣
- 涓? `BigNumberController::bigNumberListSearch`銆乣BigNumberController::bigNumberListSearchBySubAgents`銆乣BigNumberController::bigCloseOrderSearch` 涓? `BigNumberController::bigOpenOrderSearch` 琛ラ綈鐪熷疄澶т唬鐞嗙櫥褰曟?佷笅鐨勬暟鎹綊灞炶竟鐣屾祴璇曘??
- 楠岃瘉鏃? Web `user/agents/proxy/proxySearch` 涓? `user/agents/proxy/proxySearchBySub` 鍗充娇鎼哄甫 `sub_agent_ids` 涔嬪鐨勪唬鐞? `userId`锛屼篃杩斿洖绌虹粨鏋滐紝涓嶆硠闇插叾瀹冧唬鐞嗚祫鏂欍??
- 楠岃瘉鏃? Web `user/agents/close/closeOrderSearch` 涓? `user/agents/open/openOrderSearch` 鍗充娇鎼哄甫鍏跺畠浠ｇ悊鏍戝鎴? `userId`锛屼篃杩斿洖绌虹粨鏋滐紝涓嶆硠闇插叾瀹冨鎴峰紑骞充粨璁㈠崟銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontBigNumberOwnerBoundaryClosureModuleTest.php`
  - 鏂板澶т唬鐞嗕唬鐞嗗垪琛ㄦ嫆缁濋厤缃寖鍥村浠ｇ悊绛涢?夌殑鏍蜂緥銆?
  - 鏂板澶т唬鐞嗗钩浠撹鍗曟煡璇㈡嫆缁濋厤缃寖鍥村瀹㈡埛绛涢?夌殑鏍蜂緥銆?
  - 鏂板澶т唬鐞嗘寔浠撹鍗曟煡璇㈡嫆缁濋厤缃寖鍥村瀹㈡埛绛涢?夌殑鏍蜂緥銆?

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontBigNumberOwnerBoundaryClosureModuleTest.php` 鐨勪笁涓涓烘牱渚嬪湪娓呭崟琛ュ綍鍓嶅凡閫氳繃锛岃瘉鏄庣幇鏈? `currentBigAgent()` 鍙俊浠? session 涓殑澶т唬鐞嗚韩浠斤紝涓斿垪琛ㄤ笌璁㈠崟鏌ヨ閮戒細鍙犲姞 `sub_agent_ids` 璁＄畻鍑虹殑鍙鑼冨洿銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 242 鑺傘??
- GREEN锛氳拷鍔犵 242 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontBigNumberOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄 `big_agents` session銆佺湡瀹炲彲瑙佷唬鐞嗐?佸叾瀹冧唬鐞嗐?佸彲瑙佸鎴枫?佸叾瀹冨鎴峰拰鐪熷疄 `user_trades`銆?
- 澶т唬鐞嗗垪琛ㄥ拰寮?骞充粨璁㈠崟璺緞鍗充娇鏀跺埌涓嶅彲瑙? `userId/user_id` 绛涢?夛紝涔熷彧杩斿洖绌鸿〃鏍硷紝閬垮厤鎶? `sub_agent_ids` 涔嬪鐨勪唬鐞嗚祫鏂欍?佸鎴疯处鍙枫?佽鍗曠エ鎹彿鎴栬鍗曞娉ㄦ毚闇茬粰褰撳墠澶т唬鐞嗐??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `BigNumberController` 鐢熶骇閫昏緫銆佸ぇ浠ｇ悊鐧诲綍銆佸瘑鐮佷慨鏀广?佹寔浠撴眹鎬汇?佸墠绔〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤澶т唬鐞嗘寔浠撴眹鎬诲綊灞炶竟鐣屻?佷唬鐞嗕剑閲戞槑缁嗗強鍏跺畠鍓嶅彴浠ｇ悊銆佹櫘閫氱敤鎴锋垨鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 243. 2026-07-09 鍓嶅彴澶т唬鐞嗘寔浠撴眹鎬诲綊灞炶竟鐣屾祴璇曢棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `BigNumberController::bigPositionSummarySearch` 涓? `BigNumberController::bigSubPositionSummaryStats` 琛ラ綈鐪熷疄澶т唬鐞嗙櫥褰曟?佷笅鐨勬寔浠撴眹鎬诲綊灞炶竟鐣屾祴璇曘??
- 楠岃瘉鏃? Web `user/agents/position/positionSummarySearch` 鍗充娇鎼哄甫 `sub_agent_ids` 涔嬪鐨勪唬鐞? `userId`锛屼篃杩斿洖绌虹粨鏋滐紝涓嶆硠闇插叾瀹冧唬鐞嗘寔浠撴眹鎬汇??
- 楠岃瘉鏃? Web `user/agents/position/subAgentsListSearch` 鍦ㄥ悓鏍风殑璺ㄨ寖鍥翠唬鐞嗙瓫閫変笅涔熻繑鍥炵┖缁撴灉锛屼笉娉勯湶鍏跺畠浠ｇ悊 ID銆佸悕绉版垨璧勯噾瀛楁銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontBigNumberPositionOwnerBoundaryClosureModuleTest.php`
  - 鏂板澶т唬鐞嗘寔浠撴眹鎬绘嫆缁濋厤缃寖鍥村浠ｇ悊绛涢?夌殑鏍蜂緥銆?
  - 鏂板澶т唬鐞嗕笅绾ф寔浠撴眹鎬绘嫆缁濋厤缃寖鍥村浠ｇ悊绛涢?夌殑鏍蜂緥銆?

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontBigNumberPositionOwnerBoundaryClosureModuleTest.php` 鐨勪袱涓涓烘牱渚嬪湪娓呭崟琛ュ綍鍓嶅凡閫氳繃锛岃瘉鏄庣幇鏈夊ぇ浠ｇ悊鎸佷粨姹囨?诲叆鍙ｄ細鍦? session 澶т唬鐞嗚寖鍥村唴鍙犲姞鐢ㄦ埛绛涢?夈??
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 243 鑺傘??
- GREEN锛氳拷鍔犵 243 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontBigNumberPositionOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄 `big_agents` session銆佺湡瀹炲彲瑙佷唬鐞嗐?佸叾瀹冧唬鐞嗐?乣user/agents/position/positionSummarySearch` 涓? `user/agents/position/subAgentsListSearch`銆?
- 涓や釜鎸佷粨姹囨?诲叆鍙ｅ嵆浣挎敹鍒颁笉鍙 `userId/user_id`锛屼篃鍙繑鍥炵┖琛ㄦ牸锛岄伩鍏嶆妸 `sub_agent_ids` 涔嬪鐨勪唬鐞? ID銆佺敤鎴峰悕鎴栬祫閲戞寔浠撴眹鎬绘毚闇茬粰褰撳墠澶т唬鐞嗐??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `BigNumberController` 鐢熶骇閫昏緫銆佸ぇ浠ｇ悊浠ｇ悊鍒楄〃銆佸紑骞充粨璁㈠崟銆佺櫥褰曘?佸瘑鐮佷慨鏀广?佸墠绔〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤浠ｇ悊浣ｉ噾鏄庣粏銆佷唬鐞嗗眰绾ц鎯呭強鍏跺畠鍓嶅彴浠ｇ悊銆佹櫘閫氱敤鎴锋垨鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 244. 2026-07-09 鍓嶅彴瀹炴椂杩斾剑鍒楄〃涓庤鎯呭綊灞炶竟鐣屾祴璇曢棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `CommissionController::realTime`銆乣CommissionController::realtimeRebateSearch` 涓? `CommissionController::realtimeRebateDetail` 琛ラ綈浠ｇ悊瀹炴椂杩斾剑褰掑睘杈圭晫娴嬭瘯銆?
- 楠岃瘉鐜颁唬 `/api/front/commissions/realtime` 鍗充娇鎼哄甫鍏跺畠浠ｇ悊鏍戝鎴? `user_id/userId`锛屼篃鍙繑鍥炵┖缁撴灉锛屼笉鍒囨崲鍒板叾瀹冨垎鏀鍗曘??
- 楠岃瘉鏃? Web `user/realtime/realtimeRebateSearch` 涓? `user/realtime/rebate_detail/{orderNo}/{role}` 鍚屾牱鍙厑璁稿綋鍓嶄唬鐞嗘煡鐪嬭嚜宸变唬鐞嗘爲鍐呯殑杩斾剑璁㈠崟鍜岃鎯呫??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontCommissionRealtimeOwnerBoundaryClosureModuleTest.php`
  - 鏂板鐜颁唬瀹炴椂杩斾剑鍒楄〃鎷掔粷璺ㄤ唬鐞嗘爲鐢ㄦ埛绛涢?夌殑鏍蜂緥銆?
  - 鏂板鏃у疄鏃惰繑浣ｅ垪琛ㄦ嫆缁濊法浠ｇ悊鏍戠敤鎴风瓫閫夌殑鏍蜂緥銆?
  - 鏂板鏃у疄鏃惰繑浣ｈ鎯呭彧鑳芥墦寮?褰撳墠浠ｇ悊鏍戣鍗曪紝璁块棶鍏跺畠浠ｇ悊鏍戣鍗曡繑鍥? 404 涓斾笉娉勯湶鍐呭鐨勬牱渚嬨??

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontCommissionRealtimeOwnerBoundaryClosureModuleTest.php` 鐨勪笁涓涓烘牱渚嬪湪娓呭崟琛ュ綍鍓嶅凡閫氳繃锛岃瘉鏄庣幇鏈夊疄鏃惰繑浣ｆ煡璇細鍏堟寜褰撳墠浠ｇ悊鏍戦檺鍒? `user_trades.user_id`锛屽啀鍙犲姞绛涢?夋垨璇︽儏璁㈠崟鍙枫??
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 244 鑺傘??
- GREEN锛氳拷鍔犵 244 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontCommissionRealtimeOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄浠ｇ悊鐧诲綍鎬併?佸悓鏍戝鎴枫?佸叾瀹冧唬鐞嗘爲瀹㈡埛銆佺湡瀹? `user_trades`銆佺湡瀹? `commission_records`銆佺幇浠ｅ疄鏃惰繑浣ｅ垪琛ㄣ?佹棫瀹炴椂杩斾剑鍒楄〃鍜屾棫璇︽儏寮瑰眰銆?
- 瀹炴椂杩斾剑鍒楄〃瀵逛笉鍙 `userId` 杩斿洖绌哄垎椤碉紝璇︽儏寮瑰眰瀵逛笉鍙璁㈠崟杩斿洖 404锛岄伩鍏嶆妸鍏跺畠浠ｇ悊鏍戠殑璁㈠崟鍙枫?佸鎴峰悕绉般?佽鍗曞娉ㄦ垨杩斾剑璁板綍鏆撮湶缁欏綋鍓嶄唬鐞嗐??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `CommissionController` 鐢熶骇閫昏緫銆佽繑浣ｅ巻鍙层?佷剑閲戣浆璐︺?佷剑閲戣浆璐﹀?欓?夈?佸墠绔〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤杩斾剑鍘嗗彶褰掑睘杈圭晫銆佷剑閲戣浆璐﹀綊灞炶竟鐣屻?佷唬鐞嗗眰绾ц鎯呭強鍏跺畠鍓嶅彴浠ｇ悊銆佹櫘閫氱敤鎴锋垨鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 245. 2026-07-09 鍓嶅彴杩斾剑鍘嗗彶褰掑睘杈圭晫娴嬭瘯闂幆

### 鏈澶勭悊鐩爣
- 涓? `CommissionController::history` 琛ラ綈浠ｇ悊杩斾剑鍘嗗彶褰掑睘杈圭晫娴嬭瘯銆?
- 楠岃瘉鐜颁唬 `/api/front/commissions/history` 榛樿鏌ヨ鍙繑鍥炲綋鍓嶄唬鐞嗚嚜宸辩殑 `commission_records.agent_id` 璁板綍锛屼笉娉勯湶鍏跺畠浠ｇ悊杩斾剑娴佹按銆?
- 楠岃瘉鎼哄甫鍏跺畠浠ｇ悊璁板綍鐨? `orderId` 鏃惰繑鍥炵┖鍒嗛〉锛屼笉閫氳繃璁㈠崟鍙峰垏鎹㈠埌鍏跺畠浠ｇ悊鍘嗗彶銆?
- 楠岃瘉 `dataType=transfer` 绛夌被鍨嬬瓫閫夊彧鍦ㄥ綋鍓嶄唬鐞嗚寖鍥村唴鍙犲姞锛屼笉璺ㄤ唬鐞嗚鍙栧叾瀹冭浆璐﹁繑浣ｈ褰曘??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontCommissionHistoryOwnerBoundaryClosureModuleTest.php`
  - 鏂板杩斾剑鍘嗗彶鍒楄〃榛樿鏌ヨ鍜屽叾瀹冧唬鐞? `orderId` 绛涢?夐殧绂绘牱渚嬨??
  - 鏂板 `dataType=transfer` 绫诲瀷绛涢?夐殧绂绘牱渚嬨??
  - 鏂板鏈?缁堟竻鍗曡褰曟柇瑷?锛岀粦瀹氱 245 鑺傞棴鐜??

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontCommissionHistoryOwnerBoundaryClosureModuleTest.php` 鐨勪袱涓涓烘牱渚嬪湪娓呭崟琛ュ綍鍓嶅凡閫氳繃锛岃瘉鏄庣幇鏈? `history()` 鏌ヨ鍏堝浐瀹? `CommissionRecord::where('agent_id', $agentId)`锛屽啀鍙犲姞 `orderId`銆乣dataType` 鍜屾棩鏈熺瓫閫夈??
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 245 鑺傘??
- GREEN锛氳拷鍔犵 245 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontCommissionHistoryOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄浠ｇ悊鐧诲綍鎬併?佸叾瀹冧唬鐞嗐?佺湡瀹? `commission_records`銆佺幇浠? `/api/front/commissions/history`銆乣orderId` 绛涢?夊拰 `dataType` 绛涢?夈??
- 杩斾剑鍘嗗彶鍒楄〃銆佹眹鎬诲拰缁熻鍒嗘瀽鍧囩粦瀹氬綋鍓嶇櫥褰曚唬鐞? ID锛涗笉鍙璁㈠崟鍙锋垨杩斾剑绫诲瀷绛涢?変笉浼氭妸鍏跺畠浠ｇ悊鐨? `unique_id`銆佽鍗曞彿銆佸娉ㄦ垨杩斾剑閲戦甯﹀叆鍝嶅簲銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `CommissionController` 鐢熶骇閫昏緫銆佸疄鏃惰繑浣ｃ?佷剑閲戣浆璐︺?佷剑閲戣浆璐﹀?欓?夈?佸墠绔〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤浣ｉ噾杞处褰掑睘杈圭晫銆佷唬鐞嗗眰绾ц鎯呭強鍏跺畠鍓嶅彴浠ｇ悊銆佹櫘閫氱敤鎴锋垨鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 246. 2026-07-09 鍓嶅彴浣ｉ噾杞处鐩村睘浠ｇ悊褰掑睘杈圭晫娴嬭瘯闂幆

### 鏈澶勭悊鐩爣
- 涓? `CommissionController::transferAgentOptions` 涓? `CommissionController::transfer` 琛ラ綈浣ｉ噾杞处鐩村睘浠ｇ悊褰掑睘杈圭晫娴嬭瘯銆?
- 楠岃瘉鐜颁唬 `/api/front/commissions/transfer-agent-options` 鍙繑鍥炲綋鍓嶄唬鐞嗙洿灞炰笅绾т唬鐞嗭紝涓嶆硠闇插叾瀹冧唬鐞嗘爲浠ｇ悊鎴栫洿灞炴櫘閫氬鎴枫??
- 楠岃瘉鐜颁唬 `/api/front/commissions/transfers` 鏀跺埌鍏跺畠浠ｇ悊鏍? `sub_agent_id` 鏃惰繑鍥炴潈闄愭嫆缁濓紝涓嶆墸鍑忓綋鍓嶄唬鐞嗕綑棰濄?佷笉澧炲姞鐩爣浠ｇ悊浣欓銆佷笉鍐欏叆 DBCT/WBCT 浣ｉ噾娴佹按銆?
- 楠岃瘉鍚屼竴鐧诲綍浠ｇ悊鍚戠湡瀹炵洿灞炰笅绾т唬鐞嗚浆璐︽椂鍙互姝ｅ父鎵ｅ浣欓骞跺啓鍏ヤ袱鏉? `commission_records.data_type=transfer` 瀹¤璁板綍銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontCommissionTransferOwnerBoundaryClosureModuleTest.php`
  - 鏂板杞处鍊欓?夊垪琛ㄥ彧杩斿洖褰撳墠浠ｇ悊鐩村睘涓嬬骇浠ｇ悊鐨勬牱渚嬨??
  - 鏂板璺ㄤ唬鐞嗘爲 `sub_agent_id` 鎷掔粷涓斾綑棰?/娴佹按涓嶅彉鐨勬牱渚嬨??
  - 鏂板鐩村睘浠ｇ悊姝ｅ父杞处銆佷綑棰濇墸澧炲拰 DBCT/WBCT 鍙屾祦姘村啓鍏ユ牱渚嬨??

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontCommissionTransferOwnerBoundaryClosureModuleTest.php` 鐨勪袱涓涓烘牱渚嬪湪娓呭崟琛ュ綍鍓嶅凡閫氳繃锛岃瘉鏄庣幇鏈? `transferAgentOptions()` 鍜? `transfer()` 閮藉鐢? `FrontLegacyData::userScopeIds($agentId, false, 1, true)` 闄愬畾鐩村睘浠ｇ悊鑼冨洿銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 246 鑺傘??
- GREEN锛氳拷鍔犵 246 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontCommissionTransferOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄浠ｇ悊鐧诲綍鎬併?佺洿灞炰笅绾т唬鐞嗐?佸叾瀹冧唬鐞嗘爲浠ｇ悊銆佺洿灞炴櫘閫氬鎴枫?佺幇浠ｈ浆璐﹀?欓?夋帴鍙ｅ拰鐜颁唬浣ｉ噾杞处鍐欏叆鍙ｃ??
- 鍏跺畠浠ｇ悊鏍? `sub_agent_id` 鏃犳硶缁曡繃鎻愪氦鎺ュ彛鐩存帴鍐欏叆杞处锛涙嫆缁濆悗鍙屾柟浣欓鍜? `commission_records` 鍧囦繚鎸佷笉鍙樸??
- 鐩村睘浠ｇ悊姝ｅ父杞处鏃剁敓鎴愪笅绾у叆璐? DBCT 涓庡綋鍓嶄唬鐞嗗嚭璐? WBCT 涓ゆ潯娴佹按锛屽娉ㄤ繚鐣欎笟鍔¤鏄庯紝渚夸簬鍚庡彴鏍稿銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `CommissionController` 鐢熶骇閫昏緫銆佽繑浣ｅ巻鍙层?佸疄鏃惰繑浣ｃ?佹棫鍓嶅彴鐩村睘瀹㈡埛杞处銆佸墠绔〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤浠ｇ悊灞傜骇璇︽儏銆佷唬鐞嗙‘璁?/鍙樻洿鍐欏叆鍙婂叾瀹冨墠鍙颁唬鐞嗐?佹櫘閫氱敤鎴锋垨鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 247. 2026-07-09 鍓嶅彴浠ｇ悊涓嬬骇璇︽儏褰掑睘杈圭晫娴嬭瘯闂幆

### 鏈澶勭悊鐩爣
- 涓? `AgentController::userDetail`銆乣AgentController::legacyUserDetailPage`銆乣AgentController::getParentPath` 涓? `AgentController::directCustDetailList` 琛ラ綈浠ｇ悊涓嬬骇璇︽儏褰掑睘杈圭晫娴嬭瘯銆?
- 楠岃瘉鐜颁唬 `/api/front/users/{user}` 鍙兘璇诲彇褰撳墠浠ｇ悊鏍戝唴鐢ㄦ埛璇︽儏锛岃闂叾瀹冧唬鐞嗘爲鐢ㄦ埛鏃惰繑鍥炴潈闄愭嫆缁濅笖涓嶆硠闇插鍚嶆垨 ID銆?
- 楠岃瘉鏃? Web `show/user_detail/{userId}/{role}` 鍙兘娓叉煋褰撳墠浠ｇ悊鏍戝唴璇︽儏寮瑰眰锛岃闂叾瀹冧唬鐞嗘爲鐢ㄦ埛鏃惰繑鍥? 403銆?
- 楠岃瘉鏃? Web `user/proxy/parentPath` 鍜? `user/proxy/direct_cust_detail_list` 鍦ㄤ紶鍏ュ叾瀹冧唬鐞嗘爲鐩爣鏃跺彧杩斿洖绌鸿矾寰勬垨绌鸿〃锛屼笉娉勯湶鍏跺畠鍒嗘敮鑺傜偣鍜岀洿灞炲鎴锋槑缁嗐??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontAgentDetailOwnerBoundaryClosureModuleTest.php`
  - 鏂板鐜颁唬鐢ㄦ埛璇︽儏鍜屾棫璇︽儏寮瑰眰鎷掔粷鍏跺畠浠ｇ悊鏍戠洰鏍囩殑鏍蜂緥銆?
  - 鏂板鏃т唬鐞嗗眰绾ц矾寰勬嫆缁濆叾瀹冧唬鐞嗘爲鐩爣鐨勬牱渚嬨??
  - 鏂板鏃х洿灞炲鎴锋槑缁嗚〃鎷掔粷鍏跺畠浠ｇ悊鏍戠埗绾х瓫閫夌殑鏍蜂緥銆?

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontAgentDetailOwnerBoundaryClosureModuleTest.php` 鐨勪笁涓涓烘牱渚嬪湪娓呭崟琛ュ綍鍓嶅凡閫氳繃锛岃瘉鏄庣幇鏈夎鎯呯被鍏ュ彛閮介?氳繃 `canViewUser()` 涓? `FrontLegacyData::userScopeIds()` 闄愬埗褰撳墠浠ｇ悊鍙鑼冨洿銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 247 鑺傘??
- GREEN锛氳拷鍔犵 247 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontAgentDetailOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄浠ｇ悊鐧诲綍鎬併?佸悓鏍戜笅绾т唬鐞嗐?佸悓鏍戝鎴枫?佸叾瀹冧唬鐞嗘爲浠ｇ悊銆佸叾瀹冧唬鐞嗘爲瀹㈡埛銆佺幇浠ｇ敤鎴疯鎯呫?佹棫璇︽儏寮瑰眰銆佹棫灞傜骇璺緞鍜屾棫鐩村睘瀹㈡埛鏄庣粏鍒楄〃銆?
- 鐜颁唬璇︽儏瀵逛笉鍙鐢ㄦ埛杩斿洖 `ResponseCode::PERMISSION_DENIED`锛涙棫璇︽儏寮瑰眰杩斿洖 403锛涙棫 parentPath 杩斿洖绌? path/tree锛涙棫 direct_cust_detail_list 杩斿洖 `count=0,data=[]`銆?
- 鍝嶅簲鍐呭涓嶄細鍖呭惈鍏跺畠浠ｇ悊鏍戠敤鎴峰鍚嶃?佷笟鍔＄敤鎴? ID銆佽矾寰勮妭鐐规垨鐩村睘瀹㈡埛鏄庣粏銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `AgentController` 鐢熶骇閫昏緫銆佷唬鐞嗗垪琛ㄣ?佸鎴峰垪琛ㄣ?佺洿灞炲鎴疯浆璐︺?佺瓑绾х‘璁ゃ?佺粍鍒彉鏇淬?佺櫥褰曞巻鍙层?佸墠绔〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤浠ｇ悊绛夌骇纭銆佸鎴风粍鍒彉鏇淬?佺櫥褰曞巻鍙插綊灞炶竟鐣屽強鍏跺畠鍓嶅彴浠ｇ悊銆佹櫘閫氱敤鎴锋垨鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 248. 2026-07-09 鍓嶅彴鐢ㄦ埛鐧诲綍鍘嗗彶褰掑睘杈圭晫娴嬭瘯闂幆

### 鏈澶勭悊鐩爣
- 涓? `AgentController::userLoginHistory` 涓? `AgentController::legacyLoginHistorySearch` 琛ラ綈鐢ㄦ埛鐧诲綍鍘嗗彶褰掑睘杈圭晫娴嬭瘯銆?
- 楠岃瘉鐜颁唬 `/api/front/users/login-history` 鍙厑璁稿綋鍓嶄唬鐞嗚鍙栬嚜宸变唬鐞嗘爲鍐呯敤鎴风殑 `user_login_logs`锛岃闂叾瀹冧唬鐞嗘爲鐢ㄦ埛鏃惰繑鍥炴潈闄愭嫆缁濄??
- 楠岃瘉鏃? Web `user/cust/loginHistorySearch/{uid}` 瀵瑰叾瀹冧唬鐞嗘爲鐢ㄦ埛杩斿洖鏃у吋瀹圭┖琛ㄦ牸锛屼笉娉勯湶 IP銆佸湴鐞嗕綅缃?佽澶囨垨涓氬姟鐢ㄦ埛 ID銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontUserLoginHistoryOwnerBoundaryClosureModuleTest.php`
  - 鏂板鐜颁唬鐧诲綍鍘嗗彶鍙鐢ㄦ埛姝ｅ父杩斿洖銆佷笉鍙鐢ㄦ埛鏉冮檺鎷掔粷鏍蜂緥銆?
  - 鏂板鏃х櫥褰曞巻鍙茶〃鏍煎彲瑙佺敤鎴锋甯歌繑鍥炪?佷笉鍙鐢ㄦ埛绌? rows/total 鏍蜂緥銆?
  - 鏂板鏈?缁堟竻鍗曡褰曟柇瑷?锛岀粦瀹氱 248 鑺傞棴鐜??

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontUserLoginHistoryOwnerBoundaryClosureModuleTest.php` 鐨勪袱涓涓烘牱渚嬪湪娓呭崟琛ュ綍鍓嶅凡閫氳繃锛岃瘉鏄庣幇鏈? `canViewUser()` 浼氬湪璇诲彇 `user_login_logs` 鍓嶉檺鍒跺綋鍓嶄唬鐞嗗彲瑙佽寖鍥淬??
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 248 鑺傘??
- GREEN锛氳拷鍔犵 248 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontUserLoginHistoryOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄浠ｇ悊鐧诲綍鎬併?佸悓鏍戝鎴枫?佸叾瀹冧唬鐞嗘爲瀹㈡埛銆佺湡瀹? `user_login_logs`銆佺幇浠ｇ櫥褰曞巻鍙? API 鍜屾棫鐧诲綍鍘嗗彶琛ㄦ牸鍏ュ彛銆?
- 鐜颁唬鍏ュ彛瀵逛笉鍙鐢ㄦ埛杩斿洖 `ResponseCode::PERMISSION_DENIED`锛涙棫鍏ュ彛瀵逛笉鍙鐢ㄦ埛杩斿洖 `total=0,rows=[]`銆?
- 鍝嶅簲鍐呭涓嶄細鍖呭惈鍏跺畠浠ｇ悊鏍戠殑鐧诲綍 IP銆乁ser-Agent銆佷笟鍔＄敤鎴? ID 鎴栫櫥褰曞璁″瓧娈点??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `AgentController` 鐢熶骇閫昏緫銆佺敤鎴疯鎯呫?佷唬鐞嗗眰绾ц矾寰勩?佷唬鐞嗗垪琛ㄣ?佸鎴峰垪琛ㄣ?佺櫥褰曟棩蹇楁ā鍨嬨?佸墠绔〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤浠ｇ悊绛夌骇纭銆佸鎴风粍鍒彉鏇村啓鍏ュ強鍏跺畠鍓嶅彴浠ｇ悊銆佹櫘閫氱敤鎴锋垨鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 249. 2026-07-09 鍓嶅彴浠ｇ悊绛夌骇纭褰掑睘杈圭晫娴嬭瘯闂幆

### 鏈澶勭悊鐩爣
- 涓? `AgentController::confirmLevel`銆乣AgentController::proxyConfirmSearch` 涓? `AgentController::confirmLevelChange` 琛ラ綈浠ｇ悊绛夌骇纭褰掑睘杈圭晫娴嬭瘯銆?
- 楠岃瘉鐜颁唬 `/api/front/agents/level-confirmation` 鍜屾棫 Web `user/proxy/proxyConfirmSearch` 鍙繑鍥炲綋鍓嶄唬鐞嗙洿灞炲緟纭浠ｇ悊锛屼笉娉勯湶鍏跺畠浠ｇ悊鏍戝緟纭浠ｇ悊銆?
- 楠岃瘉鍒楄〃绛涢?変紶鍏ュ叾瀹冧唬鐞嗘爲 `userId` 鏃惰繑鍥炵┖鍒嗛〉锛屼笉閫氳繃绛涢?夊弬鏁板垏鎹㈠彲瑙佽寖鍥淬??
- 楠岃瘉鐜颁唬 `/api/front/agents/level-confirmation/changes` 鍜屾棫 Web `user/proxy/confirmLevelChange` 鎷掔粷纭鍏跺畠浠ｇ悊鏍戠洰鏍囷紝涓斾笉鏀瑰啓 `is_agent_confirmed`銆乣level_id`銆乣comm_rate`銆?
- 楠岃瘉鐩村睘涓嬬骇浠ｇ悊浠嶅彲姝ｅ父纭绛夌骇锛岃繑浣ｆ瘮渚嬩互 `agent_levels.user_commission + extra_val` 鍚庣璁＄畻缁撴灉涓哄噯銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontAgentLevelConfirmationOwnerBoundaryClosureModuleTest.php`
  - 鏂板鐜颁唬/鏃х瓑绾х‘璁ゅ垪琛ㄦ嫆缁濆叾瀹冧唬鐞嗘爲绛涢?夌殑鏍蜂緥銆?
  - 鏂板鐜颁唬/鏃х瓑绾х‘璁ゆ彁浜ゆ嫆缁濆叾瀹冧唬鐞嗘爲鐩爣涓斾笉鏀瑰瓧娈电殑鏍蜂緥銆?
  - 鏂板鐩村睘涓嬬骇浠ｇ悊绛夌骇纭鎴愬姛鍐欏叆鏍蜂緥銆?

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚vendor\bin\phpunit tests\Feature\FrontAgentLevelConfirmationOwnerBoundaryClosureModuleTest.php` 棣栨琛屼负鏍蜂緥涓毚闇叉祴璇? fixture 浣跨敤 `0.1` 浣滀负 `comm_rate`锛屼絾鐪熷疄 `user_infos.comm_rate` 鍜? `agent_levels.user_commission` 鍧囦负 `int(11)`锛岃惤搴撳悗涓? `0`銆備慨姝ｆ祴璇曟暟鎹负鏁村瀷姣斾緥鍚庯紝涓や釜琛屼负鏍蜂緥鍦ㄦ竻鍗曡ˉ褰曞墠閫氳繃銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 249 鑺傘??
- GREEN锛氳拷鍔犵 249 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontAgentLevelConfirmationOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄浠ｇ悊鐧诲綍鎬併?佺洿灞炲緟纭浠ｇ悊銆佸叾瀹冧唬鐞嗘爲寰呯‘璁や唬鐞嗐?佺幇浠ｇ瓑绾х‘璁ゅ垪琛ㄣ?佹棫绛夌骇纭鍒楄〃銆佺幇浠ｇ‘璁ゆ彁浜ゅ拰鏃х‘璁ゆ彁浜ゃ??
- 鍒楄〃鍏ュ彛閫氳繃鐩村睘浠ｇ悊鑼冨洿闄愬埗杩斿洖鏁版嵁锛涙彁浜ゅ叆鍙ｉ?氳繃鍚屼竴鐩村睘浠ｇ悊鑼冨洿鎷掔粷鍏跺畠浠ｇ悊鏍戠洰鏍囥??
- 鎷掔粷鍚庡叾瀹冧唬鐞嗘爲鐩爣鐨? `is_agent_confirmed`銆乣level_id`銆乣comm_rate` 鍧囦繚鎸佸師鍊硷紱鐩村睘鐩爣鍙甯哥‘璁ゅ苟鍐欏叆鍚庣绛夌骇姣斾緥銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `AgentController` 鐢熶骇閫昏緫銆佷唬鐞嗗垪琛ㄣ?佸鎴峰垪琛ㄣ?佺敤鎴疯鎯呫?佸鎴风粍鍒彉鏇淬?佸墠绔〉闈€?乣agent_levels` 鎴? `user_infos` 琛ㄧ粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤瀹㈡埛缁勫埆鍙樻洿鍐欏叆銆佷唬鐞?/瀹㈡埛鍒楄〃娣卞眰绛涢?夊強鍏跺畠鍓嶅彴浠ｇ悊銆佹櫘閫氱敤鎴锋垨鍚庡彴绠＄悊鍛樻ā鍧楀墿浣欏叆鍙ｃ??

## 250. 2026-07-09 鍓嶅彴瀹㈡埛缁勫埆鍙樻洿褰掑睘杈圭晫娴嬭瘯闂幆

### 鏈澶勭悊鐩爣
- 涓? `AgentController::groupChange`銆乣AgentController::changeDirectCustGroupEdit` 涓? `AgentController::groupChangeList` 琛ラ綈瀹㈡埛缁勫埆鍙樻洿褰掑睘杈圭晫娴嬭瘯銆?
- 楠岃瘉鐜颁唬 `/api/front/agents/group-change-applications` 鍙厑璁稿綋鍓嶄唬鐞嗕负鑷繁浠ｇ悊鏍戝唴鏅?氬鎴锋彁浜ょ粍鍒彉鏇寸敵璇凤紝鏀跺埌鍏跺畠浠ｇ悊鏍戝鎴锋椂杩斿洖鏉冮檺鎷掔粷涓斾笉鍐欏叆 `trans_apply_logs`銆?
- 楠岃瘉鏃? Web `user/cust/change/group_edit` 浣跨敤 `grpName`銆乣userId`銆乣trans_apply_reason` 鏃跺悓鏍蜂繚鎸佸綋鍓嶄唬鐞嗘爲杈圭晫锛屼笉鍏佽璺ㄤ唬鐞嗘爲鎻愪氦銆?
- 楠岃瘉鐜颁唬 `/api/front/agents/group-changes` 涓庢棫 Web `user/cust/directCustChangeListSearch` 鍙鍙栧綋鍓嶇櫥褰曚唬鐞? `applicant_id` 鐨勭敵璇疯褰曪紝浼犲叆鍏跺畠浠ｇ悊鏍戝鎴? `userId` 绛涢?夋椂杩斿洖绌哄垎椤点??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontAgentGroupChangeOwnerBoundaryClosureModuleTest.php`
  - 鏂板鐜颁唬缁勫埆鍙樻洿鎻愪氦鍙啓鍏ュ綋鍓嶄唬鐞嗙洿灞炲鎴风殑鏍蜂緥銆?
  - 鏂板鐜颁唬缁勫埆鍙樻洿鎻愪氦鎷掔粷鍏跺畠浠ｇ悊鏍戝鎴蜂笖鏃犲啓鍏ョ殑鏍蜂緥銆?
  - 鏂板鏃? `changeDirectCustGroupEdit` 鍙啓鍏ュ綋鍓嶄唬鐞嗙洿灞炲鎴枫?佹嫆缁濆叾瀹冧唬鐞嗘爲瀹㈡埛鐨勬牱渚嬨??
  - 鏂板鐜颁唬/鏃х粍鍒彉鏇村垪琛ㄦ寜褰撳墠 `applicant_id` 闅旂骞舵嫆缁濊法浠ｇ悊鏍? `userId` 绛涢?夌殑鏍蜂緥銆?

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\FrontAgentGroupChangeOwnerBoundaryClosureModuleTest.php` 棣栨杩愯涓? 5 涓涓烘牱渚嬪凡閫氳繃锛岃瘉鏄庣幇鏈夋彁浜ゅ叆鍙ｄ細鍏堟牎楠岀敵璇蜂汉涓轰唬鐞嗐?佺洰鏍囦负鏅?氬鎴凤紝鍐嶉?氳繃 `canViewUser()` 闄愬埗褰撳墠浠ｇ悊鏍戯紱鍒楄〃鍏ュ彛鍥哄畾 `trans_apply_logs.applicant_id` 涓哄綋鍓嶇櫥褰曚唬鐞嗐??
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 250 鑺傘??
- GREEN锛氳拷鍔犵 250 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontAgentGroupChangeOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄浠ｇ悊鐧诲綍鎬併?佸綋鍓嶄唬鐞嗙洿灞炲鎴枫?佸叾瀹冧唬鐞嗘爲瀹㈡埛銆佺幇浠ｆ彁浜ゆ帴鍙ｃ?佹棫鎻愪氦鍏ュ彛銆佺幇浠ｅ垪琛ㄥ拰鏃у垪琛ㄥ叆鍙ｃ??
- 璺ㄤ唬鐞嗘爲鎻愪氦鍦ㄧ幇浠ｆ帴鍙ｈ繑鍥? `ResponseCode::PERMISSION_DENIED`锛屾棫鍏ュ彛杩斿洖 `msg=FAIL`锛屽潎涓嶄細涓哄綋鍓嶄唬鐞嗗拰鍏跺畠浠ｇ悊鏍戝鎴峰啓鍏? `trans_apply_logs`銆?
- 鍒楄〃璺緞鍗充娇鏀跺埌鍏跺畠浠ｇ悊鏍戝鎴? `userId`锛屼篃鍙湪褰撳墠浠ｇ悊 `applicant_id` 鑼冨洿鍐呭彔鍔犵瓫閫夛紝杩斿洖绌哄垎椤碉紝閬垮厤娉勯湶鍏跺畠浠ｇ悊鐨勭敵璇疯褰曘?佸鎴? ID銆佺粍鍒垨鐢宠鍘熷洜銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `AgentController` 鐢熶骇閫昏緫銆佸鎴风粍鍒鏍稿悗鍙般?佸墠绔〉闈€?佺粍鍒厤缃〃鎴栨暟鎹簱缁撴瀯銆?
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤浠ｇ悊/瀹㈡埛鍒楄〃娣卞眰绛涢?夈?佹櫘閫氱敤鎴锋ā鍧楀拰鍚庡彴绠＄悊鍛樻ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 251. 2026-07-09 鍓嶅彴浠ｇ悊/瀹㈡埛涓诲垪琛ㄥ綊灞炵瓫閫夎竟鐣屾祴璇曢棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `AgentController::subList`銆乣AgentController::proxyListSearch`銆乣AgentController::customerList` 涓? `AgentController::directCustListSearch` 琛ラ綈涓讳唬鐞?/瀹㈡埛鍒楄〃褰掑睘绛涢?夎竟鐣屾祴璇曘??
- 楠岃瘉鐜颁唬 `/api/front/agents/direct` 鍙厑璁稿綋鍓嶄唬鐞嗗睍寮?鑷繁浠ｇ悊鏍戝唴鐨? `parent_id`锛屼紶鍏ュ叾瀹冧唬鐞嗘爲鐖剁骇鏃惰繑鍥炵┖鍒楄〃銆?
- 楠岃瘉鐜颁唬 `/api/front/agents/direct-customers` 鍙厑璁稿綋鍓嶄唬鐞嗗睍寮?鑷繁浠ｇ悊鏍戝唴鐨勫鎴风埗绾э紝浼犲叆鍏跺畠浠ｇ悊鏍戠埗绾ф椂杩斿洖绌哄垪琛ㄣ??
- 楠岃瘉鐜颁唬鍒楄〃鍜屾棫 Web `user/proxy/proxyListSearch`銆乣user/cust/directCustListSearch` 鏀跺埌鍏跺畠浠ｇ悊鏍? `userId` 绛涢?夋椂锛屽彧鍦ㄥ綋鍓嶄唬鐞嗗彲瑙佽寖鍥村唴鍙犲姞绛涢?夛紝涓嶆硠闇插叾瀹冨垎鏀唬鐞嗘垨瀹㈡埛銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontAgentMainListOwnerBoundaryClosureModuleTest.php`
  - 鏂板浠ｇ悊鍒楄〃 `parent_id` 閽诲彇褰撳墠鏍戞垚鍔熴?佸叾瀹冩爲绌虹粨鏋滅殑鏍蜂緥銆?
  - 鏂板浠ｇ悊鍒楄〃鐜颁唬/鏃? `userId` 璺ㄦ爲绛涢?夌┖缁撴灉鏍蜂緥銆?
  - 鏂板瀹㈡埛鍒楄〃 `parent_id` 閽诲彇褰撳墠鏍戞垚鍔熴?佸叾瀹冩爲绌虹粨鏋滅殑鏍蜂緥銆?
  - 鏂板瀹㈡埛鍒楄〃鐜颁唬/鏃? `userId` 璺ㄦ爲绛涢?夌┖缁撴灉鏍蜂緥銆?

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\FrontAgentMainListOwnerBoundaryClosureModuleTest.php` 棣栨杩愯涓袱涓涓烘牱渚嬪凡閫氳繃锛岃瘉鏄庣幇鏈変富鍒楄〃浼氬厛鐢? `canViewUser()` 闄愬埗鍙睍寮?鐖剁骇锛屽啀閫氳繃 `FrontLegacyData::userScopeIds()` 璁＄畻褰撳墠浠ｇ悊鏍戣寖鍥村悗鍙犲姞 `userId` 绛涢?夈??
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 251 鑺傘??
- GREEN锛氳拷鍔犵 251 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontAgentMainListOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄浠ｇ悊鐧诲綍鎬併?佸綋鍓嶄唬鐞嗘爲浠ｇ悊銆佸綋鍓嶄唬鐞嗘爲瀹㈡埛銆佸叾瀹冧唬鐞嗘爲浠ｇ悊銆佸叾瀹冧唬鐞嗘爲瀹㈡埛銆佺幇浠ｄ唬鐞嗗垪琛ㄣ?佺幇浠ｅ鎴峰垪琛ㄥ拰鏃у垪琛ㄦ悳绱㈠叆鍙ｃ??
- 褰撳墠鏍戝唴 `parent_id` 閽诲彇鑳芥甯歌繑鍥炵洿灞炰唬鐞嗘垨鐩村睘瀹㈡埛锛涘叾瀹冧唬鐞嗘爲 `parent_id` 杩斿洖 `count=0,data=[]`銆?
- 璺ㄦ爲 `userId` 绛涢?夊湪鐜颁唬鍜屾棫鍏ュ彛鍧囪繑鍥炵┖鍒嗛〉锛岄伩鍏嶆妸鍏跺畠浠ｇ悊鏍戠殑鐢ㄦ埛 ID銆佸鍚嶃?佸眰绾у瓧娈点?佽祫閲戞眹鎬绘垨瀹㈡埛瀛楁甯﹀叆鍝嶅簲銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `AgentController` 鐢熶骇閫昏緫銆佷唬鐞?/瀹㈡埛鍒楄〃鍓嶇銆佺粺璁℃眹鎬汇?佸鎴风粍鍒?欓?夋垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鏅?氱敤鎴锋ā鍧楀拰鍚庡彴绠＄悊鍛樻ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 252. 2026-07-09 鍓嶅彴瀵嗙爜淇敼褰掑睘杈圭晫娴嬭瘯闂幆

### 鏈澶勭悊鐩爣
- 涓? `ProfileController::changePassword` 涓? `ProfileController::user_editpsw_save` 琛ラ綈瀵嗙爜淇敼褰掑睘杈圭晫娴嬭瘯銆?
- 楠岃瘉鐜颁唬 `/api/front/profile/password` 鍗充娇鎼哄甫鍏跺畠鐢ㄦ埛 `user_id` 鎴? `userId`锛屼篃鍙兘鏍￠獙骞舵洿鏂板綋鍓嶇櫥褰曠敤鎴风殑 `user_logins.password`銆?
- 楠岃瘉鏃? Web `user/editpsw_save` 浣跨敤 `olduserpsw`銆乣newuserpsw`銆乣confirmuserpsw` 鏃跺悓鏍峰彧淇敼褰撳墠鐧诲綍鐢ㄦ埛瀵嗙爜锛屼笉鑳介?氳繃璇锋眰鍙傛暟鏀瑰啓鍏跺畠鐢ㄦ埛銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontProfilePasswordOwnerBoundaryClosureModuleTest.php`
  - 鏂板鐜颁唬瀵嗙爜淇敼蹇界暐浼?犵敤鎴? ID 鐨勬牱渚嬨??
  - 鏂板鏃у瘑鐮佷慨鏀瑰拷鐣ヤ吉閫犵敤鎴? ID 鐨勬牱渚嬨??
  - 涓や釜鏍蜂緥鍧囨瀯閫犵湡瀹炲綋鍓嶅鎴峰拰鐪熷疄鍏跺畠瀹㈡埛锛屽苟鐢? `Hash::check` 鏂█鍙綋鍓嶇櫥褰曠敤鎴峰瘑鐮佸搱甯屽彉鍖栵紝鍏跺畠鐢ㄦ埛瀵嗙爜淇濇寔鍘熷?笺??

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\FrontProfilePasswordOwnerBoundaryClosureModuleTest.php` 棣栨杩愯涓袱涓涓烘牱渚嬪凡閫氳繃锛岃瘉鏄庣幇鏈夌幇浠ｅ拰鏃у瘑鐮佷慨鏀瑰叆鍙ｉ兘浠庡綋鍓嶈璇佺敤鎴疯鍙? `user_logins`锛屼笉浼氫俊浠昏姹備腑鐨勭洰鏍囩敤鎴? ID銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 252 鑺傘??
- GREEN锛氳拷鍔犵 252 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `FrontProfilePasswordOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄鏅?氬鎴风櫥褰曟?併?佺湡瀹炲叾瀹冨鎴枫?佺幇浠? `/api/front/profile/password` 鍜屾棫 `user/editpsw_save` 涓や釜鍏ュ彛銆?
- 鎴愬姛鏀瑰瘑鍚庡綋鍓嶇櫥褰曠敤鎴锋棫瀵嗙爜澶辨晥銆佹柊瀵嗙爜鍙牎楠岋紱鍏跺畠鐢ㄦ埛鏃у瘑鐮佷粛鍙牎楠岋紝涓斾笉浼氳鍐欏叆褰撳墠鐢ㄦ埛鏂板瘑鐮佸搱甯屻??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `ProfileController` 鐢熶骇閫昏緫銆佹壘鍥炲瘑鐮併?侀偖绠?/鎵嬫満鍙蜂慨鏀广?佽祫鏂欓〉鍓嶇琛ㄥ崟鎴栨暟鎹簱缁撴瀯銆?
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴绠＄悊鍛樻ā鍧楀拰鍏跺畠鍓╀綑鍏ュ彛銆?

## 253. 2026-07-09 鍚庡彴绠＄悊鍛樿处鍙风紪杈戣矾鐢辩洰鏍囪竟鐣屾祴璇曢棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `AdminController::update` 琛ラ綈鍚庡彴绠＄悊鍛樿处鍙风紪杈戣矾鐢辩洰鏍囪竟鐣屾祴璇曘??
- 楠岃瘉 `/api/admin/updateAdmin/{id}` 鍙兘鏇存柊璺敱 `{id}` 鎸囧悜鐨? `admins.id` 璁板綍锛屽嵆浣胯〃鍗曢殣钘忓瓧娈? `id` 鎸囧悜鍏跺畠绠＄悊鍛橈紝涔熶笉鑳芥敼鍐欏叾瀹冭处鍙枫??
- 楠岃瘉鐢ㄦ埛鍚嶃?侀偖绠便?佹墜鏈哄彿銆佺姸鎬佸拰瀵嗙爜鍝堝笇鍧囧彧钀藉湪璺敱鐩爣绠＄悊鍛樹笂锛屽叾瀹冪鐞嗗憳淇濇寔鍘熷?笺??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminAccountRouteTargetBoundaryClosureModuleTest.php`
  - 鏂板鍚庡彴绠＄悊鍛樼紪杈戝拷鐣ヤ吉閫犺〃鍗? `id` 鐨勬牱渚嬨??
  - 鏍蜂緥鏋勯?犵湡瀹炴搷浣滅鐞嗗憳銆佺湡瀹炵洰鏍囩鐞嗗憳鍜岀湡瀹炲叾瀹冪鐞嗗憳锛屽苟鏂█鏇存柊鍚庡彧鏈夎矾鐢辩洰鏍囩鐞嗗憳鍙樺寲銆?

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAccountRouteTargetBoundaryClosureModuleTest.php` 棣栨杩愯涓涓烘牱渚嬪凡閫氳繃锛岃瘉鏄庣幇鏈? `AdminController::update` 浠ヨ矾鐢卞弬鏁? `$id` 鎵ц `Admin::find($id)`锛屼笉浼氫俊浠昏姹備綋涓殑闅愯棌 `id`銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 253 鑺傘??
- GREEN锛氳拷鍔犵 253 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminAccountRouteTargetBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬併?乣/api/admin/updateAdmin/{id}` 缂栬緫鍏ュ彛鍜岃〃鍗? `id` 鍐掑厖鍦烘櫙銆?
- 璺敱鐩爣绠＄悊鍛樿姝ｇ‘鏇存柊锛涘叾瀹冪鐞嗗憳鐨勭敤鎴峰悕銆侀偖绠便?佹墜鏈哄彿銆佺姸鎬佸拰瀵嗙爜鍝堝笇淇濇寔鍘熷?硷紝閬垮厤鍚庡彴璐﹀彿缂栬緫璇敼闈炵洰鏍囪处鍙枫??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `AdminController` 鐢熶骇閫昏緫銆佺鐞嗗憳璐﹀彿鍓嶇銆佹潈闄愬瓧鍏搞?佽鑹插悓姝ユ垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画鍙户缁璁? `AdminController::resetPassword` 鏄惁闇?瑕佺嫭绔嬭矾鐢便?佹寜閽潈闄愬拰琛屼负闂幆銆?

## 254. 2026-07-09 鍚庡彴绠＄悊鍛樿处鍙烽噸缃瘑鐮佽矾鐢辨潈闄愪笌琛屼负闂幆

### 鏈澶勭悊鐩爣
- 涓? `AdminController::resetPassword` 琛ラ綈鍙揪鍚庡彴 API 璺敱銆佹寜閽?/API 鏉冮檺銆佸墠绔绾у姩浣滃拰琛屼负娴嬭瘯銆?
- 楠岃瘉 `/api/admin/resetAdminPassword/{id}` 浣跨敤璺敱 `{id}` 鎸囧悜鐨? `admins.id` 浣滀负鍞竴鐩爣锛屽嵆浣胯姹備綋鎼哄甫鍏跺畠绠＄悊鍛? `id`锛屼篃鍙兘閲嶇疆璺敱鐩爣绠＄悊鍛樺瘑鐮併??
- 楠岃瘉 `admin_admin_reset_password` 鍐欏叆 `permissions.api_route=admin_api_resetAdminPassword`锛屽墠绔寜閽?氳繃 `data-permission` 鎺у埗鏄鹃殣锛屽悗绔户缁敱 `check.permission:admin` 鍋氫簩娆￠壌鏉冦??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminAccountResetPasswordClosureModuleTest.php`
  - 鏂板璺敱銆佹潈闄愯縼绉汇?丩ayui/CrmUi/Naive 鍓嶇鍔ㄤ綔鍜屽璇█鏂囨鎺ョ嚎娴嬭瘯銆?
  - 鏂板鍚庡彴绠＄悊鍛橀噸缃瘑鐮佸拷鐣ヤ吉閫犺〃鍗? `id` 鐨勮涓烘牱渚嬨??
- `routes/admin.php`
  - 鏂板 `POST /api/admin/resetAdminPassword/{id}`锛屽懡鍚嶈矾鐢变负 `admin_api_resetAdminPassword`銆?
- `database/migrations/2026_06_07_000001_add_admin_content_crud_permissions.php`
  - 鏂板 `admin_admin_reset_password` 鎸夐挳/API 鏉冮檺銆?
- `resources/admin/layui/admins/index.blade.php`銆乣public/js/apps/admin/layui/pages.js`
  - 鏂板绠＄悊鍛樺垪琛ㄢ?滈噸缃瘑鐮佲?濊绾ф寜閽?佹潈闄愭爣璁板拰瀵嗙爜鎻愪氦閫昏緫銆?
- `app/Http/Controllers/CrmUi/Admin/PageController.php`銆乣public/js/apps/naive-admin/front-plain.js`
  - 鏂板 CrmUi/Naive 绠＄悊绔噸缃瘑鐮佽绾у姩浣溿??
- `resources/lang/zh-CN/admin.php`銆乣resources/lang/en/admin.php`銆乣public/js/shared/lang/common/zh-CN.js`銆乣public/js/shared/lang/common/en.js`
  - 鏂板 `reset_password` 鏂囨銆?
- `tests/Feature/AdminContentCrudPermissionMigrationTest.php`
  - 灏? `admin_api_resetAdminPassword` 璺敱鍜? `admin_admin_reset_password` 鏉冮檺绾冲叆绠＄悊鍛? CRUD 鏉冮檺鍥炲綊銆?

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAccountResetPasswordClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓? `admin_api_resetAdminPassword` 鍛藉悕璺敱涓嶅瓨鍦ㄣ?乣/api/admin/resetAdminPassword/{id}` 杩斿洖 404銆佹渶缁堟竻鍗曠己灏戠 254 鑺傘??
- GREEN锛氳ˉ榻愯矾鐢便?佹潈闄愯縼绉汇?佸墠绔姩浣溿?佽瑷?鍖呭拰娓呭崟鍚庯紝鐩爣娴嬭瘯閫氳繃銆?

### 褰撳墠璇佹嵁
- `AdminAccountResetPasswordClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬併?乣/api/admin/resetAdminPassword/{id}` 閲嶇疆瀵嗙爜鍏ュ彛鍜岃〃鍗? `id` 鍐掑厖鍦烘櫙銆?
- 璺敱鐩爣绠＄悊鍛樺瘑鐮佽姝ｇ‘閲嶇疆锛涘叾瀹冪鐞嗗憳鏃у瘑鐮佷粛鍙牎楠岋紝涓斾笉浼氳鍐欏叆璺敱鐩爣鐨勬柊瀵嗙爜鍝堝笇銆?
- Layui銆丆rmUi 鍜? Naive 绠＄悊绔潎瀛樺湪閲嶇疆瀵嗙爜鍔ㄤ綔鍏ュ彛锛屾潈闄愭爣璇嗙粺涓?涓? `admin_admin_reset_password`锛屾帴鍙ｆ潈闄愮粺涓?缁戝畾 `admin_api_resetAdminPassword`銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩绠＄悊鍛樻柊澧炪?佺紪杈戙?佸垹闄ゃ?佽鑹插悓姝ャ?佸悗鍙扮櫥褰曡璇併?佸綋鍓嶇鐞嗗憳鑷姪鏀瑰瘑鎴栨暟鎹簱缁撴瀯銆?
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴绠＄悊鍛樻ā鍧椼?佹櫘閫氱敤鎴锋ā鍧楀拰浠ｇ悊鍟嗘ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 255. 2026-07-09 鍚庡彴绠＄悊鍛樿处鍙峰垹闄よ矾鐢辩洰鏍囪竟鐣屾祴璇曢棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `AdminController::destroy` 琛ラ綈鍚庡彴绠＄悊鍛樿处鍙峰垹闄よ矾鐢辩洰鏍囪竟鐣屾祴璇曘??
- 楠岃瘉 `/api/admin/deleteAdmin/{id}` 鍙兘杞垹闄よ矾鐢? `{id}` 鎸囧悜鐨? `admins.id` 璁板綍锛屽嵆浣胯姹備綋 `id` 鎸囧悜鍏跺畠绠＄悊鍛橈紝涔熶笉鑳藉垹闄ゅ叾瀹冭处鍙枫??
- 楠岃瘉 `admin_admin_delete` 鎸夐挳/API 鏉冮檺瀵瑰簲鍒犻櫎鍏ュ彛锛屽墠绔彲缁х画鍙妸褰撳墠琛ㄦ牸琛? `admins.id` 浣滀负璺敱鐩爣鎻愪氦銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminAccountDeleteRouteTargetBoundaryClosureModuleTest.php`
  - 鏂板鍚庡彴绠＄悊鍛樺垹闄ゅ拷鐣ヤ吉閫犺〃鍗? `id` 鐨勬牱渚嬨??
  - 鏍蜂緥鏋勯?犵湡瀹炴搷浣滅鐞嗗憳銆佺湡瀹炵洰鏍囩鐞嗗憳鍜岀湡瀹炲叾瀹冪鐞嗗憳锛屽苟鏂█鍙湁璺敱鐩爣绠＄悊鍛? `deleted_at` 琚啓鍏ャ??

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAccountDeleteRouteTargetBoundaryClosureModuleTest.php` 棣栨杩愯涓涓烘牱渚嬪凡閫氳繃锛岃瘉鏄庣幇鏈? `AdminController::destroy` 浠ヨ矾鐢卞弬鏁? `$id` 鎵ц `Admin::find($id)`锛屼笉浼氫俊浠昏姹備綋涓殑闅愯棌 `id`銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 255 鑺傘??
- GREEN锛氳拷鍔犵 255 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminAccountDeleteRouteTargetBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬併?乣/api/admin/deleteAdmin/{id}` 鍒犻櫎鍏ュ彛鍜岃〃鍗? `id` 鍐掑厖鍦烘櫙銆?
- 璺敱鐩爣绠＄悊鍛樿杞垹闄わ紱鍏跺畠绠＄悊鍛樼殑 `deleted_at` 浠嶄负 `null`锛岀敤鎴峰悕鍜岄偖绠变繚鎸佸師鍊硷紝閬垮厤鍚庡彴璐﹀彿鍒犻櫎璇垹闈炵洰鏍囪处鍙枫??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `AdminController` 鐢熶骇閫昏緫銆佺鐞嗗憳璐﹀彿鍓嶇銆佹潈闄愬瓧鍏搞?佽鑹插悓姝ャ?侀噸缃瘑鐮佹垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴绠＄悊鍛樻ā鍧椼?佹櫘閫氱敤鎴锋ā鍧楀拰浠ｇ悊鍟嗘ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 256. 2026-07-09 鍚庡彴绠＄悊鍛樺垪琛ㄥ瘑鐮佸瓧娈甸殣钘忔祴璇曢棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `AdminController::index` 琛ラ綈鍚庡彴绠＄悊鍛樺垪琛ㄦ晱鎰熷瓧娈甸殣钘忔祴璇曘??
- 楠岃瘉鏃у垪琛ㄥ叆鍙? `/api/admin/adminList` 鍜屾柊鍒楄〃鍏ュ彛 `/api/admin/admins` 閮戒笉浼氳繑鍥? `admins.password` 瀛楁鎴栧瘑鐮佸搱甯屽唴瀹广??
- 楠岃瘉娴嬭瘯鏍蜂緥蹇呴』鍦ㄥ搷搴斿垪琛ㄤ腑鍛戒腑鐪熷疄绠＄悊鍛樿褰曪紝閬垮厤浠呴潬绌哄垪琛ㄨ鍒ゅ畨鍏ㄣ??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminAccountListPasswordHiddenClosureModuleTest.php`
  - 鏂板绠＄悊鍛樺垪琛ㄤ笉鏆撮湶瀵嗙爜鍝堝笇鐨勬牱渚嬨??
  - 鏍蜂緥鏋勯?犵湡瀹炵鐞嗗憳璐﹀彿锛屽苟鍒嗗埆璇锋眰 `/api/admin/adminList` 涓? `/api/admin/admins`锛屾柇瑷?鐩爣绠＄悊鍛樺瓨鍦ㄤ絾鍝嶅簲琛屾病鏈? `password` 瀛楁锛屽畬鏁村搷搴斿唴瀹逛篃涓嶅寘鍚瘑鐮佸搱甯屻??

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAccountListPasswordHiddenClosureModuleTest.php` 棣栨杩愯涓涓烘牱渚嬪凡閫氳繃锛岃瘉鏄庣幇鏈? `Admin` 妯″瀷鐨? `$hidden = ['password']` 浼氬湪 `AdminController::index` 鍒嗛〉鍝嶅簲涓殣钘忓瘑鐮佸瓧娈点??
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 256 鑺傘??
- GREEN锛氳拷鍔犵 256 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminAccountListPasswordHiddenClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬併?佹棫 `/api/admin/adminList` 鍜屾柊 `/api/admin/admins` 涓や釜鍒楄〃鍏ュ彛銆?
- 鍝嶅簲鍒楄〃鑳芥壘鍒版祴璇曠鐞嗗憳璐﹀彿锛屼絾璇ヨ涓嶅寘鍚? `password` 瀛楁锛涘畬鏁? JSON 鍝嶅簲涓嶅寘鍚搴旂殑 `admins.password` 鍝堝笇銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `AdminController` 鐢熶骇閫昏緫銆乣Admin` 妯″瀷銆佺鐞嗗憳璐﹀彿鍓嶇銆佹潈闄愬瓧鍏搞?侀噸缃瘑鐮併?佸垹闄ゆ垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴绠＄悊鍛樻ā鍧椼?佹櫘閫氱敤鎴锋ā鍧楀拰浠ｇ悊鍟嗘ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 257. 2026-07-09 鍚庡彴绠＄悊鍛樿处鍙锋柊澧炰富閿吉閫犺竟鐣屾祴璇曢棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `AdminController::store` 琛ラ綈鍚庡彴绠＄悊鍛樿处鍙锋柊澧炰富閿吉閫犺竟鐣屾祴璇曘??
- 楠岃瘉 `/api/admin/createAdmin` 鏀跺埌璇锋眰浣? `id` 鏃朵笉浼氭妸璇ュ?煎啓鍏? `admins.id`锛屼篃涓嶄細瑕嗙洊宸叉湁绠＄悊鍛樿处鍙枫??
- 楠岃瘉鏂板璐﹀彿浠嶇敱鏁版嵁搴撹嚜澧炰富閿敓鎴愶紝瀵嗙爜鎸? `Hash::make` 鍐欏叆鏂拌处鍙凤紝宸叉湁瓒呯骇绠＄悊鍛樿处鍙蜂繚鎸佸師鐢ㄦ埛鍚嶃?侀偖绠卞拰瀵嗙爜銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminAccountCreatePrimaryKeyBoundaryClosureModuleTest.php`
  - 鏂板鍚庡彴绠＄悊鍛樻柊澧炲拷鐣ヤ吉閫犱富閿? `id` 鐨勬牱渚嬨??
  - 鏍蜂緥鏋勯?犵湡瀹炶秴绾х鐞嗗憳锛屽啀浠ヨ姹備綋 `id=1` 鏂板鍙︿竴涓鐞嗗憳锛屾柇瑷?鏂拌处鍙蜂富閿笉鏄? `1`锛屼笖鍘? `admins.id=1` 鏈瑕嗙洊銆?

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAccountCreatePrimaryKeyBoundaryClosureModuleTest.php` 棣栨杩愯涓涓烘牱渚嬪凡閫氳繃锛岃瘉鏄庣幇鏈? `AdminController::store` 鍙粠璇锋眰涓鍙? `username`銆乣email`銆乣password` 鍜屾樉寮忓彲閫夎处鍙峰瓧娈碉紝涓嶄俊浠昏姹備綋 `id`銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 257 鑺傘??
- GREEN锛氳拷鍔犵 257 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminAccountCreatePrimaryKeyBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬併?乣/api/admin/createAdmin` 鏂板鍏ュ彛鍜岃姹備綋 `id` 鍐掑厖鍦烘櫙銆?
- 鏂板绠＄悊鍛樹娇鐢ㄦ暟鎹簱鑷涓婚敭锛涘師瓒呯骇绠＄悊鍛樼敤鎴峰悕銆侀偖绠卞拰瀵嗙爜鍝堝笇淇濇寔涓嶅彉锛岄伩鍏嶆柊澧炶〃鍗曚富閿弬鏁拌鐩栧凡鏈夊悗鍙拌处鍙枫??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `AdminController` 鐢熶骇閫昏緫銆乣Admin` 妯″瀷銆佺鐞嗗憳璐﹀彿鍓嶇銆佹潈闄愬瓧鍏搞?佺紪杈戙?侀噸缃瘑鐮併?佸垹闄ゆ垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴绠＄悊鍛樻ā鍧椼?佹櫘閫氱敤鎴锋ā鍧楀拰浠ｇ悊鍟嗘ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 258. 2026-07-09 鍚庡彴绠＄悊鍛樺垪琛ㄨ蒋鍒犻櫎杩囨护娴嬭瘯闂幆

### 鏈澶勭悊鐩爣
- 涓? `AdminController::index` 琛ラ綈鍚庡彴绠＄悊鍛樺垪琛ㄨ蒋鍒犻櫎杩囨护娴嬭瘯銆?
- 楠岃瘉鏃у垪琛ㄥ叆鍙? `/api/admin/adminList` 鍜屾柊鍒楄〃鍏ュ彛 `/api/admin/admins` 閮戒笉浼氳繑鍥? `admins.deleted_at` 宸插啓鍏ョ殑杞垹闄よ处鍙枫??
- 楠岃瘉鍚屼竴鍝嶅簲涓粛鑳借繑鍥炴湭鍒犻櫎绠＄悊鍛樿处鍙凤紝閬垮厤绌哄垪琛ㄩ?犳垚璇垽銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminAccountListSoftDeleteBoundaryClosureModuleTest.php`
  - 鏂板绠＄悊鍛樺垪琛ㄦ帓闄よ蒋鍒犻櫎璐﹀彿鐨勬牱渚嬨??
  - 鏍蜂緥鏋勯?犵湡瀹炴湭鍒犻櫎绠＄悊鍛樺拰鐪熷疄宸茶蒋鍒犻櫎绠＄悊鍛橈紝骞跺垎鍒姹? `/api/admin/adminList` 涓? `/api/admin/admins`锛屾柇瑷?鏈垹闄よ处鍙峰彲瑙併?佽蒋鍒犻櫎璐﹀彿涓嶅彲瑙併??

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAccountListSoftDeleteBoundaryClosureModuleTest.php` 棣栨杩愯涓涓烘牱渚嬪凡閫氳繃锛岃瘉鏄庣幇鏈? `Admin::query()` 浼氱户鎵? `SoftDeletes` 鍏ㄥ眬浣滅敤鍩熷苟杩囨护 `deleted_at` 闈炵┖璁板綍銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 258 鑺傘??
- GREEN锛氳拷鍔犵 258 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminAccountListSoftDeleteBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬併?佹棫 `/api/admin/adminList` 鍜屾柊 `/api/admin/admins` 涓や釜鍒楄〃鍏ュ彛銆?
- 鏈垹闄ょ鐞嗗憳鑳藉湪鍒楄〃涓懡涓紱杞垹闄ょ鐞嗗憳涓嶄細鍑虹幇鍦ㄥ垪琛ㄨ閲岋紝瀹屾暣 JSON 鍝嶅簲涔熶笉鍖呭惈鍏堕偖绠便??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `AdminController` 鐢熶骇閫昏緫銆乣Admin` 妯″瀷銆佺鐞嗗憳璐﹀彿鍓嶇銆佹潈闄愬瓧鍏搞?佹柊澧炪?佺紪杈戙?侀噸缃瘑鐮併?佸垹闄ゆ垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴绠＄悊鍛樻ā鍧椼?佹櫘閫氱敤鎴锋ā鍧楀拰浠ｇ悊鍟嗘ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 259. 2026-07-09 鍚庡彴绠＄悊鍛樺綋鍓嶇櫥褰曡祫鏂欐洿鏂板綊灞炰笌閭鍞竴杈圭晫闂幆

### 鏈澶勭悊鐩爣
- 涓? `AuthController::updateProfile` 琛ラ綈褰撳墠鐧诲綍绠＄悊鍛樿祫鏂欐洿鏂拌竟鐣屾祴璇曘??
- 楠岃瘉 `/api/admin/updateProfile` 鍙洿鏂板綋鍓? admin guard 鐧诲綍绠＄悊鍛橈紝鍗充娇璇锋眰浣撴惡甯﹀叾瀹冪鐞嗗憳 `id`锛屼篃涓嶈兘鏀瑰啓鍏跺畠鍚庡彴璐﹀彿銆?
- 楠岃瘉璇ユ帴鍙ｅ彧鍏佽鏇存柊 `email` 涓? `mobile`锛屼笉鑳介?氳繃璇锋眰浣撴敼鍐? `username`銆乣role_id`銆乣status` 鎴? `password` 绛夋晱鎰熷瓧娈点??
- 楠岃瘉 `admins.email` 涓嶈兘鏀规垚鍏跺畠绠＄悊鍛樺凡鍗犵敤閭锛岄伩鍏嶅悗鍙扮櫥褰曡处鍙烽偖绠卞嚭鐜版涔夈??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminProfileUpdateOwnerBoundaryClosureModuleTest.php`
  - 鏂板褰撳墠鐧诲綍璧勬枡鏇存柊蹇界暐浼?犵洰鏍囧拰鏁忔劅瀛楁鐨勬牱渚嬨??
  - 鏂板褰撳墠绠＄悊鍛橀偖绠变笉鑳芥敼涓哄叾瀹冪鐞嗗憳閭鐨勬牱渚嬨??
- `app/Http/Controllers/Admin/AuthController.php`
  - 鍦? `AuthController::updateProfile` 鐨? `email` 鏍￠獙涓姞鍏? `admins.email` 鍞竴鎬ц鍒欙紝骞舵帓闄ゅ綋鍓嶇櫥褰曠鐞嗗憳鑷韩銆?

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminProfileUpdateOwnerBoundaryClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓噸澶嶉偖绠变粛杩斿洖鎴愬姛鐮併?佹渶缁堟竻鍗曠己灏戠 259 鑺傘??
- GREEN锛氳ˉ榻? `admins.email` 鍞竴鏍￠獙鍜岀 259 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminProfileUpdateOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬併?乣/api/admin/updateProfile` 褰撳墠璧勬枡鏇存柊鍏ュ彛銆佷吉閫犵洰鏍? `id`銆佹晱鎰熷瓧娈垫彁浜ゅ拰閲嶅閭鍦烘櫙銆?
- 褰撳墠鐧诲綍绠＄悊鍛樼殑 `email`銆乣mobile` 鍙洿鏂帮紱鍏跺畠绠＄悊鍛樿褰曚繚鎸佷笉鍙樸??
- `username`銆乣status`銆乣password` 绛夋晱鎰熷瓧娈典笉浼氶?氳繃褰撳墠璧勬枡鎺ュ彛鍐欏叆锛涢噸澶? `admins.email` 杩斿洖 `ResponseCode::VALIDATION_FAILED`锛屽弻鏂归偖绠卞拰鎵嬫満鍙蜂繚鎸佸師鍊笺??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鍚庡彴鐧诲綍銆佺櫥鍑恒?佸ご鍍忎笂浼犮?佸綋鍓嶇鐞嗗憳鏀瑰瘑銆佺鐞嗗憳璐﹀彿 CRUD 鍓嶇鎴栨暟鎹簱缁撴瀯銆?
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴绠＄悊鍛樻ā鍧椼?佹櫘閫氱敤鎴锋ā鍧楀拰浠ｇ悊鍟嗘ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 260. 2026-07-09 鍚庡彴绠＄悊鍛樺綋鍓嶇櫥褰曟敼瀵嗗綊灞炶竟鐣屾祴璇曢棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `AuthController::changePassword` 琛ラ綈褰撳墠鐧诲綍绠＄悊鍛樻敼瀵嗗綊灞炶竟鐣屾祴璇曘??
- 楠岃瘉 `/api/admin/changePassword` 鍗充娇鎼哄甫鍏跺畠绠＄悊鍛? `id` 鎴? `admin_id`锛屼篃鍙兘鏍￠獙骞舵洿鏂板綋鍓? admin guard 鐧诲綍绠＄悊鍛樼殑 `admins.password`銆?
- 楠岃瘉鏃у瘑鐮侀敊璇椂杩斿洖 `ResponseCode::OLD_PASSWORD_WRONG`锛屼笖褰撳墠绠＄悊鍛樺拰鍏跺畠绠＄悊鍛樺瘑鐮佸潎涓嶈鏀瑰啓銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminProfilePasswordOwnerBoundaryClosureModuleTest.php`
  - 鏂板褰撳墠绠＄悊鍛樻敼瀵嗗拷鐣ヤ吉閫犵洰鏍囩鐞嗗憳 ID 鐨勬牱渚嬨??
  - 鏂板鏃у瘑鐮侀敊璇椂涓嶅啓鍏ヤ换浣曠鐞嗗憳瀵嗙爜鐨勬牱渚嬨??

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminProfilePasswordOwnerBoundaryClosureModuleTest.php` 棣栨杩愯涓袱涓涓烘牱渚嬪凡閫氳繃锛岃瘉鏄庣幇鏈? `AuthController::changePassword` 浠庡綋鍓? admin guard 鐢ㄦ埛璇诲彇瀵嗙爜鍝堝笇锛屼笉淇′换璇锋眰浣撶洰鏍? ID銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 260 鑺傘??
- GREEN锛氳拷鍔犵 260 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminProfilePasswordOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬併?乣/api/admin/changePassword` 褰撳墠鏀瑰瘑鍏ュ彛銆佷吉閫? `id/admin_id` 鍜屾棫瀵嗙爜閿欒鍦烘櫙銆?
- 鎴愬姛鏀瑰瘑鍚庡彧鏈夊綋鍓嶇櫥褰曠鐞嗗憳瀵嗙爜鍙樻洿锛屽叾瀹冪鐞嗗憳鏃у瘑鐮佷粛鍙牎楠岋紝涓斾笉浼氳鍐欏叆褰撳墠绠＄悊鍛樻柊瀵嗙爜鍝堝笇銆?
- 鏃у瘑鐮侀敊璇椂杩斿洖 `ResponseCode::OLD_PASSWORD_WRONG`锛涘綋鍓嶇鐞嗗憳鍜屽叾瀹冪鐞嗗憳鐨? `admins.password` 閮戒繚鎸佸師鍊笺??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `AuthController` 鐢熶骇閫昏緫銆佸悗鍙扮櫥褰曘?佺櫥鍑恒?乀oken 鍒锋柊銆佸ご鍍忎笂浼犮?佺鐞嗗憳璐﹀彿 CRUD 鍓嶇鎴栨暟鎹簱缁撴瀯銆?
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴绠＄悊鍛樻ā鍧椼?佹櫘閫氱敤鎴锋ā鍧楀拰浠ｇ悊鍟嗘ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 261. 2026-07-09 鍚庡彴绠＄悊鍛樺綋鍓嶈祫鏂欒鍙栧綊灞炰笌瀵嗙爜闅愯棌娴嬭瘯闂幆

### 鏈澶勭悊鐩爣
- 涓? `AuthController::profileInfo` 琛ラ綈褰撳墠鐧诲綍绠＄悊鍛樿祫鏂欒鍙栬竟鐣屾祴璇曘??
- 楠岃瘉 `/api/admin/profileInfo` 鍗充娇鎼哄甫鍏跺畠绠＄悊鍛? `id` 鎴? `admin_id`锛屼篃鍙繑鍥炲綋鍓? admin guard 鐧诲綍绠＄悊鍛樿祫鏂欍??
- 楠岃瘉鍝嶅簲涓笉鍖呭惈 `admins.password` 瀛楁鎴栧綋鍓?/鍏跺畠绠＄悊鍛樺瘑鐮佸搱甯岋紝閬垮厤鍚庡彴涓汉璧勬枡鎺ュ彛娉勯湶鏁忔劅鍑瘉銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminProfileInfoOwnerBoundaryClosureModuleTest.php`
  - 鏂板褰撳墠璧勬枡璇诲彇蹇界暐浼?犵洰鏍囩鐞嗗憳 ID 鐨勬牱渚嬨??
  - 鏂板鍝嶅簲 JSON 涓嶅寘鍚? `password` 瀛楁鍜屽瘑鐮佸搱甯岀殑鏂█銆?

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminProfileInfoOwnerBoundaryClosureModuleTest.php` 棣栨杩愯涓涓烘牱渚嬪凡閫氳繃锛岃瘉鏄庣幇鏈? `AuthController::profileInfo` 浠庡綋鍓? admin guard 鐢ㄦ埛杩斿洖璧勬枡锛屽苟鐢? `Admin` 妯″瀷闅愯棌 `password`銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 261 鑺傘??
- GREEN锛氳拷鍔犵 261 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminProfileInfoOwnerBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬併?乣/api/admin/profileInfo` 褰撳墠璧勬枡璇诲彇鍏ュ彛鍜屼吉閫? `id/admin_id` 鍦烘櫙銆?
- 鍝嶅簲 `data.id`銆乣data.username`銆乣data.email` 鍧囧睘浜庡綋鍓嶇櫥褰曠鐞嗗憳锛涘叾瀹冪鐞嗗憳閭涓嶄細鍑虹幇鍦ㄥ搷搴斾腑銆?
- 鍝嶅簲 `data` 涓嶅寘鍚? `password` 瀛楁锛屽畬鏁? JSON 鍝嶅簲涓嶅寘鍚綋鍓嶇鐞嗗憳鎴栧叾瀹冪鐞嗗憳鐨? `admins.password` 鍝堝笇銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `AuthController` 鐢熶骇閫昏緫銆乣Admin` 妯″瀷銆佸悗鍙扮櫥褰曘?佺櫥鍑恒?乀oken 鍒锋柊銆佸ご鍍忎笂浼犮?佸綋鍓嶇鐞嗗憳鏀瑰瘑鎴栨暟鎹簱缁撴瀯銆?
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴绠＄悊鍛樻ā鍧椼?佹櫘閫氱敤鎴锋ā鍧楀拰浠ｇ悊鍟嗘ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 262. 2026-07-09 鍚庡彴绠＄悊鍛樿处鍙风紪杈戠┖瀵嗙爜淇濈暀鏃у瘑鐮佹祴璇曢棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `AdminController::update` 琛ラ綈鍚庡彴绠＄悊鍛樿处鍙风紪杈戞椂绌哄瘑鐮佹彁浜よ竟鐣屾祴璇曘??
- 楠岃瘉 `/api/admin/updateAdmin/{id}` 鏀跺埌 `password` 绌哄瓧绗︿覆鏃讹紝浠嶅彧鏇存柊鐢ㄦ埛鍚嶃?侀偖绠便?佹墜鏈哄彿绛夎祫鏂欏瓧娈碉紝涓嶈鐩栫洰鏍囩鐞嗗憳鍘熸湁 `admins.password`銆?
- 楠岃瘉缂栬緫鍝嶅簲涓嶈繑鍥? `password` 瀛楁锛岄伩鍏嶈处鍙风淮鎶ゆ帴鍙ｆ硠闇插瘑鐮佸搱甯屻??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminAccountUpdateBlankPasswordClosureModuleTest.php`
  - 鏂板缂栬緫绠＄悊鍛樻椂鏄惧紡鎻愪氦绌? `password` 鐨勬牱渚嬨??
  - 鏂█鐩爣绠＄悊鍛樻棫瀵嗙爜鍝堝笇淇濇寔鍘熷?硷紝涓旂┖瀛楃涓蹭笉浼氳鍐欏叆鎴栭噸鏂板搱甯屻??

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAccountUpdateBlankPasswordClosureModuleTest.php` 棣栨杩愯涓涓烘牱渚嬪凡閫氳繃锛岃瘉鏄庣幇鏈? `AdminController::update` 閫氳繃 `$request->filled('password')` 淇濈暀绌哄瘑鐮佸満鏅笅鐨勬棫瀵嗙爜銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 262 鑺傘??
- GREEN锛氳拷鍔犵 262 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminAccountUpdateBlankPasswordClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佸拰 `/api/admin/updateAdmin/{id}` 缂栬緫鍏ュ彛銆?
- 鏄惧紡鎻愪氦 `password=''` 鏃讹紝鐩爣绠＄悊鍛樼敤鎴峰悕銆侀偖绠卞拰鎵嬫満鍙峰彲鏇存柊锛沗admins.password` 鍝堝笇涓庤姹傚墠瀹屽叏涓?鑷达紝鏃у瘑鐮佷粛鍙牎楠屻??
- 鍝嶅簲 `data` 涓嶅寘鍚? `password` 瀛楁锛岄伩鍏嶇紪杈戠鐞嗗憳鎺ュ彛杩斿洖瀵嗙爜鍝堝笇銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `AdminController` 鐢熶骇閫昏緫銆佺鐞嗗憳璐﹀彿鍓嶇銆佹潈闄愬瓧鍏搞?佽鑹插悓姝ャ?侀噸缃瘑鐮併?佸垹闄ゆ垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴绠＄悊鍛樻ā鍧椼?佹櫘閫氱敤鎴锋ā鍧楀拰浠ｇ悊鍟嗘ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 266. 2026-07-09 鍚庡彴鏅?氱敤鎴疯祫鏂欐洿鏂板瓧娈电櫧鍚嶅崟闂幆

### 鏈澶勭悊鐩爣
- 涓? `AdminUserController::updateUser` 琛ラ綈鍚庡彴鏅?氱敤鎴疯祫鏂欐洿鏂板瓧娈电櫧鍚嶅崟娴嬭瘯銆?
- 楠岃瘉 `/api/admin/users/{user}` 鍙厑璁歌鎯呴〉鐪熷疄鎻愪氦鐨? `user_name`銆乣phone` 鍐欏叆 `user_infos`銆?
- 楠岃瘉璇锋眰浣撲腑鐨? `id/user_id` 浼?犵洰鏍囥?乣account_type`銆乣parent_id`銆乣auth_status`銆佽祫閲戝瓧娈靛拰鍑哄叆閲戝紑鍏充笉鑳介?氳繃璧勬枡淇濆瓨鎺ュ彛鏀瑰啓銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminUserUpdateFieldWhitelistClosureModuleTest.php`
  - 鏂板鍚庡彴鐢ㄦ埛璧勬枡鏇存柊鍙啓鍏ュ熀纭?璧勬枡瀛楁鐨勬牱渚嬨??
  - 鏂█璐﹀彿绫诲瀷銆佷笂绾т唬鐞嗐?佽璇佺姸鎬併?佽祫閲戝拰鍑哄叆閲戝紑鍏充繚鎸佸師鍊笺??
- `app/Http/Controllers/Admin/AdminUserController.php`
  - 灏? `updateUser` 鍐欏叆鏁版嵁浠庤姹傛帓闄や富閿敼涓烘槑纭櫧鍚嶅崟 `user_name`銆乣phone`銆?
  - 鏇存柊鏂规硶娉ㄩ噴锛岃鏄庢晱鎰熷瓧娈靛繀椤荤敱鍚勮嚜涓撶敤娴佺▼缁存姢銆?

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminUserUpdateFieldWhitelistClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓? `account_type` 鍙璧勬枡淇濆瓨鎺ュ彛鏀瑰啓銆佹渶缁堟竻鍗曠己灏戠 266 鑺傘??
- GREEN锛氭敹绐? `updateUser` 鍐欏叆鐧藉悕鍗曞苟杩藉姞绗? 266 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminUserUpdateFieldWhitelistClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆乣user_logins`銆乣user_infos` 琛ㄨ褰曪紝鍚庡彴 admin guard 鐧诲綍鎬佸拰 `/api/admin/users/{user}` 璧勬枡鏇存柊鍏ュ彛銆?
- `user_name` 涓? `phone` 鍙?氳繃璇︽儏椤典繚瀛樻洿鏂般??
- 璇锋眰浣撲吉閫犵殑 `user_id` 涓嶄細鍒囨崲鐩爣锛沗account_type`銆乣parent_id`銆乣auth_status`銆乣total_funds`銆乣is_deposit_allowed`銆乣is_withdrawal_allowed` 鍧囦繚鎸佸師鍊笺??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鐢ㄦ埛鍒楄〃銆佸鍑恒?佽鎯呰鍙栥?佺櫥褰曞惎鍋溿?佸疄鍚嶈璇佸鏍搞?佹暟鎹寖鍥存湇鍔°?佸墠绔〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴鏅?氱敤鎴锋ā鍧椼?佷唬鐞嗗晢妯″潡鍜屽悗鍙扮鐞嗗憳妯″潡鍏跺畠鍓╀綑鍏ュ彛銆?

## 265. 2026-07-09 鍚庡彴鏅?氱敤鎴风櫥褰曞惎鍋滅姸鎬佹牎楠岄棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `AdminUserController::changeUserStatus` 琛ラ綈鍚庡彴鏅?氱敤鎴风櫥褰曞惎鍋滅姸鎬佹牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/changeUserStatus` 鍙帴鍙? `is_enabled=0/1`锛屽垎鍒〃绀虹鐢ㄥ拰鍚敤 `user_logins.is_enabled`銆?
- 楠岃瘉浼犲叆闈炴硶 `is_enabled` 鏃惰繑鍥? `ResponseCode::VALIDATION_FAILED`锛屼笖涓嶆敼鍐欑敤鎴风櫥褰曡处鍙风姸鎬併??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminUserStatusValidationClosureModuleTest.php`
  - 鏂板鍚堟硶 `is_enabled=0/1` 鍙甯稿垏鎹㈢殑鏍蜂緥銆?
  - 鏂板闈炴硶 `is_enabled=2` 琚嫆缁濅笖涓嶅啓鍏? `user_logins.is_enabled` 鐨勬牱渚嬨??
- `app/Http/Controllers/Admin/AdminUserController.php`
  - 鍦? `changeUserStatus` 閫氳繃鏁版嵁鑼冨洿鏍￠獙鍚庡鍔? `required|in:0,1` 鍙傛暟鏍￠獙锛屽苟灏嗗啓鍏ュ?艰浆涓烘暣鏁般??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminUserStatusValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓潪娉? `is_enabled=2` 浠嶈繑鍥炴垚鍔熺爜銆佹渶缁堟竻鍗曠己灏戠 265 鑺傘??
- GREEN锛氳ˉ榻? `is_enabled` 鏍￠獙鍜岀 265 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminUserStatusValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆乣user_logins`銆乣user_infos` 琛ㄨ褰曪紝鍚庡彴 admin guard 鐧诲綍鎬佸拰 `/api/admin/changeUserStatus` 鍚仠鍏ュ彛銆?
- `is_enabled=0` 浼氭妸 `user_logins.is_enabled` 鏇存柊涓虹鐢紝`is_enabled=1` 浼氭仮澶嶅惎鐢ㄣ??
- `is_enabled=2` 杩斿洖鍙傛暟鏍￠獙澶辫触锛屽師 `user_logins.is_enabled` 淇濇寔 `1`锛岄伩鍏嶅啓鍏ラ潪甯冨皵鍚仠鐘舵?併??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鐢ㄦ埛鍒楄〃銆佸鍑恒?佽鎯呫?佽祫鏂欐洿鏂般?佸疄鍚嶈璇佸鏍搞?佹暟鎹寖鍥存湇鍔°?佸墠绔〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴鏅?氱敤鎴锋ā鍧椼?佷唬鐞嗗晢妯″潡鍜屽悗鍙扮鐞嗗憳妯″潡鍏跺畠鍓╀綑鍏ュ彛銆?

## 264. 2026-07-09 鍚庡彴绠＄悊鍛樿处鍙疯鑹蹭笌鐘舵?佸瓧娈垫牎楠屾祴璇曢棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `AdminController::store` 涓? `AdminController::update` 琛ラ綈鍚庡彴绠＄悊鍛? `role_id/status` 瀛楁鏍￠獙杈圭晫娴嬭瘯銆?
- 楠岃瘉 `/api/admin/createAdmin` 鏂板璐﹀彿鏃讹紝`role_id` 鎸囧悜涓嶅瓨鍦ㄧ殑瑙掕壊鎴? `status` 涓嶅湪 `0/1` 鑼冨洿鍐呮椂蹇呴』杩斿洖 `ResponseCode::VALIDATION_FAILED`锛屼笖涓嶅垱寤烘柊璐﹀彿銆?
- 楠岃瘉 `/api/admin/updateAdmin/{id}` 缂栬緫璐﹀彿鏃讹紝闈炴硶 `role_id/status` 涓嶄細鏀瑰啓鐩爣绠＄悊鍛樼殑璐﹀彿璧勬枡銆佽鑹层?佺姸鎬佹垨瀵嗙爜銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminAccountRoleStatusValidationClosureModuleTest.php`
  - 鏂板鏂板绠＄悊鍛樻嫆缁濅笉瀛樺湪 `role_id` 鍜岄潪娉? `status` 鐨勬牱渚嬨??
  - 鏂板缂栬緫绠＄悊鍛樻嫆缁濅笉瀛樺湪 `role_id` 鍜岄潪娉? `status` 鐨勬牱渚嬨??

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAccountRoleStatusValidationClosureModuleTest.php` 棣栨杩愯涓袱涓涓烘牱渚嬪凡閫氳繃锛岃瘉鏄庣幇鏈? `AdminController::store` 涓? `AdminController::update` 宸查?氳繃 `exists:roles,id` 鍜? `in:0,1` 鏍￠獙瑙掕壊涓庣姸鎬佸瓧娈点??
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 264 鑺傘??
- GREEN锛氳拷鍔犵 264 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminAccountRoleStatusValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬併?乣/api/admin/createAdmin` 鏂板鍏ュ彛鍜? `/api/admin/updateAdmin/{id}` 缂栬緫鍏ュ彛銆?
- 鏂板璐﹀彿浼犲叆涓嶅瓨鍦? `role_id` 鎴栭潪娉? `status` 鏃惰繑鍥炲弬鏁版牎楠屽け璐ワ紝涓嶄細鍐欏叆鏂扮鐞嗗憳銆?
- 缂栬緫璐﹀彿浼犲叆涓嶅瓨鍦? `role_id` 鎴栭潪娉? `status` 鏃惰繑鍥炲弬鏁版牎楠屽け璐ワ紝鐩爣绠＄悊鍛樼敤鎴峰悕銆侀偖绠便?乣role_id`銆乣status` 鍜屽瘑鐮佸潎淇濇寔鍘熷?笺??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `AdminController` 鐢熶骇閫昏緫銆佺鐞嗗憳璐﹀彿鍓嶇銆佹潈闄愬瓧鍏搞?佽鑹插悓姝ャ?侀噸缃瘑鐮併?佸垹闄ゆ垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴绠＄悊鍛樻ā鍧椼?佹櫘閫氱敤鎴锋ā鍧楀拰浠ｇ悊鍟嗘ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 263. 2026-07-09 鍚庡彴绠＄悊鍛樿处鍙风櫥褰曟爣璇嗗敮涓?鎬ф祴璇曢棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `AdminController::store` 涓? `AdminController::update` 琛ラ綈鍚庡彴绠＄悊鍛樼櫥褰曟爣璇嗗敮涓?鎬ф祴璇曘??
- 楠岃瘉 `/api/admin/createAdmin` 鏂板璐﹀彿鏃讹紝`admins.username` 鎴? `admins.email` 宸茶鍏跺畠绠＄悊鍛樺崰鐢ㄦ椂蹇呴』杩斿洖 `ResponseCode::VALIDATION_FAILED`锛屼笖涓嶅垱寤烘柊璐﹀彿銆?
- 楠岃瘉 `/api/admin/updateAdmin/{id}` 缂栬緫璐﹀彿鏃讹紝涓嶈兘鎶婄洰鏍囩鐞嗗憳鐨勭敤鎴峰悕鎴栭偖绠辨敼鎴愬叾瀹冪鐞嗗憳宸插崰鐢ㄥ?硷紝閬垮厤鍚庡彴鐧诲綍鏍囪瘑姝т箟銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminAccountUniqueIdentityClosureModuleTest.php`
  - 鏂板鏂板绠＄悊鍛樻嫆缁濋噸澶? `username/email` 鐨勬牱渚嬨??
  - 鏂板缂栬緫绠＄悊鍛樻嫆缁濅娇鐢ㄥ叾瀹冪鐞嗗憳 `username/email` 鐨勬牱渚嬨??

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAccountUniqueIdentityClosureModuleTest.php` 棣栨杩愯涓袱涓涓烘牱渚嬪凡閫氳繃锛岃瘉鏄庣幇鏈? `AdminController::store` 涓? `AdminController::update` 宸蹭娇鐢? `unique:admins` 鏍￠獙璐﹀彿鐧诲綍鏍囪瘑銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 263 鑺傘??
- GREEN锛氳拷鍔犵 263 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminAccountUniqueIdentityClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬併?乣/api/admin/createAdmin` 鏂板鍏ュ彛鍜? `/api/admin/updateAdmin/{id}` 缂栬緫鍏ュ彛銆?
- 鏂板璐﹀彿浼犲叆閲嶅 `admins.username` 鎴? `admins.email` 鏃惰繑鍥炲弬鏁版牎楠屽け璐ワ紝涓嶄細鍐欏叆浼?犵殑鏂拌处鍙枫??
- 缂栬緫璐﹀彿浼犲叆鍏跺畠绠＄悊鍛樺凡鍗犵敤鐨? `admins.username` 鎴? `admins.email` 鏃惰繑鍥炲弬鏁版牎楠屽け璐ワ紝鐩爣绠＄悊鍛樼敤鎴峰悕銆侀偖绠便?佹墜鏈哄彿鍜屽瘑鐮佸潎淇濇寔鍘熷?笺??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩 `AdminController` 鐢熶骇閫昏緫銆佺鐞嗗憳璐﹀彿鍓嶇銆佹潈闄愬瓧鍏搞?佽鑹插悓姝ャ?侀噸缃瘑鐮併?佸垹闄ゆ垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴绠＄悊鍛樻ā鍧椼?佹櫘閫氱敤鎴锋ā鍧楀拰浠ｇ悊鍟嗘ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??
## 267. 2026-07-09 鍚庡彴鏅?氱敤鎴峰惎鍋? REST 璺敱鐩爣杈圭晫闂幆

### 鏈澶勭悊鐩爣
- 涓? `AdminUserController::changeUserStatus` 琛ラ綈 REST 鍚仠璺敱鐩爣杈圭晫娴嬭瘯銆?
- 楠岃瘉 `/api/admin/users/{user}/status` 鍙娇鐢ㄨ矾鐢? `{user}` 浣滀负鐩爣鐢ㄦ埛锛屼笉淇′换璇锋眰浣撻噷浼?犵殑 `user_id`銆?
- 楠岃瘉鍚仠鍔ㄤ綔鍙啓鍏ョ洰鏍囩敤鎴风殑 `user_logins.is_enabled`锛屼笉浼氳鏀硅姹備綋涓叾瀹冪敤鎴风殑鐧诲綍鐘舵?併??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminUserStatusRouteTargetBoundaryClosureModuleTest.php`
  - 鏂板 REST 鍚仠璺敱蹇界暐璇锋眰浣撲吉閫? `user_id` 鐨勬牱渚嬨??
  - 鏋勯?犵洰鏍囩敤鎴峰拰鍏跺畠鐢ㄦ埛锛屾柇瑷?鍙洿鏂拌矾鐢辩洰鏍囩敤鎴风殑 `user_logins.is_enabled`銆?

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminUserStatusRouteTargetBoundaryClosureModuleTest.php` 棣栨杩愯涓涓烘牱渚嬪凡閫氳繃锛岃瘉鏄庣幇鏈? REST 璺敱浼氭妸 `{user}` 娉ㄥ叆涓虹洰鏍? `user_id`銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 267 鑺傘??
- GREEN锛氳拷鍔犵 267 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminUserStatusRouteTargetBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆乣user_logins`銆乣user_infos` 琛ㄨ褰曪紝鍚庡彴 admin guard 鐧诲綍鎬佸拰 `/api/admin/users/{user}/status` 鍚仠鍏ュ彛銆?
- 璇锋眰浣撳嵆浣挎惡甯﹀叾瀹冪敤鎴? `user_id`锛屼篃鍙細鏇存柊璺敱鐩爣鐢ㄦ埛鐨? `user_logins.is_enabled`銆?
- 鍏跺畠鐢ㄦ埛鐨? `user_logins.is_enabled` 淇濇寔鍘熷?硷紝閬垮厤 REST 鍏ュ彛琚姹備綋鐩爣瑕嗙洊銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鐢ㄦ埛鍒楄〃銆佸鍑恒?佽鎯呰鍙栥?佽祫鏂欐洿鏂般?佸疄鍚嶈璇佸鏍搞?佹暟鎹寖鍥存湇鍔°?佸墠绔〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴鏅?氱敤鎴锋ā鍧椼?佷唬鐞嗗晢妯″潡鍜屽悗鍙扮鐞嗗憳妯″潡鍏跺畠鍓╀綑鍏ュ彛銆?
## 268. 2026-07-09 鍚庡彴鏅?氱敤鎴疯鎯? REST 璺敱鐩爣杈圭晫闂幆

### 鏈澶勭悊鐩爣
- 涓? `AdminUserController::userDetail` 琛ラ綈 REST 璇︽儏璺敱鐩爣杈圭晫娴嬭瘯銆?
- 楠岃瘉 `/api/admin/users/{user}` 鍙娇鐢ㄨ矾鐢? `{user}` 璇诲彇鐩爣鐢ㄦ埛璇︽儏锛屼笉淇′换璇锋眰浣撻噷浼?犵殑 `user_id`銆?
- 楠岃瘉璇︽儏鍝嶅簲鏉ヨ嚜鐩爣鐢ㄦ埛鐨? `user_infos.user_id` 鍜屽叧鑱? `user_logins.user_id`锛屼笉浼氳繑鍥炶姹備綋涓叾瀹冪敤鎴疯祫鏂欍??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminUserDetailRouteTargetBoundaryClosureModuleTest.php`
  - 鏂板 REST 璇︽儏璺敱蹇界暐璇锋眰浣撲吉閫? `user_id` 鐨勬牱渚嬨??
  - 鏋勯?犵洰鏍囩敤鎴峰拰鍏跺畠鐢ㄦ埛锛屾柇瑷?鍝嶅簲鍙寘鍚矾鐢辩洰鏍囩敤鎴疯祫鏂欍??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminUserDetailRouteTargetBoundaryClosureModuleTest.php` 棣栨杩愯鍏堟毚闇叉祴璇曞 `user_id` JSON 绫诲瀷鐨勯敊璇亣璁撅紱淇涓哄瓧绗︿覆鏂█鍚庯紝琛屼负鏍蜂緥閫氳繃锛屾竻鍗曟祴璇曞け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 268 鑺傘??
- GREEN锛氳拷鍔犵 268 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminUserDetailRouteTargetBoundaryClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆乣user_logins`銆乣user_infos` 琛ㄨ褰曪紝鍚庡彴 admin guard 鐧诲綍鎬佸拰 `/api/admin/users/{user}` 璇︽儏鍏ュ彛銆?
- 璇锋眰浣撳嵆浣挎惡甯﹀叾瀹冪敤鎴? `user_id`锛屽搷搴? `data.user_id` 涓? `data.login.user_id` 浠嶅睘浜庤矾鐢辩洰鏍囩敤鎴枫??
- 瀹屾暣鍝嶅簲涓嶅寘鍚叾瀹冪敤鎴峰悕绉帮紝閬垮厤 REST 璇︽儏鍏ュ彛琚姹備綋鐩爣瑕嗙洊銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鐢ㄦ埛鍒楄〃銆佸鍑恒?佽祫鏂欐洿鏂般?佺櫥褰曞惎鍋溿?佸疄鍚嶈璇佸鏍搞?佹暟鎹寖鍥存湇鍔°?佸墠绔〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴鏅?氱敤鎴锋ā鍧椼?佷唬鐞嗗晢妯″潡鍜屽悗鍙扮鐞嗗憳妯″潡鍏跺畠鍓╀綑鍏ュ彛銆?
## 269. 2026-07-09 鍚庡彴鏅?氱敤鎴峰疄鍚嶈璇佸鏍搁?氳繃鐘舵?侀棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `AdminUserController::reviewAuth` 琛ラ綈瀹炲悕璁よ瘉瀹℃牳閫氳繃璺緞娴嬭瘯銆?
- 楠岃瘉 `/api/admin/reviewAuth` 鍦? `status=1` 鏃舵妸 `user_auths.id_card_status` 涓? `user_auths.bank_status` 缁熶竴鏇存柊涓洪?氳繃鐘舵?併??
- 楠岃瘉瀹℃牳閫氳繃浼氭竻绌烘棫鎷掔粷鍘熷洜锛屽苟鍚屾 `user_infos.auth_status=1`锛屽悓鏃跺啓鍏ュ悗鍙版搷浣滄棩蹇椼??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminReviewAuthApproveStateClosureModuleTest.php`
  - 鏂板瀹炲悕璁よ瘉瀹℃牳閫氳繃鏃剁姸鎬併?佸娉ㄥ拰鎿嶄綔鏃ュ織鐨勬牱渚嬨??
  - 鏋勯?犺韩浠借瘉寰呭銆侀摱琛屽崱鎹㈢粦寰呭涓斿甫鏃ф嫆缁濆師鍥犵殑鐢ㄦ埛锛屾柇瑷?閫氳繃鍚庢棫鍘熷洜琚竻绌恒??

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminReviewAuthApproveStateClosureModuleTest.php` 棣栨杩愯涓涓烘牱渚嬪凡閫氳繃锛岃瘉鏄庣幇鏈? `reviewAuth` 閫氳繃璺緞浼氬啓鍏ョ湡瀹炶璇佸瓧娈点?佹竻绌哄娉ㄥ苟璁板綍鏃ュ織銆?
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 269 鑺傘??
- GREEN锛氳拷鍔犵 269 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminReviewAuthApproveStateClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆乣user_logins`銆乣user_infos`銆乣user_auths` 涓? `operation_logs` 琛ㄨ褰曪紝鍚庡彴 admin guard 鐧诲綍鎬佸拰 `/api/admin/reviewAuth` 瀹℃牳鍏ュ彛銆?
- 瀹℃牳閫氳繃鍚? `user_auths.id_card_status=2`銆乣user_auths.bank_status=2`銆佹嫆缁濆娉ㄦ竻绌猴紝`user_infos.auth_status=1`銆?
- 鎿嶄綔鏃ュ織璁板綍绠＄悊鍛樸?佺洰鏍囩敤鎴枫?佸鏍哥姸鎬佸拰璁よ瘉鐘舵?佸彉鏇磋建杩广??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩璁よ瘉寰呭鍒楄〃銆佸凡瀹″垪琛ㄣ?佽鎯呴〉鑴氭湰銆佹暟鎹寖鍥存湇鍔°?佸墠绔〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴鏅?氱敤鎴锋ā鍧椼?佷唬鐞嗗晢妯″潡鍜屽悗鍙扮鐞嗗憳妯″潡鍏跺畠鍓╀綑鍏ュ彛銆?
## 272. 2026-07-09 鍚庡彴浠ｇ悊鍟嗕剑閲戞洿鏂扮洰鏍? ID 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `AgentController::updateCommission` 琛ラ綈浠ｇ悊浣ｉ噾鏇存柊 `agent_id` 涓ユ牸鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/updateAgentCommission` 涓嶈兘鎶? `agent_id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鍓嶇紑鏁板瓧鍖归厤鐪熷疄浠ｇ悊銆?
- 楠岃瘉闈炴硶 `agent_id` 杩斿洖 `ResponseCode::VALIDATION_FAILED`锛屼笖涓嶆敼鍐欑洰鏍囦唬鐞嗙殑 `user_infos.comm_rate`銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminAgentCommissionAgentIdValidationClosureModuleTest.php`
  - 鏂板闈炰弗鏍? `agent_id` 琚嫆缁濅笖涓嶈惤搴撶殑鏍蜂緥銆?
- `app/Http/Controllers/Admin/AgentController.php`
  - 鍦? `updateCommission` 鏌ヨ `user_infos.user_id` 鍓嶅厛鏍￠獙 `agent_id` 涓? `comm_rate`锛屾牎楠岄?氳繃鍚庡啀杞崲鐩爣 ID 骞舵墽琛屼笟鍔￠?昏緫銆?

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAgentCommissionAgentIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓? `agent_id=鐪熷疄IDabc` 浠嶈繑鍥炴垚鍔熺爜锛屾渶缁堟竻鍗曚篃缂哄皯绗? 272 鑺傘??
- 璋冭瘯锛氱湡瀹? `user_infos.comm_rate` 瀵瑰皬鏁版祴璇曞す鍏蜂細鎸夊綋鍓嶈〃缁撴瀯钀戒负 `0.0`锛屽洜姝ゅ皢涓嶆敼鍐欐柇瑷?鐨勫師濮嬪?艰皟鏁翠负 `1.0`銆?
- GREEN锛氳ˉ榻? `updateCommission` 鍓嶇疆鍙傛暟鏍￠獙鍜岀 272 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminAgentCommissionAgentIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆乣user_logins` 涓? `user_infos` 琛ㄨ褰曪紝鍚庡彴 admin guard 鐧诲綍鎬佸拰 `/api/admin/updateAgentCommission` 鏇存柊鍏ュ彛銆?
- 闈炰弗鏍? `agent_id=鐪熷疄IDabc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍘? `user_infos.comm_rate` 淇濇寔鍘熷?硷紝閬垮厤浣ｉ噾鏇存柊鎺ュ彛鍦ㄥ弬鏁版牎楠屽墠琚暟鎹簱鏁板瓧鍓嶇紑瑙勫垯鍛戒腑鐪熷疄浠ｇ悊銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊鍒楄〃銆佷唬鐞嗚鎯呫?佷笅绾у垪琛ㄣ?佺瓑绾ф洿鏂般?佺‘璁?/鎷掔粷浠ｇ悊銆佸墠绔〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤浠ｇ悊鍟嗘ā鍧椼?佸悗鍙版櫘閫氱敤鎴锋ā鍧楀拰鍚庡彴绠＄悊鍛樻ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??
## 273. 2026-07-09 鍚庡彴浠ｇ悊鍟嗙瓑绾ф洿鏂扮洰鏍? ID 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `AgentController::updateLevel` 琛ラ綈浠ｇ悊绛夌骇鏇存柊 `agent_id` 涓ユ牸鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/updateAgentLevel` 涓嶈兘鎶? `agent_id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鍓嶇紑鏁板瓧鍖归厤鐪熷疄浠ｇ悊銆?
- 楠岃瘉闈炴硶 `agent_id` 杩斿洖 `ResponseCode::VALIDATION_FAILED`锛屼笖涓嶆敼鍐欑洰鏍囦唬鐞嗙殑 `user_infos.level_id`銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminAgentLevelAgentIdValidationClosureModuleTest.php`
  - 鏂板闈炰弗鏍? `agent_id` 琚嫆缁濅笖涓嶈惤搴撶殑鏍蜂緥銆?
- `app/Http/Controllers/Admin/AgentController.php`
  - 鍦? `updateLevel` 鏌ヨ `user_infos.user_id` 鍓嶅厛鏍￠獙 `agent_id` 涓? `level`锛屾牎楠岄?氳繃鍚庡啀杞崲鐩爣 ID 骞舵墽琛屼笟鍔￠?昏緫銆?

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAgentLevelAgentIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓? `agent_id=鐪熷疄IDabc` 浠嶈繑鍥炴垚鍔熺爜锛屾渶缁堟竻鍗曚篃缂哄皯绗? 273 鑺傘??
- GREEN锛氳ˉ榻? `updateLevel` 鍓嶇疆鍙傛暟鏍￠獙鍜岀 273 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminAgentLevelAgentIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆乣agent_levels`銆乣user_logins` 涓? `user_infos` 琛ㄨ褰曪紝鍚庡彴 admin guard 鐧诲綍鎬佸拰 `/api/admin/updateAgentLevel` 鏇存柊鍏ュ彛銆?
- 闈炰弗鏍? `agent_id=鐪熷疄IDabc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍘? `user_infos.level_id` 淇濇寔鍘熷?硷紝閬垮厤绛夌骇鏇存柊鎺ュ彛鍦ㄥ弬鏁版牎楠屽墠琚暟鎹簱鏁板瓧鍓嶇紑瑙勫垯鍛戒腑鐪熷疄浠ｇ悊銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊鍒楄〃銆佷唬鐞嗚鎯呫?佷笅绾у垪琛ㄣ?佷剑閲戞洿鏂般?佺‘璁?/鎷掔粷浠ｇ悊銆佸墠绔〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤浠ｇ悊鍟嗘ā鍧椼?佸悗鍙版櫘閫氱敤鎴锋ā鍧楀拰鍚庡彴绠＄悊鍛樻ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??
## 274. 2026-07-09 鍚庡彴浠ｇ悊鍟嗙‘璁ら?氳繃娓呯悊鏃ф嫆缁濆娉ㄩ棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `AgentController::confirmAgent` 琛ラ綈纭閫氳繃鍚庣殑鐘舵?侀棴鐜祴璇曘??
- 楠岃瘉 `/api/admin/confirmAgent` 灏? `is_agent_confirmed` 鏇存柊涓洪?氳繃鏃讹紝浼氬悓姝ユ竻绌轰笂涓?娆℃嫆缁濆啓鍏ョ殑 `user_infos.remark`銆?
- 閬垮厤浠ｇ悊宸茬‘璁や絾璇︽儏涓粛娈嬬暀鏃ф嫆缁濆師鍥狅紝閫犳垚鍚庡彴鍜屽墠鍙扮姸鎬佽В閲婁笉涓?鑷淬??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminAgentConfirmationApproveRemarkClosureModuleTest.php`
  - 鏂板甯︽棫鎷掔粷澶囨敞鐨勫緟纭浠ｇ悊鍐嶆纭閫氳繃鏍蜂緥銆?
- `app/Http/Controllers/Admin/AgentController.php`
  - 鍦? `confirmAgent` 鐨勪簨鍔″唴鍚屾椂鍐欏叆 `is_agent_confirmed=1` 涓庣┖ `remark`銆?

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAgentConfirmationApproveRemarkClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓‘璁ら?氳繃鍚? `user_infos.remark` 浠嶄繚鐣欐棫鎷掔粷鍘熷洜锛屾渶缁堟竻鍗曚篃缂哄皯绗? 274 鑺傘??
- GREEN锛氳ˉ榻? `confirmAgent` 娓呯┖鏃у娉ㄩ?昏緫鍜岀 274 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminAgentConfirmationApproveRemarkClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆乣user_logins` 涓? `user_infos` 琛ㄨ褰曪紝鍚庡彴 admin guard 鐧诲綍鎬佸拰 `/api/admin/confirmAgent` 鏇存柊鍏ュ彛銆?
- 纭閫氳繃鍚? `is_agent_confirmed=1`銆?
- 鍘? `user_infos.remark` 琚竻绌猴紝閬垮厤鏃ф嫆缁濆師鍥犲湪閫氳繃鐘舵?佷笅缁х画娈嬬暀銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊鍒楄〃銆佷唬鐞嗚鎯呫?佷笅绾у垪琛ㄣ?佺瓑绾ф洿鏂般?佷剑閲戞洿鏂般?佹嫆缁濅唬鐞嗐?佸墠绔〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤浠ｇ悊鍟嗘ā鍧椼?佸悗鍙版櫘閫氱敤鎴锋ā鍧楀拰鍚庡彴绠＄悊鍛樻ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??
## 275. 2026-07-09 鍚庡彴浠ｇ悊鍟嗘嫆缁濆師鍥? trim 鍚庨潪绌烘牎楠岄棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `AgentController::rejectAgentConfirmation` 琛ラ綈鎷掔粷鍘熷洜绌虹櫧瀛楃涓茶竟鐣屾祴璇曘??
- 楠岃瘉 `/api/admin/rejectAgentConfirmation` 鏀跺埌绌烘牸 `reason` 鏃惰繑鍥? `ResponseCode::VALIDATION_FAILED`銆?
- 楠岃瘉绌虹櫧鍘熷洜涓嶄細鏀瑰啓 `is_agent_confirmed`銆乣user_infos.remark`锛屼篃涓嶄細鍐欏叆 `operation_logs`銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminAgentRejectReasonTrimValidationClosureModuleTest.php`
  - 鏂板绌烘牸鎷掔粷鍘熷洜琚弬鏁版牎楠屾嫤鎴笖涓嶈惤搴撶殑鏍蜂緥銆?

### TDD 鎵ц璁板綍
- 琛屼负楠岃瘉锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAgentRejectReasonTrimValidationClosureModuleTest.php` 棣栨杩愯涓涓烘牱渚嬪凡閫氳繃锛岃瘉鏄庡綋鍓嶈姹傛竻鐞嗕笌 `required|string|max:500` 鏍￠獙浼氭嫆缁濈┖鏍煎師鍥犮??
- RED锛氭柊澧炴竻鍗曟祴璇曢娆″け璐ワ紝鍛戒腑鏈?缁堟竻鍗曠己灏戠 275 鑺傘??
- GREEN锛氳拷鍔犵 275 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminAgentRejectReasonTrimValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆乣user_logins`銆乣user_infos` 涓? `operation_logs` 琛ㄨ褰曪紝鍚庡彴 admin guard 鐧诲綍鎬佸拰 `/api/admin/rejectAgentConfirmation` 鏇存柊鍏ュ彛銆?
- 绌烘牸 `reason` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍘? `is_agent_confirmed` 涓? `user_infos.remark` 淇濇寔鍘熷?硷紝涓旀病鏈夋柊澧? `operation_logs` 浠ｇ悊纭鏃ュ織銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊鍒楄〃銆佷唬鐞嗚鎯呫?佷笅绾у垪琛ㄣ?佺瓑绾ф洿鏂般?佷剑閲戞洿鏂般?佺‘璁や唬鐞嗐?佸墠绔〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤浠ｇ悊鍟嗘ā鍧椼?佸悗鍙版櫘閫氱敤鎴锋ā鍧楀拰鍚庡彴绠＄悊鍛樻ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??
## 276. 2026-07-09 鍚庡彴浠ｇ悊鍟嗚鎯呯洰鏍? ID 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `AgentController::show` 琛ラ綈浠ｇ悊璇︽儏璇诲彇 `agent_id` 涓ユ牸鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/agentDetail` 涓嶈兘鎶? `agent_id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鍓嶇紑鏁板瓧鍖归厤鐪熷疄浠ｇ悊銆?
- 楠岃瘉闈炴硶 `agent_id` 杩斿洖 `ResponseCode::VALIDATION_FAILED`锛屼笖涓嶈繑鍥炵洰鏍囦唬鐞嗚祫鏂欍??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminAgentDetailAgentIdValidationClosureModuleTest.php`
  - 鏂板闈炰弗鏍? `agent_id` 琚嫆缁濅笖鍝嶅簲涓嶅寘鍚唬鐞嗚祫鏂欑殑鏍蜂緥銆?
- `app/Http/Controllers/Admin/AgentController.php`
  - 鍦? `show` 鏌ヨ `user_infos.user_id` 鍓嶅厛鏍￠獙 `agent_id`锛屾牎楠岄?氳繃鍚庡啀杞崲鐩爣 ID 骞惰鍙栬鎯呫??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAgentDetailAgentIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓? `agent_id=鐪熷疄IDabc` 浠嶈繑鍥炴垚鍔熺爜骞跺彲璇诲彇鐪熷疄浠ｇ悊璇︽儏锛屾渶缁堟竻鍗曚篃缂哄皯绗? 276 鑺傘??
- GREEN锛氳ˉ榻? `show` 鍓嶇疆鍙傛暟鏍￠獙鍜岀 276 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminAgentDetailAgentIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆乣user_logins` 涓? `user_infos` 琛ㄨ褰曪紝鍚庡彴 admin guard 鐧诲綍鎬佸拰 `/api/admin/agentDetail` 璇︽儏鍏ュ彛銆?
- 闈炰弗鏍? `agent_id=鐪熷疄IDabc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍝嶅簲鍐呭涓嶅寘鍚洰鏍囦唬鐞嗗悕绉帮紝閬垮厤璇︽儏鎺ュ彛鍦ㄥ弬鏁版牎楠屽墠琚暟鎹簱鏁板瓧鍓嶇紑瑙勫垯鍛戒腑鐪熷疄浠ｇ悊銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊鍒楄〃銆佷笅绾у垪琛ㄣ?佺瓑绾ф洿鏂般?佷剑閲戞洿鏂般?佺‘璁?/鎷掔粷浠ｇ悊銆佸墠绔〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤浠ｇ悊鍟嗘ā鍧椼?佸悗鍙版櫘閫氱敤鎴锋ā鍧楀拰鍚庡彴绠＄悊鍛樻ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??
## 271. 2026-07-09 鍚庡彴浠ｇ悊鍟嗙瓑绾ф洿鏂板瓨鍦ㄦ?ф牎楠岄棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `AgentController::updateLevel` 琛ラ綈浠ｇ悊绛夌骇瀛樺湪鎬ф牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/updateAgentLevel` 鍙厑璁告妸浠ｇ悊鏇存柊鍒扮湡瀹炲瓨鍦ㄧ殑 `agent_levels.id`銆?
- 楠岃瘉涓嶅瓨鍦ㄧ殑绛夌骇 ID 杩斿洖 `ResponseCode::VALIDATION_FAILED`锛屼笖涓嶆敼鍐欑洰鏍囦唬鐞嗙殑 `user_infos.level_id`銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminAgentLevelExistsValidationClosureModuleTest.php`
  - 鏂板涓嶅瓨鍦ㄤ唬鐞嗙瓑绾ц鎷掔粷涓斾笉钀藉簱鐨勬牱渚嬨??
- `app/Http/Controllers/Admin/AgentController.php`
  - 鍦? `updateLevel` 鐨? `level` 鍙傛暟鏍￠獙涓鍔? `exists:agent_levels,id`銆?

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAgentLevelExistsValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓笉瀛樺湪鐨? `level` 浠嶈繑鍥炴垚鍔熺爜涓旀渶缁堟竻鍗曠己灏戠 271 鑺傘??
- GREEN锛氳ˉ榻? `AgentController::updateLevel` 绛夌骇瀛樺湪鎬ф牎楠屽拰绗? 271 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminAgentLevelExistsValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆乣agent_levels`銆乣user_logins` 涓? `user_infos` 琛ㄨ褰曪紝鍚庡彴 admin guard 鐧诲綍鎬佸拰 `/api/admin/updateAgentLevel` 鏇存柊鍏ュ彛銆?
- 涓嶅瓨鍦ㄧ殑 `agent_levels.id` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍘? `user_infos.level_id` 淇濇寔鍘熷?硷紝閬垮厤浠ｇ悊绛夌骇琚啓鎴愪笉瀛樺湪鐨勯厤缃? ID銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊鍒楄〃銆佷唬鐞嗚鎯呫?佷笅绾у垪琛ㄣ?佷剑閲戞洿鏂般?佺‘璁?/鎷掔粷浠ｇ悊銆佸墠绔〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤浠ｇ悊鍟嗘ā鍧椼?佸悗鍙版櫘閫氱敤鎴锋ā鍧楀拰鍚庡彴绠＄悊鍛樻ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??
## 270. 2026-07-09 鍚庡彴鏅?氱敤鎴峰疄鍚嶈璇佸鏍哥姸鎬佷弗鏍兼牎楠岄棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `AdminUserController::reviewAuth` 琛ラ綈瀹炲悕璁よ瘉瀹℃牳鐘舵?佸弬鏁颁弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/reviewAuth` 鍙帴鍙? `status=1/2`锛屼笉鑳芥妸 `status=1abc` 閫氳繃 PHP 寮鸿浆褰撲綔瀹℃牳閫氳繃銆?
- 楠岃瘉闈炴硶瀹℃牳鐘舵?佽繑鍥? `ResponseCode::VALIDATION_FAILED`锛屼笖涓嶆敼鍐? `user_auths`銆乣user_infos.auth_status` 鎴栧啓鍏ユ搷浣滄棩蹇椼??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminReviewAuthStatusValidationClosureModuleTest.php`
  - 鏂板闈炰弗鏍? `status=1abc` 琚嫆缁濅笖涓嶈惤搴撶殑鏍蜂緥銆?
- `app/Http/Controllers/Admin/AdminUserController.php`
  - 鍦? `reviewAuth` 寮?澶村鍔? `Validator` 鍙傛暟鏍￠獙锛屽厛鏍￠獙 `user_id` 涓? `status=1/2`锛屽啀寮鸿浆骞舵墽琛屼笟鍔″啓鍏ャ??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminReviewAuthStatusValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓? `status=1abc` 琚己杞负 `1` 骞惰繑鍥炴垚鍔熺爜锛屾渶缁堟竻鍗曚篃缂哄皯绗? 270 鑺傘??
- GREEN锛氳ˉ榻? `reviewAuth` 涓ユ牸鍙傛暟鏍￠獙鍜岀 270 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminReviewAuthStatusValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆乣user_logins`銆乣user_infos`銆乣user_auths` 涓? `operation_logs` 琛ㄨ褰曪紝鍚庡彴 admin guard 鐧诲綍鎬佸拰 `/api/admin/reviewAuth` 瀹℃牳鍏ュ彛銆?
- 闈炴硶 `status=1abc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍘? `user_auths.id_card_status`銆乣user_auths.bank_status`銆佸娉ㄥ拰 `user_infos.auth_status` 淇濇寔鍘熷?硷紝涓旀病鏈夋柊澧? `operation_logs` 瀹℃牳鏃ュ織銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩璁よ瘉寰呭鍒楄〃銆佸凡瀹″垪琛ㄣ?佽鎯呴〉鑴氭湰銆佹暟鎹寖鍥存湇鍔°?佸墠绔〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴鏅?氱敤鎴锋ā鍧椼?佷唬鐞嗗晢妯″潡鍜屽悗鍙扮鐞嗗憳妯″潡鍏跺畠鍓╀綑鍏ュ彛銆?
## 277. 2026-07-09 鍚庡彴浠ｇ悊涓嬬骇鍒楄〃鐩爣 ID 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `AgentController::descendants` 琛ラ綈浠ｇ悊涓嬬骇鍒楄〃 `agent_id` 涓ユ牸鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/agentDescendants` 涓嶈兘鎶? `agent_id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鍓嶇紑鏁板瓧鍖归厤鐪熷疄浠ｇ悊銆?
- 楠岃瘉闈炴硶 `agent_id` 杩斿洖 `ResponseCode::VALIDATION_FAILED`锛屼笖涓嶈繑鍥炶浠ｇ悊閫氳繃 `user_infos.parent_id` 鍏崇郴灞曞紑鍑虹殑涓嬬骇璧勬枡銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminAgentDescendantsAgentIdValidationClosureModuleTest.php`
  - 鏂板闈炰弗鏍? `agent_id` 琚嫆缁濅笖鍝嶅簲涓嶅寘鍚笅绾т唬鐞嗚祫鏂欑殑鏍蜂緥銆?
- `app/Http/Controllers/Admin/AgentController.php`
  - 鍦? `descendants` 鏌ヨ浠ｇ悊鏍戝墠鍏堟牎楠? `agent_id`锛屾牎楠岄?氳繃鍚庡啀杞崲鐩爣 ID 骞惰鍙栦笅绾у垪琛ㄣ??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAgentDescendantsAgentIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓? `agent_id=鐪熷疄IDabc` 浠嶈繑鍥炴垚鍔熺爜骞跺睍寮?鐪熷疄涓嬬骇鍒楄〃锛屾渶缁堟竻鍗曚篃缂哄皯绗? 277 鑺傘??
- GREEN锛氳ˉ榻? `descendants` 鍓嶇疆鍙傛暟鏍￠獙鍜岀 277 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminAgentDescendantsAgentIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆乣user_logins`銆乣user_infos` 鍜? `/api/admin/agentDescendants` 涓嬬骇鍒楄〃鍏ュ彛銆?
- 闈炰弗鏍? `agent_id=鐪熷疄IDabc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍝嶅簲鍐呭涓嶅寘鍚?氳繃 `user_infos.parent_id` 鐩村睘鍏崇郴鏋勯?犲嚭鐨勪笅绾т唬鐞嗗悕绉帮紝閬垮厤涓嬬骇鍒楄〃鎺ュ彛鍦ㄥ弬鏁版牎楠屽墠琚暟鎹簱鏁板瓧鍓嶇紑瑙勫垯鍛戒腑鐪熷疄浠ｇ悊銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊鍒楄〃銆佷唬鐞嗚鎯呫?佺瓑绾ф洿鏂般?佷剑閲戞洿鏂般?佺‘璁?/鎷掔粷浠ｇ悊銆佸墠绔〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤浠ｇ悊鍟嗘ā鍧椼?佸悗鍙版櫘閫氱敤鎴锋ā鍧楀拰鍚庡彴绠＄悊鍛樻ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??
## 278. 2026-07-09 鍚庡彴浠ｇ悊缁熻鍒楄〃 user_id 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `AgentController::listWithStats` 琛ラ綈浠ｇ悊缁熻鍒楄〃 `user_id` 涓ユ牸鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/agentStatsList` 琛ㄥ崟绛涢?変笉鑳芥妸 `user_id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鍓嶇紑鏁板瓧鍖归厤鐪熷疄浠ｇ悊銆?
- 楠岃瘉闈炴硶 `user_id` 杩斿洖 `ResponseCode::VALIDATION_FAILED`锛屼笖涓嶈繑鍥? `user_infos.user_id` 鍛戒腑鐨勭湡瀹炰唬鐞嗚祫鏂欍??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminAgentStatsUserIdValidationClosureModuleTest.php`
  - 鏂板闈炰弗鏍? `user_id` 琚嫆缁濅笖鍝嶅簲涓嶅寘鍚唬鐞嗙粺璁¤鐨勬牱渚嬨??
- `app/Http/Controllers/Admin/AgentController.php`
  - 鍦? `listWithStats` 鏋勯?? `user_infos.user_id` 鏌ヨ鍓嶅厛鏍￠獙 `user_id`锛屾牎楠岄?氳繃鍚庡啀杞崲涓烘暣鏁板苟搴旂敤绛涢?夈??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAgentStatsUserIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓? `user_id=鐪熷疄IDabc` 浠嶈繑鍥炴垚鍔熺爜骞跺睍绀虹湡瀹炰唬鐞嗙粺璁¤锛屾渶缁堟竻鍗曚篃缂哄皯绗? 278 鑺傘??
- GREEN锛氳ˉ榻? `listWithStats` 鍓嶇疆鍙傛暟鏍￠獙鍜岀 278 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminAgentStatsUserIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆乣user_logins`銆乣user_infos` 鍜? `/api/admin/agentStatsList` 缁熻鍒楄〃鍏ュ彛銆?
- 闈炰弗鏍? `user_id=鐪熷疄IDabc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍝嶅簲鍐呭涓嶅寘鍚洰鏍囦唬鐞嗗悕绉帮紝閬垮厤缁熻鍒楄〃鍦ㄥ弬鏁版牎楠屽墠琚暟鎹簱鏁板瓧鍓嶇紑瑙勫垯鍛戒腑鐪熷疄浠ｇ悊銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊鏅?氬垪琛ㄣ?佸鍑恒?佽鎯呫?佷笅绾у垪琛ㄣ?佺瓑绾ф洿鏂般?佷剑閲戞洿鏂般?佺‘璁?/鎷掔粷浠ｇ悊銆佸墠绔〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤浠ｇ悊鍟嗘ā鍧椼?佸悗鍙版櫘閫氱敤鎴锋ā鍧楀拰鍚庡彴绠＄悊鍛樻ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??
## 279. 2026-07-09 鍚庡彴浠ｇ悊鍒楄〃涓庡鍑? agent_id 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `AgentController::filteredAgentQuery` 琛ラ綈浠ｇ悊鏅?氬垪琛ㄥ拰瀵煎嚭鍏辩敤 `agent_id` 涓ユ牸鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/agents` 涓嶈兘鎶? `agent_id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鍓嶇紑鏁板瓧鍖归厤鐪熷疄浠ｇ悊銆?
- 楠岃瘉 `/api/admin/exportAgents` 鏀跺埌闈炴硶 `agent_id` 鏃惰繑鍥? `ResponseCode::VALIDATION_FAILED`锛屼笉鑳界户缁緭鍑哄寘鍚湡瀹炰唬鐞嗚鐨? CSV銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminAgentListExportAgentIdValidationClosureModuleTest.php`
  - 鏂板鍒楄〃闈炰弗鏍? `agent_id` 琚嫆缁濅笖鍝嶅簲涓嶅寘鍚唬鐞嗚祫鏂欑殑鏍蜂緥銆?
  - 鏂板瀵煎嚭闈炰弗鏍? `agent_id` 琚嫆缁濅笖涓嶈繘鍏? CSV 娴佸搷搴旂殑鏍蜂緥銆?
- `app/Http/Controllers/Admin/AgentController.php`
  - 鏂板浠ｇ悊鍒楄〃鍜屽鍑哄叡鐢ㄧ殑 `agent_id` 绛涢?夊弬鏁版牎楠屻??
  - `filteredAgentQuery` 鍦ㄥ簲鐢? `user_infos.user_id` 绛涢?夋椂浣跨敤鏍￠獙鍚庣殑鏁存暟鍊笺??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAgentListExportAgentIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓垪琛ㄤ粛杩斿洖鎴愬姛鐮併?佸鍑轰粛杩涘叆 CSV 娴佸搷搴旓紝鏈?缁堟竻鍗曚篃缂哄皯绗? 279 鑺傘??
- GREEN锛氳ˉ榻愬叡鐢? `agent_id` 鏍￠獙銆佹暣鏁板寲绛涢?夊拰绗? 279 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminAgentListExportAgentIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆乣user_logins`銆乣user_infos`銆乣/api/admin/agents` 鍜? `/api/admin/exportAgents` 涓や釜璇诲彇鍏ュ彛銆?
- 闈炰弗鏍? `agent_id=鐪熷疄IDabc` 鍦ㄥ垪琛ㄥ拰瀵煎嚭鍏ュ彛鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`銆?
- 鍝嶅簲鍐呭涓嶅寘鍚洰鏍囦唬鐞嗗悕绉帮紝閬垮厤 `user_infos.user_id` 绛涢?夊湪鍙傛暟鏍￠獙鍓嶈鏁版嵁搴撴暟瀛楀墠缂?瑙勫垯鍛戒腑鐪熷疄浠ｇ悊銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊璇︽儏銆佷笅绾у垪琛ㄣ?佺粺璁″垪琛ㄣ?佺瓑绾ф洿鏂般?佷剑閲戞洿鏂般?佺‘璁?/鎷掔粷浠ｇ悊銆佸墠绔〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤浠ｇ悊鍟嗘ā鍧椼?佸悗鍙版櫘閫氱敤鎴锋ā鍧楀拰鍚庡彴绠＄悊鍛樻ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??
## 280. 2026-07-09 鍚庡彴鏅?氱敤鎴疯鎯? user_id 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `AdminUserController::userDetail` 琛ラ綈鏃у悗鍙扮敤鎴疯鎯呭叆鍙? `user_id` 涓ユ牸鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/userDetail` 涓嶈兘鎶? `user_id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鍓嶇紑鏁板瓧鍖归厤鐪熷疄鐢ㄦ埛銆?
- 楠岃瘉闈炴硶 `user_id` 杩斿洖 `ResponseCode::VALIDATION_FAILED`锛屼笖涓嶈繑鍥? `user_infos.user_id` 鍛戒腑鐨勭敤鎴疯祫鏂欍??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminUserDetailUserIdValidationClosureModuleTest.php`
  - 鏂板闈炰弗鏍? `user_id` 琚嫆缁濅笖鍝嶅簲涓嶅寘鍚敤鎴疯祫鏂欑殑鏍蜂緥銆?
- `app/Http/Controllers/Admin/AdminUserController.php`
  - 鍦? `userDetail` 鏌ヨ `user_infos.user_id` 鍓嶅厛鏍￠獙 `user_id`锛屾牎楠岄?氳繃鍚庡啀杞崲涓烘暣鏁板苟璇诲彇璇︽儏銆?

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminUserDetailUserIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓? `user_id=鐪熷疄IDabc` 浠嶈繑鍥炴垚鍔熺爜骞跺彲璇诲彇鐪熷疄鐢ㄦ埛璇︽儏锛屾渶缁堟竻鍗曚篃缂哄皯绗? 280 鑺傘??
- GREEN锛氳ˉ榻? `userDetail` 鍓嶇疆鍙傛暟鏍￠獙鍜岀 280 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminUserDetailUserIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆乣user_logins`銆乣user_infos` 鍜? `/api/admin/userDetail` 鏃ц鎯呭叆鍙ｃ??
- 闈炰弗鏍? `user_id=鐪熷疄IDabc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍝嶅簲鍐呭涓嶅寘鍚洰鏍囩敤鎴峰悕绉帮紝閬垮厤璇︽儏鎺ュ彛鍦ㄥ弬鏁版牎楠屽墠琚暟鎹簱鏁板瓧鍓嶇紑瑙勫垯鍛戒腑鐪熷疄鐢ㄦ埛銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鐢ㄦ埛鍒楄〃銆佸鍑恒?佽祫鏂欐洿鏂般?佺櫥褰曞惎鍋溿?佸疄鍚嶈璇佸鏍搞?佹暟鎹寖鍥存湇鍔°?佸墠绔〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴鏅?氱敤鎴锋ā鍧椼?佷唬鐞嗗晢妯″潡鍜屽悗鍙扮鐞嗗憳妯″潡鍏跺畠鍓╀綑鍏ュ彛銆?
## 281. 2026-07-09 鍚庡彴鏅?氱敤鎴疯祫鏂欐洿鏂? user_id 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `AdminUserController::updateUser` 琛ラ綈鏃у悗鍙扮敤鎴疯祫鏂欐洿鏂板叆鍙? `user_id` 涓ユ牸鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/updateUser` 涓嶈兘鎶? `user_id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鍓嶇紑鏁板瓧鍖归厤鐪熷疄鐢ㄦ埛銆?
- 楠岃瘉闈炴硶 `user_id` 杩斿洖 `ResponseCode::VALIDATION_FAILED`锛屼笖涓嶆敼鍐? `user_infos.user_id` 鍛戒腑鐨勭敤鎴峰熀纭?璧勬枡銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminUserUpdateUserIdValidationClosureModuleTest.php`
  - 鏂板闈炰弗鏍? `user_id` 琚嫆缁濅笖涓嶆敼鍐? `user_name`銆乣phone` 鐨勬牱渚嬨??
- `app/Http/Controllers/Admin/AdminUserController.php`
  - 鍦? `updateUser` 鏌ヨ `user_infos.user_id` 鍓嶅厛鏍￠獙 `user_id`锛屾牎楠岄?氳繃鍚庡啀杞崲涓烘暣鏁板苟鎵ц璧勬枡鏇存柊銆?

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminUserUpdateUserIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓? `user_id=鐪熷疄IDabc` 浠嶈繑鍥炴洿鏂版垚鍔熺爜锛屾渶缁堟竻鍗曚篃缂哄皯绗? 281 鑺傘??
- GREEN锛氳ˉ榻? `updateUser` 鍓嶇疆鍙傛暟鏍￠獙鍜岀 281 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminUserUpdateUserIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆乣user_logins`銆乣user_infos` 鍜? `/api/admin/updateUser` 鏃ц祫鏂欎繚瀛樺叆鍙ｃ??
- 闈炰弗鏍? `user_id=鐪熷疄IDabc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍘? `user_infos.user_name` 涓? `user_infos.phone` 淇濇寔鍘熷?硷紝閬垮厤璧勬枡鏇存柊鎺ュ彛鍦ㄥ弬鏁版牎楠屽墠琚暟鎹簱鏁板瓧鍓嶇紑瑙勫垯鍛戒腑鐪熷疄鐢ㄦ埛銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鐢ㄦ埛鍒楄〃銆佸鍑恒?佽鎯呰鍙栥?佺櫥褰曞惎鍋溿?佸疄鍚嶈璇佸鏍搞?佹暟鎹寖鍥存湇鍔°?佸墠绔〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴鏅?氱敤鎴锋ā鍧椼?佷唬鐞嗗晢妯″潡鍜屽悗鍙扮鐞嗗憳妯″潡鍏跺畠鍓╀綑鍏ュ彛銆?
## 282. 2026-07-09 鍚庡彴鏅?氱敤鎴风櫥褰曞惎鍋? user_id 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `AdminUserController::changeUserStatus` 琛ラ綈鏃у悗鍙扮敤鎴风櫥褰曞惎鍋滃叆鍙? `user_id` 涓ユ牸鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/changeUserStatus` 涓嶈兘鎶? `user_id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鍓嶇紑鏁板瓧鍖归厤鐪熷疄鐢ㄦ埛銆?
- 楠岃瘉闈炴硶 `user_id` 杩斿洖 `ResponseCode::VALIDATION_FAILED`锛屼笖涓嶆敼鍐? `user_logins.user_id` 鍛戒腑鐨勭櫥褰曞惎鍋滅姸鎬併??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminUserStatusUserIdValidationClosureModuleTest.php`
  - 鏂板闈炰弗鏍? `user_id` 琚嫆缁濅笖涓嶆敼鍐? `user_logins.is_enabled` 鐨勬牱渚嬨??
- `app/Http/Controllers/Admin/AdminUserController.php`
  - 鍦? `changeUserStatus` 鏌ヨ `user_logins.user_id` 鍓嶅厛鏍￠獙 `user_id` 涓? `is_enabled`锛屾牎楠岄?氳繃鍚庡啀杞崲涓烘暣鏁板苟鎵ц鍚仠鍐欏叆銆?

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminUserStatusUserIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓? `user_id=鐪熷疄IDabc` 浠嶈繑鍥炴垚鍔熺爜锛屾渶缁堟竻鍗曚篃缂哄皯绗? 282 鑺傘??
- GREEN锛氳ˉ榻? `changeUserStatus` 鍓嶇疆鍙傛暟鏍￠獙鍜岀 282 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminUserStatusUserIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆乣user_logins`銆乣user_infos` 鍜? `/api/admin/changeUserStatus` 鏃х櫥褰曞惎鍋滃叆鍙ｃ??
- 闈炰弗鏍? `user_id=鐪熷疄IDabc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍘? `user_logins.is_enabled` 淇濇寔鍘熷?硷紝閬垮厤鐧诲綍鍚仠鎺ュ彛鍦ㄥ弬鏁版牎楠屽墠琚暟鎹簱鏁板瓧鍓嶇紑瑙勫垯鍛戒腑鐪熷疄鐢ㄦ埛銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鐢ㄦ埛鍒楄〃銆佸鍑恒?佽鎯呰鍙栥?佽祫鏂欐洿鏂般?佸疄鍚嶈璇佸鏍搞?佹暟鎹寖鍥存湇鍔°?佸墠绔〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴鏅?氱敤鎴锋ā鍧椼?佷唬鐞嗗晢妯″潡鍜屽悗鍙扮鐞嗗憳妯″潡鍏跺畠鍓╀綑鍏ュ彛銆?
## 283. 2026-07-09 鍚庡彴鏅?氱敤鎴峰垪琛ㄤ笌瀵煎嚭 user_id 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `AdminUserController::filteredUserQuery` 琛ラ綈鐢ㄦ埛鍒楄〃鍜屽鍑哄叡鐢? `user_id` 涓ユ牸鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/userList` 涓嶈兘鎶? `user_id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鍓嶇紑鏁板瓧鍖归厤鐪熷疄鐢ㄦ埛銆?
- 楠岃瘉 `/api/admin/exportUsers` 鏀跺埌闈炴硶 `user_id` 鏃惰繑鍥? `ResponseCode::VALIDATION_FAILED`锛屼笉鑳借繘鍏ュ寘鍚湡瀹炵敤鎴疯鐨? CSV 娴佸搷搴斻??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminUserListExportUserIdValidationClosureModuleTest.php`
  - 鏂板鍒楄〃闈炰弗鏍? `user_id` 琚嫆缁濅笖鍝嶅簲涓嶅寘鍚敤鎴疯祫鏂欑殑鏍蜂緥銆?
  - 鏂板瀵煎嚭闈炰弗鏍? `user_id` 琚嫆缁濅笖涓嶈繘鍏? CSV 娴佸搷搴旂殑鏍蜂緥銆?
- `app/Http/Controllers/Admin/AdminUserController.php`
  - 鏂板鐢ㄦ埛鍒楄〃鍜屽鍑哄叡鐢ㄧ殑 `user_id` 绛涢?夊弬鏁版牎楠屻??
  - `filteredUserQuery` 鍦ㄥ簲鐢? `user_infos.user_id` 绛涢?夋椂浣跨敤鏍￠獙鍚庣殑鏁存暟鍊笺??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminUserListExportUserIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓垪琛ㄤ粛杩斿洖鎴愬姛鐮併?佸鍑轰粛杩涘叆 CSV 娴佸搷搴旓紝鏈?缁堟竻鍗曚篃缂哄皯绗? 283 鑺傘??
- GREEN锛氳ˉ榻愬叡鐢? `user_id` 鏍￠獙銆佹暣鏁板寲绛涢?夊拰绗? 283 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminUserListExportUserIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆乣user_logins`銆乣user_infos`銆乣/api/admin/userList` 鍜? `/api/admin/exportUsers` 涓や釜璇诲彇鍏ュ彛銆?
- 闈炰弗鏍? `user_id=鐪熷疄IDabc` 鍦ㄥ垪琛ㄥ拰瀵煎嚭鍏ュ彛鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`銆?
- 鍝嶅簲鍐呭涓嶅寘鍚洰鏍囩敤鎴峰悕绉帮紝閬垮厤 `user_infos.user_id` 绛涢?夊湪鍙傛暟鏍￠獙鍓嶈鏁版嵁搴撴暟瀛楀墠缂?瑙勫垯鍛戒腑鐪熷疄鐢ㄦ埛銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鐢ㄦ埛璇︽儏銆佽祫鏂欐洿鏂般?佺櫥褰曞惎鍋溿?佸疄鍚嶈璇佸鏍搞?佹暟鎹寖鍥存湇鍔°?佸墠绔〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴鏅?氱敤鎴锋ā鍧椼?佷唬鐞嗗晢妯″潡鍜屽悗鍙扮鐞嗗憳妯″潡鍏跺畠鍓╀綑鍏ュ彛銆?
## 284. 2026-07-09 鍚庡彴绠＄悊鍛樿处鍙疯矾鐢? ID 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `AdminController::update`銆乣AdminController::resetPassword` 鍜? `AdminController::destroy` 琛ラ綈鏃ц矾鐢? `{id}` 涓ユ牸鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/updateAdmin/{id}` 涓嶈兘鎶? `id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鍓嶇紑鏁板瓧鍖归厤鐪熷疄鍚庡彴绠＄悊鍛樸??
- 楠岃瘉 `/api/admin/resetAdminPassword/{id}` 鍜? `/api/admin/deleteAdmin/{id}` 鏀跺埌闈炰弗鏍? ID 鏃惰繑鍥? `ResponseCode::VALIDATION_FAILED`锛屼笉鏀瑰啓瀵嗙爜涔熶笉鍒犻櫎璐﹀彿銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminAccountRouteIdValidationClosureModuleTest.php`
  - 鏂板缂栬緫銆侀噸缃瘑鐮併?佸垹闄や笁涓棫璺敱闈炰弗鏍? `{id}` 琚嫆缁濅笖涓嶈惤搴撶殑鏍蜂緥銆?
- `app/Http/Controllers/Admin/AdminController.php`
  - 鏂板鍚庡彴绠＄悊鍛樿处鍙疯矾鐢? ID 鍏辩敤鏍￠獙銆?
  - `update`銆乣resetPassword`銆乣destroy` 鍦ㄦ煡璇? `admins.id` 鍓嶅厛鏍￠獙璺敱鍙傛暟锛岄?氳繃鍚庡啀杞崲涓烘暣鏁般??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAccountRouteIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓紪杈戣繑鍥? `ResponseCode::UPDATED`銆侀噸缃瘑鐮佽繑鍥炴垚鍔熴?佸垹闄よ繑鍥? `ResponseCode::DELETED`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 284 鑺傘??
- GREEN锛氳ˉ榻愬叡鐢ㄨ矾鐢? ID 鏍￠獙銆佹暣鏁板寲鏌ヨ鍜岀 284 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminAccountRouteIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬併?乣/api/admin/updateAdmin/{id}`銆乣/api/admin/resetAdminPassword/{id}` 鍜? `/api/admin/deleteAdmin/{id}` 涓変釜鍏ュ彛銆?
- 闈炰弗鏍艰矾鐢? ID `鐪熷疄IDabc` 鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`銆?
- 鍘熻处鍙风敤鎴峰悕銆侀偖绠便?佹墜鏈哄彿銆佺姸鎬併?佸瘑鐮佸拰 `deleted_at` 淇濇寔鍘熷?硷紝閬垮厤 `admins.id` 鍦ㄥ弬鏁版牎楠屽墠琚暟鎹簱鏁板瓧鍓嶇紑瑙勫垯鍛戒腑鐪熷疄鍚庡彴绠＄悊鍛樸??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩绠＄悊鍛樺垪琛ㄣ?佹柊澧炵鐞嗗憳銆佽鑹?/鏉冮檺琛ㄣ?佸墠绔〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴绠＄悊鍛樻ā鍧椼?佸悗鍙版櫘閫氱敤鎴锋ā鍧楀拰浠ｇ悊鍟嗘ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??
## 285. 2026-07-09 鍚庡彴澶т唬鐞嗚矾鐢? ID 涓庢棫 status 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `BigAgentController::store`銆乣BigAgentController::update` 鍜? `BigAgentController::destroy` 琛ラ綈澶т唬鐞? ID 涓庡惎鍋滃瓧娈典弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/updateBigAgent/{id}` 鍜? `/api/admin/deleteBigAgent/{id}` 涓嶈兘鎶? `id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鍓嶇紑鏁板瓧鍖归厤鐪熷疄澶т唬鐞嗐??
- 楠岃瘉 `/api/admin/createBigAgent` 涓? `/api/admin/updateBigAgent/{id}` 鏀跺埌鏃у吋瀹瑰瓧娈? `status=1abc` 鏃惰繑鍥? `ResponseCode::VALIDATION_FAILED`锛屼笉鍐欏叆鎴栨敼鍐? `big_agents.is_enabled`銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminBigAgentIdStatusValidationClosureModuleTest.php`
  - 鏂板鍒涘缓銆佺紪杈戞椂闈炴硶鏃? `status` 琚嫆缁濅笖涓嶈惤搴撶殑鏍蜂緥銆?
  - 鏂板缂栬緫銆佸垹闄ゆ椂闈炰弗鏍艰矾鐢? `{id}` 琚嫆缁濅笖涓嶈惤搴撶殑鏍蜂緥銆?
- `app/Http/Controllers/Admin/BigAgentController.php`
  - 鏂板澶т唬鐞嗚矾鐢? ID 鍏辩敤鏍￠獙銆?
  - `update`銆乣destroy` 鍦ㄦ煡璇? `big_agents.id` 鍓嶅厛鏍￠獙璺敱鍙傛暟锛岄?氳繃鍚庡啀杞崲涓烘暣鏁般??
  - `store`銆乣update` 瀵规棫鍏煎 `status` 瀛楁鎵ц boolean 鏍￠獙锛屽啀浜ょ粰 `normalizePayload` 鏄犲皠鍒? `big_agents.is_enabled`銆?

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminBigAgentIdStatusValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓垱寤鸿繘鍏ユ湇鍔＄閿欒銆佺紪杈戣繑鍥? `ResponseCode::UPDATED`銆佸垹闄よ繑鍥? `ResponseCode::DELETED`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 285 鑺傘??
- GREEN锛氳ˉ榻愯矾鐢? ID 鏍￠獙銆佹棫 `status` boolean 鏍￠獙鍜岀 285 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminBigAgentIdStatusValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 涓? `big_agents` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬併?乣/api/admin/createBigAgent`銆乣/api/admin/updateBigAgent/{id}` 鍜? `/api/admin/deleteBigAgent/{id}` 涓変釜鍏ュ彛銆?
- 闈炰弗鏍艰矾鐢? ID `鐪熷疄IDabc` 涓庨潪娉曟棫瀛楁 `status=1abc` 鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`銆?
- 鍘熷ぇ浠ｇ悊鐢ㄦ埛鍚嶃?乣big_agents.is_enabled` 鍜? `deleted_at` 淇濇寔鍘熷?硷紝閬垮厤 ID 鎴栧惎鍋滅姸鎬佸湪鍙傛暟鏍￠獙鍓嶈閿欒钀藉簱銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩澶т唬鐞嗗垪琛ㄣ?佸墠绔〉闈€?佸墠鍙板ぇ浠ｇ悊鐧诲綍銆佷笅绾т唬鐞嗚寖鍥存垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴绠＄悊鍛樻ā鍧椼?佸悗鍙版櫘閫氱敤鎴锋ā鍧楀拰浠ｇ悊鍟嗘ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??
## 286. 2026-07-09 鍚庡彴浠ｇ悊绛夌骇閰嶇疆璺敱 ID 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `AgentLevelController::update` 鍜? `AgentLevelController::destroy` 琛ラ綈浠ｇ悊绛夌骇閰嶇疆璺敱 `{id}` 涓ユ牸鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/updateAgentLevel2/{id}` 涓嶈兘鎶? `id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鍓嶇紑鏁板瓧鍖归厤鐪熷疄浠ｇ悊绛夌骇銆?
- 楠岃瘉 `/api/admin/deleteAgentLevel/{id}` 鏀跺埌闈炰弗鏍? ID 鏃惰繑鍥? `ResponseCode::VALIDATION_FAILED`锛屼笉鍒犻櫎 `agent_levels.id` 鍛戒腑鐨勮褰曘??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminAgentLevelRouteIdValidationClosureModuleTest.php`
  - 鏂板缂栬緫銆佸垹闄や袱涓棫璺敱闈炰弗鏍? `{id}` 琚嫆缁濅笖涓嶈惤搴撶殑鏍蜂緥銆?
- `app/Http/Controllers/Admin/AgentLevelController.php`
  - 鏂板浠ｇ悊绛夌骇璺敱 ID 鍏辩敤鏍￠獙銆?
  - `update`銆乣destroy` 鍦ㄦ煡璇? `agent_levels.id` 鍓嶅厛鏍￠獙璺敱鍙傛暟锛岄?氳繃鍚庡啀杞崲涓烘暣鏁般??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAgentLevelRouteIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓紪杈戣繑鍥? `ResponseCode::UPDATED`銆佸垹闄よ繑鍥? `ResponseCode::DELETED`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 286 鑺傘??
- GREEN锛氳ˉ榻愬叡鐢ㄨ矾鐢? ID 鏍￠獙銆佹暣鏁板寲鏌ヨ鍜岀 286 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminAgentLevelRouteIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 涓? `agent_levels` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬併?乣/api/admin/updateAgentLevel2/{id}` 鍜? `/api/admin/deleteAgentLevel/{id}` 涓や釜鍏ュ彛銆?
- 闈炰弗鏍艰矾鐢? ID `鐪熷疄IDabc` 鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`銆?
- 鍘熶唬鐞嗙瓑绾х紪鐮併?佸悕绉般?佽繑浣ｅ瓧娈靛拰 `deleted_at` 淇濇寔鍘熷?硷紝閬垮厤 `agent_levels.id` 鍦ㄥ弬鏁版牎楠屽墠琚暟鎹簱鏁板瓧鍓嶇紑瑙勫垯鍛戒腑鐪熷疄浠ｇ悊绛夌骇銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊绛夌骇鍒楄〃銆佹柊澧炰唬鐞嗙瓑绾с?佸墠绔〉闈€?佷唬鐞嗚处鍙风瓑绾ф洿鏂板叆鍙ｆ垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤浠ｇ悊鍟嗘ā鍧椼?佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??
## 287. 2026-07-09 鍚庡彴浠ｇ悊绛夌骇杩斾剑鏁板?间弗鏍兼牎楠岄棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `AgentLevelController::normalizePayload` 琛ラ綈浠ｇ悊绛夌骇杩斾剑鏁板?煎瓧娈典弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/createAgentLevel` 鏀跺埌 `max_commission=50abc` 鏃惰繑鍥? `ResponseCode::VALIDATION_FAILED`锛屼笉鍒涘缓浠ｇ悊绛夌骇銆?
- 楠岃瘉 `/api/admin/updateAgentLevel2/{id}` 鏀跺埌鏃у吋瀹瑰瓧娈? `commission_rate=30abc` 鏃惰繑鍥? `ResponseCode::VALIDATION_FAILED`锛屼笉鏀瑰啓 `agent_levels.user_commission`銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminAgentLevelCommissionValidationClosureModuleTest.php`
  - 鏂板鍒涘缓鏃堕潪娉? `max_commission` 琚嫆缁濅笖涓嶈惤搴撶殑鏍蜂緥銆?
  - 鏂板缂栬緫鏃堕潪娉曟棫 `commission_rate` 琚嫆缁濅笖涓嶆敼鍐欒繑浣ｅ瓧娈电殑鏍蜂緥銆?
- `app/Http/Controllers/Admin/AgentLevelController.php`
  - `normalizePayload` 淇濈暀鍘熷璇锋眰鍊间氦缁? Validator 鏍￠獙锛岄伩鍏嶆牎楠屽墠寮鸿浆鍚炴帀闈炴硶鍚庣紑銆?
  - 鏂板 `castAgentLevelPayload`锛屼粎鍦ㄦ牎楠岄?氳繃鍚庢妸 `level_code`銆乣max_commission`銆乣min_commission`銆乣user_commission` 杞负鏁存暟鍐欏叆銆?

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAgentLevelCommissionValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓垱寤鸿繑鍥? `ResponseCode::CREATED`銆佺紪杈戣繑鍥? `ResponseCode::UPDATED`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 287 鑺傘??
- GREEN锛氳皟鏁存牎楠屽墠鍚庢暟鍊煎鐞嗛『搴忓苟琛ラ綈绗? 287 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminAgentLevelCommissionValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 涓? `agent_levels` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬併?乣/api/admin/createAgentLevel` 鍜? `/api/admin/updateAgentLevel2/{id}` 涓や釜鍏ュ彛銆?
- 闈炰弗鏍兼暟鍊煎瓧绗︿覆 `50abc` 涓? `30abc` 鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`銆?
- 鍘熶唬鐞嗙瓑绾у悕绉般?佹渶澶ц繑浣ｃ?佹渶灏忚繑浣ｅ拰 `agent_levels.user_commission` 淇濇寔鍘熷?硷紝閬垮厤杩斾剑閰嶇疆鍦ㄥ弬鏁版牎楠屽墠琚? PHP 寮鸿浆鎮勬倓钀藉簱銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠ｇ悊绛夌骇璺敱 ID 鏍￠獙銆佸垪琛ㄣ?佸垹闄ゃ?佸墠绔〉闈€?佷唬鐞嗚处鍙风瓑绾ф洿鏂板叆鍙ｆ垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤浠ｇ悊鍟嗘ā鍧椼?佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??
## 288. 2026-07-09 鍚庡彴缁勫埆閰嶇疆璺敱 ID 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `GroupConfigController::update` 鍜? `GroupConfigController::destroy` 琛ラ綈缁勫埆閰嶇疆璺敱 `{id}` 涓ユ牸鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/updateGroupConfig/{id}` 涓嶈兘鎶? `id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鍓嶇紑鏁板瓧鍖归厤鐪熷疄缁勫埆閰嶇疆銆?
- 楠岃瘉 `/api/admin/deleteGroupConfig/{id}` 鏀跺埌闈炰弗鏍? ID 鏃惰繑鍥? `ResponseCode::VALIDATION_FAILED`锛屼笉鍒犻櫎 `group_configs.id` 鍛戒腑鐨勮褰曘??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminGroupConfigRouteIdValidationClosureModuleTest.php`
  - 鏂板缂栬緫銆佸垹闄や袱涓棫璺敱闈炰弗鏍? `{id}` 琚嫆缁濅笖涓嶈惤搴撶殑鏍蜂緥銆?
- `app/Http/Controllers/Admin/GroupConfigController.php`
  - 鏂板缁勫埆閰嶇疆璺敱 ID 鍏辩敤鏍￠獙銆?
  - `update`銆乣destroy` 鍦ㄦ煡璇? `group_configs.id` 鍓嶅厛鏍￠獙璺敱鍙傛暟锛岄?氳繃鍚庡啀杞崲涓烘暣鏁般??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminGroupConfigRouteIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓紪杈戣繑鍥? `ResponseCode::UPDATED`銆佸垹闄よ繑鍥? `ResponseCode::DELETED`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 288 鑺傘??
- GREEN锛氳ˉ榻愬叡鐢ㄨ矾鐢? ID 鏍￠獙銆佹暣鏁板寲鏌ヨ鍜岀 288 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminGroupConfigRouteIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 涓? `group_configs` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬併?乣/api/admin/updateGroupConfig/{id}` 鍜? `/api/admin/deleteGroupConfig/{id}` 涓や釜鍏ュ彛銆?
- 闈炰弗鏍艰矾鐢? ID `鐪熷疄IDabc` 鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`銆?
- 鍘熺粍鍒悕绉般?佸熀鏁般?佸垎绫汇?佸紑鍏冲瓧娈靛拰 `deleted_at` 淇濇寔鍘熷?硷紝閬垮厤 `group_configs.id` 鍦ㄥ弬鏁版牎楠屽墠琚暟鎹簱鏁板瓧鍓嶇紑瑙勫垯鍛戒腑鐪熷疄缁勫埆閰嶇疆銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩缁勫埆閰嶇疆鍒楄〃銆佹柊澧炵粍鍒?佸墠绔〉闈€?佸鎴疯浆缁勪笟鍔℃垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤浠ｇ悊鍟嗘ā鍧椼?佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 289. 2026-07-09 鍚庡彴缁勫埆閰嶇疆鏁板?煎瓧娈典弗鏍兼牎楠岄棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `GroupConfigController::normalizePayload` 琛ラ綈缁勫埆閰嶇疆鏁板?煎瓧娈典弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/createGroupConfig` 鏀跺埌 `radix=50abc` 鏃惰繑鍥? `ResponseCode::VALIDATION_FAILED`锛屼笉鍒涘缓 `group_configs`銆?
- 楠岃瘉 `/api/admin/updateGroupConfig/{id}` 鏀跺埌 `category=1abc` 鏃惰繑鍥? `ResponseCode::VALIDATION_FAILED`锛屼笉鏀瑰啓鍘熺粍鍒厤缃??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminGroupConfigValueValidationClosureModuleTest.php`
  - 鏂板鍒涘缓鏃堕潪娉? `radix` 琚嫆缁濅笖涓嶅啓鍏? `group_configs.radix` 鐨勬牱渚嬨??
  - 鏂板缂栬緫鏃堕潪娉? `category` 琚嫆缁濅笖涓嶆敼鍐欑粍鍒瓧娈电殑鏍蜂緥銆?
- `app/Http/Controllers/Admin/GroupConfigController.php`
  - `normalizePayload` 淇濈暀 `radix` 涓? `category` 鍘熷璇锋眰鍊间氦缁? Validator 鏍￠獙銆?
  - 鏂板鏍￠獙閫氳繃鍚庣殑鍐欏簱寮鸿浆锛岄伩鍏嶆牎楠屽墠 PHP 寮鸿浆鍚炴帀闈炴硶鍚庣紑銆?

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminGroupConfigValueValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽垱寤哄懡涓垚鍔熺爜锛岀紪杈戝懡涓湇鍔＄閿欒锛屾渶缁堟竻鍗曚篃缂哄皯绗? 289 鑺傘??
- GREEN锛氳皟鏁存牎楠屽墠鍚庢暟鍊煎鐞嗛『搴忓苟琛ラ綈绗? 289 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminGroupConfigValueValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 涓? `group_configs` 璁板綍銆佸悗鍙? admin guard 鐧诲綍鎬併?乣/api/admin/createGroupConfig` 鍜? `/api/admin/updateGroupConfig/{id}` 涓や釜鍏ュ彛銆?
- 闈炰弗鏍兼暟鍊煎瓧绗︿覆 `50abc` 涓? `1abc` 鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`銆?
- 鍘熺粍鍒悕绉般?乣group_configs.radix`銆乣category` 鍜屽紑鍏冲瓧娈典繚鎸佸師鍊硷紝閬垮厤缁勫埆閰嶇疆鍦ㄥ弬鏁版牎楠屽墠琚? PHP 寮鸿浆鎮勬倓钀藉簱銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩缁勫埆閰嶇疆璺敱 ID 鏍￠獙銆佸垪琛ㄣ?佸垹闄ゃ?佸墠绔〉闈€?佸鎴疯浆缁勪笟鍔℃垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤浠ｇ悊鍟嗘ā鍧椼?佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 290. 2026-07-09 鍚庡彴鏂伴椈鍏憡璺敱 ID 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `NewsController::update`銆乣NewsController::destroy` 鍜? `NewsController::togglePublish` 琛ラ綈鏂伴椈鍏憡璺敱 `{id}` 涓ユ牸鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/updateNews/{id}` 涓嶈兘鎶? `id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鍓嶇紑鏁板瓧鍖归厤鐪熷疄鏂伴椈鍏憡銆?
- 楠岃瘉 `/api/admin/deleteNews/{id}` 鍜? `/api/admin/toggleNews/{id}` 鏀跺埌闈炰弗鏍? ID 鏃惰繑鍥? `ResponseCode::VALIDATION_FAILED`锛屼笉鍒犻櫎鍏憡涔熶笉鍒囨崲鍙戝竷鐘舵?併??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminNewsRouteIdValidationClosureModuleTest.php`
  - 鏂板缂栬緫銆佸垹闄ゃ?佸彂甯冨垏鎹笁涓棫璺敱闈炰弗鏍? `{id}` 琚嫆缁濅笖涓嶈惤搴撶殑鏍蜂緥銆?
- `app/Http/Controllers/Admin/NewsController.php`
  - 鏂板鏂伴椈鍏憡璺敱 ID 鍏辩敤鏍￠獙銆?
  - `update`銆乣destroy`銆乣togglePublish` 鍦ㄦ煡璇? `news.id` 鍓嶅厛鏍￠獙璺敱鍙傛暟锛岄?氳繃鍚庡啀杞崲涓烘暣鏁般??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminNewsRouteIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓紪杈戣繑鍥? `ResponseCode::UPDATED`銆佸垹闄よ繑鍥? `ResponseCode::DELETED`銆佸彂甯冨垏鎹㈣繑鍥炴垚鍔燂紝鏈?缁堟竻鍗曚篃缂哄皯绗? 290 鑺傘??
- GREEN锛氳ˉ榻愬叡鐢ㄨ矾鐢? ID 鏍￠獙銆佹暣鏁板寲鏌ヨ鍜岀 290 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminNewsRouteIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 涓? `news` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬併?乣/api/admin/updateNews/{id}`銆乣/api/admin/deleteNews/{id}` 鍜? `/api/admin/toggleNews/{id}` 涓変釜鍏ュ彛銆?
- 闈炰弗鏍艰矾鐢? ID `鐪熷疄IDabc` 鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`銆?
- 鍘熸柊闂绘爣棰樸?佹鏂囥?佸浘鐗囥?佷綔鑰呫?乣is_published` 鍜? `deleted_at` 淇濇寔鍘熷?硷紝閬垮厤 `news.id` 鍦ㄥ弬鏁版牎楠屽墠琚暟鎹簱鏁板瓧鍓嶇紑瑙勫垯鍛戒腑鐪熷疄鏂伴椈鍏憡銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鏂伴椈鍏憡鍒楄〃銆佹柊澧炲叕鍛娿?佸墠绔〉闈€?佸墠鍙版柊闂昏鍙栨垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴鍐呭妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 291. 2026-07-09 鍚庡彴鏀粯閫氶亾璺敱 ID 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `PaymentChannelController::update`銆乣PaymentChannelController::destroy` 鍜? `PaymentChannelController::toggleEnable` 琛ラ綈鏀粯閫氶亾璺敱 `{id}` 涓ユ牸鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/updateChannel/{id}` 涓嶈兘鎶? `id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鍓嶇紑鏁板瓧鍖归厤鐪熷疄鏀粯閫氶亾銆?
- 楠岃瘉 `/api/admin/deleteChannel/{id}` 鍜? `/api/admin/toggleChannel/{id}` 鏀跺埌闈炰弗鏍? ID 鏃惰繑鍥? `ResponseCode::VALIDATION_FAILED`锛屼笉鍒犻櫎閫氶亾涔熶笉鍒囨崲鍚敤鐘舵?併??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminPaymentChannelRouteIdValidationClosureModuleTest.php`
  - 鏂板缂栬緫銆佸垹闄ゃ?佸惎鍋滀笁涓棫璺敱闈炰弗鏍? `{id}` 琚嫆缁濅笖涓嶈惤搴撶殑鏍蜂緥銆?
- `app/Http/Controllers/Admin/PaymentChannelController.php`
  - 鏂板鏀粯閫氶亾璺敱 ID 鍏辩敤鏍￠獙銆?
  - `update`銆乣destroy`銆乣toggleEnable` 鍦ㄦ煡璇? `payment_channels.id` 鍓嶅厛鏍￠獙璺敱鍙傛暟锛岄?氳繃鍚庡啀杞崲涓烘暣鏁般??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminPaymentChannelRouteIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓紪杈戣繑鍥? `ResponseCode::UPDATED`銆佸垹闄よ繑鍥? `ResponseCode::DELETED`銆佸惎鍋滆繑鍥炴垚鍔燂紝鏈?缁堟竻鍗曚篃缂哄皯绗? 291 鑺傘??
- GREEN锛氳ˉ榻愬叡鐢ㄨ矾鐢? ID 鏍￠獙銆佹暣鏁板寲鏌ヨ鍜岀 291 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminPaymentChannelRouteIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 涓? `payment_channels` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬併?乣/api/admin/updateChannel/{id}`銆乣/api/admin/deleteChannel/{id}` 鍜? `/api/admin/toggleChannel/{id}` 涓変釜鍏ュ彛銆?
- 闈炰弗鏍艰矾鐢? ID `鐪熷疄IDabc` 鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`銆?
- 鍘熼?氶亾鍚嶇О銆佺紪鐮併?佹眹鐜囥?乣is_enabled`銆佹帓搴忋?侀厤缃拰 `deleted_at` 淇濇寔鍘熷?硷紝閬垮厤 `payment_channels.id` 鍦ㄥ弬鏁版牎楠屽墠琚暟鎹簱鏁板瓧鍓嶇紑瑙勫垯鍛戒腑鐪熷疄鏀粯閫氶亾銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鏀粯閫氶亾鍒楄〃銆佹柊澧為?氶亾銆佸墠绔〉闈€?佸墠鍙板叆閲戦?氶亾灞曠ず鎴栨暟鎹簱缁撴瀯銆?
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴鍐呭妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 292. 2026-07-09 鍚庡彴鍑瘉瀹℃牳璺敱 ID 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `VoucherController::approve` 鍜? `VoucherController::reject` 琛ラ綈鍑瘉瀹℃牳璺敱 `{id}` 涓ユ牸鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/voucherApprove/{id}` 涓嶈兘鎶? `id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鍓嶇紑鏁板瓧鍖归厤鐪熷疄鍑瘉銆?
- 楠岃瘉 `/api/admin/voucherReject/{id}` 鏀跺埌闈炰弗鏍? ID 鏃惰繑鍥? `ResponseCode::VALIDATION_FAILED`锛屼笉鏀瑰啓鍑瘉瀹℃牳鐘舵?佸拰鎷掔粷鍘熷洜銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminVoucherRouteIdValidationClosureModuleTest.php`
  - 鏂板瀹℃牳閫氳繃銆佸鏍告嫆缁濅袱涓棫璺敱闈炰弗鏍? `{id}` 琚嫆缁濅笖涓嶈惤搴撶殑鏍蜂緥銆?
- `app/Http/Controllers/Admin/VoucherController.php`
  - 鏂板鍑瘉瀹℃牳璺敱 ID 鍏辩敤鏍￠獙銆?
  - `approve`銆乣reject` 鍦ㄦ煡璇? `voucher_infos.id` 鍓嶅厛鏍￠獙璺敱鍙傛暟锛岄?氳繃鍚庡啀杞崲涓烘暣鏁般??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminVoucherRouteIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓?氳繃鍜屾嫆缁濇帴鍙ｅ潎杩斿洖鎴愬姛锛屾渶缁堟竻鍗曚篃缂哄皯绗? 292 鑺傘??
- GREEN锛氳ˉ榻愬叡鐢ㄨ矾鐢? ID 鏍￠獙銆佹暣鏁板寲鏌ヨ鍜岀 292 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminVoucherRouteIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 涓? `voucher_infos` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬併?乣/api/admin/voucherApprove/{id}` 鍜? `/api/admin/voucherReject/{id}` 涓や釜鍏ュ彛銆?
- 闈炰弗鏍艰矾鐢? ID `鐪熷疄IDabc` 鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`銆?
- 鍘? `voucher_infos.review_status`銆乣review_message`銆佸娉ㄥ拰 `deleted_at` 淇濇寔鍘熷?硷紝閬垮厤 `voucher_infos.id` 鍦ㄥ弬鏁版牎楠屽墠琚暟鎹簱鏁板瓧鍓嶇紑瑙勫垯鍛戒腑鐪熷疄鍑瘉銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鍑瘉鍒楄〃銆佸墠绔〉闈€?佸墠鍙板嚟璇佷笂浼犳垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴鍐呭妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 293. 2026-07-09 鍚庡彴榛戝悕鍗曡矾鐢? ID 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `BlacklistController::update` 鍜? `BlacklistController::destroy` 琛ラ綈榛戝悕鍗曡矾鐢? `{id}` 涓ユ牸鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/updateBlacklist/{id}` 涓嶈兘鎶? `id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鍓嶇紑鏁板瓧鍖归厤鐪熷疄榛戝悕鍗曡褰曘??
- 楠岃瘉 `/api/admin/deleteBlacklist/{id}` 鏀跺埌闈炰弗鏍? ID 鏃惰繑鍥? `ResponseCode::VALIDATION_FAILED`锛屼笉鍒犻櫎榛戝悕鍗曡褰曘??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminBlacklistRouteIdValidationClosureModuleTest.php`
  - 鏂板缂栬緫銆佸垹闄や袱涓棫璺敱闈炰弗鏍? `{id}` 琚嫆缁濅笖涓嶈惤搴撶殑鏍蜂緥銆?
- `app/Http/Controllers/Admin/BlacklistController.php`
  - 鏂板榛戝悕鍗曡矾鐢? ID 鍏辩敤鏍￠獙銆?
  - `update`銆乣destroy` 鍦ㄦ煡璇? `blacklists.id` 鍓嶅厛鏍￠獙璺敱鍙傛暟锛岄?氳繃鍚庡啀杞崲涓烘暣鏁般??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminBlacklistRouteIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓紪杈戣繑鍥? `ResponseCode::UPDATED`銆佸垹闄よ繑鍥? `ResponseCode::DELETED`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 293 鑺傘??
- GREEN锛氳ˉ榻愬叡鐢ㄨ矾鐢? ID 鏍￠獙銆佹暣鏁板寲鏌ヨ鍜岀 293 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminBlacklistRouteIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 涓? `blacklists` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬併?乣/api/admin/updateBlacklist/{id}` 鍜? `/api/admin/deleteBlacklist/{id}` 涓や釜鍏ュ彛銆?
- 闈炰弗鏍艰矾鐢? ID `鐪熷疄IDabc` 鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`銆?
- 鍘熼粦鍚嶅崟濮撳悕銆佽瘉浠跺彿銆侀偖绠便?佹墜鏈哄彿鍜? `deleted_at` 淇濇寔鍘熷?硷紝閬垮厤 `blacklists.id` 鍦ㄥ弬鏁版牎楠屽墠琚暟鎹簱鏁板瓧鍓嶇紑瑙勫垯鍛戒腑鐪熷疄榛戝悕鍗曡褰曘??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩榛戝悕鍗曞垪琛ㄣ?佹柊澧為粦鍚嶅崟銆佸墠绔〉闈€?佹敞鍐岄鎺у紩鐢ㄦ垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴鍐呭妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 294. 2026-07-09 鍚庡彴娉ㄩ攢鐢宠瀹℃牳璺敱 ID 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `CancelApplyController::approve` 鍜? `CancelApplyController::reject` 琛ラ綈娉ㄩ攢鐢宠瀹℃牳璺敱 `{id}` 涓ユ牸鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/cancelApplyApprove/{id}` 涓嶈兘鎶? `id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鍓嶇紑鏁板瓧鍖归厤鐪熷疄娉ㄩ攢鐢宠銆?
- 楠岃瘉 `/api/admin/cancelApplyReject/{id}` 鏀跺埌闈炰弗鏍? ID 鏃惰繑鍥? `ResponseCode::VALIDATION_FAILED`锛屼笉鏀瑰啓鐢宠鐘舵?併?佺敤鎴锋敞閿?鐘舵?併?佺敤鎴疯蒋鍒犵姸鎬佹垨鎿嶄綔鏃ュ織銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminCancelApplyRouteIdValidationClosureModuleTest.php`
  - 鏂板瀹℃牳閫氳繃銆佸鏍告嫆缁濅袱涓棫璺敱闈炰弗鏍? `{id}` 琚嫆缁濅笖涓嶈Е鍙戝壇浣滅敤鐨勬牱渚嬨??
- `app/Http/Controllers/Admin/CancelApplyController.php`
  - 鏂板娉ㄩ攢鐢宠璺敱 ID 鍏辩敤鏍￠獙銆?
  - `approve`銆乣reject` 鍦ㄦ煡璇? `cancel_applies.id` 鍓嶅厛鏍￠獙璺敱鍙傛暟锛岄?氳繃鍚庡啀杞崲涓烘暣鏁般??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminCancelApplyRouteIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓?氳繃鍜屾嫆缁濇帴鍙ｅ潎杩斿洖鎴愬姛锛屾渶缁堟竻鍗曚篃缂哄皯绗? 294 鑺傘??
- GREEN锛氳ˉ榻愬叡鐢ㄨ矾鐢? ID 鏍￠獙銆佹暣鏁板寲鏌ヨ鍜岀 294 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminCancelApplyRouteIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆乣cancel_applies`銆乣user_logins`銆乣user_infos` 鍜? `operation_logs` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬併?乣/api/admin/cancelApplyApprove/{id}` 鍜? `/api/admin/cancelApplyReject/{id}` 涓や釜鍏ュ彛銆?
- 闈炰弗鏍艰矾鐢? ID `鐪熷疄IDabc` 鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`銆?
- 鍘熺敵璇风姸鎬併?佹嫆缁濆師鍥犮?乣user_logins.is_cancelled`銆佺敤鎴疯祫鏂? `deleted_at` 鍜? `operation_logs` 淇濇寔鍘熷?硷紝閬垮厤 `cancel_applies.id` 鍦ㄥ弬鏁版牎楠屽墠琚暟鎹簱鏁板瓧鍓嶇紑瑙勫垯鍛戒腑鐪熷疄娉ㄩ攢鐢宠骞惰Е鍙戝鏍稿壇浣滅敤銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩娉ㄩ攢鐢宠鍒楄〃銆佸墠绔〉闈€?佸墠鍙版敞閿?鐢宠鎻愪氦鎴栨暟鎹簱缁撴瀯銆?
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴鍐呭妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??
## 295. 2026-07-09 鍚庡彴鎵归噺瀵煎叆閲嶈瘯璺敱 ID 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `BatchAmountImportController::retryDepositImport`銆乣BatchAmountImportController::retryWithdrawImport` 鍜? `BatchCreditImportController::retryCreditImport` 琛ラ綈鎵归噺瀵煎叆澶辫触閲嶈瘯璺敱 `{id}` 涓ユ牸鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/retryDepositImport/{id}`銆乣/api/admin/retryWithdrawImport/{id}` 鍜? `/api/admin/retryCreditImport/{id}` 涓嶈兘鎶? `id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鍓嶇紑鏁板瓧鍖归厤鐪熷疄澶辫触瀵煎叆璁板綍銆?
- 楠岃瘉闈炰弗鏍艰矾鐢? ID 杩斿洖 `ResponseCode::VALIDATION_FAILED`锛屼笖涓嶉噸缃鍏ヨ褰曠殑鍚屾鐘舵?併?佸け璐ュ師鍥犳垨鏇存柊浜恒??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminBatchImportRetryRouteIdValidationClosureModuleTest.php`
  - 鏂板鍏ラ噾銆佸嚭閲戙?佷俊鐢ㄤ笁绫婚噸璇曞叆鍙ｉ潪涓ユ牸 `{id}` 琚嫆缁濅笖涓嶈惤搴撶殑鏍蜂緥銆?
- `app/Http/Controllers/Admin/BatchAmountImportController.php`
  - `retryDepositImport`銆乣retryWithdrawImport` 鍦ㄦ煡璇? `deposit_imports.id` 涓? `withdraw_imports.id` 鍓嶅厛鏍￠獙璺敱鍙傛暟锛岄?氳繃鍚庡啀杞崲涓烘暣鏁般??
- `app/Http/Controllers/Admin/BatchCreditImportController.php`
  - `retryCreditImport` 鍦ㄦ煡璇? `credit_imports.id` 鍓嶅厛鏍￠獙璺敱鍙傛暟锛岄?氳繃鍚庡啀杞崲涓烘暣鏁般??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminBatchImportRetryRouteIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓叆閲戙?佸嚭閲戙?佷俊鐢ㄤ笁绫婚噸璇曟帴鍙ｅ潎杩斿洖鎴愬姛锛屾渶缁堟竻鍗曚篃缂哄皯绗? 295 鑺傘??
- GREEN锛氳ˉ榻愰噸璇曞叆鍙ｈ矾鐢? ID 鍓嶇疆鏍￠獙鍜岀 295 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminBatchImportRetryRouteIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆乣user_infos`銆乣deposit_imports`銆乣withdraw_imports` 鍜? `credit_imports` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬併?佷笁涓け璐ラ噸璇曞叆鍙ｃ??
- 闈炰弗鏍艰矾鐢? ID `鐪熷疄IDabc` 鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`銆?
- 鍘? `is_synced`銆乣fail_reason` 鍜? `updated_by` 淇濇寔鍘熷?硷紝閬垮厤 `deposit_imports.id`銆乣withdraw_imports.id` 鎴? `credit_imports.id` 鍦ㄥ弬鏁版牎楠屽墠琚暟鎹簱鏁板瓧鍓嶇紑瑙勫垯鍛戒腑鐪熷疄澶辫触璁板綍骞堕噸缃负寰呭悓姝ャ??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩瀵煎叆鍒楄〃銆佹柊澧炲鍏ャ?丆SV 妯℃澘銆佹枃浠惰В鏋愩?丮T4 鍚屾銆佸墠绔〉闈㈡垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佸唴瀹规ā鍧椼?佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??
## 296. 2026-07-09 鍚庡彴椋庢帶寮哄钩璺敱 ID 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `RiskController::forceClose` 琛ラ綈椋庢帶寮哄钩璺敱 `{id}` 涓ユ牸鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/riskForceClose/{id}` 涓嶈兘鎶? `id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鍓嶇紑鏁板瓧鍖归厤鐪熷疄 MT4 浜ゆ槗璁板綍銆?
- 楠岃瘉闈炰弗鏍艰矾鐢? ID 杩斿洖 `ResponseCode::VALIDATION_FAILED`锛屼笖涓嶈繑鍥炵洰鏍囪鍗曠殑寮哄钩淇″彿鏁版嵁銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminRiskForceCloseRouteIdValidationClosureModuleTest.php`
  - 鏂板椋庢帶寮哄钩鍏ュ彛闈炰弗鏍? `{id}` 琚嫆缁濅笖涓嶈繑鍥? `ticket/login` 淇″彿鏁版嵁鐨勬牱渚嬨??
- `app/Http/Controllers/Admin/RiskController.php`
  - `forceClose` 鍦ㄦ煡璇? `mt4_trades.id` 鍓嶅厛鏍￠獙璺敱鍙傛暟锛岄?氳繃鍚庡啀杞崲涓烘暣鏁般??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminRiskForceCloseRouteIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓己骞虫帴鍙ｈ繑鍥炴垚鍔熺爜骞惰繑鍥炵湡瀹炶鍗曚俊鍙凤紝鏈?缁堟竻鍗曚篃缂哄皯绗? 296 鑺傘??
- GREEN锛氳ˉ榻愰鎺у己骞宠矾鐢? ID 鍓嶇疆鏍￠獙鍜岀 296 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminRiskForceCloseRouteIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 涓? `mt4_trades` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佸拰 `/api/admin/riskForceClose/{id}` 鍏ュ彛銆?
- 闈炰弗鏍艰矾鐢? ID `鐪熷疄IDabc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍝嶅簲涓嶅寘鍚洰鏍囪鍗? `ticket/login`锛岄伩鍏? `mt4_trades.id` 鍦ㄥ弬鏁版牎楠屽墠琚暟鎹簱鏁板瓧鍓嶇紑瑙勫垯鍛戒腑鐪熷疄鏈钩浠撹鍗曞苟杩斿洖寮哄钩淇″彿銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩椋庢帶鎸佷粨鍒楄〃銆佽拷淇濋璀︺?佸紓甯? IP銆佸墠绔〉闈€?丮T4 寮哄钩鎵ц閾捐矾鎴栨暟鎹簱缁撴瀯銆?
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴椋庢帶妯″潡銆佽祫閲戞ā鍧椼?佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??
## 297. 2026-07-09 鍚庡彴鏉冪泭姹囨?绘墜鍔ㄧ‘璁よ矾鐢? ID 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `RightsSummaryController::manualConfirmRightsSettlement` 琛ラ綈鏉冪泭缁撶畻鎵嬪姩纭璺敱 `{id}` 涓ユ牸鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/manualConfirmRightsSettlement/{id}` 涓嶈兘鎶? `id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鍓嶇紑鏁板瓧鍖归厤鐪熷疄鏉冪泭缁撶畻璁板綍銆?
- 楠岃瘉闈炰弗鏍艰矾鐢? ID 杩斿洖 `ResponseCode::VALIDATION_FAILED`锛屼笖涓嶆妸寰呭鐞嗙粨绠楄褰曚汉宸ョ‘璁や负宸插鐞嗐??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminRightsSummaryManualConfirmRouteIdValidationClosureModuleTest.php`
  - 鏂板鎵嬪姩纭鍏ュ彛闈炰弗鏍? `{id}` 琚嫆缁濅笖涓嶆敼鍐欑粨绠楃姸鎬佸拰澶囨敞鐨勬牱渚嬨??
- `app/Http/Controllers/Admin/RightsSummaryController.php`
  - `manualConfirmRightsSettlement` 鍦ㄦ煡璇? `rights_settlements.id` 鍓嶅厛鏍￠獙璺敱鍙傛暟锛岄?氳繃鍚庡啀杞崲涓烘暣鏁般??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminRightsSummaryManualConfirmRouteIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓墜鍔ㄧ‘璁ゆ帴鍙ｈ繑鍥? `ResponseCode::UPDATED` 骞舵敼鍐欑湡瀹炵粨绠楄褰曪紝鏈?缁堟竻鍗曚篃缂哄皯绗? 297 鑺傘??
- GREEN锛氳ˉ榻愭墜鍔ㄧ‘璁よ矾鐢? ID 鍓嶇疆鏍￠獙鍜岀 297 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminRightsSummaryManualConfirmRouteIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆乣user_infos` 涓? `rights_settlements` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佸拰 `/api/admin/manualConfirmRightsSettlement/{id}` 鍏ュ彛銆?
- 闈炰弗鏍艰矾鐢? ID `鐪熷疄IDabc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍘? `rights_settlements.status` 涓? `remark` 淇濇寔鍘熷?硷紝閬垮厤 `rights_settlements.id` 鍦ㄥ弬鏁版牎楠屽墠琚暟鎹簱鏁板瓧鍓嶇紑瑙勫垯鍛戒腑鐪熷疄寰呭鐞嗙粨绠楀苟鎵ц浜哄伐纭銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鏉冪泭姹囨?诲垪琛ㄣ?佸鍑恒?丮T4 鑷姩纭銆佸墠绔〉闈€?佹暟鎹寖鍥存湇鍔℃垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??
## 298. 2026-07-09 鍚庡彴鏁版嵁鑼冨洿浠ｇ悊缁戝畾鍒犻櫎 ID 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `DataScopeController::deleteAdminAgentBinding` 琛ラ綈绠＄悊鍛樹唬鐞嗙粦瀹氬垹闄よ姹備綋 `id` 涓ユ牸鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/deleteAdminAgentBinding` 涓嶈兘鎶? `id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鍓嶇紑鏁板瓧鍖归厤鐪熷疄 `admin_agent_bindings` 璁板綍銆?
- 楠岃瘉闈炰弗鏍? ID 杩斿洖 `ResponseCode::VALIDATION_FAILED`锛屼笖涓嶅垹闄ょ鐞嗗憳浠ｇ悊缁戝畾銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminDataScopeBindingDeleteIdValidationClosureModuleTest.php`
  - 鏂板鍒犻櫎绠＄悊鍛樹唬鐞嗙粦瀹氭椂闈炰弗鏍? `id` 琚嫆缁濅笖涓嶈蒋鍒犻櫎缁戝畾璁板綍鐨勬牱渚嬨??
- `app/Http/Controllers/Admin/DataScopeController.php`
  - `deleteAdminAgentBinding` 鍦ㄦ煡璇? `admin_agent_bindings.id` 鍓嶅厛鏍￠獙璇锋眰浣? `id`锛岄?氳繃鍚庡啀杞崲涓烘暣鏁般??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminDataScopeBindingDeleteIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓垹闄ゆ帴鍙ｈ繑鍥? `ResponseCode::DELETED` 骞跺垹闄ょ湡瀹炵粦瀹氳褰曪紝鏈?缁堟竻鍗曚篃缂哄皯绗? 298 鑺傘??
- GREEN锛氳ˉ榻愬垹闄ょ粦瀹? ID 鍓嶇疆鏍￠獙鍜岀 298 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminDataScopeBindingDeleteIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆乣user_infos` 涓? `admin_agent_bindings` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佸拰 `/api/admin/deleteAdminAgentBinding` 鍏ュ彛銆?
- 闈炰弗鏍艰姹備綋 ID `鐪熷疄IDabc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍘? `admin_agent_bindings.status` 涓? `deleted_at` 淇濇寔鍘熷?硷紝閬垮厤 `admin_agent_bindings.id` 鍦ㄥ弬鏁版牎楠屽墠琚暟鎹簱鏁板瓧鍓嶇紑瑙勫垯鍛戒腑鐪熷疄绠＄悊鍛樹唬鐞嗙粦瀹氬苟鍒犻櫎銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩瑙掕壊鏁版嵁鑼冨洿淇濆瓨銆佺鐞嗗憳浠ｇ悊缁戝畾淇濆瓨銆佹暟鎹寖鍥村垪琛ㄣ?佸墠绔〉闈€?佹潈闄愬瓧鍏告垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴绠＄悊鍛樻ā鍧椼?佷唬鐞嗗晢妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 299. 2026-07-09 鍚庡彴鏁版嵁鑼冨洿浠ｇ悊缁戝畾鍒楄〃 admin_id 绛涢?変弗鏍兼牎楠岄棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `DataScopeController::adminAgentBindingList` 琛ラ綈绠＄悊鍛樹唬鐞嗙粦瀹氬垪琛? `admin_id` 绛涢?変弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/adminAgentBindingList` 涓嶈兘鎶? `admin_id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鏁板瓧鍓嶇紑鍖归厤鐪熷疄绠＄悊鍛樼粦瀹氬垪琛ㄣ??
- 楠岃瘉闈炰弗鏍? `admin_id` 杩斿洖 `ResponseCode::VALIDATION_FAILED`锛岄伩鍏嶆寜 `admin_agent_bindings.admin_id` 鐨勬暟瀛楀墠缂?杩斿洖鐪熷疄绠＄悊鍛樹唬鐞嗙粦瀹氭暟鎹??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminDataScopeBindingListAdminIdValidationClosureModuleTest.php`
  - 鏂板绠＄悊鍛樹唬鐞嗙粦瀹氬垪琛ㄩ潪涓ユ牸 `admin_id` 绛涢?夎鎷掔粷鐨勬牱渚嬨??
- `app/Http/Controllers/Admin/DataScopeController.php`
  - `adminAgentBindingList` 鍦ㄦ嫾鎺? `admin_agent_bindings.admin_id` 鏌ヨ鏉′欢鍓嶅厛鏍￠獙 `admin_id`锛岄?氳繃鍚庡啀杞崲涓烘暣鏁般??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminDataScopeBindingListAdminIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓垪琛ㄦ帴鍙ｈ繑鍥炴垚鍔熺爜骞舵寜鐪熷疄绠＄悊鍛? ID 杩斿洖缁戝畾鍒楄〃锛屾渶缁堟竻鍗曚篃缂哄皯绗? 299 鑺傘??
- GREEN锛氳ˉ榻愬垪琛ㄧ瓫閫? `admin_id` 鍓嶇疆鏍￠獙鍜岀 299 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminDataScopeBindingListAdminIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆乣user_infos` 涓? `admin_agent_bindings` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佸拰 `/api/admin/adminAgentBindingList` 鍏ュ彛銆?
- 闈炰弗鏍肩瓫閫夊?? `admin_id=鐪熷疄IDabc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鏌ヨ涓嶄細钀藉埌 `admin_agent_bindings.admin_id = 鐪熷疄ID` 鐨勫墠缂?鍖归厤缁撴灉锛岄伩鍏嶅垪琛ㄦ帴鍙ｆ硠闇叉垨璇繑鍥炵湡瀹炵鐞嗗憳浠ｇ悊缁戝畾鏁版嵁銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩瑙掕壊鏁版嵁鑼冨洿淇濆瓨銆佺鐞嗗憳浠ｇ悊缁戝畾淇濆瓨銆佺鐞嗗憳浠ｇ悊缁戝畾鍒犻櫎銆佸墠绔〉闈€?佹潈闄愬瓧鍏告垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴绠＄悊鍛樻ā鍧椼?佷唬鐞嗗晢妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 300. 2026-07-09 鍚庡彴瑙掕壊鏁版嵁鑼冨洿 ID 鍒楄〃涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `DataScopeController::saveRoleDataScope` 琛ラ綈瑙掕壊鏁版嵁鑼冨洿淇濆瓨鏃? `agent_ids` 鍜? `user_ids` 鐨勪弗鏍? ID 鍒楄〃鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/saveRoleDataScope` 涓嶈兘鎶? `agent_ids=鐪熷疄IDabc` 鍐欏叆 `role_data_scopes.agent_ids`銆?
- 楠岃瘉 `/api/admin/saveRoleDataScope` 涓嶈兘鎶? `user_ids=鐪熷疄IDabc` 鍐欏叆 `role_data_scopes.user_ids`銆?
- 楠岃瘉闈炰弗鏍? ID 鍒楄〃杩斿洖 `ResponseCode::VALIDATION_FAILED`锛屼笖涓嶆柊澧炴垨瑕嗙洊瑙掕壊鏁版嵁鑼冨洿閰嶇疆銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminDataScopeRoleIdListValidationClosureModuleTest.php`
  - 鏂板浠ｇ悊 ID 鍒楄〃銆佺敤鎴? ID 鍒楄〃涓や釜闈炰弗鏍艰緭鍏ヨ鎷掔粷涓斾笉鍐欏叆 `role_data_scopes` 鐨勬牱渚嬨??
- `app/Http/Controllers/Admin/DataScopeController.php`
  - `saveRoleDataScope` 鍦ㄨВ鏋愬苟淇濆瓨 `agent_ids`銆乣user_ids` 鍓嶅厛閫愰」鏍￠獙姝ｆ暣鏁? ID锛岄伩鍏? `parseIdList` 鎶婃贩鍏ュ瓧姣嶇殑鍊煎己杞垚鏁板瓧鎴栭潤榛樹涪寮冦??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminDataScopeRoleIdListValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓唬鐞? ID 鍒楄〃鍜岀敤鎴? ID 鍒楄〃鍧囪繑鍥? `ResponseCode::UPDATED`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 300 鑺傘??
- GREEN锛氳ˉ榻? ID 鍒楄〃鍓嶇疆鏍￠獙銆佸垪琛ㄨВ鏋愬叡鐢ㄥ綊涓?鍖栨柟娉曞拰绗? 300 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminDataScopeRoleIdListValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆乣roles` 涓? `role_data_scopes` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佸拰 `/api/admin/saveRoleDataScope` 鍏ュ彛銆?
- 闈炰弗鏍煎垪琛ㄥ?? `agent_ids=鐪熷疄IDabc` 涓? `user_ids=鐪熷疄IDabc` 鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`銆?
- `role_data_scopes.agent_ids` 鍜? `role_data_scopes.user_ids` 涓嶄細鍥犱负 PHP 鏁存暟寮鸿浆鍐欏叆鏁板瓧鍓嶇紑缁撴灉锛屼篃涓嶄細闈欓粯鍚炴帀闈炴硶椤瑰悗淇濆瓨涓嶅畬鏁撮厤缃??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩瑙掕壊鏁版嵁鑼冨洿鍒楄〃銆佺鐞嗗憳浠ｇ悊缁戝畾淇濆瓨銆佺鐞嗗憳浠ｇ悊缁戝畾鍒楄〃銆佺鐞嗗憳浠ｇ悊缁戝畾鍒犻櫎銆佸墠绔〉闈€?佹潈闄愬瓧鍏告垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴绠＄悊鍛樻ā鍧椼?佷唬鐞嗗晢妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 301. 2026-07-09 鍚庡彴瑙掕壊璇锋眰浣? ID 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `RoleController::updateRole` 鍜? `RoleController::deleteRole` 琛ラ綈瑙掕壊璇锋眰浣? `id` 涓ユ牸鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/updateRole` 涓嶈兘鎶? `id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鏁板瓧鍓嶇紑鍖归厤鐪熷疄 `roles.id`銆?
- 楠岃瘉 `/api/admin/deleteRole` 鏀跺埌闈炰弗鏍? `id` 鏃惰繑鍥? `ResponseCode::VALIDATION_FAILED`锛屼笉鍒犻櫎鐪熷疄瑙掕壊銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminRoleRequestIdValidationClosureModuleTest.php`
  - 鏂板瑙掕壊鏇存柊銆佸垹闄や袱涓姹備綋闈炰弗鏍? `id` 琚嫆缁濅笖涓嶈惤搴撶殑鏍蜂緥銆?
- `app/Http/Controllers/Admin/RoleController.php`
  - `updateRole` 鍜? `deleteRole` 鍦ㄦ煡璇? `roles.id` 鍓嶅厛鏍￠獙璇锋眰浣撴垨鍏煎璺敱 ID锛岄?氳繃鍚庡啀杞崲涓烘暣鏁般??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminRoleRequestIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓鑹叉洿鏂拌繑鍥? `ResponseCode::UPDATED`銆佽鑹插垹闄よ繑鍥? `ResponseCode::DELETED`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 301 鑺傘??
- GREEN锛氳ˉ榻愯鑹? ID 鍓嶇疆鏍￠獙鍜岀 301 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminRoleRequestIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 涓? `roles` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬併?乣/api/admin/updateRole` 鍜? `/api/admin/deleteRole` 涓や釜鍏ュ彛銆?
- 闈炰弗鏍艰姹備綋 ID `鐪熷疄IDabc` 鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`銆?
- 鍘熻鑹插悕绉般?佽鏄庛?佺姸鎬佸拰 `deleted_at` 淇濇寔鍘熷?硷紝閬垮厤 `roles.id` 鍦ㄥ弬鏁版牎楠屽墠琚暟鎹簱鏁板瓧鍓嶇紑瑙勫垯鍛戒腑鐪熷疄鍚庡彴瑙掕壊銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩瑙掕壊鍒涘缓銆佽鑹叉巿鏉冦?佹潈闄愬瓧鍏搞?佽彍鍗曞瓧鍏搞?佸墠绔〉闈€?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴绠＄悊鍛樻ā鍧椼?佷唬鐞嗗晢妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 302. 2026-07-09 鍚庡彴瑙掕壊鎺堟潈 ID 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `RoleController::assignPermissions` 琛ラ綈瑙掕壊鎺堟潈 `role_id` 鍜? `permissions[]` 涓ユ牸 ID 鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/assignPermissions` 涓嶈兘鎶? `role_id=鐪熷疄IDabc` 鍐欏叆 `role_permissions.role_id`銆?
- 楠岃瘉 `/api/admin/assignPermissions` 涓嶈兘鎶? `permissions[]=鐪熷疄IDabc` 鍐欏叆 `role_permissions.permission_id`銆?
- 楠岃瘉闈炰弗鏍兼潈闄? ID 鍒楄〃杩斿洖 `ResponseCode::VALIDATION_FAILED`锛屼笖涓嶅悓姝ヨ鑹叉潈闄愩??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminRoleAssignPermissionIdValidationClosureModuleTest.php`
  - 鏂板瑙掕壊 ID 鍜屾潈闄? ID 涓ょ被闈炰弗鏍艰緭鍏ヨ鎷掔粷涓斾笉鍐欏叆 `role_permissions` 鐨勬牱渚嬨??
- `app/Http/Controllers/Admin/RoleController.php`
  - `assignPermissions` 鍦ㄦ煡鎵? `roles.id` 鍜屽悓姝? `permissions.id` 鍓嶅厛鏍￠獙鍘熷 ID锛岄伩鍏? `intval` 鎶婃贩鍏ュ瓧姣嶇殑鍊艰浆鎹负鐪熷疄 ID銆?

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminRoleAssignPermissionIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓鑹叉巿鏉冩帴鍙ｄ袱绫婚潪涓ユ牸 ID 鍧囪繑鍥炴垚鍔熺爜锛屾渶缁堟竻鍗曚篃缂哄皯绗? 302 鑺傘??
- GREEN锛氳ˉ榻愯鑹叉巿鏉? ID 鍓嶇疆鏍￠獙鍜岀 302 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminRoleAssignPermissionIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆乣roles`銆乣permissions` 涓? `role_permissions` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佸拰 `/api/admin/assignPermissions` 鍏ュ彛銆?
- 闈炰弗鏍? `role_id=鐪熷疄IDabc` 涓? `permissions[]=鐪熷疄IDabc` 鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`銆?
- `role_permissions.role_id` 鍜? `role_permissions.permission_id` 涓嶄細鍥犱负 `intval` 鏁板瓧鍓嶇紑瑙勫垯鍚屾鍒扮湡瀹炶鑹叉潈闄愬叧绯汇??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩瑙掕壊鍒涘缓銆佽鑹叉洿鏂般?佽鑹插垹闄ゃ?佹潈闄愬瓧鍏搞?佽彍鍗曞瓧鍏搞?佸墠绔〉闈€?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴绠＄悊鍛樻ā鍧椼?佷唬鐞嗗晢妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 303. 2026-07-09 鍚庡彴鏉冮檺瀛楀吀璇锋眰浣? ID 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `PermissionController::updatePermission` 鍜? `PermissionController::deletePermission` 琛ラ綈鏉冮檺瀛楀吀璇锋眰浣? `id` 涓ユ牸鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/updatePermission` 涓嶈兘鎶? `id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鏁板瓧鍓嶇紑鍖归厤鐪熷疄 `permissions.id`銆?
- 楠岃瘉 `/api/admin/deletePermission` 鏀跺埌闈炰弗鏍? `id` 鏃惰繑鍥? `ResponseCode::VALIDATION_FAILED`锛屼笉鍒犻櫎鐪熷疄鏉冮檺瀛楀吀璁板綍銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminPermissionRequestIdValidationClosureModuleTest.php`
  - 鏂板鏉冮檺瀛楀吀鏇存柊銆佸垹闄や袱涓姹備綋闈炰弗鏍? `id` 琚嫆缁濅笖涓嶈惤搴撶殑鏍蜂緥锛屽苟娓呯悊娴嬭瘯涓撶敤鏉冮檺鏁版嵁銆?
- `app/Http/Controllers/Admin/PermissionController.php`
  - `updatePermission` 鍜? `deletePermission` 鍦ㄦ煡璇? `permissions.id` 鍓嶅厛鏍￠獙璇锋眰浣撴垨鍏煎璺敱 ID锛岄?氳繃鍚庡啀杞崲涓烘暣鏁般??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminPermissionRequestIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓潈闄愭洿鏂拌繑鍥? `ResponseCode::UPDATED`銆佹潈闄愬垹闄よ繑鍥? `ResponseCode::DELETED`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 303 鑺傘??
- GREEN锛氳ˉ榻愭潈闄愬瓧鍏? ID 鍓嶇疆鏍￠獙鍜岀 303 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminPermissionRequestIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 涓庢祴璇曚笓鐢? `permissions` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬併?乣/api/admin/updatePermission` 鍜? `/api/admin/deletePermission` 涓や釜鍏ュ彛銆?
- 闈炰弗鏍艰姹備綋 ID `鐪熷疄IDabc` 鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`銆?
- 鍘熸潈闄愬悕绉般?佹潈闄愬瓧绗︿覆銆佺姸鎬佸拰 `deleted_at` 淇濇寔鍘熷?硷紝閬垮厤 `permissions.id` 鍦ㄥ弬鏁版牎楠屽墠琚暟鎹簱鏁板瓧鍓嶇紑瑙勫垯鍛戒腑鐪熷疄鏉冮檺瀛楀吀璁板綍銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鏉冮檺鍒涘缓銆佽鑹叉巿鏉冦?佽彍鍗曞瓧鍏搞?佸墠绔〉闈€?佹潈闄愯縼绉汇?佹潈闄愯鏄庢枃妗ｆ垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴绠＄悊鍛樻ā鍧椼?佷唬鐞嗗晢妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 304. 2026-07-09 鍚庡彴鑿滃崟瀛楀吀璇锋眰浣? ID 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `MenuController::updateMenu` 鍜? `MenuController::deleteMenu` 琛ラ綈鑿滃崟瀛楀吀璇锋眰浣? `id` 涓ユ牸鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/updateMenu` 涓嶈兘鎶? `id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鏁板瓧鍓嶇紑鍖归厤鐪熷疄 `permissions.id`銆?
- 楠岃瘉 `/api/admin/deleteMenu` 鏀跺埌闈炰弗鏍? `id` 鏃惰繑鍥? `ResponseCode::VALIDATION_FAILED`锛屼笉鍒犻櫎鐪熷疄鑿滃崟瀛楀吀璁板綍銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminMenuRequestIdValidationClosureModuleTest.php`
  - 鏂板鑿滃崟瀛楀吀鏇存柊銆佸垹闄や袱涓姹備綋闈炰弗鏍? `id` 琚嫆缁濅笖涓嶈惤搴撶殑鏍蜂緥锛屽苟娓呯悊娴嬭瘯涓撶敤鑿滃崟鏉冮檺鏁版嵁銆?
- `app/Http/Controllers/Admin/MenuController.php`
  - `updateMenu` 鍜? `deleteMenu` 鍦ㄦ煡璇? `permissions.id` 鍓嶅厛鏍￠獙璇锋眰浣? `id`锛岄?氳繃鍚庡啀杞崲涓烘暣鏁般??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminMenuRequestIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓彍鍗曟洿鏂般?佽彍鍗曞垹闄ゅ潎杩斿洖鎴愬姛鐮? `ResponseCode::SUCCESS`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 304 鑺傘??
- GREEN锛氳ˉ榻愯彍鍗曞瓧鍏? ID 鍓嶇疆鏍￠獙鍜岀 304 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminMenuRequestIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 涓庢祴璇曚笓鐢? `permissions` 琛ㄨ彍鍗曡褰曘?佸悗鍙? admin guard 鐧诲綍鎬併?乣/api/admin/updateMenu` 鍜? `/api/admin/deleteMenu` 涓や釜鍏ュ彛銆?
- 闈炰弗鏍艰姹備綋 ID `鐪熷疄IDabc` 鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`銆?
- 鍘熻彍鍗曞悕绉般?乻lug銆侀〉闈㈣矾鐢便?佺姸鎬佸拰 `deleted_at` 淇濇寔鍘熷?硷紝閬垮厤 `permissions.id` 鍦ㄥ弬鏁版牎楠屽墠琚暟鎹簱鏁板瓧鍓嶇紑瑙勫垯鍛戒腑鐪熷疄鑿滃崟瀛楀吀璁板綍銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鑿滃崟鍒涘缓銆佽彍鍗曟爲璇诲彇銆佹潈闄愬瓧鍏搞?佽鑹叉巿鏉冦?佸墠绔〉闈€?佹潈闄愯縼绉汇?佹潈闄愯鏄庢枃妗ｆ垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴绠＄悊鍛樻ā鍧椼?佷唬鐞嗗晢妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 305. 2026-07-09 鍚庡彴鍏ラ噾璇锋眰浣? ID 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `DepositController::show`銆乣DepositController::approve` 鍜? `DepositController::reject` 琛ラ綈鍏ラ噾璇锋眰浣? `id` 涓ユ牸鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/depositDetail` 涓嶈兘鎶? `id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鏁板瓧鍓嶇紑鍖归厤鐪熷疄 `deposit_records.id` 骞惰繑鍥炲叆閲戣鎯呫??
- 楠岃瘉 `/api/admin/depositApprove` 鍜? `/api/admin/depositReject` 鏀跺埌闈炰弗鏍? `id` 鏃惰繑鍥? `ResponseCode::VALIDATION_FAILED`锛屼笉鏀瑰啓鐪熷疄鍏ラ噾瀹℃牳鐘舵?併?佷粯娆炬椂闂存垨椹冲洖澶囨敞銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminDepositRequestIdValidationClosureModuleTest.php`
  - 鏂板鍏ラ噾璇︽儏銆佸鏍搁?氳繃銆佸鏍搁┏鍥炰笁涓姹備綋闈炰弗鏍? `id` 琚嫆缁濅笖涓嶆硠闇叉垨鏀瑰啓 `deposit_records` 鐨勬牱渚嬶紝骞舵竻鐞嗘祴璇曚笓鐢ㄥ叆閲戣褰曘??
- `app/Http/Controllers/Admin/DepositController.php`
  - `show`銆乣approve` 鍜? `reject` 鍦ㄦ煡璇? `deposit_records.id` 鍓嶅厛鏍￠獙璇锋眰浣撴垨鍏煎璺敱 ID锛岄?氳繃鍚庡啀杞崲涓烘暣鏁般??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminDepositRequestIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓叆閲戣鎯呫?佸鏍搁?氳繃銆佸鏍搁┏鍥炲潎杩斿洖鎴愬姛鐮? `ResponseCode::SUCCESS`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 305 鑺傘??
- GREEN锛氳ˉ榻愬叆閲? ID 鍓嶇疆鏍￠獙鍜岀 305 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminDepositRequestIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 涓庢祴璇曚笓鐢? `deposit_records` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬併?乣/api/admin/depositDetail`銆乣/api/admin/depositApprove` 鍜? `/api/admin/depositReject` 涓変釜鍏ュ彛銆?
- 闈炰弗鏍艰姹備綋 ID `鐪熷疄IDabc` 鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`銆?
- 鍘熷叆閲戣褰? `status`銆乣payment_time` 涓? `remarks` 淇濇寔鍘熷?硷紝璇︽儏鍝嶅簲涓嶅寘鍚洰鏍囧叆閲戣鍗曞彿锛岄伩鍏? `deposit_records.id` 鍦ㄥ弬鏁版牎楠屽墠琚暟鎹簱鏁板瓧鍓嶇紑瑙勫垯鍛戒腑鐪熷疄鍏ラ噾璁板綍銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鍏ラ噾鍒楄〃銆佸叆閲戞祦姘淬?佹壒閲忓叆閲戝鍏ャ?佸墠绔〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 306. 2026-07-09 鍚庡彴鍑洪噾璇锋眰浣? ID 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `WithdrawController::process`銆乣WithdrawController::complete` 鍜? `WithdrawController::reject` 琛ラ綈鍑洪噾璇锋眰浣? `id` 涓ユ牸鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/withdrawProcess` 涓嶈兘鎶? `id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鏁板瓧鍓嶇紑鍖归厤鐪熷疄 `withdraw_records.id` 骞舵爣璁板鐞嗕腑銆?
- 楠岃瘉 `/api/admin/withdrawComplete` 涓嶈兘鎶婇潪涓ユ牸 `id` 鏍囪涓哄凡瀹屾垚銆?
- 楠岃瘉 `/api/admin/withdrawReject` 鏀跺埌闈炰弗鏍? `id` 鏃惰繑鍥? `ResponseCode::VALIDATION_FAILED`锛屼笉鏀瑰啓鐪熷疄鍑洪噾璁板綍鐨勭姸鎬佹垨鎷掔粷鍘熷洜銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminWithdrawRequestIdValidationClosureModuleTest.php`
  - 鏂板鍑洪噾澶勭悊涓?佸畬鎴愩?佹嫆缁濅笁涓姹備綋闈炰弗鏍? `id` 琚嫆缁濅笖涓嶈惤搴撶殑鏍蜂緥锛屽苟娓呯悊娴嬭瘯涓撶敤鍑洪噾璁板綍銆?
- `app/Http/Controllers/Admin/WithdrawController.php`
  - `show`銆乣process`銆乣complete` 鍜? `reject` 鍦ㄦ煡璇? `withdraw_records.id` 鍓嶅厛鏍￠獙璇锋眰浣撴垨鍏煎璺敱 ID锛岄?氳繃鍚庡啀杞崲涓烘暣鏁般??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminWithdrawRequestIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓嚭閲戝鐞嗕腑鍜屾嫆缁濆叆鍙ｈ繑鍥炰笟鍔℃垚鍔熺爜銆佸畬鎴愬叆鍙ｈ繑鍥炴暟鎹笉瀛樺湪鐮侊紝鏈?缁堟竻鍗曚篃缂哄皯绗? 306 鑺傘??
- GREEN锛氳ˉ榻愬嚭閲? ID 鍓嶇疆鏍￠獙鍜岀 306 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminWithdrawRequestIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 涓庢祴璇曚笓鐢? `withdraw_records` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬併?乣/api/admin/withdrawProcess`銆乣/api/admin/withdrawComplete` 鍜? `/api/admin/withdrawReject` 涓変釜鍏ュ彛銆?
- 闈炰弗鏍艰姹備綋 ID `鐪熷疄IDabc` 鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`銆?
- 鍘熷嚭閲戣褰? `status` 鍜? `reject_reason` 淇濇寔鍘熷?硷紝閬垮厤 `withdraw_records.id` 鍦ㄥ弬鏁版牎楠屽墠琚暟鎹簱鏁板瓧鍓嶇紑瑙勫垯鍛戒腑鐪熷疄鍑洪噾璁板綍銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鍑洪噾鍒楄〃銆佸嚭閲戞祦姘淬?佹壒閲忓嚭閲戝鍏ャ?佸墠绔〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 307. 2026-07-09 鍚庡彴杩斾剑璇锋眰浣? ID 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `CommissionController::settle` 琛ラ綈杩斾剑缁撶畻璇锋眰浣? `id` 涓ユ牸鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/commissionSettle` 涓嶈兘鎶? `id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鏁板瓧鍓嶇紑鍖归厤鐪熷疄 `commission_records.id` 骞舵爣璁颁负宸茬粨绠椼??
- 鍚屾淇濇姢 `CommissionController::show`锛岄伩鍏嶅悗缁帴鍏ヨ鎯呰矾鐢辨椂鍦? `commission_records.id` 鍙傛暟鏍￠獙鍓嶅懡涓湡瀹炶繑浣ｈ褰曘??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminCommissionRequestIdValidationClosureModuleTest.php`
  - 鏂板鍗曟潯杩斾剑缁撶畻璇锋眰浣撻潪涓ユ牸 `id` 琚嫆缁濅笖涓嶈惤搴撶殑鏍蜂緥锛屽苟娓呯悊娴嬭瘯涓撶敤杩斾剑璁板綍銆?
- `app/Http/Controllers/Admin/CommissionController.php`
  - `show` 鍜? `settle` 鍦ㄦ煡璇? `commission_records.id` 鍓嶅厛鏍￠獙璇锋眰浣撴垨鍏煎璺敱 ID锛岄?氳繃鍚庡啀杞崲涓烘暣鏁般??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminCommissionRequestIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓崟鏉¤繑浣ｇ粨绠楀叆鍙ｈ繑鍥炴垚鍔熺爜 `ResponseCode::SUCCESS` 骞舵妸鐪熷疄璁板綍鏍囪涓哄凡缁撶畻锛屾渶缁堟竻鍗曚篃缂哄皯绗? 307 鑺傘??
- GREEN锛氳ˉ榻愯繑浣? ID 鍓嶇疆鏍￠獙鍜岀 307 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminCommissionRequestIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 涓庢祴璇曚笓鐢? `commission_records` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佸拰 `/api/admin/commissionSettle` 鍏ュ彛銆?
- 闈炰弗鏍艰姹備綋 ID `鐪熷疄IDabc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍘熻繑浣ｈ褰? `settle_status` 淇濇寔鍘熷?硷紝閬垮厤 `commission_records.id` 鍦ㄥ弬鏁版牎楠屽墠琚暟鎹簱鏁板瓧鍓嶇紑瑙勫垯鍛戒腑鐪熷疄杩斾剑缁撶畻璁板綍銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩杩斾剑鍒楄〃銆佽繑浣ｆ壒閲忕粨绠椼?佸疄鏃惰繑浣ｃ?佸墠绔〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 308. 2026-07-09 鍚庡彴杩斾剑鍒楄〃 agent_id 绛涢?変弗鏍兼牎楠岄棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `CommissionController::index` 琛ラ綈杩斾剑鍒楄〃 `agent_id` 绛涢?変弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/commissionList` 涓嶈兘鎶? `agent_id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鏁板瓧鍓嶇紑鍖归厤鐪熷疄 `commission_records.agent_id` 骞惰繑鍥炶繑浣ｈ褰曘??
- 楠岃瘉闈炰弗鏍? `agent_id` 杩斿洖 `ResponseCode::VALIDATION_FAILED`锛岄伩鍏嶅垪琛ㄧ瓫閫夋硠闇茬湡瀹炰唬鐞嗚繑浣ｆ暟鎹??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminCommissionListAgentIdValidationClosureModuleTest.php`
  - 鏂板杩斾剑鍒楄〃闈炰弗鏍? `agent_id` 绛涢?夎鎷掔粷涓斾笉杩斿洖娴嬭瘯杩斾剑璁板綍鐨勬牱渚嬶紝骞舵竻鐞嗘祴璇曚笓鐢ㄨ繑浣ｈ褰曘??
- `app/Http/Controllers/Admin/CommissionController.php`
  - `index` 鍦ㄦ嫾鎺? `commission_records.agent_id` 鏌ヨ鏉′欢鍓嶅厛鏍￠獙 `agent_id`锛岄?氳繃鍚庡啀杞崲涓烘暣鏁般??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminCommissionListAgentIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓繑浣ｅ垪琛ㄦ帴鍙ｈ繑鍥炴垚鍔熺爜骞舵寜鐪熷疄浠ｇ悊 ID 杩斿洖璁板綍锛屾渶缁堟竻鍗曚篃缂哄皯绗? 308 鑺傘??
- GREEN锛氳ˉ榻愯繑浣ｅ垪琛? `agent_id` 鍓嶇疆鏍￠獙鍜岀 308 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminCommissionListAgentIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 涓庢祴璇曚笓鐢? `commission_records` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佸拰 `/api/admin/commissionList` 鍏ュ彛銆?
- 闈炰弗鏍肩瓫閫夊?? `agent_id=鐪熷疄IDabc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍝嶅簲涓嶅寘鍚祴璇曡繑浣ｈ褰? `unique_id`锛岄伩鍏? `commission_records.agent_id` 鍦ㄥ弬鏁版牎楠屽墠琚暟鎹簱鏁板瓧鍓嶇紑瑙勫垯鍛戒腑鐪熷疄浠ｇ悊杩斾剑鏁版嵁銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩杩斾剑缁撶畻銆佽繑浣ｆ壒閲忕粨绠椼?佸疄鏃惰繑浣ｃ?佸墠绔〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 309. 2026-07-09 鍚庡彴鍏ラ噾鍒楄〃 user_id 绛涢?変弗鏍兼牎楠岄棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `DepositController::index` 琛ラ綈鍏ラ噾鍒楄〃 `user_id` 绛涢?変弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/depositList` 涓嶈兘鎶? `user_id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鏁板瓧鍓嶇紑鍖归厤鐪熷疄 `deposit_records.user_id` 骞惰繑鍥炲叆閲戣褰曘??
- 楠岃瘉闈炰弗鏍? `user_id` 杩斿洖 `ResponseCode::VALIDATION_FAILED`锛岄伩鍏嶅垪琛ㄧ瓫閫夋硠闇茬湡瀹炵敤鎴峰叆閲戞暟鎹??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminDepositListUserIdValidationClosureModuleTest.php`
  - 鏂板鍏ラ噾鍒楄〃闈炰弗鏍? `user_id` 绛涢?夎鎷掔粷涓斾笉杩斿洖娴嬭瘯鍏ラ噾璁板綍鐨勬牱渚嬶紝骞舵竻鐞嗘祴璇曚笓鐢ㄥ叆閲戣褰曘??
- `app/Http/Controllers/Admin/DepositController.php`
  - `index` 鍦ㄦ嫾鎺? `deposit_records.user_id` 鏌ヨ鏉′欢鍓嶅厛鏍￠獙 `user_id`锛岄?氳繃鍚庡啀杞崲涓烘暣鏁般??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminDepositListUserIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓叆閲戝垪琛ㄦ帴鍙ｈ繑鍥炴垚鍔熺爜骞舵寜鐪熷疄鐢ㄦ埛 ID 杩斿洖璁板綍锛屾渶缁堟竻鍗曚篃缂哄皯绗? 309 鑺傘??
- GREEN锛氳ˉ榻愬叆閲戝垪琛? `user_id` 鍓嶇疆鏍￠獙鍜岀 309 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminDepositListUserIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 涓庢祴璇曚笓鐢? `deposit_records` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佸拰 `/api/admin/depositList` 鍏ュ彛銆?
- 闈炰弗鏍肩瓫閫夊?? `user_id=鐪熷疄IDabc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍝嶅簲涓嶅寘鍚祴璇曞叆閲戣鍗曞彿锛岄伩鍏? `deposit_records.user_id` 鍦ㄥ弬鏁版牎楠屽墠琚暟鎹簱鏁板瓧鍓嶇紑瑙勫垯鍛戒腑鐪熷疄鐢ㄦ埛鍏ラ噾鏁版嵁銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鍏ラ噾璇︽儏銆佸叆閲戝鏍搞?佸叆閲戞祦姘淬?佹壒閲忓叆閲戝鍏ャ?佸墠绔〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 310. 2026-07-09 鍚庡彴鍑洪噾鍒楄〃 user_id 绛涢?変弗鏍兼牎楠岄棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `WithdrawController::index` 琛ラ綈鍑洪噾鍒楄〃 `user_id` 绛涢?変弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/withdrawList` 涓嶈兘鎶? `user_id=鐪熷疄IDabc` 浜ょ粰鏁版嵁搴撴寜鏁板瓧鍓嶇紑鍖归厤鐪熷疄 `withdraw_records.user_id` 骞惰繑鍥炲嚭閲戣褰曘??
- 楠岃瘉闈炰弗鏍? `user_id` 杩斿洖 `ResponseCode::VALIDATION_FAILED`锛岄伩鍏嶅垪琛ㄧ瓫閫夋硠闇茬湡瀹炵敤鎴峰嚭閲戞暟鎹??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminWithdrawListUserIdValidationClosureModuleTest.php`
  - 鏂板鍑洪噾鍒楄〃闈炰弗鏍? `user_id` 绛涢?夎鎷掔粷涓斾笉杩斿洖娴嬭瘯鍑洪噾璁板綍鐨勬牱渚嬶紝骞舵竻鐞嗘祴璇曚笓鐢ㄥ嚭閲戣褰曘??
- `app/Http/Controllers/Admin/WithdrawController.php`
  - `index` 鍦ㄦ嫾鎺? `withdraw_records.user_id` 鏌ヨ鏉′欢鍓嶅厛鏍￠獙 `user_id`锛岄?氳繃鍚庡啀杞崲涓烘暣鏁般??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminWithdrawListUserIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓嚭閲戝垪琛ㄦ帴鍙ｈ繑鍥炴垚鍔熺爜骞舵寜鐪熷疄鐢ㄦ埛 ID 杩斿洖璁板綍锛屾渶缁堟竻鍗曚篃缂哄皯绗? 310 鑺傘??
- GREEN锛氳ˉ榻愬嚭閲戝垪琛? `user_id` 鍓嶇疆鏍￠獙鍜岀 310 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminWithdrawListUserIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 涓庢祴璇曚笓鐢? `withdraw_records` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佸拰 `/api/admin/withdrawList` 鍏ュ彛銆?
- 闈炰弗鏍肩瓫閫夊?? `user_id=鐪熷疄IDabc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍝嶅簲涓嶅寘鍚祴璇曞嚭閲戣鍗曞彿锛岄伩鍏? `withdraw_records.user_id` 鍦ㄥ弬鏁版牎楠屽墠琚暟鎹簱鏁板瓧鍓嶇紑瑙勫垯鍛戒腑鐪熷疄鐢ㄦ埛鍑洪噾鏁版嵁銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鍑洪噾璇︽儏銆佸嚭閲戝鐞嗐?佸嚭閲戞祦姘淬?佹壒閲忓嚭閲戝鍏ャ?佸墠绔〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 311. 2026-07-09 鍚庡彴鍑洪噾娴佹按 user_id 绛涢?変弗鏍兼牎楠岄棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `FundFlowController::withdrawFlowList` 鍜? `FundFlowController::exportWithdrawFlows` 琛ラ綈鍑洪噾娴佹按 `user_id` 绛涢?変弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/withdrawFlowList` 涓嶈兘鎶? `user_id=鐪熷疄IDabc` 鍦? PHP 灞? `(int)` 寮鸿浆鎴愮湡瀹? `mt4_trades.login` 骞惰繑鍥炲嚭閲戞祦姘淬??
- 楠岃瘉 `/api/admin/exportWithdrawFlows` 鏀跺埌闈炰弗鏍? `user_id` 鏃惰繑鍥? `ResponseCode::VALIDATION_FAILED`锛屼笉鐢熸垚褰撳墠绛涢?夋潯浠朵笅鐨? CSV銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminWithdrawFlowUserIdValidationClosureModuleTest.php`
  - 鏂板鍑洪噾娴佹按鍒楄〃鍜屽鍑轰袱涓潪涓ユ牸 `user_id` 绛涢?夎鎷掔粷鐨勬牱渚嬶紝骞舵竻鐞嗘祴璇曚笓鐢? MT4 浜ゆ槗璁板綍銆?
- `app/Http/Controllers/Admin/FundFlowController.php`
  - `withdrawFlowList` 鍜? `exportWithdrawFlows` 鍦ㄥ鐢ㄧ瓫閫夐?昏緫鍓嶅厛鏍￠獙 `user_id`锛岄?氳繃鍚庢墠鍏佽 `applyWithdrawFlowFilters` 杞崲骞舵嫾鎺? `mt4_trades.login` 鏌ヨ鏉′欢銆?

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminWithdrawFlowUserIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓嚭閲戞祦姘村垪琛ㄨ繑鍥炴垚鍔熺爜锛屽鍑哄叆鍙ｈ繑鍥? `text/csv`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 311 鑺傘??
- GREEN锛氳ˉ榻愬嚭閲戞祦姘? `user_id` 鍓嶇疆鏍￠獙鍜岀 311 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminWithdrawFlowUserIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 涓庢祴璇曚笓鐢? `mt4_trades` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬併?乣/api/admin/withdrawFlowList` 鍜? `/api/admin/exportWithdrawFlows` 涓や釜鍏ュ彛銆?
- 闈炰弗鏍肩瓫閫夊?? `user_id=鐪熷疄IDabc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍝嶅簲涓嶅寘鍚祴璇? MT4 ticket锛屽鍑哄叆鍙ｄ笉鍐嶈緭鍑? CSV锛岄伩鍏? `mt4_trades.login` 鍦ㄥ弬鏁版牎楠屽墠琚? PHP 鏁存暟寮鸿浆鍛戒腑鐪熷疄鍑洪噾娴佹按銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鏈叆閲戞祦姘淬?佹湭鍏ラ噾瀹㈡埛鍒楄〃銆佸叆閲?/鍑洪噾鐢宠銆佸墠绔〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 312. 2026-07-09 鍚庡彴鏈叆閲戞祦姘? user_id 绛涢?変弗鏍兼牎楠岄棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `FundFlowController::undepositFlowList` 鍜? `FundFlowController::exportUndepositFlows` 琛ラ綈鏈叆閲戞祦姘? `user_id` 绛涢?変弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/undepositFlowList` 涓嶈兘鎶? `user_id=鐪熷疄IDabc` 鍦? PHP 灞? `(int)` 寮鸿浆鎴愮湡瀹? `deposit_records.user_id` 骞惰繑鍥炲緟鏀粯鍏ラ噾璁板綍銆?
- 楠岃瘉 `/api/admin/exportUndepositFlows` 鏀跺埌闈炰弗鏍? `user_id` 鏃惰繑鍥? `ResponseCode::VALIDATION_FAILED`锛屼笉鐢熸垚褰撳墠绛涢?夋潯浠朵笅鐨? CSV銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminUndepositFlowUserIdValidationClosureModuleTest.php`
  - 鏂板鏈叆閲戞祦姘村垪琛ㄥ拰瀵煎嚭涓や釜闈炰弗鏍? `user_id` 绛涢?夎鎷掔粷鐨勬牱渚嬶紝骞舵竻鐞嗘祴璇曚笓鐢ㄥ叆閲戣褰曘??
- `app/Http/Controllers/Admin/FundFlowController.php`
  - `undepositFlowList` 鍜? `exportUndepositFlows` 鍦ㄥ鐢ㄧ瓫閫夐?昏緫鍓嶅厛鏍￠獙 `user_id`锛岄?氳繃鍚庢墠鍏佽 `applyUndepositFlowFilters` 杞崲骞舵嫾鎺? `deposit_records.user_id` 鏌ヨ鏉′欢銆?

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminUndepositFlowUserIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓湭鍏ラ噾娴佹按鍒楄〃杩斿洖鎴愬姛鐮侊紝瀵煎嚭鍏ュ彛杩斿洖 `text/csv`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 312 鑺傘??
- GREEN锛氳ˉ榻愭湭鍏ラ噾娴佹按 `user_id` 鍓嶇疆鏍￠獙鍜岀 312 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminUndepositFlowUserIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 涓庢祴璇曚笓鐢? `deposit_records` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬併?乣/api/admin/undepositFlowList` 鍜? `/api/admin/exportUndepositFlows` 涓や釜鍏ュ彛銆?
- 闈炰弗鏍肩瓫閫夊?? `user_id=鐪熷疄IDabc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍝嶅簲涓嶅寘鍚祴璇曞叆閲戣鍗曞彿锛屽鍑哄叆鍙ｄ笉鍐嶈緭鍑? CSV锛岄伩鍏? `deposit_records.user_id` 鍦ㄥ弬鏁版牎楠屽墠琚? PHP 鏁存暟寮鸿浆鍛戒腑鐪熷疄鏈叆閲戞祦姘淬??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鍑洪噾娴佹按銆佹湭鍏ラ噾瀹㈡埛鍒楄〃銆佸叆閲?/鍑洪噾鐢宠銆佸墠绔〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 313. 2026-07-09 鍚庡彴鏈叆閲戝鎴峰垪琛? user_id 绛涢?変弗鏍兼牎楠岄棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `FundFlowController::neverDepositUserList` 琛ラ綈鏈叆閲戝鎴峰垪琛? `user_id` 绛涢?変弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/neverDepositUserList` 涓嶈兘鎶? `user_id=鐪熷疄IDabc` 鍦? PHP 灞? `(int)` 寮鸿浆鎴愮湡瀹? `user_infos.user_id` 骞惰繑鍥炴湭鍏ラ噾瀹㈡埛銆?
- 楠岃瘉闈炰弗鏍? `user_id` 杩斿洖 `ResponseCode::VALIDATION_FAILED`锛岄伩鍏嶅垪琛ㄧ瓫閫夋硠闇茬湡瀹炴櫘閫氬鎴疯祫鏂欍??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminNeverDepositUserListUserIdValidationClosureModuleTest.php`
  - 鏂板鏈叆閲戝鎴峰垪琛ㄩ潪涓ユ牸 `user_id` 绛涢?夎鎷掔粷涓斾笉杩斿洖娴嬭瘯瀹㈡埛濮撳悕鐨勬牱渚嬶紝骞舵竻鐞嗘祴璇曚笓鐢? `user_infos`銆乣user_logins` 鍜? `deposit_records` 鏁版嵁銆?
- `app/Http/Controllers/Admin/FundFlowController.php`
  - `neverDepositUserList` 鍦ㄥ鐢? `applyNeverDepositUserFilters` 鍓嶅厛鏍￠獙 `user_id`锛岄?氳繃鍚庢墠鍏佽绛涢?夊櫒杞崲骞舵嫾鎺? `user_infos.user_id` 鏌ヨ鏉′欢銆?

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminNeverDepositUserListUserIdValidationClosureModuleTest.php` 棣栨涓氬姟绾㈢伅鍛戒腑鏈叆閲戝鎴峰垪琛ㄨ繑鍥炴垚鍔熺爜 `ResponseCode::SUCCESS`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 313 鑺傘??
- GREEN锛氳ˉ榻愭湭鍏ラ噾瀹㈡埛鍒楄〃 `user_id` 鍓嶇疆鏍￠獙鍜岀 313 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminNeverDepositUserListUserIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 涓庢祴璇曚笓鐢? `user_infos`銆乣user_logins` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佸拰 `/api/admin/neverDepositUserList` 鍏ュ彛銆?
- 闈炰弗鏍肩瓫閫夊?? `user_id=鐪熷疄IDabc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍝嶅簲涓嶅寘鍚祴璇曞鎴峰鍚嶏紝閬垮厤 `user_infos.user_id` 鍦ㄥ弬鏁版牎楠屽墠琚? PHP 鏁存暟寮鸿浆鍛戒腑鐪熷疄鏈叆閲戝鎴疯祫鏂欍??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鏈叆閲戞祦姘淬?佸嚭閲戞祦姘淬?佸叆閲?/鍑洪噾鐢宠銆佸墠绔〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 314. 2026-07-09 鍚庡彴浠撲綅娓呴浂 user_id 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 涓? `AdminWhsExpZeroController::zeroList`銆乣AdminWhsExpZeroController::recordList` 鍜? `AdminWhsExpZeroController::oneKeyZero` 琛ラ綈 `user_id` 涓ユ牸鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/whsExpZeroList` 涓嶈兘鎶? `user_id=鐪熷疄IDabc` 鍦? PHP 灞? `(int)` 寮鸿浆鎴愮湡瀹? `user_infos.user_id` 骞惰繑鍥炴竻闆跺?欓?夊鎴枫??
- 楠岃瘉 `/api/admin/whsExpZeroRecords` 涓嶈兘鎶婇潪涓ユ牸 `user_id` 浜ょ粰 `whs_exp_zeros.user_id` 鏌ヨ骞惰繑鍥炵湡瀹炴竻闆惰褰曘??
- 楠岃瘉 `/api/admin/whsExpZero` 鏀跺埌闈炰弗鏍? `user_id` 鏃惰繑鍥? `ResponseCode::VALIDATION_FAILED`锛屼笉鍒涘缓娓呴浂璁板綍銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminWhsExpZeroUserIdValidationClosureModuleTest.php`
  - 鏂板娓呴浂鍊欓?夊垪琛ㄣ?佹竻闆惰褰曞垪琛ㄥ拰涓?閿竻闆朵笁涓叆鍙ｇ殑闈炰弗鏍? `user_id` 琚嫆缁濇牱渚嬶紝骞舵竻鐞嗘祴璇曚笓鐢? `user_infos`銆乣user_trades` 涓? `whs_exp_zeros` 鏁版嵁銆?
- `app/Http/Controllers/Admin/AdminWhsExpZeroController.php`
  - 鏂板鎺у埗鍣ㄥ唴 `validateUserId()`锛屽垪琛ㄥ叆鍙ｆ寜鍙?夌瓫閫夋牎楠岋紝涓?閿竻闆跺叆鍙ｆ寜蹇呭～鍙傛暟鏍￠獙锛涙牎楠岄?氳繃鍚庢墠鍏佽鏌ヨ `user_infos.user_id` 鎴? `whs_exp_zeros.user_id`銆?

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminWhsExpZeroUserIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽?欓?夊垪琛ㄥ拰璁板綍鍒楄〃杩斿洖鎴愬姛鐮? `ResponseCode::SUCCESS`锛屼竴閿竻闆跺叆鍙ｈ繑鍥炴湇鍔＄閿欒鐮侊紝鏈?缁堟竻鍗曚篃缂哄皯绗? 314 鑺傘??
- GREEN锛氳ˉ榻愪粨浣嶆竻闆朵笁涓叆鍙ｇ殑 `user_id` 鍓嶇疆鏍￠獙鍜岀 314 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminWhsExpZeroUserIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 涓庢祴璇曚笓鐢? `user_infos`銆乣whs_exp_zeros` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佸拰 `/api/admin/whsExpZeroList`銆乣/api/admin/whsExpZeroRecords`銆乣/api/admin/whsExpZero` 涓変釜鍏ュ彛銆?
- 闈炰弗鏍肩瓫閫夊?兼垨璇锋眰鍊? `user_id=鐪熷疄IDabc` 鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`銆?
- 鍝嶅簲涓嶅寘鍚祴璇曞鎴锋垨娓呴浂璁板綍鍚嶇О锛屼竴閿竻闆朵笉鍐欏叆 `whs_exp_zeros`锛岄伩鍏? `user_infos.user_id` 涓? `whs_exp_zeros.user_id` 鍦ㄥ弬鏁版牎楠屽墠琚暟瀛楀墠缂?瑙勫垯鍛戒腑鐪熷疄璁板綍銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浠撲綅娓呴浂椤甸潰銆佹潈闄愬瓧鍏搞?佹潈闄愯縼绉汇?佸疄闄? MT4 娓呴浂鍚屾鎴栨暟鎹簱缁撴瀯銆?
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 315. 2026-07-09 鍚庡彴鍦ㄧ嚎鐢ㄦ埛鍒楄〃 user_id 绛涢?変弗鏍兼牎楠岄棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `OnlineUserController::onlineUserList` 琛ラ綈鍦ㄧ嚎鐢ㄦ埛鍒楄〃 `user_id` 绛涢?変弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/onlineUserList` 涓嶈兘鎶? `user_id=鐪熷疄IDabc` 鍦? PHP 灞? `(int)` 寮鸿浆鎴愮湡瀹? `user_onlines.user_id` 骞惰繑鍥炲湪绾胯褰曘??
- 楠岃瘉闈炰弗鏍? `user_id` 杩斿洖 `ResponseCode::VALIDATION_FAILED`锛岄伩鍏嶅湪绾跨敤鎴峰垪琛ㄦ寜鏁板瓧鍓嶇紑娉勯湶鐪熷疄鍦ㄧ嚎璁板綍銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminOnlineUserListUserIdValidationClosureModuleTest.php`
  - 鏂板鍦ㄧ嚎鐢ㄦ埛鍒楄〃闈炰弗鏍? `user_id` 绛涢?夎鎷掔粷涓斾笉杩斿洖娴嬭瘯鐢ㄦ埛濮撳悕鐨勬牱渚嬶紝骞舵竻鐞嗘祴璇曚笓鐢? `user_infos` 涓? `user_onlines` 鏁版嵁銆?
- `app/Http/Controllers/Admin/OnlineUserController.php`
  - `onlineUserList` 鍦ㄦ瀯閫? `user_onlines` 鏌ヨ鍓嶅厛鏍￠獙 `user_id`锛岄?氳繃鍚庢墠鍏佽 `applyFilters` 杞崲骞舵嫾鎺? `user_onlines.user_id` 鏌ヨ鏉′欢銆?

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminOnlineUserListUserIdValidationClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓湪绾跨敤鎴峰垪琛ㄨ繑鍥炴垚鍔熺爜 `ResponseCode::SUCCESS`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 315 鑺傘??
- GREEN锛氳ˉ榻愬湪绾跨敤鎴峰垪琛? `user_id` 鍓嶇疆鏍￠獙鍜岀 315 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminOnlineUserListUserIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 涓庢祴璇曚笓鐢? `user_infos`銆乣user_onlines` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佸拰 `/api/admin/onlineUserList` 鍏ュ彛銆?
- 闈炰弗鏍肩瓫閫夊?? `user_id=鐪熷疄IDabc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍝嶅簲涓嶅寘鍚祴璇曠敤鎴峰鍚嶏紝閬垮厤 `user_onlines.user_id` 鍦ㄥ弬鏁版牎楠屽墠琚? PHP 鏁存暟寮鸿浆鍛戒腑鐪熷疄鍦ㄧ嚎璁板綍銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩寮哄埗涓嬬嚎銆佸湪绾跨敤鎴烽〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉汇?丼SO 娓呯悊閫昏緫鎴栨暟鎹簱缁撴瀯銆?
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 316. 2026-07-09 鍚庡彴鎸佷粨姹囨?绘暟鍊肩瓫閫変弗鏍兼牎楠岄棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `PositionSummaryController::positionSummaryList` 鍜? `PositionSummaryController::exportPositionSummary` 琛ラ綈 `user_id`銆乣parent_id`銆乣account_type` 鏁板?肩瓫閫変弗鏍兼牎楠屻??
- 楠岃瘉 `/api/admin/positionSummaryList` 涓嶈兘鎶? `user_id=鐪熷疄IDabc`銆乣parent_id=鐪熷疄IDabc` 鎴? `account_type=2abc` 鍦? PHP 灞? `(int)` 寮鸿浆鍚庝氦缁? `user_infos.user_id`銆乣user_infos.parent_id`銆乣user_infos.account_type` 鏌ヨ骞惰繑鍥炵湡瀹炵敤鎴枫??
- 楠岃瘉 `/api/admin/exportPositionSummary` 鏀跺埌闈炰弗鏍兼暟鍊肩瓫閫夋椂杩斿洖 `ResponseCode::VALIDATION_FAILED`锛屼笉杈撳嚭褰撳墠绛涢?夋潯浠朵笅鐨? CSV銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminPositionSummaryNumericFilterValidationClosureModuleTest.php`
  - 鏂板鎸佷粨姹囨?诲垪琛ㄥ拰瀵煎嚭涓や釜鍏ュ彛鐨勯潪涓ユ牸鏁板?肩瓫閫夎鎷掔粷鏍蜂緥锛屽苟娓呯悊娴嬭瘯涓撶敤 `user_infos` 鏁版嵁銆?
- `app/Http/Controllers/Admin/PositionSummaryController.php`
  - `positionSummaryList` 鍜? `exportPositionSummary` 鍦ㄦ瀯寤? `user_infos` 姹囨?绘煡璇㈠墠缁熶竴璋冪敤 `validateNumericFilters()`锛岄?氳繃鍚庢墠鍏佽 `applyUserFilters()` 杞崲骞舵嫾鎺ユ煡璇㈡潯浠躲??

### TDD 鎵ц璁板綍
- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit --filter AdminPositionSummaryNumericFilterValidationClosureModuleTest --colors=never` 棣栨杩愯澶辫触锛屽懡涓? `validateNumericFilters()` 缂哄け瀵艰嚧鍒楄〃鍜屽鍑哄叆鍙ｈ繑鍥? 500锛屾渶缁堟竻鍗曚篃缂哄皯绗? 316 鑺傘??
- GREEN锛氳ˉ榻愭寔浠撴眹鎬绘暟鍊肩瓫閫夊墠缃牎楠屽拰绗? 316 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminPositionSummaryNumericFilterValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins` 涓庢祴璇曚笓鐢? `user_infos` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬併?乣/api/admin/positionSummaryList` 鍜? `/api/admin/exportPositionSummary` 涓や釜鍏ュ彛銆?
- 闈炰弗鏍肩瓫閫夊?? `user_id=鐪熷疄IDabc`銆乣parent_id=鐪熷疄IDabc`銆乣account_type=2abc` 鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`銆?
- 鍒楄〃鍝嶅簲涓嶅寘鍚祴璇曠敤鎴峰鍚嶏紝瀵煎嚭鍏ュ彛涓嶅啀杈撳嚭 `text/csv`锛岄伩鍏? `user_infos.user_id`銆乣user_infos.parent_id`銆乣user_infos.account_type` 鍦ㄥ弬鏁版牎楠屽墠琚暣鏁板己杞懡涓湡瀹炴寔浠撴眹鎬荤敤鎴枫??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鎸佷粨姹囨?荤粺璁″彛寰勩?佷氦鏄撹仛鍚堛?侀〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 317. 2026-07-09 鍚庡彴椋庢帶褰撳墠鎸佷粨 user_id 绛涢?変弗鏍兼牎楠岄棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `RiskController::positions` 琛ラ綈褰撳墠鎸佷粨椋庨櫓鍒楄〃 `user_id` 绛涢?変弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/riskPositions` 涓嶈兘鎶? `user_id=鐪熷疄IDabc` 鍦? PHP 灞? `(int)` 寮鸿浆鎴愮湡瀹? `mt4_trades.login` 骞惰繑鍥炲綋鍓嶆寔浠撻闄╄褰曘??
- 楠岃瘉闈炰弗鏍? `user_id` 杩斿洖 `ResponseCode::VALIDATION_FAILED`锛岄伩鍏嶉鎺ф寔浠撳垪琛ㄦ寜鏁板瓧鍓嶇紑娉勯湶鐪熷疄 MT4 鎸佷粨鏁版嵁銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminRiskPositionsUserIdValidationClosureModuleTest.php`
  - 鏂板褰撳墠鎸佷粨椋庨櫓鍒楄〃闈炰弗鏍? `user_id` 绛涢?夎鎷掔粷涓斾笉杩斿洖娴嬭瘯 MT4 ticket 鍜岀敤鎴峰鍚嶇殑鏍蜂緥锛屽苟娓呯悊娴嬭瘯涓撶敤 `mt4_trades` 涓? `user_infos` 鏁版嵁銆?
- `app/Http/Controllers/Admin/RiskController.php`
  - `positions` 鍦ㄦ瀯閫? `mt4_trades` 褰撳墠鎸佷粨椋庨櫓鏌ヨ鍓嶅厛鏍￠獙 `user_id`锛岄?氳繃鍚庢墠鍏佽 `applyTradeFilters` 杞崲骞舵嫾鎺? `mt4_trades.login` 鏌ヨ鏉′欢銆?

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminRiskPositionsUserIdValidationClosureModuleTest.php --colors=never` 棣栨杩愯澶辫触锛屽懡涓鎺у綋鍓嶆寔浠撳垪琛ㄨ繑鍥炴垚鍔熺爜 `ResponseCode::SUCCESS`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 317 鑺傘??
- GREEN锛氳ˉ榻愰鎺у綋鍓嶆寔浠? `user_id` 鍓嶇疆鏍￠獙鍜岀 317 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminRiskPositionsUserIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆佹祴璇曚笓鐢? `user_infos` 鍜? `mt4_trades` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佸拰 `/api/admin/riskPositions` 鍏ュ彛銆?
- 闈炰弗鏍肩瓫閫夊?? `user_id=鐪熷疄IDabc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍝嶅簲涓嶅寘鍚祴璇? MT4 ticket 鍜屾祴璇曠敤鎴峰鍚嶏紝閬垮厤 `mt4_trades.login` 鍦ㄥ弬鏁版牎楠屽墠琚? PHP 鏁存暟寮鸿浆鍛戒腑鐪熷疄鎸佷粨椋庨櫓璁板綍銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩杩戒繚棰勮銆佸紓甯? IP 椋庢帶銆佸己骞充俊鍙枫?侀鎺ч〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 318. 2026-07-09 鍚庡彴椋庢帶杩戒繚棰勮鏁板瓧绛涢?変弗鏍兼牎楠岄棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `RiskController::marginCalls` 琛ラ綈杩戒繚棰勮鍒楄〃 `user_id`銆乣login` 鍜? `max_margin_level` 鏁板瓧绛涢?変弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/riskMarginCalls` 涓嶈兘鎶? `user_id=鐪熷疄IDabc`銆乣login=鐪熷疄鐧诲綍abc` 鎴? `max_margin_level=100abc` 鍦? PHP 灞傚己杞悗鍛戒腑鐪熷疄杩戒繚棰勮璐﹀彿銆?
- 楠岃瘉闈炰弗鏍兼暟瀛楃瓫閫夎繑鍥? `ResponseCode::VALIDATION_FAILED`锛岄伩鍏嶈拷淇濋璀﹀垪琛ㄦ寜鏁板瓧鍓嶇紑娉勯湶鐪熷疄 MT4 璧勯噾蹇収鍜屼笟鍔＄敤鎴疯祫鏂欍??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminRiskMarginCallsNumericFilterValidationClosureModuleTest.php`
  - 鏂板杩戒繚棰勮鍒楄〃闈炰弗鏍兼暟瀛楃瓫閫夎鎷掔粷涓斾笉杩斿洖娴嬭瘯 MT4 鐧诲綍璐﹀彿鍜岀敤鎴峰鍚嶇殑鏍蜂緥锛屽苟娓呯悊娴嬭瘯涓撶敤 `mt4_users` 涓? `user_infos` 鏁版嵁銆?
- `app/Http/Controllers/Admin/RiskController.php`
  - `marginCalls` 鍦ㄦ瀯閫? `mt4_users` 杩戒繚棰勮鏌ヨ鍓嶅厛鏍￠獙 `user_id`銆乣login` 鍜? `max_margin_level`锛岄?氳繃鍚庢墠鍏佽 `baseMarginCallQuery` 涓? `applyMarginCallFilters` 杞崲骞舵嫾鎺ユ煡璇㈡潯浠躲??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminRiskMarginCallsNumericFilterValidationClosureModuleTest.php --colors=never` 棣栨杩愯澶辫触锛屽懡涓拷淇濋璀﹀垪琛ㄨ繑鍥炴垚鍔熺爜 `ResponseCode::SUCCESS`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 318 鑺傘??
- GREEN锛氳ˉ榻愯拷淇濋璀︽暟瀛楃瓫閫夊墠缃牎楠屽拰绗? 318 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminRiskMarginCallsNumericFilterValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆佹祴璇曚笓鐢? `user_infos` 鍜? `mt4_users` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佸拰 `/api/admin/riskMarginCalls` 鍏ュ彛銆?
- 闈炰弗鏍肩瓫閫夊?? `user_id=鐪熷疄IDabc`銆乣login=鐪熷疄鐧诲綍abc`銆乣max_margin_level=100abc` 鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`銆?
- 鍝嶅簲涓嶅寘鍚祴璇? MT4 鐧诲綍璐﹀彿鍜屾祴璇曠敤鎴峰鍚嶏紝閬垮厤 `user_infos.user_id`銆乣mt4_users.login` 涓? `max_margin_level` 鍦ㄥ弬鏁版牎楠屽墠琚暣鏁版垨娴偣寮鸿浆鍛戒腑鐪熷疄杩戒繚棰勮璐﹀彿銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩褰撳墠鎸佷粨椋庨櫓銆佸紓甯? IP 椋庢帶銆佸己骞充俊鍙枫?侀鎺ч〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 319. 2026-07-09 鍚庡彴寮傚父 IP 鍒楄〃鏁板瓧绛涢?変弗鏍兼牎楠岄棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `RiskController::riskIpList` 琛ラ綈寮傚父 IP 椋庢帶鍒楄〃 `user_id` 鍜? `min_user_count` 鏁板瓧绛涢?変弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/riskIpList` 涓嶈兘鎶? `user_id=鐪熷疄IDabc` 鎴? `min_user_count=2abc` 鍦? PHP 灞? `(int)` 寮鸿浆鍚庝氦缁? `user_login_logs.user_id` 鏌ヨ鎴栧紓甯? IP 鑱氬悎闃堝?笺??
- 楠岃瘉闈炰弗鏍兼暟瀛楃瓫閫夎繑鍥? `ResponseCode::VALIDATION_FAILED`锛岄伩鍏嶅紓甯? IP 鍒楄〃鎸夋暟瀛楀墠缂?娉勯湶鐪熷疄鐧诲綍椋庨櫓鑱氬悎鏁版嵁銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminRiskIpListNumericFilterValidationClosureModuleTest.php`
  - 鏂板寮傚父 IP 鍒楄〃闈炰弗鏍兼暟瀛楃瓫閫夎鎷掔粷涓斾笉杩斿洖娴嬭瘯鐧诲綍 IP 鍜岀敤鎴峰鍚嶇殑鏍蜂緥锛屽苟娓呯悊娴嬭瘯涓撶敤 `user_login_logs` 涓? `user_infos` 鏁版嵁銆?
- `app/Http/Controllers/Admin/RiskController.php`
  - `riskIpList` 鍦ㄦ瀯閫? `user_login_logs` 寮傚父 IP 鑱氬悎鏌ヨ鍓嶅厛鏍￠獙 `user_id` 鍜? `min_user_count`锛岄?氳繃鍚庢墠鍏佽 `baseRiskIpQuery` 涓? `applyRiskIpFilters` 杞崲骞舵嫾鎺ユ煡璇㈡潯浠躲??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminRiskIpListNumericFilterValidationClosureModuleTest.php --colors=never` 棣栨杩愯澶辫触锛屽懡涓紓甯? IP 鍒楄〃杩斿洖鎴愬姛鐮? `ResponseCode::SUCCESS`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 319 鑺傘??
- GREEN锛氳ˉ榻愬紓甯? IP 鍒楄〃鏁板瓧绛涢?夊墠缃牎楠屽拰绗? 319 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminRiskIpListNumericFilterValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆佹祴璇曚笓鐢? `user_infos` 鍜? `user_login_logs` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佸拰 `/api/admin/riskIpList` 鍏ュ彛銆?
- 闈炰弗鏍肩瓫閫夊?? `user_id=鐪熷疄IDabc`銆乣min_user_count=2abc` 鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`銆?
- 鍝嶅簲涓嶅寘鍚祴璇曠櫥褰? IP 鍜屾祴璇曠敤鎴峰鍚嶏紝閬垮厤 `user_login_logs.user_id` 涓庡紓甯? IP 鑱氬悎闃堝?煎湪鍙傛暟鏍￠獙鍓嶈鏁存暟寮鸿浆褰卞搷鐪熷疄椋庨櫓鍒楄〃銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩寮傚父 IP 璇︽儏銆佸綋鍓嶆寔浠撻闄┿?佽拷淇濋璀︺?佸己骞充俊鍙枫?侀鎺ч〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 320. 2026-07-09 鍚庡彴寮傚父 IP 璇︽儏 user_id 绛涢?変弗鏍兼牎楠岄棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `RiskController::riskIpDetail` 琛ラ綈寮傚父 IP 璇︽儏 `user_id` 绛涢?変弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/riskIpDetail` 涓嶈兘鎶? `user_id=鐪熷疄IDabc` 鍦? PHP 灞? `(int)` 寮鸿浆鍚庝氦缁? `user_login_logs.user_id` 鏌ヨ骞惰繑鍥炵湡瀹炵櫥褰曡鎯呫??
- 楠岃瘉闈炰弗鏍? `user_id` 杩斿洖 `ResponseCode::VALIDATION_FAILED`锛岄伩鍏嶅紓甯? IP 璇︽儏鎸夋暟瀛楀墠缂?娉勯湶鐪熷疄鐢ㄦ埛鐧诲綍鏄庣粏銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminRiskIpDetailUserIdValidationClosureModuleTest.php`
  - 鏂板寮傚父 IP 璇︽儏闈炰弗鏍? `user_id` 绛涢?夎鎷掔粷涓斾笉杩斿洖娴嬭瘯鐧诲綍 IP 鍜岀敤鎴峰鍚嶇殑鏍蜂緥锛屽苟娓呯悊娴嬭瘯涓撶敤 `user_login_logs` 涓? `user_infos` 鏁版嵁銆?
- `app/Http/Controllers/Admin/RiskController.php`
  - `riskIpDetail` 鍦ㄥ畬鎴? `login_ip` 蹇呭～鏍￠獙鍚庛?佹瀯閫? `user_login_logs` 璇︽儏鏌ヨ鍓嶆牎楠? `user_id`锛岄?氳繃鍚庢墠鍏佽 `applyRiskIpDetailFilters` 杞崲骞舵嫾鎺? `user_login_logs.user_id` 鏌ヨ鏉′欢銆?

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminRiskIpDetailUserIdValidationClosureModuleTest.php --colors=never` 棣栨杩愯澶辫触锛屽懡涓紓甯? IP 璇︽儏杩斿洖鎴愬姛鐮? `ResponseCode::SUCCESS`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 320 鑺傘??
- GREEN锛氳ˉ榻愬紓甯? IP 璇︽儏 `user_id` 鍓嶇疆鏍￠獙鍜岀 320 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminRiskIpDetailUserIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆佹祴璇曚笓鐢? `user_infos` 鍜? `user_login_logs` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佸拰 `/api/admin/riskIpDetail` 鍏ュ彛銆?
- 闈炰弗鏍肩瓫閫夊?? `user_id=鐪熷疄IDabc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍝嶅簲涓嶅寘鍚祴璇曠櫥褰? IP 鍜屾祴璇曠敤鎴峰鍚嶏紝閬垮厤 `user_login_logs.user_id` 鍦ㄥ弬鏁版牎楠屽墠琚暣鏁板己杞懡涓湡瀹炲紓甯? IP 鐧诲綍璇︽儏銆?

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩寮傚父 IP 鍒楄〃銆佸綋鍓嶆寔浠撻闄┿?佽拷淇濋璀︺?佸己骞充俊鍙枫?侀鎺ч〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 321. 2026-07-09 鍚庡彴瀹炲悕璁よ瘉鍒楄〃 user_id 绛涢?変弗鏍兼牎楠岄棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `AuthenticationController::pendingList` 鍜? `AuthenticationController::certifiedList` 琛ラ綈瀹炲悕璁よ瘉鍒楄〃 `user_id` 绛涢?変弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/authPendingList` 涓嶈兘鎶? `user_id=鐪熷疄IDabc` 鍦? PHP 灞? `(int)` 寮鸿浆鍚庝氦缁? `user_auths.user_id` 鏌ヨ骞惰繑鍥炵湡瀹炲緟瀹¤璇佽褰曘??
- 楠岃瘉 `/api/admin/authCertifiedList` 涓嶈兘鎶婇潪涓ユ牸 `user_id` 鍛戒腑鐪熷疄宸插璁よ瘉璁板綍銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminAuthenticationUserIdValidationClosureModuleTest.php`
  - 鏂板寰呭璁よ瘉鍒楄〃鍜屽凡瀹¤璇佸垪琛ㄩ潪涓ユ牸 `user_id` 绛涢?夎鎷掔粷涓斾笉杩斿洖娴嬭瘯鐢ㄦ埛濮撳悕鐨勬牱渚嬶紝骞舵竻鐞嗘祴璇曚笓鐢? `user_auths` 涓? `user_infos` 鏁版嵁銆?
- `app/Http/Controllers/Admin/AuthenticationController.php`
  - `pendingList` 鍜? `certifiedList` 鍦ㄦ瀯閫? `user_auths` 璁よ瘉鏌ヨ鍓嶆牎楠? `user_id`锛岄?氳繃鍚庢墠鍏佽 `applyFilters` 杞崲骞舵嫾鎺? `user_auths.user_id` 鏌ヨ鏉′欢銆?

### TDD 鎵ц璁板綍
- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit --filter AdminAuthenticationUserIdValidationClosureModuleTest --colors=never` 棣栨杩愯澶辫触锛屽懡涓緟瀹″拰宸插璁よ瘉鍒楄〃杩斿洖鎴愬姛鐮? `ResponseCode::SUCCESS`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 321 鑺傘??
- GREEN锛氳ˉ榻愬疄鍚嶈璇佸垪琛? `user_id` 鍓嶇疆鏍￠獙鍜岀 321 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminAuthenticationUserIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆佹祴璇曚笓鐢? `user_infos` 鍜? `user_auths` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佷互鍙? `/api/admin/authPendingList`銆乣/api/admin/authCertifiedList` 涓や釜鍏ュ彛銆?
- 闈炰弗鏍肩瓫閫夊?? `user_id=鐪熷疄IDabc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍝嶅簲涓嶅寘鍚祴璇曡璇佺敤鎴峰鍚嶏紝閬垮厤 `user_auths.user_id` 鍦ㄥ弬鏁版牎楠屽墠琚暣鏁板己杞懡涓湡瀹炶璇佽祫鏂欍??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩璁よ瘉瀹℃牳鍔ㄤ綔銆佸疄鍚嶈璇侀〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 322. 2026-07-09 鍚庡彴鏉冪泭姹囨?绘暟瀛楃瓫閫変弗鏍兼牎楠岄棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `RightsSummaryController::rightsSummaryList` 鍜? `RightsSummaryController::exportRightsSummary` 琛ラ綈 `user_id`銆乣login`銆乣min_equity`銆乣max_equity` 鏁板瓧绛涢?変弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/rightsSummaryList` 涓嶈兘鎶? `user_id=鐪熷疄IDabc`銆乣login=鐪熷疄鐧诲綍abc`銆乣min_equity=1000abc` 鎴? `max_equity=1300abc` 鍦? PHP 灞傚己杞悗鍛戒腑鐪熷疄鏉冪泭姹囨?昏处鍙枫??
- 楠岃瘉 `/api/admin/exportRightsSummary` 鏀跺埌闈炰弗鏍兼暟瀛楃瓫閫夋椂杩斿洖 `ResponseCode::VALIDATION_FAILED`锛屼笉杈撳嚭褰撳墠绛涢?夋潯浠朵笅鐨? CSV銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminRightsSummaryNumericFilterValidationClosureModuleTest.php`
  - 鏂板鏉冪泭姹囨?诲垪琛ㄥ拰瀵煎嚭涓や釜鍏ュ彛鐨勯潪涓ユ牸鏁板瓧绛涢?夎鎷掔粷鏍蜂緥锛屽苟娓呯悊娴嬭瘯涓撶敤 `user_infos`銆乣mt4_users` 涓? `rights_settlements` 鏁版嵁銆?
- `app/Http/Controllers/Admin/RightsSummaryController.php`
  - `rightsSummaryList` 鍜? `exportRightsSummary` 鍦ㄦ瀯閫? `mt4_users` 鏉冪泭姹囨?绘煡璇㈠墠缁熶竴璋冪敤 `validateNumericFilters()`锛岄?氳繃鍚庢墠鍏佽 `applyFilters()` 杞崲骞舵嫾鎺ユ煡璇㈡潯浠躲??

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminRightsSummaryNumericFilterValidationClosureModuleTest.php --colors=never` 棣栨杩愯澶辫触锛屽懡涓潈鐩婃眹鎬诲垪琛ㄨ繑鍥炴垚鍔熺爜 `ResponseCode::SUCCESS`銆佸鍑哄叆鍙ｈ繑鍥? `text/csv`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 322 鑺傘??
- GREEN锛氳ˉ榻愭潈鐩婃眹鎬绘暟瀛楃瓫閫夊墠缃牎楠屽拰绗? 322 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminRightsSummaryNumericFilterValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆佹祴璇曚笓鐢? `user_infos`銆乣mt4_users` 鍜? `rights_settlements` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佷互鍙? `/api/admin/rightsSummaryList`銆乣/api/admin/exportRightsSummary` 涓や釜鍏ュ彛銆?
- 闈炰弗鏍肩瓫閫夊?? `user_id=鐪熷疄IDabc`銆乣login=鐪熷疄鐧诲綍abc`銆乣min_equity=1000abc`銆乣max_equity=1300abc` 鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`銆?
- 鍒楄〃鍝嶅簲涓嶅寘鍚祴璇曠敤鎴峰鍚嶅拰 MT4 鍚嶇О锛屽鍑哄叆鍙ｄ笉鍐嶈緭鍑? `text/csv`锛岄伩鍏? `user_infos.user_id`銆乣mt4_users.login`銆乣mt4_users.equity` 鍦ㄥ弬鏁版牎楠屽墠琚暣鏁版垨娴偣寮鸿浆鍛戒腑鐪熷疄鏉冪泭姹囨?昏处鍙枫??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鏉冪泭缁撶畻鎵嬪姩纭銆佹潈鐩婃眹鎬婚〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 323. 2026-07-09 鍚庡彴绀煎搧鍙戣揣涓庡湴鍧? user_id 绛涢?変弗鏍兼牎楠岄棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `GiftController::shipmentList`銆乣GiftController::exportGiftShipments` 鍜? `GiftController::addressList` 琛ラ綈绀煎搧鍙戣揣涓庢敹璐у湴鍧? `user_id` 绛涢?変弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/giftShipmentList` 涓嶈兘鎶? `user_id=鐪熷疄IDabc` 鍦? PHP 灞? `(int)` 寮鸿浆鍚庝氦缁? `gift_shipments.user_id` 鏌ヨ骞惰繑鍥炵湡瀹炲彂璐ц褰曘??
- 楠岃瘉 `/api/admin/exportGiftShipments` 鏀跺埌闈炰弗鏍? `user_id` 鏃惰繑鍥? `ResponseCode::VALIDATION_FAILED`锛屼笉杈撳嚭褰撳墠绛涢?夋潯浠朵笅鐨? CSV銆?
- 楠岃瘉 `/api/admin/giftAddressList` 涓嶈兘鎶婇潪涓ユ牸 `user_id` 鍛戒腑鐪熷疄鍙彂鏀剧ぜ鍝佸湴鍧?銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminGiftUserIdValidationClosureModuleTest.php`
  - 鏂板绀煎搧鍙戣揣鍒楄〃銆佸彂璐у鍑哄拰鏀惰揣鍦板潃鍒楄〃涓変釜鍏ュ彛鐨勯潪涓ユ牸 `user_id` 绛涢?夎鎷掔粷鏍蜂緥锛屽苟娓呯悊娴嬭瘯涓撶敤 `gift_shipments`銆乣user_addresses` 涓? `user_infos` 鏁版嵁銆?
- `app/Http/Controllers/Admin/GiftController.php`
  - `shipmentList`銆乣exportGiftShipments` 鍜? `addressList` 鍦ㄦ瀯閫犳煡璇㈠墠鏍￠獙 `user_id`锛岄?氳繃鍚庢墠鍏佽绛涢?夊櫒杞崲骞舵嫾鎺? `gift_shipments.user_id` 鎴? `user_addresses.user_id` 鏌ヨ鏉′欢銆?

### TDD 鎵ц璁板綍
- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit --filter AdminGiftUserIdValidationClosureModuleTest --colors=never` 棣栨杩愯澶辫触锛屽懡涓ぜ鍝佸彂璐у垪琛ㄥ拰鍦板潃鍒楄〃杩斿洖鎴愬姛鐮? `ResponseCode::SUCCESS`銆佸鍑哄叆鍙ｈ繑鍥? `text/csv`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 323 鑺傘??
- GREEN锛氳ˉ榻愮ぜ鍝佸彂璐т笌鍦板潃 `user_id` 鍓嶇疆鏍￠獙鍜岀 323 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminGiftUserIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆佹祴璇曚笓鐢? `user_infos`銆乣user_addresses` 鍜? `gift_shipments` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佷互鍙? `/api/admin/giftShipmentList`銆乣/api/admin/exportGiftShipments`銆乣/api/admin/giftAddressList` 涓変釜鍏ュ彛銆?
- 闈炰弗鏍肩瓫閫夊?? `user_id=鐪熷疄IDabc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍒楄〃鍝嶅簲涓嶅寘鍚祴璇曠ぜ鍝併?佺墿娴佸崟鍙枫?佹敹浠朵汉鎴栫敤鎴峰鍚嶏紝瀵煎嚭鍏ュ彛涓嶅啀杈撳嚭 `text/csv`锛岄伩鍏? `gift_shipments.user_id` 涓? `user_addresses.user_id` 鍦ㄥ弬鏁版牎楠屽墠琚暣鏁板己杞懡涓湡瀹炵ぜ鍝佽祫鏂欍??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩绀煎搧鍙戞斁鍔ㄤ綔銆佺墿娴佹洿鏂板姩浣溿?佺ぜ鍝侀厤缃瓫閫夈?佺ぜ鍝侀〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 324. 2026-07-09 鍚庡彴浜ゆ槗璁㈠崟 user_id 绛涢?変弗鏍兼牎楠岄棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `TradeController::index`銆乣TradeController::openPositions` 鍜? `TradeController::closedPositions` 琛ラ綈浜ゆ槗璁㈠崟 `user_id` 绛涢?変弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/tradeList`銆乣/api/admin/openPositions` 鍜? `/api/admin/closedPositions` 涓嶈兘鎶? `user_id=鐪熷疄IDabc` 鍦? PHP 灞? `(int)` 寮鸿浆鎴愮湡瀹? `mt4_trades.login` 骞惰繑鍥炰氦鏄撹褰曘??
- 楠岃瘉闈炰弗鏍? `user_id` 杩斿洖 `ResponseCode::VALIDATION_FAILED`锛岄伩鍏嶄氦鏄撳垪琛ㄣ?佸綋鍓嶆寔浠撳拰骞充粨璁板綍鎸夋暟瀛楀墠缂?娉勯湶鐪熷疄 MT4 璁㈠崟鏁版嵁銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminTradeUserIdValidationClosureModuleTest.php`
  - 鏂板浜ゆ槗鍒楄〃銆佸綋鍓嶆寔浠撳拰骞充粨璁板綍涓変釜鍏ュ彛鐨勯潪涓ユ牸 `user_id` 绛涢?夎鎷掔粷鏍蜂緥锛屽苟娓呯悊娴嬭瘯涓撶敤 `mt4_trades` 涓? `user_infos` 鏁版嵁銆?
- `app/Http/Controllers/Admin/TradeController.php`
  - 涓変釜鍒楄〃鍏ュ彛鍦ㄦ瀯閫? `mt4_trades` 鏌ヨ鍓嶆牎楠? `user_id`锛岄?氳繃鍚庢墠鍏佽 `applyTradeFilters` 杞崲骞舵嫾鎺? `mt4_trades.login` 鏌ヨ鏉′欢銆?

### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminTradeUserIdValidationClosureModuleTest.php --colors=never` 棣栨杩愯澶辫触锛屽懡涓氦鏄撳垪琛ㄥ叆鍙ｈ繑鍥炴垚鍔熺爜 `ResponseCode::SUCCESS`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 324 鑺傘??
- GREEN锛氳ˉ榻愪氦鏄撹鍗? `user_id` 鍓嶇疆鏍￠獙鍜岀 324 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminTradeUserIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆佹祴璇曚笓鐢? `user_infos` 鍜? `mt4_trades` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佷互鍙? `/api/admin/tradeList`銆乣/api/admin/openPositions`銆乣/api/admin/closedPositions` 涓変釜鍏ュ彛銆?
- 闈炰弗鏍肩瓫閫夊?? `user_id=鐪熷疄IDabc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍝嶅簲涓嶅寘鍚祴璇? MT4 ticket 鍜屾祴璇曠敤鎴峰鍚嶏紝閬垮厤 `mt4_trades.login` 鍦ㄥ弬鏁版牎楠屽墠琚暣鏁板己杞懡涓湡瀹炰氦鏄撹鍗曘??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浜ゆ槗姒傝 `tradeSummary`銆佷氦鏄撻〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 325. 2026-07-09 鍚庡彴绀煎搧閰嶇疆鍒楄〃鏁板瓧绛涢?変弗鏍兼牎楠岄棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `GiftController::giftItemList` 琛ラ綈绀煎搧閰嶇疆鍒楄〃 `points_cost` 鍜? `status` 鏁板瓧绛涢?変弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/giftItemList` 涓嶈兘鎶? `points_cost=420abc` 鎴? `status=1abc` 鍦? PHP 灞? `(int)` 寮鸿浆鍚庝氦缁? `gift_items.points_cost`銆乣gift_items.status` 鏌ヨ骞惰繑鍥炵湡瀹炵ぜ鍝侀厤缃??
- 楠岃瘉闈炰弗鏍兼暟瀛楃瓫閫夎繑鍥? `ResponseCode::VALIDATION_FAILED`锛岄伩鍏嶇ぜ鍝侀厤缃垪琛ㄦ寜鏁板瓧鍓嶇紑娉勯湶鐪熷疄閰嶇疆璁板綍銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminGiftItemNumericFilterValidationClosureModuleTest.php`
  - 鏂板绀煎搧閰嶇疆鍒楄〃闈炰弗鏍? `points_cost` 鍜? `status` 绛涢?夎鎷掔粷涓斾笉杩斿洖娴嬭瘯绀煎搧鍚嶇О鐨勬牱渚嬶紝骞舵竻鐞嗘祴璇曚笓鐢? `gift_items` 鏁版嵁銆?
- `app/Http/Controllers/Admin/GiftController.php`
  - `giftItemList` 鍦ㄦ瀯閫? `gift_items` 鏌ヨ鍓嶆牎楠? `points_cost` 鍜? `status`锛岄?氳繃鍚庢墠鍏佽鍒楄〃绛涢?夊櫒杞崲骞舵嫾鎺ユ煡璇㈡潯浠躲??

### TDD 鎵ц璁板綍
- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit --filter AdminGiftItemNumericFilterValidationClosureModuleTest --colors=never` 棣栨杩愯澶辫触锛屽懡涓ぜ鍝侀厤缃垪琛ㄨ繑鍥炴垚鍔熺爜 `ResponseCode::SUCCESS`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 325 鑺傘??
- GREEN锛氳ˉ榻愮ぜ鍝侀厤缃垪琛ㄦ暟瀛楃瓫閫夊墠缃牎楠屽拰绗? 325 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminGiftItemNumericFilterValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆佹祴璇曚笓鐢? `gift_items` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佷互鍙? `/api/admin/giftItemList` 鍏ュ彛銆?
- 闈炰弗鏍肩瓫閫夊?? `points_cost=420abc` 鍜? `status=1abc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍝嶅簲涓嶅寘鍚祴璇曠ぜ鍝侀厤缃悕绉帮紝閬垮厤 `gift_items.points_cost` 涓? `gift_items.status` 鍦ㄥ弬鏁版牎楠屽墠琚暣鏁板己杞懡涓湡瀹炵ぜ鍝侀厤缃??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩绀煎搧閰嶇疆鍒涘缓銆佹洿鏂般?佸垹闄ゃ?佺ぜ鍝佸彂璐т笌鍦板潃绛涢?夈?佺ぜ鍝侀〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 326. 2026-07-09 鍚庡彴鎵归噺淇＄敤瀵煎叆鏁板瓧绛涢?変弗鏍兼牎楠岄棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `BatchCreditImportController::creditImportList` 鍜? `BatchCreditImportController::exportCreditImports` 琛ラ綈鎵归噺淇＄敤瀵煎叆 `user_id`銆乣credit_type` 鍜? `is_synced` 鏁板瓧绛涢?変弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/creditImportList` 涓嶈兘鎶? `user_id=鐪熷疄IDabc`銆乣credit_type=3abc` 鎴? `is_synced=2abc` 鍦? PHP 灞? `(int)` 寮鸿浆鍚庝氦缁? `credit_imports.user_id`銆乣credit_imports.credit_type`銆乣credit_imports.is_synced` 鏌ヨ骞惰繑鍥炵湡瀹炲鍏ヨ褰曘??
- 楠岃瘉 `/api/admin/exportCreditImports` 鏀跺埌闈炰弗鏍兼暟瀛楃瓫閫夋椂杩斿洖 `ResponseCode::VALIDATION_FAILED`锛屼笉杈撳嚭褰撳墠绛涢?夋潯浠朵笅鐨? CSV銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminBatchCreditImportNumericFilterValidationClosureModuleTest.php`
  - 鏂板鎵归噺淇＄敤瀵煎叆鍒楄〃鍜屽鍑轰袱涓叆鍙ｇ殑闈炰弗鏍兼暟瀛楃瓫閫夎鎷掔粷鏍蜂緥锛屽苟娓呯悊娴嬭瘯涓撶敤 `credit_imports` 涓? `user_infos` 鏁版嵁銆?
- `app/Http/Controllers/Admin/BatchCreditImportController.php`
  - `creditImportList` 鍜? `exportCreditImports` 鍦ㄦ瀯閫? `credit_imports` 鏌ヨ鍓嶆牎楠? `user_id`銆乣credit_type` 鍜? `is_synced`锛岄?氳繃鍚庢墠鍏佽 `applyFilters()` 杞崲骞舵嫾鎺ユ煡璇㈡潯浠躲??

### TDD 鎵ц璁板綍
- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit --filter AdminBatchCreditImportNumericFilterValidationClosureModuleTest --colors=never` 棣栨杩愯澶辫触锛屽懡涓壒閲忎俊鐢ㄥ鍏ュ垪琛ㄨ繑鍥炴垚鍔熺爜 `ResponseCode::SUCCESS`銆佸鍑哄叆鍙ｈ繑鍥? `text/csv`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 326 鑺傘??
- GREEN锛氳ˉ榻愭壒閲忎俊鐢ㄥ鍏ユ暟瀛楃瓫閫夊墠缃牎楠屽拰绗? 326 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminBatchCreditImportNumericFilterValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆佹祴璇曚笓鐢? `user_infos` 鍜? `credit_imports` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佷互鍙? `/api/admin/creditImportList`銆乣/api/admin/exportCreditImports` 涓や釜鍏ュ彛銆?
- 闈炰弗鏍肩瓫閫夊?? `user_id=鐪熷疄IDabc`銆乣credit_type=3abc` 鍜? `is_synced=2abc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍒楄〃鍝嶅簲涓嶅寘鍚祴璇曠敤鎴峰鍚嶅拰鎵规鍙凤紝瀵煎嚭鍏ュ彛涓嶅啀杈撳嚭 `text/csv`锛岄伩鍏? `credit_imports.user_id`銆乣credit_imports.credit_type` 涓? `credit_imports.is_synced` 鍦ㄥ弬鏁版牎楠屽墠琚暣鏁板己杞懡涓湡瀹炰俊鐢ㄥ鍏ヨ褰曘??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩淇＄敤瀵煎叆鏂板銆丆SV 瑙ｆ瀽銆佸け璐ラ噸璇曘?佹ā鏉夸笅杞姐?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 327. 2026-07-09 鍚庡彴鎵归噺鍏ラ噾/鍑洪噾瀵煎叆鏁板瓧绛涢?変弗鏍兼牎楠岄棴鐜?

### 鏈澶勭悊鐩爣
- 涓? `BatchAmountImportController::depositImportList`銆乣BatchAmountImportController::withdrawImportList`銆乣BatchAmountImportController::exportDepositImports` 鍜? `BatchAmountImportController::exportWithdrawImports` 琛ラ綈鎵归噺鍏ラ噾/鍑洪噾瀵煎叆 `user_id` 涓? `is_synced` 鏁板瓧绛涢?変弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/depositImportList` 鍜? `/api/admin/withdrawImportList` 涓嶈兘鎶? `user_id=鐪熷疄IDabc` 鎴? `is_synced=2abc` 鍦? PHP 灞? `(int)` 寮鸿浆鍚庝氦缁? `deposit_imports.user_id`銆乣withdraw_imports.user_id`銆乣deposit_imports.is_synced` 鎴? `withdraw_imports.is_synced` 鏌ヨ骞惰繑鍥炵湡瀹炲鍏ヨ褰曘??
- 楠岃瘉 `/api/admin/exportDepositImports` 涓? `/api/admin/exportWithdrawImports` 鏀跺埌闈炰弗鏍兼暟瀛楃瓫閫夋椂杩斿洖 `ResponseCode::VALIDATION_FAILED`锛屼笉杈撳嚭褰撳墠绛涢?夋潯浠朵笅鐨? CSV銆?

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminBatchAmountImportNumericFilterValidationClosureModuleTest.php`
  - 鏂板鎵归噺鍏ラ噾/鍑洪噾瀵煎叆鍒楄〃鍜屽鍑哄洓涓叆鍙ｇ殑闈炰弗鏍兼暟瀛楃瓫閫夎鎷掔粷鏍蜂緥锛屽苟娓呯悊娴嬭瘯涓撶敤 `deposit_imports`銆乣withdraw_imports` 涓? `user_infos` 鏁版嵁銆?
- `app/Http/Controllers/Admin/BatchAmountImportController.php`
  - 鍥涗釜鍒楄〃/瀵煎嚭鍏ュ彛鍦ㄦ瀯閫犲鍏ヨ褰曟煡璇㈠墠鏍￠獙 `user_id` 鍜? `is_synced`锛岄?氳繃鍚庢墠鍏佽 `applyCommonFilters()` 杞崲骞舵嫾鎺ユ煡璇㈡潯浠躲??

### TDD 鎵ц璁板綍
- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit --filter AdminBatchAmountImportNumericFilterValidationClosureModuleTest --colors=never` 棣栨杩愯澶辫触锛屽懡涓壒閲忓叆閲?/鍑洪噾瀵煎叆鍒楄〃杩斿洖鎴愬姛鐮? `ResponseCode::SUCCESS`銆佸鍑哄叆鍙ｈ繑鍥? `text/csv`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 327 鑺傘??
- GREEN锛氳ˉ榻愭壒閲忓叆閲?/鍑洪噾瀵煎叆鏁板瓧绛涢?夊墠缃牎楠屽拰绗? 327 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 褰撳墠璇佹嵁
- `AdminBatchAmountImportNumericFilterValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆佹祴璇曚笓鐢? `user_infos`銆乣deposit_imports` 鍜? `withdraw_imports` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佷互鍙? `/api/admin/depositImportList`銆乣/api/admin/withdrawImportList`銆乣/api/admin/exportDepositImports`銆乣/api/admin/exportWithdrawImports` 鍥涗釜鍏ュ彛銆?
- 闈炰弗鏍肩瓫閫夊?? `user_id=鐪熷疄IDabc` 鍜? `is_synced=2abc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍒楄〃鍝嶅簲涓嶅寘鍚祴璇曠敤鎴峰鍚嶅拰鎵规鍙凤紝瀵煎嚭鍏ュ彛涓嶅啀杈撳嚭 `text/csv`锛岄伩鍏嶅叆閲?/鍑洪噾瀵煎叆琛ㄧ殑 `user_id` 涓? `is_synced` 鍦ㄥ弬鏁版牎楠屽墠琚暣鏁板己杞懡涓湡瀹炶褰曘??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鍏ラ噾/鍑洪噾瀵煎叆鏂板銆丆SV 瑙ｆ瀽銆佸け璐ラ噸璇曘?佹ā鏉夸笅杞姐?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??
## 328. 2026-07-09 鍚庡彴瀹炴椂杩斾剑 user_id 绛涢?変弗鏍兼牎楠岄棴鐜?
### 鏈澶勭悊鐩爣
- 涓? `RealtimeCommissionController::realtimeCommissionList` 鍜? `RealtimeCommissionController::exportRealtimeCommissions` 琛ラ綈瀹炴椂杩斾剑 `user_id` 绛涢?変弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/realtimeCommissionList` 涓嶈兘鎶? `user_id=鐪熷疄IDabc` 鍦? PHP 灞? `(int)` 寮鸿浆鍚庝氦缁? `mt4_trades.login` 鏌ヨ骞惰繑鍥炵湡瀹炶繑浣ｈ褰曘??
- 楠岃瘉 `/api/admin/exportRealtimeCommissions` 鏀跺埌闈炰弗鏍? `user_id` 鏃惰繑鍥? `ResponseCode::VALIDATION_FAILED`锛屼笉杈撳嚭褰撳墠绛涢?夋潯浠朵笅鐨? CSV銆?
### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminRealtimeCommissionUserIdValidationClosureModuleTest.php`
  - 鏂板瀹炴椂杩斾剑鍒楄〃鍜屽鍑轰袱涓叆鍙ｇ殑闈炰弗鏍? `user_id` 绛涢?夎鎷掔粷鏍蜂緥锛屽苟娓呯悊娴嬭瘯涓撶敤 `mt4_trades` 涓? `user_infos` 鏁版嵁銆?
- `app/Http/Controllers/Admin/RealtimeCommissionController.php`
  - `realtimeCommissionList` 鍜? `exportRealtimeCommissions` 鍦ㄦ瀯閫? `mt4_trades` 鏌ヨ鍓嶆牎楠? `user_id`锛岄?氳繃鍚庢墠鍏佽 `applyFilters()` 杞崲骞舵嫾鎺? `mt4_trades.login` 鏌ヨ鏉′欢銆?
### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminRealtimeCommissionUserIdValidationClosureModuleTest.php --colors=never` 棣栨杩愯澶辫触锛屽懡涓疄鏃惰繑浣ｅ垪琛ㄨ繑鍥炴垚鍔熺爜 `ResponseCode::SUCCESS`銆佸鍑哄叆鍙ｈ繑鍥? `text/csv`锛屾渶缁堟竻鍗曚篃缂哄皯瀹炴椂杩斾剑涓ユ牸鏍￠獙绔犺妭銆?
- GREEN锛氳ˉ榻愬疄鏃惰繑浣? `user_id` 鍓嶇疆鏍￠獙鍜岀 328 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?
### 褰撳墠璇佹嵁
- `AdminRealtimeCommissionUserIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆佹祴璇曚笓鐢? `user_infos` 鍜? `mt4_trades` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佷互鍙? `/api/admin/realtimeCommissionList`銆乣/api/admin/exportRealtimeCommissions` 涓や釜鍏ュ彛銆?
- 闈炰弗鏍肩瓫閫夊?? `user_id=鐪熷疄IDabc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍒楄〃鍝嶅簲涓嶅寘鍚祴璇? MT4 ticket 鍜屾祴璇曠敤鎴峰鍚嶏紝瀵煎嚭鍏ュ彛涓嶅啀杈撳嚭 `text/csv`锛岄伩鍏? `mt4_trades.login` 鍦ㄥ弬鏁版牎楠屽墠琚暣鏁板己杞懡涓湡瀹炲疄鏃惰繑浣ｈ褰曘??
### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩瀹炴椂杩斾剑璇嗗埆瑙勫垯銆佽繑浣ｉ〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??
## 329. 2026-07-09 鍚庡彴浜у搧/浜ゆ槗鍝佺鏁板瓧绛涢?変弗鏍兼牎楠岄棴鐜?
### 鏈澶勭悊鐩爣
- 涓? `ProductionController::productionList` 鍜? `ProductionController::exportProductions` 琛ラ綈浜у搧/浜ゆ槗鍝佺 `group_id` 涓? `status` 鏁板瓧绛涢?変弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/productionList` 涓嶈兘鎶? `group_id=鐪熷疄鍒嗙粍abc` 鍦? PHP 灞? `(int)` 寮鸿浆鍚庝氦缁? `symbol_prices.group_id` 鏌ヨ骞惰繑鍥炵湡瀹炰氦鏄撳搧绉嶃??
- 楠岃瘉 `/api/admin/exportProductions` 鏀跺埌闈炰弗鏍? `status` 鏃惰繑鍥? `ResponseCode::VALIDATION_FAILED`锛屼笉杈撳嚭褰撳墠绛涢?夋潯浠朵笅鐨? CSV銆?
### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminProductionNumericFilterValidationClosureModuleTest.php`
  - 鏂板浜у搧/浜ゆ槗鍝佺鍒楄〃鍜屽鍑轰袱涓叆鍙ｇ殑闈炰弗鏍兼暟瀛楃瓫閫夎鎷掔粷鏍蜂緥锛屽苟娓呯悊娴嬭瘯涓撶敤 `symbol_prices` 鏁版嵁銆?
- `app/Http/Controllers/Admin/ProductionController.php`
  - `productionList` 鍜? `exportProductions` 鍦ㄦ瀯閫? `symbol_prices` 鏌ヨ鍓嶆牎楠? `group_id` 涓? `status`锛岄?氳繃鍚庢墠鍏佽 `applyFilters()` 杞崲骞舵嫾鎺? `symbol_prices.group_id`銆乣symbol_prices.status` 鏌ヨ鏉′欢銆?
### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminProductionNumericFilterValidationClosureModuleTest.php --colors=never` 棣栨杩愯澶辫触锛屽懡涓骇鍝佸垪琛ㄨ繑鍥炴垚鍔熺爜 `ResponseCode::SUCCESS`銆佸鍑哄叆鍙ｈ繑鍥? `text/csv`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 329 鑺傘??
- GREEN锛氳ˉ榻愪骇鍝?/浜ゆ槗鍝佺鏁板瓧绛涢?夊墠缃牎楠屽拰绗? 329 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?
### 褰撳墠璇佹嵁
- `AdminProductionNumericFilterValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆佹祴璇曚笓鐢? `symbol_prices` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佷互鍙? `/api/admin/productionList`銆乣/api/admin/exportProductions` 涓や釜鍏ュ彛銆?
- 闈炰弗鏍肩瓫閫夊?? `group_id=鐪熷疄鍒嗙粍abc` 鍜? `status=1abc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍒楄〃鍝嶅簲涓嶅寘鍚祴璇曚氦鏄撳搧绉嶄唬鐮侊紝瀵煎嚭鍏ュ彛涓嶅啀杈撳嚭 `text/csv`锛岄伩鍏? `symbol_prices.group_id` 涓? `symbol_prices.status` 鍦ㄥ弬鏁版牎楠屽墠琚暣鏁板己杞懡涓湡瀹炰骇鍝?/浜ゆ槗鍝佺璁板綍銆?
### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩浜у搧/浜ゆ槗鍝佺鍒涘缓銆佹洿鏂般?佸垹闄ゃ?佹寔浠撴眹鎬诲彛寰勩?佷骇鍝侀〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??
## 330. 2026-07-09 鍚庡彴绯荤粺閰嶇疆鏇存柊 id 涓ユ牸鏍￠獙闂幆
### 鏈澶勭悊鐩爣
- 涓? `SystemConfigController::update` 鍜? `SystemConfigController::updateSingleConfig` 琛ラ綈璇锋眰浣? `id` 涓ユ牸鏍￠獙娴嬭瘯銆?
- 楠岃瘉 `/api/admin/updateSystemConfig` 涓嶈兘鎶? `id=鐪熷疄IDabc` 浜ょ粰 `system_configs.id` 鏌ヨ鍚庤繑鍥為潪鍙傛暟閿欒鎴栬鍛戒腑鐪熷疄閰嶇疆銆?
- 楠岃瘉闈炰弗鏍? `id` 杩斿洖 `ResponseCode::VALIDATION_FAILED`锛屼笖涓嶄細鏇存柊鍘? `system_configs` 璁板綍銆?
### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminSystemConfigUpdateIdValidationClosureModuleTest.php`
  - 鏂板绯荤粺閰嶇疆鍗曡鏇存柊闈炰弗鏍? `id` 琚嫆缁濅笖涓嶆敼鍐欐祴璇曢厤缃?间笌鎻忚堪鐨勬牱渚嬶紝骞舵竻鐞嗘祴璇曚笓鐢? `system_configs` 鏁版嵁銆?
- `app/Http/Controllers/Admin/SystemConfigController.php`
  - `updateSingleConfig` 鍦ㄦ寜 `system_configs.id` 鏌ヨ鍓嶆牎楠岃姹備綋 `id`锛岄?氳繃鍚庢墠杞崲涓烘暣鏁板苟鎷兼帴涓婚敭鏌ヨ鏉′欢锛涙寜 `key` 鏇存柊鐨勫吋瀹硅矾寰勪繚鎸佷笉鍙樸??
### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminSystemConfigUpdateIdValidationClosureModuleTest.php --colors=never` 棣栨杩愯澶辫触锛岄潪涓ユ牸 `id` 杩斿洖 `ResponseCode::DATA_NOT_FOUND`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 330 鑺傘??
- GREEN锛氳ˉ榻愮郴缁熼厤缃洿鏂? `id` 鍓嶇疆鏍￠獙鍜岀 330 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?
### 褰撳墠璇佹嵁
- `AdminSystemConfigUpdateIdValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆佹祴璇曚笓鐢? `system_configs` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佷互鍙? `/api/admin/updateSystemConfig` 鍏ュ彛銆?
- 闈炰弗鏍艰姹備綋鍊? `id=鐪熷疄IDabc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 娴嬭瘯閰嶇疆鐨? `value` 鍜? `description` 淇濇寔鍘熷?硷紝閬垮厤 `system_configs.id` 鍦ㄥ弬鏁版牎楠屽墠杩涘叆鏌ヨ閾捐矾閫犳垚閿欒璇箟鎴栨綔鍦ㄨ鍛戒腑銆?
### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩绯荤粺閰嶇疆鍒楄〃銆佹壒閲? `configs[key]=value` 鏇存柊銆佹寜 `key` 鏇存柊鍏煎璺緞銆佺郴缁熼厤缃〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 331. 2026-07-09 鍚庡彴绀煎搧鍦板潃榛樿鏍囪绛涢?変弗鏍兼牎楠岄棴鐜?
### 鏈澶勭悊鐩爣
- 涓? `GiftController::addressList` 琛ラ綈绀煎搧鍦板潃 `is_default` 鏁板瓧绛涢?変弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/giftAddressList` 涓嶈兘鎶? `is_default=1abc` 鍦? PHP 灞? `(int)` 寮鸿浆鍚庝氦缁? `user_addresses.is_default` 鏌ヨ骞惰繑鍥炵湡瀹為粯璁ゅ湴鍧?銆?
- 楠岃瘉闈炰弗鏍? `is_default` 杩斿洖 `ResponseCode::VALIDATION_FAILED`锛岄伩鍏嶇ぜ鍝佸湴鍧?鍒楄〃鎸夋暟瀛楀墠缂?娉勯湶鐪熷疄鍙彂鏀惧湴鍧?璁板綍銆?
### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminGiftAddressDefaultFilterValidationClosureModuleTest.php`
  - 鏂板绀煎搧鍦板潃鍒楄〃闈炰弗鏍? `is_default` 绛涢?夎鎷掔粷涓斾笉杩斿洖娴嬭瘯鐢ㄦ埛濮撳悕涓庢敹浠朵汉鐨勬牱渚嬶紝骞舵竻鐞嗘祴璇曚笓鐢? `user_infos` 涓? `user_addresses` 鏁版嵁銆?
- `app/Http/Controllers/Admin/GiftController.php`
  - `addressList` 鍦ㄦ瀯閫? `user_addresses` 鏌ヨ鍓嶆牎楠? `is_default`锛岄?氳繃鍚庢墠鍏佽 `applyAddressFilters()` 杞崲骞舵嫾鎺? `user_addresses.is_default` 鏌ヨ鏉′欢銆?
### TDD 鎵ц璁板綍
- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit --filter AdminGiftAddressDefaultFilterValidationClosureModuleTest --colors=never` 棣栨杩愯澶辫触锛屽懡涓ぜ鍝佸湴鍧?鍒楄〃杩斿洖鎴愬姛鐮? `ResponseCode::SUCCESS`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 331 鑺傘??
- GREEN锛氳ˉ榻愮ぜ鍝佸湴鍧?榛樿鏍囪绛涢?夊墠缃牎楠屽拰绗? 331 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?
### 褰撳墠璇佹嵁
- `AdminGiftAddressDefaultFilterValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆佹祴璇曚笓鐢? `user_infos` 鍜? `user_addresses` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佷互鍙? `/api/admin/giftAddressList` 鍏ュ彛銆?
- 闈炰弗鏍肩瓫閫夊?? `is_default=1abc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍒楄〃鍝嶅簲涓嶅寘鍚祴璇曠敤鎴峰鍚嶄笌鏀朵欢浜猴紝閬垮厤 `user_addresses.is_default` 鍦ㄥ弬鏁版牎楠屽墠琚暣鏁板己杞懡涓湡瀹炵ぜ鍝佸湴鍧?銆?
### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩绀煎搧鍙戣揣鍒楄〃銆佸彂璐у鍑恒?佺ぜ鍝侀厤缃?佺ぜ鍝佸彂鏀惧姩浣溿?佺ぜ鍝侀〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??
## 332. 2026-07-09 鍚庡彴鏅?氱敤鎴峰垪琛? account_type 绛涢?変弗鏍兼牎楠岄棴鐜?
### 鏈澶勭悊鐩爣
- 涓? `AdminUserController::userList`銆乣AdminUserController::exportUsers` 鍜? `AdminUserController::filteredUserQuery` 琛ラ綈 `account_type` 绛涢?変弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/userList` 涓嶈兘鎶? `account_type=2abc` 浜ょ粰 `user_infos.account_type` 鏌ヨ鍚庤繑鍥炵湡瀹炴櫘閫氱敤鎴疯褰曘??
- 楠岃瘉 `/api/admin/exportUsers` 鏀跺埌闈炰弗鏍? `account_type` 鏃惰繑鍥? `ResponseCode::VALIDATION_FAILED`锛屼笉杈撳嚭褰撳墠绛涢?夋潯浠朵笅鐨? CSV銆?
### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminUserListExportAccountTypeValidationClosureModuleTest.php`
  - 鏂板鍚庡彴鏅?氱敤鎴峰垪琛ㄥ拰瀵煎嚭涓や釜鍏ュ彛鐨勯潪涓ユ牸 `account_type` 绛涢?夎鎷掔粷鏍蜂緥锛屽苟娓呯悊娴嬭瘯涓撶敤 `user_infos` 涓? `user_logins` 鏁版嵁銆?
- `app/Http/Controllers/Admin/AdminUserController.php`
  - `userList` 鍜? `exportUsers` 鍦ㄦ瀯閫? `user_infos` 鏌ヨ鍓嶆牎楠? `account_type`锛岄?氳繃鍚庢墠鍏佽 `filteredUserQuery()` 杞崲骞舵嫾鎺? `user_infos.account_type` 鏌ヨ鏉′欢銆?
### TDD 鎵ц璁板綍
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminUserListExportAccountTypeValidationClosureModuleTest.php --colors=never` 棣栨杩愯澶辫触锛屽懡涓敤鎴峰垪琛ㄨ繑鍥炴垚鍔熺爜 `ResponseCode::SUCCESS`銆佸鍑哄叆鍙ｈ繑鍥? `StreamedResponse`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 332 鑺傘??
- GREEN锛氳ˉ榻愬悗鍙版櫘閫氱敤鎴峰垪琛?/瀵煎嚭 `account_type` 鍓嶇疆鏍￠獙鍜岀 332 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?
### 褰撳墠璇佹嵁
- `AdminUserListExportAccountTypeValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆佹祴璇曚笓鐢? `user_infos` 涓? `user_logins` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佷互鍙? `/api/admin/userList`銆乣/api/admin/exportUsers` 涓や釜鍏ュ彛銆?
- 闈炰弗鏍肩瓫閫夊?? `account_type=2abc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
- 鍒楄〃鍝嶅簲涓嶅寘鍚祴璇曠敤鎴峰鍚嶏紝瀵煎嚭鍏ュ彛涓嶅啀杈撳嚭 CSV锛岄伩鍏? `user_infos.account_type` 鍦ㄥ弬鏁版牎楠屽墠杩涘叆鏌ヨ閾捐矾閫犳垚閿欒璇箟鎴栨綔鍦ㄨ鍛戒腑銆?
### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鐢ㄦ埛璇︽儏銆佽祫鏂欐洿鏂般?佽处鍙峰惎鍋溿?佸疄鍚嶈璇佸鏍搞?佺敤鎴峰垪琛ㄧ粺璁″彛寰勩?佺敤鎴烽〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 333. 2026-07-10 鍚庡彴鐢ㄦ埛缁勫吋瀹瑰垪琛ㄦ暟瀛楃瓫閫変弗鏍兼牎楠岄棴鐜?
### 鏈澶勭悊鐩爣
- 涓? `UserGroupController::index` 琛ラ綈鏃х敤鎴风粍鍏煎鍒楄〃 `group_type` 涓? `is_enabled` 鏁板瓧绛涢?変弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/userGroupList` 涓嶈兘鎶? `group_type=2abc` 鍦? PHP 灞? `(int)` 寮鸿浆鍚庝氦缁? `group_configs.category` 鏌ヨ銆?
- 楠岃瘉 `/api/admin/userGroupList` 涓嶈兘鎶? `is_enabled=1abc` 鍦? PHP 灞? `(int)` 寮鸿浆鍚庝氦缁? `group_configs.is_enabled` 鏌ヨ銆?
### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminUserGroupListNumericFilterValidationClosureModuleTest.php`
  - 鏂板鐢ㄦ埛缁勫吋瀹瑰垪琛ㄩ潪涓ユ牸 `group_type` 涓? `is_enabled` 绛涢?夎鎷掔粷鏍蜂緥锛屼笉渚濊禆鐪熷疄鏁版嵁搴撳す鍏凤紝绾︽潫鏃犳晥绛涢?夊繀椤诲湪鏌ヨ鍓嶈繑鍥炲弬鏁伴敊璇??
- `app/Http/Controllers/Admin/UserGroupController.php`
  - `index` 鍦ㄨ鍙? `group_configs` 鍓嶆牎楠? `group_type` 涓? `is_enabled`锛岄?氳繃鍚庢墠鍏佽鍒楄〃绛涢?夎浆鎹㈠苟鎷兼帴 `group_configs.category`銆乣group_configs.is_enabled` 鏌ヨ鏉′欢銆?
### TDD 鎵ц璁板綍
- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit --filter AdminUserGroupListNumericFilterValidationClosureModuleTest --colors=never` 棣栨杩愯澶辫触锛屽懡涓棤鏁堢瓫閫変粛杩涘叆 `group_configs` 鏌ヨ锛屼笖褰撳墠 MySQL 杩炴帴涓嶅彲鐢ㄦ椂鏆撮湶涓烘煡璇㈠紓甯革紱鏈?缁堟竻鍗曚篃缂哄皯绗? 333 鑺傘??
- GREEN锛氳ˉ榻愮敤鎴风粍鍏煎鍒楄〃鏁板瓧绛涢?夊墠缃牎楠屽拰绗? 333 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?
### 褰撳墠璇佹嵁
- `AdminUserGroupListNumericFilterValidationClosureModuleTest` 瑕嗙洊 `UserGroupController::index` 鐩存帴璋冪敤鍏ュ彛鍜? `/api/admin/userGroupList` 鍏煎璺緞璇箟銆?
- 闈炰弗鏍肩瓫閫夊?? `group_type=2abc` 涓? `is_enabled=1abc` 鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`銆?
- 鏃犳晥绛涢?夊搷搴斾笉鍐嶈Е鍙? `group_configs` 鏌ヨ锛岄伩鍏? `group_configs.category` 涓? `group_configs.is_enabled` 鍦ㄥ弬鏁版牎楠屽墠琚暣鏁板己杞懡涓湡瀹炵敤鎴风粍閰嶇疆銆?
### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鐢ㄦ埛缁勫垱寤恒?佹洿鏂般?佸垹闄ゃ?侀粯璁ょ粍鍞竴鎬с?佺粍鍒厤缃〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 褰撳墠 MySQL `127.0.0.1:3307` 鎷掔粷杩炴帴锛屾棤娉曡繍琛屼緷璧栫湡瀹? `group_configs` 澶瑰叿鐨? `AdminUserGroupCompatibilityTest`锛涙暟鎹簱鎭㈠鍚庨渶琛ヨ窇璇ュ吋瀹瑰洖褰掋??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 334. 2026-07-10 鍚庡彴鏈叆閲戝鎴? min_days 绛涢?変弗鏍兼牎楠岄棴鐜?
### 鏈澶勭悊鐩爣
- 涓? `FundFlowController::neverDepositUserList` 琛ラ綈鏈叆閲戝鎴峰垪琛? `min_days` 鏁板瓧绛涢?変弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/neverDepositUserList` 涓嶈兘鎶? `min_days=1abc` 鍦? PHP 灞? `(int)` 寮鸿浆涓? `1` 鍚庣户缁瓫閫? `user_infos.created_at`銆?
- 楠岃瘉闈炰弗鏍兼垨璐熸暟 `min_days` 鍦ㄦ瀯閫犳暟鎹簱鏌ヨ鍓嶈繑鍥? `ResponseCode::VALIDATION_FAILED`銆?
### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminNeverDepositUserListMinDaysValidationClosureModuleTest.php`
  - 鏀逛负鐩存帴璋冪敤鎺у埗鍣ㄧ殑鏃犳暟鎹簱澶瑰叿娴嬭瘯锛岀害鏉熸棤鏁? `min_days` 蹇呴』鍦ㄦ煡璇㈠墠杩斿洖鍙傛暟閿欒锛屽苟淇娓呭崟缂栧彿涓虹 334 椤广??
- `app/Http/Controllers/Admin/FundFlowController.php`
  - `neverDepositUserList` 鍦ㄦ瀯閫? `user_infos` 鏌ヨ鍓嶈皟鐢? `validateMinDaysFilter()`锛屼娇鐢? `integer|min:0` 鏍￠獙锛岄?氳繃鍚庢墠鍏佽 `applyNeverDepositUserFilters()` 杞崲骞舵嫾鎺ユ敞鍐屾椂闂存潯浠躲??
### TDD 鎵ц璁板綍
- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit --filter AdminNeverDepositUserListMinDaysValidationClosureModuleTest --colors=never` 棣栨涓氬姟杩愯澶辫触锛宍min_days=1abc` 杩斿洖鎴愬姛鐮? `ResponseCode::SUCCESS`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 334 鑺傘??
- GREEN锛氳ˉ榻? `min_days` 鍓嶇疆鏍￠獙鍜岀 334 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?
### 褰撳墠璇佹嵁
- `AdminNeverDepositUserListMinDaysValidationClosureModuleTest` 瑕嗙洊 `FundFlowController::neverDepositUserList` 鐩存帴璋冪敤鍏ュ彛鍜? `/api/admin/neverDepositUserList` 璺緞璇箟銆?
- 闈炰弗鏍肩瓫閫夊?? `min_days=1abc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`锛屼笖鏃犳晥绛涢?変笉鍐嶈Е鍙? `user_infos` 鏌ヨ銆?
- 褰撳墠娴嬭瘯涓嶄緷璧? MySQL 澶瑰叿锛屽彲鍦? `127.0.0.1:3307` 涓嶅彲鐢ㄦ椂鎸佺画楠岃瘉鍓嶇疆鏍￠獙杈圭晫銆?
### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鏈叆閲戝垽瀹氱姸鎬併?佺敤鎴锋暟鎹寖鍥淬?佸垪琛ㄥ垎椤点?侀〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 渚濊禆鐪熷疄鏈叆閲戝鎴锋暟鎹殑瀹屾暣妯″潡鍥炲綊浠嶉渶鍦? MySQL 鎭㈠鍚庤ˉ璺戙??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 335. 2026-07-10 鍚庡彴浠撲綅娓呴浂璁板綍 status 绛涢?変弗鏍兼牎楠岄棴鐜?
### 鏈澶勭悊鐩爣
- 涓? `AdminWhsExpZeroController::recordList` 琛ラ綈娓呴浂璁板綍 `status` 鏁板瓧鏋氫妇绛涢?変弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/whsExpZeroRecords` 涓嶈兘鎶? `status=1abc` 浜ょ粰 `whs_exp_zeros.status` 鏌ヨ骞惰繑鍥炵湡瀹炲緟澶勭悊璁板綍銆?
- 楠岃瘉娓呴浂璁板綍鐘舵?佸彧鍏佽 `1=寰呭鐞哷銆乣2=宸插畬鎴恅銆乣3=澶辫触`锛屽叾瀹冨?煎湪鏋勯?犳煡璇㈠墠杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminWhsExpZeroStatusFilterValidationClosureModuleTest.php`
  - 鏂板鎺у埗鍣ㄧ洿璋冪殑鏃犳暟鎹簱澶瑰叿娴嬭瘯锛岀害鏉熼潪涓ユ牸 `status` 蹇呴』鍦ㄦ煡璇㈠墠杩斿洖鍙傛暟閿欒銆?
- `app/Http/Controllers/Admin/AdminWhsExpZeroController.php`
  - `recordList` 鍦ㄦ瀯閫? `whs_exp_zeros` 鏌ヨ鍓嶈皟鐢? `validateRecordStatus()`锛屼娇鐢? `integer|in:1,2,3` 鏍￠獙鐘舵?佺瓫閫夈??
### TDD 鎵ц璁板綍
- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit --filter AdminWhsExpZeroStatusFilterValidationClosureModuleTest --colors=never` 棣栨杩愯澶辫触锛宍status=1abc` 杩斿洖鎴愬姛鐮? `ResponseCode::SUCCESS`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 335 鑺傘??
- GREEN锛氳ˉ榻愭竻闆惰褰曠姸鎬佸墠缃牎楠屽拰绗? 335 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?
### 褰撳墠璇佹嵁
- `AdminWhsExpZeroStatusFilterValidationClosureModuleTest` 瑕嗙洊 `AdminWhsExpZeroController::recordList` 鐩存帴璋冪敤鍏ュ彛鍜? `/api/admin/whsExpZeroRecords` 璺緞璇箟銆?
- 闈炰弗鏍肩瓫閫夊?? `status=1abc` 杩斿洖 `ResponseCode::VALIDATION_FAILED`锛屾棤鏁堢姸鎬佷笉鍐嶈繘鍏? `whs_exp_zeros` 鏌ヨ銆?
- 褰撳墠娴嬭瘯涓嶄緷璧? MySQL 澶瑰叿锛屽彲鍦ㄦ暟鎹簱涓嶅彲鐢ㄦ椂鎸佺画楠岃瘉鍓嶇疆鏍￠獙杈圭晫銆?
### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩娓呴浂鍊欓?夎瘑鍒?佷竴閿竻闆跺啓鍏ャ?佺姸鎬佸鐞嗘祦绋嬨?侀〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 渚濊禆鐪熷疄 `whs_exp_zeros` 璁板綍鐨勫畬鏁翠笟鍔″洖褰掍粛闇?鍦? MySQL 鎭㈠鍚庤ˉ璺戙??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 336. 2026-07-10 鍚庡彴鍑洪噾鍒楄〃 status 绛涢?変弗鏍兼牎楠岄棴鐜?
### 鏈澶勭悊鐩爣
- 涓? `WithdrawController::index` 琛ラ綈鍑洪噾鐢宠鍒楄〃 `status` 鏁板瓧鏋氫妇绛涢?変弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/withdrawList` 涓嶈兘鎶? `status=1abc` 浜ょ粰 `withdraw_records.status` 鏌ヨ骞惰繑鍥炵湡瀹炲鐞嗕腑璁板綍銆?
- 楠岃瘉鍑洪噾鐘舵?佸彧鍏佽 `0=寰呭鐞哷銆乣1=澶勭悊涓璥銆乣2=宸插畬鎴恅銆乣3=宸叉嫆缁濇垨澶辫触`锛岄潪涓ユ牸鍜岃秺鐣屽?煎湪鏋勯?犳煡璇㈠墠杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminWithdrawListStatusFilterValidationClosureModuleTest.php`
  - 鏂板鎺у埗鍣ㄧ洿璋冪殑鏃犳暟鎹簱澶瑰叿娴嬭瘯锛岃鐩? `status=1abc` 鍜岃秺鐣屽?? `status=4`銆?
- `app/Http/Controllers/Admin/WithdrawController.php`
  - `index` 鍦ㄦ瀯閫? `withdraw_records` 鏌ヨ鍓嶈皟鐢? `validateStatusFilter()`锛屼娇鐢? `integer|in:0,1,2,3` 鏍￠獙鐘舵?佺瓫閫夈??
### TDD 鎵ц璁板綍
- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit --filter AdminWithdrawListStatusFilterValidationClosureModuleTest --colors=never` 棣栨杩愯澶辫触锛岄潪娉曠姸鎬佽繑鍥炴垚鍔熺爜 `ResponseCode::SUCCESS`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 336 鑺傘??
- GREEN锛氳ˉ榻愬嚭閲戝垪琛ㄧ姸鎬佸墠缃牎楠屽拰绗? 336 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?
### 褰撳墠璇佹嵁
- `AdminWithdrawListStatusFilterValidationClosureModuleTest` 瑕嗙洊 `WithdrawController::index` 鐩存帴璋冪敤鍏ュ彛鍜? `/api/admin/withdrawList` 璺緞璇箟銆?
- 闈炰弗鏍肩瓫閫夊?? `status=1abc` 涓庤秺鐣屽?? `status=4` 鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`锛屾棤鏁堢姸鎬佷笉鍐嶈繘鍏? `withdraw_records` 鏌ヨ銆?
- 褰撳墠娴嬭瘯涓嶄緷璧? MySQL 澶瑰叿锛屽彲鍦ㄦ暟鎹簱涓嶅彲鐢ㄦ椂鎸佺画楠岃瘉鍓嶇疆鏍￠獙杈圭晫銆?
### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鍑洪噾璇︽儏銆佸鐞嗕腑/瀹屾垚/鎷掔粷鍔ㄤ綔銆佹暟鎹寖鍥淬?侀〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 渚濊禆鐪熷疄 `withdraw_records` 璁板綍鐨勫畬鏁翠笟鍔″洖褰掍粛闇?鍦? MySQL 鎭㈠鍚庤ˉ璺戙??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 337. 2026-07-10 鍚庡彴鍏ラ噾鍒楄〃鐘舵?佹槧灏勪笌涓ユ牸鏍￠獙闂幆
### 鏈澶勭悊鐩爣
- 涓? `DepositController::index` 琛ラ綈鍏ラ噾鍒楄〃 `status` 绛涢?夌殑鍏煎鏄犲皠涓庝弗鏍肩櫧鍚嶅崟鏍￠獙銆?
- 楠岃瘉鏃у悗鍙? Blade 椤甸潰鎻愪氦鐨? `0/1/2` 鑳藉垎鍒槧灏勫埌鐪熷疄 `deposit_records.status` 鐨? `01/02/09`銆?
- 楠岃瘉鏂板悗鍙伴厤缃洿鎺ユ彁浜? `01/02/09` 鏃朵繚鎸佸師鐘舵?佽涔夛紝闈炰弗鏍兼垨涓嶆敮鎸佺殑鐘舵?佸湪鏋勯?犳煡璇㈠墠杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminDepositListStatusFilterValidationClosureModuleTest.php`
  - 鏂板鐪熷疄鏁版嵁搴撳す鍏锋祴璇曪紝瑕嗙洊鏃ч〉闈㈢姸鎬佸?笺?佹暟鎹簱鍘熷鐘舵?佸?笺?侀潪涓ユ牸鍊间笌瓒婄晫鍊硷紝骞舵竻鐞嗘祴璇曚笓鐢ㄥ叆閲戣褰曘??
- `app/Http/Controllers/Admin/DepositController.php`
  - `index` 鍦ㄦ瀯閫? `deposit_records` 鏌ヨ鍓嶈皟鐢? `validateAndNormalizeStatusFilter()`锛岀粺涓?鏍￠獙骞跺綊涓?鍖栫姸鎬佸悗鍐嶆嫾鎺ユ煡璇㈡潯浠躲??
### TDD 鎵ц璁板綍
- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit tests\Feature\AdminDepositListStatusFilterValidationClosureModuleTest.php --colors=never` 棣栨杩愯鍑虹幇 3 涓鏈熷け璐ワ細`status=0` 鏈懡涓? `01` 寰呭鐞嗚褰曘?乣status=1abc` 杩斿洖鎴愬姛鐮併?佹渶缁堟竻鍗曠己灏戠 337 鑺傘??
- GREEN锛氳ˉ榻愮姸鎬佺櫧鍚嶅崟銆佸吋瀹规槧灏勫拰绗? 337 鑺傛竻鍗曞悗锛屾棫椤甸潰鍊间笌鏁版嵁搴撳師濮嬪?肩粺涓?鍛戒腑 `01/02/09`锛岄潪娉曞?煎湪鏌ヨ鍓嶈鎷掔粷銆?
### 褰撳墠璇佹嵁
- `AdminDepositListStatusFilterValidationClosureModuleTest` 瑕嗙洊鐪熷疄 `admins`銆佹祴璇曚笓鐢? `deposit_records` 琛ㄨ褰曘?佸悗鍙? admin guard 鐧诲綍鎬佷互鍙? `/api/admin/depositList` 鍏ュ彛銆?
- `0/1/2` 涓? `01/02/09` 涓ゅ杈撳叆鍧囧綊涓?鍖栦负鏁版嵁搴撶湡瀹炵姸鎬? `01/02/09`锛屼笖姣忔绛涢?夊彧杩斿洖瀵瑰簲鐘舵?佽褰曘??
- 闈炰弗鏍煎?? `1abc` 鍜屼笉鏀寔鍊? `03`銆乣9`銆乣-1` 鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`锛屾棤鏁堢姸鎬佷笉鍐嶈繘鍏ュ叆閲戝垪琛ㄦ煡璇€??
### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鍏ラ噾璇︽儏銆佸鏍搁?氳繃銆佸鏍搁┏鍥炪?佹暟鎹寖鍥淬?侀〉闈㈤?夐」銆佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 338. 2026-07-11 鍚庡彴娉ㄩ攢鐢宠鍒楄〃 status 绛涢?変弗鏍兼牎楠岄棴鐜?
### 鏈澶勭悊鐩爣
- 涓? `CancelApplyController::index` 琛ラ綈娉ㄩ攢鐢宠鍒楄〃 `status` 鏁板瓧鏋氫妇绛涢?変弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/cancelApplyList` 涓嶈兘鎶? `status=1abc` 浜ょ粰 `cancel_applies.status` 鏌ヨ骞惰繑鍥炵湡瀹炲凡閫氳繃璁板綍銆?
- 楠岃瘉娉ㄩ攢鐢宠鐘舵?佸彧鍏佽 `-1=宸叉嫆缁漙銆乣0=寰呭鐞哷銆乣1=宸查?氳繃`锛岄潪涓ユ牸鍜岃秺鐣屽?煎湪鏋勯?犳煡璇㈠墠杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminCancelApplyListStatusFilterValidationClosureModuleTest.php`
  - 鏂板鎺у埗鍣ㄧ洿璋冪殑鏃犳暟鎹簱澶瑰叿娴嬭瘯锛岃鐩? `status=1abc`銆乣status=2` 鍜? `status=-2`銆?
- `app/Http/Controllers/Admin/CancelApplyController.php`
  - `index` 鍦ㄦ瀯閫? `cancel_applies` 鏌ヨ鍓嶈皟鐢? `validateStatusFilter()`锛屼娇鐢? `integer|in:-1,0,1` 鏍￠獙鐘舵?佺瓫閫夈??
### TDD 鎵ц璁板綍
- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit --filter AdminCancelApplyListStatusFilterValidationClosureModuleTest --colors=never` 棣栨杩愯澶辫触锛岄潪娉曠姸鎬佽繑鍥炴垚鍔熺爜 `ResponseCode::SUCCESS`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 338 鑺傘??
- GREEN锛氳ˉ榻愭敞閿?鐢宠鍒楄〃鐘舵?佸墠缃牎楠屽拰绗? 338 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?
### 褰撳墠璇佹嵁
- `AdminCancelApplyListStatusFilterValidationClosureModuleTest` 瑕嗙洊 `CancelApplyController::index` 鐩存帴璋冪敤鍏ュ彛鍜? `/api/admin/cancelApplyList` 璺緞璇箟銆?
- 闈炰弗鏍肩瓫閫夊?? `status=1abc` 涓庤秺鐣屽?? `status=2`銆乣status=-2` 鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`锛屾棤鏁堢姸鎬佷笉鍐嶈繘鍏? `cancel_applies` 鏌ヨ銆?
- 褰撳墠娴嬭瘯涓嶄緷璧? MySQL 澶瑰叿锛屽彲鍦ㄦ暟鎹簱涓嶅彲鐢ㄦ椂鎸佺画楠岃瘉鍓嶇疆鏍￠獙杈圭晫銆?
### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩娉ㄩ攢鐢宠閫氳繃/鎷掔粷鍔ㄤ綔銆佺敤鎴疯蒋鍒犳祦绋嬨?佹搷浣滄棩蹇椼?侀〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 渚濊禆鐪熷疄 `cancel_applies` 璁板綍鐨勫畬鏁翠笟鍔″洖褰掍粛闇?鍦? MySQL 鎭㈠鍚庤ˉ璺戙??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 339. 2026-07-11 鍚庡彴鍑瘉瀹℃牳鍒楄〃 review_status 绛涢?変弗鏍兼牎楠岄棴鐜?
### 鏈澶勭悊鐩爣
- 涓? `VoucherController::index` 琛ラ綈鍑瘉瀹℃牳鍒楄〃 `review_status` 鏁板瓧鏋氫妇绛涢?変弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/voucherList` 涓嶈兘鎶? `review_status=1abc` 浜ょ粰 `voucher_infos.review_status` 鏌ヨ骞惰繑鍥炵湡瀹炲凡瀹℃牳璁板綍銆?
- 楠岃瘉鍑瘉瀹℃牳鐘舵?佸彧鍏佽 `0=寰呭鏍竊銆乣1=瀹℃牳閫氳繃`銆乣2=瀹℃牳鎷掔粷`锛岄潪涓ユ牸鍜岃秺鐣屽?煎湪鏋勯?犳煡璇㈠墠杩斿洖 `ResponseCode::VALIDATION_FAILED`銆?
### 鏈鍙樻洿鏂囦欢
- `tests/Feature/AdminVoucherListReviewStatusFilterValidationClosureModuleTest.php`
  - 鏂板鎺у埗鍣ㄧ洿璋冪殑鏃犳暟鎹簱澶瑰叿娴嬭瘯锛岃鐩? `review_status=1abc`銆乣review_status=3` 鍜? `review_status=-1`銆?
- `app/Http/Controllers/Admin/VoucherController.php`
  - `index` 鍦ㄦ瀯閫? `voucher_infos` 鏌ヨ鍓嶈皟鐢? `validateReviewStatusFilter()`锛屼娇鐢? `integer|in:0,1,2` 鏍￠獙瀹℃牳鐘舵?佺瓫閫夈??
### TDD 鎵ц璁板綍
- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit tests\Feature\AdminVoucherListReviewStatusFilterValidationClosureModuleTest.php --colors=never` 棣栨杩愯澶辫触锛岄潪娉曞鏍哥姸鎬佽繑鍥炴垚鍔熺爜 `ResponseCode::SUCCESS`锛屾渶缁堟竻鍗曚篃缂哄皯绗? 339 鑺傘??
- GREEN锛氳ˉ榻愬嚟璇佸鏍稿垪琛ㄧ姸鎬佸墠缃牎楠屽拰绗? 339 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?
### 褰撳墠璇佹嵁
- `AdminVoucherListReviewStatusFilterValidationClosureModuleTest` 瑕嗙洊 `VoucherController::index` 鐩存帴璋冪敤鍏ュ彛鍜? `/api/admin/voucherList` 璺緞璇箟銆?
- 闈炰弗鏍肩瓫閫夊?? `review_status=1abc` 涓庤秺鐣屽?? `review_status=3`銆乣review_status=-1` 鍧囪繑鍥? `ResponseCode::VALIDATION_FAILED`锛屾棤鏁堝鏍哥姸鎬佷笉鍐嶈繘鍏? `voucher_infos` 鏌ヨ銆?
- 褰撳墠娴嬭瘯涓嶄緷璧? MySQL 澶瑰叿锛屽彲鍦ㄦ暟鎹簱涓嶅彲鐢ㄦ椂鎸佺画楠岃瘉鍓嶇疆鏍￠獙杈圭晫銆?
### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩鍑瘉瀹℃牳閫氳繃銆佸鏍告嫆缁濄?佹嫆缁濆師鍥犱繚瀛樸?侀〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 渚濊禆鐪熷疄 `voucher_infos` 璁板綍鐨勫畬鏁翠笟鍔″洖褰掍粛闇?鍦? MySQL 鎭㈠鍚庤ˉ璺戙??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 340. 2026-07-11 鍓嶅彴鎵惧洖瀵嗙爜韬唤銆侀獙璇佺爜銆侀偖浠朵笌 MT4 鍚屾闂幆

### 鏈澶勭悊鐩爣
- 闂悎 `/api/front/auth/password/email-code`銆乣/api/front/auth/password/reset` 涓庢棫鍏煎 `user/check_user_info`銆乣user/forgetpswSendCode`銆乣user/forgetPasswordInfoVerification`銆乣user/change_password` 鐨勫畬鏁存壘鍥炲瘑鐮侀摼璺??
- 淇 `ForgotPasswordController::saveChangePassword` 鍦ㄩ獙璇佺爜涓虹┖鏃惰烦杩囨牎楠屻?佷粎鍑洰鏍? `userId` 鍜屾柊瀵嗙爜鍗冲彲閲嶇疆浠绘剰璐﹀彿鐨勯珮鍗辨紡娲炪??
- 淇 `userId=鐪熷疄IDabc` 鍦? PHP 鏁存暟寮鸿浆鎴栨暟鎹簱鏁板瓧鍓嶇紑瑙勫垯涓嬪懡涓湡瀹炶处鍙风殑闂銆?
- 鎭㈠鏃ч」鐩厛鍚屾 MT4 瀵嗙爜銆佹垚鍔熷悗鍐嶆洿鏂版湰鍦板瘑鐮佺殑涓氬姟杈圭晫锛汳T4 鍚敤涓斿悓姝ュけ璐ユ椂涓嶆洿鏂版湰鍦板瘑鐮併?佷笉娑堣垂楠岃瘉鐮併??

### 鏈鍙樻洿鏂囦欢
- `tests/Feature/FrontForgotPasswordSecurityClosureModuleTest.php`
  - 鏂板鐪熷疄 `user_logins` 澶瑰叿娴嬭瘯锛岃鐩栫┖楠岃瘉鐮併?佹暟瀛楀墠缂? ID銆佹棫 `userverfcode`銆佺‘璁ゅ瘑鐮併?佺鐢ㄨ处鍙枫?佸彂閫侀樁娈? ID/閭缁戝畾銆佺幇浠ｆ帴鍙ｃ?侀獙璇侀樁娈靛綊灞炲拰 MT4 澶辫触鍥炴粴銆?
- `app/Http/Controllers/Front/ForgotPasswordController.php`
  - `checkUserInfo`銆乣sendResetCode`銆乣forgetPasswordInfoVerification` 鍜? `saveChangePassword` 鍦ㄦ煡璇㈠墠涓ユ牸鏍￠獙鏃х敤鎴? ID銆?
  - `front_reset_code:{email}` 浠庡崟涓?瀛楃涓叉敼涓虹粦瀹? `user_id`銆佹爣鍑嗗寲 `email`銆乣code` 鐨勭粨鏋勫寲缂撳瓨銆?
  - 鍙戦?侀樁娈靛鍔? 60 绉掗偖绠?/IP 闄愭祦锛岄偖浠舵垚鍔熷悗鎵嶅啓鍏ラ獙璇佺爜缂撳瓨銆?
  - `saveChangePassword` 鍏煎鏃ц〃鍗? `userverfcode`銆乣againpassword`锛岃姹傞獙璇佺爜銆佺敤鎴枫?侀偖绠卞拰纭瀵嗙爜鍏ㄩ儴涓?鑷淬??
  - `resetPassword` 涓庢棫鍏ュ彛缁熶竴璇诲彇缁戝畾缂撳瓨锛涙垚鍔熸敼瀵嗗悗鍒犻櫎楠岃瘉鐮併??
  - `mt4.enabled=true` 鏃堕?氳繃 `Mt4ManagerApi::changePassword` 鍚屾 MT4锛涘け璐ヨ繑鍥? `ResponseCode::MT4_SYNC_FAILED` 鎴栨棫 `neterr`锛屼笉鍐欐湰鍦板瘑鐮併??
- `app/Mail/FrontResetPasswordCode.php`
  - 鏂板鍙祴璇曠殑鎵惧洖瀵嗙爜楠岃瘉鐮? Mailable锛屾浛浠ｆ棤娉曟柇瑷?鍙戦?佺粨鏋滅殑瑁搁偖浠惰皟鐢ㄣ??
- `resources/views/emails/front-reset-password-code.blade.php`
  - 鏂板鎵惧洖瀵嗙爜楠岃瘉鐮佺函鏂囨湰閭欢妯℃澘銆?
- `resources/lang/zh-CN/auth.php`銆乣resources/lang/en/auth.php`
  - 鏂板鎵惧洖瀵嗙爜楠岃瘉鐮侀偖浠舵爣棰樺拰姝ｆ枃澶氳瑷?閿??

### TDD 鎵ц璁板綍
- RED锛氶娆¤繍琛? `FrontForgotPasswordSecurityClosureModuleTest` 涓? `4 failures / 5 tests`锛岀┖楠岃瘉鐮併?佹暟瀛楀墠缂? ID銆佺‘璁ゅ瘑鐮佷笉涓?鑷村拰绂佺敤璐﹀彿鍧囬敊璇繑鍥? `SUC`銆?
- RED锛氳ˉ鍏呭彂閫佷笌楠岃瘉闃舵娴嬭瘯鍚庝负 `8 failures / 9 tests`锛岃瘉瀹炲綋鍓嶉獙璇佺爜缂撳瓨鏄瓧绗︿覆銆佹湭鍙戦?佸彲娴嬭瘯閭欢銆両D/閭涓嶇粦瀹氾紝缁撴瀯鍖栫紦瀛樿繕瑙﹀彂鏁扮粍杞瓧绗︿覆 500銆?
- RED锛氳ˉ鍏? MT4 澶辫触鍥炴粴娴嬭瘯鍚庯紝褰撳墠浠ｇ爜浠嶈繑鍥? `SUC` 骞舵洿鏂版湰鍦板瘑鐮併??
- GREEN锛氳ˉ榻愮粨鏋勫寲楠岃瘉鐮併?侀偖浠躲?佷弗鏍? ID銆佺‘璁ゅ瘑鐮併?佽处鍙风姸鎬佸拰 MT4 鍚屾閫昏緫鍚庯紝鐩爣娴嬭瘯閫氳繃銆?

### 褰撳墠璇佹嵁
- `FrontForgotPasswordSecurityClosureModuleTest` 褰撳墠瑕嗙洊 11 涓祴璇曞満鏅紱鏍稿績涓氬姟娴嬭瘯鍦ㄥ啓鍏ユ湰鑺傚墠涓? `OK (10 tests, 49 assertions)`銆?
- 缂哄け楠岃瘉鐮併?侀敊璇獙璇佺爜銆侀潪涓ユ牸 ID銆佺‘璁ゅ瘑鐮佷笉涓?鑷村拰绂佺敤璐﹀彿鍧囦笉鑳戒慨鏀? `user_logins.password`銆?
- 鏃ч〉闈㈢幇鏈夊瓧娈? `userverfcode` 涓? `againpassword` 鍙甯稿畬鎴愰噸缃紝鎴愬姛鍚? `front_reset_code:{email}` 琚垹闄わ紝楠岃瘉鐮佷笉鍙噸澶嶄娇鐢ㄣ??
- 鏃у彂閫佸叆鍙ｅ彧鏈夊湪涓ユ牸鐢ㄦ埛 ID銆侀偖绠卞拰鍚敤鐘舵?佷竴鑷存椂鎵嶅彂閫? `FrontResetPasswordCode`锛涚幇浠ｅ彂閫佸叆鍙ｆ棤闇?鐢ㄦ埛 ID锛屼絾缂撳瓨浠嶇粦瀹氭暟鎹簱鐪熷疄鐢ㄦ埛銆?
- MT4 鍏抽棴琛ㄧず鏄庣‘鐨勬湰鍦版ā寮忥紱MT4 寮?鍚椂澶栭儴鍚屾澶辫触浼氫繚鐣欐湰鍦版棫瀵嗙爜鍜岄獙璇佺爜锛屼究浜庣敤鎴烽噸璇曘??

### 鍓╀綑杈圭晫
- 鏈疆娌℃湁鏀瑰姩宸茬櫥褰曠敤鎴蜂富鍔ㄦ敼瀵嗐?佸ぇ浠ｇ悊鏀瑰瘑銆佸悗鍙扮鐞嗗憳閲嶇疆鐢ㄦ埛瀵嗙爜銆侀偖浠舵湇鍔″櫒閰嶇疆鎴? MT4 Socket 鍗忚銆?
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鏅?氱敤鎴峰叾瀹冨叕寮?韬唤鍏ュ彛锛屼互鍙婂凡鐧诲綍鐢ㄦ埛涓诲姩鏀瑰瘑鐨? MT4 鍚屾涓?鑷存?с??

## 341. 2026-07-11 鍓嶅彴宸茬櫥褰曚富鍔ㄦ敼瀵? MT4銆佹湰鍦板瘑鐮佷笌浼氳瘽澶辨晥闂幆

### 鏈澶勭悊鐩爣
- 缁熶竴 `/api/front/profile/password`銆乣user/editpsw_save`銆乣user/agents/editpsw_save` 浠ュ強鎵惧洖瀵嗙爜鍏ュ彛鐨? MT4 涓庢湰鍦板瘑鐮佸啓鍏ラ『搴忋??
- 淇 Profile 鏂版棫涓诲姩鏀瑰瘑鍏ュ彛浠呮洿鏂版湰鍦板搱甯屻?丮T4 鍚屾澶辫触浠嶈繑鍥炴垚鍔熺殑闂銆?
- 鏀瑰瘑鎴愬姛鍚庝娇褰撳墠 `jwt_token` 澶辨晥銆侀??鍑? `user` guard锛屽苟鍦ㄦ棫 web 浼氳瘽瀛樺湪鏃跺垹闄? `suser`锛岄伩鍏嶆棫鐧诲綍鎬佺户缁闂??

### 鏈鍙樻洿鏂囦欢
- `app/Services/UserPasswordService.php`
  - 鏂板鍞竴瀵嗙爜鍐欏叆鍏ュ彛 `change(UserLogin $login, string $newPassword): bool`銆?
  - `mt4.enabled=false` 鏃惰繘鍏ユ槑纭湰鍦版ā寮忥紱鍚敤鏃朵粎鍦? `Mt4ManagerApi::changePassword` 杩斿洖 `status=ok` 鍚庡啓鍏ユ湰鍦? Hash锛屽け璐ヤ繚鐣欐棫鍝堝笇銆?
- `app/Http/Controllers/Front/ProfileController.php`
  - 鐜颁唬涓庢棫涓诲姩鏀瑰瘑鍏ュ彛缁熶竴璋冪敤 `UserPasswordService`锛屽垎鍒繚鐣? `ResponseCode::MT4_SYNC_FAILED` 涓庢棫 `msg=FAIL, err=neterr` 鍗忚銆?
  - 鎴愬姛鍚庣粺涓?澶辨晥璇锋眰灞炴?т腑鐨? `jwt_token`銆侀??鍑? `user` guard锛涗粎瀵瑰疄闄呮寕杞? session 鐨勬棫 web 璇锋眰鍒犻櫎 `suser`锛孉PI 璇锋眰涓嶄細鍥犵己灏? session store 杩斿洖 500銆?
- `app/Http/Controllers/Front/AuthController.php`
  - 绉婚櫎鐩存帴鏇存柊瀵嗙爜鍝堝笇鐨勯噸澶嶈矾寰勶紝鏀圭敤 `UserPasswordService` 骞朵繚鐣欏師 JWT 澶辨晥鍝嶅簲閾俱??
- `app/Http/Controllers/Front/ForgotPasswordController.php`
  - 绉婚櫎閲嶅鐨? `syncMt4Password()` 涓庣洿鎺? Hash 鍐欏叆锛岀幇浠ｅ拰鏃ф壘鍥炲瘑鐮佸叆鍙ｅ鐢ㄥ悓涓?鏈嶅姟锛涢獙璇佺爜鍙湪瀹屾暣鏀瑰瘑鎴愬姛鍚庢秷璐广??
- `tests/Feature/UserPasswordServiceTest.php`
  - 瑕嗙洊鏈湴妯″紡銆丮T4 鎴愬姛鍚庡啓鏈湴銆丮T4 澶辫触淇濈暀鏃у瘑鐮佷笁涓湇鍔¤竟鐣屻??
- `tests/Feature/FrontProfilePasswordOwnerBoundaryClosureModuleTest.php`
  - 瑕嗙洊浼?犵洰鏍囩敤鎴? ID 涓嶈秺鏉冦?佺幇浠ｅ拰鏃у叆鍙? MT4 澶辫触鍥炴粴銆丣WT 榛戝悕鍗曘?丼SO 娓呯悊銆乬uard 鐧诲嚭涓? `suser` 鍒犻櫎銆?
- `tests/Feature/FrontProfileRelationshipScopeFallbackModuleTest.php`
  - 鎵嬪伐鏋勯?? Profile Controller 鏀逛负瀹瑰櫒瑙ｆ瀽锛屽尮閰嶇敓浜т緷璧栨敞鍏ャ??

### TDD 鎵ц璁板綍
- RED锛歚UserPasswordServiceTest` 棣栨杩愯涓? `3 failures / 3 tests`锛屽潎鏄庣‘鎶ュ憡鏈嶅姟缂哄け銆?
- GREEN锛氭柊澧炴湇鍔″悗涓? `OK (3 tests, 12 assertions)`銆?
- RED锛歅rofile 鐨勭幇浠? MT4 澶辫触鍏ュ彛浠嶈繑鍥? UPDATED锛屾棫鍏ュ彛浠嶈繑鍥? SUCCESS锛涗袱涓敤渚嬪潎璇佹槑鏈湴鐩村啓缁曡繃 MT4銆?
- GREEN锛歅rofile 鍒囨崲缁熶竴鏈嶅姟鍚庯紝MT4 澶辫触娴嬭瘯涓? `OK (2 tests, 10 assertions)`銆?
- RED锛氫細璇濇祴璇曡瘉鏄庣幇浠? token 鏈繘鍏ラ粦鍚嶅崟锛屾棫鍏ュ彛鎴愬姛鍚? `suser` 浠嶅瓨鍦紱棣栨瀹炵幇杩樻毚闇? API 璇锋眰娌℃湁 session store 鐨? 500 杈圭晫銆?
- GREEN锛氭寜 API/web 浼氳瘽杞戒綋宸紓澶勭悊鍚庯紝浼氳瘽澶辨晥娴嬭瘯涓? `OK (2 tests, 9 assertions)`銆?

### 褰撳墠璇佹嵁
- `FrontProfilePasswordOwnerBoundaryClosureModuleTest`锛歚OK (7 tests, 37 assertions)`锛涘啓鍏ユ湰鑺傚墠棰濆鍔犲叆绗? 341 鑺傛枃妗ｅ绾︺??
- `FrontForgotPasswordSecurityClosureModuleTest`锛歚OK (11 tests, 55 assertions)`銆?
- `FrontProfileControllerCommentReadabilityTest`锛歚OK (2 tests, 51 assertions)`銆?
- `FrontForgotPasswordControllerCommentReadabilityTest`锛歚OK (3 tests, 37 assertions)`銆?
- `FrontProfileRelationshipScopeFallbackModuleTest`锛歚OK (2 tests, 6 assertions)`銆?
- `FrontUiRegressionTest`锛歚OK (137 tests, 3089 assertions)`銆?
- `UserPasswordService.php`銆丳rofile銆丄uth銆丗orgot 鍥涗釜 PHP 鏂囦欢鍧囬?氳繃 `php -l`銆?

### 鍓╀綑杈圭晫
- 鏈妭宸插畬鎴愭櫘閫氱敤鎴蜂富鍔ㄦ敼瀵嗙殑瀵嗙爜涓?鑷存?у拰浼氳瘽澶辨晥闂幆锛屼絾涓嶄唬琛ㄦ櫘閫氱敤鎴枫?佷唬鐞嗗晢銆佸悗鍙扮鐞嗗憳涓夌閫愯矾鐢卞璁″凡缁忓叏閮ㄥ畬鎴愩??
- 鍚庣画缁х画瀹¤鏅?氱敤鎴? Account銆丏eposit銆丏ashboard銆丟ift 涓庡叕寮? Auth/Register 杈撳叆杈圭晫锛屽啀杩涘叆浠ｇ悊鍟嗗拰鍚庡彴绠＄悊鍛樻ā鍧椼??

## 342. 2026-07-11 鍓嶅彴璐︽埛绫诲瀷涓庡嚟璇佸鏍哥姸鎬佷弗鏍艰緭鍏ラ棴鐜?

### 鏈澶勭悊鐩爣
- 瀵圭収鏃? `UserCenterController::change_account_save`銆乣UserVoucherController::voucherSearch` 涓庢柊 `AccountController` 鐨勫疄闄呰矾鐢辨墽琛岄摼銆?
- 闃绘 `review_status=1abc` 琚暟鎹簱鏁板瓧鍓嶇紑瑙勫垯鍛戒腑鐪熷疄 `voucher_infos.review_status=1` 璁板綍銆?
- 闃绘 `is_enc/is_ecn=1abc` 琚? PHP `(int)` 寮鸿浆鎴? ECN 鐘舵?侊紝浠ュ強瓒婄晫鍊煎啓鍏? `user_infos.is_ecn`銆?

### 璺敱鎵ц閾?
- `GET /api/front/account/vouchers` 鎴? `POST user/voucher/voucherSearch` 鈫? 褰撳墠 user guard/鏃? `suser` 瑙ｆ瀽 鈫? `AccountController::voucherList` 鈫? `review_status` 鐨? `integer|in:0,1,2` 鍓嶇疆鏍￠獙 鈫? 浠呮煡璇㈠綋鍓嶇敤鎴? `voucher_infos` 鈫? 鏃ユ湡杩囨护銆佸垎椤典笌鏃у瓧娈垫槧灏? 鈫? JSON 鍝嶅簲銆?
- `POST user/change_account_save` 鈫? 褰撳墠 user guard/鏃? `suser` 瑙ｆ瀽 鈫? `AccountController::changeAccountSave` 鈫? `is_enc/is_ecn` 褰掍竴鍖? 鈫? `required|integer|in:0,1` 鍓嶇疆鏍￠獙 鈫? 褰撳墠鐢ㄦ埛鏈钩浠撴鏌? 鈫? 鏇存柊褰撳墠 `user_infos.is_ecn/leverage` 鈫? 鏃? `msg/err/col` 鍝嶅簲銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/AccountController.php`
  - `voucherList` 鍦ㄦ瀯閫犳煡璇㈠墠鏍￠獙瀹℃牳鐘舵?侊紝鍙厑璁? 0銆?1銆?2銆?
  - `changeAccountSave` 鍦ㄤ换浣曟暣鏁板己杞笌鎸佷粨鏌ヨ鍓嶆牎楠岃处鎴风被鍨嬶紝鍙厑璁? 0銆?1锛涢潪娉曞?艰繑鍥炴棫椤甸潰鍙瘑鍒殑 `FAIL/UPDATEFAIL/is_enc`銆?
- `tests/Feature/FrontAccountVoucherOwnerBoundaryClosureModuleTest.php`
  - 鏂板 `1abc`銆?3銆?-1 涓夌被瀹℃牳鐘舵?佽鎷掔粷涓斾笉娉勯湶鐪熷疄鍑瘉澶囨敞鐨勬祴璇曘??
- `tests/Feature/FrontAccountProfileOwnerBoundaryClosureModuleTest.php`
  - 鏂板 `1abc`銆?2銆?-1銆佺┖鍊煎洓绫昏处鎴风被鍨嬭鎷掔粷涓旀湰鍦? `is_ecn/leverage` 淇濇寔涓嶅彉鐨勬祴璇曘??

### TDD 鎵ц璁板綍
- RED锛氬嚟璇佸垪琛ㄩ潪娉曠姸鎬佷粛杩斿洖 `ResponseCode::SUCCESS`锛涜处鎴风被鍨? `1abc` 浠嶈繑鍥? `SUCCESS` 骞跺彲琚己杞啓搴撱??
- GREEN锛氬鍔犳煡璇㈠墠鐧藉悕鍗曟牎楠屽悗锛宍FrontAccountVoucherOwnerBoundaryClosureModuleTest` 涓? `OK (5 tests, 36 assertions)`锛宍FrontAccountProfileOwnerBoundaryClosureModuleTest` 涓? `OK (5 tests, 48 assertions)`銆?
- 鍥炲綊锛歚FrontAccountControllerCommentReadabilityTest` 涓? `OK (2 tests, 32 assertions)`銆?

### 鍓╀綑杈圭晫
- 鏈妭鍙棴鍚堣緭鍏ュ悎娉曟?у拰褰撳墠鐢ㄦ埛杈圭晫锛涙棫椤圭洰璐︽埛绫诲瀷鍒囨崲杩樺寘鍚粍鍒槧灏勩?丮T4 change-group/change-leverage銆佸閮ㄥけ璐ュ洖婊氬拰 ECN 鏈?浣庢潈鐩婄瓑娣卞眰閾捐矾锛屼粛闇?缁х画閫愰」杩佺Щ楠岃瘉銆?
- 鍑瘉涓婁紶鏂囦欢鐢熷懡鍛ㄦ湡銆佽蒋鍒犻櫎绛涢?夊拰鍒楄〃鍒嗛〉涓婇檺浠嶅湪鍚庣画 Account 瀹¤鑼冨洿鍐呫??

## 343. 2026-07-11 鍓嶅彴鍏ラ噾鍘嗗彶鐘舵?佺瓫閫変笌澶辫触鐘舵?佸睍绀洪棴鐜?

### 鏈澶勭悊鐩爣
- 瀵圭収 `/api/front/deposits/history`銆佹棫鍏ラ噾鍘嗗彶璋冪敤涓? `deposit_records.status` 褰撳墠 schema锛岄棴鍚堢姸鎬佺瓫閫夊拰鐘舵?佹枃妗堛??
- 闃绘 `status=01abc` 绛夐潪涓ユ牸鍊艰繘鍏ユ暟鎹簱鏌ヨ锛涘彧鍏佽 schema 瀹氫箟鐨? `01/02/05/09/10`銆?
- 淇鐪熷疄澶辫触鐘舵?? `09` 琚墠鍙版樉绀轰负鈥滄湭鏀粯鈥濈殑閿欒鏄犲皠銆?

### 璺敱鎵ц閾?
- `GET /api/front/deposits/history` 鈫? JWT/SSO 鈫? `DepositController::depositHistory` 鈫? 褰撳墠鐢ㄦ埛瑙ｆ瀽 鈫? status 涓ユ牸鐧藉悕鍗? 鈫? 褰撳墠鐢ㄦ埛 `deposit_records` 鏌ヨ 鈫? 鏃ユ湡杩囨护涓庡悎璁? 鈫? `FrontLegacyData::depositStatusText` 鈫? 鍒嗛〉 JSON銆?
- 鏃ч〉闈㈠吋瀹? records 璋冪敤澶嶇敤鍚屼竴 `depositHistory` 鏂规硶锛屽洜姝や娇鐢ㄧ浉鍚岀姸鎬佽涔夊拰鎵?鏈夎?呰竟鐣屻??

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/DepositController.php`
  - 鍦ㄦ瀯閫犲巻鍙叉煡璇㈠墠涓ユ牸鏍￠獙 status锛屽彧鍏佽 `01/02/05/09/10`銆?
- `app/Support/FrontLegacyData.php`
  - `depositStatusText` 灏嗘暟鎹簱鐪熷疄澶辫触鐘舵?? `09` 鏄犲皠涓? `front.status_rejected`锛屽悓鏃朵繚鐣欏巻鍙? `03/3` 鏂囨湰鍏煎銆?
- `tests/Feature/FrontDepositOwnerBoundaryClosureModuleTest.php`
  - 鏂板闈炰弗鏍?/涓嶆敮鎸佺姸鎬佷笉鏌ヨ璁板綍锛屼互鍙? `09` 杩斿洖鎷掔粷鏂囨鐨勭湡瀹炶矾鐢辨祴璇曘??

### TDD 鎵ц璁板綍
- RED锛氶潪娉曠姸鎬佷粛杩斿洖 `ResponseCode::SUCCESS`锛沗status=09` 鐨? `status_text` 瀹為檯涓? `Unpaid`銆?
- GREEN锛氫弗鏍肩櫧鍚嶅崟涓庣姸鎬佹槧灏勫畬鎴愬悗锛岀洰鏍囨祴璇曚负 `OK (6 tests, 40 assertions)`锛涘啓鍏ユ湰鑺傚墠鍔犲叆绗? 343 鑺傛枃妗ｅ绾︺??
- 鍥炲綊锛歚FrontDepositControllerCommentReadabilityTest` 涓? `OK (2 tests, 36 assertions)`銆?

### 鍓╀綑杈圭晫
- `05=閫?娆綻銆乣10=瓒呮椂` 鐩墠鍏佽鎸? schema 绛涢?夛紝浣嗗墠鍙板皻鏃犵嫭绔嬮??娆?/瓒呮椂澶氳瑷?鏂囨锛屼粛闇?缁撳悎鏃ч〉闈骇鍝佽涔夊悗琛ラ綈銆?
- 鍏ラ噾鎻愪氦鐨勯噸澶嶇偣鍑诲箓绛夈?佹敮浠樼綉鍏崇鍚嶄笌鍥炶皟骞傜瓑灞炰簬鍚庣画 Payment/Deposit 娣遍摼瀹¤鑼冨洿銆?

## 344. 2026-07-11 鍓嶅彴鐑偣鏂伴椈鍒嗛〉鍙傛暟涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 瀵圭収鏃? `LoginController::hotNews/hotNewsV2`銆佹敞鍐岄〉鐑偣鏂伴椈鍏ュ彛涓庢柊 `DashboardController`锛岄棴鍚堝叕寮?鍒嗛〉鍙傛暟銆?
- 闃绘 `page=1abc`銆乣limit=10abc` 琚? PHP 鏁存暟寮鸿浆鍚庣户缁煡璇? `news`銆?
- 闃绘 page 灏忎簬 1銆乴imit 涓嶅湪 1 鍒? 50 鑼冨洿鍐呯殑璇锋眰琚潤榛樺す鍊间负鍚堟硶鍒嗛〉銆?

### 璺敱鎵ц閾?
- `POST user/main/hot/news` 鈫? `DashboardController::hotNews` 鈫? page `sometimes|integer|min:1` 鈫? published news 鏌ヨ 鈫? 褰撳墠璇█鏍囬 鈫? 鏃? HTML 鍒楄〃 JSON銆?
- `POST user/main/hot/newsV2` 鎴? `GET user/register/hotnews` 鈫? `DashboardController::hotNewsV2` 鈫? page/limit 鍓嶇疆鏍￠獙 鈫? published news 鍒嗛〉 鈫? 褰撳墠璇█鏍囬涓庤鎯呴摼鎺? 鈫? 鏃ц〃鏍? JSON銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/DashboardController.php`
  - 涓や釜鍏紑鏂伴椈鍏ュ彛鍦ㄦ暟鎹簱鏌ヨ鍓嶆牎楠屽垎椤碉紱缂虹渷 page=1銆乴imit=10 淇濇寔涓嶅彉銆?
- `tests/Feature/FrontDashboardPaginationValidationClosureModuleTest.php`
  - 瑕嗙洊闈炰弗鏍? page/limit銆侀浂鍊笺?佽礋鍊煎拰 limit=51锛涙棤鏁堣姹傚繀椤昏繑鍥? `ResponseCode::VALIDATION_FAILED`銆?

### TDD 鎵ц璁板綍
- RED锛氫袱涓叆鍙ｅ潎鎶婇潪娉曞垎椤佃繑鍥炰负 `code=0` 鎴愬姛銆?
- GREEN锛氬墠缃牎楠屽畬鎴愬悗锛屼笟鍔＄敤渚嬩负 `OK (2 tests, 14 assertions)`銆?

### 鍓╀綑杈圭晫
- 鏂伴椈 locale 鐧藉悕鍗曘?佹柊闂昏鎯? ID 涓ユ牸鏍￠獙鍜? `frontMsg` 瀹為檯娑堟伅鏁版嵁杩佺Щ浠嶅湪 Dashboard/News 鍚庣画瀹¤鑼冨洿銆?

## 345. 2026-07-11 鍓嶅彴绀煎搧鍦板潃榛樿绛涢?変笌绀煎搧绉垎涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 瀵圭収鐜颁唬 `/api/front/gift-addresses`銆乣/api/front/gifts` 涓庢棫鍦板潃/绀煎搧鎼滅储鍏ュ彛锛岄棴鍚堟暟瀛楃瓫閫夎涔夈??
- 淇鐜颁唬鍦板潃璺敱瀹為檯鎵ц `GiftController::addressSearch`锛岃?? `is_default` 鏍￠獙浠呭啓鍦ㄦ湭琚璺敱璋冪敤鐨? `addressList` 涓殑闂銆?
- 闃绘 `is_default=1abc` 涓? `points_cost=100abc` 缁忓己杞悗鍛戒腑鐪熷疄鍦板潃鎴栫ぜ鍝併??

### 璺敱鎵ц閾?
- `GET /api/front/gift-addresses` 鎴? `POST user/address/search` 鈫? 褰撳墠鐢ㄦ埛瑙ｆ瀽 鈫? `GiftController::addressSearch` 鈫? `is_default` 甯冨皵鍓嶇疆鏍￠獙涓庣瓫閫? 鈫? 褰撳墠鐢ㄦ埛 `user_addresses` 鍒嗛〉 鈫? 鏃у瓧娈垫槧灏? 鈫? JSON銆?
- `GET /api/front/gifts` 鈫? JWT/SSO 鈫? `GiftController::giftList` 鈫? `points_cost` 闈炶礋鏁存暟鍓嶇疆鏍￠獙 鈫? 褰撳墠鐢ㄦ埛鍙戣揣璁板綍鏌ヨ + 鍙敤 `gift_items` 鏌ヨ 鈫? 缁勫悎 JSON銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/GiftController.php`
  - `addressSearch` 澧炲姞鐪熷疄璺敱鎵?闇?鐨? `is_default` 鏍￠獙涓庢煡璇㈡潯浠讹紱`addressList` 淇濈暀鍚屾牱鏍￠獙浠ヤ繚璇佺洿鎺ヨ皟鐢ㄤ竴鑷淬??
  - `giftList` 鍦ㄤ换浣曞彂璐ф垨绀煎搧鏌ヨ鍓嶆牎楠? `points_cost`銆?
- `tests/Feature/FrontGiftAddressOwnerBoundaryClosureModuleTest.php`
  - 瑕嗙洊闈炰弗鏍奸粯璁ょ姸鎬佽鎷掔粷锛屽苟璇佹槑鍚堟硶 `is_default=1` 鍙繑鍥為粯璁ゅ湴鍧?銆?
- `tests/Feature/FrontGiftShipmentOwnerBoundaryClosureModuleTest.php`
  - 瑕嗙洊闈炰弗鏍肩Н鍒嗘垚鏈笉鑳借繑鍥炵湡瀹炲彲鐢ㄧぜ鍝併??

### TDD 鎵ц璁板綍
- RED锛氫袱涓潪涓ユ牸绛涢?夊潎杩斿洖 `ResponseCode::SUCCESS`锛涘悎娉? `is_default=1` 瀹為檯杩斿洖榛樿涓庢櫘閫氫袱鏉″湴鍧?銆?
- GREEN锛氱湡瀹炴墽琛屾柟娉曡ˉ榻愭牎楠屽拰绛涢?夊悗锛屽湴鍧?娴嬭瘯涓? `OK (5 tests, 22 assertions)`锛岀ぜ鍝?/鍙戣揣娴嬭瘯涓? `OK (4 tests, 21 assertions)`銆?
- 鍥炲綊锛歚FrontGiftControllerCommentReadabilityTest` 涓? `OK (2 tests, 29 assertions)`銆?

### 鍓╀綑杈圭晫
- 榛樿鍦板潃鍒囨崲鐩墠鏄袱娆℃暟鎹簱鍐欐搷浣滐紝灏氶渶楠岃瘉骞跺彂/浜嬪姟鍘熷瓙鎬с??
- 鍦板潃绌虹櫧瀛楃涓层?佺數璇濆彿鐮佹牸寮忋?佽蒋鍒犻櫎榛樿鍦板潃闄嶇骇涓庣ぜ鍝佸厬鎹㈠啓閾句粛闇?缁х画瀹¤銆?

## 346. 2026-07-11 鍓嶅彴榛樿鏀惰揣鍦板潃涓嶅彉閲忎笌浜嬪姟寮曟搸闂幆

### 鏈澶勭悊鐩爣
- 鎭㈠鏃ч」鐩? `DEFAULT_ADDRESS_MUST_EXIST=1015` 涓氬姟璇箟锛氭湁鍦板潃鐨勭敤鎴峰繀椤昏嚦灏戜繚鐣欎竴涓粯璁ゅ湴鍧?銆?
- 闃绘绗竴鏉″湴鍧?鍒涘缓涓洪潪榛樿銆佸敮涓?榛樿鍦板潃琚彇娑堟垨鐩存帴鍒犻櫎銆?
- 淇濊瘉鈥滄竻闄ゆ棫榛樿 鈫? 鍐欏叆鏂伴粯璁も?濆け璐ユ椂瀹屾暣鍥炴粴锛屼笉鐣欎笅闆堕粯璁ゅ湴鍧?銆?

### 璺敱鎵ц閾?
- `POST /api/front/gift-addresses` 鎴栨棫 `POST user/address/update` 鏂板鍒嗘敮 鈫? 鍦板潃瀛楁鏍￠獙 鈫? 褰撳墠鐢ㄦ埛瑙ｆ瀽 鈫? 绗竴鏉￠粯璁や笉鍙橀噺 鈫? InnoDB 浜嬪姟鍐呮竻鐞嗘棫榛樿骞跺垱寤? 鈫? JSON銆?
- `PATCH /api/front/gift-addresses/{address}` 鎴栨棫缂栬緫鍒嗘敮 鈫? 涓ユ牸鍦板潃 ID/鎵?鏈夎?? 鈫? 鍞竴榛樿鍙栨秷妫?鏌? 鈫? InnoDB 浜嬪姟鍐呭垏鎹㈤粯璁ゅ苟鏇存柊 鈫? JSON銆?
- `DELETE /api/front/gift-addresses/{address}` 鈫? 涓ユ牸鍦板潃 ID/鎵?鏈夎?? 鈫? 鍞竴榛樿鍒犻櫎妫?鏌? 鈫? 杞垹闄? 鈫? JSON銆?

### 鏈鍙樻洿鏂囦欢
- `app/Constants/ResponseCode.php`
  - 澧炲姞鏃у吋瀹逛笟鍔＄爜 `DEFAULT_ADDRESS_MUST_EXIST=1015` 鍙婂璇█鏄犲皠銆?
- `app/Http/Controllers/Front/GiftController.php`
  - 鏂板銆佹洿鏂般?佸垹闄ょ粺涓?缁存姢榛樿鍦板潃涓嶅彉閲忥紱鏂板/鏇存柊榛樿鍦板潃浣跨敤 `DB::transaction()`銆?
- `resources/lang/zh-CN/response.php`銆乣resources/lang/zh_CN/response.php`銆乣resources/lang/en/response.php`
  - 澧炲姞榛樿鍦板潃蹇呴』淇濈暀鐨勪腑鑻辨枃鍝嶅簲銆?
- `database/migrations/2026_07_11_000001_convert_user_addresses_to_innodb.php`
  - 灏? `user_addresses` 浠庝笉鏀寔浜嬪姟鍥炴粴鐨? MyISAM 杞崲涓? InnoDB銆?
- `tests/Feature/FrontGiftAddressOwnerBoundaryClosureModuleTest.php`
  - 瑕嗙洊绗竴鏉￠潪榛樿琚嫆缁濄?佸敮涓?榛樿涓嶈兘鍙栨秷鎴栧垹闄ゃ??
- `tests/Feature/FrontGiftAddressTransactionClosureModuleTest.php`
  - 閫氳繃 Eloquent creating 浜嬩欢娉ㄥ叆鍒涘缓寮傚父锛岄獙璇佹棫榛樿娓呴浂鎿嶄綔琚湡瀹炴暟鎹簱浜嬪姟鍥炴粴銆?

### TDD 涓庤繍琛岃瘉鎹?
- RED锛氱涓?鏉￠潪榛樿杩斿洖 CREATED锛涘敮涓?榛樿鍙栨秷杩斿洖 SUCCESS銆?
- GREEN锛氫笟鍔′笉鍙橀噺瀹屾垚鍚庝袱涓洰鏍囩敤渚嬩负 `OK (2 tests, 8 assertions)`銆?
- 鏍瑰洜楠岃瘉锛氬姞鍏ュ簲鐢ㄤ簨鍔″悗锛屾晠闅滄敞鍏ヤ粛鍙戠幇鏃ч粯璁ゅ彉鎴? 0锛涙煡璇? `information_schema.TABLES` 纭 `user_addresses` 寮曟搸涓? `MyISAM`銆?
- 鏁版嵁搴撲慨澶嶏細鎵ц `php artisan migrate --force`锛岃縼绉? `2026_07_11_000001_convert_user_addresses_to_innodb` 鎴愬姛锛岃繍琛屾椂寮曟搸纭涓? `InnoDB`銆?
- GREEN锛氱嫭绔嬫晠闅滄敞鍏ユ祴璇曚负 `OK (1 test, 3 assertions)`锛涘畬鏁村湴鍧?杈圭晫娴嬭瘯涓? `OK (7 tests, 33 assertions)`銆?

### 鍓╀綑杈圭晫
- 鏁版嵁搴撳眰浠嶆病鏈夆?滄瘡鐢ㄦ埛鏈?澶氫竴涓? is_default=1鈥濈殑鍙Щ妞嶅敮涓?绾︽潫锛涘綋鍓嶇敱 InnoDB 浜嬪姟鍜屼笟鍔″啓鍏ュ彛缁存姢锛屽悗缁苟鍙戝弻璇锋眰杩橀渶閿佽娴嬭瘯涓庢暟鎹簱绾︽潫璁捐銆?

## 347. 2026-07-11 鏃у墠鍙扮櫥褰? user_id 涓ユ牸鏍￠獙闂幆

### 鏈澶勭悊鐩爣
- 淇 `AuthController::legacySignIn` 灏嗘墍鏈夐潪閭璐﹀彿 `(int)` 寮鸿浆鐨勯棶棰樸??
- 闃绘 `loginUid=鐪熷疄IDabc`銆乣鐪熷疄ID.9` 浣跨敤鐪熷疄璐﹀彿瀵嗙爜鐧诲綍銆佸垱寤? session/JWT 鍜岀櫥褰曟棩蹇椼??

### 璺敱鎵ц閾?
- `POST user/signIn` 鎴? `POST user/index/signIn` 鈫? `AuthController::legacySignIn` 鈫? 蹇呭～鏍￠獙 鈫? 閭鏍煎紡鎴栫函鏁板瓧姝ｆ暣鏁? user_id 鍒嗙被 鈫? 绮剧‘鏌ヨ `user_logins` 鈫? 鐘舵??/瀵嗙爜/涓氬姟璧勬枡 鈫? user guard + `suser` + JWT + 鐧诲綍鏃ュ織 鈫? 鏃у搷搴斻??

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/AuthController.php`
  - 闈為偖绠辫处鍙峰繀椤婚?氳繃 `ctype_digit` 涓斿ぇ浜? 0锛屼箣鍚庢墠鍏佽鏁存暟杞崲鍜屾暟鎹簱鏌ヨ銆?
- `tests/Feature/FrontLegacyLoginUserIdValidationClosureModuleTest.php`
  - 鐪熷疄璐﹀彿澶瑰叿涓嬫彁浜ゆ暟瀛楀墠缂?鍜屽皬鏁板舰寮忥紝鏂█ `loginStatus=401`銆佹棤 `suser`銆乬uard 鏈櫥褰曘?佹棤鐧诲綍鏃ュ織銆?

### TDD 鎵ц璁板綍
- RED锛歚412710100abc` 浣跨敤姝ｇ‘瀵嗙爜瀹為檯杩斿洖 `loginStatus=200`銆?
- GREEN锛氫弗鏍煎垎绫诲悗鐩爣鐢ㄤ緥涓? `OK (1 test, 9 assertions)`銆?

### 鍓╀綑杈圭晫
- 鏃х櫥褰曞浘褰㈤獙璇佺爜涓庣櫥褰曢鐜囬檺鍒跺皻鏈仮澶嶏紱鐜颁唬鐧诲綍璺敱鐨勯檺娴佷篃闇?涓庝骇鍝佸畨鍏ㄧ瓥鐣ヤ竴璧烽棴鍚堛??

## 348. 2026-07-11 鏃у墠鍙版敞鍐岃浇鑽蜂笌楠岃瘉鐮侀偖浠跺吋瀹归棴鐜?

### 鏈澶勭悊鐩爣
- 瀵圭収鏃ф敞鍐岄〉瀛楁銆乣UserRegisterController` 璋冪敤鍜屾柊 `AuthController`锛屾仮澶嶆棫琛ㄥ崟鍦ㄤ笉鏀惧鐜颁唬 API 濂戠害鍓嶆彁涓嬬殑娉ㄥ唽鑳藉姏銆?
- 鍏煎鏃у浘褰㈤獙璇佺爜娌℃湁鏄惧紡 `captcha_key` 鐨勪細璇濇ā寮忥紝骞跺皢鏃ф敞鍐屽瓧娈靛綊涓?鍒扮幇浠ｆ敞鍐屾湇鍔℃墍闇?瀛楁銆?
- 璁╂棫 `registerSendCode` 鍏ュ彛浣跨敤鍙祴璇曠殑涓撶敤 Mailable 鍙戦?佺湡瀹炲叚浣嶉偖绠遍獙璇佺爜锛屽悓鏃朵繚鐣欑紦瀛樺拰棰戠巼闄愬埗閾捐矾銆?

### 璺敱鎵ц閾?
- `GET user/register/captcha` 鈫? `AuthController::registerCaptcha` 鈫? 鐢熸垚楠岃瘉鐮佹枃鏈笌闅忔満 key 鈫? 鍐欏叆楠岃瘉鐮佺紦瀛? 鈫? 灏? key 鍐欏叆褰撳墠 session 鐨? `front_register_captcha_key` 鈫? 杩斿洖鏃ч〉闈㈠彲鐩存帴灞曠ず鐨? SVG銆?
- `POST user/register/registerSendCode` 鈫? `AuthController::registerSendCode` 鈫? `AuthController::normalizedRegisterInput` 鈫? 鏃у瓧娈? `register_type/useremail/modules/userphoneNo/userIdcardNo` 鏄犲皠 鈫? 璐︽埛绫诲瀷涓庨偖绠辨牎楠? 鈫? 閭/IP 棰戠巼闄愬埗 鈫? 鐢熸垚鍏綅楠岃瘉鐮? 鈫? 鍐欏叆 `front_register_email_code_*` 缂撳瓨 鈫? `FrontRegistrationVerificationCode` 閭欢鍙戦?? 鈫? 杩斿洖 `data.sent=true`銆?
- `POST user/register/registerinto` 鈫? `AuthController::register` 鈫? `AuthController::normalizedRegisterInput` 鈫? 浠呭湪妫?娴嬪埌鏃у瓧娈垫椂鏄犲皠 `register_type/userInviterId/parent_id/agreeRule/sex`銆佽ˉ榻愬崟瀵嗙爜纭瀛楁鍜? session captcha key 鈫? 鍥惧舰楠岃瘉鐮佹牎楠? 鈫? 閭楠岃瘉鐮佹牎楠? 鈫? 閭?璇峰叧绯讳笌娉ㄥ唽涓氬姟鏍￠獙 鈫? `UserRegistrationService::register` 鈫? 杩斿洖鏂版棫椤甸潰閮藉彲璇嗗埆鐨勬垚鍔熸暟鎹??

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/AuthController.php`
  - `registerCaptcha` 淇濆瓨鏃ч〉闈㈤殣寮忛獙璇佺爜 key锛沗normalizedRegisterInput` 闆嗕腑瀹屾垚鏃у瓧娈垫槧灏勶紝鐜颁唬璇锋眰鏈惡甯︽棫瀛楁鏃朵笉娉ㄥ叆鍏煎榛樿鍊硷紱`registerSendCode` 澶嶇敤褰掍竴鍖栫粨鏋滃苟鍙戦?佷笓鐢ㄩ偖浠躲??
- `app/Mail/FrontRegistrationVerificationCode.php`
  - 灏佽娉ㄥ唽楠岃瘉鐮侀偖浠讹紝浣挎敹浠朵汉銆侀獙璇佺爜鍜屾姇閫掕涓哄彲浠ョ敱 Laravel Mail fake 绮剧‘楠岃瘉銆?
- `resources/views/emails/front-registration-verification-code.blade.php`
  - 鎻愪緵鏈?灏忋?佹槑纭殑鍏綅娉ㄥ唽楠岃瘉鐮侀偖浠舵鏂囥??
- `tests/Feature/FrontLegacyRegisterPayloadCompatibilityClosureModuleTest.php`
  - 瑕嗙洊瀹屾暣鏃ф敞鍐? payload 鏄犲皠銆佹棫鍙戦?侀獙璇佺爜瀛楁鏄犲皠銆佺紦瀛樺唴瀹逛笌瀹為檯 Mailable 鎶曢?掋??

### TDD 涓庤繍琛岃瘉鎹?
- RED锛氭棫娉ㄥ唽璇锋眰缂哄皯鐜颁唬 `account_type/agree_terms/password_confirmation/captcha_key` 鏃惰繑鍥炴牎楠屽け璐ワ紱鏃у彂閫侀獙璇佺爜璇锋眰鍥? `register_type/useremail` 鏈槧灏勮?屽け璐ャ??
- GREEN锛氭棫瀹屾暣娉ㄥ唽杞借嵎娴嬭瘯閫氳繃锛屾柇瑷?褰掍竴鍚庣殑璐︽埛绫诲瀷銆侀個璇蜂汉銆佷剑閲戞ā寮忋?佹?у埆銆佺數璇濄?佸瘑鐮佺‘璁ゅ拰鏈?缁堢敤鎴锋暟鎹紱鏃у彂閫侀獙璇佺爜娴嬭瘯涓? `OK (1 test, 7 assertions)`銆?
- 鏂囨。濂戠害锛氬畬鏁存祴璇曟枃浠惰姹傛湰鑺傚悓鏃惰褰? `AuthController::normalizedRegisterInput`銆乣user/register/registerinto` 鍜屾祴璇曠被鍚嶇О锛岄槻姝㈠疄鐜颁笌杩佺Щ娓呭崟鑴辫妭銆?

### 鍓╀綑杈圭晫
- 褰撳墠娉ㄥ唽娴佺▼浠嶉渶缁х画闂悎鈥滄墍鏈変笟鍔℃牎楠屽拰钀藉簱鎴愬姛鍚庢墠娑堣垂鍥惧舰/閭楠岃瘉鐮佲?濓紝閬垮厤閭?璇峰叧绯绘垨鏁版嵁搴撳け璐ヨ揩浣跨敤鎴烽噸鏂拌幏鍙栭獙璇佺爜銆?
- 娉ㄥ唽寮傚父鍝嶅簲宸插湪绗? 349 鑺傛敼涓烘崟鑾峰叏閾? `Throwable`銆佹湇鍔＄璁板綍璇婃柇淇℃伅銆佸鎴风鍙帴鏀剁ǔ瀹氬璇█涓氬姟娑堟伅锛涙敞鍐屾垚鍔熼?氱煡閭欢浠嶉渶缁х画鎸夋棫椤圭洰璇箟鏍稿銆?

## 349. 2026-07-11 鍓嶅彴娉ㄥ唽楠岃瘉鐮佺敓鍛藉懆鏈熴?佸紓甯镐笌骞跺彂闂幆

### 鏈澶勭悊鐩爣
- 灏嗗浘褰㈤獙璇佺爜鍜岄偖绠遍獙璇佺爜浠庘?滄牎楠屾椂绔嬪嵆娑堣垂鈥濇敼涓衡?滈偖绠辩骇浜掓枼閿佸唴鏍￠獙锛岃处鍙锋槑纭惤搴撴垚鍔熷悗缁熶竴娑堣垂鈥濄??
- 涓氬姟瑙勫垯澶辫触銆佹敞鍐屾湇鍔″紓甯搞?佹湇鍔＄粨鏋滀笉瀹屾暣鏃朵繚鐣欓獙璇佺爜锛屽厑璁哥敤鎴蜂慨姝ｆ垨瀹夊叏閲嶈瘯銆?
- 璐﹀彿宸茶惤搴撲絾 JWT 绛惧彂澶辫触鏃朵笉鍐嶄吉瑁呮垚鍙噸澶嶆敞鍐岋細楠岃瘉鐮佷繚鎸佸凡娑堣垂锛屽苟鏄庣‘杩斿洖 `registered=true`銆乣login_required=true`銆?
- 鐜颁唬娉ㄥ唽鎺ュ彛涓ユ牸浣跨敤鐜颁唬瀛楁锛涙棫瀛楁鍒悕浠呭湪鏄庣‘鐨勬棫璺敱涓婂惎鐢ㄣ??

### 璺敱鎵ц閾?
- `POST /api/front/auth/register` 鈫? `AuthController::register` 鈫? `AuthController::normalizedRegisterInput`锛堢幇浠ｅ瓧娈碉紝涓嶅惎鐢ㄦ棫鍒悕锛夆啋 Laravel 鍩虹瀛楁鏍￠獙 鈫? 鎸夎鑼冨寲閭鑾峰彇 120 绉? `Cache::lock` 鈫? 閿佸唴璇诲彇骞舵牎楠屽浘褰?/閭楠岃瘉鐮? 鈫? 娉ㄥ唽鍓嶇疆涓氬姟鏍￠獙 鈫? `UserRegistrationService::register` 鏁版嵁搴撲簨鍔? 鈫? 涓ユ牸纭 `success === true` 涓庡悎娉? `UserLogin` 鈫? 娑堣垂鍙岄獙璇佺爜 鈫? `JwtService::generateToken` 鈫? 鎴愬姛鍝嶅簲 鈫? `finally` 閲婃斁閿併??
- `POST user/register/registerinto` 鈫? `AuthController::register` 鈫? `normalizedRegisterInput` 鎸? `legacy_user_register_into` 璺敱鍚敤鏃у瓧娈垫槧灏? 鈫? 涓庣幇浠ｅ叆鍙ｅ叡鐢ㄧ浉鍚屽熀纭?鏍￠獙銆侀偖绠遍攣銆侀獙璇佺爜銆佹敞鍐屼簨鍔°?佹秷璐广?丣WT 涓庨噴鏀鹃摼銆?
- 娉ㄥ唽鏈嶅姟鍦ㄨ惤搴撳墠杩斿洖涓氬姟澶辫触鎴栨姏鍑? `Throwable` 鈫? 鏈嶅姟绔褰曡劚鏁忛偖绠卞搱甯屽拰寮傚父瀵硅薄 鈫? 杩斿洖绋冲畾涓氬姟/鏈嶅姟鍣ㄩ敊璇? 鈫? 鍙岄獙璇佺爜淇濈暀 鈫? `finally` 閲婃斁閭閿併??
- 娉ㄥ唽鏈嶅姟宸叉槑纭垚鍔熷苟杩斿洖鍚堟硶 `UserLogin`锛屼絾 JWT 绛惧彂鎶涘嚭 `Throwable` 鈫? 鍙岄獙璇佺爜宸叉秷璐? 鈫? 杩斿洖 `response.registration_completed_login_required` 涓? `registered/login_required` 鏍囪 鈫? `finally` 閲婃斁閿侊紝瀹㈡埛绔笉寰楀啀娆℃彁浜ゆ敞鍐屻??
- 鍚岄偖绠卞苟鍙戣姹傦紙鍗充娇浣跨敤涓嶅悓 `captcha_key`锛夆啋 鍙湁绗竴涓姹傚彇寰楅偖绠辩骇閿侊紱鍏朵綑璇锋眰杩斿洖 `RATE_LIMITED`锛屼笉寰楄繘鍏ユ敞鍐屾湇鍔°?傞璇锋眰鎴愬姛娑堣垂楠岃瘉鐮佸悗锛屽悗缁姹傚嵆浣块噸鏂板彇寰楅攣锛屼篃浼氬湪閿佸唴鍥犻獙璇佺爜涓嶅瓨鍦ㄨ鎷掔粷銆?

### 骞跺彂涓庢暟鎹簱鏈?缁堥槻绾?
- 搴旂敤閿侀敭涓? `front_register_submit_lock_` 鍔犺鑼冨寲閭 SHA-1锛屼笉鍖呭惈鍥惧舰楠岃瘉鐮? key锛岄伩鍏嶅悓閭閫氳繃涓嶅悓楠岃瘉鐮佺粫寮?浜掓枼銆?
- 閿佺鏈熷浐瀹氫负 120 绉掞紝瑕嗙洊娉ㄥ唽浜嬪姟鐨勫父瑙勬渶闀挎墽琛岀獥鍙ｏ紱鎵?鏈夊凡鑾峰緱閿佺殑杩斿洖鍜屽紓甯歌矾寰勫潎鐢? `finally` 閲婃斁銆?
- `user_logins.email` 鐨? `user_logins_email_unique` 鍞竴绱㈠紩浣滀负缂撳瓨閿佽繃鏈熴?佽繘绋嬪紓甯告垨鏋佺鎱㈣姹備笅鐨勬渶缁堝啓鍏ラ槻绾裤??
- 杩愯鏃? `SHOW INDEX FROM user_logins` 宸茬‘璁よ绱㈠紩 `Non_unique=0`銆乣Column_name=email`銆乣Index_type=BTREE`銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/AuthController.php`
  - 灏嗘棫瀛楁鍏煎鏀逛负璺敱韬唤鍒ゅ畾锛涚幇浠ｆ帴鍙ｄ笉鍐嶆帴鍙楁棫閭銆佸鍚嶃?佺數璇濄?佽瘉浠躲?侀獙璇佺爜銆佷剑閲戞ā寮忔垨瀵嗙爜纭鍒悕銆?
  - 娉ㄥ唽鍏ㄩ摼鎹曡幏 `Throwable` 骞惰褰曟湇鍔＄璇婃柇锛屽鎴风鍙帴鏀剁ǔ瀹氬璇█娑堟伅銆?
  - 澧炲姞閭绾ф敞鍐岄攣銆侀攣鍐呴獙璇佺爜鏍￠獙銆佷弗鏍兼湇鍔℃垚鍔熺粨鏋勬鏌ャ?佽惤搴撳悗楠岃瘉鐮佹秷璐瑰拰 JWT 澶辫触鐘舵?佹爣璁般??
- `tests/Feature/FrontRegisterVerificationLifecycleClosureModuleTest.php`
  - 瑕嗙洊涓氬姟澶辫触銆佹湇鍔″紓甯搞?佹棤鏁堟湇鍔＄粨鏋溿?佺己澶辨樉寮忔垚鍔熴?佹垚鍔熸秷璐广?丣WT 澶辫触宸叉敞鍐岀姸鎬併?佺浉鍚?/涓嶅悓鍥惧舰楠岃瘉鐮佺殑鍚岄偖绠变簰鏂ャ?佹彁鍓嶈繑鍥為噴鏀鹃攣銆佹秷璐瑰悗閲嶆斁鍜岄偖绠卞敮涓?绱㈠紩濂戠害銆?
- `tests/Feature/FrontLegacyRegisterPayloadCompatibilityClosureModuleTest.php`
  - 閫愰」瑕嗙洊鐜颁唬璺敱鎷掔粷鎵?鏈夋棫瀛楁鍒悕锛屽悓鏃惰瘉鏄庢棫 `registerinto` 浠嶆寜鏃ц〃鍗曞绾﹀伐浣溿??
- `tests/Feature/FrontAuthControllerLocalizationTest.php`
  - 閭欢鑱岃矗杩佺Щ鍚庡悓鏃舵壂鎻? Controller銆丮ailable 鍜岄偖浠舵ā鏉匡紝淇濊瘉楠岃瘉鐮佷富棰樺拰姝ｆ枃缁х画浣跨敤璇█ key銆?
- `resources/lang/zh-CN/auth.php`銆乣resources/lang/zh_CN/auth.php`銆乣resources/lang/en/auth.php`
  - 澧炲姞璐﹀彿宸叉敞鍐屼絾闇?瑕侀噸鏂扮櫥褰曠殑绋冲畾鎻愮ず銆?

### TDD銆佸鏍镐笌杩愯璇佹嵁
- 棣栬疆 RED锛氫笟鍔℃牎楠屽け璐ヤ細鎻愬墠娑堣垂鍥惧舰楠岃瘉鐮侊紱娉ㄥ唽寮傚父鐩存帴杩斿洖 `SQLSTATE` 鍘熸枃銆?
- 绗簩杞? RED锛歚validateRegistration` 鐨? `Error` 绌块?忋?佹湇鍔＄己澶辨槑纭垚鍔熶粛娑堣垂銆佺幇浠ｈ矾鐢辨贩鍏ユ棫瀛楁鍙斁瀹藉绾︺??
- 骞跺彂 RED锛氬悓閭涓嶅悓 `captcha_key` 浣跨敤涓嶅悓閿侀敭锛涢獙璇佺爜鍦ㄩ攣澶栨牎楠屽舰鎴愰檲鏃ф牎楠岀粨鏋滅珵鎬併??
- 鏈?缁? GREEN锛歚FrontRegisterVerificationLifecycleClosureModuleTest` 涓? `OK (14 tests, 90 assertions)`銆?
- 鏃у吋瀹逛笌鐜颁唬闅旂锛歚FrontLegacyRegisterPayloadCompatibilityClosureModuleTest` 涓? `OK (12 tests, 62 assertions)`銆?
- 鍥炲綊锛歚FrontAuthControllerCommentReadabilityTest` 涓? `OK (2 tests, 34 assertions)`锛沗FrontAuthControllerLocalizationTest` 涓? `OK (2 tests, 36 assertions)`锛沗ApiResponseLocalizationContractTest` 涓? `OK (2 tests, 245 assertions)`銆?
- 瑙勬牸澶嶆牳鏈?缁堢粨鏋滐細Critical=0銆両mportant=0锛涗唬鐮佽川閲忓鏍告渶缁堢粨鏋滐細Critical=0銆侀樆鏂?? Important=0銆?
- `AuthController.php`銆佺敓鍛藉懆鏈熸祴璇曞拰涓夊璇█鏂囦欢鍧囬?氳繃 `php -l`銆?

### 鍓╀綑杈圭晫
- 娉ㄥ唽閿佺鏈熼渶瑕佺粨鍚堢敓浜у疄闄呰?楁椂鐩戞帶鎸佺画鏍″噯锛涘嵆浣挎瀬绔參璇锋眰瓒呰繃绉熸湡锛屾暟鎹簱閭鍞竴绱㈠紩浠嶉樆姝㈤噸澶嶈处鍙峰啓鍏ャ??
- 娉ㄥ唽鎴愬姛閫氱煡閭欢鏄惁闇?瑕佹仮澶嶆棫椤圭洰瀹屾暣娆㈣繋閭欢鍐呭锛屽皢鍦ㄦ櫘閫氱敤鎴烽?氱煡閾鹃?愯矾鐢卞鐓ф椂缁х画鏍稿锛涙湰鑺備粎瀹屾垚楠岃瘉鐮侀偖浠朵笌娉ㄥ唽鐘舵?佸畨鍏ㄩ棴鐜??

## 350. 2026-07-11 鍓嶅彴鏂伴椈杞垹闄ょ炕璇戜笌娲昏穬缈昏瘧鍞竴鎬ч棴鐜?

### 鏈澶勭悊鐩爣
- 闃绘 `news_langs.deleted_at` 宸叉爣璁板垹闄ょ殑缈昏瘧閲嶆柊鍑虹幇鍦? Dashboard銆佺儹鐐规柊闂汇?佹柊闂诲垪琛ㄣ?佹悳绱㈠拰璇︽儏銆?
- 褰撳綋鍓嶈瑷?鍙瓨鍦ㄥ凡鍒犻櫎缈昏瘧鏃讹紝绋冲畾鍥為?? `news` 涓昏〃鏍囬涓庢鏂囥??
- 浠庢暟鎹簱灞備繚璇佸悓涓? `news_id + lang_code` 鏈?澶氫竴鏉℃湭鍒犻櫎缈昏瘧锛岄伩鍏嶆棤鎺掑簭 `first()` 鍦ㄥ鏉℃椿璺冪炕璇戦棿闅忔満閫夋嫨銆?
- 灏嗘棫搴? `news/news_langs` 浠? MyISAM 杞负 InnoDB锛屼娇鏂伴椈鍐欏叆銆佽縼绉诲拰娴嬭瘯浜嬪姟鐪熷疄鐢熸晥銆?

### 璺敱鎵ц閾?
- `GET /api/front/dashboard` 鈫? JWT/SSO 鈫? `DashboardController::dashboardData` 鈫? `News::published()` 鈫? `news_langs` 鎸夋柊闂? ID銆佽瑷?鍜? `deleted_at IS NULL` 鏌ヨ 鈫? 娲昏穬缈昏瘧浼樺厛銆佸惁鍒欎富琛ㄥ洖閫? 鈫? Dashboard JSON銆?
- `POST user/main/hot/news` 鈫? `DashboardController::hotNews` 鈫? `localizedNewsTitle` 鈫? 娲昏穬缈昏瘧杩囨护 鈫? 鏃? HTML 鍒楄〃鍝嶅簲銆?
- `POST user/main/hot/newsV2` / `GET user/register/hotnews` 鈫? `DashboardController::hotNewsV2` 鈫? `localizedNewsTitle` 鈫? 娲昏穬缈昏瘧杩囨护 鈫? 琛ㄦ牸琛屽搷搴斻??
- `GET /api/front/news` 鈫? `NewsController::newsList` 鈫? `newsQuery` 鐨勭炕璇戞爣棰樻悳绱㈠彧璇诲彇鏈垹闄ょ炕璇? 鈫? `newsRow` 鍙鍙栨湭鍒犻櫎缈昏瘧 鈫? 鐜颁唬鍒嗛〉鍝嶅簲銆?
- `POST user/newsListSearch` 鈫? `NewsController::newsListSearch` 鈫? 澶嶇敤鐩稿悓 `newsQuery/newsRow` 鈫? 宸插垹闄ょ炕璇戞爣棰樹笉寰楀懡涓? 鈫? 鏃? `rows/total` 鍝嶅簲銆?
- `GET user/news/news_detail/{newsId}` 鈫? `NewsController::newsDetail` 鈫? 宸插彂甯冧富鏂伴椈 鈫? 褰撳墠璇█鏈垹闄ょ炕璇? 鈫? HTML锛涙病鏈夋椿璺冪炕璇戞椂浣跨敤涓昏〃鏍囬鍜屾鏂囥??

### 鏁版嵁搴撲笉鍙橀噺
- 杩佺Щ `2026_07_11_000002_enforce_unique_active_news_translations` 鍏堝皢 MySQL/MariaDB 鐨? `news`銆乣news_langs` 杞负 InnoDB銆?
- 杩佺Щ鍓嶆鏌ュ瓨閲忔湭鍒犻櫎缈昏瘧閲嶅缁勶紱瀛樺湪閲嶅鏃舵槑纭腑姝紝涓嶉潤榛樿鐩栦笟鍔″唴瀹广??
- MySQL/MariaDB 澧炲姞鐢熸垚鍒? `active_translation_key`锛氭湭鍒犻櫎璁板綍鐢熸垚 `news_id:lang_code`锛屽凡鍒犻櫎璁板綍鐢熸垚 `NULL`锛涘敮涓?绱㈠紩鍙檺鍒舵椿璺冪炕璇戯紝鍏佽淇濈暀澶氭潯杞垹闄ゅ巻鍙层??
- SQLite/PostgreSQL 浣跨敤 `WHERE deleted_at IS NULL` 鐨勯儴鍒嗗敮涓?绱㈠紩瀹炵幇鐩稿悓璇箟銆?
- 鐢熸垚鍒楀拰鍞竴绱㈠紩鍒嗗埆妫?娴嬨?佸垎鍒ˉ寤?/鍒犻櫎锛岄儴缃叉浘閮ㄥ垎鎵ц鏃跺彲鎭㈠銆?
- 杩愯鏃跺凡纭涓よ〃寮曟搸鍧囦负 `InnoDB`锛宍news_langs_active_translation_unique` 涓? `Non_unique=0` 鐨? BTREE 鍞竴绱㈠紩銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/DashboardController.php`
  - 涓ゅ鍘熺敓 `news_langs` 璇诲彇澧炲姞 `whereNull('deleted_at')`銆?
- `app/Http/Controllers/Front/NewsController.php`
  - 璇︽儏缈昏瘧銆佺炕璇戞爣棰樻悳绱€?佸垪琛ㄨ缈昏瘧涓夊鏌ヨ澧炲姞 `whereNull('deleted_at')`銆?
- `database/migrations/2026_07_11_000002_enforce_unique_active_news_translations.php`
  - 杞崲浜嬪姟寮曟搸骞跺缓绔嬫椿璺冪炕璇戝敮涓?绾︽潫锛涗繚鐣欏鏁版嵁搴撳疄鐜颁笌閮ㄥ垎鎵ц鎭㈠銆?
- `tests/Feature/FrontNewsTranslationSoftDeleteClosureModuleTest.php`
  - 浣跨敤 `DatabaseTransactions`銆佸姩鎬佹柊闂?/鐢ㄦ埛 ID 鍜屽姩鎬侀偖绠憋紝閬垮厤鍏变韩鏁版嵁搴撴薄鏌撱??
  - 瑕嗙洊浠呰蒋鍒犻櫎缈昏瘧鍥為??銆佹椿璺?+鍒犻櫎骞跺瓨銆佸垹闄ゆ爣棰樻悳绱笉鍛戒腑銆佽鎯?/鐑偣/Dashboard 鍥為??鍜岄噸澶嶆椿璺冪炕璇戞暟鎹簱鎷掔粷銆?

### TDD銆佽縼绉讳笌澶嶆牳璇佹嵁
- RED锛氭柊澧炶仛鐒︽祴璇曢娆¤繍琛? `4 tests / 4 failures`锛屽垪琛ㄣ?佹悳绱€?佽鎯?/鐑偣銆丏ashboard 鍧囩湡瀹炶繑鍥炲凡鍒犻櫎缈昏瘧銆?
- GREEN锛氫簲澶勬煡璇㈣ˉ榻愯繃婊ゅ悗涓? `OK (4 tests, 26 assertions)`銆?
- 缁撴瀯 RED锛氭暟鎹簱鍏佽鍚屾柊闂?/璇█鎻掑叆绗簩鏉℃湭鍒犻櫎缈昏瘧锛屽敮涓?绾︽潫娴嬭瘯鏈姏 `QueryException`銆?
- 鏍瑰洜楠岃瘉锛歚information_schema.TABLES` 纭 `news` 涓? `news_langs` 鍘熶负 MyISAM锛屼簨鍔″す鍏锋棤娉曞洖婊氾紱娓呯悊鏈疆鏄庣‘鏍囪鐨勬祴璇曟畫鐣欏悗鎵ц寮曟搸杞崲涓庡敮涓?绾︽潫杩佺Щ銆?
- 杩佺Щ鎵ц锛歚2026_07_11_000002_enforce_unique_active_news_translations` 鎴愬姛锛屾渶缁堢洰鏍囨祴璇曚负 `OK (5 tests, 27 assertions)`銆?
- 鍥炲綊锛歚FrontNewsControllerCommentReadabilityTest` 涓? `OK (2 tests, 20 assertions)`锛沗FrontDashboardControllerCommentReadabilityTest` 涓? `OK (2 tests, 31 assertions)`銆?
- 鏈?缁堣鏍煎鏍革細Critical=0銆両mportant=0锛涙渶缁堣川閲忓鏍革細Critical=0銆両mportant=0銆?

### 鍓╀綑杈圭晫
- 杩佺Щ `down()` 鍒犻櫎娲昏穬缈昏瘧鍞竴绱㈠紩鍜岀敓鎴愬垪锛屼絾涓嶄細鎶? InnoDB 鎭㈠涓烘湭鐭ョ殑鏃у紩鎿庯紱浜嬪姟瀹夊叏鍗囩骇灞炰簬鏈夋剰淇濈暀鐨勪笉鍙?嗘暟鎹眰鏀硅繘銆?
- 鏂伴椈鍒嗛〉銆佹棩鏈熻緭鍏ャ?佽鎯呰矾鐢卞弬鏁般?佹棫璺敱鐧诲綍鏉冮檺鍜? `hotNewsV2` 鍘嗗彶鍝嶅簲濂戠害浠嶅湪鍚庣画 News 瀛愮洰鏍囦腑缁х画闂悎銆?

## 351. 2026-07-11 鍓嶅彴鏂伴椈鍒嗛〉銆佹棫 rows 涓庢棩鏈熻緭鍏ラ棴鐜?

### 鏈澶勭悊鐩爣
- 闃绘鏂伴椈鍒楄〃鎶? `1abc`銆侀浂銆佽礋鏁版垨瓒呭ぇ鍒嗛〉鍊煎己杞?/澶瑰彇涓哄悎娉曟煡璇€??
- 鎭㈠鏃? EasyUI `rows` 姣忛〉鏁伴噺瀛楁锛屽苟鏄庣‘ `per_page > limit > rows > 15` 浼樺厛绾с??
- 瀵圭幇浠ｅ拰鏃ф棩鏈熷埆鍚嶉?愬瓧娈垫墽琛岀湡瀹? `Y-m-d` 鏍￠獙锛屾嫆缁濅笉瀛樺湪鏃ユ湡鍜岀粨鏉熸棩鏃╀簬寮?濮嬫棩銆?
- 鎵?鏈夐潪娉曡緭鍏ュ繀椤诲湪 `newsQuery()` 鍓嶈繑鍥烇紝鏃ф帴鍙ｅ悓鏃朵繚鐣欒〃鏍兼墍闇? `rows/total` 缁撴瀯銆?

### 璺敱鎵ц閾?
- `GET /api/front/news` 鈫? `NewsController::newsList` 鈫? `page` 鐨? `integer|min:1` 涓? `per_page` 鐨? `integer|between:1,100` 鈫? 鍥涙棩鏈熷埆鍚? `date_format:Y-m-d` 鈫? 鎸? `date_from/date_to` 浼樺厛浜? `startdate/enddate` 鍙栧緱鏈夋晥鍖洪棿 鈫? 鍖洪棿椤哄簭鏍￠獙 鈫? `newsQuery` 鈫? 鍒嗛〉涓庣炕璇戞槧灏? 鈫? 鏍囧噯 JSON銆?
- `POST user/newsListSearch` 鈫? `NewsController::newsListSearch` 鈫? 涓ユ牸鏍￠獙 `page/per_page/limit/rows` 鍜屽洓鏃ユ湡鍒悕 鈫? 闈炴硶鏃惰繑鍥? `code=VALIDATION_FAILED`銆佺浉鍚? `message/msg`銆乣rows=[]`銆乣total=0` 鈫? 鍚堟硶鏃舵寜 `per_page > limit > rows > 15` 鍙栨瘡椤垫暟 鈫? 鏄惧紡 page 鍒嗛〉 鈫? 鏃? `rows/total` 鍝嶅簲銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Controllers/Front/NewsController.php`
  - 涓や釜鍒楄〃鍏ュ彛鍦ㄤ换浣曟柊闂绘煡璇㈠墠鎵ц Validator 鍜屾棩鏈熻寖鍥存牎楠屻??
  - 鏂板闆嗕腑鍒嗛〉/鏃ユ湡瑙勫垯鍜屾棩鏈熻寖鍥? helper锛屽苟璁板綍鐜颁唬鏃ユ湡瀛楁浼樺厛绾с??
  - 鏃у叆鍙ｇ洿鎺ヤ娇鐢ㄤ弗鏍兼牎楠屽悗鐨勫垎椤靛?硷紝涓嶅啀璋冪敤浼氶潤榛樺す鍊肩殑 `FrontLegacyData::perPage`銆?
- `tests/Feature/FrontNewsListInputValidationClosureModuleTest.php`
  - 瑕嗙洊鐜颁唬/鏃ч潪娉曞垎椤点?佸洓鏃ユ湡鍒悕銆佺湡瀹炰笉瀛樺湪鏃ユ湡銆佸?掔疆鏃ユ湡鑼冨洿銆佹棫閿欒缁撴瀯銆乣rows` 绗簩椤靛拰瀹屾暣鍒嗛〉浼樺厛绾с??
  - 浣跨敤 InnoDB 浜嬪姟涓庡姩鎬佹柊闂? ID锛岄獙璇佹煡璇㈠墠鏍￠獙鍜岀湡瀹炲垎椤垫暟鎹??

### TDD 涓庡鏍歌瘉鎹?
- RED锛氭柊澧? 4 涓洰鏍囨祴璇曢娆¤繍琛屾湁 3 涓鏈熷け璐ワ紝鍒嗗埆璇佹槑鐜颁唬闈炴硶杈撳叆浠嶆垚鍔熴?佹棫閿欒缁撴瀯缂哄け銆乣rows` 鏈敓鏁堛??
- GREEN锛氫弗鏍兼牎楠屽拰鏃у垎椤垫槧灏勫畬鎴愬悗涓? `OK (4 tests, 99 assertions)`銆?
- 鍥炲綊锛歚FrontNewsTranslationSoftDeleteClosureModuleTest` 涓? `OK (5 tests, 27 assertions)`锛沗FrontNewsControllerCommentReadabilityTest` 涓? `OK (2 tests, 20 assertions)`銆?
- 杩愯鏃跺啀娆＄‘璁? `news` 琛ㄥ紩鎿庝负 InnoDB锛屼簨鍔℃祴璇曚笉浼氶仐鐣欐暟鎹??
- 鏈?缁堣鏍煎鏍革細Critical=0銆両mportant=0锛涙渶缁堣川閲忓鏍革細Critical=0銆両mportant=0銆?

### 鍓╀綑杈圭晫
- 鏈妭鏈敼鍙樻棫椤圭洰鈥滄棤鏃ユ湡鏃堕粯璁? 2024-01-01 鑷冲綋澶┾?濆拰鏂伴」鐩?滄棤鏃ユ湡鏃朵笉杩囨护鈥濈殑浜у搧宸紓锛涘綋鍓嶄繚鐣欑幇浠ｅ叏閲忔煡璇㈣涔夛紝骞跺湪鏈?缁堥?愯矾鐢辨姤鍛婁腑鏍囪涓烘湁鎰忓樊寮傘??
- 鏂伴椈璇︽儏鍙傛暟銆佺洿鎺ヨ鎯呴〉銆佹棫鐧诲綍鏉冮檺鍜岀儹鐐规柊闂诲巻鍙插搷搴斿绾︾户缁繘鍏ュ悗缁? News 瀛愮洰鏍囥??

## 352. 2026-07-11 鍓嶅彴鏂伴椈璇︽儏璺敱銆佺洿鎺ユ墦寮?涓庡瘜鏂囨湰瀹夊叏闂幆

### 鏈澶勭悊鐩爣
- 闃绘鏃ф柊闂昏鎯呴潪鏁板瓧鍙傛暟杩涘叆 `int $newsId` 鍚庝骇鐢? 500銆?
- 璁? `/front/news/detail/{newsId}` 鐪熸瀹氫綅骞惰嚜鍔ㄦ墦寮?鎸囧畾宸插彂甯冩柊闂伙紝鑰屼笉鏄??鍖栦负鏅?氬垪琛ㄧ涓?椤点??
- 闃绘闈炴硶鐜颁唬璇︽儏璺緞琚墠鍙? catch-all 鏄犲皠鎴? Dashboard 200銆?
- 鍑?鍖栨棫璇︽儏瀵屾枃鏈紝淇濈暀瀹夊叏鎺掔増骞剁Щ闄ゅ瓨鍌ㄥ瀷 XSS 鍙墽琛屽唴瀹广??

### 璺敱鎵ц閾?
- `GET user/news/news_detail/{newsId}` 鈫? 璺敱 `whereNumber` 鈫? `NewsController::newsDetail` 鈫? `News::published()` 鎺掗櫎鏈彂甯?/杞垹闄? 鈫? 娲昏穬缈昏瘧 鈫? `SafeHtml::sanitize` 姝ｆ枃 鈫? 鏃? HTML 璇︽儏锛涢潪鏁板瓧銆佷笉瀛樺湪銆佹湭鍙戝竷銆佽蒋鍒犻櫎鍧囦负 404銆?
- `GET /front/news/detail/{newsId}` 鈫? 璺敱 `whereNumber` 鈫? `NewsController::newsPage` 鈫? `News::published()->exists()` 鈫? Blade 浼犲叆 `news_id` 榛樿绛涢?夊拰 `initialNewsId` 鈫? iframe 鍐呴〉闈㈣姹? `/api/front/news?news_id={id}` 鈫? `newsQuery` 绮剧‘ ID 杩囨护鍚庡垎椤? 鈫? JS 棣栨鎵惧埌瀵瑰簲琛屽悗鏍囪骞惰皟鐢? `openNewsDetailModal` 涓?娆°??
- `GET /front/news/detail/{invalidNewsId}` 鈫? 鏁板瓧璇︽儏璺敱鏈懡涓? 鈫? catch-all 鍓嶇殑鏄庣‘闈炴硶璇︽儏璺敱 鈫? 404锛屼笉杩涘叆鍓嶅彴搴旂敤鍏滃簳銆?
- `GET /api/front/news?news_id={id}` 鈫? `news_id` 鐨? `integer|min:1` 鏍￠獙 鈫? `News::published()` 绮剧‘杩囨护 鈫? 鍒嗛〉鍜岀炕璇戞槧灏勶紱闈炴硶 ID 杩斿洖 `VALIDATION_FAILED`銆?

### 瀵屾枃鏈畨鍏ㄨ竟鐣?
- `SafeHtml` 浣跨敤 DOM 瑙ｆ瀽鍜屾爣绛?/灞炴?у厑璁稿垪琛紱绉婚櫎 `script/style/iframe/object/embed/svg/math/form` 绛夊彲鎵ц鎴栭珮椋庨櫓鑺傜偣鍙婂叾鍐呭銆?
- 鎵?鏈夋湭鍏佽灞炴?у潎绉婚櫎锛屽洜姝? `on*`銆乣style`銆乣srcset`銆乣formaction` 绛変笉鑳借繘鍏ュ搷搴斻??
- URL 鍏堝仛 HTML 瀹炰綋瑙ｇ爜骞剁Щ闄? ASCII 鎺у埗/绌虹櫧锛屽啀鎷掔粷 `javascript:`銆乣vbscript:`銆乣data:`锛涘浘鐗囧彧鍏佽 HTTP銆丠TTPS 鎴栫浉瀵硅矾寰勩??
- 鏈煡鏍囩鍏堥?掑綊鍑?鍖栧瓙鑺傜偣鍐嶈В鍖咃紝瀹夊叏鏂囨湰涓庡厑璁哥殑瀵屾枃鏈粨鏋勫彲浠ヤ繚鐣欍??
- `target=_blank` 閾炬帴鑷姩琛? `rel="noopener noreferrer"`銆?

### 鏈鍙樻洿鏂囦欢
- `routes/web.php`
  - 鏃ц鎯呭鍔犳暟瀛楃害鏉燂紱鐜颁唬璇︽儏鏀圭敱 Controller 鏍￠獙锛涘鍔? catch-all 鍓嶉潪娉曡鎯? 404 璺敱銆?
- `app/Http/Controllers/Front/NewsController.php`
  - 鏂板 `newsPage`锛涘垪琛ㄨ鍒欏拰鏌ヨ澧炲姞涓ユ牸 `news_id`锛涙棫璇︽儏浣跨敤 `SafeHtml`銆?
- `app/Support/SafeHtml.php`
  - 鏂板鍙鐢ㄧ殑瀵屾枃鏈厑璁稿垪琛ㄥ噣鍖栧櫒銆?
- `resources/front/layui/news/index.blade.php`
  - 灏嗚鎯? ID 浣滀负瀹夊叏榛樿绛涢?夊拰鍒濆鏂伴椈 ID 浼犵粰閫氱敤妯″潡椤点??
- `resources/front/layui/partials/module-page.blade.php`
  - 澧炲姞榛樿鍊间负 0 鐨? `data-initial-news-id`锛屽叾浠栨ā鍧椾笉鍙楀奖鍝嶃??
- `public/js/apps/front/layui/module-page.js`
  - 鏂伴椈鏃堕棿绾块娆″姞杞芥寚瀹氳鍚庤嚜鍔ㄦ墦寮?涓?娆★紱鍙湁鐪熷疄鎵惧埌琛屾椂鎵嶆秷璐规墦寮?鏍囪銆?
- `tests/Feature/FrontNewsDetailRouteClosureModuleTest.php`
  - 瑕嗙洊鏁板瓧/闈炴硶璺敱銆佸凡鍙戝竷/鏈彂甯?/杞垹闄?/涓嶅瓨鍦ㄣ?侀潪棣栧睆绮剧‘ ID銆侀〉闈㈡暟鎹睘鎬с?丣S 鍗曟鎵撳紑椤哄簭鍜屾棤鍐呰仈鎵ц鑴氭湰銆?
- `tests/Feature/SafeHtmlSanitizerTest.php`
  - 瑕嗙洊鐩存帴涓庢贩娣嗗崗璁?佷簨浠跺睘鎬с?丼VG/MathML銆佹湭鐭ユ爣绛俱?佸厑璁搁摼鎺?/鍥剧墖鍜岀暩褰? HTML銆?

### TDD 涓庡鏍歌瘉鎹?
- RED锛?4 涓鎯呮祴璇曢娆¤繍琛屽叏閮ㄥけ璐ワ紝鍒嗗埆璇佹槑鏃ч潪鏁板瓧 500銆佺幇浠ｉ〉闈㈡湭浼犲垵濮? ID銆丄PI 鏈繃婊?/鏍￠獙 ID銆丣S 鏈疄鐜拌嚜鍔ㄦ墦寮?銆?
- 瀹夊叏 RED锛氭棫璇︽儏鐪熷疄杩斿洖 `<script>`銆乣onerror` 鍜? `javascript:`锛汮S 鍦ㄦ壘鍒拌鍓嶆彁鍓嶆爣璁板凡鎵撳紑銆?
- GREEN锛歚FrontNewsDetailRouteClosureModuleTest` 涓? `OK (4 tests, 44 assertions)`锛沗SafeHtmlSanitizerTest` 涓? `OK (3 tests, 21 assertions)`銆?
- 鍥炲綊锛歚FrontNewsListInputValidationClosureModuleTest` 涓? `OK (4 tests, 99 assertions)`锛沗FrontNewsTranslationSoftDeleteClosureModuleTest` 涓? `OK (5 tests, 27 assertions)`锛沗FrontLegacyRouteCompatibilityTest` 涓? `OK (14 tests, 1328 assertions)`锛沗FrontUiRegressionTest` 涓? `OK (137 tests, 3089 assertions)`銆?
- `NewsController.php`銆乣SafeHtml.php`銆乣routes/web.php` 閫氳繃 `php -l`锛宍module-page.js` 閫氳繃 `node --check`銆?
- 鏈?缁堣鏍?/璐ㄩ噺鍚堝苟澶嶆牳锛欳ritical=0銆両mportant=0銆?

### 鍓╀綑杈圭晫
- 鐩墠鍏佽鍗忚鐩稿鐨? `//host/path` 閾炬帴鍜屽浘鐗囷紱瀹冧笉鏋勬垚鑴氭湰鎵ц锛屼絾鑻ヤ骇鍝佽姹傚彧鍏佽鏈珯璧勬簮锛屽彲鍦ㄥ悗缁唴瀹瑰畨鍏ㄧ瓥鐣ヤ腑杩涗竴姝ユ敹绱с??
- 鏃ф柊闂昏矾鐢辩櫥褰曟潈闄愬拰 `hotNewsV2`/娉ㄥ唽鐑偣鏂伴椈鍘嗗彶鍝嶅簲濂戠害浠嶅湪涓嬩竴瀛愮洰鏍囩户缁棴鍚堛??

## 353. 2026-07-11 鏃у墠鍙版柊闂荤櫥褰曟潈闄愪笌鐑偣鍝嶅簲濂戠害闂幆

### 鏈澶勭悊鐩爣
- 鎭㈠鏃? `LoginMiddleware` 瀵规秷鎭?佺櫥褰曞悗鐑偣銆佺ぜ鍝佹彁绀恒?佹柊闂诲垪琛?/璇︽儏/鎼滅储鐨勭櫥褰曢棬妲涳紝鍚屾椂淇濈暀 `user/main/hot/news` 鐨勬棫鍏紑渚嬪銆?
- 鍚屾椂鍏煎 Laravel `user` guard 鍜屾棫 session `suser`锛岄伩鍏嶈縼绉诲悗鍙湁 JWT 鐢ㄦ埛鑳借闂棫椤甸潰銆?
- 鎷嗗垎鐧诲綍鍚? `hotNewsV2` 涓庡叕寮?娉ㄥ唽鐑偣锛屼笉鍐嶇敤鍚屼竴鏂规硶杩斿洖浜掔浉鍐茬獊鐨勫搷搴旂粨鏋勩??
- 鎭㈠鏃? `hotNewsV2` 鐨? `code=200`銆佸浐瀹? 10 鏉°?乣lang_id` 鍜? `lang_name=zh-cn/en`銆?

### 璺敱鎵ц閾?
- 鍙椾繚鎶ら〉闈? `GET user/front/message`銆乣GET user/news_list_browse`銆乣GET user/news/news_detail/{newsId}` 鈫? `LegacyFrontAuthenticate` 鈫? user guard 鎴? `suser.user_id` 鈫? Controller锛涘尶鍚嶈姹? 302 鍒? `/user/login`銆?
- 鍙椾繚鎶? AJAX `POST user/main/hot/newsV2`銆乣POST user/main/hasShowGiftTips`銆乣POST user/newsListSearch` 鈫? `LegacyFrontAuthenticate` 鈫? 宸茬櫥褰曠户缁紱鍖垮悕杩斿洖 `AUTH_FAILED`銆佺浉鍚? `message/msg`銆乣rows=[]`銆乣total=0`銆乣footer=[]`銆乣redirect=true` 鍜岀櫥褰? URL銆?
- 鍏紑 `POST user/main/hot/news` 鈫? 淇濈暀鏃т腑闂翠欢渚嬪 鈫? `DashboardController::hotNews` 鈫? `code=0` HTML 鍒楄〃銆?
- 鐧诲綍鍚? `POST user/main/hot/newsV2` 鈫? `page/lang_id` 涓ユ牸鏍￠獙 鈫? `lang_id=1` 浣跨敤 `zh-CN` 缈昏瘧骞惰繑鍥? `lang_name=zh-cn`锛宍lang_id=2` 浣跨敤鑻辨枃骞惰繑鍥? `lang_name=en` 鈫? 鍥哄畾姣忛〉 10 鏉? 鈫? `code=200`銆乣count/data/totalRow`銆?
- 鍏紑 `GET user/register/hotnews` 鈫? `DashboardController::registerHotNews` 鈫? `page/limit/lang_id` 鏍￠獙 鈫? 鏈湴宸插彂甯冩柊闂诲拰娲昏穬缈昏瘧 鈫? 椤跺眰鍘熷鏂伴椈鏁扮粍锛屼笉鍐嶈繑鍥? `hotNewsV2` 琛ㄦ牸鍖呰銆?
- `POST user/main/hasShowGiftTips` 鈫? 閴存潈涓棿浠? 鈫? `legacyFrontUserId` 鍏煎 guard/session 鈫? 鍐欏叆 `gift_tips_shown_{user_id}` 鈫? 鎴愬姛鍝嶅簲锛涘尶鍚嶄笉鍐嶅亣鎴愬姛銆?

### 鏈鍙樻洿鏂囦欢
- `app/Http/Middleware/LegacyFrontAuthenticate.php`
  - 鏂板鏃у墠鍙扮櫥褰曚腑闂翠欢锛岀粺涓?璇嗗埆 guard 涓? `suser`锛屽垎鍒繑鍥為〉闈㈤噸瀹氬悜鍜屾棫 AJAX 鍏煎鏈巿鏉冪粨鏋勩??
- `app/Http/Kernel.php`
  - 娉ㄥ唽 `legacy.front.auth` 璺敱涓棿浠跺埆鍚嶃??
- `routes/web.php`
  - 鍏潯鏃у彈淇濇姢璺敱鎺ュ叆涓棿浠讹紱鍏紑鐑偣淇濇寔鍏紑锛涙敞鍐岀儹鐐规敼涓虹嫭绔? `registerHotNews`銆?
- `app/Http/Controllers/Front/DashboardController.php`
  - `hotNewsV2` 鎭㈠鏃х爜銆佽瑷?鍜屾瘡椤垫暟閲忥紱鏂板鍏紑娉ㄥ唽鐑偣閫傞厤锛涚ぜ鍝佹彁绀轰娇鐢ㄧ粺涓?鏃х敤鎴疯В鏋愩??
- `tests/Feature/FrontLegacyNewsAuthenticationAndHotContractClosureModuleTest.php`
  - 瑕嗙洊鍖垮悕椤甸潰/AJAX銆佹棫 session銆佺湡瀹? user guard銆佺ぜ鍝佺紦瀛樸?佷腑鏂?/鑻辨枃鐑偣鍜屾敞鍐屽師濮嬫暟缁勩??
- `tests/Feature/FrontNewsTranslationSoftDeleteClosureModuleTest.php`銆乣FrontNewsDetailRouteClosureModuleTest.php`銆乣FrontLegacyRouteCompatibilityTest.php`
  - 灏嗗彈淇濇姢鍏ュ彛鏀逛负鎼哄甫鏃? session锛岀户缁獙璇佷笟鍔¤涓鸿?屼笉缁曡繃鏂版潈闄愰棬妲涖??

### TDD 涓庡鏍歌瘉鎹?
- RED锛氭柊澧? 4 涓敤渚嬮娆¤繍琛屽叏閮ㄥけ璐ワ紝璇佹槑鍖垮悕鍙椾繚鎶ら〉闈粛 200銆乻ession 绀煎搧鎻愮ず鏈啓缂撳瓨銆乣hotNewsV2` 浠? `code=0`銆佹敞鍐岀儹鐐逛粛杩斿洖琛ㄦ牸鍖呰銆?
- GREEN锛氭渶缁堥壌鏉冧笌鐑偣濂戠害涓? `OK (5 tests, 63 assertions)`銆?
- 鍥炲綊锛歚FrontNewsTranslationSoftDeleteClosureModuleTest` 涓? `OK (5 tests, 27 assertions)`锛沗FrontNewsDetailRouteClosureModuleTest` 涓? `OK (4 tests, 44 assertions)`锛沗FrontLegacyRouteCompatibilityTest` 涓? `OK (14 tests, 1328 assertions)`锛沗FrontDashboardPaginationValidationClosureModuleTest` 涓? `OK (3 tests, 20 assertions)`锛沗FrontDashboardControllerCommentReadabilityTest` 涓? `OK (2 tests, 31 assertions)`銆?
- `LegacyFrontAuthenticate.php`銆並ernel銆丏ashboardController銆乺outes 鍧囬?氳繃 `php -l`銆?
- 鏈?缁堣鏍?/璐ㄩ噺澶嶆牳锛欳ritical=0銆両mportant=0銆?

### 鍓╀綑杈圭晫
- News/Dashboard 鏈疆瀹¤鍙戠幇鐨勮蒋鍒犻櫎缈昏瘧銆佸垎椤垫棩鏈熴?佽鎯呰矾鐢便?佺洿鎺ヨ鎯呴〉銆佺櫥褰曟潈闄愬拰鐑偣鍝嶅簲宸紓鍧囧凡閫愰」闂悎銆?
- 鏅?氱敤鎴蜂笅涓?楂橀闄╃洰鏍囪浆鍏? Deposit/Payment锛涚湡瀹為?氶亾閰嶇疆銆佺鍚嶅瘑閽ュ拰澶栭儴璧勯噾鎺ュ彛浠嶅繀椤讳互瀹夊叏 adapter 涓庡彲楠岃瘉鐘舵?佹満瀹炵幇锛屼笉鑳芥部鐢ㄥ綋鍓嶉?氱敤鍥炶皟鍋囨垚鍔熴??

## 354. 2026-07-11 鍓嶅彴鏀粯鍗遍櫓璺敱鍐荤粨涓庨?氱敤鍥炶皟澶辫触鍏抽棴

### 鏈澶勭悊鐩爣
- 绂佹鏃? GET 璇锋眰鍒涘缓鍏ラ噾璁㈠崟銆?
- 灏嗙涓夋柟寮傛閫氱煡涓庡悓姝? return 鐨? HTTP 鏂规硶涓ユ牸鍒嗙銆?
- 鍦ㄩ?愰?氶亾 adapter 鍜岄獙绛惧畬鎴愬墠锛岄樆姝换浣曢?氱敤鍥炶皟鎸夎鍗曞彿鐩存帴鍐欐垚鍔熺姸鎬併??
- 涓? web 涓殑绗笁鏂? POST 閫氱煡澧炲姞绮剧‘ CSRF 璞佸厤锛屼笉浣跨敤瀹芥硾閫氶厤绗︺??

### 璺敱鎵ц閾?
- `POST user/deposit_request` / `POST user/deposit_request_otc` 鈫? 鏃у叆閲戞彁浜? Controller锛汫ET 鐩存帴 405锛屼笉鍐嶄骇鐢熻鍗曘??
- legacy notify锛圥ayflash/Trustpay/Tiger/WP/Exlink/BTB/PassTo/Switch/OTC/OTC withdraw锛夆啋 浠? POST 鈫? 绮剧‘ CSRF 璞佸厤 鈫? `PaymentNotifyController::legacyCallback` 鈫? 鏃? URI 鏄犲皠缃戝叧 鈫? `notify`銆?
- legacy return锛圵P/Exlink/BTB/default return锛夆啋 浠? GET 鈫? `legacyCallback` 鈫? `returnPage` 鈫? `/front/deposit?status=pending`锛涘閮? status 鍙傛暟涓嶄細鏀瑰彉灞曠ず鎬佹垨璁㈠崟鐘舵?併??
- `POST /api/front/payment/notify/{gateway}` 鈫? 鏈煡缃戝叧 404锛涘凡鐭ユ棫缃戝叧鎴栧惎鐢ㄩ?氶亾浣嗙己 adapter/registry 422 鈫? 鏃ュ織鍙褰? gateway銆乸ath銆佽姹備綋鍝堝笇鍜屽師鍥? 鈫? 涓嶆煡璇€?佷笉鏇存柊 `deposit_records`銆?
- `GET /api/front/payment/return/{gateway}` 鈫? 绾〉闈㈣繑鍥烇紝涓嶅叿澶囨敮浠樻垚鍔熻瘉鏄庤兘鍔涖??

### 鏈鍙樻洿鏂囦欢
- `routes/web.php`
  - 鏃у缓鍗?/notify/return 浠? `GET|POST` 鏀剁揣涓烘槑纭? POST 鎴? GET锛屽苟鍚屾鏇存柊鍏ㄩ儴鏃у懡鍚? alias 鏂规硶銆?
- `app/Http/Middleware/VerifyCsrfToken.php`
  - 浠呭垪鍑? 12 涓湡瀹炵涓夋柟 notify URI锛涗笉璞佸厤寤哄崟鍜? return锛屼笉浣跨敤 `user/*` 鎴? `deposit_*`銆?
- `app/Http/Controllers/Front/PaymentNotifyController.php`
  - 鍒犻櫎鎸? `status=success` 鍜屾湰鍦拌鍗曞彿鐩存帴鍐? `02`/Unix payment_time 鐨勫嵄闄╅?昏緫銆?
  - 澧炲姞宸茬煡鏃х綉鍏崇櫧鍚嶅崟銆佹湭鐭? 404銆佹湭閰嶇疆 422 鍜岃劚鏁忔嫆缁濇棩蹇楋紱return 鍥哄畾 pending銆?
- `tests/Feature/FrontPaymentRouteSafetyClosureModuleTest.php`
  - 瑕嗙洊 HTTP 鏂规硶銆佹湭鐭?/鏈厤缃綉鍏炽?佽鍗曠姸鎬佷笉鍙樸?佺簿纭? CSRF 闆嗗悎銆佹棩蹇楁棤鍘? payload 鍜? return 涓嶄俊浠? status銆?
- `tests/Feature/FrontLegacyRouteCompatibilityTest.php`銆乣LegacyUiReplacementCoverageTest.php`銆乣FrontPaymentNotifyControllerCommentReadabilityTest.php`
  - 鏇存柊涓烘樉寮忓畨鍏ㄦ柟娉曘?佺湡瀹炲凡鍙戝竷鏂伴椈澶瑰叿鍜屾柊鐨勫け璐ュ叧闂鏄庛??

### TDD 涓庡鏍歌瘉鎹?
- RED锛氬畨鍏ㄦ祴璇曢娆? `4 tests / 4 failures`锛岃瘉鏄? GET 鍙缓鍗曘?丟ET 鍙?氱煡銆佹湭鐭ラ?氱煡杩斿洖鎴愬姛銆佹棤绛惧悕鍥炶皟灏濊瘯鍐欓敊璇? DATETIME 骞? 500銆?
- GREEN锛歚FrontPaymentRouteSafetyClosureModuleTest` 涓? `OK (4 tests, 35 assertions)`銆?
- 鍥炲綊锛歚FrontLegacyRouteCompatibilityTest` 涓? `OK (14 tests, 1325 assertions)`锛沗FrontendRouteManifestTest` 涓? `OK (21 tests, 76 assertions)`锛沗LegacyUiReplacementCoverageTest` 涓? `OK (69 tests, 1349 assertions)`锛涘洖璋冩敞閲婂绾︿负 `OK (2 tests, 16 assertions)`銆?
- routes銆乂erifyCsrfToken銆丳aymentNotifyController 鍧囬?氳繃 `php -l`銆?
- 鏈?缁堣鏍?/璐ㄩ噺澶嶆牳锛欳ritical=0銆両mportant=0銆?

### 鍓╀綑杈圭晫
- 褰撳墠鍥炶皟鏄畨鍏ㄧ殑澶辫触鍏抽棴锛屼笉浠ｈ〃鐪熷疄鏀粯鍙敤锛涘彧鏈夊悗缁? adapter銆佸綋鍓嶆湁鏁堝晢鎴?/瀵嗛挜銆侀噾棰濅笌绛惧悕楠岃瘉銆佺姸鎬佹満鍜岀粨绠? outbox 瀹屾垚鍚庯紝鎸囧畾閫氶亾鎵嶅彲鍚敤銆?
- 涓嬩竴鏀粯瀛愮洰鏍囪繘鍏? DECIMAL 閲戦銆両dempotency-Key銆佹湰鍦拌鍗曞敮涓?绱㈠紩銆侀?氶亾閰嶇疆瀹屾暣鎬у拰鏃? fallback 寤哄崟銆?

## 355. 2026-07-11 鍓嶅彴鏀粯绮剧‘閲戦銆佸箓绛夋湰鍦拌鍗曚笌澶辫触鍏抽棴

### 鏈澶勭悊鐩爣
- 鍏ラ噾閲戦鍙帴鍙楁櫘閫氬崄杩涘埗瀛楃涓诧紝鏈?澶氫袱浣嶅皬鏁帮紱鎷掔粷鏁板瓧 JSON銆佺瀛﹁鏁版硶銆佷笁浣嶅皬鏁般?侀潪姝ｆ暟鍜岃秴閰嶇疆杈圭晫鍊硷紝鏀粯閾捐矾涓嶄娇鐢? float 姣旇緝鎴栬绠椼??
- `PaymentOrderService` 鍦ㄤ簨鍔″唴鎸? `Idempotency-Key + user_id + gateway_code` 鍒涘缓鎴栧洖璇绘湰鍦拌鍗曪紱鐩稿悓閲戦杩斿洖鍘熻鍗曪紝涓嶅悓閲戦鏄庣‘鍐茬獊锛岃蒋鍒犻櫎璁板綍浠嶅崰鐢ㄥ箓绛夐敭銆?
- `deposit_records` 杞负 InnoDB锛屽苟灏? `amount/actual_amount` 鏀剁揣涓? `DECIMAL(18,2)`銆乣exchange_rate` 鏀剁揣涓? `DECIMAL(18,8)`锛涜ˉ鍏呮湰鍦拌鍗曞彿鍞竴绱㈠紩銆佹敮浠?/缁撶畻瀛楁鍜屽鍚堝箓绛夊敮涓?绱㈠紩銆?
- 鍒犻櫎鍐呯疆 fallback 閫氶亾閲嶅紑涓庢湰鍦? return URL 鍋囨垚鍔熴?俆ask 3 鐨勭櫧鍚嶅崟 adapter registry 灏氭湭钀藉湴鍓嶏紝鏁版嵁搴撻厤缃拰缃戝叧 URL 鍧囦笉鑳借Е鍙戝缓鍗曪紝缁熶竴杩斿洖 `OPERATION_NOT_ALLOWED`銆?

### 璺敱涓庢湇鍔￠摼
- `POST /api/front/deposits/submissions` / `POST user/deposit_request` 鈫? 褰撳墠鐢ㄦ埛瑙ｆ瀽 鈫? 鏅?氬崄杩涘埗瀛楃涓蹭笌鍏ㄥ眬/閫氶亾杈圭晫鏍￠獙 鈫? 鍙皟鐢ㄧ櫧鍚嶅崟 adapter 妫?鏌ワ紱娌℃湁鐪熷疄 adapter 鏃跺け璐ュ叧闂笖涓嶅啓 `deposit_records`銆?
- `PaymentOrderService::createOrRetrieve` 鈫? InnoDB 浜嬪姟 鈫? 鍖呭惈杞垹闄よ褰曠殑骞傜瓑閿姞閿佹煡璇? 鈫? 鍚岄噾棰濆洖璇汇?佷笉鍚岄噾棰濆啿绐? 鈫? 棣栨璇锋眰鍐欏叆绮剧‘ DECIMAL 閲戦鍜? pending 鏀粯/缁撶畻鐘舵?侊紱鍞竴閿珵鎬佸洖璇诲悗鍐嶆鏍稿閲戦銆?
- `GET /api/front/deposits/form-options` 鈫? 浠呭睍绀哄叿澶囧畬鏁翠笖鍙皟鐢ㄧ櫧鍚嶅崟 adapter 鐨勫惎鐢ㄩ?氶亾锛屼笉鍐嶄娇鐢ㄥ唴缃? fallback 閫氶亾銆?

### 鏈鍙樻洿鏂囦欢
- `app/Support/Money.php`锛氭櫘閫氬崄杩涘埗瀛楃涓茶鑼冨寲銆佸瓧绗︿覆杈圭晫姣旇緝鍙? BCMath 绮剧‘姹囩巼璁＄畻銆?
- `app/Services/Payment/PaymentOrderService.php`锛氫簨鍔″箓绛夊垱寤?/鍥炶銆佽蒋鍒犻櫎骞傜瓑鍩熷拰鍞竴閿珵鎬佹敹鏁涖??
- `database/migrations/2026_07_11_000003_harden_deposit_payment_orders.php`锛氬彲閲嶅鎵ц鐨勬暟鎹繚鐣欏瀷 schema 鍔犲浐銆?
- `app/Http/Controllers/Front/DepositController.php`銆乣app/Models/DepositRecord.php`锛氬け璐ュ叧闂?佺簿纭噾棰濆叆鍙ｃ?乫illable 涓? decimal casts銆?
- `tests/Feature/FrontDepositPaymentOrderIdempotencyClosureModuleTest.php`銆乣tests/Feature/FrontDepositOwnerBoundaryClosureModuleTest.php`锛氭柊瀹夊叏濂戠害涓庢棫鍋囨垚鍔熷绾︽洿鏂般??

### TDD 涓庨獙璇佽瘉鎹?
- RED锛氱洰鏍囨祴璇曢娆¤繍琛? `20 tests / 37 assertions / 1 error / 15 failures`锛岃瘉鏄? Money 缂哄け銆乫loat/numeric 杈撳叆浠嶈鎺ュ彈銆佸箓绛夐敭閲嶅寤哄崟銆佺己 adapter 浠嶅缓鍗曚笖 schema 浠嶄负 DOUBLE銆?
- GREEN锛歚FrontDepositPaymentOrderIdempotencyClosureModuleTest` 涓? `OK (21 tests, 59 assertions)`锛沗FrontDepositOwnerBoundaryClosureModuleTest` 涓? `OK (6 tests, 40 assertions)`锛沗FrontPaymentRouteSafetyClosureModuleTest` 涓? `OK (4 tests, 35 assertions)`銆?
- MySQL strict-mode schema 澶嶆牳纭 `deposit_records` 涓? InnoDB锛岄噾棰濆垪鍒嗗埆涓? `decimal(18,2)` / `decimal(18,2)` / `decimal(18,8)`锛屼袱涓敮涓?绱㈠紩鍒楅『搴忔纭紝涓斾細璇濆惎鐢? `STRICT_TRANS_TABLES`銆?

### 鍓╀綑杈圭晫
- Task 2 鍙畬鎴愮簿纭湰鍦拌鍗曞熀纭?涓庡け璐ュ叧闂紱鐪熷疄 provider create-order銆佺鍚嶃?佸洖璋冪姸鎬佹満鍜岀粨绠? outbox 浠嶇敱 Task 3-5 瀹炵幇銆傚湪鐧藉悕鍗? adapter 鍙皟鐢ㄥ墠锛屾敮浠樺叆鍙ｄ繚鎸佷笉鍙敤鏄鏈熷畨鍏ㄧ姸鎬併??

### Task 2 瑙勬牸澶嶆牳琛ュ己
- `PaymentOrderService` 鍙鏄庣‘鍛戒腑 `deposit_records_idempotency_user_gateway_unique` 鐨勫敮涓?閿紓甯告墽琛屽洖璇伙細MySQL 瑕佹眰 SQLSTATE `23000` 涓庨敊璇爜 `1062`锛孲QLite/PgSQL 浣跨敤鍚勮嚜鍞竴绾︽潫浠ｇ爜锛涘叾浠? `QueryException` 鍘熸牱鎶涘嚭銆?
- 绔炴?佹祴璇曚娇鐢ㄧ浜? MySQL 杩炴帴鐙珛鎻愪氦绔炰簤璁㈠崟锛岃鐩栦富浜嬪姟鍒涘缓鐐规姏鍞竴閿紓甯稿悗鐨勫悓閲戦澶嶇敤鍜屼笉鍚岄噾棰濆啿绐侊紝涓嶅啀浠ラ『搴忚皟鐢ㄤ唬鏇垮苟鍙戝垎鏀??
- 杞垹闄よ鍗曠户缁崰鐢ㄥ箓绛夐敭锛屼絾鏃犺閲戦鐩稿悓鎴栦笉鍚屽潎杩斿洖鏄庣‘鍐茬獊锛屼笉鑳戒綔涓哄彲缁х画鏀粯璁㈠崟閲嶆柊鎵撳紑銆?
- `Money` 澧炲姞 `DECIMAL(18,2)` 鍗佸叚浣嶆暣鏁颁笂闄愩?佹眹鐜囦箻绉孩鍑烘鏌ャ?佷弗鏍肩被鍨嬪０鏄庡拰 BCMath 缂哄け鏃剁殑鏄庣‘ `LogicException`銆?
- 杩佺Щ閲嶅鎵ц鏃朵細閲嶆柊鏍℃ MySQL 鍒楄鏍硷紱鍞竴绱㈠紩妫?娴嬭鐩? MySQL銆丼QLite銆丳ostgreSQL銆傞潪绌虹湡瀹? `local_order_no` 閲嶅浼氫腑姝㈣縼绉讳笖涓嶆敼鍙凤紝浠呯┖鏃у?兼寜 `LEGACY-DEP-{id}` 绋冲畾琛ラ綈銆?
- 琛ュ己 RED锛氱洰鏍囧浠朵负 `30 tests / 71 assertions / 9 failures`锛涜ˉ寮? GREEN锛歚OK (30 tests, 83 assertions)`銆?
- 涓诲箓绛夐摼鐨勨?滃悓閲戦杩斿洖鍘熻鍗曗?濅粎閫傜敤浜庢湭杞垹闄よ鍗曪紱鍛戒腑杞垹闄よ褰曟椂鏄槑纭殑瀹夊叏鍐茬獊渚嬪锛屼笉浼氳繑鍥炲彲缁х画鏀粯鐨勮鍗曘??
- 璺ㄩ┍鍔ㄦ壙璇轰粎瑕嗙洊鍞竴绱㈠紩瀛樺湪鎬ф娴嬩笌閲嶅 `up()`锛沗DECIMAL` 绮惧害銆乶ullable 鍜岄暱搴︾殑鑷姩鏍℃鍙鏈」鐩敓浜? MySQL 鎵胯锛孲QLite/PostgreSQL 涓嶄吉绉板凡鎵ц鍒楃被鍨嬫敼閫犮??
- 绌烘棫璁㈠崟鍙峰崌绾т細鍏堥璁＄畻鍏ㄩ儴 `LEGACY-DEP-{id}`锛岀粺涓?涓庢墍鏈夐潪绌虹湡瀹炶鍗曞彿鍙婂叾浠栧?欓?夋瘮杈冿紱鍙戠幇鍐茬獊鏃朵繚鎸侀浂鍐欏叆骞朵腑姝€?傝缂哄彛 RED 涓? `1 test / 1 failure`锛孏REEN 涓? `OK (1 test, 2 assertions)`銆?
- 鏈?缁? Task 2 鐩爣濂椾欢涓? `OK (31 tests, 85 assertions)`锛沷wner-boundary銆佽矾鐢卞畨鍏ㄥ拰杩佺Щ閲嶅叆缁х画鐙珛閫氳繃銆?
## 356. 2026-07-11 鏀粯缃戝叧娉ㄥ唽琛ㄤ笌寤哄崟閰嶇疆濂戠害闂幆

### 鏈瀹屾垚鑼冨洿

- 鏂板 `PaymentGatewayAdapter` 鍥涙柟娉曞绾︺?佷笉鍙彉 `PaymentOrderResult` 涓? `PaymentCallback`銆?
- 鏂板鍗曚緥 `PaymentGatewayRegistry`锛屾暟鎹簱浠呭厑璁搁厤缃櫧鍚嶅崟 alias锛涙湭娉ㄥ唽 alias銆佺鐢ㄩ?氶亾銆侀厤缃己椤广?乬ateway 涓嶅尮閰嶃?佸竵绉嶄笉鏀寔鍜? Task 4 灏氫笉瀛樺湪鐨? adapter 鍧囧け璐ュ叧闂??
- 瀹屾垚 Tiger銆乄P銆丒xlink FB/BB銆丅TB銆丳assTo銆丼witch銆丱TC alias锛涙棫 passageway 6/7 鍒嗗埆鍥哄畾 `pay_type=3/2`锛?9/10/11 鍒嗗埆鍥哄畾 `pay_type=1/2/3`銆?
- `POST /api/front/deposits/submissions` 鎵ц閾捐皟鏁翠负锛氱敤鎴蜂笌閲戦鏍￠獙 鈫? Registry 瑙ｆ瀽瀹屾暣閫氶亾 鈫? `PaymentOrderService` 骞傜瓑鏈湴璁㈠崟 鈫? 鍘熷瓙鎶㈠崰 `provider_create_in_progress` 鈫? adapter 鍒涘缓 provider 璁㈠崟 鈫? 淇濆瓨 provider 鍗曞彿涓庡畨鍏ㄧ粨鏋滃揩鐓? 鈫? 杩斿洖鍏煎瀛楁銆俻rovider 寮傚父鍐? `payment_status=provider_create_unknown`锛屼粎璁板綍璁㈠崟鍙枫?乬ateway 鍜屽紓甯哥被鍨嬶紝涓嶆硠闇插紓甯稿師鏂囨垨閰嶇疆銆?
- claim 鍚屾椂璁板綍 `provider_create_started_at/provider_create_attempts`锛涜秴杩? 15 鍒嗛挓鐨? in-progress 鍙浆 unknown锛岀粷涓嶈嚜鍔ㄥ啀娆? create銆倁nknown 鐢卞悗缁凡楠岀鍥炶皟鎴栧璐﹂摼纭锛岄伩鍏嶇涓夋柟宸插缓鍗曟椂閲嶅鍒涘缓銆?
- 鐩稿悓 Idempotency-Key 浠呭湪 `payment_status=pending` 涓斿瓨鍦ㄦ湁鏁? provider 鍗曞彿涓庡畨鍏ㄥ揩鐓ф椂鎭㈠鍘? `payment_url/form_action`锛屼笉鍐嶆璋冪敤 adapter锛泂uccess/failed/refunded/unknown/in-progress 鍧囦笉杩斿洖 checkout銆俙PaymentOrderService` 鏈煡绯荤粺寮傚父缁х画涓婃诞锛屼笉浼鎴愪笟鍔℃嫆缁濄??
- `GET /api/front/deposits/form-options` 鍙緭鍑? UI 鐧藉悕鍗曞瓧娈碉紝涓嶈緭鍑? merchant銆乻ecret/key reference 鎴栭厤缃? endpoint銆?
- 鍚庡彴閫氶亾 store/update 灏? textarea JSON 涓ユ牸瑙ｇ爜涓烘暟缁勶紱闈炴硶 JSON 鍜屾槑纭晱鎰熼敭杩斿洖 `VALIDATION_FAILED`銆俙*_reference/*_ref` 涓? Registry 鍏辩敤 `SecretReference`锛屽彧鎺ュ彈 `env:UPPER_SNAKE_NAME` 鎴? `vault:safe/path[#field]`锛沗sk-live-*` 绛夎８鍑嵁琚嫆缁濓紝`label_key/type_label_key` 绛夊叕寮?閰嶇疆涓嶈璇潃锛泃oggle 浠嶅彧缈昏浆 `is_enabled`銆?
- 淇 legacy Exlink `deposit_exlink_bbreturn`銆乣deposit_exlink_fbreturn` 鍥犱笉鍚? `_return` 鑰岃鍏? notify 鐨勯棶棰橈紝鏀逛负鏄惧紡 return URI 闆嗗悎銆?

### 鍏抽敭鍝嶅簲鍏煎

- 鎴愬姛寤哄崟缁х画杩斿洖鏃у墠绔渶瑕佺殑 `order_no`銆乣payment_url`銆乣open_blank`銆乣channel`锛屽苟澧炲姞 `provider_order_no`銆乣redirect_url`銆乣form_action`銆乣form_fields`銆?
- provider 鍒涘缓缁撴灉涓嶇‘瀹氭椂杩斿洖 `OPERATION_NOT_ALLOWED`锛屾湰鍦拌鍗曚繚鐣? `settlement_status=pending` 骞惰繘鍏? `provider_create_unknown`锛涘搷搴斾笉鍖呭惈 provider 寮傚父銆佸晢鎴峰彿銆佸瘑閽ュ紩鐢ㄦ垨绉佹湁閰嶇疆 endpoint銆?

### TDD 涓庨獙璇佽瘉鎹?

- Registry/DTO/Controller RED 渚濇璇佹槑绫荤己澶便?佹敞鍐屾柟娉曠己澶便?丏TO 缂哄け銆侀?氶亾闅愯棌銆乸rovider 澶辫触鏈暀鍗曘?佹棫鍝嶅簲瀛楁缂哄け銆侀噸澶嶈皟鐢? provider 鍜岀郴缁熷紓甯歌鍚炪??
- `PaymentGatewayRegistryTest`锛歚OK (37 tests, 103 assertions)`銆?
- `FrontDepositPaymentOrderIdempotencyClosureModuleTest`锛歚OK (31 tests, 85 assertions)`銆?
- `FrontDepositOwnerBoundaryClosureModuleTest`锛歚OK (6 tests, 40 assertions)`銆?
- `FrontPaymentRouteSafetyClosureModuleTest`锛歚OK (4 tests, 39 assertions)`銆?
- `AdminPaymentChannelToggleModuleTest`锛歚OK (14 tests, 47 assertions)`銆?
- `SecretReferenceTest`锛歚OK (9 tests, 9 assertions)`銆?
- `FrontendRouteManifestTest`锛歚OK (21 tests, 76 assertions)`銆?

### 鍚庣画杈圭晫

- Task 3 鍙缓绔嬪畨鍏ㄦ敞鍐屻?佸缓鍗曚笌閰嶇疆濂戠害锛涚湡瀹炵綉鍏冲瓧娈垫槧灏勩?佺鍚嶃?侀獙绛句笌鏃犵綉缁? fixture 鐢? Task 4 瀹屾垚銆?
- 鏀粯鎴愬姛鍥炶皟鐘舵?佹満銆侀噸澶嶅洖璋冦?佺粨绠? outbox 涓庤祫閲戝叆璐︿粛鐢? Task 5 瀹屾垚锛涘湪瀵瑰簲 adapter 鍜屽洖璋冮摼瀹屾垚鍓嶏紝榛樿鐢熶骇 alias 鍥犲疄鐜扮被涓嶅瓨鍦ㄧ户缁け璐ュ叧闂??

## 357. 2026-07-11 鏀粯缃戝叧鐪熷疄鍗忚 Adapter 涓庢棤缃戠粶 Fixture 闂幆

### 鏈瀹屾垚鑼冨洿

- 瀹屾垚 TigerPay銆乄P銆丒xlink Fiat銆丒xlink Crypto銆丅TB銆丳assTo銆丼witch 涓冪被鐪熷疄鍗忚 Adapter锛汷TC 鍥犳棫椤圭洰鏃犳硶璇佹槑瀹夊叏寤哄崟銆侀獙绛惧拰鍥炶皟鍗忚锛屾槑纭疄鐜颁负 unsupported/fail-closed锛屼笉浼?犲彲鐢ㄦ敮浠橀摼銆?
- 鎵?鏈? fixture 浠呭寘鍚櫄鏋? merchant銆佽鍗曞彿銆乁RL 鍜屾祴璇? secret锛汿iger 涓ょ粍 RSA 瀵嗛挜鍦ㄦ祴璇曡繍琛屾椂鍔ㄦ?佺敓鎴愶紝涓嶈鍙栥?佷笉澶嶅埗鏃ч」鐩? PEM 鎴栫敓浜у嚟鎹??
- 姣忎釜鍙敤 Adapter 鍧囦弗鏍兼牎楠? gateway銆乵erchant/app銆乴ocal order銆乤mount銆乧urrency銆乸rovider status锛涘绉扮鍚嶄娇鐢? `hash_equals`锛孴iger 浣跨敤 RSA PKCS#1 v1.5 鍒嗗潡鍔犺В瀵嗗拰 RSA-MD5 绛惧悕/楠岀銆?
- 瀹屾暣閰嶇疆闂ㄧ瑕嗙洊 endpoint銆乵erchant/app銆乺equest/callback key銆乧urrency銆乤mount unit銆乶otify/return route 鍜屽崗璁? profile锛涘紩鐢ㄦ牸寮忓悎娉曚絾 resolver 杩斿洖 null 鏃讹紝鍚屾牱鍦ㄤ换浣? HTTP 鍙戦?佸墠澶辫触鍏抽棴銆?
- 鎵?鏈夋暟缁勫紡 `Http::fake()` 鍧囧鍔? `* => 599` 澶辫触鍏滃簳锛屽苟鏂█鐪熷疄鐩爣 URL锛涙祴璇曚笉鍏佽鏈尮閰嶈姹傚洖钀藉缃戙??

### 璺敱涓庡缓鍗曟墽琛岄摼

- `POST /api/front/deposits/submissions` 鎴栨棫 `POST user/deposit_request` 鈫? `DepositController::submitDeposit` 鈫? 褰撳墠鐢ㄦ埛銆侀噾棰濄?侀?氶亾銆佸箓绛夐敭鏍￠獙 鈫? `PaymentGatewayRegistry::resolve` 鈫? `PaymentOrderService::createOrRetrieve` 鈫? 鍘熷瓙 claim `provider_create_in_progress` 鈫? 鍏蜂綋 Adapter `createOrder` 鈫? 淇濆瓨 provider order/result snapshot 鈫? 杩斿洖 checkout 鏁版嵁銆?
- WP Adapter 浼樺厛浣跨敤 transient `payer_mobile`锛岀己澶辨椂閫氳繃 `DepositRecord::user` 璇诲彇鐪熷疄 `user_infos.phone`锛沗DepositController` 涓嶅鍏ャ?佷笉鍒ゆ柇鍏蜂綋 Adapter 绫诲瀷銆?
- provider 寤哄崟寮傚父缁熶竴杞? `provider_create_unknown`锛屼笉鑷姩閲嶅缓鍗曪紱鏃ュ織浠呰褰曟湰鍦拌鍗曞彿銆乬ateway 鍜屽紓甯哥被锛屼笉璁板綍 secret銆佺鍚嶅師鏂囥?佸瘑鏂囨垨 provider 绉佹湁鍝嶅簲銆?

### 鍗忚閲戦涓庣鍚嶈竟鐣?

- TigerPay锛氬彂閫佹崲姹囧悗鐨? `actual_amount` 浣滀负 CNY `price`锛泂erver public key 鍔犲瘑涓氬姟 JSON锛宎pp private key 瀵硅鑼? URL-encoded data 绛惧悕銆傚洖璋冨悓鏃跺吋瀹瑰凡缂栫爜 fixture 涓? PHP percent-decoded 琛ㄥ崟鍊硷紝缁熶竴 canonical data 鍚庨獙绛撅紱ACK 涓? `SUCCESS`銆?
- WP锛氬彂閫? `actual_amount`銆佺湡瀹炴墜鏈哄彿銆佺敤鎴峰悕鍜屾湰鍦拌鍗曪紱璇锋眰绛惧悕涓哄瓧娈靛悕鎺掑簭鍚庣洿鎺ユ嫾鎺ュ苟杩藉姞 key 鐨勫ぇ鍐? SHA-1锛沜allback key 鐙珛楠岀銆?
- Exlink Fiat锛氬彂閫佹崲姹囧悗鐨? `actual_amount` 涓? pay type锛汦xlink Crypto锛氬缁堝彂閫佸師濮? `amount`锛屽嵆浣? exchange rate 闈? 1 涔熶笉鏀瑰啓 USDT 鏁伴噺锛涗袱鑰呭潎浣跨敤鎺掑簭 `key=value&...&key=secret` 灏忓啓 MD5銆?
- BTB锛氭祻瑙堝櫒 GET 璺宠浆绛惧悕 URL锛屼娇鐢ㄥ師濮? `amount`锛涘洖璋冨繀椤荤鍚嶃?佹垚鍔熺姸鎬佸強鍏ㄩ儴璁㈠崟韬唤鍖归厤銆?
- PassTo锛氬彂閫? `actual_amount` 鐨勫垎鍗曚綅鏁存暟锛屾帓搴忓瓧娈靛悗杩藉姞 key 骞朵娇鐢ㄥぇ鍐? MD5锛泇ersion 涓哄繀濉厤缃紝涓嶅啀闈欓粯琛ラ粯璁ゅ?笺??
- Switch锛氬彂閫佹崲姹囧悗鐨? `actual_amount` 涓? profile pay type锛岃姹傚拰 callback 浣跨敤鐙珛 key锛涢浂鏁存暟浣嶈鑼冨寲涓? `0.00`銆?
- OTC锛歚createOrder` 鍜? `parseCallback` 鎶? unsupported锛宍verifyCallback=false`锛孉CK 涓? HTTP 422 `UNSUPPORTED`锛岀粷涓嶈繑鍥? success/OK銆?

### TDD 涓庢渶缁堥獙璇佽瘉鎹?

- Tiger wire 浜屾缂栫爜銆佺湡瀹炶〃鍗? percent-decode銆侀噾棰濆瓧娈甸敊璇紱Exlink Crypto 姹囩巼鏀瑰啓锛沇P 鎺у埗鍣ㄥ叿浣撶被鑰﹀悎锛涘畬鏁撮厤缃?/鍙屽瘑閽ョ己澶憋紱Switch `.00` 绛夐棶棰樺潎鍏堝嚭鐜版槑纭? RED锛屽啀鍋氭渶灏忓疄鐜拌嚦 GREEN銆?
- `PaymentGatewayAdapterFixtureTest`锛歚OK (91 tests, 732 assertions)`銆?
- `PaymentGatewayRegistryTest`锛歚OK (37 tests, 106 assertions)`銆?
- `FrontDepositPaymentOrderIdempotencyClosureModuleTest`锛歚OK (32 tests, 90 assertions)`銆?
- `FrontDepositOwnerBoundaryClosureModuleTest`锛歚OK (6 tests, 40 assertions)`銆?
- `FrontPaymentRouteSafetyClosureModuleTest`锛歚OK (4 tests, 39 assertions)`銆?
- `FrontendRouteManifestTest`锛歚OK (21 tests, 76 assertions)`銆?
- `AdminPaymentChannelToggleModuleTest`锛歚OK (14 tests, 47 assertions)`銆?
- `AdminPaymentChannelRouteIdValidationClosureModuleTest`锛歚OK (4 tests, 26 assertions)`銆?
- `SecretReferenceTest`锛歚OK (9 tests, 9 assertions)`锛涘叏閮? Task 4 Adapter銆佹帶鍒跺櫒鍜屾祴璇? PHP 鏂囦欢閫氳繃 `php -l`銆?
- 鏈?缁堝叏浣撹鏍煎瀹′笌浠ｇ爜璐ㄩ噺缁堝鍧囦负 APPROVED锛孋ritical=0銆両mportant=0銆丮inor=0銆?

### 鍚庣画杈圭晫

- Task 4 鍙棴鍚? provider 寤哄崟銆佺鍚嶃?侀獙绛俱?佷弗鏍艰В鏋愪笌 ACK 濂戠害锛涘悎娉? callback 鐨勬敮浠樼姸鎬佹満銆侀噸澶嶆垚鍔熷箓绛夈?乫ailure-after-success銆佺粨绠? outbox銆丮T4/璧勯噾鍏ヨ处鍜岄噸璇曟仮澶嶄粛灞炰簬 Task 5銆?
- 鏈厤缃湡瀹? merchant銆乪ndpoint 鍜? secret reference 鐨勭敓浜ч?氶亾浠嶄笉鍙敤锛汷TC 鍦ㄨ幏寰楀彲楠岃瘉鐨勬寮忓崗璁墠淇濇寔澶辫触鍏抽棴銆?

## 358. 2026-07-11 鍓嶅彴鏀粯鍥炶皟銆佸叆閲戠粨绠椼?侀??娆鹃?嗗悜涓? Outbox 鎭㈠闂幆

### 瀹屾垚鑼冨洿

- 鏀粯鍥炶皟鎸夋湰鍦拌鍗曞彿鍔犻攣锛屼弗鏍兼牳瀵? gateway銆乵erchant銆乸rovider order銆乧urrency 涓庣簿纭? provider amount锛涢噸澶嶆垚鍔熷箓绛夛紝鎴愬姛鍚庡け璐ヤ笉鍥為??銆?
- 棣栨鎴愬姛鍦ㄥ悓涓?浜嬪姟鍐呭啓鍏ョ湡瀹? `payment_time`銆佹敮浠樼姸鎬佸拰鍞竴 `deposit_settlement` outbox锛涗簨鍔℃彁浜ゅ悗鍙戝竷闃熷垪锛屽彂甯冨け璐ョ敱姣忓垎閽? scanner 鎸佷箙鎭㈠銆?
- `SettleDepositPayment` 浣跨敤璐︽埛 USD `order.amount` 鍜岀ǔ瀹氭敞閲? `DBUN-{user_id}-#{local_order_no}`锛涘閮? MT4 鍏ラ噾濮嬬粓浣嶄簬鏁版嵁搴撲簨鍔″銆?
- 杩炴帴鍓嶅け璐ヨ繘鍏? `retryable`锛涘啓鍏ヤ笉纭畾銆佽鍙栬秴鏃躲?乻tale processing銆佸閮ㄦ垚鍔熷悗鏈湴鎻愪氦澶辫触鍧囪繘鍏? `unknown`锛岀姝㈣嚜鍔ㄩ噸澶嶈祫閲戞搷浣溿??
- provider 閫?娆惧湪缁撶畻鍓嶇粓姝㈠緟鎵ц鍏ラ噾锛涚粨绠楀悗鍒涘缓鍞竴 `deposit_refund` outbox锛岀敱 `RefundDepositPayment` 浣跨敤 `DBRF-{user_id}-#{local_order_no}` 鎵ц MT4 閫嗗悜骞惰褰? `refund_mt4_ticket/refund_time`銆?
- 閫?娆惧湪鍏ラ噾 processing 鏈熼棿鍒拌揪鏃跺厛 blocked锛氬叆閲戞垚鍔熷悗婵?娲婚?嗗悜锛屾槑纭湭鍙戦??/鎷掔粷鏃剁粓缁撲负鏃犻渶閫嗗悜锛屽叆閲戠粨鏋滀笉纭畾鏃跺弻鏂硅繘鍏ヤ汉宸ュ璐︾姸鎬併??
- scanner 鍚屾椂鎭㈠鍒版湡 pending/retryable 涓? `locked_at` 缂哄け鎴栬繃鏈熺殑 processing锛屽苟鎸? event type 鍒嗗彂姝ｇ‘ Job锛汮ob 鑷韩鍐嶆鏍￠獙 event type锛岄敊璇姇閫掑畨鍏? no-op銆?
- outbox 缂哄け璁㈠崟銆佽鍗曠粓鎬佷笉涓?鑷淬?侀敊璇富閿?/绱㈠紩銆侀儴鍒嗚縼绉诲拰鍘嗗彶 `provider_amount=NULL` 鍧囨湁鏄庣‘缁堟?併?佷慨澶嶆垨 fail-fast 杈圭晫銆?

### 鏁版嵁搴撲笌杩佺Щ涓嶅彉閲?

- `payment_settlement_outbox` 浣跨敤 InnoDB 鍏煎鐨? epoch 鏃堕棿鍒椼?佸敮涓? `(event_type, deposit_record_id)`銆乺eady 绱㈠紩鍜岃鍗曞彿绱㈠紩銆?
- 000006 閲嶅叆浼氶?愬垪琛ラ綈銆佸啀娆″洖濉? NULL provider amount銆佷弗鏍兼牎楠?/淇绱㈠紩瀹氫箟锛屽苟楠岃瘉 `id` 涓? `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY`锛涢潪绌洪敊璇粨鏋勫湪浠讳綍鍏朵粬 DDL 鍓嶅け璐ュ叧闂??
- 000007 澧炲姞 `refund_mt4_ticket` 涓庣湡瀹? DATETIME `refund_time`锛沗DepositRecord` 涓撶敤 mutator 閬垮厤鍩虹妯″瀷 Unix 鏃ユ湡鏍煎紡姹℃煋 DATETIME 鍐欏叆銆?

### 鏈?缁堥獙璇佽瘉鎹?

- `PaymentSettlementOutboxMigrationRerunClosureModuleTest`锛歚OK (8 tests, 23 assertions)`銆?
- `FrontPaymentCallbackStateMachineClosureModuleTest`锛歚OK (25 tests, 80 assertions)`銆?
- `SettleDepositPaymentJobClosureModuleTest`锛歚OK (27 tests, 126 assertions)`銆?
- `RefundDepositPaymentJobClosureModuleTest`锛歚OK (12 tests, 67 assertions)`銆?
- `DispatchPendingDepositSettlementsCommandClosureModuleTest`锛歚OK (1 test, 3 assertions)`銆?
- `Mt4DepositSettlementGatewayClosureModuleTest`锛歚OK (11 tests, 18 assertions)`銆?
- `Mt4DepositRefundGatewayClosureModuleTest`锛歚OK (6 tests, 13 assertions)`銆?
- `FrontPaymentRouteSafetyClosureModuleTest`锛歚OK (4 tests, 39 assertions)`銆?
- Task 5 fresh 鍚堣锛歚OK (94 tests, 369 assertions)`锛涜縼绉绘棤寰呮墽琛岄」锛宻canner 姣忓垎閽熸敞鍐岋紝鐩稿叧 PHP lint 鍏ㄩ儴閫氳繃銆?
- 鏈?缁堣鏍煎鏍镐笌浠ｇ爜璐ㄩ噺澶嶆牳锛欰PPROVED锛孋ritical=0銆両mportant=0銆丮inor=0銆?

## 359. 2026-07-12 鍚庡彴鎵归噺淇＄敤瀵煎叆 MT4 鍚屾闂幆

### 瀹屾垚鑼冨洿

- 琛ラ綈鏃ч」鐩? `BatchCreditController::creditImportExcel` 涓? `againCreditAmount` 涓湡瀹炰俊鐢ㄥ叆璐﹂摼璺湪鏂板悗鍙扮殑钀界偣銆?
- 鏂板 `admin_api_syncCreditImport`锛屽彧鍏佽 `credit_imports.is_synced=0` 鐨勫緟澶勭悊璁板綍鍙戣捣鐪熷疄 MT4 淇＄敤鍚屾銆?
- 鍚屾鍓嶅厛鎵ц `AdminDataScopeService` 鏁版嵁鑼冨洿杩囨护锛屽啀鐭殏 claim 涓哄唴閮ㄥ鐞嗕腑鎬? `3`锛岃繑鍥炲墠蹇呴』钀藉洖 `0/1/2`銆?
- `settled` 鍐欏叆 `is_synced=1` 涓庣湡瀹? `mt4_order_id`锛沗retryable_not_sent` 鍥炲埌寰呭鐞嗭紱`unknown/rejected` 鍐欏叆澶辫触鐘舵?佸拰鏈哄櫒閿欒鐮併??
- 澶辫触閲嶈瘯缁х画鍙妸澶辫触璁板綍鍥炲緟澶勭悊锛屼笉鐩存帴瑙﹀彂澶栭儴璧勯噾鍔ㄤ綔锛岄伩鍏嶉噸澶嶄俊鐢ㄥ叆璐︺??

### 璺敱涓庢墽琛岄摼

- `POST /api/admin/syncCreditImport/{id}` / `admin_api_syncCreditImport` 鈫? `BatchCreditImportController::syncCreditImport` 鈫? 璺敱鍙傛暟涓ユ牸鏁存暟鏍￠獙 鈫? `credit_imports.id` 鏌ヨ 鈫? 绠＄悊鍛樻暟鎹寖鍥磋繃婊? 鈫? 寰呭鐞嗙姸鎬佹牎楠? 鈫? claim `is_synced=3` 鈫? `CreditSettlementGateway::creditIn` 鈫? `Mt4ManagerService::creditIn` 鈫? MT4 `USER_CREDIT_IN` 鈫? `finishCreditImportSync` 鍐欏洖鍚屾缁撴灉銆?
- Layui锛歚resources/admin/layui/credit-imports/index.blade.php` 琛屾寜閽? `syncCreditImport` 鈫? `public/js/apps/admin/layui/pages.js` 鈫? `/api/admin/syncCreditImport/{id}`銆?
- CrmUI锛歚PageController` 涓? `credit-imports` 澧炲姞 `sync_import` 琛屽姩浣滐紝缁х画鐢? `module-page` 娓叉煋銆?
- Naive锛歚front-plain.js` 涓? `credit-imports` 澧炲姞 `syncImportEndpoint`锛屽鐢ㄩ?氱敤瀵煎叆鍚屾鍔ㄤ綔銆?

### 鏂囦欢涓庢潈闄?

- `app/Contracts/CreditSettlementGateway.php`锛氭柊澧炰俊鐢ㄥ悓姝ュ绾︺??
- `app/Services/Payment/Mt4CreditSettlementGateway.php`锛氭柊澧? MT4 淇＄敤鍚屾 gateway锛屽鐢ㄩ棴鍚堢粨鏋滃垎绫汇??
- `app/Services/Mt4ManagerService.php`锛氭柊澧? `creditIn()`锛屾寜鏃ч」鐩? `credit-in` 璇箟鏄犲皠鏂? Socket 鍛戒护 `USER_CREDIT_IN`銆?
- `app/Providers/Mt4ServiceProvider.php`锛氱粦瀹? `CreditSettlementGateway` 鍒扮敓浜? MT4 gateway銆?
- `database/migrations/2026_06_07_000005_add_admin_batch_credit_import_permissions.php`锛氭柊澧? `admin_batch_credit_import_sync` / `admin_api_syncCreditImport` 鏉冮檺銆?
- `docs/admin-legacy-migration-gap-audit.md`锛氭壒閲忎俊鐢ㄥ鍏ヤ粠鈥滈儴鍒嗚縼绉烩?濇洿鏂颁负鈥滃凡杩佺Щ鏍稿績闂幆鈥濄??

### 楠岃瘉璇佹嵁

- RED锛歚AdminBatchCreditImportMt4SyncClosureModuleTest` 棣栨杩愯澶辫触锛屽懡涓? `admin_api_syncCreditImport` 璺敱鍜? `CreditSettlementGateway` 濂戠害缂哄け銆?
- GREEN锛歚AdminBatchCreditImportMt4SyncClosureModuleTest`銆乣Mt4CreditSettlementGatewayClosureModuleTest`銆佹壒閲忎俊鐢ㄥ鍏ユ棦鏈夋ā鍧?/閲嶈瘯/鏉冮檺/鏁板瓧绛涢??/璺敱 ID銆佸鍏? UI 鍥炲綊閫氳繃銆?
- 鎵╁睍鍥炲綊锛歚FrontUiRegressionTest`銆乣CrmUiStackTest`銆乣AdminChineseCommentReadabilityTest`銆乣AdminZhCnLanguageReadabilityTest`銆乣AdminLegacyMigrationGapAuditTest` 閫氳繃銆?

## 360. 2026-07-12 鍚庡彴鍑洪噾娴佹按 COMMENT 鍒嗙被涓庢眹鎬婚棴鐜?

### 瀹屾垚鑼冨洿

- `mt4_trades` 琛ラ綈 `comment` 涓? `modify_time` 瀛楁锛屾壙鎺ユ棫椤圭洰 MT4 COMMENT 鏉ユ簮璇嗗埆鍙ｅ緞銆?
- `FundFlowController::withdrawFlowList` 鍜? `FundFlowController::exportWithdrawFlows` 缁熶竴浣跨敤 `cmd=6`銆乣open_price=0`銆乣profit<0`銆丆OMMENT 鍑洪噾鍏抽敭瀛椼?乣withdraw_source`銆乣user_id`銆乣ticket`銆佹棩鏈熻寖鍥村拰鍚庡彴鏁版嵁鑼冨洿杩囨护銆?
- 鍒楄〃杩斿洖 `data.list`銆乣data.totalRow`銆乣data.summary`锛屽苟涓烘瘡琛岃ˉ榻? `flow_source`銆乣flow_source_name`銆乣directTypeName`銆乣comment` 涓庡綋鍓嶇瓫閫夐噾棰濇眹鎬汇??
- CSV 瀵煎嚭浣跨敤鍚屼竴绛涢?夐摼璺紝杈撳嚭鏉ユ簮鍒嗙被銆佸娉ㄥ拰褰撳墠绛涢?夊悎璁¤銆?
- Layui銆丆rmUI銆丯aive 绠＄悊绔ˉ榻? `withdraw_source` 绛涢?夈?佹潵婧愬垪銆佸娉ㄥ垪鍜屾眹鎬诲瓧娈点??

### 璺敱涓庢墽琛岄摼

- `POST /api/admin/withdrawFlowList` / `admin_api_withdrawFlowList` 鈫? `FundFlowController::withdrawFlowList` 鈫? `validateUserIdFilter` 鈫? `newWithdrawFlowQuery` 鈫? `applyWithdrawFlowFilters` 鈫? `applyDataScope` 鈫? `withdrawFlowSummary` 鈫? `paginateQuery` 鈫? `formatWithdrawFlowRecord` 鈫? `success(['list','totalRow','summary'])`銆?
- `POST /api/admin/exportWithdrawFlows` / `admin_api_exportWithdrawFlows` 鈫? `FundFlowController::exportWithdrawFlows` 鈫? 鍚屼竴鏌ヨ鏋勫缓鍜屾暟鎹寖鍥磋繃婊? 鈫? `formatWithdrawFlowRecord` 鈫? 杩藉姞 `total` 鍚堣琛? 鈫? `csvDownload('withdraw_flows_export.csv')`銆?

### 娴嬭瘯璁板綍

- RED锛歚AdminWithdrawFlowCommentClassificationClosureModuleTest` 棣栨澶辫触锛屽懡涓? `mt4_trades.comment` 瀛楁缂哄け銆佸墠绔瓫閫夊垪缂哄け鍜屾枃妗ｇ己鍙ｃ??
- 鐩爣娴嬭瘯锛歚AdminWithdrawFlowCommentClassificationClosureModuleTest` 瑕嗙洊 COMMENT 鍒嗙被銆乣withdraw_source` 绛涢?夈?佸垪琛ㄦ眹鎬汇?佸鍑哄悎璁°?丩ayui/CrmUI/Naive 閰嶇疆鍜屾枃妗ｈ褰曘??
- 杈圭晫锛氭湭鍏ラ噾澶嶆潅鐘舵?佸垎绫汇?佽繍钀ヨ窡杩涚粺璁″拰璐㈠姟澶嶆牳姹囨?诲凡鍦ㄧ 361 鑺傝ˉ榻愶紱鏈妭鍚庣画鍙繚鐣欏鏉傝储鍔″鏍稿啓閾俱?佺湡瀹炴敮浠樼綉鍏崇姸鎬佸彉鏇村拰鏃ч」鐩湭纭娣卞眰娴佺▼銆?

## 361. 2026-07-25 鍚庡彴鏈叆閲戝鏉傜姸鎬佸垎绫讳笌杩愯惀姹囨?婚棴鐜?

### 瀹屾垚鑼冨洿

- `FundFlowController::undepositFlowList` 浠庘?滃彧杩斿洖寰呮敮浠樺垎椤佃褰曗?濆崌绾т负鈥滃垎椤佃褰? + `summary` + `totalRow`鈥濄??
- 姣忔潯 `deposit_records.status=01` 鏈叆閲戞祦姘存寜绛夊緟澶╂暟杩斿洖 `follow_status`銆乣follow_status_name` 鍜? `pending_days`銆?
- 鐘舵?佸垎妗跺惈涔夛細`new_pending` 琛ㄧず 0-1 澶╂柊鎻愪氦锛沗need_follow_up` 琛ㄧず 2-6 澶╅渶瑕佽繍钀ヨ窡杩涳紱`finance_review_required` 琛ㄧず 7 澶╁強浠ヤ笂闇?瑕佽储鍔″鏍搞??
- 褰撳墠绛涢?夋眹鎬昏繑鍥? `total_records`銆乣total_amount`銆乣new_pending_count`銆乣need_follow_up_count`銆乣finance_review_required_count`锛岀敤浜庨〉闈㈤《閮ㄧ粺璁″拰璐㈠姟鏍稿銆?
- `exportUndepositFlows` 澶嶇敤鍚屼竴鏌ヨ閾捐矾锛孋SV 杈撳嚭鐘舵?佸垎妗躲?佸緟澶勭悊澶╂暟鍜屽綋鍓嶇瓫閫夊悎璁¤銆?
- Layui銆丆rmUI銆丯aive 绠＄悊绔悓姝ュ睍绀? `follow_status_name` 涓? `pending_days`锛岄伩鍏嶄笁涓悗鍙板叆鍙ｅ睍绀哄彛寰勪笉涓?鑷淬??

### 璺敱涓庢墽琛岄摼

- `POST /api/admin/undepositFlowList` / `admin_api_undepositFlowList` 鈫? `FundFlowController::undepositFlowList` 鈫? `validateUserIdFilter` 鈫? `newUndepositFlowQuery` 鈫? `applyUndepositFlowFilters` 鈫? `applyDataScope` 鈫? `undepositFlowSummary` 鈫? `paginateQuery` 鈫? `formatUndepositFlowRecord` 鈫? `success(['list','totalRow','summary'])`銆?
- `POST /api/admin/exportUndepositFlows` / `admin_api_exportUndepositFlows` 鈫? `FundFlowController::exportUndepositFlows` 鈫? 鍚屼竴鏌ヨ鏋勫缓鍜屾暟鎹寖鍥磋繃婊? 鈫? `formatUndepositFlowRecord` 鈫? 杩藉姞 `total` 鍚堣琛? 鈫? `csvDownload('undeposit_flows_export.csv')`銆?

### 杩斿洖瀛楁涓枃鍚箟

- `follow_status=new_pending`锛氭柊鎻愪氦锛岄?氬父绛夊緟鏀粯鍥炶皟鎴栫敤鎴风户缁敮浠樸??
- `follow_status=need_follow_up`锛氳繍钀ヨ窡杩涳紝闇?瑕佽繍钀ヤ汉鍛樿Е杈剧敤鎴风‘璁ゆ槸鍚︾户缁敮浠樸??
- `follow_status=finance_review_required`锛氳储鍔″鏍革紝闇?瑕佹牳瀵归?氶亾寮傚父銆佹紡鍥炶皟鎴栭噸澶嶇敵璇枫??
- `pending_days`锛氳褰曚粠鍒涘缓鍒板綋鍓嶅凡缁忕瓑寰呯殑鑷劧澶╂暟銆?
- `summary.total_amount`锛氬綋鍓嶇瓫閫夋潯浠朵笅鎵?鏈夊緟鏀粯鍏ラ噾鐢宠閲戦鍚堣銆?

### 娴嬭瘯璁板綍

- RED锛歚AdminUndepositFlowSummaryClosureModuleTest` 棣栨澶辫触锛屽懡涓? `data.summary.total_records` 缂哄け鍜屾渶缁堟竻鍗曠己灏戞湰鑺傝褰曘??
- GREEN锛氳ˉ榻愭帶鍒跺櫒鐘舵?佸垎妗躲?佹眹鎬汇?佸鍑哄悎璁°?佸墠绔垪閰嶇疆銆佽瑷?鍖呭拰鏈妭娓呭崟鍚庯紝鐩爣娴嬭瘯搴旈?氳繃銆?

### 鍓╀綑杈圭晫

- 鏈疆涓嶈Е鍙戠湡瀹炴敮浠樼綉鍏炽?佷笉鏀瑰彉 `deposit_records.status`锛屽彧琛ラ綈寰呮敮浠樿褰曠殑杩愯惀鍒嗙被涓庢牳瀵规眹鎬汇??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鏅?氱敤鎴枫?佷唬鐞嗗晢鍜屽悗鍙扮鐞嗗憳鍏跺畠鍓╀綑鍏ュ彛銆?

## 362. 2026-07-25 鐣岄潰 Lucide 鍥炬爣缁熶竴涓庤〃鎯呯鍙锋竻鐞嗛棴鐜?

### 瀹屾垚鑼冨洿

- Naive 鍗曢〉鍚庡彴澹充笉鍐嶄娇鐢? `D`銆乣U`銆乣A` 绛夊崟瀛楁瘝浼浘鏍囷紝缁熶竴鏀逛负 `data-lucide` 鍥炬爣鍚嶏紝鐢辨湰鍦? Lucide vendor 娓叉煋銆?
- `lucide-bridge.js` 缁熶竴鎶? Layui 涓? Font Awesome 鏃х被鍚嶆ˉ鎺ュ埌 Lucide 鍥炬爣锛屽苟鎶婃棫鍒悕 `circle-down` 淇涓哄綋鍓? vendor 瀛樺湪鐨? `circle-arrow-down`銆?
- 鍓嶅彴蹇樿瀵嗙爜椤点?佸墠鍙拌祫鏂欓〉銆佸悗鍙伴椤佃鏄庡尯鍒犻櫎鍙琛ㄦ儏绗﹀彿锛屾敼鐢? Lucide `circle-check-big` 涓? `camera`銆?
- 鍓嶅彴 CSS 鍒犻櫎浼厓绱犻噷鐨勭鍙峰浘鏍囷紝鏀圭敱杈规銆佽儗鏅?佸瓧閲嶅拰 Lucide 鑺傜偣琛ㄨ揪鐘舵?侊紝閬垮厤瑙嗚浣撶郴娣风敤銆?
- 鏂板 `LucideIconAndEmojiPolicyTest`锛屾寔缁害鏉? Blade銆丯aive JS銆佸墠鍙? CSS 鍜屽悗鍙板３ CSS 涓嶈兘閲嶆柊鍐欏叆琛ㄦ儏绗﹀彿锛屼笖澹版槑鐨? Lucide 鍥炬爣蹇呴』瀛樺湪浜庢湰鍦? vendor銆?

### 鏂囦欢涓庨摼璺?

- `public/js/apps/naive-admin/front-plain.js`锛歚lucideIconHtml()` 璐熻矗杈撳嚭绋冲畾鐨? `data-lucide` 鑺傜偣锛宍refreshIcons()` 鍦ㄩ〉闈㈢墖娈垫覆鏌撳悗瑙﹀彂 `window.CrmIcons.refresh()`锛岃В鍐冲紓姝ラ〉闈㈠垏鎹㈠悗鍥炬爣鏈垵濮嬪寲鐨勯棶棰樸??
- `public/css/naive-admin/app.css`锛氫负 logo銆佽彍鍗曘?佷笅鎷夐」銆佹寜閽?佺粺璁″崱绛夊浘鏍囨彁渚涘浐瀹氬昂瀵革紝瑙ｅ喅鍥炬爣鏇挎崲鍚庡竷灞?鎶栧姩鍜屾枃瀛楁尋鍘嬮棶棰樸??
- `public/js/shared/lucide-bridge.js`锛氬湪鏃? Layui / Font Awesome 绫诲悕杩涘叆椤甸潰鏃剁粺涓?杞崲涓? Lucide 鍚嶇О锛岃繑鍥? `data-lucide` 娓叉煋鑺傜偣锛涙棫绫诲悕娌℃湁鏄犲皠鏃朵繚鎸佺┖鍊煎苟浜ょ粰椤甸潰鍘熼?昏緫鏆撮湶闂銆?
- `resources/views/front/auth/forget_password.blade.php`銆乣resources/views/front/profile/show.blade.php`銆乣resources/views/admin/dashboard/index.blade.php`锛氭樉寮忓紩鍏? Lucide 璧勬簮骞剁敤鍥炬爣鑺傜偣鏇挎崲鍘熻〃鎯呯鍙枫??
- `tests/Feature/LucideIconAndEmojiPolicyTest.php`锛氭壂鎻忓彲瑙? UI 婧愭枃浠讹紱瑙ｆ瀽 Naive 鍥炬爣閰嶇疆銆侀潤鎬? `data-lucide` 鍜屾ˉ鎺ユ槧灏勶紱鎶? kebab-case 鍥炬爣鍚嶈浆鎹负 vendor 鏆撮湶鐨? PascalCase 瀵煎嚭鍚嶅悗鏍￠獙瀛樺湪鎬с??

### 鎵ц缁撴灉涓枃鍚箟

- `visible ui sources do not contain emoji symbols` 閫氳繃锛氬綋鍓嶅彲瑙? UI 婧愭枃浠舵病鏈夌户缁啓鍏ヨ〃鎯呯鍙锋垨绗﹀彿鍖洪棿鍥炬爣銆?
- `naive shell uses lucide icon names instead of letter badges` 閫氳繃锛歂aive 鍚庡彴澹冲凡鏀逛负 Lucide 鍥炬爣浣撶郴锛屼笉鍐嶄緷璧栧瓧姣嶅窘鏍囥??
- `declared lucide icon names exist in bundled vendor` 閫氳繃锛氭墍鏈夊０鏄庣殑 Lucide 鍥炬爣鍚嶉兘鑳借褰撳墠鏈湴 vendor 鍖呰В鏋愶紝椤甸潰涓嶄細鍥犱负鍥炬爣鍚嶄笉瀛樺湪鑰岀┖鐧姐??

### 楠岃瘉璁板綍

- RED锛歚LucideIconAndEmojiPolicyTest` 棣栨澶辫触锛屽懡涓墠鍙? CSS 琛ㄦ儏绗﹀彿銆丯aive 鍗曞瓧姣嶄吉鍥炬爣锛屼互鍙? `circle-down` 涓嶅瓨鍦ㄤ簬鏈湴 Lucide vendor銆?
- GREEN锛氫慨姝? UI 婧愭枃浠跺拰娴嬭瘯鎻愬彇鑼冨洿鍚庯紝`php artisan test tests\Feature\LucideIconAndEmojiPolicyTest.php` 閫氳繃锛岀粨鏋滀负 `3 passed`銆?
- 璇硶楠岃瘉锛歚node --check public\js\apps\naive-admin\front-plain.js`銆乣node --check public\js\shared\lucide-bridge.js`銆乣php -l` 瀵规湰杞? Blade 涓庢祴璇曟枃浠跺潎閫氳繃銆?
- 绗﹀彿鎵弿锛歚rg -n -P '[\x{2600}-\x{27BF}\x{1F300}-\x{1FAFF}]' resources\views public\css\front public\css\naive-admin public\js\apps\naive-admin` 鏃犲尮閰嶃??

### 鍓╀綑杈圭晫

- 鏈疆鍙敹鏁涘浘鏍囦綋绯诲拰琛ㄦ儏绗﹀彿绛栫暐锛屼笉鏀瑰彉鏅?氱敤鎴枫?佷唬鐞嗗晢鎴栧悗鍙扮鐞嗗憳涓氬姟鏁版嵁璇诲啓銆?
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟杩佺Щ鍓╀綑涓氬姟闂幆锛屽苟鍦ㄦ渶缁堜腑鏂囧叏閲忚矾鐢遍摼璺姤鍛婁腑姹囨?绘湰鑺傝璁＄害鏉熴??

## 363. 2026-07-25 鍚庡彴瀹炴椂杩斾剑 COMMENT 绮剧‘璇嗗埆涓庡鍑洪棴鐜?

### 瀹屾垚鑼冨洿

- `RealtimeCommissionController` 浠庘?渀cmd=6` 涓? `profit>0` 鐨勬鍚戜綑棰濆?欓?夎褰曗?濆崌绾т负鏃ч」鐩疄鏃惰繑浣ｅ彛寰勶細蹇呴』鍚屾椂婊¤冻 `cmd=6`銆乣profit>0`銆乣comment` 鍛戒腑 `DBCN` 鎴? `-FY`銆?
- `ticket/order_id` 绛涢?夊悓鏃跺尮閰? `mt4_trades.ticket` 鍜? `mt4_trades.comment`锛岃В鍐虫棫椤圭洰婧愯鍗曞彿鍐欏湪 COMMENT 閲屾椂鍚庡彴鏃犳硶瀹氫綅杩斾剑璁板綍鐨勯棶棰樸??
- 鏃ユ湡鑼冨洿鎸夎繑浣ｇ‘璁ゆ椂闂磋繃婊わ紝鍚庣浼樺厛浣跨敤 `modify_time`锛岀己澶辨垨涓? 0 鏃跺洖閫?鍒? `close_time`锛屽吋瀹瑰巻鍙? MT4 鍚屾鏁版嵁銆?
- 鍒楄〃鍜屽鍑虹粺涓?鏍煎紡鍖栧瓧娈碉紝杩斿洖 `rebate_source`銆乣rebate_source_name`銆乣comment`銆乣modify_time`锛屽苟鎶婃暟鍊煎瀷 ID 鍜岃鍗曞彿杞负鏄庣‘鏁存暟銆?
- Layui銆丆rmUI銆丯aive 涓夊鍚庡彴鍏ュ彛閮藉睍绀鸿繑浣ｆ潵婧愩?丆OMMENT 鍜岃繑浣ｆ椂闂达紝CSV 瀵煎嚭澶嶇敤鍚屼竴绛涢?夐摼璺??

### 璺敱涓庢墽琛岄摼

- `POST /api/admin/realtimeCommissionList` / `admin_api_realtimeCommissionList` 鈫? `RealtimeCommissionController::realtimeCommissionList` 鈫? `validateUserIdFilter` 鈫? `baseRealtimeCommissionQuery` 鈫? `applyRebateCommentFilter` 鈫? `applyFilters` 鈫? `applyDataScope` 鈫? `paginateQuery` 鈫? `formatRealtimeCommissionRecord` 鈫? `success(['records','summary'])`銆?
- `POST /api/admin/exportRealtimeCommissions` / `admin_api_exportRealtimeCommissions` 鈫? `RealtimeCommissionController::exportRealtimeCommissions` 鈫? 鍚屼竴鍩虹鏌ヨ銆丆OMMENT 杩囨护銆佺瓫閫夊拰鏁版嵁鑼冨洿 鈫? `formatRealtimeCommissionRecord` 鈫? `csvDownload('realtime_commissions_export.csv')`銆?

### 杩斿洖瀛楁涓枃鍚箟

- `rebate_source=legacy_dbcn`锛氭棫 DBCN 璐︽埛杩斾剑锛岄?氬父鐢辨棫瀹炴椂杩斾剑浠诲姟鍐欏叆 MT4 COMMENT銆?
- `rebate_source=legacy_fy`锛氭棫 `-FY` 杩斾剑澶囨敞锛屽吋瀹规棫椤圭洰鏃╂湡鎴栧叾瀹冨叆鍙ｅ啓鍏ユ牸寮忋??
- `rebate_source_name`锛氶潰鍚戝悗鍙伴〉闈㈠睍绀虹殑杩斾剑鏉ユ簮涓枃鍚嶇О銆?
- `comment`锛歁T4 鍘熷 COMMENT锛岀敤浜庤储鍔℃牳瀵规簮璁㈠崟鍙枫?佷唬鐞嗗叧绯诲拰鏃т换鍔″箓绛夊娉ㄣ??
- `modify_time`锛氳繑浣ｇ‘璁ゆ椂闂达紱浼樺厛鍙? MT4 `modify_time`锛岀己澶辨椂鍥為??鍒? `close_time`銆?

### 娴嬭瘯璁板綍

- RED锛歚AdminRealtimeCommissionModuleTest` 鏂板 COMMENT 绮剧‘璇嗗埆鐢ㄤ緥鍚庨娆″け璐ワ紝鍛戒腑 CSV 鏈緭鍑? COMMENT銆佽鍗曠瓫閫夊彧鍖归厤 `ticket` 瀵艰嚧 COMMENT 鍐呮簮璁㈠崟鍙锋煡涓嶅埌銆?
- GREEN锛氳ˉ榻? COMMENT 鍏抽敭璇嶈繃婊ゃ?丆OMMENT 璁㈠崟鍙风瓫閫夈?佺粺涓?鏍煎紡鍖栧瓧娈点?佸鍑哄垪鍜屼笁濂楀悗鍙板垪閰嶇疆鍚庯紝`php artisan test tests\Feature\AdminRealtimeCommissionModuleTest.php` 閫氳繃锛岀粨鏋滀负 `9 passed`銆?

### 鍓╀綑杈圭晫

- 鏈疆涓嶈Е鍙戝疄鏃惰繑浣? MT4 鍏ヨ处銆佷笉鎵弿寰呰繑浣ｄ氦鏄撱?佷笉鏀瑰彉鍓嶅彴瀹炴椂杩斾剑缁撶畻浠诲姟锛屽彧鏀舵暃鍚庡彴鏌ヨ銆佹眹鎬诲拰瀵煎嚭鏍稿鍙ｅ緞銆?
- 鍚庣画缁х画杩佺Щ鏃ч」鐩洿娣卞眰鐨勮嚜鍔ㄧ粨绠楄仈鍔ㄣ?丮T4 瀹氭椂浠诲姟鍒嗙被鑱斿姩鍜屽叾瀹冨悗鍙?/浠ｇ悊/鏅?氱敤鎴峰墿浣欏叆鍙ｃ??

## 364. 2026-07-25 鍚庡彴浜ゆ槗鍘嗗彶骞充粨 COMMENT 寮哄钩绛涢?夐棴鐜?

### 瀹屾垚鑼冨洿

- `TradeController` 鐨勪氦鏄撳垪琛ㄣ?佸綋鍓嶆寔浠撱?佸巻鍙插钩浠撶瓫閫夊吋瀹规棫椤圭洰鍙傛暟锛歚userId`銆乣orderId`銆乣sym_symbol`銆乣startdate/enddate`銆?
- 鍘嗗彶骞充粨鎭㈠鏃ч」鐩? `is_coercion` 寮哄钩绛涢?夛細`Yes` 琛ㄧず `comment LIKE so%` 鐨勫己骞冲崟锛宍No` 琛ㄧず鎺掗櫎寮哄钩鍗曘??
- 鍘嗗彶骞充粨鍒嗛〉杩斿洖 `comment`銆佹棫 Blade 鍏煎瀛楁 `ordercomment` 鍜? `modify_time`锛屽苟鎸? `COALESCE(NULLIF(modify_time, 0), close_time)` 鍊掑簭銆?
- Layui 椤甸潰琛ラ綈璁㈠崟鍙枫?佸紑濮嬫棩鏈熴?佺粨鏉熸棩鏈熴?佸己骞崇姸鎬佺瓫閫夊拰鈥滃叏閮ㄤ氦鏄?/褰撳墠鎸佷粨/鍘嗗彶骞充粨鈥濇ā寮忔寜閽紱鎸夐挳鍥炬爣缁熶竴浣跨敤 Lucide銆?
- CrmUI 涓? Naive 鍚庡彴閰嶇疆鍚屾琛ラ綈绛涢?夐」銆丆OMMENT銆佹棫 `ordercomment` 鍜? `modify_time` 瀛楁銆?

### 璺敱涓庢墽琛岄摼

- `POST /api/admin/closedPositions` / `admin_api_closedPositions` 鈫? `TradeController::closedPositions` 鈫? `validateUserIdFilter` 鈫? `baseMt4TradeQuery` 鈫? `applyTradeFilters` 鈫? `applyForceCloseFilter` 鈫? `applyDataScope` 鈫? `paginateQuery` 鈫? `formatTradeRecord` 鈫? `success(['records','summary'])`銆?
- `POST /api/admin/openPositions` / `admin_api_openPositions` 鈫? `TradeController::openPositions` 鈫? 鍚屼竴鍩虹鏌ヨ涓庢棫鍙傛暟鍏煎绛涢?? 鈫? 鏈钩浠? `close_time is null or 0` 鈫? 鏁版嵁鑼冨洿 鈫? 鍒嗛〉涓庢眹鎬汇??
- `POST /api/admin/tradeList` / `admin_api_tradeList` 鈫? `TradeController::index` 鈫? 鍚屼竴鍩虹鏌ヨ涓庢棫鍙傛暟鍏煎绛涢?? 鈫? 鏁版嵁鑼冨洿 鈫? 鍒嗛〉涓庢眹鎬汇??

### 杩斿洖瀛楁涓枃鍚箟

- `comment`锛歁T4 鍘熷 COMMENT锛岀敤浜庤瘑鍒己骞炽?佷汉宸ュ钩浠撹鏄庡拰璐㈠姟鏍稿澶囨敞銆?
- `ordercomment`锛氭棫椤圭洰 Blade 琛ㄦ牸瀛楁鍚嶏紝鍊间笌 `comment` 涓?鑷达紝瑙ｅ喅鏃у墠绔?/鏃ф姤琛ㄨ鍙栧瓧娈靛悕涓嶄竴鑷寸殑闂銆?
- `modify_time`锛歁T4 淇敼鏃堕棿锛涘巻鍙插钩浠撲紭鍏堟寜瀹冩帓搴忥紝缂哄け鎴栦负 0 鏃跺洖閫? `close_time`銆?
- `is_coercion=Yes`锛氬彧杩斿洖 COMMENT 浠? `so` 寮?澶寸殑寮哄钩鍗曘??
- `is_coercion=No`锛氬彧杩斿洖 COMMENT 涓嶄互 `so` 寮?澶寸殑闈炲己骞冲钩浠撳崟銆?

### 娴嬭瘯璁板綍

- RED锛歚AdminTradeMt4PositionModuleTest::test_closed_positions_honor_legacy_force_close_filters_and_return_comment_fields` 棣栨澶辫触锛屾帴鍙ｈ繑鍥? 4 鏉¤?屼笉鏄? 1 鏉★紝鍛戒腑鏃х瓫閫夊弬鏁板拰寮哄钩绛涢?夋湭鐢熸晥銆?
- GREEN锛氳ˉ榻愭棫鍙傛暟璇诲彇銆佸己骞? COMMENT 绛涢?夈?佸钩浠撹褰曟牸寮忓寲鍜? `modify_time` 鎺掑簭鍚庯紝璇ョ湡瀹炴帴鍙ｆ祴璇曢?氳繃銆?
- RED锛歚AdminTradeMt4PositionModuleTest::test_trade_frontends_expose_legacy_closed_position_filters_and_comment_columns` 棣栨澶辫触锛屽懡涓? Layui Blade 缂哄皯 `ticket/start_date/end_date/is_coercion`銆?
- GREEN锛氳ˉ榻? Layui銆丆rmUI銆丯aive 涓夊鍓嶇閰嶇疆鍚庯紝璇ュ墠绔绾︽祴璇曢?氳繃銆?

### 鍓╀綑杈圭晫

- 褰撳墠鐪熷疄 `mt4_trades` 琛ㄤ粛鏃犳棫椤圭洰 `MARGIN_RATE` 瀛楁锛屾湰杞笉浼?? `MARGIN_RATE <> 0` 杩囨护銆?
- 浜ゆ槗鏄庣粏涓嬮捇銆侀闄╄仈鍔ㄥ拰浠ｇ悊鑼冨洿缁嗚妭浠嶉渶缁х画鎸夋棫椤圭洰閫愭壒杩佺Щ锛涘巻鍙叉垚浜ゅ鍑哄凡鍦ㄧ 366 鑺傝ˉ榻愩??

## 365. 2026-07-25 鍚庡彴浜ゆ槗瀹炵洏/娴嬭瘯鐩? orderType 绛涢?夐棴鐜?

### 瀹屾垚鑼冨洿

- `TradeController` 鏂板鏃ч」鐩祴璇曠洏鍒嗙粍鍚庣紑甯搁噺锛歚-TEST`銆乣-TEST-P`锛屽搴旀棫 `MY_Controller::TEST_DISK` 鍜? `MY_Controller::TEST_DISK_P`銆?
- `tradeList`銆乣openPositions`銆乣closedPositions` 涓変釜鍚庡彴浜ゆ槗鍏ュ彛缁熶竴璇诲彇 `orderType` 鎴? `order_type` 鍙傛暟锛岄伩鍏嶄笁澶勬帴鍙ｄ骇鐢熶笉鍚屽疄鐩?/娴嬭瘯鐩樺彛寰勩??
- `orderType=test_disk` 鍙繑鍥炲叧鑱? `user_infos.mt4_group` 浠? `-TEST` 鎴? `-TEST-P` 缁撳熬鐨? MT4 浜ゆ槗璁板綍銆?
- `orderType=real_disk` 鎺掗櫎娴嬭瘯鐩樼敤鎴凤紝淇濈暀娌℃湁杩佺Щ鍒? `user_infos` 鐨勫巻鍙? MT4 璁板綍锛屾壙鎺ユ棫椤圭洰鈥滈潪娴嬭瘯缁勫嵆鐪熷疄鐩樷?濈殑绛涢?夎涔夈??
- Layui銆丆rmUI 鍜? Naive 涓夊鍚庡彴鍏ュ彛鍧囪ˉ榻愬疄鐩?/娴嬭瘯鐩樼瓫閫夋帶浠讹紝閫夐」鏂囨閫氳繃涓嫳鏂囪瑷?鍖呯淮鎶わ紝鍥炬爣缁х画缁熶竴鐢? Lucide 娓叉煋銆?

### 璺敱涓庢墽琛岄摼

- `POST /api/admin/tradeList` / `admin_api_tradeList` 鈫? `TradeController::index` 鈫? `validateUserIdFilter` 鈫? `baseMt4TradeQuery` 鈫? `applyTradeFilters` 鈫? `applyOrderTypeFilter` 鈫? `applyDataScope` 鈫? `paginatedTradeRecords` 鈫? `success(['records','summary'])`銆?
- `POST /api/admin/openPositions` / `admin_api_openPositions` 鈫? `TradeController::openPositions` 鈫? 杩藉姞鏈钩浠撴潯浠? `close_time is null or 0` 鈫? 鍚屼竴 `applyOrderTypeFilter` 鈫? 杩斿洖褰撳墠鎸佷粨鍒嗛〉鍜屾眹鎬汇??
- `POST /api/admin/closedPositions` / `admin_api_closedPositions` 鈫? `TradeController::closedPositions` 鈫? 杩藉姞宸插钩浠撴潯浠? `close_time > 0` 鈫? 鍚屼竴 `applyOrderTypeFilter` 鈫? `applyForceCloseFilter` 鈫? 杩斿洖鍘嗗彶骞充粨鍒嗛〉鍜屾眹鎬汇??

### 鍙傛暟鍜岃繑鍥炵粨鏋滀腑鏂囧惈涔?

- `orderType=all` 鎴栫┖鍊硷細涓嶅尯鍒嗗疄鐩?/娴嬭瘯鐩橈紝杩斿洖鍏跺畠绛涢?夋潯浠跺懡涓殑鍏ㄩ儴浜ゆ槗璁板綍銆?
- `orderType=test_disk`锛氬彧杩斿洖娴嬭瘯鐩樿褰曪紱鎵ц缁撴灉浠ｈ〃璇ヤ氦鏄撹处鍙风殑 `user_infos.mt4_group` 鍚庣紑鍛戒腑浜? `-TEST` 鎴? `-TEST-P`銆?
- `orderType=real_disk`锛氬彧杩斿洖鐪熷疄鐩樿褰曪紱鎵ц缁撴灉浠ｈ〃璇ヤ氦鏄撹处鍙锋湭鍛戒腑娴嬭瘯鐩樺悗缂?锛屾垨璇ュ巻鍙? MT4 璁板綍灏氭棤鍙叧鑱旂敤鎴疯祫鏂欍??
- `records.data[].ticket`锛歁T4 璁㈠崟鍙凤紝鐢ㄤ簬鏍稿瀹炵洏/娴嬭瘯鐩樼瓫閫夊悗鍏蜂綋杩斿洖浜嗗摢浜涜鍗曘??
- `summary.total_orders`锛氬綋鍓嶇瓫閫夊懡涓殑璁㈠崟鎬绘暟锛屾祴璇曠洏/鐪熷疄鐩樺垏鎹㈠悗浼氬悓姝ュ彉鍖栥??
- `summary.total_profit`锛氬綋鍓嶇瓫閫夊懡涓殑鐩堜簭鍚堣锛岀敤浜庨獙璇佽鍗曞垎缁勫悗鐨勯噾棰濇眹鎬绘槸鍚﹂棴鐜??

### 涓轰粈涔堣繖鏍峰仛

- 鏃ч」鐩?氳繃 `data_list.mt4_grp REGEXP '.*-TEST$|.*-TEST-P$'` 鍖哄垎娴嬭瘯鐩橈紱鏂伴」鐩病鏈夌户缁娇鐢ㄦ棫 `data_list.mt4_grp` 浣滀负涓婚摼璺紝鍥犳蹇呴』閫夋嫨宸茬粡杩佺Щ涓旇兘鍏宠仈 MT4 鐧诲綍鍙风殑 `user_infos.mt4_group`銆?
- 涓変釜浜ゆ槗鎺ュ彛鍏辩敤 `applyTradeFilters`锛屽啀鐢? `applyOrderTypeFilter` 缁熶竴鎸傝浇鍒嗙粍鏉′欢锛岃В鍐充氦鏄撳垪琛ㄣ?佸綋鍓嶆寔浠撱?佸巻鍙插钩浠撳悓涓?绛涢?夊弬鏁拌繑鍥炵粨鏋滀笉涓?鑷寸殑闂銆?
- `real_disk` 浣跨敤鎺掗櫎娴嬭瘯鐩樺叧绯荤殑鏂瑰紡锛屾槸涓轰簡淇濈暀鏃ч」鐩腑鈥滀笉鍦ㄦ祴璇曠粍瀛愭煡璇㈠唴鍗冲綊鍏ョ湡瀹炵洏鈥濈殑鍘嗗彶鏁版嵁璇箟锛岄伩鍏嶈?? MT4 璁㈠崟鍥犱负璧勬枡缂哄け琚敊璇涪寮冦??

### 娴嬭瘯璁板綍

- RED锛歚AdminTradeMt4PositionModuleTest::test_trade_order_type_filter_uses_user_mt4_group_suffixes` 棣栨澶辫触锛宍orderType=test_disk` 杩斿洖浜嗙湡瀹炵洏璁㈠崟锛岃鏄庢帴鍙ｅ皻鏈壙鎺ユ棫娴嬭瘯鐩樺悗缂?瑙勫垯銆?
- RED锛歚AdminTradeMt4PositionModuleTest::test_trade_frontends_expose_legacy_closed_position_filters_and_comment_columns` 棣栨澶辫触锛孡ayui Blade 缂哄皯 `name="orderType"` 绛涢?夋帶浠躲??
- GREEN锛氳ˉ榻愭帶鍒跺櫒绛涢?夈?佸墠绔瓫閫夐」鍜岃瑷?鍖呭悗锛屼笂杩颁袱涓洰鏍囨祴璇曞潎閫氳繃銆?

### 鍓╀綑杈圭晫

- 褰撳墠鐪熷疄 `mt4_trades` 琛ㄤ粛鏃犳棫椤圭洰 `MARGIN_RATE` 瀛楁锛屾湰杞笉浼?? `MARGIN_RATE <> 0` 杩囨护銆?
- 鏈疆娌℃湁鏂板浜ゆ槗鏄庣粏涓嬮捇銆佺湡瀹? MT4 鏈嶅姟鍣ㄥ悓姝ユ垨鏁版嵁搴撶粨鏋勮皟鏁淬??

## 366. 2026-07-25 鍚庡彴浜ゆ槗鍘嗗彶骞充粨褰撳墠绛涢?? CSV 瀵煎嚭闂幆

### 瀹屾垚鑼冨洿

- 鏂板 `POST /api/admin/exportClosedPositions` / `admin_api_exportClosedPositions`锛岀敤浜庡鍑哄綋鍓嶇瓫閫夊懡涓殑鍘嗗彶骞充粨璁板綍銆?
- `TradeController::closedPositions` 涓? `TradeController::exportClosedPositions` 鍏辩敤 `closedPositionsQuery`锛屼繚璇佸垪琛ㄣ?佹眹鎬诲拰瀵煎嚭浣跨敤鍚屼竴濂楁棫椤圭洰绛涢?夊彛寰勩??
- CSV 瀵煎嚭瀛楁鍖呭惈 `ticket`銆乣login`銆乣symbol`銆乣cmd`銆乣volume`銆乣commission`銆乣swaps`銆乣profit`銆乣comment`銆乣ordercomment`銆乣open_time`銆乣close_time`銆乣modify_time`銆?
- Layui 鍚庡彴鏂板 `exportClosedPositions` 鎸夐挳锛屾惡甯﹀綋鍓嶆悳绱㈣〃鍗曞弬鏁板鍑猴紱CrmUI 鍜? Naive 鍚庡彴鍚屾閰嶇疆缁熶竴瀵煎嚭鍏ュ彛銆?
- 鏉冮檺杩佺Щ琛ラ綈 `admin_closed_positions_export`锛屽苟缁戝畾 `admin_api_exportClosedPositions`锛岄伩鍏嶅鍑烘帴鍙ｇ粫杩囧悗鍙版潈闄愪綋绯汇??

### 璺敱涓庢墽琛岄摼

- `POST /api/admin/exportClosedPositions` / `admin_api_exportClosedPositions` 鈫? `TradeController::exportClosedPositions` 鈫? `validateUserIdFilter` 鈫? `closedPositionsQuery` 鈫? `baseMt4TradeQuery` 鈫? `applyTradeFilters` 鈫? `applyOrderTypeFilter` 鈫? `applyForceCloseFilter` 鈫? `applyDataScope` 鈫? `orderByTradeTime(modify_time)` 鈫? `formatTradeRecord` 鈫? `csvDownload('closed_positions_export.csv')`銆?
- `closedPositionsQuery` 鍥哄畾杩藉姞 `close_time > 0`锛岃〃绀哄彧瀵煎嚭宸插钩浠撹褰曪紱瀵煎嚭鏈?澶氳繑鍥? 5000 琛岋紝閬垮厤涓?娆℃媺鍙栬繃澶氬巻鍙? MT4 鏁版嵁鎷栨參鍚庡彴銆?

### 鍙傛暟鍜岃繑鍥炵粨鏋滀腑鏂囧惈涔?

- `user_id/userId`锛歁T4 鐧诲綍璐﹀彿锛屽鍑哄墠鍏堝仛涓ユ牸鏁存暟鏍￠獙锛涢潪娉曟椂杩斿洖鏍￠獙澶辫触锛屼笉杈撳嚭 CSV銆?
- `ticket/orderId`锛歁T4 璁㈠崟鍙锋ā绯婄瓫閫夛紝鐢ㄤ簬鎸夋棫鍚庡彴璁㈠崟鍙峰揩閫熷畾浣嶅巻鍙叉垚浜ゃ??
- `symbol/sym_symbol`锛氫氦鏄撳搧绉嶇瓫閫夛紝鐢ㄤ簬鍙鍑烘寚瀹氫骇鍝佺殑鍘嗗彶骞充粨銆?
- `start_date/startdate`銆乣end_date/enddate`锛氬钩浠撴棩鏈熻寖鍥达紝瀵瑰簲 `mt4_trades.close_time`銆?
- `is_coercion=Yes`锛氬彧瀵煎嚭 COMMENT 浠? `so` 寮?澶寸殑寮哄钩鍗曪紱`No` 琛ㄧず瀵煎嚭闈炲己骞冲崟銆?
- `orderType=test_disk`锛氬彧瀵煎嚭 `user_infos.mt4_group` 浠? `-TEST` 鎴? `-TEST-P` 缁撳熬鐨勬祴璇曠洏璁㈠崟锛沗real_disk` 琛ㄧず鎺掗櫎娴嬭瘯鐩樸??
- `comment`锛歁T4 鍘熷澶囨敞锛沗ordercomment`锛氭棫椤圭洰 Blade 瀛楁鍚嶏紝鍊间笌 `comment` 涓?鑷达紝渚夸簬鏃ф姤琛ㄥ瓧娈靛鐓с??
- `closed_positions_export.csv`锛氭祻瑙堝櫒涓嬭浇鏂囦欢鍚嶏紝琛ㄧず鏈杩斿洖鐨勬槸鍘嗗彶骞充粨褰撳墠绛涢?夌粨鏋溿??

### 涓轰粈涔堣繖鏍峰仛

- 鏃ч」鐩钩浠撳垪琛ㄤ緷璧? `closeListSearch/closeListSearchV2` 鐨勬煡璇㈡潯浠讹紝鍚庡彴瀹為檯瀵煎嚭闇?瑕佸拰鍒楄〃缁撴灉涓?鑷达紱鎶婃煡璇㈠皝瑁呬负 `closedPositionsQuery` 鍙互娑堥櫎鈥滃垪琛ㄤ竴濂楁潯浠躲?佸鍑哄彟涓?濂楁潯浠垛?濈殑椋庨櫓銆?
- CSV 浣跨敤褰撳墠鐪熷疄 `mt4_trades` 瀛楁锛屼笉浼?犳棫椤圭洰灏氫笉瀛樺湪鐨? `MARGIN_RATE`锛岃缂哄け瀛楁缁х画浣滀负鐪熷疄鍓╀綑杈圭晫鏆撮湶銆?
- Layui銆丆rmUI銆丯aive 鍏辩敤鍚屼竴涓鍑鸿矾鐢憋紝瑙ｅ喅涓夊鍚庡彴鍏ュ彛鑳藉姏涓嶄竴鑷寸殑闂銆?

### 娴嬭瘯璁板綍

- RED锛歚AdminTradeMt4PositionModuleTest::test_closed_positions_export_route_permission_and_frontends_are_wired` 棣栨澶辫触锛屾彁绀? `admin_api_exportClosedPositions` 璺敱涓嶅瓨鍦ㄣ??
- RED锛歚AdminTradeMt4PositionModuleTest::test_closed_positions_export_endpoint_returns_current_filter_csv` 棣栨澶辫触锛宍/api/admin/exportClosedPositions` 杩斿洖 404銆?
- GREEN锛氳ˉ榻愯矾鐢便?佹帶鍒跺櫒瀵煎嚭銆佹潈闄愯縼绉汇?丩ayui 鎸夐挳銆丆rmUI/Naive 瀵煎嚭閰嶇疆鍚庯紝涓や釜瀵煎嚭娴嬭瘯鍧囬?氳繃銆?

### 鍓╀綑杈圭晫

- 褰撳墠鐪熷疄 `mt4_trades` 琛ㄤ粛鏃犳棫椤圭洰 `MARGIN_RATE` 瀛楁锛屾湰杞笉浼?? `MARGIN_RATE <> 0` 杩囨护銆?
- 鏈疆娌℃湁鏂板浜ゆ槗鏄庣粏涓嬮捇銆侀闄╄仈鍔ㄣ?佺湡瀹? MT4 鏈嶅姟鍣ㄥ悓姝ユ垨鏁版嵁搴撶粨鏋勮皟鏁淬??

## 367. 2026-07-26 鍚庡彴鏉冪泭姹囨?诲湪绾跨粨绠楅噾棰濈粺璁￠棴鐜?

### 瀹屾垚鑼冨洿

- `RightsSummaryController::rightsSummaryList` 鍦ㄥ師鏈夎处鎴锋暟銆佷綑棰濄?佸噣鍊笺?佷繚璇侀噾姹囨?诲熀纭?涓婃柊澧炵嚎涓婄粨绠楅噾棰濆瓧娈点??
- 鏂板 `online_settlement_deposit_amount`锛氬綋鍓嶇瓫閫夎寖鍥村唴 `deposit_records.status=02` 鐨勫凡鏀粯鍏ラ噾閲戦锛屼紭鍏堝彇 `actual_amount`锛屼负 0 鏃跺洖閫? `amount`銆?
- 鏂板 `online_settlement_withdraw_amount`锛氬綋鍓嶇瓫閫夎寖鍥村唴 `withdraw_records.status=2` 鐨勫凡瀹屾垚鍑洪噾閲戦锛屼紭鍏堝彇 `actual_amount`锛屼负 0 鏃跺洖閫? `apply_amount`銆?
- 鏂板 `online_settlement_commission_amount`锛氬綋鍓嶇瓫閫夎寖鍥村唴 `commission_records.settle_status=2` 鐨勫凡缁撶畻杩斾剑閲戦锛屼紭鍏堝彇 `real_amount`锛屼负 0 鏃跺洖閫? `commission_amount`銆?
- 鏂板 `online_settlement_net_amount`锛氭寜鈥滃凡鏀粯鍏ラ噾 - 宸插畬鎴愬嚭閲? + 宸茬粨绠楄繑浣ｂ?濊绠楀綋鍓嶇瓫閫夎寖鍥寸殑鍦ㄧ嚎鍑?缁撶畻棰濄??
- Layui 鏉冪泭姹囨?婚〉闈㈡柊澧? 4 涓眹鎬诲崱鐗囷紝Naive 鍚庡彴鏂板鍚屽悕 `summaryFields`锛屽悗绔瑷?鍖呭拰鍓嶇鍏变韩璇█鍖呰ˉ榻愬瓧娈垫枃妗堛??

### 璺敱涓庢墽琛岄摼

- `POST /api/admin/rightsSummaryList` / `admin_api_rightsSummaryList` 鈫? `RightsSummaryController::rightsSummaryList` 鈫? `validateNumericFilters` 鈫? `baseRightsQuery` 鈫? `applyFilters` 鈫? `AdminDataScopeService::apply` 鈫? `summaryFor` 鈫? `scopedUserIdQuery` 鈫? `sumScopedOnlineDepositAmount` / `sumScopedOnlineWithdrawAmount` / `sumScopedOnlineCommissionAmount` 鈫? `paginate` 鈫? `success(['records','summary'])`銆?
- `scopedUserIdQuery` 浣跨敤宸茬粡杩藉姞绛涢?夊拰鍚庡彴鏁版嵁鑼冨洿鐨? `rights_scope` 瀛愭煡璇紝鍙彇闈炵┖ `user_infos.user_id`锛岃В鍐虫眹鎬婚噾棰濅笌鍒楄〃鑼冨洿涓嶄竴鑷寸殑闂銆?

### 鍙傛暟鍜岃繑鍥炵粨鏋滀腑鏂囧惈涔?

- `mt4_group`锛歁T4 鍒嗙粍绛涢?夛紱鏈疆娴嬭瘯鐢ㄥ畠璇佹槑鍒楄〃澶栫敤鎴风殑鍏ラ噾銆佸嚭閲戝拰杩斾剑涓嶄細杩涘叆 summary銆?
- `online_settlement_deposit_amount`锛氬凡鏀粯鍏ラ噾鍚堣锛岃繑鍥? 0 琛ㄧず褰撳墠绛涢?夎寖鍥存病鏈夊凡瀹屾垚鍏ラ噾璁板綍銆?
- `online_settlement_withdraw_amount`锛氬凡瀹屾垚鍑洪噾鍚堣锛岃繑鍥? 0 琛ㄧず褰撳墠绛涢?夎寖鍥存病鏈夊畬鎴愬嚭閲戣褰曘??
- `online_settlement_commission_amount`锛氬凡缁撶畻杩斾剑鍚堣锛岃繑鍥? 0 琛ㄧず褰撳墠绛涢?夎寖鍥存病鏈夊凡缁撶畻杩斾剑璁板綍銆?
- `online_settlement_net_amount`锛氬湪绾垮噣缁撶畻棰濓紱姝ｆ暟琛ㄧず褰撳墠鑼冨洿绾夸笂鍑?娴佸叆锛岃礋鏁拌〃绀哄綋鍓嶈寖鍥寸嚎涓婂噣娴佸嚭銆?

### 涓轰粈涔堣繖鏍峰仛

- 鏃ч」鐩? `sum_agents_online_settlement_amount()` 浼氬厛缁熻杩斾剑銆佸叆閲戝拰鍑洪噾锛屽啀鐢熸垚鏉冪泭缁撶畻鏁版嵁锛涙柊椤圭洰褰撳墠涓嶅叿澶囧彲瀹夊叏鑷姩鎵ц鐨? MT4 鍐欏叆杈圭晫锛屽洜姝ゆ湰杞彧琛ュ彧璇婚噾棰濈粺璁★紝涓嶄吉閫犺嚜鍔ㄧ粨绠楁垚鍔熴??
- 涓夊紶璧勯噾琛ㄩ兘鎸夊綋鍓嶆潈鐩婂垪琛ㄨ寖鍥村唴鐨勪笟鍔＄敤鎴? ID 鑱氬悎锛岄伩鍏嶁?滈〉闈㈢湅鍒颁竴涓寖鍥达紝姹囨?诲崱鐗囩粺璁″彟涓?涓寖鍥粹?濈殑璐㈠姟瀵硅处椋庨櫓銆?
- 浣跨敤瀹為檯瀹屾垚鐘舵?佽繃婊わ紝瑙ｅ喅寰呮敮浠樺叆閲戙?佸緟澶勭悊鍑洪噾銆佸緟缁撶畻杩斾剑琚璁″叆鍦ㄧ嚎缁撶畻閲戦鐨勯棶棰樸??

### TDD 鎵ц璁板綍

- RED锛歚php artisan test tests\Feature\AdminRightsSummaryModuleTest.php --filter test_rights_summary_summary_includes_online_settlement_amounts_for_current_scope` 棣栨澶辫触锛宍online_settlement_deposit_amount` 杩斿洖 0锛岃鏄庡悗绔湭杩斿洖鍦ㄧ嚎缁撶畻閲戦銆?
- GREEN锛氳ˉ榻? `summaryFor` 鐨勪笁寮犺祫閲戣〃鑱氬悎鍜屽綋鍓嶈寖鍥寸敤鎴? ID 瀛愭煡璇㈠悗锛岃鎺ュ彛娴嬭瘯閫氳繃銆?
- RED锛歚php artisan test tests\Feature\AdminRightsSummaryModuleTest.php --filter test_rights_summary_page_renders_blade_controls` 棣栨澶辫触锛孊lade 缂哄皯 4 涓? `data-summary-field`銆?
- GREEN锛氳ˉ榻? Layui 姹囨?诲崱鐗囥?佽瑷?鍖呫?丯aive `summaryFields` 鍚庯紝椤甸潰鍜屽墠绔绾︽祴璇曢?氳繃銆?

### 鍓╀綑杈圭晫

- 鏈疆娌℃湁杩佺Щ鏃ч」鐩嚜鍔ㄧ‘璁ゅ嚭鍏ラ噾閫昏緫锛屼笉璋冪敤 MT4 鍏ラ噾鎴栧嚭閲戞帴鍙ｃ??
- 鏈疆娌℃湁鏂板鐪熷疄 MT4 鑷姩鍚屾浠诲姟锛屼篃涓嶆敼鍙? `rights_settlements` 鍐欏叆鐘舵?併??
- 鍚庣画鑻ヨ鎭㈠鑷姩缁撶畻锛屽繀椤诲厛鎸夌湡瀹? MT4 缃戝叧銆佸箓绛夎褰曞拰澶辫触閲嶈瘯閾捐矾鍗曠嫭 TDD 闂幆銆?

## 368. 2026-07-26 鍚庡彴鎸佷粨姹囨?讳唬鐞嗘爲浜ゆ槗姹囨?婚棴鐜?

### 瀹屾垚鑼冨洿

- `PositionSummaryController::positionSummaryList` 浠庘?滃彧鎸夊綋鍓嶈鐢ㄦ埛鑷韩 MT4 鐧诲綍鍙锋眹鎬烩?濆崌绾т负鈥滄寜灞曠ず琛岀敤鎴锋嫢鏈夌殑鎴愬憳鑼冨洿姹囨?烩?濄??
- 灞曠ず琛岀敤鎴锋湰韬細鏄犲皠涓? `owner_user_id = member_user_id`锛屼繚璇佹櫘閫氬鎴峰拰鏃犱笅绾т唬鐞嗕粛鑳界湅鍒拌嚜宸辩殑鎸佷粨姹囨?汇??
- 浠ｇ悊涓嬬骇鎴愬憳浼樺厛璇诲彇 `agent_descendants.agent_id / descendant_id` 闂寘琛紝鎵挎帴鏂伴」鐩凡鏈変唬鐞嗘爲鍏崇郴銆?
- 褰撻棴鍖呰〃缂哄け鏃ц縼绉绘暟鎹椂锛屼娇鐢? `user_infos.family_tree` 鍖归厤 `,浠ｇ悊ID,` 鍏滃簳姹囨?讳笅绾у鎴蜂氦鏄擄紝閬垮厤鏃ч」鐩彧杩佸叆瀹舵棌閾惧瓧娈垫椂浠ｇ悊琛岀粺璁′负 0銆?
- 鎴愬憳鏄犲皠浣跨敤 `union` 鍘婚噸锛岃В鍐抽棴鍖呰〃鍜? `family_tree` 鍚屾椂鍛戒腑鍚屼竴瀹㈡埛鏃惰鍗曟暟銆佹墜鏁般?佺泩浜忚閲嶅绱姞鐨勯棶棰樸??
- CrmUI 涓? Naive 鍚庡彴鎸佷粨姹囨?诲垪宸插悓姝ユ敼涓? `user_id`銆乣user_name`銆乣parent_id`銆乣account_type`銆乣mt4_group` 鍜? `total_*` 鑱氬悎瀛楁锛岄伩鍏嶇户缁娇鐢? `symbol/volume/profit/updated_at` 杩欑鍗曠瑪璁㈠崟鏄庣粏鍒椼??

### 璺敱涓庢墽琛岄摼

- `POST /api/admin/positionSummaryList` / `admin_api_positionSummaryList` 鈫? `PositionSummaryController::positionSummaryList` 鈫? `validateNumericFilters` 鈫? `buildTradeSummarySubquery` 鈫? `buildPositionScopeSubquery` 鈫? `buildOwnerTradeSummarySubquery` 鈫? `buildUserSummaryQuery` 鈫? `applyFilters` 鈫? `AdminDataScopeService::apply` 鈫? `positionSummaryTotals` 鈫? `paginate` 鈫? `success(['records','summary'])`銆?
- `POST /api/admin/exportPositionSummary` / `admin_api_exportPositionSummary` 鈫? `PositionSummaryController::exportPositionSummary` 鈫? 鍚屼竴绛涢?夊拰浠ｇ悊鏍戞眹鎬绘煡璇㈤摼璺? 鈫? `formatRow` 鈫? `csvDownload('position_summary_export.csv')`銆?
- `GET /admin-crmui/position-summary` 鈫? `CrmUi\Admin\PageController::show` 鈫? `positionSummaryColumns` / `positionSummaryMetrics` 鈫? 娓叉煋 CrmUI 鍚庡彴琛ㄦ牸鍒楀拰鎸囨爣銆?
- Naive 鍚庡彴杩涘叆 `position-summary` 椤甸潰 鈫? `public/js/apps/naive-admin/front-plain.js` 椤甸潰閰嶇疆 鈫? 璇锋眰 `/api/admin/positionSummaryList` 鈫? 鎸変唬鐞嗘爲姹囨?诲瓧娈垫覆鏌撹〃鏍煎拰姹囨?绘寚鏍囥??

### 鍙傛暟鍜岃繑鍥炵粨鏋滀腑鏂囧惈涔?

- `user_id`锛氱瓫閫夊睍绀鸿鐢ㄦ埛 ID锛涘懡涓唬鐞嗘椂杩斿洖璇ヤ唬鐞嗗強涓嬬骇瀹㈡埛浜ゆ槗姹囨?伙紝鍛戒腑瀹㈡埛鏃惰繑鍥炲鎴疯嚜韬氦鏄撴眹鎬汇??
- `user_name`锛氬睍绀鸿鐢ㄦ埛鍚嶇О锛岀敤浜庡悗鍙板揩閫熻瘑鍒唬鐞嗘垨瀹㈡埛銆?
- `parent_id`锛氬睍绀鸿鐢ㄦ埛涓婄骇 ID锛岀敤浜庢牳瀵逛唬鐞嗘爲鍏崇郴鏉ユ簮銆?
- `account_type`锛氳处鍙风被鍨嬶紝`1` 琛ㄧず浠ｇ悊锛屽叾瀹冨?艰〃绀烘櫘閫氬鎴锋垨涓氬姟璐﹀彿銆?
- `mt4_group`锛氱敤鎴疯祫鏂欎腑鐨? MT4 鍒嗙粍锛岀敤浜庡悗缁仈鍔ㄥ疄鐩?/娴嬭瘯鐩樻垨鏃? MT4 鐢ㄦ埛璧勬枡銆?
- `total_orders`锛氬綋鍓嶈鐢ㄦ埛鎴愬憳鑼冨洿鍐? MT4 浜ゆ槗璁㈠崟鏁伴噺锛涜繑鍥? 0 琛ㄧず璇ヨ寖鍥村唴娌℃湁鍛戒腑浜ゆ槗銆?
- `total_volume`锛氬綋鍓嶈鐢ㄦ埛鎴愬憳鑼冨洿鍐呬氦鏄撴墜鏁板悎璁★紝鐢ㄤ簬鏍稿鎸佷粨瑙勬ā銆?
- `total_profit`锛氬綋鍓嶈鐢ㄦ埛鎴愬憳鑼冨洿鍐呯泩浜忓悎璁★紱姝ｆ暟琛ㄧず鐩堝埄锛岃礋鏁拌〃绀轰簭鎹熴??
- `total_comm`锛氬綋鍓嶈鐢ㄦ埛鎴愬憳鑼冨洿鍐呮墜缁垂鍚堣銆?
- `total_swaps`锛氬綋鍓嶈鐢ㄦ埛鎴愬憳鑼冨洿鍐呭簱瀛樿垂鍚堣銆?
- `total_noble_metal`銆乣total_for_exca`銆乣total_crud_oil`銆乣total_index`銆乣total_currency`銆乣total_stock`锛氭寜 `symbol_prices` 鍝佺鍒嗙被缁熻鍑虹殑涓嶅悓浜у搧绫诲埆鎵嬫暟銆?
- `summary.total_accounts`锛氬綋鍓嶇瓫閫夊拰鏁版嵁鑼冨洿涓嬭繑鍥炵殑鐢ㄦ埛琛屾暟閲忋??

### 涓轰粈涔堣繖鏍峰仛

- 鏃ч」鐩寔浠撴眹鎬婚潰鍚戜唬鐞嗘爲缁熻锛屼笉鏄崟涓敤鎴锋槑缁嗚鍗曞垪琛紱鍙敤 `user_infos.user_id = mt4_trades.login` 浼氳浠ｇ悊琛屾紡鎺変笅绾у鎴蜂氦鏄撱??
- 鏂伴」鐩凡缁忔湁 `agent_descendants` 闂寘琛紝浣嗘棫杩佺Щ鏁版嵁鍙兘鍙繚鐣? `family_tree`锛屾墍浠ラ渶瑕佸弻璺緞璇诲彇锛屾墠鑳藉吋瀹规柊鏃т唬鐞嗗叧绯绘潵婧愩??
- 鑱氬悎鍓嶅厛鏋勯?? owner/member 鏄犲皠锛屽啀姹囨?? MT4 浜ゆ槗锛屽彲浠ヨ鍒楄〃銆佹眹鎬诲崱鐗囧拰 CSV 瀵煎嚭澶嶇敤鍚屼竴濂椾笟鍔″彛寰勶紝閬垮厤椤甸潰鍜屽鍑哄璐︿笉涓?鑷淬??
- 鍓嶇鍒楅厤缃繀椤讳笌鎺ュ彛杩斿洖瀛楁涓?鑷达紱缁х画娓叉煋 `symbol/volume/profit` 浼氭妸浠ｇ悊鏍戞眹鎬昏瀵兼垚璁㈠崟鏄庣粏锛屽奖鍝嶅悗鍙板垽鏂??

### TDD 鎵ц璁板綍

- RED锛歚php artisan test tests\Feature\AdminPositionSummaryModuleTest.php --filter test_position_summary_rolls_up_descendant_customer_trades_to_agent_row` 棣栨澶辫触锛屼唬鐞嗚 `total_orders=0`锛岃瘉鏄庢湭姹囨?? `agent_descendants` 涓嬬骇瀹㈡埛浜ゆ槗銆?
- GREEN锛氭柊澧? owner/member 鏄犲皠鍜屼唬鐞嗛棴鍖呰〃姹囨?诲悗锛岃娴嬭瘯閫氳繃銆?
- RED锛歚php artisan test tests\Feature\AdminPositionSummaryModuleTest.php --filter test_position_summary_uses_family_tree_fallback_when_descendant_rows_are_missing` 棣栨澶辫触锛岄棴鍖呰〃缂哄け鏃朵唬鐞嗚浠嶄负 0銆?
- GREEN锛氳ˉ榻? `user_infos.family_tree` 鍏滃簳璺緞骞剁敤 `union` 鍘婚噸鍚庯紝璇ユ祴璇曢?氳繃銆?
- RED锛歚php artisan test tests\Feature\FrontUiRegressionTest.php --filter test_admin_position_summary_rollup_fields_are_wired_across_crmui_and_naive` 棣栨澶辫触锛孋rmUI 缂哄皯 `data-key="user_name"`锛岃瘉鏄庡墠绔粛鏄棫鏄庣粏鍒椼??
- GREEN锛欳rmUI 涓? Naive 鍚屾鐪熷疄姹囨?诲垪鍜屾寚鏍囧悗锛屽墠绔绾︽祴璇曢?氳繃銆?
- RED锛歚php artisan test tests\Feature\AdminLegacyMigrationGapAuditTest.php --filter test_audit_document_does_not_keep_stale_position_summary_agent_tree_gap_text` 棣栨澶辫触锛屽璁℃枃妗ｇ己灏? `family_tree` 璇佹嵁骞朵繚鐣欐棫浠ｇ悊鏍戠己鍙ｆ弿杩般??
- GREEN锛氭洿鏂拌縼绉荤己鍙ｅ璁℃枃妗ｅ悗锛岃闃插洖閫?娴嬭瘯閫氳繃銆?

### 鍓╀綑杈圭晫

- 褰撳墠鐪熷疄 `mt4_trades` 琛ㄤ粛鏃犳棫椤圭洰 `MARGIN_RATE` 瀛楁锛屾湰杞笉浼?? `MARGIN_RATE <> 0` 杩囨护銆?
- 鏈疆娌℃湁鑱斿姩鏃ч」鐩? `MT4_USERS` 鐨勬洿澶氳处鎴疯祫鏂欏瓧娈碉紝涔熸病鏈夋柊澧炰氦鏄撴槑缁嗕笅閽绘垨椋庨櫓鑱斿姩銆?
- 鍚庣画鑻ョ户缁ˉ娣卞眰涓嬮捇锛屽繀椤诲厛纭鐪熷疄璺敱銆佺湡瀹炴潈闄愩?佹槑缁嗗瓧娈靛拰浠ｇ悊鏁版嵁鑼冨洿锛屽啀鎸? TDD 鍗曠嫭闂幆銆?

## 369. 2026-07-26 鍚庡彴鎸佷粨姹囨?绘棫涓嬬骇浠ｇ悊鍏ュ彛璇箟闂幆

### 瀹屾垚鑼冨洿

- 淇鏃у悗鍙板吋瀹硅矾鐢? `index/admin/order/v2/subAgentsListSearchV2` 鐨勭幇浠ｇ洰鏍囷紝浠庣函浠ｇ悊鏍戝垪琛? `admin_api_agentDescendants` 鏀瑰洖鎸佷粨姹囨?绘帴鍙? `admin_api_positionSummaryList`銆?
- `PositionSummaryController::positionSummaryList` 鍏煎鏃у弬鏁? `searchtype=subAgentsSearch` 涓? `userPId/user_pid`銆?
- 褰撴棫鍙傛暟鍛戒腑鏃讹紝鎺ュ彛鍙繑鍥? `userPId` 褰撳墠浠ｇ悊鑷韩鍜岀洿灞炰笅绾т唬鐞嗚锛涙瘡涓?琛屼粛澶嶇敤绗? 368 鑺傜殑浠ｇ悊鏍戜氦鏄撴眹鎬诲彛寰勩??
- `exportPositionSummary` 澶嶇敤鍚屼竴绛涢?夐摼璺紝鍥犳鏃у弬鏁颁笅椤甸潰鍒楄〃涓? CSV 瀵煎嚭淇濇寔涓?鑷淬??

### 璺敱涓庢墽琛岄摼

- `POST /index/admin/order/v2/subAgentsListSearchV2` 鈫? `LegacyAdminController::handle` 鈫? `targetRouteFor` 鈫? `admin_api_positionSummaryList` 鈫? `payloadForLegacyTarget` 淇濈暀 `searchtype/userPId` 鈫? `forwardToNamedRoute` 鈫? `POST /api/admin/positionSummaryList`銆?
- `POST /api/admin/positionSummaryList` 鈫? `PositionSummaryController::positionSummaryList` 鈫? `validateNumericFilters(userPId/user_pid)` 鈫? `legacySubAgentsParentId` 鈫? `applyUserFilters` 杩藉姞 `account_type=1` 涓? `(user_id=userPId OR parent_id=userPId)` 鈫? 浠ｇ悊鏍戜氦鏄撴眹鎬? 鈫? 鍒嗛〉涓? summary 杩斿洖銆?

### 鍙傛暟鍜岃繑鍥炵粨鏋滀腑鏂囧惈涔?

- `searchtype=subAgentsSearch`锛氭棫鍚庡彴涓嬬骇浠ｇ悊鎸佷粨姹囨?绘ā寮忥紝琛ㄧず鏌ョ湅鏌愪釜浠ｇ悊鍙婂叾鐩村睘涓嬬骇浠ｇ悊銆?
- `userPId/user_pid`锛氭棫鍚庡彴浼犲叆鐨勭埗绾т唬鐞? ID锛岃繃婊ょ洰鏍囦唬鐞嗚嚜韬拰鐩村睘涓嬬骇浠ｇ悊銆?
- `records.data[]`锛氬綋鍓嶄唬鐞嗕笌鐩村睘涓嬬骇浠ｇ悊鐨勬眹鎬昏锛涙瘡琛岀殑 `total_orders`銆乣total_volume`銆乣total_profit` 绛変粛琛ㄧず璇ヤ唬鐞嗗畬鏁存垚鍛樿寖鍥村唴鐨? MT4 浜ゆ槗鑱氬悎銆?
- `summary`锛氬綋鍓嶇瓫閫夊嚭鏉ョ殑杩欎簺浠ｇ悊琛岀殑姹囨?诲悎璁★紝鐢ㄤ簬椤甸潰椤堕儴鍗＄墖銆?

### 涓轰粈涔堣繖鏍峰仛

- 鏃ч」鐩? `subAgentsListSearchV2` 鐨勪笟鍔＄粨鏋滄槸鈥滀笅绾т唬鐞嗘寔浠撴眹鎬烩?濓紝涓嶆槸鈥滀唬鐞嗘爲鎴愬憳娓呭崟鈥濓紱杞埌 `admin_api_agentDescendants` 浼氫涪澶变氦鏄撻噾棰濄?佹墜鏁般?佺泩浜忓拰鍝佺鍒嗙被銆?
- 鏃у弬鏁板彧鍦? `searchtype=subAgentsSearch` 鏃剁敓鏁堬紝閬垮厤褰卞搷鏅?? `user_id`銆乣parent_id` 鎴栧叏閲忔寔浠撴眹鎬荤瓫閫夈??
- 鍚屾椂鏀寔 `userPId` 鍜? `user_pid`锛屾槸涓轰簡鍏煎鏃? Blade銆佹棫 Ajax 鍜屽彲鑳藉瓨鍦ㄧ殑铔囧舰鍙傛暟杞彂銆?

### TDD 鎵ц璁板綍

- RED锛歚php artisan test tests\Feature\AdminLegacyRouteSemanticClosureTest.php --filter test_high_risk_legacy_uris_map_to_semantic_targets` 棣栨澶辫触锛屾棫 URI 瀹為檯鐩爣涓? `admin_api_agentDescendants`銆?
- GREEN锛歚LegacyAdminController` 灏? `index/admin/order/v2/subAgentsListSearchV2` 鏀逛负 `admin_api_positionSummaryList` 鍚庯紝璺敱璇箟娴嬭瘯閫氳繃銆?
- RED锛歚php artisan test tests\Feature\AdminPositionSummaryModuleTest.php --filter test_position_summary_legacy_sub_agents_search_returns_parent_and_direct_agent_rollups` 棣栨澶辫触锛屾棫鍙傛暟鏈敓鏁堬紝鎺ュ彛杩斿洖鍏跺畠鍏ㄩ噺鐢ㄦ埛琛屻??
- GREEN锛氳ˉ榻? `legacySubAgentsParentId` 鍜屾棫涓嬬骇浠ｇ悊绛涢?夊悗锛屾帴鍙ｅ彧杩斿洖褰撳墠浠ｇ悊鍜岀洿灞炰笅绾т唬鐞嗕袱琛岋紝骞朵笖涓よ閮芥眹鎬讳笅绾у鎴蜂氦鏄撱??
- RED锛歚php artisan test tests\Feature\AdminLegacyMigrationGapAuditTest.php --filter test_audit_document_records_legacy_admin_position_sub_agents_route_semantics` 棣栨澶辫触锛屽璁℃枃妗ｆ湭璁板綍 `searchtype=subAgentsSearch` 涓? `userPId` 璇箟銆?
- GREEN锛氭洿鏂拌縼绉荤己鍙ｅ璁℃枃妗ｅ悗锛岃闃插洖閫?娴嬭瘯閫氳繃銆?

### 鍓╀綑杈圭晫

- 鏈疆鍙慨姝ｆ棫鍚庡彴涓嬬骇浠ｇ悊鎸佷粨姹囨?诲叆鍙ｏ紝涓嶆柊澧炰氦鏄撴槑缁嗕笅閽婚〉闈€??
- 鏃? `MARGIN_RATE` 鍜? `MT4_USERS` 娣卞眰瀛楁浠嶆寜绗? 368 鑺傝竟鐣岀户缁繚鐣欍??

## 369.1. 2026-07-28 鍚庡彴鎸佷粨姹囨?讳唬鐞嗛捇鍙栧墠绔棴鐜?

### 鏈澶勭悊鐩爣

- 琛ラ綈鏃ч」鐩? `position_summary_list_v2.blade.php` 涓偣鍑讳唬鐞嗚缁х画鏌ョ湅鐩村睘涓嬬骇鎸佷粨姹囨?荤殑鍓嶇鍏ュ彛銆?
- 璁? Layui 鍚庡彴鍜? CrmUI 鍚庡彴閮借兘鎶婂墠绔偣鍑昏浆鎹负鍚庣宸插吋瀹圭殑 `searchtype=subAgentsSearch` 涓? `userPId` 鍙傛暟銆?
- 淇濊瘉鍒楄〃鍒锋柊銆佸綋鍓嶇瓫閫? CSV 瀵煎嚭鍜岃繑鍥炴牴绾ф椂浣跨敤鍚屼竴濂楃瓫閫夊弬鏁帮紝閬垮厤椤甸潰鍒楄〃涓庡鍑虹粨鏋滀笉涓?鑷淬??

### 鏈鍙樻洿鏂囦欢

- `tests/Feature/AdminPositionSummaryDrilldownFrontendClosureModuleTest.php`锛氭柊澧炲墠绔绾︽祴璇曪紝鍏堢害鏉? Layui 闅愯棌瀛楁銆佽矾寰勫鍣ㄣ?侀捇鍙栦簨浠跺拰 CrmUI 鏈湴琛屾搷浣滃０鏄庛??
- `resources/admin/layui/position-summary/index.blade.php`锛氭柊澧? `searchtype`銆乣userPId` 闅愯棌瀛楁銆乣positionSummaryPath` 璺緞鏉″拰 Lucide 鍥炬爣鎸夐挳銆?
- `public/js/apps/admin/layui/pages.js`锛氭柊澧? `positionSummaryDrilldown`銆乣currentPositionSummaryFilters`銆佽矾寰勬洿鏂般?佽繑鍥炴牴绾у拰 Lucide 娓叉煋閫昏緫銆?
- `app/Http/Controllers/CrmUi/Admin/PageController.php`锛氫负 `position-summary` 澧炲姞 `position_summary_drilldown` 鏈湴琛屾搷浣滐紝骞堕?忎紶 `extraPayload`銆?
- `resources/admin/crmui/partials/module-page.blade.php`锛氳鎿嶄綔鎸夐挳鏂板 `data-extra-payload`锛岀敤浜庢妸鍥哄畾涓氬姟鍙傛暟浜ょ粰鍓嶇鑴氭湰銆?
- `public/js/apps/crmui/admin.js`锛氭柊澧炴湰鍦拌鎿嶄綔鎵╁睍鍙傛暟瑙ｆ瀽銆侀〉闈㈤檮鍔犵瓫閫夊悎骞躲?佹寔浠撴眹鎬讳唬鐞嗛捇鍙栭噸杞藉拰閲嶇疆娓呯悊閫昏緫銆?
- `resources/lang/zh-CN/crmui.php`銆乣resources/lang/en/crmui.php`锛氳ˉ榻? CrmUI 琛屾搷浣滃拰纭鏂囨锛岄伩鍏嶆樉绀哄師濮? key銆?
- `docs/admin-legacy-migration-gap-audit.md`锛氭洿鏂版寔浠撴眹鎬昏縼绉昏瘉鎹拰鍓╀綑杈圭晫銆?

### 璺敱涓庢墽琛岄摼

- Layui 椤甸潰锛歚GET /admin/position-summary` 鈫? `resources/admin/layui/position-summary/index.blade.php` 鈫? 鐢ㄦ埛鐐瑰嚮浠ｇ悊琛? `lay-event="positionSummaryDrilldown"` 鈫? `public/js/apps/admin/layui/pages.js::positionSummaryDrilldown` 鈫? 鍐欏叆闅愯棌瀛楁 `searchtype=subAgentsSearch`銆乣userPId=褰撳墠浠ｇ悊 user_id` 鈫? `POST /api/admin/positionSummaryList` 鈫? `PositionSummaryController::positionSummaryList` 鈫? 鏃т笅绾т唬鐞嗘ā寮忕瓫閫夊綋鍓嶄唬鐞嗗拰鐩村睘涓嬬骇浠ｇ悊 鈫? 杩斿洖 `records + summary`銆?
- Layui 瀵煎嚭锛氶〉闈㈠浜庨捇鍙栫姸鎬? 鈫? `exportPositionSummary` 璋冪敤 `currentPositionSummaryFilters` 鈫? `POST /api/admin/exportPositionSummary` 鈫? 鍚庣澶嶇敤鍚屼竴绛涢?夐摼璺? 鈫? 杩斿洖 `position_summary_export.csv`銆?
- CrmUI 椤甸潰锛歚GET /admin-crmui/position-summary` 鈫? `CrmUi\Admin\PageController::show` 鈫? `rowActions` 澹版槑 `position_summary_drilldown` 鈫? `module-page.blade.php` 杈撳嚭 `data-extra-payload` 鈫? 鐢ㄦ埛鐐瑰嚮浠ｇ悊琛屾寜閽? 鈫? `public/js/apps/crmui/admin.js::positionSummaryDrilldown` 鈫? 鍐欏叆椤甸潰闄勫姞绛涢?? `searchtype/userPId` 鈫? `loadPage` 閲嶈浇 `admin_api_positionSummaryList`銆?

### 鍙傛暟鍜岃繑鍥炵粨鏋滀腑鏂囧惈涔?

- `searchtype=subAgentsSearch`锛氭棫鍚庡彴涓嬬骇浠ｇ悊鎸佷粨姹囨?绘ā寮忥紝琛ㄧず鏈鏌ヨ涓嶆槸鍏ㄩ噺鍒楄〃锛岃?屾槸鏌ョ湅鏌愪釜鐖朵唬鐞嗚妭鐐广??
- `userPId`锛氭棫鍚庡彴鐖朵唬鐞? ID锛屽彇鑷鐐瑰嚮琛岀殑 `row.user_id`銆?
- `records.data[]`锛氬綋鍓嶇埗浠ｇ悊鑷韩鍜岀洿灞炰笅绾т唬鐞嗙殑鎸佷粨姹囨?昏銆?
- `summary`锛氬綋鍓嶉捇鍙栫瓫閫夌粨鏋滅殑鎬昏处鍙枫?佽鍗曘?佹墜鏁般?佺泩浜忋?佹墜缁垂鍜屽簱瀛樿垂鍚堣銆?
- 绌? `searchtype/userPId`锛氳〃绀鸿繑鍥炴櫘閫氭寔浠撴眹鎬荤瓫閫夛紝涓嶈繘鍏ユ棫涓嬬骇浠ｇ悊妯″紡銆?

### 涓轰粈涔堣繖鏍峰仛

- 鍚庣宸茬粡鍏煎鏃? `subAgentsSearch` 璇箟锛屼絾鍓嶇娌℃湁鍏ュ彛浼氬鑷翠笟鍔′汉鍛樺彧鑳界湅鍏ㄩ噺姹囨?伙紝鏃犳硶鍍忔棫鍚庡彴涓?鏍烽?愬眰鏍稿浠ｇ悊鎸佷粨銆?
- Layui 浣跨敤闅愯棌瀛楁淇濆瓨褰撳墠涓婁笅鏂囷紝鏄负浜嗚鏌ヨ銆佸埛鏂板拰瀵煎嚭澶╃劧澶嶇敤鍚屼竴浠借〃鍗曞弬鏁般??
- CrmUI 浣跨敤 `extraPayload`锛屾槸涓轰簡璁╅?氱敤琛屾搷浣滅粍浠朵繚鐣欐墿灞曡兘鍔涳紝鍚屾椂涓嶆妸鎸佷粨姹囨?荤殑鏃у弬鏁扮‖缂栫爜杩? Blade 妯℃澘銆?
- 闈炰唬鐞嗚涓嶆樉绀洪捇鍙栨寜閽紝閬垮厤鏅?氬鎴疯处鍙疯瑙﹀彂浠ｇ悊涓嬬骇鏌ヨ銆?

### TDD 鎵ц璁板綍

- RED锛歚vendor\bin\phpunit tests\Feature\AdminPositionSummaryDrilldownFrontendClosureModuleTest.php` 棣栨澶辫触锛孡ayui 缂? `positionSummaryPath`锛孋rmUI 缂? `position_summary_drilldown` 琛屾搷浣溿??
- GREEN锛氳ˉ榻? Blade 闅愯棌鍙傛暟銆丩ayui 琛屼簨浠躲?丆rmUI 鏈湴琛屾搷浣滃拰澶氳瑷?鍚庯紝鍚屼竴娴嬭瘯閫氳繃銆?
- 闈欐?佹鏌ワ細`node --check public\js\apps\admin\layui\pages.js`銆乣node --check public\js\apps\crmui\admin.js`銆乣php -l app\Http\Controllers\CrmUi\Admin\PageController.php`銆乣php -l resources\lang\zh-CN\crmui.php`銆乣php -l resources\lang\en\crmui.php` 鍧囧凡閫氳繃銆?

### 鍓╀綑杈圭晫

- 鏈疆鍙畬鎴愭棫鍚庡彴浠ｇ悊鎸佷粨姹囨?诲墠绔捇鍙栵紝涓嶆柊澧炰氦鏄撴槑缁嗕笅閽婚〉闈€??
- 鏃? `MT4_USERS` 鏇村璧勬枡瀛楁銆佹棫 `MARGIN_RATE` 杩囨护鍙ｅ緞銆佹槑缁嗕笅閽诲拰椋庨櫓鑱斿姩浠嶆寜鎸佷粨/骞充粨娣卞眰杩佺Щ杈圭晫缁х画鎺ㄨ繘銆?

## 372. 2026-07-28 鍚庡彴鎸佷粨姹囨?? MT4 璐︽埛蹇収鑱斿姩闂幆

### 鏈澶勭悊鐩爣

- 鍏抽棴鍚庡彴鎸佷粨姹囨?讳腑 `MT4_USERS` 鏈仈鍔ㄧ殑杩佺Щ缂哄彛锛屾槑纭娇鐢? `user_infos.mt4_code = mt4_users.login` 璇诲彇鐪熷疄 MT4 璐︽埛蹇収銆?
- 楠岃瘉鍒楄〃銆佸綋鍓嶇瓫閫? CSV 瀵煎嚭銆丩ayui 鍚庡彴椤甸潰鍜? CrmUI 鍚庡彴椤甸潰閮藉睍绀哄悓涓?濂? MT4 蹇収瀛楁銆?
- 楠岃瘉椤堕儴姹囨?诲彧缁熻褰撳墠绛涢?夊懡涓殑涓氬姟鐢ㄦ埛锛岄伩鍏嶆妸绛涢?夎寖鍥村鐨? MT4 璐﹀彿浣欓銆佸噣鍊笺?佷繚璇侀噾娣峰叆缁撴灉銆?

### 鏈鍙樻洿鏂囦欢

- `tests/Feature/AdminPositionSummaryMt4AccountLinkageClosureModuleTest.php`
  - 鏂板鐪熷疄鎺ュ彛銆丆SV銆佸墠绔绾﹀拰杩佺Щ鏂囨。璇佹嵁鍥涚被鏍蜂緥锛屽浐瀹? MT4 璐﹀彿鏄犲皠銆佺瓫閫夎寖鍥村拰鏈?缁堟竻鍗曡褰曘??
- `app/Http/Controllers/Admin/PositionSummaryController.php`
  - 鍒楄〃鏌ヨ鍦ㄧ敤鎴锋眹鎬昏涓婂乏鑱? `mt4_users`锛岃繑鍥? `mt4_login`銆乣mt4_name`銆乣mt4_account_group`銆佷綑棰濄?佸噣鍊笺?佷繚璇侀噾銆佸彲鐢ㄤ繚璇侀噾銆佹潬鏉嗗拰蹇収鏃堕棿銆?
  - CSV 瀵煎嚭澶嶇敤鍚屼竴鏉℃煡璇㈤摼璺紝淇濊瘉椤甸潰鐪嬪埌浠?涔堬紝璐㈠姟涓嬭浇灏卞緱鍒颁粈涔堛??
- `resources/admin/layui/position-summary/index.blade.php`
  - 椤堕儴姹囨?诲崱鐗囨柊澧? `total_mt4_accounts`銆乣total_balance`銆乣total_equity`銆乣total_margin`銆乣total_margin_free`銆?
- `public/js/apps/admin/layui/pages.js`
  - 鎸佷粨姹囨?昏〃鏍兼柊澧? MT4 蹇収鍒楋紝骞剁敤 Lucide 缁熶竴椤甸潰鎿嶄綔鍥炬爣銆?
- `app/Http/Controllers/CrmUi/Admin/PageController.php`
  - CrmUI 鎸佷粨姹囨?诲垪鍜屾寚鏍囧悓姝ユ柊澧? MT4 蹇収瀛楁锛岄伩鍏嶅彟涓?濂楀悗鍙板叆鍙ｇ户缁己鍒椼??
- `public/js/apps/crmui/admin.js`
  - 鎸囨爣娓叉煋鍏煎鍚庣 `data.summary`锛岀‘淇? CrmUI 椤堕儴鍗＄墖璇诲彇褰撳墠绛涢?夊悎璁°??
- `docs/admin-legacy-migration-gap-audit.md`
  - 鏇存柊鎸佷粨姹囨?昏縼绉荤姸鎬侊紝鎶? MT4 璐︽埛蹇収鑱斿姩浠庡墿浣欑己鍙ｆ敼涓哄凡闂幆璇佹嵁銆?

### 璺敱涓庢墽琛岄摼

- `POST /api/admin/positionSummaryList` / `admin_api_positionSummaryList` -> `PositionSummaryController::positionSummaryList` -> `validateNumericFilters` -> `baseUserQuery` -> `leftJoin mt4_users on user_infos.mt4_code = mt4_users.login` -> `applyUserFilters` -> `AdminDataScopeService` -> `paginate` -> `summaryFor` -> `success(records + summary)`銆?
- `POST /api/admin/exportPositionSummary` / `admin_api_exportPositionSummary` -> `PositionSummaryController::exportPositionSummary` -> 涓庡垪琛ㄦ帴鍙ｅ鐢ㄧ浉鍚岀瓫閫夊拰 MT4 鑱斿姩鏌ヨ -> 鍐欏嚭 `mt4_login` 绛? CSV 琛ㄥご鍜屽?? -> 杩斿洖 `position_summary_export.csv`銆?
- `GET /admin/position-summary` / `admin_page_position_summary` -> Blade 椤甸潰杈撳嚭姹囨?诲崱鐗? -> `public/js/apps/admin/layui/pages.js` 娓叉煋琛ㄦ牸鍒? -> 璇锋眰 `admin_api_positionSummaryList` -> 鐢? `records.data[]` 鏇存柊琛ㄦ牸锛岀敤 `summary` 鏇存柊 MT4 璧勯噾鍗＄墖銆?
- `GET /admin-crmui/position-summary` -> `CrmUi\Admin\PageController::show` -> 杈撳嚭鍒楀畾涔夊拰 `data-crmui-metric` 鎸囨爣 -> `public/js/apps/crmui/admin.js` 璇锋眰鍚屼竴鍒楄〃鎺ュ彛 -> 浠? `data.summary` 濉厖褰撳墠绛涢?夊悎璁°??

### 鍙傛暟鍜岃繑鍥炵粨鏋滀腑鏂囧惈涔?

- `user_id`锛氫笟鍔＄敤鎴? ID锛岀敤浜庣瓫閫夊崟涓寔浠撴眹鎬昏锛涘懡涓悗鍙繑鍥炶鐢ㄦ埛瀵瑰簲鐨? MT4 蹇収銆?
- `user_infos.mt4_code`锛氫笟鍔＄敤鎴风粦瀹氱殑鐪熷疄 MT4 鐧诲綍鍙凤紝鏄湰杞敮涓?鍙俊鐨? MT4 璐︽埛鏄犲皠鏉ユ簮銆?
- `mt4_login`锛氱湡瀹? MT4 鐧诲綍璐﹀彿锛涗负绌鸿〃绀哄綋鍓嶄笟鍔＄敤鎴锋病鏈夊彲鍖归厤鐨? MT4 蹇収銆?
- `mt4_balance`锛歁T4 褰撳墠浣欓锛岃〃绀鸿处鎴疯祫閲戜綑棰濄??
- `mt4_equity`锛歁T4 褰撳墠鍑?鍊硷紝琛ㄧず浣欓鍙犲姞娴姩鐩堜簭鍚庣殑璧勯噾鐘舵?併??
- `mt4_margin`锛歁T4 宸茬敤淇濊瘉閲戯紝琛ㄧず褰撳墠鎸佷粨鍗犵敤淇濊瘉閲戙??
- `mt4_margin_free`锛歁T4 鍙敤淇濊瘉閲戯紝琛ㄧず杩樿兘鐢ㄤ簬寮?浠撴垨鎶楅闄╃殑淇濊瘉閲戙??
- `total_mt4_accounts`锛氬綋鍓嶇瓫閫夌粨鏋滀腑鎴愬姛鍏宠仈 MT4 蹇収鐨勮处鍙锋暟閲忋??
- `total_balance/total_equity/total_margin/total_margin_free`锛氬綋鍓嶇瓫閫夌粨鏋滃搴? MT4 蹇収璧勯噾瀛楁鍚堣锛屼笉缁熻绛涢?夎寖鍥村璐﹀彿銆?

### 涓轰粈涔堣繖鏍峰仛

- 鏃ч」鐩寔浠撴眹鎬讳緷璧? `MT4_USERS` 璐︽埛璧勯噾鐘舵?侊紱鏂伴」鐩彧灞曠ず浜ゆ槗鑱氬悎浼氱己灏戜綑棰濄?佸噣鍊煎拰淇濊瘉閲戞牳瀵逛緷鎹??
- 涓氬姟鐢ㄦ埛 ID 鍜? MT4 鐧诲綍鍙峰彲鑳戒笉鍚岋紝蹇呴』鎸? `user_infos.mt4_code = mt4_users.login` 鏄犲皠锛屼笉鑳界敤 `user_infos.user_id` 鐚滄祴浜ゆ槗璐﹀彿銆?
- 鍒楄〃銆佸鍑恒?丩ayui 鍜? CrmUI 澶嶇敤鍚屼竴鍚庣鍙ｅ緞锛屽彲浠ラ伩鍏嶉〉闈笌璐㈠姟 CSV 缁撴灉涓嶄竴鑷淬??
- 褰撳墠鐪熷疄琛ㄤ粛娌℃湁鏃ч」鐩? `MARGIN_RATE` 瀛楁锛屽洜姝ゆ湰杞彧鍏抽棴 MT4 蹇収鑱斿姩锛屼笉浼?犲疄鐩樿繃婊ゃ?佷氦鏄撴槑缁嗕笅閽绘垨椋庨櫓鑱斿姩銆?

### TDD 鎵ц璁板綍

- RED锛歚vendor\bin\phpunit tests\Feature\AdminPositionSummaryMt4AccountLinkageClosureModuleTest.php` 棣栨杩愯澶辫触锛屽懡涓? CSV 缂哄皯 MT4 蹇収瀛楁銆丩ayui/CrmUI 缂哄皯灞曠ず鍒椼?佽縼绉诲璁＄己灏? `user_infos.mt4_code = mt4_users.login` 璇佹嵁銆?
- GREEN锛氳ˉ榻愬悗绔? MT4 鑱斿姩瀛楁銆丆SV 琛ㄥご鍜屽?笺?丩ayui/CrmUI 鍒楀拰鎸囨爣銆佸璇█鏂囨銆佽縼绉诲璁′互鍙婃湰鑺傛渶缁堟竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 鍓╀綑杈圭晫

- 褰撳墠鐪熷疄 `mt4_trades` 琛ㄤ粛鏃犳棫椤圭洰 `MARGIN_RATE` 瀛楁锛屾湰杞笉浼?? `MARGIN_RATE <> 0` 杩囨护銆?
- 鏈疆娌℃湁鏂板浜ゆ槗鏄庣粏涓嬮捇鎴栭闄╄仈鍔ㄩ〉闈紱杩欎簺浠嶉渶鎸夌湡瀹炶矾鐢便?佹潈闄愩?佸瓧娈靛拰鏃ч」鐩墽琛岄摼鍗曠嫭闂幆銆?

## 370. 2026-07-28 鍚庡彴杩斾剑鍒楄〃 settle_status 绛涢?変弗鏍兼牎楠岄棴鐜?

### 鏈澶勭悊鐩爣

- 涓? `CommissionController::index` 琛ラ綈杩斾剑鍒楄〃 `settle_status` 缁撶畻鐘舵?佺瓫閫変弗鏍兼牎楠屾祴璇曘??
- 楠岃瘉 `/api/admin/commissionList` 涓嶈兘鎶? `settle_status=1abc`銆乣3` 鎴? `-1` 涓嬫帹鍒? `commission_records.settle_status` 鏌ヨ銆?
- 楠岃瘉闈炴硶缁撶畻鐘舵?佽繑鍥? `ResponseCode::VALIDATION_FAILED`锛岄伩鍏嶅悗鍙拌繑浣ｅ垪琛ㄦ寜瀹芥澗鐘舵?佸?艰繑鍥炵湡瀹炶繑浣ｈ褰曘??

### 鏈鍙樻洿鏂囦欢

- `tests/Feature/AdminCommissionListSettleStatusValidationClosureModuleTest.php`
  - 鏂板杩斾剑鍒楄〃闈炴硶 `settle_status` 绛涢?夎鎷掔粷涓斾笉杩斿洖娴嬭瘯杩斾剑璁板綍鍞竴缂栧彿鐨勬牱渚嬨??
  - 浣跨敤 `commission-list-settle-status-validation-%` 浣滀负娴嬭瘯璁板綍鍓嶇紑锛屾祴璇曠粨鏉熷悗娓呯悊涓撶敤杩斾剑璁板綍銆?
- `app/Http/Controllers/Admin/CommissionController.php`
  - `index` 鍦ㄦ瀯閫? `commission_records` 鏌ヨ鍓嶈皟鐢? `validateSettleStatusFilter()`銆?
  - `validateSettleStatusFilter()` 浣跨敤瀛楃涓茬簿纭灇涓撅紝鍙厑璁? `1=寰呯粨绠梎銆乣2=宸茬粨绠梎锛屾嫆缁? `1abc`銆乣3`銆乣-1` 鍜屽叾瀹冮潪鏃ч」鐩姸鎬佸?笺??

### 璺敱涓庢墽琛岄摼

- `POST /api/admin/commissionList` / `admin_api_commissionList` 鈫? `CommissionController::index` 鈫? `validateAgentIdFilter` 鈫? `validateSettleStatusFilter` 鈫? `CommissionRecord::query()->with(['agent','parent'])` 鈫? `AdminDataScopeService::apply` 鈫? `where agent_id` 鈫? `where commission_records.settle_status` 鈫? `paginate` 鈫? `success(records)`銆?

### 鍙傛暟鍜岃繑鍥炵粨鏋滀腑鏂囧惈涔?

- `settle_status=1`锛氬彧鏌ヨ寰呯粨绠楄繑浣ｈ褰曪紝杩斿洖缁撴灉浠ｈ〃璇ヨ繑浣ｅ皻鏈墽琛屽悗鍙扮粨绠椼??
- `settle_status=2`锛氬彧鏌ヨ宸茬粨绠楄繑浣ｈ褰曪紝杩斿洖缁撴灉浠ｈ〃璇ヨ繑浣ｅ凡瀹屾垚缁撶畻鐘舵?佹洿鏂般??
- `settle_status=1abc`銆乣3`銆乣-1`锛氳繑鍥? `ResponseCode::VALIDATION_FAILED`锛屼腑鏂囧惈涔変负鍙傛暟鏍￠獙澶辫触锛屾帴鍙ｄ笉浼氱户缁鍙? `commission_records`銆?
- `records.data[]`锛氬悎娉曠瓫閫夋垚鍔熸椂鐨勮繑浣ｅ垎椤垫暟鎹紝鍖呭惈浠ｇ悊鍜岀埗绾т唬鐞嗗叧鑱斾俊鎭??

### 涓轰粈涔堣繖鏍峰仛

- 鏃у悗鍙拌繑浣ｅ垪琛ㄥ彧鏈夊緟缁撶畻鍜屽凡缁撶畻涓や釜鏈夋晥鐘舵?侊紝鏂伴」鐩鏋滄妸浠绘剰瀛楃涓茬洿鎺ヤ氦缁欐煡璇紝浼氶?犳垚鐘舵?佽竟鐣屼笉娓呮櫚銆?
- 浣跨敤瀛楃涓茬簿纭灇涓炬瘮瀹芥澗鏁板瓧姣旇緝鏇村畨鍏紝鍙互閬垮厤 `1abc` 鎴栧甫鍓嶇紑鐨勫?煎湪 PHP/Laravel 灞傝閿欒鐞嗚В涓哄悎娉曠姸鎬併??
- 鏍￠獙鏀惧湪鏌ヨ鍓嶏紝瑙ｅ喅闈炴硶绛涢?変粛鍙兘璇诲彇鐪熷疄杩斾剑璁板綍鐨勯棶棰橈紝鍜屽墠闈㈣祫閲戙?侀鎺с?佷氦鏄撴ā鍧楃殑涓ユ牸鍙傛暟杈圭晫淇濇寔涓?鑷淬??

### TDD 鎵ц璁板綍

- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit tests\Feature\AdminCommissionListSettleStatusValidationClosureModuleTest.php --colors=never` 棣栨澶辫触锛屼笁涓潪娉? `settle_status` 鍧囪繑鍥? `ResponseCode::SUCCESS`锛岃瘉鏄庢帶鍒跺櫒灏氭湭鎷︽埅缁撶畻鐘舵?佺瓫閫夈??
- GREEN锛氳ˉ榻? `validateSettleStatusFilter()` 鍜岀 370 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 鍓╀綑杈圭晫

- 鏈疆娌℃湁鏀瑰姩杩斾剑閲戦璁＄畻銆佸崟绗旂粨绠椼?佹壒閲忕粨绠椼?佽繑浣ｈ浆璐﹀璐︺?佽繑浣ｉ〉闈€?佹潈闄愬瓧鍏搞?佹潈闄愯縼绉绘垨鏁版嵁搴撶粨鏋勩??
- 鍚庣画缁х画鎸夋棫椤圭洰妯″潡娓呭崟瀹¤鍚庡彴璧勯噾妯″潡銆佷唬鐞嗗晢妯″潡銆佸悗鍙扮鐞嗗憳妯″潡鍜屽悗鍙版櫘閫氱敤鎴锋ā鍧楀叾瀹冨墿浣欏叆鍙ｃ??

## 371. 2026-07-28 鍚庡彴鏅?氱敤鎴疯祫鏂欑紪杈? MT4 鍚屾闂幆

### 鏈澶勭悊鐩爣

- 涓? `AdminUserController::updateUser` 琛ラ綈鏃ч」鐩? `CustomerController::cust_save_info` 涓氦鏄撶粍鍜屾潬鏉嗙紪杈戠殑鏍稿績闂幆銆?
- 楠岃瘉 `/api/admin/updateUser` 淇敼 `mt4_group` 鎴? `leverage` 鏃讹紝蹇呴』鍏堣皟鐢? `Mt4ManagerService::updateUserTradingProfile`銆?
- 楠岃瘉 MT4 鏈繑鍥炴槑纭垚鍔熸椂锛屾湰鍦? `user_infos.user_name`銆乣user_infos.phone`銆乣user_infos.mt4_group`銆乣user_infos.leverage` 閮戒繚鎸佸師鍊硷紝骞惰繑鍥? `ResponseCode::MT4_SYNC_FAILED`銆?
- 楠岃瘉鍙慨鏀瑰熀纭?璧勬枡鏃朵笉浼氳Е鍙? MT4 浜ゆ槗璧勬枡鍚屾锛屼絾浠嶅啓鍏? `operation_logs` 瀹¤璁板綍銆?

### 鏈鍙樻洿鏂囦欢

- `tests/Feature/AdminUserUpdateMt4SyncClosureModuleTest.php`
  - 鏂板 MT4 澶辫触涓嶈惤搴撱?丮T4 鎴愬姛鍚庢墠钀藉簱骞跺啓瀹¤鏃ュ織銆佸熀纭?璧勬枡鏇存柊涓嶈Е鍙? MT4 鐨勪笁鏉＄湡瀹炴帴鍙ｆ牱渚嬨??
  - 浣跨敤娴嬭瘯鏇胯韩璁板綍璋冪敤 MT4 鏃舵湰鍦板簱涓殑鏃у?硷紝璇佹槑鍚屾鍙戠敓鍦ㄦ湰鍦板啓鍏ヤ箣鍓嶃??
- `app/Http/Controllers/Admin/AdminUserController.php`
  - `updateUser` 鎵╁睍鏃у瓧娈靛埆鍚嶅綊涓?锛歚username`銆乣userphoneNo`銆乣usergrpName`銆乣cust_lvg`銆?
  - 缁х画淇濈暀瀛楁鐧藉悕鍗曪紝鍙厑璁稿熀纭?璧勬枡鍜屾槑纭粡杩? MT4 鍚屾鐨? `user_infos.mt4_group`銆乣user_infos.leverage` 鍐欏叆銆?
  - 鎴愬姛鍐欏叆鍚庡垱寤? `operation_logs`锛岃褰曞瓧娈垫柊鏃у?笺?佺鐞嗗憳銆佺洰鏍囩敤鎴峰拰鏉ユ簮 IP銆?

### 璺敱涓庢墽琛岄摼

- `POST /api/admin/updateUser` / `admin_api_updateUser` -> `AdminUserController::updateUser` -> `normalizedUserUpdatePayload` -> `Validator` -> `UserInfo::where(user_id)` -> `AdminDataScopeService::canAccessUser` -> `Mt4ManagerService::updateUserTradingProfile` -> `DB::transaction` -> `user_infos.update` -> `operation_logs.create` -> `success(user, UPDATED)`銆?
- `PATCH /api/admin/users/{user}` / `admin_api_updateUser` -> 鍚屼竴 `AdminUserController::updateUser` 閾捐矾锛屽尯鍒槸鐢ㄦ埛 ID 鏉ヨ嚜璺敱鍙傛暟銆?

### 鍙傛暟鍜岃繑鍥炵粨鏋滀腑鏂囧惈涔?

- `user_id`锛氫笟鍔＄敤鎴? ID锛岀敤浜庡畾浣? `user_infos.user_id`锛屼笉鏄悗鍙扮鐞嗗憳 ID銆?
- `user_name` / `username`锛氱敤鎴峰鍚嶆垨鏄电О锛屾垚鍔熷悗鍐欏叆 `user_infos.user_name`銆?
- `phone` / `userphoneNo`锛氳仈绯荤數璇濓紱鏃у瓧娈? `userphoneNo` 浼氭寜 `modules` 鐢熸垚 `86-鎵嬫満鍙穈 鏍煎紡銆?
- `mt4_group` / `usergrpName`锛氱洰鏍? MT4 浜ゆ槗缁勫悕绉帮紝鎻愪氦璇ュ瓧娈垫椂蹇呴』鍏堝悓姝? MT4銆?
- `leverage` / `cust_lvg` / `is_enc`锛氱洰鏍囨潬鏉嗭紱鏈紶鏉犳潌浣嗕紶 `is_enc=1` 鏃舵寜鏃ч」鐩? ECN 鍙ｅ緞杞崲涓? 200锛屽惁鍒欒浆鎹负 100銆?
- `ResponseCode::UPDATED`锛歁T4 鍚屾鎴愬姛鎴栨棤闇? MT4锛屽悓姝ュ悗鏈湴璧勬枡宸叉洿鏂般??
- `ResponseCode::MT4_SYNC_FAILED`锛歁T4 缃戠粶銆佸崗璁垨涓氬姟鍝嶅簲鏈槑纭垚鍔燂紝鏈湴璧勬枡鏈啓鍏ャ??
- `ResponseCode::VALIDATION_FAILED`锛氱敤鎴? ID銆佷氦鏄撶粍銆佹潬鏉嗙瓑鍙傛暟涓嶅悎娉曪紝鎺ュ彛涓嶄細缁х画鍐欏簱銆?

### 涓轰粈涔堣繖鏍峰仛

- 鏃ч」鐩妸 MT4 璐︽埛璧勬枡瑙嗕负鐪熷疄鐘舵?佹簮锛屾湰鍦扮敤鎴疯〃鍙槸闀滃儚锛涘厛鍐欐湰鍦板啀璋? MT4 浼氶?犳垚鍚庡彴鐪嬪埌鐨勭粍鍒?/鏉犳潌涓庣湡瀹炰氦鏄撹处鎴蜂笉涓?鑷淬??
- 浣跨敤涓?娆? `update_user` 鍚屾椂璁剧疆 `grp+lvg`锛岄伩鍏嶇粍鍒垚鍔熶絾鏉犳潌澶辫触鐨勯儴鍒嗘垚鍔熺姸鎬併??
- 鍩虹璧勬枡鏇存柊涓嶈皟鐢? MT4锛屽彲浠ュ噺灏戞棤鎰忎箟鐨勫閮ㄨ皟鐢紱浜ゆ槗璧勬枡鏇存柊澶辫触鍒欐暣浣撻樆鏂紝淇濊瘉鏅?氱敤鎴风紪杈戦摼璺湁鏄庣‘澶辫触杈圭晫銆?

### TDD 鎵ц璁板綍

- RED锛歚php vendor\bin\phpunit tests\Feature\AdminUserUpdateMt4SyncClosureModuleTest.php` 棣栨澶辫触锛屾帴鍙ｈ繑鍥? `UPDATED`锛屾湭璋冪敤 `Mt4ManagerService::updateUserTradingProfile`锛屼篃娌℃湁鍐? `operation_logs`銆?
- GREEN锛氳ˉ榻? MT4 鍓嶇疆鍚屾銆佹湰鍦颁簨鍔″啓鍏ュ拰绗? 371 鑺傛竻鍗曡褰曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 鍓╀綑杈圭晫

- 鏈疆娌℃湁鎶婃棫椤圭洰 `cust_save_info` 涓韩浠借瘉銆侀偖绠便?侀摱琛屽崱澶囨敞銆佷笂绾т唬鐞嗐?佸嚭鍏ラ噾寮?鍏崇瓑鎵?鏈夊垎鏀苟鍏ュ悓涓?涓帴鍙ｏ紱杩欎簺瀛楁娑夊強鍞竴鎬с?佽祫閲戞潈闄愬拰鐙珛 MT4 鍛戒护锛岄渶瑕佺户缁寜鍗曠嫭闂幆娴嬭瘯杩佺Щ銆?
- 鏈疆娌℃湁鏀瑰姩鍓嶅彴璐︽埛绫诲瀷鍒囨崲 `AccountController::changeAccountSave`锛岃鍏ュ彛宸叉湁鐙珛 MT4 鍚屾闂幆銆?

## 372. 2026-07-28 鍚庡彴鏅?氱敤鎴疯祫鏂欑紪杈戝瘑鐮侀噸缃棴鐜?

### 鏈澶勭悊鐩爣

- 涓? `AdminUserController::updateUser` 琛ラ綈鏃ч」鐩? `CustomerController::cust_save_info` 鐨勫瘑鐮侀噸缃垎鏀??
- 楠岃瘉 `/api/admin/updateUser` 鏀跺埌鐪熷疄 `password` 鎴栨棫瀛楁 `password1` 鏃讹紝蹇呴』璋冪敤 `UserPasswordService`銆?
- 楠岃瘉瀵嗙爜鏈嶅姟澶辫触鏃惰繑鍥? `ResponseCode::MT4_SYNC_FAILED`锛屾湰鍦? `user_logins.password` 鍜? `user_infos` 鍩虹璧勬枡閮戒繚鎸佸師鍊笺??
- 楠岃瘉鏃ч〉闈㈠崰浣嶇 `********` 琛ㄧず鈥滀笉淇敼瀵嗙爜鈥濓紝涓嶄細瑙﹀彂瀵嗙爜鏈嶅姟銆?
- 楠岃瘉瀹¤鏃ュ織鍙褰? `password:changed`锛屼笉鍐欏叆鏄庢枃瀵嗙爜銆?

### 鏈鍙樻洿鏂囦欢

- `tests/Feature/AdminUserUpdatePasswordClosureModuleTest.php`
  - 鏂板瀵嗙爜淇敼鎴愬姛銆佸瘑鐮佹湇鍔″け璐ャ?佹棫鍗犱綅绗﹁烦杩囧拰鏈?缁堟竻鍗曡瘉鎹洓涓牱渚嬨??
  - 浣跨敤 `UserPasswordService` 娴嬭瘯鏇胯韩璁板綍璋冪敤鏃舵湰鍦扮敤鎴疯祫鏂欐棫鍊硷紝璇佹槑瀵嗙爜鍒嗘敮鍙戠敓鍦ㄨ祫鏂欒惤搴撳墠銆?
- `app/Http/Controllers/Admin/AdminUserController.php`
  - `normalizedUserUpdatePayload` 鍏煎 `password/password1`銆?
  - `passwordChangeRequested` 鎶婄┖瀵嗙爜鍜? `********` 璇嗗埆涓轰笉鏀瑰瘑銆?
  - 鎴愬姛鏀瑰瘑鍚庡啓 `operation_logs`锛屽唴瀹瑰彧鍖呭惈 `password:changed`锛岄伩鍏嶆硠闇叉槑鏂囥??

### 璺敱涓庢墽琛岄摼

- `POST /api/admin/updateUser` / `admin_api_updateUser` -> `AdminUserController::updateUser` -> `normalizedUserUpdatePayload` -> `Validator(password)` -> `UserLogin::where(user_id)` -> `UserPasswordService::change` -> `user_infos.update` -> `operation_logs.create(password:changed)` -> `success(user, UPDATED)`銆?
- `password=********` -> `passwordChangeRequested=false` -> 璺宠繃 `UserPasswordService` -> 浠呭鐞嗗叾瀹冪櫧鍚嶅崟璧勬枡瀛楁銆?

### 鍙傛暟鍜岃繑鍥炵粨鏋滀腑鏂囧惈涔?

- `password`锛氱幇浠ｆ帴鍙ｆ彁浜ょ殑鏂板瘑鐮侊紱闈炵┖涓斾笉绛変簬 `********` 鏃惰〃绀鸿姹傞噸缃敤鎴风櫥褰曞瘑鐮併??
- `password1`锛氭棫鍚庡彴琛ㄥ崟瀛楁鍚嶏紝鍚箟涓? `password` 涓?鑷淬??
- `********`锛氭棫缂栬緫椤靛師瀵嗙爜鍗犱綅绗︼紝琛ㄧず淇濈暀鏃у瘑鐮併??
- `ResponseCode::UPDATED`锛氬瘑鐮佹湇鍔℃垚鍔熶笖鏈湴璧勬枡鎴栧璁″啓鍏ユ垚鍔熴??
- `ResponseCode::MT4_SYNC_FAILED`锛氬瘑鐮佹湇鍔℃湭鍙栧緱鏄庣‘鎴愬姛锛岄?氬父浠ｈ〃 MT4 鎴栬繙绔瘑鐮佸悓姝ュけ璐ワ紝鏈湴璧勬枡涓嶈惤搴撱??

### 涓轰粈涔堣繖鏍峰仛

- 鏃ч」鐩? `cust_save_info` 鎶婂瘑鐮侀噸缃斁鍦ㄧ敤鎴疯祫鏂欑紪杈戦噷锛涘鏋滄柊椤圭洰蹇界暐璇ュ瓧娈碉紝鏃ч〉闈㈡彁浜や細琛ㄧ幇涓衡?滀繚瀛樻垚鍔熶絾瀵嗙爜娌℃敼鈥濄??
- 澶嶇敤 `UserPasswordService` 鍙互娌跨敤宸叉湁鈥滃厛鍚屾 MT4锛屽啀鍐欐湰鍦板搱甯屸?濈殑杈圭晫锛屼笉鍦ㄦ帶鍒跺櫒閲岄噸澶嶅疄鐜板瘑鐮佸崗璁??
- 瀹¤鏃ュ織鍙褰曡劚鏁忔爣璇嗭紝瑙ｅ喅绠＄悊鍛樻搷浣滃彲杩借釜涓庢槑鏂囧瘑鐮佷笉鑳借惤搴撲箣闂寸殑鍐茬獊銆?

### TDD 鎵ц璁板綍

- RED锛歚php vendor\bin\phpunit tests\Feature\AdminUserUpdatePasswordClosureModuleTest.php` 棣栨澶辫触锛屾帴鍙ｆ湭璋冪敤 `UserPasswordService`锛屽瘑鐮佹湇鍔″け璐ヤ粛杩斿洖 `UPDATED`锛屾渶缁堟竻鍗曠己灏戝瘑鐮佸垎鏀瘉鎹??
- GREEN锛氳ˉ榻愬瘑鐮佸瓧娈靛綊涓?銆佸崰浣嶇璺宠繃銆佸瘑鐮佹湇鍔¤皟鐢ㄣ?佽劚鏁忓璁″拰绗? 372 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 鍓╀綑杈圭晫

- 鏈疆娌℃湁鎭㈠鏃ч」鐩煭淇￠?氱煡瀵嗙爜閲嶇疆缁撴灉锛屽洜涓哄綋鍓嶄换鍔′笉鑳戒吉閫犵煭淇℃湇鍔℃垚鍔熴??
- 鏈疆娌℃湁鎶婅韩浠借瘉銆侀偖绠便?侀摱琛屽崱銆佷笂绾т唬鐞嗗拰鍑哄叆閲戝紑鍏冲苟鍏ヨ祫鏂欑紪杈戞帴鍙ｏ紝杩欎簺浠嶉渶鍗曠嫭鎸夌湡瀹炲瓧娈靛拰鏉冮檺杈圭晫杩佺Щ銆?

## 373. 2026-07-28 鍚庡彴鏅?氱敤鎴疯祫鏂欑紪杈戦偖绠遍棴鐜?

### 鏈澶勭悊鐩爣

- 涓? `AdminUserController::updateUser` 琛ラ綈鏃ч」鐩? `CustomerController::cust_save_info` 鐨勭櫥褰曢偖绠辩紪杈戝垎鏀??
- 楠岃瘉鏃у瓧娈? `useremail` 鍜岀幇浠ｅ瓧娈? `email` 閮戒細褰掍竴鍖栦负鐧诲綍閭锛屽苟鍐欏叆鐪熷疄 `user_logins.email`銆?
- 楠岃瘉閲嶅閭杩斿洖 `ResponseCode::VALIDATION_FAILED`锛屼笖涓嶄細鍏堝啓鍏? `user_infos` 鍩虹璧勬枡锛岄伩鍏嶅墠绔嚭鐜扳?滆祫鏂欐垚鍔熶絾閭澶辫触鈥濈殑鍗婃垚鍔熺姸鎬併??
- 楠岃瘉鎴愬姛淇敼閭鍚庡啓鍏? `operation_logs`锛屽璁″唴瀹硅褰? `login.email:鏃ч偖绠?->鏂伴偖绠盽銆?

### 鏈鍙樻洿鏂囦欢

- `tests/Feature/AdminUserUpdateEmailClosureModuleTest.php`
  - 鏂板鏃? `useremail` 鎻愪氦鎴愬姛钀藉簱鍜屽璁℃棩蹇楁牱渚嬨??
  - 鏂板閲嶅閭澶辫触涓斿熀纭?璧勬枡涓嶈惤搴撴牱渚嬨??
  - 鏂板鏈?缁堟竻鍗曡瘉鎹牱渚嬶紝鍥哄畾鏈棴鐜矾鐢便?佸瓧娈靛拰娴嬭瘯鏂囦欢銆?
- `app/Http/Controllers/Admin/AdminUserController.php`
  - `normalizedUserUpdatePayload` 鍏煎 `email`銆乣useremail`銆乣user_email` 涓夌鍏ュ彛瀛楁銆?
  - `updateUser` 鍦ㄤ簨鍔″墠鏍￠獙閭鏍煎紡銆侀潪绌哄拰 `user_logins.email` 鍞竴鎬с??
  - `DB::transaction` 鍚屾鍐欏叆 `user_infos` 涓? `user_logins`锛屽苟缁熶竴鍐? `operation_logs`銆?

### 璺敱涓庢墽琛岄摼

- `POST /api/admin/updateUser` / `admin_api_updateUser` -> `AdminUserController::updateUser` -> `normalizedUserUpdatePayload` -> 鏃у瓧娈? `useremail` 褰掍竴鍖栦负 `email` -> `Validator(email)` -> `UserInfo::where(user_id)` -> `AdminDataScopeService::canAccessUser` -> `UserLogin::where(user_id)` -> `user_logins.email` 鍞竴鎬ф牎楠? -> `DB::transaction` -> `user_infos.update` -> `user_logins.update` -> `operation_logs.create(login.email:鏃у??->鏂板??)` -> `success(user, UPDATED)`銆?
- `PATCH /api/admin/users/{user}` / `admin_api_updateUser` -> 鍚屼竴 `AdminUserController::updateUser` 閾捐矾锛屽尯鍒槸涓氬姟鐢ㄦ埛 ID 鏉ヨ嚜璺敱 `{user}`锛岄噸澶嶉偖绠变細鍦ㄤ簨鍔″墠杩斿洖 `VALIDATION_FAILED`銆?

### 鍙傛暟鍜岃繑鍥炵粨鏋滀腑鏂囧惈涔?

- `email`锛氱幇浠ｆ帴鍙ｆ彁浜ょ殑鐧诲綍閭锛屾垚鍔熷悗鍐欏叆 `user_logins.email`銆?
- `useremail`锛氭棫鍚庡彴璧勬枡缂栬緫琛ㄥ崟瀛楁鍚嶏紝鍚箟涓? `email` 涓?鑷达紝鐢ㄤ簬鍏煎鏃? Blade 鎻愪氦娴佺▼銆?
- `user_email`锛氶澶栧吋瀹瑰瓧娈碉紝閬垮厤鍘嗗彶椤甸潰鎴栬剼鏈娇鐢ㄤ笅鍒掔嚎瀛楁鏃惰闈欓粯涓㈠純銆?
- `ResponseCode::UPDATED`锛氶偖绠辨牎楠岄?氳繃锛屽熀纭?璧勬枡銆佺櫥褰曢偖绠卞拰瀹¤鏃ュ織鍦ㄥ悓涓?浜嬪姟閲屽畬鎴愩??
- `ResponseCode::VALIDATION_FAILED`锛氶偖绠变负绌恒?佹牸寮忛敊璇垨宸茶鍏跺畠鐢ㄦ埛鍗犵敤锛涙帴鍙ｄ笉浼氱户缁啓 `user_infos` 鎴? `user_logins`銆?
- `operation_logs.content` 涓殑 `login.email:鏃ч偖绠?->鏂伴偖绠盽锛氳〃绀烘湰娆″悗鍙扮紪杈戠‘瀹炰慨鏀逛簡鐧诲綍閭锛屼究浜庡悗缁璁¤拷韪??

### 涓轰粈涔堣繖鏍峰仛

- 鏃ч」鐩? `cust_save_info` 鏀寔鍦ㄧ敤鎴疯祫鏂欑紪杈戦〉鐩存帴淇敼 `useremail`锛涙柊椤圭洰鍓嶇璇︽儏椤典篃宸茬粡鎻愪氦 `email`锛屼絾鍚庣鑻ヤ笉钀藉埌 `user_logins.email`锛屼細褰㈡垚鍓嶅悗绔柇鐐广??
- 閭鏄櫥褰曞嚟璇侊紝蹇呴』鍦ㄤ簨鍔″墠瀹屾垚鍞竴鎬ф牎楠岋紱杩欐牱鍙互瑙ｅ喅閲嶅閭瀵艰嚧鐨勭櫥褰曟涔夛紝涔熼伩鍏嶅熀纭?璧勬枡宸蹭繚瀛樹絾鐧诲綍閭澶辫触鐨勫崐鍐欏叆闂銆?
- 瀹¤鏃ュ織璁板綍鐧诲綍閭鏂版棫鍊硷紝鍙互璁╁悗鍙扮鐞嗗憳鎿嶄綔鏈夊彲杩借釜璇佹嵁锛屽悓鏃朵笉褰卞搷瀵嗙爜鍒嗘敮鐨勮劚鏁忓璁¤鍒欍??

### TDD 鎵ц璁板綍

- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit tests\Feature\AdminUserUpdateEmailClosureModuleTest.php --colors=never` 棣栨澶辫触锛屽懡涓棫 `useremail` 鏈啓鍏? `user_logins.email`銆侀噸澶嶉偖绠变粛杩斿洖 `UPDATED`銆佹渶缁堟竻鍗曠己灏戦偖绠卞垎鏀瘉鎹??
- GREEN锛氳ˉ榻愰偖绠卞瓧娈靛綊涓?銆佹牸寮忎笌鍞竴鎬ф牎楠屻?佺櫥褰曢偖绠变簨鍔″啓鍏ャ?佸璁℃棩蹇楀拰绗? 373 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 鍓╀綑杈圭晫

- 鏈疆娌℃湁鎶婅韩浠借瘉銆侀摱琛屽崱澶囨敞銆佷笂绾т唬鐞嗐?佸嚭鍏ラ噾寮?鍏炽?佺煭淇￠?氱煡鍜? MT4 娉ㄥ唽鏃ユ湡鑱斿姩骞跺叆璧勬枡缂栬緫鎺ュ彛锛涜繖浜涗粛闇?鎸夌湡瀹炲瓧娈点?佹潈闄愬拰澶栭儴鏈嶅姟杈圭晫缁х画鍗曠嫭闂幆銆?
- 鏈疆娌℃湁鏀瑰姩鍓嶅彴鐢ㄦ埛鑷淇敼閭娴佺▼锛涘墠鍙伴偖绠变慨鏀瑰凡鐢? `ProfileController` 鐩稿叧闂幆鐙珛瑕嗙洊銆?

## 374. 2026-07-28 鍚庡彴鏅?氱敤鎴疯祫鏂欑紪杈戝疄鍚嶄笌閾惰鍗￠棴鐜?

### 鏈澶勭悊鐩爣

- 涓? `AdminUserController::updateUser` 琛ラ綈鏃ч」鐩? `CustomerController::cust_save_info` 鐨勮韩浠借瘉鍙蜂笌宸插鏍搁摱琛屽崱蹇収缂栬緫鍒嗘敮銆?
- 楠岃瘉鏃у瓧娈? `userIdcardNo` 浼氬綊涓?鍖栦负 `id_card_no`锛屽苟鍐欏叆鐪熷疄 `user_auths.id_card_no`銆?
- 楠岃瘉韬唤璇佸彿鍦ㄤ笟鍔＄敤鎴风淮搴﹀敮涓?锛涢噸澶嶆椂杩斿洖 `ResponseCode::VALIDATION_FAILED`锛屼笖涓嶄細鍏堝啓鍏? `user_infos` 鍩虹璧勬枡銆?
- 楠岃瘉宸插鏍搁摱琛屽崱 `bank_status=2` 淇敼 `bank_no/bank_class/bank_info` 鏃讹紝蹇呴』鍏堣皟鐢? `Mt4ManagerService::updateComment` 鍚屾 MT4 comment锛屾垚鍔熷悗鎵嶅啓鍏? `user_auths.bank_no/bank_name/bank_addr/is_bank_synced`銆?
- 楠岃瘉 MT4 comment 鍚屾澶辫触鏃惰繑鍥? `ResponseCode::MT4_SYNC_FAILED`锛屾湰鍦? `user_infos` 鍜? `user_auths` 閮戒繚鎸佸師鍊笺??

### 鏈鍙樻洿鏂囦欢

- `tests/Feature/AdminUserUpdateAuthAndBankClosureModuleTest.php`
  - 鏂板鏃? `userIdcardNo` 鍐欏叆 `user_auths.id_card_no` 鍜岃劚鏁忓璁℃牱渚嬨??
  - 鏂板閲嶅韬唤璇佸彿澶辫触涓斿熀纭?璧勬枡涓嶈惤搴撴牱渚嬨??
  - 鏂板宸插鏍搁摱琛屽崱鍏堝悓姝? MT4 comment 鍐嶈惤搴撴牱渚嬨??
  - 鏂板 MT4 comment 鍚屾澶辫触鍏抽棴鍐欏叆鏍蜂緥鍜屾渶缁堟竻鍗曡瘉鎹牱渚嬨??
- `app/Http/Controllers/Admin/AdminUserController.php`
  - `normalizedUserUpdatePayload` 鍏煎 `id_card_no/userIdcardNo/IDcard_no` 涓? `bank_no/bank_class/bank_info`銆?
  - `updateUser` 鍦ㄤ簨鍔″墠鏍￠獙韬唤璇佸敮涓?鎬э紝骞朵负宸插鏍搁摱琛屽崱鎵ц MT4 comment 鍓嶇疆鍚屾銆?
  - `userUpdateAuditContent` 瀵硅韩浠借瘉鍙峰拰閾惰鍗″彿鍙褰? `changed` 鑴辨晱鏍囪瘑锛屽寮?鎴疯鍜屽紑鎴峰湴鍧?璁板綍鍙鏂版棫鍊笺??
- `app/Models/UserAuth.php`
  - 灏嗙湡瀹炲瓧娈? `is_bank_synced` 鍔犲叆 `$fillable`锛屼繚璇佸凡瀹℃牳閾惰鍗″悓姝ユ垚鍔熷悗鍙互鍐欏叆鍚屾鏍囪銆?

### 璺敱涓庢墽琛岄摼

- `POST /api/admin/updateUser` / `admin_api_updateUser` -> `AdminUserController::updateUser` -> `normalizedUserUpdatePayload` -> 鏃у瓧娈? `userIdcardNo` 褰掍竴鍖栦负 `id_card_no` -> `Validator(id_card_no)` -> `UserInfo::where(user_id)` -> `AdminDataScopeService::canAccessUser` -> `UserAuth::firstOrNew(user_id)` -> `user_auths.id_card_no` 鍞竴鎬ф牎楠? -> `DB::transaction` -> `user_auths.save` -> `operation_logs.create(auth.id_card_no:changed)` -> `success(user, UPDATED)`銆?
- `PATCH /api/admin/users/{user}` / `admin_api_updateUser` -> `normalizedUserUpdatePayload` -> `bank_no/bank_class/bank_info` 褰掍竴鍖栦负 `bank_no/bank_name/bank_addr` -> 璇诲彇 `user_auths.bank_status` -> `targetBankSnapshot` -> `Mt4ManagerService::updateComment(user_id, bank_no|bank_name|bank_addr)` -> `DB::transaction` -> `user_infos.update` -> `user_auths.save(is_bank_synced=1)` -> `operation_logs.create(auth.bank_no:changed; auth.bank_name:鏃у??->鏂板??)` -> `success(user, UPDATED)`銆?
- `Mt4ManagerService::updateComment` 杩斿洖闈? `status=ok/err=0` -> `ResponseCode::MT4_SYNC_FAILED` -> 涓嶈繘鍏? `DB::transaction` -> `user_infos` 鍜? `user_auths` 淇濇寔鍘熷?笺??

### 鍙傛暟鍜岃繑鍥炵粨鏋滀腑鏂囧惈涔?

- `id_card_no`锛氱幇浠ｆ帴鍙ｆ彁浜ょ殑韬唤璇佸彿锛屾垚鍔熷悗鍐欏叆 `user_auths.id_card_no`銆?
- `userIdcardNo`锛氭棫鍚庡彴璧勬枡缂栬緫瀛楁鍚嶏紝鍚箟涓? `id_card_no` 涓?鑷淬??
- `bank_no`锛氬凡瀹℃牳閾惰鍗″彿锛屽睘浜庢晱鎰熷瓧娈碉紝瀹¤鏃ュ織鍙褰? `auth.bank_no:changed`銆?
- `bank_class` / `bank_name`锛氭棫椤圭洰寮?鎴疯瀛楁鍜屾柊椤圭洰寮?鎴疯瀛楁锛屾垚鍔熷悗鍐欏叆 `user_auths.bank_name`銆?
- `bank_info` / `bank_addr`锛氭棫椤圭洰寮?鎴峰湴鍧?瀛楁鍜屾柊椤圭洰寮?鎴峰湴鍧?瀛楁锛屾垚鍔熷悗鍐欏叆 `user_auths.bank_addr`銆?
- `is_bank_synced=1`锛氳〃绀烘湰娆″凡瀹℃牳閾惰鍗″揩鐓у凡缁忓悓姝ュ埌 MT4 comment銆?
- `ResponseCode::UPDATED`锛氳韩浠借瘉鎴栭摱琛屽崱鍒嗘敮鏍￠獙閫氳繃锛屽閮ㄥ悓姝ュ拰鏈湴浜嬪姟鍐欏叆瀹屾垚銆?
- `ResponseCode::VALIDATION_FAILED`锛氳韩浠借瘉鍙烽噸澶嶆垨鍙傛暟鏍煎紡涓嶅悎娉曪紱鎺ュ彛涓嶄細鍐欏叆璧勬枡銆?
- `ResponseCode::MT4_SYNC_FAILED`锛歁T4 comment 鍚屾澶辫触锛涙帴鍙ｄ笉浼氬啓鍏ュ熀纭?璧勬枡鎴栭摱琛屽崱蹇収銆?

### 涓轰粈涔堣繖鏍峰仛

- 鏃ч」鐩敤鎴疯祫鏂欑紪杈戦〉鎶婅韩浠借瘉鍜岄摱琛屽崱蹇収鏀惧湪鍚屼竴涓? `cust_save_info` 淇濆瓨鍔ㄤ綔涓紱鏂伴」鐩鏋滃彧淇濆瓨鍩虹璧勬枡锛屼細瀵艰嚧鏃у瓧娈垫彁浜ゅ悗闈欓粯涓㈠け銆?
- 韬唤璇佸彿鏄疄鍚嶅敮涓?鍑瘉锛屽繀椤诲湪鍐欏簱鍓嶆帓闄ゅ叾瀹冪敤鎴峰崰鐢紝瑙ｅ喅閲嶅瀹炲悕璧勬枡甯︽潵鐨勮璇佸拰鍑洪噾椋庨櫓銆?
- 宸插鏍搁摱琛屽崱浼氬弬涓庡嚭閲戝拰 MT4 澶囨敞灞曠ず锛屽繀椤诲厛鍚屾 MT4 comment锛屽啀鍐欐湰鍦? `user_auths` 闀滃儚锛岄伩鍏嶄氦鏄撶涓庡悗鍙拌祫鏂欎笉涓?鑷淬??
- 閾惰鍗″彿鍜岃韩浠借瘉鍙峰睘浜庢晱鎰熶俊鎭紝瀹¤鏃ュ織鍙褰曞凡鍙樻洿锛屼笉璁板綍瀹屾暣鍙风爜锛屼繚鐣欒拷韪兘鍔涘悓鏃堕伩鍏嶆棩蹇楁硠闇层??

### TDD 鎵ц璁板綍

- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit tests\Feature\AdminUserUpdateAuthAndBankClosureModuleTest.php --colors=never` 棣栨澶辫触锛屽懡涓? `userIdcardNo` 鏈啓鍏? `user_auths.id_card_no`銆侀噸澶嶈韩浠借瘉浠嶈繑鍥? `UPDATED`銆佸凡瀹℃牳閾惰鍗℃湭璋冪敤 `Mt4ManagerService::updateComment`銆丮T4 澶辫触鏈叧闂啓鍏ャ?佹渶缁堟竻鍗曠己灏戝疄鍚嶄笌閾惰鍗″垎鏀瘉鎹??
- GREEN锛氳ˉ榻愯韩浠借瘉瀛楁褰掍竴涓庡敮涓?鎬ф牎楠屻?侀摱琛屽崱鐩爣蹇収銆丮T4 comment 鍓嶇疆鍚屾銆乣is_bank_synced` 鍐欏叆鐧藉悕鍗曘?佽劚鏁忓璁″拰绗? 374 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 鍓╀綑杈圭晫

- 鏈疆娌℃湁鎶婁笂绾т唬鐞嗐?佸嚭鍏ラ噾寮?鍏炽?佺煭淇￠?氱煡銆丮T4 娉ㄥ唽鏃ユ湡鑱斿姩鍜岀壒娈婅繍钀ュ彛寰勫苟鍏ヨ祫鏂欑紪杈戞帴鍙ｏ紱杩欎簺浠嶉渶鎸夌湡瀹炲瓧娈点?佹潈闄愬拰澶栭儴鏈嶅姟杈圭晫缁х画鍗曠嫭闂幆銆?
- 鏈疆娌℃湁鏀瑰姩鍓嶅彴瀹炲悕璁よ瘉鎴栭摱琛屽崱鎹㈢粦娴佺▼锛涘墠鍙拌祫鏂欐彁浜ゅ拰鎹㈢粦宸茬敱 `ProfileController` 鐩稿叧闂幆鐙珛瑕嗙洊銆?

## 375. 2026-07-28 鍚庡彴鏅?氱敤鎴疯祫鏂欑紪杈戝嚭鍏ラ噾寮?鍏抽棴鐜?

### 鏈澶勭悊鐩爣

- 涓? `AdminUserController::updateUser` 琛ラ綈鏃ч」鐩? `CustomerController::cust_save_info` 鐨? `isoutmoney` 鍜? `isallowmoney` 鍒嗘敮銆?
- 楠岃瘉鏃у瓧娈? `isoutmoney` 鍐欏叆 `user_infos.is_withdrawal_allowed`锛屾棫瀛楁 `isallowmoney` 鍐欏叆 `user_infos.is_deposit_allowed`銆?
- 楠岃瘉寮?鍏冲?煎彧鎺ュ彈 `0` 鍜? `1`锛涢潪娉曞?艰繑鍥? `ResponseCode::VALIDATION_FAILED`锛屽苟涓斾笉浼氬厛鍐欏叆鐢ㄦ埛鍩虹璧勬枡銆?
- 楠岃瘉鎴愬姛淇敼鍚庡啓鍏? `operation_logs`锛岃褰曞嚭閲戝拰鍏ラ噾寮?鍏崇殑鏂版棫鍊笺??
- 楠岃瘉鐜颁唬鏁忔劅瀛楁 `is_withdrawal_allowed/is_deposit_allowed` 浠嶇敱 `AdminUserUpdateFieldWhitelistClosureModuleTest` 淇濇寔榛樿蹇界暐锛岄伩鍏嶄换鎰忕幇浠ｈ姹傜粫杩囨棫瀛楁鍏煎杈圭晫銆?

### 鏈鍙樻洿鏂囦欢

- `tests/Feature/AdminUserUpdateDepositWithdrawalSwitchClosureModuleTest.php`
  - 鏂板鏃у瓧娈靛嚭鍏ラ噾寮?鍏虫垚鍔熻惤搴撳拰瀹¤鏃ュ織鏍蜂緥銆?
  - 鏂板闈炴硶鏃у瓧娈靛?煎け璐ヤ笖鍩虹璧勬枡涓嶈惤搴撴牱渚嬨??
  - 鏂板鏈?缁堟竻鍗曡瘉鎹牱渚嬶紝鍥哄畾鏈棴鐜矾鐢便?佸瓧娈靛拰娴嬭瘯鏂囦欢銆?
- `app/Http/Controllers/Admin/AdminUserController.php`
  - `normalizedUserUpdatePayload` 鍏煎鏃у瓧娈? `isoutmoney/isallowmoney`銆?
  - `updateUser` 瀵瑰綊涓?鍚庣殑寮?鍏冲?煎仛 `0/1` 涓ユ牸鏋氫妇鏍￠獙銆?
  - `userProfileUpdates` 浠呭啓鍏ユ棫瀛楁褰掍竴鍚庣殑 `user_infos.is_withdrawal_allowed` 鍜? `user_infos.is_deposit_allowed`銆?

### 璺敱涓庢墽琛岄摼

- `POST /api/admin/updateUser` / `admin_api_updateUser` -> `AdminUserController::updateUser` -> `normalizedUserUpdatePayload` -> 鏃у瓧娈? `isoutmoney/isallowmoney` 褰掍竴鍖栦负 `is_withdrawal_allowed/is_deposit_allowed` -> `Validator(in:0,1)` -> `UserInfo::where(user_id)` -> `AdminDataScopeService::canAccessUser` -> `DB::transaction` -> `user_infos.update` -> `operation_logs.create(is_withdrawal_allowed:鏃у??->鏂板??; is_deposit_allowed:鏃у??->鏂板??)` -> `success(user, UPDATED)`銆?
- `PATCH /api/admin/users/{user}` / `admin_api_updateUser` -> 鍚屼竴 `AdminUserController::updateUser` 閾捐矾锛屽尯鍒槸涓氬姟鐢ㄦ埛 ID 鏉ヨ嚜璺敱 `{user}`锛岄潪娉曞紑鍏冲?间細鍦ㄤ簨鍔″墠杩斿洖 `VALIDATION_FAILED`銆?

### 鍙傛暟鍜岃繑鍥炵粨鏋滀腑鏂囧惈涔?

- `isoutmoney`锛氭棫鍚庡彴鍑洪噾寮?鍏筹紱`0` 琛ㄧず鍏佽鍑洪噾锛宍1` 琛ㄧず绂佹鍑洪噾銆?
- `isallowmoney`锛氭棫鍚庡彴鍏ラ噾寮?鍏筹紱`0` 琛ㄧず鍏佽鍏ラ噾锛宍1` 琛ㄧず绂佹鍏ラ噾銆?
- `user_infos.is_withdrawal_allowed`锛氭柊椤圭洰鍑洪噾闄愬埗瀛楁锛屽惈涔変笌鏃ч」鐩? `is_out_money` 涓?鑷淬??
- `user_infos.is_deposit_allowed`锛氭柊椤圭洰鍏ラ噾闄愬埗瀛楁锛屽惈涔変笌鏃ч」鐩? `is_allow_money` 涓?鑷淬??
- `ResponseCode::UPDATED`锛氬紑鍏冲?煎悎娉曪紝鐢ㄦ埛璧勬枡鍜屽璁℃棩蹇楀凡鍦ㄥ悓涓?浜嬪姟閲屽畬鎴愩??
- `ResponseCode::VALIDATION_FAILED`锛氬紑鍏冲?间笉鏄? `0` 鎴? `1`锛屾帴鍙ｄ笉浼氱户缁啓 `user_infos`銆?

### 涓轰粈涔堣繖鏍峰仛

- 鏃ч」鐩祫鏂欑紪杈戦〉浼氭彁浜? `isoutmoney/isallowmoney`锛屽鏋滄柊椤圭洰蹇界暐杩欎袱涓瓧娈碉紝鍚庡彴淇濆瓨浼氭樉绀烘垚鍔熶絾鐢ㄦ埛浠嶅彲鎸夋棫鐘舵?佸叆閲戞垨鍑洪噾銆?
- 鍑哄叆閲戝紑鍏崇洿鎺ュ奖鍝嶅墠鍙板叆閲戙?佸嚭閲戙?佽繑浣ｈ浆璐︾瓑璧勯噾鍏ュ彛锛屽繀椤讳弗鏍奸檺鍒朵负 `0/1`锛屼笉鑳芥妸 `0abc` 鎴? `2` 瀹芥澗杞崲鎴愭湁鏁堢姸鎬併??
- 鍙紑鏀炬棫瀛楁鍏煎鍏ュ彛锛屼繚鐣欑幇浠ｆ晱鎰熷瓧娈甸粯璁ゅ拷鐣ワ紝鍙互鍦ㄨ縼绉绘棫 Blade 鐨勫悓鏃剁淮鎸佹柊 REST 鎺ュ彛鐨勬晱鎰熷瓧娈佃竟鐣屻??

### TDD 鎵ц璁板綍

- RED锛氬厛浠ュ悓绛夎涓烘祴璇曠‘璁ゅけ璐ワ紝棣栨澶辫触鍛戒腑鏃у瓧娈垫病鏈夊啓鍏? `user_infos.is_withdrawal_allowed/is_deposit_allowed`銆侀潪娉曞?间粛杩斿洖 `UPDATED`銆佹渶缁堟竻鍗曠己灏戝嚭鍏ラ噾寮?鍏冲垎鏀瘉鎹紱闅忓悗褰掑苟鍒板凡鏈夎鑼冨懡鍚? `AdminUserUpdateDepositWithdrawalSwitchClosureModuleTest`銆?
- GREEN锛氳ˉ榻愭棫瀛楁褰掍竴銆乣0/1` 涓ユ牸鏍￠獙銆佸紑鍏冲瓧娈靛啓鍏ャ?佸璁℃棩蹇楀拰绗? 375 鑺傛竻鍗曞悗锛宍php vendor\bin\phpunit tests\Feature\AdminUserUpdateDepositWithdrawalSwitchClosureModuleTest.php` 閫氳繃銆?

### 鍓╀綑杈圭晫

- 涓婄骇浠ｇ悊 `parent_id/userparentId` 璋冩暣宸插湪绗? 377 鑺傝ˉ榻愶紝鏈妭鍙繚鐣欏嚭鍏ラ噾寮?鍏宠竟鐣屻??
- 鏈疆娌℃湁浼?犵煭淇￠?氱煡鍜? MT4 娉ㄥ唽鏃ユ湡鑱斿姩锛涜繖浜涜兘鍔涢渶瑕佸湪鍚庣画鏈夌湡瀹炴湇鍔¤竟鐣屾椂缁х画杩佺Щ銆?

## 376. 2026-07-28 鍚庡彴鏅?氱敤鎴疯祫鏂欑紪杈? MT4 鍙鐘舵?侀棴鐜?

### 鏈澶勭悊鐩爣

- 涓? `AdminUserController::updateUser` 琛ラ綈鏃ч」鐩? `CustomerController::cust_save_info` 鐨? `enablereadonly` 鍒嗘敮銆?
- 楠岃瘉鏃у瓧娈? `enablereadonly=1` 浼氬厛璋冪敤 `Mt4ManagerService::lockUser`锛岃繙绔垚鍔熷悗鎵嶅啓鍏? `user_infos.is_mt4_readonly=1`銆?
- 楠岃瘉鏃у瓧娈? `enablereadonly=0` 浼氬厛璋冪敤 `Mt4ManagerService::unlockUser`锛岃繙绔け璐ユ椂杩斿洖 `ResponseCode::MT4_SYNC_FAILED`锛屽苟涓斾笉鍐欏叆 `user_infos.user_name` 鎴? `user_infos.is_mt4_readonly`銆?
- 楠岃瘉 `enablereadonly` 鍙帴鍙? `0/1`锛岄潪娉曞?艰繑鍥? `ResponseCode::VALIDATION_FAILED`锛岄伩鍏? PHP 瀹芥澗杞崲鎶婂紓甯歌緭鍏ュ啓鎴愭湁鏁堜氦鏄撴潈闄愩??

### 鏈鍙樻洿鏂囦欢

- `tests/Feature/AdminUserUpdateReadonlyMt4ClosureModuleTest.php`
  - 鏂板鍙閿佸畾鎴愬姛銆佽В闄ゅ彧璇诲け璐ュ叧闂啓鍏ャ?侀潪娉曞彧璇诲?兼嫆缁濆啓鍏ュ拰鏈?缁堟竻鍗曡瘉鎹洓涓牱渚嬨??
- `app/Http/Controllers/Admin/AdminUserController.php`
  - `normalizedUserUpdatePayload` 灏嗘棫瀛楁 `enablereadonly` 褰掍竴鍖栦负鍐呴儴瀛楁 `is_mt4_readonly`銆?
  - `updateUser` 鍦ㄤ簨鍔″墠鏍￠獙 `is_mt4_readonly` 鐨? `0/1` 鏋氫妇锛屽苟鎸夌洰鏍囧?艰皟鐢? `lockUser` 鎴? `unlockUser`銆?
  - `userProfileUpdates` 缁х画涓嶆妸鍙鐘舵?佸綋鏅?氬熀纭?璧勬枡鐩存帴鍐欏叆锛岄伩鍏嶇粫杩? MT4 鍚屾杈圭晫銆?
- `docs/admin-legacy-migration-gap-audit.md`
  - 灏? `AdminUserUpdateReadonlyMt4ClosureModuleTest` 鍔犲叆 `CustomerController` 杩佺Щ璇佹嵁锛屽苟鏇存柊鍓╀綑寰呮牳瀵硅寖鍥淬??
- `tests/Feature/AdminLegacyMigrationGapAuditTest.php`
  - 灏嗗彧璇荤姸鎬侀棴鐜祴璇曞姞鍏ユ棫妯″潡杩佺Щ瀹¤鏂█锛岄槻姝㈠悗缁姤鍛婇仐婕忚鍒嗘敮銆?

### 璺敱涓庢墽琛岄摼

- `POST /api/admin/updateUser` / `admin_api_updateUser` -> `AdminUserController::updateUser` -> `normalizedUserUpdatePayload` -> 鏃у瓧娈? `enablereadonly` 褰掍竴鍖栦负 `is_mt4_readonly` -> `Validator(in:0,1)` -> `UserInfo::where(user_id)` -> `AdminDataScopeService::canAccessUser` -> 鐩爣鍊间负 `1` 鏃惰皟鐢? `Mt4ManagerService::lockUser(user_id)` -> `DB::transaction` -> `user_infos.update(is_mt4_readonly=1)` -> `operation_logs.create(is_mt4_readonly:0->1)` -> `success(user, UPDATED)`銆?
- `PATCH /api/admin/users/{user}` / `admin_api_updateUser` -> 鍚屼竴 `AdminUserController::updateUser` 閾捐矾锛屽尯鍒槸涓氬姟鐢ㄦ埛 ID 鏉ヨ嚜璺敱 `{user}`锛涚洰鏍囧?间负 `0` 鏃惰皟鐢? `Mt4ManagerService::unlockUser(user_id)`锛岃繙绔け璐ュ垯杩斿洖 `MT4_SYNC_FAILED`锛屼笉杩涘叆 `DB::transaction`銆?

### 鍙傛暟鍜岃繑鍥炵粨鏋滀腑鏂囧惈涔?

- `enablereadonly`锛氭棫鍚庡彴璧勬枡缂栬緫瀛楁锛沗1` 琛ㄧず灏? MT4 璐﹀彿閿佷负鍙锛岀敤鎴峰彲浠ョ櫥褰曚絾涓嶈兘浜ゆ槗锛沗0` 琛ㄧず瑙ｉ櫎鍙锛屾仮澶嶄氦鏄撹兘鍔涖??
- `user_infos.is_mt4_readonly`锛氭柊椤圭洰鏈湴 MT4 鍙闀滃儚瀛楁锛涘彧鍦? MT4 鏄庣‘鎴愬姛鍚庢洿鏂帮紝鐢ㄤ簬鍚庡彴鍒楄〃鍜岃鎯呭睍绀恒??
- `Mt4ManagerService::lockUser`锛氳繙绔攣瀹氫氦鏄撹处鍙风殑鏈嶅姟鏂规硶锛岃繑鍥? `status=ok` 涓? `err=0` 鎵嶄唬琛ㄩ攣瀹氭垚鍔熴??
- `Mt4ManagerService::unlockUser`锛氳繙绔В闄や氦鏄撹处鍙峰彧璇荤殑鏈嶅姟鏂规硶锛岃繑鍥為潪鎴愬姛鏃朵唬琛ㄤ氦鏄撶鏈‘璁よВ闄ゃ??
- `ResponseCode::UPDATED`锛歁T4 鍚屾鎴愬姛涓旀湰鍦颁簨鍔″啓鍏ュ畬鎴愩??
- `ResponseCode::VALIDATION_FAILED`锛氬彧璇诲?间笉鏄? `0` 鎴? `1`锛屾帴鍙ｄ笉浼氱户缁啓鍏ヤ换浣曟湰鍦拌祫鏂欍??
- `ResponseCode::MT4_SYNC_FAILED`锛歁T4 lock/unlock 鏈槑纭垚鍔燂紝鎺ュ彛涓嶄細杩涘叆鏈湴浜嬪姟锛岄伩鍏嶅悗鍙扮姸鎬佸拰鐪熷疄浜ゆ槗鏉冮檺鍒嗗弶銆?

### 涓轰粈涔堣繖鏍峰仛

- 鏃ч」鐩? `cust_save_info` 鎶? `enablereadonly` 鏀惧湪鏅?氱敤鎴疯祫鏂欑紪杈戜繚瀛樺姩浣滈噷锛涙柊椤圭洰濡傛灉蹇界暐璇ュ瓧娈碉紝鏃? Blade 椤甸潰淇濆瓨浼氭樉绀烘垚鍔熶絾浜ゆ槗鏉冮檺娌℃湁鍙樺寲銆?
- MT4 鍙鐘舵?佺洿鎺ュ奖鍝嶇敤鎴疯兘鍚︿笅鍗曚氦鏄擄紝蹇呴』鍏堝悓姝ョ湡瀹炰氦鏄撶锛屽啀鍐欐湰鍦伴暅鍍忥紝瑙ｅ喅鈥滃悗鍙版樉绀哄彧璇讳絾 MT4 浠嶅彲浜ゆ槗鈥濇垨鈥滃悗鍙版樉绀哄彲浜ゆ槗浣? MT4 浠嶉攣瀹氣?濈殑鐘舵?佸垎鍙夐棶棰樸??
- 闈炴硶鏋氫妇鍊煎墠缃嫆缁濓紝鍙互閬垮厤瀛楃涓层?佹贩鍚堟暟瀛楁垨寮傚父鍊艰瀹芥澗杞崲鎴愪氦鏄撴潈闄愶紝淇濇寔璧勬枡缂栬緫鎺ュ彛鐨勫け璐ュ叧闂竟鐣屻??

### TDD 鎵ц璁板綍

- RED锛歚php vendor\bin\phpunit tests\Feature\AdminUserUpdateReadonlyMt4ClosureModuleTest.php` 棣栨澶辫触锛屽懡涓湭璋冪敤 `lockUser/unlockUser`銆丮T4 澶辫触浠嶈繑鍥? `UPDATED`銆侀潪娉? `enablereadonly=2` 鏈牎楠屽拰鏈?缁堟竻鍗曠己璇佹嵁銆?
- GREEN锛氳ˉ榻愭棫瀛楁褰掍竴銆乣0/1` 涓ユ牸鏍￠獙銆丮T4 lock/unlock 鍓嶇疆鍚屾銆佹湰鍦颁簨鍔″啓鍏ャ?佸璁℃棩蹇楀拰绗? 376 鑺傛竻鍗曞悗锛岀洰鏍囨祴璇曢?氳繃銆?

### 鍓╀綑杈圭晫

- 涓婄骇浠ｇ悊 `parent_id/userparentId` 璋冩暣宸插湪绗? 377 鑺傚崟鐙ˉ榻愶紝鏈妭鍙繚鐣? MT4 鍙鐘舵?佽竟鐣屻??
- 鏈疆娌℃湁浼?犵煭淇￠?氱煡銆丮T4 娉ㄥ唽鏃ユ湡鑱斿姩鍜岀壒娈婅繍钀ュ彛寰勶紱杩欎簺鑳藉姏闇?瑕佸湪鍚庣画鏈夌湡瀹炴湇鍔¤竟鐣屾椂缁х画杩佺Щ銆?

## 377. 2026-07-29 鍚庡彴鏅?氬鎴蜂笂绾т唬鐞嗕笌 MT4 灞傜骇涓?鑷存?ч棴鐜?

### 鏈澶勭悊鐩爣

- 瀵归綈鏃ч」鐩? `CustomerController::cust_save_info` 鐨? `data.userparentId`銆丮T4 `zip` 涓? `cny` 鐪熷疄璇箟锛屼笉鑳藉彧淇敼鏈湴 `parent_id`銆?
- 璇ヨ祫鏂欑紪杈戝叆鍙ｅ彧鍏佽璋冩暣 `account_type=2` 鐨勬櫘閫氬鎴凤紱浠ｇ悊鍟嗗眰绾т粛鐢变唬鐞嗕笓鐢ㄦ祦绋嬬淮鎶わ紝闃叉涓や釜鍏ュ彛鍚屾椂鏀逛唬鐞嗘爲銆?
- 鏂颁笂绾ч潪闆舵椂蹇呴』鏄綋鍓嶇鐞嗗憳鏁版嵁鑼冨洿鍐呯殑浠ｇ悊璐﹀彿锛沗0` 琛ㄧず鏀逛负骞冲彴鏍硅妭鐐广??
- MT4 鏄庣‘杩斿洖 `status=ok` 涓? `err=0` 鍚庯紝鎵嶅厑璁稿湪涓?涓湰鍦颁簨鍔″唴鏇存柊 `parent_id`銆乣family_tree`銆乣agent_descendants` 涓? `operation_logs`銆?
- 鏈湴浜嬪姟澶辫触鏃讹紝浣跨敤鏃? `parent_id` 涓庢棫浜旀鍏崇郴鐮佸弽鍚戣ˉ鍋? MT4锛岄伩鍏嶈繙绔拰鏁版嵁搴撳仠鍦ㄤ笉鍚屽眰绾с??

### 鏈鍙樻洿鏂囦欢

- `tests/Feature/AdminUserUpdateParentHierarchyClosureModuleTest.php`
  - 瑕嗙洊鎴愬姛杩佺Щ銆丮T4 鎷掔粷銆侀潪浠ｇ悊涓婄骇銆佺鐞嗗憳鑼冨洿澶栦唬鐞嗐?佹湰鍦颁簨鍔″け璐ヨˉ鍋裤?佺幇浠? `parent_id` 缁х画蹇界暐鍜屾枃妗ｅ绾︺??
- `tests/Feature/AdminUserUpdateParentAgentClosureModuleTest.php`
  - 淇濈暀鏃╂湡鏈湴鏍戝洖褰掑苟鏇存柊鑱岃矗杈圭晫锛氫唬鐞嗗晢閫氳繃鏅?氳祫鏂欏叆鍙ｈ縼绉绘椂蹇呴』鎷掔粷锛屾櫘閫氬鎴锋敼涓哄钩鍙版牴鑺傜偣鏃朵粛楠岃瘉 MT4 鎴愬姛鍚庣殑闂寘娓呯悊銆?
- `tests/Feature/Mt4ManagerServiceLegacyProtocolClosureModuleTest.php`
  - 鍥哄畾鏃у崗璁崟甯у繀椤诲悓鏃舵惡甯? `act=update_user&acc={瀹㈡埛ID}&zip={涓婄骇ID}&cny={浜旀鍏崇郴鐮亇`銆?
- `app/Http/Controllers/Admin/AdminUserController.php`
  - `normalizedUserUpdatePayload` 浠呭吋瀹规棫瀛楁 `userparentId/userParentId`锛屼笉寮?鏀剧幇浠ｆ晱鎰熷瓧娈? `parent_id` 鐩存帴鍐欏叆銆?
  - `updateUser` 鏍￠獙鏅?氬鎴疯韩浠姐?佺洰鏍囦唬鐞嗚韩浠姐?佸惊鐜闄╁拰 `AdminDataScopeService::canAccessUser($admin, $parentId, 'agent')`銆?
  - MT4 鎴愬姛鍚庢墠杩涘叆鏈湴浜嬪姟锛涗簨鍔″唴閲嶆柊閿佸畾瀹㈡埛銆侀攣瀹氱洰鏍囩鍏堝苟澶嶆牳灞傜骇蹇収锛屽け璐ユ椂璋冪敤鏃у揩鐓цˉ鍋裤??
  - 瀹¤鏃ュ織鍚屾椂璁板綍 `parent_id:鏃у??->鏂板?糮 涓? `family_tree:鏃у??->鏂板?糮锛屾柟渚胯拷婧綊灞炲彉鍖栥??
- `app/Services/FamilyTreeService.php`
  - `resolveCustomerHierarchy` 娌跨湡瀹? `parent_id` 鍥炴函绁栧厛锛屾嫆缁濈己澶辫妭鐐广?侀潪浠ｇ悊绁栧厛鍜屽惊鐜紝骞剁敓鎴愭柊 `family_tree` 涓庢棫 MT4 浜旀鍏崇郴鐮併??
  - `FamilyTreeService::syncCustomerDescendantRelations` 鍒犻櫎涓嶅啀灞炰簬鏃т唬鐞嗙殑鍏崇郴锛屾仮澶嶄粛鍛戒腑鐨勮蒋鍒犻櫎鍞竴琛岋紝骞堕噸绠? `depth/is_direct`銆?
- `app/Services/Mt4ManagerService.php`
  - 鏂板 `Mt4ManagerService::updateUserHierarchy`锛屼娇鐢ㄤ竴娆℃棫 `update_user` 甯у彂閫? `acc/zip/cny`锛岄伩鍏嶅彧鏇存柊鐩村睘涓婄骇鑰屽叧绯荤爜浠嶆槸鏃у?笺??

### 璺敱涓庢墽琛岄摼

- `POST /api/admin/updateUser` / `admin_api_updateUser` -> `AdminUserController::updateUser` -> `normalizedUserUpdatePayload` 灏嗘棫 `data.userparentId` 褰掍竴涓哄唴閮? `parent_agent_id` -> `Validator(integer,min:0)` -> 鏌ヨ鐩爣瀹㈡埛 -> `AdminDataScopeService::canAccessUser(..., 'user')` 鏍￠獙瀹㈡埛鑼冨洿 -> 鏍￠獙 `account_type=2` -> `validateParentAgentChange` 鏍￠獙鏂颁笂绾ф槸浠ｇ悊涓斾笉褰㈡垚寰幆 -> `AdminDataScopeService::canAccessUser(..., 'agent')` 鏍￠獙鐩爣浠ｇ悊鑼冨洿 -> `FamilyTreeService::resolveCustomerHierarchy` 鍒嗗埆璁＄畻鏂版棫绁栧厛銆佸璋卞拰浜旀鍏崇郴鐮? -> `Mt4ManagerService::updateUserHierarchy(acc,zip,cny)`銆?
- MT4 鎴愬姛閾撅細`updateUserHierarchy` 杩斿洖 `status=ok,err=0` -> `DB::transaction` -> `user_infos` 瀹㈡埛琛? `lockForUpdate` -> 鐩爣绁栧厛琛? `lockForUpdate` -> 鍐嶆 `resolveCustomerHierarchy` 闃插苟鍙戞紓绉? -> 鍐? `parent_id/family_tree/updated_by` 涓庡叾瀹冭祫鏂? -> `FamilyTreeService::syncCustomerDescendantRelations` -> `operation_logs.create` -> 鎻愪氦浜嬪姟 -> `success(user, UPDATED)`銆?
- MT4 澶辫触閾撅細杩滅寮傚父銆乣status!=ok` 鎴? `err!=0` -> 杩斿洖 `ResponseCode::MT4_SYNC_FAILED` -> 涓嶈繘鍏ユ湰鍦颁簨鍔★紝鍥犳瀹㈡埛濮撳悕銆乣parent_id`銆乣family_tree`銆侀棴鍖呰〃鍜屽璁℃棩蹇楅兘淇濇寔鏃у?笺??
- 鏈湴澶辫触琛ュ伩閾撅細MT4 鏂板眰绾ф垚鍔? -> 鏈湴浜嬪姟浠讳竴鍐欏叆鎶涘嚭寮傚父 -> Laravel 鍥炴粴鏈湴鍏ㄩ儴鍐欏叆 -> `compensateMt4Hierarchy` -> `Mt4ManagerService::updateUserHierarchy(瀹㈡埛ID,鏃т笂绾D,鏃у叧绯荤爜)` -> 璁板綍琛ュ伩缁撴灉 -> 杩斿洖 `ResponseCode::INTERNAL_ERROR`銆?
- `PATCH /api/admin/users/{user}` / `admin_api_updateUser` -> 澶嶇敤鍚屼竴鏂规硶锛屼笟鍔＄敤鎴? ID 鏉ヨ嚜璺敱 `{user}`锛涘彧鎻愪氦鐜颁唬 `parent_id` 鏃跺綊涓?鍖栫櫧鍚嶅崟涓嶄細澶嶅埗璇ュ瓧娈碉紝涓嶈皟鐢? MT4锛屼粛鍙洿鏂板厑璁哥殑鏅?氳祫鏂欍??

### 鍙傛暟鍜岃繑鍥炵粨鏋滀腑鏂囧惈涔?

- `userparentId`锛氭棫鍚庡彴璧勬枡缂栬緫瀛楁锛岃〃绀哄綋鍓嶇敤鎴锋柊鐨勭洿灞炰笂绾т唬鐞嗕笟鍔＄敤鎴? ID銆?
- `parent_agent_id`锛氭帶鍒跺櫒鍐呴儴褰掍竴鍖栧瓧娈碉紝鍙綔涓烘棫瀛楁妗ユ帴锛屼笉鐩存帴鏆撮湶缁欑幇浠? REST 琛ㄥ崟銆?
- `zip`锛氭棫 MT4 `update_user` 鐨勭洿灞炰笂绾у瓧娈碉紱绛変簬鏂? `userparentId`锛宍0` 琛ㄧず骞冲彴鏍硅妭鐐广??
- `cny`锛氭棫 MT4 浜旀浠ｇ悊鍏崇郴鐮侊紱鎸? `agent_levels.level_code` 鎶婁唬鐞? ID 鏀惧叆 1銆?2銆?3銆?4銆?5+ 浜斾釜妲戒綅锛岀┖妲藉浐瀹氫负 `0000`銆?
- `family_tree`锛氫粠鏍逛唬鐞嗗埌鐩村睘浠ｇ悊鍐嶅埌褰撳墠瀹㈡埛鑷韩鐨勯?楀彿閾撅紱骞冲彴鏍瑰鎴峰彧淇濆瓨鑷韩 ID銆?
- `agent_descendants.depth`锛氫唬鐞嗗埌瀹㈡埛鐨勮窛绂伙紱鐩村睘浠ｇ悊涓? `1`锛屽叾涓婁竴绾т负 `2`锛屼緷娆￠?掑銆?
- `agent_descendants.is_direct`锛歚1` 琛ㄧず褰撳墠 `agent_id` 灏辨槸瀹㈡埛鐩村睘涓婄骇锛宍0` 琛ㄧず闂存帴绁栧厛銆?
- `ResponseCode::UPDATED`锛歁T4 灞傜骇銆佹湰鍦板鎴疯祫鏂欍?佸璋便?佷唬鐞嗛棴鍖呭拰瀹¤鏃ュ織鍏ㄩ儴瀹屾垚銆?
- `ResponseCode::VALIDATION_FAILED`锛氱洰鏍囦笉鏄櫘閫氬鎴枫?佷笂绾т笉鏄唬鐞嗐?佸眰绾ч摼缂哄け/寰幆鎴栧弬鏁版牸寮忎笉鍚堟硶锛屾病鏈夎皟鐢? MT4 鎴栧啓鏈湴銆?
- `ResponseCode::PERMISSION_DENIED`锛氱鐞嗗憳鏃犳潈璁块棶鐩爣瀹㈡埛鎴栫洰鏍囦唬鐞嗭紝涓嶈兘璺ㄦ暟鎹寖鍥磋浆绉诲鎴枫??
- `ResponseCode::MT4_SYNC_FAILED`锛氳繙绔病鏈夋槑纭‘璁? `zip/cny` 鏇存柊锛屾湰鍦板叏閮ㄤ繚鎸佹棫鍊笺??
- `ResponseCode::INTERNAL_ERROR`锛歁T4 宸叉垚鍔熶絾鏈湴浜嬪姟澶辫触锛涙暟鎹簱宸茬粡鍥炴粴锛屽苟宸插皾璇曟妸 MT4 琛ュ伩鍥炴棫灞傜骇銆?

### 涓轰粈涔堣繖鏍峰仛

- 鏃ч」鐩悓涓?娆′繚瀛樹細鎶? `zip/cny` 鍙戠粰 MT4锛涘彧鏀规柊鏁版嵁搴撲細閫犳垚鍚庡彴褰掑睘銆佷氦鏄撶浠ｇ悊鍏崇郴銆佽繑浣ｄ笌鏁版嵁鑼冨洿浜掔浉鐭涚浘銆?
- 浜旀鍏崇郴鐮佷娇鐢ㄦ柊椤圭洰宸叉湁 `agent_levels.level_code` 浣滀负鏃х瓑绾фЫ浣嶇殑鐪熷疄瀵瑰簲鏉ユ簮锛屼笉浣跨敤纭紪鐮佹垨浼?犳槧灏勩??
- 鍏堣繙绔‘璁ゃ?佸啀鏈湴浜嬪姟锛岃В鍐斥?淢T4 鎷掔粷浣嗘暟鎹簱宸茬粡杩佺Щ鈥濈殑鍗婃垚鍔熼棶棰橈紱鏈湴寮傚父鍚庣殑鍙嶅悜琛ュ伩瑙ｅ喅鐩稿弽鏂瑰悜鐨勪笉涓?鑷淬??
- 鏂颁笂绾т篃鎵ц鏁版嵁鑼冨洿鏍￠獙锛岃В鍐崇鐞嗗憳鍙瀹㈡埛琚浆绉荤粰涓嶅彲瑙佷唬鐞嗗悗浜х敓鐨勮法绉熸埛褰掑睘闂銆?
- 鍙帴鍙楁棫 `userparentId`锛岀户缁拷鐣ョ幇浠? `parent_id`锛屼繚鐣欐棫 Blade 鍏煎鑳藉姏鍚屾椂閬垮厤鎵╁ぇ鏅?氳祫鏂欐帴鍙ｇ殑鏁忔劅瀛楁鏉冮檺銆?

### TDD 鎵ц璁板綍

- RED锛歚php vendor/bin/phpunit --colors=never tests/Feature/AdminUserUpdateParentHierarchyClosureModuleTest.php` 棣栨绋冲畾寰楀埌 `5 failures / 7 tests`锛岃瘉瀹? MT4 鏂规硶鏈皟鐢ㄣ?佹嫆缁濆垎鏀湭鍏抽棴銆佺洰鏍囦唬鐞嗚寖鍥存湭鏍￠獙銆佹湰鍦板紓甯告湭琛ュ伩銆佹枃妗ｆ湭璁板綍鐪熷疄閾捐矾銆?
- 鍗忚 RED锛歚php vendor/bin/phpunit --colors=never --filter update_user_hierarchy tests/Feature/Mt4ManagerServiceLegacyProtocolClosureModuleTest.php` 棣栨鍥? `updateUserHierarchy` 鏂规硶涓嶅瓨鍦ㄨ?屽け璐ャ??
- GREEN 鐩爣锛氫笟鍔″疄鐜板畬鎴愬悗鍏潯琛屼负鐢ㄤ緥鍏堣浆缁匡紱琛ラ綈鏈妭鏂囨。鍚庨噸鏂拌繍琛屽畬鏁寸洰鏍囨祴璇曚笌 MT4 鍗忚鍥炲綊锛岀粨鏋滆褰曚互鏈?缁堥獙璇佸懡浠よ緭鍑轰负鍑嗐??

### 鍓╀綑杈圭晫

- 鏈妭鍙鐞嗘櫘閫氬鎴风殑涓婄骇浠ｇ悊鍙樻洿锛涗唬鐞嗗晢鑷韩鎹笂绾т細褰卞搷鏁存５浠ｇ悊瀛愭爲鍜屽璐︽埛 MT4 鍏崇郴锛岀户缁敱浠ｇ悊鍟嗕笓鐢ㄩ棴鐜崟鐙疄鐜般??
- 鏈妭涓嶆墿澶х幇浠? `parent_id` 瀛楁鍐欐潈闄愶紝涔熶笉鏀瑰彉浜ゆ槗缁勩?佹潬鏉嗐?佸彧璇荤姸鎬併?佸瘑鐮併?侀摱琛屽崱绛夊叾瀹冭祫鏂欑紪杈戝垎鏀殑鏃㈡湁濂戠害銆?

## 378. 2026-07-29 鍚庡彴鏅?氱敤鎴锋棫鏈湴璧勬枡瀛楁闂幆

### 鏈澶勭悊鐩爣

- 琛ラ綈鏃? `CustomerController::cust_save_info` 鎻愪氦鐨? `sex`銆乣gift_allowed` 鍜? `userremark` 鏈湴璧勬枡瀛楁銆?
- 鏃у瓧娈靛垎鍒啓鍏? `user_infos.gender`銆乣user_infos.is_gift_allowed` 鍜? `user_infos.remark`锛岃繖浜涘瓧娈典笉璋冪敤 MT4 鎴栫煭淇℃湇鍔°??
- 闈炴硶鎬у埆銆侀潪娉曠ぜ鍝佸紑鍏冲繀椤诲湪鏁版嵁搴撲簨鍔″墠杩斿洖鍙傛暟閿欒锛屼笉鑳借繛甯﹀啓鍏ュ悓璇锋眰涓殑鐢ㄦ埛鍚嶆垨澶囨敞銆?
- 鐜颁唬瀛楁 `gender/is_gift_allowed/remark` 缁х画淇濇寔蹇界暐锛屽彧鎵挎帴鏃? Blade 瀛楁锛岄伩鍏嶆墿澶х幇浠ｈ祫鏂欐帴鍙ｇ櫧鍚嶅崟銆?

### 璺敱涓庢墽琛岄摼

- `POST /api/admin/updateUser` -> `AdminUserController::updateUser` -> `normalizedUserUpdatePayload` -> `sex` 缁? `normalizeLegacyGenderValue` 杞崲涓? `gender=1/2` -> `gift_allowed` 褰掍竴涓? `is_gift_allowed` -> `userremark` 褰掍竴涓? `remark` -> `Validator` 鏍￠獙鎬у埆銆佸紑鍏冲拰澶囨敞闀垮害 -> 鐢ㄦ埛涓庣鐞嗗憳鏁版嵁鑼冨洿鏍￠獙 -> `userProfileUpdates` 鐢熸垚鏈湴瀛楁鏇存柊 -> `userUpdateAuditContent` 鐢熸垚鏂版棫鍊? -> `DB::transaction` -> `user_infos.update` -> `operation_logs.create` -> 杩斿洖 `ResponseCode::UPDATED`銆?
- `PATCH /api/admin/users/{user}` -> 澶嶇敤鍚屼竴鏂规硶锛涙棫瀛楁涓嶅悎娉曟椂杩斿洖 `ResponseCode::VALIDATION_FAILED`锛屼笉杩涘叆浜嬪姟銆傚彧鎻愪氦鐜颁唬鍚屽悕瀛楁鏃讹紝褰掍竴鍖栫櫧鍚嶅崟涓嶄細澶嶅埗杩欎簺鏁忔劅鍒悕銆?

### 鍙傛暟銆佽繑鍥炲拰鎵ц缁撴灉涓枃鍚箟

- `sex`锛氭棫椤甸潰鎬у埆锛沗鐢?/male/m/1` 杞负 `user_infos.gender=1`锛宍濂?/female/f/2` 杞负 `2`銆?
- `gift_allowed`锛氭棫椤甸潰绀煎搧棰嗗彇寮?鍏筹紱`0` 琛ㄧず涓嶅厑璁搁鍙栵紝`1` 琛ㄧず鍏佽棰嗗彇銆?
- `userremark`锛氭棫椤甸潰鍚庡彴澶囨敞锛屽啓鍏? `user_infos.remark`锛屾渶澶? 500 涓瓧绗︺??
- `ResponseCode::UPDATED`锛氬悎娉曟棫瀛楁涓庡璁℃棩蹇楀凡鍦ㄥ悓涓?浜嬪姟鎻愪氦銆?
- `ResponseCode::VALIDATION_FAILED`锛氭?у埆涓嶅彲璇嗗埆銆佺ぜ鍝佸紑鍏充笉鏄? `0/1` 鎴栧娉ㄨ繃闀匡紝鏈湴璧勬枡鍏ㄩ儴淇濇寔鏃у?笺??

### 涓轰粈涔堣繖鏍峰仛

- 杩欎笁涓瓧娈靛彧褰卞搷鏈湴灞曠ず銆佺ぜ鍝佸叆鍙ｄ笌杩愯惀澶囨敞锛屼笉灞炰簬 MT4 鐪熷疄璐︽埛瀛楁锛涜皟鐢ㄤ笉瀛樺湪鐨勮繙绔湇鍔″弽鑰屼細鍒堕?犲亣闂幆銆?
- 鍏堝綊涓?鍐嶆牎楠岃В鍐虫棫椤甸潰涓嫳鏂囨?у埆鍊煎吋瀹癸紝鍚屾椂璁╂湭鐭ュ?兼槑纭け璐ワ紝涓嶈兘琚? PHP 瀹芥澗杞崲鎴愭湁鏁堟灇涓俱??
- 鍙紑鏀炬棫瀛楁妗ユ帴锛岃В鍐虫棫 Blade 淇濆瓨鏃犳晥鐨勯棶棰橈紝鍙堜笉浼氳鐜颁唬 REST 璇锋眰缁曡繃鏁忔劅璧勬枡鐧藉悕鍗曘??

### 娴嬭瘯璇佹嵁

- `AdminUserUpdateLegacyLocalProfileClosureModuleTest` 瑕嗙洊鎴愬姛钀藉簱涓庡璁°?侀潪娉曞?奸浂鍐欏叆銆佺幇浠ｅ埆鍚嶅拷鐣ュ拰鏂囨。濂戠害銆?
- 鎴愬姛鏃ュ織鍖呭惈 `gender:1->2`銆乣is_gift_allowed:0->1`銆乣remark:鏃у??->鏂板?糮锛屽垎鍒〃绀烘?у埆銆佺ぜ鍝佹潈闄愬拰杩愯惀澶囨敞鐨勭湡瀹炲彉鍖栥??

## 2026-07-28 鍚庡彴鎸佷粨姹囨?讳氦鏄撴槑缁嗕笅閽婚棴鐜?

### 鏈疆娴嬭瘯

- `AdminPositionSummaryTradeDetailDrilldownClosureModuleTest` 鐢ㄧ孩鐏‘璁? Layui 鎸佷粨姹囨?汇?佷氦鏄撹鍗曢〉榛樿绛涢?夈?丆rmUI 鏈湴琛屾搷浣滃拰杩佺Щ鏂囨。璇佹嵁缂哄け锛屽啀鎸夊悓涓?娴嬭瘯杩涘叆缁跨伅楠岃瘉銆?

### 璺敱涓庢墽琛岄摼璺?

- Layui 姹囨?诲叆鍙ｏ細`GET /admin/position-summary` -> `resources/admin/layui/position-summary/index.blade.php` -> `public/js/apps/admin/layui/pages.js::positionSummaryTradeDetail` -> `crmRoute('admin_page_trades')` -> `GET /admin/trades?user_id=褰撳墠琛岀敤鎴?&start_date=绛涢?夊紑濮嬫棩鏈?&end_date=绛涢?夌粨鏉熸棩鏈?&mode=all`銆?
- Layui 浜ゆ槗鏄庣粏锛歚GET /admin/trades` -> `resources/admin/layui/trades/index.blade.php` 杈撳嚭 `data-default-trade-*` -> `applyDefaultTradeQueryFilters` 鍐欏叆绛涢?夎〃鍗? -> `currentApiUrl = tradeModeUrls[defaultMode] || tradeModeUrls.all` 閫夋嫨鎺ュ彛 -> `POST /api/admin/tradeList` 杩斿洖褰撳墠鐢ㄦ埛浜ゆ槗璁㈠崟銆?
- CrmUI 姹囨?诲叆鍙ｏ細`GET /admin-crmui/position-summary` -> `CrmUi\Admin\PageController::show` -> `rowActions` 澹版槑 `position_summary_trades` -> `public/js/apps/crmui/admin.js::positionSummaryTradeDetail` -> `/admin-crmui/trades?user_id=褰撳墠琛岀敤鎴?&start_date=绛涢?夊紑濮嬫棩鏈?&end_date=绛涢?夌粨鏉熸棩鏈?&mode=all`銆?
- CrmUI 榛樿绛涢?夛細`CrmUi\Admin\PageController::definitionWithRequestDefaults` 璇诲彇鏌ヨ鍙傛暟涓殑绛涢?夊瓧娈? -> 鍐欏叆 `filters.value` -> `resources/admin/crmui/partials/module-page.blade.php` 娓叉煋琛ㄥ崟榛樿鍊? -> `currentPageFilter` 棣栨璇锋眰浜ゆ槗璁㈠崟 API銆?

### 鍙傛暟鍚箟

- `user_id`锛氬綋鍓嶆寔浠撴眹鎬昏鐨勪笟鍔＄敤鎴? ID锛岀敤浜庝氦鏄撻〉闄愬埗鍒拌鐢ㄦ埛璁㈠崟銆?
- `start_date`锛氭寔浠撴眹鎬婚〉褰撳墠绛涢?夊紑濮嬫棩鏈燂紝浜ゆ槗椤垫部鐢ㄥ悓涓?鏃堕棿涓嬮檺銆?
- `end_date`锛氭寔浠撴眹鎬婚〉褰撳墠绛涢?夌粨鏉熸棩鏈燂紝浜ゆ槗椤垫部鐢ㄥ悓涓?鏃堕棿涓婇檺銆?
- `mode`锛氫氦鏄撻〉妯″紡锛宍all` 琛ㄧず鍏ㄩ儴浜ゆ槗锛宍open` 琛ㄧず褰撳墠鎸佷粨锛宍closed` 琛ㄧず鍘嗗彶骞充粨锛涙湰杞寔浠撴眹鎬婚粯璁よ繘鍏? `all`锛屼繚璇佷粠姹囨?昏繘鍏ユ槑缁嗘椂鍏堢湅鍒板畬鏁磋鍗曘??

### 闂幆璇存槑

- 杩欐牱鍋氳В鍐斥?滄寔浠撴眹鎬诲彧鏈夎仛鍚堟暟瀛椼?佺己灏戞槑缁嗚拷婧叆鍙ｂ?濈殑闂銆?
- Layui 鍜? CrmUI 閮戒娇鐢? Lucide 鍥炬爣鎴栨棦鏈夋寜閽綋绯伙紝涓嶅紩鍏ヨ〃鎯呯鍙枫??
- 杩斿洖缁撴灉涓? `records` 琛ㄧず璁㈠崟鍒嗛〉鍒楄〃锛宍summary` 琛ㄧず褰撳墠绛涢?夊懡涓殑璁㈠崟鍚堣锛涚┖鍒楄〃浠ｈ〃璇ョ敤鎴峰湪褰撳墠鏃ユ湡鑼冨洿鍐呮病鏈変氦鏄撴槑缁嗐??

## 379. 2026-07-29 鍚庡彴鎸佷粨姹囨?讳氦鏄撹处鍙锋槧灏勯棴鐜?

### 鏈澶勭悊鐩爣

- 鏄庣‘鍖哄垎 CRM 涓氬姟鐢ㄦ埛 ID 涓? MT4 鐧诲綍鍙凤紝缁熶竴浠? `user_infos.mt4_code = mt4_trades.login` 杩炴帴涓氬姟鐢ㄦ埛鍜岃鍗曘??
- 鎸佷粨姹囨?汇?佷氦鏄撴槑缁嗗拰鍚庡彴 `custom_users` 鏁版嵁鑼冨洿蹇呴』鍏辩敤鍚屼竴璐﹀彿鏄犲皠锛屼笉鍏佽浠讳綍鍏ュ彛閫?鍥? `mt4_trades.login = user_infos.user_id`銆?
- 瀵规病鏈夋湁鏁? `mt4_code` 鐨勪笟鍔＄敤鎴疯繑鍥炵┖璁㈠崟缁撴灉锛屼笉鐚滄祴璐﹀彿锛屼笉鍒堕?犱笉瀛樺湪鐨勪氦鏄撴暟鎹??

### 璺敱涓庢墽琛岄摼

- 鎸佷粨姹囨?婚摼锛歚POST /api/admin/positionSummaryList` -> `PositionSummaryController::positionSummaryList` -> 绠＄悊鍛樻暟鎹寖鍥磋В鏋愬嚭鎴愬憳涓氬姟 `user_id` -> `user_infos.mt4_code` 杞崲涓烘垚鍛? MT4 鐧诲綍鍙? -> `mt4_trades.login` 鑱氬悎璁㈠崟 -> 杩斿洖 `records`銆傚叾涓? `records.data[*].user_id` 浠嶆槸涓氬姟鐢ㄦ埛 ID锛宍mt4_login` 鎵嶆槸鐢ㄤ簬浜ゆ槗渚ф煡璇㈢殑鐪熷疄鐧诲綍鍙枫??
- 鏄庣粏涓嬮捇閾撅細鎸佷粨姹囨?昏鎼哄甫涓氬姟 `user_id` -> `GET /admin/trades?user_id={涓氬姟鐢ㄦ埛ID}` -> `POST /api/admin/tradeList` -> `TradeController` 閫氳繃 `user_infos.mt4_code = mt4_trades.login` 鍏宠仈 -> 浠? `user_infos.user_id` 杩囨护 -> 杩斿洖鍚屼竴鐢ㄦ埛鐨勭湡瀹? MT4 璁㈠崟鍜屽綋鍓嶇瓫閫夋眹鎬汇??
- 鏉冮檺閾撅細`role_data_scopes.scope_type=custom_users` -> 璇诲彇鍏佽鐨勪笟鍔＄敤鎴? ID 闆嗗悎 -> 瀵? `user_infos.user_id` 搴旂敤鏁版嵁鑼冨洿 -> 鏄犲皠 `user_infos.mt4_code` -> 鏌ヨ `mt4_trades.login`銆傝繑鍥炶寖鍥村唴鏄犲皠璁㈠崟锛屾帓闄よ寖鍥村璁㈠崟鍜岄敊璇洿杩炶鍗曘??

### 鍙傛暟銆佽繑鍥炰笌鎵ц缁撴灉涓枃鍚箟

- `user_id`锛欳RM 涓氬姟鐢ㄦ埛 ID锛岀敤浜庣敤鎴疯祫鏂欍?佸綊灞炲拰绠＄悊鍛樻暟鎹寖鍥达紱瀹冧笉鏄? MT4 鐧诲綍鍙枫??
- `mt4_code`锛氫笟鍔＄敤鎴风粦瀹氱殑 MT4 鐧诲綍鍙凤紱鍊煎ぇ浜? `0` 鏃舵墠鍙互涓庝氦鏄撹〃寤虹珛鐪熷疄鍏宠仈銆?
- `mt4_trades.login`锛氳鍗曟墍灞? MT4 鐧诲綍鍙凤紝鍙兘涓? `user_infos.mt4_code` 姣旇緝銆?
- `records.total=1` 涓旇鍗曚负 `ticket=994601`銆乣login=884601`銆乣profit=45.25`锛氳〃绀虹湡瀹炴槧灏勯摼鍛戒腑鎴愬姛銆?
- 绌? `records`锛氳〃绀虹瓫閫夎寖鍥村唴娌℃湁璁㈠崟锛屾垨涓氬姟鐢ㄦ埛娌℃湁鏈夋晥 MT4 鏄犲皠锛涗笉浠ｈ〃鎺ュ彛閿欒銆?
- `ResponseCode::SUCCESS`锛氭煡璇€?佹槧灏勫拰鏉冮檺杩囨护鍧囧凡姝ｅ父鎵ц锛涙槸鍚︽湁璁㈠崟浠? `records.total` 涓哄噯銆?

### 涓轰粈涔堣繖鏍峰仛

- 涓氬姟鐢ㄦ埛缂栧彿鍜? MT4 鐧诲綍鍙锋潵鑷笉鍚岀紪鍙风┖闂达紝鎶婁袱鑰呯洿鎺ユ瘮杈冧細鎶婂叾瀹冭处鍙疯鍗曢敊璇綊缁欏綋鍓嶇敤鎴凤紝骞跺彲鑳界粫杩囧悗鍙版暟鎹寖鍥淬??
- 娴嬭瘯棰濆鍐欏叆 `login=user_id`銆乣ticket=994602`銆乣profit=999.99` 鐨勮楗佃鍗曪紱鍙湁浠嶄娇鐢ㄩ敊璇洿杩炵殑瀹炵幇鎵嶄細鍛戒腑瀹冿紝鍥犳鍙互绋冲畾闃绘鍘嗗彶閫昏緫鍥炲綊銆?
- `custom_users` 鍏堥檺鍒朵笟鍔＄敤鎴峰啀鏄犲皠 MT4 鐧诲綍鍙凤紝瑙ｅ喅绠＄悊鍛樻寚瀹氱敤鎴疯寖鍥翠笌浜ゆ槗琛ㄨ处鍙峰瓧娈靛彛寰勪笉鍚屽鑷寸殑瓒婃潈娉勯湶銆?
- 鏃? `MARGIN_RATE` 鍦ㄥ綋鍓嶇湡瀹炰氦鏄撹〃涓笉瀛樺湪锛岀户缁綔涓洪闄╂ā鍧楃己澶辫竟鐣岃褰曪紝鏈棴鐜笉浣跨敤浣欓鎴栫泩浜忓弽鎺ㄥ亣鍊笺??

### TDD 鎵ц璁板綍

- RED锛歚AdminPositionSummaryTradeAccountMappingClosureModuleTest` 棣栨杩愯鏃讹紝鎸佷粨姹囨?昏繑鍥炶楗电泩浜? `999.99`锛屼氦鏄撴暟鎹寖鍥磋繑鍥炶楗佃鍗? `ticket=994602`锛涗慨澶嶈繍琛屾椂鍚庯紝鏂囨。濂戠害浠嶅洜缂哄皯鏄犲皠閾捐鏄庝繚鎸佺孩鐏??
- GREEN锛歚PositionSummaryController`銆乣TradeController` 涓? `Mt4Trade` 缁熶竴鏄犲皠鍚庯紝鍙繑鍥炵湡瀹炶鍗? `ticket=994601`銆乣login=884601`銆乣profit=45.25`锛涙湰鑺傝ˉ榻愬璁″拰鎵ц閾惧悗锛岀敱鍚屼竴娴嬭瘯瀹屾垚鏈?缁堥獙璇併??
- 鍥炲綊娴嬭瘯锛歚AdminPositionSummaryTradeAccountMappingClosureModuleTest` 鍚屾椂瑕嗙洊姹囨?汇?佹槑缁嗐?乣custom_users` 鏉冮檺銆佽楗佃鍗曟帓闄ゅ拰鏂囨。濂戠害銆?

## 380. 2026-07-29 鍚庡彴椋庨櫓浜ゆ槗璐﹀彿鏄犲皠涓庣湡瀹炲己骞抽棴鐜?

### 鏈澶勭悊鐩爣

- 琛ラ綈鎸佷粨姹囨?昏繘鍏ラ鎺т腑蹇冨悗鐨勭湡瀹炲悗绔摼璺紝缁熶竴浣跨敤 `user_infos.mt4_code = mt4_trades.login`锛岀姝㈡妸涓氬姟鐢ㄦ埛 ID 鐩存帴褰? MT4 鐧诲綍鍙枫??
- 椋庨櫓鎸佷粨銆佸紓甯? IP 璇︽儏浜ゆ槗缁熻鍜屽己骞冲姩浣滃繀椤诲叡鐢ㄥ悓涓?鏄犲皠锛沗custom_users` 鍏堥檺鍒朵笟鍔＄敤鎴凤紝鍐嶄綔鐢ㄤ簬鏄犲皠鍚庣殑璁㈠崟銆?
- 淇濈暀鏃ч」鐩湡瀹炶兘鍔涜竟鐣岋細褰撳墠浜ゆ槗琛ㄦ病鏈? `MARGIN_RATE`锛屼笉鑳界敤鐩堜簭銆佹墜缁垂鎴栧父閲忓弽鎺ㄥ亣淇濊瘉閲戠巼銆?

### 椋庨櫓鎸佷粨鎵ц閾?

- `POST /api/admin/riskPositions` -> `RiskController::positions` -> `validateUserIdFilter` 鏍￠獙 `user_id` 蹇呴』鏄暣鏁? -> `baseOpenTradeRiskQuery` 鏌ヨ `cmd in (0..5)` 涓? `close_time is null/0` 鐨勬湭骞充粨璁㈠崟 -> `user_infos.mt4_code = mt4_trades.login` 鍏宠仈涓氬姟鐢ㄦ埛 -> `applyTradeFilters` 浣跨敤 `user_infos.user_id`銆乼icket銆乻ymbol 鍜屽紑浠撴棩鏈熺瓫閫? -> `applyDataScope` 浣跨敤 `user_infos.user_id` 杩囨护绠＄悊鍛樿寖鍥? -> `paginateQuery` 杩斿洖鍒嗛〉璁板綍 -> `summaryFor` 杩斿洖褰撳墠绛涢?夋眹鎬汇??
- `records.data[*].user_id` 杩斿洖 CRM 涓氬姟鐢ㄦ埛 ID锛宍records.data[*].login` 杩斿洖鐪熷疄 MT4 鐧诲綍鍙凤紝`ticket` 杩斿洖鐪熷疄璁㈠崟鍙凤紱涓夎?呰亴璐ｄ笉鍚岋紝涓嶄簰鐩哥寽娴嬨??
- `risk_value = profit - abs(commission)` 鍙〃绀烘墸闄ゆ墜缁垂鍚庣殑娴姩椋庨櫓鏀剁泭銆傝绾? `margin=null` 琛ㄧず褰撳墠蹇収娌℃湁鍙獙璇佷繚璇侀噾瀛楁锛宍total_margin=0` 琛ㄧず娌℃湁鍙眹鎬诲?硷紝涓嶇瓑浜庢棫 `MARGIN_RATE=0`銆?

### 寮傚父 IP 璇︽儏鎵ц閾?

- `POST /api/admin/riskIpDetail` -> `RiskController::riskIpDetail` -> 鏍￠獙蹇呭～ `login_ip` 鍜屽彲閫変笟鍔? `user_id` -> `baseRiskIpDetailQuery` 鎸? `user_login_logs.login_ip + user_id` 鑱氬悎鐧诲綍娆℃暟 -> 浜ゆ槗缁熻瀛愭煡璇㈤?氳繃 `user_infos.mt4_code = mt4_trades.login` 鐢熸垚涓氬姟鐢ㄦ埛缁村害 -> 鍒嗗埆璁＄畻鏈钩浠撳拰宸插钩浠撴暟閲? -> 鑱旀帴 `deposit_records.user_id` 涓? `withdraw_records.user_id` 鐨勭湡瀹為噾棰濇眹鎬? -> 瀵? `user_login_logs.user_id` 搴旂敤绠＄悊鍛樻暟鎹寖鍥? -> 杩斿洖 IP 鐢ㄦ埛璇︽儏銆?
- `open_order_count=1/closed_order_count=1` 琛ㄧず璇ヤ笟鍔＄敤鎴锋槧灏? MT4 璐﹀彿鍚勬湁涓?绗旂湡瀹炲紑浠撳拰骞充粨锛涜楗佃鍗曚笉浼氳繘鍏ョ粺璁°??

### 寮哄钩鎵ц閾?

- `POST /api/admin/riskForceClose/{id}` -> `RiskController::forceClose` -> `validateRiskRouteId` 鏍￠獙浜ゆ槗涓婚敭 -> 鏌ヨ鏈钩浠? `mt4_trades` -> 浠? `user_infos.mt4_code = mt4_trades.login` 鍏宠仈涓氬姟鐢ㄦ埛 -> `AdminDataScopeService` 瀵? `user_infos.user_id` 搴旂敤 `custom_users` 鎴栦唬鐞嗘爲鑼冨洿 -> 璇诲彇璁㈠崟鐪熷疄 `login/ticket` -> `RiskForceCloseGateway::close(login,ticket,comment)`銆?
- 缃戝叧杩斿洖 `isClosed=true` -> 鍐欏叆 `operation_logs`锛屽唴瀹硅褰? trade id銆佺湡瀹? login銆乼icket銆乸rovider reference 鍜屽娉? -> 杩斿洖 `ResponseCode::SUCCESS` 涓? provider reference銆?
- 缃戝叧鎷掔粷 -> 杩斿洖 `ResponseCode::OPERATION_NOT_ALLOWED`锛涜繛鎺ュけ璐? -> 杩斿洖 `ResponseCode::MT4_SYNC_FAILED`锛涜鍗曚笉瀛樺湪銆佸凡骞充粨鎴栦笉鍦ㄧ鐞嗗憳鑼冨洿 -> 杩斿洖 `ResponseCode::DATA_NOT_FOUND`銆備互涓婂け璐ョ粨鏋滃潎涓嶅啓鍋囧钩浠撶姸鎬侊紝涔熶笉杩藉姞鎴愬姛瀹¤銆?

### 鏉冮檺涓庤秺鏉冭竟鐣?

- `role_data_scopes.scope_type=custom_users` 涓繚瀛樼殑鏄笟鍔＄敤鎴? ID銆傛墽琛岄『搴忓繀椤绘槸 `custom_users -> user_infos.user_id -> user_infos.mt4_code -> mt4_trades.login`銆?
- 娴嬭瘯鍐欏叆 `login=user_id` 鐨勮楗佃鍗? `ticket=994702/994705`锛涙棫鐩磋繛浼氶敊璇繑鍥炰袱鏉★紝姝ｇ‘鏄犲皠鍙繑鍥? `ticket=994701/login=884701`銆?
- 鍙楅檺绠＄悊鍛樺彲鎶婅寖鍥村唴鐪熷疄璁㈠崟 `login=884701/ticket=994701` 浜ょ粰寮哄钩缃戝叧锛涜寖鍥村 `ticket=994703` 杩斿洖鏁版嵁涓嶅瓨鍦紝缃戝叧璋冪敤娆℃暟涓嶅鍔犮??

### TDD 涓庢墽琛岀粨鏋?

- RED锛氶闄╁垪琛ㄨ繑鍥炰袱鏉¤楗佃鍗曪紱寮傚父 IP 璇︽儏杩斿洖閿欒寮?浠撴暟 `2`锛涙槧灏勮鍗曞己骞宠繑鍥? `ResponseCode::DATA_NOT_FOUND`锛涜绾? `margin` 杩斿洖瀛楃涓? `"0"`锛涙枃妗ｆ病鏈夊畬鏁撮摼璺??
- GREEN锛氬洓鏉¤繍琛屾椂鐢ㄤ緥鍏堥?氳繃锛屽緱鍒扮湡瀹炶鍗? `ticket=994701`銆佺湡瀹? MT4 鐧诲綍鍙? `884701`銆佷笟鍔＄敤鎴? ID `984701`銆佸紑浠?/骞充粨 `1/1` 鍜屾纭綉鍏冲弬鏁帮紱琛岀骇 `margin=null`锛岄殢鍚庤ˉ榻愭湰鑺傛枃妗ｃ??
- `AdminRiskTradeAccountMappingClosureModuleTest` 瑕嗙洊璐﹀彿鏄犲皠銆乣custom_users`銆佽楗佃鍗曘?佸紓甯? IP 浜ゆ槗缁熻銆乣RiskForceCloseGateway`銆佽寖鍥村鎷掔粷銆乣MARGIN_RATE` 鐪熷疄缂哄け杈圭晫鍜屽璁℃枃妗ｃ??

### 鍓嶇鑱斿姩

- 椋庨櫓鑱斿姩宸查?氳繃 `position_summary_risk` 瀹屾垚锛氭寔浠撴眹鎬诲姩浣滄惡甯︿笟鍔? `user_id`銆佸紑濮嬫棩鏈熴?佺粨鏉熸棩鏈熷拰 `mode=positions` 杩涘叆 Layui/CrmUI 椋庢帶椤碉紱鍚庣鎸夋湰鑺傛槧灏勮В閲婅鍙傛暟銆?
- 椤甸潰鍔ㄤ綔缁х画浣跨敤 Lucide 鍥炬爣涓庢棦鏈夋寜閽綋绯伙紝涓嶄娇鐢ㄨ〃鎯呯鍙凤紱寮哄钩鎸夐挳鍙礋璐ｅ彂璧锋槑纭懡浠わ紝涓嶅湪娴忚鍣ㄧ鍒堕?犳垚鍔熺姸鎬併??
- `AdminPositionSummaryRiskDrilldownClosureModuleTest` 鍥哄畾涓ゅ鍚庡彴鍏ュ彛銆侀粯璁ょ瓫閫夈?侀闄╂ā寮忋?丆rmUI 鏈湴鍔ㄤ綔鍜岃縼绉绘枃妗ｈ瘉鎹紝闃叉鍚庣画鍙繚鐣欏悗绔槧灏勫嵈涓㈠け椤甸潰杩芥函閾捐矾銆?

## 381. 2026-07-31 礼品发放库存/积分联动边界锁定
- 结论：旧项目 Admin\GiftController@send_gift 只写 gift_shipments，不存在 gift_items 目录表，无任何库存/积分扣除逻辑。
- gift_items（points_cost、stock_quantity）是新项目新增目录能力，仅用于前台 vailable_gifts 展示，前台 /api/front/gifts 为只读。
- 当前无用户积分余额表、无兑换/领取 API，第一阶段不伪造“兑换扣库存/积分消耗联动”，与旧项目行为一致。
- 锁定：sendGift 不扣除 gift_items.stock_quantity、无礼品目录也能发放；前台 gift 相关路由无 redeem/exchange 入口。
- 测试：	ests/Feature/GiftStockDeductionBoundaryClosureModuleTest.php。