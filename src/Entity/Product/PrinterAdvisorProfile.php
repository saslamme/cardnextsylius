<?php

declare(strict_types=1);

namespace App\Entity\Product;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_printer_advisor_profile')]
class PrinterAdvisorProfile
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'printerAdvisorProfile', targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    // @phpstan-ignore doctrine.associationType
    private ?Product $product = null;

    #[ORM\Column(name: 'enabled', options: ['default' => false])]
    private bool $enabled = false;

    #[ORM\Column(name: 'priority', options: ['default' => 0])]
    #[Assert\Range(min: -100, max: 100)]
    private int $priority = 0;

    #[ORM\Column(name: 'min_annual_volume', options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $minAnnualVolume = 0;

    #[ORM\Column(name: 'max_annual_volume', nullable: true)]
    #[Assert\Positive]
    private ?int $maxAnnualVolume = null;

    #[ORM\Column(name: 'single_sided', options: ['default' => true])]
    private bool $singleSided = true;

    #[ORM\Column(name: 'duplex', options: ['default' => false])]
    private bool $duplex = false;

    #[ORM\Column(name: 'magnetic_stripe', options: ['default' => false])]
    private bool $magneticStripe = false;

    #[ORM\Column(name: 'contact_chip', options: ['default' => false])]
    private bool $contactChip = false;

    #[ORM\Column(name: 'rfid_nfc', options: ['default' => false])]
    private bool $rfidNfc = false;

    #[ORM\Column(name: 'direct_printing', options: ['default' => true])]
    private bool $directPrinting = true;

    #[ORM\Column(name: 'retransfer', options: ['default' => false])]
    private bool $retransfer = false;

    #[ORM\Column(name: 'lamination', options: ['default' => false])]
    private bool $lamination = false;

    #[ORM\Column(name: 'high_durability', options: ['default' => false])]
    private bool $highDurability = false;

    #[ORM\Column(name: 'performance_class', type: 'smallint', options: ['default' => 1])]
    #[Assert\Range(min: 1, max: 5)]
    private int $performanceClass = 1;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): void
    {
        $this->product = $product;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function getMinAnnualVolume(): int
    {
        return $this->minAnnualVolume;
    }

    public function setMinAnnualVolume(int $volume): void
    {
        $this->minAnnualVolume = $volume;
    }

    public function getMaxAnnualVolume(): ?int
    {
        return $this->maxAnnualVolume;
    }

    public function setMaxAnnualVolume(?int $volume): void
    {
        $this->maxAnnualVolume = $volume;
    }

    public function isSingleSided(): bool
    {
        return $this->singleSided;
    }

    public function setSingleSided(bool $value): void
    {
        $this->singleSided = $value;
    }

    public function isDuplex(): bool
    {
        return $this->duplex;
    }

    public function setDuplex(bool $value): void
    {
        $this->duplex = $value;
    }

    public function hasMagneticStripe(): bool
    {
        return $this->magneticStripe;
    }

    public function setMagneticStripe(bool $value): void
    {
        $this->magneticStripe = $value;
    }

    public function hasContactChip(): bool
    {
        return $this->contactChip;
    }

    public function setContactChip(bool $value): void
    {
        $this->contactChip = $value;
    }

    public function hasRfidNfc(): bool
    {
        return $this->rfidNfc;
    }

    public function setRfidNfc(bool $value): void
    {
        $this->rfidNfc = $value;
    }

    public function isDirectPrinting(): bool
    {
        return $this->directPrinting;
    }

    public function setDirectPrinting(bool $value): void
    {
        $this->directPrinting = $value;
    }

    public function isRetransfer(): bool
    {
        return $this->retransfer;
    }

    public function setRetransfer(bool $value): void
    {
        $this->retransfer = $value;
    }

    public function hasLamination(): bool
    {
        return $this->lamination;
    }

    public function setLamination(bool $value): void
    {
        $this->lamination = $value;
    }

    public function hasHighDurability(): bool
    {
        return $this->highDurability;
    }

    public function setHighDurability(bool $value): void
    {
        $this->highDurability = $value;
    }

    public function getPerformanceClass(): int
    {
        return $this->performanceClass;
    }

    public function setPerformanceClass(int $value): void
    {
        $this->performanceClass = $value;
    }

    #[Assert\Callback]
    public function validateVolume(ExecutionContextInterface $context): void
    {
        if ($this->maxAnnualVolume !== null && $this->maxAnnualVolume <= $this->minAnnualVolume) {
            $context->buildViolation('Das maximale Volumen muss größer als das Mindestvolumen sein.')
                ->atPath('maxAnnualVolume')->addViolation();
        }
        if (!$this->singleSided && !$this->duplex) {
            $context->buildViolation('Mindestens eine Druckseite muss unterstützt werden.')
                ->atPath('singleSided')->addViolation();
        }
    }
}
