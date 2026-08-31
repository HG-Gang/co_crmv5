# 后台新旧项目迁移缺口审计

> 审计时间：2026-06-07 Asia/Shanghai  
> 新项目：`D:\Software\PhpProject\Demo\co_crmv5`  
> 新项目后台控制器：`D:\Software\PhpProject\Demo\co_crmv5\app\Http\Controllers\Admin`  
> 旧项目后台控制器：`D:\Php-project\Php\new_co_gmtk_crmV3\app\Http\Controllers\Admin`  
> 数据库连接：`127.0.0.1:3307 / co_crmv5`  
> 审计目标：继续深入对比旧后台控制器与新后台模块，记录已迁移、部分迁移、未迁移内容，并给出真实 DB 测试数据。

## 1. 当前结论

新项目已经完成后台鉴权、菜单、按钮权限、数据范围、Blade 页面框架、多语言和一批核心业务接口迁移，但旧项目仍有多个复杂业务域没有完整迁入新项目。

本轮审计结论：

| 状态 | 说明 |
| --- | --- |
| 已迁移 | 登录、管理员、角色、权限、菜单、用户、代理基础列表、入金审核、出金审核、返佣列表、系统配置、支付通道、新闻、黑名单、注销申请、交易列表、凭证审核、大代理基础管理、仓位清零候选列表与记录入口。 |
| 部分迁移 | 代理 V3 复杂统计、持仓/平仓统计、权益汇总、风控、认证审核、出金流水/未入金流水、批量入金/批量出金导入、批量信用导入、汇率维护、礼品发放与发货、在线用户、实时返佣、产品/交易品种基础列表、大编号后台统计 API。新项目已有部分页面或接口，但旧项目细分统计、导出、批量确认、MT4 汇总或同步逻辑、写入维护流程未完整覆盖。 |
| 未迁移 | 暂无已确认完全空白的旧后台模块；剩余工作主要集中在各模块深层导出、自动同步、旧 MT4 特殊口径和复杂运营流程。 |

处理建议：

1. 优先迁移“资金与交易口径”模块：批量导入深层同步、出入金流水、权益汇总、持仓汇总。
2. 第二优先迁移“运营配置”模块：汇率、产品/交易品种维护、礼品、在线用户。
3. 所有迁移必须继续沿用新项目规则：数据表驱动权限、`check.permission:admin` 后端鉴权、`AdminDataScopeService` 数据范围过滤、Blade + JS + CSS 页面渲染、多语言文案。
4. 字段迁移不能照搬旧字段名，必须以当前真实 DB 表结构为准。本轮真实 DB 已确认：`user_infos` 使用 `level_id` 表示代理等级，不存在 `agent_level` 字段。

## 2. 真实 DB 测试数据

数据来源说明：

- 采样命令来源：`php artisan tinker --execute="...DB::table(...)"`。
- 采样连接：`127.0.0.1:3307 / co_crmv5`。
- 采样用途：作为后续后台接口、数据范围、权限可见性和页面表格的真实测试数据基础。
- 注意事项：以下数据来自当前开发库真实表，用户名和测试配置包含自动化测试生成的样本，不是手工构造的假数据。

### 2.1 数据量统计

| 表或业务口径 | 真实数量 | 字段逻辑含义 |
| --- | ---: | --- |
| `admins` | 41 | 后台管理员账号数量，用于后台登录、角色绑定、数据范围绑定测试。 |
| `user_infos` | 40 | 用户资料总数，包含代理与普通客户。 |
| `agents` | 5 | 从 `user_infos.account_type = 1` 统计得到的代理数量。 |
| `customers` | 35 | 从 `user_infos.account_type <> 1` 统计得到的普通客户数量。 |
| `permissions` | 113 | 菜单、页面、按钮、接口权限配置数量。 |
| `roles` | 50 | 后台和前台角色配置数量。 |
| `system_configs` | 4 | 系统配置项数量。 |
| `payment_channels` | 0 | 支付通道当前为空表，后续支付通道页面只能验证空列表和新增流程，不能伪造已有通道样本。 |

### 2.2 管理员样本：`admins`

字段含义：

- `id`：后台管理员主键，用于 JWT 登录主体、角色绑定和数据范围绑定。
- `username`：后台登录用户名，用于登录展示和操作日志定位。
- `email`：管理员邮箱，当前样本为空，页面需兼容空值展示。

| id | username | email |
| ---: | --- | --- |
| 1 | service-admin-6a23fb2740c46 |  |
| 2 | service-admin-6a23fd6bca27d |  |
| 3 | service-admin-6a23ff345166e |  |
| 4 | scope-admin-6a24010ecd8bc |  |
| 5 | scope-admin-6a24010ee593e |  |

### 2.3 代理样本：`user_infos.account_type = 1`

字段含义：

- `user_id`：业务用户 ID，用于用户、代理、订单、出入金等业务表关联。
- `user_name`：业务用户名，用于后台列表、搜索和详情展示。
- `account_type`：账号类型，当前口径中 `1` 表示代理。
- `level_id`：代理等级 ID，真实表字段，不是 `agent_level`。
- `comm_rate`：代理返佣比例，用于返佣配置和佣金调整。
- `parent_id`：上级代理或推荐关系 ID，用于代理树和数据范围计算。
- `mt4_code`：MT4 登录账号或交易系统账号，交易统计和 MT4 汇总会使用。

| user_id | user_name | account_type | level_id | comm_rate | parent_id | mt4_code |
| ---: | --- | ---: | ---: | ---: | ---: | ---: |
| 1001 | Demo Root Agent | 1 | 0 | 0 | 0 | 0 |
| 989400 | 测试代理 | 1 | 0 | 0 | 0 | 0 |
| 980913 | 测试代理 | 1 | 0 | 0 | 0 | 0 |
| 984579 | 测试代理 | 1 | 0 | 0 | 0 | 0 |
| 986722 | 测试代理 | 1 | 0 | 0 | 0 | 0 |

### 2.4 普通客户样本：`user_infos.account_type <> 1`

字段含义：

- `user_id`：普通客户业务 ID。
- `user_name`：普通客户名称。
- `account_type`：账号类型，当前样本中 `2` 表示普通客户。
- `parent_id`：所属代理或上级关系 ID，用于代理数据范围过滤。
- `mt4_code`：客户交易账号，持仓、平仓、权益统计会使用。

