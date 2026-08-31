<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 创建用户收货地址表 user_addresses。
 *
 * 文件功能：
 * - 用户礼品发货收货地址：收件人、电话、省市区与详细地址。
 *
 * 字段语义：
 * - user_id 用户 ID；receiver 收件人；phone 联系电话；
 * - province/city/district 省市区；address 详细地址；is_default 是否默认地址。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserAddressesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_addresses', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->integer('user_id')->comment('用户ID | User ID');
            $blueprint->string('recipient_name', 500)->default('')->comment('收件人姓名 | Recipient name');
            $blueprint->string('recipient_phone', 50)->default('')->comment('收件人电话 | Recipient phone');
            $blueprint->string('recipient_address', 5000)->default('')->comment('收件人地址 | Recipient address');
            $blueprint->tinyInteger('is_default')->default(0)->comment('是否默认 | Is default');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间(10位时间戳) | Created at (10-digit timestamp)');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间(10位时间戳) | Updated at (10-digit timestamp)');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间(10位时间戳) | Deleted at (10-digit timestamp)');

            $blueprint->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_addresses');
    }
}
