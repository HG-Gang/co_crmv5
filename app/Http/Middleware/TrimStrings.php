<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 08:52
 */

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as Middleware;

/**
 * 请求字符串自动去除首尾空白中间件。
 *
 * 文件功能：
 * - 继承 Laravel 默认的 TrimStrings 中间件，自动去除所有输入字符串的首尾空白字符。
 * - 排除密码类字段（current_password、password、password_confirmation）不做 trim 处理。
 *
 * 适用场景：
 * - 全局 Web 路由组中使用，规范化用户输入数据，避免因多余空白导致的验证问题。
 *
 * 入参例子：
 * - 对所有请求输入（包括 URL 查询参数和 POST 表单字段）进行 trim 处理。
 *
 * 返回值：
 * - 通过时继续请求链（输入数据已被 trim）。
 * - 不通过时始终通过（该中间件不拦截请求）。
 */
class TrimStrings extends Middleware
{
    /**
     * 不做 trim 处理的字段名列表（密码类字段保持原始输入，避免空白被错误规整）。
     *
     * @var array<int, string>
     */
    protected $except = [
        'current_password',
        'password',
        'password_confirmation',
    ];
}