| user_id | user_name | account_type | parent_id | mt4_code |
| ---: | --- | ---: | ---: | ---: |
| 610001 | 可见客户 | 2 | 0 | 0 |
| 610002 | 不可见客户 | 2 | 0 | 0 |
| 620101 | 绑定代理客户 | 2 | 0 | 0 |
| 620102 | 其他代理客户 | 2 | 0 | 0 |
| 743320 | 可见客户 | 2 | 0 | 0 |

### 2.5 角色与权限样本

`roles` 字段含义：

- `id`：角色主键。
- `name`：角色名称。
- `guard_type`：角色所属端，后台固定使用 `admin`。
- `status`：启用状态，`1` 表示启用。

| id | name | guard_type | status |
| ---: | --- | --- | ---: |
| 1 | 财务审核-6a23fb2706ad4 | admin | 1 |
| 2 | 客服-6a23fb273f90b | admin | 1 |
| 3 | 财务审核-6a23fd6ba13c9 | admin | 1 |
| 4 | 客服-6a23fd6bc94b3 | admin | 1 |
| 5 | 财务审核-6a23ff341a217 | admin | 1 |

`permissions` 字段含义：

- `id`：权限主键。
- `slug`：前端按钮和菜单判断使用的稳定权限标识。
- `api_route`：后端 Laravel 路由名称，用于 `check.permission:admin` 接口鉴权。
- `type`：权限类型，`1=菜单或页面`，`3=按钮或敏感动作`。
- `guard_type`：权限所属端，后台权限使用 `admin`。

| id | slug | api_route | type | guard_type |
| ---: | --- | --- | ---: | --- |
| 1 | front_news | front_api_newsList | 1 | front |
| 2 | admin_deposit_approve_6a23fb27093ea | admin_api_depositApprove | 3 | admin |
| 3 | admin_users_6a23fb27413fd | admin_api_userList | 1 | admin |
| 4 | admin_user_review_auth_6a23fb2741a47 | admin_api_reviewAuth | 3 | admin |
| 5 | admin_deposit_approve_6a23fd6ba32be | admin_api_depositApprove | 3 | admin |
| 6 | admin_users_6a23fd6bcaa80 | admin_api_userList | 1 | admin |
| 7 | admin_user_review_auth_6a23fd6bcb135 | admin_api_reviewAuth | 3 | admin |
| 8 | admin_deposit_approve_6a23ff341e9e2 | admin_api_depositApprove | 3 | admin |

### 2.6 系统配置样本：`system_configs`

字段含义：

- `id`：系统配置主键。
- `key`：配置唯一键，后端按此读取业务开关或参数。
- `value`：配置值。
- `group`：配置分组，用于后台页面分类展示。

| id | key | value | group |
| ---: | --- | --- | --- |
| 1 | unit_test_single_config | old | general |
| 2 | unit_test_batch_config | new | general |
| 3 | unit_test_single_config_6a2451d167fa9764352419 | new | risk |
| 4 | unit_test_batch_config_6a2451d187046474795164 | new | general |

## 3. 新旧后台控制器对比清单

### 3.1 已迁移或基本可替代

| 旧控制器 | 新项目对应模块 | 迁移状态 | 处理建议 |
| --- | --- | --- | --- |
| `LoginController` | `AuthController`、`AdminAuthController` | 已迁移 | 后续只保留 JWT + SSO + 多语言错误消息。 |
| `AdminController` | `AdminController`、`AdminDashboardController`、`AdminAccountFieldModuleTest` | 部分迁移 | 管理员 CRUD 已补齐；新增和编辑已写入真实 `admins.mobile`、`admins.role_id`、`admins.status` 字段，密码留空继续保留旧密码，Layui、CrmUI 与 Naive 表单已暴露手机号、角色和启停状态。旧首页统计口径需继续核对。 |
| `AdministratorsController` | `AdminController` | 已迁移 | 新项目以 `admins` 表和角色权限中间表为准。 |
| `RoleController` | `RoleController`、`PermissionController`、`MenuController` | 已迁移 | 权限来源统一为 `permissions` 与 `role_permissions`。 |
| `CustomerController` | `AdminUserController`、`UserController`、`admin_api_userList`、`admin_api_exportUsers`、`admin_api_updateUser`、`AdminUserExportModuleTest`、`AdminUserStatsModuleTest`、`AdminUserUpdateMt4SyncClosureModuleTest`、`AdminUserUpdatePasswordClosureModuleTest`、`AdminUserUpdateAuthAndBankClosureModuleTest`、`AdminUserUpdateDepositWithdrawalSwitchClosureModuleTest`、`AdminUserUpdateReadonlyMt4ClosureModuleTest`、`AdminUserUpdateParentAgentClosureModuleTest` | 部分迁移 | 用户列表、详情、认证审核、当前筛选 CSV 导出、用户交易统计第一阶段和后台用户编辑交易资料/密码重置/实名银行卡/出入金开关/MT4 只读状态/上级代理调整已迁移；用户列表与导出继续套用后台数据范围并读取真实 `user_infos`、`user_logins` 字段，且共用 `user_infos.created_at` 日期范围筛选；交易统计读取当前 `user_trades`、`deposit_records`、`withdraw_records` 和 `symbol_prices` 可支撑字段，覆盖总手数、总盈亏、库存费、品种分类手数与分页/全局汇总；`updateUser` 已兼容旧 `cust_save_info` 的 `username`、`userphoneNo`、`useremail`、`userIdcardNo`、`bank_no`、`bank_class`、`bank_info`、`isoutmoney`、`isallowmoney`、`enablereadonly`、`userparentId`、`usergrpName`、`cust_lvg`、`password/password1` 字段，修改 `user_infos.mt4_group` 或 `user_infos.leverage` 时先调用 `Mt4ManagerService::updateUserTradingProfile`，修改已审核银行卡快照时先调用 `Mt4ManagerService::updateComment`，修改只读状态时先调用 `Mt4ManagerService::lockUser/unlockUser`，修改上级代理时重建 `user_infos.family_tree` 与 `agent_descendants`，密码修改复用 `UserPasswordService`，出入金开关与只读状态严格限制为 `0/1`，上级代理必须为代理且不能形成循环，MT4/密码同步失败或参数非法时本地资料不落库，成功后写 `operation_logs` 且密码、身份证号、银行卡号均记录脱敏标识；Layui 与 CrmUI 页面已补用户姓名、开始/结束日期和统计列展示。旧项目短信通知、MT4 注册日期联动、旧 MT4 cny 层级编码和特殊运营口径仍需继续核对。 |
| `DepositAmountController` | `DepositController` | 部分迁移 | 入金列表、详情、审核已迁移；旧批量导入未迁移。 |
| `WithdrawAmountController`、`WithdrawStatusController` | `WithdrawController` | 部分迁移 | 出金处理流程已迁移；旧流水统计和导出未完整迁移。 |
| `PayChannelController` | `PaymentChannelController` | 已迁移 | 当前真实 DB `payment_channels=0`，需补新增数据后做完整页面烟测。 |
| `NewsInfoController` | `NewsController` | 已迁移 | 新闻 CRUD 和权限按钮已补齐。 |
| `GroupConfigController` | `GroupConfigController` | 已迁移 | 新项目字段以当前模型和迁移为准。 |
| `BigAgentController` | `BigAgentController` | 已迁移 | 基础 CRUD 已迁移，旧项目若有额外统计需单独审计。 |
| `CancellationController` | `CancelApplyController`、`admin_api_cancelApplyApprove`、`admin_api_cancelApplyReject`、`AdminCancelApplyReviewModuleTest` | 部分迁移 | 注销申请列表、通过/拒绝审核、拒绝原因保存、`user_logins.is_cancelled` 注销标记、用户资料软删除和 `operation_logs` 审核操作日志已覆盖；旧项目历史导出或复杂状态流仍需补充。 |
| `FengXianManageController` | `RiskController`、`AdminRiskTradeAccountMappingClosureModuleTest` | 部分迁移 | 风控持仓、追保、异常 IP、真实强平网关和业务用户到 MT4 账号映射已闭环；旧 `MARGIN_RATE` 精确保证金率因当前真实表缺失该字段而不伪造，其它旧项目特殊风控规则继续按真实数据源比对。 |

