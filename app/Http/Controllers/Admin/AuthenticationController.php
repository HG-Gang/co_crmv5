<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\Admin;

use App\Constants\ResponseCode;
use App\Models\UserAuth;
use App\Services\AdminDataScopeService;
use App\Support\AuthReviewTransition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 后台实名认证审核控制器。
 *
 * 功能逻辑说明：
 * - 旧项目 `AuthenticationController` 将实名认证拆成待审核、已审核、详情和审核保存多个入口。
 * - 新项目第一阶段以真实表 `user_auths` 与 `user_infos` 为准，先提供待审列表和已审列表。
 * - 审核动作继续复用 `AdminUserController@reviewAuth`，避免在两个控制器里维护重复写状态逻辑。
 * - 列表接口统一接入 `AdminDataScopeService`，不同后台管理员角色只能查看自己数据范围内的用户认证资料。
 *
 * 文件功能：
 * - 实名认证待审/已审列表查询；输入 user_id/user_name/auth_status/start_date/end_date 与分页参数。
 * - 只读列表，不做任何状态写入；审核动作由 AdminUserController@reviewAuth 统一执行。
 *
 * 状态口径（user_auths）：
 * - 待审：id_card_status=1，或 bank_status=1/3（3 与 1 同视为待审，进入默认待审条件）。
 * - 已审：id_card_status=2 且 bank_status=2。
 * - 审核动作写入值：通过=2，拒绝=4（由 reviewAuth 处理）。
 *
 * 适用场景：
 * - 后台"实名认证"列表页；数据范围按 user 维度套用 AdminDataScopeService。
 */
class AuthenticationController extends AdminBaseController
{
    /**
     * 后台数据范围服务。
     *
     * @var AdminDataScopeService
     */
    private $adminDataScopeService;

    /**
     * 构造函数。
     *
     * @param AdminDataScopeService $adminDataScopeService 后台数据范围服务，用于按照管理员角色、绑定代理和用户归属限制可见用户。
     */
    public function __construct(AdminDataScopeService $adminDataScopeService)
    {
        $this->adminDataScopeService = $adminDataScopeService;
    }

    /**
     * 查询待审核认证列表。
     *
     * 参数逻辑说明：
     * - user_id：业务用户 ID，对应 `user_auths.user_id` 和 `user_infos.user_id`。
     * - user_name：业务用户姓名，对应 `user_infos.user_name`，支持模糊筛选。
     * - auth_status：旧审核状态筛选，1=待审核，2=审核未通过；为空时同时返回两类记录。
     * - start_date/end_date：提交日期范围，当前以 `user_auths.created_at` 10 位时间戳过滤。
     * - page/per_page/limit：分页参数，兼容 Layui 默认提交的 `page` 与 `limit`。
     *
     * @param Request $request 当前请求对象，承载筛选条件和分页参数。
     * @return \Illuminate\Http\JsonResponse
     */
    public function pendingList(Request $request)
    {
        if (!$request->user('admin')) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        if ($userIdError = $this->validateUserIdFilter($request)) {
            return $userIdError;
        }

        if ($filterError = $this->validateListFilters($request)) {
            return $filterError;
        }

        try {
            $reviewStatuses = AuthReviewTransition::reviewQueueStatuses($request->input('auth_status', ''));
        } catch (\InvalidArgumentException $exception) {
            return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
        }

        $query = $this->baseAuthQuery($request)
            ->where(function (Builder $where) use ($reviewStatuses) {
                $where->whereIn('user_auths.id_card_status', $reviewStatuses['id_card_statuses'])
                    ->orWhereIn('user_auths.bank_status', $reviewStatuses['bank_statuses']);
            });

        $this->applyFilters($query, $request);

        return $this->success(
            $query->orderByDesc('user_auths.created_at')->paginate($this->perPage($request), ['*'], 'page', $this->page($request)),
            __('admin.auth_pending_fetched')
        );
    }

