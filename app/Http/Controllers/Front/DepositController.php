<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\Front;

use App\Models\DepositRecord;
use App\Models\UserInfo;
use App\Models\PaymentChannel;
use App\Models\SystemConfig;
use App\Constants\ResponseCode;
use App\Support\FrontLegacyData;
use App\Support\Money;
use App\Services\Payment\PaymentGatewayRegistry;
use App\Services\Payment\PaymentOrderResult;
use App\Services\Payment\PaymentOrderService;
use App\Services\Legacy\LegacyFormIntentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DomainException;
use InvalidArgumentException;
use Throwable;

/**
 * 前台入金管理控制器。
 *
 * 文件功能：
 * - 处理入金页面配置、入金申请、旧前台入金接口兼容和入金历史记录。
 * - 支付通道、汇率、金额限额和入金开关均来自 payment_channels 与 system_configs 配置。
 * - Blade 页面只负责展示通道与提交表单，后端会再次解析通道、校验限额并写入 deposit_records 表。
 *
 * 金额与精度口径：
 * - 入金金额以 USD 为单位，提交值必须为两位小数格式；Money::fromDecimalString 校验格式、正数和全局限额。
 * - 通道自身限额在全局限额之后单独二次校验，两段区间都通过才允许创建订单。
 * - 汇率与限额读取为字符串，金额计算与落库统一走 BCMath 精确运算，不使用浮点比较。
 *
 * 安全边界：
 * - 建单前先校验用户级与系统级开关、周末及每日时间窗口，任一条件不满足都拒绝建单。
 * - 建单只允许幂等入口：Idempotency-Key 必须为 1-100 安全字符；旧表单的意图校验通过后才透传为幂等键。
 * - 同一订单的 provider 建单通过支付状态机串行化，provider 未明确成功时订单回落为
 *   provider_create_unknown，不向用户展示虚假支付地址，也不重复发起远端建单。
 */
class DepositController extends FrontBaseController
{
    /**
     * 返回前台入金页初始化数据。
     *
     * 参数逻辑说明：
     * - depositPage 用于返回前台入金页初始化数据。
     * - channels 表示可用支付通道列表，优先读取 payment_channels 表。
     * - exchange_rates 表示前台展示的币种汇率，CNY/JPY 从 system_configs 读取。
     * - deposit_limits 表示全局入金限额，由 amountLimits() 读取。
     * - is_allowed/disabled_message 表示当前用户和系统配置是否允许入金。
     *
     * @param Request $request HTTP 请求对象，用于解析当前前台登录用户。
     * @return JsonResponse 入金页初始化数据响应。
     */
    public function depositPage(Request $request): JsonResponse
    {
        $userInfo = $this->legacyFrontUserInfo($request);

        if (!$userInfo) {
            return $this->legacyFrontAuthError($request);
        }

        // 支付通道渲染保持数据驱动：Blade 不写死通道按钮，只接收后端标准化后的数据库配置。
        $channels = $this->frontChannels();
        $limits = $this->amountLimits();
        $availability = $this->depositAvailability($userInfo);

        $data = [
            'user' => [
                'user_id' => $userInfo->user_id,
                'user_name' => $userInfo->user_name,
                'balance' => $userInfo->total_funds,
            ],
            'is_allowed' => $availability['allowed'],
            'disabled_message' => $availability['message'],
            'channels'       => $channels,
            'exchange_rates' => [
                'USD' => '1.00000000',
                'CNY' => (string) SystemConfig::getVal('deposit_exchange_rate_cny', '7.0'),
                'JPY' => (string) SystemConfig::getVal('deposit_exchange_rate_jpy', '145.0'),
            ],
            'deposit_limits' => $limits,
        ];

        return $this->success($data, 'response.query_success');
    }

