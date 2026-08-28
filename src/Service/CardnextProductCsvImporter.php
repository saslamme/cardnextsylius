<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Channel\Channel;
use App\Entity\Channel\ChannelPricing;
use App\Entity\Customer\Customer;
use App\Entity\Customer\CustomerGroup;
use App\Entity\Product\CustomerVariantPriceRule;
use App\Entity\Product\DeviceModel;
use App\Entity\Product\Manufacturer;
use App\Entity\Product\Product;
use App\Entity\Product\ProductAttribute;
use App\Entity\Product\ProductAttributeValue;
use App\Entity\Product\ProductCompatibility;
use App\Entity\Product\ProductDeviceCompatibility;
use App\Entity\Product\ProductDocument;
use App\Entity\Product\ProductImage;
use App\Entity\Product\ProductTaxon;
use App\Entity\Product\ProductVariant;
use App\Entity\Product\VariantPriceRule;
use App\Entity\Taxation\TaxCategory;
use App\Entity\Taxonomy\Taxon;
use App\Repository\Product\DeviceModelRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class CardnextProductCsvImporter
{
    private const REQUIRED_COLUMNS = [
        'product_code',
        'variant_code',
        'locale',
        'name',
        'taxon_code',
        'channel_codes',
        'prices_json',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
        private ProductAttributeProfileService $attributeProfiles,
    ) {
    }

    /**
     * @return array{
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
     *   device_compatibilities_created:int,
     *   device_compatibilities_updated:int,
     *   products_created:int,
     *   products_updated:int,
     *   variants_created:int,
     *   variants_updated:int,
     *   warnings:list<string>
     * }
     */
    public function import(
        string $csvPath,
        bool $dryRun = false,
        ?string $imageDirectory = null,
        ?string $manufacturerLogoDirectory = null,
        ?string $documentDirectory = null,
    ): array {
        if (!is_file($csvPath) || !is_readable($csvPath)) {
            throw new \RuntimeException(sprintf('CSV file "%s" does not exist or is not readable.', $csvPath));
        }

        $handle = fopen($csvPath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Unable to open CSV file "%s".', $csvPath));
        }

        // Excel/UTF-8 CSV files may start with a UTF-8 BOM. It has to be
        // consumed BEFORE fgetcsv() parses the first quoted field, otherwise
        // the opening quote is treated as literal text and "product_code"
        // becomes an invalid header name.
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle, 0, ';');
        if (!is_array($header)) {
            fclose($handle);

            throw new \RuntimeException('The CSV file has no header row.');
        }

        $header = array_map(
            static fn (string $column): string => trim($column),
            $header,
        );

        foreach (self::REQUIRED_COLUMNS as $requiredColumn) {
            if (!in_array($requiredColumn, $header, true)) {
                fclose($handle);

                throw new \RuntimeException(sprintf('Required CSV column "%s" is missing.', $requiredColumn));
            }
        }

        $result = [
            'rows' => 0,
            'manufacturers_created' => 0,
            'manufacturers_updated' => 0,
            'documents_created' => 0,
            'documents_updated' => 0,
            'compatibilities_created' => 0,
            'compatibilities_updated' => 0,
            'price_rules_created' => 0,
            'price_rules_updated' => 0,
            'customer_price_rules_created' => 0,
            'customer_price_rules_updated' => 0,
            'device_compatibilities_created' => 0,
            'device_compatibilities_updated' => 0,
            'products_created' => 0,
            'products_updated' => 0,
            'variants_created' => 0,
            'variants_updated' => 0,
            'warnings' => [],
        ];

        $seenProducts = [];
        $seenManufacturers = [];
        $seenDocuments = [];
        $productsByCode = [];
        $pendingCompatibilities = [];
        $compatibilityEntities = [];
        $rowNumber = 1;

        if (!$dryRun) {
            $this->entityManager->getConnection()->beginTransaction();
        }

        try {
            while (($values = fgetcsv($handle, 0, ';')) !== false) {
                ++$rowNumber;

                if ($values === [null] || $values === []) {
                    continue;
                }

                if (count($values) !== count($header)) {
                    throw new \RuntimeException(sprintf(
                        'Row %d contains %d columns; expected %d.',
                        $rowNumber,
                        count($values),
                        count($header),
                    ));
                }

                $row = array_combine($header, $values);
                if (!is_array($row)) {
                    throw new \RuntimeException(sprintf('Could not parse row %d.', $rowNumber));
                }

                $row = array_map(
                    static fn ($value): string => is_string($value) ? trim($value) : '',
                    $row,
                );

                if (($row['product_code'] ?? '') === '') {
                    continue;
                }

                ++$result['rows'];

                $productCode = $row['product_code'];
                $variantCode = $row['variant_code'];
                $locale = $row['locale'] !== '' ? $row['locale'] : 'de_DE';

                if ($variantCode === '') {
                    throw new \RuntimeException(sprintf('Row %d: variant_code must not be empty.', $rowNumber));
                }

                /** @var Product|null $product */
                $product = $this->entityManager->getRepository(Product::class)->findOneBy(['code' => $productCode]);
                $productIsNew = $product === null;

                if ($productIsNew) {
                    $product = new Product();
                    $product->setCode($productCode);
                    $product->setEnabled($this->toBool($row['enabled'] ?? '1'));
                    $this->entityManager->persist($product);
                }

                $product->setCurrentLocale($locale);
                $product->setFallbackLocale($locale);

                if (($row['model'] ?? '') !== '') {
                    $product->setModel($row['model']);
                }
                if (!$productIsNew && $product->getDataQualityStatus() === 'verified') {
                    // A routine import never silently removes a manual verification.
                } else {
                    $requestedStatus = $row['data_quality_status'] ?? 'imported';
                    $product->setDataQualityStatus($requestedStatus === 'needs_review' ? 'needs_review' : 'imported');
                }

                if (($row['name'] ?? '') !== '') {
                    $product->setName($row['name']);
                }

                $slug = $row['slug'] ?? '';
                if ($slug === '') {
                    $slug = strtolower((string) $this->slugger->slug($row['name'] !== '' ? $row['name'] : $productCode));
                }
                $product->setSlug($slug);

                if (array_key_exists('short_description', $row) && $row['short_description'] !== '') {
                    $product->setShortDescription($row['short_description']);
                }
                if (array_key_exists('description', $row) && $row['description'] !== '') {
                    $product->setDescription($row['description']);
                }
                if (array_key_exists('meta_keywords', $row) && $row['meta_keywords'] !== '') {
                    $product->setMetaKeywords($row['meta_keywords']);
                }
                if (array_key_exists('meta_description', $row) && $row['meta_description'] !== '') {
                    $product->setMetaDescription($row['meta_description']);
                }

                $manufacturerResult = $this->upsertManufacturer(
                    $row,
                    $rowNumber,
                    $manufacturerLogoDirectory,
                    $result['warnings'],
                    $dryRun,
                );

                if ($manufacturerResult['manufacturer'] instanceof Manufacturer) {
                    $product->setManufacturer($manufacturerResult['manufacturer']);

                    $manufacturerCode = $manufacturerResult['manufacturer']->getCode();
                    if (!isset($seenManufacturers[$manufacturerCode])) {
                        if ($manufacturerResult['created']) {
                            ++$result['manufacturers_created'];
                        } elseif ($manufacturerResult['updated']) {
                            ++$result['manufacturers_updated'];
                        }

                        $seenManufacturers[$manufacturerCode] = true;
                    }
                }

                if (($row['name'] ?? '') === '' || !($manufacturerResult['manufacturer'] instanceof Manufacturer)) {
                    $product->setDataQualityStatus('needs_review');
                    $result['warnings'][] = sprintf('Row %d: product is missing a name or manufacturer and requires review.', $rowNumber);
                }

                $taxon = $this->assignTaxon($product, $row['taxon_code'], $rowNumber);
                $this->assignChannels($product, $row['channel_codes'], $rowNumber);

                $productsByCode[$productCode] = $product;

                if (($row['compatibilities_json'] ?? '') !== '') {
                    $pendingCompatibilities[] = [
                        'product' => $product,
                        'json' => $row['compatibilities_json'],
                        'row_number' => $rowNumber,
                    ];
                }

                if (!isset($seenProducts[$productCode])) {
                    if ($productIsNew) {
                        ++$result['products_created'];
                    } else {
                        ++$result['products_updated'];
                    }
                    $seenProducts[$productCode] = true;
                }

                /** @var ProductVariant|null $variant */
                $variant = $this->entityManager->getRepository(ProductVariant::class)->findOneBy(['code' => $variantCode]);
                $variantIsNew = $variant === null;

                if ($variantIsNew) {
                    $variant = new ProductVariant();
                    $variant->setCode($variantCode);
                    $product->addVariant($variant);
                    $this->entityManager->persist($variant);
                } elseif ($variant->getProduct() !== $product) {
                    throw new \RuntimeException(sprintf(
                        'Row %d: variant "%s" already belongs to another product.',
                        $rowNumber,
                        $variantCode,
                    ));
                }

                $variant->setCurrentLocale($locale);
                $variant->setFallbackLocale($locale);
                $variant->setName(($row['variant_name'] ?? '') !== '' ? $row['variant_name'] : 'Standard');
                $variant->setEnabled($this->toBool($row['variant_enabled'] ?? '1'));
                $variant->setTracked($this->toBool($row['tracked'] ?? '1'));
                $variant->setOnHand($this->toInt($row['stock'] ?? '0'));
                $variant->setShippingRequired($this->toBool($row['shipping_required'] ?? '1'));
                if (array_key_exists('manufacturer_part_number', $row)) {
                    $variant->setManufacturerPartNumber($row['manufacturer_part_number']);
                }
                if (array_key_exists('gtin', $row)) {
                    $variant->setGtin($row['gtin']);
                }

                if (($row['minimum_order_quantity'] ?? '') !== '') {
                    $variant->setMinimumOrderQuantity($this->toInt($row['minimum_order_quantity']));
                }
                if (($row['order_increment'] ?? '') !== '') {
                    $variant->setOrderIncrement($this->toInt($row['order_increment']));
                }
                if (($row['pack_quantity'] ?? '') !== '') {
                    $variant->setPackQuantity($this->toInt($row['pack_quantity']));
                }

                foreach (['weight', 'width', 'height', 'depth'] as $dimension) {
                    if (($row[$dimension] ?? '') !== '') {
                        $setter = 'set' . ucfirst($dimension);
                        $variant->{$setter}((float) str_replace(',', '.', $row[$dimension]));
                    }
                }

                $taxCategoryCode = $row['tax_category_code'] ?? '';
                if ($taxCategoryCode !== '') {
                    /** @var TaxCategory|null $taxCategory */
                    $taxCategory = $this->entityManager->getRepository(TaxCategory::class)->findOneBy(['code' => $taxCategoryCode]);
                    if ($taxCategory === null) {
                        throw new \RuntimeException(sprintf(
                            'Row %d: tax category "%s" was not found.',
                            $rowNumber,
                            $taxCategoryCode,
                        ));
                    }
                    $variant->setTaxCategory($taxCategory);
                }

                $this->assignPrices($variant, $row['prices_json'], $rowNumber);

                if (($row['b2b_prices_json'] ?? '') !== '') {
                    $this->assignB2BPrices(
                        $variant,
                        $row['b2b_prices_json'],
                        $rowNumber,
                        $result,
                    );
                }

                if (($row['customer_prices_json'] ?? '') !== '') {
                    $this->assignCustomerPrices(
                        $variant,
                        $row['customer_prices_json'],
                        $rowNumber,
                        $result,
                    );
                }

                if (($row['device_compatibilities_json'] ?? '') !== '') {
                    $this->assignDeviceCompatibilities($product, $row['device_compatibilities_json'], $rowNumber, $result);
                }

                $this->assignAttributes($product, $row['attributes_json'] ?? '', $rowNumber, $result['warnings']);

                if (($row['documents_json'] ?? '') !== '') {
                    $this->assignDocuments(
                        $product,
                        $row['documents_json'],
                        $documentDirectory,
                        $rowNumber,
                        $result,
                        $seenDocuments,
                        $dryRun,
                    );
                }

                if (($row['images'] ?? '') !== '' && $imageDirectory !== null) {
                    $this->assignImages($product, $row['images'], $imageDirectory, $rowNumber, $result['warnings'], $dryRun);
                }

                if ($variantIsNew) {
                    ++$result['variants_created'];
                } else {
                    ++$result['variants_updated'];
                }

                if (!$dryRun) {
                    $this->entityManager->flush();
                }

                // The main taxon is also used to resolve the Phase 7 attribute profile.
                if (!$dryRun && $taxon !== null) {
                    $this->attributeProfiles->applyToProduct($product);
                }
            }

            foreach ($pendingCompatibilities as $pendingCompatibility) {
                $this->assignCompatibilities(
                    $pendingCompatibility['product'],
                    $pendingCompatibility['json'],
                    $pendingCompatibility['row_number'],
                    $productsByCode,
                    $compatibilityEntities,
                    $result,
                );
            }

            fclose($handle);

            if ($dryRun) {
                $this->entityManager->clear();
            } else {
                $this->entityManager->flush();
                $this->entityManager->getConnection()->commit();
            }

            return $result;
        } catch (\Throwable $exception) {
            fclose($handle);

            if (!$dryRun && $this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->getConnection()->rollBack();
            }

            // A failed dry-run may already have changed managed entities in memory.
            // Clearing here prevents a later flush (for example the admin import
            // history record) from accidentally persisting those changes.
            $this->entityManager->clear();

            throw $exception;
        }
    }

    /**
     * @param array<string, string> $row
     * @param list<string> $warnings
     *
     * @return array{manufacturer:?Manufacturer, created:bool, updated:bool}
     */
    private function upsertManufacturer(
        array $row,
        int $rowNumber,
        ?string $logoDirectory,
        array &$warnings,
        bool $dryRun,
    ): array {
        $manufacturerCode = strtoupper(trim($row['manufacturer_code'] ?? ''));
        $manufacturerName = trim($row['manufacturer_name'] ?? '');

        if ($manufacturerCode === '') {
            if ($manufacturerName !== '') {
                throw new \RuntimeException(sprintf(
                    'Row %d: manufacturer_name is set but manufacturer_code is empty.',
                    $rowNumber,
                ));
            }

            return [
                'manufacturer' => null,
                'created' => false,
                'updated' => false,
            ];
        }

        /** @var Manufacturer|null $manufacturer */
        $manufacturer = $this->entityManager
            ->getRepository(Manufacturer::class)
            ->findOneBy(['code' => $manufacturerCode]);

        $created = $manufacturer === null;
        $updated = false;

        if ($manufacturer === null) {
            if ($manufacturerName === '') {
                throw new \RuntimeException(sprintf(
                    'Row %d: manufacturer "%s" does not exist. Add manufacturer_name so it can be created automatically.',
                    $rowNumber,
                    $manufacturerCode,
                ));
            }

            $manufacturer = new Manufacturer();
            $manufacturer->setCode($manufacturerCode);
            $manufacturer->setName($manufacturerName);
            $manufacturer->setSlug(strtolower((string) $this->slugger->slug($manufacturerName)));
            $manufacturer->setEnabled(
                ($row['manufacturer_enabled'] ?? '') !== ''
                    ? $this->toBool($row['manufacturer_enabled'])
                    : true,
            );
            $manufacturer->setPosition(
                ($row['manufacturer_position'] ?? '') !== ''
                    ? $this->toInt($row['manufacturer_position'])
                    : 0,
            );

            if (($row['manufacturer_website'] ?? '') !== '') {
                $manufacturer->setWebsite($row['manufacturer_website']);
            }
            if (($row['manufacturer_description'] ?? '') !== '') {
                $manufacturer->setDescription($row['manufacturer_description']);
            }

            $this->entityManager->persist($manufacturer);
        } else {
            if ($manufacturerName !== '' && $manufacturerName !== $manufacturer->getName()) {
                $manufacturer->setName($manufacturerName);
                $updated = true;
            }

            if (($row['manufacturer_website'] ?? '') !== '') {
                $website = trim($row['manufacturer_website']);
                if ($website !== (string) $manufacturer->getWebsite()) {
                    $manufacturer->setWebsite($website);
                    $updated = true;
                }
            }

            if (($row['manufacturer_description'] ?? '') !== '') {
                $description = trim($row['manufacturer_description']);
                if ($description !== (string) $manufacturer->getDescription()) {
                    $manufacturer->setDescription($description);
                    $updated = true;
                }
            }

            if (($row['manufacturer_enabled'] ?? '') !== '') {
                $enabled = $this->toBool($row['manufacturer_enabled']);
                if ($enabled !== $manufacturer->isEnabled()) {
                    $manufacturer->setEnabled($enabled);
                    $updated = true;
                }
            }

            if (($row['manufacturer_position'] ?? '') !== '') {
                $position = $this->toInt($row['manufacturer_position']);
                if ($position !== $manufacturer->getPosition()) {
                    $manufacturer->setPosition($position);
                    $updated = true;
                }
            }
        }

        $logoFilename = trim($row['manufacturer_logo'] ?? '');
        if ($logoFilename !== '') {
            if ($logoDirectory === null) {
                $warnings[] = sprintf(
                    'Row %d: manufacturer logo "%s" was specified, but no manufacturer logo directory is configured.',
                    $rowNumber,
                    basename($logoFilename),
                );
            } else {
                $updated = $this->assignManufacturerLogo(
                    $manufacturer,
                    $logoFilename,
                    $logoDirectory,
                    $rowNumber,
                    $warnings,
                    $dryRun,
                ) || $updated;
            }
        }

        return [
            'manufacturer' => $manufacturer,
            'created' => $created,
            'updated' => $updated,
        ];
    }

    /**
     * @param list<string> $warnings
     */
    private function assignManufacturerLogo(
        Manufacturer $manufacturer,
        string $filename,
        string $logoDirectory,
        int $rowNumber,
        array &$warnings,
        bool $dryRun,
    ): bool {
        $safeFilename = basename($filename);
        $source = rtrim($logoDirectory, '/') . '/' . $safeFilename;

        if (!is_file($source) || !is_readable($source)) {
            $warnings[] = sprintf(
                'Row %d: manufacturer logo "%s" was not found; skipped.',
                $rowNumber,
                $safeFilename,
            );

            return false;
        }

        $extension = strtolower(pathinfo($safeFilename, \PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $warnings[] = sprintf(
                'Row %d: manufacturer logo "%s" has an unsupported extension; skipped.',
                $rowNumber,
                $safeFilename,
            );

            return false;
        }

        $filenameWithoutExtension = pathinfo($safeFilename, \PATHINFO_FILENAME);
        $targetFilename = sprintf(
            '%s-%s.%s',
            strtolower((string) $this->slugger->slug($manufacturer->getCode())),
            strtolower((string) $this->slugger->slug($filenameWithoutExtension)),
            $extension === 'jpeg' ? 'jpg' : $extension,
        );

        $directory = 'media/cardnext/manufacturers';
        $relativePath = $directory . '/' . $targetFilename;

        if ($manufacturer->getLogoPath() === $relativePath) {
            if (!$dryRun) {
                $filesystem = new Filesystem();
                $publicDirectory = dirname(__DIR__, 2) . '/public/' . $directory;
                $filesystem->mkdir($publicDirectory, 0775);
                $filesystem->copy($source, $publicDirectory . '/' . $targetFilename, true);
            }

            return false;
        }

        $oldPath = $manufacturer->getLogoPath();

        if (!$dryRun) {
            $filesystem = new Filesystem();
            $publicDirectory = dirname(__DIR__, 2) . '/public/' . $directory;
            $filesystem->mkdir($publicDirectory, 0775);
            $filesystem->copy($source, $publicDirectory . '/' . $targetFilename, true);

            if (
                $oldPath !== null &&
                $oldPath !== $relativePath &&
                str_starts_with($oldPath, $directory . '/')
            ) {
                $oldAbsolutePath = dirname(__DIR__, 2) . '/public/' . ltrim($oldPath, '/');
                if (is_file($oldAbsolutePath)) {
                    $filesystem->remove($oldAbsolutePath);
                }
            }
        }

        $manufacturer->setLogoPath($relativePath);

        return true;
    }

    private function assignTaxon(Product $product, string $taxonCode, int $rowNumber): ?Taxon
    {
        if ($taxonCode === '') {
            return null;
        }
        $mainTaxon = null;
        foreach ($this->splitPipe($taxonCode) as $position => $code) {
            /** @var Taxon|null $taxon */
            $taxon = $this->entityManager->getRepository(Taxon::class)->findOneBy(['code' => $code]);
            if ($taxon === null) {
                throw new \RuntimeException(sprintf('Row %d: taxon "%s" was not found.', $rowNumber, $code));
            }
            $mainTaxon ??= $taxon;
            if (!$product->hasTaxon($taxon)) {
                $productTaxon = new ProductTaxon();
                $productTaxon->setTaxon($taxon);
                $productTaxon->setPosition($position);
                $product->addProductTaxon($productTaxon);
                $this->entityManager->persist($productTaxon);
            }
        }
        $product->setMainTaxon($mainTaxon);

        return $mainTaxon;
    }

    private function assignChannels(Product $product, string $channelCodes, int $rowNumber): void
    {
        foreach ($this->splitPipe($channelCodes) as $channelCode) {
            /** @var Channel|null $channel */
            $channel = $this->entityManager->getRepository(Channel::class)->findOneBy(['code' => $channelCode]);
            if ($channel === null) {
                throw new \RuntimeException(sprintf('Row %d: channel "%s" was not found.', $rowNumber, $channelCode));
            }

            $product->addChannel($channel);
        }
    }

    private function assignPrices(ProductVariant $variant, string $pricesJson, int $rowNumber): void
    {
        $prices = $this->decodeJsonObject($pricesJson, 'prices_json', $rowNumber);

        foreach ($prices as $channelCode => $price) {
            if (!is_numeric($price)) {
                throw new \RuntimeException(sprintf(
                    'Row %d: price for channel "%s" must be an integer in minor units.',
                    $rowNumber,
                    (string) $channelCode,
                ));
            }

            /** @var Channel|null $channel */
            $channel = $this->entityManager->getRepository(Channel::class)->findOneBy(['code' => (string) $channelCode]);
            if ($channel === null) {
                throw new \RuntimeException(sprintf(
                    'Row %d: pricing channel "%s" was not found.',
                    $rowNumber,
                    (string) $channelCode,
                ));
            }

            /** @var ChannelPricing|null $pricing */
            $pricing = $variant->getChannelPricings()->get((string) $channelCode);

            if ($pricing === null) {
                $pricing = new ChannelPricing();
                $pricing->setChannelCode((string) $channelCode);
                $variant->addChannelPricing($pricing);
                $this->entityManager->persist($pricing);
            }

            $pricing->setPrice((int) $price);
        }
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
     *   products_created:int,
     *   products_updated:int,
     *   variants_created:int,
     *   variants_updated:int,
     *   warnings:list<string>
     * } $result
     */
    private function assignB2BPrices(
        ProductVariant $variant,
        string $pricesJson,
        int $rowNumber,
        array &$result,
    ): void {
        $rules = $this->decodeJsonList($pricesJson, 'b2b_prices_json', $rowNumber);

        foreach ($rules as $index => $specification) {
            if (!is_array($specification)) {
                throw new \RuntimeException(sprintf(
                    'Row %d: b2b_prices_json item %d must be a JSON object.',
                    $rowNumber,
                    $index + 1,
                ));
            }

            $channelCode = trim((string) ($specification['channel'] ?? ''));
            $customerGroupCode = trim((string) ($specification['group'] ?? ''));
            $minQuantity = (int) ($specification['min_quantity'] ?? 1);

            if ($channelCode === '') {
                throw new \RuntimeException(sprintf(
                    'Row %d: b2b_prices_json item %d requires "channel".',
                    $rowNumber,
                    $index + 1,
                ));
            }

            if (!array_key_exists('price', $specification)) {
                throw new \RuntimeException(sprintf(
                    'Row %d: b2b_prices_json item %d requires "price" in the smallest currency unit.',
                    $rowNumber,
                    $index + 1,
                ));
            }

            if ($minQuantity < 1) {
                throw new \RuntimeException(sprintf(
                    'Row %d: b2b_prices_json item %d has min_quantity below 1.',
                    $rowNumber,
                    $index + 1,
                ));
            }

            /** @var Channel|null $channel */
            $channel = $this->entityManager->getRepository(Channel::class)->findOneBy(['code' => $channelCode]);
            if ($channel === null) {
                throw new \RuntimeException(sprintf(
                    'Row %d: B2B price channel "%s" was not found.',
                    $rowNumber,
                    $channelCode,
                ));
            }

            if ($customerGroupCode !== '') {
                $group = $this->entityManager->getRepository(CustomerGroup::class)->findOneBy(['code' => $customerGroupCode]);
                if (!$group instanceof CustomerGroup) {
                    throw new \RuntimeException(sprintf(
                        'Row %d: B2B customer group "%s" was not found.',
                        $rowNumber,
                        $customerGroupCode,
                    ));
                }
            }

            $price = (int) $specification['price'];
            if ($price < 0) {
                throw new \RuntimeException(sprintf(
                    'Row %d: B2B price must not be negative.',
                    $rowNumber,
                ));
            }

            /** @var VariantPriceRule|null $rule */
            $rule = $this->entityManager->getRepository(VariantPriceRule::class)->findOneBy([
                'variant' => $variant,
                'channelCode' => $channelCode,
                'customerGroupCode' => $customerGroupCode,
                'minQuantity' => $minQuantity,
            ]);

            $created = $rule === null;
            $updated = false;

            if ($rule === null) {
                $rule = new VariantPriceRule();
                $rule->setVariant($variant);
                $rule->setChannelCode($channelCode);
                $rule->setCustomerGroupCode($customerGroupCode);
                $rule->setMinQuantity($minQuantity);
                $this->entityManager->persist($rule);
            }

            if ($rule->getPrice() !== $price) {
                $rule->setPrice($price);
                $updated = !$created;
            }

            $enabled = array_key_exists('enabled', $specification)
                ? $this->toBool($specification['enabled'])
                : true;

            if ($rule->isEnabled() !== $enabled) {
                $rule->setEnabled($enabled);
                $updated = !$created;
            }

            if ($created) {
                ++$result['price_rules_created'];
            } elseif ($updated) {
                ++$result['price_rules_updated'];
            }
        }
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
    private function assignCustomerPrices(
        ProductVariant $variant,
        string $pricesJson,
        int $rowNumber,
        array &$result,
    ): void {
        $rules = $this->decodeJsonList($pricesJson, 'customer_prices_json', $rowNumber);

        foreach ($rules as $index => $specification) {
            if (!is_array($specification)) {
                throw new \RuntimeException(sprintf(
                    'Row %d: customer_prices_json item %d must be a JSON object.',
                    $rowNumber,
                    $index + 1,
                ));
            }

            $channelCode = trim((string) ($specification['channel'] ?? ''));
            $customerEmail = mb_strtolower(trim((string) ($specification['customer_email'] ?? '')));
            $minQuantity = (int) ($specification['min_quantity'] ?? 1);

            if ($channelCode === '') {
                throw new \RuntimeException(sprintf(
                    'Row %d: customer_prices_json item %d requires "channel".',
                    $rowNumber,
                    $index + 1,
                ));
            }

            if ($customerEmail === '') {
                throw new \RuntimeException(sprintf(
                    'Row %d: customer_prices_json item %d requires "customer_email".',
                    $rowNumber,
                    $index + 1,
                ));
            }

            if (!array_key_exists('price', $specification)) {
                throw new \RuntimeException(sprintf(
                    'Row %d: customer_prices_json item %d requires "price" in the smallest currency unit.',
                    $rowNumber,
                    $index + 1,
                ));
            }

            if ($minQuantity < 1) {
                throw new \RuntimeException(sprintf(
                    'Row %d: customer_prices_json item %d has min_quantity below 1.',
                    $rowNumber,
                    $index + 1,
                ));
            }

            /** @var Channel|null $channel */
            $channel = $this->entityManager->getRepository(Channel::class)->findOneBy(['code' => $channelCode]);
            if ($channel === null) {
                throw new \RuntimeException(sprintf(
                    'Row %d: customer price channel "%s" was not found.',
                    $rowNumber,
                    $channelCode,
                ));
            }

            /** @var Customer|null $customer */
            $customer = $this->entityManager->getRepository(Customer::class)->findOneBy([
                'emailCanonical' => $customerEmail,
            ]);

            if (!$customer instanceof Customer) {
                $customer = $this->entityManager->getRepository(Customer::class)->findOneBy([
                    'email' => $customerEmail,
                ]);
            }

            if (!$customer instanceof Customer) {
                throw new \RuntimeException(sprintf(
                    'Row %d: customer "%s" was not found. Create the customer before importing individual prices.',
                    $rowNumber,
                    $customerEmail,
                ));
            }

            if ($customer->getB2bProfile() === null) {
                throw new \RuntimeException(sprintf(
                    'Row %d: customer "%s" requires a B2B profile before importing individual prices.',
                    $rowNumber,
                    $customerEmail,
                ));
            }

            $price = (int) $specification['price'];
            if ($price < 0) {
                throw new \RuntimeException(sprintf(
                    'Row %d: individual customer price must not be negative.',
                    $rowNumber,
                ));
            }

            /** @var CustomerVariantPriceRule|null $rule */
            $rule = $this->entityManager->getRepository(CustomerVariantPriceRule::class)->findOneBy([
                'variant' => $variant,
                'customer' => $customer,
                'channelCode' => $channelCode,
                'minQuantity' => $minQuantity,
            ]);

            $created = $rule === null;
            $updated = false;

            if ($rule === null) {
                $rule = new CustomerVariantPriceRule();
                $rule->setVariant($variant);
                $rule->setCustomer($customer);
                $rule->setChannelCode($channelCode);
                $rule->setMinQuantity($minQuantity);
                $this->entityManager->persist($rule);
            }

            if ($rule->getPrice() !== $price) {
                $rule->setPrice($price);
                $updated = !$created;
            }

            $enabled = array_key_exists('enabled', $specification)
                ? $this->toBool($specification['enabled'])
                : true;

            if ($rule->isEnabled() !== $enabled) {
                $rule->setEnabled($enabled);
                $updated = !$created;
            }

            if ($created) {
                ++$result['customer_price_rules_created'];
            } elseif ($updated) {
                ++$result['customer_price_rules_updated'];
            }
        }
    }

    /** @param list<string> $warnings */
    private function assignAttributes(Product $product, string $attributesJson, int $rowNumber, array &$warnings): void
    {
        if ($attributesJson === '') {
            return;
        }

        $attributes = $this->decodeJsonObject($attributesJson, 'attributes_json', $rowNumber);

        foreach ($attributes as $attributeCode => $rawValue) {
            /** @var ProductAttribute|null $attribute */
            $attribute = $this->entityManager->getRepository(ProductAttribute::class)->findOneBy(['code' => (string) $attributeCode]);
            if ($attribute === null) {
                $product->setDataQualityStatus('needs_review');
                $warnings[] = sprintf('Row %d: unknown product attribute "%s"; value skipped.', $rowNumber, (string) $attributeCode);

                continue;
            }

            if ($attribute->getType() === 'select') {
                $allowedValues = array_keys((array) ($attribute->getConfiguration()['choices'] ?? []));
                $submittedValues = is_array($rawValue) ? $rawValue : [$rawValue];
                $unknownValues = array_diff(array_map('strval', $submittedValues), $allowedValues);
                if ($unknownValues !== []) {
                    $product->setDataQualityStatus('needs_review');
                    $warnings[] = sprintf('Row %d: attribute "%s" contains unknown controlled value(s) "%s"; value skipped.', $rowNumber, (string) $attributeCode, implode(', ', $unknownValues));

                    continue;
                }
            }

            $existing = $product->getAttributeByCodeAndLocale((string) $attributeCode, null);
            if (!$existing instanceof ProductAttributeValue) {
                $existing = new ProductAttributeValue();
                $existing->setAttribute($attribute);
                $existing->setLocaleCode(null);
                $product->addAttribute($existing);
                $this->entityManager->persist($existing);
            }

            $value = match ($attribute->getStorageType()) {
                'boolean' => $this->toBool($rawValue),
                'integer' => (int) $rawValue,
                'float' => (float) $rawValue,
                'json' => is_array($rawValue) ? $rawValue : [(string) $rawValue],
                default => is_scalar($rawValue) ? (string) $rawValue : json_encode($rawValue, \JSON_THROW_ON_ERROR),
            };

            $existing->setValue($value);
        }
    }

    /** @param array<string, mixed> $result */
    private function assignDeviceCompatibilities(Product $product, string $json, int $rowNumber, array &$result): void
    {
        $specifications = $this->decodeJsonList($json, 'device_compatibilities_json', $rowNumber);
        /** @var DeviceModelRepository $repository */
        $repository = $this->entityManager->getRepository(DeviceModel::class);

        foreach ($specifications as $index => $specification) {
            if (!is_array($specification)) {
                throw new \RuntimeException(sprintf('Row %d: device compatibility item %d must be an object.', $rowNumber, $index + 1));
            }
            $identifier = trim((string) ($specification['device'] ?? $specification['code'] ?? $specification['name'] ?? ''));
            $type = trim((string) ($specification['type'] ?? ProductDeviceCompatibility::TYPE_COMPATIBLE_WITH));
            $device = $repository->findOneByIdentifier($identifier);
            if (!$device instanceof DeviceModel) {
                $product->setDataQualityStatus('needs_review');
                $result['warnings'][] = sprintf('Row %d: device "%s" could not be resolved by code, name or alias; no device was created.', $rowNumber, $identifier);

                continue;
            }
            if (!isset(ProductDeviceCompatibility::typeLabels()[$type])) {
                $product->setDataQualityStatus('needs_review');
                $result['warnings'][] = sprintf('Row %d: unknown device compatibility type "%s"; skipped.', $rowNumber, $type);

                continue;
            }
            /** @var ProductDeviceCompatibility|null $compatibility */
            $compatibility = $this->entityManager->getRepository(ProductDeviceCompatibility::class)->findOneBy(['product' => $product, 'deviceModel' => $device, 'compatibilityType' => $type]);
            $created = $compatibility === null;
            if ($compatibility === null) {
                $compatibility = new ProductDeviceCompatibility();
                $compatibility->setProduct($product);
                $compatibility->setDeviceModel($device);
                $compatibility->setCompatibilityType($type);
                $product->addDeviceCompatibility($compatibility);
                $this->entityManager->persist($compatibility);
            }
            $compatibility->setNote(isset($specification['note']) ? (string) $specification['note'] : null);
            $compatibility->setPosition((int) ($specification['position'] ?? 0));
            $compatibility->setEnabled(array_key_exists('enabled', $specification) ? $this->toBool($specification['enabled']) : true);
            // Imports are deliberately unverified unless a human verifies them later.
            if ($created) {
                ++$result['device_compatibilities_created'];
            } else {
                ++$result['device_compatibilities_updated'];
            }
        }
    }

    /**
     * @param array{
     *   rows:int,
     *   manufacturers_created:int,
     *   manufacturers_updated:int,
     *   documents_created:int,
     *   documents_updated:int,
     *   products_created:int,
     *   products_updated:int,
     *   variants_created:int,
     *   variants_updated:int,
     *   warnings:list<string>
     * } $result
     * @param array<string, true> $seenDocuments
     */
    private function assignDocuments(
        Product $product,
        string $documentsJson,
        ?string $documentDirectory,
        int $rowNumber,
        array &$result,
        array &$seenDocuments,
        bool $dryRun,
    ): void {
        $documents = $this->decodeJsonList($documentsJson, 'documents_json', $rowNumber);

        foreach ($documents as $index => $specification) {
            if (!is_array($specification)) {
                throw new \RuntimeException(sprintf(
                    'Row %d: documents_json item %d must be a JSON object.',
                    $rowNumber,
                    $index + 1,
                ));
            }

            $importKey = trim((string) ($specification['key'] ?? ''));
            $title = trim((string) ($specification['title'] ?? ''));
            $type = trim((string) ($specification['type'] ?? ProductDocument::TYPE_DATASHEET));
            $locale = trim((string) ($specification['locale'] ?? ''));
            $filename = trim((string) ($specification['file'] ?? ''));

            if ($importKey === '') {
                throw new \RuntimeException(sprintf(
                    'Row %d: documents_json item %d requires a stable "key".',
                    $rowNumber,
                    $index + 1,
                ));
            }

            if (!preg_match('/^[A-Za-z0-9._-]{1,100}$/', $importKey)) {
                throw new \RuntimeException(sprintf(
                    'Row %d: document key "%s" may only contain letters, numbers, dot, underscore and hyphen.',
                    $rowNumber,
                    $importKey,
                ));
            }

            if ($title === '') {
                throw new \RuntimeException(sprintf(
                    'Row %d: document "%s" requires a title.',
                    $rowNumber,
                    $importKey,
                ));
            }

            $allowedTypes = [
                ProductDocument::TYPE_DATASHEET,
                ProductDocument::TYPE_MANUAL,
                ProductDocument::TYPE_DRIVER,
                ProductDocument::TYPE_CERTIFICATE,
                ProductDocument::TYPE_BROCHURE,
                ProductDocument::TYPE_OTHER,
            ];

            if (!in_array($type, $allowedTypes, true)) {
                throw new \RuntimeException(sprintf(
                    'Row %d: document "%s" has unsupported type "%s".',
                    $rowNumber,
                    $importKey,
                    $type,
                ));
            }

            /** @var ProductDocument|null $document */
            $document = $this->entityManager->getRepository(ProductDocument::class)->findOneBy([
                'product' => $product,
                'importKey' => $importKey,
            ]);

            $created = $document === null;
            $updated = false;

            if ($document === null) {
                if ($filename === '') {
                    $result['warnings'][] = sprintf(
                        'Row %d: new document "%s" has no file and was skipped.',
                        $rowNumber,
                        $importKey,
                    );

                    continue;
                }

                $document = new ProductDocument();
                $document->setProduct($product);
                $document->setImportKey($importKey);
                $product->addDocument($document);
                $this->entityManager->persist($document);
            }

            if ($document->getTitle() !== $title) {
                $document->setTitle($title);
                $updated = !$created;
            }

            if ($document->getType() !== $type) {
                $document->setType($type);
                $updated = !$created;
            }

            $normalizedLocale = $locale !== '' ? $locale : null;
            if ($document->getLocale() !== $normalizedLocale) {
                $document->setLocale($normalizedLocale);
                $updated = !$created;
            }

            $position = isset($specification['position']) ? (int) $specification['position'] : 0;
            if ($document->getPosition() !== $position) {
                $document->setPosition($position);
                $updated = !$created;
            }

            $enabled = array_key_exists('enabled', $specification)
                ? $this->toBool($specification['enabled'])
                : true;
            if ($document->isEnabled() !== $enabled) {
                $document->setEnabled($enabled);
                $updated = !$created;
            }

            if ($filename !== '') {
                if ($documentDirectory === null) {
                    $result['warnings'][] = sprintf(
                        'Row %d: document "%s" specifies "%s", but no document directory is configured.',
                        $rowNumber,
                        $importKey,
                        basename($filename),
                    );

                    if ($created) {
                        $product->removeDocument($document);
                        $this->entityManager->remove($document);

                        continue;
                    }
                } else {
                    $fileChanged = $this->assignDocumentFile(
                        $product,
                        $document,
                        $filename,
                        $documentDirectory,
                        $rowNumber,
                        $result['warnings'],
                        $dryRun,
                    );

                    if ($created && $document->getFilePath() === null) {
                        $product->removeDocument($document);
                        $this->entityManager->remove($document);

                        continue;
                    }

                    $updated = $fileChanged || $updated;
                }
            }

            $counterKey = (string) $product->getCode() . ':' . $importKey;
            if (!isset($seenDocuments[$counterKey])) {
                if ($created) {
                    ++$result['documents_created'];
                } elseif ($updated) {
                    ++$result['documents_updated'];
                }

                $seenDocuments[$counterKey] = true;
            }
        }
    }

    /**
     * @param list<string> $warnings
     */
    private function assignDocumentFile(
        Product $product,
        ProductDocument $document,
        string $filename,
        string $documentDirectory,
        int $rowNumber,
        array &$warnings,
        bool $dryRun,
    ): bool {
        $safeFilename = basename($filename);
        $source = rtrim($documentDirectory, '/') . '/' . $safeFilename;

        if (!is_file($source) || !is_readable($source)) {
            $warnings[] = sprintf(
                'Row %d: document file "%s" was not found; skipped.',
                $rowNumber,
                $safeFilename,
            );

            return false;
        }

        if (strtolower(pathinfo($safeFilename, \PATHINFO_EXTENSION)) !== 'pdf') {
            $warnings[] = sprintf(
                'Row %d: document file "%s" is not a PDF; skipped.',
                $rowNumber,
                $safeFilename,
            );

            return false;
        }

        $fileSize = filesize($source);
        $mimeType = (new \finfo(\FILEINFO_MIME_TYPE))->file($source) ?: 'application/pdf';

        if ($mimeType !== 'application/pdf') {
            $warnings[] = sprintf(
                'Row %d: document file "%s" has MIME type "%s" instead of application/pdf; skipped.',
                $rowNumber,
                $safeFilename,
                $mimeType,
            );

            return false;
        }

        $productCode = strtolower((string) $this->slugger->slug((string) $product->getCode()));
        $key = strtolower((string) $this->slugger->slug((string) $document->getImportKey()));
        $directory = 'media/cardnext/product-documents/' . $productCode;
        $targetFilename = $key . '.pdf';
        $relativePath = $directory . '/' . $targetFilename;

        $changed = $document->getFilePath() !== $relativePath ||
            $document->getOriginalFilename() !== $safeFilename ||
            $document->getFileSize() !== ($fileSize !== false ? $fileSize : null);

        if (!$dryRun) {
            $filesystem = new Filesystem();
            $publicDirectory = dirname(__DIR__, 2) . '/public/' . $directory;
            $filesystem->mkdir($publicDirectory, 0775);
            $filesystem->copy($source, $publicDirectory . '/' . $targetFilename, true);

            $oldPath = $document->getFilePath();
            if (
                $oldPath !== null &&
                $oldPath !== $relativePath &&
                str_starts_with($oldPath, 'media/cardnext/product-documents/')
            ) {
                $oldAbsolutePath = dirname(__DIR__, 2) . '/public/' . ltrim($oldPath, '/');
                if (is_file($oldAbsolutePath)) {
                    $filesystem->remove($oldAbsolutePath);
                }
            }
        }

        $document->setFilePath($relativePath);
        $document->setOriginalFilename($safeFilename);
        $document->setMimeType($mimeType);
        $document->setFileSize($fileSize !== false ? $fileSize : null);

        return $changed;
    }

    /**
     * @return list<mixed>
     */
    private function decodeJsonList(string $json, string $column, int $rowNumber): array
    {
        if ($json === '') {
            return [];
        }

        try {
            $value = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException(sprintf(
                'Row %d: invalid JSON in "%s": %s',
                $rowNumber,
                $column,
                $exception->getMessage(),
            ));
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw new \RuntimeException(sprintf(
                'Row %d: "%s" must contain a JSON array.',
                $rowNumber,
                $column,
            ));
        }

        return $value;
    }

    /**
     * @param array<string, Product> $productsByCode
     * @param array<string, ProductCompatibility> $compatibilityEntities
     * @param array{
     *   rows:int,
     *   manufacturers_created:int,
     *   manufacturers_updated:int,
     *   documents_created:int,
     *   documents_updated:int,
     *   compatibilities_created:int,
     *   compatibilities_updated:int,
     *   products_created:int,
     *   products_updated:int,
     *   variants_created:int,
     *   variants_updated:int,
     *   warnings:list<string>
     * } $result
     */
    private function assignCompatibilities(
        Product $sourceProduct,
        string $compatibilitiesJson,
        int $rowNumber,
        array $productsByCode,
        array &$compatibilityEntities,
        array &$result,
    ): void {
        $specifications = $this->decodeJsonList($compatibilitiesJson, 'compatibilities_json', $rowNumber);

        foreach ($specifications as $index => $specification) {
            if (!is_array($specification)) {
                throw new \RuntimeException(sprintf(
                    'Row %d: compatibilities_json item %d must be a JSON object.',
                    $rowNumber,
                    $index + 1,
                ));
            }

            $targetCode = trim((string) ($specification['target_code'] ?? ''));
            $relationType = trim((string) ($specification['type'] ?? ProductCompatibility::TYPE_COMPATIBLE_WITH));

            if ($targetCode === '') {
                throw new \RuntimeException(sprintf(
                    'Row %d: compatibilities_json item %d requires target_code.',
                    $rowNumber,
                    $index + 1,
                ));
            }

            if (!array_key_exists($relationType, ProductCompatibility::typeLabels())) {
                throw new \RuntimeException(sprintf(
                    'Row %d: compatibility "%s" uses unsupported type "%s".',
                    $rowNumber,
                    $targetCode,
                    $relationType,
                ));
            }

            if ($targetCode === $sourceProduct->getCode()) {
                throw new \RuntimeException(sprintf(
                    'Row %d: product "%s" cannot be compatible with itself.',
                    $rowNumber,
                    $targetCode,
                ));
            }

            $targetProduct = $productsByCode[$targetCode] ?? null;
            if (!$targetProduct instanceof Product) {
                /** @var Product|null $targetProduct */
                $targetProduct = $this->entityManager->getRepository(Product::class)->findOneBy(['code' => $targetCode]);
            }

            if (!$targetProduct instanceof Product) {
                throw new \RuntimeException(sprintf(
                    'Row %d: compatibility target product "%s" was not found.',
                    $rowNumber,
                    $targetCode,
                ));
            }

            $key = sprintf('%s|%s|%s', $sourceProduct->getCode(), $targetCode, $relationType);

            $compatibility = $compatibilityEntities[$key] ?? null;
            if (!$compatibility instanceof ProductCompatibility) {
                /** @var ProductCompatibility|null $compatibility */
                $compatibility = $this->entityManager->getRepository(ProductCompatibility::class)->findOneBy([
                    'sourceProduct' => $sourceProduct,
                    'targetProduct' => $targetProduct,
                    'relationType' => $relationType,
                ]);
            }

            $created = $compatibility === null;
            $updated = false;

            if ($compatibility === null) {
                $compatibility = new ProductCompatibility();
                $compatibility->setSourceProduct($sourceProduct);
                $compatibility->setTargetProduct($targetProduct);
                $compatibility->setRelationType($relationType);

                $sourceProduct->addCompatibility($compatibility);
                $targetProduct->addReverseCompatibility($compatibility);

                $this->entityManager->persist($compatibility);
            }

            $note = array_key_exists('note', $specification)
                ? trim((string) $specification['note'])
                : null;
            if ($note !== null && $compatibility->getNote() !== ($note !== '' ? $note : null)) {
                $compatibility->setNote($note);
                $updated = !$created;
            }

            if (array_key_exists('position', $specification)) {
                $position = (int) $specification['position'];
                if ($compatibility->getPosition() !== $position) {
                    $compatibility->setPosition($position);
                    $updated = !$created;
                }
            }

            if (array_key_exists('enabled', $specification)) {
                $enabled = $this->toBool($specification['enabled']);
                if ($compatibility->isEnabled() !== $enabled) {
                    $compatibility->setEnabled($enabled);
                    $updated = !$created;
                }
            }

            if ($created) {
                ++$result['compatibilities_created'];
            } elseif ($updated) {
                ++$result['compatibilities_updated'];
            }

            $compatibilityEntities[$key] = $compatibility;
        }
    }

    /**
     * @param list<string> $warnings
     */
    private function assignImages(
        Product $product,
        string $images,
        string $imageDirectory,
        int $rowNumber,
        array &$warnings,
        bool $dryRun,
    ): void {
        $filesystem = new Filesystem();
        $productDirectory = sprintf(
            'media/cardnext/products/%s',
            strtolower((string) $this->slugger->slug((string) $product->getCode())),
        );

        foreach ($this->splitPipe($images) as $position => $filename) {
            $safeFilename = basename($filename);
            $source = rtrim($imageDirectory, '/') . '/' . $safeFilename;

            if (!is_file($source) || !is_readable($source)) {
                $warnings[] = sprintf('Row %d: image "%s" was not found; skipped.', $rowNumber, $safeFilename);

                continue;
            }

            $extension = strtolower(pathinfo($safeFilename, \PATHINFO_EXTENSION));
            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $warnings[] = sprintf('Row %d: image "%s" has an unsupported extension; skipped.', $rowNumber, $safeFilename);

                continue;
            }

            $targetFilename = sprintf('%02d-%s', $position + 1, $safeFilename);
            $relativePath = $productDirectory . '/' . $targetFilename;

            $alreadyExists = false;
            foreach ($product->getImages() as $existingImage) {
                if ($existingImage->getPath() === $relativePath) {
                    $alreadyExists = true;

                    break;
                }
            }

            if ($alreadyExists) {
                continue;
            }

            if (!$dryRun) {
                $publicDirectory = dirname(__DIR__, 2) . '/public/' . $productDirectory;
                $filesystem->mkdir($publicDirectory, 0775);
                $filesystem->copy($source, $publicDirectory . '/' . $targetFilename, true);
            }

            $image = new ProductImage();
            $image->setPath($relativePath);
            $image->setPosition($position);
            $image->setType('main');
            $product->addImage($image);
            $this->entityManager->persist($image);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(string $json, string $column, int $rowNumber): array
    {
        if ($json === '') {
            return [];
        }

        try {
            $value = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException(sprintf(
                'Row %d: invalid JSON in "%s": %s',
                $rowNumber,
                $column,
                $exception->getMessage(),
            ));
        }

        if (!is_array($value)) {
            throw new \RuntimeException(sprintf('Row %d: "%s" must contain a JSON object.', $rowNumber, $column));
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function splitPipe(string $value): array
    {
        return array_values(array_filter(
            array_map('trim', explode('|', $value)),
            static fn (string $item): bool => $item !== '',
        ));
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'ja', 'y'], true);
    }

    private function toInt(mixed $value): int
    {
        return (int) trim((string) $value);
    }
}
