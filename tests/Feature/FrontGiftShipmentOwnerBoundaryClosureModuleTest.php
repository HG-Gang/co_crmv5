<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 15:05
 */

/**
 * 前台礼品发货记录所有者边界闭合测试。
 *
 * 文件功能：
 * - 验证现代接口 GET /api/front/gifts 与旧接口 POST /user/gift/search
 *   都只返回当前登录用户的发货记录，即使收件人与礼品名与其他用户完全相同。
 * - 验证 points_cost 非严格数值（如 100abc）在现代礼品列表中触发校验失败
 *   （code = VALIDATION_FAILED）且不返回任何礼品。
 * - 验证以上边界已登记在 docs/admin-backend-blade-permission-final-checklist.md
 *   （第 218 项发货所有者边界、第 345 项 points_cost）。
 *
 * 适用场景：
 * - 回归 GiftController::giftList 与 giftSearch 的所有者过滤，防止发货历史越权。
 *
 * 入参例子：
 * - viewer 与 other 用户插入收件人、礼品名相同但 tracking_number 不同的发货记录，
 *   viewer 登录后携带 recipient_name/gift_name/startdate/enddate/limit 查询，
 *   断言只返回 viewer 的 tracking_number。
 *
 * 返回值：
 * - 测试无返回值；断言 data.shipped_gifts.data（或 data.list.data）数量为 1
 *   且不含其他用户 tracking_number 即表示闭环。
 *
 * 异常或失败场景：
 * - 断言失败意味着发货记录所有者过滤失效（返回了其他用户记录，越权），
 *   或 points_cost 被宽松解析放行，需要立即排查。
 */
namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FrontGiftShipmentOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_modern_gift_list_only_returns_current_users_shipment_history(): void
    {
        $viewerId = 412180100;
        $otherId = 412180101;

        $this->deleteFixtureRows([$viewerId, $otherId]);
        $this->insertUserInfo($viewerId, 'gift-shipment-boundary-viewer', 2);
        $this->insertUserInfo($otherId, 'gift-shipment-boundary-other', 2);

        $ownTracking = 'FGS-218-MODERN-OWN';
        $otherTracking = 'FGS-218-MODERN-OTHER';
        $this->insertShipment($viewerId, 'Boundary Shared Recipient', 'Boundary Thermos', $ownTracking);
        $this->insertShipment($otherId, 'Boundary Shared Recipient', 'Boundary Thermos', $otherTracking);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $query = http_build_query([
            'recipient_name' => 'Boundary Shared',
            'gift_name' => 'Boundary Thermos',
            'startdate' => '2026-07-01',
            'enddate' => '2026-07-31',
            'limit' => 10,
        ]);

        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/gifts?' . $query);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonCount(1, 'data.shipped_gifts.data')
            // 旧兼容 JSON 契约中 user_id 为字符串（BIGINT + EMULATE_PREPARES）。
            ->assertJsonPath('data.shipped_gifts.data.0.user_id', (string) $viewerId)
            ->assertJsonPath('data.shipped_gifts.data.0.tracking_number', $ownTracking);

        $this->assertStringNotContainsString($otherTracking, $response->getContent());
    }

    public function test_legacy_gift_search_only_returns_current_users_shipment_history(): void
    {
        $viewerId = 412180200;
        $otherId = 412180201;

        $this->deleteFixtureRows([$viewerId, $otherId]);
        $this->insertUserInfo($viewerId, 'gift-shipment-legacy-boundary-viewer', 2);
        $this->insertUserInfo($otherId, 'gift-shipment-legacy-boundary-other', 2);

        $ownTracking = 'FGS-218-LEGACY-OWN';
        $otherTracking = 'FGS-218-LEGACY-OTHER';
        $this->insertShipment($viewerId, 'Legacy Shared Recipient', 'Legacy Boundary Thermos', $ownTracking);
        $this->insertShipment($otherId, 'Legacy Shared Recipient', 'Legacy Boundary Thermos', $otherTracking);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->postJson('/user/gift/search', [
                'recipient_name' => 'Legacy Shared',
                'gift_name' => 'Legacy Boundary Thermos',
                'startdate' => '2026-07-01',
                'enddate' => '2026-07-31',
                'limit' => 10,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonCount(1, 'data.list.data')
            // 旧兼容 JSON 契约中 user_id 为字符串（BIGINT + EMULATE_PREPARES）。
            ->assertJsonPath('data.list.data.0.user_id', (string) $viewerId)
            ->assertJsonPath('data.list.data.0.tracking_number', $ownTracking);

        $this->assertStringNotContainsString($otherTracking, $response->getContent());
    }

    public function test_modern_gift_list_rejects_non_strict_points_cost_filter(): void
    {
        $viewerId = 412180300;
        $giftName = 'Hidden Points Gift 412180300';

        $this->deleteFixtureRows([$viewerId]);
        DB::table('gift_items')->where('name', $giftName)->delete();
        $this->insertUserInfo($viewerId, 'gift-points-filter-viewer', 2);
        $now = time();
        DB::table('gift_items')->insert([
            'name' => $giftName,
            'description' => 'Must not be returned for a non-strict points filter',
            'points_cost' => 100,
            'stock_quantity' => 5,
            'status' => 1,
            'image_url' => '',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/gifts?points_cost=100abc');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        $this->assertStringNotContainsString($giftName, $response->getContent());
    }

    public function test_final_checklist_records_gift_shipment_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 218.', $checklist);
        $this->assertStringContainsString('GiftController::giftList', $checklist);
        $this->assertStringContainsString('GiftController::giftSearch', $checklist);
        $this->assertStringContainsString('/api/front/gifts', $checklist);
        $this->assertStringContainsString('user/gift/search', $checklist);
        $this->assertStringContainsString('FrontGiftShipmentOwnerBoundaryClosureModuleTest', $checklist);
        $this->assertStringContainsString('## 345.', $checklist);
        $this->assertStringContainsString('points_cost', $checklist);
    }

    private function insertUserInfo(int $userId, string $userName, int $accountType): void
    {
        $now = time();

        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_logins')->where('user_id', $userId)->delete();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => 'front-gift-shipment-boundary-' . $userId . '@example.test',
            'password' => Hash::make('password'),
            'account_type' => $accountType,
            'role_id' => 0,
            'is_enabled' => 1,
            'is_cancelled' => 0,
            'source_type' => 0,
            'jwt_token_id' => '',
            'last_login_ip' => '',
            'last_login_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);

        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => $loginId,
            'user_name' => $userName,
            'phone' => '1782180' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => $accountType,
            'parent_id' => 0,
            'family_tree' => '',
            'group_id' => 0,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 1,
            'total_funds' => 0,
            'used_margin' => 0,
            'avail_margin' => 0,
            'equity' => 0,
            'effective_credit' => 0,
            'risk_ratio' => 0,
            'leverage' => 100,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    private function insertShipment(int $userId, string $recipientName, string $giftName, string $trackingNumber): int
    {
        $now = time();

        return (int) DB::table('gift_shipments')->insertGetId([
            'user_id' => $userId,
            'address_id' => 0,
            'recipient_name' => $recipientName,
            'recipient_phone' => '1882180' . substr((string) $userId, -4),
            'recipient_address' => 'Gift Boundary Address ' . $userId,
            'sender_name' => 'Ops',
            'tracking_number' => $trackingNumber,
            'gift_name' => $giftName,
            'gift_quantity' => 1,
            'status' => 1,
            'remark' => 'gift shipment owner boundary',
            'admin_id' => 0,
            'shipped_at' => '2026-07-12 10:30:00',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * @param array<int, int> $userIds
     */
    private function deleteFixtureRows(array $userIds): void
    {
        DB::table('gift_shipments')->whereIn('user_id', $userIds)->delete();
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
    }
}
