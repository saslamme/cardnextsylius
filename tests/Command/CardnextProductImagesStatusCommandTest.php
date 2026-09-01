<?php

declare(strict_types=1);

namespace App\Tests\Command;

use PHPUnit\Framework\TestCase;

final class CardnextProductImagesStatusCommandTest extends TestCase
{
    public function testStatusCommandIsReadOnlyAndExportsRequiredColumns(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/Command/CardnextProductImagesStatusCommand.php');
        self::assertIsString($source);
        self::assertStringContainsString("name: 'cardnext:product-images:status'", $source);
        self::assertStringContainsString("['product_code', 'manufacturer', 'manufacturer_part_number', 'name', 'enabled', 'image_count']", $source);
        self::assertStringNotContainsString('->flush(', $source);
        self::assertStringNotContainsString('->persist(', $source);
    }
}
