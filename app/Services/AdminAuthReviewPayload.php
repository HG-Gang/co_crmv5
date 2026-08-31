<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/07
 * Time: 18:11
 */

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use JsonException;
use RuntimeException;

/**
 * 实名审核载荷加密服务。
 *
 * 文件功能：
 * - encrypt()：把审核意图载荷 JSON 编码后经 Crypt 加密，并生成摘要指纹，返回 ciphertext + hash。
 * - decrypt()：解密后用 hash_equals 校验摘要，载荷缺失、被篡改或 JSON 非法时抛 RuntimeException 失败关闭。
 * - 明确不负责：审核状态机流转与落库（AdminAuthReviewProcessor / AuthReviewTransition）。
 */
final class AdminAuthReviewPayload
{
    /**
     * @param array<string, mixed> $payload
     * @return array{ciphertext: string, hash: string}
     */
    public static function encrypt(array $payload): array
    {
        $json = self::encode($payload);

        return [
            'ciphertext' => Crypt::encryptString($json),
            'hash' => self::hash($json),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function decrypt(string $ciphertext, string $expectedHash): array
    {
        if (trim($ciphertext) === '' || trim($expectedHash) === '') {
            throw new RuntimeException('Admin authentication review payload is incomplete.');
        }

        $json = Crypt::decryptString($ciphertext);
        if (!hash_equals(self::hash($json), $expectedHash)) {
            throw new RuntimeException('Admin authentication review payload hash mismatch.');
        }

        try {
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Admin authentication review payload is invalid.', 0, $exception);
        }

        if (!is_array($payload)) {
            throw new RuntimeException('Admin authentication review payload must be an object.');
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public static function snapshotHash(array $snapshot): string
    {
        return self::hash(self::encode($snapshot));
    }

    /**
     * @param array<string, mixed> $value
     */
    private static function encode(array $value): string
    {
        try {
            return json_encode(
                $value,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode administrator authentication review data.', 0, $exception);
        }
    }

    private static function hash(string $value): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new RuntimeException('Application key is required for authentication review payloads.');
        }

        return hash_hmac('sha256', $value, $key);
    }
}
