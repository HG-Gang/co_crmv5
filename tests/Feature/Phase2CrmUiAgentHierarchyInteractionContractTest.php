<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 13:23
 */

/**
 * Phase2CrmUiAgentHierarchyInteractionContractTest
 *
 * 文件功能：
 * - 验证 Phase2 CrmUI 代理层级交互契约：代理行暴露范围内直属代理/客户钻取链接、钻取仅接受正数 parent_id 并强制直属范围、非法 parent_id 不转发代理 API、层级查询仅限钻取页。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class Phase2CrmUiAgentHierarchyInteractionContractTest extends TestCase
{
    public function test_agent_rows_expose_scoped_direct_agent_and_customer_drilldown_links(): void
    {
        $response = $this->withHeader('X-Locale', 'zh-CN')
            ->get('/front-crmui/agent/sub');

        $response->assertOk();
        $response->assertSee('data-crmui-row-link', false);
        $response->assertSee('/front-crmui/agent/sub?parent_id=__ID__&amp;direct_only=1', false);
        $response->assertSee('/front-crmui/agent/customers?parent_id=__ID__&amp;direct_only=1', false);
        $response->assertSee('直属代理');
        $response->assertSee('直属客户');
    }

    /**
     * @dataProvider hierarchyPaths
     */
    public function test_agent_drilldown_accepts_only_a_positive_parent_id_and_forces_direct_scope(string $path): void
    {
        $response = $this->get($path . '?parent_id=123456&direct_only=0');

        $response->assertOk();
        $this->assertSame(
            ['parent_id' => 123456, 'direct_only' => 1],
            $this->defaultFilters($response->getContent())
        );
    }

    /**
     * @dataProvider invalidParentIds
     */
    public function test_invalid_parent_id_is_never_forwarded_to_agent_api(string $parentId): void
    {
        foreach ($this->hierarchyPaths() as [$path]) {
            $response = $this->get($path . '?parent_id=' . rawurlencode($parentId) . '&direct_only=1');

            $response->assertOk();
            $this->assertSame([], $this->defaultFilters($response->getContent()));
        }
    }

    public function test_hierarchy_query_is_ignored_outside_the_agent_drilldown_pages(): void
    {
        $response = $this->get('/front-crmui/dashboard?parent_id=123456&direct_only=1');

        $response->assertOk();
        $this->assertSame([], $this->defaultFilters($response->getContent()));
    }

    public function invalidParentIds(): array
    {
        return [
            'empty' => [''],
            'zero' => ['0'],
            'negative' => ['-1'],
            'decimal' => ['1.5'],
            'text' => ['agent'],
            'overflow' => ['999999999999999999999999999999'],
        ];
    }

    public function hierarchyPaths(): array
    {
        return [
            'direct agents' => ['/front-crmui/agent/sub'],
            'direct customers' => ['/front-crmui/agent/customers'],
        ];
    }

    /** @return array<string, int> */
    private function defaultFilters(string $html): array
    {
        $matched = preg_match("/data-default-filters='([^']*)'/", $html, $matches);
        $this->assertSame(1, $matched, 'CrmUI module page must expose data-default-filters.');

        $json = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }
}
