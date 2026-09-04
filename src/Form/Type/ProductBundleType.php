<?php
declare(strict_types=1);
namespace App\Form\Type;
use App\Entity\Product\ProductBundle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
final class ProductBundleType extends AbstractType { public function buildForm(FormBuilderInterface $b, array $o): void { $b->add('code', TextType::class)->add('name', TextType::class)->add('enabled', CheckboxType::class, ['required' => false, 'label' => 'Aktiv'])->add('position', IntegerType::class)->add('items', CollectionType::class, ['entry_type' => ProductBundleItemType::class, 'allow_add' => true, 'allow_delete' => true, 'by_reference' => false, 'prototype_name' => '__item__'])->add('channelConfigurations', CollectionType::class, ['entry_type' => ProductBundleChannelType::class, 'allow_add' => true, 'allow_delete' => true, 'by_reference' => false, 'prototype_name' => '__channel__']); } public function configureOptions(OptionsResolver $r): void { $r->setDefaults(['data_class' => ProductBundle::class]); } }
