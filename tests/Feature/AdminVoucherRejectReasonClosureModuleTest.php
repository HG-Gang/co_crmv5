<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/01
 * Time: 15:05
 */

/**
 * AdminVoucherRejectReasonClosureModuleTest
 *
 * 文件功能：
 * - 锁定后台凭证「拒绝」必须携带 reason，且列表页与详情页两条入口都不例外。
 * - 输入：pages.js 前端源码文本 + VoucherController 源码文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不覆盖拒绝成功后的落库结果（由凭证审核的接口测试锁定）。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 锁定凭证拒绝原因的传参闭环。
 *
 * 背景（真实缺陷，非假设）：
 * - VoucherController::reject() 对 reason 做 required|string|max:2000 校验，
 *   校验不过直接返回 VALIDATION_FAILED。
 * - 而列表页的 reviewVoucher() 原本写死 data: {}，approve/reject 共用，
 *   于是列表页的「拒绝」按钮**一条都拒不掉**，每次都被后端参数校验打回。
 * - 详情页（vouchers/detail）本来就正确收集 reviewmsg 并转成 reason，
 *   所以只对整份 pages.js 做全文搜索会被详情页的正确写法兜住、测不出列表页的缺陷。
 *   本测试因此按 registry 块切片，分别断言两条入口。
 */
class AdminVoucherRejectReasonClosureModuleTest extends TestCase
{
    /**
     * 列表页拒绝必须弹原因输入并把 reason 发给后端。
     *
     * @return void
     */
    public function test_voucher_list_reject_sends_reason(): void
    {
        $block = $this->registryBlock('vouchers/index');

        $this->assertStringContainsString(
            "reviewVoucher('/api/admin/voucherReject', obj.data.id, {reason: reason})",
            $block,
            '列表页拒绝必须把操作人填写的 reason 发给后端，否则后端校验必然失败。'
        );
        $this->assertStringContainsString('layer.prompt', $block, '列表页拒绝必须弹出原因输入框。');
        $this->assertStringContainsString(
            "CrmLang.t('admin.reject_reason_required')",
            $block,
            '原因为空时必须就地提示，不能放空串过去让后端报错。'
        );
        // 空 payload 只允许出现在「兜底默认值」位置，不允许再有写死的 data: {}。
        $this->assertStringNotContainsString(
            'data: {},',
            $block,
            '列表页不得再出现写死的空 payload：那正是拒绝永远失败的根因。'
        );
    }

    /**
     * approve 不需要 reason，必须保持无参调用，避免过度收敛。
     *
     * @return void
     */
    public function test_voucher_list_approve_stays_argument_free(): void
    {
        $block = $this->registryBlock('vouchers/index');

        $this->assertStringContainsString(
            "reviewVoucher('/api/admin/voucherApprove', obj.data.id)",
            $block,
            '通过凭证不需要原因，不应被一并要求填写。'
        );
    }

    /**
     * 详情页那条入口原本就正确，一并锁住防回退。
     *
     * @return void
     */
    public function test_voucher_detail_reject_still_requires_reason(): void
    {
        $block = $this->registryBlock('vouchers/detail');

        $this->assertStringContainsString('{reason: reason}', $block);
        $this->assertStringContainsString("CrmLang.t('admin.reject_reason_required')", $block);
    }

    /**
     * 后端校验仍在。若后端哪天放宽了 reason，本测试的前提就不成立，必须同步重估。
     *
     * @return void
     */
    public function test_backend_still_requires_reason(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/VoucherController.php')) ?: '';

        $this->assertStringContainsString(
            "'reason' => 'required|string|max:2000'",
            $source,
            '本测试以后端强制 reason 为前提；后端放宽后需同步重估前端约束。'
        );
    }

    /**
     * 文案键在两种语言里都必须存在，否则界面会露出裸键名。
     *
     * @return void
     */
    public function test_reason_labels_exist_in_both_locales(): void
    {
        foreach (['zh-CN', 'en'] as $locale) {
            $catalog = require resource_path('lang/' . $locale . '/admin.php');
            $this->assertArrayHasKey('reject_reason', $catalog, $locale . ' 缺少 reject_reason 文案。');
            $this->assertArrayHasKey(
                'reject_reason_required',
                $catalog,
                $locale . ' 缺少 reject_reason_required 文案。'
            );
        }
    }

    /**
     * 按 registry 键切出单个页面块的源码。
     *
     * 逻辑说明：
     * - pages.js 是单文件多页面注册表，整份全文搜索会让「A 页面写对」掩盖「B 页面写错」。
     * - 因此以 registry['键'] 为起点，取到下一个 registry[ 之前为止。
     *
     * @param string $key registry 键名，例如 vouchers/index。
     * @return string 该块源码。
     */
    private function registryBlock(string $key): string
    {
        $source = file_get_contents(public_path('js/apps/admin/layui/pages.js')) ?: '';
        $start = strpos($source, "registry['" . $key . "']");
        $this->assertNotFalse($start, 'pages.js 中找不到 registry 块：' . $key);

        $next = strpos($source, "registry['", $start + 10);
        $block = $next === false
            ? substr($source, $start)
            : substr($source, $start, $next - $start);

        $this->assertNotSame('', trim($block), '切出的 registry 块为空：' . $key);

        return $block;
    }
}
