<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/05
 * Time: 08:38
 */

/**
 * PermissionModelCommentReadabilityTest
 *
 * 文件功能：
 * - 验证权限模型保持可读中文逻辑注释，编辑导致乱码回流时立即失败。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * @runClassInSeparateProcess
 * @preserveGlobalState disabled
 *
 * 权限模型中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - 用户要求所有模块文件和参数必须有详细中文注释，权限模型是后台菜单、按钮和接口鉴权的数据源模型。
 * - 本测试只做源码可读性约束，不连接数据库，不改变权限模型行为。
 * - 如果后续编辑导致中文注释重新变成 mojibake 乱码，本测试会立即失败。
 */
class PermissionModelCommentReadabilityTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        gc_collect_cycles();
    }

    /**
     * Permission 模型必须包含可读中文功能注释和参数含义。
     *
     * 参数与变量含义：
     * - $source：Permission 模型源码内容，用于检查中文注释是否可读。
     * - $requiredPhrases：必须存在的中文说明片段，覆盖模型职责、核心字段、关联关系和 scope 参数含义。
     * - $forbiddenFragments：历史乱码常见片段，出现时说明文件注释仍不可读。
     *
     * @return void
     */
    public function test_permission_model_contains_readable_chinese_logic_comments(): void
    {
        $source = (string) file_get_contents(app_path('Models/Permission.php'));

        $requiredPhrases = [
            '权限模型',
            'permissions 表保存前后台菜单、页面、按钮和接口权限字典',
            'slug 表示稳定权限字符串',
            'api_route 表示 Laravel 命名路由',
            'guard_type 用于区分 admin 和 front',
            'parent_id 表示父级 permissions.id',
            'type 表示权限类型',
            'status 表示启停状态',
            '关联父级权限',
            '关联子权限集合',
            '关联拥有该权限的角色集合',
            '$query 表示 Eloquent 查询构造器',
            '限定后台权限',
            '限定按钮或接口动作权限',
        ];

        foreach ($requiredPhrases as $phrase) {
            $this->assertStringContainsString($phrase, $source, 'Permission 模型缺少中文说明：' . $phrase);
        }

        $forbiddenFragments = [
            '鏉冮檺',
            '鍔熻兘',
            '閫昏緫',
            '鍙傛暟',
            '绠＄悊',
            '鑿滃崟',
            '瀹堝崼',
        ];

        foreach ($forbiddenFragments as $fragment) {
            $this->assertStringNotContainsString($fragment, $source, 'Permission 模型仍存在乱码片段：' . $fragment);
        }
    }
}
