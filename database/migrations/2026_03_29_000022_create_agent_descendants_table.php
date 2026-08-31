<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:30
 */

/**
 * 创建代理-下级关系表 agent_descendants。
 *
 * 文件功能：
 * - 维护代理与下级（代理/客户）的树形关系，支持按深度查询团队。
 *
 * 字段语义：
 * - agent_id 代理 ID；descendant_id 下级 ID；descendant_type 下级类型（1=代理 2=客户）；
 * - is_direct 是否直属（1=直属 0=间接）；depth 层级深度（1 起，直属为 1）。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAgentDescendantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('agent_descendants', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->integer('agent_id')->comment('代理ID | Agent ID');
            $blueprint->integer('descendant_id')->comment('下级ID | Descendant ID');
            $blueprint->tinyInteger('descendant_type')->comment('下级类型: 1=代理 2=客户 | Descendant type: 1=agent 2=customer');
            $blueprint->tinyInteger('is_direct')->default(0)->comment('是否直属 | Direct');
            $blueprint->integer('depth')->default(1)->comment('深度 | Depth');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间(10位时间戳) | Created at (10-digit timestamp)');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间(10位时间戳) | Updated at (10-digit timestamp)');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间(10位时间戳) | Deleted at (10-digit timestamp)');

            $blueprint->unique(['agent_id', 'descendant_id']);
            $blueprint->index('agent_id');
            $blueprint->index('descendant_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('agent_descendants');
    }
}
