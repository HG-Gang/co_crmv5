<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * 管理员模型。
 * 
 * 功能逻辑说明：
 * - admins 表保存后台管理员登录账号、角色绑定和登录状态。
 * - 后台登录成功后会写入 last_login_ip、last_login_at、login_count 和 jwt_token_id。
 * - role_id 表示绑定的 roles.id，菜单权限、按钮权限和接口权限都通过该角色继续读取 role_permissions。
 * - jwt_token_id 表示当前有效 JWT 的唯一编号，用于后台单点登录和 token 失效判断。
 *
 * @property int $id 主键标识
 * @property string|null $role_id 角色标识
 * @property string|null $mobile 手机号
 * @property string|null $email 邮箱
 * @property string $username 用户名
 * @property string $password 密码
 * @property int $login_count 登录次数
 * @property string|null $last_login_ip 最后登录IP
 * @property string|null $last_login_at 最后登录时间
 * @property string|null $last_login_address 最后登录地址
 * @property int $status 状态: 1=启用 0=禁用
 * @property string|null $jwt_token_id SSO: 当前JWT ID
 * @property string|null $created_by 创建人
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AdminLoginLog> $loginLogs
 * @property-read int|null $login_logs_count
 * @property-read \App\Models\Role|null $role
 * @method static \Illuminate\Database\Eloquent\Builder|Admin newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Admin newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Admin onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Admin query()
 * @method static \Illuminate\Database\Eloquent\Builder|Admin whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Admin whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Admin whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Admin whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Admin whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Admin whereJwtTokenId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Admin whereLastLoginAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Admin whereLastLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Admin whereLastLoginIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Admin whereLoginCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Admin whereMobile($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Admin wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Admin whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Admin whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Admin whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Admin whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Admin withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Admin withoutTrashed()
 */
	class Admin extends \Eloquent {}
}

namespace App\Models{
/**
 * 管理员代理绑定模型。
 * 
 * 功能逻辑说明：
 * - admin_agent_bindings 表保存后台管理员与代理节点的绑定关系。
 * - admin_id 表示后台管理员 ID，对应 admins.id。
 * - agent_id 表示被授权管理的代理用户 ID，对应代理体系中的 user_infos.user_id。
 * - binding_type 表示绑定类型，primary=主绑定，extra=额外绑定。
 * - status 表示绑定是否启用，禁用绑定不参与 AdminDataScopeService 的代理树数据范围计算。
 *
 * @property int $id 主键标识
 * @property int $admin_id 管理员标识，对应 admins.id
 * @property int $agent_id 代理用户标识，对应代理体系中的用户标识
 * @property string $binding_type 绑定类型：primary=主绑定，extra=额外绑定
 * @property int $status 状态：1=启用，0=禁用
 * @property \Illuminate\Support\Carbon $created_at 创建时间，10位时间戳
 * @property \Illuminate\Support\Carbon $updated_at 更新时间，10位时间戳
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间，10位时间戳
 * @property-read \App\Models\Admin|null $admin
 * @property-read \App\Models\UserInfo|null $agent
 * @method static \Illuminate\Database\Eloquent\Builder|AdminAgentBinding newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AdminAgentBinding newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AdminAgentBinding onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|AdminAgentBinding query()
 * @method static \Illuminate\Database\Eloquent\Builder|AdminAgentBinding whereAdminId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AdminAgentBinding whereAgentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AdminAgentBinding whereBindingType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AdminAgentBinding whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AdminAgentBinding whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AdminAgentBinding whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AdminAgentBinding whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AdminAgentBinding whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AdminAgentBinding withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|AdminAgentBinding withoutTrashed()
 */
	class AdminAgentBinding extends \Eloquent {}
}

namespace App\Models{
/**
 * 管理员登录日志模型。
 * 
 * 功能逻辑说明：
 * - admin_login_logs 表记录后台管理员登录审计信息。
 * - 每次后台登录成功后写入管理员 ID、登录 IP、IP 归属地和客户端标识。
 * - 该模型只负责日志数据映射，不参与登录鉴权和权限判断。
 *
 * @property int $id 主键标识
 * @property int $admin_id 管理员标识
 * @property string $login_ip 登录IP
 * @property string|null $ip_address IP地理位置
 * @property string|null $user_agent 用户代理
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @property-read \App\Models\Admin|null $admin
 * @method static \Illuminate\Database\Eloquent\Builder|AdminLoginLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AdminLoginLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AdminLoginLog onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|AdminLoginLog query()
 * @method static \Illuminate\Database\Eloquent\Builder|AdminLoginLog whereAdminId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AdminLoginLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AdminLoginLog whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AdminLoginLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AdminLoginLog whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AdminLoginLog whereLoginIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AdminLoginLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AdminLoginLog whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AdminLoginLog withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|AdminLoginLog withoutTrashed()
 */
	class AdminLoginLog extends \Eloquent {}
}

namespace App\Models{
/**
 * 管理员角色兼容模型。
 * 
 * 功能逻辑说明：
 * - 底层数据表仍为 roles，保留该模型是为了兼容旧代码中的 AdminRole 调用。
 * - 新权限链路优先使用 Role 模型和 role_permissions 中间表。
 * - 本模型只声明角色基础字段，不单独维护第二套权限来源。
 *
 * @property int $id 主键标识
 * @property string $name 角色名称
 * @property string $guard_type 守卫类型: admin or front
 * @property string|null $description 描述
 * @property array|null $permissions 权限 slugs 数组
 * @property int $status 状态: 1=启用 0=禁用
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @method static \Illuminate\Database\Eloquent\Builder|AdminRole newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AdminRole newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AdminRole onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|AdminRole query()
 * @method static \Illuminate\Database\Eloquent\Builder|AdminRole whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AdminRole whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AdminRole whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AdminRole whereGuardType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AdminRole whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AdminRole whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AdminRole wherePermissions($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AdminRole whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AdminRole whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AdminRole withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|AdminRole withoutTrashed()
 */
	class AdminRole extends \Eloquent {}
}

namespace App\Models{
/**
 * 代理后代关系模型。
 * 
 * 功能逻辑说明：
 * - agent_descendants 表保存代理与下级代理或客户之间的层级闭包关系，是后台数据范围、前台代理客户列表和返佣统计的基础表。
 * - agent_id 表示上级代理业务用户 ID，对应 user_infos.user_id。
 * - descendant_id 表示下级业务用户 ID，可以是下级代理，也可以是普通客户。
 * - descendant_type 表示后代类型：1=代理，2=普通客户。
 * - is_direct 表示是否直属关系：1=直属，0=非直属。
 * - depth 表示上级代理到后代节点的层级距离，直属通常为 1。
 *
 * @property int $id 主键标识
 * @property int $agent_id 代理标识
 * @property int $descendant_id 下级标识
 * @property int $descendant_type 下级类型: 1=代理 2=客户
 * @property int $is_direct 是否直属
 * @property int $depth 深度
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @property-read \App\Models\UserInfo|null $agent
 * @property-read \App\Models\UserInfo|null $descendant
 * @method static \Illuminate\Database\Eloquent\Builder|AgentDescendant allAgents($agentId)
 * @method static \Illuminate\Database\Eloquent\Builder|AgentDescendant allCustomers($agentId)
 * @method static \Illuminate\Database\Eloquent\Builder|AgentDescendant directAgents($agentId)
 * @method static \Illuminate\Database\Eloquent\Builder|AgentDescendant directCustomers($agentId)
 * @method static \Illuminate\Database\Eloquent\Builder|AgentDescendant newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AgentDescendant newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AgentDescendant onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|AgentDescendant query()
 * @method static \Illuminate\Database\Eloquent\Builder|AgentDescendant whereAgentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AgentDescendant whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AgentDescendant whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AgentDescendant whereDepth($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AgentDescendant whereDescendantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AgentDescendant whereDescendantType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AgentDescendant whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AgentDescendant whereIsDirect($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AgentDescendant whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AgentDescendant withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|AgentDescendant withoutTrashed()
 */
	class AgentDescendant extends \Eloquent {}
}

namespace App\Models{
/**
 * 代理等级模型。
 * 
 * 功能逻辑说明：
 * - agent_levels 表保存代理等级与返佣比例配置，前台代理资料、后台代理等级管理和返佣计算都会读取该表。
 * - level_code 表示代理等级编码，迁移旧项目等级时用于保持稳定映射。
 * - name 表示代理等级展示名称。
 * - max_commission 表示代理最大返佣比例，min_commission 表示代理最小返佣比例。
 * - user_commission 表示普通客户默认返佣比例，用于代理给客户开户或调级时的默认值。
 *
 * @property int $id 主键标识
 * @property int $level_code 级别代码
 * @property string $name 名称
 * @property int $max_commission 最大佣金
 * @property int $min_commission 最小佣金
 * @property int $user_commission 用户佣金
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @method static \Illuminate\Database\Eloquent\Builder|AgentLevel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AgentLevel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AgentLevel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|AgentLevel query()
 * @method static \Illuminate\Database\Eloquent\Builder|AgentLevel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AgentLevel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AgentLevel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AgentLevel whereLevelCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AgentLevel whereMaxCommission($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AgentLevel whereMinCommission($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AgentLevel whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AgentLevel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AgentLevel whereUserCommission($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AgentLevel withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|AgentLevel withoutTrashed()
 */
	class AgentLevel extends \Eloquent {}
}

namespace App\Models{
/**
 * 代理节点统计模型。
 * 
 * 功能逻辑说明：
 * - agent_node_stats 表用于保存代理节点统计快照，通常包括直属代理、非直属代理、直属客户和非直属客户数量。
 * - 当前数据库未建表时不得在业务查询中直接依赖该模型，应继续以 agent_descendants 实时关系表为准。
 * - agent_id 表示被统计的代理业务用户 ID，对应 user_infos.user_id。
 * - direct_agent_count 表示直属下级代理数量，indirect_agent_count 表示非直属下级代理数量。
 * - direct_customer_count 表示直属客户数量，indirect_customer_count 表示非直属客户数量。
 * - last_calculated_at 表示统计最后计算时间，用于判断缓存统计是否需要刷新。
 *
 * @method static \Illuminate\Database\Eloquent\Builder|AgentNodeStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AgentNodeStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AgentNodeStats onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|AgentNodeStats query()
 * @method static \Illuminate\Database\Eloquent\Builder|AgentNodeStats withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|AgentNodeStats withoutTrashed()
 */
	class AgentNodeStats extends \Eloquent {}
}

namespace App\Models{
/**
 * 基础模型类。
 * 
 * 功能逻辑说明：
 * - 所有继承该类的业务模型共享软删除、主键、批量赋值和时间格式约定。
 * - SoftDeletes 表示所有继承模型默认支持软删除，删除时写入 deleted_at 而不是物理删除记录。
 * - $guarded 表示批量赋值黑名单；当前为空数组，表示字段写入边界由控制器、服务层或单独模型的 $fillable 继续约束。
 * - $hidden 表示序列化时隐藏字段；默认隐藏 deleted_at，避免接口响应暴露软删除时间。
 * - $dateFormat 表示 Eloquent 日期字段保存为 Unix 时间戳，兼容当前迁移中 created_at、updated_at、deleted_at 的整数时间戳设计。
 *
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel withoutTrashed()
 */
	class BaseModel extends \Eloquent {}
}

namespace App\Models{
/**
 * 大代理模型。
 * 
 * 功能逻辑说明：
 * - big_agents 表保存大代理登录账号、邮箱、加密密码、启用状态和可管理下级代理范围。
 * - username 表示大代理登录名，前台大代理入口会按该字段查找账号。
 * - sub_agent_ids 表示可管理下级代理 ID 集合，历史数据通常以逗号分隔字符串保存。
 * - sub_agent_ids 表示大代理可管理的下级代理 ID 集合。
 * - is_enabled 表示大代理账号是否启用，禁用账号不应允许继续登录或查看下级数据。
 * - loginLogs 表示大代理登录日志关联。
 * - jwt_token_id 表示当前登录令牌标识，用于让服务端识别最新有效会话。
 *
 * @property int $id 主键标识
 * @property string $email 邮箱
 * @property string $username 用户名
 * @property string $password 密码
 * @property string $sub_agent_ids 下级代理标识
 * @property int $is_enabled 是否启用
 * @property string|null $jwt_token_id JWT 令牌标识（SSO 会话绑定）
 * @property string $created_by 创建人
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BigAgentLoginLog> $loginLogs
 * @property-read int|null $login_logs_count
 * @method static \Illuminate\Database\Eloquent\Builder|BigAgent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BigAgent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BigAgent onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|BigAgent query()
 * @method static \Illuminate\Database\Eloquent\Builder|BigAgent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BigAgent whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BigAgent whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BigAgent whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BigAgent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BigAgent whereIsEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BigAgent whereJwtTokenId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BigAgent wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BigAgent whereSubAgentIds($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BigAgent whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BigAgent whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BigAgent withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|BigAgent withoutTrashed()
 */
	class BigAgent extends \Eloquent {}
}

namespace App\Models{
/**
 * 大代理登录日志模型。
 * 
 * 功能逻辑说明：
 * - big_agent_login_logs 表保存大代理账号登录审计记录，用于后台查看大代理账号登录历史。
 * - big_agent_id 表示登录的大代理账号 ID，对应 big_agents.id。
 * - login_ip 表示登录来源 IP，用于安全审计和异常登录排查。
 * - login_at 表示登录发生时间。
 *
 * @property int $id 主键标识
 * @property int $big_agent_id 大代理标识
 * @property string $login_ip 登录IP
 * @property string $login_at 登录时间
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @property-read \App\Models\BigAgent|null $bigAgent
 * @method static \Illuminate\Database\Eloquent\Builder|BigAgentLoginLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BigAgentLoginLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BigAgentLoginLog onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|BigAgentLoginLog query()
 * @method static \Illuminate\Database\Eloquent\Builder|BigAgentLoginLog whereBigAgentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BigAgentLoginLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BigAgentLoginLog whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BigAgentLoginLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BigAgentLoginLog whereLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BigAgentLoginLog whereLoginIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BigAgentLoginLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BigAgentLoginLog withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|BigAgentLoginLog withoutTrashed()
 */
	class BigAgentLoginLog extends \Eloquent {}
}

