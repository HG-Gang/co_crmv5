<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 20:36
 */

/**
 * AdminCoreMt4ModelCommentReadabilityTest
 *
 * 文件功能：
 * - 验证核心与 MT4 相关模型文件保持可读中文逻辑注释，并禁止历史英文占位说明或乱码片段回流。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 基础模型、大代理、MT4 与批量信用导入模型中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - 用户要求所有模块文件及参数必须有详细中文注释和逻辑注释。
 * - 本测试覆盖仍残留历史编码乱码或英文占位说明的核心模型文件。
 * - 测试只校验注释和说明文本，不改变模型运行逻辑。
 */
class AdminCoreMt4ModelCommentReadabilityTest extends TestCase
{
    /**
     * 核心模型文件必须包含可读中文功能说明和参数含义说明。
     *
     * 参数与变量含义：
     * - $expectations：键为模型文件路径，值为该文件必须包含的中文业务说明片段。
     * - $path：当前被检查的模型绝对路径。
     * - $content：模型文件完整源码文本，用于检查中文注释是否齐全。
     *
     * @return void
     */
    public function test_core_mt4_models_have_readable_chinese_logic_comments(): void
    {
        $expectations = [
            app_path('Models/BaseModel.php') => [
                '基础模型类',
                '$guarded 表示批量赋值黑名单',
                '$hidden 表示序列化时隐藏字段',
                'SoftDeletes 表示所有继承模型默认支持软删除',
                '$dateFormat 表示 Eloquent 日期字段保存为 Unix 时间戳',
                'serializeDate() 用于把日期序列化为后台接口统一展示格式',
                '@param \DateTimeInterface $date 需要输出到 JSON 或数组响应中的日期对象。',
            ],
            app_path('Models/BigAgent.php') => [
                '大代理模型',
                'big_agents 表保存大代理登录账号',
                'sub_agent_ids 表示可管理下级代理 ID 集合',
                'is_enabled 表示大代理账号是否启用',
                'loginLogs() 返回该大代理账号的登录审计日志',
            ],
            app_path('Models/Mt4User.php') => [
                'MT4 用户资金模型',
                'mt4_users 表保存从 MT4 同步的交易账号资金快照',
                'login 表示 MT4 登录账号',
                'balance/equity/margin/margin_free',
                'leverage 表示 MT4 账号杠杆',
            ],
            app_path('Models/Mt4Trade.php') => [
                'MT4 交易记录模型',
                'mt4_trades 表保存从 MT4 同步的交易订单',
                'cmd=6 表示余额类交易',
                'login 表示 MT4 登录账号',
                'user() 通过 login 字段关联业务用户',
            ],
            app_path('Models/CreditImport.php') => [
                '批量信用导入模型',
                'credit_imports 表保存后台批量信用额度导入记录',
                'credit_type 表示信用调整类型',
                'batch_no 表示批次号',
                'is_synced 表示是否已同步到 MT4',
                'fail_reason 表示同步失败原因',
            ],
        ];

        foreach ($expectations as $path => $needles) {
            $content = (string) file_get_contents($path);

            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $content, $path . ' 缺少中文注释片段：' . $needle);
            }
        }
    }

    /**
     * 核心模型文件不得继续保留旧英文占位说明或历史乱码片段。
     *
     * 参数与变量含义：
     * - $paths：需要检查的模型文件路径集合。
     * - $forbiddenFragments：禁止回流的英文占位说明和常见 mojibake 乱码片段。
     * - $content：当前模型文件源码文本。
     *
     * @return void
     */
    public function test_core_mt4_models_do_not_keep_legacy_english_or_mojibake_comments(): void
    {
        $paths = [
            app_path('Models/BaseModel.php'),
            app_path('Models/BigAgent.php'),
            app_path('Models/Mt4User.php'),
            app_path('Models/Mt4Trade.php'),
            app_path('Models/CreditImport.php'),
        ];

        $forbiddenFragments = [
            'Base Model Class',
            'All business models extend this class',
            'Use $guarded blacklist',
            'Fields hidden by default',
            'Primary key column name',
            'Timestamp storage format',
            'Serialize dates to ISO format',
            'Table Name',
            'Relation:',
            '鏁版嵁',
            '鍏宠仈',
            '鐢ㄦ埛',
            '淇＄敤',
            '澶т唬',
            '浜ゆ槗',
            '妯″瀷',
        ];

        foreach ($paths as $path) {
            $content = (string) file_get_contents($path);

            foreach ($forbiddenFragments as $fragment) {
                $this->assertStringNotContainsString($fragment, $content, $path . ' 仍包含旧注释或乱码片段：' . $fragment);
            }
        }
    }
}
