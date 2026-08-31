<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 21:05
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建角色数据范围配置表。
 *
 * 文件功能：
 * - RBAC 权限控制“能不能访问菜单、按钮或接口”，本表控制“进入接口后能查看哪些业务数据”。
 * - 后台不同管理员角色必须通过数据表配置得到数据范围，避免把代理、用户或角色范围写死在控制器中。
 * - role_id 唯一约束保证每个角色最多只有一条启用配置来源，减少同一角色多套范围配置带来的歧义。
 */
class CreateRoleDataScopesTable extends Migration
{
    /**
     * 执行迁移：创建 role_data_scopes 表。
     *
     * 字段参数说明：
     * - role_id 表示角色 ID，对应 roles.id。
     * - scope_type 表示数据范围类型：all=全部数据、self=本人数据、created=本人创建、agent_tree=绑定代理树、custom_agents=指定代理集合、custom_users=指定用户集合。
     * - agent_ids 表示指定代理 ID 数组，仅 scope_type=custom_agents 时使用。
     * - user_ids 表示指定用户 ID 数组，仅 scope_type=custom_users 时使用。
     * - status 表示配置状态，1=启用，0=禁用。
     *
     * @return void
     */
    public function up()
    {
        Schema::create('role_data_scopes', function (Blueprint $table) {
            $table->id()->comment('主键 ID');
            $table->unsignedBigInteger('role_id')->comment('角色 ID，对应 roles.id');
            $table->string('scope_type', 30)->default('self')->comment('数据范围类型：all=全部数据，self=本人数据，created=本人创建，agent_tree=绑定代理树，custom_agents=指定代理集合，custom_users=指定用户集合');
            $table->json('agent_ids')->nullable()->comment('指定代理 ID 数组，仅 scope_type=custom_agents 时使用');
            $table->json('user_ids')->nullable()->comment('指定用户 ID 数组，仅 scope_type=custom_users 时使用');
            $table->tinyInteger('status')->default(1)->comment('状态：1=启用，0=禁用');
            $table->unsignedInteger('created_at')->default(0)->comment('创建时间，10 位 Unix 时间戳');
            $table->unsignedInteger('updated_at')->default(0)->comment('更新时间，10 位 Unix 时间戳');
            $table->unsignedInteger('deleted_at')->nullable()->comment('删除时间，10 位 Unix 时间戳');

            $table->unique('role_id');
            $table->index(['scope_type', 'status']);
        });
    }

    /**
     * 回滚迁移：删除 role_data_scopes 表。
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('role_data_scopes');
    }
}
