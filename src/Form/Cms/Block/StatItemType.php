<?php

declare(strict_types=1);

namespace App\Form\Cms\Block;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class StatItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('value', TextType::class, ['label' => 'Wert', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 30)]])
            ->add('label', TextType::class, ['label' => 'Bezeichnung', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 120)]])
            ->add('description', TextType::class, ['label' => 'Beschreibung', 'required' => false, 'constraints' => [new Assert\Length(max: 250)]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