namespace App\Models{
/**
 * 黑名单模型。
 * 
 * 功能逻辑说明：
 * - blacklists 表保存被限制注册或操作的用户身份信息，用于后台风控和注册前置校验。
 * - name 表示被限制对象姓名或备注名称。
 * - id_card 表示被限制的身份证号码，用于实名信息命中黑名单。
 * - email 表示被限制的邮箱，用于注册、登录或资料变更风控。
 * - phone 表示被限制的手机号，用于注册、联系信息或安全校验风控。
 *
 * @property int $id 主键标识
 * @property string $name 姓名
 * @property string $id_card 身份证号
 * @property string $email 邮箱
 * @property string $phone 电话
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @method static \Illuminate\Database\Eloquent\Builder|Blacklist newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Blacklist newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Blacklist onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Blacklist query()
 * @method static \Illuminate\Database\Eloquent\Builder|Blacklist whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Blacklist whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Blacklist whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Blacklist whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Blacklist whereIdCard($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Blacklist whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Blacklist wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Blacklist whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Blacklist withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Blacklist withoutTrashed()
 */
	class Blacklist extends \Eloquent {}
}

namespace App\Models{
/**
 * 注销申请模型。
 * 
 * 功能逻辑说明：
 * - cancel_applies 表保存前台用户提交的账号注销申请，用于后台审核是否允许注销业务账号。
 * - user_id 表示申请注销的业务用户 ID，对应 user_infos.user_id。
 * - user_name 表示提交申请时的用户名称快照，便于后台列表直接展示。
 * - status 表示注销申请处理状态，后台审核通过或拒绝时会更新该字段。
 * - cancel_remark 表示用户提交的注销原因，reject_reason 表示后台拒绝注销的原因。
 * - created_by 和 updated_by 表示创建、更新该申请的后台管理员 ID，用于审计追踪。
 *
 * @property int $id 主键标识
 * @property int $user_id 用户标识
 * @property string $user_name 用户名
 * @property int $status 状态: 0=待处理 1=通过 -1=拒绝
 * @property string $cancel_remark 用户提交的销户原因
 * @property string $reject_reason 拒绝原因
 * @property string $created_by 创建人
 * @property string $updated_by 更新人
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @property-read \App\Models\UserInfo|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|CancelApply newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CancelApply newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CancelApply onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|CancelApply query()
 * @method static \Illuminate\Database\Eloquent\Builder|CancelApply whereCancelRemark($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CancelApply whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CancelApply whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CancelApply whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CancelApply whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CancelApply whereRejectReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CancelApply whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CancelApply whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CancelApply whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CancelApply whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CancelApply whereUserName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CancelApply withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|CancelApply withoutTrashed()
 */
	class CancelApply extends \Eloquent {}
}

namespace App\Models{
/**
 * 旧 comm_summary 单代理返佣出账模型。
 * 
 * 文件功能：
 * - 记录一笔源交易向一名上级代理发放返佣的完整状态。
 * - source_trade_id + agent_id 是幂等业务键，防止重复访问旧路由导致重复 MT4 入金。
 * - status 区分可安全重试的未发送、不可重发的未知结果和已结算结果。
 * - calculation_type、spread、volume_multiplier 固化手续费或点差返佣的计算依据。
 * 
 * 状态含义：
 * - pending：已写入本地意图，尚未向 MT4 发送。
 * - processing：已被一个进程声明，不能由并发请求再次发送。
 * - settled：MT4 已确认入账并已写入本地返佣账本。
 * - retryable：MT4 明确未接收命令，可以在 available_at 后重试。
 * - rejected：MT4 明确拒绝，本地保留失败证据，等待业务处理。
 * - unknown：命令可能已发送或本地提交失败，禁止自动重发，等待人工核对。
 * - not_payable：线下结算或非正差额，不需要调用 MT4。
 *
 * @property int $id 返佣出账主键
 * @property int $source_trade_id 源 user_trades 主键
 * @property int $source_ticket 旧 MT4 源交易单号
 * @property int $trader_user_id 产生交易的客户业务用户标识
 * @property int $agent_id 获得返佣的代理业务用户标识
 * @property int $parent_id 收款代理的上级代理业务用户标识
 * @property int $volume MT4 原始成交量，200 表示 2 手
 * @property string $rate_difference 当前代理与直属下级的返佣比例差
 * @property string $group_radix 收款代理组的旧返佣基数
 * @property string $amount 本次应入账返佣金额
 * @property string $comment 写入 MT4 的旧 DBCN 备注
 * @property string $calculation_type 计算类型：legacy_comm_summary=旧佣金汇总 / legacy_spread_comm_summary=旧点差返佣汇总
 * @property string $spread 旧点差返佣使用的整数点差快照
 * @property string $volume_multiplier 旧特殊点差品种使用的手数倍率快照
 * @property string $status 状态：pending=待处理 / processing=处理中 / settled=已结算 / retryable=可重试 / rejected=已拒绝 / unknown=未知 / not_payable=不可支付
 * @property int $attempts 实际向 MT4 发起入金的次数
 * @property \Illuminate\Support\Carbon|null $available_at 下次允许重试的 Unix 时间戳
 * @property \Illuminate\Support\Carbon|null $locked_at 当前处理声明的 Unix 时间戳
 * @property \Illuminate\Support\Carbon|null $processed_at 终态处理完成的 Unix 时间戳
 * @property string|null $provider_reference MT4 返回的入金票据号
 * @property string|null $last_error_code 最近一次 MT4 或本地失败代码
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间 Unix 时间戳
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间 Unix 时间戳
 * @property \Illuminate\Support\Carbon|null $deleted_at 软删除时间 Unix 时间戳
 * @property-read \App\Models\UserInfo|null $agent
 * @property-read \App\Models\UserTrade|null $sourceTrade
 * @property-read \App\Models\UserInfo|null $trader
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRebatePayout newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRebatePayout newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRebatePayout onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRebatePayout query()
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRebatePayout whereAgentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRebatePayout whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRebatePayout whereAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRebatePayout whereAvailableAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRebatePayout whereCalculationType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRebatePayout whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRebatePayout whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRebatePayout whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRebatePayout whereGroupRadix($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRebatePayout whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRebatePayout whereLastErrorCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRebatePayout whereLockedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRebatePayout whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRebatePayout whereProcessedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRebatePayout whereProviderReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRebatePayout whereRateDifference($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRebatePayout whereSourceTicket($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRebatePayout whereSourceTradeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRebatePayout whereSpread($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRebatePayout whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRebatePayout whereTraderUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRebatePayout whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRebatePayout whereVolume($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRebatePayout whereVolumeMultiplier($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRebatePayout withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRebatePayout withoutTrashed()
 */
	class CommissionRebatePayout extends \Eloquent {}
}

namespace App\Models{
/**
 * 佣金记录模型。
 * 
 * 功能逻辑说明：
 * - commission_records 表保存代理返佣结算和人工调整记录，是实时返佣、结算审核和后台返佣报表的数据来源。
 * - unique_id 表示返佣记录唯一业务编号，用于避免重复结算或重复导入。
 * - agent_id 表示获得返佣的代理业务用户 ID，对应 user_infos.user_id。
 * - parent_id 表示该代理的父级代理业务用户 ID，用于按代理链路汇总返佣。
 * - agent_profit 和 agent_volume 表示代理维度的盈利和交易手数统计。
 * - equity_value 和 equity_diff 表示权益值及权益差额，用于结算校验。
 * - settle_cycle 表示结算周期，date_range 表示本次统计覆盖的时间范围。
 * - mt4_order_id 表示关联的 MT4 订单号或资金系统订单号。
 * - settle_status 表示返佣结算状态，后台结算按钮和报表筛选应以该字段为准。
 * - fee、swap、deposit 表示手续费、库存费和入金相关金额。
 * - commission_amount 表示本次应返佣金额，returned_amount 表示已返还金额，real_amount 表示最终实际金额。
 * - data_type 表示记录来源类型，manual_reason 表示人工调整原因，remarks 表示补充备注。
 * - created_by 和 updated_by 表示创建、更新该返佣记录的后台管理员 ID，用于审计追踪。
 *
 * @property int $id 主键标识
 * @property string $unique_id MD5唯一标识
 * @property int $agent_id 代理标识
 * @property int $parent_id 父代理标识
 * @property float $agent_profit 代理利润
 * @property float $agent_volume 代理交易量
 * @property int $equity_value 净值
 * @property int $equity_diff 净值差
 * @property int $settle_cycle 结算周期
 * @property int $mt4_order_id MT4订单标识
 * @property string $date_range 日期范围
 * @property int $settle_status 结算状态: 1=待结算 2=已结算
 * @property float $fee 手续费
 * @property float $swap 隔夜利息
 * @property float $commission_amount 佣金金额
 * @property float $returned_amount 返还金额
 * @property float $deposit 入金
 * @property float $real_amount 实际金额
 * @property string $data_type 返佣或结算数据来源类型
 * @property string $manual_reason 手动调整原因
 * @property string $remarks 备注
 * @property string $created_by 创建人
 * @property string $updated_by 更新人
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @property-read \App\Models\UserInfo|null $agent
 * @property-read \App\Models\UserInfo|null $parent
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord query()
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord whereAgentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord whereAgentProfit($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord whereAgentVolume($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord whereCommissionAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord whereDataType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord whereDateRange($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord whereDeposit($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord whereEquityDiff($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord whereEquityValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord whereFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord whereManualReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord whereMt4OrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord whereRealAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord whereRemarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord whereReturnedAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord whereSettleCycle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord whereSettleStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord whereSwap($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord whereUniqueId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionRecord withoutTrashed()
 */
	class CommissionRecord extends \Eloquent {}
}

namespace App\Models{
/**
 * 返佣转账记录模型。
 * 
 * 文件功能：
 * - 映射 commission_transfers 表，存储代理之间的返佣转账请求。
 * - 支持 Saga 模式的状态流转（pending→processing→completed/failed）。
 * 
 * 适用场景：
 * - 上级代理给下级客户或下级代理转返佣。
 * - 后台管理员对返佣转账进行对账（reconciliation）。
 * 
 * 主要字段：
 * - source_user_id：转出方用户ID（上级代理）。
 * - target_user_id：接收方用户ID（下级代理或客户）。
 * - amount：转账金额。
 * - status：转账状态（pending/processing/completed/failed/compensated）。
 * - idempotency_key：幂等键，防止重复提交。
 * - reconcile_decision：后台对账决策（confirmed_completed/confirmed_compensated/confirmed_rejected）。
 * 
 * 关联关系：
 * - source()：转出方 UserInfo。
 * - target()：接收方 UserInfo。
 * - outbox()：对应的出队记录。
 *
 * @property int $id 主键标识。
 * @property string $local_order_no 本地订单号（幂等与对账唯一键，格式如 CMT 前缀 + 日期 + 序号）。
 * @property int $source_user_id 转出方用户标识（发起转账的代理商）。
 * @property int $target_user_id 转入方用户标识（直属下级代理或直属客户）。
 * @property string $request_purpose 转账用途：verify=认证校验 / withdraw=出金 / deposit=入金 / compensation=补偿，决定 Saga 执行分支。
 * @property string $idempotency_key 幂等键：同一笔转账重复提交时去重，防止重复扣款。
 * @property string $payload_hash 请求负载 SHA-256 摘要：防篡改与重复投递校验。
 * @property string|null $payload_ciphertext 加密负载：包含密码等敏感字段的密文，避免明文落库。
 * @property string $amount 转账金额（十进制 18 位整数部分 2 位小数）。
 * @property string|null $remark 转账备注（用户填写或系统生成）。
 * @property string $status Saga 状态：pending=待处理 / processing=处理中 / succeeded=成功 / failed=失败 / manual_reconcile_required=需人工对账。
 * @property string $current_step 当前执行步骤：verify=校验 / withdraw=出金 / deposit=入金 / compensation=补偿。
 * @property string|null $manual_origin_step 人工介入前记录的执行步骤：对账完成后用于恢复或判定补偿路径。
 * @property string $reservation_status 资金预留状态：pending=未预留 / reserved=已预留 / released=已释放，防并发扣款。
 * @property string|null $small_limit_day 小额限制日：当日小额转账累计校验日期。
 * @property string|null $small_limit_key 小额限制键：按（用户+日期）维度累计小额转账金额。
 * @property string|null $withdraw_ticket MT4 出金单据号：Saga 出金分支的 MT4 侧凭证。
 * @property string|null $deposit_ticket MT4 入金单据号：Saga 入金分支的 MT4 侧凭证。
 * @property string|null $compensation_ticket 补偿单据号：异常补偿分支的 MT4 侧凭证。
 * @property string|null $source_balance_after 转出方操作后余额快照：对账用。
 * @property string|null $target_balance_after 转入方操作后余额快照：对账用。
 * @property int $attempts Saga 重试次数：每次调度递增，超过阈值转人工对账。
 * @property \Illuminate\Support\Carbon|null $available_at 可处理时间（10 位时间戳）：重试退避后到达该时间才可再次调度。
 * @property \Illuminate\Support\Carbon|null $locked_at 锁定时间：调度器领取任务时写入，防并发处理。
 * @property \Illuminate\Support\Carbon|null $processed_at 处理完成时间：Saga 终态（成功/转人工）落库时间。
 * @property string|null $provider_reference 外部网关凭证引用：MT4 返回的交易引用号。
 * @property string|null $last_error_code 最近一次错误码：失败原因分类（网关/校验/超时）。
 * @property string|null $last_error_message 最近一次错误信息：人工排查详情。
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间（10 位时间戳）。
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间（10 位时间戳）。
 * @property \Illuminate\Support\Carbon|null $deleted_at 软删除时间（10 位时间戳）。
 * @property string|null $reconcile_decision 人工对账结论：confirmed=已确认 / refunded=已退款 / compensated=已补偿。
 * @property string|null $reconcile_external_reference 对账外部凭证：MT4 侧最终交易号或对账单引用。
 * @property string|null $reconcile_evidence 对账证据：对账时的余额/流水快照（JSON 文本）。
 * @property int|null $reconciled_by 对账人工号：后台管理员标识。
 * @property \Illuminate\Support\Carbon|null $reconciled_at 对账完成时间（10 位时间戳）。
 * @property-read \App\Models\CommissionTransferOutbox|null $outbox
 * @property-read \App\Models\UserInfo|null $source
 * @property-read \App\Models\UserInfo|null $target
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer query()
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereAvailableAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereCompensationTicket($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereCurrentStep($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereDepositTicket($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereIdempotencyKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereLastErrorCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereLastErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereLocalOrderNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereLockedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereManualOriginStep($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer wherePayloadCiphertext($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer wherePayloadHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereProcessedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereProviderReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereReconcileDecision($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereReconcileEvidence($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereReconcileExternalReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereReconciledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereReconciledBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereRemark($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereRequestPurpose($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereReservationStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereSmallLimitDay($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereSmallLimitKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereSourceBalanceAfter($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereSourceUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereTargetBalanceAfter($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereTargetUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer whereWithdrawTicket($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransfer withoutTrashed()
 */
	class CommissionTransfer extends \Eloquent {}
}

