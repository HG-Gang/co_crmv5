<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOffwebFeedbacksTable extends Migration
{
    public function up()
    {
        Schema::create('offweb_feedbacks', function (Blueprint $blueprint) {
            $blueprint->id()->comment('ID');
            $blueprint->unsignedInteger('user_id')->nullable()->comment('用户ID | User ID');
            $blueprint->string('email', 100)->comment('联系邮箱 | Email');
            $blueprint->string('title', 255)->comment('标题 | Title');
            $blueprint->text('content')->comment('反馈内容 | Content');
            $blueprint->string('reply', 255)->nullable()->comment('回复内容 | Reply');
            $blueprint->tinyInteger('status')->default(0)->comment('状态: 0=未处理, 1=已回复 | Status');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间 | Created at');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间 | Updated at');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间 | Deleted at');

            $blueprint->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('offweb_feedbacks');
    }
}
