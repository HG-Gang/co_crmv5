<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/05
 * Time: 08:38
 */

/**
 * VoucherInfoCommentReadabilityTest
 *
 * 文件功能：
 * - 验证 VoucherInfo 模型保持可读中文注释，禁止乱码编码片段回流。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * @runClassInSeparateProcess
 * @preserveGlobalState disabled
 *
 * 凭证信息模型中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - VoucherInfo 负责映射 voucher_infos 表，是后台凭证审核和前台凭证上传链路的基础模型。
 * - 本测试只约束模型源码的中文职责说明、关键字段参数注释和关联关系说明，不连接数据库。
 * - 该测试防止凭证模型再次写入历史乱码或旧英文占位说明。
 */
class VoucherInfoCommentReadabilityTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        gc_collect_cycles();
    }

    /**
     * 凭证信息模型必须使用 UTF-8 可读中文说明职责、表名和关键字段含义。
     *
     * @return void
     */
    public function test_voucher_info_model_contains_readable_chinese_logic_comments(): void
    {
        // $content 表示 VoucherInfo 模型源码，用于检查中文注释是否覆盖职责、字段和关联说明。
        $content = file_get_contents(app_path('Models/VoucherInfo.php')) ?: '';

        foreach ($this->requiredCommentFragments() as $fragment) {
            $this->assertStringContainsString($fragment, $content, 'VoucherInfo.php 缺少中文逻辑注释：' . $fragment);
        }
    }

    /**
     * 凭证信息模型不能继续保留历史编码错误产生的乱码片段。
     *
     * @return void
     */
    public function test_voucher_info_model_does_not_contain_garbled_encoding_fragments(): void
    {
        // $content 表示 VoucherInfo 模型源码，用于排查历史乱码和旧英文占位说明。
        $content = file_get_contents(app_path('Models/VoucherInfo.php')) ?: '';

        foreach ($this->forbiddenFragments() as $fragment) {
            $this->assertStringNotContainsString($fragment, $content, 'VoucherInfo.php 仍包含乱码或旧占位片段：' . $fragment);
        }
    }

    /**
     * 必须保留的中文注释片段。
     *
     * @return array<int, string> 注释片段列表，用于覆盖模型职责、数据表、字段参数和关联关系说明。
     */
    private function requiredCommentFragments(): array
    {
        return [
            '凭证信息模型',
            'voucher_infos 表保存前台用户上传的入金或审核凭证',
            '$table 表示当前模型读写的真实数据库表',
            'user_id 表示上传凭证的前台业务用户 ID',
            'images 表示凭证图片路径或 JSON 图片列表',
            'review_status 表示凭证审核状态',
            'user() 关联上传凭证所属前台业务用户资料',
        ];
    }

    /**
     * 常见乱码与旧英文占位片段黑名单。
     *
     * @return array<int, string> 乱码片段列表，用于发现 GBK/UTF-8 错读和旧英文占位说明。
     */
    private function forbiddenFragments(): array
    {
        return [
            "\0",
            '缁?',
            '閻?',
            '娑撳﹣绱?',
            '閺?',
            '閸?',
            '锟?',
            'Voucher Info Model',
            '鏁版嵁',
            '鍏宠仈',
            '鍑瘉',
        ];
    }
}
