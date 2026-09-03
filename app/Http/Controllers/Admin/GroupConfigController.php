<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\Admin;

use App\Models\GroupConfig;
use App\Constants\ResponseCode;
use App\Services\GroupPairingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * 后台组别配置控制器。
 *
 * 文件功能：
 * - 负责后台组别配置的列表、新增、详情、更新和删除。
 * - 数据来源为 group_configs 表，前台客户注册、代理客户组别变更和后台组别维护都会依赖该表。
 * - 响应文案统一使用 admin 语言包，保证后端接口支持多语言提示。
 *
 * 适用场景：
 * - 后台组别管理页面（列表/新增/详情/更新/删除）。
 * - 本控制器只维护 group_configs 表，不参与返佣或注册链路计算。
 */
class GroupConfigController extends AdminBaseController
{
    /**
     * 获取组别配置分页列表。
     *
     * 参数逻辑说明：
     * - page 表示当前页码，默认第 1 页。
     * - per_page 表示每页数量，默认每页 15 条。
     *
     * @param Request $request HTTP 请求对象，承载 page、per_page 等分页参数。
     * @return \Illuminate\Http\JsonResponse 组别配置分页列表响应。
     */
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);

        // 组别为全局配置，不做管理员数据范围过滤。
        $configs = GroupConfig::query()->paginate($perPage, ['*'], 'page', $page);

        return $this->success($configs, __('admin.group_configs_fetched'));
    }

    /**
     * 创建组别配置。
     *
     * 参数逻辑说明：
     * - name 表示真实入库组别名称，对应 group_configs.name。
     * - group_name 表示页面表单提交的组别名称，normalizePayload 会将 group_name 映射到 group_configs.name。
     * - radix 表示组别基数，对应 group_configs.radix，必须大于等于 0。
     * - category 取值 1=代理组、2=用户组，对应 group_configs.category。
     * - has_commission 表示是否参与返佣，is_enabled 表示是否启用，is_ecn 表示是否 ECN 组，is_default 表示是否默认组。
     *
     * @param Request $request HTTP 请求对象，承载组别配置新增字段。
     * @return \Illuminate\Http\JsonResponse 创建成功后返回新组别配置记录。
     */
    public function store(Request $request)
    {
        try {
            $data = $this->normalizePayload($request);

            $validator = $this->payloadValidator($data);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $data = $this->castGroupConfigPayload($data);
            $config = DB::transaction(function () use ($request, $data) {
                $this->assertDefaultGroupIsUnique($data);
                $admin = $request->user('admin') ?: Auth::guard('admin')->user();

                $config = GroupConfig::create(array_merge(
                    array_diff_key($data, ['pair_id' => true]),
                    [
                        'pair_id' => null,
                        'created_by' => $admin ? (int) $admin->id : 0,
                        'updated_by' => $admin ? (int) $admin->id : 0,
                    ]
                ));

                return app(GroupPairingService::class)->rebind(
                    $config,
                    $data['pair_id'],
                    (int) $data['is_ecn']
                );
            }, 3);

            return $this->success($config, __('admin.group_config_created'), ResponseCode::CREATED);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), ResponseCode::VALIDATION_FAILED);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 获取组别配置详情。
     *
     * 参数逻辑说明：
     * - id 表示 group_configs.id，用于定位单条组别配置。
     *
     * @param int $id 路由中的 group_configs.id。
     * @return \Illuminate\Http\JsonResponse 组别配置详情响应。
     */
    public function show($id)
    {
        $config = GroupConfig::find($id);
        if (!$config) {
            return $this->error(__('admin.group_config_not_found'), ResponseCode::DATA_NOT_FOUND);
        }

        return $this->success($config, __('admin.group_config_detail_fetched'));
    }

    /**
     * 更新组别配置。
     *
     * 参数逻辑说明：
     * - id 表示 group_configs.id，用于定位需要更新的组别配置。
     * - name 表示真实入库组别名称，对应 group_configs.name。
     * - group_name 表示页面表单提交的组别名称，normalizePayload 会将 group_name 映射到 group_configs.name。
     * - radix 表示组别基数，对应 group_configs.radix，必须大于等于 0。
     * - category 取值 1=代理组、2=用户组，对应 group_configs.category。
     * - has_commission 表示是否参与返佣，is_enabled 表示是否启用，is_ecn 表示是否 ECN 组，is_default 表示是否默认组。
     *
     * @param Request $request HTTP 请求对象，承载组别配置更新字段。
     * @param int $id 路由中的 group_configs.id。
     * @return \Illuminate\Http\JsonResponse 更新后的组别配置记录。
     */
    public function update(Request $request, $id)
    {
        try {
            if ($routeIdError = $this->validateGroupConfigRouteId($id)) {
                return $routeIdError;
            }

            $id = (int) $id;
            $pairChangeRequested = $request->exists('pair_id')
                || $request->exists('bind_id')
                || $request->headers->has('X-Legacy-Admin-Route');
            $result = DB::transaction(function () use ($request, $id, $pairChangeRequested) {
                $config = GroupConfig::query()->whereKey($id)->lockForUpdate()->first();
                if (!$config) {
                    return ['code' => ResponseCode::DATA_NOT_FOUND, 'message' => __('admin.group_config_not_found')];
                }

                $data = $this->normalizePayload($request);
                $validator = $this->payloadValidator($data, $id);
                if ($validator->fails()) {
                    return ['code' => ResponseCode::VALIDATION_FAILED, 'message' => $validator->errors()->first()];
                }
                $data = $this->castGroupConfigPayload($data);

                if ((string) $data['name'] !== (string) $config->name && $this->groupHasMembersLocked($config)) {
                    return ['code' => ResponseCode::OPERATION_NOT_ALLOWED, 'message' => __('response.operation_not_allowed')];
                }

                $this->assertDefaultGroupIsUnique($data, $id);

                $admin = $request->user('admin') ?: Auth::guard('admin')->user();
                $peerId = $pairChangeRequested ? $data['pair_id'] : ($config->pair_id ?: null);
                $config->update(array_merge(
                    array_diff_key($data, ['pair_id' => true]),
                    [
                        'updated_by' => $admin ? (int) $admin->id : 0,
                    ]
                ));

                return app(GroupPairingService::class)->rebind(
                    $config,
                    $peerId ? (int) $peerId : null,
                    (int) $data['is_ecn']
                );
            }, 3);

            if (is_array($result)) {
                return $this->error($result['message'], $result['code']);
            }

            return $this->success($result, __('admin.group_config_updated'), ResponseCode::UPDATED);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), ResponseCode::VALIDATION_FAILED);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 返回旧组配置表单使用的可配对组选项。
     */
    public function pairSelect(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'is_ecn' => 'required|integer|in:0,1',
            'self_id' => 'nullable|integer|min:1',
        ]);
        if ($validator->fails()) {
            return response('', 422);
        }

        $selfId = $request->filled('self_id') ? (int) $request->input('self_id') : 0;
        $peerType = (int) $request->input('is_ecn') === 1 ? 0 : 1;
        $groups = GroupConfig::query()
            ->where('is_ecn', $peerType)
            ->when($selfId > 0, function ($query) use ($selfId) {
                $query->where('id', '<>', $selfId);
            })
            ->where(function ($query) use ($selfId) {
                $query->whereNull('pair_id');
                if ($selfId > 0) {
                    $query->orWhere('pair_id', $selfId);
                }
            })
            ->orderBy('id')
            ->get(['id', 'name']);

        $options = '<option value="">不绑定</option>';
        foreach ($groups as $group) {
            $options .= '<option value="' . (int) $group->id . '">' . e((string) $group->name) . '</option>';
        }

        return response($options, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * 删除组别配置。
     *
     * 参数逻辑说明：
     * - id 表示 group_configs.id，用于定位需要删除的组别配置。
     * - delete() 按 GroupConfig 模型当前删除策略执行；若模型启用软删除则保留删除标记，否则执行物理删除。
     *
     * @param int $id 路由中的 group_configs.id。
     * @return \Illuminate\Http\JsonResponse 删除结果响应。
     */
    public function destroy($id)
    {
        try {
            if ($routeIdError = $this->validateGroupConfigRouteId($id)) {
                return $routeIdError;
            }

            $id = (int) $id;
            $errorCode = DB::transaction(function () use ($id) {
                $config = GroupConfig::query()->whereKey($id)->lockForUpdate()->first();
                if (!$config) {
                    return ResponseCode::DATA_NOT_FOUND;
                }
                if ($this->groupHasMembersLocked($config) || (int) $config->is_default === 1) {
                    return ResponseCode::OPERATION_NOT_ALLOWED;
                }

                app(GroupPairingService::class)->unbind($config);
                $config->delete();

                return null;
            }, 3);

            if ($errorCode === ResponseCode::DATA_NOT_FOUND) {
                return $this->error(__('admin.group_config_not_found'), ResponseCode::DATA_NOT_FOUND);
            }
            if ($errorCode === ResponseCode::OPERATION_NOT_ALLOWED) {
                return $this->error(__('response.operation_not_allowed'), ResponseCode::OPERATION_NOT_ALLOWED);
            }

            return $this->success([], __('admin.group_config_deleted'), ResponseCode::DELETED);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 校验组别配置路由 ID，避免非严格数字字符串命中 group_configs.id。
     *
     * @param mixed $id 路由参数中的 group_configs.id。
     * @return \Illuminate\Http\JsonResponse|null ID 非法时返回统一错误响应，否则返回 null。
     */
    private function validateGroupConfigRouteId($id)
    {
        $validator = Validator::make(['id' => $id], [
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    private function groupHasMembersLocked(GroupConfig $config): bool
    {
        return DB::table('user_infos')
            ->where(function ($query) use ($config) {
                $query->where('group_id', (int) $config->id)
                    ->orWhere('mt4_group', (string) $config->name);
            })
            ->lockForUpdate()
            ->first(['id']) !== null;
    }

    /**
     * 规范化组别配置保存参数。
     *
     * 参数逻辑：
     * - name 表示真实入库组别名称，对应 group_configs.name。
     * - group_name 表示页面表单提交的组别名称，进入模型前映射为 group_configs.name。
     * - group_name 映射到 group_configs.name，兼容后台 Layui 表单字段命名。
     * - radix 表示组别基数，直接写入 group_configs.radix。
     * - category 取值 1=代理组、2=用户组。
     * - has_commission 表示是否参与返佣。
     * - is_enabled 表示是否启用。
     * - is_ecn 表示是否 ECN 组。
     * - is_default 表示是否默认组。
     * - 开关字段未勾选时按 0 写入，避免复选框不提交造成旧值残留。
     *
     * @param Request $request 当前 HTTP 请求对象，承载页面表单提交参数。
     * @return array<string, mixed> 可安全写入 group_configs 表的字段集合。
     */
    private function normalizePayload(Request $request)
    {
        return [
            'name' => $request->input('name', $request->input('group_name')),
            'radix' => $request->input('radix', 50),
            'category' => $request->input('category', $request->input('type', 2)),
            'has_commission' => $request->input('has_commission', $request->input('comm_mode', 0)),
            'is_enabled' => $request->input('is_enabled', 1),
            'is_ecn' => $request->input('is_ecn', 0),
            'is_default' => $request->input('is_default', 0),
            'pair_id' => $request->input('pair_id', $request->input('bind_id')),
        ];
    }

    /**
     * 将入库字段转换为模型期望的标量类型。
     *
     * radix 转 float、category 转 int，避免字符串类型写入后与 numeric 校验或前端展示口径不一致。
     *
     * @param array<string, mixed> $data 规范化后的配置字段。
     * @return array<string, mixed> 类型转换后的字段集合。
     */
    private function castGroupConfigPayload(array $data): array
    {
        if (array_key_exists('radix', $data) && $data['radix'] !== null && $data['radix'] !== '') {
            $data['radix'] = (float) $data['radix'];
        }

        if (array_key_exists('category', $data) && $data['category'] !== null && $data['category'] !== '') {
            $data['category'] = (int) $data['category'];
        }

        foreach (['has_commission', 'is_enabled', 'is_ecn', 'is_default'] as $field) {
            $data[$field] = (int) $data[$field];
        }
        $data['pair_id'] = $data['pair_id'] === null || $data['pair_id'] === ''
            ? null
            : (int) $data['pair_id'];

        return $data;
    }

    private function payloadValidator(array $data, int $ignoreId = 0)
    {
        return Validator::make($data, [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('group_configs', 'name')->ignore($ignoreId)->whereNull('deleted_at'),
            ],
            'radix' => 'required|numeric|min:0',
            'category' => 'required|integer|in:1,2',
            'has_commission' => 'required|integer|in:0,1',
            'is_enabled' => 'required|integer|in:0,1',
            'is_ecn' => 'required|integer|in:0,1',
            'is_default' => 'required|integer|in:0,1',
            'pair_id' => 'nullable|integer|min:1' . ($ignoreId > 0 ? '|not_in:' . $ignoreId : ''),
        ]);
    }

    private function assertDefaultGroupIsUnique(array $data, int $ignoreId = 0): void
    {
        if ((int) $data['is_default'] !== 1) {
            return;
        }

        $query = GroupConfig::query()
            ->where('is_default', 1)
            ->where('is_ecn', (int) $data['is_ecn'])
            ->where('has_commission', (int) $data['has_commission']);
        if ($ignoreId > 0) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->lockForUpdate()->exists()) {
            throw new \DomainException('同类型默认组已存在。');
        }
    }

}
