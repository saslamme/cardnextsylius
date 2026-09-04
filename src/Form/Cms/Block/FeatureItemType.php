<?php

declare(strict_types=1);

namespace App\Form\Cms\Block;

use App\Cms\CmsBlockRendererRegistry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class FeatureItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('icon', ChoiceType::class, [
                'label' => 'Icon',
                'required' => false,
                'placeholder' => 'Kein Icon',
                'choices' => array_combine(
                    ['Beratung', 'Versand', 'Support', 'Qualität', 'Geschäftskunden', 'Sicherheit', 'Lager / Verfügbarkeit', 'Technologie', 'Service', 'Garantie', 'International', 'Nachhaltigkeit'],
                    CmsBlockRendererRegistry::FEATURE_ICONS,
                ),
                'constraints' => [new Assert\Choice(CmsBlockRendererRegistry::FEATURE_ICONS)],
            ])
            ->add('title', TextType::class, ['label' => 'Titel', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 120)]])
            ->add('text', TextareaType::class, ['label' => 'Text', 'required' => false, 'attr' => ['rows' => 3], 'constraints' => [new Assert\Length(max: 500)]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
