<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:45
 */

declare(strict_types=1);

/**
 * 文件功能：在 DatabaseTransactions 事务之外真实运行佣金对账权限迁移
 *           （AddCommissionTransferReconcilePermissions）：验证幂等性、重复
 *           slug 预检拦截，以及 down/up 对权限行与审计字段的非破坏性恢复。
 *
 * 适用场景：MySQL DDL 会隐式提交，该迁移契约必须自行管理最终状态，
 *           用于迁移文件的运行时回归测试。
 *
 * 入参例子：
 * - 无 HTTP 入参；内部直接 require 迁移文件并调用 up()/down()。
 *
 * 返回值：
 * - up() 重复执行保持每个 slug 唯一且 id 不变；down() 后行软禁用但保留；
 * - up() 再次执行恢复原 id 与启用状态，commission_transfers 审计列保留。
 *
 * 异常或失败场景：
 * - 存在重复 slug 时 up() 抛出 RuntimeException（预检拦截）。
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Runs the reconciliation migration outside DatabaseTransactions.
 * MySQL DDL implicitly commits, so this contract must own its final state.
 */
final class AdminCommissionTransferReconciliationMigrationRuntimeTest extends TestCase
{
    /**
     * 被测迁移文件路径：为佣金转账对账模块新增三个后台权限。运行时用例反复执行它验证幂等与回滚。
     * @var string
     */
    private const MIGRATION = 'database/migrations/2026_07_19_000004_add_commission_transfer_reconcile_permissions.php';

    /**
     * 迁移写入的三个权限 slug（列表/详情/执行）。断言 up 幂等、重复预检与 down 清理都以它们为准。
     * @var array<int, string>
     */
    private const SLUGS = [
        'admin_commission_transfer_reconciliation_list',
        'admin_commission_transfer_reconciliation_detail',
        'admin_commission_transfer_reconcile',
    ];

    // 迁移 up() 应幂等：重复执行不产生重复 slug，存在重复 slug 时预检抛异常。
    public function test_migration_is_idempotent_duplicate_preflight_is_enforced_and_down_keeps_audit_schema(): void
    {
        $this->assertFileExists(base_path(self::MIGRATION));
        require_once base_path(self::MIGRATION);

        $migration = new \AddCommissionTransferReconcilePermissions();
        $migration->up();
        $firstIds = DB::table('permissions')
            ->whereIn('slug', self::SLUGS)
            ->pluck('id', 'slug')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $migration->up();
        foreach (self::SLUGS as $slug) {
            $this->assertSame(1, DB::table('permissions')->where('slug', $slug)->count(), $slug);
            $this->assertSame($firstIds[$slug], (int) DB::table('permissions')->where('slug', $slug)->value('id'), $slug);
        }

        $duplicateId = DB::table('permissions')->insertGetId([
            'parent_id' => 0,
            'name' => 'duplicate reconciliation permission',
            'slug' => 'duplicate_reconciliation_slug',
            'api_route' => 'admin_api_commissionTransferReconciliationList',
            'route' => '',
            'icon' => '',
            'type' => 3,
            'guard_type' => 'admin',
            'sort' => 999,
            'status' => 1,
            'created_at' => now()->format('Y-m-d H:i:s'),
            'updated_at' => now()->format('Y-m-d H:i:s'),
            'deleted_at' => null,
        ]);

        try {
            $this->expectException(\RuntimeException::class);
            $migration->up();
        } finally {
            DB::table('permissions')->where('id', $duplicateId)->delete();
        }
    }

    // 迁移 down() 应软禁用权限行并保留审计列，up() 再执行恢复原 id 与启用状态。
    public function test_migration_down_soft_disables_rows_and_up_reactivates_same_ids(): void
    {
        $this->assertFileExists(base_path(self::MIGRATION));
        require_once base_path(self::MIGRATION);
        $migration = new \AddCommissionTransferReconcilePermissions();
        $migration->up();
        $firstIds = DB::table('permissions')->whereIn('slug', self::SLUGS)->pluck('id', 'slug')->all();

        $migration->down();
        foreach (self::SLUGS as $slug) {
            $row = DB::table('permissions')->where('slug', $slug)->first();
            $this->assertSame(0, (int) $row->status, $slug);
            $this->assertNotNull($row->deleted_at, $slug);
        }
        $this->assertTrue(Schema::hasColumn('commission_transfers', 'reconcile_decision'));
        $this->assertTrue(Schema::hasColumn('commission_transfers', 'reconcile_external_reference'));

        $migration->up();
        foreach (self::SLUGS as $slug) {
            $row = DB::table('permissions')->where('slug', $slug)->first();
            $this->assertSame((int) $firstIds[$slug], (int) $row->id, $slug);
            $this->assertSame(1, (int) $row->status, $slug);
            $this->assertNull($row->deleted_at, $slug);
        }
    }
}
