<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/07
 * Time: 14:42
 */

/**
 * AdminLegacyUserIdCardBankClosureModuleTest
 *
 * 文件功能：
 * - 验证旧后台身份证/银行卡合并审核闭环：双通过映射审核通过、混合结果不折叠为双驳回、银行驳回理由仅作用于银行卡组件、无活跃组件或缺 user_id 失败关闭。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Models\Admin;
use App\Services\Mt4ManagerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 后台遗留"实名认证/银行卡审核保存"入口 auth/user_idcard_bank 闭环测试。
 *
 * 文件目的：
 * - 锁定旧后台 AuthenticationController@user_idcard_bank 的迁移行为：
 *   旧表单字段 userId（别名 user_id）、idcard_auth、bank_auth（'0'=通过，否则拒绝）、
 *   userIdcard_status / userbank_status、idcard_reason / bank_reason 被转换为现代独立组件决定。
 * - 身份证状态 1、银行卡状态 1/3 参与审核；两项结果和拒绝原因彼此独立，最终认证状态由更新后的双状态推导。
 */
class AdminLegacyUserIdCardBankClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_legacy_user_idcard_bank_both_pass_maps_to_review_pass(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 987201;
        $this->seedAuthUser($userId, 'Legacy IdCard Bank Pass');

        $calls = [];
        $this->app->instance(Mt4ManagerService::class, new class($calls) extends Mt4ManagerService {
            /**
             * MT4 替身的调用捕获表。记录 updateComment 的 [user_id, comment] 入参，
             * 断言证件/银行卡更新触发的备注同步内容。
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

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/auth/user_idcard_bank', [
                'userId' => $userId,
                'username' => 'Legacy IdCard Bank Pass',
                'idcard_auth' => '0',
                'bank_auth' => '0',
                'userIdcard_status' => '1',
                'userbank_status' => '1',
                'idcard_reason' => '',
                'bank_reason' => '',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertCount(1, $calls);
        $this->assertSame($userId, $calls[0]['user_id']);
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

    public function test_legacy_user_idcard_bank_mixed_result_is_not_collapsed_to_double_reject(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 987202;
        $this->seedAuthUser($userId, 'Legacy IdCard Bank Reject');

        $calls = [];
        $this->app->instance(Mt4ManagerService::class, new class($calls) extends Mt4ManagerService {
            /**
             * MT4 替身的调用标记表。仅记录 updateComment 被调用（'called'），断言备注同步确实发生/未发生。
             * @var array<int, string>
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

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/auth/user_idcard_bank', [
                'userId' => $userId,
                'idcard_auth' => '1',
                'bank_auth' => '0',
                'userIdcard_status' => '1',
                'userbank_status' => '1',
                'idcard_reason' => '证件照片模糊',
                'bank_reason' => '',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertSame(['called'], $calls);
        $this->assertDatabaseHas('user_auths', [
            'user_id' => $userId,
            'id_card_status' => 4,
            'bank_status' => 2,
            'id_card_remarks' => '证件照片模糊',
            'bank_remarks' => '',
        ]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'auth_status' => 2,
        ]);
    }

    public function test_legacy_user_idcard_bank_keeps_bank_reject_reason_on_bank_component_only(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 987203;
        $this->seedAuthUser($userId, 'Legacy IdCard Bank Bank Reason');

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/auth/user_idcard_bank', [
                'userId' => $userId,
                'idcard_auth' => '0',
                'bank_auth' => '2',
                'userIdcard_status' => '1',
                'userbank_status' => '1',
                'idcard_reason' => '',
                'bank_reason' => '银行卡号与姓名不一致',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertDatabaseHas('user_auths', [
            'user_id' => $userId,
            'id_card_status' => 2,
            'bank_status' => 4,
            'id_card_remarks' => '',
            'bank_remarks' => '银行卡号与姓名不一致',
        ]);
    }

    public function test_legacy_user_idcard_bank_id_card_only_preserves_approved_bank_state(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 987205;
        $this->seedAuthUser($userId, 'Legacy IdCard Only');
        DB::table('user_auths')->where('user_id', $userId)->update([
            'bank_status' => 2,
            'bank_remarks' => 'approved bank state must remain',
            'is_bank_synced' => 1,
        ]);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/auth/user_idcard_bank', [
                'userId' => $userId,
                'idcard_auth' => '0',
                'bank_auth' => '2',
                'userIdcard_status' => '1',
                'userbank_status' => '2',
                'idcard_reason' => '',
                'bank_reason' => 'must be ignored',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertDatabaseHas('user_auths', [
            'user_id' => $userId,
            'id_card_status' => 2,
            'id_card_remarks' => '',
            'bank_status' => 2,
            'bank_remarks' => 'approved bank state must remain',
            'is_bank_synced' => 1,
        ]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'auth_status' => 1,
        ]);
    }

    public function test_legacy_user_idcard_bank_bank_only_preserves_approved_id_card_state(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 987207;
        $this->seedAuthUser($userId, 'Legacy Bank Only');
        DB::table('user_auths')->where('user_id', $userId)->update([
            'id_card_status' => 2,
            'id_card_remarks' => 'approved id card state must remain',
            'bank_no_tmp' => 'NEW-BANK-' . $userId,
            'bank_name_tmp' => 'New Review Bank',
            'bank_addr_tmp' => 'New Review Branch',
            'bank_card_img_tmp' => 'new-review-front.jpg',
            'bank_card_back_img_tmp' => 'new-review-back.jpg',
            'bank_status' => 3,
        ]);

        $calls = [];
        $this->app->instance(Mt4ManagerService::class, new class($calls) extends Mt4ManagerService {
            /**
             * MT4 替身的调用捕获表。记录 updateComment 的 [user_id, comment] 入参，断言备注同步内容。
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

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/auth/user_idcard_bank', [
                'userId' => $userId,
                'idcard_auth' => '2',
                'bank_auth' => '0',
                'userIdcard_status' => '2',
                'userbank_status' => '3',
                'idcard_reason' => 'must be ignored',
                'bank_reason' => '',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $this->assertSame($userId, $calls[0]['user_id']);
        $this->assertStringContainsString('NEW-BANK-' . $userId, $calls[0]['comment']);
        $this->assertStringContainsString('New Review Bank', $calls[0]['comment']);
        $this->assertDatabaseHas('user_auths', [
            'user_id' => $userId,
            'id_card_status' => 2,
            'id_card_remarks' => 'approved id card state must remain',
            'bank_no' => 'NEW-BANK-' . $userId,
            'bank_no_tmp' => '',
            'bank_name' => 'New Review Bank',
            'bank_name_tmp' => '',
            'bank_addr' => 'New Review Branch',
            'bank_addr_tmp' => '',
            'bank_card_img' => 'new-review-front.jpg',
            'bank_card_img_tmp' => '',
            'bank_card_back_img' => 'new-review-back.jpg',
            'bank_card_back_img_tmp' => '',
            'bank_status' => 2,
            'bank_remarks' => '',
            'is_bank_synced' => 1,
        ]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'auth_status' => 1,
        ]);
    }

    public function test_legacy_user_idcard_bank_without_an_active_component_fails_closed(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 987206;
        $this->seedAuthUser($userId, 'Legacy IdCard No Active Component');

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/auth/user_idcard_bank', [
                'userId' => $userId,
                'idcard_auth' => '0',
                'bank_auth' => '0',
                'userIdcard_status' => '2',
                'userbank_status' => '2',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('user_auths', [
            'user_id' => $userId,
            'id_card_status' => 1,
            'bank_status' => 1,
        ]);
    }

    public function test_legacy_user_idcard_bank_missing_user_id_fails_closed(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/auth/user_idcard_bank', [
                'idcard_auth' => '0',
                'bank_auth' => '0',
                'userIdcard_status' => '1',
                'userbank_status' => '1',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    public function test_legacy_user_idcard_bank_without_auth_record_fails_closed(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 987204;
        $this->seedUserInfoOnly($userId, 'Legacy IdCard Bank No Auth');

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/auth/user_idcard_bank', [
                'userId' => $userId,
                'idcard_auth' => '0',
                'bank_auth' => '0',
                'userIdcard_status' => '1',
                'userbank_status' => '1',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::DATA_NOT_FOUND);
    }

    private function seedAuthUser(int $userId, string $userName): void
    {
        $this->seedUserInfoOnly($userId, $userName);
        $now = time();

        DB::table('user_auths')->updateOrInsert(
            ['user_id' => $userId],
            [
                'bank_no' => 'BANK-' . $userId,
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
                'id_card_no' => 'ID-' . $userId,
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

    private function seedUserInfoOnly(int $userId, string $userName): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'legacy-idcard-bank-' . $userId . '@example.test',
            'password' => bcrypt('password'),
            'account_type' => 2,
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
            'phone' => '178000' . substr((string) $userId, -4),
            'account_type' => 2,
            'parent_id' => 0,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 0,
            'total_funds' => 0,
            'equity' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
