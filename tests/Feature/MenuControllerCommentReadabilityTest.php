<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/05
 * Time: 08:42
 */

/**
 * MenuControllerCommentReadabilityTest
 *
 * 文件功能：
 * - 验证前后台菜单控制器保持可读中文逻辑注释，并禁止典型乱码片段回流。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * @runClassInSeparateProcess
 * @preserveGlobalState disabled
 *
 * 前后台菜单控制器中文注释可读性测试。
 *
 * 功能逻辑说明：
 * - 前台菜单控制器负责代理商和普通客户的菜单树返回，是 Layui 侧栏菜单是否显示的直接入口。
 * - 后台菜单控制器负责管理员菜单树、按钮权限 slug、菜单管理 CRUD，是后台权限配置的直接入口。
 * - 本测试只约束源码注释和参数说明可读性，不改变接口行为和数据库查询逻辑。
 */
class MenuControllerCommentReadabilityTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        gc_collect_cycles();
    }

    /**
     * 前台菜单控制器必须说明角色权限来源、请求参数和返回结构。
     *
     * 参数含义：
     * - $content：前台菜单控制器源码内容，用于确认核心中文说明是否存在。
     * - $requiredTexts：必须存在的中文说明片段，覆盖用户角色、权限 ID、菜单树和返回结构。
     *
     * @return void
     */
    public function test_front_menu_controller_contains_readable_chinese_logic_comments(): void
    {
        $content = file_get_contents(app_path('Http/Controllers/Front/MenuController.php'));

        $requiredTexts = [
            '前台菜单控制器',
            '根据当前登录用户的角色读取 role_permissions',
            '$request：当前 HTTP 请求对象',
            '$permissionIds：当前前台角色拥有的 permissions.id 列表',
            '返回值：统一 JSON 响应，data 为当前用户可见的菜单树',
        ];

        foreach ($requiredTexts as $text) {
            $this->assertStringContainsString($text, $content);
        }
    }

    /**
     * 后台菜单控制器必须说明管理员菜单、按钮权限和菜单管理参数。
     *
     * 参数含义：
     * - $content：后台菜单控制器源码内容，用于确认后台权限边界注释是否存在。
     * - $requiredTexts：必须存在的中文说明片段，覆盖菜单树、按钮权限、字段映射和唯一 slug。
     *
     * @return void
     */
    public function test_admin_menu_controller_contains_readable_chinese_logic_comments(): void
    {
        $content = file_get_contents(app_path('Http/Controllers/Admin/MenuController.php'));

        $requiredTexts = [
            '后台菜单管理控制器',
            'data.menus 为菜单树，data.permissions 为权限 slug 数组',
            '前端按钮控制只是体验优化，后端接口仍由 check.permission:admin 再次校验',
            '$guardType：菜单所属守卫类型',
            '$slug：最终写入 permissions.slug 的稳定权限标识',
            '菜单 slug 是前端多语言 key 的基础',
        ];

        foreach ($requiredTexts as $text) {
            $this->assertStringContainsString($text, $content);
        }
    }

    /**
     * 前后台菜单控制器不能出现典型中文乱码片段。
     *
     * 参数含义：
     * - $files：需要扫描的菜单控制器文件路径列表。
     * - $fragment：单个乱码特征片段，命中时说明源码注释不可读。
     *
     * @return void
     */
    public function test_menu_controllers_do_not_contain_mojibake_comment_fragments(): void
    {
        $files = [
            app_path('Http/Controllers/Front/MenuController.php'),
            app_path('Http/Controllers/Admin/MenuController.php'),
        ];

        foreach ($files as $file) {
            $content = file_get_contents($file);

            foreach (['鐨', '鏉', '閺', '娴', '绠', '缁', '閸', '闁', '婢', '锛', '鍚', '鍙', '�'] as $fragment) {
                $this->assertStringNotContainsString($fragment, $content, $file . ' 存在疑似乱码片段：' . $fragment);
            }
        }
    }
}
