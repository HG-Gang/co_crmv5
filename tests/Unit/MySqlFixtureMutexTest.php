<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/29
 * Time: 13:48
 */

/**
 * MySqlFixtureMutexTest
 *
 * 文件功能：
 * - 验证 MySqlFixtureMutex 释放回退：释放失败保持锁状态供二次释放、两次失败后断开连接回退、事务活跃时跳过断开。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Tests\Support\MySqlFixtureMutex;
use Tests\TestCase;

final class MySqlFixtureMutexTest extends TestCase
{
    /**
     * 生成与 MySqlFixtureMutex 完全一致的当前会话锁名。
     *
     * 逻辑说明：
     * - 生产锁名由固定前缀 + PHPUNIT_LOCK_SUFFIX 组成；
     * - 单测断言必须使用同一公式，避免环境变量开启时断言字符串失配。
     *
     * @return string 当前会话的互斥锁名。
     */
    private function expectedLockName(): string
    {
        return 'co_crmv5_phpunit_mysql_fixture' . (string) getenv('PHPUNIT_LOCK_SUFFIX');
    }

    public function test_failed_release_keeps_lock_state_for_a_second_release_attempt(): void
    {
        $lockName = $this->expectedLockName();
        DB::shouldReceive('getDriverName')->once()->andReturn('mysql');
        DB::shouldReceive('selectOne')
            ->once()
            ->with('SELECT GET_LOCK(?, ?) AS acquired', [$lockName, 0], false)
            ->andReturn((object) ['acquired' => 1]);
        DB::shouldReceive('selectOne')
            ->once()
            ->with('SELECT RELEASE_LOCK(?) AS released', [$lockName], false)
            ->andReturn((object) ['released' => 0]);
        DB::shouldReceive('selectOne')
            ->once()
            ->with('SELECT RELEASE_LOCK(?) AS released', [$lockName], false)
            ->andReturn((object) ['released' => 1]);

        $mutex = new MySqlFixtureMutex();
        $mutex->acquire(0);
        try {
            $mutex->release();
            $this->fail('Expected the first release to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('release', strtolower($exception->getMessage()));
        }

        $mutex->release();
    }

    public function test_release_fallback_disconnects_after_two_failed_attempts(): void
    {
        $lockName = $this->expectedLockName();
        DB::shouldReceive('getDriverName')->once()->andReturn('mysql');
        DB::shouldReceive('selectOne')
            ->once()
            ->with('SELECT GET_LOCK(?, ?) AS acquired', [$lockName, 0], false)
            ->andReturn((object) ['acquired' => 1]);
        DB::shouldReceive('selectOne')
            ->twice()
            ->with('SELECT RELEASE_LOCK(?) AS released', [$lockName], false)
            ->andReturn((object) ['released' => 0]);
        // 事务击穿护栏：disconnect 前必须确认默认连接无挂起事务。
        DB::shouldReceive('connection')->once()->andReturnSelf();
        DB::shouldReceive('transactionLevel')->once()->andReturn(0);
        DB::shouldReceive('disconnect')->once();

        $mutex = new MySqlFixtureMutex();
        $mutex->acquire(0);

        try {
            $mutex->releaseWithDisconnectFallback();
            $this->fail('Expected the disconnect fallback to report failed releases.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('disconnected', $exception->getMessage());
        }

        $mutex->release();
    }

    public function test_release_fallback_skips_disconnect_while_transaction_is_active(): void
    {
        $lockName = $this->expectedLockName();
        DB::shouldReceive('getDriverName')->once()->andReturn('mysql');
        DB::shouldReceive('selectOne')
            ->once()
            ->with('SELECT GET_LOCK(?, ?) AS acquired', [$lockName, 0], false)
            ->andReturn((object) ['acquired' => 1]);
        DB::shouldReceive('selectOne')
            ->twice()
            ->with('SELECT RELEASE_LOCK(?) AS released', [$lockName], false)
            ->andReturn((object) ['released' => 0]);
        // 挂起事务存在时禁止 disconnect，改为失败关闭地抛出护栏异常。
        DB::shouldReceive('connection')->twice()->andReturnSelf();
        DB::shouldReceive('transactionLevel')->twice()->andReturn(2);
        DB::shouldReceive('disconnect')->never();

        $mutex = new MySqlFixtureMutex();
        $mutex->acquire(0);

        try {
            $mutex->releaseWithDisconnectFallback();
            $this->fail('Expected the transaction guard to skip the disconnect fallback.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('disconnect skipped', $exception->getMessage());
            $this->assertStringContainsString('active transaction', $exception->getMessage());
        }

        // 护栏跳过 disconnect 时锁仍处于持有态（失败关闭语义），不得静默伪装为已释放。
        $this->assertTrue($mutex->isHeld());
    }
}
