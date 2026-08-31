<?php

declare(strict_types=1);

namespace App\Factory;

use Sylius\Component\Product\Factory\ProductFactoryInterface;
use Sylius\Component\Product\Model\ProductInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

// @phpstan-ignore missingType.generics
#[AsDecorator('sylius.factory.product')]
final class CardnextProductFactory implements ProductFactoryInterface
{
    // @phpstan-ignore missingType.generics
    public function __construct(#[AutowireDecorated] private readonly ProductFactoryInterface $inner)
    {
    }

    public function createNew(): ProductInterface
    {
        return $this->inner->createNew();
    }

    public function createWithVariant(): ProductInterface
    {
        return $this->inner->createWithVariant();
    }
}
