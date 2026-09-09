<?php

declare(strict_types=1);

namespace App\Entity\Product;

use App\Validator\PublicSlugUnique;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\ProductTranslation as BaseProductTranslation;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_product_translation')]
#[PublicSlugUnique]
class ProductTranslation extends BaseProductTranslation
{
    #[ORM\Column(name: 'search_synonyms', type: 'text', nullable: true)]
    private ?string $searchSynonyms = null;

    public function getSearchSynonyms(): ?string
    {
        return $this->searchSynonyms;
    }

    public function setSearchSynonyms(?string $searchSynonyms): void
    {
        $searchSynonyms = $searchSynonyms !== null ? trim($searchSynonyms) : null;
        $this->searchSynonyms = $searchSynonyms !== '' ? $searchSynonyms : null;
    }
}
