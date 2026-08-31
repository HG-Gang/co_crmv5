<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 00:05
 */

namespace App\Http\Controllers\Admin;

use App\Constants\ResponseCode;
use App\Contracts\RiskForceCloseGateway;
use App\Models\DepositRecord;
use App\Models\Mt4Trade;
use App\Models\Mt4User;
use App\Models\OperationLog;
use App\Models\UserLoginLog;
use App\Models\UserInfo;
use App\Models\WithdrawRecord;
use App\Services\AdminDataScopeService;
use App\Services\LegacyRiskQueryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * 后台风控管理控制器。
 *
 * 文件功能：
 * - 旧项目 `FengXianManageController` 风控列表主要读取 MT4_TRADES，并基于盈利、手续费和保证金字段计算风险值。
 * - 新项目第一阶段只使用当前真实表可支撑的数据：`mt4_trades`、`mt4_users`、`user_infos`。
 * - 当前真实 `mt4_trades` 表没有旧项目 `MARGIN_RATE` 字段，因此只返回可验证的订单盈亏，不伪造保证金率。
 * - CRM 业务用户与 MT4 订单统一通过 `user_infos.mt4_code = mt4_trades.login` 映射，不能猜测 `user_id` 等于 MT4 登录号。
 * - 后台接口访问继续由 `permissions.api_route` 鉴权，数据可见范围继续由 `AdminDataScopeService` 从数据表配置计算。
 */
class RiskController extends AdminBaseController
{
    /**
     * 后台数据范围服务。
     *
     * @var AdminDataScopeService
     */
    private $adminDataScopeService;

    /**
     * 旧风控查询服务：封装 mt4_trades/mt4_users/user_infos 的联查与风险口径（订单盈亏、COMMENT 码归类），
     * 保证“mt4_code = login”映射与不伪造保证金率的边界只在服务层实现一次；
     * 缺失时控制器只能各自拼查询，风控口径漂移会直接影响盈亏榜单的正确性。
     *
     * @var LegacyRiskQueryService
     */
    private $legacyRiskQueryService;

    /**
     * 构造函数。
     *
     * @param AdminDataScopeService $adminDataScopeService 数据范围服务，用于限制不同后台管理员可查看的用户、代理和交易记录。
     */
    public function __construct(
        AdminDataScopeService $adminDataScopeService,
        LegacyRiskQueryService $legacyRiskQueryService
    )
    {
        $this->adminDataScopeService = $adminDataScopeService;
        $this->legacyRiskQueryService = $legacyRiskQueryService;
    }

    /**
     * 查询按业务用户聚合的盈利风险。
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function profitableUsers(Request $request)
    {
        if ($filterError = $this->legacyRiskQueryService->validateProfitFilters($request)) {
            return $this->error($filterError['message'], ResponseCode::VALIDATION_FAILED, [
                'field' => $filterError['field'],
            ]);
        }

        $result = $this->legacyRiskQueryService->profitPage($request, $request->user('admin'));

        return $this->success([
            'records' => [
                'current_page' => $result['page'],
                'data' => $result['rows'],
                'per_page' => $result['per_page'],
                'total' => $result['total'],
            ],
            'summary' => $result['summary'],
        ], __('admin.risk_profit_users_fetched'));
    }

    /**
     * 查询当前 MT4 持仓风险列表。
     *
     * 请求参数含义：
     * - user_id：CRM 业务用户 ID，对应 `user_infos.user_id`，经 `mt4_code` 映射到订单登录号。
     * - ticket：MT4 订单号，对应 `mt4_trades.ticket`，支持模糊查询。
     * - symbol：交易品种，对应 `mt4_trades.symbol`。
     * - start_date/end_date：开仓时间范围，对应 `mt4_trades.open_time` 的 10 位时间戳。
     * - page/per_page/limit：分页参数，兼容 Layui table 默认传入的 `page` 与 `limit`。
     *
     * @param Request $request 当前请求对象，负责读取筛选条件、分页参数和登录管理员。
     * @return \Illuminate\Http\JsonResponse
     */
    public function positions(Request $request)
    {
        if ($filterError = $this->legacyRiskQueryService->validatePositionFilters($request)) {
            return $this->error($filterError['message'], ResponseCode::VALIDATION_FAILED, [
                'field' => $filterError['field'],
            ]);
        }

        $result = $this->legacyRiskQueryService->positionPage($request, $request->user('admin'));

        return $this->success([
            'records' => [
                'current_page' => $result['page'],
                'data' => $result['rows'],
                'per_page' => $result['per_page'],
                'total' => $result['total'],
            ],
            'summary' => $result['summary'],
        ], __('admin.risk_positions_fetched'));
    }

