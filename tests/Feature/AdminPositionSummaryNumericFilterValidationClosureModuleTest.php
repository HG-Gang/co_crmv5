<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:54
 */

/**
 * 后台仓位汇总列表与导出接口数值筛选参数严格校验的功能测试。
 *
 * 文件功能：
 * - 验证 user_id、parent_id、account_type 等数值筛选传入非严格数字时列表与导出接口均返回校验失败。
 * - 验证校验失败时不返回测试用户数据、不流式导出 CSV。
 *
 * 适用场景：
 * - 仓位汇总页面的数值条件筛选与导出，防止非法数值注入查询。
 *
 * 入参例子：
 * - POST /api/admin/positionSummaryList，body：{"user_id": "983721abc", "limit": 5}。
 * - POST /api/admin/exportPositionSummary，body：{"parent_id": "983720abc"}。
 *
 * 返回值：
 * - 校验失败返回 code=ResponseCode::VALIDATION_FAILED，导出响应非 text/csv。
 *
 * 异常或失败场景：
 * - 任一数值筛选为非严格整数时接口拒绝查询并返回校验失败响应。
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

class AdminPositionSummaryNumericFilterValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 持仓汇总数字校验用例的夹具业务用户 ID。验证按 user_id 过滤拒绝非数字输入。
     * @var int
     */
    private const TEST_USER_ID = 983721;
    /**
     * 夹具用户的父级代理 ID。验证按父级下钻时同样执行数字校验。
     * @var int
     */
    private const TEST_PARENT_ID = 983720;
    /**
     * 夹具用户的 user_name 标记。断言返回记录归属正确用户。
     * @var string
     */
    private const TEST_USER_NAME = 'Position Summary Numeric Validation User';

    protected function tearDown(): void
    {
        DB::table('user_infos')->whereIn('user_id', [self::TEST_USER_ID, self::TEST_PARENT_ID])->delete();

        parent::tearDown();
    }

    // 验证仓位汇总列表对多种非严格数值筛选均返回校验失败且不返回测试用户。
    public function test_position_summary_list_rejects_non_strict_numeric_filters_without_returning_user(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createPositionSummaryUser();

        foreach ($this->invalidNumericFilters() as $payload) {
            $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
                ->actingAs($actor, 'admin')
                ->post('/api/admin/positionSummaryList', $payload + ['limit' => 5]);

            $response->assertOk()
                ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

            $this->assertStringNotContainsString(self::TEST_USER_NAME, $response->getContent());
        }
    }

    // 验证仓位汇总导出对多种非严格数值筛选均返回校验失败且不流式输出 CSV。
    public function test_position_summary_export_rejects_non_strict_numeric_filters_without_streaming_csv(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createPositionSummaryUser();

        foreach ($this->invalidNumericFilters() as $payload) {
            $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
                ->actingAs($actor, 'admin')
                ->post('/api/admin/exportPositionSummary', $payload);

            $this->assertStringNotContainsString('text/csv', (string) $response->headers->get('content-type'));

            $response->assertOk()
                ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        }
    }

    // 校验最终检查清单文档记录了仓位汇总数值筛选校验边界。
    public function test_final_checklist_records_position_summary_numeric_filter_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 316.', $checklist);
        $this->assertStringContainsString('PositionSummaryController::positionSummaryList', $checklist);
        $this->assertStringContainsString('PositionSummaryController::exportPositionSummary', $checklist);
        $this->assertStringContainsString('/api/admin/positionSummaryList', $checklist);
        $this->assertStringContainsString('/api/admin/exportPositionSummary', $checklist);
        $this->assertStringContainsString('user_infos.user_id', $checklist);
        $this->assertStringContainsString('user_infos.parent_id', $checklist);
        $this->assertStringContainsString('user_infos.account_type', $checklist);
        $this->assertStringContainsString('AdminPositionSummaryNumericFilterValidationClosureModuleTest', $checklist);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function invalidNumericFilters(): array
    {
        return [
            ['user_id' => self::TEST_USER_ID . 'abc'],
            ['parent_id' => self::TEST_PARENT_ID . 'abc'],
            ['account_type' => '2abc'],
        ];
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-position-summary-filter-super',
                'email' => 'admin-position-summary-filter-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createPositionSummaryUser(): void
    {
        $now = time();

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => self::TEST_USER_ID],
            [
                'login_id' => 0,
                'user_name' => self::TEST_USER_NAME,
                'phone' => '',
                'gender' => 1,
                'account_type' => 2,
                'parent_id' => self::TEST_PARENT_ID,
                'family_tree' => self::TEST_PARENT_ID . ',' . self::TEST_USER_ID,
                'mt4_group' => 'position-summary-validation',
                'total_funds' => 0,
                'equity' => 0,
                'effective_credit' => 0,
                'created_at' => $now - 3600,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }
}
