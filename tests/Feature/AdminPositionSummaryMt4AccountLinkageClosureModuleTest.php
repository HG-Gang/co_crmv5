<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/31
 * Time: 23:44
 */

namespace Tests\Feature;

use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\SingleSignOn;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 后台持仓汇总 MT4 账户快照联动闭环测试。
 *
 * 文件功能：
 * - 验证持仓汇总通过 user_infos.mt4_code 关联真实 mt4_users.login。
 * - 验证列表返回账户余额、净值、保证金、可用保证金和杠杆快照。
 * - 验证顶部汇总只累计当前用户筛选范围，不把其它 MT4 账号混入结果。
 *
 * 返回结果：
 * - 成功时接口 code=1000，records.data 返回当前用户与 MT4 快照，summary 返回当前筛选合计。
 * - 映射缺失时 MT4 快照字段为空或为零，但不得伪造账户数据。
 */
class AdminPositionSummaryMt4AccountLinkageClosureModuleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 持仓汇总 MT4 账号联动用例的业务用户 ID。验证下钻持仓走 user_infos.mt4_code 映射。
     * @var int
     */
    private const USER_ID = 983801;
    /**
     * USER_ID 映射的真实 MT4 登录号。断言联动输出的是它而非 CRM ID。
     * @var int
     */
    private const MT4_LOGIN = 883801;
    /**
     * 另一用户的业务 ID，用于验证账号联动不跨用户串数据。
     * @var int
     */
    private const OUTSIDE_USER_ID = 983802;
    /**
     * OUTSIDE_USER_ID 映射的 MT4 登录号，其订单不得出现在范围内结果。
     * @var int
     */
    private const OUTSIDE_MT4_LOGIN = 883802;

    /**
     * 当前筛选用户必须返回真实 MT4 账户快照和对应范围合计。
     *
     * 设计原因：
     * - 业务用户编号与 MT4 登录号可能不同，不能错误地按 user_infos.user_id 关联 mt4_users.login。
     * - 额外写入一个范围外账号，用于证明 summary 基于筛选后的派生表统计，而不是全表统计。
     *
     * @return void
     */
    public function test_position_summary_returns_mapped_mt4_snapshot_and_scoped_account_totals(): void
    {
        $admin = $this->ensureSuperAdmin();
        $now = time();

        $this->upsertUser(self::USER_ID, self::MT4_LOGIN, 'Position MT4 Included', $now);
        $this->upsertUser(self::OUTSIDE_USER_ID, self::OUTSIDE_MT4_LOGIN, 'Position MT4 Outside', $now);
        $this->upsertMt4Account(self::MT4_LOGIN, 'Included MT4 Name', 1234.56, 1188.50, 88.25, 1100.25, 200, $now);
        $this->upsertMt4Account(self::OUTSIDE_MT4_LOGIN, 'Outside MT4 Name', 9999.99, 8888.88, 777.77, 8111.11, 500, $now);

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($admin, 'admin')->post('/api/admin/positionSummaryList', [
            'user_id' => self::USER_ID,
            'per_page' => 5,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 1000)
            ->assertJsonPath('data.records.total', 1)
            ->assertJsonPath('data.records.data.0.mt4_name', 'Included MT4 Name');

        $row = $response->json('data.records.data.0');
        $summary = $response->json('data.summary');

        $this->assertSame(self::USER_ID, (int) $row['user_id']);
        $this->assertSame(self::MT4_LOGIN, (int) $row['mt4_login']);
        $this->assertSame('position-live', $row['mt4_account_group']);
        $this->assertSame(1234.56, (float) $row['mt4_balance']);
        $this->assertSame(1188.50, (float) $row['mt4_equity']);
        $this->assertSame(88.25, (float) $row['mt4_margin']);
        $this->assertSame(1100.25, (float) $row['mt4_margin_free']);
        $this->assertSame(200, (int) $row['mt4_leverage']);
        $this->assertSame(1, (int) $summary['total_mt4_accounts']);
        $this->assertSame(1234.56, (float) $summary['total_balance']);
        $this->assertSame(1188.50, (float) $summary['total_equity']);
        $this->assertSame(88.25, (float) $summary['total_margin']);
        $this->assertSame(1100.25, (float) $summary['total_margin_free']);
    }

    /**
     * 当前筛选 CSV 必须导出真实 MT4 快照，并排除筛选范围外账号。
     *
     * 设计原因：
     * - 列表和导出必须复用同一条 user_infos.mt4_code 映射链路，否则财务下载结果会与页面不一致。
     * - 范围外账号使用明显不同的余额和姓名，证明导出没有绕过用户筛选读取整个 mt4_users 表。
     *
     * @return void
     */
    public function test_position_summary_csv_exports_only_the_filtered_mt4_snapshot(): void
    {
        $admin = $this->ensureSuperAdmin();
        $now = time();

        $this->upsertUser(self::USER_ID, self::MT4_LOGIN, 'Position MT4 Export Included', $now);
        $this->upsertUser(self::OUTSIDE_USER_ID, self::OUTSIDE_MT4_LOGIN, 'Position MT4 Export Outside', $now);
        $this->upsertMt4Account(self::MT4_LOGIN, 'Included MT4 Export Name', 2234.56, 2188.50, 188.25, 2000.25, 300, $now);
        $this->upsertMt4Account(self::OUTSIDE_MT4_LOGIN, 'Outside MT4 Export Name', 19999.99, 18888.88, 1777.77, 17111.11, 500, $now);

        $response = $this->withoutMiddleware([
            AdminAuthenticate::class,
            JwtAuthMiddleware::class,
            SingleSignOn::class,
            CheckPermission::class,
        ])->actingAs($admin, 'admin')->post('/api/admin/exportPositionSummary', [
            'user_id' => self::USER_ID,
        ]);

        $response->assertOk();
        $content = $response->streamedContent();
        $rows = $this->parseCsv($content);
        $this->assertCount(1, $rows, 'CSV 只能包含当前 user_id 筛选命中的一行。');

        $row = $rows[0];
        foreach ([
            'mt4_login',
            'mt4_name',
            'mt4_account_group',
            'mt4_balance',
            'mt4_equity',
            'mt4_margin',
            'mt4_margin_free',
            'mt4_leverage',
            'mt4_registered_at',
            'mt4_snapshot_at',
        ] as $field) {
            $this->assertArrayHasKey($field, $row, 'CSV 缺少 MT4 快照字段：' . $field);
        }

        $this->assertSame((string) self::MT4_LOGIN, $row['mt4_login']);
        $this->assertSame('Included MT4 Export Name', $row['mt4_name']);
        $this->assertSame('position-live', $row['mt4_account_group']);
        $this->assertSame(2234.56, (float) $row['mt4_balance']);
        $this->assertSame(2188.50, (float) $row['mt4_equity']);
        $this->assertSame(188.25, (float) $row['mt4_margin']);
        $this->assertSame(2000.25, (float) $row['mt4_margin_free']);
        $this->assertSame(300, (int) $row['mt4_leverage']);
        $this->assertStringNotContainsString('Outside MT4 Export Name', $content);
        $this->assertStringNotContainsString((string) self::OUTSIDE_MT4_LOGIN, $content);
    }

    /**
     * 三个历史界面入口必须承载 MT4 快照列和当前筛选资金汇总。
     *
     * 执行边界：
     * - Layui 从聚合脚本声明表格列，并从 Blade 的 data-summary-field 更新顶部指标。
     * - CrmUI 由 PageController 输出列与指标，admin.js 必须从 response.data.summary 读取后端汇总。
     * - 已废弃 Naive URL 只允许重定向到真实服务端 Blade 页面，不能维护第二套虚假前端数据源。
     *
     * @return void
     */
    public function test_position_summary_frontends_expose_mt4_snapshot_columns_and_scoped_metrics(): void
    {
        $layuiHtml = $this->get('/admin/position-summary')->assertOk()->getContent();
        $layuiScript = (string) file_get_contents(public_path('js/apps/admin/layui/pages.js'));
        $crmuiHtml = $this->get('/admin-crmui/position-summary')->assertOk()->getContent();
        $crmuiScript = (string) file_get_contents(public_path('js/apps/crmui/admin.js'));

        $snapshotFields = [
            'mt4_login',
            'mt4_name',
            'mt4_account_group',
            'mt4_balance',
            'mt4_equity',
            'mt4_margin',
            'mt4_margin_free',
            'mt4_leverage',
            'mt4_registered_at',
            'mt4_snapshot_at',
        ];
        $summaryFields = [
            'total_mt4_accounts',
            'total_balance',
            'total_equity',
            'total_margin',
            'total_margin_free',
        ];

        foreach ($snapshotFields as $field) {
            $this->assertStringContainsString("field: '" . $field . "'", $layuiScript, 'Layui 缺少 MT4 快照列：' . $field);
            $this->assertStringContainsString('data-key="' . $field . '"', $crmuiHtml, 'CrmUI 缺少 MT4 快照列：' . $field);
        }

        foreach ($summaryFields as $field) {
            $this->assertStringContainsString('data-summary-field="' . $field . '"', $layuiHtml, 'Layui 缺少 MT4 汇总卡片：' . $field);
            $this->assertStringContainsString('data-crmui-metric="' . $field . '"', $crmuiHtml, 'CrmUI 缺少 MT4 汇总指标：' . $field);
        }

        $this->assertStringContainsString('if (value === undefined && data.summary)', $crmuiScript);
        $this->assertStringContainsString('value = data.summary[key]', $crmuiScript);
        $this->assertStringNotContainsString('crmui.fields.mt4_balance', $crmuiHtml, 'CrmUI MT4 字段必须显示已翻译文案。');
        $this->assertStringNotContainsString('crmui.metrics.total_mt4_accounts', $crmuiHtml, 'CrmUI MT4 指标必须显示已翻译文案。');
    }

    /**
     * 迁移审计必须关闭 MT4_USERS 未联动缺口，并保留无法伪造的 MARGIN_RATE 边界。
     *
     * @return void
     */
    public function test_position_summary_audit_records_mt4_linkage_without_claiming_missing_margin_rate(): void
    {
        $audit = (string) file_get_contents(base_path('docs/admin-legacy-migration-gap-audit.md'));
        $checklist = (string) file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md'));

        foreach ([
            'user_infos.mt4_code = mt4_users.login',
            'AdminPositionSummaryMt4AccountLinkageClosureModuleTest',
            'mt4_balance',
            'total_mt4_accounts',
        ] as $evidence) {
            $this->assertStringContainsString($evidence, $audit, '迁移审计缺少 MT4 快照证据：' . $evidence);
        }

        $this->assertStringNotContainsString('剩余边界只保留 `MT4_USERS` 联动', $audit);
        $this->assertStringNotContainsString('剩余缺口只保留 `MT4_USERS` 联动', $audit);
        $this->assertStringContainsString('MARGIN_RATE', $audit, '真实表没有 MARGIN_RATE 的边界必须继续保留。');
        $this->assertStringContainsString('## 372. 2026-07-28', $checklist);
    }

    /**
     * 创建或更新后台超级管理员夹具。
     *
     * @return Admin 可直接用于 admin guard 的管理员模型。
     */
    private function ensureSuperAdmin(): Admin
    {
        $now = time();

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'username' => 'admin-position-summary-mt4-super',
                'email' => 'admin-position-summary-mt4-super@example.test',
                'password' => Hash::make('password'),
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );

        return Admin::query()->findOrFail(1);
    }

    /**
     * 写入业务用户与 MT4 登录号映射。
     *
     * @param int $userId 业务用户编号，对应 user_infos.user_id。
     * @param int $mt4Login MT4 登录号，对应 user_infos.mt4_code。
     * @param string $userName 业务用户名，用于定位接口返回行。
     * @param int $now 固定时间戳，保证测试数据可重复更新。
     * @return void
     */
    private function upsertUser(int $userId, int $mt4Login, string $userName, int $now): void
    {
        DB::table('user_infos')->updateOrInsert(
            ['user_id' => $userId],
            [
                'login_id' => 0,
                'user_name' => $userName,
                'phone' => '',
                'gender' => 1,
                'account_type' => 2,
                'parent_id' => 0,
                'family_tree' => (string) $userId,
                'mt4_code' => $mt4Login,
                'mt4_group' => 'configured-position-group',
                'total_funds' => 0,
                'equity' => 0,
                'effective_credit' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }

    /**
     * 写入真实 MT4 资金快照夹具。
     *
     * @param int $login MT4 登录号。
     * @param string $name MT4 侧姓名。
     * @param float $balance 账户余额。
     * @param float $equity 账户净值。
     * @param float $margin 已用保证金。
     * @param float $marginFree 可用保证金。
     * @param int $leverage 杠杆倍数。
     * @param int $now 快照创建与更新时间戳。
     * @return void
     */
    private function upsertMt4Account(
        int $login,
        string $name,
        float $balance,
        float $equity,
        float $margin,
        float $marginFree,
        int $leverage,
        int $now
    ): void {
        DB::table('mt4_users')->updateOrInsert(
            ['login' => $login],
            [
                'name' => $name,
                'group' => 'position-live',
                'balance' => $balance,
                'equity' => $equity,
                'margin' => $margin,
                'margin_free' => $marginFree,
                'leverage' => $leverage,
                'created_at' => $now - 3600,
                'updated_at' => $now,
                'deleted_at' => null,
            ]
        );
    }

    /**
     * 将流式下载内容解析为按表头关联的 CSV 数据行。
     *
     * @param string $content exportPositionSummary 返回的完整 CSV 文本。
     * @return array<int, array<string, string|null>> 不含表头的数据行；空内容或无数据时返回空数组。
     */
    private function parseCsv(string $content): array
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $content);
        rewind($stream);

        $header = fgetcsv($stream);
        $rows = [];

        while (($values = fgetcsv($stream)) !== false) {
            if ($header === false || count($header) !== count($values)) {
                continue;
            }

            $rows[] = array_combine($header, $values);
        }

        fclose($stream);

        return $rows;
    }
}
