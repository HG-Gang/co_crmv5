<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:47
 */

/**
 * 前台头像上传属主边界闭环测试。
 *
 * 文件功能：
 * - 验证现代头像上传（/api/front/profile/avatar）与遗留头像上传
 *   （/user/center/uploadHeadImg）忽略伪造的 user_id / userId，只更新当前用户头像。
 * - 验证遗留头像上传可通过 legacy session（suser）认证且不依赖 user 守卫。
 * - 验证权限清单文档记录了该边界闭环。
 *
 * 适用场景：
 * - 前台个人资料头像上传的越权回归测试，防止通过参数覆盖他人头像。
 *
 * 入参例子：
 * - 上传字段：avatar（现代）/ headimg（遗留），伪造参数 user_id={otherId}&userId={otherId}。
 *
 * 返回值：
 * - 现代接口返回 code 为 UPLOADED；遗留接口返回 msg 为 SUC。
 * - 当前用户 avatar 指向 avatars/{userId}/ 目录，他人头像保持不变。
 *
 * 异常或失败场景：
 * - 伪造 user_id / userId 时仍只更新当前用户头像，不覆盖他人头像。
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

class FrontProfileAvatarOwnerBoundaryClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @var array<int, int>
     */
    private array $mirrorUserIds = [];

    protected function tearDown(): void
    {
        foreach ($this->mirrorUserIds as $userId) {
            File::deleteDirectory(public_path('storage/avatars/' . $userId));
        }

        parent::tearDown();
    }

    // 验证现代头像上传忽略伪造的 user_id / userId，只更新当前用户头像。
    public function test_modern_avatar_upload_ignores_spoofed_user_id_and_updates_current_profile_only(): void
    {
        $viewerId = 412290100;
        $otherId = 412290101;
        $viewerEmail = 'front-avatar-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-avatar-boundary-' . $otherId . '@example.test';
        $otherAvatar = 'avatars/' . $otherId . '/other-original.jpg';

        $this->fakePublicStorageFor([$viewerId, $otherId]);
        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->insertUserInfo($viewerId, 'avatar-boundary-viewer', $viewerEmail, '');
        $this->insertUserInfo($otherId, 'avatar-boundary-other', $otherEmail, $otherAvatar);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->post('/api/front/profile/avatar', [
                'avatar' => UploadedFile::fake()->image('viewer-avatar.jpg', 32, 32),
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::UPLOADED);

        $viewerAvatar = (string) DB::table('user_infos')->where('user_id', $viewerId)->value('avatar');
        $this->assertStringStartsWith('avatars/' . $viewerId . '/', $viewerAvatar);
        Storage::disk('public')->assertExists($viewerAvatar);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $otherId,
            'avatar' => $otherAvatar,
        ]);
        $this->assertDatabaseMissing('user_infos', [
            'user_id' => $otherId,
            'avatar' => $viewerAvatar,
        ]);
    }

    // 验证遗留头像上传忽略伪造的 user_id / userId，只更新当前用户头像。
    public function test_legacy_head_image_upload_ignores_spoofed_user_id_and_updates_current_profile_only(): void
    {
        $viewerId = 412290200;
        $otherId = 412290201;
        $viewerEmail = 'front-avatar-boundary-' . $viewerId . '@example.test';
        $otherEmail = 'front-avatar-boundary-' . $otherId . '@example.test';
        $otherAvatar = 'avatars/' . $otherId . '/other-original.jpg';

        $this->fakePublicStorageFor([$viewerId, $otherId]);
        $this->deleteFixtureRows([$viewerId, $otherId], [$viewerEmail, $otherEmail]);
        $this->insertUserInfo($viewerId, 'avatar-legacy-boundary-viewer', $viewerEmail, '');
        $this->insertUserInfo($otherId, 'avatar-legacy-boundary-other', $otherEmail, $otherAvatar);

        $login = UserLogin::where('user_id', $viewerId)->firstOrFail();
        $response = $this->withoutMiddleware([JwtAuthMiddleware::class, SingleSignOn::class])
            ->actingAs($login, 'user')
            ->post('/user/center/uploadHeadImg', [
                'headimg' => UploadedFile::fake()->image('viewer-head.jpg', 32, 32),
                'user_id' => $otherId,
                'userId' => $otherId,
            ]);

        $response->assertOk()
            ->assertJsonPath('msg', 'SUC');

        $viewerAvatar = (string) DB::table('user_infos')->where('user_id', $viewerId)->value('avatar');
        $this->assertStringStartsWith('avatars/' . $viewerId . '/', $viewerAvatar);
        Storage::disk('public')->assertExists($viewerAvatar);
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $otherId,
            'avatar' => $otherAvatar,
        ]);
        $this->assertDatabaseMissing('user_infos', [
            'user_id' => $otherId,
            'avatar' => $viewerAvatar,
        ]);
    }

    // 验证遗留头像上传接受 legacy session 且不依赖 user 守卫。
    public function test_legacy_head_image_upload_accepts_suser_session_without_user_guard(): void
    {
        $viewerId = 412290250;
        $viewerEmail = 'front-avatar-suser-' . $viewerId . '@example.test';

        $this->fakePublicStorageFor([$viewerId]);
        $this->deleteFixtureRows([$viewerId], [$viewerEmail]);
        $this->insertUserInfo($viewerId, 'avatar-suser-viewer', $viewerEmail, '');

        $response = $this->withSession([
            'suser' => [
                'user_id' => $viewerId,
                'user_name' => 'avatar-suser-viewer',
            ],
        ])->post('/user/center/uploadHeadImg', [
            'headimg' => UploadedFile::fake()->image('suser-head.jpg', 32, 32),
        ]);

        $response->assertOk()->assertJsonPath('msg', 'SUC');
        $viewerAvatar = (string) DB::table('user_infos')->where('user_id', $viewerId)->value('avatar');
        $this->assertStringStartsWith('avatars/' . $viewerId . '/', $viewerAvatar);
        Storage::disk('public')->assertExists($viewerAvatar);
    }

    // 校验权限清单文档记录了头像上传属主边界闭环。
    public function test_final_checklist_records_avatar_owner_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 229.', $checklist);
        $this->assertStringContainsString('ProfileController::uploadAvatar', $checklist);
        $this->assertStringContainsString('ProfileController::uploadHeadImg', $checklist);
        $this->assertStringContainsString('/api/front/profile/avatar', $checklist);
        $this->assertStringContainsString('user/center/uploadHeadImg', $checklist);
        $this->assertStringContainsString('FrontProfileAvatarOwnerBoundaryClosureModuleTest', $checklist);
    }

    /**
     * @param array<int, int> $userIds
     */
    private function fakePublicStorageFor(array $userIds): void
    {
        Storage::fake('public');

        foreach ($userIds as $userId) {
            $this->mirrorUserIds[] = $userId;
            File::deleteDirectory(public_path('storage/avatars/' . $userId));
        }
    }

    private function insertUserInfo(int $userId, string $userName, string $email, string $avatar): void
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
            'phone' => '1392290' . substr((string) $userId, -4),
            'gender' => 1,
            'avatar' => $avatar,
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

    /**
     * @param array<int, int> $userIds
     * @param array<int, string> $emails
     */
    private function deleteFixtureRows(array $userIds, array $emails): void
    {
        DB::table('user_infos')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('user_id', $userIds)->delete();
        DB::table('user_logins')->whereIn('email', $emails)->delete();
    }
}
