<?php

namespace App\Http\Controllers\Admin;

use App\Models\AgentLevel;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Agent Level Management Controller
 * 代理等级管理控制器
 */
class AgentLevelController extends AdminBaseController
{
    /**
     * List all agent levels
     * 获取所有代理等级列表
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $levels = AgentLevel::orderBy('level')->get();
        return $this->success($levels, __('admin.agent_levels_fetched'));
    }

    /**
     * Create agent level
     * 创建代理等级
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'level' => 'required|integer|unique:agent_levels',
                'name'  => 'required|string|max:50',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $level = AgentLevel::create($request->all());
            return $this->success($level, __('admin.agent_level_created'), ResponseCode::CREATED);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Update agent level
     * 更新代理等级
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $level = AgentLevel::find($id);
            if (!$level) {
                return $this->error(__('admin.agent_level_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $validator = Validator::make($request->all(), [
                'level' => 'required|integer|unique:agent_levels,level,' . $id,
                'name'  => 'required|string|max:50',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $level->update($request->all());
            return $this->success($level, __('admin.agent_level_updated'), ResponseCode::UPDATED);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Delete agent level
     * 删除代理等级
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $level = AgentLevel::find($id);
            if (!$level) {
                return $this->error(__('admin.agent_level_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $level->delete();
            return $this->success([], __('admin.agent_level_deleted'), ResponseCode::DELETED);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }
}
