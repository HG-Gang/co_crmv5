<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:12
 */

namespace App\Http\Controllers\Admin;

use App\Models\AgentLevel;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 后台代理等级管理控制器。
 *
 * 功能逻辑说明：
 * - 负责后台代理等级列表、新增、更新和删除。
 * - 代理等级数据写入 agent_levels 表，前后台代理资料和返佣配置会读取这些等级字段。
 * - 保存前统一调用 normalizePayload() 做字段兼容映射，避免旧页面字段名和真实表字段名不一致。
 *
 * 文件功能：
 * - 代理等级（agent_levels）的增删改查；输入 level_code/name/max_commission/min_commission/user_commission。
 * - normalizePayload() 把旧字段 level/commission_rate 映射到真实表字段后再校验入库。
 *
 * 适用场景：
 * - 后台"代理等级"维护页面；列表按 level_code 升序展示，便于从低到高维护。
 *
 * 失败语义：
 * - 校验失败返回 VALIDATION_FAILED；记录不存在返回 DATA_NOT_FOUND；未预期异常统一 serverErrorResponse()。
 */
class AgentLevelController extends AdminBaseController
{
    /**
     * 获取所有代理等级列表。
     *
     * index() 逻辑说明：
     * - level_code 表示代理等级编码，列表按该字段升序展示，便于后台从低到高维护等级。
     *
     * @return \Illuminate\Http\JsonResponse 返回代理等级列表。
     */
    public function index()
    {
        $levels = AgentLevel::orderBy('level_code')->get();
        return $this->success($levels, __('admin.agent_levels_fetched'));
    }

    /**
     * 创建代理等级。
     *
     * store() 参数说明：
     * - level_code 表示代理等级编码，必须唯一，对应 agent_levels.level_code。
     * - name 表示代理等级名称，用于后台页面和前台代理资料展示。
     * - max_commission 表示最大返佣值。
     * - min_commission 表示最小返佣值。
     * - user_commission 表示用户返佣值。
     *
     * @param Request $request 当前 HTTP 请求对象，承载新增代理等级表单参数。
     * @return \Illuminate\Http\JsonResponse 创建成功后的代理等级记录。
     *
     * 失败语义：
     * - level_code 重复或字段校验失败返回 VALIDATION_FAILED；未预期异常统一返回 serverErrorResponse()。
     */
    public function store(Request $request)
    {
        try {
            $data = $this->normalizePayload($request);

            $validator = Validator::make($data, [
                'level_code' => 'required|integer|unique:agent_levels,level_code',
                'name'  => 'required|string|max:50',
                'max_commission' => 'nullable|integer|min:0',
                'min_commission' => 'nullable|integer|min:0',
                'user_commission' => 'nullable|integer|min:0',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $data = $this->castAgentLevelPayload($data);
            $level = AgentLevel::create($data);
            return $this->success($level, __('admin.agent_level_created'), ResponseCode::CREATED);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 更新代理等级。
     *
     * update() 参数说明：
     * - $id 表示 agent_levels 表主键，用于定位要更新的代理等级记录。
     * - level_code 表示代理等级编码，更新时要求除当前记录外唯一。
     * - name、max_commission、min_commission、user_commission 含义与新增接口一致。
     *
     * @param Request $request 当前 HTTP 请求对象，承载代理等级更新表单参数。
     * @param int|string $id agent_levels 表主键。
     * @return \Illuminate\Http\JsonResponse 更新后的代理等级记录。
     *
     * 失败语义：
     * - 路由 ID 非法或记录不存在时返回 VALIDATION_FAILED / DATA_NOT_FOUND；未预期异常统一返回 serverErrorResponse()。
     */
    public function update(Request $request, $id)
    {
        try {
            if ($routeIdError = $this->validateAgentLevelRouteId($id)) {
                return $routeIdError;
            }

            $id = (int) $id;
            $level = AgentLevel::find($id);
            if (!$level) {
                return $this->error(__('admin.agent_level_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $data = $this->normalizePayload($request);

            $validator = Validator::make($data, [
                'level_code' => 'required|integer|unique:agent_levels,level_code,' . $id,
                'name'  => 'required|string|max:50',
                'max_commission' => 'nullable|integer|min:0',
                'min_commission' => 'nullable|integer|min:0',
                'user_commission' => 'nullable|integer|min:0',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $data = $this->castAgentLevelPayload($data);
            $level->update($data);
            return $this->success($level, __('admin.agent_level_updated'), ResponseCode::UPDATED);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 删除代理等级。
     *
     * destroy() 参数说明：
     * - $id 表示 agent_levels 表主键，用于定位要删除的代理等级记录。
     * - 删除前只校验记录是否存在；是否允许删除已被用户引用的等级，应由后续业务规则或数据库约束控制。
     *
     * @param int|string $id agent_levels 表主键。
     * @return \Illuminate\Http\JsonResponse 删除结果响应。
     *
     * 失败语义：
     * - 路由 ID 非法或记录不存在时返回 VALIDATION_FAILED / DATA_NOT_FOUND；未预期异常统一返回 serverErrorResponse()。
     */
    public function destroy($id)
    {
        try {
            if ($routeIdError = $this->validateAgentLevelRouteId($id)) {
                return $routeIdError;
            }

            $id = (int) $id;
            $level = AgentLevel::find($id);
            if (!$level) {
                return $this->error(__('admin.agent_level_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $level->delete();
            return $this->success([], __('admin.agent_level_deleted'), ResponseCode::DELETED);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 校验代理等级路由 ID，避免非严格数字字符串命中 agent_levels.id。
     *
     * @param mixed $id 路由参数中的 agent_levels.id。
     * @return \Illuminate\Http\JsonResponse|null ID 非法时返回统一错误响应，否则返回 null。
     */
    private function validateAgentLevelRouteId($id)
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
     * 将已通过校验的代理等级数值字段转为整数。
     *
     * @param array<string, mixed> $data 已通过 Validator 校验的代理等级字段。
     * @return array<string, mixed> 可写入 agent_levels 的字段。
     */
    private function castAgentLevelPayload(array $data): array
    {
        foreach (['level_code', 'max_commission', 'min_commission', 'user_commission'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '') {
                $data[$field] = (int) $data[$field];
            }
        }

        return $data;
    }

    /**
     * 规范化代理等级保存参数。
     *
     * normalizePayload() 参数说明：
     * - level_code 表示真实表中的代理等级编码字段。
     * - level 表示旧页面提交的等级编码字段，进入模型前映射为 agent_levels.level_code。
     * - name 表示代理等级名称。
     * - commission_rate 表示旧页面提交的返佣比例字段，进入模型前映射为 user_commission。
     * - max_commission 表示最大返佣值。
     * - min_commission 表示最小返佣值。
     * - user_commission 表示用户返佣值。
     *
     * @param Request $request 当前 HTTP 请求对象，承载页面表单提交参数。
     * @return array<string, mixed> 可安全写入 agent_levels 表的字段集合。
     */
    private function normalizePayload(Request $request)
    {
        return [
            'level_code' => $request->input('level_code', $request->input('level')),
            'name' => $request->input('name'),
            'max_commission' => $request->input('max_commission', 0),
            'min_commission' => $request->input('min_commission', 0),
            'user_commission' => $request->input('user_commission', $request->input('commission_rate', 0)),
        ];
    }
}
