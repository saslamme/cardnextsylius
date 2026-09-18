<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Exception\Configurator\InvalidConfigurationException;
use App\Service\Configurator\ConfiguredCartItemFactory;
use App\Service\Quote\ConfiguredQuoteSnapshotFactory;
use App\Service\Quote\QuoteCartService;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ConfiguredQuoteCartController extends AbstractController
{
    public function __construct(private ConfiguredCartItemFactory $items, private ConfiguredQuoteSnapshotFactory $snapshots, private QuoteCartService $cart, private ChannelContextInterface $channels) {}

    #[Route('/configurators/{configuratorCode}/quote-cart', name: 'cardnext_shop_configurator_quote_add', methods: ['POST'])]
    public function add(Request $request, string $configuratorCode): JsonResponse
    {
        if (!$this->isCsrfTokenValid('configured_quote_add_' . $configuratorCode, (string) $request->headers->get('X-CSRF-TOKEN'))) { return $this->json(['ok' => false], Response::HTTP_FORBIDDEN); }
        try { $item = $this->items->create($configuratorCode, $request->toArray()); $this->cart->addConfigured($item, $this->channel()); }
        catch (InvalidConfigurationException|\DomainException|\JsonException) { return $this->json(['ok' => false, 'message' => 'cardnext.configurator.quote.error'], Response::HTTP_UNPROCESSABLE_ENTITY); }
        return $this->json(['ok' => true, 'quoteCartUrl' => $this->generateUrl('cardnext_shop_quote_cart')]);
    }

    #[Route('/angebotskorb/konfiguration/{key}/aktualisieren', name: 'cardnext_shop_quote_configured_update', methods: ['POST'])]
    public function update(Request $request, string $key): Response
    {
        if (!$this->isCsrfTokenValid('quote_configured_' . $key, $request->request->getString('_token'))) { throw $this->createAccessDeniedException(); }
        $cart = $this->cart->cart($this->channel()); $snapshot = $cart['configuredItems'][$key]['snapshot'] ?? null;
        if (!is_array($snapshot)) { throw $this->createNotFoundException(); }
        $payload = (array) ($snapshot['canonicalConfiguration'] ?? []); $payload['quantity'] = $request->request->getInt('quantity');
        try { $fresh = $this->items->create((string) $snapshot['configuratorCode'], $payload); $this->cart->replaceConfigured($key, $this->snapshots->createSnapshot($fresh), $this->channel()); }
        catch (InvalidConfigurationException|\DomainException) { $this->addFlash('error', 'cardnext.quote.configuration_update_error'); }
        return $this->redirectToRoute('cardnext_shop_quote_cart');
    }

    #[Route('/angebotskorb/konfiguration/{key}/entfernen', name: 'cardnext_shop_quote_configured_remove', methods: ['POST'])]
    public function remove(Request $request, string $key): Response
    {
        if (!$this->isCsrfTokenValid('quote_configured_' . $key, $request->request->getString('_token'))) { throw $this->createAccessDeniedException(); }
        $this->cart->removeConfigured($key, $this->channel()); return $this->redirectToRoute('cardnext_shop_quote_cart');
    }

    private function channel(): ChannelInterface
    {
        $channel = $this->channels->getChannel(); if (!$channel instanceof ChannelInterface) { throw new \LogicException('A core channel is required.'); } return $channel;
    }
}
