<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 21:31
 */

namespace App\Http\Controllers\Admin;

use App\Constants\ResponseCode;
use App\Models\GiftItem;
use App\Models\GiftShipment;
use App\Models\OperationLog;
use App\Models\UserAddress;
use App\Models\UserInfo;
use App\Services\AdminDataScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * 后台礼品发放/发货控制器。
 *
 * 文件功能：
 * - 旧项目 `GiftController` 负责礼品发放、发货列表、可发放用户地址和导出。
 * - 新项目已基于真实表 `gift_shipments`、`user_addresses`、`user_infos` 实现发货列表、可发放地址列表、批量发放、物流更新、礼品配置 CRUD 和当前筛选 CSV 导出。
 * - 库存/积分联动边界：旧项目 `send_gift` 只写发货记录，不存在 `gift_items` 目录表，无任何扣库存/扣积分逻辑；
 *   `gift_items`（points_cost/stock_quantity）为新项目新增目录能力，仅用于前台 `available_gifts` 展示（只读）。
 *   第一阶段与旧项目行为保持一致：`sendGift` 不扣库存、不扣积分，前台无兑换/领取入口（见 GiftStockDeductionBoundaryClosureModuleTest）。
 *
 * 适用场景：
 * - 后台礼品管理页面：发货列表、可发放地址列表、批量发放、物流更新、礼品配置 CRUD 与 CSV 导出。
 * - 礼品发货记录与收货地址均按业务用户归属（user_id）做数据范围过滤，列表与导出共用同一口径。
 */
class GiftController extends AdminBaseController
{
    /**
     * 后台数据范围服务；礼品记录和收货地址都按业务用户归属过滤。
     *
     * @var AdminDataScopeService
     */
    protected $adminDataScopeService;

    /**
     * 构造后台礼品控制器。
     *
     * @param AdminDataScopeService $adminDataScopeService 数据范围服务，用于按业务用户归属过滤礼品记录和收货地址。
     */
    public function __construct(AdminDataScopeService $adminDataScopeService)
    {
        $this->adminDataScopeService = $adminDataScopeService;
    }

    /**
     * 查询礼品发货列表。
     *
     * 参数逻辑说明：
     * - user_id：业务用户 ID，对应 `gift_shipments.user_id`。
     * - gift_name：礼品名称，对应 `gift_shipments.gift_name`，支持模糊匹配。
     * - recipient_name：收件人名称，对应 `gift_shipments.recipient_name`，支持模糊匹配。
     * - start_date/end_date：发货时间范围，对应 `gift_shipments.shipped_at`。
     * - page/per_page/limit：分页参数，兼容 Layui 表格默认提交的 `page` 和 `limit`。
     *
     * @param Request $request 当前请求对象，承载筛选条件和分页参数。
     * @return \Illuminate\Http\JsonResponse
     */
    public function shipmentList(Request $request)
    {
        if ($filterError = $this->validateShipmentFilters($request)) {
            return $filterError;
        }

        $query = $this->shipmentQuery($request);

        return $this->success(
            $query->orderByDesc('gift_shipments.updated_at')->paginate($this->perPage($request)),
            __('admin.gift_shipments_fetched')
        );
    }

