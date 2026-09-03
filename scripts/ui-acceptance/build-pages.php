<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/09/01
 * Time: 02:10
 */

/**
 * 四套 UI 家族的验收页面清单生成器。
 *
 * 文件功能：
 * - 从 Laravel 路由表取出 layui 两侧（/admin/*、/front/*）的全部无参 GET 页面。
 * - 通过反射取出 crmui 两侧 PageController::pages() 的真实页面键，拼成 SPA 路径。
 * - 合并去重后写成 JSON，供 scripts/ui-acceptance/audit.js 的 --pages 使用。
 *
 * 为什么必须由代码派生而不是手写清单：
 * - 手写清单会随着新增页面静默漂移，漏掉的页面在报告里表现为「没有缺陷」，
 *   而不是「没有验收」——这正是最危险的假绿。
 * - crmui 两侧是 {path?} 兜底路由，路由表里根本看不到具体页面，
 *   唯一权威来源就是 PageController::pages() 的键。
 *
 * 边界：
 * - 只收无参路径。带 {} 占位符的路由需要真实业务 ID，缺少可靠取值来源，
 *   在此不猜；这类页面若要验收，须另行提供固定夹具 ID。
 * - 登出类路径（logout）会销毁会话，纳入矩阵会让后续组合全部掉登录态，故排除。
 *
 * 用法：
 *   php scripts/ui-acceptance/build-pages.php > scripts/ui-acceptance/pages-all.json
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

/**
 * 取出 layui 两侧的无参 GET 页面路径。
 *
 * @return array<int, string> 形如 /admin/withdrawals 的路径列表。
 */
function layuiPages(): array
{
    $found = [];

    foreach (app('router')->getRoutes() as $route) {
        if (!in_array('GET', $route->methods(), true)) {
            continue;
        }

        $uri = $route->uri();
        // 带占位符的路由需要真实 ID，不猜；logout 会销毁登录态，排除。
        if (strpos($uri, '{') !== false || substr($uri, -6) === 'logout') {
            continue;
        }
        if (!preg_match('#^(admin|front)(/|$)#', $uri)) {
            continue;
        }

        $found[] = '/' . $uri;
    }

    return $found;
}

/**
 * 反射取出一侧 crmui 的页面键并拼成 SPA 路径。
 *
 * pages() 是 private：它是控制器内部实现，本不打算对外暴露。
 * 这里用反射读取而不是把它改成 public——为了生成清单去放宽生产代码的封装，
 * 是让测试工具反向污染业务代码，代价比反射大。
 *
 * @param string $controller 控制器类名。
 * @param string $prefix SPA 路由前缀，如 admin-crmui。
 * @return array<int, string>
 */
function crmuiPages(string $controller, string $prefix): array
{
    $method = new ReflectionMethod($controller, 'pages');
    $method->setAccessible(true);

    $paths = [];
    foreach (array_keys($method->invoke(app($controller))) as $key) {
        $paths[] = '/' . $prefix . '/' . $key;
    }

    return $paths;
}

$pages = array_merge(
    layuiPages(),
    crmuiPages(App\Http\Controllers\CrmUi\Admin\PageController::class, 'admin-crmui'),
    crmuiPages(App\Http\Controllers\CrmUi\Front\PageController::class, 'front-crmui')
);

$pages = array_values(array_unique($pages));
sort($pages);

fwrite(STDERR, 'pages=' . count($pages) . PHP_EOL);
echo json_encode($pages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
