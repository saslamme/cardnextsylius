<?php

declare(strict_types=1);

namespace Tests\Entity;

use App\Entity\Product\ProductTranslation;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use PHPUnit\Framework\TestCase;

final class ProductTranslationTest extends TestCase
{
    public function testSearchSynonymsUsesMigrationColumnName(): void
    {
        $metadata = new ClassMetadata(ProductTranslation::class);
        (new AttributeDriver([\dirname(__DIR__, 2) . '/src/Entity/Product']))->loadMetadataForClass(ProductTranslation::class, $metadata);

        self::assertSame('search_synonyms', $metadata->getColumnName('searchSynonyms'));
    }

    public function testSearchSynonymsPreserveTheirFormattingAndTrimOuterWhitespace(): void
    {
        $translation = new ProductTranslation();
        $translation->setSearchSynonyms("  Ausweisdrucker\nBadge Printer  ");

        self::assertSame("Ausweisdrucker\nBadge Printer", $translation->getSearchSynonyms());
    }

    public function testEmptySearchSynonymsAreStoredAsNull(): void
    {
        $translation = new ProductTranslation();
        $translation->setSearchSynonyms(" \n ");

        self::assertNull($translation->getSearchSynonyms());
    }
}
