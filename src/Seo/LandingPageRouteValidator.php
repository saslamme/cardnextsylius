<?php

declare(strict_types=1);

namespace App\Seo;

use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RouterInterface;

final readonly class LandingPageRouteValidator
{
    private const ALLOWED_ROUTES = [
        'sylius_shop_product_index',
        'sylius_shop_product_show',
        'cardnext_shop_configurator_page',
    ];

    public function __construct(private RouterInterface $router)
    {
    }

    public function isAllowed(string $path): bool
    {
        $context = clone $this->router->getContext();
        $context->setMethod('GET');
        $matcher = new UrlMatcher($this->router->getRouteCollection(), $context);

        try {
            $matched = $matcher->match($path);
        } catch (ResourceNotFoundException | MethodNotAllowedException) {
            return false;
        }

        return in_array($matched['_route'] ?? '', self::ALLOWED_ROUTES, true);
    }
}
