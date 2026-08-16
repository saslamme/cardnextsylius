<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Product\DeviceModel;
use App\Entity\Product\Manufacturer;
use App\Entity\Product\Product;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class DeviceModelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('manufacturer', EntityType::class, ['class' => Manufacturer::class, 'choice_label' => 'name', 'label' => 'Hersteller'])
            ->add('name', TextType::class, ['label' => 'Modellname'])
            ->add('code', TextType::class, ['label' => 'Code'])
            ->add('slug', TextType::class, ['label' => 'Slug'])
            ->add('deviceType', ChoiceType::class, ['label' => 'Gerätetyp', 'choices' => array_flip(DeviceModel::typeLabels())])
            ->add('status', ChoiceType::class, ['label' => 'Status', 'choices' => array_flip(DeviceModel::statusLabels())])
            ->add('linkedProduct', EntityType::class, ['class' => Product::class, 'choice_label' => static fn (Product $product): string => sprintf('%s — %s', $product->getCode(), $product->getName()), 'label' => 'Aktuelles Produkt', 'required' => false, 'placeholder' => 'Nicht verknüpft'])
            ->add('aliases', CollectionType::class, ['entry_type' => DeviceModelAliasType::class, 'label' => 'Aliase', 'allow_add' => true, 'allow_delete' => true, 'by_reference' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => DeviceModel::class]);
    }
}
