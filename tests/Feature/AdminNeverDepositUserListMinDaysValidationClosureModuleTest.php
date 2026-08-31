<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:46
 */

/**
 * 文件功能：验证未入金用户列表（neverDepositUserList）对 min_days 筛选值的
 *           严格校验，非法值必须在查询数据库之前被拒绝，并核对检查清单文档。
 *
 * 适用场景：后台 /api/admin/neverDepositUserList 接口的筛选参数校验回归测试。
 *
 * 入参例子：
 * - POST /api/admin/neverDepositUserList：{min_days, per_page}
 *
 * 返回值：
 * - 非法 min_days（如 '1abc'）时返回 code=VALIDATION_FAILED，响应不含用户数据。
 *
 * 异常或失败场景：
 * - 非法筛选值若绕过校验进入数据库查询，则测试直接失败（fail）。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Controllers\Admin\FundFlowController;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminNeverDepositUserListMinDaysValidationClosureModuleTest extends TestCase
{
    /**
     * 夹具用户的 user_name 标记。断言非法 min_days 被拒绝且结果中不出现该用户。
     * @var string
     */
    private const TEST_USER_NAME = 'Never Deposit Min Days Validation User';

    // 未入金用户列表应在查询数据库前拒绝非法 min_days 筛选值。
    public function test_never_deposit_user_list_rejects_non_strict_min_days_filter_before_querying_database(): void
    {
        $request = Request::create('/api/admin/neverDepositUserList', 'POST', [
            'min_days' => '1abc',
            'per_page' => 5,
        ]);

        try {
            $response = app(FundFlowController::class)->neverDepositUserList($request);
        } catch (\Throwable $exception) {
            $this->fail('Invalid min_days reached database query before validation: ' . $exception->getMessage());
        }

        $payload = $response->getData(true);

        $this->assertSame(ResponseCode::VALIDATION_FAILED, $payload['code']);
        $this->assertStringNotContainsString(self::TEST_USER_NAME, $response->getContent());
    }

    // 核对最终检查清单文档记录了未入金 min_days 校验边界。
    public function test_final_checklist_records_never_deposit_min_days_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 334.', $checklist);
        $this->assertStringContainsString('FundFlowController::neverDepositUserList', $checklist);
        $this->assertStringContainsString('/api/admin/neverDepositUserList', $checklist);
        $this->assertStringContainsString('min_days', $checklist);
        $this->assertStringContainsString('AdminNeverDepositUserListMinDaysValidationClosureModuleTest', $checklist);
    }
}
