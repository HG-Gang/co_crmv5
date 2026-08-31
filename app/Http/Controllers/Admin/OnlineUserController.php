<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 13:00
 */

namespace App\Http\Controllers\Admin;

use App\Constants\ResponseCode;
use App\Models\OperationLog;
use App\Models\UserLogin;
use App\Models\UserOnline;
use App\Services\AdminDataScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

/**
 * 后台在线用户控制器。
 *
 * 文件功能：
 * - 提供在线用户列表查询和强制下线入口。
 *
 * 功能逻辑说明：
 * - 旧项目 `UserLoginOnlineController` 负责查看在线用户，新项目第一阶段基于真实表 `user_onlines` 提供列表和在线记录移除入口。
 * - `user_onlines` 只保存用户 ID、最后活跃时间、IP 和浏览器代理，因此页面通过 `user_infos` 关联补充用户名与账号类型。
 * - 强制下线会删除 `user_onlines` 在线记录、清理前台 SSO 缓存和 `user_logins.jwt_token_id`，让当前 JWT 在 `sso:user:{login_id}` 校验阶段失效。
 *
 * 安全边界：
 * - 列表和强制下线统一按 user_onlines.user_id 套用 AdminDataScopeService 数据范围，越权操作返回 PERMISSION_DENIED。
 * - 当前表没有 session_id 或设备维度字段，强制下线会使该用户全部前台 JWT 失效，无法做到单设备下线。
 */
class OnlineUserController extends AdminBaseController
{
    /**
     * 后台数据范围服务：在线用户列表与强制下线统一按 user_onlines.user_id 套用；
     * 缺失时任何管理员可查看并踢下线数据范围外的用户（强制下线会使该用户全部前台 JWT 失效），越权影响直接作用于登录态。
     *
     * @var AdminDataScopeService
     */
    private $adminDataScopeService;

    /**
     * 构造函数注入后台数据范围服务。
     *
     * @param AdminDataScopeService $adminDataScopeService 数据范围服务，用于按管理员权限限制在线用户列表与强制下线范围。
     */
    public function __construct(AdminDataScopeService $adminDataScopeService)
    {
        $this->adminDataScopeService = $adminDataScopeService;
    }

    /**
     * 查询在线用户列表。
     *
     * 参数逻辑说明：
     * - user_id：业务用户 ID，对应 `user_onlines.user_id`，用于定位某一个前台用户的在线记录。
     * - ip_address：登录或活跃 IP 地址，支持模糊匹配。
     * - start_date/end_date：最后活跃日期范围，转换为 10 位时间戳后过滤 `last_activity`。
     * - page/per_page/limit：分页参数，兼容 Layui 表格默认提交的 `page` 与 `limit`。
     *
     * @param Request $request 当前请求对象，承载筛选条件和分页参数。
     * @return \Illuminate\Http\JsonResponse
     */
    public function onlineUserList(Request $request)
    {
        if ($userIdError = $this->validateUserIdFilter($request)) {
            return $userIdError;
        }

        $admin = $request->user('admin');
        if (!$admin) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $query = UserOnline::query()
            ->leftJoin('user_infos', 'user_infos.user_id', '=', 'user_onlines.user_id')
            ->select([
                'user_onlines.id',
                'user_onlines.user_id',
                'user_infos.user_name',
                'user_infos.account_type',
                'user_onlines.last_activity',
                'user_onlines.ip_address',
                'user_onlines.user_agent',
                'user_onlines.created_at',
                'user_onlines.updated_at',
            ]);

        $query = $this->adminDataScopeService->apply(
            $query,
            $admin,
            'user',
            'user_onlines.user_id'
        );

        $this->applyFilters($query, $request);

        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', $request->input('limit', 15));

        $records = $query
            ->orderByDesc('user_onlines.last_activity')
            ->paginate($perPage, ['*'], 'page', $page);

        return $this->success($records, __('admin.online_users_fetched'));
    }

