<?php

declare(strict_types=1);

namespace App\Entity\Quote;

use App\Entity\Customer\Customer;
use App\Enum\Quote\QuoteRequestStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_quote_request')]
#[ORM\Index(columns: ['status'], name: 'IDX_CN_QUOTE_STATUS')]
#[ORM\Index(columns: ['created_at'], name: 'IDX_CN_QUOTE_CREATED')]
#[ORM\Index(columns: ['channel_code'], name: 'IDX_CN_QUOTE_CHANNEL')]
#[ORM\Index(columns: ['email'], name: 'IDX_CN_QUOTE_EMAIL')]
#[ORM\HasLifecycleCallbacks]
class QuoteRequest
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private string $number = '';

    #[ORM\Column(length: 32, enumType: QuoteRequestStatus::class)]
    private QuoteRequestStatus $status = QuoteRequestStatus::New;

    #[ORM\Column(name: 'channel_code', length: 64)]
    private string $channelCode = '';

    #[ORM\Column(name: 'locale_code', length: 12)]
    private string $localeCode = '';

    #[ORM\Column(name: 'currency_code', length: 3)]
    private string $currencyCode = '';

    #[ORM\ManyToOne(targetEntity: Customer::class), ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Customer $customer = null;

    #[Assert\NotBlank, Assert\Length(max: 255)] #[ORM\Column(length: 255)]
    private string $company = '';

    #[Assert\NotBlank, Assert\Length(max: 255)] #[ORM\Column(name: 'contact_name', length: 255)]
    private string $contactName = '';

    #[Assert\NotBlank, Assert\Email, Assert\Length(max: 254)] #[ORM\Column(length: 254)]
    private string $email = '';

    #[Assert\Length(max: 64)] #[ORM\Column(length: 64, nullable: true)]
    private ?string $phone = null;

    #[Assert\Length(max: 64)] #[ORM\Column(name: 'customer_number', length: 64, nullable: true)]
    private ?string $customerNumber = null;

    #[Assert\Length(max: 255)] #[ORM\Column(length: 255, nullable: true)]
    private ?string $street = null;

    #[Assert\Length(max: 32)] #[ORM\Column(name: 'house_number', length: 32, nullable: true)]
    private ?string $houseNumber = null;

    #[Assert\Length(max: 32)] #[ORM\Column(name: 'postal_code', length: 32, nullable: true)]
    private ?string $postalCode = null;

    #[Assert\Length(max: 128)] #[ORM\Column(length: 128, nullable: true)]
    private ?string $city = null;

    #[Assert\NotBlank, Assert\Country] #[ORM\Column(name: 'country_code', length: 2)]
    private string $countryCode = '';

    #[Assert\Length(max: 255)] #[ORM\Column(name: 'project_reference', length: 255, nullable: true)]
    private ?string $projectReference = null;

    #[Assert\Length(max: 128)] #[ORM\Column(name: 'customer_purchase_order_number', length: 128, nullable: true)]
    private ?string $customerPurchaseOrderNumber = null;

    #[ORM\Column(name: 'requested_delivery_date', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $requestedDeliveryDate = null;

    #[Assert\Length(max: 5000)] #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(name: 'needs_advice')]
    private bool $needsAdvice = false;

    #[ORM\Column(name: 'needs_compatibility_check')]
    private bool $needsCompatibilityCheck = false;

    #[ORM\Column(name: 'estimated_subtotal', nullable: true)]
    private ?int $estimatedSubtotal = null;

    #[ORM\Column(name: 'estimated_total', nullable: true)]
    private ?int $estimatedTotal = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, QuoteRequestItem> */
    #[ORM\OneToMany(mappedBy: 'quoteRequest', targetEntity: QuoteRequestItem::class, cascade: ['persist'], orphanRemoval: true)] #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $items;

    /** @var Collection<int, QuoteRequestHistory> */
    #[ORM\OneToMany(mappedBy: 'quoteRequest', targetEntity: QuoteRequestHistory::class, cascade: ['persist'], orphanRemoval: true)] #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $history;

    /** @var Collection<int, Quote> */
    #[ORM\OneToMany(mappedBy: 'quoteRequest', targetEntity: Quote::class)]
    #[ORM\OrderBy(['version' => 'DESC'])]
    private Collection $quotes;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->history = new ArrayCollection();
        $this->quotes = new ArrayCollection();
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $v): void
    {
        $this->number = $v;
    }

    public function getStatus(): QuoteRequestStatus
    {
        return $this->status;
    }

    public function setStatus(QuoteRequestStatus $v): void
    {
        $this->status = $v;
    }

    public function getChannelCode(): string
    {
        return $this->channelCode;
    }

    public function setChannelCode(string $v): void
    {
        $this->channelCode = $v;
    }

    public function getLocaleCode(): string
    {
        return $this->localeCode;
    }

    public function setLocaleCode(string $v): void
    {
        $this->localeCode = $v;
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public function setCurrencyCode(string $v): void
    {
        $this->currencyCode = $v;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $v): void
    {
        $this->customer = $v;
    }

    public function getCompany(): string
    {
        return $this->company;
    }

    public function setCompany(string $v): void
    {
        $this->company = $v;
    }

    public function getContactName(): string
    {
        return $this->contactName;
    }

    public function setContactName(string $v): void
    {
        $this->contactName = $v;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $v): void
    {
        $this->email = $v;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $v): void
    {
        $this->phone = $v;
    }

    public function getCustomerNumber(): ?string
    {
        return $this->customerNumber;
    }

    public function setCustomerNumber(?string $v): void
    {
        $this->customerNumber = $v;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(?string $v): void
    {
        $this->street = $v;
    }

    public function getHouseNumber(): ?string
    {
        return $this->houseNumber;
    }

    public function setHouseNumber(?string $v): void
    {
        $this->houseNumber = $v;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $v): void
    {
        $this->postalCode = $v;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $v): void
    {
        $this->city = $v;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function setCountryCode(string $v): void
    {
        $this->countryCode = $v;
    }

    public function getProjectReference(): ?string
    {
        return $this->projectReference;
    }

    public function setProjectReference(?string $v): void
    {
        $this->projectReference = $v;
    }

    public function getCustomerPurchaseOrderNumber(): ?string
    {
        return $this->customerPurchaseOrderNumber;
    }

    public function setCustomerPurchaseOrderNumber(?string $v): void
    {
        $this->customerPurchaseOrderNumber = $v;
    }

    public function getRequestedDeliveryDate(): ?\DateTimeImmutable
    {
        return $this->requestedDeliveryDate;
    }

    public function setRequestedDeliveryDate(?\DateTimeImmutable $v): void
    {
        $this->requestedDeliveryDate = $v;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $v): void
    {
        $this->message = $v;
    }

    public function isNeedsAdvice(): bool
    {
        return $this->needsAdvice;
    }

    public function setNeedsAdvice(bool $v): void
    {
        $this->needsAdvice = $v;
    }

    public function isNeedsCompatibilityCheck(): bool
    {
        return $this->needsCompatibilityCheck;
    }

    public function setNeedsCompatibilityCheck(bool $v): void
    {
        $this->needsCompatibilityCheck = $v;
    }

    public function getEstimatedSubtotal(): ?int
    {
        return $this->estimatedSubtotal;
    }

    public function setEstimatedSubtotal(?int $v): void
    {
        $this->estimatedSubtotal = $v;
    }

    public function getEstimatedTotal(): ?int
    {
        return $this->estimatedTotal;
    }

    public function setEstimatedTotal(?int $v): void
    {
        $this->estimatedTotal = $v;
    }

    /** @return Collection<int, QuoteRequestItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(QuoteRequestItem $v): void
    {
        $this->items->add($v);
        $v->setQuoteRequest($this);
    }

    /** @return Collection<int, QuoteRequestHistory> */
    public function getHistory(): Collection
    {
        return $this->history;
    }

    public function addHistory(QuoteRequestHistory $v): void
    {
        $this->history->add($v);
        $v->setQuoteRequest($this);
    }

    /** @return Collection<int, Quote> */
    public function getQuotes(): Collection
    {
        return $this->quotes;
    }

    public function addQuote(Quote $quote): void
    {
        if (!$this->quotes->contains($quote)) {
            $this->quotes->add($quote);
            $quote->setQuoteRequest($this);
        }
    }

    public function getActiveQuote(): ?Quote
    {
        $quote = $this->quotes->first();

        return $quote instanceof Quote ? $quote : null;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
