<?php

declare(strict_types=1);

namespace App\Entity\Product;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_product_import_run')]
#[ORM\Index(columns: ['created_at'], name: 'IDX_CARDNEXT_IMPORT_CREATED')]
#[ORM\Index(columns: ['status'], name: 'IDX_CARDNEXT_IMPORT_STATUS')]
class ProductImportRun
{
    public const STATUS_VALIDATED = 'validated';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'original_filename', length: 255)]
    private string $originalFilename = '';

    #[ORM\Column(length: 32)]
    private string $status = self::STATUS_VALIDATED;

    #[ORM\Column(name: 'dry_run', type: 'boolean', options: ['default' => false])]
    private bool $dryRun = false;

    #[ORM\Column(name: 'row_count', type: 'integer', nullable: true)]
    private ?int $rows = null;

    #[ORM\Column(name: 'manufacturers_created', type: 'integer', nullable: true)]
    private ?int $manufacturersCreated = null;

    #[ORM\Column(name: 'manufacturers_updated', type: 'integer', nullable: true)]
    private ?int $manufacturersUpdated = null;

    #[ORM\Column(name: 'documents_created', type: 'integer', nullable: true)]
    private ?int $documentsCreated = null;

    #[ORM\Column(name: 'documents_updated', type: 'integer', nullable: true)]
    private ?int $documentsUpdated = null;

    #[ORM\Column(name: 'compatibilities_created', type: 'integer', nullable: true)]
    private ?int $compatibilitiesCreated = null;

    #[ORM\Column(name: 'compatibilities_updated', type: 'integer', nullable: true)]
    private ?int $compatibilitiesUpdated = null;

    #[ORM\Column(name: 'price_rules_created', type: 'integer', nullable: true)]
    private ?int $priceRulesCreated = null;

    #[ORM\Column(name: 'price_rules_updated', type: 'integer', nullable: true)]
    private ?int $priceRulesUpdated = null;

    #[ORM\Column(name: 'customer_price_rules_created', type: 'integer', nullable: true)]
    private ?int $customerPriceRulesCreated = null;

    #[ORM\Column(name: 'customer_price_rules_updated', type: 'integer', nullable: true)]
    private ?int $customerPriceRulesUpdated = null;

    #[ORM\Column(name: 'products_created', type: 'integer', nullable: true)]
    private ?int $productsCreated = null;

    #[ORM\Column(name: 'products_updated', type: 'integer', nullable: true)]
    private ?int $productsUpdated = null;

    #[ORM\Column(name: 'variants_created', type: 'integer', nullable: true)]
    private ?int $variantsCreated = null;

    #[ORM\Column(name: 'variants_updated', type: 'integer', nullable: true)]
    private ?int $variantsUpdated = null;

    /** @var list<string> */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $warnings = null;

    #[ORM\Column(name: 'error_message', type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(name: 'user_identifier', length: 255, nullable: true)]
    private ?string $userIdentifier = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): void
    {
        $this->originalFilename = mb_substr(trim($originalFilename), 0, 255);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    public function setDryRun(bool $dryRun): void
    {
        $this->dryRun = $dryRun;
    }

    public function getRows(): ?int
    {
        return $this->rows;
    }

    public function getManufacturersCreated(): ?int
    {
        return $this->manufacturersCreated;
    }

    public function getManufacturersUpdated(): ?int
    {
        return $this->manufacturersUpdated;
    }

    public function getDocumentsCreated(): ?int
    {
        return $this->documentsCreated;
    }

    public function getDocumentsUpdated(): ?int
    {
        return $this->documentsUpdated;
    }

    public function getCompatibilitiesCreated(): ?int
    {
        return $this->compatibilitiesCreated;
    }

    public function getCompatibilitiesUpdated(): ?int
    {
        return $this->compatibilitiesUpdated;
    }

    public function getPriceRulesCreated(): ?int
    {
        return $this->priceRulesCreated;
    }

    public function getPriceRulesUpdated(): ?int
    {
        return $this->priceRulesUpdated;
    }

    public function getCustomerPriceRulesCreated(): ?int
    {
        return $this->customerPriceRulesCreated;
    }

    public function getCustomerPriceRulesUpdated(): ?int
    {
        return $this->customerPriceRulesUpdated;
    }

    public function getProductsCreated(): ?int
    {
        return $this->productsCreated;
    }

    public function getProductsUpdated(): ?int
    {
        return $this->productsUpdated;
    }

    public function getVariantsCreated(): ?int
    {
        return $this->variantsCreated;
    }

    public function getVariantsUpdated(): ?int
    {
        return $this->variantsUpdated;
    }

    /**
     * @return list<string>
     */
    public function getWarnings(): array
    {
        return $this->warnings ?? [];
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    public function setUserIdentifier(?string $userIdentifier): void
    {
        $this->userIdentifier = $userIdentifier !== null ? mb_substr($userIdentifier, 0, 255) : null;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @param array{
     *   rows:int,
     *   manufacturers_created:int,
     *   manufacturers_updated:int,
     *   documents_created:int,
     *   documents_updated:int,
     *   compatibilities_created:int,
     *   compatibilities_updated:int,
     *   price_rules_created:int,
     *   price_rules_updated:int,
     *   customer_price_rules_created:int,
     *   customer_price_rules_updated:int,
     *   products_created:int,
     *   products_updated:int,
     *   variants_created:int,
     *   variants_updated:int,
     *   warnings:list<string>
     * } $result
     */
    public function applyResult(array $result): void
    {
        $this->rows = $result['rows'];
        // @phpstan-ignore nullCoalesce.offset
        $this->manufacturersCreated = $result['manufacturers_created'] ?? 0;
        // @phpstan-ignore nullCoalesce.offset
        $this->manufacturersUpdated = $result['manufacturers_updated'] ?? 0;
        // @phpstan-ignore nullCoalesce.offset
        $this->documentsCreated = $result['documents_created'] ?? 0;
        // @phpstan-ignore nullCoalesce.offset
        $this->documentsUpdated = $result['documents_updated'] ?? 0;
        // @phpstan-ignore nullCoalesce.offset
        $this->compatibilitiesCreated = $result['compatibilities_created'] ?? 0;
        // @phpstan-ignore nullCoalesce.offset
        $this->compatibilitiesUpdated = $result['compatibilities_updated'] ?? 0;
        // @phpstan-ignore nullCoalesce.offset
        $this->priceRulesCreated = $result['price_rules_created'] ?? 0;
        // @phpstan-ignore nullCoalesce.offset
        $this->priceRulesUpdated = $result['price_rules_updated'] ?? 0;
        // @phpstan-ignore nullCoalesce.offset
        $this->customerPriceRulesCreated = $result['customer_price_rules_created'] ?? 0;
        // @phpstan-ignore nullCoalesce.offset
        $this->customerPriceRulesUpdated = $result['customer_price_rules_updated'] ?? 0;
        $this->productsCreated = $result['products_created'];
        $this->productsUpdated = $result['products_updated'];
        $this->variantsCreated = $result['variants_created'];
        $this->variantsUpdated = $result['variants_updated'];
        $this->warnings = $result['warnings'];
        $this->errorMessage = null;
    }

    public function markFailed(string $message): void
    {
        $this->status = self::STATUS_FAILED;
        $this->errorMessage = $message;
    }
}
