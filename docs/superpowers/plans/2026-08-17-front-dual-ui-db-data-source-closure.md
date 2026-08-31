# Front Dual UI DB Data Source Closure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 证明并补齐前台 Layui、CrmUI 与 Naive 渲染家族的业务列表、汇总、图表和动态选项全部读取真实数据库数据，不在浏览器端生成 mock/demo 业务记录。

**Architecture:** 继续以 `routes/front.php` 的 REST API、`App\Http\Controllers\Front` 控制器和现有 Service/Model 为唯一业务数据入口。Layui 与 CrmUI/Naive 只负责渲染 API 响应；动态交易品种统一读取 `symbol_prices`，空结果必须保持空状态，不能回退到写死示例。审计证据记录页面、API、控制器、数据表和运行时测试，禁止写旧库与正式新库。

**Tech Stack:** Laravel 8.83、PHP 7.4+、PHPUnit 9.6、MySQL 隔离测试库 `co_crmv5_test`、Blade、Layui、CrmUI/Naive、jQuery/原生 JavaScript。

**Safety boundary:** `hank_zl_data` 永久只读，正式 `co_crmv5` 禁写；只有 PHPUnit 可写 `co_crmv5_test`。当前目录没有 `.git`，不初始化仓库、不创建 worktree，以 RED/GREEN 输出、修改文件 SHA-256 和审计文档作为检查点。

---

### Task 1: 建立双 UI 数据源清单与 mock 门禁

**Files:**
- Create: `docs/audits/2026-08-17-front-dual-ui-db-data-source-audit.md`
- Create: `tests/Feature/FrontBusinessDataSourceAuditTest.php`
- Read: `resources/front/layui/**/*.blade.php`
- Read: `resources/front/crmui/**/*.blade.php`
- Read: `app/Http/Controllers/CrmUi/Front/PageController.php`
- Read: `public/js/apps/front/layui/*.js`
- Read: `public/js/apps/crmui/front.js`

- [x] **Step 1: 写业务 mock 静态门禁 RED**

测试扫描前台业务 Blade/JS，排除仅在 testing 环境且 `visual_audit=1` 时加载的视觉审计 fixture；禁止业务源码出现本地 mock 行、空结果示例行或写死交易品种数组。

```php
$this->assertStringNotContainsString('mockWhenEmpty', $businessSource);
$this->assertStringNotContainsString('renderMockData', $businessSource);
$this->assertDoesNotMatchRegularExpression('/XAUUSD\s*,\s*EURUSD/', $businessSource);
```

- [x] **Step 2: 运行 RED 并记录真实命中**

Run: `vendor\bin\phpunit --colors=never tests\Feature\FrontBusinessDataSourceAuditTest.php`

Expected: 如果存在本地业务假数据则 FAIL 并给出文件；仅测试 fixture 命中不得失败。

- [x] **Step 3: 删除业务假数据或收紧测试 fixture 边界**

只删除实际业务 fallback；保留 `resources/views/partials/visual-audit-fixture.blade.php` 的 testing-only 条件：

```blade
@if(app()->environment('testing') && request()->query('visual_audit') === '1')
    <script src="{{ asset('/js/testing/visual-audit-fixture.js') }}"></script>
@endif
```

- [x] **Step 4: 生成页面到 DB 的审计清单**

审计文档逐页写明 `页面 -> API -> Controller@method -> Model/DB table -> 测试证据`，至少覆盖 dashboard、account、position、orders、flow、commission、agent、deposit、withdraw、gift、news、profile。

- [x] **Step 5: 运行 GREEN**

Run: `vendor\bin\phpunit --colors=never tests\Feature\FrontBusinessDataSourceAuditTest.php`

Expected: PASS，业务源零本地 mock；视觉 fixture 仍仅在 testing + 显式 query 下加载。

### Task 2: 交易品种动态选项真实 DB 运行时闭环

**Files:**
- Create: `tests/Feature/FrontTradeSymbolRuntimeClosureModuleTest.php`
- Modify: `app/Http/Controllers/Front/TradeSymbolController.php`
- Modify: `tests/Feature/FrontTradeSymbolControllerCommentReadabilityTest.php`

- [x] **Step 1: 写真实 `symbol_prices` 运行时 RED**

向测试库写入启用、重复、停用、空白和软删除品种，调用 `GET /api/front/trade-symbols`，要求只返回启用且未软删除的唯一 `value/label`。

```php
$response->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);
$this->assertSame([
    ['value' => 'ZDBEURUSD', 'label' => 'ZDBEURUSD'],
    ['value' => 'ZDBXAUUSD', 'label' => 'ZDBXAUUSD'],
], $response->json('data.list'));
```

- [x] **Step 2: 运行 RED**

Run: `vendor\bin\phpunit --colors=never tests\Feature\FrontTradeSymbolRuntimeClosureModuleTest.php`

Expected: FAIL，当前直接 DB 查询会把 `deleted_at` 非空但 `status=1` 的品种返回。

- [x] **Step 3: 最小修复软删除过滤**

仅当真实表含 `deleted_at` 时追加过滤，保留旧库 `sym_symbol/voided` 与新库 `symbol/status` 兼容。

```php
if (Schema::hasColumn('symbol_prices', 'deleted_at')) {
    $query->whereNull('deleted_at');
}
```

- [x] **Step 4: 运行 GREEN 与静态契约**

