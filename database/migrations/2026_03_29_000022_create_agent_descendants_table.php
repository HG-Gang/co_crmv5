<?php

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
