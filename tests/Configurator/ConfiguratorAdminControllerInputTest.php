<?php

declare(strict_types=1);

namespace App\Tests\Configurator;

use App\Controller\Admin\ConfiguratorAdminController;
use App\Entity\Configurator\Configurator;
use App\Entity\Configurator\ConfiguratorPriceRule;
use App\Entity\Product\Product;
use App\Enum\Configurator\PriceType;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ConfiguratorAdminControllerInputTest extends TestCase
{
    private ConfiguratorAdminController $controller;

    protected function setUp(): void
    {
        $this->controller = new ConfiguratorAdminController();
    }

    #[DataProvider('emptyOptionalProductIds')]
    public function testEmptyProductIdClearsProductWithoutSymfonyInputException(array $parameters): void
    {
        $configurator = new Configurator('desk', 'Desk');
        $configurator->setProduct(new Product());
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('find');

        $this->invoke('applyConfigurator', $configurator, new Request([], $parameters), $em);

        self::assertNull($configurator->getProduct());
    }

    public static function emptyOptionalProductIds(): iterable
    {
        yield 'empty string regression for POST /admin/configurators/new' => [['product_id' => '']];
        yield 'missing parameter' => [[]];
    }

    public function testValidProductIdLoadsProduct(): void
    {
        $product = new Product();
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('find')->with(Product::class, 123)->willReturn($product);
        $configurator = new Configurator('desk', 'Desk');

        $this->invoke('applyConfigurator', $configurator, new Request([], ['product_id' => '123']), $em);

        self::assertSame($product, $configurator->getProduct());
    }

    #[DataProvider('invalidProductIds')]
    public function testInvalidProductIdIsRejectedAsDomainError(mixed $value): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Die Produkt-ID ist ungültig.');

        $this->invoke('applyConfigurator', new Configurator('desk', 'Desk'), new Request([], ['product_id' => $value]), $this->createMock(EntityManagerInterface::class));
    }

    public static function invalidProductIds(): iterable
    {
        yield 'text' => ['abc'];
        yield 'decimal' => ['1.5'];
        yield 'array' => [[]];
        yield 'negative' => ['-1'];
    }

    public function testMissingProductIsReportedClearly(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn(null);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Das gewählte Produkt existiert nicht.');

        $this->invoke('applyConfigurator', new Configurator('desk', 'Desk'), new Request([], ['product_id' => '999']), $em);
    }

    public function testEmptyOptionalPriceRuleIdsAreAccepted(): void
    {
        $rule = $this->rule();
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('find');

        $this->applyPriceRule($rule, $em, [
            'value_id' => '',
            'channel_id' => '',
            'multiplier_field_id' => '',
            'minimum_quantity' => '100',
        ]);

        self::assertNull($rule->getValue());
        self::assertNull($rule->getChannel());
        self::assertNull($rule->getMultiplierField());
        self::assertSame(100, $rule->getMinimumQuantity());
    }

    #[DataProvider('invalidOptionalPriceRuleIds')]
    public function testManipulatedOptionalPriceRuleIdsAreRejected(string $key, mixed $value, string $message): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage($message);

        $this->applyPriceRule($this->rule(), $this->createMock(EntityManagerInterface::class), [$key => $value]);
    }

    public static function invalidOptionalPriceRuleIds(): iterable
    {
        yield 'value' => ['value_id', 'abc', 'Die Wert-ID ist ungültig.'];
        yield 'channel' => ['channel_id', '1.5', 'Die Channel-ID ist ungültig.'];
        yield 'multiplier field' => ['multiplier_field_id', [], 'Die Multiplikatorfeld-ID ist ungültig.'];
    }

    #[DataProvider('invalidMinimumQuantities')]
    public function testInvalidMinimumQuantityIsRejectedClearly(mixed $value): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Die Mindestmenge muss eine ganze Zahl größer oder gleich 1 sein.');

        $this->applyPriceRule($this->rule(), $this->createMock(EntityManagerInterface::class), ['minimum_quantity' => $value]);
    }

    public static function invalidMinimumQuantities(): iterable
    {
        yield 'empty' => [''];
        yield 'text' => ['abc'];
    }

    private function rule(): ConfiguratorPriceRule
    {
        return new ConfiguratorPriceRule(new Configurator('desk', 'Desk'), 'EUR', 'base', PriceType::UNIT, 100);
    }

    /** @param array<string, mixed> $parameters */
    private function applyPriceRule(ConfiguratorPriceRule $rule, EntityManagerInterface $em, array $parameters): void
    {
        $this->invoke('applyPriceRule', $rule, new Request([], array_merge([
            'minimum_quantity' => '1',
            'multiplier_type' => 'none',
        ], $parameters)), $em);
    }

    private function invoke(string $method, mixed ...$arguments): mixed
    {
        $reflection = new \ReflectionMethod($this->controller, $method);

        return $reflection->invoke($this->controller, ...$arguments);
    }
}
