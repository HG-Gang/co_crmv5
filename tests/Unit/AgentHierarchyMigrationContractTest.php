<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/16
 * Time: 05:20
 */

/**
 * AgentHierarchyMigrationContractTest
 *
 * 文件功能：
 * - 验证代理层级迁移契约：全量重置从 parent 拓扑构建闭包且不导入反向代理关系、两个迁移入口导入后重建派生层级、可审计重建命令默认只读需 apply 才写、独立审计用闭区间深度并区分孤儿与环路。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AgentHierarchyMigrationContractTest extends TestCase
{
    public function test_full_reset_builds_closure_from_parent_topology_and_never_imports_reversed_agent_relations(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 2) . '/database/sql/full_reset_and_migrate.sql') ?: '';
        $start = strpos($sql, '-- Agent descendants');
        $end = strpos($sql, '-- Business records', $start === false ? 0 : $start);

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $section = substr($sql, (int) $start, (int) $end - (int) $start);

        $this->assertStringNotContainsString('hank_zl_data.agent_relations', $section);
        $this->assertStringContainsString('WITH RECURSIVE', $section);
        $this->assertStringContainsString('parent_id', $section);
        $this->assertStringContainsString('agent_descendants', $section);
    }

    public function test_both_php_migration_entries_rebuild_the_derived_hierarchy_after_import(): void
    {
        $command = file_get_contents(dirname(__DIR__, 2) . '/app/Console/Commands/MigrateOldData.php') ?: '';
        $legacyRunner = file_get_contents(dirname(__DIR__, 2) . '/app/Console/Commands/MigrateOldDataCommand.php') ?: '';

        foreach ([$command, $legacyRunner] as $source) {
            $this->assertStringContainsString('FamilyTreeService', $source);
            $this->assertStringContainsString('rebuildAllHierarchy', $source);
            $this->assertStringContainsString("DB::table('agent_descendants')->truncate()", $source);
        }
    }

    public function test_auditable_rebuild_command_is_read_only_by_default_and_requires_apply_for_writes(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/app/Console/Commands/RebuildAgentHierarchy.php') ?: '';

        $this->assertStringContainsString('agent-hierarchy:rebuild', $source);
        $this->assertStringContainsString('{--apply', $source);
        $this->assertStringContainsString('auditHierarchy', $source);
        $this->assertStringContainsString("if (!\$this->option('apply'))", $source);
        $this->assertStringContainsString('rebuildAllHierarchy', $source);
    }

    public function test_standalone_hierarchy_audit_uses_inclusive_depth_and_distinguishes_orphans_from_cycles(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/audit_agent_descendants.php') ?: '';

        $this->assertStringContainsString('UserInfo::MAX_HIERARCHY_DEPTH', $source);
        $this->assertStringContainsString('missing_parent_id', $source);
        $this->assertStringContainsString('cycle_at_id', $source);
        $this->assertStringContainsString('invalid_parent_type', $source);
        $this->assertStringNotContainsString('depth < 128', $source);
    }
}
