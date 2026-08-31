<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/28
 * Time: 23:44
 */

/**
 * AdminUserUpdateAuthAndBankClosureModuleTest
 *
 * 文件功能：
 * - 验证后台用户更新认证与银行卡边界：旧 id_card 字段写 user_auth 与审计日志、重复证件拒绝部分写入、银行快照先同步 MT4 备注且失败关闭。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

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

/**
 * 后台普通用户资料编辑实名与银行卡闭环测试。
 *
 * 功能逻辑说明：
 * - 旧项目 CustomerController::cust_save_info 会读取 userIdcardNo、bank_no、bank_class 和 bank_info。
 * - 新项目实名资料拆分到 user_auths，本测试锁定旧字段到新表字段的映射边界。
 * - 身份证号必须保持用户维度唯一；重复时不能先写 user_infos 基础资料，避免页面出现部分保存成功。
 * - 已审核银行卡变更会影响 MT4 comment，必须先取得 MT4 明确成功，再写本地 user_auths 镜像和审计日志。
 */
class AdminUserUpdateAuthAndBankClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_user_update_accepts_legacy_id_card_field_and_writes_user_auth_with_audit_log(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 98726901;
        $this->seedUserWithAuth($userId, 'Before Id Card', [
            'id_card_no' => 'OLD-ID-98726901',
        ]);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/updateUser', [
                'data' => [
                    'userId' => $userId,
                    'userIdcardNo' => 'NEW-ID-98726901',
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertDatabaseHas('user_auths', [
            'user_id' => $userId,
            'id_card_no' => 'NEW-ID-98726901',
        ]);

        $log = DB::table('operation_logs')
            ->where('order_no', 'user_update:' . $userId)
            ->first();

        $this->assertNotNull($log, 'updateUser 修改身份证后必须写 operation_logs 审计记录。');
        $this->assertStringContainsString('auth.id_card_no:changed', (string) $log->content);
        $this->assertStringNotContainsString('NEW-ID-98726901', (string) $log->content);
    }

    public function test_admin_user_update_rejects_duplicate_id_card_without_partial_profile_write(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 98726902;
        $otherUserId = 98726903;
        $this->seedUserWithAuth($userId, 'Before Duplicate Id Card', [
            'id_card_no' => 'ORIGINAL-ID-98726902',
        ]);
        $this->seedUserWithAuth($otherUserId, 'Other Duplicate Id Card Owner', [
            'id_card_no' => 'DUPLICATE-ID-98726903',
        ]);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->patch('/api/admin/users/' . $userId, [
                'user_name' => 'Should Not Persist Id Card',
                'id_card_no' => 'DUPLICATE-ID-98726903',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'user_name' => 'Before Duplicate Id Card',
        ]);
        $this->assertDatabaseHas('user_auths', [
            'user_id' => $userId,
            'id_card_no' => 'ORIGINAL-ID-98726902',
        ]);
        $this->assertSame(0, DB::table('operation_logs')->where('order_no', 'user_update:' . $userId)->count());
    }

    public function test_admin_user_update_bank_snapshot_syncs_mt4_comment_before_local_write(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 98726904;
        $this->seedUserWithAuth($userId, 'Before Bank Update', [
            'bank_no' => 'OLD-BANK-98726904',
            'bank_name' => 'Old Bank',
            'bank_addr' => 'Old Branch',
            'bank_status' => 2,
        ]);

        $calls = [];
        $this->bindCommentMt4($calls, true);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->postJson('/api/admin/updateUser', [
                'data' => [
                    'userId' => $userId,
                    'username' => 'After Bank Update',
                    'bank_no' => 'NEW-BANK-98726904',
                    'bank_class' => 'New Bank',
                    'bank_info' => 'New Branch',
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertSame([
            [
                'user_id' => $userId,
                'comment' => 'NEW-BANK-98726904|New Bank|New Branch',
                'before_bank_no' => 'OLD-BANK-98726904',
                'before_user_name' => 'Before Bank Update',
            ],
        ], $calls);

        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'user_name' => 'After Bank Update',
        ]);
        $this->assertDatabaseHas('user_auths', [
            'user_id' => $userId,
            'bank_no' => 'NEW-BANK-98726904',
            'bank_name' => 'New Bank',
            'bank_addr' => 'New Branch',
            'is_bank_synced' => 1,
        ]);

        $log = DB::table('operation_logs')
            ->where('order_no', 'user_update:' . $userId)
            ->first();

        $this->assertNotNull($log, 'updateUser 修改已审核银行卡后必须写 operation_logs 审计记录。');
        $this->assertStringContainsString('auth.bank_no:changed', (string) $log->content);
        $this->assertStringContainsString('auth.bank_name:Old Bank->New Bank', (string) $log->content);
        $this->assertStringNotContainsString('NEW-BANK-98726904', (string) $log->content);
    }

    public function test_admin_user_update_bank_snapshot_fails_closed_when_mt4_comment_sync_fails(): void
    {
        $admin = $this->ensureSuperAdmin();
        $userId = 98726905;
        $this->seedUserWithAuth($userId, 'Before Bank Fail', [
            'bank_no' => 'OLD-BANK-98726905',
            'bank_name' => 'Old Fail Bank',
            'bank_addr' => 'Old Fail Branch',
            'bank_status' => 2,
            'is_bank_synced' => 1,
        ]);

        $calls = [];
        $this->bindCommentMt4($calls, false);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($admin, 'admin')
            ->patch('/api/admin/users/' . $userId, [
                'user_name' => 'Should Not Persist Bank',
                'bank_no' => 'NEW-BANK-98726905',
                'bank_name' => 'New Fail Bank',
                'bank_addr' => 'New Fail Branch',
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::MT4_SYNC_FAILED);

        $this->assertCount(1, $calls);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'user_name' => 'Before Bank Fail',
        ]);
        $this->assertDatabaseHas('user_auths', [
            'user_id' => $userId,
            'bank_no' => 'OLD-BANK-98726905',
            'bank_name' => 'Old Fail Bank',
            'bank_addr' => 'Old Fail Branch',
            'is_bank_synced' => 1,
        ]);
        $this->assertSame(0, DB::table('operation_logs')->where('order_no', 'user_update:' . $userId)->count());
    }

    public function test_final_checklist_records_admin_user_update_auth_and_bank_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('AdminUserController::updateUser', $checklist);
        $this->assertStringContainsString('user_auths.id_card_no', $checklist);
        $this->assertStringContainsString('user_auths.bank_no', $checklist);
        $this->assertStringContainsString('Mt4ManagerService::updateComment', $checklist);
        $this->assertStringContainsString('AdminUserUpdateAuthAndBankClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-user-update-auth-bank-super',
                'email' => 'admin-user-update-auth-bank-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    /**
     * 创建带实名资料的后台普通用户测试夹具。
     *
     * 参数含义：
     * - $userId 表示业务用户 ID，用于 user_infos、user_logins 和 user_auths 三张表关联。
     * - $userName 表示更新前的用户姓名，用来判断失败分支是否出现部分落库。
     * - $authOverrides 表示本次测试要覆盖的实名或银行卡字段。
     *
     * @param int $userId 业务用户 ID。
     * @param string $userName 用户姓名。
     * @param array<string, mixed> $authOverrides user_auths 覆盖字段。
     * @return void
     */
    private function seedUserWithAuth(int $userId, string $userName, array $authOverrides = []): void
    {
        $now = time();

        DB::table('operation_logs')->where('order_no', 'user_update:' . $userId)->delete();
        DB::table('user_auths')->where('user_id', $userId)->delete();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'admin-user-update-auth-bank-' . $userId . '@example.test',
            'password' => Hash::make('password'),
            'account_type' => 2,
            'role_id' => 0,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => $userName,
            'phone' => '',
            'gender' => 1,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => (string) $userId,
            'auth_status' => 0,
            'mt4_group' => 'AUTH-BANK-GROUP',
            'leverage' => 100,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_auths')->insert(array_merge([
            'user_id' => $userId,
            'bank_no' => '',
            'bank_no_tmp' => '',
            'bank_name' => '',
            'bank_name_tmp' => '',
            'bank_card_img' => '',
            'bank_card_back_img' => '',
            'bank_card_img_tmp' => '',
            'bank_card_back_img_tmp' => '',
            'bank_addr' => '',
            'bank_addr_tmp' => '',
            'bank_status' => 0,
            'bank_remarks' => '',
            'id_card_no' => '',
            'id_card_status' => 0,
            'id_card_front' => '',
            'id_card_back' => '',
            'id_card_remarks' => '',
            'is_bank_synced' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ], $authOverrides));
    }

    private function bindCommentMt4(array &$calls, bool $ok): void
    {
        $this->app->instance(Mt4ManagerService::class, new class($calls, $ok) extends Mt4ManagerService {
            /**
             * MT4 替身的调用捕获表。记录 updateComment 的入参，断言认证/银行信息更新触发的备注同步。
             * @var array<int, array<string, mixed>>
             */
            private $calls;
            /**
             * MT4 替身的成功开关。false 返回连接失败，验证备注同步失败时本地更新回滚。
             * @var bool
             */
            private $ok;

            public function __construct(array &$calls, bool $ok)
            {
                $this->calls = &$calls;
                $this->ok = $ok;
                parent::__construct('127.0.0.1', 0, 'k', '1', 1);
            }

            public function updateComment($userId, $comment)
            {
                $auth = DB::table('user_auths')->where('user_id', (int) $userId)->first();
                $user = DB::table('user_infos')->where('user_id', (int) $userId)->first();
                $this->calls[] = [
                    'user_id' => (int) $userId,
                    'comment' => (string) $comment,
                    'before_bank_no' => (string) $auth->bank_no,
                    'before_user_name' => (string) $user->user_name,
                ];

                return $this->ok
                    ? ['status' => 'ok', 'err' => '0', 'message' => 'updated', 'data' => []]
                    : ['status' => 'error', 'error_code' => 'connection_failed', 'message' => 'fail', 'data' => []];
            }
        });
    }
}
