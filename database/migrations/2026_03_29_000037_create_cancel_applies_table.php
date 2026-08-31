<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/29
 * Time: 00:20
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建销户申请表。
 *
 * 文件功能：
 * - 保存普通用户和代理商提交的销户原因及后台审核结果。
 * - 使用 InnoDB，使申请状态能与登录状态、MT4 本地镜像和操作日志组成同一事务。
 *
 * 返回结果：
 * - up 创建 cancel_applies，status=0 表示待审、1 表示通过、-1 表示拒绝。
 * - down 删除该表，仅用于完整回滚初始结构。
 */
class CreateCancelAppliesTable extends Migration
{
    /**
     * 创建支持审核事务与行锁的销户申请表。
     *
     * @return void 建表成功时无返回值，数据库 DDL 失败时由迁移框架抛出异常。
     */
    public function up()
    {
        Schema::create('cancel_applies', function (Blueprint $blueprint) {
            // InnoDB 保证后台审核异常时可以回滚申请状态，并支持 lockForUpdate 串行化并发审核。
            $blueprint->engine = 'InnoDB';
            $blueprint->id()->comment('ID');
            $blueprint->integer('user_id')->comment('用户ID | User ID');
            $blueprint->string('user_name', 100)->comment('用户名 | User name');
            $blueprint->tinyInteger('status')->default(0)->comment('状态: 0=待处理 1=通过 -1=拒绝 | Status: 0=pending 1=approved -1=rejected');
            $blueprint->string('reject_reason', 500)->default('')->comment('拒绝原因 | Reject reason');
            $blueprint->string('created_by', 100)->default('')->comment('创建人 | Created by');
            $blueprint->string('updated_by', 100)->default('')->comment('更新人 | Updated by');
            
            $blueprint->unsignedInteger('created_at')->default(0)->comment('创建时间(10位时间戳) | Created at (10-digit timestamp)');
            $blueprint->unsignedInteger('updated_at')->default(0)->comment('更新时间(10位时间戳) | Updated at (10-digit timestamp)');
            $blueprint->unsignedInteger('deleted_at')->nullable()->comment('删除时间(10位时间戳) | Deleted at (10-digit timestamp)');
        });
    }

    /**
     * 回滚初始建表操作。
     *
     * @return void 表存在时删除，不存在时不执行修改。
     */
    public function down()
    {
        Schema::dropIfExists('cancel_applies');
    }
}
