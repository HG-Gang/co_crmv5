<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:09
 */

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 用户登录模型。
 *
 * 文件功能：
 * - user_logins 表保存前台登录账号、密码哈希、角色绑定和登录状态。
 * - user_id 表示业务用户 ID，关联 user_infos.user_id。
 * - role_id 表示绑定的 roles.id，前台代理商和普通客户菜单权限都通过该角色读取 role_permissions。
 * - jwt_token_id 表示当前有效 JWT 的唯一编号，用于前台单点登录和 token 失效判断。
 */
class UserLogin extends Authenticatable
{
    use SoftDeletes;

    /**
     * 日期时间格式。
     *
     * 逻辑说明：
     * - user_logins 表历史时间字段使用 Unix 时间戳，保持与旧数据兼容。
     *
     * @var string
     */
    protected $dateFormat = 'U';

    /**
     * 数据表名称。
     *
     * @var string
     */
    protected $table = 'user_logins';

    /**
     * 可批量赋值字段。
     *
     * 字段含义：
     * - user_id 表示业务用户 ID。
     * - email 表示前台登录邮箱。
     * - password 表示前台登录密码哈希，禁止对外序列化。
     * - account_type 表示账号类型，1=代理商，2=普通客户。
     * - role_id 表示绑定的 roles.id。
     * - is_enabled 表示账号是否启用。
     * - is_cancelled 表示账号是否注销。
     * - source_type 表示账号来源类型，兼容旧项目导入和新注册来源。
     * - jwt_token_id 表示当前有效 JWT 的唯一编号。
     * - last_login_ip 表示最后登录 IP。
     * - last_login_at 表示最后登录时间。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id', 'email', 'password', 'account_type', 'role_id',
        'is_enabled', 'is_cancelled', 'source_type', 'jwt_token_id',
        'last_login_ip', 'last_login_at',
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
     * - last_login_at 保持字符串输出，兼容旧前台页面显示。
     * - is_enabled、is_cancelled、role_id 转为整数，避免权限和状态判断出现字符串比较差异。
     *
     * @var array<string, string>
     */
    protected $casts = [
        'last_login_at' => 'string',
        'is_enabled' => 'integer',
        'is_cancelled' => 'integer',
        'role_id' => 'integer',
    ];

    /**
     * 为数组/JSON 序列化准备日期。
     *
     * 逻辑说明：
     * - UserLogin 继承 Authenticatable 而非 BaseModel，需自行保证接口日期输出格式统一。
     * - 落库仍使用 $dateFormat=U（Unix 秒），此处只把序列化输出固定为 Y-m-d H:i:s，
     *   避免同一时间字段在接口中时而出现 Unix 时间戳、时而出现日期字符串。
     *
     * @param \DateTimeInterface $date Eloquent 正在序列化的日期对象（已按应用时区转换）。
     * @return string Y-m-d H:i:s 格式的日期时间字符串。
     */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * 关联前台角色。
     *
     * 逻辑说明：
     * - user_logins.role_id 对应 roles.id。
     * - 前台菜单、按钮和接口权限通过该角色继续读取 role_permissions。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 前台角色。
     */
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * 关联用户资料。
     *
     * 逻辑说明：
     * - user_infos.login_id 对应 user_logins.id。
     * - 该关系用于从登录账号读取业务资料、代理层级和资金信息。
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne 用户业务资料。
     */
    public function userInfo()
    {
        return $this->hasOne(UserInfo::class, 'login_id', 'id');
    }

    /**
     * 关联登录日志。
     *
     * 逻辑说明：
     * - user_login_logs.login_id 对应 user_logins.id。
     * - 用于审计前台用户最近登录 IP、地区和客户端信息。
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany 前台登录日志集合。
     */
    public function loginLogs()
    {
        return $this->hasMany(UserLoginLog::class, 'login_id');
    }

    /**
     * 判断是否为代理商账号。
     *
     * 逻辑说明：
     * - account_type=1 表示代理商，可展示代理管理和返佣相关菜单。
     *
     * @return bool
     */
    public function isAgent()
    {
        return $this->account_type === 1;
    }

    /**
     * 判断是否为普通客户账号。
     *
     * 逻辑说明：
     * - account_type=2 表示普通客户，不应展示代理商专属菜单。
     *
     * @return bool
     */
    public function isCustomer()
    {
        return $this->account_type === 2;
    }

    /**
     * 判断账号是否可用。
     *
     * 逻辑说明：
     * - isActive 同时要求 is_enabled=1 且 is_cancelled=0。
     * - 禁用或注销账号都不能继续登录前台。
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->is_enabled === 1 && $this->is_cancelled === 0;
    }
}
