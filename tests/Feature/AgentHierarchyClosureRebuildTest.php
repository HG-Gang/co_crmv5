<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 21:21
 */

/**
 * AgentHierarchyClosureRebuildTest
 *
 * 文件功能：
 * - 验证代理层级闭包重建：祖先解析与 parent 拓扑回退、软删节点忽略、后代重建前置校验、128 层深度上限、环路与断链失败关闭、全量重建规范化与审计口径、前台范围不越过客户叶子。
 * - 输入：HTTP 请求与事务回滚的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖真实 MT4 网关与线上支付/出金通道（由网关契约测试锁定）。
 */

namespace Tests\Feature;

use App\Models\UserInfo;
use App\Services\CommissionService;
use App\Services\FamilyTreeService;
use App\Services\LegacyAdminAgentStatisticsService;
use App\Services\UserRegistrationService;
use App\Traits\HasDataScope;
use App\Http\Controllers\Front\OrderController;
use App\Http\Controllers\Front\PositionController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use App\Support\FrontLegacyData;
use Tests\Support\MySqlAutoIncrementSnapshot;
use Tests\TestCase;

class AgentHierarchyClosureRebuildTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * setUp 捕获的 MySqlAutoIncrementSnapshot 实例；tearDown 调用 restore() 还原闭包表自增值，
     * 消除重建夹具对共享库的影响。null 表示尚未捕获。
     * @var MySqlAutoIncrementSnapshot|null
     */
    private $autoIncrementSnapshot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->autoIncrementSnapshot = MySqlAutoIncrementSnapshot::capture([
            'user_infos',
            'agent_descendants',
        ]);
        $this->beforeApplicationDestroyed(function (): void {
            if ($this->autoIncrementSnapshot !== null) {
                $this->autoIncrementSnapshot->restore();
            }
        });
    }

    public function test_get_ancestors_parses_legacy_delimited_tree_without_returning_zero_or_self(): void
    {
        [$rootId, $childId, $customerId] = $this->unusedUserIds(3);

        $this->insertUser($rootId, 1, 0, ',0,' . $rootId . ',');
        $this->insertUser($childId, 1, $rootId, ',0,' . $rootId . ',' . $childId . ',');
        $this->insertUser(
            $customerId,
            2,
            $childId,
            ',0,' . $rootId . ',' . $childId . ',' . $customerId . ','
        );

        $ancestorIds = array_map(
            'intval',
            array_column((new FamilyTreeService())->getAncestors($customerId), 'user_id')
        );

        $this->assertSame([$rootId, $childId], $ancestorIds);
    }

    public function test_get_ancestors_uses_parent_topology_when_family_tree_is_stale(): void
    {
        [$rootId, $childId, $unrelatedAgentId, $customerId] = $this->unusedUserIds(4);

        $this->insertUser($rootId, 1, 0, (string) $rootId);
        $this->insertUser($childId, 1, $rootId, $rootId . ',' . $childId);
        $this->insertUser($unrelatedAgentId, 1, 0, (string) $unrelatedAgentId);
        $this->insertUser(
            $customerId,
            2,
            $childId,
            $unrelatedAgentId . ',' . $customerId
        );

        $ancestorIds = array_map(
            'intval',
            array_column((new FamilyTreeService())->getAncestors($customerId), 'user_id')
        );

        $this->assertSame([$rootId, $childId], $ancestorIds);
        $this->assertSame(
            [$rootId, $childId],
            UserInfo::where('user_id', $customerId)->first()->getAncestorIds()
        );
    }

    public function test_ancestor_reads_ignore_soft_deleted_parent_nodes(): void
    {
        [$deletedAgentId, $customerId] = $this->unusedUserIds(2);

        $this->insertUser($deletedAgentId, 1, 0, (string) $deletedAgentId);
        $this->insertUser($customerId, 2, $deletedAgentId, $deletedAgentId . ',' . $customerId);
        DB::table('user_infos')->where('user_id', $deletedAgentId)->update([
            'deleted_at' => time(),
        ]);

        $this->assertSame([], (new FamilyTreeService())->getAncestors($customerId));
        $this->assertSame([], UserInfo::where('user_id', $customerId)->firstOrFail()->getAncestorIds());
    }

    public function test_descendant_rebuild_rejects_a_customer_with_children_before_writing_rows(): void
    {
        [$rootAgentId, $customerId, $invalidChildId] = $this->unusedUserIds(3);

        $this->insertUser($rootAgentId, 1, 0, (string) $rootAgentId);
        $this->insertUser($customerId, 2, $rootAgentId, $rootAgentId . ',' . $customerId);
        $this->insertUser($invalidChildId, 2, $customerId, $rootAgentId . ',' . $customerId . ',' . $invalidChildId);

        $this->expectException(\RuntimeException::class);
        try {
            (new FamilyTreeService())->rebuildDescendants($rootAgentId);
        } finally {
            $this->assertSame(
                0,
                DB::table('agent_descendants')->where('agent_id', $rootAgentId)->count()
            );
        }
    }

    public function test_order_and_position_chain_helpers_fail_closed_for_invalid_parent_cycles(): void
    {
        $this->isolateHierarchyFixture();
        [$rootAgentId, $customerId] = $this->unusedUserIds(2);

        $this->insertUser($rootAgentId, 1, $customerId, $customerId . ',' . $rootAgentId);
        $this->insertUser($customerId, 2, $rootAgentId, $rootAgentId . ',' . $customerId);
        $customer = UserInfo::where('user_id', $customerId)->firstOrFail();

        $orderChain = new \ReflectionMethod(OrderController::class, 'parentOrderChainIds');
        $orderChain->setAccessible(true);
        $orderController = new OrderController(app(CommissionService::class));
        $this->assertSame([], $orderChain->invoke($orderController, $customer));

        $positionChain = new \ReflectionMethod(PositionController::class, 'parentSummaryChainIds');
        $positionChain->setAccessible(true);
        $this->assertSame([], $positionChain->invoke(new PositionController(), $customer));
    }

    public function test_parent_chain_over_128_agents_fails_closed_for_registration_and_ancestor_reads(): void
    {
        $ids = $this->unusedUserIds(130);
        $levelId = (int) DB::table('agent_levels')->orderBy('level_code')->value('id');
        $parentId = 0;

        foreach (array_slice($ids, 0, 129) as $agentId) {
            $this->insertUser($agentId, 1, $parentId, 'stale');
            $parentId = $agentId;
        }

        DB::table('user_infos')
            ->whereIn('user_id', array_slice($ids, 0, 129))
            ->update(['level_id' => $levelId]);

        $customerId = $ids[129];
        $this->insertUser($customerId, 2, $parentId, 'stale');
        $customer = UserInfo::where('user_id', $customerId)->firstOrFail();
        $service = new FamilyTreeService();

        $this->assertSame([], $service->getAncestors($customerId));
        $this->assertSame([], $customer->getAncestorIds());
        $this->assertSame([], FrontLegacyData::userScopeIds($ids[0], false));

        $stats = FrontLegacyData::batchSubAgentStats([$ids[0]])[$ids[0]];
        $this->assertSame(0, $stats['total_agents']);
        $this->assertSame(0, $stats['total_customers']);

        try {
            $service->rebuildDescendants($ids[0]);
            $this->fail('Over-depth descendant rebuild must fail closed.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('深度', $exception->getMessage());
        }

        try {
            $service->rebuildFamilyTree($customerId);
            $this->fail('Over-depth family tree rebuild must fail closed.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('depth', $exception->getMessage());
        }

        $relationshipCode = new \ReflectionMethod(UserRegistrationService::class, 'buildLegacyRelationshipCode');
        $relationshipCode->setAccessible(true);
        try {
            $relationshipCode->invoke(new UserRegistrationService(), $parentId);
            $this->fail('Over-depth MT4 registration parameters must fail closed.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('深度', $exception->getMessage());
        }

        $commissionChain = new \ReflectionMethod(CommissionService::class, 'familyChainIds');
        $commissionChain->setAccessible(true);
        $this->assertSame([], $commissionChain->invoke(new CommissionService(), $customer));

        $this->expectException(\InvalidArgumentException::class);
        $service->resolveCustomerHierarchy($customerId, $parentId);
    }

    public function test_parent_chain_at_128_agents_remains_valid_across_registration_and_scope_reads(): void
    {
        $ids = $this->unusedUserIds(129);
        $agentIds = array_slice($ids, 0, 128);
        $levelId = (int) DB::table('agent_levels')->orderBy('level_code')->value('id');
        $parentId = 0;

        foreach ($agentIds as $agentId) {
            $this->insertUser($agentId, 1, $parentId, 'stale');
            $parentId = $agentId;
        }

        DB::table('user_infos')->whereIn('user_id', $agentIds)->update(['level_id' => $levelId]);

        $customerId = $ids[128];
        $this->insertUser($customerId, 2, $parentId, 'stale');
        $customer = UserInfo::where('user_id', $customerId)->firstOrFail();
        $service = new FamilyTreeService();

        $ancestorIds = array_map('intval', array_column($service->getAncestors($customerId), 'user_id'));
        $this->assertSame($agentIds, $ancestorIds);
        $this->assertSame($agentIds, $customer->getAncestorIds());

        $hierarchy = $service->resolveCustomerHierarchy($customerId, $parentId);
        $this->assertSame($agentIds, $hierarchy['ancestor_ids']);

        $relationshipCode = new \ReflectionMethod(UserRegistrationService::class, 'buildLegacyRelationshipCode');
        $relationshipCode->setAccessible(true);
        $this->assertSame(
            $hierarchy['relationship_code'],
            $relationshipCode->invoke(new UserRegistrationService(), $parentId)
        );

        $commissionChain = new \ReflectionMethod(CommissionService::class, 'familyChainIds');
        $commissionChain->setAccessible(true);
        $this->assertSame(
            array_merge($agentIds, [$customerId]),
            $commissionChain->invoke(new CommissionService(), $customer)
        );

        $scopeIds = FrontLegacyData::userScopeIds($agentIds[0], false);
        $this->assertCount(128, $scopeIds);
        $this->assertContains($customerId, $scopeIds);

        $stats = FrontLegacyData::batchSubAgentStats([$agentIds[0]])[$agentIds[0]];
        $this->assertSame(127, $stats['total_agents']);
        $this->assertSame(1, $stats['total_customers']);
    }

    public function test_data_scope_descendants_ignore_stale_closure_rows(): void
    {
        [$rootId, $childId, $customerId, $staleId] = $this->unusedUserIds(4);

        $this->insertUser($rootId, 1, 0, (string) $rootId);
        $this->insertUser($childId, 1, $rootId, (string) $childId);
        $this->insertUser($customerId, 2, $childId, (string) $customerId);
        $this->insertUser($staleId, 2, 0, (string) $staleId);
        DB::table('agent_descendants')->insert([
            'agent_id' => $rootId,
            'descendant_id' => $staleId,
            'descendant_type' => 2,
            'is_direct' => 1,
            'depth' => 1,
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);

        $fixture = new class extends Model {
            use HasDataScope;
        };
        $method = new \ReflectionMethod($fixture, 'getDescendantIds');
        $method->setAccessible(true);

        $descendantIds = array_map('intval', $method->invoke($fixture, $rootId));
        sort($descendantIds);

        $expected = [$childId, $customerId];
        sort($expected);
        $this->assertSame($expected, $descendantIds);
    }

    public function test_registration_closure_rows_are_derived_from_parent_topology(): void
    {
        [$rootId, $childId, $unrelatedAgentId, $customerId] = $this->unusedUserIds(4);

        $this->insertUser($rootId, 1, 0, (string) $rootId);
        $this->insertUser($childId, 1, $rootId, $rootId . ',' . $childId);
        $this->insertUser($unrelatedAgentId, 1, 0, (string) $unrelatedAgentId);
        $this->insertUser($customerId, 2, $childId, $unrelatedAgentId . ',' . $customerId);
        $levelId = (int) DB::table('agent_levels')->orderBy('level_code')->value('id');
        DB::table('user_infos')->whereIn('user_id', [$rootId, $childId])->update(['level_id' => $levelId]);

        $customer = UserInfo::where('user_id', $customerId)->firstOrFail();
        $method = new \ReflectionMethod(UserRegistrationService::class, 'createAgentDescendantRows');
        $method->setAccessible(true);
        $method->invoke(new UserRegistrationService(), $customer);

        $relations = DB::table('agent_descendants')
            ->where('descendant_id', $customerId)
            ->orderBy('depth')
            ->get(['agent_id', 'is_direct', 'depth'])
            ->map(function ($row): array {
                return [(int) $row->agent_id, (int) $row->is_direct, (int) $row->depth];
            })
            ->all();

        $this->assertSame([
            [$childId, 1, 1],
            [$rootId, 0, 2],
        ], $relations);
    }

    public function test_rebuild_descendants_uses_parent_topology_and_replaces_soft_deleted_unique_rows(): void
    {
        [$rootId, $childAgentId, $directCustomerId, $nestedCustomerId] = $this->unusedUserIds(4);
        $now = time();

        $this->insertUser($rootId, 1, 0, (string) $rootId);
        $this->insertUser($childAgentId, 1, $rootId, (string) $childAgentId);
        $this->insertUser($directCustomerId, 2, $rootId, 'stale-tree');
        $this->insertUser($nestedCustomerId, 2, $childAgentId, ',0,999999,' . $nestedCustomerId . ',');

        DB::table('agent_descendants')->insert([
            'agent_id' => $rootId,
            'descendant_id' => $childAgentId,
            'descendant_type' => 2,
            'is_direct' => 0,
            'depth' => 99,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => $now,
        ]);

        (new FamilyTreeService())->rebuildDescendants($rootId);

        $rows = DB::table('agent_descendants')
            ->where('agent_id', $rootId)
            ->whereNull('deleted_at')
            ->orderBy('descendant_id')
            ->get(['descendant_id', 'descendant_type', 'is_direct', 'depth'])
            ->map(function ($row): array {
                return [
                    (int) $row->descendant_id,
                    (int) $row->descendant_type,
                    (int) $row->is_direct,
                    (int) $row->depth,
                ];
            })
            ->all();

        $expected = [
            [$childAgentId, 1, 1, 1],
            [$directCustomerId, 2, 1, 1],
            [$nestedCustomerId, 2, 0, 2],
        ];
        usort($expected, function (array $left, array $right): int {
            return $left[0] <=> $right[0];
        });

        $this->assertSame($expected, $rows);
        $this->assertSame(
            3,
            DB::table('agent_descendants')->where('agent_id', $rootId)->count(),
            '重建必须物理替换软删除旧行，不能留下唯一键占位记录。'
        );
    }

    public function test_all_descendant_queries_fall_back_to_complete_parent_topology_when_closure_is_missing(): void
    {
        [$rootId, $childAgentId, $directCustomerId, $nestedCustomerId] = $this->unusedUserIds(4);

        $this->insertUser($rootId, 1, 0, (string) $rootId);
        $this->insertUser($childAgentId, 1, $rootId, $rootId . ',' . $childAgentId);
        $this->insertUser($directCustomerId, 2, $rootId, $rootId . ',' . $directCustomerId);
        $this->insertUser(
            $nestedCustomerId,
            2,
            $childAgentId,
            $rootId . ',' . $childAgentId . ',' . $nestedCustomerId
        );

        $this->assertSame(0, DB::table('agent_descendants')->where('agent_id', $rootId)->count());

        $rows = collect((new FamilyTreeService())->getAllDescendants($rootId));
        $ids = $rows->pluck('descendant_id')->map(function ($id): int {
            return (int) $id;
        })->sort()->values()->all();
        $expected = [$childAgentId, $directCustomerId, $nestedCustomerId];
        sort($expected);

        $this->assertSame($expected, $ids);
        $this->assertSame(1, (int) $rows->firstWhere('descendant_id', $childAgentId)['is_direct']);
        $this->assertSame(2, (int) $rows->firstWhere('descendant_id', $nestedCustomerId)['depth']);
    }

    public function test_any_intermediate_agent_gets_all_nested_agents_and_customers_without_closure_rows(): void
    {
        $this->isolateHierarchyFixture();
        [$rootId, $agentId, $grandchildAgentId, $directCustomerId, $nestedCustomerId, $outsideId] = $this->unusedUserIds(6);

        $this->insertUser($rootId, 1, 0, 'stale');
        $this->insertUser($agentId, 1, $rootId, 'stale');
        $this->insertUser($grandchildAgentId, 1, $agentId, 'stale');
        $this->insertUser($directCustomerId, 2, $agentId, 'stale');
        $this->insertUser($nestedCustomerId, 2, $grandchildAgentId, 'stale');
        $this->insertUser($outsideId, 2, $rootId, 'stale');

        $rows = collect((new FamilyTreeService())->getAllDescendants($agentId));
        $actual = $rows->mapWithKeys(function (array $row): array {
            return [
                (int) $row['descendant_id'] => [
                    (int) $row['descendant_type'],
                    (int) $row['is_direct'],
                    (int) $row['depth'],
                ],
            ];
        })->all();

        $this->assertSame([
            $grandchildAgentId => [1, 1, 1],
            $directCustomerId => [2, 1, 1],
            $nestedCustomerId => [2, 0, 2],
        ], $actual);
        $this->assertNotContains($rootId, array_keys($actual));
        $this->assertNotContains($outsideId, array_keys($actual));
        $this->assertSame([
            'direct_agents' => 1,
            'indirect_agents' => 0,
            'total_agents' => 1,
            'direct_customers' => 1,
            'indirect_customers' => 1,
            'total_customers' => 2,
        ], (new FamilyTreeService())->getSubAgentStats($agentId));
    }

    public function test_full_rebuild_normalizes_family_tree_and_replaces_all_closure_rows(): void
    {
        $this->isolateHierarchyFixture();
        [$rootId, $childAgentId, $grandchildAgentId, $directCustomerId, $nestedCustomerId] = $this->unusedUserIds(5);

        $this->insertUser($rootId, 1, 0, ',0,' . $rootId . ',');
        $this->insertUser($childAgentId, 1, $rootId, 'old,' . $childAgentId);
        $this->insertUser($grandchildAgentId, 1, $childAgentId, 'stale-grandchild');
        $this->insertUser($directCustomerId, 2, $rootId, 'stale-customer');
        $this->insertUser($nestedCustomerId, 2, $grandchildAgentId, 'stale-nested-customer');

        DB::table('agent_descendants')->insert([
            [
                'agent_id' => $rootId,
                'descendant_id' => $childAgentId,
                'descendant_type' => 2,
                'is_direct' => 0,
                'depth' => 99,
                'created_at' => time(),
                'updated_at' => time(),
                'deleted_at' => null,
            ],
            [
                'agent_id' => $childAgentId,
                'descendant_id' => $rootId,
                'descendant_type' => 1,
                'is_direct' => 0,
                'depth' => 2,
                'created_at' => time(),
                'updated_at' => time(),
                'deleted_at' => null,
            ],
        ]);

        $result = (new FamilyTreeService())->rebuildAllHierarchy();

        $this->assertSame(5, $result['users']);
        $this->assertSame(7, $result['relations']);
        $this->assertSame(
            $rootId . ',' . $childAgentId . ',' . $grandchildAgentId,
            (string) DB::table('user_infos')->where('user_id', $grandchildAgentId)->value('family_tree')
        );
        $this->assertSame(
            $rootId . ',' . $childAgentId . ',' . $grandchildAgentId . ',' . $nestedCustomerId,
            (string) DB::table('user_infos')->where('user_id', $nestedCustomerId)->value('family_tree')
        );
        $this->assertSame(0, DB::table('agent_descendants')->where('agent_id', $childAgentId)->where('descendant_id', $rootId)->count());
        $this->assertSame(7, DB::table('agent_descendants')->whereNull('deleted_at')->count());
        $this->assertSame(0, DB::table('agent_descendants')->whereNotNull('deleted_at')->count());
    }

    public function test_full_rebuild_fails_closed_before_mutating_any_relationship_for_broken_parent_chain(): void
    {
        $this->isolateHierarchyFixture();
        [$rootId, $orphanCustomerId, $sentinelDescendantId] = $this->unusedUserIds(3);
        $this->insertUser($rootId, 1, 0, 'before');
        $this->insertUser($orphanCustomerId, 2, $sentinelDescendantId, 'before-orphan');
        $this->insertUser($sentinelDescendantId, 2, 0, 'sentinel');

        DB::table('agent_descendants')->insert([
            'agent_id' => $rootId,
            'descendant_id' => $sentinelDescendantId,
            'descendant_type' => 2,
            'is_direct' => 1,
            'depth' => 1,
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        try {
            (new FamilyTreeService())->rebuildAllHierarchy();
        } finally {
            $this->assertSame('before', DB::table('user_infos')->where('user_id', $rootId)->value('family_tree'));
            $this->assertSame(
                1,
                DB::table('agent_descendants')->where('agent_id', $rootId)->where('descendant_id', $sentinelDescendantId)->count()
            );
        }
    }

    public function test_audit_reports_valid_hierarchy_without_writing_family_tree_or_relations(): void
    {
        $this->isolateHierarchyFixture();
        [$rootId, $childAgentId, $customerId] = $this->unusedUserIds(3);
        $this->insertUser($rootId, 1, 0, (string) $rootId);
        $this->insertUser($childAgentId, 1, $rootId, $rootId . ',' . $childAgentId);
        $this->insertUser($customerId, 2, $childAgentId, $rootId . ',' . $childAgentId . ',' . $customerId);

        DB::table('agent_descendants')->insert([
            [
                'agent_id' => $rootId,
                'descendant_id' => $childAgentId,
                'descendant_type' => 1,
                'is_direct' => 1,
                'depth' => 1,
                'created_at' => time(),
                'updated_at' => time(),
                'deleted_at' => null,
            ],
            [
                'agent_id' => $rootId,
                'descendant_id' => $customerId,
                'descendant_type' => 2,
                'is_direct' => 0,
                'depth' => 2,
                'created_at' => time(),
                'updated_at' => time(),
                'deleted_at' => null,
            ],
            [
                'agent_id' => $childAgentId,
                'descendant_id' => $customerId,
                'descendant_type' => 2,
                'is_direct' => 1,
                'depth' => 1,
                'created_at' => time(),
                'updated_at' => time(),
                'deleted_at' => null,
            ],
        ]);

        $before = DB::table('user_infos')
            ->whereIn('user_id', [$rootId, $childAgentId, $customerId])
            ->pluck('family_tree', 'user_id')
            ->all();
        $result = (new FamilyTreeService())->auditHierarchy();

        $this->assertTrue($result['valid']);
        $this->assertSame(3, $result['users']);
        $this->assertSame(3, $result['expected_relations']);
        $this->assertSame(3, $result['actual_relations']);
        $this->assertSame(0, $result['missing']);
        $this->assertSame(0, $result['mismatch']);
        $this->assertSame(0, $result['extra']);
        $this->assertSame(0, $result['family_tree_mismatch']);
        $this->assertSame(0, $result['soft_deleted_relations']);
        $this->assertSame([], $result['errors']);
        $this->assertSame(
            $before,
            DB::table('user_infos')
                ->whereIn('user_id', [$rootId, $childAgentId, $customerId])
                ->pluck('family_tree', 'user_id')
                ->all()
        );
        $this->assertSame(3, DB::table('agent_descendants')->whereNull('deleted_at')->count());
    }

    public function test_audit_counts_missing_mismatched_extra_stale_and_soft_deleted_relations(): void
    {
        $this->isolateHierarchyFixture();
        [$rootId, $childAgentId, $customerId] = $this->unusedUserIds(3);
        $this->insertUser($rootId, 1, 0, (string) $rootId);
        $this->insertUser($childAgentId, 1, $rootId, $rootId . ',' . $childAgentId);
        $this->insertUser($customerId, 2, $childAgentId, 'stale-family-tree');

        DB::table('agent_descendants')->insert([
            [
                'agent_id' => $rootId,
                'descendant_id' => $childAgentId,
                'descendant_type' => 1,
                'is_direct' => 1,
                'depth' => 1,
                'created_at' => time(),
                'updated_at' => time(),
                'deleted_at' => null,
            ],
            [
                'agent_id' => $rootId,
                'descendant_id' => $customerId,
                'descendant_type' => 2,
                'is_direct' => 0,
                'depth' => 99,
                'created_at' => time(),
                'updated_at' => time(),
                'deleted_at' => null,
            ],
            [
                'agent_id' => $childAgentId,
                'descendant_id' => $rootId,
                'descendant_type' => 1,
                'is_direct' => 0,
                'depth' => 2,
                'created_at' => time(),
                'updated_at' => time(),
                'deleted_at' => null,
            ],
            [
                'agent_id' => $childAgentId,
                'descendant_id' => $customerId,
                'descendant_type' => 2,
                'is_direct' => 1,
                'depth' => 1,
                'created_at' => time(),
                'updated_at' => time(),
                'deleted_at' => time(),
            ],
        ]);

        $result = (new FamilyTreeService())->auditHierarchy();

        $this->assertFalse($result['valid']);
        $this->assertSame(3, $result['expected_relations']);
        $this->assertSame(3, $result['actual_relations']);
        $this->assertSame(1, $result['missing']);
        $this->assertSame(1, $result['mismatch']);
        $this->assertSame(1, $result['extra']);
        $this->assertSame(1, $result['family_tree_mismatch']);
        $this->assertSame(1, $result['soft_deleted_relations']);
        $this->assertSame([], $result['errors']);
    }

    public function test_front_scope_uses_parent_topology_and_rejects_stale_closure_expansion(): void
    {
        $this->isolateHierarchyFixture();
        [
            $rootId,
            $childAgentId,
            $nestedCustomerId,
            $directCustomerId,
            $invalidNestedCustomerId,
            $outsideCustomerId,
        ] = $this->unusedUserIds(6);
        $this->insertUser($rootId, 1, 0, (string) $rootId);
        $this->insertUser($childAgentId, 1, $rootId, $rootId . ',' . $childAgentId);
        $this->insertUser(
            $nestedCustomerId,
            2,
            $childAgentId,
            $rootId . ',' . $childAgentId . ',' . $nestedCustomerId
        );
        $this->insertUser($directCustomerId, 2, $rootId, $rootId . ',' . $directCustomerId);
        $this->insertUser(
            $invalidNestedCustomerId,
            2,
            $directCustomerId,
            $rootId . ',' . $directCustomerId . ',' . $invalidNestedCustomerId
        );
        $this->insertUser($outsideCustomerId, 2, 0, (string) $outsideCustomerId);

        DB::table('agent_descendants')->insert([
            'agent_id' => $rootId,
            'descendant_id' => $outsideCustomerId,
            'descendant_type' => 2,
            'is_direct' => 1,
            'depth' => 1,
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);

        $scopeIds = FrontLegacyData::userScopeIds($rootId, false);

        $this->assertContains($childAgentId, $scopeIds);
        $this->assertContains($nestedCustomerId, $scopeIds);
        $this->assertContains($directCustomerId, $scopeIds);
        $this->assertNotContains($invalidNestedCustomerId, $scopeIds);
        $this->assertNotContains($outsideCustomerId, $scopeIds);

        $stats = FrontLegacyData::batchSubAgentStats([$rootId])[$rootId];
        $this->assertSame(1, $stats['direct_agents']);
        $this->assertSame(1, $stats['total_agents']);
        $this->assertSame(1, $stats['direct_customers']);
        $this->assertSame(2, $stats['total_customers']);

        $treeUserIds = new \ReflectionMethod(LegacyAdminAgentStatisticsService::class, 'treeUserIds');
        $treeUserIds->setAccessible(true);
        $legacyAdminScopeIds = $treeUserIds->invoke(app(LegacyAdminAgentStatisticsService::class), $rootId);
        $this->assertContains($childAgentId, $legacyAdminScopeIds);
        $this->assertContains($nestedCustomerId, $legacyAdminScopeIds);
        $this->assertContains($directCustomerId, $legacyAdminScopeIds);
        $this->assertNotContains($invalidNestedCustomerId, $legacyAdminScopeIds);
        $this->assertNotContains($outsideCustomerId, $legacyAdminScopeIds);
    }

    public function test_front_scope_allows_a_customer_leaf_to_see_only_itself(): void
    {
        $this->isolateHierarchyFixture();
        [$customerId] = $this->unusedUserIds(1);
        $this->insertUser($customerId, 2, 0, (string) $customerId);

        $this->assertSame([$customerId], FrontLegacyData::userScopeIds($customerId, true));
        $this->assertSame([], FrontLegacyData::userScopeIds($customerId, false));
    }

    public function test_front_scope_never_expands_children_below_a_customer_leaf(): void
    {
        $this->isolateHierarchyFixture();
        [$rootId, $customerId, $invalidChildId] = $this->unusedUserIds(3);
        $this->insertUser($rootId, 1, 0, (string) $rootId);
        $this->insertUser($customerId, 2, $rootId, $rootId . ',' . $customerId);
        $this->insertUser(
            $invalidChildId,
            2,
            $customerId,
            $rootId . ',' . $customerId . ',' . $invalidChildId
        );

        $this->assertSame([$customerId], FrontLegacyData::userScopeIds($rootId, false));
        $this->assertSame([$customerId, $rootId], FrontLegacyData::userScopeIds($rootId, true));
        $this->assertSame([$customerId], FrontLegacyData::userScopeIds($rootId, false, null, true));
        $this->assertSame([], FrontLegacyData::userScopeIds($customerId, true));
        $this->assertSame(
            [
                'direct_agents' => 0,
                'indirect_agents' => 0,
                'total_agents' => 0,
                'direct_customers' => 1,
                'indirect_customers' => 0,
                'total_customers' => 1,
            ],
            FrontLegacyData::batchSubAgentStats([$rootId])[$rootId]
        );
    }

    public function test_front_scope_fails_closed_for_cycles_in_full_direct_and_batch_queries(): void
    {
        $this->isolateHierarchyFixture();
        [$rootId, $childAgentId] = $this->unusedUserIds(2);
        $this->insertUser($rootId, 1, $childAgentId, $childAgentId . ',' . $rootId);
        $this->insertUser($childAgentId, 1, $rootId, $rootId . ',' . $childAgentId);

        $this->assertSame([], FrontLegacyData::userScopeIds($rootId, false));
        $this->assertSame([], FrontLegacyData::userScopeIds($rootId, true));
        $this->assertSame([], FrontLegacyData::userScopeIds($rootId, false, null, true));
        $this->assertSame(0, FrontLegacyData::batchSubAgentStats([$rootId])[$rootId]['total_agents']);
    }

    public function test_front_batch_scope_accepts_exactly_128_descendant_levels(): void
    {
        $this->isolateHierarchyFixture();
        $ids = $this->unusedUserIds(129);
        $parentId = 0;

        foreach ($ids as $agentId) {
            $this->insertUser($agentId, 1, $parentId, 'stale');
            $parentId = $agentId;
        }

        $rootId = $ids[0];
        $expectedDescendants = array_slice($ids, 1);
        $this->assertSame($expectedDescendants, FrontLegacyData::userScopeIds($rootId, false));
        $this->assertSame(
            $expectedDescendants,
            FrontLegacyData::userScopesForAgentIds([$rootId], false)[$rootId]
        );
    }

    public function test_front_scope_fails_closed_when_descendants_exceed_128_levels(): void
    {
        $this->isolateHierarchyFixture();
        $ids = $this->unusedUserIds(130);
        $parentId = 0;

        foreach ($ids as $agentId) {
            $this->insertUser($agentId, 1, $parentId, 'stale');
            $parentId = $agentId;
        }

        $rootId = $ids[0];
        $this->assertSame([], FrontLegacyData::userScopeIds($rootId, false));
        $this->assertSame([], FrontLegacyData::userScopeIds($rootId, true));
        $this->assertSame([], FrontLegacyData::userScopeIds($rootId, false, null, true));
        $this->assertSame(0, FrontLegacyData::batchSubAgentStats([$rootId])[$rootId]['total_agents']);
    }

    /**
     * @return array<int, int>
     */
    private function unusedUserIds(int $count): array
    {
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $start = random_int(1700000000, 1900000000 - $count);
            $ids = range($start, $start + $count - 1);
            if (!DB::table('user_infos')->whereIn('user_id', $ids)->exists()
                && !DB::table('agent_descendants')->whereIn('agent_id', $ids)->exists()
                && !DB::table('agent_descendants')->whereIn('descendant_id', $ids)->exists()) {
                return $ids;
            }
        }

        throw new \RuntimeException('无法分配代理层级测试用户 ID。');
    }

    private function insertUser(int $userId, int $accountType, int $parentId, string $familyTree): void
    {
        DB::table('user_infos')->insert([
            'user_id' => $userId,
            'login_id' => 0,
            'user_name' => 'Hierarchy Fixture ' . $userId,
            'account_type' => $accountType,
            'parent_id' => $parentId,
            'family_tree' => $familyTree,
            'created_at' => time(),
            'updated_at' => time(),
            'deleted_at' => null,
        ]);
    }

    private function isolateHierarchyFixture(): void
    {
        DB::table('user_infos')->whereNull('deleted_at')->update(['deleted_at' => time()]);
        DB::table('agent_descendants')->delete();
    }
}
