# Phase 0 Safety and Parity Baseline Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 在不连接或修改正式数据库的前提下，建立唯一安全测试库准备入口、刷新旧项目证据，并生成覆盖新项目全部代码与 Blade 页面族的可重复清单。

**Architecture:** 复用现有 `LegacySourceInventory`、`LegacyRouteInventory` 和 `LegacyImplementationMatrix`，只补齐测试库脚本单一来源、正确旧项目默认路径和当前项目页面/资产清单。文件清单与矩阵 CLI 不引导 Laravel；必须读取当前注册路由的审计命令使用强制测试身份。本阶段不创建数据库、不执行 migration、不运行数据库 Feature 测试。

**Tech Stack:** PHP 7.4+/Laravel 8.83、PHPUnit 9.5、PowerShell、JSON、Markdown、SHA-256。

---

## 前置资料

- 设计规格：`docs/superpowers/specs/2026-08-07-full-legacy-parity-and-visual-c-design.md`
- 总路线图：`docs/superpowers/plans/2026-08-07-full-legacy-parity-and-visual-c-roadmap.md`
- 旧项目根目录：`D:\Software\PhpProject\Demo\new_co_gmtk_crmv3`
- 新项目根目录：`D:\Software\PhpProject\Demo\co_crmv5`

## 文件结构

### 现有文件

- `tests/Support/TestDatabaseGuard.php`：PHPUnit 启动前的测试库和 MT4 门禁，保持现有公开接口。
- `tests/Unit/TestDatabaseGuardTest.php`：纯值安全边界测试，不连接数据库。
- `scripts/prepare-test-database.php`：唯一保留的隔离测试库准备入口。
- `scripts/provision_test_db.php`：重复且包含凭据默认值的旧入口，本阶段删除。
- `tests/Unit/TestRunnerContractTest.php`：测试库准备器和 PowerShell 运行器静态契约。
- `app/Support/LegacySourceInventory.php`：旧项目 Controller/Blade/JS 静态扫描器。
- `scripts/generate-legacy-source-inventory.php`：旧源码清单 CLI。
- `app/Support/LegacyRouteInventory.php`、`app/Console/Commands/AuditLegacyRoutes.php`：旧新路由审计。
- `app/Support/LegacyImplementationMatrix.php`、`scripts/generate-legacy-implementation-matrix.php`：旧路由业务核验矩阵。

### 新增文件

- `app/Support/CurrentProjectSurfaceInventory.php`：扫描当前项目 Controller、路由、Blade、JS、CSS、migration 和测试文件，计算 SHA-256 并归类 UI 页面族。
- `tests/Unit/CurrentProjectSurfaceInventoryTest.php`：使用临时目录验证清单、页面族、模块、资产归属、哈希和 CLI。
- `scripts/generate-current-project-surface-inventory.php`：输出当前项目清单 JSON 与 Markdown。
- `storage/app/audits/2026-08-07-current-project-surface-inventory.json`：机器可读生成物。
- `docs/audits/2026-08-07-current-project-surface-inventory.md`：人工审阅生成物。

当前目录没有 Git 仓库。每个任务末尾记录文件 SHA-256 和测试输出，不执行 `git add` 或 `git commit`。

### Task 1: 收敛测试库准备入口并锁定本地实例

**Files:**
- Modify: `tests/Unit/TestRunnerContractTest.php`
- Modify: `scripts/prepare-test-database.php`
- Delete: `scripts/provision_test_db.php`
- Test: `tests/Unit/TestRunnerContractTest.php`

- [ ] **Step 1: 写入重复入口和实例边界失败测试**

在 `TestRunnerContractTest` 的公开测试方法区域加入：

```php
public function test_database_preparer_is_locked_to_the_confirmed_local_instance(): void
{
    $source = $this->source('scripts/prepare-test-database.php');

    $this->assertStringContainsString('$host !== \'127.0.0.1\'', $source);
    $this->assertStringContainsString('$port !== \'3307\'', $source);
    $this->assertStringContainsString("'DB_PORT', '3307'", $source);
}

public function test_obsolete_database_provisioner_is_removed(): void
{
    $this->assertFileDoesNotExist(
        dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'provision_test_db.php'
    );
}
```

