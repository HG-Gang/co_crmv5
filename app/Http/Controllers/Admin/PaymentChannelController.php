<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\Admin;

use App\Models\PaymentChannel;
use App\Constants\ResponseCode;
use App\Support\SecretReference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 后台支付通道管理控制器。
 *
 * 文件功能：
 * - 提供支付通道列表、创建、更新、删除和启停切换接口。
 *
 * 功能逻辑说明：
 * - 本控制器承载 admin_api_channelList、admin_api_createChannel、admin_api_updateChannel、admin_api_deleteChannel、admin_api_toggleChannel。
 * - 数据来源为 payment_channels 表，会影响前台入金通道展示和后台资金配置维护。
 * - 页面按钮显隐来自 permissions.slug，接口最终仍由 check.permission:admin 按 permissions.api_route 做鉴权。
 *
 * 安全边界：
 * - 通道 config 中的密钥类字段只允许写入 SecretReference 引用（env:/vault: 形式），containsPlainSecret 会拒绝任何明文字符串。
 * - 接口返回的 config 只包含引用字符串本身（非密钥明文），真实密钥取值发生在支付网关调用侧按引用解析。
 * - 密钥值只以引用形式存在配置中，禁止在注释、日志或响应里出现真实密钥内容。
 */
class PaymentChannelController extends AdminBaseController
{
    /**
     * 获取支付通道列表。
     *
     * 参数逻辑说明：
     * - page 表示当前页码，默认第 1 页。
     * - per_page 表示每页数量，兼容标准分页参数。
     * - limit 表示 Layui 表格每页数量，当前端未提交 per_page 时使用。
     * - sort 表示后台排序值，列表按 payment_channels.sort 升序展示。
     * - status 表示启用状态筛选，对应 payment_channels.is_enabled；1=已启用，0=已停用，留空表示全部。
     *   后台页面把通道按启用状态分成 layui-tab 页签，页签切换即提交该参数收窄结果集。
     * - name 表示通道名称模糊筛选，channel_code 表示通道编码模糊筛选。
     *
     * @param Request $request HTTP 请求对象，承载 page、per_page、limit、status、name、channel_code。
     * @return \Illuminate\Http\JsonResponse 支付通道分页列表响应。
     */
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', $request->input('limit', 15));

        $query = PaymentChannel::query();
        $this->applyChannelFilters($query, $request);

        $channels = $query->orderBy('sort')->paginate($perPage, ['*'], 'page', $page);

