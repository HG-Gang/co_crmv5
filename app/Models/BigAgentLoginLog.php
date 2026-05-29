<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\BaseModel;

/**
 * 大代理登录日志模型 | Big Agent Login Log Model
 * 
 * 记录大代理的登录历史。
 * Records login history of big agents.
 */
class BigAgentLoginLog extends BaseModel
{
    use HasFactory;

    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'big_agent_login_logs';

    /**
     * 关联大代理 | Relation: Big Agent
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function bigAgent()
    {
        return $this->belongsTo(BigAgent::class, 'big_agent_id');
    }
}
