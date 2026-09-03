<?php

declare(strict_types=1);

namespace App\Tests\Cms;

use App\Cms\CmsPageResolver;
use App\Cms\CmsStorefrontResolver;
use App\Entity\Channel\Channel;
use App\Entity\Cms\CmsPage;
use App\Entity\Cms\CmsPageTranslation;
use App\Entity\Cms\CmsRedirect;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Twig\Environment;

final class CmsStorefrontResolverTest extends TestCase
{
    public function testPublishedSingleSlugRendersTheCmsPageWithItsTranslationAndLocale(): void
    {
        $page = new CmsPage();
        $translation = new CmsPageTranslation();
        $translation->setLocale('de_DE');
        $translation->setSlug('support');
        $page->addTranslation($translation);

        $pages = $this->createMock(CmsPageResolver::class);
        $pages->expects(self::once())->method('resolve')->with('support')->willReturn($page);
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())->method('render')->with('shop/cms/show.html.twig', [
            'page' => $page,
            'translation' => $translation,
            'locale' => 'de_DE',
        ])->willReturn('CMS support');

        $response = $this->resolver($pages, null, $twig)->resolve('support');

        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('CMS support', $response->getContent());
    }

    public function testDraftOrUnknownCmsPageDoesNotResolvePublicly(): void
    {
        $pages = $this->createMock(CmsPageResolver::class);
        $pages->method('resolve')->with('draft')->willReturn(null);

        self::assertNull($this->resolver($pages)->resolve('draft'));
    }

    public function testCmsRedirectUsesCurrentChannelAndLocale(): void
    {
        $channel = new Channel();
        $redirect = new CmsRedirect();
        $redirect->setTargetPath('service/support');
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::once())->method('findOneBy')->with([
            'channel' => $channel,
            'locale' => 'de_DE',
            'sourcePath' => 'old-support',
        ])->willReturn($redirect);
        $pages = $this->createMock(CmsPageResolver::class);
        $pages->method('resolve')->willReturnMap([
            ['old-support', null],
            ['service/support', new CmsPage()],
        ]);

        $response = $this->resolver($pages, $repository, null, $channel)->resolve('old-support');

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/service/support', $response->getTargetUrl());
        self::assertSame(301, $response->getStatusCode());
    }

    public function testRedirectLoopDoesNotResolve(): void
    {
        $redirect = new CmsRedirect();
        $redirect->setTargetPath('support');
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($redirect);
        $pages = $this->createMock(CmsPageResolver::class);
        $pages->method('resolve')->willReturn(null);

        self::assertNull($this->resolver($pages, $repository)->resolve('/support'));
    }

    private function resolver(CmsPageResolver $pages, ?EntityRepository $repository = null, ?Environment $twig = null, ?Channel $channel = null): CmsStorefrontResolver
    {
        $repository ??= $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(CmsRedirect::class)->willReturn($repository);
        $channels = $this->createMock(ChannelContextInterface::class);
        $channels->method('getChannel')->willReturn($channel ?? new Channel());
        $locales = $this->createMock(LocaleContextInterface::class);
        $locales->method('getLocaleCode')->willReturn('de_DE');

        return new CmsStorefrontResolver($pages, $entityManager, $channels, $locales, $twig ?? $this->createStub(Environment::class));
    }
}