namespace App\Models{
/**
 * 返佣转账出队记录模型。
 * 
 * 文件功能：
 * - 映射 commission_transfer_outbox 表，记录返佣转账的 MT4 出队事件。
 * - 支持异步处理与重试机制（attempts 字段 + available_at 延迟重试）。
 * 
 * 适用场景：
 * - CommissionTransferSagaService 执行转账步骤时创建出队记录。
 * - DispatchPendingCommissionTransfers 定时任务消费出队记录。
 * 
 * 主要字段：
 * - commission_transfer_id：关联的返佣转账记录 ID。
 * - event_type：事件类型（deposit/withdraw/compensation）。
 * - status：出队状态（pending/processing/completed/failed）。
 * - attempts：已重试次数。
 * - available_at：下次可执行时间（延迟重试控制）。
 * 
 * 关联关系：
 * - transfer()：关联的 CommissionTransfer 记录。
 *
 * @property int $id 主键标识。
 * @property int $commission_transfer_id 关联佣金转账 Saga 主表标识。
 * @property string $event_type 事件类型：withdraw=出金 / deposit=入金 / compensation=补偿。
 * @property string $status 投递状态：pending=待投递 / processing=处理中 / succeeded=成功 / failed=失败。
 * @property int $attempts 投递重试次数。
 * @property string $payload_hash 事件负载 SHA-256：防重复投递与篡改。
 * @property \Illuminate\Support\Carbon|null $available_at 可投递时间（10 位时间戳）：退避后重试。
 * @property \Illuminate\Support\Carbon|null $locked_at 锁定时间：防并发投递。
 * @property \Illuminate\Support\Carbon|null $processed_at 投递完成时间。
 * @property string|null $provider_reference 外部网关凭证引用。
 * @property string|null $last_error_code 最近错误码。
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间（10 位时间戳）。
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间（10 位时间戳）。
 * @property \Illuminate\Support\Carbon|null $deleted_at 软删除时间（10 位时间戳）。
 * @property-read \App\Models\CommissionTransfer|null $transfer
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransferOutbox newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransferOutbox newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransferOutbox onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransferOutbox query()
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransferOutbox whereAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransferOutbox whereAvailableAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransferOutbox whereCommissionTransferId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransferOutbox whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransferOutbox whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransferOutbox whereEventType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransferOutbox whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransferOutbox whereLastErrorCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransferOutbox whereLockedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransferOutbox wherePayloadHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransferOutbox whereProcessedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransferOutbox whereProviderReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransferOutbox whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransferOutbox whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransferOutbox withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|CommissionTransferOutbox withoutTrashed()
 */
	class CommissionTransferOutbox extends \Eloquent {}
}

namespace App\Models{
/**
 * 批量信用导入模型。
 * 
 * 功能逻辑说明：
 * - credit_imports 表保存后台批量信用额度导入记录。
 * - user_id 和 user_name 表示本次信用调整对应的业务用户。
 * - credit_type 表示信用调整类型，当前后台约定 1=临时信用、2=永久信用、3=奖励信用、4=其他信用。
 * - amount 表示信用调整金额，batch_no 表示批次号，用于把同一次导入的多条记录归组。
 * - is_synced 表示是否已同步到 MT4，fail_reason 表示同步失败原因，便于失败重试和后台排查。
 *
 * @property int $id 主键标识
 * @property int $user_id 用户标识
 * @property string $user_name 用户名
 * @property int $credit_type 信用类型: 1=临时 2=永久 3=奖励 4=其他
 * @property int $mt4_order_id MT4订单标识
 * @property string $amount 金额
 * @property string $batch_no 批次号
 * @property int $is_synced 是否同步
 * @property string $fail_reason 失败原因
 * @property string $remarks 备注
 * @property int $created_by 创建人
 * @property int $updated_by 更新人
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @property-read \App\Models\UserInfo|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|CreditImport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CreditImport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CreditImport onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|CreditImport query()
 * @method static \Illuminate\Database\Eloquent\Builder|CreditImport whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditImport whereBatchNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditImport whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditImport whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditImport whereCreditType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditImport whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditImport whereFailReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditImport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditImport whereIsSynced($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditImport whereMt4OrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditImport whereRemarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditImport whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditImport whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditImport whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditImport whereUserName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CreditImport withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|CreditImport withoutTrashed()
 */
	class CreditImport extends \Eloquent {}
}

namespace App\Models{
/**
 * 数据操作日志模型。
 * 
 * 功能逻辑说明：
 * - data_operation_logs 表保存模型数据变更前后的审计快照，用于追踪后台敏感数据修改过程。
 * - model_type 表示被修改的数据模型类型，例如 UserInfo、DepositRecord 或 WithdrawRecord。
 * - model_id 表示被修改数据的主键 ID。
 * - before_data 表示变更前数据快照，after_data 表示变更后数据快照，均按数组结构读取。
 * - operator_id 表示执行变更的后台管理员 ID，对应 admins.id。
 *
 * @property int $id 主键标识
 * @property string $model_type 模型类型
 * @property int $model_id 模型标识
 * @property array|null $before_data 修改前数据
 * @property array|null $after_data 修改后数据
 * @property int $operator_id 操作人标识
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @property-read \App\Models\Admin|null $operator
 * @method static \Illuminate\Database\Eloquent\Builder|DataOperationLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DataOperationLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DataOperationLog onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|DataOperationLog query()
 * @method static \Illuminate\Database\Eloquent\Builder|DataOperationLog whereAfterData($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DataOperationLog whereBeforeData($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DataOperationLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DataOperationLog whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DataOperationLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DataOperationLog whereModelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DataOperationLog whereModelType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DataOperationLog whereOperatorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DataOperationLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DataOperationLog withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|DataOperationLog withoutTrashed()
 */
	class DataOperationLog extends \Eloquent {}
}

namespace App\Models{
/**
 * 数据权限范围配置模型。
 * 
 * 功能逻辑说明：
 * - 该模型用于存储角色的数据权限配置，决定不同角色在查询数据时能看到哪些数据范围。
 * - 通过 role_id + resource_name 唯一确定一条配置，每个角色对每个资源只能有一条数据权限配置。
 * - scope_type 定义数据权限类型：1=全部 2=本级及下级 3=仅本级 4=仅本人 5=自定义。
 * - scope_rule 存储自定义规则的JSON配置，当 scope_type=5 时启用。
 * 
 * 表结构：data_scopes
 * - id: 主键ID
 * - role_id: 角色ID，关联 roles.id
 * - resource_name: 资源名称，例如 user、agent、order 等
 * - scope_type: 数据权限类型
 * - scope_rule: 自定义规则JSON
 * - created_at: 创建时间
 * - updated_at: 更新时间
 * 
 * 使用示例：
 * ```php
 * // 配置角色2对用户资源的数据权限为"本级及下级"
 * DataScope::updateOrCreate(
 *     ['role_id' => 2, 'resource_name' => 'user'],
 *     ['scope_type' => 2]
 * );
 * 
 * // 查询角色的数据权限配置
 * $dataScope = DataScope::where('role_id', 2)
 *     ->where('resource_name', 'user')
 *     ->first();
 * ```
 *
 * @property-read \App\Models\Role|null $role
 * @method static \Illuminate\Database\Eloquent\Builder|DataScope newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DataScope newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DataScope onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|DataScope query()
 * @method static \Illuminate\Database\Eloquent\Builder|DataScope withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|DataScope withoutTrashed()
 */
	class DataScope extends \Eloquent {}
}

namespace App\Models{
/**
 * 批量入金导入模型。
 * 
 * 功能逻辑说明：
 * - deposit_imports 表保存后台批量入金导入记录，用于 Excel/CSV 导入后生成或同步用户入金数据。
 * - user_id 表示导入记录所属业务用户 ID，对应 user_infos.user_id，用于校验导入用户是否存在。
 * - user_name 表示导入文件中的用户展示名称，用于后台人工核对。
 * - amount 表示本条导入记录的入金金额，remarks 表示导入备注或人工补充说明。
 * - mt4_order_id 表示导入后关联的 MT4 订单号或外部资金系统订单号。
 * - batch_no 表示批次号，用于定位同一次 Excel/CSV 或手工批量导入的数据集合。
 * - is_synced 表示后续资金系统同步状态：0=待处理，1=成功，2=失败。
 * - fail_reason 表示同步失败原因，便于后台重试导入或人工修复数据。
 * - created_by 和 updated_by 表示创建、更新该导入记录的后台管理员 ID，用于审计追踪。
 *
 * @property int $id 主键标识
 * @property int $user_id 用户标识
 * @property string $user_name 用户名
 * @property string $amount 金额
 * @property string $remarks 备注
 * @property int $mt4_order_id MT4订单标识
 * @property string $batch_no 批次号
 * @property int $is_synced 是否同步: 0=待处理 1=成功 2=失败
 * @property string $fail_reason 失败原因
 * @property int $created_by 创建人
 * @property int $updated_by 更新人
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @property-read \App\Models\UserInfo|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|DepositImport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DepositImport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DepositImport onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|DepositImport query()
 * @method static \Illuminate\Database\Eloquent\Builder|DepositImport whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositImport whereBatchNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositImport whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositImport whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositImport whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositImport whereFailReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositImport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositImport whereIsSynced($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositImport whereMt4OrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositImport whereRemarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositImport whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositImport whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositImport whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositImport whereUserName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositImport withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|DepositImport withoutTrashed()
 */
	class DepositImport extends \Eloquent {}
}

namespace App\Models{
/**
 * 入金记录模型。
 * 
 * 功能逻辑说明：
 * - deposit_records 表保存前台用户入金申请和后台审核结果，是后台入金审核、资金流水展示和用户资金追踪的数据来源。
 * - user_id 表示入金所属业务用户 ID，对应 user_infos.user_id，用于按代理层级、管理员数据范围和用户维度过滤记录。
 * - user_name 表示入金用户展示名称，便于后台列表快速识别申请人。
 * - mt4_ticket 表示关联的 MT4 交易账号或资金系统票据号，用于和外部交易系统核对。
 * - amount 表示用户申请入金金额，actual_amount 表示实际到账金额，exchange_rate 表示入金折算汇率。
 * - channel_name 表示支付渠道名称，channel_order_no 表示渠道侧订单号，local_order_no 表示本地入金订单号。
 * - status 表示入金审核状态，后台列表和审核接口应按该字段判断待审、通过、拒绝等处理流转。
 * - payment_time 表示付款时间，remarks 表示后台或导入流程保留的补充说明。
 * - created_by 和 updated_by 表示创建、更新该记录的后台管理员 ID，用于审计追踪。
 *
 * @property int $id 主键标识
 * @property int $user_id 用户标识
 * @property string $user_name 用户名
 * @property int $mt4_ticket MT4订单号
 * @property int|null $refund_mt4_ticket 退款 MT4 凭证：结算失败退款后的 MT4 单据号。
 * @property string $amount 申请入金金额（用户提交）。
 * @property string $actual_amount 实际到账金额（支付成功后写入，默认 0.00）。
 * @property string|null $provider_amount 网关侧金额（第三方返回，用于对账）。
 * @property string $exchange_rate 汇率（法币/币种换算，默认 0.00000000）。
 * @property string $channel_name 渠道名称
 * @property string $channel_order_no 渠道订单号
 * @property string $local_order_no 本地订单号
 * @property string|null $idempotency_key 幂等键：防重复下单/重复回调入账。
 * @property string|null $gateway_code 支付网关代码（bank_transfer/usdt_trc20/quick_pay 等）。
 * @property string|null $merchant_id 商户号：网关侧商户标识。
 * @property string|null $currency 币种（CNY/USDT 等）。
 * @property string|null $payment_status 支付状态：pending=待支付 / paid=已支付 / expired=已过期 / failed=失败。
 * @property string|null $settlement_status 结算状态：pending=待结算 / settled=已入账 / refunded=已退款。
 * @property string|null $provider_payload_hash 网关回调负载 SHA-256：防回调伪造与重复入账。
 * @property array|null $provider_order_result 网关创建订单原始返回（JSON），供人工核账。
 * @property \Illuminate\Support\Carbon|null $provider_create_started_at 调用网关创建订单的开始时间。
 * @property int $provider_create_attempts 调用网关创建订单的重试次数。
 * @property string $status 状态: 01=待支付 02=已支付 05=退款 09=失败 10=超时
 * @property string|null $payment_time 支付时间
 * @property \Illuminate\Support\Carbon|null $refund_time 退款完成时间。
 * @property string $remarks 备注
 * @property string $created_by 创建人
 * @property string $updated_by 更新人
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @property-read \App\Models\UserInfo|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord query()
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord whereActualAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord whereChannelName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord whereChannelOrderNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord whereExchangeRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord whereGatewayCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord whereIdempotencyKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord whereLocalOrderNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord whereMerchantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord whereMt4Ticket($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord wherePaymentStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord wherePaymentTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord whereProviderAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord whereProviderCreateAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord whereProviderCreateStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord whereProviderOrderResult($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord whereProviderPayloadHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord whereRefundMt4Ticket($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord whereRefundTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord whereRemarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord whereSettlementStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord whereUserName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|DepositRecord withoutTrashed()
 */
	class DepositRecord extends \Eloquent {}
}