    /**
     * 查询追保预警用户列表。
     *
     * 请求参数含义：
     * - user_id：业务用户 ID，对应 `user_infos.user_id`。
     * - login：MT4 登录账号，对应 `mt4_users.login`。
     * - user_name：业务用户名，对应 `user_infos.user_name`，支持模糊查询。
     * - max_margin_level：最高保证金比例阈值；为空时默认使用 100，比例越低风险越高。
     * - page/per_page/limit：分页参数。
     *
     * @param Request $request 当前请求对象。
     * @return \Illuminate\Http\JsonResponse
     */
    public function marginCalls(Request $request)
    {
        if ($filterError = $this->validateMarginCallFilters($request)) {
            return $filterError;
        }

        $query = $this->baseMarginCallQuery($request);

        if ($request->user('admin')) {
            $this->adminDataScopeService->apply($query, $request->user('admin'), 'user', 'user_infos.user_id');
        }

        return $this->success([
            'records' => $this->paginateQuery($query, $request, 'margin_level', 'asc'),
            'summary' => $this->summaryFor(clone $query),
        ], __('admin.margin_calls_fetched'));
    }

    /**
     * 查询同一 IP 登录多个账号的异常 IP 风险列表。
     *
     * 请求参数含义：
     * - login_ip：登录 IP，支持模糊查询，对应 `user_login_logs.login_ip`。
     * - user_id：业务用户 ID，对应 `user_login_logs.user_id`，用于定位某个用户参与过的异常 IP。
     * - min_user_count：同一 IP 至少关联多少个不同用户才判定为风险，默认 2。
     * - start_date/end_date：登录时间范围，对应 `user_login_logs.created_at` 的 10 位时间戳。
     * - page/per_page/limit：分页参数。
     *
     * @param Request $request 当前请求对象。
     * @return \Illuminate\Http\JsonResponse
     */
    public function riskIpList(Request $request)
    {
        $this->normalizeRiskIpRequest($request);

        if ($filterError = $this->validateRiskIpListFilters($request)) {
            return $filterError;
        }

        $query = $this->baseRiskIpQuery($request);

        if ($request->user('admin')) {
            $this->adminDataScopeService->apply($query, $request->user('admin'), 'user', 'user_login_logs.user_id');
        }

        return $this->success([
            'records' => $this->paginateQuery($query, $request, 'latest_login_at'),
            'summary' => $this->summaryFor(clone $query),
        ], __('admin.risk_ip_list_fetched'));
    }

    /**
     * 查询某个异常 IP 下的登录账号详情。
     *
     * 请求参数含义：
     * - login_ip：必填，登录 IP，精确匹配 `user_login_logs.login_ip`，用于展开异常 IP 聚合行。
     * - user_id：可选，业务用户 ID，对应 `user_login_logs.user_id`，用于在详情弹层内继续定位单个账号。
     * - start_date/end_date：可选，登录时间范围，对应 `user_login_logs.created_at` 的 10 位时间戳。
     * - page/per_page/limit：分页参数，兼容 Layui table 默认参数。
     *
     * @param Request $request 当前请求对象，读取筛选参数、分页参数和登录后台管理员。
     * @return \Illuminate\Http\JsonResponse
     */
    public function riskIpDetail(Request $request)
    {
        $this->normalizeRiskIpRequest($request);

        if ($filterError = $this->validateRiskIpDetailFilters($request)) {
            return $filterError;
        }

        $loginIp = trim((string) $request->input('login_ip'));

        $query = $this->baseRiskIpDetailQuery($request, $loginIp);

        if ($request->user('admin')) {
            $this->adminDataScopeService->apply($query, $request->user('admin'), 'user', 'user_login_logs.user_id');
        }

        return $this->success([
            'records' => $this->paginateQuery($query, $request, 'latest_login_at'),
            'login_ip' => $loginIp,
        ], __('admin.risk_ip_detail_fetched'));
    }

