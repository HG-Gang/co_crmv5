<?php

namespace App\Models;

use App\Models\BaseModel;

/**
 * 组配置模型 | Group Config Model
 * 
 * 存储各种交易组或业务组的配置参数。
 * Stores configuration parameters for various trading or business groups.
 */
class GroupConfig extends BaseModel
{
    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'group_configs';

    /**
     * 关联成对的组配置 | Relation: Paired Group Config
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function pairedGroup()
    {
        return $this->belongsTo(self::class, 'pair_id');
    }

    /**
     * 作用域：代理组 | Scope: Agent groups
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAgent($query)
    {
        return $query->where('category', 1);
    }

    /**
     * 作用域：用户组 | Scope: User groups
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUser($query)
    {
        return $query->where('category', 2);
    }

    /**
     * 作用域：启用的组 | Scope: Enabled groups
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', 1);
    }

    /**
     * 作用域：默认组 | Scope: Default groups
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', 1);
    }
}
