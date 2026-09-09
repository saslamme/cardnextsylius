<?php

declare(strict_types=1);

namespace Tests\Entity;

use App\Entity\Product\ProductTranslation;
use PHPUnit\Framework\TestCase;

final class ProductTranslationTest extends TestCase
{
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
