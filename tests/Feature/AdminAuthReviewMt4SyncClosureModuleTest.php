<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:46
 */

/**
 * 后台实名认证审核 MT4 同步闭包测试。
 *
 * 文件功能：
 * - 验证 POST /api/admin/reviewAuth 审核通过时调用 Mt4ManagerService::updateComment 同步银行/证件信息到 MT4，并更新本地 user_auths 与 user_infos 状态。
 * - 验证 MT4 同步失败时采用失败关闭策略：返回 MT4_SYNC_FAILED，本地状态保持待审核不置为通过。
 * - 验证拒绝审核（status=2）不触发 MT4 同步，仅更新本地状态与备注。
 *
 * 适用场景：
 * - 后台实名认证审核流程的 MT4 同步与失败关闭回归测试。
 *
 * 入参例子：
 * - POST /api/admin/reviewAuth
 *   {
 *     "user_id": 986301,
 *     "status": 1
 *   }
 * - 拒绝时 status 传 2 并携带 reason。
 *
 * 方法功能：
 * - test_auth_review_pass_updates_mt4_comment_and_local_status：审核通过，断言网关收到含 BANK 号的备注且本地状态置为通过。
 * - test_auth_review_pass_fails_closed_when_mt4_update_fails：网关失败，断言返回 MT4_SYNC_FAILED 且本地状态保持原样。
 * - test_auth_review_reject_does_not_require_mt4_sync：拒绝审核，断言网关未被调用且本地状态置为拒绝。
 *
 * 返回值：
 * - 成功返回 code=SUCCESS，MT4 同步失败返回 code=MT4_SYNC_FAILED；断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若审核通过未同步 MT4、同步失败仍置为通过或拒绝时误调网关，测试断言失败。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use App\Services\Mt4ManagerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthReviewMt4SyncClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 审核通过：断言网关收到含银行号的备注调用且本地 user_auths/user_infos 状态置为通过。
     *
     * @return void
     */
    public function test_auth_review_pass_updates_mt4_comment_and_local_status(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 986301;
        $this->seedAuthUser($userId, 'Auth Review MT4 User', 'BANK-986301', 'ID986301');

        $calls = [];
        $this->app->instance(Mt4ManagerService::class, new class($calls) extends Mt4ManagerService {
            /**
             * MT4 替身的调用捕获表。记录审核通过触发的 updateComment 入参，断言备注同步内容。
             * @var array<int, array{user_id: int, comment: string}>
             */
            private $calls;

            public function __construct(array &$calls)
            {
                $this->calls = &$calls;
                parent::__construct('127.0.0.1', 0, 'k', '1', 1);
            }

            public function updateComment($userId, $comment)
            {
                $this->calls[] = [
                    'user_id' => (int) $userId,
                    'comment' => (string) $comment,
                ];

                return ['status' => 'ok', 'message' => 'updated', 'data' => []];
            }
        });

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/reviewAuth', [
                'user_id' => $userId,
                'status' => 1,
            ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertCount(1, $calls);
        $this->assertSame($userId, $calls[0]['user_id']);
        $this->assertStringContainsString('BANK-986301', $calls[0]['comment']);
        $this->assertDatabaseHas('user_auths', [
            'user_id' => $userId,
            'id_card_status' => 2,
            'bank_status' => 2,
        ]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'auth_status' => 1,
        ]);
    }

    /**
     * 审核通过但 MT4 同步失败：断言返回 MT4_SYNC_FAILED 且本地状态保持待审核（失败关闭）。
     *
     * @return void
     */
    public function test_auth_review_pass_fails_closed_when_mt4_update_fails(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 986302;
        $this->seedAuthUser($userId, 'Auth Review MT4 Fail', 'BANK-986302', 'ID986302');

        $this->app->instance(Mt4ManagerService::class, new class extends Mt4ManagerService {
            public function __construct()
            {
                parent::__construct('127.0.0.1', 0, 'k', '1', 1);
            }

            public function updateComment($userId, $comment)
            {
                return [
                    'status' => 'error',
                    'error_code' => 'connection_failed',
                    'message' => 'fail',
                    'data' => [],
                ];
            }
        });

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/reviewAuth', [
                'user_id' => $userId,
                'status' => 1,
            ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::MT4_SYNC_FAILED);
        $this->assertDatabaseHas('user_auths', [
            'user_id' => $userId,
            'id_card_status' => 1,
            'bank_status' => 1,
        ]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'auth_status' => 0,
        ]);
    }

    /**
     * 拒绝审核：断言网关未被调用，本地状态置为拒绝并记录拒绝原因。
     *
     * @return void
     */
    public function test_auth_review_reject_does_not_require_mt4_sync(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 986303;
        $this->seedAuthUser($userId, 'Auth Review Reject User', 'BANK-986303', 'ID986303');

        $calls = [];
        $this->app->instance(Mt4ManagerService::class, new class($calls) extends Mt4ManagerService {
            /**
             * MT4 替身的调用捕获表。记录审核驳回/复审场景的 updateComment 入参，断言备注同步内容。
             * @var array<int, array{user_id: int, comment: string}>
             */
            private $calls;

            public function __construct(array &$calls)
            {
                $this->calls = &$calls;
                parent::__construct('127.0.0.1', 0, 'k', '1', 1);
            }

            public function updateComment($userId, $comment)
            {
                $this->calls[] = 'called';

                return ['status' => 'ok', 'message' => 'updated', 'data' => []];
            }
        });

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/reviewAuth', [
                'user_id' => $userId,
                'status' => 2,
                'reason' => 'id card blur',
            ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertSame([], $calls);
        $this->assertDatabaseHas('user_auths', [
            'user_id' => $userId,
            'id_card_status' => 4,
            'bank_status' => 4,
        ]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'auth_status' => 2,
        ]);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();
        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-auth-review-mt4',
                'email' => 'admin-auth-review-mt4@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function seedAuthUser(int $userId, string $userName, string $bankNo, string $idCardNo): void
    {
        $now = time();
        DB::table('user_logins')->updateOrInsert(
            ['email' => 'auth-review-' . $userId . '@example.test'],
            [
                'user_id' => $userId,
                'password' => Hash::make('password'),
                'account_type' => 2,
                'role_id' => 0,
                'is_enabled' => 1,
                'is_cancelled' => 0,
                'source_type' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => $userId,
                'user_name' => $userName,
                'phone' => '',
                'gender' => 1,
                'account_type' => 2,
                'parent_id' => 0,
                'family_tree' => (string) $userId,
                'auth_status' => 0,
                'total_funds' => 0,
                'equity' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
        DB::table('user_auths')->updateOrInsert(
            ['user_id' => $userId],
            [
                'bank_no' => $bankNo,
                'bank_no_tmp' => '',
                'bank_name' => 'Test Bank',
                'bank_name_tmp' => '',
                'bank_card_img' => '',
                'bank_card_back_img' => '',
                'bank_card_img_tmp' => '',
                'bank_card_back_img_tmp' => '',
                'bank_addr' => 'Branch',
                'bank_addr_tmp' => '',
                'bank_status' => 1,
                'bank_remarks' => '',
                'id_card_no' => $idCardNo,
                'id_card_status' => 1,
                'id_card_front' => '',
                'id_card_back' => '',
                'id_card_remarks' => '',
                'is_bank_synced' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }
}
