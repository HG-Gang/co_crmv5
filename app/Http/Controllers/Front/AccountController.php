<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/28
 * Time: 01:07
 */

namespace App\Http\Controllers\Front;

use App\Models\CommissionRecord;
use App\Models\DepositRecord;
use App\Models\GroupConfig;
use App\Models\UserInfo;
use App\Models\UserTrade;
use App\Models\WithdrawRecord;
use App\Models\VoucherInfo;
use App\Constants\ResponseCode;
use App\Services\Mt4ManagerService;
use App\Support\FrontLegacyData;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * 前台账户管理控制器。
 *
 * 文件功能：
 * - 处理账户信息、余额明细、凭证提交和旧前台账户接口兼容。
 * - Blade 与 Layui 页面读取 JSON 数据渲染账户综合、账户余额和凭证列表。
 * - 旧前台仍会调用 user/change_account_save、user/user_voucher_save 等接口，因此本控制器保留旧响应结构。
 * - 账户类型切换通过 MT4 update_user 同时更新交易组和杠杆，远端未明确成功时禁止修改本地镜像。
 *
 * 金额口径：
 * - total_funds、equity、used_margin、avail_margin 等资金字段以 USD 为单位。
 * - total_deposit/total_withdraw/total_rebate 汇总来自 SUM 聚合，展示时统一按两位小数格式化。
 *
 * 安全边界：
 * - 账户类型切换必须同时满足净值门槛、无未平仓订单、组配置完整，且 MT4 update_user 明确成功后才更新本地镜像。
 * - 切换在事务内锁定用户行，资格判断读取锁定后的最新净值；远端命令幂等，重复提交同一请求可安全重试。
 * - 凭证图片按当前用户 user_id 归档，列表与新增记录归属都由 user_id 隔离，无法查看他人凭证。
 */
class AccountController extends FrontBaseController
{
    /** @var float ECN_MINIMUM_EQUITY 切换 ECN 时允许的最低账户净值，边界值 3000 可通过。 */
    private const ECN_MINIMUM_EQUITY = 3000.0;

    /** @var Mt4ManagerService $mt4Manager MT4 Manager 旧协议客户端。 */
    private $mt4Manager;

    /**
     * 构造前台账户控制器。
     *
     * @param Mt4ManagerService $mt4Manager MT4 Manager 服务，用于同步账户交易组与杠杆。
     */
    public function __construct(Mt4ManagerService $mt4Manager)
    {
        $this->mt4Manager = $mt4Manager;
    }

    /**
     * 返回当前登录用户的账户综合数据。
     *
     * 参数逻辑说明：
     * - accountInfo 用于返回当前登录用户的账户综合数据。
     * - 当前用户来自前台 user guard 或旧 session 中的 suser 兼容数据。
     * - 返回值包含 total_funds、equity、used_margin、avail_margin、comm_rate 等账户核心指标。
     *
     * @param Request $request HTTP 请求对象，用于解析当前前台登录用户。
     * @return JsonResponse 账户综合数据响应。
     */
    public function accountInfo(Request $request): JsonResponse
    {
        $userInfo = $this->currentUserInfo($request);

        if (!$userInfo) {
            return $this->error('auth.user_info_not_found', ResponseCode::USER_NOT_FOUND);
        }

        return $this->success($this->accountOverviewData($userInfo), 'response.query_success');
    }

    /**
     * 返回当前登录用户的余额明细数据。
     *
     * 参数逻辑说明：
     * - accountBalance 用于返回当前登录用户的余额明细数据。
     * - 当前实现复用 accountOverviewData()，保证账户综合页与余额页使用同一套资金指标。
     *
     * @param Request $request HTTP 请求对象，用于解析当前前台登录用户。
     * @return JsonResponse 余额明细数据响应。
     */
    public function accountBalance(Request $request): JsonResponse
    {
        $userInfo = $this->currentUserInfo($request);

        if (!$userInfo) {
            return $this->error('auth.user_info_not_found', ResponseCode::USER_NOT_FOUND);
        }

        return $this->success($this->accountOverviewData($userInfo), 'response.query_success');
    }

