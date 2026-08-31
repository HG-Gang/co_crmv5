<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 03:55
 */

declare(strict_types=1);

/**
 * 密钥引用校验单元测试。
 *
 * 文件功能：
 * - 校验 SecretReference::isValid 只接受显式的 env:/vault: 引用格式。
 * - 校验裸环境变量名、类密钥字符串、空白、控制字符、小写前缀及空 vault 一律拒绝。
 *
 * 适用场景：
 * - 改动密钥引用解析/校验逻辑后回归。
 *
 * 入参例子：
 * - 合法：env:PAYMENT_TIGER_SECRET、vault:payments/tiger/signing-key、vault:payments/tiger#private_key。
 * - 非法：PAYMENT_TIGER_SECRET、sk-live-abcdef123、vault:。
 *
 * 返回值：断言通过表示引用校验结果与预期一致。
 *
 * 异常或失败场景：
 * - 裸值或危险字符被放行、合法引用被拒绝时失败。
 */
namespace Tests\Unit;

use App\Support\SecretReference;
use PHPUnit\Framework\TestCase;

class SecretReferenceTest extends TestCase
{
    /**
     * @dataProvider validReferenceProvider
     * 校验显式密钥引用格式被接受。
     *
     * @param string $reference 密钥引用。
     * @return void 断言通过不返回值。
     */
    public function test_explicit_secret_references_are_accepted(string $reference): void
    {
        $this->assertTrue(SecretReference::isValid($reference));
    }

    public function validReferenceProvider(): array
    {
        return [
            'environment' => ['env:PAYMENT_TIGER_SECRET'],
            'vault path' => ['vault:payments/tiger/signing-key'],
            'vault field' => ['vault:payments/tiger#private_key'],
        ];
    }

    /**
     * @dataProvider invalidReferenceProvider
     * 校验裸值或不安全的密钥内容被拒绝。
     *
     * @param string $reference 密钥引用。
     * @return void 断言通过不返回值。
     */
    public function test_bare_or_unsafe_secret_values_are_rejected(string $reference): void
    {
        $this->assertFalse(SecretReference::isValid($reference));
    }

    public function invalidReferenceProvider(): array
    {
        return [
            'bare environment name' => ['PAYMENT_TIGER_SECRET'],
            'live key lookalike' => ['sk-live-abcdef123'],
            'whitespace' => ['env:PAYMENT SECRET'],
            'control' => ["vault:payments\nsecret"],
            'lowercase environment' => ['env:payment_secret'],
            'empty vault' => ['vault:'],
        ];
    }
}
