<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/01
 * Time: 16:20
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\FullResetAndMigrateDatabase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 全量迁移正确性断言闭环测试。
 *
 * 文件功能：
 * - 对 FullResetAndMigrateDatabase::integrityChecks() 的每一条必查项做红绿双向验证：
 *   构造「迁移正确」的数据形态断言通过，构造「已实际发生过的缺陷」数据形态断言失败。
 *
 * 适用场景：
 * - 需求 5 全量重置并从 hank_zl_data 迁移前后，保证迁移脚本的自检判据本身有效。
 *
 * 为什么必须有这个测试：
 * - 断言是迁移唯一的自动化防线，而未经验证的防线会给出虚假的安全感：
 *   若判定闭包写错（例如把 count>0 写成 count>=0），迁移会在整列丢失时照样声称成功。
 * - 2026-09-01 实测的 mt4_trades.comment 整列丢失正是行数校验放过的缺陷，
 *   本测试用同形态数据锁定该判据必须变红。
 *
 * 返回值：
 * - 所有测试方法无返回值，断言失败即测试失败。
 *
 * 异常或失败场景：
 * - 判据无法区分正确数据与缺陷数据时用例失败。
 */
class FullResetMigrationIntegrityChecksClosureModuleTest extends TestCase
{
    // 判据是全表聚合（min/max/count/逐行 Hash::check），必须清空整表才能构造出确定的
    // 「迁移后」数据形态；用事务包裹是本项目 331 个测试文件的统一约定，
    // 缺它会把 co_crmv5_test 的种子数据真删掉，破坏全量串行中依赖这两张表的其它用例。
    use DatabaseTransactions;

    /**
     * 按名称取出单条必查项，避免用下标耦合清单顺序。
     *
     * @param string $name 必查项名称。
     * @return array{name: string, actual: callable, assert: callable, expect: string} 必查项定义。
     */
    private function check(string $name): array
    {
        $command = new FullResetAndMigrateDatabase();
        $reflection = new \ReflectionMethod($command, 'integrityChecks');
        $reflection->setAccessible(true);

        foreach ($reflection->invoke($command) as $check) {
            if ($check['name'] === $name) {
                return $check;
            }
        }

        $this->fail('必查项清单中不存在：' . $name);
    }

    /**
     * 执行一条必查项，返回判定结果。
     *
     * @param string $name 必查项名称。
     * @return bool 判定是否通过。
     */
    private function runCheck(string $name): bool
    {
        $check = $this->check($name);

        return (bool) ($check['assert'])(($check['actual'])());
    }

    /**
     * comment 整列丢失必须被判红：这是 2026-09-01 实测缺陷的原始形态
     * （行数与旧库完全一致，comment 全空），行数校验放过，本判据必须拦下。
     *
     * @return void 无返回值。
     */
    public function test_comment_column_loss_is_detected(): void
    {
        // cmd=6 是 MT4 余额操作（返佣入账走这一类），symbol/volume/open_price/profit/open_time
        // 是建表时 NOT NULL 且无默认值的列，必须显式给值，否则插入本身就失败、
        // 测试会以「插入报错」而不是「判据判红」的方式失败，掩盖真正要验的东西。
        $row = [
            'login' => 700001,
            'cmd' => 6,
            'symbol' => '',
            'volume' => 0,
            'open_price' => 0,
            'profit' => 1.5,
            'open_time' => 1756700000,
        ];

        DB::table('mt4_trades')->delete();
        DB::table('mt4_trades')->insert([
            $row + ['ticket' => 900001, 'comment' => ''],
            $row + ['ticket' => 900002, 'comment' => ''],
        ]);

        $this->assertFalse(
            $this->runCheck('mt4_trades 返佣 comment 未丢失'),
            'comment 全空时判据必须失败，否则整列丢失会被当成迁移成功'
        );

        DB::table('mt4_trades')->where('ticket', 900001)->update(['comment' => 'DBCN-700001']);

        $this->assertTrue(
            $this->runCheck('mt4_trades 返佣 comment 未丢失'),
            'comment 含 DBCN 时判据必须通过，否则判据永远为红、失去区分力'
        );
    }

    /**
     * 密码统一 123456（需求 6）：混入任一非 123456 账号必须判红。
     *
     * @return void 无返回值。
     */
    public function test_password_uniformity_is_detected(): void
    {
        DB::table('user_logins')->delete();
        DB::table('user_logins')->insert([
            [
                'user_id' => 1001,
                'email' => 'agent1001@example.com',
                'password' => Hash::make('123456'),
                'account_type' => 1,
            ],
            [
                'user_id' => 600001,
                'email' => 'customer600001@example.com',
                'password' => Hash::make('abc123'),
                'account_type' => 2,
            ],
        ]);

        $this->assertFalse(
            $this->runCheck('前台密码统一为 123456'),
            '存在非 123456 密码时判据必须失败'
        );

        DB::table('user_logins')->where('user_id', 600001)
            ->update(['password' => Hash::make('123456')]);

        $this->assertTrue(
            $this->runCheck('前台密码统一为 123456'),
            '全部为 123456 时判据必须通过'
        );
    }

