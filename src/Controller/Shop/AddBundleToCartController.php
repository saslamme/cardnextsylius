<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Entity\Order\OrderItem;
use App\Entity\Product\ProductBundle;
use App\Entity\Product\ProductVariant;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Factory\CartItemFactoryInterface;
use Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class AddBundleToCartController extends AbstractController
{
    /** @param CartItemFactoryInterface<OrderItem> $cartItemFactory */
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly CartContextInterface $cartContext, private readonly ChannelContextInterface $channelContext, private readonly CartItemFactoryInterface $cartItemFactory, private readonly OrderItemQuantityModifierInterface $quantityModifier, private readonly AvailabilityCheckerInterface $availabilityChecker) {}

    #[Route('/bundle/{code}/add', name: 'cardnext_shop_bundle_add', methods: ['POST'])]
    public function __invoke(string $code, Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('bundle_add_'.$code, (string) $request->request->get('_token'))) throw $this->createAccessDeniedException();
        $bundle = $this->entityManager->getRepository(ProductBundle::class)->findOneBy(['code' => $code]);
        $channel = $this->channelContext->getChannel();
        $configuration = $bundle instanceof ProductBundle ? $bundle->configurationFor((string) $channel->getCode()) : null;
        if (!$bundle instanceof ProductBundle || !$bundle->isEnabled() || $configuration === null || !$configuration->isEnabled()) throw $this->createNotFoundException();
        $bundleQuantity = max(1, min(100, $request->request->getInt('quantity', 1)));
        $mainVariant = $this->entityManager->find(ProductVariant::class, $request->request->getInt('mainVariant'));
        if (!$mainVariant instanceof ProductVariant || $mainVariant->getProduct() !== $bundle->getMainProduct()) throw $this->createNotFoundException();
        if (!$bundle->getMainProduct()->getChannels()->contains($channel)) throw $this->createNotFoundException();
        $selected = [];
        foreach ($request->request->all('components') as $value) {
            if (is_int($value) || is_string($value)) $selected[(int) $value] = true;
        }
        $cart = $this->cartContext->getCart();
        $groupKey = self::uuid();
        $this->addItem($cart, $mainVariant, $bundleQuantity, $bundle, $groupKey, OrderItem::BUNDLE_ROLE_MAIN);
        foreach ($bundle->getItems() as $definition) {
            $variant = $definition->getVariant();
            if (!$definition->isEnabled() || !isset($selected[(int) $variant->getId()])) continue;
            $componentProduct = $variant->getProduct();
            if (!$componentProduct instanceof \App\Entity\Product\Product || !$componentProduct->getChannels()->contains($channel)) throw $this->createNotFoundException();
            $this->addItem($cart, $variant, $definition->getQuantity() * $bundleQuantity, $bundle, $groupKey, OrderItem::BUNDLE_ROLE_COMPONENT);
        }
        $this->entityManager->persist($cart);
        $this->entityManager->flush();
        $this->addFlash('success', 'sylius.cart.add_item');
        return $this->redirectToRoute('sylius_shop_cart_summary');
    }

    private function addItem(OrderInterface $cart, ProductVariant $variant, int $quantity, ProductBundle $bundle, string $key, string $role): void
    {
        if (!$variant->isEnabled() || !$variant->isValidOrderQuantity($quantity) || !$this->availabilityChecker->isStockSufficient($variant, $quantity)) throw new \DomainException('A bundle item is unavailable for the requested quantity.');
        $item = $this->cartItemFactory->createNew();
        $item->setVariant($variant); $item->setBundle($bundle); $item->setBundleGroupKey($key); $item->setBundleRole($role); $this->quantityModifier->modify($item, $quantity);
        $cart->addItem($item);
    }

    private static function uuid(): string { $hex = bin2hex(random_bytes(16)); return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-4'.substr($hex, 13, 3).'-a'.substr($hex, 17, 3).'-'.substr($hex, 20); }
}
