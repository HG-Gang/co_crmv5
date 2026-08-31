# 前台双 UI 真实数据库数据源审计

> 审计日期：2026-08-18  
> 范围：前台 Layui、CrmUI/Naive 页面族；dashboard、account、position、orders、flow、commission、agent、deposit、withdraw、gift、news、profile、big-agent。  
> 安全边界：只读审查当前源码；旧库 `hank_zl_data` 永久只读，正式库 `co_crmv5` 禁写，运行时测试只允许写 `co_crmv5_test`。

## 一、结论

1. Layui 与 CrmUI/Naive 的业务页面最终调用同一组 `/api/front/*` 接口、Controller 和新库业务表，不存在两套业务事实源。
2. `/front-naive/*` 复用 `App\Http\Controllers\CrmUi\Front\PageController`、`resources/front/crmui` 和 `public/js/apps/crmui/front.js`，只通过 `renderFamily()` 切换视觉族，不维护独立 Naive 业务接口。
3. 生产前台 Blade/JavaScript 未发现空数据时生成 mock 行、Demo 凭证或固定品种 fallback。视觉审计 fixture 仅允许在 `testing` 环境并显式传入 `visual_audit=1` 时加载。
4. 动态交易品种由 `GET /api/front/trade-symbols` 读取 `symbol_prices`；表不存在时返回空列表，停用、空白、重复及软删除记录均不进入选项。
5. 默认 `DatabaseSeeder` 的演示业务数据污染风险已关闭；`position/summary2`、账户类型切换及 Big Agent Naive 路由三项双 UI 差异也已关闭，三套视觉家族继续复用同一业务事实源。

认证边界：`/api/front/*` 业务接口统一位于 `jwt.auth:user + sso:user` 中间件组；旧 `/user/*` 除显式公开白名单外统一追加 `legacy.front.auth`。普通代理树的唯一范围事实源为 `user_infos.parent_id`，`agent_descendants` 仅是可重建派生闭包。`/front-naive/*` 只切换渲染家族，不创建第二套 Controller、API 或 DB 数据源。

## 二、页面到数据库映射

