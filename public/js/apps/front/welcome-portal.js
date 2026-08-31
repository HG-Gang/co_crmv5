// Created by PhpStorm.
// Project name co_crmv5.
// User: Huang Gang
// Date: 2026/08/01
// Time: 22:32
/**
 * 品牌门户页（welcome）交互脚本。
 *
 * 文件功能：
 * - 门户页主题切换按钮：点击后在浅色/深色之间切换（color-scheme 由 CSS 变量接管）。
 * - 无内联脚本需求，统一从 public/js 加载，符合“Blade 不写内联可执行 JS”的项目约束。
 *
 * 适用场景：
 * - resources/views/welcome.blade.php 单独引用。
 *
 * 入参例子：
 * - 页面存在 [data-crm-theme-toggle] 按钮时绑定点击事件。
 *
 * 返回值：
 * - 无。副作用：切换 documentElement.style.colorScheme。
 *
 * 异常或失败场景：
 * - 按钮不存在时静默跳过（兜底页内未渲染入口时仍可正常展示）。
 */
(function (document) {
    'use strict';

    var toggle = document.querySelector('[data-crm-theme-toggle]');
    if (!toggle) {
        return;
    }

    toggle.addEventListener('click', function () {
        var dark = document.documentElement.style.colorScheme === 'dark';
        document.documentElement.style.colorScheme = dark ? 'light' : 'dark';
    });
}(document));
