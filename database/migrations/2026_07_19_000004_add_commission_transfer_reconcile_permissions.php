<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:32
 */

/**
 * 新增佣金划转对账相关后台权限。
 *
 * 文件功能：
 * - 向 permissions 表写入佣金划转对账页面与操作权限，并绑定默认角色。
 *
 * 字段语义：
 * - 仅操作 permissions/role_permissions 字典数据；回滚时删除本迁移写入的权限节点。
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddCommissionTransferReconcilePermissions extends Migration
{
    /**
     * 权限节点挂在佣金主表上的存在性校验用表名：写入权限前先断言该表存在，
     * 防止本迁移先于 000003 执行时把对账权限写入残缺库。
     */
    private const TRANSFERS = 'commission_transfers';

    /**
     * 对账权限节点的父级权限 slug（后台“佣金管理”菜单）。新节点必须挂在该父节点下，
     * 才能出现在既有后台菜单树中；改值会让权限脱离菜单层级导致页面不可见。
     */
    private const PARENT_SLUG = 'admin_commissions';

    /**
     * 本迁移写入的权限节点集合：列表/详情/执行对账三个节点，slug 与 api_route 一一对应
     * 后台路由与 check.permission 中间件。upsert 幂等、回滚仅置 status=0 保留主键，
     * 保证二次 up() 复活同一行而不破坏 role_permissions 关联。
     *
     * @var array<int, array<string, mixed>>
     */
    private const PERMISSIONS = [
        [
            'name' => 'Commission transfer reconciliation list',
            'slug' => 'admin_commission_transfer_reconciliation_list',
            'api_route' => 'admin_api_commissionTransferReconciliationList',
            'sort' => 30,
        ],
        [
            'name' => 'Commission transfer reconciliation detail',
            'slug' => 'admin_commission_transfer_reconciliation_detail',
            'api_route' => 'admin_api_commissionTransferReconciliationDetail',
            'sort' => 40,
        ],
        [
            'name' => 'Reconcile commission transfer',
            'slug' => 'admin_commission_transfer_reconcile',
            'api_route' => 'admin_api_commissionTransferReconcile',
            'sort' => 50,
        ],
    ];

    public function up()
    {
        $this->assertSchemaPrerequisites();
        $this->assertNoDuplicatePermissionIdentities();
        $parentId = $this->commissionPermissionParentId();

        $this->addReconciliationColumns();

        foreach (self::PERMISSIONS as $permission) {
            $this->upsertPermission($permission, $parentId);
        }
    }

    public function down()
    {
        // Keep financial audit columns and permission identities. A later up()
        // reactivates the same rows without changing their primary keys.
        $now = now()->format('Y-m-d H:i:s');
        DB::table('permissions')
            ->whereIn('slug', array_column(self::PERMISSIONS, 'slug'))
            ->update([
                'status' => 0,
                'updated_at' => $now,
                'deleted_at' => $now,
            ]);
    }

    private function assertSchemaPrerequisites(): void
    {
        if (!Schema::hasTable(self::TRANSFERS)) {
            throw new RuntimeException('commission_transfers must exist before reconciliation fields are added.');
        }
        if (!Schema::hasTable('permissions')) {
            throw new RuntimeException('permissions must exist before reconciliation permissions are added.');
        }
    }

    private function assertNoDuplicatePermissionIdentities(): void
    {
        $parentCount = DB::table('permissions')->where('slug', self::PARENT_SLUG)->count();
        if ($parentCount !== 1) {
            throw new RuntimeException('Permission duplicate preflight requires exactly one admin_commissions parent.');
        }

        foreach (self::PERMISSIONS as $permission) {
            $slugRows = DB::table('permissions')->where('slug', $permission['slug'])->get();
            if ($slugRows->count() > 1) {
                throw new RuntimeException('Permission duplicate preflight failed for slug ' . $permission['slug'] . '.');
            }

            $routeRows = DB::table('permissions')->where('api_route', $permission['api_route'])->get();
            if ($routeRows->count() > 1) {
                throw new RuntimeException('Permission duplicate preflight failed for api_route ' . $permission['api_route'] . '.');
            }

            $slugRow = $slugRows->first();
            if ($slugRow && (string) $slugRow->api_route !== ''
                && (string) $slugRow->api_route !== $permission['api_route']) {
                throw new RuntimeException('Permission slug is already assigned to another api_route: ' . $permission['slug'] . '.');
            }

            $routeRow = $routeRows->first();
            if ($routeRow && (string) $routeRow->slug !== $permission['slug']) {
                throw new RuntimeException('Permission api_route is already assigned to another slug: ' . $permission['api_route'] . '.');
            }
        }
    }

    private function commissionPermissionParentId(): int
    {
        return (int) DB::table('permissions')
            ->where('slug', self::PARENT_SLUG)
            ->value('id');
    }

    private function addReconciliationColumns(): void
    {
        $missing = array_values(array_filter([
            'reconcile_decision',
            'reconcile_external_reference',
            'reconciled_by',
            'reconciled_at',
        ], static function (string $column): bool {
            return !Schema::hasColumn(self::TRANSFERS, $column);
        }));

        if ($missing === []) {
            return;
        }

        Schema::table(self::TRANSFERS, function (Blueprint $table) use ($missing): void {
            foreach ($missing as $column) {
                switch ($column) {
                    case 'reconcile_decision':
                        $table->string($column, 40)->nullable();
                        break;
                    case 'reconcile_external_reference':
                        $table->string($column, 100)->nullable();
                        break;
                    case 'reconciled_by':
                        $table->unsignedBigInteger($column)->nullable();
                        break;
                    case 'reconciled_at':
                        $table->unsignedInteger($column)->nullable();
                        break;
                }
            }
        });
    }

    /** @param array<string, mixed> $permission */
    private function upsertPermission(array $permission, int $parentId): void
    {
        $now = now()->format('Y-m-d H:i:s');
        $values = [
            'name' => $permission['name'],
            'guard_type' => 'admin',
            'parent_id' => $parentId,
            'type' => 3,
            'icon' => '',
            'sort' => $permission['sort'],
            'route' => '',
            'api_route' => $permission['api_route'],
            'status' => 1,
            'updated_at' => $now,
            'deleted_at' => null,
        ];
        $existingId = DB::table('permissions')->where('slug', $permission['slug'])->value('id');

        if ($existingId) {
            DB::table('permissions')->where('id', $existingId)->update($values);
            return;
        }

        DB::table('permissions')->insert(array_merge($values, [
            'slug' => $permission['slug'],
            'created_at' => $now,
        ]));
    }
}
