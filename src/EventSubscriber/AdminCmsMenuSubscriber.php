<?php
declare(strict_types=1);
namespace App\EventSubscriber;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent; use Symfony\Component\EventDispatcher\EventSubscriberInterface;
final class AdminCmsMenuSubscriber implements EventSubscriberInterface { public static function getSubscribedEvents():array{return['sylius.menu.admin.main'=>'addCms'];} public function addCms(MenuBuilderEvent $event):void{$root=$event->getMenu();$content=$root->getChild('cardnext_content')??$root->addChild('cardnext_content')->setLabel('Inhalte')->setLabelAttribute('icon','tabler:file-text')->setExtra('always_open',true);foreach(['pages'=>['CMS-Seiten','tabler:file'],'menus'=>['Navigation','tabler:menu-2'],'layouts'=>['Layouts','tabler:layout'],'downloads'=>['Downloads','tabler:download']] as $key=>[$label,$icon])if(!$content->getChild('cardnext_cms_'.$key))$content->addChild('cardnext_cms_'.$key,['route'=>'cardnext_admin_cms_'.$key])->setLabel($label)->setLabelAttribute('icon',$icon);} }
