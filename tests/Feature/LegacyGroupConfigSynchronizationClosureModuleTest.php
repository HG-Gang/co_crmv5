<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 03:48
 */

namespace Tests\Feature;

use App\Services\LegacyGroupConfigSynchronizer;
use Database\Seeders\FrontDemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

/**
 * 旧交易组配置同步闭环测试。
 *
 * 文件功能：
 * - 约束 legacy_group_id 保存旧表主键，pair_id 保存新表自关联主键。
 * - 验证同步必须分两阶段完成，避免把旧 pair_id 错当作用户所属组身份。
 * - 验证同步可重复执行，并修复 user_infos.group_id 与 mt4_group 的对应关系。
 * - 验证 Demo Seeder 重跑不会再清空已有配对关系。
 * - 验证 Demo 用户带入旧 MT4 组名时同步采用同名当前交易组主键。
 */
class LegacyGroupConfigSynchronizationClosureModuleTest extends TestCase
{
    /**
     * 每个测试前清除本文件已知固定夹具。
     *
     * group_configs 在历史环境中可能仍是 MyISAM，不能依赖 DatabaseTransactions 回滚，
     * 因此这里使用严格名称和测试专用旧主键做显式清理。
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupFixtures();
    }

    /**
     * 测试结束后再次清理固定夹具，避免失败用例污染本地开发数据库。
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->cleanupFixtures();
        parent::tearDown();
    }

    /**
     * 验证当前交易组表使用支持事务和行锁的 InnoDB。
     *
     * @return void
     */
    public function test_group_configs_use_innodb_for_transactional_switch(): void
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('当前数据库不是 MySQL/MariaDB，无需检查存储引擎。');
        }

        foreach (['group_configs', 'user_infos', 'user_trades'] as $table) {
            $engine = DB::table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->value('ENGINE');

            $this->assertSame('InnoDB', $engine, $table . ' 必须支持账户切换事务和测试回滚。');
        }
    }

    /**
     * 验证旧主键和配对关系会映射成两个含义明确的新字段。
     *
     * @return void
     */
    public function test_legacy_pair_mapping_produces_distinct_legacy_group_id_and_pair_id(): void
    {
        $this->assertTrue(
            Schema::hasColumn('group_configs', 'legacy_group_id'),
            'group_configs 缺少 legacy_group_id，无法区分旧主键与当前 pair_id。'
        );

        $now = time();
        $legacyStpCurrentId = $this->insertGroup('Legacy SYNC-STP', null, null, 0);
        $legacyEcnCurrentId = $this->insertGroup('Legacy SYNC-ECN', null, null, 1);
        $userId = 413270101;
        $this->insertUserInfo($userId, $legacyEcnCurrentId, 'SYNC-STP');

        $groups = $this->legacyGroups();
        $synchronizer = app(LegacyGroupConfigSynchronizer::class);
        $firstMap = $synchronizer->synchronize($groups, $now, true);
        $secondMap = $synchronizer->synchronize($groups, $now + 1, true);

        $this->assertSame($legacyStpCurrentId, $firstMap[101] ?? null);
        $this->assertSame($legacyEcnCurrentId, $firstMap[115] ?? null);
        $this->assertSame($firstMap, $secondMap);
        $this->assertDatabaseHas('group_configs', [
            'id' => $legacyStpCurrentId,
            'legacy_group_id' => 101,
            'pair_id' => $legacyEcnCurrentId,
            'name' => 'SYNC-STP',
            'is_ecn' => 0,
        ]);
        $this->assertDatabaseHas('group_configs', [
            'id' => $legacyEcnCurrentId,
            'legacy_group_id' => 115,
            'pair_id' => $legacyStpCurrentId,
            'name' => 'SYNC-ECN',
            'is_ecn' => 1,
        ]);
        $this->assertSame(
            2,
            DB::table('group_configs')->whereIn('legacy_group_id', [101, 115])->count(),
            '重复同步不得产生第二套旧交易组记录。'
        );
        $this->assertDatabaseHas('user_infos', [
            'user_id' => $userId,
            'group_id' => $legacyStpCurrentId,
            'mt4_group' => 'SYNC-STP',
        ]);
    }

    /**
     * 验证 Demo Seeder 更新标准组时保留已经建立的 pair_id。
     *
     * @return void
     */
    public function test_demo_seeder_preserves_existing_pair_id(): void
    {
        $targetId = $this->insertGroup('DEMO-PAIR-TARGET', null, null, 1);
        $standardId = (int) DB::table('group_configs')->where('name', 'Agent Standard')->value('id');
        if ($standardId <= 0) {
            $standardId = $this->insertGroup('Agent Standard', null, $targetId, 0, 1);
        } else {
            DB::table('group_configs')->where('id', $standardId)->update(['pair_id' => $targetId]);
        }

        $method = new ReflectionMethod(FrontDemoDataSeeder::class, 'seedGroupConfigs');
        $method->setAccessible(true);
        $method->invoke(new FrontDemoDataSeeder(), time(), []);

        $this->assertSame(
            $targetId,
            (int) DB::table('group_configs')->where('id', $standardId)->value('pair_id'),
            'Demo Seeder 重跑不得把业务已配置的 pair_id 覆盖为 null。'
        );
    }

    /**
     * 验证全量业务迁移按 legacy_group_id 映射用户所属组，而不是按 pair_id 反向映射。
     *
     * @return void
     */
    public function test_business_migration_maps_users_by_legacy_group_id(): void
    {
        $source = file_get_contents(database_path('seeders/LegacyFrontBusinessDataSeeder.php')) ?: '';

        $this->assertStringContainsString("->whereNotNull('legacy_group_id')", $source);
        $this->assertStringContainsString("->pluck('id', 'legacy_group_id')", $source);
        $this->assertStringNotContainsString("->pluck('id', 'pair_id')", $source);
    }

    /**
     * 验证 Demo 用户合并旧资料时按 MT4 组名改写当前组主键。
     *
     * @return void
     */
    public function test_demo_user_merge_rewrites_group_id_by_mt4_group_name(): void
    {
        $method = new ReflectionMethod(FrontDemoDataSeeder::class, 'mergeLegacyUser');
        $method->setAccessible(true);

        $mapped = $method->invoke(
            new FrontDemoDataSeeder(),
            [
                'name' => 'Demo Customer',
                'type' => 2,
                'funds' => 100,
                'rate' => 0,
                'group' => 901,
            ],
            [
                'mt4_grp' => 'SYNC-STP',
            ],
            [
                'SYNC-STP' => 902,
            ]
        );

        $this->assertSame('SYNC-STP', $mapped['mt4_group']);
        $this->assertSame(
            902,
            $mapped['group'],
            'Demo 用户的 group_id 必须与已经带入的旧 MT4 组名保持一致。'
        );
    }

    /**
     * 返回两条互相配对的标准化旧组数据。
     *
     * @return array<int, array<string, mixed>> 旧主键 101/115 的 STP 与 ECN 配对组。
     */
    private function legacyGroups(): array
    {
        return [
            [
                'legacy_group_id' => 101,
                'legacy_pair_id' => 115,
                'name' => 'SYNC-STP',
                'radix' => 50,
                'category' => 2,
                'has_commission' => 0,
                'is_enabled' => 1,
                'is_ecn' => 0,
                'is_default' => 1,
                'created_by' => 0,
                'updated_by' => 0,
                'created_at' => time(),
                'updated_at' => time(),
                'deleted_at' => null,
            ],
            [
                'legacy_group_id' => 115,
                'legacy_pair_id' => 101,
                'name' => 'SYNC-ECN',
                'radix' => 50,
                'category' => 2,
                'has_commission' => 0,
                'is_enabled' => 1,
                'is_ecn' => 1,
                'is_default' => 1,
                'created_by' => 0,
                'updated_by' => 0,
                'created_at' => time(),
                'updated_at' => time(),
                'deleted_at' => null,
            ],
        ];
    }

    /**
     * 写入测试组配置。
     *
     * @param string $name 当前组名称。
     * @param int|null $legacyGroupId 旧 group_config.id；null 表示尚未认领。
     * @param int|null $pairId 当前 group_configs.id 配对键。
     * @param int $isEcn 账户类型，0=STP，1=ECN。
     * @param int $category 组类别，1=代理组，2=客户组。
     * @return int 新增当前组主键。
     */
    private function insertGroup(
        string $name,
        int $legacyGroupId = null,
        int $pairId = null,
        int $isEcn,
        int $category = 2
    ): int {
        $now = time();
        $payload = [
            'pair_id' => $pairId,
            'name' => $name,
            'radix' => 50,
            'category' => $category,
            'has_commission' => 0,
            'is_enabled' => 1,
            'is_ecn' => $isEcn,
            'is_default' => 0,
            'created_by' => 0,
            'updated_by' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ];
        if (Schema::hasColumn('group_configs', 'legacy_group_id')) {
            $payload['legacy_group_id'] = $legacyGroupId;
        }

        return (int) DB::table('group_configs')->insertGetId($payload);
    }

    /**
     * 写入等待组别映射修复的用户资料。
     *
     * @param int $userId 业务用户编号。
     * @param int $wrongGroupId 当前错误的 group_configs.id。
     * @param string $mt4Group MT4 真实组名，作为修复依据。
     * @return void
     */
    private function insertUserInfo(int $userId, int $wrongGroupId, string $mt4Group): void
    {
        $now = time();
        DB::table('user_infos')->where('user_id', $userId)->delete();
        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => 0,
            'user_name' => 'legacy-group-sync-user',
            'phone' => '',
            'gender' => 1,
            'account_type' => 2,
            'parent_id' => 0,
            'family_tree' => (string) $userId,
            'group_id' => $wrongGroupId,
            'level_id' => 0,
            'comm_rate' => 0,
            'auth_status' => 1,
            'total_funds' => 0,
            'used_margin' => 0,
            'avail_margin' => 0,
            'equity' => 0,
            'effective_credit' => 0,
            'risk_ratio' => 0,
            'leverage' => 100,
            'is_ecn' => 0,
            'mt4_group' => $mt4Group,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    /**
     * 删除本测试文件创建的固定用户和交易组夹具。
     *
     * @return void
     */
    private function cleanupFixtures(): void
    {
        DB::table('user_infos')->where('user_id', 413270101)->delete();

        $fixtureGroupIds = DB::table('group_configs')
            ->whereIn('name', [
                'Legacy SYNC-STP',
                'Legacy SYNC-ECN',
                'SYNC-STP',
                'SYNC-ECN',
                'DEMO-PAIR-TARGET',
            ])
            ->pluck('id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->all();
        if ($fixtureGroupIds) {
            DB::table('group_configs')
                ->where('name', 'Agent Standard')
                ->whereIn('pair_id', $fixtureGroupIds)
                ->update(['pair_id' => null]);
        }

        DB::table('group_configs')
            ->whereIn('legacy_group_id', [101, 115])
            ->orWhereIn('name', [
                'Legacy SYNC-STP',
                'Legacy SYNC-ECN',
                'SYNC-STP',
                'SYNC-ECN',
                'DEMO-PAIR-TARGET',
            ])
            ->delete();
    }
}
