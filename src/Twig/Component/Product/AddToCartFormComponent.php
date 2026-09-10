<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Twig\Component\Product;

use App\Entity\Order\OrderItem as CardnextOrderItem;
use App\Entity\Product\Product as CardnextProduct;
use App\Entity\Product\ProductVariant as CardnextProductVariant;
use App\Maintenance\MaintenanceOffer;
use App\Maintenance\ProductMaintenanceOfferResolver;
use Doctrine\Persistence\ObjectManager;
use Sylius\Bundle\CoreBundle\Provider\FlashBagProvider;
use Sylius\Bundle\OrderBundle\Controller\AddToCartCommandInterface;
use Sylius\Bundle\OrderBundle\Factory\AddToCartCommandFactoryInterface;
use Sylius\Bundle\ShopBundle\Twig\Component\Product\Trait\ProductLivePropTrait;
use Sylius\Bundle\ShopBundle\Twig\Component\Product\Trait\ProductVariantLivePropTrait;
use Sylius\Bundle\UiBundle\Twig\Component\TemplatePropTrait;
use Sylius\Component\Core\Factory\CartItemFactoryInterface;
use Sylius\Component\Core\Model\OrderItem;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Core\Repository\ProductVariantRepositoryInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\SyliusCartEvents;
use Sylius\TwigHooks\LiveComponent\HookableLiveComponentTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\PreReRender;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\PostMount;
use Webmozart\Assert\Assert;

#[AsLiveComponent]
class AddToCartFormComponent
{
    use ComponentToolsTrait;
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use HookableLiveComponentTrait;
    use ProductLivePropTrait;
    use ProductVariantLivePropTrait;
    use TemplatePropTrait;

    public const SYLIUS_SHOP_VARIANT_CHANGED = 'sylius:shop:variant_changed';

    #[LiveProp]
    public string $routeName = 'sylius_shop_cart_summary';

    /** @var array<string, mixed> */
    #[LiveProp]
    public array $routeParameters = [];

    /**
     * @param CartItemFactoryInterface<OrderItem> $cartItemFactory
     * @param class-string $formClass
     * @param ProductRepositoryInterface<ProductInterface> $productRepository
     * @param ProductVariantRepositoryInterface<ProductVariantInterface> $productVariantRepository
     */
    public function __construct(
        protected readonly FormFactoryInterface $formFactory,
        protected readonly ObjectManager $manager,
        protected readonly RouterInterface $router,
        protected readonly RequestStack $requestStack,
        protected readonly EventDispatcherInterface $eventDispatcher,
        protected readonly CartContextInterface $cartContext,
        protected readonly AddToCartCommandFactoryInterface $addToCartCommandFactory,
        protected readonly CartItemFactoryInterface $cartItemFactory,
        protected readonly string $formClass,
        private readonly ProductMaintenanceOfferResolver $maintenanceResolver,
        private readonly OrderItemQuantityModifierInterface $quantityModifier,
        ProductRepositoryInterface $productRepository,
        ProductVariantRepositoryInterface $productVariantRepository,
    ) {
        $this->initializeProduct($productRepository);
        $this->initializeProductVariant($productVariantRepository);
    }

    #[PostMount(priority: 100)]
    public function postMount(): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $requestRoute = $request?->attributes->get('_route');

        // Keep the storefront destination in LiveProps. Reading app.request from the
        // template is unsafe: during a variant update the current request is the
        // /_components/* request, so the rerendered submit action would redirect to
        // the component endpoint instead of back to the product.
        if (is_string($requestRoute) && !str_starts_with($requestRoute, 'ux_live_component')) {
            $requestRouteParameters = $request->attributes->get('_route_params', []);
            $this->routeName = $requestRoute;
            $this->routeParameters = array_merge(
                is_array($requestRouteParameters) ? $requestRouteParameters : [],
                ['cnCart' => 'open'],
            );
        }

