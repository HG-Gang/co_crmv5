<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/07
 * Time: 12:02
 */

/**
 * CurrentProjectSurfaceInventoryTest
 *
 * 文件功能：
 * - 验证当前项目界面清单工具：盘点全部 surface 并对每个 Blade 家族分类、CLI 输出 JSON 与 Markdown、拒绝允许根目录之外的 Blade。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

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
