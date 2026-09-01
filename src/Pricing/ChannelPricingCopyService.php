<?php

declare(strict_types=1);

namespace App\Pricing;

use App\Entity\Channel\Channel;
use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

final readonly class ChannelPricingCopyService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FactoryInterface $channelPricingFactory,
        private LoggerInterface $logger,
    ) {
    }

    public function copy(Channel $source, Channel $target, string $adjustment = '0', bool $overwrite = false, bool $dryRun = true, int $sampleSize = 20): ChannelPricingCopyResult
    {
        $this->validateChannels($source, $target);
        [$numerator, $denominator] = $this->adjustmentMultiplier($adjustment);

        $operation = function () use ($source, $target, $adjustment, $numerator, $denominator, $overwrite, $dryRun, $sampleSize): ChannelPricingCopyResult {
            $result = new ChannelPricingCopyResult();
            $variants = $this->entityManager->createQueryBuilder()
                ->select('variant', 'product', 'productChannels', 'pricing')
                ->from(ProductVariant::class, 'variant')
                ->join('variant.product', 'product')
                ->leftJoin('product.channels', 'productChannels')
                ->leftJoin('variant.channelPricings', 'pricing')
                ->andWhere('variant.enabled = true')
                ->getQuery()->getResult();
            if (!is_iterable($variants)) {
                throw new \LogicException('The variant query did not return an iterable result.');
            }

            foreach ($variants as $variant) {
                \assert($variant instanceof ProductVariant);
                ++$result->scanned;
                $product = $variant->getProduct();
                $sourcePricing = $variant->getChannelPricingForChannel($source);
                $targetPricing = $variant->getChannelPricingForChannel($target);
                $sourcePrice = $sourcePricing?->getPrice();
                $action = 'CREATE';
                $newPrice = null;

                if (!$product instanceof Product || !$product->hasChannel($target)) {
                    ++$result->skippedNotInTargetChannel;
                    $action = 'SKIP_NOT_IN_TARGET_CHANNEL';
                } elseif ($sourcePricing === null || $sourcePrice === null) {
                    ++$result->skippedMissingSource;
                    $action = 'SKIP_NO_SOURCE';
                } elseif ($sourcePrice < 0) {
                    ++$result->skippedInvalidSource;
                    $action = 'SKIP_INVALID_SOURCE';
                } else {
                    ++$result->eligible;
                    $newPrice = $this->roundRatio($sourcePrice * $numerator, $denominator);
                    if ($newPrice < 0) {
                        throw new \InvalidArgumentException('Die Preisanpassung würde einen negativen Preis erzeugen.');
                    }
                    if ($targetPricing !== null && !$overwrite) {
                        ++$result->skippedExisting;
                        $action = 'SKIP_EXISTS';
                    } elseif ($targetPricing !== null) {
                        ++$result->overwritten;
                        $action = 'OVERWRITE';
                    } else {
                        ++$result->created;
                    }

                    if (!$dryRun && $action === 'CREATE') {
                        $targetPricing = $this->channelPricingFactory->createNew();
                        if (!$targetPricing instanceof ChannelPricingInterface) {
                            throw new \LogicException('The configured channel pricing factory returned an invalid resource.');
                        }
                        $targetPricing->setChannelCode((string) $target->getCode());
                        $variant->addChannelPricing($targetPricing);
                    }
                    if (!$dryRun && ($action === 'CREATE' || $action === 'OVERWRITE')) {
                        $targetPricing?->setPrice($newPrice);
                        $original = $sourcePricing->getOriginalPrice();
                        $targetPricing?->setOriginalPrice($original === null ? null : $this->roundRatio($original * $numerator, $denominator));
                    }
                }

                if (count($result->rows) < $sampleSize) {
                    $result->rows[] = new ChannelPricingCopyRow(
                        (string) ($product?->getName() ?? $product?->getCode() ?? '–'),
                        (string) ($variant->getName() ?? $variant->getCode() ?? '–'),
                        $variant->getCode(),
                        $sourcePrice,
                        $targetPricing?->getPrice(),
                        $newPrice,
                        $action,
                    );
                }
            }

            if (!$dryRun) {
                $this->entityManager->flush();
                $this->logger->info('Native Sylius channel prices copied.', [
                    'source' => $source->getCode(), 'target' => $target->getCode(), 'adjustment' => $adjustment,
                    'overwrite' => $overwrite, 'created' => $result->created, 'overwritten' => $result->overwritten, 'skipped' => $result->skipped(),
                ]);
            }

            return $result;
        };

        return $dryRun ? $operation() : $this->entityManager->wrapInTransaction($operation);
    }

    /** Calculates once in integer minor units and rounds half up at the final division. */
    public function adjustedPrice(int $price, string $adjustment): int
    {
        if ($price < 0) {
            throw new \InvalidArgumentException('Der Quellpreis darf nicht negativ sein.');
        }
        [$numerator, $denominator] = $this->adjustmentMultiplier($adjustment);

        return $this->roundRatio($price * $numerator, $denominator);
    }

    private function validateChannels(Channel $source, Channel $target): void
    {
        if ($source === $target || $source->getCode() === $target->getCode()) {
            throw new \InvalidArgumentException('Quell- und Ziel-Channel müssen unterschiedlich sein.');
        }
        $sourceCurrency = $source->getBaseCurrency()?->getCode();
        if ($sourceCurrency === null || $sourceCurrency !== $target->getBaseCurrency()?->getCode()) {
            throw new \InvalidArgumentException('Quell- und Ziel-Channel müssen dieselbe Basiswährung verwenden. Eine Währungsumrechnung findet nicht statt.');
        }
    }

    /** @return array{int, int} */
    private function adjustmentMultiplier(string $adjustment): array
    {
        if (!preg_match('/^([+-]?)(\d+)(?:\.(\d{1,4}))?$/', trim($adjustment), $parts)) {
            throw new \InvalidArgumentException('Die Preisanpassung muss eine Zahl mit höchstens vier Nachkommastellen sein.');
        }
        $scaledPercent = ((int) $parts[2] * 10000) + (int) str_pad($parts[3] ?? '', 4, '0');
        if ($parts[1] === '-') {
            $scaledPercent *= -1;
        }
        $numerator = 1000000 + $scaledPercent;
        if ($numerator < 0) {
            throw new \InvalidArgumentException('Die Preisanpassung würde einen negativen Preis erzeugen.');
        }

        return [$numerator, 1000000];
    }

    private function roundRatio(int $numerator, int $denominator): int
    {
        return intdiv($numerator + intdiv($denominator, 2), $denominator);
    }
}
