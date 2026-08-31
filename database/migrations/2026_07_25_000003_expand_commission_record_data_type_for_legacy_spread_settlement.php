<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/25
 * Time: 18:32
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 扩容返佣账本的算法来源字段。
 *
 * 文件功能：
 * - 保留旧手续费返佣的 data_type 存储方式。
 * - 允许旧点差返佣使用完整的 legacy_spread_comm_summary 审计标识。
 * - 修复 MT4 已成功后因本地字段截断无法落账而产生 unknown 状态的闭环断点。
 *
 * 执行结果：
 * - 成功后 commission_records.data_type 可保存最长 50 个字符的算法类型。
 * - 表或字段不存在时不做猜测性建表，保持现有迁移链对表结构的责任边界。
 */
class ExpandCommissionRecordDataTypeForLegacySpreadSettlement extends Migration
{
    /**
     * 扩容账本算法来源字段。
     *
     * @return void 已存在的短字段会升级为 varchar(50)，原有账本数据和默认值保持不变。
     */
    public function up(): void
    {
        if (!Schema::hasTable('commission_records') || !Schema::hasColumn('commission_records', 'data_type')) {
            return;
        }

        Schema::table('commission_records', function (Blueprint $table): void {
            // 算法类型是资金审计依据，必须完整保存，不能通过截断或缩写掩盖来源。
            $table->string('data_type', 50)
                ->default('mainData')
                ->comment('返佣或结算数据来源类型')
                ->change();
        });
    }

    /**
     * 不缩回已扩容的审计字段。
     *
     * @return void 回滚不能截断历史算法标识，避免已经写入的点差返佣账本无法读取。
     */
    public function down(): void
    {
        // 金融审计字段只允许向兼容方向扩展，禁止通过回滚截断有效历史记录。
    }
}
