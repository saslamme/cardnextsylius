<?php

declare(strict_types=1);

namespace App\Entity\Taxonomy;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Taxonomy\Model\TaxonTranslation as BaseTaxonTranslation;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_taxon_translation')]
class TaxonTranslation extends BaseTaxonTranslation
{
    #[ORM\Column(name: 'bottom_description', type: 'text', nullable: true)]
    private ?string $bottomDescription = null;

    public function getBottomDescription(): ?string
    {
        return $this->bottomDescription;
    }

    public function setBottomDescription(?string $bottomDescription): void
    {
        $this->bottomDescription = $bottomDescription;
    }
}
