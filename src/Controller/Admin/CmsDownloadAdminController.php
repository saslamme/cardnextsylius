<?php
declare(strict_types=1);
namespace App\Controller\Admin;
use App\Entity\Channel\Channel;
use App\Entity\Cms\CmsDownload;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
#[IsGranted(AdminUserInterface::DEFAULT_ADMIN_ROLE)]
final class CmsDownloadAdminController extends AbstractController { #[Route('/admin/cardnext/cms/downloads',name:'cardnext_admin_cms_downloads',methods:['GET'])] public function index(Request $request,EntityManagerInterface $em):Response{$qb=$em->getRepository(CmsDownload::class)->createQueryBuilder('d')->leftJoin('d.translations','t')->addSelect('t')->leftJoin('d.channels','c')->addSelect('c')->distinct()->orderBy('d.updatedAt','DESC');if($q=trim($request->query->getString('q')))$qb->andWhere('LOWER(t.title) LIKE :q OR LOWER(d.code) LIKE :q OR LOWER(d.manufacturer) LIKE :q')->setParameter('q','%'.strtolower($q).'%');foreach(['type'=>'d.type','channel'=>'c.code','enabled'=>'d.enabled','manufacturer'=>'d.manufacturer'] as $key=>$field)if(($value=$request->query->get($key))!==null&&$value!=='')$qb->andWhere($field.' = :'.$key)->setParameter($key,$value);return $this->render('admin/cardnext/cms/download/index.html.twig',['downloads'=>$qb->getQuery()->getResult(),'channels'=>$em->getRepository(Channel::class)->findBy([],['code'=>'ASC'])]);}}
