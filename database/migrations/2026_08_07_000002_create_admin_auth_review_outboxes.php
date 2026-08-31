<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/07
 * Time: 18:41
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建管理员实名审核出箱表 admin_auth_review_outboxes。
 *
 * 文件功能：
 * - 为审核意图提供持久化出箱：加密载荷与摘要、状态机字段、重试计数、认领锁与完成时间。
 * - active_user_id 可空唯一键充当跨节点部分唯一约束：生效状态保留 user_id，终态将其置空，
 *   保证同一用户同时至多一条生效审核流程。
 */
class CreateAdminAuthReviewOutboxes extends Migration
{
    public function up(): void
    {
        Schema::create('admin_auth_review_outboxes', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('active_user_id')->nullable();
            $table->unsignedBigInteger('admin_id');
            $table->string('admin_name', 100);
            $table->string('request_ip', 45)->default('');
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('payload_ciphertext')->nullable();
            $table->char('payload_hash', 64)->nullable();
            $table->char('auth_snapshot_hash', 64)->nullable();
            $table->unsignedInteger('available_at')->nullable();
            $table->unsignedInteger('locked_at')->nullable();
            $table->unsignedInteger('processed_at')->nullable();
            $table->string('last_error_code', 100)->nullable();
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedInteger('updated_at')->nullable();
            $table->unsignedInteger('deleted_at')->nullable();

            $table->unique('active_user_id', 'admin_auth_review_outboxes_active_user_unique');
            $table->index(['status', 'available_at'], 'admin_auth_review_outboxes_ready_index');
            $table->index(['status', 'locked_at'], 'admin_auth_review_outboxes_stale_index');
            $table->index('user_id', 'admin_auth_review_outboxes_user_index');
        });
    }

    public function down(): void
    {
        // Authentication review intents are audit records and are intentionally retained.
    }
}
