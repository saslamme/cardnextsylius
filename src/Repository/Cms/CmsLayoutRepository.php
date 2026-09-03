<?php
declare(strict_types=1);
namespace App\Repository\Cms; use App\Entity\Cms\CmsLayout; use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository; use Doctrine\Persistence\ManagerRegistry; final class CmsLayoutRepository extends ServiceEntityRepository{public function __construct(ManagerRegistry $r){parent::__construct($r,CmsLayout::class);}}