    /**
     * 起始 ID 与区间不重叠（需求 6）：代理起 1001、客户起 600001，且两段不交叉。
     *
     * @return void 无返回值。
     */
    public function test_user_id_ranges_are_detected(): void
    {
        DB::table('user_logins')->delete();
        DB::table('user_logins')->insert([
            [
                'user_id' => 1,
                'email' => 'agent1@example.com',
                'password' => Hash::make('123456'),
                'account_type' => 1,
            ],
            [
                'user_id' => 2,
                'email' => 'customer2@example.com',
                'password' => Hash::make('123456'),
                'account_type' => 2,
            ],
        ]);

        $this->assertFalse($this->runCheck('代理商 user_id 起始 1001'), '代理起始非 1001 必须判红');
        $this->assertFalse($this->runCheck('普通客户 user_id 起始 600001'), '客户起始非 600001 必须判红');

        DB::table('user_logins')->where('email', 'agent1@example.com')->update(['user_id' => 1001]);
        DB::table('user_logins')->where('email', 'customer2@example.com')->update(['user_id' => 600001]);

        $this->assertTrue($this->runCheck('代理商 user_id 起始 1001'), '代理起始为 1001 必须判绿');
        $this->assertTrue($this->runCheck('普通客户 user_id 起始 600001'), '客户起始为 600001 必须判绿');
        $this->assertTrue($this->runCheck('代理与客户 user_id 区间不重叠'), '两段不交叉必须判绿');
    }

    /**
     * 区间重叠必须判红：代理 ID 越过客户起始段时，业务 ID 无法再区分身份。
     *
     * @return void 无返回值。
     */
    public function test_overlapping_user_id_ranges_are_detected(): void
    {
        DB::table('user_logins')->delete();
        DB::table('user_logins')->insert([
            [
                'user_id' => 1001,
                'email' => 'agent1001@example.com',
                'password' => Hash::make('123456'),
                'account_type' => 1,
            ],
            [
                'user_id' => 600002,
                'email' => 'agent600002@example.com',
                'password' => Hash::make('123456'),
                'account_type' => 1,
            ],
            [
                'user_id' => 600001,
                'email' => 'customer600001@example.com',
                'password' => Hash::make('123456'),
                'account_type' => 2,
            ],
        ]);

        $this->assertFalse(
            $this->runCheck('代理与客户 user_id 区间不重叠'),
            '代理最大 user_id 越过客户最小 user_id 时必须判红'
        );
    }

    /**
     * 层级断链必须判红：这是代理商重编号漏改 parent_id 的确切形态。
     *
     * 为什么单独立一条：边界值断言此时全部为绿（MIN 是 1001、区间不重叠），
     * 唯独下级指向了不存在的旧号。只看边界值的迁移会声称成功。
     *
     * @return void 无返回值。
     */
    public function test_orphan_parent_reference_is_detected(): void
    {
        // login_id 是建表时 NOT NULL 且无默认值的列，必须显式给值，否则插入本身失败、
        // 测试会以「插入报错」而不是「判据判红」的方式失败，掩盖真正要验的东西。
        DB::table('user_infos')->delete();
        DB::table('user_infos')->insert([
            // 换算后的根代理：user_id 已抬到 5729（旧号 10）。
            ['user_id' => 5729, 'login_id' => 0, 'parent_id' => 0, 'account_type' => 1, 'user_name' => 'root'],
            // 下级仍指向旧号 10 —— 换算漏了 parent_id 这一列。
            ['user_id' => 2000, 'login_id' => 0, 'parent_id' => 10, 'account_type' => 1, 'user_name' => 'child'],
        ]);

        $this->assertFalse(
            $this->runCheck('代理层级引用无孤儿'),
            'parent_id 指向不存在账号时必须判红，否则漏改换算会被当成迁移成功'
        );

        DB::table('user_infos')->where('user_id', 2000)->update(['parent_id' => 5729]);

        $this->assertTrue(
            $this->runCheck('代理层级引用无孤儿'),
            'parent_id 指向真实上级时必须判绿，否则判据永远为红、失去区分力'
        );
    }

    /**
     * 序列水位被全局最大值覆盖必须判红：这是 4.6 缺 WHERE 的确切后果。
     *
     * @return void 无返回值。
     */
    public function test_id_sequence_watermark_is_detected(): void
    {
        // login_id 同上：NOT NULL 无默认值，缺它会以插入报错的形式失败。
        DB::table('user_infos')->delete();
        DB::table('user_infos')->insert([
            ['user_id' => 5778, 'login_id' => 0, 'parent_id' => 0, 'account_type' => 1, 'user_name' => 'agent'],
            ['user_id' => 620826, 'login_id' => 0, 'parent_id' => 0, 'account_type' => 2, 'user_name' => 'customer'],
        ]);

        // 缺 WHERE 的形态：两类序列都被写成全局最大值 +1。
        DB::table('id_sequences')->whereIn('type', ['agent', 'customer'])->delete();
        DB::table('id_sequences')->insert([
            ['type' => 'agent', 'current_value' => 620827, 'prefix' => '', 'step' => 1],
            ['type' => 'customer', 'current_value' => 620827, 'prefix' => '', 'step' => 1],
        ]);

        $this->assertFalse(
            $this->runCheck('id_sequences 按类型对齐本段水位'),
            '代理序列落进客户区间时必须判红，否则新注册代理商会拿到客户号段'
        );

        DB::table('id_sequences')->where('type', 'agent')->update(['current_value' => 5778]);

        $this->assertTrue(
            $this->runCheck('id_sequences 按类型对齐本段水位'),
            '两类序列各自对齐本段水位时必须判绿'
        );
    }
}
