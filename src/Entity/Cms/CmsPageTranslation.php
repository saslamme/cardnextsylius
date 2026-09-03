<?php
declare(strict_types=1);
namespace App\Entity\Cms;
use App\Cms\CmsSlug;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
#[ORM\Entity]
#[ORM\Table(name:'cardnext_cms_page_translation', uniqueConstraints:[new ORM\UniqueConstraint(name:'uniq_cms_page_locale', columns:['page_id','locale'])])]
class CmsPageTranslation {
 #[ORM\Id,ORM\GeneratedValue,ORM\Column] private ?int $id=null;
 #[ORM\ManyToOne(inversedBy:'translations')] #[ORM\JoinColumn(nullable:false,onDelete:'CASCADE')] private ?CmsPage $page=null;
 #[ORM\Column(length:12)] private string $locale=''; #[ORM\Column(length:255)] #[Assert\NotBlank] private string $title='';
 #[ORM\Column(length:255)] #[Assert\NotBlank] private string $slug=''; #[ORM\Column(type:Types::TEXT,nullable:true)] private ?string $lead=null;
 #[ORM\Column(name:'meta_title',length:255,nullable:true)] private ?string $metaTitle=null; #[ORM\Column(name:'meta_description',length:500,nullable:true)] private ?string $metaDescription=null;
 #[ORM\Column(name:'canonical_url',length:2048,nullable:true)] #[Assert\Url(protocols:['https'])] private ?string $canonicalUrl=null;
 #[ORM\Column(name:'robots_index')] private bool $robotsIndex=true; #[ORM\Column(name:'robots_follow')] private bool $robotsFollow=true;
 #[ORM\Column(name:'og_title',length:255,nullable:true)] private ?string $ogTitle=null; #[ORM\Column(name:'og_description',length:500,nullable:true)] private ?string $ogDescription=null;
 public function getId():?int{return $this->id;} public function getPage():?CmsPage{return $this->page;} public function setPage(CmsPage $v):void{$this->page=$v;}
 public function getLocale():string{return $this->locale;} public function setLocale(string $v):void{$this->locale=$v;} public function getTitle():string{return $this->title;} public function setTitle(string $v):void{$this->title=trim($v);}
 public function getSlug():string{return $this->slug;} public function setSlug(string $v):void{$this->slug=CmsSlug::normalize($v);}
 public function getLead():?string{return $this->lead;} public function setLead(?string $v):void{$this->lead=$v;} public function getMetaTitle():?string{return $this->metaTitle;} public function setMetaTitle(?string $v):void{$this->metaTitle=$v;}
 public function getMetaDescription():?string{return $this->metaDescription;} public function setMetaDescription(?string $v):void{$this->metaDescription=$v;} public function getCanonicalUrl():?string{return $this->canonicalUrl;} public function setCanonicalUrl(?string $v):void{$this->canonicalUrl=$v;}
 public function isRobotsIndex():bool{return $this->robotsIndex;} public function setRobotsIndex(bool $v):void{$this->robotsIndex=$v;} public function isRobotsFollow():bool{return $this->robotsFollow;} public function setRobotsFollow(bool $v):void{$this->robotsFollow=$v;}
 public function getOgTitle():?string{return $this->ogTitle;} public function setOgTitle(?string $v):void{$this->ogTitle=$v;} public function getOgDescription():?string{return $this->ogDescription;} public function setOgDescription(?string $v):void{$this->ogDescription=$v;}
}
