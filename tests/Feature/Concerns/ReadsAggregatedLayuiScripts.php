<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:50
 */

/**
 * 读取聚合 Layui 页面脚本的 trait。
 *
 * 文件功能：
 * - 提供读取 admin/front 两个区域的 Layui 单页脚本或聚合 pages.js 中对应页面注册片段的能力。
 * - 页面脚本不存在独立文件时，从聚合脚本中截取 registry['page'] 注册块。
 *
 * 适用场景：
 * - 需要断言 Layui 页面 JS 内容（字段、事件、列配置）的功能测试。
 *
 * 入参例子：
 * - $this->adminLayuiScript('users/index.js')。
 * - $this->frontLayuiScript('withdraw-flows/index.js')。
 *
 * 返回值：
 * - 返回脚本源码字符串；文件或注册片段不存在时返回空字符串。
 *
 * 异常或失败场景：
 * - 不抛异常；找不到内容时返回空串，由调用方断言。
 */

namespace Tests\Feature\Concerns;

trait ReadsAggregatedLayuiScripts
{
    private function adminLayuiScript(string $relativePath): string
    {
        return $this->layuiScript('admin', $relativePath);
    }

    private function frontLayuiScript(string $relativePath): string
    {
        return $this->layuiScript('front', $relativePath);
    }

    private function layuiScript(string $area, string $relativePath): string
    {
        $normalized = str_replace('\\', '/', $relativePath);
        $path = public_path('js/apps/' . $area . '/layui/' . $normalized);

        if (is_file($path)) {
            return file_get_contents($path) ?: '';
        }

        if (preg_match('#^(.+)\.js$#', $normalized, $matches)) {
            return $this->aggregatedLayuiPageScript($area, $matches[1]);
        }

        return '';
    }

    private function aggregatedLayuiPageScript(string $area, string $page): string
    {
        $aggregatePath = public_path('js/apps/' . $area . '/layui/pages.js');
        $source = is_file($aggregatePath) ? (file_get_contents($aggregatePath) ?: '') : '';
        $needle = "registry['" . str_replace("'", "\\'", $page) . "'] = once(function () {";
        $start = strpos($source, $needle);

        if ($start === false) {
            return '';
        }

        $next = strpos($source, "\n    registry['", $start + strlen($needle));
        $exports = strpos($source, "\n    exports(", $start + strlen($needle));
        $end = $next === false ? $exports : $next;

        if ($end === false) {
            return substr($source, $start);
        }

        return substr($source, $start, $end - $start);
    }
}