    /**
     * 移除在线用户记录并写入后台审计日志。
     *
     * 参数逻辑说明：
     * - id 对应 user_onlines.id，只表示当前在线记录主键。
     * - user_onlines.user_id 是业务用户编号；前台 JWT 的 sub 是 user_logins.id，因此必须先按 user_id 找到登录记录再清理 SSO。
     * - 当前表仍没有 session_id 或设备维度字段，因此本接口会使该用户当前前台 JWT 失效，但还不能做到单设备下线。
     *
     * @param Request $request 当前后台请求对象，用于读取管理员和来源 IP。
     * @param int $id 在线记录 ID。
     * @return \Illuminate\Http\JsonResponse
     */
    public function forceOffline(Request $request, int $id)
    {
        $online = UserOnline::query()->find($id);

        if (!$online) {
            return $this->error(__('admin.online_user_not_found'), ResponseCode::DATA_NOT_FOUND);
        }

        $admin = $request->user('admin');
        if (!$admin || !$this->adminDataScopeService->canAccessUser($admin, $online->user_id, 'user')) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        DB::transaction(function () use ($request, $online) {
            $this->writeForceOfflineOperationLog($request, $online);
            $this->invalidateFrontUserSession($online);
            $online->delete();
        });

        return $this->success([], __('admin.online_user_forced_offline'), ResponseCode::DELETED);
    }

    /**
     * 追加在线用户列表筛选条件。
     *
     * @param Builder $query 在线用户查询对象，用于追加 where 条件。
     * @param Request $request 当前请求对象，用于读取筛选参数。
     * @return void
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('user_id')) {
            $query->where('user_onlines.user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('ip_address')) {
            $query->where('user_onlines.ip_address', 'LIKE', '%' . $request->input('ip_address') . '%');
        }

        if ($request->filled('start_date')) {
            $query->where('user_onlines.last_activity', '>=', strtotime($request->input('start_date') . ' 00:00:00'));
        }

        if ($request->filled('end_date')) {
            $query->where('user_onlines.last_activity', '<=', strtotime($request->input('end_date') . ' 23:59:59'));
        }
    }

    /**
     * 校验 user_id 列表筛选参数，必须为整数。
     *
     * @param Request $request 当前请求对象，读取 user_id。
     * @return \Illuminate\Http\JsonResponse|null 非法时返回参数错误响应，未传或合法时返回 null。
     */
    private function validateUserIdFilter(Request $request)
    {
        if (!$request->filled('user_id')) {
            return null;
        }

        $validator = Validator::make(['user_id' => $request->input('user_id')], [
            'user_id' => 'integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 写入在线记录移除审计日志。
     *
     * 只写 operation_logs，不负责清理 SSO 缓存或删除在线记录，便于事务内按顺序调用。
     *
     * @param Request $request 当前后台请求对象。
     * @param UserOnline $online 即将被移除的在线记录。
     * @return void
     */
    private function writeForceOfflineOperationLog(Request $request, UserOnline $online): void
    {
        $admin = $request->user('admin');

        OperationLog::create([
            'admin_id' => $admin ? (int) $admin->id : 0,
            'admin_name' => $admin ? (string) $admin->username : '',
            'target_user_id' => (int) $online->user_id,
            'order_no' => 'online_user:' . $online->id,
            'content' => sprintf(
                'Force offline user_id:%s; online_record_id:%s; ip_address:%s; last_activity:%s',
                (int) $online->user_id,
                (int) $online->id,
                (string) $online->ip_address,
                (int) $online->last_activity
            ),
            'ip' => $request->ip() ?: '',
            'action_type' => 3,
        ]);
    }

    /**
     * 让被强制下线用户的当前前台 JWT 失效。
     *
     * 参数逻辑说明：
     * - $online->user_id 来自 user_onlines，是业务用户编号。
     * - user_logins.id 才是 JWT sub 和 sso:user:{sub} 缓存键中的登录主体编号。
     * - jwt_token_id 是历史兼容字段，清空后可避免后续维护者误判该账号仍有当前有效 token。
     *
     * @param UserOnline $online 即将删除的在线记录。
     * @return void
     */
    private function invalidateFrontUserSession(UserOnline $online): void
    {
        // 先按业务 user_id 找到登录记录：前台 JWT 的 sub 是 user_logins.id，sso 缓存键依赖它。
        $userLogin = UserLogin::where('user_id', (int) $online->user_id)->first();

        if (!$userLogin) {
            return;
        }

        // 清空 sso 缓存与 jwt_token_id 后，该用户当前前台 JWT 在 sso:user:{login_id} 校验阶段失效。
        Cache::forget('sso:user:' . $userLogin->id);
        $userLogin->forceFill(['jwt_token_id' => ''])->save();
    }
}
