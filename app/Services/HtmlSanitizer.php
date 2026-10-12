<?php

namespace App\Services;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;

/**
 * Cleans HTML that a user wrote before it is printed unescaped.
 *
 * The legacy store prints product descriptions, classified-ad descriptions and
 * support-ticket text with a raw echo, so anyone who can write one (a seller, a
 * customer, a customer filing a ticket that staff then open) could run script
 * in the browser of whoever views it, a logged-in admin included.
 *
 * This is an allowlist, not a list of bad patterns: the markup is parsed with
 * PHP's HTML5 parser (the same algorithm browsers use, so what is checked is
 * what a browser would build), and only the tags, attributes, URL schemes and
 * CSS properties named below survive. Everything else is dropped or unwrapped,
 * the result is serialised from the cleaned tree, and no element from the SVG
 * or MathML namespaces gets through, which is where the mutation-XSS tricks
 * against sanitisers live. Rich-text formatting (headings, lists, tables,
 * links, images, colours, alignment) is kept.
 *
 * Content an admin writes (CMS pages, blog posts, settings texts) is trusted
 * and is not passed through here.
 */
class HtmlSanitizer
{
    private const HTML_NAMESPACE = 'http://www.w3.org/1999/xhtml';

    /** Tags kept as they are (their attributes are filtered below). */
    private const ALLOWED_TAGS = [
        'a', 'abbr', 'b', 'blockquote', 'br', 'caption', 'cite', 'code', 'col', 'colgroup', 'dd', 'del', 'div', 'dl', 'dt',
        'em', 'figcaption', 'figure', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'img', 'ins', 'li', 'mark', 'ol', 'p',
        'pre', 'q', 's', 'small', 'span', 'strike', 'strong', 'sub', 'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead',
        'tr', 'u', 'ul',
    ];

    /** Tags removed together with everything inside them. Any other unknown tag is unwrapped. */
    private const DROPPED_WITH_CONTENT = [
        'applet', 'audio', 'base', 'button', 'canvas', 'dialog', 'embed', 'form', 'frame', 'frameset', 'head', 'iframe',
        'input', 'link', 'math', 'meta', 'noembed', 'noframes', 'noscript', 'object', 'optgroup', 'option', 'plaintext',
        'script', 'select', 'source', 'style', 'svg', 'template', 'textarea', 'title', 'track', 'video', 'xmp',
    ];

    /** Attributes kept on every allowed tag. */
    private const GLOBAL_ATTRIBUTES = ['class', 'dir', 'lang', 'style', 'title'];

    /** Attributes kept on particular tags, besides the global ones. */
    private const TAG_ATTRIBUTES = [
        'a' => ['href', 'target'],
        'col' => ['span', 'width'],
        'colgroup' => ['span'],
        'img' => ['alt', 'height', 'src', 'width'],
        'ol' => ['start', 'type'],
        'td' => ['align', 'colspan', 'rowspan', 'valign'],
        'th' => ['align', 'colspan', 'rowspan', 'scope', 'valign'],
    ];

    /** CSS properties kept in a `style` attribute: text and box formatting, nothing that positions, hides or fetches. */
    private const ALLOWED_CSS_PROPERTIES = [
        'background-color', 'border', 'border-bottom', 'border-collapse', 'border-color', 'border-left', 'border-right',
        'border-style', 'border-top', 'border-width', 'color', 'font-family', 'font-size', 'font-style', 'font-weight',
        'height', 'letter-spacing', 'line-height', 'list-style-type', 'margin', 'margin-bottom', 'margin-left',
        'margin-right', 'margin-top', 'max-width', 'padding', 'padding-bottom', 'padding-left', 'padding-right',
        'padding-top', 'text-align', 'text-decoration', 'text-indent', 'vertical-align', 'width',
    ];