    /**
     * 查询已审核认证列表。
     *
     * 参数逻辑说明：
     * - user_id：业务用户 ID，用于定位某一个用户认证记录。
     * - user_name：用户姓名，来自 `user_infos.user_name`。
     * - start_date/end_date：审核或更新时间范围，当前使用 `user_auths.updated_at` 10 位时间戳过滤。
     * - page/per_page/limit：分页参数，兼容 Layui 表格。
     *
     * @param Request $request 当前请求对象，承载筛选条件和分页参数。
     * @return \Illuminate\Http\JsonResponse
     */
    public function certifiedList(Request $request)
    {
        if (!$request->user('admin')) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        if ($userIdError = $this->validateUserIdFilter($request)) {
            return $userIdError;
        }

        if ($filterError = $this->validateListFilters($request)) {
            return $filterError;
        }

        $query = $this->baseAuthQuery($request)
            ->where('user_auths.id_card_status', 2)
            ->where('user_auths.bank_status', 2);

        $this->applyFilters($query, $request, 'updated_at');

        return $this->success(
            $query->orderByDesc('user_auths.updated_at')->paginate($this->perPage($request), ['*'], 'page', $this->page($request)),
            __('admin.auth_certified_fetched')
        );
    }

    /**
     * 创建认证资料基础查询。
     *
     * @param Request $request 当前请求对象，用于读取 admin guard 下的管理员并套用数据范围。
     * @return Builder 已关联 `user_infos` 的认证资料查询对象。
     */
    private function baseAuthQuery(Request $request): Builder
    {
        $query = UserAuth::query()
            ->leftJoin('user_infos', 'user_infos.user_id', '=', 'user_auths.user_id')
            ->leftJoin('user_logins', 'user_logins.id', '=', 'user_infos.login_id')
            ->select([
                'user_auths.id',
                'user_auths.user_id',
                'user_infos.user_name',
                'user_infos.parent_id',
                'user_infos.account_type',
                'user_infos.auth_status',
                'user_infos.phone',
                'user_logins.email',
                'user_auths.id_card_no',
                'user_auths.id_card_front',
                'user_auths.id_card_back',
                'user_auths.id_card_status',
                'user_auths.id_card_remarks',
                'user_auths.bank_no',
                'user_auths.bank_no_tmp',
                'user_auths.bank_name',
                'user_auths.bank_name_tmp',
                'user_auths.bank_addr',
                'user_auths.bank_addr_tmp',
                'user_auths.bank_card_img',
                'user_auths.bank_card_back_img',
                'user_auths.bank_card_img_tmp',
                'user_auths.bank_card_back_img_tmp',
                'user_auths.bank_status',
                'user_auths.bank_remarks',
                'user_auths.created_at',
                'user_auths.updated_at',
            ]);

        $this->applyDataScope($query, $request);

        return $query;
    }

    /**
     * 查询单个用户的认证详情。
     *
     * 详情必须复用列表查询和数据范围条件，避免旧详情入口绕过管理员可见范围。
     * 图片字段同时返回原始存储值和可直接访问的 URL，兼容旧表单与新上传路径。
     *
     * @param Request $request 当前请求对象，承载正整数 user_id 和 admin guard 管理员。
     * @return \Illuminate\Http\JsonResponse
     */
    public function detail(Request $request)
    {
        if (! $request->user('admin')) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }

