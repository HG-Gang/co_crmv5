<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 22:37
 */

/**
 * AdminCancellationLegacyParityClosureModuleTest
 *
 * 文件功能：
 * - 验证后台销户申请旧路由等价闭环：V1/V2 envelope 与字段别名、待审日期范围默认值、一/二级审批备注必填、创建范围按销户归属人过滤。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Models\Admin;
use App\Services\Mt4ManagerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 后台销户申请旧路由等价闭环测试。
 *
 * 列表字段只允许从 cancel_applies、user_infos 和 user_trades 读取；旧控制器只负责
 * V1/V2 envelope 与字段别名，不得复制查询 SQL 或返回模拟余额、持仓数据。
 */
class AdminCancellationLegacyParityClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 夹具订单的自增序列。生成互不重复的 ticket，构造多张销单样本。
     * @var int
     */
    private $sequence = 0;

    public function test_legacy_v1_list_returns_real_balance_open_positions_and_old_fields(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = $this->newUserId();
        $applyId = $this->seedCancelApply($userId, 'V1 cancellation user', 245.67, '2026-08-18 12:30:00');
        $this->seedTrade($userId, 0, '1970-01-01 00:00:00', 1.25);
        $this->seedTrade($userId, 1, '1970-01-01 00:00:00', 0.50);
        $this->seedTrade($userId, 2, '1970-01-01 00:00:00', 0.00);
        $this->seedTrade($userId, 0, '2026-08-18 13:30:00', 1.00);

        $response = $this->actingAs($admin, 'admin')->postJson(
            '/index/admin/cancel/userlistSearch',
            [
                'userId' => $userId,
                'cancel_status' => 0,
                'startdate' => '2026-08-18',
                'enddate' => '2026-08-18',
                'page' => 1,
                'rows' => 20,
            ]
        );

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('rows.0.cancel_id', $applyId)
            ->assertJsonPath('rows.0.cancel_userid', $userId)
            ->assertJsonPath('rows.0.cancel_username', 'V1 cancellation user')
            ->assertJsonPath('rows.0.cancel_status', 0)
            ->assertJsonPath('rows.0.cancel_remark', 'Applicant reason')
            ->assertJsonPath('rows.0.bal', '245.67')
            ->assertJsonPath('rows.0.vol', 2)
            ->assertJsonPath('rows.0.rec_crt_date', '2026-08-18 12:30:00');
    }

    public function test_legacy_v1_empty_page_keeps_empty_string_envelope(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cancel/userlistSearch', [
                'userId' => $this->newUserId(),
                'page' => 1,
                'rows' => 20,
            ])
            ->assertOk()
            ->assertJson([
                'rows' => '',
                'total' => '',
            ]);
    }

    public function test_legacy_v2_list_keeps_layui_envelope_and_defaults_to_pending_date_range(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $pendingUserId = $this->newUserId();
        $approvedUserId = $this->newUserId();
        $this->seedCancelApply($pendingUserId, 'V2 pending user', -18.50, '2026-08-18 09:15:00');
        $this->seedCancelApply($approvedUserId, 'V2 approved user', 99.00, '2026-08-18 09:10:00', 1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cancel/userlistSearchV2', [
                'userId' => $pendingUserId,
                'cancel_status' => 0,
                'page' => 1,
                'limit' => 20,
            ])
            ->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('msg', 'Request data successful.')
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.cancel_userid', $pendingUserId)
            ->assertJsonPath('data.0.bal', '-18.50')
            ->assertJsonPath('totalRow', []);
    }

    public function test_update_cancel_uses_one_two_decisions_requires_remark_and_returns_old_result_fields(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $rejectUserId = $this->newUserId();
        $rejectApplyId = $this->seedCancelApply($rejectUserId, 'Decision reject user', 0, '2026-08-18 10:00:00');

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cancel/update_cancel', [
                'cancel_userid' => $rejectUserId,
                'accept_rejection' => 2,
                'cancel_remark' => 'Required reviewer reason',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('msg', 'SUCCESS')
            ->assertJsonPath('col', 'UPDATESUC');

        $this->assertDatabaseHas('cancel_applies', [
            'id' => $rejectApplyId,
            'status' => -1,
            'reject_reason' => 'Required reviewer reason',
        ]);

        $invalidUserId = $this->newUserId();
        $invalidApplyId = $this->seedCancelApply($invalidUserId, 'Decision invalid user', 0, '2026-08-18 10:10:00');
        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cancel/update_cancel', [
                'cancel_userid' => $invalidUserId,
                'accept_rejection' => 0,
                'cancel_remark' => 'Zero is not a canonical decision',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        $this->assertSame(0, (int) DB::table('cancel_applies')->where('id', $invalidApplyId)->value('status'));
    }

    public function test_update_cancel_approval_rejects_blank_remark_before_mt4_or_database_changes(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = $this->newUserId();
        $applyId = $this->seedCancelApply($userId, 'Blank review user', 0, '2026-08-18 10:20:00');
        $mt4Calls = [];
        $this->app->instance(Mt4ManagerService::class, new class($mt4Calls) extends Mt4ManagerService {
            /**
             * MT4 lockUser 替身的调用捕获表。记录被锁定的 userId，断言注销链路触发的锁定指令。
             * @var array<int, int>
             */
            private $calls;

            public function __construct(array &$calls)
            {
                $this->calls = &$calls;
                parent::__construct('127.0.0.1', 0, 'test', '1', 1);
            }

            public function lockUser($userId)
            {
                $this->calls[] = (int) $userId;

                return ['status' => 'ok'];
            }
        });

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cancel/update_cancel', [
                'cancel_userid' => $userId,
                'accept_rejection' => 1,
                'cancel_remark' => '   ',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertSame([], $mt4Calls);
        $this->assertSame(0, (int) DB::table('cancel_applies')->where('id', $applyId)->value('status'));
        $this->assertFalse(DB::table('operation_logs')->where('order_no', 'cancel_apply:' . $applyId)->exists());
    }

    public function test_created_scope_uses_cancel_apply_owner_for_list_and_review(): void
    {
        $now = time();
        $roleId = (int) DB::table('roles')->insertGetId([
            'name' => 'cancel-created-scope-' . random_int(1000, 9999),
            'guard_type' => 'admin',
            'description' => 'Cancellation created scope fixture',
            'permissions' => null,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('role_data_scopes')->insert([
            'role_id' => $roleId,
            'scope_type' => 'created',
            'agent_ids' => null,
            'user_ids' => null,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $adminId = (int) DB::table('admins')->insertGetId([
            'role_id' => (string) $roleId,
            'username' => 'cancel-created-' . random_int(1000, 9999),
            'email' => 'cancel-created-' . random_int(100000, 999999) . '@example.test',
            'password' => Hash::make('password'),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        $admin = Admin::query()->findOrFail($adminId);
        $ownUserId = $this->newUserId();
        $foreignUserId = $this->newUserId();
        $ownApplyId = $this->seedCancelApply($ownUserId, 'Owned cancellation', 10, '2026-08-18 11:00:00', 0, (string) $adminId);
        $foreignApplyId = $this->seedCancelApply($foreignUserId, 'Foreign cancellation', 20, '2026-08-18 11:10:00', 0, (string) ($adminId + 1));

        $client = $this->withoutMiddleware()->actingAs($admin, 'admin');
        $client->postJson('/api/admin/cancelApplyList', ['per_page' => 20])
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $ownApplyId);

        $client->postJson('/api/admin/cancelApplyReject/' . $ownApplyId, ['reason' => 'Owned review'])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);
        $client->postJson('/api/admin/cancelApplyReject/' . $foreignApplyId, ['reason' => 'Foreign review'])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);

        $this->assertSame(-1, (int) DB::table('cancel_applies')->where('id', $ownApplyId)->value('status'));
        $this->assertSame(0, (int) DB::table('cancel_applies')->where('id', $foreignApplyId)->value('status'));
    }

    private function seedCancelApply(
        int $userId,
        string $userName,
        float $balance,
        string $createdAt,
        int $status = 0,
        string $createdBy = '1'
    ): int {
        $timestamp = strtotime($createdAt);
        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $userId,
            'user_name' => $userName,
            'account_type' => 2,
            'family_tree' => (string) $userId,
            'total_funds' => $balance,
            'equity' => $balance,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'deleted_at' => null,
        ]);

        return (int) DB::table('cancel_applies')->insertGetId([
            'user_id' => $userId,
            'user_name' => $userName,
            'status' => $status,
            'cancel_remark' => 'Applicant reason',
            'reject_reason' => '',
            'created_by' => $createdBy,
            'updated_by' => '',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'deleted_at' => null,
        ]);
    }

    private function seedTrade(int $userId, int $cmd, string $closeTime, float $marginRate): void
    {
        $this->sequence++;
        $ticket = $userId * 10 + $this->sequence;
        DB::table('user_trades')->insert([
            'user_id' => $userId,
            'ticket' => $ticket,
            'symbol' => 'EURUSD',
            'digits' => 5,
            'cmd' => $cmd,
            'volume' => 100,
            'open_time' => '2026-08-18 08:00:00',
            'open_price' => 1.1,
            'close_time' => $closeTime,
            'modify_time' => '2026-08-18 08:00:00',
            'margin_rate' => $marginRate,
            'created_at' => strtotime('2026-08-18 08:00:00'),
            'updated_at' => strtotime('2026-08-18 08:00:00'),
            'deleted_at' => null,
        ]);
    }

    private function newUserId(): int
    {
        do {
            $userId = random_int(930000, 939999);
        } while (DB::table('user_infos')->where('user_id', $userId)->exists()
            || DB::table('cancel_applies')->where('user_id', $userId)->exists());

        return $userId;
    }
}