| 模块 | Layui 页面/脚本 | CrmUI/Naive 页面定义 | API 与 Controller | 新库数据表 | 运行时证据 |
| --- | --- | --- | --- | --- | --- |
| Dashboard | `resources/front/layui/dashboard/index.blade.php`；`public/js/apps/front/layui/pages.js` | `PageController@dashboardPage`；`resources/front/crmui/dashboard/index.blade.php` | `GET /api/front/dashboard` → `Front\DashboardController@dashboardData` | `user_infos`、`user_trades`、`deposit_records`、`withdraw_records`、`commission_records`、`news`、`news_langs`、`system_configs` | `FrontBusinessModulesClosureTest::test_dashboard_data_contains_core_sections`；`FrontDashboardScopeFallbackModuleTest` |
| Account | `account/info.blade.php`、`balance.blade.php`、`voucher.blade.php`、`cancel.blade.php`；共享 `front-account-type-switch` 组件 | `PageController@accountInfoPage/accountBalancePage/accountVoucherPage/accountCancelPage` | `GET account/profile`、`account/balance`、`account/vouchers`；`PATCH account/trading-profile` 委托既有 `changeAccountSave()`；注销由 `CancelController` 处理 | `user_infos`、`user_logins`、`group_configs`、`user_trades`、`deposit_records`、`withdraw_records`、`commission_records`、`voucher_infos`、`cancel_applies` | `FrontBusinessModulesClosureTest`；`FrontAccountTypeChangeClosureModuleTest`；`FrontAccountProfileOwnerBoundaryClosureModuleTest` |
| Position | `position/summary.blade.php`、`summary2.blade.php`；`module-page.js` | `PageController@positionSummaryPage` 与独立 `position_self_summary` 页面 | `GET positions/summary`、`positions/self-summary`、`direct-agent-summaries`、`positions/trades` → `PositionController`；`selfSummary()` 委托旧 `positionSummary2Search()`；`GET trade-symbols` → `TradeSymbolController@index` | `user_infos`、`agent_levels`、`user_trades`、`symbol_prices` | `FrontLegacyPositionSummary2ClosureModuleTest`；`FrontLegacyPositionSummary2PageClosureTest`；`FrontTradeSymbolRuntimeClosureModuleTest` |
| Open/Closed Orders | `order/open.blade.php`、`order/closed.blade.php` | `PageController@openOrderPage/closedOrderPage` | `GET orders/open`、`orders/closed` → `OrderController` | `user_trades`；关联 `user_infos`、`user_logins`、`agent_levels`、`commission_records` | `FrontBusinessModulesClosureTest`；`FrontOrderOwnScopeOwnerBoundaryClosureModuleTest` |
| Flow | `flow/index.blade.php`；`pages.js` | `PageController@flowPage` | `GET flows/account/deposits/withdrawals/withdrawal-applications/direct-*` → `FlowController` | `deposit_records`、`withdraw_records`、`commission_records`；直属范围取 `user_infos.parent_id` | `FrontBusinessModulesClosureTest`；`FrontFlowOwnScopeOwnerBoundaryClosureModuleTest` |
| Commission | `commission/realtime.blade.php`、`history.blade.php`、`transfer.blade.php` | `PageController@commissionRealtimePage/commissionHistoryPage/commissionTransferPage` | `GET commissions/realtime/history`、`transfer-agent-options`；`POST commissions/transfers` → `CommissionController` | `user_trades`、`commission_records`、`commission_transfers`、`commission_transfer_outbox`、`user_infos` | `FrontBusinessModulesClosureTest`；`FrontCommissionHistoryOwnerBoundaryClosureModuleTest`；`FrontUiRegressionTest` 转账运行时用例 |
| Agent | `agent/sub.blade.php`、`customers.blade.php`、`confirm-level.blade.php`、`group-change.blade.php` | `PageController` 代理页面定义 | `GET agents/direct/direct-customers/statistics/level-confirmation/group-changes` → `AgentController` | `user_infos`、`user_logins`、`agent_levels`、`group_configs`、`trans_apply_logs`；统计读取交易、资金和返佣表 | `FrontBusinessModulesClosureTest`；`FrontAgentMainListOwnerBoundaryClosureModuleTest`；`Phase2CrmUiAgentHierarchyInteractionContractTest` |
| Deposit | `deposit/index.blade.php`；`pages.js` | `PageController@depositPage` | `GET deposits/form-options/history`、`POST deposits/submissions` → `DepositController` | `payment_channels`、`system_configs`、`user_infos`、`deposit_records` | `FrontBusinessModulesClosureTest`；`FrontDepositOwnerBoundaryClosureModuleTest`；`PaymentGatewayRegistryTest` |
| Withdraw | `withdraw/index.blade.php`；`pages.js` | `PageController@withdrawPage` | `GET withdrawals/form-options/history`、`POST withdrawals/submissions` → `WithdrawController` | `user_infos`、`user_auths`、`user_addresses`、`system_configs`、`withdraw_records`、`withdraw_settlement_outbox` | `FrontBusinessModulesClosureTest`；`FrontWithdrawOwnerBoundaryClosureModuleTest`；`FrontWithdrawSettlementClosureModuleTest` |
| Gift | `gift/address.blade.php`、`gift/list.blade.php` | `PageController@giftAddressPage/giftListPage` | `gift-addresses` CRUD、`GET gifts` → `GiftController` | `user_addresses`、`user_infos`、`gift_items`、`gift_shipments` | `FrontBusinessModulesClosureTest`；`FrontGiftShipmentOwnerBoundaryClosureModuleTest` |
| News | `news/index.blade.php` | `PageController@newsPage` | `GET /api/front/news` → `NewsController@newsList` | `news`、`news_langs`；翻译缺失时回退同一真实 `news` 主表字段 | `FrontBusinessModulesClosureTest`；`FrontNewsListInputValidationClosureModuleTest` |
| Profile | `profile/index.blade.php`；`pages.js` | `PageController@profilePage` | `GET/PATCH profile` 及 password、email、phone、avatar、identity、bank-card 等写入口 → `ProfileController` | `user_logins`、`user_infos`、`user_auths`；银行卡变更校验读取 `withdraw_records` | `FrontProfileClosureTest`；各 `FrontProfile*OwnerBoundaryClosureModuleTest` |
| Big Agent | `/user/agents/proxy/list`、`position/summary`、`open/order`、`close/order` → `resources/front/layui/legacy-big-agent/*` | `/front-crmui/big-agent/*` 与 `/front-naive/big-agent/*` 共用 `BigAgentPageController` 和 `resources/front/crmui/big-agent/*`，仅切换视觉家族 | 六个旧搜索 POST → `Front\BigNumberController`；`GET /user/agents/trade-symbols` → `TradeSymbolController@index` | `big_agents`、`big_agent_login_logs`、`user_infos`、`user_logins`、`agent_levels`、`user_trades`、`symbol_prices` | `FrontBigNumberPositionDataSourceClosureModuleTest`；四个 BigNumber owner/applicant 边界测试；`FrontBigAgentLegacyShellClosureTest`；`CrmUiStackTest` |

