<?php

declare(strict_types=1);

namespace App\Cms;

use App\Entity\Cms\CmsDownload;

final class CmsBlockRendererRegistry
{
    public const TYPES = ['rich_text', 'hero', 'image_text', 'faq', 'cta', 'downloads', 'link_cards', 'product_slider', 'video'];

    public const TYPE_LABELS = [
        'rich_text' => 'Text',
        'hero' => 'Hero',
        'image_text' => 'Bild & Text',
        'faq' => 'FAQ',
        'cta' => 'Call-to-Action',
        'downloads' => 'Downloads',
        'link_cards' => 'Link-Karten',
        'product_slider' => 'Produktslider',
        'video' => 'Video',
    ];

    private readonly VideoEmbedResolver $videoEmbedResolver;

    public function __construct(?VideoEmbedResolver $videoEmbedResolver = null)
    {
        $this->videoEmbedResolver = $videoEmbedResolver ?? new VideoEmbedResolver();
    }

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
            'product_slider' => ['productCodes'],
            'video' => ['provider', 'videoUrl'],
            default => ['__unsupported'],
        };

        foreach ($required as $key) {
            if (empty($configuration[$key])) {
                $errors[] = $key . ' is required.';
            }
        }

        if (isset($configuration['buttonUrl']) && (!is_string($configuration['buttonUrl']) || !self::safeUrl($configuration['buttonUrl']))) {
            $errors[] = 'buttonUrl is unsafe.';
        }

        if ($type === 'image_text' && isset($configuration['imagePosition']) && !in_array($configuration['imagePosition'], ['left', 'right'], true)) {
            $errors[] = 'imagePosition is invalid.';
        }

        if ($type === 'downloads' && isset($configuration['types']) && (!is_array($configuration['types']) || array_filter($configuration['types'], static fn (mixed $value): bool => !is_string($value) || !in_array($value, CmsDownload::TYPES, true)))) {
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

                if (!empty($item['linkUrl']) && (!is_string($item['linkUrl']) || !self::safeUrl($item['linkUrl']))) {
                    $errors[] = sprintf('items[%d].linkUrl is unsafe.', $index);
                }
            }
        }

        if ($type === 'product_slider') {
            $productCodes = $configuration['productCodes'] ?? null;
            if ($productCodes !== null && (!is_array($productCodes) || !array_is_list($productCodes))) {
                $errors[] = 'productCodes must be a list.';
            } elseif (is_array($productCodes)) {
                foreach ($productCodes as $index => $code) {
                    if (!is_string($code) || trim($code) === '') {
                        $errors[] = sprintf('productCodes[%d] must be a non-empty string.', $index);
                    }
                }
            }

            $limit = $configuration['limit'] ?? 8;
            if (!is_int($limit) || $limit < 1 || $limit > 24) {
                $errors[] = 'limit must be between 1 and 24.';
            }
        }

        if ($type === 'video') {
            $provider = $configuration['provider'] ?? null;
            $videoUrl = $configuration['videoUrl'] ?? null;
            if ($provider !== null && (!is_string($provider) || !in_array($provider, ['youtube', 'vimeo'], true))) {
                $errors[] = 'provider is invalid.';
            }
            if ($videoUrl !== null && (!is_string($videoUrl) || $videoUrl === '')) {
                $errors[] = 'videoUrl is invalid.';
            }
            if (isset($configuration['aspectRatio']) && !in_array($configuration['aspectRatio'], ['16:9', '4:3', '1:1', '9:16'], true)) {
                $errors[] = 'aspectRatio is invalid.';
            }
            if (is_string($provider) && in_array($provider, ['youtube', 'vimeo'], true) && is_string($videoUrl) && $videoUrl !== '' && $this->videoEmbedResolver->resolve($provider, $videoUrl) === null) {
                $errors[] = 'videoUrl is not a valid URL for the selected provider.';
            }
        }

        return $errors;
    }

    private static function safeUrl(string $url): bool
    {
        return (str_starts_with($url, '/') && !str_starts_with($url, '//')) ||
            (filter_var($url, \FILTER_VALIDATE_URL) !== false && in_array(parse_url($url, \PHP_URL_SCHEME), ['http', 'https'], true));
    }
}
