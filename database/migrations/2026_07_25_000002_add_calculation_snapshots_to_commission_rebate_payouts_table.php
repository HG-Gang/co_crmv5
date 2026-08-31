<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/25
 * Time: 18:24
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 为返佣出账审计表补充算法计算快照。
 *
 * 文件功能：
 * - 区分旧手续费返佣与旧点差返佣，避免同一审计表中的金额来源不可追溯。
 * - 保存旧点差算法实际使用的品种点差和特殊品种手数倍率。
 *
 * 适用场景：
 * - GET /user/position/comm_summary 使用 legacy_comm_summary。
 * - GET /user/position/comm_summaryv2 使用 legacy_spread_comm_summary。
 *
 * 返回结果：
 * - 升级成功后，每条出账均能还原具体计算分支。
 * - 表不存在时不创建残缺表，由首个建表迁移负责完整结构。
 */
class AddCalculationSnapshotsToCommissionRebatePayoutsTable extends Migration
{
    /**
     * 增加不可缺失的返佣算法快照字段。
     *
     * @return void 已有列不会重复添加；旧出账以默认手续费算法和默认倍率保留原语义。
     */
    public function up(): void
    {
        if (!Schema::hasTable('commission_rebate_payouts')) {
            return;
        }

        Schema::table('commission_rebate_payouts', function (Blueprint $table): void {
            if (!Schema::hasColumn('commission_rebate_payouts', 'calculation_type')) {
                $table->string('calculation_type', 50)
                    ->default('legacy_comm_summary')
                    ->comment('legacy_comm_summary/legacy_spread_comm_summary')
                    ->after('comment');
            }
            if (!Schema::hasColumn('commission_rebate_payouts', 'spread')) {
                $table->decimal('spread', 12, 4)
                    ->default(0)
                    ->comment('旧点差返佣使用的整数点差快照')
                    ->after('calculation_type');
            }
            if (!Schema::hasColumn('commission_rebate_payouts', 'volume_multiplier')) {
                $table->decimal('volume_multiplier', 12, 4)
                    ->default(1)
                    ->comment('旧特殊点差品种使用的手数倍率快照')
                    ->after('spread');
            }
        });
    }

    /**
     * 保留已经发生的资金算法审计快照。
     *
     * @return void 回滚不会删除可能对应已完成 MT4 入金的字段。
     */
    public function down(): void
    {
        // 金融审计字段不可逆删除，防止回滚后无法解释历史返佣金额。
    }
}
