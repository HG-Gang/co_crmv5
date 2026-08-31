<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:30
 */

/**
 * 创建角色表 roles。
 *
 * 文件功能：
 * - RBAC 角色主体表：角色名称、守卫类型、权限快照与启停状态。
 *
 * 字段语义：
 * - name 角色名称；guard_type 守卫类型（admin 后台 / front 前台）；
 * - description 角色描述；permissions 权限 slug 数组（JSON 快照）；
 * - status 状态（1=启用 0=禁用）。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRolesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('roles', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->string('name', 100)->comment('角色名称 | Role name');
            $blueprint->string('guard_type', 20)->comment('守卫类型: admin or front | Guard type: admin or front');
            $blueprint->text('description')->nullable()->comment('描述 | Description');
            $blueprint->json('permissions')->nullable()->comment('权限 slugs 数组 | Permission slugs array');
            $blueprint->tinyInteger('status')->default(1)->comment('状态: 1=启用 0=禁用 | Status: 1=active 0=disabled');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间(10位时间戳) | Created at (10-digit timestamp)');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间(10位时间戳) | Updated at (10-digit timestamp)');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间(10位时间戳) | Deleted at (10-digit timestamp)');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('roles');
    }
}
