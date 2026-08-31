<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/30
 * Time: 22:10
 */

/**
 * UiOrphanClassAndTouchTargetClosureModuleTest
 *
 * 文件功能：
 * - 锁定「Blade 引用了但 CSS 从未定义」的孤儿类不再出现，以及图标触控目标不低于 44px。
 * - 缺陷背景（本轮静态审计实测）：四套 UI 家族共有 13 个项目自有类被写在页面上却没有任何
 *   CSS 定义、也没有 JS 或测试引用，属于「写了完全不起作用」。其中影响最明显的是
 *   crm-page-head / crm-page-title / crm-page-desc 整族零定义——因 layui-card-header
 *   自带 font-weight:800 与 --crm-ink，副标题会完整继承标题样式，渲染成两行一样粗的标题。
 * - 触控目标：项目自身已有 46 处 44px 约定，但语言/主题切换的图标触发器为 38x38，
 *   低于该约定；纯图标按钮没有文字扩大可点区域，38px 在移动端会频繁点击落空。
 * - 输入：public/css 全量样式表；输出：PHPUnit 断言结果。
 * - 明确不负责：不校验视觉呈现是否美观（需真实浏览器），只校验「类有定义」与「尺寸达标」
 *   这两个可静态判定的硬性条件。
 */

namespace Tests\Feature;

use Tests\TestCase;

/**
 * UI 孤儿类与触控目标闭环测试。
 */
class UiOrphanClassAndTouchTargetClosureModuleTest extends TestCase
{
    /**
     * 本轮补齐定义的类名清单。
     *
     * 这些类此前只出现在 Blade 里，CSS 无定义、JS 无引用、测试无锚点。
     * 一旦有人删掉对应 CSS 块，页面会静默失去样式而不报任何错，
     * 因此必须由测试守住「定义存在」这条底线。
     *
     * @var array<int, string>
     */
    private const REQUIRED_CLASSES = [
        // 卡片页头三件套：标题与副标题的层级差异全靠这三个类。
        'crm-page-head',
        'crm-page-title',
        'crm-page-desc',
        // 链路渐进展开的节点容器（.crm-chain-node 单数早已定义，复数容器此前缺失）。
        'crm-chain-nodes',
        // 表单操作按钮行。
        'crm-form-actions',
        // 支付通道页签容器。
        'crm-channel-tab',
        // 语言与主题切换入口在 Layui 顶栏里的外层包装。
        'crm-preference-nav-item',
        'crm-theme-picker-nav-item',
        // 旧后台登录页图形验证码行（输入框与验证码图并排）。
        'admin-legacy-captcha-field',
        // 前台日期区间容器与统计条外层包装。
        'crm-date-range',
        'crm-table-summary-bar',
        // CrmUI 批量操作弹窗里的单选分组容器。
        'crmui-fieldset',
    ];

    /**
     * 必须达到 44px 触控目标的选择器与其所在样式表。
     *
     * @var array<string, string>
     */
    private const TOUCH_TARGETS = [
        'crm-preference-trigger' => 'common/crm-themes.css',
    ];

    /**
     * 所有本轮补齐的类必须在 CSS 中真实存在精确定义。
     *
     * 采用精确类选择器匹配（.cls 后不得紧跟类名字符），避免 crm-page 命中
     * crm-page-head 这类子串误判——正是这种子串匹配曾让人误以为类已定义。
     *
     * @return void
     */
    public function test_previously_orphan_classes_are_defined_in_css(): void
    {
        $css = $this->cssBlob();

        foreach (self::REQUIRED_CLASSES as $class) {
            $this->assertMatchesRegularExpression(
                '/\.' . preg_quote($class, '/') . '(?![A-Za-z0-9_-])/',
                $css,
                "类 .{$class} 在 CSS 中没有精确定义，页面上写了却不会生效"
            );
        }
    }

