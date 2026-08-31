<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/13
 * Time: 02:52
 */

namespace App\Http\Controllers\Admin;

use App\Models\UserInfo;
use App\Models\UserLogin;
use App\Models\UserAuth;
use App\Constants\ResponseCode;
use App\Services\UserStatisticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

/**
 * 后台用户管理控制器。
 *
 * 文件功能：
 * - 本控制器是旧后台用户管理接口入口，负责用户列表、详情、资料更新、登录状态切换、实名认证审核和用户注销。
 * - user_id 在本控制器中表示业务用户 ID，对应 user_infos.user_id、user_logins.user_id 和 user_auths.user_id。
 * - 接口响应文案统一读取 resources/lang/{locale}/admin.php，避免后台 Blade + Layui 页面在切换语言时仍显示固定英文。
 */
class UserController extends AdminBaseController
{
    /**
     * 获取后台用户列表。
     *
     * index() 参数说明：
     * - page 表示当前页码，默认第 1 页。
     * - per_page 表示每页数量，默认 15 条。
     * - user_id 表示业务用户 ID，用于精确筛选 user_infos.user_id。
     * - email 表示登录邮箱，来源于 user_logins.email，通过 login 关联做模糊筛选。
     * - user_name 表示用户姓名或昵称，用于筛选 user_infos.user_name。
     * - account_type 表示账号类型，1=代理账号，2=普通客户账号。
     * - auth_status 表示实名认证状态，用于筛选 user_infos.auth_status。
     *
     * @param Request $request 当前 HTTP 请求对象，承载分页参数和用户筛选条件。
     * @return \Illuminate\Http\JsonResponse 返回分页用户列表，包含 login 与 auth 关联信息。
     */
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);

        $query = UserInfo::query()->with(['login', 'auth']);

        // user_id：业务用户 ID，精确定位单个用户。
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // email：登录邮箱，实际字段在 user_logins 表中，通过 login 关系筛选。
        if ($request->filled('email')) {
            $email = $request->email;
            $query->whereHas('login', function($q) use ($email) {
                $q->where('email', 'LIKE', "%{$email}%");
            });
        }

        // user_name：用户姓名或昵称，使用模糊匹配便于后台快速查询。
        if ($request->filled('user_name')) {
            $query->where('user_name', 'LIKE', "%{$request->user_name}%");
        }

        // account_type：账号类型，1=代理账号，2=普通客户账号。
        if ($request->filled('account_type')) {
            $query->where('account_type', $request->account_type);
        }

        // auth_status：实名认证状态，便于后台区分待审、通过或驳回用户。
        if ($request->filled('auth_status')) {
            $query->where('auth_status', $request->auth_status);
        }

        $users = $query->orderByDesc('user_id')->paginate($perPage, ['*'], 'page', $page);

        return $this->success($users, __('admin.user_list_fetched'));
    }

    /**
     * 获取用户详情。
     *
     * show() 参数说明：
     * - $userId 表示业务用户 ID，对应 user_infos.user_id。
     * - 返回数据会加载 login 与 auth 关联，供后台详情页展示登录账号和实名认证资料。
     *
     * @param int|string $userId 业务用户 ID。
     * @return \Illuminate\Http\JsonResponse 用户详情响应；用户不存在时返回 user_not_found。
     */
    public function show($userId)
    {
        $user = UserInfo::with(['login', 'auth'])->where('user_id', $userId)->first();
        
        if (!$user) {
            return $this->error(__('admin.user_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        return $this->success($user, __('admin.user_detail_fetched'));
    }

    /**
     * 更新用户资料。
     *
     * update() 参数说明：
     * - $request 当前 HTTP 请求对象，承载允许更新的用户资料字段。
     * - $userId 表示业务用户 ID，对应 user_infos.user_id。
     * - user_name 表示用户姓名或昵称。
     * - phone 表示联系电话。
     * - group_id 表示用户所属组别 ID。
     * - comm_rate 表示代理返佣比例，范围为 0 到 100 的百分数口径：与 user_infos.comm_rate 整数列、
     *   agent_levels.max_commission 及旧后台验证（max:100）一致；0..1 分数口径为历史缺陷，已修正。
     * - trading_mode、agent_level、parent_id、remarks 为旧后台资料维护字段，仅从白名单写入。
     *
     * @param Request $request 当前 HTTP 请求对象，承载用户资料更新字段。
     * @param int|string $userId 业务用户 ID。
     * @return \Illuminate\Http\JsonResponse 更新后的用户资料响应。
     */
    public function update(Request $request, $userId)
    {
        try {
            $user = UserInfo::where('user_id', $userId)->first();
            if (!$user) {
                return $this->error(__('admin.user_not_found'), ResponseCode::USER_NOT_FOUND);
            }

            $validator = Validator::make($request->all(), [
                'user_name' => 'sometimes|string|max:100',
                'phone'     => 'sometimes|string|max:20',
                'group_id'  => 'sometimes|integer',
                'comm_rate' => 'sometimes|numeric|min:0|max:100',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $data = $request->only([
                'user_name', 'phone', 'group_id', 'comm_rate', 'trading_mode',
                'agent_level', 'parent_id', 'remarks'
            ]);

            $user->update($data);

            return $this->success($user, __('admin.user_updated'), ResponseCode::UPDATED);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 启用或禁用用户登录账号，并可同步调整实名认证状态。
     *
     * updateStatus() 参数说明：
     * - $request 当前 HTTP 请求对象，承载 is_enabled 和 auth_status。
     * - $userId 表示业务用户 ID，对应 user_infos.user_id 和 user_logins.user_id。
     * - is_enabled 表示登录账号启停状态，1=允许登录，0=禁止登录。
     * - auth_status 表示实名认证状态，写入 user_infos.auth_status。
     *
     * @param Request $request 当前 HTTP 请求对象，承载登录启停和实名状态参数。
     * @param int|string $userId 业务用户 ID。
     * @return \Illuminate\Http\JsonResponse 状态更新结果响应。
     */
    public function updateStatus(Request $request, $userId)
    {
        try {
            $user = UserInfo::where('user_id', $userId)->first();
            if (!$user) {
                return $this->error(__('admin.user_not_found'), ResponseCode::USER_NOT_FOUND);
            }

            if ($request->has('is_enabled')) {
                UserLogin::where('user_id', $userId)->update(['is_enabled' => $request->is_enabled]);
            }

            if ($request->has('auth_status')) {
                $user->update(['auth_status' => $request->auth_status]);
            }

            return $this->success([], __('admin.user_status_updated'));
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 审核用户实名认证资料。
     *
     * reviewAuth() 参数说明：
     * - $request 当前 HTTP 请求对象，承载实名认证审核参数。
     * - $userId 表示业务用户 ID，对应 user_auths.user_id。
     * - status 表示审核结果，1=审核通过，2=审核拒绝。
     * - reason 表示审核备注或拒绝原因，写入认证记录 memo 字段。
     *
     * @param Request $request 当前 HTTP 请求对象，承载 status 和 reason。
     * @param int|string $userId 业务用户 ID。
     * @return \Illuminate\Http\JsonResponse 认证审核结果响应。
     */
    public function reviewAuth(Request $request, $userId)
    {
        try {
            $auth = UserAuth::where('user_id', $userId)->first();
            if (!$auth) {
                return $this->error(__('admin.auth_record_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:1,2',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            // status：实名认证审核结果，1=审核通过，2=审核拒绝。
            $status = $request->input('status');
            $reason = $request->input('reason', '');

            $auth->update([
                'status' => $status,
                'memo'   => $reason,
            ]);

            UserInfo::where('user_id', $userId)->update(['auth_status' => $status]);

            return $this->success([], __('admin.auth_review_completed'));
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 注销并软删除用户。
     *
     * destroy() 参数说明：
     * - $userId 表示业务用户 ID，对应 user_infos.user_id。
     * - is_cancelled 表示用户已申请或已执行注销，软删除前先置为 1，便于后台区分注销用户。
     *
     * @param int|string $userId 业务用户 ID。
     * @return \Illuminate\Http\JsonResponse 删除结果响应。
     */
    public function destroy($userId)
    {
        try {
            $user = UserInfo::where('user_id', $userId)->first();
            if (!$user) {
                return $this->error(__('admin.user_not_found'), ResponseCode::USER_NOT_FOUND);
            }

            $user->update(['is_cancelled' => 1]);
            // delete()：若 UserInfo 模型启用了 SoftDeletes，则这里只写入 deleted_at；否则按模型默认删除行为执行。
            $user->delete();

            return $this->success([], __('admin.user_deleted'), ResponseCode::DELETED);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 获取带交易统计的用户列表（从旧项目CustomerController迁移）。
     *
     * listWithStats() 参数说明：
     * - page：当前页码，默认第1页。
     * - per_page/limit：每页数量，兼容Layui表格的limit参数，默认15条。
     * - user_id：业务用户ID，用于精确筛选。
     * - user_name：用户姓名，模糊匹配。
     * - user_status：用户状态，筛选启用/禁用/待审核等状态。
     * - start_date：统计开始日期，格式Y-m-d，默认2024-01-01。
     * - end_date：统计结束日期，格式Y-m-d，默认当前日期。
     *
     * 功能逻辑说明：
     * - 查询用户基础信息（user_infos表）。
     * - 关联用户登录信息（user_logins表）。
     * - 调用UserStatisticsService统计每个用户的交易数据。
     * - 返回当前页数据 + 当前页汇总 + 全部数据汇总（footer）。
     * - 应用数据权限过滤（根据当前登录管理员权限）。
     *
     * @param Request $request 当前HTTP请求对象，承载分页参数和筛选条件。
     * @return \Illuminate\Http\JsonResponse 返回用户列表及统计数据。
     */
    public function listWithStats(Request $request)
    {
        try {
            // 步骤1：获取分页参数
            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', $request->input('limit', 15));

            // 步骤2：获取筛选条件
            $userId = $request->input('user_id');
            $userName = $request->input('user_name');
            $userStatus = $request->input('user_status');
            $startDate = $request->input('start_date', '2024-01-01');
            $endDate = $request->input('end_date', date('Y-m-d'));

            // 步骤3：构建基础查询
            $query = UserInfo::query()
                ->select([
                    'user_infos.user_id',
                    'user_infos.user_name',
                    'user_infos.parent_id',
                    'user_infos.trading_mode',
                    'user_infos.mt4_code',
                    'user_infos.total_funds as mt4_balance',
                    'user_infos.equity as mt4_equity',
                    'user_infos.risk_ratio as mt4MarginLevel',
                    'user_infos.mt4_group',
                    'user_infos.auth_status',
                    'user_infos.created_at as rec_crt_date',
                ])
                ->join('user_logins', 'user_logins.user_id', '=', 'user_infos.user_id')
                ->where('user_logins.is_enabled', 1) // 只查询启用的用户
                ->where('user_infos.account_type', 2); // 只查询普通客户（account_type=2）

            // 步骤4：应用筛选条件
            if ($userId) {
                $query->where('user_infos.user_id', 'like', '%' . $userId . '%');
            }

            if ($userName) {
                $query->where('user_infos.user_name', 'like', '%' . $userName . '%');
            }

            if ($userStatus !== null) {
                $query->where('user_infos.auth_status', $userStatus);
            }

            // 步骤5：应用数据权限过滤
            // 根据当前登录管理员的权限，过滤可见的用户数据
            $currentAdmin = Auth::guard('admin')->user();
            if ($currentAdmin && $currentAdmin->role_id != 1) {
                // 非超级管理员，应用数据权限过滤
                // 这里可以根据实际业务规则添加过滤逻辑
                // 例如：只能看到自己创建的用户，或者指定代理下的用户
            }

            // 步骤6：执行分页查询
            $users = $query->orderByDesc('user_infos.user_id')
                ->paginate($perPage, ['*'], 'page', $page);

            // 步骤7：获取当前页用户ID列表
            $userIds = collect($users->items())->pluck('user_id')->toArray();

            if (empty($userIds)) {
                return $this->success([
                    'data' => [],
                    'count' => 0,
                    'totalRow' => [],
                ], __('admin.user_list_fetched'));
            }

            // 步骤8：批量查询用户交易统计数据
            $statisticsService = new UserStatisticsService();
            $userStatistics = $statisticsService->getBatchUserStatistics($userIds, $startDate, $endDate);

            // 步骤9：合并用户基础信息和统计数据
            $usersData = collect($users->items())->map(function ($user) use ($userStatistics) {
                $userId = $user->user_id;
                $stats = $userStatistics[$userId] ?? [];

                return array_merge([
                    'user_id' => $user->user_id,
                    'user_name' => $user->user_name,
                    'parent_id' => $user->parent_id,
                    'trading_mode' => $user->trading_mode,
                    'mt4_code' => $user->mt4_code,
                    'mt4_balance' => number_format($user->mt4_balance, 2, '.', ''),
                    'mt4_equity' => number_format($user->mt4_equity, 2, '.', ''),
                    'mt4MarginLevel' => number_format($user->mt4MarginLevel, 2, '.', ''),
                    'mt4_group' => $user->mt4_group,
                    'auth_status' => $user->auth_status,
                    'rec_crt_date' => date('Y-m-d H:i:s', $user->rec_crt_date),
                ], $stats);
            })->toArray();

            // 步骤10：查询全部符合条件的用户ID（用于汇总统计）
            $allUserIds = UserInfo::query()
                ->join('user_logins', 'user_logins.user_id', '=', 'user_infos.user_id')
                ->where('user_logins.is_enabled', 1)
                ->where('user_infos.account_type', 2)
                ->when($userId, function ($q) use ($userId) {
                    $q->where('user_infos.user_id', 'like', '%' . $userId . '%');
                })
                ->when($userName, function ($q) use ($userName) {
                    $q->where('user_infos.user_name', 'like', '%' . $userName . '%');
                })
                ->when($userStatus !== null, function ($q) use ($userStatus) {
                    $q->where('user_infos.auth_status', $userStatus);
                })
                ->pluck('user_infos.user_id')
                ->toArray();

            // 步骤11：查询全部数据的汇总统计
            $summaryStatistics = $statisticsService->getSummaryStatistics($allUserIds, $startDate, $endDate);

            // 步骤12：构建汇总行数据（显示在表格底部）
            $totalRow = [
                'user_id' => trans('systemlanguage.total'), // "合计"
                'user_name' => '',
                'mt4MarginLevel' => '',
                'mt4_balance' => $summaryStatistics['search_total_bal'],
                'mt4_equity' => $summaryStatistics['search_total_eqy'],
                'total_yuerj' => $summaryStatistics['search_total_yuerj'],
                'total_yuecj' => $summaryStatistics['search_total_yuecj'],
                'total_net_worth' => $summaryStatistics['search_total_net_worth'],
                'total_comm' => $summaryStatistics['search_total_comm'],
                'total_profit' => $summaryStatistics['search_total_profit'],
                'total_noble_metal' => $summaryStatistics['search_total_noble_metal'],
                'total_for_exca' => $summaryStatistics['search_total_for_exca'],
                'total_crud_oil' => $summaryStatistics['search_total_crud_oil'],
                'total_index' => $summaryStatistics['search_total_index'],
                'total_currency' => $summaryStatistics['search_total_currency'],
                'total_stock' => $summaryStatistics['search_total_stock'],
                'total_volume' => $summaryStatistics['search_total_volume'],
                'total_swaps' => $summaryStatistics['search_total_swaps'],
                'rec_crt_date' => '',
            ];

            // 步骤13：返回数据（兼容Layui表格和普通分页格式）
            return $this->success([
                'data' => $usersData,
                'count' => $users->total(),
                'totalRow' => $totalRow,
            ], __('admin.user_list_fetched'));

        } catch (\Exception $e) {
            \Log::error('UserController.listWithStats error: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return $this->serverErrorResponse();
        }
    }
}
