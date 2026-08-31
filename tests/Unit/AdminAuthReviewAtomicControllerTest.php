<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/07
 * Time: 19:05
 */

/**
 * AdminAuthReviewAtomicControllerTest
 *
 * 文件功能：
 * - 验证实名审核原子控制器：委托规范化决定与审计上下文、MT4 持久失败状态映射、进行中审核映射 operation not allowed、缺失记录映射 data not found、超长理由与未登录管理员在处理前拒绝。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Unit;

use App\Constants\ResponseCode;
use App\Http\Controllers\Admin\AdminUserController;
use App\Models\Admin;
use App\Services\AdminAuthReviewProcessor;
use App\Services\AdminDataScopeService;
use App\Services\Mt4ManagerService;
use App\Services\UserStatisticsService;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

final class AdminAuthReviewAtomicControllerTest extends TestCase
{
    public function test_controller_delegates_normalized_decisions_and_audit_context(): void
    {
        $admin = $this->admin();
        $scope = $this->allowedScope($admin, 901001);
        $statistics = Mockery::mock(UserStatisticsService::class);
        $mt4 = Mockery::mock(Mt4ManagerService::class);
        $mt4->shouldNotReceive('updateComment');
        $processor = Mockery::mock(AdminAuthReviewProcessor::class);
        $processor->shouldReceive('submit')->once()->with(
            901001,
            [
                'id_card_decision' => 1,
                'id_card_reason' => '',
            ],
            Mockery::on(function (array $context): bool {
                return $context === [
                    'admin_id' => 1,
                    'admin_name' => 'atomic-review-admin',
                    'request_ip' => '127.0.0.1',
                    'status_label' => 'component',
                    'id_card_decision_label' => '1',
                    'bank_decision_label' => 'none',
                ];
            })
        )->andReturn(['status' => 'processed']);

        $request = $this->request($admin, [
            'user_id' => 901001,
            'id_card_decision' => 1,
        ]);
        $response = (new AdminUserController($scope, $statistics, $mt4, $processor))
            ->reviewAuth($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(ResponseCode::SUCCESS, $response->getData(true)['code']);
    }

    /**
     * @dataProvider processorFailureProvider
     */
    public function test_controller_maps_durable_mt4_failure_states(
        string $status,
        string $errorCode
    ): void {
        $admin = $this->admin();
        $scope = $this->allowedScope($admin, 901002);
        $statistics = Mockery::mock(UserStatisticsService::class);
        $mt4 = Mockery::mock(Mt4ManagerService::class);
        $mt4->shouldNotReceive('updateComment');
        $processor = Mockery::mock(AdminAuthReviewProcessor::class);
        $processor->shouldReceive('submit')->once()->andReturn([
            'status' => $status,
            'outbox_id' => 88,
            'error_code' => $errorCode,
        ]);

        $response = (new AdminUserController($scope, $statistics, $mt4, $processor))
            ->reviewAuth($this->request($admin, [
                'user_id' => 901002,
                'bank_decision' => 1,
            ]));
        $body = $response->getData(true);

        $this->assertSame(ResponseCode::MT4_SYNC_FAILED, $body['code']);
        $this->assertSame([
            'user_id' => 901002,
            'outbox_id' => 88,
            'status' => $status,
            'error_code' => $errorCode,
        ], $body['data']);
    }

    public static function processorFailureProvider(): array
    {
        return [
            'definitely not sent' => ['retryable', 'connection_failed'],
            'provider rejected' => ['rejected', 'invalid_bank'],
            'result uncertain' => ['unknown', 'read_timeout'],
            'another worker processing' => ['processing', ''],
        ];
    }

    public function test_controller_maps_an_active_review_to_operation_not_allowed(): void
    {
        $admin = $this->admin();
        $scope = $this->allowedScope($admin, 901003);
        $processor = Mockery::mock(AdminAuthReviewProcessor::class);
        $processor->shouldReceive('submit')->once()->andReturn([
            'status' => 'conflict',
            'outbox_id' => 89,
        ]);

        $response = (new AdminUserController(
            $scope,
            Mockery::mock(UserStatisticsService::class),
            Mockery::mock(Mt4ManagerService::class),
            $processor
        ))->reviewAuth($this->request($admin, [
            'user_id' => 901003,
            'id_card_decision' => 1,
        ]));

        $this->assertSame(ResponseCode::OPERATION_NOT_ALLOWED, $response->getData(true)['code']);
    }

    public function test_controller_maps_a_missing_auth_record_to_data_not_found(): void
    {
        $admin = $this->admin();
        $scope = $this->allowedScope($admin, 901004);
        $processor = Mockery::mock(AdminAuthReviewProcessor::class);
        $processor->shouldReceive('submit')->once()->andReturn(['status' => 'missing']);

        $response = (new AdminUserController(
            $scope,
            Mockery::mock(UserStatisticsService::class),
            Mockery::mock(Mt4ManagerService::class),
            $processor
        ))->reviewAuth($this->request($admin, [
            'user_id' => 901004,
            'id_card_decision' => 1,
        ]));

        $this->assertSame(ResponseCode::DATA_NOT_FOUND, $response->getData(true)['code']);
    }

    /**
     * @dataProvider overlongReviewReasonProvider
     * @param array<string, int|string> $payload
     */
    public function test_controller_rejects_overlong_review_reasons_before_processing(array $payload): void
    {
        $admin = $this->admin();
        $scope = Mockery::mock(AdminDataScopeService::class);
        $scope->shouldNotReceive('canAccessUser');
        $processor = Mockery::mock(AdminAuthReviewProcessor::class);
        $processor->shouldNotReceive('submit');

        $response = (new AdminUserController(
            $scope,
            Mockery::mock(UserStatisticsService::class),
            Mockery::mock(Mt4ManagerService::class),
            $processor
        ))->reviewAuth($this->request($admin, ['user_id' => 901006] + $payload));

        $this->assertSame(ResponseCode::VALIDATION_FAILED, $response->getData(true)['code']);
    }

    public static function overlongReviewReasonProvider(): array
    {
        $reason = str_repeat('x', 501);

        return [
            'shared reason' => [['status' => 2, 'reason' => $reason]],
            'ID card reason' => [['id_card_decision' => 2, 'id_card_reason' => $reason]],
            'bank reason' => [['bank_decision' => 2, 'bank_reason' => $reason]],
        ];
    }

    public function test_controller_rejects_an_unauthenticated_admin_before_scope_or_processor(): void
    {
        $scope = Mockery::mock(AdminDataScopeService::class);
        $scope->shouldNotReceive('canAccessUser');
        $processor = Mockery::mock(AdminAuthReviewProcessor::class);
        $processor->shouldNotReceive('submit');
        $request = Request::create('/api/admin/reviewAuth', 'POST', [
            'user_id' => 901005,
            'id_card_decision' => 1,
        ]);

        $response = (new AdminUserController(
            $scope,
            Mockery::mock(UserStatisticsService::class),
            Mockery::mock(Mt4ManagerService::class),
            $processor
        ))->reviewAuth($request);

        $this->assertSame(ResponseCode::PERMISSION_DENIED, $response->getData(true)['code']);
    }

    private function admin(): Admin
    {
        $admin = new Admin();
        $admin->forceFill(['id' => 1, 'username' => 'atomic-review-admin']);

        return $admin;
    }

    private function allowedScope(Admin $admin, int $userId): AdminDataScopeService
    {
        $scope = Mockery::mock(AdminDataScopeService::class);
        $scope->shouldReceive('canAccessUser')->once()->with($admin, $userId, 'user')->andReturnTrue();

        return $scope;
    }

    /**
     * @param array<string, int|string> $payload
     */
    private function request(Admin $admin, array $payload): Request
    {
        $request = Request::create('/api/admin/reviewAuth', 'POST', $payload, [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        $request->setUserResolver(function ($guard = null) use ($admin) {
            return $guard === 'admin' ? $admin : null;
        });

        return $request;
    }
}
