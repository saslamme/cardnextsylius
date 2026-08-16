<?php

declare(strict_types=1);

namespace App\Entity\Product;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cardnext_device_model_alias')]
#[ORM\UniqueConstraint(name: 'UNIQ_CN_DEVICE_ALIAS_NORMALIZED', columns: ['normalized_alias'])]
class DeviceModelAlias
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DeviceModel::class, inversedBy: 'aliases')]
    #[ORM\JoinColumn(name: 'device_model_id', nullable: false, onDelete: 'CASCADE')]
    private DeviceModel $deviceModel;

    #[ORM\Column(length: 255)]
    private string $alias = '';

    #[ORM\Column(name: 'normalized_alias', length: 255)]
    private string $normalizedAlias = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDeviceModel(): DeviceModel
    {
        return $this->deviceModel;
    }

    public function setDeviceModel(DeviceModel $deviceModel): void
    {
        $this->deviceModel = $deviceModel;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function setAlias(string $alias): void
    {
        $this->alias = trim($alias);
        $this->normalizedAlias = self::normalize($alias);
    }

    public function getNormalizedAlias(): string
    {
        return $this->normalizedAlias;
    }

    public static function normalize(string $value): string
    {
        return mb_strtoupper((string) preg_replace('/[^\pL\pN]+/u', '', trim($value)));
    }

    public function __toString(): string
    {
        return $this->alias;
    }
}