    /**
     * 解析当前前台登录用户资料。
     *
     * 参数逻辑说明：
     * - currentUserInfo 用于解析当前前台登录用户资料。
     * - user_id 表示当前业务用户编号，对应 user_infos.user_id。
     * - 这里重新加载 login 和 groupConfig 关系，避免旧 session 中的用户资料缺少邮箱或组别名称。
     *
     * @param Request $request HTTP 请求对象，用于读取 JWT 用户或旧 session 用户。
     * @return UserInfo|null 当前用户资料；未登录或资料不存在时返回 null。
     */
    private function currentUserInfo(Request $request): ?UserInfo
    {
        $userInfo = $this->legacyFrontUserInfo($request);

        return $userInfo
            ? UserInfo::with(['login', 'groupConfig'])->where('user_id', $userInfo->user_id)->first()
            : null;
    }

    /**
     * 组装账户综合指标。
     *
     * 参数逻辑说明：
     * - accountOverviewData 用于组装账户综合指标。
     * - user_id 表示当前业务用户编号，是资金、订单、凭证和代理关系查询的主键。
     * - total_funds 表示账户总资金，balance 为前端兼容别名。
     * - equity 表示账户净值。
     * - used_margin 表示已用保证金，margin 为前端兼容别名。
     * - avail_margin 表示可用保证金，free_margin 为前端兼容别名。
     * - comm_rate 表示代理返佣比例，前端字段名为 commission_rate。
     * - is_ecn 与 ecn_minimum_equity 供 Blade 在同一次资料请求中完成账户类型和资格渲染。
     *
     * @param UserInfo $userInfo 当前用户资料，必须来自 user_infos 表。
     * @return array<string, mixed> 前台账户综合页、余额页和图表使用的数据。
     */
    private function accountOverviewData(UserInfo $userInfo): array
    {
        $userId = (int) $userInfo->user_id;
        $closedTrades = UserTrade::where('user_id', $userId)->closed();
        $openTrades = UserTrade::where('user_id', $userId)->open();
        $directAgentIds = FrontLegacyData::userScopeIds($userId, false, 1, true);
        $allAgentIds = FrontLegacyData::userScopeIds($userId, false, 1);
        $indirectAgentIds = array_values(array_diff($allAgentIds, $directAgentIds));
        $directCustomerIds = FrontLegacyData::userScopeIds($userId, false, 2, true);
        $allCustomerIds = FrontLegacyData::userScopeIds($userId, false, 2);
        $indirectCustomerIds = array_values(array_diff($allCustomerIds, $directCustomerIds));
        // totalDeposit/totalWithdraw/totalRebate 分别表示当前用户入金、出金和代理返佣累计金额。
        $totalDeposit = (float) DepositRecord::where('user_id', $userId)->sum('amount');
        $totalWithdraw = (float) WithdrawRecord::where('user_id', $userId)->sum('apply_amount');
        $totalRebate = (float) CommissionRecord::where('agent_id', $userId)->sum('commission_amount');
        $directAgents = count($directAgentIds);
        $directCustomers = count($directCustomerIds);
        $indirectCustomers = count($indirectCustomerIds);
        $fundsComparison = [
            ['key' => 'total_deposit', 'label' => 'front.total_deposit', 'value' => $totalDeposit],
            ['key' => 'total_rebate', 'label' => 'front.total_rebate', 'value' => $totalRebate],
            ['key' => 'total_withdraw', 'label' => 'front.total_withdraw', 'value' => $totalWithdraw],
            ['key' => 'total_funds', 'label' => 'front.total_funds', 'value' => (float) $userInfo->total_funds],
            ['key' => 'equity', 'label' => 'front.equity', 'value' => (float) $userInfo->equity],
        ];

        return [
            'user_id' => $userInfo->user_id,
            'user_name' => $userInfo->user_name,
            'email' => $userInfo->login ? $userInfo->login->email : '',
            'account_type' => $userInfo->account_type,
            'is_ecn' => (int) $userInfo->is_ecn,
            'ecn_minimum_equity' => self::ECN_MINIMUM_EQUITY,
            'total_funds' => $userInfo->total_funds,
            'balance' => $userInfo->total_funds,
            'equity' => $userInfo->equity,
            'used_margin' => $userInfo->used_margin,
            'margin' => $userInfo->used_margin,
            'avail_margin' => $userInfo->avail_margin,
            'free_margin' => $userInfo->avail_margin,
            'effective_credit' => $userInfo->effective_credit,
            'credit' => $userInfo->effective_credit,
            'risk_ratio' => $userInfo->risk_ratio,
            'margin_level' => $userInfo->risk_ratio,
            'leverage' => $userInfo->leverage,
            'group_id' => $userInfo->group_id,
            'group_name' => trim((string) ($userInfo->groupConfig->name ?? '')),
            'commission_rate' => (float) $userInfo->comm_rate,
            'auth_status' => $userInfo->auth_status,
            'total_deposit' => $totalDeposit,
            'total_withdraw' => $totalWithdraw,
            'total_rebate' => $totalRebate,
            'funds_comparison' => $fundsComparison,
            'open_order_count' => (clone $openTrades)->count(),
            'closed_order_count' => (clone $closedTrades)->count(),
            'profit_7d' => (clone $closedTrades)->where('close_time', '>=', now()->subDays(7))->sum('profit'),
            'profit_15d' => (clone $closedTrades)->where('close_time', '>=', now()->subDays(15))->sum('profit'),
            'profit_30d' => (clone $closedTrades)->where('close_time', '>=', now()->subDays(30))->sum('profit'),
            'direct_agents' => $directAgents,
            'indirect_agents' => count($indirectAgentIds),
            'direct_customers' => $directCustomers,
            'indirect_customers' => $indirectCustomers,
            'customer_gender_profile' => $this->customerGenderProfile($allCustomerIds),
            'relation_amount' => $allCustomerIds ? DepositRecord::whereIn('user_id', $allCustomerIds)->sum('amount') : 0,
            // 关系网各层级的真实入金、出金与返佣金额，供账户综合页的“相关金额”图表使用。
            'direct_agents_deposit' => $this->scopeDepositTotal($directAgentIds),
            'indirect_agents_deposit' => $this->scopeDepositTotal($indirectAgentIds),
            'direct_customers_deposit' => $this->scopeDepositTotal($directCustomerIds),
            'indirect_customers_deposit' => $this->scopeDepositTotal($indirectCustomerIds),
            'direct_agents_withdraw' => $this->scopeWithdrawTotal($directAgentIds),
            'indirect_agents_withdraw' => $this->scopeWithdrawTotal($indirectAgentIds),
            'direct_customers_withdraw' => $this->scopeWithdrawTotal($directCustomerIds),
            'indirect_customers_withdraw' => $this->scopeWithdrawTotal($indirectCustomerIds),
            'direct_agents_rebate' => $this->scopeRebateTotal($directAgentIds),
            'indirect_agents_rebate' => $this->scopeRebateTotal($indirectAgentIds),
        ];
    }

