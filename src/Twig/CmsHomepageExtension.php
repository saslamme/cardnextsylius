<?php
declare(strict_types=1);
namespace App\Twig;
use App\Cms\CmsHomepageResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
final class CmsHomepageExtension extends AbstractExtension
{
 public function __construct(private readonly CmsHomepageResolver $resolver){}
 public function getFunctions():array{return [new TwigFunction('cardnext_cms_homepage',$this->resolver->resolve(...))];}
}
