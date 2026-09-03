<?php

declare(strict_types=1);

namespace App\Entity\Cms;

use App\Entity\Channel\Channel;
use App\Entity\Product\Product;
use App\Repository\Cms\CmsDownloadRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: CmsDownloadRepository::class)]
#[ORM\Table(name: 'cardnext_cms_download')]
#[ORM\Index(columns: ['enabled'], name: 'idx_cms_download_enabled')]
#[ORM\Index(columns: ['published_at'], name: 'idx_cms_download_published')]
#[ORM\Index(columns: ['type'], name: 'idx_cms_download_type')]
#[ORM\Index(columns: ['manufacturer'], name: 'idx_cms_download_manufacturer')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['code'])]
final class CmsDownload
{
    public const TYPES = ['manual', 'datasheet', 'driver', 'software', 'firmware', 'certificate', 'other'];
    public const OPERATING_SYSTEMS = ['windows_11', 'windows_10', 'windows_server', 'macos', 'linux', 'other'];

    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\Column(length: 64, unique: true)] #[Assert\Regex('/^[a-z0-9_\-]+$/')] private string $code = '';
    #[ORM\Column(length: 24)] #[Assert\Choice(choices: self::TYPES)] private string $type = 'manual';
    #[ORM\Column(length: 150)] #[Assert\NotBlank] private string $manufacturer = '';
    #[ORM\Column(name: 'product_family', length: 150, nullable: true)] private ?string $productFamily = null;
    #[ORM\Column(length: 80, nullable: true)] private ?string $version = null;
    /** @var list<string>|null */
    #[ORM\Column(name: 'operating_systems', type: Types::JSON, nullable: true)] private ?array $operatingSystems = null;
    #[ORM\Column(name: 'file_path', length: 255, nullable: true)] private ?string $filePath = null;
    #[ORM\Column(name: 'external_url', length: 2048, nullable: true)] private ?string $externalUrl = null;
    #[ORM\Column(name: 'original_filename', length: 255, nullable: true)] private ?string $originalFilename = null;
    #[ORM\Column(name: 'mime_type', length: 120, nullable: true)] private ?string $mimeType = null;
    #[ORM\Column(name: 'file_size', nullable: true)] private ?int $fileSize = null;
    #[ORM\Column] private bool $enabled = true;
    #[ORM\Column] private int $position = 100;
    #[ORM\Column(name: 'published_at', type: Types::DATETIME_IMMUTABLE, nullable: true)] private ?\DateTimeImmutable $publishedAt = null;
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)] private \DateTimeImmutable $createdAt;
    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)] private \DateTimeImmutable $updatedAt;
    /** @var Collection<int, Channel> */
    #[ORM\ManyToMany(targetEntity: Channel::class)]
    #[ORM\JoinTable(name: 'cardnext_cms_download_channel')]
    #[ORM\JoinColumn(name: 'cms_download_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'channel_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $channels;
    /** @var Collection<int, Product> */
    #[ORM\ManyToMany(targetEntity: Product::class)]
    #[ORM\JoinTable(name: 'cardnext_cms_download_product')]
    #[ORM\JoinColumn(name: 'cms_download_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'product_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $products;
    /** @var Collection<int, CmsDownloadTranslation> */
    #[ORM\OneToMany(mappedBy: 'download', targetEntity: CmsDownloadTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)] private Collection $translations;

    public function __construct() { $this->channels=new ArrayCollection(); $this->products=new ArrayCollection(); $this->translations=new ArrayCollection(); $this->createdAt=$this->updatedAt=new \DateTimeImmutable(); }
    public function getId(): ?int{return $this->id;} public function getCode():string{return $this->code;} public function setCode(string $v):void{$this->code=strtolower(trim($v));}
    public function getType():string{return $this->type;} public function setType(string $v):void{$this->type=$v;} public function getManufacturer():string{return $this->manufacturer;} public function setManufacturer(string $v):void{$this->manufacturer=trim($v);}
    public function getProductFamily():?string{return $this->productFamily;} public function setProductFamily(?string $v):void{$this->productFamily=$v;} public function getVersion():?string{return $this->version;} public function setVersion(?string $v):void{$this->version=$v;}
    public function getOperatingSystems():?array{return $this->operatingSystems;} public function setOperatingSystems(?array $v):void{$this->operatingSystems=$v;}
    public function getFilePath():?string{return $this->filePath;} public function setFilePath(?string $v):void{$this->filePath=$v;} public function getExternalUrl():?string{return $this->externalUrl;} public function setExternalUrl(?string $v):void{$this->externalUrl=$v?:null;}
    public function getOriginalFilename():?string{return $this->originalFilename;} public function setOriginalFilename(?string $v):void{$this->originalFilename=$v;} public function getMimeType():?string{return $this->mimeType;} public function setMimeType(?string $v):void{$this->mimeType=$v;} public function getFileSize():?int{return $this->fileSize;} public function setFileSize(?int $v):void{$this->fileSize=$v;}
    public function isEnabled():bool{return $this->enabled;} public function setEnabled(bool $v):void{$this->enabled=$v;} public function getPosition():int{return $this->position;} public function setPosition(int $v):void{$this->position=$v;} public function getPublishedAt():?\DateTimeImmutable{return $this->publishedAt;} public function setPublishedAt(?\DateTimeImmutable $v):void{$this->publishedAt=$v;} public function getUpdatedAt():\DateTimeImmutable{return $this->updatedAt;}
    public function getChannels():Collection{return $this->channels;} public function addChannel(Channel $v):void{if(!$this->channels->contains($v))$this->channels->add($v);} public function removeChannel(Channel $v):void{$this->channels->removeElement($v);}
    public function getProducts():Collection{return $this->products;} public function addProduct(Product $v):void{if(!$this->products->contains($v))$this->products->add($v);} public function removeProduct(Product $v):void{$this->products->removeElement($v);}
    public function getTranslations():Collection{return $this->translations;} public function addTranslation(CmsDownloadTranslation $v):void{if(!$this->translations->contains($v)){$this->translations->add($v);$v->setDownload($this);}} public function removeTranslation(CmsDownloadTranslation $v):void{$this->translations->removeElement($v);} public function getTranslation(string $locale):?CmsDownloadTranslation{foreach($this->translations as $t)if($t->getLocale()===$locale)return $t;return null;}
    public function isPublished(?\DateTimeImmutable $now=null):bool{return $this->enabled&&($this->publishedAt===null||$this->publishedAt<=($now??new \DateTimeImmutable()));}
    #[Assert\Callback] public function validateSource(ExecutionContextInterface $context):void{if(($this->filePath===null)===($this->externalUrl===null))$context->buildViolation('Genau eine Quelle (Datei oder externe URL) ist erforderlich.')->atPath('externalUrl')->addViolation(); if($this->externalUrl!==null&&(!filter_var($this->externalUrl,FILTER_VALIDATE_URL)||parse_url($this->externalUrl,PHP_URL_SCHEME)!=='https'))$context->buildViolation('Nur vollständige HTTPS-URLs sind erlaubt.')->atPath('externalUrl')->addViolation(); foreach($this->operatingSystems??[] as $os)if(!in_array($os,self::OPERATING_SYSTEMS,true))$context->buildViolation('Ungültiges Betriebssystem.')->atPath('operatingSystems')->addViolation();}
    #[ORM\PreUpdate] public function touch():void{$this->updatedAt=new \DateTimeImmutable();}
}
