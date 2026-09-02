<?php

declare(strict_types=1);

namespace App\Tests\ProductImage;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class LiipImagineConfigurationTest extends TestCase
{
    public function testSyliusImageLoaderResolvesNativeAndImportedProductImagePaths(): void
    {
        $configuration = Yaml::parseFile(__DIR__ . '/../../config/packages/cardnext_product_images.yaml');

        self::assertArrayHasKey('sylius_image', $configuration['liip_imagine']['loaders']);
        self::assertSame([
            '%kernel.project_dir%/public/media/image',
            '%kernel.project_dir%/public',
        ], $configuration['liip_imagine']['loaders']['sylius_image']['filesystem']['data_root']);

        self::assertArrayNotHasKey('default', $configuration['liip_imagine']['loaders']);

        $nativeImagePath = 'products/native-image.jpg';
        $importedImagePath = 'media/cardnext/products/product-code/imported-image.jpg';
        $projectDirectory = '/srv/cardnext';
        $dataRoots = array_map(
            static fn (string $root): string => str_replace('%kernel.project_dir%', $projectDirectory, $root),
            $configuration['liip_imagine']['loaders']['sylius_image']['filesystem']['data_root'],
        );

        self::assertSame('/srv/cardnext/public/media/image/products/native-image.jpg', $dataRoots[0] . '/' . $nativeImagePath);
        self::assertSame('/srv/cardnext/public/media/cardnext/products/product-code/imported-image.jpg', $dataRoots[1] . '/' . $importedImagePath);
    }

    public function testProductCardFilterRemainsUnchanged(): void
    {
        $configuration = Yaml::parseFile(__DIR__ . '/../../config/packages/cardnext_product_images.yaml');

        self::assertSame([
            'format' => 'webp',
            'quality' => 85,
            'filters' => [
                'thumbnail' => ['size' => [600, 600], 'mode' => 'inset'],
            ],
        ], $configuration['liip_imagine']['filter_sets']['cardnext_product_card']);
    }
}
