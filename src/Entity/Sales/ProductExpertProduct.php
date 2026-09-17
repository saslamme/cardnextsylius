<?php
declare(strict_types=1);
namespace App\Entity\Sales;
use App\Entity\Product\Product;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_product_expert_product')]
#[ORM\UniqueConstraint(name: 'uniq_expert_product', columns: ['expert_id', 'product_id'])]
#[ORM\Index(name: 'idx_expert_position', columns: ['expert_id', 'position'])]
#[ORM\Index(name: 'IDX_EXPERT_PRODUCT_PRODUCT', columns: ['product_id'])]
class ProductExpertProduct
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: ProductExpert::class, inversedBy: 'products')]
    #[ORM\JoinColumn(name: 'expert_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')] private ?ProductExpert $expert = null;
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')] private ?Product $product = null;
    #[ORM\Column(options: ['default' => 0])] private int $position = 0;
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)] private \DateTimeImmutable $createdAt;
    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getExpert(): ?ProductExpert { return $this->expert; }
    public function setExpert(ProductExpert $value): void { $this->expert = $value; }
    public function getProduct(): ?Product { return $this->product; }
    public function setProduct(Product $value): void { $this->product = $value; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $value): void { $this->position = max(0, $value); }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
