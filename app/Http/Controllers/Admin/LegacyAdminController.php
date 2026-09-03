<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

/**
 * 旧版后台管理入口的兼容分发控制器。
 *
 * 文件功能：
 * - 承接旧项目 V3 后台遗留的页面与接口地址：所有旧 URI 统一注册到本控制器的 handle() 入口。
 * - 根据旧 URI 分发到现代后台 API（admin_api_* 命名路由）或旧版 Blade 页面渲染，让旧前端脚本不改地址即可继续工作。
 * - 提供旧字段（statue/msg/err/col、rows/total/footer、channel_N 等）与现代 code/message/data 响应格式的双向兼容。
 * - 静态方法 permissionRouteForLegacyUri / permissionRouteForLegacyRequest 供路由注册与 legacy.admin.auth
 *   中间件计算旧 URI 对应的现代权限点，保证旧入口也受 check.permission:admin 同等保护。
 *
 * 适用场景：
 * - 后台管理员通过旧地址访问各管理页面（/index/admin/...），例如管理员、代理、用户、入金、出金、资金流水、
 *   凭证审核、注销审核、批量出金、支付通道配置、权益确认、代理编辑保存、实名认证/银行卡审核等。
 * - 路由清单来自 storage/app/audits/legacy-routes.json，由 routes/legacy_admin.php 批量注册到本控制器。
 *
 * 入参例子：
 * - GET  /index/admin/login                          旧后台登录页。
 * - GET  /index/admin/users                          旧用户列表页（内部映射 admin_api_userList）。
 * - POST /index/admin/agents/agentsListSearch        旧代理列表查询。
 * - POST /index/admin/auth/voucherReviewSave?reviewstatus=1  旧凭证审核（reviewstatus=1 通过 / 2 拒绝）。
 * - POST /index/admin/amount/batchWithdrawApply      旧批量出金审核（payload.status：1 处理中 / 2 完成 / 3 拒绝）。
 * - POST /index/admin/withdraw/update_curr_order_id  旧出金订单号更新。
 * - POST /index/admin/channel/channel_save           旧支付通道批量配置（channel_N、channel_N_money、sort_N）。
 * - GET  /index/admin/rights/automatic_confirm       旧权益自动确认（当前无安全等价实现，返回失败）。
 *
 * 返回值：
 * - 页面类旧 URI 返回 Blade 视图（旧 Layui 页面）。
 * - 接口类旧 URI 返回现代 JSON 并尽量附带旧字段（statue/msg/err/col、rows/total/footer 等），
 *   使旧前端脚本无需改动即可判断成功失败。
 * - 新项目没有安全等价实现的写操作（如 MT4 自动权益确认、部分批量资金语义）返回
 *   410 或 OPERATION_NOT_ALLOWED 等显式失败，绝不伪造成功。
 *
 * 异常或失败场景：
 * - 参数校验失败返回 VALIDATION_FAILED，并附带旧字段 err/col 供旧页面提示。
 * - 现代目标接口不存在或写操作不被允许时返回 legacyMutationUnavailable() 的失败响应。
 * - 旧 URI 无法匹配现代路由且无页面可渲染时，由 handle() 返回 404/405 等兜底响应。
 */
namespace App\Http\Controllers\Admin;

use App\Contracts\DepositRefundGateway;
use App\Contracts\DepositSettlementGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use App\Constants\ResponseCode;
use App\Models\CancelApply;
use App\Models\GroupConfig;
use App\Models\OperationLog;
use App\Models\PaymentChannel;
use App\Models\VoucherInfo;
use App\Models\UserAuth;
use App\Models\UserInfo;
use App\Models\UserLogin;
use App\Models\WithdrawRecord;
use App\Models\WithdrawSettlementOutbox;
use App\Services\AdminDataScopeService;
use App\Services\AdminNewsQueryService;
use App\Services\CustomerGroupChangeApprovalService;
use App\Services\LegacyAdminCustomerChangeSearchService;
use App\Services\LegacyAdminCustomerSearchService;
use App\Services\LegacyAdminAgentStatisticsService;
use App\Services\LegacyRiskQueryService;
use App\Services\WithdrawRecordQueryService;
use App\Support\AuthReviewTransition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\InputBag;

class LegacyAdminController extends AdminBaseController
{
    /**
     * 旧后台礼品导出文件的有效期（秒），固定 3600 = 1 小时。
     * 导出文件本身含用户敏感数据（礼物明细、代理层级），过期文件会被后台清理任务与
     * web.php 下载入口的 expiresBefore 校验同时拒绝；缩短会打断正常下载节奏，放宽则拉长敏感文件暴露窗口。
     *
     * @var int
     */
    public const LEGACY_GIFT_EXPORT_TTL_SECONDS = 3600;

    /**
     * 根据旧后台 URI 计算对应的现代接口权限点。
     *
     * 功能逻辑说明：
     * - 路由注册阶段只能看到 URI，无法看到请求体，因此这里先按 URI 精确匹配已知写入口，
     *   再按页面模块映射到对应的 admin_api_* 权限点。
     * - 供 routes/legacy_admin.php 路由注册与 legacy.admin.auth 中间件使用，确保旧入口同样受接口权限保护。
     *
     * @param string $legacyUri 旧后台 URI，例如 index/admin/users、index/admin/agents。
     * @return string|null 现代权限点名称（如 admin_api_userList）；无法识别时返回 null。
     */
    public static function permissionRouteForLegacyUri(string $legacyUri): ?string
    {
        $controller = new static();
        $target = $controller->targetRouteFor($legacyUri);
        if ($target) {
            return $target['route'];
        }

        $riskPagePermissionMap = [
            'index/admin/fengXian/profit_list' => 'admin_api_riskProfitUsers',
            'index/admin/fengXian/position_list' => 'admin_api_riskPositions',
            'index/admin/fengXian/Ipaddress_list' => 'admin_api_riskIpList',
        ];
        if (isset($riskPagePermissionMap[$legacyUri])) {
            return $riskPagePermissionMap[$legacyUri];
        }

        $newsPagePermissionMap = [
            'index/admin/news/news_list_browse' => 'admin_api_newsList',
            'index/admin/news/news_add_browse' => 'admin_api_createNews',
            'index/admin/news/news_edit/{newsid}' => 'admin_api_updateNews',
        ];
        if (isset($newsPagePermissionMap[$legacyUri])) {
            return $newsPagePermissionMap[$legacyUri];
        }

        $giftPagePermissionMap = [
            'index/admin/gift/send_gift_browse' => 'admin_api_giftAddressList',
            'index/admin/gift/shipment_list_browse' => 'admin_api_giftShipmentList',
        ];
        if (isset($giftPagePermissionMap[$legacyUri])) {
            return $giftPagePermissionMap[$legacyUri];
        }

        $pagePermissionMap = [
            'admins' => 'admin_api_adminList',
            'agents' => 'admin_api_agentList',
            'authentications' => 'admin_api_authPendingList',
            'big-agents' => 'admin_api_bigAgentList',
            'channels' => 'admin_api_channelList',
            'deposits' => 'admin_api_depositList',
            'deposit-imports' => 'admin_api_depositImportList',
            'exchange-rates' => 'admin_api_exchangeRateInfo',
            'gifts' => 'admin_api_giftShipmentList',
            'group-configs' => 'admin_api_groupConfigList',
            'news' => 'admin_api_newsList',
            'online-users' => 'admin_api_onlineUserList',
            'position-summary' => 'admin_api_positionSummaryList',
            'productions' => 'admin_api_productionList',
            'rights-summary' => 'admin_api_rightsSummaryList',
            'roles' => 'admin_api_roleList',
            'risk' => 'admin_api_riskPositions',
            'trades' => 'admin_api_openPositions',
            'undeposit-flows' => 'admin_api_undepositFlowList',
            'users' => 'admin_api_userList',
            'vouchers' => 'admin_api_voucherList',
            'whs-exp-zero' => 'admin_api_whsExpZeroList',
            'withdraw-flows' => 'admin_api_withdrawFlowList',
            'withdraw-imports' => 'admin_api_withdrawImportList',
            'withdrawals' => 'admin_api_withdrawList',
            'dashboard' => 'admin_api_dashboardData',
        ];

        if ($legacyUri === 'index/admin/userinfo') {
            return 'admin_api_profileInfo';
        }
        if ($legacyUri === 'index/admin/userpwd') {
            return 'admin_api_profileInfo';
        }

        if (in_array($legacyUri, [
            'index/admin/auth/user_certified_detail/{uid}',
            'index/admin/auth/user_examine/detail/{mode}/{uid}',
        ], true)) {
            return 'admin_api_authDetail';
        }

        $module = $controller->pageModuleFor($legacyUri);

        return $pagePermissionMap[$module] ?? $pagePermissionMap['dashboard'];
    }

    /**
     * 根据旧请求体选择真正的变更权限。
     * 路由注册阶段只能看到 URI，审核动作还要结合 reviewstatus/accept_rejection
     * 决定 approve 还是 reject；动态重算可避免只拥有“通过”权限的管理员执行拒绝。
     *
     * @param Request $request 当前旧后台请求，读取路由 URI 与请求体（含 data 嵌套数组）。
     * @return string|null 现代权限点名称；无法识别时回退到 permissionRouteForLegacyUri() 的结果。
     */
    public static function permissionRouteForLegacyRequest(Request $request): ?string
    {
        $legacyUri = $request->route() ? $request->route()->uri() : trim($request->path(), '/');
        $payload = $request->all();
        $nested = $request->input('data');
        if (is_array($nested)) {
            $payload = array_replace($nested, $payload);
        }

        if ($legacyUri === 'index/admin/auth/voucherReviewSave') {
            return (string) ($payload['reviewstatus'] ?? '') === '2'
                ? 'admin_api_voucherReject'
                : 'admin_api_voucherApprove';
        }

        if ($legacyUri === 'index/admin/cancel/update_cancel') {
            return (string) ($payload['accept_rejection'] ?? '') === '1'
                ? 'admin_api_cancelApplyApprove'
                : 'admin_api_cancelApplyReject';
        }

        if ($legacyUri === 'index/admin/amount/batchWithdrawApply') {
            $batchPayload = $payload['payload'] ?? [];
            $status = is_array($batchPayload) ? (string) ($batchPayload['status'] ?? '') : '';

            if ($status === '2') {
                return 'admin_api_withdrawComplete';
            }
            if ($status === '3') {
                return 'admin_api_withdrawReject';
            }

            return 'admin_api_withdrawProcess';
        }

        if ($legacyUri === 'index/admin/amount/order_status') {
            $status = (string) ($payload['orderStatus'] ?? $payload['order_status'] ?? $payload['status'] ?? '');

            $routesByStatus = [
                '1' => 'admin_api_withdrawProcess',
                '2' => 'admin_api_withdrawComplete',
                '3' => 'admin_api_withdrawReject',
            ];

            return $routesByStatus[$status] ?? 'admin_api_withdrawComplete';
        }

        if ($legacyUri === 'index/admin/amount/order_status_OTC') {
            return 'admin_api_withdrawComplete';
        }

        return static::permissionRouteForLegacyUri($legacyUri);
    }

    /**
     * 旧后台请求统一分发入口。
     *
     * 功能逻辑说明：
     * - 所有旧 URI 都注册到本方法，先按 URI 精确匹配验证码、退出、登录等特殊入口，
     *   再按旧 URI 分发到资金流水、管理员账号、凭证审核、注销审核、批量出金、支付通道、
     *   权益确认、代理编辑保存等兼容分支，最后回退到旧页面渲染或现代命名路由转发。
     *
     * @param Request $request 当前旧后台请求，路由 URI 与旧参数均来自该对象。
     * @return Response 旧页面视图响应或新旧字段并存的 JSON 响应。
     */
    public function handle(Request $request): Response
    {
        $legacyUri = $request->route() ? $request->route()->uri() : trim($request->path(), '/');

        if ($legacyUri === 'index/admin/captcha') {
            return $this->captcha($request);
        }

        if ($legacyUri === 'index/admin/logout') {
            return $this->logout($request);
        }

        if ($legacyUri === 'index/admin/login') {
            return $this->renderLegacyPage($legacyUri, $request);
        }

        if (in_array($legacyUri, [
            'index/admin/amount/depositDownloadfile/{file}/{role}',
            'index/admin/amount/rights_downloadfile/{file}/{role}',
            'index/admin/amount/withdraw_downloadfile/{file}/{role}',
        ], true)) {
            return $this->forwardLegacyDownloadFile($request);
        }

        if ($legacyUri === 'index/admin/amount/rightsSummarySearch') {
            return $this->forwardLegacyRightsSummarySearch($request);
        }

        if ($legacyUri === 'index/admin/amount/rightsSumExport') {
            return $this->forwardLegacyRightsSumExport($request);
        }

        if ($legacyUri === 'index/admin/amount/rightsSummarySearchDetail/{uid}/{status}/{sumdata}') {
            return $this->forwardLegacyRightsSummaryDetail($request);
        }

        if (in_array($legacyUri, [
            'index/admin/agents/agentsListSearch',
            'index/admin/agents/agentsExamineListSearch',
            'index/admin/agent/v2/agentsListSearchV2',
            'index/admin/bigAgents/agentsListSearch',
            'index/admin/bigAgents/subAgentsListSearch',
        ], true)) {
            return $this->legacyAgentStatistics($request, $legacyUri);
        }

        $withdrawStatusSearches = [
            'index/admin/withdraw/pendingSearch' => 0,
            'index/admin/withdraw/processingSearch' => 1,
            'index/admin/withdraw/completedSearch' => 2,
            'index/admin/withdraw/failedSearch' => 3,
        ];
        if (array_key_exists($legacyUri, $withdrawStatusSearches)) {
            return $this->forwardLegacyWithdrawApplySearch(
                $request,
                $legacyUri,
                $withdrawStatusSearches[$legacyUri]
            );
        }

        $withdrawStatusExports = [
            'index/admin/withdraw/pendingExport' => 0,
            'index/admin/withdraw/processingExport' => 1,
            'index/admin/withdraw/completedExport' => 2,
            'index/admin/withdraw/failedExport' => 3,
        ];
        if (array_key_exists($legacyUri, $withdrawStatusExports)) {
            return $this->forwardLegacyWithdrawStatusExport(
                $request,
                $withdrawStatusExports[$legacyUri]
            );
        }

        if ($legacyUri === 'index/admin/group/pairSelect') {
            return app(GroupConfigController::class)->pairSelect($request);
        }

        $target = $this->targetRouteFor($legacyUri);
        if ($target) {
            // 管理员启停等旧 GET 写动作也必须先过方法边界，避免专用兼容分支绕过 405 guard。
            if (in_array(strtoupper($request->getMethod()), ['GET', 'HEAD'], true)
                && $this->isMutationTargetRoute((string) ($target['route'] ?? ''))) {
                return response()->json([
                    'code' => ResponseCode::OPERATION_NOT_ALLOWED,
                    'message' => __('response.operation_not_allowed'),
                    'data' => [
                        'legacy_uri' => $legacyUri,
                        'allowed_method' => 'POST',
                    ],
                ], 405, ['Allow' => 'POST']);
            }

            if ($legacyUri === 'index/admin/news/newsListSearch') {
                return $this->forwardLegacyNewsList($request);
            }

            if ($legacyUri === 'index/admin/gift/addressList') {
                return $this->forwardLegacyGiftAddressList($request);
            }

            if ($legacyUri === 'index/admin/gift/shipment_list') {
                return $this->forwardLegacyGiftShipmentList($request);
            }

            if ($legacyUri === 'index/admin/gift/send_gift') {
                return $this->forwardLegacyGiftSend($request);
            }

            if ($legacyUri === 'index/admin/gift/shipment_list_export') {
                return $this->forwardLegacyGiftExport($request);
            }

            if (in_array($legacyUri, [
                'index/admin/news/news_save',
                'index/admin/news/news_update',
                'index/admin/news/del',
            ], true)) {
                return $this->forwardLegacyNewsMutation($request, $legacyUri, $target['route']);
            }

            if ($this->isLegacyAdministratorAction($legacyUri)) {
                return $this->forwardLegacyAdministratorAction($request, $legacyUri, $target);
            }

            if ($legacyUri === 'index/admin/agent/update') {
                return $this->forwardLegacyAgentUpdateDebug($request);
            }

            // 旧项目把部分删除、启停和审核动作注册成 GET；不能把探测请求改写成现代 POST，
            // 否则浏览器预取、HEAD 健康检查或重复刷新都可能进入真实写链。
            if (in_array(strtoupper($request->getMethod()), ['GET', 'HEAD'], true)
                && $this->isMutationTargetRoute((string) ($target['route'] ?? ''))) {
                return response()->json([
                    'code' => ResponseCode::OPERATION_NOT_ALLOWED,
                    'message' => __('response.operation_not_allowed'),
                    'data' => [
                        'legacy_uri' => $legacyUri,
                        'allowed_method' => 'POST',
                    ],
                ], 405, ['Allow' => 'POST']);
            }

            if ($legacyUri === 'index/admin/auth/voucherReviewSave') {
                return $this->forwardLegacyVoucherReview($request);
            }

            if (in_array($legacyUri, [
                'index/admin/auth/userCertifiedSearch',
                'index/admin/auth/userCertifiedSearchV2',
                'index/admin/auth/userExaminSearch',
                'index/admin/auth/userExaminSearchV2',
            ], true)) {
                return $this->forwardLegacyAuthenticationSearch($request, $legacyUri);
            }

            if (in_array($legacyUri, [
                'index/admin/auth/voucherInfoSearch',
                'index/admin/auth/voucherInfoSearchV2',
            ], true)) {
                return $this->forwardLegacyVoucherSearch($request, $legacyUri);
            }

            if (in_array($legacyUri, [
                'index/admin/cancel/userlistSearch',
                'index/admin/cancel/userlistSearchV2',
            ], true)) {
                return $this->forwardLegacyCancelApplyList($request, $legacyUri);
            }

            if (in_array($legacyUri, [
                'index/admin/cancel/cancel_apply_pass',
                'index/admin/cancel/cancel_apply_nopass',
                'index/admin/cancel/update_cancel',
            ], true)) {
                return $this->forwardLegacyCancelApply($request, $legacyUri);
            }

            if (in_array($legacyUri, [
                'index/admin/amount/againDepositAmount',
                'index/admin/amount/againWithdrawAmount',
                'index/admin/credit/againCreditAmount',
            ], true)) {
                return $this->forwardLegacyBatchRetry($request, $legacyUri);
            }

            if ($legacyUri === 'index/admin/amount/batchWithdrawApply') {
                return $this->forwardLegacyBatchWithdrawApply($request);
            }

            if ($legacyUri === 'index/admin/amount/batchOperation') {
                return $this->forwardLegacyBatchOperation($request, 'deposit');
            }

            if ($legacyUri === 'index/admin/amount/batchOperationWithdraw') {
                return $this->forwardLegacyBatchOperation($request, 'withdraw');
            }

            if (in_array($legacyUri, [
                'index/admin/amount/depositImportSearch',
                'index/admin/amount/withdrawImportSearch',
            ], true)) {
                return $this->forwardLegacyBatchImportSearch($request, $legacyUri);
            }

            if (in_array($legacyUri, [
                'index/admin/amount/channel_enable',
                'index/admin/amount/channel_enableV2',
            ], true)) {
                return $this->forwardLegacyPaymentChannelBatch($request, $legacyUri);
            }

            if ($legacyUri === 'index/admin/amount/manual_confirm_options') {
                return $this->forwardLegacyManualRightsConfirm($request);
            }

            if ($legacyUri === 'index/admin/amount/confirm_options') {
                return $this->forwardLegacyAutomaticRightsConfirmUnavailable($request);
            }

            if ($legacyUri === 'index/admin/amount/updateCurrOrderId') {
                return $this->forwardLegacyUpdateCurrOrderId($request);
            }

            if ($legacyUri === 'index/admin/amount/OTCwithdrawOrderIdDetail') {
                return $this->forwardLegacyOtcWithdrawOrderIdDetail($request);
            }

            if ($legacyUri === 'index/admin/amount/generate_OTCorder') {
                return $this->forwardLegacyGenerateOtcOrder($request);
            }

            if ($legacyUri === 'index/admin/amount/withdrawExport') {
                return app(LegacyAdminExportController::class)->prepareLegacyWithdrawals($request);
            }

            if (in_array($legacyUri, [
                'index/admin/amount/order_status',
                'index/admin/amount/order_status_OTC',
            ], true)) {
                return $this->forwardLegacyOrderStatus($request, $legacyUri);
            }

            if (in_array($legacyUri, [
                'index/admin/amount/withdrawApplySearch',
                'index/admin/amount/withdrawApplySearchV2',
            ], true)) {
                return $this->forwardLegacyWithdrawApplySearch($request, $legacyUri);
            }

            if (in_array($legacyUri, [
                'index/admin/amount/depositFlowSearch',
                'index/admin/amount/depositFlowSearchV2',
                'index/admin/amount/withdrawFlowSearch',
                'index/admin/amount/withdrawFlowSearchV2',
                'index/admin/amount/undepositFlowSearch',
                'index/admin/amount/undepositFlowSearchV2',
            ], true)) {
                return $this->forwardLegacyFundFlowSearch($request, $legacyUri);
            }

            if (in_array($legacyUri, [
                'index/admin/agents/agents_edit_save',
            ], true)) {
                return $this->forwardLegacyAgentEditSave($request);
            }

            if (in_array($legacyUri, [
                'index/admin/cust/cust_apply_pass',
                'index/admin/cust/cust_apply_nopass',
            ], true)) {
                return $this->forwardLegacyCustApply($request, $legacyUri);
            }

            if (in_array($legacyUri, [
                'index/admin/cust/custChangeListSearch',
                'index/admin/cust/custChangeListSearchV2',
            ], true)) {
                return $this->forwardLegacyCustChangeSearch($request, $legacyUri);
            }

            if (in_array($legacyUri, [
                'index/admin/cust/custListSearch',
                'index/admin/cust/custListSearchV2',
            ], true)) {
                return $this->forwardLegacyCustListSearch($request, $legacyUri);
            }

            if ($legacyUri === 'index/admin/auth/user_idcard_bank') {
                return $this->forwardLegacyUserIdCardBank($request);
            }

            if ($legacyUri === 'index/admin/fengXian/IpaddressDeatail/{idaddr}') {
                return $this->forwardLegacyIpAddressDetail($request);
            }

            if ($legacyUri === 'index/admin/fengXian/IpaddressSearch') {
                return $this->forwardLegacyIpAddressSearch($request);
            }

            if (in_array($legacyUri, [
                'index/admin/fengXian/positionSearch',
                'index/admin/fengXian/positionSearchv2',
            ], true)) {
                return $this->forwardLegacyRiskPositionSearch($request, $legacyUri);
            }

            if (in_array($legacyUri, [
                'index/admin/fengXian/profitSearch',
                'index/admin/fengXian/profitSearchV2',
            ], true)) {
                return $this->forwardLegacyRiskProfitSearch($request, $legacyUri);
            }

            if ($legacyUri === 'index/admin/order/oneKeySearch') {
                return $this->forwardLegacyWhsExpZeroScan($request);
            }

            if ($legacyUri === 'index/admin/order/oneKeyZero') {
                return $this->forwardLegacyWhsExpZeroMutation($request);
            }

            if (in_array($legacyUri, [
                'index/admin/order/whsExpZeroListSearch',
                'index/admin/order/whsExpZeroListSearchV2',
            ], true)) {
                return $this->forwardLegacyWhsExpZeroList($request, $legacyUri);
            }

            $response = $this->forwardToNamedRoute($request, $target['route'], $target['defaults'] ?? []);

            if ($request->isMethod('POST') && $this->isLegacyIdentityRoleAction($legacyUri)) {
                return $this->legacyIdentityRoleResponse($response, $legacyUri);
            }

            if ($request->isMethod('POST') && $this->isLegacyUserGroupAction($legacyUri)) {
                return $this->legacyUserGroupResponse($response, $legacyUri);
            }

            return $response;
        }

        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            if ($legacyUri === 'index/admin/amount/orderId_detail/{orderId}') {
                return $this->renderLegacyWithdrawDetail($request);
            }

            $this->assertLegacyAgentPageAccess($request, $legacyUri);
            $this->assertLegacyCustomerPageAccess($request, $legacyUri);
            $this->assertLegacyUserGroupPageAccess($request, $legacyUri);
            $this->assertLegacyAuthenticationPageAccess($request, $legacyUri);
            $this->assertLegacyVoucherPageAccess($request, $legacyUri);

            return $this->renderLegacyPage($legacyUri, $request);
        }

