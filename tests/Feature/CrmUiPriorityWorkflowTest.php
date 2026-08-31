<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/17
 * Time: 01:53
 */

/**
 * CrmUI 优先页面真实工作流控件测试。
 *
 * 文件功能：
 * - 验证前台/后台优先页面渲染真实的表单控件（auth、profile、deposit、withdraw、flow、order）。
 * - 验证页面使用真实后端字段名（user_name、id_card_no、bank_addr 等）而非演示占位名。
 * - 验证筛选下拉渲染为真实 select、金额为 number、日期为 date 控件。
 * - 验证行操作（审批、驳回、处理、完成）与权限接口绑定正确。
 * - 验证文案双语（zh-CN/en）翻译存在、页面不渲染原始翻译键、支持 locale 切换。
 *
 * 适用场景：
 * - CrmUI 页面与后端接口/字段契约的回归测试。
 *
 * 入参例子：
 * - GET /front-crmui/login、/admin-crmui/users、/admin-crmui/deposits 等页面。
 *
 * 返回值：
 * - 各页面断言通过表示控件、字段、翻译均符合契约。
 *
 * 异常或失败场景：
 * - 页面缺控件、字段名不符、出现原始翻译键或缺失翻译时断言失败。
 */

namespace Tests\Feature;

use Tests\TestCase;

