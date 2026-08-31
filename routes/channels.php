<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:26
 */

/**
 * 广播频道路由文件。
 *
 * 文件功能：
 * - 注册应用支持的事件广播频道（Broadcast Channel）。
 * - 每个频道的授权回调用于校验当前认证用户是否有权监听该频道。
 *
 * 路由分组：
 * - 当前仅保留框架默认的 App.Models.User.{id} 私有频道。
 * - 项目业务未使用实时推送时，本文件保持最小实现即可。
 *
 * 适用场景：
 * - 需要为指定用户 ID 的私有频道做监听授权时使用。
 */

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
