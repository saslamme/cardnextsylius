<?php
declare(strict_types=1);
namespace App\Entity\Cms;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
#[ORM\Entity] #[ORM\Table(name:'cardnext_cms_download_translation', uniqueConstraints:[new ORM\UniqueConstraint(name:'uniq_download_locale',columns:['download_id','locale'])])]
final class CmsDownloadTranslation { #[ORM\Id,ORM\GeneratedValue,ORM\Column] private ?int $id=null; #[ORM\ManyToOne(inversedBy:'translations')] #[ORM\JoinColumn(nullable:false,onDelete:'CASCADE')] private ?CmsDownload $download=null; #[ORM\Column(length:20)] private string $locale=''; #[ORM\Column(length:255)] #[Assert\NotBlank] private string $title=''; #[ORM\Column(type:'text',nullable:true)] private ?string $description=null; public function getId():?int{return $this->id;} public function getDownload():?CmsDownload{return $this->download;} public function setDownload(CmsDownload $v):void{$this->download=$v;} public function getLocale():string{return $this->locale;} public function setLocale(string $v):void{$this->locale=$v;} public function getTitle():string{return $this->title;} public function setTitle(string $v):void{$this->title=$v;} public function getDescription():?string{return $this->description;} public function setDescription(?string $v):void{$this->description=$v;} }
