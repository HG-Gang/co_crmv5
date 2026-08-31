<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 08:37
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * 大代理登录日志模型。
 *
 * 文件功能：
 * - big_agent_login_logs 表保存大代理账号登录审计记录，用于后台查看大代理账号登录历史。
 * - big_agent_id 表示登录的大代理账号 ID，对应 big_agents.id。
 * - login_ip 表示登录来源 IP，用于安全审计和异常登录排查。
 * - login_at 表示登录发生时间。
 */
class BigAgentLoginLog extends BaseModel
{
    use HasFactory;

    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 big_agent_login_logs。
     */
    protected $table = 'big_agent_login_logs';

    /**
     * 关联登录日志所属的大代理账号。
     *
     * 参数逻辑说明：
     * - 外键 big_agent_id 来自 big_agent_login_logs.big_agent_id，表示本次登录属于哪个大代理账号。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 返回登录日志所属的 BigAgent 关系。
     */
    public function bigAgent()
    {
        return $this->belongsTo(BigAgent::class, 'big_agent_id');
    }
}