### 3.2 部分迁移但缺口较大

| 旧控制器 | 旧项目关键方法 | 新项目对应模块 | 优先级 | 迁移缺口 |
| --- | --- | --- | --- | --- |
| `AgentControllerV3` | `agentsListSearch`、`agentsListSearchV2`、`agentsExamineListSearch`、`agents_save`、`agents_edit_save` | `AgentController`、`admin_api_agentList`、`admin_api_agentDescendants`、`admin_api_exportAgents`、`admin_api_confirmAgent`、`admin_api_rejectAgentConfirmation`、`admin_api_agentStatsList`、`AdminDataScopeService`、`AdminAgentDescendantsModuleTest`、`AdminAgentExportModuleTest`、`AdminAgentLevelUpdateFieldModuleTest`、`AdminAgentConfirmationModuleTest`、`AdminAgentStatsModuleTest` | P0 | 新项目已有代理列表、下级、等级更新、佣金调整、当前筛选 CSV 导出、后台代理确认/拒绝闭环和代理统计第一阶段闭环；代理下级接口已通过 `admin_api_agentDescendants` 同时兼容 `agent_descendants` 闭包表和 `user_infos.parent_id` 递归代理树，返回前端可直接展示的 `user_id`、`user_name`、`account_type`、`parent_id`、`is_direct`、`depth` 字段；等级更新已写入真实 `user_infos.level_id` 字段，代理列表与导出继续套用后台数据范围并读取真实 `user_infos.level_id`、`user_logins.email` 字段，且共用 `user_infos.created_at` 日期范围筛选；代理确认/拒绝写入真实 `user_infos.is_agent_confirmed`，拒绝原因落到 `user_infos.remark`，并记录 `operation_logs` 审计；代理统计已通过 `admin_api_agentStatsList` 按真实 `user_infos.level_id` 和 `user_infos.created_at` 日期范围返回直属代理数、直属客户数、余额与权益汇总，并补齐旧项目 `fy_money`、`rj_money`、`qk_money` 行字段和页脚兼容字段，Naive 代理统计弹窗已展示这些资金列，Layui 与 CrmUI 页面已补开始/结束日期筛选。旧项目短信重发、MT4 同步、复杂代理树财务多层汇总仍需继续迁移。 |
| `AdminOpenOrderController` | 旧持仓列表与操作方法 | `TradeController@openPositions`、`RiskController`、`AdminTradeMt4PositionModuleTest` | P0 | 持仓入口已继续复用真实 `mt4_trades`、后台数据范围和 `records + summary` 结构；交易列表/持仓/平仓通用筛选已兼容旧项目 `userId`、`orderId`、`sym_symbol`、`startdate/enddate` 参数，`orderType=real_disk/test_disk` 已通过 `user_infos.mt4_group` 的 `-TEST`、`-TEST-P` 后缀承接旧项目实盘/测试盘口径；Layui、CrmUI、Naive 均补齐订单号、日期、实盘/测试盘筛选与 COMMENT/MODIFY_TIME 展示。剩余边界：旧项目 `MARGIN_RATE <> 0` 过滤当前真实表尚无字段，订单明细和风险联动仍需继续逐行核对。 |
| `AdminCloseOrderController` | 旧平仓列表与操作方法 | `TradeController@closedPositions`、`TradeController@exportClosedPositions`、`AdminTradeMt4PositionModuleTest` | P0 | 历史平仓已恢复旧项目 `COMMENT as ordercomment` 兼容字段、`modify_time` 返回和按 `COALESCE(NULLIF(modify_time,0), close_time)` 倒序；`is_coercion=Yes` 精确匹配 `comment LIKE so%` 强平单，`is_coercion=No` 排除强平单；`orderType=real_disk/test_disk` 已按 `user_infos.mt4_group` 后缀筛选真实盘和测试盘；列表汇总只统计当前筛选命中的记录；当前筛选 CSV 导出已通过 `admin_api_exportClosedPositions` 闭环，三套后台入口均可筛选、展示和导出。剩余边界：旧 `MARGIN_RATE` 过滤和代理范围细节仍需继续迁移。 |
| `PositionSummaryController` | `positionSummarySearch`、`positionSummarySearchV2`、`subAgentsListSearchV2` | `PositionSummaryController`、`admin_api_positionSummaryList`、`admin_api_exportPositionSummary`、`admin_page_position_summary`、`AdminPositionSummaryModuleTest`、`AdminLegacyRouteSemanticClosureTest`、`AdminPositionSummaryDrilldownFrontendClosureModuleTest`、`AdminPositionSummaryMt4AccountLinkageClosureModuleTest` | P0 | 持仓汇总已按当前真实 `user_infos`、`mt4_trades`、`symbol_prices`、`mt4_users` 落地页面、列表 API、当前筛选结果 CSV 导出、权限和数据范围；代理行已通过 `agent_descendants` 闭包表汇总下级客户 MT4 交易，并在闭包表缺失时使用 `user_infos.family_tree` 兜底，`union` 去重避免同一成员重复统计；旧后台 `subAgentsListSearchV2` 已归属 `admin_api_positionSummaryList`，兼容 `searchtype=subAgentsSearch` 与 `userPId/user_pid` 参数，返回当前代理自身和直属下级代理的持仓汇总行，而不是 `admin_api_agentDescendants` 纯代理树成员列表；MT4 账户快照已通过 `user_infos.mt4_code = mt4_users.login` 关联，列表、CSV、Layui 与 CrmUI 同步展示 `mt4_balance`、`mt4_equity`、`mt4_margin`、`mt4_margin_free`、`mt4_leverage` 等字段，顶部汇总增加 `total_mt4_accounts` 与当前筛选资金合计；Layui 与 CrmUI 已补齐代理行前端钻取入口，点击代理行会在本页携带 `searchtype=subAgentsSearch` 与 `userPId` 重载列表和当前筛选导出；CrmUI 与 Naive 后台已同步展示 `user_id`、`user_name`、`parent_id`、`account_type`、`mt4_group` 和 `total_*` 聚合列。剩余边界只保留旧 `MARGIN_RATE` 口径、交易明细下钻和风险联动。 |
| `RightsSummaryController` | `RightsSummarySearch`、`ConfirmWithdrawOrdeposit`、`ManualConfirmWithdrawOrdeposit`、`rightsSumExport` | `RightsSummaryController`、`admin_api_rightsSummaryList`、`admin_api_exportRightsSummary`、`admin_api_manualConfirmRightsSettlement`、`AdminRightsSummaryModuleTest`、`AdminRightsSummaryManualConfirmModuleTest` | P0 | 权益汇总第一阶段已落地 MT4 账户权益列表、汇总卡片、手动确认权益结算、当前筛选结果 CSV 导出、页面/API 权限和数据范围；当前筛选范围的线上结算金额已通过 `online_settlement_deposit_amount`、`online_settlement_withdraw_amount`、`online_settlement_commission_amount`、`online_settlement_net_amount` 汇总字段闭环；自动确认出入金和真实 MT4 自动同步仍需继续迁移。 |
| `AuthenticationController` | 旧实名/认证审核逻辑 | `AuthenticationController`、`AdminUserController@reviewAuth`、`admin_api_authPendingList`、`admin_api_authCertifiedList`、`admin_api_reviewAuth`、`AdminAuthenticationModuleTest` | P1 | 待审/已审列表、审核动作、拒绝原因写入真实备注字段、`user_infos.auth_status` 同步和 `operation_logs` 审核操作日志已覆盖；旧项目认证详情细分、独立审核历史流水和更细的身份证/银行卡分步口径仍需继续核对。 |
| `AdminRealCommissionController` | 实时返佣查询与统计 | `RealtimeCommissionController`、`admin_api_realtimeCommissionList`、`admin_api_exportRealtimeCommissions`、`admin_page_realtime_commissions`、`AdminRealtimeCommissionModuleTest` | P1 | 实时返佣已按真实 `mt4_trades.comment` 与 `modify_time` 恢复旧项目 COMMENT 关键词口径：仅统计 `cmd=6`、`profit>0` 且备注命中 `DBCN` 或 `-FY` 的返佣记录；列表、汇总、COMMENT 源订单筛选、当前筛选 CSV 导出、Layui/CrmUI/Naive 展示、页面/API 权限和后台数据范围均已闭环。剩余边界只保留旧项目更深层的自动结算联动和 MT4 定时任务分类联动。 |

