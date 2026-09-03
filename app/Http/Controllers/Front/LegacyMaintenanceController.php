<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\Front;

use App\Constants\ResponseCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 旧维护入口控制器。
 *
 * 文件功能：
 * - 承接旧项目中导入用户、导入代理、同步到 MT4、注册通知、测试入金、测试出金等公开维护路由。
 * - 这些入口在旧项目中多用于临时同步或调试，迁移到当前 Laravel 后不能继续作为公开 Web 动作执行。
 * - 当前控制器统一返回公开路由禁用响应，并记录访问日志，后续如需恢复必须迁移到 protected console command or admin task。
 *
 * 安全边界：
 * - 危险操作警示：导入、同步、测试入金/出金均为资金与数据敏感动作，本控制器一律返回 423 禁用，不执行任何写入。
 * - 如实标注现状：当前请求源未做 IP 或来源白名单限制（仅记录 path 与 ip 审计日志），任何能触达该路由的调用方都会收到禁用响应而非被放行。
 * - 恢复任何入口前必须迁移为受控的 console command 或后台任务并加来源限制，不得直接放开本控制器。
 */
class LegacyMaintenanceController extends FrontBaseController
{
    /**
     * 旧导入用户入口：仅保留路由兼容，不执行导入用户逻辑。
     *
     * @param Request $request 当前 HTTP 请求对象，用于记录请求路径、IP 和旧动作名。
     * @return JsonResponse 423 禁用响应。
     */
    public function importUser(Request $request): JsonResponse
    {
        return $this->disabledMaintenanceResponse($request, 'importUser');
    }

    /**
     * 旧导入代理入口：仅保留路由兼容，不执行导入代理逻辑。
     *
     * @param Request $request 当前 HTTP 请求对象，用于记录请求路径、IP 和旧动作名。
     * @return JsonResponse 423 禁用响应。
     */
    public function importAgents(Request $request): JsonResponse
    {
        return $this->disabledMaintenanceResponse($request, 'importAgents');
    }

    /**
     * 旧本地代理同步到 MT4 入口：仅保留路由兼容，不执行同步到 MT4 逻辑。
     *
     * @param Request $request 当前 HTTP 请求对象，用于记录请求路径、IP 和旧动作名。
     * @return JsonResponse 423 禁用响应。
     */
    public function syncToT4ByLocalAgents(Request $request): JsonResponse
    {
        return $this->disabledMaintenanceResponse($request, 'syncToT4ByLocalAgents');
    }

    /**
     * 旧本地用户同步到 MT4 入口：仅保留路由兼容，不执行用户同步逻辑。
     *
     * @param Request $request 当前 HTTP 请求对象，用于记录请求路径、IP 和旧动作名。
     * @return JsonResponse 423 禁用响应。
     */
    public function syncToT4ByLocalUser(Request $request): JsonResponse
    {
        return $this->disabledMaintenanceResponse($request, 'syncToT4ByLocalUser');
    }

    /**
     * 旧代理注册通知入口：仅保留路由兼容，不执行外部注册通知逻辑。
     *
     * @param Request $request 当前 HTTP 请求对象，用于记录请求路径、IP 和旧动作名。
     * @return JsonResponse 423 禁用响应。
     */
    public function localRegisterNotifyByAgents(Request $request): JsonResponse
    {
        return $this->disabledMaintenanceResponse($request, 'localRegisterNotifyByAgents');
    }

    /**
     * 旧代理同步入口：仅保留路由兼容，不执行同步逻辑。
     *
     * @param Request $request 当前 HTTP 请求对象，用于记录请求路径、IP 和旧动作名。
     * @return JsonResponse 423 禁用响应。
     */
    public function syncAgents(Request $request): JsonResponse
    {
        return $this->disabledMaintenanceResponse($request, 'syncAgents');
    }

    /**
     * 旧用户同步入口：仅保留路由兼容，不执行同步逻辑。
     *
     * @param Request $request 当前 HTTP 请求对象，用于记录请求路径、IP 和旧动作名。
     * @return JsonResponse 423 禁用响应。
     */
    public function syncUser(Request $request): JsonResponse
    {
        return $this->disabledMaintenanceResponse($request, 'syncUser');
    }

