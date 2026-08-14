<?php

declare(strict_types=1);

namespace App\Entity\Taxonomy;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\Taxon as BaseTaxon;
use Sylius\Component\Taxonomy\Model\TaxonTranslationInterface;
use Sylius\Resource\Model\TranslationInterface;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_taxon')]
class Taxon extends BaseTaxon
{
    public function getBottomDescription(): ?string
    {
        $translation = $this->getTranslation();

        return $translation instanceof TaxonTranslation
            ? $translation->getBottomDescription()
            : null;
    }

    public function setBottomDescription(?string $bottomDescription): void
    {
        $translation = $this->getTranslation();

        if ($translation instanceof TaxonTranslation) {
            $translation->setBottomDescription($bottomDescription);
        }
    }

    public function getTranslation(?string $locale = null): TranslationInterface
    {
        return parent::getTranslation($locale);
    }

    protected function createTranslation(): TaxonTranslationInterface
    {
        return new TaxonTranslation();
    }
}
