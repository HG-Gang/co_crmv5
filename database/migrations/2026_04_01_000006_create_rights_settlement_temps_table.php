<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRightsSettlementTempsTable extends Migration
{
    public function up()
    {
        Schema::create('rights_settlement_temps', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->unsignedInteger('user_id')->comment('用户ID | User ID');
            $blueprint->decimal('amount', 20, 8)->comment('临时金额 | Temporary amount');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间 | Created at');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间 | Updated at');

            $blueprint->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('rights_settlement_temps');
    }
}