### 3.3 未迁移模块

| 旧控制器 | 旧项目关键方法 | 当前状态 | 优先级 | 处理建议 |
| --- | --- | --- | --- | --- |
| `BatchAmountController` | `depositImportExcel`、`withdrawImportExcel`、`againDepositAmount`、`againWithdrawAmount`、`depositImportSearch`、`withdrawImportSearch` | 已迁移核心闭环 | P0 | 批量入金/出金导入已由 `BatchAmountImportController`、`admin_api_depositImportList`、`admin_api_createDepositImport`、`admin_api_depositImportTemplate`、`admin_api_exportDepositImports`、`admin_api_retryDepositImport`、`admin_api_syncDepositImport`、`admin_api_withdrawImportList`、`admin_api_createWithdrawImport`、`admin_api_withdrawImportTemplate`、`admin_api_exportWithdrawImports`、`admin_api_retryWithdrawImport`、`admin_api_syncWithdrawImport`、`admin_page_deposit_imports`、`admin_page_withdraw_imports`、`AdminBatchAmountImportModuleTest`、`AdminBatchAmountImportRetryModuleTest`、`AdminBatchAmountImportMt4SyncClosureModuleTest` 覆盖；模板下载、当前筛选结果 CSV 导出、CSV 文件上传解析、可选 `mt4_login` 交易账号校验、失败重试回待处理队列、待处理记录真实 MT4 入金/出金同步、成功 ticket 写回、连接失败保持待处理、未知/拒绝写失败原因均已覆盖；Layui、CrmUI 与 Naive 管理端均已接入同步按钮和同一 API。 |
| `BatchCreditController` | `creditImportExcel`、`creditImportSearch`、`againCreditAmount` | 已迁移核心闭环 | P0 | 批量信用导入已由 `BatchCreditImportController`、`admin_api_creditImportList`、`admin_api_createCreditImport`、`admin_api_creditImportTemplate`、`admin_api_exportCreditImports`、`admin_api_retryCreditImport`、`admin_api_syncCreditImport`、`admin_page_credit_imports`、`AdminBatchCreditImportModuleTest`、`AdminBatchCreditImportRetryModuleTest`、`AdminBatchCreditImportMt4SyncClosureModuleTest` 覆盖；模板下载、当前筛选结果 CSV 导出、CSV 文件上传解析、可选 `mt4_login` 交易账号校验和待处理记录真实 MT4 信用同步已闭环，重试仅回待处理队列，不伪造同步成功。 |
| `WithdrawFlowController` | `withdrawFlowSearch`、`withdrawFlowSearchV2`、`withdrawFlowExport` | 部分迁移 | P0 | 出金流水第一阶段已由 `FundFlowController`、`admin_api_withdrawFlowList`、`admin_api_exportWithdrawFlows`、`admin_page_withdraw_flows`、`resources/admin/layui/withdraw-flows/index.blade.php`、`AdminFundFlowModuleTest`、`AdminFundFlowPermissionMigrationTest`、`AdminWithdrawFlowCommentClassificationClosureModuleTest` 覆盖；当前筛选结果 CSV 导出已覆盖，MT4 COMMENT 细分分类和当前筛选汇总已覆盖，复杂财务复核仍需继续迁移。 |
| `UnDepositAmountController` | `undepositFlowSearch`、`undepositFlowSearchV2` | 部分迁移 | P1 | 未入金流水第一阶段已由 `FundFlowController`、`admin_api_undepositFlowList`、`admin_api_exportUndepositFlows`、`admin_page_undeposit_flows`、`resources/admin/layui/undeposit-flows/index.blade.php`、`AdminFundFlowModuleTest`、`AdminFundFlowPermissionMigrationTest` 覆盖；当前筛选结果 CSV 导出已覆盖；复杂状态分类、运营跟进统计和财务复核汇总已由 `FundFlowController::undepositFlowList` 与 `AdminUndepositFlowSummaryClosureModuleTest` 闭环。剩余边界只保留真实支付网关状态变更、人工跟进写链或其它旧项目未确认深层流程。 |
| `ExchangeRateController` | `whpj_rate_browse`、`whpj_rate_save` | 部分迁移 | P1 | 汇率配置第一阶段已由 `ExchangeRateController`、`admin_api_exchangeRateInfo`、`admin_api_updateExchangeRate`、`admin_page_exchange_rates`、`AdminExchangeRateModuleTest` 覆盖，并落到 `system_configs` 的 `sys_deposit_rate`、`sys_draw_rate`；`updateExchangeRate` 已写入 `operation_logs` 操作日志，记录管理员、IP 和汇率前后值；旧项目历史版本和更细币种汇率仍需继续迁移。 |
| `GiftController` | `send_gift`、`shipment_list_search`、`getUserAddressList`、`shipment_list_export` | 部分迁移 | P1 | 礼品发放与发货第一阶段已由 `GiftController`、`admin_api_giftShipmentList`、`admin_api_exportGiftShipments`、`admin_api_giftAddressList`、`admin_api_sendGift`、`admin_api_updateGiftShipment`、`admin_api_giftItemList`、`admin_api_createGiftItem`、`admin_api_updateGiftItem`、`admin_api_deleteGiftItem`、`admin_page_gifts`、`AdminGiftModuleTest` 覆盖；当前筛选结果 CSV 导出、后端 `recipients` 批量写入、Layui/Naive/CrmUI 地址选择到批量发放闭环、物流状态/单号/备注更新闭环、礼品配置目录 CRUD、前台真实可兑换礼品目录/库存状态展示与筛选、发放与物流更新 `operation_logs` 审计已覆盖，兑换扣库存/真实兑换规则和积分消耗联动仍需继续迁移。 |
| `UserLoginOnlineController` | `search`、`get_account_type` | 部分迁移 | P2 | 在线用户第一阶段已由 `OnlineUserController`、`admin_api_onlineUserList`、`admin_api_forceOfflineUser`、`admin_page_online_users`、`AdminOnlineUserModuleTest`、`AdminOnlineUserForceOfflineSessionInvalidationTest` 覆盖，并读取真实 `user_onlines` 关联 `user_infos`；后台强制下线会删除 `user_onlines` 在线记录、写入 `operation_logs` 审计、按 `user_logins.id` 清理 `sso:user:{login_id}` 缓存并清空 `user_logins.jwt_token_id`，`SingleSignOn` 在 SSO 缓存缺失时会拒绝旧 JWT。当前表仍没有 session_id 或设备维度字段，因此单设备下线、设备维度展示和缓存/心跳口径仍需继续迁移。 |
| `AdminProductionController` | 产品/交易品种维护 | 部分迁移 | P1 | 基础列表、维护写入、当前筛选 CSV 导出与写入 `operation_logs` 审计已由 `ProductionController`、`admin_page_productions`、`admin_api_productionList`、`admin_api_exportProductions`、`admin_api_createProduction`、`admin_api_updateProduction`、`admin_api_deleteProduction`、`resources/admin/layui/productions/index.blade.php`、`app/Http/Controllers/CrmUi/Admin/PageController.php`、`public/js/apps/naive-admin/front-plain.js`、`AdminProductionModuleTest` 覆盖；旧项目真实 MT4 同步和更复杂的品种审计流仍需继续迁移。 |
| `BigNumberController` | 大编号后台逻辑 | 部分迁移 | P2 | 后台统计接口已由 `BigNumberController`、`admin_api_bigNumberDashboard`、`admin_api_bigNumberTrend`、`2026_07_05_000001_add_admin_big_number_permissions.php`、`AdminBigNumberModuleTest` 覆盖；若旧后台还有独立页面、导出或大编号专属运营动作，需继续与旧项目逐项核对。 |
| `AdminWhsExpZeroController` | 旧特殊业务处理 | 已迁移核心闭环 | P2 | 仓位清零候选列表、记录列表和一键清零记录已由 `AdminWhsExpZeroController`、`admin_page_whs_exp_zero`、`admin_api_whsExpZeroList`、`admin_api_whsExpZeroRecords`、`admin_api_whsExpZero`、`resources/admin/layui/whs-exp-zero/index.blade.php`、`LegacyUiReplacementCoverageTest` 覆盖；实际 MT4 余额调整仍保持记录化入口，不在当前自动执行。 |
| `UserGroupController` | 用户组维护 | 已兼容替代 | P2 | 已保留旧字段入参与响应别名，内部统一读写 `group_configs`；默认初始化也改为写入 `group_configs`，不再依赖未建表的 `user_groups`。 |

