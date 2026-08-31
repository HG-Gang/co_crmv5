<?php

/**
 Created by PhpStorm.
 * Project name co_crmv5.
 * User: Huang Gang
 * Date: 2026/08/02
 * Time: 09:03
 */

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * 后台富文本最小允许列表净化器。
 *
 * 文件功能：
 * - 按白名单保留新闻排版常用标签（段落/标题/列表/表格/链接/图片等），移除其余所有可执行内容。
 * - 白名单外标签保留其子内容并就地解包；script/style/iframe/object/embed/svg/math/form 等整块丢弃（含内容）。
 * - 属性仅保留白名单列出的子集；href/src 按协议白名单校验（拒绝 javascript:/vbscript:/data:）。
 * - target=_blank 的链接强制补 rel="noopener noreferrer"。
 *
 * 安全边界：
 * - 默认拒绝一切未白名单的标签、属性与危险 URL 协议（fail-closed，宁可丢排版不可留 XSS）。
 * - 纯净化只读输入输出字符串，不执行任何脚本，也不依赖配置化的黑名单。
 *
 * 适用场景：
 * - 管理员编辑新闻/公告等富文本后入库前调用 sanitize()。
 *
 * 返回值：
 * - sanitize() 返回净化后的 HTML 字符串；空输入返回空串，净化失败返回空串。
 */
final class SafeHtml
{
    /**
     * 允许保留的标签；白名单外标签的子内容会被解包保留，但标签本身与危险属性被移除。
     */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li',
        'blockquote', 'a', 'img', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'table', 'thead', 'tbody', 'tr', 'th', 'td', 'hr', 'span', 'div',
    ];

    /**
     * 整体丢弃的标签（含子内容）：可执行或高危内容无法安全净化，只能移除。
     */
    private const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'object', 'embed', 'svg', 'math',
        'form', 'input', 'button', 'textarea', 'select', 'option', 'link', 'meta',
    ];

    /**
     * 各标签允许保留的属性子集；未列出的属性一律删除。
     */
    private const ATTRIBUTES = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'th' => ['colspan', 'rowspan'],
        'td' => ['colspan', 'rowspan'],
    ];

    /**
     * 净化富文本 HTML。
     *
     * @param string $html 待净化的 HTML。
     * @return string 净化后的 HTML；空输入或 DOM 解析失败返回空串（失败关闭）。
     */
    public static function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        // 用固定根节点包裹解析，便于净化和序列化时不影响根外的 DOCTYPE/html 包装；
        // 解析错误静默化，避免畸形 HTML 抛出异常。
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="safe-html-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = (new DOMXPath($document))->query('//*[@id="safe-html-root"]')->item(0);
        if (!$root instanceof DOMElement) {
            return '';
        }

        self::sanitizeChildren($root);

        // 只序列化根下的真实子节点，避免输出根包裹 div 自身。
        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return $result;
    }

    /**
     * 递归净化子节点：丢弃注释与高危标签，白名单外标签解包，白名单内标签净化属性后递归。
     *
     * @param DOMNode $parent 父节点。
     * @return void
     */
    private static function sanitizeChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            // HTML 注释可能隐藏内容或用于条件注入，一律删除。
            if ($node->nodeType === XML_COMMENT_NODE) {
                $parent->removeChild($node);
                continue;
            }
            if (!$node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);
            // 可执行/高危标签整块移除：其内容无法安全净化，只能丢弃。
            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $parent->removeChild($node);
                continue;
            }
            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                // 白名单外标签：保留子内容，把每个子节点提升到父级后移除标签本身。
                self::sanitizeChildren($node);
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
                continue;
            }

            self::sanitizeAttributes($node, $tag);
            self::sanitizeChildren($node);
        }
    }

    /**
     * 净化单标签属性：仅保留白名单子集，危险 URL 协议移除，外链新窗口补 rel。
     *
     * @param DOMElement $element 目标元素。
     * @param string $tag 标签名（小写）。
     * @return void
     */
    private static function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $allowed = self::ATTRIBUTES[$tag] ?? [];
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            if (!in_array($name, $allowed, true)) {
                $element->removeAttributeNode($attribute);
                continue;
            }
            // href/src 是注入入口，必须通过协议校验；其余属性不做 URL 校验。
            if (($name === 'href' || $name === 'src') && !self::isSafeUrl($attribute->value, $name === 'src')) {
                $element->removeAttributeNode($attribute);
            }
        }

        // target=_blank 存在 tabnabbing 风险：强制补 noopener noreferrer 兜底。
        if ($tag === 'a' && strtolower($element->getAttribute('target')) === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    /**
     * URL 协议白名单校验。
     *
     * 语义：先 HTML 实体解码并去除全部空白（防混淆编码绕过），
     * 拒绝 javascript:/vbscript:/data: 协议；相对路径（/、#）放行；
     * 图片仅允许 http/https，链接额外允许 mailto；无协议视为安全放行。
     *
     * @param string $value 原始 URL。
     * @param bool $image 是否为 img src（更严格）。
     * @return bool 安全为 true。
     */
    private static function isSafeUrl(string $value, bool $image): bool
    {
        $decoded = html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = preg_replace('/[\x00-\x20]+/', '', $decoded) ?: '';
        if ($normalized === '' || preg_match('/^(?:javascript|vbscript|data):/i', $normalized)) {
            return false;
        }
        if ($normalized[0] === '/' || $normalized[0] === '#') {
            return true;
        }

        $scheme = strtolower((string) parse_url($normalized, PHP_URL_SCHEME));
        $allowedSchemes = $image ? ['http', 'https'] : ['http', 'https', 'mailto'];

        return $scheme === '' || in_array($scheme, $allowedSchemes, true);
    }
}
