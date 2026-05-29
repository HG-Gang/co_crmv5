<?php
namespace App\Models;

use App\Models\BaseModel;

/**
 * 用户组模型 | User Group Model
 * 
 * 定义不同的用户组及其交易费率等配置。
 * Defines different user groups and their configurations like trading rates.
 */
class UserGroup extends BaseModel
{
    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'user_groups';
}
