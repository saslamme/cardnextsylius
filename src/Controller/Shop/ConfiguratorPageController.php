<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Cms\CmsStorefrontResolver;
use App\Entity\Channel\Channel;
use App\Service\ConfiguratorPageResolver;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

final readonly class ConfiguratorPageController
{
    public function __construct(private ConfiguratorPageResolver $resolver, private ChannelContextInterface $channelContext, private LocaleContextInterface $localeContext, private Environment $twig, private CmsStorefrontResolver $cmsStorefrontResolver)
    {
    }

    public function __invoke(string $configuratorPath): Response
    {
        $channel = $this->channelContext->getChannel();
        if (!$channel instanceof Channel) {
            throw new NotFoundHttpException();
        }
        $result = $this->resolver->resolve($configuratorPath, $this->localeContext->getLocaleCode(), $channel);
        if ($result === null) {
            return $this->cmsStorefrontResolver->resolve($configuratorPath)
                ?? throw new NotFoundHttpException('No configurator or CMS page matches this localized path.');
        }

        return new Response($this->twig->render('shop/configurator/page.html.twig', ['configurator' => $result[0], 'translation' => $result[1]]));
    }
}
