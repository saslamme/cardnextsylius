<?php
declare(strict_types=1);
namespace App\Leasing;
use App\Entity\Leasing\LeasingConfiguration; use App\Entity\Product\ProductVariant;
final readonly class LeasingOffer
{
 /** @param list<array{factor:\App\Entity\Leasing\LeasingFactor,rate:int}> $rates */
 public function __construct(public ProductVariant $variant, public int $netPrice, public string $currencyCode, public LeasingConfiguration $configuration, public array $rates, public int $preferredIndex){}
}