namespace App\Models{
/**
 * 礼品配置模型。
 * 
 * 功能逻辑说明：
 * - gift_items 表保存后台可配置的可兑换礼品目录，用于前台 available_gifts 展示。
 * - points_cost 表示兑换该礼品需要的积分，stock_quantity 表示当前可兑换库存。
 * - status=1 且 stock_quantity>0 的记录才会进入前台可兑换列表。
 *
 * @property int $id 主键标识
 * @property string $name 礼品名称
 * @property string $description 礼品说明
 * @property int $points_cost 兑换积分
 * @property int $stock_quantity 库存数量
 * @property int $status 状态：0=停用 1=启用
 * @property string $image_url 礼品图片
 * @property \Illuminate\Support\Carbon $created_at 创建时间
 * @property \Illuminate\Support\Carbon $updated_at 更新时间
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间
 * @method static \Illuminate\Database\Eloquent\Builder|GiftItem available()
 * @method static \Illuminate\Database\Eloquent\Builder|GiftItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GiftItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GiftItem onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|GiftItem query()
 * @method static \Illuminate\Database\Eloquent\Builder|GiftItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GiftItem whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GiftItem whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GiftItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GiftItem whereImageUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GiftItem whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GiftItem wherePointsCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GiftItem whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GiftItem whereStockQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GiftItem whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GiftItem withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|GiftItem withoutTrashed()
 */
	class GiftItem extends \Eloquent {}
}

namespace App\Models{
/**
 * 礼品发货模型。
 * 
 * 功能逻辑说明：
 * - gift_shipments 表保存礼品兑换后的发货和物流记录，用于后台礼品发货列表和前台礼品记录展示。
 * - user_id 表示领取礼品的业务用户 ID，对应 user_infos.user_id。
 * - address_id 表示使用的收货地址 ID，对应 user_addresses.id。
 * - recipient_name、recipient_phone、recipient_address 表示发货时快照的收件人姓名、电话和地址。
 * - sender_name 表示发货人或后台处理人名称，tracking_number 表示物流单号。
 * - gift_name 表示礼品名称，gift_quantity 表示礼品数量。
 * - status 表示发货处理状态，remark 表示后台发货备注。
 * - admin_id 表示处理发货的后台管理员 ID，shipped_at 表示发货时间。
 *
 * @property int $id 主键标识
 * @property int $user_id 用户标识
 * @property int $address_id 地址标识
 * @property string $recipient_name 收件人姓名
 * @property string $recipient_phone 收件人电话
 * @property string $recipient_address 收件人地址
 * @property string $sender_name 发件人姓名
 * @property string $tracking_number 快递单号
 * @property string $gift_name 礼品名称
 * @property int $gift_quantity 礼品数量
 * @property int $status 状态: 0=待处理 1=已发货 2=运输中 3=已送达 4=异常
 * @property string $remark 备注
 * @property int $admin_id 管理员标识
 * @property string|null $shipped_at 发货时间
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @property-read \App\Models\UserInfo|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|GiftShipment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GiftShipment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GiftShipment onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|GiftShipment query()
 * @method static \Illuminate\Database\Eloquent\Builder|GiftShipment whereAddressId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GiftShipment whereAdminId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GiftShipment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GiftShipment whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GiftShipment whereGiftName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GiftShipment whereGiftQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GiftShipment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GiftShipment whereRecipientAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GiftShipment whereRecipientName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GiftShipment whereRecipientPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GiftShipment whereRemark($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GiftShipment whereSenderName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GiftShipment whereShippedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GiftShipment whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GiftShipment whereTrackingNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GiftShipment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GiftShipment whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GiftShipment withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|GiftShipment withoutTrashed()
 */
	class GiftShipment extends \Eloquent {}
}

namespace App\Models{
/**
 * 组配置模型。
 * 
 * 功能逻辑说明：
 * - group_configs 表保存代理组和客户交易组配置，用于开户、调组、返佣规则和交易账户分组。
 * - legacy_group_id 表示旧库 group_config.id，只用于旧数据身份映射，不得与 pair_id 混用。
 * - pair_id 表示成对关联的组配置 ID，常用于代理组与客户组之间的默认配对。
 * - name 表示组名称，radix 表示组参数基数或旧项目组编码。
 * - category 表示组类型：1=代理组，2=客户组。
 * - has_commission 表示该组是否参与返佣。
 * - is_enabled 表示是否启用，is_ecn 表示是否 ECN 组，is_default 表示是否默认组。
 * - created_by 和 updated_by 表示创建、更新该组配置的后台管理员 ID，用于审计追踪。
 *
 * @property int $id 主键标识
 * @property int|null $legacy_group_id 旧 group_config.id；用于旧用户组身份映射
 * @property int|null $pair_id 交易对标识
 * @property string $name 名称
 * @property float $radix 基数
 * @property int $category 分类: 1=代理 2=用户
 * @property int $has_commission 是否有佣金
 * @property int $is_enabled 是否启用
 * @property int $is_ecn 是否ECN
 * @property int $is_default 是否默认
 * @property int $created_by 创建人
 * @property int $updated_by 更新人
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @property-read GroupConfig|null $pairedGroup
 * @method static \Illuminate\Database\Eloquent\Builder|GroupConfig agent()
 * @method static \Illuminate\Database\Eloquent\Builder|GroupConfig default()
 * @method static \Illuminate\Database\Eloquent\Builder|GroupConfig enabled()
 * @method static \Illuminate\Database\Eloquent\Builder|GroupConfig newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GroupConfig newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GroupConfig onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|GroupConfig query()
 * @method static \Illuminate\Database\Eloquent\Builder|GroupConfig user()
 * @method static \Illuminate\Database\Eloquent\Builder|GroupConfig whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GroupConfig whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GroupConfig whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GroupConfig whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GroupConfig whereHasCommission($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GroupConfig whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GroupConfig whereIsDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GroupConfig whereIsEcn($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GroupConfig whereIsEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GroupConfig whereLegacyGroupId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GroupConfig whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GroupConfig wherePairId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GroupConfig whereRadix($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GroupConfig whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GroupConfig whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GroupConfig withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|GroupConfig withoutTrashed()
 */
	class GroupConfig extends \Eloquent {}
}

namespace App\Models{
/**
 * ID 序列模型。
 * 
 * 功能逻辑说明：
 * - id_sequences 表保存业务用户编号生成状态，用于代理和客户注册时生成稳定的业务 user_id。
 * - type 表示序列类型，例如 agent=代理编号，customer=客户编号。
 * - current_value 表示当前已发放的最大编号。
 * - prefix 表示编号前缀，当前逻辑保留该字段但 nextId() 只返回数值编号。
 * - step 表示每次递增步长，默认通常为 1。
 *
 * @property int $id 主键标识
 * @property string $type 类型: agent or customer
 * @property int $current_value 当前值
 * @property string $prefix 前缀
 * @property int $step 步长
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @method static \Illuminate\Database\Eloquent\Builder|IdSequence newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|IdSequence newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|IdSequence onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|IdSequence query()
 * @method static \Illuminate\Database\Eloquent\Builder|IdSequence whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|IdSequence whereCurrentValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder|IdSequence whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|IdSequence whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|IdSequence wherePrefix($value)
 * @method static \Illuminate\Database\Eloquent\Builder|IdSequence whereStep($value)
 * @method static \Illuminate\Database\Eloquent\Builder|IdSequence whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|IdSequence whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|IdSequence withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|IdSequence withoutTrashed()
 */
	class IdSequence extends \Eloquent {}
}

namespace App\Models{
/**
 * 邮件设置模型。
 * 
 * 功能逻辑说明：
 * - mail_settings 表保存系统邮件发送配置，用于注册验证、通知消息、后台测试邮件等发送场景。
 * - driver 表示邮件发送驱动，例如 smtp。
 * - host 表示邮件服务器地址，port 表示邮件服务器端口。
 * - username 表示邮件服务登录账号，password 表示邮件服务授权密码。
 * - encryption 表示加密方式，例如 ssl、tls 或空值。
 * - from_address 表示默认发件邮箱，from_name 表示默认发件人名称。
 *
 * @property int $id 主键标识
 * @property string|null $driver 驱动
 * @property string|null $host 主机
 * @property string|null $port 端口
 * @property string|null $username 用户名
 * @property string|null $password 密码
 * @property string|null $encryption 加密方式
 * @property string|null $from_address 发件人地址
 * @property string|null $from_name 发件人名称
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @method static \Illuminate\Database\Eloquent\Builder|MailSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|MailSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|MailSetting onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|MailSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder|MailSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MailSetting whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MailSetting whereDriver($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MailSetting whereEncryption($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MailSetting whereFromAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MailSetting whereFromName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MailSetting whereHost($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MailSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MailSetting wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MailSetting wherePort($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MailSetting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MailSetting whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MailSetting withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|MailSetting withoutTrashed()
 */
	class MailSetting extends \Eloquent {}
}

namespace App\Models{
/**
 * 菜单模型。
 * 
 * 功能逻辑说明：
 * - menus 表保存前后台 Blade 页面可见的动态菜单配置，用于渲染后台 Layui/Naive 风格菜单和前台用户菜单入口。
 * - title 表示中文菜单标题，title_en 表示英文菜单标题，后端多语言渲染时通过当前 locale 选择展示文案。
 * - icon 表示菜单图标名称，path 表示 Blade 页面访问路径，component 表示兼容前端组件标识。
 * - parent_id 表示父级菜单 ID，0 表示顶级菜单；children() 会按 sort 递归加载子菜单。
 * - permission_id 表示绑定的 permissions.id，用于把菜单展示和数据库权限配置关联起来。
 * - guard_type 表示菜单所属端：admin=后台管理员菜单，front=前台代理商或客户菜单。
 * - type 表示菜单节点类型，is_visible 表示是否在界面显示，is_external 表示是否外链。
 * - sort 表示菜单排序值，status 表示启用状态，禁用菜单不应进入可见菜单树。
 *
 * @property int $id 主键标识。
 * @property string $title 菜单标题
 * @property string $title_en 菜单英文标题
 * @property string $icon 图标
 * @property string $path 前端路由路径
 * @property string $component 前端组件路径
 * @property int $parent_id 父级菜单标识, 0=顶级
 * @property int|null $permission_id 绑定权限标识
 * @property string $guard_type 守卫类型: admin/front
 * @property int $type 类型: 1=目录 2=菜单 3=按钮
 * @property int $is_visible 是否可见: 0=隐藏 1=显示
 * @property int $is_external 是否外链: 0=否 1=是
 * @property int $sort 排序值越小越前
 * @property int $status 状态: 0=禁用 1=启用
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间。
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间。
 * @property \Illuminate\Support\Carbon|null $deleted_at 软删除时间。
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Menu> $children
 * @property-read int|null $children_count
 * @property-read string $localized_title
 * @property-read Menu|null $parent
 * @property-read \App\Models\Permission|null $permission
 * @method static \Illuminate\Database\Eloquent\Builder|Menu active()
 * @method static \Illuminate\Database\Eloquent\Builder|Menu admin()
 * @method static \Illuminate\Database\Eloquent\Builder|Menu front()
 * @method static \Illuminate\Database\Eloquent\Builder|Menu newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Menu newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Menu onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Menu query()
 * @method static \Illuminate\Database\Eloquent\Builder|Menu root()
 * @method static \Illuminate\Database\Eloquent\Builder|Menu visible()
 * @method static \Illuminate\Database\Eloquent\Builder|Menu whereComponent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Menu whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Menu whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Menu whereGuardType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Menu whereIcon($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Menu whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Menu whereIsExternal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Menu whereIsVisible($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Menu whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Menu wherePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Menu wherePermissionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Menu whereSort($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Menu whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Menu whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Menu whereTitleEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Menu whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Menu whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Menu withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Menu withoutTrashed()
 */
	class Menu extends \Eloquent {}
}

namespace App\Models{
/**
 * MT4 交易记录模型。
 * 
 * 功能逻辑说明：
 * - mt4_trades 表保存从 MT4 同步的交易订单，用于交易列表、持仓、历史平仓和资金流水统计。
 * - ticket 表示 MT4 订单号，login 表示 MT4 登录账号，symbol 表示交易品种。
 * - cmd 表示 MT4 交易命令类型，其中 cmd=6 表示余额类交易，可用于识别入金、出金或调账流水。
 * - profit、commission、swaps 表示订单盈亏、手续费和库存费，是后台风控与权益统计的基础字段。
 * - open_time 和 close_time 表示开仓与平仓时间；未平仓订单通常由业务查询按时间或状态规则筛选。
 *
 * @property int $id 主键标识
 * @property int $ticket MT4订单号
 * @property int $login MT4账号
 * @property string $symbol 品种
 * @property int $cmd 类型: 0=Buy, 1=Sell
 * @property float $volume 成交量
 * @property string $open_price 开仓价
 * @property string|null $close_price 平仓价
 * @property string $commission 手续费
 * @property string $swaps 库存费
 * @property string $profit 盈利
 * @property int $open_time 开仓时间
 * @property int|null $close_time 平仓时间
 * @property string $comment MT4 订单备注（服务器侧注释）
 * @property int|null $modify_time MT4 修改时间
 * @property \Illuminate\Support\Carbon $created_at 创建时间
 * @property \Illuminate\Support\Carbon $updated_at 更新时间
 * @property-read \App\Models\UserInfo|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4Trade newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4Trade newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4Trade onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4Trade query()
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4Trade whereClosePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4Trade whereCloseTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4Trade whereCmd($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4Trade whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4Trade whereCommission($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4Trade whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4Trade whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4Trade whereLogin($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4Trade whereModifyTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4Trade whereOpenPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4Trade whereOpenTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4Trade whereProfit($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4Trade whereSwaps($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4Trade whereSymbol($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4Trade whereTicket($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4Trade whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4Trade whereVolume($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4Trade withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4Trade withoutTrashed()
 */
	class Mt4Trade extends \Eloquent {}
}

