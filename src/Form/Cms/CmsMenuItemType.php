<?php

declare(strict_types=1);

namespace App\Form\Cms;

use App\Entity\Cms\CmsMenuItem;
use App\Entity\Cms\CmsPage;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Sylius\Bundle\ChannelBundle\Form\Type\ChannelChoiceType;

final class CmsMenuItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('label', TextType::class, ['label' => 'Bezeichnung'])
            ->add('channel', ChannelChoiceType::class, ['label' => 'Verkaufskanal'])
            ->add('locale', ChoiceType::class, ['label' => 'Sprache', 'choices' => $options['locale_choices']])
            ->add('parent', EntityType::class, ['class' => CmsMenuItem::class, 'choice_label' => 'label', 'label' => 'Übergeordneter Eintrag', 'required' => false, 'choices' => $options['parent_choices']])
            ->add('targetType', ChoiceType::class, ['label' => 'Zieltyp', 'choices' => ['CMS-Seite' => CmsMenuItem::PAGE, 'Route' => CmsMenuItem::ROUTE, 'URL' => CmsMenuItem::URL]])
            ->add('page', EntityType::class, ['class' => CmsPage::class, 'choice_label' => 'code', 'label' => 'CMS-Seite', 'required' => false])
            ->add('routeName', TextType::class, ['label' => 'Routenname', 'required' => false])
            ->add('routeParameters', TextType::class, ['label' => 'Routenparameter (JSON)', 'required' => false, 'help' => 'JSON-Objekt, z. B. {"slug":"kontakt"}.'])
            ->add('externalUrl', TextType::class, ['label' => 'URL', 'required' => false, 'help' => 'Relative URL oder http(s):// URL.'])
            ->add('position', IntegerType::class, ['label' => 'Position'])
            ->add('enabled', CheckboxType::class, ['label' => 'Aktiv', 'required' => false])
            ->add('openInNewTab', CheckboxType::class, ['label' => 'In neuem Tab öffnen', 'required' => false]);
        $builder->get('routeParameters')->addModelTransformer(new CallbackTransformer(
            static fn (array $value): string => $value === [] ? '' : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            static function (?string $value): array {
                if ($value === null || trim($value) === '') {
                    return [];
                }
                $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
                if (!is_array($decoded) || array_is_list($decoded)) {
                    throw new \UnexpectedValueException('Routenparameter müssen ein JSON-Objekt sein.');
                }
                return $decoded;
            },
        ));
        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $item = $event->getData();
            if (!$item instanceof CmsMenuItem) {
                return;
            }
            if ($item->getTargetType() !== CmsMenuItem::PAGE) {
                $item->setPage(null);
            }
            if ($item->getTargetType() !== CmsMenuItem::ROUTE) {
                $item->setRouteName(null);
                $item->setRouteParameters(null);
            }
            if ($item->getTargetType() !== CmsMenuItem::URL) {
                $item->setExternalUrl(null);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CmsMenuItem::class, 'locale_choices' => [], 'parent_choices' => []]);
        $resolver->setAllowedTypes('locale_choices', 'array');
        $resolver->setAllowedTypes('parent_choices', 'array');
    }
}