## 4. 当前新项目后台 API 覆盖情况

新项目 `api/admin` 当前已经存在以下主要接口组：

- 鉴权：`login`、`logout`、`refreshToken`、`profileInfo`、`changePassword`。
- 权限：`roleList`、`createRole`、`updateRole`、`deleteRole`、`assignPermissions`、`permissionTree`、`menuTree`。
- 数据范围：`roleDataScopeList`、`saveRoleDataScope`、`adminAgentBindingList`、`saveAdminAgentBinding`。
- 用户与代理：`userList`、`userDetail`、`reviewAuth`、`agentList`、`agentDescendants`、`updateAgentLevel`、`updateAgentCommission`。
- 资金：`depositList`、`depositApprove`、`depositReject`、`withdrawList`、`withdrawProcess`、`withdrawComplete`、`withdrawReject`、`withdrawFlowList`、`undepositFlowList`、`neverDepositUserList`、`rightsSummaryList`、`manualConfirmRightsSettlement`。
- 交易与风控：`tradeList`、`openPositions`、`closedPositions`、`tradeSummary`、`positionSummaryList`、`realtimeCommissionList`、`riskPositions`、`riskMarginCalls`、`riskForceClose`。
- 配置与内容：`systemConfigList`、`updateSystemConfig`、`exchangeRateInfo`、`updateExchangeRate`、`channelList`、`createChannel`、`newsList`、`createNews`、`onlineUserList`、`forceOfflineUser`、`giftShipmentList`、`giftAddressList`、`sendGift`、`updateGiftShipment`、`giftItemList`、`createGiftItem`、`updateGiftItem`、`deleteGiftItem`。

