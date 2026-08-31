<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/13
 * Time: 19:10
 */

/**
 * API 路由文件（Laravel 默认 API 入口）。
 *
 * 文件功能：
 * - 承载挂载在 api 中间件组下的 API 路由。
 * - 当前仅保留框架默认的 /api/user 示例接口，用于返回当前登录用户信息。
 *
 * 路由分组：
 * - 前台业务接口实际位于 routes/front.php（前缀 api/front）。
 * - 后台业务接口实际位于 routes/admin.php（前缀 api/admin）。
 * - 代理商业务接口位于 routes/agent.php（内部声明 api/front、api/admin 分组）。
 *
 * 适用场景：
 * - 保留框架默认入口，避免删除后影响依赖 /api/user 的既有调用。
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
