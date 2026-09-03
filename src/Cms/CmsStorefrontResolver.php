<?php

declare(strict_types=1);

namespace App\Cms;

use App\Entity\Cms\CmsRedirect;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/** Resolves the CMS portion of the public-path fallback chain. */
class CmsStorefrontResolver
{
    public function __construct(
        private readonly CmsPageResolver $pages,
        private readonly EntityManagerInterface $entityManager,
        private readonly ChannelContextInterface $channels,
        private readonly LocaleContextInterface $locales,
        private readonly Environment $twig,
    ) {
    }

    public function resolve(string $path): ?Response
    {
        $locale = $this->locales->getLocaleCode();
        $page = $this->pages->resolve($path);

        if ($page !== null) {
            return new Response($this->twig->render('shop/cms/show.html.twig', [
                'page' => $page,
                'translation' => $page->getTranslation($locale),
                'locale' => $locale,
            ]));
        }

        $source = CmsSlug::normalize($path);
        $redirect = $this->entityManager->getRepository(CmsRedirect::class)->findOneBy([
            'channel' => $this->channels->getChannel(),
            'locale' => $locale,
            'sourcePath' => $source,
        ]);

        if (!$redirect instanceof CmsRedirect) {
            return null;
        }

        $target = $redirect->getTargetPath() ?? $redirect->getTargetPage()?->getTranslation($locale)?->getSlug();

        // Requiring a different, published target prevents both direct redirect loops
        // and redirects into draft, disabled, or otherwise unavailable pages.
        if ($target === null || $target === $source || $this->pages->resolve($target) === null) {
            return null;
        }

        return new RedirectResponse('/'.$target, $redirect->getStatusCode());
    }
}
