<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:48
 */

/**
 * 前台身份认证属主边界闭环测试。
 *
 * 文件功能：
 * - 验证现代身份提交（/api/front/profile/identity）与遗留身份证上传
 *   （/user/center/uploadIdCard）忽略伪造的 user_id / userId，只更新当前用户的
 *   实名认证信息（user_auths / user_infos.user_name）。
 * - 验证遗留身份证上传可通过 legacy session（suser）认证且不依赖 user 守卫。
 * - 验证权限清单文档记录了该边界闭环。
 *
 * 适用场景：
 * - 前台个人资料实名认证的越权回归测试，防止通过参数覆盖他人身份证信息。
 *
 * 入参例子：
 * - 现代字段：id_card_no、id_card_front、id_card_back。
 * - 遗留字段：username、userIdcardNo、Idphoto1、Idphoto2。
 * - 伪造参数：user_id={otherId}&userId={otherId}。
 *
 * 返回值：
 * - 现代接口返回 code 为 UPDATED；遗留接口返回 msg 为 SUC。
 * - 当前用户 id_card_status=1、图片路径位于 auth/{userId}/identity/，他人认证保持不变。
 *
 * 异常或失败场景：
 * - 伪造 user_id / userId 时仍只更新当前用户实名信息，不覆盖他人认证。
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

class FrontProfileIdentityOwnerBoundaryClosureModuleTest extends TestCase
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

    // 验证现代身份提交忽略伪造的 user_id / userId，只更新当前用户认证信息。
    public function test_modern_identity_submit_ignores_spoofed_user_id_and_updates_current_auth_only(): void
    {
        $viewerId = 412310100;
        $otherId = 412310101;
        $viewerEmail = 'front-identity-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-identity-boundary-' . $otherId . '@example.test';

        $this->fakePublicStorageFor([$viewerId, $otherId]);
        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->insertUserInfo($viewerId, 'identity-boundary-viewer', $viewerEmail);
        $this->insertUserInfo($otherId, 'identity-boundary-other', $otherEmail);
        $this->insertUserAuth($otherId, 'OTHER-ID-412310101');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->post('/api/front/profile/identity', [
                'id_card_no' => 'VIEWER-ID-412310100',
                'id_card_front' => UploadedFile::fake()->image('viewer-front.jpg', 32, 32),
                'id_card_back' => UploadedFile::fake()->image('viewer-back.jpg', 32, 32),
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::UPDATED);

        $viewerAuth = DB::table('user_auths')->where('user_id', $viewerId)->first();
        $this->assertNotNull($viewerAuth);
        $this->assertSame('VIEWER-ID-412310100', $viewerAuth->id_card_no);
        $this->assertSame(1, (int) $viewerAuth->id_card_status);
        $this->assertStringStartsWith('auth/' . $viewerId . '/identity/', $viewerAuth->id_card_front);
        $this->assertStringStartsWith('auth/' . $viewerId . '/identity/', $viewerAuth->id_card_back);
        Storage::disk('public')->assertExists($viewerAuth->id_card_front);
        Storage::disk('public')->assertExists($viewerAuth->id_card_back);

        $this->assertDatabaseHas('user_auths', [
            'user_id' => $otherId,
            'id_card_no' => 'OTHER-ID-412310101',
        ]);
        $this->assertDatabaseMissing('user_auths', [
            'user_id' => $otherId,
            'id_card_no' => 'VIEWER-ID-412310100',
        ]);
    }

    // 验证遗留身份证上传忽略伪造的 user_id / userId，只更新当前用户资料。
    public function test_legacy_identity_upload_ignores_spoofed_user_id_and_updates_current_profile_only(): void
    {
        $viewerId = 412310200;
        $otherId = 412310201;
        $viewerEmail = 'front-identity-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-identity-boundary-' . $otherId . '@example.test';

        $this->fakePublicStorageFor([$viewerId, $otherId]);
        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->insertUserInfo($viewerId, 'identity-legacy-viewer-original', $viewerEmail);
        $this->insertUserInfo($otherId, 'identity-legacy-other-original', $otherEmail);
        $this->insertUserAuth($otherId, 'OTHER-ID-412310201');

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->post('/user/center/uploadIdCard', [
                'username' => 'identity-legacy-viewer-updated',
                'userIdcardNo' => 'VIEWER-ID-412310200',
                'Idphoto1' => UploadedFile::fake()->image('legacy-front.jpg', 32, 32),
                'Idphoto2' => UploadedFile::fake()->image('legacy-back.jpg', 32, 32),
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'SUC');

        $viewerAuth = DB::table('user_auths')->where('user_id', $viewerId)->first();
        $this->assertNotNull($viewerAuth);
        $this->assertSame('VIEWER-ID-412310200', $viewerAuth->id_card_no);
        $this->assertSame(1, (int) $viewerAuth->id_card_status);
        $this->assertStringStartsWith('auth/' . $viewerId . '/identity/', $viewerAuth->id_card_front);
        $this->assertStringStartsWith('auth/' . $viewerId . '/identity/', $viewerAuth->id_card_back);
        Storage::disk('public')->assertExists($viewerAuth->id_card_front);
        Storage::disk('public')->assertExists($viewerAuth->id_card_back);

        $this->assertDatabaseHas('user_infos', [
            'user_id' => $viewerId,
            'user_name' => 'identity-legacy-viewer-updated',
        ]);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $otherId,
            'user_name' => 'identity-legacy-other-original',
        ]);
        $this->assertDatabaseHas('user_auths', [
            'user_id' => $otherId,
            'id_card_no' => 'OTHER-ID-412310201',
        ]);
        $this->assertDatabaseMissing('user_auths', [
            'user_id' => $otherId,
            'id_card_no' => 'VIEWER-ID-412310200',
        ]);
    }

    // 验证遗留身份证上传接受 legacy session 且不依赖 user 守卫。
    public function test_legacy_identity_upload_accepts_suser_session_without_user_guard(): void
    {
        $viewerId = 412310250;
        $viewerEmail = 'front-identity-suser-' . $viewerId . '@example.test';

        $this->fakePublicStorageFor([$viewerId]);
        $this->deleteFixtureRows([$viewerId], [$viewerEmail]);
        $this->insertUserInfo($viewerId, 'identity-suser-viewer', $viewerEmail);

        $response = $this->withSession([
            'suser' => [
                'user_id' => $viewerId,
                'user_name' => 'identity-suser-viewer',
            ],
        ])->post('/user/center/uploadIdCard', [
            'username' => 'identity-suser-viewer-updated',
            'userIdcardNo' => 'VIEWER-ID-412310250',
            'Idphoto1' => UploadedFile::fake()->image('suser-front.jpg', 32, 32),
            'Idphoto2' => UploadedFile::fake()->image('suser-back.jpg', 32, 32),
        ]);

        $response->assertOk()->assertJsonPath('msg', 'SUC');
        $this->assertDatabaseHas('user_auths', [
            'user_id' => $viewerId,
            'id_card_no' => 'VIEWER-ID-412310250',
        ]);
    }

    // 校验权限清单文档记录了身份认证属主边界闭环。
    public function test_final_checklist_records_identity_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 231.', $checklist);
        $this->assertStringContainsString('ProfileController::submitIdentity', $checklist);
        $this->assertStringContainsString('ProfileController::uploadIdCard', $checklist);
        $this->assertStringContainsString('/api/front/profile/identity', $checklist);
        $this->assertStringContainsString('user/center/uploadIdCard', $checklist);
        $this->assertStringContainsString('FrontProfileIdentityOwnerBoundaryClosureModuleTest', $checklist);
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
            'phone' => '1392310' . substr((string) $userId, -4),
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

    private function insertUserAuth(int $userId, string $idCardNo): void
    {
        $now = time();

        DB::table('user_auths')->insert([
            'user_id' => $userId,
            'id_card_no' => $idCardNo,
            'id_card_status' => 2,
            'id_card_front' => 'auth/' . $userId . '/identity/original-front.jpg',
            'id_card_back' => 'auth/' . $userId . '/identity/original-back.jpg',
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
