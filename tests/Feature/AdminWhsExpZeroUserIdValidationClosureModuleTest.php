<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:48
 */

/**
 * 一键清零相关接口 user_id 参数严格校验的闭环测试。
 *
 * 文件功能：
 * - 验证候选用户列表、清零记录列表、一键清零操作三个接口均拒绝非严格 user_id。
 * - 验证校验失败时不会返回用户/记录，也不会创建清零记录。
 * - 验证最终清单文档已记录该 user_id 校验边界。
 *
 * 适用场景：
 * - 管理员一键清零模块各接口入参安全的回归测试。
 *
 * 入参例子：
 * - POST /api/admin/whsExpZeroList   user_id: 983611abc
 * - POST /api/admin/whsExpZeroRecords user_id: 983612abc
 * - POST /api/admin/whsExpZero        user_id: 983613abc
 *
 * 返回值：
 * - 各接口返回 HTTP 200，code 为 VALIDATION_FAILED。
 * - 响应内容不含目标用户/记录信息。
 *
 * 异常或失败场景：
 * - 若非严格 user_id 被放行并返回数据或写入记录，断言失败。
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

class AdminWhsExpZeroUserIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * WHS 体验金列表校验用例的用户 ID。验证列表按 user_id 过滤拒绝非数字输入。
     * @var int
     */
    private const LIST_USER_ID = 983611;
    /**
     * 体验金记录查询校验用例的用户 ID。验证记录接口按 user_id 过滤拒绝非数字输入。
     * @var int
     */
    private const RECORD_USER_ID = 983612;
    /**
     * 体验金操作（发放）校验用例的用户 ID。验证写接口按 user_id 校验拒绝非法值。
     * @var int
     */
    private const ACTION_USER_ID = 983613;

    protected function tearDown(): void
    {
        $userIds = [self::LIST_USER_ID, self::RECORD_USER_ID, self::ACTION_USER_ID];

        DB::table('user_trades')->whereIn('user_id', $userIds)->delete();
        DB::table('whs_exp_zeros')->whereIn('user_id', $userIds)->delete();
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();

        parent::tearDown();
    }

    /**
     * 验证候选用户列表拒绝非严格 user_id 且不返回目标用户。
     */
    public function test_whs_exp_zero_candidate_list_rejects_non_strict_user_id_filter_without_returning_user(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createNegativeBalanceUser(self::LIST_USER_ID, 'WHS Zero User ID Validation Candidate', -120.50);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/whsExpZeroList', [
                'user_id' => self::LIST_USER_ID . 'abc',
                'limit' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertStringNotContainsString('WHS Zero User ID Validation Candidate', $response->getContent());
    }

    /**
     * 验证清零记录列表拒绝非严格 user_id 且不返回目标记录。
     */
    public function test_whs_exp_zero_record_list_rejects_non_strict_user_id_filter_without_returning_record(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createNegativeBalanceUser(self::RECORD_USER_ID, 'WHS Zero User ID Validation Record', -88.00);
        $this->createZeroRecord(self::RECORD_USER_ID, 'WHS Zero User ID Validation Record');

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/whsExpZeroRecords', [
                'user_id' => self::RECORD_USER_ID . 'abc',
                'limit' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertStringNotContainsString('WHS Zero User ID Validation Record', $response->getContent());
    }

    /**
     * 验证一键清零操作拒绝非严格 user_id 且不创建清零记录。
     */
    public function test_whs_exp_zero_action_rejects_non_strict_user_id_without_creating_record(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createNegativeBalanceUser(self::ACTION_USER_ID, 'WHS Zero User ID Validation Action', -66.60);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/whsExpZero', [
                'user_id' => self::ACTION_USER_ID . 'abc',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseMissing('whs_exp_zeros', [
            'user_id' => self::ACTION_USER_ID,
            'user_name' => 'WHS Zero User ID Validation Action',
        ]);
    }

    /**
     * 验证最终清单文档已记录一键清零 user_id 校验边界（## 314）。
     */
    public function test_final_checklist_records_whs_exp_zero_user_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 314.', $checklist);
        $this->assertStringContainsString('AdminWhsExpZeroController::zeroList', $checklist);
        $this->assertStringContainsString('AdminWhsExpZeroController::recordList', $checklist);
        $this->assertStringContainsString('AdminWhsExpZeroController::oneKeyZero', $checklist);
        $this->assertStringContainsString('/api/admin/whsExpZeroList', $checklist);
        $this->assertStringContainsString('/api/admin/whsExpZeroRecords', $checklist);
        $this->assertStringContainsString('/api/admin/whsExpZero', $checklist);
        $this->assertStringContainsString('user_infos.user_id', $checklist);
        $this->assertStringContainsString('whs_exp_zeros.user_id', $checklist);
        $this->assertStringContainsString('AdminWhsExpZeroUserIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-whs-exp-zero-user-id-super',
                'email' => 'admin-whs-exp-zero-user-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createNegativeBalanceUser(int $userId, string $userName, float $balance): void
    {
        $now = time();

        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => 0,
                'user_name' => $userName,
                'phone' => '',
                'gender' => 1,
                'account_type' => 2,
                'parent_id' => 0,
                'family_tree' => (string) $userId,
                'total_funds' => $balance,
                'equity' => $balance,
                'effective_credit' => 0,
                'created_at' => $now - 86400,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }

    private function createZeroRecord(int $userId, string $userName): void
    {
        $now = time();

        DB::table('whs_exp_zeros')->insert([
            'user_id' => $userId,
            'user_name' => $userName,
            'balance' => -88.00,
            'credit' => 0,
            'status' => 1,
            'md5_key' => 'whs-exp-zero-user-id-validation-' . $userId,
            'created_by' => 'test',
            'updated_by' => 'test',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
