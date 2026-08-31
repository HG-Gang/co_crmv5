# Legacy Route Inventory Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 从旧 Laravel 5 项目导出框架实际注册路由，与当前 Laravel 8 路由按 URI、HTTP 方法和处理器建立可重复的差异审计，为普通用户、代理商、后台管理员逐路由闭环提供权威输入。

**Architecture:** 使用独立 PHP 7.4 进程启动旧项目并导出 JSON，避免新旧 Laravel 类冲突；新项目内用纯 PHP Support 类比较旧路由和当前路由，Artisan 命令输出 JSON 与 Markdown 审计结果。第一轮只建立事实清单，不自动修改业务路由。

**Tech Stack:** PHP 7.4、PHP 8.1、Laravel 5.1、Laravel 8.83、PHPUnit 9、PowerShell。

**Repository note:** 当前 `.git` 目录没有 `HEAD`，无法执行提交步骤；每个任务以目标测试、语法检查和生成物校验作为检查点。

---

### Task 1: Route comparison core

**Files:**
- Create: `app/Support/LegacyRouteInventory.php`
- Create: `tests/Unit/LegacyRouteInventoryTest.php`

- [ ] **Step 1: Write the failing unit test**

测试必须覆盖：忽略隐式 `HEAD`；旧 `Route::any` 的六种业务方法；同 URI 方法缺失；URI 缺失；匹配时保留新旧 action。

```php
<?php

namespace Tests\Unit;

use App\Support\LegacyRouteInventory;
use PHPUnit\Framework\TestCase;

class LegacyRouteInventoryTest extends TestCase
{
    public function test_compare_reports_matched_missing_uri_and_missing_methods(): void
    {
        $legacy = [
            ['methods' => ['GET', 'HEAD'], 'uri' => 'user/login', 'name' => 'login', 'action' => 'OldLogin@login'],
            ['methods' => ['POST'], 'uri' => 'user/signIn', 'name' => 'signIn', 'action' => 'OldLogin@signIn'],
            ['methods' => ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], 'uri' => 'user/deposit_request', 'name' => null, 'action' => 'OldDeposit@submit'],
            ['methods' => ['POST'], 'uri' => 'user/missing', 'name' => null, 'action' => 'OldMissing@store'],
        ];

        $current = [
            ['methods' => ['GET', 'HEAD'], 'uri' => 'user/login', 'name' => 'legacy_login', 'action' => 'NewLogin@show'],
            ['methods' => ['GET', 'HEAD'], 'uri' => 'user/signIn', 'name' => 'legacy_sign_in', 'action' => 'NewLogin@signIn'],
            ['methods' => ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], 'uri' => 'user/deposit_request', 'name' => 'legacy_deposit', 'action' => 'NewDeposit@submit'],
        ];

        $rows = (new LegacyRouteInventory())->compare($legacy, $current);

        $this->assertSame('matched', $rows[0]['status']);
        $this->assertSame('NewLogin@show', $rows[0]['current_action']);
        $this->assertSame('missing_methods', $rows[1]['status']);
        $this->assertSame(['POST'], $rows[1]['missing_methods']);
        $this->assertSame('matched', $rows[2]['status']);
        $this->assertSame('missing_uri', $rows[3]['status']);
    }
}
```

- [ ] **Step 2: Run the test and confirm RED**

Run:

```powershell
php vendor\phpunit\phpunit\phpunit tests\Unit\LegacyRouteInventoryTest.php --colors=never
```

Expected: failure because `App\Support\LegacyRouteInventory` does not exist.

- [ ] **Step 3: Implement the comparison class**

`compare()` indexes current routes by URI, removes implicit `HEAD`, compares required legacy methods, and returns one row per legacy route with `matched`, `missing_uri`, or `missing_methods`.

