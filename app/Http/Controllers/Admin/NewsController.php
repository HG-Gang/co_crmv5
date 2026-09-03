<?php

/**
 * Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/03
 * Time: 14:30
 */

namespace App\Http\Controllers\Admin;

use App\Models\News;
use App\Constants\ResponseCode;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * 后台新闻公告控制器。
 *
 * 文件功能：
 * - 负责后台新闻公告的列表查询、新增、更新、删除和发布状态切换。
 *
 * 功能逻辑说明：
 * - 数据来源为 news 表，前后台公告展示均以该表记录为准。
 * - 接口响应文案统一使用 admin 语言包，保证后端支持多语言返回。
 *
 * 适用场景：
 * - 后台 Blade 页面通过分页列表、创建、编辑、删除和发布切换按钮调用本控制器接口。
 */
class NewsController extends AdminBaseController
{
    /**
     * 获取新闻公告分页列表。
     *
     * 参数逻辑说明：
     * - page 表示当前页码，默认第 1 页。
     * - per_page 表示每页数量，默认每页 15 条。
     * - title 表示新闻公告标题筛选关键字，对应 news.title，使用 LIKE 做模糊匹配。
     *
     * @param Request $request HTTP 请求对象，承载 page、per_page、title 等列表筛选参数。
     * @return \Illuminate\Http\JsonResponse 新闻公告分页列表响应。
     */
    public function index(Request $request)
    {
        $input = $request->all();
        foreach (['title', 'start_date', 'end_date', 'is_published'] as $filter) {
            if (array_key_exists($filter, $input)
                && ($input[$filter] === null || $input[$filter] === '')) {
                unset($input[$filter]);
            }
        }

        $validator = Validator::make($input, [
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'title' => 'sometimes|string',
            'start_date' => 'sometimes|date_format:Y-m-d',
            'end_date' => 'sometimes|date_format:Y-m-d|after_or_equal:start_date',
            'is_published' => 'sometimes|integer|in:0,1',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        $filters = $validator->validated();
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 15);

        $query = News::query();

        if (isset($filters['title']) && $filters['title'] !== '') {
            $query->where('title', 'LIKE', '%' . $filters['title'] . '%');
        }
        if (isset($filters['start_date'])) {
            $query->where(
                'updated_at',
                '>=',
                CarbonImmutable::parse($filters['start_date'], config('app.timezone'))->startOfDay()->timestamp
            );
        }
        if (isset($filters['end_date'])) {
            $query->where(
                'updated_at',
                '<=',
                CarbonImmutable::parse($filters['end_date'], config('app.timezone'))->endOfDay()->timestamp
            );
        }
        if (isset($filters['is_published'])) {
            $query->where('is_published', (int) $filters['is_published']);
        }

        $news = $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return $this->success($news, __('admin.news_list_fetched'));
    }

    /**
     * 创建新闻公告。
     *
     * 参数逻辑说明：
     * - title 表示新闻公告标题，对应 news.title，新增时必填且最长 500 个字符。
     * - content 表示新闻公告正文内容，对应 news.content，新增时必填。
     * - 仅写入验证后的 title/content/is_published，并固定记录当前管理员作者快照。
     *
     * @param Request $request HTTP 请求对象，承载 title、content 等新闻公告新增字段。
     * @return \Illuminate\Http\JsonResponse 创建成功后返回新新闻公告记录。
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:500',
                'content' => 'required|string',
                'is_published' => 'sometimes|integer|in:0,1',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $admin = $request->user('admin') ?: Auth::guard('admin')->user();
            if (!$admin) {
                return $this->error(__('response.auth_failed'), ResponseCode::AUTH_FAILED);
            }

            $data = $validator->validated();
            $data['is_published'] = (int) ($data['is_published'] ?? 0);
            $data['author_id'] = (int) $admin->id;
            $data['author_name'] = (string) $admin->username;
            $news = DB::transaction(static function () use ($data): News {
                return News::create($data);
            });

            return $this->success($news, __('admin.news_created'), ResponseCode::CREATED);
        } catch (\Throwable $e) {
            return $this->newsServerErrorResponse($e, 'store');
        }
    }

    /**
     * 更新新闻公告。
     *
     * 参数逻辑说明：
     * - id 表示 news.id，优先读取路由参数，兼容从 POST body 传入 id 的旧页面调用方式。
     * - title 表示新闻公告标题，对应 news.title，更新时必填且最长 500 个字符。
     * - content 表示新闻公告正文内容，对应 news.content，更新时必填。
     *
     * @param Request $request HTTP 请求对象，承载 id、title、content 等新闻公告更新字段。
     * @param int|null $id 路由中的 news.id；为空时从请求体 id 兼容读取。
     * @return \Illuminate\Http\JsonResponse 更新成功后返回新闻公告记录。
     */
    public function update(Request $request, $id = null)
    {
        try {
            // 只有调用方确实没有提供路由 ID 时才兼容 body；字符串 "0" 仍必须按非法路由 ID 处理。
            if ($id === null) {
                $id = $request->input('id');
            }
            if ($routeIdError = $this->validateNewsRouteId($id)) {
                return $routeIdError;
            }

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:500',
                'content' => 'required|string',
                'is_published' => 'sometimes|integer|in:0,1',
            ]);

            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
            }

