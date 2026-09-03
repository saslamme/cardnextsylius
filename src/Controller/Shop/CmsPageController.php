<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Cms\CmsStorefrontResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CmsPageController extends AbstractController
{
    public function __construct(private readonly CmsStorefrontResolver $resolver)
    {
    }

    #[Route('/{cmsPath}', name: 'cardnext_shop_cms_page', requirements: ['cmsPath' => '(?!admin(?:/|$)|api(?:/|$)|account(?:/|$)|cart(?:/|$)|checkout(?:/|$)|login$|register$|search(?:/|$)|configurator(?:/|$)|sitemap\.xml$).+'], methods: ['GET'], priority: -255)]
    public function __invoke(string $cmsPath): Response
    {
        return $this->resolver->resolve($cmsPath) ?? throw $this->createNotFoundException();
    }
}
