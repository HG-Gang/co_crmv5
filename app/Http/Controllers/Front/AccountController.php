<?php

namespace App\Http\Controllers\Front;

use App\Models\CommissionRecord;
use App\Models\DepositRecord;
use App\Models\UserInfo;
use App\Models\UserTrade;
use App\Models\WithdrawRecord;
use App\Models\VoucherInfo;
use App\Constants\ResponseCode;
use App\Support\FrontLegacyData;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * Front Account Management Controller
 * 前台账户管理控制器
 * 
 * Handles account information, balance details, and voucher submissions.
 * 处理账户信息、余额详情和凭证提交。
 */
class AccountController extends FrontBaseController
{
    /**
     * Get current user account info
     * 获取当前用户账户信息（余额、净值、保证金等）
     * 
     * @param Request $request
     * @return JsonResponse
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
     * Get detailed balance breakdown
     * 获取余额变动明细汇总
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function accountBalance(Request $request): JsonResponse
    {
        $userInfo = $this->currentUserInfo($request);

        if (!$userInfo) {
            return $this->error('auth.user_info_not_found', ResponseCode::USER_NOT_FOUND);
        }

        return $this->success($this->accountOverviewData($userInfo), 'response.query_success');
    }

    private function currentUserInfo(Request $request): ?UserInfo
    {
        $userLogin = $request->user('user');

        return $userLogin ? UserInfo::with('login')->where('user_id', $userLogin->user_id)->first() : null;
    }

    private function accountOverviewData(UserInfo $userInfo): array
    {
        $userId = (int) $userInfo->user_id;
        $closedTrades = UserTrade::where('user_id', $userId)->where('close_time', '>', '1971-01-01 00:00:00');
        $openTrades = UserTrade::where('user_id', $userId)->where('close_time', '<=', '1971-01-01 00:00:00');
        $relationIds = UserInfo::where('parent_id', $userId)->pluck('user_id')->toArray();
        $directAgents = UserInfo::where('parent_id', $userId)->where('account_type', 1)->count();
        $directCustomers = UserInfo::where('parent_id', $userId)->where('account_type', 2)->count();
        $indirectCustomers = UserInfo::where('family_tree', 'like', '%,' . $userId . ',%')
            ->where('parent_id', '<>', $userId)
            ->where('account_type', 2)
            ->count();

        return [
            'user_id' => $userInfo->user_id,
            'user_name' => $userInfo->user_name,
            'email' => $userInfo->login ? $userInfo->login->email : '',
            'account_type' => $userInfo->account_type,
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
            'auth_status' => $userInfo->auth_status,
            'total_deposit' => DepositRecord::where('user_id', $userId)->sum('amount'),
            'total_withdraw' => WithdrawRecord::where('user_id', $userId)->sum('apply_amount'),
            'total_rebate' => CommissionRecord::where('agent_id', $userId)->sum('commission_amount'),
            'open_order_count' => (clone $openTrades)->count(),
            'closed_order_count' => (clone $closedTrades)->count(),
            'profit_7d' => (clone $closedTrades)->where('close_time', '>=', now()->subDays(7))->sum('profit'),
            'profit_15d' => (clone $closedTrades)->where('close_time', '>=', now()->subDays(15))->sum('profit'),
            'profit_30d' => (clone $closedTrades)->where('close_time', '>=', now()->subDays(30))->sum('profit'),
            'direct_agents' => $directAgents,
            'direct_customers' => $directCustomers,
            'indirect_customers' => $indirectCustomers,
            'relation_amount' => $relationIds ? DepositRecord::whereIn('user_id', $relationIds)->sum('amount') : 0,
        ];
    }

    /**
     * Upload voucher images for review
     * 上传凭证图片供审核
     * 
     * @param Request $request
     * @return JsonResponse
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

        $userLogin = $request->user('user');
        $userInfo = $userLogin->userInfo;

        if (!$userInfo) {
            return $this->error('auth.user_info_not_found', ResponseCode::USER_NOT_FOUND);
        }

        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                // Store to storage/app/public/vouchers/{user_id}/
                $path = $file->store('vouchers/' . $userInfo->user_id, 'public');
                $imagePaths[] = $path;
            }
        }

        $voucher = VoucherInfo::create([
            'user_id'       => $userInfo->user_id,
            'images'        => implode(',', $imagePaths),
            'remarks'       => $request->input('remarks', ''),
            'review_status' => 0, // 待审核 | Pending
            'created_by'    => $userInfo->user_name,
        ]);

        return $this->success($voucher, 'response.created', ResponseCode::SUCCESS);
    }

    /**
     * List submitted vouchers
     * 获取已提交凭证列表
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function voucherList(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');
        $userInfo = $userLogin->userInfo;

        if (!$userInfo) {
            return $this->error('auth.user_info_not_found', ResponseCode::USER_NOT_FOUND);
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
}
