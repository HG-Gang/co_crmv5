<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:07
 */

namespace App\Http\Controllers\Admin;

use App\Constants\ResponseCode;
use App\Models\OperationLog;
use App\Models\SystemConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 后台系统配置控制器。
 *
 * 文件功能：
 * - 提供系统配置列表查询、单行/批量更新和操作日志查询接口。
 * - 数据来源为 system_configs 表，配置内容会影响前台展示与后台行为。
 *
 * 业务边界：
 * - 系统配置是后台高敏配置资源，接口访问必须经过 routes/admin.php 中的 check.permission:admin。
 * - 列表接口既兼容旧版按 group 分组读取，也支持 Layui 表格按分页平铺读取。
 * - 更新接口既兼容旧版 configs[key]=value 批量写入，也支持当前 Blade 页面按单行编辑写入。
 */
class SystemConfigController extends AdminBaseController
{
    /**
     * 获取系统配置列表。
     *
     * 参数说明：
     * - page：Layui 表格分页页码；存在时返回平铺分页数据，便于后台 Blade 表格直接渲染。
     * - per_page/limit：每页条数；兼容 Layui 的 limit 参数。
     * - 不传分页参数：保留旧调用方式，仍按 group 字段分组返回配置集合。
     *
     * @param Request $request 请求对象，承载分页参数和后续筛选参数。
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        if ($request->has('page') || $request->has('per_page') || $request->has('limit')) {
            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', $request->input('limit', 15));

            $configs = SystemConfig::query()
                ->orderBy('group')
                ->orderBy('key')
                ->paginate($perPage, ['*'], 'page', $page);

            return $this->success($configs, __('admin.system_configs_fetched'));
        }

        $configs = SystemConfig::all()->groupBy('group');

        return $this->success($configs, __('admin.system_configs_fetched'));
    }

    /**
     * 更新系统配置。
     *
     * 参数说明：
     * - id：单行编辑时的 system_configs.id；存在时优先按主键更新，避免 key 被改名后误更新其他配置。
     * - key：配置键；旧调用或无 id 调用时按 key 定位配置。
     * - value：配置值；允许为空字符串，用于关闭某些文本类配置。
     * - group：配置分组；用于后台页面归类展示。
     * - description：配置说明；用于解释参数业务含义。
     * - configs：旧版批量格式，键为 system_configs.key，值为需要写入的 value。
     *
     * @param Request $request 请求对象，承载单行或批量更新参数。
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        try {
            // 优先处理单行更新（id 或 key 定位），无定位字段时按旧版 configs[key]=value 批量写回。
            if ($request->filled('id') || $request->filled('key')) {
                return $this->updateSingleConfig($request);
            }

            $configs = $request->input('configs', []);

            foreach ($configs as $key => $value) {
                SystemConfig::where('key', $key)->update(['value' => $value]);
            }

            return $this->success([], __('admin.system_configs_updated'), ResponseCode::UPDATED);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 获取操作日志列表。
     *
     * 参数说明：
     * - page：分页页码。
     * - per_page：每页条数。
     * - admin_name：管理员账号关键字，存在时按名称模糊筛选日志。
     *
     * @param Request $request 请求对象，承载分页和筛选参数。
     * @return \Illuminate\Http\JsonResponse
     */
    public function logs(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);

        $query = OperationLog::query()->with('admin');

        if ($request->filled('admin_name')) {
            $query->where('admin_name', 'LIKE', "%{$request->admin_name}%");
        }

        $logs = $query->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);

        return $this->success($logs, __('admin.operation_logs_fetched'));
    }

    /**
     * 更新单条系统配置。
     *
     * 参数说明：
     * - request.id：配置主键，页面行内编辑时必传。
     * - request.key：配置键，作为无 id 兼容路径的定位字段。
     * - request.value/group/description：允许更新的业务字段，避免前端提交其他字段时误写入。
     *
     * @param Request $request 请求对象，承载单行配置字段。
     * @return \Illuminate\Http\JsonResponse
     */
    private function updateSingleConfig(Request $request)
    {
        $query = SystemConfig::query();

        // 有 id 时按主键定位，避免 key 改名后误更新其他配置；无 id 时回退到按 key 定位。
        if ($request->filled('id')) {
            $validator = Validator::make(['id' => $request->input('id')], [
                'id' => 'integer',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $query->where('id', (int) $request->input('id'));
        } else {
            $query->where('key', $request->input('key'));
        }

        $config = $query->first();
        if (!$config) {
            return $this->error(__('admin.system_config_not_found'), ResponseCode::DATA_NOT_FOUND);
        }

        $validator = Validator::make($request->all(), [
            'value' => 'nullable|string',
            'group' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $data = $request->only(['value', 'group', 'description']);
        $config->update($data);

        return $this->success($config, __('admin.system_configs_updated'), ResponseCode::UPDATED);
    }
}
