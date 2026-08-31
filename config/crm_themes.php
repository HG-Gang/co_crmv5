<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/28
 * Time: 00:22
 */

/*
|--------------------------------------------------------------------------
| CRM 主题目录（视觉 C）
|--------------------------------------------------------------------------
| 单一事实源：本文件的调色板经 resources/views/partials/theme-assets.blade.php
| 展开为 CSS 自定义属性，供 Layui / CrmUI / Naive 三个视觉家族共同消费。
|
| 2026-08-28 清爽化改版：统一收敛为「冷白底 + 纯白面 + 高饱和强调色 + 清晰描边」，
| 去掉原先偏灰偏浊的中间色，让页面整体更透气。
|
| 修改任何颜色后必须保持以下门禁（tests/Feature/GlobalCrmThemeCoverageTest.php）：
|  - 19 组文本配对 >= 4.5:1（WCAG AA）
|  - border_strong 对 surface / background / surface_alt >= 3:1（非文本对比）
|  - 10 个新增主题的签名元组唯一，且 accent 之间 RGB 距离 >= 25
*/

$lightSemanticColors = [
    // danger：危险/错误前景色（HEX）。
    'danger' => '#C0261B',
    // danger_bg：危险/错误背景色（HEX）。
    'danger_bg' => '#FDECEA',
    // danger_on：危险背景上的前景文本色（HEX）。
    'danger_on' => '#FFFFFF',
    // warning：警告前景色（HEX）。
    'warning' => '#7A4E00',
    // warning_bg：警告背景色（HEX）。
    'warning_bg' => '#FFF4D6',
    // warning_on：警告背景上的前景文本色（HEX）。
    'warning_on' => '#FFFFFF',
];

$darkSemanticColors = [
    // danger：危险/错误前景色（HEX）。
    'danger' => '#FFAAA3',
    // danger_bg：危险/错误背景色（HEX）。
    'danger_bg' => '#4B201D',
    // danger_on：危险背景上的前景文本色（HEX）。
    'danger_on' => '#24100E',
    // warning：警告前景色（HEX）。
    'warning' => '#F5CD79',
    // warning_bg：警告背景色（HEX）。
    'warning_bg' => '#493814',
    // warning_on：警告背景上的前景文本色（HEX）。
    'warning_on' => '#2B2109',
];

$theme = static function (string $label, string $mode, array $colors, array $ui) use ($lightSemanticColors, $darkSemanticColors): array {
    $semanticColors = $mode === 'dark' ? $darkSemanticColors : $lightSemanticColors;

    return [
        // label：主题多语言标签键（front.theme_*），前台切换器显示用。
        'label' => $label,
        // mode：明暗模式（light/dark），决定合并亮色或暗色语义色。
        'mode' => $mode,
        // colors：调色板 = 语义色（按 mode 选择）与主题自有颜色合并。
        'colors' => array_merge($semanticColors, $colors),
        // ui：非颜色 UI 参数组（圆角、侧栏宽度、投影、行高、导航与面板形态）。
        'ui' => $ui,
    ];
};

