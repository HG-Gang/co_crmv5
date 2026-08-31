<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/07
 * Time: 19:07
 */

/**
 * AdminAuthenticationDetailClosureTest
 *
 * 文件功能：
 * - 验证后台实名认证详情闭环：路由与权限注册、详情模式渲染与非法路径拒绝、权限迁移声明、旧详情 URL 落专用页、读写与审核状态声明、旧默认日期窗口与银行卡重绑临时图片优先。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Unit;

use App\Constants\ResponseCode;
use App\Http\Controllers\Admin\AuthenticationController;
use App\Http\Controllers\Admin\LegacyAdminController;
use App\Models\UserAuth;
use App\Services\AdminDataScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 后台实名认证详情与旧入口闭环契约测试。
 *
 * 功能逻辑说明：
 * - 详情必须是独立 API/Blade 页面，不能把旧详情链接降级为认证列表。
 * - 认证详情必须继续受后台权限与业务数据范围保护，并返回旧页面所需的资料和四张图片。
 * - 旧搜索字段保持 camelCase 兼容，日期范围保持旧默认值和严格校验。
 */
class AdminAuthenticationDetailClosureTest extends TestCase
{
    public function test_auth_detail_route_and_permission_are_registered(): void
    {
        $this->assertTrue(Route::has('admin_api_authDetail'));
        $this->assertTrue(Route::has('admin_page_authentication_detail'));
        $route = Route::getRoutes()->getByName('admin_api_authDetail');

        $this->assertNotNull($route);
        $this->assertContains('check.permission:admin', $route->gatherMiddleware());
        $this->assertStringContainsString('AuthenticationController@detail', $route->getActionName());
    }

    public function test_modern_detail_route_renders_modes_and_rejects_invalid_paths(): void
    {
        $this->get('/admin/authentications/984205/detail/show')
            ->assertOk()
            ->assertSee('data-auth-detail-mode="show"', false)
            ->assertSee('data-auth-detail-user-id="984205"', false)
            ->assertDontSee('id="authDetailReviewForm"', false);

        $this->get('/admin/authentications/984206/detail/auth')
            ->assertOk()
            ->assertSee('data-auth-detail-mode="auth"', false)
            ->assertSee('data-auth-detail-user-id="984206"', false)
            ->assertSee('id="authDetailReviewForm"', false);

        $this->get('/admin/authentications/984207/detail/invalid')->assertNotFound();
        $this->get('/admin/authentications/0/detail/show')->assertNotFound();
    }

    public function test_auth_detail_permission_migration_is_declared(): void
    {
        $path = database_path('migrations/2026_08_07_000001_add_admin_authentication_detail_permission.php');

        $this->assertFileExists($path);
        $source = (string) file_get_contents($path);
        $this->assertStringContainsString('admin_auth_detail', $source);
        $this->assertStringContainsString('admin_api_authDetail', $source);
        $this->assertStringContainsString('admin_authentications', $source);
    }

    public function test_authentication_controller_detail_contract_contains_scope_and_legacy_fields(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/AuthenticationController.php'));

        $this->assertIsString($source);
        foreach ([
            'public function detail(Request $request)',
            'applyDataScope',
            'user_logins',
            'user_infos.phone',
            'user_logins.email',
            'id_card_front',
            'id_card_back',
            'bank_card_img',
            'bank_card_back_img',
            'id_card_front_url',
            'id_card_back_url',
            'bank_card_img_url',
            'bank_card_back_img_url',
        ] as $expected) {
            $this->assertStringContainsString($expected, $source, $expected);
        }
    }

    public function test_legacy_detail_urls_render_a_dedicated_detail_page(): void
    {
        $this->withoutMiddleware();

        $this->get('/index/admin/auth/user_certified_detail/984201')
            ->assertOk()
            ->assertSee('data-auth-detail-page="1"', false)
            ->assertSee('data-auth-detail-mode="show"', false)
            ->assertSee('data-auth-detail-user-id="984201"', false)
            ->assertDontSee('id="authDetailReviewForm"', false)
            ->assertDontSee('id="authPendingTable"', false);

        $this->get('/index/admin/auth/user_examine/detail/auth/984202')
            ->assertOk()
            ->assertSee('data-auth-detail-page="1"', false)
            ->assertSee('data-auth-detail-mode="auth"', false)
            ->assertSee('data-auth-detail-user-id="984202"', false)
            ->assertSee('id="authDetailReviewForm"', false)
            ->assertDontSee('id="authPendingTable"', false);
    }