    /**
     * 提交新版前台入金申请。
     *
     * 参数逻辑说明：
     * - submitDeposit 用于提交新版前台入金申请。
     * - amount 表示新版入金金额。
     * - deposit_amt_usd 表示旧页面提交的美元入金金额，兼容旧表单字段。
     * - channel 表示新版支付通道编码。
     * - pay_channel 表示旧页面支付通道字段，passageway 表示另一个旧通道字段别名。
     * - local_order_no 表示本地入金订单号，写入 deposit_records.local_order_no。
     *
     * @param Request $request HTTP 请求对象，承载入金金额、支付通道和旧字段别名。
     * @return JsonResponse 入金申请创建结果。
     */
    public function submitDeposit(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'channel' => 'nullable|string|max:100',
            'pay_channel' => 'nullable|string|max:100',
            'passageway' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $userInfo = $this->legacyFrontUserInfo($request);

        if (!$userInfo) {
            return $this->legacyFrontAuthError($request);
        }

        $availability = $this->depositAvailability($userInfo);
        if (!$availability['allowed']) {
            return $this->error($availability['message'] ?: __('response.deposit_not_allowed'), ResponseCode::OPERATION_NOT_ALLOWED);
        }

        $amountInput = $request->input('amount', $request->input('deposit_amt_usd'));
        $submittedChannel = (string) $request->input('channel', $request->input('pay_channel', $request->input('passageway', '')));
        $idempotencyKey = trim((string) $request->header('Idempotency-Key', ''));
        if (!preg_match('/^[A-Za-z0-9._:-]{1,100}$/', $idempotencyKey)) {
            return $this->error('Idempotency-Key is required and must be 1-100 safe characters.', ResponseCode::VALIDATION_FAILED);
        }
        if (!is_string($amountInput) || $submittedChannel === '') {
            return $this->error(__('validation.required', ['attribute' => __('front.amount')]), ResponseCode::VALIDATION_FAILED);
        }

        $limits = $this->amountLimits();
        try {
            // 金额按两位小数 USD 精确解析，同时校验全局限额；格式或范围非法直接拒绝建单。
            $amount = Money::fromDecimalString($amountInput, $limits['min'], $limits['max']);
        } catch (InvalidArgumentException $exception) {
            return $this->error(__('validation.between.numeric', [
                'attribute' => __('front.amount'),
                'min' => $limits['min'],
                'max' => $limits['max'],
            ]), ResponseCode::VALIDATION_FAILED);
        }

        $channel = $this->resolvePaymentChannel($submittedChannel);
        if (!$channel) {
            return $this->error(__('response.operation_not_allowed'), ResponseCode::OPERATION_NOT_ALLOWED);
        }

        try {
            // 通道自身限额必须在全局限额基础上二次校验，通道配置覆盖全局默认值。
            Money::fromDecimalString($amount->toDecimalString(), $channel['min_amount'], $channel['max_amount']);
        } catch (InvalidArgumentException $exception) {
            return $this->error(__('validation.between.numeric', [
                'attribute' => __('front.amount'),
                'min' => $channel['min_amount'],
                'max' => $channel['max_amount'],
            ]), ResponseCode::VALIDATION_FAILED);
        }

        // status=01 表示入金未支付；provider 建单成功只确认支付入口已创建，不代表资金已到账。
        try {
            $orderResult = app(PaymentOrderService::class)->createOrRetrieve(
                $userInfo,
                $channel,
                $amount,
                $idempotencyKey
            );
        } catch (DomainException $exception) {
            return $this->error(__('response.operation_not_allowed'), ResponseCode::OPERATION_NOT_ALLOWED);
        }

        $order = $orderResult['order'];
        $order->refresh();
        if ((string) $order->payment_status === 'provider_create_in_progress'
            && $order->provider_create_started_at !== null) {
            DepositRecord::whereKey($order->getKey())
                ->where('payment_status', 'provider_create_in_progress')
                ->where('provider_create_started_at', '<=', now()->subMinutes(15))
                ->update([
                    'payment_status' => 'provider_create_unknown',
                    'updated_at' => time(),
                ]);
            $order->refresh();
        }
        $existingProviderResult = $this->providerResultFromSnapshot($order);
        if ($existingProviderResult !== null) {
            return $this->providerOrderResponse($order, $existingProviderResult);
        }
        if ((string) $order->payment_status !== 'pending') {
            return $this->error(__('response.operation_not_allowed'), ResponseCode::OPERATION_NOT_ALLOWED);
        }

        $claimed = DepositRecord::whereKey($order->getKey())
            ->where('payment_status', 'pending')
            ->where(function ($query) {
                $query->whereNull('channel_order_no')->orWhere('channel_order_no', '');
            })
            ->update([
                'payment_status' => 'provider_create_in_progress',
                'provider_create_started_at' => now(),
                'provider_create_attempts' => DB::raw('COALESCE(provider_create_attempts, 0) + 1'),
                'updated_at' => time(),
            ]);
        if ($claimed !== 1) {
            $order->refresh();
            $existingProviderResult = $this->providerResultFromSnapshot($order);
            if ($existingProviderResult !== null) {
                return $this->providerOrderResponse($order, $existingProviderResult);
            }

            return $this->error(__('response.operation_not_allowed'), ResponseCode::OPERATION_NOT_ALLOWED);
        }
        $order->refresh();

        try {
            $providerResult = $channel['_adapter']->createOrder($order, $channel['_config']);
            if (!hash_equals((string) $channel['code'], $providerResult->gatewayCode())) {
                throw new \UnexpectedValueException('Provider result gateway mismatch.');
            }
        } catch (Throwable $exception) {
            Log::error('front.payment.provider_create_failed', [
                'order_no' => (string) $order->local_order_no,
                'gateway' => (string) $order->gateway_code,
                'exception_class' => get_class($exception),
            ]);
            DepositRecord::whereKey($order->getKey())
                ->where('payment_status', 'provider_create_in_progress')
                ->update([
                    'payment_status' => 'provider_create_unknown',
                    'settlement_status' => 'pending',
                    'updated_at' => time(),
                ]);

            return $this->error(__('response.operation_not_allowed'), ResponseCode::OPERATION_NOT_ALLOWED);
        }

        $order->channel_order_no = $providerResult->providerOrderNumber();
        $order->provider_order_result = $providerResult->toArray();
        $order->payment_status = 'pending';
        $order->saveOrFail();

        return $this->providerOrderResponse($order, $providerResult);
    }

