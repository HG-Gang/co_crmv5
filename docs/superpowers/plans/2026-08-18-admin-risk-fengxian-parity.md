# Admin FengXian Risk Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 关闭旧 `FengXianManageController` 的 9 条待人工核验记录，使盈利风险、持仓风险、异常 IP 与 IP 详情在旧 V1/V2 契约、现代双 UI、权限和数据范围上形成真实本地数据闭环。

**Architecture:** 以 `user_trades` 作为旧 MT4 风控读模型的完整本地事实源，因为它保留 `margin_rate`、止盈止损和旧未平仓时间哨兵；`user_infos`、`mt4_users`、`user_login_logs`、`deposit_records`、`withdraw_records` 提供用户、资金快照和 IP 详情。新增一个只读 `LegacyRiskQueryService` 批量完成持仓与按用户盈利聚合，`RiskController` 和旧兼容层复用它；旧兼容层只负责字段别名与 V1/V2 envelope，不复制 SQL。异常 IP 继续复用 `RiskController` 的现代查询，并补齐旧 rows/total 字段映射。

**Tech Stack:** Laravel 8.83、PHP 7.4+、MySQL 3307 隔离测试库 `co_crmv5_test`、PHPUnit 9.6、Blade、Layui、CrmUI、jQuery/原生 JavaScript、BCMath。

**Safety boundary:** 旧库 `hank_zl_data` 永久只读，正式库 `co_crmv5` 禁写，只有 PHPUnit 可写 `co_crmv5_test`；MT4 永久禁用，不调用强平或同步网关。禁止执行 `database/sql/full_reset_and_migrate.sql`。目录不是 Git 仓库，不初始化 Git、不创建 worktree、不提交。浏览器运行时受 `BLOCKED_BY_BROWSER_POLICY` 限制，不得绕过。

**Audit findings:** 当前 `profitSearch/profitSearchV2` 错误转发 `TradeController@summary`，只返回按 `symbol` 的未平仓数量，和旧按用户资金/盈亏风险完全不同；position V1/V2 目前都返回现代分页 envelope，旧字段、`orderType` 和 `margin_rate` 口径未锁定；三个旧 GET 页面都渲染同一 risk 页但缺少独立默认模式；IP 列表/详情已有真实表和 scope，但仍缺旧 envelope、旧字段和完整筛选门禁。

**Completion record (2026-08-19):** Task 1–6 已按计划完成。证据组 `admin_fengxian_risk_business_2026_08_18` 覆盖 9 条独立 method+URI，矩阵为 `legacy_route_methods=475 / verified=415 / needs_manual_business_review=60 / unresolved_legacy_source=0 / unmatched_current_route=0`。新鲜回归全部通过：风控关键词 `214 tests / 1385 assertions / 00:13.192`、FengXian `3/6`、IPAddress `5/15`、IP parity `51/145`、风控 UI `18/270`、CrmUI 通用 `17/673`、Legacy UI `64/455`、Global theme `25/3468`、Unified Blade `6/65`、Visual C `18/184`；PHP/JS 语法、`view:cache`、`view:clear` 均通过。独立规格与质量复审未保留 Critical/Important 问题。浏览器仍为 `BLOCKED_BY_BROWSER_POLICY`，未使用 Playwright CLI、Edge 或 CDP 绕过；本计划完成不代表全项目完成，仍有 60 条待人工业务核验。

---

### Task 1: 锁定 9 条路由、权限和三个专属页面模式

**Files:**
- Create: `tests/Feature/AdminLegacyRiskPermissionClosureModuleTest.php`
- Create: `tests/Feature/AdminLegacyRiskUiClosureModuleTest.php`
- Modify: `app/Http/Controllers/Admin/LegacyAdminController.php`
- Modify: `app/Http/Middleware/LegacyAdminAuthenticate.php` only if the generated defaults are wrong
- Modify: `resources/admin/layui/risk/index.blade.php`
- Modify: `app/Http/Controllers/CrmUi/Admin/PageController.php`

- [x] **Step 1: 写 9 条方法级路由与权限 RED**

