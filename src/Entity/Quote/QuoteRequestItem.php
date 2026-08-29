<?php

declare(strict_types=1);
namespace App\Entity\Quote;
use App\Entity\Product\Product; use App\Entity\Product\ProductVariant; use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity] #[ORM\Table(name:'cardnext_quote_request_item')]
class QuoteRequestItem {
 #[ORM\Id,ORM\GeneratedValue,ORM\Column] private ?int $id=null;
 #[ORM\ManyToOne(inversedBy: 'items')]
 #[ORM\JoinColumn(
     name: 'quote_request_id',
     nullable: false,
     onDelete: 'CASCADE'
 )]
 private QuoteRequest $quoteRequest;
 #[ORM\ManyToOne(targetEntity:Product::class),ORM\JoinColumn(nullable:true,onDelete:'SET NULL')] private ?Product $product=null; #[ORM\ManyToOne(targetEntity:ProductVariant::class),ORM\JoinColumn(nullable:true,onDelete:'SET NULL')] private ?ProductVariant $variant=null;
 #[ORM\Column(name:'product_code',length:64)] private string $productCode=''; #[ORM\Column(name:'variant_code',length:64)] private string $variantCode=''; #[ORM\Column(name:'product_name',length:255)] private string $productName=''; #[ORM\Column(name:'variant_name',length:255,nullable:true)] private ?string $variantName=null; #[ORM\Column(name:'manufacturer_name',length:255,nullable:true)] private ?string $manufacturerName=null; #[ORM\Column] private int $quantity=1; #[ORM\Column(name:'unit_price')] private int $unitPrice=0; #[ORM\Column(name:'line_total')] private int $lineTotal=0; #[ORM\Column(name:'currency_code',length:3)] private string $currencyCode=''; #[ORM\Column(name:'product_url',length:500,nullable:true)] private ?string $productUrl=null; #[ORM\Column] private int $position=0;
 public function getId():?int{return $this->id;} public function getQuoteRequest():QuoteRequest{return $this->quoteRequest;} public function getProduct():?Product{return $this->product;} public function getVariant():?ProductVariant{return $this->variant;} public function setQuoteRequest(QuoteRequest $v):void{$this->quoteRequest=$v;} public function getProductCode():string{return $this->productCode;} public function setProductCode(string $v):void{$this->productCode=$v;} public function getVariantCode():string{return $this->variantCode;} public function setVariantCode(string $v):void{$this->variantCode=$v;} public function getProductName():string{return $this->productName;} public function setProductName(string $v):void{$this->productName=$v;} public function getVariantName():?string{return $this->variantName;} public function setVariantName(?string $v):void{$this->variantName=$v;} public function getManufacturerName():?string{return $this->manufacturerName;} public function setManufacturerName(?string $v):void{$this->manufacturerName=$v;} public function getQuantity():int{return $this->quantity;} public function setQuantity(int $v):void{$this->quantity=$v;} public function getUnitPrice():int{return $this->unitPrice;} public function setUnitPrice(int $v):void{$this->unitPrice=$v;} public function getLineTotal():int{return $this->lineTotal;} public function setLineTotal(int $v):void{$this->lineTotal=$v;} public function getCurrencyCode():string{return $this->currencyCode;} public function setCurrencyCode(string $v):void{$this->currencyCode=$v;} public function getProductUrl():?string{return $this->productUrl;} public function setProductUrl(?string $v):void{$this->productUrl=$v;} public function getPosition():int{return $this->position;} public function setPosition(int $v):void{$this->position=$v;} public function setProduct(?Product $v):void{$this->product=$v;} public function setVariant(?ProductVariant $v):void{$this->variant=$v;}
}
