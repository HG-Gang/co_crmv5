<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 11:35
 */

/**
 * FrontUploadSessionCompatibilityTest
 *
 * 文件功能：
 * - 验证前台上传会话兼容闭环：旧上传用 suser_id 命名文件、多图上传服务端生成文件名、非图片拒绝且不写文件、无登录会话的 JSON 上传失败关闭。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FrontUploadSessionCompatibilityTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 夹具登录用户 ID。验证上传会话在新旧会话实现间保持兼容。
     * @var int
     */
    private $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = $this->unusedUserId();
        $now = time();
        $loginId = DB::table('user_logins')->insertGetId([
            'user_id' => $this->userId,
            'email' => 'legacy-upload-' . $this->userId . '@example.test',
            'password' => Hash::make('password'),
            'account_type' => 2,
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
            'user_id' => $this->userId,
            'login_id' => $loginId,
            'user_name' => 'Legacy Upload User',
            'account_type' => 2,
            'family_tree' => (string) $this->userId,
            'auth_status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    public function test_legacy_upload_uses_suser_id_in_file_name_without_user_guard(): void
    {
        Storage::fake('public');

        $response = $this->withSession([
            'suser' => ['user_id' => $this->userId],
        ])->post('/user/upload/file', [
            'file' => UploadedFile::fake()->image('bank-card.jpg', 32, 32),
        ]);

        $response->assertOk()->assertJsonPath('code', 200);
        $path = (string) $response->json('data.path');
        $this->assertMatchesRegularExpression('/_' . $this->userId . '\\.[a-z0-9]+$/i', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_legacy_multiple_upload_stores_two_images_with_server_generated_names(): void
    {
        Storage::fake('public');

        $response = $this->withSession([
            'suser' => ['user_id' => $this->userId],
        ])->post('/user/multiple/file', [
            'file' => [
                UploadedFile::fake()->image('identity-front.jpg', 32, 32),
                UploadedFile::fake()->image('identity-back.png', 32, 32),
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonCount(2, 'data');

        $paths = $response->json('data.*.path');
        $this->assertCount(2, array_unique($paths));

        foreach ($paths as $path) {
            $this->assertMatchesRegularExpression(
                '~^uploads/IdCard/\d{14}_[a-f0-9]{12}_' . $this->userId . '\.(?:jpg|png)$~',
                (string) $path
            );
            Storage::disk('public')->assertExists($path);
        }

        $this->assertStringNotContainsString('identity-front', implode('|', $paths));
        $this->assertStringNotContainsString('identity-back', implode('|', $paths));
    }

    public function test_legacy_single_upload_rejects_non_image_without_writing_file(): void
    {
        Storage::fake('public');

        $response = $this->withSession([
            'suser' => ['user_id' => $this->userId],
        ])->post('/user/upload/file', [
            'file' => UploadedFile::fake()->create('payload.php', 1, 'application/x-php'),
        ]);

        $response->assertOk()->assertJsonPath('code', 500);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_legacy_json_upload_without_authenticated_session_fails_closed(): void
    {
        Storage::fake('public');

        $response = $this->withHeader('Accept', 'application/json')
            ->post('/user/upload/file', [
                'file' => UploadedFile::fake()->image('anonymous.jpg', 32, 32),
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::AUTH_FAILED)
            ->assertJsonPath('redirect', true);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    private function unusedUserId(): int
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $userId = random_int(1000000000, 1900000000);
            $occupied = DB::table('user_logins')->useWritePdo()->where('user_id', $userId)->exists()
                || DB::table('user_infos')->useWritePdo()->where('user_id', $userId)->exists()
                || DB::table('user_trades')->useWritePdo()->where('user_id', $userId)->exists();

            if (!$occupied) {
                return $userId;
            }
        }

        throw new \RuntimeException('Unable to allocate unused upload fixture user ID.');
    }
}
