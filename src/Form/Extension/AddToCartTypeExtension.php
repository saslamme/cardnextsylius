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
    public function __construct(private readonly ProductMaintenanceOfferResolver $resolver)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $product = $options['product'] ?? null;
        if (!$product instanceof Product) {
            return;
        }
        $choices = [];
        foreach ($this->resolver->resolve($product) as $offer) {
            $choices[(string) $offer->variant->getCode()] = (string) $offer->variant->getId();
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
