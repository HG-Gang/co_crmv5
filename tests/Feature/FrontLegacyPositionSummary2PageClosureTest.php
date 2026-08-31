<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 22:24
 */

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Feature\Concerns\CreatesLegacySmokeUsers;

/**
 * 旧前台本人交易汇总页面闭环测试。
 *
 * 文件功能：
 * - 固定 GET /user/position/summary2 与现代前台 /front/position/summary2 的页面职责。
 * - 验证页面只提交旧本人汇总接口所需的日期条件，避免误接入代理树汇总接口。
 *
 * 执行结果：
 * - 断言通过表示页面会向 positionSummary2Search 发起 POST 查询，并展示旧版全部汇总列。
 * - 断言失败表示页面路由、数据接口或展示字段与旧项目契约不一致。
 */
class FrontLegacyPositionSummary2PageClosureTest extends TestCase
{
    use CreatesLegacySmokeUsers;

    /**
     * 验证旧入口渲染本人汇总页面及其查询契约。
     *
     * @return void 成功时页面包含起止日期条件、专用 POST 接口和旧版资金及交易汇总字段。
     */
    public function test_legacy_summary2_page_binds_the_dedicated_self_summary_endpoint(): void
    {
        // 旧入口受 legacy.front.auth 保护，必须先创建真实可用会话用户。
        $this->ensureLegacySmokeUser(990001);

        $response = $this->withSession(['suser' => ['user_id' => 990001]])
            ->get('/user/position/summary2?frame=1');

        $response
            ->assertOk()
            ->assertSee('data-api="/user/position/positionSummary2Search"', false)
            ->assertSee('data-method="POST"', false)
            ->assertSee('name="startdate"', false)
            ->assertSee('name="enddate"', false)
            ->assertSee('total_yuerj', false)
            ->assertSee('total_yuecj', false)
            ->assertSee('total_net_worth', false)
            ->assertSee('total_profit', false)
            ->assertSee('total_comm', false)
            ->assertSee('total_swaps', false);
    }

    /**
     * 验证现代前台别名也渲染同一份本人汇总页面。
     *
     * @return void 成功时两个入口使用同一数据接口，避免导航入口和旧兼容入口出现统计口径分叉。
     */
    public function test_modern_summary2_page_uses_the_same_dedicated_view(): void
    {
        // 现代入口的外层页面只负责承载 iframe；frame=1 才是被实际加载的业务 Blade。
        $response = $this->get('/front/position/summary2?frame=1');

        $response
            ->assertOk()
            ->assertSee('data-api="/user/position/positionSummary2Search"', false)
            ->assertSee('name="startdate"', false)
            ->assertSee('name="enddate"', false);
    }

    public function test_crmui_and_naive_summary2_pages_use_the_dedicated_self_summary_definition(): void
    {
        foreach (['/front-crmui/position/summary2', '/front-naive/position/summary2'] as $url) {
            $response = $this->get($url . '?frame=1');

            $response
                ->assertOk()
                ->assertSee('data-crmui-page="front.position_self_summary"', false)
                ->assertSee('data-api-url="http://localhost/api/front/positions/self-summary"', false)
                ->assertSee('data-api-method="GET"', false)
                ->assertSee('name="date_from"', false)
                ->assertSee('name="date_to"', false)
                ->assertSee('data-key="total_yuerj"', false)
                ->assertSee('data-key="total_net_worth"', false)
                ->assertSee('data-key="total_volume"', false)
                ->assertDontSee('name="userId"', false)
                ->assertDontSee('name="userName"', false);
        }
    }

    public function test_modern_self_summary_route_is_restful_and_reuses_position_controller(): void
    {
        $route = Route::getRoutes()->getByName('front_api_positions_self_summary');

        $this->assertNotNull($route);
        $this->assertSame('api/front/positions/self-summary', $route->uri());
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertSame(
            'App\\Http\\Controllers\\Front\\PositionController@selfSummary',
            $route->getActionName()
        );
    }
}
