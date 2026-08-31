<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:48
 */

/**
 * 凭证列表 review_status 筛选参数严格校验的闭环测试。
 *
 * 文件功能：
 * - 直接调用 VoucherController::index，验证非法的 review_status（如 '1abc'、3、-1）在查询数据库前就被校验拦截。
 * - 验证最终清单文档已记录该筛选校验边界。
 *
 * 适用场景：
 * - 管理员凭证列表接口筛选参数校验的回归测试。
 *
 * 入参例子：
 * - POST /api/admin/voucherList
 *   review_status: 1abc / 3 / -1（非法值）
 *   per_page: 20
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
use App\Http\Controllers\Admin\VoucherController;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminVoucherListReviewStatusFilterValidationClosureModuleTest extends TestCase
{
    /**
     * 验证凭证列表在查询数据库前拒绝非法 review_status 筛选值。
     */
    public function test_voucher_list_rejects_invalid_review_status_filters_before_querying_database(): void
    {
        foreach (['1abc', 3, -1] as $invalidReviewStatus) {
            $request = Request::create('/api/admin/voucherList', 'POST', [
                'review_status' => $invalidReviewStatus,
                'per_page' => 20,
            ]);

            try {
                $response = app(VoucherController::class)->index($request);
            } catch (\Throwable $exception) {
                $this->fail('Invalid voucher review_status reached database query before validation: ' . $exception->getMessage());
            }

            $payload = $response->getData(true);

            $this->assertSame(ResponseCode::VALIDATION_FAILED, $payload['code']);
        }
    }

    /**
     * 验证最终清单文档已记录凭证 review_status 筛选校验边界（## 339）。
     */
    public function test_final_checklist_records_voucher_review_status_filter_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 339.', $checklist);
        $this->assertStringContainsString('VoucherController::index', $checklist);
        $this->assertStringContainsString('/api/admin/voucherList', $checklist);
        $this->assertStringContainsString('voucher_infos.review_status', $checklist);
        $this->assertStringContainsString('AdminVoucherListReviewStatusFilterValidationClosureModuleTest', $checklist);
    }
}
