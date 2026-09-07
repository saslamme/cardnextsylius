<?php
declare(strict_types=1);
namespace App\Form\Cms\Block;
use Symfony\Component\Form\AbstractType;use Symfony\Component\Form\Extension\Core\Type\TextType;use Symfony\Component\Form\FormBuilderInterface;use Symfony\Component\OptionsResolver\OptionsResolver;
final class PromiseItemType extends AbstractType
{
 public function buildForm(FormBuilderInterface $builder,array $options):void{$builder->add('title',TextType::class,['label'=>'Titel'])->add('text',TextType::class,['label'=>'Text'])->add('icon',TextType::class,['label'=>'Icon (optional)','required'=>false]);}
 public function configureOptions(OptionsResolver $resolver):void{$resolver->setDefaults(['data_class'=>null]);}
}
