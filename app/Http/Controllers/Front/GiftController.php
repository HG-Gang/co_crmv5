<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 12:52
 */

namespace App\Http\Controllers\Front;

use App\Models\UserAddress;
use App\Models\GiftShipment;
use App\Models\GiftItem;
use App\Models\UserInfo;
use App\Constants\ResponseCode;
use App\Support\FrontLegacyData;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * 前台礼品中心控制器。
 *
 * 文件功能：
 * - 处理收货地址列表、地址新增、地址更新、地址删除、礼品列表和礼品发货历史。
 * - 地址数据来源为 user_addresses 表，按当前登录用户隔离，避免代理商或普通客户读取他人的收货资料。
 * - 礼品发货历史来源为 gift_shipments 表，可兑换礼品列表来源为 gift_items 礼品配置表。
 *
 * 安全边界：
 * - 收货地址按 user_id 隔离，新增/更新/删除都必须命中当前登录用户自己的记录，防止越权操作他人地址。
 * - 同一用户只能保留一个默认收货地址，默认地址唯一性在事务内维护。
 * - 用户第一条地址强制为默认地址，保证礼品寄送始终有可用的默认收货地址。
 */
class GiftController extends FrontBaseController
{
    /**
     * addressList 用于返回当前用户收货地址列表。
     *
     * 参数逻辑说明：
     * - recipient_name 表示收货人姓名，对应 user_addresses.recipient_name。
     * - receiver_name 表示旧前台提交的收货人姓名别名，会映射到 recipient_name 查询。
     * - recipient_phone 表示收货人手机号，对应 user_addresses.recipient_phone。
     * - phone 表示旧前台提交的手机号别名，会映射到 recipient_phone 查询。
     * - is_default 表示是否为默认收货地址，1=默认地址，0=普通地址。
     *
     * @param Request $request 当前 HTTP 请求对象，承载筛选字段、登录用户 Token 和旧前台兼容参数。
     * @return JsonResponse 当前用户可见的收货地址列表响应。
     */
    public function addressList(Request $request): JsonResponse
    {
        $validator = Validator::make($request->only('is_default'), [
            'is_default' => 'sometimes|boolean',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $userId = $this->legacyFrontUserId($request);
        if ($userId <= 0) {
            return $this->legacyFrontAuthError($request);
        }

        // user_addresses 表沿用旧项目收货字段：recipient_name、recipient_phone、recipient_address。
        // 默认地址必须排在最前面，前台页面才能直接展示当前有效收货地址，无需再做客户端排序。
        $query = UserAddress::where('user_id', $userId);

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
     * addressSearch 用于兼容旧前台收货地址分页搜索入口。
     *
     * 参数逻辑说明：
     * - recipient_name / receiver_name 表示收货人姓名筛选字段，receiver_name 是旧页面别名。
     * - recipient_phone / phone 表示收货手机号筛选字段，phone 是旧页面别名。
     * - page、limit、per_page 表示分页参数，由 FrontLegacyData::perPage 统一转换。
     *
     * @param Request $request 当前 HTTP 请求对象，承载旧 Layui 表格分页和筛选参数。
     * @return JsonResponse 兼容旧前台表格结构的分页地址列表响应。
     */
    public function addressSearch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->only('is_default'), [
            'is_default' => 'sometimes|boolean',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $userId = $this->legacyFrontUserId($request);
        if ($userId <= 0) {
            return $this->legacyFrontAuthError($request);
        }

        // 旧前台地址表格的每一行都携带 gift_allowed，用于判断当前用户是否具备领取礼品资格，
        // 页面据此决定是否渲染礼品区；数据来源为 user_infos.is_gift_allowed。
        $userInfo = UserInfo::where('user_id', $userId)->first();
        $giftAllowed = $userInfo ? (int) $userInfo->is_gift_allowed : 0;

        $query = UserAddress::where('user_id', $userId);

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
            ->orderBy('updated_at', 'desc')
            ->paginate(FrontLegacyData::perPage($request))
            ->through(function (UserAddress $address) use ($giftAllowed) {
                $row = $address->toArray();
                $row['rec_id'] = $address->id;
                $row['gift_allowed'] = $giftAllowed;
                $row['updated_at'] = FrontLegacyData::dateTime($address->updated_at);

                return $row;
            });

        return $this->success(
            FrontLegacyData::paginatedListResponse($addresses),
            __('response.query_success'),
            ResponseCode::SUCCESS
        );
    }

    /**
     * addAddress 用于新增当前用户收货地址。
     *
     * 参数逻辑说明：
     * - recipient_name 表示收货人姓名，新版前台提交该字段。
     * - receiver_name 表示旧前台提交的收货人姓名别名，会写入 recipient_name。
     * - recipient_phone 表示收货人手机号，新版前台提交该字段。
     * - phone 表示旧前台提交的手机号别名，会写入 recipient_phone。
     * - recipient_address 表示完整收货地址，新版前台提交该字段。
     * - address 表示旧前台提交的完整地址别名，会写入 recipient_address。
     * - is_default 表示是否为默认收货地址；同一用户只能保留一个默认收货地址。
     *
     * @param Request $request 当前 HTTP 请求对象，承载新增地址表单字段和登录用户身份。
     * @return JsonResponse 新增后的收货地址数据响应。
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

        $userId = $this->legacyFrontUserId($request);
        if ($userId <= 0) {
            return $this->legacyFrontAuthError($request);
        }

        // 同时兼容新字段和旧前台别名，最终只写入真实表字段，避免 province/city/district 等无效属性被保存。
        $addressData = [
            'user_id'            => $userId,
            'recipient_name'     => $request->input('recipient_name', $request->input('receiver_name', '')),
            'recipient_phone'    => $request->input('recipient_phone', $request->input('phone', '')),
            'recipient_address'  => $request->input('recipient_address', $request->input('address', '')),
            'is_default'         => (int) $request->boolean('is_default'),
        ];

        // 用户首条地址强制为默认地址，保证礼品寄送始终有可用的默认收货地址。
        if ($addressData['is_default'] === 0
            && !UserAddress::where('user_id', $userId)->exists()) {
            return $this->error(
                'response.default_address_must_exist',
                ResponseCode::DEFAULT_ADDRESS_MUST_EXIST
            );
        }

        $address = DB::transaction(function () use ($addressData, $userId) {
            if ($addressData['is_default'] === 1) {
                UserAddress::where('user_id', $userId)->update(['is_default' => 0]);
            }

            return UserAddress::create($addressData);
        });

        return $this->success($address, __('response.created'), ResponseCode::CREATED);
    }

    /**
     * updateAddress 用于更新当前用户已有收货地址。
     *
     * 参数逻辑说明：
     * - id 表示 user_addresses.id，必须属于当前登录用户，避免越权编辑其它用户地址。
     * - address 表示路由参数中的地址 ID；当 URL 传入地址 ID 时会合并为 id 参数。
     * - recipient_name / receiver_name 表示收货人姓名及旧前台别名。
     * - recipient_phone / phone 表示收货人手机号及旧前台别名。
     * - recipient_address / address 表示完整收货地址及旧前台别名。
     * - is_default 表示是否提升为默认收货地址；提升时会清理同用户其它默认地址。
     *
     * @param Request $request 当前 HTTP 请求对象，承载地址更新字段和当前登录用户身份。
     * @param int $address 路由传入的收货地址 ID，兼容 REST 风格地址更新入口。
     * @return JsonResponse 更新后的收货地址数据响应。
     */
    public function updateAddress(Request $request, int $address = 0): JsonResponse
    {
        if ($address > 0) {
            $request->merge(['id' => $address]);
        }

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

        $userId = $this->legacyFrontUserId($request);
        if ($userId <= 0) {
            return $this->legacyFrontAuthError($request);
        }

        $addressRecord = UserAddress::where('user_id', $userId)->where('id', $request->id)->first();
        
        if (!$addressRecord) {
            return $this->error(__('response.data_not_found'), ResponseCode::DATA_NOT_FOUND);
        }

        // 只把本次实际提交的字段写入更新 payload；用户只编辑某一段地址时，其它旧值必须保留。
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

        if (($addressData['is_default'] ?? null) === 0
            && (int) $addressRecord->is_default === 1
            && !UserAddress::where('user_id', $userId)
                ->where('id', '!=', $addressRecord->id)
                ->where('is_default', 1)
                ->exists()) {
            return $this->error(
                'response.default_address_must_exist',
                ResponseCode::DEFAULT_ADDRESS_MUST_EXIST
            );
        }

        DB::transaction(function () use ($addressData, $addressRecord, $userId) {
            if (($addressData['is_default'] ?? null) === 1) {
                UserAddress::where('user_id', $userId)
                    ->where('id', '!=', $addressRecord->id)
                    ->update(['is_default' => 0]);
            }

            $addressRecord->update($addressData);
        });

        return $this->success($addressRecord, __('response.updated'));
    }

    /**
     * addressUpdate 用于兼容旧前台地址新增或编辑统一入口。
     *
     * 参数逻辑说明：
     * - id 表示新版提交的地址 ID，rec_id 表示旧前台表格行 ID，两者都会归一为 id。
     * - receiver_name、phone、address 是旧前台别名，会分别归一到 recipient_name、recipient_phone、recipient_address。
     * - is_default 表示是否默认地址；未传入时按普通地址处理。
     *
     * @param Request $request 当前 HTTP 请求对象，承载旧前台统一提交的新增或编辑参数。
     * @return JsonResponse 新增或更新后的收货地址响应。
     */
    public function addressUpdate(Request $request): JsonResponse
    {
        $request->merge([
            'id' => $request->input('id', $request->input('rec_id')),
            'recipient_name' => $request->input('recipient_name', $request->input('receiver_name')),
            'recipient_phone' => $request->input('recipient_phone', $request->input('phone')),
            'recipient_address' => $request->input('recipient_address', $request->input('address')),
            'is_default' => $request->input('is_default', 0),
        ]);

        if ((int) $request->input('id') <= 0) {
            $request->request->remove('id');

            return $this->addAddress($request);
        }

        return $this->updateAddress($request);
    }

    /**
     * deleteAddress 用于删除当前用户自己的收货地址。
     *
     * 参数逻辑说明：
     * - id 表示 user_addresses.id，只允许删除当前登录用户名下的记录。
     * - address 表示路由传入的地址 ID，会合并为 id 参数以兼容 REST 风格删除入口。
     *
     * @param Request $request 当前 HTTP 请求对象，承载待删除地址 ID 和登录用户身份。
     * @param int $address 路由传入的收货地址 ID。
     * @return JsonResponse 删除成功后的空数据响应。
     */
    public function deleteAddress(Request $request, int $address = 0): JsonResponse
    {
        if ($address > 0) {
            $request->merge(['id' => $address]);
        }

        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $userId = $this->legacyFrontUserId($request);
        if ($userId <= 0) {
            return $this->legacyFrontAuthError($request);
        }

        $addressRecord = UserAddress::where('user_id', $userId)->where('id', $request->id)->first();
        
        if (!$addressRecord) {
            return $this->error(__('response.data_not_found'), ResponseCode::DATA_NOT_FOUND);
        }

        if ((int) $addressRecord->is_default === 1
            && !UserAddress::where('user_id', $userId)
                ->where('id', '!=', $addressRecord->id)
                ->where('is_default', 1)
                ->exists()) {
            return $this->error(
                'response.default_address_must_exist',
                ResponseCode::DEFAULT_ADDRESS_MUST_EXIST
            );
        }

        $addressRecord->delete();

        return $this->success([], __('response.deleted'));
    }

    /**
     * giftList 用于返回可兑换礼品和已发货礼品。
     *
     * 参数逻辑说明：
     * - name 表示礼品名称筛选字段，新版前台优先使用。
     * - keyword 表示旧前台礼品关键词别名，会映射到 name。
     * - points_cost 表示所需积分筛选字段，用于精确匹配可兑换礼品积分成本。
     * - available_gifts 表示前台可展示的可兑换礼品列表。
     * - shipped_gifts 表示当前用户已发货礼品记录。
     *
     * @param Request $request 当前 HTTP 请求对象，承载礼品筛选参数和登录用户身份。
     * @return JsonResponse 可兑换礼品和当前用户发货历史组合响应。
     */
    public function giftList(Request $request): JsonResponse
    {
        $validator = Validator::make($request->only('points_cost'), [
            'points_cost' => 'sometimes|integer|min:0',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $userId = $this->legacyFrontUserId($request);
        if ($userId <= 0) {
            return $this->legacyFrontAuthError($request);
        }
        
        // shipped_gifts 表示当前用户已发货礼品记录，数据来源为 gift_shipments 表。
        $shippedGifts = $this->giftShipmentQuery($userId, $request)
            ->orderBy('updated_at', 'desc')
            ->paginate(FrontLegacyData::perPage($request, 10))
            ->through(function (GiftShipment $shipment) {
                return $this->giftShipmentRow($shipment);
            });

        // available_gifts 表示前台可展示的可兑换礼品列表，数据来源为 gift_items，停用或无库存礼品不进入前台。
        $name = trim((string) $request->input('name', $request->input('keyword', '')));
        $pointsCost = $request->input('points_cost');

        $availableGiftQuery = GiftItem::query()
            ->available()
            ->select(['id', 'name', 'description', 'points_cost', 'stock_quantity', 'status', 'image_url', 'updated_at']);
        if ($name !== '') {
            $availableGiftQuery->where('name', 'like', '%' . $name . '%');
        }
        if ($pointsCost !== null && $pointsCost !== '') {
            $availableGiftQuery->where('points_cost', (int) $pointsCost);
        }
        $availableGifts = $availableGiftQuery
            ->orderBy('points_cost')
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get()
            ->map(function (GiftItem $gift) {
                return [
                    'id' => (int) $gift->id,
                    'name' => (string) $gift->name,
                    'description' => (string) $gift->description,
                    'points_cost' => (int) $gift->points_cost,
                    'stock_quantity' => (int) $gift->stock_quantity,
                    'status' => (int) $gift->status,
                    'image_url' => (string) $gift->image_url,
                ];
            })
            ->values()
            ->all();

        return $this->success([
            'available_gifts' => $availableGifts,
            'shipped_gifts'   => $shippedGifts,
        ], 'response.query_success');
    }

    /**
     * giftSearch 用于兼容旧前台礼品发货记录搜索入口。
     *
     * 参数逻辑说明：
     * - recipient_name 表示收货人姓名筛选字段，对应 gift_shipments.recipient_name。
     * - gift_name 表示礼品名称筛选字段，对应 gift_shipments.gift_name。
     * - shipped_at 表示礼品发货时间字段，日期范围筛选由 FrontLegacyData::applyCreatedAtFilter 统一处理。
     * - page、limit、per_page 表示旧前台 Layui 表格分页参数。
     *
     * @param Request $request 当前 HTTP 请求对象，承载发货记录筛选、分页参数和登录用户身份。
     * @return JsonResponse 兼容旧前台表格结构的礼品发货记录分页响应。
     */
    public function giftSearch(Request $request): JsonResponse
    {
        $userId = $this->legacyFrontUserId($request);
        if ($userId <= 0) {
            return $this->legacyFrontAuthError($request);
        }

        $shipments = $this->giftShipmentQuery($userId, $request)
            ->orderBy('updated_at', 'desc')
            ->paginate(FrontLegacyData::perPage($request))
            ->through(function (GiftShipment $shipment) {
                return $this->giftShipmentRow($shipment);
            });

        return $this->success(
            FrontLegacyData::paginatedListResponse($shipments),
            __('response.query_success'),
            ResponseCode::SUCCESS
        );
    }

    /**
     * 构建当前用户礼品发货记录查询。
     *
     * 数据来源为 gift_shipments 表，按 user_id 隔离归属；支持收货人姓名、
     * 礼品名称模糊筛选与 shipped_at 日期范围筛选（复用 FrontLegacyData 兼容旧字段）。
     *
     * @param int $userId 当前登录业务用户 ID，限定发货记录归属。
     * @param Request $request 当前 HTTP 请求对象，读取 recipient_name、gift_name 与时间筛选参数。
     * @return \Illuminate\Database\Eloquent\Builder 已带筛选条件的 GiftShipment 查询构造器。
     */
    private function giftShipmentQuery(int $userId, Request $request)
    {
        $query = GiftShipment::where('user_id', $userId);

        if ($request->filled('recipient_name')) {
            $query->where('recipient_name', 'like', '%' . trim((string) $request->input('recipient_name')) . '%');
        }
        if ($request->filled('gift_name')) {
            $query->where('gift_name', 'like', '%' . trim((string) $request->input('gift_name')) . '%');
        }
        FrontLegacyData::applyDateTimeFilter($query, $request, 'shipped_at');

        return $query;
    }

    /**
     * 把发货记录模型转换为旧前台表格行结构。
     *
     * 补充 rec_id 行标识、gift_quantity 整数化，并把 shipped_at 统一为
     * 旧前台展示格式，供 Layui 表格直接渲染。
     *
     * @param GiftShipment $shipment 礼品发货记录模型。
     * @return array<string, mixed> 含 rec_id 与格式化 shipped_at 的行数据。
     */
    private function giftShipmentRow(GiftShipment $shipment): array
    {
        $row = $shipment->toArray();
        $row['rec_id'] = $shipment->id;
        $row['gift_quantity'] = (int) $shipment->gift_quantity;
        $row['shipped_at'] = FrontLegacyData::dateTime($shipment->shipped_at);

        return $row;
    }
}