```php
<?php

namespace App\Support;

class LegacyRouteInventory
{
    public function compare(array $legacyRoutes, array $currentRoutes): array
    {
        $currentByUri = [];
        foreach ($currentRoutes as $route) {
            $currentByUri[$route['uri']][] = $route;
        }

        $rows = [];
        foreach ($legacyRoutes as $legacy) {
            $required = $this->normalizeMethods($legacy['methods'] ?? []);
            $candidates = $currentByUri[$legacy['uri']] ?? [];
            $matched = null;
            $bestMissing = $required;

            foreach ($candidates as $candidate) {
                $available = $this->normalizeMethods($candidate['methods'] ?? []);
                $missing = array_values(array_diff($required, $available));
                if ($missing === []) {
                    $matched = $candidate;
                    $bestMissing = [];
                    break;
                }
                if (count($missing) < count($bestMissing)) {
                    $matched = $candidate;
                    $bestMissing = $missing;
                }
            }

            $status = $candidates === [] ? 'missing_uri' : ($bestMissing === [] ? 'matched' : 'missing_methods');
            $rows[] = [
                'legacy_methods' => $required,
                'legacy_uri' => $legacy['uri'],
                'legacy_name' => $legacy['name'] ?? null,
                'legacy_action' => $legacy['action'] ?? null,
                'status' => $status,
                'missing_methods' => $bestMissing,
                'current_methods' => $matched ? $this->normalizeMethods($matched['methods'] ?? []) : [],
                'current_name' => $matched['name'] ?? null,
                'current_action' => $matched['action'] ?? null,
            ];
        }

        return $rows;
    }

    private function normalizeMethods(array $methods): array
    {
        $methods = array_values(array_unique(array_filter(array_map('strtoupper', $methods), function ($method) {
            return $method !== 'HEAD';
        })));
        sort($methods);
        return $methods;
    }
}
```

- [ ] **Step 4: Run the test and confirm GREEN**

Run the same PHPUnit command. Expected: one passing test.

### Task 2: Old Laravel route exporter

**Files:**
- Create: `scripts/export-legacy-routes.php`

- [ ] **Step 1: Add the standalone exporter**

The script accepts `--root` and `--output`, boots the old application without invoking `route:list`, sorts routes deterministically, and writes UTF-8 JSON.

```php
<?php

$options = getopt('', ['root:', 'output:']);
$root = isset($options['root']) ? rtrim($options['root'], "\\/") : '';
$output = $options['output'] ?? '';

if ($root === '' || $output === '' || ! is_file($root . '/bootstrap/autoload.php')) {
    fwrite(STDERR, "Usage: php export-legacy-routes.php --root=<legacy-root> --output=<json-file>\n");
    exit(2);
}

$originalDirectory = getcwd();
chdir($root);
require $root . '/bootstrap/autoload.php';
$app = require $root . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\\Contracts\\Console\\Kernel');
$kernel->bootstrap();

$routes = [];
foreach ($app['router']->getRoutes() as $route) {
    $routes[] = [
        'methods' => array_values($route->methods()),
        'uri' => $route->uri(),
        'name' => $route->getName(),
        'action' => $route->getActionName(),
    ];
}

usort($routes, function (array $left, array $right): int {
    return [$left['uri'], implode(',', $left['methods']), $left['action']]
        <=> [$right['uri'], implode(',', $right['methods']), $right['action']];
});

$directory = dirname($output);
if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
    fwrite(STDERR, "Cannot create output directory: {$directory}\n");
    exit(3);
}

$json = json_encode($routes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($json === false || file_put_contents($output, $json . PHP_EOL) === false) {
    fwrite(STDERR, "Cannot write route inventory: {$output}\n");
    exit(4);
}

chdir($originalDirectory);
fwrite(STDOUT, 'Exported ' . count($routes) . " routes to {$output}\n");
```

- [ ] **Step 2: Validate syntax with both runtimes**

```powershell
& 'D:\Ruanjian\phpStudy_64\phpstudy_pro\Extensions\php\php7.4.3nts\php.exe' -l scripts\export-legacy-routes.php
php -l scripts\export-legacy-routes.php
```