- [ ] **Step 2: 运行测试并确认按预期失败**

Run:

```powershell
vendor\bin\phpunit --colors=never --filter TestRunnerContractTest
```

Expected: FAIL。失败至少包含 `provision_test_db.php` 仍存在，且准备器尚无精确主机/端口拒绝分支。

- [ ] **Step 3: 锁定准备器主机和端口**

把 `scripts/prepare-test-database.php` 的端口默认值改为 `3307`，并在读取连接参数后、创建 PDO 前加入：

```php
$host = trim($readEnvironment('DB_HOST', '127.0.0.1'));
$port = trim($readEnvironment('DB_PORT', '3307'));
$username = $readEnvironment('DB_USERNAME', 'root');
$password = $readEnvironment('DB_PASSWORD', '');

if ($host !== '127.0.0.1' || $port !== '3307') {
    throw new RuntimeException('Unexpected test database server identity.');
}
```

保留现有空主机、用户名、端口范围、数据库 allowlist 和不输出 PDO 异常的检查。

- [ ] **Step 4: 删除重复准备器**

使用补丁删除整个文件：

```text
*** Delete File: scripts/provision_test_db.php
```

该文件没有运行时引用，且与 `prepare-test-database.php` 重复；删除后凭据默认值不再存在第二来源。

- [ ] **Step 5: 运行目标测试并确认通过**

Run:

```powershell
vendor\bin\phpunit --colors=never --filter "TestRunnerContractTest|TestDatabaseGuardTest"
```

Expected: `OK`，零 Failure、零 Error。命令不得建立数据库连接，因为两个测试均为静态/纯值测试。

- [ ] **Step 6: 记录任务检查点**

Run:

```powershell
Get-FileHash -Algorithm SHA256 tests\Unit\TestRunnerContractTest.php,scripts\prepare-test-database.php
```

Expected: 两个文件各输出一个 64 位 SHA-256；`Test-Path scripts\provision_test_db.php` 返回 `False`。

### Task 2: 修正旧项目路径并把静态审计 CLI 收敛为纯文件工具

**Files:**
- Modify: `tests/Unit/LegacySourceInventoryTest.php`
- Modify: `tests/Unit/LegacyImplementationMatrixTest.php`
- Modify: `scripts/generate-legacy-source-inventory.php`
- Modify: `scripts/generate-legacy-implementation-matrix.php`
- Test: `tests/Unit/LegacySourceInventoryTest.php`
- Test: `tests/Unit/LegacyImplementationMatrixTest.php`

- [ ] **Step 1: 写入默认路径与只读契约失败测试**

在 `LegacySourceInventoryTest` 中加入：

```php
public function test_cli_defaults_to_the_confirmed_legacy_workspace_and_remains_static_only(): void
{
    $source = (string) file_get_contents(base_path('scripts/generate-legacy-source-inventory.php'));

    $confirmedDefault = <<<'PATH'
D:\\Software\\PhpProject\\Demo\\new_co_gmtk_crmv3
PATH;
    $obsoleteDefault = <<<'PATH'
D:\\Php-project\\Php\\new_co_gmtk_crmV3
PATH;

    $this->assertStringContainsString($confirmedDefault, $source);
    $this->assertStringNotContainsString($obsoleteDefault, $source);
    $this->assertStringNotContainsString('new PDO(', $source);
    $this->assertStringNotContainsString('DB::', $source);
    $this->assertStringNotContainsString('bootstrap/app.php', $source);
    $this->assertStringNotContainsString('Kernel::class', $source);
    $this->assertStringContainsString('new LegacySourceInventory()', $source);
    $this->assertStringContainsString('仅静态读取旧项目 PHP 与 Blade 源码', $source);
}
```

在 `LegacyImplementationMatrixTest` 中加入：

```php
public function test_matrix_cli_is_a_filesystem_only_tool(): void
{
    $source = (string) file_get_contents(base_path('scripts/generate-legacy-implementation-matrix.php'));

    $this->assertStringNotContainsString('bootstrap/app.php', $source);
    $this->assertStringNotContainsString('Kernel::class', $source);
    $this->assertStringNotContainsString('app(LegacyImplementationMatrix::class)', $source);
    $this->assertStringContainsString('new LegacyImplementationMatrix()', $source);
}
```