    /**
     * 组装 provider 建单成功后的支付响应。
     *
     * redirectUrl 为空时退回 formAction，两者都为空时 payment_url 为空字符串，由前端自行决定跳转或表单提交。
     *
     * @param DepositRecord $order 已保存 provider 结果的入金订单记录。
     * @param PaymentOrderResult $providerResult 支付服务商返回的建单结果。
     * @return JsonResponse 包含 provider 原始数据、本地订单号和支付入口地址的响应。
     */
    private function providerOrderResponse(DepositRecord $order, PaymentOrderResult $providerResult): JsonResponse
    {
        $providerData = $providerResult->toArray();
        $paymentUrl = $providerResult->redirectUrl() ?: $providerResult->formAction();

        return $this->success($providerData + [
            'order_no' => (string) $order->local_order_no,
            'payment_url' => $paymentUrl,
            'open_blank' => $providerResult->redirectUrl() !== null,
            'channel' => (string) $order->gateway_code,
        ], __('response.created'), ResponseCode::CREATED);
    }

    /**
     * 从订单已保存的 provider 快照恢复建单结果，用于幂等重放。
     *
     * 仅当订单仍处于 pending 且已保存 channel_order_no 与 provider_order_result 时才能恢复；
     * 快照字段缺失或无法重建 PaymentOrderResult 时返回 null，调用方按未建单处理，不伪造支付入口。
     *
     * @param DepositRecord $order 入金订单记录。
     * @return PaymentOrderResult|null 恢复出的建单结果；订单未完成建单或快照损坏时为 null。
     */
    private function providerResultFromSnapshot(DepositRecord $order): ?PaymentOrderResult
    {
        if ((string) $order->payment_status !== 'pending') {
            return null;
        }
        $snapshot = is_array($order->provider_order_result) ? $order->provider_order_result : [];
        if (trim((string) $order->channel_order_no) === '' || $snapshot === []) {
            return null;
        }

        try {
            return new PaymentOrderResult(
                (string) $order->gateway_code,
                (string) $order->channel_order_no,
                isset($snapshot['redirect_url']) ? (string) $snapshot['redirect_url'] : null,
                isset($snapshot['form_action']) ? (string) $snapshot['form_action'] : null,
                is_array($snapshot['form_fields'] ?? null) ? $snapshot['form_fields'] : []
            );
        } catch (InvalidArgumentException $exception) {
            return null;
        }
    }

