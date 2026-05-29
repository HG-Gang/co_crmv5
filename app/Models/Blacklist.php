<?php

namespace App\Models;

use App\Models\BaseModel;

/**
 * 黑名单模型 | Blacklist Model
 * 
 * 管理被封锁的用户或IP地址。
 * Manages blocked users or IP addresses.
 */
class Blacklist extends BaseModel
{
    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'blacklists';
}
