<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 02:09
 */

/**
 * RouteClosureAuditScriptTest
 *
 * 文件功能：
 * - 验证路由闭环审计脚本：使用当前 Laravel 路由而非 route list 文件、旧项目根目录缺失或必需路由文件缺失时拒绝执行。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class RouteClosureAuditScriptTest extends TestCase
{
    public function test_audit_uses_the_current_laravel_routes_without_a_route_list_file(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runAudit();

        $this->assertSame(0, $exitCode, $stderr ?: $stdout);
        $this->assertSame('', trim($stderr));
        $this->assertMatchesRegularExpression('/旧路由总数: [1-9][0-9]*/u', $stdout);
        $this->assertMatchesRegularExpression('/新路由总数: [1-9][0-9]*/u', $stdout);
        $this->assertFileDoesNotExist($this->projectRoot() . DIRECTORY_SEPARATOR . 'route_list.json');
    }

    public function test_audit_rejects_a_missing_legacy_project_root(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runAudit([
            'LEGACY_PROJECT_ROOT' => sys_get_temp_dir()
                . DIRECTORY_SEPARATOR
                . 'crm-missing-audit-root-' . bin2hex(random_bytes(8)),
        ]);

        $this->assertNotSame(0, $exitCode, $stdout . $stderr);
        $this->assertStringContainsString('旧项目目录不存在', $stdout . $stderr);
    }

    public function test_audit_rejects_a_legacy_root_missing_a_required_route_file(): void
    {
        $legacyRoot = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'crm-incomplete-audit-root-' . bin2hex(random_bytes(8));
        $routeDirectory = $legacyRoot . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Http';
        $this->assertTrue(mkdir($routeDirectory, 0777, true));

        $existingFiles = ['routes.php', 'routes-admin.php'];
        foreach ($existingFiles as $file) {
            $this->assertNotFalse(file_put_contents($routeDirectory . DIRECTORY_SEPARATOR . $file, '<?php'));
        }

        try {
            [$exitCode, $stdout, $stderr] = $this->runAudit([
                'LEGACY_PROJECT_ROOT' => $legacyRoot,
            ]);

            $this->assertNotSame(0, $exitCode, $stdout . $stderr);
            $this->assertStringContainsString('旧项目关键路由不存在', $stdout . $stderr);
            $this->assertStringContainsString('admin.php', $stdout . $stderr);
        } finally {
            foreach ($existingFiles as $file) {
                @unlink($routeDirectory . DIRECTORY_SEPARATOR . $file);
            }
            @rmdir($routeDirectory);
            @rmdir(dirname($routeDirectory));
            @rmdir($legacyRoot);
        }
    }

    /** @param array<string, string> $environmentOverrides */
    private function runAudit(array $environmentOverrides = []): array
    {
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, $this->projectRoot() . DIRECTORY_SEPARATOR . 'scripts'
                . DIRECTORY_SEPARATOR . '_route_closure_audit.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->projectRoot(),
            array_merge(getenv(), $environmentOverrides)
        );
        $this->assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }

    private function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }
}
