<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Cms\CmsStorefrontResolver;
use App\Entity\Channel\Channel;
use App\Service\ConfiguratorPageResolver;
use App\Repository\Configurator\SavedConfiguratorConfigurationRepository;
use App\Seo\SeoLandingPageStorefront;
use Symfony\Component\HttpFoundation\Request;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

final readonly class ConfiguratorPageController
{
    public function __construct(private ConfiguratorPageResolver $resolver, private ChannelContextInterface $channelContext, private LocaleContextInterface $localeContext, private Environment $twig, private CmsStorefrontResolver $cmsStorefrontResolver, private SeoLandingPageStorefront $seoLandingPages, private SavedConfiguratorConfigurationRepository $savedConfigurations)
    {
    }

    public function __invoke(Request $request, string $configuratorPath): Response
    {
        $channel = $this->channelContext->getChannel();
        if (!$channel instanceof Channel) {
            throw new NotFoundHttpException();
        }
        $result = $this->resolver->resolve($configuratorPath, $this->localeContext->getLocaleCode(), $channel);
        if ($result === null) {
            return $this->seoLandingPages->resolve($request, $configuratorPath)
                ?? $this->cmsStorefrontResolver->resolve($configuratorPath)
                ?? throw new NotFoundHttpException('No configurator or CMS page matches this localized path.');
        }

        $initialConfiguration = null;
        $token = $request->query->get('config');
        if ($token !== null) {
            if (!is_string($token) || !preg_match('/^[A-Za-z0-9_-]{22,64}$/', $token)) {
                throw new NotFoundHttpException();
            }
            $saved = $this->savedConfigurations->findOneBy(['token' => $token]);
            if ($saved === null || $saved->getConfigurator() !== $result[0] || $saved->getChannel() !== $channel) {
                throw new NotFoundHttpException();
            }
            $initialConfiguration = $saved->configuration();
        }

        return new Response($this->twig->render('shop/configurator/page.html.twig', ['configurator' => $result[0], 'translation' => $result[1], 'initial_configuration' => $initialConfiguration]));
    }
}