    /**
     * 导出当前筛选条件下的礼品发货记录 CSV。
     *
     * 参数逻辑说明：
     * - user_id、gift_name、recipient_name、start_date、end_date 与发货列表筛选保持一致。
     * - 当前导出只输出真实 gift_shipments 和 admins 可支撑字段，不伪造旧项目库存/兑换规则。
     *
     * @param Request $request 当前请求对象，承载筛选条件。
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportGiftShipments(Request $request)
    {
        $records = $this->shipmentExportRecords($request);
        if ($records instanceof JsonResponse) {
            return $records;
        }

        $rows = [
            ['id', 'user_id', 'address_id', 'gift_name', 'gift_quantity', 'recipient_name', 'recipient_phone', 'recipient_address', 'sender_name', 'tracking_number', 'status', 'remark', 'admin_id', 'admin_name', 'shipped_at', 'created_at', 'updated_at'],
        ];

        $records->each(function ($record) use (&$rows) {
                $rows[] = [
                    $record->id,
                    $record->user_id,
                    $record->address_id,
                    $record->gift_name,
                    $record->gift_quantity,
                    $record->recipient_name,
                    $record->recipient_phone,
                    $record->recipient_address,
                    $record->sender_name,
                    $record->tracking_number,
                    $record->status,
                    $record->remark,
                    $record->admin_id,
                    $record->admin_name,
                    $record->shipped_at,
                    $record->created_at,
                    $record->updated_at,
                ];
        });

        return $this->csvDownload('gift_shipments_export.csv', $rows);
    }

    /**
     * 查询可发放礼品的用户默认地址列表。
     *
     * 参数逻辑说明：
     * - user_id：业务用户 ID，对应 `user_addresses.user_id`。
     * - recipient_name：收件人名称，对应 `user_addresses.recipient_name`，支持模糊匹配。
     * - recipient_phone：收件人手机号，对应 `user_addresses.recipient_phone`，支持模糊匹配。
     * - is_default：是否默认地址，对应 `user_addresses.is_default`。
     *
     * @param Request $request 当前请求对象，承载筛选条件和分页参数。
     * @return \Illuminate\Http\JsonResponse
     */
    public function addressList(Request $request)
    {
        if ($userIdError = $this->validateUserIdFilter($request)) {
            return $userIdError;
        }

        if ($addressFilterError = $this->validateAddressFilters($request)) {
            return $addressFilterError;
        }

        $query = UserAddress::query()
            ->leftJoin('user_infos', 'user_infos.user_id', '=', 'user_addresses.user_id')
            ->where('user_infos.is_gift_allowed', 1)
            ->whereNull('user_infos.deleted_at')
            ->select([
                'user_addresses.id',
                'user_addresses.user_id',
                'user_infos.user_name',
                'user_infos.account_type',
                'user_addresses.recipient_name',
                'user_addresses.recipient_phone',
                'user_addresses.recipient_address',
                'user_addresses.is_default',
                'user_addresses.created_at',
                'user_addresses.updated_at',
            ]);

        // 地址归属字段是 user_addresses.user_id，必须与发货列表使用同一范围口径。
        if ($admin = $request->user('admin')) {
            $query = $this->adminDataScopeService->apply(
                $query,
                $admin,
                'user',
                'user_addresses.user_id',
                null,
                'user_infos.created_by'
            );
        }

        $this->applyAddressFilters($query, $request);

        return $this->success(
            $query->orderByDesc('user_addresses.is_default')->orderByDesc('user_addresses.updated_at')->paginate($this->perPage($request)),
            __('admin.gift_addresses_fetched')
        );
    }

