<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 08:36
 */

/**
 * AdminSecuritySequenceModelCommentReadabilityTest
 *
 * 文件功能：
 * - 验证安全和序列相关模型保持可读中文注释，禁止旧英文占位或乱码注释回流。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 注销申请、黑名单、大代理登录日志和 ID 序列模型中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - 本测试约束安全风控、账号注销、登录审计和业务编号生成相关模型的中文注释质量。
 * - 这些模型会影响后台注销审核、黑名单风控、大代理登录审计和注册编号生成，字段含义必须清楚。
 * - 测试只读取源码文件，不创建注销申请、黑名单、登录日志或 ID 序列数据。
 */
class AdminSecuritySequenceModelCommentReadabilityTest extends TestCase
{
    /**
     * 安全和序列相关模型必须包含真实表职责与关键字段中文说明。
     *
     * @return void
     */
    public function test_security_sequence_models_contain_readable_chinese_logic_comments(): void
    {
        // $expectations 表示每个模型必须包含的中文说明片段；键名为模型路径，值为真实表和关键字段含义。
        $expectations = [
            app_path('Models/CancelApply.php') => [
                '注销申请模型',
                'cancel_applies 表保存前台用户提交的账号注销申请',
                'status 表示注销申请处理状态',
                'cancel_remark 表示用户提交的注销原因',
                'reject_reason 表示后台拒绝注销的原因',
            ],
            app_path('Models/Blacklist.php') => [
                '黑名单模型',
                'blacklists 表保存被限制注册或操作的用户身份信息',
                'id_card 表示被限制的身份证号码',
                'email 表示被限制的邮箱',
                'phone 表示被限制的手机号',
            ],
            app_path('Models/BigAgentLoginLog.php') => [
                '大代理登录日志模型',
                'big_agent_login_logs 表保存大代理账号登录审计记录',
                'big_agent_id 表示登录的大代理账号 ID',
                'login_ip 表示登录来源 IP',
                'login_at 表示登录发生时间',
            ],
            app_path('Models/IdSequence.php') => [
                'ID 序列模型',
                'id_sequences 表保存业务用户编号生成状态',
                'type 表示序列类型',
                'current_value 表示当前已发放的最大编号',
                '$type 表示需要生成编号的业务类型',
                'lockForUpdate() 用于保证并发生成编号时不会重复',
            ],
        ];

        foreach ($expectations as $file => $requiredFragments) {
            // $content 表示当前模型源码，用于确认注释覆盖真实表职责和字段含义。
            $content = file_get_contents($file);

            foreach ($requiredFragments as $fragment) {
                $this->assertStringContainsString($fragment, $content, $file . ' 缺少中文说明：' . $fragment);
            }
        }
    }

    /**
     * 安全和序列相关模型不允许保留旧英文占位或乱码注释。
     *
     * @return void
     */
    public function test_security_sequence_models_do_not_contain_mojibake_or_english_placeholders(): void
    {
        // $files 表示本轮直接维护的模型文件集合，用于将失败范围限制在当前修复边界内。
        $files = [
            app_path('Models/CancelApply.php'),
            app_path('Models/Blacklist.php'),
            app_path('Models/BigAgentLoginLog.php'),
            app_path('Models/IdSequence.php'),
        ];

        // $forbiddenFragments 表示旧注释中常见的英文占位和 UTF-8/GBK 错解片段。
        $forbiddenFragments = [
            'Table Name',
            'Relation:',
            'Handles account cancellation',
            'Manages blocked users',
            'Records login history',
            'Used for generating unique ID sequences',
            'Initialize if not exists',
            '鏁版嵁',
            '鍏宠仈',
            '榛戝悕',
            '澶т唬',
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
