<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Product\DeviceModelAlias;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class DeviceModelAliasType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('alias', TextType::class, ['label' => 'Alias']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => DeviceModelAlias::class]);
    }
}