    /**
     * 旧禁用用户同步到 MT4 入口：仅保留路由兼容，不执行同步逻辑。
     *
     * @param Request $request 当前 HTTP 请求对象，用于记录请求路径、IP 和旧动作名。
     * @return JsonResponse 423 禁用响应。
     */
    public function syncDisableUserToT4(Request $request): JsonResponse
    {
        return $this->disabledMaintenanceResponse($request, 'syncDisableUserToT4');
    }

    /**
     * 旧语言导入入口：仅保留路由兼容，不执行语言导入逻辑。
     *
     * @param Request $request 当前 HTTP 请求对象，用于记录请求路径、IP 和旧动作名。
     * @return JsonResponse 423 禁用响应。
     */
    public function importLang(Request $request): JsonResponse
    {
        return $this->disabledMaintenanceResponse($request, 'importLang');
    }

    /**
     * 旧模型测试入口：仅保留路由兼容，不执行测试逻辑。
     *
     * @param Request $request 当前 HTTP 请求对象，用于记录请求路径、IP 和旧动作名。
     * @return JsonResponse 423 禁用响应。
     */
    public function testmodel(Request $request): JsonResponse
    {
        return $this->disabledMaintenanceResponse($request, 'testmodel');
    }

    /**
     * 旧返佣入金测试入口：仅保留路由兼容，不执行资金或返佣写入逻辑。
     *
     * @param Request $request 当前 HTTP 请求对象，用于记录请求路径、IP 和旧动作名。
     * @return JsonResponse 423 禁用响应。
     */
    public function orderRebateDeposit(Request $request): JsonResponse
    {
        return $this->disabledMaintenanceResponse($request, 'orderRebateDeposit');
    }

    /**
     * 旧注册页面测试入口：仅保留路由兼容，不执行测试逻辑。
     *
     * @param Request $request 当前 HTTP 请求对象，用于记录请求路径、IP 和旧动作名。
     * @return JsonResponse 423 禁用响应。
     */
    public function testRegisterPage(Request $request): JsonResponse
    {
        return $this->disabledMaintenanceResponse($request, 'testRegisterPage');
    }

    /**
     * 旧注册问候测试入口：仅保留路由兼容，不执行注册测试逻辑。
     *
     * @param Request $request 当前 HTTP 请求对象，用于记录请求路径、IP 和旧动作名。
     * @return JsonResponse 423 禁用响应。
     */
    public function testHelloRegister(Request $request): JsonResponse
    {
        return $this->disabledMaintenanceResponse($request, 'testHelloRegister');
    }

    /**
     * 旧测试入金入口：仅保留路由兼容，不执行 testDeposit 资金写入逻辑。
     *
     * @param Request $request 当前 HTTP 请求对象，用于记录请求路径、IP 和旧动作名。
     * @return JsonResponse 423 禁用响应。
     */
    public function testDeposit(Request $request): JsonResponse
    {
        return $this->disabledMaintenanceResponse($request, 'testDeposit');
    }

    /**
     * 旧测试出金入口：仅保留路由兼容，不执行 testWithdraw 资金写入逻辑。
     *
     * @param Request $request 当前 HTTP 请求对象，用于记录请求路径、IP 和旧动作名。
     * @return JsonResponse 423 禁用响应。
     */
    public function testWithdraw(Request $request): JsonResponse
    {
        return $this->disabledMaintenanceResponse($request, 'testWithdraw');
    }

    /**
     * 旧账户信息测试入口：仅保留路由兼容，不执行测试逻辑。
     *
     * @param Request $request 当前 HTTP 请求对象，用于记录请求路径、IP 和旧动作名。
     * @return JsonResponse 423 禁用响应。
     */
    public function testGetAccountInfo(Request $request): JsonResponse
    {
        return $this->disabledMaintenanceResponse($request, 'testGetAccountInfo');
    }

    /**
     * 旧权益汇总测试入口：仅保留路由兼容，不执行测试逻辑。
     *
     * @param Request $request 当前 HTTP 请求对象，用于记录请求路径、IP 和旧动作名。
     * @return JsonResponse 423 禁用响应。
     */
    public function testRightsSum(Request $request): JsonResponse
    {
        return $this->disabledMaintenanceResponse($request, 'testRightsSum');
    }

    /**
     * 旧信息测试入口：仅保留路由兼容，不执行测试逻辑。
     *
     * @param Request $request 当前 HTTP 请求对象，用于记录请求路径、IP 和旧动作名。
     * @return JsonResponse 423 禁用响应。
     */
    public function testInfo(Request $request): JsonResponse
    {
        return $this->disabledMaintenanceResponse($request, 'testInfo');
    }

