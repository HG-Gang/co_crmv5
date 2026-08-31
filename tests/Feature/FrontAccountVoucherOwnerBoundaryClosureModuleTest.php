<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:51
 */

/**
 * 前台账户凭证（Voucher）归属边界闭环测试。
 *
 * 文件功能：
 * - 验证现代与旧版凭证提交接口都忽略伪造的 user_id/userId，只创建当前登录用户的凭证。
 * - 验证凭证列表忽略伪造 user_id 只返回当前用户记录，并拒绝非严格/不支持的 review_status。
 * - 验证最终清单文档已记录凭证归属边界与状态校验边界。
 *
 * 适用场景：
 * - 前台账户凭证模块越权与伪造入参的回归测试。
 *
 * 入参例子：
 * - POST /api/front/account/voucher-submissions
 *   images: [文件], remarks: Viewer modern voucher, user_id: {otherId}（伪造）
 * - GET /api/front/account/vouchers?review_status=1abc
 *
 * 返回值：
 * - 提交成功 code 为 SUCCESS，图片存到 vouchers/{当前用户}/ 目录；列表只含当前用户记录。
 * - 非法 review_status 返回 VALIDATION_FAILED。
 *
 * 异常或失败场景：
 * - 若伪造 user_id 生效、图片归属错误或非法状态被放行，断言失败。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FrontAccountVoucherOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 验证现代凭证提交接口忽略伪造 user_id 只创建当前用户记录。
     */
    public function test_modern_voucher_submit_ignores_spoofed_user_id_and_creates_current_user_record_only(): void
    {
        $viewerId = 412340100;
        $otherId = 412340101;
        $viewerEmail = 'front-voucher-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-voucher-boundary-' . $otherId . '@example.test';

        Storage::fake('public');
        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->insertUserInfo($viewerId, 'voucher-boundary-viewer', $viewerEmail);
        $this->insertUserInfo($otherId, 'voucher-boundary-other', $otherEmail);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->post('/api/front/account/voucher-submissions', [
                'images' => [
                    UploadedFile::fake()->image('viewer-voucher.jpg', 32, 32),
                ],
                'remarks' => 'Viewer modern voucher',
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS);

        $voucher = DB::table('voucher_infos')
            ->where('user_id', $viewerId)
            ->where('remarks', 'Viewer modern voucher')
            ->first();
        $this->assertNotNull($voucher);
        $this->assertSame(0, (int) $voucher->review_status);
        $this->assertStringStartsWith('vouchers/' . $viewerId . '/', $voucher->images);
        Storage::disk('public')->assertExists($voucher->images);

        $this->assertDatabaseMissing('voucher_infos', [
            'user_id' => $otherId,
            'remarks' => 'Viewer modern voucher',
        ]);
    }

    /**
     * 验证旧版凭证提交接口忽略伪造 user_id 只创建当前用户记录。
     */
    public function test_legacy_voucher_submit_ignores_spoofed_user_id_and_creates_current_user_record_only(): void
    {
        $viewerId = 412340200;
        $otherId = 412340201;
        $viewerEmail = 'front-voucher-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-voucher-boundary-' . $otherId . '@example.test';

        Storage::fake('public');
        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->insertUserInfo($viewerId, 'voucher-legacy-viewer', $viewerEmail);
        $this->insertUserInfo($otherId, 'voucher-legacy-other', $otherEmail);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->post('/user/user_voucher_save', [
                'voucherimg' => UploadedFile::fake()->image('legacy-voucher.jpg', 32, 32),
                'voucherremark' => 'Viewer legacy voucher',
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'SUC');

        $voucher = DB::table('voucher_infos')
            ->where('user_id', $viewerId)
            ->where('remarks', 'Viewer legacy voucher')
            ->first();
        $this->assertNotNull($voucher);
        $this->assertSame(0, (int) $voucher->review_status);
        $this->assertStringStartsWith('vouchers/' . $viewerId . '/', $voucher->images);
        Storage::disk('public')->assertExists($voucher->images);

        $this->assertDatabaseMissing('voucher_infos', [
            'user_id' => $otherId,
            'remarks' => 'Viewer legacy voucher',
        ]);
    }

    /**
     * 验证凭证列表忽略伪造 user_id 只返回当前用户记录。
     */
    public function test_voucher_list_ignores_spoofed_user_id_and_returns_current_user_records_only(): void
    {
        $viewerId = 412340300;
        $otherId = 412340301;
        $viewerEmail = 'front-voucher-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-voucher-boundary-' . $otherId . '@example.test';

        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->insertUserInfo($viewerId, 'voucher-list-viewer', $viewerEmail);
        $this->insertUserInfo($otherId, 'voucher-list-other', $otherEmail);
        $this->insertVoucher($viewerId, 'Viewer visible voucher', 1);
        $this->insertVoucher($otherId, 'Other hidden voucher', 1);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->getJson('/api/front/account/vouchers?review_status=1&user_id=' . $otherId . '&userId=' . $otherId);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::SUCCESS)
            ->assertJsonPath('data.data.0.remarks', 'Viewer visible voucher');
        $this->assertStringContainsString('Viewer visible voucher', $response->getContent());
        $this->assertStringNotContainsString('Other hidden voucher', $response->getContent());
    }

    /**
     * 验证凭证列表在查询前拒绝非严格与不支持的 review_status。
     */
    public function test_voucher_list_rejects_non_strict_and_unsupported_review_status_before_querying_records(): void
    {
        $viewerId = 412340400;
        $viewerEmail = 'front-voucher-boundary-' . $viewerId . '@example.test';

        $this->deleteFixtureRows([$viewerId], [$viewerEmail]);
        $this->insertUserInfo($viewerId, 'voucher-status-viewer', $viewerEmail);
        $this->insertVoucher($viewerId, 'Voucher status must stay hidden', 1);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        foreach (['1abc', 3, -1] as $invalidStatus) {
            $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
                ->actingAs($login, 'user')
                ->getJson('/api/front/account/vouchers?review_status=' . urlencode((string) $invalidStatus));

            $response->assertOk()
                ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
            $this->assertStringNotContainsString('Voucher status must stay hidden', $response->getContent());
        }
    }

    /**
     * 验证最终清单文档已记录凭证归属边界与状态校验边界（## 234、## 342）。
     */
    public function test_final_checklist_records_account_voucher_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 234.', $checklist);
        $this->assertStringContainsString('AccountController::submitVoucher', $checklist);
        $this->assertStringContainsString('AccountController::userVoucherSave', $checklist);
        $this->assertStringContainsString('AccountController::voucherList', $checklist);
        $this->assertStringContainsString('/api/front/account/voucher-submissions', $checklist);
        $this->assertStringContainsString('/api/front/account/vouchers', $checklist);
        $this->assertStringContainsString('user/user_voucher_save', $checklist);
        $this->assertStringContainsString('FrontAccountVoucherOwnerBoundaryClosureModuleTest', $checklist);
        $this->assertStringContainsString('## 342.', $checklist);
        $this->assertStringContainsString('voucher_infos.review_status', $checklist);
    }

    private function insertUserInfo(int $userId, string $userName, string $email): void
    {
        $now = time();

        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $userId,
            'email' => $email,
            'password' => Hash::make('password'),
            'account_type' => 2,
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
            'phone' => '1392340' . substr((string) $userId, -4),
            'gender' => 1,
            'account_type' => 2,
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

    private function insertVoucher(int $userId, string $remarks, int $reviewStatus): void
    {
        $now = time();

        DB::table('voucher_infos')->insert([
            'user_id' => $userId,
            'images' => 'vouchers/' . $userId . '/fixture.jpg',
            'remarks' => $remarks,
            'review_status' => $reviewStatus,
            'review_message' => '',
            'created_by' => 'fixture',
            'updated_by' => 'fixture',
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * @param array<int, int> $userIds
     * @param array<int, string> $emails
     */
    private function deleteFixtureRows(array $userIds, array $emails): void
    {
        DB::table('voucher_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('email', $emails)->delete();
    }
}