        $validator = Validator::make($request->only('user_id'), [
            'user_id' => ['required', 'integer', 'min:1'],
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $record = $this->baseAuthQuery($request)
            ->where('user_auths.user_id', (int) $request->input('user_id'))
            ->first();

        if (! $record) {
            return $this->error(__('admin.auth_record_not_found'), ResponseCode::DATA_NOT_FOUND);
        }

        $idCardFront = (string) ($record->id_card_front ?? '');
        $idCardBack = (string) ($record->id_card_back ?? '');
        $bankCardImage = (string) ($record->review_bank_img ?? '');
        $bankCardBackImage = (string) ($record->review_bank_back_img ?? '');

        return $this->success([
            'id' => (int) $record->id,
            'user_id' => (int) $record->user_id,
            'user_name' => (string) ($record->user_name ?? ''),
            'phone' => (string) ($record->phone ?? ''),
            'email' => (string) ($record->email ?? ''),
            'account_type' => $record->account_type,
            'auth_status' => $record->auth_status,
            'id_card_no' => (string) ($record->id_card_no ?? ''),
            'id_card_front' => $idCardFront,
            'id_card_back' => $idCardBack,
            'id_card_front_url' => $this->fileUrl($idCardFront),
            'id_card_back_url' => $this->fileUrl($idCardBack),
            'id_card_status' => $record->id_card_status,
            'id_card_remarks' => (string) ($record->id_card_remarks ?? ''),
            'bank_no' => (string) ($record->review_bank_no ?? ''),
            'bank_name' => (string) ($record->review_bank_name ?? ''),
            'bank_addr' => (string) ($record->review_bank_addr ?? ''),
            'bank_card_img' => $bankCardImage,
            'bank_card_back_img' => $bankCardBackImage,
            'bank_card_img_url' => $this->fileUrl($bankCardImage),
            'bank_card_back_img_url' => $this->fileUrl($bankCardBackImage),
            'bank_status' => $record->bank_status,
            'bank_remarks' => (string) ($record->bank_remarks ?? ''),
            'created_at' => $record->created_at,
            'updated_at' => $record->updated_at,
        ], __('admin.auth_detail_fetched'));
    }

    /**
     * 套用后台管理员数据范围。
     *
     * @param Builder $query 认证列表查询对象，已关联 `user_infos` 表。
     * @param Request $request 当前请求对象，用于读取 admin guard 登录用户。
     * @return void
     */
    private function applyDataScope(Builder $query, Request $request): void
    {
        $admin = $request->user('admin');
        if (! $admin) {
            return;
        }

        $this->adminDataScopeService->apply($query, $admin, 'user', 'user_auths.user_id');
    }

    /**
     * 追加公共筛选条件。
     *
     * @param Builder $query 认证列表查询对象。
     * @param Request $request 当前请求对象，用于读取筛选参数。
     * @param string $dateColumn 日期字段名，待审默认按 created_at，已审默认按 updated_at。
     * @return void
     */
    private function applyFilters(Builder $query, Request $request, string $dateColumn = 'created_at'): void
    {
        if ($request->filled('user_id')) {
            $query->where('user_auths.user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('user_name')) {
            $query->where('user_infos.user_name', 'LIKE', '%' . $request->input('user_name') . '%');
        }

        if ($request->filled('start_date')) {
            $query->where('user_auths.' . $dateColumn, '>=', strtotime($request->input('start_date') . ' 00:00:00'));
        }

        if ($request->filled('end_date')) {
            $query->where('user_auths.' . $dateColumn, '<=', strtotime($request->input('end_date') . ' 23:59:59'));
        }
    }

    /**
     * 严格校验认证列表日期范围。
     *
     * 只接受真实存在的 `Y-m-d` 日期；开始日期晚于结束日期时直接失败关闭，
     * 确保查询不会因为 PHP 的宽松 strtotime 解析而扩大数据范围。
     *
     * @param Request $request 当前请求对象。
     * @return \Illuminate\Http\JsonResponse|null
     */
    protected function validateListFilters(Request $request)
    {
        $dates = [];
        foreach (['start_date', 'end_date'] as $field) {
            if (! $request->filled($field)) {
                continue;
            }

            $value = (string) $request->input($field);
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            $dateErrors = \DateTimeImmutable::getLastErrors();
            if ($date === false
                || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
                || $date->format('Y-m-d') !== $value
            ) {
                return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED, [
                    'field' => $field,
                ]);
            }

            $dates[$field] = $date;
        }

        if (isset($dates['start_date'], $dates['end_date'])
            && $dates['start_date'] > $dates['end_date']) {
            return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED, [
                'field' => 'date_range',
            ]);
        }

        return null;
    }

    /**
     * 校验可选的 user_id 筛选参数。
     *
     * @param Request $request 当前请求对象，读取 user_id。
     * @return \Illuminate\Http\JsonResponse|null user_id 非法时返回统一错误响应，否则返回 null。
     */
    private function validateUserIdFilter(Request $request)
    {
        if (!$request->filled('user_id')) {
            return null;
        }

        $validator = Validator::make(['user_id' => $request->input('user_id')], [
            'user_id' => ['integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 将数据库文件路径转换为公开访问路径，绝对 URL 保持原值。
     *
     * @param string $path 数据库存储路径。
     * @return string|null
     */
    private function fileUrl(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        if (strpos($path, '/storage/') === 0) {
            return $path;
        }

        return '/storage/' . ltrim($path, '/');
    }

    /**
     * 获取当前页码。
     *
     * @param Request $request 当前请求对象。
     * @return int 当前页码，最小值为 1。
     */
    private function page(Request $request): int
    {
        return max(1, (int) $request->input('page', 1));
    }

    /**
     * 获取每页条数。
     *
     * @param Request $request 当前请求对象，`limit` 用于兼容 Layui 表格，`per_page` 用于兼容后端接口习惯。
     * @return int 每页条数，限制在 1 到 100 之间。
     */
    private function perPage(Request $request): int
    {
        return max(1, min(100, (int) $request->input('per_page', $request->input('limit', 15))));
    }
}