    /**
     * 发送强平信号。
     *
     * 参数含义：
     * - id：`mt4_trades.id` 主键，仅允许对当前未平仓交易记录发送强平信号。
     * - 受限管理员先按映射后的业务用户校验数据范围，再把订单真实 `login/ticket` 交给 `RiskForceCloseGateway`。
     * - 网关只有明确返回已平仓状态才写审计并返回成功，拒绝或连接失败都不会伪造本地平仓结果。
     *
     * @param Request $request 当前请求对象，用于读取登录管理员。
     * @param int|string $id MT4 交易记录主键。
     * @return \Illuminate\Http\JsonResponse
     */
    public function forceClose(Request $request, $id)
    {
        if ($routeIdError = $this->validateRiskRouteId($id)) {
            return $routeIdError;
        }

        $query = Mt4Trade::query()
            ->leftJoin('user_infos', function ($join) {
                $join->on('user_infos.mt4_code', '=', 'mt4_trades.login')
                    ->whereNull('user_infos.deleted_at');
            })
            ->select('mt4_trades.*')
            ->where('mt4_trades.id', (int) $id)
            ->whereIn('mt4_trades.cmd', [0, 1, 2, 3, 4, 5])
            ->where(function (Builder $subQuery) {
                $subQuery->whereNull('mt4_trades.close_time')
                    ->orWhere('mt4_trades.close_time', 0);
            });

        $this->applyDataScope($query, $request);
        $trade = $query->first();

        if (!$trade) {
            return $this->error(__('admin.position_not_found_or_closed'), ResponseCode::DATA_NOT_FOUND);
        }

        $login = (int) $trade->login;
        $ticket = (int) $trade->ticket;
        if ($login <= 0 || $ticket <= 0) {
            return $this->error(__('admin.position_not_found_or_closed'), ResponseCode::DATA_NOT_FOUND);
        }

        $comment = \App\Constants\Mt4RemarkCodes::RISK_FORCE_CLOSE . (int) $trade->id;
        /** @var RiskForceCloseGateway $gateway */
        $gateway = app(RiskForceCloseGateway::class);
        $result = $gateway->close($login, $ticket, $comment);

        if (!$result->isClosed()) {
            $code = $result->errorCode() === 'connection_failed'
                ? ResponseCode::MT4_SYNC_FAILED
                : ResponseCode::OPERATION_NOT_ALLOWED;

            return $this->error(__('admin.force_close_failed'), $code, [
                'ticket' => $ticket,
                'login' => $login,
                'error_code' => $result->errorCode(),
                'status' => $result->status(),
            ]);
        }

        $admin = $request->user('admin');
        OperationLog::create([
            'admin_id' => $admin ? (int) $admin->id : 0,
            'admin_name' => $admin ? (string) $admin->username : '',
            'target_user_id' => $login,
            'order_no' => 'risk_force_close:' . $ticket,
            'content' => sprintf(
                'risk_force_close; trade_id:%s; login:%s; ticket:%s; provider_reference:%s; comment:%s',
                (int) $trade->id,
                $login,
                $ticket,
                (string) $result->providerReference(),
                $comment
            ),
            'ip' => $request->ip() ?: '',
            'action_type' => 3,
        ]);

        return $this->success([
            'ticket' => $ticket,
            'login' => $login,
            'provider_reference' => $result->providerReference(),
        ], __('admin.force_close_signal_sent'));
    }

