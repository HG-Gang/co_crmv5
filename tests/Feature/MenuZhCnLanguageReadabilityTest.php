<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/07/05
 * Time: 08:42
 */

/**
 * MenuZhCnLanguageReadabilityTest
 *
 * 文件功能：
 * - 验证中文菜单语言包：key 与英文包一致、核心标题可读中文、不含 UTF-8/GBK 错解乱码片段。
 * - 输入：控制器/模型/JS/Blade/CSS 等源码与语言包文本；输出：PHPUnit 断言结果。
 * - 明确不负责：不验证运行时业务行为与数据库交互。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * @runClassInSeparateProcess
 * @preserveGlobalState disabled
 *
 * 前后台菜单中文语言包可读性测试。
 *
 * 功能逻辑说明：
 * - 菜单接口通过 `MenuService::buildTree()` 读取 `__('menus.xxx')` 生成返回给 Layui/Blade 的菜单标题。
 * - 如果 `resources/lang/zh-CN/menus.php` 出现乱码，前台代理商、普通客户和后台管理员看到的菜单都会不可读。
 * - 本测试约束中文菜单包与英文菜单包 key 对齐，并检查高频前后台菜单标题必须是可读中文。
 */
class MenuZhCnLanguageReadabilityTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        gc_collect_cycles();
    }

    /**
     * 中文菜单语言包的 key 必须与英文菜单语言包保持一致。
     *
     * 参数含义：
     * - $enKeys：英文菜单语言包 key 列表，是菜单翻译完整性的参照集合。
     * - $zhKeys：中文菜单语言包 key 列表，必须与英文包完全一致，避免某个菜单回退成 key。
     *
     * @return void
     */
    public function test_zh_cn_menu_language_keys_match_english_keys(): void
    {
        $enKeys = array_keys(require resource_path('lang/en/menus.php'));
        $zhKeys = array_keys(require resource_path('lang/zh-CN/menus.php'));

        sort($enKeys);
        sort($zhKeys);

        $this->assertSame($enKeys, $zhKeys);
    }

    /**
     * 前后台高频菜单标题必须返回可读中文。
     *
     * 参数含义：
     * - $menus：中文菜单语言包数组，用于读取指定 slug 的标题。
     * - $expected：关键菜单 slug 与期望中文标题映射，覆盖前台和后台高频入口。
     *
     * @return void
     */
    public function test_zh_cn_menu_language_contains_readable_core_titles(): void
    {
        $menus = require resource_path('lang/zh-CN/menus.php');

        $expected = [
            'front_dashboard' => '控制台',
            'front_agent' => '代理管理',
            'front_agent_sub' => '下级代理',
            'front_commission' => '返佣管理',
            'front_gift' => '礼品中心',
            'admin_dashboard' => '控制台',
            'admin_user_mgmt' => '用户管理',
            'admin_finance' => '财务管理',
            'admin_system' => '系统管理',
            'admin_risk' => '风控管理',
            'admin_online_users' => '在线用户',
            'admin_authentications' => '实名认证审核',
        ];

        foreach ($expected as $key => $title) {
            $this->assertSame($title, $menus[$key], $key . ' 菜单标题必须是可读中文。');
        }
    }

    /**
     * 中文菜单语言包不能包含典型乱码片段。
     *
     * 参数含义：
     * - $content：中文菜单语言包源码内容，用于扫描 UTF-8/GBK 错解后的乱码片段。
     * - $fragment：单个乱码特征片段，命中时说明菜单标题或注释不可读。
     *
     * @return void
     */
    public function test_zh_cn_menu_language_does_not_contain_mojibake_fragments(): void
    {
        $content = file_get_contents(resource_path('lang/zh-CN/menus.php'));

        foreach (['鐨', '鏉', '閺', '娴', '绠', '缁', '閸', '闁', '婢', '锛', '鍚', '鍙', '�'] as $fragment) {
            $this->assertStringNotContainsString($fragment, $content, 'zh-CN/menus.php 存在疑似乱码片段：' . $fragment);
        }
    }
}
