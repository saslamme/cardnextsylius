<?php

declare(strict_types=1);

namespace App\Grid\Mutator;

use App\Entity\Taxonomy\Taxon;
use App\Service\ProductFacetDefinitionService;
use App\Service\ProductFacetService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Bundle\GridBundle\Builder\Filter\Filter;
use Sylius\Bundle\GridBundle\Builder\GridBuilderInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
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
        private ProductFacetService $facetValues,
        private ChannelContextInterface $channelContext,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(GridBuilderInterface $gridBuilder): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return;
        }
        $slug = $request->attributes->get('slug');

        if (!is_string($slug) || $slug === '') {
            return;
        }

        $resolved = $this->resolveTaxonAndProfile($slug, $request->getLocale());
        if ($resolved === null) {
            return;
        }
        [$taxon, $profileCode] = $resolved;

        try {
            $available = $this->facetValues->getFacets($taxon, $this->channelContext->getChannel(), $request, $profileCode);
        } catch (\Throwable $exception) {
            // Facets are an optional catalogue enhancement. A malformed legacy
            // attribute must never make an otherwise valid taxon page fail.
            $this->logger->warning('Could not build product facets for taxon.', [
                'taxon' => $taxon->getCode(),
                'profile' => $profileCode,
                'exception' => $exception,
            ]);

            return;
        }

        $manufacturerChoices = [];
        foreach ($available['manufacturer'] as $code => $manufacturer) {
            $manufacturerChoices[sprintf('%s (%d)', $manufacturer['label'], $manufacturer['count'])] = $code;
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

        foreach ($this->facets->forProfile($profileCode, $request->getLocale()) as $facet) {
            $counts = $available['attributes'][$facet['attribute']] ?? [];
            if ($counts === []) {
                continue;
            }
            $choices = [];
            foreach ($facet['choices'] as $label => $value) {
                if (isset($counts[$value])) {
                    $choices[sprintf('%s (%d)', $label, $counts[$value])] = $value;
                }
            }
            if ($choices === []) {
                continue;
            }
            if ($facet['type'] === 'boolean') {
                $gridBuilder->addFilter(
                    Filter::create($facet['name'], 'cardnext_attribute_boolean')
                        ->setLabel($facet['label'])
                        ->setTemplate('shop/grid/filter/accordion_choice.html.twig')
                        ->addOption('attribute_code', $facet['attribute'])
                        ->addFormOption('choices', $choices)
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
                    ->addFormOption('choices', $choices)
                    ->addFormOption('expanded', true)
                    ->addFormOption('multiple', true),
            );
        }
    }

    /** @return array{Taxon, string}|null */
    private function resolveTaxonAndProfile(string $slug, string $locale): ?array
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

        $profileCode = $this->resolveProfileCode($taxon);
        if ($profileCode !== null) {
            return [$taxon, $profileCode];
        }

        return null;
    }

    public function resolveProfileCode(Taxon $taxon): ?string
    {
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
