<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/06/09
 * Time: 00:20
 */

/**
 * FrontVoucherControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证前台 VoucherController 对凭证图片、备注、审核状态、当前登录用户、分页筛选和返回结构均有中文逻辑说明。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 前台凭证控制器中文注释可读性测试。
 *
 * 测试目标：
 * - 只读取 Front\VoucherController 源码，不连接真实数据库，不写入上传文件。
 * - 约束凭证图片、备注、审核状态、当前登录用户、分页筛选和返回结构必须具备中文逻辑说明。
 */
class FrontVoucherControllerCommentReadabilityTest extends TestCase
{
    public function test_front_voucher_controller_contains_required_chinese_logic_comments(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/VoucherController.php')) ?: '';

        $expectedComments = [
            '前台凭证控制器',
            '处理前台用户上传入金凭证、保存凭证图片、写入 voucher_infos 表以及查询当前用户凭证记录',
            'store 用于提交当前前台用户的凭证图片',
            'images 表示凭证图片上传字段',
            'images.* 表示每一张凭证图片文件',
            'remarks 表示用户提交凭证时填写的备注',
            'userLogin 表示当前 user guard 登录记录',
            'userInfo 表示当前登录记录关联的业务用户资料',
            'imagePaths 表示已保存到 public 磁盘的凭证图片相对路径集合',
            'review_status=0 表示凭证待后台审核',
            'created_by 表示凭证提交人的显示名称',
            'records 用于返回当前前台用户自己的凭证提交记录',
            'review_status 表示按凭证审核状态筛选',
            'date_from 表示凭证创建开始日期',
            'date_to 表示凭证创建结束日期',
            'per_page 表示每页返回记录数量',
            'records 返回 Laravel 分页对象',
        ];

        foreach ($expectedComments as $expectedComment) {
            $this->assertStringContainsString($expectedComment, $source, 'Front VoucherController 缺少中文逻辑注释：' . $expectedComment);
        }
    }

    public function test_front_voucher_controller_does_not_keep_legacy_english_comment_titles(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Front/VoucherController.php')) ?: '';

        $legacyEnglishComments = [
            'Submit voucher',
            'Get current user\'s voucher submissions',
            'Store to storage',
            'Pending',
        ];

        foreach ($legacyEnglishComments as $legacyEnglishComment) {
            $this->assertStringNotContainsString($legacyEnglishComment, $source, 'Front VoucherController 仍残留旧英文注释标题：' . $legacyEnglishComment);
        }
    }
}
