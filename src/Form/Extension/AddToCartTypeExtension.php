<?php

declare(strict_types=1);

namespace App\Form\Extension;

use App\Entity\Product\Product;
use App\Maintenance\ProductMaintenanceOfferResolver;
use Sylius\Bundle\ShopBundle\Form\Type\AddToCartType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

final class AddToCartTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $product = $options['product'] ?? null;
        if (!$product instanceof Product) {
            return;
        }
        $choices = [];
        foreach ($product->getAssociations() as $association) {
            if ($association->getType()?->getCode() !== ProductMaintenanceOfferResolver::ASSOCIATION_TYPE) {
                continue;
            }
            foreach ($association->getAssociatedProducts() as $addon) {
                if (!$addon instanceof Product || !$addon->isAddonOnly() || !$addon->isEnabled()) {
                    continue;
                }
                foreach ($addon->getEnabledVariants() as $variant) {
                    $choices[(string) $variant->getCode()] = (string) $variant->getId();
                }
            }
        }
        if ($choices !== []) {
            $builder->add('maintenanceVariant', ChoiceType::class, [
                'mapped' => false,
                'required' => false,
                'placeholder' => 'cardnext.maintenance.none',
                'choices' => $choices,
            ]);
        }
    }

    public static function getExtendedTypes(): iterable
    {
        return [AddToCartType::class];
    }
}