路由入口统一由 `routes/front.php` 定义，`App\Providers\RouteServiceProvider` 增加 `/api/front` 前缀。前端家族只消费响应，不直接连接数据库。

## 三、Mock 与动态选项门禁

- `tests/Feature/FrontBusinessDataSourceAuditTest.php` 扫描生产前台 Blade/JavaScript，禁止 `mockRows`、`renderMockData`、Demo 凭证和固定 `XAUUSD/EURUSD` 选项。
- `resources/views/partials/visual-audit-fixture.blade.php` 的脚本注入必须同时满足 `app()->environment('testing')` 和 `visual_audit=1`。
- `TradeSymbolController@index` 查询 `symbol_prices`，兼容 `symbol/status` 与旧字段命名，过滤停用、空白及 `deleted_at` 非空记录，排序并去重后返回 `value/label`。
- Layui 的 `module-page.js` 与 CrmUI/Naive 的 `front.js` 均请求 `/api/front/trade-symbols`；空响应保持空选项，不回退示例品种。
- 大代理两套页面改用 `GET /user/agents/trade-symbols`，同样只读取 `symbol_prices`。CrmUI 大代理表格提交 `page/limit/per_page`、渲染后端 `footer`，服务端总数收缩时先修正越界页码并重新请求合法页。

## 四、风险与后续动作

### 1. 默认 Seeder 的生产写入风险（已关闭）

`database/seeders/DatabaseSeeder.php` 现在始终执行全新库所需的 `InitialDataSeeder`，但只有运行环境为 `local/testing` 且 `config('seeding.front_demo_enabled') === true` 时才调用 `FrontDemoDataSeeder`。`config/seeding.php` 从 `FRONT_DEMO_SEEDER_ENABLED` 读取开关，`.env.example` 默认值为 `false`；生产环境即使误把开关设为 true 也会被环境白名单拒绝。

`DatabaseSeederDemoGateClosureModuleTest` 直接执行 `DatabaseSeeder::run()`，通过 Facade mock 隔离全部 DB 写入，并验证拒绝场景仍调用 `InitialDataSeeder`、允许场景才按顺序追加 `FrontDemoDataSeeder`。测试还覆盖 local 未显式开启、production 强制拒绝和非布尔 truthy 值拒绝。本轮未执行任何 Seeder 或数据库写命令。

### 2. 双 UI 链路差异（已关闭）

