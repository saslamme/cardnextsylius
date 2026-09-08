<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Entity\Leasing\LeasingConfiguration;
use App\Entity\Product\Product;
use App\Leasing\LeasingEligibilityChecker;
use App\Leasing\LeasingInquiryManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LeasingInquiryController extends AbstractController
{
    /**
     * @param ProductRepositoryInterface<Product> $productRepository
     */
    #[Route('/leasing/anfrage/{code}', name: 'cardnext_shop_leasing_inquiry', methods: ['GET', 'POST'])]
    public function __invoke(
        string $code,
        Request $request,
        ProductRepositoryInterface $productRepository,
        EntityManagerInterface $entityManager,
        ChannelContextInterface $channels,
        LeasingEligibilityChecker $checker,
        LeasingInquiryManager $manager,
        LoggerInterface $logger,
    ): Response {
        $channel = $channels->getChannel();
        $product = $productRepository->findOneBy(['code' => $code]);

        if (!$product instanceof Product) {
            throw $this->createNotFoundException(sprintf('Product with code "%s" was not found.', $code));
        }

        $offer = $checker->offer($product, $channel);
        if ($offer === null) {
            $configuration = $entityManager->find(LeasingConfiguration::class, 1);
            $logger->warning('Leasing inquiry requested for an ineligible product.', [
                'product_code' => $product->getCode(),
                'product_id' => $product->getId(),
                'request_host' => $request->getHost(),
                'channel_code' => $channel->getCode(),
                'product_leasing_enabled' => $product->isLeasingEnabled(),
                'leasing_enabled' => $configuration?->isEnabled(),
                'minimum_net_amount' => $configuration?->getMinimumNetAmount(),
            ]);

            throw $this->createNotFoundException('Leasing is not available for this product.');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('leasing-'.$product->getCode(), (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $data = $request->request->all();
            if (!isset($data['consent'])) {
                $this->addFlash('error', 'cardnext.leasing.consent_required');
            } else {
                try {
                    $manager->create($product, $channel, (int) ($data['duration'] ?? 0), $data);
                    $this->addFlash('success', 'cardnext.leasing.inquiry_success');

                    return $this->redirectToRoute('cardnext_shop_leasing_inquiry', ['code' => $code]);
                } catch (\DomainException) {
                    $this->addFlash('error', 'cardnext.leasing.inquiry_invalid');
                }
            }
        }

        return $this->render('shop/leasing/inquiry.html.twig', ['product' => $product, 'offer' => $offer]);
    }
}
