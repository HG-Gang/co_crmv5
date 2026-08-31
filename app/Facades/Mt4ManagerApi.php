<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 01:25
 */

/**
 * MT4 管理器门面（Facade）。
 *
 * 文件功能：
 * - 提供对 Mt4ManagerService 的静态访问门面，隐藏服务容器解析细节，
 *   业务代码可通过 Mt4ManagerApi::method() 直接调用 MT4 管理器能力。
 *
 * 适用场景：
 * - 任意需要操作 MT4 交易服务器的服务中，例如修改用户交易密码：
 *   Mt4ManagerApi::changePassword((int) $login->user_id, $newPassword)。
 *
 * 入参例子：
 * - Mt4ManagerApi::changePassword(10001, 'new-password');
 *
 * 返回值：
 * - 取决于所调用的 Mt4ManagerService 方法（数组/对象/布尔等），
 *   与底层方法返回值一致。
 *
 * 异常或失败场景：
 * - 门面本身不处理异常，由底层 Mt4ManagerService 方法决定是否抛出。
 */
namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class Mt4ManagerApi extends Facade
{
    /**
     * 返回门面绑定的服务容器键名。
     *
     * @return string 服务容器键 'mt4.manager'（由 Mt4ServiceProvider 注册并
     *         别名到 Mt4ManagerService）。
     */
    protected static function getFacadeAccessor()
    {
        return 'mt4.manager';
    }
}
