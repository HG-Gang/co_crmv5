<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/26
 * Time: 13:40
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建当前交易组配置表。
 *
 * 文件功能：
 *
 * group_configs 参与账户类型切换和配对组解析，必须使用 InnoDB 才能支持事务、行锁和测试回滚。
 */
class CreateGroupConfigsTable extends Migration
{
    /**
     * 创建交易组配置表及查询索引。
     *
     * @return void
     */
    public function up()
    {
        Schema::create('group_configs', function (Blueprint $blueprint) {
            $blueprint->engine = 'InnoDB';
            $blueprint->id()->comment('ID');
            $blueprint->integer('pair_id')->nullable()->comment('交易对ID | Pair ID');
            $blueprint->string('name', 255)->comment('名称 | Name');
            $blueprint->double('radix', 8, 2)->default(50)->comment('基数 | Radix');
            $blueprint->tinyInteger('category')->default(2)->comment('分类: 1=代理 2=用户 | Category: 1=agent 2=user');
            $blueprint->tinyInteger('has_commission')->default(0)->comment('是否有佣金 | Has commission');
            $blueprint->tinyInteger('is_enabled')->default(1)->comment('是否启用 | Enabled');
            $blueprint->tinyInteger('is_ecn')->default(0)->comment('是否ECN | ECN');
            $blueprint->tinyInteger('is_default')->default(0)->comment('是否默认 | Default');
            $blueprint->integer('created_by')->default(0)->comment('创建人 | Created by');
            $blueprint->integer('updated_by')->default(0)->comment('更新人 | Updated by');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间(10位时间戳) | Created at (10-digit timestamp)');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间(10位时间戳) | Updated at (10-digit timestamp)');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间(10位时间戳) | Deleted at (10-digit timestamp)');

            $blueprint->index('pair_id');
        });
    }

    /**
     * 删除交易组配置表。
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('group_configs');
    }
}
