<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 20:06
 */

namespace App\Http\Controllers\Front;

use App\Constants\ResponseCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 前台交易品种控制器。
 *
 * 文件功能：
 * - 提供 `GET /api/front/trade-symbols` 接口，供 Layui Blade 和 Naive 风格页面加载交易品种动态下拉选项。
 * - 交易品种数据只读取真实 `symbol_prices` 表，不写死示例品种，也不在前端生成虚拟选项。
 * - 该接口返回的品种会被前台持仓、订单等页面作为 symbol 精确筛选条件使用。
 */
class TradeSymbolController extends FrontBaseController
{
    /**
     * 返回前台交易品种下拉选项。
     *
     * 业务逻辑说明：
     * - symbol_prices 表不存在时返回空 list，避免没有导入行情表的环境出现 500。
     * - sym_symbol 表示旧表结构中的交易品种字段，symbol 表示新表结构中的交易品种字段。
     * - voided 表示旧表结构中的启用状态字段，status 表示新表结构中的启用状态字段。
     * - deleted_at 表示新表结构中的软删除时间，非空记录不能进入业务筛选下拉。
     * - list 表示前端 select 组件使用的选项数组，value 和 label 都使用交易品种编码。
     * - response.query_success 表示查询成功的多语言消息 key，由 ApiResponse 统一翻译。
     *
     * @return JsonResponse 交易品种下拉选项响应。
     */
    public function index(): JsonResponse
    {
        if (!Schema::hasTable('symbol_prices')) {
            return $this->success(['list' => []], 'response.query_success', ResponseCode::SUCCESS);
        }

        // 根据当前真实表结构选择品种字段，兼容旧库 sym_symbol 和新库 symbol。
        $symbolColumn = Schema::hasColumn('symbol_prices', 'sym_symbol') ? 'sym_symbol' : 'symbol';
        $query = DB::table('symbol_prices')
            ->select($symbolColumn)
            ->whereNotNull($symbolColumn)
            ->where($symbolColumn, '<>', '')
            ->distinct();

        // 根据当前真实表结构选择启用状态字段，避免停用品种进入前台筛选下拉。
        if (Schema::hasColumn('symbol_prices', 'voided')) {
            $query->where('voided', 1);
        } elseif (Schema::hasColumn('symbol_prices', 'status')) {
            $query->where('status', 1);
        }
        if (Schema::hasColumn('symbol_prices', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        // 输出统一的 value/label 结构，前端可直接渲染为 select option。
        $symbols = $query->orderBy($symbolColumn)
            ->pluck($symbolColumn)
            ->map(fn ($symbol) => trim((string) $symbol))
            ->filter()
            ->unique()
            ->values()
            ->map(fn ($symbol) => ['value' => $symbol, 'label' => $symbol])
            ->all();

        return $this->success(['list' => $symbols], 'response.query_success', ResponseCode::SUCCESS);
    }
}