缺口判断：

- 有接口不代表旧项目完整迁移。旧项目中大量控制器包含导入、导出、MT4 聚合、多层代理树统计和批量重试逻辑，新项目当前多数只覆盖基础列表和基础操作。
- 后续每迁移一个旧模块，必须同步新增 Blade 页面、JS、语言包、权限迁移、后端控制器注释、Feature 测试和最终清单章节。

## 5. 下一步落地顺序

| 顺序 | 模块 | 原因 | 需要新增的核心权限 |
| ---: | --- | --- | --- |
| 1 | 批量入金/出金/信用导入深层同步 | 批量入金/出金/信用导入的页面、列表、手工新增、失败重试、模板下载、当前筛选导出、CSV 上传解析、交易账号校验和真实 MT4 同步已落地。 | 已补 `admin_api_syncDepositImport`、`admin_api_syncWithdrawImport`、`admin_api_syncCreditImport`；本轮闭环已完成 |
| 2 | 出金流水/未入金流水深层迁移 | 页面、列表接口、当前筛选结果 CSV 导出、出金流水 MT4 COMMENT 细分分类和当前筛选汇总已落地；未入金复杂状态分类、运营跟进统计和财务复核汇总已由第 361 节对应实现与 `AdminUndepositFlowSummaryClosureModuleTest` 闭环。剩余缺口只保留复杂财务复核写链、真实支付网关状态变更和旧项目未确认深层流程。 | 后续写链/网关状态权限按真实接口命名补齐 |
| 3 | 持仓/平仓/持仓汇总深层迁移 | 持仓、平仓和持仓汇总页面/API 已落地；平仓 COMMENT 强平筛选、MODIFY_TIME 排序展示、实盘/测试盘 orderType 筛选和当前筛选 CSV 导出已覆盖；持仓汇总当前筛选 CSV 导出已覆盖；持仓汇总代理树下级交易汇总已通过 `agent_descendants` 与 `user_infos.family_tree` 双路径闭环，Layui/CrmUI 代理行前端钻取已承接旧项目 `subAgentsSearch + userPId`，MT4 账户快照已通过 `user_infos.mt4_code = mt4_users.login` 联动并同步输出 `mt4_balance` 与 `total_mt4_accounts`。剩余缺口只保留旧 `MARGIN_RATE` 口径、明细下钻和风险联动。 | 后续明细/风险联动权限按真实接口命名补齐 |
| 4 | 权益汇总深层迁移 | 权益列表、汇总卡片、手动确认、当前筛选 CSV 导出和当前筛选范围线上结算金额汇总已落地；剩余缺口是自动确认出入金和真实 MT4 自动同步。 | 后续自动确认/MT4 同步权限按真实接口命名补齐 |
| 5 | 汇率、产品、礼品、在线用户、实时返佣深层迁移 | 基础页面/API 已落地；汇率编辑操作日志、产品维护写入审计、礼品发货当前筛选 CSV 导出、批量发放、物流状态更新、礼品配置目录/库存展示、礼品发放与物流更新审计、在线记录强制下线、操作审计和当前前台 JWT 失效已覆盖；剩余缺口是汇率历史版本/更细币种、产品真实 MT4 同步/复杂品种审计、礼品兑换扣库存/真实兑换规则、积分消耗联动、在线设备维度、缓存/心跳精细口径和实时返佣旧 MT4 精确分类。 | 按模块继续新增导出、审计、写入维护、真实会话控制或结算联动权限。 |

## 6. 注释与多语言要求

后续所有新增或修改文件必须保持以下规则：

- PHP 控制器：类注释说明模块功能；方法注释说明业务流程；每个关键参数必须说明来源、含义和作用。
- Blade 页面：页面级注释说明模块用途；筛选项、按钮权限、弹窗字段必须有中文注释。
- JS 文件：页面初始化、表格列、接口参数、弹窗表单、按钮权限必须有中文逻辑注释。
- 迁移文件：说明权限 `slug`、`api_route`、`type`、`guard_type` 的含义，避免权限配置不可维护。
- 多语言：后端错误消息放入 `resources/lang/{locale}/admin.php`，前端页面文案放入 `public/js/common/lang/{locale}.js`。

## 7. 本轮审计产物

- 新增迁移缺口审计文档：`docs/admin-legacy-migration-gap-audit.md`。
- 新增审计文档测试：`tests/Feature/AdminLegacyMigrationGapAuditTest.php`。
- 本文档已记录真实 DB 测试数据、字段含义、旧控制器迁移状态和下一步优先级。

## 8. 2026-07-28 后台用户资料编辑邮箱迁移补充

- `CustomerController::cust_save_info` 的 `useremail` 分支已补入新项目 `AdminUserController::updateUser`。
- 新项目现在兼容 `email/useremail/user_email`，统一写入 `user_logins.email`，并在写库前校验邮箱格式、非空和唯一性。
- 重复邮箱会返回 `ResponseCode::VALIDATION_FAILED`，且不会写入 `user_infos` 基础资料，避免资料编辑接口出现半成功状态。
- 成功修改邮箱会写入 `operation_logs`，内容包含 `login.email:旧邮箱->新邮箱`，用于后台审计追踪。
- 当前 `CustomerController` 剩余待核对范围更新为：短信通知、MT4 注册日期联动、旧 MT4 cny 层级编码和特殊运营口径。

