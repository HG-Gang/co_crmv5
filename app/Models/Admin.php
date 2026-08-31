<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 02:31
 */

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 管理员模型。
 *
 * 文件功能：
 * - admins 表保存后台管理员登录账号、角色绑定和登录状态。
 * - 后台登录成功后会写入 last_login_ip、last_login_at、login_count 和 jwt_token_id。
 * - role_id 表示绑定的 roles.id，菜单权限、按钮权限和接口权限都通过该角色继续读取 role_permissions。
 * - jwt_token_id 表示当前有效 JWT 的唯一编号，用于后台单点登录和 token 失效判断。
 */
class Admin extends Authenticatable
{
    use SoftDeletes;

    /**
     * 数据表名称。
     *
     * @var string
     */
    protected $table = 'admins';

    /**
     * 认证守卫名称。
     *
     * 逻辑说明：
     * - guard=admin 表示该模型服务后台管理员认证，不与前台 user guard 混用。
     *
     * @var string
     */
    protected $guard = 'admin';

    /**
     * 时间戳存储格式。
     *
     * 逻辑说明：
     * - admins 表历史时间戳字段使用 Unix 时间戳，保持与旧数据兼容。
     *
     * @var string
     */
    protected $dateFormat = 'U';

    /**
     * 可批量赋值字段。
     *
     * 字段含义：
     * - username 表示后台登录账号。
     * - email 表示管理员邮箱。
     * - password 表示管理员登录密码哈希，禁止对外序列化。
     * - mobile 表示管理员手机号。
     * - role_id 表示绑定的 roles.id。
     * - status 表示管理员启停状态，1=启用，0=停用。
     * - last_login_ip 表示最后一次登录 IP。
     * - last_login_at 表示最后一次登录时间。
     * - last_login_address 表示最后一次登录地理位置文本。
     * - login_count 表示累计登录次数。
     * - created_by 表示创建该管理员的上级管理员 ID 或来源标识。
     * - jwt_token_id 表示当前有效 JWT 的唯一编号。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username', 'email', 'password', 'mobile',
        'role_id', 'status', 'last_login_ip', 'last_login_at',
        'last_login_address', 'login_count', 'created_by', 'jwt_token_id'
    ];

    /**
     * 序列化隐藏字段。
     *
     * 逻辑说明：
     * - password 表示密码哈希，只能用于服务端校验，不能出现在接口响应中。
     *
     * @var array<int, string>
     */
    protected $hidden = ['password'];

    /**
     * 字段类型转换。
     *
     * 字段含义：
     * - status 转为整数，便于 isActive() 和接口稳定判断。
     * - login_count 转为整数，避免页面统计出现字符串数字。
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => 'integer',
        'login_count' => 'integer',
    ];

    /**
     * 序列化日期为可读格式。
     *
     * 参数含义：
     * - $date 表示 Eloquent 正在序列化的日期对象。
     *
     * @param \DateTimeInterface $date 日期对象。
     * @return string 标准日期时间字符串。
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * 关联后台角色。
     *
     * 逻辑说明：
     * - admins.role_id 对应 roles.id。
     * - 后台管理员的菜单、按钮和接口权限都通过该角色继续读取 role_permissions。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 后台角色。
     */
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * 检查当前管理员是否拥有指定权限。
     *
     * 参数含义：
     * - $slug 表示 permissions.slug，是后台菜单、按钮和接口共用的稳定权限字符串。
     *
     * 逻辑边界：
     * - 管理员没有绑定角色时直接返回 false。
     * - 具体授权判断委托给 Role::hasPermission()，保持 role_permissions 为唯一权限来源。
     *
     * @param string $slug 权限字符串。
     * @return bool true=拥有权限，false=没有权限。
     */
    public function hasPermission($slug)
    {
        if (!$this->role) return false;
        return $this->role->hasPermission($slug);
    }

    /**
     * 获取当前管理员拥有的全部权限 slug。
     *
     * 逻辑说明：
     * - 权限唯一来源是 role_permissions 中间表，不再读取 roles.permissions JSON。
     * - 返回 slug 数组，供 Blade/JS 前端判断按钮显示或后端辅助判断。
     *
     * @return array 权限 slug 数组，例如 ['admin_user_list', 'admin_user_update']。
     */
    public function getAllPermissions()
    {
        if (!$this->role) {
            return [];
        }

        return $this->role->permissionsRelation()
            ->where('permissions.status', 1)
            ->pluck('permissions.slug')
            ->toArray();
    }

    /**
     * 关联登录日志。
     *
     * 逻辑说明：
     * - admin_login_logs.admin_id 对应 admins.id。
     * - 该关系用于后台审计页面查看管理员登录 IP、地区和客户端信息。
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany 管理员登录日志集合。
     */
    public function loginLogs()
    {
        return $this->hasMany(AdminLoginLog::class, 'admin_id');
    }

    /**
     * 判断管理员是否启用。
     *
     * 逻辑说明：
     * - isActive 根据 admins.status 判断账号状态。
     * - status=1 表示可登录，其他值表示禁用或不可用。
     *
     * @return bool true=启用，false=禁用。
     */
    public function isActive()
    {
        return (int)$this->status === 1;
    }
}
