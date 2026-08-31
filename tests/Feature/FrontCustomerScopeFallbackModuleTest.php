<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:53
 */

/**
 * 前端客户-数据范围回退模块测试。
 *
 * 文件功能：
 * - 通过源码静态断言 CustomerController 使用 FrontLegacyData::userScopeIds 获取客户数据范围。
 * - 验证 direct_only 过滤、account_type=2 过滤与用户名校验的写法。
 * - 验证实现未回退到 AgentDescendant 旧查询写法。
 * - 验证最终权限检查清单文档记录了该回退模块。
 *
 * 适用场景：
 * - 防止前端客户列表接口在数据范围实现上回退到旧查询方式的回归测试。
 *
 * 入参例子：
 * - 无请求参数，直接读取 app/Http/Controllers/Front/CustomerController.php 源码断言。
 *
 * 返回值：
 * - 断言通过即测试通过，无业务返回值。
 *
 * 异常或失败场景：
 * - 若源码中缺少 userScopeIds 调用或出现 AgentDescendant 旧写法，测试失败。
 */

namespace Tests\Feature;

use Tests\TestCase;

class FrontCustomerScopeFallbackModuleTest extends TestCase
{
    /**
     * 验证前端客户控制器使用共享的父级树数据范围回退实现。
     *
     * 断言 CustomerController 源码使用 userScopeIds、account_type=2 过滤，
     * 且未导入或使用 AgentDescendant。
     */
    public function test_front_customer_controller_uses_shared_parent_tree_scope_fallback(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/CustomerController.php')) ?: '';

        $this->assertStringContainsString('use App\Models\UserInfo;', $source);
        $this->assertStringContainsString('use App\Support\FrontLegacyData;', $source);
        $this->assertStringContainsString('$directOnly = $request->input(\'direct_only\') == 1;', $source);
        $this->assertStringContainsString('FrontLegacyData::userScopeIds($agentId, false, 2, $directOnly ? true : null)', $source);
        $this->assertStringContainsString('FrontLegacyData::userScopeIds($agentId, false, 2)', $source);
        $this->assertStringContainsString("UserInfo::whereIn('user_id', \$customerIds)", $source);
        $this->assertStringContainsString("->where('account_type', 2)", $source);
        $this->assertStringContainsString("->where('user_name', 'like', '%' . \$request->user_name . '%')", $source);
        $this->assertStringNotContainsString('use App\Models\AgentDescendant;', $source);
        $this->assertStringNotContainsString("AgentDescendant::where('agent_id', \$agentId)", $source);
    }

    /**
     * 验证最终权限检查清单记录了本次数据范围回退模块。
     *
     * 断言清单包含第 165 项、Front\CustomerController、userScopeIds 及本测试类名。
     */
    public function test_final_checklist_records_front_customer_scope_fallback(): void
    {
        $content = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        foreach ([
            '## 165. 2026-07-07 前台客户列表 parent_id 作用域兜底闭环',
            '`Front\\CustomerController`',
            '`FrontLegacyData::userScopeIds`',
            '`user_infos.parent_id`',
            '`agent_descendants`',
            '`FrontCustomerScopeFallbackModuleTest`',
        ] as $expected) {
            $this->assertStringContainsString($expected, $content);
        }
    }
}
