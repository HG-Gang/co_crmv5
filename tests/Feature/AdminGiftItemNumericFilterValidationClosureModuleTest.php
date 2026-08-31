<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:46
 */

/**
 * 文件功能：验证礼品商品列表（giftItemList）对 points_cost、status 数值筛选值
 *           的严格校验，非法筛选值不得返回商品，并核对最终检查清单文档。
 *
 * 适用场景：后台 /api/admin/giftItemList 接口的筛选参数校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/giftItemList：{points_cost, status, limit}
 *
 * 返回值：
 * - 非法筛选值（如 '420abc'、'1abc'）时返回 code=VALIDATION_FAILED，不含商品数据。
 *
 * 异常或失败场景：
 * - 非严格数字筛选值时校验失败，不返回任何礼品商品。
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

class AdminGiftItemNumericFilterValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 礼品项数字校验用例的夹具礼品名称标记。
     * @var string
     */
    private const TEST_GIFT_NAME = 'Gift Item Numeric Validation Thermos';
    /**
     * 夹具礼品的积分兑换价格。验证按积分价格过滤时执行数字校验。
     * @var int
     */
    private const TEST_POINTS_COST = 420;

    protected function tearDown(): void
    {
        DB::table('gift_items')->where('name', self::TEST_GIFT_NAME)->delete();

        parent::tearDown();
    }

    // 礼品商品列表应拒绝非严格 points_cost 筛选值且不返回商品。
    public function test_gift_item_list_rejects_non_strict_points_cost_filter_without_returning_item(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createGiftItem();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/giftItemList', [
                'points_cost' => self::TEST_POINTS_COST . 'abc',
                'limit' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertStringNotContainsString(self::TEST_GIFT_NAME, $response->getContent());
    }

    // 礼品商品列表应拒绝非严格 status 筛选值且不返回商品。
    public function test_gift_item_list_rejects_non_strict_status_filter_without_returning_item(): void
    {
        $actor = $this->ensureSuperAdmin();
        $this->createGiftItem();

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/giftItemList', [
                'status' => '1abc',
                'limit' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertStringNotContainsString(self::TEST_GIFT_NAME, $response->getContent());
    }

    // 核对最终检查清单文档记录了礼品商品数值筛选校验边界。
    public function test_final_checklist_records_gift_item_numeric_filter_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 325.', $checklist);
        $this->assertStringContainsString('GiftController::giftItemList', $checklist);
        $this->assertStringContainsString('/api/admin/giftItemList', $checklist);
        $this->assertStringContainsString('gift_items.points_cost', $checklist);
        $this->assertStringContainsString('gift_items.status', $checklist);
        $this->assertStringContainsString('AdminGiftItemNumericFilterValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-gift-item-numeric-super',
                'email' => 'admin-gift-item-numeric-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createGiftItem(): void
    {
        $now = time();

        DB::table('gift_items')->where('name', self::TEST_GIFT_NAME)->delete();
        DB::table('gift_items')->insert([
            'name' => self::TEST_GIFT_NAME,
            'description' => 'Gift item numeric validation fixture',
            'points_cost' => self::TEST_POINTS_COST,
            'stock_quantity' => 12,
            'status' => 1,
            'image_url' => '/images/gifts/gift-item-numeric-validation.png',
            'created_at' => $now - 3600,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
