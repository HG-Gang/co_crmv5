<?php

namespace App\Models;

use App\Models\BaseModel;

/**
 * 用户详细信息模型 | User Info Model
 * 
 * 存储用户的详细业务信息、资金情况及代理层级关系。
 * Stores user's detailed business info, financial status, and agent hierarchy.
 */
class UserInfo extends BaseModel
{
    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'user_infos';

    /**
     * 可批量赋值的属性 | The attributes that are mass assignable.
     * @var array
     */
    protected $fillable = [
        'user_id', 'login_id', 'user_name', 'phone', 'gender', 'avatar',
        'level_id', 'group_id', 'parent_id', 'account_type', 'family_tree',
        'total_funds', 'used_margin', 'avail_margin', 'equity', 'effective_credit',
        'risk_ratio', 'margin_amount', 'leverage', 'cust_vol', 'pay_provider_id',
        'equity_ratio', 'comm_rate', 'is_ecn', 'follow_parent_ecn', 'auth_status',
        'is_mt4_synced', 'is_mt4_enabled', 'is_mt4_readonly', 'is_withdrawal_allowed',
        'is_deposit_allowed', 'is_agent_confirmed', 'original_group', 'mt4_group',
        'mt4_code', 'trading_mode', 'settle_method', 'settle_cycle', 'country',
        'city', 'state', 'address', 'is_gift_allowed', 'data_source', 'remark',
        'created_by', 'updated_by'
    ];

    /**
     * 关联登录信息 | Relation: Login
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function login()
    {
        return $this->belongsTo(UserLogin::class, 'login_id');
    }

    /**
     * 关联上级代理 | Relation: Parent Agent
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id', 'user_id');
    }

    /**
     * 关联直属下级 | Relation: Direct Children
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function directChildren()
    {
        return $this->hasMany(self::class, 'parent_id', 'user_id');
    }

    /**
     * 关联实名认证信息 | Relation: Auth Info
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function auth()
    {
        return $this->hasOne(UserAuth::class, 'user_id', 'user_id');
    }

    /**
     * 关联代理等级 | Relation: Level
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function level()
    {
        return $this->belongsTo(AgentLevel::class, 'level_id', 'id');
    }

    /**
     * 关联组配置 | Relation: Group Config
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function groupConfig()
    {
        return $this->belongsTo(GroupConfig::class, 'group_id', 'id');
    }

    /**
     * 从家谱树中获取所有祖先ID | Get all ancestors from family_tree
     * 
     * @return array
     */
    public function getAncestorIds(): array
    {
        if (empty($this->family_tree)) return [];
        $ids = explode(',', $this->family_tree);
        array_pop($ids); // 移除自身 | remove self
        return array_map('intval', $ids);
    }

    /**
     * 关联所有后代记录 | Relation: Descendants
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function descendants()
    {
        return $this->hasMany(AgentDescendant::class, 'agent_id', 'user_id');
    }

    /**
     * 获取直属下级代理 | Get direct sub-agents
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function directSubAgents()
    {
        return $this->hasMany(AgentDescendant::class, 'agent_id', 'user_id')
            ->where('descendant_type', 1)
            ->where('is_direct', 1);
    }

    /**
     * 获取直属客户 | Get direct customers
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function directCustomers()
    {
        return $this->hasMany(AgentDescendant::class, 'agent_id', 'user_id')
            ->where('descendant_type', 2)
            ->where('is_direct', 1);
    }

    /**
     * 是否为代理 | Is Agent
     * @return bool
     */
    public function isAgent()
    {
        return (int) $this->account_type === 1;
    }

    /**
     * 是否为普通客户 | Is Customer
     * @return bool
     */
    public function isCustomer()
    {
        return (int) $this->account_type === 2;
    }
}
