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
}
