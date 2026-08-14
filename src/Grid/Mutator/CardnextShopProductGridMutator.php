<?php

declare(strict_types=1);

namespace App\Grid\Mutator;

use App\Entity\Product\Manufacturer;
use App\Entity\Taxonomy\Taxon;
use App\Service\ProductFacetDefinitionService;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\GridBundle\Builder\Filter\Filter;
use Sylius\Bundle\GridBundle\Builder\GridBuilderInterface;
use Sylius\Component\Grid\Mutator\GridMutatorInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\RequestStack;

#[AutoconfigureTag('sylius.grid_mutator', [
    'grid' => 'sylius_shop_product',
    'priority' => 100,
])]
final readonly class CardnextShopProductGridMutator implements GridMutatorInterface
{
    public function __construct(
        private RequestStack $requestStack,
        private EntityManagerInterface $entityManager,
        private ProductFacetDefinitionService $facets,
    ) {
    }

    public function __invoke(GridBuilderInterface $gridBuilder): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $slug = $request?->attributes->get('slug');

        if (!is_string($slug) || $slug === '') {
            return;
        }

        $profileCode = $this->resolveProfileCode($slug, $request?->getLocale() ?: 'de_DE');
        if ($profileCode === null || !$this->facets->hasProfile($profileCode)) {
            return;
        }

        $manufacturerChoices = [];
        /** @var list<Manufacturer> $manufacturers */
        $manufacturers = $this->entityManager->getRepository(Manufacturer::class)->findBy(
            ['enabled' => true],
            ['position' => 'ASC', 'name' => 'ASC'],
        );

        foreach ($manufacturers as $manufacturer) {
            $manufacturerChoices[$manufacturer->getName()] = $manufacturer->getCode();
        }

        if ($manufacturerChoices !== []) {
            $gridBuilder->addFilter(
                Filter::create('manufacturer', 'cardnext_manufacturer')
                    ->setLabel('Hersteller')
                    ->setTemplate('shop/grid/filter/accordion_choice.html.twig')
                    ->addFormOption('choices', $manufacturerChoices)
                    ->addFormOption('expanded', true)
                    ->addFormOption('multiple', true),
            );
        }

        foreach ($this->facets->forProfile($profileCode) as $facet) {
            if ($facet['type'] === 'boolean') {
                $gridBuilder->addFilter(
                    Filter::create($facet['name'], 'cardnext_attribute_boolean')
                        ->setLabel($facet['label'])
                        ->setTemplate('shop/grid/filter/accordion_choice.html.twig')
                        ->addOption('attribute_code', $facet['attribute'])
                        ->addFormOption('choices', [
                            'Ja' => '1',
                            'Nein' => '0',
                        ])
                        ->addFormOption('expanded', true)
                        ->addFormOption('multiple', false),
                );

                continue;
            }

            $gridBuilder->addFilter(
                Filter::create($facet['name'], 'cardnext_attribute_select')
                    ->setLabel($facet['label'])
                    ->setTemplate('shop/grid/filter/accordion_choice.html.twig')
                    ->addOption('attribute_code', $facet['attribute'])
                    ->addFormOption('choices', $facet['choices'] ?? [])
                    ->addFormOption('expanded', true)
                    ->addFormOption('multiple', true),
            );
        }
    }

    private function resolveProfileCode(string $slug, string $locale): ?string
    {
        $taxon = $this->entityManager
            ->createQueryBuilder()
            ->select('taxon', 'translation')
            ->from(Taxon::class, 'taxon')
            ->innerJoin('taxon.translations', 'translation')
            ->andWhere('translation.slug = :slug')
            ->andWhere('translation.locale = :locale')
            ->setParameter('slug', $slug)
            ->setParameter('locale', $locale)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if (!$taxon instanceof Taxon) {
            return null;
        }

        do {
            $code = $taxon->getCode();
            if (is_string($code) && $this->facets->hasProfile($code)) {
                return $code;
            }

            $parent = $taxon->getParent();
            $taxon = $parent instanceof Taxon ? $parent : null;
        } while ($taxon !== null);

        return null;
    }
}
