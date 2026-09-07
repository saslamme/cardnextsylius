<?php
declare(strict_types=1);
namespace App\Form\Cms\Block;
use Symfony\Component\Form\AbstractType;use Symfony\Component\Form\Extension\Core\Type\TextType;use Symfony\Component\Form\FormBuilderInterface;use Symfony\Component\OptionsResolver\OptionsResolver;
final class HomepageCategoryItemType extends AbstractType
{
 public function buildForm(FormBuilderInterface $builder,array $options):void{$builder->add('taxonCode',TextType::class,['label'=>'Taxon-Code'])->add('image',TextType::class,['label'=>'Bild-/Icon-Pfad','required'=>false]);}
 public function configureOptions(OptionsResolver $resolver):void{$resolver->setDefaults(['data_class'=>null]);}
}
