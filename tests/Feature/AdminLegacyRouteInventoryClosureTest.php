<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:46
 */

/**
 * 文件功能：运行 legacy 路由审计命令（legacy-routes:audit）并断言管理端
 *           legacy 路由清单无未解决缺口。
 *
 * 适用场景：管理端 legacy 路由与新路由体系匹配度的整体门禁回归测试。
 *
 * 入参例子：
 * - artisan legacy-routes:audit --scope=admin --policy=... --json=... --markdown=...
 *
 * 返回值：
 * - 命令退出码为 0，审计 JSON 共 204 行，且不存在未匹配/非预期方法限制的行。
 *
 * 异常或失败场景：
 * - 存在 status 非 matched/intentional_method_restriction 的缺口行时断言失败。
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AdminLegacyRouteInventoryClosureTest extends TestCase
{
    // 管理端 legacy 路由清单应无未解决的缺口。
    public function test_admin_legacy_route_inventory_has_no_unresolved_gaps(): void
    {
        $jsonFile = storage_path('app/audits/test-admin-legacy-route-inventory.json');
        $markdownFile = storage_path('app/audits/test-admin-legacy-route-inventory.md');

        $exitCode = Artisan::call('legacy-routes:audit', [
            'legacy' => storage_path('app/audits/legacy-routes.json'),
            '--scope' => 'admin',
            '--policy' => base_path('docs/audits/legacy-route-method-policy.json'),
            '--json' => $jsonFile,
            '--markdown' => $markdownFile,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());

        $rows = json_decode((string) file_get_contents($jsonFile), true);
        $this->assertIsArray($rows);
        $this->assertCount(204, $rows);

        $gaps = array_values(array_filter($rows, static function (array $row): bool {
            return ! in_array($row['status'], ['matched', 'intentional_method_restriction'], true);
        }));

        $this->assertSame([], $gaps);
    }
}
