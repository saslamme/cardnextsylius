<?php

declare(strict_types=1);

namespace App\Form\Cms;

use App\Cms\CmsBlockRendererRegistry;
use App\Entity\Cms\CmsLayout;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CmsLayoutType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $types = array_combine(CmsBlockRendererRegistry::TYPES, CmsBlockRendererRegistry::TYPES);
        $renderers = ['Standard' => 'standard', 'Breit' => 'wide', 'Landingpage' => 'landing', 'Service' => 'service'];
        $builder->add('code', TextType::class, ['label' => 'Code'])
            ->add('name', TextType::class, ['label' => 'Name'])
            ->add('renderer', ChoiceType::class, ['label' => 'Renderer', 'choices' => $renderers])
            ->add('enabled', CheckboxType::class, ['label' => 'Aktiv', 'required' => false])
            ->add('allowedBlockTypes', ChoiceType::class, ['label' => 'Erlaubte Blocktypen', 'choices' => $types, 'multiple' => true, 'expanded' => true, 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CmsLayout::class]);
    }
}