Expected: both commands report no syntax errors.

- [ ] **Step 3: Export the authoritative old route inventory**

```powershell
& 'D:\Ruanjian\phpStudy_64\phpstudy_pro\Extensions\php\php7.4.3nts\php.exe' scripts\export-legacy-routes.php --root='D:\Php-project\Php\new_co_gmtk_crmV3' --output='D:\Software\PhpProject\Demo\co_crmv5\storage\app\audits\legacy-routes.json'
```

Expected: successful message with a non-zero route count and valid JSON output.

### Task 3: Artisan audit command and reports

**Files:**
- Create: `app/Console/Commands/AuditLegacyRoutes.php`
- Create: `tests/Feature/LegacyRouteAuditCommandTest.php`

- [ ] **Step 1: Write the failing command test**

The test writes a two-route fixture, runs the command, expects exit code `1` for one missing route, and asserts JSON/Markdown contain both rows.

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LegacyRouteAuditCommandTest extends TestCase
{
    public function test_command_writes_json_and_markdown_and_fails_when_routes_are_missing(): void
    {
        Route::get('/audit-present', function () { return 'ok'; })->name('audit_present');

        $legacyFile = storage_path('app/audits/test-legacy-routes.json');
        $jsonFile = storage_path('app/audits/test-route-audit.json');
        $markdownFile = storage_path('app/audits/test-route-audit.md');
        if (! is_dir(dirname($legacyFile))) {
            mkdir(dirname($legacyFile), 0777, true);
        }

        file_put_contents($legacyFile, json_encode([
            ['methods' => ['GET', 'HEAD'], 'uri' => 'audit-present', 'name' => null, 'action' => 'Old@present'],
            ['methods' => ['POST'], 'uri' => 'audit-missing', 'name' => null, 'action' => 'Old@missing'],
        ]));

        $exitCode = Artisan::call('legacy-routes:audit', [
            'legacy' => $legacyFile,
            '--json' => $jsonFile,
            '--markdown' => $markdownFile,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('audit-present', file_get_contents($jsonFile));
        $this->assertStringContainsString('audit-missing', file_get_contents($markdownFile));
    }
}
```

- [ ] **Step 2: Run the test and confirm RED**

```powershell
php vendor\phpunit\phpunit\phpunit tests\Feature\LegacyRouteAuditCommandTest.php --colors=never
```

Expected: command is not defined.

- [ ] **Step 3: Implement the command**

The command signature is:

```php
protected $signature = 'legacy-routes:audit
    {legacy : Legacy route JSON path}
    {--scope=all : all, front, or admin}
    {--json= : JSON output path}
    {--markdown= : Markdown output path}';
```

Implementation requirements:

- Read and validate the JSON array.
- Convert current `Route::getRoutes()` entries to the same four fields.
- Filter `front` to old URIs beginning with `user/`, `agents/`, `en/user/`, plus `show/user_detail/`, `open/order_detail/`, `close/order_detail/` and legacy front maintenance/test routes.
- Filter `admin` to old URIs beginning with `index/`.
- Call `LegacyRouteInventory::compare()`.
- Write JSON rows and a Markdown table with legacy method, legacy URI, legacy action, status, missing methods, current name, and current action.
- Return `1` if any row is not `matched`, otherwise `0`.

- [ ] **Step 4: Run the test and confirm GREEN**

Run the same command test. Expected: one passing test.

### Task 4: Generate the first authoritative front gap list

**Files:**
- Create: `docs/audits/2026-07-11-front-legacy-route-inventory.md`
- Generate: `storage/app/audits/front-legacy-route-inventory.json`

- [ ] **Step 1: Run the front audit**

```powershell
php artisan legacy-routes:audit storage\app\audits\legacy-routes.json --scope=front --json=storage\app\audits\front-legacy-route-inventory.json --markdown=docs\audits\2026-07-11-front-legacy-route-inventory.md
```

Expected: the command returns `0` only if every old front URI and HTTP method has a current mapping; otherwise it returns `1` and the generated files list every exact gap.

- [ ] **Step 2: Validate report completeness**

```powershell
php -r "$r=json_decode(file_get_contents('storage/app/audits/front-legacy-route-inventory.json'), true); if (!is_array($r) || count($r)===0) { exit(1); } echo count($r), PHP_EOL;"
rg -n "missing_uri|missing_methods" storage\app\audits\front-legacy-route-inventory.json
```

Expected: non-zero route count; the second command is the exact ordinary/agent front route registration gap list.

### Task 5: Baseline regression checkpoint

**Files:**
- Verify: `tests/Unit/LegacyRouteInventoryTest.php`
- Verify: `tests/Feature/LegacyRouteAuditCommandTest.php`
- Verify: `tests/Feature/FrontLegacyRouteCompatibilityTest.php`
- Verify: `tests/Feature/FrontendRouteManifestTest.php`

- [ ] **Step 1: Run focused route tests**

```powershell
php -d memory_limit=1G vendor\phpunit\phpunit\phpunit tests\Unit\LegacyRouteInventoryTest.php tests\Feature\LegacyRouteAuditCommandTest.php tests\Feature\FrontLegacyRouteCompatibilityTest.php tests\Feature\FrontendRouteManifestTest.php --colors=never
```

Expected: all tests pass. Any existing regression is fixed before writing the next gap-closure plan.

- [ ] **Step 2: Record the next-plan inputs**

The next implementation plan uses only rows marked `missing_uri` or `missing_methods`, plus mapped routes whose Controller/Blade/JavaScript execution chain lacks ownership, permission, status, transaction, or error-path tests. It must name each concrete route and file discovered by this audit.

### Task 4.5: Explicitly approve secure HTTP method restrictions

**Files:**
- Modify: `app/Support/LegacyRouteInventory.php`
- Modify: `app/Console/Commands/AuditLegacyRoutes.php`
- Modify: `tests/Unit/LegacyRouteInventoryTest.php`
- Create: `docs/audits/legacy-route-method-policy.json`

- [ ] **Step 1: Add a failing policy test**

Add an explicit policy for `user/deposit_request` whose accepted current methods are `GET` and `POST`. Assert that the row status becomes `intentional_method_restriction`, retains missing `DELETE/PATCH/PUT`, and records a non-empty reason. A route without policy must remain `missing_methods`.

- [ ] **Step 2: Run the unit test and confirm RED**

```powershell
php vendor\phpunit\phpunit\phpunit tests\Unit\LegacyRouteInventoryTest.php --colors=never
```

Expected: failure because `compare()` does not accept or apply method policies.

- [ ] **Step 3: Implement explicit policy support**

Extend `LegacyRouteInventory::compare()` with an optional URI-keyed policy array. A policy is valid only when `accepted_current_methods` exactly equals the normalized current method set and `reason` is non-empty. Invalid or absent policies leave the row as `missing_methods`.

Add `--policy=` to `legacy-routes:audit`. Load the policy JSON and treat `matched` plus `intentional_method_restriction` as resolved statuses. Include `decision_reason` in JSON and Markdown.

- [ ] **Step 4: Record the 20 explicit old-any restrictions**

Create `docs/audits/legacy-route-method-policy.json` with one entry for each reported payment callback, payment return, legacy deposit submission and OTC withdrawal callback URI. Each entry accepts only `GET` and `POST` and states that old `Route::any` exposed unused mutating verbs while current Blade/payment integrations use GET/POST.

- [ ] **Step 5: Re-run the front audit with the policy**

```powershell
php artisan legacy-routes:audit storage\app\audits\legacy-routes.json --scope=front --policy=docs\audits\legacy-route-method-policy.json --json=storage\app\audits\front-legacy-route-inventory.json --markdown=docs\audits\2026-07-11-front-legacy-route-inventory.md
```

Expected: 183 routes resolved, 20 recorded as intentional method restrictions, zero unresolved gaps, exit code `0`.
