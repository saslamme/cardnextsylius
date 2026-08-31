<?php

declare(strict_types=1);

namespace App\Tests\Configurator;

use App\Dto\Configurator\ConfiguratorConfiguration;
use App\Dto\Configurator\ConfiguratorPriceResult;
use App\Entity\Configurator\Configurator;
use App\Entity\Order\ConfiguredOrderItem;
use App\Entity\Taxation\TaxCategory;
use App\Service\Configurator\ConfigurationHashGenerator;
use App\Service\Configurator\ConfiguredOrderItemSnapshotFactory;
use PHPUnit\Framework\TestCase;

final class CheckoutMetadataTest extends TestCase
{
    public function testConfiguratorCheckoutMetadataDefaultsAndCanChange(): void
    {
        $configurator = new Configurator('cards', 'Cards');
        self::assertTrue($configurator->isShippingRequired());
        self::assertNull($configurator->getTaxCategory());

        $category = new TaxCategory();
        $category->setCode('standard');
        $configurator->setShippingRequired(false);
        $configurator->setTaxCategory($category);
        self::assertFalse($configurator->isShippingRequired());
        self::assertSame($category, $configurator->getTaxCategory());

        $configurator->setTaxCategory(null);
        self::assertNull($configurator->getTaxCategory());
        self::assertFalse(method_exists(Configurator::class, 'getProduct'));
        self::assertFalse(method_exists(Configurator::class, 'getVariant'));
    }

    public function testFactorySnapshotsServerSideCheckoutMetadataIncludingNullCategory(): void
    {
        $configurator = new Configurator('cards', 'Cards');
        $configurator->setShippingRequired(false);
        $category = new TaxCategory();
        $category->setCode('printed-goods');
        $configurator->setTaxCategory($category);

        $item = $this->factory()->create($configurator, $this->configuration(), $this->price(), 'de_DE');
        self::assertSame('printed-goods', $item->getTaxCategoryCode());
        self::assertFalse($item->isShippingRequired());
        self::assertFalse(method_exists($item, 'getProduct'));
        self::assertFalse(method_exists($item, 'getVariant'));

        $configurator->setTaxCategory(null);
        self::assertNull($this->factory()->create($configurator, $this->configuration(), $this->price(), 'de_DE')->getTaxCategoryCode());
    }

    public function testReplacePricingCopiesCheckoutMetadataAndExistingSnapshots(): void
    {
        $original = $this->item('old-tax', true, 100, ['old' => true]);
        $fresh = $this->item('new-tax', false, 900, ['fresh' => true]);
        $original->replacePricing($fresh);

        self::assertSame('new-tax', $original->getTaxCategoryCode());
        self::assertFalse($original->isShippingRequired());
        self::assertSame(900, $original->getTotal());
        self::assertSame(['fresh' => true], $original->getSelectionsSnapshot());
        self::assertSame(['canonical' => 'fresh'], $original->getCanonicalConfiguration());
        self::assertSame('express', $original->getLeadTimeCode());
    }

    private function factory(): ConfiguredOrderItemSnapshotFactory
    {
        return new ConfiguredOrderItemSnapshotFactory(new ConfigurationHashGenerator());
    }

    private function configuration(): ConfiguratorConfiguration
    {
        return new ConfiguratorConfiguration('cards', 2, 'EUR', 'DE_WEB');
    }

    private function price(): ConfiguratorPriceResult
    {
        return new ConfiguratorPriceResult(2, 'EUR', 100, 20, 120, 240, 10, 5, 255, []);
    }

    /** @param array<string, mixed> $selections */
    private function item(?string $taxCode, bool $shippingRequired, int $total, array $selections): ConfiguredOrderItem
    {
        return new ConfiguredOrderItem('cards', 'Cards', 'de_DE', 'DE_WEB', 'EUR', 2, str_repeat('a', 64), $selections, ['price' => $total], ['canonical' => $selections === ['fresh' => true] ? 'fresh' : 'old'], 100, 20, 120, 240, 10, 5, $total, 'express', 'Express', 3, $taxCode, $shippingRequired);
    }
}