namespace App\Models{
/**
 * MT4 用户资金模型。
 * 
 * 功能逻辑说明：
 * - mt4_users 表保存从 MT4 同步的交易账号资金快照。
 * - login 表示 MT4 登录账号，通常通过 user_infos.mt4_code 映射到业务用户。
 * - balance/equity/margin/margin_free 分别表示余额、净值、已用保证金和可用保证金。
 * - leverage 表示 MT4 账号杠杆，用于后台风险、权益和交易账号信息展示。
 * - 后台数据权限不直接依赖 MT4 账号归属，而是先映射到业务用户后再应用管理员数据范围。
 *
 * @property int $id 主键标识
 * @property int $login MT4账号
 * @property string $name 姓名
 * @property string $group MT4分组
 * @property string $balance 余额
 * @property string $equity 净值
 * @property string $margin 保证金
 * @property string $margin_free 可用保证金
 * @property int $leverage 杠杆
 * @property \Illuminate\Support\Carbon $created_at 创建时间
 * @property \Illuminate\Support\Carbon $updated_at 更新时间
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4User onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4User query()
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4User whereBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4User whereEquity($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4User whereGroup($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4User whereLeverage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4User whereLogin($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4User whereMargin($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4User whereMarginFree($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4User withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Mt4User withoutTrashed()
 */
	class Mt4User extends \Eloquent {}
}

namespace App\Models{
/**
 * 新闻公告模型。
 * 
 * 功能逻辑说明：
 * - news 表保存后台发布的新闻公告内容，供后台公告管理和前台公告列表读取。
 * - title 表示公告标题，content 表示公告正文内容，image 表示公告封面图或配图地址。
 * - author_id 表示发布公告的后台管理员 ID，author_name 表示发布时记录的管理员名称快照。
 * - is_published 表示公告是否发布：1=已发布，0=草稿或未发布。
 *
 * @property int $id 主键标识
 * @property string $title 标题
 * @property string $content 内容
 * @property string|null $image 图片
 * @property int $author_id 作者标识
 * @property string $author_name 作者名称
 * @property int $is_published 是否发布: 0=草稿 1=已发布
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @method static \Illuminate\Database\Eloquent\Builder|News newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|News newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|News onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|News published()
 * @method static \Illuminate\Database\Eloquent\Builder|News query()
 * @method static \Illuminate\Database\Eloquent\Builder|News whereAuthorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|News whereAuthorName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|News whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|News whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|News whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|News whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|News whereImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|News whereIsPublished($value)
 * @method static \Illuminate\Database\Eloquent\Builder|News whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|News whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|News withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|News withoutTrashed()
 */
	class News extends \Eloquent {}
}

namespace App\Models{
/**
 * 操作日志模型。
 * 
 * 功能逻辑说明：
 * - operation_logs 表保存后台管理员业务操作审计记录，用于追踪用户管理、资金审核、订单处理等敏感操作。
 * - admin_id 表示执行操作的后台管理员 ID，对应 admins.id。
 * - admin_name 表示操作时记录的管理员名称快照，避免管理员改名后历史日志不可读。
 * - target_user_id 表示被操作的业务用户 ID，对应 user_infos.user_id，可为空。
 * - order_no 表示关联的业务订单号，例如入金、出金或交易订单编号。
 * - content 表示操作内容说明，ip 表示操作来源 IP。
 * - action_type 表示操作类型，用于后台审计页面筛选创建、更新、删除、审核等行为。
 *
 * @property int $id 主键标识
 * @property int $admin_id 管理员标识
 * @property string $admin_name 管理员名称
 * @property int|null $target_user_id 目标用户标识
 * @property string|null $order_no 订单号
 * @property string $content 操作内容
 * @property string $ip 操作者 IP 地址
 * @property int $action_type 行为类型: 0=普通 1=提现
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @property-read \App\Models\Admin|null $admin
 * @property-read \App\Models\UserInfo|null $targetUser
 * @method static \Illuminate\Database\Eloquent\Builder|OperationLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|OperationLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|OperationLog onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|OperationLog query()
 * @method static \Illuminate\Database\Eloquent\Builder|OperationLog whereActionType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OperationLog whereAdminId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OperationLog whereAdminName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OperationLog whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OperationLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OperationLog whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OperationLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OperationLog whereIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OperationLog whereOrderNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OperationLog whereTargetUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OperationLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|OperationLog withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|OperationLog withoutTrashed()
 */
	class OperationLog extends \Eloquent {}
}

namespace App\Models{
/**
 * 支付通道模型。
 * 
 * 功能逻辑说明：
 * - payment_channels 表保存后台可用支付通道配置，用于前台入金渠道展示和后台通道管理。
 * - name 表示通道显示名称，channel_code 表示支付通道唯一编码。
 * - exchange_rate 表示通道入金汇率，前台入金金额折算和后台通道列表会读取该字段。
 * - is_enabled 表示通道是否启用，sort 表示通道展示排序值。
 * - config 表示通道扩展配置，通常保存商户号、回调参数、限额等 JSON 配置。
 *
 * @property int $id 主键标识
 * @property string $name 名称
 * @property string $channel_code 渠道代码
 * @property float $exchange_rate 汇率
 * @property int $is_enabled 是否启用
 * @property int $sort 排序
 * @property array|null $config 配置
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentChannel enabled()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentChannel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentChannel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentChannel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentChannel query()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentChannel whereChannelCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentChannel whereConfig($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentChannel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentChannel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentChannel whereExchangeRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentChannel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentChannel whereIsEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentChannel whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentChannel whereSort($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentChannel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentChannel withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentChannel withoutTrashed()
 */
	class PaymentChannel extends \Eloquent {}
}

namespace App\Models{
/**
 * 支付结算出队记录模型。
 * 
 * 文件功能：
 * - 映射 payment_settlement_outbox 表，记录入金订单的 MT4 结算出队事件。
 * - 支持异步结算与重试机制。
 * 
 * 适用场景：
 * - 后台管理员审核通过入金后，DispatchPendingDepositSettlements 定时任务消费出队记录。
 * - 入金审核通过后向 MT4 写入信用/余额。
 * 
 * 主要字段：
 * - deposit_record_id：关联的入金记录 ID。
 * - local_order_no：本地订单号。
 * - event_type：事件类型（settle/create_claim）。
 * - status：出队状态（pending/processing/completed/failed）。
 * - attempts：已重试次数。
 * 
 * 关联关系：
 * - depositRecord()：关联的 DepositRecord。
 *
 * @property int $id 主键标识。
 * @property int $deposit_record_id 关联入金记录表标识。
 * @property string $local_order_no 本地订单号（与 deposit_records.local_order_no 对应）。
 * @property string $event_type 事件类型：settle=结算入账 / create_claim=创建索赔。
 * @property string $status 投递状态：pending / processing / succeeded / failed。
 * @property int $attempts 投递重试次数。
 * @property string $payload_hash 事件负载 SHA-256。
 * @property \Illuminate\Support\Carbon|null $available_at
 * @property \Illuminate\Support\Carbon|null $locked_at
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property string|null $provider_reference MT4 入金凭证引用。
 * @property string|null $last_error_code 最近错误码。
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间（10 位时间戳）。
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间（10 位时间戳）。
 * @property \Illuminate\Support\Carbon|null $deleted_at 软删除时间（10 位时间戳）。
 * @property-read \App\Models\DepositRecord|null $depositRecord
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentSettlementOutbox newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentSettlementOutbox newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentSettlementOutbox onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentSettlementOutbox query()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentSettlementOutbox whereAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentSettlementOutbox whereAvailableAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentSettlementOutbox whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentSettlementOutbox whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentSettlementOutbox whereDepositRecordId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentSettlementOutbox whereEventType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentSettlementOutbox whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentSettlementOutbox whereLastErrorCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentSettlementOutbox whereLocalOrderNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentSettlementOutbox whereLockedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentSettlementOutbox wherePayloadHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentSettlementOutbox whereProcessedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentSettlementOutbox whereProviderReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentSettlementOutbox whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentSettlementOutbox whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentSettlementOutbox withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|PaymentSettlementOutbox withoutTrashed()
 */
	class PaymentSettlementOutbox extends \Eloquent {}
}

namespace App\Models{
/**
 * 权限模型。
 * 
 * 功能逻辑说明：
 * - permissions 表保存前后台菜单、页面、按钮和接口权限字典。
 * - slug 表示稳定权限字符串，是前端按钮和后端接口共同使用的权限标识，例如 admin_role_assign_permissions。
 * - api_route 表示 Laravel 命名路由，例如 admin_api_assignPermissions，供权限中间件匹配接口访问权限。
 * - guard_type 用于区分 admin 和 front，避免后台管理员权限和前台代理/客户菜单权限混用。
 * - type=1 表示菜单，type=2 表示页面，type=3 表示按钮或接口动作。
 *
 * @property int $id 主键标识。
 * @property string $name 名称
 * @property string $slug 标识符
 * @property string $guard_type 守卫类型: admin/front
 * @property int $parent_id 父标识
 * @property int $type 类型: 1=菜单 2=页面 3=按钮
 * @property string|null $icon 图标
 * @property int $sort 排序
 * @property string|null $route 前端路由路径
 * @property string|null $api_route 后端API路由名称
 * @property int $status 状态: 0=禁用 1=启用
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间。
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间。
 * @property \Illuminate\Support\Carbon|null $deleted_at 软删除时间。
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Permission> $children
 * @property-read int|null $children_count
 * @property-read Permission|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Role> $roles
 * @property-read int|null $roles_count
 * @method static \Illuminate\Database\Eloquent\Builder|Permission active()
 * @method static \Illuminate\Database\Eloquent\Builder|Permission admin()
 * @method static \Illuminate\Database\Eloquent\Builder|Permission button()
 * @method static \Illuminate\Database\Eloquent\Builder|Permission front()
 * @method static \Illuminate\Database\Eloquent\Builder|Permission menu()
 * @method static \Illuminate\Database\Eloquent\Builder|Permission newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Permission newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Permission onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Permission page()
 * @method static \Illuminate\Database\Eloquent\Builder|Permission query()
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereApiRoute($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereGuardType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereIcon($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereRoute($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereSort($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Permission withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Permission withoutTrashed()
 */
	class Permission extends \Eloquent {}
}

namespace App\Models{
/**
 * 角色模型。
 * 
 * 功能逻辑说明：
 * - roles 表保存后台管理员、前台代理商和普通客户可绑定的角色。
 * - guard_type 用于区分 admin 和 front，admin 表示后台管理员角色，front 表示前台用户角色。
 * - role_permissions 是唯一生效的权限授权来源，菜单、按钮和接口是否可用都应从该中间表计算。
 * - roles.permissions JSON 只保留兼容字段，不再作为真实鉴权来源，避免形成双数据源。
 * - role_data_scopes.role_id 通过 dataScope() 关联，用于后台管理员的数据查看范围配置。
 *
 * @property int $id 主键标识
 * @property string $name 角色名称
 * @property string $guard_type 守卫类型: admin or front
 * @property string|null $description 描述
 * @property \Illuminate\Database\Eloquent\Collection<int, \App\Models\Permission> $permissions 权限 slugs 数组
 * @property int $status 状态: 1=启用 0=禁用
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Admin> $admins
 * @property-read int|null $admins_count
 * @property-read \App\Models\RoleDataScope|null $dataScope
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Permission> $permissionsRelation
 * @property-read int|null $permissions_relation_count
 * @method static \Illuminate\Database\Eloquent\Builder|Role newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Role newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Role onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Role query()
 * @method static \Illuminate\Database\Eloquent\Builder|Role whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Role whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Role whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Role whereGuardType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Role whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Role whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Role wherePermissions($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Role whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Role whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Role withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Role withoutTrashed()
 */
	class Role extends \Eloquent {}
}

namespace App\Models{
/**
 * 角色数据范围模型。
 * 
 * 功能逻辑说明：
 * - role_data_scopes 表保存角色级数据查看范围配置，是“进入接口后能看哪些数据”的真实数据表来源。
 * - role_id 表示角色 ID，对应 roles.id；当前表通过唯一约束保证每个角色最多维护一条数据范围配置。
 * - scope_type 表示数据范围类型，例如 all=全部数据、self=本人数据、agent_tree=绑定代理树、custom_users=指定用户集合。
 * - agent_ids 表示指定代理 ID 数组，仅在 custom_agents 等代理集合范围中参与计算。
 * - user_ids 表示指定用户 ID 数组，仅在 custom_users 范围中参与计算。
 * - status 表示配置是否启用，禁用配置不应参与后台业务列表或单条动作的数据范围判断。
 *
 * @property int $id 主键标识
 * @property int $role_id 角色标识，对应 roles.id
 * @property string $scope_type 数据范围类型：all=全部，self=本人，created=本人创建，agent_tree=绑定代理树，custom_agents=指定代理集合，custom_users=指定用户集合
 * @property array|null $agent_ids 指定代理标识数组，仅 scope_type=custom_agents 时使用
 * @property array|null $user_ids 指定用户标识数组，仅 scope_type=custom_users 时使用
 * @property int $status 状态：1=启用，0=禁用
 * @property \Illuminate\Support\Carbon $created_at 创建时间，10位时间戳
 * @property \Illuminate\Support\Carbon $updated_at 更新时间，10位时间戳
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间，10位时间戳
 * @property-read \App\Models\Role|null $role
 * @method static \Illuminate\Database\Eloquent\Builder|RoleDataScope newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RoleDataScope newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RoleDataScope onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|RoleDataScope query()
 * @method static \Illuminate\Database\Eloquent\Builder|RoleDataScope whereAgentIds($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RoleDataScope whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RoleDataScope whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RoleDataScope whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RoleDataScope whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RoleDataScope whereScopeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RoleDataScope whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RoleDataScope whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RoleDataScope whereUserIds($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RoleDataScope withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|RoleDataScope withoutTrashed()
 */
	class RoleDataScope extends \Eloquent {}
}

