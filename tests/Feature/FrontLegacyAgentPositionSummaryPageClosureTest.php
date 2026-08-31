<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/01
 * Time: 12:34
 */

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Concerns\CreatesLegacySmokeUsers;

/**
 * 旧前台代理持仓汇总页面闭环测试。
 *
 * 文件功能：
 * - 固定旧代理持仓页的现代 API、下钻动作和详情页初始代理参数。
 * - 防止 `summary/deatil/{id}` 仅渲染页面却丢失旧路由携带的代理 ID。
 *
 * 执行结果：
 * - 断言通过表示普通汇总页可调用真实接口，详情页会把目标代理写入初始筛选条件。
 * - 断言失败表示旧 Blade 页面交互或详情页参数传递发生回归。
 */
class FrontLegacyAgentPositionSummaryPageClosureTest extends TestCase
{
    use CreatesLegacySmokeUsers;

    /**
     * 验证普通页面绑定真实代理持仓 API 和前端下钻动作。
     *
     * @return void 成功时页面声明现代只读接口与 positionSummaryDrill 行为。
     */
    public function test_position_summary_page_binds_the_real_api_and_drill_action(): void
    {
        // 页面由旧前台鉴权保护，先自建真实会话用户再断言渲染契约。
        $this->ensureLegacySmokeUser(990001);

        $response = $this->withSession(['suser' => ['user_id' => 990001]])
            ->get('/user/position/summary?frame=1');

        $response
            ->assertOk()
            ->assertSee('data-api="/api/front/positions/summary"', false)
            ->assertSee('positionSummaryDrill', false)
            ->assertSee('data-default-filters=', false);
    }

    /**
     * 验证旧详情路由把 URL 中的代理 ID 传给初始 userId 查询条件。
     *
     * @return void 成功时详情页面首次加载只查询目标代理，不回退为当前代理根汇总。
     */
    public function test_position_summary_detail_page_preserves_the_legacy_target_agent_id(): void
    {
        $targetAgentId = 513001002;
        $this->ensureLegacySmokeUser(990001);

        $response = $this->withSession(['suser' => ['user_id' => 990001]])
            ->get('/user/position/summary/deatil/' . $targetAgentId . '?frame=1');

        $response
            ->assertOk()
            ->assertSee('"userId":' . $targetAgentId, false);
    }
}
