<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 02:21
 */

/**
 * AdminLegacyAgentUserSaveClosureModuleTest
 *
 * 文件功能：
 * - 验证旧后台 agents_save 与 cust_save 闭环：旧字段名建代理/客户、客户分组校验、无邀请人失败关闭、嵌套 data 载荷更新资料及 MT4 同步失败回滚。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Models\Admin;
use App\Services\Mt4ManagerService;
use App\Services\Registration\Mt4UserProvisioningGateway;
use App\Services\Registration\UserMt4ProvisioningProcessor;
use App\Services\UserRegistrationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 后台遗留"代理/客户新增与资料保存"入口 agents_save、cust_save_add、cust_save_info 闭环测试。
 *
 * 文件目的：
 * - 锁定旧后台 AgentControllerV3@agents_save、CustomerController@cust_save_add / cust_save_info
 *   的迁移行为：旧表单字段（useremail、password1/againpassword、username、userphoneNo+modules、
 *   userIdcardNo、userInviterId、sex、comm_type 与嵌套 data）在新端由
 *   admin_api_createAgent / admin_api_createUser / admin_api_updateUser 原样承接。
 * - 客户新增必须挂靠已有代理（旧业务同样要求邀请人），缺失时按现代校验失败关闭。
 */
class AdminLegacyAgentUserSaveClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_legacy_agents_save_creates_agent_with_legacy_field_names(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $email = 'legacy-agents-save-' . time() . '@example.test';
        $manager = $this->fakeProvisioningProcessor();

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/agents_save', [
                'useremail' => $email,
                'password1' => 'secret123',
                'againpassword' => 'secret123',
                'username' => 'Legacy Created Agent',
                'userphoneNo' => '13800138000',
                'modules' => '86',
                'userIdcardNo' => 'ID-LEGACY-AGENT-1',
                'sex' => '1',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::CREATED);

        $userId = DB::table('user_logins')->where('email', $email)->value('user_id');
        $this->assertNotNull($userId, 'Legacy agents_save must create a login row via useremail.');
        $this->assertDatabaseHas('user_logins', [
            'email' => $email,
            'account_type' => 1,
        ]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => (int) $userId,
            'user_name' => 'Legacy Created Agent',
            'phone' => '86-13800138000',
            'account_type' => 1,
        ]);
        $this->assertDatabaseHas('user_auths', [
            'user_id' => (int) $userId,
            'id_card_no' => 'ID-LEGACY-AGENT-1',
        ]);
        $outbox = DB::table('user_mt4_provisioning_outbox')->where('user_id', $userId)->first();
        $this->assertNotNull($outbox, '本地注册仍必须保留待同步 Outbox 记录。');
        $this->assertSame('pending', (string) $outbox->status);
        $this->assertSame(0, (int) $outbox->attempts);
        $this->assertSame(0, $manager->registerCalls, 'MT4 开关关闭时不得调用 Manager 注册接口。');
    }

    public function test_legacy_cust_save_add_creates_customer_under_legacy_inviter_field(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $inviterId = 987501;
        $this->seedAgent($inviterId, 'Legacy Save Inviter Agent');
        $email = 'legacy-cust-save-add-' . time() . '@example.test';
        $this->fakeProvisioningProcessor();
        $this->ensureCustomerSequence();

        $response = $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/cust_save_add', [
                'useremail' => $email,
                'password1' => 'secret123',
                'againpassword' => 'secret123',
                'username' => 'Legacy Created Customer',
                'userphoneNo' => '13900139000',
                'modules' => '86',
                'userIdcardNo' => 'ID-LEGACY-CUSTOMER-1',
                'userInviterId' => $inviterId,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::CREATED);

        $userId = DB::table('user_logins')->where('email', $email)->value('user_id');
        $this->assertNotNull($userId, 'Legacy cust_save_add must create a login row via useremail.');
        $this->assertDatabaseHas('user_infos', [
            'user_id' => (int) $userId,
            'user_name' => 'Legacy Created Customer',
            'phone' => '86-13900139000',
            'account_type' => 2,
            'parent_id' => $inviterId,
        ]);
        $this->assertDatabaseHas('user_auths', [
            'user_id' => (int) $userId,
            'id_card_no' => 'ID-LEGACY-CUSTOMER-1',
        ]);
    }

    public function test_legacy_cust_save_add_persists_the_selected_customer_group(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $token = (string) random_int(100000, 999999);
        $inviterId = 987510;
        $this->seedAgent($inviterId, 'Legacy Group Selection Inviter');
        $groupName = 'LEGACY-CUSTOMER-GROUP-' . $token;
        $groupId = $this->seedGroup($groupName, 2, 1);
        $email = 'legacy-cust-group-' . $token . '@example.test';
        $this->fakeProvisioningProcessor();
        $this->ensureCustomerSequence();

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/cust_save_add', [
                'data' => [
                    'useremail' => $email,
                    'password' => 'secret123',
                    'againpassword' => 'secret123',
                    'username' => 'Legacy Group Customer',
                    'userphoneNo' => '13902' . $token,
                    'modules' => '86',
                    'userIdcardNo' => 'ID-LEGACY-GROUP-' . $token,
                    'userInviterId' => $inviterId,
                    'usergrpId' => $groupId,
                ],
                'usergrpName' => $groupName,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::CREATED);

        $userId = (int) DB::table('user_logins')->where('email', $email)->value('user_id');
        $this->assertGreaterThan(0, $userId);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'group_id' => $groupId,
            'mt4_group' => $groupName,
            'account_type' => 2,
        ]);
    }

    public function test_legacy_cust_save_add_rejects_mismatched_group_id_and_name_before_registration(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $token = (string) random_int(100000, 999999);
        $inviterId = 987511;
        $this->seedAgent($inviterId, 'Legacy Invalid Group Inviter');
        $submittedGroupId = $this->seedGroup('LEGACY-GROUP-ID-' . $token, 2, 1);
        $submittedGroupName = 'LEGACY-GROUP-NAME-' . $token;
        $this->seedGroup($submittedGroupName, 2, 1);
        $email = 'legacy-cust-group-mismatch-' . $token . '@example.test';
        $this->fakeProvisioningProcessor();
        $this->ensureCustomerSequence();

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/cust_save_add', [
                'data' => [
                    'useremail' => $email,
                    'password' => 'secret123',
                    'againpassword' => 'secret123',
                    'username' => 'Legacy Invalid Group Customer',
                    'userphoneNo' => '13903' . $token,
                    'modules' => '86',
                    'userIdcardNo' => 'ID-LEGACY-GROUP-MISMATCH-' . $token,
                    'userInviterId' => $inviterId,
                    'usergrpId' => $submittedGroupId,
                ],
                'usergrpName' => $submittedGroupName,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseMissing('user_logins', ['email' => $email]);
    }

    public function test_legacy_cust_save_add_rejects_disabled_customer_group_before_registration(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $token = (string) random_int(100000, 999999);
        $inviterId = 987512;
        $this->seedAgent($inviterId, 'Legacy Disabled Group Inviter');
        $groupName = 'LEGACY-DISABLED-CUSTOMER-GROUP-' . $token;
        $groupId = $this->seedGroup($groupName, 2, 0);
        $email = 'legacy-cust-disabled-group-' . $token . '@example.test';
        $this->fakeProvisioningProcessor();
        $this->ensureCustomerSequence();

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/cust_save_add', [
                'useremail' => $email,
                'password1' => 'secret123',
                'againpassword' => 'secret123',
                'username' => 'Legacy Disabled Group Customer',
                'userphoneNo' => '13904' . $token,
                'modules' => '86',
                'userIdcardNo' => 'ID-LEGACY-DISABLED-GROUP-' . $token,
                'userInviterId' => $inviterId,
                'usergrpId' => $groupId,
                'usergrpName' => $groupName,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseMissing('user_logins', ['email' => $email]);
    }

    public function test_legacy_cust_save_add_rejects_agent_group_before_registration(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $token = (string) random_int(100000, 999999);
        $inviterId = 987513;
        $this->seedAgent($inviterId, 'Legacy Wrong Category Group Inviter');
        $groupName = 'LEGACY-AGENT-GROUP-' . $token;
        $groupId = $this->seedGroup($groupName, 1, 1);
        $email = 'legacy-cust-agent-group-' . $token . '@example.test';
        $this->fakeProvisioningProcessor();
        $this->ensureCustomerSequence();

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/cust_save_add', [
                'useremail' => $email,
                'password1' => 'secret123',
                'againpassword' => 'secret123',
                'username' => 'Legacy Wrong Category Customer',
                'userphoneNo' => '13905' . $token,
                'modules' => '86',
                'userIdcardNo' => 'ID-LEGACY-AGENT-GROUP-' . $token,
                'userInviterId' => $inviterId,
                'usergrpId' => $groupId,
                'usergrpName' => $groupName,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseMissing('user_logins', ['email' => $email]);
    }

    public function test_legacy_cust_save_add_without_inviter_fails_closed(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/cust_save_add', [
                'useremail' => 'legacy-cust-no-inviter-' . time() . '@example.test',
                'password1' => 'secret123',
                'againpassword' => 'secret123',
                'username' => 'Legacy No Inviter Customer',
                'userphoneNo' => '13900139001',
                'modules' => '86',
                'userIdcardNo' => 'ID-LEGACY-CUSTOMER-2',
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    public function test_legacy_cust_save_info_updates_profile_via_nested_data_payload(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $userId = 987502;
        $this->seedUser($userId, 'Before Legacy Save Info', '18850200001', 'before-legacy-save-info@example.test');

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/cust_save_info', [
                'data' => [
                    'userId' => $userId,
                    'username' => 'After Legacy Save Info',
                    'userphoneNo' => '18850200999',
                    'modules' => '86',
                    'useremail' => 'After-Legacy-Save-Info@Example.Test',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'user_name' => 'After Legacy Save Info',
            'phone' => '86-18850200999',
        ]);
        $this->assertDatabaseHas('user_logins', [
            'user_id' => $userId,
            'email' => 'after-legacy-save-info@example.test',
        ]);
    }

    public function test_legacy_cust_save_info_persists_the_valid_selected_customer_group_after_mt4_sync(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $token = (string) random_int(100000, 999999);
        $userId = 987503;
        $oldGroupName = 'LEGACY-DETAIL-OLD-GROUP-' . $token;
        $targetGroupName = 'LEGACY-DETAIL-TARGET-GROUP-' . $token;
        $oldGroupId = $this->seedGroup($oldGroupName, 2, 1, 0);
        $targetGroupId = $this->seedGroup($targetGroupName, 2, 1, 1);
        $this->seedUser($userId, 'Before Legacy Group Save', '18850300001', 'legacy-group-save-' . $token . '@example.test');
        DB::table('user_infos')->where('user_id', $userId)->update([
            'group_id' => $oldGroupId,
            'mt4_group' => $oldGroupName,
            'is_ecn' => 0,
            'leverage' => 100,
        ]);

        $calls = [];
        $this->bindTradingProfileMt4($calls, true);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/cust_save_info', [
                'data' => [
                    'userId' => $userId,
                    'usergrpId' => $targetGroupId,
                ],
                'usergrpName' => $targetGroupName,
                'is_enc' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $this->assertSame([[
            'user_id' => $userId,
            'group' => $targetGroupName,
            'leverage' => 200,
        ]], $calls);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'group_id' => $targetGroupId,
            'mt4_group' => $targetGroupName,
            'is_ecn' => 1,
            'leverage' => 200,
        ]);
    }

    public function test_legacy_cust_save_info_rejects_mismatched_customer_group_id_and_name_before_mt4_sync(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $token = (string) random_int(100000, 999999);
        $userId = 987504;
        $oldGroupName = 'LEGACY-DETAIL-MISMATCH-OLD-' . $token;
        $submittedGroupName = 'LEGACY-DETAIL-MISMATCH-NAME-' . $token;
        $oldGroupId = $this->seedGroup($oldGroupName, 2, 1);
        $submittedGroupId = $this->seedGroup('LEGACY-DETAIL-MISMATCH-ID-' . $token, 2, 1);
        $this->seedGroup($submittedGroupName, 2, 1);
        $this->seedUser($userId, 'Before Legacy Mismatch Save', '18850400001', 'legacy-group-mismatch-' . $token . '@example.test');
        DB::table('user_infos')->where('user_id', $userId)->update([
            'group_id' => $oldGroupId,
            'mt4_group' => $oldGroupName,
            'is_ecn' => 0,
            'leverage' => 100,
        ]);

        $calls = [];
        $this->bindTradingProfileMt4($calls, true);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/cust_save_info', [
                'data' => [
                    'userId' => $userId,
                    'usergrpId' => $submittedGroupId,
                ],
                'usergrpName' => $submittedGroupName,
                'is_enc' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertSame([], $calls);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'group_id' => $oldGroupId,
            'mt4_group' => $oldGroupName,
            'is_ecn' => 0,
            'leverage' => 100,
        ]);
    }

    public function test_legacy_cust_save_info_rejects_disabled_customer_group_before_mt4_sync(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $token = (string) random_int(100000, 999999);
        $userId = 987505;
        $oldGroupName = 'LEGACY-DETAIL-DISABLED-OLD-' . $token;
        $targetGroupName = 'LEGACY-DETAIL-DISABLED-TARGET-' . $token;
        $oldGroupId = $this->seedGroup($oldGroupName, 2, 1);
        $targetGroupId = $this->seedGroup($targetGroupName, 2, 0);
        $this->seedUser($userId, 'Before Legacy Disabled Save', '18850500001', 'legacy-group-disabled-' . $token . '@example.test');
        DB::table('user_infos')->where('user_id', $userId)->update([
            'group_id' => $oldGroupId,
            'mt4_group' => $oldGroupName,
            'is_ecn' => 0,
            'leverage' => 100,
        ]);

        $calls = [];
        $this->bindTradingProfileMt4($calls, true);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/cust_save_info', [
                'data' => [
                    'userId' => $userId,
                    'usergrpId' => $targetGroupId,
                ],
                'usergrpName' => $targetGroupName,
                'is_enc' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertSame([], $calls);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'group_id' => $oldGroupId,
            'mt4_group' => $oldGroupName,
        ]);
    }

    public function test_legacy_cust_save_info_rejects_agent_group_before_mt4_sync(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $token = (string) random_int(100000, 999999);
        $userId = 987506;
        $oldGroupName = 'LEGACY-DETAIL-AGENT-OLD-' . $token;
        $targetGroupName = 'LEGACY-DETAIL-AGENT-TARGET-' . $token;
        $oldGroupId = $this->seedGroup($oldGroupName, 2, 1);
        $targetGroupId = $this->seedGroup($targetGroupName, 1, 1);
        $this->seedUser($userId, 'Before Legacy Agent Group Save', '18850600001', 'legacy-agent-group-save-' . $token . '@example.test');
        DB::table('user_infos')->where('user_id', $userId)->update([
            'group_id' => $oldGroupId,
            'mt4_group' => $oldGroupName,
            'is_ecn' => 0,
            'leverage' => 100,
        ]);

        $calls = [];
        $this->bindTradingProfileMt4($calls, true);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/cust_save_info', [
                'data' => [
                    'userId' => $userId,
                    'usergrpId' => $targetGroupId,
                ],
                'usergrpName' => $targetGroupName,
                'is_enc' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertSame([], $calls);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'group_id' => $oldGroupId,
            'mt4_group' => $oldGroupName,
        ]);
    }

    public function test_legacy_cust_save_info_rejects_customer_group_change_when_open_order_exists(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $token = (string) random_int(100000, 999999);
        $userId = 987507;
        $oldGroupName = 'LEGACY-DETAIL-OPEN-OLD-' . $token;
        $targetGroupName = 'LEGACY-DETAIL-OPEN-TARGET-' . $token;
        $oldGroupId = $this->seedGroup($oldGroupName, 2, 1);
        $targetGroupId = $this->seedGroup($targetGroupName, 2, 1);
        $this->seedUser($userId, 'Before Legacy Open Order Save', '18850700001', 'legacy-open-order-save-' . $token . '@example.test');
        DB::table('user_infos')->where('user_id', $userId)->update([
            'group_id' => $oldGroupId,
            'mt4_group' => $oldGroupName,
            'is_ecn' => 0,
            'leverage' => 100,
        ]);
        DB::table('user_trades')->insert([
            'user_id' => $userId,
            'ticket' => 700000 + (int) substr($token, -5),
            'symbol' => 'EURUSD',
            'digits' => 5,
            'cmd' => 0,
            'volume' => 100,
            'open_time' => '2026-08-16 10:00:00',
            'open_price' => 1.10000,
            'close_time' => '1970-01-01 00:00:00',
            'modify_time' => '2026-08-16 10:00:00',
        ]);

        $calls = [];
        $this->bindTradingProfileMt4($calls, true);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/cust_save_info', [
                'data' => [
                    'userId' => $userId,
                    'usergrpId' => $targetGroupId,
                ],
                'usergrpName' => $targetGroupName,
                'is_enc' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED);

        $this->assertSame([], $calls);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'group_id' => $oldGroupId,
            'mt4_group' => $oldGroupName,
            'leverage' => 100,
        ]);
    }

    public function test_legacy_cust_save_info_without_user_id_fails_closed(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->postJson('/index/admin/cust/cust_save_info', [
                'data' => [
                    'username' => 'No User Id Update',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
    }

    /**
     * 安装内存 MT4 Manager，并返回实例供测试断言真实调用次数。
     *
     * @return object 带 registerCalls 计数器的内存 Manager。
     */
    private function fakeProvisioningProcessor(): object
    {
        $manager = new class extends Mt4ManagerService {
            /** @var int 注册接口调用次数。 */
            public $registerCalls = 0;

            public function __construct()
            {
            }

            public function registerUser($data)
            {
                $this->registerCalls++;

                return [
                    'status' => 'ok',
                    'err' => '0',
                    'acc' => (string) ($data['user_id'] ?? ''),
                    'ticket' => 'T-' . ($data['user_id'] ?? '0'),
                ];
            }

            public function getAccountInfo($userId)
            {
                return [
                    'status' => 'ok',
                    'err' => '0',
                    'acc' => (string) $userId,
                    'bal' => '0.00',
                    'ena' => '1',
                    'grp' => 'default',
                ];
            }
        };

        $gateway = new Mt4UserProvisioningGateway($manager);
        $this->app->instance(
            UserRegistrationService::class,
            new UserRegistrationService(new UserMt4ProvisioningProcessor($gateway))
        );

        return $manager;
    }

    private function bindTradingProfileMt4(array &$calls, bool $ok): void
    {
        $this->app->instance(Mt4ManagerService::class, new class($calls, $ok) extends Mt4ManagerService {
            /**
             * MT4 替身的调用捕获表。记录 updateUserTradingProfile 的入参与改前组/杠杆，断言代理保存的同步指令。
             * @var array<int, array<string, mixed>>
             */
            private $calls;
            /**
             * MT4 替身的成功开关。false 返回连接失败，验证本地保存回滚。
             * @var bool
             */
            private $ok;

            public function __construct(array &$calls, bool $ok)
            {
                $this->calls = &$calls;
                $this->ok = $ok;
                parent::__construct('127.0.0.1', 0, 'test-key', '1', 1);
            }

            public function updateUserTradingProfile($userId, $group, $leverage)
            {
                $this->calls[] = [
                    'user_id' => (int) $userId,
                    'group' => (string) $group,
                    'leverage' => (int) $leverage,
                ];

                return $this->ok
                    ? ['status' => 'ok', 'err' => '0', 'message' => 'updated', 'data' => []]
                    : ['status' => 'error', 'error_code' => 'connection_failed', 'message' => 'failed', 'data' => []];
            }
        });
    }

    private function ensureCustomerSequence(): void
    {
        $now = time();

        DB::table('id_sequences')->updateOrInsert(
            ['type' => 'customer'],
            ['current_value' => 987900, 'prefix' => '', 'step' => 1, 'created_at' => $now, 'updated_at' => $now]
        );
    }

    private function seedGroup(string $name, int $category, int $isEnabled, int $isEcn = 0): int
    {
        $now = time();

        return (int) DB::table('group_configs')->insertGetId([
            'name' => $name,
            'radix' => 50,
            'category' => $category,
            'has_commission' => 0,
            'is_enabled' => $isEnabled,
            'is_ecn' => $isEcn,
            'is_default' => 0,
            'created_by' => 1,
            'updated_by' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function seedAgent(int $userId, string $userName): void
    {
        $this->seedUser($userId, $userName, '18850100001', 'legacy-save-inviter-' . $userId . '@example.test', 1);
    }

    private function seedUser(int $userId, string $userName, string $phone, string $email, int $accountType = 2): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => $email,
            'password' => bcrypt('password'),
            'account_type' => $accountType,
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
            'phone' => $phone,
            'account_type' => $accountType,
            'parent_id' => 0,
            'level_id' => $accountType === 1 ? 2 : 0,
            'comm_rate' => $accountType === 1 ? 20 : 0,
            'auth_status' => 1,
            'total_funds' => 0,
            'equity' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
