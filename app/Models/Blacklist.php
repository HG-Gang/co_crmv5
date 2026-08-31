<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:08
 */

namespace App\Models;

/**
 * 黑名单模型。
 *
 * 文件功能：
 * - blacklists 表保存被限制注册或操作的用户身份信息，用于后台风控和注册前置校验。
 * - name 表示被限制对象姓名或备注名称。
 * - id_card 表示被限制的身份证号码，用于实名信息命中黑名单。
 * - email 表示被限制的邮箱，用于注册、登录或资料变更风控。
 * - phone 表示被限制的手机号，用于注册、联系信息或安全校验风控。
 *
 * 安全边界：
 * - id_card、email、phone 属敏感个人信息，使用或展示时必须脱敏，不得明文写入日志和接口响应。
 */
class Blacklist extends BaseModel
{
    /**
     * 模型绑定的数据表名称。
     *
     * @var string $table 表示当前模型读写的真实数据库表，固定为 blacklists。
     */
    protected $table = 'blacklists';
}