class CrmUiPriorityWorkflowTest extends TestCase
{
    /**
     * 验证前台优先页面暴露真实工作流控件与接口绑定。
     */
    public function test_front_priority_pages_expose_real_workflow_controls(): void
    {
        $this->get('/front-crmui/login')
            ->assertOk()
            ->assertSee('data-crmui-auth="front-login"', false)
            ->assertSee('/api/front/auth/login', false)
            ->assertSee('name="account"', false)
            ->assertSee('name="password"', false);

        $this->get('/front-crmui/register')
            ->assertOk()
            ->assertSee('data-crmui-auth="front-register"', false)
            ->assertSee('/api/front/auth/register', false)
            ->assertSee('name="account_type" value="1"', false)
            ->assertSee('name="account_type" value="2"', false)
            ->assertSee('name="commission_mode"', false)
            ->assertSee('name="inviter_id"', false)
            ->assertSee('name="phone_code"', false)
            ->assertSee('name="phone_number"', false)
            ->assertSee('name="id_card_no"', false)
            ->assertSee('name="captcha_key"', false)
            ->assertSee('name="captcha_code"', false)
            ->assertSee('name="agree_terms"', false)
            ->assertSee('/api/front/auth/register/captcha', false)
            ->assertSee('data-crmui-captcha', false)
            ->assertDontSee('name="email_code"', false)
            ->assertDontSee('data-crmui-secondary-action="send-email-code"', false);

        $this->get('/front-crmui/forgot-password')
            ->assertOk()
            ->assertSee('data-crmui-auth="front-forgot-password"', false)
            ->assertSee('/api/front/auth/password/reset', false)
            ->assertSee('/api/front/auth/password/email-code', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="code"', false)
            ->assertSee('data-crmui-secondary-action="send-email-code"', false)
            ->assertDontSee('name="email_code"', false);

        $this->get('/front-crmui/profile')
            ->assertOk()
            ->assertSee('data-crmui-page="front.profile"', false)
            ->assertSee('data-crmui-upload="avatar"', false)
            ->assertSee('/api/front/profile/avatar', false)
            ->assertSee('data-crmui-panel="identity"', false)
            ->assertSee('name="id_card_front"', false)
            ->assertSee('name="id_card_back"', false)
            ->assertSee('/api/front/profile/identity', false)
            ->assertSee('data-crmui-panel="bank-card"', false)
            ->assertSee('/api/front/profile/bank-card', false)
            ->assertSee('/api/front/profile/contact-info', false);

        $this->get('/front-crmui/deposit')
            ->assertOk()
            ->assertSee('/api/front/deposits/form-options', false)
            ->assertSee('/api/front/deposits/submissions', false)
            ->assertSee('name="amount"', false)
            ->assertSee('name="channel"', false)
            ->assertSee('data-crmui-money-preview', false);

        $this->get('/front-crmui/withdraw')
            ->assertOk()
            ->assertSee('/api/front/withdrawals/form-options', false)
            ->assertSee('/api/front/withdrawals/submissions', false)
            ->assertSee('name="amount"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="agree"', false);

        $this->get('/front-crmui/flow')
            ->assertOk()
            ->assertSee('data-crmui-view="deposits"', false)
            ->assertSee('data-crmui-view="withdrawals"', false)
            ->assertSee('data-crmui-view="withdrawal_applications"', false)
            ->assertSee('/api/front/flows/direct-deposits', false)
            ->assertSee('/api/front/flows/direct-withdrawals', false);

        foreach (['order/open', 'order/closed'] as $path) {
            $this->get('/front-crmui/' . $path)
                ->assertOk()
                ->assertSee('data-key="ticket"', false)
                ->assertSee('data-action="showOrderInfo"', false)
                ->assertDontSee('data-crmui-row-action="detail"', false);
        }
    }

    /**
     * 验证优先页面使用真实后端字段名而非演示占位名。
     */
    public function test_crmui_priority_pages_use_real_backend_payload_fields(): void
    {
        $this->get('/front-crmui/profile')
            ->assertOk()
            ->assertSee('name="user_name"', false)
            ->assertSee('name="id_card_no"', false)
            ->assertSee('name="bank_addr"', false)
            ->assertSee('data-crmui-avatar-upload', false)
            ->assertDontSee('name="name"', false)
            ->assertDontSee('name="id_card"', false)
            ->assertDontSee('name="bank_card_img_back"', false);

        $this->get('/front-crmui/withdraw')
            ->assertOk()
            ->assertSee('name="amount"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="agree"', false)
            ->assertDontSee('name="bank_card"', false);

        $this->get('/admin-crmui/users')
            ->assertOk()
            ->assertSee('name="email"', false)
            ->assertSee('name="user_name"', false)
            ->assertSee('name="account_type"', false)
            ->assertSee('name="start_date" type="date"', false)
            ->assertSee('name="end_date" type="date"', false)
            ->assertSee('data-crmui-row-action="change_status"', false)
            ->assertSee('data-record-key="user_id"', false)
            ->assertSee('data-payload-name="user_id"', false)
            ->assertSee('name:is_enabled:select', false)
            ->assertSee('data-crmui-row-action="review_auth"', false)
            ->assertSee('name:id_card_decision:select', false)
            ->assertSee('name:id_card_reason:textarea', false)
            ->assertSee('name:bank_decision:select', false)
            ->assertSee('name:bank_reason:textarea', false)
            ->assertDontSee('name:status:select', false)
            ->assertDontSee('name:reason:textarea', false)
            ->assertDontSee('name:review_status:select', false)
            ->assertDontSee('name:remark:text', false);

        $this->get('/admin-crmui/deposits')
            ->assertOk()
            ->assertSee('name="local_order_no"', false)
            ->assertSee('name="user_id"', false)
            ->assertSee('name:reason:textarea', false)
            ->assertDontSee('name:reject_reason:text', false);

        $this->get('/admin-crmui/withdrawals')
            ->assertOk()
            ->assertSee('name="local_order_no"', false)
            ->assertSee('name="user_id"', false)
            ->assertSee('name:reason:textarea', false)
            ->assertDontSee('name:reject_reason:text', false);

        $this->get('/admin-crmui/authentications')
            ->assertOk()
            ->assertSee('name:id_card_decision:select', false)
            ->assertSee('name:id_card_reason:textarea', false)
            ->assertSee('name:bank_decision:select', false)
            ->assertSee('name:bank_reason:textarea', false)
            ->assertDontSee('name:status:select', false)
            ->assertDontSee('name:reason:textarea', false)
            ->assertDontSee('name:review_status:select', false);

        $this->get('/admin-crmui/agents')
            ->assertOk()
            ->assertSee('name="agent_id"', false)
            ->assertSee('name="user_name"', false)
            ->assertSee('name="start_date" type="date"', false)
            ->assertSee('name="end_date" type="date"', false)
            ->assertSee('data-record-key="user_id"', false)
            ->assertSee('data-payload-name="agent_id"', false)
            ->assertSee('name:comm_rate:number', false)
            ->assertDontSee('name:commission_rate:text', false);

        $this->get('/admin-crmui/permissions')
            ->assertOk()
            ->assertSee('name="slug"', false)
            ->assertSee('name="guard_type"', false)
            ->assertSee('name="type"', false)
            ->assertSee('name="api_route"', false)
            ->assertSee('name="route"', false)
            ->assertDontSee('name="code"', false);

        $this->get('/admin-crmui/risk')
            ->assertOk()
            ->assertSee('name="ticket"', false)
            ->assertSee('name="start_date"', false)
            ->assertSee('name="end_date"', false)
            ->assertSee('name="max_margin_level"', false)
            ->assertSee('name="login_ip"', false);

        $this->get('/admin-crmui/profile/edit')
            ->assertOk()
            ->assertSee('name="email"', false)
            ->assertSee('name="mobile"', false)
            ->assertDontSee('name="phone"', false)
            ->assertDontSee('name="name"', false);
    }

    /**
     * 验证筛选下拉渲染为真实 select 控件。
     */
    public function test_crmui_select_filters_render_as_real_select_controls(): void
    {
        $users = $this->get('/admin-crmui/users')->assertOk()->getContent();
        $this->assertStringContainsString('<select class="crmui-input" name="account_type"', $users);
        $this->assertStringContainsString('<option value="1"', $users);
        $this->assertStringContainsString('<option value="2"', $users);
        $this->assertStringNotContainsString('name="account_type" type="select"', $users);

        $permissions = $this->get('/admin-crmui/permissions')->assertOk()->getContent();
        $this->assertStringContainsString('<select class="crmui-input" name="guard_type"', $permissions);
        $this->assertStringContainsString('<select class="crmui-input" name="type"', $permissions);
        $this->assertStringContainsString('<select class="crmui-input" name="status"', $permissions);
        $this->assertStringNotContainsString('name="guard_type" type="select"', $permissions);
    }

    /**
     * 验证后台优先筛选使用业务 select 与 date 控件。
     */
    public function test_admin_priority_filters_use_business_selects_and_dates(): void
    {
        $deposits = $this->get('/admin-crmui/deposits')->assertOk()->getContent();
        $this->assertStringContainsString('<select class="crmui-input" name="status"', $deposits);
        $this->assertStringContainsString('<option value="01"', $deposits);
        $this->assertStringContainsString('<option value="02"', $deposits);
        $this->assertStringContainsString('<option value="09"', $deposits);
        $this->assertStringNotContainsString('name="status" type="text"', $deposits);

        $withdrawals = $this->get('/admin-crmui/withdrawals')->assertOk()->getContent();
        $this->assertStringContainsString('<select class="crmui-input" name="status"', $withdrawals);
        $this->assertStringContainsString('<option value="0"', $withdrawals);
        $this->assertStringContainsString('<option value="1"', $withdrawals);
        $this->assertStringContainsString('<option value="2"', $withdrawals);
        $this->assertStringContainsString('<option value="3"', $withdrawals);
        $this->assertStringNotContainsString('name="status" type="text"', $withdrawals);

        $authentications = $this->get('/admin-crmui/authentications')->assertOk()->getContent();
        $this->assertStringContainsString('<select class="crmui-input" name="auth_status"', $authentications);
        $this->assertStringContainsString('<option value="1"', $authentications);
        $this->assertStringContainsString('<option value="2"', $authentications);
        $this->assertStringNotContainsString('name="status"', $authentications);

        $risk = $this->get('/admin-crmui/risk')->assertOk()->getContent();
        $this->assertStringContainsString('name="start_date" type="date"', $risk);
        $this->assertStringContainsString('name="end_date" type="date"', $risk);
        $this->assertStringContainsString('name="max_margin_level" type="number"', $risk);
        $this->assertStringContainsString('name="min_user_count" type="number"', $risk);
        $this->assertStringNotContainsString('name="start_date" type="text"', $risk);
        $this->assertStringNotContainsString('name="end_date" type="text"', $risk);
        $this->assertStringNotContainsString('name="max_margin_level" type="text"', $risk);
        $this->assertStringNotContainsString('name="min_user_count" type="text"', $risk);
    }

    /**
     * 验证前台优先筛选使用业务 select 与 numeric 金额控件。
     */
    public function test_front_priority_filters_use_business_selects_and_numeric_amounts(): void
    {
        $deposit = $this->get('/front-crmui/deposit')->assertOk()->getContent();
        $this->assertStringContainsString('name="amount" type="number"', $deposit);
        $this->assertStringContainsString('<select class="crmui-input" name="status"', $deposit);
        $this->assertStringContainsString('<option value="01"', $deposit);
        $this->assertStringContainsString('<option value="02"', $deposit);
        $this->assertStringContainsString('<option value="09"', $deposit);
        $this->assertStringNotContainsString('name="keyword"', $deposit);

        $withdraw = $this->get('/front-crmui/withdraw')->assertOk()->getContent();
        $this->assertStringContainsString('name="amount" type="number"', $withdraw);
        $this->assertStringContainsString('<select class="crmui-input" name="status"', $withdraw);
        $this->assertStringContainsString('<option value="0"', $withdraw);
        $this->assertStringContainsString('<option value="1"', $withdraw);
        $this->assertStringContainsString('<option value="2"', $withdraw);
        $this->assertStringContainsString('<option value="3"', $withdraw);
        $this->assertStringNotContainsString('name="keyword"', $withdraw);

        $flow = $this->get('/front-crmui/flow')->assertOk()->getContent();
        $this->assertStringContainsString('<select class="crmui-input" name="flow_type"', $flow);
        $this->assertStringContainsString('<option value="all"', $flow);
        $this->assertStringContainsString('<option value="deposit"', $flow);
        $this->assertStringContainsString('<option value="withdraw"', $flow);
        $this->assertStringContainsString('<option value="direct_deposit"', $flow);
        $this->assertStringContainsString('<option value="direct_withdraw"', $flow);
        $this->assertStringNotContainsString('name="flow_type" type="text"', $flow);
    }

    /**
     * 验证后台优先页面暴露审核与操作控件并绑定真实接口。
     */
    public function test_admin_priority_pages_expose_review_and_operation_controls(): void
    {
        $this->get('/admin-crmui/users')
            ->assertOk()
            ->assertSee('data-crmui-page="admin.users"', false)
            ->assertSee('data-crmui-row-action="detail"', false)
            ->assertSee('data-crmui-row-action="change_status"', false)
            ->assertSee('/api/admin/changeUserStatus', false)
            ->assertSee('data-crmui-row-action="review_auth"', false)
            ->assertSee('/api/admin/reviewAuth', false);

        $this->get('/admin-crmui/deposits')
            ->assertOk()
            ->assertSee('data-crmui-row-action="approve"', false)
            ->assertSee('/api/admin/depositApprove', false)
            ->assertSee('data-crmui-row-action="reject"', false)
            ->assertSee('/api/admin/depositReject', false);

        $this->get('/admin-crmui/withdrawals')
            ->assertOk()
            ->assertSee('data-crmui-row-action="process"', false)
            ->assertSee('/api/admin/withdrawProcess', false)
            ->assertSee('data-crmui-row-action="complete"', false)
            ->assertSee('/api/admin/withdrawComplete', false)
            ->assertSee('data-crmui-row-action="reject"', false)
            ->assertSee('/api/admin/withdrawReject', false);

        $this->get('/admin-crmui/authentications')
            ->assertOk()
            ->assertSee('data-crmui-view="pending"', false)
            ->assertSee('data-crmui-view="certified"', false)
            ->assertSee('/api/admin/authPendingList', false)
            ->assertSee('/api/admin/authCertifiedList', false)
            ->assertSee('data-crmui-row-action="review"', false)
            ->assertSee('/api/admin/reviewAuth', false);

        $this->get('/admin-crmui/agents')
            ->assertOk()
            ->assertSee('data-crmui-row-action="descendants"', false)
            ->assertSee('/api/admin/agentDescendants', false)
            ->assertSee('data-crmui-row-action="update_level"', false)
            ->assertSee('/api/admin/updateAgentLevel', false)
            ->assertSee('data-crmui-row-action="update_commission"', false)
            ->assertSee('/api/admin/updateAgentCommission', false);

        $this->get('/admin-crmui/permissions')
            ->assertOk()
            ->assertSee('/api/admin/permissions/tree', false)
            ->assertSee('/api/admin/createPermission', false)
            ->assertSee('data-crmui-row-action="update"', false)
            ->assertSee('/api/admin/updatePermission', false)
            ->assertSee('data-crmui-row-action="delete"', false)
            ->assertSee('/api/admin/deletePermission', false);

        $this->get('/admin-crmui/risk')
            ->assertOk()
            ->assertSee('data-crmui-view="positions"', false)
            ->assertSee('/api/admin/riskPositions', false)
            ->assertSee('data-crmui-view="margin_calls"', false)
            ->assertSee('/api/admin/riskMarginCalls', false)
            ->assertSee('data-crmui-view="ip_risk"', false)
            ->assertSee('/api/admin/riskIpList', false)
            ->assertSee('data-crmui-row-action="force_close"', false)
            ->assertSee('/api/admin/riskForceClose/__ID__', false);
    }

    /**
     * 验证前端 JS 支持模态框、上传、标签页与行操作。
     */
    public function test_crmui_javascript_supports_modal_upload_tabs_and_row_actions(): void
    {
        $frontJs = file_get_contents(public_path('js/apps/crmui/front.js')) ?: '';
        $adminJs = file_get_contents(public_path('js/apps/crmui/admin.js')) ?: '';
        $combined = $frontJs . $adminJs;

        $this->assertStringContainsString('bindViewTabs', $combined);
        $this->assertStringContainsString('bindUploads', $combined);
        $this->assertStringContainsString('bindRowActions', $combined);
        $this->assertStringContainsString('openActionModal', $combined);
        $this->assertStringContainsString('submitRowAction', $combined);
        $this->assertStringContainsString('FormData', $combined);
        $this->assertStringContainsString('bindCaptcha', $frontJs);
        $this->assertStringContainsString('bindPageActions', $combined);
        $this->assertStringContainsString('focusCreateForm', $combined);
        $this->assertStringContainsString('exportPage', $combined);
        $this->assertStringContainsString('X-Locale', $combined);
    }

    /**
     * 验证业务错误码在成功处理器运行前被拒绝。
     */
    public function test_crmui_javascript_rejects_business_error_codes_before_success_handlers_run(): void
    {
        $frontJs = file_get_contents(public_path('js/apps/crmui/front.js')) ?: '';
        $adminJs = file_get_contents(public_path('js/apps/crmui/admin.js')) ?: '';

        foreach ([$frontJs, $adminJs] as $source) {
            $this->assertStringContainsString('businessCodeSucceeded', $source);
            $this->assertStringContainsString('$.Deferred().reject', $source);
            $this->assertMatchesRegularExpression('/Number\((?:response\.code|rawCode)\)/', $source);
            $this->assertStringContainsString('1000, 1001, 1002, 1003, 1004', $source);
            $this->assertStringContainsString('3000, 3002, 3004, 3005', $source);
            $this->assertStringNotContainsString('code >= 1000 && code < 2000', $source);
            $this->assertStringNotContainsString('code >= 3000 && code < 4000', $source);
        }
    }

    /**
     * 验证前台充值表单成功后打开返回的支付链接。
     */
    public function test_crmui_front_deposit_form_success_opens_returned_payment_url(): void
    {
        $frontJs = file_get_contents(public_path('js/apps/crmui/front.js')) ?: '';

        $this->assertStringContainsString('function handleFormSuccess($form, response)', $frontJs);
        $this->assertStringContainsString('function paymentUrlFromPayload(payload)', $frontJs);
        $this->assertStringContainsString("data-crmui-page') === 'front.deposit'", $frontJs);
        $this->assertStringContainsString('payment_url', $frontJs);
        $this->assertStringContainsString('open_blank', $frontJs);
        $this->assertStringContainsString("window.open(paymentUrl, '_blank', 'noopener')", $frontJs);
        $this->assertStringContainsString('window.location.href = paymentUrl', $frontJs);
    }

    /**
     * 验证动态下拉尊重后端默认选项。
     */
    public function test_crmui_dynamic_selects_honor_backend_default_options(): void
    {
        $sources = [
            'front' => file_get_contents(public_path('js/apps/crmui/front.js')) ?: '',
            'admin' => file_get_contents(public_path('js/apps/crmui/admin.js')) ?: '',
        ];

        foreach ($sources as $name => $source) {
            $this->assertStringContainsString('function firstPresentValue(values)', $source, "{$name} missing stable option value resolution");
            $this->assertStringContainsString('function itemIsSelected(item)', $source, "{$name} missing default option detection");
            $this->assertStringContainsString('item.is_default', $source, "{$name} missing is_default support");
            $this->assertStringContainsString('item.selected', $source, "{$name} missing selected support");
            $this->assertStringContainsString("item['default']", $source, "{$name} missing default support");
            $this->assertStringContainsString(".prop('selected', true)", $source, "{$name} does not mark default option selected");
        }
    }

    /**
     * 验证行操作模态框预填当前行数据。
     */
    public function test_crmui_row_action_modals_prefill_current_row_values(): void
    {
        $sources = [
            'front' => file_get_contents(public_path('js/apps/crmui/front.js')) ?: '',
            'admin' => file_get_contents(public_path('js/apps/crmui/admin.js')) ?: '',
        ];

        foreach ($sources as $name => $source) {
            $this->assertStringContainsString('function rowValue(row, key)', $source, "{$name} missing raw row value helper");
            $fieldSignature = $name === 'front' ? 'function fieldHtml(field, row, $page)' : 'function fieldHtml(field, row)';
            $rowLookup = $name === 'front' ? 'rowValue(row, field.source || field.name)' : 'rowValue(row, field.name)';
            $fieldCall = $name === 'front' ? 'return fieldHtml(field, row, $page);' : 'return fieldHtml(field, row);';
            $this->assertStringContainsString($fieldSignature, $source, "{$name} modal fields cannot receive row data");
            $this->assertStringContainsString($rowLookup, $source, "{$name} modal fields do not read current row values");
            $this->assertStringContainsString("String(option.value) === String(value) ? ' selected' : ''", $source, "{$name} modal selects do not preselect row value");
            $this->assertStringContainsString('value="\' + escapeHtml(value) + \'"', $source, "{$name} modal inputs do not render row value");
            $this->assertStringContainsString('fields.map(function(field) {', $source, "{$name} modal field renderer is not row-aware");
            $this->assertStringContainsString($fieldCall, $source, "{$name} modal field renderer does not pass row");
        }
    }

    /**
     * 验证资料与表单页面仅在安全模式下预填表单。
     */
    public function test_crmui_profile_and_form_pages_prefill_forms_only_in_safe_modes(): void
    {
        $this->get('/front-crmui/profile')
            ->assertOk()
            ->assertSee('data-crmui-mode="profile"', false);

        $this->get('/front-crmui/profile/edit')
            ->assertOk()
            ->assertSee('data-crmui-mode="form"', false);

        $this->get('/front-crmui/deposit')
            ->assertOk()
            ->assertSee('data-crmui-mode="table"', false);

        $this->get('/admin-crmui/profile/edit')
            ->assertOk()
            ->assertSee('data-crmui-mode="form"', false);

        $sources = [
            'front' => file_get_contents(public_path('js/apps/crmui/front.js')) ?: '',
            'admin' => file_get_contents(public_path('js/apps/crmui/admin.js')) ?: '',
        ];

        foreach ($sources as $name => $source) {
            $this->assertStringContainsString('function flattenFormPayload(payload)', $source, "{$name} missing form payload flattening");
            $this->assertStringContainsString('function fillFormFields($form, values)', $source, "{$name} missing form field filler");
            $this->assertStringContainsString('function fillPageForms($page, response)', $source, "{$name} missing page form filler");
            $this->assertStringContainsString("mode !== 'form' && mode !== 'profile'", $source, "{$name} form filler is not mode-limited");
            $this->assertStringContainsString('values.current_email = values.email;', $source, "{$name} does not map current email alias");
            $this->assertStringContainsString('values.verify_phone = values.phone;', $source, "{$name} does not map verify phone alias");
            $this->assertStringContainsString('fillPageForms($page, response);', $source, "{$name} does not run filler after page load");
        }
    }

    /**
     * 验证表单页面不渲染仅表格使用的控件。
     */
    public function test_crmui_form_surfaces_do_not_render_table_only_controls(): void
    {
        foreach ([
            '/front-crmui/profile',
            '/front-crmui/profile/edit',
            '/front-crmui/profile/change-email',
            '/admin-crmui/profile/edit',
        ] as $path) {
            $content = $this->get($path)->assertOk()->getContent();

            $this->assertStringNotContainsString('data-crmui-action="create"', $content, "{$path} should not expose create action");
            $this->assertStringNotContainsString('data-crmui-action="export"', $content, "{$path} should not expose export action");
            $this->assertStringNotContainsString('data-crmui-filter', $content, "{$path} should not render table filter form");
            $this->assertStringNotContainsString('data-crmui-table-body', $content, "{$path} should not render generic table body");
            $this->assertStringNotContainsString('data-crmui-total', $content, "{$path} should not render generic record total");
        }

        $this->get('/front-crmui/profile/change-email')
            ->assertOk()
            ->assertSee('/api/front/profile', false)
            ->assertSee('/api/front/profile/email', false);

        $this->get('/front-crmui/deposit')
            ->assertOk()
            ->assertSee('data-crmui-filter', false)
            ->assertSee('data-crmui-table-body', false)
            ->assertSee('data-crmui-action="export"', false);
    }

    /**
     * 验证表单页面不渲染通用指标卡片。
     */
    public function test_crmui_form_surfaces_do_not_render_generic_metric_cards(): void
    {
        foreach ([
            '/front-crmui/profile',
            '/front-crmui/profile/edit',
            '/front-crmui/profile/change-email',
            '/admin-crmui/profile/edit',
        ] as $path) {
            $content = $this->get($path)->assertOk()->getContent();

            $this->assertStringNotContainsString('class="crmui-metrics"', $content, "{$path} should not render an empty metrics shell");
            $this->assertStringNotContainsString('data-crmui-metric', $content, "{$path} should not render generic metric cards");
        }

        $this->get('/front-crmui/deposit')
            ->assertOk()
            ->assertSee('class="crmui-metrics"', false)
            ->assertSee('data-crmui-metric="total"', false);
    }

    /**
     * 验证移动端顶栏对操作按钮做换行布局。
     */
    public function test_crmui_mobile_topbar_wraps_action_buttons(): void
    {
        $css = file_get_contents(public_path('css/crmui/front.css')) ?: '';

        $this->assertStringContainsString('@media (max-width: 479px)', $css);
        $this->assertStringContainsString('flex-wrap: wrap;', $css);
        $this->assertStringContainsString('flex: 1 1 calc(100% - 52px);', $css);
        $this->assertStringContainsString('flex: 1 0 100%;', $css);
        $this->assertStringContainsString('justify-content: flex-start;', $css);
    }

    /**
     * 验证表格在移动端渲染字段标签（data-label）。
     */
    public function test_crmui_tables_render_mobile_field_labels(): void
    {
        $css = file_get_contents(public_path('css/crmui/front.css')) ?: '';
        $sources = [
            'front' => file_get_contents(public_path('js/apps/crmui/front.js')) ?: '',
            'admin' => file_get_contents(public_path('js/apps/crmui/admin.js')) ?: '',
        ];

        foreach ($sources as $name => $source) {
            $this->assertMatchesRegularExpression(
                '/columns\.push\(\{\s*key:\s*key,\s*label:\s*\$th\.text\(\)/',
                $source,
                "{$name} table renderer does not keep header labels"
            );
            if ($name === 'front') {
                $this->assertMatchesRegularExpression(
                    '/columns\.push\(\{[\s\S]*?format:\s*\$th\.data\(\x27format\x27\)[\s\S]*?action:\s*\$th\.data\(\x27action\x27\)[\s\S]*?recordKey:\s*\$th\.data\(\x27record-key\x27\)/',
                    $source,
                    'front table renderer must preserve format, action, and recordKey metadata'
                );
            }
            $this->assertStringContainsString('data-label="\' + escapeHtml(column.label) + \'"', $source, "{$name} cells do not render data-label");
            $this->assertStringContainsString('var operationsLabel = $page.find(\'[data-crmui-action-column]\').text() || \'Operations\';', $source, "{$name} action cells do not keep operation label");
            $this->assertStringContainsString('data-label="\' + escapeHtml(operationsLabel) + \'"', $source, "{$name} row action cell does not render data-label");
        }

        $this->assertStringContainsString('@media (max-width: 640px)', $css);
        $this->assertStringContainsString('.crmui-table thead', $css);
        $this->assertStringContainsString('content: attr(data-label);', $css);
        $this->assertStringContainsString('grid-template-columns: minmax(96px, 34%) minmax(0, 1fr);', $css);
    }

    /**
     * 验证优先工作流文案在 zh-CN 与 en 双语下均有翻译。
     */
    public function test_crmui_priority_workflow_labels_are_translated_in_both_languages(): void
    {
        $translationKeys = [
            'crmui.common.no_file_selected',
            'crmui.common.operations',
            'crmui.actions.send_email_code',
            'crmui.actions.detail',
            'crmui.actions.change_status',
            'crmui.actions.review_auth',
            'crmui.actions.review',
            'crmui.actions.approve',
            'crmui.actions.reject',
            'crmui.actions.process',
            'crmui.actions.complete',
            'crmui.actions.descendants',
            'crmui.actions.update_level',
            'crmui.actions.update_commission',
            'crmui.actions.update',
            'crmui.actions.delete',
            'crmui.actions.force_close',
            'crmui.actions.ip_detail',
            'crmui.panels.avatar_upload',
            'crmui.panels.identity_audit',
            'crmui.panels.bank_card',
            'crmui.tabs.account_flow',
            'crmui.tabs.deposit_flow',
            'crmui.tabs.withdraw_flow',
            'crmui.tabs.withdraw_apply_flow',
            'crmui.tabs.direct_deposit_flow',
            'crmui.tabs.direct_withdraw_flow',
            'crmui.tabs.pending_auth',
            'crmui.tabs.certified_auth',
            'crmui.tabs.risk_positions',
            'crmui.tabs.margin_calls',
            'crmui.tabs.risk_ip',
            'crmui.options.enabled',
            'crmui.options.disabled',
            'crmui.options.approved',
            'crmui.options.rejected',
            'crmui.options.deposit_pending',
            'crmui.options.deposit_approved',
            'crmui.options.deposit_rejected',
            'crmui.options.withdraw_pending',
            'crmui.options.withdraw_processing',
            'crmui.options.withdraw_completed',
            'crmui.options.withdraw_rejected',
            'crmui.options.id_card_pending',
            'crmui.options.bank_pending',
            'crmui.options.all_flows',
            'crmui.options.deposit_flow',
            'crmui.options.withdraw_flow',
            'crmui.options.withdraw_apply_flow',
            'crmui.options.direct_deposit_flow',
            'crmui.options.direct_withdraw_flow',
            'crmui.confirms.delete',
            'crmui.confirms.force_close',
            'crmui.fields.id_card_front',
            'crmui.fields.id_card_back',
            'crmui.fields.bank_name',
            'crmui.fields.bank_no',
            'crmui.fields.bank_card_img',
            'crmui.fields.bank_card_img_back',
            'crmui.fields.actual_amount',
            'crmui.fields.payment_channel',
            'crmui.fields.reject_reason',
            'crmui.fields.review_status',
            'crmui.fields.review_message',
        ];

        foreach (['zh-CN', 'en'] as $locale) {
            foreach ($translationKeys as $key) {
                $this->assertNotSame($key, __($key, [], $locale), "Missing {$locale} translation: {$key}");
            }
        }
    }

    /**
     * 验证优先页面不渲染原始翻译键。
     */
    public function test_crmui_priority_pages_do_not_render_raw_translation_keys(): void
    {
        $paths = [
            '/front-crmui/login',
            '/front-crmui/register',
            '/front-crmui/profile',
            '/front-crmui/deposit',
            '/front-crmui/withdraw',
            '/front-crmui/flow',
            '/front-crmui/order/open',
            '/front-crmui/order/closed',
            '/admin-crmui/users',
            '/admin-crmui/deposits',
            '/admin-crmui/withdrawals',
            '/admin-crmui/authentications',
            '/admin-crmui/agents',
            '/admin-crmui/permissions',
            '/admin-crmui/risk',
        ];

        $rawPrefixes = [
            'crmui.common.',
            'crmui.fields.',
            'crmui.actions.',
            'crmui.panels.',
            'crmui.tabs.',
            'crmui.options.',
            'crmui.confirms.',
            'crmui.front.',
            'crmui.admin.',
        ];

        foreach (['zh-CN', 'en'] as $locale) {
            foreach ($paths as $path) {
                $content = $this->get($path . '?locale=' . $locale)->assertOk()->getContent();

                foreach ($rawPrefixes as $prefix) {
                    $this->assertStringNotContainsString($prefix, $content, "{$path} renders raw translation prefix {$prefix} for {$locale}");
                }
            }
        }
    }

    /**
     * 验证页面可通过 locale 查询参数切换语言。
     */
    public function test_crmui_pages_can_switch_language_with_locale_query(): void
    {
        $this->get('/front-crmui/login?locale=zh-CN')
            ->assertOk()
            ->assertSee('登录客户工作台', false)
            ->assertSee('客户工作台', false);

        $this->get('/front-crmui/login?locale=en')
            ->assertOk()
            ->assertSee('Sign in to Client Workspace', false)
            ->assertSee('Client Workspace', false);

        $this->get('/admin-crmui/login?locale=zh-CN')
            ->assertOk()
            ->assertSee('登录运营管理台', false)
            ->assertSee('运营管理台', false);

        $this->get('/admin-crmui/login?locale=en')
            ->assertOk()
            ->assertSee('Sign in to Operations Console', false)
            ->assertSee('Operations Console', false);
    }
}