逐条断言以下 method+URI、route name、`LegacyAdminController@handle`、`legacy.admin.auth` 和权限目标：三个 GET 页面及 position/profit Search 要求风险只读权限，IP 列表与详情分别要求 `admin_api_riskIpList`、`admin_api_riskIpDetail`。匿名请求不能进入控制器，普通角色缺少对应权限返回 HTTP 403。

```text
GET index/admin/fengXian/profit_list
POST index/admin/fengXian/profitSearch
POST index/admin/fengXian/profitSearchV2
GET index/admin/fengXian/position_list
POST index/admin/fengXian/positionSearch
POST index/admin/fengXian/positionSearchv2
GET index/admin/fengXian/Ipaddress_list
POST index/admin/fengXian/IpaddressSearch
GET index/admin/fengXian/IpaddressDeatail/{idaddr}
```

- [x] **Step 2: 写页面默认模式 RED**

`profit_list` 必须输出 `profit` 固定默认模式，`position_list` 输出 `positions`，`Ipaddress_list` 输出 `ipRisk`；query string 不能覆盖旧专属页模式。`/admin/risk` 与 `/admin-crmui/risk` 保留可切换 tabs。三个旧页面都必须有对应搜索字段，不生成 mock 行。

- [x] **Step 3: 运行 RED**

```powershell
vendor\bin\phpunit --colors=never tests\Feature\AdminLegacyRiskPermissionClosureModuleTest.php tests\Feature\AdminLegacyRiskUiClosureModuleTest.php
```

Expected: 页面模式和 profit 权限目标至少一项 FAIL；不得为了制造失败修改已正确的 route name。

- [x] **Step 4: 增加固定页面模式和精确权限映射**

在 `renderLegacyModule()` 为三个 GET URI 注入 `defaultRiskMode`，并在 query defaults 合入后重新覆盖。Layui marker 与 CrmUI definition 只消费受控枚举 `profit/positions/ipRisk`。profit Search 的权限不得继续借用无关的 `admin_api_tradeSummary`。

- [x] **Step 5: 运行 GREEN**

```powershell
vendor\bin\phpunit --colors=never tests\Feature\AdminLegacyRiskPermissionClosureModuleTest.php tests\Feature\AdminLegacyRiskUiClosureModuleTest.php tests\Feature\AdminLegacyPageRenderClosureModuleTest.php
```

### Task 2: 实现旧持仓风险 V1/V2 真实读模型

**Files:**
- Create: `app/Services/LegacyRiskQueryService.php`
- Create: `tests/Feature/AdminLegacyRiskPositionParityClosureModuleTest.php`
- Modify: `app/Http/Controllers/Admin/LegacyAdminController.php`
- Modify: `app/Http/Controllers/Admin/RiskController.php`
- Modify: `tests/Feature/AdminLegacyOrderRiskSearchClosureModuleTest.php`

- [x] **Step 1: 写旧字段、V1/V2、筛选和风险值 RED**

在 `user_trades` 种入：合法盈利持仓、手续费为 0、`margin_rate=0`、已平仓、普通亏损、不同用户与不同 MT4 group。断言 V1 返回 `rows/total`，V2 返回 `code/msg/count/data/totalRow`；行含 `ticket/login/user_id/symbol/cmd/volume/sl/tp/commission/profit/swaps/open_price/open_time/abs_comm/feng_xian_positionval`。

```php
$v1->assertJsonPath('rows.0.ticket', 99100101)
    ->assertJsonPath('rows.0.feng_xian_positionval', '150.00')
    ->assertJsonPath('total', 1);

$v2->assertJsonPath('code', 200)
    ->assertJsonPath('count', 1)
    ->assertJsonPath('data.0.ticket', 99100101);
```

- [x] **Step 2: 写筛选、scope、分页、空结果和非法输入 RED**

旧 `userId/orderId/orderType/startdate/enddate/page/rows` 与现代别名同时覆盖；`orderType=real_disk/test_disk` 按 `user_infos.mt4_group` 后缀 `-TEST/-TEST-P` 分类。分页优先级固定 `rows -> limit -> per_page -> 15`。用户和 ticket 必须正整数，日期必须 `Y-m-d` 且不倒置，page/per-page 必须合法；受限管理员的 rows/count/totalRow 都不能包含范围外用户。

