<?php

namespace App\Http\Controllers\Admin;

use App\Models\GroupConfig;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Group Configuration Controller
 * 分组配置控制器
 */
class GroupConfigController extends AdminBaseController
{
    /**
     * List group configurations
     * 获取分组配置列表
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);

        $configs = GroupConfig::query()->paginate($perPage, ['*'], 'page', $page);

        return $this->success($configs, __('admin.group_configs_fetched'));
    }

    /**
     * Create group configuration
     * 创建分组配置
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'group_name' => 'required|string|max:100|unique:group_configs',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $data = $request->all();
            $config = GroupConfig::create($data);

            return $this->success($config, __('admin.group_config_created'), ResponseCode::CREATED);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Get group configuration detail
     * 获取分组配置详情
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
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
     * Update group configuration
     * 更新分组配置
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $config = GroupConfig::find($id);
            if (!$config) {
                return $this->error(__('admin.group_config_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $validator = Validator::make($request->all(), [
                'group_name' => 'required|string|max:100|unique:group_configs,group_name,' . $id,
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $config->update($request->all());

            return $this->success($config, __('admin.group_config_updated'), ResponseCode::UPDATED);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Delete group configuration
     * 删除分组配置
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $config = GroupConfig::find($id);
            if (!$config) {
                return $this->error(__('admin.group_config_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $config->delete();

            return $this->success([], __('admin.group_config_deleted'), ResponseCode::DELETED);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }
}
