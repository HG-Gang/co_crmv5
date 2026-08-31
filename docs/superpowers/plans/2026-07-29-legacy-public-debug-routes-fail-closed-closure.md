# 旧公开调试与维护路由失败关闭闭环实施计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 将矩阵中尚未核验的 6 条旧公开调试路由纳入统一失败关闭闭环，并保证 `LegacyMaintenanceController` 的全部公开路由以后不会漏测。

**Architecture:** 项目1的 `test_export` 是带 `dd()` 的未完成汇总查询，`test_serach/{id}` 与 `whstest` 指向不存在的方法，`test_order` 可匿名调用外部 OTC，`test_rights_sum` 会创建权益结算记录，`trades_exp_zero` 会调用 MT4 入金并写入爆仓清零记录。这些入口没有可恢复的生产 Blade 合同，项目2继续由 `LegacyMaintenanceController` 统一记录警告并返回 HTTP 423；本批不新增第二套业务实现，只补齐穷举测试与迁移证据。

**Tech Stack:** PHP 7.4、Laravel 8 路由集合、PHPUnit 9、JSON 迁移证据、MySQL。

---

### Task 1: 用运行时路由建立漏测红灯

**Files:**
- Modify: `tests/Feature/FrontLegacyMaintenanceRuntimeClosureModuleTest.php`

- [ ] **Step 1: 增加数据提供器完整性测试**

引入 `App\Http\Controllers\Front\LegacyMaintenanceController` 和 `Illuminate\Support\Facades\Route`，从运行时路由集合筛选对应 Controller 路由，移除 GET 自动附带的 HEAD，再与数据提供器中的 `HTTP 方法 + 路由模板 URI` 排序后严格比较。参数路由可以额外提供 `route_uri`，实际请求仍使用可执行 URI：

```php
public function test_data_provider_covers_every_legacy_maintenance_route(): void
{
    $provided = collect(self::disabledLegacyMaintenanceRouteProvider())
        ->map(static function (array $case): string {
            return strtoupper($case['method']) . ' ' . ($case['route_uri'] ?? ltrim($case['uri'], '/'));
        })
        ->sort()
        ->values()
        ->all();

    $registered = collect(Route::getRoutes()->getRoutes())
        ->filter(static function ($route): bool {
            return strpos((string) $route->getActionName(), LegacyMaintenanceController::class . '@') === 0;
        })
        ->flatMap(static function ($route): array {
            return collect($route->methods())
                ->reject(static fn (string $method): bool => strtoupper($method) === 'HEAD')
                ->map(static fn (string $method): string => strtoupper($method) . ' ' . $route->uri())
                ->all();
        })
        ->sort()
        ->values()
        ->all();

    $this->assertSame($registered, $provided);
}
```

- [ ] **Step 2: 运行测试并确认 RED**

运行：

```powershell
php artisan test --filter=FrontLegacyMaintenanceRuntimeClosureModuleTest --colors=never
```

预期：完整性测试列出当前数据提供器遗漏的 `/test/withdraw`、`/test_rights_sum`、`/test_serach/123`、`/test_export`、`/test_order`、`/trades_exp_zero`、`/whstest`；已有 17 条逐路由测试继续通过。

### Task 2: 补齐全部失败关闭运行测试

**Files:**
- Modify: `tests/Feature/FrontLegacyMaintenanceRuntimeClosureModuleTest.php`
- Verify: `app/Http/Controllers/Front/LegacyMaintenanceController.php`
- Verify: `routes/web.php`

- [ ] **Step 1: 在显式数据提供器加入 7 条遗漏路由**

```php
'test-withdraw' => ['method' => 'POST', 'uri' => '/test/withdraw', 'action' => 'testWithdraw'],
'test-rights-sum' => ['method' => 'GET', 'uri' => '/test_rights_sum', 'action' => 'testRightsSum'],
'test-search' => [
    'method' => 'GET',
    'uri' => '/test_serach/123',
    'route_uri' => 'test_serach/{id}',
    'action' => 'testSearch',
],
'test-export' => ['method' => 'POST', 'uri' => '/test_export', 'action' => 'testExport'],
'test-order' => ['method' => 'GET', 'uri' => '/test_order', 'action' => 'testOrder'],
'trades-exp-zero' => ['method' => 'GET', 'uri' => '/trades_exp_zero', 'action' => 'tradesExpZero'],
'whs-test' => ['method' => 'GET', 'uri' => '/whstest', 'action' => 'whsTest'],
```