- [x] **Step 3: 运行 RED**

```powershell
vendor\bin\phpunit --colors=never tests\Feature\AdminLegacyRiskPositionParityClosureModuleTest.php
```

Expected: FAIL；当前转发现代 `mt4_trades` envelope，缺少 `margin_rate` 过滤、旧字段和 V1/V2 区分。

- [x] **Step 4: 建立 `LegacyRiskQueryService` 并复用到现代/旧入口**

服务使用 `UserTrade::query()` + `user_infos`，应用 `AdminDataScopeService`，持仓条件固定为 `cmd in 0..5`、`close_time='1970-01-01 00:00:00'`、`margin_rate<>0`、`profit-abs(commission)>0`。`feng_xian_positionval` 使用 BCMath 字符串计算：手续费为 0 时等于 profit，否则为 `(profit-abs_comm)/abs_comm*100`，禁止 PHP float。`RiskController::positions()` 和两个旧 Search 调用同一服务。

- [x] **Step 5: 运行 GREEN 与现代风控回归**

```powershell
vendor\bin\phpunit --colors=never tests\Feature\AdminLegacyRiskPositionParityClosureModuleTest.php tests\Feature\AdminRiskMt4ModuleTest.php tests\Feature\AdminRiskTradeAccountMappingClosureModuleTest.php tests\Feature\AdminRiskPositionsUserIdValidationClosureModuleTest.php
```

### Task 3: 恢复按用户盈利风险而非按品种汇总

**Files:**
- Create: `tests/Feature/AdminLegacyRiskProfitParityClosureModuleTest.php`
- Modify: `app/Services/LegacyRiskQueryService.php`
- Modify: `app/Http/Controllers/Admin/RiskController.php`
- Modify: `app/Http/Controllers/Admin/LegacyAdminController.php`
- Modify: `routes/admin.php`
- Create: `database/migrations/2026_08_18_000001_add_admin_risk_profit_permission.php`
- Modify: `tests/Feature/AdminSecondBatchPermissionMigrationTest.php`

- [x] **Step 1: 写按用户资金、盈亏和 V1/V2 RED**

种入 `user_infos + mt4_users + user_trades`，锁定旧行字段：`user_id/user_name/parent_id/trans_mode/mt4_code/cust_eqy/mt4_grp/user_status/voided/IDcard_status/bank_status/mt4_login/mt4_name/mt4_balance/mt4_equity/mt4_regdate/total_comm/total_yuerj/total_yuecj/total_volume/total_swaps/total_profit/total_net_worth/feng_xian_val`。只有 `total_profit-total_comm>0` 的用户进入结果，V1 为 `rows/total`，V2 为 `code/msg/count/data/totalRow`。

- [x] **Step 2: 写日期、用户、用户名、scope、空结果和大额 RED**

日期按旧 `MT4_USERS.REGDATE` 语义映射 `mt4_users.created_at`，默认 `2024-01-01` 至当天；`userId` 精确筛选业务 user_id/MT4 login，`username` 模糊匹配。交易聚合必须一次 grouped subquery 完成，禁止逐用户查询。大额测试锁定金额字符串和风险比例不经 PHP float。

- [x] **Step 3: 运行 RED**

```powershell
vendor\bin\phpunit --colors=never tests\Feature\AdminLegacyRiskProfitParityClosureModuleTest.php
```

Expected: FAIL；当前只返回 `{symbol,total_volume,count}`。

- [x] **Step 4: 新增现代盈利风险 API 与旧 envelope 适配**

新增 `admin_api_riskProfitUsers`，由 `RiskController::profitableUsers()` 调用同一服务；旧 `profitSearch/profitSearchV2` 在通用转发前走专用适配器。权限迁移只新增风险只读 action，不执行迁移命令；测试仅在 `co_crmv5_test` 内建权限夹具。

- [x] **Step 5: 运行 GREEN 与权限回归**

