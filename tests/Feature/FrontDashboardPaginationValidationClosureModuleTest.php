<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:53
 */

/**
 * 前端仪表盘分页参数校验-封闭模块测试。
 *
 * 文件功能：
 * - 验证旧接口 /user/main/hot/news 对非法 page 值返回 VALIDATION_FAILED。
 * - 验证注册页接口 /user/register/hotnews 对非法 page/limit 值返回 VALIDATION_FAILED。
 * - 验证最终权限检查清单文档记录了该校验闭环。
 *
 * 适用场景：
 * - 仪表盘热门资讯接口分页参数校验的回归测试，防止非法参数触发查询或异常。
 *
 * 入参例子：
 * - POST /user/main/hot/news（body: { "page": "1abc" | 0 | -1 }）
 * - GET /user/register/hotnews?page=1&limit=51（或 page=1abc、limit=10abc、limit=0）
 *
 * 返回值：
 * - 各非法参数均返回 HTTP 200，业务 code 为 VALIDATION_FAILED。
 *
 * 异常或失败场景：
 * - 若非法参数未被拒绝（返回非 VALIDATION_FAILED），测试失败。
 */

namespace Tests\Feature;

use App\Constants\ResponseCode;
use Tests\TestCase;

class FrontDashboardPaginationValidationClosureModuleTest extends TestCase
{
    /**
     * 验证旧热门资讯接口在查询前拒绝非法 page 值。
     *
     * 依次以 1abc、0、-1 作为 page 请求，断言均返回 VALIDATION_FAILED。
     */
    public function test_legacy_hot_news_rejects_invalid_page_before_querying_news(): void
    {
        foreach (['1abc', 0, -1] as $invalidPage) {
            $this->postJson('/user/main/hot/news', ['page' => $invalidPage])
                ->assertOk()
                ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        }
    }

    /**
     * 验证注册页热门资讯接口在查询前拒绝非法 page 与 limit 值。
     *
     * 依次以非法 page、limit 组合请求，断言均返回 VALIDATION_FAILED。
     */
    public function test_register_hot_news_rejects_invalid_page_and_limit_before_querying_news(): void
    {
        foreach ([
            ['page' => '1abc', 'limit' => 10],
            ['page' => 1, 'limit' => '10abc'],
            ['page' => 1, 'limit' => 0],
            ['page' => 1, 'limit' => 51],
        ] as $query) {
            $this->getJson('/user/register/hotnews?' . http_build_query($query))
                ->assertOk()
                ->assertJsonPath('code', ResponseCode::VALIDATION_FAILED);
        }
    }

    /**
     * 验证最终权限检查清单记录了本次分页校验闭环。
     *
     * 断言清单包含第 344 项、DashboardController 相关方法及接口路径和本测试类名。
     */
    public function test_final_checklist_records_dashboard_pagination_validation_boundary(): void
    {
        $checklist = file_get_contents(base_path('docs/admin-backend-blade-permission-final-checklist.md')) ?: '';

        $this->assertStringContainsString('## 344.', $checklist);
        $this->assertStringContainsString('DashboardController::hotNews', $checklist);
        $this->assertStringContainsString('DashboardController::hotNewsV2', $checklist);
        $this->assertStringContainsString('user/main/hot/news', $checklist);
        $this->assertStringContainsString('user/register/hotnews', $checklist);
        $this->assertStringContainsString('FrontDashboardPaginationValidationClosureModuleTest', $checklist);
    }
}
