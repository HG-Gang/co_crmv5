<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:54
 */

/**
 * 后台产品列表与导出接口数值筛选参数严格校验的功能测试。
 *
 * 文件功能：
 * - 验证 group_id、status 等数值筛选传入非严格数字时列表与导出接口均返回校验失败。
 * - 验证校验失败时不返回测试产品、不流式导出 CSV。
 *
 * 适用场景：
 * - 产品（交易品种）管理页面的条件筛选与导出，防止非法数值注入查询。
 *
 * 入参例子：
 * - POST /api/admin/productionList，body：{"group_id": "3291abc", "limit": 5}。
 * - POST /api/admin/exportProductions，body：{"status": "1abc"}。
 *
 * 返回值：
 * - 校验失败返回 code=ResponseCode::VALIDATION_FAILED，导出响应非 text/csv。
 *
 * 异常或失败场景：
 * - group_id、status 为非严格整数时接口拒绝查询并返回校验失败响应。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminProductionNumericFilterValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 持仓列表数字校验用例的合约代码标记。验证按 symbol 过滤拒绝非法输入。
     * @var string
     */
    private const TEST_SYMBOL = 'PRODVAL329';
    /**
     * 夹具 MT4 组 ID。验证按组过滤时同样执行数字校验。
     * @var int
     */
    private const TEST_GROUP_ID = 3291;

    protected function tearDown(): void
    {
        DB::table('symbol_prices')->where('symbol', self::TEST_SYMBOL)->delete();

        parent::tearDown();
    }

    // 验证产品列表对非严格 group_id 筛选返回校验失败且不返回测试产品。
    public function test_production_list_rejects_non_strict_group_id_filter_without_returning_symbol(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createProductionSymbol();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/productionList', [
                'group_id' => self::TEST_GROUP_ID . 'abc',
                'limit' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertStringNotContainsString(self::TEST_SYMBOL, $response->getContent());
    }

    // 验证产品导出对非严格 status 筛选返回校验失败且不流式输出 CSV。
    public function test_production_export_rejects_non_strict_status_filter_without_streaming_csv(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createProductionSymbol();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/exportProductions', [
                'status' => '1abc',
            ]);

        $this->assertStringNotContainsString('text/csv', (string) $response->headers->get('content-type'));

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    // 校验最终检查清单文档记录了产品数值筛选校验边界。
    public function test_final_checklist_records_production_numeric_filter_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 329.', $checklist);
        $this->assertStringContainsString('ProductionController::productionList', $checklist);
        $this->assertStringContainsString('ProductionController::exportProductions', $checklist);
        $this->assertStringContainsString('/api/admin/productionList', $checklist);
        $this->assertStringContainsString('/api/admin/exportProductions', $checklist);
        $this->assertStringContainsString('symbol_prices.group_id', $checklist);
        $this->assertStringContainsString('symbol_prices.status', $checklist);
        $this->assertStringContainsString('AdminProductionNumericFilterValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-production-numeric-super',
                'email' => 'admin-production-numeric-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createProductionSymbol(): void
    {
        $now = time();

        DB::table('symbol_prices')->updateOrInsert(
            ['symbol' => self::TEST_SYMBOL],
            [
                'time' => date('Y-m-d H:i:s', $now),
                'bid' => 12.34,
                'ask' => 12.56,
                'low' => 12.00,
                'high' => 12.80,
                'direction' => 0,
                'digits' => 2,
                'spread' => 0.22,
                'group_id' => self::TEST_GROUP_ID,
                'status' => 1,
                'modify_time' => date('Y-m-d H:i:s', $now),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }
}
