<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Product\ProductBundleItem;
use App\Entity\Product\ProductVariant;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProductBundleItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void { $builder->add('variant', EntityType::class, ['class' => ProductVariant::class, 'choice_label' => static fn (ProductVariant $v) => sprintf('%s — %s / MPN %s / GTIN %s', $v->getProduct()?->getName(), $v->getCode(), $v->getManufacturerPartNumber() ?? '–', $v->getGtin() ?? '–'), 'autocomplete' => true, 'label' => 'Produkt / Variante'])->add('quantity', IntegerType::class, ['label' => 'Menge', 'attr' => ['min' => 1]])->add('position', IntegerType::class)->add('enabled', CheckboxType::class, ['required' => false]); }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['data_class' => ProductBundleItem::class]); }
}
