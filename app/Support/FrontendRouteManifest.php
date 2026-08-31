<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/08
 * Time: 00:41
 */

namespace App\Support;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * 前端命名页面路由清单生成器。
 *
 * 文件功能：
 * - 从 Laravel 已注册路由中筛选 Blade 页面和旧项目页面兼容别名。
 * - 将路由参数保留为占位符，供项目自定义 JavaScript 在浏览器中安全替换。
 * - 排除后端 API 与仅用于服务器重定向的历史客户端入口，避免浏览器形成第二套路由来源。
 *
 * 返回值：
 * - `make()` 返回以路由名为键的页面路由数组。
 * - `json()` 返回可直接注入 Blade 的 JSON 字符串。
 */
class FrontendRouteManifest
{
    /**
     * 可导出的命名路由前缀，只覆盖当前 Blade 页面和旧项目页面兼容入口。
     */
    private const ROUTE_NAME_PREFIXES = [
        'legacy_',
        'front_page_',
        'admin_page_',
        'front_crmui_',
        'front_naive_',
        'admin_crmui_',
    ];

    /**
     * web.php 中部分旧项目别名不是 legacy_ 前缀，只能根据 URI 判断是否属于前端可导出范围。
     */
    private const ROUTE_URI_PREFIXES = [
        'front',
        'admin',
        'front-crmui',
        'front-naive',
        'admin-crmui',
        'user',
        'agents',
        'en/user',
        'show/user_detail',
        'open/order_detail',
        'close/order_detail',
    ];

    /**
     * 从 Laravel 已注册的命名路由表导出前端可用清单。
     *
     * 注意：旧项目兼容路由里有通过 RouteCollection nameList 追加的别名，
     * 所以这里必须读取 getRoutesByName()，不能只遍历 getRoutes()。
     */
    public static function make(): array
    {
        $manifest = [];
        $routes = RouteFacade::getRoutes()->getRoutesByName();

        foreach ($routes as $name => $route) {
            if (! $route instanceof Route || ! self::shouldExport($name, $route)) {
                continue;
            }

            $manifest[$name] = [
                'name' => $name,
                'uri' => self::normalizeUri($route->uri()),
                'methods' => self::normalizeMethods($route->methods()),
            ];
        }

        // 按键名排序输出，保证多次生成与多进程环境下清单内容稳定（利于对比与缓存）。
        ksort($manifest);

        return $manifest;
    }

    /**
     * Blade 内联 JSON 时使用，统一 JSON 编码参数，避免每个视图重复处理。
     */
    public static function json(): string
    {
        return json_encode(self::make(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * 判断命名路由是否属于浏览器可调用的页面路由。
     *
     * @param string $name Laravel 路由名称，必传。
     * @param Route $route 当前已注册路由对象，必传。
     * @return bool 页面路由返回 true，API、调试及历史客户端重定向路由返回 false。
     */
    private static function shouldExport(string $name, Route $route): bool
    {
        if ($name === '' || strpos($name, '_debugbar') === 0) {
            return false;
        }

        foreach (self::ROUTE_NAME_PREFIXES as $prefix) {
            if (strpos($name, $prefix) === 0) {
                return true;
            }
        }

        $uri = trim($route->uri(), '/');
        foreach (self::ROUTE_URI_PREFIXES as $prefix) {
            if ($uri === $prefix || strpos($uri, $prefix . '/') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * 将 Laravel URI 规范化为浏览器使用的绝对路径模板。
     *
     * @param string $uri Laravel 路由 URI，例如 `front/register/{inviter_id?}`。
     * @return string 去除可选参数问号并补全前导斜杠后的路径。
     */
    private static function normalizeUri(string $uri): string
    {
        $uri = trim($uri, '/');
        $uri = preg_replace('/\{([^}]+)\?\}/', '{$1}', $uri);

        return $uri === '' ? '/' : '/' . $uri;
    }

    /**
     * 规范化页面路由允许的 HTTP 方法。
     *
     * @param array<int, string> $methods Laravel 路由方法列表。
     * @return array<int, string> 移除隐式 HEAD 并按字母排序后的方法列表。
     */
    private static function normalizeMethods(array $methods): array
    {
        $methods = array_values(array_diff($methods, ['HEAD']));
        sort($methods);

        return $methods;
    }
}
