<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 15:08
 */

/**
 * legacy-routes:audit 命令(LegacyRouteAuditCommandTest)的集成测试。
 *
 * 文件功能:
 * - 验证 AuditLegacyRoutes 命令能读取旧版路由清单 JSON,与当前 Laravel 注册路由比对后,
 *   同时写出 JSON 与 Markdown 两份审计报告;存在旧路由在当前框架缺失时退出码为 1。
 * - 验证 --policy 选项能按 URI 施加显式方法限制策略(intentional_method_restriction),
 *   拒绝旧版遗留的未使用变更类方法(GET/HEAD 之外的多余动词)。
 *
 * 适用场景:重构或迁移路由清单后回归,确保新增/删除路由都会被审计工具如实反映,
 * 且方法限制策略不会误伤仍在使用的路由。
 *
 * 入参例子:--legacy 传入旧路由清单 JSON(元素含 methods/uri/name/action),
 * --json 与 --markdown 指定两份报告输出路径,--policy 传入按 URI 配置的策略 JSON。
 *
 * 返回值:命令退出码 0 表示审计通过、1 表示存在缺失路由;断言通过即表示
 * 报告内容与退出码闭环正确。
 *
 * 失败场景:断言失败说明审计命令的退出码、报告内容或方法限制策略解析与预期不符,
 * 需先检查命令实现,再检查测试夹具中的路由清单是否过期。
 */

namespace Tests\Feature;

use App\Console\Commands\AuditLegacyRoutes;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LegacyRouteAuditCommandTest extends TestCase
{
    public function test_command_writes_json_and_markdown_and_fails_when_routes_are_missing(): void
    {
        $this->assertTrue(class_exists(AuditLegacyRoutes::class), 'Legacy route audit command is missing.');

        Route::get('/audit-present', function () {
            return 'ok';
        })->name('audit_present');

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
        $this->assertStringContainsString('audit-present', (string) file_get_contents($jsonFile));
        $this->assertStringContainsString('audit-missing', (string) file_get_contents($markdownFile));

        foreach ([$legacyFile, $jsonFile, $markdownFile] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function test_command_resolves_explicit_method_restriction_policy(): void
    {
        $command = Artisan::all()['legacy-routes:audit'];
        $this->assertTrue($command->getDefinition()->hasOption('policy'), 'Method policy option is missing.');

        Route::match(['get', 'post'], '/audit-restricted', function () {
            return 'ok';
        })->name('audit_restricted');

        $directory = storage_path('app/audits');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $legacyFile = $directory . '/test-restricted-legacy-routes.json';
        $policyFile = $directory . '/test-restricted-route-policy.json';
        $jsonFile = $directory . '/test-restricted-route-audit.json';
        $markdownFile = $directory . '/test-restricted-route-audit.md';
        file_put_contents($legacyFile, json_encode([
            ['methods' => ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], 'uri' => 'audit-restricted', 'name' => null, 'action' => 'Old@restricted'],
        ]));
        file_put_contents($policyFile, json_encode([
            'audit-restricted' => [
                'accepted_current_methods' => ['GET', 'POST'],
                'reason' => 'Unused legacy mutating verbs are intentionally rejected.',
            ],
        ]));

        $exitCode = Artisan::call('legacy-routes:audit', [
            'legacy' => $legacyFile,
            '--policy' => $policyFile,
            '--json' => $jsonFile,
            '--markdown' => $markdownFile,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('intentional_method_restriction', (string) file_get_contents($jsonFile));
        $this->assertStringContainsString('Unused legacy mutating verbs', (string) file_get_contents($markdownFile));

        foreach ([$legacyFile, $policyFile, $jsonFile, $markdownFile] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