每条继续复用现有测试方法，必须断言：HTTP 423、`ResponseCode::OPERATION_NOT_ALLOWED`、多语言禁用消息、准确 `legacy_action` 和实际 path。

- [ ] **Step 2: 运行目标测试并确认 GREEN**

运行：

```powershell
php artisan test --filter=FrontLegacyMaintenanceRuntimeClosureModuleTest --colors=never
```

预期：数据提供器完整性 1 项和 24 条逐路由失败关闭测试全部通过；请求不会调用数据库、MT4、短信、支付或 OTC 服务。

- [ ] **Step 3: 运行相关回归与语法检查**

```powershell
php artisan test --filter=FrontLegacyRouteCompatibilityTest --colors=never
php artisan test --filter=FrontLegacyMaintenanceControllerCommentReadabilityTest --colors=never
php -l tests\Feature\FrontLegacyMaintenanceRuntimeClosureModuleTest.php
php -l app\Http\Controllers\Front\LegacyMaintenanceController.php
```

预期：路由映射 14 项、控制器中文注释与多语言测试、PHP 语法全部通过。

### Task 3: 扩展已有维护入口核验证据

**Files:**
- Modify: `docs/audits/旧项目路由核验证据.json`
- Generate: `storage/app/audits/current-legacy-route-audit.json`
- Generate: `docs/audits/2026-07-29-current-legacy-route-audit.md`
- Generate: `storage/app/audits/旧项目模块逻辑迁移核验矩阵.json`
- Generate: `docs/audits/旧项目模块逻辑迁移核验矩阵.md`

- [ ] **Step 1: 将 6 条旧路由加入既有分组**

扩展 `front_disabled_maintenance_2026_07_26`，显式登记：

```text
POST test_export -> legacy_test_export -> LegacyMaintenanceController@testExport
GET test_serach/{id} -> legacy_test_search -> LegacyMaintenanceController@testSearch
GET test_order -> legacy_test_order -> LegacyMaintenanceController@testOrder
GET test_rights_sum -> legacy_test_rights_sum -> LegacyMaintenanceController@testRightsSum
GET trades_exp_zero -> legacy_trades_exp_zero -> LegacyMaintenanceController@tradesExpZero
GET whstest -> legacy_whs_test -> LegacyMaintenanceController@whsTest
```

`/test/withdraw` 不是项目1导出的旧路由方法，只进入运行时穷举测试，不伪造旧路由迁移证据。分组结论改为 23 条旧路由，自动化证据记录 24 条当前路由逐条验证和 1 条提供器完整性验证。

- [ ] **Step 2: 重建路由审计和迁移矩阵**

```powershell
php artisan legacy-routes:audit storage/app/audits/legacy-routes.json --scope=all --policy=docs/audits/legacy-route-method-policy.json --json=storage/app/audits/current-legacy-route-audit.json --markdown=docs/audits/2026-07-29-current-legacy-route-audit.md
php scripts/generate-legacy-implementation-matrix.php
```

预期：395 条旧路由仍为 375 matched、20 intentional method restrictions、0 gaps；本批 6 条由 `needs_manual_business_review` 变为 `verified`，未匹配和旧源码缺失保持 0。

- [ ] **Step 3: 独立审查与最终验证**

审查旧行为证据、失败关闭理由、路由穷举算法和证据字段；然后重新运行目标测试、矩阵单测和 JSON 解析。任何 Critical/Important 问题必须先修复，才能进入下一模块。

**环境说明：** 当前工作区没有可用 Git 元数据，因此每一步以 RED/GREEN、语法、路由审计和矩阵重新生成为检查点，不执行提交命令。
