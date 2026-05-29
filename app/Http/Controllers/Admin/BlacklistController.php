<?php

namespace App\Http\Controllers\Admin;

use App\Models\Blacklist;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Blacklist Management Controller
 * 黑名单管理控制器
 */
class BlacklistController extends AdminBaseController
{
    /**
     * List all blacklist entries
     * 获取黑名单列表
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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
     * Add entry to blacklist
     * 添加黑名单记录
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Update blacklist entry
     * 更新黑名单记录
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
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
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Delete from blacklist
     * 删除黑名单记录
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $entry = Blacklist::find($id);
            if (!$entry) {
                return $this->error(__('admin.blacklist_entry_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $entry->delete();
            return $this->success([], __('admin.blacklist_entry_deleted'), ResponseCode::DELETED);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }
}