    /**
     * 汇总一批用户的入金金额。
     *
     * 参数逻辑说明：
     * - scopeDepositTotal 用于汇总指定用户集合在 deposit_records 中的入金金额。
     * - $userIds 表示业务用户编号集合，空集合直接返回 0，不产生无条件全表聚合。
     *
     * @param array<int, int|string> $userIds 业务用户编号集合。
     * @return float 入金金额合计，单位 USD。
     */
    private function scopeDepositTotal(array $userIds): float
    {
        $userIds = $this->normalizeScopeIds($userIds);

        return $userIds ? (float) DepositRecord::whereIn('user_id', $userIds)->sum('amount') : 0.0;
    }

    /**
     * 汇总一批用户的出金申请金额。
     *
     * 参数逻辑说明：
     * - scopeWithdrawTotal 用于汇总指定用户集合在 withdraw_records 中的出金申请金额。
     * - $userIds 表示业务用户编号集合，空集合直接返回 0。
     *
     * @param array<int, int|string> $userIds 业务用户编号集合。
     * @return float 出金金额合计，单位 USD。
     */
    private function scopeWithdrawTotal(array $userIds): float
    {
        $userIds = $this->normalizeScopeIds($userIds);

        return $userIds ? (float) WithdrawRecord::whereIn('user_id', $userIds)->sum('apply_amount') : 0.0;
    }

    /**
     * 汇总一批代理自身获得的返佣金额。
     *
     * 参数逻辑说明：
     * - scopeRebateTotal 用于汇总 commission_records 中 agent_id 落在指定集合内的返佣金额。
     * - commission_records 只有 agent_id（获得返佣的代理）与 parent_id（上级代理），没有 user_id 列，
     *   因此这里按 agent_id 统计，口径是“这批代理自己拿到了多少返佣”。
     * - 普通客户不是代理，不会出现在 agent_id 中，因此客户维度只提供入金与出金金额。
     *
     * @param array<int, int|string> $agentIds 代理业务用户编号集合。
     * @return float 返佣金额合计，单位 USD。
     */
    private function scopeRebateTotal(array $agentIds): float
    {
        $agentIds = $this->normalizeScopeIds($agentIds);

        return $agentIds ? (float) CommissionRecord::whereIn('agent_id', $agentIds)->sum('commission_amount') : 0.0;
    }

