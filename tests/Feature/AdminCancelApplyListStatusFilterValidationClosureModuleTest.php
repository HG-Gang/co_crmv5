<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 22:36
 */

/**
 * 文件功能：验证销户申请列表（cancelApplyList）对 status 筛选值的严格校验，
 *           非法筛选值必须在查询数据库之前被拒绝，并核对最终检查清单文档。
 *
 * 适用场景：后台 /api/admin/cancelApplyList 接口的筛选参数校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/cancelApplyList：{status, per_page}
 *
 * 返回值：
 * - 合法 status 返回正常列表数据；
 * - 非法 status（如 '1abc'、2、-2）返回 code=VALIDATION_FAILED。
 *
 * 异常或失败场景：
 * - 非法筛选值若绕过校验进入数据库查询，则测试直接失败（fail）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Controllers\Admin\CancelApplyController;
use App\Models\Admin;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminCancelApplyListStatusFilterValidationClosureModuleTest extends TestCase
{
    // 销户申请列表应在查询数据库前拒绝非法 status 筛选值。
    public function test_cancel_apply_list_rejects_invalid_status_filters_before_querying_database(): void
    {
        $admin = new Admin();
        $admin->id = 1;
        $admin->username = 'cancel-validation-admin';

        foreach (['1abc', 2, -2] as $invalidStatus) {
            $request = Request::create('/api/admin/cancelApplyList', 'POST', [
                'status' => $invalidStatus,
                'per_page' => 20,
            ]);
            $request->setUserResolver(static function ($guard = null) use ($admin) {
                return $guard === 'admin' ? $admin : null;
            });

            try {
                $response = app(CancelApplyController::class)->index($request);
            } catch (\Throwable $exception) {
                $this->fail('Invalid cancel-apply status reached database query before validation: ' . $exception->getMessage());
            }

            $payload = $response->getData(true);

            $this->assertSame(ResponseCode::VALIDATION_FAILED, $payload['code']);
        }
    }

    // 核对最终检查清单文档记录了销户申请状态筛选校验边界。
    public function test_final_checklist_records_cancel_apply_status_filter_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 338.', $checklist);
        $this->assertStringContainsString('CancelApplyController::index', $checklist);
        $this->assertStringContainsString('/api/admin/cancelApplyList', $checklist);
        $this->assertStringContainsString('cancel_applies.status', $checklist);
        $this->assertStringContainsString('AdminCancelApplyListStatusFilterValidationClosureModuleTest', $checklist);
    }
}