        return $this->success($channels, __('admin.payment_channels_fetched'));
    }

    /**
     * 追加支付通道列表筛选条件。
     *
     * 参数逻辑说明：
     * - status 只接受 '0' 与 '1' 两个值；其他取值（包括空串）视为不筛选，避免页签传入脏值后返回空列表。
     * - is_enabled 兼容旧字段名，与 status 语义相同。
     * - name / channel_code 按 LIKE 模糊匹配，供页签内的关键字搜索使用。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 支付通道查询对象。
     * @param Request $request 当前 HTTP 请求对象。
     * @return void
     */
    private function applyChannelFilters($query, Request $request): void
    {
        $status = $request->input('status', $request->input('is_enabled'));
        if ($status !== null && in_array((string) $status, ['0', '1'], true)) {
            $query->where('is_enabled', (int) $status);
        }

        if ($request->filled('name')) {
            $query->where('name', 'LIKE', '%' . $request->input('name') . '%');
        }

        if ($request->filled('channel_code')) {
            $query->where('channel_code', 'LIKE', '%' . $request->input('channel_code') . '%');
        }
    }

    /**
     * 创建支付通道。
     *
     * 参数逻辑说明：
     * - name：支付通道名称，对应 payment_channels.name，新增时必填且最长 100 个字符。
     * - channel_name：旧页面提交的通道名称字段，后端在 name 为空时映射到 name，兼容旧 JS 调用。
     * - channel_code：支付通道编码，对应 payment_channels.channel_code，新增时必须唯一。
     * - exchange_rate：支付通道汇率，必须是大于等于 0 的数字，用于入金金额换算。
     * - is_enabled：通道是否启用，控制该通道是否在前台入金页展示和接受入金请求。
     * - sort：后台排序值，数值越小越靠前，控制通道在列表和前台的展示顺序。
     * - config：支付通道扩展配置 JSON，通常保存第三方参数、限额或展示配置；其中密钥类字段只允许 SecretReference 引用。
     *
     * 安全边界：
     * - config 中的密钥类字段必须以 SecretReference 形式（env:/vault: 前缀）存储，containsPlainSecret 会拒绝任何明文字符串。
     *
     * @param Request $request HTTP 请求对象，承载支付通道新增字段。
     * @return \Illuminate\Http\JsonResponse 创建成功返回新支付通道记录；校验失败返回错误响应。
     */
    public function store(Request $request)
    {
        try {
            // channel_name 是旧页面字段名，映射到真实入库字段 payment_channels.name，保证新旧调用共用同一写入逻辑。
            if ($request->filled('channel_name') && !$request->filled('name')) {
                $request->merge(['name' => $request->input('channel_name')]);
            }

            if ($configError = $this->normalizePaymentConfig($request)) {
                return $configError;
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:100',
                'channel_code' => 'required|string|max:50|unique:payment_channels',
                'exchange_rate' => 'sometimes|numeric|min:0',
                'is_enabled' => 'sometimes|boolean',
                'sort' => 'sometimes|integer',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $channel = PaymentChannel::create($request->only([
                'name', 'channel_code', 'exchange_rate', 'is_enabled', 'sort', 'config'
            ]));
            return $this->success($channel, __('admin.payment_channel_created'), ResponseCode::CREATED);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 更新支付通道。
     *
     * 参数逻辑说明：
     * - id 表示 payment_channels.id。
     * - id 表示支付通道主键，优先读取路由参数，兼容从 POST body 传入的 id。
     * - name/channel_name、channel_code、exchange_rate、is_enabled、sort、config 含义与新增接口一致。
     * - channel_name 映射到 payment_channels.name，避免旧页面字段名和真实更新字段不一致。
     * - channel_code 编辑时仍需唯一，但会排除当前 id 对应的记录。
     *
     * @param Request $request HTTP 请求对象，承载支付通道更新字段。
     * @param int|null $id 路由中的 payment_channels.id；为空时从请求体 id 兼容读取。
     * @return \Illuminate\Http\JsonResponse 更新后的支付通道记录。
     */
    public function update(Request $request, $id = null)
    {
        try {
            $id = $id ?: $request->input('id');
            if ($routeIdError = $this->validatePaymentChannelRouteId($id)) {
                return $routeIdError;
            }

            $id = (int) $id;
            $channel = PaymentChannel::find($id);
            if (!$channel) {
                return $this->error(__('admin.payment_channel_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            // channel_name 是旧页面字段名，映射到真实更新字段 payment_channels.name，保证新旧调用共用同一更新逻辑。
            if ($request->filled('channel_name') && !$request->filled('name')) {
                $request->merge(['name' => $request->input('channel_name')]);
            }

            if ($configError = $this->normalizePaymentConfig($request)) {
                return $configError;
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:100',
                'channel_code' => 'required|string|max:50|unique:payment_channels,channel_code,' . $id,
                'exchange_rate' => 'sometimes|numeric|min:0',
                'is_enabled' => 'sometimes|boolean',
                'sort' => 'sometimes|integer',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $channel->update($request->only([
                'name', 'channel_code', 'exchange_rate', 'is_enabled', 'sort', 'config'
            ]));
            return $this->success($channel, __('admin.payment_channel_updated'), ResponseCode::UPDATED);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 删除支付通道。
     *
     * 参数逻辑说明：
     * - id 表示 payment_channels.id。
     * - id 表示支付通道主键，对应 payment_channels.id。
     * - 删除入口必须具备 admin_api_deleteChannel 对应权限配置，避免无权限管理员直接调用接口。
     *
     * @param int $id 路由中的 payment_channels.id。
     * @return \Illuminate\Http\JsonResponse 删除结果响应。
     */
    public function destroy($id)
    {
        try {
            if ($routeIdError = $this->validatePaymentChannelRouteId($id)) {
                return $routeIdError;
            }

            $id = (int) $id;
            $channel = PaymentChannel::find($id);
            if (!$channel) {
                return $this->error(__('admin.payment_channel_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $channel->delete();
            return $this->success([], __('admin.payment_channel_deleted'), ResponseCode::DELETED);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 切换支付通道启用/禁用状态。
     *
     * 参数逻辑说明：
     * - id 表示 payment_channels.id。
     * - id 表示支付通道主键，对应 payment_channels.id。
     * - is_enabled 表示通道是否启用，本方法会将当前值取反。
     * - toggleEnable 用于切换支付通道启用状态，必须通过 admin_api_toggleChannel 命名路由和 admin_channel_toggle 权限访问。
     * - 启停动作只修改 is_enabled，不改动通道编码、汇率、排序和扩展配置，避免状态切换误伤通道配置。
     *
     * @param int $id 路由中的 payment_channels.id。
     * @return \Illuminate\Http\JsonResponse 状态切换结果响应。
     */
    public function toggleEnable($id)
    {
        try {
            if ($routeIdError = $this->validatePaymentChannelRouteId($id)) {
                return $routeIdError;
            }

            $id = (int) $id;
            $channel = PaymentChannel::find($id);
            if (!$channel) {
                return $this->error(__('admin.payment_channel_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $channel->update(['is_enabled' => !$channel->is_enabled]);
            return $this->success([], __('admin.status_toggled'));
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 校验支付通道主键，必须为整数。
     *
     * @param mixed $id payment_channels.id。
     * @return \Illuminate\Http\JsonResponse|null 非法时返回参数错误响应，合法时返回 null。
     */
    private function validatePaymentChannelRouteId($id)
    {
        $validator = Validator::make(['id' => $id], [
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 归一化通道扩展配置，并拒绝明文密钥。
     *
     * @param Request $request 当前请求对象，读取 config 字段。
     * @return \Illuminate\Http\JsonResponse|null 配置非法时返回错误响应，正常时把解析后的数组合并回请求。
     */
    private function normalizePaymentConfig(Request $request)
    {
        if (!$request->exists('config')) {
            return null;
        }

        $config = $request->input('config');
        if (is_string($config)) {
            $config = json_decode($config, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($config)) {
                return $this->error('config must be a valid JSON object.', ResponseCode::VALIDATION_FAILED);
            }
        }

        if (!is_array($config)) {
            return $this->error('config must be an object.', ResponseCode::VALIDATION_FAILED);
        }
        // 密钥类字段只允许 SecretReference 引用，出现明文立即拒绝写入，避免第三方密钥落库。
        if ($this->containsPlainSecret($config)) {
            return $this->error('Payment secrets must be stored by reference.', ResponseCode::VALIDATION_FAILED);
        }

        $request->merge(['config' => $config]);

        return null;
    }

    /**
     * 递归检查配置中是否包含明文密钥。
     *
     * 语义说明：
     * - 字段名归一化后以 _reference/_ref 结尾的，值必须是 SecretReference（env: 或 vault: 开头）；
     * - 字段名含 secret/password/token，或以 _key 结尾（notify/request/hmac/encryption/api/access/signing/merchant/private 等前缀）的，直接判定为明文密钥。
     *
     * @param array<string|int, mixed> $config 通道扩展配置。
     * @return bool true=存在明文密钥，必须拒绝保存。
     */
    private function containsPlainSecret(array $config): bool
    {
        foreach ($config as $key => $value) {
            if (is_string($key)) {
                // 驼峰/大小写混写先归一化为小写下划线形式，保证 _key 与 *_reference 识别不受命名风格影响。
                $normalized = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $key);
                $normalized = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', (string) $normalized), '_'));
                if (preg_match('/_(?:reference|ref)$/', $normalized)) {
                    // 引用类字段必须携带合法 SecretReference（env:/vault: 前缀），否则按明文密钥拒绝。
                    if (!is_string($value) || !SecretReference::isValid($value)) {
                        return true;
                    }
                } elseif (preg_match('/(^|_)(secret|password|token)($|_)/', $normalized)
                    || preg_match('/(^|_)(notify|request|hmac|encryption|api|access|signing|merchant|private)_key($|_)/', $normalized)) {
                    // 命中密钥特征字段名的任何值都视为明文，必须由维护者改用 SecretReference 引用。
                    return true;
                }
            }
            if (is_array($value) && $this->containsPlainSecret($value)) {
                return true;
            }
        }

        return false;
    }
}
