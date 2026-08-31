# 后台 Blade 页面与权限配置执行清???

> 更新时间???2026-06-06  
> 项目：`D:\Software\PhpProject\Demo\co_crmv5`  
> 范围：后台鉴权???Blade 后台页面、多语言、权限表配置、数据范围阶段成果???

## 1. 本次新增后台 Blade 页面

以下页面已从 `/admin/{path?}` ??? Naive 兜底页面中拆出，改为 Laravel Blade 模板直接渲染???

| 页面 | 路由名称 | Blade 文件 | JS 文件 | 主表??? ID |
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
| 代理等级 | `admin_api_agentLevelList` | 后续继续补新???/编辑按钮 |
| 组别配置 | `admin_api_groupConfigList` | 后续继续补新???/编辑按钮 |
| 系统配置 | `admin_api_systemConfigList` | 后续继续补编辑按??? |
| 支付通道 | `admin_api_channelList` | 后续继续补启???/禁用按钮 |
| 管理员账??? | `admin_api_adminList` | 后续继续补新???/编辑/删除按钮 |
| 新闻公告 | `admin_api_newsList` | 后续继续补新???/编辑/删除按钮 |
| 凭证审核 | `admin_api_voucherList` | `admin_api_voucherApprove`、`admin_api_voucherReject` |
| 风控管理 | `admin_api_riskPositions` | `admin_api_riskMarginCalls`、`admin_api_riskForceClose` |
| 黑名??? | `admin_api_blacklistList` | `admin_api_createBlacklist`、`admin_api_updateBlacklist`、`admin_api_deleteBlacklist` |
| 注销申请 | `admin_api_cancelApplyList` | `admin_api_cancelApplyApprove`、`admin_api_cancelApplyReject` |
| 交易订单 | `admin_api_tradeList` | `admin_api_openPositions`、`admin_api_closedPositions`、`admin_api_tradeSummary` |
| 大代??? | `admin_api_bigAgentList` | `admin_api_createBigAgent`、`admin_api_updateBigAgent`、`admin_api_deleteBigAgent` |

## 3. 权限表配???

新增迁移???

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

本轮继续按迁移缺口审计中??? P0 项推进，补齐旧项??? `BatchAmountController` 的第???阶段新项目落点：批量入金/出金导入记录的后台页面???API、权限配置???多语言和中文注释???

本轮新增文件???

- `app/Http/Controllers/Admin/BatchAmountImportController.php`
- `resources/admin/layui/deposit-imports/index.blade.php`
- `resources/admin/layui/withdraw-imports/index.blade.php`
- `public/js/admin/layui/deposit-imports/index.js`
- `public/js/admin/layui/withdraw-imports/index.js`
- `database/migrations/2026_06_07_000004_add_admin_batch_amount_import_permissions.php`
- `tests/Feature/AdminBatchAmountImportModuleTest.php`
- `tests/Feature/AdminBatchAmountImportPermissionMigrationTest.php`

本轮修改文件???

- `routes/admin.php`：新增批量入???/出金导入 API 路由???
- `routes/web.php`：新??? `/admin/deposit-imports`、`/admin/withdraw-imports` Blade 页面路由，放??? `/admin/{path?}` 兜底前???
- `app/Models/DepositImport.php`、`app/Models/WithdrawImport.php`、`app/Models/CreditImport.php`：重写为可读中文功能注释和字段???辑说明???
- `resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`：新增后端多语言消息???
- `public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`：新增前端表格???弹窗和状???多语言文案???
- `tests/Feature/AdminChineseCommentReadabilityTest.php`：扩大本轮新增文件的中文注释可读性覆盖???

新增后台页面???

| 页面 | 路由??? | Blade 文件 | JS 文件 | 表格 ID |
| --- | --- | --- | --- | --- |
| `/admin/deposit-imports` | `admin_page_deposit_imports` | `resources/admin/layui/deposit-imports/index.blade.php` | `public/js/admin/layui/deposit-imports/index.js` | `depositImportTable` |
| `/admin/withdraw-imports` | `admin_page_withdraw_imports` | `resources/admin/layui/withdraw-imports/index.blade.php` | `public/js/admin/layui/withdraw-imports/index.js` | `withdrawImportTable` |

新增后台 API???

| 接口 | 路由??? | 控制器方??? | 参数说明 |
| --- | --- | --- | --- |
| `POST /api/admin/depositImportList` | `admin_api_depositImportList` | `BatchAmountImportController@depositImportList` | `user_id` 按业务用户筛选；`batch_no` 按批次号模糊筛???；`is_synced` 按同步状态筛选；`page/per_page/limit` 控制分页??? |
| `POST /api/admin/createDepositImport` | `admin_api_createDepositImport` | `BatchAmountImportController@createDepositImport` | `user_id` 必填且必须存在于 `user_infos.user_id`；`amount` 必填且大??? 0；`batch_no` 必填；`user_name` 可留空由后端按用??? ID 自动补全??? |
| `POST /api/admin/withdrawImportList` | `admin_api_withdrawImportList` | `BatchAmountImportController@withdrawImportList` | 参数含义同入金导入列表，但读??? `withdraw_imports` 琛ㄣ?? |
| `POST /api/admin/createWithdrawImport` | `admin_api_createWithdrawImport` | `BatchAmountImportController@createWithdrawImport` | 参数含义同入金导入新增，但写??? `withdraw_imports` 琛ㄣ?? |

新增权限配置???

| slug | type | route/api_route | 功能 |
| --- | ---: | --- | --- |
| `admin_deposit_imports` | 1 | `/admin/deposit-imports` | 批量入金导入页面入口??? |
| `admin_batch_deposit_import_list` | 3 | `admin_api_depositImportList` | 批量入金导入列表接口权限??? |
| `admin_batch_deposit_import_create` | 3 | `admin_api_createDepositImport` | 新增批量入金导入记录按钮与接口权限??? |
| `admin_withdraw_imports` | 1 | `/admin/withdraw-imports` | 批量出金导入页面入口??? |
| `admin_batch_withdraw_import_list` | 3 | `admin_api_withdrawImportList` | 批量出金导入列表接口权限??? |
| `admin_batch_withdraw_import_create` | 3 | `admin_api_createWithdrawImport` | 新增批量出金导入记录按钮与接口权限??? |

实现边界???

- 已完成：导入记录列表、筛选???新增单条导入记录???权限表配置、按钮权限???多语言、数据范围过滤???中文注释覆盖???
- 暂未完成：旧项目 Excel/CSV 文件解析、导入失败重试???MT4 同步、导入模板下载???批量导出???这些属??? `BatchAmountController` 深层业务逻辑，下???轮继续迁移???

本轮验证命令???

```text
php artisan migrate --force
结果???2026_06_07_000004_add_admin_batch_amount_import_permissions 已成功执???

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
结果：均??? No syntax errors detected

node --check public\js\admin\layui\deposit-imports\index.js
node --check public\js\admin\layui\withdraw-imports\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
结果：全部???出码 0
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

写入??? API 权限???

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

统一实现文件???

- `public/js/admin/layui/layout.js`

实现规则???

- `/api/admin/menus` 返回??? `permissions` 数组会写??? `window.CrmAdminPermissions` ??? `localStorage.crm_admin_permissions`???
- Blade 页面敏感按钮通过 `data-permission="permissions.slug"` 声明权限???
- `layout.js` 统一扫描 `[data-permission]` 元素，当前管理员没有对应 slug 时自动隐藏???
- 前端隐藏只做体验控制，后端接口仍??? `check.permission:admin` ??? `permissions.api_route` 二次鉴权???

已接入按钮权限的页面???

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

本次补齐??? CRUD 入口???

| 页面 | 新增按钮 | 编辑按钮权限 | 删除按钮权限 | 弹窗表单 |
| --- | --- | --- | --- | --- |
| `/admin/blacklist` | `id="addBlacklist"` / `admin_blacklist_create` | `admin_blacklist_update` | `admin_blacklist_delete` | `id="blacklistModal"`，参数：`id`、`name`、`id_card`、`email`、`phone`、`remark` |
| `/admin/big-agents` | `id="addBigAgent"` / `admin_big_agent_create` | `admin_big_agent_update` | `admin_big_agent_delete` | `id="bigAgentModal"`，参数：`id`、`username`、`password`、`status` |
| `/admin/agent-levels` | `id="addAgentLevel"` / `admin_agent_level_create` | `admin_agent_level_update` | `admin_agent_level_delete` | `id="agentLevelModal"`，参数：`id`、`level`、`name`、`max_commission`、`min_commission`、`user_commission` |
| `/admin/group-configs` | `id="addGroupConfig"` / `admin_group_config_create` | `admin_group_config_update` | `admin_group_config_delete` | `id="groupConfigModal"`，参数：`id`、`group_name`、`radix`、`category`、`has_commission`、`is_enabled`、`is_ecn`、`is_default` |

接口参数说明???
- `admin_api_createBlacklist`：创建黑名单记录，`name` 为必填；`id_card`、`email`、`phone`、`remark` 用于补充识别和备注信息???
- `admin_api_updateBlacklist`：更新黑名单记录，Laravel 路由参数 `id` 通过 `routeParams.id` 传入，表??? `id` 仅用于前端判断新???/编辑模式???
- `admin_api_deleteBlacklist`：删除黑名单记录，Laravel 路由参数 `id` 通过 `routeParams.id` 传入???
- `admin_api_createBigAgent`：创建大代理账号，`username`、`password` 为必填，`status` 表示启用状??????
- `admin_api_updateBigAgent`：更新大代理账号，Laravel 路由参数 `id` 通过 `routeParams.id` 传入；`password` 留空时后端保留原密码???
- `admin_api_deleteBigAgent`：删除大代理账号，Laravel 路由参数 `id` 通过 `routeParams.id` 传入???
- `admin_api_createAgentLevel`：创建代理等级，`level` 映射到真实字??? `level_code`；`max_commission`、`min_commission`、`user_commission` 写入佣金配置???
- `admin_api_updateAgentLevel2`：更新代理等级，Laravel 路由参数 `id` 通过 `routeParams.id` 传入???
- `admin_api_deleteAgentLevel`：删除代理等级，Laravel 路由参数 `id` 通过 `routeParams.id` 传入???
- `admin_api_createGroupConfig`：创建组别配置，`group_name` 映射到真实字??? `name`；`category` 表示 1=代理组???2=用户组???
- `admin_api_updateGroupConfig`：更新组别配置，Laravel 路由参数 `id` 通过 `routeParams.id` 传入???
- `admin_api_deleteGroupConfig`：删除组别配置，Laravel 路由参数 `id` 通过 `routeParams.id` 传入???

## 5. 多语???

已补充：

- `resources/lang/zh-CN/admin.php`
- `resources/lang/en/admin.php`
- `public/js/common/lang/zh-CN.js`
- `public/js/common/lang/en.js`
- `public/js/common/i18n.js`

新增语言键覆盖：

- 页面标题：`admins`、`agent_levels`、`group_configs`、`system_configs`
- 筛???字段：`user_id`、`agent_id`、`user_name`、`keyword`
- 表格字段：`amount`、`status`、`name`、`code`、`level`、`configKey`、`configValue`、`username`、`title`、`publishStatus`、`updatedAt`
- 状???与动作：`pending`、`approved`、`rejected`、`processing`、`completed`、`settled`、`enabled`、`disabled`、`approve`、`reject`、`process`、`complete`、`settle`
- 第二批模块：`vouchers`、`review_status`、`reviewStatus`、`margin_calls`、`force_close`、`cancel_applies`、`trades`、`symbol`、`ticket`、`volume`、`profit`、`openTime`、`open_positions`、`closed_positions`、`idCard`、`phone`
- CRUD 弹窗：`password`、`create_blacklist`、`edit_blacklist`、`create_big_agent`、`edit_big_agent`
- 配置??? CRUD：`create_agent_level`、`edit_agent_level`、`max_commission`、`min_commission`、`user_commission`、`create_group_config`、`edit_group_config`、`group_name`、`radix`、`category`、`agent_group`、`user_group`

## 6. 交易模型修复

新增文件变更???

- `app/Models/UserTrade.php`

新增查询作用域：

| scope | 作用 | 业务规则 |
| --- | --- | --- |
| `scopeOpen()` | 查询当前持仓 | `close_time = 1970-01-01 00:00:00` |
| `scopeClosed()` | 查询历史平仓 | `close_time != 1970-01-01 00:00:00` |

## 7. 已验证命???

```text
vendor\bin\phpunit tests\Feature\AdminBladeModuleCoverageTest.php
结果：OK (20 tests, 60 assertions)

vendor\bin\phpunit tests\Feature\AdminLocalizationTest.php
结果：OK (2 tests, 10 assertions)

vendor\bin\phpunit tests\Feature\AdminBusinessPermissionMigrationTest.php
结果：OK (1 test, 163 assertions)

php artisan migrate --force
结果：第???批与第二批后台业务模块权限迁移已执行成功

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
结果：全部???出码 0

node --check public\js\admin\layui\layout.js
结果：???出码 0

vendor\bin\phpunit tests\Feature\AdminCrudUiControlsTest.php
结果：OK (4 tests, 35 assertions)

vendor\bin\phpunit tests\Feature\AdminConfigCrudPermissionMigrationTest.php
结果：OK (2 tests, 39 assertions)

node --check public\js\admin\layui\blacklist\index.js
结果：???出码 0

node --check public\js\admin\layui\big-agents\index.js
结果：???出码 0

node --check public\js\admin\layui\agent-levels\index.js
node --check public\js\admin\layui\group-configs\index.js
结果：???出码 0

node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
node --check public\js\common\i18n.js
结果：全部???出码 0
```

## 8. 2026-06-07 内容与账号类 CRUD 补齐

本轮已补??? `/admin/channels`、`/admin/admins`、`/admin/news` 三个后台 Blade 页面，继续保持???Blade 渲染页面 + JS 调用接口 + permissions 数据表驱动按钮显??? + 后端中间件二次鉴权???的实现方式???

本轮补齐??? CRUD 入口???

| 页面 | 新增按钮 | 编辑按钮权限 | 删除按钮权限 | 弹窗表单 |
| --- | --- | --- | --- | --- |
| `/admin/channels` | `id="addChannel"` / `admin_channel_create` | `admin_channel_update` | `admin_channel_delete` | `id="channelModal"`，参数：`id`、`name`、`channel_code`、`exchange_rate`、`sort`、`is_enabled`、`config` |
| `/admin/admins` | `id="addAdmin"` / `admin_admin_create` | `admin_admin_update` | `admin_admin_delete` | `id="adminModal"`，参数：`id`、`username`、`email`、`password` |
| `/admin/news` | `id="addNews"` / `admin_news_create` | `admin_news_update` | `admin_news_delete` | `id="newsModal"`，参数：`id`、`title`、`is_published`、`content` |

本轮新增或调整的接口???

- `admin_api_createChannel`：`POST /api/admin/createChannel`，创建支付???道；`name`、`channel_code` 为必填，`exchange_rate`、`sort`、`is_enabled`、`config` 为???道扩展参数???
- `admin_api_updateChannel`：`POST /api/admin/updateChannel/{id}`，更新指定支付???道；Laravel 路由参数 `id` 通过 `routeParams.id` 传入???
- `admin_api_deleteChannel`：`POST /api/admin/deleteChannel/{id}`，删除指定支付???道；删除入口必须具??? `admin_channel_delete`???
- `admin_api_createAdmin`：`POST /api/admin/createAdmin`，创建后台管理员；`username`、`email`、`password` 为必填???
- `admin_api_updateAdmin`：`POST /api/admin/updateAdmin/{id}`，更新后台管理员；编辑时 `password` 留空表示不修改密码???
- `admin_api_deleteAdmin`：`POST /api/admin/deleteAdmin/{id}`，删除后台管理员；删除入口必须具??? `admin_admin_delete`???
- `admin_api_createNews`：`POST /api/admin/createNews`，创建新闻公告；`title`、`content` 为必填，`is_published` 控制发布状??????
- `admin_api_updateNews`：`POST /api/admin/updateNews/{id}`，更新指定新闻公告；Laravel 路由参数 `id` 通过 `routeParams.id` 传入???
- `admin_api_deleteNews`：`POST /api/admin/deleteNews/{id}`，删除指定新闻公告；删除入口必须具备 `admin_news_delete`???

本轮新增权限迁移???

- `database/migrations/2026_06_07_000001_add_admin_content_crud_permissions.php`

写入??? `permissions.slug`???

- `admin_channel_create`、`admin_channel_update`、`admin_channel_delete`
- `admin_admin_create`、`admin_admin_update`、`admin_admin_delete`
- `admin_news_create`、`admin_news_update`、`admin_news_delete`

本轮补充多语???键：

- Laravel 后端语言包：`resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`
- 前端运行时语???包：`public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`
- 新增键覆盖：`exchange_rate`、`sort`、`config`、`config_json_placeholder`、`create_channel`、`edit_channel`、`create_admin`、`edit_admin`、`password_keep_placeholder`、`content`、`published`、`unpublished`、`create_news`、`edit_news`

本轮新增测试???

- `tests/Feature/AdminContentCrudPermissionMigrationTest.php`
- `tests/Feature/AdminCrudUiControlsTest.php` 新增 channels/admins/news 三个页面控件覆盖断言???

本轮验证命令???

```text
php artisan migrate --force
结果???2026_06_07_000001_add_admin_content_crud_permissions 已执行成???

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
结果：全部???出码 0
```

## 9. 2026-06-07 系统配置编辑闭环补齐

本轮已补??? `/admin/system-configs` 的编辑入口，修正页面使用的字段与真实 `system_configs` 表字段一致：`key`、`value`、`group`、`description`???

本轮补齐的操作入口：

| 页面 | 编辑按钮权限 | 弹窗表单 | 说明 |
| --- | --- | --- | --- |
| `/admin/system-configs` | `admin_system_config_update` | `id="systemConfigModal"`，参数：`id`、`key`、`value`、`group`、`description` | `key` 只读用于识别配置项，`value/group/description` 可维??? |

本轮接口行为???

- `admin_api_systemConfigList`：`POST /api/admin/systemConfigList`；传??? `page/per_page/limit` 时返回平铺分页数据，便于 Layui 表格渲染；不传分页参数时仍兼容旧版按 `group` 分组返回???
- `admin_api_updateSystemConfig`：`POST /api/admin/updateSystemConfig`；支持单行编辑参??? `id/key/value/group/description`，也保留旧版 `configs[key]=value` 批量更新格式???

本轮新增权限迁移???

- `database/migrations/2026_06_07_000002_add_admin_system_config_update_permission.php`

写入??? `permissions.slug`???

- `admin_system_config_update`，绑??? `api_route=admin_api_updateSystemConfig`

本轮补充多语???键：

- Laravel 后端语言包：`system_config_not_found`、`group`、`description`、`edit_system_config`
- 前端运行时语???包：`group`、`description`、`edit_system_config`

本轮新增测试???

- `tests/Feature/AdminSystemConfigUpdatePermissionMigrationTest.php`
- `tests/Feature/AdminSystemConfigUpdateControllerTest.php`
- `tests/Feature/AdminCrudUiControlsTest.php` 新增系统配置编辑控件覆盖断言???

本轮验证命令???

```text
php artisan migrate --force
结果???2026_06_07_000002_add_admin_system_config_update_permission 已执行成???

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
结果：全部???出码 0
```

## 10. 2026-06-07 代理操作入口补齐

本轮已补??? `/admin/agents` 的业务操作入口，代理列表行内提供下级查看、等级调整???佣金调整，并全部绑??? `permissions.slug`???

本轮补齐的操作入口：

| 页面 | 下级查看权限 | 等级调整权限 | 佣金调整权限 | 弹窗表单 |
| --- | --- | --- | --- | --- |
| `/admin/agents` | `admin_agent_descendants` | `admin_agent_update_level` | `admin_agent_update_commission` | `agentLevelUpdateModal` 参数：`agent_id`、`level`；`agentCommissionUpdateModal` 参数：`agent_id`、`comm_rate` |

本轮接口行为???

- `admin_api_agentDescendants`：`POST /api/admin/agentDescendants`；参??? `agent_id` 表示业务代理用户 ID，接口继续执行数据范围校验???
- `admin_api_updateAgentLevel`：`POST /api/admin/updateAgentLevel`；参??? `agent_id`、`level`，用于更??? `user_infos.agent_level`???
- `admin_api_updateAgentCommission`：`POST /api/admin/updateAgentCommission`；参??? `agent_id`、`comm_rate`，`comm_rate` ??? 0 ??? 1 的佣金比例???

本轮新增权限迁移???

- `database/migrations/2026_06_07_000003_add_admin_agent_operation_permissions.php`

写入??? `permissions.slug`???

- `admin_agent_update_level`，绑??? `api_route=admin_api_updateAgentLevel`
- `admin_agent_update_commission`，绑??? `api_route=admin_api_updateAgentCommission`

本轮补充多语???键：

- `update_agent_level`
- `update_agent_commission`

本轮新增测试???

- `tests/Feature/AdminAgentOperationPermissionMigrationTest.php`
- `tests/Feature/AdminCrudUiControlsTest.php` 新增代理操作控件覆盖断言???

本轮验证命令???

```text
php artisan migrate --force
结果???2026_06_07_000003_add_admin_agent_operation_permissions 已执行成???

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
结果：全部???出码 0
```

## 11. 后续仍需继续补齐

- 继续??? `docs/admin-auth-permission-plan.md` 审计剩余已接入页面，优先补齐仍只有列表壳、缺少业务操作弹窗或缺少细粒度按钮权限的模块???
- 继续把旧文件中残留的乱码注释分批改成可读中文，优先处理本次直接涉及的控制器和中间件???
- 继续细化按钮权限：前端隐藏按钮只做体验优化，后端 `check.permission:admin` 仍是安全边界???
## 12. 2026-06-07 新旧项目迁移缺口审计与真??? DB 测试数据

本轮按用户要求继续深入对比旧项目后台控制器与新项目后台模块，并新增可验证审计文档???

- 新增审计文档：`docs/admin-legacy-migration-gap-audit.md`
- 新增测试：`tests/Feature/AdminLegacyMigrationGapAuditTest.php`
- 重写并扩展中文注释可读???测试：`tests/Feature/AdminChineseCommentReadabilityTest.php`

审计结论???

| 状??? | 说明 |
| --- | --- |
| 已迁??? | 登录、管理员、角色???权限???菜单???用户???代理基???列表、入金审核???出金审核???返佣列表???系统配置???支付???道、新闻???黑名单、注???申请、交易列表???凭证审核???大代理基础管理??? |
| 部分迁移 | 代理 V3 复杂统计、持???/平仓统计、权益汇总???风控???认证审核???出入金流水??? |
| 未迁??? | 批量入金/出金导入、批量信用导入???汇率维护???礼品发放与发货、在线用户???未入金流水、产???/交易品种管理、大编号后台、实时返佣明细??? |

真实 DB 测试数据来源???

- 采样连接：`127.0.0.1:3307 / co_crmv5`
- 采样方式：???过 `php artisan tinker` 直接读取当前 DB 琛ㄣ??
- 采样时间：`2026-06-07 Asia/Shanghai`

真实数量???

| 表或业务口径 | 数量 |
| --- | ---: |
| `admins` | 41 |
| `user_infos` | 40 |
| `agents`，来??? `user_infos.account_type = 1` | 5 |
| `customers`，来??? `user_infos.account_type <> 1` | 35 |
| `permissions` | 113 |
| `roles` | 50 |
| `system_configs` | 4 |
| `payment_channels` | 0 |

本轮发现的重要字段差异：

- 当前真实 DB ??? `user_infos` 表使??? `level_id` 表示代理等级，不存在 `agent_level` 字段???
- `payment_channels` 当前为空表，因此支付通道模块后续测试只能验证空列表???新增和编辑流程，不能编造已有支付???道样本???

本轮新增验证命令???

```text
vendor\bin\phpunit tests\Feature\AdminLegacyMigrationGapAuditTest.php
结果：OK (2 tests, 30 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
结果：OK (2 tests, 204 assertions)
```

## 14. 2026-06-07 批量信用导入后台闭环

本轮继续对比旧项??? `BatchCreditController` 与新项目后台模块，完成批量信用导入第???阶段迁移闭环。当前阶段先实现列表、筛选???手工新增???页面入口???权限配置???多语言和数据范围控制；Excel/CSV 文件解析、MT4 同步、失败重试和模板下载仍保留为后续深层迁移任务???

### 新增和维护文???

- `app/Http/Controllers/Admin/BatchCreditImportController.php`：批量信用导入后台控制器，参??? `user_id`、`credit_type`、`amount`、`batch_no`、`is_synced` 均有中文逻辑注释???
- `app/Models/CreditImport.php`：批量信用导入模型，对应真实数据??? `credit_imports`???
- `resources/admin/layui/credit-imports/index.blade.php`：Laravel Blade 页面，渲染筛选区、列表表格和新增弹窗???
- `public/js/admin/layui/credit-imports/index.js`：Layui 页面脚本，负责表格渲染???筛选???新增弹窗和按钮权限刷新???
- `database/migrations/2026_06_07_000005_add_admin_batch_credit_import_permissions.php`：从数据表写入页面和按钮/API 权限配置???
- `tests/Feature/AdminBatchCreditImportModuleTest.php`：验证页面路由???Blade 控件??? API 权限中间件???
- `tests/Feature/AdminBatchCreditImportPermissionMigrationTest.php`：验证权限迁移写??? `permissions.slug` ??? `permissions.api_route`???
- `tests/Feature/AdminChineseCommentReadabilityTest.php`：扩大中文注释可读???覆盖，防止本轮维护文件再次出现乱码???

### 页面与接???

| 类型 | 名称 | 地址或路由名 | 说明 |
| --- | --- | --- | --- |
| Blade 页面 | 批量信用导入 | `/admin/credit-imports` / `admin_page_credit_imports` | 当前项目非前后端分离，页面由 Laravel Blade 渲染??? |
| API | 列表查询 | `POST /api/admin/creditImportList` / `admin_api_creditImportList` | 支持 `page`、`per_page`、`limit`、`user_id`、`batch_no`、`credit_type`、`is_synced`??? |
| API | 新增记录 | `POST /api/admin/createCreditImport` / `admin_api_createCreditImport` | 写入真实 `credit_imports` 表，`user_id` 必须存在??? `user_infos.user_id`??? |

### 数据表权限配???

本轮新增权限全部来自 `permissions` 表配置，不在代码中硬编码角色授权???

| permissions.slug | permissions.api_route | 用??? |
| --- | --- | --- |
| `admin_credit_imports` | ??? | 批量信用导入页面/菜单节点??? |
| `admin_batch_credit_import_list` | `admin_api_creditImportList` | 批量信用导入列表查询接口权限??? |
| `admin_batch_credit_import_create` | `admin_api_createCreditImport` | 新增批量信用导入记录接口权限和页面按钮权限??? |

### 多语???支持

已补??? Laravel 后端语言包和前端运行时语???包：

- `resources/lang/zh-CN/admin.php`
- `resources/lang/en/admin.php`
- `public/js/common/lang/zh-CN.js`
- `public/js/common/lang/en.js`

新增键包括：`credit_imports`、`credit_imports_fetched`、`credit_import_created`、`credit_type`、`credit_type_temp`、`credit_type_permanent`、`credit_type_reward`、`credit_type_other`、`create_credit_import`???

### 真实 DB 测试数据来源

- 连接：`127.0.0.1:3307 / co_crmv5 / root / 123456`
- 当前真实表：`credit_imports`
- 字段：`id`、`user_id`、`user_name`、`credit_type`、`mt4_order_id`、`amount`、`batch_no`、`is_synced`、`fail_reason`、`remarks`、`created_by`、`updated_by`、`created_at`、`updated_at`、`deleted_at`
- 当前真实记录数：`0`

说明：由??? `credit_imports` 当前为空表，本轮测试不伪造已有信用导入样本，只验证空列表、页面入口???接口注册???权限配置和手工新增入口。后续做 Excel/CSV 导入??? MT4 同步时再基于真实导入数据扩展测试样本???

### 已完成边???

- 已完成批量信用导??? Blade 页面??? Layui 脚本???
- 已完成列表查询接口和手工新增接口???
- 宸插畬鎴? `permissions` 数据表驱动的页面、按钮和 API 权限配置???
- 已完成后端与前端多语???键???
- 已完成中文注释乱码修复，覆盖本轮信用导入、批量入???/出金导入、系统配置???代理操作相??? JS 文件???
- 已接入后台数据范围过滤，列表按当前管理员角色和绑定代理过滤可见业务用户数据???

### 后续未完成边???

- 未完??? Excel/CSV 上传解析???
- 未完??? MT4 信用同步???
- 未完成失败导入重试???
- 未完成导入模板下载???
- 未完成旧项目其它 P0 模块：出入金流水、持仓汇总???权益汇总等???

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
结果：全??? No syntax errors detected

node --check public\js\admin\layui\deposit-imports\index.js
node --check public\js\admin\layui\withdraw-imports\index.js
node --check public\js\admin\layui\credit-imports\index.js
node --check public\js\admin\layui\system-configs\index.js
node --check public\js\admin\layui\agents\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
结果：全部???出码 0

真实 DB 只读采样
结果???
credit_imports: 0
permissions: 122
admin_credit_imports => ??? api_route
admin_batch_credit_import_list => admin_api_creditImportList
admin_batch_credit_import_create => admin_api_createCreditImport
```

## 15. 2026-06-07 前台代理商菜单父级保留修???

本次针对前台 Layui 风格??? agent 账号登录后菜单可能丢失的问题，审计了前台布局、菜单接口和 `MenuService` 的权限过滤???辑???

### 问题原因

- 前台 Layui 布局文件 `resources/front/layui/layouts/app.blade.php` 中的左侧菜单容器 `#sideMenu` 不是写死菜单，???是??? `public/js/front/layui/layout.js` 登录后调??? `POST /api/front/navigation/menus` 动???渲染???
- 该接口最终进??? `App\Http\Controllers\Front\MenuController@userMenus`，再调用 `App\Services\MenuService::getUserMenus('front', $permissionIds)`???
- 原???辑在传??? `$permissionIds` 时只保留 `id in $permissionIds` 的顶级菜单???如果角色只授权??? `front_agent_sub`、`front_agent_customers` 等子菜单，但没有显式授权父级 `front_agent`，父级容器会被过滤掉，子菜单也无法展示???

### 本次修复

- 修改 `app/Services/MenuService.php`???
  - 父级菜单自身被授权时继续显示???
  - 父级菜单未直接授权，但存在已授权子菜单时，也保留父级菜单容器???
  - 子菜单列表仍然按 `$permissionIds` 过滤，避免未授权页面被展示???

### 新增回归测试

- 修改 `tests/Feature/AdminPermissionPlanTest.php`，新增：
  - `test_front_menu_tree_keeps_parent_when_only_child_permission_is_granted`
  - 覆盖“前台角色只授权子菜单时必须保留父级菜单”的场景???

### 后台登录信息复核

- 后台登录页面路由：`GET /admin/login`
- 后台登录接口：`POST /api/admin/login`
- `database/seeders/InitialDataSeeder.php` 中默认超级管理员???
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
结果：未进入业务断言，当前本??? MySQL 127.0.0.1:3307 拒绝连接???
错误：SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接???
```

### 待数据库恢复后复???

数据库连接恢复后，需要继续用真实 DB 复核以下数据???

- `permissions` ??? `guard_type=front` 的菜单树是否包含代理商父级与子级菜单???
- agent 登录账号对应的角色是否???过 `role_permissions` 授权了前台代理商菜单???
- `POST /api/front/navigation/menus` ??? agent token 下是否返??? `front_agent` 及其子菜单???

## 16. 2026-06-07 后台资金流水模块第一阶段

本轮继续按迁移缺口审计中??? P0/P1 资金核对链路推进，补齐旧项目 `WithdrawFlowController` ??? `UnDepositAmountController` 的第???阶段新项目落点???当前阶段先实现后台 Blade 页面、Layui 脚本、列??? API、权限配置???多语言文案、中文???辑注释和测试覆盖；导出、MT4 COMMENT 细分分类、失败重试和复杂财务复核流程保留为后续深层迁移任务???

### 新增和维护文???

- `app/Http/Controllers/Admin/FundFlowController.php`：后台资金流水控制器，负责出金流水和未入金流水列表???方法注释说明了 `user_id`、`ticket`、`local_order_no`、`channel_order_no`、`start_date`、`end_date`、`page`、`limit` 等参数的来源、含义和筛???作用???
- `app/Models/Mt4Trade.php`：MT4 交易模型，对应真实交易表 `mt4_trades`，用于出金流水按余额类交易口径读取???
- `resources/admin/layui/withdraw-flows/index.blade.php`：后台出金流??? Blade 页面，包含筛选区、表格区和页面模块说明注释???
- `resources/admin/layui/undeposit-flows/index.blade.php`：后台未入金流水 Blade 页面，包含待支付入金记录筛???与表格展示???
- `public/js/admin/layui/withdraw-flows/index.js`：出金流??? Layui 页面脚本，负责读取筛选参数???渲染表格???统???多语???文案和刷新动作???
- `public/js/admin/layui/undeposit-flows/index.js`：未入金流水 Layui 页面脚本，负责订单号、???道单号、用??? ID 和时间范围筛选???
- `database/migrations/2026_06_07_000006_add_admin_fund_flow_permissions.php`：资金流水权限迁移，??? `permissions` 表写入页面入口和 API 权限配置???
- `tests/Feature/AdminFundFlowModuleTest.php`：资金流水模块页面???路由???控制器和数据筛选的功能测试???
- `tests/Feature/AdminFundFlowPermissionMigrationTest.php`：资金流水权限迁移测试，验证 `permissions.slug` ??? `permissions.api_route` 配置???
- `tests/Feature/AdminChineseCommentReadabilityTest.php`：扩大中文注释可读???覆盖，防止新增资金流水文件缺少中文逻辑说明???

### 页面与接???

| 类型 | 名称 | 地址或路由名 | 说明 |
| --- | --- | --- | --- |
| Blade 页面 | 出金流水 | `/admin/withdraw-flows` / `admin_page_withdraw_flows` | 用于核对 MT4 余额类出金交易，页面??? Laravel Blade 渲染??? |
| Blade 页面 | 未入金流??? | `/admin/undeposit-flows` / `admin_page_undeposit_flows` | 用于核对待支付或未完成入金记录，页面??? Laravel Blade 渲染??? |
| API | 出金流水列表 | `POST /api/admin/withdrawFlowList` / `admin_api_withdrawFlowList` | 读取 `mt4_trades`，按 `cmd=6`、`open_price=0`、`profit<0` 识别出金流水??? |
| API | 未入金流水列??? | `POST /api/admin/undepositFlowList` / `admin_api_undepositFlowList` | 读取 `deposit_records`，按 `status='01'` 识别未入金记录??? |

### 数据表权限配???

本轮权限仍然坚持“数据表配置驱动鉴权”，页面入口、按???/API 权限均写??? `permissions` 表，后续??? `role_permissions` 分配给不同后台管理员角色???

| permissions.slug | permissions.api_route | 类型 | 用??? |
| --- | --- | ---: | --- |
| `admin_withdraw_flows` | ??? | 1 | 后台出金流水页面/菜单节点??? |
| `admin_withdraw_flow_list` | `admin_api_withdrawFlowList` | 3 | 出金流水列表接口权限??? |
| `admin_undeposit_flows` | ??? | 1 | 后台未入金流水页???/菜单节点??? |
| `admin_undeposit_flow_list` | `admin_api_undepositFlowList` | 3 | 未入金流水列表接口权限??? |

### 数据范围与业务口???

- 出金流水列表使用 `AdminDataScopeService->apply(..., 'trade', 'login')` 追加后台管理员数据可见范围???`login` 参数对应 MT4 登录账号，用于把资金流水限制在当前角色允许查看的客户或代理范围内???
- 未入金流水列表使??? `AdminDataScopeService->apply(..., 'deposit', 'user_id')` 追加数据范围。`user_id` 参数对应业务用户 ID，避免普通财务或客服角色看到未授权客户的入金记录???
- 出金流水当前??? MT4 余额类交易识别：`cmd=6` 表示余额类记录，`open_price=0` 表示非持仓交易，`profit<0` 表示资金流出???
- 未入金流水当前按 `deposit_records.status='01'` 识别，后续如旧项目存在更多未入金状???，???要继续基于真??? DB 状???字典扩展???

### 多语???支持

已补??? Laravel 后端语言包和前端运行时语???包：

- `resources/lang/zh-CN/admin.php`
- `resources/lang/en/admin.php`
- `public/js/common/lang/zh-CN.js`
- `public/js/common/lang/en.js`

新增键包含：`withdraw_flows`、`undeposit_flows`、`withdraw_flows_fetched`、`undeposit_flows_fetched`、`ticket`、`channel_order_no`、`local_order_no`、`profit`、`close_time` 等资金流水相关文案???

### 真实 DB 验证状???

当前本机 `127.0.0.1:3307` 仍拒绝连接，因此本轮不能完成真实 DB 的迁移写入复核和接口真实数据抽样。已确认阻塞不是业务断言失败，???是数据库端口不可达???

```text
Test-NetConnection 127.0.0.1 -Port 3307
结果：TcpTestSucceeded=False
```
数据库恢复后必须继续执行???

```text
php artisan migrate --force
vendor\bin\phpunit tests\Feature\AdminFundFlowPermissionMigrationTest.php
vendor\bin\phpunit tests\Feature\AdminPermissionPlanTest.php --filter front_menu_tree_keeps_parent
```

并用真实 DB 复核???

- `permissions` 中是否存??? `admin_withdraw_flows`、`admin_withdraw_flow_list`、`admin_undeposit_flows`、`admin_undeposit_flow_list`???
- `role_permissions` 是否已按后台管理员角色分配资金流水页面和列表接口权限???
- `POST /api/admin/withdrawFlowList` 是否能按真实 `mt4_trades` 返回出金流水???
- `POST /api/admin/undepositFlowList` 是否能按真实 `deposit_records` 返回未入金流水???

### 本轮已完成边???

- 已完成出金流水和未入金流??? Blade 页面入口???
- 已完成两个列??? API，并接入后台数据范围过滤???
- 宸插畬鎴? `permissions` 数据表驱动的页面??? API 权限配置迁移???
- 已完成后端与前端多语???文案???
- 已完成新增文件的中文逻辑注释覆盖???

### 后续未完成边???

- 未完成出金流水导出???
- 未完成未入金流水导出???
- 未完成旧项目 MT4 COMMENT 分类、人工复核和财务导出格式的深度迁移???
- 未完成真??? DB 迁移写入复核，原因是当前 MySQL `3307` 端口拒绝连接???

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
结果：全??? No syntax errors detected

node --check public\js\admin\layui\withdraw-flows\index.js
node --check public\js\admin\layui\undeposit-flows\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
结果：全部???出码 0

vendor\bin\phpunit tests\Feature\AdminFundFlowModuleTest.php
结果：OK (4 tests, 20 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
结果：OK (2 tests, 663 assertions)

php artisan route:list --path=withdrawFlowList
结果：POST api/admin/withdrawFlowList 已注册，路由??? admin_api_withdrawFlowList，中间件包含 jwt.auth:admin、sso:admin、check.permission:admin

php artisan route:list --path=undepositFlowList
结果：POST api/admin/undepositFlowList 已注册，路由??? admin_api_undepositFlowList，中间件包含 jwt.auth:admin、sso:admin、check.permission:admin

vendor\bin\phpunit tests\Feature\AdminFundFlowPermissionMigrationTest.php
结果：ERROR，数据库连接失败；SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接???

Test-NetConnection 127.0.0.1 -Port 3307
结果：TcpTestSucceeded=False
```

## 17. 2026-06-07 后台权益汇???模块第???阶段

本轮继续按迁移缺口审计中??? P0 财务统计链路推进，补齐旧项目 `RightsSummaryController` 的第???阶段新项目落点???当前阶段先实现权益汇???只读列表???页面顶部汇总卡片???后??? API、权限配置???多语言文案、中文???辑注释和测试覆盖；旧项目中的自???/手动确认出入金???导出等复杂流程保留为后续深层迁移任务，在线结算金额统计已在??? 367 节补齐闭环???

### 新增和维护文???

- `app/Http/Controllers/Admin/RightsSummaryController.php`：后台权益汇总控制器，读??? `mt4_users` 并???过 `user_infos.mt4_code` 映射业务用户???
- `app/Models/Mt4User.php`：MT4 用户资金模型，对应真实表 `mt4_users`???
- `resources/admin/layui/rights-summary/index.blade.php`：后台权益汇??? Blade 页面???
- `public/js/admin/layui/rights-summary/index.js`：权益汇??? Layui 页面脚本???
- `database/migrations/2026_06_07_000007_add_admin_rights_summary_permissions.php`：权益汇总权限迁移???
- `tests/Feature/AdminRightsSummaryModuleTest.php`：权益汇总模块页面???API 路由和权限中间件测试???
- `tests/Feature/AdminRightsSummaryPermissionMigrationTest.php`：权益汇总权限迁移测试???
- `tests/Feature/AdminChineseCommentReadabilityTest.php`：扩大中文注释可读???覆盖???
- `public/css/admin/style.css`：新增权益汇总统计卡片样式???

### 页面与接???

| 类型 | 名称 | 地址或路由名 | 说明 |
| --- | --- | --- | --- |
| Blade 页面 | 权益汇??? | `/admin/rights-summary` / `admin_page_rights_summary` | 查看 MT4 账户余额、净值???保证金、可用保证金和账户数汇??汇?? |
| API | 权益汇???列??? | `POST /api/admin/rightsSummaryList` / `admin_api_rightsSummaryList` | 读取 `mt4_users`，???过 `user_infos.mt4_code` 关联业务用户并应用后台数据范围??? |

### 数据表权限配???

| permissions.slug | permissions.api_route | permissions.route | 类型 | 用??? |
| --- | --- | --- | ---: | --- |
| `admin_rights_summary` | ??? | `/admin/rights-summary` | 1 | 后台权益汇???页???/菜单节点??? |
| `admin_rights_summary_list` | `admin_api_rightsSummaryList` | ??? | 3 | 权益汇???列表接口权限??? |

### 数据范围与业务口???

- 主数据来源为 `mt4_users`，第???阶段使用 `balance`、`equity`、`margin`、`margin_free`、`leverage` 做权益列表和汇??汇??
- 业务用户映射使用 `user_infos.mt4_code = mt4_users.login`，从而把 MT4 账号映射??? `user_infos.user_id`???
- 后台数据范围使用 `AdminDataScopeService->apply(..., 'user', 'user_infos.user_id')`，不同管理员角色只能看到权限范围内的业务用户权益???
- 当前接口返回 `records` ??? `summary`：`records` ??? Laravel paginator，`summary` 为账户数、余额合计???净值合计???保证金合计和可用保证金合计???

### 多语???支持

已补??? Laravel 后端语言包和前端运行时语???包：

- `resources/lang/zh-CN/admin.php`
- `resources/lang/en/admin.php`
- `public/js/common/lang/zh-CN.js`
- `public/js/common/lang/en.js`

新增键包含：`rights_summary`、`rights_summary_fetched`、`total_accounts`、`total_balance`、`total_equity`、`total_margin`、`total_margin_free`、`mt4_login`、`mt4_name`、`mt4_group`、`margin_free`、`min_equity`、`max_equity`???

### 本轮验证命令与结???

```text
php -l app\Http\Controllers\Admin\RightsSummaryController.php
php -l app\Models\Mt4User.php
php -l database\migrations\2026_06_07_000007_add_admin_rights_summary_permissions.php
php -l routes\admin.php
php -l routes\web.php
php -l resources\lang\zh-CN\admin.php
php -l resources\lang\en\admin.php
结果：全??? No syntax errors detected

node --check public\js\admin\layui\rights-summary\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
结果：全部???出码 0

vendor\bin\phpunit tests\Feature\AdminRightsSummaryModuleTest.php
结果：OK (3 tests, 11 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
结果：OK (2 tests, 782 assertions)

php artisan route:list --path=rights-summary
结果：GET admin/rights-summary 已注册，路由??? admin_page_rights_summary

php artisan route:list --path=rightsSummaryList
结果：POST api/admin/rightsSummaryList 已注册，路由??? admin_api_rightsSummaryList，中间件包含 jwt.auth:admin、sso:admin、check.permission:admin

vendor\bin\phpunit tests\Feature\AdminRightsSummaryPermissionMigrationTest.php
结果：ERROR，数据库连接失败；SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接???

Test-NetConnection 127.0.0.1 -Port 3307
结果：TcpTestSucceeded=False
```

### 后续未完成边???

- 未完成旧项目 `ConfirmWithdrawOrdeposit`、`ManualConfirmWithdrawOrdeposit` 自动/手动确认出入金???辑???
- 未完??? `rightsSumExport` 导出格式迁移???
- 在线结算金额统计已在??? 367 节补齐当前筛选范围的只读汇???闭环；剩余边界只保留自动确认出入金与真??? MT4 自动同步???
- 未完成真??? DB 权限写入复核，原因是当前 MySQL `3307` 端口拒绝连接???

## 18. 2026-06-07 ???终清单结构修复与权益汇???复???

本轮只调整文档结构和复核验证结果，不改动权益汇???业务代码???此??? `## 17. 2026-06-07 后台权益汇???模块第???阶段` 被插入到??? 16 节资金流水模块中间，导致??? 16 节???数据库恢复后必须继续执行???和验证结果被拆???。现在已把第 17 节完整移动到??? 16 节之后，保证???终清单按模块顺序阅读和交接???

### 本轮文档修复
- `docs/admin-backend-blade-permission-final-checklist.md`：调整章节顺序，确保??? 16 节后台资金流水模块完整闭合，??? 17 节后台权益汇总模块位于第 16 节之后的独立位置???
- 本次调整不改变接口???控制器、模型???迁移???Blade 页面、JS 脚本和语???包，仅修复最终交付清单的结构???

### 本轮复验命令与结???
```text
php -l app\Http\Controllers\Admin\RightsSummaryController.php
php -l app\Models\Mt4User.php
php -l database\migrations\2026_06_07_000007_add_admin_rights_summary_permissions.php
php -l routes\admin.php
php -l routes\web.php
php -l resources\lang\zh-CN\admin.php
php -l resources\lang\en\admin.php
结果：全??? No syntax errors detected

node --check public\js\admin\layui\rights-summary\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
结果：全部???出码 0

vendor\bin\phpunit tests\Feature\AdminRightsSummaryModuleTest.php
结果：OK (3 tests, 11 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
结果：OK (2 tests, 782 assertions)

php artisan route:list --path=rights-summary
结果：GET admin/rights-summary 已注册，路由??? admin_page_rights_summary

php artisan route:list --path=rightsSummaryList
结果：POST api/admin/rightsSummaryList 已注册，路由??? admin_api_rightsSummaryList，中间件包含 jwt.auth:admin、sso:admin、check.permission:admin

Test-NetConnection 127.0.0.1 -Port 3307
结果：TcpTestSucceeded=False

vendor\bin\phpunit tests\Feature\AdminRightsSummaryPermissionMigrationTest.php
结果：ERROR，SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接???
```

### 数据库恢复后必须继续执行
```text
php artisan migrate --force
vendor\bin\phpunit tests\Feature\AdminRightsSummaryPermissionMigrationTest.php
vendor\bin\phpunit tests\Feature\AdminFundFlowPermissionMigrationTest.php
vendor\bin\phpunit tests\Feature\AdminPermissionPlanTest.php --filter front_menu_tree_keeps_parent
```

并用真实 DB 复核???
- `permissions` 中是否存??? `admin_rights_summary`、`admin_rights_summary_list`、`admin_withdraw_flows`、`admin_withdraw_flow_list`、`admin_undeposit_flows`、`admin_undeposit_flow_list`???
- `role_permissions` 是否已按后台管理员角色分配权益汇总???出金流水???未入金流水页面和接口权限???
- `POST /api/admin/rightsSummaryList` 是否能按真实 `mt4_users`、`user_infos.mt4_code` 和后台数据范围返回权益汇总数据???

## 19. 2026-06-07 后台交易 MT4 持仓/平仓第一阶段

本轮继续按迁移缺口审计中??? P0“持???/平仓/持仓汇??????推进???旧项目 `AdminOpenOrderController`、`AdminCloseOrderController` 的核心数据源??? MT4_TRADES；新项目此前 `TradeController` 仍读??? `user_trades`，只能覆盖基???订单列表，不能证明后台交易页面已经按当前真实 `mt4_trades` 表迁移???本轮先完成第一阶段：交易类订单列表、持仓列表???平仓列表???筛选???分页和汇???卡片???

### 本轮新增和维护文???
- `app/Http/Controllers/Admin/TradeController.php`：重写为可读 UTF-8 中文注释，后台交易列表统???读取 `Mt4Trade`；参数注释说??? `user_id`、`ticket`、`symbol`、`start_date`、`end_date`、`page`、`per_page`、`limit` 的来源???含义和作用???
- `resources/admin/layui/trades/index.blade.php`：新增订单数、???手数??????盈亏???库存费合计、手续费合计汇???卡片，继续使用 Laravel Blade 渲染???
- `public/js/admin/layui/trades/index.js`：重写为 UTF-8，新??? `records + summary` 解析、汇总卡片更新???辑和字段级中文逻辑注释???
- `resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`：补充后台交易汇总卡片多语言键???
- `public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`：补充前端运行时交易汇???多语言键???
- `tests/Feature/AdminTradeMt4PositionModuleTest.php`：新增后??? MT4 持仓/平仓第一阶段契约测试。由于当??? MySQL 3307 不可达且 PHP 未启??? SQLite PDO，本测试采用源码契约验证，不访问外部 DB???

### 当前接口契约
| 接口 | 路由??? | 鏁版嵁婧? | 第一阶段口径 |
| --- | --- | --- | --- |
| `POST /api/admin/tradeList` | `admin_api_tradeList` | `mt4_trades` | `cmd in (0..5)` 的全部交易类订单，按 `open_time` 范围筛?????? |
| `POST /api/admin/openPositions` | `admin_api_openPositions` | `mt4_trades` | `cmd in (0..5)` ??? `close_time is null or close_time = 0` 表示当前持仓??? |
| `POST /api/admin/closedPositions` | `admin_api_closedPositions` | `mt4_trades` | `cmd in (0..5)` ??? `close_time > 0` 表示历史平仓??? |
| `POST /api/admin/tradeSummary` | `admin_api_tradeSummary` | `mt4_trades` | 当前持仓??? `symbol` 分组统计 `total_volume` ??? `count`??? |

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

字段含义???
- `records`：Laravel paginator，用??? Layui 表格分页渲染???
- `summary.total_orders`：当前筛选条件下的订单数量???
- `summary.total_volume`：当前筛选条件下的交易手???/成交量合计，第一阶段直接沿用当前真实??? `volume` 鏁板?????
- `summary.total_profit`：当前筛选条件下的盈亏合计???
- `summary.total_swaps`：当前筛选条件下的库存费合计???
- `summary.total_commission`：当前筛选条件下的手续费合计???

### 本轮验证命令与结???
```text
vendor\bin\phpunit tests\Feature\AdminTradeMt4PositionModuleTest.php
结果：OK (3 tests, 17 assertions)

php -l app\Http\Controllers\Admin\TradeController.php
php -l resources\lang\zh-CN\admin.php
php -l resources\lang\en\admin.php
结果：全??? No syntax errors detected

node --check public\js\admin\layui\trades\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
结果：全部???出码 0

vendor\bin\phpunit tests\Feature\AdminSecondBatchModuleCoverageTest.php
结果：OK (34 tests, 80 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
结果：OK (2 tests, 782 assertions)

php artisan route:list --path=openPositions
结果：POST api/admin/openPositions 已注册，路由??? admin_api_openPositions，中间件包含 jwt.auth:admin、sso:admin、check.permission:admin

php artisan route:list --path=closedPositions
结果：POST api/admin/closedPositions 已注册，路由??? admin_api_closedPositions，中间件包含 jwt.auth:admin、sso:admin、check.permission:admin

php artisan route:list --path=tradeList
结果：POST api/admin/tradeList 已注册，路由??? admin_api_tradeList，中间件包含 jwt.auth:admin、sso:admin、check.permission:admin
```

### 后续未完成边???
- 旧项目基??? `MARGIN_RATE` 的实???/测试盘区分和特殊 MT4 口径仍需继续迁移；`COMMENT` ??? `MODIFY_TIME` 已在后续??? 364 节补齐历史平仓强平筛选和展示???
- 后台交易列表数据范围已在??? 20 节接??? `AdminDataScopeService`，当前保留此条作为历史边界关闭记录???
- 真实 DB 查询验证已在后续交易模块测试中???过受控 `mt4_trades` 夹具覆盖，当前剩余的是旧项目更深层导出和 `orderType` 口径???

## 20. 2026-06-07 后台交易数据范围补齐

本轮补齐??? 19 节留下的???个明确边界：后台交易列表已经读取 `mt4_trades`，但上一轮尚未接??? `AdminDataScopeService`。现??? `tradeList`、`openPositions`、`closedPositions`、`tradeSummary` 都会在存??? admin 登录用户时，??? `targetType=trade` ??? `userIdColumn=login` 追加数据范围过滤，避免普通后台管理员看到超出角色/代理绑定范围??? MT4 交易记录???

### 本轮维护文件
- `app/Http/Controllers/Admin/TradeController.php`：注??? `AdminDataScopeService`，新??? `applyDataScope()` 方法；方法注释明??? `targetType=trade` ??? `login` 字段的业务含义???
- `tests/Feature/AdminTradeMt4PositionModuleTest.php`：补充交易控制器必须注入和调??? `AdminDataScopeService` 的契约断??????
- `docs/admin-backend-blade-permission-final-checklist.md`：记录本轮补齐的数据范围边界和验证结果???

### 数据范围口径
```text
AdminDataScopeService->apply($query, $admin, 'trade', 'login')
```

参数含义???
- `$query`：当??? `mt4_trades` 查询对象，调用前已经追加全部交易、持仓或平仓基础条件???
- `$admin`：当??? admin guard 下的后台管理员???
- `trade`：数据范围目标类型，表示当前业务对象是交易订单???
- `login`：`mt4_trades.login`，对应业务用??? ID/MT4 登录账号，用于把交易记录限制在当前管理员角色允许查看的用户集合内???

### 本轮验证命令与结???
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
结果：POST api/admin/tradeSummary 已注册，路由??? admin_api_tradeSummary，中间件包含 jwt.auth:admin、sso:admin、check.permission:admin

vendor\bin\phpunit tests\Feature\AdminDataScopeControllerWiringTest.php
结果：ERROR，测试启??? DatabaseTransactions 时连??? MySQL 失败：SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接???
```

### 数据库恢复后必须继续执行
```text
vendor\bin\phpunit tests\Feature\AdminDataScopeControllerWiringTest.php
vendor\bin\phpunit tests\Feature\AdminDataScopeServiceTest.php
```

并用真实 DB 复核???
- 不同后台管理员角色访??? `POST /api/admin/tradeList` 时，是否只能看到 `role_data_scopes` ??? `admin_agent_bindings` 配置范围内的 `mt4_trades.login`???
- `POST /api/admin/openPositions` ??? `POST /api/admin/closedPositions` 的汇总卡片是否与数据范围过滤后的列表???鑷淬??
- 超级管理员是否仍可查看全??? `mt4_trades` 交易记录???

## 21. 2026-06-07 后台汇率配置模块第一阶段

本轮继续按后??? Blade 页面、权限表配置、多语言和中文???辑注释要求推进，补齐旧项目汇率配置的第???阶段新项目落点???旧项目中入金汇率和出金汇率属于后台运营配置，本阶段在新项目中统???落到 `system_configs` ??? key/value 模式，不再新增业务表，也不把汇率写死在前端或控制器常量之外的散落位置???

### 本轮新增和维护文???

- `app/Http/Controllers/Admin/ExchangeRateController.php`：后台汇率配置控制器，维??? `sys_deposit_rate` ??? `sys_draw_rate` 两个配置 key，并使用中文注释说明参数含义、保存边界和 system_configs 数据来源???
- `resources/admin/layui/exchange-rates/index.blade.php`：后台汇率配??? Blade 页面，只渲染入金汇率、出金汇率和保存按钮，页面数据由后台 API 读取???
- `public/js/admin/layui/exchange-rates/index.js`：汇率配??? Layui 页面脚本，负责读取接口回填表单???提交保存和刷新按钮权限???
- `database/migrations/2026_06_07_000008_add_admin_exchange_rate_permissions.php`：写入汇率配置页面权限???查看接口权限和更新接口权限???
- `tests/Feature/AdminExchangeRateModuleTest.php`：汇率配置模块契约测试，覆盖页面路由、Blade 控件、API 权限中间件???控制器 key/value 保存契约和权限迁移声明???
- `tests/Feature/AdminChineseCommentReadabilityTest.php`：把本轮新增控制器???Blade、JS、迁移和测试纳入中文注释可读性检查???
- `routes/admin.php`：新增两个受保护 API 路由???
- `routes/web.php`：新??? `/admin/exchange-rates` Blade 页面路由???
- `resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`：新增后端页面标题???接口消息和字段文案???
- `resources/lang/zh-CN/menus.php`、`resources/lang/en/menus.php`：新增菜单标题翻译???
- `public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`：新增前端运行时语言 key???

### 页面与接口清???

| 类型 | 地址或路由名 | 说明 |
| --- | --- | --- |
| Blade 页面 | `GET /admin/exchange-rates` / `admin_page_exchange_rates` | 汇率配置页面，使??? Laravel Blade + Layui 表单渲染??? |
| API | `POST /api/admin/exchangeRateInfo` / `admin_api_exchangeRateInfo` | 读取 `system_configs` 中的 `sys_deposit_rate` ??? `sys_draw_rate`??? |
| API | `POST /api/admin/updateExchangeRate` / `admin_api_updateExchangeRate` | 保存入金汇率和出金汇率，使用 `SystemConfig::updateOrCreate` 写入配置表??? |

### 参数和数据来???

| 参数 | 鏁版嵁琛? key | 逻辑含义 |
| --- | --- | --- |
| `sys_deposit_rate` | `system_configs.key=sys_deposit_rate` | 入金换算汇率，要求为大于 0 的数字??? |
| `sys_draw_rate` | `system_configs.key=sys_draw_rate` | 出金/取款换算汇率，要求为大于 0 的数字??? |
| `group` | `exchange_rate` | 系统配置分组，用于在系统配置列表中归类显示汇率配置??? |

### 权限配置

| permissions.slug | permissions.type | route/api_route | 功能 |
| --- | ---: | --- | --- |
| `admin_exchange_rates` | 1 | `/admin/exchange-rates` | 汇率配置页面/菜单入口??? |
| `admin_exchange_rate_info` | 3 | `admin_api_exchangeRateInfo` | 查看汇率配置接口权限??? |
| `admin_exchange_rate_update` | 3 | `admin_api_updateExchangeRate` | 保存汇率配置按钮与接口权限??? |

### 本轮验证命令与结???

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
结果：全??? No syntax errors detected

node --check public\js\admin\layui\exchange-rates\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
结果：全部???出码 0

php artisan route:list --path=exchange-rates
结果：GET admin/exchange-rates 已注册，路由??? admin_page_exchange_rates

php artisan route:list --path=exchangeRate
结果：POST api/admin/exchangeRateInfo 已注册，路由??? admin_api_exchangeRateInfo，中间件包含 jwt.auth:admin、sso:admin、CheckPermission:admin

php artisan route:list --path=updateExchangeRate
结果：POST api/admin/updateExchangeRate 已注册，路由??? admin_api_updateExchangeRate，中间件包含 jwt.auth:admin、sso:admin、CheckPermission:admin

Test-NetConnection 127.0.0.1 -Port 3307
结果：TcpTestSucceeded=False，真??? DB 迁移落库与数据复核暂不可执行
```

### 数据库恢复后必须继续执行

```text
php artisan migrate --force
```

并用真实 DB 复核???

- `permissions` 中是否存??? `admin_exchange_rates`、`admin_exchange_rate_info`、`admin_exchange_rate_update`???
- `role_permissions` 是否已按后台管理员角色分配汇率配置页面???查看接口和保存接口权限???
- `system_configs` 中是否能正确写入或更??? `sys_deposit_rate`、`sys_draw_rate`，且 `group` ??? `exchange_rate`???
## 22. 2026-06-07 后台在线用户模块第一阶段

本轮继续按旧项目后台迁移缺口推进，补齐旧项目 `UserLoginOnlineController` 在新项目中的第一阶段落点。当前实现以新项目真实表结构为准，读??? `user_onlines`，并通过 `user_infos` 关联补充业务用户名称和账号类型；页面继续使用 Laravel Blade + Layui + JS 渲染，接口鉴权继续来??? `permissions.api_route` 鏁版嵁琛ㄩ厤缃??

### 本轮新增和维护文???
- `app/Models/UserOnline.php`：新增在线用户模型，对应真实??? `user_onlines`；该表没??? `deleted_at` 字段，因此模型不继承带软删除逻辑??? `BaseModel`???
- `app/Http/Controllers/Admin/OnlineUserController.php`：新增后台在线用户控制器，提供只读列表接??? `onlineUserList`，筛选参数包??? `user_id`、`ip_address`、`start_date`、`end_date`、`page`、`per_page`、`limit`???
- `resources/admin/layui/online-users/index.blade.php`：新增后台在线用??? Blade 页面，包??? `onlineUserSearchForm`、`onlineUserTable` 和日期筛选控件???
- `public/js/admin/layui/online-users/index.js`：新??? Layui 页面脚本，负责渲染表格???提交筛选条件???格式化账号类型和最后活跃时间???
- `database/migrations/2026_06_07_000009_add_admin_online_user_permissions.php`：新增页面权??? `admin_online_users` ??? API 权限 `admin_online_user_list`???
- `routes/web.php`：新??? `GET /admin/online-users`，放在后??? Layui 兜底路由之前，避免被 `/admin/{path?}` 转到 Naive 页面???
- `routes/admin.php`：新??? `POST /api/admin/onlineUserList`，继承后??? JWT、SSO、权限中间件???
- `resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`、`public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`：补齐在线用户模块中英文文案???
- `tests/Feature/AdminOnlineUserModuleTest.php`：新增在线用户模块契约测试，覆盖页面路由、Blade 控件、API 权限中间件???真实表读取和权限迁移声明???
- `tests/Feature/AdminChineseCommentReadabilityTest.php`：纳入本轮新增文件，并增加连续问号占位检查，避免中文注释或中文文案被系统编码吞掉???

### 页面与接口清???
| 类型 | 地址或路由名 | 说明 |
| --- | --- | --- |
| Blade 页面 | `GET /admin/online-users` / `admin_page_online_users` | 在线用户页面，使??? Laravel Blade + Layui 表格渲染??? |
| API | `POST /api/admin/onlineUserList` / `admin_api_onlineUserList` | 读取 `user_onlines` ???近活跃记录，并关??? `user_infos` 返回用户名称和账号类型??? |

### 参数和数据来???
| 参数 | 鏁版嵁琛ㄥ瓧娈? | 逻辑含义 |
| --- | --- | --- |
| `user_id` | `user_onlines.user_id` | 前台业务用户 ID，用于定位某个代理商或普通客户的在线记录??? |
| `ip_address` | `user_onlines.ip_address` | 登录或活??? IP，后端按 `LIKE` 模糊筛?????? |
| `start_date` | `user_onlines.last_activity` | ???后活跃开始日期，后端转换为当??? 00:00:00 秒级时间戳??? |
| `end_date` | `user_onlines.last_activity` | ???后活跃结束日期，后端转换为当??? 23:59:59 秒级时间戳??? |
| `page` | Laravel paginator | 当前页码，兼??? Layui 表格分页??? |
| `per_page` / `limit` | Laravel paginator | 每页条数，`limit` 兼容 Layui 默认参数名??? |

### 权限配置
| permissions.slug | permissions.type | route/api_route | 功能 |
| --- | ---: | --- | --- |
| `admin_online_users` | 1 | `/admin/online-users` | 在线用户页面/菜单入口??? |
| `admin_online_user_list` | 3 | `admin_api_onlineUserList` | 在线用户列表接口权限??? |

### 本轮验证命令与结???
```text
php -l app\Http\Controllers\Admin\OnlineUserController.php
php -l app\Models\UserOnline.php
php -l database\migrations\2026_06_07_000009_add_admin_online_user_permissions.php
php -l routes\admin.php
php -l routes\web.php
php -l resources\lang\zh-CN\admin.php
php -l resources\lang\en\admin.php
结果：全??? No syntax errors detected

node --check public\js\admin\layui\online-users\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
结果：全部???出码 0

php artisan route:list --path=online-users
结果：GET admin/online-users 已注册，路由??? admin_page_online_users

php artisan route:list --path=onlineUserList
结果：POST api/admin/onlineUserList 已注册，路由??? admin_api_onlineUserList，中间件包含 jwt.auth:admin、sso:admin、CheckPermission:admin

vendor\bin\phpunit tests\Feature\AdminOnlineUserModuleTest.php
结果：OK (5 tests, 20 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
结果：OK (3 tests, 977 assertions)

Test-NetConnection 127.0.0.1 -Port 3307
结果：TcpTestSucceeded=False，真??? DB 迁移落库与真实在线用户样本复核暂不可执行
```

### 数据库恢复后必须继续执行
```text
php artisan migrate --force
```

并用真实 DB 复核???
- `permissions` 中是否存??? `admin_online_users`、`admin_online_user_list`???
- 目标后台角色是否通过 `role_permissions` 授权了在线用户页面和列表接口???
- `POST /api/admin/onlineUserList` 是否能按真实 `user_onlines` 数据返回列表???
- `user_infos.account_type=1` 的代理商??? `account_type=2` 的普通客户在页面中是否能正确显示账号类型???

## 23. 2026-06-07 后台产品/交易品种模块第一阶段

本轮继续按迁移缺口审计中??? P1“产???/交易品种管理”推进，补齐旧项??? `AdminProductionController` 在新项目中的第一阶段落点。旧控制器主要基??? `symbol_prices` ??? `MT4_TRADES` 统计交易品种当前持仓；新项目当前阶段以真实表 `symbol_prices` ??? `mt4_trades` 为准，先实现只读列表、当前持仓汇总???后??? Blade 页面、权限表配置、多语言文案、中文???辑注释和测试覆盖???

### 本轮新增和维护文???
- `app/Http/Controllers/Admin/ProductionController.php`：新增后台产???/交易品种控制器，读取 `SymbolPrice::query()`，???过 `leftJoin('mt4_trades')` 汇???买入手数???卖出手数???净持仓和浮动盈亏???
- `resources/admin/layui/productions/index.blade.php`：新增后台产???/交易品种 Blade 页面，包??? `productionSearchForm`、`productionTable` 和顶部汇总卡片???
- `public/js/admin/layui/productions/index.js`：新??? Layui 页面脚本，负责调??? `admin_api_productionList`、渲染表格???刷新汇总卡片和提交筛???条件???
- `database/migrations/2026_06_07_000010_add_admin_production_permissions.php`：新增页面权??? `admin_productions` ??? API 权限 `admin_production_list`???
- `routes/web.php`：新??? `GET /admin/productions`，放在后??? Layui 兜底路由之前，避免被 `/admin/{path?}` 转到 Naive 页面???
- `routes/admin.php`：新??? `POST /api/admin/productionList`，继承后??? JWT、SSO、权限中间件???
- `resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`、`resources/lang/zh-CN/menus.php`、`resources/lang/en/menus.php`、`public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`：补齐产???/交易品种模块中英文文案???
- `tests/Feature/AdminProductionModuleTest.php`：新增产???/交易品种模块契约测试，覆盖页面路由???Blade 控件、API 权限中间件???真实表读取和权限迁移声明???
- `tests/Feature/AdminChineseCommentReadabilityTest.php`：纳入本轮新增产品模块文件，继续???查中文???辑注释和编码占位???

### 页面与接口清???
| 类型 | 地址或路由名 | 说明 |
| --- | --- | --- |
| Blade 页面 | `GET /admin/productions` / `admin_page_productions` | 产品/交易品种页面，使??? Laravel Blade + Layui 表格渲染??? |
| API | `POST /api/admin/productionList` / `admin_api_productionList` | 读取 `symbol_prices`，并汇??? `mt4_trades` 当前未平仓买卖方向数据??? |

### 参数和数据来???
| 参数 | 鏁版嵁琛ㄥ瓧娈? | 逻辑含义 |
| --- | --- | --- |
| `symbol` | `symbol_prices.symbol` | 交易品种编码，例??? XAUUSD，后端按 `LIKE` 模糊筛?????? |
| `group_id` | `symbol_prices.group_id` | 品种分组 ID，用于按贵金属???能源???外汇等业务类别筛?????? |
| `status` | `symbol_prices.status` | 品种状???，1 表示启用???0 表示停用??? |
| `page` | Laravel paginator | 当前页码，兼??? Layui 表格分页??? |
| `per_page` / `limit` | Laravel paginator | 每页条数，`limit` 兼容 Layui 默认参数名??? |
| `total_buy_volume` | `mt4_trades.volume where cmd=0` | 当前未平仓买入方向???手数??? |
| `total_sell_volume` | `mt4_trades.volume where cmd=1` | 当前未平仓卖出方向???手数??? |
| `net_volume` | 买入总手??? - 卖出总手??? | 延续旧项目产品净持仓展示口径??? |
| `float_profit_loss` | `mt4_trades.profit` | 当前未平仓订单浮动盈亏合计??? |

### 权限配置
| permissions.slug | permissions.type | route/api_route | 功能 |
| --- | ---: | --- | --- |
| `admin_productions` | 1 | `/admin/productions` | 产品/交易品种页面/菜单入口??? |
| `admin_production_list` | 3 | `admin_api_productionList` | 产品/交易品种列表接口权限??? |

### 本轮验证命令与结???
```text
php -l app\Http\Controllers\Admin\ProductionController.php
php -l database\migrations\2026_06_07_000010_add_admin_production_permissions.php
php -l routes\admin.php
php -l routes\web.php
php -l resources\lang\zh-CN\admin.php
php -l resources\lang\en\admin.php
结果：全??? No syntax errors detected

node --check public\js\admin\layui\productions\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
结果：全部???出码 0

php artisan route:list --path=productions
结果：GET admin/productions 已注册，路由??? admin_page_productions

php artisan route:list --path=productionList
结果：POST api/admin/productionList 已注册，路由??? admin_api_productionList，中间件包含 jwt.auth:admin、sso:admin、CheckPermission:admin

vendor\bin\phpunit tests\Feature\AdminProductionModuleTest.php
结果：OK (5 tests, 21 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
结果：OK (3 tests, 1062 assertions)

Test-NetConnection 127.0.0.1 -Port 3307
结果：TcpTestSucceeded=False，真??? DB 迁移落库与真实产???/交易品种样本复核暂不可执???
```

### 数据库恢复后必须继续执行
```text
php artisan migrate --force
```

并用真实 DB 复核???
- `permissions` 中是否存??? `admin_productions`、`admin_production_list`???
- 目标后台角色是否通过 `role_permissions` 授权了产???/交易品种页面和列表接口???
- `POST /api/admin/productionList` 是否能按真实 `symbol_prices` ??? `mt4_trades` 数据返回列表???
- 当前未平仓交易中 `cmd=0`、`cmd=1` 的手数???净持仓和浮动盈亏是否与旧项??? `AdminProductionController` 统计口径???鑷淬??

## 24. 2026-06-07 后台礼品发放/发货模块第一阶段

本轮继续按迁移缺口审计中??? P1“礼品发放与发货”推进，补齐旧项??? `GiftController` 在新项目中的第一阶段落点。旧控制器包含礼品发放???发货列表???用户地???列表和导出；新项目当前阶段以真实??? `gift_shipments`、`user_addresses`、`user_infos` 为准，先实现后台 Blade 页面、发货列??? API、可发放地址 API、发放写??? API、权限表配置、多语言文案、中文???辑注释和测试覆盖???当时导出能力只先声明权限；当前状???已在第 160 节校准为已实??? `admin_api_exportGiftShipments` 当前筛??? CSV 导出???

### 本轮新增和维护文???
- `app/Http/Controllers/Admin/GiftController.php`：新增后台礼品控制器，提??? `shipmentList`、`addressList`、`sendGift` 三个方法；发放动作使??? `DB::transaction` 批量写入 `gift_shipments`???
- `resources/admin/layui/gifts/index.blade.php`：新增后台礼??? Blade 页面，包??? `giftShipmentSearchForm`、`giftShipmentTable`、`giftAddressTable`、`sendGiftForm`???
- `public/js/admin/layui/gifts/index.js`：新??? Layui 页面脚本，负责发货列表???地???列表、勾选地???、组??? `recipients` 参数和提交发放???
- `database/migrations/2026_06_07_000011_add_admin_gift_permissions.php`：新增页面权限???发货列表权限???地???列表权限、发放权限和导出权限???
- `routes/web.php`：新??? `GET /admin/gifts`，放在后??? Layui 兜底路由之前???
- `routes/admin.php`：新??? `POST /api/admin/giftShipmentList`、`POST /api/admin/giftAddressList`、`POST /api/admin/sendGift`，全部继承后??? JWT、SSO、权限中间件???
- `resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`、`resources/lang/zh-CN/menus.php`、`resources/lang/en/menus.php`、`public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`：补齐礼品模块中英文文案???
- `tests/Feature/AdminGiftModuleTest.php`：新增礼品模块契约测试，覆盖页面路由、Blade 控件、API 权限中间件???真实表读取、事务写入和权限迁移声明???
- `tests/Feature/AdminChineseCommentReadabilityTest.php`：纳入本轮新增礼品模块文件，继续???查中文???辑注释和编码占位???

### 页面与接口清???
| 类型 | 地址或路由名 | 说明 |
| --- | --- | --- |
| Blade 页面 | `GET /admin/gifts` / `admin_page_gifts` | 礼品发放/发货页面，使??? Laravel Blade + Layui 表格和弹窗渲染??? |
| API | `POST /api/admin/giftShipmentList` / `admin_api_giftShipmentList` | 读取 `gift_shipments` 发货记录，并关联 `admins.username` 显示后台操作人??? |
| API | `POST /api/admin/giftAddressList` / `admin_api_giftAddressList` | 读取 `user_addresses`，并关联 `user_infos` 限制 `is_gift_allowed=1` 的可发放用户??? |
| API | `POST /api/admin/sendGift` / `admin_api_sendGift` | 根据勾???地???批量写入 `gift_shipments` 发货记录??? |

### 参数和数据来???
| 参数 | 鏁版嵁琛ㄥ瓧娈? | 逻辑含义 |
| --- | --- | --- |
| `user_id` | `gift_shipments.user_id` / `user_addresses.user_id` | 业务用户 ID，用于筛选发货记录或可发放地?????? |
| `gift_name` | `gift_shipments.gift_name` | 礼品名称，发货列表按 `LIKE` 筛???，发放时写入发货记录??? |
| `recipient_name` | `gift_shipments.recipient_name` / `user_addresses.recipient_name` | 收件人姓名，用于发货记录展示和地???筛?????? |
| `recipient_phone` | `user_addresses.recipient_phone` | 收件人联系电话，用于地址筛???和发放写入??? |
| `recipient_address` | `user_addresses.recipient_address` | 收件地址，发放时写入 `gift_shipments.recipient_address`??? |
| `sender_name` | `gift_shipments.sender_name` | 发件人名称，由后台发放表单提交??? |
| `gift_quantity` | `gift_shipments.gift_quantity` | 礼品数量，必须大于等??? 1??? |
| `tracking_number` | `gift_shipments.tracking_number` | 物流单号；为空时状???为待处理，有单号时状???为已发货??? |
| `recipients` | 多个地址行组成的数组 | JS 根据 `giftAddressTable` 勾???项生成，后端???条写入发货记录??? |

### 权限配置
| permissions.slug | permissions.type | route/api_route | 功能 |
| --- | ---: | --- | --- |
| `admin_gifts` | 1 | `/admin/gifts` | 礼品后台页面/菜单入口??? |
| `admin_gift_shipments` | 3 | `admin_api_giftShipmentList` | 礼品发货列表接口权限??? |
| `admin_gift_addresses` | 3 | `admin_api_giftAddressList` | 可发放地???列表接口权限??? |
| `admin_gift_send` | 3 | `admin_api_sendGift` | 发放礼品按钮与接口权限??? |
| `admin_gift_export` | 3 | `admin_api_exportGiftShipments` | 当前筛???礼品发??? CSV 导出接口权限??? |

### 本轮验证命令与结???
```text
php -l app\Http\Controllers\Admin\GiftController.php
php -l database\migrations\2026_06_07_000011_add_admin_gift_permissions.php
php -l routes\admin.php
php -l routes\web.php
php -l resources\lang\zh-CN\admin.php
php -l resources\lang\en\admin.php
结果：全??? No syntax errors detected

node --check public\js\admin\layui\gifts\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
结果：全部???出码 0

php artisan route:list --path=gifts
结果：GET admin/gifts 已注册，路由??? admin_page_gifts

php artisan route:list --path=giftShipmentList
结果：POST api/admin/giftShipmentList 已注册，路由??? admin_api_giftShipmentList，中间件包含 jwt.auth:admin、sso:admin、CheckPermission:admin

php artisan route:list --path=giftAddressList
结果：POST api/admin/giftAddressList 已注册，路由??? admin_api_giftAddressList，中间件包含 jwt.auth:admin、sso:admin、CheckPermission:admin

php artisan route:list --path=sendGift
结果：POST api/admin/sendGift 已注册，路由??? admin_api_sendGift，中间件包含 jwt.auth:admin、sso:admin、CheckPermission:admin

vendor\bin\phpunit tests\Feature\AdminGiftModuleTest.php
结果：OK (5 tests, 30 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
结果：OK (3 tests, 1147 assertions)

Test-NetConnection 127.0.0.1 -Port 3307
结果：TcpTestSucceeded=False，真??? DB 迁移落库与真实礼品样本复核暂不可执行
```

### 数据库恢复后必须继续执行
```text
php artisan migrate --force
```

并用真实 DB 复核???
- `permissions` 中是否存??? `admin_gifts`、`admin_gift_shipments`、`admin_gift_addresses`、`admin_gift_send`、`admin_gift_export`???
- 目标后台角色是否通过 `role_permissions` 授权了礼品页面???发货列表???地???列表和发放接口???
- `POST /api/admin/giftAddressList` 是否只返??? `user_infos.is_gift_allowed=1` 且存在收货地???的用户???
- `POST /api/admin/sendGift` 是否能按真实地址批量写入 `gift_shipments`，并正确记录 `admin_id`、`gift_quantity`、`tracking_number`、`shipped_at`???
- 旧项??? `shipment_list_export` 对应的新项目当前筛??? CSV 导出已由 `admin_api_exportGiftShipments` 承接；兑换扣库存、真实兑换规则和积分消???联动仍???继续迁移???

## 25. 2026-06-07 后台实名认证审核模块第一阶段

本轮继续按旧项目后台迁移缺口推进，补齐旧项目 `AuthenticationController` 在新项目中的第一阶段落点。旧项目认证审核包含待审列表、已审列表???认证详情???身份证审核、银行卡审核和拒绝原因记录；新项目当前先以真实表 `user_auths` ??? `user_infos` 为准，完成后??? Blade 页面、待审列??? API、已审列??? API、复用审核动作???权限表配置、多语言文案、中文???辑注释和契约测试???

### 本轮新增和维护文???
- `app/Http/Controllers/Admin/AuthenticationController.php`：新增实名认证审核控制器，读??? `UserAuth::query()`，关??? `user_infos`，并通过 `AdminDataScopeService` 套用后台数据范围???
- `app/Http/Controllers/Admin/AdminUserController.php`：修??? `reviewAuth` 的真实落库字段，避免继续写不存在或不符合当前表结构的 `status/memo` 字段；当前按 `id_card_status`、`bank_status`、`id_card_remarks`、`bank_remarks` ??? `user_infos.auth_status` 写入???
- `resources/admin/layui/authentications/index.blade.php`：新增后台认证审??? Blade 页面，包含待审列表???已审列表和审核弹窗???
- `public/js/admin/layui/authentications/index.js`：新??? Layui 页面脚本，负责渲染待???/已审表格、筛选参数???审核弹窗和提交 `reviewAuth`???
- `database/migrations/2026_06_07_000012_add_admin_authentication_permissions.php`：新增页面???待审列表???已审列表和审核动作权限字典???
- `routes/web.php`：新??? `GET /admin/authentications`，放在后??? Layui 兜底路由之前???
- `routes/admin.php`：新??? `POST /api/admin/authPendingList` ??? `POST /api/admin/authCertifiedList`，继续挂载后??? JWT、SSO、权限中间件???
- `resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`、`resources/lang/zh-CN/menus.php`、`resources/lang/en/menus.php`、`public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`：补齐认证审核模块中英文文案???
- `tests/Feature/AdminAuthenticationModuleTest.php`：新增认证审核模块契约测试???
- `tests/Feature/AdminChineseCommentReadabilityTest.php`：纳入本轮认证审核新增文件，继续???查中文注释和编码占位???

### 页面与接口清???
| 类型 | 地址或路由名 | 说明 |
| --- | --- | --- |
| Blade 页面 | `GET /admin/authentications` / `admin_page_authentications` | 实名认证审核页面，使??? Laravel Blade + Layui 表格和弹窗渲染??? |
| API | `POST /api/admin/authPendingList` / `admin_api_authPendingList` | 查询 `user_auths` 中身份证或银行卡待审记录，并??? `user_infos` 补充用户信息??? |
| API | `POST /api/admin/authCertifiedList` / `admin_api_authCertifiedList` | 查询身份证和银行卡均已???过的认证记录??? |
| API | `POST /api/admin/reviewAuth` / `admin_api_reviewAuth` | 复用原审核动作，当前已修正为写入真实认证字段??? |

### 权限配置
| permissions.slug | permissions.type | route/api_route | 功能 |
| --- | ---: | --- | --- |
| `admin_authentications` | 1 | `/admin/authentications` | 实名认证审核页面/菜单入口??? |
| `admin_auth_pending_list` | 3 | `admin_api_authPendingList` | 待审核认证列表接口权限??? |
| `admin_auth_certified_list` | 3 | `admin_api_authCertifiedList` | 已审核认证列表接口权限??? |
| `admin_user_review_auth` | 3 | `admin_api_reviewAuth` | 执行认证审核动作权限??? |

### 本轮验证命令与结???
```text
php -l app\Http\Controllers\Admin\AuthenticationController.php
php -l app\Http\Controllers\Admin\AdminUserController.php
php -l database\migrations\2026_06_07_000012_add_admin_authentication_permissions.php
php -l routes\admin.php
php -l routes\web.php
php -l resources\lang\zh-CN\admin.php
php -l resources\lang\en\admin.php
结果：全??? No syntax errors detected

node --check public\js\admin\layui\authentications\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
结果：全部???出码 0

php artisan route:list --path=authentications
结果：GET admin/authentications 已注册，路由??? admin_page_authentications

php artisan route:list --path=authPendingList
结果：POST api/admin/authPendingList 已注册，路由??? admin_api_authPendingList，中间件包含 jwt.auth:admin、sso:admin、CheckPermission:admin

php artisan route:list --path=authCertifiedList
结果：POST api/admin/authCertifiedList 已注册，路由??? admin_api_authCertifiedList，中间件包含 jwt.auth:admin、sso:admin、CheckPermission:admin

vendor\bin\phpunit tests\Feature\AdminAuthenticationModuleTest.php
结果：OK (5 tests, 30 assertions)

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
结果：OK (3 tests, 1249 assertions)

Test-NetConnection 127.0.0.1 -Port 3307
结果：TcpTestSucceeded=False，真??? DB 迁移落库与认证审核样本复核暂不可执行
```

### 数据库恢复后必须继续执行
```text
php artisan migrate --force
```

并用真实 DB 复核???
- `permissions` 中是否存??? `admin_authentications`、`admin_auth_pending_list`、`admin_auth_certified_list`、`admin_user_review_auth`???
- 目标后台角色是否通过 `role_permissions` 授权了认证审核页面???待审列表???已审列表和审核动作???
- `POST /api/admin/authPendingList` 是否??? `user_auths.id_card_status=1`、`bank_status=1/3` 返回待审数据???
- `POST /api/admin/authCertifiedList` 是否只返??? `id_card_status=2` ??? `bank_status=2` 的已审核数据???
- `POST /api/admin/reviewAuth` 是否按真实表字段正确更新 `user_auths.id_card_status`、`user_auths.bank_status`、拒绝原因和 `user_infos.auth_status`???
## 26. 2026-06-07 后台实时返佣模块第一阶段

本轮继续按旧项目后台迁移缺口推进，补齐旧项目 `AdminRealCommissionController` 在新项目中的第一阶段落点。旧项目实时返佣依赖 `MT4_TRADES.COMMENT` 关键字和 `MODIFY_TIME` 字段做精确分类；新项目当前真实表 `mt4_trades` 只有 `ticket`、`login`、`symbol`、`cmd`、`volume`、`commission`、`swaps`、`profit`、`open_time`、`close_time` 等字段，暂不具备 COMMENT 正则筛???条件???因此本阶段只实现真实表可支撑的后台只读列表、筛选???汇总???权限配置???多语言和中文???辑注释，当前返佣??????口径为 `cmd=6` ??? `profit>0` 的余额类正向记录???

### 本轮新增和维护文???

- `app/Http/Controllers/Admin/RealtimeCommissionController.php`：新增后台实时返佣控制器，读??? `Mt4Trade::query()`，按 `cmd=6`、`profit>0` 查询，并通过 `AdminDataScopeService` ??? `trade/login` 套用后台数据范围???
- `resources/admin/layui/realtime-commissions/index.blade.php`：新增实时返??? Blade 页面，包含筛选表单???汇总指标区??? `realtimeCommissionTable` 表格容器???
- `public/js/admin/layui/realtime-commissions/index.js`：新??? Layui 页面脚本，负责日期筛选???表格渲染???接口解析和汇???指标更新???
- `database/migrations/2026_06_07_000013_add_admin_realtime_commission_permissions.php`：新增权限字典迁移，维护页面入口和列表接口权限???
- `routes/web.php`：新??? `GET /admin/realtime-commissions`，路由名??? `admin_page_realtime_commissions`，放在后??? Layui 兜底路由之前???
- `routes/admin.php`：新??? `POST /api/admin/realtimeCommissionList`，路由名??? `admin_api_realtimeCommissionList`，继续继承后??? JWT、SSO、权限中间件???
- `resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`、`resources/lang/zh-CN/menus.php`、`resources/lang/en/menus.php`、`public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`：补齐实时返佣中英文文案???
- `tests/Feature/AdminRealtimeCommissionModuleTest.php`：新增实时返佣契约测试，覆盖页面路由、Blade 控件、API 中间件???控制器真实数据源和权限迁移声明???
- `tests/Feature/AdminChineseCommentReadabilityTest.php`：纳入本轮新增文件，继续???查中文???辑注释和编码占位问题???

### 页面与接口清???

| 类型 | 地址或路由名 | 说明 |
| --- | --- | --- |
| Blade 页面 | `GET /admin/realtime-commissions` / `admin_page_realtime_commissions` | 实时返佣后台页面，使??? Laravel Blade + Layui 表格渲染??? |
| API | `POST /api/admin/realtimeCommissionList` / `admin_api_realtimeCommissionList` | 查询 `mt4_trades` ??? `cmd=6` ??? `profit>0` 的余额类正向记录，并返回分页与汇总??? |

### 参数和数据来???

| 参数 | 鏁版嵁琛ㄥ瓧娈? | 逻辑含义 |
| --- | --- | --- |
| `user_id` | `mt4_trades.login` | 业务用户??? MT4 登录号，用于定位指定账户的返佣??????记录??? |
| `ticket` | `mt4_trades.ticket` | MT4 订单号，支持模糊筛?????? |
| `order_id` | `mt4_trades.ticket` | 兼容旧项目参数命名，逻辑等同 `ticket`??? |
| `start_date` | `mt4_trades.close_time` | 平仓时间起始日期，后端转换为当天 `00:00:00` ??? 10 位时间戳??? |
| `end_date` | `mt4_trades.close_time` | 平仓时间结束日期，后端转换为当天 `23:59:59` ??? 10 位时间戳??? |
| `page` | Laravel paginator | 当前页码，兼??? Layui 表格分页??? |
| `per_page` / `limit` | Laravel paginator | 每页条数，`limit` 用于兼容 Layui 默认参数??? |
| `summary.total_records` | 查询结果计数 | 当前筛???条件下的返佣??????记录数??? |
| `summary.total_profit` | `sum(mt4_trades.profit)` | 当前筛???条件下的正向余额金额合计??? |
| `summary.total_commission` | `sum(mt4_trades.profit)` | 第一阶段等同返佣金额合计；后??? COMMENT 字段补齐后可升级为精确返佣口径??? |

### 权限配置

| permissions.slug | permissions.type | route/api_route | 功能 |
| --- | ---: | --- | --- |
| `admin_realtime_commissions` | 1 | `/admin/realtime-commissions` | 实时返佣页面/菜单入口??? |
| `admin_realtime_commission_list` | 3 | `admin_api_realtimeCommissionList` | 实时返佣列表接口权限??? |

### 本轮验证命令与结???

```text
vendor\bin\phpunit tests\Feature\AdminRealtimeCommissionModuleTest.php
结果：OK (5 tests, 21 assertions)，已确认??? RED ??? GREEN???

php -l app\Http\Controllers\Admin\RealtimeCommissionController.php
php -l database\migrations\2026_06_07_000013_add_admin_realtime_commission_permissions.php
php -l routes\admin.php
php -l routes\web.php
php -l resources\lang\zh-CN\admin.php
php -l resources\lang\en\admin.php
php -l resources\lang\zh-CN\menus.php
php -l resources\lang\en\menus.php
结果：全??? No syntax errors detected

node --check public\js\admin\layui\realtime-commissions\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
结果：全部???出码 0

php artisan route:list --path=realtime-commissions
结果：GET admin/realtime-commissions 已注册，路由??? admin_page_realtime_commissions

php artisan route:list --path=realtimeCommissionList
结果：POST api/admin/realtimeCommissionList 已注册，路由??? admin_api_realtimeCommissionList，中间件包含 jwt.auth:admin、sso:admin、CheckPermission:admin

vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php
结果：OK (3 tests, 1334 assertions)

rg -n "\?\?\?" 本轮业务文件、路由???语???文件和测试文???
结果：无命中

Test-NetConnection 127.0.0.1 -Port 3307
结果：TcpTestSucceeded=False，真??? DB 迁移落库和真实返佣样本复核暂不可执行
```

### 数据库恢复后必须继续执行

```text
php artisan migrate --force
```

并用真实 DB 复核???

- `permissions` 中是否存??? `admin_realtime_commissions`、`admin_realtime_commission_list`???
- 目标后台角色是否通过 `role_permissions` 授权了实时返佣页面和列表接口???
- `POST /api/admin/realtimeCommissionList` 是否能按真实 `mt4_trades` 数据返回分页、汇总和数据范围裁剪后的记录???
- 若后续同步补齐旧项目 `COMMENT` 或等价字段，???要把当前 `cmd=6 + profit>0` 的??????口径升级为旧项目返佣关键字精确口径???

## 27. 2026-06-07 默认后台账号与前??? Layui 菜单角色授权修复

本轮继续修复两个真实测试缺口：后台默认超级管理员必须写入当前登录控制器实际读取的 `admins` 表；前台 agent/customer ??? Layui 菜单必须??? `permissions`、`roles`、`role_permissions` ??? `user_logins.role_id` 数据表配置驱动???

### 本轮新增和维护文???

- `database/migrations/2026_06_07_000014_fix_default_admin_and_front_menu_roles.php`：补??? `user_logins.role_id` 字段、写??? `superadmin / Admin@123456`、补齐前台菜单权限字典???绑??? `agent_role` ??? `customer_role` 菜单授权???
- `tests/Feature/DefaultAdminAndFrontMenuRoleMigrationTest.php`：新增回归测试，防止默认后台账号继续落错表，也防止前??? Layui 菜单角色授权缺失???
- `tests/Feature/AdminChineseCommentReadabilityTest.php`：纳入本轮新增迁移和测试文件，继续检查中文???辑注释和编码占位问题???

### 登录与菜单入???

| 类型 | 地址或账??? | 说明 |
| --- | --- | --- |
| 后台登录??? | `GET /admin/login` | Laravel Blade + Layui 后台登录页面??? |
| 后台登录 API | `POST /api/admin/login` | 读取 `admins.username` ??? `admins.password`??? |
| 超级管理员账??? | `superadmin` | 本轮迁移写入 `admins` 琛ㄣ?? |
| 超级管理员初始密??? | `Admin@123456` | 本轮迁移使用 `Hash::make` 写入，不明文保存??? |
| 前台菜单 API | `POST /api/front/navigation/menus` | 前台 Layui 侧栏菜单读取接口??? |
| 前台 agent 测试账号 | `agent@test.com / agent123` | 本轮迁移把演??? agent 绑定??? `agent_role`??? |

### 本轮数据表职???

| 鏁版嵁琛? | 功能 |
| --- | --- |
| `admins` | 后台管理员登录账号表，当前后台登录控制器实际读取该表??? |
| `roles` | 前后台角色表，使??? `guard_type` 区分 `admin` ??? `front`??? |
| `permissions` | 前后台菜单???页面???按钮和 API 权限字典表??? |
| `role_permissions` | 角色实际拥有权限的唯???生效来源??? |
| `user_logins.role_id` | 前台用户绑定角色，用于菜单接口按角色过滤??? |

### 本轮验证命令与结???

```text
vendor\bin\phpunit tests\Feature\DefaultAdminAndFrontMenuRoleMigrationTest.php
结果：OK (1 test, 33 assertions)，已确认??? RED ??? GREEN???

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

- `admins` 表存??? `username=superadmin` ??? `status=1`???
- `/admin/login` 使用 `superadmin / Admin@123456` 可登录???
- `user_logins` 表存??? `role_id` 字段???
- `agent@test.com` 绑定??? `agent_role`???
- `agent_role` 通过 `role_permissions` 拥有 `front_agent`、`front_agent_sub`、`front_commission` 等前台菜单权限???
- `POST /api/front/navigation/menus` ??? agent 登录后返回非??? Layui 菜单树???

## 28. 2026-06-07 后台持仓汇???第???阶段

本轮继续按旧项目后台 `PositionSummaryController` 缺口推进。旧项目持仓汇???依??? `agents`、`data_list`、`MT4_TRADES`、`MT4_USERS`、`symbol_prices`、`family_tree`、`COMMENT REGEXP` ??? `MARGIN_RATE` 等数据???新项目当时真实 `mt4_trades` 表暂不具备旧字段 `COMMENT`、`MARGIN_RATE`、`MODIFY_TIME`，因此第???阶段只迁移当前真实表可支撑的持仓交易统计口径，不伪???入金???出金???返佣精确分类；后续 `COMMENT` ??? `MODIFY_TIME` 已补齐，`MARGIN_RATE` 仍作为剩余边界继续跟进???

### 本轮新增和维护文???

- `app/Http/Controllers/Admin/PositionSummaryController.php`：新增后台持仓汇总控制器，按 `user_infos.user_id = mt4_trades.login` 聚合订单数???手数???盈亏???手续费、库存费和品种分类手数???
- `resources/admin/layui/position-summary/index.blade.php`：新??? Laravel Blade + Layui 后台页面，提供汇总卡片???筛选表单和列表容器???
- `public/js/admin/layui/position-summary/index.js`：新??? Layui 表格脚本，请??? `POST /api/admin/positionSummaryList` 并渲染分页列表与汇???卡片???
- `database/migrations/2026_06_07_000015_add_admin_position_summary_permissions.php`：新增后台持仓汇总页面权限和列表 API 权限字典???
- `routes/web.php`：新??? `GET /admin/position-summary`，路由名 `admin_page_position_summary`，并放在 `/admin/{path?}` 兜底前???
- `routes/admin.php`：新??? `POST /api/admin/positionSummaryList`，路由名 `admin_api_positionSummaryList`，位于后??? JWT、SSO、权限中间件组内???
- `resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`：新增后台持仓汇??? Blade 与接口多语言文案???
- `public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`：新??? Layui JS 运行时多语言字段???
- `tests/Feature/AdminPositionSummaryModuleTest.php`：新增模块契约测试，覆盖页面路由、Blade 控件、API 权限中间件???真实数据源和权限迁移???
- `tests/Feature/AdminChineseCommentReadabilityTest.php`：纳入本轮新增文件，继续???查中文???辑注释与乱码占位???

### 页面与接???

| 类型 | 地址/路由??? | 功能 |
| --- | --- | --- |
| 后台页面 | `GET /admin/position-summary` / `admin_page_position_summary` | Blade 渲染持仓汇???页面??? |
| 后台接口 | `POST /api/admin/positionSummaryList` / `admin_api_positionSummaryList` | 返回持仓汇???分页列表和汇???卡片数据??? |
| 页面 JS | `/js/admin/layui/position-summary/index.js` | Layui 表格渲染、筛选提交???汇总卡片刷新??? |

### 请求参数含义

| 参数 | 数据来源/字段 | 功能 |
| --- | --- | --- |
| `user_id` | `user_infos.user_id`、`mt4_trades.login` | 定位单个代理或客户??? |
| `user_name` | `user_infos.user_name` | 按用户名模糊查询??? |
| `parent_id` | `user_infos.parent_id` | 查询某个上级代理的直属下级??? |
| `account_type` | `user_infos.account_type` | 账户类型筛???，`1=代理`，`2=普???客户`??? |
| `start_date` | `mt4_trades.close_time` | 交易统计???始日期，兼容未平??? `close_time` 为空??? 0 的记录??? |
| `end_date` | `mt4_trades.close_time` | 交易统计结束日期，兼容未平仓 `close_time` 为空??? 0 的记录??? |
| `page` | Laravel paginator | 当前页码??? |
| `per_page` / `limit` | Laravel paginator | 每页条数，`limit` 用于兼容 Layui 默认分页参数??? |

### 返回字段与数据来???

| 字段 | 数据来源 | 功能 |
| --- | --- | --- |
| `records.data[].user_id` | `user_infos.user_id` | 业务用户 ID??? |
| `records.data[].user_name` | `user_infos.user_name` | 业务用户名??? |
| `records.data[].parent_id` | `user_infos.parent_id` | 上级代理 ID??? |
| `records.data[].account_type` | `user_infos.account_type` | 账户类型??? |
| `records.data[].mt4_group` | `user_infos.mt4_group` | MT4 分组??? |
| `records.data[].total_orders` | `COUNT(mt4_trades.*)` | 交易订单数??? |
| `records.data[].total_volume` | `SUM(mt4_trades.volume)` | 总交易手数??? |
| `records.data[].total_profit` | `SUM(mt4_trades.profit)` | 总盈亏??? |
| `records.data[].total_comm` | `SUM(mt4_trades.commission)` | 手续费合计??? |
| `records.data[].total_swaps` | `SUM(mt4_trades.swaps)` | 库存费合计??? |
| `records.data[].total_noble_metal` | `symbol_prices.group_id = 1` | 贵金属手数??? |
| `records.data[].total_crud_oil` | `symbol_prices.group_id = 2` | 原油手数??? |
| `records.data[].total_for_exca` | `symbol_prices.group_id = 3` | 外汇手数??? |
| `records.data[].total_index` | `symbol_prices.group_id = 4` | 指数手数??? |
| `records.data[].total_currency` | `symbol_prices.group_id = 5` | 货币手数??? |
| `records.data[].total_stock` | `symbol_prices.group_id = 6` | 股票手数??? |
| `summary.total_accounts` | 当前筛???后的用户行??? | 汇???卡片账户数??? |
| `summary.total_orders` | 当前筛???后的订单合??? | 汇???卡片订单数??? |
| `summary.total_volume` | 当前筛???后的手数合??? | 汇???卡片???手数??? |
| `summary.total_profit` | 当前筛???后的盈亏合??? | 汇???卡片???盈亏??? |
| `summary.total_comm` | 当前筛???后的手续费合计 | 汇???卡片手续费??? |
| `summary.total_swaps` | 当前筛???后的库存费合计 | 汇???卡片库存费??? |

### 权限配置

| permissions.slug | permissions.type | route/api_route | 功能 |
| --- | ---: | --- | --- |
| `admin_position_summary` | 1 | `/admin/position-summary` | 后台持仓汇???页???/菜单入口??? |
| `admin_position_summary_list` | 3 | `admin_api_positionSummaryList` | 后台持仓汇???列表接口权限??? |

### 数据范围控制

后台接口先经??? `jwt.auth:admin`、`sso:admin`、`check.permission:admin`，再??? `PositionSummaryController::applyDataScope()` 调用 `AdminDataScopeService`。实际可见数据来自数据表配置???

- `role_data_scopes`：角色级数据范围配置???
- `admin_agent_bindings`：管理员可管理代理节点绑定???
- `permissions`：页面???按钮和接口权限字典???
- `role_permissions`：角色实际拥有权限的唯一生效来源???

### 当前边界

- 当前第一阶段不使用旧项目 `COMMENT REGEXP`、`MARGIN_RATE`、`MODIFY_TIME`，因为新项目真实 `mt4_trades` 表暂未确认具备这些字段???
- 当前统计只覆盖交易类 `cmd in (0,1,2,3,4,5)`，不??? `cmd=6` 余额流水混入持仓汇??汇??
- 真实 DB `127.0.0.1:3307` 当前不可连???，因此本轮可以完成静??????路由???Blade 契约和单测验证，不能声称真实库迁移已执行???

### 数据库恢复后必须继续执行

```text
php artisan migrate --force
```

并用真实 DB 复核???

- `permissions` 中是否存??? `admin_position_summary`、`admin_position_summary_list`???
- 目标后台角色是否通过 `role_permissions` 授权了持仓汇总页面和列表接口???
- `POST /api/admin/positionSummaryList` 是否按真??? `user_infos`、`mt4_trades`、`symbol_prices` 返回分页、汇总和数据范围裁剪后的记录???
- 如后续补齐旧项目字段或等价字段，再把入金、出金???返佣和保证金口径升级为旧项目精确口径???

## 29. 2026-06-07 后台风控 MT4 第一阶段

本轮继续按旧项目 `FengXianManageController` 缺口推进。旧项目风控包含盈利风险、持仓风险???异??? IP、追保和强平入口；新项目此前 `RiskController` 仍读??? `UserTrade`，且 `marginCalls()` 返回空数组占位，不能证明后台风控已经按当前真??? MT4 表迁移???本轮先完成第一阶段：当前持仓风险列表???追保预警列表???强平信号前置校验???Blade 双表视图、运行时多语???和测试覆盖???

### 本轮新增和维护文???

- `app/Http/Controllers/Admin/RiskController.php`：重写为可读 UTF-8 中文注释版本，读??? `Mt4Trade`、`Mt4User`、`UserInfo`，并接入 `AdminDataScopeService`???
- `resources/admin/layui/risk/index.blade.php`：升级为 Laravel Blade + Layui 双视图页面，包含风险持仓表???追保预警表、筛选表单和汇???卡片???
- `public/js/admin/layui/risk/index.js`：重写为 UTF-8，解??? `records + summary` 统一响应结构，支持风险持???/追保预警切换和强平信号按钮???
- `resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`：补充风控页面描述???风险??????最高保证金比例等后端多语言 key???
- `public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`：补??? Layui JS 运行时风控字段多语言 key???
- `tests/Feature/AdminRiskMt4ModuleTest.php`：新??? RED-GREEN 契约测试，约束风控必须从真实 MT4 表读取，不能继续使用 `UserTrade` 占位或空数组追保???
- `tests/Feature/AdminChineseCommentReadabilityTest.php`：纳入本轮风控新???/重写文件，继续检查中文???辑注释与编码占位???

### 页面与接???

| 类型 | 地址/路由??? | 功能 |
| --- | --- | --- |
| 后台页面 | `GET /admin/risk` / `admin_page_risk` | Blade 渲染风控管理页面??? |
| 风险持仓接口 | `POST /api/admin/riskPositions` / `admin_api_riskPositions` | 返回当前 MT4 未平仓订单风险分页列表和汇??汇?? |
| 追保预警接口 | `POST /api/admin/riskMarginCalls` / `admin_api_riskMarginCalls` | 返回保证金比例低于阈值的 MT4 用户资金快照??? |
| 强平信号接口 | `POST /api/admin/riskForceClose/{id}` / `admin_api_riskForceClose` | 校验当前未平仓订单和数据范围后返回强平信号结果??? |

### 请求参数含义

| 参数 | 数据来源/字段 | 功能 |
| --- | --- | --- |
| `user_id` | `mt4_trades.login`、`user_infos.user_id` | 风险持仓??? MT4 登录账号筛???，追保预警按业务用??? ID 筛?????? |
| `ticket` | `mt4_trades.ticket` | 当前持仓风险列表??? MT4 订单号模糊查询??? |
| `symbol` | `mt4_trades.symbol` | 当前持仓风险列表按交易品种筛选??? |
| `start_date` / `end_date` | `mt4_trades.open_time` | 当前持仓风险列表按开仓时间戳范围筛?????? |
| `login` | `mt4_users.login` | 追保预警??? MT4 登录账号筛?????? |
| `user_name` | `user_infos.user_name` | 追保预警按业务用户名模糊查询??? |
| `max_margin_level` | 计算字段 `equity / margin * 100` | 追保预警阈???，默认 100，比例越低风险越高??? |
| `page` | Laravel paginator | 当前页码??? |
| `per_page` / `limit` | Laravel paginator | 每页条数，`limit` 用于兼容 Layui 默认分页参数??? |

### 返回字段与数据来???

| 字段 | 数据来源 | 功能 |
| --- | --- | --- |
| `records.data[].ticket` | `mt4_trades.ticket` | MT4 订单号??? |
| `records.data[].login` | `mt4_trades.login`、`mt4_users.login` | MT4 登录账号??? |
| `records.data[].symbol` | `mt4_trades.symbol` | 交易品种??? |
| `records.data[].volume` | `mt4_trades.volume` | 当前持仓手数??? |
| `records.data[].profit` | `mt4_trades.profit` | 当前浮动盈亏??? |
| `records.data[].risk_value` | `profit - abs(commission)` | 第一阶段风险收益值??? |
| `records.data[].margin_level` | `mt4_users.equity / mt4_users.margin * 100` | 追保预警保证金比例??? |
| `summary.total_records` | 当前筛???后的行??? | 汇???卡片记录数??? |
| `summary.total_profit` | 当前筛???后的盈亏合??? | 风险持仓盈亏合计??? |
| `summary.total_volume` | 当前筛???后的手数合??? | 风险持仓手数合计??? |
| `summary.total_margin` | 当前筛???后的保证金合计 | 追保预警保证金合计??? |
| `summary.total_risk_value` | 当前筛???后的风险???合??? | 风险值合计??? |

### 权限与数据范???

已有权限迁移 `2026_06_06_000005_add_admin_second_batch_module_permissions.php` 继续生效???

| permissions.slug | permissions.type | route/api_route | 功能 |
| --- | ---: | --- | --- |
| `admin_risk` | 1 | `/admin/risk` | 后台风控页面/菜单入口??? |
| `admin_risk_positions` | 3 | `admin_api_riskPositions` | 风险持仓列表接口权限??? |
| `admin_risk_margin_calls` | 3 | `admin_api_riskMarginCalls` | 追保预警接口权限??? |
| `admin_risk_force_close` | 3 | `admin_api_riskForceClose` | 强平信号接口权限??? |

数据范围仍来自数据表配置???

- `permissions`：页面???按钮???接口权限字典???
- `role_permissions`：角色实际拥有权限的唯一生效来源???
- `role_data_scopes`：角色级数据范围配置???
- `admin_agent_bindings`：管理员绑定代理节点???

### 当前边界

- 当前第一阶段不迁移旧项目异常 IP 明细，因为旧项目依赖 `system_login_log`，新项目当前主要登录日志表需要再做字段级确认???
- 当前第一阶段不直接调??? MT4 服务器执行强平，只完成订单存在???未平仓和数据范围校验，并返回强平信号结果???
- 当前真实 `mt4_trades` 表暂未确认旧项目 `COMMENT`、`MARGIN_RATE`、`MODIFY_TIME` 字段完整存在，因此不伪???旧项目精确盈利风险和实???/测试盘特殊口径???
- 真实 DB `127.0.0.1:3307` 当前不可连???，不能声称真实库迁移或真实样本查询已执行???

### 数据库恢复后必须继续执行

```text
php artisan migrate --force
```

并用真实 DB 复核???

- `permissions` 中是否存??? `admin_risk`、`admin_risk_positions`、`admin_risk_margin_calls`、`admin_risk_force_close`???
- 目标后台角色是否通过 `role_permissions` 授权了风控页面???追保预警和强平信号接口???
- `POST /api/admin/riskPositions` 是否按真??? `mt4_trades` 未平仓记录返回分页和汇??汇??
- `POST /api/admin/riskMarginCalls` 是否按真??? `mt4_users` 资金快照返回保证金比例低于阈值的用户???
- `POST /api/admin/riskForceClose/{id}` 是否拒绝已平仓或数据范围外订单???

## 30. 2026-06-07 后台风控异常 IP 第一阶段

本轮继续补齐旧项??? `FengXianManageController::fengXian_Ipaddress_list` 缺口。旧项目通过 `system_login_log` 聚合同一 IP 登录多个账号的风险；新项目当前真实表??? `user_login_logs`，因此第???阶段基于 `user_login_logs.login_ip` 聚合??? IP 多账号登录风险，并继续使用后台权限与数据范围控制???

### 本轮新增和维护文???

- `app/Http/Controllers/Admin/RiskController.php`：新??? `riskIpList()`，读??? `UserLoginLog::query()`，按 `user_login_logs.login_ip` 聚合异常 IP???
- `routes/admin.php`：新??? `POST /api/admin/riskIpList`，路由名 `admin_api_riskIpList`，位于后??? JWT、SSO、权限中间件组内???
- `database/migrations/2026_06_06_000005_add_admin_second_batch_module_permissions.php`：在风控模块下新??? `admin_risk_ip_list` 接口权限字典???
- `resources/admin/layui/risk/index.blade.php`：新增异??? IP 筛???项 `login_ip`、`min_user_count` ??? `riskIpTable` 表格容器???
- `public/js/admin/layui/risk/index.js`：新??? `ipRisk` 视图、`riskIpTable` 表格??? `/api/admin/riskIpList` 请求???
- `resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`：新增异??? IP 后端多语??? key???
- `public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`：新增异??? IP Layui 运行时多语言 key???
- `tests/Feature/AdminRiskIpModuleTest.php`：新??? RED-GREEN 契约测试，约束路由???控制器数据源???Blade、JS 和权限迁移???
- `tests/Feature/AdminChineseCommentReadabilityTest.php`：纳入本轮新增测试文件???

### 页面与接???

| 类型 | 地址/路由??? | 功能 |
| --- | --- | --- |
| 后台页面 | `GET /admin/risk` / `admin_page_risk` | 风控页面第三个视图：异常 IP??? |
| 异常 IP 接口 | `POST /api/admin/riskIpList` / `admin_api_riskIpList` | 返回同一 IP 登录多个业务账号的聚合风险列表??? |

### 请求参数含义

| 参数 | 数据来源/字段 | 功能 |
| --- | --- | --- |
| `login_ip` | `user_login_logs.login_ip` | 按登??? IP 模糊查询??? |
| `user_id` | `user_login_logs.user_id` | 查询某个业务用户参与过的异常 IP??? |
| `min_user_count` | `COUNT(DISTINCT user_login_logs.user_id)` | 同一 IP 至少关联多少个不同用户才判定为风险，默认 2??? |
| `start_date` / `end_date` | `user_login_logs.created_at` | 登录时间戳范围筛选??? |
| `page` | Laravel paginator | 当前页码??? |
| `per_page` / `limit` | Laravel paginator | 每页条数，兼??? Layui 默认分页参数??? |

### 返回字段与数据来???

| 字段 | 数据来源 | 功能 |
| --- | --- | --- |
| `records.data[].login_ip` | `user_login_logs.login_ip` | 异常登录 IP??? |
| `records.data[].login_count` | `COUNT(*)` | ??? IP 总登录次数??? |
| `records.data[].distinct_user_count` | `COUNT(DISTINCT user_id)` | ??? IP 关联的不同业务用户数量??? |
| `records.data[].latest_login_at` | `MAX(user_login_logs.created_at)` | ???近一次登录时间戳??? |
| `records.data[].sample_user_name` | `MIN(user_infos.user_name)` | 示例用户名，用于快???识别风险来源??? |
| `summary.total_records` | 当前筛???后??? IP 聚合行数 | 异常 IP 数量??? |

### 权限配置

| permissions.slug | permissions.type | route/api_route | 功能 |
| --- | ---: | --- | --- |
| `admin_risk_ip_list` | 3 | `admin_api_riskIpList` | 查看异常 IP 风控列表??? |

该权限仍挂在 `admin_risk` 页面权限下，角色是否拥有访问能力只由 `role_permissions` 决定???

### 当前边界

- 第一阶段只做 IP 聚合列表，不展开 IP 下全部用户明细；后续可新增详情弹窗???
- 当前 `user_login_logs.created_at` ??? 10 位时间戳，页面暂直接展示原???；后续 UI 优化可统???格式化日期???
- 真实 DB `127.0.0.1:3307` 当前不可连???，因此不能声称真实异常 IP 样本已查询???

### 数据库恢复后必须继续执行

```text
php artisan migrate --force
```

并用真实 DB 复核???

- `permissions` 中是否存??? `admin_risk_ip_list`，且挂在 `admin_risk` 页面权限下???
- 目标后台角色是否通过 `role_permissions` 授权 `admin_api_riskIpList`???
- `POST /api/admin/riskIpList` 是否按真??? `user_login_logs` 返回??? IP 多账号聚合数据???

## 31. 2026-06-07 后台风控异常 IP 详情第一阶段

本轮继续补齐旧项??? `FengXianManageController::fengXian_Ipaddress_detail` 缺口。旧项目详情会按登录 IP 展开账号明细，并补充用户名???上级???注册时间???开平仓数量和入出金统计；新项目本阶段基于当前真实表实现可维护版本：`user_login_logs` 负责登录明细，`user_infos` 负责用户资料和代理关系，`mt4_trades` 负责???平仓数量，`deposit_records` ??? `withdraw_records` 负责资金统计???

### 本轮新增和维护文???

- `tests/Feature/AdminRiskIpDetailModuleTest.php`：先??? RED 契约测试，约束路由???控制器、Blade、JS 和权限迁移必须完整???
- `app/Http/Controllers/Admin/RiskController.php`：新??? `riskIpDetail()` ??? `baseRiskIpDetailQuery()`，按 `login_ip` 精确展开异常 IP 下的账号详情???
- `routes/admin.php`：新??? `POST /api/admin/riskIpDetail`，路由名 `admin_api_riskIpDetail`，继续位于后??? JWT、SSO、权限中间件组???
- `database/migrations/2026_06_06_000005_add_admin_second_batch_module_permissions.php`：新??? `admin_risk_ip_detail` 权限字典，`api_route` 绑定 `admin_api_riskIpDetail`???
- `resources/admin/layui/risk/index.blade.php`：新增异??? IP 详情按钮模板 `riskIpActions` 和详情弹??? `riskIpDetailDialog`、`riskIpDetailTable`???
- `public/js/admin/layui/risk/index.js`：新增异??? IP 详情表格、`ipDetail` 行工具事件和 `/api/admin/riskIpDetail` 请求???
- `resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`：新增后端多语言 key：`risk_ip_detail`、`risk_ip_detail_fetched`???
- `public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`：新??? Layui 运行时多语言 key：`risk_ip_detail`、`risk_ip_detail_fetched`???

### 页面与接???

| 类型 | 地址/路由??? | 功能 |
| --- | --- | --- |
| 后台页面 | `GET /admin/risk` / `admin_page_risk` | 异常 IP 列表中点击???详情???打??? Blade + Layui 弹层??? |
| 异常 IP 列表接口 | `POST /api/admin/riskIpList` / `admin_api_riskIpList` | 返回同一 IP 登录多个业务账号的聚合风险列表??? |
| 异常 IP 详情接口 | `POST /api/admin/riskIpDetail` / `admin_api_riskIpDetail` | ??? `login_ip` 展开??? IP 下的业务账号、登录次数???交易统计和资金统计??? |

### 请求参数含义

| 参数 | 数据来源/字段 | 功能 |
| --- | --- | --- |
| `login_ip` | `user_login_logs.login_ip` | 必填，精确匹配异??? IP 聚合行，用于展开详情??? |
| `user_id` | `user_login_logs.user_id` | 可???，在详情弹层内定位某个业务用户??? |
| `start_date` / `end_date` | `user_login_logs.created_at` | 可???，按登录时间戳范围筛???详情记录??? |
| `page` | Laravel paginator | 当前页码??? |
| `per_page` / `limit` | Laravel paginator | 每页条数，`limit` 用于兼容 Layui 默认分页参数??? |

### 返回字段与数据来???

| 字段 | 数据来源 | 功能 |
| --- | --- | --- |
| `records.data[].login_ip` | `user_login_logs.login_ip` | 当前展开的异常登??? IP??? |
| `records.data[].user_id` | `user_login_logs.user_id` | ??? IP 下登录过的业务用??? ID??? |
| `records.data[].user_name` | `user_infos.user_name` | 业务用户名??? |
| `records.data[].parent_id` | `user_infos.parent_id` | 上级代理 ID，用于追踪代理链路风险??? |
| `records.data[].account_type` | `user_infos.account_type` | 账号类型，便于区分代理与普???客户??? |
| `records.data[].registered_at` | `user_infos.created_at` | 用户注册时间戳??? |
| `records.data[].login_count` | `COUNT(*)` | 该用户在??? IP 下的登录次数??? |
| `records.data[].latest_login_at` | `MAX(user_login_logs.created_at)` | 该用户在??? IP 下最近一次登录时间戳??? |
| `records.data[].open_order_count` | `mt4_trades` 聚合 | 当前未平仓订单数量，口径为交易类 `cmd in (0..5)` ??? `close_time IS NULL OR close_time = 0`??? |
| `records.data[].closed_order_count` | `mt4_trades` 聚合 | 历史平仓订单数量，口径为交易??? `cmd in (0..5)` ??? `close_time > 0`??? |
| `records.data[].total_deposit` | `deposit_records.amount` | 当前业务入金表中的入金金额合计??? |
| `records.data[].total_withdraw` | `withdraw_records.apply_amount` | 当前业务出金表中的申请出金金额合计??? |

### 权限配置

| permissions.slug | permissions.type | route/api_route | 功能 |
| --- | ---: | --- | --- |
| `admin_risk_ip_detail` | 3 | `admin_api_riskIpDetail` | 查看异常 IP 详情??? |

该权限挂??? `admin_risk` 页面权限下，前端按钮??? `data-permission="admin_risk_ip_detail"` 控制显示，后端接口用 `permissions.api_route = admin_api_riskIpDetail` ??? `check.permission:admin` 做强制鉴权???角色是否拥有能力仍只由 `role_permissions` 中间表决定???

### 数据范围

- 异常 IP 详情接口继续调用 `AdminDataScopeService->apply($query, $admin, 'user', 'user_login_logs.user_id')`???
- 不同后台管理员只能看到数据表配置允许的数据范围；数据范围来源仍是 `role_data_scopes` ??? `admin_agent_bindings`???
- 详情接口不会绕过异常 IP 列表权限，必须单独拥??? `admin_risk_ip_detail` 对应接口权限???

### 当前边界

- 本阶段不执行旧项目里被注???/关闭??? MT4 同步动作，不伪??? MT4 服务端实时刷新???
- 本阶段资金统计使用新项目真实业务??? `deposit_records` ??? `withdraw_records`，不强行复刻旧项目依??? `MT4_TRADES.COMMENT REGEXP` 的资金识别口径???
- 真实 DB `127.0.0.1:3307` 当前不可连???，因此不能声称真实库样本已经查出；本轮已完成代码???路由???Blade、JS、语???包和??? DB 契约验证???

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
已确??? admin_api_riskIpDetail 位于 api、jwt.auth:admin、sso:admin、check.permission:admin 中间件组???
```

??? DB 连接影响的验证：

```text
vendor\bin\phpunit tests\Feature\AdminSecondBatchPermissionMigrationTest.php
ERROR: SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接???

Test-NetConnection 127.0.0.1 -Port 3307
TcpTestSucceeded: False
```

### 数据库恢复后必须继续执行

```text
php artisan migrate --force
vendor\bin\phpunit tests\Feature\AdminSecondBatchPermissionMigrationTest.php
```

并用真实 DB 复核???
- `permissions` 中是否存??? `admin_risk_ip_detail`，且 `api_route` ??? `admin_api_riskIpDetail`???
- 目标后台角色是否通过 `role_permissions` 授权 `admin_risk_ip_detail`???
- `POST /api/admin/riskIpDetail` 是否按真??? `user_login_logs`、`user_infos`、`mt4_trades`、`deposit_records`、`withdraw_records` 返回正确详情???
- 数据范围配置是否能限制普通管理员只看到授权代理或用户范围内的 IP 详情???

## 32. 2026-06-07 后台批量入金/出金导入失败重试第一阶段

本轮继续按迁移缺口审计中??? P0 资金导入链路推进，补齐旧项目 `BatchAmountController::againDepositAmount` ??? `BatchAmountController::againWithdrawAmount` 的第???阶段新项目落点???当前阶段不伪??? MT4 同步结果，只把失败导入记录重新放回待处理队列，供后续真实同步或人工处理链路继续执行???

### 本轮新增和维护文???

- `tests/Feature/AdminBatchAmountImportRetryModuleTest.php`：先??? RED 契约测试，约束失败重试路由???控制器、Blade、JS 和权限迁移必须完整???
- `app/Http/Controllers/Admin/BatchAmountImportController.php`：新??? `retryDepositImport()`、`retryWithdrawImport()` ??? `retryImportRecord()`，只允许 `is_synced=2` 的失败记录回到待处理状??????
- `routes/admin.php`：新??? `POST /api/admin/retryDepositImport/{id}` ??? `POST /api/admin/retryWithdrawImport/{id}`，继续位于后??? JWT、SSO、权限中间件组???
- `database/migrations/2026_06_07_000004_add_admin_batch_amount_import_permissions.php`：新??? `admin_batch_deposit_import_retry` ??? `admin_batch_withdraw_import_retry` 权限字典???
- `tests/Feature/AdminBatchAmountImportPermissionMigrationTest.php`：同步补充两??? retry 权限断言，数据库恢复后会继续验证权限是否真正写入 `permissions` 琛ㄣ??
- `resources/admin/layui/deposit-imports/index.blade.php`：新增入金导入重试按钮模??? `depositImportActions`???
- `resources/admin/layui/withdraw-imports/index.blade.php`：新增出金导入重试按钮模??? `withdrawImportActions`???
- `public/js/admin/layui/deposit-imports/index.js`：新??? `retryDepositImport` 行工具事件，调用 `/api/admin/retryDepositImport/{id}`???
- `public/js/admin/layui/withdraw-imports/index.js`：新??? `retryWithdrawImport` 行工具事件，调用 `/api/admin/retryWithdrawImport/{id}`???
- `resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`：新增后端重试成功???记录不存在、非失败记录不可重试等多语言 key???
- `public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`：新增前端按钮和确认提示多语??? key???

### 页面与接???

| 类型 | 地址/路由??? | 功能 |
| --- | --- | --- |
| 入金导入页面 | `GET /admin/deposit-imports` / `admin_page_deposit_imports` | 在入金导入列表中提供失败记录重试按钮??? |
| 出金导入页面 | `GET /admin/withdraw-imports` / `admin_page_withdraw_imports` | 在出金导入列表中提供失败记录重试按钮??? |
| 入金重试接口 | `POST /api/admin/retryDepositImport/{id}` / `admin_api_retryDepositImport` | 将失败的入金导入记录重新置为待处理??? |
| 出金重试接口 | `POST /api/admin/retryWithdrawImport/{id}` / `admin_api_retryWithdrawImport` | 将失败的出金导入记录重新置为待处理??? |

### 请求参数含义

| 参数 | 数据来源/字段 | 功能 |
| --- | --- | --- |
| `id` | `deposit_imports.id` ??? `withdraw_imports.id` | 必填，导入记录主键，用于定位要重试的单条失败记录??? |

### 重试业务规则

| 字段/条件 | 规则 |
| --- | --- |
| `is_synced = 2` | 只有同步失败的记录可以重试??? |
| `is_synced = 0` | 重试后回到待处理状???，等待后续真实同步流程处理??? |
| `fail_reason = ''` | 重试后清空旧失败原因，避免页面继续展示过期错误??? |
| `updated_by` | 写入当前后台管理??? ID；若测试或无登录上下文则??? 0??? |
| 数据范围 | 重试前继续调??? `AdminDataScopeService`，管理员只能重试自己可见范围内的导入记录??? |

### 权限配置

| permissions.slug | permissions.type | route/api_route | 功能 |
| --- | ---: | --- | --- |
| `admin_batch_deposit_import_retry` | 3 | `admin_api_retryDepositImport` | 重试失败的批量入金导入记录??? |
| `admin_batch_withdraw_import_retry` | 3 | `admin_api_retryWithdrawImport` | 重试失败的批量出金导入记录??? |

前端按钮使用 `data-permission` 控制可见性，后端接口继续使用 `permissions.api_route` ??? `check.permission:admin` 强制鉴权。角色是否拥有重试能力仍只由 `role_permissions` 中间表决定???

### 当前边界

- 本阶段不执行 Excel/CSV 文件解析???
- 本阶段不直接执行 MT4 入金、出金或同步动作???
- 本阶段不生成导入模板和导出文件???
- 本阶段只提供失败导入记录重新入队能力，为后续真实同步链路留出清晰状??????

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
已确??? admin_api_retryDepositImport ??? admin_api_retryWithdrawImport 位于 api、jwt.auth:admin、sso:admin、check.permission:admin 中间件组???
```

??? DB 连接影响的验证：

```text
vendor\bin\phpunit tests\Feature\AdminBatchAmountImportPermissionMigrationTest.php
ERROR: SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接???

Test-NetConnection 127.0.0.1 -Port 3307
TcpTestSucceeded: False
```

### 数据库恢复后必须继续执行

```text
php artisan migrate --force
vendor\bin\phpunit tests\Feature\AdminBatchAmountImportPermissionMigrationTest.php
```

并用真实 DB 复核???
- `permissions` 中是否存??? `admin_batch_deposit_import_retry`，且 `api_route` ??? `admin_api_retryDepositImport`???
- `permissions` 中是否存??? `admin_batch_withdraw_import_retry`，且 `api_route` ??? `admin_api_retryWithdrawImport`???
- 目标后台角色是否通过 `role_permissions` 授权两个重试权限???
- 失败记录 `is_synced=2` 调用重试后是否变??? `is_synced=0` ??? `fail_reason` 清空???
- 待处理或成功记录调用重试时是否返??? `import_retry_only_failed`，避免重复进入队列???

## 33. 2026-06-07 后台批量信用导入失败重试第一阶段

本轮继续补齐旧项??? `BatchCreditController::againCreditAmount` 缺口。当前阶段保持与批量入金/出金导入重试???致的安全边界：失败重试只把失败记录重新放回待处理队列，不伪??? MT4 信用同步成功，也不直接改动用户信用额度???

### 本轮新增和维护文???

- `tests/Feature/AdminBatchCreditImportRetryModuleTest.php`：先??? RED 契约测试，约束信用导入失败重试路由???控制器、Blade、JS 和权限迁移???
- `app/Http/Controllers/Admin/BatchCreditImportController.php`：新??? `retryCreditImport()`，只允许 `is_synced=2` 的失败记录回到待处理状??????
- `routes/admin.php`：新??? `POST /api/admin/retryCreditImport/{id}`，路由名 `admin_api_retryCreditImport`???
- `database/migrations/2026_06_07_000005_add_admin_batch_credit_import_permissions.php`：新??? `admin_batch_credit_import_retry` 权限字典???
- `tests/Feature/AdminBatchCreditImportPermissionMigrationTest.php`：同步补??? retry 权限断言，数据库恢复后会验证权限是否真正写入 `permissions` 琛ㄣ??
- `resources/admin/layui/credit-imports/index.blade.php`：新增信用导入重试按钮模??? `creditImportActions`???
- `public/js/admin/layui/credit-imports/index.js`：新??? `retryCreditImport` 行工具事件，调用 `/api/admin/retryCreditImport/{id}`???
- `resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`：新??? `credit_import_retry_success` 后端多语??? key???

### 页面与接???

| 类型 | 地址/路由??? | 功能 |
| --- | --- | --- |
| 信用导入页面 | `GET /admin/credit-imports` / `admin_page_credit_imports` | 在信用导入列表中提供失败记录重试按钮??? |
| 信用重试接口 | `POST /api/admin/retryCreditImport/{id}` / `admin_api_retryCreditImport` | 将失败的信用导入记录重新置为待处理??? |

### 请求参数含义

| 参数 | 数据来源/字段 | 功能 |
| --- | --- | --- |
| `id` | `credit_imports.id` | 必填，信用导入记录主键，用于定位要重试的单条失败记录??? |

### 重试业务规则

| 字段/条件 | 规则 |
| --- | --- |
| `is_synced = 2` | 只有同步失败的信用导入记录可以重试??? |
| `is_synced = 0` | 重试后回到待处理状???，等待后续真实 MT4 信用同步或人工处理??? |
| `fail_reason = ''` | 重试后清空旧失败原因，避免页面展示过期错误??? |
| `updated_by` | 写入当前后台管理??? ID；无登录上下文时??? 0??? |
| 数据范围 | 重试前继续调??? `AdminDataScopeService`，管理员只能重试自己可见范围内的信用导入记录??? |

### 权限配置

| permissions.slug | permissions.type | route/api_route | 功能 |
| --- | ---: | --- | --- |
| `admin_batch_credit_import_retry` | 3 | `admin_api_retryCreditImport` | 重试失败的批量信用导入记录??? |

前端按钮使用 `data-permission="admin_batch_credit_import_retry"` 控制显示，后端接口继续使??? `permissions.api_route = admin_api_retryCreditImport` ??? `check.permission:admin` 强制鉴权。角色是否拥有重试能力仍只由 `role_permissions` 中间表决定???

### 当前边界

- 本阶段不执行 Excel/CSV 文件解析???
- 本阶段不执行 MT4 信用额度同步???
- 本阶段不直接修改 `user_infos` 的信用???权益或保证金字段???
- 本阶段只提供失败信用导入记录重新入队能力，为后续真实同步链路保留清晰状??????

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
已确??? admin_api_retryCreditImport 位于 api、jwt.auth:admin、sso:admin、check.permission:admin 中间件组???
```

??? DB 连接影响的验证：

```text
vendor\bin\phpunit tests\Feature\AdminBatchCreditImportPermissionMigrationTest.php
ERROR: SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接???

Test-NetConnection 127.0.0.1 -Port 3307
TcpTestSucceeded: False
```

### 数据库恢复后必须继续执行

```text
php artisan migrate --force
vendor\bin\phpunit tests\Feature\AdminBatchCreditImportPermissionMigrationTest.php
```

并用真实 DB 复核???
- `permissions` 中是否存??? `admin_batch_credit_import_retry`，且 `api_route` ??? `admin_api_retryCreditImport`???
- 目标后台角色是否通过 `role_permissions` 授权 `admin_batch_credit_import_retry`???
- 失败信用导入记录 `is_synced=2` 调用重试后是否变??? `is_synced=0` ??? `fail_reason` 清空???
- 待处理或成功信用导入记录调用重试时是否返??? `import_retry_only_failed`，避免重复进入队列???

## 34. 2026-06-07 后台权益汇???手动确认第???阶段

本轮继续对比旧项??? `RightsSummaryController::ManualConfirmWithdrawOrdeposit`，补齐新项目权益汇???模块中可安全落库的手动确认能力。自??? MT4 入出金确认仍保持未迁移状态，避免??? MT4 链路未完整验证前伪???结算成功???

### 本轮新增和维护文???

- `tests/Feature/AdminRightsSummaryManualConfirmModuleTest.php`：按 TDD 先写 RED 测试，约束手动确??? API、控制器、Blade、JS 和权限迁移???
- `app/Http/Controllers/Admin/RightsSummaryController.php`：新??? `manualConfirmRightsSettlement()`，并在权益汇总列表中关联每个用户???新一??? `rights_settlements` 记录???
- `routes/admin.php`：新??? `POST /api/admin/manualConfirmRightsSettlement/{id}`，路由名 `admin_api_manualConfirmRightsSettlement`???
- `database/migrations/2026_06_07_000007_add_admin_rights_summary_permissions.php`：新??? `admin_rights_summary_manual_confirm` 权限字典，`api_route` 指向 `admin_api_manualConfirmRightsSettlement`???
- `resources/admin/layui/rights-summary/index.blade.php`：新增行操作按钮模板 `rightsSummaryActions` 和手动确认弹窗表单???
- `public/js/admin/layui/rights-summary/index.js`：新增操作列、结算状态展示???手动确认弹窗提交和权限刷新???
- `resources/lang/zh-CN/admin.php`、`resources/lang/en/admin.php`：新增手动确认后端多语言消息???
- `public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`：新增手动确认前端多语言文案???

### 页面与接???

| 类型 | 地址/路由??? | 功能 |
| --- | --- | --- |
| 权益汇???页??? | `GET /admin/rights-summary` / `admin_page_rights_summary` | 展示 MT4 权益汇??汇??最新权益结算金额和结算状???，并提供手动确认入口??? |
| 手动确认接口 | `POST /api/admin/manualConfirmRightsSettlement/{id}` / `admin_api_manualConfirmRightsSettlement` | ??? `rights_settlements.status=0` 的待处理记录人工确认为已处理??? |

### 请求参数含义

| 参数 | 数据来源/字段 | 功能 |
| --- | --- | --- |
| `id` | `rights_settlements.id` | 必填，权益结算记录主键，用于定位待确认记录??? |
| `manual_confirm_reason` | 写入 `rights_settlements.remark` | 必填，人工确认原因或财务备注，用于后续审计追踪??? |

### 业务规则

| 字段/条件 | 规则 |
| --- | --- |
| `rights_settlements.status = 0` | 只有待处理记录可以手动确认??? |
| `rights_settlements.status = 1` | 手动确认后置为已处理??? |
| `rights_settlements.remark` | 写入 `manual_confirm_reason`，保留人工确认原因??? |
| `updated_at` | 写入当前 Unix 时间戳??? |
| 数据范围 | 确认前调??? `AdminDataScopeService::canAccessUser()`，管理员只能确认自己可见业务用户的权益结算记录??? |
| MT4 边界 | 本阶段不调用 MT4 入金/出金接口，不??? `mt4_trades`，不伪???自动结算成功??? |

### 权限配置

| permissions.slug | permissions.type | route/api_route | 功能 |
| --- | ---: | --- | --- |
| `admin_rights_summary_manual_confirm` | 3 | `admin_api_manualConfirmRightsSettlement` | 手动确认权益结算记录??? |

前端按钮使用 `data-permission="admin_rights_summary_manual_confirm"` 控制显示，后端接口继续使??? `permissions.api_route = admin_api_manualConfirmRightsSettlement` ??? `check.permission:admin` 强制鉴权。角色是否拥有该能力仍只??? `role_permissions` 中间表决定???

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
已确??? admin_api_manualConfirmRightsSettlement 位于 api、jwt.auth:admin、sso:admin、check.permission:admin 中间件组???

rg -n "\?\?\?" 本轮触碰文件
无命???
```

??? DB 连接影响的验证：

```text
Test-NetConnection 127.0.0.1 -Port 3307
TcpTestSucceeded: False
```

### 数据库恢复后必须继续执行

```text
php artisan migrate --force
```

并用真实 DB 复核???
- `permissions` 中是否存??? `admin_rights_summary_manual_confirm`，且 `api_route` ??? `admin_api_manualConfirmRightsSettlement`???
- 目标后台角色是否通过 `role_permissions` 授权 `admin_rights_summary_manual_confirm`???
- `rights_settlements.status=0` 记录调用手动确认后是否变??? `status=1`，且 `remark` 写入确认原因???
- `rights_settlements.status=1` 记录再次调用是否返回 `rights_settlement_only_pending`???
- 非当前管理员数据范围内的 `user_id` 是否无法被确认???

## 35. 2026-06-07 后台 zh-CN 语言包可读???修???

本轮继续推进“后端必须支持多语言”的目标，重点修??? `resources/lang/zh-CN/admin.php` 中历史编码错解产生的不可读中文???该文件虽然此前可以通过 `php -l`，但 Laravel 运行时读??? `__('admin.dashboard')`、`__('admin.rights_summary')` ??? key 时返回乱码，导致后台页面标题、接口提示和权限相关消息不可读???

### 本轮维护文件

- `resources/lang/zh-CN/admin.php`：重建后台中文语???包，key 数量??? `resources/lang/en/admin.php` 保持???鑷淬??
- `tests/Feature/AdminZhCnLanguageReadabilityTest.php`：作为本??? RED 测试，约束后台高频中??? key 和常见乱码片段???
- `docs/admin-backend-blade-permission-final-checklist.md`：记录本轮修复???验证结果和仍需后续继续精翻的边界???

### 语言包规???

| 项目 | 规则 |
| --- | --- |
| key 来源 | ??? `resources/lang/en/admin.php` 为权??? key 集合，当??? `zh-CN/admin.php` 宸插榻? 453 ??? key??? |
| 中文覆盖 | 后台登录、菜单???权限???数据范围???资金???导入???权益汇总???持仓汇总???在线用户???产品???礼品???认证审核和风控等重点模块已恢复可读中文??? |
| 临时兜底 | 少量暂未精翻 key 保留英文可读文案，避免再次出现乱码；后续可以按模块继续补全中文精翻??? |
| 编码边界 | 不再通过 PowerShell 管道直接写中文语???包，避免控制台编码链路再次污??? UTF-8 内容??? |

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
dashboard=控制???
risk_ip_detail=异常IP详情
```

### 后续待继???

- 继续按模块把少量英文兜底 key 精翻为中文，但必须保??? `AdminZhCnLanguageReadabilityTest` 通过???
- 前端运行时语?????? `public/js/common/lang/zh-CN.js` 仍需要单独审计，不能把它作为后端中文修复的数据源???
- 当前 MySQL `127.0.0.1:3307` 仍需恢复后再执行真实 DB 迁移落库、权限配置和真实样本接口验证???

## 36. 2026-06-07 后台 Blade UI 外壳测试可读性修???

本轮继续推进“后??? UI 必须使用 Laravel Blade + JS + CSS，并参??? Vben Admin、Vue Naive Admin、Naive UI Admin、Ant Design Pro、Arco Design Pro 的现代后台风格???的目标。先审计 `public/js/common/lang/zh-CN.js`，???过 Node 运行时解析确??? `admin.rights_summary`、`admin.risk_ip_detail` 等后台运行时文案已经是可读中文，未发现常见乱码片段，因此本轮没有强行修改运行时语???包???

随后发现 `tests/Feature/AdminLayoutUiModernizationTest.php` 本身存在不可读中文注释和断言消息。虽然测试可执行，但不符合???所有模块文件及参数必须有详细中文注释和逻辑注释”的要求。本轮已将该测试重写为可读中文注释，并继续保留对后台总布???和公??? CSS 的现代化约束???

### 本轮维护文件

- `tests/Feature/AdminLayoutUiModernizationTest.php`：重写乱码注释和断言消息，继续约束后台统??? Blade 外壳、CSS 设计变量、Layui 组件覆盖和常见乱码风险???
- `tests/Feature/AdminLayoutShellReadabilityTest.php`：新增后台???布???外壳可读性测试，约束 `data-shell-label`、页??? kicker、菜单加载说明???主???/界面切换等静态中文文案???
- `docs/admin-backend-blade-permission-final-checklist.md`：记录本??? UI 外壳测试可读性修复和验证结果???

### UI 外壳约束???

| 约束??? | 文件 | 作用 |
| --- | --- | --- |
| `crm-admin-workbench`、`crm-admin-shell`、`crm-admin-topbar`、`crm-admin-sidebar` | `resources/admin/layui/layouts/app.blade.php` | 保证???有后??? Blade 页面继续挂统???工作台外壳??? |
| `crm-admin-page-head`、`data-shell-label="后台工作???"` | `resources/admin/layui/layouts/app.blade.php` | 保证页面头部与外壳语义可读??? |
| `--admin-radius`、`--admin-sidebar-width`、`--admin-header-height`、`--admin-shadow` | `public/css/admin/style.css` | 保证后台 CSS 继续具备现代管理台设计变量??? |
| `.layui-card`、`.layui-form-pane`、`.layui-table-view`、`.layui-layer`、`.layui-laypage` | `public/css/admin/style.css` | 保证 Layui 组件被统???覆盖为更接近现代中后台的信息密度和视觉风格??? |

### 本轮验证记录

```text
node -e 解析 public/js/common/lang/zh-CN.js
locale=zh-CN
admin.rights_summary=权益汇???
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

### 后续待继???

- 继续逐页审计后台 Blade 页面内部卡片、表单???表格???弹窗和操作区是否符合现代中后台布局密度???
- `public/js/common/lang/zh-CN.js` 当前关键后台运行时文案可读，但后续新??? key 仍必须???过 Node 解析验证，不能只??? PowerShell 终端输出???
- 真实 DB `127.0.0.1:3307` 恢复后，仍需继续验证后台菜单权限、按钮权限和数据范围在真实角色下的页面表现???
## 37. 2026-06-07 后台公共 CSS 中文逻辑注释回归保护
本轮继续推进“所有模块文件及参数必须有详细中文注释和逻辑注释”的目标，重点审计后??? Blade 页面共享样式文件 `public/css/admin/style.css`。该文件承担后台工作台外壳???Layui 卡片、表单???表格???弹窗等公共视觉组件的统???样式职责，因此注释需要解释组件用途和设计边界，不能只依赖样式选择器本身表达意图???

排查时发??? PowerShell `Get-Content` 输出会把部分 UTF-8 中文显示成乱码，但???过专门测试直接读取文件内容后，`public/css/admin/style.css` 中关键中文注释实际已经是可读文本，没有命中常??? UTF-8/GBK 错误解码片段。因此本轮没有改??? CSS 样式行为，也没有重写样式文件，只新增回归测试把这???状???固定下来???

### 本轮维护文件

- `tests/Feature/AdminCssCommentReadabilityTest.php`：新增后台公??? CSS 注释可读性测试，约束 `.crm-admin-main`、Layui 卡片、Layui 表单、Layui 表格、Layui 弹窗等关键注释必须保持可读中文???
- `docs/admin-backend-blade-permission-final-checklist.md`：记录本轮审计结论???验证命令和后续边界???

### 测试约束说明

| 约束??? | 作用 |
| --- | --- |
| `.crm-admin-main：后台业务页面内容容器` | 确认后台业务页面主内容容器注释可读，后续维护者能理解页面留白和最大阅读宽度的用?????? |
| `Layui 卡片：用于列表筛选区、表格区和详情区` | 确认后台常见信息分区组件的注释可读，避免卡片样式被误当成装饰性容器??? |
| `Layui 表单：统???输入框??????择框???日期框` | 确认表单组件统一高度、圆角和聚焦反馈的设计意图可读??? |
| `Layui 表格：后台核心列表组件` | 确认后台核心列表组件的表头???边框???分页和滚动区域说明可读??? |
| `Layui 弹窗：用于新增???编辑???确认等后台操作` | 确认弹窗用于后台关键操作场景的说明可读??? |
| 常见乱码片段黑名??? | 防止后续把中文注释错误写??? `鐨`、`鏉`、`锛`、`鍙` 等不可读片段??? |

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

### 后续待继???

- `routes/web.php`、`routes/admin.php`、`MenuController`、`MenuService` 等历史文件仍存在 PowerShell 输出层面或真实源码层面的中文乱码风险，需要继续分批用测试和运行时解析区分，不能仅凭终端显示判断文件损坏???
- 真实 DB `127.0.0.1:3307` 当前仍未完成本轮连???验证；数据库恢复后，需要继续使用真??? `permissions`、`roles`、`role_permissions`、`user_logins.role_id` 数据验证前后台菜单权限???

## 38. 2026-06-07 默认后台账号与前台菜单角色边界测试增???
本轮继续推进“前后台???有菜单可控，前台分代理商和普通客户两个菜单配置???的目标，重点审??? `database/migrations/2026_06_07_000014_fix_default_admin_and_front_menu_roles.php` ??? `tests/Feature/DefaultAdminAndFrontMenuRoleMigrationTest.php`。该迁移负责写入默认超级管理员???前??? agent/customer 角色、前台菜单权限字典，以及 `role_permissions` 授权关系，是解决 agent 登录??? Layui 菜单为空问题的核心配置来源之??????

本轮没有修改迁移源码，因为当前迁移语法可解析，且源码??? agent/customer 菜单集合已经分离；本轮新增测试约束，直接通过反射读取迁移里的 `agentMenuSlugs()` ??? `customerMenuSlugs()` 私有配置，确认两套前台菜单授权边界不会被后续改坏???

### 本轮维护文件

- `tests/Feature/DefaultAdminAndFrontMenuRoleMigrationTest.php`：新??? `test_front_agent_and_customer_roles_declare_different_menu_scopes`，约束代理商菜单必须包含代理管理和返佣管理，普???客户菜单不能包含代???/返佣专属权限???
- `docs/admin-backend-blade-permission-final-checklist.md`：记录本轮权限边界测试增强???验证结果和真实 DB 待验证边界???

### 权限边界规则

| 角色 | 必须包含 | 必须排除 |
| --- | --- | --- |
| `agent_role` | `front_agent`、`front_agent_sub`、`front_agent_customers`、`front_commission`、`front_commission_rt` 等代???/返佣菜单，以及控制台、个人中心???账户???资金???交易???礼品???公告等通用菜单??? | 无，本角色用于代理商，允许查看代理与返佣菜单??? |
| `customer_role` | `front_dashboard`、`front_profile`、`front_account`、`front_deposit_withdraw`、`front_trading`、`front_gift`、`front_news` 等普通客户???用菜单??? | `front_agent`、`front_agent_sub`、`front_agent_customers`、`front_commission`、`front_commission_rt` 等代???/返佣专属菜单??? |

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\DefaultAdminAndFrontMenuRoleMigrationTest.php
OK (2 tests, 57 assertions)

php -l tests\Feature\DefaultAdminAndFrontMenuRoleMigrationTest.php
No syntax errors detected in tests\Feature\DefaultAdminAndFrontMenuRoleMigrationTest.php

php -l database\migrations\2026_06_07_000014_fix_default_admin_and_front_menu_roles.php
No syntax errors detected in database\migrations\2026_06_07_000014_fix_default_admin_and_front_menu_roles.php
```

### 后续待继???

- 真实 DB `127.0.0.1:3307` 恢复后，必须执行或确??? `php artisan migrate` 已落库，并查??? `roles`、`permissions`、`role_permissions`、`user_logins.role_id`，验??? `agent_role` ??? `customer_role` 授权关系真实存在???
- 用真??? agent 账号登录后，请求 `/api/front/navigation/menus`，确认返回的 `data` 中包含代理菜单；用普通客户账号登录同接口，确认不包含代理和返佣菜单???

## 39. 2026-06-07 前后台菜单中文语???包运行时可读性回归保???
本轮继续推进“后端必须支持多语言”和“前后台???有菜单可控???的目标，重点审??? `MenuService::buildTree()` 依赖??? `resources/lang/zh-CN/menus.php`。菜单服务会通过 `__('menus.' . $menu->slug)` 给前??? Layui、后??? Layui ??? Blade 页面返回菜单标题，因此菜单语???包的可读性会直接影响代理商???普通客户和后台管理员看到的导航文案???

排查??? PowerShell `Get-Content` 会把 `zh-CN/menus.php` 显示成乱码，??? Laravel/PHP 运行时读取结果为可读中文。本轮新增专门测试，约束中文菜单包与英文菜单??? key 对齐，并锁定前后台高频菜单标题，例如 `front_dashboard=控制台`、`front_agent=代理管理`、`admin_system=系统管理`，避免后续误写乱码或??? key???

### 本轮维护文件

- `tests/Feature/MenuZhCnLanguageReadabilityTest.php`：新增菜单中文语???包可读???测试，覆盖 key 对齐、高频菜单中文标题和典型乱码片段黑名单???
- `docs/admin-backend-blade-permission-final-checklist.md`：记录本轮菜单多语言验证、真??? DB 边界和后续实测要求???

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\MenuZhCnLanguageReadabilityTest.php
OK (3 tests, 26 assertions)

php -l tests\Feature\MenuZhCnLanguageReadabilityTest.php
No syntax errors detected in tests\Feature\MenuZhCnLanguageReadabilityTest.php

php artisan tinker --execute="echo __('menus.front_dashboard') . PHP_EOL; echo __('menus.front_agent') . PHP_EOL; echo __('menus.admin_system') . PHP_EOL;"
控制???
代理管理
系统管理
```

### 受真??? DB 影响的验???

```text
Test-NetConnection 127.0.0.1 -Port 3307
TcpTestSucceeded: False

vendor\bin\phpunit tests\Feature\AdminPermissionPlanTest.php tests\Feature\DefaultAdminAndFrontMenuRoleMigrationTest.php
AdminPermissionPlanTest ??? SQLSTATE[HY000] [2002] 目标计算机积极拒绝连接???失败???
DefaultAdminAndFrontMenuRoleMigrationTest 本身此前已单独???过；本次组合命令失败来??? AdminPermissionPlanTest ??? DatabaseTransactions 连接真实 MySQL???
```

### 后续待继???

- 真实 DB `127.0.0.1:3307` 恢复后，???要重新运??? `vendor\bin\phpunit tests\Feature\AdminPermissionPlanTest.php tests\Feature\DefaultAdminAndFrontMenuRoleMigrationTest.php`???
- 真实 DB 可用后，???要登??? agent 和普通客户账号分别请??? `/api/front/navigation/menus`，确认接口返回的 `title` 是可读中文，且菜单集合符??? agent/customer 授权边界???

## 40. 2026-06-07 MenuService 中文逻辑注释与参数说明修???
本轮继续推进“所有模块的文件及参数必须有详细中文注释和???辑注释”的目标，重点维护前后台菜单统一入口 `app/Services/MenuService.php`。该服务负责??? `permissions` 表读取菜单???按角色权限过滤菜单、保留父级菜单容器???构造树形数组，并???过语言包返回可读菜单标题，是前台代理商、普通客户和后台管理员菜单展示的核心服务???

本轮先新??? `tests/Feature/MenuServiceCommentReadabilityTest.php`，确认原文件虽然不是运行时乱码，但参数说明仍不够完整；随后补??? `MenuService` 的中文职责说明???`$guardType`、`$permissionIds`、`$menus`、`$locale` 参数含义、返回结构说明，以及父级菜单保留和多语言标题来源说明。同时移除未使用??? `App\Models\Menu` ??? `Cache` 引用，减少核心服务的无效依赖???

### 本轮维护文件

- `app/Services/MenuService.php`：补齐菜单服务职责???权限过滤???多语言标题和参数返回说明???
- `tests/Feature/MenuServiceCommentReadabilityTest.php`：新增菜单服务注释可读???测试，约束核心中文说明和常见乱码片段黑名单???
- `docs/admin-backend-blade-permission-final-checklist.md`：记录本轮核心服务注释修复和验证结果???

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

### 后续待继???

- `Front\MenuController`、`Admin\MenuController`、`routes/front.php`、`routes/admin.php` 仍属于菜单权限链路，???要继续分批补齐可读中文参数说明和接口边界说明???
- 真实 DB `127.0.0.1:3307` 恢复后，仍需重新验证 `MenuService::getUserMenus()` 在真??? `permissions`、`role_permissions`、`user_logins.role_id` 数据下的 agent/customer 菜单返回???

## 41. 2026-06-07 前后??? MenuController 中文逻辑注释与接口边界修???
本轮继续推进“所有模块的文件及参数必须有详细中文注释和???辑注释”的目标，重点维护菜单权限链路中的两个控制器：`app/Http/Controllers/Front/MenuController.php` ??? `app/Http/Controllers/Admin/MenuController.php`。这两个控制器分别负责前??? agent/customer 菜单树返回???后台管理员菜单树和按钮权限返回，以及后台菜单权限字??? CRUD，是权限配置??? DB ??? Blade/Layui 页面展示的直接接口层???

本轮先新??? `tests/Feature/MenuControllerCommentReadabilityTest.php`，确认原控制器注释缺少角色权限来源???请求参数???返回结构???安全边界和字段映射说明；随后补齐前台控制器??? `role_permissions`、`permissions.id`、`data` 菜单树的说明，并补齐后台控制器对 `data.menus`、`data.permissions`、`check.permission:admin`、`guard_type`、`slug`、CRUD 参数和唯??? slug 生成逻辑的说明???本轮只改注释和无行为说明，不改变接口返回结构和数据库查询???辑???

### 本轮维护文件

- `app/Http/Controllers/Front/MenuController.php`：补齐前台菜单接口职责???角色权限来源???参数含义和返回结构说明???
- `app/Http/Controllers/Admin/MenuController.php`：补齐后台菜单树、按钮权限???菜单管??? CRUD、字段映射和 slug 唯一性说明???
- `tests/Feature/MenuControllerCommentReadabilityTest.php`：新增前后台菜单控制器注释可读???测试，约束核心中文说明和乱码片段黑名单???
- `docs/admin-backend-blade-permission-final-checklist.md`：记录本轮菜单控制器注释修复和验证结果???

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

### 后续待继???

- `routes/front.php` ??? `routes/admin.php` 仍是菜单权限接口注册入口，需要继续补齐可读中文路由分组???参数和中间件边界说明???
- 真实 DB `127.0.0.1:3307` 恢复后，???要实??? `POST /api/front/navigation/menus`、`POST /api/admin/menus`、`POST /api/admin/menuTree` 的真实返回结构和角色过滤效果???

## 42. 2026-06-07 前后台菜??? API 路由中文注释与中间件边界修复
本轮继续推进“前后台???有菜单可控???和“所有模块文件及参数必须有详细中文注释???的目标，重点维??? `routes/front.php` ??? `routes/admin.php`。这两个文件是菜单权限接口的注册入口：前台菜单接口决??? agent/customer ??? Layui/Blade 菜单树从哪里加载，后台菜单接口决定管理员菜单树???按钮权??? slug 和菜单权限字典管理是否处于正确中间件保护下???

本轮先新??? `tests/Feature/MenuRouteCommentReadabilityTest.php`，确认路由文件虽然语法正确，但缺少可读的路由前缀、中间件、菜单接口用途和权限边界说明；随后补??? `routes/front.php` 顶部说明??? `/navigation/menus`、`/menus` 菜单接口注释，并补齐 `routes/admin.php` 顶部说明、后??? JWT/SSO/check.permission:admin 分组说明、`/menus` 当前管理员菜单接口和 `/menuTree` 菜单管理接口说明。本轮只改注释，不改变任何路??? URI、控制器方法或路由名称???

### 本轮维护文件

- `routes/front.php`：补??? `api/front` 路由前缀、前台控制器命名空间、JWT/SSO 保护边界，以及前台菜单接口用途说明???
- `routes/admin.php`：补??? `api/admin` 路由前缀、后台控制器命名空间、JWT/SSO/check.permission:admin 权限边界，以及后台菜单和菜单管理接口用???说明???
- `tests/Feature/MenuRouteCommentReadabilityTest.php`：新增前后台菜单 API 路由注释可读性测试，约束关键中文说明和乱码片段黑名单???
- `docs/admin-backend-blade-permission-final-checklist.md`：记录本轮路由入口注释修复和验证结果???

### 权限边界说明

| 路由 | 路由名称 | 中间件边??? | 用??? |
| --- | --- | --- | --- |
| `POST /api/front/navigation/menus` | `front_api_navigation_menus` | `jwt.auth:user`、`sso:user` | 返回当前前台用户可见??? Layui/Blade 菜单树，用于 agent/customer 两套菜单配置??? |
| `POST /api/front/menus` | `front_api_menus` | `jwt.auth:user`、`sso:user` | 前台菜单兼容接口，复用同?????? `MenuController@userMenus`??? |
| `POST /api/admin/menus` | `admin_api_menus` | `jwt.auth:admin`、`sso:admin`、`check.permission:admin` | 返回 `data.menus` ??? `data.permissions`，供后台 Blade/Layui 渲染菜单和按钮??? |
| `POST /api/admin/menuTree` | `admin_api_menuTree` | `jwt.auth:admin`、`sso:admin`、`check.permission:admin` | 后台菜单管理接口，读取完整菜单树以维??? `permissions` 表菜单权限字典??? |

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

### 后续待继???

- 真实 DB `127.0.0.1:3307` 恢复后，???要使用真??? token 实测 `POST /api/front/navigation/menus`、`POST /api/admin/menus`、`POST /api/admin/menuTree`???
- 继续审计后台 Blade 页面内部按钮权限 `data-permission` 与后??? `permissions.api_route` 是否逐项???鑷淬??

## 43. 2026-06-07 后台 Blade 按钮权限与迁移声明覆盖测???
本轮继续推进“后台不同管理员角色拥有不同菜单权限和按钮权限???的目标，重点审计后??? Blade 页面中的 `data-permission`。这些标识用于前端根??? `/api/admin/menus` 返回??? `data.permissions` 隐藏无权限按钮，但真正安全边界仍在后??? `check.permission:admin` 中间件和 `permissions.api_route` 配置。本轮先做静态覆盖约束，确保页面上出现的每一个按钮权??? slug 都能在迁移源码里找到对应 `permissions.slug` 声明，避免页面写了一个数据库永远不会有的权限???

### 本轮维护文件

- `tests/Feature/AdminBladeButtonPermissionCoverageTest.php`：新增后??? Blade 按钮权限覆盖测试，扫??? `resources/admin/layui/**/*.blade.php` 中的 `data-permission`，并确认每个 slug 都在 `database/migrations/**/*.php` 中以 `slug` 形式声明???
- `docs/admin-backend-blade-permission-final-checklist.md`：记录本轮按钮权限覆盖验证和后续真实 DB 验证边界???

### 覆盖规则

| 规则 | 说明 |
| --- | --- |
| `data-permission` 必须使用 `admin_` 前缀 | 确保后台按钮权限不会混入前台 guard 命名空间??? |
| 每个按钮权限 slug 必须在迁移中声明 | 确保按钮显隐配置有对??? DB 权限字典来源??? |
| 本测试不连接真实 DB | 当前 3307 MySQL 不可用时仍可做静态权限配置质量约束??? |

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

### 后续待继???

- 当前测试确认“页面按??? slug 有迁移声明???，后续仍需继续核对每个按钮 slug 是否拥有正确??? `api_route`，以及对应命名路由是否挂??? `check.permission:admin`???
- 真实 DB `127.0.0.1:3307` 恢复后，???要查??? `permissions` ??? `role_permissions`，确认迁移声明已经落库并按角色授权???

## 44. 2026-06-07 后台 Blade 按钮权限 api_route 与中间件覆盖测试
本轮继续推进“后台按钮权限必须由数据表配置驱动，并由后端再次鉴权”的目标。在??? 43 节已确认 Blade 中的 `data-permission` 都能在迁移源码中找到 `permissions.slug` 声明后，本轮继续向后端安全边界推进：确认每个按钮权限 slug 都绑定了非空 `permissions.api_route`，对??? Laravel 命名路由真实存在，并且该路由挂载 `check.permission:admin`???

本轮新增 `tests/Feature/AdminBladeButtonPermissionRouteCoverageTest.php`。测试从 `resources/admin/layui/**/*.blade.php` 提取?????? `data-permission`，从 `database/migrations/**/*.php` 提取 `slug => api_route` 映射，再通过 Laravel 路由表校验命名路由与中间件???该测试不连接真??? MySQL，因此在 `127.0.0.1:3307` 不可用时仍可验证代码层权限链路???

### 本轮维护文件

- `tests/Feature/AdminBladeButtonPermissionRouteCoverageTest.php`：新增按钮权??? slug ??? `api_route` ??? `check.permission:admin` 的覆盖测试???
- `docs/admin-backend-blade-permission-final-checklist.md`：记录本轮按钮权限后端路由覆盖验证和真实 DB 待验证边界???

### 覆盖规则

| 规则 | 说明 |
| --- | --- |
| 每个 `data-permission` 必须有非??? `api_route` | 按钮代表可执行动作，不能只做前端显隐而没有后端权限入口??? |
| `api_route` 必须是已注册 Laravel 命名路由 | 防止权限表配置指向不存在的接口??? |
| 命名路由必须挂载 `check.permission:admin` | 确保后端接口??? `permissions.api_route` 强制鉴权??? |

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

### 后续待继???

- 当前测试证明代码层的按钮权限、迁移声明???命名路由和中间件一致；真实 DB 恢复后仍???确认这些迁移已执行到 `permissions` 琛ㄣ??
- 后续可继续对后台 JS 操作事件??? `data-permission` 鍋氫竴鑷存??审计，确认表格操作列刷新后仍会重新应用按钮权限显隐???

## 45. 2026-06-07 后台 Layui 表格操作列权限刷新覆???
本轮继续推进“后台不同管理员角色拥有不同菜单权限和按钮权限???的目标，重点补??? Layui 表格重载后的前端按钮显隐链路。后台行内操作按钮使??? `data-permission` 绑定 `permissions.slug`，首次页面加载会??? `layout.js` ??? `/api/admin/menus` 返回??? `data.permissions` 隐藏无权限按钮；??? Layui 表格在搜索???分页???审核???重载后会重新生成操作列 DOM，如果业??? JS 没有再次调用 `CrmAdminPermissions.refresh()`，行内按钮可能重新显示???

### 本轮维护文件

- `tests/Feature/AdminTablePermissionRefreshTest.php`：新增静态回归测试，扫描后台 Blade 中含 `data-permission` ??? Layui 操作列模板，并要求对应业??? JS 调用 `CrmAdminPermissions.refresh()`???
- `public/js/admin/layui/withdrawals/index.js`、`deposits/index.js`、`cancel-applies/index.js`、`commissions/index.js`、`roles/index.js`、`users/index.js`、`vouchers/index.js`、`data-scopes/index.js`、`risk/index.js`：补??? `refreshPermissions()` 辅助函数与表??? `done` 回调调用???

### 逻辑说明

| 规则 | 作用 |
| --- | --- |
| 操作列模板含 `data-permission` | 表示该表格行内按钮受 `permissions.slug` 控制??? |
| 对应 JS 必须调用 `CrmAdminPermissions.refresh()` | 确保搜索、分页???审核???重载后新生成的行内按钮继续按权限隐藏??? |
| 后端仍以 `check.permission:admin` 为最终边??? | 前端隐藏只改善体验，真正安全控制仍由权限中间件和 `permissions.api_route` 完成??? |

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
以上 node --check 命令??? exit 0???
```

### 后续待继???

- 真实 DB `127.0.0.1:3307` 恢复后，仍需要用不同后台管理员角色登录并实测 `/api/admin/menus`、页面按钮显隐和接口二次鉴权???
- 继续按计划审计后??? Blade 页面布局密度、CSS 现代化和多语???运行时文案，尤其是仍未被专门测试覆盖的业务模块???

## 46. 2026-06-07 后台 Blade 业务面板统一现代???
本轮继续推进“后??? UI 参??? Vben Admin、Vue Naive Admin、Naive UI Admin、Ant Design Pro、Arco Design Pro，但必须使用 Laravel Blade + JS + CSS 渲染”的目标。此前后台???外壳和公共 CSS 已经具备现代工作台结构，但很多业务列表页仍使用裸 `layui-card`，页面内部缺少统???业务面板语义类???

本轮将后??? Blade 业务卡片统一补齐??? `layui-card crm-admin-panel`，并在公??? CSS 中新??? `crm-admin-panel` 样式钩子。该类用于承接列表页、表单页、双表格页的统一边框、背景???阴影和溢出边界，方便后续继续按现代中后台的信息密度维护页面，???不???要每个模块单独散落样式???

### 本轮维护文件

- `tests/Feature/AdminBladePagePanelModernizationTest.php`：新增后??? Blade 页面内部布局测试，扫描含 `<table class="layui-hide"` 的业务列表页，要求使??? `crm-admin-panel`???
- `public/css/admin/style.css`：新??? `crm-admin-panel` 业务面板样式和中文???辑注释???
- `resources/admin/layui/**/*.blade.php`：批量为后台业务卡片补齐 `crm-admin-panel` 类，保留原有 Blade 渲染、表格???表单???按钮权限和接口逻辑不变???

### UI 约束说明

| 约束 | 作用 |
| --- | --- |
| ??? Layui 表格的后台业务页必须使用 `crm-admin-panel` | 保证列表、筛选???表格和操作区都有统???业务面板语义??? |
| `crm-admin-panel` 在公??? CSS 中集中维??? | 后续 UI 调整可???过统一类完成，避免每个 Blade 页面重复写样式??? |
| 不改变路由???接口和权限 slug | 本轮只做布局语义和样式钩子补齐，不改变后台权限与业务数据流??? |

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

### 后续待继???

- 继续逐页审计后台 Blade 页面内部筛???区、按钮区、弹窗表单和统计卡片是否有统???结构类与中文逻辑注释???
- 真实 DB `127.0.0.1:3307` 恢复后，仍需结合不同管理员角色实测页面菜单???按钮显隐???数据范围和接口鉴权???

## 47. 2026-06-07 数据范围页面运行时多语言补齐
本轮继续推进“后端也必须支持多语???”和“所有页面必须使??? Laravel Blade + JS + CSS 渲染”的目标，重点修复后台数据范围页面的运行时文案???该页面的静态表单文案已经???过 Blade ??? `__('admin.xxx')` 读取 Laravel 语言包，??? Layui 表格列名、状态徽标???弹窗标题和数据范围标签??? `public/js/admin/layui/data-scopes/index.js` 动???生成，原先仍有硬编码英文，中文界面下会出现英文表头和弹窗标题???

本轮将数据范??? JS 的运行时文案统一改为 `CrmLang.t('admin.xxx')`，并??? `public/js/common/lang/zh-CN.js` ??? `public/js/common/lang/en.js` ??? `admin` 段补齐对??? key。这??? Blade 静???文案???Layui 表格运行时文案和弹窗文案都能跟随当前语言切换???

### 本轮维护文件

- `tests/Feature/AdminDataScopeRuntimeLocalizationTest.php`：新增数据范围运行时多语???测试，约??? JS 不能硬编码英文，并要求中英文 common/lang 文件都具备对??? key???
- `public/js/admin/layui/data-scopes/index.js`：表格列名???状态徽标???弹窗标题???删除确认和范围标签改为 `CrmLang.t` 读取???
- `public/js/common/lang/zh-CN.js`：补齐数据范围运行时中文 key???
- `public/js/common/lang/en.js`：补齐数据范围运行时英文 key???

### 多语??? key 说明

| key | 用??? |
| --- | --- |
| `admin.role_data_scope_role_name`、`admin.scope_type` | 角色数据范围表格列名??? |
| `admin.agent_ids`、`admin.user_ids` | 指定代理和指定用户范围字段??? |
| `admin.admin_id`、`admin.admin_name`、`admin.agent_id`、`admin.agent_name` | 管理员代理绑定表格列名??? |
| `admin.binding_type`、`admin.binding_primary`、`admin.binding_extra` | 代理绑定类型展示??? |
| `admin.scope_all`、`admin.scope_self`、`admin.scope_created`、`admin.scope_agent_tree`、`admin.scope_custom_agents`、`admin.scope_custom_users` | `role_data_scopes.scope_type` 的运行时标签??? |
| `admin.data_scope_saved`、`admin.admin_agent_binding_deleted`、`admin.admin_agent_binding_delete_confirm` | 保存、删除和确认提示??? |
| `admin.role_data_scope_modal_title`、`admin.admin_agent_binding_modal_title` | Layui 弹窗标题??? |

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\AdminDataScopeRuntimeLocalizationTest.php
OK (2 tests, 86 assertions)

node --check public\js\admin\layui\data-scopes\index.js
node --check public\js\common\lang\zh-CN.js
node --check public\js\common\lang\en.js
以上 node --check 命令??? exit 0???

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
ERRORS: 4，均??? SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接???

vendor\bin\phpunit tests\Feature\AdminDataScopeControllerWiringTest.php
ERRORS: 4，均??? SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接???
```

以上失败来自真实 MySQL `127.0.0.1:3307` 不可连接，不是本轮运行时多语???静???契约失败???数据库恢复后仍???重跑这两个数据范围测试，并使用真实管理员角色验证页面文案、数据范围过滤和接口鉴权???

### 后续待继???

- 继续扫描其他后台 Layui JS 是否仍有会显示给用户的硬编码英文或乱码文案???
- 继续补齐后台业务模块的中文参数注释，尤其是弹窗表单???筛选参数和 JS helper 函数???

## 48. 2026-06-07 凭证审核图片预览运行时多语言修复
本轮继续推进“后???/后台必须支持多语???”的目标，重点审计后台凭证审核页面的运行时图片预览文案???`resources/admin/layui/vouchers/index.blade.php` 的静态页面文案已经???过 Laravel Blade 语言包渲染，??? `public/js/admin/layui/vouchers/index.js` 动???生成图片查看链接和 Layui 预览弹窗标题，原先保留了 `|| 'View'` ??? `|| 'Voucher Images'` 英文兜底。中文后台界面下，如果运行时语言包加载正常，不应再落回英文兜底???

本轮新增专门测试并移除英文兜底，凭证图片链接文案统一读取 `CrmLang.t('common.view')`，弹窗标题统???读取 `CrmLang.t('front.voucher_images')`。中英文运行时语???包中这两??? key 已存在，因此本轮不新增重复语??? key???

### 本轮维护文件

- `tests/Feature/AdminVoucherRuntimeLocalizationTest.php`：新增凭证审核运行时多语???测试，约束图片预览链接和弹窗标题不能写死英文兜底???
- `public/js/admin/layui/vouchers/index.js`：移??? `View` ??? `Voucher Images` 英文兜底，并补充图片链接文案来源的中文???辑注释???

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

### 后续待继???

- 继续扫描其他后台 Layui JS 中会显示给用户的英文兜底文案，优先处理弹窗标题???确认提示???状态标签和表格列名???
- 真实 DB `127.0.0.1:3307` 恢复后，仍需用真实管理员角色打开凭证审核页，验证图片预览文案、按钮权限显隐和审核接口鉴权???
## 49. 2026-06-07 数据范围 JS 中文注释可读性修???

本轮继续推进“所有模块的文件及参数必须有详细中文注释和???辑注释”的目标，重点修??? `public/js/admin/layui/data-scopes/index.js` 中残留的中文注释乱码。数据范围页面涉及后台角色数据范围???管理员代理绑定、Layui 表格重载后的按钮权限刷新等核心权限???辑，注释必须能清楚说明参数来源和业务边界，不能出现 `�`、`锟` 等不可读字符???

本轮只修复注释可读???，不改变接口地???、表格列、提交参数???权??? slug、数据范围计算或 Layui 交互逻辑???

### 本轮维护文件

- `tests/Feature/AdminDataScopeJsCommentReadabilityTest.php`：新增数据范??? JS 中文注释可读性测试，要求关键注释片段存在，并拦截常见乱码片段???
- `public/js/admin/layui/data-scopes/index.js`：修复角色数据范围弹窗???管理员代理绑定弹窗和相关参数说明中的乱码注释???

### 注释覆盖???

| 注释片段 | 功能作用 |
| --- | --- |
| `表格列名、状态徽标和弹窗标题都从运行时语???包读取` | 说明数据范围页面的运行时文案来源，避免后台中文界面出现硬编码英文??? |
| `Layui 表格重载会重新生成操作列按钮` | 说明为什么表??? `done` 后必须再次执??? `CrmAdminPermissions.refresh()`??? |
| `row.data_scope 是后??? role_data_scopes 关联配置` | 说明角色数据范围弹窗参数来自后端角色关联数据??? |
| `新增时传入空字段对象` | 说明管理员代理绑定弹窗新增场景的参数边界??? |
| `role_data_scopes.scope_type` | 说明范围标签值与后端数据范围表字段对应??? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminDataScopeJsCommentReadabilityTest.php
RED: 2 failures
- 缺少 `row.data_scope 是后??? role_data_scopes 关联配置`
- 仍包含乱码片??? `�`

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

本轮是静态注释可读???修复，不需要连接数据库。真??? DB `127.0.0.1:3307` 当前仍需恢复后再继续执行数据范围管理相关的数据库集成测试，并用不同后台管理员角色实测菜单、按钮???数据范围和接口鉴权???
## 50. 2026-06-07 后台公共运行时提示多语言兜底修复

本轮继续推进“后???/后台必须支持多语???”和“所有页面必须使??? Laravel Blade + JS + CSS 渲染”的目标，重点处理后台公??? Layui 脚本中的运行时提示???`public/js/admin/layui/common.js` 负责旧版后台 AJAX 登录过期处理，`public/js/admin/layui/layout.js` 负责后台总布???、主题切换???菜单和按钮权限刷新；这两个文件的提示会在所有后台页面扩散，因此不能保留不可切换的英文兜底???

本轮移除 `Session expired` ??? `Theme applied` 英文兜底，登录过期提示统???读取旧版后台 i18n ??? `login_expired`，主题切换提示统???读取 `CrmLang.t('common.success')`。本轮只调整运行时提示文案来源，不改变登录跳转???菜单加载???主题切换???权限刷新或接口调用逻辑???

### 本轮维护文件

- `tests/Feature/AdminCommonRuntimeLocalizationTest.php`：新增后台公共运行时多语???测试，约束公共脚本不能保留英文兜底，并确??? `login_expired` 存在于中英文 admin i18n 文件???
- `public/js/admin/layui/common.js`：移除登录过期提示的 `Session expired` 英文兜底???
- `public/js/admin/layui/layout.js`：移除主题切换提示的 `Theme applied` 英文兜底???

### 多语???边界说明

| 位置 | 文案来源 | 功能作用 |
| --- | --- | --- |
| `common.js` 登录过期处理 | `CRM.t('login_expired')` | 处理 `4001`、`4002`、`4003`、`4004` 后提示用户重新登录??? |
| `layout.js` 主题切换提示 | `CrmLang.t('common.success')` | 后台总布???切换皮肤后显示统???成功提示??? |
| `public/js/admin/i18n/zh-CN.js` | `login_expired` | 旧版 admin common 模块读取的中文登录过期提示??? |
| `public/js/admin/i18n/en.js` | `login_expired` | 旧版 admin common 模块读取的英文登录过期提示??? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminCommonRuntimeLocalizationTest.php
RED 1: 后台登录过期提示仍包??? `Session expired`

vendor\bin\phpunit tests\Feature\AdminCommonRuntimeLocalizationTest.php
RED 2: 后台主题切换提示仍包??? `Theme applied`

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

### 后续待继???

- 继续扫描后台公共 JS 与业??? JS 中其他用户可见英文兜底，尤其是弹窗标题???确认提示???错误提示和动???表格列名???
- `public/js/admin/layui/common.js` ??? `layout.js` 仍含历史编码注释问题，后续应在更小范围内继续用测试驱动修复可读中文注释，避免???次???大改影响旧后台脚本???
## 51. 2026-06-07 后台菜单管理 JS 参数注释可读性修???

本轮继续推进“前后台???有菜单可控???和“所有模块的文件及参数必须有详细中文注释”的目标，重点维??? `public/js/admin/layui/menus/index.js`。后台菜单管理页通过 `POST /api/admin/menuTree` 读取 `permissions` 表中的菜单权限字典，再???过新增、编辑???删除接口维护菜单节点；它是后台菜单、页面入口和按钮权限配置闭环中的核心页面???

本轮只修??? `showModal(data)` 附近的中文???辑注释，明确说??? tree 节点来自 `permissions` 琛ㄣ??弹窗表单回??? `route`、`icon`、`name`，以??? `guard_type` 用于区分 `admin/front` 菜单命名空间。不改变菜单树加载???表单字段???接口地???、权??? slug ??? Layui 交互逻辑???

### 本轮维护文件

- `tests/Feature/AdminMenuJsCommentReadabilityTest.php`：新增菜单管??? JS 注释可读性测试，约束菜单弹窗参数说明和乱码片段黑名单???
- `public/js/admin/layui/menus/index.js`：修复菜单弹窗参数注释，补充 `guard_type` 命名空间边界说明???

### 注释覆盖???

| 注释片段 | 功能作用 |
| --- | --- |
| `tree 节点来自 permissions 表` | 说明菜单管理页维护的是权限表中的菜单字典，???不是独立静态菜单??? |
| `弹窗表单只暴露常用菜单字段` | 说明弹窗表单的字段边界，避免把权限表???有字段直接暴露给页面??? |
| `route、icon、name` | 说明菜单弹窗提交后会回写的核心菜单展示字段??? |
| `guard_type 用于区分 admin/front 菜单命名空间` | 说明前后台菜单权限不能混用，符合前后台菜单均由数据表配置控制的方案??? |

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

本轮是静态注释可读???修复，不连接真实数据库。真??? DB `127.0.0.1:3307` 恢复后，仍需要???过后台菜单管理页实测新增???编辑???删除菜单节点，并确??? `permissions.guard_type`、`route`、`api_route` 与角色授权数据真实写入后能影响菜单显示和接口鉴权???
## 52. 2026-06-07 后台公共 common.js 中文注释 UTF-8 修复

本轮继续推进“所有模块的文件及参数必须有详细中文注释和???辑注释”的目标，重点维??? `public/js/admin/layui/common.js`。该文件是旧版后??? Layui 页面共用模块，负责路由生成???Token 读写、AJAX 请求封装、登录过期处理和旧版 admin i18n 语言包加载；如果注释继续乱码，后续维护登录页、旧后台页面和公共请求???辑时很容易误解参数边界???

本轮??? `common.js` 重写??? UTF-8 可读中文注释，保留原??? `CRM.t`、`getToken`、`setToken`、`removeToken`、`route`、`ajax`、`applyTranslations`、`switchLang`、`getLang`、`initLang` 接口。业务???辑保持不变：登录过期响应码仍会清理 Token 并跳转后台登录页，旧页面仍从 `public/js/admin/i18n/{lang}.js` 加载语言包???

### 本轮维护文件

- `tests/Feature/AdminCommonJsCommentReadabilityTest.php`：新增后台公??? JS 中文注释可读性测试，约束核心注释片段和乱码黑名单???
- `public/js/admin/layui/common.js`：重写中文注释，说明路由、Token、AJAX、登录过期???data-translate 和旧??? i18n 加载逻辑???

### 注释覆盖???

| 注释片段 | 功能作用 |
| --- | --- |
| `后台 Layui 公共模块` | 说明文件是旧版后??? Layui 公共模块，不是单???页面脚本??? |
| `通过 PHP 导出??? Laravel 路由名称生成 URL` | 说明 `routeUrl(name, params, fallback)` 的参数边界??? |
| `admin_token 是布?????? CrmAjax 使用的统???键名` | 说明新旧 Token 键名兼容关系??? |
| `登录过期响应码会清理 Token 并跳回后台登录页` | 说明 `4001`、`4002`、`4003`、`4004` 的公共处理边界??? |
| `??? data-translate 属???应用旧版后台语???包` | 说明旧版后台页面的运行时多语???替换入口??? |
| `??? public/js/admin/i18n 加载旧版后台语言包` | 说明旧版 admin i18n 文件来源??? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminCommonJsCommentReadabilityTest.php
RED: 1 failure
- 缺少 `后台 Layui 公共模块` 可读中文注释，common.js 仍显示历史乱码注???

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

### 后续待继???

- 继续分批修复 `public/js/admin/layui/layout.js` 等后台公共脚本中的历史乱码注释???
- 真实 DB `127.0.0.1:3307` 恢复后，仍需要用真实后台账号验证登录过期跳转、旧??? i18n 切换和后台菜单加载流程???
## 53. 2026-06-07 后台布局 layout.js 权限与菜单注??? UTF-8 修复

本轮继续推进“后??? UI 必须使用 Laravel Blade + JS + CSS 渲染”???前后台???有菜单可控???和“所有模块文件及参数必须有详细中文注释???的目标，重点维??? `public/js/admin/layui/layout.js`。该文件是后??? Blade/Layui 外壳的运行时核心，负??? `/api/admin/menus` 菜单加载、`permissions.slug` 按钮显隐、语???切换、主题切换???侧边栏交互和???出登录???

本轮??? `layout.js` 重写??? UTF-8 可读中文注释，明确前端按钮显隐只负责体验，真正安全边界仍??? `check.permission:admin` 中间件与 `permissions.api_route`。业务???辑保持不变：仍??? `/api/admin/menus` 读取菜单树和按钮权限，仍缓存 `crm_admin_permissions`，仍通过 `CrmAdminPermissions.refresh()` ??? `MutationObserver` 处理 Layui 表格异步工具栏???

### 本轮维护文件

- `tests/Feature/AdminLayoutJsCommentReadabilityTest.php`：新增后台布??? JS 中文注释可读性测试，约束权限边界、菜单权限缓存???按钮显隐和 DOM 监听说明???
- `public/js/admin/layui/layout.js`：重写布???外壳中文注释，保留菜单???权限???主题???侧栏和???出登录???辑不变???

### 注释覆盖???

| 注释片段 | 功能作用 |
| --- | --- |
| `后台布局壳层的接口和跳转都从 PHP 注入??? Laravel 路由清单读取` | 说明 `routeUrl` 的路由来源，避免后台路径硬编码??? |
| `后台按钮权限控制器只负责前端显示体验` | 明确 `CrmAdminPermissions` 不是安全边界??? |
| `真正安全边界仍是 check.permission:admin 中间件` | 对齐计划中的后端二次鉴权要求??? |
| `slug 对应 permissions.slug` | 说明按钮 `data-permission` 与权限表字段的对应关系??? |
| `菜单接口返回后会覆盖该缓存` | 说明 `crm_admin_permissions` 只是减少闪烁的临时缓存??? |
| `接口权限必须继续依赖后端中间件校验` | 防止后续误把隐藏按钮当成接口鉴权??? |
| `Layui table 工具栏由模板异步渲染` | 说明为什么需要监??? DOM 变化后重新应用按钮权限??? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminLayoutJsCommentReadabilityTest.php
RED: 1 failure
- 缺少 `后台布局壳层的接口和跳转都从 PHP 注入??? Laravel 路由清单读取` 可读中文注释，layout.js 仍显示历史乱码注???

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

### 后续待继???

- 继续按模块扫描后台业??? JS 中的历史乱码注释和用户可见英文兜底文案???
- 真实 DB `127.0.0.1:3307` 恢复后，仍需使用不同后台管理员角色实??? `/api/admin/menus`、侧边菜单???按钮显隐???表格重载后的权限刷新和接口二次鉴权???

## 54. 2026-06-07 VoucherInfo 模型中文注释??? UTF-8 编码修复

本轮继续推进“所有模块的文件及参数必须有详细中文注释和???辑注释”的目标，重点修??? `app/Models/VoucherInfo.php`。该模型??? `voucher_infos` 表的 Eloquent 映射，服务于前台凭证上传和后台凭证审核链路；原文件存??? UTF-16 空字节和历史中文乱码，导致源码注释不可读，也不利于后续维护凭证图片???用户关联和审核逻辑???

本轮只修复编码和注释，不改变业务行为：`protected $table = 'voucher_infos'` 保持不变，`user()` 仍然通过 `belongsTo(UserInfo::class, 'user_id', 'user_id')` 关联前台用户资料???

### 本轮维护文件

- `tests/Feature/VoucherInfoCommentReadabilityTest.php`：新增模型注释可读???测试，约束 UTF-8 编码、中文职责说明???表名说明???`$table` 参数含义、`user_id` ??? `images` 字段含义、`user()` 关联说明???
- `app/Models/VoucherInfo.php`：重写为 UTF-8 文件，补齐凭证信息模型职责???`voucher_infos` 表名、`user_id`、`images`、`$table` ??? `user()` 关联参数的中文???辑注释???

### 注释覆盖???

| 注释片段 | 功能作用 |
| --- | --- |
| `凭证信息模型 | Voucher Info Model` | 明确模型职责和英文辅助名称??? |
| `管理用户上传的交易凭证???认证凭证或后台审核凭证` | 说明该模型服务的业务范围??? |
| `数据表名称：voucher_infos 表` | 明确 Eloquent 映射的数据表来源??? |
| `$table：当前模型映射的数据表名称` | 说明模型参数 `$table` 的含义和作用??? |
| `user_id 表示上传凭证的前台用??? ID` | 说明凭证与前台用户的业务外键??? |
| `images 存储凭证图片路径??? JSON 图片列表` | 说明凭证图片字段的存储边界??? |
| `user() 关联上传凭证的前台用户资料` | 说明模型关联方法的业务意义??? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\VoucherInfoCommentReadabilityTest.php
RED: 2 failures
- 缺少 `凭证信息模型 | Voucher Info Model` 可读中文逻辑注释???
- 文件包含 UTF-16 空字??? `\0`，证明原文件存在错误编码片段???
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

本轮为模型源码编码和中文注释修复，不连接真实数据库，也不修改凭证审核、图片解析或用户关联查询逻辑。真??? DB `127.0.0.1:3307` 恢复后，仍需结合后台凭证审核页面和前台凭证上传接口做端到端数据验证???

## 55. 2026-06-07 后台登录 login.js 中文注释??? UTF-8 编码修复

本轮继续推进后台 Blade + Layui 登录入口的可维护性，重点修复 `public/js/admin/layui/auth/login.js`。该脚本负责后台管理员登录表单提交???`/api/admin/login` 请求、JWT Token 缓存、登录成功跳转和登录页语???切换；原文件存在??? UTF-8 字节和历史乱码注释，影响后续排查默认超级管理员登录???后台菜单加载和多语???切换问题???

本轮只修复编码和中文逻辑注释，不改变登录业务行为：仍然提??? `username` ??? `password`，仍然在 `res.code === 1000` 时保??? `res.data.access_token`，仍然跳??? `CRM.route('admin_page_dashboard')`，仍然???过 `CRM.switchLang(lang)` 切换旧版 admin i18n???

### 本轮维护文件

- `tests/Feature/AdminLoginJsCommentReadabilityTest.php`：新增后台登??? JS 注释可读性测试，约束登录脚本职责、`username`、`password`、`access_token`、`admin_page_dashboard` 和语???切换说明???
- `public/js/admin/layui/auth/login.js`：重写为 UTF-8 文件，补齐后台登录参数???Token 保存、登录成功跳转和语言切换的中文???辑注释???

### 注释覆盖???

| 注释片段 | 功能作用 |
| --- | --- |
| `后台登录页脚本` | 明确文件是后台登录页入口脚本??? |
| `username 表示后台管理员登录名` | 对齐 `AuthController@login` 的请求参数??? |
| `password 表示后台管理员登录密码` | 说明登录密码参数边界??? |
| `access_token 是后台登录接口返回的 JWT` | 说明 Token 来源和后续后台接口复用关系??? |
| `admin_page_dashboard 是登录成功后的后台首页路由` | 说明登录成功跳转依赖后端命名路由??? |
| `切换后台登录页语???` | 说明登录页运行时多语???入口??? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminLoginJsCommentReadabilityTest.php
RED: 1 failure
- 缺少 `后台登录页脚本` 可读中文逻辑注释，原脚本仍保留历史乱码注释???
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

本轮未连接真实数据库，因此没有验??? `superadmin / Admin@123456` 的真实登录响应???真??? DB `127.0.0.1:3307` 恢复后，仍需通过 `/admin/login` 页面实测登录、Token 写入、跳??? `/admin/dashboard` 和后台菜单加载???

## 56. 2026-06-07 后台管理员账??? admins/index.js 中文注释与权限边界修???

本轮继续推进后台 Blade + Layui 页面源码可维护???，重点修复 `public/js/admin/layui/admins/index.js`。该脚本负责后台管理员账号列表???新增???编辑???删除???编辑时密码留空不更新，以及 Layui 表格重载后的按钮权限刷新；管理员账号属于高敏后台资源，页面入口显隐必须继续按 `permissions.slug` 控制，接口安全边界仍由后??? `check.permission:admin` 中间件保证???

本轮只修复编码和中文逻辑注释，不改变业务行为：仍然读??? `/api/admin/adminList`，仍然调??? `/api/admin/createAdmin`、`/api/admin/updateAdmin/{id}`、`/api/admin/deleteAdmin/{id}`，仍然在编辑管理员且 `password` 为空时删除该字段，避免覆盖旧密码???

### 本轮维护文件

- `tests/Feature/AdminAdminsJsCommentReadabilityTest.php`：新增后台管理员账号 JS 注释可读性测试，约束账号安全边界、`username`、`password`、`id`、`permissions.slug` 和权限刷新说明???
- `public/js/admin/layui/admins/index.js`：重写为 UTF-8 文件，补齐管理员账号列表、表单弹窗参数???密码留空边界和 `CrmAdminPermissions.refresh()` 权限刷新注释???

### 注释覆盖???

| 注释片段 | 功能作用 |
| --- | --- |
| `管理员账号列表` | 明确脚本维护后台管理员账号表格??? |
| `管理员账号属于高敏后台资源` | 标注该模块属于敏感后台资源??? |
| `username 表示管理员登录名` | 对齐后台登录读取??? `admins.username`??? |
| `password 留空表示编辑时不修改旧密码` | 防止编辑时误提交空密码覆盖旧密码??? |
| `id 为空表示新增管理员` | 说明新增/编辑分支判断依据??? |
| `重新应用按钮权限` | 说明表格重载后需要重新处理按钮显隐??? |
| `permissions.slug` | 说明前端按钮权限与权限表 slug 的对应关系??? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminAdminsJsCommentReadabilityTest.php
RED: 1 failure
- 缺少 `管理员账号属于高敏后台资源` 可读中文逻辑注释，原脚本仍保留历史乱码注释???
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

本轮未连接真实数据库，因此没有验证管理员账号新增、编辑???删除接口的真实写入结果。真??? DB `127.0.0.1:3307` 恢复后，仍需使用超级管理员登录后台，进入 `/admin/admins` 页面实测账号列表、新增管理员、编辑管理员、密码留空不更新、删除管理员和按钮权限显隐???

## 57. 2026-06-07 后台权限字典 permissions/index.js 中文注释与权限树边界修复

本轮继续推进后台权限配置链路的可维护性，重点修复 `public/js/admin/layui/permissions/index.js`。该脚本负责读取 `/api/admin/permissionTree`，按 `guard_type=admin` 渲染后台权限树，用于核对 `permissions` 表中的后台菜单???按钮和接口权限字典是否完整；该页面只做权限树预览，角色授权仍在角色模块通过 `assignPermissions` 完成???

本轮只修复编码和中文逻辑注释，不改变业务行为：仍然请??? `/api/admin/permissionTree`，仍然传??? `guard_type: 'admin'`，仍然用 Layui `tree.render` 渲染 `#permissionTree`，仍然???过 `normalizeTree` 把后端节点转换为 Layui tree ??????字段???

### 本轮维护文件

- `tests/Feature/AdminPermissionsJsCommentReadabilityTest.php`：新增权限字??? JS 注释可读性测试，约束权限树来源???`guard_type` 参数含义、权限预览边界???角色授权边界和 `normalizeTree` 参数说明???
- `public/js/admin/layui/permissions/index.js`：重写为 UTF-8 文件，补齐权限树加载、`permissions` 表来源???前后台守卫隔离、角色授权归属和树节点字段转换的中文逻辑注释???

### 注释覆盖???

| 注释片段 | 功能作用 |
| --- | --- |
| `加载后台权限树` | 明确页面读取后台权限字典??? |
| `guard_type 表示权限???属守卫` | 说明请求参数用于区分 admin/front 权限??? |
| `permissions 表中的后台菜单???按钮和接口权限字典` | 对齐“鉴权数据必须从数据表配置得到???的目标??? |
| `当前页面只做权限树预览` | 明确该页面不直接保存角色授权??? |
| `角色授权在角色模块???过 assignPermissions 完成` | 说明授权写入入口归属角色模块??? |
| `normalizeTree 将后端权限节点转换为 Layui tree ???要的字段` | 说明树形数据转换函数的职责??? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminPermissionsJsCommentReadabilityTest.php
RED: 1 failure
- 缺少 `guard_type 表示权限???属守卫` 可读中文逻辑注释，原脚本仍保留历史乱码注释???
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

本轮未连接真实数据库，因此没有验??? `/api/admin/permissionTree` 的真实返回数据???真??? DB `127.0.0.1:3307` 恢复后，仍需使用超级管理员进??? `/admin/permissions` 页面，确认权限树能读??? `permissions.guard_type=admin` 的菜单???按钮和接口权限配置，并确认角色模块??? `assignPermissions` 能把授权写入 `role_permissions`???

## 58. 2026-06-07 后台代理等级 agent-levels/index.js 中文注释与佣金字段边界修???

本轮继续推进后台配置页面源码可维护???，重点修复 `public/js/admin/layui/agent-levels/index.js`。该脚本负责代理等级列表、新增???编辑???删除和佣金比例字段维护；代理等级配置会影响前台代理体系、等级确认和返佣计算，因此页面注释必须明确真实字段来源???表单参数含义和权限刷新边界???

本轮只修复编码和中文逻辑注释，不改变业务行为：仍然读??? `/api/admin/agentLevelList`，仍然调??? `/api/admin/createAgentLevel`、`/api/admin/updateAgentLevel2/{id}`、`/api/admin/deleteAgentLevel/{id}`，仍然使??? `level_code`/`level` 兼容旧表单字段，仍然在表格重载后调用 `CrmAdminPermissions.refresh()`???

### 本轮维护文件

- `tests/Feature/AdminAgentLevelsJsCommentReadabilityTest.php`：新增代理等??? JS 注释可读性测试，约束 `level_code`、`max_commission`、`min_commission`、`user_commission`、新???/编辑分支??? `permissions.slug` 权限刷新说明???
- `public/js/admin/layui/agent-levels/index.js`：重写为 UTF-8 文件，补齐代理等级字段???佣金参数???表单兼容字段和按钮权限刷新边界的中文???辑注释???

### 注释覆盖???

| 注释片段 | 功能作用 |
| --- | --- |
| `代理等级参数由后端模型定义` | 明确页面字段来源于后端真实数据结构??? |
| `level_code 表示等级编码` | 说明等级编码与旧表单 `level` 字段兼容关系??? |
| `max_commission 表示该等级允许的???大佣金比例` | 说明???大佣金字段含义??? |
| `min_commission 表示该等级允许的???小佣金比例` | 说明???小佣金字段含义??? |
| `user_commission 表示客户侧佣金比例` | 说明客户侧佣金字段含义及旧字段兜底关系??? |
| `id 为空表示新增代理等级` | 说明新增/编辑接口分支依据??? |
| `重新应用按钮权限` | 说明表格重载后必须刷新按钮显隐??? |
| `permissions.slug` | 说明按钮显隐来自权限??? slug??? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminAgentLevelsJsCommentReadabilityTest.php
RED: 1 failure
- 缺少 `level_code 表示等级编码` 可读中文逻辑注释，原脚本仍保留历史乱码注释???
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

本轮未连接真实数据库，因此没有验证代理等级新增???编辑???删除接口的真实写入结果。真??? DB `127.0.0.1:3307` 恢复后，仍需使用超级管理员进??? `/admin/agent-levels` 页面，实测代理等级列表???创建等级???修改佣金比例???删除等级，以及表格重载后的按钮权限显隐???

## 59. 2026-06-07 后台支付通道 channels/index.js 中文注释与扩展配置边界修???

本轮继续推进后台配置页面源码可维护???，重点修复 `public/js/admin/layui/channels/index.js`。该脚本负责支付通道列表、状态筛选???新增???编辑???删除和通道扩展配置格式化；支付通道会影响前台入金???出金和支付渠道展示，因此必须明确???道编码、汇率???启用状态和扩展 JSON 配置的含义???

本轮只修复编码和中文逻辑注释，不改变业务行为：仍然读??? `/api/admin/channelList`，仍然调??? `/api/admin/createChannel`、`/api/admin/updateChannel/{id}`、`/api/admin/deleteChannel/{id}`，仍然用 `normalizeConfig()` 防止对象配置直接显示??? `[object Object]`，仍然在表格重载后调??? `CrmAdminPermissions.refresh()`???

### 本轮维护文件

- `tests/Feature/AdminChannelsJsCommentReadabilityTest.php`：新增支付???道 JS 注释可读性测试，约束 `status`、`channel_code`、`exchange_rate`、`is_enabled`、`config`、新???/编辑分支、`normalizeConfig` ??? `permissions.slug` 权限刷新说明???
- `public/js/admin/layui/channels/index.js`：重写为 UTF-8 文件，补齐支付???道字段、启用状态筛选???扩展配置格式化和按钮权限刷新边界的中文逻辑注释???

### 注释覆盖???

| 注释片段 | 功能作用 |
| --- | --- |
| `status 表示支付通道启用状???筛选` | 说明列表筛???参数含义??? |
| `channel_code 表示支付通道编码` | 说明后端识别支付网关或???道实现的关键字段??? |
| `exchange_rate 表示该???道使用的汇率` | 说明入金/出金展示和换算读取的配置??? |
| `is_enabled 表示通道是否启用` | 说明通道启停状???字段??? |
| `config 表示通道扩展配置` | 说明商户号???网关参数和回调配置??? JSON 文本边界??? |
| `id 为空表示新增支付通道` | 说明新增/编辑接口分支依据??? |
| `normalizeConfig 将???道扩展配置转换??? textarea 文本` | 说明对象配置格式化目的??? |
| `重新应用按钮权限` | 说明表格重载后必须刷新按钮显隐??? |
| `permissions.slug` | 说明按钮显隐来自权限??? slug??? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminChannelsJsCommentReadabilityTest.php
RED: 1 failure
- 缺少 `status 表示支付通道启用状???筛选` 可读中文逻辑注释，原脚本仍保留历史乱码注释???
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

本轮未连接真实数据库，因此没有验证支付???道新增、编辑???删除接口的真实写入结果。真??? DB `127.0.0.1:3307` 恢复后，仍需使用超级管理员进??? `/admin/channels` 页面，实测支付???道列表、状态筛选???创建???道、修改汇率和扩展配置、删除???道，以及表格重载后的按钮权限显隐???


## 60. 2026-06-07 后台入金审核 deposits/index.js 中文注释与审核权限边界修???

本轮继续推进后台资金审核页面的源码可维护性，重点修复 `public/js/admin/layui/deposits/index.js`。该脚本负责后台入金审核列表、用??? ID 和状态筛选???入金金额展示???审核???过、审核驳回，以及 Layui 表格重载后的按钮权限刷新；入金审核直接影响客户资金数据，因此页面参数、审核动作和记录主键必须有清晰中文???辑注释???

本轮只修复编码和中文逻辑注释，不改变业务行为：仍然读??? `POST /api/admin/depositList`，仍然???过 `POST /api/admin/depositApprove` 审核通过入金记录，仍然???过 `POST /api/admin/depositReject` 驳回入金记录，仍然在表格 `done` 回调中执??? `CrmAdminPermissions.refresh()`，按钮显隐继续由 `permissions.slug` 和当前管理员角色授权控制???

### 本轮维护文件

- `tests/Feature/AdminDepositsJsCommentReadabilityTest.php`：新增入金审??? JS 注释可读性测试，约束入金审核列表、`user_id`、`status`、`amount`、`approve`、`reject`、`id`、`permissions.slug` 和乱码黑名单???
- `public/js/admin/layui/deposits/index.js`：重写为 UTF-8 可读中文注释，补齐入金审核列表职责???搜索参数含义???入金金额展示边界???审核动作含义???记录主键用途和按钮权限刷新说明???

### 注释覆盖???

| 注释片段 | 功能作用 |
| --- | --- |
| `入金审核列表` | 说明列表数据来自 `/api/admin/depositList`，并由后端按管理员角色和数据范围过滤??? |
| `user_id 表示入金???属用户` | 说明筛???参数对应入金记录中的业务用??? ID??? |
| `status 表示入金审核状???` | 说明状???筛选参数含义，空字符串表示不限制状态??? |
| `amount 表示入金申请金额` | 说明金额字段只做列表展示，真实审核仍由后端接口校验??? |
| `approve 表示审核通过入金记录` | 说明操作列???过按钮对应审核通过接口??? |
| `reject 表示驳回入金记录` | 说明操作列驳回按钮对应审核驳回接口??? |
| `id 表示入金记录主键` | 说明审核接口参数用于后端读取记录并校验数据范围??? |
| `重新应用按钮权限` | 说明 Layui 表格重载后必须重新隐藏无权限按钮??? |
| `permissions.slug` | 说明按钮显隐来自权限??? slug 与角色授权配置??? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminDepositsJsCommentReadabilityTest.php
RED: 1 failure
- 缺少 `入金审核列表` 可读中文逻辑注释，原脚本仍保留历史乱码注释???
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

本轮未连接真实数据库，因此没有验??? `deposit_records` 的真实列表数据???审核???过写入结果、审核驳回写入结果和不同管理员数据范围过滤效果???真??? DB `127.0.0.1:3307` 恢复后，仍需使用超级管理员进??? `/admin/deposits` 页面，实测入金列表???`user_id` 筛??????`status` 筛??????审核???过、审核驳回，以及低权限管理员??? `role_permissions` 和数据范围配置下的按钮显隐与数据隔离???


## 61. 2026-06-07 后台出金审核 withdrawals Blade/JS 中文注释与状态流转边界修???

本轮继续推进后台资金审核页面的源码可维护性，重点修复 `resources/admin/layui/withdrawals/index.blade.php` ??? `public/js/admin/layui/withdrawals/index.js`。该模块负责出金审核页面结构、筛选表单???处理按钮???出金列表加载???状态筛选???标记处理中、完成出金???拒绝出金，以及 Layui 表格重载后的按钮权限刷新；出金审核直接影响客户资金安全，因此页面注释必须说明参数含义、接口来源???状态动作和后端权限边界???

本轮只修复编码和中文逻辑注释，不改变业务行为：仍然读??? `POST /api/admin/withdrawList`，仍然???过 `POST /api/admin/withdrawProcess` 标记处理中，仍然通过 `POST /api/admin/withdrawComplete` 完成出金，仍然???过 `POST /api/admin/withdrawReject` 拒绝出金，按钮显隐继续由 `admin_withdraw_process`、`admin_withdraw_complete`、`admin_withdraw_reject` ??? `permissions.slug` 与角色授权配置控制???

### 本轮维护文件

- `tests/Feature/AdminWithdrawalsCommentReadabilityTest.php`：新增出金审??? Blade ??? JS 注释可读性测试，约束页面边界、接口来源???`user_id`、`status`、`amount`、`process`、`complete`、`reject`、`id`、`permissions.slug` 和乱码黑名单???
- `resources/admin/layui/withdrawals/index.blade.php`：重写页面顶部中文注释，说明列表接口、处理接口和后端权限与数据范围校验边界???
- `public/js/admin/layui/withdrawals/index.js`：重写为 UTF-8 可读中文注释，补齐出金审核列表职责???搜索参数含义???出金金额展示边界???状态流转动作含义???记录主键用途和按钮权限刷新说明???

### 注释覆盖???

| 注释片段 | 功能作用 |
| --- | --- |
| `出金管理页面` | 说明 Blade 页面负责出金审核页面结构和操作按钮展示??? |
| `admin_api_withdrawList` | 说明列表读取接口由后??? API 路由名配置??? |
| `admin_api_withdrawProcess` | 说明“处理中”动作对应的后端权限接口??? |
| `admin_api_withdrawComplete` | 说明“完成出金???动作对应的后端权限接口??? |
| `admin_api_withdrawReject` | 说明“拒绝出金???动作对应的后端权限接口??? |
| `后端权限与数据范围校验` | 说明前端按钮不是???终安全边界，真实权限由后端二次校验??? |
| `出金审核列表` | 说明列表数据来自 `/api/admin/withdrawList`，并由后端按角色和数据范围过滤??? |
| `user_id 表示出金申请人` | 说明筛???参数对应出金记录中的业务用??? ID??? |
| `status 表示出金处理状???` | 说明状???筛选参数和状???流转最终以后端校验为准??? |
| `amount 表示出金申请金额` | 说明金额字段只做列表展示，真实处理仍由后端接口校验??? |
| `process 表示标记出金处理中` | 说明操作列处理中按钮对应状???流转入口??? |
| `complete 表示完成出金记录` | 说明操作列完成按钮对应状态流转入口??? |
| `reject 表示拒绝出金记录` | 说明操作列拒绝按钮对应状态流转入口??? |
| `id 表示出金申请主键` | 说明处理接口参数用于后端读取记录、校验数据范围和判断状???流转??? |
| `重新应用按钮权限` | 说明 Layui 表格重载后必须重新隐藏无权限按钮??? |
| `permissions.slug` | 说明按钮显隐来自权限??? slug 与角色授权配置??? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminWithdrawalsCommentReadabilityTest.php
RED: 2 failures
- JS 缺少 `出金审核列表` 可读中文逻辑注释???
- Blade 缺少 `出金管理页面` 可读中文逻辑注释???
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

本轮未连接真实数据库，因此没有验??? `withdraw_records` 的真实列表数据???标记处理中、完成出金???拒绝出金写入结果和不同管理员数据范围过滤效果???真??? DB `127.0.0.1:3307` 恢复后，仍需使用超级管理员进??? `/admin/withdrawals` 页面，实测出金列表???`user_id` 筛??????`status` 筛??????处理中、完成???拒绝，以及低权限管理员??? `role_permissions` 和数据范围配置下的按钮显隐与数据隔离???


## 62. 2026-06-07 后台用户列表 users Blade/JS 中文注释与数据范围边界修???

本轮继续推进后台用户管理页面的源码可维护性，重点修复 `resources/admin/layui/users/index.blade.php` ??? `public/js/admin/layui/users/index.js`。该模块负责后台用户列表页面结构、用??? ID/邮箱/账号类型筛??????代理与客户展示、认证状态展示???详情弹窗???账号启停状态切换，以及 Layui 表格重载后的按钮权限刷新；用户列表是后台数据范围控制的核心入口，因此页面注释必须说明筛???参数???接口来源???账号类型???状态按钮和后端权限边界???

本轮只修复编码和中文逻辑注释，不改变业务行为：仍然读??? `POST /api/admin/userList`，仍然???过 `POST /api/admin/changeUserStatus` 切换登录账号启停状???，详情仍然使用 `crmRoute('admin_page_users_detail', {id: data.user_id})` 打开 `/admin/users/{id}` Blade 页面，状态按钮显隐继续由 `admin_user_status` 对应??? `permissions.slug` 与角色授权配置控制???

### 本轮维护文件

- `tests/Feature/AdminUsersIndexCommentReadabilityTest.php`：新增用户列??? Blade ??? JS 注释可读性测试，约束页面职责、接口来源???`user_id`、`email`、`account_type`、`auth_status`、`detail`、`status`、`is_enabled`、`permissions.slug` 和乱码黑名单???
- `resources/admin/layui/users/index.blade.php`：补齐页面顶部中文注释，说明用户列表接口、状态修改接口???筛选字段和后端权限与数据范围校验边界???
- `public/js/admin/layui/users/index.js`：重写为 UTF-8 可读中文注释，补齐用户列表数据来源???搜索参数???账号类型???认证状态???详情弹窗???状态切换和按钮权限刷新说明???

### 注释覆盖???

| 注释片段 | 功能作用 |
| --- | --- |
| `用户管理页面` | 说明 Blade 页面负责后台用户列表结构和操作按钮展示??? |
| `admin_api_userList` | 说明列表读取接口由后??? API 路由名配置??? |
| `admin_api_changeUserStatus` | 说明状???按钮对应的后端权限接口??? |
| `user_id 筛???业务用??? ID` | 说明筛???表单的业务用户 ID 参数含义??? |
| `email 筛???登录邮箱` | 说明筛???表单的登录邮箱参数含义??? |
| `account_type 区分代理和客户` | 说明账号类型用于区分代理商和普???客户??? |
| `后端权限与数据范围校验` | 说明前端按钮不是???终安全边界，真实权限由后端二次校验??? |
| `用户列表` | 说明列表数据来自 `/api/admin/userList`，并由后端按角色和数据范围过滤??? |
| `user_id 表示业务用户 ID` | 说明列表主键用于详情页面和状态修改接口??? |
| `email 表示登录邮箱` | 说明邮箱字段来自 `user_logins` 关联对象??? |
| `account_type 表示账号类型` | 说明 `1=代理`、`2=客户` 的展示???辑??? |
| `auth_status 表示认证状???` | 说明认证状???枚举展示???辑??? |
| `detail 表示打开用户详情` | 说明详情按钮打开后台 Blade 详情页??? |
| `status 表示切换用户启停状???` | 说明状???按钮的业务动作??? |
| `is_enabled 表示登录账号是否启用` | 说明状???修改接口参数含义??? |
| `重新应用按钮权限` | 说明 Layui 表格重载后必须重新隐藏无权限按钮??? |
| `permissions.slug` | 说明按钮显隐来自权限??? slug 与角色授权配置??? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminUsersIndexCommentReadabilityTest.php
RED: 2 failures
- JS 缺少 `用户列表` 可读中文逻辑注释???
- Blade 缺少 `用户管理页面` 可读中文逻辑注释???
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

本轮未连接真实数据库，因此没有验??? `user_infos`、`user_logins` 的真实列表数据???账号启停写入结果和不同管理员数据范围过滤效果???真??? DB `127.0.0.1:3307` 恢复后，仍需使用超级管理员进??? `/admin/users` 页面，实测用户列表???`user_id` 筛??????`email` 筛??????`account_type` 筛??????详情弹窗???状态启停，以及低权限管理员??? `role_permissions`、`role_data_scopes` ??? `admin_agent_bindings` 配置下的按钮显隐与数据隔离???


## 63. 2026-06-08 后台用户详情 users/detail Blade/JS 中文注释与保存边界修???

本轮继续推进后台用户管理页面的源码可维护性，重点修复 `resources/admin/layui/users/detail.blade.php` ??? `public/js/admin/layui/users/detail.js`。该模块负责后台用户详情页面结构、隐藏用户主键???详情读取???基???资料回填、用户姓???/手机号保存???登录启停状态同步和保存成功后返回用户列表；用户详情是后台数据范围校验的重要单条入口，因此页面注释必须说??? `user_id` 来源、真实表关系、保存字段???状态字段和后端权限边界???

本轮只修复编码和中文逻辑注释，不改变业务行为：仍然读??? `POST /api/admin/userDetail`，仍然???过 `POST /api/admin/updateUser` 更新 `user_infos` 基础资料，仍然???过 `POST /api/admin/changeUserStatus` 更新 `user_logins.is_enabled`，保存成功后仍然跳转 `crmRoute('admin_page_users')` 返回后台用户列表???

### 本轮维护文件

- `tests/Feature/AdminUsersDetailCommentReadabilityTest.php`：新增用户详??? Blade ??? JS 注释可读性测试，约束页面职责、隐藏主键???`admin_api_userDetail`、`admin_api_updateUser`、`admin_api_changeUserStatus`、`user_id`、`user_name`、`phone`、`status`、`is_enabled`、真实表关系和乱码黑名单???
- `resources/admin/layui/users/detail.blade.php`：补齐页面顶部中文注释，说明 `/admin/users/{id}` 路由参数、隐藏字段???详情读取接口???保存接口???状态接口和后端权限与数据范围校验边界???
- `public/js/admin/layui/users/detail.js`：重写为 UTF-8 可读中文注释，补齐详情读取???表单回填???`user_infos`、`user_logins`、基???资料保存和启停状态同步说明???

### 注释覆盖???

| 注释片段 | 功能作用 |
| --- | --- |
| `用户详情页面` | 说明 Blade 页面负责后台用户单条详情编辑结构??? |
| `user_id 隐藏字段` | 说明隐藏主键来自 `/admin/users/{id}` 路由参数??? |
| `admin_api_userDetail` | 说明详情读取接口来源??? |
| `admin_api_updateUser` | 说明基础资料保存接口来源??? |
| `admin_api_changeUserStatus` | 说明登录启停状???同步接口来源??? |
| `后端权限与数据范围校验` | 说明前端页面不是???终安全边界，真实权限由后端二次校验??? |
| `用户详情` | 说明 JS 负责读取并回填单条用户详情??? |
| `user_id 表示业务用户 ID` | 说明详情读取和保存接口的主键含义??? |
| `user_infos` | 说明基础资料字段来源于业务用户表??? |
| `user_logins` | 说明登录邮箱和启停状态来自登录账号表??? |
| `user_name 表示用户姓名` | 说明保存??? `user_infos.user_name` 的字段含义??? |
| `phone 表示用户手机号` | 说明保存??? `user_infos.phone` 的字段含义??? |
| `status 表示页面选择的启停状态` | 说明页面表单状???和后端启停参数之间的关系??? |
| `is_enabled 表示登录账号是否启用` | 说明写入 `user_logins.is_enabled` 的参数含义??? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminUsersDetailCommentReadabilityTest.php
RED: 2 failures
- JS 缺少 `user_id 表示业务用户 ID` 可读中文逻辑注释???
- Blade 缺少 `用户详情页面` 可读中文逻辑注释???
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

本轮未连接真实数据库，因此没有验??? `user_infos`、`user_logins` 的真实详情读取???基???资料保存、启停状态写入和不同管理员数据范围过滤效果???真??? DB `127.0.0.1:3307` 恢复后，仍需使用超级管理员进??? `/admin/users/{id}` 页面，实测详情回填???用户姓名保存???手机号保存、启停状态同步，以及低权限管理员??? `role_permissions`、`role_data_scopes` ??? `admin_agent_bindings` 配置下的单条访问拦截???


## 64. 2026-06-08 后台个人资料 profile/edit Blade/JS 中文注释与字段边界修???

本轮继续推进后台管理员个人中心页面的源码可维护???，重点修复 `resources/admin/layui/profile/edit.blade.php` ??? `public/js/admin/layui/profile/edit.js`。该模块负责当前登录管理员资料读取???用户名只读展示、邮箱编辑???手机号编辑和保存结果提示；个人资料接口虽然是当前管理员自服务入口，但仍属于后台认证链路，因此页面注释必须说明当前登录管理员、接口来源???字段可编辑边界和后端校验边界???

本轮只修复编码和中文逻辑注释，不改变业务行为：仍然读??? `POST /api/admin/profileInfo`，仍然提??? `POST /api/admin/updateProfile`，`username` 继续只读展示，`email` ??? `mobile` 继续由后??? `AuthController@updateProfile` 校验并写入当前管理员记录???

### 本轮维护文件

- `tests/Feature/AdminProfileEditCommentReadabilityTest.php`：新增个人资料编??? Blade ??? JS 注释可读性测试，约束当前管理员???`admin_api_profileInfo`、`admin_api_updateProfile`、`username`、`email`、`mobile`、可更新字段边界和乱码黑名单???
- `resources/admin/layui/profile/edit.blade.php`：补齐页面顶部中文注释，说明当前登录管理员资料读取???保存接口和字段可编辑边界???
- `public/js/admin/layui/profile/edit.js`：重写为 UTF-8 可读中文注释，补齐资料读取???表单回填???邮???/手机号保存和后端校验说明???

### 注释覆盖???

| 注释片段 | 功能作用 |
| --- | --- |
| `后台个人资料编辑页面` | 说明 Blade 页面负责后台当前管理员个人资料编辑结构??? |
| `当前登录管理员` | 说明读取和保存对象来??? admin guard 当前用户??? |
| `admin_api_profileInfo` | 说明资料读取接口来源??? |
| `admin_api_updateProfile` | 说明资料保存接口来源??? |
| `username 只读` | 说明用户名只展示，不通过本页面修改??? |
| `email 可更新` | 说明邮箱是允许提交保存的字段??? |
| `mobile 可更新` | 说明手机号是允许提交保存的字段??? |
| `username 表示管理员登录名` | 说明 JS 表单字段含义??? |
| `email 表示管理员邮箱` | 说明提交参数含义??? |
| `mobile 表示管理员手机号` | 说明提交参数含义??? |
| `只允许更??? email ??? mobile` | 说明页面和后端保存字段边界??? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminProfileEditCommentReadabilityTest.php
RED: 2 failures
- JS 缺少 `admin_api_profileInfo` 可读中文逻辑注释???
- Blade 缺少 `后台个人资料编辑页面` 可读中文逻辑注释???
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

本轮未连接真实数据库，因此没有验??? `admins` 表中当前管理员邮箱和手机号的真实读取、格式校验和写入结果。真??? DB `127.0.0.1:3307` 恢复后，仍需使用后台管理员进??? `/admin/profile/edit` 页面，实测资料回填???邮箱格式校验???手机号保存和保存成功提示???


## 65. 2026-06-08 后台大代??? big-agents Blade/JS 中文注释??? CRUD 权限边界修复

本轮继续推进后台二批业务模块的源码可维护性，重点修复 `resources/admin/layui/big-agents/index.blade.php` ??? `public/js/admin/layui/big-agents/index.js`。该模块负责大代理列表???刷新???新增???编辑???删除???弹窗表单和表格重载后的按钮权限刷新；大代理账号会影响前台大客户/大代理登录链路，因此页面注释必须说明 `big_agents` 数据来源、CRUD 接口、字段含义和 `permissions.slug` 按钮权限边界???

本轮只修复编码和中文逻辑注释，不改变业务行为：仍然读??? `POST /api/admin/bigAgentList`，仍然???过 `POST /api/admin/createBigAgent` 新增，仍然???过 `POST /api/admin/updateBigAgent/{id}` 编辑，仍然???过 `POST /api/admin/deleteBigAgent/{id}` 删除；新增???编辑???删除按钮继续使??? `admin_big_agent_create`、`admin_big_agent_update`、`admin_big_agent_delete` 对应??? `permissions.slug` 控制显隐???

### 本轮维护文件

- `tests/Feature/AdminBigAgentsCommentReadabilityTest.php`：新增大代理 Blade ??? JS 注释可读性测试，约束 `big_agents`、`admin_api_bigAgentList`、`admin_api_createBigAgent`、`admin_api_updateBigAgent`、`admin_api_deleteBigAgent`、`id`、`username`、`password`、`status`、`permissions.slug` 和乱码黑名单???
- `resources/admin/layui/big-agents/index.blade.php`：补齐页面顶部和弹窗表单中文注释，说??? CRUD 接口、按钮权限???安全边界???`id`、`password` ??? `status` 字段含义???
- `public/js/admin/layui/big-agents/index.js`：重写为 UTF-8 可读中文注释，补齐列表来源???表格字段???创???/编辑分支、删除接口???密码留空边界和按钮权限刷新说明???

### 注释覆盖???

| 注释片段 | 功能作用 |
| --- | --- |
| `大代理管理页面` | 说明 Blade 页面负责大代??? CRUD 入口??? |
| `admin_api_bigAgentList` | 说明列表读取接口来源??? |
| `admin_api_createBigAgent` | 说明新增接口来源??? |
| `admin_api_updateBigAgent` | 说明编辑接口来源??? |
| `admin_api_deleteBigAgent` | 说明删除接口来源??? |
| `data-permission 对应 permissions.slug` | 说明按钮显隐来自权限??? slug??? |
| `后端 check.permission:admin` | 说明前端按钮不是???终安全边界??? |
| `id 为空表示新增` | 说明新增/编辑分支依据??? |
| `password 可留空` | 说明编辑时不修改密码的表单边界??? |
| `big_agents` | 说明列表数据对应真实数据表??? |
| `username 表示大代理登录名` | 说明登录名字段含义??? |
| `password 表示大代理登录密码` | 说明密码字段含义??? |
| `status 表示大代理启停状态` | 说明状???字段含义??? |
| `重新应用按钮权限` | 说明表格重载后必须重新隐藏无权限按钮??? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminBigAgentsCommentReadabilityTest.php
RED: 2 failures
- JS 缺少 `big_agents` 可读中文逻辑注释???
- Blade 缺少 `大代理管理页面` 可读中文逻辑注释???
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

本轮未连接真实数据库，因此没有验??? `big_agents` 表中的真实列表数据???新增写入???编辑更新???删除结果和权限中间件真实拦截效果???真??? DB `127.0.0.1:3307` 恢复后，仍需使用后台管理员进??? `/admin/big-agents` 页面，实测列表???创建大代理、编辑大代理、编辑时密码留空、删除大代理，以及低权限管理员在 `role_permissions` 配置下的按钮显隐和接口拦截???


## 66. 2026-06-08 后台黑名??? blacklist Blade/JS 中文注释??? CRUD 权限边界修复

本轮继续推进后台二批风控配置模块的源码可维护性，重点修复 `resources/admin/layui/blacklist/index.blade.php` ??? `public/js/admin/layui/blacklist/index.js`。该模块负责黑名单列表???关键词搜索、新增???编辑???删除???弹窗表单和表格重载后的按钮权限刷新；黑名单会影响业务用户准入和风控判断，因此页面注释必须说??? `blacklists` 数据来源、关键词匹配范围、CRUD 接口、字段含义和 `permissions.slug` 按钮权限边界???

本轮只修复编码和中文逻辑注释，不改变业务行为：仍然读??? `POST /api/admin/blacklistList`，仍然???过 `POST /api/admin/createBlacklist` 新增，仍然???过 `POST /api/admin/updateBlacklist/{id}` 编辑，仍然???过 `POST /api/admin/deleteBlacklist/{id}` 删除；新增???编辑???删除按钮继续使??? `admin_blacklist_create`、`admin_blacklist_update`、`admin_blacklist_delete` 对应??? `permissions.slug` 控制显隐???

### 本轮维护文件

- `tests/Feature/AdminBlacklistCommentReadabilityTest.php`：新增黑名单 Blade ??? JS 注释可读性测试，约束 `blacklists`、`keyword`、`name`、`id_card`、`email`、`phone`、`remark`、CRUD 接口、`permissions.slug` 和乱码黑名单???
- `resources/admin/layui/blacklist/index.blade.php`：补齐页面顶部和弹窗表单中文注释，说明列表接口???搜索范围???CRUD 接口、按钮权限???安全边界和表单字段来源???
- `public/js/admin/layui/blacklist/index.js`：重写为 UTF-8 可读中文注释，补齐列表来源???关键词搜索、表格字段???新???/编辑分支、删除接口???备注字段和按钮权限刷新说明???

### 注释覆盖???

| 注释片段 | 功能作用 |
| --- | --- |
| `黑名单管理页面` | 说明 Blade 页面负责黑名??? CRUD 入口??? |
| `admin_api_blacklistList` | 说明列表读取接口来源??? |
| `admin_api_createBlacklist` | 说明新增接口来源??? |
| `admin_api_updateBlacklist` | 说明编辑接口来源??? |
| `admin_api_deleteBlacklist` | 说明删除接口来源??? |
| `keyword 匹配姓名` | 说明搜索关键字覆盖姓名???证件???邮箱和手机号??? |
| `data-permission 来自 permissions.slug` | 说明按钮显隐来自权限??? slug??? |
| `后端 check.permission:admin` | 说明前端按钮不是???终安全边界??? |
| `字段名与 BlacklistController 入参保持???致` | 说明表单字段与后端控制器入参对齐??? |
| `blacklists` | 说明列表数据对应真实数据表??? |
| `keyword 表示统一搜索关键字` | 说明 JS 搜索参数含义??? |
| `name 表示黑名单姓名` | 说明姓名字段含义??? |
| `id_card 表示证件号码` | 说明证件字段含义??? |
| `email 表示邮箱` | 说明邮箱字段含义??? |
| `phone 表示手机号` | 说明手机号字段含义??? |
| `remark 表示备注` | 说明备注字段含义??? |
| `重新应用按钮权限` | 说明表格重载后必须重新隐藏无权限按钮??? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminBlacklistCommentReadabilityTest.php
RED: 2 failures
- JS 缺少 `黑名单列表` 可读中文逻辑注释???
- Blade 缺少 `黑名单管理页面` 可读中文逻辑注释???
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

本轮未连接真实数据库，因此没有验??? `blacklists` 表中的真实列表数据???关键词搜索、新增写入???编辑更新???删除结果和权限中间件真实拦截效果???真??? DB `127.0.0.1:3307` 恢复后，仍需使用后台管理员进??? `/admin/blacklist` 页面，实测搜索???新增黑名单、编辑黑名单、删除黑名单，以及低权限管理员在 `role_permissions` 配置下的按钮显隐和接口拦截???


## 67. 2026-06-08 后台注销申请 cancel-applies Blade/JS 中文注释与审核权限边界修???

本轮继续推进后台二批审核模块的源码可维护性，重点修复 `resources/admin/layui/cancel-applies/index.blade.php` ??? `public/js/admin/layui/cancel-applies/index.js`。该模块负责注销申请列表、状态筛选???审核???过、审核拒绝和表格重载后的按钮权限刷新；注???申请会影响客户账号生命周期，因此页面注释必须说明 `cancel_applies` 数据来源、状态枚举???审核接口???记录主键和 `permissions.slug` 按钮权限边界???

本轮只修复编码和中文逻辑注释，不改变业务行为：仍然读??? `POST /api/admin/cancelApplyList`，仍然???过 `POST /api/admin/cancelApplyApprove/{id}` 审核通过，仍然???过 `POST /api/admin/cancelApplyReject/{id}` 审核拒绝；审核按钮继续使??? `admin_cancel_apply_approve`、`admin_cancel_apply_reject` 对应??? `permissions.slug` 控制显隐???

### 本轮维护文件

- `tests/Feature/AdminCancelAppliesCommentReadabilityTest.php`：新增注???申请 Blade ??? JS 注释可读性测试，约束 `cancel_applies`、`status`、`0=待处理`、`1=通过`、`-1=拒绝`、审核接口???`id`、`approve`、`reject`、`permissions.slug` 和乱码黑名单???
- `resources/admin/layui/cancel-applies/index.blade.php`：补齐页面顶部和操作列中文注释，说明列表接口、审核接口???状态筛选???按钮权限和后端安全边界???
- `public/js/admin/layui/cancel-applies/index.js`：重写为 UTF-8 可读中文注释，补齐列表来源???状态枚举???申请主键???审核???过/拒绝动作和按钮权限刷新说明???

### 注释覆盖???

| 注释片段 | 功能作用 |
| --- | --- |
| `注销申请管理页面` | 说明 Blade 页面负责注销申请审核入口??? |
| `admin_api_cancelApplyList` | 说明列表读取接口来源??? |
| `admin_api_cancelApplyApprove` | 说明审核通过接口来源??? |
| `admin_api_cancelApplyReject` | 说明审核拒绝接口来源??? |
| `status 为空表示全部申请` | 说明筛???表单状态参数边界??? |
| `data-permission 来自 permissions.slug` | 说明按钮显隐来自权限??? slug??? |
| `后端 check.permission:admin` | 说明前端按钮不是???终安全边界??? |
| `注销申请列表` | 说明 JS 列表读取职责??? |
| `cancel_applies` | 说明数据对应真实注销申请表??? |
| `status 表示注销申请状???` | 说明状???字段含义??? |
| `0=待处理`、`1=通过`、`-1=拒绝` | 说明状???枚举??? |
| `id 表示注销申请主键` | 说明审核接口路径参数含义??? |
| `approve 表示通过注销申请` | 说明操作列???过按钮业务动作??? |
| `reject 表示拒绝注销申请` | 说明操作列拒绝按钮业务动作??? |
| `重新应用按钮权限` | 说明表格重载后必须重新隐藏无权限按钮??? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminCancelAppliesCommentReadabilityTest.php
RED: 2 failures
- JS 缺少 `注销申请列表` 可读中文逻辑注释???
- Blade 缺少 `注销申请管理页面` 可读中文逻辑注释???
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

本轮未连接真实数据库，因此没有验??? `cancel_applies` 表中的真实列表数据???状态筛选???审核???过、审核拒绝和权限中间件真实拦截效果???真??? DB `127.0.0.1:3307` 恢复后，仍需使用后台管理员进??? `/admin/cancel-applies` 页面，实测列表???状态筛选??????过注销申请、拒绝注???申请，以及低权限管理员在 `role_permissions` 配置下的按钮显隐和接口拦截???


## 68. 2026-06-08 后台返佣结算 commissions Blade/JS 中文注释??? settle_status 字段修复

本轮继续推进后台资金与代理链路模块的源码可维护???，重点修复 `resources/admin/layui/commissions/index.blade.php` ??? `public/js/admin/layui/commissions/index.js`。该模块负责返佣结算列表、代理筛选???结算状态筛选???单条返佣结算和表格重载后的按钮权限刷新；返佣记录???过 `agent_id` 与代理数据范围绑定，因此页面注释必须说明 `commission_records` 数据来源、`AdminDataScopeService` 裁剪边界、结算状态字段和 `permissions.slug` 按钮权限边界???

本轮除修复编码和中文逻辑注释外，还修复了前端字段与后端控制器不一致的问题：后??? `CommissionController@index` 使用 `settle_status` 筛???，页面原来提交 `status` 且表格读??? `status`，本轮已统一改为 `settle_status`，避免结算状态筛选和展示字段失效。业务接口保持不变：仍然读取 `POST /api/admin/commissionList`，仍然???过 `POST /api/admin/commissionSettle` 结算单条返佣记录，结算按钮继续使??? `admin_commission_settle` 对应??? `permissions.slug` 控制显隐???

### 本轮维护文件

- `tests/Feature/AdminCommissionsCommentReadabilityTest.php`：新增返佣结??? Blade ??? JS 注释可读性测试，约束 `commission_records`、`agent_id`、`user_id`、`amount`、`settle_status`、`AdminDataScopeService`、接口来源???`id`、`settle`、`permissions.slug`、字段对齐和乱码黑名单???
- `resources/admin/layui/commissions/index.blade.php`：补齐页面顶部和操作列中文注释，说明列表接口、结算接口???代理筛选???`settle_status` 筛???和后端安全边界???
- `public/js/admin/layui/commissions/index.js`：重写为 UTF-8 可读中文注释，补齐列表来源???数据范围???表格字段???结算动作和按钮权限刷新说明???

### 注释覆盖???

| 注释片段 | 功能作用 |
| --- | --- |
| `返佣结算管理页面` | 说明 Blade 页面负责返佣结算入口??? |
| `admin_api_commissionList` | 说明列表读取接口来源??? |
| `admin_api_commissionSettle` | 说明单条结算接口来源??? |
| `agent_id 筛???返佣归属代理` | 说明代理筛???参数含义??? |
| `settle_status 筛???结算状态` | 说明结算状???筛选参数必须与后端???鑷淬?? |
| `data-permission 来自 permissions.slug` | 说明按钮显隐来自权限??? slug??? |
| `后端 check.permission:admin` | 说明前端按钮不是???终安全边界??? |
| `返佣结算列表` | 说明 JS 列表读取职责??? |
| `commission_records` | 说明数据对应真实返佣记录表??? |
| `agent_id 表示返佣归属代理` | 说明数据范围归属字段??? |
| `user_id 表示产生返佣的客户` | 说明客户字段展示含义??? |
| `amount 表示返佣金额` | 说明金额列展示含义??? |
| `1=待结算`、`2=已结算` | 说明结算状???枚举??? |
| `AdminDataScopeService` | 说明列表会按管理员角???/代理范围裁剪??? |
| `id 表示返佣记录主键` | 说明结算接口参数含义??? |
| `settle 表示结算返佣记录` | 说明操作列按钮业务动作??? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminCommissionsCommentReadabilityTest.php
RED: 2 failures
- JS 缺少 `返佣结算列表` 可读中文逻辑注释???
- Blade 缺少 `返佣结算管理页面` 可读中文逻辑注释???
- 测试同时约束 `name="settle_status"` ??? `field: 'settle_status'`，用于暴露前端原 `status` 与后??? `settle_status` 不一致问题???
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

本轮未连接真实数据库，因此没有验??? `commission_records` 表中的真实列表数据???`settle_status` 筛???结果???单条结算写入和管理员代理数据范围裁剪效果???真??? DB `127.0.0.1:3307` 恢复后，仍需使用后台管理员进??? `/admin/commissions` 页面，实测代理筛选???待结算/已结算筛选???单条结算，以及低权限管理员??? `role_permissions`、`role_data_scopes` ??? `admin_agent_bindings` 配置下的按钮显隐和数据隔离???
## 69. 2026-06-08 后台组别配置 group-configs Blade/JS 中文注释与字段边界修???

本轮继续推进后台配置类模块的 Blade + Layui 可维护???，重点修复 `resources/admin/layui/group-configs/index.blade.php` ??? `public/js/admin/layui/group-configs/index.js`。该模块负责组别配置列表、关键字搜索、新增???编辑???删除???开关字段归???化和表格重载后的按钮权限刷新；组别配置数据来自真??? `group_configs` 表，会影响代理组、用户组、交易基数???返佣开关???ECN 标记和默认组配置，因此页面与脚本必须明确字段来源、接口来源和权限边界???

本轮只修复编码和中文逻辑注释，不改变现有业务接口：列表仍读取 `POST /api/admin/groupConfigList`，新增仍调用 `POST /api/admin/createGroupConfig`，编辑仍调用 `POST /api/admin/updateGroupConfig/{id}`，删除仍调用 `POST /api/admin/deleteGroupConfig/{id}`。新增???编辑???删除按钮继续使??? `admin_group_config_create`、`admin_group_config_update`、`admin_group_config_delete` 对应??? `permissions.slug` 控制显隐，后端最终仍??? `check.permission:admin` 鉴权???

### 本轮维护文件

- `tests/Feature/AdminGroupConfigsCommentReadabilityTest.php`：新增组别配??? Blade ??? JS 注释可读性测试，约束 `group_configs`、`keyword`、`name`、`group_name`、`radix`、`category`、`has_commission`、`is_enabled`、`is_ecn`、`is_default`、CRUD 接口、`id`、`permissions.slug` 和乱码黑名单???
- `resources/admin/layui/group-configs/index.blade.php`：重写为 UTF-8 可读中文注释，说明页面职责???接口来源???`group_name` ??? `group_configs.name` 的映射???`category=1/2` 业务含义、开关字段和权限边界???
- `public/js/admin/layui/group-configs/index.js`：重写为 UTF-8 可读中文注释，说明列表来源???搜索参数???表格字段???弹窗参数???CRUD 接口、复选框 1/0 归一化和按钮权限刷新逻辑???

### 注释覆盖???

| 注释片段 | 功能作用 |
| --- | --- |
| `组别配置管理页面` | 说明 Blade 页面负责组别配置 CRUD 入口??? |
| `组别配置列表` | 说明 JS 表格读取职责??? |
| `group_configs` | 说明数据对应真实组别配置表??? |
| `keyword 表示组别名称搜索关键字` | 说明搜索参数含义??? |
| `name 表示组别名称` | 说明列表字段来自 `group_configs.name`??? |
| `group_name 表示页面表单提交的组别名称` | 说明页面字段与后??? `normalizePayload()` 的映射关系??? |
| `radix 表示交易组别基数` | 说明交易组基数字段含义??? |
| `category 表示组别分类` | 说明分类字段含义??? |
| `1=代理组`、`2=用户组` | 说明 `category` 枚举边界??? |
| `has_commission 表示是否参与返佣` | 说明返佣???关字段??? |
| `is_enabled 表示是否启用` | 说明启用状???字段??? |
| `is_ecn 表示是否 ECN 组` | 说明 ECN 标记字段??? |
| `is_default 表示是否默认组` | 说明默认组字段??? |
| `admin_api_groupConfigList` | 说明列表接口来源??? |
| `admin_api_createGroupConfig` | 说明新增接口来源??? |
| `admin_api_updateGroupConfig` | 说明编辑接口来源??? |
| `admin_api_deleteGroupConfig` | 说明删除接口来源??? |
| `id 表示组别配置主键` | 说明编辑和删除路由参数含义??? |
| `重新应用按钮权限` | 说明表格重载后必须重新隐藏无权限按钮??? |
| `permissions.slug` | 说明按钮权限来源??? |
| `后端 check.permission:admin` | 说明前端隐藏不是???终安全边界??? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminGroupConfigsCommentReadabilityTest.php
RED: 2 failures
- JS 缺少 `组别配置列表` 可读中文逻辑注释???
- Blade 缺少 `组别配置管理页面` 可读中文逻辑注释???
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
group-configs JS/Blade 已无乱码命中；后??? Layui 剩余乱码文件??? `public/js/admin/layui/news/index.js`???
```

### 验证边界

本轮尝试运行 `vendor\bin\phpunit tests\Feature\AdminConfigCrudPermissionMigrationTest.php`，但该测试需要连接真??? MySQL，当??? `127.0.0.1:3307` 返回 `SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接`，因此不能声明真??? DB 权限迁移验证通过。真??? DB 恢复后，仍需进入 `/admin/group-configs` 页面实测列表、关键字搜索、新增???编辑???删除，以及低权限管理员??? `role_permissions` 配置下的按钮显隐和接口拦截效果???

## 70. 2026-06-08 后台新闻公告 news Blade/JS 中文注释与搜索字段修???

本轮继续推进后台内容管理模块??? Blade + Layui 可维护???，重点修复 `resources/admin/layui/news/index.blade.php` ??? `public/js/admin/layui/news/index.js`。该模块负责新闻公告列表、刷新???标题搜索???新增???编辑???删除???发布状态展示和表格重载后的按钮权限刷新；新闻公告数据来自真??? `news` 表，并???过 `is_published` 决定前台是否可见，因此页面与脚本必须明确字段来源、接口来源???发布状态枚举和权限边界???

本轮除修复编码和中文逻辑注释外，还修复了前后端搜索字段不???致的问题：`NewsController@index` 读取 `title` 作为搜索入参，原 Blade 搜索框提??? `keyword` 会导致搜索条件不生效。本轮已把搜索框改为 `name="title"`，与后端控制器保持一致???业务接口保持不变：列表仍读??? `POST /api/admin/newsList`，新增仍调用 `POST /api/admin/createNews`，编辑仍调用 `POST /api/admin/updateNews/{id}`，删除仍调用 `POST /api/admin/deleteNews/{id}`。新增???编辑???删除按钮继续使??? `admin_news_create`、`admin_news_update`、`admin_news_delete` 对应??? `permissions.slug` 控制显隐，后端最终仍??? `check.permission:admin` 鉴权???

### 本轮维护文件

- `tests/Feature/AdminNewsCommentReadabilityTest.php`：新增新闻公??? Blade ??? JS 注释可读性测试，约束 `news`、`title`、`content`、`is_published`、`1=已发布`、`0=未发布`、CRUD 接口、`id`、`permissions.slug`、搜索字段对齐和乱码黑名单???
- `resources/admin/layui/news/index.blade.php`：重写为 UTF-8 可读中文注释，说明页面职责???接口来源???`title` 搜索参数、表单字段???发布状态和权限边界???
- `public/js/admin/layui/news/index.js`：重写为 UTF-8 可读中文注释，说明列表来源???表格字段???弹窗参数???CRUD 接口、发布状态枚举和按钮权限刷新逻辑???

### 注释覆盖???

| 注释片段 | 功能作用 |
| --- | --- |
| `新闻公告管理页面` | 说明 Blade 页面负责新闻公告 CRUD 入口??? |
| `新闻公告列表` | 说明 JS 表格读取职责??? |
| `news` | 说明数据对应真实新闻公告表??? |
| `title 搜索参数??? NewsController@index 保持???致` | 说明搜索入参必须与后端控制器???鑷淬?? |
| `title 表示新闻标题` | 说明标题字段含义??? |
| `content 表示新闻正文` | 说明正文内容字段含义??? |
| `is_published 表示发布状???` | 说明发布状???字段含义??? |
| `1=已发布`、`0=未发布` | 说明发布状???枚举边界??? |
| `admin_api_newsList` | 说明列表接口来源??? |
| `admin_api_createNews` | 说明新增接口来源??? |
| `admin_api_updateNews` | 说明编辑接口来源??? |
| `admin_api_deleteNews` | 说明删除接口来源??? |
| `id 表示新闻公告主键` | 说明编辑和删除路由参数含义??? |
| `重新应用按钮权限` | 说明表格重载后必须重新隐藏无权限按钮??? |
| `permissions.slug` | 说明按钮权限来源??? |
| `后端 check.permission:admin` | 说明前端隐藏不是???终安全边界??? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminNewsCommentReadabilityTest.php
RED: 2 failures
- JS 缺少 `新闻公告列表` 可读中文逻辑注释???
- Blade 缺少 `新闻公告管理页面` 可读中文逻辑注释???
- 测试同时约束搜索框必须提??? `title`，用于暴露原 `keyword` ??? NewsController@index 不一致的问题???
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

本轮未连接真实数据库，因此没有验??? `news` 表中的真实列表数据???标题搜索结果???新增写入???编辑更新???删除结果???发布状态对前台展示的影响，以及低权限管理员??? `role_permissions` 配置下的按钮显隐和接口拦截效果???真??? DB `127.0.0.1:3307` 恢复后，仍需进入 `/admin/news` 页面实测刷新、标题搜索???新增新闻公告???编辑新闻公告???删除新闻公告，并用前台新闻公告页面确认 `is_published=1` 的可见???边界???

## 71. 2026-06-08 后台认证 AuthController 中文参数注释与旧密码错误多语???修复

本轮继续推进后端控制器层“所有模块文件及参数必须有详细中文注释???的目标，重点维??? `app/Http/Controllers/Admin/AuthController.php`。该控制器是后台认证链路入口，负??? `admin_api_login`、`admin_api_logout`、`admin_api_refreshToken`、`admin_api_profileInfo`、`admin_api_updateProfile`、`admin_api_changePassword`、`admin_api_uploadAvatar` 等基???接口；它决定后台管理员是谁???JWT 如何签发与失效???登录审计如何记录，以及改密后当??? Token 是否立即失效???

本轮补齐控制器类、构造函数???登录???登出???资料读取???资料更新???改密???头像上传和刷新 Token 的中文???辑注释，明确请求参数和安全边界。同时修复一个后端多语言缺口：`changePassword()` 原来在旧密码错误时返回英文硬编码 `Old password incorrect`，本轮改??? `__('response.old_password_wrong')`，中英文语言包实际返回分别为“旧密码不正确???和“Old password is incorrect”???

### 本轮维护文件

- `tests/Feature/AdminAuthControllerCommentReadabilityTest.php`：新增后台认证控制器注释可读性测试，约束登录参数、JWT 载荷、登录日志???Token 失效、资料字段???改密字段???头像字段???接口名、多语言键和乱码黑名单???
- `app/Http/Controllers/Admin/AuthController.php`：补齐中文类注释、方法注释???参数注释和安全边界说明；旧密码错误响应从英文硬编码改为 `__('response.old_password_wrong')`???

### 注释覆盖???

| 注释片段 | 功能作用 |
| --- | --- |
| `后台管理员认证控制器` | 说明控制器职责??? |
| `username 表示后台管理员登录名` | 说明登录账号参数含义??? |
| `password 表示后台管理员登录密码` | 说明登录密码参数含义??? |
| `sub 表示 admins.id` | 说明 JWT 主体字段含义??? |
| `guard 固定??? admin` | 说明后台 JWT guard 边界??? |
| `AdminLoginLog 记录登录审计信息` | 说明登录成功后的审计日志写入??? |
| `jwt_token 表示当前请求解析出的后台 JWT` | 说明登出、改密???刷??? Token ??? Token 来源??? |
| `profileInfo 返回当前登录管理员资料` | 说明资料接口只返回当前管理员??? |
| `email 表示管理员邮箱` | 说明资料更新字段??? |
| `mobile 表示管理员手机号` | 说明资料更新字段??? |
| `old_password 表示当前旧密码` | 说明改密校验字段??? |
| `password_confirmation 表示新密码确认???` | 说明 Laravel confirmed 规则字段??? |
| `修改密码成功后使当前 Token 失效` | 说明改密安全边界??? |
| `avatar 表示上传的管理员头像文件` | 说明头像上传字段??? |
| `refreshToken 使用当前有效 Token 换取??? Token` | 说明刷新 Token 琛屼负銆? |
| `admin_api_login`、`admin_api_refreshToken` | 说明认证接口来源??? |
| `check.permission:admin` | 说明认证基础接口和业务权限中间件边界??? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminAuthControllerCommentReadabilityTest.php
RED: 1 failure
- AuthController 缺少 `username 表示后台管理员登录名` 等可读中文参数注释???
- 测试同时约束旧密码错误必须使??? `__('response.old_password_wrong')`，不能保??? `'Old password incorrect'` 英文硬编码???
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

本轮未连接真实数据库，因此没有使用真??? `admins` 账号实测 `/api/admin/login`、`/api/admin/changePassword`、`/api/admin/refreshToken`、`/api/admin/profileInfo`、`/api/admin/updateProfile` ??? `/api/admin/uploadAvatar`。真??? DB `127.0.0.1:3307` 恢复后，仍需用后台管理员账号完整验证登录成功、登录日志写入???旧密码错误多语???响应、改密后当前 Token 失效、刷??? Token 和资料更新???

## 72. 2026-06-08 后台支付通道 PaymentChannelController 中文参数注释补齐

本轮继续推进后端控制器层“所有模块文件及参数必须有详细中文注释???的目标，重点维??? `app/Http/Controllers/Admin/PaymentChannelController.php`。该控制器负责后台支付???道列表、新增???编辑???删除和启用状???切换；支付通道数据来自真实 `payment_channels` 表，会影响前台入金???道展示和后台资金配置维护，因此控制器注释必须明确字段含义???兼容字段映射???接口权限边界和路由参数???

本轮只补齐中文???辑注释，不改变 CRUD 行为：列表仍读取 `POST /api/admin/channelList`，新增仍调用 `POST /api/admin/createChannel`，编辑仍调用 `POST /api/admin/updateChannel/{id}`，删除仍调用 `POST /api/admin/deleteChannel/{id}`。页面按钮显隐仍来自 `permissions.slug`，接口最终仍??? `check.permission:admin` ??? `permissions.api_route` 鉴权???

### 本轮维护文件

- `tests/Feature/AdminPaymentChannelControllerCommentReadabilityTest.php`：新增支付???道控制器注释可读???测试，约束 `payment_channels`、分页参数??????道字段、兼容字段???CRUD 接口、权限边界和乱码黑名单???
- `app/Http/Controllers/Admin/PaymentChannelController.php`：补齐控制器类???列表???新增???编辑???删除???启用状态切换方法的中文参数说明和???辑边界说明???

### 注释覆盖???

| 注释片段 | 功能作用 |
| --- | --- |
| `支付通道管理控制器` | 说明控制器职责??? |
| `payment_channels` | 说明数据来源表??? |
| `admin_api_channelList` | 说明列表接口来源??? |
| `admin_api_createChannel` | 说明新增接口来源??? |
| `admin_api_updateChannel` | 说明编辑接口来源??? |
| `admin_api_deleteChannel` | 说明删除接口来源??? |
| `page 表示当前页码` | 说明分页入参??? |
| `per_page 表示每页数量` | 说明标准分页入参??? |
| `limit 表示 Layui 表格每页数量` | 说明 Layui 兼容分页入参??? |
| `name 表示支付通道名称` | 说明真实通道名称字段??? |
| `channel_name 表示旧页面提交的通道名称` | 说明旧字段到 `name` 的兼容映射??? |
| `channel_code 表示支付通道编码` | 说明通道唯一编码字段??? |
| `exchange_rate 表示支付通道汇率` | 说明汇率字段??? |
| `is_enabled 表示通道是否启用` | 说明业务可用状???字段??? |
| `sort 表示后台排序值` | 说明排序字段??? |
| `config 表示支付通道扩展配置` | 说明扩展配置字段??? |
| `id 表示支付通道主键` | 说明编辑、删除???切换状态路由参数??? |
| `check.permission:admin` | 说明后端鉴权边界??? |
| `permissions.api_route` | 说明接口权限配置来源??? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminPaymentChannelControllerCommentReadabilityTest.php
RED: 1 failure
- PaymentChannelController 缺少 `支付通道管理控制器` 及字段参数可读中文???辑注释???
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

本轮未连接真实数据库，因此没有验??? `payment_channels` 表中的真实列表数据???新增写入???编辑更新???删除结果??????道启用状???对前台入金流程的影响，以及低权限管理员??? `role_permissions` 配置下的按钮显隐和接口拦截效果???真??? DB `127.0.0.1:3307` 恢复后，仍需进入 `/admin/channels` 页面实测刷新、新增支付???道、编辑支付???道、删除支付???道，并在前台入金流程确??? `is_enabled=1` 的支付???道可见性???

## 73. 2026-06-08 后台大代??? BigAgent 字段对齐、乱码注释与控制器参数说明修???

本轮继续推进“大代理模块”从 Blade/JS 到后端控制器和模型的字段???鑷存?????排查发??? `big_agents` 表和前台大代理登录???辑使用 `is_enabled` 表示账号是否启用，但后台 Blade/JS 原来仍使??? `status` 字段，导致后台启停状态可能无法正确写入真实业务字段???本轮已统一改为 `is_enabled`，并补齐 `BigAgentController`、`BigAgent` 模型、`big-agents` Blade/JS 的中文???辑注释???

本轮保留原接口不变：列表仍读??? `POST /api/admin/bigAgentList`，新增仍调用 `POST /api/admin/createBigAgent`，编辑仍调用 `POST /api/admin/updateBigAgent/{id}`，删除仍调用 `POST /api/admin/deleteBigAgent/{id}`。控制器兼容??? `status` 入参，但真实写入字段统一??? `big_agents.is_enabled`。编辑大代理时，`password` 留空现在通过 `nullable|string|min:6` 校验并保留原密码，避免空密码触发校验失败或覆盖旧密码???

### 本轮维护文件

- `tests/Feature/AdminBigAgentBackendFieldAlignmentTest.php`：新增大代理后台字段对齐测试，约束控制器、模型???Blade、JS 全链路使??? `is_enabled`，并???查乱码黑名单???
- `tests/Feature/AdminBigAgentsCommentReadabilityTest.php`：重写为 UTF-8 可读中文测试，并把旧 `status` 断言改为 `is_enabled`???
- `app/Http/Controllers/Admin/BigAgentController.php`：补齐接口???参数???权限边界和密码留空逻辑说明；新??? `normalizePayload()`，统?????? `is_enabled/status` 兼容入参写入 `big_agents.is_enabled`???
- `app/Models/BigAgent.php`：补齐模型职责???`sub_agent_ids`、`is_enabled` 和登录日志关联说明???
- `resources/admin/layui/big-agents/index.blade.php`：表单字段从 `status` 改为 `is_enabled`，并补齐可读中文注释???
- `public/js/admin/layui/big-agents/index.js`：表格字段和提交字段??? `status` 改为 `is_enabled`，并补齐可读中文注释???

### 关键字段修复

| 字段 | 修复??? | 修复??? |
| --- | --- | --- |
| 启停状???表单字??? | `status` | `is_enabled` |
| 表格展示字段 | `status` | `is_enabled` |
| 控制器真实写入字??? | 可能??? `$request->all()` 写入 `status` | 统一写入 `big_agents.is_enabled` |
| 编辑密码留空 | `sometimes|string|min:6` 可能让空字符串参与校??? | `nullable|string|min:6`，留空保留原密码 |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminBigAgentBackendFieldAlignmentTest.php
RED: 1 failure
- BigAgentController 缺少 `admin_api_bigAgentList` 等控制器中文逻辑注释???
- 测试同时约束 Blade/JS 必须使用 `is_enabled`，不能继续使??? `status`???
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

Node 大代理生产文件乱码扫???
[]
```

### 验证边界

本轮未连接真实数据库，因此没有验??? `big_agents` 表中的真实列表数据???新增写入???编辑更新???删除结果???`is_enabled` 对前台大代理登录的真实拦截效果，以及低权限管理员??? `role_permissions` 配置下的按钮显隐和接口拦截效果???真??? DB `127.0.0.1:3307` 恢复后，仍需进入 `/admin/big-agents` 页面实测创建大代理???编辑大代理、编辑时密码留空、启停状态保存???删除大代理，并用前台大代理登录入口确认 `is_enabled=0` 的账号不可登录???

## 74. 2026-06-08 后台角色权限分配 UI、权限控制器注释??? guard 边界修复

本轮继续推进后台“菜单???按钮???接口权限全部来自数据表配置”的目标，重点修复角色与权限管理链路。此前后台角色页只能新增、编辑???删除角色，没有直接分配菜单/按钮/接口权限??? UI；虽然后端已??? `POST /api/admin/assignPermissions`，但页面无法维护 `role_permissions`，会导致“多种管理员角色拥有不同菜单权限”的配置闭环不完整???

本轮已在 `/admin/roles` ??? Blade + Layui JS 中新增???分配权限???入口：操作列按钮使??? `admin_role_assign_permissions`，点击后加载 `POST /api/admin/permissionTree` 权限树，按当前角??? `permission_ids` 回显勾???状态，保存时提??? `role_id` ??? `permissions` 数组??? `POST /api/admin/assignPermissions`，最终由后端写入 `role_permissions` 琛ㄣ??

### 本轮维护文件

- `app/Http/Controllers/Admin/RoleController.php`：重写中文???辑注释，补??? `page`、`per_page`、`role_id`、`permissions` 等参数说明；角色列表返回 `permission_ids`；`assignPermissions()` 增加??? `guard_type` 权限校验，只允许后台角色绑定 admin 权限、前台角色绑??? front 权限???
- `app/Http/Controllers/Admin/PermissionController.php`：重写中文???辑注释，明??? `permissions` 表是前后台菜单???页面???按钮和接口权限的唯???配置来源；新???/更新权限改为显式字段白名单???
- `resources/admin/layui/roles/index.blade.php`：新??? `assignPermissions` 操作按钮、`rolePermissionForm` 表单、`permissionTreeBox` 权限树容器和 `saveRolePermissions` 保存按钮???
- `public/js/admin/layui/roles/index.js`：新增权限树加载、已授权节点回显、`tree.getChecked()` 收集权限 ID、保存授权???按钮权限刷新和详细中文参数注释???
- `database/migrations/2026_06_06_000006_add_admin_core_button_permissions.php`：新??? `admin_role_assign_permissions => admin_api_assignPermissions`，确保授权按钮权限也来自 `permissions` 琛ㄣ??
- `resources/lang/zh-CN/role.php`、`resources/lang/en/role.php`：新??? Blade 侧???分配权限???文案???
- `public/js/common/lang/zh-CN.js`、`public/js/common/lang/en.js`：新??? JS ??? `role.assignPermissions`、`role.assignPermissionHint` 文案???
- `tests/Feature/AdminRolePermissionAssignmentUiTest.php`：新增角色授??? UI 覆盖测试，约??? Blade/JS 必须存在授权入口和真??? API 调用???
- `tests/Feature/AdminRolePermissionControllerReadabilityTest.php`：新增角???/权限控制器中文注释与乱码黑名单测试???
- `tests/Feature/AdminCorePermissionMigrationTest.php`、`tests/Feature/AdminButtonPermissionVisibilityTest.php`：补??? `admin_role_assign_permissions` 期望???

### 本轮接口与权限消???

| 页面/接口 | 方法 | 作用 | 权限来源 |
| --- | --- | --- | --- |
| `/admin/roles` | GET | 角色管理 Blade 页面，渲染角色列表和权限分配弹窗 | 页面入口来自后台菜单权限 |
| `/api/admin/roleList` | POST | 返回角色列表、分页???数??? `permission_ids` | `permissions.api_route=admin_api_roleList` |
| `/api/admin/permissionTree` | POST | ??? `guard_type` 返回权限??? | `permissions.api_route=admin_api_permissionTree` |
| `/api/admin/assignPermissions` | POST | 写入 `role_permissions` 授权关系 | `admin_role_assign_permissions` / `admin_api_assignPermissions` |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminRolePermissionControllerReadabilityTest.php
RED: RoleController 缺少 `roles 表保存角色基???信息` 等中文???辑注释???

vendor\bin\phpunit tests\Feature\AdminRolePermissionAssignmentUiTest.php
RED: 角色 Blade 缺少 `admin_role_assign_permissions`，说明页面没有权限分配入口???
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

`vendor\bin\phpunit tests\Feature\AdminCorePermissionMigrationTest.php` 本轮运行时仍因真??? MySQL `127.0.0.1:3307` 拒绝连接失败，错误为 `SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接。` 因此本轮没有声明真实 DB 权限迁移写入已验证???

真实 DB 恢复后必须继续执行：运行核心权限迁移，确??? `permissions.slug=admin_role_assign_permissions` ??? `api_route=admin_api_assignPermissions`；使用超级管理员进入 `/admin/roles`，打???“分配权限???弹窗，勾???/取消权限后保存；再用低权限管理员登录，验??? `/api/admin/menus` 返回的菜单与按钮权限确实??? `role_permissions` 改变???

## 75. 2026-06-08 角色与权限模型中文注释???字段含义和授权来源补齐

本轮继续推进后台权限链路的模型层维护，重点处??? `app/Models/Role.php` ??? `app/Models/Permission.php`。这两个模型??? `roles`、`permissions`、`role_permissions` 三张权限核心表的入口，如果模型注释不可读或字段边界不清晰，后续控制器、菜单服务和 Blade 页面即使已经接入权限表，也容易再次出现???双权限来源”或前后台权限混用问题???

本轮不改变业务行为：`Role::hasPermission($slug)` 仍然通过 `permissionsRelation()` 读取 `role_permissions` 中间表，并按 `permissions.slug` ??? `permissions.status=1` 判断；`Permission` 模型仍保留原??? `admin/front/menu/page/button/active` scope，只补齐中文逻辑注释和字段参数说明???

### 本轮维护文件

- `tests/Feature/AdminRolePermissionModelReadabilityTest.php`：新增模型层中文注释可读性测试，约束 Role/Permission 模型必须说明字段含义、关联关系???授权来源和乱码黑名单???
- `app/Models/Role.php`：补??? `roles`、`guard_type`、`role_permissions`、`roles.permissions JSON`、`role_data_scopes`、`permissionsRelation()`、`hasPermission($slug)` ??? `admins()` 的中文说明???
- `app/Models/Permission.php`：补??? `permissions` 表字段???`slug`、`api_route`、`guard_type`、`type=1/2/3`、父子权限???角色关联和 scope 参数说明???

### 模型字段边界

| 模型 | 字段/关系 | 当前说明 |
| --- | --- | --- |
| `Role` | `guard_type` | `admin` 表示后台管理员角色，`front` 表示前台代理商或普???客户角色??? |
| `Role` | `permissions()` | 通过 `role_permissions.role_id` ??? `role_permissions.permission_id` 关联权限??? |
| `Role` | `permissionsRelation()` | 兼容旧调用名，仍返回 `permissions()`，不引入第二套授权来源??? |
| `Role` | `permissions` JSON | 仅保留兼容字段，不作为真实鉴权来源??? |
| `Permission` | `slug` | 前端 `data-permission` 和后端权限判断共同使用的稳定标识??? |
| `Permission` | `api_route` | Laravel 命名路由，供 `check.permission:admin` 匹配接口权限??? |
| `Permission` | `type` | `1=菜单`、`2=页面`、`3=按钮或接口动作`??? |
| `Permission` | `guard_type` | 区分后台 `admin` 与前??? `front` 权限，避免两端混用??? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminRolePermissionModelReadabilityTest.php
RED: Role 模型缺少 `roles 表保存后台管理员、前台代理商和普通客户可绑定的角色` 等中文???辑注释???
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

`vendor\bin\phpunit tests\Feature\AdminPermissionPlanTest.php --filter test_role_permission_check_uses_role_permissions_table` 本轮运行时仍因真??? MySQL `127.0.0.1:3307` 拒绝连接失败，错误为 `SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接。` 因此本轮只完成源码与静???测试验证，没有声明真实 DB ??? `roles`、`permissions`、`role_permissions` 的运行时查询已验证???

真实 DB 恢复后必须继续执行：创建或确认一个普通后台角色，给它写入部分 `role_permissions`，调??? `Role::hasPermission()` 或访问受保护后台接口，验证已授权 slug 返回允许、未授权 slug 返回拒绝；同时确??? `roles.permissions` JSON 不参与真实鉴权判断???

## 76. 2026-06-08 CheckPermission 中间件中文注释与多语???权限响应边界补齐

本轮继续推进后台权限强制鉴权链路，重点维??? `app/Http/Middleware/CheckPermission.php`。该中间件是后台接口权限安全边界：`jwt.auth:admin` 只能确认“是谁???，`sso:admin` 只能确认“当??? token 是否仍有效???，真正判断当前管理员能不能访问当前接口，必须???过 `permissions.api_route`、`permissions.guard_type` ??? `role_permissions` 完成???

本轮不改变鉴权行为，只补齐中间件中文逻辑注释、参数含义???白名单边界和多语言响应说明。当前???辑仍保持：未登录返??? `__('response.auth_failed')`；无路由名???无角色、权限未配置、角色未授权时返??? `__('response.permission_denied')`；超级管理员只跳过权限表校验，不跳过 JWT ??? SSO???

### 本轮维护文件

- `tests/Feature/AdminCheckPermissionMiddlewareReadabilityTest.php`：新增中间件中文注释可读性测试，约束鉴权顺序、`$guardType`、`$routeName`、`permissions.api_route`、`permissions.guard_type`、`role_permissions`、白名单和多语言响应???
- `app/Http/Middleware/CheckPermission.php`：补齐类注释、`handle()` 参数说明、鉴权顺序???白名单说明和超级管理员边界说明???

### 核心权限边界

| 步骤 | 当前职责 |
| --- | --- |
| `jwt.auth:admin` | 解析后台管理??? token，确认当前请求是谁??? |
| `sso:admin` | 校验当前 token 是否仍是该账号有效登录??? |
| `check.permission:admin` | 按当前命名路由匹??? `permissions.api_route`，再??? `role_permissions` 判断当前角色是否授权??? |
| 白名单接??? | 菜单、个人资料???改密???头像??????出登录???刷??? token，只要求登录??? SSO 有效??? |
| 超级管理??? | 只跳过权限表校验，不能跳过登录认证和 SSO??? |

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminCheckPermissionMiddlewareReadabilityTest.php
RED: CheckPermission 缺少 `后台接口权限???查中间件` 等中文???辑注释???
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

`vendor\bin\phpunit tests\Feature\AdminPermissionPlanTest.php --filter test_admin_protected_routes_include_permission_middleware` 本轮运行时仍因真??? MySQL `127.0.0.1:3307` 拒绝连接失败，错误为 `SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接。` 因此本轮没有声明真实数据库事务场景下的完整权限中间件测试通过???

真实 DB 恢复后必须继续执行：用普通后台管理员访问???个未授权的受保护接口，确认返??? `4006` 与当前语?????? `response.permission_denied`；给该角色写入对??? `role_permissions` 后再次访问，确认接口放行；再用超级管理员确认同接口可跳过权限表校验但仍必须携带有??? token???

## 77. 2026-06-08 前台 Layui 菜单权限配置与真实路由一致???补???

本轮针对 `agent` 账号登录后前??? Layui 菜单缺失的问题继续收口???当前前台菜单不??? Blade 静???写死，而是??? `public/js/front/layui/layout.js` 调用 `POST /api/front/navigation/menus` 后动态渲染；后端 `Front\MenuController@userMenus` 再根??? `user_logins.role_id`、`roles`、`role_permissions` ??? `permissions` 返回当前账号可见菜单树???

排查时发??? `database/migrations/2026_06_07_000014_fix_default_admin_and_front_menu_roles.php` 中两个前台返佣菜单的 `permissions.api_route` 写成了不存在的命名路由：`front_api_commissionRealTime`、`front_api_commissionHistory`。这会导致菜单权限字典和真实接口路由不一致，后续如果??? `api_route` 做接口级鉴权或配置审计，会出现???菜单已授权但接口路由不可匹配???的隐患???

### 本轮维护文件

- `tests/Feature/FrontMenuPermissionRouteConsistencyTest.php`：新增前台菜单权限配置一致???测试，直接读取前台菜单修复迁移里的 `frontMenuTree()`，并逐条校验非空 `api_route` 是否存在??? Laravel 当前命名路由表???
- `database/migrations/2026_06_07_000014_fix_default_admin_and_front_menu_roles.php`：修??? `front_commission_rt` ??? `api_route` ??? `front_api_commissions_realtime`，修??? `front_commission_hist` ??? `api_route` ??? `front_api_commissions_history`???

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontMenuPermissionRouteConsistencyTest.php
RED: 前台菜单 permissions.api_route 存在未注册的命名路由???
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

本轮验证的是源码层面的菜单权限配置与当前 Laravel 命名路由表一致???，没有声明真实 MySQL ??? `permissions`、`roles`、`role_permissions`、`user_logins.role_id` 已经写入成功。真??? DB 恢复后仍???执行迁移，并??? `agent@test.com / agent123` 登录前台 Layui，确??? `POST /api/front/navigation/menus` 返回代理菜单树，且返佣实时???返佣历史菜单对应页面能正常调用当前真实接口???

## 78. 2026-06-08 后台用户控制器参数注释与前台资料路由别名补齐

本轮继续围绕 plan.md 中???所有模块文件及参数必须有详细中文注释???和“前后台???有菜单???按钮???接口权限从数据表配置得到???的要求推进。优先维护后台用户管理控制器，因为它同时涉及用户列表、详情???资料更新???实名认证审核???登录账号启停和数据范围校验，是后台数据查看权限链路中的核心入口???

同时在复跑数据范围接入测试时发现应用启动阶段存在前台资料接口兼容别名错误：`routes/web.php` 仍把 `front.password.update`、`front.profile.update`、`front.profile.avatar` 等旧 Blade 兼容别名指向不存在的 `front_api_changePassword`、`front_api_updateProfile`、`front_api_uploadAvatar`。当前真实路由名已经??? `front_api_profile_password`、`front_api_profile_update`、`front_api_profile_avatar`。本轮已同步修正别名??? `CheckPermission` 基础白名单，避免应用 boot 时因别名目标缺失直接报错???

### 本轮维护文件

- `tests/Feature/AdminUserControllerCommentReadabilityTest.php`：新增后台用户控制器中文注释可读性测试，要求保留 `userList()`、`reviewAuth()`、`userDetail()`、`updateUser()`、`changeUserStatus()` 的参数含义???表来源、数据范围和权限边界说明???
- `app/Http/Controllers/Admin/AdminUserController.php`：补齐控制器类注释和核心接口参数说明，明??? `user_id`、`email`、`account_type`、`status`、`reason`、`is_enabled` 的业务含义，并说明接口权限来??? `permissions.api_route`，数据范围来??? `AdminDataScopeService` 读取 `role_data_scopes` ??? `admin_agent_bindings`???
- `routes/web.php`：修正旧 Blade 路由别名目标，将前台资料更新、改密???头像上传别名指向当前真实前??? API 路由名???
- `app/Http/Middleware/CheckPermission.php`：同步更新前台资料类基础白名单，使用当前真实路由名，避免白名单继续引用已不存在的旧接口名???

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminUserControllerCommentReadabilityTest.php
RED: AdminUserController 缺少中文参数或???辑注释：userList() 参数说明
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

`vendor\bin\phpunit tests\Feature\AdminDataScopeControllerWiringTest.php` 在路由别名修复后已经不再因为 `front.password.update -> front_api_changePassword` 目标缺失而中断，当前失败原因推进为真??? MySQL `127.0.0.1:3307` 拒绝连接：`SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接。` 因此本轮没有声明真实数据库事务场景下的数据范围接入测试???过。真??? DB 恢复后仍???继续执行该测试，并验证普通管理员??? `role_data_scopes` ??? `admin_agent_bindings` 限制下只能访问授权范围内的用户???代理???入金???出金和返佣数据???

## 79. 2026-06-08 ??? Blade 路由兼容别名???鑷存??测试与中文参数说明补齐

本轮继续处理前后端不分离场景下的 Blade 路由稳定性问题???当前项目同时保??? `resources/views` 历史 Blade、`resources/front/layui`、`resources/admin/layui` ??? Naive 页面入口，因??? `routes/web.php` 末尾维护了一组旧 Blade 路由兼容别名。该别名配置如果指向不存在的真实命名路由，Laravel 应用会在 boot 阶段直接抛出 `Route alias target is missing`，导致后台页面???前台页面和自动化测试全部被阻断???

### 本轮维护文件

- `tests/Feature/BladeRouteAliasCompatibilityTest.php`：新增旧 Blade 路由兼容别名???鑷存??测试，静???解??? `$crmBladeRouteAliases`，并校验每个 `targetName` 与每??? `alias` 都已经注册到 Laravel 路由表???
- `routes/web.php`：补??? `crm_alias_named_route()` ??? `$crmBladeRouteAliases` 配置块的中文逻辑注释，说??? `alias`、`targetName`、目标路由存在???校验和兼容边界???

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
已确认应用可正常 boot，并能列??? admin_page_dashboard、front_page_dashboard、front_api_profile_password、front_api_profile_update、front_api_profile_avatar 等真实路由???

php -l routes\web.php
No syntax errors detected in routes\web.php

php -l tests\Feature\BladeRouteAliasCompatibilityTest.php
No syntax errors detected in tests\Feature\BladeRouteAliasCompatibilityTest.php

基础 UTF-8/乱码片段扫描
routes/web.php: OK
tests/Feature/BladeRouteAliasCompatibilityTest.php: OK
```

### 验证边界

本轮验证的是路由别名配置与当??? Laravel 命名路由表的???鑷存??，未连接真??? MySQL，也未声明业务接口权限???菜单授权或数据范围配置已经在数据库中执行成功???真??? DB 恢复后仍???继续执行数据范围、菜单权限和用户登录场景测试???

## 80. 2026-06-08 后台用户控制器响应文案多语言???

本轮继续推进 plan.md 中???后端必须支持多语言”和“所有模块文件及参数必须有详细中文注释???的要求，优先修??? `app/Http/Controllers/Admin/AdminUserController.php` 中仍然硬编码英文响应文案的问题???该控制器负责后台用户列表???删除???详情???更新和启停状???接口，如果响应文案直接写死英文，会导致后台 Blade 页面、Layui 弹层提示和后续接口错误提示无法按 Laravel 当前语言环境切换???

### 本轮维护文件

- `tests/Feature/AdminUserControllerLocalizationTest.php`：新增后台用户控制器多语???测试，验证控制器不再包含 `User list fetched`、`User not found`、`User deleted`、`User detail fetched`、`User updated`、`User status updated` 等硬编码英文响应，并验证 `resources/lang/zh-CN/admin.php` ??? `resources/lang/en/admin.php` 均存在对应语??? key???
- `app/Http/Controllers/Admin/AdminUserController.php`：将用户列表、用户不存在、删除成功???详情获取成功???更新成功和状???更新成功的响应文案全部改为 `__('admin.xxx')` 语言包调用???接口参数???数据范围和权限边界的中文???辑注释保持不变???

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

本轮验证的是后台用户控制器源码层面的多语???调用和语?????? key 完整性，未连接真??? MySQL，因此没有使用真实管理员 Token 调用 `/api/admin/users`、用户详情???更新???删除和启停接口。真??? DB `127.0.0.1:3307 / co_crmv5` 恢复后，仍需??? `zh-CN` ??? `en` 两种语言环境下分别调用接口，确认 JSON 响应 message ??? Blade/Layui 前端提示文案按当前语???正确显示???

## 81. 2026-06-08 后台系统统计控制器多语言与中文注释补???

本轮继续推进 plan.md 中???后端必须支持多语言”和“所有模块文件及参数必须有详细中文注释???的要求，重点维??? `app/Http/Controllers/Admin/AdminDashboardController.php`。该控制器是旧后台系统统计入口之???，为 Blade + Layui 仪表盘返回用户???代理???客户???待审核入金、待处理出金和今日新增用户统计???原实现中接口响??? message 直接写死 `System statistics fetched`，并且文件内存在较多英文临时说明，不利于后台多语???和后续维护???

### 本轮维护文件

- `tests/Feature/AdminDashboardControllerLocalizationTest.php`：新增后台统计控制器多语???测试，要求响应文案必须使??? `__('admin.system_statistics_fetched')`，并校验 `resources/lang/zh-CN/admin.php` ??? `resources/lang/en/admin.php` 均存??? `system_statistics_fetched`???
- `tests/Feature/AdminDashboardControllerCommentReadabilityTest.php`：新增中文注释可读???测试，要求控制器包含类职责、`dashboardData()` 参数说明、统计字段含义???`account_type` 业务含义??? `created_at` 时间戳说明???
- `app/Http/Controllers/Admin/AdminDashboardController.php`：将响应文案改为语言包调用；补齐中文逻辑注释和参数注释；清理未使用的 `ResponseCode`、`DB` 引入；删除旧英文临时说明，保留统计???辑不变???
- `resources/lang/zh-CN/admin.php`：新??? `system_statistics_fetched => 系统统计获取成功`???
- `resources/lang/en/admin.php`：新??? `system_statistics_fetched => System statistics fetched`???

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminDashboardControllerLocalizationTest.php
RED:
- 后台统计控制器仍存在硬编码英文响应：System statistics fetched
- zh-CN/admin.php 缺少 system_statistics_fetched

vendor\bin\phpunit tests\Feature\AdminDashboardControllerCommentReadabilityTest.php
RED: 后台统计控制器缺少中文注释：后台系统统计控制???
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

本轮验证的是后台统计控制器源码层面的多语???调用、语?????? key 完整性???PHP 语法和中文???辑注释完整性，未连接真??? MySQL，因此没有使用真实超级管理员 Token 调用后台统计接口。真??? DB `127.0.0.1:3307 / co_crmv5` 恢复后，仍需登录 `/admin/login`，进入后台仪表盘并确认统计接口返回的 `total_users`、`total_agents`、`total_customers`、`pending_deposits`、`pending_withdrawals`、`today_new_users` 与真实数据一致，同时确认 `zh-CN` ??? `en` 两种语言??? message 正确切换???

## 82. 2026-06-08 旧入金导入占位接口多语言与中文边界说明补???

本轮继续推进 plan.md 中???后端必须支持多语言”和“所有模块文件及参数必须有详细中文注释???的要求，重点处??? `app/Http/Controllers/Admin/DepositController.php` 中的旧入金导入占位入口???当前真实批量入金导入功能已经迁移到 `BatchAmountImportController`，并通过 `deposit_imports` 表和 `/api/admin/createDepositImport` 等接口承载；??? `DepositController#import` 仍保留兼容入口，但原实现直接返回硬编码英??? `Import feature coming soon`，不符合后端多语???要求，也缺少“不要在此处继续新增真实导入逻辑”的边界说明???

### 本轮维护文件

- `tests/Feature/DepositControllerImportLocalizationTest.php`：新增旧入金导入占位接口测试，要??? `DepositController#import` 不再包含硬编码英文响应，必须调用 `__('admin.deposit_import_feature_coming_soon')`，并要求中英文语???包存在同??? key???
- `app/Http/Controllers/Admin/DepositController.php`：将旧占位响应改为语???包调用；补齐 `import()` 方法中文参数说明，明??? `$request` 的旧上传入口含义、真实批量导入已迁移??? `BatchAmountImportController`、当前方法只保留兼容响应???
- `resources/lang/zh-CN/admin.php`：新??? `deposit_import_feature_coming_soon => 入金导入功能即将???放`???
- `resources/lang/en/admin.php`：新??? `deposit_import_feature_coming_soon => Deposit import feature coming soon`???

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
未发现匹配项???
```

### 验证边界

本轮没有实现新的 CSV/Excel 入金导入功能，也没有修改 `BatchAmountImportController` 的真实批量导入链路；只修复旧兼容入口的多语言响应与中文边界注释???真??? DB 恢复后，仍需分别验证 `/api/admin/createDepositImport` 写入 `deposit_imports` 琛ㄣ??`/api/admin/depositImportList` 列表展示、失败记录重试以及旧 `DepositController#import` 兼容入口??? `zh-CN` ??? `en` 语言环境下返回正??? message???

## 83. 2026-06-08 旧后台用户控制器中文逻辑注释补齐

本轮继续推进 plan.md 中???所有模块文件及参数必须有详细中文注释???的要求，重点维??? `app/Http/Controllers/Admin/UserController.php`。该控制器是旧后台用户管理入口之???，响应文案此前已经改为语???包调用，但文件内仍保??? `User Management Controller`、`List all users`、`Filter by user_id`、`Review identity verification` 等英文注释，??? `page`、`per_page`、`account_type`、`auth_status`、`comm_rate`、`is_enabled`、`status`、`reason`、`is_cancelled` 等参数缺少清晰中文业务含义说明???

### 本轮维护文件

- `tests/Feature/UserControllerCommentReadabilityTest.php`：新增旧后台用户控制器中文注释可读???测试，要求类职责???列表筛选参数???详情参数???更新字段???状态切换参数???实名认证审核参数和注销字段均有中文说明???
- `app/Http/Controllers/Admin/UserController.php`：补齐类级中文???辑说明，明??? `user_id` 对应 `user_infos.user_id`、`user_logins.user_id` ??? `user_auths.user_id`；补??? `index()`、`show()`、`update()`、`updateStatus()`、`reviewAuth()`、`destroy()` 的中文参数说明；将英文行内注释替换为中文；清理未使用??? `DB` 引入???

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

旧英文注释扫???
rg -n "User Management Controller|List all users|Filter by|Get user detail|Update user info|Enable/disable|Review identity|Soft delete user|Approved|Rejected|If SoftDeletes|resources/lang/\*/admin" app\Http\Controllers\Admin\UserController.php
未发现匹配项???
```

### 验证边界

本轮没有改动 `UserController` 的业务查询???更新???审核和删除逻辑，只补齐中文逻辑注释并清理未使用引入。真??? DB 恢复后，仍需用后台管理员 Token 调用旧用户管理接口，验证列表筛??????详情???资料更新???启停???实名认证审核和注销流程在真实数据上的行为是否与新后台权限和数据范围要求???鑷淬??

## 84. 2026-06-08 返佣结算控制器中文???辑注释补齐

本轮继续推进 plan.md 中???所有模块文件及参数必须有详细中文注释???的要求，重点维??? `app/Http/Controllers/Admin/CommissionController.php`。该控制器负责后台返佣记录列表???详情???单笔结算和批量结算，并且已经???过 `AdminDataScopeService` ??? `commission_records.agent_id` 做管理员数据范围限制。原文件仍保??? `Commission Settlement Controller`、`List commission settlement records`、`Settle single commission record`、`Batch settle multiple records` ??? `// Settled` 等英文注释，??? `agent_id`、`settle_status`、`ids`、数据范围校验边界没有完整中文说明???

### 本轮维护文件

- `tests/Feature/CommissionControllerCommentReadabilityTest.php`：新增返佣结算控制器中文注释可读性测试，要求类职责???列表筛选参数???详情参数???单笔结算参数???批量结算参数和数据范围判断字段均有中文说明???
- `app/Http/Controllers/Admin/CommissionController.php`：补齐类级中文???辑说明，明确返佣权限归属字段为 `commission_records.agent_id`；补??? `index()`、`show()`、`settle()`、`batchSettle()`、`denyCommissionAccessIfNeeded()` 的中文参数说明；明确 `settle_status=2` 表示已结算；??? `// Settled` 等英文注释替换为中文；清理未使用??? `Validator` 引入???

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

旧英文注释扫???
rg -n "Commission Settlement Controller|List commission|Get commission|Settle single|Batch settle|Settled" app\Http\Controllers\Admin\CommissionController.php
未发现匹配项???
```

### 验证边界

本轮没有改动返佣查询、详情???单笔结算???批量结算或数据范围判断逻辑，只补齐中文逻辑注释并清理未使用引入。真??? DB 恢复后，仍需用不同角色后台管理员分别调用返佣列表、详情???单笔结算和批量结算接口，验??? `role_data_scopes`、`admin_agent_bindings` ??? `agent_id` 数据范围限制是否阻止越权查看或结算???

## 85. 2026-06-08 后台仪表盘统计控制器中文逻辑注释补齐

本轮继续推进 plan.md 中???所有模块文件及参数必须有详细中文注释???的要求，重点维??? `app/Http/Controllers/Admin/DashboardController.php`。该控制器为后台 Blade + Layui 首页统计卡片和趋势图提供数据，原文件保留 `Dashboard Statistics Controller`、`Dashboard overview statistics`、`Detailed statistics with date range`、`Total users`、`Paid`、`Completed` 等英文注释，??? `total_users`、`pending_deposits`、`start_date`、`end_date`、`user_stats`、`deposit_stats`、`withdraw_stats` 等统计字段缺少完整中文业务说明???

### 本轮维护文件

- `tests/Feature/DashboardControllerCommentReadabilityTest.php`：新增仪表盘统计控制器中文注释可读???测试，要求类职责???概览统计字段???趋势统计字段???日期参数和入金/出金状??????均有中文说明???
- `app/Http/Controllers/Admin/DashboardController.php`：补齐类级中文???辑说明，明??? `index()` 返回统计卡片数据、`stats()` 返回趋势图数据；补齐 `total_users`、`total_agents`、`total_customers`、`pending_deposits`、`pending_withdrawals`、`start_date`、`end_date`、`user_stats`、`deposit_stats`、`withdraw_stats`、`status=02`、`status=2` 的中文说明；清理未使用的 `UserLogin` 引入和旧英文注释???

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\DashboardControllerCommentReadabilityTest.php
RED: DashboardController 缺少中文注释：后台仪表盘统计控制???
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\DashboardControllerCommentReadabilityTest.php
OK (1 test, 15 assertions)

php -l app\Http\Controllers\Admin\DashboardController.php
No syntax errors detected in app\Http\Controllers\Admin\DashboardController.php

php -l tests\Feature\DashboardControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\DashboardControllerCommentReadabilityTest.php

旧英文统计注释扫???
rg -n "Dashboard Statistics Controller|Dashboard overview|Detailed statistics|Total users|Total agents|Total customers|Pending deposits|Pending withdrawals|User registration stats|Deposit amount stats|Withdraw amount stats|Paid|Completed" app\Http\Controllers\Admin\DashboardController.php
未发现匹配项???
```

### 验证边界

本轮没有改动仪表盘统??? SQL、日期范围默认??????入金状态或出金状??????辑，只补齐中文逻辑注释并清理未使用引入。真??? DB 恢复后，仍需登录后台首页验证统计卡片和趋势图数据是否与真??? `user_infos`、`deposit_records`、`withdraw_records` 鏁版嵁涓?致，并确认日期筛选参数在 Blade 页面中能正确传???到 `stats()` 接口???

## 86. 2026-06-08 代理等级控制器中文???辑注释补齐

本轮继续推进 plan.md 中???所有模块文件及参数必须有详细中文注释???的要求，重点维??? `app/Http/Controllers/Admin/AgentLevelController.php`。该控制器负责代理等级列表???新增???更新和删除，等级编码与返佣字段会影响后台代理配置???前台代理资料展示和返佣规则维护。原文件仍保??? `Agent Level Management Controller`、`List all agent levels`、`Create agent level`、`Update agent level`、`Delete agent level` 等英文标题，??? `level_code`、`name`、`max_commission`、`min_commission`、`user_commission`、`level`、`commission_rate` 等字段缺少完整中文说明???

### 本轮维护文件

- `tests/Feature/AgentLevelControllerCommentReadabilityTest.php`：新增代理等级控制器中文注释可读性测试，要求类职责???CRUD 参数、真实表字段和旧页面兼容字段映射均有中文说明???
- `app/Http/Controllers/Admin/AgentLevelController.php`：补齐类级中文???辑说明，明确数据写??? `agent_levels` 表；补齐 `index()`、`store()`、`update()`、`destroy()`、`normalizePayload()` 的中文参数说明；说明 `level_code` 是代理等级编码，`level` 是旧页面等级编码字段，`commission_rate` 会映射为 `user_commission`???

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

旧英??? CRUD 标题扫描
rg -n "Agent Level Management Controller|List all agent levels|Create agent level|Update agent level|Delete agent level" app\Http\Controllers\Admin\AgentLevelController.php
未发现匹配项???
```

### 验证边界

本轮没有改动代理等级新增、更新???删除???辑，也没有调整 `agent_levels` 表结构或返佣字段计算方式，只补齐中文逻辑注释。真??? DB 恢复后，仍需在后台代理等级页面验证新增???更新???删除和旧字段兼容映射是否能正确写入 `level_code`、`max_commission`、`min_commission`、`user_commission`???

## 87. 2026-06-08 注销申请控制器中文???辑注释补齐

本轮继续推进 plan.md 中???所有模块文件及参数必须有详细中文注释???的要求，重点维??? `app/Http/Controllers/Admin/CancelApplyController.php`。该控制器负责后台查看??????过和拒绝客户账号注???申请，???过申请时会标记 `user_infos.is_cancelled` 并调用用户模??? `delete()`。原文件仍保??? `Account Cancellation Management Controller`、`List cancel applications`、`Approve cancellation`、`Reject cancellation`、`// Soft delete` 等英文注释，??? `status`、`reason`、`reject_reason`、`is_cancelled` 和软删除边界缺少完整中文说明???

### 本轮维护文件

- `tests/Feature/CancelApplyControllerCommentReadabilityTest.php`：新增注???申请控制器中文注释可读???测试，要求类职责???列表筛选参数???审核???过参数、拒绝参数???状态???和用户注销标记均有中文说明???
- `app/Http/Controllers/Admin/CancelApplyController.php`：补齐类级中文???辑说明，明??? `cancel_applies.status` 状??????含义：`0=待处理`、`1=宸查??过`、`-1=已拒绝`；补??? `index()`、`approve()`、`reject()` 的中文参数说明；说明 `reason` 写入 `cancel_applies.reject_reason`，`is_cancelled` 写入 `user_infos.is_cancelled`，`delete()` 执行用户软删除；清理未使用的 `Validator` 引入???

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\CancelApplyControllerCommentReadabilityTest.php
RED: CancelApplyController 缺少中文注释：后台注???申请管理控制???
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\CancelApplyControllerCommentReadabilityTest.php
OK (1 test, 11 assertions)

php -l app\Http\Controllers\Admin\CancelApplyController.php
No syntax errors detected in app\Http\Controllers\Admin\CancelApplyController.php

php -l tests\Feature\CancelApplyControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\CancelApplyControllerCommentReadabilityTest.php

旧英文标题扫???
rg -n "Account Cancellation Management Controller|List cancel applications|Approve cancellation|Reject cancellation|Soft delete" app\Http\Controllers\Admin\CancelApplyController.php
未发现匹配项???
```

### 验证边界

本轮没有改动注销申请审核通过、拒绝或用户删除逻辑，只补齐中文逻辑注释并清理未使用引入。真??? DB 恢复后，仍需在后台注???申请页面验证状???筛选??????过申请、拒绝申请???拒绝原因保存和用户软删除行为是否符合真实业务预期???

## 88. 2026-06-08 黑名单控制器中文逻辑注释补齐

本轮继续推进 plan.md 中???所有模块文件及参数必须有详细中文注释???的要求，重点维??? `app/Http/Controllers/Admin/BlacklistController.php`。该控制器是后台风控黑名单配置入口，负责黑名单列表查询???新增???更新和删除。原文件仍保??? `Blacklist Management Controller`、`List all blacklist entries`、`Add entry to blacklist`、`Update blacklist entry`、`Delete from blacklist` 等英文注释，??? `keyword`、`name`、`id_card`、`email`、`phone`、`reason`、`status` ??? `$request->all()` 写入边界缺少完整中文说明???

### 本轮维护文件

- `tests/Feature/BlacklistControllerCommentReadabilityTest.php`：新增黑名单控制器中文注释可读???测试，要求类职责???列表关键字、可匹配字段、新增字段???更新字段???删除主键和模型写入边界均有中文说明???
- `app/Http/Controllers/Admin/BlacklistController.php`：补齐类级中文???辑说明；补??? `index()`、`store()`、`update()`、`destroy()` 的中文参数说明；明确 `keyword` 同时匹配 `name`、`id_card`、`email`、`phone`；明??? `$request->all()` 写入字段??? `Blacklist` 模型 `fillable` 白名单控制???

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\BlacklistControllerCommentReadabilityTest.php
RED: BlacklistController 缺少中文注释：后台黑名单管理控制???
```

### 本轮验证记录

```text
vendor\bin\phpunit tests\Feature\BlacklistControllerCommentReadabilityTest.php
OK (1 test, 12 assertions)

php -l app\Http\Controllers\Admin\BlacklistController.php
No syntax errors detected in app\Http\Controllers\Admin\BlacklistController.php

php -l tests\Feature\BlacklistControllerCommentReadabilityTest.php
No syntax errors detected in tests\Feature\BlacklistControllerCommentReadabilityTest.php

旧英文标题扫???
rg -n "Blacklist Management Controller|List all blacklist entries|Add entry to blacklist|Update blacklist entry|Delete from blacklist" app\Http\Controllers\Admin\BlacklistController.php
未发现匹配项???
```

### 验证边界

本轮没有改动黑名单查询???新增???更新???删除???辑，也没有调整 `Blacklist` 模型可写字段，只补齐中文逻辑注释。真??? DB 恢复后，仍需在后台黑名单页面验证关键字搜索???新增???更新???删除，以及模型 `fillable` 与页面表单字段是否一致???

## 89. 2026-06-08 出金控制器中文???辑注释补齐

本轮继续推进 plan.md 中???所有模块文件及参数必须有详细中文注释???的要求，重点维??? `app/Http/Controllers/Admin/WithdrawController.php`。该控制器负责后台出金申请列表???详情???标记处理中、标记完成和拒绝出金，并通过 `AdminDataScopeService` ??? `withdraw_records.user_id` 做管理员数据范围限制。原文件仍保??? `Withdrawal Management Controller`、`List all withdrawal applications`、`Mark as processing`、`Mark as completed`、`Reject with reason`、`// Processing`、`// Completed`、`// Failed/Rejected` 等英文注释，??? `status`、`user_id`、`local_order_no`、`reason` 和数据范围字段缺少完整中文说明???

### 本轮维护文件

- `tests/Feature/WithdrawControllerCommentReadabilityTest.php`：新增出金控制器中文注释可读性测试，要求类职责???列表筛选参数???详情主键???处理中状??????完成状态???拒绝原因和数据范围判断字段均有中文说明???
- `app/Http/Controllers/Admin/WithdrawController.php`：补齐类级中文???辑说明，明确出金状态???：`0=待处理`、`1=处理中`、`2=已完成`、`3=已拒绝或失败`；补??? `index()`、`show()`、`process()`、`complete()`、`reject()`、`denyWithdrawAccessIfNeeded()` 的中文参数说明；明确 `user_id` 是数据范围判断字段；清理未使用的 `Validator` 引入???

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

旧英文状态注释扫???
rg -n "Withdrawal Management Controller|List all withdrawal applications|Get withdrawal detail|Mark as processing|Mark as completed|Reject with reason|Processing|Completed|Failed/Rejected" app\Http\Controllers\Admin\WithdrawController.php
未发现匹配项???
```

### 验证边界

本轮没有改动出金列表、详情???处理中、完成???拒绝或数据范围判断逻辑，只补齐中文逻辑注释并清理未使用引入。真??? DB 恢复后，仍需用不同角色后台管理员验证出金列表、详情???处理???完成和拒绝接口的数据范围限制，以及 `status` 状???流转是否符合真实业务流程???

## 90. 2026-06-08 凭证审核控制器中文???辑注释补齐

本轮继续推进 plan.md 中???所有模块文件及参数必须有详细中文注释和逻辑注释”的要求，重点维??? `app/Http/Controllers/Admin/VoucherController.php`。该控制器负责后台凭证提交列表???审核???过和审核拒绝，真实数据来源??? `voucher_infos` 表，审核状???由 `review_status` 字段表达。原文件仍保??? `Voucher Management Controller`、`List voucher submissions`、`Approve voucher`、`Reject voucher` 等英文标题注释，并且 `review_status`、`id`、`reason`、拒绝原因写入边界缺少完整中文说明???

### 本轮维护文件

- `tests/Feature/VoucherControllerCommentReadabilityTest.php`：新增凭证审核控制器中文注释可读性测试，要求类职责???列表筛选参数???审核状态??????审核???过参数、审核拒绝参数???拒绝原因保存字段和旧英文标题清理均有可验证约束???
- `app/Http/Controllers/Admin/VoucherController.php`：补齐类级中文???辑说明，明??? `review_status` 状?????间负 `0=待审核，1=审核通过???2=审核拒绝`；补??? `index()`、`approve()`、`reject()` 的中文参数说明；明确 `id` 表示 `voucher_infos.id`，`reason` 表示审核拒绝原因并写??? `voucher_infos.review_message`；清理未使用??? `Validator` 引入???

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
未发现匹配项???
```

### 验证边界

本轮没有改动凭证列表查询、审核???过、审核拒绝或拒绝原因保存的业务???辑，只补齐中文逻辑注释并清理未使用引入。真??? DB 恢复后，仍需在后台凭证审核页面验证凭证列表筛选???审核???过、审核拒绝???`review_status=2` 表示审核拒绝，以??? `reason` 是否正确保存??? `voucher_infos.review_message`???

## 91. 2026-06-08 新闻公告控制器中文???辑注释补齐

本轮继续推进 plan.md 中???所有模块文件及参数必须有详细中文注释和逻辑注释”的要求，重点维??? `app/Http/Controllers/Admin/NewsController.php`。该控制器负责后台新闻公告的列表查询、新增???更新???删除和发布状???切换，数据来源??? `news` 表，响应文案继续使用 `admin` 语言包保证后端多语言。原文件仍保??? `News and Announcement Controller`、`List all news`、`Create news`、`Update news`、`Delete news`、`Toggle publish status` 等旧英文标题注释，并??? `title`、`content`、`page`、`per_page`、`id`、`is_published` 的业务含义说明不完整???

### 本轮维护文件

- `tests/Feature/NewsControllerCommentReadabilityTest.php`：新增新闻公告控制器中文注释可读性测试，要求类职责???列表筛选参数???创???/更新字段、删除主键???发布状态字段???真实表来源和旧英文标题清理均有可验证约束???
- `app/Http/Controllers/Admin/NewsController.php`：补齐类级中文???辑说明，明确数据来源为 `news` 表；补齐 `index()`、`store()`、`update()`、`destroy()`、`togglePublish()` 的中文参数说明；明确 `title` 对应 `news.title`，`content` 对应 `news.content`，`id` 对应 `news.id`，`is_published` 表示是否发布???

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
未发现匹配项???
```

### 验证边界

本轮没有改动新闻公告列表、新增???更新???删除或发布状???切换业务???辑，只补齐中文逻辑注释。真??? DB 恢复后，仍需在后台新闻公告页面验证标题筛选???新增公告???更新公告???删除公告???发???/取消发布，以??? `is_published` 状???取反后前后台公告展示是否符合真实业务预期???

## 92. 2026-06-08 组别配置控制器中文???辑注释补齐

本轮继续推进 plan.md 中???所有模块文件及参数必须有详细中文注释和逻辑注释”的要求，重点维??? `app/Http/Controllers/Admin/GroupConfigController.php`。该控制器负责后台组别配置列表???新增???详情???更新和删除，数据来源为 `group_configs` 表，后台 Layui 页面提交??? `group_name` 会在控制器内映射为真实入库字??? `name`。原文件仍保??? `Group Configuration Controller`、`List group configurations`、`Create group configuration`、`Get group configuration detail`、`Update group configuration`、`Delete group configuration` 等旧英文标题注释，并??? `page`、`per_page`、`id`、`name`、`group_name`、`radix`、`category`、`has_commission`、`is_enabled`、`is_ecn`、`is_default` 的字段含义说明不完整???

### 本轮维护文件

- `tests/Feature/GroupConfigControllerCommentReadabilityTest.php`：新增组别配置控制器中文注释可读性测试，要求控制器职责???真实表来源、分页参数???主键参数???页面字段映射???组别分类和???关字段均有中文说明，并清理旧英文标题???
- `app/Http/Controllers/Admin/GroupConfigController.php`：补齐类级中文???辑说明，明确数据来源为 `group_configs` 表；补齐 `index()`、`store()`、`show()`、`update()`、`destroy()`、`normalizePayload()` 的中文参数说明；明确 `group_name 映射??? group_configs.name`，`category 取??? 1=代理组???2=用户组`，以及各???关字段含义???

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
未发现匹配项???
```

### 验证边界

本轮没有改动组别配置列表、新增???详情???更新???删除或 `normalizePayload()` 参数归一化业务???辑，只补齐中文逻辑注释。真??? DB 恢复后，仍需在后台组别配置页面验证列表分页???新增组别???编辑组别???删除组别???`group_name` ??? `group_configs.name` 的映射???`category` 代理???/用户组分类，以及 `has_commission`、`is_enabled`、`is_ecn`、`is_default` ???关保存是否符合真实业务预期???

## 93. 2026-06-08 支付通道控制器中文???辑注释补齐

本轮继续推进 plan.md 中???所有模块文件及参数必须有详细中文注释和逻辑注释”的要求，重点维??? `app/Http/Controllers/Admin/PaymentChannelController.php`。该控制器负责后台支付???道列表、新增???更新???删除和预留启用状???切换，数据来源??? `payment_channels` 琛ㄣ??原文件仍保??? `Payment Channel Management Controller` 旧英文标题注释，并且 `channel_name` 到真实字??? `payment_channels.name` 的映射???`id` 主键含义??? `toggleEnable` 启用切换边界???要更明确的中文说明???

### 本轮维护文件

- `tests/Feature/PaymentChannelControllerCommentReadabilityTest.php`：新增支付???道控制器中文注释可读???测试，要求控制器职责???真实表来源、分页参数???主键参数??????道名称兼容字段映射、编码???汇率???启用状态???扩展配置和启用切换说明均可验证???
- `app/Http/Controllers/Admin/PaymentChannelController.php`：清理旧英文标题注释，补齐???后台支付???道管理控制器???类级说明；明确数据来源??? `payment_channels` 表；补充 `channel_name 映射??? payment_channels.name`、`id 表示 payment_channels.id`、`toggleEnable 用于切换支付通道启用状???` 等中文???辑说明???

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\PaymentChannelControllerCommentReadabilityTest.php
RED: PaymentChannelController 缺少中文注释：后台支付???道管理控制???

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
未发现匹配项???
```

### 验证边界

本轮没有改动支付通道列表、新增???更新???删除或 `toggleEnable()` 业务逻辑，只补齐中文逻辑注释。真??? DB 恢复后，仍需在后台支付???道页面验证列表排序、新增???道、编辑???道、删除???道、`channel_name` ??? `payment_channels.name` 的兼容映射???`channel_code` 唯一校验、`exchange_rate` 鏁板??校验，以及启用状???是否影响前台入金???道展示???

## 94. 2026-06-08 管理员账号控制器中文逻辑注释补齐

本轮继续推进 plan.md 中???所有模块文件及参数必须有详细中文注释和逻辑注释”的要求，重点维??? `app/Http/Controllers/Admin/AdminController.php`。该控制器负责后台管理员账号列表、新增???更新???密码重置和删除，数据来源为 `admins` 表，并???过 `role_id` 关联 `roles.id` 参与菜单、按钮???接口权限和数据范围控制。原文件仍保??? `Admin User Management Controller` 旧英文标题注释，并且 `role_id`、`roles`、`admins.id`、密码留空编辑边界和 `resetPassword` 重置密码入口???要更明确的中文说明???

### 本轮维护文件

- `tests/Feature/AdminControllerCommentReadabilityTest.php`：新增管理员账号控制器中文注释可读???测试，要求控制器职责???真实表来源、主键参数???账号字段???密码新???/编辑/重置边界、角色绑定字段和删除权限边界均有中文说明???
- `app/Http/Controllers/Admin/AdminController.php`：清理旧英文标题注释，补齐???后台管理员账号管理控制器???类级说明；明确数据来源??? `admins` 表；补充 `id 表示 admins.id`、`role_id 表示绑定的后台角色`、`roles.id`、`password 留空表示编辑时保留原密码`、`resetPassword 用于重置管理员登录密码` 等中文???辑说明???

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\AdminControllerCommentReadabilityTest.php
RED: AdminController 缺少中文注释：后台管理员账号管理控制???

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
未发现匹配项???
```

### 验证边界

本轮没有改动管理员账号列表???新增???更新???密码重置???删除或角色同步业务逻辑，只补齐中文逻辑注释。真??? DB 恢复后，仍需在后台管理员页面验证列表分页、新增管理员、编辑管理员、编辑时密码留空不覆盖旧密码、重置密码???删除管理员，以??? `role_id`/`roles.id` 绑定后菜单权限???按钮权限和数据范围是否符合真实角色配置???

## 95. 2026-06-08 大代理控制器中文逻辑注释补齐

本轮继续推进 plan.md 中???所有模块的文件及参数必须有详细中文注释和???辑注释”的要求，重点维??? `app/Http/Controllers/Admin/BigAgentController.php`。该控制器负责后台大代理列表、新增???编辑和删除，数据来源为 `big_agents` 表；`username` 表示大代理登录名，`password` 表示大代理登录密码，`is_enabled` 表示大代理账号是否启用，`status` 是旧页面历史字段，仅用于兼容映射??? `is_enabled`???

### 本轮维护文件

- `tests/Feature/BigAgentControllerCommentReadabilityTest.php`：新增大代理控制器中文注释可读???测试，约束控制器职责???真实表来源、主键参数???登录名、密码???编辑密码留空边界???启用状态???旧字段兼容??? `normalizePayload` 保存字段规范化说明???
- `app/Http/Controllers/Admin/BigAgentController.php`：清理旧英文标题 `Big Agent Management Controller`，补齐???后台大代理管理控制器??????数据来源为 big_agents 琛ㄢ?????id 表示 big_agents.id”???password 留空表示编辑时保留原密码”???normalizePayload 用于规范化大代理保存字段”等精确中文逻辑说明???

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\BigAgentControllerCommentReadabilityTest.php
RED: BigAgentController 缺少中文注释：后台大代理管理控制???
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
生产控制器已包含本轮要求的中文短句，旧英文标题只保留在测试黑名单断言中???
```

### 验证边界

本轮没有改动大代理列表???新增???更新???删除或 `normalizePayload()` 业务逻辑，只补齐中文逻辑注释。真??? DB 恢复后，仍需在后台大代理页面验证列表分页、新增大代理、编辑大代理、删除大代理、编辑时密码留空保留旧密码???`is_enabled` 影响前台大代理登录，以及旧页面提??? `status` 时是否正确兼容映射到 `big_agents.is_enabled`???

## 96. 2026-06-08 代理控制器中文???辑注释补齐

本轮继续推进 plan.md 中???所有模块文件及参数必须有详细中文注释???的要求，重点维??? `app/Http/Controllers/Admin/AgentController.php`。该控制器负责后台代理列表???代理详情???下级关系???代理等级更新和代理佣金比例更新，数据来源为 `user_infos` 表，其中 `account_type=1` 表示代理账号；同时???过 `AdminDataScopeService` 对不同管理员的数据查看范围做二次限制???

### 本轮维护文件

- `tests/Feature/AgentControllerCommentReadabilityTest.php`：新增代理控制器中文注释可读性测试，约束代理数据来源、筛选参数???层级关系???等级字段???佣金字段和数据范围鉴权说明，且禁止保留旧英文标题注释???
- `app/Http/Controllers/Admin/AgentController.php`：清??? `Agent Management Controller`、`List agents only`、`Get agent detail with hierarchy info`、`Get all direct/indirect sub-agents and customers`、`Update agent level`、`Update agent commission rate` 等旧英文标题；补??? `agent_id`、`user_name`、`level`、`comm_rate`、`AgentDescendant`、`denyAgentAccessIfNeeded` 的中文参数和业务边界说明???

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
生产控制器已包含本轮要求的中文短句，旧英文标题只保留在测试黑名单断言中???
```

### 验证边界

本轮没有改动代理列表、代理详情???下级关系???代理等级更新???代理佣金比例更新或数据范围判断业务逻辑，只补齐中文逻辑注释并修正注释可读??????真??? DB 恢复后，仍需在后台代理管理页面验证代理列表分页???`agent_id` 精确筛??????`user_name` 模糊筛??????不同管理员数据范围隔离、代理详情查看???下级关系展???、代理等级更新和佣金比例更新是否符合真实角色与代理链路配置???

## 97. 2026-06-08 入金控制器中文???辑注释补齐

本轮继续推进 plan.md 中???后端必须支持多语言”和“所有模块文件及参数必须有详细中文注释???的要求，重点维??? `app/Http/Controllers/Admin/DepositController.php`。该控制器负责后台入金记录列表???入金详情???审核???过、审核驳回和旧入金导入占位入口，数据来源??? `deposit_records` 表；入金审核属于资金敏感操作，因此必须明??? `status`、`user_id`、`local_order_no`、`id`、审核???过/驳回状???码和数据范围鉴权边界???

### 本轮维护文件

- `tests/Feature/DepositControllerCommentReadabilityTest.php`：新增入金控制器中文注释可读性测试，约束列表筛???参数???详情主键???审核???过、审核驳回???状态码、驳回原因和数据范围判断说明，且禁止保留旧英文标题注释???
- `app/Http/Controllers/Admin/DepositController.php`：清??? `Deposit Management Controller`、`List all deposit records`、`Get deposit detail`、`Approve deposit`、`Reject deposit`、`Further logic to update user balance can be added here`、`Failed/Rejected` 等旧英文注释；补??? `status 表示入金审核状???`、`status=02 表示入金已审核???过`、`status=09 表示入金审核驳回或失败`、`payment_time`、`reason` ??? `denyDepositAccessIfNeeded` 的中文???辑说明???

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

关键词复核：rg -n "Deposit Management Controller|List all deposit records|Get deposit detail|Approve deposit|Further logic to update user balance can be added here|Reject deposit|Failed/Rejected|后台入金管理控制器|status=02 表示入金已审核???过|status=09 表示入金审核驳回或失败|denyDepositAccessIfNeeded 用于" app\Http\Controllers\Admin\DepositController.php tests\Feature\DepositControllerCommentReadabilityTest.php
生产控制器已包含本轮要求的中文短句，旧英文标题只保留在测试黑名单断言中???
```

### 验证边界

本轮没有改动入金列表、详情???审核???过、审核驳回???旧导入占位入口或数据范围判断业务???辑，只补齐中文逻辑注释并保持旧导入占位接口继续从语???包读取响应???真??? DB 恢复后，仍需在后台入金审核页面验证列表分页???`status` 筛??????`user_id` 筛??????`local_order_no` 模糊筛??????详情查看???审核???过写入 `status=02` ??? `payment_time`、审核驳回写??? `status=09` ??? `remarks`，以及不同管理员在数据范围配置下的数据隔离和按钮权限???

## 98. 2026-06-08 前台认证注册验证码多语言补齐

本轮继续推进 plan.md 中???后端也必须支持多语???”的要求，重点维??? `app/Http/Controllers/Front/AuthController.php` 的注册验证码链路。该控制器原先在注册图形验证码错误???邮箱验证码错误、注册预???查兜底???邮箱验证码发???频率限制???邮件发送失败，以及验证码邮件标???/正文中直接写入英文文案，导致前台 Layui/Blade 页面切换语言后接口提示仍可能显示硬编码英文???

### 本轮维护文件

- `tests/Feature/FrontAuthControllerLocalizationTest.php`：新增前台认证控制器多语???测试，约??? `Invalid captcha`、`Invalid email verification code`、`Validation failed`、`Please request the email code later`、`Email send failed`、邮件标题和邮件正文不再硬编码在控制器中，并要求中英文语???包存在对??? key???
- `app/Http/Controllers/Front/AuthController.php`：将注册验证码错误改??? `auth.invalid_captcha`，邮箱验证码错误改为 `auth.invalid_email_code`，注册预???查兜底改??? `response.validation_failed`，频率限制改??? `response.rate_limited`，邮件发送失败改??? `response.email_send_failed`，验证码邮件标题和正文改??? `auth.registration_verification_mail_subject` ??? `auth.registration_verification_mail_body`???
- `resources/lang/zh-CN/auth.php`：新??? `id_card_exists`、`invalid_captcha`、`invalid_email_code`、`registration_verification_mail_subject`、`registration_verification_mail_body`???
- `resources/lang/en/auth.php`：新增同名英文语??? key???

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
生产控制器已改为语言??? key；硬编码英文仅保留在测试黑名单和英文语言包翻译???中???
```

### 验证边界

本轮没有改动注册校验、验证码缓存、邮箱验证码缓存、发送频率限制或邮件发???业务???辑，只替换响应文案和邮件文案来源???真??? SMTP 与真??? DB 恢复后，仍需在前台注册页验证图形验证码错误???邮箱验证码错误、重复邮???/手机???/证件号预???查???频率限制???邮件发送失败和邮件正文??? `zh-CN` ??? `en` 语言环境下均能显示正确文案???

## 99. 2026-06-08 前台认证控制器中文???辑注释补齐

本轮继续推进 plan.md 中???所有模块的文件及参数必须有详细中文注释和???辑注释包括参数的注释及功能作用”的要求，重点维??? `app/Http/Controllers/Front/AuthController.php`。该控制器承担前台登录???注册???旧登录注册兼容、验证码、邮箱验证码、注???、刷??? Token、改密和???请人校验等入口，属于前台代理商和普???客户登录后的权限菜单链路前置入口???原文件仍保??? `Front User Authentication Controller`、`Show login page`、`User Login`、`Refresh Token`、`Change Password` 等英文标题注释，并且 `account_type`、`captcha_key`、`captcha_code`、`email_code`、`loginUid`、`loginPassword` 等参数缺少成体系的中文业务说明???

### 本轮维护文件

- `tests/Feature/FrontAuthControllerCommentReadabilityTest.php`：新增前台认证控制器中文注释可读性测试，静???读取源码，不连接真实数据库；约束登录???注册???旧接口兼容、验证码、邮箱验证码??? Token 相关参数必须保留中文逻辑说明，并禁止保留旧英文标题注释???
- `app/Http/Controllers/Front/AuthController.php`：补齐类级???属性???构造函数???登录页、注册页、旧注册链接、注册提交???新版登录???旧版登录???注???、刷??? Token、改密???邀请人校验、邮箱检查???图形验证码、注册预???查???邮箱验证码发??????字段标准化、验证码缓存键等中文逻辑注释；明??? `registrationService`、`jwtService`、`account_type=1`、`account_type=2`、`captcha_key`、`captcha_code`、`email_code`、`loginUid`、`loginPassword` 等参数含义???
- `app/Http/Controllers/Front/AuthController.php`：删??? `showLogin()` ??? `showRegister()` 中重复的不可??? `return view(...)`，不改变实际返回页面，只清理注释补齐过程中发现的死代码???

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontAuthControllerCommentReadabilityTest.php
RED:
- Front AuthController 缺少中文逻辑注释：处理前台用户登录???注册???注???、令牌刷???
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

本轮没有改动前台注册、登录???旧登录兼容、验证码缓存、邮箱验证码缓存、邮箱发送???JWT 签发、SSO、邀请人规则或密码修改业务???辑，只补齐中文逻辑注释并清理两个不可达重复 return。真??? DB、SMTP 和浏览器环境恢复后，仍需用代理账号和普???客户账号分别登??? Layui 前台，验证登录后 `POST /api/front/navigation/menus` 能按 `user_logins.role_id` 返回代理/客户菜单树，并验证注册页图形验证码???邮箱验证码发??????旧登录接口和新版登录接口在 `zh-CN` ??? `en` 语言下的提示与跳转行为???

## 100. 2026-06-08 前台账户控制器中文???辑注释补齐

本轮继续推进 plan.md 中???所有模块的文件及参数必须有详细中文注释和???辑注释包括参数的注释及功能作用”的要求，重点维??? `app/Http/Controllers/Front/AccountController.php`。该控制器承载前台账户综合???账户余额???凭证提交???旧凭证上传、旧账户类型切换和凭证列表接口，是代理商和普通客户登录后账户菜单页面的核心数据来源???原文件仍保??? `Front Account Management Controller`、`Get current user account info`、`Get detailed balance breakdown`、`Upload voucher images for review`、`List submitted vouchers` 等英文标题注释，并且 `total_funds`、`equity`、`used_margin`、`avail_margin`、`comm_rate`、`voucherimg`、`is_enc`、`review_status` 等参数缺少完整中文业务说明???

### 本轮维护文件

- `tests/Feature/FrontAccountControllerCommentReadabilityTest.php`：新增前台账户控制器中文注释可读性测试，静???读取源码，不连接真实数据库；约束账户概览???余额???凭证???旧接口兼容和账户类型切换参数必须保留中文???辑说明，并禁止保留旧英文标题注释???
- `app/Http/Controllers/Front/AccountController.php`：补齐类级???账户综合???余额明细???当前用户解析???账户指标组装???客户???别统计、新版凭证提交???旧凭证上传、旧账户类型切换、凭证列表???旧成功响应和旧失败响应的中文???辑注释???
- `app/Http/Controllers/Front/AccountController.php`：明??? `user_id`、`total_funds`、`equity`、`used_margin`、`avail_margin`、`comm_rate`、`images`、`remarks`、`voucherimg`、`voucherremark`、`is_enc`、`review_status`、`msg`、`err`、`col` 等参数含义???

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontAccountControllerCommentReadabilityTest.php
RED:
- Front AccountController 缺少中文逻辑注释：处理账户信息???余额明细???凭证提交和旧前台账户接口兼???
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

本轮没有改动账户综合、余额明细???凭证上传???旧凭证上传、账户类型切换???凭证列表???订单统计???客户???别统计或旧响应结构业务逻辑，只补齐中文逻辑注释并替换旧英文标题注释。真??? DB、文件上传和浏览器环境恢复后，仍???分别用代理账号和普???客户账号进??? Layui 前台账户综合、账户余额???提交凭证页面，验证 `/api/front/account/profile`、`/api/front/account/balance`、`/api/front/account/vouchers`、`/api/front/account/voucher-submissions`、`user/user_voucher_save`、`user/change_account_save` 在真实数据下返回字段、上传文件???审核状态筛选和旧页面错误码均符合现有业务???

## 101. 2026-06-08 前台入金控制器中文???辑注释补齐

本轮继续推进 plan.md 中???所有模块文件及参数必须有详细中文注释和逻辑注释，包括参数的注释及功能作用???的要求，重点维??? `app/Http/Controllers/Front/DepositController.php`。该控制器负责前台入金页初始化数据???新版入金申请???旧前台入金申请兼容、OTC 旧入口兼容???入金历史列表???支付???道解析、入金限额???入金可用状态和系统配置读取，是代理商与普???客户前台资金菜单的核心链路之一???

### 本轮维护文件

- `tests/Feature/FrontDepositControllerCommentReadabilityTest.php`：新增前台入金控制器中文注释可读性测试，静???读取源码，不连接真实数据库；约束入金页面???入金提交???旧接口兼容、支付???道、限额???系统配置???状态码和旧英文标题注释清理???
- `app/Http/Controllers/Front/DepositController.php`：补??? `depositPage`、`submitDeposit`、`deposit_request`、`deposit_request_otc`、`depositHistory`、`store`、`records`、`frontChannels`、`resolvePaymentChannel`、`amountLimits`、`depositAvailability`、`fallbackChannels`、`fallbackChannel`、`legacyChannelMeta`、`configValue` 的中文???辑注释；明??? `amount`、`deposit_amt_usd`、`channel`、`pay_channel`、`passageway`、`local_order_no`、`status=01`、`deposit_limits`、`exchange_rates`、系统开关和时间窗口的业务含义???

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

本轮没有改动前台入金页面初始化???新版入金提交???旧版入金提交???OTC 兼容入口、入金历史???支付???道解析、限额计算???系统开关或时间窗口业务逻辑，只补齐中文逻辑注释并清理旧英文标题注释。真??? DB 恢复后，仍需用代理账号和普???客户账号分别验??? `/api/front/deposits/form-options`、`/api/front/deposits/submissions`、`/api/front/deposits/history`、`user/deposit_request`、`user/deposit_request_otc`，确认支付???道来自 `payment_channels` 或兼容配置???`status=01` 待审核记录写入正确???`local_order_no` 唯一、入金限额和系统???关生效，以及前台 Layui 入金页支付???道 Tab 渲染和历史列表字段均符合真实业务数据???

## 102. 2026-06-08 前台出金控制器中文???辑注释补齐

本轮继续推进 plan.md 中???所有模块文件及参数必须有详细中文注释和逻辑注释，包括参数的注释及功能作用???的要求，重点维??? `app/Http/Controllers/Front/WithdrawController.php`。该控制器负责前台出金页初始化数据???新版出金申请???旧前台出金申请兼容、OTC 旧入口兼容???出金历史列表???出金手续费、可出金金额和出金可用???判断，是代理商与普通客户前台资金菜单的核心链路之一???

### 本轮维护文件

- `tests/Feature/FrontWithdrawControllerCommentReadabilityTest.php`：新增前台出金控制器中文注释可读性测试，静???读取源码，不连接真实数据库；约束出金页面???出金提交???旧接口兼容、OTC 入口、出金历史???手续费、银行卡、状态码、可出金余额和时间窗口说明必须存在???
- `app/Http/Controllers/Front/WithdrawController.php`：清??? `Front Withdraw Management Controller`、`Get withdraw page data (bank info, rates, limits)`、`Submit withdrawal request`、`List withdrawal records`、`Legacy method for store`、`Legacy method for records`、`Check Risk Ratio`、`Calculate fee`、`Pending` 等旧英文标题或短注释；补??? `withdrawPage`、`submitWithdraw`、`withdraw_request`、`withdraw_request_OTC`、`withdrawHistory`、`store`、`records`、`withdrawDisplayOrderNo`、`withdrawSourceText`、`withdrawableAmount`、`withdrawAvailability`、`isNowInTimeWindow` 的中文???辑注释???
- `app/Http/Controllers/Front/WithdrawController.php`：明??? `amount`、`withdraw_amt`、`password`、`withdraw_password`、`withdraw_psw`、`agree`、`bank_no`、`withdraw_limits`、`fee`、`status=0`、`local_order_no`、`applystatus`、`total_funds`、`avail_margin`、`auth_status=1`、`withdrawal_enabled`、`withdrawal_weekend_enabled` 和出金时间窗口的业务含义???

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontWithdrawControllerCommentReadabilityTest.php
RED:
- Front WithdrawController 缺少中文逻辑注释：处理出金页面配置???出金申请???旧前台出金接口兼容和出金历史记???
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

关键词复核：rg -n "Front Withdraw Management Controller|Handles withdrawal page data|Get withdraw page data|Submit withdrawal request|List withdrawal records|Legacy method for store|Legacy method for records|Check Risk Ratio|Calculate fee|Pending|前台出金管理控制器|withdrawPage 用于返回前台出金页初始化数据|status=0 表示出金申请待后台审???" app\Http\Controllers\Front\WithdrawController.php tests\Feature\FrontWithdrawControllerCommentReadabilityTest.php
生产控制器已包含本轮要求的中文短语，旧英文标题只保留在测试黑名单断言中???
```

### 验证边界

本轮没有改动前台出金页面初始化???新版出金提交???旧版出金提交???OTC 兼容入口、出金历史???手续费计算、持仓风险检查???可出金余额、实名状态检查???系统开关或时间窗口业务逻辑，只补齐中文逻辑注释并清理旧英文标题注释。真??? DB 恢复后，仍需用代理账号和普???客户账号分别验??? `/api/front/withdrawals/form-options`、`/api/front/withdrawals/submissions`、`/api/front/withdrawals/history`、`user/withdraw_request`、`user/withdraw_request_OTC`，确认银行卡信息来自 `user_auths`、`status=0` 待审核记录写入正确???`local_order_no` 唯一、固定手续费和比例手续费计算正确、金额上下限和系统开关生效，以及前台 Layui 出金页表单???历史列表和旧表格字段均符合真实业务数据???

## 103. 2026-06-08 前台订单控制器中文???辑注释补齐

本轮继续推进 plan.md 中???所有模块文件及参数必须有详细中文注释和逻辑注释，包括参数的注释及功能作用???的要求，重点维??? `app/Http/Controllers/Front/OrderController.php`。该控制器负责前台当前持仓订单???历史平仓订单???旧前台订单搜索入口、旧前台订单详情弹层、订单所属用户资料???代理链路和订单返佣明细展示，是代理商与普???客户前台交易菜单的核心链路之一???

### 本轮维护文件

- `tests/Feature/FrontOrderControllerCommentReadabilityTest.php`：新增前台订单控制器中文注释可读性测试，静???读取源码，不连接真实数据库；约束持仓订单???历史订单???旧搜索入口、订单详情???订单号筛??????交易品种筛选???强平筛选???代理链路和返佣明细说明必须存在???
- `app/Http/Controllers/Front/OrderController.php`：清??? `Front Order Management Controller`、`Handles open and closed trading orders for users.`、`List current open orders`、`List historical closed orders` 等旧英文标题注释；补??? `openOrders`、`openOrderSearch`、`openOrder2Search`、`closedOrders`、`closeOrderSearch`、`closeOrderSearchV2`、`closeOrder2Search`、`openOrderDetail`、`closeOrderDetail`、`userDetail`、`orderChain`、`legacyOrderDetailHtml`、`legacyOrderChainHtml`、`legacyCommissionDetailsHtml`、`legacyDetailItem` 的中文???辑注释???
- `app/Http/Controllers/Front/OrderController.php`：明??? `orderId`、`ticket`、`symbol`、`date_from`、`date_to`、`open_time`、`close_time`、`is_coercion`、`reason`、`commission_details`、`account_type=1`、`account_type=2`、`family_tree`、`viewerAgentId`、`orderType`、`role`、`title` 等字段和参数的业务含义???

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontOrderControllerCommentReadabilityTest.php
RED:
- Front OrderController 缺少中文逻辑注释：处理当前持仓订单???历史平仓订单???旧前台订单搜索入口和订单详情弹???
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

关键词复核：rg -n "Front Order Management Controller|Handles open and closed trading orders|List current open orders|List historical closed orders|前台订单管理控制器|openOrders 用于返回当前用户可见的持仓订单列表|closedOrders 用于返回当前用户可见的历史平仓订单列表|orderChain 用于??? family_tree" app\Http\Controllers\Front\OrderController.php tests\Feature\FrontOrderControllerCommentReadabilityTest.php
生产控制器已包含本轮要求的中文短语，旧英文标题只保留在测试黑名单断言中???
```

### 验证边界

本轮没有改动前台持仓订单查询、历史订单查询???旧搜索入口、订单详??? HTML、代理链路???返佣明细???数据可见范围过滤或分页汇???业务???辑，只补齐中文逻辑注释并清理旧英文标题注释。真??? DB 恢复后，仍需用代理账号和普???客户账号分别验??? `/api/front/orders/open`、`/api/front/orders/closed`、`user/order/openOrderSearch`、`user/order/closeOrderSearch`、旧订单详情弹层入口，确认订单号筛??????交易品种筛选???开???/平仓日期筛??????强平筛选???代理树数据隔离、普通客户仅看自身订单???代理订单返佣明细和旧页面字段均符合真实业务数据???

## 104. 2026-06-08 前台礼品中心控制器中文???辑注释补齐

本轮继续推进 plan.md 中???所有模块文件及参数必须有详细中文注释和逻辑注释，包括参数的注释及功能作用???的要求，重点维??? `app/Http/Controllers/Front/GiftController.php`。该控制器负责前台收货地???列表、旧前台地址分页搜索、地???新增、地???更新、旧前台地址新增或编辑统???入口、地???删除、可兑换礼品列表和礼品发货历史搜索，是代理商与普通客户前台礼品中心菜单的核心链路之一???

### 本轮维护文件

- `tests/Feature/FrontGiftControllerCommentReadabilityTest.php`：新增前台礼品中心控制器中文注释可读性测试，静???读取源码，不连接真实数据库；约束收货地???字段、旧字段别名、默认地???唯一规则、礼品列表???可兑换礼品、已发货礼品和旧英文标题注释清理???
- `app/Http/Controllers/Front/GiftController.php`：清??? `Front Gift Center Controller`、`Handles user addresses and gift redemption/history.`、`List user addresses`、`Add new address`、`Update address`、`Build an update payload from the fields that were actually submitted.`、`Delete address`、`List available gifts / shipped gifts`、`Shipped gifts (from GiftShipment)`、`Available gifts (dummy list if no GiftInfo model exists)` 等旧英文标题或短注释???
- `app/Http/Controllers/Front/GiftController.php`：补??? `addressList`、`addressSearch`、`addAddress`、`updateAddress`、`addressUpdate`、`deleteAddress`、`giftList`、`giftSearch` 的中文???辑注释，明??? `recipient_name`、`receiver_name`、`recipient_phone`、`phone`、`recipient_address`、`address`、`is_default`、`rec_id`、`available_gifts`、`shipped_gifts`、`gift_name`、`shipped_at`、`page/limit/per_page` 等参数或返回字段的业务含义???

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontGiftControllerCommentReadabilityTest.php
RED:
- Front GiftController 缺少中文逻辑注释：处理收货地???列表、地???新增、地???更新、地???删除、礼品列表和礼品发货历史
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

本轮没有改动收货地址查询、新增???更新???删除???旧前台地址统一保存入口、礼品可兑换列表、礼品发货历史搜索???分页结构???默认地???唯一规则或当前用户数据隔离业务???辑，只补齐中文逻辑注释并清理旧英文标题注释。真??? DB 恢复后，仍需用代理账号和普???客户账号分别验??? `/api/front/gift-addresses`、`/api/front/gifts`、`user/address/search`、`user/address/update`、`user/gift/search`，确??? `user_addresses` ??? `gift_shipments` 真实数据下的字段映射、默认地???唯一、旧字段别名兼容、发货时间筛选???礼品名称筛选和前台 Layui/Naive 页面表格字段均符合现有业务???

## 105. 2026-06-08 前台返佣控制器中文???辑注释补齐

本轮继续推进 plan.md 中???所有模块文件及参数必须有详细中文注释和逻辑注释，包括参数的注释及功能作用???的要求，重点维??? `app/Http/Controllers/Front/CommissionController.php`。该控制器负责前台实时返佣列表???旧前台实时返佣搜索、旧前台返佣详情弹层、返佣历史???返佣趋势统计??????别维度统计、直属下级代理转账???项和佣金转账，是代理商前台返佣菜单和数据范围隔离的核心链路之一???

### 本轮维护文件

- `tests/Feature/FrontCommissionControllerCommentReadabilityTest.php`：新增前台返佣控制器中文注释可读性测试，静???读取源码，不连接真实数据库；约束实时返佣???旧返佣详情、返佣历史???统计分析???转账???项、佣金转账和旧英文标题注释清理???
- `app/Http/Controllers/Front/CommissionController.php`：清??? `Front Commission Management Controller`、`Handles real-time commission calculation, history, and transfers.`、`Calculate real-time commission for current agent`、`Get settled commission history`、`Commission transfer to sub-agent`、`Verify sub-agent belongs to current agent`、`Handle transfer...`、`Deduct from agent`、`Add to sub-agent`、`Receiver deposit side: DBCT...` 等旧英文标题或短注释???
- `app/Http/Controllers/Front/CommissionController.php`：补??? `commissionService`、`realTime`、`realtimeRebateSearch`、`realtimeRebateDetail`、`userDetail`、`orderChain`、`legacyDetailItem`、`currentAgentOrderCommission`、`history`、`commissionHistoryAnalytics`、`transferAgentOptions`、`transfer` 的中文???辑注释，明??? `userId`、`orderId`、`detail_commission`、`current_commission_amount`、`orderNo`、`role`、`date_from`、`date_to`、`dataType`、`sub_agent_id`、`amount`、`remark`、`DBCT`、`WBCT` 等参数和字段的业务含义???

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontCommissionControllerCommentReadabilityTest.php
RED:
- Front CommissionController 缺少中文逻辑注释：处理实时返佣计算???返佣历史???返佣统计分析???旧前台返佣详情和佣金转???
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

本轮没有改动实时返佣查询、旧前台返佣详情、返佣历史查询???返佣统计分析???直属下级代理???项、佣金转账???余额扣增???事务写入或数据范围业务逻辑，只补齐中文逻辑注释并清理旧英文标题注释。真??? DB 恢复后，仍需用代理账号验??? `/api/front/commissions/realtime`、`/api/front/commissions/history`、`/api/front/commissions/transfer-agent-options`、`/api/front/commissions/transfers`、`user/realtime/realtimeRebateSearch` 等接口，确认代理树范围???订单关闭时间筛选???当前代理返佣金额??????级返佣明细、返佣趋势统计??????别维度统计、直属代理转账???项、`DBCT/WBCT` 双流水和余额变更均符合真实业务数据???

## 106. 2026-06-08 前台代理管理控制器中文???辑注释补齐

本轮继续推进 plan.md 中???所有模块文件及参数必须有详细中文注释和逻辑注释，包括参数的注释及功能作用???的要求，重点维??? `app/Http/Controllers/Front/AgentController.php`。该控制器负责下级代理列表???直属客户列表???直属客户佣金转账???代理等级?????????代理层级路径???直属客户明细???代理统计???用户详情???登录历史???代理等级确认???客户组别变更申请和旧前台兼容入口，是前台代理商菜单、按钮操作和数据范围隔离的核心链路之??????

### 本轮维护文件

- `tests/Feature/FrontAgentControllerCommentReadabilityTest.php`：新增前台代理管理控制器中文注释可读性测试，静???读取源码，不连接真实数据库；约束代理树、直属客户???佣金转账???等级确认???组别变更???用户详情和旧前台兼容入口必须有中文逻辑说明???
- `app/Http/Controllers/Front/AgentController.php`：清??? `Front Agent Management Controller`、`Provides sub-agent and customer lists, and statistics for agents.`、`List all sub-agents (direct and indirect)`、`List all customers (direct and indirect)`、`Add hierarchy and trade stats for each agent`、`Get agent statistics`、`View/confirm agent level`、`Request customer group change`、`Verify the target exists...`、`Confirm the requested group...`、`Verify target user is descendant.`、`Base columns follow...` 等旧英文标题或短注释???
- `app/Http/Controllers/Front/AgentController.php`：补??? `familyTreeService`、`subList`、`proxyListSearch`、`customerList`、`directCustListSearch`、`directUserCommTrans`、`getSubAgentsGrpIdList`、`getParentPath`、`directCustDetailList`、`statistics`、`userDetail`、`agentLevelDetailPayload`、`userLoginHistory`、`confirmLevel`、`confirmLevelChange`、`groupChangeList`、`groupChange`、`canViewUser`、`isDirectTransferTarget`、`availableGroupOptions` 等中文???辑注释???
- `app/Http/Controllers/Front/AgentController.php`：明??? `parent_id`、`direct_only`、`descendant_type=1`、`descendant_type=2`、`available_groups`、`depositId`、`comm_money`、`password`、`DBCT`、`WBCT`、`agentGId`、`event_name`、`puid`、`agent_gId`、`target_user_id`、`new_group_id` 等旧前台和新版接口参数含义???

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontAgentControllerCommentReadabilityTest.php
RED:
- Front AgentController 缺少中文逻辑注释：处理下级代理列表???直属客户列表???代理统计???等级确认???客户组别变更???用户详情和旧前台兼容入???
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

本轮没有改动下级代理列表、客户列表???直属客户转账???代理等级?????????层级路径???直属客户明细???代理统计???用户详情???登录历史???等级确认???组别变更申请???数据范围校验或旧前台响应结构，只补齐中文???辑注释并清理旧英文标题注释。真??? DB 恢复后，仍需用代理账号验??? `/api/front/agents/direct`、`/api/front/agents/direct-customers`、`/api/front/agents/level-confirmation`、`/api/front/agents/level-confirmation/changes`、`/api/front/agents/group-changes`、`/api/front/customers/commission-transfers`、`/api/front/users/{user}`、`user/proxy/proxyListSearch`、`user/proxy/direct_cust_detail_list`、`user/proxy/parentPath`、`user/proxy/confirmLevelChange`、`user/cust/change/group_edit` 等接口，确认代理树权限???直???/非直属筛选???客户组别?????????等级确认比例来源???旧前台字段映射??? DB 真实数据隔离均符合业务???

## 107. 2026-06-08 前台资金流水控制器中文???辑注释补齐

本轮继续推进 plan.md 中???所有模块的文件及参数必须有详细中文注释和???辑注释，包括参数的注释及功能作用???的要求，重点维??? `app/Http/Controllers/Front/FlowController.php`。该控制器负责前台入金流水???出金流水???返佣流水聚合查询，兼容旧前台入???/出金/出金申请/直属客户入出金搜索入口，并提供直属客户入金流??? CSV 导出和下载，是前台资金菜单???代理数据范围和旧项目迁移兼容的核心链路之一???

### 本轮维护文件

- `tests/Feature/FrontFlowControllerCommentReadabilityTest.php`：新增前台资金流水控制器中文注释可读性测试，静???读取源码，不连接真实数据库；约??? `flow_type`、`flowType`、`date_from`、`date_to`、`deposit_records`、`withdraw_records`、`commission_records`、`local_order_no`、`third_order_no`、`flow_type_text`、`totalRow`、`withdraw_source`、直属客户导出参数???下载参数和旧前台搜索入口必须有中文逻辑说明???
- `app/Http/Controllers/Front/FlowController.php`：清??? `Front Account Flow Controller`、`Lists all account transactions including deposits, withdrawals, and commissions.`、`List all account transactions (deposits, withdrawals, commissions)`、`Query deposits`、`Query withdrawals`、`Query commissions.`、`Combine and paginate`、`commission_records uses the rebuilt schema field commission_amount`、`All three source tables use integer timestamps`、`Assume 02 is completed` 等旧英文标题或短注释???
- `app/Http/Controllers/Front/FlowController.php`：补??? `accountFlow`、`typedFlow`、`applyWithdrawSourceFilter`、`depositExport`、`downloadFile`、`depositFlowSearch`、`withdrawalFlowSearch`、`withdrawApplyFlowSearch`、`directDepositFlowSearch`、`directWithdrawalFlowSearch`、`legacyTypedFlow`、`legacyCurrentUserId`、`withdrawDisplayOrderNo`、`withdrawSourceText`、`flowScopeUserIds` 的中文功能说明???参数含义和数据边界说明???

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontFlowControllerCommentReadabilityTest.php
RED:
- Front FlowController 缺少中文逻辑注释：处理入金流水???出金流水???返佣流水???直属客户流水???直属代理流水???旧前台流水搜索和导出下???
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

- `POST /api/front/flows/account`：新版账户流水聚合入口，`flow_type=all` 时聚??? `deposit_records`、`withdraw_records`、`commission_records`，非 all 时按类型分流到单类流水查询???
- `POST /user/flow/depositFlowSearch`：旧前台入金流水搜索入口，内部写??? `flow_type=deposit` 后复??? `accountFlow`???
- `POST /user/flow/withdrawalFlowSearch`：旧前台出金流水搜索入口，内部写??? `flow_type=withdraw` 后复??? `accountFlow`???
- `POST /user/flow/withdrawApplyFlowSearch`：旧前台出金申请流水搜索入口，内部写??? `flow_type=withdraw_apply` 后复??? `accountFlow`???
- `POST /user/flow/directDepositFlowSearch`：旧前台直属客户入金流水搜索入口，内部写??? `flow_type=direct_deposit` 后复??? `accountFlow`???
- `POST /user/flow/directWithdrawalFlowSearch`：旧前台直属客户出金流水搜索入口，内部写??? `flow_type=direct_withdraw` 后复??? `accountFlow`???
- `GET /user/flow/directDepositExport` 与下载路由：继续通过 `depositExport` 生成直属客户入金 CSV，???过 `downloadFile` 下载 `storage/app/front_exports` 下的安全文件名???

### 验证边界

本轮没有改动入金流水查询、出金流水查询???返佣流水查询???直属客???/直属代理可见范围、日期筛选???出金来源筛选???分页汇总???CSV 导出或下载业务???辑，只补齐中文逻辑注释并清理旧英文标题注释。当前本机真??? MySQL `127.0.0.1:3307` 仍未连接验证，因此未声明真实 DB 数据已经覆盖；真??? DB 恢复后，仍需??? `agent@test.com / agent123` 登录前台 Layui ??? Naive，分别验??? `/api/front/flows/account`、旧前台流水搜索接口、直属客户入金导出???`withdraw_source=bank_transfer/crypto_currency`、`date_from/date_to`、`direct_deposit_userId`、`direct_deposit_id` 等真实数据筛选结果和菜单权限数据隔离???

## 108. 2026-06-08 前台???户申请控制器中文逻辑注释补齐与礼品列??? GET 路由对齐

本轮继续推进 plan.md 中???所有模块的文件及参数必须有详细中文注释和???辑注释，包括参数的注释及功能作用???的要求，重点维??? `app/Http/Controllers/Front/CancelController.php`。该控制器负责当前前台用户提交销户申请???旧前台 `ajaxCancelAccount` 兼容入口和最近一次销户申请状态查询；提交前会校验重复待审申请、未平仓订单、账户???资金和账户???值，是前台账户注???页面与后台注???审核页面之间的关键链路???

### 本轮维护文件

- `tests/Feature/FrontCancelControllerCommentReadabilityTest.php`：新增前台销户申请控制器中文注释可读性测试，静???读取源码，不连接真实数据库；约??? `reason`、`cancel_applies`、`status=0`、`cancel_remark`、`reject_reason`、重复待审校验???`UserTrade::open`、`total_funds`、`equity`、`cancelRemark`、`remark` 和最近一次状态查询必须有中文逻辑说明???
- `app/Http/Controllers/Front/CancelController.php`：清??? `Front account cancellation controller.`、`This controller rebuilds the old front-office cancellation workflow:`、`Submit an account cancellation request for the current front user.`、`Prevent duplicate pending requests.`、`Open orders must be closed before cancellation`、`Compatibility fallback...` 等旧英文标题或短注释???
- `routes/front.php`：将礼品列表接口??? `POST /api/front/gifts` 对齐??? `GET /api/front/gifts`，与 Layui 礼品列表页面、Naive 礼品模块和现有回归测试的资源风格接口约束???鑷淬??
- `resources/front/layui/gift/list.blade.php`：补??? `'method' => 'GET'`，确??? Layui 礼品列表页面??? GET 请求 `/api/front/gifts`???

### 本轮 TDD 与调试记???

```text
vendor\bin\phpunit tests\Feature\FrontCancelControllerCommentReadabilityTest.php
RED:
- Front CancelController 缺少中文逻辑注释：前台销户申请控制器
- Front CancelController 仍残留旧英文注释标题：Front account cancellation controller.

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_front_agent_gift_and_cancel_legacy_operations_have_resource_style_api_routes
RED:
- Route::get('/gifts', 'GiftController@giftList') route is missing.
- 礼品列表 Blade 缺少 'method' => 'GET'

根因???
- 前端 Layui ??? Naive 均按 GET /api/front/gifts 调用礼品列表，但 routes/front.php 仍注册为 POST，Layui Blade 也没有显式声??? GET 方法???
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

- `POST /api/front/account/cancellation`：返回当前前台用户最近一次销户申请状态，页面用于展示审核状??????申请原因???拒绝原因和时间???
- `POST /api/front/account/cancellation-applications`：提交当前前台用户销户申请，参数 `reason` 表示???户原因???
- `POST /user/center/ajaxCancelAccount`：旧前台???户提交兼容入口，支持 `reason`、`cancelRemark`、`remark` 原因字段，内部复??? `apply`???
- `GET /api/front/gifts`：礼品列表资源风格接口，返回可兑换礼品和已发货礼品；本轮已与 Layui/Naive 前端调用方式对齐???

### 验证边界

本轮没有改动???户申请创建???重复待审判断???未平仓订单判断、资???/???值判断???后台审核???用户注???标记或真实礼品列表业务???辑，只补齐中文逻辑注释，并修复礼品列表前端调用方法与路由方法不???致的问题。当前本机真??? MySQL `127.0.0.1:3307` 仍未连接验证，因此未声明真实 DB 数据已经覆盖；真??? DB 恢复后，仍需用代理账号和普???客户账号分别验??? `/api/front/account/cancellation`、`/api/front/account/cancellation-applications`、`/user/center/ajaxCancelAccount`、`GET /api/front/gifts`，确认重复待审???未平仓、余???/???值???原因字段写入???后台审核状态展示和礼品列表数据均符合真实业务???
## 109. 2026-06-08 前台个人资料控制器中文???辑注释补齐

本轮继续推进 plan.md 中???所有模块的文件及参数必须有详细中文注释和???辑注释，包括参数的注释及功能作用???的要求，重点维??? `app/Http/Controllers/Front/ProfileController.php`。该控制器承载前台资料读取???资料更新???密码修改???邮箱修改???头像上传???实名认证???银行卡认证、银行卡换绑、销户前身份校验、验证码发??????代理关系链查询和旧前台资料接口兼容，是前台 Layui/Naive 资料页与旧项目前台资料入口共用的核心控制器???

### 本轮维护文件

- `app/Http/Controllers/Front/ProfileController.php`：补齐底部私有工具方法的中文功能说明、参数含义和返回值说明，覆盖 `resolveFileUrl`、`storeProfileFile`、`verifiedContactUser`、`currentProfileContext`、`normalizeChinaPhone`、`phoneMatches`、`sendLegacyProfileCode`、`relationshipText`、`relationshipIds`、`legacyBankCardUpload`、`firstUploadedField`、`legacySuccess`、`legacyFail`、`idCardStatusText`、`bankStatusText`、`mirrorPublicDiskFile`、`deletePublicMirror`、`maskPhone`、`maskEmail`、`maskIdCard`、`maskBankNo`???
- `tests/Feature/FrontProfileControllerCommentReadabilityTest.php`：继续作为前台个人资料控制器中文注释可读性约束，静???读取源码，不连接真实数据库???
- `docs/admin-backend-blade-permission-final-checklist.md`：追加本节记录本次维护内容???验证命令???相关接口和真实 DB 验证边界???

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

- `GET /api/front/profile`：读取当前前台用户资料，返回 `login`、`info`、`auth`、`avatar_url`、脱敏手机号、脱敏邮箱???脱敏身份证号和认证状???文案???
- `PATCH /api/front/profile`：更新当前前台用户基???资料，参数包??? `user_name`、`phone`、`id_card_no`、`gender`、`address`???
- `POST /api/front/profile/password`：修改当前前台用户登录密码，参数包含 `old_password`、`password`、`password_confirmation`???
- `POST /api/front/profile/email`：修改当前前台登录邮箱，参数包含 `verify_phone`、`current_email`、`new_email`???
- `POST /api/front/profile/avatar`：上传当前前台用户头像，参数 `avatar` 为新版头像上传文件字段???
- `POST /api/front/profile/identity`：提交实名认证资料，参数包含 `id_card_no`、`id_card_front`、`id_card_back`???
- `POST /api/front/profile/bank-card`：提交银行卡认证资料，参数包??? `bank_name`、`bank_no`、`bank_addr`、`bank_card_img`、`bank_card_back_img`???
- `POST /api/front/profile/bank-card-change`：提交银行卡换绑资料，写??? `bank_name_tmp`、`bank_no_tmp`、`bank_addr_tmp`、`bank_card_img_tmp`、`bank_card_back_img_tmp`，并设置 `bank_status=3` 表示银行卡换绑待审核???
- `POST /api/front/profile/verification-cancellation-checks` 与旧路由 `POST /user/center/cancelVerifyInfo`：校验销户前的手机号、邮箱和身份证号???
- `POST /api/front/profile/verification-cancellation/verification-codes` 与旧路由 `POST /user/center/cancelVerifyPassSendCode`：发送销户验证邮件验证码???
- `POST /api/front/profile/relationship-path`、`POST /api/front/profile/relationship-path/html`、`POST /api/front/profile/relationship-tree/html` 与旧关系链路由：返回代理关系链文本或旧前??? HTML 兼容格式???

### 验证边界

本轮没有改动个人资料业务逻辑，只补齐中文逻辑注释和参数说明???当前本机真??? MySQL `127.0.0.1:3307` 仍然连接失败，`php artisan migrate:status` ??? `SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接`，因此未声明真实 DB 数据验证完成???

??? DB 连接失败影响，以下两个旧前台 session 回归测试无法作为通过证据，失败根因均??? `FrontBaseController::legacyFrontUserLogin()` 查询 `user_logins` 时数据库连接被拒绝：

```text
vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_profile_ajax_routes_do_not_return_server_errors_for_stale_legacy_session_user
FAIL: /user/center/cancelVerifyInfo returned 500 because MySQL refused connection.

vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_profile_ajax_routes_resolve_real_legacy_session_user
FAIL: expected 200 but received 500 because MySQL refused connection while resolving user_id=1001.
```

真实 DB 恢复后，???要继续使用代理账号和普???客户账号分别验??? `GET /api/front/profile`、`PATCH /api/front/profile`、头像上传???实名认证上传???银行卡认证、银行卡换绑、销户校验???关系链接口和旧前台 `user/center/*` 兼容入口，确认真实数据???脱敏字段???文??? URL、邮件验证码缓存和旧响应结构均符合业务预期???
## 110. 2026-06-08 前台仪表盘控制器中文逻辑注释补齐

本轮继续推进 plan.md 中???后端支持多语言、所有模块文件及参数必须有详细中文注释和逻辑注释”的要求，重点维??? `app/Http/Controllers/Front/DashboardController.php`。该控制器负责前台首??? Blade 视图、账户摘要???代理层级统计???入金出金交易月度统计???新闻公告???旧前台热点新闻、注册页热点新闻和礼品提示状态，是前??? Layui/Naive 首页和旧前台首页兼容入口共用的数据控制器???

### 本轮维护文件

- `tests/Feature/FrontDashboardControllerCommentReadabilityTest.php`：新增前台仪表盘控制器中文注释可读???测试，静???读取源码，不连接真实数据库；约束首页统计字段???新闻多语言、旧前台热点新闻、礼品提示和???请注册链接必须有中文逻辑说明???
- `app/Http/Controllers/Front/DashboardController.php`：清??? `Front Dashboard Controller`、`Provides dashboard views and account summary data.`、`Dashboard view`、`Account summary data`、`Trading records preserve MT4 open_time/close_time.`、`Get first configured value from possible old/new keys.` 等旧英文注释，补齐中文功能说明???参数含义和返回值说明???
- `docs/admin-backend-blade-permission-final-checklist.md`：追加本节记录本轮变更???接口消息???验证结果和数据库迁移边界???

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontDashboardControllerCommentReadabilityTest.php
RED:
- Front DashboardController 缺少中文逻辑注释：处理前台首??? Blade 视图、账户摘要???代理层级统计???入金出金交易月度统计???新闻公告???旧前台热点新闻和礼品提示状???
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
未命中旧英文注释???

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
已可连接数据库；??? 2026_06_07_000006 ??? 2026_06_07_000015 中多条后台权???/前台菜单角色迁移仍为 No???

php -l docs\admin-backend-blade-permission-final-checklist.md
No syntax errors detected in docs\admin-backend-blade-permission-final-checklist.md
```

### 相关接口消息

- `GET /api/front/dashboard`：返回当前前台用户首页账户摘要，包含 `user`、`profile`、`downloads`、`stats`、`news`、`period`???
- `GET /front/dashboard`：前??? Layui 仪表??? Blade 页面路由，页面只渲染容器，真实数据由 `/api/front/dashboard` 返回???
- `GET /user/front/message`：旧前台消息面板占位入口，返回空消息面板 HTML???
- `POST /user/main/hot/news`：旧前台首页热点新闻 HTML 列表接口，返??? `code`、`msg`、`page`、`count`、`dataHtml`???
- `POST /user/main/hot/newsV2` ??? `GET /user/register/hotnews`：旧前台注册页热点新闻表格接口，返回 `code`、`msg`、`count`、`data`、`totalRow`???
- `POST /user/main/hasShowGiftTips`：旧前台礼品提示已读接口，按当前登录用户写入 `gift_tips_shown_{user_id}` 缓存键???

### 验证边界

本轮只补齐中文???辑注释和参数说明，没有改动首页统计、新闻查询???下载配置???邀请链接???礼品提示或旧前台响应结构???`php artisan migrate:status` 本轮已能连接数据库，但以下迁移仍未执行：`2026_06_07_000006_add_admin_fund_flow_permissions`、`2026_06_07_000007_add_admin_rights_summary_permissions`、`2026_06_07_000008_add_admin_exchange_rate_permissions`、`2026_06_07_000009_add_admin_online_user_permissions`、`2026_06_07_000010_add_admin_production_permissions`、`2026_06_07_000011_add_admin_gift_permissions`、`2026_06_07_000012_add_admin_authentication_permissions`、`2026_06_07_000013_add_admin_realtime_commission_permissions`、`2026_06_07_000014_fix_default_admin_and_front_menu_roles`、`2026_06_07_000015_add_admin_position_summary_permissions`。因此后台新增权限???默认超级管理员修复和前??? Layui 菜单角色授权仍需在后续真??? DB 阶段执行或复核???

真实 DB 业务验证仍需要继续使用代理账号和普???客户账号分别访??? `/api/front/dashboard`，确认代理可聚合后代入金/出金/交易统计，客户只查看自身统计；同时验??? `news_langs` 多语???标题、下载地???候???键、注册链??? `account_type` ??? `commission_mode` 参数，以及旧前台热点新闻接口返回结构???
## 111. 2026-06-09 后台默认超级管理员与前台 Layui 菜单权限真实 DB 落库验证

本轮针对测试时发现的“前??? Layui 风格菜单没有了???和“后台超级管理员账号不可登录/未知”问题，直接在当前真??? MySQL `co_crmv5` 数据库执行未落库迁移，并用真实接口验证登录???后台菜单???前??? agent 菜单返回结果。根因是 `2026_06_07_000014_fix_default_admin_and_front_menu_roles` ??? 10 条迁移此前处??? `No` 状???，导致 `superadmin` 未写入当前登录控制器读取??? `admins` 表，前台 `agent_role`、`customer_role` ??? `role_permissions` 菜单授权也未完整写入???

### 本轮执行内容

- 执行 `php artisan migrate`，完??? `2026_06_07_000006` 鑷? `2026_06_07_000015` ??? 10 条后台权限???前台菜单角色和默认后台账号迁移???
- 确认 `admins.username=superadmin` 已存在，初始登录密码??? `Admin@123456`，当前后台页面登录入口为 `GET /admin/login`，后台登??? API ??? `POST /api/admin/login`???
- 确认前台 agent 演示账号 `agent@test.com / agent123` 已绑??? `roles.name=agent_role`，`GET /api/front/navigation/menus` 返回代理专属菜单???
- 确认 `customer_role` 宸插啓鍏? 17 条普通客户菜单授权，且未包含 `front_agent`、`front_commission` 等代???/返佣专属菜单???

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
2026_06_07_000006 ??? 2026_06_07_000015 均为 Yes，Batch=13???

POST /api/admin/login
username=superadmin
password=Admin@123456
结果：HTTP 200，code=1000，user.username=superadmin???

POST /api/admin/menus
Authorization=Bearer {superadmin token}
结果：HTTP 200，code=1000，后台菜单数???=74，后台权??? slug 数量=150???

POST /api/front/auth/login
email=agent@test.com
password=agent123
结果：HTTP 200，code=1000，user_id=1001???

GET /api/front/navigation/menus
Authorization=Bearer {agent token}
结果：HTTP 200，code=1000，根菜单数量=9，菜??? slug 数量=26???
关键菜单：front_agent、front_agent_sub、front_agent_customers、front_commission、front_commission_rt 均存在???

DB 权限范围复核
roles:
- super_admin：admin 角色，id=51
- agent_role：前台代理角色，id=52
- customer_role：前台客户角色，id=53
front permissions 启用数量=28
agent_role 授权数量=26，包含代理管理和返佣管理
customer_role 授权数量=17，不包含 front_agent ??? front_commission，包??? front_dashboard
```

### 本轮自动化测试记???

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

- `GET /admin/login`：后??? Layui 登录页面入口???
- `POST /api/admin/login`：后台登录接口，参数 `username` 表示 `admins.username`，参??? `password` 表示后台登录密码；当前默认超级管理员??? `superadmin / Admin@123456`???
- `POST /api/admin/menus`：后台当前管理员菜单与按钮权限接口，返回 `data.menus` ??? `data.permissions`，超级管理员返回全部启用后台菜单和权??? slug???
- `POST /api/front/auth/login`：前台登录接口，参数 `email` ??? `password` 用于登录 `user_logins` 表账号；本轮??? `agent@test.com / agent123` 验证通过???
- `GET /api/front/navigation/menus`：前??? Layui/Blade 菜单接口，按当前登录用户 `role_id` 读取 `roles`、`role_permissions`、`permissions` 表配置；agent 返回代理管理和返佣管理，customer_role 配置不包含代理专属菜单???
- `GET /api/front/menus`：前台菜单兼容接口，??? `/api/front/navigation/menus` 复用同一控制器方法???

### 验证边界

本轮已经修复并验证此前清单第 110 节记录的“迁移未执行”边界；当前真实 DB ??? 6 ??? 7 日末??? 10 条迁移已全部执行。仍???注意：当前真??? DB 暂未发现 `account_type=2` 的普通客户登录账号，因此本轮只能验证 `customer_role` 的菜单授权配置正确，不能完成普???客户账号登录后的菜单接口冒烟???后续补充真实普通客户测试数据后，需要再用普通客户账号调??? `POST /api/front/auth/login` ??? `GET /api/front/navigation/menus`，确认返回菜单不包含 `front_agent`、`front_commission` 等代理专属节点???
## 112. 2026-06-09 前台持仓管理控制器中文???辑注释补齐

本轮继续推进 plan.md 中???所有模块的文件及参数必须有详细中文注释和???辑注释，包括参数的注释及功能作用???的要求，重点维??? `app/Http/Controllers/Front/PositionController.php`。该控制器承载前台持仓汇总???本??? MT4 汇??汇??下级代理汇总???交易明细???旧前台点击明细、代理网络权限校验???返佣汇总和平仓品种分类统计，是前台 Layui/Naive 持仓页面与旧前台 `user/position/*` 入口共用的核心控制器???

### 本轮维护文件

- `tests/Feature/FrontPositionControllerCommentReadabilityTest.php`：新增静态中文注释可读???测试，不连接真实数据库，约??? PositionController 必须包含持仓汇??汇??本??? MT4 汇??汇??返佣汇总???平仓筛选???品种组、入出金备注、代理链路???钻取权限和旧前台入口等中文逻辑说明???
- `app/Http/Controllers/Front/PositionController.php`：移除旧英文注释标题，补齐类说明、入口方法说明???私有工具方法说明???参数含义???变量含义和返回值边界；本轮只改注释，不改动查询、汇总???权限或响应结构???
- `docs/admin-backend-blade-permission-final-checklist.md`：追加本节，记录本次变更、接口消息???验证命令和剩余边界???

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontPositionControllerCommentReadabilityTest.php
RED:
- Front PositionController 缺少中文逻辑注释：处理持仓汇总???本??? MT4 汇??汇??下级代理汇总???交易明细???旧前台搜索入口、代理关系权限校验和品种分类统计
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
未命中旧英文注释标题???

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
当前真实 DB 可连接，???有迁移均??? Yes???2026_06_07_000006 ??? 2026_06_07_000015 均已落库???
```

### 相关接口消息

- `GET /api/front/positions/summary`：新前台持仓汇???接口，返回当前代理可见的持仓汇总；参数 `userPId`、`target_id` ??? `user_id` 表示钻取目标，必须属于当前代理网络???
- `GET /api/front/positions/direct-agent-summaries`：新前台直属代理持仓汇???接口，返回当前代理下级代理资金与交易汇总???
- `GET /api/front/positions/trades`：新前台交易明细接口，参??? `user_id` 表示被查看用户，`ticket`/`orderId` 表示订单号，`status` 表示订单状???，1=历史平仓???0=当前持仓???
- `POST /user/position/positionSummarySearch`：旧前台持仓汇???搜索入口，内部复用 `positionSummary`???
- `POST /user/position/positionSummary2Search`：旧前台本人 MT4 汇???入口，返回入金、出金???盈亏???手续费、库存费和品种手数???
- `POST /user/position/v2/subAgentsListSearchV2`：旧前台下级代理持仓汇???入口，内部复用 `subPositionSummary`???
- `POST /user/position/v2/positionSummaryClickSearch`：旧前台点击持仓明细入口，内部复??? `positionDetail`???

### 验证边界

本轮只补齐中文???辑注释和参数说明，没有改动持仓汇???业务???辑。`FrontUiRegressionTest.php --filter positions`、`--filter positionSummary2Search`、`--filter positionSummaryClickSearch` 当前没有匹配到测试方法，因此不作为???过证据；本轮已用命中的路由注册、旧前台模块路由、旧命名路由、未登录 Ajax 入口和真实返佣字段回归测试覆盖核心行为???后续若要进???步加强运行时验证，应补充带登??? token 的真??? DB 接口测试，使??? agent 账号访问 `GET /api/front/positions/summary`、`GET /api/front/positions/direct-agent-summaries` ??? `GET /api/front/positions/trades`，并构???普通客户或越权目标验证 `resolveSummaryTargetId` ??? `positionDetail` 的权限边界???
## 113. 2026-06-09 前台上传控制器中文???辑注释补齐

本轮继续推进 plan.md 中???所有模块的文件及参数必须有详细中文注释和???辑注释，包括参数的注释及功能作用???的要求，重点维??? `app/Http/Controllers/Front/UploadController.php`。该控制器承载新前台 `/api/front/uploads/*` 上传入口、旧前台 `user/upload/file` 单文件上传和 `user/multiple/file` 多文件上传入口，返回结构直接影响头像、身份证、银行卡、资料认证和??? Layui 上传回调???

### 本轮维护文件

- `tests/Feature/FrontUploadControllerCommentReadabilityTest.php`：新增静态中文注释可读???测试，不连接真实数据库、不写入真实上传文件；约束新前台上传、旧前台单文件上传???旧前台多文件上传和 legacy 返回字段必须有中文???辑说明???
- `app/Http/Controllers/Front/UploadController.php`：移??? `Generic Upload Controller`、`Generic upload method` 等旧英文注释标题，补齐类说明、公???上传入口说明、私有保存方法说明???参数含义???返回字段含义和旧前台兼容边界???
- `docs/admin-backend-blade-permission-final-checklist.md`：追加本节，记录本轮上传模块注释补齐、接口消息???验证命令和边界说明???

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
未命中旧英文注释标题???

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

- `POST /api/front/uploads`：新前台通用上传入口，由 Common UploadController 处理，保留资源风格路由???
- `POST /api/front/uploads/single`：新前台单文件上传入口，内部调用 `Front\UploadController@singleFileUpload`，返回旧前台兼容结构???
- `POST /api/front/uploads/multiple`：新前台多文件上传入口，内部调用 `Front\UploadController@multipleFileUpload`，返回旧前台兼容结构???
- `POST /user/upload/file`：旧前台单文件上传入口，`file` 表示上传文件字段；成功返??? `code=200,msg=SUC,data={name,path,url}`，失败返??? `code=500`???
- `POST /user/multiple/file`：旧前台多文件上传入口，`file` 表示上传文件集合；返??? `code=200,msg=SUC,data=[]` 或成功文件列表???

### 验证边界

本轮只补齐中文???辑注释和参数说明，没有改变上传校验规则、上传目录???文件命名规则或响应结构。直接???过 HTTP Kernel 调用??? web 上传路由时会先被 CSRF 中间件拦截并返回 419，这??? web 路由安全边界，不代表控制器???辑失败；因此本轮用直接调用控制器方法验证无文件时旧上传响应结构仍保持不变???真实文件上传仍建议在后续用 Laravel UploadedFile 构???带图片的接口测试，验证 `uploads/Bank`、`uploads/IdCard`、`/storage/{path}` 与浏览器访问 URL 是否符合旧前台和新前台页面预期???

## 114. 2026-06-09 ǰ̨ƾ֤�����������߼�ע�Ͳ���

���ּ����ƽ� plan.md �С�����ģ����ļ���������������ϸ����ע�ͺ��߼�ע�ͣ�����������ע�ͼ��������á���Ҫ��ά�??? `app/Http/Controllers/Front/VoucherController.php`���ÿ���������ǰ̨�û��ύƾ֤ͼƬ��д�� `voucher_infos` �����¼�����״̬���Լ�����ǰ��¼�û���ѯ�Լ���ƾ֤��ҳ��¼��ֱ��Ӱ��ǰ̨ Layui ƾ֤ҳ��ͺ�̨ƾ֤�����·��

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
˵�����ù���������ǰû��ƥ�䵽���Է�������˲�����Ϊ��·��ͨ��֤�ݡ???
```

### ��ؽӿ���??

- `POST /api/front/voucherSubmit`����ʷǰ̨ƾ֤�ύ��������˵������ǰ��Դ���ӿ���Ҫ���˻�ģ�� `POST /api/front/account/voucher-submissions` �е���
- `GET /api/front/voucherRecords`����ʷǰ̨ƾ֤��¼��������˵������ǰ��Դ���ӿ���Ҫ���˻�ģ�� `GET /api/front/account/vouchers` �е���
- `POST /api/front/account/voucher-submissions`��ǰ̨ƾ֤�ύ�ӿڣ����� `images[]` ��ʾһ��ƾ֤ͼƬ��`remarks` ��ʾ�û���ע��д�� `voucher_infos.images`��`voucher_infos.remarks`��`review_status=0`��
- `GET /api/front/account/vouchers`��ǰ̨��ǰ�û�ƾ֤��¼�ӿڣ����� `review_status` ��ʾ���״̬ɸѡ��`date_from` �� `date_to` ��ʾ�������ڷ�Χ��`per_page` ��ʾ��ҳ��С��
- `POST /user/user_voucher_save`����ǰ̨ƾ֤�ύ�ӿڣ���ǰ�� `Front\AccountController@userVoucherSave` ���ݴ���???
- `POST /user/voucher/voucherSearch`����ǰ̨ƾ֤��ѯ�ӿڣ���ǰ�� `Front\AccountController@voucherList` ���ݴ���???

### ��֤�߽�

����ֻ���� `Front\VoucherController` �������߼�ע�ͺͱ���ɶ��ԣ�û�иı�ƾ֤�ύ���ļ����桢���״̬����ҳ��ѯ�򷵻ؽṹ����ǰǰ̨��ʵҳ��·�ɺ� API ��Ҫ���� `Front\AccountController`����˱����Ծ�̬�ɶ��Բ��Ժ�ƾ?? UI �ع���Ϊ��Ҫ֤�ݣ�������Ҫ��֤ `Front\VoucherController` ����ʱ��Ϊ��Ӧ����??? `UploadedFile` �ĵ�¼̬�ӿڲ��ԣ�ȷ�� `images[]` ��ͼ�ϴ���`remarks` ��ע��`review_status=0` �� `voucher_infos.images` ·��ƴ�Ӿ�������ʵ DB Ԥ�ڡ�

## 115. 2026-06-09 ǰ̨���Ź�������������߼�ע���������˵������

���ּ����ƽ� plan.md �С���˱���֧�ֶ����ԡ��͡�����ģ����ļ���������������ϸ����ע�ͺ��߼�ע�͡���Ҫ��ά�� `app/Http/Controllers/Front/NewsController.php`���ÿ�����������ǰ̨���Ź����ҳ�ӿڡ���ǰ̨�����б������ӿڡ���ǰ̨�������??? HTML����ͨ�� `X-Locale` ����ͷ���ȶ�ȡ `news_langs` ��ǰ���Լ�¼��

### ����ά���ļ�

- `tests/Feature/FrontNewsControllerCommentReadabilityTest.php`��������̬����ע�Ϳɶ��Բ��ԣ�ֻ��ȡ `Front\NewsController` Դ�룬��������ʵ���ݿ⣬������ `news` �� `news_langs` ��ʵ���ݡ�
- `app/Http/Controllers/Front/NewsController.php`����дΪ UTF-8 �ɶ�Դ�룬���� `newsList`��`newsListSearch`��`newsDetail` ������ҵ���߼����������塢�����Ի��ˡ���ǰ̨��Ӧ�ֶκ� HTML ����˵����
- `docs/admin-backend-blade-permission-final-checklist.md`��׷�ӱ��ڣ���¼���ֶ���������ģ��ά�����ӿ���Ϣ����֤�������֤�߽???

### ���� TDD ��¼

```text
vendor\bin\phpunit tests\Feature\FrontNewsControllerCommentReadabilityTest.php
RED:
- Front NewsController ȱ�������߼�ע�ͣ�����ǰ̨���Ź����б����ǰ̨������������ǰ̨�������??? HTML �� news_langs �����Ի���
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
˵�����ù���������ǰû��ƥ�䵽���Է�������˲�����Ϊ��·��ͨ��֤�ݡ???

vendor\bin\phpunit tests\Feature\FrontendRouteManifestTest.php --filter news
No tests executed!
˵�����ù���������ǰû��ƥ�䵽���Է�������˲�����Ϊ·�??? Manifest ͨ��֤�ݡ�
```

### ��ؽӿ���??

- `GET /api/front/news`����ǰ̨���Ź����ҳ�ӿڣ����??? `page` ��ʾ��ǰҳ�룬`per_page` ��ʾÿҳ����������`title` ��ʾ���ű���ɸѡ�ؼ��֣�`author_name` ��ʾ��������ɸѡ��`X-Locale` ����ͷ��ʾ��ǰ���ԡ�
- `POST /user/newsListSearch`����ǰ̨�����б������ӿڣ����� `rows` �� `total`��`rows` �б��� `news_id`��`news_title`��`news_content`��`rec_upd_date` �Ⱦ�ҳ���ֶΡ�
- `GET /user/news/news_detail/{newsId}`����ǰ̨�������� HTML �ӿڣ�`newsId` ��ʾ `news.id`������ `crm-legacy-news` HTML ����������ͳһ JSON��
- `news_langs` �����Զ�ȡ���򣺵� `news_langs.news_id + lang_code` ������ `title/content` ��Ϊ��ʱ�����ȷ��ط����ֶΣ�������??? `news.title` �� `news.content`��
- `Schema::hasTable('news_langs')`�������ھ�����ҳ����ȱ�ٶ����Ա�Ĳ��𻷾�������Ǩ��δ���ʱ����ҳֱ�ӱ���???

### ��֤�߽�

����ֻ���� `Front\NewsController` �������߼�ע�͡�������˵���ͱ���ɶ��ԣ�û�иı�����ɸѡ����ҳ��������ˡ���ǰ̨ `rows/total` �ṹ������ HTML ��������ڱ����������Ǿ�̬�ɶ��Բ��ԣ���δʹ����?? DB ���� `news/news_langs` ����������������ʱ���ԣ�����Ӧ�������?? `news_langs` �Ľӿڲ��ԣ��ֱ���֤���ġ�Ӣ�ġ�ȱʧ����Ϳշ����ֶ�ʱ�Ļ�����Ϊ�???

## 116. 2026-06-09 ǰ̨�ͻ������������߼�ע����������ݷ�Χ˵�����???

���ּ����ƽ� plan.md �С���ͬ�������ͨ�û�����������ʾ���ݡ��Լ�������ģ����ļ���������������ϸ����ע�ͺ��߼�ע�͡���Ҫ��ά�� `app/Http/Controllers/Front/CustomerController.php`���ÿ����������ǰ����ɼ��ͻ��б�Ϳͻ�ͳ��ժҪ��������ѯ��Χ���??? `agent_descendants`�����Ե�ǰ��¼���� `user_id` ��Ϊ���ݱ߽硣

### ����ά���ļ�

- `tests/Feature/FrontCustomerControllerCommentReadabilityTest.php`��������̬����ע�Ϳɶ��Բ��ԣ�ֻ��ȡ `Front\CustomerController` Դ�룬��������ʵ���ݿ⡣
- `app/Http/Controllers/Front/CustomerController.php`����дΪ UTF-8 �ɶ�Դ�룬���� `myCustomers`��`stats` ������ҵ���߼����������塢�ͻ���Χ��ֱ��ɸѡ���ͻ�����ɸѡ������ͳ�ƺͻ�Ծ�ͻ�ͳ��˵����
- `docs/admin-backend-blade-permission-final-checklist.md`��׷�ӱ��ڣ���¼���ֿͻ�������ά�����ӿڱ߽硢��֤�����ʣ������ʱ��֤���???

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
˵�����ù���������ǰû��ƥ�䵽���Է�������˲�����Ϊ�ͻ�ҳ�??? UI �ع�ͨ��֤�ݡ�
```

### ��ؽӿ���Ϣ��·�ɱ߽???

- `Front\CustomerController@myCustomers`�������ǰ̨�ͻ��б����������??? `direct_only` ��ʾ�Ƿ�ֻ��ֱ��ͻ���`user_name` ��ʾ�ͻ�����ģ��ɸѡ��`per_page` ��ʾ��ҳ��С����ǰ��Ŀ���ͻ��б�·�ɲ�δֱ�Ӱ󶨸÷�����
- `Front\CustomerController@stats`������ĵ�ǰ����ͻ�ͳ��ժҪ���������� `total_customers`��`active_customers`��`inactive_customers` �� `total_volume`��
- `GET /api/front/agents/direct-customers`����ǰǰ̨�ͻ�ҳ��ʵ��ʹ�õ����ӿڣ��� `Front\AgentController@customerList`������ͨ�� `FrontAgentControllerCommentReadabilityTest` ������ע�͸�����Ȼͨ����
- `agent_descendants.descendant_type=2`����ʾ�ͻ��ڵ㣬����ע����ȷ���������ڱ�����¼��������ͻ��б��
- `agent_descendants.is_direct=1`����ʾֱ���ϵ��ֻ�??? `direct_only=1` ʱ׷�Ӹ�ɸѡ��

### ��֤�߽�

����ֻ���� `Front\CustomerController` �������߼�ע�͡�����˵���ͱ���ɶ��ԣ�û�иı����ͻ���ѯ������ͳ�ƻ�ͳ��ժҪ���ؽṹ�����ڵ�?? Layui �ͻ�ҳ��ʵ�ʵ��� `Front\AgentController@customerList`������û�а� `Front\CustomerController` ������ҳ������ʱ֤�ݣ��������������ÿ�������Ӧ������ʽ·�ɻ�ɾ��δʹ����ڣ���ʹ����?? agent token ���ÿͻ��б��ͳ��ժҪ����֤����ֻ�ܲ鿴�Լ??? `agent_descendants` ��Χ�ڵĿͻ����ݡ�

## 117. 2026-06-09 ǰ̨�ֲֻ��ܱ��ÿ����������߼�ע����Ȩ�ޱ߽�˵������

���ּ����ƽ� plan.md �С���ͬ�������ͨ�û�����������ʾ���ݡ��Լ�������ģ����ļ���������������ϸ����ע�ͺ��߼�ע�͡���Ҫ��ά�� `app/Http/Controllers/Front/PositionSummaryController.php`���ÿ����������ǰ����ֱ��ڵ�ֲָ������ֲ�ɸѡ���ܡ��¼������ѯ��ָ���û�������ϸ�������ص�����ȷ�����������ݷ�Χ�͵����ϸԽȨУ�???

### ����ά���ļ�

- `tests/Feature/FrontPositionSummaryControllerCommentReadabilityTest.php`��������̬����ע�Ϳɶ��Բ��ԣ�ֻ��ȡ `Front\PositionSummaryController` Դ�룬��������ʵ���ݿ⡣
- `app/Http/Controllers/Front/PositionSummaryController.php`����дΪ UTF-8 �ɶ�Դ�룬���� `index`��`search`��`subSearch`��`clickSearch` ������ҵ���߼����������塢ֱ��ڵ㷶Χ����������Χ������״̬ɸѡ��ԽȨУ��˵���???
- `docs/admin-backend-blade-permission-final-checklist.md`��׷�ӱ��ڣ���¼���ֱֲֳ��ÿ�����ά�����ӿڱ߽硢��֤�����ʣ������ʱ��֤���???

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
˵�����ù���������ǰû��ƥ�䵽���Է�������˲�����Ϊ��·��ͨ��֤�ݡ???
```

### ��ؽӿ���Ϣ��·�ɱ߽???

- `Front\PositionSummaryController@index`������ĵ�ǰ����ֱ��ڵ�ֲָ���������ͳ�??? `is_direct=1` ��ֱ��ڵ㼰����δƽ�ֶ����???
- `Front\PositionSummaryController@search`������ĵ�ǰ��������ֲ�ɸѡ���������� `date_from`��`date_to`��`symbol` �� `per_page` ����ɸѡ���ҳ�???
- `Front\PositionSummaryController@subSearch`��������¼������ѯ������`descendant_type=1` ��ʾֻ��ѯ����ڵ???
- `Front\PositionSummaryController@clickSearch`�������ָ���û�������ϸ���������??? `user_id` ��ʾ���鿴�û���`symbol` ��ʾ����Ʒ�֣�`ticket` ��ʾ�����ţ�`status=1` ��ʾ��ƽ�֣�`status=0` ��ʾδƽ�֣�Ŀ���û������ڵ�ǰ���������Ҳ��ǵ�ǰ�������ʱ���??? `PERMISSION_DENIED`��
- `GET /api/front/positions/summary`����ǰǰ̨�ֲֻ������ӿڣ��� `Front\PositionController@positionSummary`������ͨ�� `FrontPositionControllerCommentReadabilityTest` �ͳֲ� UI �ع鸴������·δ��Ӱ�졣
- `GET /api/front/positions/direct-agent-summaries`����ǰǰ̨�¼�����ֲֻ������ӿڣ��??? `Front\PositionController@subPositionSummary`��

### ��֤�߽�

����ֻ���� `Front\PositionSummaryController` �������߼�ע�͡�����˵���ͱ���ɶ��ԣ�û�иı�ֲֻ��ܡ�ɸѡ���¼������ѯ�������ϸ��ԽȨУ�鷵�ؽṹ����ǰǰ̨�ֲ�ҳ��ʵ������·ʹ�� `Front\PositionController`������û�аѱ��ÿ���������������ʱ֤�ݣ��������������ÿ�������Ӧ������ʽ·�ɻ�����δʹ����ڣ���ʹ����?? agent token ����Ŀ���û��ڴ���������/��������ԣ���?? `clickSearch` �� `PERMISSION_DENIED` �߽硣

## 118. 2026-06-09 前台基础控制器中文???辑注释与旧登录兼容边界复核

本轮继续推进 plan.md 中???后端必须支持多语言”???所有模块的文件及参数必须有详细中文注释和???辑注释”的要求，维??? `app/Http/Controllers/Front/FrontBaseController.php`。该基础控制器是前台控制器共用父类，统一复用 `ApiResponse` 返回结构，并兼容??? JWT `user guard` 与旧前台 session `suser` 登录态，因此它的注释必须说明清楚统一响应、多语言消息 key、登录???解析和认证错误边界???

### 本轮维护文件

- `tests/Feature/FrontBaseControllerCommentReadabilityTest.php`：新增静态中文注释可读???测试，只读??? `FrontBaseController` 源码，不连接真实数据库???
- `app/Http/Controllers/Front/FrontBaseController.php`：补齐前台基???控制器???`legacyFrontUserId`、`legacyFrontUserLogin`、`legacyFrontUserInfo`、`legacyFrontAuthError` 的中文业务???辑说明和参数含义说明???
- `docs/admin-backend-blade-permission-final-checklist.md`：追加本节，记录本轮基础控制器维护???接口响应边界???验证命令和剩余真实登录验证建议???

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontBaseControllerCommentReadabilityTest.php
RED:
- FrontBaseController 缺少“前台基???控制器??????ApiResponse 多语???消息 key”???JWT user guard”???旧 session suser”???USER_NOT_FOUND / AUTH_FAILED 边界”等中文逻辑注释???
- FrontBaseController 仍残留旧英文注释标题：Front Base Controller、All front controllers extend this class???
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
未命中旧英文注释标题???
```

### 相关接口消息与兼容边???

- `legacyFrontUserId(Request $request)`：参??? `request` 表示当前 HTTP 请求；优先读??? `$request->user('user')` 中的 JWT 前台登录记录，其次读取旧前台 session `suser`，最终返回业务用??? ID，无法识别时返回 `0`???
- `legacyFrontUserLogin(Request $request)`：参??? `request` 表示当前 HTTP 请求；优先返??? `user guard` 已解析的 `UserLogin`，旧 session 场景下按 `user_id` 查询 `user_logins`???
- `legacyFrontUserInfo(Request $request)`：参??? `request` 表示当前 HTTP 请求；按当前登录记录或旧 session 用户 ID 查询 `user_infos`，用于前台业务控制器复用统一的用户资料解析???
- `legacyFrontAuthError(Request $request)`：参??? `request` 表示当前 HTTP 请求；能识别 `user_id` 但缺少业务资料时返回 `auth.user_info_not_found` ??? `USER_NOT_FOUND`，完全无法识别登录???时返回 `response.auth_failed` ??? `AUTH_FAILED`???
- `ApiResponse`：本基础控制器统???使用 `success` ??? `error` 响应，消息参数传??? `response.*`、`auth.*` ??? Laravel 多语??? key，保证后端响应支??? `zh-CN` ??? `en` 等语???包切换???

### 验证边界

本轮只补??? `FrontBaseController` 的中文???辑注释、参数说明和编码可读性，没有改变前台 JWT 登录态???旧 session 兼容、用户资料查询或认证错误返回结构。本轮已通过旧前??? Ajax 主路由和命名路由别名精准回归，说明基???控制器维护没有破坏旧前台兼容入口。真??? DB 与浏览器环境下仍???继续??? `agent@test.com / agent123` 登录 Layui 前台，确认登录后 `GET /api/front/navigation/menus` 能携??? token 正常返回代理菜单树，并用普???客户账号补测客户菜单边界???

## 119. 2026-06-09 前台大代??? BigNumberController 中文逻辑注释与多语言响应补齐

本轮继续推进 plan.md 中???后端必须支持多语言”???所有模块的文件及参数必须有详细中文注释和???辑注释”的要求，维??? `app/Http/Controllers/Front/BigNumberController.php`。该控制器同时承载旧前台 `legacy /user/agents/*` 大代理入口和新前??? `/api/front/auth/big-number/login` 登录接口，直接影响大代理账号登录、下级代理范围???持仓汇总和订单查询边界???

### 本轮维护文件

- `tests/Feature/FrontBigNumberControllerCommentReadabilityTest.php`：新增静态中文注释与多语???响应可读性测试，只读取控制器源码和语???包，不连接真实数据库???
- `app/Http/Controllers/Front/BigNumberController.php`：补齐控制器类???构造函数???旧大代理登录???旧页面渲染、旧 Ajax 列表、旧订单查询、新 big-number API 登录、直属代理查询和私有范围方法的中文???辑注释与参数说明???
- `docs/admin-backend-blade-permission-final-checklist.md`：追加本节，记录本轮大代理入口维护???接口消息???验证命令和真实 DB 验证边界???

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontBigNumberControllerCommentReadabilityTest.php
RED:
- BigNumberController 缺少中文逻辑注释：前台大代理控制器???
- BigNumberController 仍残留旧英文注释标题：Big-number agent portal (legacy /user/agents/*)???
- 旧前??? agentsSignIn 中仍存在账号、密码???禁用等面向用户的硬编码提示，没有统?????? Laravel 多语??? key???
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

rg -n "Big-number agent portal|账号或密码不能为空|无效账号|账号已被禁用|密码错误|账号|无效|密码|禁用|错?" app\Http\Controllers\Front\BigNumberController.php -S
未命中旧英文标题、旧硬编码提示和典型乱码片段???

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter big_number
No tests executed!

vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter big
No tests executed!
说明：当??? FrontUiRegressionTest 没有匹配 big_number/big 过滤条件的方法名，因此不能作为大代理 UI 回归通过证据???
```

### 相关接口消息与参数边???

- `GET /agents/login`：旧前台大代理登录页面入口，绑定 `BigNumberController@agentsLogin`，参??? `langId` 表示旧系统语???编号???
- `POST /user/agents/signIn`：旧前台大代理登录接口，参数 `loginUid` 表示旧前台提交的大代理登录名，也兼容 `email`、`user_id`；参??? `loginPassword` 表示旧前台提交的大代理登录密码，也兼??? `password`???
- `POST /api/front/auth/big-number/login`：新前台 big-number 登录接口，参??? `email` ??? `user_id` 至少传一个，参数 `password` 表示登录密码；只??? `user_infos.account_type=1` 的代理账号允许登录，普???客户返??? `response.permission_denied`???
- `POST /user/agents/proxy/proxySearch`：旧前台大代理直属代理列表接口，数据范围来自 `big_agents.sub_agent_ids`???
- `POST /user/agents/proxy/proxySearchBySub`、`POST /user/agents/position/positionSummarySearch`、`POST /user/agents/position/subAgentsListSearch`：旧前台大代理代理网络列表和持仓汇???接口，`includeDescendants=true` 时会把直属代理的下级代理纳入查询???
- `POST /user/agents/close/closeOrderSearch` ??? `POST /user/agents/open/openOrderSearch`：旧前台大代理订单接口，参数 `open` 表示是否查询未平仓订单；订单客户范围只来自当前大代理可见代理网络??? `agent_descendants.descendant_type=2` 的客户节点???
- `POST /user/agents/changePassword`：旧前台大代理修改密码接口，参数 `old_password` / `oldPassword` / `old_psw` 表示旧密码，参数 `password` / `new_password` / `newPassword` 表示新密码???

### 多语???响应调整

- 旧大代理登录账号或密码缺失：`errpsw = __('auth.password_required')`???
- 旧大代理账号不存在或密码错误：`notactive` / `errpsw = __('auth.failed')`???
- 旧大代理账号禁用：`notactive = __('auth.account_disabled')`???
- 旧大代理改密旧密码错误：`msg = __('auth.old_password_error')`，同时保留旧前台识别??? `errorType=OLD_PASSWORD`???
- ??? big-number API 登录失败：继续???过统一 `ApiResponse` 返回多语???后的 `__('auth.failed')`???

### 验证边界

本轮只补??? `BigNumberController` 的中文???辑注释、参数说明和用户可见错误提示多语???来源，没有改变旧路由 URI、旧 JSON 字段名???分页结构???`big_agents.sub_agent_ids` 范围、`UserTrade::open()` / `UserTrade::closed()` 查询规则或新 API 登录权限判断。由于本轮未连接真实 MySQL，没有用真实 `big_agents` 账号测试登录、禁用拦截???登录日志写入???token 写入、下级代理列表???持仓汇总和订单查询；真??? DB 恢复后仍???用启???/禁用大代理账号分别实测旧入口和新入口???

## 120. 2026-06-09 前台找回密码 ForgotPasswordController 中文逻辑注释与多语言 key 修复

本轮继续推进 plan.md 中???后端必须支持多语言”???所有模块的文件及参数必须有详细中文注释和???辑注释”的要求，维??? `app/Http/Controllers/Front/ForgotPasswordController.php`。该控制器同时承载新前台找回密码接口和旧前台找回密码兼容接口，直接影响验证码缓存、邮箱账号校验???密码重置???旧页面错误码和登录链路恢复???

### 本轮维护文件

- `tests/Feature/FrontForgotPasswordControllerCommentReadabilityTest.php`：新增静态中文注释与多语???响应可读性测试，只读取控制器源码和语???包，不连接真实数据库???
- `app/Http/Controllers/Front/ForgotPasswordController.php`：补齐控制器类???发送验证码、重置密码???旧用户信息校验、旧验证码校验???旧保存新密码???旧成功响应和旧失败响应的中文???辑注释与参数说明???
- `docs/admin-backend-blade-permission-final-checklist.md`：追加本节，记录本轮找回密码入口维护、接口消息???验证命令和真实 DB/邮件验证边界???

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontForgotPasswordControllerCommentReadabilityTest.php
RED:
- ForgotPasswordController 缺少中文逻辑注释：前台找回密码控制器???
- 用户不存在响应仍使用 auth.user_not_found，但当前 auth.php 没有 user_not_found 语言 key???
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

### 相关接口消息与参数边???

- `GET /user/forget_password`：旧前台找回密码页面入口，绑??? `ForgotPasswordController@showForgotPassword`???
- `POST /api/front/auth/password/email-code`：新前台发???找回密码验证码接口，参??? `email` 表示接收验证码的登录邮箱；旧参数 `useremail` 会归???化为 `email`???
- `POST /api/front/auth/password/reset`：新前台密码重置接口，参??? `email` 表示登录邮箱，参??? `code` 表示验证码，旧参??? `codedata` 会归???化为 `code`，参??? `password_confirmation` 表示 Laravel `confirmed` 规则使用的确认密码???
- `POST /user/check_user_info`：旧前台找回密码第一步校验接口，参数 `userId/user_id` 表示业务用户 ID，参??? `useremail/email` 表示登录邮箱；返回旧页面脚本识别??? `IDerror`、`UserDisable`、`emailerror`???
- `POST /user/forgetpswSendCode`：旧前台发???验证码接口，复??? `sendResetCode`，旧请求会返??? `{status: true}` 以兼容历史脚本???
- `POST /user/forgetPasswordInfoVerification`：旧前台验证码校验接口，参数 `codedata/code` 表示验证码；验证码错误返回旧错误??? `errorCodedate`???
- `POST /user/change_password`：旧前台保存新密码接口，参数 `userId/user_id/accountno` 表示业务用户 ID，参??? `password/newPsw` 表示新密码，参数 `codedata/code` 表示验证码???

### 多语???响应调整

- 新接口用户不存在：从不存在的 `auth.user_not_found` 改为真实存在??? `response.user_not_found`???
- 新接口验证码发???成功：继续使用 `auth.reset_code_sent`???
- 新接口验证码无效或过期：继续使用 `auth.reset_code_invalid`???
- 新接口密码重置成功：继续使用 `auth.password_reset_success`???
- 参数校验失败：继续???过 Laravel Validator 返回具体验证错误，并使用 `ResponseCode::VALIDATION_FAILED`???

### 验证边界

本轮没有改变找回密码路由 URI、旧前台 `msg/err/col` 响应字段、验证码缓存 key、验证码有效期???密??? Hash 写入方式或旧页面错误码，只补齐中文???辑注释和修正不存在的多语言 key。由于本轮未连接真实 MySQL、SMTP 或浏览器环境，没有声明真实邮箱发送???验证码收取、真实账号密码重置已经端到端通过；真实环境恢复后仍需使用真实 `user_logins.email` 请求验证码???读取邮件或测试缓存验证码???完??? `/api/front/auth/password/reset` 和旧 `/user/change_password` 两条链路验证???

## 121. 2026-06-09 前台交易品种 TradeSymbolController 中文逻辑注释与真实下拉数据来源补???

本轮继续推进 plan.md 中???所有模块的文件及参数必须有详细中文注释和???辑注释”???前端页面数据必须来自真实后端配置???的要求，维??? `app/Http/Controllers/Front/TradeSymbolController.php`。该控制器负??? `GET /api/front/trade-symbols`，为 Layui Blade ??? Naive 风格页面提供交易品种动???下拉???项，直接影响持仓???订单等模块??? `symbol` 精确筛??????

### 本轮维护文件

- `tests/Feature/FrontTradeSymbolControllerCommentReadabilityTest.php`：新增静态中文注释与真实数据来源测试，只读取控制器源码，不连接真实数据库???
- `app/Http/Controllers/Front/TradeSymbolController.php`：补齐控制器类???接口用途???真实表来源、新旧字段兼容???启用状态字段和返回结构的中文???辑注释???
- `docs/admin-backend-blade-permission-final-checklist.md`：追加本节，记录本轮交易品种接口维护、接口消息???验证命令和真实 DB 验证边界???

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontTradeSymbolControllerCommentReadabilityTest.php
RED:
- TradeSymbolController 缺少中文逻辑注释：前台交易品种控制器???
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

### 相关接口消息与参数边???

- `GET /api/front/trade-symbols`：前台交易品种动态下拉接口，路由??? `front_api_trade_symbols`???
- `symbol_prices`：交易品种真实数据表，是本接口唯???数据来源???
- `sym_symbol`：旧表结构中的交易品种字段???
- `symbol`：新表结构中的交易品种字段???
- `voided`：旧表结构中的启用状态字段，当前逻辑??? `voided=1` 过滤???
- `status`：新表结构中的启用状态字段，当前逻辑??? `status=1` 过滤???
- `list`：前??? select 组件使用的???项数组???
- `value` / `label`：都使用交易品种编码，保证前端展示???与提交给后端筛选的 `symbol` 鍊间竴鑷淬??
- `response.query_success`：查询成功多语言消息 key，由 `ApiResponse` 统一翻译???

### 验证边界

本轮没有改变交易品种查询行为、路由???返回结构或前端动???下拉配置，只补齐中文???辑注释和专项测试???由于本轮未连接真实 MySQL，没有验证当??? `symbol_prices` 表中真实品种数量、`sym_symbol/symbol` 实际列名、`voided/status` 实际启用状???和页面下拉实际选项；真??? DB 恢复后仍???请求 `/api/front/trade-symbols`，确认返回的 `list` 与真??? `symbol_prices` 启用品种???致，并在持仓、订单页面???择某一品种后验证后端按同一 `symbol` 精确筛??????

## 122. 2026-06-09 前台支付回调 PaymentNotifyController 中文逻辑注释与旧回调边界补齐

本轮继续推进 plan.md 中???所有模块的文件及参数必须有详细中文注释和???辑注释”的要求，维??? `app/Http/Controllers/Front/PaymentNotifyController.php`。该控制器承载旧前台多条入金/出金支付回调路径，也承载新前??? `/api/front/payment/notify/{gateway}` ??? `/api/front/payment/return/{gateway}`，属于资金链路的安全敏感入口???

### 本轮维护文件

- `tests/Feature/FrontPaymentNotifyControllerCommentReadabilityTest.php`：新增静态中文注释与兼容边界测试，只读取控制器源码，不连接真实数据库、不触发真实支付通道???
- `app/Http/Controllers/Front/PaymentNotifyController.php`：补齐控制器类???旧回调入口、异步???知、同步返回???旧网关映射的中文???辑注释与参数说明???
- `docs/admin-backend-blade-permission-final-checklist.md`：追加本节，记录本轮支付回调入口维护、接口消息???验证命令和真实支付通道验证边界???

### 本轮 TDD 记录

```text
vendor\bin\phpunit tests\Feature\FrontPaymentNotifyControllerCommentReadabilityTest.php
RED:
- PaymentNotifyController 缺少中文逻辑注释：前台支付回调控制器???
- PaymentNotifyController 仍残留旧英文注释标题：Payment gateway notify/return endpoints???
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

### 相关接口消息与参数边???

- `POST /api/front/payment/notify/{gateway}`：新前台支付异步通知入口，参??? `gateway` 表示支付网关标识???
- `GET /api/front/payment/return/{gateway}`：新前台支付同步返回入口，参??? `gateway` 表示支付网关标识，`status` 默认 `pending`???
- `user/deposit_notfiy`、`user/deposit_notfiy2`、`user/deposit_tigerpay_notify`、`user/deposit_wppay_notify`、`user/deposit_exlink_*`、`user/deposit_btb_*`、`user/deposit_passto_notify`、`user/deposit_switch_notify`、`user/deposit_notfiy_otc`：旧前台入金回调兼容路径，统???进入 `legacyCallback`???
- `user/withdraw_notfiy_otc`、`user/withdraw_verify_otc`：旧前台出金回调兼容路径，当前只记录日志并返??? `success`，避免在未完成验签和出金确认规则迁移前误改出金状态???
- `payload`：第三方支付平台回传的完整参数，当前通过 `Log::info` 记录???
- `order_no / local_order_no / out_trade_no`：不同???道可能回传的本地订单号字段，当前按顺序兼容读取???
- `DepositRecord`：对??? `deposit_records` 入金记录，当前按 `local_order_no` 定位记录???
- `status=success`：第三方通知支付成功时才更新入金记录???
- `status=02`：当前项目中入金记录已支付或待后台确认的状??????，本轮不改变业务枚举???
- `legacyGatewayName`：把旧路由路径映射为统一网关标识，例??? `wppay`、`exlink_bb`、`btb`、`otc_deposit`???

### 验证边界

本轮没有改变支付回调路由、返??? `success` 字符串???同步返回重定向、入金状态更新字段或旧网关映射，只补齐中文???辑注释和专项测试???由于本轮未连接真实 MySQL、未配置真实支付通道、未执行第三方签名验签，不能声明真实通道回调已经安全完成；真实环境恢复后仍需??? `payment_channels` 配置逐个通道补齐验签、幂等处理???金额校验???订单归属校验和重复回调测试???

## 123. 2026-06-09 旧前台页??? LegacyPageController 中文逻辑注释与反馈多语言修复

### 本次维护文件
- `app/Http/Controllers/Front/LegacyPageController.php`
- `tests/Feature/FrontLegacyPageControllerCommentReadabilityTest.php`
- `docs/admin-backend-blade-permission-final-checklist.md`

### TDD RED 依据
- 新增 `FrontLegacyPageControllerCommentReadabilityTest` 约束旧前台页面控制器必须具备中文功能逻辑注释???
- 测试要求旧页面参??? `legacyParentUserId`、`legacyTargetUserId`、`legacyAddressId` ??? `offweb_feedbacks` 写入边界必须有中文说明???
- 测试要求旧意见反馈成功消息必须使用后端多语言 key `__('response.success')`，不能继续保留硬编码 `发???成功`???

### 生产代码调整
- ??? `LegacyPageController` 补齐旧前??? `legacy user/*` 页面入口??? `front_layui::*` Blade 模板的职责说明???
- 为控制台、个人中心???账户???入金???出金???流水???代理???返佣???礼品???新闻等旧页面映射补充中文???辑注释???
- 为旧路由透传参数补充参数含义：`legacyParentUserId` 表示直属客户页面的上级代理用??? ID，`legacyTargetUserId` 表示返佣转账或组别变更目标用??? ID，`legacyAddressId` 表示地址编辑记录 ID???
- ??? `feedback()` 补充 `email`、`username`、`phone`、`remarks` ??? `offweb_feedbacks` 表写入边界说明???
- 将旧意见反馈成功响应从硬编码文案改为 `__('response.success')`，保证后端多语言输出???
- ??? `logout()` 补充清理??? `user guard` 与旧 session `suser` 的???辑说明???

### 接口与页面边???
- `GET user/index`、`GET user/index/index`、`POST user/indexreg`、`GET user/main/home` 继续映射??? `front_layui::dashboard.index`???
- `GET user/center*`、`GET user/editpsw`、`GET user/agents/editpsw` 继续映射??? `front_layui::profile.index`???
- `GET user/account`、`GET user/voucher`、`GET user/deposit`、`GET user/withdraw`、`GET user/flow/main` 继续映射到账户???凭证???入金???出金与流水 Blade 页面???
- `GET user/proxy/direct_cust_detail/{puid}` 将旧参数写入 `legacyParentUserId`???
- `GET user/proxy/direct_user_commTrans_browse/{uid}` ??? `GET user/cust/change/group/{uid}` 将旧参数写入 `legacyTargetUserId`???
- `GET user/address/info/{recId}` 将旧地址记录 ID 写入 `legacyAddressId`???
- `POST user/offweb/feedback` 继续写入 `offweb_feedbacks`，成功消息由 `resources/lang/*/response.php` ??? `success` key 输出???
- `GET user/loginOut` 继续清理 Laravel `user` guard 与旧前台 session `suser`???

### 验证记录
- `vendor\bin\phpunit tests\Feature\FrontLegacyPageControllerCommentReadabilityTest.php`：???过???2 tests / 19 assertions???
- `php -l app\Http\Controllers\Front\LegacyPageController.php`：???过，无语法错误???
- `php -l tests\Feature\FrontLegacyPageControllerCommentReadabilityTest.php`：???过，无语法错误???
- `vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_user_module_routes_are_registered`：???过???1 test / 482 assertions???
- `vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_named_route_aliases_are_registered`：???过???1 test / 156 assertions???
- `vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_page_routes_render_without_crashing`：???过???1 test / 46 assertions???
- `vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_public_smoke_routes_do_not_crash`：???过???1 test / 17 assertions???
- `rg -n "发???成功|鍙戦€佹垚鍔|�|旧前台页面控制器|legacyParentUserId|legacyTargetUserId|legacyAddressId|response\.success" ...`：确??? `response.success` 和关键参数注释存在，未发现硬编码 `发???成功`???

### 本轮边界
- 本轮不改旧页面路由???Blade 映射??? `offweb_feedbacks` 表结构，只完成旧页面控制器注释可读???与反馈成功消息多语???修复???
- 未执行真实浏览器表单提交；真??? DB 联调时仍???用旧页面 `POST user/offweb/feedback` 做一次人工提??? smoke???

## 124. 2026-06-09 旧维护入??? LegacyMaintenanceController 中文逻辑注释与禁用响应多语言修复

### 本次维护文件
- `app/Http/Controllers/Front/LegacyMaintenanceController.php`
- `tests/Feature/FrontLegacyMaintenanceControllerCommentReadabilityTest.php`
- `resources/lang/zh-CN/response.php`
- `resources/lang/en/response.php`
- `docs/admin-backend-blade-permission-final-checklist.md`

### TDD RED 依据
- 新增 `FrontLegacyMaintenanceControllerCommentReadabilityTest`，约束旧维护入口控制器必须说明导入用户???同步到 MT4、测试入金???测试出金等公开维护路由的禁用边界???
- RED 阶段失败点：`LegacyMaintenanceController` 缺少 `旧维护入口控制器`、`legacyAction 表示旧项目维护入口动作名` 等中文???辑注释???
- RED 阶段失败点：旧维护入口禁用消息仍硬编码英??? `Legacy maintenance action is disabled...`，未使用后端语言??? key???

### 生产代码调整
- ??? `LegacyMaintenanceController` 补充类级中文逻辑注释，明确旧项目公开维护入口迁移后只能保留兼容路由，不能继续公开执行导入、同步或测试写入动作???
- ??? `importUser`、`importAgents`、`syncToT4ByLocalAgents`、`syncToT4ByLocalUser`、`localRegisterNotifyByAgents`、`syncAgents`、`syncUser`、`syncDisableUserToT4`、`importLang`、`testDeposit`、`testWithdraw` 等入口补充中文用途和禁用边界注释???
- ??? `testSearch(Request $request, $id)` 补充 `$id` 参数含义，说明该参数只保留旧路由签名兼容???
- ??? `disabledMaintenanceResponse()` 补充 `$request`、`$legacyAction`、`action`、`path`、`legacy_action` 的中文???辑含义???
- 将禁用响应消息改??? `__('response.legacy_maintenance_disabled')`，新增中英文语言??? key???

### 接口与页面边???
- `GET /importUser`、`GET /importAgents`、`GET /syncToT4ByLocalAgents` 继续返回 423，不恢复公开维护执行逻辑???
- `POST /syncToT4ByLocalUser`、`POST /localRegisterNotifyByAgents`、`POST /syncAgents`、`POST /syncUser`、`POST /syncDisableUserToT4` 继续返回 423???
- `GET /importLang`、`GET /test`、`POST /test/deposit`、`POST /test/withdraw`、`GET /test_rights_sum`、`GET /test_serach/{id}` 等旧测试入口继续返回 423???
- 响应 `data.legacy_action` 保留旧动作名，`data.path` 保留触发路径，方便旧调用方和测试定位命中的禁用入口???
- 禁用日志仍写??? `front.legacy_maintenance.disabled`，字段包??? `action`、`path`、`ip`???

### 验证记录
- `vendor\bin\phpunit tests\Feature\FrontLegacyMaintenanceControllerCommentReadabilityTest.php`：???过???2 tests / 13 assertions???
- `vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_user_maintenance_and_big_agent_routes_are_registered`：???过???1 test / 190 assertions???
- `vendor\bin\phpunit tests\Feature\FrontLegacyRouteCompatibilityTest.php --filter test_front_legacy_public_smoke_routes_do_not_crash`：???过???1 test / 17 assertions???
- `php -l app\Http\Controllers\Front\LegacyMaintenanceController.php`：???过，无语法错误???
- `php -l tests\Feature\FrontLegacyMaintenanceControllerCommentReadabilityTest.php`：???过，无语法错误???
- `php -l resources\lang\zh-CN\response.php`：???过，无语法错误???
- `php -l resources\lang\en\response.php`：???过，无语法错误???
- Laravel HTTP Kernel smoke：`GET /importUser` 返回 `status=423`，`data.legacy_action=importUser`，确认旧维护入口仍保持禁用响应???

### 本轮边界
- 本轮不恢复任何旧维护、导入???同步???测试写入动作，只提升注释可读???和禁用响应多语???维护性???
- 后续如需实现真实导入或同步，必须迁移到受保护??? Artisan 命令或后台任务，并重新设计权限???审计???幂等和真实 DB 测试数据???

## 125. 2026-06-09 后台 Blade 总布??? UI 参???标记与信息密度增强

### 本次维护文件
- `resources/admin/layui/layouts/app.blade.php`
- `public/css/admin/style.css`
- `tests/Feature/AdminLayoutUiReferenceDensityTest.php`
- `docs/admin-backend-blade-permission-final-checklist.md`

### TDD RED 依据
- 新增 `AdminLayoutUiReferenceDensityTest`，约束后??? Blade 总布???必须显式声明 UI 参???来源：Vben Admin、Vue Naive Admin、Naive UI Admin、Ant Design Pro、Arco Design Pro???
- RED 阶段失败点：后台总布???缺少 `data-ui-reference`，无法从结构上证明当??? Blade/Layui 外壳仍按 plan.md ??? 7 节参考体系维护???
- RED 阶段失败点：后台公共 CSS 缺少 `--admin-content-gap`、`--admin-panel-padding`、`--admin-toolbar-height`、吸顶页头???页头工具区和统???工具条等现代中后台信息密度规则???

### 生产代码调整
- 在后台???布??? `<body>` 增加 `data-ui-reference="Vben Admin, Vue Naive Admin, Naive UI Admin, Ant Design Pro, Arco Design Pro"`，同时保??? `data-render-mode="blade"`，明确当前项目仍??? Laravel Blade 渲染???
- 将后台页头拆分为 `crm-admin-page-head-main` ??? `crm-admin-page-head-tools`，左侧承载页面标题，右侧承载面包屑和后续工具按钮???
- ??? `public/css/admin/style.css` 增加后台 UI 参???层中文注释，说明在 Blade + Layui 约束下吸??? Vben/Naive/Ant/Arco 的信息密度和组件秩序???
- 新增密度变量：`--admin-content-gap`、`--admin-panel-padding`、`--admin-toolbar-height`，供紧凑/舒???模式和业务面板统一复用???
- ??? `crm-admin-page-head` 增加 `position: sticky`、`top: 0`、???明面板背景??? `backdrop-filter`，提升后台长表格页面的页头可见??????
- 新增 `crm-admin-toolbar`、`crm-admin-density-compact`、`crm-admin-density-comfortable`，为后续模块统一工具条和密度切换预留稳定 CSS 入口???
- 移动端下??? `crm-admin-page-head-tools` ??? `crm-admin-toolbar` 自动换行，避免面包屑、筛选工具和按钮拥挤溢出???

### 页面边界
- `GET /admin/dashboard` 仍使??? Laravel Blade + Layui 总布???渲染，HTTP Kernel smoke 返回 200，并能看??? `data-ui-reference`???
- `GET /admin/login` 是独立登录页模板，未继承后台工作台???布???，因此不强制包含 `data-ui-reference`???
- 本轮只调整后台全???外壳与公??? CSS，不改任何后台业务接口???菜单权限???按钮权限和表格数据逻辑???

### 验证记录
- `vendor\bin\phpunit tests\Feature\AdminLayoutUiReferenceDensityTest.php`：???过???2 tests / 25 assertions???
- `vendor\bin\phpunit tests\Feature\AdminLayoutUiModernizationTest.php`：???过???2 tests / 33 assertions???
- `vendor\bin\phpunit tests\Feature\AdminLayoutShellReadabilityTest.php`：???过???2 tests / 18 assertions???
- `vendor\bin\phpunit tests\Feature\AdminBladePagePanelModernizationTest.php`：???过???1 test / 1 assertion???
- `php -l resources\admin\layui\layouts\app.blade.php`：???过，无语法错误???
- `php -l tests\Feature\AdminLayoutUiReferenceDensityTest.php`：???过，无语法错误???
- `rg -n "data-ui-reference|crm-admin-page-head-main|crm-admin-page-head-tools|后台 UI 参???层|--admin-content-gap|--admin-panel-padding|--admin-toolbar-height|crm-admin-density-compact|crm-admin-toolbar|position: sticky" ...`：确认布?????? CSS 关键片段存在???
- Laravel HTTP Kernel smoke：`GET /admin/dashboard` 返回 200 且包??? `data-ui-reference`；`GET /admin/login` 返回 200，登录页保持独立模板边界???

### 本轮边界
- 本轮没有启动浏览器截图验证；属于 Blade/CSS 静???和 HTTP smoke 改进。后续如继续深入 UI，需要启动本地服务后用浏览器???查桌面和移动端实际视觉效果???
- 后台仍需继续审计各业务页面是否充分使用统???工具条???面板密度和按钮权限刷新，本轮只是给全局外壳建立稳定规则???

## 126. 2026-06-09 后台 Blade 菜单页面真实 DB 权限覆盖修复

### 本次维护文件
- `database/migrations/2026_06_09_000001_fix_admin_page_menu_permission_routes.php`
- `tests/Feature/AdminPageMenuPermissionCoverageTest.php`
- `docs/admin-backend-blade-permission-final-checklist.md`

### TDD RED 依据
- 新增 `AdminPageMenuPermissionCoverageTest`，直接读取真??? DB ??? `permissions` 表，验证后台 `admin_page_*` Blade 页面菜单入口必须存在唯一启用??? `permissions.route` 配置???
- RED 阶段失败点：`/admin/dashboard`、`/admin/menus`、`/admin/permissions`、`/admin/roles` 宸叉敞鍐? Blade 页面路由，但真实 DB 缺少 `permissions.route` 菜单权限配置???
- 真实 DB 额外审计发现：`/admin/users` 存在 19 条启用的重复菜单权限 slug，会导致菜单树重复???角色授权混乱和后续页面权限审计误判???

### 生产代码调整
- 新增迁移 `FixAdminPageMenuPermissionRoutes`???
- 通过 `upsertAdminMenuPermission()` 补齐 4 个后台核心页面菜单权限：
  - `admin_dashboard`：`route=/admin/dashboard`，`api_route=admin_api_dashboardData`???
  - `admin_roles`：`route=/admin/roles`，`api_route=admin_api_roleList`???
  - `admin_permissions`：`route=/admin/permissions`，`api_route=admin_api_permissionTree`???
  - `admin_menus`：`route=/admin/menus`，`api_route=admin_api_menuTree`???
- 通过 `mergeDuplicateAdminRoute('/admin/users')` 合并重复用户菜单权限：保留最早启用的 `permissions.id=3`，把重复权限上的 `role_permissions` 授权迁移到保留权限，再禁用重复权限并写入 `deleted_at`???
- 回滚边界：只禁用本迁移补齐的 4 个核心页??? slug，不重新制???历史重??? `/admin/users` 权限???

### 参数与数据表字段含义
- `slug`：权限稳定标识，后台菜单、按钮显隐和角色授权共同依赖该字段???
- `route`：后??? Blade 页面访问路径，用于左侧菜单跳转和页面权限覆盖审计???
- `api_route`：该页面主要读取或维护接口的 Laravel 命名路由，后端接口最终由 `check.permission:admin` 按该字段鉴权???
- `guard_type=admin`：表示该权限只属于后台管理员体系，不能与前台代理???/普???客户菜单混用???
- `role_permissions.role_id`：被授权角色 ID，对??? `roles.id`???
- `role_permissions.permission_id`：被授权权限 ID，对??? `permissions.id`；重复权限合并时会迁移到保留权限 ID???

### 相关接口与页面消???
- `GET /admin/dashboard`：后台控制台 Blade 页面，菜单权??? slug ??? `admin_dashboard`???
- `GET /admin/roles`：后台角色管??? Blade 页面，菜单权??? slug ??? `admin_roles`???
- `GET /admin/permissions`：后台权限管??? Blade 页面，菜单权??? slug ??? `admin_permissions`???
- `GET /admin/menus`：后台菜单管??? Blade 页面，菜单权??? slug ??? `admin_menus`???
- `GET /admin/users`：后台用户管??? Blade 页面，当前真??? DB 只保留一条启用菜单权??? `admin_users_6a23fb27413fd`???
- `POST /api/admin/menus`：后台菜单接口会继续??? `permissions` ??? `role_permissions` 读取菜单和按钮权限，本迁移补齐的数据会被该接口消费???

### 验证记录
- `vendor\bin\phpunit tests\Feature\AdminPageMenuPermissionCoverageTest.php`：RED 阶段失败，缺??? `/admin/dashboard`、`/admin/menus`、`/admin/permissions`、`/admin/roles` 的真??? DB 权限配置???
- `php -l database\migrations\2026_06_09_000001_fix_admin_page_menu_permission_routes.php`：???过，无语法错误???
- `php -l tests\Feature\AdminPageMenuPermissionCoverageTest.php`：???过，无语法错误???
- `php artisan migrate`：已执行 `2026_06_09_000001_fix_admin_page_menu_permission_routes`???
- `vendor\bin\phpunit tests\Feature\AdminPageMenuPermissionCoverageTest.php`：???过???1 test / 2 assertions???
- `vendor\bin\phpunit tests\Feature\AdminBladeButtonPermissionRouteCoverageTest.php`：???过???1 test / 233 assertions???
- `vendor\bin\phpunit tests\Feature\AdminBladeButtonPermissionCoverageTest.php`：???过???2 tests / 117 assertions???
- `vendor\bin\phpunit tests\Feature\AdminButtonPermissionVisibilityTest.php`：???过???2 tests / 37 assertions???
- `vendor\bin\phpunit tests\Feature\AdminPermissionPlanTest.php`：???过???4 tests / 9 assertions???
- 真实 DB 复查：`/admin/dashboard`、`/admin/menus`、`/admin/permissions`、`/admin/roles` 已存在启用权限；`/admin/users` 只剩 1 条启用权限???

### 本轮边界
- 本轮修复的是后台 Blade 页面菜单权限字典完整性与重复 route 数据问题，没有改动前台菜单???后台按??? `data-permission`、接口中间件白名单或业务数据范围查询逻辑???
- 根目录未发现用户提到??? `plan.md`，本轮按当前项目内的 `docs/admin-auth-permission-plan.md` ??? `docs/admin-auth-permission-execution-checklist.md` 继续推进权限闭环???

### 126 补充修复：测试污染源与全量重??? route 清理
- 复跑 `AdminPageMenuPermissionCoverageTest` 时发??? `/admin/users` 仍出现第二条启用权限 `admin_users_6a26fad1ecbd9`???
- 根因定位??? `tests/Feature/AdminPermissionPlanTest.php` 会在真实 MySQL 中创建随??? `admin_users_*` ??? route ??? `/admin/users` 的测试菜单权限，测试结束后会污染真实权限字典???
- 已修??? `AdminPermissionPlanTest`：临时菜??? slug 改为 `test_admin_users_*`，临??? route 改为 `/admin/__test-users`，并??? `tearDown()` 中清??? `test_admin_users_*` ??? `test_admin_user_review_auth_*` 测试权限???
- 新增迁移 `database/migrations/2026_06_09_000002_merge_duplicate_admin_permission_routes.php`，扫描所??? `guard_type=admin`、`status=1` 的重复页??? route，保留最早权限???迁??? `role_permissions`、禁用重复记录???
- `php artisan migrate` 宸叉墽琛? `2026_06_09_000002_merge_duplicate_admin_permission_routes`，当前迁移状态为 Yes???
- 复查真实 DB：`duplicate_enabled_admin_routes=0`，表示后台启用页??? route 已无重复???
- 补充验证???
  - `php -l database\migrations\2026_06_09_000002_merge_duplicate_admin_permission_routes.php`：???过，无语法错误???
  - `php -l tests\Feature\AdminPermissionPlanTest.php`：???过，无语法错误???
  - `vendor\bin\phpunit tests\Feature\AdminPageMenuPermissionCoverageTest.php`：???过???1 test / 2 assertions???
  - `vendor\bin\phpunit tests\Feature\AdminPermissionPlanTest.php`：???过???4 tests / 9 assertions???
  - `vendor\bin\phpunit tests\Feature\AdminBladeButtonPermissionRouteCoverageTest.php`：???过???1 test / 233 assertions???
  - `vendor\bin\phpunit tests\Feature\AdminBladeButtonPermissionCoverageTest.php`：???过???2 tests / 117 assertions???
  - `vendor\bin\phpunit tests\Feature\AdminButtonPermissionVisibilityTest.php`：???过???2 tests / 37 assertions???

## 127. 2026-06-09 JWT 鉴权中间件错误响应多语言修复

### 本次维护文件
- `app/Http/Middleware/JwtAuthMiddleware.php`
- `tests/Feature/JwtAuthMiddlewareLocalizationTest.php`
- `docs/admin-backend-blade-permission-final-checklist.md`

### TDD RED 依据
- 新增 `JwtAuthMiddlewareLocalizationTest`，约??? JWT 鉴权中间件不能继续硬编码英文错误消息???
- RED 阶段失败点：缺少 Token 时仍返回 `'Authorization token not found'`，用户不存在时仍返回 `'User not found'`???
- RED 阶段失败点：中间件缺??? `$guard`、`$header`、`$token`、`$payload`、`$decodedGuard` 等核心参数的中文逻辑含义说明???

### 生产代码调整
- `JwtAuthMiddleware` 引入 `App\Constants\ResponseCode`，认证错误统???使用项目状???码常量???
- 缺少 `Authorization: Bearer ...` 请求头时返回 `__('response.token_missing')` ??? `ResponseCode::TOKEN_MISSING`???
- JWT 载荷中的用户 ID 无法在当??? guard 下找到用户时返回 `__('response.user_not_found')` ??? `ResponseCode::USER_NOT_FOUND`???
- JWT 解析异常统一返回 `__('response.auth_failed')` ??? `ResponseCode::AUTH_FAILED`，避免把内部异常文本直接暴露给前端???
- ??? `$guard`、`$header`、`$token`、`$payload`、`$decodedGuard` 补充中文逻辑注释，说明前??? user 与后??? admin 双守卫下的参数用途???

### 参数与接口边???
- `$guard`：当前认证守卫，`user` 表示前台用户，`admin` 表示后台管理员???
- `$header`：HTTP `Authorization` 请求头，必须符合 `Bearer {token}` 格式???
- `$token`：Bearer 后面??? JWT 字符串，只用于解析身份，不写入响应???
- `$payload`：JWT 解析后的载荷，包??? `sub`、`guard`、`jti` 等认证与单点登录字段???
- `$decodedGuard`：令牌载荷中的守卫类型，用于兼容前台与后台登录入口???
- `POST /api/admin/profileInfo`、`POST /api/admin/menus`、前台受保护接口等所有经??? `jwt.auth` 的接口都会消费本次修复后的多语言认证失败消息???

### 验证记录
- `vendor\bin\phpunit tests\Feature\JwtAuthMiddlewareLocalizationTest.php`：RED 阶段失败，命中硬编码英文响应与缺少中文参数说明???
- `php -l app\Http\Middleware\JwtAuthMiddleware.php`：???过，无语法错误???
- `php -l tests\Feature\JwtAuthMiddlewareLocalizationTest.php`：???过，无语法错误???
- `vendor\bin\phpunit tests\Feature\JwtAuthMiddlewareLocalizationTest.php`：???过???2 tests / 9 assertions???
- `vendor\bin\phpunit tests\Feature\AdminCheckPermissionMiddlewareReadabilityTest.php`：???过???1 test / 23 assertions???
- Laravel HTTP Kernel smoke：无 Token 请求 `POST /api/admin/profileInfo`，`zh-CN` 返回 `code=4004,message=令牌缺失`；`en` 返回 `code=4004,message=Token is missing`???

### 本轮边界
- 本轮只修??? JWT 认证入口的多语言响应和中文参数注释，没有改动 JWT 签发、刷新???SSO 校验、角色权限和业务数据范围逻辑???
- 仍需继续审计其他服务类中的英文日志或第三方接口内部消息，但本轮已覆盖前后??? API ???核心的认证失败响应入口???

## 128. 2026-06-09 MT4 Manager 服务错误响应多语???与中文参数注释修???

### 本次维护文件
- `app/Services/Mt4ManagerService.php`
- `resources/lang/zh-CN/response.php`
- `resources/lang/en/response.php`
- `tests/Feature/Mt4ManagerServiceLocalizationTest.php`
- `docs/admin-backend-blade-permission-final-checklist.md`

### TDD RED 依据
- 新增 `Mt4ManagerServiceLocalizationTest`，约??? MT4 服务返回数组中的用户可见 `message` 不能继续写死英文???
- RED 阶段失败点：连接失败仍返??? `'Connection failed'`，读取超时仍返回 `'Read timeout or empty response'`???
- RED 阶段失败点：`Mt4ManagerService` 缺少 `$host`、`$port`、`$apiKey`、`$apiVersion`、`$timeout`、`$cmd`、`$params`、`$paramStr`、`$fullCmd`、`$response`、`$parts`、`$status` 等核心参数和解析变量的中文???辑说明???

### 生产代码调整
- `Mt4ManagerService` 增加类级中文说明，明确该服务负责把开户注册???入金???出金???改密???锁定???组别变更等动作转换??? MT4 Manager Socket 命令???
- 给构造参数补齐中文含义：`$host` 表示 MT4 Manager API 主机地址，`$port` 表示端口，`$apiKey` 表示授权密钥，`$apiVersion` 表示协议版本，`$timeout` 表示 Socket 连接和读取超时时间???
- ??? `sendCommand($cmd, $params = [])` 补齐中文逻辑说明，明??? `$cmd` ??? MT4 命令名称，`$params` 是命令参数键值对，`$paramStr` 是协议参数片段，`$fullCmd` 是最终写??? Socket 的完整命令字符串，`$response`、`$parts`、`$status` ??? MT4 响应解析链路???
- 将连接失败响应改??? `__('response.mt4_connection_failed')`???
- 将读取失败或超时响应改为 `__('response.mt4_read_timeout')`???
- 中英??? `response.php` 新增 `mt4_connection_failed` ??? `mt4_read_timeout`，保证后端响应可以随当前 locale 输出???

### 相关接口和消???
- `Mt4ManagerService::registerUser()`：写??? `USER_RECORD_NEW` 命令，用??? MT4 ???户???
- `Mt4ManagerService::deposit()`：写??? `USER_DEPOSIT` 命令，用??? MT4 入金???
- `Mt4ManagerService::withdrawal()`：写??? `USER_WITHDRAW` 命令，用??? MT4 出金???
- `Mt4ManagerService::getAccountInfo()`：写??? `USER_INFO_GET` 命令，用于读取余额???净值???保证金和杠杆等账户信息???
- `Mt4ManagerService::changePassword()`：写??? `USER_PASS_CHANGE` 命令，用??? MT4 密码修改???
- `Mt4ManagerService::lockUser()` / `unlockUser()`：写??? `USER_LOCK` 命令，用于禁用或恢复交易???
- `Mt4ManagerService::changeGroup()`：写??? `USER_GROUP_CHANGE` 命令，用于调??? MT4 组别???
- `Mt4ManagerService::updateComment()`：写??? `USER_COMMENT_UPDATE` 命令，用于更??? MT4 备注字段???
- `response.mt4_connection_failed`：MT4 连接失败，中文为 `MT4连接失败`，英文为 `MT4 connection failed`???
- `response.mt4_read_timeout`：MT4 读取超时或响应为空，中文??? `MT4读取超时或响应为空`，英文为 `MT4 read timeout or empty response`???

### 验证记录
- `vendor\bin\phpunit tests\Feature\Mt4ManagerServiceLocalizationTest.php`：RED 阶段失败，命中硬编码英文响应和中文参数注释缺口???
- `php -l app\Services\Mt4ManagerService.php`：???过，无语法错误???
- `php -l resources\lang\zh-CN\response.php`：???过，无语法错误???
- `php -l resources\lang\en\response.php`：???过，无语法错误???
- `php -l tests\Feature\Mt4ManagerServiceLocalizationTest.php`：???过，无语法错误???
- `vendor\bin\phpunit tests\Feature\Mt4ManagerServiceLocalizationTest.php`：???过???2 tests / 20 assertions???

### 本轮边界
- 本轮只修??? MT4 Manager 服务自身返回错误的多语言文案和中文参数???辑注释，没有实际连??? MT4 服务器，也没有改变任何命令名称???参数编码???Socket 写入、响应解析或上层资金业务流程???
- `Log::warning('MT4 API is disabled in config.')`、`Log::error("MT4 Connection Error...")` ??? `Log::error("MT4 Read Error...")` 仍保留为运维日志文本；本轮重点是用户可见响应 message 的多语言化???

## 129. 2026-06-09 JWT 服务异常响应多语???与中文参数注释修???

### 本次维护文件
- `app/Services/JwtService.php`
- `resources/lang/zh-CN/response.php`
- `resources/lang/en/response.php`
- `tests/Feature/JwtServiceLocalizationTest.php`
- `docs/admin-backend-blade-permission-final-checklist.md`

### TDD RED 依据
- 新增 `JwtServiceLocalizationTest`，约??? JWT 服务层不能继续硬编码英文异常文案???
- RED 阶段失败点：`parseToken()` 中黑名单命中仍抛??? `'Token has been invalidated'`???
- RED 阶段失败点：`refreshToken()` 超出刷新窗口仍抛??? `'Token cannot be refreshed, refresh window expired'`???
- RED 阶段失败点：刷新失败仍拼??? `'Token refresh failed: '` 英文前缀???
- RED 阶段失败点：`JwtService` 缺少 `$secret`、`$ttl`、`$refreshTtl`、`$algo`、`$payload`、`$jti`、`$mergedPayload`、`$decoded`、`$cacheKey`、`$token`、`$newPayload` 等核心认证参数中文???辑说明???

### 生产代码调整
- ??? `JwtService.php` 重写??? UTF-8 可读中文注释版本，保留原有方法和认证流程：`generateToken()`、`parseToken()`、`refreshToken()`、`invalidateToken()`、`getPayload()`???
- 补齐类级说明，明确该服务是前??? `user` 与后??? `admin` 共用??? JWT 签发、解析???刷新和失效服务???
- 补齐安全字段说明：`sub` 表示登录主体 ID，`guard` 表示认证守卫，`jti` 表示令牌唯一编号，SSO 缓存只保存当前有??? `jti`???
- `parseToken()` 中黑名单命中改为 `__('response.jwt_token_invalidated')`???
- `refreshToken()` 超出刷新窗口改为 `__('response.jwt_refresh_window_expired')`???
- `refreshToken()` 捕获异常后的错误前缀改为 `__('response.jwt_refresh_failed')`???
- 中英??? `response.php` 新增 `jwt_token_invalidated`、`jwt_refresh_window_expired`、`jwt_refresh_failed`，保证服务层异常可被上层按当??? locale 输出???

### 相关接口和消???
- `JwtService::generateToken(array $payload)`：生成前台或后台 JWT，写??? `iss`、`iat`、`exp`、`nbf`、`jti` 与业务载荷，并把当前 `jti` 写入 SSO 缓存???
- `JwtService::parseToken(string $token)`：解析并校验 JWT，黑名单命中时返回多语言??? `response.jwt_token_invalidated`???
- `JwtService::refreshToken(string $token)`：在刷新窗口内刷??? JWT，超出窗口时返回 `response.jwt_refresh_window_expired`，刷新失败统???使用 `response.jwt_refresh_failed` 前缀???
- `JwtService::invalidateToken(string $token)`：把当前 JWT ??? `jti` 写入黑名单，并在当前 token ??? SSO ?????? token 时清??? SSO 缓存???
- `JwtService::getPayload(string $token)`：在???出和刷新场景读取 JWT 载荷，不按普通访??? token 过期时间拦截???
- `response.jwt_token_invalidated`：中文为 `令牌已失效`，英文为 `Token has been invalidated`???
- `response.jwt_refresh_window_expired`：中文为 `令牌已超过刷新窗口，请重新登录`，英文为 `Token cannot be refreshed, refresh window expired`???
- `response.jwt_refresh_failed`：中文为 `令牌刷新失败`，英文为 `Token refresh failed`???

### 验证记录
- `vendor\bin\phpunit tests\Feature\JwtServiceLocalizationTest.php`：RED 阶段失败，命中英文硬编码异常和中文参数注释缺口???
- `php -l app\Services\JwtService.php`：???过，无语法错误???
- `php -l resources\lang\zh-CN\response.php`：???过，无语法错误???
- `php -l resources\lang\en\response.php`：???过，无语法错误???
- `php -l tests\Feature\JwtServiceLocalizationTest.php`：???过，无语法错误???
- `vendor\bin\phpunit tests\Feature\JwtServiceLocalizationTest.php`：???过???2 tests / 23 assertions???
- `vendor\bin\phpunit tests\Feature\JwtAuthMiddlewareLocalizationTest.php`：???过???2 tests / 9 assertions，确认上??? JWT 中间件多语言响应未被破坏???

### 本轮边界
- 本轮只修??? `JwtService` 服务层异常文案多语言化和中文参数逻辑注释，没有改??? JWT 签名算法、TTL、刷新窗口???SSO 缓存键???黑名单键???Token 载荷结构或中间件鉴权流程???
- `refreshToken()` 仍保留原有异常包装模式，只把错误前缀改为多语??? key；底层异常详情仍追加在冒号后，便于调试定位???

## 130. 2026-06-09 用户注册服务身份证重复提示多语言与中文参数注释修???

### 本次维护文件
- `app/Services/UserRegistrationService.php`
- `resources/lang/zh-CN/response.php`
- `resources/lang/en/response.php`
- `tests/Feature/UserRegistrationServiceLocalizationTest.php`
- `docs/admin-backend-blade-permission-final-checklist.md`

### TDD RED 依据
- 新增 `UserRegistrationServiceLocalizationTest`，约束注册服务不能继续把 `__('front.id_card_no')` 与英??? `' already exists'` 拼接成半中半英提示???
- RED 阶段失败点：`validateRegistrationData()` ??? `validateRegistration()` 中身份证号重复提示仍使用英文拼接???
- RED 阶段失败点：注册服务缺少 `$data`、`$parentId`、`$accountType`、`$commissionMode`、`$userId`、`$userLogin`、`$userInfo`、`$parentInfo`、`$familyTree`、`$treeIds` 等核心参数和中间变量的中文???辑说明???

### 生产代码调整
- 将正式注册校??? `validateRegistrationData()` 中的身份证号重复提示改为 `__('response.id_card_exists')`???
- 将注册前置验??? `validateRegistration()` 中的身份证号重复提示改为 `__('response.id_card_exists')`???
- 中英??? `response.php` 新增 `id_card_exists`，中文为 `证件号码已存在`，英文为 `ID card number already exists`???
- 补齐 `register()` 参数说明，明??? `$data` 是注册表单数据，`$parentId` 是邀请人业务 user_id，`$accountType` 是注册账号类型，`$commissionMode` 是注册返佣模式???
- 补齐注册写库过程说明，明??? `$userId`、`$userLogin`、`$userInfo`、`$parentInfo` 的数据表来源和用途???
- 补齐 `createUserInfo()` ??? `createAgentDescendantRows()` 说明，明??? `$familyTree` 是代理家族链，`$treeIds` 是拆分后的用户链路???

### 相关接口和消???
- `UserRegistrationService::register(array $data, ?int $parentId, ?int $accountType)`：前台代理商/普???客户注册写库入口，创建 `user_logins`、`user_infos`、`user_auths`，并同步 `agent_descendants`???
- `UserRegistrationService::validateRegistration($data, $parentId, int $accountType, string $commissionMode)`：注册前置验证入口，供控制器在真正写库前复用???
- `response.id_card_exists`：身份证号或证件号码重复提示，避免中文字段名拼接英文 `already exists`???

### 验证记录
- `vendor\bin\phpunit tests\Feature\UserRegistrationServiceLocalizationTest.php`：RED 阶段失败，命中半中半英文案和中文参数注释缺口???
- `php -l app\Services\UserRegistrationService.php`：???过，无语法错误???
- `php -l resources\lang\zh-CN\response.php`：???过，无语法错误???
- `php -l resources\lang\en\response.php`：???过，无语法错误???
- `php -l tests\Feature\UserRegistrationServiceLocalizationTest.php`：???过，无语法错误???
- `vendor\bin\phpunit tests\Feature\UserRegistrationServiceLocalizationTest.php`：???过???2 tests / 14 assertions???
- `vendor\bin\phpunit tests\Feature\FrontAuthControllerLocalizationTest.php`：???过???2 tests / 36 assertions，确认前台认证本地化契约未被破坏???

### 本轮边界
- 本轮只修复身份证重复提示的多语言 key 和注册服务参数注释，没有改变注册事务、账号类型判断???邀请人规则、ID 生成、user_logins/user_infos/user_auths 写入??? agent_descendants 关系同步逻辑???
- 其它旧前台兼容接口中返回 `FAIL`、`CLASSINVALID` 等历??? Ajax 状???码仍保留，后续???要结合旧前端协议逐个判断是否可多语言化，不能???单替换???

## 131. 2026-06-09 后台 Session 鉴权中间件多语言与中文参数注释修???

### 本次处理目标
- 继续??? `plan.md` / `docs/admin-auth-permission-plan.md` 推进后台鉴权、多语言和中文???辑注释要求???
- 修复 `app/Http/Middleware/AdminAuthenticate.php` ??? JSON 未认证响应硬编码英文 `Unauthenticated.` 的问题???
- 补齐后台 Session guard 鉴权边界的中文功能注释???参数含义注释和分支逻辑说明???

### 修改文件
- `app/Http/Middleware/AdminAuthenticate.php`
  - 类注释说明该中间件用于后??? Blade 页面或兼容入口的 Session guard 鉴权???
  - `handle(Request $request, Closure $next)` 方法补充 `$request`、`$next`、`expectsJson`、`admin_page_login` 的中文???辑含义???
  - JSON 未登录响应从硬编码英文改??? `__('response.auth_failed')`，继续保??? HTTP 401???
  - 普???页面未登录仍跳??? `admin_page_login`，不改变现有后台登录入口???
- `tests/Feature/AdminAuthenticateMiddlewareLocalizationTest.php`
  - 新增测试覆盖多语???响应 key、禁止硬编码英文和关键中文注释要求???

### 接口/响应影响
- 适用边界：使??? `AdminAuthenticate` 中间件保护的后台页面或兼容路由???
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
- 页面请求仍重定向??? `/admin/login` 对应??? `admin_page_login` 命名路由???

### 验证记录
- `php -l app\Http\Middleware\AdminAuthenticate.php`：???过???
- `php -l tests\Feature\AdminAuthenticateMiddlewareLocalizationTest.php`：???过???
- `vendor\bin\phpunit tests\Feature\AdminAuthenticateMiddlewareLocalizationTest.php`：???过???2 tests / 8 assertions???
- `vendor\bin\phpunit tests\Feature\JwtAuthMiddlewareLocalizationTest.php`：???过???2 tests / 9 assertions???
- 本机 PHP 输出仍包含历??? Xdebug 配置提示，不影响命令???出码和测试结果???


## 132. 2026-06-09 用户服务 UserService 中文逻辑注释与参数含义补???

### 本次处理目标
- 继续执行 `plan.md` / `docs/admin-auth-permission-plan.md` 中???所有模块文件及参数必须有详细中文???辑注释”的要求???
- 清理 `app/Services/UserService.php` 中遗留英文功能注释，避免后续后台用户迁移时误读字段含义???
- 不改变用户详情???资料更新???状态更新和注销标记的现有业务行为???

### 修改文件
- `app/Services/UserService.php`
  - 重写??? UTF-8 中文注释版本，保留原有四个公???方法???
  - 补充 `UserLogin`、`UserInfo`、`UserAuth` 三个模型在用户资料链路中的职责说明???
  - 补充 `$userId`、`$data`、`is_enabled`、`auth_status`、`is_cancelled` 的真实数据表含义???
  - 移除未使用的 `Hash` 引用，减少无效依赖???
- `tests/Feature/UserServiceCommentReadabilityTest.php`
  - 新增测试覆盖英文注释残留???查和核心参数中文含义???查???

### 业务边界说明
- `getUserDetail(int $userId)`：读??? `user_logins`、`user_infos`、`user_auths` 组合详情；登录记录不存在时返回空数组???
- `updateUserInfo(int $userId, array $data)`：只更新 `user_infos` 表，调用方仍???负责字段白名单???
- `updateUserStatus(int $userId, array $data)`：事务内兼容更新 `user_logins.is_enabled` ??? `user_auths.status`???
- `deleteUser(int $userId)`：只写入 `user_logins.is_cancelled=1`，不物理删除业务资料???

### 验证记录
- `php -l app\Services\UserService.php`：???过???
- `php -l tests\Feature\UserServiceCommentReadabilityTest.php`：???过???
- `vendor\bin\phpunit tests\Feature\UserServiceCommentReadabilityTest.php`：???过???2 tests / 12 assertions???
- `vendor\bin\phpunit tests\Feature\AdminUserControllerCommentReadabilityTest.php tests\Feature\UserControllerCommentReadabilityTest.php`：???过???1 test / 25 assertions???
- 本机 PHP 输出仍包含历??? Xdebug 配置提示，不影响命令???出码和测试结果???


## 133. 2026-06-09 公共上传控制??? Common UploadController 中文注释与多语言响应修复

### 本次处理目标
- 继续执行后端多语???与所有模块中文???辑注释要求???
- 清理 `app/Http/Controllers/Common/UploadController.php` 中英文注释残留???
- 统一上传成功文案??? `response.uploaded`，保持前后台 API 响应口径???鑷淬??

### 修改文件
- `app/Http/Controllers/Common/UploadController.php`
  - 重写??? UTF-8 中文注释版本???
  - 补充 `file`、`type`、`avatar`、`id_card`、`bank_card`、`voucher`、`general`、`allowedMimes` 的参数含义???
  - 成功消息??? `messages.upload_success` 改为 `response.uploaded`???
- `tests/Feature/CommonUploadControllerCommentReadabilityTest.php`
  - 新增公共上传控制器注释与多语???响应测试???

### 业务边界说明
- 响应结构仍为旧前端兼容格式：`code`、`msg`、`data.url/path/name/size`???
- 上传目录仍为 public disk ??? `{type}/{Ymd}`???
- 图片类业务只允许 `jpeg/png/jpg/gif`???
- `general` 额外允许 `pdf/doc/docx/xls/xlsx`???

### 验证记录
- `php -l app\Http\Controllers\Common\UploadController.php`：???过???
- `php -l tests\Feature\CommonUploadControllerCommentReadabilityTest.php`：???过???
- `vendor\bin\phpunit tests\Feature\CommonUploadControllerCommentReadabilityTest.php`：???过???3 tests / 15 assertions???
- `vendor\bin\phpunit tests\Feature\FrontUploadControllerCommentReadabilityTest.php --filter test_front_upload_controller_contains_required_chinese_logic_comments`：???过???1 test / 21 assertions???
- `vendor\bin\phpunit tests\Feature\FrontUiRegressionTest.php --filter test_front_upload_apis_use_readable_resource_style_routes`：???过???1 test / 14 assertions???
- 本机 PHP 输出仍包含历??? Xdebug 配置提示，不影响命令???出码和测试结果???


## 134. 2026-06-09 后台权限名称、字符串与功能作用中文说??? MD 补齐

### 本次处理目标
- 响应“后台所有权限名称必须在 MD 文件中有中文注释、对应字符串和功能作用???的要求???
- 直接读取当前真实数据??? `permissions` 表中 `guard_type=admin` 的后台权限，生成可维护的中文说明文档???
- 增加自动化测试，防止后续新增后台权限后遗漏文档说明???

### 新增/修改文件
- `docs/admin-permission-name-reference.md`
  - 新增后台权限名称说明文档???
  - 数据来源为真??? `permissions` 表，当前共记??? `195` 条后台权限???
  - 每条权限包含 `ID`、`parent_id`、`类型`、`权限名称`、`权限字符??? slug`、`接口路由字符??? api_route`、`页面路由 route`、`状???` ??? `功能作用`???
  - 对历??? DB 中已出现??? mojibake 权限名称做只读还原，文档中展示可读中文，不直接修改数据库原??????
- `tests/Feature/AdminPermissionNameReferenceDocumentationTest.php`
  - 新增后台权限说明文档覆盖测试???
  - 测试直接读取真实 `permissions.guard_type=admin` 记录，并逐条断言 MD 中包??? `slug`、非??? `api_route`、非??? `route` 和可读权限名称???
  - 测试内补??? `$documentPath`、`$document`、`$permissions`、`$name`、`$slug`、`$apiRoute`、`$pageRoute` 的中文参数含义注释???

### 文档字段说明
- `权限名称`：来??? `permissions.name`，用于后台权限管理页面展示???
- `权限字符串`：来??? `permissions.slug`，用于角色授权???Blade/JS `data-permission` 和按钮显隐判断???
- `接口路由字符串`：来??? `permissions.api_route`，由 `check.permission:admin` 用于接口层鉴权???
- `页面路由`：来??? `permissions.route`，用于后??? Blade 菜单或页面跳转???
- `功能作用`：按菜单权限、页面权限???按???/接口权限分别说明该权限控制的业务边界???

### 验证记录
- `php artisan tinker --execute="echo DB::table('permissions')->where('guard_type','admin')->count();"`：返??? `195`???
- `php -l tests\Feature\AdminPermissionNameReferenceDocumentationTest.php`：???过???
- `php -l docs\admin-permission-name-reference.md`：???过???
- `vendor\bin\phpunit tests\Feature\AdminPermissionNameReferenceDocumentationTest.php`：???过???1 test / 810 assertions???

### 本轮边界
- 本轮只新增后台权限中文说??? MD 和覆盖测试，没有修改 `permissions`、`roles`、`role_permissions` 真实数据???
- 文档中对历史乱码权限名做可读展示，但未执行数据库修复；如后续要清??? DB 原始 `permissions.name` 编码，需要单独迁移并备份验证???
- 当前仍保留真实数据库里已存在的停用权限与历史重复权限记录，因为用户要求覆盖???后台所有权限名称???，本轮不擅自删除历史权限数据???


## 135. 2026-06-09 Permission 权限模型中文注释可读性与参数含义补齐

### 本次处理目标
- 继续执行“所有模块文件及参数必须有详细中文注释和逻辑注释”的要求???
- 维护 `app/Models/Permission.php`，该模型是后台菜单???页面???按钮和接口鉴权的核心数据源???
- 统一 `slug`、`api_route`、`guard_type`、`parent_id`、`type`、`status`、`$query` 等核心字???/参数的中文含义说明???

### 修改文件
- `app/Models/Permission.php`
  - 补强类级说明：`permissions` 表保存前后台菜单、页面???按钮和接口权限字典???
  - 明确 `slug` 表示稳定权限字符串，供前端按钮显隐和后端授权判断使用???
  - 明确 `api_route` 表示 Laravel 命名路由，供 `check.permission:admin` 做接口鉴权???
  - 明确 `guard_type` 用于区分 `admin` ??? `front`，避免前后台权限混用???
  - 统一 `parent_id`、`name`、`route`、`icon`、`type`、`sort`、`status` 的字段含义注释???
  - 调整 `scopeButton()` 注释为???限定按钮或接口动作权限”，与后台权限分类口径一致???
- `tests/Feature/PermissionModelCommentReadabilityTest.php`
  - 新增 TDD 可读性测试，要求 Permission 模型包含可读中文功能注释和参数含义???
  - 禁止历史 mojibake 乱码片段重新出现在权限模型注释中???

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\PermissionModelCommentReadabilityTest.php` 首次失败，提示缺??? `slug 表示稳定权限字符串` 等明确中文说明???
- GREEN：补齐字段说明和 scope 标题后，专项测试通过???

### 验证记录
- `php -l app\Models\Permission.php`：???过???
- `php -l tests\Feature\PermissionModelCommentReadabilityTest.php`：???过???
- `vendor\bin\phpunit tests\Feature\PermissionModelCommentReadabilityTest.php`：???过???1 test / 21 assertions???
- `vendor\bin\phpunit tests\Feature\AdminPermissionNameReferenceDocumentationTest.php`：???过???1 test / 810 assertions???

### 本轮边界
- 本轮只维护权限模型源码注释和专项测试，没有修??? `permissions` 表结构???模型字段???关联关系???scope 查询行为或鉴权中间件逻辑???
- 后台权限名称说明 MD 仍以真实 DB `permissions.guard_type=admin` 为数据来源，本轮回归确认其覆盖契约未被破坏???


## 136. 2026-06-09 Role 角色模型与角色权限模型可读???测试升???

### 本次处理目标
- 继续执行“所有模块文件及参数必须有详细中文注释和逻辑注释”的要求???
- 维护 `app/Models/Role.php`，该模型是后台管理员角色、前台代???/客户角色、`role_permissions` 授权关系??? `role_data_scopes` 数据范围的核心入口???
- 升级旧的 `AdminRolePermissionModelReadabilityTest`，移除历史乱码断???，改为可读中文质量门???

### 修改文件
- `app/Models/Role.php`
  - ??? `guard_type 用于区分 admin ??? front` 统一??? `guard_type 用于区分 admin ??? front`，与权限说明文档口径???鑷淬??
  - ??? `name`、`guard_type`、`description`、`permissions`、`status` 字段说明统一为???字段名 表示 …??????格式???
  - ??? `$slug` 参数说明统一??? `$slug 表示 permissions.slug`，明确它是前端菜单???前端按钮和后端接口共用的稳定权限标识???
  - 未修改模型字段???关联关系???`hasPermission()` 行为或任何数据库读写逻辑???
- `tests/Feature/AdminRolePermissionModelReadabilityTest.php`
  - 重写??? UTF-8 可读中文测试???
  - 同时约束 `Role` ??? `Permission` 两个核心权限模型???
  - 要求源码说明 `roles`、`permissions`、`role_permissions`、`role_data_scopes` 的职责边界???
  - 禁止常见 UTF-8/GBK 错误解码后的乱码片段重新出现???

### TDD 记录
- RED：旧测试运行失败，原因是仍断???旧的 `slug 是前端按钮和后端接口共同使用的稳定权限标识` 文案，无法???配当前统一后的 `slug 表示稳定权限字符串` 注释契约???
- GREEN：重写测试为可读中文契约，并按失败点补齐 `Role.php` 字段/参数说明后???过???

### 验证记录
- `php -l app\Models\Role.php`：???过???
- `php -l tests\Feature\AdminRolePermissionModelReadabilityTest.php`：???过???
- `vendor\bin\phpunit tests\Feature\AdminRolePermissionModelReadabilityTest.php`：???过???1 test / 33 assertions???
- `vendor\bin\phpunit tests\Feature\PermissionModelCommentReadabilityTest.php`：???过???1 test / 21 assertions???
- `vendor\bin\phpunit tests\Feature\AdminPermissionNameReferenceDocumentationTest.php`：???过???1 test / 810 assertions???

### 本轮边界
- 本轮只处理角色与权限模型的注释可读??????参数含义和测试质量门，不修??? `roles`、`permissions`、`role_permissions`、`role_data_scopes` 鏁版嵁銆?
- `roles.permissions` JSON 仍仅作为历史兼容字段保留，真实授权来源继续是 `role_permissions` 中间表???
- 超级权限判断仍保持原逻辑：`hasPermission('*')` 仅在角色名为 `super_admin` 时返??? true???


## 137. 2026-06-09 后台管理员认证模型中文注释与参数含义补齐

### 本次处理目标
- 继续执行“所有模块文件及参数必须有详细中文注释和逻辑注释”的要求???
- 修复后台认证链路模型 `Admin`、`AdminRole`、`AdminLoginLog` 中的历史乱码注释和英文占位注释???
- 明确后台管理员账号???角色绑定???JWT 标识、权??? slug、登录日志字段的业务含义???

### 修改文件
- `app/Models/Admin.php`
  - 补充 `admins` 表职责：保存后台管理员登录账号???角色绑定和登录状??????
  - 补充 `role_id`、`jwt_token_id`、`username`、`email`、`password`、`status`、`last_login_ip`、`last_login_at`、`login_count` 等字段含义???
  - 补充 `hasPermission($slug)` 参数说明：`$slug` 表示 `permissions.slug`，后台菜单???按钮和接口共用的稳定权限字符串???
  - 明确 `getAllPermissions()` 的权限唯???来源??? `role_permissions` 中间表，不读??? `roles.permissions` JSON???
  - 补充 `role()`、`loginLogs()`、`isActive()` 的中文???辑边界???
- `app/Models/AdminRole.php`
  - 改为“管理员角色兼容模型”说明，明确底层数据表仍??? `roles`???
  - 明确该模型只兼容旧代码调用，新权限链路优先使??? `Role` 模型??? `role_permissions` 中间表???
  - 补充 `name`、`guard_type`、`description`、`permissions`、`status` 字段含义???
- `app/Models/AdminLoginLog.php`
  - 补充 `admin_login_logs` 表职责：记录后台管理员登录审计信息???
  - 补充 `admin_id`、`login_ip`、`ip_location`、`user_agent` 字段含义???
  - 明确 `admin()` 关联用于审计页面展示登录账号、邮箱和角色信息???
- `tests/Feature/AdminAuthModelCommentReadabilityTest.php`
  - 新增后台管理员认证模型可读???测试???
  - ???查三个模型必须包含中文职责???字段含义???参数说明，并禁止常见乱码和英文占位片段???

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AdminAuthModelCommentReadabilityTest.php` 首次失败，提??? `Admin` 模型缺少 `admins 表保存后台管理员登录账号、角色绑定和登录状???` 等中文说明???
- GREEN：补齐三个模型注释后测试通过???

### 验证记录
- `php -l app\Models\Admin.php`：???过???
- `php -l app\Models\AdminRole.php`：???过???
- `php -l app\Models\AdminLoginLog.php`：???过???
- `php -l tests\Feature\AdminAuthModelCommentReadabilityTest.php`：???过???
- `vendor\bin\phpunit tests\Feature\AdminAuthModelCommentReadabilityTest.php`：???过???1 test / 32 assertions???
- `vendor\bin\phpunit tests\Feature\AdminRolePermissionModelReadabilityTest.php`：???过???1 test / 33 assertions???
- `vendor\bin\phpunit tests\Feature\AdminPermissionNameReferenceDocumentationTest.php`：???过???1 test / 810 assertions???
- `rg` 扫描 `Admin.php`、`AdminRole.php`、`AdminLoginLog.php` 的历史乱码和英文占位片段：无命中???

### 本轮边界
- 本轮只修改注释与测试，不改变后台登录、JWT、角色关联???权限判断???登录日志写入或数据库结构???
- `AdminRole` 继续作为旧代码兼容模型保留，真实角色权限授权仍以 `Role` ??? `role_permissions` 为准???


## 138. 2026-06-09 前台用户认证与业务资料模型中文注释补???

### 本次处理目标
- 继续执行“所有模块文件及参数必须有详细中文注释和逻辑注释”的要求???
- 修复前台用户认证链路模型 `UserLogin`、`UserInfo`、`User`、`UserLoginLog` 中的历史乱码注释和英文占位注释???
- 明确前台代理???/普???客户账号???角色绑定???JWT 标识、业务资料???代理层级和登录日志字段含义???

### 修改文件
- `app/Models/UserLogin.php`
  - 补充 `user_logins` 表职责：保存前台登录账号、密码哈希???角色绑定和登录状??????
  - 补充 `user_id`、`email`、`password`、`account_type`、`role_id`、`is_enabled`、`is_cancelled`、`source_type`、`jwt_token_id`、`last_login_ip`、`last_login_at` 字段含义???
  - 明确 `role_id` 对应 `roles.id`，前台代理商和普通客户菜单权限都通过该角色读??? `role_permissions`???
  - 补充 `role()`、`userInfo()`、`loginLogs()`、`isAgent()`、`isCustomer()`、`isActive()` 的中文???辑边界???
- `app/Models/UserInfo.php`
  - 补充 `user_infos` 表职责：保存前台业务用户资料、代理层级???资金字段和 MT4 状??????
  - 按身份字段???层级字段???资金字段???交易字段???审核字段???MT4 字段、地???字段、审计字段分组说??? `$fillable`???
  - 明确 `user_id`、`login_id`、`parent_id`、`family_tree`、`account_type`、`auth_status` 的业务含义???
  - 补充 `getAncestorIds()`、直属代???/直属客户、实名认证???代理等级???组配置等关系说明???
- `app/Models/User.php`
  - 改为 Laravel 默认前台用户兼容模型说明???
  - 明确当前业务登录主体优先使用 `UserLogin`，该模型只保??? Laravel 默认用户体系兼容能力???
  - 补充 `role_id` ??? `$slug` 权限参数说明，避免误??? `User` 作为当前主业务登录表???
- `app/Models/UserLoginLog.php`
  - 补充 `user_login_logs` 表职责：记录前台用户登录审计信息???
  - 补充 `login_id`、`user_id`、`login_ip`、`ip_location`、`user_agent` 字段含义???
- `tests/Feature/FrontUserAuthModelCommentReadabilityTest.php`
  - 新增前台用户认证模型可读性测试???
  - ???查四个模型必须包含中文职责???字段含义???参数说明，并禁止常见乱码和英文占位片段???

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\FrontUserAuthModelCommentReadabilityTest.php` 首次失败，提??? `UserLogin` 模型缺少 `user_logins 表保存前台登录账号???密码哈希???角色绑定和登录状???` 等中文说明???
- GREEN：补齐四个模型注释后测试通过???

### 验证记录
- `php -l app\Models\UserLogin.php`：???过???
- `php -l app\Models\UserInfo.php`：???过???
- `php -l app\Models\User.php`：???过???
- `php -l app\Models\UserLoginLog.php`：???过???
- `php -l tests\Feature\FrontUserAuthModelCommentReadabilityTest.php`：???过???
- `vendor\bin\phpunit tests\Feature\FrontUserAuthModelCommentReadabilityTest.php`：???过???1 test / 45 assertions???
- `vendor\bin\phpunit tests\Feature\AdminRolePermissionModelReadabilityTest.php`：???过???1 test / 33 assertions???
- `vendor\bin\phpunit tests\Feature\DefaultAdminAndFrontMenuRoleMigrationTest.php`：???过???2 tests / 57 assertions???
- `vendor\bin\phpunit tests\Feature\UserServiceCommentReadabilityTest.php`：???过???2 tests / 12 assertions???
- `rg` 扫描 `UserLogin.php`、`UserInfo.php`、`User.php`、`UserLoginLog.php` 的历史乱码和英文占位片段：无命中???

### 本轮边界
- 本轮只修改模型注释与专项测试，不改变前台登录、JWT、角色授权???代理树、资金字段???MT4 状??????登录日志写入或数据库结构???
- 前台菜单权限仍按 `user_logins.role_id -> roles -> role_permissions -> permissions` 读取，代理商和普通客户菜单边界不变???


## 139. 2026-06-09 资金入金/出金模型中文逻辑注释与真??? DB 字段核对

### 本次处理目标
- 继续执行“所有模块文件及参数必须有详细中文注释和逻辑注释”的要求???
- 清理资金相关模型中残留的英文占位注释和历史编码残留，避免后台资金审核、批量导入和数据范围???发时误读字段含义???
- 使用真实数据库字段和记录作为注释依据，确保注释中的字段名称与当前 `co_crmv5` 鏁版嵁搴撲竴鑷淬??

### 修改文件
- `app/Models/DepositRecord.php`
  - 改为“入金记录模型???说明，明确 `deposit_records` 表保存前台用户入金申请和后台审核结果???
  - 补充 `user_id`、`user_name`、`mt4_ticket`、`amount`、`actual_amount`、`exchange_rate`、`channel_name`、`channel_order_no`、`local_order_no`、`status`、`payment_time`、`remarks`、`created_by`、`updated_by` 的中文含义???
  - 补充 `user()` 关联中外??? `deposit_records.user_id` 和目标键 `user_infos.user_id` 的???辑说明???
- `app/Models/WithdrawRecord.php`
  - 改为“出金记录模型???说明，明确 `withdraw_records` 表保存前台用户出金申请和后台处理结果???
  - 根据真实表结构使??? `apply_amount` 说明出金申请金额，避免误写为不存在的 `amount` 字段???
  - 补充银行卡???手续费、拒绝原因???第三方订单号???MT4 返回状???和管理员审计字段的中文含义???
- `app/Models/DepositImport.php`
  - 补充“批量入金导入模型???说明，明确 `deposit_imports` 表用??? Excel/CSV 批量入金导入记录???
  - 补充 `user_id`、`user_name`、`amount`、`remarks`、`mt4_order_id`、`batch_no`、`is_synced`、`fail_reason`、`created_by`、`updated_by` 的中文含义???
- `app/Models/WithdrawImport.php`
  - 补充“批量出金导入模型???说明，明确 `withdraw_imports` 表用??? Excel/CSV 批量出金导入记录???
  - 补充 `amount` 字段为导入记录出金金额???`is_synced` 为后续出金处理或资金系统同步状???等中文说明???
- `tests/Feature/AdminFundModelCommentReadabilityTest.php`
  - 新增资金模型中文注释质量门禁???
  - ???查四个模型必须包含真实数据表职责、关键字段含义和用户关联说明???
  - 禁止 `Table Name`、`Relation:`、`Maintains user deposit transaction history`、`Records the withdrawal transaction details` 等旧英文占位注释回流???

### 真实 DB 数据来源
- `Schema::getColumnListing('deposit_records')` 返回字段包含：`id,user_id,user_name,mt4_ticket,amount,actual_amount,exchange_rate,channel_name,channel_order_no,local_order_no,status,payment_time,remarks,created_by,updated_by,created_at,updated_at,deleted_at`???
- `Schema::getColumnListing('withdraw_records')` 返回字段包含：`id,user_id,user_name,mt4_ticket,apply_amount,actual_amount,fee,exchange_rate,rmb_fee,bank_no,bank_name,bank_addr,status,local_order_no,third_order_no,reject_reason,mt4_return_status,created_by,updated_by,created_at,updated_at,deleted_at`???
- `Schema::getColumnListing('deposit_imports')` ??? `Schema::getColumnListing('withdraw_imports')` 返回字段均包含：`id,user_id,user_name,amount,remarks,mt4_order_id,batch_no,is_synced,fail_reason,created_by,updated_by,created_at,updated_at,deleted_at`???
- 当前真实数据量：
  - `deposit_records`???18 条；样例 `id=54,user_id=600106,local_order_no=pas600115260325009381,status=01`???
  - `withdraw_records`???12 条；样例 `id=35,user_id=600106,local_order_no=WDR202603240050,status=2`???
  - `deposit_imports`???0 条；当前无样例记录???
  - `withdraw_imports`???0 条；当前无样例记录???

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AdminFundModelCommentReadabilityTest.php` 首次失败，提??? `DepositRecord.php` 缺少“入金记录模型???，且仍包含 `Table Name` 英文占位注释???
- GREEN：补齐四个资金模型中文???辑注释并按真实 DB 字段修正测试期望后，专项测试通过???

### 验证记录
- `php -l app\Models\DepositRecord.php`：???过???
- `php -l app\Models\WithdrawRecord.php`：???过???
- `php -l app\Models\DepositImport.php`：???过???
- `php -l app\Models\WithdrawImport.php`：???过???
- `php -l tests\Feature\AdminFundModelCommentReadabilityTest.php`：???过???
- `vendor\bin\phpunit tests\Feature\AdminFundModelCommentReadabilityTest.php`：???过???2 tests / 52 assertions???
- `vendor\bin\phpunit tests\Feature\AdminBatchAmountImportModuleTest.php`：???过???4 tests / 30 assertions???
- `vendor\bin\phpunit tests\Feature\AdminBatchAmountImportRetryModuleTest.php`：???过???5 tests / 27 assertions???
- `vendor\bin\phpunit tests\Feature\AdminBatchAmountImportPermissionMigrationTest.php`：???过???1 test / 25 assertions???
- `vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php`：???过???3 tests / 1521 assertions???
- `rg "鍏呭€|出金|数据|关联|Table Name|Relation:|Maintains user deposit transaction history|Records the withdrawal transaction details" app\Models\DepositRecord.php app\Models\WithdrawRecord.php app\Models\DepositImport.php app\Models\WithdrawImport.php`：无命中???

### 本轮边界
- 本轮只修改资金相关模型注释和新增注释质量测试，没有改变入金???出金???批量导入???同步状态???用户关联或数据库结构???
- `withdraw_records` 的申请金额字段以真实 DB 字段 `apply_amount` 为准；`deposit_imports`、`withdraw_imports` 当前真实表为空，已记录表结构作为测试数据依据???
## 140. 2026-06-09 菜单、审计日志与邮件配置模型中文注释补齐

### 本次处理目标
- 继续执行“所有模块文件及参数必须有详细中文注释和逻辑注释”的要求???
- 优先清理与后台权限菜单???审计追踪???系统邮件配置相关的模型注释???
- 保持业务行为不变，仅补充真实表职责???字段含义???关联参数和本地化???辑说明???

### 修改文件
- `app/Models/Menu.php`
  - 补充 `menus` 表保存前后台 Blade 页面可见动???菜单配置的职责说明???
  - 补充 `title`、`title_en`、`icon`、`path`、`component`、`parent_id`、`permission_id`、`guard_type`、`type`、`is_visible`、`is_external`、`sort`、`status` 的中文含义???
  - 补充 `parent()`、`children()`、`permission()` 关联外键说明???
  - 补充 `scopeAdmin()`、`scopeFront()`、`scopeVisible()`、`scopeActive()`、`scopeRoot()` 查询作用域参数说明???
  - 补充 `getLocalizedTitleAttribute()` 按当??? locale 返回中文或英文菜单标题的多语???逻辑说明???
- `app/Models/OperationLog.php`
  - 补充 `operation_logs` 表保存后台管理员业务操作审计记录的职责说明???
  - 补充 `admin_id`、`admin_name`、`target_user_id`、`order_no`、`content`、`ip`、`action_type` 字段含义???
  - 补充 `admin()` ??? `targetUser()` 关联外键和目标键说明???
- `app/Models/DataOperationLog.php`
  - 补充 `data_operation_logs` 表保存模型数据变更前后审计快照的职责说明???
  - 补充 `model_type`、`model_id`、`before_data`、`after_data`、`operator_id` 字段含义???
  - 补充 `$casts` ??? JSON 快照字段自动转数组的参数说明???
- `app/Models/MailSetting.php`
  - 补充 `mail_settings` 表保存系统邮件发送配置的职责说明???
  - 补充 `driver`、`host`、`port`、`username`、`password`、`encryption`、`from_address`、`from_name` 字段含义???
- `tests/Feature/AdminMenuAuditConfigModelCommentReadabilityTest.php`
  - 新增菜单、审计日志???邮件配置模型中文注释质量测试???
  - 禁止 `Table Name`、`Relation:`、`Fillable attributes`、`Attribute Casting` 及旧英文说明回流???

### 真实 DB 数据来源
- `Schema::getColumnListing('menus')` 返回字段：`id,title,title_en,icon,path,component,parent_id,permission_id,guard_type,type,is_visible,is_external,sort,status,created_at,updated_at,deleted_at`???
- `Schema::getColumnListing('operation_logs')` 返回字段：`id,admin_id,admin_name,target_user_id,order_no,content,ip,action_type,created_at,updated_at,deleted_at`???
- `Schema::getColumnListing('data_operation_logs')` 返回字段：`id,model_type,model_id,before_data,after_data,operator_id,created_at,updated_at,deleted_at`???
- `Schema::getColumnListing('mail_settings')` 返回字段：`id,driver,host,port,username,password,encryption,from_address,from_name,created_at,updated_at,deleted_at`???
- 当前真实数据量：
  - `menus`???0 条；当前无样例记录???
  - `operation_logs`???0 条；当前无样例记录???
  - `data_operation_logs`???0 条；当前无样例记录???
  - `mail_settings`???0 条；当前无样例记录???

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AdminMenuAuditConfigModelCommentReadabilityTest.php` 首次失败，提??? `Menu.php` 缺少 `menus 表保存前后台 Blade 页面可见的动态菜单配置`，且仍包??? `Table Name` 英文占位注释???
- GREEN：补??? `Menu`、`OperationLog`、`DataOperationLog`、`MailSetting` 四个模型注释后，专项测试通过???

### 验证记录
- `php -l app\Models\Menu.php`：???过???
- `php -l app\Models\OperationLog.php`：???过???
- `php -l app\Models\DataOperationLog.php`：???过???
- `php -l app\Models\MailSetting.php`：???过???
- `php -l tests\Feature\AdminMenuAuditConfigModelCommentReadabilityTest.php`：???过???
- `vendor\bin\phpunit tests\Feature\AdminMenuAuditConfigModelCommentReadabilityTest.php`：???过???2 tests / 68 assertions???
- `vendor\bin\phpunit tests\Feature\AdminRolePermissionModelReadabilityTest.php`：???过???1 test / 33 assertions???
- `vendor\bin\phpunit tests\Feature\DefaultAdminAndFrontMenuRoleMigrationTest.php`：???过???2 tests / 57 assertions???
- `vendor\bin\phpunit tests\Feature\AdminPermissionNameReferenceDocumentationTest.php`：???过???1 test / 810 assertions???

### 本轮边界
- 本轮只修改模型注释和新增注释质量测试，没有改变菜单树加载、权限绑定???日志关联???邮件配置读写或数据库结构???
- `menus` 当前真实表为空，后台实际授权菜单仍以 `permissions` / `role_permissions` 链路为主；本轮仅补齐历史 `Menu` 模型说明，避免后续维护误用???
## 141. 2026-06-09 代理层级、代理节点统计与代理等级模型中文注释补齐

### 本次处理目标
- 继续执行“所有模块文件及参数必须有详细中文注释和逻辑注释”的要求???
- 优先清理与后台数据范围???前台代理客户列表???注册代理关系和返佣配置相关的代理层级模型???
- 保持业务行为不变，仅补充真实表职责???字段含义???查询作用域参数和当前数据库边界说明???

### 修改文件
- `app/Models/AgentDescendant.php`
  - 补充 `agent_descendants` 表保存代理与下级代理或客户之间层级闭包关系的职责说明???
  - 补充 `agent_id`、`descendant_id`、`descendant_type`、`is_direct`、`depth` 的中文含义???
  - 补充 `agent()`、`descendant()` 关联外键和目标键说明???
  - 补充 `scopeDirectAgents()`、`scopeAllAgents()`、`scopeDirectCustomers()`、`scopeAllCustomers()` ??? `$query` ??? `$agentId` 的参数含义???
- `app/Models/AgentNodeStats.php`
  - 补充“代理节点统计模型???说明，明确 `agent_node_stats` 用于保存代理节点统计快照???
  - 明确当前数据库未??? `agent_node_stats` 表时不得在业务查询中直接依赖该模型，应继续以 `agent_descendants` 实时关系表为准???
  - 补充 `agent_id`、`direct_agent_count`、`indirect_agent_count`、`direct_customer_count`、`indirect_customer_count`、`last_calculated_at` 的中文含义???
- `app/Models/AgentLevel.php`
  - 补充 `agent_levels` 表保存代理等级与返佣比例配置的职责说明???
  - 补充 `level_code`、`name`、`max_commission`、`min_commission`、`user_commission` 的中文含义???
- `tests/Feature/AgentHierarchyModelCommentReadabilityTest.php`
  - 新增代理层级相关模型中文注释质量测试???
  - 禁止 `Table Name`、`Relation:`、`Scope:`、`Attribute Casting` 和旧英文说明回流???

### 真实 DB 数据来源
- `Schema::getColumnListing('agent_descendants')` 返回字段：`id,agent_id,descendant_id,descendant_type,is_direct,depth,created_at,updated_at,deleted_at`???
- `Schema::getColumnListing('agent_levels')` 返回字段：`id,level_code,name,max_commission,min_commission,user_commission,created_at,updated_at,deleted_at`???
- `Schema::hasTable('agent_node_stats')` 返回 `false`，说明当前数据库尚未创建代理节点统计表???
- 当前真实数据量：
  - `agent_descendants`???85 条；样例 `id=1,agent_id=620001,descendant_id=620101,descendant_type=2,is_direct=1,depth=1`???
  - `agent_levels`???5 条；样例 `id=1,level_code=1,name=???级代???,max_commission=85,min_commission=85,user_commission=0`???
  - `agent_node_stats`：当前未建表，无样例记录???

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AgentHierarchyModelCommentReadabilityTest.php` 首次失败，提??? `AgentDescendant.php` 缺少 `agent_descendants 表保存代理与下级代理或客户之间的层级闭包关系`，且仍包??? `Table Name` 英文占位注释???
- GREEN：补齐三个代理层级模型中文???辑注释并保留原查询逻辑后，专项测试通过???

### 验证记录
- `php -l app\Models\AgentDescendant.php`：???过???
- `php -l app\Models\AgentNodeStats.php`：???过???
- `php -l app\Models\AgentLevel.php`：???过???
- `php -l tests\Feature\AgentHierarchyModelCommentReadabilityTest.php`：???过???
- `vendor\bin\phpunit tests\Feature\AgentHierarchyModelCommentReadabilityTest.php`：???过???2 tests / 46 assertions???
- `vendor\bin\phpunit tests\Feature\AdminDataScopeServiceTest.php`：???过???4 tests / 6 assertions???
- `vendor\bin\phpunit tests\Feature\AgentLevelControllerCommentReadabilityTest.php`：???过???1 test / 14 assertions???
- `vendor\bin\phpunit tests\Feature\UserRegistrationServiceLocalizationTest.php`：???过???2 tests / 14 assertions???
- `vendor\bin\phpunit tests\Feature\DefaultAdminAndFrontMenuRoleMigrationTest.php`：???过???2 tests / 57 assertions???
- `rg "Table Name|Relation:|Scope:|Attribute Casting|Maintains hierarchical relationships|Stores statistical data|Defines different agent levels" app\Models\AgentDescendant.php app\Models\AgentNodeStats.php app\Models\AgentLevel.php`：无命中???

### 本轮边界
- 本轮只修改代理层级相关模型注释和新增注释质量测试，没有改变代理树查询、后台数据范围过滤???前台代理客户列表???注册关系写入???返佣计算或数据库结构???
- `agent_node_stats` 当前是未建表的统计预留模型，后续若要启用必须先补迁移、数据生成任务和回归测试???
## 142. 2026-06-09 返佣记录、组配置与支付???道模型中文注释补齐

### 本次处理目标
- 继续执行“所有模块文件及参数必须有详细中文注释和逻辑注释”的要求???
- 清理返佣、交易组配置和支付???道模型中的旧英文占位注释与历史编码残留???
- 使用真实数据库字段和样例数据作为注释依据，确保资金配置相关字段说明可维护???

### 修改文件
- `app/Models/CommissionRecord.php`
  - 补充 `commission_records` 表保存代理返佣结算和人工调整记录的职责说明???
  - 补充 `unique_id`、`agent_id`、`parent_id`、`agent_profit`、`agent_volume`、`equity_value`、`equity_diff`、`settle_cycle`、`mt4_order_id`、`date_range`、`settle_status`、`fee`、`swap`、`commission_amount`、`returned_amount`、`deposit`、`real_amount`、`data_type`、`manual_reason`、`remarks`、`created_by`、`updated_by` 的中文含义???
  - 补充 `agent()` ??? `parent()` 关联外键和目标键说明???
- `app/Models/GroupConfig.php`
  - 补充 `group_configs` 表保存代理组和客户交易组配置的职责说明???
  - 补充 `pair_id`、`name`、`radix`、`category`、`has_commission`、`is_enabled`、`is_ecn`、`is_default`、`created_by`、`updated_by` 的中文含义???
  - 补充 `pairedGroup()` 关联说明，以??? `scopeAgent()`、`scopeUser()`、`scopeEnabled()`、`scopeDefault()` ??? `$query` 参数含义???
- `app/Models/PaymentChannel.php`
  - 补充 `payment_channels` 表保存后台可用支付???道配置的职责说明???
  - 补充 `name`、`channel_code`、`exchange_rate`、`is_enabled`、`sort`、`config` 的中文含义???
  - 补充 `$casts` ??? `config` JSON 自动转数组的说明，以??? `scopeEnabled()` ??? `$query` 参数含义???
- `tests/Feature/AdminFinanceConfigModelCommentReadabilityTest.php`
  - 新增返佣、组配置、支付???道模型中文注释质量测试???
  - 禁止 `Table Name`、`Relation:`、`Scope:`、`Attribute Casting` 和旧英文说明回流???

### 真实 DB 数据来源
- `Schema::getColumnListing('commission_records')` 返回字段：`id,unique_id,agent_id,parent_id,agent_profit,agent_volume,equity_value,equity_diff,settle_cycle,mt4_order_id,date_range,settle_status,fee,swap,commission_amount,returned_amount,deposit,real_amount,data_type,manual_reason,remarks,created_by,updated_by,created_at,updated_at,deleted_at`???
- `Schema::getColumnListing('group_configs')` 返回字段：`id,pair_id,name,radix,category,has_commission,is_enabled,is_ecn,is_default,created_by,updated_by,created_at,updated_at,deleted_at`???
- `Schema::getColumnListing('payment_channels')` 返回字段：`id,name,channel_code,exchange_rate,is_enabled,sort,config,created_at,updated_at,deleted_at`???
- 当前真实数据量：
  - `commission_records`???4 条；样例 `id=17,agent_id=1001,parent_id=0,commission_amount=880.55,settle_status=2`???
  - `group_configs`???15 条；样例 `id=1,pair_id=null,name=Agent Standard,category=1,is_enabled=1,is_default=1`???
  - `payment_channels`???3 条；样例 `id=1,name=Bank Transfer,channel_code=bank_transfer,exchange_rate=7.1200,is_enabled=1`???

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AdminFinanceConfigModelCommentReadabilityTest.php` 首次失败，提??? `CommissionRecord.php` 缺少 `commission_records 表保存代理返佣结算和人工调整记录`，且仍包??? `Table Name` 英文占位注释???
- GREEN：补齐三个模型中文???辑注释并保留原模型行为后，专项测试通过???

### 验证记录
- `php -l app\Models\CommissionRecord.php`：???过???
- `php -l app\Models\GroupConfig.php`：???过???
- `php -l app\Models\PaymentChannel.php`：???过???
- `php -l tests\Feature\AdminFinanceConfigModelCommentReadabilityTest.php`：???过???
- `vendor\bin\phpunit tests\Feature\AdminFinanceConfigModelCommentReadabilityTest.php`：???过???2 tests / 51 assertions???
- `vendor\bin\phpunit tests\Feature\AdminCommissionsCommentReadabilityTest.php`：???过???2 tests / 40 assertions???
- `vendor\bin\phpunit tests\Feature\AdminGroupConfigsCommentReadabilityTest.php`：???过???2 tests / 59 assertions???
- `vendor\bin\phpunit tests\Feature\AdminPaymentChannelControllerCommentReadabilityTest.php`：???过???1 test / 34 assertions???
- `vendor\bin\phpunit tests\Feature\PaymentChannelControllerCommentReadabilityTest.php`：???过???1 test / 15 assertions???
- `rg "Table Name|Relation:|Scope:|Attribute Casting|Records details of commissions|Stores configuration parameters|Manages available payment channels" app\Models\CommissionRecord.php app\Models\GroupConfig.php app\Models\PaymentChannel.php`：无命中???

### 本轮边界
- 本轮只修改返佣???组配置、支付???道模型注释和新增注释质量测试，没有改变返佣结算、交易组筛??????支付???道启用筛??????JSON cast 或数据库结构???
- 三个模型文件被重写为干净 UTF-8 注释版本，原有类名???表名???关联关系和 scope 查询行为保持???鑷淬??
## 143. 2026-06-09 新闻公告、实名认证???收货地???与礼品发货模型中文注释补???

### 本次处理目标
- 继续执行“所有模块文件及参数必须有详细中文注释和逻辑注释”的要求???
- 清理公告、实名认证???收货地???和礼品发货模型中的旧英文占位注释与历史编码残留???
- 使用真实数据库字段和样例数据作为注释依据，确保前后台页面展示字段含义可维护???

### 修改文件
- `app/Models/News.php`
  - 补充 `news` 表保存后台发布新闻公告内容的职责说明???
  - 补充 `title`、`content`、`image`、`author_id`、`author_name`、`is_published` 的中文含义???
  - 补充 `scopePublished()` ??? `$query` 参数含义??? `is_published=1` 筛??????辑???
- `app/Models/UserAuth.php`
  - 补充 `user_auths` 表保存前台用户实名和银行卡认证资料的职责说明???
  - 补充 `user_id`、`bank_no`、`bank_no_tmp`、`bank_name`、`bank_name_tmp`、`bank_addr`、`bank_addr_tmp`、`bank_card_img`、`bank_card_back_img`、`bank_status`、`bank_remarks`、`id_card_no`、`id_card_front`、`id_card_back`、`id_card_status`、`id_card_remarks`、`is_bank_synced` 的中文含义???
  - 补充 `$fillable` 的兼容边界：旧字段保留给旧表单和旧接口兼容，调用方仍应按真实表结构过滤后写入???
  - 补充 `userInfo()` 关联外键和目标键说明???
- `app/Models/UserAddress.php`
  - 补充 `user_addresses` 表保存前台用户礼品收货地???的职责说明???
  - 补充 `user_id`、`recipient_name`、`recipient_phone`、`recipient_address`、`is_default` 的中文含义???
  - 补充 `user()` 关联外键和目标键说明???
- `app/Models/GiftShipment.php`
  - 补充 `gift_shipments` 表保存礼品兑换发货和物流记录的职责说明???
  - 补充 `user_id`、`address_id`、`recipient_name`、`recipient_phone`、`recipient_address`、`sender_name`、`tracking_number`、`gift_name`、`gift_quantity`、`status`、`remark`、`admin_id`、`shipped_at` 的中文含义???
  - 补充 `user()` 关联外键和目标键说明???
- `tests/Feature/AdminContentAuthGiftModelCommentReadabilityTest.php`
  - 新增公告、实名认证???收货地???和礼品发货模型中文注释质量测试???
  - 禁止 `Table Name`、`Relation:`、`Scope:`、`mass assignable` 和旧英文说明回流???

### 真实 DB 数据来源
- `Schema::getColumnListing('news')` 返回字段：`id,title,content,image,author_id,author_name,is_published,created_at,updated_at,deleted_at`???
- `Schema::getColumnListing('user_auths')` 返回字段：`id,user_id,bank_no,bank_no_tmp,bank_name,bank_name_tmp,bank_card_img,bank_card_back_img,bank_card_img_tmp,bank_card_back_img_tmp,bank_addr,bank_addr_tmp,bank_status,bank_remarks,id_card_no,id_card_status,id_card_front,id_card_back,id_card_remarks,is_bank_synced,created_at,updated_at,deleted_at`???
- `Schema::getColumnListing('user_addresses')` 返回字段：`id,user_id,recipient_name,recipient_phone,recipient_address,is_default,created_at,updated_at,deleted_at`???
- `Schema::getColumnListing('gift_shipments')` 返回字段：`id,user_id,address_id,recipient_name,recipient_phone,recipient_address,sender_name,tracking_number,gift_name,gift_quantity,status,remark,admin_id,shipped_at,created_at,updated_at,deleted_at`???
- 当前真实数据量：
  - `news`???2 条；样例 `id=1,title=Codex Runtime News Check,author_id=0,is_published=1`???
  - `user_auths`???9 条；样例 `id=1,user_id=1001,bank_status=2,id_card_status=2,is_bank_synced=0`???
  - `user_addresses`???1 条；样例 `id=5,user_id=1001,recipient_name=Demo Root Agent,is_default=1`???
  - `gift_shipments`???1 条；样例 `id=5,user_id=1001,address_id=5,gift_name=VIP Gift Box,status=2`???

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AdminContentAuthGiftModelCommentReadabilityTest.php` 首次失败，提??? `News.php` 缺少“新闻公告模型???，且仍包含 `Table Name` 英文占位注释???
- GREEN：补齐四个模型中文???辑注释并保留原模型行为后，专项测试通过???

### 验证记录
- `php -l app\Models\News.php`：???过???
- `php -l app\Models\UserAuth.php`：???过???
- `php -l app\Models\UserAddress.php`：???过???
- `php -l app\Models\GiftShipment.php`：???过???
- `php -l tests\Feature\AdminContentAuthGiftModelCommentReadabilityTest.php`：???过???
- `vendor\bin\phpunit tests\Feature\AdminContentAuthGiftModelCommentReadabilityTest.php`：???过???2 tests / 69 assertions???
- `vendor\bin\phpunit tests\Feature\AdminNewsCommentReadabilityTest.php`：???过???2 tests / 55 assertions???
- `vendor\bin\phpunit tests\Feature\AdminAuthenticationModuleTest.php`：???过???5 tests / 30 assertions???
- `vendor\bin\phpunit tests\Feature\AdminGiftModuleTest.php`：???过???5 tests / 30 assertions???
- `vendor\bin\phpunit tests\Feature\FrontNewsControllerCommentReadabilityTest.php tests\Feature\FrontGiftControllerCommentReadabilityTest.php`：???过???2 tests / 20 assertions???
- `rg "Table Name|Relation:|Scope:|mass assignable|Manages news and announcements|Manages user|shipping address information|shipping process and logistics" app\Models\News.php app\Models\UserAuth.php app\Models\UserAddress.php app\Models\GiftShipment.php`：无命中???

### 本轮边界
- 本轮只修改公告???认证???地???、礼品发货模型注释和新增注释质量测试，没有改变公告发布筛选???实名认证字段白名单、用户地???关联、礼品发货关联或数据库结构???
- `UserAuth::$fillable` 保留旧项目兼容字段；这些字段不全部代表当??? `user_auths` 表真实字段，实际写入仍应由控制器或服务层按真实表结构过滤???
## 144. 2026-06-09 注销申请、黑名单、大代理登录日志??? ID 序列模型中文注释补齐

### 本次处理目标
- 继续执行“所有模块文件及参数必须有详细中文注释和逻辑注释”的要求???
- 清理注销申请、黑名单、大代理登录日志??? ID 序列模型中的旧英文占位注释与历史编码残留???
- 使用真实数据库字段和样例数据作为注释依据，确保安全风控???账号注???、登录审计和编号生成逻辑可维护???

### 修改文件
- `app/Models/CancelApply.php`
  - 补充 `cancel_applies` 表保存前台用户提交账号注???申请的职责说明???
  - 补充 `user_id`、`user_name`、`status`、`cancel_remark`、`reject_reason`、`created_by`、`updated_by` 的中文含义???
  - 补充 `user()` 关联外键和目标键说明???
- `app/Models/Blacklist.php`
  - 补充 `blacklists` 表保存被限制注册或操作的用户身份信息的职责说明???
  - 补充 `name`、`id_card`、`email`、`phone` 的中文含义???
- `app/Models/BigAgentLoginLog.php`
  - 补充 `big_agent_login_logs` 表保存大代理账号登录审计记录的职责说明???
  - 补充 `big_agent_id`、`login_ip`、`login_at` 的中文含义???
  - 补充 `bigAgent()` 关联外键说明???
- `app/Models/IdSequence.php`
  - 补充 `id_sequences` 表保存业务用户编号生成状态的职责说明???
  - 补充 `type`、`current_value`、`prefix`、`step` 的中文含义???
  - 补充 `nextId(string $type)` 的参数含义???返回???含义和 `lockForUpdate()` 并发锁定目的???
- `tests/Feature/AdminSecuritySequenceModelCommentReadabilityTest.php`
  - 新增注销申请、黑名单、大代理登录日志??? ID 序列模型中文注释质量测试???
  - 禁止 `Table Name`、`Relation:`、`Handles account cancellation`、`Manages blocked users`、`Records login history`、`Used for generating unique ID sequences`、`Initialize if not exists` 等旧说明回流???

### 真实 DB 数据来源
- `Schema::getColumnListing('cancel_applies')` 返回字段：`id,user_id,user_name,status,cancel_remark,reject_reason,created_by,updated_by,created_at,updated_at,deleted_at`???
- `Schema::getColumnListing('blacklists')` 返回字段：`id,name,id_card,email,phone,created_at,updated_at,deleted_at`???
- `Schema::getColumnListing('big_agent_login_logs')` 返回字段：`id,big_agent_id,login_ip,login_at,created_at,updated_at,deleted_at`???
- `Schema::getColumnListing('id_sequences')` 返回字段：`id,type,current_value,prefix,step,created_at,updated_at,deleted_at`???
- 当前真实数据量：
  - `cancel_applies`???0 条；当前无样例记录???
  - `blacklists`???0 条；当前无样例记录???
  - `big_agent_login_logs`???0 条；当前无样例记录???
  - `id_sequences`???1 条；样例 `id=1,type=agent,current_value=1001,prefix=,step=1`???

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AdminSecuritySequenceModelCommentReadabilityTest.php` 首次失败，提??? `CancelApply.php` 缺少 `cancel_applies 表保存前台用户提交的账号注销申请`，且仍包??? `Table Name` 英文占位注释???
- GREEN：补齐四个模型中文???辑注释并保留原模型行为后，专项测试通过???

### 验证记录
- `php -l app\Models\CancelApply.php`：???过???
- `php -l app\Models\Blacklist.php`：???过???
- `php -l app\Models\BigAgentLoginLog.php`：???过???
- `php -l app\Models\IdSequence.php`：???过???
- `php -l tests\Feature\AdminSecuritySequenceModelCommentReadabilityTest.php`：???过???
- `vendor\bin\phpunit tests\Feature\AdminSecuritySequenceModelCommentReadabilityTest.php`：???过???2 tests / 65 assertions???
- `vendor\bin\phpunit tests\Feature\AdminCancelAppliesCommentReadabilityTest.php`：???过???2 tests / 37 assertions???
- `vendor\bin\phpunit tests\Feature\AdminBlacklistCommentReadabilityTest.php`：???过???2 tests / 41 assertions???
- `vendor\bin\phpunit tests\Feature\AdminBigAgentsCommentReadabilityTest.php`：???过???2 tests / 52 assertions???
- `vendor\bin\phpunit tests\Feature\UserRegistrationServiceLocalizationTest.php`：???过???2 tests / 14 assertions???
- `rg "Table Name|Relation:|Handles account cancellation|Manages blocked users|Records login history|Used for generating unique ID sequences|Initialize if not exists" app\Models\CancelApply.php app\Models\Blacklist.php app\Models\BigAgentLoginLog.php app\Models\IdSequence.php`：无命中???

### 本轮边界
- 本轮只修改注???申请、黑名单、大代理登录日志??? ID 序列模型注释，并新增注释质量测试，没有改变注???审核、黑名单风控、大代理登录审计、注册编号生成或数据库结构???
- `IdSequence::nextId()` 的事务???`lockForUpdate()` 行锁、起始???和递增逻辑保持原样，仅将并发编号生成意图写成中文注释???
## 145. 2026-06-09 交易订单、余额信用清零???转组申请与品种行情模型中文注释补齐

### 本次处理目标
- 继续执行“所有模块文件及参数必须有详细中文注释和逻辑注释”的要求???
- 清理交易订单、余额信用清零???转组申请日志和交易品种价格模型中的旧英文占位注释与历史编码残留???
- 使用真实数据库字段和样例数据作为注释依据，确保后台交易风控???持???/平仓、清零???转组审核和行情展示逻辑可维护???

### 修改文件
- `app/Models/UserTrade.php`
  - 补充 `user_trades` 表保存用??? MT4 交易订单数据的职责说明???
  - 补充 `user_id`、`ticket`、`symbol`、`digits`、`cmd`、`volume`、`open_time`、`open_price`、`stop_loss`、`take_profit`、`close_time`、`close_price`、`profit`、`commission`、`commission_agent`、`swaps`、`settlement_status`、`settled_at`、`comment`、`internal_id`、`magic` 等字段含义???
  - 补充 `user()` 关联外键和目标键说明???
  - 补充 `scopeOpen()`、`scopeClosed()` ??? `$query` 参数含义，以及旧 MT4 未平仓订??? `close_time=1970-01-01 00:00:00` 的业务规则???
- `app/Models/WhsExpZero.php`
  - 补充 `whs_exp_zeros` 表保存用户余额或信用额度清零操作记录的职责说明???
  - 补充 `user_id`、`user_name`、`balance`、`credit`、`status`、`md5_key`、`created_by`、`updated_by` 的中文含义???
  - 补充 `user()` 关联外键和目标键说明???
- `app/Models/TransApplyLog.php`
  - 补充 `trans_apply_logs` 表保存用户申请变更交易组审核记录的职责说明???
  - 补充 `user_id`、`origin_group_id`、`group_id`、`group_name`、`applicant_id`、`applicant_name`、`status`、`apply_reason`、`reject_reason`、`created_by`、`updated_by` 的中文含义???
  - 补充 `user()` 关联外键和目标键说明???
- `app/Models/SymbolPrice.php`
  - 补充 `symbol_prices` 表保存交易品种实时或历史报价的职责说明???
  - 补充 `symbol`、`time`、`bid`、`ask`、`low`、`high`、`direction`、`digits`、`spread`、`group_id`、`status`、`modify_time` 的中文含义???
- `tests/Feature/AdminTradingModelCommentReadabilityTest.php`
  - 新增交易订单、清零???转组和行情模型中文注释质量测试???
  - 禁止 `Table Name`、`Relation:`、`Records user`、`Records the operation`、`Records the history`、`Stores real-time` 等旧说明回流???

### 真实 DB 数据来源
- `Schema::getColumnListing('user_trades')` 返回字段：`id,user_id,ticket,symbol,digits,cmd,volume,open_time,open_price,stop_loss,take_profit,close_time,expiration,reason,conv_rate1,conv_rate2,commission,commission_agent,swaps,close_price,profit,taxes,comment,internal_id,margin_rate,timestamp_val,magic,gw_volume,gw_open_price,gw_close_price,modify_time,settlement_status,settled_at,created_at,updated_at,deleted_at`???
- `Schema::getColumnListing('whs_exp_zeros')` 返回字段：`id,user_id,user_name,balance,credit,status,md5_key,created_by,updated_by,created_at,updated_at,deleted_at`???
- `Schema::getColumnListing('trans_apply_logs')` 返回字段：`id,user_id,origin_group_id,group_id,group_name,applicant_id,applicant_name,status,apply_reason,reject_reason,created_by,updated_by,created_at,updated_at,deleted_at`???
- `Schema::getColumnListing('symbol_prices')` 返回字段：`id,symbol,time,bid,ask,low,high,direction,digits,spread,group_id,status,modify_time,created_at,updated_at,deleted_at`???
- 当前真实数据量：
  - `user_trades`???36 条；样例 `id=288,user_id=600106,ticket=900135,symbol=XAUUSD.G,close_time=2026-05-22 10:36:33,settlement_status=1`???
  - `whs_exp_zeros`???0 条；当前无样例记录???
  - `trans_apply_logs`???1 条；样例 `id=8,user_id=600103,origin_group_id=2,group_id=3,status=0`???
  - `symbol_prices`???8 条；样例 `id=1,symbol=AUDJPY.G,bid=100,ask=100.25,spread=25,status=1`???

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AdminTradingModelCommentReadabilityTest.php` 首次失败，提??? `UserTrade.php` 缺少 `user_trades 表保存用??? MT4 交易订单数据`，且仍包??? `Table Name` 英文占位注释???
- GREEN：补齐四个模型中文???辑注释并保留原模型行为后，专项测试通过???
- 测试调整：初版禁止词 `Model` 过宽，误伤正??? `BaseModel` 继承；已收窄为旧英文说明片段???

### 验证记录
- `php -l app\Models\UserTrade.php`：???过???
- `php -l app\Models\WhsExpZero.php`：???过???
- `php -l app\Models\TransApplyLog.php`：???过???
- `php -l app\Models\SymbolPrice.php`：???过???
- `php -l tests\Feature\AdminTradingModelCommentReadabilityTest.php`：???过???
- `vendor\bin\phpunit tests\Feature\AdminTradingModelCommentReadabilityTest.php`：???过???2 tests / 67 assertions???
- `vendor\bin\phpunit tests\Feature\AdminRiskMt4ModuleTest.php`：???过???4 tests / 31 assertions???
- `vendor\bin\phpunit tests\Feature\FrontPositionControllerCommentReadabilityTest.php`：???过???2 tests / 40 assertions???
- `vendor\bin\phpunit tests\Feature\FrontTradeSymbolControllerCommentReadabilityTest.php`：???过???2 tests / 19 assertions???
- `vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php`：???过???3 tests / 1521 assertions???
- `rg "Table Name|Relation:|Records user|Records the operation|Records the history|Stores real-time" app\Models\UserTrade.php app\Models\WhsExpZero.php app\Models\TransApplyLog.php app\Models\SymbolPrice.php`：无命中???

### 本轮边界
- 本轮只修改交易订单???余额信用清零???转组申请日志和交易品种价格模型注释，并新增注释质量测试，没有改变交易订单开平仓判断、清零处理???转组审核???行情读取或数据库结构???
- `scopeOpen()` ??? `scopeClosed()` 仍然沿用??? MT4 `close_time=1970-01-01 00:00:00` 的未平仓判定规则???
## 146. 2026-06-09 系统配置、点差配置???历史用户组、认证备份与凭证模型中文注释补齐

### 本次处理目标
- 继续执行“所有模块文件及参数必须有详细中文注释和逻辑注释”的要求???
- 清理系统配置、点差配置???历史用户组、用户认证备份和凭证模型中的旧英文占位注释与历史编码残留???
- 明确 `user_groups` ??? `user_auth_info` 当前未建表的兼容边界，避免后续业务误直接依赖历史模型???

### 修改文件
- `app/Models/SystemConfig.php`
  - 补充 `system_configs` 表保存后台全???配置项的职责说明???
  - 补充 `key`、`value`、`group`、`description` 字段含义???
  - 补充 `getVal($key, $default)` ??? `$key`、`$default` 参数含义和返回???说明???
- `app/Models/SpreadConfig.php`
  - 补充 `spread_configs` 表保存交易产品或代理组点差配置的职责说明???
  - 补充 `spread`、`agent_group_id`、`spread_ratio`、`status` 字段含义???
- `app/Models/UserGroup.php`
  - 改为“用户组兼容模型”说明???
  - 明确当前数据库未??? `user_groups` 表时不得在业务查询中直接依赖该模型，应优先使??? `group_configs`???
- `app/Models/UserAuthInfo.php`
  - 改为“用户认证信息备份模型???说明???
  - 明确当前数据库未??? `user_auth_info` 表时不得在业务查询中直接依赖该模型，应以 `user_auths` 作为真实认证数据源???
- `app/Models/VoucherInfo.php`
  - 补充 `voucher_infos` 表保存前台用户上传入金或审核凭证的职责说明???
  - 补充 `user_id`、`images`、`remarks`、`review_status`、`review_message`、`created_by`、`updated_by` 字段含义???
  - 补充 `user()` 关联外键和目标键说明???
- `tests/Feature/AdminConfigVoucherModelCommentReadabilityTest.php`
  - 新增系统配置、点差配置???历史用户组、认证备份和凭证模型中文注释质量测试???
- `tests/Feature/VoucherInfoCommentReadabilityTest.php`
  - 从旧乱码断言升级为真正的 UTF-8 可读中文断言???
  - 禁止 `Voucher Info Model`、`数据`、`关联`、`凭证` 等旧片段回流???

### 真实 DB 数据来源
- `Schema::getColumnListing('system_configs')` 返回字段：`id,key,value,group,description,created_at,updated_at,deleted_at`???
- `Schema::getColumnListing('spread_configs')` 返回字段：`id,spread,agent_group_id,spread_ratio,status,created_at,updated_at,deleted_at`???
- `Schema::getColumnListing('voucher_infos')` 返回字段：`id,user_id,images,remarks,review_status,review_message,created_by,updated_by,created_at,updated_at,deleted_at`???
- `Schema::hasTable('user_groups')` 返回 `false`???
- `Schema::hasTable('user_auth_info')` 返回 `false`???
- 当前真实数据量：
  - `system_configs`???41 条；样例 `id=1,key=unit_test_single_config,value=old,group=general`???
  - `spread_configs`???0 条；当前无样例记录???
  - `voucher_infos`???1 条；样例 `id=10,user_id=1001,review_status=1,review_message=已经处理到账了`???
  - `user_groups`：当前未建表???
  - `user_auth_info`：当前未建表???

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AdminConfigVoucherModelCommentReadabilityTest.php tests\Feature\VoucherInfoCommentReadabilityTest.php` 首次失败，提??? `SystemConfig.php` 缺少 `system_configs 表保存后台全???配置项`，且仍包??? `Table Name` 英文占位注释???
- GREEN：补齐五个模型中文???辑注释，并升级 VoucherInfo 旧测试断???后，专项测试通过???

### 验证记录
- `php -l app\Models\SystemConfig.php`：???过???
- `php -l app\Models\SpreadConfig.php`：???过???
- `php -l app\Models\UserGroup.php`：???过???
- `php -l app\Models\UserAuthInfo.php`：???过???
- `php -l app\Models\VoucherInfo.php`：???过???
- `php -l tests\Feature\AdminConfigVoucherModelCommentReadabilityTest.php`：???过???
- `php -l tests\Feature\VoucherInfoCommentReadabilityTest.php`：???过???
- `vendor\bin\phpunit tests\Feature\AdminConfigVoucherModelCommentReadabilityTest.php tests\Feature\VoucherInfoCommentReadabilityTest.php`：???过???2 tests / 81 assertions???
- `vendor\bin\phpunit tests\Feature\AdminSystemConfigUpdateControllerTest.php`：???过???2 tests / 4 assertions???
- `vendor\bin\phpunit tests\Feature\AdminExchangeRateModuleTest.php`：???过???5 tests / 23 assertions???
- `vendor\bin\phpunit tests\Feature\VoucherInfoCommentReadabilityTest.php tests\Feature\VoucherControllerCommentReadabilityTest.php tests\Feature\FrontVoucherControllerCommentReadabilityTest.php`：???过???2 tests / 18 assertions???
- `vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php`：???过???3 tests / 1521 assertions???
- `rg "Table Name|Get Config Value|Manages various global configuration|Manages spread configurations|Defines different user groups|Stores backups or historical records|Voucher Info Model|数据|关联|系统|点差|鍑???" app\Models\SystemConfig.php app\Models\SpreadConfig.php app\Models\UserGroup.php app\Models\UserAuthInfo.php app\Models\VoucherInfo.php`：无命中???

### 本轮边界
- 本轮只修改模型注释和注释质量测试，没有改变系统配置读取???汇率配置???点差配置???凭证审核???凭证上传或数据库结构???
- `UserGroup` ??? `UserAuthInfo` 当前是历史兼容模型，因真实数据库未建表，后续若要启用必须先补迁移、数据来源和回归测试???
## 147. 2026-06-09 后台权限名称、权限字符串与功能作??? MD 逐项注释补强

### 本次处理目标
- 响应“后台所有权限名称，??? MD 文件中必须有中文注释当前权限名称、对应字符串以及功能作用”的要求???
- 基于真实数据??? `permissions` 表中 `guard_type=admin` 的后台权限记录校验文档，不使用手写猜测清单???
- 增加逐行完整性测试，确保每条后台权限都在同一张表格行中同时包含权限名称???权限字符串、接口路由???页面路由???状态和功能作用???

### 修改文件
- `docs/admin-permission-name-reference.md`
  - 新增 `权限名称中文注释规则` 小节???
  - 明确每条后台权限必须独立成行，且 `权限名称`、`权限字符串`、`接口路由字符串`、`页面路由`、`功能作用` 必须逐项说明???
  - 明确无独立接口路由或页面路由时使??? `-`，避免维护???误以为空缺字段遗漏???
- `tests/Feature/AdminPermissionNameReferenceRowCompletenessTest.php`
  - 新增真实 DB 驱动的权限文档???行完整性测试???
  - 参数注释说明 `$documentPath`、`$document`、`$rows`、`$permission`、`$slug`、`$name` 的???辑含义及功能作用???
  - 校验每条后台权限必须拥有独立表格行，并在同一行内说明中文权限名称、`permissions.slug`、`permissions.api_route`、`permissions.route` 和功能作用???

### 真实 DB 数据来源
- 查询来源：`permissions` 表，筛???条??? `guard_type=admin`???
- 当前后台权限总数：`195`???
- 当前启用后台权限数量：`176`???
- 当前停用后台权限数量：`19`???
- 类型分布：菜单权??? `54` 条，其中启用 `35` 条???停??? `19` 条；按钮/接口权限 `141` 条，全部启用???
- 样例权限：`id=2,name=入金审核,slug=admin_deposit_approve_6a23fb27093ea,api_route=admin_api_depositApprove,type=3,status=1`???

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AdminPermissionNameReferenceRowCompletenessTest.php` 首次失败，提??? `docs/admin-permission-name-reference.md` 缺少 `## 权限名称中文注释规则`???
- GREEN：补充文档规则小节后，新增测试???过，证??? 195 条真实后台权限均??? MD 中拥有独立完整说明行???

### 验证记录
- `php -l tests\Feature\AdminPermissionNameReferenceRowCompletenessTest.php`：???过???
- `vendor\bin\phpunit tests\Feature\AdminPermissionNameReferenceRowCompletenessTest.php`：???过???1 test / 1757 assertions???
- `vendor\bin\phpunit tests\Feature\AdminPermissionNameReferenceDocumentationTest.php`：???过???1 test / 810 assertions???

### 本轮边界
- 本轮只补强后台权限说明文档与文档完整性测试，没有修改业务鉴权中间件???角色授权???辑、菜单渲染???辑或数据库权限数据???
- 当前文档覆盖范围为后??? `guard_type=admin` 权限；前台代理商和普通客户菜单权限仍由前台权限文???/菜单审计继续跟进???
## 148. 2026-06-09 基础模型、大代理、MT4 与批量信用导入模型中文注释补???

### 本次处理目标
- 继续执行“所有模块的文件及参数必须有详细中文注释和???辑注释包括参数的注释及功能作用”的要求???
- 清理 `BaseModel`、`BigAgent`、`Mt4User`、`Mt4Trade`、`CreditImport` 中残留的旧英文占位注释和历史编码乱码???
- 保持模型行为不变，只补充可读中文逻辑说明、字段含义???参数含义和关联关系边界???

### 修改文件
- `app/Models/BaseModel.php`
  - 改为干净 UTF-8 中文注释???
  - 补充基础模型职责：软删除、主键???批量赋值???序列化隐藏字段??? Unix 时间戳日期格式???
  - 补充 `$guarded`、`$hidden`、`$primaryKey`、`$dateFormat`、`serializeDate()` 的参数含义及功能作用???
- `app/Models/BigAgent.php`
  - 补充 `big_agents` 表职责说明???
  - 补充 `username`、`sub_agent_ids`、`is_enabled`、`jwt_token_id` 的业务含义???
  - 补充 `loginLogs()` ??? `big_agent_login_logs.big_agent_id` 的关联说明???
- `app/Models/Mt4User.php`
  - 补充 `mt4_users` 表保??? MT4 资金快照的职责说明???
  - 补充 `login`、`name`、`group`、`balance/equity/margin/margin_free`、`leverage` 字段含义???
  - 补充 `$casts` 的金额精度和整数字段转换说明???
- `app/Models/Mt4Trade.php`
  - 补充 `mt4_trades` 表保??? MT4 交易订单的职责说明???
  - 补充 `ticket`、`login`、`symbol`、`cmd`、`profit`、`commission`、`swaps`、`open_time`、`close_time` 字段含义???
  - 明确 `cmd=6` 表示余额类交易，`user()` 当前是历史兼容关系，严格归属判断应优先确??? `user_infos.mt4_code` 映射???
- `app/Models/CreditImport.php`
  - 补充 `credit_imports` 表保存后台批量信用额度导入记录的职责说明???
  - 补充 `user_id`、`user_name`、`credit_type`、`amount`、`batch_no`、`is_synced`、`fail_reason` 字段含义???
  - 补充 `user()` ??? `user_infos.user_id` 的关联说明???
- `tests/Feature/AdminCoreMt4ModelCommentReadabilityTest.php`
  - 新增基础模型、大代理、MT4 用户资金、MT4 交易记录和批量信用导入模型中文注释可读???测试???
  - 禁止 `Base Model Class`、`All business models extend this class`、`Table Name`、`Relation:` 以及常见 mojibake 片段回流???

### 真实 DB 数据来源
- `big_agents` 字段：`id,email,username,password,sub_agent_ids,is_enabled,jwt_token_id,created_by,created_at,updated_at,deleted_at`；当前真实记录数 `0`，暂无样例记录???
- `mt4_users` 字段：`id,login,name,group,balance,equity,margin,margin_free,leverage,created_at,updated_at,deleted_at`；当前真实记录数 `0`，暂无样例记录???
- `mt4_trades` 字段：`id,ticket,login,symbol,cmd,volume,open_price,close_price,commission,swaps,profit,open_time,close_time,created_at,updated_at`；当前真实记录数 `0`，暂无样例记录???
- `credit_imports` 字段：`id,user_id,user_name,credit_type,mt4_order_id,amount,batch_no,is_synced,fail_reason,remarks,created_by,updated_by,created_at,updated_at,deleted_at`；当前真实记录数 `0`，暂无样例记录???
- `BaseModel` 是基???父类，不对应单独数据表???

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AdminCoreMt4ModelCommentReadabilityTest.php` 首次失败，提??? `BaseModel.php` 缺少 `$guarded 表示批量赋???黑名单`，且仍包??? `Base Model Class` 英文占位说明???
- GREEN：重??? 5 个模型注释为可读中文并保留原行为后，专项测试通过???

### 验证记录
- `php -l app\Models\BaseModel.php`：???过???
- `php -l app\Models\BigAgent.php`：???过???
- `php -l app\Models\Mt4User.php`：???过???
- `php -l app\Models\Mt4Trade.php`：???过???
- `php -l app\Models\CreditImport.php`：???过???
- `php -l tests\Feature\AdminCoreMt4ModelCommentReadabilityTest.php`：???过???
- `vendor\bin\phpunit tests\Feature\AdminCoreMt4ModelCommentReadabilityTest.php`：???过???2 tests / 108 assertions???
- `vendor\bin\phpunit tests\Feature\AdminBatchCreditImportModuleTest.php`：???过???3 tests / 16 assertions???
- `vendor\bin\phpunit tests\Feature\AdminBatchCreditImportPermissionMigrationTest.php`：???过???1 test / 13 assertions???
- `vendor\bin\phpunit tests\Feature\AdminBigAgentsCommentReadabilityTest.php`：???过???2 tests / 52 assertions???
- `vendor\bin\phpunit tests\Feature\AdminRiskMt4ModuleTest.php`：???过???4 tests / 31 assertions???
- `vendor\bin\phpunit tests\Feature\AdminChineseCommentReadabilityTest.php`：???过???3 tests / 1521 assertions???
- `rg "Base Model Class|All business models extend this class|Use \$guarded blacklist|Fields hidden by default|Primary key column name|Timestamp storage format|Serialize dates to ISO format|Table Name|Relation:|数据|关联|用户|信用|大代|交易|妯??????" app\Models\BaseModel.php app\Models\BigAgent.php app\Models\Mt4User.php app\Models\Mt4Trade.php app\Models\CreditImport.php`：无命中???

### 本轮边界
- 本轮只修改模型注释和新增注释质量测试，没有改变表名???字段白名单、类型转换???软删除、日期序列化、模型关联或后台业务逻辑???
- 四张业务表当前真实数据均??? 0 条，因此清单记录真实表结构与当前无样例状态；后续若导入真实数据，可再补充样例记录???
## 149. 2026-06-09 后台数据范围模型与迁移中文注释补???

### 本次处理目标
- 继续执行“后台不同管理员角色拥有不同菜单权限和数据查看权限???的目标中数据查看权限部分???
- 清理 `RoleDataScope`、`AdminAgentBinding` 以及对应建表迁移中的历史编码乱码???
- 明确 `role_data_scopes` ??? `admin_agent_bindings` 是后台数据范围从数据表配置得到的核心来源???

### 修改文件
- `app/Models/RoleDataScope.php`
  - 补充 `role_data_scopes` 表保存角色级数据查看范围配置的职责说明???
  - 补充 `role_id`、`scope_type`、`agent_ids`、`user_ids`、`status` 字段的业务含义???
  - 补充 `$casts` 中数组字段和整数字段的转换目的???
  - 补充 `role()` ??? `roles.id` 的关联说明???
- `app/Models/AdminAgentBinding.php`
  - 补充 `admin_agent_bindings` 表保存后台管理员与代理节点绑定关系的职责说明???
  - 补充 `admin_id`、`agent_id`、`binding_type`、`status` 字段的业务含义???
  - 补充 `admin()` ??? `agent()` 关系说明，明??? `agent_id` 对应 `user_infos.user_id`???
- `database/migrations/2026_06_06_000001_create_role_data_scopes_table.php`
  - 重写为可读中文注释，说明 RBAC 控制接口访问，本表控制进入接口后的可见数据范围???
  - 补充 `scope_type` 枚举含义：`all=全部数据`、`self=本人数据`、`created=本人创建`、`agent_tree=绑定代理树`、`custom_agents=指定代理集合`、`custom_users=指定用户集合`???
  - 明确 `role_id` 唯一约束保证每个角色???多只有一条数据范围配置来源???
- `database/migrations/2026_06_06_000002_create_admin_agent_bindings_table.php`
  - 重写为可读中文注释，说明管理员到代理节点的绑定关系用??? `agent_tree` ??? `custom_agents` 数据范围???
  - 补充 `binding_type` 枚举含义：`primary=主绑定`、`extra=额外绑定`???
- `tests/Feature/AdminDataScopeSchemaCommentReadabilityTest.php`
  - 新增数据范围模型和迁移中文注释可读???测试???
  - 禁止 `数据`、`关联`、`角色`、`管理`、`代理`、`用户` 等历史乱码片段回流???

### 真实 DB 数据来源
- `role_data_scopes` 当前真实记录数：`100`???
- `role_data_scopes` 样例：`id=1,role_id=7,scope_type=custom_users,agent_ids=null,user_ids=[610001],status=1`???
- `admin_agent_bindings` 当前真实记录数：`58`???
- `admin_agent_bindings` 样例：`id=1,admin_id=5,agent_id=620001,binding_type=primary,status=1`???
- 说明：记录数来自本轮验证后的当前 DB 状???，数据范围测试会写入测试记录，因此以最终取样为准???

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AdminDataScopeSchemaCommentReadabilityTest.php` 首次失败，提??? `RoleDataScope.php` 缺少 `role_data_scopes 表保存角色级数据查看范围配置`???
- GREEN：补齐两个模型和两份迁移的中文???辑注释后，专项测试通过???

### 验证记录
- `php -l app\Models\RoleDataScope.php`：???过???
- `php -l app\Models\AdminAgentBinding.php`：???过???
- `php -l database\migrations\2026_06_06_000001_create_role_data_scopes_table.php`：???过???
- `php -l database\migrations\2026_06_06_000002_create_admin_agent_bindings_table.php`：???过???
- `php -l tests\Feature\AdminDataScopeSchemaCommentReadabilityTest.php`：???过???
- `vendor\bin\phpunit tests\Feature\AdminDataScopeSchemaCommentReadabilityTest.php`：???过???2 tests / 60 assertions???
- `vendor\bin\phpunit tests\Feature\AdminDataScopeServiceTest.php`：???过???4 tests / 6 assertions???
- `vendor\bin\phpunit tests\Feature\AdminDataScopeManagementTest.php tests\Feature\AdminDataScopeRuntimeLocalizationTest.php`：???过???4 tests / 14 assertions???
- `rg "数据|关联|角色|管理|代理|用户|Table Name|Relation:|Attribute Casting" app\Models\RoleDataScope.php app\Models\AdminAgentBinding.php database\migrations\2026_06_06_000001_create_role_data_scopes_table.php database\migrations\2026_06_06_000002_create_admin_agent_bindings_table.php`：无命中???

### 本轮边界
- 本轮只修改数据范围模型和迁移注释，并新增注释质量测试，没有改变表结构、索引???模型关联???数据范围计算???辑或管理员授权规则???
- 当前数据范围的运行???辑仍由 `AdminDataScopeService`、`role_data_scopes`、`admin_agent_bindings` ??? `agent_descendants` 共同决定；本轮确保这些数据表来源的注释和参数含义可维护???
## 150. 2026-06-09 公共 API 响应多语???契约补齐

### 本次处理目标
- 继续执行“后端必须支持多语言”的目标，优先补齐所有接口共用的响应消息入口???
- 保证 `ApiResponse` 未显式传??? message 时，可以根据 `ResponseCode` 自动返回当前语言环境下的 `response.*` 文案???
- 清理 `ApiResponse` ??? `ResponseCode` 中历史编码乱码和英文占位注释，补充参数???辑说明???

### 修改文件
- `app/Traits/ApiResponse.php`
  - 重写为干??? UTF-8 中文注释???
  - 明确???有前后台 API 统一返回 `code`、`message`、`data` 三个字段???
  - 明确 `$message` 可传 Laravel 多语??? key；为空时根据 `$code` 通过 `ResponseCode::messageKey()` 自动读取语言包???
  - 补充 `success($data, $message, $code)` ??? `error($message, $code, $data)` 的参数含义及功能作用???
- `app/Constants/ResponseCode.php`
  - 重写为干??? UTF-8 中文注释???
  - 保留原有响应码数值和别名常量不变???
  - 补齐 `messageKey()` 映射，覆??? `INVALID_AGENT_LEVEL`、`INVALID_AUDIT_STATUS`、`WITHDRAWAL_NOT_ALLOWED`、`DEPOSIT_NOT_ALLOWED`、`INVALID_AMOUNT`、`INSUFFICIENT_BALANCE`、`RISK_RATE_EXCEEDED`、`CANCEL_APPLY_EXISTS`、`BLACKLISTED`、`DATA_ALREADY_EXISTS`、`SETTLEMENT_NOT_FOUND`、`ORDER_NOT_FOUND`、`MT4_SYNC_FAILED`、`QUERY_SUCCESS`、`QUERY_FAILED`、`IMPORT_SUCCESS`、`IMPORT_FAILED`、`EXPORT_SUCCESS`、`BATCH_SUCCESS`、`BATCH_PARTIAL_FAILED`、`ACCOUNT_LOCKED`、`RATE_LIMITED`、`EMAIL_SEND_FAILED`、`THIRD_PARTY_ERROR` 等此前可能回?????? `response.unknown` 的状态码???
- `tests/Feature/ApiResponseLocalizationContractTest.php`
  - 新增公共 API 响应多语???契约测试???
  - 使用反射读取 `ResponseCode` 中全部整数状态码，并校验每个状???码都映射到明确??? `response.*` key???
  - 校验 `resources/lang/zh-CN/response.php` ??? `resources/lang/en/response.php` 同时存在对应语言包键???
  - 禁止公共响应层继续出??? `Standard JSON Response Trait`、`Unified Response Status Code Constants` 和常??? mojibake 乱码片段???

### 数据与配置来???
- 响应码来源：`app/Constants/ResponseCode.php` 中定义的整数常量，别名常量去重后由测试反射读取???
- 多语???来源：`resources/lang/zh-CN/response.php` ??? `resources/lang/en/response.php`???
- 示例映射：`ResponseCode::SUCCESS` -> `response.success`???
- 示例映射：`ResponseCode::INVALID_AGENT_LEVEL` -> `response.invalid_agent_level`???
- 示例映射：`ResponseCode::THIRD_PARTY_ERROR` -> `response.third_party_error`???

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\ApiResponseLocalizationContractTest.php` 首次失败，提示状态码 `2007` 仍回?????? `response.unknown`，且 `ApiResponse.php` 缺少“参数???辑说明”???
- GREEN：补齐所有公共响应码??? `response.*` 的映射，并重写公共响应层中文注释后，专项测试通过???

### 验证记录
- `php -l app\Traits\ApiResponse.php`：???过???
- `php -l app\Constants\ResponseCode.php`：???过???
- `php -l tests\Feature\ApiResponseLocalizationContractTest.php`：???过???
- `vendor\bin\phpunit tests\Feature\ApiResponseLocalizationContractTest.php`：???过???2 tests / 241 assertions???
- `vendor\bin\phpunit tests\Feature\AdminCommonRuntimeLocalizationTest.php`：???过???2 tests / 6 assertions???
- `vendor\bin\phpunit tests\Feature\JwtServiceLocalizationTest.php tests\Feature\JwtAuthMiddlewareLocalizationTest.php`：???过???2 tests / 23 assertions???
- `vendor\bin\phpunit tests\Feature\AdminCheckPermissionMiddlewareReadabilityTest.php tests\Feature\AdminAuthenticateMiddlewareLocalizationTest.php`：???过???1 test / 23 assertions???
- `rg "Standard JSON Response Trait|Unified Response Status Code Constants|All APIs return unified format|Get the i18n message key|supports i18n key|鐘舶|成?|数据|认证|权限|失败|鍝嶅???" app\Traits\ApiResponse.php app\Constants\ResponseCode.php`：无命中???

### 本轮边界
- 本轮只修改公共响应层注释和状态码到语?????? key 的映射，没有改变 JSON 响应结构、HTTP 状???码、控制器调用方式或语???包文案内容???
- 后续仍需继续扫描具体后台控制器中直接传入硬编码中???/英文 message 的接口，并???步迁移??? `admin.*`、`response.*` 或模块语?????? key???
## 151. 2026-06-09 用户注册服务硬编码消息迁移到多语??? key

### 本次处理目标
- 继续执行“后端必须支持多语言”的目标，将 `UserRegistrationService` 中直接返回给前台页面的硬编码中文业务消息迁移到语???包???
- 覆盖注册成功、账号类型错误???普通客户缺少邀请人、普通客户缺少有效邀请人四类注册链路消息???
- 保证前台 Layui/Blade 注册页在中文和英文环境下都可以从后端获得可切换文案???

### 修改文件
- `app/Services/UserRegistrationService.php`
  - ??? `注册成功` 改为 `__('register.success')`???
  - ??? `账户类型无效` 改为 `__('register.invalid_account_type')`???
  - ??? `普???客户必须填写邀请人ID` 改为 `__('register.customer_inviter_required')`???
  - ??? `普???客户必须提供有效邀请人ID` 改为 `__('register.customer_valid_inviter_required')`???
- `resources/lang/zh-CN/register.php`
  - 新增 `success`、`invalid_account_type`、`customer_inviter_required`、`customer_valid_inviter_required` 中文文案???
- `resources/lang/en/register.php`
  - 新增同名英文文案，并补齐 `email_exists`、`inviter_*`、`invalid_commission_mode`、`inviter_valid` 等注册规??? key，避免英文环境读取注册规则时回?????? key???
- `tests/Feature/UserRegistrationServiceMessageKeyTest.php`
  - 新增注册服务消息 key 契约测试???
  - 静???检查注册服务不再保留上述硬编码中文消息???
  - 校验中英??? register 语言包同时存在注册服务依??? key???

### 多语??? key 清单
- `register.success`：注册成功???
- `register.invalid_account_type`：账号类型不在代理商/普???客户范围内???
- `register.customer_inviter_required`：普通客户注册必须填写邀请人 ID???
- `register.customer_valid_inviter_required`：普通客户注册必须提供有效邀请人 ID???

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\UserRegistrationServiceMessageKeyTest.php` 首次失败，提??? `UserRegistrationService.php` 缺少 `__('register.success')`，且中文 register 语言包缺??? `success` key???
- GREEN：迁移服务消息并补齐中英文语???包后，专项测试???过???

### 验证记录
- `php -l app\Services\UserRegistrationService.php`：???过???
- `php -l resources\lang\zh-CN\register.php`：???过???
- `php -l resources\lang\en\register.php`：???过???
- `php -l tests\Feature\UserRegistrationServiceMessageKeyTest.php`：???过???
- `vendor\bin\phpunit tests\Feature\UserRegistrationServiceMessageKeyTest.php`：???过???2 tests / 24 assertions???
- `vendor\bin\phpunit tests\Feature\UserRegistrationServiceLocalizationTest.php`：???过???2 tests / 14 assertions???
- `vendor\bin\phpunit tests\Feature\UserRegistrationServiceLocalizationTest.php tests\Feature\DefaultAdminAndFrontMenuRoleMigrationTest.php`：???过???2 tests / 14 assertions???
- `rg "注册成功|账户类型无效|普???客户必须填写邀请人ID|普???客户必须提供有效邀请人ID" app\Services\UserRegistrationService.php`：无命中???

### 本轮边界
- 本轮只迁移注册服务返回消息和补齐语言??? key，没有改变注册事务???用户编号生成???登录账号创建???用户资料创建???实名认证创建或代理后代关系写入逻辑???
- 后续仍需继续扫描后台控制器中??? `$validator->errors()->first()`、`$e->getMessage()` 以及旧控制器硬编码验证消息，逐步迁移到可控的语言??? key 或异常包装策略???
## 152. 2026-06-09 后台权限名称中文注释与权限字符串文档复核

### 本次处理目标
- 响应“后台所有权限名称，??? MD 文件中必须有中文注释当前权限名称、对应字符串和功能作用???的要求???
- 复核 `docs/admin-permission-name-reference.md` 是否已经以真??? DB 权限数据为准，???条写明后台权限名称、`permissions.slug` 权限字符串???`permissions.api_route` 接口路由字符串???`permissions.route` 页面路由和功能作用???
- 用现有测试证明文档不是手写零散清单，而是能覆盖当前真实数据库 `permissions` 表中 `guard_type=admin` 的全部后台权限记录???

### 复核文件
- `docs/admin-permission-name-reference.md`
  - 已包含???权限名称中文注释规则???小节，明确每条后台权限必须独立成行???
  - “权限明细???表格已逐条列出 `权限名称`、`权限字符串`、`接口路由字符串`、`页面路由`、`状???` ??? `功能作用`???
  - `功能作用` 文本会点名当前权限名称，并说明该权限用于菜单显示、按钮显隐???页面入口或接口鉴权的控制边界???
- `tests/Feature/AdminPermissionNameReferenceRowCompletenessTest.php`
  - 直接读取真实 DB `permissions` 表，筛??? `guard_type=admin`，校验每???条后台权限都必须在同?????? MD 表格行中写明名称、字符串和功能作用???
- `tests/Feature/AdminPermissionNameReferenceDocumentationTest.php`
  - 校验权限说明文档覆盖全部后台权限??? `slug`、`api_route` ??? `route` 字段，避免遗漏真实数据库权限配置???

### 真实 DB 数据来源
- 数据表：`permissions`???
- 筛???条件：`guard_type=admin`???
- 当前后台权限总数：`195`???
- 当前测试断言总数：???行完整??? `1757` 个断???，文档覆盖??? `810` 个断??????

### 验证记录
- `vendor\bin\phpunit tests\Feature\AdminPermissionNameReferenceRowCompletenessTest.php`：???过，`1 test / 1757 assertions`???
- `vendor\bin\phpunit tests\Feature\AdminPermissionNameReferenceDocumentationTest.php`：???过，`1 test / 810 assertions`???

### 本轮结论
- 当前后台权限名称说明 MD 已满足：???有后台权限名称均有中文说明，???有权限字符串均以反引号???字列出，并且每条权限都有对应功能作用说明???
- 本轮没有修改业务鉴权逻辑、菜单渲染???辑、角色授权???辑或数据库权限数据，只补充???终清单中的审计记录???
## 153. 2026-06-09 后台 Blade 登录控制器多语言验证与中文参数注释补???

### 本次处理目标
- 继续执行“后端必须支持多语言”和“所有模块文件及参数必须有详细中文???辑注释”的要求???
- 修复 `AdminAuthController` 中后??? Blade 登录表单验证消息固定 `zh_CN` 和硬编码中文提示的问题???
- 补齐后台 Blade 登录入口、登录动作??????出动作以??? `email`、`password`、`remember`、`$request` 参数的中文???辑说明???

### 修改文件
- `app/Http/Controllers/Admin/AdminAuthController.php`
  - 新增“后??? Blade 登录控制器???类级中文说明，明确该控制器服务传统 Blade 登录页，不负??? JWT API 登录???
  - ??? `showLogin()` 补充中文说明：已登录管理员跳转控制台，未登录管理员返回登录页???
  - ??? `doLogin(Request $request)` 补充中文参数说明：`$request`、`email`、`password`、`remember` 的来源???含义和功能作用???
  - ??? `email.required` 改为 `__('validation.required', ['attribute' => __('auth.email')])`，跟随当前语???环境返回验证文案???
  - ??? `password.required` 改为 `__('validation.required', ['attribute' => __('auth.password_label')])`，移除硬编码“不能为空??????
  - 引入 `App\Models\AdminLoginLog`，并为后台登录审计日志写入补充中文说明???
  - ??? `logout(Request $request)` 补充 Session 失效与重新生??? CSRF Token 的安全边界说明???
- `resources/lang/zh-CN/auth.php`
  - 新增 `auth.password_label`，中文???为“密码??????
- `resources/lang/en/auth.php`
  - 新增 `auth.password_label`，英文??间负 `Password`???
- `tests/Feature/AdminBladeLoginControllerLocalizationTest.php`
  - 新增后台 Blade 登录控制器多语言与中文注释契约测试???
  - 约束控制器不得再出现 `__('common.required', [], 'zh_CN')`、硬编码“不能为空???或 mojibake 空???提示???
  - 约束控制器必须使用当??? locale ??? `validation.required` ??? `auth.*` 语言 key???

### 多语??? key 清单
- `validation.required`：Laravel 表单必填验证文案，使用当前语???环境???
- `auth.email`：管理员登录邮箱字段名称???
- `auth.password_label`：管理员登录密码字段名称，本轮补齐中英文语言包???
- `auth.failed`：管理员登录失败提示，保留原有多语言 key???

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AdminBladeLoginControllerLocalizationTest.php` 首次失败，提??? `AdminAuthController` 缺少“后??? Blade 登录控制器???中文???辑注释，同时源码仍保留固定 `zh_CN` 和硬编码中文验证提示???
- GREEN：补齐控制器中文注释、改??? `validation.required` 当前语言环境文案、补??? `auth.password_label` 中英??? key 后，专项测试通过???

### 验证记录
- `php -l app\Http\Controllers\Admin\AdminAuthController.php`：???过???
- `php -l resources\lang\zh-CN\auth.php`：???过???
- `php -l resources\lang\en\auth.php`：???过???
- `php -l tests\Feature\AdminBladeLoginControllerLocalizationTest.php`：???过???
- `vendor\bin\phpunit tests\Feature\AdminBladeLoginControllerLocalizationTest.php`：???过，`1 test / 19 assertions`???
- `vendor\bin\phpunit tests\Feature\AdminCommonRuntimeLocalizationTest.php`：???过，`2 tests / 6 assertions`???
- `vendor\bin\phpunit tests\Feature\AdminAuthenticateMiddlewareLocalizationTest.php`：???过，`2 tests / 8 assertions`???
- `vendor\bin\phpunit tests\Feature\AdminAuthControllerCommentReadabilityTest.php`：???过，`1 test / 35 assertions`???
- `rg "common\.required|zh_CN|不能为空|不能為空|不???为空" app\Http\Controllers\Admin\AdminAuthController.php`：无命中???

### 本轮边界
- 本轮只修复传??? Blade 后台登录控制器的表单验证多语???和中文注释，不修??? JWT API 登录、管理员账号密码规则、Session guard 配置、登录路由或数据库结构???
- 后续仍需继续扫描其他后台 Blade 控制器和页面中是否存在硬编码提示、固??? locale、参数注释缺失或 UI 风格未统???的问题???
## 154. 2026-06-09 后台控制器异常消息统???多语???响应

### 本次处理目标
- 继续执行“后端必须支持多语言”的要求，收敛后台控制器 `catch` 分支直接返回 `$e->getMessage()` 的问题???
- 避免数据库异常???文件路径???第三方接口异常或内部类名被原样返回给后台页???/API 前端???
- 将未预期服务端异常统???改为 `response.server_error` 当前语言环境文案???

### 修改文件
- `app/Http/Controllers/Admin/AdminBaseController.php`
  - 重写为可读中文???辑注释，说明后台控制器统一复用 `ApiResponse`???
  - 新增 `serverErrorResponse()` 方法???
  - `serverErrorResponse()` 固定返回 `__('response.server_error')` ??? `ResponseCode::SERVER_ERROR`，确保服务端异常响应跟随当前语言环境???
  - 参数说明明确：该方法不接收异常对象作为返回内容，避免泄露 SQL、文件路径???第三方接口细节或内部类名???
- 批量替换以下后台控制器中??? `$this->error($e->getMessage(), ResponseCode::SERVER_ERROR)` ??? `$this->serverErrorResponse()`???
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
  - 新增后台异常消息多语???契约测试???
  - 静???扫??? `app/Http/Controllers/Admin`，禁止后台控制器继续直接外泄 `$e->getMessage()`???
  - 校验 `AdminBaseController` 必须提供 `serverErrorResponse()`，并使用 `response.server_error` ??? `ResponseCode::SERVER_ERROR`???

### 多语??? key 清单
- `response.server_error`：服务端未预期异常统???提示???
  - `resources/lang/zh-CN/response.php`：服务器内部错误???
  - `resources/lang/en/response.php`：Internal server error???
- `ResponseCode::SERVER_ERROR`：服务端错误状???码，统?????? `ResponseCode::messageKey()` 映射??? `response.server_error`???

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AdminExceptionMessageLocalizationTest.php` 首次失败，提??? `AdminController.php` 仍直接返??? `$e->getMessage()`，且 `AdminBaseController` 缺少“后台服务端异常响应”与 `serverErrorResponse()`???
- GREEN：新增基类统???异常响应方法并替??? 16 个后台控制器的异常外泄调用后，专项测试???过???

### 验证记录
- `php -l app\Http\Controllers\Admin\AdminBaseController.php`：???过???
- 批量 `php -l` 以下控制器：`WithdrawController.php`、`VoucherController.php`、`UserController.php`、`SystemConfigController.php`、`NewsController.php`、`BigAgentController.php`、`CancelApplyController.php`、`AgentController.php`、`DashboardController.php`、`AdminController.php`、`BlacklistController.php`、`CommissionController.php`、`AgentLevelController.php`、`DepositController.php`、`GroupConfigController.php`、`PaymentChannelController.php`：全部???过???
- `vendor\bin\phpunit tests\Feature\AdminExceptionMessageLocalizationTest.php`：???过，`2 tests / 43 assertions`???
- `vendor\bin\phpunit tests\Feature\ApiResponseLocalizationContractTest.php`：???过，`2 tests / 241 assertions`???
- `vendor\bin\phpunit tests\Feature\AdminCommonRuntimeLocalizationTest.php`：???过，`2 tests / 6 assertions`???
- `vendor\bin\phpunit tests\Feature\AdminDashboardControllerLocalizationTest.php`：???过，`2 tests / 6 assertions`???
- `vendor\bin\phpunit tests\Feature\AdminUserControllerLocalizationTest.php`：???过，`2 tests / 36 assertions`???
- `rg "getMessage\(\)|serverErrorResponse" app\Http\Controllers\Admin`：只??? `serverErrorResponse()` 调用和基类方法说明，未再发现后台控制器直接返??? `$e->getMessage()`???

### 本轮边界
- 本轮只统???未预期异常的服务端错误响应，不改变业务可预期错误、表单校验失败???权限不足???数据不存在等具体业务提示???
- 当前 `catch (\Exception $e)` 仍保留原控制流，仅将前端可见 message 改为多语???安全文案；如后续???要记录异常详情，应接入日志???不是返回给前端???
## 155. 2026-06-09 后台 Blade 登录链路统一到现??? Layui 视图

### 本次处理目标
- 继续执行“后??? UI 必须参??? Vben Admin、Vue Naive Admin、Naive UI Admin、Ant Design Pro、Arco Design Pro，并使用 Laravel Blade 模板渲染”的要求???
- 修复 `AdminAuthController` 仍返回旧 `resources/views/admin/auth/login.blade.php` 的问题，避免后台登录入口绕开 `resources/admin/layui` 现代后台视图体系???
- 修复现代后台登录页表单字段与控制器校验不???致的问题：页面提??? `username`，控制器校验 `email`，会导致传统 Blade 登录链路不可用???

### 修改文件
- `app/Http/Controllers/Admin/AdminAuthController.php`
  - `showLogin()` ??? `view('admin.auth.login')` 改为 `view('admin_layui::auth.login')`???
  - 中文注释同步说明未登录管理员返回 `admin_layui::auth.login` 现代 Layui Blade 模板???
  - 继续保留 `email`、`password`、`remember` 参数说明和多语言验证逻辑???
- `resources/admin/layui/auth/login.blade.php`
  - 表单 action 改为 `{{ route('admin.login.post') }}`，method 改为 `POST`，并加入 `@csrf`???
  - 登录账号字段??? `name="username"` 改为 `name="email"`，与 `AdminAuthController::doLogin` ??? `email|required|email` 校验保持???鑷淬??
  - 密码字段使用 `data-translate-placeholder="auth.password_label"` ??? `__('auth.password_label')`???
  - 新增 `remember` 复???框，与控制器中??? `$request->boolean('remember')` 对齐???
  - 补充 `email`、`password`、`remember` 的中文???辑注释和功能作用说明???
- `public/js/admin/layui/auth/login.js`
  - 重写??? Blade 表单登录脚本，不再拦截表单请??? `/api/admin/login`???
  - 保留 Layui form 正常提交，返??? `true` 交给浏览??? POST ??? `admin.login.post`???
  - 保留语言切换逻辑，并补充 `email`、`password`、`remember`、`CRM.switchLang` 的中文注释???
- `tests/Feature/AdminBladeLoginViewConsistencyTest.php`
  - 新增后台 Blade 登录视图???鑷存??测试???
  - 约束控制器必须使??? `admin_layui::auth.login`，并禁止回???到旧 `admin.auth.login`???
  - 约束现代登录页必须提??? `email`、`password`、`remember` 字段???
- `tests/Feature/AdminLoginJsCommentReadabilityTest.php`
  - 按当??? Blade 登录目标重写测试契约???
  - 禁止登录脚本回?????? `/api/admin/login` ??? `CrmAjax.setToken` ??? JWT API 登录模式???

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AdminBladeLoginViewConsistencyTest.php` 首次失败，提??? `AdminAuthController` 仍返??? `admin.auth.login`，且现代登录页缺??? `admin.login.post` 表单 action、`email` 字段??? `remember` 字段???
- GREEN：切换控制器视图、修复现代登录页字段、重写登??? JS ??? Blade 表单提交后，专项测试通过???
- 兼容修复：`AdminLoginJsCommentReadabilityTest` 原先??? JWT API 登录脚本设计，已改为当前 Blade 登录目标，防止未来回?????? API 拦截登录???

### 验证记录
- `php -l app\Http\Controllers\Admin\AdminAuthController.php`：???过???
- `php -l tests\Feature\AdminBladeLoginViewConsistencyTest.php`：???过???
- `php -l tests\Feature\AdminLoginJsCommentReadabilityTest.php`：???过???
- `vendor\bin\phpunit tests\Feature\AdminBladeLoginViewConsistencyTest.php`：???过，`2 tests / 14 assertions`???
- `vendor\bin\phpunit tests\Feature\AdminLoginJsCommentReadabilityTest.php`：???过，`2 tests / 16 assertions`???
- `vendor\bin\phpunit tests\Feature\AdminBladeLoginControllerLocalizationTest.php`：???过，`1 test / 19 assertions`???
- `vendor\bin\phpunit tests\Feature\AdminBladeUiTest.php`：???过，`1 test / 5 assertions`???
- `vendor\bin\phpunit tests\Feature\AdminLayoutUiReferenceDensityTest.php`：???过，`2 tests / 25 assertions`???
- `vendor\bin\phpunit tests\Feature\AdminLayoutShellReadabilityTest.php`：???过，`2 tests / 18 assertions`???
- `vendor\bin\phpunit tests\Feature\AdminLayuiLayoutReadableChineseTest.php`：???过，`1 test / 20 assertions`???
- `rg "admin.auth.login" app routes tests`：生产代码无旧视图引用，仅测试说明中保留回归约束???
- `rg "/api/admin/login|CrmAjax.setToken" public\js\admin\layui\auth\login.js tests\Feature\AdminLoginJsCommentReadabilityTest.php`：登录脚本无实际 API/JWT 调用残留，仅测试中保留禁止回???断言???

### 本轮边界
- 本轮只统???后台 Blade 登录页入口与表单字段，不修改 `/api/admin/login` JWT 登录接口本身???
- ??? `resources/views/admin/auth/login.blade.php` 文件仍保留在仓库中，但当前后台登录控制器已不再引用；后续可继续审??? `resources/views/admin` 旧视图目录是否需要删除???归档或增加禁止引用测试???
## 156. 2026-06-10 旧后??? Blade 视图归档??? admin_layui 回???保护

### 本次处理目标
- 继续执行“后??? UI 必须使用 Laravel Blade 模板渲染，并参??? Vben Admin、Vue Naive Admin、Naive UI Admin、Ant Design Pro、Arco Design Pro”的要求???
- 防止后台页面再次回???到历??? `resources/views/admin` 旧视图目录???
- 修复 `Admin\AuthController@showLogin()` 中仍存在的错误旧视图引用 `view("admin.layui.auth.login")`，统???改为 `admin_layui::auth.login`???

### 修改文件
- `app/Http/Controllers/Admin/AuthController.php`
  - `showLogin()` ??? `view("admin.layui.auth.login")` 改为 `view("admin_layui::auth.login")`???
  - 中文注释同步说明该旧控制器路径即使保留，也必须返回现??? Layui Blade 登录页???
- `resources/views/admin/README.md`
  - 新增旧后??? Blade 视图归档说明???
  - 明确 `resources/views/admin` 只用于迁移对照和排查旧页面差异???
  - 明确当前后台页面必须统一使用 `resources/admin/layui` ??? `admin_layui::` 视图命名空间???
  - 明确禁止在生产路由或控制器中继续引用 `view('admin.*')`、`view("admin.*")`、`@extends('admin.layouts.app')` 或旧目录???部视图???
- `tests/Feature/AdminLegacyViewNamespaceGuardTest.php`
  - 新增旧后台视图命名空间回???保护测试???
  - 扫描 `app`、`routes`、`resources/admin/layui` 下的 PHP/Blade 文件，禁止旧 `admin.*` 视图引用回流???
  - 校验旧视图目录必须保留归档说明，避免维护者误以为它仍是当前后台入口???

### TDD 记录
- RED：`vendor\bin\phpunit tests\Feature\AdminLegacyViewNamespaceGuardTest.php` 首次失败，发??? `app\Http\Controllers\Admin\AuthController.php` 仍包??? `view("admin.layui.auth.login")`，并??? `resources/views/admin/README.md` 不存在???
- GREEN：修复旧控制器登录视图引用并新增归档说明后，专项测试通过???

### 验证记录
- `php -l app\Http\Controllers\Admin\AuthController.php`：???过???
- `php -l tests\Feature\AdminLegacyViewNamespaceGuardTest.php`：???过???
- `vendor\bin\phpunit tests\Feature\AdminLegacyViewNamespaceGuardTest.php`：???过，`2 tests / 1222 assertions`???
- `vendor\bin\phpunit tests\Feature\AdminBladeLoginViewConsistencyTest.php`：???过，`2 tests / 14 assertions`???
- `vendor\bin\phpunit tests\Feature\AdminAuthControllerCommentReadabilityTest.php`：???过，`1 test / 35 assertions`???
- `vendor\bin\phpunit tests\Feature\AdminBladeUiTest.php`：???过，`1 test / 5 assertions`???
- `vendor\bin\phpunit tests\Feature\AdminBladeModuleCoverageTest.php`：???过，`20 tests / 60 assertions`???
- `vendor\bin\phpunit tests\Feature\AdminPageMenuPermissionCoverageTest.php`：???过，`1 test / 2 assertions`???
- `rg "view\('admin\." app routes resources\admin\layui`：无命中???
- `AdminLegacyViewNamespaceGuardTest` 宸插畬鏁存壂鎻? `app`、`routes`、`resources/admin/layui`，证明生产代码不再引用旧 `resources/views/admin` 后台视图???

### 本轮边界
- 本轮没有删除 `resources/views/admin` 旧视图文件，只新增归档说明并禁止生产代码继续引用???
- 旧视图目录中仍可能存在历史乱码和旧布???代码，但它们现在被定义为迁移对照资料；当前后台页面入口必须走 `resources/admin/layui`???
## 157. 2026-06-10 菜单服务中文逻辑与参数注释补???

### 本次变更文件
- `app/Services/MenuService.php`
  - 补齐菜单服务类???守卫类型???权限编号集合???菜单集合???语???环境等关键参数的中文逻辑注释???
  - 清理 `Menu Service`、`Function`、`Parameter`、`Returns`、`Table Name`、`Relation:` 等英文占位式注释，避免后续维护时出现中英混杂和含义不明确的问题???
  - 保留权限菜单??? `permissions` 表配置生成的核心逻辑，未改变菜单鉴权行为???
- `tests/Feature/MenuServiceCommentReadabilityTest.php`
  - 强化菜单服务中文注释可读性测试???
  - 增加英文占位片段和常见乱码片段的禁止断言，用测试约束后续新增注释质量???

### TDD 执行记录
- RED：先调整 `MenuServiceCommentReadabilityTest`，要求存??? `菜单服务。` 等中文说明，并禁??? `Menu Service` 等英文占位片段；测试按预期失败，证明旧注释未满足当前中文注释规范???
- GREEN：补齐并清理 `MenuService.php` 注释后，重新运行测试通过???

### 验证命令
- `php -l app\Services\MenuService.php`
- `php -l tests\Feature\MenuServiceCommentReadabilityTest.php`
- `vendor\bin\phpunit tests\Feature\MenuServiceCommentReadabilityTest.php`
- `vendor\bin\phpunit tests\Feature\MenuControllerCommentReadabilityTest.php`
- `vendor\bin\phpunit tests\Feature\AdminBladeButtonPermissionCoverageTest.php`
- `vendor\bin\phpunit tests\Feature\AdminPageMenuPermissionCoverageTest.php`
- `rg -n "Menu Service|Function|Parameter|Returns|Table Name|Relation:" app\Services\MenuService.php tests\Feature\MenuServiceCommentReadabilityTest.php`
- `rg -n "閻|閺|闁|濞|缁|缂|濠|閿|閸|???" app\Services\MenuService.php tests\Feature\MenuServiceCommentReadabilityTest.php`

### 验证结果
- `MenuServiceCommentReadabilityTest`???2 个测试???26 个断???通过???
- `MenuControllerCommentReadabilityTest`???3 个测试???37 个断???通过???
- `AdminBladeButtonPermissionCoverageTest`???2 个测试???117 个断???通过???
- `AdminPageMenuPermissionCoverageTest`???1 个测试???2 个断???通过???
- 生产文件 `app/Services/MenuService.php` 未再发现英文占位注释或常见中文乱码片段???

### 对目标的推进
- 菜单服务是前后台菜单与按钮权限从数据表配置生成的核心服务之一，本次补齐了其关键参数中文注释，并用测试固定注释规范???
- 本次未改动数据库结构、菜单权限数据和运行时鉴权???辑，仅提升维护可读性与文档???鑷存?????
## 158. 2026-06-11 用户注册服务中文逻辑注释与多语言约束复核

### 本次变更文件
- `app/Services/UserRegistrationService.php`
  - 将类标题从中英混??? `用户注册服务 | User Registration Service` 改为纯中??? `用户注册服务。`???
  - 补齐注册主入口???注册数据校验???邮箱重复检查???邀请人规则校验、业??? user_id 生成、登录账号创建???业务资料创建???实名认证资料创建???代理后代关系同步??????别标准化???注册前置验证等方法的中文???辑注释???
  - 明确 `$data`、`$parentId`、`$accountType`、`$commissionMode`、`$userId`、`$userLogin`、`$userInfo`、`$parentInfo`、`$familyTree`、`$treeIds`、`$ancestorIds` 等参数或中间变量的业务含义和功能作用???
  - 说明 `agent_descendants` 表保存代理与下级用户的祖先后代关系，用于后续数据权限、团队统计和返佣查询???
  - 保留原有注册校验、事务写库???语?????? key、家族链和代理后代关系写入???辑，未改变业务行为???
- `tests/Feature/UserRegistrationServiceLocalizationTest.php`
  - 重写为干??? UTF-8 中文注释测试???
  - 要求注册服务源码必须包含可读中文参数说明，并禁止 `User Registration Service`、`鐢`、`璇`、`鍙`、`閭`、`缁`、`锟`、`????` 等英文标题或历史编码残留???

### TDD 执行记录
- RED：先更新 `UserRegistrationServiceLocalizationTest`，要求注册服务包??? `用户注册服务。`、核心参数中文说明和 `agent_descendants` 表用途说明；首次运行失败，提示生产文件仍缺少 `用户注册服务。`，且保留 `User Registration Service`???
- GREEN：只重写 `UserRegistrationService.php` 注释块，不改注册业务代码；重新运行专项测试???过???

### 验证命令
- `php -l app\Services\UserRegistrationService.php`
- `php -l tests\Feature\UserRegistrationServiceLocalizationTest.php`
- `vendor\bin\phpunit tests\Feature\UserRegistrationServiceLocalizationTest.php`
- `vendor\bin\phpunit tests\Feature\UserRegistrationServiceMessageKeyTest.php`
- `vendor\bin\phpunit tests\Feature\UserRegistrationServiceLocalizationTest.php tests\Feature\FrontAuthControllerLocalizationTest.php`
- `rg -n "User Registration Service|鐢|璇|鍙|閭|缁|锟|€\?" app\Services\UserRegistrationService.php tests\Feature\UserRegistrationServiceLocalizationTest.php`

### 验证结果
- `UserRegistrationServiceLocalizationTest`???3 个测试???24 个断???通过???
- `UserRegistrationServiceMessageKeyTest`???2 个测试???24 个断???通过???
- `UserRegistrationServiceLocalizationTest + FrontAuthControllerLocalizationTest`???3 个测试???24 个断???通过???
- 生产文件 `app/Services/UserRegistrationService.php` 未再发现注册服务英文标题或常见乱码片段；搜索命中仅保留在测试的禁止片段清单中???

### 对目标的推进
- 用户注册服务同时影响前台代理商和普???客户注册，本次补齐了多表写入???邀请人规则、数据权限基???关系和多语言消息的中文维护说明???
- 本轮没有改动数据库结构???注册返回结构???注册校验规则???密码加密方式或代理关系写入逻辑，仅提升注释可维护???和测试约束???
## 159. 2026-06-11 前台注册规则与代理家族链服务中文注释补齐

### 本次变更文件
- `app/Services/FrontRegisterRuleService.php`
  - 将旧英文说明 `Port of legacy RegisterEnMiddleware / RegisterGmtkCnEnMiddleware invite rules.` 改为中文类注释???
  - 补齐前台注册???请规则服务的功能说明，明确代理商和普通客户注册都???要校验邀请人是否存在、是否启用???是否为代理账号???
  - 补齐 `validate()` 方法参数注释，说??? `$inviterId`、`$accountType`、`$commissionMode`、`$login`、`$info` 的业务含义???
  - 明确 `message` 返回的是 `register` 语言??? key，上层再通过 `__()` 转成当前语言文案???
- `app/Services/FamilyTreeService.php`
  - 将英文标题和历史编码乱码注释重写为干??? UTF-8 中文注释???
  - 补齐代理家族链服务类说明，明??? `user_infos.family_tree` ??? `agent_descendants` 表分别承担链路存储???团队统计???返佣汇总???数据范围过滤等作用???
  - 补齐 `getAncestors()`、`getDirectChildren()`、`getAllDescendants()`、`getSubAgentStats()`、`getAgentStats()`、`getNetworkTree()`、`rebuildFamilyTree()`、`rebuildDescendants()` 的参数和中间变量中文注释???
  - 明确 `$userId`、`$agentId`、`$dateFrom`、`$dateTo`、`$descendantIds`、`$treeIds`、`$depth`、`$isDirect` 等参数或变量的???辑含义???
  - 保留原有查询条件、返回字段???事务边界和代理关系重建逻辑，仅把少量单行早返回改为带花括号的等价写法???
- `tests/Feature/FrontRegistrationRuleAndFamilyTreeServiceCommentReadabilityTest.php`
  - 新增前台注册规则与代理家族链服务中文注释可读性测试???
  - 要求两个服务必须包含关键中文参数说明，并禁止旧英文占位标题与常见历史编码乱码片段???

### TDD 执行记录
- RED：先新增 `FrontRegistrationRuleAndFamilyTreeServiceCommentReadabilityTest`，首次运??? 3 个测试全部失败，分别提示 `FrontRegisterRuleService` 缺少 `前台注册???请规则服务???`、`FamilyTreeService` 缺少 `代理家族链服务???`，并保留 `Port of legacy` 等英文占位注释???
- GREEN：只补齐两个服务的中文注释和参数说明，不改业务判断；重新运行专项测试通过???

### 验证命令
- `php -l app\Services\FrontRegisterRuleService.php`
- `php -l app\Services\FamilyTreeService.php`
- `php -l tests\Feature\FrontRegistrationRuleAndFamilyTreeServiceCommentReadabilityTest.php`
- `vendor\bin\phpunit tests\Feature\FrontRegistrationRuleAndFamilyTreeServiceCommentReadabilityTest.php`
- `vendor\bin\phpunit tests\Feature\UserRegistrationServiceLocalizationTest.php tests\Feature\UserRegistrationServiceMessageKeyTest.php tests\Feature\FrontAuthControllerLocalizationTest.php`
- `vendor\bin\phpunit tests\Feature\FrontDashboardControllerCommentReadabilityTest.php tests\Feature\FrontAgentControllerCommentReadabilityTest.php`
- `rg -n "Port of legacy|Get the full ancestor chain|Get all direct children|Get all descendants|Get agent|Get comprehensive statistics|Get full network tree|Rebuild family_tree|Rebuild agent_descendants|Remove self from the chain|Recursively rebuild children|Delete existing records|Find all users whose family_tree contains this agent|鐢|璇|鍙|閭|缁|锟|€\?" app\Services\FrontRegisterRuleService.php app\Services\FamilyTreeService.php tests\Feature\FrontRegistrationRuleAndFamilyTreeServiceCommentReadabilityTest.php`

### 验证结果
- `FrontRegistrationRuleAndFamilyTreeServiceCommentReadabilityTest`???3 个测试???36 个断???通过???
- `UserRegistrationServiceLocalizationTest + UserRegistrationServiceMessageKeyTest + FrontAuthControllerLocalizationTest`???3 个测试???24 个断???通过???
- `FrontDashboardControllerCommentReadabilityTest + FrontAgentControllerCommentReadabilityTest`???2 个测试???31 个断???通过???
- 生产文件 `FrontRegisterRuleService.php` ??? `FamilyTreeService.php` 未再命中旧英文占位标题或常见乱码片段；搜索命中仅保留在测试禁止片段清单中???

### 对目标的推进
- 前台代理商和普???客户注册???邀请关系???代理团队树、数据范围基???链路的服务层中文注释已补齐???
- 本轮没有改动注册???请校验规则???团队统??? SQL、返佣汇总字段???代理网络树返回结构或数据库结构???
## 160. 2026-07-07 后台礼品导出状态校准与注释回归保护

### 本次处理目标
- 校准后台礼品模块当前状???，避免继续把已实现??? `shipment_list_export` 新项目落点误写成待补导出???
- 明确 `admin_gift_export` 当前绑定 `admin_api_exportGiftShipments`，导出文件名??? `gift_shipments_export.csv`???
- 保留真实边界：兑换扣库存/积分消耗联动与旧项目一致不迁移：旧 send_gift 只写 gift_shipments，无 gift_items 目录表，gift_items 仅用于前台 available_gifts 展示，不能把当前发放 CSV 导出等同于完整礼品兑换规则闭环（详见 ## 381.锛夈€

### 本次变更文件
- `app/Http/Controllers/Admin/GiftController.php`
  - 顶部模块说明改为当前事实：发货列表???可发放地址列表、批量发放???物流更新???礼品配??? CRUD 和当前筛??? CSV 导出已落地???
  - 删除原先把导出能力描述为仅声明权限和待补接口的过期说法???
  - 灏? `writeGiftOperationLog()` 的注释改回礼品发???/物流更新操作日志，把“生??? CSV 下载响应”注释放??? `csvDownload()`銆?
  - 将发货列表筛选条件注释放??? `applyShipmentFilters()`锛岄伩鍏嶈创鍦? `updateShipment()` 前误导维护???
- `tests/Feature/AdminGiftModuleTest.php`
  - 新增 `test_gift_controller_comments_match_export_implementation`，静态约束控制器注释必须匹配当前导出实现???
  - 新增 `test_final_checklist_records_current_gift_export_closure`，约束本清单记录??? 160 节并禁止旧导出待补说法回流???
- `docs/admin-backend-blade-permission-final-checklist.md`
  - 修正??? 24 节当前状态说明??乣admin_gift_export` 权限落点和数据库恢复后的复核边界???
  - 追加本节记录当前校准证据???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\AdminGiftModuleTest.php --filter test_gift_controller_comments_match_export_implementation` 首次失败，命??? `GiftController` 顶部仍包含过期导出待补说明???
- GREEN：只修改 `GiftController` 注释和错位说明，不改礼品导出、发放???物流更新或配置 CRUD 业务代码；专项测试??氳繃銆?
- RED锛歚vendor\bin\phpunit tests\Feature\AdminGiftModuleTest.php --filter test_final_checklist_records_current_gift_export_closure` 首次失败，命中最终清单缺少第 160 节???
- GREEN：追加第 160 节并修正??? 24 节当前状态描述后，专项测试??氳繃銆?

### 验证命令
- `php -l app\Http\Controllers\Admin\GiftController.php`
- `php -l tests\Feature\AdminGiftModuleTest.php`
- `vendor\bin\phpunit tests\Feature\AdminGiftModuleTest.php --filter test_gift_controller_comments_match_export_implementation`
- `vendor\bin\phpunit tests\Feature\AdminGiftModuleTest.php --filter test_final_checklist_records_current_gift_export_closure`

### 当前证据
- `routes/admin.php` 已注??? `POST /api/admin/exportGiftShipments`，路由名 `admin_api_exportGiftShipments`銆?
- `database/migrations/2026_06_07_000011_add_admin_gift_permissions.php` 涓? `admin_gift_export` 鐨? `api_route` 已指??? `admin_api_exportGiftShipments`銆?
- `resources/admin/layui/gifts/index.blade.php` 已提??? `id="exportGiftShipments"` 按钮并绑??? `data-permission="admin_gift_export"`銆?
- `public/js/apps/naive-admin/front-plain.js` 已配??? `exportEndpoint: '/api/admin/exportGiftShipments'` 涓? `exportFileName: 'gift_shipments_export.csv'`銆?
- `app/Http/Controllers/CrmUi/Admin/PageController.php` 已为礼品模块配置 `exportActions('admin_api_exportGiftShipments', 'gift_shipments_export.csv')`銆?
- `AdminGiftModuleTest::test_gift_shipment_export_endpoint_returns_current_filter_csv` 已覆盖当前筛选发货记??? CSV 响应???
- `AdminGiftModuleTest::test_gift_controller_comments_match_export_implementation` 已覆盖本次注释回归边界???

### 剩余边界
- 本轮没有改动礼品发放、物流更新???礼品配??? CRUD、前台可兑换礼品列表或数据库结构???
- 兑换扣库???/积分消???联动仍未迁移；后续应基于真实兑换规则???库存扣减???积分流水和失败回滚补独立闭环测试???
- 当前本机 MySQL `127.0.0.1:3307` 仍不可连接；真实 DB 恢复后，???要再运行完整 `AdminGiftModuleTest` 和浏览器侧导出按钮冒烟???
## 161. 2026-07-07 在线用户强制下线 JWT 失效闭环

### 本次处理目标
- 修复后台在线用户强制下线只删??? `user_onlines` 记录，但旧前??? JWT 仍可能继续访问接口的问题???
- 明确前台 JWT 鐨? `sub` 对应 `user_logins.id`锛岃?? `user_onlines.user_id` 是业务用户编号，强制下线时必须先按业务用户编号找到登录主体再清理 SSO 状??併??
- 将当前可落地范围固定为整账号当前前台 JWT 失效；单设备下线、设备维度展示和缓存/心跳精细口径仍需继续迁移???

### 本次变更文件
- `app/Http/Middleware/SingleSignOn.php`
  - SSO 缓存缺失??? jti 不匹配时统一返回 `response.sso_conflict` 鍜? `ResponseCode::SSO_CONFLICT`銆?
  - 不完??? JWT payload 返回认证失败，避免缺??? `guard`銆乣sub` 鎴? `jti` 的请求进入业务控制器???
- `app/Http/Controllers/Admin/OnlineUserController.php`
  - 强制下线事务内先写操作审计，再清理前??? SSO 缓存和登录表 token 标识，最后删除在线记录???
  - 通过 `UserLogin::where('user_id', (int) $online->user_id)` 找到 `user_logins.id`锛屾竻鐞? `sso:user:{login_id}` 并清??? `user_logins.jwt_token_id`銆?
- `tests/Feature/AdminOnlineUserForceOfflineSessionInvalidationTest.php`
  - 新增 SSO 缓存缺失拒绝??? token 的行为测试???
  - 新增后台强制下线必须清理前台 SSO 状???和登录??? token 标识的静态契约测试???
  - 新增???终清单闭环记录测试???
- `docs/admin-legacy-migration-gap-audit.md`
  - 更新 `UserLoginOnlineController` 当前证据，避免继续把真实 JWT 失效描述为完全未迁移???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\AdminOnlineUserForceOfflineSessionInvalidationTest.php --filter test_single_sign_on_rejects_token_when_active_jti_cache_is_missing` 首次失败，暴??? `SingleSignOn` 鍦? `sso:user:{login_id}` 缓存缺失时仍会放行旧 token銆?
- GREEN锛氫慨鏀? `SingleSignOn`，要求缓存缺失或 jti 不匹配都??? SSO 冲突拒绝???
- RED锛歚vendor\bin\phpunit tests\Feature\AdminOnlineUserForceOfflineSessionInvalidationTest.php --filter test_force_offline_controller_clears_front_user_sso_state` 首次失败，暴露强制下线控制器未查 `UserLogin`、未 `Cache::forget`、未清空 `user_logins.jwt_token_id`銆?
- GREEN：强制下线按 `user_onlines.user_id` 找到 `user_logins.id`锛屾竻鐞? `sso:user:{login_id}` 并清??? `user_logins.jwt_token_id`銆?
- RED锛歚vendor\bin\phpunit tests\Feature\AdminOnlineUserForceOfflineSessionInvalidationTest.php --filter test_final_checklist_records_online_user_force_offline_session_invalidation` 首次失败，命中最终清单缺少本节???
- GREEN：追加本节记录当前闭环证据和剩余边界???

### 当前证据
- `SingleSignOn` 已在 SSO 缓存缺失时拒绝旧 JWT銆?
- `OnlineUserController::forceOffline()` 已在删除在线记录前清??? `sso:user:{login_id}`銆?
- `OnlineUserController::forceOffline()` 已清??? `user_logins.jwt_token_id`，避免维护???误判该账号仍保留当前有??? token銆?
- `AdminOnlineUserForceOfflineSessionInvalidationTest` 覆盖 SSO 缓存缺失拒绝、控制器清理 SSO 状??佸拰鏈?终清单记录???

### 剩余边界
- `user_onlines` 当前仍无 session_id銆乨evice_id 鎴? token 维度字段，所以本轮不能声明已经支持单设备下线???
- 单设备下线???设备维度展示和缓存/心跳精细口径仍需继续迁移???
- 本机 MySQL `127.0.0.1:3307` 当前不可连接，完??? DB 闭环恢复后需要再补真实数据库场景下的强制下线接口测试???
## 162. 2026-07-07 前台代理 parent_id 作用域兜底闭环

### 本次处理目标
- 补齐前台代理商模块在旧数据迁移场景下的可见范围兜底：不能只依??? `agent_descendants` 闭包表???
- 当旧项目导入数据只有 `user_infos.parent_id` 关系时，前台代理下级、直属客户???资金流水???返佣和持仓等共享作用域仍应能识别当前代理树???
- 保持现有 `agent_descendants` 为优先来源，同时??? `user_infos.parent_id` 递归结果合并进去，避免同???代理树规则在多个控制器里重复实现???

### 本次变更文件
- `app/Support/FrontLegacyData.php`
  - 灏? `FrontLegacyData::userScopeIds` 拆成 `descendantScopeIds` 涓? `parentTreeScopeIds` 两个来源???
  - `descendantScopeIds` 继续读取 `agent_descendants`锛屼繚鐣? `descendant_type` 鍜? `is_direct` 过滤???
  - `parentTreeScopeIds` 递归读取 `user_infos.parent_id`锛屽苟鎸? `account_type`銆佺洿灞?/间接层级口径补充兜底 ID銆?
  - 鏈?终作用域通过 `array_merge($ids, $fallbackIds)` 合并并去重???
- `app/Http/Controllers/Front/AgentController.php`
  - `canViewUser()` 改为复用 `FrontLegacyData::userScopeIds($currentUserId, false)`，让用户详情、层级路径???登录历史和客户组别变更共享同一套代理树边界???
  - 客户组别变更提交改为调用 `canViewUser()`，避免只??? `agent_descendants` 导致 parent_id 迁移数据无法提交???
- `tests/Feature/FrontAgentScopeFallbackModuleTest.php`
  - 新增前台代理作用域兜底契约测试???
  - 覆盖共享 helper 合并 `agent_descendants` 涓? `user_infos.parent_id`，以及代理控制器可见性判断复用共享作用域???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentScopeFallbackModuleTest.php --filter test_front_legacy_data_scope_merges_descendant_table_with_parent_tree_fallback` 首次失败，命??? `FrontLegacyData::userScopeIds` 仍只读取 `agent_descendants`銆?
- GREEN锛氳ˉ鍏? `descendantScopeIds`銆乣parentTreeScopeIds` 鍜? `collectParentTreeScopeIds`，让共享作用域同时合并闭包表??? `user_infos.parent_id` 递归结果???
- GREEN锛歚vendor\bin\phpunit tests\Feature\FrontAgentScopeFallbackModuleTest.php --filter test_front_agent_visibility_uses_shared_scope_fallback` 通过，确??? `AgentController::canViewUser()` 和组别变更权限判断已复用共享作用域???
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentScopeFallbackModuleTest.php --filter test_final_checklist_records_front_agent_parent_tree_fallback` 首次失败，命中最终清单缺少本节???
- GREEN：追加本节记录当前前台代理作用域闭环和剩余边界???

### 当前证据
- `FrontLegacyData::userScopeIds` 已保??? `agent_descendants` 口径，并新增 `user_infos.parent_id` 递归兜底???
- `FrontLegacyData::userScopeIds` 瀵? `descendant_type` 涓? `directOnly` 的过滤同时作用于闭包表和 parent_id 兜底结果???
- `AgentController::canViewUser()` 已使用共享作用域，避免用户详情???组别变更等入口继续只依赖单???关系表???
- `FrontAgentScopeFallbackModuleTest` 覆盖本次共享 helper 与代理控制器静???契约???

### 剩余边界
- 本轮没有修改数据库结构，也没有重??? `agent_descendants` 鎴? `family_tree` 数据???
- 真实数据库恢复后仍需补充代理下级列表、直属客户列表、资金流水和持仓汇总的接口级回归???
- 当前本机 MySQL `127.0.0.1:3307` 仍不可连接，暂不能运行依赖真实代理树数据的完整闭环测试???
## 163. 2026-07-07 前台持仓汇总 parent_id 作用域兜底闭环

### 本次处理目标
- 继续补齐??? 162 节留下的持仓汇???边界：前台备用 `FrontPositionSummaryController` 不能继续只依??? `agent_descendants` 闭包表???
- 当旧项目导入数据只有 `user_infos.parent_id` 关系时，持仓直属节点、汇总筛选???下级代理搜索和点击明细权限也应复用共享代理树作用域???
- 保持 `FrontLegacyData::userScopeIds` 为唯???前台共享作用域入口，避免资金流水、代理模块和持仓汇???各自维护不同的代理树规则???

### 本次变更文件
- `app/Http/Controllers/Front/PositionSummaryController.php`
  - 引入 `FrontLegacyData::userScopeIds`銆?
  - `index()` 的直属节点来源改??? `FrontLegacyData::userScopeIds($agentId, false, null, true)`銆?
  - 每个直属节点的汇总范围改??? `FrontLegacyData::userScopeIds((int) $child->user_id, true)`銆?
  - `search()` 的全量可见范围改??? `FrontLegacyData::userScopeIds($agentId, true)`銆?
  - `subSearch()` 的下级代理范围改??? `FrontLegacyData::userScopeIds($agentId, false, 1)`銆?
  - `clickSearch()` 的明细权限判断改??? `in_array((int) $targetUserId, FrontLegacyData::userScopeIds($agentId, false), true)`銆?
- `tests/Feature/FrontPositionSummaryScopeFallbackModuleTest.php`
  - 新增前台持仓汇???作用域兜底契约测试???
  - 覆盖控制器必须复用共??? helper，并禁止回???鍒? `AgentDescendant::where('agent_id', $agentId)` 单一路径???
  - 新增???终清单闭环记录测试???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontPositionSummaryScopeFallbackModuleTest.php --filter test_front_position_summary_uses_shared_parent_tree_scope_fallback` 首次失败，命??? `PositionSummaryController` 未导??? `FrontLegacyData`，仍直接查询 `AgentDescendant`銆?
- RED锛歚vendor\bin\phpunit tests\Feature\FrontPositionSummaryScopeFallbackModuleTest.php --filter test_final_checklist_records_front_position_summary_scope_fallback` 首次失败，命中最终清单缺少第 163 节???
- GREEN：控制器改为复用 `FrontLegacyData::userScopeIds`，并追加本节记录当前闭环证据和剩余边界???

### 当前证据
- `FrontPositionSummaryController` 的直属节点???子树汇总???全量搜索???下级代理搜索和点击明细权限已复??? `FrontLegacyData::userScopeIds`銆?
- `FrontLegacyData::userScopeIds` 同时合并 `agent_descendants` 鍜? `user_infos.parent_id`，因此备用持仓汇总控制器也获得旧迁移数据兜底???
- `FrontPositionSummaryScopeFallbackModuleTest` 覆盖本次控制器静态契约和???终清单记录???

### 剩余边界
- 本轮没有修改真实持仓聚合 SQL、分页结构???返回字段???数据库结构或旧前台路由???
- 当前本机 MySQL `127.0.0.1:3307` 仍不可连接，真实数据库恢复后仍需用代理账号验证备用持仓汇总接口的真实数据隔离、直属节点???下级代理和点击明细???
## 164. 2026-07-07 前台主持仓控制器 parent_id 作用域兜底闭环

### 本次处理目标
- 继续补齐前台主持??? `Front\PositionController` 的代理树边界，避免核心持仓汇总入口仍只读??? `agent_descendants`銆?
- 当旧项目导入数据只有 `user_infos.parent_id` 关系时，持仓汇???钻取???直属代理???直属下级???旧搜索范围和点击明细权限也必须复用共享作用域???
- 保持真实交易查询、汇总字段???分页结构和旧前台路由不变，只统???代理/客户可见范围来源???

### 本次变更文件
- `app/Http/Controllers/Front/PositionController.php`
  - 删除 `use App\Models\AgentDescendant;`銆?
  - `resolveSummaryTargetId()` 改为 `in_array($targetId, FrontLegacyData::userScopeIds($agentId, false, 1), true)`，校验目标代理是否属于当前代理树???
  - `directAgentIds()` 改为 `FrontLegacyData::userScopeIds($agentId, false, 1, true)`銆?
  - `directDescendantIds()` 改为 `FrontLegacyData::userScopeIds($agentId, false, null, true)`銆?
  - `search()` 的聚合范围改??? `FrontLegacyData::userScopeIds($agentId, true)`銆?
  - `positionDetail()` 的明细权限判断改??? `in_array((int) $targetUserId, FrontLegacyData::userScopeIds($agentId, false), true)`銆?
- `tests/Feature/FrontPositionScopeFallbackModuleTest.php`
  - 新增前台主持仓控制器作用域兜底契约测试???
  - 覆盖钻取、直属代理???直属下级???旧搜索、点击明细五条路径必须复用共??? helper，并禁止 `AgentDescendant::where` 回流???
  - 新增???终清单闭环记录测试???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontPositionScopeFallbackModuleTest.php --filter test_front_position_controller_uses_shared_parent_tree_scope_fallback` 首次失败，命??? `PositionController` 仍保??? `AgentDescendant::where` 直接查询???
- RED锛歚vendor\bin\phpunit tests\Feature\FrontPositionScopeFallbackModuleTest.php --filter test_final_checklist_records_front_position_controller_scope_fallback` 首次失败，命中最终清单缺少第 164 节???
- GREEN：控制器统一复用 `FrontLegacyData::userScopeIds`，并追加本节记录当前闭环证据和剩余边界???

### 当前证据
- `Front\PositionController` 的钻取目标校验???直属代理???直属下级???旧搜索范围和点击明细权限已复用 `FrontLegacyData::userScopeIds`銆?
- `FrontLegacyData::userScopeIds` 已合??? `agent_descendants` 涓? `user_infos.parent_id`，因此主持仓控制器也能兼容旧项目 parent_id 迁移数据???
- `FrontPositionScopeFallbackModuleTest` 覆盖本次控制器静态契约和???终清单记录???

### 剩余边界
- 本轮没有改动交易聚合 SQL銆丮T4 COMMENT 识别、品种分组???返佣汇总???分页结构???返回字段或数据库结构???
- 当前本机 MySQL `127.0.0.1:3307` 仍不可连接，真实数据库恢复后仍需用代理账号和普???客户账号验??? `/api/front/positions/summary`銆乣/api/front/positions/direct-agent-summaries`銆乣/api/front/positions/trades` 和旧 `user/position/*` 路由的数据隔离???
## 165. 2026-07-07 前台客户列表 parent_id 作用域兜底闭环

### 本次处理目标
- 继续补齐前台客户兼容控制器的代理树边界，避免客户列表和客户统计只依赖 `agent_descendants`銆?
- 当旧项目导入数据只有 `user_infos.parent_id` 关系时，当前代理仍能看到共享作用域内的普通客户，并继续支持直属客户筛选和客户名称筛??夈??
- 保持客户交易统计口径不变，只统一客户 ID 范围来源???

### 本次变更文件
- `app/Http/Controllers/Front/CustomerController.php`
  - 删除 `AgentDescendant` 直接依赖???
  - 引入 `UserInfo` 涓? `FrontLegacyData`銆?
  - `myCustomers()` 改为通过 `FrontLegacyData::userScopeIds($agentId, false, 2, $directOnly ? true : null)` 获取客户范围，再??? `user_infos` 读取客户资料???
  - `stats()` 改为通过 `FrontLegacyData::userScopeIds($agentId, false, 2)` 获取客户统计范围???
  - 分页记录继续追加 `descendant_id`銆乣descendant_type`銆乣is_direct`銆乣descendant` 鍜? `trade_stats`，兼容旧客户关系列表常见字段???
- `tests/Feature/FrontCustomerScopeFallbackModuleTest.php`
  - 新增前台客户控制器作用域兜底契约测试???
  - 覆盖控制器必须复??? `FrontLegacyData::userScopeIds`，并禁止 `AgentDescendant::where('agent_id', $agentId)` 回流???
  - 新增???终清单闭环记录测试???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontCustomerScopeFallbackModuleTest.php --filter test_front_customer_controller_uses_shared_parent_tree_scope_fallback` 首次失败，命??? `CustomerController` 仍保??? `AgentDescendant` 直接查询???
- RED锛歚vendor\bin\phpunit tests\Feature\FrontCustomerScopeFallbackModuleTest.php --filter test_final_checklist_records_front_customer_scope_fallback` 首次失败，命中最终清单缺少第 165 节???
- GREEN：控制器客户列表与统计统???复用 `FrontLegacyData::userScopeIds`，并追加本节记录当前闭环证据和剩余边界???

### 当前证据
- `Front\CustomerController` 的客户列表和客户统计已复??? `FrontLegacyData::userScopeIds`銆?
- `FrontLegacyData::userScopeIds` 已合??? `agent_descendants` 涓? `user_infos.parent_id`，因此前台客户兼容控制器也能兼容旧项??? parent_id 迁移数据???
- `FrontCustomerScopeFallbackModuleTest` 覆盖本次控制器静态契约和???终清单记录???

### 剩余边界
- 本轮没有改动真实交易统计 SQL、分页字段名、旧前台路由绑定、数据库结构??? `AgentController@customerList` 主客户列表入口???
- 当前本机 MySQL `127.0.0.1:3307` 仍不可连接，真实数据库恢复后仍需用代理账号验??? `CustomerController@myCustomers`銆乣CustomerController@stats` 以及主客户列??? `/api/front/agents/direct-customers` 的数据隔离???
## 166. 2026-07-07 前台首页 parent_id 作用域兜底闭环

### 本次处理目标
- 继续补齐前台首页代理树边界，避免首页月度入金、出金???订单聚合和代理/客户数量统计只依??? `agent_descendants`銆?
- 当旧项目导入数据只有 `user_infos.parent_id` 关系时，代理首页仍能把当前代理网络内的直???/间接下级纳入统计???
- 保持首页返回字段、统计窗口???新闻公告???下载配置和注册链接结构不变，只统一代理树作用域来源???

### 本次变更文件
- `app/Http/Controllers/Front/DashboardController.php`
  - 删除 `AgentDescendant` 直接依赖???
  - 引入 `FrontLegacyData`銆?
  - `dashboardData()` 鐨? `$descendantIds` 改为通过 `FrontLegacyData::userScopeIds($userId, false)` 获取，继续与当前用户 ID 合并??? `$scopeUserIds` 后用于月度入金???出金和订单聚合???
- `app/Services/FamilyTreeService.php`
  - 引入 `FrontLegacyData`銆?
  - `FamilyTreeService::getSubAgentStats()` 的直???/全部代理和直???/全部客户数量改为通过 `FrontLegacyData::userScopeIds` 统计???
  - 返回??? `direct_agents`銆乣indirect_agents`銆乣total_agents`銆乣direct_customers`銆乣indirect_customers`銆乣total_customers` 保持不变???
- `tests/Feature/FrontDashboardScopeFallbackModuleTest.php`
  - 新增前台首页作用域兜底契约测试???
  - 覆盖控制器月度聚合范围和首页代理/客户数量统计必须复用 `FrontLegacyData::userScopeIds`銆?
  - 新增???终清单闭环记录测试???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontDashboardScopeFallbackModuleTest.php --filter test_front_dashboard_uses_shared_parent_tree_scope_for_monthly_metrics` 首次失败，命??? `DashboardController` 仍保??? `AgentDescendant` 直接查询???
- RED锛歚vendor\bin\phpunit tests\Feature\FrontDashboardScopeFallbackModuleTest.php --filter test_family_tree_dashboard_stats_use_shared_parent_tree_scope_fallback` 首次失败，命??? `FamilyTreeService::getSubAgentStats` 仍直接按 `agent_descendants` 计数???
- RED锛歚vendor\bin\phpunit tests\Feature\FrontDashboardScopeFallbackModuleTest.php --filter test_final_checklist_records_front_dashboard_scope_fallback` 首次失败，命中最终清单缺少第 166 节???
- GREEN：首页月度聚合范围和层级统计统一复用 `FrontLegacyData::userScopeIds`，并追加本节记录当前闭环证据和剩余边界???

### 当前证据
- `Front\DashboardController` 的首页月度资金和订单统计范围已复??? `FrontLegacyData::userScopeIds`銆?
- `FamilyTreeService::getSubAgentStats` 的代???/客户数量统计已复??? `FrontLegacyData::userScopeIds`銆?
- `FrontLegacyData::userScopeIds` 已合??? `agent_descendants` 涓? `user_infos.parent_id`，因此前台首页也能兼容旧项目 parent_id 迁移数据???
- `FrontDashboardScopeFallbackModuleTest` 覆盖本次控制器???服务统计和???终清单记录???

### 剩余边界
- 本轮没有改动首页新闻、多语言、下载配置???注册链接???返佣金额统计???数据库结构或真实交易聚??? SQL銆?
- `FamilyTreeService` 其他网络树???团队统计和重建能力仍保留原有闭包表职责；后续应按具体入口继续补独立闭环???
- 当前本机 MySQL `127.0.0.1:3307` 仍不可连接，真实数据库恢复后仍需用代理账号验??? `/api/front/dashboard` 首页统计卡片的数据隔离和 parent_id 迁移数据兼容???
## 167. 2026-07-07 前台返佣转账 parent_id 作用域兜底闭???

### 本次处理目标
- 补齐前台返佣转账的代理树边界，避免下拉???项和提交校验只依赖 `agent_descendants`銆?
- 当旧项目导入数据只有 `user_infos.parent_id` 关系时，当前代理仍只能向直属下级代理转账，并能正常看到直属代理名称和等级???
- 保持转账流水、余额扣增???返回字段???前端路由和旧前台表单结构不变，只统???直属代理作用域来源???

### 本次变更文件
- `app/Http/Controllers/Front/CommissionController.php`
  - 删除 `AgentDescendant` 直接依赖???
  - `transferAgentOptions` 改为通过 `FrontLegacyData::userScopeIds($agentId, false, 1, true)` 获取直属代理 ID锛屽啀浠? `user_infos` 读取下拉展示资料???
  - 选项继续返回 `value`銆乣label`銆乣user_id`銆乣user_name` 鍜? `agent_level_name`锛屽吋瀹? Layui 涓? Naive 前端动??侀?夐」銆?
  - `transfer()` 的直属下级代理校验改为复用同???组共享作用域 ID，避免下拉和提交使用两套代理树规则???
- `tests/Feature/FrontCommissionScopeFallbackModuleTest.php`
  - 新增前台返佣转账作用域兜底契约测试???
  - 覆盖下拉选项和提交校验必须复??? `FrontLegacyData::userScopeIds`，并禁止 `AgentDescendant::where('agent_id', $agentId)` 回流???
  - 新增???终清单闭环记录测试???
- `tests/Feature/FrontUiRegressionTest.php`
  - 同步返佣转账动???下拉静态断???，继续约束前端路由???动态???项字段和标签组成，同时接受共享作用域实现???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontCommissionScopeFallbackModuleTest.php --filter test_front_commission_transfer_uses_shared_parent_tree_scope_fallback` 首次失败，命??? `CommissionController` 仍保??? `AgentDescendant` import 和直接查询???
- RED锛歚vendor\bin\phpunit tests\Feature\FrontCommissionScopeFallbackModuleTest.php --filter test_final_checklist_records_front_commission_transfer_scope_fallback` 首次失败，命中最终清单缺少第 167 节???
- GREEN：返佣转账下拉???项和提交校验统???复用 `FrontLegacyData::userScopeIds`，并追加本节记录当前闭环证据和剩余边界???

### 当前证据
- `Front\CommissionController::transferAgentOptions` 已???过 `FrontLegacyData::userScopeIds($agentId, false, 1, true)` 获取直属代理作用域???
- 下拉展示资料已从 `UserInfo::with('level')` 读取，并保留 `value`銆乣label`銆乣user_id`銆乣user_name`銆乣agent_level_name` 字段???
- `Front\CommissionController::transfer` 已用同一组直属代??? ID 鍋? `sub_agent_id` 权限校验???
- `FrontCommissionScopeFallbackModuleTest` 覆盖本次控制器静态契约和???终清单记录???

### 剩余边界
- 本轮没有改动佣金转账事务、余额扣增??乣commission_records` 写入字段、前端表单结构???数据库结构或真实佣金统计口径???
- 当前本机 MySQL `127.0.0.1:3307` 仍不可连接，真实数据库恢复后仍需用代理账号验??? `/api/front/commissions/transfer-agent-options` 鍜? `/api/front/commissions/transfers` 的真实数据隔离与转账闭环???
- 控制器标记：`Front\CommissionController`

## 168. 2026-07-07 前台大代??? parent_id 作用域兜底闭???

### 本次处理目标
- 补齐前台大代理旧入口的代理树边界，避免大代理代理网络和订单客户范围只依赖 `agent_descendants`銆?
- 当旧项目导入数据只有 `user_infos.parent_id` 关系时，`big_agents.sub_agent_ids` 指定的直属代理仍能展???其下级代理网络和客户订单范围???
- 保持大代理登录???旧前台页面、列表返回结构???订单状??? scope、分页字段和统计 footer 不变，只统一代理/客户 ID 作用域来源???

### 本次变更文件
- `app/Http/Controllers/Front/BigNumberController.php`Front\BigNumberController`锛?
  - `subAgentIdsForRequest` 在需要包含下级代理网络时，改为对 `big_agents.sub_agent_ids` 中的直属代理逐个调用 `FrontLegacyData::userScopeIds($subAgentId, false, 1)`銆?
  - `legacyOrderListResponse` 的客户订单范围改为对可见代理 ID 调用 `FrontLegacyData::userScopeIds($agentId, false, 2)`銆?
  - 删除大代理旧入口中两??? `\App\Models\AgentDescendant::whereIn` 直接查询，让闭包表和 `user_infos.parent_id` 兜底统一走共??? helper銆?
- `tests/Feature/FrontBigNumberScopeFallbackModuleTest.php`
  - 新增前台大代理作用域兜底契约测试???
  - 覆盖代理网络展开和订单客户范围必须复??? `FrontLegacyData::userScopeIds`，并禁止大代理控制器回???鍒? `AgentDescendant::whereIn`銆?
  - 新增???终清单闭环记录测试???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontBigNumberScopeFallbackModuleTest.php --filter test_front_big_number_controller_uses_shared_parent_tree_scope_fallback` 首次失败，命??? `BigNumberController::subAgentIdsForRequest` 仍直接查??? `AgentDescendant::whereIn`銆?
- RED锛歚vendor\bin\phpunit tests\Feature\FrontBigNumberScopeFallbackModuleTest.php --filter test_final_checklist_records_front_big_number_scope_fallback` 首次失败，命中最终清单缺少第 168 节???
- GREEN：大代理代理网络展开和订单客户范围统???复用 `FrontLegacyData::userScopeIds`，并追加本节记录当前闭环证据和剩余边界???

### 当前证据
- `Front\BigNumberController::subAgentIdsForRequest` 已???过 `FrontLegacyData::userScopeIds($subAgentId, false, 1)` 展开直属代理的下级代理网络???
- `Front\BigNumberController::legacyOrderListResponse` 已???过 `FrontLegacyData::userScopeIds($agentId, false, 2)` 获取可见客户订单范围???
- `big_agents.sub_agent_ids` 仍是大代理可见直属代理的入口配置，范围展???统一兼容 `agent_descendants` 鍜? `user_infos.parent_id`銆?
- `FrontBigNumberScopeFallbackModuleTest` 覆盖本次控制器静态契约和???终清单记录???

### 剩余边界
- 本轮没有改动大代理登录???密码修改???列表返回结构???真实订单聚合??乣UserTrade::open()` / `UserTrade::closed()` 状??? scope、数据库结构??? `big_agents.sub_agent_ids` 配置来源???
- 当前本机 MySQL `127.0.0.1:3307` 仍不可连接，真实数据库恢复后仍需用大代理账号验证??? `/user/agents/*` 代理列表、持仓汇总???未平仓订单和已平仓订单的数据隔离???
## 169. 2026-07-07 前台代理/客户主列??? parent_id 作用域兜底闭???

### 本次处理目标
- 补齐 `Front\AgentController` 主代理列表和主客户列表的代理树边界，避免 `/api/front/agents/direct` 涓? `/api/front/agents/direct-customers` 只依??? `agent_descendants`銆?
- 当旧项目导入数据只有 `user_infos.parent_id` 关系时，当前代理仍能展开下级代理、直属代理???客户列表和直属客户筛??夈??
- 保持旧前台字??? `depth`銆乣is_direct`銆乣descendant`銆乣can_drill_agents`銆乣can_drill_customers`銆乣comm_trans`銆乣change_group`銆乣available_groups` 与分???/汇???结构不变???

### 本次变更文件
- `app/Http/Controllers/Front/AgentController.php`锛坄Front\AgentController`锛?
  - `subList` 改为通过 `FrontLegacyData::userScopeIds($queryAgentId, false, 1, $directOnly ? true : null)` 获取代理范围，再??? `user_infos` 读取代理资料???
  - `customerList` 改为通过 `FrontLegacyData::userScopeIds($queryAgentId, false, 2, $directOnly ? true : null)` 获取客户范围，再??? `user_infos` 读取客户资料???
  - `scopeDepth` 鐢? `user_infos.family_tree` 推导旧字??? `depth`，缺链路时非直属??? 2 兜底，避免为了展示字段继续依赖闭包表???
  - 两个主列表继续保留旧前台??? Naive 依赖的兼容字段???统计字段???客户组别???项和汇??? footer銆?
- `tests/Feature/FrontAgentMainListScopeFallbackModuleTest.php`
  - 新增前台代理/客户主列表作用域兜底契约测试???
  - 覆盖 `subList` 涓? `customerList` 必须复用 `FrontLegacyData::userScopeIds`，并禁止这两个方法回???鍒? `AgentDescendant::query()`銆?
  - 新增???终清单闭环记录测试???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentMainListScopeFallbackModuleTest.php --filter test_front_agent_main_lists_use_shared_parent_tree_scope_fallback` 首次失败，命??? `subList` 鍜? `customerList` 仍直接使??? `AgentDescendant::query()`銆?
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentMainListScopeFallbackModuleTest.php --filter test_final_checklist_records_front_agent_main_list_scope_fallback` 首次失败，命中最终清单缺少第 169 节???
- GREEN锛氫唬鐞?/客户主列表统???复用 `FrontLegacyData::userScopeIds`，并追加本节记录当前闭环证据和剩余边界???

### 当前证据
- `Front\AgentController::subList` 已???过 `FrontLegacyData::userScopeIds` 同时兼容 `agent_descendants` 鍜? `user_infos.parent_id` 的下级代理范围???
- `Front\AgentController::customerList` 已???过 `FrontLegacyData::userScopeIds` 同时兼容 `agent_descendants` 鍜? `user_infos.parent_id` 的客户范围???
- `scopeDepth` 保留旧列??? `depth` 字段，`is_direct` 鐢? `user_infos.parent_id` 判定，避免主列表为展示字段继续绑定单???关系表???
- `FrontAgentMainListScopeFallbackModuleTest` 覆盖本次控制器静态契约和???终清单记录???

### 剩余边界
- 本轮没有改动前台等级确认、旧佣金转账、层级路径???登录历史???详情弹层???数据库结构??? `FamilyTreeService` 重建闭包表能力???
- 当前本机 MySQL `127.0.0.1:3307` 仍不可连接，真实数据库恢复后仍需用代理账号验??? `/api/front/agents/direct`銆乣/api/front/agents/direct-customers` 和旧 `user/proxy/*`銆乣user/cust/*` 路由的数据隔离与点击钻取???
## 170. 2026-07-08 前台代理等级确认 parent_id 作用域兜底闭???

### 本次处理目标
- 补齐 `Front\AgentController` 等级确认列表和确认提交的直属代理边界，避??? `confirmLevel` 涓? `confirmLevelChange` 鍦? `agent_descendants` 存在部分旧数据时遮蔽 `user_infos.parent_id` 迁移关系???
- 当旧项目导入数据只有 `user_infos.parent_id` 关系或闭包表不完整时，当前代理仍能看到并确认真实直属下级代理等级???
- 保持等级候??夈??返佣比例计算???提交字段???旧前台路由??? Naive/Layui 前端结构不变，只统一直属代理 ID 来源???

### 本次变更文件
- `app/Http/Controllers/Front/AgentController.php`锛坄Front\AgentController`锛?
  - `confirmLevel` 改为通过 `FrontLegacyData::userScopeIds((int) $userInfo->user_id, false, 1, true)` 获取直属代理范围???
  - `confirmLevelChange` 改为通过 `FrontLegacyData::userScopeIds($agentId, false, 1, true)` 校验待确认代理，避免闭包表和 parent_id 使用两套规则???
  - 继续??? `agent_levels.user_commission` 计算确认后的真实返佣比例，不信任前端提交??? `comm_prop`銆?
- `tests/Feature/FrontAgentLevelConfirmationScopeFallbackModuleTest.php`
  - 新增前台代理等级确认作用域兜底契约测试???
  - 覆盖 `confirmLevel` 涓? `confirmLevelChange` 必须复用 `FrontLegacyData::userScopeIds`，并禁止这两个方法回???到单独查??? `AgentDescendant` 鎴? `user_infos.parent_id`銆?
  - 新增???终清单闭环记录测试???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentLevelConfirmationScopeFallbackModuleTest.php` 首次失败，命??? `confirmLevel` 仍直接查??? `AgentDescendant` 且清单缺少第 170 节???
- GREEN：等级确认列表和提交校验统一复用 `FrontLegacyData::userScopeIds`，并追加本节记录当前闭环证据和剩余边界???

### 当前证据
- `Front\AgentController::confirmLevel` 已???过 `FrontLegacyData::userScopeIds` 同时兼容 `agent_descendants` 鍜? `user_infos.parent_id` 的直属下级代理范围???
- `Front\AgentController::confirmLevelChange` 已用同一组直属代??? ID 鍋? `userId` 权限校验???
- `FrontAgentLevelConfirmationScopeFallbackModuleTest` 覆盖本次控制器静态契约和???终清单记录???

### 剩余边界
- 本轮没有改动等级候???列表???等级确认写入字段???返佣比例计算来源???前端提交结构???数据库结构??? `FamilyTreeService` 重建闭包表能力???
- 当前本机 MySQL `127.0.0.1:3307` 仍不可连接，真实数据库恢复后仍需用代理账号验??? `/api/front/agents/level-confirmation`銆乣/api/front/agents/level-confirmation/changes` 和旧 `user/proxy/proxyConfirmSearch`銆乣user/proxy/confirmLevelChange` 的真实数据隔离与确认提交闭环???
## 171. 2026-07-08 前台旧佣金转账目标校??? parent_id 作用域兜底闭???

### 本次处理目标
- 补齐 `Front\AgentController` 旧前台直属用户佣金转账目标校验，避免 `directUserCommTrans` 仍???过独立??? `AgentDescendant` 查询??? `user_infos.parent_id` 查询维护第二套直属关系规则???
- 当旧项目导入数据只有 `user_infos.parent_id` 关系??? `agent_descendants` 不完整时，旧前台佣金转账仍只允许当前代理向真实直属下级转账???
- 保持转账金额校验、密码校验???余额扣增??乣commission_records` 写入字段和旧前台响应结构不变，只统一目标用户权限边界???

### 本次变更文件
- `app/Http/Controllers/Front/AgentController.php`锛坄Front\AgentController`锛?
  - 删除 `AgentDescendant` import銆?
  - `isDirectTransferTarget` 改为通过 `FrontLegacyData::userScopeIds($agentId, false, null, true)` 判断目标用户是否为直属下级???
  - `directUserCommTrans` 继续复用 `isDirectTransferTarget`，保持原有转账事务和响应格式???
- `tests/Feature/FrontAgentDirectTransferScopeFallbackModuleTest.php`
  - 新增前台旧佣金转账目标校验作用域兜底契约测试???
  - 覆盖 `AgentController` 不再 import `AgentDescendant`，且 `isDirectTransferTarget` 必须复用 `FrontLegacyData::userScopeIds`銆?
  - 新增???终清单闭环记录测试???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentDirectTransferScopeFallbackModuleTest.php` 首次失败，命??? `AgentController` 浠? import `AgentDescendant` 且清单缺少第 171 节???
- GREEN：旧佣金转账目标校验统一复用 `FrontLegacyData::userScopeIds`，并追加本节记录当前闭环证据和剩余边界???

### 当前证据
- `Front\AgentController::isDirectTransferTarget` 已???过 `FrontLegacyData::userScopeIds($agentId, false, null, true)` 同时兼容 `agent_descendants` 鍜? `user_infos.parent_id` 的直属下级范围???
- `Front\AgentController::directUserCommTrans` 保持调用 `isDirectTransferTarget` 作为目标用户权限门禁???
- `FrontAgentDirectTransferScopeFallbackModuleTest` 覆盖本次控制器静态契约和???终清单记录???

### 剩余边界
- 本轮没有改动旧佣金转账事务???余额扣增???密码校验??乣commission_records` 写入字段、前端表单结构或数据库结构???
- 当前 MySQL `127.0.0.1:3307` 已恢复连通；本节仍需结合真实代理账号继续扩展??? `user/proxy/directUserCommTrans` 成功/拒绝写入级闭环测试???
## 172. 2026-07-09 前台返佣计算服务 parent_id 作用域兜底闭???

### 本次处理目标
- 补齐 `CommissionService` 的返佣计算范围，避免 `calculateRealTimeCommission` 涓? `calculateSettlement` 继续只读??? `agent_descendants`銆?
- 当旧项目导入数据只有 `user_infos.parent_id` 关系??? `agent_descendants` 不完整时，实时返佣和待结算返佣仍能纳入当前代理树下真实用户订单???
- 保持返佣公式、订单开平仓 scope、结算状态过滤??乣commission_records` 写入字段和现有返回结构不变，只统???下级用户 ID 范围来源???

### 本次变更文件
- `app/Services/CommissionService.php`锛坄CommissionService`锛?
  - `calculateRealTimeCommission` 改为通过 `FrontLegacyData::userScopeIds($agentId, false)` 获取当前代理可见下级用户范围???
  - `calculateSettlement` 改为通过同一共享范围获取待结算平仓订单用户范围???
  - 继续复用 `UserTrade::open()` 涓? `UserTrade::closed()`，不重复维护 MT4 寮?平仓哨兵条件???
- `tests/Feature/FrontCommissionServiceScopeFallbackModuleTest.php`
  - 新增前台返佣计算服务作用域兜底契约测试???
  - 覆盖实时返佣与结算计算必须复??? `FrontLegacyData::userScopeIds`，并禁止回???鍒? `DB::table('agent_descendants')`銆?
  - 新增???终清单闭环记录测试???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontCommissionServiceScopeFallbackModuleTest.php` 首次失败，命??? `CommissionService` 仍直接查??? `agent_descendants` 且清单缺少第 172 节???
- GREEN：返佣计算服务的实时返佣和结算范围统???复用 `FrontLegacyData::userScopeIds`，并追加本节记录当前闭环证据和剩余边界???

### 当前证据
- `CommissionService::calculateRealTimeCommission` 已???过 `FrontLegacyData::userScopeIds($agentId, false)` 获取用户范围，并继续调用 `UserTrade::open()`銆?
- `CommissionService::calculateSettlement` 已???过 `FrontLegacyData::userScopeIds($agentId, false)` 获取用户范围，并继续调用 `UserTrade::closed()` 涓? `settlement_status=0`銆?
- `FrontCommissionServiceScopeFallbackModuleTest` 覆盖本次服务静???契约和???终清单记录???

### 剩余边界
- 本轮没有改动返佣公式、点差配置读取???结算记录写入字段???订单状??? scope、数据库结构或前端展示字段???
- 当前 MySQL `127.0.0.1:3307` 已恢复连通；后续仍需扩展真实订单级计算样例，覆盖 parent_id-only 代理树下实时返佣金额与结算记录写入闭环???
## 173. 2026-07-09 后台管理员数据范??? parent_id 作用域兜底闭???
### 本次处理目标
- 补齐后台管理??? `agent_tree` / `custom_agents` 数据范围的代理树兼容性，避免只读??? `agent_descendants` 导致旧项目导入的 `user_infos.parent_id` 关系不可见???
- 当绑定代理树只有 `user_infos.parent_id` 层级、没有闭包表记录时，后台用户列表范围和单条详???/审核/处理权限判断仍应只放行该代理树下真实客户，拒绝其它代理树客户???
- 保持原有数据范围语义：起始代??? ID 仍并入可??? ID锛宍targetType=agent` 仍取代理后代，其它用???/资金/交易目标仍取客户后代；本轮只统一后代来源???

### 本次变更文件
- `app/Services/AdminDataScopeService.php`
  - 删除??? `AgentDescendant` 的直接查询依赖，引入 `FrontLegacyData::userScopeIds` 作为代理树后代解析入口???
  - `resolveAgentTreeUserIds()` 先过滤正整数代理 ID，避免把配置中的 `0` 当作 `parent_id=0` 根节点展???銆?
  - 每个起始代理通过 `FrontLegacyData::userScopeIds($agentId, false, $descendantType)` 合并闭包表和 `user_infos.parent_id` 后代，再保持与起始代??? ID 合并返回???
- `tests/Feature/AdminDataScopeServiceTest.php`
  - 新增 parent_id-only 后台数据范围 RED/GREEN 用例，覆盖列??? `apply()` 与单??? `canAccessUser()` 两条路径???
  - 扩展 `createUserInfo()` 测试 helper，支持构??? `account_type` 涓? `parent_id` fixture銆?
- `tests/Feature/AdminDataScopeControllerWiringTest.php`
  - 修正 `AdminUserController` 测试桩构造参数，按真实构造函数传??? `UserStatisticsService`，避免接线测试因过期测试桩误报???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\AdminDataScopeServiceTest.php --filter parent_id` 首次失败，列表路径返回空数组，单条访问路径返??? false，证明旧服务只依??? `agent_descendants`銆?
- GREEN锛歚AdminDataScopeService` 改为复用 `FrontLegacyData::userScopeIds` 后，同一 parent_id-only 用例通过???
- 调试修正：`AdminDataScopeControllerWiringTest` 暴露测试桩仍按旧构???函数实例化 `AdminUserController`，已按真实依赖补齐，不改生产业务逻辑???

### 当前证据
- `AdminDataScopeService::resolveAgentTreeUserIds` 已同时兼??? `agent_descendants` 涓? `user_infos.parent_id`，后台角色绑定代理树、单条权限校验和自定义代理范围共用同???解析路径???
- `AdminDataScopeServiceTest` 覆盖闭包表路径和 parent_id-only 迁移数据路径???
- 已验证后台数据范围???控制器接线、迁移缺口审计???用户统计???代理统计???实时返佣和持仓汇???相关抽样???

### 剩余边界
- 本轮没有改动后台控制器查询字段???角色数据范围配置表、管理员代理绑定表???闭包表重建逻辑、资???/交易聚合 SQL 或前端展示字段???
- `FamilyTreeService` 的网络树、闭包表重建和后台代??? descendants 展示仍保留其闭包表职责；后续只在具体业务入口???瑕? parent_id 兜底时继续补独立闭环???
- 当前 MySQL `127.0.0.1:3307` 已恢复连通；后续仍应按真实后台账号继续扩展端到端接口级数据隔离样例???
## 174. 2026-07-09 前台代理统计 parent_id 作用域兜底闭环
### 本次处理目标
- 补齐 `FamilyTreeService::getAgentStats` 的下级用户统计范围，避免前台代理列表和代理统计详情继续只读取 `agent_descendants`銆?
- 当旧项目导入数据只有 `user_infos.parent_id` 关系、没有闭包表记录时，代理统计仍能纳入当前代理树下真实下级交易、活跃用户和新增注册???
- 保持交易量???盈亏???返佣金额???活跃用户???新增注册返回字段不变；本轮只统???下级用户 ID 范围来源???

### 本次变更文件
- `app/Services/FamilyTreeService.php`
  - `getAgentStats()` 鐨? `$descendantIds` 改为 `FrontLegacyData::userScopeIds($agentId, false)`銆?
  - 继续保留 `getAllDescendants()`銆乣getNetworkTree()` 鍜? `rebuildDescendants()` 的闭包表职责，不误删用于展示/重建的关系表能力???
  - 方法注释同步说明统计范围兼容闭包表与 `user_infos.parent_id` 导入关系???
- `tests/Feature/FrontFamilyTreeAgentStatsScopeFallbackModuleTest.php`
  - 新增 parent_id-only 服务级闭环测试，构???无 `agent_descendants` 行的代理树，并验??? `getAgentStats()` 能统计下级交易和注册???
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontFamilyTreeAgentStatsScopeFallbackModuleTest.php` 首次失败，`total_volume` 从期??? `300.0` 变成 `0.0`，证明旧 `getAgentStats()` 在闭包表缺失时漏??? parent_id-only 下级交易???
- GREEN锛歚FamilyTreeService::getAgentStats()` 改为复用 `FrontLegacyData::userScopeIds($agentId, false)` 后，同一服务级测试??氳繃銆?
- RED锛歚vendor\bin\phpunit tests\Feature\FrontFamilyTreeAgentStatsScopeFallbackModuleTest.php --filter final_checklist` 首次失败，命中最终清单缺少第 174 节???
- GREEN：追加本节记录后，清单测试??氳繃銆?

### 当前证据
- `FamilyTreeService::getAgentStats` 已同时兼??? `agent_descendants` 涓? `user_infos.parent_id` 的下级用户范围???
- `FrontFamilyTreeAgentStatsScopeFallbackModuleTest` 覆盖 parent_id-only 代理树下的交易量、盈亏???返佣金额???活跃用户和新增注册统计???
- 已验证前台首页作用域、前台代理作用域、前台代理主列表、旧前台路由兼容、FamilyTreeService 注释可读性和迁移缺口审计相关测试???

### 剩余边界
- 本轮没有改动 `FamilyTreeService::getAllDescendants()`銆乣getNetworkTree()`銆乣rebuildFamilyTree()`銆乣rebuildDescendants()`、真实交易聚合字段???返佣记录写入口径???数据库结构或前端展示字段???
- `FrontLegacyData::userScopeIds` 仍是当前共享作用域入口；后续若要??? `FamilyTreeService` 剩余网络树展示入口完??? parent_id 化，应按具体调用入口补单??? RED/GREEN銆?
- 当前 MySQL `127.0.0.1:3307` 已恢复连通；后续仍应补真实前台代理账号接口级统计隔离样例???
## 175. 2026-07-09 前台资料关系链 parent_id 祖先链兜底闭环
### 本次处理目标
- 补齐 `ProfileController::relationshipIds` 的祖先链兜底，避免资料页关系链接口在 `family_tree` 鍜? `agent_descendants` 都缺失时只返回目标用户自身???
- 当旧项目导入数据只有 `user_infos.parent_id` 关系时，`/api/front/profile/relationship-path`、旧 `user/relationShipHtml` 和代理关系链 HTML 入口仍能返回从上级代理到目标用户的完??? ID 链???
- 保持既有优先级：`user_infos.family_tree` 优先，其次保留旧闭包表回???；只有闭包表也无祖先行时，才??? `user_infos.parent_id` 向上组链???

### 本次变更文件
- `app/Http/Controllers/Front/ProfileController.php`
  - `relationshipIds()` 在闭包表无祖先行时回???鍒? `parentRelationshipIds()`銆?
  - 新增 `parentRelationshipIds()`，沿 `parent_id` 向上收集祖先 ID，并使用 visited 防止脏数据循环???
  - 关系链返回格式和三个公开入口 `relationShip`銆乣relationShipHtml`銆乣relationShipHtmlV2` 保持不变???
- `tests/Feature/FrontProfileRelationshipScopeFallbackModuleTest.php`
  - 新增 parent_id-only 关系链闭环测试，构???无 `family_tree`、无 `agent_descendants` 的代理树，验证关系链返回 `root -> sub -> customer`銆?
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontProfileRelationshipScopeFallbackModuleTest.php --filter parent_id_tree` 首次失败，实际返回目标用户自??? ID，证明旧逻辑没有 parent_id 祖先链兜底???
- GREEN锛歚ProfileController::relationshipIds()` 增加 `parentRelationshipIds()` 回???后，同一 parent_id-only 用例通过???
- RED锛歚vendor\bin\phpunit tests\Feature\FrontProfileRelationshipScopeFallbackModuleTest.php --filter final_checklist` 首次失败，命中最终清单缺少第 175 节???
- GREEN：追加本节记录后，清单测试??氳繃銆?

### 当前证据
- `ProfileController::relationshipIds` 已兼??? `family_tree`銆乣agent_descendants` 鍜? `user_infos.parent_id` 三种关系链来源???
- `FrontProfileRelationshipScopeFallbackModuleTest` 覆盖 parent_id-only 导入数据下的资料关系链输出???
- 已验??? ProfileController 注释可读性???旧前台路由兼容、资料路由静态契约和迁移缺口审计相关测试???

### 剩余边界
- 本轮没有改动头像、资料更新??佸疄鍚?/银行卡上传???销户验证???路由定义??佸搷搴? JSON 字段或前端展示??昏緫銆?
- 闭包表有祖先行时仍保留旧回???顺序；如后续要统???闭包表关系链顺序，应单独补历史兼容测试后再改???
- 当前 MySQL `127.0.0.1:3307` 已恢复连通；后续仍可补登录???接口级样例，覆盖真实前台账号调用关系链接口???
## 176. 2026-07-09 前台直属代理流水路由作用域闭???
### 本次处理目标
- 修复新版 `/api/front/flows/direct-agent-deposits`銆乣/api/front/flows/direct-agent-withdrawals` 与旧 `user/flow/directAgents*FlowSearch` 入口复用同一控制器方法时仍固定查询直属客户流水的问题???
- 当旧项目导入数据只有 `user_infos.parent_id` 关系、没??? `agent_descendants` 闭包表记录时，直属代理流水必须进??? `direct_agents_deposit` / `direct_agents_withdraw` 作用域，不能串到直属客户流水???
- 保持直属客户流水、分页结构???汇总字段???旧前台响应字段和前??? tab 路由不变，只补齐路由到流水类型的分流???

### 本次变更文件
- `app/Http/Controllers/Front/FlowController.php`
  - `FlowController::directDepositFlowSearch` 按当前路由名/路径判断直属代理入口，分别写??? `direct_agents_deposit` 鎴? `direct_deposit`銆?
  - `FlowController::directWithdrawalFlowSearch` 按同???规则写入 `direct_agents_withdraw` 鎴? `direct_withdraw`銆?
  - 新增 `isDirectAgentFlowRequest()`，同时兼容新??? `front_api_flows_direct_agent_*` 路由、旧 `legacy_user_flow_direct_agents_*` 路由和历史驼峰路径???
- `tests/Feature/FrontFlowDirectAgentRouteScopeModuleTest.php`
  - 新增接口级闭环测试，构??? parent_id-only 直属代理和直属客户，并写入真??? `deposit_records`銆乣withdraw_records`銆?
  - 验证直属代理路由只返回代理流水，直属客户路由只返回客户流水???
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontFlowDirectAgentRouteScopeModuleTest.php --filter direct_agent` 首次失败，直属代理入金和出金路由分别返回 `DCDEP-*`銆乣DCWDR-*` 直属客户订单号，证明路由分流缺失???
- GREEN锛歚FlowController` 根据路由???/路径选择 `direct_agents_deposit` 涓? `direct_agents_withdraw` 后，直属代理和直属客户两类接口级样例均??氳繃銆?
- RED：新增清单测试首次失败，命中???终清单缺少第 176 节???

### 当前证据
- `FlowController::directDepositFlowSearch` 已能区分直属客户入金与直属代理入金入口???
- `FlowController::directWithdrawalFlowSearch` 已能区分直属客户出金与直属代理出金入口???
- `FrontFlowDirectAgentRouteScopeModuleTest` 覆盖 parent_id-only 关系下的真实接口、真实流水表和前端使用的新版 API 路径???

### 剩余边界
- 本轮没有改动 `typedFlow()` 的分页???汇总???日期筛选???用户筛选???导出???旧前台字段映射或数据库结构???
- 鏃? web 路由通过同一??? route name/path 判断逻辑兼容；后续如要为直属代理流水拆独立控制器方法，应先更新路由兼容测试后再迁移???
## 177. 2026-07-09 前台账户综合 parent_id 客户范围兜底闭环

### 本次处理目标
- 补齐 `AccountController::accountOverviewData` 的客户范围统计，避免账户综合页在 `family_tree` 鍜? `agent_descendants` 缺失时漏??? parent_id-only 的间接客户???
- 当旧项目导入数据只有 `user_infos.parent_id` 关系时，直属代理数???直属客户数、间接客户数、客户???别画像和关系入金金额仍按同???代理树返回???
- 保持账户资金指标、订单统计???余额页复用结构、前端字段名和响应格式不变，只统???客户范围来源???

### 本次变更文件
- `app/Http/Controllers/Front/AccountController.php`
  - `AccountController::accountOverviewData` 改为通过 `FrontLegacyData::userScopeIds` 获取直属代理、直属客户和全部客户 ID銆?
  - `indirect_customers` 改为用全部客??? ID 扣除直属客户 ID，避免依??? `family_tree like`銆?
  - `relation_amount` 改为按全部客??? ID 汇??? `deposit_records.amount`銆?
  - `AccountController::customerGenderProfile` 改为接收客户 ID 数组，并按同???范围统计男女和未知???别占比???
- `tests/Feature/FrontAccountOverviewScopeFallbackModuleTest.php`
  - 新增接口级闭环测试，构???无 `agent_descendants`、无 `family_tree` 鐨? parent_id-only 代理树???
  - 验证 `/api/front/account/profile` 能返回直属代理???直属客户???间接客户???关系入金金额和客户性别画像???
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAccountOverviewScopeFallbackModuleTest.php` 首次失败，`indirect_customers` 实际??? `0`，证明旧账户综合页只靠直??? `parent_id` 鍜? `family_tree` 会漏掉间接客户???
- GREEN：账户综合页统一复用 `FrontLegacyData::userScopeIds` 后，parent_id-only 接口样例通过???
- RED：新增清单测试首次失败，命中???终清单缺少第 177 节???

### 当前证据
- `AccountController::accountOverviewData` 已???过 `FrontLegacyData::userScopeIds` 同时兼容 `agent_descendants` 涓? `user_infos.parent_id` 的客户范围???
- `AccountController::customerGenderProfile` 涓? `relation_amount` 已复用同???批全部客??? ID，避免???别画像和关系金额使用第二套范围???
- `FrontAccountOverviewScopeFallbackModuleTest` 覆盖 parent_id-only 关系下的真实接口、真实用户表和真实入金表???

### 剩余边界
- 本轮没有改动账户资金字段、开平仓订单统计、认证状态???组别展示???余额页路由、前端展示字段或数据库结构???
- `FrontLegacyData::userScopeIds` 仍是前台共享范围入口；后续如发现其它账户页入口仍手写 `family_tree` 鎴? `parent_id` 范围，应继续按独??? RED/GREEN 补齐???
- 当前 MySQL `127.0.0.1:3307` 已恢复连通；后续仍可补真实登录???账号的账户综合页端到端隔离样例???

## 178. 2026-07-09 前台订单链路 parent_id 兜底闭环

### 本次处理目标
- 补齐 `OrderController::orderChain` 的链路展示兜底，避免订单列表范围已能通过 `FrontLegacyData::userScopeIds` 命中 parent_id-only 客户订单，但返回??? `order_chain` 仍因缺少 `family_tree` 变成空链???
- 当旧项目导入数据只有 `user_infos.parent_id` 关系、没??? `family_tree` 鍜? `agent_descendants` 时，前台代理查看下级订单仍能看到当前代理到下级代理再到客户的完整订单链路???
- 保持订单查询范围、开平仓 scope、分页汇总???订单字段???详情弹层和返佣明细结构不变，只补齐链路展示 ID 来源???

### 本次变更文件
- `app/Http/Controllers/Front/OrderController.php`
  - `OrderController::orderChain` 改为先???过 `orderChainIds()` 获取链路 ID，再按当前查看代理截取可见链路???
  - 新增 `orderChainIds()`锛屼繚鐣? `family_tree` 优先；仅??? `family_tree` 为空时才回??? parent 链???
  - 新增 `parentOrderChainIds()`，沿 `user_infos.parent_id` 向上补齐祖先 ID，并使用 visited 防止脏数据循环???
- `tests/Feature/FrontOrderChainScopeFallbackModuleTest.php`
  - 新增接口级闭环测试，构???无 `agent_descendants`、无 `family_tree` 鐨? root agent -> sub agent -> customer 三层关系???
  - 验证 `/api/front/orders/closed` 能返回真实订单，并在 `order_chain` 中输??? root -> sub -> customer銆?
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontOrderChainScopeFallbackModuleTest.php` 首次有效失败，订单接口返回真实订单，??? `order_chain` 实际为空数组，证明旧 `OrderController::orderChain` 只依??? `family_tree`銆?
- GREEN锛歚OrderController::orderChain` 增加 parent 链兜底后，同??? parent_id-only 订单接口样例通过???
- RED：新增清单测试首次失败，命中???终清单缺少第 178 节???

### 当前证据
- 订单列表可见范围仍由 `FrontLegacyData::userScopeIds` 通过 `FrontLegacyData::applyAllowedUserFilter` 约束???
- `OrderController::orderChain` 已兼??? `family_tree` 涓? `user_infos.parent_id` 两种链路来源，且只返回当前查看代理节点之后的可见链路???
- `FrontOrderChainScopeFallbackModuleTest` 覆盖 parent_id-only 关系下的真实登录态???真实订单表和真实订单链路响应???

### 剩余边界
- 本轮没有改动订单查询过滤、开???/平仓判断、订单汇总???前端字段映射???详情弹??? HTML、返佣拆分计算或数据库结构???
- `CommissionController::orderChain` 仍有独立链路展示逻辑；后续应按实时返佣列表入口单独补 RED/GREEN，避免把订单列表改动扩大到返佣模块???
- 当前 MySQL `127.0.0.1:3307` 已恢复连通；后续仍可用真实代理账号补订单详情弹层 HTML 鐨? parent_id-only 链路展示样例???

## 179. 2026-07-09 前台实时返佣订单链路 parent_id 兜底闭环

### 本次处理目标
- 补齐 `CommissionController::orderChain` 的链路展示兜底，避免实时返佣列表范围已能通过 `FrontLegacyData::userScopeIds` 命中 parent_id-only 客户订单，但返回??? `order_chain` 仍因缺少 `family_tree` 变成空链???
- 当旧项目导入数据只有 `user_infos.parent_id` 关系、没??? `family_tree` 鍜? `agent_descendants` 时，前台代理查看实时返佣订单仍能看到当前代理到下级代理再到客户的完整订单链路???
- 保持实时返佣查询、平仓订??? scope、当前代理返佣金额???结算状态???返佣比例和详情弹层结构不变，只补齐列表链路展示 ID 来源???

### 本次变更文件
- `app/Http/Controllers/Front/CommissionController.php`
  - `CommissionController::orderChain` 改为先???过 `orderChainIds()` 获取链路 ID，再按当前查看代理截取可见链路???
  - 新增 `orderChainIds()`锛屼繚鐣? `family_tree` 优先；仅??? `family_tree` 为空时才回??? parent 链???
  - 新增 `parentOrderChainIds()`，沿 `user_infos.parent_id` 向上补齐祖先 ID，并使用 visited 防止脏数据循环???
- `tests/Feature/FrontCommissionOrderChainScopeFallbackModuleTest.php`
  - 新增接口级闭环测试，构???无 `agent_descendants`、无 `family_tree` 鐨? root agent -> sub agent -> customer 三层关系???
  - 验证 `/api/front/commissions/realtime` 能返回真实平仓订单，并在 `order_chain` 中输??? root -> sub -> customer銆?
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontCommissionOrderChainScopeFallbackModuleTest.php` 首次失败，实时返佣接口返回真实订单，??? `order_chain` 实际为空数组，证明旧 `CommissionController::orderChain` 只依??? `family_tree`銆?
- GREEN锛歚CommissionController::orderChain` 增加 parent 链兜底后，同??? parent_id-only 实时返佣接口样例通过???
- RED：新增清单测试首次失败，命中???终清单缺少第 179 节???

### 当前证据
- 实时返佣列表可见范围仍由 `FrontLegacyData::userScopeIds` 直接约束???
- `CommissionController::orderChain` 已兼??? `family_tree` 涓? `user_infos.parent_id` 两种链路来源，且只返回当前查看代理节点之后的可见链路???
- `FrontCommissionOrderChainScopeFallbackModuleTest` 覆盖 parent_id-only 关系下的真实登录态???真实订单表和真实实时返佣链路响应???

### 剩余边界
- 本轮没有改动实时返佣金额计算、返佣详情拆分???结算状态???历史返佣列表???佣金转账???前端字段映射或数据库结构???
- 实时返佣详情弹层当前主要展示返佣明细表；如后续要在弹层中展示订单链路，应??? HTML 输出单独??? RED/GREEN銆?
- 当前 MySQL `127.0.0.1:3307` 已恢复连通；后续仍可用真实代理账号补实时返佣详情弹层??? parent_id-only 展示样例???

## 180. 2026-07-09 前台代理列表 depth 字段 parent_id 兜底闭环

### 本次处理目标
- 补齐 `AgentController::scopeDepth` 的层级计算兜底，避免前台代理列表??? `family_tree` 鍜? `agent_descendants` 缺失时把多层 parent_id-only 下级代理统一显示??? depth=2銆?
- 当旧项目导入数据只保??? `user_infos.parent_id` 层级时，`/api/front/agents/direct` 仍应按当前代理可见范围返回全部下级代理，并给出相对当前代理的真实 depth銆?
- 保持代理列表查询范围、分页结构???统计字段???直属筛选和前端字段名不变；本轮只补 depth 展示值来源???

### 本次变更文件
- `app/Http/Controllers/Front/AgentController.php`
  - `AgentController::scopeDepth` 鍦? `family_tree` 无法解析层级时回???鍒? parent 链计算???
  - 新增 `AgentController::parentScopeDepth`，沿 `user_infos.parent_id` 向上查找当前查看代理，使??? visited 防止脏数据循环???
  - 代理可见范围继续复用 `FrontLegacyData::userScopeIds`，只统一列表 depth 字段的兜底语义???
- `tests/Feature/FrontAgentScopeDepthFallbackModuleTest.php`
  - 新增 parent_id-only 代理树闭环测试，构??? root -> level1 -> level2 -> level3 且清??? `agent_descendants` 鍜? `family_tree`銆?
  - 验证 `/api/front/agents/direct` 返回 level1/level2/level3 鐨? depth 分别??? 1銆?2銆?3銆?
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentScopeDepthFallbackModuleTest.php` 首次失败，第三层代理 depth 实际??? `2`、期望为 `3`，证明旧 `scopeDepth` 鍦? parent_id-only 多层代理树下只能给非直属下级固定兜底值???
- GREEN锛歚AgentController::scopeDepth` 增加 `parentScopeDepth` 回???后，同一业务断言通过???
- RED：新增清单测试首次失败，命中???终清单缺少第 180 节???

### 当前证据
- `AgentController::scopeDepth` 已兼??? `family_tree` 涓? `user_infos.parent_id` 两种层级来源???
- `AgentController::parentScopeDepth` 只在 `family_tree` 无法给出当前代理相对层级时触发，不改变已??? family_tree 优先级???
- `FrontAgentScopeDepthFallbackModuleTest` 覆盖 parent_id-only 关系下的真实登录态???真实用户表和真实前台代理列表接口???

### 剩余边界
- 本轮没有改动代理列表查询范围、直属客户明细???代理统计聚合???闭包表重建、前端字段映射或数据库结构???
- `FrontLegacyData::userScopeIds` 仍是前台代理可见范围入口；后续若发现其它列表字段仍手??? `family_tree` 层级，应继续按独??? RED/GREEN 补闭环???
- 当前 MySQL `127.0.0.1:3307` 已恢复连通；后续继续用真实数据库事务补剩余模块测试???
## 181. 2026-07-09 前台持仓汇??婚摼璺? parent_id 兜底闭环

### 本次处理目标
- 补齐 `PositionController::summaryChain` 的面包屑链路兜底，避免前台持仓汇总钻取下级代理时??? `family_tree` 缺失??? parent_id-only 数据下漏掉中间代理???
- 当旧项目导入数据只有 `user_infos.parent_id` 关系、没??? `family_tree` 鍜? `agent_descendants` 时，`/api/front/positions/summary?target_id=...` 仍应返回当前代理到目标代理的完整链路???
- 保持持仓汇???查询范围???分页结构??佽祫閲?/持仓聚合、目标代理权限校验和前端字段名不变；本轮只补 `data.chain` 展示链路来源???

### 本次变更文件
- `app/Http/Controllers/Front/PositionController.php`
  - `PositionController::summaryChain` 保留 `family_tree` 优先；仅当目标代??? `family_tree` 为空时回???鍒? parent 链???
  - 新增 `PositionController::parentSummaryChainIds`，沿 `user_infos.parent_id` 向上收集祖先代理 ID，并使用 visited 防止脏数据循环???
  - 目标代理可见性仍??? `FrontLegacyData::userScopeIds($agentId, false, 1)` 约束，不改变原有数据隔离规则???
- `tests/Feature/FrontPositionSummaryChainScopeFallbackModuleTest.php`
  - 新增接口??? parent_id-only 链路闭环测试，构??? root -> level1 -> level2锛屾竻绌? `agent_descendants` 鍜? `family_tree`銆?
  - 验证 `/api/front/positions/summary` 钻取 level2 鏃? `data.chain` 返回 root -> level1 -> level2銆?
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontPositionSummaryChainScopeFallbackModuleTest.php` 首次有效失败，`data.chain` 实际??? `[root, level2]`，期望为 `[root, level1, level2]`，证明旧 `summaryChain` 鍦? parent_id-only 多层代理树下漏掉中间节点???
- GREEN锛歚PositionController::summaryChain` 增加 `parentSummaryChainIds` 回???后，同一业务断言通过???
- RED：新增清单测试首次失败，命中???终清单缺少第 181 节???

### 当前证据
- `PositionController::summaryChain` 已兼??? `family_tree` 涓? `user_infos.parent_id` 两种链路来源???
- `PositionController::parentSummaryChainIds` 只在 `family_tree` 为空时触发，不改变旧链路优先级???
- `FrontPositionSummaryChainScopeFallbackModuleTest` 覆盖 parent_id-only 关系下的真实登录态???真实用户表和真实前台持仓汇总接口???

### 剩余边界
- 本轮没有改动持仓汇???聚合???交易明细???直属代理汇总??佹寔浠?/平仓 scope、佣金统计???前端字段映射或数据库结构???
- `FrontLegacyData::userScopeIds` 仍是前台持仓模块的数据范围入口；后续如发现其它持仓链路或弹层仍手??? `family_tree`，应继续按独??? RED/GREEN 补齐???
- 当前 MySQL `127.0.0.1:3307` 已恢复连通；后续继续用真实数据库事务补剩余模块测试???
## 182. 2026-07-09 前台旧代??? parentPath 链路 parent_id 兜底闭环

### 本次处理目标
- 补齐 `AgentController::getParentPath` 的旧代理层级路径兜底，避??? `user/proxy/parentPath` 鍦? `family_tree` 缺失??? parent_id-only 多层代理树下漏掉中间代理???
- 当旧项目导入数据只有 `user_infos.parent_id` 关系、没??? `family_tree` 鍜? `agent_descendants` 时，当前代理查看下级代理路径仍应返回 root -> level1 -> target 的完??? HTML 节点链???
- 保持可见性校验???响应字??? `path/tree`銆丩ayui 事件名???颜色映射和旧路由不变；本轮只补路径 ID 来源???

### 本次变更文件
- `app/Http/Controllers/Front/AgentController.php`
  - `AgentController::getParentPath` 保留 `family_tree` 优先；仅当目标用??? `family_tree` 为空时回???鍒? parent 链???
  - 新增 `AgentController::parentPathIds`，沿 `user_infos.parent_id` 向上收集祖先 ID，并使用 visited 防止脏数据循环???
  - 当前代理是否可见目标用户仍由 `FrontLegacyData::userScopeIds` 间接约束，不改变原有权限边界???
- `tests/Feature/FrontAgentParentPathScopeFallbackModuleTest.php`
  - 新增旧路由接口级 parent_id-only 链路闭环测试，构??? root -> level1 -> level2锛屾竻绌? `agent_descendants` 鍜? `family_tree`銆?
  - 验证 `POST /user/proxy/parentPath` 返回??? `data.tree` 节点 ID 涓? root -> level1 -> level2銆?
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentParentPathScopeFallbackModuleTest.php` 首次失败，`data.tree` 实际??? `[root, level2]`，期望为 `[root, level1, level2]`，证明旧 `getParentPath` 鍦? parent_id-only 多层代理树下漏掉中间节点???
- GREEN锛歚AgentController::getParentPath` 增加 `parentPathIds` 回???后，同一业务断言通过???
- RED：新增清单测试首次失败，命中???终清单缺少第 182 节???

### 当前证据
- `AgentController::getParentPath` 已兼??? `family_tree` 涓? `user_infos.parent_id` 两种路径来源???
- `AgentController::parentPathIds` 只在 `family_tree` 为空时触发，不改变旧链路优先级???
- `FrontAgentParentPathScopeFallbackModuleTest` 覆盖 parent_id-only 关系下的真实登录态???真实用户表和旧前台代理路径接口???

### 剩余边界
- 本轮没有改动代理列表范围、代???/客户明细、等级确认???佣金转账???前端链路渲??? JS、闭包表重建或数据库结构???
- `FrontLegacyData::userScopeIds` 仍是前台代理可见性入口；后续如发现其它旧代理弹层仍手??? `family_tree`，应继续按独??? RED/GREEN 补齐???
- 当前 MySQL `127.0.0.1:3307` 已恢复连通；后续继续用真实数据库事务补剩余模块测试???
## 183. 2026-07-09 前台旧佣金转账写入级闭环

### 本次处理目标
- 补齐旧前??? `user/proxy/directUserCommTrans` 在真??? DB 下的成功/拒绝写入级闭环测试???
- 验证 parent_id-only 直属目标在缺??? `agent_descendants` 时仍可完成转账，并同步更新转出方/接收??? `user_infos.total_funds`銆?
- 验证非直属目标被拒绝时不变更余额、不写入 `commission_records`，避免跨层级或外部用户被旧接口转账???
- 补齐旧接口成功写入时的审计备注，保证 DBCT/WBCT 两条流水都记录用户提交的 `remark` 鍒? `manual_reason` 鍜? `remarks`銆?

### 本次变更文件
- `app/Http/Controllers/Front/AgentController.php`
  - `directUserCommTrans` 新增读取 `remark` 参数???
  - DBCT 接收方入账流水与 WBCT 当前代理出账流水都写??? `manual_reason`銆?
  - DBCT/WBCT `remarks` 保留旧订单号前缀，并在存在备注时追加用户提交的备注内容???
- `tests/Feature/FrontAgentDirectTransferWriteClosureModuleTest.php`
  - 新增旧路由写入级成功样例，构??? parent_id-only 直属客户，验证余额扣增??丏BCT/WBCT 双流水???正负佣金金额??佺埗瀛? ID 和备注字段???
  - 新增旧路由拒绝样例，构???间接客户，验证 `NOTALLOW` 且余额和流水均无写入???
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentDirectTransferWriteClosureModuleTest.php` 首次失败，`manual_reason` 实际为空字符串，期望为用户提交的 `legacy direct transfer write closure`，证明旧 `directUserCommTrans` 成功转账后没有完整记录审计备注???
- GREEN锛歚directUserCommTrans` 灏? `remark` 写入 DBCT/WBCT 两条 `commission_records` 后，成功写入样例和拒绝无写入样例均??氳繃銆?
- RED：新增清单测试首次失败，命中???终清单缺少第 183 节???

### 当前证据
- `FrontAgentDirectTransferWriteClosureModuleTest` 覆盖真实登录态??佺湡瀹? `user_infos` 余额更新、真??? `commission_records` 写入和旧路由 `POST /user/proxy/directUserCommTrans`銆?
- 成功路径已验??? parent_id-only 直属目标可转账，并写入一正一??? DBCT/WBCT 流水???
- 拒绝路径已验证间接目标返??? `NOTALLOW`，且不会变更余额或写入转账流水???

### 剩余边界
- 本轮没有改动密码校验、余额不足校验???转账金额校验???旧前台表单结构、现??? `/api/front/commissions/transfers`、数据库结构或共享作用域实现???
- 旧接口异常捕获仍保持原有 `MT4_data_no_sync` 响应；如后续要细化异常类型，应按独立 RED/GREEN 覆盖事务失败场景???
## 184. 2026-07-09 前台返佣计算服务真实订单??? parent_id-only 金额闭环

### 本次处理目标
- 补齐 `CommissionService::calculateRealTimeCommission` 涓? `CommissionService::calculateSettlement` 的真实订单级计算样例???
- 验证 parent_id-only 三层代理??? root agent -> sub agent -> customer 在缺??? `agent_descendants` 涓? `family_tree` 时，当前代理只计算自己与链路下一节点的佣金率差额???
- 避免上级代理按???当前代理佣金率 - 鏈?终客户佣金率”计算，把直属下级代理应得差额一并计入上级实时返佣或结算记录???
- 覆盖实时返佣金额和结算写??? `commission_records` 两条闭环???

### 本次变更文件
- `app/Services/CommissionService.php`
  - `calculateRealTimeCommission` 改为通过共享的链路差额计算方法获取每笔订单当前代理应得金额???
  - `calculateSettlement` 改为复用同一链路差额计算方法，写入结算记录时金额与实时返佣口径一致???
  - 新增 `commissionAmountForTrade`，沿 `family_tree` 鎴? parent 链找到当前代理后的下???节点，并按???当前代理佣金率 - 下一节点佣金率???计算金额???
  - 当代理不存在、交易用户不存在或当前代理不在订单链路中时返??? 0，不扩大可见范围???
- `tests/Feature/FrontCommissionServiceOrderCalculationClosureModuleTest.php`
  - 新增实时返佣真实订单样例，构??? parent_id-only root -> sub-agent -> customer 鍜? 1 手未平仓订单，验??? root 只得??? 1.00 返佣???
  - 新增结算写入样例，构??? 1 手已平仓未结算订单，验证 `calculateSettlement` 写入 root 鐨? `commission_records.commission_amount=1.00`銆乣real_amount=1.00`銆乣agent_volume=1.00`銆?
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontCommissionServiceOrderCalculationClosureModuleTest.php` 首次失败，实时返??? `total` 和结算记??? `commission_amount` 都实际为 `3.0`，期望为 `1.0`，证明旧服务把最终客户差额全部算??? root 代理???
- GREEN锛歚CommissionService` 改为按链路下???节点佣金率计算后，实时返佣金额和结算写入金额均转??? `1.0`銆?
- RED：新增清单测试首次失败，命中???终清单缺少第 184 节???

### 当前证据
- `FrontCommissionServiceOrderCalculationClosureModuleTest` 覆盖真实 `user_infos`銆佺湡瀹? `user_trades`銆佺湡瀹? `group_configs`銆佺湡瀹? `spread_configs` 和真??? `commission_records` 写入???
- `CommissionService::calculateRealTimeCommission` 涓? `CommissionService::calculateSettlement` 现在共享同一逐级差额口径，避免实时金额和结算金额不一致???
- parent_id-only 代理树下不依??? `agent_descendants` 即可找到订单链路中的下一节点佣金率???

### 剩余边界
- 本轮没有改动 `orderCommissionDetails`、后台结算审核??乣settleCommission`、交易订??? settlement_status 更新、前端返佣列表字段???数据库结构或点差配置管理???
- 当前结算仍保持原有聚合记录写入策略；如后续要改成逐订单结算记录或回写订单结算状???，应按独立 RED/GREEN 继续补齐???
## 185. 2026-07-09 前台客户组别变更申请写入边界闭环

### 本次处理目标
- 补齐前台 `AgentController::groupChange` 的真??? DB 写入级闭环测试，确保客户组别变更申请只允许提交普通客户???
- 验证 parent_id-only 直属客户在缺??? `agent_descendants` 时仍可提交转组申请，并写??? `trans_apply_logs` 的目标用户???原组???目标组、申请人和申请原因???
- 验证直属下级代理不能被当作客户提交组别变更申请，拒绝时不写入 `trans_apply_logs`，避免代理账号进入客户转组审核流???

### 本次变更文件
- `app/Http/Controllers/Front/AgentController.php`
  - `groupChange` 在目标用户存在后增加 `account_type=2` 限制，非普???客户直接返回权限拒绝???
  - 保持目标组别校验、代理树可见性校验???申请字段写入和旧兼容入口复用关系不变???
- `tests/Feature/FrontAgentGroupChangeWriteClosureModuleTest.php`
  - 新增直属客户成功申请样例，覆盖真??? `user_infos`銆乣user_logins`銆乣group_configs` 鍜? `trans_apply_logs` 写入???
  - 新增直属代理拒绝样例，验证返??? `ResponseCode::PERMISSION_DENIED` 且无申请记录写入???
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentGroupChangeWriteClosureModuleTest.php` 首次失败，直属代理转组申请实际返??? `1000`锛屾湡鏈? `4006`，证明旧 `groupChange` 只校验代理树范围，没有限制目标必须是普???客户???
- GREEN锛歚groupChange` 增加 `account_type=2` 边界后，直属客户成功写入与直属代理拒绝无写入样例均??氳繃銆?
- RED：新增清单测试首次失败，命中???终清单缺少第 185 节???

### 当前证据
- `FrontAgentGroupChangeWriteClosureModuleTest` 覆盖真实登录态??佺湡瀹? parent_id-only 关系、真实客户组别配置和 `trans_apply_logs` 写入结果???
- 成功路径已验证普通客户申请记录写??? `origin_group_id`銆乣group_id`銆乣group_name`銆乣applicant_id`銆乣applicant_name` 鍜? `apply_reason`銆?
- 拒绝路径已验证下级代理账号返回权限拒绝，且不会生成客户转组申请记录???

### 剩余边界
- 本轮没有改动转组审核通过/拒绝流程、后台审核页面???旧前台表单结构、客户列表可见范围???客户组别下拉来源或数据库结构???
- `changeDirectCustGroupInfo` 继续复用 `groupChange`，旧字段映射入口自然继承同一账号类型边界；如后续补旧路由适配器，应按独立 RED/GREEN 覆盖路由层???
## 186. 2026-07-09 前台客户转组目标组别类别闭环

### 本次处理目标
- 补齐前台客户组别变更申请的目标组别类别边界，确保客户转组只能提交??? `group_configs.category=2` 的客户组???
- 验证现代 `/api/front/agents/group-change-applications` 即使传入启用的代理组 ID，也必须返回参数校验失败且不写入 `trans_apply_logs`銆?
- 验证??? Web `changeDirectCustGroupEdit` 通过 `grpName` 命中代理组名时，同样不能写入客户转组申请，避免旧入口绕过现代下拉选项限制???

### 本次变更文件
- `app/Http/Controllers/Front/AgentController.php`
  - `groupChange` 在确认目标组别存在且启用后，继续校验 `group_configs.category=2`銆?
  - 缺少 `category` 字段的旧迁移环境仍保留原有启用组兼容逻辑，不扩大迁移环境风险???
- `tests/Feature/FrontAgentGroupChangeGroupCategoryClosureModuleTest.php`
  - 新增现代接口代理??? ID 拒绝样例，覆盖真??? `user_infos`銆乣user_logins`銆乣group_configs` 鍜? `trans_apply_logs`銆?
  - 新增??? `user/cust/change/group_edit` 代理组名拒绝样例，确认旧响应??? `FAIL` 且无申请记录写入???
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentGroupChangeGroupCategoryClosureModuleTest.php` 首次失败，现代接口实际返??? `1000`、旧入口实际返回 `SUCCESS`，证明旧逻辑只校验组别启用，没有限制目标组别必须是客户组???
- GREEN锛歚groupChange` 增加 `group_configs.category=2` 校验后，现代接口和旧入口均拒绝代理组目标，并且不写入 `trans_apply_logs`銆?
- RED：新增清单测试首次失败，命中???终清单缺少第 186 节???

### 当前证据
- `FrontAgentGroupChangeGroupCategoryClosureModuleTest` 覆盖现代 API 和旧 Web 入口两种提交路径???
- 成功拒绝路径已验证启用的代理组不能进入客户转组申请流，拒绝后没有产生 `trans_apply_logs` 记录???
- 绗? 185 节普通客户目标账号边界与本节客户组别类别边界共同收紧客户转组写入面???

### 剩余边界
- 本轮没有改动客户组别下拉展示、后台组别配??? CRUD、转组审核???过后实际更新客户组别的后台流程或数据库结构???
- `changeDirectCustGroupEdit` 仍保持旧 `CLASSINVALID` 涓? `SUCCESS/FAIL` 响应结构；如后续发现旧页面还有其它备注字段名，应按独??? RED/GREEN 补旧参数映射???
## 187. 2026-07-09 旧前??? group_edit 申请原因字段写入闭环

### 本次处理目标
- 补齐??? Web `user/cust/change/group_edit` 的申请原因字段映射，避免旧表单提??? `trans_apply_reason` 时写??? `trans_apply_logs.apply_reason` 为空???
- 验证旧入口仍??? `grpName` 查找客户组???按 `userId` 定位直属客户，并把旧字段申请原因完整落库???
- 保持现代 `groupChange` 参数结构和旧 `SUCCESS/FAIL` 响应结构不变，只补旧字段名兼容???

### 本次变更文件
- `app/Http/Controllers/Front/AgentController.php`
  - `changeDirectCustGroupEdit` 鐨? `reason` 合并逻辑扩展??? `reason -> apply_reason -> trans_apply_reason`銆?
  - 写入仍复??? `groupChange`，继续继承账号类型???客户组类别和代理树可见性边界???
- `tests/Feature/FrontAgentGroupChangeLegacyReasonClosureModuleTest.php`
  - 新增旧路由真实写入样例，提交 `trans_apply_reason` 后验??? `trans_apply_logs.apply_reason` 保存原始原因???
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentGroupChangeLegacyReasonClosureModuleTest.php` 首次失败，数据库中相同申请记录的 `apply_reason` 实际为空，证明旧 `changeDirectCustGroupEdit` 只读??? `reason`，丢失旧字段 `trans_apply_reason`銆?
- GREEN：旧入口原因字段合并后，同一旧路由写入样例??氳繃锛宍apply_reason` 正确保存旧字段??笺??
- RED：新增清单测试首次失败，命中???终清单缺少第 187 节???

### 当前证据
- `FrontAgentGroupChangeLegacyReasonClosureModuleTest` 覆盖真实登录态??佺湡瀹? parent_id-only 客户、真实客户组名查找和 `trans_apply_logs` 写入???
- 鏃? `group_edit` 继续通过 `groupChange` 执行统一校验，因此第 185銆?186 节新增的账号类型和客户组类别边界仍有效???

### 剩余边界
- 本轮没有改动旧页??? HTML銆佺幇浠? `/api/front/agents/group-change-applications`、组别下拉来源???转组审核后台或数据库结构???
- 如后续发现旧项目还有 `remark`銆乣memo` 等额外原因字段，应继续按独立 RED/GREEN 扩展字段映射???
## 188. 2026-07-09 前台客户转组申请人账号类型边界闭???

### 本次处理目标
- 补齐 `AgentController::groupChange` 的申请人账号类型边界，确保只有代理账??? `account_type=1` 能提交客户组别变更申请???
- 验证普???客户即使把 `target_user_id` 写成自己，也不能利用 `canViewUser` 鍚? ID 分支自助提交转组申请???
- 避免普???用户模块越权进入代理商客户管理写入流，拒绝时不写入 `trans_apply_logs`銆?

### 本次变更文件
- `app/Http/Controllers/Front/AgentController.php`
  - `groupChange` 在解析当前登录账号后增加申请??? `account_type=1` 校验???
  - 后续目标用户必须为普通客户???目标组别必须为客户组???目标客户必须在代理树内的边界保持不变???
- `tests/Feature/FrontAgentGroupChangeApplicantBoundaryClosureModuleTest.php`
  - 新增普???客户自提交转组申请拒绝样例，覆盖真??? `user_logins`銆乣user_infos`銆乣group_configs` 鍜? `trans_apply_logs`銆?
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentGroupChangeApplicantBoundaryClosureModuleTest.php` 首次失败，普通客户自提交转组申请实际返回 `1000`锛屾湡鏈? `4006`，证明旧 `groupChange` 没有限制申请人必须是代理???
- GREEN锛歚groupChange` 增加申请人账号类型校验后，普通客户自提交被权限拒绝且不写??? `trans_apply_logs`銆?
- RED：新增清单测试首次失败，命中???终清单缺少第 188 节???

### 当前证据
- `FrontAgentGroupChangeApplicantBoundaryClosureModuleTest` 覆盖普???客户登录???下的真??? API 请求和拒绝无写入结果???
- 绗? 185-188 节共同覆盖客户转组写入流的申请人、目标用户???目标组别和旧字段映射边界???

### 剩余边界
- 本轮没有改动前台菜单权限、角色权限分配???客户组别下拉展示???旧页面模板、转组审核后台或数据库结构???
- 如果后续要在路由或中间件层进???步隔离代理专??? API，应按独??? RED/GREEN 覆盖前台角色权限与接口鉴权链路???
## 189. 2026-07-09 前台客户转组列表申请人账号类型边界闭???

### 本次处理目标
- 补齐 `AgentController::groupChangeList` 的读取边界，确保只有代理账号 `account_type=1` 能读取客户转组申请列表和 `available_groups`銆?
- 验证普???客户即使已有历??? `trans_apply_logs` 记录，也不能访问代理商客户转组列表接口???
- 避免普???用户模块越权读取代理商客户管理列表配置和客户组候??夐」銆?

### 本次变更文件
- `app/Http/Controllers/Front/AgentController.php`
  - `groupChangeList` 在解析当前登录账号后增加 `account_type=1` 校验???
  - `directCustChangeListSearch` 继续复用 `groupChangeList`，因此旧客户转组搜索入口继承同一读取边界???
- `tests/Feature/FrontAgentGroupChangeListApplicantBoundaryClosureModuleTest.php`
  - 新增普???客户访问代理转组列表拒绝样例，预置真实 `trans_apply_logs` 后验证接口返回权限拒绝???
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentGroupChangeListApplicantBoundaryClosureModuleTest.php` 首次失败，普通客户访??? `/api/front/agents/group-changes` 实际返回 `1000`锛屾湡鏈? `4006`，证明旧 `groupChangeList` 没有限制申请人必须是代理???
- GREEN锛歚groupChangeList` 增加申请人账号类型校验后，普通客户读取代理转组列表被权限拒绝???
- RED：新增清单测试首次失败，命中???终清单缺少第 189 节???

### 当前证据
- `FrontAgentGroupChangeListApplicantBoundaryClosureModuleTest` 覆盖真实普???客户登录??併??真实历史申请记录和代理转组列表 API銆?
- 绗? 188 节收紧写入入口，??? 189 节收紧读取入口，客户转组模块的普通用户越权面同步闭合???

### 剩余边界
- 本轮没有改动代理正常列表分页、日期筛选???客户组下拉来源、前端页面渲染???旧 Web 页面或数据库结构???
- 如后续继续推进代理专??? API 中间件化，应??? `groupChangeList` 涓? `groupChange` 纳入统一角色/菜单权限回归???
## 190. 2026-07-09 前台代理等级候???接口申请人边界闭环

### 本次处理目标
- 补齐 `AgentController::getSubAgentsGrpIdList` 的申请人账号类型边界，避免普通客户读取代理等级??欓?? `agentList`銆?
- 验证现代 `/api/front/agents/direct-level-options` 和旧 Web `user/proxy/getSubAgentsGrpIdList` 两个入口都必须要求代理账??? `account_type=1`銆?
- 避免普???用户模块越权读取代理等级名称和返佣比例配置???

### 本次变更文件
- `app/Http/Controllers/Front/AgentController.php`
  - `getSubAgentsGrpIdList` 在读??? `agent_levels` 前增加登录???和 `account_type=1` 校验???
  - 保持代理账号正常返回旧前台兼??? `agentList` 结构，不改变候???等级字段名???
- `tests/Feature/FrontAgentLevelOptionsApplicantBoundaryClosureModuleTest.php`
  - 新增现代接口普???客户拒绝样例???
  - 新增??? Web 入口普???客户拒绝样例???
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentLevelOptionsApplicantBoundaryClosureModuleTest.php` 首次失败，两个入口响应中 `code` 均为 `null`，证明旧 `getSubAgentsGrpIdList` 直接返回 `agentList`，没有登录???或代理身份边界???
- GREEN锛歚getSubAgentsGrpIdList` 增加登录态和代理账号校验后，普???客户访问现代和旧入口均返回 `ResponseCode::PERMISSION_DENIED`銆?
- RED：新增清单测试首次失败，命中???终清单缺少第 190 节???

### 当前证据
- `FrontAgentLevelOptionsApplicantBoundaryClosureModuleTest` 覆盖真实普???客户登录??併?佺湡瀹? `agent_levels` 配置和现???/旧两个代理等级??欓??入口???
- 绗? 190 节把代理等级候???读取纳入代理专属边界，和第 188-189 节共同收紧普通用户访问代理模块的入口面???

### 剩余边界
- 本轮没有改动代理等级确认写入、代理等级列表排序???旧前台字段名???后台代理等级配置或数据库结构???
- 后续可继续按同一模式审计 `subList`銆乣customerList`銆乣statistics`銆乣userLoginHistory` 等代理专属读接口的普通用户边界???
## 191. 2026-07-09 前台代理直属列表申请人边界闭???

### 本次处理目标
- 补齐 `AgentController::subList` 涓? `AgentController::customerList` 的申请人账号类型边界，确保只有代理账??? `account_type=1` 能读取直属代理列表和直属客户列表???
- 验证普???客户访问现??? `/api/front/agents/direct` 涓? `/api/front/agents/direct-customers` 均返??? `ResponseCode::PERMISSION_DENIED`銆?
- 避免普???用户模块越权读取代理商下级代理、直属客户???统计钻取入口和客户可转组??欓??配置???

### 本次变更文件
- `app/Http/Controllers/Front/AgentController.php`
  - `subList` 在读取代理树下级代理前增加登录???和 `account_type=1` 校验???
  - `customerList` 在读取直属客户和 `available_groups` 前增加登录???和 `account_type=1` 校验???
  - `proxyListSearch` 涓? `directCustListSearch` 继续复用对应列表方法，因此旧兼容入口继承同一代理身份边界???
- `tests/Feature/FrontAgentMainListApplicantBoundaryClosureModuleTest.php`
  - 新增普???客户访问直属代理列表拒绝样例???
  - 新增普???客户访问直属客户列表拒绝样例???
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentMainListApplicantBoundaryClosureModuleTest.php` 首次失败，两个列表接口实际返??? `1000`锛屾湡鏈? `4006`，证明旧 `subList` 鍜? `customerList` 只校验登录用??? ID，没有限制申请人必须是代理???
- GREEN锛歚subList` 涓? `customerList` 增加登录态和代理账号校验后，普???客户访问直属代理列表和直属客户列表均返??? `ResponseCode::PERMISSION_DENIED`銆?
- RED：新增清单测试首次失败，命中???终清单缺少第 191 节???

### 当前证据
- `FrontAgentMainListApplicantBoundaryClosureModuleTest` 覆盖真实普???客户登录???和现代直属代理、直属客户列??? API銆?
- 绗? 190 节收紧代理等级??欓?夎鍙栵紝绗? 191 节继续收紧代理主列表读取，普通客户进入代理商管理读接口的越权面进???步闭合???

### 剩余边界
- 本轮没有改动代理账号正常列表分页、`parent_id` 钻取可见性??乣FrontLegacyData::userScopeIds`銆乣available_groups` 生成、旧前台页面模板或数据库结构???
- 后续可继续按同一模式审计 `statistics`銆乣userLoginHistory`、资金和交易明细等代理专属读接口的普通用户边界???
## 192. 2026-07-09 前台代理统计申请人边界闭???

### 本次处理目标
- 补齐 `AgentController::statistics` 的申请人账号类型边界，确保只有代理账??? `account_type=1` 能读取代理统计数据???
- 验证普???客户访问现??? `/api/front/agents/statistics` 返回 `ResponseCode::PERMISSION_DENIED`銆?
- 避免普???用户模块越权读取代理交易统计???层级统计和代理树汇总信息???

### 本次变更文件
- `app/Http/Controllers/Front/AgentController.php`
  - `statistics` 在调??? `FamilyTreeService::getAgentStats` 涓? `getSubAgentStats` 前增加登录???和 `account_type=1` 校验???
  - 保持代理账号正常??? `date_from`銆乣date_to` 统计参数和响应结构不变???
- `tests/Feature/FrontAgentStatisticsApplicantBoundaryClosureModuleTest.php`
  - 新增普???客户访问代理统计拒绝样例???
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentStatisticsApplicantBoundaryClosureModuleTest.php` 首次失败，代理统计接口实际返??? `1000`锛屾湡鏈? `4006`，证明旧 `statistics` 只校验登录用??? ID，没有限制申请人必须是代理???
- GREEN锛歚statistics` 增加登录态和代理账号校验后，普???客户访??? `/api/front/agents/statistics` 返回 `ResponseCode::PERMISSION_DENIED`銆?
- RED：新增清单测试首次失败，命中???终清单缺少第 192 节???

### 当前证据
- `FrontAgentStatisticsApplicantBoundaryClosureModuleTest` 覆盖真实普???客户登录???和现代代理统计 API銆?
- 绗? 191 节收紧代理主列表读取，第 192 节继续收紧代理统计读取，普???客户进入代理商管理读接口的越权面进???步闭合???

### 剩余边界
- 本轮没有改动代理账号正常统计口径、日期筛选参数??乣FamilyTreeService` 聚合逻辑、前端统计卡片或数据库结构???
- 后续可继续按同一模式审计 `userLoginHistory`、用户详情???资金和交易明细等代理专属读接口的普通用户边界???
## 193. 2026-07-09 前台用户登录历史申请人边界闭???

### 本次处理目标
- 补齐 `AgentController::userLoginHistory` 涓? `AgentController::legacyLoginHistorySearch` 的申请人账号类型边界，确保只有代理账??? `account_type=1` 能读取可见用户登录历史???
- 验证普???客户访问现??? `/api/front/users/login-history` 即使查询自己，也返回 `ResponseCode::PERMISSION_DENIED`銆?
- 验证??? Web `user/cust/loginHistorySearch/{uid}` 在普通客户已有真实登录日志时仍返回空表格，避免旧表格入口泄漏登录 IP、地区和浏览器标识???

### 本次变更文件
- `app/Http/Controllers/Front/AgentController.php`
  - `userLoginHistory` 在可见???判断和读取 `user_login_logs` 前增加登录???和 `account_type=1` 校验???
  - `legacyLoginHistorySearch` 在旧表格查询前增加同???代理账号校验；非代理或未登录时返回旧兼容空表格结构???
  - 保持代理账号正常查询下级用户登录历史、现代响应结构和??? `rows/total` 响应结构不变???
- `tests/Feature/FrontUserLoginHistoryApplicantBoundaryClosureModuleTest.php`
  - 新增普???客户访问现代登录历史拒绝样例???
  - 新增普???客户访问旧登录历史表格空结果样例，预置真实 `user_login_logs` 验证不会泄漏???
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontUserLoginHistoryApplicantBoundaryClosureModuleTest.php` 首次失败，现代入口实际返??? `1000`，旧入口 `total` 实际??? `1`，证明旧 `userLoginHistory` 鍜? `legacyLoginHistorySearch` 只依??? `canViewUser`，允许普通客户读取自己的代理详情登录历史接口???
- GREEN：两个入口增加代理账号校验后，现代入口返??? `ResponseCode::PERMISSION_DENIED`，旧入口返回 `rows=[]`銆乣total=0`銆?
- RED：新增清单测试首次失败，命中???终清单缺少第 193 节???

### 当前证据
- `FrontUserLoginHistoryApplicantBoundaryClosureModuleTest` 覆盖真实普???客户登录??併?佺湡瀹? `user_login_logs` 记录、现??? API 和旧 Web 表格入口???
- 绗? 191-193 节连续收紧代理主列表、统计和登录历史读取面，普???客户不能再通过代理详情链路读取代理商管理信息???

### 剩余边界
- 本轮没有改动代理账号正常查看下级用户登录历史、登录日志写入??侀闄? IP 后台模块、前端详情弹层或数据库结构???
- 后续可继续按同一模式审计 `userDetail`銆乣showUser`銆乣directCustDetailList`、资金和交易明细等代理专属读接口的普通用户边界???
## 194. 2026-07-09 前台代理用户详情申请人边界闭???

### 本次处理目标
- 补齐 `AgentController::userDetail`銆乣showUser` 鍜? `legacyUserDetailPage` 的申请人账号类型边界，确保只有代理账??? `account_type=1` 能???过代理详情入口查看可见用户资料???
- 验证普???客户访问现??? `/api/front/users/{user}` 即使查询自己，也返回 `ResponseCode::PERMISSION_DENIED`銆?
- 验证普???客户访问旧 Web `show/user_detail/{userId}/{role}` 即使查询自己，也返回 HTTP 403，避免旧详情弹层泄漏代理管理口径下的资金、订单和返佣汇???字段???

### 本次变更文件
- `app/Http/Controllers/Front/AgentController.php`
  - `userDetail` 在代理树可见性判断前增加登录态和 `account_type=1` 校验???
  - `showUser` 继续复用 `userDetail`锛屽洜姝? REST 风格详情入口继承同一代理身份边界???
  - `legacyUserDetailPage` 在渲染旧 HTML 前增加同???代理账号校验；非代理或未登录时直??? 403銆?
  - 保持代理账号正常查看下级用户详情、客户账号不展示代理等级字段、旧 HTML 结构和现代响应结构不变???
- `tests/Feature/FrontUserDetailApplicantBoundaryClosureModuleTest.php`
  - 新增普???客户访问现代代理用户详情拒绝样例???
  - 新增普???客户访问旧代理详情??? 403 样例???
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontUserDetailApplicantBoundaryClosureModuleTest.php` 首次失败，现代入口实际返??? `1000`，旧详情页实际返??? `200`，证明旧 `userDetail` 鍜? `legacyUserDetailPage` 只依??? `canViewUser`，允许普通客户???过代理详情入口查看自己???
- GREEN：两个入口增加代理账号校验后，现代入口返??? `ResponseCode::PERMISSION_DENIED`，旧详情页返??? HTTP 403銆?
- RED：新增清单测试首次失败，命中???终清单缺少第 194 节???

### 当前证据
- `FrontUserDetailApplicantBoundaryClosureModuleTest` 覆盖真实普???客户登录??併?佺幇浠? REST 详情 API 和旧 Web 详情页???
- 绗? 193 节收紧登录历史读取，??? 194 节继续收紧详情读取，普???客户不能再通过代理详情链路读取代理商管理口径的用户信息???

### 剩余边界
- 本轮没有改动普???用户自己的 `/api/front/profile` 资料入口、代理正常查看下级用户详情???代理树范围判断、前端详情弹层或数据库结构???
- 后续可继续按同一模式审计 `directCustDetailList`、资金流水???持仓和订单明细等代理专属读接口的普通用户边界???
## 195. 2026-07-09 前台直属客户明细旧表格申请人边界闭环

### 本次处理目标
- 补齐 `AgentController::directCustDetailList` 的申请人账号类型边界，确保只有代理账??? `account_type=1` 能读取指定代理的直属客户明细表格???
- 验证普???客户访问旧 Web `user/proxy/direct_cust_detail_list` 即使??? `puid` 传成自己，且名下存在真实 `parent_id` 子客户，也只能返回空表格???
- 避免普???用户模块越权读取代理商直属客户的资金汇总???用户别名字段和旧表格明细数据???

### 本次变更文件
- `app/Http/Controllers/Front/AgentController.php`
  - `directCustDetailList` 在读取指定父级直属客户前增加登录态和 `account_type=1` 校验???
  - 非代理或未登录时保持旧兼??? `code/msg/count/data/totalRow` 响应结构，返??? `count=0`銆乣data=[]`銆?
  - 保持代理账号正常查询可见父级、筛选字段???分页结构和 `totalRow` 汇??婚??辑不变???
- `tests/Feature/FrontDirectCustomerDetailApplicantBoundaryClosureModuleTest.php`
  - 新增普???客户访问旧直属客户明细空结果样例，构??犵湡瀹? `parent_id` 子客户验证不会泄漏???
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontDirectCustomerDetailApplicantBoundaryClosureModuleTest.php` 首次失败，旧表格入口实际返回 `count=1`，证明旧 `directCustDetailList` 只依??? `canViewUser`，允许普通客户用自身 ID 读取直属客户明细???
- GREEN锛歚directCustDetailList` 增加代理账号校验后，普???客户访问同???旧入口返??? `count=0`銆乣data=[]`銆?
- RED：新增清单测试首次失败，命中???终清单缺少第 195 节???

### 当前证据
- `FrontDirectCustomerDetailApplicantBoundaryClosureModuleTest` 覆盖真实普???客户登录??併??真实父子客户数据和??? `user/proxy/direct_cust_detail_list` 表格入口???
- 绗? 194 节收紧详情页，第 195 节继续收紧旧直属客户明细表格，代理详情周边读取面继续闭合???

### 剩余边界
- 本轮没有改动代理账号正常查看直属客户明细、`parent_id`/`userId` 旧参数兼容??乣FrontLegacyData::financialTotalRowForUserIds`、前端表格模板或数据库结构???
- 后续可继续按同一模式审计层级路径、资金流水???持仓和订单明细等代理专属读接口的普通用户边界???
## 196. 2026-07-09 前台代理层级路径申请人边界闭???

### 本次处理目标
- 补齐 `AgentController::getParentPath` 的申请人账号类型边界，确保只有代理账??? `account_type=1` 能读取代理层级路??? HTML銆?
- 验证普???客户访问现??? `/api/front/agents/hierarchy-path` 即使查询自己，也只能返回??? `path/tree`銆?
- 验证普???客户访问旧 Web `user/proxy/parentPath` 即使查询自己，也只能返回??? `path/tree`，避免旧层级路径组件泄漏代理树节??? HTML銆?

### 本次变更文件
- `app/Http/Controllers/Front/AgentController.php`
  - `getParentPath` 在代理树可见性和路径拼接前增加登录???和 `account_type=1` 校验???
  - 非代理???未登录、无效目标或不可见目标统???沿用旧兼容空路径响应结构???
  - 保持代理账号正常层级路径、`event_name`銆乣family_tree` 涓? parent_id fallback 逻辑不变???
- `tests/Feature/FrontAgentParentPathApplicantBoundaryClosureModuleTest.php`
  - 新增普???客户访问现代层级路径空结果样例???
  - 新增普???客户访问旧层级路径空结果样例???
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentParentPathApplicantBoundaryClosureModuleTest.php` 首次失败，现代和旧入口都返回了包含普通客户自??? ID 与名称的路径 HTML，证明旧 `getParentPath` 只依??? `canViewUser`，允许普通客户读取自身代理层级路径???
- GREEN锛歚getParentPath` 增加代理账号校验后，普???客户访问现代和旧入口均返回??? `path` 与空 `tree`銆?
- RED：新增清单测试首次失败，命中???终清单缺少第 196 节???

### 当前证据
- `FrontAgentParentPathApplicantBoundaryClosureModuleTest` 覆盖真实普???客户登录??併?佺幇浠? `/api/front/agents/hierarchy-path` 和旧 `user/proxy/parentPath` 两个入口???
- 绗? 195 节收紧直属客户明细表格，??? 196 节继续收紧层级路径组件，代理树周边读取面继续闭合???

### 剩余边界
- 本轮没有改动代理账号正常查看层级路径、路径颜色映射??乣parentPathIds` fallback、前端层级组件或数据库结构???
- 后续可继续按同一模式审计确认代理等级、资金流水???持仓和订单明细等代理专属读写接口的普???用户边界???
## 197. 2026-07-09 前台代理等级确认列表申请人边界闭???

### 本次处理目标
- 补齐 `AgentController::confirmLevel` 涓? `proxyConfirmSearch` 的申请人账号类型边界，确保只有代理账??? `account_type=1` 能读取待确认下级代理等级列表???
- 验证普???客户访问现??? `/api/front/agents/level-confirmation` 返回 `ResponseCode::PERMISSION_DENIED`銆?
- 验证普???客户访问旧 Web `user/proxy/proxyConfirmSearch` 同样返回 `ResponseCode::PERMISSION_DENIED`，避免读取代理等级确认摘要??佸?欓??等级和待确认下级列表配置???

### 本次变更文件
- `app/Http/Controllers/Front/AgentController.php`
  - `confirmLevel` 在读取当前用户资料??乣agent_levels` 和直属下级代理前增加登录态和 `account_type=1` 校验???
  - `proxyConfirmSearch` 继续复用 `confirmLevel`，因此旧等级确认搜索入口继承同一代理身份边界???
  - 保持代理账号正常读取 `summary`銆乣available_levels`銆乣range_list` 鍜? parent_id scope fallback 逻辑不变???
- `tests/Feature/FrontAgentLevelConfirmationApplicantBoundaryClosureModuleTest.php`
  - 新增普???客户访问现代等级确认列表拒绝样例???
  - 新增普???客户访问旧等级确认搜索拒绝样例???
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentLevelConfirmationApplicantBoundaryClosureModuleTest.php` 首次失败，现代和旧入口实际都返回 `1000`锛屾湡鏈? `4006`，证明旧 `confirmLevel` 只读取当前用户资料，没有限制申请人必须是代理???
- GREEN锛歚confirmLevel` 增加登录态和代理账号校验后，普???客户访问现代和旧入口均返回 `ResponseCode::PERMISSION_DENIED`銆?
- RED：新增清单测试首次失败，命中???终清单缺少第 197 节???

### 当前证据
- `FrontAgentLevelConfirmationApplicantBoundaryClosureModuleTest` 覆盖真实普???客户登录??併?佺幇浠? `/api/front/agents/level-confirmation` 和旧 `user/proxy/proxyConfirmSearch` 两个入口???
- 绗? 197 节把代理等级确认读取入口纳入代理专属边界，和??? 190 节代理等级??欓??接口共同收紧等级配置读取面???

### 剩余边界
- 本轮没有改动 `confirmLevelChange` 等级确认写入、代理账号正常读取等级确认列表???前端确认等级页面或数据库结构???
- 后续继续按独??? RED/GREEN 审计 `confirmLevelChange` 的普通用户写入边界???
## 198. 2026-07-09 前台代理等级确认写入申请人边界闭???

### 本次处理目标
- 补齐 `AgentController::confirmLevelChange` 的申请人账号类型边界，确保只有代理账??? `account_type=1` 能确认直属下级代理等级???
- 验证普???客户即使名下存??? `parent_id` 直属代理子账号，也不能???过现代 `/api/front/agents/level-confirmation/changes` 写入等级确认???
- 验证??? Web `user/proxy/confirmLevelChange` 同样拒绝普???客户，避免写入 `user_infos.is_agent_confirmed`銆乣level_id` 鍜? `comm_rate`銆?

### 本次变更文件
- `app/Http/Controllers/Front/AgentController.php`
  - `confirmLevelChange` 在校验参数后改为通过 `legacyFrontUserLogin` 读取当前登录记录???
  - 未登录仍走旧兼容认证错误；非代理账号返回 `ResponseCode::PERMISSION_DENIED`銆?
  - 保持代理账号正常写入流程、直属代理范围??乣FrontLegacyData::userScopeIds`銆乣agent_levels.user_commission` 鍜? `extra_val` 计算口径不变???
- `tests/Feature/FrontAgentLevelConfirmationChangeApplicantBoundaryClosureModuleTest.php`
  - 新增普???客户访问现代等级确认写入口拒绝样例???
  - 新增普???客户访问旧等级确认写入口拒绝样例???
  - 两个样例均断???目标直属代理??? `is_agent_confirmed`銆乣level_id`銆乣comm_rate` 未被写入???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontAgentLevelConfirmationChangeApplicantBoundaryClosureModuleTest.php` 首次失败，现代和旧写入口实际都返??? `1000`锛屾湡鏈? `4006`，并且会进入等级确认写入路径???
- GREEN锛歚confirmLevelChange` 增加登录态和代理账号 `account_type=1` 校验后，普???客户访问现代和旧入口均返回 `ResponseCode::PERMISSION_DENIED`，目标代理等级确认字段保持未变???
- RED：新增清单测试首次失败，命中???终清单缺少第 198 节???

### 当前证据
- `FrontAgentLevelConfirmationChangeApplicantBoundaryClosureModuleTest` 覆盖真实普???客户登录??併?佺湡瀹? `agent_levels` 配置、真??? `parent_id` 直属代理子账号和两个写入口???
- 绗? 197 节已收紧等级确认读取入口，第 198 节继续闭合等级确认写入入口，普???客户不能???用代理树兜底关系修改代理账号等级状态???

### 剩余边界
- 本轮没有改动代理账号正常确认直属代理等级、代理等级??欓?夈??前端确认等级页面??乣agent_levels.user_commission` 真实比例来源或数据库结构???
- 后续继续按独??? RED/GREEN 审计其它代理专属写入口的普???用户申请人边界???
## 199. 2026-07-09 前台直属佣金转账申请人边界闭???

### 本次处理目标
- 补齐 `AgentController::directUserCommTrans` 的申请人账号类型边界，确保只有代理账??? `account_type=1` 能发起直属客户佣金转账???
- 验证普???客户即使名下存??? `parent_id` 直属子客户，也不能???过现代 `/api/front/customers/commission-transfers` 扣减自身余额并给子客户入账???
- 验证??? Web `user/proxy/directUserCommTrans` 同样拒绝普???客户，避免写入 DBCT/WBCT 两条 `commission_records` 转账流水???

### 本次变更文件
- `app/Http/Controllers/Front/AgentController.php`
  - `directUserCommTrans` 在解析登录记录后增加 `account_type=1` 校验???
  - 非代理账号沿用旧前台响应结构返回 `msg=FAIL`銆乣errorType=NOTALLOW`銆?
  - 保持代理账号正常密码校验、余额扣增???直属目标校验???事务写入和 DBCT/WBCT 审计字段不变???
- `tests/Feature/FrontDirectTransferApplicantBoundaryClosureModuleTest.php`
  - 新增普???客户访问现代佣金转账入口拒绝样例???
  - 新增普???客户访问旧佣金转账入口拒绝样例???
  - 两个样例均断???转出方余额???接收方余额??? `commission_records` 未被写入???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontDirectTransferApplicantBoundaryClosureModuleTest.php` 首次失败，现代和旧入口实际都返回 `SUCCESS`，证明普通客户可??? `parent_id` 直属子账号进入佣金转账写入路径???
- GREEN锛歚directUserCommTrans` 增加代理账号 `account_type=1` 校验后，普???客户访问现代和旧入口均返回 `NOTALLOW`，余额和转账流水保持未变???
- RED：新增清单测试首次失败，命中???终清单缺少第 199 节???

### 当前证据
- `FrontDirectTransferApplicantBoundaryClosureModuleTest` 覆盖真实普???客户登录??併?佺湡瀹? `parent_id` 子客户???真实余额字段??佺幇浠? `/api/front/customers/commission-transfers` 和旧 `user/proxy/directUserCommTrans` 两个入口???
- 绗? 183 节覆盖代理正常写入和非直属拒绝，??? 199 节继续闭合申请人角色边界，普通客户不能进入代理专属佣金转账写入流???

### 剩余边界
- 本轮没有改动代理账号正常佣金转账、密码错误响应???余额不足响应???直属范围兜底???前端表单字段或数据库结构???
- 后续继续按独??? RED/GREEN 审计其它代理资金、持仓和订单相关接口的普通用户申请人边界???
## 200. 2026-07-09 前台返佣转账申请人边界闭???

### 本次处理目标
- 补齐 `CommissionController::transfer` 的申请人账号类型边界，确保只有代理账??? `account_type=1` 能调用现??? `/api/front/commissions/transfers`銆?
- 验证普???客户即使名下存??? `parent_id` 直属代理子账号，也不能扣减自身余额并向直属代理子账号入账???
- 避免普???用户模块越权写入返佣转??? DBCT/WBCT 流水和余额字段???

### 本次变更文件
- `app/Http/Controllers/Front/CommissionController.php`
  - `transfer` 在解析当前登录记录后增加 `account_type=1` 校验???
  - 非代理账号返??? `ResponseCode::PERMISSION_DENIED`銆?
  - 保持代理账号正常参数校验、直属下级代理范围???余额不足判断???事务写入和 DBCT/WBCT 审计字段不变???
- `tests/Feature/FrontCommissionTransferApplicantBoundaryClosureModuleTest.php`
  - 新增普???客户访??? `/api/front/commissions/transfers` 拒绝样例???
  - 断言转出方余额???接收方余额??? `commission_records` 均未被写入???
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontCommissionTransferApplicantBoundaryClosureModuleTest.php` 首次失败，返佣转账入口实际返??? `1000`锛屾湡鏈? `4006`，证明普通客户可借直属代理子账号进入写入路径???
- GREEN锛歚CommissionController::transfer` 增加代理账号 `account_type=1` 校验后，普???客户访问该入口返回 `ResponseCode::PERMISSION_DENIED`，余额和转账流水保持未变???
- RED：新增清单测试首次失败，命中???终清单缺少第 200 节???

### 当前证据
- `FrontCommissionTransferApplicantBoundaryClosureModuleTest` 覆盖真实普???客户登录??併?佺湡瀹? `parent_id` 直属代理子账号???真实余额字段和现代返佣转账 API銆?
- 绗? 199 节闭??? `AgentController::directUserCommTrans` 直属客户转账申请人边界，??? 200 节继续闭??? `CommissionController::transfer` 直属代理返佣转账写入口???

### 剩余边界
- 本轮没有改动代理账号正常返佣转账、转账下级代理???项、返佣历史列表???实时返佣列表???前端表单字段或数据库结构???
- 后续继续按独??? RED/GREEN 审计返佣模块读取入口和转账下级代理???项的普通用户申请人边界???
## 201. 2026-07-09 前台返佣转账下级代理选项申请人边界闭???

### 本次处理目标
- 补齐 `CommissionController::transferAgentOptions` 的申请人账号类型边界，确保只有代理账??? `account_type=1` 能读取返佣转账直属下级代理??夐」銆?
- 验证普???客户即使名下存??? `parent_id` 直属代理子账号，也不能???过 `/api/front/commissions/transfer-agent-options` 读取子账??? ID、名称和等级候??夈??
- 避免普???用户模块越权枚举代理转账??欓??，为第 200 节写入口边界提供前置读取闭环???

### 本次变更文件
- `app/Http/Controllers/Front/CommissionController.php`
  - `transferAgentOptions` 改为通过 `legacyFrontUserLogin` 读取当前登录记录???
  - 未登录仍走旧兼容认证错误；非代理账号返回 `ResponseCode::PERMISSION_DENIED`銆?
  - 保持代理账号正常选项字段、`FrontLegacyData::userScopeIds` 直属代理范围??? `account_type=1` 候???过滤不变???
- `tests/Feature/FrontCommissionTransferOptionsApplicantBoundaryClosureModuleTest.php`
  - 新增普???客户访问返佣转账下级代理???项拒绝样例???
  - 断言响应中不包含直属代理子账??? ID 和名称???
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontCommissionTransferOptionsApplicantBoundaryClosureModuleTest.php` 首次失败，???项接口实际返回 `1000`锛屾湡鏈? `4006`，证明普通客户可读取直属代理子账号??欓?夈??
- GREEN锛歚transferAgentOptions` 增加代理账号 `account_type=1` 校验后，普???客户访问该入口返回 `ResponseCode::PERMISSION_DENIED`，响应不再泄漏直属代理??欓?夈??
- RED：新增清单测试首次失败，命中???终清单缺少第 201 节???

### 当前证据
- `FrontCommissionTransferOptionsApplicantBoundaryClosureModuleTest` 覆盖真实普???客户登录??併?佺湡瀹? `parent_id` 直属代理子账号和现代选项 API銆?
- 绗? 200 节闭合返佣转账写入口，第 201 节闭合转账??欓??读取入口，普???客户不能先枚举再提交代理返佣转账???

### 剩余边界
- 本轮没有改动代理账号正常读取下级代理选项、返佣转账写入???返佣历史列表???实时返佣列表???前端下拉字段或数据库结构???
- 后续继续按独??? RED/GREEN 审计返佣实时列表、返佣历史和旧返佣详情入口的普???用户申请人边界???
## 202. 2026-07-09 前台返佣实时列表申请人边界闭???
### 本次处理目标
- 补齐 `CommissionController::realTime` 的申请人账号类型边界，确保只有代理账??? `account_type=1` 能读取实时返佣列表???
- 验证普???客户即使处于真实登录???，也不能???过现代 `/api/front/commissions/realtime` 读取返佣实时列表、订单筛选结果??乣detail_commission` 或返佣明细字段???
- 验证??? Web `user/realtime/realtimeRebateSearch` 复用同一边界，同样拒绝普通客户读取实时返佣数据???

### 本次变更文件
- `app/Http/Controllers/Front/CommissionController.php`
  - `realTime` 改为通过 `legacyFrontUserLogin` 读取当前登录记录???
  - 未登录仍走旧兼容认证错误；非代理账号返回 `ResponseCode::PERMISSION_DENIED`銆?
  - 保持代理账号正常实时返佣口径、订单筛选??乣detail_commission`、返佣明细和分页响应结构不变???
- `tests/Feature/FrontCommissionRealtimeApplicantBoundaryClosureModuleTest.php`
  - 新增普???客户访问现代实时返佣列表拒绝样例???
  - 新增普???客户访问旧实时返佣搜索入口拒绝样例???
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontCommissionRealtimeApplicantBoundaryClosureModuleTest.php` 首次失败，现??? `/api/front/commissions/realtime` 和旧 `user/realtime/realtimeRebateSearch` 对普通客户实际返??? `1000`锛屾湡鏈? `4006`，证明实时返佣列表只按登录用??? ID 取数，未限制申请人必须是代理???
- GREEN锛歚realTime` 增加代理账号 `account_type=1` 校验后，普???客户访问现代和旧入口均返回 `ResponseCode::PERMISSION_DENIED`銆?
- RED：新增清单测试首次失败，命中???终清单缺少第 202 节???

### 当前证据
- `FrontCommissionRealtimeApplicantBoundaryClosureModuleTest` 覆盖真实普???客户登录??併?佺幇浠? `/api/front/commissions/realtime` 和旧 `user/realtime/realtimeRebateSearch` 两个入口???
- 绗? 201 节闭合返佣转账??欓??读取入口，??? 202 节继续闭合实时返佣读取入口，普???客户不能读取代理专属返佣实时列表???

### 剩余边界
- 本轮没有改动代理账号正常实时返佣列表、返佣历史列表???旧返佣详情页???前端列表字段???订单筛选口径或数据库结构???
- 后续继续按独??? RED/GREEN 审计返佣历史列表和旧返佣详情入口的普通用户申请人边界???
## 203. 2026-07-09 前台返佣历史列表申请人边界闭???
### 本次处理目标
- 补齐 `CommissionController::history` 的申请人账号类型边界，确保只有代理账??? `account_type=1` 能读取返佣历史列表???
- 验证普???客户即使名下存??? `commission_records.agent_id` 归属记录，也不能通过现代 `/api/front/commissions/history` 读取历史返佣分页、汇总和统计分析???
- 避免普???用户模块越权读取代理专属历史返佣金额???订单号、结算状态??乣analytics` 趋势和???别维度统计???

### 本次变更文件
- `app/Http/Controllers/Front/CommissionController.php`
  - `history` 改为通过 `legacyFrontUserLogin` 读取当前登录记录???
  - 未登录仍走旧兼容认证错误；非代理账号返回 `ResponseCode::PERMISSION_DENIED`銆?
  - 保持代理账号正常历史返佣查询、日期筛选???订单号筛??夈?乣dataType` 筛??夈??分页??乣totalRow` 鍜? `analytics` 统计口径不变???
- `tests/Feature/FrontCommissionHistoryApplicantBoundaryClosureModuleTest.php`
  - 新增普???客户访问现代历史返佣列表拒绝样例，并构造真??? `commission_records` 记录证明拒绝发生在查询前???
  - 断言拒绝响应不包含普通客户名下历史返佣记录的 `unique_id`銆?
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontCommissionHistoryApplicantBoundaryClosureModuleTest.php` 首次失败，历史返佣入口实际返??? `1000`锛屾湡鏈? `4006`锛岃瘉鏄? `history` 只按登录用户 ID 查询 `commission_records.agent_id`，未限制申请人必须是代理???
- GREEN锛歚history` 增加代理账号 `account_type=1` 校验后，普???客户访??? `/api/front/commissions/history` 返回 `ResponseCode::PERMISSION_DENIED`，响应不再包含其名下历史返佣记录???
- RED：新增清单测试首次失败，命中???终清单缺少第 203 节???

### 当前证据
- `FrontCommissionHistoryApplicantBoundaryClosureModuleTest` 覆盖真实普???客户登录??併?佺湡瀹? `commission_records` 历史返佣记录和现??? `/api/front/commissions/history` 入口???
- 绗? 202 节闭合实时返佣读取入口，??? 203 节继续闭合历史返佣读取入口，普???客户不能读取代理专属返佣历史数据???

### 剩余边界
- 本轮没有改动代理账号正常历史返佣列表、实时返佣列表???旧实时返佣详情页???前端列表字段???历史统计图表或数据库结构???
- 后续继续按独??? RED/GREEN 审计旧实时返佣详情入口的普???用户申请人边界???
## 204. 2026-07-09 前台旧实时返佣详情申请人边界闭环
### 本次处理目标
- 补齐 `CommissionController::realtimeRebateDetail` 的申请人账号类型边界，确保只有代理账??? `account_type=1` 能打???旧实时返佣详??? HTML 弹层???
- 验证普???客户即使名下存??? `parent_id` 直属子客户，且子客户有真??? `user_trades` 已平仓订单，也不能??氳繃鏃? Web `user/realtime/rebate_detail/{orderNo}/{role}` 读取订单详情???
- 避免普???用户模块??? `FrontLegacyData::userScopeIds` 父子树兜底读取代理专属订单返佣详情???订单号、盈亏???方向和当前账号返佣字段???

### 本次变更文件
- `app/Http/Controllers/Front/CommissionController.php`
  - `realtimeRebateDetail` 改为通过 `legacyFrontUserLogin` 读取当前登录记录???
  - 未登录或非代理账号统??? `abort(403)`，保持旧详情 HTML 入口的拒绝方式???
  - 保持代理账号正常旧详情渲染???订单范围??乣FrontLegacyData::userScopeIds` 可见链路、`currentAgentOrderCommission` 鍜? `orderCommissionDetails` 口径不变???
- `tests/Feature/FrontCommissionRealtimeDetailApplicantBoundaryClosureModuleTest.php`
  - 新增普???客户访问旧实时返佣详情拒绝样例，构造真实普通客户???直属子客户和已平仓 `user_trades` 订单???
  - 断言拒绝响应不包含目标订单号???
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontCommissionRealtimeDetailApplicantBoundaryClosureModuleTest.php` 首次失败，旧详情入口实际返回 `200`锛屾湡鏈? `403`，证明普通客户可借直属子客户订单进入旧返佣详??? HTML 渲染路径???
- GREEN锛歚realtimeRebateDetail` 增加登录记录和代理账??? `account_type=1` 校验后，普???客户访??? `user/realtime/rebate_detail/{orderNo}/{role}` 返回 `403`銆?
- RED：新增清单测试首次失败，命中???终清单缺少第 204 节???

### 当前证据
- `FrontCommissionRealtimeDetailApplicantBoundaryClosureModuleTest` 覆盖真实普???客户登录??併?佺湡瀹? `parent_id` 直属子客户??佺湡瀹? `user_trades` 已平仓订单和??? `user/realtime/rebate_detail/{orderNo}/{role}` HTML 入口???
- 绗? 202 节闭合实时返佣列表，??? 203 节闭合历史返佣列表，??? 204 节继续闭合旧实时返佣详情弹层，返佣读取面完成这一组三个入口的普???客户边界收紧???

### 剩余边界
- 本轮没有改动代理账号正常查看旧实时返佣详情???现代实时返佣列表???历史返佣列表???前端详情跳转???返佣明细计算或数据库结构???
- 后续继续按独??? RED/GREEN 审计下一个前台代理专属读取或写入入口的普通用户申请人边界???
## 205. 2026-07-09 前台下级代理持仓汇???申请人边界闭环
### 本次处理目标
- 补齐 `PositionController::subPositionSummary` 的申请人账号类型边界，确保只有代理账??? `account_type=1` 能读取下级代理持仓汇总???
- 验证普???客户即使名下存??? `parent_id` 直属代理子账号，也不能???过现代 `/api/front/positions/direct-agent-summaries` 枚举该代理子账号的持仓汇总行???
- 验证??? Web `user/position/v2/subAgentsListSearchV2` 同样拒绝普???客户，避免??? `FrontLegacyData::userScopeIds` 父子树兜底读取下级代??? ID、名称和资金持仓汇??汇??

### 本次变更文件
- `app/Http/Controllers/Front/PositionController.php`
  - `subPositionSummary` 改为通过 `legacyFrontUserLogin` 读取当前登录记录???
  - 未登录仍走旧兼容认证错误；非代理账号返回 `ResponseCode::PERMISSION_DENIED`銆?
  - 保持代理账号正常下级代理持仓汇??汇?乣FrontLegacyData::userScopeIds($agentId, false, 1)` 范围、用户名筛??夈??分页和财务汇???口径不变???
- `tests/Feature/FrontPositionDirectAgentSummaryApplicantBoundaryClosureModuleTest.php`
  - 新增普???客户访问现代下级代理持仓汇总拒绝样例???
  - 新增普???客户访问旧下级代理持仓汇???拒绝样例???
  - 两个样例均构造真??? `parent_id` 直属代理子账号，并断???拒绝响应不包含子账号 ID 和名称???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontPositionDirectAgentSummaryApplicantBoundaryClosureModuleTest.php` 首次失败，现代和旧下级代理持仓汇总入口实际都返回 `1000`锛屾湡鏈? `4006`，证明普通客户可借直属代理子账号进入下级代理汇???读取路径???
- GREEN锛歚subPositionSummary` 增加登录记录和代理账??? `account_type=1` 校验后，普???客户访问现代和旧入口均返回 `ResponseCode::PERMISSION_DENIED`，响应不再包含直属代理子账号???
- RED：新增清单测试首次失败，命中???终清单缺少第 205 节???

### 当前证据
- `FrontPositionDirectAgentSummaryApplicantBoundaryClosureModuleTest` 覆盖真实普???客户登录??併?佺湡瀹? `parent_id` 直属代理子账号??佺幇浠? `/api/front/positions/direct-agent-summaries` 和旧 `user/position/v2/subAgentsListSearchV2` 两个入口???
- 绗? 205 节只收紧下级代理汇???入口；普???客户本??? MT4 汇??? `positionSummary2Search` 鍜? `/api/front/positions/summary` 的自查语义不在本轮改动范围内???

### 剩余边界
- 本轮没有改动代理账号正常读取下级代理持仓汇??汇??普通客户本人持仓汇总???持仓交易明细???点击明细???前端持仓汇总页面或数据库结构???
- 后续继续按独??? RED/GREEN 审计持仓交易明细、旧点击明细等代理专属读取入口的普???用户申请人边界???
## 206. 2026-07-09 前台持仓交易明细下级读取申请人边界闭???
### 本次处理目标
- 补齐 `PositionController::positionDetail` 涓? `clickSearch` 的普通客户下级读取边界：普???客户只能读取本人交易明细，不能??? `parent_id` 父子树读取下级用户交易???
- 验证普???客户即使名下存在直属子客户，且子客户有真实 `user_trades` 已平仓订单，也不能???过现代 `/api/front/positions/trades` 读取该子客户交易明细???
- 验证??? Web `user/position/v2/positionSummaryClickSearch` 同样拒绝普???客户读取下级交易明细，避免泄漏订单号???品种???盈亏???开平仓时间和交易状态???

### 本次变更文件
- `app/Http/Controllers/Front/PositionController.php`
  - `positionDetail` 改为通过 `legacyFrontUserLogin` 读取当前登录记录???
  - 未登录仍走旧兼容认证错误；非代理账号仅允??? `targetUserId` 等于当前登录业务用户 ID銆?
  - 代理账号继续沿用原有 `FrontLegacyData::userScopeIds($agentId, false)` 下级范围和本人范围校验???
  - `clickSearch` 继续复用 `positionDetail`，因此旧点击明细入口继承同一边界???
- `tests/Feature/FrontPositionTradeDetailApplicantBoundaryClosureModuleTest.php`
  - 新增普???客户访问现代子客户交易明细拒绝样例???
  - 新增普???客户访问旧点击明细入口拒绝样例???
  - 两个样例均构造真实直属子客户??? `user_trades` 已平仓订单，并断???拒绝响应不包含目标订单号和子客户名称???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontPositionTradeDetailApplicantBoundaryClosureModuleTest.php` 首次失败，现代和旧交易明细入口实际都返回 `1000`锛屾湡鏈? `4006`，证明普通客户可借直属子客户进入交易明细读取路径???
- GREEN锛歚positionDetail` 区分代理与普通客户后，普通客户读取下级交易明细返??? `ResponseCode::PERMISSION_DENIED`锛沗clickSearch` 复用该方法同步收紧???
- RED：新增清单测试首次失败，命中???终清单缺少第 206 节???

### 当前证据
- `FrontPositionTradeDetailApplicantBoundaryClosureModuleTest` 覆盖真实普???客户登录??併?佺湡瀹? `parent_id` 直属子客户??佺湡瀹? `user_trades` 已平仓订单??佺幇浠? `/api/front/positions/trades` 和旧 `user/position/v2/positionSummaryClickSearch` 两个入口???
- 绗? 205 节收紧下级代理持仓汇总，??? 206 节继续收紧持仓交易明细下钻，普???客户不能???过代理树兜底读取下级交易明细???

### 剩余边界
- 本轮没有改动代理账号正常查看下级交易明细、普通客户本人交易明细???下级代理持仓汇总??佹湰浜? MT4 汇??汇??前端持仓汇总页面或数据库结构???
- 后续继续按独??? RED/GREEN 审计旧持仓聚合搜索???资金流水等代理专属读取入口的普通用户申请人边界???

## 207. 2026-07-09 前台大代理持仓搜索参数冒充申请人边界闭环

### 本次处理目标
- 补齐 `BigNumberController::currentBigAgent` 的旧大代理身份来源边界，确保旧大代理 Ajax 只能信任登录后写入的 `bigAgents` session銆?
- 验证普???客户即使知道真??? `big_agents.id`，也不能通过请求参数 `big_agent_id` 鎴? `bigAgentId` 冒充大代理读取持仓聚合搜索结果???
- 覆盖??? Web `user/agents/position/positionSummarySearch` 鍜? `user/agents/position/subAgentsListSearch` 两个持仓入口，避免泄??? `big_agents.sub_agent_ids` 下的代理 ID、名称和资金汇???字段???

### 本次变更文件
- `app/Http/Controllers/Front/BigNumberController.php`
  - `currentBigAgent` 移除请求参数 `big_agent_id` / `bigAgentId` 直查大代理账号的兜底???
  - 保留旧大代理登录成功后写??? session `bigAgents` 的正常读取路径???
  - 保持大代理账号正常读取持仓汇总???下级代理范围??乣FrontLegacyData::userScopeIds` 兜底和旧表格 `rows/total/footer` 响应结构不变???
- `tests/Feature/FrontBigNumberPositionApplicantBoundaryClosureModuleTest.php`
  - 新增普???客户携??? `big_agent_id` 访问旧大代理持仓搜索入口的拒绝样例???
  - 新增普???客户携??? `bigAgentId` 访问旧大代理下级持仓统计入口的拒绝样例???
  - 两个样例均构造真实普通客户???真实大代理账号和可见代理子账号，并断言响应 `rows=[]`銆乣total=0` 且不包含可见代理 ID 和名称???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontBigNumberPositionApplicantBoundaryClosureModuleTest.php` 首次失败，两个旧大代理持仓入口都会返回可见代理行，证明普通客户可通过 `big_agent_id` / `bigAgentId` 参数冒充大代理身份???
- GREEN锛歚currentBigAgent` 只信??? `bigAgents` session 后，普???客户参数冒充访问两个旧持仓入口均返回空 `rows` 鍜? `total=0`銆?
- RED：新增清单测试首次失败，命中???终清单缺少第 207 节???

### 当前证据
- `FrontBigNumberPositionApplicantBoundaryClosureModuleTest` 覆盖真实普???客户登录??併?佺湡瀹? `big_agents` 记录、真实可见代理子账号、`user/agents/position/positionSummarySearch` 鍜? `user/agents/position/subAgentsListSearch` 两个旧入口???
- 绗? 207 节收紧旧大代理持仓搜索的身份来源，普通客户不能再靠请求参数枚举大代理可管理代理范围???

### 剩余边界
- 本轮没有改动大代理账号正常登录??乻ession 读取、代理列表???订单列表???改密入口或数据库结构???
- 后续继续按独??? RED/GREEN 审计旧大代理代理列表、订单列表???资金流水等入口是否还存在参数冒充或普???用户申请人边界缺口???

## 208. 2026-07-09 前台直属客户入金流水申请人边界闭???

### 本次处理目标
- 补齐 `FlowController::accountFlow` 对直属客户流水类型的申请人账号类型边界，确保只有代理账号 `account_type=1` 能读取直属客户入金流水???
- 验证普???客户即使名下存??? `parent_id` 直属子客户，且子客户有真??? `deposit_records` 入金记录，也不能通过现代 `/api/front/flows/direct-deposits` 读取该子客户入金流水???
- 验证??? Web `user/flow/directDepositFlowSearch` 同样拒绝普???客户读取直属客户入金流水，避免泄漏入金订单号???用户名称???金额???渠道和支付时间???

### 本次变更文件
- `app/Http/Controllers/Front/FlowController.php`
  - `accountFlow` 在解??? `flow_type` 鍚庯紝瀵? `direct_deposit`銆乣direct_withdraw`銆乣direct_agents_deposit`銆乣direct_agents_withdraw` 增加申请??? `account_type=1` 校验???
  - 非代理账号读取直属客户或直属代理流水时返??? `ResponseCode::PERMISSION_DENIED`銆?
  - 保持普???客户本人入???/出金/出金申请流水、代理账号正常读取直属流水???日期筛选???分页和 `totalRow` 汇???口径不变???
- `tests/Feature/FrontFlowDirectDepositApplicantBoundaryClosureModuleTest.php`
  - 新增普???客户访问现代直属客户入金流水拒绝样例???
  - 新增普???客户访问旧直属客户入金流水拒绝样例???
  - 两个样例均构造真实普通客户???直属子客户??? `deposit_records` 入金流水，并断言拒绝响应不包含目标订单号和子客户名称???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontFlowDirectDepositApplicantBoundaryClosureModuleTest.php` 首次失败，现代和旧直属客户入金流水入口实际都返回 `1000`锛屾湡鏈? `4006`，证明普通客户可借直属子客户进入代理专属入金流水读取路径???
- GREEN锛歚accountFlow` 对直属客???/直属代理流水类型增加代理账号校验后，普???客户访问现代和旧直属客户入金流水入口均返回 `ResponseCode::PERMISSION_DENIED`銆?
- RED：新增清单测试首次失败，命中???终清单缺少第 208 节???

### 当前证据
- `FrontFlowDirectDepositApplicantBoundaryClosureModuleTest` 覆盖真实普???客户登录??併?佺湡瀹? `parent_id` 直属子客户??佺湡瀹? `deposit_records` 入金流水、现??? `/api/front/flows/direct-deposits` 和旧 `user/flow/directDepositFlowSearch` 两个入口???
- 绗? 208 节只收紧直属客户入金流水读取入口；普通客户本人流水和代理账号正常直属客户流水不在本轮改动范围内???

### 剩余边界
- 本轮没有改动普???客户本人资金流水???代理账号正常直属客户入金流水???直属客户出金流水???直属代理入出金流水、直属客户入金导出???前端流水页签或数据库结构???
- 后续继续按独??? RED/GREEN 审计直属客户出金、直属代理入出金和直属客户入金导出等资金流水入口的普通用户申请人边界???

## 209. 2026-07-09 前台直属客户入金导出申请人边界闭???

### 本次处理目标
- 补齐 `FlowController::depositExport` 的申请人账号类型边界，确保只有代理账??? `account_type=1` 能导出直属客户入金流??? CSV銆?
- 验证普???客户即使名下存??? `parent_id` 直属子客户，且子客户有真??? `deposit_records` 入金记录，也不能通过??? Web `user/flow/depositExport` 生成直属客户入金导出文件???
- 避免普???用户模块???直属子客户关系导出入金订单号??佺敤鎴? ID、渠道???金额和入金时间???

### 本次变更文件
- `app/Http/Controllers/Front/FlowController.php`
  - `depositExport` 改为通过 `legacyFrontUserInfo` 读取当前登录用户资料???
  - 未登录或非代理账号返回旧前台兼容??? `msg=FAIL`，不再进入直属客??? scope 鍜? CSV 生成逻辑???
  - 移除只返回当前业务用??? ID 鐨? `legacyCurrentUserId` 私有方法，避免保留绕过账号类型的旧判断???
  - 保持代理账号正常导出直属客户入金流水、订单筛选???日期筛选??丆SV 文件名和下载路径不变???
- `tests/Feature/FrontFlowDirectDepositExportApplicantBoundaryClosureModuleTest.php`
  - 新增普???客户访??? `user/flow/depositExport` 拒绝样例???
  - 样例构???真实普通客户???直属子客户??? `deposit_records` 入金记录；RED 阶段若生成临??? CSV，会在断???前清理该文件???
- `tests/Feature/FrontFlowControllerCommentReadabilityTest.php`
  - 移除对已删除 `legacyCurrentUserId` 注释的静态约束???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontFlowDirectDepositExportApplicantBoundaryClosureModuleTest.php` 首次失败，导出入口实际返??? `direct_deposit_transactions_*` 文件标识，期??? `FAIL`，证明普通客户可借直属子客户生成入金流水 CSV銆?
- GREEN锛歚depositExport` 增加登录资料和代理账??? `account_type=1` 校验后，普???客户访问导出入口返??? `msg=FAIL`銆?
- RED：新增清单测试首次失败，命中???终清单缺少第 209 节???

### 当前证据
- `FrontFlowDirectDepositExportApplicantBoundaryClosureModuleTest` 覆盖真实普???客户登录??併?佺湡瀹? `parent_id` 直属子客户??佺湡瀹? `deposit_records` 入金流水和旧 `user/flow/depositExport` 导出入口???
- 绗? 208 节收紧直属客户入金流水读取入口，??? 209 节继续收紧同???数据面的 CSV 导出口???

### 剩余边界
- 本轮没有改动代理账号正常导出直属客户入金流水、普通客户本人资金流水???直属客户出金流水???直属代理入出金流水、下载文件安全路径或数据库结构???
- 后续继续按独??? RED/GREEN 审计直属客户出金、直属代理入出金和旧下载文件访问等资金流水入口的普???用户申请人边界???

## 210. 2026-07-09 前台旧下载文件访问申请人边界闭环

### 本次处理目标
- 补齐 `FlowController::downloadFile` 的申请人账号类型边界，确保只有代理账??? `account_type=1` 能下载前台直属客户入金导??? CSV銆?
- 验证普???客户即使知道真??? `storage/app/front_exports` 导出文件名，也不能??氳繃鏃? Web `user/flow/downloadfile/{file}/{role}` 直接下载文件内容???
- 避免普???用户模块绕过第 209 节的导出入口校验，???旧下载路由读取已有 CSV 中的订单号??佺敤鎴? ID、渠道???金额和入金时间???

### 本次变更文件
- `app/Http/Controllers/Front/FlowController.php`
  - `downloadFile` 增加 `Request` 参数，???过 `legacyFrontUserInfo` 读取当前登录用户资料???
  - 未登录或非代理账号统??? `abort(403)`，不再进入文件名清洗、`front_exports` 路径解析和二进制下载逻辑???
  - 保持代理账号正常下载路径、文件名安全字符过滤、强??? `.csv` 后缀??? 404 文件不存在响应不变???
- `tests/Feature/FrontFlowDownloadFileApplicantBoundaryClosureModuleTest.php`
  - 新增普???客户访问旧下载文件路由拒绝样例???
  - 样例写入真实 `storage/app/front_exports/*.csv` 临时文件，断???普???客户返??? 403 且响应不包含目标 CSV 内容???
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontFlowDownloadFileApplicantBoundaryClosureModuleTest.php` 首次失败，下载入口实际返??? `200`锛屾湡鏈? `403`，证明普通客户可直接下载真实存在的前台流水导??? CSV銆?
- GREEN锛歚downloadFile` 增加登录资料和代理账??? `account_type=1` 校验后，普???客户访??? `user/flow/downloadfile/{file}/{role}` 返回 `403`，响应不再包??? CSV 内容???
- RED：新增清单测试首次失败，命中???终清单缺少第 210 节???

### 当前证据
- `FrontFlowDownloadFileApplicantBoundaryClosureModuleTest` 覆盖真实普???客户登录??併?佺湡瀹? `front_exports` CSV 文件和旧 `user/flow/downloadfile/{file}/{role}` 下载入口???
- 绗? 209 节收紧直属客户入??? CSV 生成入口，第 210 节继续收紧同???导出文件的旧下载入口，普通客户不能绕过生成入口直接读取已有文件???

### 剩余边界
- 本轮没有改动代理账号正常下载导出文件、CSV 文件名生成规则???文件不存在 404 响应、普通客户本人资金流水或数据库结构???
- 后续继续按独??? RED/GREEN 审计直属客户出金和直属代理入出金等资金流水入口的普???用户申请人边界???

## 211. 2026-07-09 前台旧下载文件归属边界闭???

### 本次处理目标
- 补齐 `FlowController::depositExport` 涓? `downloadFile` 之间的导出文件归属边界，确保新生成的直属客户入金 CSV 只能由生成它的代理账号下载???
- 验证代理 A 生成 `storage/app/front_exports` 导出文件后，代理 B 即使知道文件标识，也不能通过??? Web `user/flow/downloadfile/{file}/{role}` 下载??? CSV銆?
- 避免代理账号之间靠文件名猜测跨账号读取直属客户入金订单号、用??? ID、渠道???金额和入金时间???

### 本次变更文件
- `app/Http/Controllers/Front/FlowController.php`
  - `depositExport` 鍦? CSV 生成成功后，同步写入同名 `.meta.json` 归属元数据，记录生成代理 `user_id`、文件名和创建时间???
  - `downloadFile` 在目??? CSV 存在且检测到 `.meta.json` 时，校验元数据中??? `user_id` 必须等于当前登录代理账号???
  - 元数据缺失的历史导出文件仍沿用第 210 节的代理账号校验，避免直接打断旧文件下载兼容路径???
  - 元数据写入失败时删除刚生成的 CSV 并返??? `msg=FAIL`，避免产生无法归属校验的新导出文件???
- `tests/Feature/FrontFlowDownloadFileOwnerBoundaryClosureModuleTest.php`
  - 新增代理 A 生成 CSV 后可正常下载的样例???
  - 新增代理 B 访问同一 CSV 琚? 403 拒绝的样例???
  - 新增???终清单记录测试，约束本节必须留档???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontFlowDownloadFileOwnerBoundaryClosureModuleTest.php` 首次失败，代??? B 下载代理 A 刚生成的 CSV 实际返回 `200`锛屾湡鏈? `403`，证明旧下载入口没有导出文件归属校验???
- GREEN锛歚depositExport` 写入 `.meta.json` 涓? `downloadFile` 按元数据校验当前代理后，生成代理下载返回 `200`，其它代理访问同???文件返回 `403`銆?
- RED：新增清单测试首次失败，命中???终清单缺少第 211 节???

### 当前证据
- `FrontFlowDownloadFileOwnerBoundaryClosureModuleTest` 覆盖真实代理登录态???真实直属子客户、真??? `deposit_records` 入金流水、真??? `depositExport` 生成 CSV 和旧 `user/flow/downloadfile/{file}/{role}` 下载入口???
- 绗? 210 节收紧普通客户下载入口，??? 211 节继续收紧代理账号之间的导出文件归属边界???

### 剩余边界
- 本轮没有改动 CSV 表头和内容???文件名随机规则、历史无元数据导出文件的代理下载兼容、普通客户本人资金流水或数据库结构???
- 后续继续按独??? RED/GREEN 审计直属客户出金和直属代理入出金等资金流水入口的剩余闭环测试???

## 212. 2026-07-09 前台直属客户出金流水申请人边界测试闭???

### 本次处理目标
- 涓? `FlowController::directWithdrawalFlowSearch` 补齐独立闭环测试，确认直属客户出金流水入口同样只允许代理账号 `account_type=1` 读取???
- 验证普???客户即使名下存??? `parent_id` 直属子客户，且子客户有真??? `withdraw_records` 出金记录，也不能通过现代 `/api/front/flows/direct-withdrawals` 读取该子客户出金流水???
- 验证??? Web `user/flow/directWithdrawalFlowSearch` 同样拒绝普???客户读取直属客户出金流水，避免泄漏出金订单号???用户名称???银行卡和出金金额???

### 本次变更文件
- `tests/Feature/FrontFlowDirectWithdrawApplicantBoundaryClosureModuleTest.php`
  - 新增普???客户访问现代直属客户出金流水拒绝样例???
  - 新增普???客户访问旧直属客户出金流水拒绝样例???
  - 两个样例均构造真实普通客户???直属子客户??? `withdraw_records` 出金流水，并断言拒绝响应不包含目标订单号和子客户名称???

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontFlowDirectWithdrawApplicantBoundaryClosureModuleTest.php` 在新增清单断???前直接???过，证明第 208 节已加入??? `accountFlow` 共享 `account_type=1` 校验覆盖??? `direct_withdraw` 类型???
- RED：新增清单测试首次失败，命中???终清单缺少第 212 节???
- GREEN：追加第 212 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontFlowDirectWithdrawApplicantBoundaryClosureModuleTest` 覆盖真实普???客户登录??併?佺湡瀹? `parent_id` 直属子客户??佺湡瀹? `withdraw_records` 出金流水、现??? `/api/front/flows/direct-withdrawals` 和旧 `user/flow/directWithdrawalFlowSearch` 两个入口???
- 绗? 208 节收紧共享读取???辑，第 212 节把直属客户出金流水作为独立入口补齐闭环测试证据???

### 剩余边界
- 本轮没有改动 `FlowController` 生产逻辑、普通客户本人出金流水???代理账号正常直属客户出金流水???直属代理入出金流水或数据库结构???
- 后续继续按独立闭环测试审计直属代理入金和直属代理出金流水入口???

## 213. 2026-07-09 前台直属代理入出金流水申请人边界测试闭环

### 本次处理目标
- 涓? `FlowController::directDepositFlowSearch` 鍜? `directWithdrawalFlowSearch` 补齐直属代理流水独立闭环测试，确??? `direct_agents_deposit` 涓? `direct_agents_withdraw` 均只允许代理账号 `account_type=1` 读取???
- 验证普???客户即使名下存??? `parent_id` 直属代理子账号，且该代理子账号有真实 `deposit_records` 鎴? `withdraw_records` 记录，也不能通过现代直属代理流水接口读取???
- 验证??? Web `user/flow/directAgentsDepositFlowSearch` 涓? `user/flow/directAgentsWithdrawalFlowSearch` 同样拒绝普???客户读取直属代理入出金流水???

### 本次变更文件
- `tests/Feature/FrontFlowDirectAgentApplicantBoundaryClosureModuleTest.php`
  - 新增普???客户访问现代与旧直属代理入金流水拒绝样例???
  - 新增普???客户访问现代与旧直属代理出金流水拒绝样例???
  - 两个样例分别构???真实普通客户???直属代理子账号、真??? `deposit_records` 鎴? `withdraw_records` 流水，并断言拒绝响应不包含目标订单号和代理子账号名称???

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontFlowDirectAgentApplicantBoundaryClosureModuleTest.php` 在新增清单断???前直接???过，证明第 208 节已加入??? `accountFlow` 共享 `account_type=1` 校验覆盖??? `direct_agents_deposit` 鍜? `direct_agents_withdraw` 两类入口???
- RED：新增清单测试首次失败，命中???终清单缺少第 213 节???
- GREEN：追加第 213 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontFlowDirectAgentApplicantBoundaryClosureModuleTest` 覆盖真实普???客户登录??併?佺湡瀹? `parent_id` 直属代理子账号???真实入金和出金流水、现??? `/api/front/flows/direct-agent-deposits`銆乣/api/front/flows/direct-agent-withdrawals` 以及??? `user/flow/directAgentsDepositFlowSearch`銆乣user/flow/directAgentsWithdrawalFlowSearch` 四个入口???
- 绗? 212 节补齐直属客户出金流水测试，??? 213 节补齐直属代理入出金流水测试，资金流水读取入口的普???客户申请人边界测试覆盖继续收拢???

### 剩余边界
- 本轮没有改动 `FlowController` 生产逻辑、普通客户本人资金流水???代理账号正常直属代理入出金流水、前端流水页签或数据库结构???
- 后续继续按旧项目模块清单审计下一个前台代???/普??氱敤鎴?/后台管理员模块剩余入口???

## 214. 2026-07-09 前台旧大代理代理列表与订单列表参数冒充测试闭???

### 本次处理目标
- 为旧大代理代理列表与订单列表补齐参数冒充闭环测试，确认第 207 节的 `currentBigAgent` session-only 身份来源覆盖 `proxySearch`銆乣proxySearchBySub`銆乣closeOrderSearch` 鍜? `openOrderSearch`銆?
- 验证普???客户即使携带真??? `big_agent_id` 鎴? `bigAgentId`，也不能读取大代??? `sub_agent_ids` 下的代理列表???
- 验证普???客户即使知道真实大代理 ID，且大代理可见代理网络下存在真实客户交易订单，也不能读取旧大代理已平仓或未平仓订单列表???

### 本次变更文件
- `tests/Feature/FrontBigNumberListAndOrderApplicantBoundaryClosureModuleTest.php`
  - 新增普???客户参数冒充访??? `user/agents/proxy/proxySearch` 涓? `user/agents/proxy/proxySearchBySub` 的拒绝样例???
  - 新增普???客户参数冒充访??? `user/agents/close/closeOrderSearch` 涓? `user/agents/open/openOrderSearch` 的拒绝样例???
  - 样例构??犵湡瀹? `big_agents` 记录、可见代理子账号、可见客户和真实 `user_trades` 寮?平仓订单，并断言响应为空且不包含目标代理、客户或订单号???

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontBigNumberListAndOrderApplicantBoundaryClosureModuleTest.php` 在新增清单断???前直接???过，证明第 207 节已移除请求参数兜底后的 `currentBigAgent` 覆盖旧大代理代理列表和订单列表???
- RED：新增清单测试首次失败，命中???终清单缺少第 214 节???
- GREEN：追加第 214 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontBigNumberListAndOrderApplicantBoundaryClosureModuleTest` 覆盖真实普???客户登录??併?佺湡瀹? `big_agents` 记录、真实可见代理和客户、真实开平仓 `user_trades` 订单，以及四个旧大代??? Ajax 入口???
- 绗? 207 节收紧旧大代理持仓搜索身份来源，??? 214 节补齐同???身份来源规则在代理列表和订单列表上的剩余测试证据???

### 剩余边界
- 本轮没有改动 `BigNumberController` 生产逻辑、大代理账号正常登录、session 读取、代理列表???订单列表???持仓搜索或数据库结构???
- 后续继续按旧项目模块清单审计下一个前台代???/普??氱敤鎴?/后台管理员模块剩余入口???

## 215. 2026-07-09 前台持仓主汇总钻取申请人边界闭环

### 本次处理目标
- 补齐 `PositionController::positionSummary` 的钻取申请人账号类型边界，确保只有代理账??? `account_type=1` 能按 `target_id`銆乣userPId` 鎴? `user_id` 展开下级代理持仓汇??汇??
- 验证普???客户即使名下存??? `parent_id` 直属代理子账号，也不能???过现代 `/api/front/positions/summary` 读取该子代理持仓汇??汇??
- 验证??? Web `user/position/positionSummarySearch` 同样拒绝普???客户钻取直属代理持仓汇总，避免泄漏代理子账??? ID、名称和汇???数据???

### 本次变更文件
- `app/Http/Controllers/Front/PositionController.php`
  - `positionSummary` 改为通过 `legacyFrontUserLogin` 读取当前登录记录，保留未登录兼容认证错误???
  - 当请求目标不是当前用户本人时，非代理账号返回 `ResponseCode::PERMISSION_DENIED`，不再进??? `FrontLegacyData::userScopeIds` 的下级代理钻取??昏緫銆?
  - 保持普???客户本??? MT4 汇??汇??代理账号正常钻取下级代理??乣parent_id` fallback、面包屑链路、分页和汇???口径不变???
- `tests/Feature/FrontPositionSummaryApplicantBoundaryClosureModuleTest.php`
  - 新增普???客户访问现代持仓主汇???钻取入口拒绝样例???
  - 新增普???客户访问旧持仓主汇总搜索入口拒绝样例???
  - 两个样例均构造真实普通客户和 `parent_id` 直属代理子账号，并断???拒绝响应不包含目标子代理 ID 或名称???

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontPositionSummaryApplicantBoundaryClosureModuleTest.php` 首次失败，现代和旧主持仓汇???入口实际都返回 `1000`锛屾湡鏈? `4006`，证明普通客户可借直属代理进入代理专属持仓汇总钻取路径???
- GREEN锛歚positionSummary` 增加非本人钻取的代理账号 `account_type=1` 校验后，普???客户访问现代和旧持仓汇总钻取入口均返回 `ResponseCode::PERMISSION_DENIED`銆?
- RED：新增清单测试首次失败，命中???终清单缺少第 215 节???
- GREEN：追加第 215 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontPositionSummaryApplicantBoundaryClosureModuleTest` 覆盖真实普???客户登录??併?佺湡瀹? `parent_id` 直属代理子账号??佺幇浠? `/api/front/positions/summary` 和旧 `user/position/positionSummarySearch` 两个入口???
- 绗? 205 节已收紧直属代理持仓汇??诲叆鍙ｏ紝绗? 206 节已收紧持仓交易详情入口，第 215 节继续闭合主持仓汇???钻取入口的普???客户申请人边界???

### 剩余边界
- 本轮没有改动普???客户本人持仓汇总???代理账号正常持仓汇总钻取???持仓详情???旧页面入口、前端持仓页或数据库结构???
- 后续继续按旧项目模块清单审计下一个前台代???/普??氱敤鎴?/后台管理员模块剩余入口???

## 216. 2026-07-09 前台资料关系链申请人边界闭环

### 本次处理目标
- 补齐 `ProfileController::relationshipText` 的申请人可见范围边界，确保关系链查询只能读取当前用户本人或当前代理可见下级???
- 验证普???客户即使知道其它真实用??? ID，也不能通过现代 `/api/front/profile/relationship-path` 读取无关用户代理链???
- 验证??? Web `user/relationShipHtml` 同样拒绝普???客户读取无关用户关系链，避免泄漏上级代??? ID 和目标用??? ID銆?

### 本次变更文件
- `app/Http/Controllers/Front/ProfileController.php`
  - `relationshipText` 在读取目标用户前增加当前登录人可见???校验???
  - 新增 `canViewRelationshipTarget`：本人可读取本人关系链；代理账号 `account_type=1` 可读??? `FrontLegacyData::userScopeIds` 范围内下级；普???客户读取非本人目标时返回空关系链???
  - 保持关系链生成顺序??乣family_tree` 优先、`agent_descendants` 涓? `user_infos.parent_id` fallback、现代和??? `real` 字段响应结构不变???
- `tests/Feature/FrontProfileRelationshipApplicantBoundaryClosureModuleTest.php`
  - 新增普???客户访问现代无关用户关系链拒绝样例???
  - 新增普???客户访问旧无关用户关系??? HTML 拒绝样例???
  - 两个样例均构造真实普通客户???无关上级代理和目标客户，并断言响应 `real` 为空且不包含目标链路 ID銆?

### TDD 执行记录
- RED锛歚vendor\bin\phpunit tests\Feature\FrontProfileRelationshipApplicantBoundaryClosureModuleTest.php` 首次失败，现代入口返??? `412160101 -> 412160102`，旧入口返回 `412160201->412160202`，证明普通客户可枚举无关用户代理链???
- GREEN锛歚relationshipText` 增加登录人可见范围校验后，普通客户访问现代和旧无关用户关系链入口均返回空 `real`銆?
- RED：新增清单测试首次失败，命中???终清单缺少第 216 节???
- GREEN：追加第 216 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontProfileRelationshipApplicantBoundaryClosureModuleTest` 覆盖真实普???客户登录??併??真实无关代理链、现??? `/api/front/profile/relationship-path` 和旧 `user/relationShipHtml` 两个入口???
- 绗? 175 节已覆盖关系??? `parent_id` fallback，第 216 节继续收紧关系链读取入口的申请人可见范围???

### 剩余边界
- 本轮没有改动本人关系链展示???代理账号正常读取下级关系链、关系链文本格式、资料页其它上传/修改入口或数据库结构???
- 后续继续按旧项目模块清单审计下一个前台代???/普??氱敤鎴?/后台管理员模块剩余入口???

## 217. 2026-07-09 前台礼品收货地址归属边界测试闭环

### 本次处理目标
- 涓? `GiftController::updateAddress` 涓? `GiftController::deleteAddress` 补齐独立闭环测试，确认礼品收货地???更新和删除只能作用于当前登录用户自己的地???銆?
- 验证普???客户即使知道其它用户真实地??? ID，也不能通过现代 `/api/front/gift-addresses/{address}` 修改或删除他人收货地???銆?
- 验证??? Web `user/address/update` 传入他人 `rec_id` 时同样返回未找到，避免旧参数兼容路径绕过归属过滤???

### 本次变更文件
- `tests/Feature/FrontGiftAddressOwnerBoundaryClosureModuleTest.php`
  - 新增普???客户访问现代礼品地???更新入口修改他人地址的拒绝样例???
  - 新增普???客户访问现代礼品地???删除入口删除他人地址的拒绝样例???
  - 新增普???客户??氳繃鏃? `user/address/update` 涓? `rec_id` 修改他人地址的拒绝样例???
  - 样例均构造真实登录客户???真实地???归属用户和真??? `user_addresses` 记录，并断言拒绝后数据库原记录未被修改或软删除???

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontGiftAddressOwnerBoundaryClosureModuleTest.php` 的行为样例在新增清单断言前已通过，证明现??? `GiftController::updateAddress` 涓? `GiftController::deleteAddress` 已???过 `user_id + id` 查询限制地址归属???
- RED：新增清单测试首次失败，命中???终清单缺少第 217 节???
- GREEN：追加第 217 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontGiftAddressOwnerBoundaryClosureModuleTest` 覆盖真实普???客户登录??併??真实他??? `user_addresses` 记录、现??? `/api/front/gift-addresses/{address}` 更新/删除入口和旧 `user/address/update` 更新入口???
- `addressUpdate` 旧入口继续复用现??? `updateAddress` 归属查询，因此旧 `rec_id` 兼容参数不能越权修改他人地址???

### 剩余边界
- 本轮没有改动 `GiftController` 生产逻辑、本人地???新增/更新/删除流程、礼品申请流程???前端礼品地???表单或数据库结构???
- 后续继续按旧项目模块清单审计下一个前台代???/普??氱敤鎴?/后台管理员模块剩余入口???

## 218. 2026-07-09 前台礼品发货历史归属边界测试闭环

### 本次处理目标
- 涓? `GiftController::giftList` 涓? `GiftController::giftSearch` 补齐独立闭环测试，确认礼品发货历史只能读取当前登录用户自己的 `gift_shipments` 记录???
- 验证现代 `/api/front/gifts` 在存在同名收件人、同名礼品和同日期区间的他人发货记录时，只返回当前用户自己的 `shipped_gifts`銆?
- 验证??? Web `user/gift/search` 同样只返回当前用户自己的发货历史，避免旧礼品列表通过筛???条件读到其它用户物流单号和发货记录???

### 本次变更文件
- `tests/Feature/FrontGiftShipmentOwnerBoundaryClosureModuleTest.php`
  - 新增现代礼品列表发货历史归属样例???
  - 新增旧礼品发货搜索归属样例???
  - 两个样例均构造真实登录客户???真实其它客户和同筛选条件下的两??? `gift_shipments` 记录，并断言响应只包含本人物流单号，不包含他人物流单号???

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontGiftShipmentOwnerBoundaryClosureModuleTest.php` 的行为样例在清单补录前已通过，证明现??? `giftShipmentQuery($userId, $request)` 已???过 `gift_shipments.user_id` 限制发货历史归属???
- RED：新增清单测试首次失败，命中???终清单缺少第 218 节???
- GREEN：追加第 218 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontGiftShipmentOwnerBoundaryClosureModuleTest` 覆盖真实普???客户登录??併??真实本人和他人 `gift_shipments` 记录、现??? `/api/front/gifts` 和旧 `user/gift/search` 两个入口???
- 现代组合接口中的 `data.shipped_gifts.data` 与旧分页接口中的 `data.list.data` 均只返回当前用户发货历史???

### 剩余边界
- 本轮没有改动 `GiftController` 生产逻辑、可兑换礼品目录、本人礼品发货历史筛选???后台礼品发???/发货流程或数据库结构???
- 后续继续按旧项目模块清单审计下一个前台代???/普??氱敤鎴?/后台管理员模块剩余入口???

## 219. 2026-07-09 前台???户申请归属边界测试闭???

### 本次处理目标
- 涓? `CancelController::status` 涓? `CancelController::apply` 补齐参数冒充归属边界测试，确认销户状态读取和???户申请创建都只使用当前登录用户身份???
- 验证现代 `/api/front/account/cancellation` 即使携带其它用户 `user_id` 鎴? `userId` 参数，也只返回当前用户自己的???近一??? `cancel_applies` 记录???
- 验证现代 `/api/front/account/cancellation-applications` 提交时即使携带其它用??? ID，也只能为当前登录用户创建销户申请，不能写入他人账号???

### 本次变更文件
- `tests/Feature/FrontCancelApplyOwnerBoundaryClosureModuleTest.php`
  - 新增???户状态读取忽略伪造用??? ID 的样例???
  - 新增???户申请创建忽略伪造用??? ID 的样例???
  - 样例均构造真实当前客户???真实其它客户和真实 `cancel_applies` 记录，并断言响应或数据库写入只落在当前登录用户名下???

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontCancelApplyOwnerBoundaryClosureModuleTest.php` 的行为样例在清单补录前已通过，证明现??? `legacyFrontUserInfo($request)` 身份来源已覆盖状态读取和申请创建???
- RED：新增清单测试首次失败，命中???终清单缺少第 219 节???
- GREEN：追加第 219 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontCancelApplyOwnerBoundaryClosureModuleTest` 覆盖真实普???客户登录??併??真实其它客户??佺湡瀹? `cancel_applies` 记录、现??? `/api/front/account/cancellation` 鍜? `/api/front/account/cancellation-applications` 两个入口???
- 状???读取不会泄漏他人销户原因，申请创建不会因请求参数中??? `user_id` 鎴? `userId` 写入他人???户申请???

### 剩余边界
- 本轮没有改动 `CancelController` 生产逻辑、旧???户验证码校验、重复待审校验???未平仓/资金/鍑?值拦截???后台销户审核或数据库结构???
- 后续继续按旧项目模块清单审计下一个前台代???/普??氱敤鎴?/后台管理员模块剩余入口???

## 220. 2026-07-09 前台旧销户提交归属边界测试闭???

### 本次处理目标
- 涓? `CancelController::ajaxCancelAccount` 补齐旧前台销户提交归属边界测试，确认??? `user/center/ajaxCancelAccount` 入口也只使用当前登录用户身份???
- 验证旧入口在身份证???手机号、邮箱???密码和 `cancelCode` 均匹配当前用户时，即使请求携带其它用??? `user_id` 鎴? `userId`，也只能为当前用户创建销户申请???
- 验证旧原因字??? `cancelRemark` 继续写入当前用户 `cancel_applies.cancel_remark`，不能被参数冒充写入他人账号???

### 本次变更文件
- `tests/Feature/FrontCancelLegacyApplyOwnerBoundaryClosureModuleTest.php`
  - 新增旧销户提交忽略伪造用??? ID 的样例???
  - 样例构???真实当前客户???真实其它客户??佺湡瀹? `user_auths` 身份证记录和??? session `cancelCode`，并断言旧响应成功后数据库只写入当前用户???户申请???

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontCancelLegacyApplyOwnerBoundaryClosureModuleTest.php` 的行为样例在清单补录前已通过，证明旧 `ajaxCancelAccount` 已???过 `legacyFrontUserInfo($request)` 与当前登录记录完成归属绑定???
- RED：新增清单测试首次失败，命中???终清单缺少第 220 节???
- GREEN：追加第 220 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontCancelLegacyApplyOwnerBoundaryClosureModuleTest` 覆盖真实普???客户登录??併??真实其它客户??佺湡瀹? `user_auths` 身份记录、旧 session `cancelCode` 和旧 `user/center/ajaxCancelAccount` 提交入口???
- 旧入口响??? `msg=SUC` 后，`cancel_applies` 只新增当前登录用户记录，不会根据请求参数中的 `user_id` 鎴? `userId` 写入他人???户申请???

### 剩余边界
- 本轮没有改动 `CancelController` 生产逻辑、现代销户提交???旧验证码发送???旧身份校验字段、重复待审校验???未平仓/资金/鍑?值拦截或数据库结构???
- 后续继续按旧项目模块清单审计下一个前台代???/普??氱敤鎴?/后台管理员模块剩余入口???

## 221. 2026-07-09 前台???户身份校验归属边界测试闭???

### 本次处理目标
- 涓? `ProfileController::cancelVerifyInfo` 补齐???户前身份校验归属边界测试，确认现代和旧身份校验入口都只校验当前登录用户资料???
- 验证现代 `/api/front/profile/verification-cancellation-checks` 即使携带其它用户 `user_id` 鎴? `userId`，当前用户提交自己的手机号???邮箱和身份证号仍按当前用户通过???
- 验证??? Web `user/center/cancelVerifyInfo` 即使携带其它用户 ID，提交其它用户手机号、邮箱和身份证号也会按当前用户失败，不能把其它用户资料当作当前用户资料??氳繃銆?

### 本次变更文件
- `tests/Feature/FrontCancelVerificationOwnerBoundaryClosureModuleTest.php`
  - 新增现代???户身份校验忽略伪造用??? ID 的???过样例???
  - 新增旧销户身份校验拒绝伪造其它用户资料的失败样例???
  - 样例均构造真实当前客户???真实其它客户和真实 `user_auths` 身份证记录，并断???校验结果只受当前登录用户资料影响???

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontCancelVerificationOwnerBoundaryClosureModuleTest.php` 的行为样例在清单补录前已通过，证明现??? `currentProfileContext($request)` 涓? `UserAuth::where('user_id', $userInfo->user_id)` 已按当前登录用户绑定身份校验???
- RED：新增清单测试首次失败，命中???终清单缺少第 221 节???
- GREEN：追加第 221 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontCancelVerificationOwnerBoundaryClosureModuleTest` 覆盖真实普???客户登录??併??真实其它客户??佺湡瀹? `user_auths` 身份记录、现??? `/api/front/profile/verification-cancellation-checks` 和旧 `user/center/cancelVerifyInfo` 两个入口???
- 现代和旧???户身份校验都不会信任请求参数中的 `user_id` 鎴? `userId` 来切换校验对象???

### 剩余边界
- 本轮没有改动 `ProfileController` 生产逻辑、销户验证码发??併??资料修???/银行卡换绑验证码、销户提交???后台销户审核或数据库结构???
- 后续继续按旧项目模块清单审计下一个前台代???/普??氱敤鎴?/后台管理员模块剩余入口???

## 222. 2026-07-09 前台???户验证码发???归属边界测试闭???

### 本次处理目标
- 涓? `ProfileController::cancelVerifyPassSendCode` 补齐???户验证码发???归属边界测试，确认验证码缓存只按当前登录用户写入???
- 验证现代 `/api/front/profile/verification-cancellation/verification-codes` 即使携带其它用户 `user_id` 鎴? `userId`，只要邮箱匹配当前用户，就只写入当前用户 `front_profile_cancel_code:{user_id}` 缓存???
- 验证??? Web `user/center/cancelVerifyPassSendCode` 即使携带其它用户 ID，提交其它用户邮箱也会失败，不会给当前用户或其它用户写入???户验证码缓存???

### 本次变更文件
- `tests/Feature/FrontCancelVerificationCodeOwnerBoundaryClosureModuleTest.php`
  - 新增现代???户验证码发???忽略伪造用??? ID 的缓存归属样例???
  - 新增旧销户验证码发???拒绝伪造其它用户邮箱的样例???
  - 样例使用 `Mail::fake()` 避免真实发信，并直接断言 `front_profile_cancel_code:{user_id}` 缓存只落在当前登录用户名下???

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontCancelVerificationCodeOwnerBoundaryClosureModuleTest.php` 的行为样例在清单补录前已通过，证明现??? `sendLegacyProfileCode($request, 'cancel')` 已???过 `currentProfileContext($request)` 和当前登录邮箱完成归属绑定???
- RED：新增清单测试首次失败，命中???终清单缺少第 222 节???
- GREEN：追加第 222 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontCancelVerificationCodeOwnerBoundaryClosureModuleTest` 覆盖真实普???客户登录??併??真实其它客户??佺幇浠? `/api/front/profile/verification-cancellation/verification-codes` 和旧 `user/center/cancelVerifyPassSendCode` 两个入口???
- 现代入口只写入当前用??? `front_profile_cancel_code:{当前用户 ID}`；旧入口使用其它用户邮箱时返??? `status=false`，且不会写入当前用户或其它用户验证码缓存???

### 剩余边界
- 本轮没有改动 `ProfileController` 生产逻辑、真实邮件发送???资料修???/银行卡换绑验证码、销户身份校验???销户提交或数据库结构???
- 后续继续按旧项目模块清单审计下一个前台代???/普??氱敤鎴?/后台管理员模块剩余入口???

## 223. 2026-07-09 前台资料更新归属边界测试闭环

### 本次处理目标
- 涓? `ProfileController::updateProfile` 补齐资料更新归属边界测试，确认现代资料保存入口只更新当前登录用户资料???
- 验证现代 `PATCH /api/front/profile` 即使携带其它用户 `user_id` 鎴? `userId`，也只能写入当前用户??? `user_infos` 基础资料???
- 验证身份证号更新同样只写入当前用户的 `user_auths.id_card_no`，不能???过请求参数冒充更新其它用户认证资料???

### 本次变更文件
- `tests/Feature/FrontProfileUpdateOwnerBoundaryClosureModuleTest.php`
  - 新增资料更新忽略伪??犵敤鎴? ID 的样例???
  - 样例构???真实当前客户???真实其它客户??佺湡瀹? `user_infos` 鍜? `user_auths` 记录，并断言更新后只有当前登录用户资料变化，其它用户资料保持原??笺??

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontProfileUpdateOwnerBoundaryClosureModuleTest.php` 的行为样例在清单补录前已通过，证明现??? `request->user('user')` 涓? `$userLogin->userInfo` 已把资料更新绑定到当前登录用户???
- RED：新增清单测试首次失败，命中???终清单缺少第 223 节???
- GREEN：追加第 223 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontProfileUpdateOwnerBoundaryClosureModuleTest` 覆盖真实普???客户登录??併??真实其它客户??佺幇浠? `PATCH /api/front/profile` 资料更新入口，以??? `user_infos` 涓? `user_auths` 两张写入表???
- 请求参数中的 `user_id` 鎴? `userId` 不会切换资料更新对象；姓名???电话??佹?у埆銆佸湴鍧?和身份证号均只落在当前登录用户???

### 剩余边界
- 本轮没有改动 `ProfileController` 生产逻辑、旧资料上传/认证/联系方式入口、资料页前端表单、密码修改??侀偖绠?/手机号修改或数据库结构???
- 后续继续按旧项目模块清单审计下一个前台代???/普??氱敤鎴?/后台管理员模块剩余入口???

## 224. 2026-07-09 前台联系方式更新归属边界测试闭环

### 本次处理目标
- 涓? `ProfileController::updatePhoneEmailInfo` 补齐联系方式更新归属边界测试，确认手机号和邮箱修改都只作用于当前登录用户???
- 验证现代 `/api/front/profile/contact-info` 修改邮箱时，即使携带其它用户 `user_id` 鎴? `userId`，也只能更新当前登录用户??? `user_logins.email`銆?
- 验证??? Web `user/center/updatePhoneEmailInfo` 修改手机号时，即使携带其它用??? ID，也只能更新当前登录用户??? `user_infos.phone`銆?

### 本次变更文件
- `tests/Feature/FrontProfileContactInfoOwnerBoundaryClosureModuleTest.php`
  - 新增现代联系方式邮箱修改忽略伪??犵敤鎴? ID 的样例???
  - 新增旧联系方式手机号修改忽略伪??犵敤鎴? ID 的样例???
  - 两个样例均构造真实当前客户和真实其它客户，并断言写入只落在当前登录用户，其它用户邮箱和手机号保持原??笺??

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontProfileContactInfoOwnerBoundaryClosureModuleTest.php` 的行为样例在清单补录前已通过，证明现??? `request->user('user')` 涓? `$userLogin->userInfo` 已把联系方式更新绑定到当前登录用户???
- RED：新增清单测试首次失败，命中???终清单缺少第 224 节???
- GREEN：追加第 224 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontProfileContactInfoOwnerBoundaryClosureModuleTest` 覆盖真实普???客户登录??併??真实其它客户??佺幇浠? `/api/front/profile/contact-info` 和旧 `user/center/updatePhoneEmailInfo` 两个入口???
- 请求参数中的 `user_id` 鎴? `userId` 不会切换联系方式更新对象；邮箱只更新当前用户登录记录，手机号只更新当前用户资料记录???

### 剩余边界
- 本轮没有改动 `ProfileController` 生产逻辑、联系方式验证码发??併??资料基???字段更新、上传认证入口???资料页前端表单或数据库结构???
- 后续继续按旧项目模块清单审计下一个前台代???/普??氱敤鎴?/后台管理员模块剩余入口???

## 225. 2026-07-09 前台资料修改验证码发送归属边界测试闭???

### 本次处理目标
- 涓? `ProfileController::updVerifyPassSendCode` 补齐资料修改验证码发送归属边界测试，确认验证码缓存只按当前登录用户写入???
- 验证现代 `/api/front/profile/verification-password/verification-codes` 即使携带其它用户 `user_id` 鎴? `userId`，只要邮箱匹配当前用户，就只写入当前用户 `front_profile_updverify_code:{user_id}` 缓存???
- 验证??? Web `user/center/updVerifyPassSendCode` 即使携带其它用户 ID，提交其它用户邮箱也会失败，不会给当前用户或其它用户写入资料修改验证码缓存???

### 本次变更文件
- `tests/Feature/FrontProfileUpdateVerificationCodeOwnerBoundaryClosureModuleTest.php`
  - 新增现代资料修改验证码发送忽略伪造用??? ID 的缓存归属样例???
  - 新增旧资料修改验证码发???拒绝伪造其它用户邮箱的样例???
  - 样例使用 `Mail::fake()` 避免真实发信，并直接断言 `front_profile_updverify_code:{user_id}` 缓存只落在当前登录用户名下???

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontProfileUpdateVerificationCodeOwnerBoundaryClosureModuleTest.php` 的行为样例在清单补录前已通过，证明现??? `sendLegacyProfileCode($request, 'updverify')` 已???过 `currentProfileContext($request)` 和当前登录邮箱完成归属绑定???
- RED：新增清单测试首次失败，命中???终清单缺少第 225 节???
- GREEN：追加第 225 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontProfileUpdateVerificationCodeOwnerBoundaryClosureModuleTest` 覆盖真实普???客户登录??併??真实其它客户??佺幇浠? `/api/front/profile/verification-password/verification-codes` 和旧 `user/center/updVerifyPassSendCode` 两个入口???
- 现代入口只写入当前用??? `front_profile_updverify_code:{当前用户 ID}`；旧入口使用其它用户邮箱时返??? `status=false`，且不会写入当前用户或其它用户验证码缓存???

### 剩余边界
- 本轮没有改动 `ProfileController` 生产逻辑、真实邮件发送???联系方式更新???资料基???字段更新、上传认证入口或数据库结构???
- 后续继续按旧项目模块清单审计下一个前台代???/普??氱敤鎴?/后台管理员模块剩余入口???

## 226. 2026-07-09 前台银行卡换绑验证码发???归属边界测试闭???

### 本次处理目标
- 涓? `ProfileController::changeBankCardSendCode` 补齐银行卡换绑验证码发???归属边界测试，确认验证码缓存只按当前登录用户写入???
- 验证现代 `/api/front/profile/bank-card-change/verification-codes` 即使携带其它用户 `user_id` 鎴? `userId`，只要邮箱匹配当前用户，就只写入当前用户 `front_profile_change_code:{user_id}` 缓存???
- 验证??? Web `user/center/changeBankCardSendCode` 即使携带其它用户 ID，提交其它用户邮箱也会失败，不会给当前用户或其它用户写入银行卡换绑验证码缓存???

### 本次变更文件
- `tests/Feature/FrontProfileBankChangeVerificationCodeOwnerBoundaryClosureModuleTest.php`
  - 新增现代银行卡换绑验证码发???忽略伪造用??? ID 的缓存归属样例???
  - 新增旧银行卡换绑验证码发送拒绝伪造其它用户邮箱的样例???
  - 样例使用 `Mail::fake()` 避免真实发信，并直接断言 `front_profile_change_code:{user_id}` 缓存只落在当前登录用户名下???

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontProfileBankChangeVerificationCodeOwnerBoundaryClosureModuleTest.php` 的行为样例在清单补录前已通过，证明现??? `sendLegacyProfileCode($request, 'change')` 已???过 `currentProfileContext($request)` 和当前登录邮箱完成归属绑定???
- RED：新增清单测试首次失败，命中???终清单缺少第 226 节???
- GREEN：追加第 226 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontProfileBankChangeVerificationCodeOwnerBoundaryClosureModuleTest` 覆盖真实普???客户登录??併??真实其它客户??佺幇浠? `/api/front/profile/bank-card-change/verification-codes` 和旧 `user/center/changeBankCardSendCode` 两个入口???
- 现代入口只写入当前用??? `front_profile_change_code:{当前用户 ID}`；旧入口使用其它用户邮箱时返??? `status=false`，且不会写入当前用户或其它用户验证码缓存???

### 剩余边界
- 本轮没有改动 `ProfileController` 生产逻辑、真实邮件发送???银行卡换绑资料提交、银行卡换绑校验、资料基???字段更新或数据库结构???
- 后续继续按旧项目模块清单审计下一个前台代???/普??氱敤鎴?/后台管理员模块剩余入口???

## 227. 2026-07-09 前台银行卡换绑邮箱校验归属边界测试闭???

### 本次处理目标
- 涓? `ProfileController::changeBankCardVerifyCode` 补齐银行卡换绑前邮箱校验归属边界测试，确认校验对象只来自当前登录用户???
- 验证现代 `/api/front/profile/bank-card-change/verification-checks` 即使携带其它用户 `user_id` 鎴? `userId`，提交当前用户邮箱仍按当前用户??氳繃銆?
- 验证??? Web `user/center/changeBankCardVerifyCode` 即使携带其它用户 ID，提交其它用户邮箱也会按当前用户失败，不能把其它用户邮箱当作当前用户资料通过???

### 本次变更文件
- `tests/Feature/FrontProfileBankChangeVerificationCheckOwnerBoundaryClosureModuleTest.php`
  - 新增现代银行卡换绑邮箱校验忽略伪造用??? ID 的???过样例???
  - 新增旧银行卡换绑邮箱校验拒绝伪???其它用户邮箱的失败样例???
  - 两个样例均构造真实当前客户和真实其它客户，并断言校验结果只受当前登录用户邮箱影响???

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontProfileBankChangeVerificationCheckOwnerBoundaryClosureModuleTest.php` 的行为样例在清单补录前已通过，证明现??? `currentProfileContext($request)` 已把银行卡换绑邮箱校验绑定到当前登录用户???
- RED：新增清单测试首次失败，命中???终清单缺少第 227 节???
- GREEN：追加第 227 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontProfileBankChangeVerificationCheckOwnerBoundaryClosureModuleTest` 覆盖真实普???客户登录??併??真实其它客户??佺幇浠? `/api/front/profile/bank-card-change/verification-checks` 和旧 `user/center/changeBankCardVerifyCode` 两个入口???
- 请求参数中的 `user_id` 鎴? `userId` 不会切换邮箱校验对象；旧入口使用其它用户邮箱时返??? `msg=FAIL`銆乣err=useremail`銆乣col=useremail`銆?

### 剩余边界
- 本轮没有改动 `ProfileController` 生产逻辑、银行卡换绑验证码发送???银行卡换绑资料提交、真实邮件发送或数据库结构???
- 后续继续按旧项目模块清单审计下一个前台代???/普??氱敤鎴?/后台管理员模块剩余入口???

## 228. 2026-07-09 前台联系方式唯一性校验归属边界测试闭???

### 本次处理目标
- 涓? `ProfileController::updateVerifyInfo` 补齐手机号和邮箱唯一性校验的归属边界测试，确认排除对象始终是当前登录用户???
- 验证现代 `/api/front/profile/verification-checks` 校验当前用户自己的手机号时，即使携带其它用户 `user_id` 鎴? `userId`，也不会把当前手机号误判为重复???
- 验证??? Web `user/center/updateVerifyInfo` 校验其它用户邮箱时，即使携带其它用户 ID，也会按当前用户排除规则返回重复，不能???过伪???参数把其它用户排除掉???

### 本次变更文件
- `tests/Feature/FrontProfileContactUniquenessOwnerBoundaryClosureModuleTest.php`
  - 新增现代手机号唯???性校验忽略伪造用??? ID 的???过样例???
  - 新增旧邮箱唯???性校验拒绝伪造其它用户排除对象的失败样例???
  - 两个样例均构造真实当前客户和真实其它客户，并断言唯一性校验只排除当前登录用户???

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontProfileContactUniquenessOwnerBoundaryClosureModuleTest.php` 的行为样例在清单补录前已通过，证明现??? `currentProfileContext($request)` 已把手机号和邮箱唯一性校验的排除对象绑定到当前登录用户???
- RED：新增清单测试首次失败，命中???终清单缺少第 228 节???
- GREEN：追加第 228 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontProfileContactUniquenessOwnerBoundaryClosureModuleTest` 覆盖真实普???客户登录??併??真实其它客户??佺幇浠? `/api/front/profile/verification-checks` 和旧 `user/center/updateVerifyInfo` 两个入口???
- 请求参数中的 `user_id` 鎴? `userId` 不会切换唯一性校验的排除对象；当前用户自己的手机号可通过，其它用户邮箱仍返回重复???

### 剩余边界
- 本轮没有改动 `ProfileController` 生产逻辑、联系方式更新???资料修改验证码、银行卡换绑验证码???资料页前端表单或数据库结构???
- 后续继续按旧项目模块清单审计下一个前台代???/普??氱敤鎴?/后台管理员模块剩余入口???

## 229. 2026-07-09 前台头像上传归属边界测试闭环

### 本次处理目标
- 涓? `ProfileController::uploadAvatar` 涓? `ProfileController::uploadHeadImg` 补齐头像上传归属边界测试，确认头像文件和 `user_infos.avatar` 只写入当前登录用户???
- 验证现代 `/api/front/profile/avatar` 即使携带其它用户 `user_id` 鎴? `userId`，上传文件也只保存到当前用户 `avatars/{user_id}` 目录并更新当前用户头像???
- 验证??? Web `user/center/uploadHeadImg` 使用旧字??? `headimg` 时同样只更新当前用户头像，不会覆盖其它用户头像路径???

### 本次变更文件
- `tests/Feature/FrontProfileAvatarOwnerBoundaryClosureModuleTest.php`
  - 新增现代头像上传忽略伪??犵敤鎴? ID 的样例???
  - 新增旧头像上传忽略伪造用??? ID 的样例???
  - 样例使用 `Storage::fake('public')` 和测试后镜像目录清理，断???上传路径只落在当前登录用户目录，其它用户头像保持原??笺??

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontProfileAvatarOwnerBoundaryClosureModuleTest.php` 的行为样例在清单补录前已通过，证明现??? `request->user('user')` 涓? `$userLogin->userInfo` 已把现代和旧头像上传绑定到当前登录用户???
- RED：新增清单测试首次失败，命中???终清单缺少第 229 节???
- GREEN：追加第 229 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontProfileAvatarOwnerBoundaryClosureModuleTest` 覆盖真实普???客户登录??併??真实其它客户??佺幇浠? `/api/front/profile/avatar` 和旧 `user/center/uploadHeadImg` 两个入口???
- 请求参数中的 `user_id` 鎴? `userId` 不会切换头像更新对象；现代和旧入口上传后的头像路径均以当前登录用??? `avatars/{当前用户 ID}/` 寮?头???

### 剩余边界
- 本轮没有改动 `ProfileController` 生产逻辑、头像前端裁剪或预览、资料基???字段更新、上传认证入口??乸ublic disk 配置或数据库结构???
- 后续继续按旧项目模块清单审计下一个前台代???/普??氱敤鎴?/后台管理员模块剩余入口???

## 230. 2026-07-09 前台现代联系方式修改归属边界测试闭环

### 本次处理目标
- 涓? `ProfileController::changePhone` 鍜? `ProfileController::changeEmail` 补齐现代联系方式修改归属边界测试，确认手机号与邮箱修改都只作用于当前登录用户???
- 验证现代 `/api/front/profile/phone` 即使携带其它用户 `user_id` 鎴? `userId`，也只能更新当前登录用户??? `user_infos.phone`銆?
- 验证现代 `/api/front/profile/email` 即使携带其它用户 `user_id` 鎴? `userId`，也只能更新当前登录用户??? `user_logins.email`銆?

### 本次变更文件
- `tests/Feature/FrontProfileModernContactChangeOwnerBoundaryClosureModuleTest.php`
  - 新增现代手机号修改忽略伪造用??? ID 的样例???
  - 新增现代邮箱修改忽略伪??犵敤鎴? ID 的样例???
  - 两个样例均构造真实当前客户和真实其它客户，并断言写入只落在当前登录用户记录，其它用户手机号和邮箱保持原??笺??

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontProfileModernContactChangeOwnerBoundaryClosureModuleTest.php` 的行为样例在清单补录前已通过，证明现??? `changePhone` 鍜? `changeEmail` 已经绑定到当前登录用户???
- RED：新增清单测试首次失败，命中???终清单缺少第 230 节???
- GREEN：追加第 230 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontProfileModernContactChangeOwnerBoundaryClosureModuleTest` 覆盖真实普???客户登录??併??真实其它客户??佺幇浠? `/api/front/profile/phone` 鍜? `/api/front/profile/email` 两个入口???
- 请求参数中的 `user_id` 鎴? `userId` 不会切换手机号或邮箱修改对象；手机号只更新当前用户资料记录，邮箱只更新当前用户登录记录???

### 剩余边界
- 本轮没有改动 `ProfileController` 生产逻辑、旧联系方式更新入口、验证码发???/校验、头像上传???资料基???字段更新或数据库结构???
- 后续继续按旧项目模块清单审计下一个前台代理???普通用户或后台管理员模块剩余入口???

## 231. 2026-07-09 前台实名认证提交归属边界??? real_name 字段兼容测试闭环

### 本次处理目标
- 涓? `ProfileController::submitIdentity` 涓? `ProfileController::uploadIdCard` 补齐实名认证提交归属边界测试，确认现代和旧上传入口都只写入当前登录用户的认证资料???
- 验证现代 `/api/front/profile/identity` 即使携带其它用户 `user_id` 鎴? `userId`，也只能写入当前登录用户??? `user_auths.id_card_no`、身份证正反面图片和审核状??併??
- 验证??? Web `user/center/uploadIdCard` 即使携带其它用户 ID，也只能更新当前登录用户??? `user_infos.user_name` 涓? `user_auths` 实名认证资料，不会覆盖其它用户认证记录???
- 修复旧实名认证上传和注册服务向当前真??? `user_auths` 表不存在??? `real_name` 列写入导??? 500 的兼容缺口???

### 本次变更文件
- `tests/Feature/FrontProfileIdentityOwnerBoundaryClosureModuleTest.php`
  - 新增现代实名认证提交忽略伪??犵敤鎴? ID 的样例???
  - 新增旧实名认证上传忽略伪造用??? ID 的样例???
  - 样例使用 `Storage::fake('public')` 和测试后镜像目录清理，断???认证图片路径只落在当前登录用??? `auth/{user_id}/identity` 目录，其它用户认证记录保持原值???
- `app/Http/Controllers/Front/ProfileController.php`
  - `uploadIdCard` 写入 `user_auths` 前按 `Schema::hasColumn('user_auths', 'real_name')` 过滤旧兼容字段，避免当前真实库缺列时??? SQL 500銆?
- `app/Services/UserRegistrationService.php`
  - 注册链路创建 `user_auths` 时同样按真实表结构过??? `real_name` 兼容字段，保持同???字段规则???致???

### TDD 执行记录
- RED：新增目标测试首次运行时，现代实名认证归属样例???过；旧 `uploadIdCard` 暴露 `Unknown column 'real_name'` 生产缺口；清单断???也因缺少??? 231 节失败???
- GREEN：按真实 `user_auths` 表结构过??? `real_name` 后，行为样例通过；追加第 231 节清单记录后目标测试通过???

### 当前证据
- `FrontProfileIdentityOwnerBoundaryClosureModuleTest` 覆盖真实普???客户登录??併??真实其它客户??佺幇浠? `/api/front/profile/identity` 和旧 `user/center/uploadIdCard` 两个入口???
- 请求参数中的 `user_id` 鎴? `userId` 不会切换实名认证提交对象；身份证号???身份证图片、审核状态和旧入口姓名更新均只落在当前登录用户???
- 当前真实数据库没??? `user_auths.real_name` 时，旧上传入口和注册服务不会再向不存在列写入；如果旧库存在该列，仍可保留兼容写入???

### 剩余边界
- 本轮没有改动银行卡认证???银行卡换绑提交、后台实名认证审核???认证图片前端预览或数据库结构???
- 后续继续按旧项目模块清单审计银行卡认证???银行卡换绑提交及其它前台代???/普??氱敤鎴?/后台管理员模块剩余入口???

## 232. 2026-07-09 前台银行卡认证提交归属边界测试闭???

### 本次处理目标
- 涓? `ProfileController::submitBankCard` 涓? `ProfileController::uploadBankCard` 补齐银行卡认证提交归属边界测试，确认现代和旧上传入口都只写入当前登录用户的银行卡认证资料???
- 验证现代 `/api/front/profile/bank-card` 即使携带其它用户 `user_id` 鎴? `userId`，也只能写入当前登录用户??? `user_auths.bank_name`銆乣bank_no`銆乣bank_addr`、银行卡正反面图片和审核状??併??
- 验证??? Web `user/center/uploadBankCard` 即使携带其它用户 ID，也只能更新当前登录用户??? `user_auths` 银行卡认证资料，不会覆盖其它用户认证记录???

### 本次变更文件
- `tests/Feature/FrontProfileBankCardOwnerBoundaryClosureModuleTest.php`
  - 新增现代银行卡认证提交忽略伪造用??? ID 的样例???
  - 新增旧银行卡认证上传忽略伪??犵敤鎴? ID 的样例???
  - 样例使用 `Storage::fake('public')` 和测试后镜像目录清理，断???银行卡图片路径只落在当前登录用户 `auth/{user_id}/bank` 目录，其它用户银行卡认证记录保持原??笺??

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontProfileBankCardOwnerBoundaryClosureModuleTest.php` 的行为样例在清单补录前已通过，证明现??? `request->user('user')`銆乣currentProfileContext($request)` 涓? `legacyBankCardUpload($request, false)` 已把银行卡认证提交绑定到当前登录用户???
- RED：新增清单测试首次失败，命中???终清单缺少第 232 节???
- GREEN：追加第 232 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontProfileBankCardOwnerBoundaryClosureModuleTest` 覆盖真实普???客户登录??併??真实其它客户??佺幇浠? `/api/front/profile/bank-card` 和旧 `user/center/uploadBankCard` 两个入口???
- 请求参数中的 `user_id` 鎴? `userId` 不会切换银行卡认证提交对象；???户行、银行卡号???开户地???、银行卡图片和审核状态均只落在当前登录用户???

### 剩余边界
- 本轮没有改动 `ProfileController` 生产逻辑、银行卡换绑提交、银行卡换绑验证???/邮箱校验、后台银行卡审核、银行卡图片前端预览或数据库结构???
- 后续继续按旧项目模块清单审计银行卡换绑提交及其它前台代理、普通用户或后台管理员模块剩余入口???

## 233. 2026-07-09 前台银行卡换绑提交归属边界测试闭???

### 本次处理目标
- 涓? `ProfileController::submitBankChange` 涓? `ProfileController::uploadChangeBankCard` 补齐银行卡换绑提交归属边界测试，确认现代和旧上传入口都只写入当前登录用户的银行卡临时换绑资料???
- 验证现代 `/api/front/profile/bank-card-change` 即使携带其它用户 `user_id` 鎴? `userId`，也只能写入当前登录用户??? `user_auths.bank_name_tmp`銆乣bank_no_tmp`銆乣bank_addr_tmp`、银行卡临时正反面图片和 `bank_status=3`銆?
- 验证??? Web `user/center/uploadChangeBankCard` 即使携带其它用户 ID，也只能更新当前登录用户??? `user_auths` 临时换绑字段，不会覆盖其它用户认证记录???

### 本次变更文件
- `tests/Feature/FrontProfileBankChangeSubmitOwnerBoundaryClosureModuleTest.php`
  - 新增现代银行卡换绑提交忽略伪造用??? ID 的样例???
  - 新增旧银行卡换绑上传忽略伪??犵敤鎴? ID 的样例???
  - 样例使用 `Storage::fake('public')` 和测试后镜像目录清理，断???银行卡换绑图片路径只落在当前登录用户 `auth/{user_id}/bank-change` 目录，其它用户临时换绑记录保持原值???

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontProfileBankChangeSubmitOwnerBoundaryClosureModuleTest.php` 的行为样例在清单补录前已通过，证明现??? `verifiedContactUser($request)`銆乣currentProfileContext($request)` 涓? `legacyBankCardUpload($request, true)` 已把银行卡换绑提交绑定到当前登录用户???
- RED：新增清单测试首次失败，命中???终清单缺少第 233 节???
- GREEN：追加第 233 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontProfileBankChangeSubmitOwnerBoundaryClosureModuleTest` 覆盖真实普???客户登录??併??真实其它客户??佺幇浠? `/api/front/profile/bank-card-change` 和旧 `user/center/uploadChangeBankCard` 两个入口???
- 请求参数中的 `user_id` 鎴? `userId` 不会切换银行卡换绑提交对象；临时???户行、临时银行卡号???临时开户地???、临时银行卡图片和换绑状态均只落在当前登录用户???

### 剩余边界
- 本轮没有改动 `ProfileController` 生产逻辑、银行卡认证提交、银行卡换绑验证???/邮箱校验、后台银行卡审核、银行卡图片前端预览或数据库结构???
- 后续继续按旧项目模块清单审计其它前台代理、普通用户或后台管理员模块剩余入口???

## 234. 2026-07-09 前台账户凭证提交与查询归属边界测试闭???

### 本次处理目标
- 涓? `AccountController::submitVoucher`銆乣AccountController::userVoucherSave` 涓? `AccountController::voucherList` 补齐凭证提交和查询归属边界测试，确认现代和旧前台凭证入口都只读写当前登录用户数据???
- 验证现代 `/api/front/account/voucher-submissions` 即使携带其它用户 `user_id` 鎴? `userId`，也只能为当前登录用户创??? `voucher_infos` 记录，图片路径落??? `vouchers/{当前用户 ID}`銆?
- 验证??? Web `user/user_voucher_save` 即使携带其它用户 ID，也只能为当前登录用户创建凭证记录???
- 验证现代 `/api/front/account/vouchers` 查询时即使携带其它用??? ID，也只返回当前登录用户自己的凭证列表，不泄露其它用户凭证备注或图片???

### 本次变更文件
- `tests/Feature/FrontAccountVoucherOwnerBoundaryClosureModuleTest.php`
  - 新增现代凭证提交忽略伪??犵敤鎴? ID 的样例???
  - 新增旧凭证提交忽略伪造用??? ID 的样例???
  - 新增凭证列表查询忽略伪??犵敤鎴? ID 的样例???
  - 上传样例使用 `Storage::fake('public')`，直接断??? `voucher_infos.user_id` 和凭证图片路径归属当前登录用户???

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontAccountVoucherOwnerBoundaryClosureModuleTest.php` 的行为样例在清单补录前已通过，证明现??? `currentUserInfo($request)` 涓? `legacyFrontUserInfo($request)` 已把凭证提交和查询绑定到当前登录用户???
- RED：新增清单测试首次失败，命中???终清单缺少第 234 节???
- GREEN：追加第 234 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontAccountVoucherOwnerBoundaryClosureModuleTest` 覆盖真实普???客户登录??併??真实其它客户??佺幇浠? `/api/front/account/voucher-submissions`銆佺幇浠? `/api/front/account/vouchers` 和旧 `user/user_voucher_save` 三个入口???
- 请求参数中的 `user_id` 鎴? `userId` 不会切换凭证提交或查询对象；新增凭证、凭证图片路径???备注和列表返回均只落在当前登录用户???

### 剩余边界
- 本轮没有改动 `AccountController` 生产逻辑、后台凭证审核???凭证图片前端预览???账户余???/账户综合、旧账户类型切换或数据库结构???
- 后续继续按旧项目模块清单审计账户类型切换、账户综???/余额及其它前台代理???普通用户或后台管理员模块剩余入口???

## 235. 2026-07-09 前台账户综合余额与账户类型切换归属边界测试闭???

### 本次处理目标
- 涓? `AccountController::accountInfo`銆乣AccountController::accountBalance` 涓? `AccountController::changeAccountSave` 补齐账户读写归属边界测试，确认现代账户数据读取和旧账户类型切换都只作用于当前登录用户???
- 验证现代 `/api/front/account/profile` 即使携带其它用户 `user_id` 鎴? `userId`，也只返回当前登录用户的账户综合数据，不泄露其它用户姓名或资金字段???
- 验证现代 `/api/front/account/balance` 即使携带其它用户 ID，也只返回当前登录用户余额数据???
- 验证??? Web `user/change_account_save` 即使携带其它用户 ID，也只更新当前登录用户的 `user_infos.is_ecn` 涓? `leverage`銆?
- 修复旧账户类型切换向整型 `user_infos.updated_by` 写入用户名字符串导致 500 的兼容缺口???

### 本次变更文件
- `tests/Feature/FrontAccountProfileOwnerBoundaryClosureModuleTest.php`
  - 新增账户综合读取忽略伪??犵敤鎴? ID 的样例???
  - 新增账户余额读取忽略伪??犵敤鎴? ID 的样例???
  - 新增旧账户类型切换忽略伪造用??? ID 的样例???
- `app/Http/Controllers/Front/AccountController.php`
  - `changeAccountSave` 更新 `user_infos` 时将 `updated_by` 改为当前业务 `user_id`，匹配真实整型列定义，避免旧入口提交时报 SQL 类型错误???

### TDD 执行记录
- RED：新增目标测试首次运行时，账户综合与余额行为只因 JSON `user_id` 序列化为字符串???需调整断言；旧 `changeAccountSave` 暴露 `updated_by` 写入用户名导??? 500；清单断???也因缺少??? 235 节失败???
- GREEN锛歚changeAccountSave` 改为写入当前业务用户 ID 后，行为样例通过；追加第 235 节清单记录后目标测试通过???

### 当前证据
- `FrontAccountProfileOwnerBoundaryClosureModuleTest` 覆盖真实普???客户登录??併??真实其它客户??佺幇浠? `/api/front/account/profile`銆佺幇浠? `/api/front/account/balance` 和旧 `user/change_account_save` 三个入口???
- 请求参数中的 `user_id` 鎴? `userId` 不会切换账户读取或账户类型切换对象；资金、净值???姓名??丒CN 标识和杠杆均只属于当前登录用户???

### 剩余边界
- 本轮没有改动凭证提交/查询、入出金、流水???后台账户审核???账户页面前端展示或数据库结构???
- 后续继续按旧项目模块清单审计入出金???流水及其它前台代理、普通用户或后台管理员模块剩余入口???

## 236. 2026-07-09 前台入金申请与历史归属边界测试闭???

### 本次处理目标
- 涓? `DepositController::submitDeposit`銆乣DepositController::deposit_request` 涓? `DepositController::depositHistory` 补齐入金申请和历史查询归属边界测试，确认现代和旧前台入金入口都只读写当前登录用户数据???
- 验证现代 `/api/front/deposits/submissions` 即使携带其它用户 `user_id` 鎴? `userId`，也只能为当前登录用户创??? `deposit_records` 记录???
- 验证??? Web `user/deposit_request` 即使携带其它用户 ID，也只能为当前登录用户创建入金申请记录???
- 验证现代 `/api/front/deposits/history` 查询时即使携带其它用??? ID，也只返回当前登录用户自己的入金历史，不泄露其它用户订单号???姓名或金额???

### 本次变更文件
- `tests/Feature/FrontDepositOwnerBoundaryClosureModuleTest.php`
  - 新增现代入金提交忽略伪??犵敤鎴? ID 的样例???
  - 新增旧入金提交忽略伪造用??? ID 的样例???
  - 新增入金历史查询忽略伪??犵敤鎴? ID 的样例???
  - 样例显式固定入金系统???关???时间窗口???限额和测试支付通道，避免真实库配置影响归属边界判断???

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontDepositOwnerBoundaryClosureModuleTest.php` 的三个行为样例在清单补录前已通过，证明现??? `legacyFrontUserInfo($request)` 已把现代入金提交、旧入金提交和历史查询绑定到当前登录用户???
- RED：新增清单测试首次失败，命中???终清单缺少第 236 节???
- GREEN：追加第 236 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontDepositOwnerBoundaryClosureModuleTest` 覆盖真实普???客户登录??併??真实其它客户??佺幇浠? `/api/front/deposits/submissions`銆佺幇浠? `/api/front/deposits/history` 和旧 `user/deposit_request` 三个入口???
- 请求参数中的 `user_id` 鎴? `userId` 不会切换入金创建或历史查询对象；新增入金记录、订单备注???历史列表和汇???金额均只属于当前登录用户???

### 剩余边界
- 本轮没有改动 `DepositController` 生产逻辑、支付???道解析、支付网关跳转???入金回调???后台入金审核???入金页面前端展示或数据库结构???
- 后续继续按旧项目模块清单审计出金、资金流水及其它前台代理、普通用户或后台管理员模块剩余入口???

## 237. 2026-07-09 前台出金申请与历史归属边界测试闭???

### 本次处理目标
- 涓? `WithdrawController::submitWithdraw`銆乣WithdrawController::withdraw_request` 涓? `WithdrawController::withdrawHistory` 补齐出金申请和历史查询归属边界测试，确认现代和旧前台出金入口都只读写当前登录用户数据???
- 验证现代 `/api/front/withdrawals/submissions` 即使携带其它用户 `user_id` 鎴? `userId`，也只能为当前登录用户创??? `withdraw_records` 记录，并使用当前用户的银行卡认证资料???
- 验证??? Web `user/withdraw_request` 即使携带其它用户 ID，也只能为当前登录用户创建出金申请记录???
- 验证现代 `/api/front/withdrawals/history` 查询时即使携带其它用??? ID，也只返回当前登录用户自己的出金历史，不泄露其它用户订单号???姓名???银行卡或金额???

### 本次变更文件
- `tests/Feature/FrontWithdrawOwnerBoundaryClosureModuleTest.php`
  - 新增现代出金提交忽略伪??犵敤鎴? ID 的样例???
  - 新增旧出金提交忽略伪造用??? ID 的样例???
  - 新增出金历史查询忽略伪??犵敤鎴? ID 的样例???
  - 样例显式固定出金系统???关???时间窗口???限额???手续费、汇率???持仓检查???实名状态和余额，避免真实库配置影响归属边界判断???

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontWithdrawOwnerBoundaryClosureModuleTest.php` 的三个行为样例在清单补录前已通过，证明现??? `legacyFrontUserLogin($request)`銆乣legacyFrontUserInfo($request)` 已把现代出金提交、旧出金提交和历史查询绑定到当前登录用户???
- RED：新增清单测试首次失败，命中???终清单缺少第 237 节???
- GREEN：追加第 237 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontWithdrawOwnerBoundaryClosureModuleTest` 覆盖真实普???客户登录??併??真实其它客户??佺幇浠? `/api/front/withdrawals/submissions`銆佺幇浠? `/api/front/withdrawals/history` 和旧 `user/withdraw_request` 三个入口???
- 请求参数中的 `user_id` 鎴? `userId` 不会切换出金创建或历史查询对象；新增出金记录、银行卡资料、历史列表和汇???金额均只属于当前登录用户???

### 剩余边界
- 本轮没有改动 `WithdrawController` 生产逻辑、密码校验???出金手续费规则、风险率校验、持仓校验???后台出金审核???出金页面前端展示或数据库结构???
- 后续继续按旧项目模块清单审计资金流水及其它前台代理???普通用户或后台管理员模块剩余入口???

## 238. 2026-07-09 前台本人资金流水查询归属边界测试闭环

### 本次处理目标
- 涓? `FlowController::accountFlow`銆乣FlowController::depositFlowSearch`銆乣FlowController::withdrawalFlowSearch` 涓? `FlowController::withdrawApplyFlowSearch` 补齐本人资金流水归属边界测试???
- 验证现代 `/api/front/flows/account` 即使携带其它用户 `user_id` 鎴? `userId`，聚合流水也只返回当前登录用户自己的入金和出金流水???
- 验证现代 `/api/front/flows/deposits` 在携带不可见用户 ID 筛???时不会切换到其它用户数据，而是返回空结果???
- 验证??? Web `user/flow/withdrawalFlowSearch` 涓? `user/flow/withdrawApplyFlowSearch` 在携带不可见用户 ID 筛???时同样返回空结果，不泄露其它用户出金流水???

### 本次变更文件
- `tests/Feature/FrontFlowOwnScopeOwnerBoundaryClosureModuleTest.php`
  - 新增聚合账户流水忽略伪??犵敤鎴? ID 并只返回当前用户流水的样例???
  - 新增现代本人入金流水拒绝越权用户筛???且不泄露其它用户记录的样例???
  - 新增旧本人出金流水和出金申请流水拒绝越权用户筛???且不泄露其它用户记录的样例???

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontFlowOwnScopeOwnerBoundaryClosureModuleTest.php` 的行为样例在清单补录前已通过，证明现??? `accountFlow()` 涓? `typedFlow()` 已把本人流水范围限制在当前登录用户???
- RED：新增清单测试首次失败，命中???终清单缺少第 238 节???
- GREEN：追加第 238 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontFlowOwnScopeOwnerBoundaryClosureModuleTest` 覆盖真实普???客户登录??併??真实其它客户??佺湡瀹? `deposit_records`銆佺湡瀹? `withdraw_records`銆佺幇浠? `/api/front/flows/account`銆佺幇浠? `/api/front/flows/deposits` 以及??? `user/flow/withdrawalFlowSearch`銆乣user/flow/withdrawApplyFlowSearch`銆?
- 聚合流水路径不使用请求里的伪造用??? ID 切换查询对象；单类本人流水路径对不可见用??? ID 追加空结果条件，避免把其它用户记录暴露给当前用户???

### 剩余边界
- 本轮没有改动 `FlowController` 生产逻辑、直属客???/直属代理流水、流水导出下载???前端流水页签或数据库结构???
- 后续继续按旧项目模块清单审计代理资金流水、订单明细及其它前台代理、普通用户或后台管理员模块剩余入口???

## 239. 2026-07-09 前台交易订单本人查询归属边界测试闭环

### 本次处理目标
- 涓? `OrderController::openOrders`銆乣OrderController::openOrderSearch`銆乣OrderController::closedOrders` 涓? `OrderController::closeOrderSearch` 补齐本人交易订单查询归属边界测试???
- 验证现代 `/api/front/orders/open` 在携带不可见用户 `user_id` 鎴? `userId` 筛???时不会切换到其它用户订单，而是返回空结果???
- 验证??? Web `user/open/openOrderSearch` 涓? `user/close/closeOrderSearch` 在携带不可见用户 ID 筛???时同样返回空结果，不泄露其它用户持仓单或平仓单???

### 本次变更文件
- `tests/Feature/FrontOrderOwnScopeOwnerBoundaryClosureModuleTest.php`
  - 新增现代本人持仓订单拒绝越权用户筛???且不泄露其它用户订单的样例???
  - 新增旧本人持仓订单查询拒绝越权用户筛选且不泄露其它用户订单的样例???
  - 新增旧本人平仓订单查询拒绝越权用户筛选且不泄露其它用户订单的样例???

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontOrderOwnScopeOwnerBoundaryClosureModuleTest.php` 的三个行为样例在清单补录前已通过，证明现有订单查询会通过本人可见范围拒绝不可见用户筛选???
- RED：新增清单测试首次失败，命中???终清单缺少第 239 节???
- GREEN：追加第 239 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontOrderOwnScopeOwnerBoundaryClosureModuleTest` 覆盖真实普???客户登录??併??真实其它客户??佺湡瀹? `user_trades`銆佺幇浠? `/api/front/orders/open` 以及??? `user/open/openOrderSearch`銆乣user/close/closeOrderSearch`銆?
- 本人订单路径对不可见 `user_id` 鎴? `userId` 追加空结果条件，避免把其它用户持仓单、平仓单、票据号、账户名或订单备注暴露给当前用户???

### 剩余边界
- 本轮没有改动 `OrderController` 生产逻辑、订单详情???订单导出???订单前端页签或数据库结构???
- 后续继续按旧项目模块清单审计订单详情、代理资金流水及其它前台代理、普通用户或后台管理员模块剩余入口???

## 240. 2026-07-09 前台交易订单详情弹层归属边界测试闭环

### 本次处理目标
- 涓? `OrderController::openOrderDetail` 涓? `OrderController::closeOrderDetail` 补齐旧前台订单详情弹层归属边界测试???
- 验证??? Web `open/order_detail/{orderId}/{orderType}/{role}` 只能打开当前登录用户可见的持仓订单详情???
- 验证??? Web `close/order_detail/{orderId}/{orderType}/{role}` 在访问其它用户平仓订单时返回 404，不泄露其它用户订单内容???

### 本次变更文件
- `tests/Feature/FrontOrderDetailOwnerBoundaryClosureModuleTest.php`
  - 新增当前用户持仓订单详情可正常渲染的样例???
  - 新增其它用户持仓订单详情访问被拒绝且不泄露票据号、用户名和备注的样例???
  - 新增其它用户平仓订单详情访问被拒绝且不泄露票据号、用户名和备注的样例???

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontOrderDetailOwnerBoundaryClosureModuleTest.php` 的三个行为样例在清单补录前已通过，证明现有详情查询会通过 `FrontLegacyData::applyAllowedUserFilter()` 限制当前登录用户可见范围???
- RED：新增清单测试首次失败，命中???终清单缺少第 240 节???
- GREEN：追加第 240 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontOrderDetailOwnerBoundaryClosureModuleTest` 覆盖真实普???客户登录??併??真实其它客户??佺湡瀹? `user_trades`、旧 `open/order_detail/{orderId}/{orderType}/{role}` 与旧 `close/order_detail/{orderId}/{orderType}/{role}`銆?
- 旧详情弹层按当前登录用户过滤订单；不可见订单返回 404，避免把其它用户持仓单???平仓单、票据号、账户名或订单备注渲染到 HTML銆?

### 剩余边界
- 本轮没有改动 `OrderController` 生产逻辑、订单列表???代理订单详情???订单前端弹层样式???订单导出或数据库结构???
- 后续继续按旧项目模块清单审计代理资金流水、代理订单明细及其它前台代理、普通用户或后台管理员模块剩余入口???

## 241. 2026-07-09 前台代理直属资金流水归属边界测试闭环

### 本次处理目标
- 涓? `FlowController::directDepositFlowSearch` 涓? `FlowController::directWithdrawalFlowSearch` 补齐代理直属客户和直属代理资金流水归属边界测试???
- 验证现代 `/api/front/flows/direct-deposits` 涓? `/api/front/flows/direct-agent-deposits` 即使携带其它代理树的 `user_id` 鎴? `userId`，也只返回空结果，不切换到其它分支流水???
- 验证??? Web `user/flow/directWithdrawalFlowSearch` 涓? `user/flow/directAgentsWithdrawalFlowSearch` 在携带其它代理树用户 ID 筛???时同样返回空结果，不泄露其它分支出金流水???

### 本次变更文件
- `tests/Feature/FrontFlowDirectOwnerBoundaryClosureModuleTest.php`
  - 新增现代直属客户入金流水拒绝跨代理树用户筛???的样例???
  - 新增旧直属客户出金流水拒绝跨代理树用户筛选的样例???
  - 新增现代直属代理入金流水拒绝跨代理树代理筛???的样例???
  - 新增旧直属代理出金流水拒绝跨代理树代理筛选的样例???

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontFlowDirectOwnerBoundaryClosureModuleTest.php` 的四个行为样例在清单补录前已通过，证明现??? `flowScopeUserIds()` 涓? `requestedUserId()` 会把直属客户和直属代理流水限制在当前代理树内???
- RED：新增清单测试首次失败，命中???终清单缺少第 241 节???
- GREEN：追加第 241 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontFlowDirectOwnerBoundaryClosureModuleTest` 覆盖真实代理登录态???同树直属客户???同树直属代理???其它代理树客户和其它代理树代理，以及真??? `deposit_records`銆乣withdraw_records`銆?
- 直属客户与直属代理流水路径都对不可见 `user_id` 鎴? `userId` 追加空结果条件，避免把其它代理树的订单号、用户名、入金或出金记录暴露给当前代理???

### 剩余边界
- 本轮没有改动 `FlowController` 生产逻辑、流水导出下载???普通客户本人流水???前端流水页签或数据库结构???
- 后续继续按旧项目模块清单审计代理订单明细、大代理入口及其它前台代理???普通用户或后台管理员模块剩余入口???

## 242. 2026-07-09 前台大代理列表与订单归属边界测试闭环

### 本次处理目标
- 涓? `BigNumberController::bigNumberListSearch`銆乣BigNumberController::bigNumberListSearchBySubAgents`銆乣BigNumberController::bigCloseOrderSearch` 涓? `BigNumberController::bigOpenOrderSearch` 补齐真实大代理登录???下的数据归属边界测试???
- 验证??? Web `user/agents/proxy/proxySearch` 涓? `user/agents/proxy/proxySearchBySub` 即使携带 `sub_agent_ids` 之外的代??? `userId`，也返回空结果，不泄露其它代理资料???
- 验证??? Web `user/agents/close/closeOrderSearch` 涓? `user/agents/open/openOrderSearch` 即使携带其它代理树客??? `userId`，也返回空结果，不泄露其它客户开平仓订单???

### 本次变更文件
- `tests/Feature/FrontBigNumberOwnerBoundaryClosureModuleTest.php`
  - 新增大代理代理列表拒绝配置范围外代理筛???的样例???
  - 新增大代理平仓订单查询拒绝配置范围外客户筛???的样例???
  - 新增大代理持仓订单查询拒绝配置范围外客户筛???的样例???

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontBigNumberOwnerBoundaryClosureModuleTest.php` 的三个行为样例在清单补录前已通过，证明现??? `currentBigAgent()` 只信??? session 中的大代理身份，且列表与订单查询都会叠加 `sub_agent_ids` 计算出的可见范围???
- RED：新增清单测试首次失败，命中???终清单缺少第 242 节???
- GREEN：追加第 242 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontBigNumberOwnerBoundaryClosureModuleTest` 覆盖真实 `big_agents` session、真实可见代理???其它代理???可见客户???其它客户和真实 `user_trades`銆?
- 大代理列表和???平仓订单路径即使收到不可??? `userId/user_id` 筛???，也只返回空表格，避免??? `sub_agent_ids` 之外的代理资料???客户账号???订单票据号或订单备注暴露给当前大代理???

### 剩余边界
- 本轮没有改动 `BigNumberController` 生产逻辑、大代理登录、密码修改???持仓汇总???前端页面或数据库结构???
- 后续继续按旧项目模块清单审计大代理持仓汇总归属边界???代理佣金明细及其它前台代理、普通用户或后台管理员模块剩余入口???

## 243. 2026-07-09 前台大代理持仓汇总归属边界测试闭???

### 本次处理目标
- 涓? `BigNumberController::bigPositionSummarySearch` 涓? `BigNumberController::bigSubPositionSummaryStats` 补齐真实大代理登录???下的持仓汇总归属边界测试???
- 验证??? Web `user/agents/position/positionSummarySearch` 即使携带 `sub_agent_ids` 之外的代??? `userId`，也返回空结果，不泄露其它代理持仓汇总???
- 验证??? Web `user/agents/position/subAgentsListSearch` 在同样的跨范围代理筛选下也返回空结果，不泄露其它代理 ID、名称或资金字段???

### 本次变更文件
- `tests/Feature/FrontBigNumberPositionOwnerBoundaryClosureModuleTest.php`
  - 新增大代理持仓汇总拒绝配置范围外代理筛???的样例???
  - 新增大代理下级持仓汇总拒绝配置范围外代理筛???的样例???

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontBigNumberPositionOwnerBoundaryClosureModuleTest.php` 的两个行为样例在清单补录前已通过，证明现有大代理持仓汇???入口会??? session 大代理范围内叠加用户筛??夈??
- RED：新增清单测试首次失败，命中???终清单缺少第 243 节???
- GREEN：追加第 243 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontBigNumberPositionOwnerBoundaryClosureModuleTest` 覆盖真实 `big_agents` session、真实可见代理???其它代理??乣user/agents/position/positionSummarySearch` 涓? `user/agents/position/subAgentsListSearch`銆?
- 两个持仓汇???入口即使收到不可见 `userId/user_id`，也只返回空表格，避免把 `sub_agent_ids` 之外的代??? ID、用户名或资金持仓汇总暴露给当前大代理???

### 剩余边界
- 本轮没有改动 `BigNumberController` 生产逻辑、大代理代理列表、开平仓订单、登录???密码修改???前端页面或数据库结构???
- 后续继续按旧项目模块清单审计代理佣金明细、代理层级详情及其它前台代理、普通用户或后台管理员模块剩余入口???

## 244. 2026-07-09 前台实时返佣列表与详情归属边界测试闭???

### 本次处理目标
- 涓? `CommissionController::realTime`銆乣CommissionController::realtimeRebateSearch` 涓? `CommissionController::realtimeRebateDetail` 补齐代理实时返佣归属边界测试???
- 验证现代 `/api/front/commissions/realtime` 即使携带其它代理树客??? `user_id/userId`，也只返回空结果，不切换到其它分支订单???
- 验证??? Web `user/realtime/realtimeRebateSearch` 涓? `user/realtime/rebate_detail/{orderNo}/{role}` 同样只允许当前代理查看自己代理树内的返佣订单和详情???

### 本次变更文件
- `tests/Feature/FrontCommissionRealtimeOwnerBoundaryClosureModuleTest.php`
  - 新增现代实时返佣列表拒绝跨代理树用户筛???的样例???
  - 新增旧实时返佣列表拒绝跨代理树用户筛选的样例???
  - 新增旧实时返佣详情只能打???当前代理树订单，访问其它代理树订单返??? 404 且不泄露内容的样例???

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontCommissionRealtimeOwnerBoundaryClosureModuleTest.php` 的三个行为样例在清单补录前已通过，证明现有实时返佣查询会先按当前代理树限??? `user_trades.user_id`，再叠加筛???或详情订单号???
- RED：新增清单测试首次失败，命中???终清单缺少第 244 节???
- GREEN：追加第 244 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontCommissionRealtimeOwnerBoundaryClosureModuleTest` 覆盖真实代理登录态???同树客户???其它代理树客户、真??? `user_trades`銆佺湡瀹? `commission_records`、现代实时返佣列表???旧实时返佣列表和旧详情弹层???
- 实时返佣列表对不可见 `userId` 返回空分页，详情弹层对不可见订单返回 404，避免把其它代理树的订单号???客户名称???订单备注或返佣记录暴露给当前代理???

### 剩余边界
- 本轮没有改动 `CommissionController` 生产逻辑、返佣历史???佣金转账???佣金转账??欓?夈??前端页面或数据库结构???
- 后续继续按旧项目模块清单审计返佣历史归属边界、佣金转账归属边界???代理层级详情及其它前台代理、普通用户或后台管理员模块剩余入口???

## 245. 2026-07-09 前台返佣历史归属边界测试闭环

### 本次处理目标
- 涓? `CommissionController::history` 补齐代理返佣历史归属边界测试???
- 验证现代 `/api/front/commissions/history` 默认查询只返回当前代理自己的 `commission_records.agent_id` 记录，不泄露其它代理返佣流水???
- 验证携带其它代理记录??? `orderId` 时返回空分页，不通过订单号切换到其它代理历史???
- 验证 `dataType=transfer` 等类型筛选只在当前代理范围内叠加，不跨代理读取其它转账返佣记录???

### 本次变更文件
- `tests/Feature/FrontCommissionHistoryOwnerBoundaryClosureModuleTest.php`
  - 新增返佣历史列表默认查询和其它代??? `orderId` 筛???隔离样例???
  - 新增 `dataType=transfer` 类型筛???隔离样例???
  - 新增???终清单记录断???，绑定第 245 节闭环???

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontCommissionHistoryOwnerBoundaryClosureModuleTest.php` 的两个行为样例在清单补录前已通过，证明现??? `history()` 查询先固??? `CommissionRecord::where('agent_id', $agentId)`，再叠加 `orderId`銆乣dataType` 和日期筛选???
- RED：新增清单测试首次失败，命中???终清单缺少第 245 节???
- GREEN：追加第 245 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontCommissionHistoryOwnerBoundaryClosureModuleTest` 覆盖真实代理登录态???其它代理??佺湡瀹? `commission_records`銆佺幇浠? `/api/front/commissions/history`銆乣orderId` 筛???和 `dataType` 筛??夈??
- 返佣历史列表、汇总和统计分析均绑定当前登录代??? ID；不可见订单号或返佣类型筛???不会把其它代理??? `unique_id`、订单号、备注或返佣金额带入响应???

### 剩余边界
- 本轮没有改动 `CommissionController` 生产逻辑、实时返佣???佣金转账???佣金转账??欓?夈??前端页面或数据库结构???
- 后续继续按旧项目模块清单审计佣金转账归属边界、代理层级详情及其它前台代理、普通用户或后台管理员模块剩余入口???

## 246. 2026-07-09 前台佣金转账直属代理归属边界测试闭环

### 本次处理目标
- 涓? `CommissionController::transferAgentOptions` 涓? `CommissionController::transfer` 补齐佣金转账直属代理归属边界测试???
- 验证现代 `/api/front/commissions/transfer-agent-options` 只返回当前代理直属下级代理，不泄露其它代理树代理或直属普通客户???
- 验证现代 `/api/front/commissions/transfers` 收到其它代理??? `sub_agent_id` 时返回权限拒绝，不扣减当前代理余额???不增加目标代理余额、不写入 DBCT/WBCT 佣金流水???
- 验证同一登录代理向真实直属下级代理转账时可以正常扣增余额并写入两??? `commission_records.data_type=transfer` 审计记录???

### 本次变更文件
- `tests/Feature/FrontCommissionTransferOwnerBoundaryClosureModuleTest.php`
  - 新增转账候???列表只返回当前代理直属下级代理的样例???
  - 新增跨代理树 `sub_agent_id` 拒绝且余???/流水不变的样例???
  - 新增直属代理正常转账、余额扣增和 DBCT/WBCT 双流水写入样例???

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontCommissionTransferOwnerBoundaryClosureModuleTest.php` 的两个行为样例在清单补录前已通过，证明现??? `transferAgentOptions()` 鍜? `transfer()` 都复??? `FrontLegacyData::userScopeIds($agentId, false, 1, true)` 限定直属代理范围???
- RED：新增清单测试首次失败，命中???终清单缺少第 246 节???
- GREEN：追加第 246 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontCommissionTransferOwnerBoundaryClosureModuleTest` 覆盖真实代理登录态???直属下级代理???其它代理树代理、直属普通客户???现代转账??欓??接口和现代佣金转账写入口???
- 其它代理??? `sub_agent_id` 无法绕过提交接口直接写入转账；拒绝后双方余额??? `commission_records` 均保持不变???
- 直属代理正常转账时生成下级入??? DBCT 与当前代理出??? WBCT 两条流水，备注保留业务说明，便于后台核对???

### 剩余边界
- 本轮没有改动 `CommissionController` 生产逻辑、返佣历史???实时返佣???旧前台直属客户转账、前端页面或数据库结构???
- 后续继续按旧项目模块清单审计代理层级详情、代理确???/变更写入及其它前台代理???普通用户或后台管理员模块剩余入口???

## 247. 2026-07-09 前台代理下级详情归属边界测试闭环

### 本次处理目标
- 涓? `AgentController::userDetail`銆乣AgentController::legacyUserDetailPage`銆乣AgentController::getParentPath` 涓? `AgentController::directCustDetailList` 补齐代理下级详情归属边界测试???
- 验证现代 `/api/front/users/{user}` 只能读取当前代理树内用户详情，访问其它代理树用户时返回权限拒绝且不泄露姓名或 ID銆?
- 验证??? Web `show/user_detail/{userId}/{role}` 只能渲染当前代理树内详情弹层，访问其它代理树用户时返??? 403銆?
- 验证??? Web `user/proxy/parentPath` 鍜? `user/proxy/direct_cust_detail_list` 在传入其它代理树目标时只返回空路径或空表，不泄露其它分支节点和直属客户明细???

### 本次变更文件
- `tests/Feature/FrontAgentDetailOwnerBoundaryClosureModuleTest.php`
  - 新增现代用户详情和旧详情弹层拒绝其它代理树目标的样例???
  - 新增旧代理层级路径拒绝其它代理树目标的样例???
  - 新增旧直属客户明细表拒绝其它代理树父级筛选的样例???

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontAgentDetailOwnerBoundaryClosureModuleTest.php` 的三个行为样例在清单补录前已通过，证明现有详情类入口都???过 `canViewUser()` 涓? `FrontLegacyData::userScopeIds()` 限制当前代理可见范围???
- RED：新增清单测试首次失败，命中???终清单缺少第 247 节???
- GREEN：追加第 247 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontAgentDetailOwnerBoundaryClosureModuleTest` 覆盖真实代理登录态???同树下级代理???同树客户???其它代理树代理、其它代理树客户、现代用户详情???旧详情弹层、旧层级路径和旧直属客户明细列表???
- 现代详情对不可见用户返回 `ResponseCode::PERMISSION_DENIED`；旧详情弹层返回 403；旧 parentPath 返回??? path/tree；旧 direct_cust_detail_list 返回 `count=0,data=[]`銆?
- 响应内容不会包含其它代理树用户姓名???业务用??? ID、路径节点或直属客户明细???

### 剩余边界
- 本轮没有改动 `AgentController` 生产逻辑、代理列表???客户列表???直属客户转账???等级确认???组别变更???登录历史???前端页面或数据库结构???
- 后续继续按旧项目模块清单审计代理等级确认、客户组别变更???登录历史归属边界及其它前台代理、普通用户或后台管理员模块剩余入口???

## 248. 2026-07-09 前台用户登录历史归属边界测试闭环

### 本次处理目标
- 涓? `AgentController::userLoginHistory` 涓? `AgentController::legacyLoginHistorySearch` 补齐用户登录历史归属边界测试???
- 验证现代 `/api/front/users/login-history` 只允许当前代理读取自己代理树内用户的 `user_login_logs`，访问其它代理树用户时返回权限拒绝???
- 验证??? Web `user/cust/loginHistorySearch/{uid}` 对其它代理树用户返回旧兼容空表格，不泄露 IP、地理位置???设备或业务用户 ID銆?

### 本次变更文件
- `tests/Feature/FrontUserLoginHistoryOwnerBoundaryClosureModuleTest.php`
  - 新增现代登录历史可见用户正常返回、不可见用户权限拒绝样例???
  - 新增旧登录历史表格可见用户正常返回???不可见用户??? rows/total 样例???
  - 新增???终清单记录断???，绑定第 248 节闭环???

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontUserLoginHistoryOwnerBoundaryClosureModuleTest.php` 的两个行为样例在清单补录前已通过，证明现??? `canViewUser()` 会在读取 `user_login_logs` 前限制当前代理可见范围???
- RED：新增清单测试首次失败，命中???终清单缺少第 248 节???
- GREEN：追加第 248 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontUserLoginHistoryOwnerBoundaryClosureModuleTest` 覆盖真实代理登录态???同树客户???其它代理树客户、真??? `user_login_logs`、现代登录历??? API 和旧登录历史表格入口???
- 现代入口对不可见用户返回 `ResponseCode::PERMISSION_DENIED`；旧入口对不可见用户返回 `total=0,rows=[]`銆?
- 响应内容不会包含其它代理树的登录 IP銆乁ser-Agent、业务用??? ID 或登录审计字段???

### 剩余边界
- 本轮没有改动 `AgentController` 生产逻辑、用户详情???代理层级路径???代理列表???客户列表???登录日志模型???前端页面或数据库结构???
- 后续继续按旧项目模块清单审计代理等级确认、客户组别变更写入及其它前台代理、普通用户或后台管理员模块剩余入口???

## 249. 2026-07-09 前台代理等级确认归属边界测试闭环

### 本次处理目标
- 涓? `AgentController::confirmLevel`銆乣AgentController::proxyConfirmSearch` 涓? `AgentController::confirmLevelChange` 补齐代理等级确认归属边界测试???
- 验证现代 `/api/front/agents/level-confirmation` 和旧 Web `user/proxy/proxyConfirmSearch` 只返回当前代理直属待确认代理，不泄露其它代理树待确认代理???
- 验证列表筛???传入其它代理树 `userId` 时返回空分页，不通过筛???参数切换可见范围???
- 验证现代 `/api/front/agents/level-confirmation/changes` 和旧 Web `user/proxy/confirmLevelChange` 拒绝确认其它代理树目标，且不改写 `is_agent_confirmed`銆乣level_id`銆乣comm_rate`銆?
- 验证直属下级代理仍可正常确认等级，返佣比例以 `agent_levels.user_commission + extra_val` 后端计算结果为准???

### 本次变更文件
- `tests/Feature/FrontAgentLevelConfirmationOwnerBoundaryClosureModuleTest.php`
  - 新增现代/旧等级确认列表拒绝其它代理树筛???的样例???
  - 新增现代/旧等级确认提交拒绝其它代理树目标且不改字段的样例???
  - 新增直属下级代理等级确认成功写入样例???

### TDD 执行记录
- 行为验证：`vendor\bin\phpunit tests\Feature\FrontAgentLevelConfirmationOwnerBoundaryClosureModuleTest.php` 首次行为样例中暴露测??? fixture 使用 `0.1` 作为 `comm_rate`，但真实 `user_infos.comm_rate` 鍜? `agent_levels.user_commission` 均为 `int(11)`，落库后??? `0`。修正测试数据为整型比例后，两个行为样例在清单补录前通过???
- RED：新增清单测试首次失败，命中???终清单缺少第 249 节???
- GREEN：追加第 249 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontAgentLevelConfirmationOwnerBoundaryClosureModuleTest` 覆盖真实代理登录态???直属待确认代理、其它代理树待确认代理???现代等级确认列表???旧等级确认列表、现代确认提交和旧确认提交???
- 列表入口通过直属代理范围限制返回数据；提交入口???过同一直属代理范围拒绝其它代理树目标???
- 拒绝后其它代理树目标??? `is_agent_confirmed`銆乣level_id`銆乣comm_rate` 均保持原值；直属目标可正常确认并写入后端等级比例???

### 剩余边界
- 本轮没有改动 `AgentController` 生产逻辑、代理列表???客户列表???用户详情???客户组别变更???前端页面??乣agent_levels` 鎴? `user_infos` 表结构???
- 后续继续按旧项目模块清单审计客户组别变更写入、代???/客户列表深层筛???及其它前台代理、普通用户或后台管理员模块剩余入口???

## 250. 2026-07-09 前台客户组别变更归属边界测试闭环

### 本次处理目标
- 涓? `AgentController::groupChange`銆乣AgentController::changeDirectCustGroupEdit` 涓? `AgentController::groupChangeList` 补齐客户组别变更归属边界测试???
- 验证现代 `/api/front/agents/group-change-applications` 只允许当前代理为自己代理树内普???客户提交组别变更申请，收到其它代理树客户时返回权限拒绝且不写入 `trans_apply_logs`銆?
- 验证??? Web `user/cust/change/group_edit` 使用 `grpName`銆乣userId`銆乣trans_apply_reason` 时同样保持当前代理树边界，不允许跨代理树提交???
- 验证现代 `/api/front/agents/group-changes` 与旧 Web `user/cust/directCustChangeListSearch` 只读取当前登录代??? `applicant_id` 的申请记录，传入其它代理树客??? `userId` 筛???时返回空分页???

### 本次变更文件
- `tests/Feature/FrontAgentGroupChangeOwnerBoundaryClosureModuleTest.php`
  - 新增现代组别变更提交可写入当前代理直属客户的样例???
  - 新增现代组别变更提交拒绝其它代理树客户且无写入的样例???
  - 新增??? `changeDirectCustGroupEdit` 可写入当前代理直属客户???拒绝其它代理树客户的样例???
  - 新增现代/旧组别变更列表按当前 `applicant_id` 隔离并拒绝跨代理??? `userId` 筛???的样例???

### TDD 执行记录
- 行为验证：`php vendor\phpunit\phpunit\phpunit tests\Feature\FrontAgentGroupChangeOwnerBoundaryClosureModuleTest.php` 首次运行??? 5 个行为样例已通过，证明现有提交入口会先校验申请人为代理???目标为普???客户，再???过 `canViewUser()` 限制当前代理树；列表入口固定 `trans_apply_logs.applicant_id` 为当前登录代理???
- RED：新增清单测试首次失败，命中???终清单缺少第 250 节???
- GREEN：追加第 250 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontAgentGroupChangeOwnerBoundaryClosureModuleTest` 覆盖真实代理登录态???当前代理直属客户???其它代理树客户、现代提交接口???旧提交入口、现代列表和旧列表入口???
- 跨代理树提交在现代接口返??? `ResponseCode::PERMISSION_DENIED`，旧入口返回 `msg=FAIL`，均不会为当前代理和其它代理树客户写??? `trans_apply_logs`銆?
- 列表路径即使收到其它代理树客??? `userId`，也只在当前代理 `applicant_id` 范围内叠加筛选，返回空分页，避免泄露其它代理的申请记录??佸鎴? ID、组别或申请原因???

### 剩余边界
- 本轮没有改动 `AgentController` 生产逻辑、客户组别审核后台???前端页面???组别配置表或数据库结构???
- 后续继续按旧项目模块清单审计代理/客户列表深层筛??夈??普通用户模块和后台管理员模块其它剩余入口???

## 251. 2026-07-09 前台代理/客户主列表归属筛选边界测试闭???

### 本次处理目标
- 涓? `AgentController::subList`銆乣AgentController::proxyListSearch`銆乣AgentController::customerList` 涓? `AgentController::directCustListSearch` 补齐主代???/客户列表归属筛???边界测试???
- 验证现代 `/api/front/agents/direct` 只允许当前代理展???自己代理树内??? `parent_id`，传入其它代理树父级时返回空列表???
- 验证现代 `/api/front/agents/direct-customers` 只允许当前代理展???自己代理树内的客户父级，传入其它代理树父级时返回空列表???
- 验证现代列表和旧 Web `user/proxy/proxyListSearch`銆乣user/cust/directCustListSearch` 收到其它代理??? `userId` 筛???时，只在当前代理可见范围内叠加筛???，不泄露其它分支代理或客户???

### 本次变更文件
- `tests/Feature/FrontAgentMainListOwnerBoundaryClosureModuleTest.php`
  - 新增代理列表 `parent_id` 钻取当前树成功???其它树空结果的样例???
  - 新增代理列表现代/鏃? `userId` 跨树筛???空结果样例???
  - 新增客户列表 `parent_id` 钻取当前树成功???其它树空结果的样例???
  - 新增客户列表现代/鏃? `userId` 跨树筛???空结果样例???

### TDD 执行记录
- 行为验证：`php vendor\phpunit\phpunit\phpunit tests\Feature\FrontAgentMainListOwnerBoundaryClosureModuleTest.php` 首次运行中两个行为样例已通过，证明现有主列表会先??? `canViewUser()` 限制可展???父级，再通过 `FrontLegacyData::userScopeIds()` 计算当前代理树范围后叠加 `userId` 筛??夈??
- RED：新增清单测试首次失败，命中???终清单缺少第 251 节???
- GREEN：追加第 251 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontAgentMainListOwnerBoundaryClosureModuleTest` 覆盖真实代理登录态???当前代理树代理、当前代理树客户、其它代理树代理、其它代理树客户、现代代理列表???现代客户列表和旧列表搜索入口???
- 当前树内 `parent_id` 钻取能正常返回直属代理或直属客户；其它代理树 `parent_id` 返回 `count=0,data=[]`銆?
- 跨树 `userId` 筛???在现代和旧入口均返回空分页，避免把其它代理树的用户 ID、姓名???层级字段???资金汇总或客户字段带入响应???

### 剩余边界
- 本轮没有改动 `AgentController` 生产逻辑、代???/客户列表前端、统计汇总???客户组别??欓??或数据库结构???
- 后续继续按旧项目模块清单审计普???用户模块和后台管理员模块其它剩余入口???

## 252. 2026-07-09 前台密码修改归属边界测试闭环

### 本次处理目标
- 涓? `ProfileController::changePassword` 涓? `ProfileController::user_editpsw_save` 补齐密码修改归属边界测试???
- 验证现代 `/api/front/profile/password` 即使携带其它用户 `user_id` 鎴? `userId`，也只能校验并更新当前登录用户的 `user_logins.password`銆?
- 验证??? Web `user/editpsw_save` 使用 `olduserpsw`銆乣newuserpsw`銆乣confirmuserpsw` 时同样只修改当前登录用户密码，不能???过请求参数改写其它用户???

### 本次变更文件
- `tests/Feature/FrontProfilePasswordOwnerBoundaryClosureModuleTest.php`
  - 新增现代密码修改忽略伪??犵敤鎴? ID 的样例???
  - 新增旧密码修改忽略伪造用??? ID 的样例???
  - 两个样例均构造真实当前客户和真实其它客户，并??? `Hash::check` 断言只当前登录用户密码哈希变化，其它用户密码保持原??笺??

### TDD 执行记录
- 行为验证：`php vendor\phpunit\phpunit\phpunit tests\Feature\FrontProfilePasswordOwnerBoundaryClosureModuleTest.php` 首次运行中两个行为样例已通过，证明现有现代和旧密码修改入口都从当前认证用户读??? `user_logins`，不会信任请求中的目标用??? ID銆?
- RED：新增清单测试首次失败，命中???终清单缺少第 252 节???
- GREEN：追加第 252 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `FrontProfilePasswordOwnerBoundaryClosureModuleTest` 覆盖真实普???客户登录??併??真实其它客户??佺幇浠? `/api/front/profile/password` 和旧 `user/editpsw_save` 两个入口???
- 成功改密后当前登录用户旧密码失效、新密码可校验；其它用户旧密码仍可校验，且不会被写入当前用户新密码哈希???

### 剩余边界
- 本轮没有改动 `ProfileController` 生产逻辑、找回密码??侀偖绠?/手机号修改???资料页前端表单或数据库结构???
- 后续继续按旧项目模块清单审计后台管理员模块和其它剩余入口???

## 253. 2026-07-09 后台管理员账号编辑路由目标边界测试闭???

### 本次处理目标
- 涓? `AdminController::update` 补齐后台管理员账号编辑路由目标边界测试???
- 验证 `/api/admin/updateAdmin/{id}` 只能更新路由 `{id}` 指向??? `admins.id` 记录，即使表单隐藏字??? `id` 指向其它管理员，也不能改写其它账号???
- 验证用户名???邮箱???手机号、状态和密码哈希均只落在路由目标管理员上，其它管理员保持原??笺??

### 本次变更文件
- `tests/Feature/AdminAccountRouteTargetBoundaryClosureModuleTest.php`
  - 新增后台管理员编辑忽略伪造表??? `id` 的样例???
  - 样例构???真实操作管理员、真实目标管理员和真实其它管理员，并断言更新后只有路由目标管理员变化???

### TDD 执行记录
- 行为验证：`php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAccountRouteTargetBoundaryClosureModuleTest.php` 首次运行中行为样例已通过，证明现??? `AdminController::update` 以路由参??? `$id` 执行 `Admin::find($id)`，不会信任请求体中的隐藏 `id`銆?
- RED：新增清单测试首次失败，命中???终清单缺少第 253 节???
- GREEN：追加第 253 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `AdminAccountRouteTargetBoundaryClosureModuleTest` 覆盖真实 `admins` 表记录??佸悗鍙? admin guard 登录态??乣/api/admin/updateAdmin/{id}` 编辑入口和表??? `id` 冒充场景???
- 路由目标管理员被正确更新；其它管理员的用户名、邮箱???手机号、状态和密码哈希保持原???，避免后台账号编辑误改非目标账号???

### 剩余边界
- 本轮没有改动 `AdminController` 生产逻辑、管理员账号前端、权限字典???角色同步或数据库结构???
- 后续可继续审??? `AdminController::resetPassword` 是否???要独立路由???按钮权限和行为闭环???

## 254. 2026-07-09 后台管理员账号重置密码路由权限与行为闭环

### 本次处理目标
- 涓? `AdminController::resetPassword` 补齐可达后台 API 路由、按???/API 权限、前端行级动作和行为测试???
- 验证 `/api/admin/resetAdminPassword/{id}` 使用路由 `{id}` 指向??? `admins.id` 作为唯一目标，即使请求体携带其它管理??? `id`，也只能重置路由目标管理员密码???
- 验证 `admin_admin_reset_password` 写入 `permissions.api_route=admin_api_resetAdminPassword`，前端按钮???过 `data-permission` 控制显隐，后端继续由 `check.permission:admin` 做二次鉴权???

### 本次变更文件
- `tests/Feature/AdminAccountResetPasswordClosureModuleTest.php`
  - 新增路由、权限迁移??丩ayui/CrmUi/Naive 前端动作和多语言文案接线测试???
  - 新增后台管理员重置密码忽略伪造表??? `id` 的行为样例???
- `routes/admin.php`
  - 新增 `POST /api/admin/resetAdminPassword/{id}`，命名路由为 `admin_api_resetAdminPassword`銆?
- `database/migrations/2026_06_07_000001_add_admin_content_crud_permissions.php`
  - 新增 `admin_admin_reset_password` 按钮/API 权限???
- `resources/admin/layui/admins/index.blade.php`銆乣public/js/apps/admin/layui/pages.js`
  - 新增管理员列表???重置密码???行级按钮???权限标记和密码提交逻辑???
- `app/Http/Controllers/CrmUi/Admin/PageController.php`銆乣public/js/apps/naive-admin/front-plain.js`
  - 新增 CrmUi/Naive 管理端重置密码行级动作???
- `resources/lang/zh-CN/admin.php`銆乣resources/lang/en/admin.php`銆乣public/js/shared/lang/common/zh-CN.js`銆乣public/js/shared/lang/common/en.js`
  - 新增 `reset_password` 文案???
- `tests/Feature/AdminContentCrudPermissionMigrationTest.php`
  - 灏? `admin_api_resetAdminPassword` 路由??? `admin_admin_reset_password` 权限纳入管理??? CRUD 权限回归???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAccountResetPasswordClosureModuleTest.php` 首次运行失败，命??? `admin_api_resetAdminPassword` 命名路由不存在??乣/api/admin/resetAdminPassword/{id}` 返回 404、最终清单缺少第 254 节???
- GREEN：补齐路由???权限迁移???前端动作??佽瑷?包和清单后，目标测试通过???

### 当前证据
- `AdminAccountResetPasswordClosureModuleTest` 覆盖真实 `admins` 表记录??佸悗鍙? admin guard 登录态??乣/api/admin/resetAdminPassword/{id}` 重置密码入口和表??? `id` 冒充场景???
- 路由目标管理员密码被正确重置；其它管理员旧密码仍可校验，且不会被写入路由目标的新密码哈希???
- Layui銆丆rmUi 鍜? Naive 管理端均存在重置密码动作入口，权限标识统???涓? `admin_admin_reset_password`，接口权限统???绑定 `admin_api_resetAdminPassword`銆?

### 剩余边界
- 本轮没有改动管理员新增???编辑???删除???角色同步???后台登录认证???当前管理员自助改密或数据库结构???
- 后续继续按旧项目模块清单审计后台管理员模块???普通用户模块和代理商模块其它剩余入口???

## 255. 2026-07-09 后台管理员账号删除路由目标边界测试闭???

### 本次处理目标
- 涓? `AdminController::destroy` 补齐后台管理员账号删除路由目标边界测试???
- 验证 `/api/admin/deleteAdmin/{id}` 只能软删除路??? `{id}` 指向??? `admins.id` 记录，即使请求体 `id` 指向其它管理员，也不能删除其它账号???
- 验证 `admin_admin_delete` 按钮/API 权限对应删除入口，前端可继续只把当前表格??? `admins.id` 作为路由目标提交???

### 本次变更文件
- `tests/Feature/AdminAccountDeleteRouteTargetBoundaryClosureModuleTest.php`
  - 新增后台管理员删除忽略伪造表??? `id` 的样例???
  - 样例构???真实操作管理员、真实目标管理员和真实其它管理员，并断言只有路由目标管理??? `deleted_at` 被写入???

### TDD 执行记录
- 行为验证：`php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAccountDeleteRouteTargetBoundaryClosureModuleTest.php` 首次运行中行为样例已通过，证明现??? `AdminController::destroy` 以路由参??? `$id` 执行 `Admin::find($id)`，不会信任请求体中的隐藏 `id`銆?
- RED：新增清单测试首次失败，命中???终清单缺少第 255 节???
- GREEN：追加第 255 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `AdminAccountDeleteRouteTargetBoundaryClosureModuleTest` 覆盖真实 `admins` 表记录??佸悗鍙? admin guard 登录态??乣/api/admin/deleteAdmin/{id}` 删除入口和表??? `id` 冒充场景???
- 路由目标管理员被软删除；其它管理员的 `deleted_at` 仍为 `null`，用户名和邮箱保持原值，避免后台账号删除误删非目标账号???

### 剩余边界
- 本轮没有改动 `AdminController` 生产逻辑、管理员账号前端、权限字典???角色同步???重置密码或数据库结构???
- 后续继续按旧项目模块清单审计后台管理员模块???普通用户模块和代理商模块其它剩余入口???

## 256. 2026-07-09 后台管理员列表密码字段隐藏测试闭???

### 本次处理目标
- 涓? `AdminController::index` 补齐后台管理员列表敏感字段隐藏测试???
- 验证旧列表入??? `/api/admin/adminList` 和新列表入口 `/api/admin/admins` 都不会返??? `admins.password` 字段或密码哈希内容???
- 验证测试样例必须在响应列表中命中真实管理员记录，避免仅靠空列表误判安全???

### 本次变更文件
- `tests/Feature/AdminAccountListPasswordHiddenClosureModuleTest.php`
  - 新增管理员列表不暴露密码哈希的样例???
  - 样例构???真实管理员账号，并分别请求 `/api/admin/adminList` 涓? `/api/admin/admins`锛屾柇瑷?目标管理员存在但响应行没??? `password` 字段，完整响应内容也不包含密码哈希???

### TDD 执行记录
- 行为验证：`php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAccountListPasswordHiddenClosureModuleTest.php` 首次运行中行为样例已通过，证明现??? `Admin` 模型??? `$hidden = ['password']` 会在 `AdminController::index` 分页响应中隐藏密码字段???
- RED：新增清单测试首次失败，命中???终清单缺少第 256 节???
- GREEN：追加第 256 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `AdminAccountListPasswordHiddenClosureModuleTest` 覆盖真实 `admins` 表记录??佸悗鍙? admin guard 登录态???旧 `/api/admin/adminList` 和新 `/api/admin/admins` 两个列表入口???
- 响应列表能找到测试管理员账号，但该行不包??? `password` 字段；完??? JSON 响应不包含对应的 `admins.password` 哈希???

### 剩余边界
- 本轮没有改动 `AdminController` 生产逻辑、`Admin` 模型、管理员账号前端、权限字典???重置密码???删除或数据库结构???
- 后续继续按旧项目模块清单审计后台管理员模块???普通用户模块和代理商模块其它剩余入口???

## 257. 2026-07-09 后台管理员账号新增主键伪造边界测试闭???

### 本次处理目标
- 涓? `AdminController::store` 补齐后台管理员账号新增主键伪造边界测试???
- 验证 `/api/admin/createAdmin` 收到请求??? `id` 时不会把该??煎啓鍏? `admins.id`，也不会覆盖已有管理员账号???
- 验证新增账号仍由数据库自增主键生成，密码??? `Hash::make` 写入新账号，已有超级管理员账号保持原用户名???邮箱和密码???

### 本次变更文件
- `tests/Feature/AdminAccountCreatePrimaryKeyBoundaryClosureModuleTest.php`
  - 新增后台管理员新增忽略伪造主??? `id` 的样例???
  - 样例构???真实超级管理员，再以请求体 `id=1` 新增另一个管理员，断???新账号主键不??? `1`锛屼笖鍘? `admins.id=1` 未被覆盖???

### TDD 执行记录
- 行为验证：`php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAccountCreatePrimaryKeyBoundaryClosureModuleTest.php` 首次运行中行为样例已通过，证明现??? `AdminController::store` 只从请求中读??? `username`銆乣email`銆乣password` 和显式可选账号字段，不信任请求体 `id`銆?
- RED：新增清单测试首次失败，命中???终清单缺少第 257 节???
- GREEN：追加第 257 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `AdminAccountCreatePrimaryKeyBoundaryClosureModuleTest` 覆盖真实 `admins` 表记录??佸悗鍙? admin guard 登录态??乣/api/admin/createAdmin` 新增入口和请求体 `id` 冒充场景???
- 新增管理员使用数据库自增主键；原超级管理员用户名、邮箱和密码哈希保持不变，避免新增表单主键参数覆盖已有后台账号???

### 剩余边界
- 本轮没有改动 `AdminController` 生产逻辑、`Admin` 模型、管理员账号前端、权限字典???编辑???重置密码???删除或数据库结构???
- 后续继续按旧项目模块清单审计后台管理员模块???普通用户模块和代理商模块其它剩余入口???

## 258. 2026-07-09 后台管理员列表软删除过滤测试闭环

### 本次处理目标
- 涓? `AdminController::index` 补齐后台管理员列表软删除过滤测试???
- 验证旧列表入??? `/api/admin/adminList` 和新列表入口 `/api/admin/admins` 都不会返??? `admins.deleted_at` 已写入的软删除账号???
- 验证同一响应中仍能返回未删除管理员账号，避免空列表???成误判???

### 本次变更文件
- `tests/Feature/AdminAccountListSoftDeleteBoundaryClosureModuleTest.php`
  - 新增管理员列表排除软删除账号的样例???
  - 样例构???真实未删除管理员和真实已软删除管理员，并分别请??? `/api/admin/adminList` 涓? `/api/admin/admins`锛屾柇瑷?未删除账号可见???软删除账号不可见???

### TDD 执行记录
- 行为验证：`php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAccountListSoftDeleteBoundaryClosureModuleTest.php` 首次运行中行为样例已通过，证明现??? `Admin::query()` 会继??? `SoftDeletes` 全局作用域并过滤 `deleted_at` 非空记录???
- RED：新增清单测试首次失败，命中???终清单缺少第 258 节???
- GREEN：追加第 258 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `AdminAccountListSoftDeleteBoundaryClosureModuleTest` 覆盖真实 `admins` 表记录??佸悗鍙? admin guard 登录态???旧 `/api/admin/adminList` 和新 `/api/admin/admins` 两个列表入口???
- 未删除管理员能在列表中命中；软删除管理员不会出现在列表行里，完整 JSON 响应也不包含其邮箱???

### 剩余边界
- 本轮没有改动 `AdminController` 生产逻辑、`Admin` 模型、管理员账号前端、权限字典???新增???编辑???重置密码???删除或数据库结构???
- 后续继续按旧项目模块清单审计后台管理员模块???普通用户模块和代理商模块其它剩余入口???

## 259. 2026-07-09 后台管理员当前登录资料更新归属与邮箱唯一边界闭环

### 本次处理目标
- 涓? `AuthController::updateProfile` 补齐当前登录管理员资料更新边界测试???
- 验证 `/api/admin/updateProfile` 只更新当??? admin guard 登录管理员，即使请求体携带其它管理员 `id`，也不能改写其它后台账号???
- 验证该接口只允许更新 `email` 涓? `mobile`，不能???过请求体改??? `username`銆乣role_id`銆乣status` 鎴? `password` 等敏感字段???
- 验证 `admins.email` 不能改成其它管理员已占用邮箱，避免后台登录账号邮箱出现歧义???

### 本次变更文件
- `tests/Feature/AdminProfileUpdateOwnerBoundaryClosureModuleTest.php`
  - 新增当前登录资料更新忽略伪???目标和敏感字段的样例???
  - 新增当前管理员邮箱不能改为其它管理员邮箱的样例???
- `app/Http/Controllers/Admin/AuthController.php`
  - 鍦? `AuthController::updateProfile` 鐨? `email` 校验中加??? `admins.email` 唯一性规则，并排除当前登录管理员自身???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminProfileUpdateOwnerBoundaryClosureModuleTest.php` 首次运行失败，命中重复邮箱仍返回成功码???最终清单缺少第 259 节???
- GREEN锛氳ˉ榻? `admins.email` 唯一校验和第 259 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminProfileUpdateOwnerBoundaryClosureModuleTest` 覆盖真实 `admins` 表记录??佸悗鍙? admin guard 登录态??乣/api/admin/updateProfile` 当前资料更新入口、伪造目??? `id`、敏感字段提交和重复邮箱场景???
- 当前登录管理员的 `email`銆乣mobile` 可更新；其它管理员记录保持不变???
- `username`銆乣status`銆乣password` 等敏感字段不会???过当前资料接口写入；重??? `admins.email` 返回 `ResponseCode::VALIDATION_FAILED`，双方邮箱和手机号保持原值???

### 剩余边界
- 本轮没有改动后台登录、登出???头像上传???当前管理员改密、管理员账号 CRUD 前端或数据库结构???
- 后续继续按旧项目模块清单审计后台管理员模块???普通用户模块和代理商模块其它剩余入口???

## 260. 2026-07-09 后台管理员当前登录改密归属边界测试闭???

### 本次处理目标
- 涓? `AuthController::changePassword` 补齐当前登录管理员改密归属边界测试???
- 验证 `/api/admin/changePassword` 即使携带其它管理??? `id` 鎴? `admin_id`，也只能校验并更新当??? admin guard 登录管理员的 `admins.password`銆?
- 验证旧密码错误时返回 `ResponseCode::OLD_PASSWORD_WRONG`，且当前管理员和其它管理员密码均不被改写???

### 本次变更文件
- `tests/Feature/AdminProfilePasswordOwnerBoundaryClosureModuleTest.php`
  - 新增当前管理员改密忽略伪造目标管理员 ID 的样例???
  - 新增旧密码错误时不写入任何管理员密码的样例???

### TDD 执行记录
- 行为验证：`php vendor\phpunit\phpunit\phpunit tests\Feature\AdminProfilePasswordOwnerBoundaryClosureModuleTest.php` 首次运行中两个行为样例已通过，证明现??? `AuthController::changePassword` 从当??? admin guard 用户读取密码哈希，不信任请求体目??? ID銆?
- RED：新增清单测试首次失败，命中???终清单缺少第 260 节???
- GREEN：追加第 260 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `AdminProfilePasswordOwnerBoundaryClosureModuleTest` 覆盖真实 `admins` 表记录??佸悗鍙? admin guard 登录态??乣/api/admin/changePassword` 当前改密入口、伪??? `id/admin_id` 和旧密码错误场景???
- 成功改密后只有当前登录管理员密码变更，其它管理员旧密码仍可校验，且不会被写入当前管理员新密码哈希???
- 旧密码错误时返回 `ResponseCode::OLD_PASSWORD_WRONG`；当前管理员和其它管理员??? `admins.password` 都保持原值???

### 剩余边界
- 本轮没有改动 `AuthController` 生产逻辑、后台登录???登出??乀oken 刷新、头像上传???管理员账号 CRUD 前端或数据库结构???
- 后续继续按旧项目模块清单审计后台管理员模块???普通用户模块和代理商模块其它剩余入口???

## 261. 2026-07-09 后台管理员当前资料读取归属与密码隐藏测试闭环

### 本次处理目标
- 涓? `AuthController::profileInfo` 补齐当前登录管理员资料读取边界测试???
- 验证 `/api/admin/profileInfo` 即使携带其它管理??? `id` 鎴? `admin_id`，也只返回当??? admin guard 登录管理员资料???
- 验证响应中不包含 `admins.password` 字段或当???/其它管理员密码哈希，避免后台个人资料接口泄露敏感凭证???

### 本次变更文件
- `tests/Feature/AdminProfileInfoOwnerBoundaryClosureModuleTest.php`
  - 新增当前资料读取忽略伪???目标管理员 ID 的样例???
  - 新增响应 JSON 不包??? `password` 字段和密码哈希的断言???

### TDD 执行记录
- 行为验证：`php vendor\phpunit\phpunit\phpunit tests\Feature\AdminProfileInfoOwnerBoundaryClosureModuleTest.php` 首次运行中行为样例已通过，证明现??? `AuthController::profileInfo` 从当??? admin guard 用户返回资料，并??? `Admin` 模型隐藏 `password`銆?
- RED：新增清单测试首次失败，命中???终清单缺少第 261 节???
- GREEN：追加第 261 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `AdminProfileInfoOwnerBoundaryClosureModuleTest` 覆盖真实 `admins` 表记录??佸悗鍙? admin guard 登录态??乣/api/admin/profileInfo` 当前资料读取入口和伪??? `id/admin_id` 场景???
- 响应 `data.id`銆乣data.username`銆乣data.email` 均属于当前登录管理员；其它管理员邮箱不会出现在响应中???
- 响应 `data` 不包??? `password` 字段，完??? JSON 响应不包含当前管理员或其它管理员??? `admins.password` 哈希???

### 剩余边界
- 本轮没有改动 `AuthController` 生产逻辑、`Admin` 模型、后台登录???登出??乀oken 刷新、头像上传???当前管理员改密或数据库结构???
- 后续继续按旧项目模块清单审计后台管理员模块???普通用户模块和代理商模块其它剩余入口???

## 262. 2026-07-09 后台管理员账号编辑空密码保留旧密码测试闭???

### 本次处理目标
- 涓? `AdminController::update` 补齐后台管理员账号编辑时空密码提交边界测试???
- 验证 `/api/admin/updateAdmin/{id}` 收到 `password` 空字符串时，仍只更新用户名???邮箱???手机号等资料字段，不覆盖目标管理员原有 `admins.password`銆?
- 验证编辑响应不返??? `password` 字段，避免账号维护接口泄露密码哈希???

### 本次变更文件
- `tests/Feature/AdminAccountUpdateBlankPasswordClosureModuleTest.php`
  - 新增编辑管理员时显式提交??? `password` 的样例???
  - 断言目标管理员旧密码哈希保持原???，且空字符串不会被写入或重新哈希???

### TDD 执行记录
- 行为验证：`php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAccountUpdateBlankPasswordClosureModuleTest.php` 首次运行中行为样例已通过，证明现??? `AdminController::update` 通过 `$request->filled('password')` 保留空密码场景下的旧密码???
- RED：新增清单测试首次失败，命中???终清单缺少第 262 节???
- GREEN：追加第 262 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `AdminAccountUpdateBlankPasswordClosureModuleTest` 覆盖真实 `admins` 表记录??佸悗鍙? admin guard 登录态和 `/api/admin/updateAdmin/{id}` 编辑入口???
- 显式提交 `password=''` 时，目标管理员用户名、邮箱和手机号可更新；`admins.password` 哈希与请求前完全???致，旧密码仍可校验???
- 响应 `data` 不包??? `password` 字段，避免编辑管理员接口返回密码哈希???

### 剩余边界
- 本轮没有改动 `AdminController` 生产逻辑、管理员账号前端、权限字典???角色同步???重置密码???删除或数据库结构???
- 后续继续按旧项目模块清单审计后台管理员模块???普通用户模块和代理商模块其它剩余入口???

## 266. 2026-07-09 后台普???用户资料更新字段白名单闭环

### 本次处理目标
- 涓? `AdminUserController::updateUser` 补齐后台普???用户资料更新字段白名单测试???
- 验证 `/api/admin/users/{user}` 只允许详情页真实提交??? `user_name`銆乣phone` 写入 `user_infos`銆?
- 验证请求体中??? `id/user_id` 伪???目标??乣account_type`銆乣parent_id`銆乣auth_status`、资金字段和出入金开关不能???过资料保存接口改写???

### 本次变更文件
- `tests/Feature/AdminUserUpdateFieldWhitelistClosureModuleTest.php`
  - 新增后台用户资料更新只写入基???资料字段的样例???
  - 断言账号类型、上级代理???认证状态???资金和出入金开关保持原值???
- `app/Http/Controllers/Admin/AdminUserController.php`
  - 灏? `updateUser` 写入数据从请求排除主键改为明确白名单 `user_name`銆乣phone`銆?
  - 更新方法注释，说明敏感字段必须由各自专用流程维护???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminUserUpdateFieldWhitelistClosureModuleTest.php` 首次运行失败，命??? `account_type` 可被资料保存接口改写、最终清单缺少第 266 节???
- GREEN锛氭敹绐? `updateUser` 写入白名单并追加??? 266 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminUserUpdateFieldWhitelistClosureModuleTest` 覆盖真实 `admins`銆乣user_logins`銆乣user_infos` 表记录，后台 admin guard 登录态和 `/api/admin/users/{user}` 资料更新入口???
- `user_name` 涓? `phone` 可???过详情页保存更新???
- 请求体伪造的 `user_id` 不会切换目标；`account_type`銆乣parent_id`銆乣auth_status`銆乣total_funds`銆乣is_deposit_allowed`銆乣is_withdrawal_allowed` 均保持原值???

### 剩余边界
- 本轮没有改动用户列表、导出???详情读取???登录启停???实名认证审核???数据范围服务???前端页面或数据库结构???
- 后续继续按旧项目模块清单审计后台普???用户模块???代理商模块和后台管理员模块其它剩余入口???

## 265. 2026-07-09 后台普???用户登录启停状态校验闭???

### 本次处理目标
- 涓? `AdminUserController::changeUserStatus` 补齐后台普???用户登录启停状态校验测试???
- 验证 `/api/admin/changeUserStatus` 只接??? `is_enabled=0/1`，分别表示禁用和启用 `user_logins.is_enabled`銆?
- 验证传入非法 `is_enabled` 时返??? `ResponseCode::VALIDATION_FAILED`，且不改写用户登录账号状态???

### 本次变更文件
- `tests/Feature/AdminUserStatusValidationClosureModuleTest.php`
  - 新增合法 `is_enabled=0/1` 可正常切换的样例???
  - 新增非法 `is_enabled=2` 被拒绝且不写??? `user_logins.is_enabled` 的样例???
- `app/Http/Controllers/Admin/AdminUserController.php`
  - 鍦? `changeUserStatus` 通过数据范围校验后增??? `required|in:0,1` 参数校验，并将写入???转为整数???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminUserStatusValidationClosureModuleTest.php` 首次运行失败，命中非??? `is_enabled=2` 仍返回成功码、最终清单缺少第 265 节???
- GREEN锛氳ˉ榻? `is_enabled` 校验和第 265 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminUserStatusValidationClosureModuleTest` 覆盖真实 `admins`銆乣user_logins`銆乣user_infos` 表记录，后台 admin guard 登录态和 `/api/admin/changeUserStatus` 启停入口???
- `is_enabled=0` 会把 `user_logins.is_enabled` 更新为禁用，`is_enabled=1` 会恢复启用???
- `is_enabled=2` 返回参数校验失败，原 `user_logins.is_enabled` 保持 `1`，避免写入非布尔启停状??併??

### 剩余边界
- 本轮没有改动用户列表、导出???详情???资料更新???实名认证审核???数据范围服务???前端页面或数据库结构???
- 后续继续按旧项目模块清单审计后台普???用户模块???代理商模块和后台管理员模块其它剩余入口???

## 264. 2026-07-09 后台管理员账号角色与状???字段校验测试闭???

### 本次处理目标
- 涓? `AdminController::store` 涓? `AdminController::update` 补齐后台管理??? `role_id/status` 字段校验边界测试???
- 验证 `/api/admin/createAdmin` 新增账号时，`role_id` 指向不存在的角色??? `status` 不在 `0/1` 范围内时必须返回 `ResponseCode::VALIDATION_FAILED`，且不创建新账号???
- 验证 `/api/admin/updateAdmin/{id}` 编辑账号时，非法 `role_id/status` 不会改写目标管理员的账号资料、角色???状态或密码???

### 本次变更文件
- `tests/Feature/AdminAccountRoleStatusValidationClosureModuleTest.php`
  - 新增新增管理员拒绝不存在 `role_id` 和非??? `status` 的样例???
  - 新增编辑管理员拒绝不存在 `role_id` 和非??? `status` 的样例???

### TDD 执行记录
- 行为验证：`php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAccountRoleStatusValidationClosureModuleTest.php` 首次运行中两个行为样例已通过，证明现??? `AdminController::store` 涓? `AdminController::update` 已???过 `exists:roles,id` 鍜? `in:0,1` 校验角色与状态字段???
- RED：新增清单测试首次失败，命中???终清单缺少第 264 节???
- GREEN：追加第 264 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `AdminAccountRoleStatusValidationClosureModuleTest` 覆盖真实 `admins` 表记录??佸悗鍙? admin guard 登录态??乣/api/admin/createAdmin` 新增入口??? `/api/admin/updateAdmin/{id}` 编辑入口???
- 新增账号传入不存??? `role_id` 或非??? `status` 时返回参数校验失败，不会写入新管理员???
- 编辑账号传入不存??? `role_id` 或非??? `status` 时返回参数校验失败，目标管理员用户名、邮箱??乣role_id`銆乣status` 和密码均保持原??笺??

### 剩余边界
- 本轮没有改动 `AdminController` 生产逻辑、管理员账号前端、权限字典???角色同步???重置密码???删除或数据库结构???
- 后续继续按旧项目模块清单审计后台管理员模块???普通用户模块和代理商模块其它剩余入口???

## 263. 2026-07-09 后台管理员账号登录标识唯???性测试闭???

### 本次处理目标
- 涓? `AdminController::store` 涓? `AdminController::update` 补齐后台管理员登录标识唯???性测试???
- 验证 `/api/admin/createAdmin` 新增账号时，`admins.username` 鎴? `admins.email` 已被其它管理员占用时必须返回 `ResponseCode::VALIDATION_FAILED`，且不创建新账号???
- 验证 `/api/admin/updateAdmin/{id}` 编辑账号时，不能把目标管理员的用户名或邮箱改成其它管理员已占用???，避免后台登录标识歧义???

### 本次变更文件
- `tests/Feature/AdminAccountUniqueIdentityClosureModuleTest.php`
  - 新增新增管理员拒绝重??? `username/email` 的样例???
  - 新增编辑管理员拒绝使用其它管理员 `username/email` 的样例???

### TDD 执行记录
- 行为验证：`php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAccountUniqueIdentityClosureModuleTest.php` 首次运行中两个行为样例已通过，证明现??? `AdminController::store` 涓? `AdminController::update` 已使??? `unique:admins` 校验账号登录标识???
- RED：新增清单测试首次失败，命中???终清单缺少第 263 节???
- GREEN：追加第 263 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `AdminAccountUniqueIdentityClosureModuleTest` 覆盖真实 `admins` 表记录??佸悗鍙? admin guard 登录态??乣/api/admin/createAdmin` 新增入口??? `/api/admin/updateAdmin/{id}` 编辑入口???
- 新增账号传入重复 `admins.username` 鎴? `admins.email` 时返回参数校验失败，不会写入伪???的新账号???
- 编辑账号传入其它管理员已占用??? `admins.username` 鎴? `admins.email` 时返回参数校验失败，目标管理员用户名、邮箱???手机号和密码均保持原??笺??

### 剩余边界
- 本轮没有改动 `AdminController` 生产逻辑、管理员账号前端、权限字典???角色同步???重置密码???删除或数据库结构???
- 后续继续按旧项目模块清单审计后台管理员模块???普通用户模块和代理商模块其它剩余入口???
## 267. 2026-07-09 后台普???用户启??? REST 路由目标边界闭环

### 本次处理目标
- 涓? `AdminUserController::changeUserStatus` 补齐 REST 启停路由目标边界测试???
- 验证 `/api/admin/users/{user}/status` 只使用路??? `{user}` 作为目标用户，不信任请求体里伪???的 `user_id`銆?
- 验证启停动作只写入目标用户的 `user_logins.is_enabled`，不会误改请求体中其它用户的登录状??併??

### 本次变更文件
- `tests/Feature/AdminUserStatusRouteTargetBoundaryClosureModuleTest.php`
  - 新增 REST 启停路由忽略请求体伪??? `user_id` 的样例???
  - 构???目标用户和其它用户，断???只更新路由目标用户的 `user_logins.is_enabled`銆?

### TDD 执行记录
- 行为验证：`php vendor\phpunit\phpunit\phpunit tests\Feature\AdminUserStatusRouteTargetBoundaryClosureModuleTest.php` 首次运行中行为样例已通过，证明现??? REST 路由会把 `{user}` 注入为目??? `user_id`銆?
- RED：新增清单测试首次失败，命中???终清单缺少第 267 节???
- GREEN：追加第 267 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `AdminUserStatusRouteTargetBoundaryClosureModuleTest` 覆盖真实 `admins`銆乣user_logins`銆乣user_infos` 表记录，后台 admin guard 登录态和 `/api/admin/users/{user}/status` 启停入口???
- 请求体即使携带其它用??? `user_id`，也只会更新路由目标用户??? `user_logins.is_enabled`銆?
- 其它用户??? `user_logins.is_enabled` 保持原???，避免 REST 入口被请求体目标覆盖???

### 剩余边界
- 本轮没有改动用户列表、导出???详情读取???资料更新???实名认证审核???数据范围服务???前端页面或数据库结构???
- 后续继续按旧项目模块清单审计后台普???用户模块???代理商模块和后台管理员模块其它剩余入口???
## 268. 2026-07-09 后台普???用户详??? REST 路由目标边界闭环

### 本次处理目标
- 涓? `AdminUserController::userDetail` 补齐 REST 详情路由目标边界测试???
- 验证 `/api/admin/users/{user}` 只使用路??? `{user}` 读取目标用户详情，不信任请求体里伪???的 `user_id`銆?
- 验证详情响应来自目标用户??? `user_infos.user_id` 和关??? `user_logins.user_id`，不会返回请求体中其它用户资料???

### 本次变更文件
- `tests/Feature/AdminUserDetailRouteTargetBoundaryClosureModuleTest.php`
  - 新增 REST 详情路由忽略请求体伪??? `user_id` 的样例???
  - 构???目标用户和其它用户，断???响应只包含路由目标用户资料???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminUserDetailRouteTargetBoundaryClosureModuleTest.php` 首次运行先暴露测试对 `user_id` JSON 类型的错误假设；修正为字符串断言后，行为样例通过，清单测试失败，命中???终清单缺少第 268 节???
- GREEN：追加第 268 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `AdminUserDetailRouteTargetBoundaryClosureModuleTest` 覆盖真实 `admins`銆乣user_logins`銆乣user_infos` 表记录，后台 admin guard 登录态和 `/api/admin/users/{user}` 详情入口???
- 请求体即使携带其它用??? `user_id`锛屽搷搴? `data.user_id` 涓? `data.login.user_id` 仍属于路由目标用户???
- 完整响应不包含其它用户名称，避免 REST 详情入口被请求体目标覆盖???

### 剩余边界
- 本轮没有改动用户列表、导出???资料更新???登录启停???实名认证审核???数据范围服务???前端页面或数据库结构???
- 后续继续按旧项目模块清单审计后台普???用户模块???代理商模块和后台管理员模块其它剩余入口???
## 269. 2026-07-09 后台普???用户实名认证审核???过状??侀棴鐜?

### 本次处理目标
- 涓? `AdminUserController::reviewAuth` 补齐实名认证审核通过路径测试???
- 验证 `/api/admin/reviewAuth` 鍦? `status=1` 时把 `user_auths.id_card_status` 涓? `user_auths.bank_status` 统一更新为???过状??併??
- 验证审核通过会清空旧拒绝原因，并同步 `user_infos.auth_status=1`，同时写入后台操作日志???

### 本次变更文件
- `tests/Feature/AdminReviewAuthApproveStateClosureModuleTest.php`
  - 新增实名认证审核通过时状态???备注和操作日志的样例???
  - 构???身份证待审、银行卡换绑待审且带旧拒绝原因的用户，断???通过后旧原因被清空???

### TDD 执行记录
- 行为验证：`php vendor\phpunit\phpunit\phpunit tests\Feature\AdminReviewAuthApproveStateClosureModuleTest.php` 首次运行中行为样例已通过，证明现??? `reviewAuth` 通过路径会写入真实认证字段???清空备注并记录日志???
- RED：新增清单测试首次失败，命中???终清单缺少第 269 节???
- GREEN：追加第 269 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `AdminReviewAuthApproveStateClosureModuleTest` 覆盖真实 `admins`銆乣user_logins`銆乣user_infos`銆乣user_auths` 涓? `operation_logs` 表记录，后台 admin guard 登录态和 `/api/admin/reviewAuth` 审核入口???
- 审核通过??? `user_auths.id_card_status=2`銆乣user_auths.bank_status=2`、拒绝备注清空，`user_infos.auth_status=1`銆?
- 操作日志记录管理员???目标用户???审核状态和认证状??佸彉鏇磋建杩广??

### 剩余边界
- 本轮没有改动认证待审列表、已审列表???详情页脚本、数据范围服务???前端页面或数据库结构???
- 后续继续按旧项目模块清单审计后台普???用户模块???代理商模块和后台管理员模块其它剩余入口???
## 272. 2026-07-09 后台代理商佣金更新目??? ID 严格校验闭环

### 本次处理目标
- 涓? `AgentController::updateCommission` 补齐代理佣金更新 `agent_id` 严格校验测试???
- 验证 `/api/admin/updateAgentCommission` 不能??? `agent_id=真实IDabc` 交给数据库按前缀数字匹配真实代理???
- 验证非法 `agent_id` 返回 `ResponseCode::VALIDATION_FAILED`，且不改写目标代理的 `user_infos.comm_rate`銆?

### 本次变更文件
- `tests/Feature/AdminAgentCommissionAgentIdValidationClosureModuleTest.php`
  - 新增非严??? `agent_id` 被拒绝且不落库的样例???
- `app/Http/Controllers/Admin/AgentController.php`
  - 鍦? `updateCommission` 查询 `user_infos.user_id` 前先校验 `agent_id` 涓? `comm_rate`，校验???过后再转换目标 ID 并执行业务??昏緫銆?

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAgentCommissionAgentIdValidationClosureModuleTest.php` 首次运行失败，命??? `agent_id=真实IDabc` 仍返回成功码，最终清单也缺少??? 272 节???
- 调试：真??? `user_infos.comm_rate` 对小数测试夹具会按当前表结构落为 `0.0`，因此将不改写断???的原始???调整为 `1.0`銆?
- GREEN锛氳ˉ榻? `updateCommission` 前置参数校验和第 272 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminAgentCommissionAgentIdValidationClosureModuleTest` 覆盖真实 `admins`銆乣user_logins` 涓? `user_infos` 表记录，后台 admin guard 登录态和 `/api/admin/updateAgentCommission` 更新入口???
- 非严??? `agent_id=真实IDabc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 鍘? `user_infos.comm_rate` 保持原???，避免佣金更新接口在参数校验前被数据库数字前缀规则命中真实代理???

### 剩余边界
- 本轮没有改动代理列表、代理详情???下级列表???等级更新??佺‘璁?/拒绝代理、前端页面或数据库结构???
- 后续继续按旧项目模块清单审计代理商模块???后台普通用户模块和后台管理员模块其它剩余入口???
## 273. 2026-07-09 后台代理商等级更新目??? ID 严格校验闭环

### 本次处理目标
- 涓? `AgentController::updateLevel` 补齐代理等级更新 `agent_id` 严格校验测试???
- 验证 `/api/admin/updateAgentLevel` 不能??? `agent_id=真实IDabc` 交给数据库按前缀数字匹配真实代理???
- 验证非法 `agent_id` 返回 `ResponseCode::VALIDATION_FAILED`，且不改写目标代理的 `user_infos.level_id`銆?

### 本次变更文件
- `tests/Feature/AdminAgentLevelAgentIdValidationClosureModuleTest.php`
  - 新增非严??? `agent_id` 被拒绝且不落库的样例???
- `app/Http/Controllers/Admin/AgentController.php`
  - 鍦? `updateLevel` 查询 `user_infos.user_id` 前先校验 `agent_id` 涓? `level`，校验???过后再转换目标 ID 并执行业务??昏緫銆?

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAgentLevelAgentIdValidationClosureModuleTest.php` 首次运行失败，命??? `agent_id=真实IDabc` 仍返回成功码，最终清单也缺少??? 273 节???
- GREEN锛氳ˉ榻? `updateLevel` 前置参数校验和第 273 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminAgentLevelAgentIdValidationClosureModuleTest` 覆盖真实 `admins`銆乣agent_levels`銆乣user_logins` 涓? `user_infos` 表记录，后台 admin guard 登录态和 `/api/admin/updateAgentLevel` 更新入口???
- 非严??? `agent_id=真实IDabc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 鍘? `user_infos.level_id` 保持原???，避免等级更新接口在参数校验前被数据库数字前缀规则命中真实代理???

### 剩余边界
- 本轮没有改动代理列表、代理详情???下级列表???佣金更新??佺‘璁?/拒绝代理、前端页面或数据库结构???
- 后续继续按旧项目模块清单审计代理商模块???后台普通用户模块和后台管理员模块其它剩余入口???
## 274. 2026-07-09 后台代理商确认???过清理旧拒绝备注闭???

### 本次处理目标
- 涓? `AgentController::confirmAgent` 补齐确认通过后的状???闭环测试???
- 验证 `/api/admin/confirmAgent` 灏? `is_agent_confirmed` 更新为???过时，会同步清空上???次拒绝写入的 `user_infos.remark`銆?
- 避免代理已确认但详情中仍残留旧拒绝原因，造成后台和前台状态解释不???致???

### 本次变更文件
- `tests/Feature/AdminAgentConfirmationApproveRemarkClosureModuleTest.php`
  - 新增带旧拒绝备注的待确认代理再次确认通过样例???
- `app/Http/Controllers/Admin/AgentController.php`
  - 鍦? `confirmAgent` 的事务内同时写入 `is_agent_confirmed=1` 与空 `remark`銆?

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAgentConfirmationApproveRemarkClosureModuleTest.php` 首次运行失败，命中确认??氳繃鍚? `user_infos.remark` 仍保留旧拒绝原因，最终清单也缺少??? 274 节???
- GREEN锛氳ˉ榻? `confirmAgent` 清空旧备注???辑和第 274 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminAgentConfirmationApproveRemarkClosureModuleTest` 覆盖真实 `admins`銆乣user_logins` 涓? `user_infos` 表记录，后台 admin guard 登录态和 `/api/admin/confirmAgent` 更新入口???
- 确认通过??? `is_agent_confirmed=1`銆?
- 鍘? `user_infos.remark` 被清空，避免旧拒绝原因在通过状???下继续残留???

### 剩余边界
- 本轮没有改动代理列表、代理详情???下级列表???等级更新???佣金更新???拒绝代理???前端页面或数据库结构???
- 后续继续按旧项目模块清单审计代理商模块???后台普通用户模块和后台管理员模块其它剩余入口???
## 275. 2026-07-09 后台代理商拒绝原??? trim 后非空校验闭???

### 本次处理目标
- 涓? `AgentController::rejectAgentConfirmation` 补齐拒绝原因空白字符串边界测试???
- 验证 `/api/admin/rejectAgentConfirmation` 收到空格 `reason` 时返??? `ResponseCode::VALIDATION_FAILED`銆?
- 验证空白原因不会改写 `is_agent_confirmed`銆乣user_infos.remark`，也不会写入 `operation_logs`銆?

### 本次变更文件
- `tests/Feature/AdminAgentRejectReasonTrimValidationClosureModuleTest.php`
  - 新增空格拒绝原因被参数校验拦截且不落库的样例???

### TDD 执行记录
- 行为验证：`php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAgentRejectReasonTrimValidationClosureModuleTest.php` 首次运行中行为样例已通过，证明当前请求清理与 `required|string|max:500` 校验会拒绝空格原因???
- RED：新增清单测试首次失败，命中???终清单缺少第 275 节???
- GREEN：追加第 275 节清单记录后，目标测试??氳繃銆?

### 当前证据
- `AdminAgentRejectReasonTrimValidationClosureModuleTest` 覆盖真实 `admins`銆乣user_logins`銆乣user_infos` 涓? `operation_logs` 表记录，后台 admin guard 登录态和 `/api/admin/rejectAgentConfirmation` 更新入口???
- 空格 `reason` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 鍘? `is_agent_confirmed` 涓? `user_infos.remark` 保持原???，且没有新??? `operation_logs` 代理确认日志???

### 剩余边界
- 本轮没有改动代理列表、代理详情???下级列表???等级更新???佣金更新???确认代理???前端页面或数据库结构???
- 后续继续按旧项目模块清单审计代理商模块???后台普通用户模块和后台管理员模块其它剩余入口???
## 276. 2026-07-09 后台代理商详情目??? ID 严格校验闭环

### 本次处理目标
- 涓? `AgentController::show` 补齐代理详情读取 `agent_id` 严格校验测试???
- 验证 `/api/admin/agentDetail` 不能??? `agent_id=真实IDabc` 交给数据库按前缀数字匹配真实代理???
- 验证非法 `agent_id` 返回 `ResponseCode::VALIDATION_FAILED`，且不返回目标代理资料???

### 本次变更文件
- `tests/Feature/AdminAgentDetailAgentIdValidationClosureModuleTest.php`
  - 新增非严??? `agent_id` 被拒绝且响应不包含代理资料的样例???
- `app/Http/Controllers/Admin/AgentController.php`
  - 鍦? `show` 查询 `user_infos.user_id` 前先校验 `agent_id`，校验???过后再转换目标 ID 并读取详情???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAgentDetailAgentIdValidationClosureModuleTest.php` 首次运行失败，命??? `agent_id=真实IDabc` 仍返回成功码并可读取真实代理详情，最终清单也缺少??? 276 节???
- GREEN锛氳ˉ榻? `show` 前置参数校验和第 276 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminAgentDetailAgentIdValidationClosureModuleTest` 覆盖真实 `admins`銆乣user_logins` 涓? `user_infos` 表记录，后台 admin guard 登录态和 `/api/admin/agentDetail` 详情入口???
- 非严??? `agent_id=真实IDabc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 响应内容不包含目标代理名称，避免详情接口在参数校验前被数据库数字前缀规则命中真实代理???

### 剩余边界
- 本轮没有改动代理列表、下级列表???等级更新???佣金更新??佺‘璁?/拒绝代理、前端页面或数据库结构???
- 后续继续按旧项目模块清单审计代理商模块???后台普通用户模块和后台管理员模块其它剩余入口???
## 271. 2026-07-09 后台代理商等级更新存在???校验闭???

### 本次处理目标
- 涓? `AgentController::updateLevel` 补齐代理等级存在性校验测试???
- 验证 `/api/admin/updateAgentLevel` 只允许把代理更新到真实存在的 `agent_levels.id`銆?
- 验证不存在的等级 ID 返回 `ResponseCode::VALIDATION_FAILED`，且不改写目标代理的 `user_infos.level_id`銆?

### 本次变更文件
- `tests/Feature/AdminAgentLevelExistsValidationClosureModuleTest.php`
  - 新增不存在代理等级被拒绝且不落库的样例???
- `app/Http/Controllers/Admin/AgentController.php`
  - 鍦? `updateLevel` 鐨? `level` 参数校验中增??? `exists:agent_levels,id`銆?

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAgentLevelExistsValidationClosureModuleTest.php` 首次运行失败，命中不存在??? `level` 仍返回成功码且最终清单缺少第 271 节???
- GREEN锛氳ˉ榻? `AgentController::updateLevel` 等级存在性校验和??? 271 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminAgentLevelExistsValidationClosureModuleTest` 覆盖真实 `admins`銆乣agent_levels`銆乣user_logins` 涓? `user_infos` 表记录，后台 admin guard 登录态和 `/api/admin/updateAgentLevel` 更新入口???
- 不存在的 `agent_levels.id` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 鍘? `user_infos.level_id` 保持原???，避免代理等级被写成不存在的配??? ID銆?

### 剩余边界
- 本轮没有改动代理列表、代理详情???下级列表???佣金更新??佺‘璁?/拒绝代理、前端页面或数据库结构???
- 后续继续按旧项目模块清单审计代理商模块???后台普通用户模块和后台管理员模块其它剩余入口???
## 270. 2026-07-09 后台普???用户实名认证审核状态严格校验闭???

### 本次处理目标
- 涓? `AdminUserController::reviewAuth` 补齐实名认证审核状???参数严格校验测试???
- 验证 `/api/admin/reviewAuth` 只接??? `status=1/2`，不能把 `status=1abc` 通过 PHP 强转当作审核通过???
- 验证非法审核状??佽繑鍥? `ResponseCode::VALIDATION_FAILED`，且不改??? `user_auths`銆乣user_infos.auth_status` 或写入操作日志???

### 本次变更文件
- `tests/Feature/AdminReviewAuthStatusValidationClosureModuleTest.php`
  - 新增非严??? `status=1abc` 被拒绝且不落库的样例???
- `app/Http/Controllers/Admin/AdminUserController.php`
  - 鍦? `reviewAuth` 寮?头增??? `Validator` 参数校验，先校验 `user_id` 涓? `status=1/2`，再强转并执行业务写入???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminReviewAuthStatusValidationClosureModuleTest.php` 首次运行失败，命??? `status=1abc` 被强转为 `1` 并返回成功码，最终清单也缺少??? 270 节???
- GREEN锛氳ˉ榻? `reviewAuth` 严格参数校验和第 270 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminReviewAuthStatusValidationClosureModuleTest` 覆盖真实 `admins`銆乣user_logins`銆乣user_infos`銆乣user_auths` 涓? `operation_logs` 表记录，后台 admin guard 登录态和 `/api/admin/reviewAuth` 审核入口???
- 非法 `status=1abc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 鍘? `user_auths.id_card_status`銆乣user_auths.bank_status`、备注和 `user_infos.auth_status` 保持原???，且没有新??? `operation_logs` 审核日志???

### 剩余边界
- 本轮没有改动认证待审列表、已审列表???详情页脚本、数据范围服务???前端页面或数据库结构???
- 后续继续按旧项目模块清单审计后台普???用户模块???代理商模块和后台管理员模块其它剩余入口???
## 277. 2026-07-09 后台代理下级列表目标 ID 严格校验闭环

### 本次处理目标
- 涓? `AgentController::descendants` 补齐代理下级列表 `agent_id` 严格校验测试???
- 验证 `/api/admin/agentDescendants` 不能??? `agent_id=真实IDabc` 交给数据库按前缀数字匹配真实代理???
- 验证非法 `agent_id` 返回 `ResponseCode::VALIDATION_FAILED`，且不返回该代理通过 `user_infos.parent_id` 关系展开出的下级资料???

### 本次变更文件
- `tests/Feature/AdminAgentDescendantsAgentIdValidationClosureModuleTest.php`
  - 新增非严??? `agent_id` 被拒绝且响应不包含下级代理资料的样例???
- `app/Http/Controllers/Admin/AgentController.php`
  - 鍦? `descendants` 查询代理树前先校??? `agent_id`，校验???过后再转换目标 ID 并读取下级列表???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAgentDescendantsAgentIdValidationClosureModuleTest.php` 首次运行失败，命??? `agent_id=真实IDabc` 仍返回成功码并展???真实下级列表，最终清单也缺少??? 277 节???
- GREEN锛氳ˉ榻? `descendants` 前置参数校验和第 277 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminAgentDescendantsAgentIdValidationClosureModuleTest` 覆盖真实 `admins`銆乣user_logins`銆乣user_infos` 鍜? `/api/admin/agentDescendants` 下级列表入口???
- 非严??? `agent_id=真实IDabc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 响应内容不包含???过 `user_infos.parent_id` 直属关系构???出的下级代理名称，避免下级列表接口在参数校验前被数据库数字前缀规则命中真实代理???

### 剩余边界
- 本轮没有改动代理列表、代理详情???等级更新???佣金更新??佺‘璁?/拒绝代理、前端页面或数据库结构???
- 后续继续按旧项目模块清单审计代理商模块???后台普通用户模块和后台管理员模块其它剩余入口???
## 278. 2026-07-09 后台代理统计列表 user_id 严格校验闭环

### 本次处理目标
- 涓? `AgentController::listWithStats` 补齐代理统计列表 `user_id` 严格校验测试???
- 验证 `/api/admin/agentStatsList` 表单筛???不能把 `user_id=真实IDabc` 交给数据库按前缀数字匹配真实代理???
- 验证非法 `user_id` 返回 `ResponseCode::VALIDATION_FAILED`，且不返??? `user_infos.user_id` 命中的真实代理资料???

### 本次变更文件
- `tests/Feature/AdminAgentStatsUserIdValidationClosureModuleTest.php`
  - 新增非严??? `user_id` 被拒绝且响应不包含代理统计行的样例???
- `app/Http/Controllers/Admin/AgentController.php`
  - 鍦? `listWithStats` 构??? `user_infos.user_id` 查询前先校验 `user_id`，校验???过后再转换为整数并应用筛??夈??

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAgentStatsUserIdValidationClosureModuleTest.php` 首次运行失败，命??? `user_id=真实IDabc` 仍返回成功码并展示真实代理统计行，最终清单也缺少??? 278 节???
- GREEN锛氳ˉ榻? `listWithStats` 前置参数校验和第 278 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminAgentStatsUserIdValidationClosureModuleTest` 覆盖真实 `admins`銆乣user_logins`銆乣user_infos` 鍜? `/api/admin/agentStatsList` 统计列表入口???
- 非严??? `user_id=真实IDabc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 响应内容不包含目标代理名称，避免统计列表在参数校验前被数据库数字前缀规则命中真实代理???

### 剩余边界
- 本轮没有改动代理普???列表???导出???详情???下级列表???等级更新???佣金更新??佺‘璁?/拒绝代理、前端页面或数据库结构???
- 后续继续按旧项目模块清单审计代理商模块???后台普通用户模块和后台管理员模块其它剩余入口???
## 279. 2026-07-09 后台代理列表与导??? agent_id 严格校验闭环

### 本次处理目标
- 涓? `AgentController::filteredAgentQuery` 补齐代理普???列表和导出共用 `agent_id` 严格校验测试???
- 验证 `/api/admin/agents` 不能??? `agent_id=真实IDabc` 交给数据库按前缀数字匹配真实代理???
- 验证 `/api/admin/exportAgents` 收到非法 `agent_id` 时返??? `ResponseCode::VALIDATION_FAILED`，不能继续输出包含真实代理行??? CSV銆?

### 本次变更文件
- `tests/Feature/AdminAgentListExportAgentIdValidationClosureModuleTest.php`
  - 新增列表非严??? `agent_id` 被拒绝且响应不包含代理资料的样例???
  - 新增导出非严??? `agent_id` 被拒绝且不进??? CSV 流响应的样例???
- `app/Http/Controllers/Admin/AgentController.php`
  - 新增代理列表和导出共用的 `agent_id` 筛???参数校验???
  - `filteredAgentQuery` 在应??? `user_infos.user_id` 筛???时使用校验后的整数值???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAgentListExportAgentIdValidationClosureModuleTest.php` 首次运行失败，命中列表仍返回成功码???导出仍进入 CSV 流响应，???终清单也缺少??? 279 节???
- GREEN：补齐共??? `agent_id` 校验、整数化筛??夊拰绗? 279 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminAgentListExportAgentIdValidationClosureModuleTest` 覆盖真实 `admins`銆乣user_logins`銆乣user_infos`銆乣/api/admin/agents` 鍜? `/api/admin/exportAgents` 两个读取入口???
- 非严??? `agent_id=真实IDabc` 在列表和导出入口均返??? `ResponseCode::VALIDATION_FAILED`銆?
- 响应内容不包含目标代理名称，避免 `user_infos.user_id` 筛???在参数校验前被数据库数字前???规则命中真实代理???

### 剩余边界
- 本轮没有改动代理详情、下级列表???统计列表???等级更新???佣金更新??佺‘璁?/拒绝代理、前端页面或数据库结构???
- 后续继续按旧项目模块清单审计代理商模块???后台普通用户模块和后台管理员模块其它剩余入口???
## 280. 2026-07-09 后台普???用户详??? user_id 严格校验闭环

### 本次处理目标
- 涓? `AdminUserController::userDetail` 补齐旧后台用户详情入??? `user_id` 严格校验测试???
- 验证 `/api/admin/userDetail` 不能??? `user_id=真实IDabc` 交给数据库按前缀数字匹配真实用户???
- 验证非法 `user_id` 返回 `ResponseCode::VALIDATION_FAILED`，且不返??? `user_infos.user_id` 命中的用户资料???

### 本次变更文件
- `tests/Feature/AdminUserDetailUserIdValidationClosureModuleTest.php`
  - 新增非严??? `user_id` 被拒绝且响应不包含用户资料的样例???
- `app/Http/Controllers/Admin/AdminUserController.php`
  - 鍦? `userDetail` 查询 `user_infos.user_id` 前先校验 `user_id`，校验???过后再转换为整数并读取详情???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminUserDetailUserIdValidationClosureModuleTest.php` 首次运行失败，命??? `user_id=真实IDabc` 仍返回成功码并可读取真实用户详情，最终清单也缺少??? 280 节???
- GREEN锛氳ˉ榻? `userDetail` 前置参数校验和第 280 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminUserDetailUserIdValidationClosureModuleTest` 覆盖真实 `admins`銆乣user_logins`銆乣user_infos` 鍜? `/api/admin/userDetail` 旧详情入口???
- 非严??? `user_id=真实IDabc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 响应内容不包含目标用户名称，避免详情接口在参数校验前被数据库数字前缀规则命中真实用户???

### 剩余边界
- 本轮没有改动用户列表、导出???资料更新???登录启停???实名认证审核???数据范围服务???前端页面或数据库结构???
- 后续继续按旧项目模块清单审计后台普???用户模块???代理商模块和后台管理员模块其它剩余入口???
## 281. 2026-07-09 后台普???用户资料更??? user_id 严格校验闭环

### 本次处理目标
- 涓? `AdminUserController::updateUser` 补齐旧后台用户资料更新入??? `user_id` 严格校验测试???
- 验证 `/api/admin/updateUser` 不能??? `user_id=真实IDabc` 交给数据库按前缀数字匹配真实用户???
- 验证非法 `user_id` 返回 `ResponseCode::VALIDATION_FAILED`，且不改??? `user_infos.user_id` 命中的用户基???资料???

### 本次变更文件
- `tests/Feature/AdminUserUpdateUserIdValidationClosureModuleTest.php`
  - 新增非严??? `user_id` 被拒绝且不改??? `user_name`銆乣phone` 的样例???
- `app/Http/Controllers/Admin/AdminUserController.php`
  - 鍦? `updateUser` 查询 `user_infos.user_id` 前先校验 `user_id`，校验???过后再转换为整数并执行资料更新???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminUserUpdateUserIdValidationClosureModuleTest.php` 首次运行失败，命??? `user_id=真实IDabc` 仍返回更新成功码，最终清单也缺少??? 281 节???
- GREEN锛氳ˉ榻? `updateUser` 前置参数校验和第 281 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminUserUpdateUserIdValidationClosureModuleTest` 覆盖真实 `admins`銆乣user_logins`銆乣user_infos` 鍜? `/api/admin/updateUser` 旧资料保存入口???
- 非严??? `user_id=真实IDabc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 鍘? `user_infos.user_name` 涓? `user_infos.phone` 保持原???，避免资料更新接口在参数校验前被数据库数字前缀规则命中真实用户???

### 剩余边界
- 本轮没有改动用户列表、导出???详情读取???登录启停???实名认证审核???数据范围服务???前端页面或数据库结构???
- 后续继续按旧项目模块清单审计后台普???用户模块???代理商模块和后台管理员模块其它剩余入口???
## 282. 2026-07-09 后台普???用户登录启??? user_id 严格校验闭环

### 本次处理目标
- 涓? `AdminUserController::changeUserStatus` 补齐旧后台用户登录启停入??? `user_id` 严格校验测试???
- 验证 `/api/admin/changeUserStatus` 不能??? `user_id=真实IDabc` 交给数据库按前缀数字匹配真实用户???
- 验证非法 `user_id` 返回 `ResponseCode::VALIDATION_FAILED`，且不改??? `user_logins.user_id` 命中的登录启停状态???

### 本次变更文件
- `tests/Feature/AdminUserStatusUserIdValidationClosureModuleTest.php`
  - 新增非严??? `user_id` 被拒绝且不改??? `user_logins.is_enabled` 的样例???
- `app/Http/Controllers/Admin/AdminUserController.php`
  - 鍦? `changeUserStatus` 查询 `user_logins.user_id` 前先校验 `user_id` 涓? `is_enabled`，校验???过后再转换为整数并执行启停写入???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminUserStatusUserIdValidationClosureModuleTest.php` 首次运行失败，命??? `user_id=真实IDabc` 仍返回成功码，最终清单也缺少??? 282 节???
- GREEN锛氳ˉ榻? `changeUserStatus` 前置参数校验和第 282 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminUserStatusUserIdValidationClosureModuleTest` 覆盖真实 `admins`銆乣user_logins`銆乣user_infos` 鍜? `/api/admin/changeUserStatus` 旧登录启停入口???
- 非严??? `user_id=真实IDabc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 鍘? `user_logins.is_enabled` 保持原???，避免登录启停接口在参数校验前被数据库数字前缀规则命中真实用户???

### 剩余边界
- 本轮没有改动用户列表、导出???详情读取???资料更新???实名认证审核???数据范围服务???前端页面或数据库结构???
- 后续继续按旧项目模块清单审计后台普???用户模块???代理商模块和后台管理员模块其它剩余入口???
## 283. 2026-07-09 后台普???用户列表与导出 user_id 严格校验闭环

### 本次处理目标
- 涓? `AdminUserController::filteredUserQuery` 补齐用户列表和导出共??? `user_id` 严格校验测试???
- 验证 `/api/admin/userList` 不能??? `user_id=真实IDabc` 交给数据库按前缀数字匹配真实用户???
- 验证 `/api/admin/exportUsers` 收到非法 `user_id` 时返??? `ResponseCode::VALIDATION_FAILED`，不能进入包含真实用户行??? CSV 流响应???

### 本次变更文件
- `tests/Feature/AdminUserListExportUserIdValidationClosureModuleTest.php`
  - 新增列表非严??? `user_id` 被拒绝且响应不包含用户资料的样例???
  - 新增导出非严??? `user_id` 被拒绝且不进??? CSV 流响应的样例???
- `app/Http/Controllers/Admin/AdminUserController.php`
  - 新增用户列表和导出共用的 `user_id` 筛???参数校验???
  - `filteredUserQuery` 在应??? `user_infos.user_id` 筛???时使用校验后的整数值???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminUserListExportUserIdValidationClosureModuleTest.php` 首次运行失败，命中列表仍返回成功码???导出仍进入 CSV 流响应，???终清单也缺少??? 283 节???
- GREEN：补齐共??? `user_id` 校验、整数化筛??夊拰绗? 283 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminUserListExportUserIdValidationClosureModuleTest` 覆盖真实 `admins`銆乣user_logins`銆乣user_infos`銆乣/api/admin/userList` 鍜? `/api/admin/exportUsers` 两个读取入口???
- 非严??? `user_id=真实IDabc` 在列表和导出入口均返??? `ResponseCode::VALIDATION_FAILED`銆?
- 响应内容不包含目标用户名称，避免 `user_infos.user_id` 筛???在参数校验前被数据库数字前???规则命中真实用户???

### 剩余边界
- 本轮没有改动用户详情、资料更新???登录启停???实名认证审核???数据范围服务???前端页面或数据库结构???
- 后续继续按旧项目模块清单审计后台普???用户模块???代理商模块和后台管理员模块其它剩余入口???
## 284. 2026-07-09 后台管理员账号路??? ID 严格校验闭环

### 本次处理目标
- 涓? `AdminController::update`銆乣AdminController::resetPassword` 鍜? `AdminController::destroy` 补齐旧路??? `{id}` 严格校验测试???
- 验证 `/api/admin/updateAdmin/{id}` 不能??? `id=真实IDabc` 交给数据库按前缀数字匹配真实后台管理员???
- 验证 `/api/admin/resetAdminPassword/{id}` 鍜? `/api/admin/deleteAdmin/{id}` 收到非严??? ID 时返??? `ResponseCode::VALIDATION_FAILED`，不改写密码也不删除账号???

### 本次变更文件
- `tests/Feature/AdminAccountRouteIdValidationClosureModuleTest.php`
  - 新增编辑、重置密码???删除三个旧路由非严??? `{id}` 被拒绝且不落库的样例???
- `app/Http/Controllers/Admin/AdminController.php`
  - 新增后台管理员账号路??? ID 共用校验???
  - `update`銆乣resetPassword`銆乣destroy` 在查??? `admins.id` 前先校验路由参数，???过后再转换为整数???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAccountRouteIdValidationClosureModuleTest.php` 首次运行失败，命中编辑返??? `ResponseCode::UPDATED`、重置密码返回成功???删除返??? `ResponseCode::DELETED`，最终清单也缺少??? 284 节???
- GREEN：补齐共用路??? ID 校验、整数化查询和第 284 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminAccountRouteIdValidationClosureModuleTest` 覆盖真实 `admins` 表记录??佸悗鍙? admin guard 登录态??乣/api/admin/updateAdmin/{id}`銆乣/api/admin/resetAdminPassword/{id}` 鍜? `/api/admin/deleteAdmin/{id}` 三个入口???
- 非严格路??? ID `真实IDabc` 均返??? `ResponseCode::VALIDATION_FAILED`銆?
- 原账号用户名、邮箱???手机号、状态???密码和 `deleted_at` 保持原???，避免 `admins.id` 在参数校验前被数据库数字前缀规则命中真实后台管理员???

### 剩余边界
- 本轮没有改动管理员列表???新增管理员、角???/权限表???前端页面或数据库结构???
- 后续继续按旧项目模块清单审计后台管理员模块???后台普通用户模块和代理商模块其它剩余入口???
## 285. 2026-07-09 后台大代理路??? ID 与旧 status 严格校验闭环

### 本次处理目标
- 涓? `BigAgentController::store`銆乣BigAgentController::update` 鍜? `BigAgentController::destroy` 补齐大代??? ID 与启停字段严格校验测试???
- 验证 `/api/admin/updateBigAgent/{id}` 鍜? `/api/admin/deleteBigAgent/{id}` 不能??? `id=真实IDabc` 交给数据库按前缀数字匹配真实大代理???
- 验证 `/api/admin/createBigAgent` 涓? `/api/admin/updateBigAgent/{id}` 收到旧兼容字??? `status=1abc` 时返??? `ResponseCode::VALIDATION_FAILED`，不写入或改??? `big_agents.is_enabled`銆?

### 本次变更文件
- `tests/Feature/AdminBigAgentIdStatusValidationClosureModuleTest.php`
  - 新增创建、编辑时非法??? `status` 被拒绝且不落库的样例???
  - 新增编辑、删除时非严格路??? `{id}` 被拒绝且不落库的样例???
- `app/Http/Controllers/Admin/BigAgentController.php`
  - 新增大代理路??? ID 共用校验???
  - `update`銆乣destroy` 在查??? `big_agents.id` 前先校验路由参数，???过后再转换为整数???
  - `store`銆乣update` 对旧兼容 `status` 字段执行 boolean 校验，再交给 `normalizePayload` 映射??? `big_agents.is_enabled`銆?

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminBigAgentIdStatusValidationClosureModuleTest.php` 首次运行失败，命中创建进入服务端错误、编辑返??? `ResponseCode::UPDATED`、删除返??? `ResponseCode::DELETED`，最终清单也缺少??? 285 节???
- GREEN：补齐路??? ID 校验、旧 `status` boolean 校验和第 285 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminBigAgentIdStatusValidationClosureModuleTest` 覆盖真实 `admins` 涓? `big_agents` 表记录??佸悗鍙? admin guard 登录态??乣/api/admin/createBigAgent`銆乣/api/admin/updateBigAgent/{id}` 鍜? `/api/admin/deleteBigAgent/{id}` 三个入口???
- 非严格路??? ID `真实IDabc` 与非法旧字段 `status=1abc` 均返??? `ResponseCode::VALIDATION_FAILED`銆?
- 原大代理用户名??乣big_agents.is_enabled` 鍜? `deleted_at` 保持原???，避免 ID 或启停状态在参数校验前被错误落库???

### 剩余边界
- 本轮没有改动大代理列表???前端页面???前台大代理登录、下级代理范围或数据库结构???
- 后续继续按旧项目模块清单审计后台管理员模块???后台普通用户模块和代理商模块其它剩余入口???
## 286. 2026-07-09 后台代理等级配置路由 ID 严格校验闭环

### 本次处理目标
- 涓? `AgentLevelController::update` 鍜? `AgentLevelController::destroy` 补齐代理等级配置路由 `{id}` 严格校验测试???
- 验证 `/api/admin/updateAgentLevel2/{id}` 不能??? `id=真实IDabc` 交给数据库按前缀数字匹配真实代理等级???
- 验证 `/api/admin/deleteAgentLevel/{id}` 收到非严??? ID 时返??? `ResponseCode::VALIDATION_FAILED`，不删除 `agent_levels.id` 命中的记录???

### 本次变更文件
- `tests/Feature/AdminAgentLevelRouteIdValidationClosureModuleTest.php`
  - 新增编辑、删除两个旧路由非严??? `{id}` 被拒绝且不落库的样例???
- `app/Http/Controllers/Admin/AgentLevelController.php`
  - 新增代理等级路由 ID 共用校验???
  - `update`銆乣destroy` 在查??? `agent_levels.id` 前先校验路由参数，???过后再转换为整数???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAgentLevelRouteIdValidationClosureModuleTest.php` 首次运行失败，命中编辑返??? `ResponseCode::UPDATED`、删除返??? `ResponseCode::DELETED`，最终清单也缺少??? 286 节???
- GREEN：补齐共用路??? ID 校验、整数化查询和第 286 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminAgentLevelRouteIdValidationClosureModuleTest` 覆盖真实 `admins` 涓? `agent_levels` 表记录??佸悗鍙? admin guard 登录态??乣/api/admin/updateAgentLevel2/{id}` 鍜? `/api/admin/deleteAgentLevel/{id}` 两个入口???
- 非严格路??? ID `真实IDabc` 均返??? `ResponseCode::VALIDATION_FAILED`銆?
- 原代理等级编码???名称???返佣字段和 `deleted_at` 保持原???，避免 `agent_levels.id` 在参数校验前被数据库数字前缀规则命中真实代理等级???

### 剩余边界
- 本轮没有改动代理等级列表、新增代理等级???前端页面???代理账号等级更新入口或数据库结构???
- 后续继续按旧项目模块清单审计代理商模块???后台管理员模块和后台普通用户模块其它剩余入口???
## 287. 2026-07-09 后台代理等级返佣数???严格校验闭???

### 本次处理目标
- 涓? `AgentLevelController::normalizePayload` 补齐代理等级返佣数???字段严格校验测试???
- 验证 `/api/admin/createAgentLevel` 收到 `max_commission=50abc` 时返??? `ResponseCode::VALIDATION_FAILED`，不创建代理等级???
- 验证 `/api/admin/updateAgentLevel2/{id}` 收到旧兼容字??? `commission_rate=30abc` 时返??? `ResponseCode::VALIDATION_FAILED`，不改写 `agent_levels.user_commission`銆?

### 本次变更文件
- `tests/Feature/AdminAgentLevelCommissionValidationClosureModuleTest.php`
  - 新增创建时非??? `max_commission` 被拒绝且不落库的样例???
  - 新增编辑时非法旧 `commission_rate` 被拒绝且不改写返佣字段的样例???
- `app/Http/Controllers/Admin/AgentLevelController.php`
  - `normalizePayload` 保留原始请求值交??? Validator 校验，避免校验前强转吞掉非法后缀???
  - 新增 `castAgentLevelPayload`，仅在校验???过后把 `level_code`銆乣max_commission`銆乣min_commission`銆乣user_commission` 转为整数写入???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminAgentLevelCommissionValidationClosureModuleTest.php` 首次运行失败，命中创建返??? `ResponseCode::CREATED`、编辑返??? `ResponseCode::UPDATED`，最终清单也缺少??? 287 节???
- GREEN：调整校验前后数值处理顺序并补齐??? 287 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminAgentLevelCommissionValidationClosureModuleTest` 覆盖真实 `admins` 涓? `agent_levels` 表记录??佸悗鍙? admin guard 登录态??乣/api/admin/createAgentLevel` 鍜? `/api/admin/updateAgentLevel2/{id}` 两个入口???
- 非严格数值字符串 `50abc` 涓? `30abc` 均返??? `ResponseCode::VALIDATION_FAILED`銆?
- 原代理等级名称???最大返佣???最小返佣和 `agent_levels.user_commission` 保持原???，避免返佣配置在参数校验前??? PHP 强转悄悄落库???

### 剩余边界
- 本轮没有改动代理等级路由 ID 校验、列表???删除???前端页面???代理账号等级更新入口或数据库结构???
- 后续继续按旧项目模块清单审计代理商模块???后台管理员模块和后台普通用户模块其它剩余入口???
## 288. 2026-07-09 后台组别配置路由 ID 严格校验闭环

### 本次处理目标
- 涓? `GroupConfigController::update` 鍜? `GroupConfigController::destroy` 补齐组别配置路由 `{id}` 严格校验测试???
- 验证 `/api/admin/updateGroupConfig/{id}` 不能??? `id=真实IDabc` 交给数据库按前缀数字匹配真实组别配置???
- 验证 `/api/admin/deleteGroupConfig/{id}` 收到非严??? ID 时返??? `ResponseCode::VALIDATION_FAILED`，不删除 `group_configs.id` 命中的记录???

### 本次变更文件
- `tests/Feature/AdminGroupConfigRouteIdValidationClosureModuleTest.php`
  - 新增编辑、删除两个旧路由非严??? `{id}` 被拒绝且不落库的样例???
- `app/Http/Controllers/Admin/GroupConfigController.php`
  - 新增组别配置路由 ID 共用校验???
  - `update`銆乣destroy` 在查??? `group_configs.id` 前先校验路由参数，???过后再转换为整数???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminGroupConfigRouteIdValidationClosureModuleTest.php` 首次运行失败，命中编辑返??? `ResponseCode::UPDATED`、删除返??? `ResponseCode::DELETED`，最终清单也缺少??? 288 节???
- GREEN：补齐共用路??? ID 校验、整数化查询和第 288 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminGroupConfigRouteIdValidationClosureModuleTest` 覆盖真实 `admins` 涓? `group_configs` 表记录??佸悗鍙? admin guard 登录态??乣/api/admin/updateGroupConfig/{id}` 鍜? `/api/admin/deleteGroupConfig/{id}` 两个入口???
- 非严格路??? ID `真实IDabc` 均返??? `ResponseCode::VALIDATION_FAILED`銆?
- 原组别名称???基数???分类???开关字段和 `deleted_at` 保持原???，避免 `group_configs.id` 在参数校验前被数据库数字前缀规则命中真实组别配置???

### 剩余边界
- 本轮没有改动组别配置列表、新增组别???前端页面???客户转组业务或数据库结构???
- 后续继续按旧项目模块清单审计代理商模块???后台管理员模块和后台普通用户模块其它剩余入口???

## 289. 2026-07-09 后台组别配置数???字段严格校验闭???

### 本次处理目标
- 涓? `GroupConfigController::normalizePayload` 补齐组别配置数???字段严格校验测试???
- 验证 `/api/admin/createGroupConfig` 收到 `radix=50abc` 时返??? `ResponseCode::VALIDATION_FAILED`，不创建 `group_configs`銆?
- 验证 `/api/admin/updateGroupConfig/{id}` 收到 `category=1abc` 时返??? `ResponseCode::VALIDATION_FAILED`，不改写原组别配置???

### 本次变更文件
- `tests/Feature/AdminGroupConfigValueValidationClosureModuleTest.php`
  - 新增创建时非??? `radix` 被拒绝且不写??? `group_configs.radix` 的样例???
  - 新增编辑时非??? `category` 被拒绝且不改写组别字段的样例???
- `app/Http/Controllers/Admin/GroupConfigController.php`
  - `normalizePayload` 保留 `radix` 涓? `category` 原始请求值交??? Validator 校验???
  - 新增校验通过后的写库强转，避免校验前 PHP 强转吞掉非法后缀???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminGroupConfigValueValidationClosureModuleTest.php` 首次运行失败，创建命中成功码，编辑命中服务端错误，最终清单也缺少??? 289 节???
- GREEN：调整校验前后数值处理顺序并补齐??? 289 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminGroupConfigValueValidationClosureModuleTest` 覆盖真实 `admins` 涓? `group_configs` 记录、后??? admin guard 登录态??乣/api/admin/createGroupConfig` 鍜? `/api/admin/updateGroupConfig/{id}` 两个入口???
- 非严格数值字符串 `50abc` 涓? `1abc` 均返??? `ResponseCode::VALIDATION_FAILED`銆?
- 原组别名称??乣group_configs.radix`銆乣category` 和开关字段保持原值，避免组别配置在参数校验前??? PHP 强转悄悄落库???

### 剩余边界
- 本轮没有改动组别配置路由 ID 校验、列表???删除???前端页面???客户转组业务或数据库结构???
- 后续继续按旧项目模块清单审计代理商模块???后台管理员模块和后台普通用户模块其它剩余入口???

## 290. 2026-07-09 后台新闻公告路由 ID 严格校验闭环

### 本次处理目标
- 涓? `NewsController::update`銆乣NewsController::destroy` 鍜? `NewsController::togglePublish` 补齐新闻公告路由 `{id}` 严格校验测试???
- 验证 `/api/admin/updateNews/{id}` 不能??? `id=真实IDabc` 交给数据库按前缀数字匹配真实新闻公告???
- 验证 `/api/admin/deleteNews/{id}` 鍜? `/api/admin/toggleNews/{id}` 收到非严??? ID 时返??? `ResponseCode::VALIDATION_FAILED`，不删除公告也不切换发布状??併??

### 本次变更文件
- `tests/Feature/AdminNewsRouteIdValidationClosureModuleTest.php`
  - 新增编辑、删除???发布切换三个旧路由非严??? `{id}` 被拒绝且不落库的样例???
- `app/Http/Controllers/Admin/NewsController.php`
  - 新增新闻公告路由 ID 共用校验???
  - `update`銆乣destroy`銆乣togglePublish` 在查??? `news.id` 前先校验路由参数，???过后再转换为整数???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminNewsRouteIdValidationClosureModuleTest.php` 首次运行失败，命中编辑返??? `ResponseCode::UPDATED`、删除返??? `ResponseCode::DELETED`、发布切换返回成功，???终清单也缺少??? 290 节???
- GREEN：补齐共用路??? ID 校验、整数化查询和第 290 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminNewsRouteIdValidationClosureModuleTest` 覆盖真实 `admins` 涓? `news` 表记录??佸悗鍙? admin guard 登录态??乣/api/admin/updateNews/{id}`銆乣/api/admin/deleteNews/{id}` 鍜? `/api/admin/toggleNews/{id}` 三个入口???
- 非严格路??? ID `真实IDabc` 均返??? `ResponseCode::VALIDATION_FAILED`銆?
- 原新闻标题???正文???图片???作者??乣is_published` 鍜? `deleted_at` 保持原???，避免 `news.id` 在参数校验前被数据库数字前缀规则命中真实新闻公告???

### 剩余边界
- 本轮没有改动新闻公告列表、新增公告???前端页面???前台新闻读取或数据库结构???
- 后续继续按旧项目模块清单审计后台内容模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 291. 2026-07-09 后台支付通道路由 ID 严格校验闭环

### 本次处理目标
- 涓? `PaymentChannelController::update`銆乣PaymentChannelController::destroy` 鍜? `PaymentChannelController::toggleEnable` 补齐支付通道路由 `{id}` 严格校验测试???
- 验证 `/api/admin/updateChannel/{id}` 不能??? `id=真实IDabc` 交给数据库按前缀数字匹配真实支付通道???
- 验证 `/api/admin/deleteChannel/{id}` 鍜? `/api/admin/toggleChannel/{id}` 收到非严??? ID 时返??? `ResponseCode::VALIDATION_FAILED`，不删除通道也不切换启用状??併??

### 本次变更文件
- `tests/Feature/AdminPaymentChannelRouteIdValidationClosureModuleTest.php`
  - 新增编辑、删除???启停三个旧路由非严??? `{id}` 被拒绝且不落库的样例???
- `app/Http/Controllers/Admin/PaymentChannelController.php`
  - 新增支付通道路由 ID 共用校验???
  - `update`銆乣destroy`銆乣toggleEnable` 在查??? `payment_channels.id` 前先校验路由参数，???过后再转换为整数???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminPaymentChannelRouteIdValidationClosureModuleTest.php` 首次运行失败，命中编辑返??? `ResponseCode::UPDATED`、删除返??? `ResponseCode::DELETED`、启停返回成功，???终清单也缺少??? 291 节???
- GREEN：补齐共用路??? ID 校验、整数化查询和第 291 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminPaymentChannelRouteIdValidationClosureModuleTest` 覆盖真实 `admins` 涓? `payment_channels` 表记录??佸悗鍙? admin guard 登录态??乣/api/admin/updateChannel/{id}`銆乣/api/admin/deleteChannel/{id}` 鍜? `/api/admin/toggleChannel/{id}` 三个入口???
- 非严格路??? ID `真实IDabc` 均返??? `ResponseCode::VALIDATION_FAILED`銆?
- 原???道名称、编码???汇率??乣is_enabled`、排序???配置和 `deleted_at` 保持原???，避免 `payment_channels.id` 在参数校验前被数据库数字前缀规则命中真实支付通道???

### 剩余边界
- 本轮没有改动支付通道列表、新增???道、前端页面???前台入金???道展示或数据库结构???
- 后续继续按旧项目模块清单审计后台内容模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 292. 2026-07-09 后台凭证审核路由 ID 严格校验闭环

### 本次处理目标
- 涓? `VoucherController::approve` 鍜? `VoucherController::reject` 补齐凭证审核路由 `{id}` 严格校验测试???
- 验证 `/api/admin/voucherApprove/{id}` 不能??? `id=真实IDabc` 交给数据库按前缀数字匹配真实凭证???
- 验证 `/api/admin/voucherReject/{id}` 收到非严??? ID 时返??? `ResponseCode::VALIDATION_FAILED`，不改写凭证审核状???和拒绝原因???

### 本次变更文件
- `tests/Feature/AdminVoucherRouteIdValidationClosureModuleTest.php`
  - 新增审核通过、审核拒绝两个旧路由非严??? `{id}` 被拒绝且不落库的样例???
- `app/Http/Controllers/Admin/VoucherController.php`
  - 新增凭证审核路由 ID 共用校验???
  - `approve`銆乣reject` 在查??? `voucher_infos.id` 前先校验路由参数，???过后再转换为整数???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminVoucherRouteIdValidationClosureModuleTest.php` 首次运行失败，命中???过和拒绝接口均返回成功，最终清单也缺少??? 292 节???
- GREEN：补齐共用路??? ID 校验、整数化查询和第 292 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminVoucherRouteIdValidationClosureModuleTest` 覆盖真实 `admins` 涓? `voucher_infos` 表记录??佸悗鍙? admin guard 登录态??乣/api/admin/voucherApprove/{id}` 鍜? `/api/admin/voucherReject/{id}` 两个入口???
- 非严格路??? ID `真实IDabc` 均返??? `ResponseCode::VALIDATION_FAILED`銆?
- 鍘? `voucher_infos.review_status`銆乣review_message`、备注和 `deleted_at` 保持原???，避免 `voucher_infos.id` 在参数校验前被数据库数字前缀规则命中真实凭证???

### 剩余边界
- 本轮没有改动凭证列表、前端页面???前台凭证上传或数据库结构???
- 后续继续按旧项目模块清单审计后台内容模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 293. 2026-07-09 后台黑名单路??? ID 严格校验闭环

### 本次处理目标
- 涓? `BlacklistController::update` 鍜? `BlacklistController::destroy` 补齐黑名单路??? `{id}` 严格校验测试???
- 验证 `/api/admin/updateBlacklist/{id}` 不能??? `id=真实IDabc` 交给数据库按前缀数字匹配真实黑名单记录???
- 验证 `/api/admin/deleteBlacklist/{id}` 收到非严??? ID 时返??? `ResponseCode::VALIDATION_FAILED`，不删除黑名单记录???

### 本次变更文件
- `tests/Feature/AdminBlacklistRouteIdValidationClosureModuleTest.php`
  - 新增编辑、删除两个旧路由非严??? `{id}` 被拒绝且不落库的样例???
- `app/Http/Controllers/Admin/BlacklistController.php`
  - 新增黑名单路??? ID 共用校验???
  - `update`銆乣destroy` 在查??? `blacklists.id` 前先校验路由参数，???过后再转换为整数???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminBlacklistRouteIdValidationClosureModuleTest.php` 首次运行失败，命中编辑返??? `ResponseCode::UPDATED`、删除返??? `ResponseCode::DELETED`，最终清单也缺少??? 293 节???
- GREEN：补齐共用路??? ID 校验、整数化查询和第 293 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminBlacklistRouteIdValidationClosureModuleTest` 覆盖真实 `admins` 涓? `blacklists` 表记录??佸悗鍙? admin guard 登录态??乣/api/admin/updateBlacklist/{id}` 鍜? `/api/admin/deleteBlacklist/{id}` 两个入口???
- 非严格路??? ID `真实IDabc` 均返??? `ResponseCode::VALIDATION_FAILED`銆?
- 原黑名单姓名、证件号、邮箱???手机号??? `deleted_at` 保持原???，避免 `blacklists.id` 在参数校验前被数据库数字前缀规则命中真实黑名单记录???

### 剩余边界
- 本轮没有改动黑名单列表???新增黑名单、前端页面???注册风控引用或数据库结构???
- 后续继续按旧项目模块清单审计后台内容模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 294. 2026-07-09 后台注销申请审核路由 ID 严格校验闭环

### 本次处理目标
- 涓? `CancelApplyController::approve` 鍜? `CancelApplyController::reject` 补齐注销申请审核路由 `{id}` 严格校验测试???
- 验证 `/api/admin/cancelApplyApprove/{id}` 不能??? `id=真实IDabc` 交给数据库按前缀数字匹配真实注销申请???
- 验证 `/api/admin/cancelApplyReject/{id}` 收到非严??? ID 时返??? `ResponseCode::VALIDATION_FAILED`，不改写申请状??併??用户注???状??併??用户软删状态或操作日志???

### 本次变更文件
- `tests/Feature/AdminCancelApplyRouteIdValidationClosureModuleTest.php`
  - 新增审核通过、审核拒绝两个旧路由非严??? `{id}` 被拒绝且不触发副作用的样例???
- `app/Http/Controllers/Admin/CancelApplyController.php`
  - 新增注销申请路由 ID 共用校验???
  - `approve`銆乣reject` 在查??? `cancel_applies.id` 前先校验路由参数，???过后再转换为整数???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminCancelApplyRouteIdValidationClosureModuleTest.php` 首次运行失败，命中???过和拒绝接口均返回成功，最终清单也缺少??? 294 节???
- GREEN：补齐共用路??? ID 校验、整数化查询和第 294 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminCancelApplyRouteIdValidationClosureModuleTest` 覆盖真实 `admins`銆乣cancel_applies`銆乣user_logins`銆乣user_infos` 鍜? `operation_logs` 表记录??佸悗鍙? admin guard 登录态??乣/api/admin/cancelApplyApprove/{id}` 鍜? `/api/admin/cancelApplyReject/{id}` 两个入口???
- 非严格路??? ID `真实IDabc` 均返??? `ResponseCode::VALIDATION_FAILED`銆?
- 原申请状态???拒绝原因??乣user_logins.is_cancelled`、用户资??? `deleted_at` 鍜? `operation_logs` 保持原???，避免 `cancel_applies.id` 在参数校验前被数据库数字前缀规则命中真实注销申请并触发审核副作用???

### 剩余边界
- 本轮没有改动注销申请列表、前端页面???前台注???申请提交或数据库结构???
- 后续继续按旧项目模块清单审计后台内容模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???
## 295. 2026-07-09 后台批量导入重试路由 ID 严格校验闭环

### 本次处理目标
- 涓? `BatchAmountImportController::retryDepositImport`銆乣BatchAmountImportController::retryWithdrawImport` 鍜? `BatchCreditImportController::retryCreditImport` 补齐批量导入失败重试路由 `{id}` 严格校验测试???
- 验证 `/api/admin/retryDepositImport/{id}`銆乣/api/admin/retryWithdrawImport/{id}` 鍜? `/api/admin/retryCreditImport/{id}` 不能??? `id=真实IDabc` 交给数据库按前缀数字匹配真实失败导入记录???
- 验证非严格路??? ID 返回 `ResponseCode::VALIDATION_FAILED`，且不重置导入记录的同步状??併??失败原因或更新人???

### 本次变更文件
- `tests/Feature/AdminBatchImportRetryRouteIdValidationClosureModuleTest.php`
  - 新增入金、出金???信用三类重试入口非严格 `{id}` 被拒绝且不落库的样例???
- `app/Http/Controllers/Admin/BatchAmountImportController.php`
  - `retryDepositImport`銆乣retryWithdrawImport` 在查??? `deposit_imports.id` 涓? `withdraw_imports.id` 前先校验路由参数，???过后再转换为整数???
- `app/Http/Controllers/Admin/BatchCreditImportController.php`
  - `retryCreditImport` 在查??? `credit_imports.id` 前先校验路由参数，???过后再转换为整数???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminBatchImportRetryRouteIdValidationClosureModuleTest.php` 首次运行失败，命中入金???出金???信用三类重试接口均返回成功，最终清单也缺少??? 295 节???
- GREEN：补齐重试入口路??? ID 前置校验和第 295 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminBatchImportRetryRouteIdValidationClosureModuleTest` 覆盖真实 `admins`銆乣user_infos`銆乣deposit_imports`銆乣withdraw_imports` 鍜? `credit_imports` 表记录??佸悗鍙? admin guard 登录态???三个失败重试入口???
- 非严格路??? ID `真实IDabc` 均返??? `ResponseCode::VALIDATION_FAILED`銆?
- 鍘? `is_synced`銆乣fail_reason` 鍜? `updated_by` 保持原???，避免 `deposit_imports.id`銆乣withdraw_imports.id` 鎴? `credit_imports.id` 在参数校验前被数据库数字前缀规则命中真实失败记录并重置为待同步???

### 剩余边界
- 本轮没有改动导入列表、新增导入??丆SV 模板、文件解析??丮T4 同步、前端页面或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、内容模块???代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???
## 296. 2026-07-09 后台风控强平路由 ID 严格校验闭环

### 本次处理目标
- 涓? `RiskController::forceClose` 补齐风控强平路由 `{id}` 严格校验测试???
- 验证 `/api/admin/riskForceClose/{id}` 不能??? `id=真实IDabc` 交给数据库按前缀数字匹配真实 MT4 交易记录???
- 验证非严格路??? ID 返回 `ResponseCode::VALIDATION_FAILED`，且不返回目标订单的强平信号数据???

### 本次变更文件
- `tests/Feature/AdminRiskForceCloseRouteIdValidationClosureModuleTest.php`
  - 新增风控强平入口非严??? `{id}` 被拒绝且不返??? `ticket/login` 信号数据的样例???
- `app/Http/Controllers/Admin/RiskController.php`
  - `forceClose` 在查??? `mt4_trades.id` 前先校验路由参数，???过后再转换为整数???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminRiskForceCloseRouteIdValidationClosureModuleTest.php` 首次运行失败，命中强平接口返回成功码并返回真实订单信号，???终清单也缺少??? 296 节???
- GREEN：补齐风控强平路??? ID 前置校验和第 296 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminRiskForceCloseRouteIdValidationClosureModuleTest` 覆盖真实 `admins` 涓? `mt4_trades` 表记录??佸悗鍙? admin guard 登录态和 `/api/admin/riskForceClose/{id}` 入口???
- 非严格路??? ID `真实IDabc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 响应不包含目标订??? `ticket/login`锛岄伩鍏? `mt4_trades.id` 在参数校验前被数据库数字前缀规则命中真实未平仓订单并返回强平信号???

### 剩余边界
- 本轮没有改动风控持仓列表、追保预警??佸紓甯? IP、前端页面??丮T4 强平执行链路或数据库结构???
- 后续继续按旧项目模块清单审计后台风控模块、资金模块???代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???
## 297. 2026-07-09 后台权益汇???手动确认路??? ID 严格校验闭环

### 本次处理目标
- 涓? `RightsSummaryController::manualConfirmRightsSettlement` 补齐权益结算手动确认路由 `{id}` 严格校验测试???
- 验证 `/api/admin/manualConfirmRightsSettlement/{id}` 不能??? `id=真实IDabc` 交给数据库按前缀数字匹配真实权益结算记录???
- 验证非严格路??? ID 返回 `ResponseCode::VALIDATION_FAILED`，且不把待处理结算记录人工确认为已处理???

### 本次变更文件
- `tests/Feature/AdminRightsSummaryManualConfirmRouteIdValidationClosureModuleTest.php`
  - 新增手动确认入口非严??? `{id}` 被拒绝且不改写结算状态和备注的样例???
- `app/Http/Controllers/Admin/RightsSummaryController.php`
  - `manualConfirmRightsSettlement` 在查??? `rights_settlements.id` 前先校验路由参数，???过后再转换为整数???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminRightsSummaryManualConfirmRouteIdValidationClosureModuleTest.php` 首次运行失败，命中手动确认接口返??? `ResponseCode::UPDATED` 并改写真实结算记录，???终清单也缺少??? 297 节???
- GREEN：补齐手动确认路??? ID 前置校验和第 297 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminRightsSummaryManualConfirmRouteIdValidationClosureModuleTest` 覆盖真实 `admins`銆乣user_infos` 涓? `rights_settlements` 表记录??佸悗鍙? admin guard 登录态和 `/api/admin/manualConfirmRightsSettlement/{id}` 入口???
- 非严格路??? ID `真实IDabc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 鍘? `rights_settlements.status` 涓? `remark` 保持原???，避免 `rights_settlements.id` 在参数校验前被数据库数字前缀规则命中真实待处理结算并执行人工确认???

### 剩余边界
- 本轮没有改动权益汇???列表???导出??丮T4 自动确认、前端页面???数据范围服务或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???
## 298. 2026-07-09 后台数据范围代理绑定删除 ID 严格校验闭环

### 本次处理目标
- 涓? `DataScopeController::deleteAdminAgentBinding` 补齐管理员代理绑定删除请求体 `id` 严格校验测试???
- 验证 `/api/admin/deleteAdminAgentBinding` 不能??? `id=真实IDabc` 交给数据库按前缀数字匹配真实 `admin_agent_bindings` 记录???
- 验证非严??? ID 返回 `ResponseCode::VALIDATION_FAILED`，且不删除管理员代理绑定???

### 本次变更文件
- `tests/Feature/AdminDataScopeBindingDeleteIdValidationClosureModuleTest.php`
  - 新增删除管理员代理绑定时非严??? `id` 被拒绝且不软删除绑定记录的样例???
- `app/Http/Controllers/Admin/DataScopeController.php`
  - `deleteAdminAgentBinding` 在查??? `admin_agent_bindings.id` 前先校验请求??? `id`锛岄??过后再转换为整数???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminDataScopeBindingDeleteIdValidationClosureModuleTest.php` 首次运行失败，命中删除接口返??? `ResponseCode::DELETED` 并删除真实绑定记录，???终清单也缺少??? 298 节???
- GREEN：补齐删除绑??? ID 前置校验和第 298 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminDataScopeBindingDeleteIdValidationClosureModuleTest` 覆盖真实 `admins`銆乣user_infos` 涓? `admin_agent_bindings` 表记录??佸悗鍙? admin guard 登录态和 `/api/admin/deleteAdminAgentBinding` 入口???
- 非严格请求体 ID `真实IDabc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 鍘? `admin_agent_bindings.status` 涓? `deleted_at` 保持原???，避免 `admin_agent_bindings.id` 在参数校验前被数据库数字前缀规则命中真实管理员代理绑定并删除???

### 剩余边界
- 本轮没有改动角色数据范围保存、管理员代理绑定保存、数据范围列表???前端页面???权限字典或数据库结构???
- 后续继续按旧项目模块清单审计后台管理员模块???代理商模块和后台普通用户模块其它剩余入口???

## 299. 2026-07-09 后台数据范围代理绑定列表 admin_id 筛???严格校验闭???

### 本次处理目标
- 涓? `DataScopeController::adminAgentBindingList` 补齐管理员代理绑定列??? `admin_id` 筛???严格校验测试???
- 验证 `/api/admin/adminAgentBindingList` 不能??? `admin_id=真实IDabc` 交给数据库按数字前缀匹配真实管理员绑定列表???
- 验证非严??? `admin_id` 返回 `ResponseCode::VALIDATION_FAILED`，避免按 `admin_agent_bindings.admin_id` 的数字前???返回真实管理员代理绑定数据???

### 本次变更文件
- `tests/Feature/AdminDataScopeBindingListAdminIdValidationClosureModuleTest.php`
  - 新增管理员代理绑定列表非严格 `admin_id` 筛???被拒绝的样例???
- `app/Http/Controllers/Admin/DataScopeController.php`
  - `adminAgentBindingList` 在拼??? `admin_agent_bindings.admin_id` 查询条件前先校验 `admin_id`锛岄??过后再转换为整数???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminDataScopeBindingListAdminIdValidationClosureModuleTest.php` 首次运行失败，命中列表接口返回成功码并按真实管理??? ID 返回绑定列表，最终清单也缺少??? 299 节???
- GREEN：补齐列表筛??? `admin_id` 前置校验和第 299 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminDataScopeBindingListAdminIdValidationClosureModuleTest` 覆盖真实 `admins`銆乣user_infos` 涓? `admin_agent_bindings` 表记录??佸悗鍙? admin guard 登录态和 `/api/admin/adminAgentBindingList` 入口???
- 非严格筛选??? `admin_id=真实IDabc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 查询不会落到 `admin_agent_bindings.admin_id = 真实ID` 的前???匹配结果，避免列表接口泄露或误返回真实管理员代理绑定数据???

### 剩余边界
- 本轮没有改动角色数据范围保存、管理员代理绑定保存、管理员代理绑定删除、前端页面???权限字典或数据库结构???
- 后续继续按旧项目模块清单审计后台管理员模块???代理商模块和后台普通用户模块其它剩余入口???

## 300. 2026-07-09 后台角色数据范围 ID 列表严格校验闭环

### 本次处理目标
- 涓? `DataScopeController::saveRoleDataScope` 补齐角色数据范围保存??? `agent_ids` 鍜? `user_ids` 的严??? ID 列表校验测试???
- 验证 `/api/admin/saveRoleDataScope` 不能??? `agent_ids=真实IDabc` 写入 `role_data_scopes.agent_ids`銆?
- 验证 `/api/admin/saveRoleDataScope` 不能??? `user_ids=真实IDabc` 写入 `role_data_scopes.user_ids`銆?
- 验证非严??? ID 列表返回 `ResponseCode::VALIDATION_FAILED`，且不新增或覆盖角色数据范围配置???

### 本次变更文件
- `tests/Feature/AdminDataScopeRoleIdListValidationClosureModuleTest.php`
  - 新增代理 ID 列表、用??? ID 列表两个非严格输入被拒绝且不写入 `role_data_scopes` 的样例???
- `app/Http/Controllers/Admin/DataScopeController.php`
  - `saveRoleDataScope` 在解析并保存 `agent_ids`銆乣user_ids` 前先逐项校验正整??? ID锛岄伩鍏? `parseIdList` 把混入字母的值强转成数字或静默丢弃???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminDataScopeRoleIdListValidationClosureModuleTest.php` 首次运行失败，命中代??? ID 列表和用??? ID 列表均返??? `ResponseCode::UPDATED`，最终清单也缺少??? 300 节???
- GREEN锛氳ˉ榻? ID 列表前置校验、列表解析共用归???化方法和??? 300 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminDataScopeRoleIdListValidationClosureModuleTest` 覆盖真实 `admins`銆乣roles` 涓? `role_data_scopes` 表记录??佸悗鍙? admin guard 登录态和 `/api/admin/saveRoleDataScope` 入口???
- 非严格列表??? `agent_ids=真实IDabc` 涓? `user_ids=真实IDabc` 均返??? `ResponseCode::VALIDATION_FAILED`銆?
- `role_data_scopes.agent_ids` 鍜? `role_data_scopes.user_ids` 不会因为 PHP 整数强转写入数字前缀结果，也不会静默吞掉非法项后保存不完整配置???

### 剩余边界
- 本轮没有改动角色数据范围列表、管理员代理绑定保存、管理员代理绑定列表、管理员代理绑定删除、前端页面???权限字典或数据库结构???
- 后续继续按旧项目模块清单审计后台管理员模块???代理商模块和后台普通用户模块其它剩余入口???

## 301. 2026-07-09 后台角色请求??? ID 严格校验闭环

### 本次处理目标
- 涓? `RoleController::updateRole` 鍜? `RoleController::deleteRole` 补齐角色请求??? `id` 严格校验测试???
- 验证 `/api/admin/updateRole` 不能??? `id=真实IDabc` 交给数据库按数字前缀匹配真实 `roles.id`銆?
- 验证 `/api/admin/deleteRole` 收到非严??? `id` 时返??? `ResponseCode::VALIDATION_FAILED`，不删除真实角色???

### 本次变更文件
- `tests/Feature/AdminRoleRequestIdValidationClosureModuleTest.php`
  - 新增角色更新、删除两个请求体非严??? `id` 被拒绝且不落库的样例???
- `app/Http/Controllers/Admin/RoleController.php`
  - `updateRole` 鍜? `deleteRole` 在查??? `roles.id` 前先校验请求体或兼容路由 ID锛岄??过后再转换为整数???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminRoleRequestIdValidationClosureModuleTest.php` 首次运行失败，命中角色更新返??? `ResponseCode::UPDATED`、角色删除返??? `ResponseCode::DELETED`，最终清单也缺少??? 301 节???
- GREEN：补齐角??? ID 前置校验和第 301 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminRoleRequestIdValidationClosureModuleTest` 覆盖真实 `admins` 涓? `roles` 表记录??佸悗鍙? admin guard 登录态??乣/api/admin/updateRole` 鍜? `/api/admin/deleteRole` 两个入口???
- 非严格请求体 ID `真实IDabc` 均返??? `ResponseCode::VALIDATION_FAILED`銆?
- 原角色名称???说明???状态和 `deleted_at` 保持原???，避免 `roles.id` 在参数校验前被数据库数字前缀规则命中真实后台角色???

### 剩余边界
- 本轮没有改动角色创建、角色授权???权限字典???菜单字典???前端页面???权限迁移或数据库结构???
- 后续继续按旧项目模块清单审计后台管理员模块???代理商模块和后台普通用户模块其它剩余入口???

## 302. 2026-07-09 后台角色授权 ID 严格校验闭环

### 本次处理目标
- 涓? `RoleController::assignPermissions` 补齐角色授权 `role_id` 鍜? `permissions[]` 严格 ID 校验测试???
- 验证 `/api/admin/assignPermissions` 不能??? `role_id=真实IDabc` 写入 `role_permissions.role_id`銆?
- 验证 `/api/admin/assignPermissions` 不能??? `permissions[]=真实IDabc` 写入 `role_permissions.permission_id`銆?
- 验证非严格权??? ID 列表返回 `ResponseCode::VALIDATION_FAILED`，且不同步角色权限???

### 本次变更文件
- `tests/Feature/AdminRoleAssignPermissionIdValidationClosureModuleTest.php`
  - 新增角色 ID 和权??? ID 两类非严格输入被拒绝且不写入 `role_permissions` 的样例???
- `app/Http/Controllers/Admin/RoleController.php`
  - `assignPermissions` 在查??? `roles.id` 和同??? `permissions.id` 前先校验原始 ID锛岄伩鍏? `intval` 把混入字母的值转换为真实 ID銆?

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminRoleAssignPermissionIdValidationClosureModuleTest.php` 首次运行失败，命中角色授权接口两类非严格 ID 均返回成功码，最终清单也缺少??? 302 节???
- GREEN：补齐角色授??? ID 前置校验和第 302 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminRoleAssignPermissionIdValidationClosureModuleTest` 覆盖真实 `admins`銆乣roles`銆乣permissions` 涓? `role_permissions` 表记录??佸悗鍙? admin guard 登录态和 `/api/admin/assignPermissions` 入口???
- 非严??? `role_id=真实IDabc` 涓? `permissions[]=真实IDabc` 均返??? `ResponseCode::VALIDATION_FAILED`銆?
- `role_permissions.role_id` 鍜? `role_permissions.permission_id` 不会因为 `intval` 数字前缀规则同步到真实角色权限关系???

### 剩余边界
- 本轮没有改动角色创建、角色更新???角色删除???权限字典???菜单字典???前端页面???权限迁移或数据库结构???
- 后续继续按旧项目模块清单审计后台管理员模块???代理商模块和后台普通用户模块其它剩余入口???

## 303. 2026-07-09 后台权限字典请求??? ID 严格校验闭环

### 本次处理目标
- 涓? `PermissionController::updatePermission` 鍜? `PermissionController::deletePermission` 补齐权限字典请求??? `id` 严格校验测试???
- 验证 `/api/admin/updatePermission` 不能??? `id=真实IDabc` 交给数据库按数字前缀匹配真实 `permissions.id`銆?
- 验证 `/api/admin/deletePermission` 收到非严??? `id` 时返??? `ResponseCode::VALIDATION_FAILED`，不删除真实权限字典记录???

### 本次变更文件
- `tests/Feature/AdminPermissionRequestIdValidationClosureModuleTest.php`
  - 新增权限字典更新、删除两个请求体非严??? `id` 被拒绝且不落库的样例，并清理测试专用权限数据???
- `app/Http/Controllers/Admin/PermissionController.php`
  - `updatePermission` 鍜? `deletePermission` 在查??? `permissions.id` 前先校验请求体或兼容路由 ID锛岄??过后再转换为整数???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminPermissionRequestIdValidationClosureModuleTest.php` 首次运行失败，命中权限更新返??? `ResponseCode::UPDATED`、权限删除返??? `ResponseCode::DELETED`，最终清单也缺少??? 303 节???
- GREEN：补齐权限字??? ID 前置校验和第 303 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminPermissionRequestIdValidationClosureModuleTest` 覆盖真实 `admins` 与测试专??? `permissions` 表记录??佸悗鍙? admin guard 登录态??乣/api/admin/updatePermission` 鍜? `/api/admin/deletePermission` 两个入口???
- 非严格请求体 ID `真实IDabc` 均返??? `ResponseCode::VALIDATION_FAILED`銆?
- 原权限名称???权限字符串、状态和 `deleted_at` 保持原???，避免 `permissions.id` 在参数校验前被数据库数字前缀规则命中真实权限字典记录???

### 剩余边界
- 本轮没有改动权限创建、角色授权???菜单字典???前端页面???权限迁移???权限说明文档或数据库结构???
- 后续继续按旧项目模块清单审计后台管理员模块???代理商模块和后台普通用户模块其它剩余入口???

## 304. 2026-07-09 后台菜单字典请求??? ID 严格校验闭环

### 本次处理目标
- 涓? `MenuController::updateMenu` 鍜? `MenuController::deleteMenu` 补齐菜单字典请求??? `id` 严格校验测试???
- 验证 `/api/admin/updateMenu` 不能??? `id=真实IDabc` 交给数据库按数字前缀匹配真实 `permissions.id`銆?
- 验证 `/api/admin/deleteMenu` 收到非严??? `id` 时返??? `ResponseCode::VALIDATION_FAILED`，不删除真实菜单字典记录???

### 本次变更文件
- `tests/Feature/AdminMenuRequestIdValidationClosureModuleTest.php`
  - 新增菜单字典更新、删除两个请求体非严??? `id` 被拒绝且不落库的样例，并清理测试专用菜单权限数据???
- `app/Http/Controllers/Admin/MenuController.php`
  - `updateMenu` 鍜? `deleteMenu` 在查??? `permissions.id` 前先校验请求??? `id`锛岄??过后再转换为整数???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminMenuRequestIdValidationClosureModuleTest.php` 首次运行失败，命中菜单更新???菜单删除均返回成功??? `ResponseCode::SUCCESS`，最终清单也缺少??? 304 节???
- GREEN：补齐菜单字??? ID 前置校验和第 304 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminMenuRequestIdValidationClosureModuleTest` 覆盖真实 `admins` 与测试专??? `permissions` 表菜单记录??佸悗鍙? admin guard 登录态??乣/api/admin/updateMenu` 鍜? `/api/admin/deleteMenu` 两个入口???
- 非严格请求体 ID `真实IDabc` 均返??? `ResponseCode::VALIDATION_FAILED`銆?
- 原菜单名称??乻lug、页面路由???状态和 `deleted_at` 保持原???，避免 `permissions.id` 在参数校验前被数据库数字前缀规则命中真实菜单字典记录???

### 剩余边界
- 本轮没有改动菜单创建、菜单树读取、权限字典???角色授权???前端页面???权限迁移???权限说明文档或数据库结构???
- 后续继续按旧项目模块清单审计后台管理员模块???代理商模块和后台普通用户模块其它剩余入口???

## 305. 2026-07-09 后台入金请求??? ID 严格校验闭环

### 本次处理目标
- 涓? `DepositController::show`銆乣DepositController::approve` 鍜? `DepositController::reject` 补齐入金请求??? `id` 严格校验测试???
- 验证 `/api/admin/depositDetail` 不能??? `id=真实IDabc` 交给数据库按数字前缀匹配真实 `deposit_records.id` 并返回入金详情???
- 验证 `/api/admin/depositApprove` 鍜? `/api/admin/depositReject` 收到非严??? `id` 时返??? `ResponseCode::VALIDATION_FAILED`，不改写真实入金审核状??併??付款时间或驳回备注???

### 本次变更文件
- `tests/Feature/AdminDepositRequestIdValidationClosureModuleTest.php`
  - 新增入金详情、审核???过、审核驳回三个请求体非严??? `id` 被拒绝且不泄露或改写 `deposit_records` 的样例，并清理测试专用入金记录???
- `app/Http/Controllers/Admin/DepositController.php`
  - `show`銆乣approve` 鍜? `reject` 在查??? `deposit_records.id` 前先校验请求体或兼容路由 ID锛岄??过后再转换为整数???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminDepositRequestIdValidationClosureModuleTest.php` 首次运行失败，命中入金详情???审核???过、审核驳回均返回成功??? `ResponseCode::SUCCESS`，最终清单也缺少??? 305 节???
- GREEN：补齐入??? ID 前置校验和第 305 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminDepositRequestIdValidationClosureModuleTest` 覆盖真实 `admins` 与测试专??? `deposit_records` 表记录??佸悗鍙? admin guard 登录态??乣/api/admin/depositDetail`銆乣/api/admin/depositApprove` 鍜? `/api/admin/depositReject` 三个入口???
- 非严格请求体 ID `真实IDabc` 均返??? `ResponseCode::VALIDATION_FAILED`銆?
- 原入金记??? `status`銆乣payment_time` 涓? `remarks` 保持原???，详情响应不包含目标入金订单号，避??? `deposit_records.id` 在参数校验前被数据库数字前缀规则命中真实入金记录???

### 剩余边界
- 本轮没有改动入金列表、入金流水???批量入金导入???前端页面???权限字典???权限迁移或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 306. 2026-07-09 后台出金请求??? ID 严格校验闭环

### 本次处理目标
- 涓? `WithdrawController::process`銆乣WithdrawController::complete` 鍜? `WithdrawController::reject` 补齐出金请求??? `id` 严格校验测试???
- 验证 `/api/admin/withdrawProcess` 不能??? `id=真实IDabc` 交给数据库按数字前缀匹配真实 `withdraw_records.id` 并标记处理中???
- 验证 `/api/admin/withdrawComplete` 不能把非严格 `id` 标记为已完成???
- 验证 `/api/admin/withdrawReject` 收到非严??? `id` 时返??? `ResponseCode::VALIDATION_FAILED`，不改写真实出金记录的状态或拒绝原因???

### 本次变更文件
- `tests/Feature/AdminWithdrawRequestIdValidationClosureModuleTest.php`
  - 新增出金处理中???完成???拒绝三个请求体非严??? `id` 被拒绝且不落库的样例，并清理测试专用出金记录???
- `app/Http/Controllers/Admin/WithdrawController.php`
  - `show`銆乣process`銆乣complete` 鍜? `reject` 在查??? `withdraw_records.id` 前先校验请求体或兼容路由 ID锛岄??过后再转换为整数???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminWithdrawRequestIdValidationClosureModuleTest.php` 首次运行失败，命中出金处理中和拒绝入口返回业务成功码、完成入口返回数据不存在码，???终清单也缺少??? 306 节???
- GREEN：补齐出??? ID 前置校验和第 306 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminWithdrawRequestIdValidationClosureModuleTest` 覆盖真实 `admins` 与测试专??? `withdraw_records` 表记录??佸悗鍙? admin guard 登录态??乣/api/admin/withdrawProcess`銆乣/api/admin/withdrawComplete` 鍜? `/api/admin/withdrawReject` 三个入口???
- 非严格请求体 ID `真实IDabc` 均返??? `ResponseCode::VALIDATION_FAILED`銆?
- 原出金记??? `status` 鍜? `reject_reason` 保持原???，避免 `withdraw_records.id` 在参数校验前被数据库数字前缀规则命中真实出金记录???

### 剩余边界
- 本轮没有改动出金列表、出金流水???批量出金导入???前端页面???权限字典???权限迁移或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 307. 2026-07-09 后台返佣请求??? ID 严格校验闭环

### 本次处理目标
- 涓? `CommissionController::settle` 补齐返佣结算请求??? `id` 严格校验测试???
- 验证 `/api/admin/commissionSettle` 不能??? `id=真实IDabc` 交给数据库按数字前缀匹配真实 `commission_records.id` 并标记为已结算???
- 同步保护 `CommissionController::show`，避免后续接入详情路由时??? `commission_records.id` 参数校验前命中真实返佣记录???

### 本次变更文件
- `tests/Feature/AdminCommissionRequestIdValidationClosureModuleTest.php`
  - 新增单条返佣结算请求体非严格 `id` 被拒绝且不落库的样例，并清理测试专用返佣记录???
- `app/Http/Controllers/Admin/CommissionController.php`
  - `show` 鍜? `settle` 在查??? `commission_records.id` 前先校验请求体或兼容路由 ID锛岄??过后再转换为整数???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminCommissionRequestIdValidationClosureModuleTest.php` 首次运行失败，命中单条返佣结算入口返回成功码 `ResponseCode::SUCCESS` 并把真实记录标记为已结算，最终清单也缺少??? 307 节???
- GREEN：补齐返??? ID 前置校验和第 307 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminCommissionRequestIdValidationClosureModuleTest` 覆盖真实 `admins` 与测试专??? `commission_records` 表记录??佸悗鍙? admin guard 登录态和 `/api/admin/commissionSettle` 入口???
- 非严格请求体 ID `真实IDabc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 原返佣记??? `settle_status` 保持原???，避免 `commission_records.id` 在参数校验前被数据库数字前缀规则命中真实返佣结算记录???

### 剩余边界
- 本轮没有改动返佣列表、返佣批量结算???实时返佣???前端页面???权限字典???权限迁移或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 308. 2026-07-09 后台返佣列表 agent_id 筛???严格校验闭???

### 本次处理目标
- 涓? `CommissionController::index` 补齐返佣列表 `agent_id` 筛???严格校验测试???
- 验证 `/api/admin/commissionList` 不能??? `agent_id=真实IDabc` 交给数据库按数字前缀匹配真实 `commission_records.agent_id` 并返回返佣记录???
- 验证非严??? `agent_id` 返回 `ResponseCode::VALIDATION_FAILED`，避免列表筛选泄露真实代理返佣数据???

### 本次变更文件
- `tests/Feature/AdminCommissionListAgentIdValidationClosureModuleTest.php`
  - 新增返佣列表非严??? `agent_id` 筛???被拒绝且不返回测试返佣记录的样例，并清理测试专用返佣记录???
- `app/Http/Controllers/Admin/CommissionController.php`
  - `index` 在拼??? `commission_records.agent_id` 查询条件前先校验 `agent_id`锛岄??过后再转换为整数???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminCommissionListAgentIdValidationClosureModuleTest.php` 首次运行失败，命中返佣列表接口返回成功码并按真实代理 ID 返回记录，最终清单也缺少??? 308 节???
- GREEN：补齐返佣列??? `agent_id` 前置校验和第 308 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminCommissionListAgentIdValidationClosureModuleTest` 覆盖真实 `admins` 与测试专??? `commission_records` 表记录??佸悗鍙? admin guard 登录态和 `/api/admin/commissionList` 入口???
- 非严格筛选??? `agent_id=真实IDabc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 响应不包含测试返佣记??? `unique_id`锛岄伩鍏? `commission_records.agent_id` 在参数校验前被数据库数字前缀规则命中真实代理返佣数据???

### 剩余边界
- 本轮没有改动返佣结算、返佣批量结算???实时返佣???前端页面???权限字典???权限迁移或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 309. 2026-07-09 后台入金列表 user_id 筛???严格校验闭???

### 本次处理目标
- 涓? `DepositController::index` 补齐入金列表 `user_id` 筛???严格校验测试???
- 验证 `/api/admin/depositList` 不能??? `user_id=真实IDabc` 交给数据库按数字前缀匹配真实 `deposit_records.user_id` 并返回入金记录???
- 验证非严??? `user_id` 返回 `ResponseCode::VALIDATION_FAILED`，避免列表筛选泄露真实用户入金数据???

### 本次变更文件
- `tests/Feature/AdminDepositListUserIdValidationClosureModuleTest.php`
  - 新增入金列表非严??? `user_id` 筛???被拒绝且不返回测试入金记录的样例，并清理测试专用入金记录???
- `app/Http/Controllers/Admin/DepositController.php`
  - `index` 在拼??? `deposit_records.user_id` 查询条件前先校验 `user_id`锛岄??过后再转换为整数???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminDepositListUserIdValidationClosureModuleTest.php` 首次运行失败，命中入金列表接口返回成功码并按真实用户 ID 返回记录，最终清单也缺少??? 309 节???
- GREEN：补齐入金列??? `user_id` 前置校验和第 309 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminDepositListUserIdValidationClosureModuleTest` 覆盖真实 `admins` 与测试专??? `deposit_records` 表记录??佸悗鍙? admin guard 登录态和 `/api/admin/depositList` 入口???
- 非严格筛选??? `user_id=真实IDabc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 响应不包含测试入金订单号，避??? `deposit_records.user_id` 在参数校验前被数据库数字前缀规则命中真实用户入金数据???

### 剩余边界
- 本轮没有改动入金详情、入金审核???入金流水???批量入金导入???前端页面???权限字典???权限迁移或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 310. 2026-07-09 后台出金列表 user_id 筛???严格校验闭???

### 本次处理目标
- 涓? `WithdrawController::index` 补齐出金列表 `user_id` 筛???严格校验测试???
- 验证 `/api/admin/withdrawList` 不能??? `user_id=真实IDabc` 交给数据库按数字前缀匹配真实 `withdraw_records.user_id` 并返回出金记录???
- 验证非严??? `user_id` 返回 `ResponseCode::VALIDATION_FAILED`，避免列表筛选泄露真实用户出金数据???

### 本次变更文件
- `tests/Feature/AdminWithdrawListUserIdValidationClosureModuleTest.php`
  - 新增出金列表非严??? `user_id` 筛???被拒绝且不返回测试出金记录的样例，并清理测试专用出金记录???
- `app/Http/Controllers/Admin/WithdrawController.php`
  - `index` 在拼??? `withdraw_records.user_id` 查询条件前先校验 `user_id`锛岄??过后再转换为整数???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminWithdrawListUserIdValidationClosureModuleTest.php` 首次运行失败，命中出金列表接口返回成功码并按真实用户 ID 返回记录，最终清单也缺少??? 310 节???
- GREEN：补齐出金列??? `user_id` 前置校验和第 310 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminWithdrawListUserIdValidationClosureModuleTest` 覆盖真实 `admins` 与测试专??? `withdraw_records` 表记录??佸悗鍙? admin guard 登录态和 `/api/admin/withdrawList` 入口???
- 非严格筛选??? `user_id=真实IDabc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 响应不包含测试出金订单号，避??? `withdraw_records.user_id` 在参数校验前被数据库数字前缀规则命中真实用户出金数据???

### 剩余边界
- 本轮没有改动出金详情、出金处理???出金流水???批量出金导入???前端页面???权限字典???权限迁移或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 311. 2026-07-09 后台出金流水 user_id 筛???严格校验闭???

### 本次处理目标
- 涓? `FundFlowController::withdrawFlowList` 鍜? `FundFlowController::exportWithdrawFlows` 补齐出金流水 `user_id` 筛???严格校验测试???
- 验证 `/api/admin/withdrawFlowList` 不能??? `user_id=真实IDabc` 鍦? PHP 灞? `(int)` 强转成真??? `mt4_trades.login` 并返回出金流水???
- 验证 `/api/admin/exportWithdrawFlows` 收到非严??? `user_id` 时返??? `ResponseCode::VALIDATION_FAILED`，不生成当前筛???条件下??? CSV銆?

### 本次变更文件
- `tests/Feature/AdminWithdrawFlowUserIdValidationClosureModuleTest.php`
  - 新增出金流水列表和导出两个非严格 `user_id` 筛???被拒绝的样例，并清理测试专??? MT4 交易记录???
- `app/Http/Controllers/Admin/FundFlowController.php`
  - `withdrawFlowList` 鍜? `exportWithdrawFlows` 在复用筛选???辑前先校验 `user_id`锛岄??过后才允许 `applyWithdrawFlowFilters` 转换并拼??? `mt4_trades.login` 查询条件???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminWithdrawFlowUserIdValidationClosureModuleTest.php` 首次运行失败，命中出金流水列表返回成功码，导出入口返??? `text/csv`，最终清单也缺少??? 311 节???
- GREEN：补齐出金流??? `user_id` 前置校验和第 311 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminWithdrawFlowUserIdValidationClosureModuleTest` 覆盖真实 `admins` 与测试专??? `mt4_trades` 表记录??佸悗鍙? admin guard 登录态??乣/api/admin/withdrawFlowList` 鍜? `/api/admin/exportWithdrawFlows` 两个入口???
- 非严格筛选??? `user_id=真实IDabc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 响应不包含测??? MT4 ticket，导出入口不再输??? CSV锛岄伩鍏? `mt4_trades.login` 在参数校验前??? PHP 整数强转命中真实出金流水???

### 剩余边界
- 本轮没有改动未入金流水???未入金客户列表、入???/出金申请、前端页面???权限字典???权限迁移或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 312. 2026-07-09 后台未入金流??? user_id 筛???严格校验闭???

### 本次处理目标
- 涓? `FundFlowController::undepositFlowList` 鍜? `FundFlowController::exportUndepositFlows` 补齐未入金流??? `user_id` 筛???严格校验测试???
- 验证 `/api/admin/undepositFlowList` 不能??? `user_id=真实IDabc` 鍦? PHP 灞? `(int)` 强转成真??? `deposit_records.user_id` 并返回待支付入金记录???
- 验证 `/api/admin/exportUndepositFlows` 收到非严??? `user_id` 时返??? `ResponseCode::VALIDATION_FAILED`，不生成当前筛???条件下??? CSV銆?

### 本次变更文件
- `tests/Feature/AdminUndepositFlowUserIdValidationClosureModuleTest.php`
  - 新增未入金流水列表和导出两个非严??? `user_id` 筛???被拒绝的样例，并清理测试专用入金记录???
- `app/Http/Controllers/Admin/FundFlowController.php`
  - `undepositFlowList` 鍜? `exportUndepositFlows` 在复用筛选???辑前先校验 `user_id`锛岄??过后才允许 `applyUndepositFlowFilters` 转换并拼??? `deposit_records.user_id` 查询条件???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminUndepositFlowUserIdValidationClosureModuleTest.php` 首次运行失败，命中未入金流水列表返回成功码，导出入口返回 `text/csv`，最终清单也缺少??? 312 节???
- GREEN：补齐未入金流水 `user_id` 前置校验和第 312 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminUndepositFlowUserIdValidationClosureModuleTest` 覆盖真实 `admins` 与测试专??? `deposit_records` 表记录??佸悗鍙? admin guard 登录态??乣/api/admin/undepositFlowList` 鍜? `/api/admin/exportUndepositFlows` 两个入口???
- 非严格筛选??? `user_id=真实IDabc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 响应不包含测试入金订单号，导出入口不再输??? CSV锛岄伩鍏? `deposit_records.user_id` 在参数校验前??? PHP 整数强转命中真实未入金流水???

### 剩余边界
- 本轮没有改动出金流水、未入金客户列表、入???/出金申请、前端页面???权限字典???权限迁移或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 313. 2026-07-09 后台未入金客户列??? user_id 筛???严格校验闭???

### 本次处理目标
- 涓? `FundFlowController::neverDepositUserList` 补齐未入金客户列??? `user_id` 筛???严格校验测试???
- 验证 `/api/admin/neverDepositUserList` 不能??? `user_id=真实IDabc` 鍦? PHP 灞? `(int)` 强转成真??? `user_infos.user_id` 并返回未入金客户???
- 验证非严??? `user_id` 返回 `ResponseCode::VALIDATION_FAILED`，避免列表筛选泄露真实普通客户资料???

### 本次变更文件
- `tests/Feature/AdminNeverDepositUserListUserIdValidationClosureModuleTest.php`
  - 新增未入金客户列表非严格 `user_id` 筛???被拒绝且不返回测试客户姓名的样例，并清理测试专??? `user_infos`銆乣user_logins` 鍜? `deposit_records` 数据???
- `app/Http/Controllers/Admin/FundFlowController.php`
  - `neverDepositUserList` 在复??? `applyNeverDepositUserFilters` 前先校验 `user_id`锛岄??过后才允许筛???器转换并拼??? `user_infos.user_id` 查询条件???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminNeverDepositUserListUserIdValidationClosureModuleTest.php` 首次业务红灯命中未入金客户列表返回成功码 `ResponseCode::SUCCESS`，最终清单也缺少??? 313 节???
- GREEN：补齐未入金客户列表 `user_id` 前置校验和第 313 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminNeverDepositUserListUserIdValidationClosureModuleTest` 覆盖真实 `admins` 与测试专??? `user_infos`銆乣user_logins` 表记录??佸悗鍙? admin guard 登录态和 `/api/admin/neverDepositUserList` 入口???
- 非严格筛选??? `user_id=真实IDabc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 响应不包含测试客户姓名，避免 `user_infos.user_id` 在参数校验前??? PHP 整数强转命中真实未入金客户资料???

### 剩余边界
- 本轮没有改动未入金流水???出金流水??佸叆閲?/出金申请、前端页面???权限字典???权限迁移或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 314. 2026-07-09 后台仓位清零 user_id 严格校验闭环

### 本次处理目标
- 涓? `AdminWhsExpZeroController::zeroList`銆乣AdminWhsExpZeroController::recordList` 鍜? `AdminWhsExpZeroController::oneKeyZero` 补齐 `user_id` 严格校验测试???
- 验证 `/api/admin/whsExpZeroList` 不能??? `user_id=真实IDabc` 鍦? PHP 灞? `(int)` 强转成真??? `user_infos.user_id` 并返回清零??欓??客户???
- 验证 `/api/admin/whsExpZeroRecords` 不能把非严格 `user_id` 交给 `whs_exp_zeros.user_id` 查询并返回真实清零记录???
- 验证 `/api/admin/whsExpZero` 收到非严??? `user_id` 时返??? `ResponseCode::VALIDATION_FAILED`，不创建清零记录???

### 本次变更文件
- `tests/Feature/AdminWhsExpZeroUserIdValidationClosureModuleTest.php`
  - 新增清零候???列表???清零记录列表和???键清零三个入口的非严??? `user_id` 被拒绝样例，并清理测试专??? `user_infos`銆乣user_trades` 涓? `whs_exp_zeros` 数据???
- `app/Http/Controllers/Admin/AdminWhsExpZeroController.php`
  - 新增控制器内 `validateUserId()`，列表入口按可???筛选校验，???键清零入口按必填参数校验；校验???过后才允许查询 `user_infos.user_id` 鎴? `whs_exp_zeros.user_id`銆?

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminWhsExpZeroUserIdValidationClosureModuleTest.php` 首次运行失败，??欓??列表和记录列表返回成功??? `ResponseCode::SUCCESS`，一键清零入口返回服务端错误码，???终清单也缺少??? 314 节???
- GREEN：补齐仓位清零三个入口的 `user_id` 前置校验和第 314 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminWhsExpZeroUserIdValidationClosureModuleTest` 覆盖真实 `admins` 与测试专??? `user_infos`銆乣whs_exp_zeros` 表记录??佸悗鍙? admin guard 登录态和 `/api/admin/whsExpZeroList`銆乣/api/admin/whsExpZeroRecords`銆乣/api/admin/whsExpZero` 三个入口???
- 非严格筛选???或请求??? `user_id=真实IDabc` 均返??? `ResponseCode::VALIDATION_FAILED`銆?
- 响应不包含测试客户或清零记录名称，一键清零不写入 `whs_exp_zeros`锛岄伩鍏? `user_infos.user_id` 涓? `whs_exp_zeros.user_id` 在参数校验前被数字前???规则命中真实记录???

### 剩余边界
- 本轮没有改动仓位清零页面、权限字典???权限迁移??佸疄闄? MT4 清零同步或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 315. 2026-07-09 后台在线用户列表 user_id 筛???严格校验闭???

### 本次处理目标
- 涓? `OnlineUserController::onlineUserList` 补齐在线用户列表 `user_id` 筛???严格校验测试???
- 验证 `/api/admin/onlineUserList` 不能??? `user_id=真实IDabc` 鍦? PHP 灞? `(int)` 强转成真??? `user_onlines.user_id` 并返回在线记录???
- 验证非严??? `user_id` 返回 `ResponseCode::VALIDATION_FAILED`，避免在线用户列表按数字前缀泄露真实在线记录???

### 本次变更文件
- `tests/Feature/AdminOnlineUserListUserIdValidationClosureModuleTest.php`
  - 新增在线用户列表非严??? `user_id` 筛???被拒绝且不返回测试用户姓名的样例，并清理测试专??? `user_infos` 涓? `user_onlines` 数据???
- `app/Http/Controllers/Admin/OnlineUserController.php`
  - `onlineUserList` 在构??? `user_onlines` 查询前先校验 `user_id`锛岄??过后才允许 `applyFilters` 转换并拼??? `user_onlines.user_id` 查询条件???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminOnlineUserListUserIdValidationClosureModuleTest.php` 首次运行失败，命中在线用户列表返回成功码 `ResponseCode::SUCCESS`，最终清单也缺少??? 315 节???
- GREEN：补齐在线用户列??? `user_id` 前置校验和第 315 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminOnlineUserListUserIdValidationClosureModuleTest` 覆盖真实 `admins` 与测试专??? `user_infos`銆乣user_onlines` 表记录??佸悗鍙? admin guard 登录态和 `/api/admin/onlineUserList` 入口???
- 非严格筛选??? `user_id=真实IDabc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 响应不包含测试用户姓名，避免 `user_onlines.user_id` 在参数校验前??? PHP 整数强转命中真实在线记录???

### 剩余边界
- 本轮没有改动强制下线、在线用户页面???权限字典???权限迁移??丼SO 清理逻辑或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 316. 2026-07-09 后台持仓汇???数值筛选严格校验闭???

### 本次处理目标
- 涓? `PositionSummaryController::positionSummaryList` 鍜? `PositionSummaryController::exportPositionSummary` 补齐 `user_id`銆乣parent_id`銆乣account_type` 数???筛选严格校验???
- 验证 `/api/admin/positionSummaryList` 不能??? `user_id=真实IDabc`銆乣parent_id=真实IDabc` 鎴? `account_type=2abc` 鍦? PHP 灞? `(int)` 强转后交??? `user_infos.user_id`銆乣user_infos.parent_id`銆乣user_infos.account_type` 查询并返回真实用户???
- 验证 `/api/admin/exportPositionSummary` 收到非严格数值筛选时返回 `ResponseCode::VALIDATION_FAILED`，不输出当前筛???条件下??? CSV銆?

### 本次变更文件
- `tests/Feature/AdminPositionSummaryNumericFilterValidationClosureModuleTest.php`
  - 新增持仓汇???列表和导出两个入口的非严格数???筛选被拒绝样例，并清理测试专用 `user_infos` 数据???
- `app/Http/Controllers/Admin/PositionSummaryController.php`
  - `positionSummaryList` 鍜? `exportPositionSummary` 在构??? `user_infos` 汇???查询前统一调用 `validateNumericFilters()`锛岄??过后才允许 `applyUserFilters()` 转换并拼接查询条件???

### TDD 执行记录
- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit --filter AdminPositionSummaryNumericFilterValidationClosureModuleTest --colors=never` 首次运行失败，命??? `validateNumericFilters()` 缺失导致列表和导出入口返??? 500，最终清单也缺少??? 316 节???
- GREEN：补齐持仓汇总数值筛选前置校验和??? 316 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminPositionSummaryNumericFilterValidationClosureModuleTest` 覆盖真实 `admins` 与测试专??? `user_infos` 表记录??佸悗鍙? admin guard 登录态??乣/api/admin/positionSummaryList` 鍜? `/api/admin/exportPositionSummary` 两个入口???
- 非严格筛选??? `user_id=真实IDabc`銆乣parent_id=真实IDabc`銆乣account_type=2abc` 均返??? `ResponseCode::VALIDATION_FAILED`銆?
- 列表响应不包含测试用户姓名，导出入口不再输出 `text/csv`锛岄伩鍏? `user_infos.user_id`銆乣user_infos.parent_id`銆乣user_infos.account_type` 在参数校验前被整数强转命中真实持仓汇总用户???

### 剩余边界
- 本轮没有改动持仓汇???统计口径???交易聚合???页面???权限字典???权限迁移或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 317. 2026-07-09 后台风控当前持仓 user_id 筛???严格校验闭???

### 本次处理目标
- 涓? `RiskController::positions` 补齐当前持仓风险列表 `user_id` 筛???严格校验测试???
- 验证 `/api/admin/riskPositions` 不能??? `user_id=真实IDabc` 鍦? PHP 灞? `(int)` 强转成真??? `mt4_trades.login` 并返回当前持仓风险记录???
- 验证非严??? `user_id` 返回 `ResponseCode::VALIDATION_FAILED`，避免风控持仓列表按数字前缀泄露真实 MT4 持仓数据???

### 本次变更文件
- `tests/Feature/AdminRiskPositionsUserIdValidationClosureModuleTest.php`
  - 新增当前持仓风险列表非严??? `user_id` 筛???被拒绝且不返回测试 MT4 ticket 和用户姓名的样例，并清理测试专用 `mt4_trades` 涓? `user_infos` 数据???
- `app/Http/Controllers/Admin/RiskController.php`
  - `positions` 在构??? `mt4_trades` 当前持仓风险查询前先校验 `user_id`锛岄??过后才允许 `applyTradeFilters` 转换并拼??? `mt4_trades.login` 查询条件???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminRiskPositionsUserIdValidationClosureModuleTest.php --colors=never` 首次运行失败，命中风控当前持仓列表返回成功码 `ResponseCode::SUCCESS`，最终清单也缺少??? 317 节???
- GREEN：补齐风控当前持??? `user_id` 前置校验和第 317 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminRiskPositionsUserIdValidationClosureModuleTest` 覆盖真实 `admins`、测试专??? `user_infos` 鍜? `mt4_trades` 表记录??佸悗鍙? admin guard 登录态和 `/api/admin/riskPositions` 入口???
- 非严格筛选??? `user_id=真实IDabc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 响应不包含测??? MT4 ticket 和测试用户姓名，避免 `mt4_trades.login` 在参数校验前??? PHP 整数强转命中真实持仓风险记录???

### 剩余边界
- 本轮没有改动追保预警、异??? IP 风控、强平信号???风控页面???权限字典???权限迁移或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 318. 2026-07-09 后台风控追保预警数字筛???严格校验闭???

### 本次处理目标
- 涓? `RiskController::marginCalls` 补齐追保预警列表 `user_id`銆乣login` 鍜? `max_margin_level` 数字筛???严格校验测试???
- 验证 `/api/admin/riskMarginCalls` 不能??? `user_id=真实IDabc`銆乣login=真实登录abc` 鎴? `max_margin_level=100abc` 鍦? PHP 层强转后命中真实追保预警账号???
- 验证非严格数字筛选返??? `ResponseCode::VALIDATION_FAILED`，避免追保预警列表按数字前缀泄露真实 MT4 资金快照和业务用户资料???

### 本次变更文件
- `tests/Feature/AdminRiskMarginCallsNumericFilterValidationClosureModuleTest.php`
  - 新增追保预警列表非严格数字筛选被拒绝且不返回测试 MT4 登录账号和用户姓名的样例，并清理测试专用 `mt4_users` 涓? `user_infos` 数据???
- `app/Http/Controllers/Admin/RiskController.php`
  - `marginCalls` 在构??? `mt4_users` 追保预警查询前先校验 `user_id`銆乣login` 鍜? `max_margin_level`锛岄??过后才允许 `baseMarginCallQuery` 涓? `applyMarginCallFilters` 转换并拼接查询条件???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminRiskMarginCallsNumericFilterValidationClosureModuleTest.php --colors=never` 首次运行失败，命中追保预警列表返回成功码 `ResponseCode::SUCCESS`，最终清单也缺少??? 318 节???
- GREEN：补齐追保预警数字筛选前置校验和??? 318 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminRiskMarginCallsNumericFilterValidationClosureModuleTest` 覆盖真实 `admins`、测试专??? `user_infos` 鍜? `mt4_users` 表记录??佸悗鍙? admin guard 登录态和 `/api/admin/riskMarginCalls` 入口???
- 非严格筛选??? `user_id=真实IDabc`銆乣login=真实登录abc`銆乣max_margin_level=100abc` 均返??? `ResponseCode::VALIDATION_FAILED`銆?
- 响应不包含测??? MT4 登录账号和测试用户姓名，避免 `user_infos.user_id`銆乣mt4_users.login` 涓? `max_margin_level` 在参数校验前被整数或浮点强转命中真实追保预警账号???

### 剩余边界
- 本轮没有改动当前持仓风险、异??? IP 风控、强平信号???风控页面???权限字典???权限迁移或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 319. 2026-07-09 后台异常 IP 列表数字筛???严格校验闭???

### 本次处理目标
- 涓? `RiskController::riskIpList` 补齐异常 IP 风控列表 `user_id` 鍜? `min_user_count` 数字筛???严格校验测试???
- 验证 `/api/admin/riskIpList` 不能??? `user_id=真实IDabc` 鎴? `min_user_count=2abc` 鍦? PHP 灞? `(int)` 强转后交??? `user_login_logs.user_id` 查询或异??? IP 聚合阈??笺??
- 验证非严格数字筛选返??? `ResponseCode::VALIDATION_FAILED`，避免异??? IP 列表按数字前???泄露真实登录风险聚合数据???

### 本次变更文件
- `tests/Feature/AdminRiskIpListNumericFilterValidationClosureModuleTest.php`
  - 新增异常 IP 列表非严格数字筛选被拒绝且不返回测试登录 IP 和用户姓名的样例，并清理测试专用 `user_login_logs` 涓? `user_infos` 数据???
- `app/Http/Controllers/Admin/RiskController.php`
  - `riskIpList` 在构??? `user_login_logs` 异常 IP 聚合查询前先校验 `user_id` 鍜? `min_user_count`锛岄??过后才允许 `baseRiskIpQuery` 涓? `applyRiskIpFilters` 转换并拼接查询条件???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminRiskIpListNumericFilterValidationClosureModuleTest.php --colors=never` 首次运行失败，命中异??? IP 列表返回成功??? `ResponseCode::SUCCESS`，最终清单也缺少??? 319 节???
- GREEN：补齐异??? IP 列表数字筛???前置校验和??? 319 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminRiskIpListNumericFilterValidationClosureModuleTest` 覆盖真实 `admins`、测试专??? `user_infos` 鍜? `user_login_logs` 表记录??佸悗鍙? admin guard 登录态和 `/api/admin/riskIpList` 入口???
- 非严格筛选??? `user_id=真实IDabc`銆乣min_user_count=2abc` 均返??? `ResponseCode::VALIDATION_FAILED`銆?
- 响应不包含测试登??? IP 和测试用户姓名，避免 `user_login_logs.user_id` 与异??? IP 聚合阈???在参数校验前被整数强转影响真实风险列表???

### 剩余边界
- 本轮没有改动异常 IP 详情、当前持仓风险???追保预警???强平信号???风控页面???权限字典???权限迁移或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 320. 2026-07-09 后台异常 IP 详情 user_id 筛???严格校验闭???

### 本次处理目标
- 涓? `RiskController::riskIpDetail` 补齐异常 IP 详情 `user_id` 筛???严格校验测试???
- 验证 `/api/admin/riskIpDetail` 不能??? `user_id=真实IDabc` 鍦? PHP 灞? `(int)` 强转后交??? `user_login_logs.user_id` 查询并返回真实登录详情???
- 验证非严??? `user_id` 返回 `ResponseCode::VALIDATION_FAILED`，避免异??? IP 详情按数字前???泄露真实用户登录明细???

### 本次变更文件
- `tests/Feature/AdminRiskIpDetailUserIdValidationClosureModuleTest.php`
  - 新增异常 IP 详情非严??? `user_id` 筛???被拒绝且不返回测试登录 IP 和用户姓名的样例，并清理测试专用 `user_login_logs` 涓? `user_infos` 数据???
- `app/Http/Controllers/Admin/RiskController.php`
  - `riskIpDetail` 在完??? `login_ip` 必填校验后??佹瀯閫? `user_login_logs` 详情查询前校??? `user_id`锛岄??过后才允许 `applyRiskIpDetailFilters` 转换并拼??? `user_login_logs.user_id` 查询条件???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminRiskIpDetailUserIdValidationClosureModuleTest.php --colors=never` 首次运行失败，命中异??? IP 详情返回成功??? `ResponseCode::SUCCESS`，最终清单也缺少??? 320 节???
- GREEN：补齐异??? IP 详情 `user_id` 前置校验和第 320 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminRiskIpDetailUserIdValidationClosureModuleTest` 覆盖真实 `admins`、测试专??? `user_infos` 鍜? `user_login_logs` 表记录??佸悗鍙? admin guard 登录态和 `/api/admin/riskIpDetail` 入口???
- 非严格筛选??? `user_id=真实IDabc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 响应不包含测试登??? IP 和测试用户姓名，避免 `user_login_logs.user_id` 在参数校验前被整数强转命中真实异??? IP 登录详情???

### 剩余边界
- 本轮没有改动异常 IP 列表、当前持仓风险???追保预警???强平信号???风控页面???权限字典???权限迁移或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 321. 2026-07-09 后台实名认证列表 user_id 筛???严格校验闭???

### 本次处理目标
- 涓? `AuthenticationController::pendingList` 鍜? `AuthenticationController::certifiedList` 补齐实名认证列表 `user_id` 筛???严格校验测试???
- 验证 `/api/admin/authPendingList` 不能??? `user_id=真实IDabc` 鍦? PHP 灞? `(int)` 强转后交??? `user_auths.user_id` 查询并返回真实待审认证记录???
- 验证 `/api/admin/authCertifiedList` 不能把非严格 `user_id` 命中真实已审认证记录???

### 本次变更文件
- `tests/Feature/AdminAuthenticationUserIdValidationClosureModuleTest.php`
  - 新增待审认证列表和已审认证列表非严格 `user_id` 筛???被拒绝且不返回测试用户姓名的样例，并清理测试专??? `user_auths` 涓? `user_infos` 数据???
- `app/Http/Controllers/Admin/AuthenticationController.php`
  - `pendingList` 鍜? `certifiedList` 在构??? `user_auths` 认证查询前校??? `user_id`锛岄??过后才允许 `applyFilters` 转换并拼??? `user_auths.user_id` 查询条件???

### TDD 执行记录
- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit --filter AdminAuthenticationUserIdValidationClosureModuleTest --colors=never` 首次运行失败，命中待审和已审认证列表返回成功??? `ResponseCode::SUCCESS`，最终清单也缺少??? 321 节???
- GREEN：补齐实名认证列??? `user_id` 前置校验和第 321 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminAuthenticationUserIdValidationClosureModuleTest` 覆盖真实 `admins`、测试专??? `user_infos` 鍜? `user_auths` 表记录??佸悗鍙? admin guard 登录态以??? `/api/admin/authPendingList`銆乣/api/admin/authCertifiedList` 两个入口???
- 非严格筛选??? `user_id=真实IDabc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 响应不包含测试认证用户姓名，避免 `user_auths.user_id` 在参数校验前被整数强转命中真实认证资料???

### 剩余边界
- 本轮没有改动认证审核动作、实名认证页面???权限字典???权限迁移或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 322. 2026-07-09 后台权益汇???数字筛选严格校验闭???

### 本次处理目标
- 涓? `RightsSummaryController::rightsSummaryList` 鍜? `RightsSummaryController::exportRightsSummary` 补齐 `user_id`銆乣login`銆乣min_equity`銆乣max_equity` 数字筛???严格校验测试???
- 验证 `/api/admin/rightsSummaryList` 不能??? `user_id=真实IDabc`銆乣login=真实登录abc`銆乣min_equity=1000abc` 鎴? `max_equity=1300abc` 鍦? PHP 层强转后命中真实权益汇???账号???
- 验证 `/api/admin/exportRightsSummary` 收到非严格数字筛选时返回 `ResponseCode::VALIDATION_FAILED`，不输出当前筛???条件下??? CSV銆?

### 本次变更文件
- `tests/Feature/AdminRightsSummaryNumericFilterValidationClosureModuleTest.php`
  - 新增权益汇???列表和导出两个入口的非严格数字筛???被拒绝样例，并清理测试专用 `user_infos`銆乣mt4_users` 涓? `rights_settlements` 数据???
- `app/Http/Controllers/Admin/RightsSummaryController.php`
  - `rightsSummaryList` 鍜? `exportRightsSummary` 在构??? `mt4_users` 权益汇???查询前统一调用 `validateNumericFilters()`锛岄??过后才允许 `applyFilters()` 转换并拼接查询条件???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminRightsSummaryNumericFilterValidationClosureModuleTest.php --colors=never` 首次运行失败，命中权益汇总列表返回成功码 `ResponseCode::SUCCESS`、导出入口返??? `text/csv`，最终清单也缺少??? 322 节???
- GREEN：补齐权益汇总数字筛选前置校验和??? 322 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminRightsSummaryNumericFilterValidationClosureModuleTest` 覆盖真实 `admins`、测试专??? `user_infos`銆乣mt4_users` 鍜? `rights_settlements` 表记录??佸悗鍙? admin guard 登录态以??? `/api/admin/rightsSummaryList`銆乣/api/admin/exportRightsSummary` 两个入口???
- 非严格筛选??? `user_id=真实IDabc`銆乣login=真实登录abc`銆乣min_equity=1000abc`銆乣max_equity=1300abc` 均返??? `ResponseCode::VALIDATION_FAILED`銆?
- 列表响应不包含测试用户姓名和 MT4 名称，导出入口不再输??? `text/csv`锛岄伩鍏? `user_infos.user_id`銆乣mt4_users.login`銆乣mt4_users.equity` 在参数校验前被整数或浮点强转命中真实权益汇???账号???

### 剩余边界
- 本轮没有改动权益结算手动确认、权益汇总页面???权限字典???权限迁移或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 323. 2026-07-09 后台礼品发货与地??? user_id 筛???严格校验闭???

### 本次处理目标
- 涓? `GiftController::shipmentList`銆乣GiftController::exportGiftShipments` 鍜? `GiftController::addressList` 补齐礼品发货与收货地??? `user_id` 筛???严格校验测试???
- 验证 `/api/admin/giftShipmentList` 不能??? `user_id=真实IDabc` 鍦? PHP 灞? `(int)` 强转后交??? `gift_shipments.user_id` 查询并返回真实发货记录???
- 验证 `/api/admin/exportGiftShipments` 收到非严??? `user_id` 时返??? `ResponseCode::VALIDATION_FAILED`，不输出当前筛???条件下??? CSV銆?
- 验证 `/api/admin/giftAddressList` 不能把非严格 `user_id` 命中真实可发放礼品地???銆?

### 本次变更文件
- `tests/Feature/AdminGiftUserIdValidationClosureModuleTest.php`
  - 新增礼品发货列表、发货导出和收货地址列表三个入口的非严格 `user_id` 筛???被拒绝样例，并清理测试专用 `gift_shipments`銆乣user_addresses` 涓? `user_infos` 数据???
- `app/Http/Controllers/Admin/GiftController.php`
  - `shipmentList`銆乣exportGiftShipments` 鍜? `addressList` 在构造查询前校验 `user_id`锛岄??过后才允许筛???器转换并拼??? `gift_shipments.user_id` 鎴? `user_addresses.user_id` 查询条件???

### TDD 执行记录
- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit --filter AdminGiftUserIdValidationClosureModuleTest --colors=never` 首次运行失败，命中礼品发货列表和地址列表返回成功??? `ResponseCode::SUCCESS`、导出入口返??? `text/csv`，最终清单也缺少??? 323 节???
- GREEN：补齐礼品发货与地址 `user_id` 前置校验和第 323 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminGiftUserIdValidationClosureModuleTest` 覆盖真实 `admins`、测试专??? `user_infos`銆乣user_addresses` 鍜? `gift_shipments` 表记录??佸悗鍙? admin guard 登录态以??? `/api/admin/giftShipmentList`銆乣/api/admin/exportGiftShipments`銆乣/api/admin/giftAddressList` 三个入口???
- 非严格筛选??? `user_id=真实IDabc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 列表响应不包含测试礼品???物流单号???收件人或用户姓名，导出入口不再输出 `text/csv`锛岄伩鍏? `gift_shipments.user_id` 涓? `user_addresses.user_id` 在参数校验前被整数强转命中真实礼品资料???

### 剩余边界
- 本轮没有改动礼品发放动作、物流更新动作???礼品配置筛选???礼品页面???权限字典???权限迁移或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 324. 2026-07-09 后台交易订单 user_id 筛???严格校验闭???

### 本次处理目标
- 涓? `TradeController::index`銆乣TradeController::openPositions` 鍜? `TradeController::closedPositions` 补齐交易订单 `user_id` 筛???严格校验测试???
- 验证 `/api/admin/tradeList`銆乣/api/admin/openPositions` 鍜? `/api/admin/closedPositions` 不能??? `user_id=真实IDabc` 鍦? PHP 灞? `(int)` 强转成真??? `mt4_trades.login` 并返回交易记录???
- 验证非严??? `user_id` 返回 `ResponseCode::VALIDATION_FAILED`，避免交易列表???当前持仓和平仓记录按数字前???泄露真实 MT4 订单数据???

### 本次变更文件
- `tests/Feature/AdminTradeUserIdValidationClosureModuleTest.php`
  - 新增交易列表、当前持仓和平仓记录三个入口的非严格 `user_id` 筛???被拒绝样例，并清理测试专用 `mt4_trades` 涓? `user_infos` 数据???
- `app/Http/Controllers/Admin/TradeController.php`
  - 三个列表入口在构??? `mt4_trades` 查询前校??? `user_id`锛岄??过后才允许 `applyTradeFilters` 转换并拼??? `mt4_trades.login` 查询条件???

### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminTradeUserIdValidationClosureModuleTest.php --colors=never` 首次运行失败，命中交易列表入口返回成功码 `ResponseCode::SUCCESS`，最终清单也缺少??? 324 节???
- GREEN：补齐交易订??? `user_id` 前置校验和第 324 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminTradeUserIdValidationClosureModuleTest` 覆盖真实 `admins`、测试专??? `user_infos` 鍜? `mt4_trades` 表记录??佸悗鍙? admin guard 登录态以??? `/api/admin/tradeList`銆乣/api/admin/openPositions`銆乣/api/admin/closedPositions` 三个入口???
- 非严格筛选??? `user_id=真实IDabc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 响应不包含测??? MT4 ticket 和测试用户姓名，避免 `mt4_trades.login` 在参数校验前被整数强转命中真实交易订单???

### 剩余边界
- 本轮没有改动交易概览 `tradeSummary`、交易页面???权限字典???权限迁移或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 325. 2026-07-09 后台礼品配置列表数字筛???严格校验闭???

### 本次处理目标
- 涓? `GiftController::giftItemList` 补齐礼品配置列表 `points_cost` 鍜? `status` 数字筛???严格校验测试???
- 验证 `/api/admin/giftItemList` 不能??? `points_cost=420abc` 鎴? `status=1abc` 鍦? PHP 灞? `(int)` 强转后交??? `gift_items.points_cost`銆乣gift_items.status` 查询并返回真实礼品配置???
- 验证非严格数字筛选返??? `ResponseCode::VALIDATION_FAILED`，避免礼品配置列表按数字前缀泄露真实配置记录???

### 本次变更文件
- `tests/Feature/AdminGiftItemNumericFilterValidationClosureModuleTest.php`
  - 新增礼品配置列表非严??? `points_cost` 鍜? `status` 筛???被拒绝且不返回测试礼品名称的样例，并清理测试专??? `gift_items` 数据???
- `app/Http/Controllers/Admin/GiftController.php`
  - `giftItemList` 在构??? `gift_items` 查询前校??? `points_cost` 鍜? `status`锛岄??过后才允许列表筛???器转换并拼接查询条件???

### TDD 执行记录
- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit --filter AdminGiftItemNumericFilterValidationClosureModuleTest --colors=never` 首次运行失败，命中礼品配置列表返回成功码 `ResponseCode::SUCCESS`，最终清单也缺少??? 325 节???
- GREEN：补齐礼品配置列表数字筛选前置校验和??? 325 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminGiftItemNumericFilterValidationClosureModuleTest` 覆盖真实 `admins`、测试专??? `gift_items` 表记录??佸悗鍙? admin guard 登录态以??? `/api/admin/giftItemList` 入口???
- 非严格筛选??? `points_cost=420abc` 鍜? `status=1abc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 响应不包含测试礼品配置名称，避免 `gift_items.points_cost` 涓? `gift_items.status` 在参数校验前被整数强转命中真实礼品配置???

### 剩余边界
- 本轮没有改动礼品配置创建、更新???删除???礼品发货与地址筛??夈??礼品页面???权限字典???权限迁移或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 326. 2026-07-09 后台批量信用导入数字筛???严格校验闭???

### 本次处理目标
- 涓? `BatchCreditImportController::creditImportList` 鍜? `BatchCreditImportController::exportCreditImports` 补齐批量信用导入 `user_id`銆乣credit_type` 鍜? `is_synced` 数字筛???严格校验测试???
- 验证 `/api/admin/creditImportList` 不能??? `user_id=真实IDabc`銆乣credit_type=3abc` 鎴? `is_synced=2abc` 鍦? PHP 灞? `(int)` 强转后交??? `credit_imports.user_id`銆乣credit_imports.credit_type`銆乣credit_imports.is_synced` 查询并返回真实导入记录???
- 验证 `/api/admin/exportCreditImports` 收到非严格数字筛选时返回 `ResponseCode::VALIDATION_FAILED`，不输出当前筛???条件下??? CSV銆?

### 本次变更文件
- `tests/Feature/AdminBatchCreditImportNumericFilterValidationClosureModuleTest.php`
  - 新增批量信用导入列表和导出两个入口的非严格数字筛选被拒绝样例，并清理测试专用 `credit_imports` 涓? `user_infos` 数据???
- `app/Http/Controllers/Admin/BatchCreditImportController.php`
  - `creditImportList` 鍜? `exportCreditImports` 在构??? `credit_imports` 查询前校??? `user_id`銆乣credit_type` 鍜? `is_synced`锛岄??过后才允许 `applyFilters()` 转换并拼接查询条件???

### TDD 执行记录
- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit --filter AdminBatchCreditImportNumericFilterValidationClosureModuleTest --colors=never` 首次运行失败，命中批量信用导入列表返回成功码 `ResponseCode::SUCCESS`、导出入口返??? `text/csv`，最终清单也缺少??? 326 节???
- GREEN：补齐批量信用导入数字筛选前置校验和??? 326 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminBatchCreditImportNumericFilterValidationClosureModuleTest` 覆盖真实 `admins`、测试专??? `user_infos` 鍜? `credit_imports` 表记录??佸悗鍙? admin guard 登录态以??? `/api/admin/creditImportList`銆乣/api/admin/exportCreditImports` 两个入口???
- 非严格筛选??? `user_id=真实IDabc`銆乣credit_type=3abc` 鍜? `is_synced=2abc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 列表响应不包含测试用户姓名和批次号，导出入口不再输出 `text/csv`锛岄伩鍏? `credit_imports.user_id`銆乣credit_imports.credit_type` 涓? `credit_imports.is_synced` 在参数校验前被整数强转命中真实信用导入记录???

### 剩余边界
- 本轮没有改动信用导入新增、CSV 解析、失败重试???模板下载???权限字典???权限迁移或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 327. 2026-07-09 后台批量入金/出金导入数字筛???严格校验闭???

### 本次处理目标
- 涓? `BatchAmountImportController::depositImportList`銆乣BatchAmountImportController::withdrawImportList`銆乣BatchAmountImportController::exportDepositImports` 鍜? `BatchAmountImportController::exportWithdrawImports` 补齐批量入金/出金导入 `user_id` 涓? `is_synced` 数字筛???严格校验测试???
- 验证 `/api/admin/depositImportList` 鍜? `/api/admin/withdrawImportList` 不能??? `user_id=真实IDabc` 鎴? `is_synced=2abc` 鍦? PHP 灞? `(int)` 强转后交??? `deposit_imports.user_id`銆乣withdraw_imports.user_id`銆乣deposit_imports.is_synced` 鎴? `withdraw_imports.is_synced` 查询并返回真实导入记录???
- 验证 `/api/admin/exportDepositImports` 涓? `/api/admin/exportWithdrawImports` 收到非严格数字筛选时返回 `ResponseCode::VALIDATION_FAILED`，不输出当前筛???条件下??? CSV銆?

### 本次变更文件
- `tests/Feature/AdminBatchAmountImportNumericFilterValidationClosureModuleTest.php`
  - 新增批量入金/出金导入列表和导出四个入口的非严格数字筛选被拒绝样例，并清理测试专用 `deposit_imports`銆乣withdraw_imports` 涓? `user_infos` 数据???
- `app/Http/Controllers/Admin/BatchAmountImportController.php`
  - 四个列表/导出入口在构造导入记录查询前校验 `user_id` 鍜? `is_synced`锛岄??过后才允许 `applyCommonFilters()` 转换并拼接查询条件???

### TDD 执行记录
- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit --filter AdminBatchAmountImportNumericFilterValidationClosureModuleTest --colors=never` 首次运行失败，命中批量入???/出金导入列表返回成功??? `ResponseCode::SUCCESS`、导出入口返??? `text/csv`，最终清单也缺少??? 327 节???
- GREEN：补齐批量入???/出金导入数字筛???前置校验和??? 327 节清单后，目标测试??氳繃銆?

### 当前证据
- `AdminBatchAmountImportNumericFilterValidationClosureModuleTest` 覆盖真实 `admins`、测试专??? `user_infos`銆乣deposit_imports` 鍜? `withdraw_imports` 表记录??佸悗鍙? admin guard 登录态以??? `/api/admin/depositImportList`銆乣/api/admin/withdrawImportList`銆乣/api/admin/exportDepositImports`銆乣/api/admin/exportWithdrawImports` 四个入口???
- 非严格筛选??? `user_id=真实IDabc` 鍜? `is_synced=2abc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 列表响应不包含测试用户姓名和批次号，导出入口不再输出 `text/csv`，避免入???/出金导入表的 `user_id` 涓? `is_synced` 在参数校验前被整数强转命中真实记录???

### 剩余边界
- 本轮没有改动入金/出金导入新增、CSV 解析、失败重试???模板下载???权限字典???权限迁移或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???
## 328. 2026-07-09 后台实时返佣 user_id 筛???严格校验闭???
### 本次处理目标
- 涓? `RealtimeCommissionController::realtimeCommissionList` 鍜? `RealtimeCommissionController::exportRealtimeCommissions` 补齐实时返佣 `user_id` 筛???严格校验测试???
- 验证 `/api/admin/realtimeCommissionList` 不能??? `user_id=真实IDabc` 鍦? PHP 灞? `(int)` 强转后交??? `mt4_trades.login` 查询并返回真实返佣记录???
- 验证 `/api/admin/exportRealtimeCommissions` 收到非严??? `user_id` 时返??? `ResponseCode::VALIDATION_FAILED`，不输出当前筛???条件下??? CSV銆?
### 本次变更文件
- `tests/Feature/AdminRealtimeCommissionUserIdValidationClosureModuleTest.php`
  - 新增实时返佣列表和导出两个入口的非严??? `user_id` 筛???被拒绝样例，并清理测试专用 `mt4_trades` 涓? `user_infos` 数据???
- `app/Http/Controllers/Admin/RealtimeCommissionController.php`
  - `realtimeCommissionList` 鍜? `exportRealtimeCommissions` 在构??? `mt4_trades` 查询前校??? `user_id`锛岄??过后才允许 `applyFilters()` 转换并拼??? `mt4_trades.login` 查询条件???
### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminRealtimeCommissionUserIdValidationClosureModuleTest.php --colors=never` 首次运行失败，命中实时返佣列表返回成功码 `ResponseCode::SUCCESS`、导出入口返??? `text/csv`，最终清单也缺少实时返佣严格校验章节???
- GREEN：补齐实时返??? `user_id` 前置校验和第 328 节清单后，目标测试??氳繃銆?
### 当前证据
- `AdminRealtimeCommissionUserIdValidationClosureModuleTest` 覆盖真实 `admins`、测试专??? `user_infos` 鍜? `mt4_trades` 表记录??佸悗鍙? admin guard 登录态以??? `/api/admin/realtimeCommissionList`銆乣/api/admin/exportRealtimeCommissions` 两个入口???
- 非严格筛选??? `user_id=真实IDabc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 列表响应不包含测??? MT4 ticket 和测试用户姓名，导出入口不再输出 `text/csv`锛岄伩鍏? `mt4_trades.login` 在参数校验前被整数强转命中真实实时返佣记录???
### 剩余边界
- 本轮没有改动实时返佣识别规则、返佣页面???权限字典???权限迁移或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???
## 329. 2026-07-09 后台产品/交易品种数字筛???严格校验闭???
### 本次处理目标
- 涓? `ProductionController::productionList` 鍜? `ProductionController::exportProductions` 补齐产品/交易品种 `group_id` 涓? `status` 数字筛???严格校验测试???
- 验证 `/api/admin/productionList` 不能??? `group_id=真实分组abc` 鍦? PHP 灞? `(int)` 强转后交??? `symbol_prices.group_id` 查询并返回真实交易品种???
- 验证 `/api/admin/exportProductions` 收到非严??? `status` 时返??? `ResponseCode::VALIDATION_FAILED`，不输出当前筛???条件下??? CSV銆?
### 本次变更文件
- `tests/Feature/AdminProductionNumericFilterValidationClosureModuleTest.php`
  - 新增产品/交易品种列表和导出两个入口的非严格数字筛选被拒绝样例，并清理测试专用 `symbol_prices` 数据???
- `app/Http/Controllers/Admin/ProductionController.php`
  - `productionList` 鍜? `exportProductions` 在构??? `symbol_prices` 查询前校??? `group_id` 涓? `status`锛岄??过后才允许 `applyFilters()` 转换并拼??? `symbol_prices.group_id`銆乣symbol_prices.status` 查询条件???
### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminProductionNumericFilterValidationClosureModuleTest.php --colors=never` 首次运行失败，命中产品列表返回成功码 `ResponseCode::SUCCESS`、导出入口返??? `text/csv`，最终清单也缺少??? 329 节???
- GREEN：补齐产???/交易品种数字筛???前置校验和??? 329 节清单后，目标测试??氳繃銆?
### 当前证据
- `AdminProductionNumericFilterValidationClosureModuleTest` 覆盖真实 `admins`、测试专??? `symbol_prices` 表记录??佸悗鍙? admin guard 登录态以??? `/api/admin/productionList`銆乣/api/admin/exportProductions` 两个入口???
- 非严格筛选??? `group_id=真实分组abc` 鍜? `status=1abc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 列表响应不包含测试交易品种代码，导出入口不再输出 `text/csv`锛岄伩鍏? `symbol_prices.group_id` 涓? `symbol_prices.status` 在参数校验前被整数强转命中真实产???/交易品种记录???
### 剩余边界
- 本轮没有改动产品/交易品种创建、更新???删除???持仓汇总口径???产品页面???权限字典???权限迁移或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???
## 330. 2026-07-09 后台系统配置更新 id 严格校验闭环
### 本次处理目标
- 涓? `SystemConfigController::update` 鍜? `SystemConfigController::updateSingleConfig` 补齐请求??? `id` 严格校验测试???
- 验证 `/api/admin/updateSystemConfig` 不能??? `id=真实IDabc` 交给 `system_configs.id` 查询后返回非参数错误或误命中真实配置???
- 验证非严??? `id` 返回 `ResponseCode::VALIDATION_FAILED`，且不会更新??? `system_configs` 记录???
### 本次变更文件
- `tests/Feature/AdminSystemConfigUpdateIdValidationClosureModuleTest.php`
  - 新增系统配置单行更新非严??? `id` 被拒绝且不改写测试配置???与描述的样例，并清理测试专??? `system_configs` 数据???
- `app/Http/Controllers/Admin/SystemConfigController.php`
  - `updateSingleConfig` 在按 `system_configs.id` 查询前校验请求体 `id`锛岄??过后才转换为整数并拼接主键查询条件；按 `key` 更新的兼容路径保持不变???
### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminSystemConfigUpdateIdValidationClosureModuleTest.php --colors=never` 首次运行失败，非严格 `id` 返回 `ResponseCode::DATA_NOT_FOUND`，最终清单也缺少??? 330 节???
- GREEN：补齐系统配置更??? `id` 前置校验和第 330 节清单后，目标测试??氳繃銆?
### 当前证据
- `AdminSystemConfigUpdateIdValidationClosureModuleTest` 覆盖真实 `admins`、测试专??? `system_configs` 表记录??佸悗鍙? admin guard 登录态以??? `/api/admin/updateSystemConfig` 入口???
- 非严格请求体??? `id=真实IDabc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 测试配置??? `value` 鍜? `description` 保持原???，避免 `system_configs.id` 在参数校验前进入查询链路造成错误语义或潜在误命中???
### 剩余边界
- 本轮没有改动系统配置列表、批??? `configs[key]=value` 更新、按 `key` 更新兼容路径、系统配置页面???权限字典???权限迁移或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 331. 2026-07-09 后台礼品地址默认标记筛???严格校验闭???
### 本次处理目标
- 涓? `GiftController::addressList` 补齐礼品地址 `is_default` 数字筛???严格校验测试???
- 验证 `/api/admin/giftAddressList` 不能??? `is_default=1abc` 鍦? PHP 灞? `(int)` 强转后交??? `user_addresses.is_default` 查询并返回真实默认地???銆?
- 验证非严??? `is_default` 返回 `ResponseCode::VALIDATION_FAILED`，避免礼品地???列表按数字前???泄露真实可发放地???记录???
### 本次变更文件
- `tests/Feature/AdminGiftAddressDefaultFilterValidationClosureModuleTest.php`
  - 新增礼品地址列表非严??? `is_default` 筛???被拒绝且不返回测试用户姓名与收件人的样例，并清理测试专??? `user_infos` 涓? `user_addresses` 数据???
- `app/Http/Controllers/Admin/GiftController.php`
  - `addressList` 在构??? `user_addresses` 查询前校??? `is_default`锛岄??过后才允许 `applyAddressFilters()` 转换并拼??? `user_addresses.is_default` 查询条件???
### TDD 执行记录
- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit --filter AdminGiftAddressDefaultFilterValidationClosureModuleTest --colors=never` 首次运行失败，命中礼品地???列表返回成功??? `ResponseCode::SUCCESS`，最终清单也缺少??? 331 节???
- GREEN：补齐礼品地???默认标记筛???前置校验和??? 331 节清单后，目标测试??氳繃銆?
### 当前证据
- `AdminGiftAddressDefaultFilterValidationClosureModuleTest` 覆盖真实 `admins`、测试专??? `user_infos` 鍜? `user_addresses` 表记录??佸悗鍙? admin guard 登录态以??? `/api/admin/giftAddressList` 入口???
- 非严格筛选??? `is_default=1abc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 列表响应不包含测试用户姓名与收件人，避免 `user_addresses.is_default` 在参数校验前被整数强转命中真实礼品地???銆?
### 剩余边界
- 本轮没有改动礼品发货列表、发货导出???礼品配置???礼品发放动作???礼品页面???权限字典???权限迁移或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???
## 332. 2026-07-09 后台普???用户列??? account_type 筛???严格校验闭???
### 本次处理目标
- 涓? `AdminUserController::userList`銆乣AdminUserController::exportUsers` 鍜? `AdminUserController::filteredUserQuery` 补齐 `account_type` 筛???严格校验测试???
- 验证 `/api/admin/userList` 不能??? `account_type=2abc` 交给 `user_infos.account_type` 查询后返回真实普通用户记录???
- 验证 `/api/admin/exportUsers` 收到非严??? `account_type` 时返??? `ResponseCode::VALIDATION_FAILED`，不输出当前筛???条件下??? CSV銆?
### 本次变更文件
- `tests/Feature/AdminUserListExportAccountTypeValidationClosureModuleTest.php`
  - 新增后台普???用户列表和导出两个入口的非严格 `account_type` 筛???被拒绝样例，并清理测试专用 `user_infos` 涓? `user_logins` 数据???
- `app/Http/Controllers/Admin/AdminUserController.php`
  - `userList` 鍜? `exportUsers` 在构??? `user_infos` 查询前校??? `account_type`锛岄??过后才允许 `filteredUserQuery()` 转换并拼??? `user_infos.account_type` 查询条件???
### TDD 执行记录
- RED锛歚php vendor\phpunit\phpunit\phpunit tests\Feature\AdminUserListExportAccountTypeValidationClosureModuleTest.php --colors=never` 首次运行失败，命中用户列表返回成功码 `ResponseCode::SUCCESS`、导出入口返??? `StreamedResponse`，最终清单也缺少??? 332 节???
- GREEN：补齐后台普通用户列???/导出 `account_type` 前置校验和第 332 节清单后，目标测试??氳繃銆?
### 当前证据
- `AdminUserListExportAccountTypeValidationClosureModuleTest` 覆盖真实 `admins`、测试专??? `user_infos` 涓? `user_logins` 表记录??佸悗鍙? admin guard 登录态以??? `/api/admin/userList`銆乣/api/admin/exportUsers` 两个入口???
- 非严格筛选??? `account_type=2abc` 返回 `ResponseCode::VALIDATION_FAILED`銆?
- 列表响应不包含测试用户姓名，导出入口不再输出 CSV锛岄伩鍏? `user_infos.account_type` 在参数校验前进入查询链路造成错误语义或潜在误命中???
### 剩余边界
- 本轮没有改动用户详情、资料更新???账号启停???实名认证审核???用户列表统计口径???用户页面???权限字典???权限迁移或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 333. 2026-07-10 后台用户组兼容列表数字筛选严格校验闭???
### 本次处理目标
- 涓? `UserGroupController::index` 补齐旧用户组兼容列表 `group_type` 涓? `is_enabled` 数字筛???严格校验测试???
- 验证 `/api/admin/userGroupList` 不能??? `group_type=2abc` 鍦? PHP 灞? `(int)` 强转后交??? `group_configs.category` 查询???
- 验证 `/api/admin/userGroupList` 不能??? `is_enabled=1abc` 鍦? PHP 灞? `(int)` 强转后交??? `group_configs.is_enabled` 查询???
### 本次变更文件
- `tests/Feature/AdminUserGroupListNumericFilterValidationClosureModuleTest.php`
  - 新增用户组兼容列表非严格 `group_type` 涓? `is_enabled` 筛???被拒绝样例，不依赖真实数据库夹具，约束无效筛???必须在查询前返回参数错误???
- `app/Http/Controllers/Admin/UserGroupController.php`
  - `index` 在读??? `group_configs` 前校??? `group_type` 涓? `is_enabled`锛岄??过后才允许列表筛???转换并拼接 `group_configs.category`銆乣group_configs.is_enabled` 查询条件???
### TDD 执行记录
- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit --filter AdminUserGroupListNumericFilterValidationClosureModuleTest --colors=never` 首次运行失败，命中无效筛选仍进入 `group_configs` 查询，且当前 MySQL 连接不可用时暴露为查询异常；???终清单也缺少??? 333 节???
- GREEN：补齐用户组兼容列表数字筛???前置校验和??? 333 节清单后，目标测试??氳繃銆?
### 当前证据
- `AdminUserGroupListNumericFilterValidationClosureModuleTest` 覆盖 `UserGroupController::index` 直接调用入口??? `/api/admin/userGroupList` 兼容路径语义???
- 非严格筛选??? `group_type=2abc` 涓? `is_enabled=1abc` 均返??? `ResponseCode::VALIDATION_FAILED`銆?
- 无效筛???响应不再触??? `group_configs` 查询，避??? `group_configs.category` 涓? `group_configs.is_enabled` 在参数校验前被整数强转命中真实用户组配置???
### 剩余边界
- 本轮没有改动用户组创建???更新???删除???默认组唯一性???组别配置页面???权限字典???权限迁移或数据库结构???
- 当前 MySQL `127.0.0.1:3307` 拒绝连接，无法运行依赖真??? `group_configs` 夹具??? `AdminUserGroupCompatibilityTest`；数据库恢复后需补跑该兼容回归???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 334. 2026-07-10 后台未入金客??? min_days 筛???严格校验闭???
### 本次处理目标
- 涓? `FundFlowController::neverDepositUserList` 补齐未入金客户列??? `min_days` 数字筛???严格校验测试???
- 验证 `/api/admin/neverDepositUserList` 不能??? `min_days=1abc` 鍦? PHP 灞? `(int)` 强转??? `1` 后继续筛??? `user_infos.created_at`銆?
- 验证非严格或负数 `min_days` 在构造数据库查询前返??? `ResponseCode::VALIDATION_FAILED`銆?
### 本次变更文件
- `tests/Feature/AdminNeverDepositUserListMinDaysValidationClosureModuleTest.php`
  - 改为直接调用控制器的无数据库夹具测试，约束无??? `min_days` 必须在查询前返回参数错误，并修正清单编号为第 334 项???
- `app/Http/Controllers/Admin/FundFlowController.php`
  - `neverDepositUserList` 在构??? `user_infos` 查询前调??? `validateMinDaysFilter()`锛屼娇鐢? `integer|min:0` 校验，???过后才允许 `applyNeverDepositUserFilters()` 转换并拼接注册时间条件???
### TDD 执行记录
- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit --filter AdminNeverDepositUserListMinDaysValidationClosureModuleTest --colors=never` 首次业务运行失败，`min_days=1abc` 返回成功??? `ResponseCode::SUCCESS`，最终清单也缺少??? 334 节???
- GREEN锛氳ˉ榻? `min_days` 前置校验和第 334 节清单后，目标测试??氳繃銆?
### 当前证据
- `AdminNeverDepositUserListMinDaysValidationClosureModuleTest` 覆盖 `FundFlowController::neverDepositUserList` 直接调用入口??? `/api/admin/neverDepositUserList` 路径语义???
- 非严格筛选??? `min_days=1abc` 返回 `ResponseCode::VALIDATION_FAILED`，且无效筛???不再触??? `user_infos` 查询???
- 当前测试不依??? MySQL 夹具，可??? `127.0.0.1:3307` 不可用时持续验证前置校验边界???
### 剩余边界
- 本轮没有改动未入金判定状态???用户数据范围???列表分页???页面???权限字典???权限迁移或数据库结构???
- 依赖真实未入金客户数据的完整模块回归仍需??? MySQL 恢复后补跑???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 335. 2026-07-10 后台仓位清零记录 status 筛???严格校验闭???
### 本次处理目标
- 涓? `AdminWhsExpZeroController::recordList` 补齐清零记录 `status` 数字枚举筛???严格校验测试???
- 验证 `/api/admin/whsExpZeroRecords` 不能??? `status=1abc` 交给 `whs_exp_zeros.status` 查询并返回真实待处理记录???
- 验证清零记录状???只允许 `1=待处理`、`2=已完成`、`3=失败`，其它???在构???查询前返回 `ResponseCode::VALIDATION_FAILED`銆?
### 本次变更文件
- `tests/Feature/AdminWhsExpZeroStatusFilterValidationClosureModuleTest.php`
  - 新增控制器直调的无数据库夹具测试，约束非严格 `status` 必须在查询前返回参数错误???
- `app/Http/Controllers/Admin/AdminWhsExpZeroController.php`
  - `recordList` 在构??? `whs_exp_zeros` 查询前调??? `validateRecordStatus()`锛屼娇鐢? `integer|in:1,2,3` 校验状???筛选???
### TDD 执行记录
- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit --filter AdminWhsExpZeroStatusFilterValidationClosureModuleTest --colors=never` 首次运行失败，`status=1abc` 返回成功??? `ResponseCode::SUCCESS`，最终清单也缺少??? 335 节???
- GREEN：补齐清零记录状态前置校验和??? 335 节清单后，目标测试??氳繃銆?
### 当前证据
- `AdminWhsExpZeroStatusFilterValidationClosureModuleTest` 覆盖 `AdminWhsExpZeroController::recordList` 直接调用入口??? `/api/admin/whsExpZeroRecords` 路径语义???
- 非严格筛选??? `status=1abc` 返回 `ResponseCode::VALIDATION_FAILED`，无效状态不再进??? `whs_exp_zeros` 查询???
- 当前测试不依??? MySQL 夹具，可在数据库不可用时持续验证前置校验边界???
### 剩余边界
- 本轮没有改动清零候???识别???一键清零写入???状态处理流程???页面???权限字典???权限迁移或数据库结构???
- 依赖真实 `whs_exp_zeros` 记录的完整业务回归仍???鍦? MySQL 恢复后补跑???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 336. 2026-07-10 后台出金列表 status 筛???严格校验闭???
### 本次处理目标
- 涓? `WithdrawController::index` 补齐出金申请列表 `status` 数字枚举筛???严格校验测试???
- 验证 `/api/admin/withdrawList` 不能??? `status=1abc` 交给 `withdraw_records.status` 查询并返回真实处理中记录???
- 验证出金状???只允许 `0=待处理`、`1=处理中`、`2=已完成`、`3=已拒绝或失败`，非严格和越界???在构???查询前返回 `ResponseCode::VALIDATION_FAILED`銆?
### 本次变更文件
- `tests/Feature/AdminWithdrawListStatusFilterValidationClosureModuleTest.php`
  - 新增控制器直调的无数据库夹具测试，覆??? `status=1abc` 和越界??? `status=4`銆?
- `app/Http/Controllers/Admin/WithdrawController.php`
  - `index` 在构??? `withdraw_records` 查询前调??? `validateStatusFilter()`锛屼娇鐢? `integer|in:0,1,2,3` 校验状???筛选???
### TDD 执行记录
- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit --filter AdminWithdrawListStatusFilterValidationClosureModuleTest --colors=never` 首次运行失败，非法状态返回成功码 `ResponseCode::SUCCESS`，最终清单也缺少??? 336 节???
- GREEN：补齐出金列表状态前置校验和??? 336 节清单后，目标测试??氳繃銆?
### 当前证据
- `AdminWithdrawListStatusFilterValidationClosureModuleTest` 覆盖 `WithdrawController::index` 直接调用入口??? `/api/admin/withdrawList` 路径语义???
- 非严格筛选??? `status=1abc` 与越界??? `status=4` 均返??? `ResponseCode::VALIDATION_FAILED`，无效状态不再进??? `withdraw_records` 查询???
- 当前测试不依??? MySQL 夹具，可在数据库不可用时持续验证前置校验边界???
### 剩余边界
- 本轮没有改动出金详情、处理中/完成/拒绝动作、数据范围???页面???权限字典???权限迁移或数据库结构???
- 依赖真实 `withdraw_records` 记录的完整业务回归仍???鍦? MySQL 恢复后补跑???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 337. 2026-07-10 后台入金列表状???映射与严格校验闭环
### 本次处理目标
- 涓? `DepositController::index` 补齐入金列表 `status` 筛???的兼容映射与严格白名单校验???
- 验证旧后??? Blade 页面提交??? `0/1/2` 能分别映射到真实 `deposit_records.status` 鐨? `01/02/09`銆?
- 验证新后台配置直接提??? `01/02/09` 时保持原状???语义，非严格或不支持的状???在构???查询前返回 `ResponseCode::VALIDATION_FAILED`銆?
### 本次变更文件
- `tests/Feature/AdminDepositListStatusFilterValidationClosureModuleTest.php`
  - 新增真实数据库夹具测试，覆盖旧页面状态??笺??数据库原始状??佸?笺??非严格值与越界值，并清理测试专用入金记录???
- `app/Http/Controllers/Admin/DepositController.php`
  - `index` 在构??? `deposit_records` 查询前调??? `validateAndNormalizeStatusFilter()`锛岀粺涓?校验并归???化状态后再拼接查询条件???
### TDD 执行记录
- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit tests\Feature\AdminDepositListStatusFilterValidationClosureModuleTest.php --colors=never` 首次运行出现 3 个预期失败：`status=0` 未命??? `01` 待处理记录??乣status=1abc` 返回成功码???最终清单缺少第 337 节???
- GREEN：补齐状态白名单、兼容映射和??? 337 节清单后，旧页面值与数据库原始??肩粺涓?命中 `01/02/09`，非法???在查询前被拒绝???
### 当前证据
- `AdminDepositListStatusFilterValidationClosureModuleTest` 覆盖真实 `admins`、测试专??? `deposit_records` 表记录??佸悗鍙? admin guard 登录态以??? `/api/admin/depositList` 入口???
- `0/1/2` 涓? `01/02/09` 两套输入均归???化为数据库真实状??? `01/02/09`，且每次筛???只返回对应状???记录???
- 非严格??? `1abc` 和不支持??? `03`銆乣9`銆乣-1` 均返??? `ResponseCode::VALIDATION_FAILED`，无效状态不再进入入金列表查询???
### 剩余边界
- 本轮没有改动入金详情、审核???过、审核驳回???数据范围???页面???项、权限字典???权限迁移或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 338. 2026-07-11 后台注销申请列表 status 筛???严格校验闭???
### 本次处理目标
- 涓? `CancelApplyController::index` 补齐注销申请列表 `status` 数字枚举筛???严格校验测试???
- 验证 `/api/admin/cancelApplyList` 不能??? `status=1abc` 交给 `cancel_applies.status` 查询并返回真实已通过记录???
- 验证注销申请状???只允许 `-1=已拒绝`、`0=待处理`、`1=已???过`，非严格和越界???在构???查询前返回 `ResponseCode::VALIDATION_FAILED`銆?
### 本次变更文件
- `tests/Feature/AdminCancelApplyListStatusFilterValidationClosureModuleTest.php`
  - 新增控制器直调的无数据库夹具测试，覆??? `status=1abc`銆乣status=2` 鍜? `status=-2`銆?
- `app/Http/Controllers/Admin/CancelApplyController.php`
  - `index` 在构??? `cancel_applies` 查询前调??? `validateStatusFilter()`锛屼娇鐢? `integer|in:-1,0,1` 校验状???筛选???
### TDD 执行记录
- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit --filter AdminCancelApplyListStatusFilterValidationClosureModuleTest --colors=never` 首次运行失败，非法状态返回成功码 `ResponseCode::SUCCESS`，最终清单也缺少??? 338 节???
- GREEN：补齐注???申请列表状???前置校验和??? 338 节清单后，目标测试??氳繃銆?
### 当前证据
- `AdminCancelApplyListStatusFilterValidationClosureModuleTest` 覆盖 `CancelApplyController::index` 直接调用入口??? `/api/admin/cancelApplyList` 路径语义???
- 非严格筛选??? `status=1abc` 与越界??? `status=2`銆乣status=-2` 均返??? `ResponseCode::VALIDATION_FAILED`，无效状态不再进??? `cancel_applies` 查询???
- 当前测试不依??? MySQL 夹具，可在数据库不可用时持续验证前置校验边界???
### 剩余边界
- 本轮没有改动注销申请通过/拒绝动作、用户软删流程???操作日志???页面???权限字典???权限迁移或数据库结构???
- 依赖真实 `cancel_applies` 记录的完整业务回归仍???鍦? MySQL 恢复后补跑???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 339. 2026-07-11 后台凭证审核列表 review_status 筛???严格校验闭???
### 本次处理目标
- 涓? `VoucherController::index` 补齐凭证审核列表 `review_status` 数字枚举筛???严格校验测试???
- 验证 `/api/admin/voucherList` 不能??? `review_status=1abc` 交给 `voucher_infos.review_status` 查询并返回真实已审核记录???
- 验证凭证审核状???只允许 `0=待审核`、`1=审核通过`銆乣2=审核拒绝`，非严格和越界???在构???查询前返回 `ResponseCode::VALIDATION_FAILED`銆?
### 本次变更文件
- `tests/Feature/AdminVoucherListReviewStatusFilterValidationClosureModuleTest.php`
  - 新增控制器直调的无数据库夹具测试，覆??? `review_status=1abc`銆乣review_status=3` 鍜? `review_status=-1`銆?
- `app/Http/Controllers/Admin/VoucherController.php`
  - `index` 在构??? `voucher_infos` 查询前调??? `validateReviewStatusFilter()`锛屼娇鐢? `integer|in:0,1,2` 校验审核状???筛选???
### TDD 执行记录
- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit tests\Feature\AdminVoucherListReviewStatusFilterValidationClosureModuleTest.php --colors=never` 首次运行失败，非法审核状态返回成功码 `ResponseCode::SUCCESS`，最终清单也缺少??? 339 节???
- GREEN：补齐凭证审核列表状态前置校验和??? 339 节清单后，目标测试??氳繃銆?
### 当前证据
- `AdminVoucherListReviewStatusFilterValidationClosureModuleTest` 覆盖 `VoucherController::index` 直接调用入口??? `/api/admin/voucherList` 路径语义???
- 非严格筛选??? `review_status=1abc` 与越界??? `review_status=3`銆乣review_status=-1` 均返??? `ResponseCode::VALIDATION_FAILED`，无效审核状态不再进??? `voucher_infos` 查询???
- 当前测试不依??? MySQL 夹具，可在数据库不可用时持续验证前置校验边界???
### 剩余边界
- 本轮没有改动凭证审核通过、审核拒绝???拒绝原因保存???页面???权限字典???权限迁移或数据库结构???
- 依赖真实 `voucher_infos` 记录的完整业务回归仍???鍦? MySQL 恢复后补跑???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 340. 2026-07-11 前台找回密码身份、验证码、邮件与 MT4 同步闭环

### 本次处理目标
- 闭合 `/api/front/auth/password/email-code`銆乣/api/front/auth/password/reset` 与旧兼容 `user/check_user_info`銆乣user/forgetpswSendCode`銆乣user/forgetPasswordInfoVerification`銆乣user/change_password` 的完整找回密码链路???
- 修复 `ForgotPasswordController::saveChangePassword` 在验证码为空时跳过校验???仅凭目??? `userId` 和新密码即可重置任意账号的高危漏洞???
- 修复 `userId=真实IDabc` 鍦? PHP 整数强转或数据库数字前缀规则下命中真实账号的问题???
- 恢复旧项目先同步 MT4 密码、成功后再更新本地密码的业务边界；MT4 启用且同步失败时不更新本地密码???不消费验证码???

### 本次变更文件
- `tests/Feature/FrontForgotPasswordSecurityClosureModuleTest.php`
  - 新增真实 `user_logins` 夹具测试，覆盖空验证码???数字前??? ID、旧 `userverfcode`、确认密码???禁用账号???发送阶??? ID/邮箱绑定、现代接口???验证阶段归属和 MT4 失败回滚???
- `app/Http/Controllers/Front/ForgotPasswordController.php`
  - `checkUserInfo`銆乣sendResetCode`銆乣forgetPasswordInfoVerification` 鍜? `saveChangePassword` 在查询前严格校验旧用??? ID銆?
  - `front_reset_code:{email}` 从单???字符串改为绑??? `user_id`、标准化 `email`銆乣code` 的结构化缓存???
  - 发???阶段增??? 60 秒邮???/IP 限流，邮件成功后才写入验证码缓存???
  - `saveChangePassword` 兼容旧表??? `userverfcode`銆乣againpassword`，要求验证码、用户???邮箱和确认密码全部???致???
  - `resetPassword` 与旧入口统一读取绑定缓存；成功改密后删除验证码???
  - `mt4.enabled=true` 时???过 `Mt4ManagerApi::changePassword` 同步 MT4；失败返??? `ResponseCode::MT4_SYNC_FAILED` 或旧 `neterr`，不写本地密码???
- `app/Mail/FrontResetPasswordCode.php`
  - 新增可测试的找回密码验证??? Mailable，替代无法断???发???结果的裸邮件调用???
- `resources/views/emails/front-reset-password-code.blade.php`
  - 新增找回密码验证码纯文本邮件模板???
- `resources/lang/zh-CN/auth.php`銆乣resources/lang/en/auth.php`
  - 新增找回密码验证码邮件标题和正文多语???键???

### TDD 执行记录
- RED：首次运??? `FrontForgotPasswordSecurityClosureModuleTest` 涓? `4 failures / 5 tests`，空验证码???数字前??? ID、确认密码不???致和禁用账号均错误返??? `SUC`銆?
- RED：补充发送与验证阶段测试后为 `8 failures / 9 tests`，证实当前验证码缓存是字符串、未发???可测试邮件、ID/邮箱不绑定，结构化缓存还触发数组转字符串 500銆?
- RED锛氳ˉ鍏? MT4 失败回滚测试后，当前代码仍返??? `SUC` 并更新本地密码???
- GREEN：补齐结构化验证码???邮件??佷弗鏍? ID、确认密码???账号状态和 MT4 同步逻辑后，目标测试通过???

### 当前证据
- `FrontForgotPasswordSecurityClosureModuleTest` 当前覆盖 11 个测试场景；核心业务测试在写入本节前??? `OK (10 tests, 49 assertions)`銆?
- 缺失验证码???错误验证码、非严格 ID、确认密码不???致和禁用账号均不能修??? `user_logins.password`銆?
- 旧页面现有字??? `userverfcode` 涓? `againpassword` 可正常完成重置，成功??? `front_reset_code:{email}` 被删除，验证码不可重复使用???
- 旧发送入口只有在严格用户 ID、邮箱和启用状???一致时才发??? `FrontResetPasswordCode`；现代发送入口无???用户 ID，但缓存仍绑定数据库真实用户???
- MT4 关闭表示明确的本地模式；MT4 寮?启时外部同步失败会保留本地旧密码和验证码，便于用户重试???

### 剩余边界
- 本轮没有改动已登录用户主动改密???大代理改密、后台管理员重置用户密码、邮件服务器配置??? MT4 Socket 协议???
- 后续继续按旧项目模块清单审计普???用户其它公???身份入口，以及已登录用户主动改密??? MT4 同步???致??с??

## 341. 2026-07-11 前台已登录主动改??? MT4、本地密码与会话失效闭环

### 本次处理目标
- 统一 `/api/front/profile/password`銆乣user/editpsw_save`銆乣user/agents/editpsw_save` 以及找回密码入口??? MT4 与本地密码写入顺序???
- 修复 Profile 新旧主动改密入口仅更新本地哈希??丮T4 同步失败仍返回成功的问题???
- 改密成功后使当前 `jwt_token` 失效、???鍑? `user` guard，并在旧 web 会话存在时删??? `suser`，避免旧登录态继续访问???

### 本次变更文件
- `app/Services/UserPasswordService.php`
  - 新增唯一密码写入入口 `change(UserLogin $login, string $newPassword): bool`銆?
  - `mt4.enabled=false` 时进入明确本地模式；启用时仅??? `Mt4ManagerApi::changePassword` 返回 `status=ok` 后写入本??? Hash，失败保留旧哈希???
- `app/Http/Controllers/Front/ProfileController.php`
  - 现代与旧主动改密入口统一调用 `UserPasswordService`，分别保??? `ResponseCode::MT4_SYNC_FAILED` 与旧 `msg=FAIL, err=neterr` 协议???
  - 成功后统???失效请求属??т腑鐨? `jwt_token`銆侀??鍑? `user` guard；仅对实际挂??? session 的旧 web 请求删除 `suser`锛孉PI 请求不会因缺??? session store 返回 500銆?
- `app/Http/Controllers/Front/AuthController.php`
  - 移除直接更新密码哈希的重复路径，改用 `UserPasswordService` 并保留原 JWT 失效响应链???
- `app/Http/Controllers/Front/ForgotPasswordController.php`
  - 移除重复??? `syncMt4Password()` 与直??? Hash 写入，现代和旧找回密码入口复用同???服务；验证码只在完整改密成功后消费???
- `tests/Feature/UserPasswordServiceTest.php`
  - 覆盖本地模式、MT4 成功后写本地、MT4 失败保留旧密码三个服务边界???
- `tests/Feature/FrontProfilePasswordOwnerBoundaryClosureModuleTest.php`
  - 覆盖伪???目标用??? ID 不越权???现代和旧入??? MT4 失败回滚、JWT 黑名单??丼SO 清理、guard 登出??? `suser` 删除???
- `tests/Feature/FrontProfileRelationshipScopeFallbackModuleTest.php`
  - 手工构??? Profile Controller 改为容器解析，匹配生产依赖注入???

### TDD 执行记录
- RED锛歚UserPasswordServiceTest` 首次运行??? `3 failures / 3 tests`，均明确报告服务缺失???
- GREEN：新增服务后??? `OK (3 tests, 12 assertions)`銆?
- RED锛歅rofile 的现??? MT4 失败入口仍返??? UPDATED，旧入口仍返??? SUCCESS；两个用例均证明本地直写绕过 MT4銆?
- GREEN锛歅rofile 切换统一服务后，MT4 失败测试??? `OK (2 tests, 10 assertions)`銆?
- RED：会话测试证明现??? token 未进入黑名单，旧入口成功??? `suser` 仍存在；首次实现还暴??? API 请求没有 session store 鐨? 500 边界???
- GREEN：按 API/web 会话载体差异处理后，会话失效测试??? `OK (2 tests, 9 assertions)`銆?

### 当前证据
- `FrontProfilePasswordOwnerBoundaryClosureModuleTest`锛歚OK (7 tests, 37 assertions)`；写入本节前额外加入??? 341 节文档契约???
- `FrontForgotPasswordSecurityClosureModuleTest`锛歚OK (11 tests, 55 assertions)`銆?
- `FrontProfileControllerCommentReadabilityTest`锛歚OK (2 tests, 51 assertions)`銆?
- `FrontForgotPasswordControllerCommentReadabilityTest`锛歚OK (3 tests, 37 assertions)`銆?
- `FrontProfileRelationshipScopeFallbackModuleTest`锛歚OK (2 tests, 6 assertions)`銆?
- `FrontUiRegressionTest`锛歚OK (137 tests, 3089 assertions)`銆?
- `UserPasswordService.php`銆丳rofile銆丄uth銆丗orgot 四个 PHP 文件均???过 `php -l`銆?

### 剩余边界
- 本节已完成普通用户主动改密的密码???致???和会话失效闭环，但不代表普通用户???代理商、后台管理员三端逐路由审计已经全部完成???
- 后续继续审计普??氱敤鎴? Account銆丏eposit銆丏ashboard銆丟ift 与公??? Auth/Register 输入边界，再进入代理商和后台管理员模块???

## 342. 2026-07-11 前台账户类型与凭证审核状态严格输入闭???

### 本次处理目标
- 对照??? `UserCenterController::change_account_save`銆乣UserVoucherController::voucherSearch` 与新 `AccountController` 的实际路由执行链???
- 阻止 `review_status=1abc` 被数据库数字前缀规则命中真实 `voucher_infos.review_status=1` 记录???
- 阻止 `is_enc/is_ecn=1abc` 琚? PHP `(int)` 强转??? ECN 状???，以及越界值写??? `user_infos.is_ecn`銆?

### 路由执行???
- `GET /api/front/account/vouchers` 鎴? `POST user/voucher/voucherSearch` 鈫? 当前 user guard/鏃? `suser` 解析 鈫? `AccountController::voucherList` 鈫? `review_status` 鐨? `integer|in:0,1,2` 前置校验 鈫? 仅查询当前用??? `voucher_infos` 鈫? 日期过滤、分页与旧字段映??? 鈫? JSON 响应???
- `POST user/change_account_save` 鈫? 当前 user guard/鏃? `suser` 解析 鈫? `AccountController::changeAccountSave` 鈫? `is_enc/is_ecn` 归一??? 鈫? `required|integer|in:0,1` 前置校验 鈫? 当前用户未平仓检??? 鈫? 更新当前 `user_infos.is_ecn/leverage` 鈫? 鏃? `msg/err/col` 响应???

### 本次变更文件
- `app/Http/Controllers/Front/AccountController.php`
  - `voucherList` 在构造查询前校验审核状??侊紝鍙厑璁? 0銆?1銆?2銆?
  - `changeAccountSave` 在任何整数强转与持仓查询前校验账户类型，只允??? 0銆?1；非法???返回旧页面可识别的 `FAIL/UPDATEFAIL/is_enc`銆?
- `tests/Feature/FrontAccountVoucherOwnerBoundaryClosureModuleTest.php`
  - 新增 `1abc`銆?3銆?-1 三类审核状???被拒绝且不泄露真实凭证备注的测试???
- `tests/Feature/FrontAccountProfileOwnerBoundaryClosureModuleTest.php`
  - 新增 `1abc`銆?2銆?-1、空值四类账户类型被拒绝且本??? `is_ecn/leverage` 保持不变的测试???

### TDD 执行记录
- RED：凭证列表非法状态仍返回 `ResponseCode::SUCCESS`；账户类??? `1abc` 仍返??? `SUCCESS` 并可被强转写库???
- GREEN：增加查询前白名单校验后，`FrontAccountVoucherOwnerBoundaryClosureModuleTest` 涓? `OK (5 tests, 36 assertions)`锛宍FrontAccountProfileOwnerBoundaryClosureModuleTest` 涓? `OK (5 tests, 48 assertions)`銆?
- 回归：`FrontAccountControllerCommentReadabilityTest` 涓? `OK (2 tests, 32 assertions)`銆?

### 剩余边界
- 本节只闭合输入合法???和当前用户边界；旧项目账户类型切换还包含组别映射??丮T4 change-group/change-leverage、外部失败回滚和 ECN 鏈?低权益等深层链路，仍???继续逐项迁移验证???
- 凭证上传文件生命周期、软删除筛???和列表分页上限仍在后续 Account 审计范围内???

## 343. 2026-07-11 前台入金历史状???筛选与失败状???展示闭???

### 本次处理目标
- 对照 `/api/front/deposits/history`、旧入金历史调用??? `deposit_records.status` 当前 schema，闭合状态筛选和状???文案???
- 阻止 `status=01abc` 等非严格值进入数据库查询；只允许 schema 定义??? `01/02/05/09/10`銆?
- 修复真实失败状??? `09` 被前台显示为“未支付”的错误映射???

### 路由执行???
- `GET /api/front/deposits/history` 鈫? JWT/SSO 鈫? `DepositController::depositHistory` 鈫? 当前用户解析 鈫? status 严格白名??? 鈫? 当前用户 `deposit_records` 查询 鈫? 日期过滤与合??? 鈫? `FrontLegacyData::depositStatusText` 鈫? 分页 JSON銆?
- 旧页面兼??? records 调用复用同一 `depositHistory` 方法，因此使用相同状态语义和???有???边界???

### 本次变更文件
- `app/Http/Controllers/Front/DepositController.php`
  - 在构造历史查询前严格校验 status，只允许 `01/02/05/09/10`銆?
- `app/Support/FrontLegacyData.php`
  - `depositStatusText` 将数据库真实失败状??? `09` 映射??? `front.status_rejected`，同时保留历??? `03/3` 文本兼容???
- `tests/Feature/FrontDepositOwnerBoundaryClosureModuleTest.php`
  - 新增非严???/不支持状态不查询记录，以??? `09` 返回拒绝文案的真实路由测试???

### TDD 执行记录
- RED：非法状态仍返回 `ResponseCode::SUCCESS`锛沗status=09` 鐨? `status_text` 实际??? `Unpaid`銆?
- GREEN：严格白名单与状态映射完成后，目标测试为 `OK (6 tests, 40 assertions)`；写入本节前加入??? 343 节文档契约???
- 回归：`FrontDepositControllerCommentReadabilityTest` 涓? `OK (2 tests, 36 assertions)`銆?

### 剩余边界
- `05=閫?娆綻銆乣10=超时` 目前允许??? schema 筛???，但前台尚无独立???娆?/超时多语???文案，仍???结合旧页面产品语义后补齐???
- 入金提交的重复点击幂等???支付网关签名与回调幂等属于后续 Payment/Deposit 深链审计范围???

## 344. 2026-07-11 前台热点新闻分页参数严格校验闭环

### 本次处理目标
- 对照??? `LoginController::hotNews/hotNewsV2`、注册页热点新闻入口与新 `DashboardController`，闭合公???分页参数???
- 阻止 `page=1abc`銆乣limit=10abc` 琚? PHP 整数强转后继续查??? `news`銆?
- 阻止 page 小于 1銆乴imit 不在 1 鍒? 50 范围内的请求被静默夹值为合法分页???

### 路由执行???
- `POST user/main/hot/news` 鈫? `DashboardController::hotNews` 鈫? page `sometimes|integer|min:1` 鈫? published news 查询 鈫? 当前语言标题 鈫? 鏃? HTML 列表 JSON銆?
- `POST user/main/hot/newsV2` 鎴? `GET user/register/hotnews` 鈫? `DashboardController::hotNewsV2` 鈫? page/limit 前置校验 鈫? published news 分页 鈫? 当前语言标题与详情链??? 鈫? 旧表??? JSON銆?

### 本次变更文件
- `app/Http/Controllers/Front/DashboardController.php`
  - 两个公开新闻入口在数据库查询前校验分页；缺省 page=1銆乴imit=10 保持不变???
- `tests/Feature/FrontDashboardPaginationValidationClosureModuleTest.php`
  - 覆盖非严??? page/limit、零值???负值和 limit=51；无效请求必须返??? `ResponseCode::VALIDATION_FAILED`銆?

### TDD 执行记录
- RED：两个入口均把非法分页返回为 `code=0` 成功???
- GREEN：前置校验完成后，业务用例为 `OK (2 tests, 14 assertions)`銆?

### 剩余边界
- 新闻 locale 白名单???新闻详??? ID 严格校验??? `frontMsg` 实际消息数据迁移仍在 Dashboard/News 后续审计范围???

## 345. 2026-07-11 前台礼品地址默认筛???与礼品积分严格校验闭环

### 本次处理目标
- 对照现代 `/api/front/gift-addresses`銆乣/api/front/gifts` 与旧地址/礼品搜索入口，闭合数字筛选语义???
- 修复现代地址路由实际执行 `GiftController::addressSearch`锛岃?? `is_default` 校验仅写在未被该路由调用??? `addressList` 中的问题???
- 阻止 `is_default=1abc` 涓? `points_cost=100abc` 经强转后命中真实地址或礼品???

### 路由执行???
- `GET /api/front/gift-addresses` 鎴? `POST user/address/search` 鈫? 当前用户解析 鈫? `GiftController::addressSearch` 鈫? `is_default` 布尔前置校验与筛??? 鈫? 当前用户 `user_addresses` 分页 鈫? 旧字段映??? 鈫? JSON銆?
- `GET /api/front/gifts` 鈫? JWT/SSO 鈫? `GiftController::giftList` 鈫? `points_cost` 非负整数前置校验 鈫? 当前用户发货记录查询 + 可用 `gift_items` 查询 鈫? 组合 JSON銆?

### 本次变更文件
- `app/Http/Controllers/Front/GiftController.php`
  - `addressSearch` 增加真实路由???闇?鐨? `is_default` 校验与查询条件；`addressList` 保留同样校验以保证直接调用一致???
  - `giftList` 在任何发货或礼品查询前校??? `points_cost`銆?
- `tests/Feature/FrontGiftAddressOwnerBoundaryClosureModuleTest.php`
  - 覆盖非严格默认状态被拒绝，并证明合法 `is_default=1` 只返回默认地???銆?
- `tests/Feature/FrontGiftShipmentOwnerBoundaryClosureModuleTest.php`
  - 覆盖非严格积分成本不能返回真实可用礼品???

### TDD 执行记录
- RED：两个非严格筛???均返回 `ResponseCode::SUCCESS`锛涘悎娉? `is_default=1` 实际返回默认与普通两条地???銆?
- GREEN：真实执行方法补齐校验和筛??夊悗锛屽湴鍧?测试??? `OK (5 tests, 22 assertions)`锛岀ぜ鍝?/发货测试??? `OK (4 tests, 21 assertions)`銆?
- 回归：`FrontGiftControllerCommentReadabilityTest` 涓? `OK (2 tests, 29 assertions)`銆?

### 剩余边界
- 默认地址切换目前是两次数据库写操作，尚需验证并发/事务原子性???
- 地址空白字符串???电话号码格式???软删除默认地址降级与礼品兑换写链仍???继续审计???

## 346. 2026-07-11 前台默认收货地址不变量与事务引擎闭环

### 本次处理目标
- 恢复旧项??? `DEFAULT_ADDRESS_MUST_EXIST=1015` 业务语义：有地址的用户必须至少保留一个默认地???銆?
- 阻止第一条地???创建为非默认、唯???默认地址被取消或直接删除???
- 保证“清除旧默认 鈫? 写入新默认???失败时完整回滚，不留下零默认地???銆?

### 路由执行???
- `POST /api/front/gift-addresses` 或旧 `POST user/address/update` 新增分支 鈫? 地址字段校验 鈫? 当前用户解析 鈫? 第一条默认不变量 鈫? InnoDB 事务内清理旧默认并创??? 鈫? JSON銆?
- `PATCH /api/front/gift-addresses/{address}` 或旧编辑分支 鈫? 严格地址 ID/鎵?有??? 鈫? 唯一默认取消???鏌? 鈫? InnoDB 事务内切换默认并更新 鈫? JSON銆?
- `DELETE /api/front/gift-addresses/{address}` 鈫? 严格地址 ID/鎵?有??? 鈫? 唯一默认删除???鏌? 鈫? 软删??? 鈫? JSON銆?

### 本次变更文件
- `app/Constants/ResponseCode.php`
  - 增加旧兼容业务码 `DEFAULT_ADDRESS_MUST_EXIST=1015` 及多语言映射???
- `app/Http/Controllers/Front/GiftController.php`
  - 新增、更新???删除统???维护默认地址不变量；新增/更新默认地址使用 `DB::transaction()`銆?
- `resources/lang/zh-CN/response.php`銆乣resources/lang/zh_CN/response.php`銆乣resources/lang/en/response.php`
  - 增加默认地址必须保留的中英文响应???
- `database/migrations/2026_07_11_000001_convert_user_addresses_to_innodb.php`
  - 灏? `user_addresses` 从不支持事务回滚??? MyISAM 转换??? InnoDB銆?
- `tests/Feature/FrontGiftAddressOwnerBoundaryClosureModuleTest.php`
  - 覆盖第一条非默认被拒绝??佸敮涓?默认不能取消或删除???
- `tests/Feature/FrontGiftAddressTransactionClosureModuleTest.php`
  - 通过 Eloquent creating 事件注入创建异常，验证旧默认清零操作被真实数据库事务回滚???

### TDD 与运行证???
- RED锛氱涓?条非默认返回 CREATED锛涘敮涓?默认取消返回 SUCCESS銆?
- GREEN：业务不变量完成后两个目标用例为 `OK (2 tests, 8 assertions)`銆?
- 根因验证：加入应用事务后，故障注入仍发现旧默认变??? 0锛涙煡璇? `information_schema.TABLES` 确认 `user_addresses` 引擎??? `MyISAM`銆?
- 数据库修复：执行 `php artisan migrate --force`锛岃縼绉? `2026_07_11_000001_convert_user_addresses_to_innodb` 成功，运行时引擎确认??? `InnoDB`銆?
- GREEN：独立故障注入测试为 `OK (1 test, 3 assertions)`；完整地???边界测试??? `OK (7 tests, 33 assertions)`銆?

### 剩余边界
- 数据库层仍没有???每用户???多一??? is_default=1”的可移植唯???约束；当前由 InnoDB 事务和业务写入口维护，后续并发双请求还需锁行测试与数据库约束设计???

## 347. 2026-07-11 旧前台登??? user_id 严格校验闭环

### 本次处理目标
- 修复 `AuthController::legacySignIn` 将所有非邮箱账号 `(int)` 强转的问题???
- 阻止 `loginUid=真实IDabc`、`真实ID.9` 使用真实账号密码登录、创??? session/JWT 和登录日志???

### 路由执行???
- `POST user/signIn` 鎴? `POST user/index/signIn` 鈫? `AuthController::legacySignIn` 鈫? 必填校验 鈫? 邮箱格式或纯数字正整??? user_id 分类 鈫? 精确查询 `user_logins` 鈫? 状???/密码/业务资料 鈫? user guard + `suser` + JWT + 登录日志 鈫? 旧响应???

### 本次变更文件
- `app/Http/Controllers/Front/AuthController.php`
  - 非邮箱账号必须???过 `ctype_digit` 且大??? 0，之后才允许整数转换和数据库查询???
- `tests/Feature/FrontLegacyLoginUserIdValidationClosureModuleTest.php`
  - 真实账号夹具下提交数字前???和小数形式，断言 `loginStatus=401`、无 `suser`銆乬uard 未登录???无登录日志???

### TDD 执行记录
- RED锛歚412710100abc` 使用正确密码实际返回 `loginStatus=200`銆?
- GREEN：严格分类后目标用例??? `OK (1 test, 9 assertions)`銆?

### 剩余边界
- 旧登录图形验证码与登录频率限制尚未恢复；现代登录路由的限流也???与产品安全策略一起闭合???

## 348. 2026-07-11 旧前台注册载荷与验证码邮件兼容闭???

### 本次处理目标
- 对照旧注册页字段、`UserRegisterController` 调用和新 `AuthController`，恢复旧表单在不放宽现代 API 契约前提下的注册能力???
- 兼容旧图形验证码没有显式 `captcha_key` 的会话模式，并将旧注册字段归???到现代注册服务所???字段???
- 让旧 `registerSendCode` 入口使用可测试的专用 Mailable 发???真实六位邮箱验证码，同时保留缓存和频率限制链路???

### 路由执行???
- `GET user/register/captcha` 鈫? `AuthController::registerCaptcha` 鈫? 生成验证码文本与随机 key 鈫? 写入验证码缓??? 鈫? 灏? key 写入当前 session 鐨? `front_register_captcha_key` 鈫? 返回旧页面可直接展示??? SVG銆?
- `POST user/register/registerSendCode` 鈫? `AuthController::registerSendCode` 鈫? `AuthController::normalizedRegisterInput` 鈫? 旧字??? `register_type/useremail/modules/userphoneNo/userIdcardNo` 映射 鈫? 账户类型与邮箱校??? 鈫? 邮箱/IP 频率限制 鈫? 生成六位验证??? 鈫? 写入 `front_register_email_code_*` 缓存 鈫? `FrontRegistrationVerificationCode` 邮件发??? 鈫? 返回 `data.sent=true`銆?
- `POST user/register/registerinto` 鈫? `AuthController::register` 鈫? `AuthController::normalizedRegisterInput` 鈫? 仅在???测到旧字段时映射 `register_type/userInviterId/parent_id/agreeRule/sex`、补齐单密码确认字段??? session captcha key 鈫? 图形验证码校??? 鈫? 邮箱验证码校??? 鈫? 閭?请关系与注册业务校验 鈫? `UserRegistrationService::register` 鈫? 返回新旧页面都可识别的成功数据???

### 本次变更文件
- `app/Http/Controllers/Front/AuthController.php`
  - `registerCaptcha` 保存旧页面隐式验证码 key锛沗normalizedRegisterInput` 集中完成旧字段映射，现代请求未携带旧字段时不注入兼容默认值；`registerSendCode` 复用归一化结果并发???专用邮件???
- `app/Mail/FrontRegistrationVerificationCode.php`
  - 封装注册验证码邮件，使收件人、验证码和投递行为可以由 Laravel Mail fake 精确验证???
- `resources/views/emails/front-registration-verification-code.blade.php`
  - 提供???小???明确的六位注册验证码邮件正文???
- `tests/Feature/FrontLegacyRegisterPayloadCompatibilityClosureModuleTest.php`
  - 覆盖完整旧注??? payload 映射、旧发???验证码字段映射、缓存内容与实际 Mailable 投??掋??

### TDD 与运行证???
- RED：旧注册请求缺少现代 `account_type/agree_terms/password_confirmation/captcha_key` 时返回校验失败；旧发送验证码请求??? `register_type/useremail` 未映射???失败???
- GREEN：旧完整注册载荷测试通过，断???归一后的账户类型、邀请人、佣金模式??佹??别、电话???密码确认和???终用户数据；旧发送验证码测试??? `OK (1 test, 7 assertions)`銆?
- 文档契约：完整测试文件要求本节同时记??? `AuthController::normalizedRegisterInput`銆乣user/register/registerinto` 和测试类名称，防止实现与迁移清单脱节???

### 剩余边界
- 当前注册流程仍需继续闭合“所有业务校验和落库成功后才消费图形/邮箱验证码??濓紝閬垮厤閭?请关系或数据库失败迫使用户重新获取验证码???
- 注册异常响应已在??? 349 节改为捕获全??? `Throwable`、服务端记录诊断信息、客户端只接收稳定多语言业务消息；注册成功???知邮件仍需继续按旧项目语义核对???

## 349. 2026-07-11 前台注册验证码生命周期???异常与并发闭环

### 本次处理目标
- 将图形验证码和邮箱验证码从???校验时立即消费”改为???邮箱级互斥锁内校验，账号明确落库成功后统一消费”???
- 业务规则失败、注册服务异常???服务结果不完整时保留验证码，允许用户修正或安全重试???
- 账号已落库但 JWT 签发失败时不再伪装成可重复注册：验证码保持已消费，并明确返回 `registered=true`銆乣login_required=true`銆?
- 现代注册接口严格使用现代字段；旧字段别名仅在明确的旧路由上启用???

### 路由执行???
- `POST /api/front/auth/register` 鈫? `AuthController::register` 鈫? `AuthController::normalizedRegisterInput`（现代字段，不启用旧别名）→ Laravel 基础字段校验 鈫? 按规范化邮箱获取 120 绉? `Cache::lock` 鈫? 锁内读取并校验图???/邮箱验证??? 鈫? 注册前置业务校验 鈫? `UserRegistrationService::register` 数据库事??? 鈫? 严格确认 `success === true` 与合??? `UserLogin` 鈫? 消费双验证码 鈫? `JwtService::generateToken` 鈫? 成功响应 鈫? `finally` 释放锁???
- `POST user/register/registerinto` 鈫? `AuthController::register` 鈫? `normalizedRegisterInput` 鎸? `legacy_user_register_into` 路由启用旧字段映??? 鈫? 与现代入口共用相同基???校验、邮箱锁、验证码、注册事务???消费??丣WT 与释放链???
- 注册服务在落库前返回业务失败或抛??? `Throwable` 鈫? 服务端记录脱敏邮箱哈希和异常对象 鈫? 返回稳定业务/服务器错??? 鈫? 双验证码保留 鈫? `finally` 释放邮箱锁???
- 注册服务已明确成功并返回合法 `UserLogin`，但 JWT 签发抛出 `Throwable` 鈫? 双验证码已消??? 鈫? 返回 `response.registration_completed_login_required` 涓? `registered/login_required` 标记 鈫? `finally` 释放锁，客户端不得再次提交注册???
- 同邮箱并发请求（即使使用不同 `captcha_key`锛夆啋 只有第一个请求取得邮箱级锁；其余请求返回 `RATE_LIMITED`，不得进入注册服务???首请求成功消费验证码后，后续请求即使重新取得锁，也会在锁内因验证码不存在被拒绝???

### 并发与数据库???终防???
- 应用锁键??? `front_register_submit_lock_` 加规范化邮箱 SHA-1，不包含图形验证??? key，避免同邮箱通过不同验证码绕???互斥???
- 锁租期固定为 120 秒，覆盖注册事务的常规最长执行窗口；???有已获得锁的返回和异常路径均??? `finally` 释放???
- `user_logins.email` 鐨? `user_logins_email_unique` 唯一索引作为缓存锁过期???进程异常或极端慢请求下的最终写入防线???
- 运行??? `SHOW INDEX FROM user_logins` 已确认该索引 `Non_unique=0`銆乣Column_name=email`銆乣Index_type=BTREE`銆?

### 本次变更文件
- `app/Http/Controllers/Front/AuthController.php`
  - 将旧字段兼容改为路由身份判定；现代接口不再接受旧邮箱、姓名???电话???证件???验证码、佣金模式或密码确认别名???
  - 注册全链捕获 `Throwable` 并记录服务端诊断，客户端只接收稳定多语言消息???
  - 增加邮箱级注册锁、锁内验证码校验、严格服务成功结构检查???落库后验证码消费和 JWT 失败状???标记???
- `tests/Feature/FrontRegisterVerificationLifecycleClosureModuleTest.php`
  - 覆盖业务失败、服务异常???无效服务结果???缺失显式成功???成功消费??丣WT 失败已注册状态??佺浉鍚?/不同图形验证码的同邮箱互斥???提前返回释放锁、消费后重放和邮箱唯???索引契约???
- `tests/Feature/FrontLegacyRegisterPayloadCompatibilityClosureModuleTest.php`
  - 逐项覆盖现代路由拒绝???有旧字段别名，同时证明旧 `registerinto` 仍按旧表单契约工作???
- `tests/Feature/FrontAuthControllerLocalizationTest.php`
  - 邮件职责迁移后同时扫??? Controller銆丮ailable 和邮件模板，保证验证码主题和正文继续使用语言 key銆?
- `resources/lang/zh-CN/auth.php`銆乣resources/lang/zh_CN/auth.php`銆乣resources/lang/en/auth.php`
  - 增加账号已注册但???要重新登录的稳定提示???

### TDD、复核与运行证据
- 首轮 RED：业务校验失败会提前消费图形验证码；注册异常直接返回 `SQLSTATE` 原文???
- 第二??? RED锛歚validateRegistration` 鐨? `Error` 绌块?忋??服务缺失明确成功仍消费、现代路由混入旧字段可放宽契约???
- 并发 RED：同邮箱不同 `captcha_key` 使用不同锁键；验证码在锁外校验形成陈旧校验结果竞态???
- 鏈?缁? GREEN锛歚FrontRegisterVerificationLifecycleClosureModuleTest` 涓? `OK (14 tests, 90 assertions)`銆?
- 旧兼容与现代隔离：`FrontLegacyRegisterPayloadCompatibilityClosureModuleTest` 涓? `OK (12 tests, 62 assertions)`銆?
- 回归：`FrontAuthControllerCommentReadabilityTest` 涓? `OK (2 tests, 34 assertions)`锛沗FrontAuthControllerLocalizationTest` 涓? `OK (2 tests, 36 assertions)`锛沗ApiResponseLocalizationContractTest` 涓? `OK (2 tests, 245 assertions)`銆?
- 规格复核???终结果：Critical=0銆両mportant=0；代码质量复核最终结果：Critical=0、阻断??? Important=0銆?
- `AuthController.php`、生命周期测试和三套语言文件均???过 `php -l`銆?

### 剩余边界
- 注册锁租期需要结合生产实际???时监控持续校准；即使极端慢请求超过租期，数据库邮箱唯一索引仍阻止重复账号写入???
- 注册成功通知邮件是否需要恢复旧项目完整欢迎邮件内容，将在普通用户???知链???路由对照时继续核对；本节仅完成验证码邮件与注册状???安全闭环???

## 350. 2026-07-11 前台新闻软删除翻译与活跃翻译唯一性闭???

### 本次处理目标
- 阻止 `news_langs.deleted_at` 已标记删除的翻译重新出现??? Dashboard、热点新闻???新闻列表???搜索和详情???
- 当当前语???只存在已删除翻译时，稳定回??? `news` 主表标题与正文???
- 从数据库层保证同??? `news_id + lang_code` 鏈?多一条未删除翻译，避免无排序 `first()` 在多条活跃翻译间随机选择???
- 将旧??? `news/news_langs` 浠? MyISAM 转为 InnoDB，使新闻写入、迁移和测试事务真实生效???

### 路由执行???
- `GET /api/front/dashboard` 鈫? JWT/SSO 鈫? `DashboardController::dashboardData` 鈫? `News::published()` 鈫? `news_langs` 按新??? ID銆佽瑷?鍜? `deleted_at IS NULL` 查询 鈫? 活跃翻译优先、否则主表回??? 鈫? Dashboard JSON銆?
- `POST user/main/hot/news` 鈫? `DashboardController::hotNews` 鈫? `localizedNewsTitle` 鈫? 活跃翻译过滤 鈫? 鏃? HTML 列表响应???
- `POST user/main/hot/newsV2` / `GET user/register/hotnews` 鈫? `DashboardController::hotNewsV2` 鈫? `localizedNewsTitle` 鈫? 活跃翻译过滤 鈫? 表格行响应???
- `GET /api/front/news` 鈫? `NewsController::newsList` 鈫? `newsQuery` 的翻译标题搜索只读取未删除翻??? 鈫? `newsRow` 只读取未删除翻译 鈫? 现代分页响应???
- `POST user/newsListSearch` 鈫? `NewsController::newsListSearch` 鈫? 复用相同 `newsQuery/newsRow` 鈫? 已删除翻译标题不得命??? 鈫? 鏃? `rows/total` 响应???
- `GET user/news/news_detail/{newsId}` 鈫? `NewsController::newsDetail` 鈫? 已发布主新闻 鈫? 当前语言未删除翻??? 鈫? HTML；没有活跃翻译时使用主表标题和正文???

### 数据库不变量
- 迁移 `2026_07_11_000002_enforce_unique_active_news_translations` 先将 MySQL/MariaDB 鐨? `news`銆乣news_langs` 转为 InnoDB銆?
- 迁移前检查存量未删除翻译重复组；存在重复时明确中止，不静默覆盖业务内容???
- MySQL/MariaDB 增加生成??? `active_translation_key`：未删除记录生成 `news_id:lang_code`，已删除记录生成 `NULL`锛涘敮涓?索引只限制活跃翻译，允许保留多条软删除历史???
- SQLite/PostgreSQL 使用 `WHERE deleted_at IS NULL` 的部分唯???索引实现相同语义???
- 生成列和唯一索引分别???测???分别补???/删除，部署曾部分执行时可恢复???
- 运行时已确认两表引擎均为 `InnoDB`锛宍news_langs_active_translation_unique` 涓? `Non_unique=0` 鐨? BTREE 唯一索引???

### 本次变更文件
- `app/Http/Controllers/Front/DashboardController.php`
  - 两处原生 `news_langs` 读取增加 `whereNull('deleted_at')`銆?
- `app/Http/Controllers/Front/NewsController.php`
  - 详情翻译、翻译标题搜索???列表行翻译三处查询增加 `whereNull('deleted_at')`銆?
- `database/migrations/2026_07_11_000002_enforce_unique_active_news_translations.php`
  - 转换事务引擎并建立活跃翻译唯???约束；保留多数据库实现与部分执行恢复???
- `tests/Feature/FrontNewsTranslationSoftDeleteClosureModuleTest.php`
  - 使用 `DatabaseTransactions`、动态新???/用户 ID 和动态邮箱，避免共享数据库污染???
  - 覆盖仅软删除翻译回???銆佹椿璺?+删除并存、删除标题搜索不命中、详???/热点/Dashboard 回???和重复活跃翻译数据库拒绝???

### TDD、迁移与复核证据
- RED：新增聚焦测试首次运??? `4 tests / 4 failures`，列表???搜索??佽鎯?/热点、Dashboard 均真实返回已删除翻译???
- GREEN：五处查询补齐过滤后??? `OK (4 tests, 26 assertions)`銆?
- 结构 RED：数据库允许同新???/语言插入第二条未删除翻译，唯???约束测试未抛 `QueryException`銆?
- 根因验证：`information_schema.TABLES` 确认 `news` 涓? `news_langs` 原为 MyISAM，事务夹具无法回滚；清理本轮明确标记的测试残留后执行引擎转换与唯???约束迁移???
- 迁移执行：`2026_07_11_000002_enforce_unique_active_news_translations` 成功，最终目标测试为 `OK (5 tests, 27 assertions)`銆?
- 回归：`FrontNewsControllerCommentReadabilityTest` 涓? `OK (2 tests, 20 assertions)`锛沗FrontDashboardControllerCommentReadabilityTest` 涓? `OK (2 tests, 31 assertions)`銆?
- 鏈?终规格复核：Critical=0銆両mportant=0；最终质量复核：Critical=0銆両mportant=0銆?

### 剩余边界
- 迁移 `down()` 删除活跃翻译唯一索引和生成列，但不会??? InnoDB 恢复为未知的旧引擎；事务安全升级属于有意保留的不可???数据层改进???
- 新闻分页、日期输入???详情路由参数???旧路由登录权限??? `hotNewsV2` 历史响应契约仍在后续 News 子目标中继续闭合???

## 351. 2026-07-11 前台新闻分页、旧 rows 与日期输入闭???

### 本次处理目标
- 阻止新闻列表??? `1abc`、零、负数或超大分页值强???/夹取为合法查询???
- 恢复??? EasyUI `rows` 每页数量字段，并明确 `per_page > limit > rows > 15` 优先级???
- 对现代和旧日期别名???字段执行真??? `Y-m-d` 校验，拒绝不存在日期和结束日早于???始日???
- 鎵?有非法输入必须在 `newsQuery()` 前返回，旧接口同时保留表格所??? `rows/total` 结构???

### 路由执行???
- `GET /api/front/news` 鈫? `NewsController::newsList` 鈫? `page` 鐨? `integer|min:1` 涓? `per_page` 鐨? `integer|between:1,100` 鈫? 四日期别??? `date_format:Y-m-d` 鈫? 鎸? `date_from/date_to` 优先??? `startdate/enddate` 取得有效区间 鈫? 区间顺序校验 鈫? `newsQuery` 鈫? 分页与翻译映??? 鈫? 标准 JSON銆?
- `POST user/newsListSearch` 鈫? `NewsController::newsListSearch` 鈫? 严格校验 `page/per_page/limit/rows` 和四日期别名 鈫? 非法时返??? `code=VALIDATION_FAILED`銆佺浉鍚? `message/msg`銆乣rows=[]`銆乣total=0` 鈫? 合法时按 `per_page > limit > rows > 15` 取每页数 鈫? 显式 page 分页 鈫? 鏃? `rows/total` 响应???

### 本次变更文件
- `app/Http/Controllers/Front/NewsController.php`
  - 两个列表入口在任何新闻查询前执行 Validator 和日期范围校验???
  - 新增集中分页/日期规则和日期范??? helper，并记录现代日期字段优先级???
  - 旧入口直接使用严格校验后的分页???，不再调用会静默夹值的 `FrontLegacyData::perPage`銆?
- `tests/Feature/FrontNewsListInputValidationClosureModuleTest.php`
  - 覆盖现代/旧非法分页???四日期别名、真实不存在日期、???置日期范围、旧错误结构、`rows` 第二页和完整分页优先级???
  - 使用 InnoDB 事务与动态新??? ID，验证查询前校验和真实分页数据???

### TDD 与复核证???
- RED锛氭柊澧? 4 个目标测试首次运行有 3 个预期失败，分别证明现代非法输入仍成功???旧错误结构缺失、`rows` 未生效???
- GREEN：严格校验和旧分页映射完成后??? `OK (4 tests, 99 assertions)`銆?
- 回归：`FrontNewsTranslationSoftDeleteClosureModuleTest` 涓? `OK (5 tests, 27 assertions)`锛沗FrontNewsControllerCommentReadabilityTest` 涓? `OK (2 tests, 20 assertions)`銆?
- 运行时再次确??? `news` 表引擎为 InnoDB，事务测试不会遗留数据???
- 鏈?终规格复核：Critical=0銆両mportant=0；最终质量复核：Critical=0銆両mportant=0銆?

### 剩余边界
- 本节未改变旧项目“无日期时默??? 2024-01-01 至当天???和新项目???无日期时不过滤”的产品差异；当前保留现代全量查询语义，并在???终???路由报告中标记为有意差异???
- 新闻详情参数、直接详情页、旧登录权限和热点新闻历史响应契约继续进入后??? News 子目标???

## 352. 2026-07-11 前台新闻详情路由、直接打???与富文本安全闭环

### 本次处理目标
- 阻止旧新闻详情非数字参数进入 `int $newsId` 后产??? 500銆?
- 璁? `/front/news/detail/{newsId}` 真正定位并自动打???指定已发布新闻，而不是???化为普???列表第???页???
- 阻止非法现代详情路径被前??? catch-all 映射??? Dashboard 200銆?
- 鍑?化旧详情富文本，保留安全排版并移除存储型 XSS 可执行内容???

### 路由执行???
- `GET user/news/news_detail/{newsId}` 鈫? 路由 `whereNumber` 鈫? `NewsController::newsDetail` 鈫? `News::published()` 排除未发???/软删??? 鈫? 活跃翻译 鈫? `SafeHtml::sanitize` 正文 鈫? 鏃? HTML 详情；非数字、不存在、未发布、软删除均为 404銆?
- `GET /front/news/detail/{newsId}` 鈫? 路由 `whereNumber` 鈫? `NewsController::newsPage` 鈫? `News::published()->exists()` 鈫? Blade 传入 `news_id` 默认筛???和 `initialNewsId` 鈫? iframe 内页面请??? `/api/front/news?news_id={id}` 鈫? `newsQuery` 精确 ID 过滤后分??? 鈫? JS 首次找到对应行后标记并调??? `openNewsDetailModal` 涓?次???
- `GET /front/news/detail/{invalidNewsId}` 鈫? 数字详情路由未命??? 鈫? catch-all 前的明确非法详情路由 鈫? 404，不进入前台应用兜底???
- `GET /api/front/news?news_id={id}` 鈫? `news_id` 鐨? `integer|min:1` 校验 鈫? `News::published()` 精确过滤 鈫? 分页和翻译映射；非法 ID 返回 `VALIDATION_FAILED`銆?

### 富文本安全边???
- `SafeHtml` 使用 DOM 解析和标???/属???允许列表；移除 `script/style/iframe/object/embed/svg/math/form` 等可执行或高风险节点及其内容???
- 鎵?有未允许属???均移除，因??? `on*`銆乣style`銆乣srcset`銆乣formaction` 等不能进入响应???
- URL 先做 HTML 实体解码并移??? ASCII 控制/空白，再拒绝 `javascript:`銆乣vbscript:`銆乣data:`；图片只允许 HTTP銆丠TTPS 或相对路径???
- 未知标签先??掑綊鍑?化子节点再解包，安全文本与允许的富文本结构可以保留???
- `target=_blank` 链接自动??? `rel="noopener noreferrer"`銆?

### 本次变更文件
- `routes/web.php`
  - 旧详情增加数字约束；现代详情改由 Controller 校验；增??? catch-all 前非法详??? 404 路由???
- `app/Http/Controllers/Front/NewsController.php`
  - 新增 `newsPage`；列表规则和查询增加严格 `news_id`；旧详情使用 `SafeHtml`銆?
- `app/Support/SafeHtml.php`
  - 新增可复用的富文本允许列表净化器???
- `resources/front/layui/news/index.blade.php`
  - 将详??? ID 作为安全默认筛???和初始新闻 ID 传给通用模块页???
- `resources/front/layui/partials/module-page.blade.php`
  - 增加默认值为 0 鐨? `data-initial-news-id`，其他模块不受影响???
- `public/js/apps/front/layui/module-page.js`
  - 新闻时间线首次加载指定行后自动打???涓?次；只有真实找到行时才消费打???标记???
- `tests/Feature/FrontNewsDetailRouteClosureModuleTest.php`
  - 覆盖数字/非法路由、已发布/未发???/软删???/不存在???非首屏精确 ID、页面数据属性??丣S 单次打开顺序和无内联执行脚本???
- `tests/Feature/SafeHtmlSanitizerTest.php`
  - 覆盖直接与混淆协议???事件属性??丼VG/MathML、未知标签???允许链???/图片和畸??? HTML銆?

### TDD 与复核证???
- RED锛?4 个详情测试首次运行全部失败，分别证明旧非数字 500、现代页面未传初??? ID銆丄PI 未过???/校验 ID銆丣S 未实现自动打???銆?
- 安全 RED：旧详情真实返回 `<script>`銆乣onerror` 鍜? `javascript:`锛汮S 在找到行前提前标记已打开???
- GREEN锛歚FrontNewsDetailRouteClosureModuleTest` 涓? `OK (4 tests, 44 assertions)`锛沗SafeHtmlSanitizerTest` 涓? `OK (3 tests, 21 assertions)`銆?
- 回归：`FrontNewsListInputValidationClosureModuleTest` 涓? `OK (4 tests, 99 assertions)`锛沗FrontNewsTranslationSoftDeleteClosureModuleTest` 涓? `OK (5 tests, 27 assertions)`锛沗FrontLegacyRouteCompatibilityTest` 涓? `OK (14 tests, 1328 assertions)`锛沗FrontUiRegressionTest` 涓? `OK (137 tests, 3089 assertions)`銆?
- `NewsController.php`銆乣SafeHtml.php`銆乣routes/web.php` 通过 `php -l`锛宍module-page.js` 通过 `node --check`銆?
- 鏈?终规???/质量合并复核：Critical=0銆両mportant=0銆?

### 剩余边界
- 目前允许协议相对??? `//host/path` 链接和图片；它不构成脚本执行，但若产品要求只允许本站资源，可在后续内容安全策略中进一步收紧???
- 旧新闻路由登录权限和 `hotNewsV2`/注册热点新闻历史响应契约仍在下一子目标继续闭合???

## 353. 2026-07-11 旧前台新闻登录权限与热点响应契约闭环

### 本次处理目标
- 恢复??? `LoginMiddleware` 对消息???登录后热点、礼品提示???新闻列???/详情/搜索的登录门槛，同时保留 `user/main/hot/news` 的旧公开例外???
- 同时兼容 Laravel `user` guard 和旧 session `suser`，避免迁移后只有 JWT 用户能访问旧页面???
- 拆分登录??? `hotNewsV2` 与公???注册热点，不再用同一方法返回互相冲突的响应结构???
- 恢复??? `hotNewsV2` 鐨? `code=200`銆佸浐瀹? 10 条??乣lang_id` 鍜? `lang_name=zh-cn/en`銆?

### 路由执行???
- 受保护页??? `GET user/front/message`銆乣GET user/news_list_browse`銆乣GET user/news/news_detail/{newsId}` 鈫? `LegacyFrontAuthenticate` 鈫? user guard 鎴? `suser.user_id` 鈫? Controller；匿名请??? 302 鍒? `/user/login`銆?
- 受保??? AJAX `POST user/main/hot/newsV2`銆乣POST user/main/hasShowGiftTips`銆乣POST user/newsListSearch` 鈫? `LegacyFrontAuthenticate` 鈫? 已登录继续；匿名返回 `AUTH_FAILED`銆佺浉鍚? `message/msg`銆乣rows=[]`銆乣total=0`銆乣footer=[]`銆乣redirect=true` 和登??? URL銆?
- 公开 `POST user/main/hot/news` 鈫? 保留旧中间件例外 鈫? `DashboardController::hotNews` 鈫? `code=0` HTML 列表???
- 登录??? `POST user/main/hot/newsV2` 鈫? `page/lang_id` 严格校验 鈫? `lang_id=1` 使用 `zh-CN` 翻译并返??? `lang_name=zh-cn`锛宍lang_id=2` 使用英文并返??? `lang_name=en` 鈫? 固定每页 10 鏉? 鈫? `code=200`銆乣count/data/totalRow`銆?
- 公开 `GET user/register/hotnews` 鈫? `DashboardController::registerHotNews` 鈫? `page/limit/lang_id` 校验 鈫? 本地已发布新闻和活跃翻译 鈫? 顶层原始新闻数组，不再返??? `hotNewsV2` 表格包装???
- `POST user/main/hasShowGiftTips` 鈫? 鉴权中间??? 鈫? `legacyFrontUserId` 兼容 guard/session 鈫? 写入 `gift_tips_shown_{user_id}` 鈫? 成功响应；匿名不再假成功???

### 本次变更文件
- `app/Http/Middleware/LegacyFrontAuthenticate.php`
  - 新增旧前台登录中间件，统???识别 guard 涓? `suser`，分别返回页面重定向和旧 AJAX 兼容未授权结构???
- `app/Http/Kernel.php`
  - 注册 `legacy.front.auth` 路由中间件别名???
- `routes/web.php`
  - 六条旧受保护路由接入中间件；公开热点保持公开；注册热点改为独??? `registerHotNews`銆?
- `app/Http/Controllers/Front/DashboardController.php`
  - `hotNewsV2` 恢复旧码、语???和每页数量；新增公开注册热点适配；礼品提示使用统???旧用户解析???
- `tests/Feature/FrontLegacyNewsAuthenticationAndHotContractClosureModuleTest.php`
  - 覆盖匿名页面/AJAX、旧 session銆佺湡瀹? user guard、礼品缓存??佷腑鏂?/英文热点和注册原始数组???
- `tests/Feature/FrontNewsTranslationSoftDeleteClosureModuleTest.php`銆乣FrontNewsDetailRouteClosureModuleTest.php`銆乣FrontLegacyRouteCompatibilityTest.php`
  - 将受保护入口改为携带??? session，继续验证业务行为???不绕过新权限门槛???

### TDD 与复核证???
- RED锛氭柊澧? 4 个用例首次运行全部失败，证明匿名受保护页面仍 200銆乻ession 礼品提示未写缓存、`hotNewsV2` 浠? `code=0`、注册热点仍返回表格包装???
- GREEN：最终鉴权与热点契约??? `OK (5 tests, 63 assertions)`銆?
- 回归：`FrontNewsTranslationSoftDeleteClosureModuleTest` 涓? `OK (5 tests, 27 assertions)`锛沗FrontNewsDetailRouteClosureModuleTest` 涓? `OK (4 tests, 44 assertions)`锛沗FrontLegacyRouteCompatibilityTest` 涓? `OK (14 tests, 1328 assertions)`锛沗FrontDashboardPaginationValidationClosureModuleTest` 涓? `OK (3 tests, 20 assertions)`锛沗FrontDashboardControllerCommentReadabilityTest` 涓? `OK (2 tests, 31 assertions)`銆?
- `LegacyFrontAuthenticate.php`銆並ernel銆丏ashboardController銆乺outes 均???过 `php -l`銆?
- 鏈?终规???/质量复核：Critical=0銆両mportant=0銆?

### 剩余边界
- News/Dashboard 本轮审计发现的软删除翻译、分页日期???详情路由???直接详情页、登录权限和热点响应差异均已逐项闭合???
- 普???用户下???高风险目标转??? Deposit/Payment；真实???道配置、签名密钥和外部资金接口仍必须以安全 adapter 与可验证状???机实现，不能沿用当前???用回调假成功???

## 354. 2026-07-11 前台支付危险路由冻结与???用回调失败关闭

### 本次处理目标
- 禁止??? GET 请求创建入金订单???
- 将第三方异步通知与同??? return 鐨? HTTP 方法严格分离???
- 在??愰??道 adapter 和验签完成前，阻止任何???用回调按订单号直接写成功状态???
- 涓? web 中的第三??? POST 通知增加精确 CSRF 豁免，不使用宽泛通配符???

### 路由执行???
- `POST user/deposit_request` / `POST user/deposit_request_otc` 鈫? 旧入金提??? Controller锛汫ET 直接 405，不再产生订单???
- legacy notify锛圥ayflash/Trustpay/Tiger/WP/Exlink/BTB/PassTo/Switch/OTC/OTC withdraw锛夆啋 浠? POST 鈫? 精确 CSRF 豁免 鈫? `PaymentNotifyController::legacyCallback` 鈫? 鏃? URI 映射网关 鈫? `notify`銆?
- legacy return锛圵P/Exlink/BTB/default return锛夆啋 浠? GET 鈫? `legacyCallback` 鈫? `returnPage` 鈫? `/front/deposit?status=pending`锛涘閮? status 参数不会改变展示态或订单状??併??
- `POST /api/front/payment/notify/{gateway}` 鈫? 未知网关 404；已知旧网关或启用???道但缺 adapter/registry 422 鈫? 日志只记??? gateway銆乸ath、请求体哈希和原??? 鈫? 不查询???不更新 `deposit_records`銆?
- `GET /api/front/payment/return/{gateway}` 鈫? 纯页面返回，不具备支付成功证明能力???

### 本次变更文件
- `routes/web.php`
  - 旧建???/notify/return 浠? `GET|POST` 收紧为明??? POST 鎴? GET，并同步更新全部旧命??? alias 方法???
- `app/Http/Middleware/VerifyCsrfToken.php`
  - 仅列??? 12 个真实第三方 notify URI；不豁免建单??? return，不使用 `user/*` 鎴? `deposit_*`銆?
- `app/Http/Controllers/Front/PaymentNotifyController.php`
  - 删除??? `status=success` 和本地订单号直接??? `02`/Unix payment_time 的危险??昏緫銆?
  - 增加已知旧网关白名单、未??? 404、未配置 422 和脱敏拒绝日志；return 固定 pending銆?
- `tests/Feature/FrontPaymentRouteSafetyClosureModuleTest.php`
  - 覆盖 HTTP 方法、未???/未配置网关???订单状态不变??佺簿纭? CSRF 集合、日志无??? payload 鍜? return 不信??? status銆?
- `tests/Feature/FrontLegacyRouteCompatibilityTest.php`銆乣LegacyUiReplacementCoverageTest.php`銆乣FrontPaymentNotifyControllerCommentReadabilityTest.php`
  - 更新为显式安全方法???真实已发布新闻夹具和新的失败关闭说明???

### TDD 与复核证???
- RED：安全测试首??? `4 tests / 4 failures`锛岃瘉鏄? GET 可建单??丟ET 可???知、未知???知返回成功、无签名回调尝试写错??? DATETIME 骞? 500銆?
- GREEN锛歚FrontPaymentRouteSafetyClosureModuleTest` 涓? `OK (4 tests, 35 assertions)`銆?
- 回归：`FrontLegacyRouteCompatibilityTest` 涓? `OK (14 tests, 1325 assertions)`锛沗FrontendRouteManifestTest` 涓? `OK (21 tests, 76 assertions)`锛沗LegacyUiReplacementCoverageTest` 涓? `OK (69 tests, 1349 assertions)`；回调注释契约为 `OK (2 tests, 16 assertions)`銆?
- routes銆乂erifyCsrfToken銆丳aymentNotifyController 均???过 `php -l`銆?
- 鏈?终规???/质量复核：Critical=0銆両mportant=0銆?

### 剩余边界
- 当前回调是安全的失败关闭，不代表真实支付可用；只有后??? adapter、当前有效商???/密钥、金额与签名验证、状态机和结??? outbox 完成后，指定通道才可启用???
- 下一支付子目标进??? DECIMAL 金额、Idempotency-Key、本地订单唯???索引、???道配置完整性和??? fallback 建单???

## 355. 2026-07-11 前台支付精确金额、幂等本地订单与失败关闭

### 本次处理目标
- 入金金额只接受普通十进制字符串，???多两位小数；拒绝数字 JSON、科学计数法、三位小数???非正数和超配置边界值，支付链路不使??? float 比较或计算???
- `PaymentOrderService` 在事务内??? `Idempotency-Key + user_id + gateway_code` 创建或回读本地订单；相同金额返回原订单，不同金额明确冲突，软删除记录仍占用幂等键???
- `deposit_records` 转为 InnoDB锛屽苟灏? `amount/actual_amount` 收紧??? `DECIMAL(18,2)`銆乣exchange_rate` 收紧??? `DECIMAL(18,8)`；补充本地订单号唯一索引、支???/结算字段和复合幂等唯???索引???
- 删除内置 fallback 通道重开与本??? return URL 假成功??俆ask 3 的白名单 adapter registry 尚未落地前，数据库配置和网关 URL 均不能触发建单，统一返回 `OPERATION_NOT_ALLOWED`銆?

### 路由与服务链
- `POST /api/front/deposits/submissions` / `POST user/deposit_request` 鈫? 当前用户解析 鈫? 普???十进制字符串与全局/通道边界校验 鈫? 可调用白名单 adapter 妫?查；没有真实 adapter 时失败关闭且不写 `deposit_records`銆?
- `PaymentOrderService::createOrRetrieve` 鈫? InnoDB 事务 鈫? 包含软删除记录的幂等键加锁查??? 鈫? 同金额回读???不同金额冲??? 鈫? 首次请求写入精确 DECIMAL 金额??? pending 支付/结算状???；唯一键竞态回读后再次核对金额???
- `GET /api/front/deposits/form-options` 鈫? 仅展示具备完整且可调用白名单 adapter 的启用???道，不再使用内??? fallback 通道???

### 本次变更文件
- `app/Support/Money.php`：普通十进制字符串规范化、字符串边界比较??? BCMath 精确汇率计算???
- `app/Services/Payment/PaymentOrderService.php`：事务幂等创???/回读、软删除幂等域和唯一键竞态收敛???
- `database/migrations/2026_07_11_000003_harden_deposit_payment_orders.php`：可重复执行的数据保留型 schema 加固???
- `app/Http/Controllers/Front/DepositController.php`銆乣app/Models/DepositRecord.php`：失败关闭???精确金额入口??乫illable 涓? decimal casts銆?
- `tests/Feature/FrontDepositPaymentOrderIdempotencyClosureModuleTest.php`銆乣tests/Feature/FrontDepositOwnerBoundaryClosureModuleTest.php`：新安全契约与旧假成功契约更新???

### TDD 与验证证???
- RED：目标测试首次运??? `20 tests / 37 assertions / 1 error / 15 failures`锛岃瘉鏄? Money 缺失、float/numeric 输入仍被接受、幂等键重复建单、缺 adapter 仍建单且 schema 仍为 DOUBLE銆?
- GREEN锛歚FrontDepositPaymentOrderIdempotencyClosureModuleTest` 涓? `OK (21 tests, 59 assertions)`锛沗FrontDepositOwnerBoundaryClosureModuleTest` 涓? `OK (6 tests, 40 assertions)`锛沗FrontPaymentRouteSafetyClosureModuleTest` 涓? `OK (4 tests, 35 assertions)`銆?
- MySQL strict-mode schema 复核确认 `deposit_records` 涓? InnoDB，金额列分别??? `decimal(18,2)` / `decimal(18,2)` / `decimal(18,8)`，两个唯???索引列顺序正确，且会话启??? `STRICT_TRANS_TABLES`銆?

### 剩余边界
- Task 2 只完成精确本地订单基???与失败关闭；真实 provider create-order、签名???回调状态机和结??? outbox 仍由 Task 3-5 实现。在白名??? adapter 可调用前，支付入口保持不可用是预期安全状态???

### Task 2 规格复核补强
- `PaymentOrderService` 只对明确命中 `deposit_records_idempotency_user_gateway_unique` 的唯???键异常执行回读：MySQL 要求 SQLSTATE `23000` 与错误码 `1062`锛孲QLite/PgSQL 使用各自唯一约束代码；其??? `QueryException` 原样抛出???
- 竞???测试使用第??? MySQL 连接独立提交竞争订单，覆盖主事务创建点抛唯一键异常后的同金额复用和不同金额冲突，不再以顺序调用代替并发分支???
- 软删除订单继续占用幂等键，但无论金额相同或不同均返回明确冲突，不能作为可继续支付订单重新打开???
- `Money` 增加 `DECIMAL(18,2)` 十六位整数上限???汇率乘积溢出检查???严格类型声明和 BCMath 缺失时的明确 `LogicException`銆?
- 迁移重复执行时会重新校正 MySQL 列规格；唯一索引???测覆??? MySQL銆丼QLite銆丳ostgreSQL。非空真??? `local_order_no` 重复会中止迁移且不改号，仅空旧???按 `LEGACY-DEP-{id}` 稳定补齐???
- 补强 RED：目标套件为 `30 tests / 71 assertions / 9 failures`锛涜ˉ寮? GREEN锛歚OK (30 tests, 83 assertions)`銆?
- 主幂等链的???同金额返回原订单???仅适用于未软删除订单；命中软删除记录时是明确的安全冲突例外，不会返回可继续支付的订单???
- 跨驱动承诺仅覆盖唯一索引存在性检测与重复 `up()`锛沗DECIMAL` 精度、nullable 和长度的自动校正只对本项目生??? MySQL 承诺，SQLite/PostgreSQL 不伪称已执行列类型改造???
- 空旧订单号升级会先预计算全部 `LEGACY-DEP-{id}`锛岀粺涓?与所有非空真实订单号及其他??欓??比较；发现冲突时保持零写入并中止???该缺口 RED 涓? `1 test / 1 failure`锛孏REEN 涓? `OK (1 test, 2 assertions)`銆?
- 鏈?缁? Task 2 目标套件??? `OK (31 tests, 85 assertions)`锛沷wner-boundary、路由安全和迁移重入继续独立通过???
## 356. 2026-07-11 支付网关注册表与建单配置契约闭环

### 本次完成范围

- 新增 `PaymentGatewayAdapter` 四方法契约???不可变 `PaymentOrderResult` 涓? `PaymentCallback`銆?
- 新增单例 `PaymentGatewayRegistry`，数据库仅允许配置白名单 alias；未注册 alias、禁用???道、配置缺项??乬ateway 不匹配???币种不支持??? Task 4 尚不存在??? adapter 均失败关闭???
- 完成 Tiger銆乄P銆丒xlink FB/BB銆丅TB銆丳assTo銆丼witch銆丱TC alias；旧 passageway 6/7 分别固定 `pay_type=3/2`锛?9/10/11 分别固定 `pay_type=1/2/3`銆?
- `POST /api/front/deposits/submissions` 执行链调整为：用户与金额校验 鈫? Registry 解析完整通道 鈫? `PaymentOrderService` 幂等本地订单 鈫? 原子抢占 `provider_create_in_progress` 鈫? adapter 创建 provider 订单 鈫? 保存 provider 单号与安全结果快??? 鈫? 返回兼容字段。provider 异常??? `payment_status=provider_create_unknown`，仅记录订单号??乬ateway 和异常类型，不泄露异常原文或配置???
- claim 同时记录 `provider_create_started_at/provider_create_attempts`锛涜秴杩? 15 分钟??? in-progress 只转 unknown，绝不自动再??? create銆倁nknown 由后续已验签回调或对账链确认，避免第三方已建单时重复创建???
- 相同 Idempotency-Key 仅在 `payment_status=pending` 且存在有??? provider 单号与安全快照时恢复??? `payment_url/form_action`，不再次调用 adapter锛泂uccess/failed/refunded/unknown/in-progress 均不返回 checkout銆俙PaymentOrderService` 未知系统异常继续上浮，不伪装成业务拒绝???
- `GET /api/front/deposits/form-options` 只输??? UI 白名单字段，不输??? merchant銆乻ecret/key reference 或配??? endpoint銆?
- 后台通道 store/update 灏? textarea JSON 严格解码为数组；非法 JSON 和明确敏感键返回 `VALIDATION_FAILED`銆俙*_reference/*_ref` 涓? Registry 共用 `SecretReference`，只接受 `env:UPPER_SNAKE_NAME` 鎴? `vault:safe/path[#field]`锛沗sk-live-*` 等裸凭据被拒绝，`label_key/type_label_key` 等公???配置不被误杀；toggle 仍只翻转 `is_enabled`銆?
- 修复 legacy Exlink `deposit_exlink_bbreturn`銆乣deposit_exlink_fbreturn` 因不??? `_return` 而误??? notify 的问题，改为显式 return URI 集合???

### 关键响应兼容

- 成功建单继续返回旧前端需要的 `order_no`銆乣payment_url`銆乣open_blank`銆乣channel`，并增加 `provider_order_no`銆乣redirect_url`銆乣form_action`銆乣form_fields`銆?
- provider 创建结果不确定时返回 `OPERATION_NOT_ALLOWED`，本地订单保??? `settlement_status=pending` 并进??? `provider_create_unknown`；响应不包含 provider 异常、商户号、密钥引用或私有配置 endpoint銆?

### TDD 与验证证???

- Registry/DTO/Controller RED 依次证明类缺失???注册方法缺失??丏TO 缺失、???道隐藏、provider 失败未留单???旧响应字段缺失、重复调??? provider 和系统异常被吞???
- `PaymentGatewayRegistryTest`锛歚OK (37 tests, 103 assertions)`銆?
- `FrontDepositPaymentOrderIdempotencyClosureModuleTest`锛歚OK (31 tests, 85 assertions)`銆?
- `FrontDepositOwnerBoundaryClosureModuleTest`锛歚OK (6 tests, 40 assertions)`銆?
- `FrontPaymentRouteSafetyClosureModuleTest`锛歚OK (4 tests, 39 assertions)`銆?
- `AdminPaymentChannelToggleModuleTest`锛歚OK (14 tests, 47 assertions)`銆?
- `SecretReferenceTest`锛歚OK (9 tests, 9 assertions)`銆?
- `FrontendRouteManifestTest`锛歚OK (21 tests, 76 assertions)`銆?

### 后续边界

- Task 3 只建立安全注册???建单与配置契约；真实网关字段映射???签名???验签与无网??? fixture 鐢? Task 4 完成???
- 支付成功回调状???机、重复回调??佺粨绠? outbox 与资金入账仍??? Task 5 完成；在对应 adapter 和回调链完成前，默认生产 alias 因实现类不存在继续失败关闭???

## 357. 2026-07-11 支付网关真实协议 Adapter 与无网络 Fixture 闭环

### 本次完成范围

- 完成 TigerPay銆乄P銆丒xlink Fiat銆丒xlink Crypto銆丅TB銆丳assTo銆丼witch 七类真实协议 Adapter锛汷TC 因旧项目无法证明安全建单、验签和回调协议，明确实现为 unsupported/fail-closed，不伪???可用支付链???
- 鎵?鏈? fixture 仅包含虚??? merchant、订单号、URL 和测??? secret锛汿iger 两组 RSA 密钥在测试运行时动???生成，不读取???不复制旧项??? PEM 或生产凭据???
- 每个可用 Adapter 均严格校??? gateway銆乵erchant/app銆乴ocal order銆乤mount銆乧urrency銆乸rovider status；对称签名使??? `hash_equals`锛孴iger 使用 RSA PKCS#1 v1.5 分块加解密和 RSA-MD5 签名/验签???
- 完整配置门禁覆盖 endpoint銆乵erchant/app銆乺equest/callback key銆乧urrency銆乤mount unit銆乶otify/return route 和协??? profile；引用格式合法但 resolver 返回 null 时，同样在任??? HTTP 发???前失败关闭???
- 鎵?有数组式 `Http::fake()` 均增??? `* => 599` 失败兜底，并断言真实目标 URL；测试不允许未匹配请求回落外网???

### 路由与建单执行链

- `POST /api/front/deposits/submissions` 或旧 `POST user/deposit_request` 鈫? `DepositController::submitDeposit` 鈫? 当前用户、金额??侀??道、幂等键校验 鈫? `PaymentGatewayRegistry::resolve` 鈫? `PaymentOrderService::createOrRetrieve` 鈫? 原子 claim `provider_create_in_progress` 鈫? 具体 Adapter `createOrder` 鈫? 保存 provider order/result snapshot 鈫? 返回 checkout 数据???
- WP Adapter 优先使用 transient `payer_mobile`，缺失时通过 `DepositRecord::user` 读取真实 `user_infos.phone`锛沗DepositController` 不导入???不判断具体 Adapter 类型???
- provider 建单异常统一??? `provider_create_unknown`，不自动重建单；日志仅记录本地订单号、gateway 和异常类，不记录 secret、签名原文???密文或 provider 私有响应???

### 协议金额与签名边???

- TigerPay：发送换汇后??? `actual_amount` 作为 CNY `price`锛泂erver public key 加密业务 JSON锛宎pp private key 对规??? URL-encoded data 签名。回调同时兼容已编码 fixture 涓? PHP percent-decoded 表单值，统一 canonical data 后验签；ACK 涓? `SUCCESS`銆?
- WP锛氬彂閫? `actual_amount`、真实手机号、用户名和本地订单；请求签名为字段名排序后直接拼接并追加 key 的大??? SHA-1锛沜allback key 独立验签???
- Exlink Fiat：发送换汇后??? `actual_amount` 涓? pay type锛汦xlink Crypto：始终发送原??? `amount`锛屽嵆浣? exchange rate 闈? 1 也不改写 USDT 数量；两者均使用排序 `key=value&...&key=secret` 小写 MD5銆?
- BTB：浏览器 GET 跳转签名 URL，使用原??? `amount`；回调必须签名???成功状态及全部订单身份匹配???
- PassTo锛氬彂閫? `actual_amount` 的分单位整数，排序字段后追加 key 并使用大??? MD5锛泇ersion 为必填配置，不再静默补默认??笺??
- Switch：发送换汇后??? `actual_amount` 涓? profile pay type，请求和 callback 使用独立 key；零整数位规范化??? `0.00`銆?
- OTC锛歚createOrder` 鍜? `parseCallback` 鎶? unsupported锛宍verifyCallback=false`锛孉CK 涓? HTTP 422 `UNSUPPORTED`，绝不返??? success/OK銆?

### TDD 与最终验证证???

- Tiger wire 二次编码、真实表??? percent-decode、金额字段错误；Exlink Crypto 汇率改写；WP 控制器具体类耦合；完整配???/双密钥缺失；Switch `.00` 等问题均先出现明??? RED，再做最小实现至 GREEN銆?
- `PaymentGatewayAdapterFixtureTest`锛歚OK (91 tests, 732 assertions)`銆?
- `PaymentGatewayRegistryTest`锛歚OK (37 tests, 106 assertions)`銆?
- `FrontDepositPaymentOrderIdempotencyClosureModuleTest`锛歚OK (32 tests, 90 assertions)`銆?
- `FrontDepositOwnerBoundaryClosureModuleTest`锛歚OK (6 tests, 40 assertions)`銆?
- `FrontPaymentRouteSafetyClosureModuleTest`锛歚OK (4 tests, 39 assertions)`銆?
- `FrontendRouteManifestTest`锛歚OK (21 tests, 76 assertions)`銆?
- `AdminPaymentChannelToggleModuleTest`锛歚OK (14 tests, 47 assertions)`銆?
- `AdminPaymentChannelRouteIdValidationClosureModuleTest`锛歚OK (4 tests, 26 assertions)`銆?
- `SecretReferenceTest`锛歚OK (9 tests, 9 assertions)`锛涘叏閮? Task 4 Adapter、控制器和测??? PHP 文件通过 `php -l`銆?
- 鏈?终全体规格复审与代码质量终审均为 APPROVED锛孋ritical=0銆両mportant=0銆丮inor=0銆?

### 后续边界

- Task 4 只闭??? provider 建单、签名???验签???严格解析与 ACK 契约；合??? callback 的支付状态机、重复成功幂等??乫ailure-after-success銆佺粨绠? outbox銆丮T4/资金入账和重试恢复仍属于 Task 5銆?
- 未配置真??? merchant銆乪ndpoint 鍜? secret reference 的生产???道仍不可用；OTC 在获得可验证的正式协议前保持失败关闭???

## 358. 2026-07-11 前台支付回调、入金结算??侀??款??嗗悜涓? Outbox 恢复闭环

### 完成范围

- 支付回调按本地订单号加锁，严格核??? gateway銆乵erchant銆乸rovider order銆乧urrency 与精??? provider amount；重复成功幂等，成功后失败不回???銆?
- 首次成功在同???事务内写入真??? `payment_time`、支付状态和唯一 `deposit_settlement` outbox；事务提交后发布队列，发布失败由每分??? scanner 持久恢复???
- `SettleDepositPayment` 使用账户 USD `order.amount` 和稳定注??? `DBUN-{user_id}-#{local_order_no}`锛涘閮? MT4 入金始终位于数据库事务外???
- 连接前失败进??? `retryable`；写入不确定、读取超时??乻tale processing、外部成功后本地提交失败均进??? `unknown`，禁止自动重复资金操作???
- provider 閫?款在结算前终止待执行入金；结算后创建唯一 `deposit_refund` outbox，由 `RefundDepositPayment` 使用 `DBRF-{user_id}-#{local_order_no}` 执行 MT4 逆向并记??? `refund_mt4_ticket/refund_time`銆?
- 閫?款在入金 processing 期间到达时先 blocked：入金成功后???活???向，明确未发???/拒绝时终结为无需逆向，入金结果不确定时双方进入人工对账状态???
- scanner 同时恢复到期 pending/retryable 涓? `locked_at` 缺失或过期的 processing锛屽苟鎸? event type 分发正确 Job锛汮ob 自身再次校验 event type，错误投递安??? no-op銆?
- outbox 缺失订单、订单终态不???致???错误主???/索引、部分迁移和历史 `provider_amount=NULL` 均有明确终??併??修复或 fail-fast 边界???

### 数据库与迁移不变???

- `payment_settlement_outbox` 使用 InnoDB 兼容??? epoch 时间列??佸敮涓? `(event_type, deposit_record_id)`銆乺eady 索引和订单号索引???
- 000006 重入会???列补齐、再次回??? NULL provider amount、严格校???/修复索引定义，并验证 `id` 涓? `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY`；非空错误结构在任何其他 DDL 前失败关闭???
- 000007 增加 `refund_mt4_ticket` 与真??? DATETIME `refund_time`锛沗DepositRecord` 专用 mutator 避免基础模型 Unix 日期格式污染 DATETIME 写入???

### 鏈?终验证证???

- `PaymentSettlementOutboxMigrationRerunClosureModuleTest`锛歚OK (8 tests, 23 assertions)`銆?
- `FrontPaymentCallbackStateMachineClosureModuleTest`锛歚OK (25 tests, 80 assertions)`銆?
- `SettleDepositPaymentJobClosureModuleTest`锛歚OK (27 tests, 126 assertions)`銆?
- `RefundDepositPaymentJobClosureModuleTest`锛歚OK (12 tests, 67 assertions)`銆?
- `DispatchPendingDepositSettlementsCommandClosureModuleTest`锛歚OK (1 test, 3 assertions)`銆?
- `Mt4DepositSettlementGatewayClosureModuleTest`锛歚OK (11 tests, 18 assertions)`銆?
- `Mt4DepositRefundGatewayClosureModuleTest`锛歚OK (6 tests, 13 assertions)`銆?
- `FrontPaymentRouteSafetyClosureModuleTest`锛歚OK (4 tests, 39 assertions)`銆?
- Task 5 fresh 合计：`OK (94 tests, 369 assertions)`；迁移无待执行项，scanner 每分钟注册，相关 PHP lint 全部通过???
- 鏈?终规格复核与代码质量复核：APPROVED锛孋ritical=0銆両mportant=0銆丮inor=0銆?

## 359. 2026-07-12 后台批量信用导入 MT4 同步闭环

### 完成范围

- 补齐旧项??? `BatchCreditController::creditImportExcel` 涓? `againCreditAmount` 中真实信用入账链路在新后台的落点???
- 新增 `admin_api_syncCreditImport`，只允许 `credit_imports.is_synced=0` 的待处理记录发起真实 MT4 信用同步???
- 同步前先执行 `AdminDataScopeService` 数据范围过滤，再短暂 claim 为内部处理中??? `3`，返回前必须落回 `0/1/2`銆?
- `settled` 写入 `is_synced=1` 与真??? `mt4_order_id`锛沗retryable_not_sent` 回到待处理；`unknown/rejected` 写入失败状???和机器错误码???
- 失败重试继续只把失败记录回待处理，不直接触发外部资金动作，避免重复信用入账???

### 路由与执行链

- `POST /api/admin/syncCreditImport/{id}` / `admin_api_syncCreditImport` 鈫? `BatchCreditImportController::syncCreditImport` 鈫? 路由参数严格整数校验 鈫? `credit_imports.id` 查询 鈫? 管理员数据范围过??? 鈫? 待处理状态校??? 鈫? claim `is_synced=3` 鈫? `CreditSettlementGateway::creditIn` 鈫? `Mt4ManagerService::creditIn` 鈫? MT4 `USER_CREDIT_IN` 鈫? `finishCreditImportSync` 写回同步结果???
- Layui锛歚resources/admin/layui/credit-imports/index.blade.php` 行按??? `syncCreditImport` 鈫? `public/js/apps/admin/layui/pages.js` 鈫? `/api/admin/syncCreditImport/{id}`銆?
- CrmUI锛歚PageController` 涓? `credit-imports` 增加 `sync_import` 行动作，继续??? `module-page` 渲染???
- Naive锛歚front-plain.js` 涓? `credit-imports` 增加 `syncImportEndpoint`，复用???用导入同步动作???

### 文件与权???

- `app/Contracts/CreditSettlementGateway.php`：新增信用同步契约???
- `app/Services/Payment/Mt4CreditSettlementGateway.php`锛氭柊澧? MT4 信用同步 gateway，复用闭合结果分类???
- `app/Services/Mt4ManagerService.php`锛氭柊澧? `creditIn()`，按旧项??? `credit-in` 语义映射??? Socket 命令 `USER_CREDIT_IN`銆?
- `app/Providers/Mt4ServiceProvider.php`锛氱粦瀹? `CreditSettlementGateway` 到生??? MT4 gateway銆?
- `database/migrations/2026_06_07_000005_add_admin_batch_credit_import_permissions.php`锛氭柊澧? `admin_batch_credit_import_sync` / `admin_api_syncCreditImport` 权限???
- `docs/admin-legacy-migration-gap-audit.md`：批量信用导入从“部分迁移???更新为“已迁移核心闭环”???

### 验证证据

- RED锛歚AdminBatchCreditImportMt4SyncClosureModuleTest` 首次运行失败，命??? `admin_api_syncCreditImport` 路由??? `CreditSettlementGateway` 契约缺失???
- GREEN锛歚AdminBatchCreditImportMt4SyncClosureModuleTest`銆乣Mt4CreditSettlementGatewayClosureModuleTest`、批量信用导入既有模???/重试/权限/数字筛???/路由 ID銆佸鍏? UI 回归通过???
- 扩展回归：`FrontUiRegressionTest`銆乣CrmUiStackTest`銆乣AdminChineseCommentReadabilityTest`銆乣AdminZhCnLanguageReadabilityTest`銆乣AdminLegacyMigrationGapAuditTest` 通过???

## 360. 2026-07-12 后台出金流水 COMMENT 分类与汇总闭???

### 完成范围

- `mt4_trades` 补齐 `comment` 涓? `modify_time` 字段，承接旧项目 MT4 COMMENT 来源识别口径???
- `FundFlowController::withdrawFlowList` 鍜? `FundFlowController::exportWithdrawFlows` 统一使用 `cmd=6`銆乣open_price=0`銆乣profit<0`銆丆OMMENT 出金关键字??乣withdraw_source`銆乣user_id`銆乣ticket`、日期范围和后台数据范围过滤???
- 列表返回 `data.list`銆乣data.totalRow`銆乣data.summary`，并为每行补??? `flow_source`銆乣flow_source_name`銆乣directTypeName`銆乣comment` 与当前筛选金额汇总???
- CSV 导出使用同一筛???链路，输出来源分类、备注和当前筛???合计行???
- Layui銆丆rmUI銆丯aive 管理端补??? `withdraw_source` 筛??夈??来源列、备注列和汇总字段???

### 路由与执行链

- `POST /api/admin/withdrawFlowList` / `admin_api_withdrawFlowList` 鈫? `FundFlowController::withdrawFlowList` 鈫? `validateUserIdFilter` 鈫? `newWithdrawFlowQuery` 鈫? `applyWithdrawFlowFilters` 鈫? `applyDataScope` 鈫? `withdrawFlowSummary` 鈫? `paginateQuery` 鈫? `formatWithdrawFlowRecord` 鈫? `success(['list','totalRow','summary'])`銆?
- `POST /api/admin/exportWithdrawFlows` / `admin_api_exportWithdrawFlows` 鈫? `FundFlowController::exportWithdrawFlows` 鈫? 同一查询构建和数据范围过??? 鈫? `formatWithdrawFlowRecord` 鈫? 追加 `total` 合计??? 鈫? `csvDownload('withdraw_flows_export.csv')`銆?

### 测试记录

- RED锛歚AdminWithdrawFlowCommentClassificationClosureModuleTest` 首次失败，命??? `mt4_trades.comment` 字段缺失、前端筛选列缺失和文档缺口???
- 目标测试：`AdminWithdrawFlowCommentClassificationClosureModuleTest` 覆盖 COMMENT 分类、`withdraw_source` 筛??夈??列表汇总???导出合计??丩ayui/CrmUI/Naive 配置和文档记录???
- 边界：未入金复杂状???分类???运营跟进统计和财务复核汇???已在第 361 节补齐；本节后续只保留复杂财务复核写链???真实支付网关状态变更和旧项目未确认深层流程???

## 361. 2026-07-25 后台未入金复杂状态分类与运营汇??婚棴鐜?

### 完成范围

- `FundFlowController::undepositFlowList` 从???只返回待支付分页记录???升级为“分页记??? + `summary` + `totalRow`鈥濄??
- 每条 `deposit_records.status=01` 未入金流水按等待天数返回 `follow_status`銆乣follow_status_name` 鍜? `pending_days`銆?
- 状???分桶含义：`new_pending` 表示 0-1 天新提交；`need_follow_up` 表示 2-6 天需要运营跟进；`finance_review_required` 表示 7 天及以上???要财务复核???
- 当前筛???汇总返??? `total_records`銆乣total_amount`銆乣new_pending_count`銆乣need_follow_up_count`銆乣finance_review_required_count`，用于页面顶部统计和财务核对???
- `exportUndepositFlows` 复用同一查询链路，CSV 输出状???分桶???待处理天数和当前筛选合计行???
- Layui銆丆rmUI銆丯aive 管理端同步展??? `follow_status_name` 涓? `pending_days`，避免三个后台入口展示口径不???致???

### 路由与执行链

- `POST /api/admin/undepositFlowList` / `admin_api_undepositFlowList` 鈫? `FundFlowController::undepositFlowList` 鈫? `validateUserIdFilter` 鈫? `newUndepositFlowQuery` 鈫? `applyUndepositFlowFilters` 鈫? `applyDataScope` 鈫? `undepositFlowSummary` 鈫? `paginateQuery` 鈫? `formatUndepositFlowRecord` 鈫? `success(['list','totalRow','summary'])`銆?
- `POST /api/admin/exportUndepositFlows` / `admin_api_exportUndepositFlows` 鈫? `FundFlowController::exportUndepositFlows` 鈫? 同一查询构建和数据范围过??? 鈫? `formatUndepositFlowRecord` 鈫? 追加 `total` 合计??? 鈫? `csvDownload('undeposit_flows_export.csv')`銆?

### 返回字段中文含义

- `follow_status=new_pending`：新提交，???常等待支付回调或用户继续支付???
- `follow_status=need_follow_up`：运营跟进，???要运营人员触达用户确认是否继续支付???
- `follow_status=finance_review_required`：财务复核，???要核对???道异常、漏回调或重复申请???
- `pending_days`：记录从创建到当前已经等待的自然天数???
- `summary.total_amount`：当前筛选条件下???有待支付入金申请金额合计???

### 测试记录

- RED锛歚AdminUndepositFlowSummaryClosureModuleTest` 首次失败，命??? `data.summary.total_records` 缺失和最终清单缺少本节记录???
- GREEN：补齐控制器状???分桶???汇总???导出合计???前端列配置、语???包和本节清单后，目标测试应??氳繃銆?

### 剩余边界

- 本轮不触发真实支付网关???不改变 `deposit_records.status`，只补齐待支付记录的运营分类与核对汇总???
- 后续继续按旧项目模块清单审计普???用户???代理商和后台管理员其它剩余入口???

## 362. 2026-07-25 界面 Lucide 图标统一与表情符号清理闭???

### 完成范围

- Naive 单页后台壳不再使??? `D`銆乣U`銆乣A` 等单字母伪图标，统一改为 `data-lucide` 图标名，由本??? Lucide vendor 渲染???
- `lucide-bridge.js` 统一??? Layui 涓? Font Awesome 旧类名桥接到 Lucide 图标，并把旧别名 `circle-down` 修正为当??? vendor 存在??? `circle-arrow-down`銆?
- 前台忘记密码页???前台资料页、后台首页说明区删除可见表情符号，改??? Lucide `circle-check-big` 涓? `camera`銆?
- 前台 CSS 删除伪元素里的符号图标，改由边框、背景???字重和 Lucide 节点表达状???，避免视觉体系混用???
- 新增 `LucideIconAndEmojiPolicyTest`，持续约??? Blade銆丯aive JS銆佸墠鍙? CSS 和后台壳 CSS 不能重新写入表情符号，且声明??? Lucide 图标必须存在于本??? vendor銆?

### 文件与链???

- `public/js/apps/naive-admin/front-plain.js`锛歚lucideIconHtml()` 负责输出稳定??? `data-lucide` 节点，`refreshIcons()` 在页面片段渲染后触发 `window.CrmIcons.refresh()`，解决异步页面切换后图标未初始化的问题???
- `public/css/naive-admin/app.css`：为 logo、菜单???下拉项、按钮???统计卡等图标提供固定尺寸，解决图标替换后布???抖动和文字挤压问题???
- `public/js/shared/lucide-bridge.js`锛氬湪鏃? Layui / Font Awesome 类名进入页面时统???转换??? Lucide 名称，返??? `data-lucide` 渲染节点；旧类名没有映射时保持空值并交给页面原???辑暴露问题???
- `resources/views/front/auth/forget_password.blade.php`銆乣resources/views/front/profile/show.blade.php`銆乣resources/views/admin/dashboard/index.blade.php`：显式引??? Lucide 资源并用图标节点替换原表情符号???
- `tests/Feature/LucideIconAndEmojiPolicyTest.php`：扫描可??? UI 源文件；解析 Naive 图标配置、静??? `data-lucide` 和桥接映射；??? kebab-case 图标名转换为 vendor 暴露??? PascalCase 导出名后校验存在性???

### 执行结果中文含义

- `visible ui sources do not contain emoji symbols` 通过：当前可??? UI 源文件没有继续写入表情符号或符号区间图标???
- `naive shell uses lucide icon names instead of letter badges` 通过：Naive 后台壳已改为 Lucide 图标体系，不再依赖字母徽标???
- `declared lucide icon names exist in bundled vendor` 通过：所有声明的 Lucide 图标名都能被当前本地 vendor 包解析，页面不会因为图标名不存在而空白???

### 验证记录

- RED锛歚LucideIconAndEmojiPolicyTest` 首次失败，命中前??? CSS 表情符号、Naive 单字母伪图标，以??? `circle-down` 不存在于本地 Lucide vendor銆?
- GREEN锛氫慨姝? UI 源文件和测试提取范围后，`php artisan test tests\Feature\LucideIconAndEmojiPolicyTest.php` 通过，结果为 `3 passed`銆?
- 语法验证：`node --check public\js\apps\naive-admin\front-plain.js`銆乣node --check public\js\shared\lucide-bridge.js`銆乣php -l` 对本??? Blade 与测试文件均通过???
- 符号扫描：`rg -n -P '[\x{2600}-\x{27BF}\x{1F300}-\x{1FAFF}]' resources\views public\css\front public\css\naive-admin public\js\apps\naive-admin` 无匹配???

### 剩余边界

- 本轮只收敛图标体系和表情符号策略，不改变普???用户???代理商或后台管理员业务数据读写???
- 后续继续按旧项目模块清单迁移剩余业务闭环，并在最终中文全量路由链路报告中汇???本节设计约束???

## 363. 2026-07-25 后台实时返佣 COMMENT 精确识别与导出闭???

### 完成范围

- `RealtimeCommissionController` 从??渀cmd=6` 涓? `profit>0` 的正向余额??欓??记录???升级为旧项目实时返佣口径：必须同时满足 `cmd=6`銆乣profit>0`銆乣comment` 命中 `DBCN` 鎴? `-FY`銆?
- `ticket/order_id` 筛???同时匹??? `mt4_trades.ticket` 鍜? `mt4_trades.comment`，解决旧项目源订单号写在 COMMENT 里时后台无法定位返佣记录的问题???
- 日期范围按返佣确认时间过滤，后端优先使用 `modify_time`，缺失或??? 0 时回???鍒? `close_time`，兼容历??? MT4 同步数据???
- 列表和导出统???格式化字段，返回 `rebate_source`銆乣rebate_source_name`銆乣comment`銆乣modify_time`，并把数值型 ID 和订单号转为明确整数???
- Layui銆丆rmUI銆丯aive 三套后台入口都展示返佣来源??丆OMMENT 和返佣时间，CSV 导出复用同一筛???链路???

### 路由与执行链

- `POST /api/admin/realtimeCommissionList` / `admin_api_realtimeCommissionList` 鈫? `RealtimeCommissionController::realtimeCommissionList` 鈫? `validateUserIdFilter` 鈫? `baseRealtimeCommissionQuery` 鈫? `applyRebateCommentFilter` 鈫? `applyFilters` 鈫? `applyDataScope` 鈫? `paginateQuery` 鈫? `formatRealtimeCommissionRecord` 鈫? `success(['records','summary'])`銆?
- `POST /api/admin/exportRealtimeCommissions` / `admin_api_exportRealtimeCommissions` 鈫? `RealtimeCommissionController::exportRealtimeCommissions` 鈫? 同一基础查询、COMMENT 过滤、筛选和数据范围 鈫? `formatRealtimeCommissionRecord` 鈫? `csvDownload('realtime_commissions_export.csv')`銆?

### 返回字段中文含义

- `rebate_source=legacy_dbcn`：旧 DBCN 账户返佣，???常由旧实时返佣任务写入 MT4 COMMENT銆?
- `rebate_source=legacy_fy`：旧 `-FY` 返佣备注，兼容旧项目早期或其它入口写入格式???
- `rebate_source_name`：面向后台页面展示的返佣来源中文名称???
- `comment`锛歁T4 原始 COMMENT，用于财务核对源订单号???代理关系和旧任务幂等备注???
- `modify_time`：返佣确认时间；优先??? MT4 `modify_time`，缺失时回???鍒? `close_time`銆?

### 测试记录

- RED锛歚AdminRealtimeCommissionModuleTest` 新增 COMMENT 精确识别用例后首次失败，命中 CSV 未输??? COMMENT、订单筛选只匹配 `ticket` 导致 COMMENT 内源订单号查不到???
- GREEN锛氳ˉ榻? COMMENT 关键词过滤??丆OMMENT 订单号筛选??佺粺涓?格式化字段???导出列和三套后台列配置后，`php artisan test tests\Feature\AdminRealtimeCommissionModuleTest.php` 通过，结果为 `9 passed`銆?

### 剩余边界

- 本轮不触发实时返??? MT4 入账、不扫描待返佣交易???不改变前台实时返佣结算任务，只收敛后台查询、汇总和导出核对口径???
- 后续继续迁移旧项目更深层的自动结算联动??丮T4 定时任务分类联动和其它后???/代理/普???用户剩余入口???

## 364. 2026-07-25 后台交易历史平仓 COMMENT 强平筛??夐棴鐜?

### 完成范围

- `TradeController` 的交易列表???当前持仓???历史平仓筛选兼容旧项目参数：`userId`銆乣orderId`銆乣sym_symbol`銆乣startdate/enddate`銆?
- 历史平仓恢复旧项??? `is_coercion` 强平筛??夛細`Yes` 表示 `comment LIKE so%` 的强平单，`No` 表示排除强平单???
- 历史平仓分页返回 `comment`、旧 Blade 兼容字段 `ordercomment` 鍜? `modify_time`锛屽苟鎸? `COALESCE(NULLIF(modify_time, 0), close_time)` 鍊掑簭銆?
- Layui 页面补齐订单号???开始日期???结束日期???强平状态筛选和“全部交???/当前持仓/历史平仓”模式按钮；按钮图标统一使用 Lucide銆?
- CrmUI 涓? Naive 后台配置同步补齐筛??夐」銆丆OMMENT、旧 `ordercomment` 鍜? `modify_time` 字段???

### 路由与执行链

- `POST /api/admin/closedPositions` / `admin_api_closedPositions` 鈫? `TradeController::closedPositions` 鈫? `validateUserIdFilter` 鈫? `baseMt4TradeQuery` 鈫? `applyTradeFilters` 鈫? `applyForceCloseFilter` 鈫? `applyDataScope` 鈫? `paginateQuery` 鈫? `formatTradeRecord` 鈫? `success(['records','summary'])`銆?
- `POST /api/admin/openPositions` / `admin_api_openPositions` 鈫? `TradeController::openPositions` 鈫? 同一基础查询与旧参数兼容筛??? 鈫? 未平??? `close_time is null or 0` 鈫? 数据范围 鈫? 分页与汇总???
- `POST /api/admin/tradeList` / `admin_api_tradeList` 鈫? `TradeController::index` 鈫? 同一基础查询与旧参数兼容筛??? 鈫? 数据范围 鈫? 分页与汇总???

### 返回字段中文含义

- `comment`锛歁T4 原始 COMMENT，用于识别强平???人工平仓说明和财务核对备注???
- `ordercomment`：旧项目 Blade 表格字段名，值与 `comment` 涓?致，解决旧前???/旧报表读取字段名不一致的问题???
- `modify_time`锛歁T4 修改时间；历史平仓优先按它排序，缺失或为 0 时回??? `close_time`銆?
- `is_coercion=Yes`：只返回 COMMENT 浠? `so` 寮?头的强平单???
- `is_coercion=No`：只返回 COMMENT 不以 `so` 寮?头的非强平平仓单???

### 测试记录

- RED锛歚AdminTradeMt4PositionModuleTest::test_closed_positions_honor_legacy_force_close_filters_and_return_comment_fields` 首次失败，接口返??? 4 条??屼笉鏄? 1 条，命中旧筛选参数和强平筛???未生效???
- GREEN：补齐旧参数读取、强??? COMMENT 筛??夈??平仓记录格式化??? `modify_time` 排序后，该真实接口测试??氳繃銆?
- RED锛歚AdminTradeMt4PositionModuleTest::test_trade_frontends_expose_legacy_closed_position_filters_and_comment_columns` 首次失败，命??? Layui Blade 缺少 `ticket/start_date/end_date/is_coercion`銆?
- GREEN锛氳ˉ榻? Layui銆丆rmUI銆丯aive 三套前端配置后，该前端契约测试??氳繃銆?

### 剩余边界

- 当前真实 `mt4_trades` 表仍无旧项目 `MARGIN_RATE` 字段，本轮不伪??? `MARGIN_RATE <> 0` 过滤???
- 交易明细下钻、风险联动和代理范围细节仍需继续按旧项目逐批迁移；历史成交导出已在第 366 节补齐???

## 365. 2026-07-25 后台交易实盘/测试??? orderType 筛??夐棴鐜?

### 完成范围

- `TradeController` 新增旧项目测试盘分组后缀常量：`-TEST`銆乣-TEST-P`，对应旧 `MY_Controller::TEST_DISK` 鍜? `MY_Controller::TEST_DISK_P`銆?
- `tradeList`銆乣openPositions`銆乣closedPositions` 三个后台交易入口统一读取 `orderType` 鎴? `order_type` 参数，避免三处接口产生不同实???/测试盘口径???
- `orderType=test_disk` 只返回关??? `user_infos.mt4_group` 浠? `-TEST` 鎴? `-TEST-P` 结尾??? MT4 交易记录???
- `orderType=real_disk` 排除测试盘用户，保留没有迁移??? `user_infos` 的历??? MT4 记录，承接旧项目“非测试组即真实盘???的筛???语义???
- Layui銆丆rmUI 鍜? Naive 三套后台入口均补齐实???/测试盘筛选控件，选项文案通过中英文语???包维护，图标继续统一??? Lucide 渲染???

### 路由与执行链

- `POST /api/admin/tradeList` / `admin_api_tradeList` 鈫? `TradeController::index` 鈫? `validateUserIdFilter` 鈫? `baseMt4TradeQuery` 鈫? `applyTradeFilters` 鈫? `applyOrderTypeFilter` 鈫? `applyDataScope` 鈫? `paginatedTradeRecords` 鈫? `success(['records','summary'])`銆?
- `POST /api/admin/openPositions` / `admin_api_openPositions` 鈫? `TradeController::openPositions` 鈫? 追加未平仓条??? `close_time is null or 0` 鈫? 同一 `applyOrderTypeFilter` 鈫? 返回当前持仓分页和汇总???
- `POST /api/admin/closedPositions` / `admin_api_closedPositions` 鈫? `TradeController::closedPositions` 鈫? 追加已平仓条??? `close_time > 0` 鈫? 同一 `applyOrderTypeFilter` 鈫? `applyForceCloseFilter` 鈫? 返回历史平仓分页和汇总???

### 参数和返回结果中文含???

- `orderType=all` 或空值：不区分实???/测试盘，返回其它筛???条件命中的全部交易记录???
- `orderType=test_disk`：只返回测试盘记录；执行结果代表该交易账号的 `user_infos.mt4_group` 后缀命中??? `-TEST` 鎴? `-TEST-P`銆?
- `orderType=real_disk`：只返回真实盘记录；执行结果代表该交易账号未命中测试盘后???，或该历??? MT4 记录尚无可关联用户资料???
- `records.data[].ticket`锛歁T4 订单号，用于核对实盘/测试盘筛选后具体返回了哪些订单???
- `summary.total_orders`：当前筛选命中的订单总数，测试盘/真实盘切换后会同步变化???
- `summary.total_profit`：当前筛选命中的盈亏合计，用于验证订单分组后的金额汇总是否闭环???

### 为什么这样做

- 旧项目???过 `data_list.mt4_grp REGEXP '.*-TEST$|.*-TEST-P$'` 区分测试盘；新项目没有继续使用旧 `data_list.mt4_grp` 作为主链路，因此必须选择已经迁移且能关联 MT4 登录号的 `user_infos.mt4_group`銆?
- 三个交易接口共用 `applyTradeFilters`锛屽啀鐢? `applyOrderTypeFilter` 统一挂载分组条件，解决交易列表???当前持仓???历史平仓同???筛???参数返回结果不???致的问题???
- `real_disk` 使用排除测试盘关系的方式，是为了保留旧项目中“不在测试组子查询内即归入真实盘”的历史数据语义，避免??? MT4 订单因为资料缺失被错误丢弃???

### 测试记录

- RED锛歚AdminTradeMt4PositionModuleTest::test_trade_order_type_filter_uses_user_mt4_group_suffixes` 首次失败，`orderType=test_disk` 返回了真实盘订单，说明接口尚未承接旧测试盘后???规则???
- RED锛歚AdminTradeMt4PositionModuleTest::test_trade_frontends_expose_legacy_closed_position_filters_and_comment_columns` 首次失败，Layui Blade 缺少 `name="orderType"` 筛???控件???
- GREEN：补齐控制器筛??夈??前端筛选项和语???包后，上述两个目标测试均通过???

### 剩余边界

- 当前真实 `mt4_trades` 表仍无旧项目 `MARGIN_RATE` 字段，本轮不伪??? `MARGIN_RATE <> 0` 过滤???
- 本轮没有新增交易明细下钻、真??? MT4 服务器同步或数据库结构调整???

## 366. 2026-07-25 后台交易历史平仓当前筛??? CSV 导出闭环

### 完成范围

- 新增 `POST /api/admin/exportClosedPositions` / `admin_api_exportClosedPositions`，用于导出当前筛选命中的历史平仓记录???
- `TradeController::closedPositions` 涓? `TradeController::exportClosedPositions` 共用 `closedPositionsQuery`，保证列表???汇总和导出使用同一套旧项目筛???口径???
- CSV 导出字段包含 `ticket`銆乣login`銆乣symbol`銆乣cmd`銆乣volume`銆乣commission`銆乣swaps`銆乣profit`銆乣comment`銆乣ordercomment`銆乣open_time`銆乣close_time`銆乣modify_time`銆?
- Layui 后台新增 `exportClosedPositions` 按钮，携带当前搜索表单参数导出；CrmUI 鍜? Naive 后台同步配置统一导出入口???
- 权限迁移补齐 `admin_closed_positions_export`，并绑定 `admin_api_exportClosedPositions`，避免导出接口绕过后台权限体系???

### 路由与执行链

- `POST /api/admin/exportClosedPositions` / `admin_api_exportClosedPositions` 鈫? `TradeController::exportClosedPositions` 鈫? `validateUserIdFilter` 鈫? `closedPositionsQuery` 鈫? `baseMt4TradeQuery` 鈫? `applyTradeFilters` 鈫? `applyOrderTypeFilter` 鈫? `applyForceCloseFilter` 鈫? `applyDataScope` 鈫? `orderByTradeTime(modify_time)` 鈫? `formatTradeRecord` 鈫? `csvDownload('closed_positions_export.csv')`銆?
- `closedPositionsQuery` 固定追加 `close_time > 0`，表示只导出已平仓记录；导出???多返??? 5000 行，避免???次拉取过多历??? MT4 数据拖慢后台???

### 参数和返回结果中文含???

- `user_id/userId`锛歁T4 登录账号，导出前先做严格整数校验；非法时返回校验失败，不输出 CSV銆?
- `ticket/orderId`锛歁T4 订单号模糊筛选，用于按旧后台订单号快速定位历史成交???
- `symbol/sym_symbol`：交易品种筛选，用于只导出指定产品的历史平仓???
- `start_date/startdate`銆乣end_date/enddate`：平仓日期范围，对应 `mt4_trades.close_time`銆?
- `is_coercion=Yes`：只导出 COMMENT 浠? `so` 寮?头的强平单；`No` 表示导出非强平单???
- `orderType=test_disk`：只导出 `user_infos.mt4_group` 浠? `-TEST` 鎴? `-TEST-P` 结尾的测试盘订单；`real_disk` 表示排除测试盘???
- `comment`锛歁T4 原始备注；`ordercomment`：旧项目 Blade 字段名，值与 `comment` 涓?致，便于旧报表字段对照???
- `closed_positions_export.csv`：浏览器下载文件名，表示本次返回的是历史平仓当前筛???结果???

### 为什么这样做

- 旧项目平仓列表依??? `closeListSearch/closeListSearchV2` 的查询条件，后台实际导出???要和列表结果???致；把查询封装为 `closedPositionsQuery` 可以消除“列表一套条件???导出另???套条件???的风险???
- CSV 使用当前真实 `mt4_trades` 字段，不伪???旧项目尚不存在??? `MARGIN_RATE`，让缺失字段继续作为真实剩余边界暴露???
- Layui銆丆rmUI銆丯aive 共用同一个导出路由，解决三套后台入口能力不一致的问题???

### 测试记录

- RED锛歚AdminTradeMt4PositionModuleTest::test_closed_positions_export_route_permission_and_frontends_are_wired` 首次失败，提??? `admin_api_exportClosedPositions` 路由不存在???
- RED锛歚AdminTradeMt4PositionModuleTest::test_closed_positions_export_endpoint_returns_current_filter_csv` 首次失败，`/api/admin/exportClosedPositions` 返回 404銆?
- GREEN：补齐路由???控制器导出、权限迁移??丩ayui 按钮、CrmUI/Naive 导出配置后，两个导出测试均??氳繃銆?

### 剩余边界

- 当前真实 `mt4_trades` 表仍无旧项目 `MARGIN_RATE` 字段，本轮不伪??? `MARGIN_RATE <> 0` 过滤???
- 本轮没有新增交易明细下钻、风险联动??佺湡瀹? MT4 服务器同步或数据库结构调整???

## 367. 2026-07-26 后台权益汇???在线结算金额统计闭???

### 完成范围

- `RightsSummaryController::rightsSummaryList` 在原有账户数、余额???净值???保证金汇??诲熀纭?上新增线上结算金额字段???
- 新增 `online_settlement_deposit_amount`：当前筛选范围内 `deposit_records.status=02` 的已支付入金金额，优先取 `actual_amount`，为 0 时回??? `amount`銆?
- 新增 `online_settlement_withdraw_amount`：当前筛选范围内 `withdraw_records.status=2` 的已完成出金金额，优先取 `actual_amount`，为 0 时回??? `apply_amount`銆?
- 新增 `online_settlement_commission_amount`：当前筛选范围内 `commission_records.settle_status=2` 的已结算返佣金额，优先取 `real_amount`，为 0 时回??? `commission_amount`銆?
- 新增 `online_settlement_net_amount`：按“已支付入金 - 已完成出??? + 已结算返佣???计算当前筛选范围的在线???结算额???
- Layui 权益汇???页面新??? 4 个汇总卡片，Naive 后台新增同名 `summaryFields`，后端语???包和前端共享语言包补齐字段文案???

### 路由与执行链

- `POST /api/admin/rightsSummaryList` / `admin_api_rightsSummaryList` 鈫? `RightsSummaryController::rightsSummaryList` 鈫? `validateNumericFilters` 鈫? `baseRightsQuery` 鈫? `applyFilters` 鈫? `AdminDataScopeService::apply` 鈫? `summaryFor` 鈫? `scopedUserIdQuery` 鈫? `sumScopedOnlineDepositAmount` / `sumScopedOnlineWithdrawAmount` / `sumScopedOnlineCommissionAmount` 鈫? `paginate` 鈫? `success(['records','summary'])`銆?
- `scopedUserIdQuery` 使用已经追加筛???和后台数据范围??? `rights_scope` 子查询，只取非空 `user_infos.user_id`，解决汇总金额与列表范围不一致的问题???

### 参数和返回结果中文含???

- `mt4_group`锛歁T4 分组筛???；本轮测试用它证明列表外用户的入金、出金和返佣不会进入 summary銆?
- `online_settlement_deposit_amount`：已支付入金合计，返??? 0 表示当前筛???范围没有已完成入金记录???
- `online_settlement_withdraw_amount`：已完成出金合计，返??? 0 表示当前筛???范围没有完成出金记录???
- `online_settlement_commission_amount`：已结算返佣合计，返??? 0 表示当前筛???范围没有已结算返佣记录???
- `online_settlement_net_amount`：在线净结算额；正数表示当前范围线上???流入，负数表示当前范围线上净流出???

### 为什么这样做

- 旧项??? `sum_agents_online_settlement_amount()` 会先统计返佣、入金和出金，再生成权益结算数据；新项目当前不具备可安全自动执行??? MT4 写入边界，因此本轮只补只读金额统计，不伪造自动结算成功???
- 三张资金表都按当前权益列表范围内的业务用??? ID 聚合，避免???页面看到一个范围，汇???卡片统计另???个范围???的财务对账风险???
- 使用实际完成状???过滤，解决待支付入金???待处理出金、待结算返佣被误计入在线结算金额的问题???

### TDD 执行记录

- RED锛歚php artisan test tests\Feature\AdminRightsSummaryModuleTest.php --filter test_rights_summary_summary_includes_online_settlement_amounts_for_current_scope` 首次失败，`online_settlement_deposit_amount` 返回 0，说明后端未返回在线结算金额???
- GREEN锛氳ˉ榻? `summaryFor` 的三张资金表聚合和当前范围用??? ID 子查询后，该接口测试通过???
- RED锛歚php artisan test tests\Feature\AdminRightsSummaryModuleTest.php --filter test_rights_summary_page_renders_blade_controls` 首次失败，Blade 缺少 4 涓? `data-summary-field`銆?
- GREEN锛氳ˉ榻? Layui 汇???卡片??佽瑷?包??丯aive `summaryFields` 后，页面和前端契约测试??氳繃銆?

### 剩余边界

- 本轮没有迁移旧项目自动确认出入金逻辑，不调用 MT4 入金或出金接口???
- 本轮没有新增真实 MT4 自动同步任务，也不改??? `rights_settlements` 写入状??併??
- 后续若要恢复自动结算，必须先按真??? MT4 网关、幂等记录和失败重试链路单独 TDD 闭环???

## 368. 2026-07-26 后台持仓汇???代理树交易汇??婚棴鐜?

### 完成范围

- `PositionSummaryController::positionSummaryList` 从???只按当前行用户自身 MT4 登录号汇总???升级为“按展示行用户拥有的成员范围汇??烩?濄??
- 展示行用户本身会映射??? `owner_user_id = member_user_id`，保证普通客户和无下级代理仍能看到自己的持仓汇??汇??
- 代理下级成员优先读取 `agent_descendants.agent_id / descendant_id` 闭包表，承接新项目已有代理树关系???
- 当闭包表缺失旧迁移数据时，使??? `user_infos.family_tree` 匹配 `,代理ID,` 兜底汇???下级客户交易，避免旧项目只迁入家族链字段时代理行统计为 0銆?
- 成员映射使用 `union` 去重，解决闭包表??? `family_tree` 同时命中同一客户时订单数、手数???盈亏被重复累加的问题???
- CrmUI 涓? Naive 后台持仓汇???列已同步改??? `user_id`銆乣user_name`銆乣parent_id`銆乣account_type`銆乣mt4_group` 鍜? `total_*` 聚合字段，避免继续使??? `symbol/volume/profit/updated_at` 这种单笔订单明细列???

### 路由与执行链

- `POST /api/admin/positionSummaryList` / `admin_api_positionSummaryList` 鈫? `PositionSummaryController::positionSummaryList` 鈫? `validateNumericFilters` 鈫? `buildTradeSummarySubquery` 鈫? `buildPositionScopeSubquery` 鈫? `buildOwnerTradeSummarySubquery` 鈫? `buildUserSummaryQuery` 鈫? `applyFilters` 鈫? `AdminDataScopeService::apply` 鈫? `positionSummaryTotals` 鈫? `paginate` 鈫? `success(['records','summary'])`銆?
- `POST /api/admin/exportPositionSummary` / `admin_api_exportPositionSummary` 鈫? `PositionSummaryController::exportPositionSummary` 鈫? 同一筛???和代理树汇总查询链??? 鈫? `formatRow` 鈫? `csvDownload('position_summary_export.csv')`銆?
- `GET /admin-crmui/position-summary` 鈫? `CrmUi\Admin\PageController::show` 鈫? `positionSummaryColumns` / `positionSummaryMetrics` 鈫? 渲染 CrmUI 后台表格列和指标???
- Naive 后台进入 `position-summary` 页面 鈫? `public/js/apps/naive-admin/front-plain.js` 页面配置 鈫? 请求 `/api/admin/positionSummaryList` 鈫? 按代理树汇???字段渲染表格和汇???指标???

### 参数和返回结果中文含???

- `user_id`：筛选展示行用户 ID；命中代理时返回该代理及下级客户交易汇???，命中客户时返回客户自身交易汇总???
- `user_name`：展示行用户名称，用于后台快速识别代理或客户???
- `parent_id`：展示行用户上级 ID，用于核对代理树关系来源???
- `account_type`：账号类型，`1` 表示代理，其它???表示普通客户或业务账号???
- `mt4_group`：用户资料中??? MT4 分组，用于后续联动实???/测试盘或??? MT4 用户资料???
- `total_orders`：当前行用户成员范围??? MT4 交易订单数量；返??? 0 表示该范围内没有命中交易???
- `total_volume`：当前行用户成员范围内交易手数合计，用于核对持仓规模???
- `total_profit`：当前行用户成员范围内盈亏合计；正数表示盈利，负数表示亏损???
- `total_comm`：当前行用户成员范围内手续费合计???
- `total_swaps`：当前行用户成员范围内库存费合计???
- `total_noble_metal`銆乣total_for_exca`銆乣total_crud_oil`銆乣total_index`銆乣total_currency`銆乣total_stock`：按 `symbol_prices` 品种分类统计出的不同产品类别手数???
- `summary.total_accounts`：当前筛选和数据范围下返回的用户行数量???

### 为什么这样做

- 旧项目持仓汇总面向代理树统计，不是单个用户明细订单列表；只用 `user_infos.user_id = mt4_trades.login` 会让代理行漏掉下级客户交易???
- 新项目已经有 `agent_descendants` 闭包表，但旧迁移数据可能只保??? `family_tree`，所以需要双路径读取，才能兼容新旧代理关系来源???
- 聚合前先构??? owner/member 映射，再汇??? MT4 交易，可以让列表、汇总卡片和 CSV 导出复用同一套业务口径，避免页面和导出对账不???致???
- 前端列配置必须与接口返回字段???致；继续渲染 `symbol/volume/profit` 会把代理树汇总误导成订单明细，影响后台判断???

### TDD 执行记录

- RED锛歚php artisan test tests\Feature\AdminPositionSummaryModuleTest.php --filter test_position_summary_rolls_up_descendant_customer_trades_to_agent_row` 首次失败，代理行 `total_orders=0`，证明未汇??? `agent_descendants` 下级客户交易???
- GREEN锛氭柊澧? owner/member 映射和代理闭包表汇???后，该测试通过???
- RED锛歚php artisan test tests\Feature\AdminPositionSummaryModuleTest.php --filter test_position_summary_uses_family_tree_fallback_when_descendant_rows_are_missing` 首次失败，闭包表缺失时代理行仍为 0銆?
- GREEN锛氳ˉ榻? `user_infos.family_tree` 兜底路径并用 `union` 去重后，该测试??氳繃銆?
- RED锛歚php artisan test tests\Feature\FrontUiRegressionTest.php --filter test_admin_position_summary_rollup_fields_are_wired_across_crmui_and_naive` 首次失败，CrmUI 缺少 `data-key="user_name"`，证明前端仍是旧明细列???
- GREEN锛欳rmUI 涓? Naive 同步真实汇???列和指标后，前端契约测试??氳繃銆?
- RED锛歚php artisan test tests\Feature\AdminLegacyMigrationGapAuditTest.php --filter test_audit_document_does_not_keep_stale_position_summary_agent_tree_gap_text` 首次失败，审计文档缺??? `family_tree` 证据并保留旧代理树缺口描述???
- GREEN：更新迁移缺口审计文档后，该防回???测试通过???

### 剩余边界

- 当前真实 `mt4_trades` 表仍无旧项目 `MARGIN_RATE` 字段，本轮不伪??? `MARGIN_RATE <> 0` 过滤???
- 本轮没有联动旧项??? `MT4_USERS` 的更多账户资料字段，也没有新增交易明细下钻或风险联动???
- 后续若继续补深层下钻，必须先确认真实路由、真实权限???明细字段和代理数据范围，再??? TDD 单独闭环???

## 369. 2026-07-26 后台持仓汇???旧下级代理入口语义闭环

### 完成范围

- 修正旧后台兼容路??? `index/admin/order/v2/subAgentsListSearchV2` 的现代目标，从纯代理树列??? `admin_api_agentDescendants` 改回持仓汇??绘帴鍙? `admin_api_positionSummaryList`銆?
- `PositionSummaryController::positionSummaryList` 兼容旧参??? `searchtype=subAgentsSearch` 涓? `userPId/user_pid`銆?
- 当旧参数命中时，接口只返??? `userPId` 当前代理自身和直属下级代理行；每???行仍复用??? 368 节的代理树交易汇总口径???
- `exportPositionSummary` 复用同一筛???链路，因此旧参数下页面列表??? CSV 导出保持???致???

### 路由与执行链

- `POST /index/admin/order/v2/subAgentsListSearchV2` 鈫? `LegacyAdminController::handle` 鈫? `targetRouteFor` 鈫? `admin_api_positionSummaryList` 鈫? `payloadForLegacyTarget` 保留 `searchtype/userPId` 鈫? `forwardToNamedRoute` 鈫? `POST /api/admin/positionSummaryList`銆?
- `POST /api/admin/positionSummaryList` 鈫? `PositionSummaryController::positionSummaryList` 鈫? `validateNumericFilters(userPId/user_pid)` 鈫? `legacySubAgentsParentId` 鈫? `applyUserFilters` 追加 `account_type=1` 涓? `(user_id=userPId OR parent_id=userPId)` 鈫? 代理树交易汇??? 鈫? 分页??? summary 返回???

### 参数和返回结果中文含???

- `searchtype=subAgentsSearch`：旧后台下级代理持仓汇???模式，表示查看某个代理及其直属下级代理???
- `userPId/user_pid`：旧后台传入的父级代??? ID，过滤目标代理自身和直属下级代理???
- `records.data[]`：当前代理与直属下级代理的汇总行；每行的 `total_orders`銆乣total_volume`銆乣total_profit` 等仍表示该代理完整成员范围内??? MT4 交易聚合???
- `summary`：当前筛选出来的这些代理行的汇???合计，用于页面顶部卡片???

### 为什么这样做

- 旧项??? `subAgentsListSearchV2` 的业务结果是“下级代理持仓汇总???，不是“代理树成员清单”；转到 `admin_api_agentDescendants` 会丢失交易金额???手数???盈亏和品种分类???
- 旧参数只??? `searchtype=subAgentsSearch` 时生效，避免影响普??? `user_id`銆乣parent_id` 或全量持仓汇总筛选???
- 同时支持 `userPId` 鍜? `user_pid`，是为了兼容??? Blade、旧 Ajax 和可能存在的蛇形参数转发???

### TDD 执行记录

- RED锛歚php artisan test tests\Feature\AdminLegacyRouteSemanticClosureTest.php --filter test_high_risk_legacy_uris_map_to_semantic_targets` 首次失败，旧 URI 实际目标??? `admin_api_agentDescendants`銆?
- GREEN锛歚LegacyAdminController` 灏? `index/admin/order/v2/subAgentsListSearchV2` 改为 `admin_api_positionSummaryList` 后，路由语义测试通过???
- RED锛歚php artisan test tests\Feature\AdminPositionSummaryModuleTest.php --filter test_position_summary_legacy_sub_agents_search_returns_parent_and_direct_agent_rollups` 首次失败，旧参数未生效，接口返回其它全量用户行???
- GREEN锛氳ˉ榻? `legacySubAgentsParentId` 和旧下级代理筛???后，接口只返回当前代理和直属下级代理两行，并且两行都汇总下级客户交易???
- RED锛歚php artisan test tests\Feature\AdminLegacyMigrationGapAuditTest.php --filter test_audit_document_records_legacy_admin_position_sub_agents_route_semantics` 首次失败，审计文档未记录 `searchtype=subAgentsSearch` 涓? `userPId` 语义???
- GREEN：更新迁移缺口审计文档后，该防回???测试通过???

### 剩余边界

- 本轮只修正旧后台下级代理持仓汇???入口，不新增交易明细下钻页面???
- 鏃? `MARGIN_RATE` 鍜? `MT4_USERS` 深层字段仍按??? 368 节边界继续保留???

## 369.1. 2026-07-28 后台持仓汇???代理钻取前端闭???

### 本次处理目标

- 补齐旧项??? `position_summary_list_v2.blade.php` 中点击代理行继续查看直属下级持仓汇???的前端入口???
- 璁? Layui 后台??? CrmUI 后台都能把前端点击转换为后端已兼容的 `searchtype=subAgentsSearch` 涓? `userPId` 参数???
- 保证列表刷新、当前筛??? CSV 导出和返回根级时使用同一套筛选参数，避免页面列表与导出结果不???致???

### 本次变更文件

- `tests/Feature/AdminPositionSummaryDrilldownFrontendClosureModuleTest.php`：新增前端契约测试，先约??? Layui 隐藏字段、路径容器???钻取事件和 CrmUI 本地行操作声明???
- `resources/admin/layui/position-summary/index.blade.php`锛氭柊澧? `searchtype`銆乣userPId` 隐藏字段、`positionSummaryPath` 路径条和 Lucide 图标按钮???
- `public/js/apps/admin/layui/pages.js`锛氭柊澧? `positionSummaryDrilldown`銆乣currentPositionSummaryFilters`、路径更新???返回根级和 Lucide 渲染逻辑???
- `app/Http/Controllers/CrmUi/Admin/PageController.php`：为 `position-summary` 增加 `position_summary_drilldown` 本地行操作，并???传 `extraPayload`銆?
- `resources/admin/crmui/partials/module-page.blade.php`：行操作按钮新增 `data-extra-payload`，用于把固定业务参数交给前端脚本???
- `public/js/apps/crmui/admin.js`：新增本地行操作扩展参数解析、页面附加筛选合并???持仓汇总代理钻取重载和重置清理逻辑???
- `resources/lang/zh-CN/crmui.php`銆乣resources/lang/en/crmui.php`锛氳ˉ榻? CrmUI 行操作和确认文案，避免显示原??? key銆?
- `docs/admin-legacy-migration-gap-audit.md`：更新持仓汇总迁移证据和剩余边界???

### 路由与执行链

- Layui 页面：`GET /admin/position-summary` 鈫? `resources/admin/layui/position-summary/index.blade.php` 鈫? 用户点击代理??? `lay-event="positionSummaryDrilldown"` 鈫? `public/js/apps/admin/layui/pages.js::positionSummaryDrilldown` 鈫? 写入隐藏字段 `searchtype=subAgentsSearch`銆乣userPId=当前代理 user_id` 鈫? `POST /api/admin/positionSummaryList` 鈫? `PositionSummaryController::positionSummaryList` 鈫? 旧下级代理模式筛选当前代理和直属下级代理 鈫? 返回 `records + summary`銆?
- Layui 导出：页面处于钻取状??? 鈫? `exportPositionSummary` 调用 `currentPositionSummaryFilters` 鈫? `POST /api/admin/exportPositionSummary` 鈫? 后端复用同一筛??夐摼璺? 鈫? 返回 `position_summary_export.csv`銆?
- CrmUI 页面：`GET /admin-crmui/position-summary` 鈫? `CrmUi\Admin\PageController::show` 鈫? `rowActions` 声明 `position_summary_drilldown` 鈫? `module-page.blade.php` 输出 `data-extra-payload` 鈫? 用户点击代理行按??? 鈫? `public/js/apps/crmui/admin.js::positionSummaryDrilldown` 鈫? 写入页面附加筛??? `searchtype/userPId` 鈫? `loadPage` 重载 `admin_api_positionSummaryList`銆?

### 参数和返回结果中文含???

- `searchtype=subAgentsSearch`：旧后台下级代理持仓汇???模式，表示本次查询不是全量列表，???是查看某个父代理节点???
- `userPId`：旧后台父代??? ID，取自被点击行的 `row.user_id`銆?
- `records.data[]`：当前父代理自身和直属下级代理的持仓汇??昏銆?
- `summary`：当前钻取筛选结果的总账号???订单???手数???盈亏???手续费和库存费合计???
- 绌? `searchtype/userPId`：表示返回普通持仓汇总筛选，不进入旧下级代理模式???

### 为什么这样做

- 后端已经兼容??? `subAgentsSearch` 语义，但前端没有入口会导致业务人员只能看全量汇???，无法像旧后台???样???层核对代理持仓???
- Layui 使用隐藏字段保存当前上下文，是为了让查询、刷新和导出天然复用同一份表单参数???
- CrmUI 使用 `extraPayload`，是为了让???用行操作组件保留扩展能力，同时不把持仓汇???的旧参数硬编码??? Blade 模板???
- 非代理行不显示钻取按钮，避免普???客户账号误触发代理下级查询???

### TDD 执行记录

- RED锛歚vendor\bin\phpunit tests\Feature\AdminPositionSummaryDrilldownFrontendClosureModuleTest.php` 首次失败，Layui 缂? `positionSummaryPath`锛孋rmUI 缂? `position_summary_drilldown` 行操作???
- GREEN锛氳ˉ榻? Blade 隐藏参数、Layui 行事件??丆rmUI 本地行操作和多语???后，同一测试通过???
- 静???检查：`node --check public\js\apps\admin\layui\pages.js`銆乣node --check public\js\apps\crmui\admin.js`銆乣php -l app\Http\Controllers\CrmUi\Admin\PageController.php`銆乣php -l resources\lang\zh-CN\crmui.php`銆乣php -l resources\lang\en\crmui.php` 均已通过???

### 剩余边界

- 本轮只完成旧后台代理持仓汇???前端钻取，不新增交易明细下钻页面???
- 鏃? `MT4_USERS` 更多资料字段、旧 `MARGIN_RATE` 过滤口径、明细下钻和风险联动仍按持仓/平仓深层迁移边界继续推进???

## 372. 2026-07-28 后台持仓汇总 MT4 账户快照联动闭环

### 本次处理目标

- 关闭后台持仓汇???中 `MT4_USERS` 未联动的迁移缺口，明确使??? `user_infos.mt4_code = mt4_users.login` 读取真实 MT4 账户快照???
- 验证列表、当前筛??? CSV 导出、Layui 后台页面??? CrmUI 后台页面都展示同???濂? MT4 快照字段???
- 验证顶部汇???只统计当前筛???命中的业务用户，避免把筛???范围外??? MT4 账号余额、净值???保证金混入结果???

### 本次变更文件

- `tests/Feature/AdminPositionSummaryMt4AccountLinkageClosureModuleTest.php`
  - 新增真实接口、CSV、前端契约和迁移文档证据四类样例，固??? MT4 账号映射、筛选范围和???终清单记录???
- `app/Http/Controllers/Admin/PositionSummaryController.php`
  - 列表查询在用户汇总行上左??? `mt4_users`锛岃繑鍥? `mt4_login`銆乣mt4_name`銆乣mt4_account_group`、余额???净值???保证金、可用保证金、杠杆和快照时间???
  - CSV 导出复用同一条查询链路，保证页面看到???么，财务下载就得到什么???
- `resources/admin/layui/position-summary/index.blade.php`
  - 顶部汇???卡片新??? `total_mt4_accounts`銆乣total_balance`銆乣total_equity`銆乣total_margin`銆乣total_margin_free`銆?
- `public/js/apps/admin/layui/pages.js`
  - 持仓汇???表格新??? MT4 快照列，并用 Lucide 统一页面操作图标???
- `app/Http/Controllers/CrmUi/Admin/PageController.php`
  - CrmUI 持仓汇???列和指标同步新??? MT4 快照字段，避免另???套后台入口继续缺列???
- `public/js/apps/crmui/admin.js`
  - 指标渲染兼容后端 `data.summary`锛岀‘淇? CrmUI 顶部卡片读取当前筛???合计???
- `docs/admin-legacy-migration-gap-audit.md`
  - 更新持仓汇???迁移状态，??? MT4 账户快照联动从剩余缺口改为已闭环证据???

### 路由与执行链

- `POST /api/admin/positionSummaryList` / `admin_api_positionSummaryList` -> `PositionSummaryController::positionSummaryList` -> `validateNumericFilters` -> `baseUserQuery` -> `leftJoin mt4_users on user_infos.mt4_code = mt4_users.login` -> `applyUserFilters` -> `AdminDataScopeService` -> `paginate` -> `summaryFor` -> `success(records + summary)`銆?
- `POST /api/admin/exportPositionSummary` / `admin_api_exportPositionSummary` -> `PositionSummaryController::exportPositionSummary` -> 与列表接口复用相同筛选和 MT4 联动查询 -> 写出 `mt4_login` 绛? CSV 表头和??? -> 返回 `position_summary_export.csv`銆?
- `GET /admin/position-summary` / `admin_page_position_summary` -> Blade 页面输出汇??诲崱鐗? -> `public/js/apps/admin/layui/pages.js` 渲染表格??? -> 请求 `admin_api_positionSummaryList` -> 鐢? `records.data[]` 更新表格，用 `summary` 更新 MT4 资金卡片???
- `GET /admin-crmui/position-summary` -> `CrmUi\Admin\PageController::show` -> 输出列定义和 `data-crmui-metric` 指标 -> `public/js/apps/crmui/admin.js` 请求同一列表接口 -> 浠? `data.summary` 填充当前筛???合计???

### 参数和返回结果中文含???

- `user_id`：业务用??? ID，用于筛选单个持仓汇总行；命中后只返回该用户对应??? MT4 快照???
- `user_infos.mt4_code`：业务用户绑定的真实 MT4 登录号，是本轮唯???可信??? MT4 账户映射来源???
- `mt4_login`锛氱湡瀹? MT4 登录账号；为空表示当前业务用户没有可匹配??? MT4 快照???
- `mt4_balance`锛歁T4 当前余额，表示账户资金余额???
- `mt4_equity`锛歁T4 当前???值，表示余额叠加浮动盈亏后的资金状??併??
- `mt4_margin`锛歁T4 已用保证金，表示当前持仓占用保证金???
- `mt4_margin_free`锛歁T4 可用保证金，表示还能用于???仓或抗风险的保证金???
- `total_mt4_accounts`：当前筛选结果中成功关联 MT4 快照的账号数量???
- `total_balance/total_equity/total_margin/total_margin_free`：当前筛选结果对??? MT4 快照资金字段合计，不统计筛???范围外账号???

### 为什么这样做

- 旧项目持仓汇总依??? `MT4_USERS` 账户资金状???；新项目只展示交易聚合会缺少余额???净值和保证金核对依据???
- 业务用户 ID 鍜? MT4 登录号可能不同，必须??? `user_infos.mt4_code = mt4_users.login` 映射，不能用 `user_infos.user_id` 猜测交易账号???
- 列表、导出??丩ayui 鍜? CrmUI 复用同一后端口径，可以避免页面与财务 CSV 结果不一致???
- 当前真实表仍没有旧项??? `MARGIN_RATE` 字段，因此本轮只关闭 MT4 快照联动，不伪???实盘过滤???交易明细下钻或风险联动???

### TDD 执行记录

- RED锛歚vendor\bin\phpunit tests\Feature\AdminPositionSummaryMt4AccountLinkageClosureModuleTest.php` 首次运行失败，命??? CSV 缺少 MT4 快照字段、Layui/CrmUI 缺少展示列???迁移审计缺??? `user_infos.mt4_code = mt4_users.login` 证据???
- GREEN：补齐后??? MT4 联动字段、CSV 表头和??笺?丩ayui/CrmUI 列和指标、多语言文案、迁移审计以及本节最终清单后，目标测试??氳繃銆?

### 剩余边界

- 当前真实 `mt4_trades` 表仍无旧项目 `MARGIN_RATE` 字段，本轮不伪??? `MARGIN_RATE <> 0` 过滤???
- 本轮没有新增交易明细下钻或风险联动页面；这些仍需按真实路由???权限???字段和旧项目执行链单独闭环???

## 370. 2026-07-28 后台返佣列表 settle_status 筛???严格校验闭???

### 本次处理目标

- 涓? `CommissionController::index` 补齐返佣列表 `settle_status` 结算状???筛选严格校验测试???
- 验证 `/api/admin/commissionList` 不能??? `settle_status=1abc`銆乣3` 鎴? `-1` 下推??? `commission_records.settle_status` 查询???
- 验证非法结算状??佽繑鍥? `ResponseCode::VALIDATION_FAILED`，避免后台返佣列表按宽松状??佸??返回真实返佣记录???

### 本次变更文件

- `tests/Feature/AdminCommissionListSettleStatusValidationClosureModuleTest.php`
  - 新增返佣列表非法 `settle_status` 筛???被拒绝且不返回测试返佣记录唯一编号的样例???
  - 使用 `commission-list-settle-status-validation-%` 作为测试记录前缀，测试结束后清理专用返佣记录???
- `app/Http/Controllers/Admin/CommissionController.php`
  - `index` 在构??? `commission_records` 查询前调??? `validateSettleStatusFilter()`銆?
  - `validateSettleStatusFilter()` 使用字符串精确枚举，只允??? `1=待结算`、`2=已结算`，拒??? `1abc`銆乣3`銆乣-1` 和其它非旧项目状态??笺??

### 路由与执行链

- `POST /api/admin/commissionList` / `admin_api_commissionList` 鈫? `CommissionController::index` 鈫? `validateAgentIdFilter` 鈫? `validateSettleStatusFilter` 鈫? `CommissionRecord::query()->with(['agent','parent'])` 鈫? `AdminDataScopeService::apply` 鈫? `where agent_id` 鈫? `where commission_records.settle_status` 鈫? `paginate` 鈫? `success(records)`銆?

### 参数和返回结果中文含???

- `settle_status=1`：只查询待结算返佣记录，返回结果代表该返佣尚未执行后台结算???
- `settle_status=2`：只查询已结算返佣记录，返回结果代表该返佣已完成结算状???更新???
- `settle_status=1abc`銆乣3`銆乣-1`锛氳繑鍥? `ResponseCode::VALIDATION_FAILED`，中文含义为参数校验失败，接口不会继续读??? `commission_records`銆?
- `records.data[]`：合法筛选成功时的返佣分页数据，包含代理和父级代理关联信息???

### 为什么这样做

- 旧后台返佣列表只有待结算和已结算两个有效状???，新项目如果把任意字符串直接交给查询，会???成状???边界不清晰???
- 使用字符串精确枚举比宽松数字比较更安全，可以避免 `1abc` 或带前缀的???在 PHP/Laravel 层被错误理解为合法状态???
- 校验放在查询前，解决非法筛???仍可能读取真实返佣记录的问题，和前面资金???风控???交易模块的严格参数边界保持???致???

### TDD 执行记录

- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit tests\Feature\AdminCommissionListSettleStatusValidationClosureModuleTest.php --colors=never` 首次失败，三个非??? `settle_status` 均返??? `ResponseCode::SUCCESS`，证明控制器尚未拦截结算状???筛选???
- GREEN锛氳ˉ榻? `validateSettleStatusFilter()` 和第 370 节清单记录后，目标测试??氳繃銆?

### 剩余边界

- 本轮没有改动返佣金额计算、单笔结算???批量结算???返佣转账对账???返佣页面???权限字典???权限迁移或数据库结构???
- 后续继续按旧项目模块清单审计后台资金模块、代理商模块、后台管理员模块和后台普通用户模块其它剩余入口???

## 371. 2026-07-28 后台普???用户资料编??? MT4 同步闭环

### 本次处理目标

- 涓? `AdminUserController::updateUser` 补齐旧项??? `CustomerController::cust_save_info` 中交易组和杠杆编辑的核心闭环???
- 验证 `/api/admin/updateUser` 修改 `mt4_group` 鎴? `leverage` 时，必须先调??? `Mt4ManagerService::updateUserTradingProfile`銆?
- 验证 MT4 未返回明确成功时，本??? `user_infos.user_name`銆乣user_infos.phone`銆乣user_infos.mt4_group`銆乣user_infos.leverage` 都保持原值，并返??? `ResponseCode::MT4_SYNC_FAILED`銆?
- 验证只修改基???资料时不会触??? MT4 交易资料同步，但仍写??? `operation_logs` 审计记录???

### 本次变更文件

- `tests/Feature/AdminUserUpdateMt4SyncClosureModuleTest.php`
  - 新增 MT4 失败不落库??丮T4 成功后才落库并写审计日志、基???资料更新不触??? MT4 的三条真实接口样例???
  - 使用测试替身记录调用 MT4 时本地库中的旧???，证明同步发生在本地写入之前???
- `app/Http/Controllers/Admin/AdminUserController.php`
  - `updateUser` 扩展旧字段别名归???锛歚username`銆乣userphoneNo`銆乣usergrpName`銆乣cust_lvg`銆?
  - 继续保留字段白名单，只允许基???资料和明确经??? MT4 同步??? `user_infos.mt4_group`銆乣user_infos.leverage` 写入???
  - 成功写入后创??? `operation_logs`，记录字段新旧??笺??管理员、目标用户和来源 IP銆?

### 路由与执行链

- `POST /api/admin/updateUser` / `admin_api_updateUser` -> `AdminUserController::updateUser` -> `normalizedUserUpdatePayload` -> `Validator` -> `UserInfo::where(user_id)` -> `AdminDataScopeService::canAccessUser` -> `Mt4ManagerService::updateUserTradingProfile` -> `DB::transaction` -> `user_infos.update` -> `operation_logs.create` -> `success(user, UPDATED)`銆?
- `PATCH /api/admin/users/{user}` / `admin_api_updateUser` -> 同一 `AdminUserController::updateUser` 链路，区别是用户 ID 来自路由参数???

### 参数和返回结果中文含???

- `user_id`：业务用??? ID，用于定??? `user_infos.user_id`，不是后台管理员 ID銆?
- `user_name` / `username`：用户姓名或昵称，成功后写入 `user_infos.user_name`銆?
- `phone` / `userphoneNo`：联系电话；旧字??? `userphoneNo` 会按 `modules` 生成 `86-手机号` 格式???
- `mt4_group` / `usergrpName`锛氱洰鏍? MT4 交易组名称，提交该字段时必须先同??? MT4銆?
- `leverage` / `cust_lvg` / `is_enc`：目标杠杆；未传杠杆但传 `is_enc=1` 时按旧项??? ECN 口径转换??? 200，否则转换为 100銆?
- `ResponseCode::UPDATED`锛歁T4 同步成功或无??? MT4，同步后本地资料已更新???
- `ResponseCode::MT4_SYNC_FAILED`锛歁T4 网络、协议或业务响应未明确成功，本地资料未写入???
- `ResponseCode::VALIDATION_FAILED`锛氱敤鎴? ID、交易组、杠杆等参数不合法，接口不会继续写库???

### 为什么这样做

- 旧项目把 MT4 账户资料视为真实状???源，本地用户表只是镜像；先写本地再??? MT4 会???成后台看到的组???/杠杆与真实交易账户不???致???
- 使用???娆? `update_user` 同时设置 `grp+lvg`，避免组别成功但杠杆失败的部分成功状态???
- 基础资料更新不调??? MT4，可以减少无意义的外部调用；交易资料更新失败则整体阻断，保证普???用户编辑链路有明确失败边界???

### TDD 执行记录

- RED锛歚php vendor\bin\phpunit tests\Feature\AdminUserUpdateMt4SyncClosureModuleTest.php` 首次失败，接口返??? `UPDATED`，未调用 `Mt4ManagerService::updateUserTradingProfile`，也没有??? `operation_logs`銆?
- GREEN锛氳ˉ榻? MT4 前置同步、本地事务写入和??? 371 节清单记录后，目标测试??氳繃銆?

### 剩余边界

- 本轮没有把旧项目 `cust_save_info` 中身份证、邮箱???银行卡备注、上级代理???出入金???关等???有分支并入同???个接口；这些字段涉及唯一性???资金权限和独立 MT4 命令，需要继续按单独闭环测试迁移???
- 本轮没有改动前台账户类型切换 `AccountController::changeAccountSave`，该入口已有独立 MT4 同步闭环???

## 372. 2026-07-28 后台普通用户资料编辑密码重置闭环

### 本次处理目标

- 涓? `AdminUserController::updateUser` 补齐旧项??? `CustomerController::cust_save_info` 的密码重置分支???
- 验证 `/api/admin/updateUser` 收到真实 `password` 或旧字段 `password1` 时，必须调用 `UserPasswordService`銆?
- 验证密码服务失败时返??? `ResponseCode::MT4_SYNC_FAILED`锛屾湰鍦? `user_logins.password` 鍜? `user_infos` 基础资料都保持原值???
- 验证旧页面占位符 `********` 表示“不修改密码”，不会触发密码服务???
- 验证审计日志只记??? `password:changed`，不写入明文密码???

### 本次变更文件

- `tests/Feature/AdminUserUpdatePasswordClosureModuleTest.php`
  - 新增密码修改成功、密码服务失败???旧占位符跳过和???终清单证据四个样例???
  - 使用 `UserPasswordService` 测试替身记录调用时本地用户资料旧值，证明密码分支发生在资料落库前???
- `app/Http/Controllers/Admin/AdminUserController.php`
  - `normalizedUserUpdatePayload` 兼容 `password/password1`銆?
  - `passwordChangeRequested` 把空密码??? `********` 识别为不改密???
  - 成功改密后写 `operation_logs`，内容只包含 `password:changed`，避免泄露明文???

### 路由与执行链

- `POST /api/admin/updateUser` / `admin_api_updateUser` -> `AdminUserController::updateUser` -> `normalizedUserUpdatePayload` -> `Validator(password)` -> `UserLogin::where(user_id)` -> `UserPasswordService::change` -> `user_infos.update` -> `operation_logs.create(password:changed)` -> `success(user, UPDATED)`銆?
- `password=********` -> `passwordChangeRequested=false` -> 跳过 `UserPasswordService` -> 仅处理其它白名单资料字段???

### 参数和返回结果中文含???

- `password`：现代接口提交的新密码；非空且不等于 `********` 时表示要求重置用户登录密码???
- `password1`：旧后台表单字段名，含义??? `password` 涓?致???
- `********`：旧编辑页原密码占位符，表示保留旧密码???
- `ResponseCode::UPDATED`：密码服务成功且本地资料或审计写入成功???
- `ResponseCode::MT4_SYNC_FAILED`：密码服务未取得明确成功，???常代表 MT4 或远端密码同步失败，本地资料不落库???

### 为什么这样做

- 旧项??? `cust_save_info` 把密码重置放在用户资料编辑里；如果新项目忽略该字段，旧页面提交会表现为???保存成功但密码没改”???
- 复用 `UserPasswordService` 可以沿用已有“先同步 MT4，再写本地哈希???的边界，不在控制器里重复实现密码协议???
- 审计日志只记录脱敏标识，解决管理员操作可追踪与明文密码不能落库之间的冲突???

### TDD 执行记录

- RED锛歚php vendor\bin\phpunit tests\Feature\AdminUserUpdatePasswordClosureModuleTest.php` 首次失败，接口未调用 `UserPasswordService`，密码服务失败仍返回 `UPDATED`，最终清单缺少密码分支证据???
- GREEN：补齐密码字段归???、占位符跳过、密码服务调用???脱敏审计和??? 372 节清单后，目标测试??氳繃銆?

### 剩余边界

- 本轮没有恢复旧项目短信???知密码重置结果，因为当前任务不能伪造短信服务成功???
- 本轮没有把身份证、邮箱???银行卡、上级代理和出入金开关并入资料编辑接口，这些仍需单独按真实字段和权限边界迁移???

## 373. 2026-07-28 后台普???用户资料编辑邮箱闭???

### 本次处理目标

- 涓? `AdminUserController::updateUser` 补齐旧项??? `CustomerController::cust_save_info` 的登录邮箱编辑分支???
- 验证旧字??? `useremail` 和现代字??? `email` 都会归一化为登录邮箱，并写入真实 `user_logins.email`銆?
- 验证重复邮箱返回 `ResponseCode::VALIDATION_FAILED`，且不会先写??? `user_infos` 基础资料，避免前端出现???资料成功但邮箱失败”的半成功状态???
- 验证成功修改邮箱后写??? `operation_logs`，审计内容记??? `login.email:旧邮???->新邮箱`???

### 本次变更文件

- `tests/Feature/AdminUserUpdateEmailClosureModuleTest.php`
  - 新增??? `useremail` 提交成功落库和审计日志样例???
  - 新增重复邮箱失败且基???资料不落库样例???
  - 新增???终清单证据样例，固定本闭环路由???字段和测试文件???
- `app/Http/Controllers/Admin/AdminUserController.php`
  - `normalizedUserUpdatePayload` 兼容 `email`銆乣useremail`銆乣user_email` 三种入口字段???
  - `updateUser` 在事务前校验邮箱格式、非空和 `user_logins.email` 唯一性???
  - `DB::transaction` 同步写入 `user_infos` 涓? `user_logins`，并统一??? `operation_logs`銆?

### 路由与执行链

- `POST /api/admin/updateUser` / `admin_api_updateUser` -> `AdminUserController::updateUser` -> `normalizedUserUpdatePayload` -> 旧字??? `useremail` 归一化为 `email` -> `Validator(email)` -> `UserInfo::where(user_id)` -> `AdminDataScopeService::canAccessUser` -> `UserLogin::where(user_id)` -> `user_logins.email` 唯一性校??? -> `DB::transaction` -> `user_infos.update` -> `user_logins.update` -> `operation_logs.create(login.email:旧???->新???)` -> `success(user, UPDATED)`銆?
- `PATCH /api/admin/users/{user}` / `admin_api_updateUser` -> 同一 `AdminUserController::updateUser` 链路，区别是业务用户 ID 来自路由 `{user}`，重复邮箱会在事务前返回 `VALIDATION_FAILED`銆?

### 参数和返回结果中文含???

- `email`：现代接口提交的登录邮箱，成功后写入 `user_logins.email`銆?
- `useremail`：旧后台资料编辑表单字段名，含义??? `email` 涓?致，用于兼容??? Blade 提交流程???
- `user_email`：额外兼容字段，避免历史页面或脚本使用下划线字段时被静默丢弃???
- `ResponseCode::UPDATED`：邮箱校验??氳繃锛屽熀纭?资料、登录邮箱和审计日志在同???事务里完成???
- `ResponseCode::VALIDATION_FAILED`：邮箱为空???格式错误或已被其它用户占用；接口不会继续写 `user_infos` 鎴? `user_logins`銆?
- `operation_logs.content` 中的 `login.email:旧邮???->新邮箱`：表示本次后台编辑确实修改了登录邮箱，便于后续审计追踪???

### 为什么这样做

- 旧项??? `cust_save_info` 支持在用户资料编辑页直接修改 `useremail`；新项目前端详情页也已经提交 `email`，但后端若不落到 `user_logins.email`，会形成前后端断点???
- 邮箱是登录凭证，必须在事务前完成唯一性校验；这样可以解决重复邮箱导致的登录歧义，也避免基???资料已保存但登录邮箱失败的半写入问题???
- 审计日志记录登录邮箱新旧值，可以让后台管理员操作有可追踪证据，同时不影响密码分支的脱敏审计规则???

### TDD 执行记录

- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit tests\Feature\AdminUserUpdateEmailClosureModuleTest.php --colors=never` 首次失败，命中旧 `useremail` 未写??? `user_logins.email`、重复邮箱仍返回 `UPDATED`、最终清单缺少邮箱分支证据???
- GREEN：补齐邮箱字段归???、格式与唯一性校验???登录邮箱事务写入???审计日志和??? 373 节清单后，目标测试??氳繃銆?

### 剩余边界

- 本轮没有把身份证、银行卡备注、上级代理???出入金???关???短信??氱煡鍜? MT4 注册日期联动并入资料编辑接口；这些仍???按真实字段???权限和外部服务边界继续单独闭环???
- 本轮没有改动前台用户自行修改邮箱流程；前台邮箱修改已??? `ProfileController` 相关闭环独立覆盖???

## 374. 2026-07-28 后台普???用户资料编辑实名与银行卡闭???

### 本次处理目标

- 涓? `AdminUserController::updateUser` 补齐旧项??? `CustomerController::cust_save_info` 的身份证号与已审核银行卡快照编辑分支???
- 验证旧字??? `userIdcardNo` 会归???化为 `id_card_no`，并写入真实 `user_auths.id_card_no`銆?
- 验证身份证号在业务用户维度唯???；重复时返回 `ResponseCode::VALIDATION_FAILED`，且不会先写??? `user_infos` 基础资料???
- 验证已审核银行卡 `bank_status=2` 修改 `bank_no/bank_class/bank_info` 时，必须先调??? `Mt4ManagerService::updateComment` 同步 MT4 comment，成功后才写??? `user_auths.bank_no/bank_name/bank_addr/is_bank_synced`銆?
- 验证 MT4 comment 同步失败时返??? `ResponseCode::MT4_SYNC_FAILED`锛屾湰鍦? `user_infos` 鍜? `user_auths` 都保持原值???

### 本次变更文件

- `tests/Feature/AdminUserUpdateAuthAndBankClosureModuleTest.php`
  - 新增??? `userIdcardNo` 写入 `user_auths.id_card_no` 和脱敏审计样例???
  - 新增重复身份证号失败且基???资料不落库样例???
  - 新增已审核银行卡先同??? MT4 comment 再落库样例???
  - 新增 MT4 comment 同步失败关闭写入样例和最终清单证据样例???
- `app/Http/Controllers/Admin/AdminUserController.php`
  - `normalizedUserUpdatePayload` 兼容 `id_card_no/userIdcardNo/IDcard_no` 涓? `bank_no/bank_class/bank_info`銆?
  - `updateUser` 在事务前校验身份证唯???性，并为已审核银行卡执行 MT4 comment 前置同步???
  - `userUpdateAuditContent` 对身份证号和银行卡号只记??? `changed` 脱敏标识，对???户行和开户地???记录可读新旧值???
- `app/Models/UserAuth.php`
  - 将真实字??? `is_bank_synced` 加入 `$fillable`，保证已审核银行卡同步成功后可以写入同步标记???

### 路由与执行链

- `POST /api/admin/updateUser` / `admin_api_updateUser` -> `AdminUserController::updateUser` -> `normalizedUserUpdatePayload` -> 旧字??? `userIdcardNo` 归一化为 `id_card_no` -> `Validator(id_card_no)` -> `UserInfo::where(user_id)` -> `AdminDataScopeService::canAccessUser` -> `UserAuth::firstOrNew(user_id)` -> `user_auths.id_card_no` 唯一性校??? -> `DB::transaction` -> `user_auths.save` -> `operation_logs.create(auth.id_card_no:changed)` -> `success(user, UPDATED)`銆?
- `PATCH /api/admin/users/{user}` / `admin_api_updateUser` -> `normalizedUserUpdatePayload` -> `bank_no/bank_class/bank_info` 归一化为 `bank_no/bank_name/bank_addr` -> 读取 `user_auths.bank_status` -> `targetBankSnapshot` -> `Mt4ManagerService::updateComment(user_id, bank_no|bank_name|bank_addr)` -> `DB::transaction` -> `user_infos.update` -> `user_auths.save(is_bank_synced=1)` -> `operation_logs.create(auth.bank_no:changed; auth.bank_name:旧???->新???)` -> `success(user, UPDATED)`銆?
- `Mt4ManagerService::updateComment` 返回??? `status=ok/err=0` -> `ResponseCode::MT4_SYNC_FAILED` -> 不进??? `DB::transaction` -> `user_infos` 鍜? `user_auths` 保持原??笺??

### 参数和返回结果中文含???

- `id_card_no`：现代接口提交的身份证号，成功后写入 `user_auths.id_card_no`銆?
- `userIdcardNo`：旧后台资料编辑字段名，含义??? `id_card_no` 涓?致???
- `bank_no`：已审核银行卡号，属于敏感字段，审计日志只记??? `auth.bank_no:changed`銆?
- `bank_class` / `bank_name`：旧项目???户行字段和新项目???户行字段，成功后写入 `user_auths.bank_name`銆?
- `bank_info` / `bank_addr`：旧项目???户地???字段和新项目???户地???字段，成功后写入 `user_auths.bank_addr`銆?
- `is_bank_synced=1`：表示本次已审核银行卡快照已经同步到 MT4 comment銆?
- `ResponseCode::UPDATED`：身份证或银行卡分支校验通过，外部同步和本地事务写入完成???
- `ResponseCode::VALIDATION_FAILED`：身份证号重复或参数格式不合法；接口不会写入资料???
- `ResponseCode::MT4_SYNC_FAILED`锛歁T4 comment 同步失败；接口不会写入基???资料或银行卡快照???

### 为什么这样做

- 旧项目用户资料编辑页把身份证和银行卡快照放在同一??? `cust_save_info` 保存动作中；新项目如果只保存基础资料，会导致旧字段提交后静默丢失???
- 身份证号是实名唯???凭证，必须在写库前排除其它用户占用，解决重复实名资料带来的认证和出金风险???
- 已审核银行卡会参与出金和 MT4 备注展示，必须先同步 MT4 comment，再写本??? `user_auths` 镜像，避免交易端与后台资料不???致???
- 银行卡号和身份证号属于敏感信息，审计日志只记录已变更，不记录完整号码，保留追踪能力同时避免日志泄露???

### TDD 执行记录

- RED锛歚php -d memory_limit=1G vendor\phpunit\phpunit\phpunit tests\Feature\AdminUserUpdateAuthAndBankClosureModuleTest.php --colors=never` 首次失败，命??? `userIdcardNo` 未写??? `user_auths.id_card_no`、重复身份证仍返??? `UPDATED`、已审核银行卡未调用 `Mt4ManagerService::updateComment`銆丮T4 失败未关闭写入???最终清单缺少实名与银行卡分支证据???
- GREEN：补齐身份证字段归一与唯???性校验???银行卡目标快照、MT4 comment 前置同步、`is_bank_synced` 写入白名单???脱敏审计和??? 374 节清单后，目标测试??氳繃銆?

### 剩余边界

- 本轮没有把上级代理???出入金???关???短信??氱煡銆丮T4 注册日期联动和特殊运营口径并入资料编辑接口；这些仍需按真实字段???权限和外部服务边界继续单独闭环???
- 本轮没有改动前台实名认证或银行卡换绑流程；前台资料提交和换绑已由 `ProfileController` 相关闭环独立覆盖???

## 375. 2026-07-28 后台普???用户资料编辑出入金???关闭???

### 本次处理目标

- 涓? `AdminUserController::updateUser` 补齐旧项??? `CustomerController::cust_save_info` 鐨? `isoutmoney` 鍜? `isallowmoney` 分支???
- 验证旧字??? `isoutmoney` 写入 `user_infos.is_withdrawal_allowed`，旧字段 `isallowmoney` 写入 `user_infos.is_deposit_allowed`銆?
- 验证???关???只接受 `0` 鍜? `1`；非法??艰繑鍥? `ResponseCode::VALIDATION_FAILED`，并且不会先写入用户基础资料???
- 验证成功修改后写??? `operation_logs`，记录出金和入金???关的新旧值???
- 验证现代敏感字段 `is_withdrawal_allowed/is_deposit_allowed` 仍由 `AdminUserUpdateFieldWhitelistClosureModuleTest` 保持默认忽略，避免任意现代请求绕过旧字段兼容边界???

### 本次变更文件

- `tests/Feature/AdminUserUpdateDepositWithdrawalSwitchClosureModuleTest.php`
  - 新增旧字段出入金???关成功落库和审计日志样例???
  - 新增非法旧字段???失败且基础资料不落库样例???
  - 新增???终清单证据样例，固定本闭环路由???字段和测试文件???
- `app/Http/Controllers/Admin/AdminUserController.php`
  - `normalizedUserUpdatePayload` 兼容旧字??? `isoutmoney/isallowmoney`銆?
  - `updateUser` 对归???后的???关???做 `0/1` 严格枚举校验???
  - `userProfileUpdates` 仅写入旧字段归一后的 `user_infos.is_withdrawal_allowed` 鍜? `user_infos.is_deposit_allowed`銆?

### 路由与执行链

- `POST /api/admin/updateUser` / `admin_api_updateUser` -> `AdminUserController::updateUser` -> `normalizedUserUpdatePayload` -> 旧字??? `isoutmoney/isallowmoney` 归一化为 `is_withdrawal_allowed/is_deposit_allowed` -> `Validator(in:0,1)` -> `UserInfo::where(user_id)` -> `AdminDataScopeService::canAccessUser` -> `DB::transaction` -> `user_infos.update` -> `operation_logs.create(is_withdrawal_allowed:旧???->新???; is_deposit_allowed:旧???->新???)` -> `success(user, UPDATED)`銆?
- `PATCH /api/admin/users/{user}` / `admin_api_updateUser` -> 同一 `AdminUserController::updateUser` 链路，区别是业务用户 ID 来自路由 `{user}`，非法开关???会在事务前返回 `VALIDATION_FAILED`銆?

### 参数和返回结果中文含???

- `isoutmoney`：旧后台出金???关；`0` 表示允许出金，`1` 表示禁止出金???
- `isallowmoney`：旧后台入金???关；`0` 表示允许入金，`1` 表示禁止入金???
- `user_infos.is_withdrawal_allowed`：新项目出金限制字段，含义与旧项??? `is_out_money` 涓?致???
- `user_infos.is_deposit_allowed`：新项目入金限制字段，含义与旧项??? `is_allow_money` 涓?致???
- `ResponseCode::UPDATED`：开关???合法，用户资料和审计日志已在同???事务里完成???
- `ResponseCode::VALIDATION_FAILED`：开关??间笉鏄? `0` 鎴? `1`，接口不会继续写 `user_infos`銆?

### 为什么这样做

- 旧项目资料编辑页会提??? `isoutmoney/isallowmoney`，如果新项目忽略这两个字段，后台保存会显示成功但用户仍可按旧状???入金或出金???
- 出入金开关直接影响前台入金???出金???返佣转账等资金入口，必须严格限制为 `0/1`，不能把 `0abc` 鎴? `2` 宽松转换成有效状态???
- 只开放旧字段兼容入口，保留现代敏感字段默认忽略，可以在迁移旧 Blade 的同时维持新 REST 接口的敏感字段边界???

### TDD 执行记录

- RED：先以同等行为测试确认失败，首次失败命中旧字段没有写??? `user_infos.is_withdrawal_allowed/is_deposit_allowed`、非法???仍返回 `UPDATED`、最终清单缺少出入金???关分支证据；随后归并到已有规范命??? `AdminUserUpdateDepositWithdrawalSwitchClosureModuleTest`銆?
- GREEN：补齐旧字段归一、`0/1` 严格校验、开关字段写入???审计日志和??? 375 节清单后，`php vendor\bin\phpunit tests\Feature\AdminUserUpdateDepositWithdrawalSwitchClosureModuleTest.php` 通过???

### 剩余边界

- 上级代理 `parent_id/userparentId` 调整已在??? 377 节补齐，本节只保留出入金???关边界???
- 本轮没有伪???短信??氱煡鍜? MT4 注册日期联动；这些能力需要在后续有真实服务边界时继续迁移???

## 376. 2026-07-28 后台普???用户资料编??? MT4 只读状??侀棴鐜?

### 本次处理目标

- 涓? `AdminUserController::updateUser` 补齐旧项??? `CustomerController::cust_save_info` 鐨? `enablereadonly` 分支???
- 验证旧字??? `enablereadonly=1` 会先调用 `Mt4ManagerService::lockUser`，远端成功后才写??? `user_infos.is_mt4_readonly=1`銆?
- 验证旧字??? `enablereadonly=0` 会先调用 `Mt4ManagerService::unlockUser`，远端失败时返回 `ResponseCode::MT4_SYNC_FAILED`，并且不写入 `user_infos.user_name` 鎴? `user_infos.is_mt4_readonly`銆?
- 验证 `enablereadonly` 只接??? `0/1`，非法??艰繑鍥? `ResponseCode::VALIDATION_FAILED`锛岄伩鍏? PHP 宽松转换把异常输入写成有效交易权限???

### 本次变更文件

- `tests/Feature/AdminUserUpdateReadonlyMt4ClosureModuleTest.php`
  - 新增只读锁定成功、解除只读失败关闭写入???非法只读???拒绝写入和???终清单证据四个样例???
- `app/Http/Controllers/Admin/AdminUserController.php`
  - `normalizedUserUpdatePayload` 将旧字段 `enablereadonly` 归一化为内部字段 `is_mt4_readonly`銆?
  - `updateUser` 在事务前校验 `is_mt4_readonly` 鐨? `0/1` 枚举，并按目标??艰皟鐢? `lockUser` 鎴? `unlockUser`銆?
  - `userProfileUpdates` 继续不把只读状???当普??氬熀纭?资料直接写入，避免绕??? MT4 同步边界???
- `docs/admin-legacy-migration-gap-audit.md`
  - 灏? `AdminUserUpdateReadonlyMt4ClosureModuleTest` 加入 `CustomerController` 迁移证据，并更新剩余待核对范围???
- `tests/Feature/AdminLegacyMigrationGapAuditTest.php`
  - 将只读状态闭环测试加入旧模块迁移审计断言，防止后续报告遗漏该分支???

### 路由与执行链

- `POST /api/admin/updateUser` / `admin_api_updateUser` -> `AdminUserController::updateUser` -> `normalizedUserUpdatePayload` -> 旧字??? `enablereadonly` 归一化为 `is_mt4_readonly` -> `Validator(in:0,1)` -> `UserInfo::where(user_id)` -> `AdminDataScopeService::canAccessUser` -> 目标值为 `1` 时调??? `Mt4ManagerService::lockUser(user_id)` -> `DB::transaction` -> `user_infos.update(is_mt4_readonly=1)` -> `operation_logs.create(is_mt4_readonly:0->1)` -> `success(user, UPDATED)`銆?
- `PATCH /api/admin/users/{user}` / `admin_api_updateUser` -> 同一 `AdminUserController::updateUser` 链路，区别是业务用户 ID 来自路由 `{user}`；目标???为 `0` 时调??? `Mt4ManagerService::unlockUser(user_id)`，远端失败则返回 `MT4_SYNC_FAILED`，不进入 `DB::transaction`銆?

### 参数和返回结果中文含???

- `enablereadonly`：旧后台资料编辑字段；`1` 表示??? MT4 账号锁为只读，用户可以登录但不能交易；`0` 表示解除只读，恢复交易能力???
- `user_infos.is_mt4_readonly`：新项目本地 MT4 只读镜像字段；只??? MT4 明确成功后更新，用于后台列表和详情展示???
- `Mt4ManagerService::lockUser`：远端锁定交易账号的服务方法，返??? `status=ok` 涓? `err=0` 才代表锁定成功???
- `Mt4ManagerService::unlockUser`：远端解除交易账号只读的服务方法，返回非成功时代表交易端未确认解除???
- `ResponseCode::UPDATED`锛歁T4 同步成功且本地事务写入完成???
- `ResponseCode::VALIDATION_FAILED`：只读??间笉鏄? `0` 鎴? `1`，接口不会继续写入任何本地资料???
- `ResponseCode::MT4_SYNC_FAILED`锛歁T4 lock/unlock 未明确成功，接口不会进入本地事务，避免后台状态和真实交易权限分叉???

### 为什么这样做

- 旧项??? `cust_save_info` 鎶? `enablereadonly` 放在普???用户资料编辑保存动作里；新项目如果忽略该字段，??? Blade 页面保存会显示成功但交易权限没有变化???
- MT4 只读状???直接影响用户能否下单交易，必须先同步真实交易端，再写本地镜像，解决“后台显示只读但 MT4 仍可交易”或“后台显示可交易??? MT4 仍锁定???的状???分叉问题???
- 非法枚举值前置拒绝，可以避免字符串???混合数字或异常值被宽松转换成交易权限，保持资料编辑接口的失败关闭边界???

### TDD 执行记录

- RED锛歚php vendor\bin\phpunit tests\Feature\AdminUserUpdateReadonlyMt4ClosureModuleTest.php` 首次失败，命中未调用 `lockUser/unlockUser`銆丮T4 失败仍返??? `UPDATED`銆侀潪娉? `enablereadonly=2` 未校验和???终清单缺证据???
- GREEN：补齐旧字段归一、`0/1` 严格校验、MT4 lock/unlock 前置同步、本地事务写入???审计日志和??? 376 节清单后，目标测试??氳繃銆?

### 剩余边界

- 上级代理 `parent_id/userparentId` 调整已在??? 377 节单独补齐，本节只保??? MT4 只读状???边界???
- 本轮没有伪???短信??氱煡銆丮T4 注册日期联动和特殊运营口径；这些能力???要在后续有真实服务边界时继续迁移???

## 377. 2026-07-29 后台普???客户上级代理与 MT4 层级???致??ч棴鐜?

### 本次处理目标

- 对齐旧项??? `CustomerController::cust_save_info` 鐨? `data.userparentId`銆丮T4 `zip` 涓? `cny` 真实语义，不能只修改本地 `parent_id`銆?
- 该资料编辑入口只允许调整 `account_type=2` 的普通客户；代理商层级仍由代理专用流程维护，防止两个入口同时改代理树???
- 新上级非零时必须是当前管理员数据范围内的代理账号；`0` 表示改为平台根节点???
- MT4 明确返回 `status=ok` 涓? `err=0` 后，才允许在???个本地事务内更新 `parent_id`銆乣family_tree`銆乣agent_descendants` 涓? `operation_logs`銆?
- 本地事务失败时，使用??? `parent_id` 与旧五段关系码反向补??? MT4，避免远端和数据库停在不同层级???

### 本次变更文件

- `tests/Feature/AdminUserUpdateParentHierarchyClosureModuleTest.php`
  - 覆盖成功迁移、MT4 拒绝、非代理上级、管理员范围外代理???本地事务失败补偿??佺幇浠? `parent_id` 继续忽略和文档契约???
- `tests/Feature/AdminUserUpdateParentAgentClosureModuleTest.php`
  - 保留早期本地树回归并更新职责边界：代理商通过普???资料入口迁移时必须拒绝，普通客户改为平台根节点时仍验证 MT4 成功后的闭包清理???
- `tests/Feature/Mt4ManagerServiceLegacyProtocolClosureModuleTest.php`
  - 固定旧协议单帧必须同时携??? `act=update_user&acc={客户ID}&zip={上级ID}&cny={五段关系码}`銆?
- `app/Http/Controllers/Admin/AdminUserController.php`
  - `normalizedUserUpdatePayload` 仅兼容旧字段 `userparentId/userParentId`锛屼笉寮?放现代敏感字??? `parent_id` 直接写入???
  - `updateUser` 校验普???客户身份???目标代理身份???循环风险和 `AdminDataScopeService::canAccessUser($admin, $parentId, 'agent')`銆?
  - MT4 成功后才进入本地事务；事务内重新锁定客户、锁定目标祖先并复核层级快照，失败时调用旧快照补偿???
  - 审计日志同时记录 `parent_id:旧???->新??糮 涓? `family_tree:旧???->新???`，方便追溯归属变化???
- `app/Services/FamilyTreeService.php`
  - `resolveCustomerHierarchy` 沿真??? `parent_id` 回溯祖先，拒绝缺失节点???非代理祖先和循环，并生成新 `family_tree` 与旧 MT4 五段关系码???
  - `FamilyTreeService::syncCustomerDescendantRelations` 删除不再属于旧代理的关系，恢复仍命中的软删除唯一行，并重??? `depth/is_direct`銆?
- `app/Services/Mt4ManagerService.php`
  - 新增 `Mt4ManagerService::updateUserHierarchy`，使用一次旧 `update_user` 帧发??? `acc/zip/cny`，避免只更新直属上级而关系码仍是旧??笺??

### 路由与执行链

- `POST /api/admin/updateUser` / `admin_api_updateUser` -> `AdminUserController::updateUser` -> `normalizedUserUpdatePayload` 将旧 `data.userparentId` 归一为内??? `parent_agent_id` -> `Validator(integer,min:0)` -> 查询目标客户 -> `AdminDataScopeService::canAccessUser(..., 'user')` 校验客户范围 -> 校验 `account_type=2` -> `validateParentAgentChange` 校验新上级是代理且不形成循环 -> `AdminDataScopeService::canAccessUser(..., 'agent')` 校验目标代理范围 -> `FamilyTreeService::resolveCustomerHierarchy` 分别计算新旧祖先、家谱和五段关系??? -> `Mt4ManagerService::updateUserHierarchy(acc,zip,cny)`銆?
- MT4 成功链：`updateUserHierarchy` 返回 `status=ok,err=0` -> `DB::transaction` -> `user_infos` 客户??? `lockForUpdate` -> 目标祖先??? `lockForUpdate` -> 再次 `resolveCustomerHierarchy` 防并发漂??? -> 鍐? `parent_id/family_tree/updated_by` 与其它资??? -> `FamilyTreeService::syncCustomerDescendantRelations` -> `operation_logs.create` -> 提交事务 -> `success(user, UPDATED)`銆?
- MT4 失败链：远端异常、`status!=ok` 鎴? `err!=0` -> 返回 `ResponseCode::MT4_SYNC_FAILED` -> 不进入本地事务，因此客户姓名、`parent_id`銆乣family_tree`、闭包表和审计日志都保持旧??笺??
- 本地失败补偿链：MT4 新层级成??? -> 本地事务任一写入抛出异常 -> Laravel 回滚本地全部写入 -> `compensateMt4Hierarchy` -> `Mt4ManagerService::updateUserHierarchy(客户ID,旧上级ID,旧关系码)` -> 记录补偿结果 -> 返回 `ResponseCode::INTERNAL_ERROR`銆?
- `PATCH /api/admin/users/{user}` / `admin_api_updateUser` -> 复用同一方法，业务用??? ID 来自路由 `{user}`；只提交现代 `parent_id` 时归???化白名单不会复制该字段，不调??? MT4，仍只更新允许的普???资料???

### 参数和返回结果中文含???

- `userparentId`：旧后台资料编辑字段，表示当前用户新的直属上级代理业务用??? ID銆?
- `parent_agent_id`：控制器内部归一化字段，只作为旧字段桥接，不直接暴露给现??? REST 表单???
- `zip`：旧 MT4 `update_user` 的直属上级字段；等于??? `userparentId`锛宍0` 表示平台根节点???
- `cny`：旧 MT4 五段代理关系码；??? `agent_levels.level_code` 把代??? ID 放入 1銆?2銆?3銆?4銆?5+ 五个槽位，空槽固定为 `0000`銆?
- `family_tree`：从根代理到直属代理再到当前客户自身的???号链；平台根客户只保存自身 ID銆?
- `agent_descendants.depth`：代理到客户的距离；直属代理??? `1`，其上一级为 `2`，依次??掑銆?
- `agent_descendants.is_direct`锛歚1` 表示当前 `agent_id` 就是客户直属上级，`0` 表示间接祖先???
- `ResponseCode::UPDATED`锛歁T4 层级、本地客户资料???家谱???代理闭包和审计日志全部完成???
- `ResponseCode::VALIDATION_FAILED`：目标不是普通客户???上级不是代理???层级链缺失/循环或参数格式不合法，没有调??? MT4 或写本地???
- `ResponseCode::PERMISSION_DENIED`：管理员无权访问目标客户或目标代理，不能跨数据范围转移客户???
- `ResponseCode::MT4_SYNC_FAILED`：远端没有明确确??? `zip/cny` 更新，本地全部保持旧值???
- `ResponseCode::INTERNAL_ERROR`锛歁T4 已成功但本地事务失败；数据库已经回滚，并已尝试把 MT4 补偿回旧层级???

### 为什么这样做

- 旧项目同???次保存会??? `zip/cny` 发给 MT4；只改新数据库会造成后台归属、交易端代理关系、返佣与数据范围互相矛盾???
- 五段关系码使用新项目已有 `agent_levels.level_code` 作为旧等级槽位的真实对应来源，不使用硬编码或伪???映射???
- 先远端确认???再本地事务，解决??淢T4 拒绝但数据库已经迁移”的半成功问题；本地异常后的反向补偿解决相反方向的不???致???
- 新上级也执行数据范围校验，解决管理员可见客户被转移给不可见代理后产生的跨租户归属问题???
- 只接受旧 `userparentId`，继续忽略现??? `parent_id`，保留旧 Blade 兼容能力同时避免扩大普???资料接口的敏感字段权限???

### TDD 执行记录

- RED锛歚php vendor/bin/phpunit --colors=never tests/Feature/AdminUserUpdateParentHierarchyClosureModuleTest.php` 首次稳定得到 `5 failures / 7 tests`锛岃瘉瀹? MT4 方法未调用???拒绝分支未关闭、目标代理范围未校验、本地异常未补偿、文档未记录真实链路???
- 协议 RED锛歚php vendor/bin/phpunit --colors=never --filter update_user_hierarchy tests/Feature/Mt4ManagerServiceLegacyProtocolClosureModuleTest.php` 首次??? `updateUserHierarchy` 方法不存在???失败???
- GREEN 目标：业务实现完成后六条行为用例先转绿；补齐本节文档后重新运行完整目标测试与 MT4 协议回归，结果记录以???终验证命令输出为准???

### 剩余边界

- 本节只处理普通客户的上级代理变更；代理商自身换上级会影响整棵代理子树和多账户 MT4 关系，继续由代理商专用闭环单独实现???
- 本节不扩大现??? `parent_id` 字段写权限，也不改变交易组???杠杆???只读状态???密码???银行卡等其它资料编辑分支的既有契约???

## 378. 2026-07-29 后台普???用户旧本地资料字段闭环

### 本次处理目标

- 补齐??? `CustomerController::cust_save_info` 提交??? `sex`銆乣gift_allowed` 鍜? `userremark` 本地资料字段???
- 旧字段分别写??? `user_infos.gender`銆乣user_infos.is_gift_allowed` 鍜? `user_infos.remark`，这些字段不调用 MT4 或短信服务???
- 非法性别、非法礼品开关必须在数据库事务前返回参数错误，不能连带写入同请求中的用户名或备注???
- 现代字段 `gender/is_gift_allowed/remark` 继续保持忽略，只承接??? Blade 字段，避免扩大现代资料接口白名单???

### 路由与执行链

- `POST /api/admin/updateUser` -> `AdminUserController::updateUser` -> `normalizedUserUpdatePayload` -> `sex` 缁? `normalizeLegacyGenderValue` 转换??? `gender=1/2` -> `gift_allowed` 归一??? `is_gift_allowed` -> `userremark` 归一??? `remark` -> `Validator` 校验性别、开关和备注长度 -> 用户与管理员数据范围校验 -> `userProfileUpdates` 生成本地字段更新 -> `userUpdateAuditContent` 生成新旧??? -> `DB::transaction` -> `user_infos.update` -> `operation_logs.create` -> 返回 `ResponseCode::UPDATED`銆?
- `PATCH /api/admin/users/{user}` -> 复用同一方法；旧字段不合法时返回 `ResponseCode::VALIDATION_FAILED`，不进入事务。只提交现代同名字段时，归一化白名单不会复制这些敏感别名???

### 参数、返回和执行结果中文含义

- `sex`：旧页面性别；`???/male/m/1` 转为 `user_infos.gender=1`锛宍濂?/female/f/2` 转为 `2`銆?
- `gift_allowed`：旧页面礼品领取???关；`0` 表示不允许领取，`1` 表示允许领取???
- `userremark`：旧页面后台备注，写??? `user_infos.remark`锛屾渶澶? 500 个字符???
- `ResponseCode::UPDATED`：合法旧字段与审计日志已在同???事务提交???
- `ResponseCode::VALIDATION_FAILED`锛氭??别不可识别、礼品开关不??? `0/1` 或备注过长，本地资料全部保持旧??笺??

### 为什么这样做

- 这三个字段只影响本地展示、礼品入口与运营备注，不属于 MT4 真实账户字段；调用不存在的远端服务反而会制???假闭环???
- 先归???再校验解决旧页面中英文???别值兼容，同时让未知???明确失败，不能??? PHP 宽松转换成有效枚举???
- 只开放旧字段桥接，解决旧 Blade 保存无效的问题，又不会让现代 REST 请求绕过敏感资料白名单???

### 测试证据

- `AdminUserUpdateLegacyLocalProfileClosureModuleTest` 覆盖成功落库与审计???非法???零写入、现代别名忽略和文档契约???
- 成功日志包含 `gender:1->2`銆乣is_gift_allowed:0->1`銆乣remark:旧???->新???`，分别表示???别、礼品权限和运营备注的真实变化???

## 2026-07-28 后台持仓汇???交易明细下钻闭???

### 本轮测试

- `AdminPositionSummaryTradeDetailDrilldownClosureModuleTest` 用红灯确??? Layui 持仓汇??汇??交易订单页默认筛??夈?丆rmUI 本地行操作和迁移文档证据缺失，再按同???测试进入绿灯验证???

### 路由与执行链???

- Layui 汇???入口：`GET /admin/position-summary` -> `resources/admin/layui/position-summary/index.blade.php` -> `public/js/apps/admin/layui/pages.js::positionSummaryTradeDetail` -> `crmRoute('admin_page_trades')` -> `GET /admin/trades?user_id=当前行用???&start_date=筛???开始日???&end_date=筛???结束日???&mode=all`銆?
- Layui 交易明细：`GET /admin/trades` -> `resources/admin/layui/trades/index.blade.php` 输出 `data-default-trade-*` -> `applyDefaultTradeQueryFilters` 写入筛??夎〃鍗? -> `currentApiUrl = tradeModeUrls[defaultMode] || tradeModeUrls.all` 选择接口 -> `POST /api/admin/tradeList` 返回当前用户交易订单???
- CrmUI 汇???入口：`GET /admin-crmui/position-summary` -> `CrmUi\Admin\PageController::show` -> `rowActions` 声明 `position_summary_trades` -> `public/js/apps/crmui/admin.js::positionSummaryTradeDetail` -> `/admin-crmui/trades?user_id=当前行用???&start_date=筛???开始日???&end_date=筛???结束日???&mode=all`銆?
- CrmUI 默认筛??夛細`CrmUi\Admin\PageController::definitionWithRequestDefaults` 读取查询参数中的筛??夊瓧娈? -> 写入 `filters.value` -> `resources/admin/crmui/partials/module-page.blade.php` 渲染表单默认??? -> `currentPageFilter` 首次请求交易订单 API銆?

### 参数含义

- `user_id`：当前持仓汇总行的业务用??? ID，用于交易页限制到该用户订单???
- `start_date`：持仓汇总页当前筛???开始日期，交易页沿用同???时间下限???
- `end_date`：持仓汇总页当前筛???结束日期，交易页沿用同???时间上限???
- `mode`：交易页模式，`all` 表示全部交易，`open` 表示当前持仓，`closed` 表示历史平仓；本轮持仓汇总默认进??? `all`，保证从汇???进入明细时先看到完整订单???

### 闭环说明

- 这样做解决???持仓汇总只有聚合数字???缺少明细追溯入口???的问题???
- Layui 鍜? CrmUI 都使??? Lucide 图标或既有按钮体系，不引入表情符号???
- 返回结果??? `records` 表示订单分页列表，`summary` 表示当前筛???命中的订单合计；空列表代表该用户在当前日期范围内没有交易明细???

## 379. 2026-07-29 后台持仓汇总交易账号映射闭环

### 本次处理目标

- 明确区分 CRM 业务用户 ID 涓? MT4 登录号，统一??? `user_infos.mt4_code = mt4_trades.login` 连接业务用户和订单???
- 持仓汇??汇??交易明细和后台 `custom_users` 数据范围必须共用同一账号映射，不允许任何入口???鍥? `mt4_trades.login = user_infos.user_id`銆?
- 对没有有??? `mt4_code` 的业务用户返回空订单结果，不猜测账号，不制???不存在的交易数据???

### 路由与执行链

- 持仓汇??婚摼锛歚POST /api/admin/positionSummaryList` -> `PositionSummaryController::positionSummaryList` -> 管理员数据范围解析出成员业务 `user_id` -> `user_infos.mt4_code` 转换为成??? MT4 登录??? -> `mt4_trades.login` 聚合订单 -> 返回 `records`銆傚叾涓? `records.data[*].user_id` 仍是业务用户 ID锛宍mt4_login` 才是用于交易侧查询的真实登录号???
- 明细下钻链：持仓汇???行携带业务 `user_id` -> `GET /admin/trades?user_id={业务用户ID}` -> `POST /api/admin/tradeList` -> `TradeController` 通过 `user_infos.mt4_code = mt4_trades.login` 关联 -> 浠? `user_infos.user_id` 过滤 -> 返回同一用户的真??? MT4 订单和当前筛选汇总???
- 权限链：`role_data_scopes.scope_type=custom_users` -> 读取允许的业务用??? ID 集合 -> 瀵? `user_infos.user_id` 应用数据范围 -> 映射 `user_infos.mt4_code` -> 查询 `mt4_trades.login`。返回范围内映射订单，排除范围外订单和错误直连订单???

### 参数、返回与执行结果中文含义

- `user_id`锛欳RM 业务用户 ID，用于用户资料???归属和管理员数据范围；它不??? MT4 登录号???
- `mt4_code`：业务用户绑定的 MT4 登录号；值大??? `0` 时才可以与交易表建立真实关联???
- `mt4_trades.login`：订单所??? MT4 登录号，只能??? `user_infos.mt4_code` 比较???
- `records.total=1` 且订单为 `ticket=994601`銆乣login=884601`銆乣profit=45.25`：表示真实映射链命中成功???
- 绌? `records`：表示筛选范围内没有订单，或业务用户没有有效 MT4 映射；不代表接口错误???
- `ResponseCode::SUCCESS`：查询???映射和权限过滤均已正常执行；是否有订单??? `records.total` 为准???

### 为什么这样做

- 业务用户编号??? MT4 登录号来自不同编号空间，把两者直接比较会把其它账号订单错误归给当前用户，并可能绕过后台数据范围???
- 测试额外写入 `login=user_id`銆乣ticket=994602`銆乣profit=999.99` 的诱饵订单；只有仍使用错误直连的实现才会命中它，因此可以稳定阻止历史逻辑回归???
- `custom_users` 先限制业务用户再映射 MT4 登录号，解决管理员指定用户范围与交易表账号字段口径不同导致的越权泄露???
- 鏃? `MARGIN_RATE` 在当前真实交易表中不存在，继续作为风险模块缺失边界记录，本闭环不使用余额或盈亏反推假值???

### TDD 执行记录

- RED锛歚AdminPositionSummaryTradeAccountMappingClosureModuleTest` 首次运行时，持仓汇???返回诱饵盈??? `999.99`，交易数据范围返回诱饵订??? `ticket=994602`；修复运行时后，文档契约仍因缺少映射链说明保持红灯???
- GREEN锛歚PositionSummaryController`銆乣TradeController` 涓? `Mt4Trade` 统一映射后，只返回真实订??? `ticket=994601`銆乣login=884601`銆乣profit=45.25`；本节补齐审计和执行链后，由同一测试完成???终验证???
- 回归测试：`AdminPositionSummaryTradeAccountMappingClosureModuleTest` 同时覆盖汇??汇??明细??乣custom_users` 权限、诱饵订单排除和文档契约???

## 380. 2026-07-29 后台风险交易账号映射与真实强平闭???

### 本次处理目标

- 补齐持仓汇???进入风控中心后的真实后端链路，统一使用 `user_infos.mt4_code = mt4_trades.login`，禁止把业务用户 ID 直接??? MT4 登录号???
- 风险持仓、异??? IP 详情交易统计和强平动作必须共用同???映射；`custom_users` 先限制业务用户，再作用于映射后的订单???
- 保留旧项目真实能力边界：当前交易表没??? `MARGIN_RATE`，不能用盈亏、手续费或常量反推假保证金率???

### 风险持仓执行???

- `POST /api/admin/riskPositions` -> `RiskController::positions` -> `validateUserIdFilter` 校验 `user_id` 必须是整??? -> `baseOpenTradeRiskQuery` 查询 `cmd in (0..5)` 涓? `close_time is null/0` 的未平仓订单 -> `user_infos.mt4_code = mt4_trades.login` 关联业务用户 -> `applyTradeFilters` 使用 `user_infos.user_id`銆乼icket銆乻ymbol 和开仓日期筛??? -> `applyDataScope` 使用 `user_infos.user_id` 过滤管理员范??? -> `paginateQuery` 返回分页记录 -> `summaryFor` 返回当前筛???汇总???
- `records.data[*].user_id` 返回 CRM 业务用户 ID锛宍records.data[*].login` 返回真实 MT4 登录号，`ticket` 返回真实订单号；三???职责不同，不互相猜测???
- `risk_value = profit - abs(commission)` 只表示扣除手续费后的浮动风险收益。行??? `margin=null` 表示当前快照没有可验证保证金字段，`total_margin=0` 表示没有可汇总???，不等于旧 `MARGIN_RATE=0`銆?

### 异常 IP 详情执行???

- `POST /api/admin/riskIpDetail` -> `RiskController::riskIpDetail` -> 校验必填 `login_ip` 和可选业??? `user_id` -> `baseRiskIpDetailQuery` 鎸? `user_login_logs.login_ip + user_id` 聚合登录次数 -> 交易统计子查询???过 `user_infos.mt4_code = mt4_trades.login` 生成业务用户维度 -> 分别计算未平仓和已平仓数??? -> 联接 `deposit_records.user_id` 涓? `withdraw_records.user_id` 的真实金额汇??? -> 瀵? `user_login_logs.user_id` 应用管理员数据范??? -> 返回 IP 用户详情???
- `open_order_count=1/closed_order_count=1` 表示该业务用户映??? MT4 账号各有???笔真实开仓和平仓；诱饵订单不会进入统计???

### 强平执行???

- `POST /api/admin/riskForceClose/{id}` -> `RiskController::forceClose` -> `validateRiskRouteId` 校验交易主键 -> 查询未平??? `mt4_trades` -> 浠? `user_infos.mt4_code = mt4_trades.login` 关联业务用户 -> `AdminDataScopeService` 瀵? `user_infos.user_id` 应用 `custom_users` 或代理树范围 -> 读取订单真实 `login/ticket` -> `RiskForceCloseGateway::close(login,ticket,comment)`銆?
- 网关返回 `isClosed=true` -> 写入 `operation_logs`，内容记??? trade id銆佺湡瀹? login銆乼icket銆乸rovider reference 和备??? -> 返回 `ResponseCode::SUCCESS` 涓? provider reference銆?
- 网关拒绝 -> 返回 `ResponseCode::OPERATION_NOT_ALLOWED`；连接失??? -> 返回 `ResponseCode::MT4_SYNC_FAILED`；订单不存在、已平仓或不在管理员范围 -> 返回 `ResponseCode::DATA_NOT_FOUND`。以上失败结果均不写假平仓状态，也不追加成功审计???

### 权限与越权边???

- `role_data_scopes.scope_type=custom_users` 中保存的是业务用??? ID。执行顺序必须是 `custom_users -> user_infos.user_id -> user_infos.mt4_code -> mt4_trades.login`銆?
- 测试写入 `login=user_id` 的诱饵订??? `ticket=994702/994705`；旧直连会错误返回两条，正确映射只返??? `ticket=994701/login=884701`銆?
- 受限管理员可把范围内真实订单 `login=884701/ticket=994701` 交给强平网关；范围外 `ticket=994703` 返回数据不存在，网关调用次数不增加???

### TDD 与执行结???

- RED：风险列表返回两条诱饵订单；异常 IP 详情返回错误???仓数 `2`；映射订单强平返??? `ResponseCode::DATA_NOT_FOUND`锛涜绾? `margin` 返回字符??? `"0"`；文档没有完整链路???
- GREEN：四条运行时用例先???过，得到真实订??? `ticket=994701`銆佺湡瀹? MT4 登录??? `884701`、业务用??? ID `984701`銆佸紑浠?/平仓 `1/1` 和正确网关参数；行级 `margin=null`，随后补齐本节文档???
- `AdminRiskTradeAccountMappingClosureModuleTest` 覆盖账号映射、`custom_users`、诱饵订单??佸紓甯? IP 交易统计、`RiskForceCloseGateway`、范围外拒绝、`MARGIN_RATE` 真实缺失边界和审计文档???

### 前端联动

- 风险联动已通过 `position_summary_risk` 完成：持仓汇总动作携带业??? `user_id`、开始日期???结束日期和 `mode=positions` 进入 Layui/CrmUI 风控页；后端按本节映射解释该参数???
- 页面动作继续使用 Lucide 图标与既有按钮体系，不使用表情符号；强平按钮只负责发起明确命令，不在浏览器端制???成功状态???
- `AdminPositionSummaryRiskDrilldownClosureModuleTest` 固定两套后台入口、默认筛选???风险模式??丆rmUI 本地动作和迁移文档证据，防止后续只保留后端映射却丢失页面追溯链路???

## 381. 2026-07-31 礼品发放库存/积分联动边界锁定
- 结论：旧项目 Admin\GiftController@send_gift 只写 gift_shipments，不存在 gift_items 目录表，无任何库存/积分扣除逻辑。
- gift_items（points_cost、stock_quantity）是新项目新增目录能力，仅用于前台 vailable_gifts 展示，前台 /api/front/gifts 为只读。
- 当前无用户积分余额表、无兑换/领取 API，第一阶段不伪造“兑换扣库存/积分消耗联动”，与旧项目行为一致。
- 锁定：sendGift 不扣除 gift_items.stock_quantity、无礼品目录也能发放；前台 gift 相关路由无 redeem/exchange 入口。
- 测试：	ests/Feature/GiftStockDeductionBoundaryClosureModuleTest.php。
## 382. 2026-07-31 前台注册成功欢迎邮件闭环
- 结论：旧项目 `RegisterController::registerinto` 在 MT4 同步成功后必发 `mail.register_mail_gmtk` 欢迎邮件（主题按 lang_id 分 注册成功/Registration Notice，正文含交易账号与明文密码，`send_type=verifyemail`）；新项目此前完全未实现该邮件，属于 349 节剩余边界挂起项。
- 本轮补齐：新增 `App\Mail\FrontRegistrationSuccessNotification`（subject 用 `auth.registration_success_mail_subject`，纯文本模板 `emails/front-registration-success-notification`），在 `AuthController::register` 的 provisioning_status=processed 且业务资料校验通过后发送；发送失败只记 warning 日志，不影响注册成功响应。
- 安全决策：旧邮件回显明文密码，本轮按确认结论只发送交易账号（user_id/mt4_code）与开通提示，不回显密码；文案通过 `auth.registration_success_mail_greeting/account/footer` 语言 key 提供 zh-CN 与 en 两版。
- 触发条件对齐旧流程：仅 MT4 同步成功（processed）且用户已激活、mt4_code=user_id 时才发送；MT4 失败路径不发送。
- 测试：`tests/Feature/FrontRegistrationSuccessNotificationClosureModuleTest.php` 覆盖成功发送（含收件人/账号/称呼断言）、MT4 失败不发送、正文含账号不含密码、语言主题与清单断言。
## 383. 2026-08-01 UI 残留清理与合并

- 结论：项目版本内共存在 8 组 UI 积压物，实为 2 套正式 UI：LayUI（front_page_*/admin_page_*，web.php:628/839 默认入口，front_layui::/admin_layui::）+ CRMUI（/front-crmui/* 与 /admin-crmui/*，PageController 通配分发，作为新视觉家族与 LayUI 并存）。
- 清理确认删除 5 组残留：(1) resources/front/adminlte 2 blade + public/js/apps/front/adminlte 3 js；(2) resources/views/admin 9 个 + README；(3) resources/views/front 9 个孤儿模板（legacy/direct_customer_detail 与 layouts/app 被 AgentController.php:957 继续使用）；(4) ui-samples 套件（web.php 对应 SamplePageController、4 blade + js/css + 2 个 ui_samples 语言包）；(5) front-naive/admin-naive 两组 301 跳转（转到 front_page_*/admin_page_*）。
- 清理动作：AppServiceProvider.php 移除 front_adminlte/admin_adminlte 两个命名空间；web.php 删除 3 组路由块；同步删除 UiSamplePagesTest.php、AdminLegacyViewNamespaceGuardTest.php 并更新 6 个 UI 参考文档测试（FrontUiRegressionTest/FrontendRouteManifestTest/UnifiedBladeDesignSystemTest/LucideIconAndEmojiPolicyTest/BladeOnlyFrontendArchitectureTest/AdminPositionSummaryMt4AccountLinkageClosureModuleTest）中的 adminlte/legacy/naive 断言条目。
- 顺带修复预存问题：CrmUiBusinessCodeContractTest 的 loadPage 场景补充 stub currentPageFilter（消除 ReferenceError）；AdminLegacyAdministratorsClosureModuleTest 的 3 个过时 test 改为 405 guard 口径（start/stop/del GET 语义为拒绝并返回 OPERATION_NOT_ALLOWED + Allow POST，与 AdminLegacyMutationMethodBoundaryClosureTest 一致）。
- 版本状态：LayUI 与 CRMUI 两套并存，保留核心外部链路 front/legacy/direct_customer_detail + layouts/app + partials/frontend-routes + partials/lucide-assets + emails/*。