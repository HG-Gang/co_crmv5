<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 02:31
 */

namespace App\Models;

use App\Models\BaseModel;

/**
 * 管理员登录日志模型。
 *
 * 文件功能：
 * - admin_login_logs 表记录后台管理员登录审计信息。
 * - 每次后台登录成功后写入管理员 ID、登录 IP、IP 归属地和客户端标识。
 * - 该模型只负责日志数据映射，不参与登录鉴权和权限判断。
 */
class AdminLoginLog extends BaseModel
{
    /**
     * 数据表名称。
     *
     * @var string
     */
    protected $table = 'admin_login_logs';

    /**
     * 可批量赋值字段。
     *
     * 字段含义：
     * - admin_id 表示登录管理员 admins.id。
     * - login_ip 表示登录 IP。
     * - ip_location 表示登录 IP 解析出的地区文本。
     * - user_agent 表示登录浏览器或客户端标识。
     *
     * @var array<int, string>
     */
    protected $fillable = ['admin_id', 'login_ip', 'ip_location', 'user_agent'];

    /**
     * 关联管理员。
     *
     * 逻辑说明：
     * - admin_login_logs.admin_id 对应 admins.id。
     * - 后台审计页面可通过该关系展示登录账号、邮箱和角色信息。
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo 登录日志所属管理员。
     */
    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}