- [ ] **Step 2: 运行测试并确认路径断言失败**

Run:

```powershell
vendor\bin\phpunit --colors=never --filter "LegacySourceInventoryTest|LegacyImplementationMatrixTest"
```

Expected: FAIL，提示缺少已确认的旧项目路径，并显示两个 CLI 仍引导 Laravel。

- [ ] **Step 3: 修改 CLI 默认路径**

把 `scripts/generate-legacy-source-inventory.php` 的引导和路径初始化替换为：

```php
require dirname(__DIR__) . '/vendor/autoload.php';

$projectRoot = dirname(__DIR__);
$options = getopt('', ['legacy-root:', 'json:', 'markdown:', 'project-name::']);
$legacyRoot = rtrim(
    (string) ($options['legacy-root'] ?? 'D:\\Software\\PhpProject\\Demo\\new_co_gmtk_crmv3'),
    "\\/"
);
$jsonPath = resolveOutputPath(
    (string) ($options['json'] ?? 'storage/app/audits/旧项目源码逻辑清单.json'),
    $projectRoot
);
$markdownPath = resolveOutputPath(
    (string) ($options['markdown'] ?? 'docs/audits/旧项目源码逻辑清单.md'),
    $projectRoot
);
$projectName = (string) ($options['project-name'] ?? '项目1旧项目');
```

删除 `Illuminate\Contracts\Console\Kernel`、`bootstrap/app.php` 和 Kernel bootstrap。把扫描器创建改为：

```php
$scanner = new LegacySourceInventory();
```

把输出路径函数改为不依赖 Laravel helper：

```php
function resolveOutputPath(string $path, string $projectRoot): string
{
    if (preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $path)) {
        return $path;
    }

    return $projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}
```

同时更新文件头默认路径说明。不得加入数据库连接、旧应用写操作或自动执行旧代码。

- [ ] **Step 4: 把矩阵 CLI 改为纯文件工具**

在 `scripts/generate-legacy-implementation-matrix.php` 中删除 `Illuminate\Contracts\Console\Kernel`、`bootstrap/app.php` 和 Kernel bootstrap，并使用：

```php
$projectRoot = dirname(__DIR__);
require $projectRoot . '/vendor/autoload.php';

$builder = new LegacyImplementationMatrix();
$matrix = $builder->build(
    readJsonArray($legacyRoutesPath),
    readJsonArray($routeAuditPath),
    readJsonArray($sourceInventoryPath),
    readJsonArray($verificationEvidencePath)
);
```

把全部 `resolvePath(...)` 调用增加 `$projectRoot` 参数，把 Markdown 输出改为 `$builder->toMarkdown($matrix)`，并将路径函数改为：

```php
function resolvePath(string $path, string $projectRoot): string
{
    if (preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $path)) {
        return $path;
    }

    return $projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}
```

- [ ] **Step 5: 运行目标测试并确认通过**

Run:

```powershell
vendor\bin\phpunit --colors=never --filter "LegacySourceInventoryTest|LegacyImplementationMatrixTest"
```

Expected: `OK`，现有提取、Markdown 和 CLI 夹具测试全部保持通过。

- [ ] **Step 6: 记录任务检查点**

Run:

```powershell
Get-FileHash -Algorithm SHA256 tests\Unit\LegacySourceInventoryTest.php,tests\Unit\LegacyImplementationMatrixTest.php,scripts\generate-legacy-source-inventory.php,scripts\generate-legacy-implementation-matrix.php
```

Expected: 四个文件各输出一个 SHA-256。

### Task 3: 建立当前项目全量表面清单

**Files:**
- Create: `app/Support/CurrentProjectSurfaceInventory.php`
- Create: `tests/Unit/CurrentProjectSurfaceInventoryTest.php`
- Create: `scripts/generate-current-project-surface-inventory.php`
- Test: `tests/Unit/CurrentProjectSurfaceInventoryTest.php`

