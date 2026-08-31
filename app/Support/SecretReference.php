<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 08:53
 */

/**
 * 密钥引用格式校验工具。
 *
 * 文件功能：
 * - 校验支付渠道等配置中“密钥引用”字符串的合法性，支持两种格式：
 *   - env: 环境变量引用，如 env:EXLINK_SECRET（变量名 3~128 位大写字母/数字/下划线，以字母开头）。
 *   - vault: 密钥库引用，如 vault:pay/exlink#callback_key（路径 1~200 位，可选 # 后 1~64 位键名，禁止 .. 与 //）。
 *
 * 适用场景：
 * - PaymentGatewayRegistry 及各支付网关适配器解析 secret_reference / key_reference / callback_key_reference 前的统一校验。
 *
 * 入参例子：
 * - isValid('env:EXLINK_SECRET') -> true
 * - isValid('vault:pay/exlink#key') -> true
 * - isValid('plain-secret') -> false
 *
 * 返回值：
 * - bool 引用格式合法返回 true，否则 false（空值、超长、非法字符、含 .. 或 // 均返回 false）。
 *
 * 异常或失败场景：
 * - 本工具只做格式校验，不解析实际密钥值，也不抛异常。
 */

declare(strict_types=1);

namespace App\Support;

final class SecretReference
{
    /**
     * 校验密钥引用字符串格式。
     *
     * 只做格式校验，不解析、不读取实际密钥值，也不抛异常。
     *
     * @param string $reference 待校验的引用字符串，支持 env: 与 vault: 两种格式。
     * @return bool 格式合法返回 true；空值、超长、非法字符、含 .. 或 // 均返回 false。
     */
    public static function isValid(string $reference): bool
    {
        $reference = trim($reference);
        if ($reference === '' || strlen($reference) > 255) {
            // 空引用与超长引用直接拒绝，避免把任意长字符串当作合法密钥引用。
            return false;
        }
        // env: 引用必须是标准环境变量名（大写字母开头，3~128 位），防止引用任意配置段。
        if (preg_match('/^env:[A-Z][A-Z0-9_]{2,127}$/', $reference)) {
            return true;
        }

        // vault: 引用限制路径字符与长度，并禁止 .. 与 // 防止路径穿越越界到无关目录。
        return preg_match('/^vault:[A-Za-z0-9._\/-]{1,200}(?:#[A-Za-z0-9._-]{1,64})?$/', $reference) === 1
            && strpos($reference, '..') === false
            && strpos($reference, '//') === false;
    }
}
