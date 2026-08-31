<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 12:55
 */

/**
 * AdminLegacyNewsUiClosureModuleTest
 *
 * 文件功能：
 * - 验证新闻公告双 UI 契约：Layui 本地化筛选与权限控件、旧页面模式由服务端选定不可被查询参数覆盖、CrmUI 筛选/新建表单/行权限声明与双语标签。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * 新闻公告双 UI 的页面结构、旧页面模式和权限声明契约。
 *
 * 这里锁定的是页面壳与交互声明；真实列表/写入业务仍由新闻 API 及其权限中间件负责。
 */
class AdminLegacyNewsUiClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_layui_news_exposes_localized_filters_and_permission_controls(): void
    {
        App::setLocale('zh-CN');
        $response = $this->get('/admin/news')->assertOk();
        $script = file_get_contents(public_path('js/apps/admin/layui/pages.js')) ?: '';
        $start = strpos($script, "registry['news/index']");
        $end = strpos($script, "registry['online-users/index']", (int) $start);

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $module = substr($script, (int) $start, (int) $end - (int) $start);

        foreach ([
            'name="title"',
            'name="start_date"',
            'name="end_date"',
            'name="is_published"',
            'id="newsStartDate"',
            'id="newsEndDate"',
            'for="newsStartDate"',
            'for="newsEndDate"',
            'data-permission="admin_news_create"',
            'data-permission="admin_news_update"',
            'data-permission="admin_news_toggle"',
            'data-permission="admin_news_delete"',
            'value="1"',
            'value="0"',
        ] as $needle) {
            $response->assertSee($needle, false);
        }

        $response->assertSee(
            '<button class="layui-btn" id="saveNewsButton" data-permission="admin_news_create"',
            false
        );

        foreach ([
            __('admin.title'),
            __('admin.start_date'),
            __('admin.end_date'),
            __('admin.publishStatus'),
            __('admin.published'),
            __('admin.unpublished'),
        ] as $label) {
            $response->assertSee($label, false);
        }

        foreach ([
            "layui.use(['table', 'form', 'laydate', 'layer', 'jquery']",
            "laydate.render({elem: '#newsStartDate'",
            "laydate.render({elem: '#newsEndDate'",
            'page: {curr: 1}',
            'Math.min(680, window.innerWidth - 32)',
            'Math.min(600, window.innerHeight - 32)',
            "if (field.value !== '')",
            "where: clearedNewsSearchFilters()",
            'title: null',
            'start_date: null',
            'end_date: null',
            'is_published: null',
            "prop('disabled', submitting)",
            'complete: function()',
        ] as $needle) {
            $this->assertStringContainsString($needle, $module);
        }

        $this->assertGreaterThanOrEqual(2, substr_count($module, 'page: {curr: 1}'));
        $this->assertGreaterThanOrEqual(2, substr_count($module, 'where: newsSearchFilters()'));
        $this->assertStringNotContainsString('where: data.field', $module);
        $this->assertStringNotContainsString('Math.max(', $module);
        $this->assertStringContainsString(
            "if (mode === 'create') {\n                    openNewsModal({id: '', title: '', content: '', is_published: 1});",
            $module
        );
        $this->assertStringContainsString("attr('aria-busy', submitting ? 'true' : 'false')", $module);
        $this->assertStringNotContainsString('location.search', $module);
        $this->assertStringNotContainsString('URLSearchParams', $module);

        $openStart = strpos($module, 'function openNewsModal(row)');
        $openEnd = strpos($module, 'function newsSearchFilters()', (int) $openStart);
        $this->assertNotFalse($openStart);
        $this->assertNotFalse($openEnd);
        $openModal = substr($module, (int) $openStart, (int) $openEnd - (int) $openStart);
        foreach ([
            "row.id ? 'admin_news_update' : 'admin_news_create'",
            ".attr('data-permission', savePermission)",
            ".data('permission', savePermission)",
            'refreshPermissions();',
        ] as $needle) {
            $this->assertStringContainsString($needle, $openModal);
        }
        $this->assertLessThan(
            strpos($openModal, 'refreshPermissions();'),
            strpos($openModal, ".attr('data-permission', savePermission)")
        );

        $this->assertStringNotContainsString('<tr class="mock', $response->getContent());
        $this->assertStringNotContainsString('mockNews', $module);
    }

    public function test_legacy_news_mode_is_server_selected_and_query_cannot_override_it(): void
    {
        $admin = Admin::query()->findOrFail(1);
        $unsafeTitle = 'UI "><script>alert(1)</script>';
        $unsafeContent = '<img src=x onerror=alert(1)>';
        $newsId = (int) DB::table('news')->insertGetId([
            'title' => $unsafeTitle,
            'content' => $unsafeContent,
            'image' => '',
            'author_id' => (int) $admin->id,
            'author_name' => (string) ($admin->username ?? 'admin'),
            'is_published' => 1,
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/index/admin/news/news_list_browse?newsMode=edit&mode=create')
            ->assertOk()
            ->assertSee('data-news-mode="list"', false)
            ->assertDontSee('data-news-mode="edit"', false);

        $this->actingAs($admin, 'admin')
            ->get('/index/admin/news/news_add_browse?newsMode=edit')
            ->assertOk()
            ->assertSee('data-news-mode="create"', false)
            ->assertDontSee('data-news-mode="edit"', false);

        $editResponse = $this->actingAs($admin, 'admin')
            ->get('/index/admin/news/news_edit/' . $newsId . '?newsid=1&newsMode=create')
            ->assertOk()
            ->assertSee('data-news-mode="edit"', false)
            ->assertSee('data-news-info=', false)
            ->assertSee('\\u003Cscript\\u003E', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);

        $this->assertStringContainsString('\\u003Cimg', $editResponse->getContent());
    }

    public function test_crmui_news_declares_filters_create_form_and_row_permissions(): void
    {
        $response = $this->get('/admin-crmui/news')->assertOk();
        $controller = file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php')) ?: '';

        foreach ([
            'name="title"',
            'name="start_date"',
            'name="end_date"',
            'name="is_published"',
            'data-permission="admin_news_create"',
            'data-permission="admin_news_update"',
            'data-permission="admin_news_toggle"',
            'data-permission="admin_news_delete"',
            'value="1"',
            'value="0"',
        ] as $needle) {
            $response->assertSee($needle, false);
        }

        $this->assertStringContainsString("'formPermission' => 'admin_news_create'", $controller);
        $this->assertStringContainsString("'filters' => ['title', 'start_date', 'end_date'", $controller);
        $this->assertStringContainsString("'permission' => 'admin_news_update'", $controller);
        $this->assertStringContainsString("'permission' => 'admin_news_toggle'", $controller);
        $this->assertStringContainsString("'permission' => 'admin_news_delete'", $controller);
        $this->assertStringNotContainsString('mock', strtolower($response->getContent()));
    }

    public function test_news_labels_are_available_in_both_supported_locales(): void
    {
        $english = file_get_contents(resource_path('lang/en/admin.php')) ?: '';
        $chinese = file_get_contents(resource_path('lang/zh-CN/admin.php')) ?: '';

        foreach (['start_date', 'end_date', 'publishStatus', 'published', 'unpublished'] as $key) {
            $this->assertStringContainsString("'{$key}'", $english);
            $this->assertStringContainsString("'{$key}'", $chinese);
        }

        foreach (['zh-CN', 'en'] as $locale) {
            App::setLocale($locale);
            $response = $this->get('/admin/news')->assertOk();

            foreach (['title', 'start_date', 'end_date', 'publishStatus', 'published', 'unpublished'] as $key) {
                $response->assertSee(__('admin.' . $key), false);
            }
        }
    }
}
