<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMt4UsersTable extends Migration
{
    public function up()
    {
        Schema::create('mt4_users', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->integer('login')->unique()->comment('MT4账号 | MT4 Login');
            $blueprint->string('name', 100)->comment('姓名 | Name');
            $blueprint->string('group', 100)->comment('MT4分组 | MT4 Group');
            $blueprint->decimal('balance', 20, 2)->default(0)->comment('余额 | Balance');
            $blueprint->decimal('equity', 20, 2)->default(0)->comment('净值 | Equity');
            $blueprint->decimal('margin', 20, 2)->default(0)->comment('保证金 | Margin');
            $blueprint->decimal('margin_free', 20, 2)->default(0)->comment('可用保证金 | Free margin');
            $blueprint->integer('leverage')->default(100)->comment('杠杆 | Leverage');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间 | Created at');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间 | Updated at');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间 | Deleted at');

            $blueprint->index('login');
        });
    }

    public function down()
    {
        Schema::dropIfExists('mt4_users');
    }
}
