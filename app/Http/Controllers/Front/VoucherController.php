<?php

namespace App\Http\Controllers\Front;

use App\Models\UserInfo;
use App\Models\VoucherInfo;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class VoucherController extends FrontBaseController
{
    /**
     * 提交凭证 | Submit voucher
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'images'   => 'required|array|min:1',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:5120',
            'remarks'  => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $userLogin = $request->user('user');
        $userInfo = $userLogin->userInfo;

        if (!$userInfo) {
            return $this->error(__('auth.user_info_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                // 存储到 storage/app/public/vouchers/{user_id}/ | Store to storage
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

        return $this->success($voucher, __('response.success'), ResponseCode::SUCCESS);
    }

    /**
     * 获取当前用户的凭证记录 | Get current user's voucher submissions
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function records(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');
        $userInfo = $userLogin->userInfo;

        if (!$userInfo) {
            return $this->error(__('auth.user_info_not_found'), ResponseCode::USER_NOT_FOUND);
        }

        $query = VoucherInfo::where('user_id', $userInfo->user_id);

        if ($request->filled('review_status')) $query->where('review_status', $request->input('review_status'));
        
        if ($request->filled('date_from')) $query->where('created_at', '>=', strtotime($request->input('date_from') . ' 00:00:00'));
        if ($request->filled('date_to')) $query->where('created_at', '<=', strtotime($request->input('date_to') . ' 23:59:59'));

        $records = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return $this->success($records, __('response.query_success'), ResponseCode::SUCCESS);
    }
}
