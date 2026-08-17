<?php

declare(strict_types=1);

namespace App\Entity\Product;

use App\Validator\PublicSlugUnique;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\ProductTranslation as BaseProductTranslation;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_product_translation')]
#[PublicSlugUnique]
#[UniqueEntity(fields: ['locale', 'configuratorPath'], message: 'cardnext.configurator_path.unique', ignoreNull: true)]
class ProductTranslation extends BaseProductTranslation
{
    #[ORM\Column(name: 'configurator_path', length: 512, nullable: true)]
    private ?string $configuratorPath = null;

    public function getConfiguratorPath(): ?string
    {
        return $this->configuratorPath;
    }

    public function setConfiguratorPath(?string $configuratorPath): void
    {
        if ($configuratorPath === null || trim($configuratorPath) === '') {
            $this->configuratorPath = null;

            return;
        }

        $path = trim($configuratorPath);
        if (str_starts_with($path, '/') && !str_starts_with($path, '//')) {
            $path = substr($path, 1);
        }
        if (str_ends_with($path, '/') && !str_ends_with($path, '//')) {
            $path = substr($path, 0, -1);
        }

        $this->configuratorPath = $path;
    }

    #[Assert\Callback]
    public function validateConfiguratorPath(ExecutionContextInterface $context): void
    {
        $product = $this->getTranslatable();
        if (!$product instanceof Product) {
            return;
        }

        if (!$product->isConfigurable()) {
            if ($this->configuratorPath !== null) {
                $context->buildViolation('cardnext.configurator_path.configurable_only')->atPath('configuratorPath')->addViolation();
            }

            return;
        }

        if ($this->configuratorPath === null) {
            $context->buildViolation('cardnext.configurator_path.required')->atPath('configuratorPath')->addViolation();

            return;
        }

        if (str_contains($this->configuratorPath, '://') || str_starts_with($this->configuratorPath, '//') || str_contains($this->configuratorPath, '?') || str_contains($this->configuratorPath, '#') || str_contains($this->configuratorPath, '..') || str_contains($this->configuratorPath, '//') || preg_match('~^[\\p{L}\\p{N}](?:[\\p{L}\\p{N}_-]*[\\p{L}\\p{N}])?(?:/[\\p{L}\\p{N}](?:[\\p{L}\\p{N}_-]*[\\p{L}\\p{N}])?)*$~u', $this->configuratorPath) !== 1) {
            $context->buildViolation('cardnext.configurator_path.invalid')->atPath('configuratorPath')->addViolation();
        }
    }
}
