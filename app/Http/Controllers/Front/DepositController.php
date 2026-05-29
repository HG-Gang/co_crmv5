<?php

namespace App\Http\Controllers\Front;

use App\Models\DepositRecord;
use App\Models\UserInfo;
use App\Models\PaymentChannel;
use App\Models\SystemConfig;
use App\Constants\ResponseCode;
use App\Support\FrontLegacyData;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Front Deposit Management Controller
 * 前台充值管理控制器
 * 
 * Handles deposit page data, deposit requests, and history.
 * 处理充值页面数据、充值请求和历史记录。
 */
class DepositController extends FrontBaseController
{
    /**
     * Get deposit page data (exchange rates, channels)
     * 获取充值页面数据（汇率、通道）
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function depositPage(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');
        $userInfo = $userLogin->userInfo;

        if (!$userInfo) {
            return $this->error(__('auth.user_info_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        // Keep channel rendering data-driven like the old front page: Blade does
        // not hard-code channel buttons, it only receives normalized DB config.
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
                'USD' => 1.0,
                'CNY' => (float) SystemConfig::getVal('deposit_exchange_rate_cny', '7.0'),
                'JPY' => (float) SystemConfig::getVal('deposit_exchange_rate_jpy', '145.0'),
            ],
            'deposit_limits' => $limits,
        ];

        return $this->success($data, 'response.query_success');
    }

    /**
     * Submit deposit request
     * 提交充值请求
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function submitDeposit(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount'  => 'nullable|numeric|min:0.01',
            'deposit_amt_usd'  => 'nullable|numeric|min:0.01',
            'channel' => 'nullable|string|max:100',
            'pay_channel' => 'nullable|string|max:100',
            'passageway' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $userLogin = $request->user('user');
        $userInfo = $userLogin->userInfo;

        if (!$userInfo) {
            return $this->error(__('auth.user_info_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        $availability = $this->depositAvailability($userInfo);
        if (!$availability['allowed']) {
            return $this->error($availability['message'] ?: __('response.deposit_not_allowed'), ResponseCode::OPERATION_NOT_ALLOWED);
        }

        $amount = (float) $request->input('amount', $request->input('deposit_amt_usd'));
        $submittedChannel = (string) $request->input('channel', $request->input('pay_channel', $request->input('passageway', '')));
        if ($amount <= 0 || $submittedChannel === '') {
            return $this->error(__('validation.required', ['attribute' => __('front.amount')]), ResponseCode::VALIDATION_FAILED);
        }

        $limits = $this->amountLimits();
        if ($amount < $limits['min'] || $amount > $limits['max']) {
            return $this->error(__('validation.between.numeric', [
                'attribute' => __('front.amount'),
                'min' => $limits['min'],
                'max' => $limits['max'],
            ]), ResponseCode::VALIDATION_FAILED);
        }

        // Resolve the submitted id/code on the server.  The front page is a
        // convenience layer only; this prevents arbitrary channel names from
        // being persisted into deposit_records.
        $channel = $this->resolvePaymentChannel($submittedChannel);
        if (!$channel) {
            return $this->error(__('front.payment_channel'), ResponseCode::VALIDATION_FAILED);
        }
        if (!empty($channel['min_amount']) && $amount < (float) $channel['min_amount']) {
            return $this->error(__('validation.min.numeric', [
                'attribute' => __('front.amount'),
                'min' => $channel['min_amount'],
            ]), ResponseCode::VALIDATION_FAILED);
        }
        if (!empty($channel['max_amount']) && $amount > (float) $channel['max_amount']) {
            return $this->error(__('validation.max.numeric', [
                'attribute' => __('front.amount'),
                'max' => $channel['max_amount'],
            ]), ResponseCode::VALIDATION_FAILED);
        }

        $exchangeRate = (float) $channel['exchange_rate'];
        if ($exchangeRate <= 0) {
            $exchangeRate = 1.0;
        }
        $localOrderNo = 'DEP' . date('YmdHis') . Str::upper(Str::random(6));

        $deposit = DepositRecord::create([
            'user_id'         => $userInfo->user_id,
            'user_name'       => $userInfo->user_name,
            'amount'          => $amount,
            'actual_amount'   => round($amount * $exchangeRate, 2),
            'exchange_rate'   => $exchangeRate,
            'channel_name'    => $channel['name'],
            'local_order_no'  => $localOrderNo,
            'status'          => '01', // 未支付 | Unpaid
            'remarks'         => 'DBUN-' . $userInfo->user_id . '-#' . $localOrderNo . ';pay_channel=' . $channel['code'],
            'created_by'      => $userInfo->user_name,
        ]);

        $paymentUrl = trim((string) ($channel['gateway_url'] ?? ''));
        if ($paymentUrl === '') {
            $paymentUrl = url('/api/front/payment/return/' . urlencode($channel['code']) . '?order_no=' . urlencode($localOrderNo));
        }

        return $this->success([
            'deposit' => $deposit,
            'order_no' => $localOrderNo,
            'payment_url' => $paymentUrl,
            'open_blank' => true,
            'channel' => $channel['code'],
        ], 'response.created', ResponseCode::CREATED);
    }

    /**
     * List deposit records
     * 获取充值记录历史
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function depositHistory(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');
        $userInfo = $userLogin->userInfo;

        if (!$userInfo) {
            return $this->error(__('auth.user_info_not_found'), ResponseCode::USER_NOT_FOUND);
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
     * Legacy method for store
     */
    public function store(Request $request): JsonResponse
    {
        return $this->submitDeposit($request);
    }

