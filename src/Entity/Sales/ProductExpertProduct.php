<?php
declare(strict_types=1);
namespace App\Entity\Sales;
use App\Entity\Product\Product;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_product_expert_product', uniqueConstraints: [new ORM\UniqueConstraint(name: 'uniq_expert_product', columns: ['expert_id', 'product_id'])], indexes: [new ORM\Index(name: 'idx_expert_position', columns: ['expert_id', 'position'])])]
class ProductExpertProduct
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: ProductExpert::class, inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private ?ProductExpert $expert = null;
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private ?Product $product = null;
    #[ORM\Column(options: ['default' => 0])] private int $position = 0;
    #[ORM\Column] private \DateTimeImmutable $createdAt;
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