Run: `vendor\bin\phpunit --colors=never tests\Feature\FrontTradeSymbolRuntimeClosureModuleTest.php tests\Feature\FrontTradeSymbolControllerCommentReadabilityTest.php`

Expected: PASS，返回顺序稳定、去重、无停用/软删除/空白选项。

### Task 3: Layui 与 CrmUI/Naive 动态选项消费闭环

**Files:**
- Modify: `tests/Feature/FrontUiRegressionTest.php`
- Modify if RED requires: `public/js/apps/front/layui/module-page.js`
- Modify if RED requires: `public/js/apps/crmui/front.js`
- Modify if RED requires: `app/Http/Controllers/CrmUi/Front/PageController.php`

- [x] **Step 1: 写两家族消费契约 RED**

验证 position summary、open order、closed order 的 `symbol` 都是动态 select；两套脚本均以 GET 请求 `/api/front/trade-symbols`，只渲染响应的 `data.list`，请求失败或空结果不写入示例品种。

```php
$this->assertStringContainsString("symbols: '/api/front/trade-symbols'", $script);
$this->assertStringContainsString("symbols: 'GET'", $script);
$this->assertStringNotContainsString("['XAUUSD', 'EURUSD']", $script);
```

- [x] **Step 2: 运行 RED**

Run: `vendor\bin\phpunit --colors=never --filter trade_symbol tests\Feature\FrontUiRegressionTest.php`

Expected: 发现任一 UI 家族静态 fallback 时 FAIL；若现有实现已满足，则保留测试作为审计证据并进入下一步。

- [x] **Step 3: 最小修复消费差异**

只统一请求方法、响应归一化和空状态，不增加缓存层或新依赖；Naive 继续复用 CrmUI canonical page 定义与同一 REST API。

- [x] **Step 4: 运行 GREEN**

Run: `vendor\bin\phpunit --colors=never --filter "trade_symbol|dynamic_database_options" tests\Feature\FrontUiRegressionTest.php`

Expected: PASS，三个页面和三个 UI family 使用同一 DB-backed option API。

### Task 4: 前台高风险模块运行时 DB 证据

**Files:**
- Modify: `tests/Feature/FrontUiRegressionTest.php`
- Create only when a focused fixture is clearer: `tests/Feature/FrontDualUiDbSourceClosureModuleTest.php`
- Modify only when a failing runtime test proves a defect: target `app/Http/Controllers/Front/*.php`

- [x] **Step 1: 按审计缺口写最小运行时 RED**

优先覆盖只有静态源码断言、没有真实响应断言的模块；测试写入隔离库后调用实际 REST API，断言返回值等于测试行且空结果不出现本地示例。每个测试只覆盖一个模块和一个权限范围。

- [x] **Step 2: 逐条运行 RED**

Run: `vendor\bin\phpunit --colors=never --filter <new_test_name> <test_file>`

Expected: 只允许因目标 DB 查询/字段/范围缺口失败，不接受 fixture、正式库保护或随机数据失败。

- [x] **Step 3: 修复根因并运行 GREEN**

控制器必须查询现有 Model/DB table；前端不得生成业务行。权限、代理范围、状态和分页沿用现有服务，不创建第二事实源。

- [x] **Step 4: 更新审计证据**

将运行时测试类、方法、API 和表名写入 `docs/audits/2026-08-17-front-dual-ui-db-data-source-audit.md`。

### Task 5: 进度账本与回归门禁

**Files:**
- Modify: `docs/项目整体进度梳理-2026-08-17.md`
- Modify only for actual verified rows: `storage/app/audits/旧项目模块逻辑迁移核验矩阵.json`
- Modify: `docs/audits/2026-08-17-front-dual-ui-db-data-source-audit.md`

- [x] **Step 1: 只回写有七维证据的矩阵记录**

若本批次对应 `needs_manual_business_review` 记录，必须同时具备旧行为、路由、后端、前端、权限范围、校验错误和自动化测试证据后才改为 `verified`；不满足则保持原状态并写明缺口。

- [x] **Step 2: 更新总进度文档**

记录本批次完成项、测试数量、仍阻塞的浏览器矩阵与需授权操作；不得把未执行的浏览器验证、正式迁移或正式库修复写成完成。

- [x] **Step 3: 运行目标回归**

Run: `vendor\bin\phpunit --colors=never tests\Feature\FrontTradeSymbolRuntimeClosureModuleTest.php tests\Feature\FrontTradeSymbolControllerCommentReadabilityTest.php tests\Feature\FrontBusinessDataSourceAuditTest.php`

Expected: 全部 PASS。

- [x] **Step 4: 运行前台综合回归**

Run: `vendor\bin\phpunit --colors=never tests\Feature\FrontUiRegressionTest.php`

Expected: 0 failures / 0 errors。

- [x] **Step 5: 运行语法与证据校验**

Run: `php -l app\Http\Controllers\Front\TradeSymbolController.php`

Run: `php -l tests\Feature\FrontTradeSymbolRuntimeClosureModuleTest.php`

Run: `php -r "$d=json_decode(file_get_contents('storage/app/audits/旧项目模块逻辑迁移核验矩阵.json'),true,512,JSON_THROW_ON_ERROR); echo count($d['rows']);"`

Expected: PHP 语法通过；矩阵 JSON 可解析且总行数保持 475。
