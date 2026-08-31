<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 02:47
 */

/**
 * AdminMenuAuditConfigModelCommentReadabilityTest
 *
 * 文件功能：
 * - 验证菜单、日志和配置相关模型保持可读中文注释，禁止历史乱码或英文占位注释回流。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 后台菜单、审计日志和系统配置模型中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - 本测试约束后台权限菜单、操作日志、数据变更日志、邮件配置模型的中文说明质量。
 * - 这些模型支撑菜单展示、权限入口、审计追踪和系统邮件发送，字段含义必须和真实数据表保持一致。
 * - 测试只读取源码文件，不写入业务数据，也不改变当前后台权限或日志行为。
 */
class AdminMenuAuditConfigModelCommentReadabilityTest extends TestCase
{
    /**
     * 菜单、日志和配置模型必须包含真实表职责与关键字段中文说明。
     *
     * @return void
     */
    public function test_models_contain_readable_chinese_logic_comments(): void
    {
        // $expectations 表示每个模型必须包含的中文说明片段；键名为模型路径，值为关键职责和字段含义。
        $expectations = [
            app_path('Models/Menu.php') => [
                '菜单模型',
                'menus 表保存前后台 Blade 页面可见的动态菜单配置',
                'permission_id 表示绑定的 permissions.id',
                'guard_type 表示菜单所属端',
                'getLocalizedTitleAttribute() 按当前 locale 返回中文或英文菜单标题',
            ],
            app_path('Models/OperationLog.php') => [
                '操作日志模型',
                'operation_logs 表保存后台管理员业务操作审计记录',
                'admin_id 表示执行操作的后台管理员 ID',
                'target_user_id 表示被操作的业务用户 ID',
                'action_type 表示操作类型',
            ],
            app_path('Models/DataOperationLog.php') => [
                '数据操作日志模型',
                'data_operation_logs 表保存模型数据变更前后的审计快照',
                'model_type 表示被修改的数据模型类型',
                'before_data 表示变更前数据快照',
                'operator_id 表示执行变更的后台管理员 ID',
            ],
            app_path('Models/MailSetting.php') => [
                '邮件设置模型',
                'mail_settings 表保存系统邮件发送配置',
                'driver 表示邮件发送驱动',
                'from_address 表示默认发件邮箱',
                'password 表示邮件服务授权密码',
            ],
        ];

        foreach ($expectations as $file => $requiredFragments) {
            // $content 表示当前模型源码，用于确认注释是否覆盖真实表职责和字段含义。
            $content = file_get_contents($file);

            foreach ($requiredFragments as $fragment) {
                $this->assertStringContainsString($fragment, $content, $file . ' 缺少中文说明：' . $fragment);
            }
        }
    }

    /**
     * 菜单、日志和配置模型不允许保留历史乱码或英文占位注释。
     *
     * @return void
     */
    public function test_models_do_not_contain_mojibake_or_english_placeholder_comments(): void
    {
        // $files 表示本轮直接维护的模型文件集合，用于把失败范围限制在当前修复边界内。
        $files = [
            app_path('Models/Menu.php'),
            app_path('Models/OperationLog.php'),
            app_path('Models/DataOperationLog.php'),
            app_path('Models/MailSetting.php'),
        ];

        // $forbiddenFragments 表示旧注释中常见的英文占位和 UTF-8/GBK 错解片段。
        $forbiddenFragments = [
            'Table Name',
            'Relation:',
            'Fillable attributes',
            'Attribute Casting',
            'Manages dynamic navigation menus',
            'Records operation behaviors',
            'Records data change details',
            'Stores system email delivery configurations',
            '鏁版嵁',
            '鍏宠仈',
            '鑿滃崟',
            '閭欢',
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
