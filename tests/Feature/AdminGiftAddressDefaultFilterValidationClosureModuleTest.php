<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:46
 */

/**
 * 文件功能：验证礼品地址列表（giftAddressList）对 is_default 筛选值的严格校验，
 *           非法筛选值不得返回任何地址，并核对最终检查清单文档。
 *
 * 适用场景：后台 /api/admin/giftAddressList 接口的筛选参数校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/giftAddressList：{is_default, limit}
 *
 * 返回值：
 * - is_default 带非数字后缀时返回 code=VALIDATION_FAILED，响应不含任何地址数据。
 *
 * 异常或失败场景：
 * - 非严格数字 is_default（如 '1abc'）时校验失败，不返回地址与用户信息。
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

class AdminGiftAddressDefaultFilterValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 礼赠地址默认过滤校验用例的夹具业务用户 ID。
     * @var int
     */
    private const TEST_USER_ID = 983830;
    /**
     * 夹具用户的 user_name 标记。断言返回记录归属正确用户。
     * @var string
     */
    private const TEST_USER_NAME = 'Gift Address Default Validation User';
    /**
     * 夹具收货地址的收件人标记。断言默认地址过滤命中正确地址。
     * @var string
     */
    private const TEST_RECIPIENT_NAME = 'Gift Address Default Validation Recipient';

    protected function tearDown(): void
    {
        DB::table('user_addresses')->where('user_id', self::TEST_USER_ID)->delete();
        DB::table('user_infos')->where('user_id', self::TEST_USER_ID)->delete();

        parent::tearDown();
    }

    // 礼品地址列表应拒绝非严格 is_default 筛选值且不返回地址。
    public function test_gift_address_list_rejects_non_strict_is_default_filter_without_returning_address(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createGiftAddress();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/giftAddressList', [
                'is_default' => '1abc',
                'limit' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertStringNotContainsString(self::TEST_USER_NAME, $response->getContent());
        $this->assertStringNotContainsString(self::TEST_RECIPIENT_NAME, $response->getContent());
    }

    // 核对最终检查清单文档记录了礼品地址默认筛选校验边界。
    public function test_final_checklist_records_gift_address_default_filter_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 331.', $checklist);
        $this->assertStringContainsString('GiftController::addressList', $checklist);
        $this->assertStringContainsString('/api/admin/giftAddressList', $checklist);
        $this->assertStringContainsString('user_addresses.is_default', $checklist);
        $this->assertStringContainsString('AdminGiftAddressDefaultFilterValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-gift-address-default-super',
                'email' => 'admin-gift-address-default-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createGiftAddress(): void
    {
        $now = time();

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

        DB::table('user_addresses')->insert([
            'user_id' => self::TEST_USER_ID,
            'recipient_name' => self::TEST_RECIPIENT_NAME,
            'recipient_phone' => '13800003830',
            'recipient_address' => 'Gift Address Default Validation Address',
            'is_default' => 1,
            'created_at' => $now - 1200,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
