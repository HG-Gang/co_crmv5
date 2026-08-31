<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 13:18
 */

namespace App\Http\Controllers\Front;

use App\Constants\ResponseCode;
use App\Models\News;
use App\Support\FrontLegacyData;
use App\Support\SafeHtml;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * 前台新闻公告控制器。
 *
 * 文件功能：
 * - 处理前台新闻公告列表、旧前台新闻搜索、旧前台新闻详情 HTML 和 news_langs 多语言回退。
 * - 新前台 Layui Blade 页面通过 `GET /api/front/news` 读取 JSON 分页数据，页面本身仍由 Laravel Blade 渲染。
 * - 旧前台 `user/newsListSearch` 和 `user/news/news_detail/{newsId}` 保留历史响应结构，避免旧页面表格和弹窗详情失效。
 *
 * 安全边界：
 * - 所有查询只读 News::published() 已发布范围，未发布或不存在时详情页返回 404。
 * - 详情正文经 SafeHtml::sanitize() 白名单过滤后才拼入 HTML，标题、作者等文本一律 e() 转义，防止存储型 XSS 进入旧弹窗。
 */
class NewsController extends FrontBaseController
{
    /**
     * 渲染指定已发布新闻的现代详情页。
     *
     * @param Request $request 当前页面请求。
     * @param int $newsId 新闻主表 ID。
     * @return \Illuminate\View\View
     */
    public function newsPage(Request $request, int $newsId)
    {
        if (!News::published()->where('id', $newsId)->exists()) {
            abort(404);
        }

        return view('front_layui::news.index', ['legacyNewsId' => $newsId]);
    }

