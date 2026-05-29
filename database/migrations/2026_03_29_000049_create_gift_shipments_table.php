<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGiftShipmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gift_shipments', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->integer('user_id')->comment('用户ID | User ID');
            $blueprint->bigInteger('address_id')->default(0)->comment('地址ID | Address ID');
            $blueprint->string('recipient_name', 100)->default('')->comment('收件人姓名 | Recipient name');
            $blueprint->string('recipient_phone', 50)->default('')->comment('收件人电话 | Recipient phone');
            $blueprint->string('recipient_address', 500)->default('')->comment('收件人地址 | Recipient address');
            $blueprint->string('sender_name', 100)->default('')->comment('发件人姓名 | Sender name');
            $blueprint->string('tracking_number', 100)->default('')->comment('快递单号 | Tracking number');
            $blueprint->string('gift_name', 200)->default('')->comment('礼品名称 | Gift name');
            $blueprint->integer('gift_quantity')->default(1)->comment('礼品数量 | Quantity');
            $blueprint->tinyInteger('status')->default(0)->comment('状态: 0=待处理 1=已发货 2=运输中 3=已送达 4=异常 | Status: 0=pending 1=shipped 2=in_transit 3=delivered 4=error');
            $blueprint->string('remark', 500)->default('')->comment('备注 | Remark');
            $blueprint->integer('admin_id')->default(0)->comment('管理员ID | Admin ID');
            $blueprint->dateTime('shipped_at')->nullable()->comment('发货时间 | Shipped at');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间(10位时间戳) | Created at (10-digit timestamp)');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间(10位时间戳) | Updated at (10-digit timestamp)');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间(10位时间戳) | Deleted at (10-digit timestamp)');

            $blueprint->index('user_id');
            $blueprint->index('tracking_number');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gift_shipments');
    }
}
