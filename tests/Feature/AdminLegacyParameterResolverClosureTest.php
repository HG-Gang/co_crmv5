<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:46
 */

/**
 * 文件功能：验证 legacy 控制器 LegacyAdminController 的私有参数解析器
 *           （routeParametersFor）对各路由 ID 别名字段的解析行为。
 *
 * 适用场景：/index/admin/group/user_group_delete、/index/admin/amount/updateCurrOrderId
 *           等 legacy 路由参数别名的单元级回归测试。
 *
 * 入参例子：
 * - POST /index/admin/group/user_group_delete：{grp_recId, user_id}
 * - POST /index/admin/amount/updateCurrOrderId：{recordId}
 *
 * 返回值：
 * - 使用 grp_recId/recordId 别名解析出路由参数 id；
 * - 缺失别名字段时解析结果为 null，不跨域回退到 user_id。
 *
 * 异常或失败场景：
 * - 缺少对应别名字段时 id 为 null，调用方应走校验失败分支。
 */

namespace Tests\Feature;

use App\Http\Controllers\Admin\LegacyAdminController;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

class AdminLegacyParameterResolverClosureTest extends TestCase
{
    // 分组删除路由应使用 grp_recId 别名解析 id，缺失时不得回退到 user_id。
    public function test_group_delete_uses_grp_rec_id_alias_without_cross_domain_user_id_fallback(): void
    {
        $controller = new LegacyAdminController();
        $method = new ReflectionMethod($controller, 'routeParametersFor');
        $method->setAccessible(true);

        $request = Request::create('/index/admin/group/user_group_delete', 'POST', [
            'grp_recId' => 7301,
            'user_id' => 991001,
        ]);

        $this->assertSame(
            7301,
            $method->invoke($controller, 'user-groups/{id}', $request)['id']
        );

        $missingAlias = Request::create('/index/admin/group/user_group_delete', 'POST', [
            'user_id' => 991001,
        ]);
        $this->assertNull($method->invoke($controller, 'user-groups/{id}', $missingAlias)['id']);
    }

    // 更新当前订单 ID 路由应使用 recordId 别名解析 id，缺失时为 null。
    public function test_update_current_order_id_uses_record_id_alias_and_missing_required_id_is_null(): void
    {
        $controller = new LegacyAdminController();
        $method = new ReflectionMethod($controller, 'routeParametersFor');
        $method->setAccessible(true);

        $request = Request::create('/index/admin/amount/updateCurrOrderId', 'POST', [
            'recordId' => 7302,
        ]);

        $this->assertSame(
            7302,
            $method->invoke($controller, 'withdrawals/{id}', $request)['id']
        );

        $missing = Request::create('/index/admin/amount/updateCurrOrderId', 'POST');
        $this->assertNull($method->invoke($controller, 'withdrawals/{id}', $missing)['id']);
    }
}
