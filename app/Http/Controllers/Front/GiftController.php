<?php

namespace App\Http\Controllers\Front;

use App\Models\UserAddress;
use App\Models\GiftShipment;
use App\Models\UserInfo;
use App\Constants\ResponseCode;
use App\Support\FrontLegacyData;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * Front Gift Center Controller
 * 前台礼品中心控制器
 * 
 * Handles user addresses and gift redemption/history.
 * 处理用户地址及礼品兑换/历史。
 */
class GiftController extends FrontBaseController
{
    /**
     * List user addresses
     * 获取用户收货地址列表
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function addressList(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');

        // The new user_addresses table follows the old project naming:
        // recipient_name / recipient_phone / recipient_address.  Always order the
        // default address first so the front page can present the active delivery
        // address without additional client-side sorting.
        $query = UserAddress::where('user_id', $userLogin->user_id);

        if ($request->filled('recipient_name') || $request->filled('receiver_name')) {
            $query->where('recipient_name', 'like', '%' . $request->input('recipient_name', $request->input('receiver_name')) . '%');
        }
        if ($request->filled('recipient_phone') || $request->filled('phone')) {
            $query->where('recipient_phone', 'like', '%' . $request->input('recipient_phone', $request->input('phone')) . '%');
        }
        if ($request->filled('is_default')) {
            $query->where('is_default', (int) $request->input('is_default'));
        }
        FrontLegacyData::applyCreatedAtFilter($query, $request);

        $addresses = $query->orderBy('is_default', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return $this->success($addresses, __('response.success'));
    }

    /**
     * Add new address
     * 新增收货地址
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function addAddress(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'recipient_name'    => 'required_without:receiver_name|string|max:500',
            'receiver_name'     => 'required_without:recipient_name|string|max:500',
            'recipient_phone'   => 'required_without:phone|string|max:50',
            'phone'             => 'required_without:recipient_phone|string|max:50',
            'recipient_address' => 'required_without:address|string|max:5000',
            'address'           => 'required_without:recipient_address|string|max:5000',
            'is_default'        => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $userLogin = $request->user('user');

        // Support both the new database column names and older/temporary front-end
        // aliases.  The persisted payload is restricted to real table columns so a
        // request cannot create invalid attributes such as province/city/district.
        $addressData = [
            'user_id'            => $userLogin->user_id,
            'recipient_name'     => $request->input('recipient_name', $request->input('receiver_name', '')),
            'recipient_phone'    => $request->input('recipient_phone', $request->input('phone', '')),
            'recipient_address'  => $request->input('recipient_address', $request->input('address', '')),
            'is_default'         => (int) $request->boolean('is_default'),
        ];

        // Only one address can be the default for the same user.  When the new
        // record is marked default, clear the existing defaults before insertion.
        if ($addressData['is_default'] === 1) {
            UserAddress::where('user_id', $userLogin->user_id)->update(['is_default' => 0]);
        }

        $address = UserAddress::create($addressData);

        return $this->success($address, __('response.created'), ResponseCode::CREATED);
    }

    /**
     * Update address
     * 更新收货地址
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function updateAddress(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id'                => 'required|integer',
            'recipient_name'    => 'sometimes|string|max:500',
            'receiver_name'     => 'sometimes|string|max:500',
            'recipient_phone'   => 'sometimes|string|max:50',
            'phone'             => 'sometimes|string|max:50',
            'recipient_address' => 'sometimes|string|max:5000',
            'address'           => 'sometimes|string|max:5000',
            'is_default'        => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $userLogin = $request->user('user');
        $address = UserAddress::where('user_id', $userLogin->user_id)->where('id', $request->id)->first();
        
        if (!$address) {
            return $this->error(__('response.data_not_found'), ResponseCode::DATA_NOT_FOUND);
        }

        // Build an update payload from the fields that were actually submitted.
        // This preserves existing values when the user edits only one address part.
        $addressData = [];
        if ($request->has('recipient_name') || $request->has('receiver_name')) {
            $addressData['recipient_name'] = $request->input('recipient_name', $request->input('receiver_name', ''));
        }
        if ($request->has('recipient_phone') || $request->has('phone')) {
            $addressData['recipient_phone'] = $request->input('recipient_phone', $request->input('phone', ''));
        }
        if ($request->has('recipient_address') || $request->has('address')) {
            $addressData['recipient_address'] = $request->input('recipient_address', $request->input('address', ''));
        }
        if ($request->has('is_default')) {
            $addressData['is_default'] = (int) $request->boolean('is_default');
        }

        // If this address is promoted to default, clear default status on sibling
        // addresses first to keep the table invariant consistent with the old CRM.
        if (isset($addressData['is_default']) && $addressData['is_default'] === 1) {
            UserAddress::where('user_id', $userLogin->user_id)->where('id', '!=', $request->id)->update(['is_default' => 0]);
        }

        $address->update($addressData);

        return $this->success($address, __('response.updated'));
    }

    /**
     * Delete address
     * 删除收货地址
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteAddress(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $userLogin = $request->user('user');
        $address = UserAddress::where('user_id', $userLogin->user_id)->where('id', $request->id)->first();
        
        if (!$address) {
            return $this->error(__('response.data_not_found'), ResponseCode::DATA_NOT_FOUND);
        }

        $address->delete();

        return $this->success([], __('response.deleted'));
    }

    /**
     * List available gifts / shipped gifts
     * 获取可用礼品及已发货礼品列表
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function giftList(Request $request): JsonResponse
    {
        $userLogin = $request->user('user');
        
        // Shipped gifts (from GiftShipment)
        $shippedGifts = GiftShipment::where('user_id', $userLogin->user_id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 10));

        // Available gifts (dummy list if no GiftInfo model exists)
        $availableGifts = [
            [
                'id'          => 1,
                'name'        => 'VIP Gift Box',
                'description' => 'Exclusive gift for VIP agents',
                'points_cost' => 1000,
                'image_url'   => '/images/gifts/vip_box.png'
            ],
            [
                'id'          => 2,
                'name'        => 'Customized Trading Keyboard',
                'description' => 'Mechanical keyboard for traders',
                'points_cost' => 500,
                'image_url'   => '/images/gifts/keyboard.png'
            ],
        ];

        $name = trim((string) $request->input('name', $request->input('keyword', '')));
        $pointsCost = $request->input('points_cost');
        if ($name !== '' || $pointsCost !== null && $pointsCost !== '') {
            $availableGifts = array_values(array_filter($availableGifts, function (array $gift) use ($name, $pointsCost) {
                if ($name !== '' && stripos((string) $gift['name'], $name) === false) {
                    return false;
                }
                if ($pointsCost !== null && $pointsCost !== '' && (float) $gift['points_cost'] !== (float) $pointsCost) {
                    return false;
                }

                return true;
            }));
        }

        return $this->success([
            'available_gifts' => $availableGifts,
            'shipped_gifts'   => $shippedGifts,
        ], 'response.query_success');
    }
}
