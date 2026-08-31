<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 04:47
 */

namespace App\Models;

use App\Models\BaseModel;

/**
 * 用户业务资料模型。
 *
 * 文件功能：
 * - user_infos 表保存前台业务用户资料、代理层级、资金字段和 MT4 状态。
 * - user_id 表示业务用户 ID，是代理树、交易、出入金和资料审核的核心关联键。
 * - login_id 表示 user_logins.id，用于把登录账号和业务资料连接起来。
 * - parent_id 表示上级代理业务用户 ID，family_tree 表示代理家谱链。
 * - account_type 表示账号类型，1=代理商，2=普通客户。
 */
class UserInfo extends BaseModel
{
    /**
     * 单条代理层级链允许的最大祖先深度，固定 128。
     * family_tree 与 agent_descendants 的闭包推导按该上限做防环/防超深保护：
     * 脏数据形成环路时会在 128 层截断报错，而不是无限递归拖垮库；正常业务层级远小于该值。
     *
     * @var int
     */
    public const MAX_HIERARCHY_DEPTH = 128;

    /**
     * 数据表名称。
     *
     * @var string
     */
    protected $table = 'user_infos';

    /**
     * 可批量赋值字段。
     *
     * 字段分组说明：
     * - 身份字段：user_id、login_id、user_name、phone、gender、avatar、account_type。
     * - 层级字段：level_id、group_id、parent_id、family_tree、is_agent_confirmed。
     * - 资金字段：total_funds、used_margin、avail_margin、equity、effective_credit、risk_ratio、margin_amount。
     * - 交易字段：leverage、cust_vol、is_ecn、follow_parent_ecn、trading_mode、settle_method、settle_cycle。
     * - 审核字段：auth_status 表示实名认证状态，is_withdrawal_allowed 和 is_deposit_allowed 表示出入金开关。
     * - MT4 字段：is_mt4_synced、is_mt4_enabled、is_mt4_readonly、mt4_group、mt4_code。
     * - 地址字段：country、city、state、address。
     * - 审计字段：created_by、updated_by、data_source、remark。
     *
     * @var array<int, string>
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
     * 关联登录信息。
     *
     * 逻辑说明：
     * - user_infos.login_id 对应 user_logins.id。
     * - 用于从业务资料反查登录邮箱、账号状态和前台角色。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 前台登录账号。
     */
    public function login()
    {
        return $this->belongsTo(UserLogin::class, 'login_id');
    }

    /**
     * 关联上级代理。
     *
     * 逻辑说明：
     * - 当前 user_infos.parent_id 对应上级代理的 user_infos.user_id。
     * - 普通客户或下级代理通过该关系找到直属上级。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 上级代理资料。
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id', 'user_id');
    }

    /**
     * 关联直属下级。
     *
     * 逻辑说明：
     * - 直属下级的 parent_id 等于当前用户的 user_id。
     * - 用于代理管理页面展示直属代理和直属客户。
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany 直属下级用户资料集合。
     */
    public function directChildren()
    {
        return $this->hasMany(self::class, 'parent_id', 'user_id');
    }

    /**
     * 关联实名认证信息。
     *
     * 逻辑说明：
     * - user_auths.user_id 对应 user_infos.user_id。
     * - auth_status 表示实名认证状态，具体证件信息从 UserAuth 读取。
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne 实名认证资料。
     */
    public function auth()
    {
        return $this->hasOne(UserAuth::class, 'user_id', 'user_id');
    }

    /**
     * 关联代理等级。
     *
     * 逻辑说明：
     * - user_infos.level_id 对应 agent_levels.id。
     * - 用于代理等级展示、返佣比例和升级确认。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 代理等级。
     */
    public function level()
    {
        return $this->belongsTo(AgentLevel::class, 'level_id', 'id');
    }

    /**
     * 关联组配置。
     *
     * 逻辑说明：
     * - user_infos.group_id 对应 group_configs.id。
     * - 用于展示用户所属交易组、MT4 组别和出入金限制。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 组配置。
     */
    public function groupConfig()
    {
        return $this->belongsTo(GroupConfig::class, 'group_id', 'id');
    }

    /**
     * getAncestorIds 沿 parent_id 获取所有祖先代理 ID。
     *
     * family_tree 是派生快照，不能作为当前链路事实源；孤儿父级、非代理父级或循环链路失败关闭。
     *
     * @return array<int, int> 按根到直属顺序排列的上级代理业务用户 ID 列表。
     */
    public function getAncestorIds(): array
    {
        $userId = (int) $this->user_id;
        if ($userId <= 0) {
            return [];
        }

        $ancestorIds = [];
        $visited = [$userId => true];
        $parentId = (int) $this->parent_id;
        while ($parentId > 0) {
            if (isset($visited[$parentId]) || count($ancestorIds) >= self::MAX_HIERARCHY_DEPTH) {
                return [];
            }

            $parent = static::where('user_id', $parentId)
                ->first(['user_id', 'parent_id', 'account_type']);
            if (!$parent || (int) $parent->account_type !== 1) {
                return [];
            }

            $visited[$parentId] = true;
            array_unshift($ancestorIds, $parentId);
            $parentId = (int) $parent->parent_id;
        }

        return $ancestorIds;
    }

    /**
     * 关联所有后代记录。
     *
     * 逻辑说明：
     * - agent_descendants.agent_id 对应当前 user_infos.user_id。
     * - 用于快速查询代理树下所有直属和间接用户。
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany 后代关系集合。
     */
    public function descendants()
    {
        return $this->hasMany(AgentDescendant::class, 'agent_id', 'user_id');
    }

    /**
     * 获取直属下级代理。
     *
     * 逻辑说明：
     * - descendant_type=1 表示下级代理。
     * - is_direct=1 表示直属关系。
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany 直属下级代理关系集合。
     */
    public function directSubAgents()
    {
        return $this->hasMany(AgentDescendant::class, 'agent_id', 'user_id')
            ->where('descendant_type', 1)
            ->where('is_direct', 1);
    }

    /**
     * 获取直属客户。
     *
     * 逻辑说明：
     * - descendant_type=2 表示普通客户。
     * - is_direct=1 表示直属客户关系。
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany 直属客户关系集合。
     */
    public function directCustomers()
    {
        return $this->hasMany(AgentDescendant::class, 'agent_id', 'user_id')
            ->where('descendant_type', 2)
            ->where('is_direct', 1);
    }

    /**
     * isAgent 判断是否为代理商。
     *
     * 逻辑说明：
     * - account_type=1 表示代理商，可拥有代理树、返佣和客户管理能力。
     *
     * @return bool
     */
    public function isAgent()
    {
        return (int) $this->account_type === 1;
    }

    /**
     * isCustomer 判断是否为普通客户。
     *
     * 逻辑说明：
     * - account_type=2 表示普通客户，不拥有代理管理能力。
     *
     * @return bool
     */
    public function isCustomer()
    {
        return (int) $this->account_type === 2;
    }
}
