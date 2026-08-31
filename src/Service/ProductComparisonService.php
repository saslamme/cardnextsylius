<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Channel\Channel;
use App\Entity\Product\Product;
use App\Entity\Product\PrinterAdvisorProfile;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProductComparisonService
{
    public const MAX_PRODUCTS = 3;

    /** @var array<string, array{section: string, position: int}> */
    private const PRESENTATION = [
        'CN_PRINTER_TECHNOLOGY' => ['section' => 'Druck', 'position' => 10],
        'CN_PRINT_SIDES' => ['section' => 'Druck', 'position' => 20],
        'CN_PRINT_RESOLUTION' => ['section' => 'Druck', 'position' => 30],
        'CN_PRINT_MODE' => ['section' => 'Druck', 'position' => 40],
        'CN_PRINT_SPEED' => ['section' => 'Druck', 'position' => 50],
        'CN_CARD_FORMAT' => ['section' => 'Kartenhandling', 'position' => 10],
        'CN_CARD_INPUT_CAPACITY' => ['section' => 'Kartenhandling', 'position' => 20],
        'CN_CARD_OUTPUT_CAPACITY' => ['section' => 'Kartenhandling', 'position' => 30],
        'CN_CARD_THICKNESS' => ['section' => 'Kartenhandling', 'position' => 40],
        'CN_ENCODING_OPTIONS' => ['section' => 'Kodierung', 'position' => 10],
        'CN_MAGNETIC_STRIPE' => ['section' => 'Kodierung', 'position' => 20],
        'CN_CARD_CHIP' => ['section' => 'Kodierung', 'position' => 30],
        'CN_RFID_FREQUENCY' => ['section' => 'Kodierung', 'position' => 40],
        'CN_RFID_TECHNOLOGIES' => ['section' => 'Kodierung', 'position' => 50],
        'CN_PRINTER_DISPLAY' => ['section' => 'Ausstattung', 'position' => 10],
        'CN_CONNECTIVITY' => ['section' => 'Schnittstellen', 'position' => 10],
        'CN_RFID_INTERFACE' => ['section' => 'Schnittstellen', 'position' => 20],
        'CN_SCANNER_INTERFACES' => ['section' => 'Schnittstellen', 'position' => 30],
        'CN_NETWORK_INTERFACES' => ['section' => 'Schnittstellen', 'position' => 40],
        'CN_WIRELESS' => ['section' => 'Schnittstellen', 'position' => 50],
        'CN_ADVISOR_DUPLEX' => ['section' => 'Druck', 'position' => 25],
        'CN_ADVISOR_MAGNETIC' => ['section' => 'Kodierung', 'position' => 20],
        'CN_ADVISOR_CONTACT_CHIP' => ['section' => 'Kodierung', 'position' => 30],
        'CN_ADVISOR_RFID_NFC' => ['section' => 'Kodierung', 'position' => 40],
        'CN_ADVISOR_LAMINATION' => ['section' => 'Ausstattung', 'position' => 20],
        'CN_ADVISOR_HIGH_DURABILITY' => ['section' => 'Ausstattung', 'position' => 30],
    ];

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /** @param list<string> $codes @return list<Product> */
    // @phpstan-ignore missingType.iterableValue
    public function findComparableProducts(array $codes, Channel $channel): array
    {
        $codes = array_slice(array_values(array_unique(array_filter(array_map('trim', $codes)))), 0, self::MAX_PRODUCTS);
        if ($codes === []) {
            return [];
        }

        /** @var list<Product> $found */
        $found = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT p, m, a, av, v, cp, pt, t, image, profile')
            ->from(Product::class, 'p')
            ->leftJoin('p.manufacturer', 'm')->leftJoin('p.attributes', 'av')->leftJoin('av.attribute', 'a')
            ->innerJoin('p.variants', 'v', 'WITH', 'v.enabled = true')
            ->innerJoin('v.channelPricings', 'cp', 'WITH', 'cp.channelCode = :channelCode AND cp.price IS NOT NULL')
            ->innerJoin('p.channels', 'ch', 'WITH', 'ch = :channel')
            ->leftJoin('p.productTaxons', 'pt')->leftJoin('pt.taxon', 't')->leftJoin('p.images', 'image')
            ->leftJoin('p.printerAdvisorProfile', 'profile')
            ->andWhere('p.enabled = true')->andWhere('p.code IN (:codes)')
            ->setParameter('channel', $channel)->setParameter('channelCode', $channel->getCode())->setParameter('codes', $codes)
            ->getQuery()->getResult();

        $byCode = [];
        foreach ($found as $product) {
            $byCode[$product->getCode()] = $product;
        }

        return array_values(array_filter(array_map(static fn (string $code): ?Product => $byCode[$code] ?? null, $codes)));
    }

    /** @param list<Product> $products @return array{products: list<array<string, mixed>>, sections: list<array<string, mixed>>, compatible: bool, group: ?string} */
    // @phpstan-ignore missingType.iterableValue
    public function build(array $products, Channel $channel, string $locale): array
    {
        $productGroups = array_map($this->comparisonGroup(...), $products);
        $groups = array_values(array_unique(array_filter($productGroups)));
        $compatible = count($products) < 2 || (count($groups) === 1 && !in_array(null, $productGroups, true));
        $columns = [];
        $valuesByCode = [];

        foreach ($products as $product) {
            $variant = null;
            $pricing = null;
            foreach ($product->getVariants() as $candidate) {
                // @phpstan-ignore method.notFound
                $candidatePricing = $candidate->getChannelPricingForChannel($channel);
                if ($candidate->isEnabled() && $candidatePricing?->getPrice() !== null) {
                    $variant = $candidate;
                    $pricing = $candidatePricing;
                    break;
                }
            }
            $columns[] = ['product' => $product, 'variant' => $variant, 'pricing' => $pricing, 'manufacturer' => $product->getManufacturer()?->getName(), 'model' => $product->getModel()];
            $valuesByCode[] = $this->productValues($product, $locale);
        }

        $allCodes = array_values(array_unique(array_merge(...array_map('array_keys', $valuesByCode ?: [[]]))));
        $sections = [];
        foreach ($allCodes as $code) {
            $first = null;
            foreach ($valuesByCode as $values) {
                if (isset($values[$code])) { $first = $values[$code]; break; }
            }
            if ($first === null) { continue; }
            $cells = array_map(static fn (array $values): array => $values[$code] ?? ['display' => '—', 'normalized' => ''], $valuesByCode);
            $normalized = array_column($cells, 'normalized');
            // @phpstan-ignore nullCoalesce.offset
            $config = self::PRESENTATION[$code] ?? ['section' => 'Weitere technische Daten', 'position' => 1000 + (int) ($first['position'] ?? 0)];
            $sections[$config['section']][] = ['code' => $code, 'label' => $first['label'], 'cells' => $cells, 'different' => count(array_unique($normalized)) > 1, 'position' => $config['position']];
        }
        foreach ($sections as &$rows) { usort($rows, static fn (array $a, array $b): int => $a['position'] <=> $b['position']); }

        return ['products' => $columns, 'sections' => array_map(static fn (string $title, array $rows): array => ['title' => $title, 'rows' => $rows], array_keys($sections), $sections), 'compatible' => $compatible, 'group' => $groups[0] ?? null];
    }

    private function comparisonGroup(Product $product): ?string
    {
        $taxon = $product->getMainTaxon();
        if ($taxon === null) { return null; }
        // @phpstan-ignore nullsafe.neverNull
        while ($taxon->getParent() !== null && $taxon->getParent()?->getCode() !== 'products') { $taxon = $taxon->getParent(); }
        return $taxon->getCode();
    }

    /** @return array<string, array{label: string, display: string, normalized: string, position: int}> */
    private function productValues(Product $product, string $locale): array
    {
        $result = [];
        foreach ($product->getAttributes() as $attributeValue) {
            if ($attributeValue->getLocaleCode() !== null && $attributeValue->getLocaleCode() !== $locale) { continue; }
            $value = $attributeValue->getValue();
            if ($value === null || $value === '' || $value === []) { continue; }
            $attribute = $attributeValue->getAttribute();
            if ($attribute === null) { continue; }
            $display = $this->formatValue($value, $attribute->getConfiguration(), $locale);
            $result[(string) $attribute->getCode()] = ['label' => (string) $attribute->getName(), 'display' => $display, 'normalized' => mb_strtolower(trim($display)), 'position' => (int) $attribute->getPosition()];
        }
        $profile = $product->getPrinterAdvisorProfile();
        if ($profile?->isEnabled()) { $this->addAdvisorValues($result, $profile); }
        return $result;
    }

    // @phpstan-ignore missingType.iterableValue
    private function formatValue(mixed $value, array $configuration, string $locale): string
    {
        if (is_bool($value)) { return $value ? '✓ Ja' : '— Nein'; }
        $items = is_array($value) ? $value : [$value];
        $choices = $configuration['choices'] ?? [];
        return implode(', ', array_map(static function (mixed $item) use ($choices, $locale): string {
            // @phpstan-ignore cast.string
            $label = $choices[(string) $item] ?? $item;
            if (is_array($label)) { $label = $label[$locale] ?? reset($label); }
            return (string) $label;
        }, $items));
    }

    /** @param array<string, array{label: string, display: string, normalized: string, position: int}> $values */
    private function addAdvisorValues(array &$values, PrinterAdvisorProfile $profile): void
    {
        $fallbacks = [
            'CN_ADVISOR_DUPLEX' => ['Beidseitiger Druck', $profile->isDuplex(), 'Druck', 25],
            'CN_ADVISOR_MAGNETIC' => ['Magnetstreifen-Kodierung', $profile->hasMagneticStripe(), 'Kodierung', 20],
            'CN_ADVISOR_CONTACT_CHIP' => ['Kontaktchip-Kodierung', $profile->hasContactChip(), 'Kodierung', 30],
            'CN_ADVISOR_RFID_NFC' => ['RFID / NFC-Kodierung', $profile->hasRfidNfc(), 'Kodierung', 40],
            'CN_ADVISOR_LAMINATION' => ['Laminierung', $profile->hasLamination(), 'Ausstattung', 20],
            'CN_ADVISOR_HIGH_DURABILITY' => ['Hochbeständiger Kartendruck', $profile->hasHighDurability(), 'Ausstattung', 30],
        ];
        foreach ($fallbacks as $code => [$label, $bool]) {
            if (($code === 'CN_ADVISOR_DUPLEX' && isset($values['CN_PRINT_SIDES'])) || (($code === 'CN_ADVISOR_MAGNETIC' || $code === 'CN_ADVISOR_CONTACT_CHIP' || $code === 'CN_ADVISOR_RFID_NFC') && isset($values['CN_ENCODING_OPTIONS']))) { continue; }
            $display = $bool ? '✓ Ja' : '— Nein';
            $values[$code] = ['label' => $label, 'display' => $display, 'normalized' => $bool ? '1' : '0', 'position' => 0];
        }
    }
}