        $this->isValidated = true;
    }

    #[PreReRender(priority: -100)]
    public function variantChanged(): void
    {
        $addToCartCommand = $this->getForm()->getData();
        Assert::isInstanceOf($addToCartCommand, AddToCartCommandInterface::class);
        $cartItem = $addToCartCommand->getCartItem();
        Assert::isInstanceOf($cartItem, OrderItem::class);
        $newVariant = $cartItem->getVariant();
        if ($newVariant === $this->variant) {
            return;
        }
        $this->variant = $newVariant;

        $this->emit(self::SYLIUS_SHOP_VARIANT_CHANGED, ['variantId' => $this->variant?->getId()]);
    }

    /** @param array<string, mixed> $routeParameters */
    #[LiveAction]
    public function addToCart(
        #[LiveArg]
        ?string $routeName = null,
        #[LiveArg]
        array $routeParameters = [],
        #[LiveArg]
        ?string $idRouteParameter = null,
        #[LiveArg]
        bool $addFlashMessage = true,
    ): RedirectResponse {
        $this->submitForm();
        $addToCartCommand = $this->getForm()->getData();
        Assert::isInstanceOf($addToCartCommand, AddToCartCommandInterface::class);
        $mainCartItem = $addToCartCommand->getCartItem();
        Assert::isInstanceOf($mainCartItem, OrderItem::class);

        $maintenanceVariant = $this->resolveSelectedAddon($mainCartItem, 'maintenanceVariant', MaintenanceOffer::CATEGORY_SERVICE);
        $warrantyVariant = $this->resolveSelectedAddon($mainCartItem, 'warrantyVariant', MaintenanceOffer::CATEGORY_WARRANTY);

        $this->eventDispatcher->dispatch(new GenericEvent($addToCartCommand), SyliusCartEvents::CART_ITEM_ADD);
        $parent = null;
        foreach ($addToCartCommand->getCart()->getItems() as $item) {
            if ($item instanceof CardnextOrderItem && $item->getVariant() === $mainCartItem->getVariant() && !$item->isAddon()) {
                $parent = $item;
            }
        }
        if ($parent instanceof CardnextOrderItem) {
            $this->replaceAddon($addToCartCommand, $parent, $maintenanceVariant, CardnextOrderItem::ADDON_TYPE_MAINTENANCE);
            $this->replaceAddon($addToCartCommand, $parent, $warrantyVariant, CardnextOrderItem::ADDON_TYPE_WARRANTY);
        }

        $this->manager->persist($addToCartCommand->getCart());
        $this->manager->flush();

        if ($addFlashMessage) {
            FlashBagProvider::getFlashBag($this->requestStack)->add('success', 'sylius.cart.add_item');
        }

        if ($idRouteParameter !== null) {
            $routeParameters[$idRouteParameter] = $addToCartCommand->getCart()->getId();
        }

        return new RedirectResponse($this->router->generate(
            $routeName ?? $this->routeName,
            array_merge($this->routeParameters, $routeParameters),
        ));
    }

    private function resolveSelectedAddon(OrderItem $mainCartItem, string $field, string $category): ?CardnextProductVariant
    {
        $selected = $this->getForm()->has($field) ? $this->getForm()->get($field)->getData() : null;
        if ($selected === null || $selected === '') {
            return null;
        }
        $variant = $this->product instanceof CardnextProduct && (is_int($selected) || is_string($selected)) && $mainCartItem->getVariant() instanceof CardnextProductVariant
            ? $this->maintenanceResolver->findValidVariant($this->product, $mainCartItem->getVariant(), $selected, $category)
            : null;
        if (!$variant instanceof CardnextProductVariant) {
            throw new \DomainException(sprintf('The selected %s add-on is not available for this product.', $category));
        }

        return $variant;
    }

    private function replaceAddon(AddToCartCommandInterface $command, CardnextOrderItem $parent, ?CardnextProductVariant $variant, string $addonType): void
    {
        if ($variant === null) {
            return;
        }
        foreach ($command->getCart()->getItems() as $item) {
            if ($item instanceof CardnextOrderItem && $item->getParentItem() === $parent && $item->getAddonType() === $addonType) {
                $command->getCart()->removeItem($item);
            }
        }
        $addon = $this->cartItemFactory->createNew();
        if (!$addon instanceof CardnextOrderItem) {
            throw new \LogicException('The configured order item class must support add-ons.');
        }
        $addon->setVariant($variant);
        $addon->setParentItem($parent);
        $addon->setAddonType($addonType);
        $this->quantityModifier->modify($addon, $parent->getQuantity());
        $addonCommand = $this->addToCartCommandFactory->createWithCartAndCartItem($command->getCart(), $addon);
        $this->eventDispatcher->dispatch(new GenericEvent($addonCommand), SyliusCartEvents::CART_ITEM_ADD);
    }

    protected function instantiateForm(): FormInterface
    {
        if ($this->product === null) {
            throw new \LogicException('A product is required to instantiate the add-to-cart form.');
        }
        $addToCartCommand = $this->addToCartCommandFactory->createWithCartAndCartItem(
            $this->cartContext->getCart(),
            $this->cartItemFactory->createForProduct($this->product),
        );

        return $this->formFactory->create($this->formClass, $addToCartCommand, ['product' => $this->product]);
    }
}
