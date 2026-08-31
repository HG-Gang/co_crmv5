<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 14:37
 */

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * 大代理模型。
 *
 * 文件功能：
 * - big_agents 表保存大代理登录账号、邮箱、加密密码、启用状态和可管理下级代理范围。
 * - id 表示大代理主键，即 big_agents.id，供登录会话与后台管理定位账号。
 * - username 表示大代理登录名，前台大代理入口会按该字段查找账号。
 * - sub_agent_ids 表示可管理下级代理 ID 集合，历史数据通常以逗号分隔字符串保存；
 *   即 sub_agent_ids 表示大代理可管理的下级代理 ID 集合，供后台授权其查看的下级代理范围。
 * - is_enabled 表示大代理账号是否启用，禁用账号不应允许继续登录或查看下级数据。
 * - jwt_token_id 表示当前登录令牌标识，用于让服务端识别最新有效会话。
 * - loginLogs 表示大代理登录日志关联，用于读取该账号的登录审计记录。
 *
 * 安全边界：
 * - password 为密码哈希、jwt_token_id 为会话令牌标识，均通过 $hidden 禁止序列化到接口响应。
 */
class BigAgent extends BaseModel
{
    use HasFactory;

    /**
     * 序列化隐藏字段。
     *
     * 逻辑说明：
     * - password 表示密码哈希，只能用于服务端校验，不能出现在接口响应中。
     * - jwt_token_id 表示会话令牌标识，暴露后会破坏服务端会话识别边界。
     * - deleted_at 表示软删除时间，隐藏避免暴露删除状态细节。
     *
     * @var array<int, string>
     */
    protected $hidden = ['password', 'jwt_token_id', 'deleted_at'];

    /**
     * 模型对应的数据表名称。
     *
     * 参数逻辑说明：
     * - $table 指向 big_agents 表，避免 Laravel 按类名自动推断错误表名。
     *
     * @var string
     */
    protected $table = 'big_agents';

    /**
     * loginLogs() 返回该大代理账号的登录审计日志。
     *
     * 关联逻辑说明：
     * - big_agent_login_logs.big_agent_id 对应当前 big_agents.id。
     * - 后台查看大代理登录历史时通过该关系读取 IP、登录时间等审计信息。
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany 大代理登录日志集合。
     */
    public function loginLogs()
    {
        return $this->hasMany(BigAgentLoginLog::class, 'big_agent_id');
    }
}