    public function test_detail_blade_declares_complete_read_and_review_states(): void
    {
        $source = file_get_contents(resource_path('admin/layui/authentications/detail.blade.php'));

        $this->assertIsString($source);
        foreach ([
            'id="authDetailLoading"',
            'id="authDetailError"',
            'id="authDetailContent"',
            'data-auth-detail-component="id_card"',
            'data-auth-detail-component="bank"',
            'id="authDetailIdCardFront"',
            'id="authDetailIdCardBack"',
            'id="authDetailBankCardFront"',
            'id="authDetailBankCardBack"',
            'data-layui-page="authentications/detail"',
        ] as $expected) {
            $this->assertStringContainsString($expected, $source, $expected);
        }

        $this->assertStringContainsString("=== 'auth'", $source);
        $this->assertStringContainsString('lay-filter="submitAuthDetailReview"', $source);
        $this->assertStringContainsString('name="id_card_decision"', $source);
        $this->assertStringContainsString('name="id_card_reason"', $source);
        $this->assertStringContainsString('name="bank_decision"', $source);
        $this->assertStringContainsString('name="bank_reason"', $source);
        $this->assertSame(2, substr_count($source, 'maxlength="500"'));
        $this->assertStringNotContainsString('maxlength="1000"', $source);
    }

    public function test_authentication_lists_link_pending_and_certified_rows_to_the_correct_detail_modes(): void
    {
        $blade = (string) file_get_contents(resource_path('admin/layui/authentications/index.blade.php'));
        $script = (string) file_get_contents(public_path('js/apps/admin/layui/pages.js'));

        $this->assertStringContainsString('lay-event="detail"', $blade);
        $this->assertStringContainsString('id="authCertifiedToolbar"', $blade);
        $this->assertStringContainsString("table.on('tool(authCertifiedTable)'", $script);
        $this->assertStringContainsString('buildAuthDetailPageUrl', $script);
        $this->assertStringContainsString("'/detail/' + mode", $script);
        $this->assertStringNotContainsString("window.location.href = '/index/admin/auth/user_examine/detail", $script);
        $this->assertStringNotContainsString("window.location.href = '/index/admin/auth/user_certified_detail", $script);
    }

    public function test_authentication_lists_apply_the_legacy_default_date_window_on_initial_load(): void
    {
        $blade = (string) file_get_contents(resource_path('admin/layui/authentications/index.blade.php'));
        $script = (string) file_get_contents(public_path('js/apps/admin/layui/pages.js'));
        $start = strpos($script, "registry['authentications/index']");
        $end = strpos($script, "registry['big-agents/index']", $start ?: 0);

        $this->assertSame(2, substr_count($blade, 'name="start_date"'));
        $this->assertSame(2, substr_count($blade, 'name="end_date"'));
        $this->assertSame(2, substr_count($blade, 'value="2024-01-01"'));
        $this->assertSame(2, substr_count($blade, "value=\"{{ date('Y-m-d') }}\""));
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $module = substr($script, (int) $start, (int) $end - (int) $start);
        foreach ([
            "laydate.render({elem: '#authPendingStartDate', type: 'date'});",
            "laydate.render({elem: '#authPendingEndDate', type: 'date'});",
            "laydate.render({elem: '#authCertifiedStartDate', type: 'date'});",
            "laydate.render({elem: '#authCertifiedEndDate', type: 'date'});",
            "where: serializeForm($, '#authPendingSearchForm')",
            "where: serializeForm($, '#authCertifiedSearchForm')",
        ] as $expected) {
            $this->assertStringContainsString($expected, $module, $expected);
        }
    }

    public function test_authentication_detail_script_loads_renders_and_reviews_independent_components(): void
    {
        $script = (string) file_get_contents(public_path('js/apps/admin/layui/pages.js'));
        $start = strpos($script, "registry['authentications/detail']");
        $end = strpos($script, "registry['authentications/index']", $start ?: 0);

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $module = substr($script, (int) $start, (int) $end - (int) $start);
        foreach ([
            "url: '/api/admin/authDetail'",
            "url: '/api/admin/reviewAuth'",
            'setDetailState',
            'renderAuthDetail',
            'renderDetailImage',
            'buildAuthDetailReviewPayload',
            "decision === '2' && !reason",
            'loadAuthDetail();',
        ] as $expected) {
            $this->assertStringContainsString($expected, $module, $expected);
        }

        $this->assertStringContainsString("status === '1'", $module);
        $this->assertStringContainsString("status === '1' || status === '3'", $module);
        $this->assertStringContainsString("String(detail && detail.user_id)", $module);
        $this->assertStringNotContainsString('.html(detail', $module);
    }

