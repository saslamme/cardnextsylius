<?php

declare(strict_types=1);

namespace App\Entity\Quote;

use App\Entity\User\AdminUser;
use App\Enum\Quote\QuoteStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_quote')]
#[ORM\UniqueConstraint(name: 'UNIQ_CN_OFFER_NUMBER_VERSION', columns: ['number', 'version'])]
#[ORM\Index(columns: ['quote_request_id'], name: 'IDX_CN_OFFER_REQUEST')]
#[ORM\Index(columns: ['status'], name: 'IDX_CN_OFFER_STATUS')]
#[ORM\Index(columns: ['created_at'], name: 'IDX_CN_OFFER_CREATED')]
#[ORM\HasLifecycleCallbacks]
class Quote
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: QuoteRequest::class, inversedBy: 'quotes')]
    #[ORM\JoinColumn(name: 'quote_request_id', nullable: false, onDelete: 'RESTRICT')]
    private QuoteRequest $quoteRequest;
    #[ORM\Column(length: 20)] private string $number = '';
    #[ORM\Column] private int $version = 1;
    #[ORM\Column(length: 16, enumType: QuoteStatus::class)] private QuoteStatus $status = QuoteStatus::Draft;
    #[ORM\Column(name: 'channel_code', length: 64)] private string $channelCode = '';
    #[ORM\Column(name: 'locale_code', length: 12)] private string $localeCode = '';
    #[ORM\Column(name: 'currency_code', length: 3)] private string $currencyCode = '';
    #[ORM\Column(name: 'customer_company', length: 255)] private string $customerCompany = '';
    #[ORM\Column(name: 'customer_contact_name', length: 255)] private string $customerContactName = '';
    #[ORM\Column(name: 'customer_email', length: 254)] private string $customerEmail = '';
    #[ORM\Column(name: 'valid_until', type: Types::DATE_IMMUTABLE, nullable: true)] private ?\DateTimeImmutable $validUntil = null;
    #[ORM\Column(name: 'delivery_terms', type: Types::TEXT, nullable: true)] private ?string $deliveryTerms = null;
    #[ORM\Column(name: 'payment_terms', type: Types::TEXT, nullable: true)] private ?string $paymentTerms = null;
    #[ORM\Column(name: 'customer_note', type: Types::TEXT, nullable: true)] private ?string $customerNote = null;
    #[ORM\Column(name: 'internal_note', type: Types::TEXT, nullable: true)] private ?string $internalNote = null;
    #[ORM\Column] private int $subtotal = 0;
    #[ORM\Column(name: 'discount_total')] private int $discountTotal = 0;
    #[ORM\Column(name: 'shipping_total')] private int $shippingTotal = 0;
    #[ORM\Column(name: 'service_total')] private int $serviceTotal = 0;
    #[ORM\Column(name: 'tax_total')] private int $taxTotal = 0;
    #[ORM\Column(name: 'grand_total')] private int $grandTotal = 0;
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)] private \DateTimeImmutable $createdAt;
    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)] private \DateTimeImmutable $updatedAt;
    #[ORM\ManyToOne(targetEntity: AdminUser::class), ORM\JoinColumn(name: 'created_by_id', nullable: true, onDelete: 'SET NULL')] private ?AdminUser $createdBy = null;
    #[ORM\ManyToOne(targetEntity: AdminUser::class), ORM\JoinColumn(name: 'updated_by_id', nullable: true, onDelete: 'SET NULL')] private ?AdminUser $updatedBy = null;
    /** @var Collection<int, QuoteItem> */
    #[ORM\OneToMany(mappedBy: 'quote', targetEntity: QuoteItem::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $items;

    public function __construct() { $this->items = new ArrayCollection(); $this->createdAt = $this->updatedAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getQuoteRequest(): QuoteRequest { return $this->quoteRequest; }
    public function setQuoteRequest(QuoteRequest $value): void { $this->quoteRequest = $value; }
    public function getNumber(): string { return $this->number; } public function setNumber(string $value): void { $this->number = $value; }
    public function getVersion(): int { return $this->version; } public function setVersion(int $value): void { $this->version = $value; }
    public function getStatus(): QuoteStatus { return $this->status; } public function setStatus(QuoteStatus $value): void { $this->status = $value; }
    public function getChannelCode(): string { return $this->channelCode; } public function setChannelCode(string $value): void { $this->channelCode = $value; }
    public function getLocaleCode(): string { return $this->localeCode; } public function setLocaleCode(string $value): void { $this->localeCode = $value; }
    public function getCurrencyCode(): string { return $this->currencyCode; } public function setCurrencyCode(string $value): void { $this->currencyCode = $value; }
    public function getCustomerCompany(): string { return $this->customerCompany; } public function setCustomerCompany(string $value): void { $this->customerCompany = $value; }
    public function getCustomerContactName(): string { return $this->customerContactName; } public function setCustomerContactName(string $value): void { $this->customerContactName = $value; }
    public function getCustomerEmail(): string { return $this->customerEmail; } public function setCustomerEmail(string $value): void { $this->customerEmail = $value; }
    public function getValidUntil(): ?\DateTimeImmutable { return $this->validUntil; } public function setValidUntil(?\DateTimeImmutable $value): void { $this->validUntil = $value; }
    public function getDeliveryTerms(): ?string { return $this->deliveryTerms; } public function setDeliveryTerms(?string $value): void { $this->deliveryTerms = $value; }
    public function getPaymentTerms(): ?string { return $this->paymentTerms; } public function setPaymentTerms(?string $value): void { $this->paymentTerms = $value; }
    public function getCustomerNote(): ?string { return $this->customerNote; } public function setCustomerNote(?string $value): void { $this->customerNote = $value; }
    public function getInternalNote(): ?string { return $this->internalNote; } public function setInternalNote(?string $value): void { $this->internalNote = $value; }
    public function getSubtotal(): int { return $this->subtotal; } public function setSubtotal(int $value): void { $this->subtotal = $value; }
    public function getDiscountTotal(): int { return $this->discountTotal; } public function setDiscountTotal(int $value): void { $this->discountTotal = $value; }
    public function getShippingTotal(): int { return $this->shippingTotal; } public function setShippingTotal(int $value): void { $this->shippingTotal = $value; }
    public function getServiceTotal(): int { return $this->serviceTotal; } public function setServiceTotal(int $value): void { $this->serviceTotal = $value; }
    public function getTaxTotal(): int { return $this->taxTotal; } public function setTaxTotal(int $value): void { $this->taxTotal = $value; }
    public function getGrandTotal(): int { return $this->grandTotal; } public function setGrandTotal(int $value): void { $this->grandTotal = $value; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; } public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function getCreatedBy(): ?AdminUser { return $this->createdBy; } public function setCreatedBy(?AdminUser $value): void { $this->createdBy = $value; }
    public function getUpdatedBy(): ?AdminUser { return $this->updatedBy; } public function setUpdatedBy(?AdminUser $value): void { $this->updatedBy = $value; }
    /** @return Collection<int, QuoteItem> */ public function getItems(): Collection { return $this->items; }
    public function addItem(QuoteItem $item): void { if (!$this->items->contains($item)) { $this->items->add($item); $item->setQuote($this); } }
    public function removeItem(QuoteItem $item): void { $this->items->removeElement($item); }
    #[ORM\PreUpdate] public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }
}