## 9. 2026-07-28 后台用户资料编辑实名与银行卡迁移补充

- `CustomerController::cust_save_info` 的 `userIdcardNo`、`bank_no`、`bank_class`、`bank_info` 分支已补入新项目 `AdminUserController::updateUser`。
- 新项目现在兼容 `id_card_no/userIdcardNo/IDcard_no`，统一写入 `user_auths.id_card_no`，并在写库前校验身份证号用户维度唯一性。
- 已审核银行卡快照兼容 `bank_no/bank_class/bank_info`，统一写入 `user_auths.bank_no/bank_name/bank_addr`。
- 修改已审核银行卡时先调用 `Mt4ManagerService::updateComment` 同步 `bank_no|bank_name|bank_addr`，远端失败返回 `ResponseCode::MT4_SYNC_FAILED`，且不会写入 `user_infos` 或 `user_auths`。
- 成功修改实名或银行卡会写入 `operation_logs`，身份证号和银行卡号只记录 `changed` 脱敏标识，开户行和开户地址记录可读新旧值。
- 当前 `CustomerController` 剩余待核对范围更新为：上级代理、短信通知、MT4 注册日期联动和特殊运营口径。

## 10. 2026-07-28 后台用户资料编辑出入金开关迁移补充

- `CustomerController::cust_save_info` 的 `isoutmoney` 与 `isallowmoney` 分支已补入新项目 `AdminUserController::updateUser`。
- 新项目现在只通过旧字段别名兼容该分支：`isoutmoney` 写入 `user_infos.is_withdrawal_allowed`，`isallowmoney` 写入 `user_infos.is_deposit_allowed`。
- 两个开关值在写库前严格限制为 `0/1`；非法值返回 `ResponseCode::VALIDATION_FAILED`，并且不会提前写入 `user_infos.user_name` 等基础资料。
- 现代敏感字段 `is_withdrawal_allowed/is_deposit_allowed` 仍由资料编辑白名单测试保持默认忽略，避免普通资料接口被直接扩权。
- 成功修改后写入 `operation_logs`，审计内容包含 `is_withdrawal_allowed:旧值->新值` 与 `is_deposit_allowed:旧值->新值`，用于后台追踪资金入口开关变更。
- 当前 `CustomerController` 剩余待核对范围更新为：上级代理、短信通知、MT4 注册日期联动和特殊运营口径。
## 11. 2026-07-28 后台用户资料编辑 MT4 只读状态迁移补充
- `CustomerController::cust_save_info` 的 `enablereadonly` 分支已补入新项目 `AdminUserController::updateUser`。
- 新项目现在只通过旧字段别名兼容该分支：`enablereadonly=1` 写入目标 `user_infos.is_mt4_readonly=1`，含义是锁定 MT4 交易权限；`enablereadonly=0` 写入目标 `user_infos.is_mt4_readonly=0`，含义是解除只读。
- 写本地前必须先同步 MT4：目标值为 `1` 时调用 `Mt4ManagerService::lockUser`，目标值为 `0` 时调用 `Mt4ManagerService::unlockUser`。
- MT4 返回非明确成功时返回 `ResponseCode::MT4_SYNC_FAILED`，并且不会提前写入 `user_infos.user_name` 或 `user_infos.is_mt4_readonly`。
- 只读状态值在写库前严格限制为 `0/1`；非法值返回 `ResponseCode::VALIDATION_FAILED`，避免异常输入被宽松转换成真实交易权限。
- 成功修改后写入 `operation_logs`，审计内容包含 `is_mt4_readonly:旧值->新值`，用于后台追踪交易权限变更。
- 当前 `CustomerController` 剩余待核对范围更新为：短信通知、MT4 注册日期联动、旧 MT4 cny 层级编码和特殊运营口径。

## 12. 2026-07-28 后台用户资料编辑上级代理调整迁移补充
- `CustomerController::cust_save_info` 的 `userparentId` 分支已补入新项目 `AdminUserController::updateUser`。
- 新项目现在只通过旧字段别名兼容该分支：`data.userparentId` 归一化为内部字段 `parent_agent_id`，成功后写入真实字段 `user_infos.parent_id`。
- 写库前会校验新上级必须是 `account_type=1` 的代理；上级为普通客户、当前用户自己或当前用户下级时返回 `ResponseCode::VALIDATION_FAILED`。
- 成功修改后调用 `FamilyTreeService::reassignParent`，同步重建目标用户及其下级子树的 `user_infos.family_tree`。
- `agent_descendants` 会按新 `family_tree` 物理清理并重建，旧上级不再拥有该用户及其下级的闭包范围，新上级获得新的直接或间接后代关系。
- `userparentId=0` 表示移动到平台根节点，目标用户的 `family_tree` 会变为自身 user_id，作为后代的 `agent_descendants` 行会被清理。
- 成功修改后写入 `operation_logs`，审计内容包含 `parent_id:旧值->新值`，用于后台追踪代理归属变更。
- 当前 `CustomerController` 剩余待核对范围更新为：短信通知、MT4 注册日期联动、旧 MT4 cny 层级编码和特殊运营口径。

## 2026-07-28 后台持仓汇总交易明细下钻闭环补充

- 交易明细下钻已通过 `position_summary_trades` 补齐：Layui 持仓汇总行会跳转 `admin_page_trades`，CrmUI 持仓汇总行会跳转 `/admin-crmui/trades`。
- 参数链路：`user_id` 表示当前持仓汇总行用户，`start_date/end_date` 继承当前汇总页日期筛选，`mode=all` 表示默认进入全部交易订单接口，后续仍可切换当前持仓或历史平仓。
- 解决问题：旧项目持仓汇总只停留在聚合视图，新项目现在可以从聚合行直接进入明细订单页，形成“汇总 -> 明细 -> 按同一筛选查询”的闭环。

## 13. 2026-07-29 后台持仓汇总交易账号映射闭环

