<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:31
 */

/**
 * 为 trans_apply_logs 补充旧前台申请字段。
 *
 * 文件功能：
 * - 增加旧前台转账申请兼容字段（申请来源、旧申请表 ID、渠道与扩展信息），
 *   保证旧数据迁移后前端展示与查询口径一致。
 *
 * 字段语义：
 * - 新增字段均为可空/带默认值，不破坏现有数据；回滚时仅删除本迁移新增字段。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOldFrontApplyFieldsToTransApplyLogsTable extends Migration
{
    /**
     * Add front transfer-apply fields restored from the old CRM database.
     *
     * hank_zl_data.trans_apply_log kept the submitted reason separately from the
     * reviewer reject reason.  co_crmv5 already has reject_reason, so this
     * migration adds apply_reason and origin_group_id for a complete audit trail.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('trans_apply_logs', function (Blueprint $blueprint) {
            if (!Schema::hasColumn('trans_apply_logs', 'origin_group_id')) {
                $blueprint->integer('origin_group_id')->default(0)->after('user_id')->comment('Original group ID before transfer apply');
            }

            if (!Schema::hasColumn('trans_apply_logs', 'apply_reason')) {
                $blueprint->string('apply_reason', 500)->default('')->after('status')->comment('Application reason submitted by agent');
            }
        });
    }

    /**
     * Remove the restored front transfer-apply audit fields.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('trans_apply_logs', function (Blueprint $blueprint) {
            if (Schema::hasColumn('trans_apply_logs', 'origin_group_id')) {
                $blueprint->dropColumn('origin_group_id');
            }

            if (Schema::hasColumn('trans_apply_logs', 'apply_reason')) {
                $blueprint->dropColumn('apply_reason');
            }
        });
    }
}