            $admin = $request->user('admin') ?: Auth::guard('admin')->user();
            if (!$admin) {
                return $this->error(__('response.auth_failed'), ResponseCode::AUTH_FAILED);
            }

            $id = (int) $id;
            $data = $validator->validated();
            $news = DB::transaction(function () use ($id, $data, $admin) {
                $news = News::query()->whereKey($id)->lockForUpdate()->first();
                if (!$news) {
                    return null;
                }

                $oldTitle = $news->title;
                $oldContent = $news->content;
                $translations = DB::table('news_langs')
                    ->where('news_id', $id)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->get(['id', 'title', 'content']);

                $mirrorIds = [];
                foreach ($translations as $translation) {
                    // PHP byte-level equality avoids case/accent/trailing-space folding by the DB collation.
                    if ($translation->title === $oldTitle && $translation->content === $oldContent) {
                        $mirrorIds[] = (int) $translation->id;
                    }
                }

                $updates = [
                    'title' => $data['title'],
                    'content' => $data['content'],
                    'author_id' => (int) $admin->id,
                    'author_name' => (string) $admin->username,
                ];
                if (array_key_exists('is_published', $data)) {
                    $updates['is_published'] = (int) $data['is_published'];
                }
                $news->update($updates);

                if ($mirrorIds !== []) {
                    $now = time();
                    $fitsTranslationStorage = mb_strlen($data['title'], 'UTF-8') <= 255
                        && strlen($data['content']) <= 65535;
                    $translationUpdates = $fitsTranslationStorage
                        ? [
                            'title' => $data['title'],
                            'content' => $data['content'],
                            'updated_at' => $now,
                        ]
                        : [
                            'deleted_at' => $now,
                            'updated_at' => $now,
                        ];
                    DB::table('news_langs')->whereIn('id', $mirrorIds)->update($translationUpdates);
                }

                return $news->refresh();
            });