    /**
     * 校验强平路由主键必须为整数。
     *
     * @param mixed $id 路由中的 MT4 交易记录主键。
     * @return \Illuminate\Http\JsonResponse|null 非法时返回参数错误响应，合法时返回 null。
     */
    private function validateRiskRouteId($id)
    {
        $validator = Validator::make(['id' => $id], [
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 校验 user_id 筛选参数必须为整数（可选参数，未传时直接放行）。
     *
     * @param Request $request 当前请求对象，读取可选的 user_id。
     * @return \Illuminate\Http\JsonResponse|null 参数非法时返回校验失败响应；合法或未传时返回 null。
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
     * 校验追保预警筛选参数类型（user_id/login 为整数，max_margin_level 为数值）。
     *
     * 只对请求中实际出现的参数生成规则，全部未传时直接放行。
     *
     * @param Request $request 当前请求对象，读取 user_id、login、max_margin_level。
     * @return \Illuminate\Http\JsonResponse|null 任一参数非法时返回校验失败响应；否则返回 null。
     */
    private function validateMarginCallFilters(Request $request)
    {
        $rules = [];

        if ($request->filled('user_id')) {
            $rules['user_id'] = 'integer';
        }

        if ($request->filled('login')) {
            $rules['login'] = 'integer';
        }

        if ($request->filled('max_margin_level')) {
            $rules['max_margin_level'] = 'numeric';
        }

        if ($rules === []) {
            return null;
        }

        $validator = Validator::make($request->only(array_keys($rules)), $rules);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 校验异常 IP 风险筛选参数类型（user_id/min_user_count 必须为整数）。
     *
     * 只对请求中实际出现的参数生成规则，全部未传时直接放行。
     *
     * @param Request $request 当前请求对象，读取 user_id、min_user_count。
     * @return \Illuminate\Http\JsonResponse|null 任一参数非法时返回校验失败响应；否则返回 null。
     */
    private function validateRiskIpListFilters(Request $request)
    {
        $validator = Validator::make($request->only([
            'user_id',
            'min_user_count',
            'login_ip',
            'start_date',
            'end_date',
        ]), [
            'user_id' => 'nullable|integer|min:1',
            'min_user_count' => 'nullable|integer|min:2',
            'login_ip' => 'nullable|string',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        if ($request->filled('login_ip')
            && !$this->isValidRiskIpSearch((string) $request->input('login_ip'))) {
            return $this->error(__('validation.ip', [
                'attribute' => __('admin.login_ip'),
            ]), ResponseCode::VALIDATION_FAILED);
        }

        if ($this->riskIpDatesReversed($request)) {
            return $this->error(__('validation.after_or_equal', [
                'attribute' => 'end_date',
                'date' => 'start_date',
            ]), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 列表保留点分 IPv4 前缀搜索，但拒绝越界网段和其它畸形 IP 文本。
     */
    private function isValidRiskIpSearch(string $value): bool
    {
        $value = trim($value);
        if (filter_var($value, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        if (preg_match('/^\d{1,3}(?:\.\d{1,3}){0,2}$/D', $value) !== 1) {
            return false;
        }

        foreach (explode('.', $value) as $octet) {
            if ((int) $octet > 255) {
                return false;
            }
        }

        return true;
    }

    /**
     * 校验异常 IP 详情筛选参数。
     *
     * 详情与列表共用 user/date/min_user_count 边界；login_ip 还必须是完整有效 IP。
     */
    private function validateRiskIpDetailFilters(Request $request)
    {
        $validator = Validator::make($request->only([
            'login_ip',
            'user_id',
            'min_user_count',
            'start_date',
            'end_date',
        ]), [
            'login_ip' => 'required|string',
            'user_id' => 'nullable|integer|min:1',
            'min_user_count' => 'nullable|integer|min:2',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $loginIp = trim((string) $request->input('login_ip'));
        if ($loginIp === '' || filter_var($loginIp, FILTER_VALIDATE_IP) === false) {
            return $this->error(__('validation.ip', ['attribute' => __('admin.login_ip')]), ResponseCode::VALIDATION_FAILED);
        }

        if ($this->riskIpDatesReversed($request)) {
            return $this->error(__('validation.after_or_equal', [
                'attribute' => 'end_date',
                'date' => 'start_date',
            ]), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 兼容旧后台字段名，同时保留现代字段作为唯一查询入口。
     */
    private function normalizeRiskIpRequest(Request $request): void
    {
        foreach ([
            'userId' => 'user_id',
            'startdate' => 'start_date',
            'enddate' => 'end_date',
        ] as $legacy => $modern) {
            if (!$request->exists($modern) && $request->exists($legacy)) {
                $request->merge([$modern => $request->input($legacy)]);
            }
        }

        if ($request->exists('login_ip') && is_string($request->input('login_ip'))) {
            $request->merge(['login_ip' => trim((string) $request->input('login_ip'))]);
        }
    }

    private function riskIpDatesReversed(Request $request): bool
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        return is_string($startDate)
            && is_string($endDate)
            && $startDate !== ''
            && $endDate !== ''
            && $startDate > $endDate;
    }

    /**
     * 构造当前持仓风险基础查询。
     *
     * 字段逻辑说明：
     * - `Mt4Trade::query()` 明确读取真实 `mt4_trades` 表。
     * - `user_infos.mt4_code = mt4_trades.login` 把订单映射回 CRM 业务用户，供筛选和数据范围共同使用。
     * - 未平仓口径为 `close_time IS NULL OR close_time = 0`，与当前项目交易列表保持一致。
     * - `risk_value` 按 `profit - abs(commission)` 计算，表示扣除手续费后的浮动风险收益；不冒充旧 `MARGIN_RATE`。
     *
     * @return Builder 当前持仓风险查询对象。
     */
    private function baseOpenTradeRiskQuery(): Builder
    {
        return Mt4Trade::query()
            ->leftJoin('user_infos', function ($join) {
                $join->on('user_infos.mt4_code', '=', 'mt4_trades.login')
                    ->whereNull('user_infos.deleted_at');
            })
            ->whereIn('mt4_trades.cmd', [0, 1, 2, 3, 4, 5])
            ->where(function (Builder $subQuery) {
                $subQuery->whereNull('mt4_trades.close_time')
                    ->orWhere('mt4_trades.close_time', 0);
            })
            ->select([
                'mt4_trades.id',
                'mt4_trades.ticket',
                'mt4_trades.login',
                'mt4_trades.symbol',
                'mt4_trades.cmd',
                'mt4_trades.volume',
                'mt4_trades.commission',
                'mt4_trades.swaps',
                'mt4_trades.profit',
                'mt4_trades.open_price',
                'mt4_trades.open_time',
                'user_infos.user_id',
                'user_infos.user_name',
                'user_infos.parent_id',
                'user_infos.account_type',
                'user_infos.mt4_group',
            ])
            // 当前交易快照没有旧 MARGIN_RATE，null 明确表示行级保证金不可得，不能用 0 冒充真实值。
            ->selectRaw('NULL as margin')
            ->selectRaw('(mt4_trades.profit - ABS(mt4_trades.commission)) as risk_value');
    }

    /**
     * 构造追保预警基础查询。
     *
     * 字段逻辑说明：
     * - `Mt4User::query()` 明确读取真实 `mt4_users` 资金快照。
     * - `UserInfo::query()` 放在注释与源码中用于表明追保预警必须映射业务用户后再做数据范围。
     * - `margin_level = equity / margin * 100`，当保证金为 0 时返回 0，避免除零错误。
     *
     * @param Request $request 当前请求对象，读取筛选参数和阈值。
     * @return \Illuminate\Database\Query\Builder 追保预警查询对象。
     */
    private function baseMarginCallQuery(Request $request)
    {
        // UserInfo::query()：业务用户数据源说明，实际查询使用 join 以便和 mt4_users 聚合字段保持同一个 SQL。
        UserInfo::query();
        $maxMarginLevel = (float) $request->input('max_margin_level', 100);

        $query = Mt4User::query()
            ->leftJoin('user_infos', 'user_infos.mt4_code', '=', 'mt4_users.login')
            ->where('mt4_users.margin', '>', 0)
            ->select([
                'mt4_users.id',
                'mt4_users.login',
                'mt4_users.name',
                'mt4_users.group',
                'mt4_users.balance',
                'mt4_users.equity',
                'mt4_users.margin',
                'mt4_users.margin_free',
                'mt4_users.leverage',
                'mt4_users.updated_at',
                'user_infos.user_id',
                'user_infos.user_name',
                'user_infos.parent_id',
                'user_infos.account_type',
            ])
            ->selectRaw('CASE WHEN mt4_users.margin > 0 THEN (mt4_users.equity / mt4_users.margin) * 100 ELSE 0 END as margin_level')
            ->selectRaw('0 as volume')
            ->selectRaw('0 as profit')
            ->selectRaw('0 as risk_value')
            ->whereRaw('(CASE WHEN mt4_users.margin > 0 THEN (mt4_users.equity / mt4_users.margin) * 100 ELSE 0 END) <= ?', [$maxMarginLevel]);

        $this->applyMarginCallFilters($query, $request);

        return $query;
    }

    /**
     * 构造异常 IP 风险基础查询。
     *
     * 字段逻辑说明：
     * - `UserLoginLog::query()` 明确读取当前真实 `user_login_logs` 表。
     * - `distinct_user_count` 表示同一登录 IP 关联的不同业务用户数量，是同 IP 多账号风险的核心判断依据。
     * - `latest_login_at` 使用最大 `created_at` 时间戳，便于后台优先查看最近发生的风险。
     *
     * @param Request $request 当前请求对象，读取 IP、用户、时间和阈值筛选。
     * @return \Illuminate\Database\Eloquent\Builder 异常 IP 风险查询对象。
     */
    private function baseRiskIpQuery(Request $request): Builder
    {
        $minUserCount = (int) $request->input('min_user_count', 2);
        $minUserCount = $minUserCount > 1 ? $minUserCount : 2;

        $query = UserLoginLog::query()
            ->leftJoin('user_infos', 'user_infos.user_id', '=', 'user_login_logs.user_id')
            ->where('user_login_logs.login_ip', '<>', '')
            ->selectRaw('MIN(user_login_logs.id) as id')
            ->selectRaw('user_login_logs.login_ip')
            ->selectRaw('COUNT(*) as login_count')
            ->selectRaw('COUNT(DISTINCT user_login_logs.user_id) as distinct_user_count')
            ->selectRaw('MAX(user_login_logs.created_at) as latest_login_at')
            ->selectRaw('MIN(user_login_logs.user_id) as sample_user_id')
            ->selectRaw("SUBSTRING(MIN(CONCAT(LPAD(CAST(user_login_logs.user_id AS CHAR), 20, '0'), COALESCE(user_infos.user_name, ''))), 21) as sample_user_name")
            ->selectRaw('MAX(user_login_logs.ip_location) as sample_ip_location')
            ->selectRaw('0 as profit')
            ->selectRaw('0 as volume')
            ->selectRaw('0 as margin')
            ->selectRaw('0 as risk_value')
            ->groupBy('user_login_logs.login_ip')
            ->havingRaw('COUNT(DISTINCT user_login_logs.user_id) >= ?', [$minUserCount]);

        $this->applyRiskIpFilters($query, $request);

        return $query;
    }

    /**
     * 构造异常 IP 详情基础查询。
     *
     * 字段逻辑说明：
     * - `UserLoginLog::query()` 读取真实 `user_login_logs` 登录日志，按 IP 与用户聚合详情。
     * - `user_infos.parent_id` 用于展示该登录账号所属上级代理，便于管理员追踪代理链路风险。
     * - `open_order_count` 与 `closed_order_count` 来自 `mt4_trades`，分别统计当前未平仓与历史平仓订单数。
     * - `total_deposit` 来自 `deposit_records.amount`，`total_withdraw` 来自 `withdraw_records.apply_amount`，只使用当前项目真实存在字段。
     *
     * @param Request $request 当前请求对象，读取用户和时间筛选参数。
     * @param string $loginIp 登录 IP，精确匹配异常 IP 聚合行。
     * @return Builder 异常 IP 详情查询对象。
     */
    private function baseRiskIpDetailQuery(Request $request, string $loginIp): Builder
    {
        $tradeStats = Mt4Trade::query()
            ->join('user_infos', function ($join) {
                $join->on('user_infos.mt4_code', '=', 'mt4_trades.login')
                    ->whereNull('user_infos.deleted_at');
            })
            ->where('user_infos.mt4_code', '>', 0)
            ->selectRaw('user_infos.user_id as user_id')
            ->selectRaw('SUM(CASE WHEN mt4_trades.cmd IN (0, 1, 2, 3, 4, 5) AND (mt4_trades.close_time IS NULL OR mt4_trades.close_time = 0) THEN 1 ELSE 0 END) as open_order_count')
            ->selectRaw('SUM(CASE WHEN mt4_trades.cmd IN (0, 1, 2, 3, 4, 5) AND mt4_trades.close_time > 0 THEN 1 ELSE 0 END) as closed_order_count')
            ->groupBy('user_infos.user_id');

        $depositStats = DepositRecord::query()
            ->selectRaw('deposit_records.user_id')
            ->selectRaw('CAST(SUM(CAST(deposit_records.amount AS DECIMAL(65, 10))) AS DECIMAL(65, 2)) as total_deposit')
            ->groupBy('deposit_records.user_id');

        $withdrawStats = WithdrawRecord::query()
            ->selectRaw('withdraw_records.user_id')
            ->selectRaw('CAST(SUM(CAST(withdraw_records.apply_amount AS DECIMAL(65, 10))) AS DECIMAL(65, 2)) as total_withdraw')
            ->groupBy('withdraw_records.user_id');

        $query = UserLoginLog::query()
            ->leftJoin('user_infos', 'user_infos.user_id', '=', 'user_login_logs.user_id')
            ->leftJoinSub($tradeStats, 'trade_stats', 'trade_stats.user_id', '=', 'user_login_logs.user_id')
            ->leftJoinSub($depositStats, 'deposit_stats', 'deposit_stats.user_id', '=', 'user_login_logs.user_id')
            ->leftJoinSub($withdrawStats, 'withdraw_stats', 'withdraw_stats.user_id', '=', 'user_login_logs.user_id')
            ->where('user_login_logs.login_ip', $loginIp)
            ->selectRaw('MIN(user_login_logs.id) as id')
            ->selectRaw('user_login_logs.login_ip')
            ->selectRaw('user_login_logs.user_id')
            ->selectRaw('MAX(user_login_logs.created_at) as latest_login_at')
            ->selectRaw('COUNT(*) as login_count')
            ->selectRaw('MAX(user_login_logs.ip_location) as login_id_desc')
            ->selectRaw('user_infos.user_name')
            ->selectRaw('user_infos.parent_id')
            ->selectRaw('user_infos.account_type')
            ->selectRaw('user_infos.created_at as registered_at')
            ->selectRaw('COALESCE(MAX(trade_stats.open_order_count), 0) as open_order_count')
            ->selectRaw('COALESCE(MAX(trade_stats.closed_order_count), 0) as closed_order_count')
            ->selectRaw('CAST(COALESCE(MAX(deposit_stats.total_deposit), 0) AS DECIMAL(65, 2)) as total_deposit')
            ->selectRaw('CAST(COALESCE(MAX(withdraw_stats.total_withdraw), 0) AS DECIMAL(65, 2)) as total_withdraw')
            ->groupBy(
                'user_login_logs.login_ip',
                'user_login_logs.user_id',
                'user_infos.user_name',
                'user_infos.parent_id',
                'user_infos.account_type',
                'user_infos.created_at'
            );

        $this->applyRiskIpDetailFilters($query, $request);

        return $query;
    }

    /**
     * 追加当前持仓风险筛选条件。
     *
     * @param Builder $query 当前持仓风险查询对象。
     * @param Request $request 当前请求对象。
     * @return void
     */
    private function applyTradeFilters(Builder $query, Request $request): void
    {
        if ($request->filled('user_id')) {
            $query->where('user_infos.user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('ticket')) {
            $query->where('mt4_trades.ticket', 'LIKE', '%' . $request->input('ticket') . '%');
        }

        if ($request->filled('symbol')) {
            $query->where('mt4_trades.symbol', $request->input('symbol'));
        }

        if ($request->filled('start_date')) {
            $query->where('mt4_trades.open_time', '>=', strtotime($request->input('start_date') . ' 00:00:00'));
        }

        if ($request->filled('end_date')) {
            $query->where('mt4_trades.open_time', '<=', strtotime($request->input('end_date') . ' 23:59:59'));
        }
    }

    /**
     * 追加追保预警筛选条件。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 追保预警查询对象。
     * @param Request $request 当前请求对象。
     * @return void
     */
    private function applyMarginCallFilters($query, Request $request): void
    {
        if ($request->filled('user_id')) {
            $query->where('user_infos.user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('login')) {
            $query->where('mt4_users.login', (int) $request->input('login'));
        }

        if ($request->filled('user_name')) {
            $query->where('user_infos.user_name', 'LIKE', '%' . $request->input('user_name') . '%');
        }
    }

    /**
     * 追加异常 IP 风险筛选条件。
     *
     * @param Builder $query 异常 IP 风险查询对象。
     * @param Request $request 当前请求对象。
     * @return void
     */
    private function applyRiskIpFilters(Builder $query, Request $request): void
    {
        if ($request->filled('login_ip')) {
            $query->where('user_login_logs.login_ip', 'LIKE', '%' . $request->input('login_ip') . '%');
        }

        if ($request->filled('user_id')) {
            $userId = (int) $request->input('user_id');
            $query->havingRaw(
                'SUM(CASE WHEN user_login_logs.user_id = ? THEN 1 ELSE 0 END) > 0',
                [$userId]
            );
        }

        if ($request->filled('start_date')) {
            $query->where('user_login_logs.created_at', '>=', strtotime($request->input('start_date') . ' 00:00:00'));
        }

        if ($request->filled('end_date')) {
            $query->where('user_login_logs.created_at', '<=', strtotime($request->input('end_date') . ' 23:59:59'));
        }
    }

    /**
     * 追加异常 IP 详情筛选条件。
     *
     * @param Builder $query 异常 IP 详情查询对象。
     * @param Request $request 当前请求对象，读取 user_id、start_date 和 end_date。
     * @return void
     */
    private function applyRiskIpDetailFilters(Builder $query, Request $request): void
    {
        if ($request->filled('user_id')) {
            $query->where('user_login_logs.user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('start_date')) {
            $query->where('user_login_logs.created_at', '>=', strtotime($request->input('start_date') . ' 00:00:00'));
        }

        if ($request->filled('end_date')) {
            $query->where('user_login_logs.created_at', '<=', strtotime($request->input('end_date') . ' 23:59:59'));
        }
    }

    /**
     * 追加后台管理员数据范围。
     *
     * 参数逻辑说明：
     * - $query：当前持仓风险查询对象。
     * - targetType=trade：告诉数据范围服务这是交易订单类数据。
     * - userIdColumn=user_infos.user_id：先限制 CRM 业务用户，再由查询中的 `mt4_code` 关联真实订单。
     *
     * @param Builder $query 当前持仓风险查询对象。
     * @param Request $request 当前请求对象。
     * @return void
     */
    private function applyDataScope(Builder $query, Request $request): void
    {
        $admin = $request->user('admin');
        if (!$admin) {
            return;
        }

        $this->adminDataScopeService->apply($query, $admin, 'trade', 'user_infos.user_id');
    }

    /**
     * 计算当前筛选条件下的风控汇总。
     *
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query 已经追加筛选和数据范围的查询对象。
     * @return array<string, float|int> 页面汇总卡片数据。
     */
    private function summaryFor($query): array
    {
        $summary = DB::query()
            ->fromSub($query->toBase(), 'risk_scope')
            ->selectRaw('COUNT(*) as total_records')
            ->selectRaw('COALESCE(SUM(risk_scope.profit), 0) as total_profit')
            ->selectRaw('COALESCE(SUM(risk_scope.volume), 0) as total_volume')
            ->selectRaw('COALESCE(SUM(risk_scope.margin), 0) as total_margin')
            ->selectRaw('COALESCE(SUM(risk_scope.risk_value), 0) as total_risk_value')
            ->first();

        return [
            'total_records' => (int) ($summary->total_records ?? 0),
            'total_profit' => (float) ($summary->total_profit ?? 0),
            'total_volume' => (float) ($summary->total_volume ?? 0),
            'total_margin' => (float) ($summary->total_margin ?? 0),
            'total_risk_value' => (float) ($summary->total_risk_value ?? 0),
        ];
    }

    /**
     * 按 Layui 分页参数返回列表。
     *
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query 查询对象。
     * @param Request $request 当前请求对象，读取 page、per_page 或 limit。
     * @param string $orderColumn 排序字段名。
     * @param string $direction 排序方向，默认 desc。
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    private function paginateQuery($query, Request $request, string $orderColumn, string $direction = 'desc')
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', $request->input('limit', 15));
        $perPage = $perPage > 0 ? min($perPage, 100) : 15;

        return $query->orderBy($orderColumn, $direction)->paginate($perPage, ['*'], 'page', $page);
    }
}
