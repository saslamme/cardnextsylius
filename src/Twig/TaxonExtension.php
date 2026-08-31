<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Taxonomy\Taxon;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class TaxonExtension extends AbstractExtension
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LocaleContextInterface $localeContext,
    ) {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('cardnext_taxon_by_code', $this->byCode(...))];
    }

    public function byCode(string $code): ?Taxon
    {
        $taxon = $this->entityManager->getRepository(Taxon::class)->findOneBy(['code' => $code]);
        if (!$taxon instanceof Taxon) {
            return null;
        }

        $taxon->setCurrentLocale($this->localeContext->getLocaleCode());

        return $taxon;
    }
}
