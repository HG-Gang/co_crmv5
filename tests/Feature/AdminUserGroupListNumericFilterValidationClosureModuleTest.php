<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:56
 */

/**
 * 后台用户分组列表接口数值筛选参数严格校验的功能测试。
 *
 * 文件功能：
 * - 验证 group_type、is_enabled 等数值筛选传入非严格数字时列表接口返回校验失败。
 * - 验证校验失败时不返回测试分组数据。
 *
 * 适用场景：
 * - 后台用户分组管理列表的条件筛选，防止非法数值注入查询。
 *
 * 入参例子：
 * - POST /api/admin/userGroupList，body：{"group_type": "2abc", "per_page": 20}。
 *
 * 返回值：
 * - 校验失败返回 code=ResponseCode::VALIDATION_FAILED。
 *
 * 异常或失败场景：
 * - group_type、is_enabled 为非严格整数时接口拒绝查询并返回校验失败响应。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use App\Http\Controllers\Admin\UserGroupController;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminUserGroupListNumericFilterValidationClosureModuleTest extends TestCase
{
    /**
     * 夹具用户分组的名称标记。断言非法 group_type 筛选被拒绝且不返回该分组。
     * @var string
     */
    private const TEST_GROUP_NAME = 'user-group-numeric-validation-333';

    // 验证用户分组列表对非严格 group_type 筛选返回校验失败且不返回测试分组。
    public function test_user_group_list_rejects_non_strict_group_type_filter_without_returning_group(): void
    {
        $request = Request::create('/api/admin/userGroupList', 'POST', [
            'group_type' => '2abc',
            'per_page' => 20,
        ]);

        $response = app(UserGroupController::class)->index($request);
        $payload = $response->getData(true);

        $this->assertSame(ResponseCode::VALIDATION_FAILED, $payload['code']);
        $this->assertStringNotContainsString(self::TEST_GROUP_NAME, $response->getContent());
    }

    // 验证用户分组列表对非严格 is_enabled 筛选返回校验失败且不返回测试分组。
    public function test_user_group_list_rejects_non_strict_is_enabled_filter_without_returning_group(): void
    {
        $request = Request::create('/api/admin/userGroupList', 'POST', [
            'is_enabled' => '1abc',
            'per_page' => 20,
        ]);

        $response = app(UserGroupController::class)->index($request);
        $payload = $response->getData(true);

        $this->assertSame(ResponseCode::VALIDATION_FAILED, $payload['code']);
        $this->assertStringNotContainsString(self::TEST_GROUP_NAME, $response->getContent());
    }

    // 校验最终检查清单文档记录了用户分组列表数值筛选校验边界。
    public function test_final_checklist_records_user_group_list_numeric_filter_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 333.', $checklist);
        $this->assertStringContainsString('UserGroupController::index', $checklist);
        $this->assertStringContainsString('/api/admin/userGroupList', $checklist);
        $this->assertStringContainsString('group_configs.category', $checklist);
        $this->assertStringContainsString('group_configs.is_enabled', $checklist);
        $this->assertStringContainsString('AdminUserGroupListNumericFilterValidationClosureModuleTest', $checklist);
    }
}
