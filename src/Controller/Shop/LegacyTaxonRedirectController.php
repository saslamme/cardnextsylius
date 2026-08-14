<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class LegacyTaxonRedirectController
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function __invoke(string $_locale, string $slug): RedirectResponse
    {
        return new RedirectResponse(
            $this->urlGenerator->generate('sylius_shop_product_index', [
                '_locale' => $_locale,
                'slug' => $slug,
            ]),
            RedirectResponse::HTTP_MOVED_PERMANENTLY,
        );
    }
}