- [ ] **Step 1: 写入当前项目清单失败测试**

创建 `tests/Unit/CurrentProjectSurfaceInventoryTest.php`：

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\CurrentProjectSurfaceInventory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CurrentProjectSurfaceInventoryTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'current-surface-' . uniqid('', true);

        foreach ([
            'app/Http/Controllers/Admin',
            'routes',
            'resources/admin/layui/users',
            'resources/admin/crmui/users',
            'resources/front/layui/account',
            'resources/front/crmui/account',
            'resources/views/emails',
            'public/js/apps/front',
            'public/js/vendor/example',
            'public/css/admin',
            'database/migrations',
            'tests/Feature',
        ] as $directory) {
            mkdir($this->root . '/' . $directory, 0777, true);
        }

        $this->write('app/Http/Controllers/Admin/UserController.php', '<?php class UserController {}');
        $this->write('routes/web.php', '<?php');
        $this->write('resources/admin/layui/users/index.blade.php', '<main>layui admin</main>');
        $this->write('resources/admin/crmui/users/index.blade.php', '<main>crmui admin</main>');
        $this->write('resources/front/layui/account/index.blade.php', '<main>layui front</main>');
        $this->write('resources/front/crmui/account/index.blade.php', '<main>crmui front</main>');
        $this->write('resources/views/emails/notice.blade.php', '<p>mail</p>');
        $this->write('public/js/apps/front/account.js', 'window.account = {};');
        $this->write('public/js/vendor/example/vendor.js', 'window.vendor = {};');
        $this->write('public/css/admin/style.css', 'body { color: black; }');
        $this->write('database/migrations/2026_01_01_000000_create_demo.php', '<?php');
        $this->write('tests/Feature/DemoTest.php', '<?php');
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
        parent::tearDown();
    }

    public function test_it_inventorys_all_surfaces_and_classifies_every_blade_family(): void
    {
        $inventory = (new CurrentProjectSurfaceInventory())->inspect($this->root);

        $this->assertSame(1, $inventory['summary']['controller_files']);
        $this->assertSame(1, $inventory['summary']['route_files']);
        $this->assertSame(5, $inventory['summary']['blade_files']);
        $this->assertSame(2, $inventory['summary']['javascript_files']);
        $this->assertSame(1, $inventory['summary']['stylesheet_files']);
        $this->assertSame(1, $inventory['summary']['migration_files']);
        $this->assertSame(1, $inventory['summary']['test_files']);
        $this->assertSame([
            'admin_crmui' => 1,
            'admin_layui' => 1,
            'front_crmui' => 1,
            'front_layui' => 1,
            'shared_views' => 1,
        ], $inventory['summary']['blade_families']);
        $this->assertSame(
            ['users', 'users', 'account', 'account', 'emails'],
            array_column($inventory['blades'], 'module')
        );
        $this->assertSame('first_party', $inventory['files']['javascript'][0]['ownership']);
        $this->assertSame('vendor', $inventory['files']['javascript'][1]['ownership']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $inventory['blades'][0]['sha256']);
    }

    public function test_cli_writes_json_and_markdown(): void
    {
        $projectRoot = dirname(__DIR__, 2);
        $json = $this->root . '/surface.json';
        $markdown = $this->root . '/surface.md';
        $command = sprintf(
            '%s %s --root=%s --json=%s --markdown=%s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($projectRoot . '/scripts/generate-current-project-surface-inventory.php'),
            escapeshellarg($this->root),
            escapeshellarg($json),
            escapeshellarg($markdown)
        );

        exec($command, $output, $exitCode);

        $this->assertSame(0, $exitCode, implode(PHP_EOL, $output));
        $this->assertFileExists($json);
        $this->assertFileExists($markdown);
        $this->assertSame(5, json_decode((string) file_get_contents($json), true)['summary']['blade_files']);
        $this->assertStringContainsString('Current Project Surface Inventory', (string) file_get_contents($markdown));
    }

    public function test_it_rejects_a_blade_outside_the_allowed_roots(): void
    {
        mkdir($this->root . '/resources/unclassified', 0777, true);
        $this->write('resources/unclassified/unknown.blade.php', '<main>unknown</main>');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unclassified Blade file');

        (new CurrentProjectSurfaceInventory())->inspect($this->root);
    }

    private function write(string $relativePath, string $contents): void
    {
        file_put_contents($this->root . '/' . $relativePath, $contents);
    }
}
```

- [ ] **Step 2: 运行新测试并确认类和 CLI 缺失**

Run:

```powershell
vendor\bin\phpunit --colors=never --filter CurrentProjectSurfaceInventoryTest
```

Expected: FAIL，提示 `CurrentProjectSurfaceInventory` 或生成脚本不存在。

- [ ] **Step 3: 实现当前项目清单服务**

创建 `app/Support/CurrentProjectSurfaceInventory.php`：

```php
<?php