    /**
     * 页头副标题必须显式声明非加粗字重。
     *
     * 仅改颜色不足以形成层级：layui-card-header 的 font-weight:800 会被继承，
     * 而深色主题下 muted 与 ink 的对比差被压缩，字重相同时两行几乎无法区分。
     *
     * @return void
     */
    public function test_page_desc_overrides_inherited_bold_weight(): void
    {
        $css = (string) file_get_contents(public_path('css/common/crm-design-system.css'));

        $this->assertMatchesRegularExpression(
            '/\.crm-page-desc\s*\{[^}]*font-weight\s*:\s*(400|normal)/s',
            $css,
            'crm-page-desc 未显式覆盖继承来的 800 字重，副标题会与标题一样粗'
        );
    }

    /**
     * 图标触发器的触控目标不得低于 44px。
     *
     * @return void
     */
    public function test_icon_triggers_meet_minimum_touch_target(): void
    {
        foreach (self::TOUCH_TARGETS as $class => $file) {
            $css = (string) file_get_contents(public_path('css/' . $file));

            $this->assertMatchesRegularExpression(
                '/\.' . preg_quote($class, '/') . '\s*\{[^}]*\bwidth\s*:\s*(4[4-9]|[5-9]\d|\d{3,})px/s',
                $css,
                ".{$class} 的宽度低于 44px 触控目标"
            );
            $this->assertMatchesRegularExpression(
                '/\.' . preg_quote($class, '/') . '\s*\{[^}]*\bheight\s*:\s*(4[4-9]|[5-9]\d|\d{3,})px/s',
                $css,
                ".{$class} 的高度低于 44px 触控目标"
            );
        }
    }

    /**
     * 前台资料页引用的语言键必须在 JS 语言包中存在。
     *
     * 缺陷机制：这些键由客户端 CrmLang 解析（data-translate 属性），
     * i18n.js 的 translate() 在「运行时语言包 → 内置 fallback → 中文 fallback」三级查找
     * 全部失败后会 `return humanizeKey(key)` —— 取键末段首字母大写后直接渲染。
     * 因此缺键不会报错、也不显示原始点号键，而是渲染出 BankCardNo / IdentityVerification
     * 这类裸驼峰英文，在中文界面下尤为突兀，且只在切换语言后暴露，极易漏测。
     *
     * @return void
     */
    public function test_front_profile_language_keys_exist_in_js_packs(): void
    {
        $keys = [
            // 实名认证区块
            'identityVerification', 'realName', 'idCardNo',
            // 手机与验证码区块
            'phoneSettings', 'phoneCode', 'emailCode', 'sendCode', 'verificationCode',
            // 银行卡区块
            'bankCardInfo', 'bankName', 'bankBranch', 'bankCardNo', 'bankAccountName',
        ];

        foreach (['zh-CN', 'en'] as $locale) {
            $pack = (string) file_get_contents(public_path('js/shared/lang/common/' . $locale . '.js'));
            $this->assertNotSame('', trim($pack), "语言包为空：{$locale}");

            foreach ($keys as $key) {
                $this->assertMatchesRegularExpression(
                    '/\b' . preg_quote($key, '/') . '\s*:/',
                    $pack,
                    "语言包 {$locale} 缺少 profile.{$key}，页面会退化成 humanizeKey 输出"
                );
            }

            // auth 组的蛇形写法，前台资料页以 auth.send_code 引用。
            $this->assertMatchesRegularExpression(
                '/\bsend_code\s*:/',
                $pack,
                "语言包 {$locale} 缺少 auth.send_code"
            );
        }
    }

    /**
     * 合并全部项目样式表为单一检索语料。
     *
     * 不含 vendor 目录：Layui 官方样式不属于本项目维护范围，
     * 把它并入会让「项目自有类是否定义」的判定失真。
     *
     * @return string 拼接后的 CSS 文本。
     */
    private function cssBlob(): string
    {
        $blob = '';
        $base = public_path('css');
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'css') {
                continue;
            }
            $path = str_replace('\\', '/', $file->getPathname());
            if (strpos($path, '/vendor/') !== false) {
                continue;
            }
            $blob .= "\n" . (string) file_get_contents($path);
        }

        $this->assertNotSame('', trim($blob), 'public/css 下未读到任何样式表');

        return $blob;
    }
}
