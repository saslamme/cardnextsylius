<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product\Product;
use App\Entity\Product\ProductAttribute;
use App\Entity\Product\ProductAttributeValue;
use App\Entity\Product\ProductVariant;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CardnextResearchAttributeCsvImporterFinal
{
    public function __construct(private EntityManagerInterface $em) {}

    /** @return array<string,mixed> */
    public function import(string $path, bool $dryRun = false, bool $overwrite = false, bool $includeAmbiguous = false): array
    {
        if (!is_readable($path)) throw new \RuntimeException(sprintf('CSV "%s" is not readable.', $path));
        $h = fopen($path, 'rb');
        if ($h === false) throw new \RuntimeException('Could not open CSV.');

        $header = fgetcsv($h, 0, ';', '"', '\\');
        if (!is_array($header)) throw new \RuntimeException('CSV header is missing.');
        $header = array_map(static fn($v) => ltrim(trim((string)$v), "\xEF\xBB\xBF"), $header);
        $idx = array_flip($header);
        foreach (['Hersteller','Titel','Hersteller-Artikelnummer','attributes_json','research_status'] as $col) {
            if (!isset($idx[$col])) throw new \RuntimeException(sprintf('Required CSV column "%s" is missing.', $col));
        }

        $variants = $this->em->getRepository(ProductVariant::class);
        $attributesRepo = $this->em->getRepository(ProductAttribute::class);
        $defs = [];
        $r = [
            'rows'=>0,'products_found'=>0,'products_missing'=>0,'ambiguous_matches'=>0,'status_skipped'=>0,
            'empty_attributes'=>0,'candidate_values'=>0,'values_would_write'=>0,'values_written'=>0,
            'existing_values_skipped'=>0,'slots_would_create'=>0,'slots_created'=>0,'unknown_attributes'=>0,
            'invalid_values'=>0,'manufacturer_mismatches'=>0,'changes'=>[],'warnings'=>[],
        ];

        while (($row = fgetcsv($h, 0, ';', '"', '\\')) !== false) {
            if ($row === [] || $row === [null]) continue;
            ++$r['rows'];
            $manufacturer = trim((string)($row[$idx['Hersteller']] ?? ''));
            $title = trim((string)($row[$idx['Titel']] ?? ''));
            $mpn = trim((string)($row[$idx['Hersteller-Artikelnummer']] ?? ''));
            $status = trim((string)($row[$idx['research_status']] ?? ''));
            $json = trim((string)($row[$idx['attributes_json']] ?? ''));

            if (!in_array($status, ['complete','partial'], true) && !($includeAmbiguous && $status === 'ambiguous')) {
                ++$r['status_skipped']; continue;
            }

            $normalized = self::normId($mpn);
            if ($normalized === '') { ++$r['products_missing']; continue; }

            /** @var list<ProductVariant> $matches */
            $matches = $variants->findBy(['manufacturerPartNumberNormalized'=>$normalized]);
            if (count($matches) > 1 && $manufacturer !== '') {
                $needle = self::normText($manufacturer);
                $filtered = array_values(array_filter($matches, static function(ProductVariant $v) use ($needle): bool {
                    $p = $v->getProduct();
                    return $p instanceof Product && self::normText($p->getManufacturer()?->getName() ?? '') === $needle;
                }));
                if ($filtered !== []) $matches = $filtered;
            }
            if ($matches === []) { ++$r['products_missing']; $this->warn($r, "$title: no variant found for MPN $mpn."); continue; }
            if (count($matches) !== 1) { ++$r['ambiguous_matches']; $this->warn($r, "$title: MPN $mpn matches ".count($matches).' variants.'); continue; }

            $product = $matches[0]->getProduct();
            if (!$product instanceof Product) { ++$r['products_missing']; continue; }
            ++$r['products_found'];

            if ($manufacturer !== '') {
                $shopManufacturer = $product->getManufacturer()?->getName() ?? '';
                if ($shopManufacturer !== '' && self::normText($manufacturer) !== self::normText($shopManufacturer)) {
                    ++$r['manufacturer_mismatches'];
                    $this->warn($r, sprintf('%s / %s: manufacturer "%s" differs from shop "%s".', $product->getCode(), $mpn, $manufacturer, $shopManufacturer));
                }
            }

            try { $data = $json !== '' ? json_decode($json, true, 512, JSON_THROW_ON_ERROR) : []; }
            catch (\JsonException $e) { ++$r['invalid_values']; $this->warn($r, "$mpn: invalid attributes_json."); continue; }
            if (!is_array($data) || $data === []) { ++$r['empty_attributes']; continue; }

            $slots = [];
            foreach ($product->getAttributes() as $slot) {
                if ($slot instanceof ProductAttributeValue && $slot->getCode() !== null) $slots[(string)$slot->getCode()] = $slot;
            }

            foreach ($data as $code => $raw) {
                $code = (string)$code;
                if (!str_starts_with($code, 'CN_')) { ++$r['invalid_values']; continue; }
                ++$r['candidate_values'];

                if (!array_key_exists($code, $defs)) $defs[$code] = $attributesRepo->findOneBy(['code'=>$code]);
                $def = $defs[$code];
                if (!$def instanceof ProductAttribute) { ++$r['unknown_attributes']; $this->warn($r, "$mpn: unknown attribute $code."); continue; }

                $slot = $slots[$code] ?? null;
                $isNew = !$slot instanceof ProductAttributeValue;
                if ($isNew) {
                    ++$r['slots_would_create'];
                    $slot = new ProductAttributeValue();
                    $slot->setAttribute($def);
                    $slot->setLocaleCode(null);
                }

                $value = $this->normalize($slot, $raw, $code);
                if (!$this->has($value)) { ++$r['invalid_values']; $this->warn($r, "$mpn: invalid value for $code."); continue; }
                $current = $isNew ? null : $slot->getValue();
                if (!$overwrite && $this->has($current)) { ++$r['existing_values_skipped']; continue; }
                if ($this->same($current, $value)) continue;

                ++$r['values_would_write'];
                if (count($r['changes']) < 150) $r['changes'][] = ['mpn'=>$mpn,'product'=>(string)$product->getCode(),'attribute'=>$code,'old'=>$current,'new'=>$value];

                if (!$dryRun) {
                    if ($isNew) {
                        $product->addAttribute($slot);
                        $this->em->persist($slot);
                        $slots[$code] = $slot;
                        ++$r['slots_created'];
                    }
                    $slot->setValue($value);
                    ++$r['values_written'];
                }
            }
        }
        fclose($h);
        if (!$dryRun) $this->em->flush();
        return $r;
    }

    private function normalize(ProductAttributeValue $slot, mixed $raw, string $code): mixed
    {
        $a = $slot->getAttribute(); if ($a === null) return null;
        return match ($a->getStorageType()) {
            'boolean' => is_bool($raw) ? $raw : (is_scalar($raw) ? filter_var((string)$raw, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) : null),
            'integer' => is_numeric($raw) ? (int)$raw : null,
            'float' => is_numeric(str_replace(',', '.', (string)$raw)) ? (float)str_replace(',', '.', (string)$raw) : null,
            'json' => $this->select($raw, (array)($a->getConfiguration()['choices'] ?? []), (bool)($a->getConfiguration()['multiple'] ?? false), $code),
            default => is_scalar($raw) ? (($v=trim((string)$raw)) !== '' ? $v : null) : null,
        };
    }

    /** @param array<string,mixed> $choices */
    private function select(mixed $raw, array $choices, bool $multiple, string $code): ?array
    {
        $values = is_array($raw) ? $raw : [$raw];
        $values = array_values(array_unique(array_filter(array_map(static fn($v)=>trim((string)$v), $values), static fn($v)=>$v!=='')));
        if ($choices !== [] && array_diff($values, array_keys($choices)) !== []) return null;
        if ($values === []) return null;
        if (!$multiple && count($values) !== 1) {
            if ($code === 'CN_RIBBON_TYPE' && isset($choices['other'])) return ['other'];
            return null;
        }
        return $values;
    }

    private function has(mixed $v): bool { return $v !== null && (!is_string($v) || trim($v) !== '') && (!is_array($v) || $v !== []); }
    private function same(mixed $a, mixed $b): bool {
        if (is_array($a) && is_array($b)) { $a=array_map('strval',$a); $b=array_map('strval',$b); sort($a); sort($b); return $a===$b; }
        return $a === $b;
    }
    /** @param array<string,mixed> $r */
    private function warn(array &$r, string $m): void { if (count($r['warnings']) < 100) $r['warnings'][]=$m; }
    private static function normId(string $v): string { return preg_replace('/[^a-z0-9]+/i','',mb_strtolower(trim($v))) ?? ''; }
    private static function normText(string $v): string { $v=mb_strtolower(trim($v)); $v=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$v) ?: $v; return preg_replace('/[^a-z0-9]+/i','',$v) ?? ''; }
}
