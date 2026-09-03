<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\Admin;

use App\Constants\ResponseCode;
use App\Models\GroupConfig;
use App\Services\GroupPairingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * 后台用户组兼容控制器。
 *
 * 文件功能：
 * - 提供用户组（代理组/用户组）列表、创建、更新和删除接口。
 * - 旧项目字段与当前 group_configs 表字段之间做双向兼容映射。
 *
 * 功能逻辑说明：
 * - 旧项目的 UserGroupController 使用 user_groups 表；当前项目已统一迁移到 group_configs 表。
 * - 本控制器保留旧字段入参和响应别名，内部只读写 group_configs，避免恢复第二套组别数据源。
 *
 * 业务边界：
 * - 默认组（is_default=1）不允许删除；仍被其他组关联（pair_id 指向）的组不允许删除。
 * - 组下仍有用户（user_infos.group_id 引用）时不允许删除，避免用户悬挂在已删除组上。
 */
class UserGroupController extends AdminBaseController
{
    /**
     * 获取用户组列表。
     *
     * 参数说明：
     * - group_type：旧字段，映射 group_configs.category，1=代理组，2=用户组。
     * - is_enabled：启用状态筛选，0=停用，1=启用。
     * - page/per_page/limit：分页参数，兼容 Layui 默认提交的 page 与 limit。
     *
     * @param Request $request 当前请求对象，承载筛选与分页参数。
     * @return \Illuminate\Http\JsonResponse 旧字段别名格式的用户组分页列表。
     */
    public function index(Request $request)
    {
        if ($filterError = $this->validateListFilters($request)) {
            return $filterError;
        }

        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', $request->input('limit', $request->input('rows', 15)));

        $query = GroupConfig::query();

        if ($request->filled('group_type')) {
            $query->where('category', (int) $request->input('group_type'));
        }

        if ($request->filled('is_enabled')) {
            $query->where('is_enabled', (int) $request->input('is_enabled'));
        }

        $groups = $query->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $rows = collect($groups->items())->map(function (GroupConfig $group) {
            $relationGroupName = '';
            if ($group->pair_id) {
                $relationGroupName = (string) GroupConfig::whereKey($group->pair_id)->value('name');
            }

            return [
                'user_group_id' => $group->id,
                'user_group_name' => $group->name,
                'group_type' => (int) $group->category,
                'group_type_name' => (int) $group->category === 1 ? '代理组' : '用户组',
                'group_id' => (int) $group->has_commission,
                'group_comm_name' => (int) $group->has_commission === 1 ? '有佣金' : '无佣金',
                'group_enable' => (int) $group->is_enabled,
                'is_default' => (int) $group->is_default,
                'relation_grp_id' => (int) ($group->pair_id ?: 0),
                'relation_grp_name' => $relationGroupName,
                'is_enc' => (int) $group->is_ecn,
                'is_enc_group' => (int) $group->is_ecn === 1 ? 'ECN' : 'STP',
                'rec_upd_date' => $this->formatTimestamp($group->updated_at),
            ];
        })->values()->all();

        return $this->success([
            'data' => $rows,
            'count' => $groups->total(),
        ], __('admin.user_group_list_fetched'));
    }

