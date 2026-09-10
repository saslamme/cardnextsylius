<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Entity\Order\Order;
use App\Entity\Order\OrderItem;
use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use App\Maintenance\CartProtectionUpdater;
use App\Maintenance\MaintenanceOffer;
use App\Maintenance\ProductMaintenanceOfferResolver;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CartProtectionController extends AbstractController
{
    public function __construct(
        private readonly CartContextInterface $cartContext,
        private readonly ProductMaintenanceOfferResolver $resolver,
        private readonly CartProtectionUpdater $updater,
        private readonly OrderProcessorInterface $processor,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/cart/protection/{id}', name: 'cardnext_shop_cart_protection_options', requirements: ['id' => '\\d+'], methods: ['GET'], priority: 100)]
    public function options(int $id): Response
    {
        [$cart, $parent, $product, $variant] = $this->context($id);
        $offers = $this->resolver->resolve($product, $variant);
        $selected = $this->selectedTypes($cart, $parent);
        $service = array_values(array_filter($offers, static fn (MaintenanceOffer $offer): bool => $offer->category === MaintenanceOffer::CATEGORY_SERVICE));
        $warranty = array_values(array_filter($offers, static fn (MaintenanceOffer $offer): bool => $offer->category === MaintenanceOffer::CATEGORY_WARRANTY));
        if (($service === [] || $selected['maintenance']) && ($warranty === [] || $selected['warranty'])) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        return $this->render('shop/cart/protection_options.html.twig', [
            'parent' => $parent,
            'product' => $product,
            'serviceOffers' => $selected['maintenance'] ? [] : $service,
            'warrantyOffers' => $selected['warranty'] ? [] : $warranty,
        ]);
    }

    #[Route('/cart/protection/{id}', name: 'cardnext_shop_cart_protection_update', requirements: ['id' => '\\d+'], methods: ['POST'], priority: 100)]
    public function update(Request $request, int $id): Response
    {
        [$cart, $parent, $product, $mainVariant] = $this->context($id);
        if (!$this->isCsrfTokenValid('cart_protection_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        foreach ([
            'maintenanceVariant' => [MaintenanceOffer::CATEGORY_SERVICE, 'updateMaintenance'],
            'warrantyVariant' => [MaintenanceOffer::CATEGORY_WARRANTY, 'updateWarranty'],
        ] as $field => [$category, $method]) {
            if (!$request->request->has($field)) {
                continue;
            }
            $value = (string) $request->request->get($field);
            $addon = $value === '' ? null : $this->resolver->findValidVariant($product, $mainVariant, $value, $category);
            if ($value !== '' && !$addon instanceof ProductVariant) {
                return new Response('Invalid protection option.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $this->updater->{$method}($cart, $parent, $addon);
        }
        $this->processor->process($cart);
        $this->em->persist($cart);
        $this->em->flush();

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /** @return array{Order, OrderItem, Product, ProductVariant} */
    private function context(int $id): array
    {
        $cart = $this->cartContext->getCart();
        if (!$cart instanceof Order) {
            throw $this->createNotFoundException();
        }
        $parent = null;
        foreach ($cart->getItems() as $item) {
            if ($item instanceof OrderItem && $item->getId() === $id) {
                $parent = $item;

                break;
            }
        }
        $variant = $parent?->getVariant();
        $product = $variant?->getProduct();
        if (!$parent instanceof OrderItem || $parent->isAddon() || !$variant instanceof ProductVariant || !$product instanceof Product || $product->isAddonOnly()) {
            throw $this->createNotFoundException();
        }

        return [$cart, $parent, $product, $variant];
    }

    /** @return array{maintenance: bool, warranty: bool} */
    private function selectedTypes(Order $cart, OrderItem $parent): array
    {
        $selected = ['maintenance' => false, 'warranty' => false];
        foreach ($cart->getItems() as $item) {
            if ($item instanceof OrderItem && $item->getParentItem() === $parent && isset($selected[(string) $item->getAddonType()])) {
                $selected[(string) $item->getAddonType()] = true;
            }
        }

        return $selected;
    }
}
