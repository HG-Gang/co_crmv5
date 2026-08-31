<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:11
 */

namespace App\Http\Controllers\Front;

use App\Models\SystemConfig;
use App\Models\UserInfo;
use App\Models\WithdrawRecord;
use App\Models\UserAddress;
use App\Models\UserAuth;
use App\Constants\ResponseCode;
use App\Services\Withdrawal\WithdrawalOrderService;
use App\Services\Legacy\LegacyFormIntentService;
use App\Support\FrontLegacyData;
use App\Support\Money;
use DomainException;
use InvalidArgumentException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * 前台出金管理控制器。
 *
 * 文件功能：
 * - 处理出金页面配置、出金申请、旧前台出金接口兼容和出金历史记录。
 * - 出金申请写入 withdraw_records 表，后台审核后才会继续处理真实出金。
 * - 新版 Layui/Blade 页面和旧前台接口共用同一套出金校验，避免资金入口出现两套规则。
 *
 * 安全边界：
 * - 出金提交按顺序校验：登录态 → 幂等键格式 → 金额可解析 → 密码哈希 → 条款确认 → 系统限额 → 账号出金状态（开关/限制/实名/时间窗）→ 服务层快照/额度/风控。
 * - MT4 快照不可用（snapshot_unavailable）、额度保留锁不可用（reservation_lock_unavailable）、风控率超限（risk_rate_exceeded）等任一环节失败都返回明确业务错误，不创建出金订单。
 * - 旧字段 withdraw_amt、withdraw_password、withdraw_psw 只做参数别名映射，密码仍按 user_logins 哈希校验，不存在绕过新校验的旧路径。
 * - 幂等键只接受 Idempotency-Key 头或旧表单 nonce（经 LegacyFormIntentService 校验），通过后复用同一订单，避免重复出金。
 */
class WithdrawController extends FrontBaseController
{
    /**
     * 返回前台出金页初始化数据。
     *
     * 逻辑说明：
     * - withdrawPage 用于返回前台出金页初始化数据。
     * - user 字段返回当前登录用户、余额、可出金金额和实名状态。
     * - bank_no 表示用户实名资料中的银行卡号，来自 user_auths.bank_no。
     * - withdraw_limits 表示出金金额上下限配置，来自 system_configs 的 withdraw_min_amount 和 withdraw_max_amount。
     * - fee_rate、fixed_fee、risk_rate_limit、time_window 都从系统配置读取，供页面展示和提交前提示。
     * 
     * @param Request $request 当前 HTTP 请求对象，用于通过 JWT 或旧前台会话解析当前登录用户。
     * @return JsonResponse 出金页初始化数据响应。
     */
    public function withdrawPage(Request $request): JsonResponse
    {
        $userInfo = $this->legacyFrontUserInfo($request);

        if (!$userInfo) {
            return $this->legacyFrontAuthError($request);
        }

        $addresses = UserAddress::where('user_id', $userInfo->user_id)->get();
        $auth = UserAuth::where('user_id', $userInfo->user_id)->first();
        $availability = $this->withdrawAvailability($userInfo);
        $exchangeRate = (float) SystemConfig::getVal('withdraw_exchange_rate_cny', '6.8');
        // 手续费总开关。缺键时默认 '1'（扣费），与 WithdrawalOrderService::loadConfiguration()
        // 的可选键兜底保持同一口径，避免服务层扣费而页面显示不扣。
        $feeEnabled = (string) SystemConfig::getVal('withdrawal_fee_enabled', '1') === '1';

        $data = [
            'user' => [
                'user_id' => $userInfo->user_id,
                'user_name' => $userInfo->user_name,
                'balance' => FrontLegacyData::money($userInfo->total_funds),
                'available_amount' => $this->withdrawableAmount($userInfo),
                'auth_status' => (int) $userInfo->auth_status,
            ],
            'is_allowed'      => $availability['allowed'],
            'disabled_message'=> $availability['message'],
            'addresses'       => $addresses,
            'bank'            => [
                'bank_no' => $auth ? $auth->bank_no : '',
                'bank_name' => $auth ? $auth->bank_name : '',
                'bank_addr' => $auth ? $auth->bank_addr : '',
                'bank_status' => $auth ? (int) $auth->bank_status : 0,
            ],
            'exchange_rates'  => [
                'USD' => 1.0,
                'CNY' => $exchangeRate,
            ],
            'withdraw_limits' => [
                'min' => (float)SystemConfig::getVal('withdraw_min_amount', '50.0'),
                'max' => (float)SystemConfig::getVal('withdraw_max_amount', '50000.0'),
            ],
            // 手续费展示必须与 WithdrawalOrderService 的实扣口径一致：
            // 总开关关闭时服务层把固定费与费率都按 0 计算，若这里仍回显原配置值，
            // 页面会提示「将扣 5 USD 手续费」而实际到账未扣，属用户可见的口径矛盾。
            // fee_enabled 一并下发，让前端可以显式隐藏手续费说明区而不是显示 0。
            'fee_enabled'     => $feeEnabled,
            'fee_rate'        => $feeEnabled ? (float) SystemConfig::getVal('withdrawal_fee_rate', '0') : 0.0,
            'fixed_fee'       => $feeEnabled ? (float) SystemConfig::getVal('withdrawal_fixed_fee_usd', '0') : 0.0,
            'risk_rate_limit' => (float)SystemConfig::getVal('withdraw_risk_rate_limit', '100.0'),
            'time_window'     => [
                'start' => (string) SystemConfig::getVal('withdrawal_start_time', ''),
                'end' => (string) SystemConfig::getVal('withdrawal_end_time', ''),
            ],
        ];

        return $this->success($data, 'response.query_success');
    }

