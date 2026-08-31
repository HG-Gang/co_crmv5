<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/28
 * Time: 22:34
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 前台银行卡换绑提交归属边界测试。
 *
 * 文件功能：
 * - 验证现代 API 与旧 URI 都只更新当前登录用户的待审核银行卡资料。
 * - 合法提交必须先具备已审核银行卡，再通过当前密码和绑定邮箱的一次性验证码。
 * - 请求体伪造 user_id 或 userId 不得改变验证主体和写入主体。
 */
class FrontProfileBankChangeSubmitOwnerBoundaryClosureModuleTest extends TestCase
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
            Cache::forget('front_profile_change_code:' . $userId);
        }

        parent::tearDown();
    }

    /**
     * 验证现代换绑接口完成业务前置校验后，只写当前用户的临时银行卡字段。
     */
    public function test_modern_bank_change_submit_ignores_spoofed_user_id_and_updates_current_tmp_auth_only(): void
    {
        $viewerId = 412330100;
        $otherId = 412330101;
        $viewerEmail = 'front-bank-change-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-bank-change-boundary-' . $otherId . '@example.test';

        $this->fakePublicStorageFor([$viewerId, $otherId]);
        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->insertUserInfo($viewerId, 'bank-change-boundary-viewer', $viewerEmail, '13923300100');
        $this->insertUserInfo($otherId, 'bank-change-boundary-other', $otherEmail, '13923300101');
        $this->insertApprovedUserAuth($viewerId);
        $this->insertUserAuth($otherId, 'Other Tmp Bank', '6222330000000101');
        Cache::put('front_profile_change_code:' . $viewerId, [
            'code' => '233100',
            'email' => $viewerEmail,
            'phone' => '13923300100',
            'type' => 'bank-change',
        ], now()->addMinutes(10));

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->post('/api/front/profile/bank-card-change', [
                'verify_phone' => '13923300100',
                'verify_email' => $viewerEmail,
                'password' => 'password',
                'verification_code' => '233100',
                'bank_name' => 'Viewer Tmp Bank',
                'bank_no' => '6222330000000100',
                'bank_addr' => 'Viewer Tmp Branch',
                'bank_card_img' => UploadedFile::fake()->image('viewer-change-front.jpg', 32, 32),
                'bank_card_back_img' => UploadedFile::fake()->image('viewer-change-back.jpg', 32, 32),
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $viewerAuth = DB::table('user_auths')->where('user_id', $viewerId)->first();
        $this->assertNotNull($viewerAuth);
        $this->assertSame('Viewer Tmp Bank', $viewerAuth->bank_name_tmp);
        $this->assertSame('6222330000000100', $viewerAuth->bank_no_tmp);
        $this->assertSame('Viewer Tmp Branch', $viewerAuth->bank_addr_tmp);
        $this->assertSame(3, (int) $viewerAuth->bank_status);
        $this->assertStringStartsWith('auth/' . $viewerId . '/bank-change/', $viewerAuth->bank_card_img_tmp);
        $this->assertStringStartsWith('auth/' . $viewerId . '/bank-change/', $viewerAuth->bank_card_back_img_tmp);
        Storage::disk('public')->assertExists($viewerAuth->bank_card_img_tmp);
        Storage::disk('public')->assertExists($viewerAuth->bank_card_back_img_tmp);
        $this->assertNull(Cache::get('front_profile_change_code:' . $viewerId));

        $this->assertDatabaseHas('user_auths', [
            'user_id' => $otherId,
            'bank_name_tmp' => 'Other Tmp Bank',
            'bank_no_tmp' => '6222330000000101',
        ]);
        $this->assertDatabaseMissing('user_auths', [
            'user_id' => $otherId,
            'bank_no_tmp' => '6222330000000100',
        ]);
    }

    /**
     * 验证旧换绑入口完成密码和验证码校验后，伪造用户 ID 仍不能改变写入对象。
     */
    public function test_legacy_bank_change_upload_ignores_spoofed_user_id_and_updates_current_tmp_auth_only(): void
    {
        $viewerId = 412330200;
        $otherId = 412330201;
        $viewerEmail = 'front-bank-change-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-bank-change-boundary-' . $otherId . '@example.test';

        $this->fakePublicStorageFor([$viewerId, $otherId]);
        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->insertUserInfo($viewerId, 'bank-change-legacy-viewer', $viewerEmail, '13923300200');
        $this->insertUserInfo($otherId, 'bank-change-legacy-other', $otherEmail, '13923300201');
        $this->insertApprovedUserAuth($viewerId);
        $this->insertUserAuth($otherId, 'Other Legacy Tmp Bank', '6222330000000201');
        Cache::put('front_profile_change_code:' . $viewerId, [
            'code' => '233200',
            'email' => $viewerEmail,
            'phone' => '13923300200',
            'type' => 'bank-change',
        ], now()->addMinutes(10));

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->post('/user/center/uploadChangeBankCard', [
                'useremail' => $viewerEmail,
                'password' => 'password',
                'userverfcode' => '233200',
                'bankclass' => 'Viewer Legacy Tmp Bank',
                'bankno' => '6222330000000200',
                'bankinfo' => 'Viewer Legacy Tmp Branch',
                'bankimg' => UploadedFile::fake()->image('legacy-change-front.jpg', 32, 32),
                'bankimg_back' => UploadedFile::fake()->image('legacy-change-back.jpg', 32, 32),
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'SUC');

        $viewerAuth = DB::table('user_auths')->where('user_id', $viewerId)->first();
        $this->assertNotNull($viewerAuth);
        $this->assertSame('Viewer Legacy Tmp Bank', $viewerAuth->bank_name_tmp);
        $this->assertSame('6222330000000200', $viewerAuth->bank_no_tmp);
        $this->assertSame('Viewer Legacy Tmp Branch', $viewerAuth->bank_addr_tmp);
        $this->assertSame(3, (int) $viewerAuth->bank_status);
        $this->assertStringStartsWith('auth/' . $viewerId . '/bank-change/', $viewerAuth->bank_card_img_tmp);
        $this->assertStringStartsWith('auth/' . $viewerId . '/bank-change/', $viewerAuth->bank_card_back_img_tmp);
        Storage::disk('public')->assertExists($viewerAuth->bank_card_img_tmp);
        Storage::disk('public')->assertExists($viewerAuth->bank_card_back_img_tmp);
        $this->assertNull(Cache::get('front_profile_change_code:' . $viewerId));

        $this->assertDatabaseHas('user_auths', [
            'user_id' => $otherId,
            'bank_name_tmp' => 'Other Legacy Tmp Bank',
            'bank_no_tmp' => '6222330000000201',
        ]);
        $this->assertDatabaseMissing('user_auths', [
            'user_id' => $otherId,
            'bank_no_tmp' => '6222330000000200',
        ]);
    }

    public function test_final_checklist_records_bank_change_submit_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 233.', $checklist);
        $this->assertStringContainsString('ProfileController::submitBankChange', $checklist);
        $this->assertStringContainsString('ProfileController::uploadChangeBankCard', $checklist);
        $this->assertStringContainsString('/api/front/profile/bank-card-change', $checklist);
        $this->assertStringContainsString('user/center/uploadChangeBankCard', $checklist);
        $this->assertStringContainsString('FrontProfileBankChangeSubmitOwnerBoundaryClosureModuleTest', $checklist);
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

    private function insertUserInfo(int $userId, string $userName, string $email, string $phone): void
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
            'phone' => $phone,
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

    private function insertUserAuth(int $userId, string $bankNameTmp, string $bankNoTmp): void
    {
        $now = time();

        DB::table('user_auths')->insert([
            'user_id' => $userId,
            'bank_name_tmp' => $bankNameTmp,
            'bank_no_tmp' => $bankNoTmp,
            'bank_addr_tmp' => 'Other Tmp Branch',
            'bank_card_img_tmp' => 'auth/' . $userId . '/bank-change/original-front.jpg',
            'bank_card_back_img_tmp' => 'auth/' . $userId . '/bank-change/original-back.jpg',
            'bank_status' => 3,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 创建已通过审核的当前银行卡，bank_status=2 表示用户具备申请换绑的前置资格。
     */
    private function insertApprovedUserAuth(int $userId): void
    {
        $now = time();

        DB::table('user_auths')->insert([
            'user_id' => $userId,
            'bank_name' => 'Approved Bank',
            'bank_no' => '622233999999' . substr((string) $userId, -4),
            'bank_addr' => 'Approved Branch',
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
        DB::table('withdraw_records')->whereIn('user_id', $userIds)->delete();
        DB::table('user_auths')->whereIn('user_id', $userIds)->delete();
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('email', $emails)->delete();
    }
}
