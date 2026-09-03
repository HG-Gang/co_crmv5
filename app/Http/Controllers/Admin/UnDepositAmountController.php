<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\Admin;

use App\Models\DepositRecord;
use App\Models\UserInfo;
use App\Services\AdminDataScopeService;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 后台未入金用户管理控制器。
 *
 * 文件功能：
 * - 查询已注册但尚未成功入金的用户列表，供运营人员跟进新用户入金转化。
 *
 * 功能逻辑说明：
 * - 从旧项目 UnDepositAmountController 迁移核心查询逻辑。
 * - 未入金流水口径：deposit_records 表中状态为待处理（status='01'）的入金申请记录。
 * - 从未入金口径：user_infos 中已注册、但 deposit_records 没有任何 status='02'（已审核通过）记录的用户。
 *
 * 安全边界：
 * - 两个列表均按管理员数据范围过滤（deposit 或 user 目标类型），避免越权查看其他角色的用户数据。
 */
class UnDepositAmountController extends AdminBaseController
{
    /**
     * 后台数据范围服务。
     *
     * @var AdminDataScopeService
     */
    protected $adminDataScopeService;

    /**
     * 构造函数。
     *
     * @param AdminDataScopeService $adminDataScopeService 数据范围服务。
     */
    public function __construct(AdminDataScopeService $adminDataScopeService)
    {
        $this->adminDataScopeService = $adminDataScopeService;
    }

