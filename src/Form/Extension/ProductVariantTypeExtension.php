<?php

declare(strict_types=1);

namespace App\Form\Extension;

use Sylius\Bundle\ProductBundle\Form\Type\ProductVariantType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;

final class ProductVariantTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('manufacturerPartNumber', TextType::class, [
                'required' => false,
                'label' => 'Hersteller-Art.-Nr. / MPN',
                'help' => 'Konkrete Artikelnummer dieser kaufbaren Variante.',
                'attr' => [
                    'maxlength' => 128,
                    'autocomplete' => 'off',
                ],
            ])
            ->add('gtin', TextType::class, [
                'required' => false,
                'label' => 'GTIN / EAN / UPC',
                'help' => 'Globale Artikelnummer dieser Variante.',
                'attr' => [
                    'maxlength' => 64,
                    'autocomplete' => 'off',
                ],
            ])
            ->add('minimumOrderQuantity', IntegerType::class, [
                'required' => true,
                'empty_data' => '1',
                'label' => 'Mindestbestellmenge',
                'help' => 'Kleinste Menge, die bestellt werden kann.',
                'constraints' => [new GreaterThanOrEqual(1)],
                'attr' => ['min' => 1, 'step' => 1],
            ])
            ->add('orderIncrement', IntegerType::class, [
                'required' => true,
                'empty_data' => '1',
                'label' => 'Bestellschritt',
                'help' => 'Weitere Mengen müssen in diesem Schritt erhöht werden, z. B. 10, 20, 30.',
                'constraints' => [new GreaterThanOrEqual(1)],
                'attr' => ['min' => 1, 'step' => 1],
            ])
            ->add('packQuantity', IntegerType::class, [
                'required' => true,
                'empty_data' => '1',
                'label' => 'Verpackungseinheit',
                'help' => 'Anzahl physischer Einheiten je Verkaufspackung.',
                'constraints' => [new GreaterThanOrEqual(1)],
                'attr' => ['min' => 1, 'step' => 1],
            ]);
    }

    public static function getExtendedTypes(): iterable
    {
        return [ProductVariantType::class];
    }
}
