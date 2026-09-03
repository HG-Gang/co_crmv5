<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

declare(strict_types=1);

namespace App\Http\Controllers\Front;

use App\Services\Legacy\LegacyCommissionSummaryService;
use Illuminate\Http\JsonResponse;

/**
 * 旧前台 comm_summary 兼容控制器。
 *
 * 文件功能：
 * - 承接旧项目 GET /user/position/comm_summary 定时触发入口。
 * - 不再错误渲染持仓汇总 Blade，而是执行真实返佣批处理并返回标准 JSON 汇总。
 * - 具体交易筛选、代理链计算、MT4 入账和幂等由 LegacyCommissionSummaryService 负责。
 *
 * 返回结果：
 * - code=1000 表示请求已执行；data 中的计数用于区分成功、可重试、失败、跳过和已完成交易。
 * - 外部 MT4 失败不会被控制器伪装成成功，服务会保留对应出账状态供后续处理。
 *
 * 兼容语义：
 * - 本控制器不渲染 Blade，也不做鉴权以外的业务判断；访问控制由路由中间件完成。
 * - code=1000 只表示批次已执行，不代表每笔交易都成功，data 内的计数才是审计依据。
 * - 交易筛选、代理链计算与 MT4 入账细节全部由 LegacyCommissionSummaryService 负责，本控制器不接触任何密钥或敏感值。
 */
class LegacyCommissionSummaryController extends FrontBaseController
{
    /**
     * 执行旧实时返佣汇总入口。
     *
     * @param LegacyCommissionSummaryService $service 返佣批处理服务，由容器注入统一 MT4 网关。
     * @return JsonResponse 返回旧入口本批次的可审计执行汇总。
     */
    public function commSummary(LegacyCommissionSummaryService $service): JsonResponse
    {
        return $this->success($service->settleBatch());
    }
}
