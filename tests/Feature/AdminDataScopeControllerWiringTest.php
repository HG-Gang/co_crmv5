<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 21:59
 */

/**
 * AdminDataScopeControllerWiringTest
 *
 * 文件功能：
 * - 验证敏感后台列表控制器注入数据范围服务、单条记录控制器调用访问检查器，且佣金记录模型显式提供 parent 关系。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AgentController;
use App\Http\Controllers\Admin\CommissionController;
use App\Http\Controllers\Admin\DepositController;
use App\Http\Controllers\Admin\WithdrawController;
use App\Models\Admin;
use App\Models\CommissionRecord;
use App\Models\UserInfo;
use App\Services\AdminDataScopeService;
use App\Services\Mt4ManagerService;
use App\Services\UserStatisticsService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use ReflectionClass;
use Tests\TestCase;

/**
 * 后台数据范围控制器接入回归测试。
 *
 * 测试目标：
 * - 敏感列表控制器必须显式依赖 AdminDataScopeService。
 * - 敏感单条动作必须调用 canAccessUser() 或更严格的 canAccessRecord()，避免只限制列表。
 * - 返佣控制器使用的模型关系必须存在，避免 with(['agent', 'parent']) 运行时报错。
 */
class AdminDataScopeControllerWiringTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 核心列表控制器必须通过构造函数注入数据范围服务。
     *
     * @return void
     */
    public function test_sensitive_admin_list_controllers_inject_data_scope_service(): void
    {
        $controllers = [
            AdminUserController::class,
            AgentController::class,
            DepositController::class,
            WithdrawController::class,
            CommissionController::class,
        ];

        foreach ($controllers as $controllerClass) {
            $constructor = (new ReflectionClass($controllerClass))->getConstructor();

            $this->assertNotNull($constructor, $controllerClass . ' 必须定义构造函数。');
            $this->assertSame(
                AdminDataScopeService::class,
                $constructor->getParameters()[0]->getType()->getName(),
                $controllerClass . ' 必须注入 AdminDataScopeService。'
            );
        }
    }

    /**
     * 敏感单条控制器必须调用用户或记录级数据范围检查。
     *
     * @return void
     */
    public function test_sensitive_single_record_controllers_call_data_scope_access_checker(): void
    {
        $controllers = [
            AdminUserController::class,
            AgentController::class,
            DepositController::class,
            WithdrawController::class,
            CommissionController::class,
        ];

        foreach ($controllers as $controllerClass) {
            $source = file_get_contents((new ReflectionClass($controllerClass))->getFileName());

            $this->assertMatchesRegularExpression(
                '/canAccess(?:User|Record)\s*\(/',
                $source,
                $controllerClass . ' 的单条详情/审核/处理接口必须接入用户或记录级数据范围检查。'
            );
            $this->assertStringContainsString(
                'PERMISSION_DENIED',
                $source,
                $controllerClass . ' 单条越权时必须返回统一权限不足状态码。'
            );
        }
    }

    /**
     * 用户详情必须拒绝数据范围外的业务用户。
     *
     * @return void
     */
    public function test_admin_user_detail_denies_user_outside_data_scope(): void
    {
        $userId = random_int(960000, 979999);
        UserInfo::create([
            'user_id' => $userId,
            'login_id' => $userId,
            'user_name' => '不可见用户',
            'account_type' => 2,
        ]);

        $service = new class extends AdminDataScopeService {
            /**
             * 测试替身：强制模拟当前管理员不能访问任何单条业务用户。
             *
             * @param Admin $admin 当前后台管理员。
             * @param int|string $userId 业务用户ID，不是后台管理员ID。
             * @param string $targetType 目标类型：user=客户，agent=代理。
             * @return bool false=拒绝访问。
             */
            public function canAccessUser(Admin $admin, $userId, $targetType = 'user')
            {
                return false;
            }
        };

        $request = Request::create('/api/admin/userDetail', 'POST', ['user_id' => $userId]);
        $request->setUserResolver(function ($guard = null) {
            return $guard === 'admin' ? new Admin(['id' => 999]) : null;
        });

        $response = (new AdminUserController(
            $service,
            new UserStatisticsService(),
            app(Mt4ManagerService::class)
        ))->userDetail($request);
        $payload = $response->getData(true);

        $this->assertSame(ResponseCode::PERMISSION_DENIED, $payload['code']);
        $this->assertSame(__('response.permission_denied'), $payload['message']);
    }

    /**
     * CommissionController 使用 with(['agent', 'parent'])，模型必须显式提供 parent 关系。
     *
     * @return void
     */
    public function test_commission_record_defines_parent_relation_used_by_controller(): void
    {
        $relation = (new CommissionRecord())->parent();

        $this->assertInstanceOf(BelongsTo::class, $relation);
    }
}