    /**
     * 返回新前台新闻公告分页数据。
     *
     * 业务逻辑说明：
     * - newsList 用于返回新前台新闻公告分页数据，只读取 News::published() 范围内的已发布公告。
     * - page 表示当前页码，未传时默认第 1 页。
     * - per_page 表示每页新闻数量，未传时默认 15 条。
     * - X-Locale 表示前端当前语言，用于决定优先读取哪一条 news_langs 翻译记录。
     * - title 表示新闻标题筛选关键字，会同时匹配 news.title 和当前语言 news_langs.title。
     * - translatedIds 表示 news_langs 中命中当前语言标题的新闻 ID 集合，用于让翻译标题也能被搜索到。
     * - author_name 表示按作者名称模糊筛选。
     * - paginator 表示 Laravel 分页对象，保留 total、current_page、last_page 等分页元数据给前端分页组件。
     * - news_langs 记录存在时优先使用翻译标题和翻译内容；翻译字段为空时回退 news 主表标题和内容。
     *
     * @param Request $request HTTP 请求对象，承载 page、per_page、title、author_name、日期范围和 X-Locale 请求头。
     * @return JsonResponse 返回 data.news 分页对象，分页记录中包含 id、title、author_name、created_at、content。
     */
    public function newsList(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->listValidationRules(false));
        if ($validator->fails() || !$this->hasValidDateRange($request)) {
            return $this->error('response.validation_failed', ResponseCode::VALIDATION_FAILED);
        }

        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 15);
        $locale = $request->header('X-Locale', app()->getLocale());

        $query = $this->newsQuery($request, $locale);
        if ($request->filled('author_name')) {
            $query->where('author_name', 'like', '%' . trim((string) $request->input('author_name')) . '%');
        }

        $paginator = $query->orderByDesc('updated_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $translations = $this->newsTranslationsFor($paginator->getCollection(), $locale);

        // 使用分页结果的 ID 批量读取翻译，保留 Layui/Naive 分页元数据并避免逐行查询。
        $paginator->getCollection()->transform(function ($news) use ($translations) {
            return $this->newsRow($news, $translations[(int) $news->id] ?? null);
        });

        return $this->success(['news' => $paginator], 'response.query_success');
    }

    /**
     * 兼容旧前台新闻列表搜索接口。
     *
     * 业务逻辑说明：
     * - newsListSearch 用于兼容旧前台新闻列表搜索接口，响应结构必须保持 `rows + total`。
     * - X-Locale 表示前端当前语言，旧页面同样优先展示 news_langs 当前语言标题和内容。
     * - title 表示新闻标题筛选关键字，会匹配主表标题和当前语言翻译标题。
     * - translatedIds 表示 news_langs 中命中当前语言标题的新闻 ID 集合。
     * - rows 表示旧前台表格数据行，字段包含 news_id、news_title、news_content、rec_upd_date 等历史字段。
     * - total 表示旧前台分页总数，旧 Layui 表格按该字段计算分页。
     *
     * @param Request $request HTTP 请求对象，承载 title、日期范围、分页参数和 X-Locale 请求头。
     * @return JsonResponse 返回旧前台表格结构 rows 与 total。
     */
    public function newsListSearch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->listValidationRules(true));
        if ($validator->fails() || !$this->hasValidDateRange($request)) {
            $message = __('response.validation_failed');

            return response()->json([
                'code' => ResponseCode::VALIDATION_FAILED,
                'message' => $message,
                'msg' => $message,
                'rows' => [],
                'total' => 0,
            ]);
        }

        if (!$this->hasLegacyListCriteria($request) && $this->legacyFrontUserId($request) <= 0) {
            $message = __('response.auth_failed');

            return response()->json([
                'code' => ResponseCode::AUTH_FAILED,
                'message' => $message,
                'msg' => $message,
                'rows' => [],
                'total' => 0,
                'footer' => [],
                'redirect' => true,
                'redirectUrl' => url('/user/login'),
            ]);
        }

        $locale = $request->header('X-Locale', app()->getLocale());
        $query = $this->newsQuery($request, $locale);

        $perPage = (int) $request->input(
            'per_page',
            $request->input('limit', $request->input('rows', 15))
        );
        $page = (int) $request->input('page', 1);

        $total = (clone $query)->count();
        $news = $query->orderByDesc('updated_at')
            ->paginate($perPage, ['*'], 'page', $page)
            ->getCollection();
        $translations = $this->newsTranslationsFor($news, $locale);
        $rows = $news->map(function ($news) use ($translations) {
                return $this->newsRow($news, $translations[(int) $news->id] ?? null);
            })
            ->values();

        return response()->json([
            'rows' => $rows,
            'total' => $total,
        ]);
    }

    /**
     * 返回新闻列表输入规则；旧入口额外接收 limit 和 EasyUI rows 分页字段。
     *
     * @param bool $legacy true 表示旧 rows/total 接口。
     * @return array<string, string>
     */
    private function listValidationRules(bool $legacy): array
    {
        $rules = [
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|between:1,100',
            'news_id' => 'sometimes|integer|min:1',
            'date_from' => 'sometimes|date_format:Y-m-d',
            'date_to' => 'sometimes|date_format:Y-m-d',
            'startdate' => 'sometimes|date_format:Y-m-d',
            'enddate' => 'sometimes|date_format:Y-m-d',
        ];

        if ($legacy) {
            $rules['limit'] = 'sometimes|integer|between:1,100';
            $rules['rows'] = 'sometimes|integer|between:1,100';
        }

        return $rules;
    }

    /**
     * 判断旧前台列表请求是否携带了任何可执行的筛选或分页条件。
     *
     * 未携带任何条件且未登录时旧页面视为未登录访问，返回带 redirect 标记的空列表。
     *
     * @param Request $request 旧前台新闻列表请求。
     * @return bool true 表示至少存在一个旧列表参数。
     */
    private function hasLegacyListCriteria(Request $request): bool
    {
        foreach (['page', 'per_page', 'limit', 'rows', 'title', 'news_title', 'news_id', 'date_from', 'date_to', 'startdate', 'enddate'] as $key) {
            if ($request->exists($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 按 FrontLegacyData 的现代字段优先级比较已通过 Y-m-d 格式校验的日期范围。
     *
     * @param Request $request 新闻列表请求。
     * @return bool 未同时提供起止日期或结束日期不早于开始日期时返回 true。
     */
    private function hasValidDateRange(Request $request): bool
    {
        $from = FrontLegacyData::dateFrom($request);
        $to = FrontLegacyData::dateTo($request);

        return !$from || !$to || $to >= $from;
    }

    /**
     * 渲染旧前台新闻详情 HTML。
     *
     * 业务逻辑说明：
     * - newsDetail 用于渲染旧前台新闻详情 HTML，直接返回 HTML 片段而不是统一 JSON。
     * - newsId 表示新闻主表 news.id，用于定位一条已发布新闻；不存在或未发布时返回 404。
     * - X-Locale 表示前端当前语言，详情标题和正文优先使用 news_langs 当前语言翻译。
     * - Schema::hasTable 用于兼容缺少 news_langs 表的部署环境，避免旧项目数据未迁移完时详情页直接报错。
     * - crm-legacy-news 表示旧前台详情页 HTML 容器，内联样式保留旧弹窗详情的基本排版。
     *
     * @param Request $request HTTP 请求对象，承载 X-Locale 请求头。
     * @param int $newsId 新闻主表 news.id。
     * @return \Illuminate\Http\Response 新闻详情 HTML 响应。
     */
    public function newsDetail(Request $request, int $newsId)
    {
        $news = News::published()->where('id', $newsId)->first();
        if (!$news) {
            abort(404);
        }

        $locale = $request->header('X-Locale', app()->getLocale());
        if (Schema::hasTable('news_langs')) {
            $lang = DB::table('news_langs')
                ->where('news_id', $news->id)
                ->where('lang_code', $locale)
                ->whereNull('deleted_at')
                ->first();
            if ($lang) {
                $news->title = $lang->title ?: $news->title;
                $news->content = $lang->content ?: $news->content;
            }
        }

        $html = '<div class="crm-legacy-news">';
        $html .= '<style>.crm-legacy-news{font-family:Arial,"Microsoft YaHei",sans-serif;padding:18px;color:#243042;line-height:1.7}.crm-legacy-news h2{margin:0 0 8px;font-size:20px}.crm-legacy-news .meta{margin-bottom:14px;color:#708196;font-size:12px}.crm-legacy-news img{max-width:100%;border-radius:6px;margin:8px 0 14px}.crm-legacy-news .content{word-break:break-word}</style>';
        $html .= '<h2>' . e($news->title) . '</h2>';
        $html .= '<div class="meta">' . e($news->author_name) . ' ' . e(FrontLegacyData::dateTime($news->created_at)) . '</div>';
        if (!empty($news->image)) {
            $html .= '<img src="' . e($news->image) . '" alt="">';
        }
        // 正文是后台富文本，必须过 SafeHtml 白名单过滤；标题等其余文本已用 e() 转义，防止 XSS 进入旧弹窗 HTML。
        $html .= '<div class="content">' . SafeHtml::sanitize((string) $news->content) . '</div>';
        $html .= '</div>';

        return response($html);
    }

    /**
     * 构造已发布新闻的查询对象，并套用 ID、标题关键字和日期范围筛选。
     *
     * 标题关键字同时匹配主表 title 与当前语言 news_langs.title，翻译命中的新闻通过 ID 集合并入结果。
     *
     * @param Request $request 新闻列表请求，读取 news_id、title/news_title 和日期范围。
     * @param string $locale 当前语言代码，用于查询 news_langs 翻译记录。
     * @return \Illuminate\Database\Eloquent\Builder 已发布新闻的查询对象。
     */
    private function newsQuery(Request $request, string $locale)
    {
        $query = News::published();

        if ($request->filled('news_id')) {
            $query->where('id', (int) $request->input('news_id'));
        }

        if ($request->filled('title') || $request->filled('news_title')) {
            $title = trim((string) $request->input('title', $request->input('news_title')));
            $translatedIds = DB::table('news_langs')
                ->where('lang_code', $locale)
                ->whereNull('deleted_at')
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

        FrontLegacyData::applyCreatedAtFilter($query, $request, 'updated_at');

        return $query;
    }

    /**
     * 将一条新闻组装为兼容新旧前台的列表行。
     *
     * 标题与正文优先取当前语言 news_langs 翻译，翻译字段为空时回退主表值；
     * 输出同时保留新旧字段名（news_title/title、news_content/content 等）供两类页面直接使用。
     *
     * @param News $news 已发布新闻记录。
     * @param string $locale 当前语言代码。
     * @return array<string, mixed> 新旧前台均可直接渲染的新闻行。
     */
    private function newsTranslationsFor($news, string $locale): array
    {
        $newsIds = $news->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        if ($newsIds === []) {
            return [];
        }

        return DB::table('news_langs')
            ->whereIn('news_id', $newsIds)
            ->where('lang_code', $locale)
            ->whereNull('deleted_at')
            ->get(['news_id', 'title', 'content'])
            ->keyBy('news_id')
            ->all();
    }

    private function newsRow(News $news, ?object $lang = null): array
    {
        $title = $lang && $lang->title ? $lang->title : $news->title;
        $content = $lang && $lang->content ? $lang->content : $news->content;
        $createdAt = FrontLegacyData::dateTime($news->created_at);
        $updatedAt = FrontLegacyData::dateTime($news->updated_at ?: $news->created_at);

        return [
            'news_id' => $news->id,
            'id' => $news->id,
            'news_title' => $title,
            'title' => $title,
            'news_content' => $content,
            'content' => $content,
            'author_name' => $news->author_name,
            'rec_crt_date' => $createdAt,
            'rec_upd_date' => $updatedAt,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];
    }
}
