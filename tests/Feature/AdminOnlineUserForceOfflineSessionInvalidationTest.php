<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/07
 * Time: 21:24
 */

/**
 * AdminOnlineUserForceOfflineSessionInvalidationTest
 *
 * 文件功能：
 * - 验证在线用户强制下线的 JWT 失效闭环：活动 jti 缓存缺失时 SSO 拒绝 token、强下线控制器清理前台用户 SSO 状态并记录到最终清单。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Middleware\SingleSignOn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * 后台在线用户强制下线会话失效契约测试。
 *
 * 功能逻辑说明：
 * - 旧项目在线用户强制下线不能只删除列表记录，否则用户现有 JWT 仍可能继续访问前台接口。
 * - 本测试不连接数据库，只约束 SSO 中间件和后台控制器必须具备让当前前台 token 失效的基础闭环。
 */
class AdminOnlineUserForceOfflineSessionInvalidationTest extends TestCase
{
    /**
     * SSO 缓存缺失时必须拒绝请求，不能把已被清理的 token 当作有效会话。
     *
     * @return void
     */
    public function test_single_sign_on_rejects_token_when_active_jti_cache_is_missing(): void
    {
        Cache::forget('sso:user:991001');

        $request = Request::create('/api/front/profile', 'GET');
        $request->attributes->set('jwt_payload', (object) [
            'guard' => 'user',
            'sub' => 991001,
            'jti' => 'stale-jti-from-force-offline',
        ]);

        $response = (new SingleSignOn())->handle($request, function () {
            return response()->json(['passed' => true]);
        }, 'user');

        $payload = $response->getData(true);

        $this->assertArrayHasKey('code', $payload, 'SSO 缓存缺失时不应放行到后续控制器。');
        $this->assertSame(ResponseCode::SSO_CONFLICT, (int) $payload['code']);
        $this->assertSame(__('response.sso_conflict'), $payload['message']);
        $this->assertArrayNotHasKey('passed', $payload);
    }

    /**
     * 后台强制下线必须清理当前用户的前台 SSO 状态和登录表 token 标识。
     *
     * @return void
     */
    public function test_force_offline_controller_clears_front_user_sso_state(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/OnlineUserController.php')) ?: '';

        $this->assertStringContainsString('UserLogin::where', $source);
        $this->assertStringContainsString("UserLogin::where('user_id', (int) \$online->user_id)", $source);
        $this->assertStringContainsString('Cache::forget', $source);
        $this->assertStringContainsString('sso:user:', $source);
        $this->assertStringContainsString("'jwt_token_id' => ''", $source);
    }

    /**
     * 最终清单必须记录在线用户强制下线的当前 JWT 失效闭环。
     *
     * @return void
     */
    public function test_final_checklist_records_online_user_force_offline_session_invalidation(): void
    {
        $content = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        foreach ([
            '## 161. 2026-07-07 在线用户强制下线 JWT 失效闭环',
            '`SingleSignOn`',
            '`sso:user:{login_id}`',
            '`user_logins.jwt_token_id`',
            '`AdminOnlineUserForceOfflineSessionInvalidationTest`',
            '单设备下线、设备维度展示和缓存/心跳精细口径仍需继续迁移',
        ] as $expected) {
            $this->assertStringContainsString($expected, $content);
        }
    }
}
