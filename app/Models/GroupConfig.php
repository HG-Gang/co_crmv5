<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/26
 * Time: 13:28
 */

namespace App\Models;

/**
 * 组配置模型。
 *
 * 文件功能：
 * - group_configs 表保存代理组和客户交易组配置，用于开户、调组、返佣规则和交易账户分组。
 * - legacy_group_id 表示旧库 group_config.id，只用于旧数据身份映射，不得与 pair_id 混用。
 * - pair_id 表示成对关联的组配置 ID，常用于代理组与客户组之间的默认配对。
 * - name 表示组名称，radix 表示组参数基数或旧项目组编码。
 * - category 表示组类型：1=代理组，2=客户组。
 * - has_commission 表示该组是否参与返佣。
 * - is_enabled 表示是否启用，is_ecn 表示是否 ECN 组，is_default 表示是否默认组。
 * - created_by 和 updated_by 表示创建、更新该组配置的后台管理员 ID，用于审计追踪。
 */
class GroupConfig extends BaseModel
{
    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 group_configs。
     */
    protected $table = 'group_configs';

    /**
     * 关联成对的组配置。
     *
     * 参数逻辑说明：
     * - 外键 pair_id 来自 group_configs.pair_id，表示当前组默认配对的另一组配置。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 返回配对 GroupConfig 关系。
     */
    public function pairedGroup()
    {
        return $this->belongsTo(self::class, 'pair_id');
    }

    /**
     * 限定代理组配置。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 表示组配置查询构造器。
     * @return \Illuminate\Database\Eloquent\Builder 已追加 category=1 条件的查询构造器。
     */
    public function scopeAgent($query)
    {
        return $query->where('category', 1);
    }

    /**
     * 限定客户交易组配置。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 表示组配置查询构造器。
     * @return \Illuminate\Database\Eloquent\Builder 已追加 category=2 条件的查询构造器。
     */
    public function scopeUser($query)
    {
        return $query->where('category', 2);
    }

    /**
     * 限定启用组配置。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 表示组配置查询构造器。
     * @return \Illuminate\Database\Eloquent\Builder 已追加 is_enabled=1 条件的查询构造器。
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', 1);
    }

    /**
     * 限定默认组配置。
     *
     * @param \Illuminate\Database\Eloquent\Builder $query 表示组配置查询构造器。
     * @return \Illuminate\Database\Eloquent\Builder 已追加 is_default=1 条件的查询构造器。
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', 1);
    }
}
