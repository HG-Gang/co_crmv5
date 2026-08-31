<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 15:08
 */

/**
 * SafeHtml::sanitize(SafeHtmlSanitizerTest)HTML 消毒器的安全回归测试。
 *
 * 文件功能:
 * - 验证消毒器移除可执行标签(<script>/<style>/<svg>/<math>)、事件属性(onclick/onload/onerror)、
 *   srcset、data: 协议及混淆后的 javascript:/vbscript: 协议。
 * - 验证安全富文本(链接、图片、加粗)保留,未知自定义元素被解包只留子内容。
 * - 验证畸形 HTML 消毒后不会重新引入 <script> 或事件属性。
 *
 * 适用场景:任何改动 SafeHtml 白名单、协议过滤或解析逻辑后必须回归,
 * 防止富文本场景重新引入 XSS 注入面。
 *
 * 入参例子:sanitize() 接收原始 HTML 字符串,如带 onclick 的 <p>、混淆协议链接、
 * data: 图片等混合输入;空输入返回空串由实现保证,本测试不覆盖。
 *
 * 返回值:返回消毒后的 HTML 字符串;断言通过表示输出只含白名单内内容,闭环成立。
 *
 * 失败场景:任一危险片段仍出现在输出中即断言失败,说明消毒器出现安全回归,
 * 必须修复后才可发布,不能以"测试输入太刁钻"为由放宽。
 */

namespace Tests\Feature;

use App\Support\SafeHtml;
use Tests\TestCase;

class SafeHtmlSanitizerTest extends TestCase
{
    public function test_sanitizer_removes_executable_tags_attributes_and_obfuscated_protocols(): void
    {
        $html = <<<'HTML'
<p onclick="alert(1)">Safe text</p>
<script>alert(2)</script><style>body{display:none}</style>
<svg onload="alert(3)"><circle></circle></svg><math><mi>x</mi></math>
<a href="java&#x0A;script:alert(4)">JavaScript</a>
<a href="vbscript:msgbox(5)">VBScript</a>
<img src="data:image/svg+xml;base64,PHN2Zy8+" onerror="alert(6)" srcset="bad">
HTML;

        $sanitized = SafeHtml::sanitize($html);

        $this->assertStringContainsString('<p>Safe text</p>', $sanitized);
        foreach (['<script', '<style', '<svg', '<math', 'onclick=', 'onload=', 'onerror=', 'srcset=', 'javascript:', 'vbscript:', 'data:'] as $dangerous) {
            $this->assertStringNotContainsString($dangerous, strtolower($sanitized));
        }
    }

    public function test_sanitizer_keeps_safe_rich_text_and_unwraps_unknown_elements(): void
    {
        $sanitized = SafeHtml::sanitize(
            '<custom-box><strong>Kept child</strong></custom-box>' .
            '<a href="https://example.test/path" target="_blank" title="Safe">Link</a>' .
            '<img src="/images/news.png" alt="News" width="120">'
        );

        $this->assertStringNotContainsString('custom-box', $sanitized);
        $this->assertStringContainsString('<strong>Kept child</strong>', $sanitized);
        $this->assertStringContainsString('href="https://example.test/path"', $sanitized);
        $this->assertStringContainsString('target="_blank"', $sanitized);
        $this->assertStringContainsString('rel="noopener noreferrer"', $sanitized);
        $this->assertStringContainsString('src="/images/news.png"', $sanitized);
    }

    public function test_sanitizer_handles_malformed_html_without_reintroducing_script(): void
    {
        $sanitized = SafeHtml::sanitize('<p>Before<scr<script>ipt>alert(1)</scr<script>ipt><b>After');

        $this->assertStringContainsString('Before', $sanitized);
        $this->assertStringNotContainsString('<script', strtolower($sanitized));
        $this->assertStringNotContainsString('onerror=', strtolower($sanitized));
    }
}
