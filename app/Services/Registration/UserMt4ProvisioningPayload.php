<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 08:59
 */

/**
 * MT4 开户负载加密与解密工具。
 *
 * 文件功能：
 * - 对 MT4 开户的用户数据加密（AES）+ 完整性哈希（HMAC-SHA256），并支持解密与完整性校验。
 *
 * 适用场景：
 * - 用户开户流程中，敏感数据需要加密传输或持久化存储，解密时需校验数据未被篡改。
 *
 * 入参例子：
 * - encrypt(["user_id" => 12345, "group" => "default"]) -> ["ciphertext" => "...", "hash" => "..."]
 * - decrypt($ciphertext, $expectedHash) -> ["user_id" => 12345, ...]
 *
 * 返回值：
 * - encrypt() 返回数组 ["ciphertext" => string, "hash" => string]。
 * - decrypt() 返回解密后的原始负载数组。
 *
 * 安全边界：
 * - 密文使用 Laravel Crypt（AES-256-CBC + app.key），完整性由 HMAC-SHA256 独立保障，密钥不落注释与日志。
 * - 解密必须经过完整性校验（hash_equals 常量时间比对），不匹配即抛异常失败关闭，绝不返回被篡改的数据。
 * - app.key 缺失时加密/解密都拒绝执行，不静默降级。
 *
 * 异常或失败场景：
 * - JSON 编码失败或解密失败时抛出 RuntimeException。
 * - 密文或哈希为空时抛出 RuntimeException。
 * - 哈希校验不匹配时抛出 RuntimeException("hash mismatch")。
 * - app.key 配置缺失时抛出 RuntimeException。
 */

declare(strict_types=1);

namespace App\Services\Registration;

use Illuminate\Support\Facades\Crypt;
use JsonException;
use RuntimeException;

final class UserMt4ProvisioningPayload
{
    /**
     * 加密开户负载：JSON 序列化后做 AES 加密，并附加 HMAC-SHA256 完整性哈希。
     *
     * @param array<string, mixed> $payload 开户负载（含 password 等敏感字段）。
     * @return array{ciphertext: string, hash: string} 密文与哈希，供出箱表持久化。
     * @throws RuntimeException JSON 序列化失败或 app.key 缺失时抛出。
     */
    public static function encrypt(array $payload): array
    {
        try {
            $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode MT4 provisioning payload.', 0, $exception);
        }

        return [
            'ciphertext' => Crypt::encryptString($json),
            'hash' => self::hash($json),
        ];
    }

    /**
     * 解密开户负载：先校验完整性哈希，再解析 JSON，任何一步失败都拒绝返回数据（fail-closed）。
     *
     * @param string $ciphertext 出箱表中的密文。
     * @param string $expectedHash 出箱表中保存的完整性哈希。
     * @return array<string, mixed> 解密并校验通过后的负载。
     * @throws RuntimeException 输入缺失、哈希不匹配、解密失败或 JSON 非法时抛出。
     */
    public static function decrypt(string $ciphertext, string $expectedHash): array
    {
        if (trim($ciphertext) === '' || trim($expectedHash) === '') {
            throw new RuntimeException('MT4 provisioning payload is incomplete.');
        }

        // 必须先做完整性校验再解析：哈希不匹配说明数据被篡改或损坏，后续解析结果不可信。
        $json = Crypt::decryptString($ciphertext);
        if (!hash_equals(self::hash($json), $expectedHash)) {
            throw new RuntimeException('MT4 provisioning payload hash mismatch.');
        }

        try {
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('MT4 provisioning payload is invalid.', 0, $exception);
        }
        if (!is_array($payload)) {
            throw new RuntimeException('MT4 provisioning payload must be an object.');
        }

        return $payload;
    }

    /**
     * 计算负载完整性哈希：HMAC-SHA256 使用 app.key 作为密钥。
     *
     * @param string $json 序列化后的负载明文。
     * @return string 64 位十六进制 HMAC。
     * @throws RuntimeException app.key 缺失时抛出（哈希无密钥即无完整性保障，禁止使用）。
     */
    private static function hash(string $json): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new RuntimeException('Application key is required for MT4 provisioning payloads.');
        }

        return hash_hmac('sha256', $json, $key);
    }
}
