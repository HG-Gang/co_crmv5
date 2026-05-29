<?php

namespace App\Http\Controllers\Admin;

use App\Models\BigAgent;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

/**
 * Big Agent Management Controller
 * 大代理管理控制器
 */
class BigAgentController extends AdminBaseController
{
    /**
     * List all big agents
     * 获取大代理列表
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);

        $agents = BigAgent::query()->paginate($perPage, ['*'], 'page', $page);

        return $this->success($agents, __('admin.big_agents_fetched'));
    }

    /**
     * Create big agent
     * 创建大代理
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'username' => 'required|string|max:50|unique:big_agents',
                'password' => 'required|string|min:6',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $data = $request->all();
            $data['password'] = Hash::make($data['password']);
            $agent = BigAgent::create($data);

            return $this->success($agent, __('admin.big_agent_created'), ResponseCode::CREATED);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Update big agent
     * 更新大代理
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $agent = BigAgent::find($id);
            if (!$agent) {
                return $this->error(__('admin.big_agent_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $validator = Validator::make($request->all(), [
                'username' => 'required|string|max:50|unique:big_agents,username,' . $id,
                'password' => 'sometimes|string|min:6',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $data = $request->all();
            if ($request->filled('password')) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            $agent->update($data);

            return $this->success($agent, __('admin.big_agent_updated'), ResponseCode::UPDATED);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Delete big agent
     * 删除大代理
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $agent = BigAgent::find($id);
            if (!$agent) {
                return $this->error(__('admin.big_agent_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $agent->delete();

            return $this->success([], __('admin.big_agent_deleted'), ResponseCode::DELETED);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }
}