        return response()->json([
            'code' => 410,
            'message' => 'Legacy admin route has no current target.',
            'data' => [
                'legacy_uri' => $legacyUri,
            ],
        ], 410);
    }

    /**
     * 把现代可发放地址分页转换为旧 Layui envelope 与 rec_id 字段。
     */
    private function forwardLegacyGiftAddressList(Request $request): Response
    {
        $response = $this->forwardToNamedRoute(
            $request,
            'admin_api_giftAddressList',
            ['is_default' => 1]
        );
        $body = json_decode((string) $response->getContent(), true);

        if (!is_array($body) || (int) ($body['code'] ?? 0) !== ResponseCode::SUCCESS) {
            return $this->legacyGiftListFailure(is_array($body) ? ($body['message'] ?? '') : '');
        }

        $paginator = is_array($body['data'] ?? null) ? $body['data'] : [];
        $rows = array_map(static function (array $row): array {
            return [
                'rec_id' => isset($row['id']) ? (int) $row['id'] : null,
                'user_id' => isset($row['user_id']) ? (int) $row['user_id'] : null,
                'recipient_name' => $row['recipient_name'] ?? '',
                'recipient_phone' => $row['recipient_phone'] ?? '',
                'recipient_address' => $row['recipient_address'] ?? '',
                'is_default' => (int) ($row['is_default'] ?? 0),
                'updated_at' => $row['updated_at'] ?? null,
            ];
        }, is_array($paginator['data'] ?? null) ? $paginator['data'] : []);

        return response()->json([
            'code' => 0,
            'msg' => (string) ($body['message'] ?? ''),
            'count' => (int) ($paginator['total'] ?? count($rows)),
            'data' => $rows,
            'totalRow' => [],
        ]);
    }

    /**
     * 把现代发货分页转换为旧默认日期、可见字段和 rec_id 协议。
     */
    private function forwardLegacyGiftShipmentList(Request $request): Response
    {
        $response = $this->forwardToNamedRoute(
            $request,
            'admin_api_giftShipmentList',
            $this->legacyGiftShipmentPayload($request)
        );
        $body = json_decode((string) $response->getContent(), true);

        if (!is_array($body) || (int) ($body['code'] ?? 0) !== ResponseCode::SUCCESS) {
            return $this->legacyGiftListFailure(is_array($body) ? ($body['message'] ?? '') : '');
        }

        $paginator = is_array($body['data'] ?? null) ? $body['data'] : [];
        $rows = array_map(static function (array $row): array {
            return [
                'rec_id' => isset($row['id']) ? (int) $row['id'] : null,
                'user_id' => isset($row['user_id']) ? (int) $row['user_id'] : null,
                'recipient_name' => $row['recipient_name'] ?? '',
                'recipient_phone' => $row['recipient_phone'] ?? '',
                'recipient_address' => $row['recipient_address'] ?? '',
                'sender_name' => $row['sender_name'] ?? '',
                'tracking_number' => $row['tracking_number'] ?? '',
                'gift_name' => $row['gift_name'] ?? '',
                'gift_quantity' => (int) ($row['gift_quantity'] ?? 0),
                'remark' => $row['remark'] ?? '',
                'admin_name' => $row['admin_name'] ?? '',
                'shipped_at' => $row['shipped_at'] ?? null,
                'created_at' => $row['created_at'] ?? null,
            ];
        }, is_array($paginator['data'] ?? null) ? $paginator['data'] : []);

        return response()->json([
            'code' => 0,
            'msg' => (string) ($body['message'] ?? ''),
            'count' => (int) ($paginator['total'] ?? count($rows)),
            'data' => $rows,
            'totalRow' => [],
        ]);
    }

    /**
     * 展开 giftInfo 和 recipients[*].rec_id，并把现代结果收敛为旧 0/5000 协议。
     */
    private function forwardLegacyGiftSend(Request $request): Response
    {
        $payload = $this->payloadForLegacyTarget($request);
        $giftInfo = $payload['giftInfo'] ?? null;
        if (is_array($giftInfo)) {
            $payload = array_replace($payload, $giftInfo);
        }
        unset($payload['giftInfo']);

        if (is_array($payload['recipients'] ?? null)) {
            $payload['recipients'] = array_map(static function ($recipient) {
                if (!is_array($recipient)) {
                    return $recipient;
                }
                if (!array_key_exists('address_id', $recipient) && array_key_exists('rec_id', $recipient)) {
                    $recipient['address_id'] = $recipient['rec_id'];
                }

                return $recipient;
            }, $payload['recipients']);
        }

        try {
            $response = $this->forwardToNamedRoute($request, 'admin_api_sendGift', $payload);
            $body = json_decode((string) $response->getContent(), true);
        } catch (\Throwable $exception) {
            return $this->legacyGiftMutationFailure('寄送失败');
        }

        if (is_array($body) && in_array((int) ($body['code'] ?? 0), [
            ResponseCode::SUCCESS,
            ResponseCode::CREATED,
        ], true)) {
            return response()->json([
                'code' => 0,
                'data' => [],
                'message' => '寄送成功',
            ]);
        }

        return $this->legacyGiftMutationFailure('寄送失败');
    }

    /**
     * 生成管理员隔离的旧 CSV 文件，并返回两阶段下载路径。
     */
    private function forwardLegacyGiftExport(Request $request): Response
    {
        $request->merge($this->legacyGiftShipmentPayload($request));
        $giftController = app(GiftController::class);
        $records = $giftController->shipmentExportRecords($request);

        if ($records instanceof Response || $records->isEmpty()) {
            return $this->legacyGiftMutationFailure('操作失败');
        }

        $admin = $request->user('admin');
        if (!$admin || (int) $admin->id <= 0) {
            return $this->legacyGiftMutationFailure('操作失败');
        }

        $directory = storage_path('app/legacy-admin-exports/admin/' . (int) $admin->id);
        $absolutePath = null;

        try {
            if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new \RuntimeException('Unable to create legacy Gift export directory.');
            }

            $this->pruneExpiredLegacyGiftExports($directory);
            $token = bin2hex(random_bytes(16));
            $absolutePath = $directory . DIRECTORY_SEPARATOR . $token . '.csv';
            $giftController->writeCsvFile(
                $absolutePath,
                $giftController->legacyShipmentCsvRows($records)
            );
        } catch (\Throwable $exception) {
            if ($absolutePath && is_file($absolutePath)) {
                @unlink($absolutePath);
            }

            return $this->legacyGiftMutationFailure('操作失败');
        }

        return response()->json([
            'code' => 0,
            'data' => [
                'path' => 'index/admin/gift/shipment_list_download/' . $token,
            ],
            'message' => '操作成功',
        ]);
    }

    /**
     * 删除当前管理员目录内超过 TTL 的标准旧 Gift 导出文件。
     */
    private function pruneExpiredLegacyGiftExports(string $directory): void
    {
        $entries = @scandir($directory);
        if ($entries === false) {
            throw new \RuntimeException('Unable to scan legacy Gift export directory.');
        }

        $expiresBefore = now()->timestamp - self::LEGACY_GIFT_EXPORT_TTL_SECONDS;
        foreach ($entries as $entry) {
            if (!preg_match('/^[a-f0-9]{32}\.csv$/D', $entry)) {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (is_link($path) || !is_file($path)) {
                continue;
            }

            $modifiedAt = @filemtime($path);
            if ($modifiedAt === false || $modifiedAt >= $expiresBefore) {
                continue;
            }

            if (!@unlink($path)) {
                throw new \RuntimeException('Unable to prune legacy Gift export file.');
            }
        }
    }

    /**
     * 归一化旧发货筛选并补齐旧默认日期。
     *
     * @return array<string, mixed>
     */
    private function legacyGiftShipmentPayload(Request $request): array
    {
        $payload = $this->payloadForLegacyTarget($request);
        if (!array_key_exists('start_date', $payload) || trim((string) $payload['start_date']) === '') {
            $payload['start_date'] = '2024-01-01';
        }
        if (!array_key_exists('end_date', $payload) || trim((string) $payload['end_date']) === '') {
            $payload['end_date'] = date('Y-m-d');
        }

        return $payload;
    }

    private function legacyGiftListFailure(string $message): Response
    {
        return response()->json([
            'code' => 5000,
            'msg' => $message !== '' ? $message : '查询失败',
            'count' => 0,
            'data' => [],
            'totalRow' => [],
        ]);
    }

    private function legacyGiftMutationFailure(string $message): Response
    {
        return response()->json([
            'code' => 5000,
            'data' => [],
            'message' => $message,
        ]);
    }

    /**
     * Forward the old news list to NewsController@index and adapt only its fields and envelope.
     */
    private function forwardLegacyNewsList(Request $request): Response
    {
        $payload = $this->legacyNewsPayload($request);
        if (array_key_exists('rows', $payload)) {
            $perPage = $payload['rows'];
        } elseif (array_key_exists('limit', $payload)) {
            $perPage = $payload['limit'];
        } elseif (array_key_exists('per_page', $payload)) {
            $perPage = $payload['per_page'];
        } else {
            $perPage = 20;
        }

        $startDate = $payload['startdate'] ?? null;
        if ($startDate === null || (is_string($startDate) && trim($startDate) === '')) {
            $startDate = '2024-01-01';
        }
        $endDate = $payload['enddate'] ?? null;
        if ($endDate === null || (is_string($endDate) && trim($endDate) === '')) {
            $endDate = date('Y-m-d');
        }

        $defaults = [
            'per_page' => $perPage,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
        if (array_key_exists('ispush', $payload)) {
            // Preserve the write/display meaning: 0=not published, 1=published. Do not copy the old formatter defect.
            $defaults['is_published'] = $payload['ispush'];
        }

        $response = $this->forwardToNamedRoute($request, 'admin_api_newsList', $defaults);
        if (!method_exists($response, 'getData')) {
            return $response;
        }

        $body = $response->getData(true);
        if (!is_array($body)
            || (int) ($body['code'] ?? ResponseCode::SERVER_ERROR) !== ResponseCode::SUCCESS) {
            return $response;
        }

        $page = $body['data'] ?? [];
        $rows = is_array($page) && isset($page['data']) && is_array($page['data'])
            ? $page['data']
            : [];
        $total = is_array($page) ? (int) ($page['total'] ?? 0) : 0;
        if ($rows === []) {
            return response()->json(['rows' => '', 'total' => '']);
        }

        foreach ($rows as &$row) {
            if (!is_array($row)) {
                continue;
            }
            $row['news_id'] = $row['id'] ?? null;
            $row['news_title'] = $row['title'] ?? '';
            $row['news_content'] = $row['content'] ?? '';
            $row['is_published'] = (int) ($row['is_published'] ?? 0);
            $row['is_push'] = $row['is_published'];
            $row['news_user'] = $row['author_name'] ?? '';
            $row['rec_upd_date'] = $row['updated_at'] ?? null;
            $row['rec_crt_date'] = $row['created_at'] ?? null;
        }
        unset($row);

        return response()->json(['rows' => $rows, 'total' => $total]);
    }

    /**
     * Map the real old news form fields and restore its mutation response contract.
     */
    private function forwardLegacyNewsMutation(
        Request $request,
        string $legacyUri,
        string $targetRoute
    ): Response {
        $payload = $this->legacyNewsPayload($request);
        if ($legacyUri !== 'index/admin/news/news_save') {
            $idFields = $legacyUri === 'index/admin/news/news_update'
                ? ['newsId', 'newsid']
                : ['newsid', 'newsId'];
            $rawId = null;
            foreach ($idFields as $idField) {
                if (array_key_exists($idField, $payload)) {
                    $rawId = $payload[$idField];
                    break;
                }
            }
            if ((!is_int($rawId) && !is_string($rawId)) || $this->legacyPositiveInt($rawId) === null) {
                return $this->legacyNewsFailure(ResponseCode::VALIDATION_FAILED);
            }
        }

        if ($legacyUri === 'index/admin/news/news_update' && !array_key_exists('ispush', $payload)) {
            return $this->legacyNewsFailure(ResponseCode::VALIDATION_FAILED);
        }

        $defaults = [];
        foreach ([
            'newsTitle' => 'title',
            'newsContent' => 'content',
            'ispush' => 'is_published',
        ] as $legacyField => $modernField) {
            if (array_key_exists($legacyField, $payload)) {
                $defaults[$modernField] = $payload[$legacyField];
            }
        }

        return $this->legacyNewsMutationResponse(
            $this->forwardToNamedRoute($request, $targetRoute, $defaults)
        );
    }

    /**
     * Flatten the old data object without adding news aliases to the global adapter.
     *
     * @return array<string, mixed>
     */
    private function legacyNewsPayload(Request $request): array
    {
        $payload = $request->all();
        $nested = $request->input('data');
        if (is_array($nested)) {
            unset($payload['data']);
            $payload = array_replace($nested, $payload);
        }

        return $payload;
    }

    private function legacyNewsMutationResponse(Response $response): Response
    {
        if (!method_exists($response, 'getData')) {
            return $response;
        }

        $body = $response->getData(true);
        if (!is_array($body)) {
            return $response;
        }

        $modernCode = (int) ($body['code'] ?? ResponseCode::SERVER_ERROR);
        $success = in_array($modernCode, [
            ResponseCode::CREATED,
            ResponseCode::UPDATED,
            ResponseCode::DELETED,
        ], true);

        return response()->json([
            'msg' => $success ? 'SUC' : 'FAIL',
            'code' => $success ? 0 : $modernCode,
            'modern_code' => $modernCode,
            'message' => $body['message'] ?? '',
            'data' => $body['data'] ?? (object) [],
        ], $response->getStatusCode());
    }

    private function legacyNewsFailure(int $code): Response
    {
        return response()->json([
            'msg' => 'FAIL',
            'code' => $code,
            'modern_code' => $code,
            'message' => __(ResponseCode::messageKey($code)),
            'data' => (object) [],
        ]);
    }

    /** 扫描真实候选并恢复旧 oneKeySearch 的 msg/err/col 响应。 */
    private function forwardLegacyWhsExpZeroScan(Request $request): Response
    {
        $original = $request->all();
        $request->replace($this->payloadForLegacyTarget($request));

        try {
            $response = app(AdminWhsExpZeroController::class)->scanCandidates($request);
        } finally {
            $request->replace($original);
        }

        $body = json_decode((string) $response->getContent(), true);
        $body = is_array($body) ? $body : [];
        $createdCount = (int) ($body['data']['created_count'] ?? 0);
        $success = (int) ($body['code'] ?? ResponseCode::SERVER_ERROR) === ResponseCode::SUCCESS
            && $createdCount > 0;
        $body['msg'] = $success ? 'SUC' : 'FAIL';
        $body['err'] = $success ? 'noerr' : 'zerofail';
        $body['col'] = $createdCount;

        return response()->json($body, $response->getStatusCode(), $response->headers->all());
    }

    /** 查询真实清零记录并分别恢复旧 V1 与 V2 列表 envelope。 */
    private function forwardLegacyWhsExpZeroList(Request $request, string $legacyUri): Response
    {
        $original = $request->all();
        $payload = $this->payloadForLegacyTarget($request);
        $aliases = [
            'wez_userid' => 'user_id',
            'wez_username' => 'user_name',
            'wez_status' => 'status',
            'startdate' => 'start_date',
            'enddate' => 'end_date',
        ];
        foreach ($aliases as $legacy => $modern) {
            if (!array_key_exists($modern, $payload) && array_key_exists($legacy, $payload)) {
                $payload[$modern] = $payload[$legacy];
            }
        }
        if (!isset($payload['per_page']) && isset($payload['rows'])) {
            $payload['per_page'] = $payload['rows'];
        } elseif (!isset($payload['per_page']) && isset($payload['limit'])) {
            $payload['per_page'] = $payload['limit'];
        }
        if (!array_key_exists('start_date', $payload) || trim((string) $payload['start_date']) === '') {
            $payload['start_date'] = '2024-01-01';
        }
        if (!array_key_exists('end_date', $payload) || trim((string) $payload['end_date']) === '') {
            $payload['end_date'] = date('Y-m-d');
        }
        $request->replace($payload);

        try {
            $response = $this->forwardToNamedRoute($request, 'admin_api_whsExpZeroRecords', []);
        } finally {
            $request->replace($original);
        }

        $body = json_decode((string) $response->getContent(), true);
        $body = is_array($body) ? $body : [];
        if ((int) ($body['code'] ?? ResponseCode::SERVER_ERROR) !== ResponseCode::SUCCESS) {
            return response()->json($body, $response->getStatusCode(), $response->headers->all());
        }

        $page = is_array($body['data'] ?? null) ? $body['data'] : [];
        $modernRows = is_array($page['data'] ?? null) ? $page['data'] : [];
        $rows = array_map(static function (array $row): array {
            return [
                'wezuserid' => (int) ($row['user_id'] ?? 0),
                'wezusername' => (string) ($row['user_name'] ?? ''),
                'wezuserbal' => (string) ($row['balance_before'] ?? '0.00'),
                'wezusercrt' => (string) ($row['credit_amount'] ?? '0.00'),
                'wezstatus' => (int) ($row['status'] ?? 0),
                'rec_crt_date' => (string) ($row['created_at'] ?? ''),
            ];
        }, $modernRows);

        if (substr($legacyUri, -2) === 'V2') {
            return response()->json([
                'code' => 200,
                'msg' => 'Request data successful.',
                'count' => (int) ($page['count'] ?? count($rows)),
                'data' => $rows,
                'totalRow' => [],
            ]);
        }

        return response()->json([
            'rows' => $rows === [] ? '' : $rows,
            'total' => '',
        ]);
    }

    /** 执行现代清零状态机并补回旧 oneKeyZero 判断字段。 */
    private function forwardLegacyWhsExpZeroMutation(Request $request): Response
    {
        $original = $request->all();
        $request->replace($this->payloadForLegacyTarget($request));

        try {
            $response = $this->forwardToNamedRoute($request, 'admin_api_whsExpZero', []);
        } finally {
            $request->replace($original);
        }

        $body = json_decode((string) $response->getContent(), true);
        $body = is_array($body) ? $body : [];
        $code = (int) ($body['code'] ?? ResponseCode::SERVER_ERROR);
        $success = $code === ResponseCode::SUCCESS;
        $body['msg'] = $success ? 'SUC' : 'FAIL';
        $body['err'] = $success ? 'noerr' : ($code === ResponseCode::MT4_SYNC_FAILED ? 'zerofail' : 'crtfail');
        $body['col'] = $success ? 'enable' : 'nocol';

        return response()->json($body, $response->getStatusCode(), $response->headers->all());
    }

    /**
     * 恢复旧 FengXian 持仓风险 V1/V2 envelope，查询逻辑统一复用只读服务。
     */
    private function forwardLegacyRiskPositionSearch(Request $request, string $legacyUri): Response
    {
        $originalPayload = $request->all();
        $request->replace($this->payloadForLegacyTarget($request));

        try {
            $admin = $request->user('admin') ?: Auth::guard('admin')->user();
            $service = app(LegacyRiskQueryService::class);
            if ($filterError = $service->validatePositionFilters($request, true)) {
                return $this->error($filterError['message'], ResponseCode::VALIDATION_FAILED, [
                    'field' => $filterError['field'],
                ]);
            }
            $result = $service->positionPage($request, $admin, true);
        } finally {
            $request->replace($originalPayload);
        }

        if ($legacyUri === 'index/admin/fengXian/positionSearchv2') {
            return response()->json([
                'code' => 200,
                'msg' => 'Request data successful.',
                'count' => $result['total'],
                'data' => $result['rows'],
                'totalRow' => [],
            ]);
        }

        return response()->json([
            'rows' => $result['total'] > 0 ? $result['rows'] : '',
            'total' => $result['total'],
        ]);
    }

    /**
     * 恢复旧 FengXian 盈利风险 V1/V2 envelope，查询逻辑统一复用只读服务。
     */
    private function forwardLegacyRiskProfitSearch(Request $request, string $legacyUri): Response
    {
        $originalPayload = $request->all();
        $request->replace($this->payloadForLegacyTarget($request));

        try {
            $admin = $request->user('admin') ?: Auth::guard('admin')->user();
            $service = app(LegacyRiskQueryService::class);
            if ($filterError = $service->validateProfitFilters($request)) {
                return $this->error($filterError['message'], ResponseCode::VALIDATION_FAILED, [
                    'field' => $filterError['field'],
                ]);
            }
            $result = $service->profitPage($request, $admin);
        } finally {
            $request->replace($originalPayload);
        }

        if ($legacyUri === 'index/admin/fengXian/profitSearchV2') {
            return response()->json([
                'code' => 200,
                'msg' => 'Request data successful.',
                'count' => $result['total'],
                'data' => $result['rows'],
                'totalRow' => [],
            ]);
        }

        return response()->json([
            'rows' => $result['total'] > 0 ? $result['rows'] : '',
            'total' => $result['total'],
        ]);
    }

    /**
     * Fail closed before rendering legacy agent pages that address a business user directly.
     */
    private function assertLegacyAgentPageAccess(Request $request, string $legacyUri): void
    {
        $routeParameters = [
            'index/admin/agent/edit/{user_id?}' => 'user_id',
            'index/admin/agents/agents_edit_info/{uid}' => 'uid',
            'index/admin/customer/{user_id?}' => 'user_id',
            'index/admin/agent/{user_id?}' => 'user_id',
        ];

        if (!isset($routeParameters[$legacyUri])) {
            return;
        }

        $parameter = $routeParameters[$legacyUri];
        $rawUserId = $request->route($parameter);
        if ($legacyUri === 'index/admin/agent/{user_id?}' && $rawUserId === null) {
            return;
        }

        if (!is_string($rawUserId) && !is_int($rawUserId)) {
            abort(404);
        }

        $rawUserId = (string) $rawUserId;
        if (!preg_match('/^[1-9]\d*$/', $rawUserId)) {
            abort(404);
        }

        $userId = (int) $rawUserId;
        $agentExists = UserInfo::query()
            ->where('user_id', $userId)
            ->where('account_type', 1)
            ->exists();
        if (!$agentExists) {
            abort(404);
        }

        $admin = $request->user('admin') ?: Auth::guard('admin')->user();
        if (!$admin || !app(AdminDataScopeService::class)->canAccessUser($admin, $userId, 'agent')) {
            abort(403);
        }
    }

    /**
     * Fail closed before rendering the old ordinary-customer detail page.
     */
    private function assertLegacyCustomerPageAccess(Request $request, string $legacyUri): void
    {
        if ($legacyUri !== 'index/admin/cust/cust_detail/{acc_uid}') {
            return;
        }

        $rawUserId = $request->route('acc_uid');
        abort_unless(is_string($rawUserId) || is_int($rawUserId), 404);
        $rawUserId = (string) $rawUserId;
        abort_unless((bool) preg_match('/^[1-9]\d*$/D', $rawUserId), 404);

        $userId = (int) $rawUserId;
        abort_unless(
            UserInfo::query()->where('user_id', $userId)->where('account_type', 2)->exists(),
            404
        );

        $admin = $request->user('admin') ?: Auth::guard('admin')->user();
        abort_unless(
            $admin && app(AdminDataScopeService::class)->canAccessUser($admin, $userId, 'user'),
            403
        );
    }

    /**
     * 旧实名认证详情页必须先确认认证记录存在且属于当前管理员可见范围。
     * 无管理员仅可能出现在关闭中间件的视图契约测试里，真实旧路由由 legacy.admin.auth 先拦截。
     */
    private function assertLegacyAuthenticationPageAccess(Request $request, string $legacyUri): void
    {
        $parameter = null;
        if ($legacyUri === 'index/admin/auth/user_certified_detail/{uid}') {
            $parameter = 'uid';
        } elseif ($legacyUri === 'index/admin/auth/user_examine/detail/{mode}/{uid}') {
            $mode = (string) $request->route('mode');
            abort_unless(in_array($mode, ['auth', 'show'], true), 404);
            $parameter = 'uid';
        }

        if ($parameter === null) {
            return;
        }

        $rawUserId = $request->route($parameter);
        abort_unless(is_string($rawUserId) || is_int($rawUserId), 404);
        $rawUserId = (string) $rawUserId;
        abort_unless((bool) preg_match('/^[1-9]\d*$/D', $rawUserId), 404);

        $admin = $request->user('admin') ?: Auth::guard('admin')->user();
        if (!$admin) {
            return;
        }

        $userId = (int) $rawUserId;
        abort_unless(UserAuth::query()->where('user_id', $userId)->exists(), 404);
        abort_unless(
            app(AdminDataScopeService::class)->canAccessUser($admin, $userId, 'user'),
            403
        );
    }

    /**
     * 旧凭证详情同时携带凭证主键和业务用户 ID，二者必须精确匹配并通过数据范围校验。
     */
    private function assertLegacyVoucherPageAccess(Request $request, string $legacyUri): void
    {
        if ($legacyUri !== 'index/admin/auth/user_voucher/detail/{recId}/{uid}') {
            return;
        }

        $rawRecordId = $request->route('recId');
        $rawUserId = $request->route('uid');
        abort_unless((is_string($rawRecordId) || is_int($rawRecordId))
            && (bool) preg_match('/^[1-9]\d*$/D', (string) $rawRecordId), 404);
        abort_unless((is_string($rawUserId) || is_int($rawUserId))
            && (bool) preg_match('/^[1-9]\d*$/D', (string) $rawUserId), 404);

        $admin = $request->user('admin') ?: Auth::guard('admin')->user();
        if (!$admin) {
            return;
        }

        $voucher = VoucherInfo::query()
            ->whereKey((int) $rawRecordId)
            ->where('user_id', (int) $rawUserId)
            ->whereNull('deleted_at')
            ->first();
        abort_unless($voucher, 404);
        abort_unless(
            app(AdminDataScopeService::class)->canAccessUser($admin, (int) $rawUserId, 'user'),
            403
        );
    }

    private function assertLegacyUserGroupPageAccess(Request $request, string $legacyUri): void
    {
        if ($legacyUri !== 'index/admin/group/user_group_edit/{recId}') {
            return;
        }

        $rawId = $request->route('recId');
        abort_unless(is_string($rawId) || is_int($rawId), 404);
        $rawId = (string) $rawId;
        abort_unless((bool) preg_match('/^[1-9]\d*$/D', $rawId), 404);
        abort_unless(GroupConfig::query()->whereKey((int) $rawId)->exists(), 404);
    }

    /**
     * Serve the legacy agent statistic tables without changing unrelated route adapters.
     */
    private function legacyAgentStatistics(Request $request, string $legacyUri): Response
    {
        $admin = $request->user('admin') ?: Auth::guard('admin')->user();
        if (!$admin) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $service = app(LegacyAdminAgentStatisticsService::class);
        if ($legacyUri === 'index/admin/agents/agentsListSearch') {
            return response()->json($service->agentList($request, $admin));
        }
        if ($legacyUri === 'index/admin/agents/agentsExamineListSearch') {
            return response()->json($service->agentExamineList($request, $admin));
        }
        if ($legacyUri === 'index/admin/agent/v2/agentsListSearchV2') {
            return response()->json($service->agentList($request, $admin, true));
        }
        if ($legacyUri === 'index/admin/bigAgents/agentsListSearch') {
            return response()->json($service->bigAgentList($request, $admin));
        }

        return response()->json($service->bigAgentSubList($request, $admin));
    }

    /**
     * 兼容旧后台代理 update 半成品调试入口。
     *
     * 参数逻辑说明：
     * - 项目1 `AgentControllerV3@AgentUpdate` 只读取 `$request->parent_id`，随后 `print_r($data); die;`。
     * - 旧方法没有保存 parent_id、level_id，也没有返回 `statue/msg` 成功 JSON。
     * - 项目2必须显式保持只读回显，避免误转发到现代代理等级或层级写接口造成真实数据变更。
     *
     * @param Request $request 当前旧后台请求，读取 parent_id 嵌套数组或标量。
     * @return Response 旧 `print_r` 纯文本响应；没有写库副作用。
     */
    private function forwardLegacyAgentUpdateDebug(Request $request): Response
    {
        return response((string) print_r($request->input('parent_id'), true), 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /**
     * 归一化旧状态页的平面筛选字段，并由 URI 强制限定导出状态。
     */
    private function forwardLegacyWithdrawStatusExport(Request $request, int $status): Response
    {
        $legacyOrModern = static function (string $legacyKey, string $modernKey) use ($request) {
            $legacyValue = $request->input($legacyKey);

            return $legacyValue !== null && $legacyValue !== ''
                ? $legacyValue
                : $request->input($modernKey);
        };

        $userId = $legacyOrModern('userId', 'user_id');
        $mt4Ticket = $legacyOrModern('withdraw_id', 'mt4_ticket');
        $startDate = $legacyOrModern('withdraw_startdate', 'start_date');
        $endDate = $legacyOrModern('withdraw_enddate', 'end_date');

        $request->merge([
            'user_id' => $userId,
            'mt4_ticket' => $mt4Ticket,
            'status' => $status,
            'start_date' => $startDate === null || $startDate === '' ? '2024-01-01' : $startDate,
            'end_date' => $endDate === null || $endDate === '' ? now()->toDateString() : $endDate,
        ]);

        return app(LegacyAdminExportController::class)->exportWithdrawals($request);
    }

    /**
     * 兼容旧出金申请 V1/V2 搜索，真实读取统一由 WithdrawRecordQueryService 完成。
     */
    private function forwardLegacyWithdrawApplySearch(
        Request $request,
        string $legacyUri,
        ?int $forcedStatus = null
    ): Response
    {
        $today = now()->toDateString();
        $filters = [
            'user_id' => $request->input('userId', $request->input('user_id')),
            'mt4_ticket' => $request->input('withdraw_id', $request->input('mt4_ticket')),
            'status' => $forcedStatus !== null
                ? $forcedStatus
                : $request->input('withdraw_source', $request->input('status')),
            'local_order_no' => $request->input('local_order_no'),
            'start_date' => $request->input('withdraw_startdate', $request->input('start_date', '2024-01-01')),
            'end_date' => $request->input('withdraw_enddate', $request->input('end_date', $today)),
            'page' => $request->input('page', 1),
            'per_page' => $request->input(
                'rows',
                $request->input('limit', $request->input('per_page', 15))
            ),
        ];

        if ($filters['start_date'] === null || $filters['start_date'] === '') {
            $filters['start_date'] = '2024-01-01';
        }
        if ($filters['end_date'] === null || $filters['end_date'] === '') {
            $filters['end_date'] = $today;
        }

        $validator = Validator::make($filters, [
            'user_id' => 'nullable|integer|min:1',
            'mt4_ticket' => 'nullable|string|max:100',
            'status' => 'nullable|integer|in:0,1,2,3',
            'local_order_no' => 'nullable|string|max:200',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'page' => 'required|integer|min:1',
            'per_page' => 'required|integer|between:1,100',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $service = app(WithdrawRecordQueryService::class);
        $admin = $request->user('admin') ?: Auth::guard('admin')->user();
        $query = $service->query($admin, $filters);
        $summaryQuery = clone $query;
        $paginator = $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate((int) $filters['per_page'], ['*'], 'page', (int) $filters['page']);
        $rows = array_map(function (WithdrawRecord $record) use ($service): array {
            return $this->formatLegacyWithdrawRow($record, $service);
        }, $paginator->items());
        $count = (int) $paginator->total();
        $totalRow = $rows === [] ? [] : $this->formatLegacyWithdrawTotalRow($service->summarize($summaryQuery));

        if ($forcedStatus !== null || $legacyUri === 'index/admin/amount/withdrawApplySearchV2') {
            return response()->json([
                'code' => 200,
                'msg' => 'Request data successful.',
                'count' => $count,
                'data' => $rows,
                'totalRow' => $totalRow,
            ]);
        }

        return response()->json([
            'rows' => $rows,
            'total' => $count,
            'footer' => $totalRow === [] ? [] : [$totalRow],
        ]);
    }

    /**
     * 把现代出金记录格式化为旧后台出金表格的单行结构。
     *
     * 字段口径按项目1 WithdrawAmountController 的 SQL 别名逐一对齐：
     * - applyamount 对应旧 draw_record_log.apply_amount，即申请金额（未扣手续费）；
     * - actapplyamount 对应旧 draw_record_log.act_apply_amount，即实际金额；
     *   两者是不同业务含义，不能同源，否则「实际金额」列会显示申请金额，
     *   且与 formatLegacyWithdrawTotalRow() 的合计行口径自相矛盾。
     * - actdraw 为实际金额乘以汇率得到的本币金额，用 BCMath 定点乘法避免浮点误差；
     * - drawpoundage 只取 USD 口径的 fee，不能混用 RMB 口径的 rmb_fee。
     *
     * 金额统一经 formatMoney() 输出定点字符串，禁止返回 float，
     * 避免大额出金在 JSON 序列化阶段丢精度。
     *
     * @param WithdrawRecord $record 现代出金记录；user_name 缺失时回退关联用户。
     * @param WithdrawRecordQueryService $service 出金只读查询服务，提供定点金额格式化与汇率乘法。
     * @return array<string, mixed> 旧 Layui 表格单行结构，键名保持旧契约不变。
     */
    private function formatLegacyWithdrawRow(
        WithdrawRecord $record,
        WithdrawRecordQueryService $service
    ): array
    {
        $createdAt = $record->created_at;
        $updatedAt = $record->updated_at;

        return [
            'record_id' => (int) $record->id,
            'mt4_ticket' => (string) $record->mt4_ticket,
            'userId' => (int) $record->user_id,
            'username' => (string) ($record->user_name ?: optional($record->user)->user_name),
            'bank_no' => (string) $record->bank_no,
            'bank_no_info' => (string) $record->bank_name . (string) $record->bank_addr,
            'applyamount' => $service->formatMoney($record->apply_amount),
            // 实际金额必须取 actual_amount：旧 SQL 为 act_apply_amount as actapplyamount，
            // 与上一行的申请金额是两个独立业务字段。
            'actapplyamount' => $service->formatMoney($record->actual_amount),
            'drawrate' => (string) $record->exchange_rate,
            'actdraw' => $service->multiplyMoneyByRate(
                (string) $record->actual_amount,
                (string) $record->exchange_rate
            ),
            'drawpoundage' => $service->formatMoney($record->fee),
            'applystatus' => (int) $record->status,
            'rec_crt_date' => $createdAt ? $createdAt->format('Y-m-d H:i:s') : '',
            'rec_upd_date' => $updatedAt ? $updatedAt->format('Y-m-d H:i:s') : '',
            'orderIdLOC' => (string) $record->local_order_no,
            'orderIdOTC' => (string) $record->third_order_no,
            'orderIdOTCstatus' => (string) $record->mt4_return_status,
            'apply_remark' => (string) $record->reject_reason,
            'rec_crt_user' => (string) $record->created_by,
            'rec_upd_user' => (string) $record->updated_by,
        ];
    }

    /**
     * 把出金汇总结果格式化为旧后台表格的合计行（footer / totalRow）。
     *
     * 合计行与逐行必须同源同口径：applyamount 对应申请金额合计，
     * actapplyamount 对应实际金额合计，actdraw 对应本币金额合计，drawpoundage 只汇总 USD fee。
     * 非金额列按旧契约输出空字符串占位，保证旧 Layui 表格列数对齐不错位。
     *
     * @param array<string, string> $summary 出金汇总定点字符串，由 WithdrawRecordQueryService::summarize() 产出。
     * @return array<string, mixed> 旧表格合计行结构。
     */
    private function formatLegacyWithdrawTotalRow(array $summary): array
    {
        return [
            'mt4_ticket' => __('systemlanguage.total'),
            'userId' => '',
            'username' => '',
            'bank_no' => '',
            'bank_no_info' => '',
            'applyamount' => $summary['apply_amount'],
            'actapplyamount' => $summary['actual_amount'],
            'drawrate' => '',
            'actdraw' => $summary['actual_draw'],
            'drawpoundage' => $summary['fee'],
            'applystatus' => '',
            'rec_crt_date' => '',
        ];
    }

    /**
     * 兼容旧后台入金、出金和未入金流水搜索入口。
     *
     * 参数逻辑说明：
     * - 项目1 V1 表格读取 `rows/total/footer`，V2 表格读取 `code/msg/count/data/totalRow`。
     * - 项目2真实查询由 FundFlowController 提供，本方法只做旧字段转换和旧响应包装。
     * - 入金流水单独转发到 `admin_api_depositFlowList`，避免把 MT4 入金流水误映射成入金申请列表。
     *
     * @param Request $request 当前旧后台请求。
     * @param string $legacyUri 当前旧 URI，用于区分入金、出金、未入金和 V1/V2。
     * @return Response 旧表格可直接消费的 JSON 响应。
     */
    private function forwardLegacyFundFlowSearch(Request $request, string $legacyUri): Response
    {
        $targetRoute = $this->legacyFundFlowTargetRoute($legacyUri);
        $this->normalizeLegacyFundFlowPayload($request, $legacyUri);

        $response = $this->forwardToNamedRoute($request, $targetRoute, []);
        $body = method_exists($response, 'getData')
            ? $response->getData(true)
            : json_decode((string) $response->getContent(), true);
        if (!is_array($body)) {
            $body = [];
        }

        $code = (int) ($body['code'] ?? ResponseCode::SERVER_ERROR);
        if ($code !== ResponseCode::SUCCESS) {
            return response()->json($body, $response->getStatusCode());
        }

        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        $list = is_array($data['list'] ?? null) ? $data['list'] : [];
        $rows = is_array($list['data'] ?? null) ? $list['data'] : [];
        $rows = $this->formatLegacyFundFlowRows($rows, $legacyUri);
        $count = (int) ($list['total'] ?? count($rows));
        $totalRow = $this->formatLegacyFundFlowTotalRow(
            is_array($data['totalRow'] ?? null) ? $data['totalRow'] : [],
            $legacyUri
        );

        if (substr($legacyUri, -2) === 'V2') {
            return response()->json([
                'code' => 200,
                'msg' => 'Request data successful.',
                'count' => $count,
                'data' => $count > 0 ? $rows : [],
                'totalRow' => $count > 0 ? $totalRow : [],
            ]);
        }

        return response()->json([
            'rows' => $count > 0 ? $rows : '',
            'total' => $count,
            'footer' => $count > 0 ? [$totalRow] : '',
        ]);
    }

    /**
     * Serve an export generated by the legacy admin workflow without allowing
     * the old file/role parameters to escape the dedicated export directories.
     */
    private function forwardLegacyDownloadFile(Request $request): Response
    {
        $file = (string) $request->route('file');
        $role = (string) $request->route('role');

        $legacyUri = $request->route() ? $request->route()->uri() : trim($request->path(), '/');
        if ($legacyUri === 'index/admin/amount/withdraw_downloadfile/{file}/{role}') {
            return $this->forwardLegacyWithdrawDownloadFile($request, $file, $role);
        }

        if ($role !== 'admin'
            || $file === ''
            || str_contains($file, '..')
            || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/D', $file)) {
            abort(404);
        }

        $filename = str_ends_with(strtolower($file), '.xlsx') ? $file : $file . '.xlsx';
        $roots = [storage_path('app/legacy-admin-exports/' . $role)];
        $legacyRoot = public_path('uploads/exports');
        if (is_dir($legacyRoot)) {
            $roots[] = $legacyRoot;
        }

        foreach ($roots as $root) {
            $rootPath = realpath($root);
            if ($rootPath === false) {
                continue;
            }

            $candidate = realpath($rootPath . DIRECTORY_SEPARATOR . $filename);
            if ($candidate === false
                || !is_file($candidate)
                || !str_starts_with($candidate, $rootPath . DIRECTORY_SEPARATOR)) {
                continue;
            }

            return response()->download($candidate, $filename);
        }

        abort(404);
    }

    /**
     * 下载旧出金两阶段导出的文件，只允许当前管理员专属目录中的 CSV/XLSX。
     */
    private function forwardLegacyWithdrawDownloadFile(Request $request, string $file, string $role): Response
    {
        $admin = $request->user('admin');
        if (!$admin
            || $role !== 'admin'
            || $file === ''
            || str_contains($file, '..')
            || !preg_match('/^withdrawals_' . (int) $admin->id . '_[A-Za-z0-9]+\.(csv|xlsx)$/iD', $file)) {
            abort(404);
        }

        $root = realpath(storage_path('app/legacy-admin-exports/admin/' . (int) $admin->id));
        if ($root === false) {
            abort(404);
        }

        $candidate = realpath($root . DIRECTORY_SEPARATOR . basename($file));
        if ($candidate === false
            || !is_file($candidate)
            || !str_starts_with($candidate, $root . DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        $contentType = strtolower((string) pathinfo($file, PATHINFO_EXTENSION)) === 'csv'
            ? 'text/csv; charset=UTF-8'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response()->download($candidate, basename($file), [
            'Content-Type' => $contentType,
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * 获取旧资金流水 URI 对应的现代查询路由。
     *
     * @param string $legacyUri 当前旧 URI。
     * @return string 现代后台 API 命名路由。
     */
    private function legacyFundFlowTargetRoute(string $legacyUri): string
    {
        if (strpos($legacyUri, 'undepositFlowSearch') !== false) {
            return 'admin_api_undepositFlowList';
        }

        if (strpos($legacyUri, 'depositFlowSearch') !== false) {
            return 'admin_api_depositFlowList';
        }

        if (strpos($legacyUri, 'withdrawFlowSearch') !== false) {
            return 'admin_api_withdrawFlowList';
        }

        return 'admin_api_withdrawFlowList';
    }

    /**
     * 把旧资金流水请求字段转换为现代查询字段。
     *
     * @param Request $request 当前旧后台请求。
     * @param string $legacyUri 当前旧 URI。
     * @return void
     */
    private function normalizeLegacyFundFlowPayload(Request $request, string $legacyUri): void
    {
        $payload = [];

        if ($request->filled('userId') && !$request->filled('user_id')) {
            $payload['user_id'] = $request->input('userId');
        }

        if ($request->filled('deposit_startdate') && !$request->filled('start_date')) {
            $payload['start_date'] = $request->input('deposit_startdate');
        }

        if ($request->filled('deposit_enddate') && !$request->filled('end_date')) {
            $payload['end_date'] = $request->input('deposit_enddate');
        }

        if (strpos($legacyUri, 'depositFlowSearch') !== false) {
            if ($request->filled('deposit_id') && !$request->filled('ticket')) {
                $payload['ticket'] = $request->input('deposit_id');
            }
            if ($request->filled('direct_deposit_source') && !$request->filled('deposit_source')) {
                $payload['deposit_source'] = $request->input('direct_deposit_source');
            }
        }

        if (strpos($legacyUri, 'withdrawFlowSearch') !== false
            && $request->filled('withdraw_id')
            && !$request->filled('ticket')) {
            $payload['ticket'] = $request->input('withdraw_id');
        }

        if (strpos($legacyUri, 'undepositFlowSearch') !== false
            && $request->filled('undeposit_id')
            && !$request->filled('local_order_no')) {
            $payload['local_order_no'] = $request->input('undeposit_id');
        }

        if ($payload !== []) {
            $request->merge($payload);
        }
    }

    /**
     * 格式化旧资金流水表格行。
     *
     * @param array<int, array<string, mixed>> $rows 现代列表行。
     * @param string $legacyUri 当前旧 URI。
     * @return array<int, array<string, mixed>> 旧 Layui 表格行。
     */
    private function formatLegacyFundFlowRows(array $rows, string $legacyUri): array
    {
        return array_map(function (array $row) use ($legacyUri): array {
            if (strpos($legacyUri, 'undepositFlowSearch') !== false) {
                $row['userId'] = (int) ($row['user_id'] ?? 0);
                $row['username'] = (string) ($row['user_name'] ?? ($row['username'] ?? ''));
            } else {
                $row['order_no'] = (int) ($row['ticket'] ?? ($row['order_no'] ?? 0));
                $row['userId'] = (int) ($row['userId'] ?? ($row['login'] ?? ($row['user_id'] ?? 0)));
                $row['username'] = (string) ($row['username'] ?? ($row['user_name'] ?? ''));
                $row['directProfit'] = round((float) ($row['directProfit'] ?? ($row['profit'] ?? 0)), 2);
                $row['directType'] = (string) ($row['directType'] ?? ($row['comment'] ?? ''));
                $row['directComment'] = (string) ($row['directComment'] ?? ($row['comment'] ?? ''));
                $row['directCloseTime'] = $row['directCloseTime'] ?? ($row['close_time'] ?? '');
            }

            return $row;
        }, $rows);
    }

    /**
     * 格式化旧资金流水合计行。
     *
     * @param array<string, mixed> $totalRow 现代合计行。
     * @param string $legacyUri 当前旧 URI。
     * @return array<string, mixed> 旧 V1 footer 或 V2 totalRow。
     */
    private function formatLegacyFundFlowTotalRow(array $totalRow, string $legacyUri): array
    {
        $totalRow['order_no'] = '总计';

        if (strpos($legacyUri, 'undepositFlowSearch') !== false) {
            $totalRow['userId'] = '';
            $totalRow['username'] = '';
            $totalRow['amount'] = round((float) ($totalRow['amount'] ?? 0), 2);

            return $totalRow;
        }

        $totalRow['userId'] = '';
        $totalRow['username'] = '';
        $totalRow['directProfit'] = round((float) ($totalRow['directProfit'] ?? ($totalRow['profit'] ?? 0)), 2);
        $totalRow['directType'] = '';
        $totalRow['directComment'] = '';
        $totalRow['directCloseTime'] = '';

        return $totalRow;
    }

    /**
     * 判断当前旧 URI 是否属于后台管理员账号写入口。
     *
     * 参数说明：
     * - 项目1的启用、停用、删除使用 GET 写操作；项目2只对白名单管理员账号动作兼容，避免全局放开 GET 写操作。
     * - 新增和编辑仍走 POST，但需要补旧字段 `statue` 和旧编辑密码字段 `password2`。
     *
     * @param string $legacyUri 当前旧后台 URI。
     * @return bool true 表示进入管理员账号专用兼容链。
     */
    private function isLegacyAdministratorAction(string $legacyUri): bool
    {
        return in_array($legacyUri, [
            'index/admin/Administrators/addsave',
            'index/admin/Administrators/editsave',
            'index/admin/Administrators/start',
            'index/admin/Administrators/stop',
            'index/admin/Administrators/del',
        ], true);
    }

    /**
     * 判断需要保留旧 profile/password/role 写接口响应字段的 URI。
     */
    private function isLegacyIdentityRoleAction(string $legacyUri): bool
    {
        return in_array($legacyUri, [
            'index/admin/userinfo/save',
            'index/admin/userpwd/save',
            'index/admin/role/addsave',
            'index/admin/role/editsave',
        ], true);
    }

    /**
     * 为旧 identity/role 写接口补充 state/msg，同时保留现代 code/message/data。
     */
    private function legacyIdentityRoleResponse(Response $response, string $legacyUri): Response
    {
        $body = method_exists($response, 'getData')
            ? $response->getData()
            : json_decode((string) $response->getContent());
        if (!is_object($body)) {
            $body = new \stdClass();
        }

        $code = (int) ($body->code ?? ResponseCode::SERVER_ERROR);
        $success = in_array($code, [
            ResponseCode::SUCCESS,
            ResponseCode::CREATED,
            ResponseCode::UPDATED,
        ], true);
        $messages = [
            'index/admin/userinfo/save' => [
                'success' => [1, '成功'],
                'failure' => [0, '失败'],
            ],
            'index/admin/userpwd/save' => [
                'success' => [1, '成功'],
                'failure' => [0, '失败'],
            ],
            'index/admin/role/addsave' => [
                'success' => ['1', '添加成功'],
                'failure' => ['0', '添加失败'],
            ],
            'index/admin/role/editsave' => [
                'success' => ['1', '修改成功'],
                'failure' => ['0', '修改失败'],
            ],
        ];
        $config = $messages[$legacyUri][$success ? 'success' : 'failure'];
        $body->state = $config[0];
        $body->msg = $config[1];

        if (method_exists($response, 'setData')) {
            $response->setData($body);
        } else {
            $response->setContent(json_encode($body, JSON_UNESCAPED_UNICODE));
        }

        return $response;
    }

    private function isLegacyUserGroupAction(string $legacyUri): bool
    {
        return in_array($legacyUri, [
            'index/admin/group/user_group_delete',
            'index/admin/group/user_group_search',
            'index/admin/group/user_group_searchV2',
            'index/admin/group/user_group_store',
            'index/admin/group/user_group_update',
        ], true);
    }

    private function legacyUserGroupResponse(Response $response, string $legacyUri): Response
    {
        $body = method_exists($response, 'getData')
            ? $response->getData(true)
            : json_decode((string) $response->getContent(), true);
        if (!is_array($body)) {
            $body = [];
        }

        $modernCode = (int) ($body['code'] ?? ResponseCode::SERVER_ERROR);
        if ($modernCode !== ResponseCode::SUCCESS
            && !in_array($modernCode, [ResponseCode::CREATED, ResponseCode::UPDATED, ResponseCode::DELETED], true)) {
            return response()->json($body, $response->getStatusCode());
        }

        if (in_array($legacyUri, [
            'index/admin/group/user_group_search',
            'index/admin/group/user_group_searchV2',
        ], true)) {
            $payload = is_array($body['data'] ?? null) ? $body['data'] : [];
            $rows = is_array($payload['data'] ?? null) ? $payload['data'] : [];
            $count = (int) ($payload['count'] ?? count($rows));
            if ($legacyUri === 'index/admin/group/user_group_search') {
                return response()->json([
                    'rows' => $count > 0 ? $rows : '',
                    'total' => $count,
                ]);
            }

            return response()->json([
                'code' => 200,
                'msg' => 'Request data successful.',
                'count' => $count,
                'data' => $count > 0 ? $rows : [],
                'totalRow' => [],
            ]);
        }

        return response()->json([
            'code' => 0,
            'modern_code' => $modernCode,
            'msg' => (string) ($body['message'] ?? '操作成功'),
            'message' => (string) ($body['message'] ?? '操作成功'),
            'data' => $body['data'] ?? (object) [],
        ]);
    }

    /**
     * 兼容旧后台管理员账号新增、编辑、启停和删除入口。
     *
     * 参数逻辑说明：
     * - addsave 默认写入 status=1，对齐旧项目新管理员默认启用 state=1。
     * - editsave 的 password2 才是旧页面提交的新密码，转发前覆盖现代 password 字段。
     * - start/stop/del 是项目1遗留 GET 写入口，只在这里显式兼容，避免影响其他高风险 GET 写路由。
     *
     * @param Request $request 当前旧后台请求。
     * @param string $legacyUri 当前旧 URI，用于选择旧响应文案和字段转换。
     * @param array{route: string, defaults?: array<string, mixed>} $target 现代目标路由配置。
     * @return Response 新旧字段并存的 JSON 响应。
     */
    private function forwardLegacyAdministratorAction(Request $request, string $legacyUri, array $target): Response
    {
        $defaults = $target['defaults'] ?? [];
        $payload = $this->payloadForLegacyTarget($request);

        if ($legacyUri === 'index/admin/Administrators/addsave') {
            $defaults['status'] = 1;
        }

        if ($legacyUri === 'index/admin/Administrators/editsave'
            && array_key_exists('password2', $payload)
            && trim((string) $payload['password2']) !== '') {
            $defaults['password'] = (string) $payload['password2'];
        }

        $response = $this->forwardToNamedRoute($request, (string) $target['route'], $defaults);

        return $this->legacyAdministratorResponse($response, $legacyUri, $payload);
    }

    /**
     * 为旧后台管理员账号动作补充项目1响应字段。
     *
     * 参数说明：
     * - 现代 code/message/data 保留，供项目2统一接口处理。
     * - 旧字段 statue/msg/id 保留，供旧 Blade/Layui 脚本继续判断成功、失败和目标管理员 ID。
     *
     * @param Response $response 现代控制器或转发层返回的响应。
     * @param string $legacyUri 当前旧 URI，用于选择成功/失败文案。
     * @param array<string, mixed> $payload 旧请求体，用于回填 id。
     * @return Response 新旧字段合并后的 JSON 响应。
     */
    private function legacyAdministratorResponse(Response $response, string $legacyUri, array $payload): Response
    {
        $body = method_exists($response, 'getData')
            ? $response->getData(true)
            : json_decode((string) $response->getContent(), true);
        if (!is_array($body)) {
            $body = [];
        }

        $code = (int) ($body['code'] ?? ResponseCode::SERVER_ERROR);
        $successCodes = [
            ResponseCode::CREATED,
            ResponseCode::UPDATED,
            ResponseCode::DELETED,
            ResponseCode::SUCCESS,
        ];
        $success = in_array($code, $successCodes, true);
        $config = $this->legacyAdministratorMessageConfig($legacyUri, $success);

        $body['statue'] = $config['statue'];
        $body['msg'] = $config['msg'];
        if (array_key_exists('id', $payload) && $payload['id'] !== '') {
            $body['id'] = (string) $payload['id'];
        }

        return response()->json($body, $response->getStatusCode());
    }

    /**
     * 获取旧后台管理员账号动作的旧响应文案。
     *
     * @param string $legacyUri 当前旧 URI。
     * @param bool $success 现代业务响应是否表示成功。
     * @return array{statue: int|string, msg: string} 旧 statue/msg 字段。
     */
    private function legacyAdministratorMessageConfig(string $legacyUri, bool $success): array
    {
        $map = [
            'index/admin/Administrators/addsave' => [
                'success' => ['statue' => '1', 'msg' => '添加成功'],
                'failure' => ['statue' => '0', 'msg' => '添加失败'],
            ],
            'index/admin/Administrators/editsave' => [
                'success' => ['statue' => '1', 'msg' => '编辑成功'],
                'failure' => ['statue' => '0', 'msg' => '编辑失败'],
            ],
            'index/admin/Administrators/start' => [
                'success' => ['statue' => 1, 'msg' => '启用成功'],
                'failure' => ['statue' => 0, 'msg' => '启用失败'],
            ],
            'index/admin/Administrators/stop' => [
                'success' => ['statue' => 1, 'msg' => '停用成功'],
                'failure' => ['statue' => 0, 'msg' => '停用失败'],
            ],
            'index/admin/Administrators/del' => [
                'success' => ['statue' => '1', 'msg' => '删除成功'],
                'failure' => ['statue' => '0', 'msg' => '删除失败'],
            ],
        ];

        return $map[$legacyUri][$success ? 'success' : 'failure']
            ?? ['statue' => $success ? '1' : '0', 'msg' => $success ? __('response.success') : __('response.error')];
    }

    /**
     * 兼容旧实名认证 V1/V2 列表响应。
     *
     * 现代分页器使用 data={data,total,...}，旧 V1 读取 rows/total，旧 V2 直接读取
     * count/data。这里仅在 legacy URI 上展开分页并补回旧列名，现代 API 保持原契约。
     */
    private function forwardLegacyAuthenticationSearch(Request $request, string $legacyUri): Response
    {
        $route = str_contains($legacyUri, 'Certified')
            ? 'admin_api_authCertifiedList'
            : 'admin_api_authPendingList';
        $response = $this->forwardToNamedRoute($request, $route, []);

        return $this->legacyListResponse($response, 'authentication');
    }

    /**
     * 兼容旧凭证 V1/V2 列表响应，并将 userId/startdate/enddate 的结果映射为旧字段。
     */
    private function forwardLegacyVoucherSearch(Request $request, string $legacyUri): Response
    {
        $response = $this->forwardToNamedRoute($request, 'admin_api_voucherList', []);

        return $this->legacyListResponse($response, 'voucher');
    }

    /**
     * 展开现代分页数据并补回旧表格字段；错误响应原样保留，仅增加旧 msg 字段。
     */
    private function legacyListResponse(Response $response, string $type): Response
    {
        if (!method_exists($response, 'getData')) {
            return $response;
        }

        $body = $response->getData(true);
        if (!is_array($body)) {
            return $response;
        }

        $code = (int) ($body['code'] ?? ResponseCode::SERVER_ERROR);
        $message = (string) ($body['message'] ?? '');
        $data = $body['data'] ?? [];
        $rows = [];
        $total = 0;

        if (is_array($data) && isset($data['data']) && is_array($data['data'])) {
            $rows = $data['data'];
            $total = (int) ($data['total'] ?? count($rows));
        } elseif (is_array($data)) {
            $rows = array_is_list($data) ? $data : [];
            $total = count($rows);
        }

        foreach ($rows as &$row) {
            if (!is_array($row)) {
                continue;
            }

            if ($type === 'authentication') {
                $row['IDcard_status'] = $row['id_card_status'] ?? null;
                $row['rec_crt_date'] = $row['created_at'] ?? null;
                $row['rec_upd_date'] = $row['updated_at'] ?? null;
                $row['mt4_grp'] = $row['mt4_grp'] ?? '';
            } else {
                $user = $row['user'] ?? [];
                $row['user_name'] = $row['user_name'] ?? (is_array($user) ? ($user['user_name'] ?? '') : '');
                $row['review_msg'] = $row['review_message'] ?? '';
                $row['rec_crt_date'] = $row['created_at'] ?? null;
                $row['rec_upd_date'] = $row['updated_at'] ?? null;
            }
        }
        unset($row);

        $body['message'] = $message;
        $body['msg'] = $message;
        $body['count'] = $total;
        $body['data'] = $rows;
        $body['totalRow'] = [];
        $body['rows'] = $rows;
        $body['total'] = $total;
        $body['footer'] = [];

        return response()->json($body, $response->getStatusCode(), $response->headers->all());
    }

    /**
     * 兼容旧凭证审核：reviewstatus=1 走通过，2 走拒绝，reviewmsg 对应现代 reason。
     * 旧页面把 recId 作为 voucher_infos.id；状态分支必须在进入现代控制器前确定，
     * 防止拒绝请求被固定映射成 approve 并丢失审核备注。
     */
    private function forwardLegacyVoucherReview(Request $request): Response
    {
        $payload = $this->payloadForLegacyTarget($request);
        $validator = Validator::make(
            ['reviewstatus' => $payload['reviewstatus'] ?? null],
            ['reviewstatus' => 'required|integer|in:1,2']
        );

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $status = (int) $payload['reviewstatus'];
        if (!array_key_exists('recId', $payload) && !array_key_exists('id', $payload)) {
            return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
        }

        if (!array_key_exists('reason', $payload) && array_key_exists('reviewmsg', $payload)) {
            $request->merge(['reason' => $payload['reviewmsg']]);
        }

        $response = $this->forwardToNamedRoute(
            $request,
            $status === 1 ? 'admin_api_voucherApprove' : 'admin_api_voucherReject',
            []
        );

        return $this->legacyMutationResponse($response, 'voucher');
    }

    /**
     * 兼容旧注销审核：cancel_userid 是业务 user_id，不是 cancel_applies.id。
     * 先定位唯一待处理申请，再把真实申请主键交给现代 approve/reject 控制器，
     * 因而仍会经过数据范围、MT4 同步和本地事务边界。
     */
    private function forwardLegacyCancelApply(Request $request, string $legacyUri): Response
    {
        $payload = $this->payloadForLegacyTarget($request);
        $userId = $payload['cancel_userid'] ?? null;
        $userIdValidator = Validator::make(['cancel_userid' => $userId], [
            'cancel_userid' => 'required|integer|min:1',
        ]);

        if ($userIdValidator->fails()) {
            return $this->error($userIdValidator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        if ($legacyUri === 'index/admin/cancel/update_cancel') {
            $decisionValidator = Validator::make(
                ['accept_rejection' => $payload['accept_rejection'] ?? null],
                ['accept_rejection' => 'required|integer|in:1,2']
            );
            if ($decisionValidator->fails()) {
                return $this->error($decisionValidator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $reviewRemark = trim((string) ($payload['cancel_remark'] ?? ''));
            $remarkValidator = Validator::make(['cancel_remark' => $reviewRemark], [
                'cancel_remark' => 'required|string|max:500',
            ]);
            if ($remarkValidator->fails()) {
                return $this->error($remarkValidator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $approve = (int) $payload['accept_rejection'] === 1;
        } else {
            $approve = $legacyUri === 'index/admin/cancel/cancel_apply_pass';
            $reviewRemark = trim((string) ($payload['reason'] ?? ($payload['cancel_remark'] ?? '')));
        }

        $admin = $request->user('admin');
        if (!$admin) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $applyQuery = CancelApply::query()
            ->where('cancel_applies.user_id', (int) $userId)
            ->where('cancel_applies.status', 0);
        app(AdminDataScopeService::class)->apply(
            $applyQuery,
            $admin,
            'user',
            'cancel_applies.user_id',
            null,
            'cancel_applies.created_by'
        );
        $apply = $applyQuery->orderBy('cancel_applies.id')->first();

        if (!$apply) {
            return $this->legacyCancelApplyMutationResponse(
                $this->error(__('admin.cancel_apply_not_found_or_processed'), ResponseCode::DATA_NOT_FOUND)
            );
        }

        $request->merge([
            'id' => $apply->id,
            'reason' => $reviewRemark,
        ]);

        return $this->legacyCancelApplyMutationResponse(
            $this->forwardToNamedRoute(
                $request,
                $approve ? 'admin_api_cancelApplyApprove' : 'admin_api_cancelApplyReject',
                []
            )
        );
    }

    /**
     * 兼容旧销户申请列表的字段名、默认日期和 V1/V2 envelope。
     */
    private function forwardLegacyCancelApplyList(Request $request, string $legacyUri): Response
    {
        $original = $request->all();
        $payload = $this->payloadForLegacyTarget($request);
        if (!array_key_exists('status', $payload) && array_key_exists('cancel_status', $payload)) {
            $payload['status'] = $payload['cancel_status'];
        }
        if (!array_key_exists('per_page', $payload)) {
            $payload['per_page'] = $payload['rows'] ?? ($payload['limit'] ?? 20);
        }
        if (!array_key_exists('start_date', $payload) || trim((string) $payload['start_date']) === '') {
            $payload['start_date'] = '2024-01-01';
        }
        if (!array_key_exists('end_date', $payload) || trim((string) $payload['end_date']) === '') {
            $payload['end_date'] = date('Y-m-d');
        }

        $request->replace($payload);
        try {
            $response = $this->forwardToNamedRoute($request, 'admin_api_cancelApplyList', []);
        } finally {
            $request->replace($original);
        }

        $body = json_decode((string) $response->getContent(), true);
        $body = is_array($body) ? $body : [];
        if ((int) ($body['code'] ?? ResponseCode::SERVER_ERROR) !== ResponseCode::SUCCESS) {
            return $response;
        }

        $page = is_array($body['data'] ?? null) ? $body['data'] : [];
        $rows = array_map(static function (array $row): array {
            $reviewRemark = trim((string) ($row['reject_reason'] ?? ''));

            return [
                'cancel_id' => (int) ($row['id'] ?? 0),
                'cancel_userid' => (int) ($row['user_id'] ?? 0),
                'cancel_username' => (string) ($row['user_name'] ?? ''),
                'bal' => (string) ($row['balance'] ?? '0.00'),
                'vol' => (int) ($row['open_positions'] ?? 0),
                'cancel_status' => (int) ($row['status'] ?? 0),
                'cancel_remark' => $reviewRemark !== ''
                    ? $reviewRemark
                    : (string) ($row['cancel_remark'] ?? ''),
                'review_remark' => $reviewRemark,
                'rec_crt_date' => (string) ($row['created_at'] ?? ''),
                'rec_upd_date' => (string) ($row['updated_at'] ?? ''),
            ];
        }, is_array($page['data'] ?? null) ? $page['data'] : []);

        if (substr($legacyUri, -2) === 'V2') {
            return response()->json([
                'code' => 200,
                'msg' => 'Request data successful.',
                'count' => empty($rows) ? 0 : (int) ($page['total'] ?? count($rows)),
                'data' => $rows,
                'totalRow' => [],
            ]);
        }

        return response()->json([
            'rows' => empty($rows) ? '' : $rows,
            'total' => empty($rows) ? '' : (int) ($page['total'] ?? count($rows)),
        ]);
    }

    /**
     * 在现代业务码之外补回旧页面依赖的 msg/col 审核结果。
     */
    private function legacyCancelApplyMutationResponse(Response $response): Response
    {
        $body = json_decode((string) $response->getContent(), true);
        $body = is_array($body) ? $body : [];
        $code = (int) ($body['code'] ?? ResponseCode::SERVER_ERROR);
        $success = $code === ResponseCode::SUCCESS;

        if ($success) {
            $column = 'UPDATESUC';
        } elseif ($code === ResponseCode::DATA_NOT_FOUND || $code === ResponseCode::PERMISSION_DENIED) {
            $column = 'INVALIDUSER';
        } elseif ($code === ResponseCode::MT4_SYNC_FAILED) {
            $column = 'FATALCANOTCONNECT';
        } else {
            $column = 'UPDATEFAIL';
        }

        $body['msg'] = $success ? 'SUCCESS' : 'FAIL';
        $body['col'] = $column;

        return response()->json($body, $response->getStatusCode(), $response->headers->all());
    }

    /**
     * 兼容旧批量重试数组，但沿用现代安全状态机：这里只把失败记录重新放回待处理队列，
     * 不在 HTTP 循环中直接重复 MT4 资金命令。每行只接受导入表主键 id，避免 user_id
     * 或 batch_no 被误当主键；现代控制器继续负责失败态和管理员数据范围校验。
     */
    /**
     * 兼容旧版 batchOperation/batchOperationWithdraw 的直接 MT4 批量资金操作。
     * 旧接口使用逗号分隔的 id_list，并把用户 ID 拼接到 MT4 备注后；现代导入接口是
     * 单条落库，不能直接转发，否则会丢失批量语义。这里保留旧响应字段，并严格依据
     * 结算网关结果决定是否返回成功，禁止用空订单号伪造成功。
     */
    private function forwardLegacyBatchOperation(Request $request, string $type): Response
    {
        $payload = $this->payloadForLegacyTarget($request);
        $amountField = $type === 'deposit' ? 'deposit_amount' : 'withdraw_amount';
        $commentField = $type === 'deposit' ? 'deposit_comment' : 'withdraw_comment';
        $amount = $payload[$amountField] ?? null;
        $comment = trim((string) ($payload[$commentField] ?? ''));
        $idList = $payload['id_list'] ?? null;

        $validator = Validator::make([
            'amount' => $amount,
            'comment' => $comment,
            'id_list' => $idList,
        ], [
            'amount' => ['required', 'numeric', 'gt:0'],
            'comment' => ['nullable', 'string', 'max:500'],
            'id_list' => ['required', 'string', 'max:5000'],
        ]);
        if ($validator->fails()) {
            return $this->legacyBatchAmountResponse(ResponseCode::VALIDATION_FAILED, $validator->errors()->first(), 0, '', 0);
        }

        $rawIds = array_map('trim', explode(',', (string) $idList));
        $ids = [];
        foreach ($rawIds as $rawId) {
            if (!preg_match('/^[1-9]\d*$/D', $rawId)) {
                return $this->legacyBatchAmountResponse(ResponseCode::VALIDATION_FAILED, __('response.validation_failed'), 0, '', 0);
            }
            $ids[] = (int) $rawId;
        }
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return $this->legacyBatchAmountResponse(ResponseCode::VALIDATION_FAILED, __('response.validation_failed'), 0, '', 0);
        }

        $admin = $request->user('admin') ?: Auth::guard('admin')->user();
        if (!$admin) {
            return $this->legacyBatchAmountResponse(ResponseCode::PERMISSION_DENIED, __('response.permission_denied'), 0, '', 0);
        }

        $existingIds = UserInfo::query()
            ->whereIn('user_id', $ids)
            ->pluck('user_id')
            ->map(static function ($id): int {
                return (int) $id;
            })
            ->all();
        if (count($existingIds) !== count($ids)) {
            return $this->legacyBatchAmountResponse(ResponseCode::DATA_NOT_FOUND, __('response.data_not_found'), 0, '', 0);
        }

        foreach ($ids as $userId) {
            if (!app(AdminDataScopeService::class)->canAccessUser($admin, $userId, $type)) {
                return $this->legacyBatchAmountResponse(ResponseCode::DATA_NOT_FOUND, __('response.data_not_found'), 0, '', 0);
            }
        }

        $startedAt = microtime(true);
        $orders = [];
        $gateway = $type === 'deposit'
            ? app(DepositSettlementGateway::class)
            : app(DepositRefundGateway::class);

        foreach ($ids as $userId) {
            $mt4Comment = $comment . '-' . $userId;
            try {
                $result = $type === 'deposit'
                    ? $gateway->deposit($userId, (string) $amount, $mt4Comment)
                    : $gateway->refund($userId, (string) $amount, $mt4Comment);
            } catch (\Throwable $exception) {
                $result = \App\Services\Payment\DepositSettlementResult::unknown('gateway_exception');
            }

            if ($result->status() !== 'settled' || trim((string) $result->providerReference()) === '') {
                return $this->legacyBatchAmountResponse(
                    ResponseCode::MT4_SYNC_FAILED,
                    __('response.mt4_sync_failed'),
                    count($orders),
                    implode(',', $orders),
                    (int) max(0, microtime(true) - $startedAt)
                );
            }

            $orders[] = trim((string) $result->providerReference());
        }

        return $this->legacyBatchAmountResponse(
            ResponseCode::SUCCESS,
            __('response.success'),
            count($ids),
            implode(',', $orders),
            (int) max(0, microtime(true) - $startedAt)
        );
    }

    /** 将现代导入分页响应恢复为旧的 count/data/totalRow 协议。 */
    private function forwardLegacyBatchImportSearch(Request $request, string $legacyUri): Response
    {
        $targetRoute = $legacyUri === 'index/admin/amount/depositImportSearch'
            ? 'admin_api_depositImportList'
            : 'admin_api_withdrawImportList';
        $response = $this->forwardToNamedRoute($request, $targetRoute, []);
        if (!method_exists($response, 'getData')) {
            return $response;
        }

        $body = $response->getData(true);
        if (!is_array($body) || (int) ($body['code'] ?? ResponseCode::SERVER_ERROR) !== ResponseCode::SUCCESS) {
            return $response;
        }

        $page = $body['data'] ?? [];
        $rows = is_array($page) && isset($page['data']) && is_array($page['data'])
            ? $page['data']
            : (is_array($page) ? $page : []);
        $total = is_array($page) && isset($page['total']) ? (int) $page['total'] : count($rows);
        $amountTotal = 0.0;
        foreach ($rows as &$row) {
            if (!is_array($row)) {
                continue;
            }
            $row['is_sync_succ'] = (int) ($row['is_sync_succ'] ?? $row['is_synced'] ?? 0);
            $row['user_name'] = $row['user_name'] ?? ($row['user']['user_name'] ?? '');
            $amountTotal += (float) ($row['amount'] ?? 0);
        }
        unset($row);

        $body['msg'] = (string) ($body['message'] ?? __('response.success'));
        $body['count'] = $total;
        $body['data'] = $rows;
        $body['rows'] = $rows;
        $body['total'] = $total;
        $body['totalRow'] = [
            'user_id' => __('systemlanguage.total'),
            'amount' => number_format($amountTotal, 2, '.', ''),
        ];

        return response()->json($body, $response->getStatusCode(), $response->headers->all());
    }

    private function legacyBatchAmountResponse(int $code, string $message, int $count, string $orders, int $elapsed): Response
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'msg' => $message,
            'data' => (object) [],
            'no' => $count,
            'time' => $elapsed,
            'order' => $orders,
        ]);
    }

    private function forwardLegacyBatchRetry(Request $request, string $legacyUri): Response
    {
        $rows = $request->all();
        if (isset($rows['data']) && is_array($rows['data'])) {
            $rows = $rows['data'];
        } elseif (isset($rows['rows']) && is_array($rows['rows'])) {
            $rows = $rows['rows'];
        } elseif (array_key_exists('id', $rows)) {
            $rows = [$rows];
        }

        if (!is_array($rows) || $rows === []) {
            return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
        }

        $routeMap = [
            'index/admin/amount/againDepositAmount' => 'admin_api_retryDepositImport',
            'index/admin/amount/againWithdrawAmount' => 'admin_api_retryWithdrawImport',
            'index/admin/credit/againCreditAmount' => 'admin_api_retryCreditImport',
        ];
        $targetRoute = $routeMap[$legacyUri];
        $originalPayload = $request->all();
        $results = [];
        $successCount = 0;

        foreach (array_values($rows) as $index => $row) {
            if (!is_array($row)) {
                $results[] = [
                    'index' => $index,
                    'id' => null,
                    'code' => ResponseCode::VALIDATION_FAILED,
                    'message' => __('response.validation_failed'),
                ];
                continue;
            }

            $validator = Validator::make(['id' => $row['id'] ?? null], [
                'id' => 'required|integer|min:1',
            ]);
            if ($validator->fails()) {
                $results[] = [
                    'index' => $index,
                    'id' => $row['id'] ?? null,
                    'code' => ResponseCode::VALIDATION_FAILED,
                    'message' => $validator->errors()->first(),
                ];
                continue;
            }

            $request->replace(array_replace($row, ['id' => (int) $row['id']]));
            $itemResponse = $this->forwardToNamedRoute($request, $targetRoute, []);
            $itemPayload = method_exists($itemResponse, 'getData')
                ? $itemResponse->getData(true)
                : [];
            $itemCode = (int) ($itemPayload['code'] ?? ResponseCode::SERVER_ERROR);
            if ($itemCode === ResponseCode::SUCCESS) {
                $successCount++;
            }

            $results[] = [
                'index' => $index,
                'id' => (int) $row['id'],
                'code' => $itemCode,
                'message' => (string) ($itemPayload['message'] ?? __('response.server_error')),
            ];
        }

        $request->replace($originalPayload);
        $total = count($results);
        if ($successCount === 0) {
            $code = ResponseCode::VALIDATION_FAILED;
        } elseif ($successCount === $total) {
            $code = ResponseCode::BATCH_SUCCESS;
        } else {
            $code = ResponseCode::BATCH_PARTIAL_FAILED;
        }
        $message = __(ResponseCode::messageKey($code));

        return response()->json([
            'code' => $code,
            'message' => $message,
            'msg' => $message,
            'data' => [
                'total' => $total,
                'success' => $successCount,
                'failed' => $total - $successCount,
                'results' => $results,
            ],
        ]);
    }

    /**
     * 兼容旧后台批量出金审核入口。
     *
     * 参数逻辑说明：
     * - payload.status 表示旧页面批量目标状态：1=处理中、2=完成、3=拒绝。
     * - payload.orderList.*.recordId 表示项目2 withdraw_records.id，不能使用 userId 或票据号兜底。
     * - 每条记录逐一转发到现代出金状态机，保留锁行、数据范围、资金状态和退款 outbox 规则。
     *
     * @param Request $request 当前旧后台批量请求。
     * @return Response 批量处理结果；data.results 逐条说明成功或失败。
     */
    private function forwardLegacyBatchWithdrawApply(Request $request): Response
    {
        $payload = $request->input('payload');
        if (!is_array($payload)) {
            return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
        }

        $status = (string) ($payload['status'] ?? '');
        $routeMap = [
            '1' => 'admin_api_withdrawProcess',
            '2' => 'admin_api_withdrawComplete',
            '3' => 'admin_api_withdrawReject',
        ];
        if (!isset($routeMap[$status])) {
            return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
        }

        $rows = $payload['orderList'] ?? [];
        if (!is_array($rows) || $rows === []) {
            return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
        }

        $originalPayload = $request->all();
        $targetRoute = $routeMap[$status];
        $results = [];
        $successCount = 0;

        foreach (array_values($rows) as $index => $row) {
            if (!is_array($row)) {
                $results[] = $this->legacyBatchWithdrawResult(
                    $index,
                    null,
                    ResponseCode::VALIDATION_FAILED,
                    __('response.validation_failed')
                );
                continue;
            }

            $validator = Validator::make(['recordId' => $row['recordId'] ?? null], [
                'recordId' => 'required|integer|min:1',
            ]);
            if ($validator->fails()) {
                $results[] = $this->legacyBatchWithdrawResult(
                    $index,
                    $row['recordId'] ?? null,
                    ResponseCode::VALIDATION_FAILED,
                    $validator->errors()->first()
                );
                continue;
            }

            $itemPayload = ['id' => (int) $row['recordId']];
            if ($status === '3') {
                $itemPayload['reason'] = trim((string) ($payload['remark'] ?? ''));
                if ($itemPayload['reason'] === '') {
                    $itemPayload['reason'] = __('admin.legacy_batch_withdraw_reject_reason');
                }
            }

            $request->replace($itemPayload);
            $itemResponse = $this->forwardToNamedRoute($request, $targetRoute, []);
            $itemData = method_exists($itemResponse, 'getData')
                ? $itemResponse->getData(true)
                : [];
            $itemCode = (int) ($itemData['code'] ?? ResponseCode::SERVER_ERROR);
            $itemMessage = (string) ($itemData['message'] ?? __('response.server_error'));

            if (in_array($itemCode, [ResponseCode::SUCCESS, ResponseCode::UPDATED], true)) {
                $successCount++;
            }

            $results[] = $this->legacyBatchWithdrawResult(
                $index,
                (int) $row['recordId'],
                $itemCode,
                $itemMessage
            );
        }

        $request->replace($originalPayload);
        $total = count($results);
        if ($successCount === 0) {
            $code = ResponseCode::VALIDATION_FAILED;
        } elseif ($successCount === $total) {
            $code = ResponseCode::SUCCESS;
        } else {
            $code = ResponseCode::BATCH_PARTIAL_FAILED;
        }
        $message = __(ResponseCode::messageKey($code));

        return response()->json([
            'code' => $code,
            'message' => $message,
            'msg' => $message,
            'data' => [
                'total' => $total,
                'success' => $successCount,
                'failed' => $total - $successCount,
                'status' => (int) $status,
                'results' => $results,
            ],
        ]);
    }

    /**
     * 生成旧批量出金逐条结果。
     *
     * @param int $index orderList 中的原始顺序。
     * @param int|string|null $recordId 旧页面传入的出金记录 ID。
     * @param int $code 单条现代状态机返回码。
     * @param string $message 单条结果中文消息。
     * @return array<string, mixed> 可直接返回给 Layui 表格脚本的结果行。
     */
    private function legacyBatchWithdrawResult(int $index, $recordId, int $code, string $message): array
    {
        return [
            'index' => $index,
            'recordId' => $recordId === null ? null : (int) $recordId,
            'code' => $code,
            'message' => $message,
        ];
    }

    /**
     * 兼容旧后台单笔出金生成本地 OTC 订单号入口。
     *
     * 参数说明：
     * - recordId 是旧页面提交的出金记录主键，对应项目2 withdraw_records.id。
     * - userId、orderId 只属于页面展示或辅助信息，不能兜底为出金主键。
     * - 项目1只写 orderId_LOC，不写 apply_status；项目2因此只更新 local_order_no，不推进 status。
     *
     * 返回说明：
     * - 待处理且没有 withdraw_settlement_outbox 的记录返回 SUCCESS，并兼容旧字段 msg/err/col。
     * - 已存在资金 outbox、状态不是待处理、记录不存在或越权时返回失败，不覆盖资金幂等订单号。
     *
     * @param Request $request 旧后台提交的 HTTP 请求，可能是 JSON 或普通表单。
     * @return Response 兼容旧 Layui 的 JSON；msg=SUCCESS 表示本地订单号已生成。
     */
    private function forwardLegacyUpdateCurrOrderId(Request $request): Response
    {
        $payload = $this->payloadForLegacyTarget($request);
        $validator = Validator::make(
            ['recordId' => $payload['recordId'] ?? null],
            ['recordId' => 'required|integer|min:1']
        );

        if ($validator->fails()) {
            return $this->legacyUpdateCurrOrderIdResponse(
                ResponseCode::VALIDATION_FAILED,
                $validator->errors()->first(),
                'FAIL',
                'VALIDATION_FAILED',
                'recordId'
            );
        }

        $admin = $request->user('admin') ?: Auth::guard('admin')->user();
        if (!$admin) {
            return $this->legacyUpdateCurrOrderIdResponse(
                ResponseCode::PERMISSION_DENIED,
                __('response.permission_denied'),
                'FAIL',
                'PERMISSION_DENIED',
                'NOCOL'
            );
        }

        $recordId = (int) $payload['recordId'];
        $failedResponse = null;

        $newOrderNo = DB::transaction(function () use ($recordId, $request, $admin, &$failedResponse): ?string {
            $locked = WithdrawRecord::query()->whereKey($recordId)->lockForUpdate()->first();
            if (!$locked || (int) $locked->status !== 0) {
                $failedResponse = $this->legacyUpdateCurrOrderIdResponse(
                    ResponseCode::DATA_NOT_FOUND,
                    __('admin.withdrawal_not_found_or_invalid'),
                    'FAIL',
                    'UPDATEFAIL',
                    'NOCOL'
                );

                return null;
            }

            $scope = app(AdminDataScopeService::class);
            if (!$scope->canAccessUser($admin, (int) $locked->user_id, 'user')) {
                $failedResponse = $this->legacyUpdateCurrOrderIdResponse(
                    ResponseCode::PERMISSION_DENIED,
                    __('response.permission_denied'),
                    'FAIL',
                    'PERMISSION_DENIED',
                    'NOCOL'
                );

                return null;
            }

            $hasOutbox = WithdrawSettlementOutbox::query()
                ->where('withdraw_record_id', $locked->id)
                ->lockForUpdate()
                ->exists();
            if ($hasOutbox) {
                $failedResponse = $this->legacyUpdateCurrOrderIdResponse(
                    ResponseCode::DATA_NOT_FOUND,
                    __('admin.withdrawal_not_found_or_invalid'),
                    'FAIL',
                    'UPDATEFAIL',
                    'NOCOL'
                );

                return null;
            }

            $generatedOrderNo = 'BROTC-' . date('YmdHis') . '-WR-' . (int) $locked->user_id;
            $exists = WithdrawRecord::query()
                ->where('local_order_no', $generatedOrderNo)
                ->whereKeyNot($locked->id)
                ->exists();
            if ($exists) {
                $failedResponse = $this->legacyUpdateCurrOrderIdResponse(
                    ResponseCode::DATA_ALREADY_EXISTS,
                    __('response.data_already_exists'),
                    'FAIL',
                    'UPDATEFAIL',
                    'NOCOL'
                );

                return null;
            }

            $locked->local_order_no = $generatedOrderNo;
            $locked->updated_by = (string) $admin->getKey();
            $locked->saveOrFail();

            return $generatedOrderNo;
        }, 3);

        if ($failedResponse) {
            return $failedResponse;
        }

        if ($newOrderNo === null) {
            return $this->legacyUpdateCurrOrderIdResponse(
                ResponseCode::DATA_NOT_FOUND,
                __('admin.withdrawal_not_found_or_invalid'),
                'FAIL',
                'UPDATEFAIL',
                'NOCOL'
            );
        }

        return $this->legacyUpdateCurrOrderIdResponse(
            ResponseCode::SUCCESS,
            __('response.success'),
            'SUCCESS',
            'NOERR',
            'NOCOL',
            ['local_order_no' => $newOrderNo]
        );
    }

    /**
     * 组装旧 updateCurrOrderId 的兼容响应。
     *
     * 参数说明：
     * - code/message/data 是项目2统一 API 字段，方便现代前端继续按标准格式读取。
     * - msg/err/col 是项目1 Layui 页面依赖的旧字段，分别表示旧操作结果、错误码和错误字段。
     *
     * @param int $code 项目2业务响应码。
     * @param string $message 当前语言环境下的响应说明。
     * @param string $legacyMsg 旧字段 msg；SUCCESS 表示成功，FAIL 表示失败。
     * @param string $legacyErr 旧字段 err；NOERR 表示无错误。
     * @param string $legacyCol 旧字段 col；NOCOL 表示没有单独错误字段。
     * @param array<string, mixed> $data 附加返回数据，成功时包含 local_order_no。
     * @return Response 新旧字段并存的 JSON 响应。
     */
    private function legacyUpdateCurrOrderIdResponse(
        int $code,
        string $message,
        string $legacyMsg,
        string $legacyErr,
        string $legacyCol,
        array $data = []
    ): Response {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'data' => (object) $data,
            'msg' => $legacyMsg,
            'err' => $legacyErr,
            'col' => $legacyCol,
        ]);
    }

    /**
     * 将新项目 rights_settlements 记录转换为旧权益汇总行。
     * 新表没有旧项目的利润、周期等字段，因此缺失字段保持空值；金额、用户、状态和日期保持可追溯。
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int, footer: array<int, array<string, mixed>>}
     */
    private function legacyRightsSettlementRows(
        Request $request,
        ?int $userId = null,
        ?string $status = null,
        ?string $sumDate = null,
        bool $paginate = true
    ): array {
        $payload = $this->payloadForLegacyTarget($request);
        $userId = $userId ?? $this->legacyPositiveInt($payload['userId'] ?? $payload['user_id'] ?? null);
        $status = $status ?? (($payload['orderstatus'] ?? $payload['rightsSumStatus'] ?? null) !== null
            ? (string) ($payload['orderstatus'] ?? $payload['rightsSumStatus'])
            : null);
        $sumDate = $sumDate ?? (($payload['rightsSumDate'] ?? $payload['sumdata'] ?? null) !== null
            ? (string) ($payload['rightsSumDate'] ?? $payload['sumdata'])
            : null);

        $query = DB::table('rights_settlements')->whereNull('deleted_at');
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $modernStatus = null;
        if ($status !== null && $status !== '') {
            if (!in_array($status, ['0', '1', '2'], true)) {
                return ['rows' => [], 'total' => 0, 'footer' => []];
            }
            $modernStatus = $status === '2' ? 1 : 0;
            $query->where('status', $modernStatus);
        }

        $startDate = $payload['startdate'] ?? $payload['start_date'] ?? null;
        $endDate = $payload['enddate'] ?? $payload['end_date'] ?? null;
        if (is_string($sumDate) && preg_match('/^\d{8}$/', $sumDate)) {
            $startDate = substr($sumDate, 0, 4) . '-' . substr($sumDate, 4, 2) . '-' . substr($sumDate, 6, 2);
            $endDate = $startDate;
        }

        if (is_string($startDate) && ($startTimestamp = strtotime($startDate . ' 00:00:00')) !== false) {
            $query->where('created_at', '>=', $startTimestamp);
        }
        if (is_string($endDate) && ($endTimestamp = strtotime($endDate . ' 23:59:59')) !== false) {
            $query->where('created_at', '<=', $endTimestamp);
        }

        $admin = $request->user('admin') ?: Auth::guard('admin')->user();
        $records = $query->orderByDesc('created_at')->orderByDesc('id')->get();
        if ($admin) {
            $scope = app(AdminDataScopeService::class);
            $records = $records->filter(static function ($record) use ($scope, $admin): bool {
                return $scope->canAccessUser($admin, (int) $record->user_id, 'user');
            })->values();
        }

        $total = $records->count();
        $page = max(1, (int) ($payload['page'] ?? 1));
        $limit = (int) ($payload['limit'] ?? $payload['per_page'] ?? 15);
        $limit = $limit > 0 ? min($limit, 100) : 15;
        $visibleRecords = $paginate ? $records->slice(($page - 1) * $limit, $limit) : $records;
        $rows = $visibleRecords->map(function ($record): array {
            $amount = number_format((float) $record->amount, 8, '.', '');
            $createdAt = (int) $record->created_at;
            $date = $createdAt > 0 ? date('Ymd', $createdAt) : '';
            $oldStatus = (int) $record->status === 1 ? 2 : 1;

            return [
                'rightsId' => (int) $record->id,
                'rightsUserIdent' => '',
                'rightsUserId' => (int) $record->user_id,
                'rightsUserPId' => '',
                'rightsUserProfit' => '',
                'rightsUserVolume' => '',
                'rightsUserValue' => '',
                'rightsUserValueDiff' => '',
                'rightsUseCycle' => '',
                'rightsMt4OrderId' => '',
                'rightsSumDate' => $date,
                'rightsSumStatus' => $oldStatus,
                'rightsManualReason' => (string) ($record->remark ?? ''),
                'rightsSumReturnamt' => '',
                'rightsSumShouxufei' => '',
                'rightsSumSwaps' => '',
                'rightsSumMoney' => $amount,
                'rightsSumYajin' => '',
                'rightsSumRealamt' => $amount,
                'rightsSumComm' => '',
                'rightsSumRemarks' => (string) ($record->remark ?? ''),
                'rightsSumDatetype' => 'mainData',
                'voided' => '1',
                'rec_crt_date' => $createdAt > 0 ? date('Y-m-d H:i:s', $createdAt) : '',
                'rec_upd_date' => (int) $record->updated_at > 0 ? date('Y-m-d H:i:s', (int) $record->updated_at) : '',
                'realamt' => $amount,
            ];
        })->values()->all();

        $sum = '0.00000000';
        foreach ($records as $record) {
            $sum = number_format((float) $sum + (float) $record->amount, 8, '.', '');
        }

        $footer = $total > 0 ? [[
            'rightsUserId' => '',
            'rightsSumRemarks' => __('systemlanguage.total'),
            'rightsSumRealamt' => $sum,
            'realamt' => $sum,
        ]] : [];

        return ['rows' => $rows, 'total' => $total, 'footer' => $footer];
    }

    private function forwardLegacyRightsSummarySearch(Request $request): Response
    {
        $legacy = $this->legacyRightsSettlementRows($request);
        $modernResponse = $this->forwardToNamedRoute($request, 'admin_api_rightsSummaryList', []);
        $modern = method_exists($modernResponse, 'getData') ? $modernResponse->getData(true) : [];
        $modern['rows'] = $legacy['rows'];
        $modern['total'] = $legacy['total'];
        $modern['footer'] = $legacy['footer'];
        $modern['data'] = is_array($modern['data'] ?? null) ? $modern['data'] : [];
        $modern['data']['rows'] = $legacy['rows'];
        $modern['data']['total'] = $legacy['total'];
        $modern['data']['footer'] = $legacy['footer'];

        return response()->json($modern, $modernResponse->getStatusCode());
    }

    private function forwardLegacyRightsSummaryDetail(Request $request): Response
    {
        // 旧页面会先打开权益汇总 Blade，再由 Ajax 请求详情；保留无 JSON 协商时的页面契约。
        if (!$request->expectsJson()) {
            return $this->renderLegacyPage(
                'index/admin/amount/rightsSummarySearchDetail/{uid}/{status}/{sumdata}',
                $request
            );
        }

        $rawUserId = $request->route('uid');
        $rawStatus = $request->route('status');
        $sumDate = (string) $request->route('sumdata');
        $validator = Validator::make(
            ['uid' => $rawUserId, 'status' => $rawStatus, 'sumdata' => $sumDate],
            [
                'uid' => 'required|integer|min:1',
                'status' => 'required|in:1,2',
                'sumdata' => ['required', 'regex:/^\d{8}$/'],
            ]
        );
        if ($validator->fails()) {
            return response()->json([
                'code' => ResponseCode::VALIDATION_FAILED,
                'message' => $validator->errors()->first(),
                'data' => (object) [],
                'msg' => 'FAIL',
                'err' => 'VALIDATION_FAILED',
                'col' => 'sumdata',
            ]);
        }

        $legacy = $this->legacyRightsSettlementRows($request, (int) $rawUserId, (string) $rawStatus, $sumDate, false);

        return response()->json([
            'code' => ResponseCode::SUCCESS,
            'message' => __('admin.rights_summary_fetched'),
            'data' => [
                'rows' => $legacy['rows'],
                'total' => $legacy['total'],
                'footer' => $legacy['footer'],
            ],
            'rows' => $legacy['rows'],
            'total' => $legacy['total'],
            'footer' => $legacy['footer'],
        ]);
    }

    private function forwardLegacyRightsSumExport(Request $request): Response
    {
        $legacy = $this->legacyRightsSettlementRows($request, null, null, null, false);
        $rows = [[
            'rightsId',
            'rightsUserId',
            'rightsSumStatus',
            'rightsSumDate',
            'rightsSumRealamt',
            'realamt',
            'rightsSumRemarks',
        ]];
        foreach ($legacy['rows'] as $row) {
            $rows[] = [
                $row['rightsId'],
                $row['rightsUserId'],
                $row['rightsSumStatus'],
                $row['rightsSumDate'],
                $row['rightsSumRealamt'],
                $row['realamt'],
                $row['rightsSumRemarks'],
            ];
        }

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, 'rights_summary_export.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Legacy-Export-Message' => $legacy['total'] > 0 ? 'rights_summary_export.csv' : 'FAIL',
        ]);
    }

    private function legacyPositiveInt($value): ?int
    {
        if ($value === null || !preg_match('/^[1-9]\d*$/D', (string) $value)) {
            return null;
        }

        return (int) $value;
    }

    /**
     * 兼容旧后台单笔 OTC 下单入口。
     *
     * 旧项目使用 orderId/userId 读取待处理出金记录，随后生成本地单号并请求 OTC。
     * 项目2没有经过验证的 OTC 下单协议，因此这里仅做参数、记录和权限校验，明确失败关闭，
     * 不写入 local_order_no、third_order_no、资金状态或 outbox，避免把未结算的假成功暴露给旧页面。
     */
    private function forwardLegacyGenerateOtcOrder(Request $request): Response
    {
        $payload = $this->payloadForLegacyTarget($request);
        $rawOrderId = $payload['orderId'] ?? $payload['order_id'] ?? $payload['recordId'] ?? $payload['id'] ?? null;
        $rawUserId = $payload['userId'] ?? $payload['user_id'] ?? null;
        $validator = Validator::make(
            ['orderId' => $rawOrderId, 'userId' => $rawUserId],
            [
                'orderId' => 'required|integer|min:1',
                'userId' => 'required|integer|min:1',
            ]
        );

        if ($validator->fails()) {
            $field = $validator->errors()->has('orderId') ? 'orderId' : 'userId';

            return $this->legacyGenerateOtcOrderResponse(
                ResponseCode::VALIDATION_FAILED,
                $validator->errors()->first(),
                'FAIL',
                'VALIDATION_FAILED',
                $field
            );
        }

        $admin = $request->user('admin') ?: Auth::guard('admin')->user();
        if (!$admin) {
            return $this->legacyGenerateOtcOrderResponse(
                ResponseCode::PERMISSION_DENIED,
                __('response.permission_denied'),
                'FAIL',
                'PERMISSION_DENIED',
                'NOCOL'
            );
        }

        $orderId = (int) $rawOrderId;
        $userId = (int) $rawUserId;
        $withdraw = WithdrawRecord::query()
            ->whereKey($orderId)
            ->where('user_id', $userId)
            ->where('status', 0)
            ->first();

        if (!$withdraw) {
            return $this->legacyGenerateOtcOrderResponse(
                ResponseCode::DATA_NOT_FOUND,
                __('admin.withdrawal_not_found_or_invalid'),
                'FAIL',
                'UPDATEFAIL',
                'NOCOL'
            );
        }

        $scope = app(AdminDataScopeService::class);
        if (!$scope->canAccessRecord(
            $admin,
            (int) $withdraw->user_id,
            $withdraw->created_by,
            'withdraw'
        )) {
            return $this->legacyGenerateOtcOrderResponse(
                ResponseCode::PERMISSION_DENIED,
                __('response.permission_denied'),
                'FAIL',
                'PERMISSION_DENIED',
                'NOCOL'
            );
        }

        if (trim((string) $withdraw->third_order_no) !== '') {
            return $this->legacyGenerateOtcOrderResponse(
                ResponseCode::DATA_ALREADY_EXISTS,
                __('response.data_already_exists'),
                'exists order',
                'errexists',
                'nocol',
                ['record_id' => $orderId, 'user_id' => $userId]
            );
        }

        $reason = 'OTC payment protocol is unsupported.';

        return $this->legacyGenerateOtcOrderResponse(
            ResponseCode::THIRD_PARTY_ERROR,
            __('response.third_party_error'),
            'FAIL',
            'OTCERR',
            [
                'recordId' => $orderId,
                'userId' => $userId,
                'reason' => $reason,
            ],
            [
                'record_id' => $orderId,
                'user_id' => $userId,
                'reason' => $reason,
            ]
        );
    }

    /**
     * 兼容旧后台 order_status/order_status_OTC 的字段与状态机。
     * order_status 只把 1/2 映射到现代 process/complete；OTC 分支协议未接入时始终失败关闭。
     */
    private function forwardLegacyOrderStatus(Request $request, string $legacyUri): Response
    {
        $payload = $this->payloadForLegacyTarget($request);
        $rawOrderId = $payload['orderId'] ?? $payload['order_id'] ?? $payload['recordId'] ?? $payload['id'] ?? null;
        $rawStatus = $payload['orderStatus'] ?? $payload['order_status'] ?? $payload['status'] ?? null;
        $orderRemark = trim((string) ($payload['orderRemark'] ?? $payload['order_remark'] ?? $payload['remark'] ?? ''));

        $validator = Validator::make(
            ['orderId' => $rawOrderId, 'orderStatus' => $rawStatus],
            [
                'orderId' => 'required|integer|min:1',
                'orderStatus' => 'required|in:0,1,2,3',
            ]
        );

        if ($validator->fails()) {
            if ($validator->errors()->has('orderStatus')) {
                return $this->legacyOrderStatusResponse(
                    ResponseCode::VALIDATION_FAILED,
                    $validator->errors()->first(),
                    'FAIL',
                    'invalidValue',
                    'apply_status'
                );
            }

            return $this->legacyOrderStatusResponse(
                ResponseCode::VALIDATION_FAILED,
                $validator->errors()->first(),
                'FAIL',
                'VALIDATION_FAILED',
                'orderId'
            );
        }

        $admin = $request->user('admin') ?: Auth::guard('admin')->user();
        if (!$admin) {
            return $this->legacyOrderStatusResponse(
                ResponseCode::PERMISSION_DENIED,
                __('response.permission_denied'),
                'FAIL',
                'PERMISSION_DENIED',
                'NOCOL'
            );
        }

        $orderId = (int) $rawOrderId;
        $orderStatus = (string) $rawStatus;
        $withdraw = WithdrawRecord::query()->whereKey($orderId)->first();
        if (!$withdraw) {
            return $this->legacyOrderStatusResponse(
                ResponseCode::DATA_NOT_FOUND,
                __('admin.withdrawal_not_found_or_invalid'),
                'FAIL',
                'UPDATEFAIL',
                'NOCOL'
            );
        }

        $scope = app(AdminDataScopeService::class);
        if (!$scope->canAccessRecord(
            $admin,
            (int) $withdraw->user_id,
            $withdraw->created_by,
            'withdraw'
        )) {
            return $this->legacyOrderStatusResponse(
                ResponseCode::PERMISSION_DENIED,
                __('response.permission_denied'),
                'FAIL',
                'PERMISSION_DENIED',
                'NOCOL'
            );
        }

        if ($legacyUri === 'index/admin/amount/order_status_OTC') {
            $reason = 'OTC payment protocol is unsupported.';

            return $this->legacyOrderStatusResponse(
                ResponseCode::THIRD_PARTY_ERROR,
                __('response.third_party_error'),
                'FAIL',
                'OTCERR',
                [
                    'orderId' => $orderId,
                    'orderStatus' => $orderStatus,
                    'reason' => $reason,
                ],
                [
                    'order_id' => $orderId,
                    'order_status' => (int) $orderStatus,
                    'reason' => $reason,
                ]
            );
        }

        if ($orderStatus === '0') {
            if ((int) $withdraw->status !== 0) {
                return $this->legacyOrderStatusResponse(
                    ResponseCode::DATA_NOT_FOUND,
                    __('admin.withdrawal_not_found_or_invalid'),
                    'FAIL',
                    'UPDATEFAIL',
                    'NOCOL'
                );
            }

            return $this->legacyOrderStatusResponse(
                ResponseCode::SUCCESS,
                __('response.success'),
                'SUC',
                'NOERR',
                'NOCOL'
            );
        }

        $routesByStatus = [
            '1' => 'admin_api_withdrawProcess',
            '2' => 'admin_api_withdrawComplete',
            '3' => 'admin_api_withdrawReject',
        ];
        $routeName = $routesByStatus[$orderStatus] ?? null;

        if ($routeName === null) {
            return $this->legacyOrderStatusResponse(
                ResponseCode::VALIDATION_FAILED,
                __('response.validation_failed'),
                'FAIL',
                'invalidValue',
                'apply_status'
            );
        }

        if ($orderStatus === '3' && $orderRemark === '') {
            return $this->legacyOrderStatusResponse(
                ResponseCode::VALIDATION_FAILED,
                __('response.validation_failed'),
                'FAIL',
                'VALIDATION_FAILED',
                'orderRemark'
            );
        }

        $originalPayload = $request->all();
        $request->replace(array_merge($payload, [
            'id' => $orderId,
            'reason' => $orderRemark,
        ]));

        try {
            $modernResponse = $this->forwardToNamedRoute($request, $routeName, []);
        } finally {
            $request->replace($originalPayload);
        }

        $modernData = method_exists($modernResponse, 'getData')
            ? $modernResponse->getData(true)
            : [];
        $modernCode = (int) ($modernData['code'] ?? ResponseCode::SERVER_ERROR);
        $modernMessage = (string) ($modernData['message'] ?? __('response.server_error'));
        $success = in_array($modernCode, [ResponseCode::SUCCESS, ResponseCode::UPDATED], true);

        return $this->legacyOrderStatusResponse(
            $modernCode,
            $modernMessage,
            $success ? 'SUC' : 'FAIL',
            $success ? 'NOERR' : ($modernCode === ResponseCode::VALIDATION_FAILED ? 'VALIDATION_FAILED' : 'UPDATEFAIL'),
            $success ? 'NOCOL' : 'NOCOL',
            $modernData['data'] ?? []
        );
    }

    private function legacyGenerateOtcOrderResponse(
        int $code,
        string $message,
        string $legacyMsg,
        string $legacyErr,
        $legacyCol,
        array $data = []
    ): Response {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'data' => (object) $data,
            'msg' => $legacyMsg,
            'err' => $legacyErr,
            'col' => $legacyCol,
        ]);
    }

    private function legacyOrderStatusResponse(
        int $code,
        string $message,
        string $legacyMsg,
        string $legacyErr,
        $legacyCol,
        array $data = []
    ): Response {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'data' => (object) $data,
            'msg' => $legacyMsg,
            'err' => $legacyErr,
            'col' => $legacyCol,
        ]);
    }

    /**
     * 兼容旧后台 OTC 临时出金订单详情入口。
     *
     * 参数说明：
     * - recordId 是旧页面传入的出金记录主键，对应项目2 withdraw_records.id。
     * - userId 必须与该出金记录的 user_id 一致，避免把一名用户的银行卡资料拼到另一名用户的 OTC 请求中。
     *
     * 返回说明：
     * - 项目2当前没有已验证的 OTC 出金下单协议，因此只返回兼容旧 Layui 的 OTCERR，不伪造第三方成功 URL。
     * - 该入口只读 withdraw_records 并生成 BRTMP 临时订单号用于解释旧请求意图，不写 local_order_no、third_order_no 或 outbox。
     *
     * @param Request $request 旧后台提交的 HTTP 请求，承载 recordId 与 userId。
     * @return Response 新旧字段并存的 JSON 响应。
     */
    private function forwardLegacyOtcWithdrawOrderIdDetail(Request $request): Response
    {
        $payload = $this->payloadForLegacyTarget($request);
        $rawUserId = $payload['userId'] ?? $payload['user_id'] ?? null;
        $validator = Validator::make(
            [
                'recordId' => $payload['recordId'] ?? null,
                'userId' => $rawUserId,
            ],
            [
                'recordId' => 'required|integer|min:1',
                'userId' => 'required|integer|min:1',
            ]
        );

        if ($validator->fails()) {
            $field = $validator->errors()->has('recordId') ? 'recordId' : 'userId';

            return $this->legacyOtcWithdrawOrderIdDetailResponse(
                ResponseCode::VALIDATION_FAILED,
                $validator->errors()->first(),
                'FAIL',
                'VALIDATION_FAILED',
                $field
            );
        }

        $admin = $request->user('admin') ?: Auth::guard('admin')->user();
        if (!$admin) {
            return $this->legacyOtcWithdrawOrderIdDetailResponse(
                ResponseCode::PERMISSION_DENIED,
                __('response.permission_denied'),
                'FAIL',
                'PERMISSION_DENIED',
                'NOCOL'
            );
        }

        $recordId = (int) $payload['recordId'];
        $userId = (int) $rawUserId;
        $withdraw = WithdrawRecord::query()
            ->whereKey($recordId)
            ->where('user_id', $userId)
            ->first();

        if (!$withdraw) {
            return $this->legacyOtcWithdrawOrderIdDetailResponse(
                ResponseCode::DATA_NOT_FOUND,
                __('admin.withdrawal_not_found_or_invalid'),
                'FAIL',
                'UPDATEFAIL',
                'NOCOL'
            );
        }

        $scope = app(AdminDataScopeService::class);
        if (!$scope->canAccessRecord(
            $admin,
            (int) $withdraw->user_id,
            $withdraw->created_by,
            'withdraw'
        )) {
            return $this->legacyOtcWithdrawOrderIdDetailResponse(
                ResponseCode::PERMISSION_DENIED,
                __('response.permission_denied'),
                'FAIL',
                'PERMISSION_DENIED',
                'NOCOL'
            );
        }

        $temporaryOrderId = 'BRTMP-' . date('YmdHis') . '-WR-' . $userId;
        $legacyCol = [
            'recordId' => $recordId,
            'userId' => $userId,
            'orderId' => $temporaryOrderId,
            'reason' => 'OTC payment protocol is unsupported.',
        ];

        return $this->legacyOtcWithdrawOrderIdDetailResponse(
            ResponseCode::THIRD_PARTY_ERROR,
            __('response.third_party_error'),
            'FAIL',
            'OTCERR',
            $legacyCol,
            [
                'record_id' => $recordId,
                'user_id' => $userId,
                'order_id' => $temporaryOrderId,
                'reason' => $legacyCol['reason'],
            ]
        );
    }

    /**
     * 组装旧 OTC 临时出金订单详情入口的兼容响应。
     *
     * @param int $code 项目2统一业务码。
     * @param string $message 当前语言环境下的响应说明。
     * @param string $legacyMsg 旧字段 msg；FAIL 表示旧页面不能继续打开第三方 URL。
     * @param string $legacyErr 旧字段 err；OTCERR 表示 OTC 下单协议未接入或第三方不可用。
     * @param mixed $legacyCol 旧字段 col；校验失败时为字段名，业务失败时为可解释数组。
     * @param array<string, mixed> $data 现代响应数据，成功或失败均用于解释旧请求链路。
     * @return Response 新旧字段并存的 JSON 响应。
     */
    private function legacyOtcWithdrawOrderIdDetailResponse(
        int $code,
        string $message,
        string $legacyMsg,
        string $legacyErr,
        $legacyCol,
        array $data = []
    ): Response {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'data' => (object) $data,
            'msg' => $legacyMsg,
            'err' => $legacyErr,
            'col' => $legacyCol,
        ]);
    }

    /**
     * 兼容旧后台 11 路支付通道批量配置入口。
     *
     * 参数逻辑说明：
     * - channel_N 表示旧 PAYMENT_CHANNEL_N 是否显示，项目2写入 payment_channels.is_enabled。
     * - channel_N_money 表示旧 para_data1 最低入金金额，项目2写入 config.min_amount。
     * - sort_N 表示旧 para_data6 排序值，项目2写入 payment_channels.sort。
     * - channel_enableV2 额外读取 default_channel，项目2写入 config.is_default；非 V2 入口保持原默认标记不变。
     *
     * @param Request $request 当前旧后台批量通道请求。
     * @param string $legacyUri 当前旧 URI，用于区分是否执行默认通道写入。
     * @return Response 兼容旧 Layui 的 JSON；msg=SUC 表示旧页面可提示更新成功。
     */
    private function forwardLegacyPaymentChannelBatch(Request $request, string $legacyUri): Response
    {
        $withDefault = $legacyUri === 'index/admin/amount/channel_enableV2';
        $validator = Validator::make($request->all(), $this->legacyPaymentChannelRules($withDefault));
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $payload = $request->all();
        $defaultChannel = $withDefault ? (int) $payload['default_channel'] : null;
        $updated = 0;
        $missing = [];

        DB::transaction(function () use ($payload, $withDefault, $defaultChannel, &$updated, &$missing): void {
            for ($index = 1; $index <= 11; $index++) {
                $channel = PaymentChannel::query()
                    ->where('channel_code', (string) $index)
                    ->lockForUpdate()
                    ->first();

                if (!$channel) {
                    $missing[] = $index;
                    continue;
                }

                $config = is_array($channel->config) ? $channel->config : [];
                $config['legacy_para_name'] = $config['legacy_para_name'] ?? ('PAYMENT_CHANNEL_' . $index);
                $config['max_amount'] = $config['max_amount'] ?? $this->legacyPaymentChannelMaxAmount($index);

                $money = trim((string) ($payload['channel_' . $index . '_money'] ?? ''));
                if ($money !== '') {
                    $config['min_amount'] = (float) $money;
                }

                if ($withDefault) {
                    $config['is_default'] = $defaultChannel === $index ? 1 : 0;
                }

                $sort = trim((string) ($payload['sort_' . $index] ?? ''));
                if ($sort !== '') {
                    $channel->sort = (int) $sort;
                }

                $channel->is_enabled = (int) $payload['channel_' . $index];
                $channel->config = $config;
                $channel->updated_at = time();
                $channel->saveOrFail();
                $updated++;
            }
        }, 3);

        if ($updated === 0) {
            return $this->error(__('admin.payment_channel_not_found'), ResponseCode::DATA_NOT_FOUND, [
                'legacy_uri' => $legacyUri,
                'missing' => $missing,
            ]);
        }

        $message = __('admin.legacy_payment_channels_updated');

        return response()->json([
            'code' => ResponseCode::SUCCESS,
            'message' => $message,
            'msg' => 'SUC',
            'err' => 'NOERR',
            'col' => 'NOTCOL',
            'data' => [
                'total' => 11,
                'updated' => $updated,
                'missing' => $missing,
                'default_channel' => $defaultChannel,
            ],
        ]);
    }

    /**
     * 构造旧支付通道批量配置校验规则。
     *
     * @param bool $withDefault 是否要求 default_channel 字段；V2 入口需要。
     * @return array<string, string> Laravel Validator 规则。
     */
    private function legacyPaymentChannelRules(bool $withDefault): array
    {
        $rules = [];
        for ($index = 1; $index <= 11; $index++) {
            $rules['channel_' . $index] = 'required|integer|in:0,1';
            $rules['channel_' . $index . '_money'] = 'nullable|numeric|min:0';
            $rules['sort_' . $index] = 'nullable|integer|min:0|max:999999';
        }

        if ($withDefault) {
            $rules['default_channel'] = 'required|integer|min:1|max:11';
        }

        return $rules;
    }

    /**
     * 返回旧项目每个支付通道的最大金额兜底值。
     *
     * @param int $index 旧 PAYMENT_CHANNEL_N 中的 N。
     * @return int 旧迁移脚本使用的最大入金金额。
     */
    private function legacyPaymentChannelMaxAmount(int $index): int
    {
        $limits = [
            1 => 6800,
            2 => 30000,
            3 => 80000,
            4 => 500000,
            5 => 500000,
            6 => 6800,
            7 => 6800,
            8 => 14000,
            9 => 80000,
            10 => 6800,
            11 => 6800,
        ];

        return $limits[$index] ?? 500000;
    }

    /**
     * 兼容旧后台权益自动确认入口的安全失败响应。
     *
     * 参数说明：
     * - uid、real_amt、amount、sumdata、status、type 是旧自动确认所需字段。
     * - type 只能是 deposit 或 withdraw；旧项目会据此调用 MT4 入金或出金接口。
     *
     * 返回说明：
     * - 字段缺失或非法时返回 VALIDATION_FAILED 与旧字段 errparams。
     * - 字段完整时仍返回 OPERATION_NOT_ALLOWED，因为项目2没有已验证 MT4 自动权益写接口，不能伪造成功。
     *
     * @param Request $request 旧后台自动权益确认请求。
     * @return Response 兼容旧 Layui 的失败 JSON。
     */
    private function forwardLegacyAutomaticRightsConfirmUnavailable(Request $request): Response
    {
        $payload = $this->payloadForLegacyTarget($request);
        $validator = Validator::make(
            [
                'uid' => $payload['uid'] ?? null,
                'real_amt' => $payload['real_amt'] ?? null,
                'amount' => $payload['amount'] ?? null,
                'sumdata' => $payload['sumdata'] ?? null,
                'status' => $payload['status'] ?? null,
                'type' => $payload['type'] ?? null,
            ],
            [
                'uid' => 'required|integer|min:1',
                'real_amt' => 'required|numeric',
                'amount' => 'required|numeric',
                'sumdata' => 'required|string',
                'status' => 'required|integer',
                'type' => 'required|string|in:deposit,withdraw',
            ]
        );

        if ($validator->fails()) {
            $field = (string) array_key_first($validator->errors()->messages());

            return $this->legacyAutomaticRightsConfirmResponse(
                ResponseCode::VALIDATION_FAILED,
                $validator->errors()->first(),
                'FAIL',
                'errparams',
                $field,
                ['reason' => 'Invalid automatic rights confirmation parameters.']
            );
        }

        return $this->legacyAutomaticRightsConfirmResponse(
            ResponseCode::OPERATION_NOT_ALLOWED,
            __('response.operation_not_allowed'),
            'FAIL',
            'erroptions',
            'NOCOL',
            ['reason' => 'MT4 automatic rights confirmation is unsupported.']
        );
    }

    /**
     * 组装旧权益自动确认入口的兼容失败响应。
     *
     * @param int $code 项目2统一业务码。
     * @param string $message 当前语言环境下的响应说明。
     * @param string $legacyMsg 旧字段 msg；FAIL 表示旧页面不能当作确认成功。
     * @param string $legacyErr 旧字段 err；errparams 表示参数失败，erroptions 表示自动确认不可执行。
     * @param string $legacyCol 旧字段 col；参数失败时返回字段名，业务失败时返回 NOCOL。
     * @param array<string, mixed> $data 现代响应数据，用于解释失败原因。
     * @return Response 新旧字段并存的 JSON 响应。
     */
    private function legacyAutomaticRightsConfirmResponse(
        int $code,
        string $message,
        string $legacyMsg,
        string $legacyErr,
        string $legacyCol,
        array $data = []
    ): Response {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'data' => (object) $data,
            'msg' => $legacyMsg,
            'err' => $legacyErr,
            'col' => $legacyCol,
        ]);
    }

    /**
     * 兼容旧权益手动确认入口。
     *
     * 旧 `manual_confirm_options` 只允许落到项目2已验证的人工确认链路：
     * - `settlement_id` 等别名必须解析为 `rights_settlements.id`，不能用 manual_uid 兜底。
     * - `manual_status` 若存在只能为 1，表示把待处理结算确认为已处理。
     * - `manual_reason` 归一化为 `manual_confirm_reason`，写入现代接口的审计备注。
     *
     * @param Request $request 旧后台请求，承载结算主键、状态和人工确认原因。
     * @return Response 现代手动确认接口的 JSON 响应；参数不完整时返回校验失败。
     */
    private function forwardLegacyManualRightsConfirm(Request $request): Response
    {
        $payload = $this->payloadForLegacyTarget($request);
        if (!$request->filled('manual_confirm_reason') && array_key_exists('manual_confirm_reason', $payload)) {
            $request->merge(['manual_confirm_reason' => $payload['manual_confirm_reason']]);
        }

        if (array_key_exists('manual_status', $payload) && (string) $payload['manual_status'] !== '1') {
            return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED, [
                'legacy_uri' => trim($request->path(), '/'),
                'target_route' => 'admin_api_manualConfirmRightsSettlement',
                'invalid_parameter' => 'manual_status',
            ]);
        }

        $defaults = [];
        if (array_key_exists('manual_confirm_reason', $payload)) {
            $defaults['manual_confirm_reason'] = $payload['manual_confirm_reason'];
        }

        return $this->forwardToNamedRoute($request, 'admin_api_manualConfirmRightsSettlement', $defaults);
    }

    /**
     * Complete the legacy customer group-change approval workflow.
     *
     * This endpoint owns trans_apply_logs and the customer's MT4/local group.
     * It must never be forwarded to the unrelated agent-confirmation workflow.
     */
    private function forwardLegacyCustApply(Request $request, string $legacyUri): Response
    {
        $payload = $this->payloadForLegacyTarget($request);
        $uid = $payload['uid'] ?? ($payload['trans_uid'] ?? null);
        $uidValidator = Validator::make(['uid' => $uid], [
            'uid' => 'required|integer|min:1',
        ]);

        if ($uidValidator->fails()) {
            return $this->legacyCustApplyResponse(
                ResponseCode::VALIDATION_FAILED,
                $uidValidator->errors()->first()
            );
        }

        $admin = $request->user('admin') ?: Auth::guard('admin')->user();
        if (!$admin) {
            return $this->legacyCustApplyResponse(ResponseCode::PERMISSION_DENIED);
        }

        $service = app(CustomerGroupChangeApprovalService::class);
        if ($legacyUri === 'index/admin/cust/cust_apply_pass') {
            $result = $service->approve($admin, (int) $uid, $request->ip() ?: '');
        } else {
            $reason = trim((string) ($payload['trans_apply_reason'] ?? ($payload['reason'] ?? '')));
            $reasonValidator = Validator::make(['reason' => $reason], [
                'reason' => 'required|string|max:500',
            ]);
            if ($reasonValidator->fails()) {
                return $this->legacyCustApplyResponse(
                    ResponseCode::VALIDATION_FAILED,
                    $reasonValidator->errors()->first()
                );
            }

            $result = $service->reject($admin, (int) $uid, $reason, $request->ip() ?: '');
        }

        return $this->legacyCustApplyResponse((int) $result['code'], '', $result['data'] ?? []);
    }

    /**
     * 兼容旧 Customer 转组申请列表查询入口。
     *
     * 旧项目两条查询入口读取 trans_apply_log，并分别返回 rows/total（V1）
     * 与 count/data/totalRow（V2）；不能转发到普通用户列表，否则审核状态、
     * 申请原因和余额/持仓列都会丢失。
     */
    private function forwardLegacyCustChangeSearch(Request $request, string $legacyUri): Response
    {
        $admin = $request->user('admin') ?: Auth::guard('admin')->user();
        if (!$admin) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $payload = $this->payloadForLegacyTarget($request);
        $validator = Validator::make($payload, [
            'user_id' => 'nullable|integer|min:1',
            'trans_apply_status' => 'nullable|in:0,1,-1',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
            'page' => 'nullable|integer|min:1',
            'rows' => 'nullable|integer|min:1|max:100',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        if (!empty($payload['start_date']) && !empty($payload['end_date'])
            && $payload['start_date'] > $payload['end_date']) {
            return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
        }

        $result = app(LegacyAdminCustomerChangeSearchService::class)->search($payload, $admin);
        $message = __('admin.user_list_fetched');

        if ($legacyUri === 'index/admin/cust/custChangeListSearchV2') {
            return response()->json([
                'code' => ResponseCode::SUCCESS,
                'message' => $message,
                'msg' => 'SUCCESS',
                'count' => $result['total'],
                'data' => $result['rows'],
                'totalRow' => [],
            ]);
        }

        return response()->json([
            'code' => ResponseCode::SUCCESS,
            'message' => $message,
            'msg' => 'SUCCESS',
            'rows' => $result['rows'],
            'total' => $result['total'],
        ]);
    }

    /**
     * Query the legacy ordinary-customer list without changing the modern user list.
     */
    private function forwardLegacyCustListSearch(Request $request, string $legacyUri): Response
    {
        $admin = $request->user('admin') ?: Auth::guard('admin')->user();
        if (!$admin) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $payload = $this->payloadForLegacyTarget($request);
        if (array_key_exists('user_id', $payload) && is_float($payload['user_id'])) {
            return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
        }

        $validator = Validator::make($payload, [
            'user_id' => ['nullable', 'regex:/^\d+[Xx]?$/'],
            'user_name' => 'nullable|string',
            'userstatus' => 'nullable|in:0,1,2,4',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
            'page' => 'nullable|integer|min:1',
            'rows' => 'nullable|integer|min:1|max:100',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        if (!empty($payload['start_date']) && !empty($payload['end_date'])
            && $payload['start_date'] > $payload['end_date']) {
            return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
        }

        $result = app(LegacyAdminCustomerSearchService::class)->search($payload, $admin);
        $message = __('admin.user_list_fetched');

        if ($legacyUri === 'index/admin/cust/custListSearchV2') {
            return response()->json([
                'code' => ResponseCode::SUCCESS,
                'message' => $message,
                'msg' => 'SUCCESS',
                'count' => $result['total'],
                'data' => $result['rows'],
                'totalRow' => $result['summary'],
            ]);
        }

        return response()->json([
            'code' => ResponseCode::SUCCESS,
            'message' => $message,
            'msg' => 'SUCCESS',
            'rows' => $result['rows'],
            'total' => $result['total'],
            'footer' => [$result['summary']],
        ]);
    }

    /**
     * Return the modern response contract together with the fields consumed by
     * the legacy Blade JavaScript.
     *
     * @param array<string,mixed> $data
     */
    private function legacyCustApplyResponse(int $code, string $message = '', array $data = []): Response
    {
        $legacy = [
            ResponseCode::UPDATED => ['msg' => 'SUCCESS', 'err' => 'NOERR', 'col' => 'UPDATESUC'],
            ResponseCode::VALIDATION_FAILED => ['msg' => 'FAIL', 'err' => 'VALIDATIONFAIL', 'col' => 'NOCOL'],
            ResponseCode::USER_NOT_FOUND => ['msg' => 'FAIL', 'err' => 'NOERR', 'col' => 'INVALIDUSER'],
            ResponseCode::DATA_NOT_FOUND => ['msg' => 'FAIL', 'err' => 'NOERR', 'col' => 'INVALIDUSER'],
            ResponseCode::PERMISSION_DENIED => ['msg' => 'FAIL', 'err' => 'PERMISSIONDENIED', 'col' => 'NOCOL'],
            ResponseCode::THIRD_PARTY_ERROR => ['msg' => 'FAIL', 'err' => 'FATALCANOTCONNECT', 'col' => 'FATALCANOTCONNECT'],
            ResponseCode::RATE_LIMITED => ['msg' => 'FAIL', 'err' => 'BUSY', 'col' => 'NOCOL'],
            ResponseCode::SERVER_ERROR => ['msg' => 'FAIL', 'err' => 'UPDATEFAIL', 'col' => 'UPDATEFAIL'],
        ];
        $legacyFields = $legacy[$code] ?? ['msg' => 'FAIL', 'err' => 'UPDATEFAIL', 'col' => 'UPDATEFAIL'];

        return response()->json(array_merge([
            'code' => $code,
            'message' => $message !== '' ? $message : __(ResponseCode::messageKey($code)),
            'data' => (object) $data,
        ], $legacyFields));
    }

    /**
     * 兼容旧"实名认证/银行卡审核"保存入口。
     *
     * 旧业务说明（项目1 AuthenticationController@user_idcard_bank）：
     * - 旧表单提交 userId、username、userIdcardNo、userbankNo、bank_class_tmp、
     *   bank_info_tmp、idcard_auth、bank_auth、userIdcard_status、userbank_status、
     *   idcard_reason、bank_reason；idcard_auth/bank_auth 为 0 表示审核通过，否则拒绝。
     * - 新项目 admin_api_reviewAuth 以独立组件决定维护 user_auths 的身份证和银行卡状态，
     *   银行卡通过时同步 MT4 备注，最终 user_infos.auth_status 由两项状态共同推导。
     *
     * 参数逻辑说明：
     * - userId 已由 payloadForLegacyTarget 转换为 user_id。
     * - userIdcard_status=1、userbank_status=1/3 的组件参与本次审核；3 是新项目银行卡换绑待审状态。
     * - idcard_auth/bank_auth 为 0 时映射为通过，其他标量值映射为拒绝；两项原因分别转发。
     * - 旧表单的证照号码/银行卡号修改项不在现代审核契约内，属设计收紧，
     *   证照资料应由用户在前台资料页更新后再进入后台审核。
     *
     * @param Request $request 当前旧后台请求。
     * @return Response 现代审核接口的 JSON 响应。
     */
    private function forwardLegacyUserIdCardBank(Request $request): Response
    {
        $payload = $this->payloadForLegacyTarget($request);

        try {
            $componentPayload = AuthReviewTransition::legacyDecisionPayload($payload);
        } catch (\InvalidArgumentException $exception) {
            return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED, [
                'legacy_uri' => trim($request->path(), '/'),
                'reason' => $exception->getMessage(),
            ]);
        }

        $request->merge(array_merge([
            'status' => null,
            'reason' => null,
            'id_card_decision' => null,
            'bank_decision' => null,
            'id_card_reason' => null,
            'bank_reason' => null,
        ], $componentPayload));

        return $this->legacyMutationResponse(
            $this->forwardToNamedRoute($request, 'admin_api_reviewAuth', []),
            'authentication'
        );
    }

    /**
     * 为旧 Blade 补回 msg/err，同时保留现代 code/message/data，避免旧页面把成功响应当成无结果。
     */
    private function legacyMutationResponse(Response $response, string $type): Response
    {
        if (!method_exists($response, 'getData')) {
            return $response;
        }

        $body = $response->getData(true);
        if (!is_array($body)) {
            return $response;
        }

        $code = (int) ($body['code'] ?? ResponseCode::SERVER_ERROR);
        $success = $code === ResponseCode::SUCCESS;
        $body['msg'] = $success ? 'SUC' : 'FAIL';
        $body['err'] = $success
            ? 'NOERR'
            : ($code === ResponseCode::VALIDATION_FAILED ? 'invalidValue' : 'UPDATEFAIL');
        if (!$success && $type === 'authentication' && $code === ResponseCode::PERMISSION_DENIED) {
            $body['err'] = 'PERMISSIONDENIED';
        }

        return response()->json($body, $response->getStatusCode(), $response->headers->all());
    }

    /**
     * 兼容旧"异常 IP 登录明细"入口。
     *
     * 旧业务说明（项目1 FengXianManageController@fengXian_Ipaddress_detail）：
     * - 旧路由以 {idaddr} 传递登录 IP，其中点号在旧 URL 体系中被替换为下划线
     *   （例如 192_168_1_1），并返回该 IP 下登录用户明细列表。
     * - 新项目以 admin_api_riskIpDetail 承接：入参 login_ip + 数据范围过滤，
     *   返回分页的登录明细记录。
     *
     * 参数逻辑说明：
     * - idaddr 表示 URL 中的下划线格式 IP，转发前还原为点分 login_ip。
     * - 校验失败直接返回现代校验错误，避免把畸形 IP 送进明细查询。
     *
     * @param Request $request 当前旧后台请求。
     * @return Response 现代 IP 明细接口的 JSON 响应。
     */
    private function forwardLegacyIpAddressDetail(Request $request): Response
    {
        $idaddr = trim((string) ($request->route('idaddr') ?? ''));

        // 旧 URL 只允许以四段下划线分隔的 IPv4 传参，避免宽松替换把畸形值送入查询。
        if (!preg_match('/^\d{1,3}(?:_\d{1,3}){3}$/D', $idaddr)) {
            return $this->error(__('validation.required', ['attribute' => __('admin.login_ip')]), ResponseCode::VALIDATION_FAILED);
        }

        $loginIp = str_replace('_', '.', $idaddr);

        if ($loginIp === '' || filter_var($loginIp, FILTER_VALIDATE_IP) === false) {
            return $this->error(__('validation.required', ['attribute' => __('admin.login_ip')]), ResponseCode::VALIDATION_FAILED);
        }

        $request->merge(['login_ip' => $loginIp]);

        $response = $this->forwardToNamedRoute($request, 'admin_api_riskIpDetail', []);
        $body = $this->legacyRiskResponseBody($response);
        if ($body === null || (int) ($body['code'] ?? 0) !== ResponseCode::SUCCESS) {
            return $response;
        }

        $records = $body['data']['records'] ?? [];
        $rows = is_array($records['data'] ?? null) ? array_map(
            fn ($row): array => $this->legacyIpDetailRow((array) $row),
            $records['data']
        ) : [];
        $total = (int) ($records['total'] ?? count($rows));

        // 保留现代 data 供已迁移调用方读取，同时把旧 rows/total 放回顶层。
        $body['rows'] = $rows !== [] ? $rows : '';
        $body['total'] = $total;

        return response()->json($body, $response->getStatusCode(), $response->headers->all());
    }

    /**
     * 旧异常 IP 列表专用适配。查询只通过现代 riskIpList，避免复制聚合 SQL。
     */
    private function forwardLegacyIpAddressSearch(Request $request): Response
    {
        $response = $this->forwardToNamedRoute($request, 'admin_api_riskIpList', []);
        $body = $this->legacyRiskResponseBody($response);
        if ($body === null || (int) ($body['code'] ?? 0) !== ResponseCode::SUCCESS) {
            return $response;
        }

        $records = $body['data']['records'] ?? [];
        $rows = is_array($records['data'] ?? null) ? array_map(
            fn ($row): array => $this->legacyIpListRow((array) $row),
            $records['data']
        ) : [];

        return response()->json([
            'rows' => $rows !== [] ? $rows : '',
            'total' => (int) ($records['total'] ?? count($rows)),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function legacyRiskResponseBody(Response $response): ?array
    {
        if (!method_exists($response, 'getData')) {
            return null;
        }

        $body = $response->getData(true);

        return is_array($body) ? $body : null;
    }

    /** @param array<string, mixed> $row */
    private function legacyIpListRow(array $row): array
    {
        return [
            'sys_id' => (int) ($row['id'] ?? 0),
            'login_id' => (int) ($row['sample_user_id'] ?? 0),
            'login_name' => (string) ($row['sample_user_name'] ?? ''),
            'login_ip' => (string) ($row['login_ip'] ?? ''),
            'login_id_desc' => (string) ($row['sample_ip_location'] ?? ''),
            'login_count' => (int) ($row['login_count'] ?? 0),
            'distinct_user_count' => (int) ($row['distinct_user_count'] ?? 0),
            'latest_login_at' => $this->legacyRiskDateTime($row['latest_login_at'] ?? null),
        ];
    }

    /** @param array<string, mixed> $row */
    private function legacyIpDetailRow(array $row): array
    {
        return [
            'sys_id' => (int) ($row['id'] ?? 0),
            'login_id' => (int) ($row['user_id'] ?? 0),
            'login_name' => (string) ($row['user_name'] ?? ''),
            'login_ip' => (string) ($row['login_ip'] ?? ''),
            'login_id_desc' => (string) ($row['login_id_desc'] ?? ''),
            'login_count' => (int) ($row['login_count'] ?? 0),
            'login_date' => $this->legacyRiskDateTime($row['latest_login_at'] ?? null),
            'rec_crt_date' => $this->legacyRiskDateTime($row['registered_at'] ?? null),
            'open' => (int) ($row['open_order_count'] ?? 0),
            'close' => (int) ($row['closed_order_count'] ?? 0),
            'amount_rj' => $this->legacyRiskAmount($row['total_deposit'] ?? null),
            'amount_cj' => $this->legacyRiskAmount($row['total_withdraw'] ?? null),
        ];
    }

    private function legacyRiskDateTime($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $timestamp = is_numeric($value) ? (int) $value : strtotime((string) $value);

        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : '';
    }

    private function legacyRiskAmount($value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        return is_string($value) ? $value : (string) $value;
    }

    /**
     * 兼容旧后台代理编辑保存入口。
     *
     * 业务逻辑说明：
     * - 旧项目 agents_edit_save 同时修改本地资料、代理等级、返佣、出入金开关，并尝试同步 MT4 组别、杠杆、只读、启停、密码和注销状态。
     * - 项目2当前只对本地资料字段具备可验证闭环；凡是会改变真实交易端状态的字段都先返回旧格式失败，避免本地库与 MT4 分叉。
     * - 成功路径只写 user_infos、user_logins、user_auths 和 operation_logs，返回 msg/err/col 兼容旧 Blade 判断。
     *
     * @param Request $request 旧 Blade 代理编辑表单请求，主要字段位于 data 嵌套对象和根级开关字段。
     * @return Response 旧格式与项目2 code/message 并存的 JSON 响应。
     */
    private function forwardLegacyAgentEditSave(Request $request): Response
    {
        $payload = $this->legacyAgentEditSavePayload($request);
        $userId = $payload['userId'] ?? ($payload['user_id'] ?? null);
        $validator = Validator::make(['userId' => $userId], [
            'userId' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->legacyAgentEditSaveResponse(
                ResponseCode::VALIDATION_FAILED,
                'FAIL',
                'errparams',
                'userId'
            );
        }

        $userId = (int) $userId;
        $agent = UserInfo::query()
            ->where('user_id', $userId)
            ->where('account_type', 1)
            ->first();
        if (!$agent) {
            return $this->legacyAgentEditSaveResponse(
                ResponseCode::USER_NOT_FOUND,
                'FAIL',
                'USERNOTFOUND',
                'userId'
            );
        }

        $admin = $request->user('admin') ?: Auth::guard('admin')->user();
        if ($admin && !app(AdminDataScopeService::class)->canAccessUser($admin, $userId, 'agent')) {
            return $this->legacyAgentEditSaveResponse(
                ResponseCode::PERMISSION_DENIED,
                'FAIL',
                'PERMISSIONDENIED',
                'NOCOL'
            );
        }

        if ($blocked = $this->legacyAgentEditSaveSensitiveChangeResponse($agent, $payload)) {
            return $blocked;
        }

        $normalized = $this->normalizedLegacyAgentEditSavePayload($payload);
        if ($validationResponse = $this->validateNormalizedLegacyAgentEditSavePayload($normalized, $userId)) {
            return $validationResponse;
        }

        try {
            DB::transaction(function () use ($admin, $request, $userId, $normalized) {
                $lockedAgent = UserInfo::query()
                    ->where('user_id', $userId)
                    ->where('account_type', 1)
                    ->lockForUpdate()
                    ->firstOrFail();

                $login = UserLogin::query()->where('user_id', $userId)->lockForUpdate()->first();
                if (!$login) {
                    throw new \RuntimeException('legacy agent edit save missing user login.');
                }

                $auth = UserAuth::query()->where('user_id', $userId)->lockForUpdate()->first();
                if (!$auth) {
                    $auth = new UserAuth(['user_id' => $userId]);
                }

                $infoUpdates = $this->legacyAgentEditSaveInfoUpdates($normalized, $lockedAgent);
                $loginUpdates = $this->legacyAgentEditSaveLoginUpdates($normalized);
                $authUpdates = $this->legacyAgentEditSaveAuthUpdates($normalized);
                $content = $this->legacyAgentEditSaveAuditContent(
                    $lockedAgent,
                    $infoUpdates,
                    $login,
                    $loginUpdates,
                    $auth,
                    $authUpdates
                );

                if (!empty($infoUpdates)) {
                    $lockedAgent->update($infoUpdates + ['updated_by' => $admin ? (int) $admin->id : 0]);
                }
                if (!empty($loginUpdates)) {
                    $login->update($loginUpdates);
                }
                if (!empty($authUpdates)) {
                    $auth->fill($authUpdates + ['user_id' => $userId]);
                    $auth->save();
                }

                OperationLog::create([
                    'admin_id' => $admin ? (int) $admin->id : 0,
                    'admin_name' => $admin ? (string) $admin->username : '',
                    'target_user_id' => $userId,
                    'order_no' => 'legacy_agent_edit_save:' . $userId,
                    'content' => $content,
                    'ip' => $request->ip() ?: '',
                    'action_type' => 0,
                ]);
            });
        } catch (\Throwable $exception) {
            return $this->legacyAgentEditSaveResponse(
                ResponseCode::SERVER_ERROR,
                'FAIL',
                'INFOUPDATEFAIL',
                'NOCOL',
                ['reason' => 'Legacy agent edit local transaction failed.']
            );
        }

        return $this->legacyAgentEditSaveResponse(
            ResponseCode::UPDATED,
            'SUC',
            'NOERR',
            'NOCOL',
            UserInfo::query()->where('user_id', $userId)->first()->toArray()
        );
    }

    /**
     * 归一化旧代理编辑请求的根级与 data 嵌套字段。
     *
     * @param Request $request 当前旧后台请求。
     * @return array<string, mixed> 已展开的旧字段集合，根级字段优先级高于 data 内同名字段。
     */
    private function legacyAgentEditSavePayload(Request $request): array
    {
        $payload = $request->all();
        $nested = $request->input('data');
        if (is_array($nested)) {
            unset($payload['data']);
            $payload = array_replace($nested, $payload);
        }

        if (!array_key_exists('phone', $payload) && array_key_exists('userphoneNo', $payload)) {
            $phoneNo = trim((string) $payload['userphoneNo']);
            $payload['phone'] = $phoneNo === ''
                ? ''
                : trim((string) ($payload['modules'] ?? '86')) . '-' . $phoneNo;
        }

        return $payload;
    }

    /**
     * 对 MT4/层级敏感字段执行失败关闭判断。
     *
     * @param UserInfo $agent 当前代理资料快照。
     * @param array<string, mixed> $payload 旧字段请求体。
     * @return Response|null 返回 Response 表示必须拒绝，返回 null 表示可继续本地资料更新。
     */
    private function legacyAgentEditSaveSensitiveChangeResponse(UserInfo $agent, array $payload): ?Response
    {
        if (trim((string) ($payload['cancel_activity'] ?? '')) !== '') {
            return $this->legacyAgentEditSaveMt4UnsupportedResponse('cancel_activity');
        }

        $password = trim((string) ($payload['password'] ?? ''));
        if ($password !== '' && $password !== '********') {
            return $this->legacyAgentEditSaveMt4UnsupportedResponse('password');
        }

        if (array_key_exists('usergrpName', $payload)) {
            $targetGroup = trim((string) $payload['usergrpName']);
            if ($targetGroup !== '' && $targetGroup !== (string) $agent->mt4_group) {
                return $this->legacyAgentEditSaveMt4UnsupportedResponse('mt4_group');
            }
        }

        $targetLeverage = $this->legacyAgentEditTargetLeverage($payload);
        if ($targetLeverage !== null && $targetLeverage !== (int) $agent->leverage) {
            return $this->legacyAgentEditSaveMt4UnsupportedResponse('cust_lvg');
        }

        if (array_key_exists('userparentId', $payload) && (int) $payload['userparentId'] !== (int) $agent->parent_id) {
            return $this->legacyAgentEditSaveMt4UnsupportedResponse('userparentId');
        }

        // 结算方式（旧 settlement_model / 新 user_infos.settle_method）不允许在此变更。
        // 旧项目改这个字段时带一道硬校验：顶层代理（parent_id=0）改结算方式前，
        // 必须确认直属客户没有未平仓订单，有则拒绝。那道校验尚未移植，
        // 直接落库等于绕开风控；而静默丢弃又会让操作人以为改成功了。
        // 因此显式拒绝，并如实说明原因。提交与当前值相同时不算变更，放行。
        $targetSettleMethod = $this->legacyAgentEditTargetSettleMethod($payload);
        if ($targetSettleMethod !== null && $targetSettleMethod !== (int) $agent->settle_method) {
            return $this->legacyAgentEditSaveSettleMethodUnsupportedResponse();
        }

        if (array_key_exists('enable', $payload) && (int) $payload['enable'] !== (int) $agent->is_mt4_enabled) {
            return $this->legacyAgentEditSaveMt4UnsupportedResponse('enable');
        }

        if (array_key_exists('enablereadonly', $payload) && (int) $payload['enablereadonly'] !== (int) $agent->is_mt4_readonly) {
            return $this->legacyAgentEditSaveMt4UnsupportedResponse('enablereadonly');
        }

        if ($this->legacyAgentEditBankSnapshotRequested($payload)) {
            $bankStatus = (int) UserAuth::query()->where('user_id', (int) $agent->user_id)->value('bank_status');
            if ($bankStatus === 2) {
                return $this->legacyAgentEditSaveMt4UnsupportedResponse('bank_no');
            }
        }

        return null;
    }

    /**
     * 计算旧代理编辑请求目标结算方式。
     *
     * 旧页面把该字段读作 settlementmodel（agents.settlement_model 的查询别名），
     * 提交侧历史上出现过 settlement_model 写法，两者都接。
     * 取值域与旧项目一致：1=线上、2=线下；其余值视为未提交，交由后续校验拒绝。
     *
     * @param array<string, mixed> $payload 旧字段请求体。
     * @return int|null null 表示未提交结算方式字段。
     */
    private function legacyAgentEditTargetSettleMethod(array $payload): ?int
    {
        foreach (['settlementmodel', 'settlement_model'] as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }
            $raw = trim((string) $payload[$key]);
            if ($raw === '') {
                continue;
            }

            return (int) $raw;
        }

        return null;
    }

    /**
     * 返回旧代理编辑「结算方式不支持变更」失败关闭响应。
     *
     * 为什么单独一个响应而不复用 MT4 那个：
     * - 拒绝原因不是 MT4 同步，写成 MT4SYNCUNSUPPORTED 属于谎报原因，会把排查带偏。
     * - 也不能照抄旧项目的 directExistOrder：那个含义是「直属客户尚有持仓」，
     *   而本方法并未做持仓检查，用它同样是谎报。
     *
     * @return Response 旧格式失败 JSON。
     */
    private function legacyAgentEditSaveSettleMethodUnsupportedResponse(): Response
    {
        return $this->legacyAgentEditSaveResponse(
            ResponseCode::OPERATION_NOT_ALLOWED,
            'FAIL',
            'SETTLEMETHODUNSUPPORTED',
            'settlement_model',
            ['reason' => 'Changing settle method requires the legacy direct-customer open-order guard, which is not ported yet.']
        );
    }

    /**
     * 计算旧代理编辑请求目标杠杆。
     *
     * @param array<string, mixed> $payload 旧字段请求体。
     * @return int|null null 表示未提交杠杆相关字段。
     */
    private function legacyAgentEditTargetLeverage(array $payload): ?int
    {
        if (array_key_exists('cust_lvg', $payload) && trim((string) $payload['cust_lvg']) !== '') {
            return (int) $payload['cust_lvg'];
        }

        if (array_key_exists('is_enc', $payload) && trim((string) $payload['is_enc']) !== '') {
            return (int) $payload['is_enc'] === 1 ? 200 : 100;
        }

        return null;
    }

    /**
     * 判断旧请求是否提交了已审核银行卡快照字段。
     *
     * @param array<string, mixed> $payload 旧字段请求体。
     * @return bool true=提交了 bank_no、bank_class 或 bank_info 中任一字段。
     */
    private function legacyAgentEditBankSnapshotRequested(array $payload): bool
    {
        return array_key_exists('bank_no', $payload)
            || array_key_exists('bank_class', $payload)
            || array_key_exists('bank_info', $payload);
    }

    /**
     * 归一化可本地写入的旧代理编辑字段。
     *
     * @param array<string, mixed> $payload 旧字段请求体。
     * @return array<string, mixed> 新项目字段名和值。
     */
    private function normalizedLegacyAgentEditSavePayload(array $payload): array
    {
        $normalized = [];
        $this->copyLegacyAgentEditValue($normalized, $payload, 'user_name', ['username']);
        $this->copyLegacyAgentEditValue($normalized, $payload, 'email', ['useremail', 'email']);
        $this->copyLegacyAgentEditValue($normalized, $payload, 'phone', ['phone']);
        $this->copyLegacyAgentEditValue($normalized, $payload, 'id_card_no', ['userIdcardNo', 'id_card_no']);
        $this->copyLegacyAgentEditValue($normalized, $payload, 'level_id', ['useragtId']);
        $this->copyLegacyAgentEditValue($normalized, $payload, 'comm_rate', ['userrebate']);
        $this->copyLegacyAgentEditValue($normalized, $payload, 'is_withdrawal_allowed', ['isoutmoney']);
        $this->copyLegacyAgentEditValue($normalized, $payload, 'is_deposit_allowed', ['isallowmoney']);
        $this->copyLegacyAgentEditValue($normalized, $payload, 'trading_mode', ['usertype']);
        $this->copyLegacyAgentEditValue($normalized, $payload, 'equity_ratio', ['userrights']);
        $this->copyLegacyAgentEditValue($normalized, $payload, 'settle_cycle', ['datausercycle', 'usercycle']);
        $this->copyLegacyAgentEditValue($normalized, $payload, 'is_gift_allowed', ['gift_allowed']);
        $this->copyLegacyAgentEditValue($normalized, $payload, 'remark', ['userremark']);

        if (array_key_exists('sex', $payload)) {
            $normalized['gender'] = $this->normalizeLegacyAgentEditGender($payload['sex']);
        }

        if (array_key_exists('email', $normalized)) {
            $normalized['email'] = strtolower(trim((string) $normalized['email']));
        }

        return $normalized;
    }

    /**
     * 从旧字段候选列表中复制第一个存在的值。
     *
     * @param array<string, mixed> $normalized 归一化目标数组。
     * @param array<string, mixed> $payload 原始旧字段数组。
     * @param string $target 新项目字段名。
     * @param array<int, string> $candidates 旧字段候选名。
     * @return void
     */
    private function copyLegacyAgentEditValue(array &$normalized, array $payload, string $target, array $candidates): void
    {
        foreach ($candidates as $candidate) {
            if (!array_key_exists($candidate, $payload)) {
                continue;
            }

            $normalized[$target] = is_string($payload[$candidate])
                ? trim($payload[$candidate])
                : $payload[$candidate];
            return;
        }
    }

    /**
     * 将旧 sex 字段归一成 user_infos.gender。
     *
     * @param mixed $value 旧字段 sex，支持 1/2、男/女、male/female。
     * @return mixed 1=男，2=女；无法识别时保留原值交给 Validator 返回失败。
     */
    private function normalizeLegacyAgentEditGender($value)
    {
        $gender = is_string($value) ? trim($value) : $value;
        $lowerGender = strtolower((string) $gender);
        if ($gender === '男' || $lowerGender === 'male' || $lowerGender === 'm' || (string) $gender === '1') {
            return 1;
        }
        if ($gender === '女' || $lowerGender === 'female' || $lowerGender === 'f' || (string) $gender === '2') {
            return 2;
        }

        return $gender;
    }

    /**
     * 校验旧代理编辑本地字段及唯一性。
     *
     * @param array<string, mixed> $normalized 归一化后的字段。
     * @param int $userId 当前代理业务用户 ID。
     * @return Response|null 返回 Response 表示校验失败。
     */
    private function validateNormalizedLegacyAgentEditSavePayload(array $normalized, int $userId): ?Response
    {
        $validator = Validator::make($normalized, [
            'user_name' => 'sometimes|nullable|string|max:200',
            'email' => 'sometimes|nullable|email|max:191',
            'phone' => 'sometimes|nullable|string|max:50',
            'id_card_no' => 'sometimes|nullable|string|max:50',
            'level_id' => 'sometimes|nullable|integer|min:0',
            'comm_rate' => 'sometimes|nullable|numeric|min:0|max:100',
            'is_withdrawal_allowed' => 'sometimes|in:0,1',
            'is_deposit_allowed' => 'sometimes|in:0,1',
            'trading_mode' => 'sometimes|in:0,1',
            'equity_ratio' => 'sometimes|nullable|numeric|min:0',
            'settle_cycle' => 'sometimes|nullable|integer|in:0,1,2,3',
            'is_gift_allowed' => 'sometimes|in:0,1',
            'gender' => 'sometimes|in:1,2',
            'remark' => 'sometimes|nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            $field = (string) array_key_first($validator->errors()->messages());

            return $this->legacyAgentEditSaveResponse(
                ResponseCode::VALIDATION_FAILED,
                'FAIL',
                'errparams',
                $field
            );
        }

        if (array_key_exists('level_id', $normalized)
            && (int) $normalized['level_id'] > 0
            && !DB::table('agent_levels')->where('id', (int) $normalized['level_id'])->exists()
        ) {
            return $this->legacyAgentEditSaveResponse(ResponseCode::VALIDATION_FAILED, 'FAIL', 'err_gid', 'useragtId');
        }

        if (array_key_exists('email', $normalized)
            && $normalized['email'] !== ''
            && UserLogin::query()->where('email', (string) $normalized['email'])->where('user_id', '!=', $userId)->exists()
        ) {
            return $this->legacyAgentEditSaveResponse(ResponseCode::VALIDATION_FAILED, 'FAIL', 'Existemail', 'useremail');
        }

        if (array_key_exists('phone', $normalized)
            && $normalized['phone'] !== ''
            && UserInfo::query()->where('phone', (string) $normalized['phone'])->where('user_id', '!=', $userId)->exists()
        ) {
            return $this->legacyAgentEditSaveResponse(ResponseCode::VALIDATION_FAILED, 'FAIL', 'Existphone', 'userphoneNo');
        }

        if (array_key_exists('id_card_no', $normalized)
            && $normalized['id_card_no'] !== ''
            && UserAuth::query()->where('id_card_no', (string) $normalized['id_card_no'])->where('user_id', '!=', $userId)->exists()
        ) {
            return $this->legacyAgentEditSaveResponse(ResponseCode::VALIDATION_FAILED, 'FAIL', 'Existidcard', 'userIdcardNo');
        }

        if ((int) ($normalized['trading_mode'] ?? 0) === 1
            && array_key_exists('settle_cycle', $normalized)
            && !in_array((int) $normalized['settle_cycle'], [1, 2, 3], true)
        ) {
            return $this->legacyAgentEditSaveResponse(ResponseCode::VALIDATION_FAILED, 'FAIL', 'INVALIDCYCLE', 'usercycle');
        }

        return null;
    }

    /**
     * 生成 user_infos 可写字段。
     *
     * @param array<string, mixed> $normalized 归一化后的字段。
     * @param UserInfo $agent 当前代理资料。
     * @return array<string, mixed> 可直接写入 user_infos 的字段。
     */
    private function legacyAgentEditSaveInfoUpdates(array $normalized, UserInfo $agent): array
    {
        $updates = [];
        foreach (['user_name', 'phone', 'remark'] as $field) {
            if (array_key_exists($field, $normalized)) {
                $updates[$field] = (string) $normalized[$field];
            }
        }

        foreach (['level_id', 'is_withdrawal_allowed', 'is_deposit_allowed', 'trading_mode', 'settle_cycle', 'is_gift_allowed', 'gender'] as $field) {
            if (array_key_exists($field, $normalized)) {
                $updates[$field] = (int) $normalized[$field];
            }
        }

        foreach (['comm_rate', 'equity_ratio'] as $field) {
            if (array_key_exists($field, $normalized)) {
                $updates[$field] = $normalized[$field] + 0;
            }
        }

        if (array_key_exists('trading_mode', $normalized) && (int) $normalized['trading_mode'] !== 1) {
            $updates['settle_cycle'] = 0;
            $updates['equity_ratio'] = 0;
        } elseif ((int) ($normalized['trading_mode'] ?? $agent->trading_mode) === 1) {
            if (array_key_exists('settle_cycle', $normalized)) {
                $updates['settle_cycle'] = (int) $normalized['settle_cycle'];
            }
            if (array_key_exists('equity_ratio', $normalized)) {
                $updates['equity_ratio'] = $normalized['equity_ratio'] + 0;
            }
        }

        return $updates;
    }

    /**
     * 生成 user_logins 可写字段。
     *
     * @param array<string, mixed> $normalized 归一化后的字段。
     * @return array<string, mixed> 可直接写入 user_logins 的字段。
     */
    private function legacyAgentEditSaveLoginUpdates(array $normalized): array
    {
        return array_key_exists('email', $normalized)
            ? ['email' => (string) $normalized['email']]
            : [];
    }

    /**
     * 生成 user_auths 可写字段。
     *
     * @param array<string, mixed> $normalized 归一化后的字段。
     * @return array<string, mixed> 可直接写入 user_auths 的字段。
     */
    private function legacyAgentEditSaveAuthUpdates(array $normalized): array
    {
        return array_key_exists('id_card_no', $normalized)
            ? ['id_card_no' => (string) $normalized['id_card_no']]
            : [];
    }

    /**
     * 生成代理编辑审计日志内容。
     *
     * @param UserInfo $agent 更新前代理资料。
     * @param array<string, mixed> $infoUpdates user_infos 更新字段。
     * @param UserLogin $login 更新前登录资料。
     * @param array<string, mixed> $loginUpdates user_logins 更新字段。
     * @param UserAuth $auth 更新前认证资料。
     * @param array<string, mixed> $authUpdates user_auths 更新字段。
     * @return string operation_logs.content 内容。
     */
    private function legacyAgentEditSaveAuditContent(
        UserInfo $agent,
        array $infoUpdates,
        UserLogin $login,
        array $loginUpdates,
        UserAuth $auth,
        array $authUpdates
    ): string {
        $changes = [];
        foreach ($infoUpdates as $field => $newValue) {
            if ($field === 'updated_by') {
                continue;
            }
            $oldValue = (string) $agent->{$field};
            $newValue = (string) $newValue;
            if ($oldValue !== $newValue) {
                $changes[] = $field . ':' . $oldValue . '->' . $newValue;
            }
        }

        foreach ($loginUpdates as $field => $newValue) {
            $oldValue = (string) $login->{$field};
            $newValue = (string) $newValue;
            if ($oldValue !== $newValue) {
                $changes[] = 'login.' . $field . ':' . $oldValue . '->' . $newValue;
            }
        }

        foreach ($authUpdates as $field => $newValue) {
            $oldValue = (string) $auth->{$field};
            $newValue = (string) $newValue;
            if ($oldValue === $newValue) {
                continue;
            }
            $changes[] = $field === 'id_card_no'
                ? 'auth.id_card_no:changed'
                : 'auth.' . $field . ':' . $oldValue . '->' . $newValue;
        }

        return 'Legacy agent edit save user_id:' . (int) $agent->user_id
            . '; changes:' . (empty($changes) ? 'no_changes' : implode('; ', $changes));
    }

    /**
     * 返回旧代理编辑 MT4 敏感字段失败关闭响应。
     *
     * @param string $legacyCol 旧页面应高亮或识别的字段。
     * @return Response 旧格式失败 JSON。
     */
    private function legacyAgentEditSaveMt4UnsupportedResponse(string $legacyCol): Response
    {
        return $this->legacyAgentEditSaveResponse(
            ResponseCode::OPERATION_NOT_ALLOWED,
            'FAIL',
            'MT4SYNCUNSUPPORTED',
            $legacyCol,
            ['reason' => 'Legacy agent edit requires MT4 synchronization that is not safely available.']
        );
    }

    /**
     * 组装旧代理编辑保存响应。
     *
     * @param int $code 项目2统一业务码。
     * @param string $legacyMsg 旧字段 msg，SUC=成功，FAIL=失败。
     * @param string $legacyErr 旧字段 err，NOERR=无错误，其余为旧页面业务错误码。
     * @param string $legacyCol 旧字段 col，表示失败字段或 NOCOL。
     * @param array<string, mixed> $data 现代响应 data。
     * @return Response 新旧字段并存的 JSON 响应。
     */
    private function legacyAgentEditSaveResponse(
        int $code,
        string $legacyMsg,
        string $legacyErr,
        string $legacyCol,
        array $data = []
    ): Response {
        return response()->json([
            'code' => $code,
            'message' => __(ResponseCode::messageKey($code)),
            'data' => (object) $data,
            'msg' => $legacyMsg,
            'err' => $legacyErr,
            'col' => $legacyCol,
        ]);
    }

    /**
     * 旧请求依赖新项目不存在的批量配置/MT4 资金语义时显式终止。
     *
     * 返回 410 表示入口仍被识别但没有安全等价实现，避免误调用单条 toggle 或本地假结算；
     * 响应附带 legacy_uri 与 target_route 便于排查旧前端请求。
     *
     * @param string $legacyUri 当前旧后台 URI。
     * @param string $targetRoute 现代目标路由名（不执行，仅用于说明）。
     * @return Response 410 失败 JSON。
     */
    private function legacyMutationUnavailable(string $legacyUri, string $targetRoute): Response
    {
        return response()->json([
            'code' => ResponseCode::OPERATION_NOT_ALLOWED,
            'message' => __('response.operation_not_allowed'),
            'data' => [
                'legacy_uri' => $legacyUri,
                'target_route' => $targetRoute,
                'reason' => 'legacy_payload_has_no_safe_modern_equivalent',
            ],
        ], 410);
    }

    /**
     * Identify modern targets whose execution can change state or write a file.
     * Read-only list/detail/export targets intentionally remain compatible with
     * historical GET links and are forwarded through the existing adapter.
     */
    private function isMutationTargetRoute(string $routeName): bool
    {
        if (in_array($routeName, [
            'admin_api_whsExpZero',
            'admin_api_confirmAgent',
            'admin_api_reviewAuth',
            'admin_api_commissionTransferReconcile',
            'admin_api_forceOfflineUser',
        ], true)) {
            return true;
        }

        return preg_match(
            '/(?:create|update|delete|destroy|change|reset|assign|save|approve|reject|process|complete|settle|retry|sync|toggle|send|forceClose|manualConfirm|upload)/',
            $routeName
        ) === 1;
    }

    /**
     * 生成旧后台登录页图形验证码。
     *
     * 复用旧项目同一验证码组件：渲染 PNG 并同时写入 captcha Session 与一次性 Cache，
     * 保证旧登录页与旧 /index/admin/logon 提交链路能校验同一份验证码。
     *
     * @param Request $request 当前旧后台请求。
     * @return Response 验证码 PNG 图片响应。
     */
    private function captcha(Request $request): Response
    {
        // 复用旧项目同一验证码组件：生成 PNG，同时写入 captcha Session 和一次性 Cache。
        return app('captcha')->create('custom_captcha');
    }

    /**
     * 退出旧后台会话。
     *
     * 清理 admin guard 会话并使 Session 失效；请求带 Bearer token 时继续转发到现代登出接口
     * 让 JWT 进入黑名单，否则重定向回后台登录页。
     *
     * @param Request $request 当前旧后台请求。
     * @return Response 现代登出 JSON 或登录页重定向。
     */
    private function logout(Request $request): Response
    {
        $hasBearerToken = $request->bearerToken() !== null;

        Auth::guard('admin')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($hasBearerToken) {
            return $this->forwardToNamedRoute($request, 'admin_api_logout', []);
        }

        return redirect()->route('admin_page_login');
    }

    /**
     * 渲染旧 URI 对应的 Blade 页面。
     *
     * 先按旧 URI 解析视图名与页面数据（路由占位参数、默认出金状态），
     * 再交给 Laravel 视图响应，使旧地址直接打开现代 Layui 页面。
     *
     * @param string $legacyUri 当前旧后台 URI。
     * @param Request $request 当前旧后台请求，读取路由占位参数。
     * @return Response Blade 页面视图响应。
     */
    private function renderLegacyPage(string $legacyUri, Request $request): Response
    {
        $view = $this->pageViewFor($legacyUri);
        $data = $this->pageDataFor($legacyUri, $request);

        return response()->view($view, $data);
    }

    /**
     * 渲染旧出金详情页。非法、不存在或超出管理员数据范围的记录统一返回 404，
     * 避免通过连续主键探测其他业务用户的资金快照。
     */
    private function renderLegacyWithdrawDetail(Request $request): Response
    {
        $rawOrderId = $request->route('orderId');
        abort_unless(is_string($rawOrderId) || is_int($rawOrderId), 404);
        $rawOrderId = (string) $rawOrderId;
        abort_unless((bool) preg_match('/^[1-9]\d*$/D', $rawOrderId), 404);

        $withdraw = WithdrawRecord::query()
            ->with('user')
            ->whereKey((int) $rawOrderId)
            ->first();
        abort_unless($withdraw, 404);

        $admin = $request->user('admin') ?: Auth::guard('admin')->user();
        abort_unless(
            $admin && app(AdminDataScopeService::class)->canAccessRecord(
                $admin,
                (int) $withdraw->user_id,
                $withdraw->created_by,
                'withdraw'
            ),
            404
        );

        return response()->view('admin_layui::withdrawals.detail', [
            'withdraw' => $withdraw,
        ]);
    }

    /**
     * 解析旧 URI 对应的 Blade 视图名。
     *
     * 登录、个人资料、改密和出金页有专用视图，其余按 pageModuleFor() 的模块映射
     * 落到 admin_layui::<module>.index。
     *
     * @param string $legacyUri 当前旧后台 URI。
     * @return string 视图名，形如 admin_layui::users.index。
     */
    private function pageViewFor(string $legacyUri): string
    {
        if ($legacyUri === 'index/admin/login' || $legacyUri === 'index/admin/captcha') {
            return 'admin_layui::auth.login';
        }

        if ($legacyUri === 'index/admin/userinfo') {
            return 'admin_layui::profile.edit';
        }

        if ($legacyUri === 'index/admin/userpwd') {
            return 'admin_layui::profile.change-password';
        }

        if (in_array($legacyUri, [
            'index/admin/auth/user_certified_detail/{uid}',
            'index/admin/auth/user_examine/detail/{mode}/{uid}',
        ], true)) {
            return 'admin_layui::authentications.detail';
        }

        if ($legacyUri === 'index/admin/auth/user_voucher/detail/{recId}/{uid}') {
            return 'admin_layui::vouchers.detail';
        }

        if ($legacyUri === 'index/admin/customer/{user_id?}') {
            return 'admin_layui::users.direct-customers';
        }

        if ($legacyUri === 'index/admin/cust/add') {
            return 'admin_layui::users.customer-add';
        }

        if ($legacyUri === 'index/admin/cust/cust_detail/{acc_uid}') {
            return 'admin_layui::users.customer-detail';
        }

        if (strpos($legacyUri, 'index/admin/withdraw/') === 0) {
            return 'admin_layui::withdrawals.index';
        }

        $module = $this->pageModuleFor($legacyUri);

        return 'admin_layui::' . $module . '.index';
    }

    /**
     * 收集旧页面渲染需要的额外数据。
     *
     * 从路由占位参数中提取 id/uid/user_id 等主键字段，并为出金列表页注入默认状态筛选，
     * 保证旧链接打开页面时保持原来的查询语境。
     *
     * @param string $legacyUri 当前旧后台 URI。
     * @param Request $request 当前旧后台请求，读取路由占位参数。
     * @return array<string, mixed> 传给 Blade 视图的页面数据。
     */
    private function pageDataFor(string $legacyUri, Request $request): array
    {
        $data = [];

        $newsModes = [
            'index/admin/news/news_list_browse' => 'list',
            'index/admin/news/news_add_browse' => 'create',
            'index/admin/news/news_edit/{newsid}' => 'edit',
        ];
        if (isset($newsModes[$legacyUri])) {
            $data['newsMode'] = $newsModes[$legacyUri];
        }

        $giftModes = [
            'index/admin/gift/send_gift_browse' => 'send',
            'index/admin/gift/shipment_list_browse' => 'shipments',
        ];
        if (isset($giftModes[$legacyUri])) {
            $data['giftPageMode'] = $giftModes[$legacyUri];
        }

        if ($legacyUri === 'index/admin/news/news_edit/{newsid}') {
            $rawNewsId = $request->route('newsid');
            abort_unless(
                (is_string($rawNewsId) || is_int($rawNewsId))
                    && (bool) preg_match('/^[1-9]\d*$/D', (string) $rawNewsId),
                404
            );
            $data['newsInfo'] = app(AdminNewsQueryService::class)
                ->editableFields((int) $rawNewsId);
        }

        foreach (['id', 'uid', 'user_id', 'acc_uid', 'recId', 'newsid', 'orderId'] as $key) {
            $value = $request->route($key);
            if ($value !== null) {
                $data[$key] = $value;
            }
        }

        $withdrawStatus = [
            'index/admin/withdraw/pending' => '0',
            'index/admin/withdraw/processing' => '1',
            'index/admin/withdraw/completed' => '2',
            'index/admin/withdraw/failed' => '3',
        ];

        if (isset($withdrawStatus[$legacyUri])) {
            $data['defaultStatus'] = $withdrawStatus[$legacyUri];
        }

        $riskModes = [
            'index/admin/fengXian/profit_list' => 'profit',
            'index/admin/fengXian/position_list' => 'positions',
            'index/admin/fengXian/Ipaddress_list' => 'ipRisk',
        ];
        if (isset($riskModes[$legacyUri])) {
            $data['defaultRiskMode'] = $riskModes[$legacyUri];
        }

        if ($legacyUri === 'index/admin/auth/user_certified_detail/{uid}') {
            $uid = (string) $request->route('uid');
            abort_unless((bool) preg_match('/^[1-9]\d*$/', $uid), 404);
            $data['authUserId'] = (int) $uid;
            $data['authMode'] = 'show';
        }

        if ($legacyUri === 'index/admin/auth/user_examine/detail/{mode}/{uid}') {
            $mode = (string) $request->route('mode');
            $uid = (string) $request->route('uid');
            abort_unless(in_array($mode, ['auth', 'show'], true), 404);
            abort_unless((bool) preg_match('/^[1-9]\d*$/', $uid), 404);
            $data['authUserId'] = (int) $uid;
            $data['authMode'] = $mode;
        }

        if ($legacyUri === 'index/admin/auth/user_voucher/detail/{recId}/{uid}') {
            $recordId = (string) $request->route('recId');
            $userId = (string) $request->route('uid');
            abort_unless((bool) preg_match('/^[1-9]\d*$/D', $recordId), 404);
            abort_unless((bool) preg_match('/^[1-9]\d*$/D', $userId), 404);

            $admin = $request->user('admin') ?: Auth::guard('admin')->user();
            if ($admin) {
                $voucher = VoucherInfo::query()
                    ->with('user')
                    ->whereKey((int) $recordId)
                    ->where('user_id', (int) $userId)
                    ->whereNull('deleted_at')
                    ->firstOrFail();
                $data['voucher'] = $voucher;
                $images = json_decode((string) $voucher->images, true);
                $data['voucherImages'] = is_array($images) ? array_values(array_filter($images, 'is_string')) : [];
            } else {
                $data['voucher'] = null;
                $data['voucherImages'] = [];
            }
        }

        if ($legacyUri === 'index/admin/customer/{user_id?}') {
            $parentId = (int) $request->route('user_id');
            $admin = $request->user('admin') ?: Auth::guard('admin')->user();
            if ($admin && $parentId > 0) {
                $data['parentAgent'] = UserInfo::query()
                    ->where('user_id', $parentId)
                    ->where('account_type', 1)
                    ->firstOrFail();
                $data['directCustomerResult'] = app(LegacyAdminCustomerSearchService::class)
                    ->directCustomers($parentId, $admin);
            }
        }

        if ($legacyUri === 'index/admin/cust/add') {
            $data['customerGroups'] = GroupConfig::query()
                ->user()
                ->enabled()
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get();
        }

        if ($legacyUri === 'index/admin/cust/cust_detail/{acc_uid}') {
            $userId = (int) $request->route('acc_uid');
            $customer = UserInfo::query()
                ->with(['login', 'auth', 'parent', 'groupConfig'])
                ->where('user_id', $userId)
                ->where('account_type', 2)
                ->firstOrFail();

            $data['customer'] = $customer;
            $data['customerGroups'] = GroupConfig::query()
                ->user()
                ->where(function ($query) use ($customer): void {
                    $query->where('is_enabled', 1);
                    if ((int) $customer->group_id > 0) {
                        $query->orWhere('id', (int) $customer->group_id);
                    }
                })
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get();
        }

        return $data;
    }

    /**
     * 按 URI 前缀把旧后台地址映射到现代模块名。
     *
     * 前缀匹配采用精确段匹配；以 _ 结尾的前缀表示整族（如 agents_）前缀匹配，
     * 无法识别时回退到 dashboard 模块，保证任何旧地址都能打开页面而不是 500。
     *
     * @param string $legacyUri 当前旧后台 URI。
     * @return string 模块名，用于拼接 admin_layui::<module>.index 视图。
     */
    private function pageModuleFor(string $legacyUri): string
    {
        $prefixMap = [
            'index/admin/Administrators' => 'admins',
            'index/admin/agent' => 'agents',
            'index/admin/agents' => 'agents',
            'index/admin/agents_' => 'agents',
            'index/admin/amount/batch_operation_withdraw' => 'withdraw-imports',
            'index/admin/amount/batch_operation' => 'deposit-imports',
            'index/admin/amount/deposit_import_index' => 'deposit-imports',
            'index/admin/amount/deposit_flow' => 'deposits',
            'index/admin/amount/orderId_detail' => 'withdrawals',
            'index/admin/amount/rightsSummarySearchDetail' => 'rights-summary',
            'index/admin/amount/rights_downloadfile' => 'rights-summary',
            'index/admin/amount/rights_summary' => 'rights-summary',
            'index/admin/amount/show_channel_browse' => 'channels',
            'index/admin/amount/undeposit_flow' => 'undeposit-flows',
            'index/admin/amount/whpj_rate' => 'exchange-rates',
            'index/admin/amount/withdraw_apply' => 'withdrawals',
            'index/admin/amount/withdraw_downloadfile' => 'withdrawals',
            'index/admin/amount/withdrawDownloadfile' => 'withdraw-flows',
            'index/admin/amount/withdraw_flow' => 'withdraw-flows',
            'index/admin/amount/withdraw_import_index' => 'withdraw-imports',
            'index/admin/auth/user_certified' => 'authentications',
            'index/admin/auth/user_certified_detail' => 'authentications',
            'index/admin/auth/user_examine' => 'authentications',
            'index/admin/auth/user_voucher' => 'vouchers',
            'index/admin/auth/voucher_info_browse' => 'vouchers',
            'index/admin/bigAgents' => 'big-agents',
            'index/admin/big_agents_list' => 'big-agents',
            'index/admin/cancel' => 'cancel-applies',
            'index/admin/credit' => 'credit-imports',
            'index/admin/cust' => 'users',
            'index/admin/customer' => 'users',
            'index/admin/fengXian' => 'risk',
            'index/admin/gift' => 'gifts',
            'index/admin/group/user_group' => 'group-configs',
            'index/admin/group' => 'group-configs',
            'index/admin/news' => 'news',
            'index/admin/online' => 'online-users',
            'index/admin/order/close_list' => 'trades',
            'index/admin/order/open_list' => 'trades',
            'index/admin/order/position_summary_list' => 'position-summary',
            'index/admin/order/production_list' => 'productions',
            'index/admin/order/real_commission_list' => 'realtime-commissions',
            'index/admin/order/whs_exp_zero_list' => 'whs-exp-zero',
            'index/admin/role' => 'roles',
            'index/admin/withdraw' => 'withdrawals',
            'index/admin/welcome' => 'dashboard',
            'index/admin/index' => 'dashboard',
        ];

        foreach ($prefixMap as $prefix => $module) {
            $matchesUnderscoreFamily = substr($prefix, -1) === '_'
                && strpos($legacyUri, $prefix) === 0;

            if ($legacyUri === $prefix
                || strpos($legacyUri, $prefix . '/') === 0
                || $matchesUnderscoreFamily) {
                return $module;
            }
        }

        return 'dashboard';
    }

    /**
     * 把旧 URI 精确映射到现代命名路由。
     *
     * 优先查 exact 表，命中即返回 route 名与可选 defaults；带 {file}/{role} 等占位符
     * 的导出地址通过前缀匹配兜底。defaults 用于向现代控制器注入 status、is_enabled 等
     * 旧页面未提交但语义必需的默认值。
     *
     * @param string $legacyUri 当前旧后台 URI。
     * @return array{route: string, defaults?: array<string, mixed>}|null 匹配不到时返回 null。
     */
    private function targetRouteFor(string $legacyUri): ?array
    {
        $exact = [
            'index/admin/Administrators/addsave' => ['route' => 'admin_api_createAdmin'],
            'index/admin/Administrators/del' => ['route' => 'admin_api_deleteAdmin'],
            'index/admin/Administrators/editsave' => ['route' => 'admin_api_updateAdmin'],
            'index/admin/Administrators/start' => ['route' => 'admin_api_changeAdminStatus', 'defaults' => ['status' => 1]],
            'index/admin/Administrators/stop' => ['route' => 'admin_api_changeAdminStatus', 'defaults' => ['status' => 0]],
            'index/admin/agent/update' => ['route' => 'admin_api_updateAgentLevel'],
            'index/admin/agent/v2/agentsListSearchV2' => ['route' => 'admin_api_agentStatsList'],
            'index/admin/agents/agentsExamineListSearch' => ['route' => 'admin_api_agentList'],
            'index/admin/agents/agentsListSearch' => ['route' => 'admin_api_agentList'],
            'index/admin/agents/agents_edit_save' => ['route' => 'admin_api_updateAgentLevel'],
            'index/admin/agents_save' => ['route' => 'admin_api_createAgent'],
            'index/admin/amount/OTCwithdrawOrderIdDetail' => ['route' => 'admin_api_withdrawList'],
            'index/admin/amount/againDepositAmount' => ['route' => 'admin_api_retryDepositImport'],
            'index/admin/amount/againWithdrawAmount' => ['route' => 'admin_api_retryWithdrawImport'],
            'index/admin/amount/batchOperation' => ['route' => 'admin_api_createDepositImport'],
            'index/admin/amount/batchOperationWithdraw' => ['route' => 'admin_api_createWithdrawImport'],
            'index/admin/amount/batchWithdrawApply' => ['route' => 'admin_api_withdrawProcess'],
            'index/admin/amount/channel_enable' => ['route' => 'admin_api_toggleChannel'],
            'index/admin/amount/channel_enableV2' => ['route' => 'admin_api_toggleChannel'],
            'index/admin/amount/confirm_options' => ['route' => 'admin_api_manualConfirmRightsSettlement'],
            'index/admin/amount/depositDownloadfile/{file}/{role}' => ['route' => 'admin_api_exportDepositFlows'],
            'index/admin/amount/depositExport' => ['route' => 'admin_api_exportDepositFlows'],
            'index/admin/amount/depositFlowSearch' => ['route' => 'admin_api_depositFlowList'],
            'index/admin/amount/depositFlowSearchV2' => ['route' => 'admin_api_depositFlowList'],
            'index/admin/amount/depositImportExcel' => ['route' => 'admin_api_createDepositImport'],
            'index/admin/amount/depositImportSearch' => ['route' => 'admin_api_depositImportList'],
            'index/admin/amount/generate_OTCorder' => ['route' => 'admin_api_withdrawProcess'],
            'index/admin/amount/manual_confirm_options' => ['route' => 'admin_api_manualConfirmRightsSettlement'],
            'index/admin/amount/order_status' => ['route' => 'admin_api_withdrawComplete'],
            'index/admin/amount/order_status_OTC' => ['route' => 'admin_api_withdrawComplete'],
            'index/admin/amount/rightsSumExport' => ['route' => 'admin_api_exportRightsSummary'],
            'index/admin/amount/rightsSummarySearch' => ['route' => 'admin_api_rightsSummaryList'],
            'index/admin/amount/undepositFlowSearch' => ['route' => 'admin_api_undepositFlowList'],
            'index/admin/amount/undepositFlowSearchV2' => ['route' => 'admin_api_undepositFlowList'],
            'index/admin/amount/updateCurrOrderId' => ['route' => 'admin_api_withdrawProcess'],
            'index/admin/amount/whpj_rate_save' => ['route' => 'admin_api_updateExchangeRate'],
            'index/admin/amount/withdrawApplySearch' => ['route' => 'admin_api_withdrawList'],
            'index/admin/amount/withdrawApplySearchV2' => ['route' => 'admin_api_withdrawList'],
            'index/admin/amount/rights_downloadfile/{file}/{role}' => ['route' => 'admin_api_exportRightsSummary'],
            'index/admin/amount/withdrawDownloadfile/{file}/{role}' => ['route' => 'admin_api_exportWithdrawFlows'],
            'index/admin/amount/withdrawExport' => ['route' => 'admin_api_exportWithdrawals'],
            'index/admin/amount/withdraw_downloadfile/{file}/{role}' => ['route' => 'admin_api_exportWithdrawals'],
            'index/admin/amount/withdrawFlowExport' => ['route' => 'admin_api_exportWithdrawFlows'],
            'index/admin/amount/withdrawFlowSearch' => ['route' => 'admin_api_withdrawFlowList'],
            'index/admin/amount/withdrawFlowSearchV2' => ['route' => 'admin_api_withdrawFlowList'],
            'index/admin/amount/withdrawImportExcel' => ['route' => 'admin_api_createWithdrawImport'],
            'index/admin/amount/withdrawImportSearch' => ['route' => 'admin_api_withdrawImportList'],
            'index/admin/auth/userCertifiedSearch' => ['route' => 'admin_api_authCertifiedList'],
            'index/admin/auth/userCertifiedSearchV2' => ['route' => 'admin_api_authCertifiedList'],
            'index/admin/auth/userExaminSearch' => ['route' => 'admin_api_authPendingList'],
            'index/admin/auth/userExaminSearchV2' => ['route' => 'admin_api_authPendingList'],
            'index/admin/auth/user_idcard_bank' => ['route' => 'admin_api_reviewAuth'],
            'index/admin/auth/voucherInfoSearch' => ['route' => 'admin_api_voucherList'],
            'index/admin/auth/voucherInfoSearchV2' => ['route' => 'admin_api_voucherList'],
            'index/admin/auth/voucherReviewSave' => ['route' => 'admin_api_voucherApprove'],
            'index/admin/bigAgents/agentsListSearch' => ['route' => 'admin_api_bigAgentList'],
            'index/admin/bigAgents/del' => ['route' => 'admin_api_deleteBigAgent'],
            'index/admin/bigAgents/save' => ['route' => 'admin_api_createBigAgent'],
            'index/admin/bigAgents/start' => ['route' => 'admin_api_changeBigAgentStatus', 'defaults' => ['is_enabled' => 1]],
            'index/admin/bigAgents/stop' => ['route' => 'admin_api_changeBigAgentStatus', 'defaults' => ['is_enabled' => 0]],
            'index/admin/bigAgents/subAgentsListSearch' => ['route' => 'admin_api_agentDescendants'],
            'index/admin/bigAgents/updateInfo' => ['route' => 'admin_api_updateBigAgent'],
            'index/admin/cancel/cancel_apply_nopass' => ['route' => 'admin_api_cancelApplyReject'],
            'index/admin/cancel/cancel_apply_pass' => ['route' => 'admin_api_cancelApplyApprove'],
            'index/admin/cancel/update_cancel' => ['route' => 'admin_api_cancelApplyReject'],
            'index/admin/cancel/userlistSearch' => ['route' => 'admin_api_cancelApplyList'],
            'index/admin/cancel/userlistSearchV2' => ['route' => 'admin_api_cancelApplyList'],
            'index/admin/credit/againCreditAmount' => ['route' => 'admin_api_retryCreditImport'],
            'index/admin/credit/creditImportExcel' => ['route' => 'admin_api_createCreditImport'],
            'index/admin/credit/creditImportSearch' => ['route' => 'admin_api_creditImportList'],
            'index/admin/cust/custChangeListSearch' => ['route' => 'admin_api_userList'],
            'index/admin/cust/custChangeListSearchV2' => ['route' => 'admin_api_userList'],
            'index/admin/cust/custListSearch' => ['route' => 'admin_api_userList'],
            'index/admin/cust/custListSearchV2' => ['route' => 'admin_api_userList'],
            // Customer group-change approvals are a separate capability from
            // ordinary customer updates and agent confirmation actions.
            'index/admin/cust/cust_apply_nopass' => ['route' => 'admin_api_customerGroupApproval'],
            'index/admin/cust/cust_apply_pass' => ['route' => 'admin_api_customerGroupApproval'],
            'index/admin/cust/cust_save_add' => ['route' => 'admin_api_createUser'],
            'index/admin/cust/cust_save_info' => ['route' => 'admin_api_updateUser'],
            'index/admin/fengXian/IpaddressDeatail/{idaddr}' => ['route' => 'admin_api_riskIpDetail'],
            'index/admin/fengXian/IpaddressSearch' => ['route' => 'admin_api_riskIpList'],
            'index/admin/fengXian/positionSearch' => ['route' => 'admin_api_riskPositions'],
            'index/admin/fengXian/positionSearchv2' => ['route' => 'admin_api_riskPositions'],
            'index/admin/fengXian/profitSearch' => ['route' => 'admin_api_riskProfitUsers'],
            'index/admin/fengXian/profitSearchV2' => ['route' => 'admin_api_riskProfitUsers'],
            'index/admin/gift/addressList' => ['route' => 'admin_api_giftAddressList'],
            'index/admin/gift/send_gift' => ['route' => 'admin_api_sendGift'],
            'index/admin/gift/shipment_list' => ['route' => 'admin_api_giftShipmentList'],
            'index/admin/gift/shipment_list_export' => ['route' => 'admin_api_exportGiftShipments'],
            'index/admin/group/store' => ['route' => 'admin_api_createGroupConfig'],
            'index/admin/group/update' => ['route' => 'admin_api_updateGroupConfig'],
            'index/admin/group/user_group_delete' => ['route' => 'admin_api_deleteUserGroup'],
            'index/admin/group/user_group_search' => ['route' => 'admin_api_userGroupList'],
            'index/admin/group/user_group_searchV2' => ['route' => 'admin_api_userGroupList'],
            'index/admin/group/user_group_store' => ['route' => 'admin_api_createUserGroup'],
            'index/admin/group/user_group_update' => ['route' => 'admin_api_updateUserGroup'],
            'index/admin/logon' => ['route' => 'admin_api_login'],
            'index/admin/news/del' => ['route' => 'admin_api_deleteNews'],
            'index/admin/news/newsListSearch' => ['route' => 'admin_api_newsList'],
            'index/admin/news/news_save' => ['route' => 'admin_api_createNews'],
            'index/admin/news/news_update' => ['route' => 'admin_api_updateNews'],
            'index/admin/online/search' => ['route' => 'admin_api_onlineUserList'],
            'index/admin/order/closeListSearch' => ['route' => 'admin_api_closedPositions'],
            'index/admin/order/closeListSearchV2' => ['route' => 'admin_api_closedPositions'],
            'index/admin/order/oneKeySearch' => ['route' => 'admin_api_whsExpZero'],
            'index/admin/order/oneKeyZero' => ['route' => 'admin_api_whsExpZero'],
            'index/admin/order/openlistSearch' => ['route' => 'admin_api_openPositions'],
            'index/admin/order/openlistSearchV2' => ['route' => 'admin_api_openPositions'],
            'index/admin/order/positionSummarySearch' => ['route' => 'admin_api_positionSummaryList'],
            'index/admin/order/productionListSearch' => ['route' => 'admin_api_productionList'],
            'index/admin/order/productionListSearchV2' => ['route' => 'admin_api_productionList'],
            'index/admin/order/realCommissionListSearch' => ['route' => 'admin_api_realtimeCommissionList'],
            'index/admin/order/realCommissionListSearchV2' => ['route' => 'admin_api_realtimeCommissionList'],
            'index/admin/order/v2/parentPath' => ['route' => 'admin_api_agentParentPath'],
            'index/admin/order/v2/positionSummarySearchV2' => ['route' => 'admin_api_positionSummaryList'],
            'index/admin/order/v2/subAgentsListSearchV2' => ['route' => 'admin_api_positionSummaryList'],
            'index/admin/order/whsExpZeroListSearch' => ['route' => 'admin_api_whsExpZeroRecords'],
            'index/admin/order/whsExpZeroListSearchV2' => ['route' => 'admin_api_whsExpZeroRecords'],
            'index/admin/role/addsave' => ['route' => 'admin_api_createRole'],
            'index/admin/role/editsave' => ['route' => 'admin_api_updateRole'],
            'index/admin/send/againSendSms' => ['route' => 'admin_api_resetUserPassword'],
            'index/admin/userinfo/save' => ['route' => 'admin_api_updateProfile'],
            'index/admin/userpwd/save' => ['route' => 'admin_api_changePassword'],
            'index/admin/withdraw/completedExport' => ['route' => 'admin_api_exportWithdrawals', 'defaults' => ['status' => 2]],
            'index/admin/withdraw/completedSearch' => ['route' => 'admin_api_withdrawList', 'defaults' => ['status' => 2]],
            'index/admin/withdraw/failedExport' => ['route' => 'admin_api_exportWithdrawals', 'defaults' => ['status' => 3]],
            'index/admin/withdraw/failedSearch' => ['route' => 'admin_api_withdrawList', 'defaults' => ['status' => 3]],
            'index/admin/withdraw/pendingExport' => ['route' => 'admin_api_exportWithdrawals', 'defaults' => ['status' => 0]],
            'index/admin/withdraw/pendingSearch' => ['route' => 'admin_api_withdrawList', 'defaults' => ['status' => 0]],
            'index/admin/withdraw/processingExport' => ['route' => 'admin_api_exportWithdrawals', 'defaults' => ['status' => 1]],
            'index/admin/withdraw/processingSearch' => ['route' => 'admin_api_withdrawList', 'defaults' => ['status' => 1]],
        ];

        if (isset($exact[$legacyUri])) {
            return $exact[$legacyUri];
        }

        if (strpos($legacyUri, 'index/admin/role/del') === 0) {
            return ['route' => 'admin_api_deleteRole'];
        }

        return null;
    }

    /**
     * 把旧后台请求转发到现代命名路由。
     *
     * 根据目标路由重建子请求：替换 URI 占位参数、合并旧字段 payload 与 defaults，
     * JSON 请求保留真实 body 并同步 request 输入袋；复制原请求头，避免现代控制器的
     * Validator 与 Session 校验读到空输入。登录等依赖同一 Session 的路由在当前请求
     * 上下文直接运行，其余走完整 Kernel 以保留中间件链。
     *
     * @param Request $request 当前旧后台请求。
     * @param string $routeName 现代命名路由名。
     * @param array<string, mixed> $defaults 需注入的默认参数，优先级高于旧 payload。
     * @return Response 现代路由处理后的响应。
     */
    private function forwardToNamedRoute(Request $request, string $routeName, array $defaults): Response
    {
        $route = Route::getRoutes()->getByName($routeName);
        if (!$route) {
            return response()->json([
                'code' => 500,
                'message' => 'Current admin route is missing.',
                'data' => [
                    'target_route' => $routeName,
                ],
            ], 500);
        }

        $uri = '/' . ltrim($route->uri(), '/');
        $parameters = $this->routeParametersFor($route->uri(), $request);

        foreach ($parameters as $key => $value) {
            if ($value === null && strpos($route->uri(), '{' . $key . '?}') === false) {
                return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED, [
                    'legacy_uri' => trim($request->path(), '/'),
                    'target_route' => $routeName,
                    'missing_parameter' => $key,
                ]);
            }

            if ($value === null) {
                continue;
            }

            $uri = preg_replace('/\{' . preg_quote($key, '/') . '\??\}/', (string) $value, $uri);
        }

        $payload = array_replace($this->payloadForLegacyTarget($request), $defaults);
        $server = $request->server->all();
        $content = null;
        $requestParameters = $payload;
        if ($request->isJson()) {
            // JSON 子请求必须携带真实 body；仅设置参数袋会在 Kernel 重新解析时变成空输入。
            $server['CONTENT_TYPE'] = 'application/json';
            $server['HTTP_ACCEPT'] = $server['HTTP_ACCEPT'] ?? 'application/json';
            $content = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $requestParameters = [];
        }
        $subRequest = Request::create(
            $uri,
            $this->routeMethodFor($route->methods()),
            $requestParameters,
            $request->cookies->all(),
            $request->files->all(),
            $server,
            $content
        );
        $subRequest->attributes->set('legacy_admin_uri', trim($request->path(), '/'));
        // 旧后台常以 JSON 提交；复制原请求头后必须同步 JSON 输入袋，
        // 否则现代控制器的 Request::all()/Validator 会读到空参数并误报必填失败。
        $subRequest->request->replace($payload);
        if ($request->isJson()) {
            $subRequest->setJson(new InputBag($payload));
        }

        foreach ($request->headers->all() as $key => $values) {
            if (strtolower((string) $key) === 'content-length') {
                continue;
            }
            $subRequest->headers->set($key, $values);
        }

        $subRequest->headers->set('X-Legacy-Admin-Route', $request->path());

        // 旧登录依赖同一次 HTTP 请求中的图形验证码 Session。若再次进入完整
        // Kernel，嵌套请求会重新从 Cookie 启动 Session，验证码可能被误判为过期。
        // 登录本身是公开路由，不需要 JWT/权限中间件，因此在当前请求上下文运行
        // 现代路由，既保留 AuthController 的字段校验和签发逻辑，也确保 Session 连续。
        if ($routeName === 'admin_api_login') {
            $matchedRoute = Route::getRoutes()->match($subRequest);
            $subRequest->setRouteResolver(static function () use ($matchedRoute) {
                return $matchedRoute;
            });
            $subRequest->setUserResolver(static function ($guard = null) {
                return Auth::guard($guard ?: 'admin')->user();
            });
            if ($request->hasSession()) {
                $subRequest->setLaravelSession($request->session());
            }

            return $this->runMatchedRouteWithRequest($matchedRoute, $subRequest);
        }

        if ($request->attributes->get('legacy_admin_auth_mode') === 'session') {
            $matchedRoute = Route::getRoutes()->match($subRequest);
            $subRequest->setRouteResolver(static function () use ($matchedRoute) {
                return $matchedRoute;
            });
            $subRequest->setUserResolver(static function ($guard = null) {
                return Auth::guard($guard ?: 'admin')->user();
            });
            if ($request->hasSession()) {
                $subRequest->setLaravelSession($request->session());
            }

            return $this->runMatchedRouteWithRequest($matchedRoute, $subRequest);
        }

        return app()->handle($subRequest);
    }

    /**
     * 用指定子请求直接运行已匹配的现代路由。
     *
     * 参数逻辑说明：
     * - $matchedRoute 表示已经通过子请求匹配出的现代路由对象。
     * - $subRequest 表示兼容层重建后的请求，包含现代 URI、JSON body、Session 和用户解析器。
     * - 直接调用 Route::run() 时，控制器依赖注入会读取容器中的 request；运行前临时替换可避免现代控制器读到旧请求或空 JSON。
     *
     * @param mixed $matchedRoute 已匹配的 Laravel 路由对象。
     * @param Request $subRequest 传给现代控制器的子请求。
     * @return Response 现代控制器返回的响应。
     */
    private function runMatchedRouteWithRequest($matchedRoute, Request $subRequest): Response
    {
        $originalRequest = app('request');
        app()->instance('request', $subRequest);

        try {
            return $matchedRoute->run();
        } finally {
            app()->instance('request', $originalRequest);
        }
    }

    /**
     * 归一化旧请求字段并转换旧别名。
     *
     * 展开 data 嵌套对象（根级优先），把 userId/useremail/manual_reason 等旧字段名
     * 映射为现代字段名，并补全 phone（modules 区号 + userphoneNo）与 password（password1）
     * 兼容取值。未识别的旧字段原样保留，由现代控制器校验兜底。
     *
     * @param Request $request 当前旧后台请求。
     * @return array<string, mixed> 新旧字段并存的归一化 payload。
     */
    private function payloadForLegacyTarget(Request $request): array
    {
        $payload = $request->all();
        $nested = $request->input('data');
        if (is_array($nested)) {
            unset($payload['data']);
            $payload = array_replace($nested, $payload);
        }

        $aliases = [
            'userId' => 'user_id',
            'userInviterId' => 'inviter_id',
            'useremail' => 'email',
            'username' => 'user_name',
            // 旧持仓汇总/用户搜索 Blade 使用驼峰 userName，PHP 数组键区分大小写，必须单独映射。
            'userName' => 'user_name',
            'startdate' => 'start_date',
            'enddate' => 'end_date',
            'userIdcardNo' => 'id_card_no',
            'withdraw_userId' => 'user_id',
            'withdraw_id' => 'local_order_no',
            'withdraw_startdate' => 'start_date',
            'withdraw_enddate' => 'end_date',
            'deposit_startdate' => 'start_date',
            'deposit_enddate' => 'end_date',
            'manual_reason' => 'manual_confirm_reason',
        ];
        foreach ($aliases as $legacy => $modern) {
            if (!array_key_exists($modern, $payload) && array_key_exists($legacy, $payload)) {
                $payload[$modern] = $payload[$legacy];
            }
        }

        if (in_array($legacyUri = ($request->route() ? $request->route()->uri() : trim($request->path(), '/')), [
            'index/admin/amount/depositImportSearch',
            'index/admin/amount/withdrawImportSearch',
        ], true)) {
            if (!array_key_exists('is_synced', $payload) && array_key_exists('sync_succ', $payload)) {
                $payload['is_synced'] = $payload['sync_succ'];
            }
            if (!array_key_exists('start_date', $payload) || trim((string) $payload['start_date']) === '') {
                $payload['start_date'] = '2024-01-01';
            }
            if (!array_key_exists('end_date', $payload) || trim((string) $payload['end_date']) === '') {
                $payload['end_date'] = date('Y-m-d');
            }
        }

        if ($legacyUri === 'index/admin/userpwd/save') {
            if (!array_key_exists('old_password', $payload) && array_key_exists('pwd', $payload)) {
                $payload['old_password'] = $payload['pwd'];
            }
            if (!array_key_exists('password', $payload) && array_key_exists('npwd', $payload)) {
                $payload['password'] = $payload['npwd'];
            }
            if (!array_key_exists('password_confirmation', $payload)
                && array_key_exists('password', $payload)) {
                $payload['password_confirmation'] = $payload['password'];
            }
        }

        if (in_array($legacyUri, [
            'index/admin/role/addsave',
            'index/admin/role/editsave',
        ], true)) {
            if (!array_key_exists('name', $payload) && array_key_exists('username', $payload)) {
                $payload['name'] = $payload['username'];
            }
            if (!array_key_exists('description', $payload) && array_key_exists('desc', $payload)) {
                $payload['description'] = $payload['desc'];
            }
            if (!array_key_exists('id', $payload) && array_key_exists('role_id', $payload)) {
                $payload['id'] = $payload['role_id'];
            }
            if (!array_key_exists('guard_type', $payload)) {
                $payload['guard_type'] = 'admin';
            }
            if (!array_key_exists('status', $payload)) {
                $payload['status'] = 1;
            }
        }

        if (!array_key_exists('phone', $payload) && array_key_exists('userphoneNo', $payload)) {
            $payload['phone'] = trim((string) ($payload['modules'] ?? '86'))
                . '-' . trim((string) $payload['userphoneNo']);
        }
        if (!array_key_exists('password', $payload) && array_key_exists('password1', $payload)) {
            $payload['password'] = $payload['password1'];
        }

        if (in_array($legacyUri, [
            'index/admin/auth/userExaminSearch',
            'index/admin/auth/userExaminSearchV2',
            'index/admin/auth/userCertifiedSearch',
            'index/admin/auth/userCertifiedSearchV2',
        ], true)) {
            if (!array_key_exists('start_date', $payload) || trim((string) $payload['start_date']) === '') {
                $payload['start_date'] = '2024-01-01';
            }
            if (!array_key_exists('end_date', $payload) || trim((string) $payload['end_date']) === '') {
                $payload['end_date'] = date('Y-m-d');
            }
        }

        // 旧持仓汇总三个搜索入口在旧控制器内默认 startdate=2024-01-01、enddate=当天；
        // 现代接口只在显式传入时过滤，因此转发前必须补齐同样的默认区间，保持统计口径一致。
        if (in_array($legacyUri, [
            'index/admin/order/positionSummarySearch',
            'index/admin/order/v2/positionSummarySearchV2',
            'index/admin/order/v2/subAgentsListSearchV2',
        ], true)) {
            if (!array_key_exists('start_date', $payload) || trim((string) $payload['start_date']) === '') {
                $payload['start_date'] = '2024-01-01';
            }
            if (!array_key_exists('end_date', $payload) || trim((string) $payload['end_date']) === '') {
                $payload['end_date'] = date('Y-m-d');
            }
        }

        return $payload;
    }

    /**
     * 提取目标路由 URI 占位参数对应的值。
     *
     * 解析 {name} 与 {name?} 占位符，逐个交给 parameterValue() 从旧请求的路由参数
     * 或输入袋取值，用于拼装现代子请求的 URI。
     *
     * @param string $routeUri 现代目标路由的 URI 模板。
     * @param Request $request 当前旧后台请求。
     * @return array<string, mixed> 占位符名到值的映射。
     */
    private function routeParametersFor(string $routeUri, Request $request): array
    {
        preg_match_all('/\{([^?}]+)\??\}/', $routeUri, $matches);
        $parameters = [];

        foreach ($matches[1] as $name) {
            $parameters[$name] = $this->parameterValue($name, $request);
        }

        return $parameters;
    }

    /**
     * 解析单个路由占位参数的值。
     *
     * 部分旧 URI 使用别名字段传主键（如 recId、grp_recId），按 legacyUri 登记的白名单
     * 逐一尝试；user_id/uid 等业务 ID 不参与全局兜底，避免把用户 ID 误当凭证、申请、
     * 导入记录或配置主键。全部缺失时返回 null 交给调用方失败关闭。
     *
     * @param string $name 路由占位符名。
     * @param Request $request 当前旧后台请求。
     * @return mixed 解析出的参数值，未找到时为 null。
     */
    private function parameterValue(string $name, Request $request)
    {
        $legacyUri = $request->route() ? $request->route()->uri() : trim($request->path(), '/');
        $newsIdAliases = [
            'index/admin/news/del' => ['newsid', 'newsId'],
            'index/admin/news/news_update' => ['newsId', 'newsid'],
        ];
        if (isset($newsIdAliases[$legacyUri])) {
            $payload = $this->legacyNewsPayload($request);
            foreach ($newsIdAliases[$legacyUri] as $candidate) {
                if (array_key_exists($candidate, $payload)) {
                    return $payload[$candidate];
                }
            }

            return null;
        }

        $aliases = [
            'index/admin/auth/voucherReviewSave' => ['recId'],
            'index/admin/group/user_group_delete' => ['grp_recId'],
            'index/admin/group/user_group_update' => ['grp_recId'],
            'index/admin/amount/updateCurrOrderId' => ['recordId'],
            // 旧支付通道启停页面只负责状态开关；这些别名均解析为 payment_channels.id，缺失时交由现代路由参数校验失败。
            'index/admin/amount/channel_enable' => ['recordId', 'channel_id', 'channelId', 'pay_channel_id', 'payChannelId'],
            'index/admin/amount/channel_enableV2' => ['recordId', 'channel_id', 'channelId', 'pay_channel_id', 'payChannelId'],
            // 旧权益手动确认必须解析到结算记录主键；manual_uid 是用户 ID，不能作为 rights_settlements.id 使用。
            'index/admin/amount/manual_confirm_options' => ['recordId', 'settlement_id', 'settlementId', 'rights_settlement_id', 'rightsSettlementId'],
        ];

        // 路由占位符和明确登记的旧字段属于同一主键域；user_id/uid 等其他业务 ID
        // 不再作为全局兜底，避免把用户 ID 误当凭证、申请、导入记录或配置主键。
        $candidates = array_unique(array_merge([$name], $aliases[$legacyUri] ?? []));

        foreach ($candidates as $candidate) {
            $value = $request->route($candidate);
            if ($value !== null && $value !== '') {
                return $value;
            }

            if ($request->filled($candidate)) {
                return $request->input($candidate);
            }
        }

        return null;
    }

    /**
     * 选择子请求使用的 HTTP 方法。
     *
     * HEAD 只用于探测，不参与真实转发；目标路由允许 POST 时优先用 POST，
     * 否则取第一个可用方法，保证现代路由按声明的方法接收请求。
     *
     * @param array<int, string> $methods 目标路由声明的方法列表。
     * @return string 转发使用的 HTTP 方法名。
     */
    private function routeMethodFor(array $methods): string
    {
        $methods = array_values(array_diff($methods, ['HEAD']));

        return in_array('POST', $methods, true) ? 'POST' : ($methods[0] ?? 'GET');
    }
}
