<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 02:49
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
/**
 * 邮件设置模型。
 *
 * 文件功能：
 * - mail_settings 表保存系统邮件发送配置，用于注册验证、通知消息、后台测试邮件等发送场景。
 * - driver 表示邮件发送驱动，例如 smtp。
 * - host 表示邮件服务器地址，port 表示邮件服务器端口。
 * - username 表示邮件服务登录账号，password 表示邮件服务授权密码。
 * - encryption 表示加密方式，例如 ssl、tls 或空值。
 * - from_address 表示默认发件邮箱，from_name 表示默认发件人名称。
 */
class MailSetting extends BaseModel
{
    use HasFactory;

    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 mail_settings。
     */
    protected $table = 'mail_settings';
}