    /**
     * 发放礼品并写入发货记录。
     *
     * 参数逻辑说明：
     * - recipients：收件地址数组，每项包含 user_id、address_id、recipient_name、recipient_phone、recipient_address。
     * - sender_name：发件人名称，写入 `gift_shipments.sender_name`。
     * - gift_name：礼品名称，写入 `gift_shipments.gift_name`。
     * - gift_quantity：礼品数量，必须大于等于 1。
     * - tracking_number：物流单号，可为空；为空时仍可创建待补单号的发货记录。
     * - remark：发放备注。
     *
     * @param Request $request 当前请求对象，承载礼品和收件人参数。
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendGift(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sender_name' => 'required|string|max:100',
            'gift_name' => 'required|string|max:200',
            'gift_quantity' => 'required|integer|min:1',
            'tracking_number' => 'nullable|string|max:100',
            'remark' => 'nullable|string|max:500',
            'recipients' => 'required|array|min:1',
            'recipients.*.user_id' => 'required|integer|min:1',
            'recipients.*.address_id' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $admin = $request->user('admin');
        if (!$admin) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }
        $adminId = (int) $admin->id;
        $payload = $validator->validated();

        $userIds = array_values(array_unique(array_map(static function (array $recipient): int {
            return (int) $recipient['user_id'];
        }, $payload['recipients'])));
        $addressIds = array_values(array_unique(array_map(static function (array $recipient): int {
            return (int) $recipient['address_id'];
        }, $payload['recipients'])));

        $users = UserInfo::query()
            ->whereIn('user_id', $userIds)
            ->get(['user_id', 'created_by', 'is_gift_allowed'])
            ->keyBy('user_id');
        $addresses = UserAddress::query()
            ->whereIn('id', $addressIds)
            ->where('is_default', 1)
            ->get([
                'id',
                'user_id',
                'recipient_name',
                'recipient_phone',
                'recipient_address',
                'is_default',
            ])
            ->keyBy('id');

        // 先完成整批真实用户、默认地址与数据范围校验，再开启事务，保证失败批次零写入。
        $validatedRecipients = [];
        foreach ($payload['recipients'] as $recipient) {
            $userId = (int) $recipient['user_id'];
            $user = $users->get($userId);
            if (!$user) {
                return $this->error(__('response.data_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            if (!$this->adminDataScopeService->canAccessRecord(
                $admin,
                $userId,
                $user->created_by,
                'user'
            )) {
                return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
            }

            $address = $addresses->get((int) $recipient['address_id']);
            if (!$address
                || (int) $address->user_id !== $userId
                || (int) $user->is_gift_allowed !== 1) {
                return $this->error(__('response.data_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $validatedRecipients[] = [
                'user_id' => $userId,
                'address_id' => (int) $address->id,
                'recipient_name' => (string) $address->recipient_name,
                'recipient_phone' => (string) $address->recipient_phone,
                'recipient_address' => (string) $address->recipient_address,
            ];
        }

        $legacySend = $request->attributes->get('legacy_admin_uri') === 'index/admin/gift/send_gift';
        $trackingNumber = (string) ($payload['tracking_number'] ?? '');
        if ($legacySend && trim($trackingNumber) === '') {
            $trackingNumber = '0';
        }
        $status = $legacySend ? 1 : (trim($trackingNumber) === '' ? 0 : 1);

        $created = DB::transaction(function () use (
            $payload,
            $validatedRecipients,
            $trackingNumber,
            $status,
            $adminId,
            $admin,
            $request
        ) {
            $rows = [];

            foreach ($validatedRecipients as $recipient) {
                $rows[] = GiftShipment::create([
                    'user_id' => (int) $recipient['user_id'],
                    'address_id' => (int) $recipient['address_id'],
                    'recipient_name' => $recipient['recipient_name'],
                    'recipient_phone' => $recipient['recipient_phone'],
                    'recipient_address' => $recipient['recipient_address'],
                    'sender_name' => $payload['sender_name'],
                    'gift_name' => $payload['gift_name'],
                    'gift_quantity' => (int) $payload['gift_quantity'],
                    'tracking_number' => $trackingNumber,
                    'remark' => $payload['remark'] ?? '',
                    'status' => $status,
                    'admin_id' => $adminId,
                    'shipped_at' => now()->format('Y-m-d H:i:s'),
                ]);
            }

            $this->writeGiftOperationLog(
                $request,
                $admin,
                'gift_send',
                null,
                sprintf(
                    'Send gift gift_name:%s; gift_quantity:%s; recipients:%s; tracking_number:%s; shipment_ids:%s',
                    $payload['gift_name'],
                    (int) $payload['gift_quantity'],
                    implode(',', array_map(static function (array $recipient) {
                        return (string) (int) $recipient['user_id'];
                    }, $validatedRecipients)),
                    $trackingNumber,
                    implode(',', array_map(static function (GiftShipment $shipment) {
                        return (string) $shipment->id;
                    }, $rows))
                )
            );

            return $rows;
        });

        return $this->success(['count' => count($created)], __('admin.gift_sent'), ResponseCode::CREATED);
    }

    /**
     * 查询后台礼品配置列表。
     *
     * @param Request $request 当前请求对象，承载 name、points_cost、status 和分页参数。
     * @return \Illuminate\Http\JsonResponse
     */
    public function giftItemList(Request $request)
    {
        if ($filterError = $this->validateGiftItemFilters($request)) {
            return $filterError;
        }

        $query = GiftItem::query();

        if ($request->filled('name')) {
            $query->where('name', 'LIKE', '%' . trim((string) $request->input('name')) . '%');
        }
        if ($request->filled('points_cost')) {
            $query->where('points_cost', (int) $request->input('points_cost'));
        }
        if ($request->filled('status')) {
            $query->where('status', (int) $request->input('status'));
        }

        return $this->success(
            $query->orderByDesc('updated_at')->paginate($this->perPage($request)),
            __('admin.gift_items_fetched')
        );
    }

    /**
     * 新增礼品配置。
     *
     * @param Request $request 当前请求对象，承载 gift_items 可写字段。
     * @return \Illuminate\Http\JsonResponse
     */
    public function createGiftItem(Request $request)
    {
        $validator = Validator::make($request->all(), $this->giftItemRules());

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $giftItem = GiftItem::create($validator->validated());

        return $this->success($giftItem, __('admin.gift_item_created'), ResponseCode::CREATED);
    }

