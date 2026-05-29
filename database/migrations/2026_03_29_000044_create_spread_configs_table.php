<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSpreadConfigsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('spread_configs', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->double('spread')->comment('点差 | Spread');
            $blueprint->integer('agent_group_id')->comment('代理组ID | Agent group ID');
            $blueprint->double('spread_ratio')->comment('点差比例 | Spread ratio');
            $blueprint->tinyInteger('status')->default(1)->comment('状态 | Status');
            
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
        Schema::dropIfExists('spread_configs');
    }
}