```powershell
vendor\bin\phpunit --colors=never tests\Feature\AdminLegacyRiskProfitParityClosureModuleTest.php tests\Feature\AdminSecondBatchPermissionMigrationTest.php tests\Feature\AdminLegacyRiskPermissionClosureModuleTest.php
```

### Task 4: 补齐异常 IP 列表与详情旧契约

**Files:**
- Create: `tests/Feature/AdminLegacyRiskIpParityClosureModuleTest.php`
- Modify: `app/Http/Controllers/Admin/LegacyAdminController.php`
- Modify: `app/Http/Controllers/Admin/RiskController.php` only for shared validation/query defects
- Modify: `tests/Feature/AdminLegacyIpAddressDetailClosureModuleTest.php`
- Modify: `tests/Feature/AdminLegacyMiscOperationsClosureModuleTest.php`

- [x] **Step 1: 写 IP 列表 rows/total 和旧字段 RED**

同一 IP 至少关联两个不同业务用户才进入结果；同一用户重复登录不能误判。旧 `userId/startdate/enddate` 与现代 `login_ip/min_user_count` 均受严格校验和 scope 限制。V1 行保留 `sys_id/login_id/login_name/login_ip/login_id_desc/login_count`，同时允许现代附加字段。

- [x] **Step 2: 写 GET 详情旧 envelope、资金和交易统计 RED**

`192_168_1_1` 只还原为 `192.168.1.1`；畸形 IPv4/IPv6 失败关闭。旧详情返回 `rows/total`，每个业务用户只出现一行，并包含用户名、登录次数、最近登录、开/平仓数、入金、出金。scope 外用户不能出现在详情或聚合。

- [x] **Step 3: 运行 RED**

```powershell
vendor\bin\phpunit --colors=never tests\Feature\AdminLegacyRiskIpParityClosureModuleTest.php tests\Feature\AdminLegacyIpAddressDetailClosureModuleTest.php
```

- [x] **Step 4: 增加旧响应格式器，不复制 IP SQL**

继续调用 `RiskController::riskIpList/riskIpDetail`，仅把现代 paginator 展开为旧 rows/total 并补字段别名。数组/对象筛选、非正 user_id/min_user_count、非法日期和倒置日期返回 `VALIDATION_FAILED`。列表与详情金额不得用 float。

- [x] **Step 5: 运行 GREEN 与 IP 现代回归**

```powershell
vendor\bin\phpunit --colors=never tests\Feature\AdminLegacyRiskIpParityClosureModuleTest.php tests\Feature\AdminLegacyIpAddressDetailClosureModuleTest.php tests\Feature\AdminRiskIpModuleTest.php tests\Feature\AdminRiskIpDetailModuleTest.php tests\Feature\AdminRiskTradeAccountMappingClosureModuleTest.php
```

### Task 5: 完成 Layui/CrmUI 四模式与搜索闭环

**Files:**
- Modify: `resources/admin/layui/risk/index.blade.php`
- Modify: `public/js/apps/admin/layui/pages.js`
- Modify: `app/Http/Controllers/CrmUi/Admin/PageController.php`
- Modify: `public/js/apps/crmui/admin.js` only if per-tab columns cannot be expressed by the current contract
- Modify: `resources/lang/*/admin.php`
- Modify: `resources/lang/*/crmui.php`
- Modify: `tests/Feature/AdminLegacyRiskUiClosureModuleTest.php`

- [x] **Step 1: 写双 UI tabs、搜索、reset、详情和多语言 RED**

Layui/CrmUI 至少提供 `盈利风险/持仓风险/追保预警/异常 IP` 四个可辨识 tab；每个 table 有对应搜索条件。tab 切换、搜索、reset 均回第一页；旧专属页初载到固定模式。IP 详情必须弹框、自适应 PC/iPad/移动端，并只在 IP tab 行显示。禁止页面 mock 数据和硬编码中文列名。

- [x] **Step 2: 运行 RED**

