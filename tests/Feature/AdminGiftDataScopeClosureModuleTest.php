<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:46
 */

/**
 * 文件功能：验证礼品模块（发货列表、导出、地址列表、发礼、更新发货）在
 *           受限管理员数据范围下的隔离：范围外数据不可见、不可操作。
 *
 * 适用场景：后台 /api/admin/giftShipmentList、exportGiftShipments、
 *           giftAddressList、sendGift、updateGiftShipment/{id} 的数据范围回归测试。
 *
 * 入参例子：
 * - POST /api/admin/giftShipmentList：{limit}
 * - POST /api/admin/sendGift：{sender_name, gift_name, gift_quantity, recipients[]}
 * - POST /api/admin/updateGiftShipment/{id}：{status, tracking_number, remark}
 *
 * 返回值：
 * - 范围外数据被过滤或返回 code=PERMISSION_DENIED/DATA_NOT_FOUND，不落库；
 * - 地址归属他人时 sendGift 返回 code=DATA_NOT_FOUND 并拒绝发货。
 *
 * 异常或失败场景：
 * - 越权操作（范围外用户/发货单/他人地址）一律被拒绝，数据保持不变。
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

class AdminGiftDataScopeClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 夹具创建的后台管理员 ID。绑定受限数据范围后登录，验证礼赠列表的数据隔离。
     * @var int
     */
    private const ADMIN_ID = 987701;
    /**
     * 为 ADMIN_ID 创建的角色 ID，其 role_data_scopes 决定可见用户范围。
     * @var int
     */
    private const ROLE_ID = 987702;
    /**
     * 数据范围内的业务用户 ID，其礼赠记录对受限管理员必须可见。
     * @var int
     */
    private const VISIBLE_USER_ID = 987711;
    /**
     * 数据范围外的业务用户 ID，其礼赠记录必须被隔离。
     * @var int
     */
    private const HIDDEN_USER_ID = 987712;
    /**
     * 可见用户的 user_name 标记。用例按它断言列表中出现的是范围内数据。
     * @var string
     */
    private const VISIBLE_USER_NAME = 'Gift Scope Visible User';
    /**
     * 隐藏用户的 user_name 标记。断言受限结果中不含该用户。
     * @var string
     */
    private const HIDDEN_USER_NAME = 'Gift Scope Hidden User';
    /**
     * 范围内礼赠发货记录的名称标记，配合分页与汇总断言。
     * @var string
     */
    private const VISIBLE_GIFT_NAME = 'Gift Scope Visible Shipment';
    /**
     * 范围外语义下礼赠发货记录的名称标记，断言不出现在结果中。
     * @var string
     */
    private const HIDDEN_GIFT_NAME = 'Gift Scope Hidden Shipment';
    /**
     * 范围内礼赠收件人姓名标记，用于精确断言可见数据。
     * @var string
     */
    private const VISIBLE_RECIPIENT = 'Gift Scope Visible Recipient';
    /**
     * 范围外语义下礼赠收件人姓名标记，用于精确断言被隔离数据。
     * @var string
     */
    private const HIDDEN_RECIPIENT = 'Gift Scope Hidden Recipient';

    protected function setUp(): void
    {
        parent::setUp();
        $this->removeFixtures();
        $this->createScopedAdmin();
        $this->createUsersAndAddresses();
        $this->createShipments();
    }

    protected function tearDown(): void
    {
        $this->removeFixtures();
        parent::tearDown();
    }

    // 受限管理员的发货列表应排除数据范围外的用户发货单。
    public function test_scoped_admin_shipment_list_excludes_out_of_scope_user(): void
    {
        $response = $this->postAsScopedAdmin('/api/admin/giftShipmentList', ['limit' => 20]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);
        $rows = $response->json('data.data');
        $this->assertCount(1, $rows);
        $this->assertSame(self::VISIBLE_USER_ID, (int) $rows[0]['user_id']);
        $this->assertStringNotContainsString(self::HIDDEN_GIFT_NAME, $response->getContent());
    }

    // 受限管理员的发货导出应排除数据范围外的用户发货单。
    public function test_scoped_admin_shipment_export_excludes_out_of_scope_user(): void
    {
        $response = $this->postAsScopedAdmin('/api/admin/exportGiftShipments');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $csv = $response->streamedContent();
        $this->assertStringContainsString(self::VISIBLE_GIFT_NAME, $csv);
        $this->assertStringNotContainsString(self::HIDDEN_GIFT_NAME, $csv);
    }

    // 受限管理员的地址列表应排除数据范围外的用户地址。
    public function test_scoped_admin_address_list_excludes_out_of_scope_user(): void
    {
        $response = $this->postAsScopedAdmin('/api/admin/giftAddressList', ['limit' => 20]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::SUCCESS);
        $this->assertStringContainsString(self::VISIBLE_USER_NAME, $response->getContent());
        $this->assertStringContainsString(self::VISIBLE_RECIPIENT, $response->getContent());
        $this->assertStringNotContainsString(self::HIDDEN_USER_NAME, $response->getContent());
        $this->assertStringNotContainsString(self::HIDDEN_RECIPIENT, $response->getContent());
    }

    // 受限管理员不能向数据范围外的用户发礼。
    public function test_scoped_admin_cannot_send_gift_to_out_of_scope_user(): void
    {
        $giftName = 'Gift Scope Unauthorized Send ' . uniqid();

        $response = $this->postAsScopedAdmin('/api/admin/sendGift', [
            'sender_name' => 'Scope Sender',
            'gift_name' => $giftName,
            'gift_quantity' => 1,
            'tracking_number' => 'SCOPE-UNAUTHORIZED-SEND',
            'recipients' => [[
                'user_id' => self::HIDDEN_USER_ID,
                'address_id' => $this->hiddenAddressId(),
                'recipient_name' => self::HIDDEN_RECIPIENT,
                'recipient_phone' => '13800007712',
                'recipient_address' => 'Hidden scope address',
            ]],
        ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
        $this->assertDatabaseMissing('gift_shipments', ['gift_name' => $giftName]);
    }

    // 受限管理员不能更新数据范围外的发货单。
    public function test_scoped_admin_cannot_update_out_of_scope_shipment(): void
    {
        $shipmentId = (int) DB::table('gift_shipments')
            ->where('gift_name', self::HIDDEN_GIFT_NAME)
            ->value('id');

        $response = $this->postAsScopedAdmin('/api/admin/updateGiftShipment/' . $shipmentId, [
            'status' => 3,
            'tracking_number' => 'SCOPE-UNAUTHORIZED-UPDATE',
            'remark' => 'must remain unchanged',
        ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::PERMISSION_DENIED);
        $this->assertDatabaseHas('gift_shipments', [
            'id' => $shipmentId,
            'status' => 0,
            'tracking_number' => 'SCOPE-HIDDEN-TRACKING',
            'remark' => 'hidden shipment fixture',
        ]);
    }

    // 发礼时应拒绝使用归属其他用户的收货地址。
    public function test_send_gift_rejects_address_owned_by_another_user(): void
    {
        $giftName = 'Gift Scope Wrong Owner ' . uniqid();

        $response = $this->postAsScopedAdmin('/api/admin/sendGift', [
            'sender_name' => 'Scope Sender',
            'gift_name' => $giftName,
            'gift_quantity' => 1,
            'tracking_number' => '',
            'recipients' => [[
                'user_id' => self::VISIBLE_USER_ID,
                'address_id' => $this->hiddenAddressId(),
                'recipient_name' => self::HIDDEN_RECIPIENT,
                'recipient_phone' => '13800007712',
                'recipient_address' => 'Forged scope address',
            ]],
        ]);

        $response->assertOk()->assertJsonPath('code', ResponseCode::DATA_NOT_FOUND);
        $this->assertDatabaseMissing('gift_shipments', ['gift_name' => $giftName]);
    }

    private function postAsScopedAdmin(string $uri, array $payload = [])
    {
        return $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($this->scopedAdmin(), 'admin')->post($uri, $payload);
    }

    private function scopedAdmin(): Admin
    {
        return Admin::query()->findOrFail(self::ADMIN_ID);
    }

    private function createScopedAdmin(): void
    {
        $now = time();

        DB::table('roles')->insert([
            'id' => self::ROLE_ID,
            'name' => 'gift_scope_role_' . self::ROLE_ID,
            'guard_type' => 'admin',
            'description' => 'Gift data scope closure test role',
            'permissions' => null,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('role_data_scopes')->insert([
            'role_id' => self::ROLE_ID,
            'scope_type' => 'custom_users',
            'agent_ids' => null,
            'user_ids' => json_encode([self::VISIBLE_USER_ID]),
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
        DB::table('admins')->insert([
            'id' => self::ADMIN_ID,
            'role_id' => (string) self::ROLE_ID,
            'mobile' => null,
            'email' => 'gift-scope-' . self::ADMIN_ID . '@example.test',
            'username' => 'gift_scope_' . self::ADMIN_ID,
            'password' => Hash::make('password'),
            'login_count' => 0,
            'last_login_ip' => null,
            'last_login_at' => null,
            'last_login_address' => null,
            'status' => 1,
            'jwt_token_id' => null,
            'created_by' => 'gift-scope-test',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function createUsersAndAddresses(): void
    {
        $now = time();
        foreach ([
            self::VISIBLE_USER_ID => self::VISIBLE_USER_NAME,
            self::HIDDEN_USER_ID => self::HIDDEN_USER_NAME,
        ] as $userId => $name) {
            DB::table('user_infos')->insert([
                'user_id' => $userId,
                'login_id' => 0,
                'user_name' => $name,
                'phone' => '',
                'gender' => 1,
                'account_type' => 2,
                'parent_id' => 0,
                'family_tree' => (string) $userId,
                'is_gift_allowed' => 1,
                'total_funds' => 0,
                'equity' => 0,
                'effective_credit' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);

            DB::table('user_addresses')->insert([
                'user_id' => $userId,
                'recipient_name' => $userId === self::VISIBLE_USER_ID ? self::VISIBLE_RECIPIENT : self::HIDDEN_RECIPIENT,
                'recipient_phone' => '1380000' . substr((string) $userId, -4),
                'recipient_address' => $name . ' address',
                'is_default' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
        }
    }

    private function createShipments(): void
    {
        $now = time();
        foreach ([
            self::VISIBLE_USER_ID => [self::VISIBLE_GIFT_NAME, self::VISIBLE_RECIPIENT, 'SCOPE-VISIBLE-TRACKING', 1, 'visible shipment fixture'],
            self::HIDDEN_USER_ID => [self::HIDDEN_GIFT_NAME, self::HIDDEN_RECIPIENT, 'SCOPE-HIDDEN-TRACKING', 0, 'hidden shipment fixture'],
        ] as $userId => [$giftName, $recipient, $tracking, $status, $remark]) {
            $addressId = (int) DB::table('user_addresses')->where('user_id', $userId)->value('id');
            DB::table('gift_shipments')->insert([
                'user_id' => $userId,
                'address_id' => $addressId,
                'recipient_name' => $recipient,
                'recipient_phone' => '1380000' . substr((string) $userId, -4),
                'recipient_address' => 'Shipment ' . $userId . ' address',
                'sender_name' => 'Scope Fixture Sender',
                'tracking_number' => $tracking,
                'gift_name' => $giftName,
                'gift_quantity' => 1,
                'status' => $status,
                'remark' => $remark,
                'admin_id' => self::ADMIN_ID,
                'shipped_at' => date('Y-m-d H:i:s', $now),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
        }
    }

    private function hiddenAddressId(): int
    {
        return (int) DB::table('user_addresses')->where('user_id', self::HIDDEN_USER_ID)->value('id');
    }

    private function removeFixtures(): void
    {
        DB::table('operation_logs')->where('admin_id', self::ADMIN_ID)->delete();
        DB::table('gift_shipments')->whereIn('user_id', [self::VISIBLE_USER_ID, self::HIDDEN_USER_ID])->delete();
        DB::table('user_addresses')->whereIn('user_id', [self::VISIBLE_USER_ID, self::HIDDEN_USER_ID])->delete();
        DB::table('user_infos')->whereIn('user_id', [self::VISIBLE_USER_ID, self::HIDDEN_USER_ID])->delete();
        DB::table('role_data_scopes')->where('role_id', self::ROLE_ID)->delete();
        DB::table('admins')->where('id', self::ADMIN_ID)->delete();
        DB::table('roles')->where('id', self::ROLE_ID)->delete();
    }
}
