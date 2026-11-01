<?php
declare(strict_types=1);

namespace Moves\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/** Allowlist de HTML editorial: remove scripts, eventos, estilos e URLs perigosas. */
final class HtmlSanitizer
{
    private const TAGS = ['p', 'br', 'hr', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'strong', 'b', 'em', 'i', 'u', 'a', 'img', 'blockquote', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'figure', 'figcaption', 'span', 'div'];
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta', 'base', 'template'];
    private const ATTRS = ['a' => ['href', 'title'], 'img' => ['src', 'alt', 'title', 'width', 'height']];

    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="rz-root">' . $html . '</div>', LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $dom->getElementById('rz-root');
        if ($root === null) {
            return '';
        }
        self::walk($root);
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }
        return trim($out);
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);
                continue;
            }
            if (!$child instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);
                continue;
            }
            self::walk($child);
            if (!in_array($tag, self::TAGS, true)) {
                while ($child->firstChild !== null) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            $allowed = self::ATTRS[$tag] ?? [];
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                $safe = in_array($name, $allowed, true)
                    && (!in_array($name, ['href', 'src'], true) || self::safeUrl($attr->value, $name === 'src'));
                if (!$safe) {
                    $child->removeAttribute($attr->name);
                }
            }
            if ($tag === 'a' && $child->hasAttribute('href')) {
                $child->setAttribute('rel', 'noopener nofollow');
            }
        }
    }

    public static function safeUrl(string $url, bool $imageOnly = false): bool
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '' || preg_match('/[\x00-\x20\\\\]/', $url) === 1 || str_starts_with($url, '//')) {
            return false;
        }
        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }
        $pattern = $imageOnly ? '~^https://~i' : '~^(https?://|mailto:)~i';
        return preg_match($pattern, $url) === 1;
    }
}