    public function test_authentication_detail_has_visual_c_responsive_styles(): void
    {
        $css = (string) file_get_contents(public_path('css/layui/visual-c.css'));

        foreach ([
            '.auth-detail-page',
            '.auth-detail-toolbar',
            '.auth-detail-state',
            '.auth-detail-facts',
            '.auth-detail-images',
            '.auth-detail-image-item',
            '.auth-detail-review-component',
            '.auth-detail-field-error',
        ] as $selector) {
            $this->assertStringContainsString($selector, $css, $selector);
        }

        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 768px\).*?\.auth-detail-images.*?grid-template-columns:\s*1fr/s',
            $css
        );
    }

    public function test_authentication_detail_language_contract_is_complete_in_both_locales(): void
    {
        $keys = [
            'auth_detail',
            'auth_detail_fetched',
            'auth_detail_load_failed',
            'auth_basic_information',
            'identity_materials',
            'review_remark',
            'id_card_front',
            'id_card_back',
            'bank_materials',
            'bank_no',
            'bank_name',
            'bank_addr',
            'bank_card_front',
            'bank_card_back',
            'auth_review_decision',
            'auth_no_reviewable_component',
            'reject_reason_required',
            'retry',
        ];

        foreach (['zh-CN', 'en'] as $locale) {
            $source = (string) file_get_contents(resource_path('lang/' . $locale . '/admin.php'));
            foreach ($keys as $key) {
                $this->assertStringContainsString("'" . $key . "' =>", $source, $locale . ':' . $key);
            }

            $browserSource = (string) file_get_contents(
                public_path('js/shared/lang/common/' . $locale . '.js')
            );
            foreach ([
                'auth_detail_load_failed',
                'auth_no_reviewable_component',
                'reject_reason_required',
                'account_type_agent',
                'account_type_customer',
                'account_type_unknown',
            ] as $key) {
                $this->assertStringContainsString($key . ':', $browserSource, $locale . ':browser:' . $key);
            }
        }
    }

    public function test_legacy_detail_mode_is_restricted_to_read_only_or_review(): void
    {
        $this->withoutMiddleware();

        $this->get('/index/admin/auth/user_examine/detail/invalid/984203')
            ->assertNotFound();
    }

    public function test_legacy_auth_search_aliases_include_dates_and_old_defaults(): void
    {
        $controller = new LegacyAdminController();
        $method = new \ReflectionMethod($controller, 'payloadForLegacyTarget');
        $method->setAccessible(true);

        $request = Request::create('/index/admin/auth/userExaminSearch', 'POST', [
            'userId' => '984204',
            'username' => 'Legacy Search',
            'startdate' => '2026-08-01',
            'enddate' => '2026-08-07',
        ]);
        $payload = $method->invoke($controller, $request);

        $this->assertSame('984204', $payload['user_id']);
        $this->assertSame('Legacy Search', $payload['user_name']);
        $this->assertSame('2026-08-01', $payload['start_date']);
        $this->assertSame('2026-08-07', $payload['end_date']);

        $defaultPayload = $method->invoke(
            $controller,
            Request::create('/index/admin/auth/userExaminSearchV2', 'POST')
        );

        $this->assertSame('2024-01-01', $defaultPayload['start_date']);
        $this->assertSame(date('Y-m-d'), $defaultPayload['end_date']);
    }

    /**
     * @dataProvider invalidDateRangeProvider
     */
    public function test_authentication_list_rejects_invalid_date_ranges(array $input): void
    {
        $controller = new TestableAuthenticationController($this->app->make(AdminDataScopeService::class));
        $response = $controller->validateFiltersForTest(Request::create('/api/admin/authPendingList', 'POST', $input));

        $this->assertNotNull($response);
        $this->assertSame(ResponseCode::VALIDATION_FAILED, $response->getData(true)['code']);
    }

    public function invalidDateRangeProvider(): array
    {
        return [
            'invalid start day' => [['start_date' => '2026-02-30']],
            'invalid ISO datetime' => [['end_date' => '2026-08-07T00:00:00']],
            'start after end' => [['start_date' => '2026-08-08', 'end_date' => '2026-08-07']],
        ];
    }

    public function test_bank_rebind_detail_prefers_temporary_card_images(): void
    {
        $auth = new UserAuth();
        $auth->setRawAttributes([
            'bank_status' => 3,
            'bank_card_img' => 'bank/formal-front.jpg',
            'bank_card_back_img' => 'bank/formal-back.jpg',
            'bank_card_img_tmp' => 'bank/pending-front.jpg',
            'bank_card_back_img_tmp' => 'bank/pending-back.jpg',
        ]);

        $this->assertSame('bank/pending-front.jpg', $auth->review_bank_img);
        $this->assertSame('bank/pending-back.jpg', $auth->review_bank_back_img);
    }
}

/**
 * 仅暴露受保护的生产校验方法，测试仍执行真实 AuthenticationController 逻辑。
 */
final class TestableAuthenticationController extends AuthenticationController
{
    public function validateFiltersForTest(Request $request)
    {
        return $this->validateListFilters($request);
    }
}
