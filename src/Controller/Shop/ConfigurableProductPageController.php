<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Entity\Channel\Channel;
use App\Service\ConfigurableProductPageResolver;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

final readonly class ConfigurableProductPageController
{
    public function __construct(
        private ConfigurableProductPageResolver $resolver,
        private ChannelContextInterface $channelContext,
        private LocaleContextInterface $localeContext,
        private Environment $twig,
    ) {
    }

    public function __invoke(string $configuratorPath): Response
    {
        $channel = $this->channelContext->getChannel();
        if (!$channel instanceof Channel) {
            throw new NotFoundHttpException();
        }

        $product = $this->resolver->resolve($configuratorPath, $this->localeContext->getLocaleCode(), $channel);
        if ($product === null) {
            throw new NotFoundHttpException('No configurable product page matches this localized path.');
        }

        return new Response($this->twig->render('shop/configurator/page.html.twig', ['product' => $product]));
    }
}
