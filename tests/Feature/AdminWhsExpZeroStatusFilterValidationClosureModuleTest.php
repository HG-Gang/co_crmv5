<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:48
 */

/**
 * 一键清零记录列表 status 筛选参数严格校验的闭环测试。
 *
 * 文件功能：
 * - 直接调用 AdminWhsExpZeroController::recordList，验证非严格 status（如 '1abc'）被校验拦截。
 * - 验证最终清单文档已记录该筛选校验边界。
 *
 * 适用场景：
 * - 管理员一键清零记录列表接口筛选参数校验的回归测试。
 *
 * 入参例子：
 * - POST /api/admin/whsExpZeroRecords
 *   status: 1abc（非法值）
 *   limit: 5
 *
 * 返回值：
 * - 响应 code 为 VALIDATION_FAILED。
 *
 * 异常或失败场景：
 * - 若非法 status 未被拦截而进入查询，断言失败。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Controllers\Admin\AdminWhsExpZeroController;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminWhsExpZeroStatusFilterValidationClosureModuleTest extends TestCase
{
    /**
     * 验证清零记录列表拒绝非严格 status 筛选值。
     */
    public function test_whs_exp_zero_record_list_rejects_non_strict_status_before_querying_database(): void
    {
        $request = Request::create('/api/admin/whsExpZeroRecords', 'POST', [
            'status' => '1abc',
            'limit' => 5,
        ]);

        $response = app(AdminWhsExpZeroController::class)->recordList($request);
        $payload = $response->getData(true);

        $this->assertSame(ResponseCode::VALIDATION_FAILED, $payload['code']);
    }

    /**
     * 验证最终清单文档已记录清零记录 status 筛选校验边界（## 335）。
     */
    public function test_final_checklist_records_whs_exp_zero_status_filter_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 335.', $checklist);
        $this->assertStringContainsString('AdminWhsExpZeroController::recordList', $checklist);
        $this->assertStringContainsString('/api/admin/whsExpZeroRecords', $checklist);
        $this->assertStringContainsString('whs_exp_zeros.status', $checklist);
        $this->assertStringContainsString('AdminWhsExpZeroStatusFilterValidationClosureModuleTest', $checklist);
    }
}