namespace App\Models{
/**
 * 点差配置模型。
 * 
 * 功能逻辑说明：
 * - spread_configs 表保存交易产品或代理组点差配置，用于后台点差管理和交易报价规则。
 * - spread 表示固定点差值。
 * - agent_group_id 表示代理组 ID，用于按代理组匹配点差规则。
 * - spread_ratio 表示点差比例，用于按比例计算或调整交易点差。
 * - status 表示点差配置状态，后台列表和业务读取应只使用启用配置。
 *
 * @property int $id 主键标识
 * @property float $spread 点差
 * @property int $agent_group_id 代理组标识
 * @property float $spread_ratio 点差比例
 * @property int $status 状态
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @method static \Illuminate\Database\Eloquent\Builder|SpreadConfig newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SpreadConfig newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SpreadConfig onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|SpreadConfig query()
 * @method static \Illuminate\Database\Eloquent\Builder|SpreadConfig whereAgentGroupId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SpreadConfig whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SpreadConfig whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SpreadConfig whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SpreadConfig whereSpread($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SpreadConfig whereSpreadRatio($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SpreadConfig whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SpreadConfig whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SpreadConfig withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|SpreadConfig withoutTrashed()
 */
	class SpreadConfig extends \Eloquent {}
}

namespace App\Models{
/**
 * 交易品种价格模型。
 * 
 * 功能逻辑说明：
 * - symbol_prices 表保存交易品种实时或历史报价，用于产品行情展示、风控和报价同步检查。
 * - symbol 表示交易品种代码，例如 XAUUSD 或 EURUSD。
 * - time 表示报价时间，modify_time 表示报价在外部系统中的更新时间。
 * - bid 表示买价，ask 表示卖价，low 表示周期最低价，high 表示周期最高价。
 * - direction 表示价格方向，digits 表示报价小数位数，spread 表示点差。
 * - group_id 表示报价所属交易组 ID，status 表示该报价记录是否启用或有效。
 *
 * @property int $id 主键标识
 * @property string $symbol 交易品种
 * @property string $time 时间
 * @property float $bid 买入价
 * @property float $ask 卖出价
 * @property float $low 最低价
 * @property float $high 最高价
 * @property int $direction 方向
 * @property int $digits 小数位数
 * @property float $spread 点差
 * @property int $group_id 分组标识: 1=贵金属 2=能源 3=外汇 4=指数 5=货币 6=股票
 * @property int $status 状态
 * @property string $modify_time 修改时间
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @method static \Illuminate\Database\Eloquent\Builder|SymbolPrice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SymbolPrice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SymbolPrice onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|SymbolPrice query()
 * @method static \Illuminate\Database\Eloquent\Builder|SymbolPrice whereAsk($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SymbolPrice whereBid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SymbolPrice whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SymbolPrice whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SymbolPrice whereDigits($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SymbolPrice whereDirection($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SymbolPrice whereGroupId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SymbolPrice whereHigh($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SymbolPrice whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SymbolPrice whereLow($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SymbolPrice whereModifyTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SymbolPrice whereSpread($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SymbolPrice whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SymbolPrice whereSymbol($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SymbolPrice whereTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SymbolPrice whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SymbolPrice withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|SymbolPrice withoutTrashed()
 */
	class SymbolPrice extends \Eloquent {}
}

namespace App\Models{
/**
 * 系统配置模型。
 * 
 * 功能逻辑说明：
 * - system_configs 表保存后台全局配置项，用于汇率、下载地址、出入金限制、开关项等系统级参数。
 * - key 表示配置唯一键，控制器和服务层应使用稳定 key 读取配置。
 * - value 表示配置值，按业务场景保存字符串、数字字符串或 JSON 字符串。
 * - group 表示配置分组，例如 general、risk、deposit 等，用于后台页面归类展示。
 * - description 表示配置说明，便于后台管理员理解该配置项用途。
 *
 * @property int $id 主键标识
 * @property string $key 配置键
 * @property string|null $value 配置值
 * @property string $group 分组
 * @property string $description 描述
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @method static \Illuminate\Database\Eloquent\Builder|SystemConfig newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SystemConfig newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SystemConfig onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|SystemConfig query()
 * @method static \Illuminate\Database\Eloquent\Builder|SystemConfig whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SystemConfig whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SystemConfig whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SystemConfig whereGroup($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SystemConfig whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SystemConfig whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SystemConfig whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SystemConfig whereValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SystemConfig withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|SystemConfig withoutTrashed()
 */
	class SystemConfig extends \Eloquent {}
}

namespace App\Models{
/**
 * 转组申请日志模型。
 * 
 * 功能逻辑说明：
 * - trans_apply_logs 表保存用户申请变更交易组的审核记录，用于后台转组审核和前台申请历史展示。
 * - user_id 表示申请转组的业务用户 ID，对应 user_infos.user_id。
 * - origin_group_id 表示原交易组 ID，group_id 表示目标交易组 ID。
 * - group_name 表示目标交易组名称快照，便于历史记录展示。
 * - applicant_id 和 applicant_name 表示提交申请的账号 ID 与名称快照。
 * - status 表示转组申请审核状态。
 * - apply_reason 表示申请原因，reject_reason 表示拒绝原因。
 * - created_by 和 updated_by 表示创建、更新该转组申请的后台管理员 ID，用于审计追踪。
 *
 * @property int $id 主键标识
 * @property int $user_id 用户标识
 * @property int $origin_group_id 转组申请前的原组别标识
 * @property int $group_id 分组标识
 * @property string $group_name 分组名称
 * @property int $applicant_id 申请人标识
 * @property string $applicant_name 申请人姓名
 * @property int $status 状态: 0=待处理 1=通过 -1=拒绝
 * @property string $apply_reason 代理提交的转组申请原因
 * @property string|null $reject_reason 拒绝原因
 * @property string $created_by 创建人
 * @property string $updated_by 更新人
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @property-read \App\Models\UserInfo|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|TransApplyLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TransApplyLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TransApplyLog onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|TransApplyLog query()
 * @method static \Illuminate\Database\Eloquent\Builder|TransApplyLog whereApplicantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransApplyLog whereApplicantName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransApplyLog whereApplyReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransApplyLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransApplyLog whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransApplyLog whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransApplyLog whereGroupId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransApplyLog whereGroupName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransApplyLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransApplyLog whereOriginGroupId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransApplyLog whereRejectReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransApplyLog whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransApplyLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransApplyLog whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransApplyLog whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransApplyLog withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|TransApplyLog withoutTrashed()
 */
	class TransApplyLog extends \Eloquent {}
}

namespace App\Models{
/**
 * Laravel 默认前台用户兼容模型。
 * 
 * 功能逻辑说明：
 * - 当前业务登录主体优先使用 UserLogin，对应真实业务表 user_logins。
 * - 该模型保留 Laravel 默认用户体系兼容能力，避免旧依赖或框架功能引用 User 时失效。
 * - role_id 表示绑定的 roles.id，权限判断仍委托给 Role::hasPermission。
 *
 * @property int $id 主键标识。
 * @property string $name 用户名（框架默认字段）。
 * @property string $email 邮箱（框架默认字段）。
 * @property \Illuminate\Support\Carbon|null $email_verified_at 邮箱验证时间。
 * @property string $password 密码哈希（框架默认字段）。
 * @property string|null $remember_token 记住登录令牌。
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间。
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间。
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \App\Models\Role|null $role
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Database\Factories\UserFactory factory(...$parameters)
 * @method static \Illuminate\Database\Eloquent\Builder|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User query()
 * @method static \Illuminate\Database\Eloquent\Builder|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereUpdatedAt($value)
 */
	class User extends \Eloquent {}
}

namespace App\Models{
/**
 * 用户收货地址模型。
 * 
 * 功能逻辑说明：
 * - user_addresses 表保存前台用户礼品收货地址，用于礼品发货、地址管理和后台发货页面展示。
 * - user_id 表示地址所属业务用户 ID，对应 user_infos.user_id。
 * - recipient_name 表示收件人姓名，recipient_phone 表示收件人联系电话。
 * - recipient_address 表示完整收货地址。
 * - is_default 表示是否默认地址：1=默认，0=普通地址。
 *
 * @property int $id 主键标识
 * @property int $user_id 用户标识
 * @property string $recipient_name 收件人姓名
 * @property string $recipient_phone 收件人电话
 * @property string $recipient_address 收件人地址
 * @property int $is_default 是否默认
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @property-read \App\Models\UserInfo|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|UserAddress newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserAddress newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserAddress onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|UserAddress query()
 * @method static \Illuminate\Database\Eloquent\Builder|UserAddress whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAddress whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAddress whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAddress whereIsDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAddress whereRecipientAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAddress whereRecipientName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAddress whereRecipientPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAddress whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAddress whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAddress withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|UserAddress withoutTrashed()
 */
	class UserAddress extends \Eloquent {}
}

namespace App\Models{
/**
 * 用户实名认证模型。
 * 
 * 功能逻辑说明：
 * - user_auths 表保存前台用户实名和银行卡认证资料，是后台实名认证审核、银行卡审核和前台资料展示的数据来源。
 * - user_id 表示认证资料所属业务用户 ID，对应 user_infos.user_id。
 * - bank_no 和 bank_no_tmp 表示已审核银行卡号与待审核银行卡号。
 * - bank_name 和 bank_name_tmp 表示已审核开户行名称与待审核开户行名称。
 * - bank_addr 和 bank_addr_tmp 表示已审核开户地址与待审核开户地址。
 * - bank_card_img、bank_card_back_img 及 tmp 字段表示银行卡图片和待审核图片。
 * - bank_status 表示银行卡审核状态，bank_remarks 表示银行卡审核备注。
 * - id_card_no 表示身份证号码，id_card_front 和 id_card_back 表示身份证正反面图片。
 * - id_card_status 表示身份证审核状态，id_card_remarks 表示身份证审核备注。
 * - is_bank_synced 表示银行卡信息是否已同步到后续资金或交易系统。
 *
 * @property int $id 主键标识
 * @property int $user_id 用户标识
 * @property string $bank_no 银行卡号
 * @property string $bank_no_tmp 银行卡号临时值：换卡流程中的新卡号（审核通过后覆盖正式字段）。
 * @property string $bank_name 银行名称
 * @property string $bank_name_tmp 开户行临时值：换卡流程中的新开户行。
 * @property string $bank_card_img 银行卡图片
 * @property string $bank_card_back_img 银行卡背面照片：实名/绑卡审核资料。
 * @property string $bank_card_img_tmp 银行卡临时图片
 * @property string $bank_card_back_img_tmp 银行卡背面照片临时值：换卡流程新卡背面照。
 * @property string $bank_addr 分行地址
 * @property string $bank_addr_tmp 分行临时地址
 * @property int $bank_status 银行卡状态: 0=未通过 1=审核中 2=已通过 3=变更中 4=已拒绝
 * @property string $bank_remarks 银行备注
 * @property string $id_card_no 身份证号
 * @property int $id_card_status 身份证状态: 0=未通过 1=审核中 2=已通过 4=已退回
 * @property string $id_card_front 身份证正面
 * @property string $id_card_back 身份证背面
 * @property string $id_card_remarks 身份证备注
 * @property int $is_bank_synced 银行信息同步
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @property-read \App\Models\UserInfo|null $userInfo
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuth newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuth newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuth onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuth query()
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuth whereBankAddr($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuth whereBankAddrTmp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuth whereBankCardBackImg($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuth whereBankCardBackImgTmp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuth whereBankCardImg($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuth whereBankCardImgTmp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuth whereBankName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuth whereBankNameTmp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuth whereBankNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuth whereBankNoTmp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuth whereBankRemarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuth whereBankStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuth whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuth whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuth whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuth whereIdCardBack($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuth whereIdCardFront($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuth whereIdCardNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuth whereIdCardRemarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuth whereIdCardStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuth whereIsBankSynced($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuth whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuth whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuth withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuth withoutTrashed()
 */
	class UserAuth extends \Eloquent {}
}

namespace App\Models{
/**
 * 用户认证信息备份模型。
 * 
 * 功能逻辑说明：
 * - user_auth_info 表曾用于保存用户认证历史快照或旧项目认证备份数据。
 * - 当前数据库未建 user_auth_info 表时不得在业务查询中直接依赖该模型，应以 user_auths 表作为实名认证和银行卡认证的真实数据源。
 * - 本模型仅作为历史代码兼容入口保留，后续如要重新启用必须先补迁移、数据迁移和回归测试。
 *
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuthInfo newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuthInfo newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuthInfo onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuthInfo query()
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuthInfo withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|UserAuthInfo withoutTrashed()
 */
	class UserAuthInfo extends \Eloquent {}
}