- `position/summary2` 新增 `GET /api/front/positions/self-summary`；`PositionController@selfSummary` 直接委托旧 `positionSummary2Search()`，CrmUI/Naive 使用独立 `position_self_summary` 页面且只暴露日期筛选，不再错误复用代理树汇总。
- 账户类型切换新增 `PATCH /api/front/account/trading-profile`；现代入口委托既有 `changeAccountSave()`，Layui、CrmUI、Naive 共用一个 Blade 组件，继续复用同一事务、资格校验、未平仓检查、配对组与 MT4 同步逻辑。
- 新增 `/front-naive/big-agent/login|dashboard|{path?}` 路由族；其与 CrmUI 共用 `BigAgentPageController`、旧 `bigAgents` session 和受限旧 API，`LegacyFrontAuthenticate` 按请求家族返回对应登录地址，不接入普通用户 JWT `/api/front/*`。

### 3. 非 mock 的硬编码业务默认值

以下值来自后端失败降级，不属于前端 mock，但仍应迁移到可审计配置并补缺配置语义：Account ECN 门槛 `3000`；Deposit 汇率 CNY `7.0`、JPY `145.0`；Withdraw 汇率 `6.8`、限额 `50/50000`、风险率 `100`；Dashboard 下载地址 `#`。

## 五、验收门禁

本审计关闭前必须保持以下命令全绿：

```powershell
vendor\bin\phpunit --colors=never tests\Feature\FrontBusinessDataSourceAuditTest.php
vendor\bin\phpunit --colors=never tests\Feature\FrontTradeSymbolRuntimeClosureModuleTest.php
vendor\bin\phpunit --colors=never tests\Feature\FrontTradeSymbolControllerCommentReadabilityTest.php
vendor\bin\phpunit --colors=never --filter "trade_symbol|dynamic_database_options" tests\Feature\FrontUiRegressionTest.php
php -l app\Http\Controllers\Front\TradeSymbolController.php
```

2026-08-17 本轮已执行证据：

- `FrontBusinessDataSourceAuditTest`：2 tests / 913 assertions。
- `FrontTradeSymbolRuntimeClosureModuleTest`：1 test / 6 assertions。
- `FrontTradeSymbolControllerCommentReadabilityTest`：2 tests / 21 assertions。
- `FrontUiRegressionTest --filter trade_symbol`：2 tests / 62 assertions。
- `FrontUiRegressionTest --filter crmui`：30 tests / 721 assertions。
- 完整 `FrontUiRegressionTest`：141 tests / 2978 assertions。
- `FrontBigNumberPositionDataSourceClosureModuleTest`：14 tests / 148 assertions。
- 全部 `FrontBig*`：72 tests / 754 assertions。
- `AgentHierarchyClosureRebuildTest`：22 tests / 102 assertions。
- `DatabaseSeederDemoGateClosureModuleTest`：PHP 8.0 与 PHP 7.4 均为 8 tests / 15 assertions。
- `TestDatabaseGuardTest`：PHP 8.0 与 PHP 7.4 均为 20 tests / 46 assertions；`app/bootstrap/config/database/routes/tests` 的 PHP 7.4 全量语法扫描为 0 failures。
- `AdminLegacyRouteSemanticClosureTest`、`AdminLegacyAmountWithdrawClosureModuleTest`、`AdminLegacyWithdrawPermissionClosureModuleTest`：合计 47 tests / 399 assertions，证明为 PHP 7.4 改写的两处出金状态路由映射保持原语义。
- `FrontLegacyPositionSummary2ClosureModuleTest` 与页面测试：5 tests / 59 assertions。
- `FrontAccountTypeChangeClosureModuleTest`：11 tests / 80 assertions。
- `FrontBigAgentLegacyShellClosureTest`：14 tests / 226 assertions；`CrmUiStackTest`：6 tests / 520 assertions。
- `FrontUiRegressionTest` 的 `position/account/naive/big_agent` 四组筛选：18 tests / 439 assertions；相关 PHP、JS 语法和 Blade cache/clear 均 exit 0。

完整前台 UI 回归已确认搜索 placeholder 与中英文静态翻译 key 门禁同时通过；该结果仍不替代四视口浏览器交互验收。

本文件证明数据源链路、三个双 UI 差异的代码与自动化闭环；浏览器四视口交互仍需在浏览器策略解除后独立实施与验证。
