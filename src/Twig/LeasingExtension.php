<?php
declare(strict_types=1);
namespace App\Twig;
use App\Entity\Product\Product; use App\Leasing\LeasingEligibilityChecker; use Twig\Extension\AbstractExtension; use Twig\TwigFunction;
final class LeasingExtension extends AbstractExtension
{ public function __construct(private LeasingEligibilityChecker $checker){} public function getFunctions():array{return [new TwigFunction('cardnext_leasing_offer',fn(Product $p)=>$this->checker->offer($p))];} }
