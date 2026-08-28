<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Entity\Content\LegalPage;
use App\Repository\Content\LegalPageRepository;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LegalPageController extends AbstractController
{
    #[Route('/{_locale}/impressum', name: 'cardnext_shop_legal_imprint', methods: ['GET'], priority: 120)]
    public function imprint(string $_locale, LegalPageRepository $repository, ChannelContextInterface $channelContext): Response
    {
        return $this->show('imprint', $_locale, $repository, $channelContext);
    }

    #[Route('/{_locale}/datenschutz', name: 'cardnext_shop_legal_privacy', methods: ['GET'], priority: 120)]
    public function privacy(string $_locale, LegalPageRepository $repository, ChannelContextInterface $channelContext): Response
    {
        return $this->show('privacy', $_locale, $repository, $channelContext);
    }

    #[Route('/{_locale}/agb', name: 'cardnext_shop_legal_terms', methods: ['GET'], priority: 120)]
    public function terms(string $_locale, LegalPageRepository $repository, ChannelContextInterface $channelContext): Response
    {
        return $this->show('terms', $_locale, $repository, $channelContext);
    }

    private function show(
        string $code,
        string $localeCode,
        LegalPageRepository $repository,
        ChannelContextInterface $channelContext,
    ): Response {
        $page = $repository->findOneByTypeAndChannel($code, $channelContext->getChannel(), $localeCode);

        if (!$page instanceof LegalPage) {
            throw $this->createNotFoundException('Rechtstext wurde nicht gefunden.');
        }

        return $this->render('shop/legal/show.html.twig', ['page' => $page]);
    }
}
