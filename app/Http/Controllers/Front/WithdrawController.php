<?php

namespace App\Http\Controllers\Front;

use App\Models\SystemConfig;
use App\Models\UserInfo;
use App\Models\WithdrawRecord;
use App\Models\UserTrade;
use App\Models\UserAddress;
use App\Models\UserAuth;
use App\Constants\ResponseCode;
use App\Support\FrontLegacyData;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Front Withdraw Management Controller
 * 前台出金管理控制器
 * 
 * Handles withdrawal page data, withdrawal requests, and history.
 * 处理出金页面数据、出金申请和历史记录。
 */
class WithdrawController extends FrontBaseController
{
    /**
     * Get withdraw page data (bank info, rates, limits)
     * 获取出金页面数据（银行卡信息、汇率、限制）
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function withdrawPage(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');
        $userInfo = $userLogin->userInfo;

        if (!$userInfo) {
            return $this->error(__('auth.user_info_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        $addresses = UserAddress::where('user_id', $userInfo->user_id)->get();
        $auth = UserAuth::where('user_id', $userInfo->user_id)->first();
        $availability = $this->withdrawAvailability($userInfo);
        $exchangeRate = (float) SystemConfig::getVal('withdraw_exchange_rate_cny', '6.8');

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
            'fee_rate'        => (float)SystemConfig::getVal('withdrawal_fee_rate', '0'),
            'fixed_fee'       => (float)SystemConfig::getVal('withdrawal_fixed_fee_usd', '0'),
            'risk_rate_limit' => (float)SystemConfig::getVal('withdraw_risk_rate_limit', '100.0'),
            'time_window'     => [
                'start' => (string) SystemConfig::getVal('withdrawal_start_time', ''),
                'end' => (string) SystemConfig::getVal('withdrawal_end_time', ''),
            ],
        ];

        return $this->success($data, 'response.query_success');
    }

    /**
     * Submit withdrawal request
     * 提交出金申请
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function submitWithdraw(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'nullable|numeric|min:0.01',
            'withdraw_amt' => 'nullable|numeric|min:0.01',
            'password' => 'nullable|string',
            'withdraw_password' => 'nullable|string',
            'withdraw_psw' => 'nullable|string',
            'agree' => 'nullable',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $userLogin = $request->user('user');
        $userInfo = $userLogin->userInfo;

        if (!$userInfo) {
            return $this->error(__('auth.user_info_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        $amount = (float) $request->input('amount', $request->input('withdraw_amt'));
        if ($amount <= 0) {
            return $this->error(__('validation.required', ['attribute' => __('front.withdraw_amount')]), ResponseCode::VALIDATION_FAILED);
        }

        $password = (string) $request->input('password', $request->input('withdraw_password', $request->input('withdraw_psw', '')));
        if ($password === '' || !Hash::check($password, $userLogin->password)) {
            return $this->error('auth.old_password_error', ResponseCode::OLD_PASSWORD_WRONG);
        }

        if (!$request->boolean('agree')) {
            return $this->error('front.withdrawal_terms_required', ResponseCode::VALIDATION_FAILED);
        }

        $availability = $this->withdrawAvailability($userInfo);
        if (!$availability['allowed']) {
            return $this->error($availability['message'] ?: __('response.withdrawal_not_allowed'), ResponseCode::OPERATION_NOT_ALLOWED);
        }

        $minAmount = (float) SystemConfig::getVal('withdraw_min_amount', '50.0');
        $maxAmount = (float) SystemConfig::getVal('withdraw_max_amount', '50000.0');

        if ($amount < $minAmount || $amount > $maxAmount) {
            return $this->error(__('validation.between.numeric', [
                'attribute' => __('front.withdraw_amount'),
                'min' => $minAmount,
                'max' => $maxAmount,
            ]), ResponseCode::VALIDATION_FAILED);
        }

        // 检查风险率 | Check Risk Ratio
        if ($userInfo->risk_ratio > 0 && $userInfo->risk_ratio < (float)SystemConfig::getVal('withdraw_risk_rate_limit', '100.0')) {
             return $this->error(__('response.risk_rate_exceeded'), ResponseCode::RISK_RATE_EXCEEDED);
        }

        if ($this->withdrawableAmount($userInfo) < $amount) {
             return $this->error(__('response.insufficient_balance'), ResponseCode::INSUFFICIENT_BALANCE);
        }

        // 检查是否有持仓（风险考虑） | Check open positions (risk rate)
        $hasOpen = UserTrade::where('user_id', $userInfo->user_id)
            ->where('close_time', '1970-01-01 00:00:00')
            ->exists();
        if ($hasOpen && SystemConfig::getVal('withdraw_check_open', '1') === '1') {
            return $this->error(__('response.risk_rate_exceeded'), ResponseCode::RISK_RATE_EXCEEDED);
        }

        // 计算手续费 | Calculate fee
        $feeRate = (float)SystemConfig::getVal('withdrawal_fee_rate', '0');
        $fixedFee = (float)SystemConfig::getVal('withdrawal_fixed_fee_usd', '0');
        $fee = $fixedFee + $amount * ($feeRate / 100);
        $exchangeRate = (float)SystemConfig::getVal('withdraw_exchange_rate_cny', '6.8');
        $auth = UserAuth::where('user_id', $userInfo->user_id)->first();

        $localOrderNo = 'WDR' . date('YmdHis') . Str::upper(Str::random(6));

        $withdraw = WithdrawRecord::create([
            'user_id'        => $userInfo->user_id,
            'user_name'      => $userInfo->user_name,
            'apply_amount'   => $amount,
            'fee'            => $fee,
            'actual_amount'  => max(0, $amount - $fee),
            'exchange_rate'  => $exchangeRate,
            'rmb_fee'        => $fee * $exchangeRate,
            'bank_no'        => $auth ? $auth->bank_no : '',
            'bank_name'      => $auth ? $auth->bank_name : '',
            'bank_addr'      => $auth ? $auth->bank_addr : '',
            'status'         => 0, // 待审核 | Pending
            'local_order_no' => $localOrderNo,
            'reject_reason'  => 'WBIN-' . $userInfo->user_id . '-#' . $localOrderNo,
            'created_by'     => $userInfo->user_name,
        ]);

        return $this->success($withdraw, __('response.created'), ResponseCode::CREATED);
    }

    /**
     * List withdrawal records
     * 获取出金记录历史
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function withdrawHistory(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');
        $userInfo = $userLogin->userInfo;

        if (!$userInfo) {
            return $this->error(__('auth.user_info_not_found'), ResponseCode::USER_NOT_FOUND);
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
                $row['order_no'] = $record->local_order_no;
                $row['userId'] = $record->user_id;
                $row['userName'] = $record->user_name;
                $row['withdrawalType'] = $record->status;
                $row['withdrawalType2'] = $record->bank_name;
                $row['withdrawalActProfit'] = FrontLegacyData::money($record->actual_amount ?: $record->apply_amount);
                $row['applyamount'] = FrontLegacyData::money($record->apply_amount);
                $row['actdraw'] = FrontLegacyData::money($record->actual_amount ?: $record->apply_amount);
                $row['drawpoundage'] = FrontLegacyData::money($record->fee);
                $row['drawrate'] = $record->exchange_rate;
                $row['drawbankno'] = $record->bank_no;
                $row['drawbankclass'] = $record->bank_name;
                $row['applystatus'] = $record->status;
                $row['status_text'] = FrontLegacyData::withdrawStatusText($record->status);
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
     * Legacy method for store
     */
    public function store(Request $request): JsonResponse
    {
        return $this->submitWithdraw($request);
    }

    /**
     * Legacy method for records
     */
    public function records(Request $request): JsonResponse
    {
        return $this->withdrawHistory($request);
    }

    private function withdrawableAmount(UserInfo $userInfo): float
    {
        $balance = (float) $userInfo->total_funds;
        $availableMargin = (float) $userInfo->avail_margin;
        $available = $availableMargin > 0 ? min($balance, $availableMargin) : $balance;

        return FrontLegacyData::money(max(0, $available));
    }

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