- 账号映射统一使用 `user_infos.mt4_code = mt4_trades.login`。`user_infos.user_id` 是 CRM 业务用户 ID，`mt4_trades.login` 是 MT4 登录号，两者没有相等约束，任何汇总、明细或权限查询都不能直接连接。
- 持仓汇总执行链：`POST /api/admin/positionSummaryList` -> `PositionSummaryController::positionSummaryList` -> 先按管理员范围取得成员业务 `user_id` -> 读取 `user_infos.mt4_code` -> 以 `member_mt4_login = mt4_trades.login` 聚合订单 -> 返回业务用户、MT4 快照与交易汇总。
- 交易明细执行链：`POST /api/admin/tradeList(user_id)` -> `TradeController::tradeList` -> 以 `user_infos.mt4_code = mt4_trades.login` 关联账户 -> 用 `user_infos.user_id` 解释和筛选请求中的业务用户 ID -> 返回该用户映射 MT4 登录号下的真实订单。
- 权限执行链：`role_data_scopes.scope_type=custom_users` -> `AdminDataScopeService` 先把范围约束到 `user_infos.user_id` -> 再读取对应 `mt4_code` -> 最后查询 `mt4_trades.login`；该顺序避免指定用户管理员看到范围外 MT4 订单。
- RED 证据：修复前汇总会错误读取 `login=user_id` 的诱饵订单，返回诱饵盈亏 `999.99`；`custom_users` 明细也会错误返回诱饵订单 `ticket=994602`，证明历史直连同时破坏业务结果与数据权限。
- GREEN 证据：修复后汇总和明细只命中映射订单 `ticket=994601`、`login=884601`、`profit=45.25`，并排除诱饵订单与范围外订单。
- `mt4_code=0` 或没有有效映射表示该业务用户尚无可查询 MT4 账户；接口返回空交易结果，不猜测 `user_id` 是登录号，也不伪造订单。
- 当前真实 `mt4_trades` 结构没有旧项目 `MARGIN_RATE` 字段，本闭环不推导或伪造保证金率；该项继续作为风险联动的真实数据边界。
- 运行时契约由 `AdminPositionSummaryTradeAccountMappingClosureModuleTest` 覆盖，测试同时写入真实映射订单、错误直连诱饵订单和范围外订单，防止后续回退到历史错误关联。

## 14. 2026-07-29 后台风险交易账号映射与强平闭环

### 新旧项目语义结论

- 旧项目 `FengXianManageController` 的登录账号可直接用于查询旧 `MT4_TRADES.LOGIN`；新项目把 CRM 业务身份与交易账号拆分为 `user_infos.user_id` 和 `user_infos.mt4_code`，因此迁移后必须通过 `user_infos.mt4_code = mt4_trades.login` 映射。
- `user_id` 筛选、`custom_users` 数据范围和异常 IP 登录日志都使用 CRM 业务用户 ID；MT4 列表、交易统计与强平网关只使用真实 `mt4_trades.login/ticket`，两类编号不能直接比较。
- 当前真实 `mt4_trades` 没有旧 `MARGIN_RATE`。风险持仓行返回 `margin=null` 表示“该字段不可得”，汇总 `total_margin=0` 仅表示当前没有可汇总的真实保证金值，不反推或伪造旧保证金率。

### 路由与执行链

- 风险持仓：`POST /api/admin/riskPositions` -> `RiskController::positions` -> 严格校验业务 `user_id` -> `baseOpenTradeRiskQuery` 读取未平仓 `mt4_trades` -> 以 `user_infos.mt4_code = mt4_trades.login` 映射用户 -> `applyTradeFilters` 按 `user_infos.user_id` 筛选 -> `applyDataScope` 对 `user_infos.user_id` 应用后台数据范围 -> `paginateQuery + summaryFor` -> 返回 `records + summary`。
- 异常 IP 详情：`POST /api/admin/riskIpDetail` -> `RiskController::riskIpDetail` -> 校验 `login_ip/user_id` -> `baseRiskIpDetailQuery` 按 `user_login_logs.user_id` 聚合登录记录 -> 交易子查询先通过 `user_infos.mt4_code = mt4_trades.login` 转回业务 `user_id` -> 分别统计 `open_order_count/closed_order_count` -> 联动真实入金、出金聚合 -> 数据范围过滤 -> 返回该 IP 下业务用户明细。
- 强平：`POST /api/admin/riskForceClose/{id}` -> `RiskController::forceClose` -> 校验路由主键 -> 查询未平仓订单并按 `mt4_code` 关联业务用户 -> `custom_users` 等范围按 `user_infos.user_id` 校验 -> 把订单真实 `login/ticket` 传给 `RiskForceCloseGateway::close` -> 网关明确关闭后写 `operation_logs` -> 返回 provider reference；网关拒绝、连接失败、订单已关闭或范围外都不伪造成功。

### TDD 证据

- RED：旧风险列表按 `mt4_trades.login=user_id` 命中两个诱饵订单 `ticket=994702/994705`；异常 IP 详情错误得到 `open_order_count=2`；受限管理员对真实映射订单强平返回 `ResponseCode::DATA_NOT_FOUND`，网关没有收到调用。
- 字段边界 RED：真实表没有 `MARGIN_RATE`，旧实现仍把行级 `margin` 返回为字符串 `"0"`，无法区分“真实保证金为零”和“字段不可得”。
- GREEN：风险列表只返回 `ticket=994701`、`login=884701`、业务 `user_id=984701`、`profit=-25.5`、`risk_value=-27.5`；异常 IP 详情返回真实映射订单的开仓 `1`、平仓 `1`；受限管理员强平时网关收到真实 `login=884701/ticket=994701`，范围外订单被拒绝；行级 `margin` 返回 `null`。
- `AdminRiskTradeAccountMappingClosureModuleTest` 覆盖风险列表筛选、`custom_users`、诱饵订单、范围外订单、异常 IP 交易统计、真实强平目标、`MARGIN_RATE` 缺失边界和文档契约。

### 前端联动证据

- 风险联动已通过 `position_summary_risk` 同步补齐 Layui 与 CrmUI 入口：两套后台都只传业务 `user_id`，并继承 `start_date/end_date` 与 `mode=positions`。
- Layui 风控页先从 `data-default-risk-*` 读取下钻参数，再调用 `currentRiskFilters` 请求风险接口；页面分别展示业务 `user_id` 和真实 MT4 `login`，避免把两个编号继续混为一列。
- 行操作、视图切换、搜索、重置、刷新、强平和 IP 详情均使用 Lucide 图标，不使用表情符号；强平结果仍以后端网关明确响应为准。
