<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Entity\Content\LegalPage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LegalPageController extends AbstractController
{
    #[Route('/{_locale}/impressum', name: 'cardnext_shop_legal_imprint', methods: ['GET'])]
    public function imprint(string $_locale, EntityManagerInterface $entityManager): Response
    {
        return $this->show('imprint', $_locale, $entityManager);
    }

    #[Route('/{_locale}/datenschutz', name: 'cardnext_shop_legal_privacy', methods: ['GET'])]
    public function privacy(string $_locale, EntityManagerInterface $entityManager): Response
    {
        return $this->show('privacy', $_locale, $entityManager);
    }

    #[Route('/{_locale}/agb', name: 'cardnext_shop_legal_terms', methods: ['GET'])]
    public function terms(string $_locale, EntityManagerInterface $entityManager): Response
    {
        return $this->show('terms', $_locale, $entityManager);
    }

    private function show(string $code, string $localeCode, EntityManagerInterface $entityManager): Response
    {
        $repository = $entityManager->getRepository(LegalPage::class);

        $page = $repository->findOneBy(['code' => $code, 'localeCode' => $localeCode]);

        if (!$page instanceof LegalPage && $localeCode !== 'de_DE') {
            $page = $repository->findOneBy(['code' => $code, 'localeCode' => 'de_DE']);
        }

        if (!$page instanceof LegalPage) {
            throw $this->createNotFoundException('Rechtstext wurde nicht gefunden.');
        }

        return $this->render('shop/legal/show.html.twig', ['page' => $page]);
    }
}