    /**
     * Legacy method for records
     */
    public function records(Request $request): JsonResponse
    {
        return $this->depositHistory($request);
    }

    /**
     * Build front-safe enabled channel data.
     *
     * Only the fields required by the deposit UI are exposed.  Provider-specific
     * options stay in config so admin tools can evolve without changing Blade.
     *
     * @return array
     */
    private function frontChannels(): array
    {
        $limits = $this->amountLimits();
        $channels = PaymentChannel::enabled()
            ->orderBy('sort', 'desc')
            ->orderBy('id', 'asc')
            ->get();

        if ($channels->isEmpty()) {
            return $this->fallbackChannels();
        }

        return $channels->map(function (PaymentChannel $channel) use ($limits) {
            $config = is_array($channel->config) ? $channel->config : [];
            $legacyMeta = $this->legacyChannelMeta($channel);
            $labelKey = (string) ($config['label_key'] ?? '');
            $type = (string) ($config['type'] ?? $legacyMeta['type']);
            $typeLabelKey = (string) ($config['type_label_key'] ?? ($type === 'crypto' ? 'front.channel_type_crypto' : 'front.channel_type_fiat'));
            $exchangeRate = (float) $channel->exchange_rate;
            if ($exchangeRate <= 0) {
                $exchangeRate = $type === 'crypto'
                    ? 1.0
                    : (float) $this->configValue(['deposit_exchange_rate_cny'], '7.0');
            }

            return [
                'id' => (int) $channel->id,
                'name' => $labelKey !== '' ? __($labelKey) : $channel->name,
                'label_key' => $labelKey,
                'code' => $channel->channel_code,
                'exchange_rate' => $exchangeRate,
                'sort' => (int) $channel->sort,
                'is_default' => (int) (!empty($config['is_default'])),
                'min_amount' => (float) ($config['min_amount'] ?? ($legacyMeta['min'] ?: $limits['min'])),
                'max_amount' => (float) ($config['max_amount'] ?? ($legacyMeta['max'] ?: $limits['max'])),
                'type' => $type,
                'type_label_key' => $typeLabelKey,
                'type_label' => __($typeLabelKey),
                'description' => (string) ($config['description'] ?? ''),
            ];
        })->values()->all();
    }

