<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 08:52
 */

/**
 * AdminConfigVoucherModelCommentReadabilityTest
 *
 * 文件功能：
 * - 验证配置与凭证相关模型保持可读中文注释，禁止旧英文占位或乱码注释回流。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 系统配置、点差配置、用户组兼容、认证备份和凭证模型中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - 本测试约束后台系统配置、点差配置、历史用户组、认证备份和凭证模型的中文注释质量。
 * - 这些模型会影响汇率配置、下载配置、点差配置、凭证审核和历史兼容入口，字段含义及未建表边界必须写清楚。
 * - 测试只读取源码文件，不写入系统配置、点差配置或凭证数据。
 */
class AdminConfigVoucherModelCommentReadabilityTest extends TestCase
{
    /**
     * 配置与凭证相关模型必须包含真实表职责、关键字段和兼容边界说明。
     *
     * @return void
     */
    public function test_config_voucher_models_contain_readable_chinese_logic_comments(): void
    {
        // $expectations 表示每个模型必须包含的中文说明片段；键名为模型路径，值为真实表、字段含义或兼容边界。
        $expectations = [
            app_path('Models/SystemConfig.php') => [
                '系统配置模型',
                'system_configs 表保存后台全局配置项',
                'key 表示配置唯一键',
                'value 表示配置值',
                '$key 表示 system_configs.key',
            ],
            app_path('Models/SpreadConfig.php') => [
                '点差配置模型',
                'spread_configs 表保存交易产品或代理组点差配置',
                'spread 表示固定点差值',
                'agent_group_id 表示代理组 ID',
                'spread_ratio 表示点差比例',
            ],
            app_path('Models/UserGroup.php') => [
                '用户组兼容模型',
                '当前数据库未建 user_groups 表时不得在业务查询中直接依赖该模型',
                'user_groups 表曾用于保存旧项目用户组和交易费率配置',
            ],
            app_path('Models/UserAuthInfo.php') => [
                '用户认证信息备份模型',
                '当前数据库未建 user_auth_info 表时不得在业务查询中直接依赖该模型',
                'user_auth_info 表曾用于保存用户认证历史快照',
            ],
            app_path('Models/VoucherInfo.php') => [
                '凭证信息模型',
                'voucher_infos 表保存前台用户上传的入金或审核凭证',
                'images 表示凭证图片路径或 JSON 图片列表',
                'review_status 表示凭证审核状态',
                'review_message 表示审核说明或拒绝原因',
            ],
        ];

        foreach ($expectations as $file => $requiredFragments) {
            // $content 表示当前模型源码，用于确认注释覆盖真实表职责、字段含义和兼容边界。
            $content = file_get_contents($file);

            foreach ($requiredFragments as $fragment) {
                $this->assertStringContainsString($fragment, $content, $file . ' 缺少中文说明：' . $fragment);
            }
        }
    }

    /**
     * 配置与凭证相关模型不允许保留旧英文占位或乱码注释。
     *
     * @return void
     */
    public function test_config_voucher_models_do_not_contain_mojibake_or_english_placeholders(): void
    {
        // $files 表示本轮直接维护的模型文件集合，用于将失败范围限制在当前修复边界内。
        $files = [
            app_path('Models/SystemConfig.php'),
            app_path('Models/SpreadConfig.php'),
            app_path('Models/UserGroup.php'),
            app_path('Models/UserAuthInfo.php'),
            app_path('Models/VoucherInfo.php'),
        ];

        // $forbiddenFragments 表示旧注释中常见的英文占位和 UTF-8/GBK 错解片段。
        $forbiddenFragments = [
            'Table Name',
            'Get Config Value',
            'Manages various global configuration',
            'Manages spread configurations',
            'Defines different user groups',
            'Stores backups or historical records',
            'Voucher Info Model',
            '鏁版嵁',
            '鍏宠仈',
            '绯荤粺',
            '鐐瑰樊',
            '鍑瘉',
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
