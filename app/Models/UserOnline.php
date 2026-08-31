<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:09
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 在线用户模型。
 *
 * 文件功能：
 * - 对应真实数据表 `user_onlines`，记录前台用户最近活跃时间、IP 和浏览器代理信息。
 * - 本表迁移中没有 `deleted_at` 字段，因此不能继承带 SoftDeletes 的 BaseModel，避免查询时自动追加不存在的软删除条件。
 * - 后台在线用户页面默认读取审计展示；强制下线由后台控制器删除在线记录并写入操作审计。
 */
class UserOnline extends Model
{
    /**
     * 数据表名称。
     *
     * @var string
     */
    protected $table = 'user_onlines';

    /**
     * 时间戳存储格式。
     *
     * 逻辑说明：
     * - user_onlines 迁移中时间字段为 Unix 整数时间戳，保持 $dateFormat=U 与表结构一致。
     * - 该表没有 deleted_at，不能继承带 SoftDeletes 的 BaseModel，因此日期格式约定需在此单独声明。
     *
     * @var string
     */
    protected $dateFormat = 'U';

    /**
     * 可批量赋值字段。
     *
     * 字段含义：
     * - user_id：在线用户业务用户 ID，对应 user_infos.user_id。
     * - last_activity：最近活跃时间（Unix 秒），后台在线列表按该字段判断在线状态。
     * - ip_address：最近活跃来源 IP。
     * - user_agent：最近活跃客户端标识。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'last_activity',
        'ip_address',
        'user_agent',
    ];
}
