<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:46
 */

/**
 * 文件功能：验证礼品发货列表、导出、地址列表接口对 user_id 筛选值的严格校验，
 *           非法筛选值不得返回任何礼品数据，并核对最终检查清单文档。
 *
 * 适用场景：后台 /api/admin/giftShipmentList、exportGiftShipments、
 *           giftAddressList 接口的筛选参数校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/giftShipmentList：{user_id, limit}
 * - POST /api/admin/exportGiftShipments：{user_id}
 * - POST /api/admin/giftAddressList：{user_id, limit}
 *
 * 返回值：
 * - user_id 带非数字后缀时返回 code=VALIDATION_FAILED，响应不含发货/地址数据。
 *
 * 异常或失败场景：
 * - 非严格数字 user_id（如 '983771abc'）时校验失败，不返回任何礼品记录。
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

class AdminGiftUserIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 礼赠 user_id 校验用例的夹具业务用户 ID。验证列表按 user_id 过滤拒绝非数字输入。
     * @var int
     */
    private const TEST_USER_ID = 983771;
    /**
     * 夹具用户的 user_name 标记。断言返回记录归属正确用户。
     * @var string
     */
    private const TEST_USER_NAME = 'Gift User ID Validation User';
    /**
     * 夹具礼赠发货记录名称标记。断言过滤命中正确发货单。
     * @var string
     */
    private const TEST_GIFT_NAME = 'Gift User ID Validation Box';
    /**
     * 夹具发货单的运单号标记。断言记录内容与过滤条件一致。
     * @var string
     */
    private const TEST_TRACKING_NUMBER = 'GIFT-USER-ID-VALIDATION-983771';
    /**
     * 夹具收件人姓名标记。断言收件人字段过滤正确。
     * @var string
     */
    private const TEST_RECIPIENT_NAME = 'Gift User ID Validation Recipient';

    protected function tearDown(): void
    {
        DB::table('gift_shipments')->where('user_id', self::TEST_USER_ID)->delete();
        DB::table('user_addresses')->where('user_id', self::TEST_USER_ID)->delete();
        DB::table('user_infos')->where('user_id', self::TEST_USER_ID)->delete();

        parent::tearDown();
    }

    // 发货列表应拒绝非严格 user_id 筛选值且不返回发货单。
    public function test_gift_shipment_list_rejects_non_strict_user_id_filter_without_returning_shipment(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createGiftUserRecords($actor);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/giftShipmentList', [
                'user_id' => self::TEST_USER_ID . 'abc',
                'limit' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertStringNotContainsString(self::TEST_GIFT_NAME, $response->getContent());
        $this->assertStringNotContainsString(self::TEST_TRACKING_NUMBER, $response->getContent());
        $this->assertStringNotContainsString(self::TEST_RECIPIENT_NAME, $response->getContent());
    }

    // 发货导出应拒绝非严格 user_id 筛选值且不返回 CSV。
    public function test_gift_shipment_export_rejects_non_strict_user_id_filter_without_returning_csv(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createGiftUserRecords($actor);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/exportGiftShipments', [
                'user_id' => self::TEST_USER_ID . 'abc',
            ]);

        $response->assertOk();
        $this->assertStringNotContainsString('text/csv', (string) $response->headers->get('content-type'));
        $response->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        $this->assertStringNotContainsString(self::TEST_GIFT_NAME, $response->getContent());
        $this->assertStringNotContainsString(self::TEST_TRACKING_NUMBER, $response->getContent());
    }

    // 地址列表应拒绝非严格 user_id 筛选值且不返回地址。
    public function test_gift_address_list_rejects_non_strict_user_id_filter_without_returning_address(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createGiftUserRecords($actor);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/giftAddressList', [
                'user_id' => self::TEST_USER_ID . 'abc',
                'limit' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertStringNotContainsString(self::TEST_USER_NAME, $response->getContent());
        $this->assertStringNotContainsString(self::TEST_RECIPIENT_NAME, $response->getContent());
    }

    // 核对最终检查清单文档记录了礼品 user_id 校验边界。
    public function test_final_checklist_records_gift_user_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 323.', $checklist);
        $this->assertStringContainsString('GiftController::shipmentList', $checklist);
        $this->assertStringContainsString('GiftController::exportGiftShipments', $checklist);
        $this->assertStringContainsString('GiftController::addressList', $checklist);
        $this->assertStringContainsString('/api/admin/giftShipmentList', $checklist);
        $this->assertStringContainsString('/api/admin/exportGiftShipments', $checklist);
        $this->assertStringContainsString('/api/admin/giftAddressList', $checklist);
        $this->assertStringContainsString('gift_shipments.user_id', $checklist);
        $this->assertStringContainsString('user_addresses.user_id', $checklist);
        $this->assertStringContainsString('AdminGiftUserIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-gift-user-id-super',
                'email' => 'admin-gift-user-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createGiftUserRecords(Admin $admin): void
    {
        $now = time();

        DB::table('gift_shipments')->where('user_id', self::TEST_USER_ID)->delete();
        DB::table('user_addresses')->where('user_id', self::TEST_USER_ID)->delete();
        DB::table('user_infos')->where('user_id', self::TEST_USER_ID)->delete();

        DB::table('user_infos')->insert([
            'user_id' => self::TEST_USER_ID,
            'login_id' => 0,
            'user_name' => self::TEST_USER_NAME,
            'phone' => '',
            'gender' => 1,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => (string) self::TEST_USER_ID,
            'is_gift_allowed' => 1,
            'total_funds' => 0,
            'equity' => 0,
            'effective_credit' => 0,
            'created_at' => $now - 3600,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $addressId = DB::table('user_addresses')->insertGetId([
            'user_id' => self::TEST_USER_ID,
            'recipient_name' => self::TEST_RECIPIENT_NAME,
            'recipient_phone' => '13800003771',
            'recipient_address' => 'Gift User ID Validation Address',
            'is_default' => 1,
            'created_at' => $now - 1200,
            'updated_at' => $now - 600,
            'deleted_at' => null,
        ]);

        DB::table('gift_shipments')->insert([
            'user_id' => self::TEST_USER_ID,
            'address_id' => $addressId,
            'recipient_name' => self::TEST_RECIPIENT_NAME,
            'recipient_phone' => '13800003771',
            'recipient_address' => 'Gift User ID Validation Address',
            'sender_name' => 'Gift User ID Validation Sender',
            'tracking_number' => self::TEST_TRACKING_NUMBER,
            'gift_name' => self::TEST_GIFT_NAME,
            'gift_quantity' => 1,
            'status' => 1,
            'remark' => 'gift user id validation fixture',
            'admin_id' => $admin->id,
            'shipped_at' => date('Y-m-d H:i:s', $now - 300),
            'created_at' => $now - 300,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