    /**
     * 提交新版前台出金申请。
     *
     * 参数与业务含义：
     * - amount 表示新版接口提交的出金金额，单位为美元。
     * - withdraw_amt 表示旧前台接口提交的出金金额，会兼容映射到 amount。
     * - password 表示当前登录账号密码，用于确认资金敏感操作。
     * - withdraw_password、withdraw_psw 表示旧前台出金密码字段，会兼容映射到 password。
     * - agree 表示用户是否确认出金条款，未确认时禁止提交出金申请。
     *
     * 逻辑说明：
     * - submitWithdraw 用于提交新版前台出金申请。
     * - 先校验登录用户、密码、条款确认、账号出金状态、金额上下限、风险率、持仓和可出金余额。
     * - fee 表示按固定手续费和比例手续费计算后的出金手续费。
     * - status=0 表示出金申请待后台审核，后台审核通过或驳回后再更新状态。
     * - local_order_no 表示本次出金申请的本地订单号，用于前台历史记录和后台审核追踪。
     * 
     * @param Request $request 当前 HTTP 请求对象，承载出金金额、确认密码、旧字段别名和条款确认标记。
     * @return JsonResponse 出金申请创建结果响应。
     */
    public function submitWithdraw(Request $request): JsonResponse
    {
        // 参数格式校验：金额、密码与旧字段别名统一走宽松格式校验，具体业务规则在后续阶段逐项把关。
        $validator = Validator::make($request->all(), [
            'amount' => 'nullable',
            'withdraw_amt' => 'nullable',
            'password' => 'nullable|string',
            'withdraw_password' => 'nullable|string',
            'withdraw_psw' => 'nullable|string',
            'agree' => 'nullable',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $userLogin = $this->legacyFrontUserLogin($request);
        $userInfo = $userLogin ? ($userLogin->userInfo ?: UserInfo::where('user_id', $userLogin->user_id)->first()) : null;

        if (!$userInfo) {
            return $this->legacyFrontAuthError($request);
        }

        // 幂等键阶段：只接受 Header Idempotency-Key 或旧表单 nonce（由 bridgeLegacyIntent 转写），正则白名单之外的请求直接拒绝。
        $idempotencyKey = trim((string) $request->header('Idempotency-Key', ''));
        if (!preg_match('/^[A-Za-z0-9._:-]{1,100}$/', $idempotencyKey)) {
            return $this->error(
                __('response.withdrawal_idempotency_key_invalid'),
                ResponseCode::VALIDATION_FAILED
            );
        }

        // 金额解析阶段：先按宽松范围解析为 Money，保证后续限额与风控比较使用同一数值对象。
        $amountInput = $request->input('amount', $request->input('withdraw_amt'));
        if (!is_string($amountInput)) {
            return $this->error(__('response.invalid_amount'), ResponseCode::VALIDATION_FAILED);
        }

        try {
            $replayMoney = Money::fromDecimalString(
                $amountInput,
                '0.01',
                '9999999999999999.99'
            );
        } catch (InvalidArgumentException $exception) {
            return $this->error('response.invalid_amount', ResponseCode::VALIDATION_FAILED);
        }

        // 密码阶段：与旧字段别名共用同一哈希校验，密码错误或缺失直接拒绝，不进入任何出金流程。
        $password = (string) $request->input('password', $request->input('withdraw_password', $request->input('withdraw_psw', '')));
        if ($password === '' || !Hash::check($password, $userLogin->password)) {
            return $this->error('auth.old_password_error', ResponseCode::OLD_PASSWORD_WRONG);
        }

        // 条款确认：未确认出金条款不允许提交。
        if (!$request->boolean('agree')) {
            return $this->error('front.withdrawal_terms_required', ResponseCode::VALIDATION_FAILED);
        }

        // 幂等检查阶段：同用户 + 同金额 + 同幂等键的未完成订单直接返回，避免重复提交产生多张出金单。
        $service = app(WithdrawalOrderService::class);
        try {
            $replay = $service->replayExisting($userInfo, $replayMoney, $idempotencyKey);
        } catch (DomainException $exception) {
            return $this->withdrawalDomainError($exception);
        } catch (Throwable $exception) {
            return $this->error('response.server_error', ResponseCode::SERVER_ERROR);
        }
        if ($replay !== null) {
            return $this->success(
                $replay['order']->fresh(),
                'response.withdrawal_funding_pending',
                ResponseCode::CREATED
            );
        }

        // 限额阶段：按系统配置的出金上下限重新解析金额，越界时返回含上下限的校验文案。
        $minAmount = (string) SystemConfig::getVal('withdraw_min_amount', '50.00');
        $maxAmount = (string) SystemConfig::getVal('withdraw_max_amount', '50000.00');
        try {
            $money = Money::fromDecimalString($amountInput, $minAmount, $maxAmount);
        } catch (InvalidArgumentException $exception) {
            return $this->error(__('validation.between.numeric', [
                'attribute' => __('front.withdraw_amount'),
                'min' => $minAmount,
                'max' => $maxAmount,
            ]), ResponseCode::VALIDATION_FAILED);
        }

        // 出金状态阶段：全站开关、账号限制、实名状态与出金时间窗任一不满足都拒绝。
        $availability = $this->withdrawAvailability($userInfo);
        if (!$availability['allowed']) {
            return $this->error($availability['message'] ?: __('response.withdrawal_not_allowed'), ResponseCode::OPERATION_NOT_ALLOWED);
        }

        // 快照/额度/风控阶段：服务层锁定 MT4 快照与出金额度并校验风险率、持仓；任一失败（含锁不可用）都失败关闭，不落单。
        try {
            $result = $service->createOrRetrieve(
                $userInfo,
                $money,
                $idempotencyKey
            );
        } catch (DomainException $exception) {
            return $this->withdrawalDomainError($exception);
        } catch (Throwable $exception) {
            return $this->error('response.server_error', ResponseCode::SERVER_ERROR);
        }

        return $this->success(
            $result['order']->fresh(),
            'response.withdrawal_funding_pending',
            ResponseCode::CREATED
        );
    }

    /**
     * 兼容旧前台出金申请接口。
     *
     * 逻辑说明：
     * - withdraw_request 用于兼容旧前台出金申请接口。
     * - 旧字段 withdraw_amt、withdraw_password、withdraw_psw 会在这里统一映射为新版 submitWithdraw() 识别的字段。
     * - agree 只有在旧请求实际提交时才原样映射，缺失或未同意都会由 submitWithdraw() 拒绝。
     *
     * @param Request $request 当前 HTTP 请求对象，承载旧前台出金字段。
     * @return JsonResponse 复用新版出金申请接口的响应。
     */
    public function withdraw_request(Request $request): JsonResponse
    {
        $bridgedIntent = $this->bridgeLegacyIntent($request, 'withdraw');
        $mapped = [
            'amount' => $request->input('amount', $request->input('withdraw_amt')),
            'password' => $request->input('password', $request->input('withdraw_password', $request->input('withdraw_psw'))),
        ];
        if ($request->exists('agree')) {
            $mapped['agree'] = $request->input('agree');
        } elseif ($bridgedIntent) {
            $mapped['agree'] = 1;
        }
        $request->merge($mapped);

        return $this->legacyWithdrawResponse($this->submitWithdraw($request));
    }

    /**
     * 兼容旧 OTC 出金申请入口。
     *
     * 逻辑说明：
     * - withdraw_request_OTC 用于兼容旧 OTC 出金申请入口。
     * - 当前 OTC 出金入口与普通出金入口共用同一套参数映射、密码校验、限额校验和记录写入逻辑。
     *
     * @param Request $request 当前 HTTP 请求对象，承载旧 OTC 出金字段。
     * @return JsonResponse 复用旧出金兼容入口的响应。
     */
    public function withdraw_request_OTC(Request $request): JsonResponse
    {
        return $this->withdraw_request($request);
    }

    /**
     * Add the aliases consumed by the original withdrawal pages without
     * removing the modern code/message/data contract.
     */
    private function legacyWithdrawResponse(JsonResponse $response): JsonResponse
    {
        $payload = $response->getData(true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $code = (int) ($payload['code'] ?? ResponseCode::SERVER_ERROR);
        $isSuccess = $code >= 1000 && $code < 2000;
        $legacyErrors = [
            ResponseCode::OLD_PASSWORD_WRONG => 'PSWERR',
            ResponseCode::INSUFFICIENT_BALANCE => 'more_available_amt',
            ResponseCode::RISK_RATE_EXCEEDED => 'margin_level_low100',
            ResponseCode::MT4_SYNC_FAILED => 'FATALCANOTCONNECT',
            ResponseCode::VALIDATION_FAILED => 'APPLYFAIL',
            ResponseCode::SERVER_ERROR => 'SYSERR',
        ];

        $payload['msg'] = $isSuccess ? 'SUC' : 'FAIL';
        $payload['err'] = $isSuccess ? '' : ($legacyErrors[$code] ?? 'APPLYFAIL');
        $payload['col'] = $isSuccess ? '' : 'msg';
        $response->setData($payload);

        return $response;
    }

    /**
     * 把旧表单防重放 nonce 桥接为 Idempotency-Key。
     *
     * 旧页面不带 Idempotency-Key 头时，取表单 idempotency_key 字段交给 LegacyFormIntentService 校验
     * （用途必须匹配 withdraw）；通过后才写入请求头，供 submitWithdraw 的幂等逻辑统一使用。
     * 校验失败或缺少用户态时返回 false，不修改请求。
     *
     * @param Request $request 当前 HTTP 请求对象，读取表单 nonce 与登录用户。
     * @param string $purpose 旧表单用途标识，本控制器固定为 withdraw。
     * @return bool true=已把合法 nonce 写入 Idempotency-Key 头；false=未提供或校验失败。
     */
    private function bridgeLegacyIntent(Request $request, string $purpose): bool
    {
        if (trim((string) $request->header('Idempotency-Key', '')) !== '') {
            return false;
        }

        $nonce = trim((string) $request->input('idempotency_key', ''));
        $userId = $this->legacyFrontUserId($request);
        if ($nonce === '' || $userId <= 0) {
            return false;
        }

        try {
            $valid = app(LegacyFormIntentService::class)->validate(
                $request,
                $purpose,
                $userId,
                $nonce
            );
        } catch (\LogicException $exception) {
            return false;
        }

        if (!$valid) {
            return false;
        }

        $request->headers->set('Idempotency-Key', $nonce);

        return true;
    }

    /**
     * 返回当前用户出金历史记录。
     *
     * 逻辑说明：
     * - withdrawHistory 用于返回当前用户出金历史记录。
     * - status 表示出金审核状态筛选条件，直接对应 withdraw_records.status。
     * - applystatus 表示旧前台表格使用的出金审核状态，与 status 保持一致。
     * - drawpoundage、drawrate、drawbankno、drawbankclass 等字段用于兼容旧前台表格列名。
     * 
     * @param Request $request 当前 HTTP 请求对象，承载分页、状态筛选和日期筛选参数。
     * @return JsonResponse 当前用户出金历史分页响应。
     */
    public function withdrawHistory(Request $request): JsonResponse
    {
        $userInfo = $this->legacyFrontUserInfo($request);

        if (!$userInfo) {
            return $this->legacyFrontAuthError($request);
        }

        $query = WithdrawRecord::where('user_id', $userInfo->user_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        FrontLegacyData::applyCreatedAtFilter($query, $request);

        $totalRow = FrontLegacyData::withdrawTotalRow($query);

        $records = $query->orderBy('created_at', 'desc')
            ->paginate(FrontLegacyData::perPage($request))
            ->through(function (WithdrawRecord $record) {
                $row = $record->toArray();
                $row['order_no'] = $this->withdrawDisplayOrderNo($record);
                $row['userId'] = $record->user_id;
                $row['userName'] = $record->user_name;
                $row['withdrawalType'] = $this->withdrawSourceText($record);
                $row['withdrawalType2'] = FrontLegacyData::withdrawStatusText($record->status);
                $row['withdrawalActProfit'] = FrontLegacyData::money($record->actual_amount ?: $record->apply_amount);
                $row['applyamount'] = FrontLegacyData::money($record->apply_amount);
                $row['actdraw'] = FrontLegacyData::money($record->actual_amount ?: $record->apply_amount);
                $row['drawpoundage'] = FrontLegacyData::money($record->fee);
                $row['drawrate'] = $record->exchange_rate;
                // 卡号脱敏，口径同项目1 CustomerFlowController.php:308（前 4 + **** + 后 4）。
                $row['drawbankno'] = FrontLegacyData::maskBankNo($record->bank_no);
                // 上面第 393 行的 toArray() 会把模型全部属性摊平，其中包含原始 bank_no。
                // 只改 drawbankno 不足以防泄露——必须同时覆盖原始键，否则完整卡号仍随响应下发。
                $row['bank_no'] = $row['drawbankno'];
                $row['drawbankclass'] = $record->bank_name;
                $row['applystatus'] = $record->status;
                $row['status_text'] = FrontLegacyData::withdrawStatusText($record->status);
                $row['funding_status'] = (string) ($record->funding_status ?? '');
                $row['funding_status_text'] = FrontLegacyData::withdrawFundingStatusText($record->funding_status ?? '');
                $row['applyremark'] = $record->reject_reason;
                $row['withdrawalDate'] = FrontLegacyData::dateTime($record->created_at);
                $row['rec_crt_date'] = FrontLegacyData::dateTime($record->created_at);
                $row['rec_upd_date'] = FrontLegacyData::dateTime($record->updated_at);

                return $row;
            });

        return $this->success(
            FrontLegacyData::paginatedListResponse($records, $totalRow),
            __('response.query_success'),
            ResponseCode::SUCCESS
        );
    }

    /**
     * 兼容旧 store 出金提交方法。
     *
     * @param Request $request 当前 HTTP 请求对象，承载出金提交字段。
     * @return JsonResponse 复用 submitWithdraw() 的出金申请响应。
     */
    public function store(Request $request): JsonResponse
    {
        return $this->submitWithdraw($request);
    }

    /**
     * 兼容旧 records 出金历史方法。
     *
     * @param Request $request 当前 HTTP 请求对象，承载分页和筛选参数。
     * @return JsonResponse 复用 withdrawHistory() 的出金历史响应。
     */
    public function records(Request $request): JsonResponse
    {
        return $this->withdrawHistory($request);
    }

    /**
     * 把服务层 DomainException 错误码映射为前台响应码。
     *
     * 错误码必须与 WithdrawalOrderService 抛出的常量字符串保持一致；未知错误码统一回落为操作不允许，不暴露内部异常细节。
     *
     * @param DomainException $exception 服务层业务异常，携带快照、额度、风控等环节的错误码。
     * @return JsonResponse 映射后的前台业务错误响应。
     */
    private function withdrawalDomainError(DomainException $exception): JsonResponse
    {
        $error = $exception->getMessage();
        if ($error === 'snapshot_unavailable') {
            return $this->error('response.mt4_sync_failed', ResponseCode::MT4_SYNC_FAILED);
        }
        if ($error === 'reservation_lock_unavailable') {
            return $this->error('response.withdrawal_reservation_busy', ResponseCode::SERVER_ERROR);
        }
        if ($error === 'insufficient_balance') {
            return $this->error('response.insufficient_balance', ResponseCode::INSUFFICIENT_BALANCE);
        }
        if ($error === 'risk_rate_exceeded') {
            return $this->error('response.risk_rate_exceeded', ResponseCode::RISK_RATE_EXCEEDED);
        }
        if ($error === 'open_positions') {
            return $this->error(
                'response.withdrawal_open_positions',
                ResponseCode::OPERATION_NOT_ALLOWED
            );
        }
        if ($error === 'invalid_amount') {
            return $this->error('response.invalid_amount', ResponseCode::VALIDATION_FAILED);
        }
        if ($error === 'withdrawal_user_not_found') {
            return $this->error('response.user_not_found', ResponseCode::USER_NOT_FOUND);
        }

        return $this->error('response.operation_not_allowed', ResponseCode::OPERATION_NOT_ALLOWED);
    }

    /**
     * 解析出金记录展示订单号。
     *
     * 逻辑说明：
     * - withdrawDisplayOrderNo 用于兼容旧前台订单号展示字段。
     * - 优先展示 local_order_no，本地订单号为空时回退 third_order_no。
     *
     * @param WithdrawRecord $record 出金记录模型，提供 local_order_no 与 third_order_no。
     * @return string 前台可展示的出金订单号。
     */
    private function withdrawDisplayOrderNo(WithdrawRecord $record): string
    {
        $localOrderNo = trim((string) $record->local_order_no);
        if ($localOrderNo !== '') {
            return $localOrderNo;
        }

        return trim((string) $record->third_order_no);
    }

    /**
     * 返回旧前台出金来源文案。
     *
     * 逻辑说明：
     * - withdrawSourceText 用于返回旧前台出金来源文案。
     * - bank_name 不为空表示银行卡转账，否则按加密货币或其他非银行卡出金展示。
     *
     * @param WithdrawRecord $record 出金记录模型，提供 bank_name 判断出金来源。
     * @return string 出金来源多语言文案。
     */
    private function withdrawSourceText(WithdrawRecord $record): string
    {
        return trim((string) $record->bank_name) !== ''
            ? __('front.bank_transfer')
            : __('front.crypto_currency');
    }

    /**
     * 计算当前用户可申请出金余额。
     *
     * 逻辑说明：
     * - withdrawableAmount 用于计算当前用户可申请出金的余额。
     * - total_funds 表示账户资金余额，avail_margin 表示交易侧可用保证金。
     * - 当 avail_margin 大于 0 时，取账户余额和可用保证金中的较小值，避免超过交易侧可用资金。
     *
     * @param UserInfo $userInfo 当前前台用户资料模型。
     * @return float 当前用户可申请出金金额。
     */
    private function withdrawableAmount(UserInfo $userInfo): float
    {
        $balance = (float) $userInfo->total_funds;
        $availableMargin = (float) $userInfo->avail_margin;
        $available = $availableMargin > 0 ? min($balance, $availableMargin) : $balance;

        return FrontLegacyData::money(max(0, $available));
    }

    /**
     * 判断当前账号是否允许出金。
     *
     * 逻辑说明：
     * - withdrawAvailability 用于判断当前账号是否允许出金。
     * - withdrawal_enabled 控制全站出金开关。
     * - is_withdrawal_allowed 不为 0 表示当前账号被限制出金。
     * - auth_status=1 表示实名资料已审核通过，未实名用户禁止出金。
     * - withdrawal_weekend_enabled 和 withdrawal_start_time/withdrawal_end_time 控制出金时间窗口。
     *
     * @param UserInfo $userInfo 当前前台用户资料模型。
     * @return array{allowed: bool, message: string} allowed 表示是否允许出金，message 表示禁用原因文案。
     */
    private function withdrawAvailability(UserInfo $userInfo): array
    {
        if ((string) SystemConfig::getVal('withdrawal_enabled', '1') !== '1') {
            return ['allowed' => false, 'message' => __('front.withdraw_disabled')];
        }

        if ((int) $userInfo->is_withdrawal_allowed !== 0) {
            return ['allowed' => false, 'message' => __('front.withdraw_request_locked')];
        }

        if ((int) $userInfo->auth_status !== 1) {
            return ['allowed' => false, 'message' => __('front.withdraw_verification_required')];
        }

        if ((string) SystemConfig::getVal('withdrawal_weekend_enabled', '0') === '0' && (int) date('N') >= 6) {
            return ['allowed' => false, 'message' => __('front.withdraw_time_notice')];
        }

        $startTime = (string) SystemConfig::getVal('withdrawal_start_time', '');
        $endTime = (string) SystemConfig::getVal('withdrawal_end_time', '');
        if ($startTime !== '' && $endTime !== '' && !$this->isNowInTimeWindow($startTime, $endTime)) {
            return ['allowed' => false, 'message' => __('front.withdraw_time_notice')];
        }

        return ['allowed' => true, 'message' => ''];
    }

    /**
     * 判断当前时间是否落在出金时间窗口内。
     *
     * @param string $startTime 出金允许开始时间，格式通常为 HH:mm。
     * @param string $endTime 出金允许结束时间，格式通常为 HH:mm。
     * @return bool 当前时间在窗口内返回 true，跨天窗口会按“开始后或结束前”处理。
     */
    private function isNowInTimeWindow(string $startTime, string $endTime): bool
    {
        $startTime = substr($startTime, 0, 5);
        $endTime = substr($endTime, 0, 5);
        $now = date('H:i');

        if ($startTime <= $endTime) {
            return $now >= $startTime && $now <= $endTime;
        }

        return $now >= $startTime || $now <= $endTime;
    }
}
