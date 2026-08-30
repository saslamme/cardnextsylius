<?php

declare(strict_types=1);

namespace App\Entity\Quote;

use App\Entity\Order\Order;
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
    #[ORM\Column(name: 'customer_street', length: 255, nullable: true)] private ?string $customerStreet = null;
    #[ORM\Column(name: 'customer_house_number', length: 32, nullable: true)] private ?string $customerHouseNumber = null;
    #[ORM\Column(name: 'customer_postal_code', length: 32, nullable: true)] private ?string $customerPostalCode = null;
    #[ORM\Column(name: 'customer_city', length: 128, nullable: true)] private ?string $customerCity = null;
    #[ORM\Column(name: 'customer_country_code', length: 2, nullable: true)] private ?string $customerCountryCode = null;
    #[ORM\Column(name: 'customer_number', length: 64, nullable: true)] private ?string $customerNumber = null;
    #[ORM\Column(name: 'customer_phone', length: 64, nullable: true)] private ?string $customerPhone = null;
    #[ORM\Column(name: 'project_reference', length: 255, nullable: true)] private ?string $projectReference = null;
    #[ORM\Column(name: 'customer_purchase_order_number', length: 128, nullable: true)] private ?string $customerPurchaseOrderNumber = null;
    #[ORM\Column(name: 'quote_date', type: Types::DATE_IMMUTABLE, nullable: true)] private ?\DateTimeImmutable $quoteDate = null;
    #[ORM\Column(name: 'ready_at', type: Types::DATETIME_IMMUTABLE, nullable: true)] private ?\DateTimeImmutable $readyAt = null;
    #[ORM\Column(name: 'access_token_hash', length: 64, nullable: true)] private ?string $accessTokenHash = null;
    #[ORM\Column(name: 'access_token_issued_at', type: Types::DATETIME_IMMUTABLE, nullable: true)] private ?\DateTimeImmutable $accessTokenIssuedAt = null;
    #[ORM\Column(name: 'first_sent_at', type: Types::DATETIME_IMMUTABLE, nullable: true)] private ?\DateTimeImmutable $firstSentAt = null;
    #[ORM\Column(name: 'last_sent_at', type: Types::DATETIME_IMMUTABLE, nullable: true)] private ?\DateTimeImmutable $lastSentAt = null;
    #[ORM\Column(name: 'send_count', options: ['default' => 0])] private int $sendCount = 0;
    #[ORM\Column(name: 'first_viewed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)] private ?\DateTimeImmutable $firstViewedAt = null;
    #[ORM\Column(name: 'last_viewed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)] private ?\DateTimeImmutable $lastViewedAt = null;
    #[ORM\Column(name: 'view_count', options: ['default' => 0])] private int $viewCount = 0;
    #[ORM\Column(name: 'accepted_at', type: Types::DATETIME_IMMUTABLE, nullable: true)] private ?\DateTimeImmutable $acceptedAt = null;
    #[ORM\Column(name: 'accepted_by_name', length: 255, nullable: true)] private ?string $acceptedByName = null;
    #[ORM\Column(name: 'rejected_at', type: Types::DATETIME_IMMUTABLE, nullable: true)] private ?\DateTimeImmutable $rejectedAt = null;
    #[ORM\Column(name: 'rejected_by_name', length: 255, nullable: true)] private ?string $rejectedByName = null;
    #[ORM\Column(name: 'rejection_reason', type: Types::TEXT, nullable: true)] private ?string $rejectionReason = null;
    #[ORM\Column(name: 'default_tax_rate', options: ['default' => 0])] private int $defaultTaxRate = 0;
    #[ORM\Column(name: 'tax_note', type: Types::TEXT, nullable: true)] private ?string $taxNote = null;
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
    #[ORM\OneToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(name: 'order_id', nullable: true, unique: true, onDelete: 'SET NULL')]
    private ?Order $order = null;
    #[ORM\Column(name: 'converted_to_order_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $convertedToOrderAt = null;
    #[ORM\ManyToOne(targetEntity: AdminUser::class)]
    #[ORM\JoinColumn(name: 'converted_to_order_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?AdminUser $convertedToOrderBy = null;
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
    public function getStatus(): QuoteStatus { return $this->status; }
    public function transitionTo(QuoteStatus $value): void { if (!$this->status->canTransitionTo($value)) throw new \DomainException('Invalid quote status transition.'); $this->status = $value; }
    /** @internal Initial state is only set by factories/ORM. */ public function setStatus(QuoteStatus $value): void { $this->status = $value; }
    public function getChannelCode(): string { return $this->channelCode; } public function setChannelCode(string $value): void { $this->channelCode = $value; }
    public function getLocaleCode(): string { return $this->localeCode; } public function setLocaleCode(string $value): void { $this->localeCode = $value; }
    public function getCurrencyCode(): string { return $this->currencyCode; } public function setCurrencyCode(string $value): void { $this->currencyCode = $value; }
    public function getCustomerCompany(): string { return $this->customerCompany; } public function setCustomerCompany(string $value): void { $this->customerCompany = $value; }
    public function getCustomerContactName(): string { return $this->customerContactName; } public function setCustomerContactName(string $value): void { $this->customerContactName = $value; }
    public function getCustomerEmail(): string { return $this->customerEmail; } public function setCustomerEmail(string $value): void { $this->customerEmail = $value; }
    public function getCustomerStreet(): ?string { return $this->customerStreet; } public function setCustomerStreet(?string $v): void { $this->customerStreet=$v; }
    public function getCustomerHouseNumber(): ?string { return $this->customerHouseNumber; } public function setCustomerHouseNumber(?string $v): void { $this->customerHouseNumber=$v; }
    public function getCustomerPostalCode(): ?string { return $this->customerPostalCode; } public function setCustomerPostalCode(?string $v): void { $this->customerPostalCode=$v; }
    public function getCustomerCity(): ?string { return $this->customerCity; } public function setCustomerCity(?string $v): void { $this->customerCity=$v; }
    public function getCustomerCountryCode(): ?string { return $this->customerCountryCode; } public function setCustomerCountryCode(?string $v): void { $this->customerCountryCode=$v; }
    public function getCustomerNumber(): ?string { return $this->customerNumber; } public function setCustomerNumber(?string $v): void { $this->customerNumber=$v; }
    public function getCustomerPhone(): ?string { return $this->customerPhone; } public function setCustomerPhone(?string $v): void { $this->customerPhone=$v; }
    public function getProjectReference(): ?string { return $this->projectReference; } public function setProjectReference(?string $v): void { $this->projectReference=$v; }
    public function getCustomerPurchaseOrderNumber(): ?string { return $this->customerPurchaseOrderNumber; } public function setCustomerPurchaseOrderNumber(?string $v): void { $this->customerPurchaseOrderNumber=$v; }
    public function getQuoteDate(): ?\DateTimeImmutable { return $this->quoteDate; } public function setQuoteDate(?\DateTimeImmutable $v): void { $this->quoteDate=$v; }
    public function getReadyAt(): ?\DateTimeImmutable { return $this->readyAt; } public function setReadyAt(?\DateTimeImmutable $v): void { $this->readyAt=$v; }
    public function getAccessTokenHash(): ?string { return $this->accessTokenHash; }
    public function getAccessTokenIssuedAt(): ?\DateTimeImmutable { return $this->accessTokenIssuedAt; }
    public function setPublicAccess(?string $hash, ?\DateTimeImmutable $issuedAt): void { $this->accessTokenHash=$hash; $this->accessTokenIssuedAt=$issuedAt; }
    public function getFirstSentAt(): ?\DateTimeImmutable { return $this->firstSentAt; } public function getLastSentAt(): ?\DateTimeImmutable { return $this->lastSentAt; } public function getSendCount(): int { return $this->sendCount; }
    public function recordSent(\DateTimeImmutable $now): void { if (!in_array($this->status,[QuoteStatus::Ready,QuoteStatus::Sent],true)) throw new \DomainException('Dieses Angebot kann nicht versendet werden.'); if ($this->firstSentAt===null) $this->firstSentAt=$now; $this->lastSentAt=$now; ++$this->sendCount; if ($this->status===QuoteStatus::Ready) $this->transitionTo(QuoteStatus::Sent); }
    public function getFirstViewedAt(): ?\DateTimeImmutable { return $this->firstViewedAt; } public function getLastViewedAt(): ?\DateTimeImmutable { return $this->lastViewedAt; } public function getViewCount(): int { return $this->viewCount; }
    public function recordViewed(\DateTimeImmutable $now): bool { $first=$this->firstViewedAt===null; if ($first) $this->firstViewedAt=$now; $this->lastViewedAt=$now; ++$this->viewCount; return $first; }
    public function getAcceptedAt(): ?\DateTimeImmutable { return $this->acceptedAt; } public function getAcceptedByName(): ?string { return $this->acceptedByName; }
    public function accept(string $name, \DateTimeImmutable $now): void { $name=trim($name); if ($name==='' || mb_strlen($name)>255) throw new \InvalidArgumentException('Invalid name.'); $this->transitionTo(QuoteStatus::Accepted); $this->acceptedAt=$now; $this->acceptedByName=$name; }
    public function getRejectedAt(): ?\DateTimeImmutable { return $this->rejectedAt; } public function getRejectedByName(): ?string { return $this->rejectedByName; } public function getRejectionReason(): ?string { return $this->rejectionReason; }
    public function reject(string $name, ?string $reason, \DateTimeImmutable $now): void { $name=trim($name); $reason=$reason===null?null:trim($reason); if ($name==='' || mb_strlen($name)>255 || ($reason!==null && mb_strlen($reason)>2000)) throw new \InvalidArgumentException('Invalid rejection data.'); $this->transitionTo(QuoteStatus::Rejected); $this->rejectedAt=$now; $this->rejectedByName=$name; $this->rejectionReason=$reason===''?null:$reason; }
    public function isExpired(?\DateTimeImmutable $now=null): bool { return $this->validUntil!==null && ($now??new \DateTimeImmutable())->setTime(0,0)>$this->validUntil->setTime(0,0); }
    public function getDefaultTaxRate(): int { return $this->defaultTaxRate; } public function setDefaultTaxRate(int $v): void { if ($v < 0) throw new \InvalidArgumentException('Tax rate cannot be negative.'); $this->defaultTaxRate=$v; }
    public function getTaxNote(): ?string { return $this->taxNote; } public function setTaxNote(?string $v): void { $this->taxNote=$v; }
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
    public function getOrder(): ?Order { return $this->order; }
    public function getConvertedToOrderAt(): ?\DateTimeImmutable { return $this->convertedToOrderAt; }
    public function getConvertedToOrderBy(): ?AdminUser { return $this->convertedToOrderBy; }
    public function markConvertedToOrder(Order $order, ?AdminUser $admin, \DateTimeImmutable $now): void
    {
        if ($this->order !== null) throw new \DomainException('Dieses Angebot wurde bereits in eine Bestellung umgewandelt.');
        $this->order = $order; $this->convertedToOrderBy = $admin; $this->convertedToOrderAt = $now;
    }
    /** @return Collection<int, QuoteItem> */ public function getItems(): Collection { return $this->items; }
    public function addItem(QuoteItem $item): void { if (!$this->items->contains($item)) { $this->items->add($item); $item->setQuote($this); } }
    public function removeItem(QuoteItem $item): void { $this->items->removeElement($item); }
    #[ORM\PreUpdate] public function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }
}