    /**
     * 获取未入金记录列表（待处理的入金申请）。
     *
     * undepositFlowList() 参数说明：
     * - page：当前页码，默认第1页。
     * - per_page/limit：每页数量，兼容Layui的limit参数，默认15条。
     * - user_id：用户ID，精确筛选。
     * - undeposit_id：未入金记录ID或订单号，模糊匹配。
     * - deposit_startdate：申请开始日期，默认2024-01-01。
     * - deposit_enddate：申请结束日期，默认当前日期。
     *
     * 功能逻辑说明：
     * - 查询deposit_records表中status='01'（待审核）的记录。
     * - 这些记录表示用户已提交入金申请但尚未审核通过。
     * - 关联user_infos表获取用户基础信息。
     * - 统计每个用户的未入金申请数量和金额。
     *
     * @param Request $request 当前HTTP请求对象。
     * @return \Illuminate\Http\JsonResponse 返回未入金记录列表。
     */
    public function undepositFlowList(Request $request)
    {
        try {
            // 分页参数：兼容 Layui 默认提交的 page 与 limit。
            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', $request->input('limit', 15));

            // 旧页面筛选参数：userId/undeposit_id 做模糊匹配，日期默认回退到 2024-01-01 至今天。
            $userId = $request->input('user_id');
            $undepositId = $request->input('undeposit_id');
            $startDate = $request->input('deposit_startdate', '2024-01-01');
            $endDate = $request->input('deposit_enddate', date('Y-m-d'));

            // 只取待审核（status='01'）的入金申请，并关联用户基础信息。
            $query = DepositRecord::query()
                // 列名必须与 deposit_records 真实结构一致：该表只有 channel_name / channel_order_no /
                // gateway_code，没有 channel 或 third_party_order_no。引用不存在的列会抛
                // SQLSTATE[42S22] 1054，并被本方法末尾的 catch 降级成通用 500，掩盖真实原因。
                ->select([
                    'deposit_records.id',
                    'deposit_records.user_id',
                    'deposit_records.amount',
                    'deposit_records.channel_name',
                    'deposit_records.local_order_no',
                    'deposit_records.channel_order_no',
                    'deposit_records.status',
                    'deposit_records.remarks',
                    'deposit_records.created_at as rec_crt_date',
                    'deposit_records.updated_at as rec_upd_date',
                ])
                ->with('user')
                ->where('deposit_records.status', '01'); // status='01' 表示待审核

            // 用户与订单号筛选：订单号对本地单号、渠道单号、三方单号任一匹配即可。
            if ($userId) {
                $query->where('deposit_records.user_id', $userId);
            }

            // 旧筛选口径见项目1 UnDepositAmountController.php:72-75：undeposit_id 同时匹配
            // dep_outChannelNo 与 dep_outTrande 两列。新库对应 local_order_no 与 channel_order_no，
            // 两列即可覆盖旧语义；原先第三个 third_party_order_no 列在本表并不存在。
            if ($undepositId) {
                $query->where(function ($q) use ($undepositId) {
                    $q->where('deposit_records.local_order_no', 'like', '%' . $undepositId . '%')
                        ->orWhere('deposit_records.channel_order_no', 'like', '%' . $undepositId . '%');
                });
            }

            // 申请日期范围转换为 10 位时间戳区间过滤。
            if ($startDate && $endDate) {
                $query->whereBetween('deposit_records.created_at', [
                    strtotime($startDate . ' 00:00:00'),
                    strtotime($endDate . ' 23:59:59')
                ]);
            }

            // 按当前管理员数据范围过滤，避免越权查看其他用户入金申请。
            $admin = $request->user('admin');
            if ($admin) {
                $this->adminDataScopeService->apply($query, $admin, 'deposit', 'user_id');
            }

            // 先统计总数再取当前页，总数用于 Layui 表格分页条。
            $total = $query->count();

            // 旧页面用 skip/take 手写分页，数据格式与 Layui data/count 结构一致。
            $records = $query->orderByDesc('deposit_records.created_at')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            // 格式化输出：金额保留两位小数，时间戳转 Y-m-d H:i:s，用户名缺省为空串。
            $recordsData = $records->map(function ($record) {
                return [
                    'id' => $record->id,
                    'user_id' => $record->user_id,
                    'username' => $record->user ? $record->user->user_name : '',
                    'amount' => number_format($record->amount, 2, '.', ''),
                    // 输出键沿用旧页面的 channel 名称，取值来自真实列 channel_name；
                    // 不再输出 third_party_order_no，该列在 deposit_records 中不存在。
                    'channel' => $record->channel_name,
                    'local_order_no' => $record->local_order_no,
                    'channel_order_no' => $record->channel_order_no,
                    'status' => $record->status,
                    'remarks' => $record->remarks,
                    'rec_crt_date' => date('Y-m-d H:i:s', $record->rec_crt_date),
                    'rec_upd_date' => date('Y-m-d H:i:s', $record->rec_upd_date),
                ];
            })->toArray();

            // 兼容 Layui 表格：data 为当前页数据，count 为总条数。
            return $this->success([
                'data' => $recordsData,
                'count' => $total,
            ], __('admin.undeposit_list_fetched'));

        } catch (\Exception $e) {
            \Log::error('UnDepositAmountController.undepositFlowList error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }

    /**
     * 获取从未入金的用户列表（已注册但从未有入金记录的用户）。
     *
     * neverDepositUserList() 参数说明：
     * - page：当前页码。
     * - per_page/limit：每页数量。
     * - start_date：注册开始日期。
     * - end_date：注册结束日期。
     * - min_days：最少未入金天数。
     *
     * 功能逻辑说明：
     * - 查询user_infos表中已注册的用户。
     * - 排除在deposit_records表中有status='02'（已审核通过）记录的用户。
     * - 这些用户表示注册后从未成功入金。
     * - 计算未入金天数（当前时间 - 注册时间）。
     *
     * @param Request $request 当前HTTP请求对象。
     * @return \Illuminate\Http\JsonResponse 返回从未入金的用户列表。
     */
    public function neverDepositUserList(Request $request)
    {
        try {
            // 分页参数：兼容 Layui 默认提交的 page 与 limit。
            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', $request->input('limit', 15));

            // 注册日期与最少未入金天数筛选，默认回退到最近 30 天注册的用户。
            $startDate = $request->input('start_date', date('Y-m-d', strtotime('-30 days')));
            $endDate = $request->input('end_date', date('Y-m-d'));
            $minDays = (int) $request->input('min_days', 0); // 最少未入金天数，0 表示不过滤

            // 只取普通客户（account_type=2），并排除存在 status='02'（已审核通过）入金记录的用户。
            $query = UserInfo::query()
                ->select([
                    'user_infos.user_id',
                    'user_infos.user_name',
                    'user_infos.phone',
                    'user_infos.email',
                    'user_infos.created_at',
                ])
                ->where('user_infos.account_type', 2) // 只查询普通客户
                ->whereNotExists(function ($subQuery) {
                    // 排除已有成功入金记录的用户
                    $subQuery->select(DB::raw(1))
                        ->from('deposit_records')
                        ->whereColumn('deposit_records.user_id', 'user_infos.user_id')
                        ->where('deposit_records.status', '02'); // status='02' 表示已审核通过
                });

            // 注册日期范围转换为 10 位时间戳区间过滤。
            if ($startDate && $endDate) {
                $query->whereBetween('user_infos.created_at', [
                    strtotime($startDate . ' 00:00:00'),
                    strtotime($endDate . ' 23:59:59')
                ]);
            }

            // 按当前管理员数据范围过滤，避免越权查看其他角色的用户。
            $admin = $request->user('admin');
            if ($admin) {
                $this->adminDataScopeService->apply($query, $admin, 'user', 'user_id');
            }

            // 分页拉取用户，随后在内存中计算未入金天数。
            $users = $query->orderByDesc('user_infos.created_at')
                ->paginate($perPage, ['*'], 'page', $page);

            // 计算注册至今的天数，并过滤掉不满足 min_days 的用户；count 仍用分页总数。
            $now = time();
            $usersData = collect($users->items())->map(function ($user) use ($now, $minDays) {
                $registerTime = $user->created_at;
                $days = floor(($now - $registerTime) / 86400);

                // 最少未入金天数只作用于当前页展示，不参与 SQL 过滤。
                if ($minDays > 0 && $days < $minDays) {
                    return null;
                }

                return [
                    'user_id' => $user->user_id,
                    'user_name' => $user->user_name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'register_date' => date('Y-m-d H:i:s', $registerTime),
                    'never_deposit_days' => $days,
                ];
            })->filter()->values()->toArray();

            // 兼容 Layui 表格：data 为当前页数据，count 为总条数。
            return $this->success([
                'data' => $usersData,
                'count' => $users->total(),
            ], __('admin.never_deposit_user_list_fetched'));

        } catch (\Exception $e) {
            \Log::error('UnDepositAmountController.neverDepositUserList error: ' . $e->getMessage());
            return $this->serverErrorResponse();
        }
    }
}