    /**
     * 创建用户组。
     *
     * 参数说明：
     * - group_name：组名称，同表内必须唯一。
     * - group_type：1=代理组，2=用户组。
     * - group_id：0=无佣金，1=有佣金，映射 has_commission。
     * - group_enable：0=停用，1=启用，映射 is_enabled。
     * - is_default：0=非默认，1=默认组；同类别下默认组唯一。
     * - is_enc：0=STP，1=ECN，映射 is_ecn。
     * - relation_group_id：关联组主键，映射 pair_id。
     *
     * 失败语义：
     * - 名称重复返回 DATA_ALREADY_EXISTS；同类别默认组已存在时拒绝创建。
     *
     * @param Request $request 当前请求对象，承载用户组字段。
     * @return \Illuminate\Http\JsonResponse 创建成功返回新组记录。
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'group_name' => 'required|string|max:100',
            'group_type' => 'required|integer|in:1,2',
            'group_id' => 'required|integer|in:0,1',
            'group_enable' => 'required|integer|in:0,1',
            'is_default' => 'required|integer|in:0,1',
            'is_enc' => 'required|integer|in:0,1',
            'relation_group_id' => 'nullable|integer|exists:group_configs,id',
            'add_ecn_grp_id' => 'nullable|integer|min:0',
            'add_stp_grp_id' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $name = trim((string) $request->input('group_name'));
        if ($name === '') {
            return $this->error('用户组名称不能为空', ResponseCode::VALIDATION_FAILED);
        }
        if (GroupConfig::where('name', $name)->exists()) {
            return $this->error('用户组名称已存在', ResponseCode::DATA_ALREADY_EXISTS);
        }

        if ((int) $request->input('is_default') === 1 && $this->defaultGroupExists($request)) {
            return $this->error('默认组已存在', ResponseCode::DATA_ALREADY_EXISTS);
        }

        try {
            $group = DB::transaction(function () use ($request, $name) {
                $data = $this->payload($request, ['name' => $name]);
                $pairId = $this->pairIdFor($request, 'add');
                $group = GroupConfig::create(array_merge($data, ['pair_id' => null]));

                return app(GroupPairingService::class)->rebind(
                    $group,
                    $pairId,
                    (int) $data['is_ecn']
                );
            }, 3);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), ResponseCode::VALIDATION_FAILED);
        }

        return $this->success($group, '用户组创建成功', ResponseCode::CREATED);
    }

    /**
     * 更新用户组。
     *
     * 参数说明：
     * - id：group_configs.id，来自路由参数。
     * - group_name/group_type/group_id/group_enable/is_default/is_enc/relation_group_id：与创建接口含义一致，全部可选。
     *
     * 失败语义：
     * - 组不存在返回 DATA_NOT_FOUND；名称重复、默认组冲突或关联组指向自身时拒绝更新。
     *
     * @param Request $request 当前请求对象，承载待更新字段。
     * @param int|string $id 路由中的 group_configs.id。
     * @return \Illuminate\Http\JsonResponse 更新成功返回最新组记录。
     */
    public function update(Request $request, $id)
    {
        if ($routeIdError = $this->validateGroupRouteId($id)) {
            return $routeIdError;
        }

        $id = (int) $id;
        $validator = Validator::make($request->all(), [
            'group_name' => 'sometimes|string|max:100',
            'group_enable' => 'sometimes|integer|in:0,1',
            'is_default' => 'sometimes|integer|in:0,1',
            'is_enc' => 'sometimes|integer|in:0,1',
            'group_id' => 'sometimes|integer|in:0,1',
            'group_type' => 'sometimes|integer|in:1,2',
            'relation_group_id' => 'nullable|integer|exists:group_configs,id',
            'edit_ecn_grp_id' => 'nullable|integer|min:0',
            'edit_stp_grp_id' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $data = $this->payload($request);
        if ($request->has('group_name')) {
            $name = trim((string) $request->input('group_name'));
            if ($name === '') {
                return $this->error('用户组名称不能为空', ResponseCode::VALIDATION_FAILED);
            }
            $data['name'] = $name;
        }
        if ($request->filled('relation_group_id') && (int) $request->input('relation_group_id') === $id) {
            return $this->error('关联组不能指向自身', ResponseCode::VALIDATION_FAILED);
        }

        $pairChangeRequested = $request->exists('relation_group_id')
            || $request->exists('edit_ecn_grp_id')
            || $request->exists('edit_stp_grp_id');

        try {
            $result = DB::transaction(function () use ($request, $id, $data, $pairChangeRequested) {
                $group = GroupConfig::query()->whereKey($id)->lockForUpdate()->first();
                if (!$group) {
                    return ['code' => ResponseCode::DATA_NOT_FOUND, 'message' => '用户组不存在'];
                }

                if (isset($data['name'])) {
                    $duplicate = GroupConfig::query()
                        ->where('name', $data['name'])
                        ->whereKeyNot($id)
                        ->lockForUpdate()
                        ->first(['id']);
                    if ($duplicate) {
                        return ['code' => ResponseCode::DATA_ALREADY_EXISTS, 'message' => '用户组名称已存在'];
                    }
                    if ((string) $data['name'] !== (string) $group->name && $this->groupHasMembersLocked($group)) {
                        return ['code' => ResponseCode::OPERATION_NOT_ALLOWED, 'message' => '该用户组下还有用户，无法改名'];
                    }
                }

                if (isset($data['is_default'])
                    && (int) $data['is_default'] === 1
                    && $this->defaultGroupExists($request, $id, $group, true)) {
                    return ['code' => ResponseCode::DATA_ALREADY_EXISTS, 'message' => '默认组已存在'];
                }

                $targetIsEcn = (int) ($data['is_ecn'] ?? $group->is_ecn);
                $pairId = $pairChangeRequested
                    ? $this->pairIdFor($request, 'edit')
                    : ($group->pair_id ? (int) $group->pair_id : null);
                $group->update(array_diff_key($data, ['pair_id' => true]));

                return app(GroupPairingService::class)->rebind($group, $pairId, $targetIsEcn);
            }, 3);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), ResponseCode::VALIDATION_FAILED);
        }

        if (is_array($result)) {
            return $this->error($result['message'], $result['code']);
        }

        return $this->success($result, '用户组更新成功', ResponseCode::UPDATED);
    }

