<?php

declare(strict_types=1);

namespace App\Tests\Configurator;

use App\Controller\Admin\ConfiguratorAdminController;
use App\Entity\Configurator\Configurator;
use App\Entity\Configurator\ConfiguratorField;
use App\Entity\Configurator\ConfiguratorPriceRule;
use App\Entity\Configurator\ConfiguratorSection;
use App\Enum\Configurator\FieldType;
use App\Enum\Configurator\MultiplierType;
use App\Enum\Configurator\PercentageBase;
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

    public function testConfiguratorAdminCannotAssignAProduct(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../src/Controller/Admin/ConfiguratorAdminController.php');
        $template = file_get_contents(__DIR__ . '/../../templates/admin/cardnext/configurator/form.html.twig');

        self::assertIsString($controller);
        self::assertIsString($template);
        self::assertStringNotContainsString('productSearch', $controller);
        self::assertStringNotContainsString('product_id', $controller);
        self::assertStringNotContainsString('product_id', $template);
        self::assertStringNotContainsString('product-search', $template);
    }

    public function testConfiguratorCreateBuildsStandaloneAggregate(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../src/Controller/Admin/ConfiguratorAdminController.php');

        self::assertIsString($controller);
        self::assertStringNotContainsString("redirectToRoute('sylius_admin_product_create')", $controller);
        self::assertStringContainsString("new Configurator($this->required", $controller);
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

    #[DataProvider('nonPercentagePriceTypes')]
    public function testNonPercentageRuleCanBeSavedWithoutPercentageBase(PriceType $priceType): void
    {
        $rule = $this->rule($priceType);

        $this->applyPriceRule($rule, $this->createMock(EntityManagerInterface::class));

        self::assertNull($rule->getPercentageBase());
    }

    public static function nonPercentagePriceTypes(): iterable
    {
        yield 'UNIT' => [PriceType::UNIT];
        yield 'FIXED' => [PriceType::FIXED];
    }

    public function testPercentageRuleRequiresPercentageBase(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('percentage_base ist erforderlich.');

        $this->applyPriceRule($this->rule(PriceType::PERCENT), $this->createMock(EntityManagerInterface::class));
    }

    public function testPercentageRuleAcceptsPercentageBase(): void
    {
        $rule = $this->rule(PriceType::PERCENT);

        $this->applyPriceRule($rule, $this->createMock(EntityManagerInterface::class), ['percentage_base' => 'subtotal']);

        self::assertSame(PercentageBase::SUBTOTAL, $rule->getPercentageBase());
    }

    public function testManipulatedUnitPercentageBaseIsRejected(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Eine Prozentbasis ist nur für prozentuale Regeln zulässig.');

        $this->applyPriceRule($this->rule(), $this->createMock(EntityManagerInterface::class), ['percentage_base' => 'base']);
    }

    public function testNoMultiplierDoesNotRequireMultiplierField(): void
    {
        $rule = $this->rule();

        $this->applyPriceRule($rule, $this->createMock(EntityManagerInterface::class));

        self::assertSame(MultiplierType::NONE, $rule->getMultiplierType());
        self::assertNull($rule->getMultiplierField());
    }

    public function testFieldValueMultiplierRequiresMultiplierField(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('FIELD_VALUE requires a multiplier field.');

        $this->applyPriceRule($this->rule(), $this->createMock(EntityManagerInterface::class), ['multiplier_type' => 'field_value']);
    }

    public function testFieldValueMultiplierAcceptsValidMultiplierField(): void
    {
        $rule = $this->rule();
        $field = $this->multiplierField($rule->getConfigurator());
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('find')->with(ConfiguratorField::class, 7)->willReturn($field);

        $this->applyPriceRule($rule, $em, ['multiplier_type' => 'field_value', 'multiplier_field_id' => '7']);

        self::assertSame(MultiplierType::FIELD_VALUE, $rule->getMultiplierType());
        self::assertSame($field, $rule->getMultiplierField());
    }

    public function testStaleMultiplierFieldIdIsRejectedAfterSwitchingToNone(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Ein Multiplikatorfeld ist nur für Feldwert-Multiplikatoren zulässig.');

        $this->applyPriceRule($this->rule(), $this->createMock(EntityManagerInterface::class), ['multiplier_field_id' => '7']);
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

    private function rule(PriceType $priceType = PriceType::UNIT): ConfiguratorPriceRule
    {
        return new ConfiguratorPriceRule(new Configurator('desk', 'Desk'), 'EUR', 'base', $priceType, 100);
    }

    private function multiplierField(Configurator $configurator): ConfiguratorField
    {
        $section = new ConfiguratorSection('dimensions', 'Dimensions');
        $configurator->addSection($section);
        $field = new ConfiguratorField('width', 'Width', FieldType::INTEGER);
        $section->addField($field);

        return $field;
    }

    /** @param array<string, mixed> $parameters */
    private function applyPriceRule(ConfiguratorPriceRule $rule, EntityManagerInterface $em, array $parameters = []): void
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
