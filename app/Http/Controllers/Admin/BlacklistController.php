<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\Admin;

use App\Models\Blacklist;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 后台黑名单管理控制器。
 *
 * 文件功能：
 * - 负责后台风控黑名单的列表查询、新增、更新和删除。
 * - 黑名单记录可包含姓名、身份证号、邮箱、手机号等识别信息，列表关键字会同时匹配这些字段。
 * - 当前新增和更新仍沿用 Blacklist 模型的 fillable 白名单承接字段，控制器只做必要参数校验。
 */
class BlacklistController extends AdminBaseController
{
    /**
     * 获取黑名单列表。
     *
     * index() 参数说明：
     * - page 表示当前页码，默认第 1 页。
     * - per_page 表示每页数量，默认 15 条。
     * - keyword 表示黑名单关键字，会同时匹配 name、id_card、email、phone。
     * - name 表示黑名单对象姓名。
     * - id_card 表示身份证号。
     * - email 表示邮箱。
     * - phone 表示手机号。
     *
     * @param Request $request 当前 HTTP 请求对象，承载分页参数和 keyword 搜索条件。
     * @return \Illuminate\Http\JsonResponse 返回分页黑名单列表。
     */
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);

        $query = Blacklist::query();

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;
            $query->where(function($q) use ($keyword) {
                $q->where('name', 'LIKE', "%{$keyword}%")
                  ->orWhere('id_card', 'LIKE', "%{$keyword}%")
                  ->orWhere('email', 'LIKE', "%{$keyword}%")
                  ->orWhere('phone', 'LIKE', "%{$keyword}%");
            });
        }

        $list = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->success($list, __('admin.blacklist_fetched'));
    }

    /**
     * 添加黑名单记录。
     *
     * store() 参数说明：
     * - name 表示黑名单对象姓名，当前为必填字段。
     * - id_card 表示身份证号，可由页面提交并通过模型白名单写入。
     * - email 表示邮箱，可由页面提交并通过模型白名单写入。
     * - phone 表示手机号，可由页面提交并通过模型白名单写入。
     * - reason 表示加入黑名单原因，可由页面提交并通过模型白名单写入。
     * - status 表示黑名单启用状态，可由页面提交并通过模型白名单写入。
     * - $request->all() 会写入 Blacklist 模型允许的字段，实际可写字段由模型 fillable 控制。
     *
     * @param Request $request 当前 HTTP 请求对象，承载黑名单新增表单参数。
     * @return \Illuminate\Http\JsonResponse 新增后的黑名单记录。
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:50',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $entry = Blacklist::create($request->all());
            return $this->success($entry, __('admin.blacklist_entry_added'), ResponseCode::CREATED);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 更新黑名单记录。
     *
     * update() 参数说明：
     * - $id 表示 blacklists 表主键，用于定位要更新的黑名单记录。
     * - name、id_card、email、phone、reason、status 含义与新增接口一致。
     * - $request->all() 会写入 Blacklist 模型允许的字段，实际可写字段由模型 fillable 控制。
     *
     * @param Request $request 当前 HTTP 请求对象，承载黑名单更新表单参数。
     * @param int|string $id blacklists 表主键。
     * @return \Illuminate\Http\JsonResponse 更新后的黑名单记录。
     */
    public function update(Request $request, $id)
    {
        try {
            if ($routeIdError = $this->validateBlacklistRouteId($id)) {
                return $routeIdError;
            }

            $id = (int) $id;
            $entry = Blacklist::find($id);
            if (!$entry) {
                return $this->error(__('admin.blacklist_entry_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:50',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $entry->update($request->all());
            return $this->success($entry, __('admin.blacklist_entry_updated'), ResponseCode::UPDATED);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 删除黑名单记录。
     *
     * destroy() 参数说明：
     * - $id 表示 blacklists 表主键，用于定位要删除的黑名单记录。
     *
     * @param int|string $id blacklists 表主键。
     * @return \Illuminate\Http\JsonResponse 删除结果响应。
     */
    public function destroy($id)
    {
        try {
            if ($routeIdError = $this->validateBlacklistRouteId($id)) {
                return $routeIdError;
            }

            $id = (int) $id;
            $entry = Blacklist::find($id);
            if (!$entry) {
                return $this->error(__('admin.blacklist_entry_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $entry->delete();
            return $this->success([], __('admin.blacklist_entry_deleted'), ResponseCode::DELETED);
        } catch (\Exception $e) {
            return $this->serverErrorResponse();
        }
    }

    /**
     * 校验黑名单主键必须为整数。
     *
     * @param mixed $id blacklists 表主键原始请求值。
     * @return \Illuminate\Http\JsonResponse|null 非法时返回参数错误响应，合法时返回 null。
     */
    private function validateBlacklistRouteId($id)
    {
        $validator = Validator::make(['id' => $id], [
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }
}
