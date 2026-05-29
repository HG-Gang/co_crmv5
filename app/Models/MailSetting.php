<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\BaseModel;

/**
 * 邮件设置模型 | Mail Setting Model
 * 
 * 存储系统的邮件发送配置（SMTP等）。
 * Stores system email delivery configurations (SMTP, etc.).
 */
class MailSetting extends BaseModel
{
    use HasFactory;

    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'mail_settings';
}
