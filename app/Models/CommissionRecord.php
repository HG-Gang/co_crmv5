<?php

namespace App\Models;

use App\Models\BaseModel;

/**
 * 佣金记录模型 | Commission Record Model
 * 
 * 记录代理获得的佣金详情。
 * Records details of commissions earned by agents.
 */
class CommissionRecord extends BaseModel
{
    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'commission_records';

    /**
     * 关联代理人 | Relation: Agent
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function agent()
    {
        return $this->belongsTo(UserInfo::class, 'agent_id', 'user_id');
    }
}
