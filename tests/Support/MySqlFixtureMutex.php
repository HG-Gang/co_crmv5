<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/29
 * Time: 13:48
 */

/**
 * MySqlFixtureMutex
 *
 * 文件功能：
 * - 在多个 PHPUnit 数据库会话之间串行化真实 MySQL 夹具：acquire 获取建议锁、release 幂等释放；瞬时释放失败重试一次，仍失败断开持锁连接并抛汇总异常，防止锁逃逸也不伪装成功。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 在多个 PHPUnit 数据库会话之间串行化真实 MySQL 夹具。
 *
 * 本建议锁与验证运行器外层使用的 PowerShell
 * Global\co_crmv5_phpunit_mysql 操作系统互斥锁彼此独立。
 */
final class MySqlFixtureMutex
{
    /**
     * 生成当前会话使用的 MySQL 建议锁名称。
     *
     * 逻辑说明：
     * - 默认名称为 co_crmv5_phpunit_mysql_fixture，与历史运行器保持一致。
     * - 通过环境变量 PHPUNIT_LOCK_SUFFIX 追加会话后缀，避免与
     *   外部校验运行器（co_crmv5_verify 库）的同一全局锁名互相阻塞。
     * - 锁名仅用于进程间协调，后缀不影响业务正确性。
     *
     * @return string 当前会话的锁名。
     */
    private static function lockName(): string
    {
        return 'co_crmv5_phpunit_mysql_fixture' . (string) getenv('PHPUNIT_LOCK_SUFFIX');
    }

    /**
     * 当前进程是否已持有建议锁。释放操作按它做幂等保护：未持锁时调用 release 不向 MySQL
     * 发送 RELEASE_LOCK，避免误释放其他会话的同名锁。
     *
     * @var bool
     */
    private $held = false;

    /**
     * 获取当前测试库会话的 MySQL 建议锁。
     *
     * @param int $timeoutSeconds 最长等待秒数，默认 20 秒。
     * @return void 成功持锁时无返回值。
     *
     * @throws RuntimeException 驱动不是 MySQL、超时或数据库拒绝获取锁时抛出。
     */
    public function acquire(int $timeoutSeconds = 20): void
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException('MySqlFixtureMutex requires the MySQL driver.');
        }

        // 等待上限设为 20 秒：执行环境会在进程约 30 秒无输出时中断命令，
        // 若继续等待 120 秒会导致测试进程被误杀并遗留持锁孤儿连接。
        $row = DB::selectOne(
            'SELECT GET_LOCK(?, ?) AS acquired',
            [self::lockName(), $timeoutSeconds],
            false
        );
        $values = $row ? array_values((array) $row) : [];
        if (!isset($values[0]) || (int) $values[0] !== 1) {
            throw new RuntimeException('Unable to acquire ' . self::lockName() . '.');
        }

        $this->held = true;
    }

    /**
     * 当前是否仍持有建议锁。
     *
     * @return bool acquire 成功且尚未成功释放/断开时为 true。
     */
    public function isHeld(): bool
    {
        return $this->held;
    }

    /**
     * 释放由当前数据库连接持有的建议锁。
     *
     * @return void 未持锁或释放成功时无返回值。
     *
     * @throws RuntimeException 数据库未确认释放成功时抛出。
     */
    public function release(): void
    {
        if (!$this->held) {
            return;
        }

        $row = DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [self::lockName()], false);
        $values = $row ? array_values((array) $row) : [];
        if (!isset($values[0]) || (int) $values[0] !== 1) {
            throw new RuntimeException('Unable to release ' . self::lockName() . '.');
        }

        $this->held = false;
    }

    /**
     * 对瞬时释放失败重试一次，仍失败时断开持锁连接。
     *
     * 断开连接后仍抛出汇总异常，既防止建议锁逃逸出夹具生命周期，
     * 也不把释放失败伪装成成功。
     *
     * @return void 首次或重试释放成功时无返回值。
     *
     * @throws RuntimeException 两次释放均失败，或后续断开连接也失败时抛出。
     */
    public function releaseWithDisconnectFallback(): void
    {
        if (!$this->held) {
            return;
        }

        $failures = [];
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $this->release();

                return;
            } catch (\Throwable $exception) {
                $failures[] = 'attempt ' . $attempt . ': ' . $exception->getMessage();
            }
        }

        // 事务击穿护栏：当默认连接仍处于挂起事务（如 DatabaseTransactions 包裹）时，
        // disconnect 会隐式回滚挂起事务且 Laravel 的 $transactions 计数器不重置，
        // 后续写入将在无事务连接上永久提交。此时不执行 disconnect，锁泄漏通过
        // 汇总异常失败关闭地暴露，由调用方显式处理，而不是静默击穿测试事务。
        if (DB::connection()->transactionLevel() > 0) {
            $failures[] = 'disconnect skipped: connection has an active transaction (level '
                . DB::connection()->transactionLevel() . ')';

            throw new RuntimeException(
                'MySQL fixture mutex release failed twice; disconnect skipped to protect the active transaction: '
                . implode(' | ', $failures)
            );
        }

        try {
            DB::disconnect();
            $this->held = false;
        } catch (\Throwable $exception) {
            $failures[] = 'disconnect: ' . $exception->getMessage();
            throw new RuntimeException(
                'MySQL fixture mutex release and disconnect failed: ' . implode(' | ', $failures),
                0,
                $exception
            );
        }

        throw new RuntimeException(
            'MySQL fixture mutex release failed twice; owning connection disconnected: '
            . implode(' | ', $failures)
        );
    }
}
