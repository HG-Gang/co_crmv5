<?php

namespace App\Http\Controllers\Admin;

use App\Models\News;
use App\Constants\ResponseCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * News and Announcement Controller
 * 新闻及公告控制器
 */
class NewsController extends AdminBaseController
{
    /**
     * List all news
     * 获取所有新闻公告列表
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);

        $query = News::query();

        if ($request->filled('title')) {
            $query->where('title', 'LIKE', "%{$request->title}%");
        }

        $news = $query->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);

        return $this->success($news, __('admin.news_list_fetched'));
    }

    /**
     * Create news
     * 创建新闻公告
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:200',
                'content' => 'required',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $data = $request->all();
            $news = News::create($data);

            return $this->success($news, __('admin.news_created'), ResponseCode::CREATED);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Update news
     * 更新新闻公告
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id = null)
    {
        try {
            // admin.php 使用统一 POST 地址，记录 ID 从请求体传入；兼容旧的 /{id} 调用方式。
            $id = $id ?: $request->input('id');
            $news = News::find($id);
            if (!$news) {
                return $this->error(__('admin.news_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:200',
                'content' => 'required',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $news->update($request->all());

            return $this->success($news, __('admin.news_updated'), ResponseCode::UPDATED);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Delete news
     * 删除新闻公告
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $id = null)
    {
        try {
            // 删除接口同样兼容 POST body id，避免路由必须暴露路径参数。
            $id = $id ?: $request->input('id');
            $news = News::find($id);
            if (!$news) {
                return $this->error(__('admin.news_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $news->delete();

            return $this->success([], __('admin.news_deleted'), ResponseCode::DELETED);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }

    /**
     * Toggle publish status
     * 切换发布状态
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function togglePublish($id)
    {
        try {
            $news = News::find($id);
            if (!$news) {
                return $this->error(__('admin.news_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            $news->update(['is_published' => !$news->is_published]);

            return $this->success([], __('admin.publish_status_toggled'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), ResponseCode::SERVER_ERROR);
        }
    }
}
