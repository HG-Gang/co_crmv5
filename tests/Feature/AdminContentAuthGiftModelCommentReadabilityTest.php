<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 08:27
 */

/**
 * AdminContentAuthGiftModelCommentReadabilityTest
 *
 * 文件功能：
 * - 验证内容、认证、地址和发货相关模型保持可读中文注释，禁止旧英文占位或乱码注释回流。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台公告、实名认证、收货地址和礼品发货模型中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - 本测试约束内容公告、用户认证、收货地址和礼品发货模型的中文注释质量。
 * - 这些模型会被后台公告、实名认证审核、礼品发货列表和前台资料页面共同使用，字段含义必须清楚。
 * - 测试只读取源码文件，不创建公告、认证、地址或发货数据，也不改变业务行为。
 */
class AdminContentAuthGiftModelCommentReadabilityTest extends TestCase
{
    /**
     * 内容、认证、地址和发货模型必须包含真实表职责与关键字段中文说明。
     *
     * @return void
     */
    public function test_models_contain_readable_chinese_logic_comments(): void
    {
        // $expectations 表示每个模型必须包含的中文说明片段；键名为模型路径，值为真实表和关键字段含义。
        $expectations = [
            app_path('Models/News.php') => [
                '新闻公告模型',
                'news 表保存后台发布的新闻公告内容',
                'is_published 表示公告是否发布',
                '$query 表示新闻公告查询构造器',
            ],
            app_path('Models/UserAuth.php') => [
                '用户实名认证模型',
                'user_auths 表保存前台用户实名和银行卡认证资料',
                'user_id 表示认证资料所属业务用户 ID',
                'bank_status 表示银行卡审核状态',
                'id_card_status 表示身份证审核状态',
                'is_bank_synced 表示银行卡信息是否已同步',
            ],
            app_path('Models/UserAddress.php') => [
                '用户收货地址模型',
                'user_addresses 表保存前台用户礼品收货地址',
                'recipient_name 表示收件人姓名',
                'recipient_phone 表示收件人联系电话',
                'is_default 表示是否默认地址',
            ],
            app_path('Models/GiftShipment.php') => [
                '礼品发货模型',
                'gift_shipments 表保存礼品兑换后的发货和物流记录',
                'address_id 表示使用的收货地址 ID',
                'tracking_number 表示物流单号',
                'status 表示发货处理状态',
                'admin_id 表示处理发货的后台管理员 ID',
            ],
        ];

        foreach ($expectations as $file => $requiredFragments) {
            // $content 表示当前模型源码，用于确认注释覆盖真实数据表职责和字段含义。
            $content = file_get_contents($file);

            foreach ($requiredFragments as $fragment) {
                $this->assertStringContainsString($fragment, $content, $file . ' 缺少中文说明：' . $fragment);
            }
        }
    }

    /**
     * 内容、认证、地址和发货模型不允许保留旧英文占位或乱码注释。
     *
     * @return void
     */
    public function test_models_do_not_contain_mojibake_or_english_placeholders(): void
    {
        // $files 表示本轮直接维护的模型文件集合，用于将失败范围限制在当前修复边界内。
        $files = [
            app_path('Models/News.php'),
            app_path('Models/UserAuth.php'),
            app_path('Models/UserAddress.php'),
            app_path('Models/GiftShipment.php'),
        ];

        // $forbiddenFragments 表示旧注释中常见的英文占位和 UTF-8/GBK 错解片段。
        $forbiddenFragments = [
            'Table Name',
            'Relation:',
            'Scope:',
            'mass assignable',
            'Manages news and announcements',
            'Manages user',
            'shipping address information',
            'shipping process and logistics',
            '鏁版嵁',
            '鍏宠仈',
            '鐢ㄦ埛',
            '绀煎搧',
        ];

        foreach ($files as $file) {
            // $content 表示当前模型源码，用于逐项排查不可读注释残留。
            $content = file_get_contents($file);

            foreach ($forbiddenFragments as $fragment) {
                $this->assertStringNotContainsString($fragment, $content, $file . ' 仍包含不可读或占位注释：' . $fragment);
            }
        }
    }
}
