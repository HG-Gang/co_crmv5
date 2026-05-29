<?php
namespace App\Models;

use App\Models\BaseModel;

/**
 * 用户认证信息备份模型 | User Auth Info Model
 * 
 * 存储用户认证信息的备份或历史记录。
 * Stores backups or historical records of user authentication information.
 */
class UserAuthInfo extends BaseModel
{
    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'user_auth_info';
}