declare(strict_types=1);

namespace App\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class CurrentProjectSurfaceInventory
{
    private const GROUPS = [
        'controllers' => ['app/Http/Controllers', '.php'],
        'routes' => ['routes', '.php'],
        'javascript' => ['public/js', '.js'],
        'stylesheets' => ['public/css', '.css'],
        'migrations' => ['database/migrations', '.php'],
        'tests' => ['tests', 'Test.php'],
    ];

    private const BLADE_ROOTS = [
        'resources/admin/layui' => 'admin_layui',
        'resources/admin/crmui' => 'admin_crmui',
        'resources/front/layui' => 'front_layui',
        'resources/front/crmui' => 'front_crmui',
        'resources/views' => 'shared_views',
    ];

    private const VENDOR_ASSET_PREFIXES = [
        'public/js/vendor/',
        'public/css/layui/',
        'public/css/naui/',
    ];

    public function inspect(string $root): array
    {
        $resolvedRoot = realpath($root);
        if ($resolvedRoot === false || ! is_dir($resolvedRoot)) {
            throw new RuntimeException('Current project root does not exist: ' . $root);
        }

        $files = [];
        foreach (self::GROUPS as $name => [$directory, $suffix]) {
            $files[$name] = $this->records($resolvedRoot, $directory, $suffix);
        }

        $blades = $this->records($resolvedRoot, 'resources', '.blade.php');
        foreach ($blades as &$record) {
            $matchedRoot = null;
            foreach (self::BLADE_ROOTS as $directory => $family) {
                if (strpos($record['path'], $directory . '/') !== 0) {
                    continue;
                }

                $matchedRoot = $directory;
                $record['family'] = $family;
                $record['module'] = $this->moduleFor($record['path'], $directory);
                break;
            }

            if ($matchedRoot === null) {
                throw new RuntimeException('Unclassified Blade file: ' . $record['path']);
            }
        }
        unset($record);
        usort($blades, static fn (array $left, array $right): int => $left['path'] <=> $right['path']);

        $families = array_count_values(array_column($blades, 'family'));
        ksort($families);

        return [
            'summary' => [
                'controller_files' => count($files['controllers']),
                'route_files' => count($files['routes']),
                'blade_files' => count($blades),
                'javascript_files' => count($files['javascript']),
                'stylesheet_files' => count($files['stylesheets']),
                'migration_files' => count($files['migrations']),
                'test_files' => count($files['tests']),
                'blade_families' => $families,
            ],
            'blades' => $blades,
            'files' => $files,
        ];
    }