    /**
     * 兼容旧前台入金申请接口。
     *
     * 参数逻辑说明：
     * - deposit_request 用于兼容旧前台入金申请接口。
     * - deposit_amt 表示部分旧页面提交的入金金额字段，会统一合并到 amount。
     * - pay_channel/passageway 表示旧页面支付通道字段，会统一合并到 channel。
     *
     * @param Request $request HTTP 请求对象，承载新旧入金金额和支付通道字段。
     * @return JsonResponse 入金申请创建结果。
     */
    public function deposit_request(Request $request): JsonResponse
    {
        $this->bridgeLegacyIntent($request, 'deposit');
        $request->merge([
            'amount' => $request->input('amount', $request->input('deposit_amt_usd', $request->input('deposit_amt'))),
            'channel' => $request->input('channel', $request->input('pay_channel', $request->input('passageway'))),
        ]);

        return $this->submitDeposit($request);
    }

    /**
     * 兼容旧前台 OTC 入金申请接口。
     *
     * 参数逻辑说明：
     * - deposit_request_otc 用于兼容旧前台 OTC 入金申请接口。
     * - 当前 OTC 入金与普通旧入金申请走同一套金额、通道和限额校验。
     *
     * @param Request $request HTTP 请求对象，承载旧 OTC 入金字段。
     * @return JsonResponse 入金申请创建结果。
     */
    public function deposit_request_otc(Request $request): JsonResponse
    {
        return $this->deposit_request($request);
    }

    /**
     * 将旧表单携带的防重放 nonce 桥接为 HTTP Idempotency-Key。
     *
     * 旧页面没有 HTTP 头，只在表单里提交 idempotency_key；本方法先由 LegacyFormIntentService
     * 校验 nonce 与当前用户/用途绑定关系，校验通过才透传为 Idempotency-Key，避免未经验证的 nonce 进入幂等建单链路。
     *
     * @param Request $request 当前 HTTP 请求对象，可能携带表单 nonce。
     * @param string $purpose 旧表单用途标识（如 deposit）。
     * @return void
     */
    private function bridgeLegacyIntent(Request $request, string $purpose): void
    {
        if (trim((string) $request->header('Idempotency-Key', '')) !== '') {
            return;
        }

        $nonce = trim((string) $request->input('idempotency_key', ''));
        $userId = $this->legacyFrontUserId($request);
        if ($nonce === '' || $userId <= 0) {
            return;
        }

        try {
            $valid = app(LegacyFormIntentService::class)->validate(
                $request,
                $purpose,
                $userId,
                $nonce
            );
        } catch (\LogicException $exception) {
            $valid = false;
        }

        if ($valid) {
            $request->headers->set('Idempotency-Key', $nonce);
        }
    }

