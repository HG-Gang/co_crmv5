<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/23
 * Time: 05:33
 */

/**
 * AdminLegacyMutationMethodBoundaryClosureTest
 *
 * 文件功能：
 * - 验证旧后台变更边界：匿名 GET/HEAD 不进入变更目标，变更分类器覆盖审核确认、对账与强制下线目标，业务写 SQL 被禁止。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Models\Admin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 旧后台的变更型 GET/HEAD 只能被识别为方法错误，不能被转成现代 POST 写请求。
 * 这样可以阻止链接预取、健康探测或重复刷新触发删除、启停等副作用。
 */
class AdminLegacyMutationMethodBoundaryClosureTest extends TestCase
{
    /**
     * @dataProvider legacyMutationUris
     */
    public function test_authenticated_legacy_get_does_not_enter_mutation_target(string $uri): void
    {
        $this->authenticateLegacyAdmin();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson('/' . $uri);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertStatus(405)
            ->assertJsonPath('code', ResponseCode::OPERATION_NOT_ALLOWED)
            ->assertJsonPath('data.legacy_uri', $uri);
        $this->assertNoMutationQueries($queries);
    }

    /**
     * @dataProvider legacyMutationUris
     */
    public function test_authenticated_legacy_head_does_not_enter_mutation_target(string $uri): void
    {
        $this->authenticateLegacyAdmin();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->call('HEAD', '/' . $uri);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Symfony 按 HTTP 规范会移除 HEAD 响应体，因此这里只验证状态和 Allow 头；
        // GET 用例已经验证同一分支返回的业务码与旧 URI。
        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('POST', $response->headers->get('Allow'));
        $this->assertNoMutationQueries($queries);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function legacyMutationUris(): array
    {
        return [
            'delete admin' => ['index/admin/Administrators/del'],
            'start admin' => ['index/admin/Administrators/start'],
            'delete role' => ['index/admin/role/del'],
            'stop big agent' => ['index/admin/bigAgents/stop'],
        ];
    }

    public function test_mutation_classifier_covers_review_confirmation_reconcile_and_force_offline_targets(): void
    {
        $controller = new \App\Http\Controllers\Admin\LegacyAdminController();
        $method = new \ReflectionMethod($controller, 'isMutationTargetRoute');
        $method->setAccessible(true);

        foreach ([
            'admin_api_confirmAgent',
            'admin_api_reviewAuth',
            'admin_api_commissionTransferReconcile',
            'admin_api_forceOfflineUser',
            'admin_api_whsExpZero',
        ] as $routeName) {
            $this->assertTrue($method->invoke($controller, $routeName), $routeName);
        }

        foreach ([
            'admin_api_agentList',
            'admin_api_exportDeposits',
            'admin_api_reconciliationCase',
        ] as $routeName) {
            $this->assertFalse($method->invoke($controller, $routeName), $routeName);
        }
    }

    private function authenticateLegacyAdmin(): void
    {
        $admin = new Admin();
        $admin->id = 1;
        $admin->status = 1;
        Auth::guard('admin')->setUser($admin);
    }

    /**
     * 权限中间件可以合法执行 SELECT；这里真正要禁止的是业务写 SQL。
     *
     * @param array<int, array{query: string}> $queries
     */
    private function assertNoMutationQueries(array $queries): void
    {
        $mutations = array_values(array_filter($queries, static function (array $query): bool {
            return preg_match('/^\s*(insert|update|delete|replace|alter|drop|truncate)\b/i', (string) ($query['query'] ?? '')) === 1;
        }));

        $this->assertSame([], $mutations, '变更型 legacy GET/HEAD 不应进入现代写链。');
    }
}
