<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/29
 * Time: 13:19
 */

/**
 * 前台邀请代理等级自举闭包测试。
 *
 * 文件功能：
 * - 锁定 2026_08_01_000002_ensure_front_inviter_test_agent 迁移的自给自足语义：
 *   migrate:fresh 阶段 seeder 尚未运行、agent_levels 为空表时，迁移必须幂等创建
 *   level_code=1 的基础行，保证 user 10（固定前台邀请代理）的 level_id 可解析。
 * - 回归背景：2026-08-28/29 全量串行出现 27 条顺序失败（agents_save 4005、
 *   前台注册 5000「代理未配置有效等级」、FrontBusiness 21 条级联），根因是该迁移
 *   曾回退 level_id=0，而修复链路的 FrontDemoDataSeeder 自 2026-08-17 22:18 起被
 *   FRONT_DEMO_SEEDER_ENABLED 安全门禁挡在串行之外。
 *
 * 适用场景：
 * - 全新库 migrate:fresh --seed 后的邀请代理等级基线回归。
 *
 * 方法功能：
 * - test_migration_bootstraps_level_one_when_agent_levels_is_empty：空表下执行 up()，断言基础行被创建且 user 10 等级可解析。
 * - test_migration_is_idempotent_for_level_bootstrap：重复执行 up() 不产生重复基础行。
 *
 * 返回值：
 * - 断言失败时抛出 PHPUnit 断言异常。
 *
 * 异常或失败场景：
 * - 若迁移在空表上仍把 user 10 写成 level_id=0，或重复执行产生重复行，测试失败。
 */

namespace Tests\Feature;

use App\Services\FamilyTreeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FrontInviterAgentLevelBootstrapClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 迁移文件无命名空间、不经 composer autoload，测试内直接加载源文件。
     */
    private function migration(): \EnsureFrontInviterTestAgent
    {
        require_once database_path('migrations/2026_08_01_000002_ensure_front_inviter_test_agent.php');

        return new \EnsureFrontInviterTestAgent();
    }

    public function test_migration_bootstraps_level_one_when_agent_levels_is_empty(): void
    {
        // 模拟 migrate:fresh 的执行顺序：migrate 阶段 agent_levels 尚未被 seeder 填充。
        DB::table('agent_levels')->delete();

        $this->migration()->up();

        $levelRow = DB::table('agent_levels')->where('level_code', 1)->first();
        $this->assertNotNull($levelRow, '空表时迁移必须自举 level_code=1 的基础行。');

        $userLevelId = (int) DB::table('user_infos')->where('user_id', 10)->value('level_id');
        $this->assertSame((int) $levelRow->id, $userLevelId, 'user 10 的 level_id 必须指向迁移自举的一级代理行。');

        // 与注册链路同口径：祖先等级必须能解析出 level_code，且关系码可生成。
        $resolved = DB::table('agent_levels')->whereIn('id', [$userLevelId])->pluck('level_code', 'id');
        $this->assertSame(['1'], array_map('strval', array_values($resolved->all())), 'user 10 的等级必须能被 legacyRelationshipCode 解析。');

        $service = new FamilyTreeService();
        $hierarchy = $service->resolveCustomerHierarchy(999999901, 10);
        $this->assertSame([10], $hierarchy['ancestor_ids'], '以 user 10 为直属上级的祖先链必须只含 user 10。');
        $this->assertNotSame('00000000000000000000', $hierarchy['relationship_code'], '一级代理在链上时关系码不能全空。');
    }

    public function test_migration_is_idempotent_for_level_bootstrap(): void
    {
        DB::table('agent_levels')->delete();

        $migration = $this->migration();
        $migration->up();
        $migration->up();

        $count = (int) DB::table('agent_levels')->where('level_code', 1)->count();
        $this->assertSame(1, $count, '重复执行 up() 不得产生重复的 level_code=1 基础行。');
    }
}
