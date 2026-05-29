<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\BaseModel;

/**
 * 新闻模型 | News Model
 * 
 * 管理平台发布的新闻资讯。
 * Manages news and announcements published on the platform.
 */
class News extends BaseModel
{
    use HasFactory;

    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'news';

    /**
     * 作用域：已发布的新闻 | Scope: Published news
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', 1);
    }
}