    /**
     * 删除用户组。
     *
     * 失败语义：
     * - 组不存在返回 DATA_NOT_FOUND；组下仍有用户、是默认组或被其他组关联时返回 OPERATION_NOT_ALLOWED。
     *
     * @param int|string $id 路由中的 group_configs.id。
     * @return \Illuminate\Http\JsonResponse 删除成功返回空数据。
     */
    public function destroy($id)
    {
        if ($routeIdError = $this->validateGroupRouteId($id)) {
            return $routeIdError;
        }

        $id = (int) $id;
        $result = DB::transaction(function () use ($id) {
            $group = GroupConfig::query()->whereKey($id)->lockForUpdate()->first();
            if (!$group) {
                return ['code' => ResponseCode::DATA_NOT_FOUND, 'message' => '用户组不存在'];
            }
            if ($this->groupHasMembersLocked($group)) {
                return ['code' => ResponseCode::OPERATION_NOT_ALLOWED, 'message' => '该用户组下还有用户，无法删除'];
            }
            if ((int) $group->is_default === 1) {
                return ['code' => ResponseCode::OPERATION_NOT_ALLOWED, 'message' => '默认用户组不能删除'];
            }

            app(GroupPairingService::class)->unbind($group);
            $group->delete();

            return null;
        }, 3);

        if (is_array($result)) {
            return $this->error($result['message'], $result['code']);
        }

        return $this->success([], '用户组删除成功', ResponseCode::DELETED);
    }

