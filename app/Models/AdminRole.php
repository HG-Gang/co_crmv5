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
 * 管理员角色兼容模型。
 *
 * 文件功能：
 * - 底层数据表仍为 roles，保留该模型是为了兼容旧代码中的 AdminRole 调用。
 * - 新权限链路优先使用 Role 模型和 role_permissions 中间表。
 * - 本模型只声明角色基础字段，不单独维护第二套权限来源。
 */
class AdminRole extends BaseModel
{
    /**
     * 数据表名称。
     *
     * 逻辑说明：
     * - AdminRole 与 Role 共用 roles 表，避免后台角色出现两套数据源。
     *
     * @var string
     */
    protected $table = 'roles';

    /**
     * 可批量赋值字段。
     *
     * 字段含义：
     * - name 表示角色稳定名称，例如 super_admin。
     * - guard_type 表示角色守卫类型，admin=后台角色，front=前台角色。
     * - description 表示角色用途说明。
     * - permissions 表示历史 JSON 兼容字段，不作为当前真实鉴权来源。
     * - status 表示角色启停状态，1=启用，0=停用。
     *
     * @var array<int, string>
     */
    protected $fillable = ['name', 'guard_type', 'description', 'permissions', 'status'];

    /**
     * 字段类型转换。
     *
     * 字段含义：
     * - permissions 转为数组，仅用于兼容旧数据显示。
     * - status 转为整数，便于页面和接口判断角色是否启用。
     *
     * @var array<string, string>
     */
    protected $casts = [
        'permissions' => 'array',
        'status' => 'integer',
    ];
}