    public function toMarkdown(array $inventory): string
    {
        $summary = $inventory['summary'] ?? [];
        $lines = [
            '# Current Project Surface Inventory',
            '',
            '- Controllers: ' . (int) ($summary['controller_files'] ?? 0),
            '- Route files: ' . (int) ($summary['route_files'] ?? 0),
            '- Blade files: ' . (int) ($summary['blade_files'] ?? 0),
            '- JavaScript files: ' . (int) ($summary['javascript_files'] ?? 0),
            '- Stylesheet files: ' . (int) ($summary['stylesheet_files'] ?? 0),
            '- Migrations: ' . (int) ($summary['migration_files'] ?? 0),
            '- Tests: ' . (int) ($summary['test_files'] ?? 0),
            '',
            '| Blade | Family | Module | SHA-256 |',
            '|---|---|---|---|',
        ];

        foreach ($inventory['blades'] ?? [] as $blade) {
            $lines[] = sprintf(
                '| `%s` | `%s` | `%s` | `%s` |',
                str_replace('|', '\\|', (string) $blade['path']),
                (string) $blade['family'],
                (string) $blade['module'],
                (string) $blade['sha256']
            );
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    private function records(string $root, string $relativeDirectory, string $suffix): array
    {
        $directory = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
        if (! is_dir($directory)) {
            throw new RuntimeException('Required inventory directory does not exist: ' . $relativeDirectory);
        }

        $records = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            $filename = $item->getFilename();
            if (! $item->isFile() || substr($filename, -strlen($suffix)) !== $suffix) {
                continue;
            }

            $path = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
            $hash = hash_file('sha256', $item->getPathname());
            if ($hash === false) {
                throw new RuntimeException('Unable to hash inventory file: ' . $path);
            }

            $records[] = [
                'path' => $path,
                'bytes' => $item->getSize(),
                'sha256' => $hash,
                'ownership' => $this->ownershipFor($path),
            ];
        }

        usort($records, static fn (array $left, array $right): int => $left['path'] <=> $right['path']);
        return $records;
    }

    private function moduleFor(string $path, string $bladeRoot): string
    {
        $relative = substr($path, strlen($bladeRoot) + 1);
        $separator = strpos($relative, '/');
        return $separator === false ? '_root' : substr($relative, 0, $separator);
    }

    private function ownershipFor(string $path): string
    {
        foreach (self::VENDOR_ASSET_PREFIXES as $prefix) {
            if (strpos($path, $prefix) === 0) {
                return 'vendor';
            }
        }

        return 'first_party';
    }
}
```

- [ ] **Step 4: 实现只读 CLI**

创建 `scripts/generate-current-project-surface-inventory.php`：

```php
<?php

declare(strict_types=1);

use App\Support\CurrentProjectSurfaceInventory;

$projectRoot = dirname(__DIR__);
require $projectRoot . '/vendor/autoload.php';

$options = getopt('', ['root::', 'json::', 'markdown::']);
$root = (string) ($options['root'] ?? $projectRoot);
$jsonPath = outputPath((string) ($options['json'] ?? 'storage/app/audits/2026-08-07-current-project-surface-inventory.json'), $projectRoot);
$markdownPath = outputPath((string) ($options['markdown'] ?? 'docs/audits/2026-08-07-current-project-surface-inventory.md'), $projectRoot);

try {
    $scanner = new CurrentProjectSurfaceInventory();
    $inventory = $scanner->inspect($root);
    $inventory['meta'] = [
        'schema_version' => 1,
        'generated_at' => date(DATE_ATOM),
        'root' => realpath($root),
        'evidence_boundary' => 'Read-only filesystem inventory; no application bootstrap and no database connection.',
    ];

    $json = json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('Unable to encode current project inventory JSON.');
    }

    writeOutput($jsonPath, $json . PHP_EOL);
    writeOutput($markdownPath, $scanner->toMarkdown($inventory));
    fwrite(STDOUT, sprintf(
        "Current inventory ready: controllers=%d routes=%d blades=%d js=%d css=%d migrations=%d tests=%d.%s",
        $inventory['summary']['controller_files'],
        $inventory['summary']['route_files'],
        $inventory['summary']['blade_files'],
        $inventory['summary']['javascript_files'],
        $inventory['summary']['stylesheet_files'],
        $inventory['summary']['migration_files'],
        $inventory['summary']['test_files'],
        PHP_EOL
    ));
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}