return [
    // themes：主题定义总表；每个键是主题标识（前台切换用），值由 $theme() 工厂展开为 label/mode/colors/ui。
    'themes' => [
        // 默认日光：冷白底 + 蔚蓝强调 + 深墨蓝侧栏
        'light' => $theme('front.theme_light', 'light', [
            // background：页面最底层背景色（HEX #RRGGBB）；与 text/border 的对比度受门禁约束。
            'background' => '#F7F9FC',
            // surface：卡片/面板表面色（HEX）；与 background 形成层次。
            'surface' => '#FFFFFF',
            // surface_alt：次级表面/隔行底色（HEX）。
            'surface_alt' => '#EEF3F9',
            // text：正文文本色（HEX）；对 background/surface 须 >= 4.5:1（WCAG AA）。
            'text' => '#33415A',
            // muted：次要文本色（HEX）；对 surface 须 >= 4.5:1。
            'muted' => '#55637A',
            // heading：标题文本色（HEX）；通常为该主题最深色。
            'heading' => '#0F172A',
            // border：常规描边/分隔线色（HEX）。
            'border' => '#DCE4EF',
            // border_strong：强描边色（HEX）；对 surface/background/surface_alt 须 >= 3:1（非文本对比门禁）。
            'border_strong' => '#6B7C93',
            // accent：品牌强调色（HEX）；主题之间 RGB 距离须 >= 25，改值会影响所有选中/链接/按钮。
            'accent' => '#0B6BCB',
            // accent_hover：强调色悬停态（HEX）；须与 accent 同族且可区分。
            'accent_hover' => '#0A5AAD',
            // on_accent：强调色之上的前景文本色（HEX）；对 accent 须 >= 4.5:1。
            'on_accent' => '#FFFFFF',
            // focus：键盘焦点环颜色（HEX）。
            'focus' => '#0B6BCB',
            // sidebar：侧栏背景色（HEX）。
            'sidebar' => '#14243B',
            // sidebar_hover：侧栏菜单悬停背景色（HEX）。
            'sidebar_hover' => '#223A5B',
            // sidebar_text：侧栏主文本色（HEX）；对 sidebar 须 >= 4.5:1。
            'sidebar_text' => '#F4F8FD',
            // sidebar_muted：侧栏次文本色（HEX）；对 sidebar 须 >= 4.5:1。
            'sidebar_muted' => '#B4C4D8',
        ], [
            // radius/radius_sm：面板与小组件圆角（CSS 长度，px）；sidebar_width：侧栏宽度（px），影响内容区栅格。
            'radius' => '6px', 'radius_sm' => '4px', 'sidebar_width' => '236px',
            // shadow：面板投影（CSS box-shadow 值，none=无投影）；table_row_height：表格行高（px），影响数据密度。
            'shadow' => '0 2px 10px rgba(15, 23, 42, .06)', 'table_row_height' => '42px',
            // nav_style：导航视觉形态（left-line/tab/fill/outline/compact/block/underline/marker/grid/sidebar-fill）；panel_style：面板视觉形态（top-line/topbar-line/soft-shadow/strong-border/none/panel-left/title-line/dark-sidebar/nav-mark/table-rule）。
            'nav_style' => 'left-line', 'panel_style' => 'top-line',
        ]),
        // 夜间：深蓝黑底 + 亮天蓝强调
        'dark' => $theme('front.theme_dark', 'dark', [
            // background：页面最底层背景色（HEX #RRGGBB）；与 text/border 的对比度受门禁约束。
            'background' => '#0F1620',
            // surface：卡片/面板表面色（HEX）；与 background 形成层次。
            'surface' => '#17202D',
            // surface_alt：次级表面/隔行底色（HEX）。
            'surface_alt' => '#212C3C',
            // text：正文文本色（HEX）；对 background/surface 须 >= 4.5:1（WCAG AA）。
            'text' => '#DCE5EF',
            // muted：次要文本色（HEX）；对 surface 须 >= 4.5:1。
            'muted' => '#A8B6C6',
            // heading：标题文本色（HEX）；通常为该主题最深色。
            'heading' => '#F7FAFC',
            // border：常规描边/分隔线色（HEX）。
            'border' => '#33415A',
            // border_strong：强描边色（HEX）；对 surface/background/surface_alt 须 >= 3:1（非文本对比门禁）。
            'border_strong' => '#7C8DA3',
            // accent：品牌强调色（HEX）；主题之间 RGB 距离须 >= 25，改值会影响所有选中/链接/按钮。
            'accent' => '#6FB6FF',
            // accent_hover：强调色悬停态（HEX）；须与 accent 同族且可区分。
            'accent_hover' => '#97CBFF',
            // on_accent：强调色之上的前景文本色（HEX）；对 accent 须 >= 4.5:1。
            'on_accent' => '#06182B',
            // focus：键盘焦点环颜色（HEX）。
            'focus' => '#6FB6FF',
            // sidebar：侧栏背景色（HEX）。
            'sidebar' => '#0A111B',
            // sidebar_hover：侧栏菜单悬停背景色（HEX）。
            'sidebar_hover' => '#1B2735',
            // sidebar_text：侧栏主文本色（HEX）；对 sidebar 须 >= 4.5:1。
            'sidebar_text' => '#F2F7FC',
            // sidebar_muted：侧栏次文本色（HEX）；对 sidebar 须 >= 4.5:1。
            'sidebar_muted' => '#A7B7C8',
        ], [
            // radius/radius_sm：面板与小组件圆角（CSS 长度，px）；sidebar_width：侧栏宽度（px），影响内容区栅格。
            'radius' => '6px', 'radius_sm' => '4px', 'sidebar_width' => '236px',
            // shadow：面板投影（CSS box-shadow 值，none=无投影）；table_row_height：表格行高（px），影响数据密度。
            'shadow' => '0 4px 16px rgba(0, 0, 0, .32)', 'table_row_height' => '42px',
            // nav_style：导航视觉形态（left-line/tab/fill/outline/compact/block/underline/marker/grid/sidebar-fill）；panel_style：面板视觉形态（top-line/topbar-line/soft-shadow/strong-border/none/panel-left/title-line/dark-sidebar/nav-mark/table-rule）。
            'nav_style' => 'left-line', 'panel_style' => 'top-line',
        ]),
        // 海蓝：青蓝强调 + 深青侧栏
        'sea' => $theme('front.theme_sea', 'light', [
            // background：页面最底层背景色（HEX #RRGGBB）；与 text/border 的对比度受门禁约束。
            'background' => '#F2FAFB',
            // surface：卡片/面板表面色（HEX）；与 background 形成层次。
            'surface' => '#FFFFFF',
            // surface_alt：次级表面/隔行底色（HEX）。
            'surface_alt' => '#E3F3F5',
            // text：正文文本色（HEX）；对 background/surface 须 >= 4.5:1（WCAG AA）。
            'text' => '#1F4A50',
            // muted：次要文本色（HEX）；对 surface 须 >= 4.5:1。
            'muted' => '#4C6A70',
            // heading：标题文本色（HEX）；通常为该主题最深色。
            'heading' => '#0C3439',
            // border：常规描边/分隔线色（HEX）。
            'border' => '#C8E1E4',
            // border_strong：强描边色（HEX）；对 surface/background/surface_alt 须 >= 3:1（非文本对比门禁）。
            'border_strong' => '#5E8A90',
            // accent：品牌强调色（HEX）；主题之间 RGB 距离须 >= 25，改值会影响所有选中/链接/按钮。
            'accent' => '#0E7490',
            // accent_hover：强调色悬停态（HEX）；须与 accent 同族且可区分。
            'accent_hover' => '#0B6178',
            // on_accent：强调色之上的前景文本色（HEX）；对 accent 须 >= 4.5:1。
            'on_accent' => '#FFFFFF',
            // focus：键盘焦点环颜色（HEX）。
            'focus' => '#0E7490',
            // sidebar：侧栏背景色（HEX）。
            'sidebar' => '#0C3439',
            // sidebar_hover：侧栏菜单悬停背景色（HEX）。
            'sidebar_hover' => '#17505A',
            // sidebar_text：侧栏主文本色（HEX）；对 sidebar 须 >= 4.5:1。
            'sidebar_text' => '#F2FAFB',
            // sidebar_muted：侧栏次文本色（HEX）；对 sidebar 须 >= 4.5:1。
            'sidebar_muted' => '#AFCDD2',
        ], [
            // radius/radius_sm：面板与小组件圆角（CSS 长度，px）；sidebar_width：侧栏宽度（px），影响内容区栅格。
            'radius' => '8px', 'radius_sm' => '6px', 'sidebar_width' => '232px',
            // shadow：面板投影（CSS box-shadow 值，none=无投影）；table_row_height：表格行高（px），影响数据密度。
            'shadow' => '0 3px 12px rgba(12, 52, 57, .07)', 'table_row_height' => '42px',
            // nav_style：导航视觉形态（left-line/tab/fill/outline/compact/block/underline/marker/grid/sidebar-fill）；panel_style：面板视觉形态（top-line/topbar-line/soft-shadow/strong-border/none/panel-left/title-line/dark-sidebar/nav-mark/table-rule）。
            'nav_style' => 'tab', 'panel_style' => 'topbar-line',
        ]),
        // 暖绿：翡翠强调 + 深林侧栏
        'warm' => $theme('front.theme_warm', 'light', [
            // background：页面最底层背景色（HEX #RRGGBB）；与 text/border 的对比度受门禁约束。
            'background' => '#F5FAF7',
            // surface：卡片/面板表面色（HEX）；与 background 形成层次。
            'surface' => '#FFFFFF',
            // surface_alt：次级表面/隔行底色（HEX）。
            'surface_alt' => '#E7F3EC',
            // text：正文文本色（HEX）；对 background/surface 须 >= 4.5:1（WCAG AA）。
            'text' => '#27443A',
            // muted：次要文本色（HEX）；对 surface 须 >= 4.5:1。
            'muted' => '#506A5D',
            // heading：标题文本色（HEX）；通常为该主题最深色。
            'heading' => '#10362A',
            // border：常规描边/分隔线色（HEX）。
            'border' => '#C8DFD3',
            // border_strong：强描边色（HEX）；对 surface/background/surface_alt 须 >= 3:1（非文本对比门禁）。
            'border_strong' => '#66907F',
            // accent：品牌强调色（HEX）；主题之间 RGB 距离须 >= 25，改值会影响所有选中/链接/按钮。
            'accent' => '#047857',
            // accent_hover：强调色悬停态（HEX）；须与 accent 同族且可区分。
            'accent_hover' => '#036147',
            // on_accent：强调色之上的前景文本色（HEX）；对 accent 须 >= 4.5:1。
            'on_accent' => '#FFFFFF',
            // focus：键盘焦点环颜色（HEX）。
            'focus' => '#047857',
            // sidebar：侧栏背景色（HEX）。
            'sidebar' => '#10362A',
            // sidebar_hover：侧栏菜单悬停背景色（HEX）。
            'sidebar_hover' => '#1D523F',
            // sidebar_text：侧栏主文本色（HEX）；对 sidebar 须 >= 4.5:1。
            'sidebar_text' => '#F5FAF7',
            // sidebar_muted：侧栏次文本色（HEX）；对 sidebar 须 >= 4.5:1。
            'sidebar_muted' => '#B2CDC0',
        ], [
            // radius/radius_sm：面板与小组件圆角（CSS 长度，px）；sidebar_width：侧栏宽度（px），影响内容区栅格。
            'radius' => '8px', 'radius_sm' => '6px', 'sidebar_width' => '244px',
            // shadow：面板投影（CSS box-shadow 值，none=无投影）；table_row_height：表格行高（px），影响数据密度。
            'shadow' => '0 4px 14px rgba(16, 54, 42, .08)', 'table_row_height' => '44px',
            // nav_style：导航视觉形态（left-line/tab/fill/outline/compact/block/underline/marker/grid/sidebar-fill）；panel_style：面板视觉形态（top-line/topbar-line/soft-shadow/strong-border/none/panel-left/title-line/dark-sidebar/nav-mark/table-rule）。
            'nav_style' => 'fill', 'panel_style' => 'soft-shadow',
        ]),
        // 高对比：中性石板强调 + 直角描边
        'contrast' => $theme('front.theme_contrast', 'light', [
            // background：页面最底层背景色（HEX #RRGGBB）；与 text/border 的对比度受门禁约束。
            'background' => '#F5F7FA',
            // surface：卡片/面板表面色（HEX）；与 background 形成层次。
            'surface' => '#FFFFFF',
            // surface_alt：次级表面/隔行底色（HEX）。
            'surface_alt' => '#E9EEF4',
            // text：正文文本色（HEX）；对 background/surface 须 >= 4.5:1（WCAG AA）。
            'text' => '#26334A',
            // muted：次要文本色（HEX）；对 surface 须 >= 4.5:1。
            'muted' => '#4D5C72',
            // heading：标题文本色（HEX）；通常为该主题最深色。
            'heading' => '#080D18',
            // border：常规描边/分隔线色（HEX）。
            'border' => '#C3CEDC',
            // border_strong：强描边色（HEX）；对 surface/background/surface_alt 须 >= 3:1（非文本对比门禁）。
            'border_strong' => '#6D7A8B',
            // accent：品牌强调色（HEX）；主题之间 RGB 距离须 >= 25，改值会影响所有选中/链接/按钮。
            'accent' => '#334155',
            // accent_hover：强调色悬停态（HEX）；须与 accent 同族且可区分。
            'accent_hover' => '#26313F',
            // on_accent：强调色之上的前景文本色（HEX）；对 accent 须 >= 4.5:1。
            'on_accent' => '#FFFFFF',
            // focus：键盘焦点环颜色（HEX）。
            'focus' => '#334155',
            // sidebar：侧栏背景色（HEX）。
            'sidebar' => '#111827',
            // sidebar_hover：侧栏菜单悬停背景色（HEX）。
            'sidebar_hover' => '#26334A',
            // sidebar_text：侧栏主文本色（HEX）；对 sidebar 须 >= 4.5:1。
            'sidebar_text' => '#F8FAFC',
            // sidebar_muted：侧栏次文本色（HEX）；对 sidebar 须 >= 4.5:1。
            'sidebar_muted' => '#BCC7D6',
        ], [
            // radius/radius_sm：面板与小组件圆角（CSS 长度，px）；sidebar_width：侧栏宽度（px），影响内容区栅格。
            'radius' => '3px', 'radius_sm' => '2px', 'sidebar_width' => '224px',
            // shadow：面板投影（CSS box-shadow 值，none=无投影）；table_row_height：表格行高（px），影响数据密度。
            'shadow' => '0 1px 6px rgba(8, 13, 24, .08)', 'table_row_height' => '40px',
            // nav_style：导航视觉形态（left-line/tab/fill/outline/compact/block/underline/marker/grid/sidebar-fill）；panel_style：面板视觉形态（top-line/topbar-line/soft-shadow/strong-border/none/panel-left/title-line/dark-sidebar/nav-mark/table-rule）。
            'nav_style' => 'outline', 'panel_style' => 'strong-border',
        ]),
        // 清蓝：浅色侧栏 + 蔚蓝左侧指示线
        'clear-blue' => $theme('front.theme_clear_blue', 'light', [
            // background：页面最底层背景色（HEX #RRGGBB）；与 text/border 的对比度受门禁约束。
            'background' => '#F6F9FD',
            // surface：卡片/面板表面色（HEX）；与 background 形成层次。
            'surface' => '#FFFFFF',
            // surface_alt：次级表面/隔行底色（HEX）。
            'surface_alt' => '#E8F1FB',
            // text：正文文本色（HEX）；对 background/surface 须 >= 4.5:1（WCAG AA）。
            'text' => '#243247',
            // muted：次要文本色（HEX）；对 surface 须 >= 4.5:1。
            'muted' => '#4F5E75',
            // heading：标题文本色（HEX）；通常为该主题最深色。
            'heading' => '#0E1728',
            // border：常规描边/分隔线色（HEX）。
            'border' => '#D3E1F1',
            // border_strong：强描边色（HEX）；对 surface/background/surface_alt 须 >= 3:1（非文本对比门禁）。
            'border_strong' => '#6A7F98',
            // accent：品牌强调色（HEX）；主题之间 RGB 距离须 >= 25，改值会影响所有选中/链接/按钮。
            'accent' => '#0B6BCB',
            // accent_hover：强调色悬停态（HEX）；须与 accent 同族且可区分。
            'accent_hover' => '#0A5AAD',
            // on_accent：强调色之上的前景文本色（HEX）；对 accent 须 >= 4.5:1。
            'on_accent' => '#FFFFFF',
            // focus：键盘焦点环颜色（HEX）。
            'focus' => '#0B6BCB',
            // sidebar：侧栏背景色（HEX）。
            'sidebar' => '#F9FBFE',
            // sidebar_hover：侧栏菜单悬停背景色（HEX）。
            'sidebar_hover' => '#E4EFFA',
            // sidebar_text：侧栏主文本色（HEX）；对 sidebar 须 >= 4.5:1。
            'sidebar_text' => '#243247',
            // sidebar_muted：侧栏次文本色（HEX）；对 sidebar 须 >= 4.5:1。
            'sidebar_muted' => '#4F5E75',
        ], [
            // radius/radius_sm：面板与小组件圆角（CSS 长度，px）；sidebar_width：侧栏宽度（px），影响内容区栅格。
            'radius' => '6px', 'radius_sm' => '4px', 'sidebar_width' => '236px',
            // shadow：面板投影（CSS box-shadow 值，none=无投影）；table_row_height：表格行高（px），影响数据密度。
            'shadow' => '0 2px 10px rgba(14, 41, 74, .07)', 'table_row_height' => '42px',
            // nav_style：导航视觉形态（left-line/tab/fill/outline/compact/block/underline/marker/grid/sidebar-fill）；panel_style：面板视觉形态（top-line/topbar-line/soft-shadow/strong-border/none/panel-left/title-line/dark-sidebar/nav-mark/table-rule）。
            'nav_style' => 'left-line', 'panel_style' => 'top-line',
        ]),
        // 薄荷账簿：翡翠强调 + 柔和投影
        'mint-ledger' => $theme('front.theme_mint_ledger', 'light', [
            // background：页面最底层背景色（HEX #RRGGBB）；与 text/border 的对比度受门禁约束。
            'background' => '#F4FAF7',
            // surface：卡片/面板表面色（HEX）；与 background 形成层次。
            'surface' => '#FFFFFF',
            // surface_alt：次级表面/隔行底色（HEX）。
            'surface_alt' => '#E5F3EC',
            // text：正文文本色（HEX）；对 background/surface 须 >= 4.5:1（WCAG AA）。
            'text' => '#22443A',
            // muted：次要文本色（HEX）；对 surface 须 >= 4.5:1。
            'muted' => '#4C6A5D',
            // heading：标题文本色（HEX）；通常为该主题最深色。
            'heading' => '#0D3629',
            // border：常规描边/分隔线色（HEX）。
            'border' => '#C9E1D5',
            // border_strong：强描边色（HEX）；对 surface/background/surface_alt 须 >= 3:1（非文本对比门禁）。
            'border_strong' => '#65907E',
            // accent：品牌强调色（HEX）；主题之间 RGB 距离须 >= 25，改值会影响所有选中/链接/按钮。
            'accent' => '#047857',
            // accent_hover：强调色悬停态（HEX）；须与 accent 同族且可区分。
            'accent_hover' => '#036147',
            // on_accent：强调色之上的前景文本色（HEX）；对 accent 须 >= 4.5:1。
            'on_accent' => '#FFFFFF',
            // focus：键盘焦点环颜色（HEX）。
            'focus' => '#047857',
            // sidebar：侧栏背景色（HEX）。
            'sidebar' => '#EEF8F3',
            // sidebar_hover：侧栏菜单悬停背景色（HEX）。
            'sidebar_hover' => '#D8ECE2',
            // sidebar_text：侧栏主文本色（HEX）；对 sidebar 须 >= 4.5:1。
            'sidebar_text' => '#1F4034',
            // sidebar_muted：侧栏次文本色（HEX）；对 sidebar 须 >= 4.5:1。
            'sidebar_muted' => '#4C6A5D',
        ], [
            // radius/radius_sm：面板与小组件圆角（CSS 长度，px）；sidebar_width：侧栏宽度（px），影响内容区栅格。
            'radius' => '8px', 'radius_sm' => '6px', 'sidebar_width' => '244px',
            // shadow：面板投影（CSS box-shadow 值，none=无投影）；table_row_height：表格行高（px），影响数据密度。
            'shadow' => '0 4px 16px rgba(13, 54, 41, .08)', 'table_row_height' => '44px',
            // nav_style：导航视觉形态（left-line/tab/fill/outline/compact/block/underline/marker/grid/sidebar-fill）；panel_style：面板视觉形态（top-line/topbar-line/soft-shadow/strong-border/none/panel-left/title-line/dark-sidebar/nav-mark/table-rule）。
            'nav_style' => 'fill', 'panel_style' => 'soft-shadow',
        ]),
        // 云白极简：纯中性灰阶 + 无投影直角
        'cloud-minimal' => $theme('front.theme_cloud_minimal', 'light', [
            // background：页面最底层背景色（HEX #RRGGBB）；与 text/border 的对比度受门禁约束。
            'background' => '#F6F7F8',
            // surface：卡片/面板表面色（HEX）；与 background 形成层次。
            'surface' => '#FFFFFF',
            // surface_alt：次级表面/隔行底色（HEX）。
            'surface_alt' => '#EBEEF1',
            // text：正文文本色（HEX）；对 background/surface 须 >= 4.5:1（WCAG AA）。
            'text' => '#2B323A',
            // muted：次要文本色（HEX）；对 surface 须 >= 4.5:1。
            'muted' => '#525B66',
            // heading：标题文本色（HEX）；通常为该主题最深色。
            'heading' => '#12161A',
            // border：常规描边/分隔线色（HEX）。
            'border' => '#D2D7DD',
            // border_strong：强描边色（HEX）；对 surface/background/surface_alt 须 >= 3:1（非文本对比门禁）。
            'border_strong' => '#727B86',
            // accent：品牌强调色（HEX）；主题之间 RGB 距离须 >= 25，改值会影响所有选中/链接/按钮。
            'accent' => '#3F4A54',
            // accent_hover：强调色悬停态（HEX）；须与 accent 同族且可区分。
            'accent_hover' => '#2E373F',
            // on_accent：强调色之上的前景文本色（HEX）；对 accent 须 >= 4.5:1。
            'on_accent' => '#FFFFFF',
            // focus：键盘焦点环颜色（HEX）。
            'focus' => '#3F4A54',
            // sidebar：侧栏背景色（HEX）。
            'sidebar' => '#F2F4F6',
            // sidebar_hover：侧栏菜单悬停背景色（HEX）。
            'sidebar_hover' => '#E1E5E9',
            // sidebar_text：侧栏主文本色（HEX）；对 sidebar 须 >= 4.5:1。
            'sidebar_text' => '#2B323A',
            // sidebar_muted：侧栏次文本色（HEX）；对 sidebar 须 >= 4.5:1。
            'sidebar_muted' => '#525B66',
        ], [
            // radius/radius_sm：面板与小组件圆角（CSS 长度，px）；sidebar_width：侧栏宽度（px），影响内容区栅格。
            'radius' => '2px', 'radius_sm' => '0px', 'sidebar_width' => '216px',
            // shadow：面板投影（CSS box-shadow 值，none=无投影）；table_row_height：表格行高（px），影响数据密度。
            'shadow' => 'none', 'table_row_height' => '40px',
            // nav_style：导航视觉形态（left-line/tab/fill/outline/compact/block/underline/marker/grid/sidebar-fill）；panel_style：面板视觉形态（top-line/topbar-line/soft-shadow/strong-border/none/panel-left/title-line/dark-sidebar/nav-mark/table-rule）。
            'nav_style' => 'compact', 'panel_style' => 'none',
        ]),
        // 海盐：青蓝强调 + 浅色侧栏页签
        'sea-salt' => $theme('front.theme_sea_salt', 'light', [
            // background：页面最底层背景色（HEX #RRGGBB）；与 text/border 的对比度受门禁约束。
            'background' => '#F1FAFB',
            // surface：卡片/面板表面色（HEX）；与 background 形成层次。
            'surface' => '#FFFFFF',
            // surface_alt：次级表面/隔行底色（HEX）。
            'surface_alt' => '#E1F3F6',
            // text：正文文本色（HEX）；对 background/surface 须 >= 4.5:1（WCAG AA）。
            'text' => '#1B4A51',
            // muted：次要文本色（HEX）；对 surface 须 >= 4.5:1。
            'muted' => '#476A70',
            // heading：标题文本色（HEX）；通常为该主题最深色。
            'heading' => '#08343A',
            // border：常规描边/分隔线色（HEX）。
            'border' => '#C4E1E6',
            // border_strong：强描边色（HEX）；对 surface/background/surface_alt 须 >= 3:1（非文本对比门禁）。
            'border_strong' => '#5B8A91',
            // accent：品牌强调色（HEX）；主题之间 RGB 距离须 >= 25，改值会影响所有选中/链接/按钮。
            'accent' => '#0E7490',
            // accent_hover：强调色悬停态（HEX）；须与 accent 同族且可区分。
            'accent_hover' => '#0B6178',
            // on_accent：强调色之上的前景文本色（HEX）；对 accent 须 >= 4.5:1。
            'on_accent' => '#FFFFFF',
            // focus：键盘焦点环颜色（HEX）。
            'focus' => '#0E7490',
            // sidebar：侧栏背景色（HEX）。
            'sidebar' => '#F3FAFB',
            // sidebar_hover：侧栏菜单悬停背景色（HEX）。
            'sidebar_hover' => '#DBEFF2',
            // sidebar_text：侧栏主文本色（HEX）；对 sidebar 须 >= 4.5:1。
            'sidebar_text' => '#1B4A51',
            // sidebar_muted：侧栏次文本色（HEX）；对 sidebar 须 >= 4.5:1。
            'sidebar_muted' => '#476A70',
        ], [
            // radius/radius_sm：面板与小组件圆角（CSS 长度，px）；sidebar_width：侧栏宽度（px），影响内容区栅格。
            'radius' => '8px', 'radius_sm' => '6px', 'sidebar_width' => '232px',
            // shadow：面板投影（CSS box-shadow 值，none=无投影）；table_row_height：表格行高（px），影响数据密度。
            'shadow' => '0 3px 12px rgba(8, 52, 58, .07)', 'table_row_height' => '42px',
            // nav_style：导航视觉形态（left-line/tab/fill/outline/compact/block/underline/marker/grid/sidebar-fill）；panel_style：面板视觉形态（top-line/topbar-line/soft-shadow/strong-border/none/panel-left/title-line/dark-sidebar/nav-mark/table-rule）。
            'nav_style' => 'tab', 'panel_style' => 'topbar-line',
        ]),
        // 靛青秩序：靛蓝强调 + 块状导航
        'indigo-order' => $theme('front.theme_indigo_order', 'light', [
            // background：页面最底层背景色（HEX #RRGGBB）；与 text/border 的对比度受门禁约束。
            'background' => '#F7F7FC',
            // surface：卡片/面板表面色（HEX）；与 background 形成层次。
            'surface' => '#FFFFFF',
            // surface_alt：次级表面/隔行底色（HEX）。
            'surface_alt' => '#EAEAF8',
            // text：正文文本色（HEX）；对 background/surface 须 >= 4.5:1（WCAG AA）。
            'text' => '#2F3350',
            // muted：次要文本色（HEX）；对 surface 须 >= 4.5:1。
            'muted' => '#575C7A',
            // heading：标题文本色（HEX）；通常为该主题最深色。
            'heading' => '#191D3C',
            // border：常规描边/分隔线色（HEX）。
            'border' => '#D3D4EC',
            // border_strong：强描边色（HEX）；对 surface/background/surface_alt 须 >= 3:1（非文本对比门禁）。
            'border_strong' => '#6F74A0',
            // accent：品牌强调色（HEX）；主题之间 RGB 距离须 >= 25，改值会影响所有选中/链接/按钮。
            'accent' => '#4338CA',
            // accent_hover：强调色悬停态（HEX）；须与 accent 同族且可区分。
            'accent_hover' => '#362CA6',
            // on_accent：强调色之上的前景文本色（HEX）；对 accent 须 >= 4.5:1。
            'on_accent' => '#FFFFFF',
            // focus：键盘焦点环颜色（HEX）。
            'focus' => '#4338CA',
            // sidebar：侧栏背景色（HEX）。
            'sidebar' => '#F5F5FB',
            // sidebar_hover：侧栏菜单悬停背景色（HEX）。
            'sidebar_hover' => '#E5E5F5',
            // sidebar_text：侧栏主文本色（HEX）；对 sidebar 须 >= 4.5:1。
            'sidebar_text' => '#2C3049',
            // sidebar_muted：侧栏次文本色（HEX）；对 sidebar 须 >= 4.5:1。
            'sidebar_muted' => '#575C7A',
        ], [
            // radius/radius_sm：面板与小组件圆角（CSS 长度，px）；sidebar_width：侧栏宽度（px），影响内容区栅格。
            'radius' => '6px', 'radius_sm' => '4px', 'sidebar_width' => '246px',
            // shadow：面板投影（CSS box-shadow 值，none=无投影）；table_row_height：表格行高（px），影响数据密度。
            'shadow' => '0 3px 14px rgba(25, 29, 60, .08)', 'table_row_height' => '44px',
            // nav_style：导航视觉形态（left-line/tab/fill/outline/compact/block/underline/marker/grid/sidebar-fill）；panel_style：面板视觉形态（top-line/topbar-line/soft-shadow/strong-border/none/panel-left/title-line/dark-sidebar/nav-mark/table-rule）。
            'nav_style' => 'block', 'panel_style' => 'panel-left',
        ]),
        // 珊瑚笔记：暖橙强调 + 下划线导航
        'coral-note' => $theme('front.theme_coral_note', 'light', [
            // background：页面最底层背景色（HEX #RRGGBB）；与 text/border 的对比度受门禁约束。
            'background' => '#FDF8F6',
            // surface：卡片/面板表面色（HEX）；与 background 形成层次。
            'surface' => '#FFFFFF',
            // surface_alt：次级表面/隔行底色（HEX）。
            'surface_alt' => '#F9EBE5',
            // text：正文文本色（HEX）；对 background/surface 须 >= 4.5:1（WCAG AA）。
            'text' => '#46332D',
            // muted：次要文本色（HEX）；对 surface 须 >= 4.5:1。
            'muted' => '#68544D',
            // heading：标题文本色（HEX）；通常为该主题最深色。
            'heading' => '#2E1D18',
            // border：常规描边/分隔线色（HEX）。
            'border' => '#E7D2C9',
            // border_strong：强描边色（HEX）；对 surface/background/surface_alt 须 >= 3:1（非文本对比门禁）。
            'border_strong' => '#96695E',
            // accent：品牌强调色（HEX）；主题之间 RGB 距离须 >= 25，改值会影响所有选中/链接/按钮。
            'accent' => '#C2410C',
            // accent_hover：强调色悬停态（HEX）；须与 accent 同族且可区分。
            'accent_hover' => '#9F3409',
            // on_accent：强调色之上的前景文本色（HEX）；对 accent 须 >= 4.5:1。
            'on_accent' => '#FFFFFF',
            // focus：键盘焦点环颜色（HEX）。
            'focus' => '#C2410C',
            // sidebar：侧栏背景色（HEX）。
            'sidebar' => '#FBF4F1',
            // sidebar_hover：侧栏菜单悬停背景色（HEX）。
            'sidebar_hover' => '#F3DED6',
            // sidebar_text：侧栏主文本色（HEX）；对 sidebar 须 >= 4.5:1。
            'sidebar_text' => '#45322C',
            // sidebar_muted：侧栏次文本色（HEX）；对 sidebar 须 >= 4.5:1。
            'sidebar_muted' => '#68544D',
        ], [
            // radius/radius_sm：面板与小组件圆角（CSS 长度，px）；sidebar_width：侧栏宽度（px），影响内容区栅格。
            'radius' => '8px', 'radius_sm' => '6px', 'sidebar_width' => '238px',
            // shadow：面板投影（CSS box-shadow 值，none=无投影）；table_row_height：表格行高（px），影响数据密度。
            'shadow' => '0 3px 14px rgba(46, 29, 24, .08)', 'table_row_height' => '42px',
            // nav_style：导航视觉形态（left-line/tab/fill/outline/compact/block/underline/marker/grid/sidebar-fill）；panel_style：面板视觉形态（top-line/topbar-line/soft-shadow/strong-border/none/panel-left/title-line/dark-sidebar/nav-mark/table-rule）。
            'nav_style' => 'underline', 'panel_style' => 'title-line',
        ]),
        // 青瓷运营：草绿强调 + 描边面板
        'celadon-ops' => $theme('front.theme_celadon_ops', 'light', [
            // background：页面最底层背景色（HEX #RRGGBB）；与 text/border 的对比度受门禁约束。
            'background' => '#F4F9F5',
            // surface：卡片/面板表面色（HEX）；与 background 形成层次。
            'surface' => '#FFFFFF',
            // surface_alt：次级表面/隔行底色（HEX）。
            'surface_alt' => '#E6F2E9',
            // text：正文文本色（HEX）；对 background/surface 须 >= 4.5:1（WCAG AA）。
            'text' => '#294038',
            // muted：次要文本色（HEX）；对 surface 须 >= 4.5:1。
            'muted' => '#4F6759',
            // heading：标题文本色（HEX）；通常为该主题最深色。
            'heading' => '#0F3020',
            // border：常规描边/分隔线色（HEX）。
            'border' => '#CBE0D1',
            // border_strong：强描边色（HEX）；对 surface/background/surface_alt 须 >= 3:1（非文本对比门禁）。
            'border_strong' => '#668572',
            // accent：品牌强调色（HEX）；主题之间 RGB 距离须 >= 25，改值会影响所有选中/链接/按钮。
            'accent' => '#15803D',
            // accent_hover：强调色悬停态（HEX）；须与 accent 同族且可区分。
            'accent_hover' => '#116631',
            // on_accent：强调色之上的前景文本色（HEX）；对 accent 须 >= 4.5:1。
            'on_accent' => '#FFFFFF',
            // focus：键盘焦点环颜色（HEX）。
            'focus' => '#15803D',
            // sidebar：侧栏背景色（HEX）。
            'sidebar' => '#EDF6F0',
            // sidebar_hover：侧栏菜单悬停背景色（HEX）。
            'sidebar_hover' => '#DAEBDF',
            // sidebar_text：侧栏主文本色（HEX）；对 sidebar 须 >= 4.5:1。
            'sidebar_text' => '#264036',
            // sidebar_muted：侧栏次文本色（HEX）；对 sidebar 须 >= 4.5:1。
            'sidebar_muted' => '#4F6759',
        ], [
            // radius/radius_sm：面板与小组件圆角（CSS 长度，px）；sidebar_width：侧栏宽度（px），影响内容区栅格。
            'radius' => '4px', 'radius_sm' => '2px', 'sidebar_width' => '252px',
            // shadow：面板投影（CSS box-shadow 值，none=无投影）；table_row_height：表格行高（px），影响数据密度。
            'shadow' => '0 1px 8px rgba(15, 48, 32, .06)', 'table_row_height' => '42px',
            // nav_style：导航视觉形态（left-line/tab/fill/outline/compact/block/underline/marker/grid/sidebar-fill）；panel_style：面板视觉形态（top-line/topbar-line/soft-shadow/strong-border/none/panel-left/title-line/dark-sidebar/nav-mark/table-rule）。
            'nav_style' => 'outline', 'panel_style' => 'strong-border',
        ]),
        // 日光标记：琥珀强调 + 标记式导航
        'sunlit-mark' => $theme('front.theme_sunlit_mark', 'light', [
            // background：页面最底层背景色（HEX #RRGGBB）；与 text/border 的对比度受门禁约束。
            'background' => '#FCFAF4',
            // surface：卡片/面板表面色（HEX）；与 background 形成层次。
            'surface' => '#FFFFFF',
            // surface_alt：次级表面/隔行底色（HEX）。
            'surface_alt' => '#F4EFE0',
            // text：正文文本色（HEX）；对 background/surface 须 >= 4.5:1（WCAG AA）。
            'text' => '#403A2C',
            // muted：次要文本色（HEX）；对 surface 须 >= 4.5:1。
            'muted' => '#5F594B',
            // heading：标题文本色（HEX）；通常为该主题最深色。
            'heading' => '#2A251A',
            // border：常规描边/分隔线色（HEX）。
            'border' => '#E3D9C1',
            // border_strong：强描边色（HEX）；对 surface/background/surface_alt 须 >= 3:1（非文本对比门禁）。
            'border_strong' => '#847749',
            // accent：品牌强调色（HEX）；主题之间 RGB 距离须 >= 25，改值会影响所有选中/链接/按钮。
            'accent' => '#92400E',
            // accent_hover：强调色悬停态（HEX）；须与 accent 同族且可区分。
            'accent_hover' => '#77330B',
            // on_accent：强调色之上的前景文本色（HEX）；对 accent 须 >= 4.5:1。
            'on_accent' => '#FFFFFF',
            // focus：键盘焦点环颜色（HEX）。
            'focus' => '#92400E',
            // sidebar：侧栏背景色（HEX）。
            'sidebar' => '#F8F5EB',
            // sidebar_hover：侧栏菜单悬停背景色（HEX）。
            'sidebar_hover' => '#EDE6D3',
            // sidebar_text：侧栏主文本色（HEX）；对 sidebar 须 >= 4.5:1。
            'sidebar_text' => '#3F392B',
            // sidebar_muted：侧栏次文本色（HEX）；对 sidebar 须 >= 4.5:1。
            'sidebar_muted' => '#5F594B',
        ], [
            // radius/radius_sm：面板与小组件圆角（CSS 长度，px）；sidebar_width：侧栏宽度（px），影响内容区栅格。
            'radius' => '4px', 'radius_sm' => '2px', 'sidebar_width' => '228px',
            // shadow：面板投影（CSS box-shadow 值，none=无投影）；table_row_height：表格行高（px），影响数据密度。
            'shadow' => '0 2px 10px rgba(42, 37, 26, .07)', 'table_row_height' => '40px',
            // nav_style：导航视觉形态（left-line/tab/fill/outline/compact/block/underline/marker/grid/sidebar-fill）；panel_style：面板视觉形态（top-line/topbar-line/soft-shadow/strong-border/none/panel-left/title-line/dark-sidebar/nav-mark/table-rule）。
            'nav_style' => 'marker', 'panel_style' => 'nav-mark',
        ]),
        // 钢构表格：钢蓝强调 + 直角网格，密集行高
        'steel-table' => $theme('front.theme_steel_table', 'light', [
            // background：页面最底层背景色（HEX #RRGGBB）；与 text/border 的对比度受门禁约束。
            'background' => '#F3F7FA',
            // surface：卡片/面板表面色（HEX）；与 background 形成层次。
            'surface' => '#FFFFFF',
            // surface_alt：次级表面/隔行底色（HEX）。
            'surface_alt' => '#E5EDF3',
            // text：正文文本色（HEX）；对 background/surface 须 >= 4.5:1（WCAG AA）。
            'text' => '#22364A',
            // muted：次要文本色（HEX）；对 surface 须 >= 4.5:1。
            'muted' => '#4C6070',
            // heading：标题文本色（HEX）；通常为该主题最深色。
            'heading' => '#0D2333',
            // border：常规描边/分隔线色（HEX）。
            'border' => '#C6D5E0',
            // border_strong：强描边色（HEX）；对 surface/background/surface_alt 须 >= 3:1（非文本对比门禁）。
            'border_strong' => '#5F7A8D',
            // accent：品牌强调色（HEX）；主题之间 RGB 距离须 >= 25，改值会影响所有选中/链接/按钮。
            'accent' => '#1F5D7A',
            // accent_hover：强调色悬停态（HEX）；须与 accent 同族且可区分。
            'accent_hover' => '#184A62',
            // on_accent：强调色之上的前景文本色（HEX）；对 accent 须 >= 4.5:1。
            'on_accent' => '#FFFFFF',
            // focus：键盘焦点环颜色（HEX）。
            'focus' => '#1F5D7A',
            // sidebar：侧栏背景色（HEX）。
            'sidebar' => '#ECF2F7',
            // sidebar_hover：侧栏菜单悬停背景色（HEX）。
            'sidebar_hover' => '#D9E4EC',
            // sidebar_text：侧栏主文本色（HEX）；对 sidebar 须 >= 4.5:1。
            'sidebar_text' => '#22364A',
            // sidebar_muted：侧栏次文本色（HEX）；对 sidebar 须 >= 4.5:1。
            'sidebar_muted' => '#4C6070',
        ], [
            // radius/radius_sm：面板与小组件圆角（CSS 长度，px）；sidebar_width：侧栏宽度（px），影响内容区栅格。
            'radius' => '0px', 'radius_sm' => '0px', 'sidebar_width' => '224px',
            // shadow：面板投影（CSS box-shadow 值，none=无投影）；table_row_height：表格行高（px），影响数据密度。
            'shadow' => 'none', 'table_row_height' => '38px',
            // nav_style：导航视觉形态（left-line/tab/fill/outline/compact/block/underline/marker/grid/sidebar-fill）；panel_style：面板视觉形态（top-line/topbar-line/soft-shadow/strong-border/none/panel-left/title-line/dark-sidebar/nav-mark/table-rule）。
            'nav_style' => 'grid', 'panel_style' => 'table-rule',
        ]),
        // 墨紫侧栏：紫罗兰强调 + 深墨侧栏
        'ink-sidebar' => $theme('front.theme_ink_sidebar', 'light', [
            // background：页面最底层背景色（HEX #RRGGBB）；与 text/border 的对比度受门禁约束。
            'background' => '#F8F7FB',
            // surface：卡片/面板表面色（HEX）；与 background 形成层次。
            'surface' => '#FFFFFF',
            // surface_alt：次级表面/隔行底色（HEX）。
            'surface_alt' => '#EEEBF6',
            // text：正文文本色（HEX）；对 background/surface 须 >= 4.5:1（WCAG AA）。
            'text' => '#363044',
            // muted：次要文本色（HEX）；对 surface 须 >= 4.5:1。
            'muted' => '#5D5670',
            // heading：标题文本色（HEX）；通常为该主题最深色。
            'heading' => '#1F1A2C',
            // border：常规描边/分隔线色（HEX）。
            'border' => '#DBD5E8',
            // border_strong：强描边色（HEX）；对 surface/background/surface_alt 须 >= 3:1（非文本对比门禁）。
            'border_strong' => '#786D8C',
            // accent：品牌强调色（HEX）；主题之间 RGB 距离须 >= 25，改值会影响所有选中/链接/按钮。
            'accent' => '#6D28D9',
            // accent_hover：强调色悬停态（HEX）；须与 accent 同族且可区分。
            'accent_hover' => '#5A20B4',
            // on_accent：强调色之上的前景文本色（HEX）；对 accent 须 >= 4.5:1。
            'on_accent' => '#FFFFFF',
            // focus：键盘焦点环颜色（HEX）。
            'focus' => '#6D28D9',
            // sidebar：侧栏背景色（HEX）。
            'sidebar' => '#1E1A2B',
            // sidebar_hover：侧栏菜单悬停背景色（HEX）。
            'sidebar_hover' => '#332B47',
            // sidebar_text：侧栏主文本色（HEX）；对 sidebar 须 >= 4.5:1。
            'sidebar_text' => '#F7F5FC',
            // sidebar_muted：侧栏次文本色（HEX）；对 sidebar 须 >= 4.5:1。
            'sidebar_muted' => '#C5BCD6',
        ], [
            // radius/radius_sm：面板与小组件圆角（CSS 长度，px）；sidebar_width：侧栏宽度（px），影响内容区栅格。
            'radius' => '6px', 'radius_sm' => '4px', 'sidebar_width' => '240px',
            // shadow：面板投影（CSS box-shadow 值，none=无投影）；table_row_height：表格行高（px），影响数据密度。
            'shadow' => '0 3px 14px rgba(31, 26, 44, .09)', 'table_row_height' => '42px',
            // nav_style：导航视觉形态（left-line/tab/fill/outline/compact/block/underline/marker/grid/sidebar-fill）；panel_style：面板视觉形态（top-line/topbar-line/soft-shadow/strong-border/none/panel-left/title-line/dark-sidebar/nav-mark/table-rule）。
            'nav_style' => 'sidebar-fill', 'panel_style' => 'dark-sidebar',
        ]),
    ],
];
