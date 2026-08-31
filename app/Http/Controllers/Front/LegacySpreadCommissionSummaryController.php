<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:13
 */

declare(strict_types=1);

namespace App\Http\Controllers\Front;

use App\Services\Legacy\LegacySpreadCommissionSummaryService;
use Illuminate\Http\JsonResponse;

/**
 * 旧前台 comm_summaryv2 点差返佣兼容控制器。
 *
 * 文件功能：
 * - 承接旧项目 GET /user/position/comm_summaryv2 的免登录定时任务入口。
 * - 执行品种点差、代理组点差比例和特殊手数倍率返佣，而非渲染持仓汇总页面。
 *
 * 返回结果：
 * - code=1000 表示批次已执行，data 返回各类处理数量。
 * - 失败、配置缺失或结果未知均保留未结算状态，不能被控制器伪装为成功。
 *
 * 安全边界：
 * - 本入口不校验登录态（旧项目由定时任务免登录调用），属于资金写入接口；恢复线上执行前必须迁移为受控的 console command 并限制调用来源。
 * - 控制器只转发 settleBatch() 结果，不落库不记账；批次内部任何失败都由服务层失败关闭。
 */
class LegacySpreadCommissionSummaryController extends FrontBaseController
{
    /**
     * 执行旧 V2 点差返佣汇总入口。
     *
     * @param LegacySpreadCommissionSummaryService $service 点差返佣服务，由容器注入统一 MT4 入金网关。
     * @return JsonResponse 返回本批次的点差返佣执行汇总。
     */
    public function commSummaryV2(LegacySpreadCommissionSummaryService $service): JsonResponse
    {
        return $this->success($service->settleBatch());
    }
}