    /**
     * Resolve submitted channel id/code into normalized channel data.
     *
     * @param string $submitted
     * @return array|null
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
            $config = is_array($channel->config) ? $channel->config : [];
            $legacyMeta = $this->legacyChannelMeta($channel);
            $labelKey = (string) ($config['label_key'] ?? '');
            $type = (string) ($config['type'] ?? $legacyMeta['type']);
            $typeLabelKey = (string) ($config['type_label_key'] ?? ($type === 'crypto' ? 'front.channel_type_crypto' : 'front.channel_type_fiat'));
            $exchangeRate = (float) $channel->exchange_rate;
            if ($exchangeRate <= 0) {
                $exchangeRate = $type === 'crypto'
                    ? 1.0
                    : (float) $this->configValue(['deposit_exchange_rate_cny'], '7.0');
            }

            return [
                'id' => (int) $channel->id,
                'name' => $labelKey !== '' ? __($labelKey) : $channel->name,
                'label_key' => $labelKey,
                'code' => $channel->channel_code,
                'exchange_rate' => $exchangeRate,
                'description' => (string) ($config['description'] ?? ''),
                'min_amount' => (float) ($config['min_amount'] ?? $legacyMeta['min']),
                'max_amount' => (float) ($config['max_amount'] ?? $legacyMeta['max']),
                'type' => $type,
                'type_label_key' => $typeLabelKey,
                'type_label' => __($typeLabelKey),
            ];
        }

        foreach ($this->fallbackChannels() as $fallback) {
            if ((string) $fallback['code'] === $submitted || (string) $fallback['id'] === $submitted) {
                return $fallback;
            }
        }

        return null;
    }

    /**
     * Read global amount limits from system_config with conservative defaults.
     *
     * @return array
     */
    private function amountLimits(): array
    {
        return [
            'min' => (float) SystemConfig::getVal('deposit_min_amount', '10.0'),
            'max' => (float) SystemConfig::getVal('deposit_max_amount', '500000.0'),
        ];
    }

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
     * Fallback channels keep a fresh database operational until payment channel
     * rows are configured in the admin module.
     *
     * @return array
     */
    private function fallbackChannels(): array
    {
        return [
            $this->fallbackChannel(1, 'front.channel_one', '1', ['sys_deposit_rate', 'deposit_exchange_rate_cny'], 110, true),
            $this->fallbackChannel(2, 'front.channel_two', '2', ['sys_deposit_rate2', 'deposit_exchange_rate_cny'], 100, false),
            $this->fallbackChannel(3, 'front.channel_three', '3', ['sys_deposit_rate3', 'deposit_exchange_rate_cny'], 90, false),
            $this->fallbackChannel(4, 'front.crypto_currency', '4', ['sys_deposit_rate4'], 80, false),
            $this->fallbackChannel(5, 'front.crypto_currency_two', '5', ['sys_deposit_rate5'], 70, false),
            $this->fallbackChannel(6, 'front.wechat_pay_one', '6', ['sys_deposit_rate6', 'deposit_exchange_rate_cny'], 60, false),
            $this->fallbackChannel(7, 'front.alipay_one', '7', ['sys_deposit_rate7', 'deposit_exchange_rate_cny'], 50, false),
            $this->fallbackChannel(8, 'front.channel_five', '8', ['sys_deposit_rate8', 'deposit_exchange_rate_cny'], 40, false),
            $this->fallbackChannel(9, 'front.channel_six', '9', ['sys_deposit_rate9', 'deposit_exchange_rate_cny'], 30, false),
            $this->fallbackChannel(10, 'front.alipay_two', '10', ['sys_deposit_rate10', 'deposit_exchange_rate_cny'], 20, false),
            $this->fallbackChannel(11, 'front.wechat_pay_two', '11', ['sys_deposit_rate11', 'deposit_exchange_rate_cny'], 10, false),
        ];
    }

    private function fallbackChannel(int $id, string $labelKey, string $code, array $rateKeys, int $sort, bool $default): array
    {
        $limits = $this->amountLimits();
        $meta = $this->legacyChannelMeta((object) ['id' => $id, 'channel_code' => $code]);
        $defaultRate = $meta['type'] === 'crypto' ? '1.0' : '7.0';
        $typeLabelKey = $meta['type'] === 'crypto' ? 'front.channel_type_crypto' : 'front.channel_type_fiat';

        return [
            'id' => $id,
            'name' => __($labelKey),
            'label_key' => $labelKey,
            'code' => $code,
            'exchange_rate' => (float) $this->configValue($rateKeys, $defaultRate),
            'sort' => $sort,
            'is_default' => $default ? 1 : 0,
            'min_amount' => $meta['min'] ?: $limits['min'],
            'max_amount' => $meta['max'] ?: $limits['max'],
            'type' => $meta['type'],
            'type_label_key' => $typeLabelKey,
            'type_label' => __($typeLabelKey),
            'description' => '',
        ];
    }

    private function legacyChannelMeta($channel): array
    {
        $code = (string) ($channel->channel_code ?? '');
        $legacyId = ctype_digit($code) ? (int) $code : (int) ($channel->id ?? 0);
        $min = (float) SystemConfig::getVal('deposit_channel_' . $legacyId . '_min', '0');
        $maxMap = [
            1 => 6800,
            2 => 30000,
            3 => 80000,
            4 => 500000,
            5 => 500000,
            6 => 6800,
            7 => 6800,
            8 => 14000,
            9 => 80000,
            10 => 6800,
            11 => 6800,
        ];

        return [
            'min' => $min,
            'max' => $maxMap[$legacyId] ?? 0,
            'type' => in_array($legacyId, [4, 5], true) ? 'crypto' : 'fiat',
        ];
    }

    private function configValue(array $keys, string $default): string
    {
        foreach ($keys as $key) {
            $value = SystemConfig::getVal($key, null);
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return $default;
    }
}
