<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:29
 */

/**
 * 后台权限名称参考文档生成器。
 *
 * 脚本用途：
 * - 从 permissions 表读取全部后台权限，重新生成 docs/admin-permission-name-reference.md
 *   的“后台权限总览 / 权限明细”章节（保留文档头部与维护规则章节）。
 * - 权限名称按 GBK 编码修复乱码，类型映射为 菜单/页面/按钮接口 权限。
 *
 * 运行方式：
 * - php scripts/generate-admin-permission-name-reference.php（需数据库可用）。
 * - 权限 slug 为空或文档章节缺失时抛出 RuntimeException 并终止。
 */

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$path = base_path('docs/admin-permission-name-reference.md');
$current = (string) file_get_contents($path);
$summaryOffset = strpos($current, '## 后台权限总览');
$maintenanceOffset = strpos($current, '## 维护规则');
if ($summaryOffset === false || $maintenanceOffset === false || $maintenanceOffset <= $summaryOffset) {
    throw new RuntimeException('Permission reference document sections could not be located.');
}

$permissions = DB::table('permissions')
    ->where('guard_type', 'admin')
    ->orderBy('id')
    ->get(['id', 'parent_id', 'type', 'name', 'slug', 'api_route', 'route', 'status']);

$enabled = $permissions->where('status', 1)->count();
$disabled = $permissions->count() - $enabled;
$lines = [
    '## 后台权限总览',
    '',
    '- 后台权限总数：`' . $permissions->count() . '`。',
    '- 启用权限数量：`' . $enabled . '`。',
    '- 停用权限数量：`' . $disabled . '`。',
    '',
    '## 权限明细',
    '',
    '| ID | 父级ID | 类型 | 权限名称 | 权限字符串 | 接口路由字符串 | 页面路由 | 状态 | 功能作用 |',
    '| --- | --- | --- | --- | --- | --- | --- | --- | --- |',
];

foreach ($permissions as $permission) {
    $name = readablePermissionName((string) $permission->name);
    $slug = trim((string) $permission->slug);
    if ($slug === '') {
        throw new RuntimeException('Admin permission ' . $permission->id . ' has an empty slug.');
    }

    $lines[] = '| ' . (int) $permission->id
        . ' | ' . (int) $permission->parent_id
        . ' | ' . permissionType((int) $permission->type)
        . ' | ' . markdownCell($name)
        . ' | `' . markdownInline($slug) . '`'
        . ' | ' . routeCell((string) $permission->api_route)
        . ' | ' . routeCell((string) $permission->route)
        . ' | ' . ((int) $permission->status === 1 ? '启用' : '停用')
        . ' | ' . markdownCell(permissionEffect($name, (int) $permission->type, (string) $permission->api_route, (string) $permission->route))
        . ' |';
}

$prefix = rtrim(substr($current, 0, $summaryOffset));
$maintenance = ltrim(substr($current, $maintenanceOffset));
$report = $prefix . PHP_EOL . PHP_EOL . implode(PHP_EOL, $lines) . PHP_EOL . PHP_EOL . $maintenance;
if (file_put_contents($path, $report) !== strlen($report)) {
    throw new RuntimeException('Unable to write the admin permission reference document.');
}

echo $path . PHP_EOL;
echo 'permissions=' . $permissions->count() . PHP_EOL;
echo 'enabled=' . $enabled . PHP_EOL;
echo 'disabled=' . $disabled . PHP_EOL;

function readablePermissionName(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '未命名权限';
    }

    $converted = @iconv('UTF-8', 'GBK//IGNORE', $name);
    if ($converted !== false && mb_check_encoding($converted, 'UTF-8') && preg_match('/\p{Han}/u', $converted)) {
        return $converted;
    }

    return $name;
}

function permissionType(int $type): string
{
    if ($type === 1) {
        return '菜单权限';
    }
    if ($type === 2) {
        return '页面权限';
    }

    return '按钮/接口权限';
}

function permissionEffect(string $name, int $type, string $apiRoute, string $pageRoute): string
{
    if ($type === 1) {
        $effect = '控制后台【' . $name . '】菜单分组或一级入口是否在后台侧边栏显示；角色未授权该权限字符串时，普通管理员不能看到对应菜单入口。';
    } elseif ($type === 2) {
        $effect = '控制后台【' . $name . '】页面入口与页面数据访问；角色授权决定页面是否可见，接口仍需独立执行身份、权限和数据范围鉴权。';
    } else {
        $effect = '控制后台【' . $name . '】按钮、表格动作或 API 接口操作；前端按权限字符串控制按钮显隐，后端按接口路由字符串执行二次鉴权。';
    }

    if (trim($apiRoute) === '') {
        $effect .= ' 当前未绑定独立接口路由字符串，表示该权限只控制菜单、页面容器或前端动作。';
    }
    if (trim($pageRoute) === '') {
        $effect .= ' 当前未绑定页面路由，表示该权限主要用于接口动作或按钮授权。';
    }

    return $effect;
}

function routeCell(string $value): string
{
    $value = trim($value);

    return $value === '' ? '-' : '`' . markdownInline($value) . '`';
}

function markdownCell(string $value): string
{
    return str_replace(['|', "\r", "\n"], ['\\|', ' ', ' '], trim($value));
}

function markdownInline(string $value): string
{
    return str_replace('`', '\\`', trim($value));
}
