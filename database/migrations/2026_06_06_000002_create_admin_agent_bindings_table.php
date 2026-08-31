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
 * 创建管理员与代理绑定关系表。
 *
 * 文件功能：
 * - 当角色数据范围为 agent_tree 或 custom_agents 时，需要知道管理员被授权管理哪些代理节点。
 * - 控制器不直接维护代理范围 SQL，统一交给 AdminDataScopeService 根据本表和 agent_descendants 表计算。
 * - 本表只保存管理员与代理节点的绑定关系，具体菜单和按钮权限仍由 permissions 与 role_permissions 控制。
 */
class CreateAdminAgentBindingsTable extends Migration
{
    /**
     * 执行迁移：创建 admin_agent_bindings 表。
     *
     * 字段参数说明：
     * - admin_id 表示后台管理员 ID，对应 admins.id。
     * - agent_id 表示代理业务用户 ID，对应 user_infos.user_id。
     * - binding_type 表示绑定类型：primary=主绑定，extra=额外绑定。
     * - status 表示绑定状态，1=启用，0=禁用。
     *
     * @return void
     */
    public function up()
    {
        Schema::create('admin_agent_bindings', function (Blueprint $table) {
            $table->id()->comment('主键 ID');
            $table->unsignedBigInteger('admin_id')->comment('后台管理员 ID，对应 admins.id');
            $table->integer('agent_id')->comment('代理业务用户 ID，对应 user_infos.user_id');
            $table->string('binding_type', 20)->default('primary')->comment('绑定类型：primary=主绑定，extra=额外绑定');
            $table->tinyInteger('status')->default(1)->comment('状态：1=启用，0=禁用');
            $table->unsignedInteger('created_at')->default(0)->comment('创建时间，10 位 Unix 时间戳');
            $table->unsignedInteger('updated_at')->default(0)->comment('更新时间，10 位 Unix 时间戳');
            $table->unsignedInteger('deleted_at')->nullable()->comment('删除时间，10 位 Unix 时间戳');

            $table->index(['admin_id', 'status']);
            $table->index(['agent_id', 'status']);
        });
    }

    /**
     * 回滚迁移：删除 admin_agent_bindings 表。
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('admin_agent_bindings');
    }
}