    /**
     * 返回当前用户入金历史记录。
     *
     * 参数逻辑说明：
     * - depositHistory 用于返回当前用户入金历史记录。
     * - status 表示入金记录状态筛选，对应 deposit_records.status。
     * - 时间范围筛选由 FrontLegacyData::applyCreatedAtFilter() 兼容旧页面字段。
     *
     * @param Request $request HTTP 请求对象，承载 status、分页和时间筛选参数。
     * @return JsonResponse 当前用户入金历史分页列表。
     */
    public function depositHistory(Request $request): JsonResponse
    {
        $userInfo = $this->legacyFrontUserInfo($request);

        if (!$userInfo) {
            return $this->legacyFrontAuthError($request);
        }

        if ($request->filled('status')) {
            $status = (string) $request->input('status');
            if (!in_array($status, ['01', '02', '05', '09', '10'], true)) {
                return $this->error(__('response.validation_failed'), ResponseCode::VALIDATION_FAILED);
            }
        }

        $query = DepositRecord::where('user_id', $userInfo->user_id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        FrontLegacyData::applyCreatedAtFilter($query, $request);

        $totalRow = FrontLegacyData::depositTotalRow($query);

        $records = $query->orderBy('created_at', 'desc')
            ->paginate(FrontLegacyData::perPage($request))
            ->through(function (DepositRecord $record) {
                $row = $record->toArray();
                $row['order_no'] = $record->local_order_no;
                $row['userId'] = $record->user_id;
                $row['userName'] = $record->user_name;
                $row['depositType'] = $record->channel_name;
                $row['depositComment'] = $record->remarks;
                $row['depositActProfit'] = FrontLegacyData::money($record->actual_amount ?: $record->amount);
                $row['amount'] = FrontLegacyData::money($record->amount);
                $row['actual_amount'] = FrontLegacyData::money($record->actual_amount ?: $record->amount);
                $row['exchange_rate'] = FrontLegacyData::money($record->exchange_rate ?: 1);
                $row['status_text'] = FrontLegacyData::depositStatusText($record->status);
                $row['modify_time'] = FrontLegacyData::dateTime($record->payment_time ?: $record->updated_at ?: $record->created_at);
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
     * 兼容旧 store 方法。
     *
     * 参数逻辑说明：
     * - store 保留给旧路由或旧接口调用，实际复用 submitDeposit()。
     *
     * @param Request $request HTTP 请求对象，承载入金申请参数。
     * @return JsonResponse 入金申请创建结果。
     */
    public function store(Request $request): JsonResponse
    {
        return $this->submitDeposit($request);
    }

    /**
     * 兼容旧 records 方法。
     *
     * 参数逻辑说明：
     * - records 保留给旧路由或旧接口调用，实际复用 depositHistory()。
     *
     * @param Request $request HTTP 请求对象，承载入金历史筛选参数。
     * @return JsonResponse 当前用户入金历史分页列表。
     */
    public function records(Request $request): JsonResponse
    {
        return $this->depositHistory($request);
    }

    /**
     * 构建前台可展示支付通道。
     *
     * 参数逻辑说明：
     * - frontChannels 用于构建前台可展示支付通道。
     * - 只暴露入金 UI 必需字段，支付服务商私有配置继续保存在 payment_channels.config。
     * - 只展示具备可调用白名单 adapter 的通道；仅有数据库配置时保持失败关闭。
     *
     * @return array<int, array<string, mixed>> 前台支付通道列表。
     */
    private function frontChannels(): array
    {
        $limits = $this->amountLimits();
        $channels = PaymentChannel::enabled()
            ->orderBy('sort', 'desc')
            ->orderBy('id', 'asc')
            ->get();

        $registry = app(PaymentGatewayRegistry::class);

        return $channels->filter(function (PaymentChannel $channel) use ($registry) {
            return $registry->resolve($channel, (string) $channel->channel_code) !== null;
        })->map(function (PaymentChannel $channel) use ($limits) {
            $config = is_array($channel->config) ? $channel->config : [];
            $legacyMeta = $this->legacyChannelMeta($channel);
            $legacyId = $this->legacyChannelId($channel);
            $labelKey = (string) ($config['label_key'] ?? '');
            $type = (string) ($config['type'] ?? $legacyMeta['type']);
            $typeLabelKey = (string) ($config['type_label_key'] ?? ($type === 'crypto' ? 'front.channel_type_crypto' : 'front.channel_type_fiat'));
            $exchangeRate = (string) $channel->exchange_rate;
            $remarkItems = $this->channelRemarkItems($config);
            $description = (string) ($config['description'] ?? $this->channelDescription($remarkItems));

            return [
                'id' => (int) $channel->id,
                'name' => $labelKey !== '' ? __($labelKey) : $channel->name,
                'label_key' => $labelKey,
                'code' => $channel->channel_code,
                'exchange_rate' => $exchangeRate,
                'sort' => (int) $channel->sort,
                'is_default' => (int) (!empty($config['is_default'])),
                'min_amount' => (string) ($config['min_amount'] ?? ($legacyMeta['min'] ?: $limits['min'])),
                'max_amount' => (string) ($config['max_amount'] ?? ($legacyMeta['max'] ?: $limits['max'])),
                'type' => $type,
                'type_label_key' => $typeLabelKey,
                'type_label' => __($typeLabelKey),
                'description' => $description,
                'remark_items' => $remarkItems,
            ];
        })->values()->all();
    }

    /**
     * 校验并标准化前端提交的通道。
     *
     * 参数逻辑说明：
     * - resolvePaymentChannel 用于校验并标准化前端提交的通道。
     * - submitted 表示前端提交的通道 id 或 channel_code。
     * - 通道必须存在、启用并具备可调用白名单 adapter，不再重开内置 fallback 通道。
     *
     * @param string $submitted 前端提交的通道 id 或编码。
     * @return array<string, mixed>|null 标准化通道配置；无效通道返回 null。
     */
    private function resolvePaymentChannel(string $submitted)
    {
        $submitted = trim($submitted);
        $channel = PaymentChannel::enabled()
            ->where(function ($query) use ($submitted) {
                $query->where('channel_code', $submitted);
                if (ctype_digit($submitted)) {
                    $query->orWhere('id', (int) $submitted);
                }
            })
            ->first();

        if ($channel) {
            $resolved = app(PaymentGatewayRegistry::class)->resolve($channel, (string) $channel->channel_code);
            if ($resolved === null) {
                return null;
            }
            $config = $resolved['config'];
            $legacyMeta = $this->legacyChannelMeta($channel);
            $legacyId = $this->legacyChannelId($channel);
            $labelKey = (string) ($config['label_key'] ?? '');
            $type = (string) ($config['type'] ?? $legacyMeta['type']);
            $typeLabelKey = (string) ($config['type_label_key'] ?? ($type === 'crypto' ? 'front.channel_type_crypto' : 'front.channel_type_fiat'));
            $exchangeRate = (string) $channel->exchange_rate;
            $remarkItems = $this->channelRemarkItems($config);
            $description = (string) ($config['description'] ?? $this->channelDescription($remarkItems));

            return [
                'id' => (int) $channel->id,
                'name' => $labelKey !== '' ? __($labelKey) : $channel->name,
                'label_key' => $labelKey,
                'code' => $channel->channel_code,
                'exchange_rate' => $exchangeRate,
                'currency' => strtoupper((string) $config['currency']),
                'description' => $description,
                'remark_items' => $remarkItems,
                'min_amount' => (string) ($config['min_amount'] ?? $legacyMeta['min']),
                'max_amount' => (string) ($config['max_amount'] ?? $legacyMeta['max']),
                'type' => $type,
                'type_label_key' => $typeLabelKey,
                'type_label' => __($typeLabelKey),
                '_adapter' => $resolved['adapter'],
                '_config' => $config,
            ];
        }

        return null;
    }

    /**
     * 读取全局入金金额上下限。
     *
     * 参数逻辑说明：
     * - amountLimits 用于读取全局入金金额上下限。
     * - deposit_min_amount 表示最小入金金额，默认 10。
     * - deposit_max_amount 表示最大入金金额，默认 500000。
     *
     * @return array{min: string, max: string} 全局入金限额。
     */
    private function amountLimits(): array
    {
        return [
            'min' => (string) SystemConfig::getVal('deposit_min_amount', '10.00'),
            'max' => (string) SystemConfig::getVal('deposit_max_amount', '500000.00'),
        ];
    }

    /**
     * 判断当前用户和系统是否允许入金。
     *
     * 参数逻辑说明：
     * - depositAvailability 用于判断当前用户和系统是否允许入金。
     * - is_deposit_allowed 不等于 0 表示当前用户被禁止入金。
     * - deposit_enabled 表示系统入金总开关。
     * - deposit_weekend_enabled 表示周末是否允许入金。
     * - deposit_start_time/deposit_end_time 表示每日允许入金时间窗口。
     *
     * @param UserInfo $userInfo 当前前台用户资料。
     * @return array{allowed: bool, message: string} 是否允许入金及禁用提示。
     */
    private function depositAvailability(UserInfo $userInfo): array
    {
        $message = (string) SystemConfig::getVal('deposit_disabled_message', __('front.deposit_disabled'));

        if ((int) $userInfo->is_deposit_allowed !== 0) {
            return ['allowed' => false, 'message' => $message];
        }

        if ((string) SystemConfig::getVal('deposit_enabled', '1') !== '1') {
            return ['allowed' => false, 'message' => $message];
        }

        if ((string) SystemConfig::getVal('deposit_weekend_enabled', '1') === '0' && (int) date('N') >= 6) {
            return ['allowed' => false, 'message' => $message];
        }

        $startTime = (string) SystemConfig::getVal('deposit_start_time', '');
        $endTime = (string) SystemConfig::getVal('deposit_end_time', '');
        if ($startTime !== '' && $endTime !== '') {
            $startTime = substr($startTime, 0, 5);
            $endTime = substr($endTime, 0, 5);
            $now = date('H:i');
            if ($startTime <= $endTime) {
                $inWindow = $now >= $startTime && $now <= $endTime;
            } else {
                $inWindow = $now >= $startTime || $now <= $endTime;
            }

            if (!$inWindow) {
                return ['allowed' => false, 'message' => $message];
            }
        }

        return ['allowed' => true, 'message' => ''];
    }

    /**
     * 读取旧通道限额和类型。
     *
     * 参数逻辑说明：
     * - legacyChannelMeta 用于读取旧通道限额和类型。
     * - channel_code 为纯数字时优先作为旧通道编号，否则使用 payment_channels.id。
     * - type=crypto 表示加密货币通道，type=fiat 表示法币通道。
     *
     * @param object $channel 支付通道对象或兼容对象，至少包含 id/channel_code。
     * @return array{min: string, max: string, type: string} 旧通道限额和类型。
     */
    private function legacyChannelMeta($channel): array
    {
        $legacyId = $this->legacyChannelId($channel);
        $min = (string) SystemConfig::getVal('deposit_channel_' . $legacyId . '_min', '0');
        $maxMap = [
            1 => '6800',
            2 => '30000',
            3 => '80000',
            4 => '500000',
            5 => '500000',
            6 => '6800',
            7 => '6800',
            8 => '14000',
            9 => '80000',
            10 => '6800',
            11 => '6800',
        ];

        return [
            'min' => $min,
            'max' => $maxMap[$legacyId] ?? '0',
            'type' => in_array($legacyId, [4, 5], true) ? 'crypto' : 'fiat',
        ];
    }

    /**
     * 推导旧项目使用的通道编号。
     *
     * 旧配置键（如 deposit_channel_{id}_min）按通道编号寻址：channel_code 为纯数字时直接使用它，
     * 否则回退到数据库主键 id，保证新旧通道都能命中旧限额配置。
     *
     * @param object $channel 支付通道对象或兼容对象，至少包含 channel_code/id。
     * @return int 旧通道编号。
     */
    private function legacyChannelId($channel): int
    {
        $code = (string) ($channel->channel_code ?? '');

        return ctype_digit($code) ? (int) $code : (int) ($channel->id ?? 0);
    }

    /**
     * 按优先级从通道配置中提取展示用备注条目。
     *
     * 依次尝试 remark_items/remarks/remark/description 配置键，解析出非空条目列表；
     * 配置全部缺失时返回空列表，前端不展示备注区域。
     *
     * @param array<string, mixed> $config 通道私有配置。
     * @return array<int, string> 备注条目列表。
     */
    private function channelRemarkItems(array $config): array
    {
        foreach (['remark_items', 'remarks', 'remark', 'description'] as $key) {
            if (!array_key_exists($key, $config)) {
                continue;
            }

            $items = $this->normalizeChannelRemarkItems($config[$key]);
            if ($items !== []) {
                return $items;
            }
        }

        return [];
    }

    /**
     * 把配置中的备注值统一解析为条目列表。
     *
     * 数组原样保留，字符串按换行、<br> 或中英文分号拆分，并过滤空条目；
     * 兼容旧项目把多条备注写在同一字符串里的存储方式。
     *
     * @param mixed $value 配置中的备注值，可为数组或字符串。
     * @return array<int, string> 去空后的备注条目列表。
     */
    private function normalizeChannelRemarkItems($value): array
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $text = trim((string) $value);
            if ($text === '') {
                return [];
            }
            $items = preg_split('/(?:\r\n|\r|\n|<br\s*\/?>|；|;)+/i', $text) ?: [];
        }

        return array_values(array_filter(array_map(static function ($item) {
            return trim((string) $item);
        }, $items), static function ($item) {
            return $item !== '';
        }));
    }

    /**
     * 把备注条目列表拼接为通道描述文案。
     *
     * @param array<int, string> $items 备注条目列表。
     * @return string 以空格连接的单行描述。
     */
    private function channelDescription(array $items): string
    {
        return implode(' ', $items);
    }

    /**
     * 从通道配置中提取支付网关地址。
     *
     * 按 gateway_url/payment_url/pay_url/url 顺序取第一个非空值，兼容新旧配置字段名。
     *
     * @param array<string, mixed> $config 通道私有配置。
     * @return string 网关地址；全部字段缺失或为空时返回空字符串。
     */
    private function channelGatewayUrl(array $config): string
    {
        foreach (['gateway_url', 'payment_url', 'pay_url', 'url'] as $key) {
            $value = trim((string) ($config[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
