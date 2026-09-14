<?php

declare(strict_types=1);

namespace App\Seo;

/** Small allow-list sanitizer shared by landing-page content at write and render time. */
final class LandingPageContentSanitizer
{
    private const ALLOWED_TAGS = '<p><br><strong><em><b><i><ul><ol><li><h2><h3><h4><a><blockquote>';

    public function sanitize(?string $html): ?string
    {
        $html = trim(strip_tags((string) $html, self::ALLOWED_TAGS));
        if ($html === '') return null;

        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="utf-8" ?><div>'.$html.'</div>', \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD);
        foreach ($document->getElementsByTagName('*') as $element) {
            foreach (iterator_to_array($element->attributes) as $attribute) {
                if ($element->tagName !== 'a' || !in_array($attribute->name, ['href', 'title'], true)) $element->removeAttribute($attribute->name);
            }
            if ($element->tagName === 'a') {
                $href = $element->getAttribute('href');
                if ($href !== '' && !str_starts_with($href, '/') && !str_starts_with($href, '#') && !str_starts_with($href, 'https://')) $element->removeAttribute('href');
                $element->setAttribute('rel', 'noopener');
            }
        }
        $wrapper = $document->getElementsByTagName('div')->item(0);
        if ($wrapper === null) return null;
        $result = '';
        foreach ($wrapper->childNodes as $node) $result .= $document->saveHTML($node);

        return trim($result) ?: null;
    }
}
