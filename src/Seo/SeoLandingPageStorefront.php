<?php

declare(strict_types=1);

namespace App\Seo;

use App\Cms\CmsSlug;
use App\Entity\Channel\Channel;
use App\Entity\Cms\CmsRedirect;
use App\Entity\Seo\SeoLandingPage;
use App\Repository\Seo\SeoLandingPageRepository;
use App\Service\ProductFacetDefinitionService;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class SeoLandingPageStorefront
{
    public function __construct(private SeoLandingPageRepository $pages, private EntityManagerInterface $entityManager, private ChannelContextInterface $channels, private LocaleContextInterface $locales, private ProductFacetDefinitionService $facets, #[Autowire(service: 'sylius.controller.product')] private ResourceController $products) {}

    public function resolve(Request $request, string $requestedPath): ?Response
    {
        $channel = $this->channels->getChannel();
        if (!$channel instanceof Channel) return null;
        try { $path = LandingPagePath::normalize($requestedPath); } catch (\InvalidArgumentException) { return null; }
        $locale = $this->locales->getLocaleCode();
        $page = $this->pages->findEnabled($channel, $locale, $path);
        if (!$page instanceof SeoLandingPage) return $this->redirect($channel, $locale, $path);
        $taxon = $page->getBaseTaxon();
        if ($taxon === null) return null;

        $criteria = $request->query->all('criteria');
        $definition = $page->getFilterDefinition();
        if (($definition['manufacturers'] ?? []) !== []) $criteria['manufacturer']['value'] = array_values($definition['manufacturers']);
        $profile = $this->facets->profileForTaxon($taxon);
        if ($profile !== null) foreach ($this->facets->forProfile($profile, $locale) as $facet) {
            $values = $definition['attributes'][$facet['attribute']] ?? [];
            if ($values !== []) $criteria[$facet['name']]['value'] = array_values($values);
        }
        $request->query->set('criteria', $criteria);
        $request->attributes->set('cardnext_taxon', $taxon);
        $request->attributes->set('cardnext_seo_landing_page', $page);
        $request->attributes->set('slug', (string) $taxon->getTranslation($locale)->getSlug());
        $request->attributes->set('_sylius', ['template' => '@SyliusShop/product/index.html.twig', 'grid' => 'sylius_shop_product']);

        return $this->products->indexAction($request);
    }

    private function redirect(Channel $channel, string $locale, string $path): ?Response
    {
        $redirect = $this->entityManager->getRepository(CmsRedirect::class)->findOneBy(['channel' => $channel, 'locale' => $locale, 'sourcePath' => CmsSlug::normalize($path)]);
        if (!$redirect instanceof CmsRedirect || $redirect->getTargetPath() === null) return null;
        $target = LandingPagePath::normalize($redirect->getTargetPath());
        if ($target === $path || $this->pages->findEnabled($channel, $locale, $target) === null) return null;
        return new RedirectResponse($target, 301);
    }

}
