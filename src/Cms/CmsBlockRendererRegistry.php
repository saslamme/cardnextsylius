<?php

declare(strict_types=1);

namespace App\Cms;

use App\Entity\Cms\CmsDownload;

final class CmsBlockRendererRegistry
{
    public const TYPES = ['rich_text', 'hero', 'image_text', 'faq', 'cta', 'downloads', 'link_cards'];

    public function template(string $type): string
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException('Unknown CMS block type.');
        }

        return 'shop/cms/block/_' . $type . '.html.twig';
    }

    /**
     * @param array<string, mixed> $configuration
     *
     * @return list<string>
     */
    public function validate(string $type, array $configuration): array
    {
        $errors = [];
        $required = match ($type) {
            'rich_text' => ['content'],
            'hero' => ['headline'],
            'image_text' => ['text'],
            'faq', 'link_cards' => ['items'],
            'cta' => ['headline', 'buttonLabel', 'buttonUrl'],
            'downloads' => [],
            default => ['__unsupported'],
        };

        foreach ($required as $key) {
            if (empty($configuration[$key])) {
                $errors[] = $key . ' is required.';
            }
        }

        if (isset($configuration['buttonUrl']) && !self::safeUrl((string) $configuration['buttonUrl'])) {
            $errors[] = 'buttonUrl is unsafe.';
        }

        if ($type === 'image_text' && isset($configuration['imagePosition']) && !in_array($configuration['imagePosition'], ['left', 'right'], true)) {
            $errors[] = 'imagePosition is invalid.';
        }

        if ($type === 'downloads' && isset($configuration['types']) && array_diff((array) $configuration['types'], CmsDownload::TYPES)) {
            $errors[] = 'types contains an invalid type.';
        }

        if ($type === 'link_cards') {
            foreach ((array) ($configuration['items'] ?? []) as $index => $item) {
                if (!is_array($item)) {
                    $errors[] = sprintf('items[%d] is invalid.', $index);
                    continue;
                }

                if (empty($item['title'])) {
                    $errors[] = sprintf('items[%d].title is required.', $index);
                }

                if (!empty($item['linkUrl']) && !self::safeUrl((string) $item['linkUrl'])) {
                    $errors[] = sprintf('items[%d].linkUrl is unsafe.', $index);
                }
            }
        }

        return $errors;
    }

    private static function safeUrl(string $url): bool
    {
        return (str_starts_with($url, '/') && !str_starts_with($url, '//'))
            || (filter_var($url, FILTER_VALIDATE_URL) !== false && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true));
    }
}