    /**
     * 更新礼品配置。
     *
     * @param Request $request 当前请求对象，承载 gift_items 可写字段。
     * @param int $id gift_items.id，表示要更新的礼品配置。
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateGiftItem(Request $request, int $id)
    {
        $giftItem = GiftItem::query()->find($id);

        if (!$giftItem) {
            return $this->error(__('admin.gift_item_not_found'), ResponseCode::DATA_NOT_FOUND);
        }

        $validator = Validator::make($request->all(), $this->giftItemRules());

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $giftItem->update($validator->validated());

        return $this->success($giftItem->fresh(), __('admin.gift_item_updated'), ResponseCode::UPDATED);
    }

    /**
     * 删除礼品配置。
     *
     * @param int $id gift_items.id，表示要软删除的礼品配置。
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteGiftItem(int $id)
    {
        $giftItem = GiftItem::query()->find($id);

        if (!$giftItem) {
            return $this->error(__('admin.gift_item_not_found'), ResponseCode::DATA_NOT_FOUND);
        }

        $giftItem->delete();

        return $this->success([], __('admin.gift_item_deleted'), ResponseCode::DELETED);
    }

    /**
     * 更新礼品发货物流状态。
     *
     * @param Request $request 当前请求对象，承载物流状态、单号和备注。
     * @param int $id gift_shipments.id，表示要更新的发货记录。
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateShipment(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|integer|in:0,1,2,3,4',
            'tracking_number' => 'nullable|string|max:100',
            'remark' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $shipment = GiftShipment::query()->find($id);

        if (!$shipment) {
            return $this->error(__('admin.gift_shipment_not_found'), ResponseCode::DATA_NOT_FOUND);
        }

        $payload = $validator->validated();
        $admin = $request->user('admin');
        if (!$admin || !$this->adminDataScopeService->canAccessRecord(
            $admin,
            (int) $shipment->user_id,
            $shipment->admin_id,
            'user'
        )) {
            return $this->error(__('response.permission_denied'), ResponseCode::PERMISSION_DENIED);
        }
        $beforeStatus = (int) $shipment->status;
        $beforeTrackingNumber = (string) $shipment->tracking_number;
        $beforeRemark = (string) $shipment->remark;

        // 物流更新与审计日志在同一事务内写入，保证状态变更必有操作记录。
        DB::transaction(function () use ($request, $admin, $payload, $shipment, $beforeStatus, $beforeTrackingNumber, $beforeRemark) {
            $shipment->status = (int) $payload['status'];
            $shipment->tracking_number = $payload['tracking_number'] ?? '';
            $shipment->remark = $payload['remark'] ?? '';
            $shipment->admin_id = optional($admin)->id ?: 0;
            $shipment->save();

            $this->writeGiftOperationLog(
                $request,
                $admin,
                'gift_shipment:' . $shipment->id,
                (int) $shipment->user_id,
                sprintf(
                    'Update gift shipment shipment_id:%s; status:%s->%s; tracking_number:%s->%s; remark:%s->%s',
                    $shipment->id,
                    $beforeStatus,
                    (int) $payload['status'],
                    $beforeTrackingNumber,
                    $payload['tracking_number'] ?? '',
                    $beforeRemark,
                    $payload['remark'] ?? ''
                )
            );
        });

        return $this->success($shipment->fresh(), __('admin.gift_shipment_updated'), ResponseCode::UPDATED);
    }

    /**
     * 礼品配置写入校验规则。
     *
     * @return array<string, string>
     */
    private function giftItemRules(): array
    {
        return [
            'name' => 'required|string|max:200',
            'description' => 'nullable|string|max:1000',
            'points_cost' => 'required|integer|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'status' => 'required|integer|in:0,1',
            'image_url' => 'nullable|string|max:500',
        ];
    }

