<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 13:58
 */

/**
 * AdminLegacyNewsPageModeClosureModuleTest
 *
 * 文件功能：
 * - 验证新闻公告页面模式契约：列表/新增页暴露显式模式、编辑页仅预载活跃路由新闻字段、非法或缺失路由 id 被拒绝并走共享查询服务边界。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Http\Controllers\Admin\LegacyAdminController;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminLegacyNewsPageModeClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_list_and_create_pages_expose_their_explicit_modes(): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->get('/index/admin/news/news_list_browse?newsMode=edit')
            ->assertOk()
            ->assertViewIs('admin_layui::news.index')
            ->assertViewHas('newsMode', 'list');

        $this->actingAs($admin, 'admin')
            ->get('/index/admin/news/news_add_browse?newsMode=edit')
            ->assertOk()
            ->assertViewIs('admin_layui::news.index')
            ->assertViewHas('newsMode', 'create');
    }

    public function test_edit_page_preloads_only_the_active_route_news_fields(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $targetId = $this->insertNews('Route Selected News', null);
        $queryId = $this->insertNews('Query Selected News', null);

        $this->actingAs($admin, 'admin')
            ->get('/index/admin/news/news_edit/' . $targetId . '?newsid=' . $queryId . '&title=Injected')
            ->assertOk()
            ->assertViewIs('admin_layui::news.index')
            ->assertViewHas('newsMode', 'edit')
            ->assertViewHas('newsInfo', [
                'id' => $targetId,
                'title' => 'Route Selected News',
                'content' => 'Content: Route Selected News',
                'is_published' => 1,
            ]);
    }

    /**
     * @dataProvider invalidEditIdProvider
     */
    public function test_edit_page_rejects_non_positive_or_non_integer_route_ids(string $routeId): void
    {
        $admin = Admin::query()->findOrFail(1);

        $this->actingAs($admin, 'admin')
            ->get('/index/admin/news/news_edit/' . $routeId)
            ->assertNotFound();
    }

    public static function invalidEditIdProvider(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-1'],
            'decimal' => ['1.5'],
            'suffix' => ['1abc'],
        ];
    }

    public function test_edit_page_rejects_missing_and_soft_deleted_news(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $missingId = 2147483001;
        $softDeletedId = $this->insertNews('Soft Deleted News', time());

        $this->actingAs($admin, 'admin')
            ->get('/index/admin/news/news_edit/' . $missingId)
            ->assertNotFound();

        $this->actingAs($admin, 'admin')
            ->get('/index/admin/news/news_edit/' . $softDeletedId)
            ->assertNotFound();
    }

    public function test_edit_page_uses_the_shared_news_query_service_boundary(): void
    {
        $method = new \ReflectionMethod(LegacyAdminController::class, 'pageDataFor');
        $this->assertSame('pageDataFor', $method->getName());
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $fileName = $method->getFileName();

        $this->assertIsInt($startLine);
        $this->assertIsInt($endLine);
        $this->assertGreaterThan(0, $startLine);
        $this->assertGreaterThan($startLine, $endLine);
        $this->assertIsString($fileName);
        $this->assertFileExists($fileName);

        $lines = file($fileName);
        $this->assertIsArray($lines);
        $methodSource = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
        $tokens = token_get_all("<?php\n" . $methodSource);
        $executableSource = '';
        foreach ($tokens as $token) {
            if (!is_array($token)) {
                $executableSource .= $token;
                continue;
            }
            if (in_array($token[0], [
                T_COMMENT,
                T_DOC_COMMENT,
                T_CONSTANT_ENCAPSED_STRING,
                T_ENCAPSED_AND_WHITESPACE,
            ], true)) {
                continue;
            }
            $executableSource .= $token[1];
        }

        $this->assertStringContainsString('AdminNewsQueryService', $executableSource);
        $this->assertStringContainsString('editableFields', $executableSource);
        $this->assertStringNotContainsString('News::query', $executableSource);
    }

    private function insertNews(string $title, ?int $deletedAt): int
    {
        $now = time();

        return (int) DB::table('news')->insertGetId([
            'title' => $title,
            'content' => 'Content: ' . $title,
            'image' => '/not-preloaded.png',
            'author_id' => 998102,
            'author_name' => 'Not Preloaded',
            'is_published' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => $deletedAt,
        ]);
    }
}
