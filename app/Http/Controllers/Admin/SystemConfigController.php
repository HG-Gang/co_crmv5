<?php

namespace App\Http\Controllers\Admin;

use App\Models\SystemConfig;
use App\Models\OperationLog;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * System Configuration Controller
 * 系统配置控制器
 */
class SystemConfigController extends AdminBaseController
{
    /**
     * Get all system configs grouped
     * 获取所有系统配置（分组）
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $configs = SystemConfig::all()->groupBy('group');
        return $this->success($configs, __('admin.system_configs_fetched'));
    }

    /**
     * Update system configs (batch update key-value pairs)
     * 更新系统配置 (批量更新键值对)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        try {
            $configs = $request->input('configs', []);

            foreach ($configs as $key => $value) {
                SystemConfig::where('key', $key)->update(['value' => $value]);
            }

            return $this->success([], __('admin.system_configs_updated'), ResponseCode::UPDATED);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Get operation logs
     * 获取操作日志
     *
     * @param Request $request
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
}
