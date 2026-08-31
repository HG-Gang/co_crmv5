<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 12:27
 */

/**
 * AdminLegacyNewsMutationParityClosureModuleTest
 *
 * 文件功能：
 * - 验证新闻公告写操作双口径等价：旧 save/update/delete 字段映射与成功 envelope、软删除、非法别名载荷不写库，且别名不被现代控制器直接接受。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
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
use Tests\TestCase;

class AdminLegacyNewsMutationParityClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @dataProvider pushStateProvider
     */
    public function test_legacy_save_maps_real_old_fields_and_success_envelope(int $isPush): void
    {
        $admin = Admin::query()->findOrFail(1);
        $title = 'Legacy Save Push ' . $isPush . ' ' . uniqid('', true);

        $this->actingAs($admin, 'admin')->postJson('/index/admin/news/news_save', [
            'newsTitle' => $title,
            'newsContent' => 'Legacy save content',
            'ispush' => $isPush,
            'author_id' => 998301,
        ])->assertOk()
            ->assertJsonPath('msg', 'SUC')
            ->assertJsonPath('code', 0)
            ->assertJsonPath('modern_code', ResponseCode::CREATED);

        $news = DB::table('news')->where('title', $title)->first();
        $this->assertNotNull($news);
        $this->assertSame($isPush, (int) $news->is_published);
        $this->assertSame((int) $admin->id, (int) $news->author_id);
    }

    public static function pushStateProvider(): array
    {
        return ['not published' => [0], 'published' => [1]];
    }

    /**
     * @dataProvider legacyUpdateIdAliasProvider
     */
    public function test_legacy_update_maps_old_fields_target_id_and_success_envelope(string $idField): void
    {
        $admin = Admin::query()->findOrFail(1);
        $id = $this->insertNews('Legacy Update Old ' . $idField, null);

        $this->actingAs($admin, 'admin')->postJson('/index/admin/news/news_update', [
            $idField => $id,
            'newsTitle' => 'Legacy Update New ' . $idField,
            'newsContent' => 'Legacy update content',
            'ispush' => 0,
        ])->assertOk()
            ->assertJsonPath('msg', 'SUC')
            ->assertJsonPath('code', 0)
            ->assertJsonPath('modern_code', ResponseCode::UPDATED);

        $this->assertDatabaseHas('news', [
            'id' => $id,
            'title' => 'Legacy Update New ' . $idField,
            'content' => 'Legacy update content',
            'is_published' => 0,
            'deleted_at' => null,
        ]);
    }

    public static function legacyUpdateIdAliasProvider(): array
    {
        return ['newsId' => ['newsId'], 'newsid' => ['newsid']];
    }

    public function test_legacy_delete_returns_old_success_envelope_and_soft_deletes(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $id = $this->insertNews('Legacy Delete', null);

        $this->actingAs($admin, 'admin')->postJson('/index/admin/news/del', [
            'newsid' => $id,
        ])->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('modern_code', ResponseCode::DELETED);

        $this->assertSame(0, DB::table('news')->where('id', $id)->whereNull('deleted_at')->count());
    }

    public function test_legacy_mutations_preserve_real_modern_failures_and_write_nothing(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $softDeletedId = $this->insertNews('Legacy Soft Deleted', time());
        $before = DB::table('news')->count();

        $this->actingAs($admin, 'admin')->postJson('/index/admin/news/news_save', [
            'newsContent' => 'missing title',
            'ispush' => 1,
        ])->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->actingAs($admin, 'admin')->postJson('/index/admin/news/news_update', [
            'newsId' => 2147483102,
            'newsTitle' => 'Missing target',
            'newsContent' => 'Missing target content',
            'ispush' => 1,
        ])->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('code', ResponseCode::DATA_NOT_FOUND);

        $this->actingAs($admin, 'admin')->postJson('/index/admin/news/del', [
            'newsid' => $softDeletedId,
        ])->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('code', ResponseCode::DATA_NOT_FOUND);

        $this->actingAs($admin, 'admin')->postJson('/index/admin/news/del', [
            'newsid' => '1abc',
        ])->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertSame($before, DB::table('news')->count());
        $this->assertNotNull(DB::table('news')->where('id', $softDeletedId)->value('deleted_at'));
    }

    /**
     * @dataProvider invalidLegacyNewsWritePayloadProvider
     */
    public function test_legacy_save_rejects_invalid_alias_payloads_without_writing(array $payload): void
    {
        $admin = Admin::query()->findOrFail(1);
        $beforeCount = DB::table('news')->count();

        $this->actingAs($admin, 'admin')->postJson('/index/admin/news/news_save', $payload)
            ->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertSame($beforeCount, DB::table('news')->count());
    }

    /**
     * @dataProvider invalidLegacyNewsWritePayloadProvider
     */
    public function test_legacy_update_rejects_invalid_alias_payloads_without_writing(array $payload): void
    {
        $admin = Admin::query()->findOrFail(1);
        $targetId = $this->insertNews('Legacy Invalid Update Target', null);
        $beforeNews = (array) DB::table('news')->where('id', $targetId)->first();

        $payload['newsId'] = $targetId;
        $response = $this->actingAs($admin, 'admin')->postJson('/index/admin/news/news_update', $payload)
            ->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertSame(ResponseCode::VALIDATION_FAILED, (int) $response->json('modern_code'));
        $this->assertSame($beforeNews, (array) DB::table('news')->where('id', $targetId)->first());
    }

    public static function invalidLegacyNewsWritePayloadProvider(): array
    {
        return [
            'news title array' => [[
                'newsTitle' => ['unsafe'],
                'newsContent' => 'content',
                'ispush' => 1,
            ]],
            'news content array' => [[
                'newsTitle' => 'title',
                'newsContent' => ['unsafe'],
                'ispush' => 1,
            ]],
            'news content object' => [[
                'newsTitle' => 'title',
                'newsContent' => (object) ['value' => 'content'],
                'ispush' => 1,
            ]],
            'invalid ispush' => [[
                'newsTitle' => 'title',
                'newsContent' => 'content',
                'ispush' => 2,
            ]],
            'missing news content' => [[
                'newsTitle' => 'title',
                'ispush' => 1,
            ]],
        ];
    }

    public function test_news_aliases_are_not_accepted_by_the_modern_controller_directly(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $title = 'Modern Must Not Alias ' . uniqid('', true);

        $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($admin, 'admin')->postJson('/api/admin/createNews', [
            'newsTitle' => $title,
            'newsContent' => 'Old-only fields',
            'ispush' => 1,
        ])->assertOk()->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseMissing('news', ['title' => $title]);
    }

    public function test_legacy_update_requires_the_real_old_ispush_field(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $id = $this->insertNews('Legacy Missing Push', null);

        $this->actingAs($admin, 'admin')->postJson('/index/admin/news/news_update', [
            'newsId' => $id,
            'newsTitle' => 'Must Not Update Without Push',
            'newsContent' => 'Must Not Update Without Push Content',
        ])->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('news', [
            'id' => $id,
            'title' => 'Legacy Missing Push',
            'content' => 'Original legacy content',
            'is_published' => 1,
            'deleted_at' => null,
        ]);
    }

    /**
     * @dataProvider invalidLegacyTargetIdProvider
     */
    public function test_legacy_update_and_delete_strictly_reject_invalid_target_ids($invalidId): void
    {
        $admin = Admin::query()->findOrFail(1);
        $originalTitle = 'Legacy Invalid Target ' . uniqid('', true);
        $targetId = $this->insertNews($originalTitle, null);

        $this->actingAs($admin, 'admin')->postJson('/index/admin/news/news_update', [
            'newsId' => $invalidId,
            'id' => $targetId,
            'newsTitle' => 'Must Not Update',
            'newsContent' => 'Must Not Update Content',
            'ispush' => 0,
        ])->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->actingAs($admin, 'admin')->postJson('/index/admin/news/del', [
            'newsid' => $invalidId,
            'id' => $targetId,
        ])->assertOk()
            ->assertJsonPath('msg', 'FAIL')
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertDatabaseHas('news', [
            'id' => $targetId,
            'title' => $originalTitle,
            'is_published' => 1,
            'deleted_at' => null,
        ]);
    }

    public static function invalidLegacyTargetIdProvider(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
            'decimal' => ['1.5'],
            'suffix' => ['1abc'],
            'array' => [['1']],
        ];
    }

    private function insertNews(string $title, ?int $deletedAt): int
    {
        $now = time();

        return (int) DB::table('news')->insertGetId([
            'title' => $title,
            'content' => 'Original legacy content',
            'image' => '',
            'author_id' => 1,
            'author_name' => 'Legacy Admin',
            'is_published' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => $deletedAt,
        ]);
    }
}
