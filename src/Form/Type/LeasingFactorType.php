<?php
declare(strict_types=1);
namespace App\Form\Type;
use App\Entity\Leasing\LeasingFactor; use Symfony\Component\Form\AbstractType; use Symfony\Component\Form\Extension\Core\Type\{CheckboxType,IntegerType,NumberType,SubmitType}; use Symfony\Component\Form\FormBuilderInterface; use Symfony\Component\OptionsResolver\OptionsResolver;
final class LeasingFactorType extends AbstractType { public function buildForm(FormBuilderInterface $b,array $o):void{$b->add('durationMonths',IntegerType::class,['label'=>'cardnext.leasing.duration'])->add('factor',NumberType::class,['label'=>'cardnext.leasing.factor','scale'=>8,'html5'=>true])->add('active',CheckboxType::class,['label'=>'cardnext.leasing.active','required'=>false])->add('position',IntegerType::class,['label'=>'cardnext.leasing.position'])->add('save',SubmitType::class,['label'=>'cardnext.ui.save']);}public function configureOptions(OptionsResolver $r):void{$r->setDefaults(['data_class'=>LeasingFactor::class]);}}
