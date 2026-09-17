<?php
declare(strict_types=1);
namespace App\Tests\Sales;
use App\Entity\Sales\ProductExpert;
use App\Entity\Sales\ProductExpertProduct;
use PHPUnit\Framework\TestCase;
final class ProductExpertTest extends TestCase
{
    public function testItNormalizesProfileInputAndDefaultsToEnabled(): void
    { $expert = new ProductExpert(); $expert->setDisplayName(' Max Mustermann '); $expert->setSlug('max-mustermann'); $expert->setIntroText('  Beratung  '); self::assertSame('Max Mustermann', $expert->getDisplayName()); self::assertSame('max-mustermann', $expert->getSlug()); self::assertSame('Beratung', $expert->getIntroText()); self::assertTrue($expert->isEnabled()); }
    public function testPositionCannotBeNegative(): void
    { $item = new ProductExpertProduct(); $item->setPosition(-10); self::assertSame(0, $item->getPosition()); }
}
