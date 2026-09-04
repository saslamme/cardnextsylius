<?php

declare(strict_types=1);

namespace App\Form\Cms\Block;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class LinkCardItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('icon', ChoiceType::class, [
                'label' => 'Icon',
                'required' => false,
                'choices' => [
                    'Support' => 'support',
                    'Download' => 'download',
                    'Dokumentation' => 'manual',
                    'Service / Reparatur' => 'service',
                    'Kontakt' => 'contact',
                    'Produkt' => 'product',
                ],
            ])
            ->add('title', TextType::class, ['label' => 'Titel'])
            ->add('text', TextareaType::class, ['label' => 'Text', 'required' => false, 'attr' => ['rows' => 3]])
            ->add('linkLabel', TextType::class, ['label' => 'Link-Text', 'required' => false])
            ->add('linkUrl', TextType::class, ['label' => 'Link-URL', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
