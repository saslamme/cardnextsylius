<?php

declare(strict_types=1);

namespace App\Cms;

use App\Entity\Cms\CmsDownload;

final class CmsBlockRendererRegistry
{
    public const TYPES = ['rich_text', 'hero', 'image_text', 'faq', 'cta', 'downloads', 'link_cards', 'product_slider', 'video', 'manufacturer_slider', 'gallery', 'features', 'stats', 'testimonials'];

    public const FEATURE_ICONS = ['consulting', 'shipping', 'support', 'quality', 'business', 'security', 'stock', 'technology', 'service', 'warranty', 'international', 'sustainability'];

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
        'manufacturer_slider' => 'Hersteller-Slider',
        'gallery' => 'Galerie',
        'features' => 'Vorteile / USP',
        'stats' => 'Zahlen & Fakten',
        'testimonials' => 'Kundenstimmen',
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
            'faq', 'link_cards', 'features', 'stats', 'testimonials' => ['items'],
            'cta' => ['headline', 'buttonLabel', 'buttonUrl'],
            'downloads' => [],
            'product_slider' => ['productCodes'],
            'video' => ['provider', 'videoUrl'],
            'manufacturer_slider' => ['manufacturerCodes'],
            'gallery' => ['items'],
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

        if ($type === 'manufacturer_slider') {
            $codes = $configuration['manufacturerCodes'] ?? null;
            if ($codes !== null && (!is_array($codes) || !array_is_list($codes))) {
                $errors[] = 'manufacturerCodes must be a list.';
            } elseif (is_array($codes)) {
                foreach ($codes as $index => $code) {
                    if (!is_string($code) || trim($code) === '') {
                        $errors[] = sprintf('manufacturerCodes[%d] must be a non-empty string.', $index);
                    }
                }
            }
            $limit = $configuration['limit'] ?? 12;
            if (!is_int($limit) || $limit < 1 || $limit > 24) {
                $errors[] = 'limit must be between 1 and 24.';
            }
            foreach (['showNavigation', 'linkToManufacturer'] as $option) {
                if (isset($configuration[$option]) && !is_bool($configuration[$option])) {
                    $errors[] = $option . ' must be boolean.';
                }
            }
        }

        if ($type === 'gallery') {
            $items = $configuration['items'] ?? null;
            if ($items !== null && (!is_array($items) || !array_is_list($items))) {
                $errors[] = 'items must be a list.';
            } elseif (is_array($items)) {
                foreach ($items as $index => $item) {
                    if (!is_array($item)) {
                        $errors[] = sprintf('items[%d] is invalid.', $index);

                        continue;
                    }
                    if (!isset($item['image']) || !is_string($item['image']) || !self::managedImagePath($item['image'])) {
                        $errors[] = sprintf('items[%d].image is required.', $index);
                    }
                    foreach (['alt', 'caption'] as $field) {
                        if (isset($item[$field]) && !is_string($item[$field])) {
                            $errors[] = sprintf('items[%d].%s must be a string.', $index, $field);
                        }
                    }
                }
            }
            if (!in_array($configuration['columns'] ?? 3, [2, 3, 4], true)) {
                $errors[] = 'columns must be 2, 3 or 4.';
            }
            if (isset($configuration['showCaptions']) && !is_bool($configuration['showCaptions'])) {
                $errors[] = 'showCaptions must be boolean.';
            }
        }

        if (in_array($type, ['features', 'stats', 'testimonials'], true)) {
            $items = $configuration['items'] ?? null;
            if ($items !== null && (!is_array($items) || !array_is_list($items))) {
                $errors[] = 'items must be a list.';
            } elseif (is_array($items)) {
                foreach ($items as $index => $item) {
                    if (!is_array($item)) {
                        $errors[] = sprintf('items[%d] is invalid.', $index);

                        continue;
                    }

                    if ($type === 'features') {
                        self::validateRequiredString($errors, $item, $index, 'title');
                        self::validateOptionalString($errors, $item, $index, 'text');
                        if (isset($item['icon']) && (!is_string($item['icon']) || !in_array($item['icon'], self::FEATURE_ICONS, true))) {
                            $errors[] = sprintf('items[%d].icon is invalid.', $index);
                        }
                    } elseif ($type === 'stats') {
                        self::validateRequiredString($errors, $item, $index, 'value');
                        self::validateRequiredString($errors, $item, $index, 'label');
                        self::validateOptionalString($errors, $item, $index, 'description');
                    } else {
                        self::validateRequiredString($errors, $item, $index, 'quote');
                        foreach (['name', 'role', 'company'] as $field) {
                            self::validateOptionalString($errors, $item, $index, $field);
                        }
                        $name = $item['name'] ?? null;
                        $company = $item['company'] ?? null;
                        if ((!is_string($name) || trim($name) === '') && (!is_string($company) || trim($company) === '')) {
                            $errors[] = sprintf('items[%d] requires a name or company.', $index);
                        }
                    }
                }
            }

            $allowedColumns = $type === 'testimonials' ? [1, 2, 3] : [2, 3, 4];
            $defaultColumns = $type === 'testimonials' ? 3 : 4;
            if (!in_array($configuration['columns'] ?? $defaultColumns, $allowedColumns, true)) {
                $errors[] = $type === 'testimonials' ? 'columns must be 1, 2 or 3.' : 'columns must be 2, 3 or 4.';
            }
        }

        return $errors;
    }

    private static function safeUrl(string $url): bool
    {
        return (str_starts_with($url, '/') && !str_starts_with($url, '//')) ||
            (filter_var($url, \FILTER_VALIDATE_URL) !== false && in_array(parse_url($url, \PHP_URL_SCHEME), ['http', 'https'], true));
    }

    /**
     * @param list<string> $errors
     * @param array<mixed> $item
     */
    private static function validateRequiredString(array &$errors, array $item, int $index, string $field): void
    {
        if (!isset($item[$field]) || !is_string($item[$field]) || trim($item[$field]) === '') {
            $errors[] = sprintf('items[%d].%s is required.', $index, $field);
        }
    }

    /**
     * @param list<string> $errors
     * @param array<mixed> $item
     */
    private static function validateOptionalString(array &$errors, array $item, int $index, string $field): void
    {
        if (isset($item[$field]) && !is_string($item[$field])) {
            $errors[] = sprintf('items[%d].%s must be a string.', $index, $field);
        }
    }

    private static function managedImagePath(string $path): bool
    {
        return preg_match('#^uploads/cms/[^/]+\.(?:jpe?g|png|webp)$#i', $path) === 1;
    }
}
