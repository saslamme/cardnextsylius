<?php

declare(strict_types=1);

namespace App\Tests\Grid\Mutator;

use App\Entity\Taxonomy\Taxon;
use App\Grid\Mutator\CardnextShopProductGridMutator;
use App\Service\ProductAttributeProfileService;
use App\Service\ProductFacetDefinitionService;
use App\Service\ProductFacetService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class CardnextShopProductGridMutatorTest extends TestCase
{
    public function testDirectChildInheritsItsParentsFacetProfile(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $profiles = new ProductAttributeProfileService($entityManager);
        $definitions = new ProductFacetDefinitionService($profiles);
        $mutator = new CardnextShopProductGridMutator(
            new RequestStack(),
            $entityManager,
            $definitions,
            new ProductFacetService($entityManager, $profiles),
            $this->createMock(ChannelContextInterface::class),
            new NullLogger(),
        );

        $parent = $this->createMock(Taxon::class);
        $parent->method('getCode')->willReturn('id_accessories');
        $child = $this->createMock(Taxon::class);
        $child->method('getCode')->willReturn('id_accessories_reels');
        $child->method('getParent')->willReturn($parent);

        self::assertSame('id_accessories', $mutator->resolveProfileCode($child));
    }
}
