<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIdSequencesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('id_sequences', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->string('type', 50)->unique()->comment('类型: agent or customer | Type: agent or customer');
            $blueprint->bigInteger('current_value')->comment('当前值 | Current value');
            $blueprint->string('prefix', 10)->default('')->comment('前缀 | Prefix');
            $blueprint->integer('step')->default(1)->comment('步长 | Step');
            
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
        Schema::dropIfExists('id_sequences');
    }
}
