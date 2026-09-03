<?php

declare(strict_types=1);

namespace App\Form\Cms;

use App\Cms\CmsBlockRendererRegistry;
use App\Entity\Cms\CmsBlock;
use App\Form\Cms\Block\FaqItemType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

final class CmsBlockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = array_combine(CmsBlockRendererRegistry::TYPES, CmsBlockRendererRegistry::TYPES);
        $builder->add('locale', ChoiceType::class, ['label' => 'Sprache', 'choices' => $options['locale_choices']])
            ->add('type', ChoiceType::class, ['label' => 'Blocktyp', 'choices' => $choices])
            ->add('position', IntegerType::class, ['label' => 'Position'])
            ->add('enabled', CheckboxType::class, ['label' => 'Aktiv', 'required' => false]);

        $configure = function ($form, string $type, array $configuration): void {
            foreach ($this->fields($type) as $name => [$fieldType, $fieldOptions]) {
                $form->add($name, $fieldType, $fieldOptions + ['mapped' => false, 'data' => $configuration[$name] ?? null]);
            }
        };
        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) use ($configure): void {
            $block = $event->getData();
            if ($block instanceof CmsBlock) {
                $configure($event->getForm(), $block->getType(), $block->getConfiguration());
            }
        });
        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event) use ($configure): void {
            $data = (array) $event->getData();
            $type = isset($data['type']) && is_string($data['type']) ? $data['type'] : 'rich_text';
            $configure($event->getForm(), $type, []);
        });
    }

    /** @return array<string, array{class-string, array<string, mixed>}> */
    private function fields(string $type): array
    {
        $text = fn (string $label, bool $required = false): array => [TextareaType::class, ['label' => $label, 'required' => $required, 'attr' => ['rows' => 5]]];
        $line = fn (string $label, bool $required = false): array => [TextType::class, ['label' => $label, 'required' => $required]];
        $button = ['buttonLabel' => $line('Button-Text'), 'buttonUrl' => $line('Button-URL')];
        $image = ['image' => [FileType::class, ['label' => 'Bild', 'required' => false, 'mapped' => false, 'constraints' => [new Image(maxSize: '5M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'])]]]];

        return match ($type) {
            'rich_text' => ['headline' => $line('Überschrift'), 'content' => $text('Inhalt', true)],
            'hero' => ['kicker' => $line('Kicker'), 'headline' => $line('Überschrift', true), 'text' => $text('Text')] + $image + $button,
            'image_text' => ['headline' => $line('Überschrift'), 'text' => $text('Text', true)] + $image + ['imagePosition' => [ChoiceType::class, ['label' => 'Bildposition', 'choices' => ['Links' => 'left', 'Rechts' => 'right']]]] + $button,
            'faq' => ['headline' => $line('Überschrift'), 'items' => [CollectionType::class, ['label' => 'FAQ-Einträge', 'entry_type' => FaqItemType::class, 'allow_add' => true, 'allow_delete' => true, 'by_reference' => false]]],
            'cta' => ['headline' => $line('Überschrift', true), 'text' => $text('Text')] + $button,
            default => [],
        };
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CmsBlock::class, 'locale_choices' => []]);
        $resolver->setAllowedTypes('locale_choices', 'array');
    }
}
