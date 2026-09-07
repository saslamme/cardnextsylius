<?php
declare(strict_types=1);
namespace App\Form\Cms\Block;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
final class HomepageServiceItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder,array $options):void{$builder->add('icon',ChoiceType::class,['choices'=>['Beratung'=>'advice','Konfiguration'=>'configuration','Projektgeschäft'=>'projects','Verbrauchsmaterial'=>'consumables']])->add('title',TextType::class)->add('text',TextareaType::class)->add('linkUrl',TextType::class,['required'=>false]);}
    public function configureOptions(OptionsResolver $resolver):void{$resolver->setDefaults(['data_class'=>null]);}
}