    /**
     * 严格校验发货列表与导出的共用筛选参数。
     *
     * @param Request $request 当前请求对象。
     * @return JsonResponse|null
     */
    private function validateShipmentFilters(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|integer|min:1',
            'gift_name' => 'nullable|string|max:200',
            'recipient_name' => 'nullable|string|max:100',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
            'page' => 'nullable|integer|min:1|max:1000000',
            'per_page' => 'nullable|integer|min:1|max:100',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if (!$validator->fails()
            && $request->filled('start_date')
            && $request->filled('end_date')
            && strcmp((string) $request->input('start_date'), (string) $request->input('end_date')) > 0) {
            $validator->errors()->add('end_date', __('validation.after_or_equal', [
                'attribute' => 'end_date',
                'date' => 'start_date',
            ]));
        }

        if ($validator->errors()->isNotEmpty()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 构建发货列表与导出唯一共用的筛选、范围查询。
     *
     * @param Request $request 当前请求对象。
     * @return Builder
     */
    private function shipmentQuery(Request $request): Builder
    {
        $query = GiftShipment::query()
            ->leftJoin('admins', 'admins.id', '=', 'gift_shipments.admin_id')
            ->select([
                'gift_shipments.id',
                'gift_shipments.user_id',
                'gift_shipments.address_id',
                'gift_shipments.recipient_name',
                'gift_shipments.recipient_phone',
                'gift_shipments.recipient_address',
                'gift_shipments.sender_name',
                'gift_shipments.tracking_number',
                'gift_shipments.gift_name',
                'gift_shipments.gift_quantity',
                'gift_shipments.status',
                'gift_shipments.remark',
                'gift_shipments.admin_id',
                'admins.username as admin_name',
                'gift_shipments.shipped_at',
                'gift_shipments.created_at',
                'gift_shipments.updated_at',
            ]);

        if ($admin = $request->user('admin')) {
            $query = $this->adminDataScopeService->apply(
                $query,
                $admin,
                'user',
                'gift_shipments.user_id',
                null,
                'gift_shipments.admin_id'
            );
        }

        $this->applyShipmentFilters($query, $request);

        return $query;
    }

    /**
     * 返回经严格校验和数据范围过滤的导出记录，供现代和旧协议共用。
     *
     * @param Request $request 当前请求对象。
     * @return \Illuminate\Support\Collection|JsonResponse
     */
    public function shipmentExportRecords(Request $request)
    {
        if ($filterError = $this->validateShipmentFilters($request)) {
            return $filterError;
        }

        return $this->shipmentQuery($request)
            ->orderByDesc('gift_shipments.updated_at')
            ->limit(5000)
            ->get();
    }

    /**
     * 把发货记录转换为旧导出协议的列顺序。
     *
     * @param iterable $records 经 shipmentExportRecords() 过滤的记录。
     * @return array<int, array<int, mixed>>
     */
    public function legacyShipmentCsvRows($records): array
    {
        $rows = [[
            '礼物名称',
            '账户ID',
            '收件人',
            '联系电话',
            '收件地址',
            '寄件人名称',
            '数量',
            '备注',
            '管理员',
            '寄件时间',
        ]];

        foreach ($records as $record) {
            $rows[] = [
                $record->gift_name,
                $record->user_id,
                $record->recipient_name,
                $record->recipient_phone,
                $record->recipient_address,
                $record->sender_name,
                $record->gift_quantity,
                $record->remark,
                $record->admin_name,
                $record->shipped_at,
            ];
        }

        return $rows;
    }

    /**
     * 以独占方式写入 UTF-8 BOM、公式安全的 CSV 文件。
     *
     * @param string $path 由兼容层确定的管理员隔离绝对路径。
     * @param array<int, array<int, mixed>> $rows CSV 行。
     * @return void
     */
    public function writeCsvFile(string $path, array $rows): void
    {
        $handle = @fopen($path, 'xb');
        if ($handle === false) {
            throw new \RuntimeException('Unable to create Gift CSV export.');
        }

        try {
            $this->writeCsvRows($handle, $rows);
        } finally {
            fclose($handle);
        }
    }

    /**
     * 校验列表 user_id 筛选参数必须为整数。
     *
     * @param Request $request 当前请求对象。
     * @return \Illuminate\Http\JsonResponse|null 校验失败返回统一错误响应，未传或通过时返回 null。
     */
    private function validateUserIdFilter(Request $request)
    {
        if (!$request->filled('user_id')) {
            return null;
        }

        $validator = Validator::make(['user_id' => $request->input('user_id')], [
            'user_id' => 'integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 校验礼品配置列表的 points_cost/status 筛选参数必须为整数。
     *
     * @param Request $request 当前请求对象。
     * @return \Illuminate\Http\JsonResponse|null 任一已填筛选参数非法即返回错误响应；未填或通过时返回 null。
     */
    private function validateGiftItemFilters(Request $request)
    {
        $rules = [];

        if ($request->filled('points_cost')) {
            $rules['points_cost'] = 'integer';
        }

        if ($request->filled('status')) {
            $rules['status'] = 'integer';
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
     * 校验地址列表 is_default 筛选参数必须为整数。
     *
     * @param Request $request 当前请求对象。
     * @return \Illuminate\Http\JsonResponse|null 校验失败返回统一错误响应，未传或通过时返回 null。
     */
    private function validateAddressFilters(Request $request)
    {
        if (!$request->filled('is_default')) {
            return null;
        }

        $validator = Validator::make(['is_default' => $request->input('is_default')], [
            'is_default' => 'integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 追加发货列表筛选条件。
     *
     * @param Builder $query 发货列表查询对象。
     * @param Request $request 当前请求对象。
     * @return void
     */
    private function applyShipmentFilters(Builder $query, Request $request): void
    {
        if ($request->filled('user_id')) {
            $query->where('gift_shipments.user_id', (int) $request->input('user_id'));
        }
        if ($request->filled('gift_name')) {
            $query->where('gift_shipments.gift_name', 'LIKE', '%' . trim((string) $request->input('gift_name')) . '%');
        }
        if ($request->filled('recipient_name')) {
            $query->where('gift_shipments.recipient_name', 'LIKE', '%' . trim((string) $request->input('recipient_name')) . '%');
        }
        if ($request->filled('start_date')) {
            $query->where('gift_shipments.shipped_at', '>=', $request->input('start_date') . ' 00:00:00');
        }
        if ($request->filled('end_date')) {
            $query->where('gift_shipments.shipped_at', '<=', $request->input('end_date') . ' 23:59:59');
        }
    }

    /**
     * 追加可发放地址筛选条件。
     *
     * @param Builder $query 地址列表查询对象。
     * @param Request $request 当前请求对象。
     * @return void
     */
    private function applyAddressFilters(Builder $query, Request $request): void
    {
        if ($request->filled('user_id')) {
            $query->where('user_addresses.user_id', (int) $request->input('user_id'));
        }
        if ($request->filled('recipient_name')) {
            $query->where('user_addresses.recipient_name', 'LIKE', '%' . $request->input('recipient_name') . '%');
        }
        if ($request->filled('recipient_phone')) {
            $query->where('user_addresses.recipient_phone', 'LIKE', '%' . $request->input('recipient_phone') . '%');
        }
        if ($request->filled('is_default')) {
            $query->where('user_addresses.is_default', (int) $request->input('is_default'));
        }
    }

    /**
     * 读取分页大小。
     *
     * @param Request $request 当前请求对象。
     * @return int 每页条数，兼容 Layui 的 limit 参数。
     */
    private function perPage(Request $request): int
    {
        return (int) $request->input('per_page', $request->input('limit', 15));
    }

    /**
     * 写入礼品发放/物流更新操作日志。
     *
     * @param Request $request 当前请求对象。
     * @param mixed $admin 当前后台管理员。
     * @param string $orderNo 操作关联单号。
     * @param int|null $targetUserId 被操作的业务用户 ID。
     * @param string $content 操作内容。
     * @return void
     */
    private function writeGiftOperationLog(Request $request, $admin, string $orderNo, int $targetUserId = null, string $content): void
    {
        OperationLog::create([
            'admin_id' => $admin ? (int) $admin->id : 0,
            'admin_name' => $admin ? (string) $admin->username : '',
            'target_user_id' => $targetUserId,
            'order_no' => $orderNo,
            'content' => $content,
            'ip' => $request->ip() ?: '',
            'action_type' => 0,
        ]);
    }

    /**
     * 生成 CSV 下载响应。
     *
     * @param string $fileName 下载文件名。
     * @param array<int, array<int, mixed>> $rows CSV 行数据。
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    private function csvDownload(string $fileName, array $rows)
    {
        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            $this->writeCsvRows($handle, $rows);
            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * @param resource $handle CSV 输出流。
     * @param array<int, array<int, mixed>> $rows CSV 行。
     * @return void
     */
    private function writeCsvRows($handle, array $rows): void
    {
        fwrite($handle, "\xEF\xBB\xBF");

        foreach ($rows as $row) {
            fputcsv($handle, array_map(function ($value) {
                return $this->formulaSafeCsvValue($value);
            }, $row));
        }
    }

    /**
     * @param mixed $value CSV 单元格。
     * @return mixed
     */
    private function formulaSafeCsvValue($value)
    {
        if (is_string($value)
            && $value !== ''
            && preg_match('/^[=+\-@\x09\x0D\x0A]/', $value) === 1) {
            return "'" . $value;
        }

        return $value;
    }
}
