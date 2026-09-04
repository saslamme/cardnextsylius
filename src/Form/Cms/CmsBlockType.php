<?php

declare(strict_types=1);

namespace App\Form\Cms;

use App\Cms\CmsBlockRendererRegistry;
use App\Entity\Cms\CmsBlock;
use App\Form\Cms\Block\FaqItemType;
use App\Form\Cms\Block\FeatureItemType;
use App\Form\Cms\Block\GalleryItemType;
use App\Form\Cms\Block\LinkCardItemType;
use App\Form\Cms\Block\StatItemType;
use App\Form\Cms\Block\TestimonialItemType;
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
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Image;

final class CmsBlockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = array_flip(CmsBlockRendererRegistry::TYPE_LABELS);
        $builder->add('locale', ChoiceType::class, ['label' => 'Sprache', 'choices' => $options['locale_choices']])
            ->add('type', ChoiceType::class, ['label' => 'Blocktyp', 'choices' => $choices])
            ->add('position', IntegerType::class, ['label' => 'Position'])
            ->add('enabled', CheckboxType::class, ['label' => 'Aktiv', 'required' => false]);

        $configure = function ($form, string $type, array $configuration, bool $useDefaults): void {
            foreach ($this->fields($type) as $name => [$fieldType, $fieldOptions]) {
                $default = $useDefaults && $type === 'product_slider' ? match ($name) {
                    'limit' => 8,
                    'showNavigation' => true,
                    default => null,
                } : ($useDefaults && $type === 'manufacturer_slider' ? match ($name) {
                    'limit' => 12,
                    'showNavigation', 'linkToManufacturer' => true,
                    default => null,
                } : ($useDefaults && $type === 'gallery' ? match ($name) {
                    'columns' => 3,
                    'showCaptions' => true,
                    default => null,
                } : ($useDefaults && in_array($type, ['features', 'stats', 'testimonials'], true) ? match ($name) {
                    'columns' => $type === 'testimonials' ? 3 : 4,
                    default => null,
                } : ($useDefaults && $type === 'video' ? match ($name) {
                    'aspectRatio' => '16:9',
                    'showControls', 'privacyMode' => true,
                    default => null,
                } : null))));
                $value = array_key_exists($name, $configuration) ? $configuration[$name] : $default;
                if ($type === 'gallery' && $name === 'items' && is_array($value)) {
                    $value = array_map(static function (mixed $item): mixed {
                        if (is_array($item) && isset($item['image']) && is_string($item['image'])) {
                            $item['existingImage'] = $item['image'];
                            unset($item['image']);
                        }

                        return $item;
                    }, $value);
                }
                $form->add($name, $fieldType, $fieldOptions + [
                    'mapped' => false,
                    'data' => $value,
                ]);
            }
        };
        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) use ($configure): void {
            $block = $event->getData();
            if ($block instanceof CmsBlock) {
                $configure($event->getForm(), $block->getType(), $block->getConfiguration(), true);
            }
        });
        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event) use ($configure): void {
            $data = (array) $event->getData();
            $type = isset($data['type']) && is_string($data['type']) ? $data['type'] : 'rich_text';
            $configure($event->getForm(), $type, [], false);
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
            'downloads' => ['headline' => $line('Überschrift'), 'text' => $text('Einleitung'), 'types' => [ChoiceType::class, ['label' => 'Downloadtypen', 'choices' => array_combine(\App\Entity\Cms\CmsDownload::TYPES, \App\Entity\Cms\CmsDownload::TYPES), 'multiple' => true, 'required' => false]], 'manufacturer' => $line('Hersteller'), 'limit' => [IntegerType::class, ['label' => 'Maximale Anzahl', 'required' => false]], 'showFilters' => [CheckboxType::class, ['label' => 'Filter anzeigen', 'required' => false]]],
            'link_cards' => [
                'headline' => $line('Überschrift'),
                'text' => $text('Einleitung'),
                'items' => [CollectionType::class, [
                    'label' => 'Karten',
                    'entry_type' => LinkCardItemType::class,
                    'allow_add' => true,
                    'allow_delete' => true,
                    'by_reference' => false,
                ]],
            ],
            'product_slider' => [
                'headline' => $line('Überschrift'),
                'text' => $text('Einleitung'),
                'productCodes' => [CmsProductSelectionType::class, [
                    'label' => 'Produkte',
                    'help' => 'Nach Produktname, Produktcode, Variantencode, Hersteller-Art.-Nr. oder GTIN suchen.',
                ]],
                'limit' => [IntegerType::class, ['label' => 'Maximale Anzahl', 'required' => false, 'attr' => ['min' => 1, 'max' => 24]]],
                'showNavigation' => [CheckboxType::class, ['label' => 'Slider-Navigation anzeigen', 'required' => false]],
            ],
            'video' => [
                'headline' => $line('Überschrift'),
                'text' => $text('Einleitung'),
                'provider' => [ChoiceType::class, ['label' => 'Video-Anbieter', 'choices' => ['YouTube' => 'youtube', 'Vimeo' => 'vimeo'], 'constraints' => [new Assert\NotBlank(), new Assert\Choice(['youtube', 'vimeo'])]]],
                'videoUrl' => [TextType::class, ['label' => 'Video-URL', 'constraints' => [new Assert\NotBlank(), new Assert\Url(protocols: ['https'])]]],
                'caption' => $line('Bildunterschrift'),
                'aspectRatio' => [ChoiceType::class, ['label' => 'Seitenverhältnis', 'choices' => ['16:9' => '16:9', '4:3' => '4:3', '1:1' => '1:1', '9:16' => '9:16'], 'constraints' => [new Assert\Choice(['16:9', '4:3', '1:1', '9:16'])]]],
                'showControls' => [CheckboxType::class, ['label' => 'Steuerung anzeigen', 'required' => false]],
                'privacyMode' => [CheckboxType::class, ['label' => 'Datenschutzmodus', 'required' => false]],
            ],
            'manufacturer_slider' => [
                'headline' => $line('Überschrift'),
                'text' => $text('Einleitung'),
                'manufacturerCodes' => [CmsManufacturerSelectionType::class, ['label' => 'Hersteller', 'help' => 'Nach Herstellername, Code oder Slug suchen.']],
                'limit' => [IntegerType::class, ['label' => 'Maximale Anzahl', 'required' => false, 'attr' => ['min' => 1, 'max' => 24]]],
                'showNavigation' => [CheckboxType::class, ['label' => 'Slider-Navigation anzeigen', 'required' => false]],
                'linkToManufacturer' => [CheckboxType::class, ['label' => 'Auf Herstellerseite verlinken', 'required' => false]],
            ],
            'gallery' => [
                'headline' => $line('Überschrift'),
                'text' => $text('Einleitung'),
                'columns' => [ChoiceType::class, ['label' => 'Spalten', 'choices' => ['2 Spalten' => 2, '3 Spalten' => 3, '4 Spalten' => 4]]],
                'showCaptions' => [CheckboxType::class, ['label' => 'Bildunterschriften anzeigen', 'required' => false]],
                'items' => [CollectionType::class, ['label' => 'Galeriebilder', 'entry_type' => GalleryItemType::class, 'allow_add' => true, 'allow_delete' => true, 'by_reference' => false]],
            ],
            'features' => [
                'headline' => $line('Überschrift'),
                'text' => $text('Einleitung'),
                'columns' => [ChoiceType::class, ['label' => 'Spalten', 'choices' => ['2 Spalten' => 2, '3 Spalten' => 3, '4 Spalten' => 4], 'constraints' => [new Assert\Choice([2, 3, 4])]]],
                'items' => [CollectionType::class, ['label' => 'Vorteile', 'entry_type' => FeatureItemType::class, 'allow_add' => true, 'allow_delete' => true, 'by_reference' => false, 'constraints' => [new Assert\Count(min: 1)]]],
            ],
            'stats' => [
                'headline' => $line('Überschrift'),
                'text' => $text('Einleitung'),
                'columns' => [ChoiceType::class, ['label' => 'Spalten', 'choices' => ['2 Spalten' => 2, '3 Spalten' => 3, '4 Spalten' => 4], 'constraints' => [new Assert\Choice([2, 3, 4])]]],
                'items' => [CollectionType::class, ['label' => 'Zahlen & Fakten', 'entry_type' => StatItemType::class, 'allow_add' => true, 'allow_delete' => true, 'by_reference' => false, 'constraints' => [new Assert\Count(min: 1)]]],
            ],
            'testimonials' => [
                'headline' => $line('Überschrift'),
                'text' => $text('Einleitung'),
                'columns' => [ChoiceType::class, ['label' => 'Spalten', 'choices' => ['1 Spalte' => 1, '2 Spalten' => 2, '3 Spalten' => 3], 'constraints' => [new Assert\Choice([1, 2, 3])]]],
                'items' => [CollectionType::class, ['label' => 'Kundenstimmen', 'entry_type' => TestimonialItemType::class, 'allow_add' => true, 'allow_delete' => true, 'by_reference' => false, 'constraints' => [new Assert\Count(min: 1)]]],
            ],
            default => [],
        };
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CmsBlock::class, 'locale_choices' => []]);
        $resolver->setAllowedTypes('locale_choices', 'array');
    }
}
