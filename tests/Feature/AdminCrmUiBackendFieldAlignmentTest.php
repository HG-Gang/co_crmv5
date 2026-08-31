<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 20:50
 */

/**
 * 文件功能：核对 CrmUI 后台各创建表单暴露的字段与后端创建接口真正读取或校验
 *           的字段一致，并校验支付通道、新闻公告列表存在独立行操作按钮。
 *
 * 适用场景：CrmUI 后台页面（roles/menus/data-scopes/gifts/channels/group-configs/
 *           system-configs/big-agents/admins/news）前后端字段对齐回归测试。
 *
 * 入参例子：
 * - GET /admin-crmui/{模块}：页面 HTML；
 * - 数据源 crmuiCreateFormProvider 提供 {uri, requiredFields} 用例集。
 *
 * 返回值：
 * - 页面 HTML 包含全部 requiredFields 的 name 属性、行操作 data 属性时通过。
 *
 * 异常或失败场景：
 * - 任一必填字段缺失（断言失败并提示缺失字段与页面 URI），或行操作未暴露。
 */

namespace Tests\Feature;

use Tests\TestCase;

class AdminCrmUiBackendFieldAlignmentTest extends TestCase
{
    // CrmUI 创建表单必须暴露后端创建接口真正读取或校验的全部字段。
    /**
     * CrmUI 后台创建表单必须暴露后端创建接口真正会读取或校验的字段。
     *
     * @dataProvider crmuiCreateFormProvider
     *
     * @param array<int, string> $requiredFields
     */
    public function test_admin_crmui_create_forms_match_backend_required_fields(string $uri, array $requiredFields): void
    {
        $html = $this->get($uri)->assertOk()->getContent();

        foreach ($requiredFields as $field) {
            $this->assertStringContainsString(
                'name="' . $field . '"',
                $html,
                'Missing CrmUI backend form field [' . $field . '] on ' . $uri
            );
        }
    }

    /**
     * @return array<string, array{uri:string, requiredFields:array<int, string>}>
     */
    public static function crmuiCreateFormProvider(): array
    {
        return [
            'role' => [
                'uri' => '/admin-crmui/roles',
                'requiredFields' => [
                    'name',
                    'guard_type',
                    'description',
                    'status',
                ],
            ],
            'menu' => [
                'uri' => '/admin-crmui/menus',
                'requiredFields' => [
                    'title',
                    'slug',
                    'icon',
                    'path',
                    'api_route',
                    'parent_id',
                    'guard_type',
                    'type',
                    'sort',
                    'status',
                ],
            ],
            'data-scope' => [
                'uri' => '/admin-crmui/data-scopes',
                'requiredFields' => [
                    'role_id',
                    'scope_type',
                    'agent_ids',
                    'user_ids',
                    'status',
                ],
            ],
            'gift-send' => [
                'uri' => '/admin-crmui/gift-addresses',
                'requiredFields' => [
                    'sender_name',
                    'gift_name',
                    'gift_quantity',
                    'tracking_number',
                    'remark',
                    'recipients_payload',
                ],
            ],
            'payment-channel' => [
                'uri' => '/admin-crmui/channels',
                'requiredFields' => [
                    'name',
                    'channel_code',
                    'exchange_rate',
                    'is_enabled',
                    'sort',
                    'config',
                ],
            ],
            'group-config' => [
                'uri' => '/admin-crmui/group-configs',
                'requiredFields' => [
                    'group_name',
                    'radix',
                    'category',
                    'has_commission',
                    'is_enabled',
                    'is_ecn',
                    'is_default',
                ],
            ],
            'system-config' => [
                'uri' => '/admin-crmui/system-configs',
                'requiredFields' => [
                    'key',
                    'value',
                    'group',
                    'description',
                ],
            ],
            'big-agent' => [
                'uri' => '/admin-crmui/big-agents',
                'requiredFields' => [
                    'username',
                    'password',
                    'is_enabled',
                ],
            ],
            'admin-account' => [
                'uri' => '/admin-crmui/admins',
                'requiredFields' => [
                    'username',
                    'email',
                    'password',
                ],
            ],
        ];
    }

    // CrmUI 支付通道列表必须暴露独立启用/禁用行操作。
    /**
     * CrmUI 支付通道列表必须暴露独立启用/禁用行操作。
     *
     * @return void
     */
    public function test_admin_crmui_payment_channel_page_contains_toggle_action(): void
    {
        $html = $this->get('/admin-crmui/channels')->assertOk()->getContent();

        $this->assertStringContainsString('data-crmui-row-action="toggle"', $html);
        $this->assertStringContainsString('admin_api_toggleChannel', file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php')) ?: '');
    }

    // CrmUI 新闻公告列表必须暴露独立发布/下架行操作。
    /**
     * CrmUI 新闻公告列表必须暴露独立发布/下架行操作。
     *
     * @return void
     */
    public function test_admin_crmui_news_page_contains_toggle_action(): void
    {
        $html = $this->get('/admin-crmui/news')->assertOk()->getContent();

        $this->assertStringContainsString('data-crmui-row-action="toggle"', $html);
        $this->assertStringContainsString('admin_api_toggleNews', file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php')) ?: '');
    }
}
