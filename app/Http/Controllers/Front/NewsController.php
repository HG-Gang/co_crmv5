<?php

namespace App\Http\Controllers\Front;

use App\Models\News;
use App\Support\FrontLegacyData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Front News Controller
 * 前台新闻公告控制器。
 *
 * 旧项目前台存在 news_list 模块；新项目在这里提供对应的新闻公告列表 API，
 * 页面仍由 Layui Blade 渲染，列表数据通过 JWT API 动态读取。
 */
class NewsController extends FrontBaseController
{
    /**
     * Paginated published news list.
     * 分页读取已发布新闻，并按当前 JS 传入的 X-Locale 优先取 news_langs 翻译。
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function newsList(Request $request): JsonResponse
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 15);
        $locale = $request->header('X-Locale', app()->getLocale());

        $query = News::published();

        if ($request->filled('title')) {
            $title = trim((string) $request->input('title'));
            $translatedIds = DB::table('news_langs')
                ->where('lang_code', $locale)
                ->where('title', 'like', '%' . $title . '%')
                ->pluck('news_id')
                ->all();
            $query->where(function ($inner) use ($title, $translatedIds) {
                $inner->where('title', 'like', '%' . $title . '%');
                if ($translatedIds) {
                    $inner->orWhereIn('id', $translatedIds);
                }
            });
        }
        if ($request->filled('author_name')) {
            $query->where('author_name', 'like', '%' . trim((string) $request->input('author_name')) . '%');
        }
        FrontLegacyData::applyCreatedAtFilter($query, $request);

        $paginator = $query->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        // paginator 内部 collection 逐条本地化，保留分页元数据给前端 laypage 使用。
        $paginator->getCollection()->transform(function ($news) use ($locale) {
            $lang = DB::table('news_langs')
                ->where('news_id', $news->id)
                ->where('lang_code', $locale)
                ->first();

            if ($lang) {
                $news->title = $lang->title ?: $news->title;
                $news->content = $lang->content ?: $news->content;
            }

            return [
                'id' => $news->id,
                'title' => $news->title,
                'author_name' => $news->author_name,
                'created_at' => FrontLegacyData::dateTime($news->created_at),
            ];
        });

        return $this->success(['news' => $paginator], 'response.query_success');
    }
}
