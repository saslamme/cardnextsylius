<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Entity\Order\ConfiguredOrderItem;
use App\Entity\Order\Order;
use App\Exception\Configurator\InvalidConfigurationException;
use App\Service\Configurator\ConfiguredCartItemFactory;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ConfiguredCartController extends AbstractController
{
    public function __construct(private readonly ConfiguredCartItemFactory $itemFactory, private readonly CartContextInterface $cartContext, private readonly EntityManagerInterface $em, private readonly OrderProcessorInterface $orderProcessor)
    {
    }

    #[Route('/configurators/{configuratorCode}/cart', name: 'cardnext_shop_configurator_cart_add', methods: ['POST'])]
    public function add(Request $request, string $configuratorCode): JsonResponse
    {
        if (!$this->isCsrfTokenValid('configured_cart_add_' . $configuratorCode, (string) $request->headers->get('X-CSRF-TOKEN'))) {
            return $this->json(['ok' => false, 'message' => 'Ungültiges Sicherheitstoken.'], Response::HTTP_FORBIDDEN);
        }

        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return $this->json(['ok' => false], 400);
        }
        try {
            $item = $this->itemFactory->create($configuratorCode, $payload);
        } catch (InvalidConfigurationException|\DomainException) {
            return $this->json(['ok' => false, 'message' => 'Die Konfiguration konnte nicht berechnet werden.'], 422);
        }
        $cart = $this->cartContext->getCart();
        if (!$cart instanceof Order) {
            return $this->json(['ok' => false], 500);
        }
        $cart->addConfiguredItem($item);
        $this->orderProcessor->process($cart);
        $this->em->persist($cart);
        $this->em->flush();

        return $this->json(['ok' => true, 'cartUrl' => $this->generateUrl('sylius_shop_cart_summary')]);
    }

    #[Route('/cart/configured-items/{id}/quantity', name: 'cardnext_shop_configured_item_quantity', methods: ['POST'])]
    public function quantity(Request $request, ConfiguredOrderItem $item): Response
    {
        $this->assertOwned($item);
        if (!$this->isCsrfTokenValid('configured_item_' . $item->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $canonical = $item->getCanonicalConfiguration();
        $canonical['quantity'] = $request->request->getInt('quantity');
        try {
            $fresh = $this->itemFactory->create($item->getConfiguratorCode(), $canonical);
        } catch (InvalidConfigurationException|\DomainException) {
            $this->addFlash('error', 'Die Konfiguration kann für diese Menge derzeit nicht berechnet werden.');
            return $this->redirectToRoute('sylius_shop_cart_summary');
        }
        $item->replacePricing($fresh);
        $this->orderProcessor->process($item->getOrder());
        $this->em->flush();

        return $this->redirectToRoute('sylius_shop_cart_summary');
    }

    #[Route('/cart/configured-items/{id}/remove', name: 'cardnext_shop_configured_item_remove', methods: ['POST'])]
    public function remove(Request $request, ConfiguredOrderItem $item): Response
    {
        $this->assertOwned($item);
        if (!$this->isCsrfTokenValid('configured_item_' . $item->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $order = $item->getOrder();
        $order->removeConfiguredItem($item);
        $this->orderProcessor->process($order);
        $this->em->flush();

        return $this->redirectToRoute('sylius_shop_cart_summary');
    }

    private function assertOwned(ConfiguredOrderItem $item): void
    {
        if ($item->getOrder() !== $this->cartContext->getCart()) {
            throw $this->createNotFoundException();
        }
    }
}
