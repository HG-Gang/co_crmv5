<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:46
 */

/**
 * 文件功能：验证新闻公告（news）更新、删除、发布切换接口对非严格路由 ID 的
 *           校验边界，并核对最终检查清单文档。
 *
 * 适用场景：后台 /api/admin/updateNews/{id}、/api/admin/deleteNews/{id}、
 *           /api/admin/toggleNews/{id} 接口的输入校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/updateNews/{id}abc：{title, content, image, author_id, ...}
 * - POST /api/admin/deleteNews/{id}abc：无请求体
 * - POST /api/admin/toggleNews/{id}abc：无请求体
 *
 * 返回值：
 * - 路由 ID 带非数字后缀时返回 code=VALIDATION_FAILED，新闻内容与发布状态保持原样。
 *
 * 异常或失败场景：
 * - 路由 ID 非严格数字（如 {id}abc）时校验失败，不做更新、删除或切换。
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

class AdminNewsRouteIdValidationClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    // 更新新闻时路由 ID 带非数字后缀应校验失败且新闻保持原样。
    public function test_update_news_rejects_non_strict_route_id_without_changing_news(): void
    {
        $actor = $this->ensureSuperAdmin();
        $targetId = $this->createManagedNews('Route Id News Original', 1);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/updateNews/' . $targetId . 'abc', [
                'title' => 'Route Id News Updated',
                'content' => 'Updated news content',
                'image' => '/uploads/news/updated.png',
                'author_id' => 99,
                'author_name' => 'Updated Author',
                'is_published' => 0,
            ]);

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $news = DB::table('news')->where('id', $targetId)->first();

        $this->assertSame('Route Id News Original', (string) $news->title);
        $this->assertSame('Original news content', (string) $news->content);
        $this->assertSame('/uploads/news/original.png', (string) $news->image);
        $this->assertSame(7, (int) $news->author_id);
        $this->assertSame('Original Author', (string) $news->author_name);
        $this->assertSame(1, (int) $news->is_published);
        $this->assertNull($news->deleted_at);
    }

    // 删除新闻时路由 ID 带非数字后缀应校验失败且不删除新闻。
    public function test_delete_news_rejects_non_strict_route_id_without_deleting_news(): void
    {
        $actor = $this->ensureSuperAdmin();
        $targetId = $this->createManagedNews('Route Id News Delete', 1);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/deleteNews/' . $targetId . 'abc');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $news = DB::table('news')->where('id', $targetId)->first();

        $this->assertNotNull($news);
        $this->assertNull($news->deleted_at);
        $this->assertSame('Route Id News Delete', (string) $news->title);
    }

    // 切换新闻发布状态时路由 ID 带非数字后缀应校验失败且状态保持原样。
    public function test_toggle_news_rejects_non_strict_route_id_without_changing_publish_status(): void
    {
        $actor = $this->ensureSuperAdmin();
        $targetId = $this->createManagedNews('Route Id News Toggle', 1);

        $response = $this->withoutMiddleware([AdminAuthenticate::class, JwtAuthMiddleware::class, SingleSignOn::class, CheckPermission::class])
            ->actingAs($actor, 'admin')
            ->post('/api/admin/toggleNews/' . $targetId . 'abc');

        $response->assertOk()
            ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);

        $this->assertSame(1, (int) DB::table('news')->where('id', $targetId)->value('is_published'));
    }

    // 核对最终检查清单文档记录了新闻路由 ID 校验边界。
    public function test_final_checklist_records_news_route_id_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 290.', $checklist);
        $this->assertStringContainsString('NewsController::update', $checklist);
        $this->assertStringContainsString('NewsController::destroy', $checklist);
        $this->assertStringContainsString('NewsController::togglePublish', $checklist);
        $this->assertStringContainsString('/api/admin/updateNews/{id}', $checklist);
        $this->assertStringContainsString('/api/admin/deleteNews/{id}', $checklist);
        $this->assertStringContainsString('/api/admin/toggleNews/{id}', $checklist);
        $this->assertStringContainsString('news.id', $checklist);
        $this->assertStringContainsString('AdminNewsRouteIdValidationClosureModuleTest', $checklist);
    }

    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-news-route-id-super',
                'email' => 'admin-news-route-id-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    private function createManagedNews(string $title, int $isPublished): int
    {
        $now = time();

        DB::table('news')->where('title', $title)->delete();

        return (int) DB::table('news')->insertGetId([
            'title' => $title,
            'content' => 'Original news content',
            'image' => '/uploads/news/original.png',
            'author_id' => 7,
            'author_name' => 'Original Author',
            'is_published' => $isPublished,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }
}
