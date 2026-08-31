<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 02:36
 */

namespace App\Models;

use App\Models\BaseModel;

/**
 * 前台用户登录日志模型。
 *
 * 文件功能：
 * - user_login_logs 表记录前台用户登录审计信息。
 * - 每次前台登录成功后写入登录账号 ID、业务用户 ID、登录 IP、IP 归属地和客户端标识。
 * - 该模型只负责日志数据映射，不参与登录鉴权和菜单权限判断。
 */
class UserLoginLog extends BaseModel
{
    /**
     * 数据表名称。
     *
     * @var string
     */
    protected $table = 'user_login_logs';

    /**
     * 可批量赋值字段。
     *
     * 字段含义：
     * - login_id 表示 user_logins.id。
     * - user_id 表示业务用户 ID。
     * - login_ip 表示登录 IP。
     * - ip_location 表示登录 IP 解析出的地区文本。
     * - user_agent 表示登录浏览器或客户端标识。
     *
     * @var array<int, string>
     */
    protected $fillable = ['login_id', 'user_id', 'login_ip', 'ip_location', 'user_agent'];

    /**
     * 关联登录认证信息。
     *
     * 逻辑说明：
     * - user_login_logs.login_id 对应 user_logins.id。
     * - 前台审计页面可通过该关系展示登录邮箱、账号类型和状态。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 登录日志所属前台登录账号。
     */
    public function userLogin()
    {
        return $this->belongsTo(UserLogin::class, 'login_id');
    }
}
