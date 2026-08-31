<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:49
 */

/**
 * 提现列表 status 筛选参数严格校验的闭环测试。
 *
 * 文件功能：
 * - 直接调用 WithdrawController::index，验证非法的 status（如 '1abc'、4）在查询数据库前就被校验拦截。
 * - 验证最终清单文档已记录该筛选校验边界。
 *
 * 适用场景：
 * - 管理员提现列表接口筛选参数校验的回归测试。
 *
 * 入参例子：
 * - POST /api/admin/withdrawList
 *   status: 1abc / 4（非法值）
 *   per_page: 5
 *
 * 返回值：
 * - 响应 code 为 VALIDATION_FAILED。
 * - 校验失败时不会抛出异常、不会触发数据库查询。
 *
 * 异常或失败场景：
 * - 若非法状态值穿透到数据库查询阶段，测试直接 fail。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Controllers\Admin\WithdrawController;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminWithdrawListStatusFilterValidationClosureModuleTest extends TestCase
{
    /**
     * 验证提现列表在查询数据库前拒绝非法 status 筛选值。
     */
    public function test_withdraw_list_rejects_invalid_status_filters_before_querying_database(): void
    {
        foreach (['1abc', 4] as $invalidStatus) {
            $request = Request::create('/api/admin/withdrawList', 'POST', [
                'status' => $invalidStatus,
                'per_page' => 5,
            ]);

            try {
                $response = app(WithdrawController::class)->index($request);
            } catch (\Throwable $exception) {
                $this->fail('Invalid withdraw status reached database query before validation: ' . $exception->getMessage());
            }

            $payload = $response->getData(true);

            $this->assertSame(ResponseCode::VALIDATION_FAILED, $payload['code']);
        }
    }

    /**
     * 验证最终清单文档已记录提现 status 筛选校验边界（## 336）。
     */
    public function test_final_checklist_records_withdraw_status_filter_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 336.', $checklist);
        $this->assertStringContainsString('WithdrawController::index', $checklist);
        $this->assertStringContainsString('/api/admin/withdrawList', $checklist);
        $this->assertStringContainsString('withdraw_records.status', $checklist);
        $this->assertStringContainsString('AdminWithdrawListStatusFilterValidationClosureModuleTest', $checklist);
    }
}
