<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 创建 MT4 用户表 mt4_users。
 *
 * 文件功能：
 * - MT4 账户信息镜像：登录号、分组、杠杆、资金状态与同步状态。
 *
 * 字段语义：
 * - login MT4 登录号（唯一）；group MT4 分组；leverage 杠杆；
 * - balance/equity/margin 余额/净值/保证金；is_synced 是否已同步；sync_at 同步时间。
 */

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