            if (!$news) {
                return $this->error(__('admin.news_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            return $this->success($news, __('admin.news_updated'), ResponseCode::UPDATED);
        } catch (\Throwable $e) {
            return $this->newsServerErrorResponse($e, 'update', $id);
        }
    }

    /**
     * 删除新闻公告。
     *
     * 参数逻辑说明：
     * - id 表示 news.id，优先读取路由参数，兼容从 POST body 传入 id 的旧页面调用方式。
     * - delete() 按 News 模型当前删除策略执行；若模型启用软删除则保留删除标记，否则执行物理删除。
     *
     * @param Request $request HTTP 请求对象，承载兼容旧页面提交的 id 参数。
     * @param int|null $id 路由中的 news.id；为空时从请求体 id 兼容读取。
     * @return \Illuminate\Http\JsonResponse 删除结果响应。
     */
    public function destroy(Request $request, $id = null)
    {
        try {
            // 路由值即使为 "0" 也不能回退到 body 的另一条记录 ID。
            if ($id === null) {
                $id = $request->input('id');
            }
            if ($routeIdError = $this->validateNewsRouteId($id)) {
                return $routeIdError;
            }

            $id = (int) $id;
            $deleted = DB::transaction(function () use ($id): bool {
                $news = News::query()->whereKey($id)->lockForUpdate()->first();
                if (!$news) {
                    return false;
                }

                $news->delete();
                return true;
            });

            if (!$deleted) {
                return $this->error(__('admin.news_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            return $this->success([], __('admin.news_deleted'), ResponseCode::DELETED);
        } catch (\Throwable $e) {
            return $this->newsServerErrorResponse($e, 'destroy', $id);
        }
    }

    /**
     * 切换新闻公告发布状态。
     *
     * 参数逻辑说明：
     * - id 表示 news.id，用于定位需要切换发布状态的新闻公告。
     * - is_published 表示是否发布，本方法会把当前布尔值取反。
     * - togglePublish 用于切换发布状态，适合后台列表里的启用/停用按钮调用。
     *
     * @param int $id 路由中的 news.id。
     * @return \Illuminate\Http\JsonResponse 发布状态切换结果响应。
     */
    public function togglePublish($id)
    {
        try {
            if ($routeIdError = $this->validateNewsRouteId($id)) {
                return $routeIdError;
            }

            $id = (int) $id;
            $news = DB::transaction(function () use ($id) {
                $news = News::query()->whereKey($id)->lockForUpdate()->first();
                if (!$news) {
                    return null;
                }

                $news->update(['is_published' => $news->is_published ? 0 : 1]);
                return $news->fresh();
            });

            if (!$news) {
                return $this->error(__('admin.news_not_found'), ResponseCode::DATA_NOT_FOUND);
            }

            return $this->success([], __('admin.publish_status_toggled'));
        } catch (\Throwable $e) {
            return $this->newsServerErrorResponse($e, 'togglePublish', $id);
        }
    }

    /**
     * 写入新闻公告操作失败日志并返回统一服务端错误响应。
     *
     * 参数逻辑说明：
     * - operation：当前操作名称（store/update/destroy/togglePublish），用于定位失败来源。
     * - exception：捕获的异常对象，提取异常类与异常码写入日志上下文。
     * - newsId：可选的新闻公告 ID，更新/删除/切换操作会传入，用于关联失败记录。
     * - 异常码只记录整数或符合安全字符集的字符串，避免把 SQL 片段或敏感信息写入日志。
     *
     * @param \Throwable $exception 捕获的异常对象。
     * @param string $operation 操作名称，标识失败的具体入口。
     * @param int|string|null $newsId 可选的新闻公告 ID，用于关联失败记录。
     * @return \Illuminate\Http\JsonResponse 统一服务端错误响应，不泄露异常细节。
     */
    private function newsServerErrorResponse(\Throwable $exception, string $operation, $newsId = null)
    {
        $context = [
            'operation' => $operation,
            'exception_class' => get_class($exception),
        ];
        if (filter_var($newsId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false) {
            $context['news_id'] = (int) $newsId;
        }
        $exceptionCode = $exception->getCode();
        if (is_int($exceptionCode)
            || (is_string($exceptionCode)
                && (bool) preg_match('/^[A-Za-z0-9_.:-]{1,64}$/D', $exceptionCode))) {
            $context['exception_code'] = $exceptionCode;
        }

        Log::error('Admin news operation failed.', $context);

        return $this->serverErrorResponse();
    }

    /**
     * 校验新闻公告路由主键，必须为整数。
     *
     * @param mixed $id news.id。
     * @return \Illuminate\Http\JsonResponse|null 非法时返回参数错误响应，合法时返回 null。
     */
    private function validateNewsRouteId($id)
    {
        $validator = Validator::make(['id' => $id], [
            'id' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), ResponseCode::VALIDATION_FAILED);
        }

        return null;
    }
}
