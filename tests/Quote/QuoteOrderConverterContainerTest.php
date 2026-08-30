<?php

declare(strict_types=1);

namespace App\Tests\Quote;

use App\Service\Quote\QuoteOrderConverter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class QuoteOrderConverterContainerTest extends KernelTestCase
{
    public function testConverterCanBeInstantiatedByTheContainer(): void
    {
        self::bootKernel();

        self::assertInstanceOf(
            QuoteOrderConverter::class,
            self::getContainer()->get(QuoteOrderConverter::class),
        );
    }

    public function testConverterHasNoCustomerFactoryOrRepositoryDependency(): void
    {
        $constructor = (new \ReflectionClass(QuoteOrderConverter::class))->getConstructor();

        self::assertNotNull($constructor);
        self::assertNotContains(
            'customerFactory',
            array_map(static fn (\ReflectionParameter $parameter): string => $parameter->getName(), $constructor->getParameters()),
        );
        self::assertNotContains(
            'customerRepository',
            array_map(static fn (\ReflectionParameter $parameter): string => $parameter->getName(), $constructor->getParameters()),
        );

        $services = (string) file_get_contents(__DIR__.'/../../config/services.yaml');
        self::assertStringNotContainsString("\$customerFactory: '@sylius.factory.customer'", $services);
        self::assertStringNotContainsString("\$customerRepository: '@sylius.repository.customer'", $services);
    }
}