namespace App\Models{
/**
 * 用户组兼容模型。
 * 
 * 功能逻辑说明：
 * - user_groups 表曾用于保存旧项目用户组和交易费率配置。
 * - 当前数据库未建 user_groups 表时不得在业务查询中直接依赖该模型，应优先使用 group_configs 表承载代理组和客户交易组配置。
 * - 本模型仅作为历史代码兼容入口保留，后续如要重新启用必须先补迁移、种子数据和回归测试。
 *
 * @method static \Illuminate\Database\Eloquent\Builder|UserGroup newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserGroup newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserGroup onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|UserGroup query()
 * @method static \Illuminate\Database\Eloquent\Builder|UserGroup withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|UserGroup withoutTrashed()
 */
	class UserGroup extends \Eloquent {}
}

namespace App\Models{
/**
 * 用户业务资料模型。
 * 
 * 功能逻辑说明：
 * - user_infos 表保存前台业务用户资料、代理层级、资金字段和 MT4 状态。
 * - user_id 表示业务用户 ID，是代理树、交易、出入金和资料审核的核心关联键。
 * - login_id 表示 user_logins.id，用于把登录账号和业务资料连接起来。
 * - parent_id 表示上级代理业务用户 ID，family_tree 表示代理家谱链。
 * - account_type 表示账号类型，1=代理商，2=普通客户。
 *
 * @property int $id 主键标识
 * @property int $user_id 用户标识
 * @property int $login_id 登录标识
 * @property string $user_name 用户名
 * @property string $phone 电话
 * @property int $gender 性别
 * @property string|null $avatar 头像
 * @property int $level_id 级别标识
 * @property int $group_id 分组标识
 * @property int $parent_id 父标识
 * @property int $account_type 账户类型
 * @property string $family_tree 家谱树: 逗号分隔祖先链
 * @property float $total_funds 总资金
 * @property float $used_margin 已用保证金
 * @property float $avail_margin 可用保证金
 * @property float $equity 净值
 * @property float $effective_credit 有效信用额
 * @property float $risk_ratio 风险率
 * @property float $margin_amount 保证金金额
 * @property int $leverage 杠杆
 * @property string $cust_vol 客户交易量
 * @property int $pay_provider_id 支付提供商标识
 * @property int $equity_ratio 净值比例
 * @property int $comm_rate 佣金率
 * @property int $is_ecn 是否ECN
 * @property int $follow_parent_ecn 跟随父级ECN
 * @property int $auth_status 认证状态: 0=未验证 1=已验证 2=已退回 3=已禁用
 * @property int $is_mt4_synced 是否同步MT4
 * @property int $is_mt4_enabled MT4是否启用
 * @property int $is_mt4_readonly MT4是否只读
 * @property int $is_withdrawal_allowed 允许提现: 0=是 1=否
 * @property int $is_deposit_allowed 允许充值: 0=是 1=否
 * @property int $is_agent_confirmed 代理确认
 * @property string $original_group 原分组
 * @property string $mt4_group MT4分组
 * @property int $mt4_code MT4代码
 * @property int $trading_mode 交易模式: 0=佣金 1=净值
 * @property int $settle_method 结算方式: 1=线上 2=线下
 * @property int $settle_cycle 结算周期: 1=每周 2=每两周 3=每月
 * @property string $country 国家
 * @property string $city 城市
 * @property string $state 州/省
 * @property string|null $address 地址
 * @property int $is_gift_allowed 允许礼品
 * @property int $data_source 数据来源
 * @property string $remark 备注
 * @property int $created_by 创建人
 * @property int $updated_by 更新人
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @property-read \App\Models\UserAuth|null $auth
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AgentDescendant> $descendants
 * @property-read int|null $descendants_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, UserInfo> $directChildren
 * @property-read int|null $direct_children_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AgentDescendant> $directCustomers
 * @property-read int|null $direct_customers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AgentDescendant> $directSubAgents
 * @property-read int|null $direct_sub_agents_count
 * @property-read \App\Models\GroupConfig|null $groupConfig
 * @property-read \App\Models\AgentLevel|null $level
 * @property-read \App\Models\UserLogin|null $login
 * @property-read UserInfo|null $parent
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo query()
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereAccountType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereAuthStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereAvailMargin($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereAvatar($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereCommRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereCustVol($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereDataSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereEffectiveCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereEquity($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereEquityRatio($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereFamilyTree($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereFollowParentEcn($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereGroupId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereIsAgentConfirmed($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereIsDepositAllowed($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereIsEcn($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereIsGiftAllowed($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereIsMt4Enabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereIsMt4Readonly($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereIsMt4Synced($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereIsWithdrawalAllowed($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereLevelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereLeverage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereLoginId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereMarginAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereMt4Code($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereMt4Group($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereOriginalGroup($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo wherePayProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereRemark($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereRiskRatio($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereSettleCycle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereSettleMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereTotalFunds($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereTradingMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereUsedMargin($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo whereUserName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|UserInfo withoutTrashed()
 */
	class UserInfo extends \Eloquent {}
}

namespace App\Models{
/**
 * 用户登录模型。
 * 
 * 功能逻辑说明：
 * - user_logins 表保存前台登录账号、密码哈希、角色绑定和登录状态。
 * - user_id 表示业务用户 ID，关联 user_infos.user_id。
 * - role_id 表示绑定的 roles.id，前台代理商和普通客户菜单权限都通过该角色读取 role_permissions。
 * - jwt_token_id 表示当前有效 JWT 的唯一编号，用于前台单点登录和 token 失效判断。
 *
 * @property int $id 主键标识
 * @property int $user_id 业务用户标识 (来自id_sequences)
 * @property string $email 邮箱
 * @property string $password 密码
 * @property int $account_type 账户类型: 1=代理, 2=客户
 * @property int $role_id 前台角色标识，对应 roles.id，用于菜单和按钮权限过滤
 * @property int $is_enabled 是否启用
 * @property int $is_cancelled 是否注销
 * @property int $source_type 来源: 0=系统, 1=导入
 * @property string|null $jwt_token_id SSO: 当前JWT ID
 * @property string $last_login_ip 最后登录IP
 * @property string|null $last_login_at 最后登录时间
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserLoginLog> $loginLogs
 * @property-read int|null $login_logs_count
 * @property-read \App\Models\Role|null $role
 * @property-read \App\Models\UserInfo|null $userInfo
 * @method static \Illuminate\Database\Eloquent\Builder|UserLogin newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserLogin newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserLogin onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|UserLogin query()
 * @method static \Illuminate\Database\Eloquent\Builder|UserLogin whereAccountType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserLogin whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserLogin whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserLogin whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserLogin whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserLogin whereIsCancelled($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserLogin whereIsEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserLogin whereJwtTokenId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserLogin whereLastLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserLogin whereLastLoginIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserLogin wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserLogin whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserLogin whereSourceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserLogin whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserLogin whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserLogin withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|UserLogin withoutTrashed()
 */
	class UserLogin extends \Eloquent {}
}

namespace App\Models{
/**
 * 前台用户登录日志模型。
 * 
 * 功能逻辑说明：
 * - user_login_logs 表记录前台用户登录审计信息。
 * - 每次前台登录成功后写入登录账号 ID、业务用户 ID、登录 IP、IP 归属地和客户端标识。
 * - 该模型只负责日志数据映射，不参与登录鉴权和菜单权限判断。
 *
 * @property int $id 主键标识
 * @property int $login_id 登录标识
 * @property int $user_id 用户标识
 * @property string $login_ip 登录IP
 * @property string $ip_location IP地理位置
 * @property string|null $user_agent 用户代理
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @property-read \App\Models\UserLogin|null $userLogin
 * @method static \Illuminate\Database\Eloquent\Builder|UserLoginLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserLoginLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserLoginLog onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|UserLoginLog query()
 * @method static \Illuminate\Database\Eloquent\Builder|UserLoginLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserLoginLog whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserLoginLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserLoginLog whereIpLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserLoginLog whereLoginId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserLoginLog whereLoginIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserLoginLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserLoginLog whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserLoginLog whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserLoginLog withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|UserLoginLog withoutTrashed()
 */
	class UserLoginLog extends \Eloquent {}
}

namespace App\Models{
/**
 * MT4 用户建仓出队记录模型。
 * 
 * 文件功能：
 * - 映射 user_mt4_provisioning_outbox 表，记录新用户创建后在 MT4 端建仓的出队事件。
 * - 支持异步建仓与对账重试机制（reconciliation_attempts）。
 * 
 * 适用场景：
 * - 用户注册成功后，DispatchPendingUserMt4Provisioning 定时任务消费出队记录。
 * - MT4 建仓包括：创建交易账户、设置杠杆、分配交易组。
 * 
 * 主要字段：
 * - user_login_id：关联的 user_logins 记录 ID。
 * - user_info_id：关联的 user_infos 记录 ID。
 * - user_id：业务用户 ID。
 * - status：出队状态（pending/processing/completed/failed）。
 * - attempts：已重试次数（建仓重试）。
 * - reconciliation_attempts：对账重试次数（建仓成功后验证）。
 * - payload_ciphertext：加密的建仓参数载荷。
 * 
 * 关联关系：
 * - userLogin()：关联的 UserLogin。
 * - userInfo()：关联的 UserInfo。
 *
 * @property int $id 主键标识。
 * @property int $user_login_id 关联用户登录表标识（user_logins.id）。
 * @property int $user_info_id 关联用户信息表标识（user_infos.id）。
 * @property int $user_id 业务用户标识（与 user_infos.user_id 一致，用于查询便捷）。
 * @property string $status 预配状态：pending=待预配 / processing=预配中 / succeeded=成功 / failed=失败。
 * @property int $attempts 预配重试次数。
 * @property int $reconciliation_attempts 对账重试次数：注册成功但远端未确认时的核对次数。
 * @property string|null $payload_ciphertext 预配负载密文（开户所需资料加密存储）。
 * @property string|null $payload_hash 预配负载 SHA-256。
 * @property \Illuminate\Support\Carbon|null $available_at 可预配时间（10 位时间戳）。
 * @property \Illuminate\Support\Carbon|null $locked_at 锁定时间。
 * @property \Illuminate\Support\Carbon|null $processed_at 预配完成时间。
 * @property string|null $provider_reference MT4 开户凭证引用（mt4_code）。
 * @property string|null $last_error_code 最近错误码。
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间（10 位时间戳）。
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间（10 位时间戳）。
 * @property \Illuminate\Support\Carbon|null $deleted_at 软删除时间（10 位时间戳）。
 * @property-read \App\Models\UserInfo|null $userInfo
 * @property-read \App\Models\UserLogin|null $userLogin
 * @method static \Illuminate\Database\Eloquent\Builder|UserMt4ProvisioningOutbox newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserMt4ProvisioningOutbox newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserMt4ProvisioningOutbox onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|UserMt4ProvisioningOutbox query()
 * @method static \Illuminate\Database\Eloquent\Builder|UserMt4ProvisioningOutbox whereAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserMt4ProvisioningOutbox whereAvailableAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserMt4ProvisioningOutbox whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserMt4ProvisioningOutbox whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserMt4ProvisioningOutbox whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserMt4ProvisioningOutbox whereLastErrorCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserMt4ProvisioningOutbox whereLockedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserMt4ProvisioningOutbox wherePayloadCiphertext($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserMt4ProvisioningOutbox wherePayloadHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserMt4ProvisioningOutbox whereProcessedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserMt4ProvisioningOutbox whereProviderReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserMt4ProvisioningOutbox whereReconciliationAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserMt4ProvisioningOutbox whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserMt4ProvisioningOutbox whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserMt4ProvisioningOutbox whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserMt4ProvisioningOutbox whereUserInfoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserMt4ProvisioningOutbox whereUserLoginId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserMt4ProvisioningOutbox withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|UserMt4ProvisioningOutbox withoutTrashed()
 */
	class UserMt4ProvisioningOutbox extends \Eloquent {}
}

namespace App\Models{
/**
 * 在线用户模型。
 * 
 * 功能逻辑说明：
 * - 对应真实数据表 `user_onlines`，记录前台用户最近活跃时间、IP 和浏览器代理信息。
 * - 本表迁移中没有 `deleted_at` 字段，因此不能继承带 SoftDeletes 的 BaseModel，避免查询时自动追加不存在的软删除条件。
 * - 后台在线用户页面默认读取审计展示；强制下线由后台控制器删除在线记录并写入操作审计。
 *
 * @property int $id 主键标识
 * @property int $user_id 用户标识
 * @property int $last_activity 最后活跃时间
 * @property string|null $ip_address IP地址
 * @property string|null $user_agent 浏览器代理
 * @property \Illuminate\Support\Carbon $created_at 创建时间
 * @property \Illuminate\Support\Carbon $updated_at 更新时间
 * @method static \Illuminate\Database\Eloquent\Builder|UserOnline newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserOnline newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserOnline query()
 * @method static \Illuminate\Database\Eloquent\Builder|UserOnline whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserOnline whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserOnline whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserOnline whereLastActivity($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserOnline whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserOnline whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserOnline whereUserId($value)
 */
	class UserOnline extends \Eloquent {}
}

