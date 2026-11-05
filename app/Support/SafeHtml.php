<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

final class SafeHtml
{
    public static function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NONET);
            $body = $document->getElementsByTagName('body')->item(0);
            if (! $body) {
                return null;
            }
            self::walk($body);
            $result = '';
            foreach ($body->childNodes as $child) {
                $result .= $document->saveHTML($child);
            }

            return trim($result) ?: null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function walk(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                if ($child->nodeType !== XML_TEXT_NODE) {
                    $parent->removeChild($child);
                }

                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template', 'form', 'input', 'textarea', 'button'], true)) {
                $parent->removeChild($child);

                continue;
            }
            self::walk($child);
            if (! in_array($tag, ['p', 'br', 'ul', 'ol', 'li', 'strong', 'em', 'h2', 'h3', 'h4', 'blockquote', 'code', 'pre', 'a'], true)) {
                while ($child->firstChild) {
                    $parent->insertBefore($child->firstChild, $child);
                }
                $parent->removeChild($child);

                continue;
            }
            $href = $child->getAttribute('href');
            foreach (iterator_to_array($child->attributes) as $attribute) {
                $child->removeAttribute($attribute->name);
            }
            if ($tag === 'a' && filter_var($href, FILTER_VALIDATE_URL) && strtolower((string) parse_url($href, PHP_URL_SCHEME)) === 'https'
                && ! parse_url($href, PHP_URL_USER) && ! parse_url($href, PHP_URL_PASS)) {
                $child->setAttribute('href', $href);
                $child->setAttribute('rel', 'nofollow noopener noreferrer');
            }
        }
    }
}
