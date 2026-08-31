<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/25
 * Time: 18:03
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 创建旧 comm_summary 返佣出账审计表。
 *
 * 文件功能：
 * - 为每个“源交易 + 收款代理”保存唯一的 MT4 返佣入金状态。
 * - 在外部 MT4 调用前持久化意图，在调用后保存成功、可重试、拒绝或结果不确定状态。
 * - 用数据库唯一索引替代旧项目仅靠查询 MT4 评论字段的幂等判断。
 *
 * 适用场景：
 * - 旧路由 GET /user/position/comm_summary 的实时返佣兼容入口。
 * - 定时任务或人工补偿需要重新扫描未结算的已平仓交易。
 *
 * 返回结果：
 * - 建表成功时提供可锁定、可审计、可重试的返佣出账存储。
 * - 已存在但结构不完整时抛出异常，阻止在未知表结构上执行金融写入。
 */
class CreateCommissionRebatePayoutsTable extends Migration
{
    /**
     * 创建返佣出账表并建立金融幂等索引。
     *
     * @return void 新库会创建完整表结构；已有库只校验必需字段，避免误修改非空审计数据。
     *
     * @throws \RuntimeException 当已有表缺少闭环所需字段时抛出异常。
     */
    public function up(): void
    {
        if (Schema::hasTable('commission_rebate_payouts')) {
            $this->assertExistingTableContract();

            return;
        }

        Schema::create('commission_rebate_payouts', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id')->comment('返佣出账主键');
            $table->unsignedBigInteger('source_trade_id')->comment('源 user_trades 主键');
            $table->unsignedInteger('source_ticket')->comment('旧 MT4 源交易单号');
            $table->unsignedBigInteger('trader_user_id')->comment('产生交易的客户业务用户 ID');
            $table->unsignedBigInteger('agent_id')->comment('获得返佣的代理业务用户 ID');
            $table->unsignedBigInteger('parent_id')->default(0)->comment('收款代理的上级代理业务用户 ID');
            $table->unsignedInteger('volume')->comment('MT4 原始成交量，200 表示 2 手');
            $table->decimal('rate_difference', 12, 4)->comment('当前代理与直属下级的返佣比例差');
            $table->decimal('group_radix', 18, 8)->comment('收款代理组的旧返佣基数');
            $table->decimal('amount', 18, 2)->comment('本次应入账返佣金额');
            $table->string('comment', 100)->comment('写入 MT4 的旧 DBCN 备注');
            $table->string('status', 30)->default('pending')->comment('pending/processing/settled/retryable/rejected/unknown/not_payable');
            $table->unsignedInteger('attempts')->default(0)->comment('实际向 MT4 发起入金的次数');
            $table->unsignedInteger('available_at')->nullable()->comment('下次允许重试的 Unix 时间戳');
            $table->unsignedInteger('locked_at')->nullable()->comment('当前处理声明的 Unix 时间戳');
            $table->unsignedInteger('processed_at')->nullable()->comment('终态处理完成的 Unix 时间戳');
            $table->string('provider_reference', 100)->nullable()->comment('MT4 返回的入金票据号');
            $table->string('last_error_code', 100)->nullable()->comment('最近一次 MT4 或本地失败代码');
            $table->unsignedInteger('created_at')->nullable()->comment('创建时间 Unix 时间戳');
            $table->unsignedInteger('updated_at')->nullable()->comment('更新时间 Unix 时间戳');
            $table->unsignedInteger('deleted_at')->nullable()->comment('软删除时间 Unix 时间戳');

            // 同一源交易向同一代理最多存在一笔返佣出账，保证并发触发时不会二次入金。
            $table->unique(['source_trade_id', 'agent_id'], 'commission_rebate_payouts_trade_agent_unique');
            // 定时扫描按状态和可执行时间查找重试记录，索引避免全表扫描。
            $table->index(['status', 'available_at'], 'commission_rebate_payouts_ready_index');
            $table->index('source_ticket', 'commission_rebate_payouts_ticket_index');
        });
    }

    /**
     * 校验已有返佣出账表的最小金融字段合同。
     *
     * @return void 字段齐全时不返回值。
     *
     * @throws \RuntimeException 缺失任何状态、金额、来源或幂等字段时抛出异常。
     */
    private function assertExistingTableContract(): void
    {
        $requiredColumns = [
            'id', 'source_trade_id', 'source_ticket', 'trader_user_id', 'agent_id', 'parent_id',
            'volume', 'rate_difference', 'group_radix', 'amount', 'comment', 'status', 'attempts',
            'available_at', 'locked_at', 'processed_at', 'provider_reference', 'last_error_code',
            'created_at', 'updated_at', 'deleted_at',
        ];
        $missing = array_values(array_filter($requiredColumns, static function (string $column): bool {
            return !Schema::hasColumn('commission_rebate_payouts', $column);
        }));
        if ($missing !== []) {
            throw new \RuntimeException(
                'Cannot safely use existing commission_rebate_payouts; missing columns: ' . implode(', ', $missing)
            );
        }
    }

    /**
     * 返佣出账是资金审计记录，回滚不删除已经生成的记录。
     *
     * @return void 保持审计表和外部 MT4 入金追踪数据不被 migration rollback 破坏。
     */
    public function down(): void
    {
        // 金融审计数据不可逆删除，避免回滚后失去已经发生的 MT4 入账追踪证据。
    }
}
