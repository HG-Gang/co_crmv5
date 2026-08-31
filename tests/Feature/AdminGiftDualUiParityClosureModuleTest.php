<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/19
 * Time: 21:38
 */

/**
 * AdminGiftDualUiParityClosureModuleTest
 *
 * 文件功能：
 * - 验证后台礼品模块 Layui 与 CrmUI 双端页面语义契约：日期筛选与 jQuery 依赖、可访问性标签、CrmUI 不渲染手工收件表单、地址选择默认收货地址、服务端分页与待提交状态。
 * - 输入：路由、控制器、Blade/JS、迁移等项目源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 运行时业务流与 MT4 真实网关同步（由集成与功能测试锁定）。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台礼品 Layui 与 CrmUI 页面语义契约。
 */
class AdminGiftDualUiParityClosureModuleTest extends TestCase
{
    public function test_layui_gift_page_has_real_date_filters_and_explicit_jquery_dependency(): void
    {
        $html = $this->get('/admin/gifts')->assertOk()->getContent();
        $script = file_get_contents(public_path('js/apps/admin/layui/pages.js')) ?: '';

        $this->assertStringContainsString('data-gift-page-mode="all"', $html);
        $this->assertStringContainsString('name="start_date"', $html);
        $this->assertStringContainsString('name="end_date"', $html);
        $this->assertStringContainsString("layui.use(['table', 'form', 'layer', 'jquery']", $script);
        $this->assertStringContainsString('var $ = layui.jquery;', $script);
        $this->assertStringContainsString("document.getElementById('openSendGift')", $script);
        $this->assertStringContainsString('if (openSendGiftButton)', $script);
    }

    public function test_layui_gift_sections_and_tables_have_accessible_label_associations(): void
    {
        $html = $this->get('/admin/gifts')->assertOk()->getContent();

        foreach ([
            ['giftItemsSection', 'giftItemsHeading', 'giftItemTable'],
            ['giftShipmentsSection', 'giftShipmentsHeading', 'giftShipmentTable'],
            ['giftAddressesSection', 'giftAddressesHeading', 'giftAddressTable'],
        ] as [$sectionId, $headingId, $tableId]) {
            $this->assertMatchesRegularExpression(
                '/<section[^>]*id="' . $sectionId . '"[^>]*aria-labelledby="' . $headingId . '"[^>]*>/',
                $html
            );
            $this->assertStringContainsString('id="' . $headingId . '"', $html);
            $this->assertMatchesRegularExpression(
                '/<table[^>]*id="' . $tableId . '"[^>]*aria-labelledby="' . $headingId . '"[^>]*>/',
                $html
            );
        }
    }

    public function test_crmui_shipments_does_not_render_manual_recipient_form(): void
    {
        $html = $this->get('/admin-crmui/gifts')->assertOk()->getContent();

        $this->assertStringContainsString('data-crmui-page="admin.gifts"', $html);
        $this->assertStringContainsString('data-api-url="http://localhost/api/admin/giftShipmentList"', $html);
        foreach (['data-crmui-pagination', 'data-crmui-page-prev', 'data-crmui-page-next', 'data-crmui-page-current'] as $marker) {
            $this->assertStringContainsString($marker, $html);
        }
        $this->assertStringNotContainsString('/api/admin/sendGift', $html);
        $this->assertStringNotContainsString('name="recipients[0][user_id]"', $html);
        $this->assertStringNotContainsString('name="recipients[0][address_id]"', $html);
    }

    public function test_crmui_address_picker_defaults_to_default_addresses_and_is_linked_from_sidebar(): void
    {
        $html = $this->get('/admin-crmui/gift-addresses')->assertOk()->getContent();
        $controller = file_get_contents(app_path('Http/Controllers/CrmUi/Admin/PageController.php')) ?: '';
        $script = file_get_contents(public_path('js/apps/crmui/admin.js')) ?: '';

        $this->assertStringContainsString('data-crmui-page="admin.gift_addresses"', $html);
        $this->assertStringContainsString('data-api-url="http://localhost/api/admin/giftAddressList"', $html);
        $this->assertStringContainsString('data-crmui-gift-recipient-picker="1"', $html);
        $this->assertMatchesRegularExpression(
            '/<form[^>]*data-crmui-form[^>]*data-action-url="http:\/\/localhost\/api\/admin\/sendGift"[^>]*>/',
            $html
        );
        $this->assertStringContainsString('<button class="crmui-button is-primary" type="submit"', $html);
        $this->assertStringContainsString('name="recipients_payload"', $html);
        $this->assertStringContainsString('data-crmui-row-action="select_gift_recipient"', $html);
        $this->assertStringContainsString("$(document).on('submit', '[data-crmui-form]'", $script);
        foreach (['data-crmui-pagination', 'data-crmui-page-prev', 'data-crmui-page-next', 'data-crmui-page-current'] as $marker) {
            $this->assertStringContainsString($marker, $html);
        }
        $this->assertMatchesRegularExpression(
            '/<option value="1"[^>]*selected[^>]*>/',
            $html,
            '发礼地址页必须默认只查询默认地址。'
        );
        $this->assertStringContainsString("['label' => __('crmui.admin.pages.gifts.title'), 'path' => 'gifts'", $controller);
        $this->assertStringContainsString("['label' => __('crmui.admin.pages.gift_addresses.title'), 'path' => 'gift-addresses'", $controller);
    }

    public function test_crmui_module_surface_exposes_server_pagination_and_pending_submit_state(): void
    {
        $zhHtml = $this->get('/admin-crmui/gifts?locale=zh-CN')->assertOk()->getContent();
        $enHtml = $this->get('/admin-crmui/gifts?locale=en')->assertOk()->getContent();
        $script = file_get_contents(public_path('js/apps/crmui/admin.js')) ?: '';

        $this->assertMatchesRegularExpression(
            '/<nav[^>]*data-crmui-pagination[^>]*aria-label="分页导航"[^>]*>/',
            $zhHtml
        );
        $this->assertMatchesRegularExpression(
            '/<nav[^>]*data-crmui-pagination[^>]*aria-label="Pagination"[^>]*>/',
            $enHtml
        );
        foreach (['state.page', 'state.lastPage', 'per_page', 'data-crmui-page-next', 'aria-busy'] as $marker) {
            $this->assertStringContainsString($marker, $script);
        }
        $this->assertStringContainsString("$(document).on('submit', '[data-crmui-form]'", $script);
        $this->assertStringContainsString('if ($form.data(\'requestPending\'))', $script);
        $this->assertStringContainsString('$form.data(\'requestPending\', true);', $script);
        $this->assertStringContainsString('$submit.prop(\'disabled\', true).attr(\'aria-busy\', \'true\');', $script);
        $this->assertStringContainsString('pendingRequest.always(function()', $script);
        $this->assertStringContainsString('$form.removeData(\'requestPending\');', $script);
        $this->assertStringContainsString('$submit.prop(\'disabled\', false).removeAttr(\'aria-busy\');', $script);
    }
}