function outputPath(string $path, string $projectRoot): string
{
    if (preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $path)) {
        return $path;
    }
    return $projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function writeOutput(string $path, string $contents): void
{
    $directory = dirname($path);
    if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
        throw new RuntimeException('Unable to create inventory output directory: ' . $directory);
    }
    if (file_put_contents($path, $contents) === false) {
        throw new RuntimeException('Unable to write inventory output: ' . $path);
    }
}
```

- [ ] **Step 5: 运行测试并确认通过**

Run:

```powershell
vendor\bin\phpunit --colors=never --filter CurrentProjectSurfaceInventoryTest
```

Expected: `OK (3 tests, ...)`，零 Failure、零 Error。

- [ ] **Step 6: 语法检查并记录检查点**

Run:

```powershell
php -l app\Support\CurrentProjectSurfaceInventory.php
php -l scripts\generate-current-project-surface-inventory.php
php -l tests\Unit\CurrentProjectSurfaceInventoryTest.php
Get-FileHash -Algorithm SHA256 app\Support\CurrentProjectSurfaceInventory.php,scripts\generate-current-project-surface-inventory.php,tests\Unit\CurrentProjectSurfaceInventoryTest.php
```

Expected: 三次 `No syntax errors detected`，随后输出三个 SHA-256。

### Task 4: 重新生成旧新证据基线

**Files:**
- Regenerate: `storage/app/audits/旧项目源码逻辑清单.json`
- Regenerate: `docs/audits/旧项目源码逻辑清单.md`
- Regenerate: `storage/app/audits/current-legacy-route-audit.json`
- Regenerate: `docs/audits/2026-08-07-current-legacy-route-audit.md`
- Regenerate: `storage/app/audits/旧项目模块逻辑迁移核验矩阵.json`
- Regenerate: `docs/audits/旧项目模块逻辑迁移核验矩阵.md`
- Create: `storage/app/audits/2026-08-07-current-project-surface-inventory.json`
- Create: `docs/audits/2026-08-07-current-project-surface-inventory.md`

- [ ] **Step 1: 生成旧源码清单**

Run:

```powershell
php scripts\generate-legacy-source-inventory.php --legacy-root="D:\Software\PhpProject\Demo\new_co_gmtk_crmv3" --json="storage\app\audits\旧项目源码逻辑清单.json" --markdown="docs\audits\旧项目源码逻辑清单.md" --project-name="项目1旧项目"
```

Expected: exit 0，输出 `已生成旧项目源码逻辑清单`，只读取旧项目文件。

- [ ] **Step 2: 重新审计旧新路由**

Run:

```powershell
$env:APP_ENV = 'testing'
$env:DATABASE_URL = ''
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '3307'
$env:DB_SOCKET = ''
$env:DB_DATABASE = 'co_crmv5_test'
$env:MT4_ENABLED = 'false'
$env:MT4_USER_SYNC_ENABLED = 'false'
php artisan legacy-routes:audit storage\app\audits\legacy-routes.json --scope=all --policy=docs\audits\legacy-route-method-policy.json --json=storage\app\audits\current-legacy-route-audit.json --markdown=docs\audits\2026-08-07-current-legacy-route-audit.md
```

Expected: exit 0，摘要中的 gaps 为 0。测试库不存在也不影响纯路由加载；若任何服务提供者尝试查询数据库，命令必须失败而不能回退到正式库。若退出码为 1 或 2，停止本任务并保留输出，不能继续生成“已完成”矩阵。

- [ ] **Step 3: 重新生成业务核验矩阵**

Run:

```powershell
php scripts\generate-legacy-implementation-matrix.php --legacy-routes=storage\app\audits\legacy-routes.json --route-audit=storage\app\audits\current-legacy-route-audit.json --source-inventory=storage\app\audits\旧项目源码逻辑清单.json --verification-evidence=docs\audits\旧项目路由核验证据.json --json=storage\app\audits\旧项目模块逻辑迁移核验矩阵.json --markdown=docs\audits\旧项目模块逻辑迁移核验矩阵.md
```

Expected: exit 0。输出必须分别列出 verified、待人工业务核验、旧源码未解决和新路由未匹配数量，不允许把静态匹配自动提升为 verified。

- [ ] **Step 4: 生成当前项目全量表面清单**

Run:

```powershell
php scripts\generate-current-project-surface-inventory.php --root="D:\Software\PhpProject\Demo\co_crmv5" --json=storage\app\audits\2026-08-07-current-project-surface-inventory.json --markdown=docs\audits\2026-08-07-current-project-surface-inventory.md
```

Expected: exit 0，输出 controllers、routes、blades、js、css、migrations 和 tests 的正整数计数。

- [ ] **Step 5: 机器校验页面族和证据边界**

Run:

```powershell
$inventory = Get-Content -Raw -Encoding UTF8 storage\app\audits\2026-08-07-current-project-surface-inventory.json | ConvertFrom-Json
if ($inventory.summary.blade_files -ne $inventory.blades.Count) { throw 'Blade count mismatch' }
$allowed = @('admin_layui','admin_crmui','front_layui','front_crmui','shared_views')
$unknown = @($inventory.blades | Where-Object { $_.family -notin $allowed })
if ($unknown.Count -ne 0) { throw 'Unknown Blade family detected' }
Write-Output ("BLADE_FILES=" + $inventory.summary.blade_files)
Write-Output ("UNKNOWN_BLADE_FAMILIES=" + $unknown.Count)
```

Expected: `UNKNOWN_BLADE_FAMILIES=0`，Blade 文件数为正整数。

- [ ] **Step 6: 记录生成物检查点**

Run:

```powershell
Get-FileHash -Algorithm SHA256 storage\app\audits\旧项目源码逻辑清单.json,storage\app\audits\current-legacy-route-audit.json,storage\app\audits\旧项目模块逻辑迁移核验矩阵.json,storage\app\audits\2026-08-07-current-project-surface-inventory.json
```

Expected: 四个生成物均输出 SHA-256。

### Task 5: 阶段 0 总验证

**Files:**
- Verify: `tests/Unit/TestDatabaseGuardTest.php`
- Verify: `tests/Unit/TestRunnerContractTest.php`
- Verify: `tests/Unit/LegacySourceInventoryTest.php`
- Verify: `tests/Unit/LegacyRouteInventoryTest.php`
- Verify: `tests/Unit/LegacyImplementationMatrixTest.php`
- Verify: `tests/Unit/CurrentProjectSurfaceInventoryTest.php`

- [ ] **Step 1: 运行阶段 0 目标测试**

Run:

```powershell
vendor\bin\phpunit --colors=never --filter "TestDatabaseGuardTest|TestRunnerContractTest|LegacySourceInventoryTest|LegacyRouteInventoryTest|LegacyImplementationMatrixTest|CurrentProjectSurfaceInventoryTest"
```

Expected: `OK`，零 Failure、零 Error。该命令只运行 Unit/静态 CLI 测试，不连接业务数据库。

- [ ] **Step 2: 运行 Composer 和 PHP 语法校验**

Run:

```powershell
composer validate --no-check-publish
php -l tests\Unit\TestRunnerContractTest.php
php -l tests\Unit\LegacySourceInventoryTest.php
php -l app\Support\CurrentProjectSurfaceInventory.php
php -l tests\Unit\CurrentProjectSurfaceInventoryTest.php
php -l scripts\prepare-test-database.php
php -l scripts\generate-legacy-source-inventory.php
php -l scripts\generate-current-project-surface-inventory.php
```

Expected: Composer 配置有效；所有 PHP 文件均输出 `No syntax errors detected`。

- [ ] **Step 3: 证明正式库未被本阶段工具引用为写入目标**

Run:

```powershell
Select-String -Path scripts\prepare-test-database.php -Pattern 'co_crmv5|hank_zl_data'
```

Expected: 只匹配精确字符串 `co_crmv5_test`；不出现 `hank_zl_data` 或独立的 `co_crmv5` 写入目标。

- [ ] **Step 4: 写入阶段结果检查点**

在 `docs/audits/2026-08-07-phase-0-result.md` 记录实际命令、退出码、测试数量、生成物 SHA-256、旧路由 gap 数、矩阵四类状态数量和五个 Blade 页面族数量。所有值必须来自本轮输出；不得复制历史报告。

- [ ] **Step 5: 阶段完成判断**

只有以下条件同时满足才标记 Phase 0 完成：

```text
目标测试：零 Failure、零 Error
路由审计：gaps = 0
当前 Blade：全部属于五个允许页面族
旧库与新正式库：零写入
证据输出：四个 JSON/Markdown 组合均存在且有 SHA-256
阶段报告：只引用本轮运行结果
```

任一条件不满足时，阶段报告状态必须写为 `blocked`，并准确列出失败命令和恢复入口。