namespace App\Models{
/**
 * 用户交易模型。
 * 
 * 功能逻辑说明：
 * - user_trades 表保存用户 MT4 交易订单数据，用于后台交易查询、风控持仓、前台订单列表和返佣统计。
 * - user_id 表示交易订单所属业务用户 ID，对应 user_infos.user_id。
 * - ticket 表示 MT4 订单号，symbol 表示交易品种代码，cmd 表示订单方向或交易命令类型。
 * - volume 表示交易手数，digits 表示报价小数位数。
 * - open_time 和 open_price 表示开仓时间和开仓价格。
 * - stop_loss 和 take_profit 表示止损价和止盈价。
 * - close_time 表示订单平仓时间，旧 MT4 未平仓订单固定为 1970-01-01 00:00:00。
 * - close_price 表示平仓价格，profit 表示订单盈亏，commission、commission_agent、swaps 表示手续费、代理佣金和库存费。
 * - settlement_status 表示订单返佣或结算状态，settled_at 表示订单完成结算时间。
 * - comment、internal_id、magic 等字段保留 MT4 原始订单附加信息，便于排查和对账。
 *
 * @property int $id 主键标识
 * @property int $user_id 用户标识
 * @property int $ticket 订单号
 * @property string $symbol 交易品种
 * @property int $digits 小数位数
 * @property int $cmd 类型
 * @property int $volume 成交量
 * @property string $open_time 开仓时间
 * @property float $open_price 开仓价格
 * @property float $stop_loss 止损
 * @property float $take_profit 止盈
 * @property string $close_time 平仓时间
 * @property string|null $expiration 到期时间
 * @property int $reason 原因
 * @property float $conv_rate1 转换率1
 * @property float $conv_rate2 转换率2
 * @property float $commission 佣金
 * @property float $commission_agent 代理佣金
 * @property float $swaps 隔夜利息
 * @property float $close_price 平仓价格
 * @property float $profit 利润
 * @property float $taxes 税费
 * @property string $comment 评论
 * @property int $internal_id 内部标识
 * @property float $margin_rate 保证金率
 * @property int $timestamp_val 时间戳
 * @property int $magic 魔法号
 * @property int $gw_volume 网关成交量
 * @property int $gw_open_price 网关开仓价
 * @property int $gw_close_price 网关平仓价
 * @property string $modify_time 修改时间
 * @property int $settlement_status 结算状态: 0=未结算 1=已结算
 * @property string|null $settled_at 结算时间
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @property-read \App\Models\UserInfo|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade closed()
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade open()
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade query()
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereClosePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereCloseTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereCmd($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereCommission($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereCommissionAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereConvRate1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereConvRate2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereDigits($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereExpiration($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereGwClosePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereGwOpenPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereGwVolume($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereInternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereMagic($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereMarginRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereModifyTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereOpenPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereOpenTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereProfit($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereSettledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereSettlementStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereStopLoss($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereSwaps($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereSymbol($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereTakeProfit($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereTaxes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereTicket($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereTimestampVal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade whereVolume($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|UserTrade withoutTrashed()
 */
	class UserTrade extends \Eloquent {}
}

namespace App\Models{
/**
 * 凭证信息模型。
 * 
 * 功能逻辑说明：
 * - voucher_infos 表保存前台用户上传的入金或审核凭证，是后台凭证审核和前台凭证上传链路的基础模型。
 * - user_id 表示上传凭证的前台业务用户 ID，对应 user_infos.user_id。
 * - images 表示凭证图片路径或 JSON 图片列表，控制器或展示层负责按业务场景解析。
 * - remarks 表示用户或后台填写的凭证备注。
 * - review_status 表示凭证审核状态，review_message 表示审核说明或拒绝原因。
 * - created_by 和 updated_by 表示创建、更新该凭证记录的后台管理员 ID，用于审计追踪。
 *
 * @property int $id 主键标识
 * @property int $user_id 用户标识
 * @property string $images 凭证图片
 * @property string $remarks 备注
 * @property int $review_status 审核状态: 0=待处理 1=通过 2=拒绝
 * @property string $review_message 审核留言
 * @property string $created_by 创建人
 * @property string $updated_by 更新人
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @property-read \App\Models\UserInfo|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|VoucherInfo newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|VoucherInfo newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|VoucherInfo onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|VoucherInfo query()
 * @method static \Illuminate\Database\Eloquent\Builder|VoucherInfo whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VoucherInfo whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VoucherInfo whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VoucherInfo whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VoucherInfo whereImages($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VoucherInfo whereRemarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VoucherInfo whereReviewMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VoucherInfo whereReviewStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VoucherInfo whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VoucherInfo whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VoucherInfo whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VoucherInfo withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|VoucherInfo withoutTrashed()
 */
	class VoucherInfo extends \Eloquent {}
}

namespace App\Models{
/**
 * 余额信用清零模型。
 * 
 * 功能逻辑说明：
 * - whs_exp_zeros 表保存用户余额或信用额度清零操作记录，用于后台资金清零、风控处理和审计追踪。
 * - user_id 表示被清零的业务用户 ID，对应 user_infos.user_id。
 * - user_name 表示清零时记录的用户名称快照，便于后台列表展示。
 * - balance 表示清零前或清零目标余额，credit 表示清零前或清零目标信用额度。
 * - status 表示清零处理状态，后台应按该字段区分待处理、成功或失败。
 * - md5_key 表示清零请求校验签名，用于防止重复或伪造请求。
 * - created_by 和 updated_by 表示创建、更新该清零记录的后台管理员 ID，用于审计追踪。
 *
 * @property int $id 主键标识
 * @property int $user_id 用户标识
 * @property string $user_name 用户名
 * @property float $balance 余额
 * @property float $credit 信用额
 * @property int $status 状态: 1=待处理 2=已清零
 * @property string $md5_key MD5标识
 * @property string $created_by 创建人
 * @property string $updated_by 更新人
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @property-read \App\Models\UserInfo|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|WhsExpZero newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|WhsExpZero newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|WhsExpZero onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|WhsExpZero query()
 * @method static \Illuminate\Database\Eloquent\Builder|WhsExpZero whereBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhsExpZero whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhsExpZero whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhsExpZero whereCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhsExpZero whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhsExpZero whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhsExpZero whereMd5Key($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhsExpZero whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhsExpZero whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhsExpZero whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhsExpZero whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhsExpZero whereUserName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WhsExpZero withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|WhsExpZero withoutTrashed()
 */
	class WhsExpZero extends \Eloquent {}
}

namespace App\Models{
/**
 * 批量出金导入模型。
 * 
 * 功能逻辑说明：
 * - withdraw_imports 表保存后台批量出金导入记录，用于 Excel/CSV 导入后生成或同步用户出金数据。
 * - user_id 表示导入记录所属业务用户 ID，对应 user_infos.user_id，用于校验导入用户是否存在。
 * - user_name 表示导入文件中的用户展示名称，用于后台人工核对。
 * - amount 表示本条导入记录的出金金额，表结构中以字符串保存以兼容旧项目导入数据。
 * - remarks 表示导入备注或人工补充说明，mt4_order_id 表示关联的 MT4 订单号或外部资金系统订单号。
 * - batch_no 表示批次号，用于定位同一次 Excel/CSV 或手工批量导入的数据集合。
 * - is_synced 表示后续出金处理或资金系统同步状态：0=待处理，1=成功，2=失败。
 * - fail_reason 表示同步失败原因，便于后台重试导入或人工修复数据。
 * - created_by 和 updated_by 表示创建、更新该导入记录的后台管理员 ID，用于审计追踪。
 *
 * @property int $id 主键标识
 * @property int $user_id 用户标识
 * @property string $user_name 用户名
 * @property string $amount 金额
 * @property string $remarks 备注
 * @property int $mt4_order_id MT4订单标识
 * @property string $batch_no 批次号
 * @property int $is_synced 是否同步: 0=待处理 1=成功 2=失败
 * @property string $fail_reason 失败原因
 * @property int $created_by 创建人
 * @property int $updated_by 更新人
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @property-read \App\Models\UserInfo|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawImport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawImport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawImport onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawImport query()
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawImport whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawImport whereBatchNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawImport whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawImport whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawImport whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawImport whereFailReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawImport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawImport whereIsSynced($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawImport whereMt4OrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawImport whereRemarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawImport whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawImport whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawImport whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawImport whereUserName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawImport withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawImport withoutTrashed()
 */
	class WithdrawImport extends \Eloquent {}
}

namespace App\Models{
/**
 * 出金记录模型。
 * 
 * 功能逻辑说明：
 * - withdraw_records 表保存前台用户出金申请和后台处理结果，是后台出金审核、资金风控和用户提现查询的数据来源。
 * - user_id 表示出金所属业务用户 ID，对应 user_infos.user_id，用于按代理层级、管理员数据范围和用户维度过滤记录。
 * - user_name 表示出金用户展示名称，mt4_ticket 表示关联的 MT4 交易账号或资金系统票据号。
 * - apply_amount 表示出金申请金额，actual_amount 表示实际出金金额，fee 表示手续费，rmb_fee 表示人民币手续费。
 * - exchange_rate 表示出金折算汇率，bank_no、bank_name、bank_addr 分别表示收款银行卡号、开户行名称和开户地址。
 * - status 表示出金处理状态，local_order_no 表示本地出金订单号，third_order_no 表示第三方支付或资金系统订单号。
 * - reject_reason 表示拒绝原因，后台驳回出金申请时必须写入可追溯说明。
 * - mt4_return_status 表示 MT4 或外部资金系统返回状态，用于判断后续同步是否成功。
 * - created_by 和 updated_by 表示创建、更新该记录的后台管理员 ID，用于审计追踪。
 *
 * @property int $id 主键标识
 * @property int $user_id 用户标识
 * @property string $user_name 用户名
 * @property string $mt4_ticket MT4订单号
 * @property string $apply_amount 申请出金金额（用户提交）。
 * @property string $actual_amount 实际打款金额（扣除手续费后）。
 * @property string $fee 出金手续费（默认 0.00）。
 * @property string $exchange_rate 汇率（默认 0.00000000）。
 * @property string $rmb_fee 人民币口径手续费（默认 0.00）。
 * @property string $bank_no 银行卡号
 * @property string $bank_name 银行名称
 * @property string $bank_addr 分行地址
 * @property int $status 状态: 0=待处理 1=处理中 2=完成 3=失败
 * @property string $local_order_no 本地订单号（幂等与对账唯一键）。
 * @property string $third_order_no 第三方订单号
 * @property string|null $reject_reason 拒绝原因
 * @property string $mt4_return_status MT4返回状态
 * @property string $created_by 创建人
 * @property string $updated_by 更新人
 * @property \Illuminate\Support\Carbon $created_at 创建时间(10位时间戳)
 * @property \Illuminate\Support\Carbon $updated_at 更新时间(10位时间戳)
 * @property \Illuminate\Support\Carbon|null $deleted_at 删除时间(10位时间戳)
 * @property string|null $idempotency_key 幂等键：防重复出金申请。
 * @property string $funding_status 打款状态：pending=待打款 / funded=已打款 / refunded=已退款。
 * @property string|null $funding_payload_hash 打款负载 SHA-256：防篡改。
 * @property int|null $refund_mt4_ticket 退款 MT4 凭证。
 * @property \Illuminate\Support\Carbon|null $refund_time 退款完成时间。
 * @property string|null $funding_error_code 打款失败错误码。
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WithdrawSettlementOutbox> $settlementOutboxes
 * @property-read int|null $settlement_outboxes_count
 * @property-read \App\Models\UserInfo|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord query()
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord whereActualAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord whereApplyAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord whereBankAddr($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord whereBankName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord whereBankNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord whereExchangeRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord whereFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord whereFundingErrorCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord whereFundingPayloadHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord whereFundingStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord whereIdempotencyKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord whereLocalOrderNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord whereMt4ReturnStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord whereMt4Ticket($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord whereRefundMt4Ticket($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord whereRefundTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord whereRejectReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord whereRmbFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord whereThirdOrderNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord whereUserName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawRecord withoutTrashed()
 */
	class WithdrawRecord extends \Eloquent {}
}

namespace App\Models{
/**
 * 出金结算出队记录模型。
 * 
 * 文件功能：
 * - 映射 withdraw_settlement_outbox 表，记录出金订单的 MT4 结算出队事件。
 * - 支持异步退款与重试机制。
 * 
 * 适用场景：
 * - 后台管理员拒绝出金申请后，DispatchPendingWithdrawSettlements 定时任务消费出队记录。
 * - 将已扣除的出金金额退还到用户 MT4 账户。
 * 
 * 主要字段：
 * - withdraw_record_id：关联的出金记录 ID。
 * - local_order_no：本地订单号。
 * - event_type：事件类型（refund/complete）。
 * - status：出队状态（pending/processing/completed/failed）。
 * - attempts：已重试次数。
 * 
 * 关联关系：
 * - withdrawRecord()：关联的 WithdrawRecord。
 *
 * @property int $id 主键标识。
 * @property int $withdraw_record_id 关联出金记录表标识。
 * @property string $local_order_no 本地订单号（与 withdraw_records.local_order_no 对应）。
 * @property string $event_type 事件类型：refund=退款 / complete=完成。
 * @property string $status 投递状态：pending / processing / succeeded / failed。
 * @property int $attempts 投递重试次数。
 * @property string $payload_hash 事件负载 SHA-256。
 * @property \Illuminate\Support\Carbon|null $available_at 可投递时间（10 位时间戳）。
 * @property \Illuminate\Support\Carbon|null $locked_at 锁定时间。
 * @property \Illuminate\Support\Carbon|null $processed_at 投递完成时间。
 * @property string|null $provider_reference MT4 出金/退款凭证引用。
 * @property string|null $last_error_code 最近错误码。
 * @property \Illuminate\Support\Carbon|null $created_at 创建时间（10 位时间戳）。
 * @property \Illuminate\Support\Carbon|null $updated_at 更新时间（10 位时间戳）。
 * @property \Illuminate\Support\Carbon|null $deleted_at 软删除时间（10 位时间戳）。
 * @property-read \App\Models\WithdrawRecord|null $withdrawRecord
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawSettlementOutbox newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawSettlementOutbox newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawSettlementOutbox onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawSettlementOutbox query()
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawSettlementOutbox whereAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawSettlementOutbox whereAvailableAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawSettlementOutbox whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawSettlementOutbox whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawSettlementOutbox whereEventType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawSettlementOutbox whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawSettlementOutbox whereLastErrorCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawSettlementOutbox whereLocalOrderNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawSettlementOutbox whereLockedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawSettlementOutbox wherePayloadHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawSettlementOutbox whereProcessedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawSettlementOutbox whereProviderReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawSettlementOutbox whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawSettlementOutbox whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawSettlementOutbox whereWithdrawRecordId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawSettlementOutbox withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|WithdrawSettlementOutbox withoutTrashed()
 */
	class WithdrawSettlementOutbox extends \Eloquent {}
}