    /**
     * 旧短信测试入口：仅保留路由兼容，不执行短信发送逻辑。
     *
     * @param Request $request 当前 HTTP 请求对象，用于记录请求路径、IP 和旧动作名。
     * @return JsonResponse 423 禁用响应。
     */
    public function testSms(Request $request): JsonResponse
    {
        return $this->disabledMaintenanceResponse($request, 'testSms');
    }

    /**
     * 旧搜索测试入口：仅保留路由兼容，不执行测试查询逻辑。
     *
     * 参数含义：
     * - $id：旧路由 `test_serach/{id}` 传入的测试标识，当前只保留签名兼容。
     *
     * @param Request $request 当前 HTTP 请求对象，用于记录请求路径、IP 和旧动作名。
     * @param int|string $id 旧测试搜索标识。
     * @return JsonResponse 423 禁用响应。
     */
    public function testSearch(Request $request, $id): JsonResponse
    {
        return $this->disabledMaintenanceResponse($request, 'testSearch');
    }

    /**
     * 旧导出测试入口：仅保留路由兼容，不执行导出逻辑。
     *
     * @param Request $request 当前 HTTP 请求对象，用于记录请求路径、IP 和旧动作名。
     * @return JsonResponse 423 禁用响应。
     */
    public function testExport(Request $request): JsonResponse
    {
        return $this->disabledMaintenanceResponse($request, 'testExport');
    }

    /**
     * 旧订单测试入口：仅保留路由兼容，不执行测试逻辑。
     *
     * @param Request $request 当前 HTTP 请求对象，用于记录请求路径、IP 和旧动作名。
     * @return JsonResponse 423 禁用响应。
     */
    public function testOrder(Request $request): JsonResponse
    {
        return $this->disabledMaintenanceResponse($request, 'testOrder');
    }

    /**
     * 旧交易到期归零测试入口：仅保留路由兼容，不执行交易修正逻辑。
     *
     * @param Request $request 当前 HTTP 请求对象，用于记录请求路径、IP 和旧动作名。
     * @return JsonResponse 423 禁用响应。
     */
    public function tradesExpZero(Request $request): JsonResponse
    {
        return $this->disabledMaintenanceResponse($request, 'tradesExpZero');
    }

    /**
     * 旧 WSH 测试入口：仅保留路由兼容，不执行测试逻辑。
     *
     * @param Request $request 当前 HTTP 请求对象，用于记录请求路径、IP 和旧动作名。
     * @return JsonResponse 423 禁用响应。
     */
    public function whsTest(Request $request): JsonResponse
    {
        return $this->disabledMaintenanceResponse($request, 'whsTest');
    }

    /**
     * 统一生成旧维护入口禁用响应。
     *
     * 参数和字段含义：
     * - $request：当前 HTTP 请求对象，用于读取 path、ip 等审计信息。
     * - legacyAction 表示旧项目维护入口动作名，例如 importUser、syncToT4ByLocalAgents、testDeposit。
     * - action 表示写入日志的旧动作名称，便于排查是否仍有外部系统调用旧维护入口。
     * - path 表示触发旧维护入口的请求路径，便于定位来源页面或脚本。
     * - legacy_action 表示返回给调用方的旧动作标识，方便旧调用方和测试用例确认命中的禁用入口。
     *
     * @param Request $request 当前 HTTP 请求对象。
     * @param string $legacyAction 旧项目维护入口动作名。
     * @return JsonResponse 423 禁用响应。
     */
    private function disabledMaintenanceResponse(Request $request, string $legacyAction): JsonResponse
    {
        // 先记录审计日志（动作名、路径、IP），供排查是否仍有外部系统在调用旧维护入口。
        Log::warning('front.legacy_maintenance.disabled', [
            'action' => $legacyAction,
            'path' => $request->path(),
            'ip' => $request->ip(),
        ]);

        // 再统一返回 423：调用方只能看到禁用响应，任何旧动作都不会真正执行。
        return response()->json([
            'code' => ResponseCode::OPERATION_NOT_ALLOWED,
            'message' => __('response.legacy_maintenance_disabled'),
            'data' => [
                'legacy_action' => $legacyAction,
                'path' => $request->path(),
            ],
        ], 423);
    }
}
