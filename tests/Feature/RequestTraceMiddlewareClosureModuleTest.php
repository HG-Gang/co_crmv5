<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/10
 * Time: 20:58
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\RequestTraceMiddleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * 请求链路追踪中间件与异常注入闭环测试。
 *
 * 文件功能：
 * - 验证所有请求（成功/异常）响应均携带 request_id（ULID）与 trace_id（UUID）：
 *   响应头 X-Request-Id / X-Trace-Id、JSON 响应体同名字段。
 * - 验证命中模块的请求在模块 daily channel 落盘“请求参数 + 响应”日志（Log spy 断言调用契约），
 *   未命中模块的请求只注入标识不写模块日志。
 *
 * 适用场景：
 * - 任何改动 RequestTraceMiddleware / Exception Handler / config(trace|logging) 后回归。
 *
 * 入参例子：
 * - GET /user/login -> 响应头含 X-Request-Id / X-Trace-Id。
 * - GET 不存在的 API -> JSON 响应体含 request_id / trace_id（异常兜底路径）。
 *
 * 返回值：断言通过即表示链路标识与模块日志契约成立。
 *
 * 异常或失败场景：
 * - 任一路径缺少标识、日志未按模块落盘或敏感字段未脱敏时失败。
 */
final class RequestTraceMiddlewareClosureModuleTest extends TestCase
{
    /**
     * 页面（HTML）响应必须携带 X-Request-Id 与 X-Trace-Id 响应头。
     *
     * @return void 断言通过不返回值。
     */
    public function test_html_response_carries_trace_headers(): void
    {
        $response = $this->get(route('legacy_user_login_page', ['langId' => 1]));

        $response->assertStatus(200);
        $requestId = (string) $response->headers->get('X-Request-Id', '');
        $traceId = (string) $response->headers->get('X-Trace-Id', '');

        // ULID 26 位 + Crockford 字符集；UUID 36 位标准格式。
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $requestId, 'X-Request-Id 必须为 ULID');
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $traceId,
            'X-Trace-Id 必须为 UUID v4'
        );
    }

    /**
     * JSON 响应必须注入 request_id 与 trace_id 字段（无论业务成功或异常）。
     *
     * 说明：/api/admin/menus 在测试基座下可能返回 200 或 401，
     * 本测试只断言“字段注入”契约，不依赖具体状态码。
     *
     * @return void 断言通过不返回值。
     */
    public function test_json_response_injects_trace_fields(): void
    {
        $response = $this->postJson('/api/admin/menus');
        $json = $response->json();
        $this->assertIsArray($json);
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', (string) ($json['request_id'] ?? ''), 'JSON 响应体必须含 ULID request_id');
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            (string) ($json['trace_id'] ?? ''),
            'JSON 响应体必须含 UUID trace_id'
        );
        // 响应头同步注入。
        $this->assertNotEmpty((string) $response->headers->get('X-Request-Id', ''));
        $this->assertNotEmpty((string) $response->headers->get('X-Trace-Id', ''));
    }

    public function test_direct_middleware_injection_supports_object_and_array_roots_without_changing_nested_objects(): void
    {
        $middleware = new RequestTraceMiddleware();

        $objectResponse = $middleware->handle(
            Request::create('/trace-object-shape', 'GET'),
            function (): JsonResponse {
                return response()->json((object) [
                    'code' => 1000,
                    'data' => (object) [],
                ]);
            }
        );
        $objectBody = json_decode($objectResponse->getContent());

        $this->assertIsObject($objectBody);
        $this->assertIsObject($objectBody->data);
        $this->assertNotEmpty((string) $objectBody->request_id);
        $this->assertNotEmpty((string) $objectBody->trace_id);
        $this->assertNotEmpty((string) $objectResponse->headers->get('X-Request-Id', ''));
        $this->assertNotEmpty((string) $objectResponse->headers->get('X-Trace-Id', ''));

        $arrayResponse = $middleware->handle(
            Request::create('/trace-array-shape', 'GET'),
            function (): JsonResponse {
                return response()->json(['first']);
            }
        );
        $arrayBody = json_decode($arrayResponse->getContent());

        $this->assertIsArray($arrayBody);
        $this->assertSame('first', $arrayBody[0]);
        $this->assertStringNotContainsString('request_id', $arrayResponse->getContent());
        $this->assertStringNotContainsString('trace_id', $arrayResponse->getContent());
        $this->assertNotEmpty((string) $arrayResponse->headers->get('X-Request-Id', ''));
        $this->assertNotEmpty((string) $arrayResponse->headers->get('X-Trace-Id', ''));

        $scalarResponse = $middleware->handle(
            Request::create('/trace-scalar-shape', 'GET'),
            function (): JsonResponse {
                return response()->json('scalar');
            }
        );

        $this->assertSame('scalar', json_decode($scalarResponse->getContent()));
        $this->assertNotEmpty((string) $scalarResponse->headers->get('X-Request-Id', ''));
        $this->assertNotEmpty((string) $scalarResponse->headers->get('X-Trace-Id', ''));
    }

    /**
     * 路由级异常（404/405）也必须注入链路标识（Handler 兜底生成路径）。
     *
     * @return void 断言通过不返回值。
     */
    public function test_route_level_exception_still_carries_trace_ids(): void
    {
        $response = $this->getJson('/api/front/definitely-not-exists-route-xyz');
        $response->assertStatus(404);
        $json = $response->json();
        $this->assertIsArray($json);
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', (string) ($json['request_id'] ?? ''));
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            (string) ($json['trace_id'] ?? '')
        );
    }

    /**
     * 命中模块的请求必须写入模块 daily 日志文件（请求参数 + 响应摘要）。
     *
     * 说明：直接断言真实日志文件内容，测试后清理产物，避免依赖 mock 链式调用。
     *
     * @return void 断言通过不返回值。
     */
    public function test_module_request_and_response_logged_to_module_channel(): void
    {
        $logFile = storage_path('logs/front_auth/front_auth-' . date('Y-m-d') . '.log');
        @unlink($logFile);

        // /user/login 归属 front_auth 模块。
        $this->get(route('legacy_user_login_page', ['langId' => 1]));

        $content = @file_get_contents($logFile);
        $this->assertNotFalse($content, 'front_auth 模块日志文件必须生成');
        $this->assertStringContainsString('front_auth=请求参数', (string) $content, '必须记录请求参数日志');
        $this->assertStringContainsString('front_auth=响应日志', (string) $content, '必须记录响应日志');
        $this->assertStringContainsString('"request_id"', (string) $content, '日志必须携带 request_id');
        $this->assertStringContainsString('"trace_id"', (string) $content, '日志必须携带 trace_id');

        @unlink($logFile);
    }

    /**
     * 未命中任何模块的请求不写模块日志，但仍注入链路标识。
     *
     * @return void 断言通过不返回值。
     */
    public function test_unmatched_request_only_carries_trace_ids_without_module_log(): void
    {
        Log::spy();

        $response = $this->get('/whstest');
        // fail-closed 423 为既有契约；链路标识仍必须注入。
        $this->assertNotEmpty((string) $response->headers->get('X-Request-Id', ''));
        $this->assertNotEmpty((string) $response->headers->get('X-Trace-Id', ''));

        // 未命中模块：只注入标识，不调用任何模块 channel。
        Log::shouldNotHaveReceived('channel');
    }

    /**
     * RequestTrace 标识生成与模块前缀映射配置一致性（22 个模块、排除礼品与资讯）。
     *
     * @return void 断言通过不返回值。
     */
    public function test_trace_module_config_matches_agreed_scope(): void
    {
        $modules = array_keys((array) config('trace.modules', []));
        sort($modules);
        $expected = [
            'admin_agent', 'admin_auth', 'admin_auth_review', 'admin_cancel',
            'admin_deposit', 'admin_fund', 'admin_online', 'admin_permission',
            'admin_risk', 'admin_system', 'admin_trade', 'admin_user',
            'admin_withdraw', 'front_agent', 'front_auth', 'front_commission',
            'front_deposit', 'front_flow', 'front_position', 'front_profile',
            'front_trade', 'front_withdraw',
        ];
        $this->assertSame($expected, $modules, '模块清单必须与确认的 22 个模块一致（排除 front_gift/admin_news）');

        // 每个模块必须有同名日志通道（daily / 7 天）。
        foreach ($modules as $module) {
            $channel = config('logging.channels.' . $module, null);
            $this->assertIsArray($channel, "模块 {$module} 必须存在同名日志通道");
            $this->assertSame('daily', $channel['driver'] ?? null, "模块 {$module} 通道必须为 daily");
            $this->assertSame(7, $channel['days'] ?? null, "模块 {$module} 通道保留天数必须为 7");
        }
    }

    /**
     * 敏感参数（密码类）与敏感 Header 必须在日志中脱敏。
     *
     * 说明：直接断言真实日志文件中的掩码结果，测试后清理产物。
     *
     * @return void 断言通过不返回值。
     */
    public function test_sensitive_fields_are_masked_in_logs(): void
    {
        $logFile = storage_path('logs/front_auth/front_auth-' . date('Y-m-d') . '.log');
        @unlink($logFile);

        $this->post(route('legacy_user_sign_in'), [
            'loginUid' => 'demo@test.com',
            'loginPassword' => 'super-secret-password',
        ]);

        $content = (string) @file_get_contents($logFile);
        $this->assertNotFalse($content, 'front_auth 模块日志文件必须生成');
        $this->assertStringNotContainsString('super-secret-password', $content, '密码明文不得出现在日志中');
        $this->assertStringContainsString('******', $content, '敏感字段必须掩码为 ******');

        @unlink($logFile);
    }
}