    /**
     * 归一化用户编号集合：去空、去重并转为整数，避免非法值进入 SQL IN 条件。
     *
     * @param array<int, int|string> $userIds 原始用户编号集合。
     * @return array<int, int> 归一化后的用户编号集合。
     */
    private function normalizeScopeIds(array $userIds): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $userIds))));
    }

    /**
     * 统计代理名下客户性别分布。
     *
     * 参数逻辑说明：
     * - customerGenderProfile 用于统计代理名下客户性别分布。
     * - user_id 表示当前代理业务用户编号，直属客户通过 parent_id 匹配，间接客户通过 family_tree 匹配。
     * - gender=1 表示男性，gender=2 表示女性，其他值归入 unknown。
     *
     * @param int $userId 当前代理业务用户编号。
     * @return array<string, array<string, int|float>> 客户性别数量和百分比。
     */
    private function customerGenderProfile(array $customerIds): array
    {
        $customerIds = array_values(array_unique(array_filter(array_map('intval', $customerIds))));
        $customers = $customerIds
            ? UserInfo::whereIn('user_id', $customerIds)->get(['gender'])
            : collect();
        $total = max(1, $customers->count());
        $profile = [
            'male' => ['count' => 0, 'ratio' => 0],
            'female' => ['count' => 0, 'ratio' => 0],
            'unknown' => ['count' => 0, 'ratio' => 0],
        ];

        foreach ($customers as $customer) {
            $key = (int) $customer->gender === 1 ? 'male' : ((int) $customer->gender === 2 ? 'female' : 'unknown');
            $profile[$key]['count']++;
        }

        foreach ($profile as $key => $value) {
            $profile[$key]['ratio'] = round($value['count'] * 100 / $total, 2);
        }

        return $profile;
    }

    /**
     * 提交新版凭证图片。
     *
     * 参数逻辑说明：
     * - submitVoucher 用于提交新版凭证图片。
     * - images 表示凭证图片数组，至少上传 1 张，单张最大 5120KB。
     * - remarks 表示凭证备注，可为空，最大 2000 字符。
     * - review_status=0 表示凭证待审核，后续由后台凭证审核模块处理。
     *
     * @param Request $request HTTP 请求对象，承载 images[] 和 remarks。
     * @return JsonResponse 凭证创建结果。
     */
    public function submitVoucher(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'images'   => 'required|array|min:1',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:5120',
            'remarks'  => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_ERROR);
        }

        $userInfo = $this->currentUserInfo($request);

        if (!$userInfo) {
            return $this->legacyFrontAuthError($request);
        }

        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                // 凭证图片保存到 public 磁盘的 vouchers/{user_id}/ 目录，数据库只保存相对路径。
                $path = $file->store('vouchers/' . $userInfo->user_id, 'public');
                $imagePaths[] = $path;
            }
        }

        $voucher = VoucherInfo::create([
            'user_id'       => $userInfo->user_id,
            'images'        => implode(',', $imagePaths),
            'remarks'       => $request->input('remarks', ''),
            'review_status' => 0, // review_status=0 表示凭证待审核。
            'created_by'    => $userInfo->user_name,
        ]);

        return $this->success($voucher, 'response.created', ResponseCode::SUCCESS);
    }

    /**
     * 兼容旧前台凭证上传接口。
     *
     * 参数逻辑说明：
     * - userVoucherSave 用于兼容旧前台凭证上传接口。
     * - voucherimg 表示旧页面第一张凭证图片。
     * - voucherimg2、voucherimg3 表示旧页面第二、第三张凭证图片。
     * - voucherremark 表示旧页面凭证备注，兼容新版 remarks 字段。
     * - 旧前台根据 err、col 判断具体表单错误，因此这里继续返回 legacyFail()/legacySuccess() 结构。
     *
     * @param Request $request HTTP 请求对象，承载旧页面凭证图片字段和备注。
     * @return JsonResponse 旧前台兼容响应。
     */
    public function userVoucherSave(Request $request): JsonResponse
    {
        $userInfo = $this->currentUserInfo($request);
        if (!$userInfo) {
            return $this->legacyFail('userNotFound', 'userId');
        }

        $files = [];
        foreach (['voucherimg', 'voucherimg2', 'voucherimg3'] as $field) {
            if ($request->hasFile($field)) {
                $files[$field] = $request->file($field);
            }
        }
        if (!$files && $request->hasFile('images')) {
            foreach ((array) $request->file('images') as $index => $file) {
                $files['images' . $index] = $file;
            }
        }

        if (!$files) {
            return $this->legacyFail('POSERRORFORMAT1', 'voucherimg');
        }

        $paths = [];
        foreach ($files as $field => $file) {
            if (!$file || !$file->isValid()) {
                return $this->legacyFail('POSERRORFORMAT1', $field);
            }

            $validator = Validator::make(['file' => $file], [
                'file' => 'image|mimes:jpeg,png,jpg,gif|max:10240',
            ]);
            if ($validator->fails()) {
                return $this->legacyFail(strpos($field, '2') !== false ? 'POSOVERSIZE2' : 'POSOVERSIZE1', $field);
            }

            $paths[] = $file->store('vouchers/' . (int) $userInfo->user_id, 'public');
        }

        VoucherInfo::create([
            'user_id' => (int) $userInfo->user_id,
            'images' => implode(',', $paths),
            'remarks' => (string) $request->input('voucherremark', $request->input('remarks', '')),
            'review_status' => 0,
            'created_by' => $userInfo->user_name,
            'updated_by' => $userInfo->user_name,
        ]);

        return $this->legacySuccess('SUC', 'NOTERROR');
    }

    /**
     * 更新当前登录用户的交易账户类型。
     *
     * 现代 REST 入口复用旧前台已验证的事务、资格检查和 MT4 同步逻辑。
     *
     * @param Request $request HTTP 请求对象，承载 is_ecn。
     * @return JsonResponse 账户类型切换结果。
     */
    public function updateTradingProfile(Request $request): JsonResponse
    {
        return $this->changeAccountSave($request);
    }

    /**
     * 兼容旧前台账户类型切换接口。
     *
     * 参数逻辑说明：
     * - changeAccountSave 用于兼容旧前台账户类型切换接口。
     * - is_enc 表示旧页面 ECN 标识，兼容新版 is_ecn 字段。
     * - 切换 ECN 要求账户净值不低于 3000；该规则在事务锁内复核，不能只依赖浏览器校验。
     * - 存在未平仓订单时不允许切换账户类型，避免杠杆变化影响当前持仓风险。
     * - 当前组通过 group_id 与 mt4_group 双重解析，pair_id 指向目标当前交易组。
     * - is_ecn=1 时目标杠杆为 200，is_ecn=0 时目标杠杆为 100。
     * - MT4 使用一次幂等 update_user 设置 grp+lvg，明确成功后才更新本地全部镜像字段。
     * - 用户资料行锁保证同一用户的并发切换串行执行；伪造 user_id 不参与任何查询或写入。
     *
     * 返回结果：
     * - SUCCESS/noerr 表示 MT4 与本地资料均已更新。
     * - ECNMINBALANCE 表示账户净值低于 ECN 最低门槛，未调用 MT4。
     * - ERRVOL 表示存在未平仓或挂单，未调用 MT4。
     * - relationGroupNotExit 表示当前组或目标配对组配置不完整，未调用 MT4。
     * - MT4OHTERUPDFAIL 表示 MT4 未明确成功，本地资料保持原值。
     * - UPDATEFAIL 表示远端成功后的本地提交失败；重复同一请求可安全恢复，因为 update_user 是幂等设置。
     *
     * @param Request $request HTTP 请求对象，承载 is_enc/is_ecn。
     * @return JsonResponse 旧前台兼容响应。
     */
    public function changeAccountSave(Request $request): JsonResponse
    {
        $userInfo = $this->currentUserInfo($request);
        if (!$userInfo) {
            return $this->legacyFail('userNotFound', 'userId');
        }

        $rawIsEcn = $request->input('is_enc', $request->input('is_ecn'));
        $validator = Validator::make(['is_ecn' => $rawIsEcn], [
            'is_ecn' => 'required|integer|in:0,1',
        ]);
        if ($validator->fails()) {
            return $this->legacyFail('UPDATEFAIL', 'is_enc');
        }

        $isEcn = (int) $rawIsEcn;
        $userId = (int) $userInfo->user_id;

        try {
            $result = DB::transaction(function () use ($userId, $isEcn) {
                $lockedUser = UserInfo::where('user_id', $userId)->lockForUpdate()->first();
                if (!$lockedUser) {
                    return ['error' => 'userNotFound', 'column' => 'userId'];
                }

                // 资格判断必须读取锁定后的最新净值，避免并发资金变动绕过 ECN 最低门槛。
                if ($isEcn === 1 && (float) $lockedUser->equity < self::ECN_MINIMUM_EQUITY) {
                    return ['error' => 'ECNMINBALANCE', 'column' => 'is_enc'];
                }

                $openOrders = UserTrade::where('user_id', $userId)->open()->count();
                if ($openOrders > 0) {
                    return ['error' => 'ERRVOL', 'column' => 'NOCOL'];
                }

                $currentGroup = $this->resolveTradingGroup($lockedUser);
                if (!$currentGroup) {
                    return ['error' => 'relationGroupNotExit', 'column' => 'is_enc'];
                }

                // 重复提交同一类型时仍同步当前组，确保 MT4 与本地杠杆最终收敛到同一目标值。
                $targetGroup = (int) $currentGroup->is_ecn === $isEcn
                    ? $currentGroup
                    : GroupConfig::enabled()->whereKey((int) $currentGroup->pair_id)->first();
                if (!$targetGroup || (int) $targetGroup->is_ecn !== $isEcn) {
                    return ['error' => 'relationGroupNotExit', 'column' => 'is_enc'];
                }

                $leverage = $isEcn === 1 ? 200 : 100;
                try {
                    $mt4Result = $this->mt4Manager->updateUserTradingProfile(
                        $userId,
                        (string) $targetGroup->name,
                        $leverage
                    );
                } catch (Throwable $exception) {
                    Log::error('前台账户类型切换调用 MT4 异常。', [
                        'user_id' => $userId,
                        'target_group_id' => (int) $targetGroup->id,
                        'exception' => get_class($exception),
                    ]);

                    return ['error' => 'MT4OHTERUPDFAIL', 'column' => 'userphoneNo'];
                }

                if (($mt4Result['status'] ?? '') !== 'ok'
                    || (isset($mt4Result['err']) && trim((string) $mt4Result['err']) !== '0')
                ) {
                    Log::warning('前台账户类型切换未取得 MT4 明确成功响应。', [
                        'user_id' => $userId,
                        'target_group_id' => (int) $targetGroup->id,
                        'error_code' => (string) ($mt4Result['error_code'] ?? $mt4Result['err'] ?? ''),
                    ]);

                    return ['error' => 'MT4OHTERUPDFAIL', 'column' => 'userphoneNo'];
                }

                $lockedUser->update([
                    'group_id' => (int) $targetGroup->id,
                    'mt4_group' => (string) $targetGroup->name,
                    'original_group' => (int) $targetGroup->id === (int) $currentGroup->id
                        ? ((string) $lockedUser->original_group ?: (string) $currentGroup->name)
                        : (string) $currentGroup->name,
                    'is_ecn' => $isEcn,
                    'leverage' => $leverage,
                    'updated_by' => $userId,
                ]);

                return ['success' => true];
            }, 3);
        } catch (Throwable $exception) {
            // MT4 update_user 是幂等设置；若本地事务失败，用户重复同一请求即可重新确认远端并完成本地提交。
            Log::error('前台账户类型切换本地事务失败。', [
                'user_id' => $userId,
                'exception' => get_class($exception),
            ]);

            return $this->legacyFail('UPDATEFAIL', 'nocol');
        }

        if (isset($result['error'])) {
            return $this->legacyFail((string) $result['error'], (string) ($result['column'] ?? 'nocol'));
        }

        return $this->legacySuccess('SUCCESS');
    }

    /**
     * 解析用户当前真实交易组配置。
     *
     * 业务逻辑说明：
     * - 优先读取 user_infos.group_id 指向的启用组。
     * - mt4_group 非空时要求名称一致，避免历史错误 group_id 把用户切换到错误配对组。
     * - 历史 Demo 数据可能保存 “Legacy {MT4组名}”，同步前保留该名称作为兼容兜底。
     *
     * @param UserInfo $userInfo 已在当前事务中锁定的用户资料。
     * @return GroupConfig|null 当前启用交易组；无法可靠解析时返回 null。
     */
    private function resolveTradingGroup(UserInfo $userInfo): ?GroupConfig
    {
        $mt4Group = trim((string) $userInfo->mt4_group);
        $group = null;
        if ((int) $userInfo->group_id > 0) {
            $group = GroupConfig::enabled()->whereKey((int) $userInfo->group_id)->first();
        }

        if ($group && ($mt4Group === ''
                || (string) $group->name === $mt4Group
                || (string) $group->name === 'Legacy ' . $mt4Group)
        ) {
            return $group;
        }
        if ($mt4Group === '') {
            return $group;
        }

        return GroupConfig::enabled()
            ->whereIn('name', [$mt4Group, 'Legacy ' . $mt4Group])
            ->orderByRaw('CASE WHEN name = ? THEN 0 ELSE 1 END', [$mt4Group])
            ->first();
    }

    /**
     * 返回当前用户凭证列表。
     *
     * 参数逻辑说明：
     * - voucherList 用于返回当前用户凭证列表。
     * - review_status 表示凭证审核状态，传入时按 voucher_infos.review_status 精确筛选。
     * - 起止时间筛选由 FrontLegacyData::applyCreatedAtFilter() 兼容旧页面字段。
     *
     * @param Request $request HTTP 请求对象，承载 review_status、分页和时间筛选参数。
     * @return JsonResponse 当前用户凭证分页列表。
     */
    public function voucherList(Request $request): JsonResponse
    {
        $userInfo = $this->currentUserInfo($request);

        if (!$userInfo) {
            return $this->legacyFrontAuthError($request);
        }

        if ($request->filled('review_status')) {
            $validator = Validator::make($request->only('review_status'), [
                'review_status' => 'integer|in:0,1,2',
            ]);
            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }
        }

        $query = VoucherInfo::where('user_id', $userInfo->user_id);

        if ($request->filled('review_status')) {
            $query->where('review_status', $request->input('review_status'));
        }
        
        FrontLegacyData::applyCreatedAtFilter($query, $request);

        $records = $query->orderBy('created_at', 'desc')
            ->paginate(FrontLegacyData::perPage($request))
            ->through(function (VoucherInfo $voucher) {
                $row = $voucher->toArray();
                $row['review_msg'] = $voucher->review_message;
                $row['rec_crt_date'] = FrontLegacyData::dateTime($voucher->created_at);
                $row['rec_upd_date'] = FrontLegacyData::dateTime($voucher->updated_at);

                return $row;
            });

        return $this->success($records, 'response.query_success');
    }

    /**
     * 返回旧前台成功响应结构。
     *
     * 参数逻辑说明：
     * - legacySuccess 用于返回旧前台成功响应结构。
     * - msg 表示旧页面主提示码，例如 SUC、SUCCESS。
     * - err 表示旧页面错误码，成功时通常为 noerr 或 NOTERROR。
     * - col 表示旧页面需要定位的表单字段，成功时通常为 nocol。
     *
     * @param string $msg 旧页面主提示码。
     * @param string $err 旧页面错误码。
     * @param string $col 旧页面字段定位标识。
     * @return JsonResponse 旧前台兼容成功响应。
     */
    private function legacySuccess(string $msg = 'SUC', string $err = 'noerr', string $col = 'nocol'): JsonResponse
    {
        return response()->json([
            'msg' => $msg,
            'err' => $err,
            'col' => $col,
        ]);
    }

    /**
     * 返回旧前台失败响应结构。
     *
     * 参数逻辑说明：
     * - legacyFail 用于返回旧前台失败响应结构。
     * - err 表示旧页面错误码，例如 userNotFound、POSERRORFORMAT1、ERRVOL。
     * - col 表示旧页面需要高亮或提示的字段名称。
     *
     * @param string $err 旧页面错误码。
     * @param string $col 旧页面字段定位标识。
     * @return JsonResponse 旧前台兼容失败响应。
     */
    private function legacyFail(string $err, string $col = 'nocol'): JsonResponse
    {
        return response()->json([
            'msg' => 'FAIL',
            'err' => $err,
            'col' => $col,
        ]);
    }
}