```powershell
vendor\bin\phpunit --colors=never tests\Feature\AdminLegacyRiskUiClosureModuleTest.php tests\Feature\GlobalCrmThemeCoverageTest.php tests\Feature\UnifiedBladeDesignSystemTest.php tests\Feature\VisualCFoundationContractTest.php
```

- [x] **Step 3: 实现统一模式配置和响应式表格**

Layui 增加 profit table/config，所有模式从同一筛选表单读取适用字段；CrmUI viewTabs 为每个模式声明 API、权限和列，若当前 generic contract 不支持 per-tab columns，只做最小扩展并让列切换不改变表格宽度。IP 详情 modal 使用现有弹窗组件和断点 token。

- [x] **Step 4: 运行 GREEN、JS/PHP 语法与 Blade 编译**

```powershell
vendor\bin\phpunit --colors=never tests\Feature\AdminLegacyRiskUiClosureModuleTest.php tests\Feature\LegacyUiReplacementCoverageTest.php tests\Feature\GlobalCrmThemeCoverageTest.php tests\Feature\UnifiedBladeDesignSystemTest.php tests\Feature\VisualCFoundationContractTest.php
node --check public\js\apps\admin\layui\pages.js
node --check public\js\apps\crmui\admin.js
php artisan view:cache
php artisan view:clear
```

浏览器四视口仅在策略允许时执行；若仍为 `BLOCKED_BY_BROWSER_POLICY`，记录阻塞，不使用 Playwright CLI、Edge 或 CDP 绕过。

### Task 6: 双复审、全回归、9 条证据和下一轮

**Files:**
- Create: `tests/Unit/AdminFengXianRiskMatrixClosureTest.php`
- Modify: `docs/audits/旧项目路由核验证据.json`
- Regenerate: `storage/app/audits/旧项目模块逻辑迁移核验矩阵.json`
- Regenerate: `docs/audits/旧项目模块逻辑迁移核验矩阵.md`
- Modify: `docs/项目整体进度梳理-2026-08-17.md`
- Modify: `docs/superpowers/plans/2026-08-18-admin-risk-fengxian-parity.md`

- [x] **Step 1: 独立规格复审**

逐条核查 9 个 method+URI 的七维证据；Critical/Important 必须修复并重审。重点确认 profit 不再是 symbol summary、position V1/V2 区分、IP 详情旧 GET envelope、三个页面模式和权限目标。

- [x] **Step 2: 独立质量复审**

检查 scope、BCMath、数组输入失败关闭、日期边界、分页汇总、N+1、grouped subquery、双 UI reset/tab/detail 和无重复 SQL。Critical/Important 清零后才能写 evidence。

- [x] **Step 3: 运行完整风控专项回归**

```powershell
vendor\bin\phpunit --colors=never --filter "(?i)(risk|fengxian|ipaddress)" tests\Feature
```

Expected: exit 0，记录新鲜 tests/assertions/time。

- [x] **Step 4: 写 9 条独立七维证据与矩阵门禁**

证据组固定为 `admin_fengxian_risk_business_2026_08_18`；每个 GET/V1/V2/详情均登记独立 method+URI、七维方法级说明和实际通过的测试方法名。

- [x] **Step 5: 重生成矩阵并验证目标计数**

```powershell
php scripts\generate-legacy-implementation-matrix.php
vendor\bin\phpunit --colors=never tests\Unit\AdminFengXianRiskMatrixClosureTest.php
vendor\bin\phpunit --colors=never tests\Unit\AdminWithdrawStatusMatrixClosureTest.php
vendor\bin\phpunit --colors=never tests\Unit\Phase2IdentityOrganizationMatrixClosureTest.php
vendor\bin\phpunit --colors=never tests\Unit\FrontBigNumberBusinessMatrixClosureTest.php
```

Expected: `legacy_route_methods=475 / verified=415 / needs_manual_business_review=60 / unresolved_legacy_source=0 / unmatched_current_route=0`。

- [x] **Step 6: 更新总进度并登记下一轮入口**

已记录回归、复审、矩阵时间、浏览器策略和残余风险。下一批从剩余 60 条另行选择并继续三方只读审计；不得把风控 9 条完成写成全项目完成。
