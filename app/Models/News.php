<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 08:28
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * 新闻公告模型。
 *
 * 文件功能：
 * - news 表保存后台发布的新闻公告内容，供后台公告管理和前台公告列表读取。
 * - title 表示公告标题，content 表示公告正文内容，image 表示公告封面图或配图地址。
 * - author_id 表示发布公告的后台管理员 ID，author_name 表示发布时记录的管理员名称快照。
 * - is_published 表示公告是否发布：1=已发布，0=草稿或未发布。
 */
class News extends BaseModel
{
    use HasFactory;

    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 news。
     */
    protected $table = 'news';

    /**
     * 限定已发布的新闻公告。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 表示新闻公告查询构造器。
     * @return \Illuminate\Database\Eloquent\Builder 已追加 is_published=1 条件的查询构造器。
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', 1);
    }
}
