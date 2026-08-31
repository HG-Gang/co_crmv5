<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/07
 * Time: 14:59
 */

/**
 * AdminAuthenticationPendingPresentationContractTest
 *
 * 文件功能：
 * - 验证待审核实名展示契约：待审控制器使用共享审核队列状态契约，Layui/CrmUI 筛选标签与统一审核银行卡字段的双语标签。
 * - 输入：内存构造的服务对象、测试替身与必要的数据库夹具；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖 HTTP 路由与控制器接线（由 Feature 契约测试锁定）。
 */

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AdminAuthenticationPendingPresentationContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 2);
    }

    public function test_pending_controller_uses_the_shared_review_queue_status_contract(): void
    {
        $source = $this->source('app/Http/Controllers/Admin/AuthenticationController.php');

        $this->assertStringContainsString('AuthReviewTransition::reviewQueueStatuses', $source);
        $this->assertStringContainsString("'user_auths.id_card_status'", $source);
        $this->assertStringContainsString("'user_auths.bank_status'", $source);
    }

    public function test_layui_filter_labels_pending_and_rejected_review_states(): void
    {
        $source = $this->source('resources/admin/layui/authentications/index.blade.php');

        $this->assertStringContainsString(
            '<option value="1">{{ __(\'admin.auth_reviewing\') }}</option>',
            $source
        );
        $this->assertStringContainsString(
            '<option value="2">{{ __(\'admin.auth_rejected\') }}</option>',
            $source
        );
        $this->assertStringNotContainsString(
            '<option value="1">{{ __(\'admin.id_card_auth\') }}</option>',
            $source
        );
    }

    public function test_crmui_filter_labels_pending_and_rejected_review_states(): void
    {
        $pageSource = $this->source('app/Http/Controllers/CrmUi/Admin/PageController.php');

        $this->assertMatchesRegularExpression(
            "/'authentications'.*?'auth_status'.*?'value'\s*=>\s*1,\s*'label'\s*=>\s*'auth_pending'.*?'value'\s*=>\s*2,\s*'label'\s*=>\s*'auth_rejected'/s",
            $pageSource
        );

        foreach (['resources/lang/zh-CN/crmui.php', 'resources/lang/en/crmui.php'] as $path) {
            $languageSource = $this->source($path);
            $this->assertStringContainsString("'auth_pending'", $languageSource, $path);
            $this->assertStringContainsString("'auth_rejected'", $languageSource, $path);
        }
    }

    public function test_layui_pending_table_consumes_unified_review_bank_fields(): void
    {
        $source = $this->source('public/js/apps/admin/layui/pages.js');

        $this->assertStringContainsString("field: 'review_bank_no'", $source);
        $this->assertStringContainsString("field: 'review_bank_name'", $source);
        $this->assertStringContainsString("field: 'review_bank_addr'", $source);
        $this->assertStringNotContainsString("field: 'bank_no_tmp'", $source);
        $this->assertStringNotContainsString("field: 'bank_name_tmp'", $source);
        $this->assertStringNotContainsString("field: 'bank_addr_tmp'", $source);
    }

    public function test_crmui_authentication_table_consumes_unified_review_bank_fields(): void
    {
        $source = $this->source('app/Http/Controllers/CrmUi/Admin/PageController.php');

        $this->assertMatchesRegularExpression(
            "/'authentications'.*?'columns'\s*=>\s*\[[^\]]*'review_bank_no'[^\]]*'review_bank_name'[^\]]*'review_bank_addr'/s",
            $source
        );
    }

    public function test_crmui_unified_review_bank_fields_have_bilingual_labels(): void
    {
        foreach (['resources/lang/zh-CN/crmui.php', 'resources/lang/en/crmui.php'] as $path) {
            $source = $this->source($path);

            $this->assertStringContainsString("'review_bank_no'", $source, $path);
            $this->assertStringContainsString("'review_bank_name'", $source, $path);
            $this->assertStringContainsString("'review_bank_addr'", $source, $path);
        }
    }

    private function source(string $path): string
    {
        $source = file_get_contents($this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        $this->assertNotFalse($source, $path);

        return $source;
    }
}
