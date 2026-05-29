<?php
namespace App\Models;

use App\Models\BaseModel;

/**
 * 管理员角色模型 | Admin Role Model
 * 
 * 定义管理员的角色及其访问控制列表（ACL）。
 * Defines admin roles and their Access Control List (ACL).
 */
class AdminRole extends BaseModel
{
    /**
     * 数据库表名 | Table Name
     * @var string
     */
    protected $table = 'roles';

    /**
     * 可批量赋值的属性 | The attributes that are mass assignable.
     * @var array
     */
    protected $fillable = ['name', 'guard_type', 'description', 'permissions', 'status'];

    /**
     * 字段类型转换 | Attribute Casting
     * @var array
     */
    protected $casts = [
        'permissions' => 'array',
        'status' => 'integer',
    ];
}
