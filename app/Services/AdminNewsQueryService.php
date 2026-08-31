<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 13:08
 */

namespace App\Services;

use App\Models\News;

/**
 * 后台新闻编辑字段查询服务。
 *
 * 文件功能：
 * - 按主键读取 news 表中后台编辑表单所需的最小字段集（id/title/content/is_published），
 *   记录不存在时抛 ModelNotFoundException。
 * - 明确不负责：新闻列表查询、多语言翻译与前台展示口径。
 */
final class AdminNewsQueryService
{
    /**
     * Load only the fields needed by the admin edit page.
     *
     * @return array{id: int, title: string, content: string, is_published: int}
     */
    public function editableFields(int $id): array
    {
        $news = News::query()
            ->select(['id', 'title', 'content', 'is_published'])
            ->whereKey($id)
            ->firstOrFail();

        return [
            'id' => (int) $news->id,
            'title' => (string) $news->title,
            'content' => (string) $news->content,
            'is_published' => (int) $news->is_published,
        ];
    }
}
