<?php

declare(strict_types=1);

namespace App\Form\Extension;

use App\Entity\Product\Product;
use App\Maintenance\ProductMaintenanceOfferResolver;
use App\Maintenance\WertgarantieVariantResolver;
use Sylius\Bundle\ShopBundle\Form\Type\AddToCartType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

final class AddToCartTypeExtension extends AbstractTypeExtension
{
    public function __construct(private readonly WertgarantieVariantResolver $wertgarantieResolver)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $product = $options['product'] ?? null;
        if (!$product instanceof Product) {
            return;
        }
        $maintenanceChoices = [];
        $warrantyChoices = [];
        foreach ($product->getAssociations() as $association) {
            if ($association->getType()?->getCode() !== ProductMaintenanceOfferResolver::ASSOCIATION_TYPE) {
                continue;
            }
            foreach ($association->getAssociatedProducts() as $addon) {
                if (!$addon instanceof Product || !$addon->isAddonOnly() || !$addon->isEnabled()) {
                    continue;
                }
                foreach ($addon->getEnabledVariants() as $variant) {
                    if ($this->wertgarantieResolver->isWertgarantie($addon)) {
                        $warrantyChoices[(string) $variant->getCode()] = (string) $variant->getId();
                    } else {
                        $maintenanceChoices[(string) $variant->getCode()] = (string) $variant->getId();
                    }
                }
            }
        }
        if ($maintenanceChoices !== []) {
            $builder->add('maintenanceVariant', ChoiceType::class, [
                'mapped' => false,
                'required' => false,
                'placeholder' => 'cardnext.maintenance.none_service',
                'choices' => $maintenanceChoices,
            ]);
        }
        if ($warrantyChoices !== []) {
            $builder->add('warrantyVariant', ChoiceType::class, [
                'mapped' => false,
                'required' => false,
                'placeholder' => 'cardnext.maintenance.none_warranty',
                'choices' => $warrantyChoices,
            ]);
        }
    }

    public static function getExtendedTypes(): iterable
    {
        return [AddToCartType::class];
    }
}