    /**
     * 校验列表筛选与分页参数。
     *
     * @param Request $request 当前请求对象。
     * @return \Illuminate\Http\JsonResponse|null 非法时返回参数错误响应，全部合法时返回 null。
     */
    private function validateListFilters(Request $request)
    {
        $rules = [];

        if ($request->filled('group_type')) {
            $rules['group_type'] = 'integer|in:1,2';
        }

        if ($request->filled('is_enabled')) {
            $rules['is_enabled'] = 'integer|in:0,1';
        }
        foreach (['page', 'per_page', 'limit', 'rows'] as $field) {
            if ($request->filled($field)) {
                $rules[$field] = $field === 'page'
                    ? 'integer|min:1'
                    : 'integer|min:1|max:100';
            }
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
     * 严格校验用户组路由主键，拒绝部分数字字符串在查询前被强转。
     *
     * @param mixed $id group_configs.id。
     * @return \Illuminate\Http\JsonResponse|null
     */
    private function validateGroupRouteId($id)
    {
        $validator = Validator::make(['id' => $id], ['id' => 'required|integer|min:1']);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }

    /**
     * 把旧用户组字段映射为 group_configs 真实字段。
     *
     * @param Request $request 当前请求对象。
     * @param array<string, mixed> $overrides 额外覆盖字段（如 name）。
     * @return array<string, mixed> 可写入 group_configs 的字段数组。
     */
    private function payload(Request $request, array $overrides = []): array
    {
        $data = [];

        // 旧字段到新表的字段映射：group_type→category、group_id→has_commission、group_enable→is_enabled、is_enc→is_ecn、relation_group_id→pair_id。
        if ($request->filled('group_type')) {
            $data['category'] = (int) $request->input('group_type');
        }
        if ($request->filled('group_id')) {
            $data['has_commission'] = (int) $request->input('group_id');
        }
        if ($request->filled('group_enable')) {
            $data['is_enabled'] = (int) $request->input('group_enable');
        }
        if ($request->filled('is_default')) {
            $data['is_default'] = (int) $request->input('is_default');
        }
        if ($request->filled('is_enc')) {
            $data['is_ecn'] = (int) $request->input('is_enc');
        }
        if ($request->filled('relation_group_id')) {
            $data['pair_id'] = (int) $request->input('relation_group_id');
        }

        return array_merge($data, $overrides);
    }

    private function pairIdFor(Request $request, string $mode): ?int
    {
        if ($request->exists('relation_group_id')) {
            $value = (int) $request->input('relation_group_id');

            return $value > 0 ? $value : null;
        }

        $isEcn = (int) $request->input('is_enc');
        $field = $mode === 'add'
            ? ($isEcn === 1 ? 'add_stp_grp_id' : 'add_ecn_grp_id')
            : ($isEcn === 1 ? 'edit_stp_grp_id' : 'edit_ecn_grp_id');
        $value = (int) $request->input($field, 0);

        return $value > 0 ? $value : null;
    }

    private function groupHasMembersLocked(GroupConfig $group): bool
    {
        return DB::table('user_infos')
            ->where(function ($query) use ($group) {
                $query->where('group_id', (int) $group->id)
                    ->orWhere('mt4_group', (string) $group->name);
            })
            ->lockForUpdate()
            ->first(['id']) !== null;
    }

    /**
     * 判断同类别（category/has_commission/is_ecn 组合）下是否已存在启用中的默认组。
     *
     * 默认组唯一性按类别、佣金开关和 ECN/STP 组合维度约束，避免前台找不到可用默认组。
     *
     * @param Request $request 当前请求对象，读取类别字段。
     * @param int $ignoreId 更新时排除的当前组 ID。
     * @param GroupConfig|null $current 当前组记录，用于回退读取未提交字段。
     * @return bool true=已存在默认组。
     */
    private function defaultGroupExists(
        Request $request,
        int $ignoreId = 0,
        GroupConfig $current = null,
        bool $lock = false
    ): bool
    {
        $query = GroupConfig::query()
            ->where('is_default', 1)
            ->where('is_enabled', 1)
            ->where('category', (int) $request->input('group_type', $current ? $current->category : 2))
            ->where('has_commission', (int) $request->input('group_id', $current ? $current->has_commission : 0))
            ->where('is_ecn', (int) $request->input('is_enc', $current ? $current->is_ecn : 0));

        if ($ignoreId > 0) {
            $query->where('id', '<>', $ignoreId);
        }

        if ($lock) {
            return $query->lockForUpdate()->first(['id']) !== null;
        }

        return $query->exists();
    }

    /**
     * 统一时间戳展示格式。
     *
     * @param mixed $value DateTimeInterface 或 10 位时间戳。
     * @return string Y-m-d H:i:s；无效值返回空串。
     */
    private function formatTimestamp($value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        $timestamp = (int) $value;
        return $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : '';
    }
}
