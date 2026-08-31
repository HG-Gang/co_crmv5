<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 03:48
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Controllers\Front\LegacyMaintenanceController;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 旧公开维护与调试入口运行闭环测试。
 *
 * 文件功能：逐条执行项目1遗留的导入、MT4 同步、注册通知、测试资金、测试短信和测试模型入口，
 * 验证项目2只保留 URI 兼容并统一返回 423。这样既能让旧调用方得到明确中文失败结果，也能
 * 阻止匿名请求继续触发导入、外部通知、账户查询或资金写入。
 */
class FrontLegacyMaintenanceRuntimeClosureModuleTest extends TestCase
{
    /**
     * 提供所有已归类为公开维护/调试入口的 HTTP 方法、URI 和旧动作名。
     *
     * @return array<string, array{method:string,uri:string,route_uri?:string,action:string}> 显式路由清单，不使用通配符。
     */
    public static function disabledLegacyMaintenanceRouteProvider(): array
    {
        return [
            'import-user' => ['method' => 'GET', 'uri' => '/importUser', 'action' => 'importUser'],
            'import-agents' => ['method' => 'GET', 'uri' => '/importAgents', 'action' => 'importAgents'],
            'import-language' => ['method' => 'GET', 'uri' => '/importLang', 'action' => 'importLang'],
            'sync-local-agents' => ['method' => 'GET', 'uri' => '/syncToT4ByLocalAgents', 'action' => 'syncToT4ByLocalAgents'],
            'sync-local-user' => ['method' => 'POST', 'uri' => '/syncToT4ByLocalUser', 'action' => 'syncToT4ByLocalUser'],
            'local-agent-register-notify' => ['method' => 'POST', 'uri' => '/localRegisterNotifyByAgents', 'action' => 'localRegisterNotifyByAgents'],
            'sync-agents' => ['method' => 'POST', 'uri' => '/syncAgents', 'action' => 'syncAgents'],
            'sync-user' => ['method' => 'POST', 'uri' => '/syncUser', 'action' => 'syncUser'],
            'sync-disabled-user' => ['method' => 'POST', 'uri' => '/syncDisableUserToT4', 'action' => 'syncDisableUserToT4'],
            'test-register-page' => ['method' => 'GET', 'uri' => '/test', 'action' => 'testRegisterPage'],
            'test-register-write' => ['method' => 'POST', 'uri' => '/test/helloRegister', 'action' => 'testHelloRegister'],
            'test-deposit' => ['method' => 'POST', 'uri' => '/test/deposit', 'action' => 'testDeposit'],
            'test-withdraw' => ['method' => 'POST', 'uri' => '/test/withdraw', 'action' => 'testWithdraw'],
            'test-account-info' => ['method' => 'POST', 'uri' => '/test/getAccountInfo', 'action' => 'testGetAccountInfo'],
            'test-rights-sum' => ['method' => 'GET', 'uri' => '/test_rights_sum', 'action' => 'testRightsSum'],
            'test-info' => ['method' => 'GET', 'uri' => '/test_info', 'action' => 'testInfo'],
            'test-sms' => ['method' => 'GET', 'uri' => '/test_sms', 'action' => 'testSms'],
            'test-search' => [
                'method' => 'GET',
                'uri' => '/test_serach/123',
                'action' => 'testSearch',
                'route_uri' => 'test_serach/{id}',
            ],
            'test-export' => ['method' => 'POST', 'uri' => '/test_export', 'action' => 'testExport'],
            'test-order' => ['method' => 'GET', 'uri' => '/test_order', 'action' => 'testOrder'],
            'trades-exp-zero' => ['method' => 'GET', 'uri' => '/trades_exp_zero', 'action' => 'tradesExpZero'],
            'whs-test' => ['method' => 'GET', 'uri' => '/whstest', 'action' => 'whsTest'],
            'register-test-model' => ['method' => 'GET', 'uri' => '/user/register/testmodel', 'action' => 'testmodel'],
            'register-rebate-deposit' => ['method' => 'GET', 'uri' => '/user/register/rebateDeposit', 'action' => 'orderRebateDeposit'],
        ];
    }

    /**
     * 验证所有指向旧维护控制器的运行时路由都进入逐路由失败关闭测试。
     *
     * 参数路由使用 route_uri 保存模板做集合比较，uri 则保留可直接发起请求的实际值；
     * 这样新增维护路由时必须同步说明请求样例和旧动作，不能只注册路由而漏掉安全回归。
     */
    public function test_disabled_legacy_maintenance_routes_match_registered_routes(): void
    {
        $provided = collect(self::disabledLegacyMaintenanceRouteProvider())
            ->map(static function (array $case): string {
                return strtoupper($case['method']) . ' ' . ($case['route_uri'] ?? ltrim($case['uri'], '/'));
            })
            ->sort()
            ->values()
            ->all();

        $registered = collect(Route::getRoutes()->getRoutes())
            ->filter(static function ($route): bool {
                return strpos((string) $route->getActionName(), LegacyMaintenanceController::class . '@') === 0;
            })
            ->flatMap(static function ($route): array {
                return collect($route->methods())
                    ->reject(static function (string $method): bool {
                        return strtoupper($method) === 'HEAD';
                    })
                    ->map(static function (string $method) use ($route): string {
                        return strtoupper($method) . ' ' . $route->uri();
                    })
                    ->all();
            })
            ->sort()
            ->values()
            ->all();

        $this->assertSame($registered, $provided);
    }

    /**
     * 每条旧维护入口都必须返回相同禁用协议，并回显实际命中的旧动作与路径。
     *
     * @dataProvider disabledLegacyMaintenanceRouteProvider
     * @param string $method HTTP 方法。
     * @param string $uri 旧项目兼容 URI。
     * @param string $action 旧动作标识。
     * @param string|null $routeUri 参数路由模板，仅供数据提供器完整性检查使用。
     * @return void
     */
    public function test_each_disabled_legacy_entrypoint_returns_locked_protocol(
        string $method,
        string $uri,
        string $action,
        string $routeUri = null
    ): void {
        $response = $this->json($method, $uri, [
            'user_id' => 990099999,
            'amount' => '999999.99',
        ]);

        $response->assertStatus(423)
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED)
            ->assertJsonPath('message', __('response.legacy_maintenance_disabled'))
            ->assertJsonPath('data.legacy_action', $action)
            ->assertJsonPath('data.path', ltrim($uri, '/'));
    }
}
