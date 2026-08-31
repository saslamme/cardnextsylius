<?php

declare(strict_types=1);

namespace App\Entity\Customer;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_customer_b2b_profile')]
#[ORM\UniqueConstraint(name: 'UNIQ_CN_B2B_CUSTOMER', columns: ['customer_id'])]
#[ORM\UniqueConstraint(name: 'UNIQ_CN_B2B_CUSTOMER_NUMBER', columns: ['customer_number'])]
#[ORM\UniqueConstraint(name: 'UNIQ_CN_B2B_ERP_NUMBER', columns: ['erp_customer_number'])]
#[ORM\Index(columns: ['enabled'], name: 'IDX_CN_B2B_ENABLED')]
class CustomerB2BProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'b2bProfile', targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'customer_id', nullable: false, onDelete: 'CASCADE')]
    private Customer $customer;

    #[ORM\Column(name: 'customer_number', length: 64, nullable: true)]
    private ?string $customerNumber = null;

    #[ORM\Column(name: 'company_name', length: 255, nullable: true)]
    private ?string $companyName = null;

    #[ORM\Column(name: 'vat_number', length: 64, nullable: true)]
    private ?string $vatNumber = null;

    #[ORM\Column(name: 'erp_customer_number', length: 64, nullable: true)]
    private ?string $erpCustomerNumber = null;

    #[ORM\Column(name: 'contact_person', length: 255, nullable: true)]
    private ?string $contactPerson = null;

    #[ORM\Column(name: 'invoice_allowed', type: 'boolean', options: ['default' => false])]
    private bool $invoiceAllowed = false;

    #[ORM\Column(name: 'credit_limit', type: 'integer', nullable: true)]
    private ?int $creditLimit = null;

    #[ORM\Column(name: 'credit_limit_currency', length: 3, nullable: true)]
    private ?string $creditLimitCurrency = null;

    #[ORM\Column(name: 'payment_term_days', type: 'integer', nullable: true)]
    private ?int $paymentTermDays = null;

    #[ORM\Column(name: 'purchase_order_required', type: 'boolean', options: ['default' => false])]
    private bool $purchaseOrderRequired = false;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): void
    {
        $this->customer = $customer;
        $this->touch();

        if ($customer->getB2bProfile() !== $this) {
            $customer->setB2bProfile($this);
        }
    }

    public function getCustomerNumber(): ?string
    {
        return $this->customerNumber;
    }

    public function setCustomerNumber(?string $customerNumber): void
    {
        $this->customerNumber = $this->normalizeNullable($customerNumber);
        $this->touch();
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(?string $companyName): void
    {
        $this->companyName = $this->normalizeNullable($companyName);
        $this->touch();
    }

    public function getVatNumber(): ?string
    {
        return $this->vatNumber;
    }

    public function setVatNumber(?string $vatNumber): void
    {
        $vatNumber = $this->normalizeNullable($vatNumber);
        $this->vatNumber = $vatNumber !== null ? strtoupper($vatNumber) : null;
        $this->touch();
    }

    public function getErpCustomerNumber(): ?string
    {
        return $this->erpCustomerNumber;
    }

    public function setErpCustomerNumber(?string $erpCustomerNumber): void
    {
        $this->erpCustomerNumber = $this->normalizeNullable($erpCustomerNumber);
        $this->touch();
    }

    public function getContactPerson(): ?string
    {
        return $this->contactPerson;
    }

    public function setContactPerson(?string $contactPerson): void
    {
        $this->contactPerson = $this->normalizeNullable($contactPerson);
        $this->touch();
    }

    public function isInvoiceAllowed(): bool
    {
        return $this->invoiceAllowed;
    }

    public function setInvoiceAllowed(bool $invoiceAllowed): void
    {
        $this->invoiceAllowed = $invoiceAllowed;
        $this->touch();
    }

    public function getCreditLimit(): ?int
    {
        return $this->creditLimit;
    }

    public function setCreditLimit(?int $creditLimit): void
    {
        if ($creditLimit !== null && $creditLimit < 0) {
            throw new \InvalidArgumentException('Credit limit must not be negative.');
        }

        $this->creditLimit = $creditLimit;
        $this->touch();
    }

    public function getCreditLimitCurrency(): ?string
    {
        return $this->creditLimitCurrency;
    }

    public function setCreditLimitCurrency(?string $creditLimitCurrency): void
    {
        $creditLimitCurrency = $this->normalizeNullable($creditLimitCurrency);
        $this->creditLimitCurrency = $creditLimitCurrency !== null
            ? strtoupper($creditLimitCurrency)
            : null;

        $this->touch();
    }

    public function getPaymentTermDays(): ?int
    {
        return $this->paymentTermDays;
    }

    public function setPaymentTermDays(?int $paymentTermDays): void
    {
        if ($paymentTermDays !== null && $paymentTermDays < 0) {
            throw new \InvalidArgumentException('Payment term must not be negative.');
        }

        $this->paymentTermDays = $paymentTermDays;
        $this->touch();
    }

    public function isPurchaseOrderRequired(): bool
    {
        return $this->purchaseOrderRequired;
    }

    public function setPurchaseOrderRequired(bool $purchaseOrderRequired): void
    {
        $this->purchaseOrderRequired = $purchaseOrderRequired;
        $this->touch();
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
        $this->touch();
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $this->normalizeNullable($notes);
        $this->touch();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getDisplayName(): string
    {
        if ($this->companyName !== null) {
            return $this->companyName;
        }

        $fullName = trim($this->customer->getFullName());
        if ($fullName !== '') {
            return $fullName;
        }

        return (string) $this->customer->getEmail();
    }

    private function normalizeNullable(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value !== '' ? $value : null;
    }

    private function touch(): void
    {
        // @phpstan-ignore isset.initializedProperty
        if (isset($this->createdAt)) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }
}
