<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:47
 */

/**
 * 前台银行卡提交属主边界闭环测试。
 *
 * 文件功能：
 * - 验证现代银行卡提交（/api/front/profile/bank-card）与遗留银行卡上传
 *   （/user/center/uploadBankCard）忽略伪造的 user_id / userId，只更新当前用户的
 *   银行卡认证信息（user_auths）。
 * - 验证权限清单文档记录了该边界闭环。
 *
 * 适用场景：
 * - 前台个人资料银行卡绑定的越权回归测试，防止通过参数覆盖他人银行卡信息。
 *
 * 入参例子：
 * - 现代字段：bank_name、bank_no、bank_addr、bank_card_img、bank_card_back_img。
 * - 遗留字段：bankclass、bankno、bankinfo、bankimg、bankimg_back。
 * - 伪造参数：user_id={otherId}&userId={otherId}。
 *
 * 返回值：
 * - 现代接口返回 code 为 UPDATED；遗留接口返回 msg 为 SUC。
 * - 当前用户 bank_status=1、图片路径位于 auth/{userId}/bank/，他人银行卡保持不变。
 *
 * 异常或失败场景：
 * - 伪造 user_id / userId 时仍只更新当前用户银行卡，不覆盖他人银行卡。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FrontProfileBankCardOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @var array<int, int>
     */
    private array $mirrorUserIds = [];

    protected function tearDown(): void
    {
        foreach ($this->mirrorUserIds as $userId) {
            File::deleteDirectory(public_path('storage/auth/' . $userId));
        }

        parent::tearDown();
    }

    // 验证现代银行卡提交忽略伪造的 user_id / userId，只更新当前用户认证信息。
    public function test_modern_bank_card_submit_ignores_spoofed_user_id_and_updates_current_auth_only(): void
    {
        $viewerId = 412320100;
        $otherId = 412320101;
        $viewerEmail = 'front-bank-card-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-bank-card-boundary-' . $otherId . '@example.test';

        $this->fakePublicStorageFor([$viewerId, $otherId]);
        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->insertUserInfo($viewerId, 'bank-card-boundary-viewer', $viewerEmail);
        $this->insertUserInfo($otherId, 'bank-card-boundary-other', $otherEmail);
        $this->insertUserAuth($otherId, 'Other Bank', '6222000000000101');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->post('/api/front/profile/bank-card', [
                'bank_name' => 'Viewer Bank',
                'bank_no' => '6222000000000100',
                'bank_addr' => 'Viewer Branch',
                'bank_card_img' => UploadedFile::fake()->image('viewer-bank-front.jpg', 32, 32),
                'bank_card_back_img' => UploadedFile::fake()->image('viewer-bank-back.jpg', 32, 32),
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $viewerAuth = DB::table('user_auths')->where('user_id', $viewerId)->first();
        $this->assertNotNull($viewerAuth);
        $this->assertSame('Viewer Bank', $viewerAuth->bank_name);
        $this->assertSame('6222000000000100', $viewerAuth->bank_no);
        $this->assertSame('Viewer Branch', $viewerAuth->bank_addr);
        $this->assertSame(1, (int) $viewerAuth->bank_status);
        $this->assertStringStartsWith('auth/' . $viewerId . '/bank/', $viewerAuth->bank_card_img);
        $this->assertStringStartsWith('auth/' . $viewerId . '/bank/', $viewerAuth->bank_card_back_img);
        Storage::disk('public')->assertExists($viewerAuth->bank_card_img);
        Storage::disk('public')->assertExists($viewerAuth->bank_card_back_img);

        $this->assertDatabaseHas('user_auths', [
            'user_id' => $otherId,
            'bank_name' => 'Other Bank',
            'bank_no' => '6222000000000101',
        ]);
        $this->assertDatabaseMissing('user_auths', [
            'user_id' => $otherId,
            'bank_no' => '6222000000000100',
        ]);
    }

    // 验证遗留银行卡上传忽略伪造的 user_id / userId，只更新当前用户认证信息。
    public function test_legacy_bank_card_upload_ignores_spoofed_user_id_and_updates_current_auth_only(): void
    {
        $viewerId = 412320200;
        $otherId = 412320201;
        $viewerEmail = 'front-bank-card-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-bank-card-boundary-' . $otherId . '@example.test';

        $this->fakePublicStorageFor([$viewerId, $otherId]);
        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->insertUserInfo($viewerId, 'bank-card-legacy-viewer', $viewerEmail);
        $this->insertUserInfo($otherId, 'bank-card-legacy-other', $otherEmail);
        $this->insertUserAuth($otherId, 'Other Legacy Bank', '6222000000000201');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->post('/user/center/uploadBankCard', [
                'bankclass' => 'Viewer Legacy Bank',
                'bankno' => '6222000000000200',
                'bankinfo' => 'Viewer Legacy Branch',
                'bankimg' => UploadedFile::fake()->image('legacy-bank-front.jpg', 32, 32),
                'bankimg_back' => UploadedFile::fake()->image('legacy-bank-back.jpg', 32, 32),
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'SUC');

        $viewerAuth = DB::table('user_auths')->where('user_id', $viewerId)->first();
        $this->assertNotNull($viewerAuth);
        $this->assertSame('Viewer Legacy Bank', $viewerAuth->bank_name);
        $this->assertSame('6222000000000200', $viewerAuth->bank_no);
        $this->assertSame('Viewer Legacy Branch', $viewerAuth->bank_addr);
        $this->assertSame(1, (int) $viewerAuth->bank_status);
        $this->assertStringStartsWith('auth/' . $viewerId . '/bank/', $viewerAuth->bank_card_img);
        $this->assertStringStartsWith('auth/' . $viewerId . '/bank/', $viewerAuth->bank_card_back_img);
        Storage::disk('public')->assertExists($viewerAuth->bank_card_img);
        Storage::disk('public')->assertExists($viewerAuth->bank_card_back_img);

        $this->assertDatabaseHas('user_auths', [
            'user_id' => $otherId,
            'bank_name' => 'Other Legacy Bank',
            'bank_no' => '6222000000000201',
        ]);
        $this->assertDatabaseMissing('user_auths', [
            'user_id' => $otherId,
            'bank_no' => '6222000000000200',
        ]);
    }

    // 校验权限清单文档记录了银行卡提交属主边界闭环。
    public function test_final_checklist_records_bank_card_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 232.', $checklist);
        $this->assertStringContainsString('ProfileController::submitBankCard', $checklist);
        $this->assertStringContainsString('ProfileController::uploadBankCard', $checklist);
        $this->assertStringContainsString('/api/front/profile/bank-card', $checklist);
        $this->assertStringContainsString('user/center/uploadBankCard', $checklist);
        $this->assertStringContainsString('FrontProfileBankCardOwnerBoundaryClosureModuleTest', $checklist);
    }

    /**
     * @param array<int, int> $userIds
     */
    private function fakePublicStorageFor(array $userIds): void
    {
        Storage::fake('public');

        foreach ($userIds as $userId) {
            $this->mirrorUserIds[] = $userId;
            File::deleteDirectory(public_path('storage/auth/' . $userId));
        }
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
            'phone' => '1392320' . substr((string) $userId, -4),
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

    private function insertUserAuth(int $userId, string $bankName, string $bankNo): void
    {
        $now = time();

        DB::table('user_auths')->insert([
            'user_id' => $userId,
            'bank_name' => $bankName,
            'bank_no' => $bankNo,
            'bank_addr' => 'Other Branch',
            'bank_card_img' => 'auth/' . $userId . '/bank/original-front.jpg',
            'bank_card_back_img' => 'auth/' . $userId . '/bank/original-back.jpg',
            'bank_status' => 2,
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
        DB::table('user_auths')->whereIn('user_id', $userIds)->delete();
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('email', $emails)->delete();
    }
}