    public function clean(?string $html): string
    {
        $html = (string) $html;

        if (trim($html) === '') {
            return '';
        }

        $document = HTMLDocument::createFromString('<!DOCTYPE html><html><head></head><body></body></html>');
        $body = $document->body;
        $body->innerHTML = $html;

        $this->cleanChildren($body);

        return $body->innerHTML;
    }

    private function cleanChildren(Node $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if ($child instanceof Text) {
                continue;
            }

            if (! $child instanceof Element) {
                $parent->removeChild($child);

                continue;
            }

            $tag = strtolower($child->localName);

            if ($child->namespaceURI !== self::HTML_NAMESPACE || in_array($tag, self::DROPPED_WITH_CONTENT, true)) {
                $parent->removeChild($child);

                continue;
            }

            $this->cleanChildren($child);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                $this->unwrap($parent, $child);

                continue;
            }

            $this->cleanAttributes($child, $tag);
        }
    }

    /** Replace an element by its children. */
    private function unwrap(Node $parent, Element $element): void
    {
        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private function cleanAttributes(Element $element, string $tag): void
    {
        $allowed = [...self::GLOBAL_ATTRIBUTES, ...(self::TAG_ATTRIBUTES[$tag] ?? [])];

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);

            if (! in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->name);

                continue;
            }

            $value = match (true) {
                $name === 'style' => $this->cleanStyle($attribute->value),
                $name === 'href' => $this->safeUrl($attribute->value, false),
                $name === 'src' => $this->safeUrl($attribute->value, true),
                $name === 'target' => $attribute->value === '_blank' ? '_blank' : null,
                default => $attribute->value,
            };

            if ($value === null) {
                $element->removeAttribute($attribute->name);
            } else {
                $element->setAttribute($attribute->name, $value);
            }
        }

        if ($tag === 'a') {
            $element->setAttribute('rel', 'noopener noreferrer nofollow');
        }
    }

    /**
     * The URL if it is safe where it is used, else null: http, https, mailto
     * and tel links, and relative ones; for an image, http, https, relative and
     * a base64 PNG, JPEG, GIF or WebP (the rich-text editor embeds those).
     */
    private function safeUrl(string $url, bool $isImage): ?string
    {
        // Browsers ignore whitespace and control characters inside a scheme
        // (`java\tscript:`), so the check looks at the URL without them.
        $compact = (string) preg_replace('/[\x00-\x20\x7F-\x9F]+/u', '', $url);

        if ($compact === '') {
            return null;
        }

        if (! preg_match('/^([a-z][a-z0-9+.\-]*):/i', $compact, $scheme)) {
            return trim($url);
        }

        $scheme = strtolower($scheme[1]);

        if ($isImage && $scheme === 'data') {
            return preg_match('#^data:image/(png|jpe?g|gif|webp);base64,[a-z0-9+/=]+$#i', $compact) === 1 ? $compact : null;
        }

        $schemes = $isImage ? ['http', 'https'] : ['http', 'https', 'mailto', 'tel'];

        return in_array($scheme, $schemes, true) ? trim($url) : null;
    }

    /** The declarations of a `style` attribute that use an allowed property and a plain value, or null if none do. */
    private function cleanStyle(string $style): ?string
    {
        $kept = [];

        foreach (explode(';', $style) as $declaration) {
            [$property, $value] = array_pad(explode(':', $declaration, 2), 2, '');
            $property = strtolower(trim($property));
            $value = trim($value);

            if (! in_array($property, self::ALLOWED_CSS_PROPERTIES, true) || $value === '') {
                continue;
            }

            $plain = $property === 'font-family'
                ? preg_match('/^[a-z0-9\s,\-\'"]+$/i', $value) === 1
                : preg_match('/^[a-z0-9\s#%.,()+\-]+$/i', $value) === 1;

            if ($plain && ! preg_match('/url\s*\(|expression|javascript|import|behavior|binding/i', $value)) {
                $kept[] = "$property: $value";
            }
        }

        return $kept === [] ? null : implode('; ', $kept);
    }
}
