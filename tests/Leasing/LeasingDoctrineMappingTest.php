<?php

declare(strict_types=1);

namespace App\Tests\Leasing;

use App\Entity\Leasing\LeasingConfiguration;
use App\Entity\Leasing\LeasingFactor;
use App\Entity\Leasing\LeasingInquiry;
use App\Entity\Product\Product;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use PHPUnit\Framework\TestCase;

final class LeasingDoctrineMappingTest extends TestCase
{
    public function testFieldsUseTheDeployedSnakeCaseColumnNames(): void
    {
        $expectedColumns = [
            LeasingConfiguration::class => [
                'providerName' => 'provider_name',
                'minimumNetAmount' => 'minimum_net_amount',
                'frontendNotice' => 'frontend_notice',
                'ctaText' => 'cta_text',
                'notificationEmail' => 'notification_email',
                'updatedAt' => 'updated_at',
            ],
            LeasingFactor::class => [
                'durationMonths' => 'duration_months',
                'activeKey' => 'active_key',
                'createdAt' => 'created_at',
                'updatedAt' => 'updated_at',
            ],
            LeasingInquiry::class => [
                'inquiryNumber' => 'inquiry_number',
                'productNameSnapshot' => 'product_name_snapshot',
                'productCodeSnapshot' => 'product_code_snapshot',
                'currencyCode' => 'currency_code',
                'netPrice' => 'net_price',
                'leasingFactorSnapshot' => 'leasing_factor_snapshot',
                'durationMonths' => 'duration_months',
                'calculatedMonthlyRate' => 'calculated_monthly_rate',
                'firstName' => 'first_name',
                'lastName' => 'last_name',
                'houseNumber' => 'house_number',
                'postalCode' => 'postal_code',
                'countryCode' => 'country_code',
                'internalNote' => 'internal_note',
                'createdAt' => 'created_at',
                'updatedAt' => 'updated_at',
            ],
            Product::class => [
                'leasingEnabled' => 'leasing_enabled',
                'leasingCustomPrice' => 'leasing_custom_price',
            ],
        ];

        foreach ($expectedColumns as $class => $fields) {
            $metadata = $this->loadMetadata($class);

            foreach ($fields as $field => $column) {
                self::assertSame($column, $metadata->getColumnName($field), $class . '::$' . $field);
            }
        }
    }

    public function testAssociationsUseTheDeployedJoinColumns(): void
    {
        $configuration = $this->loadMetadata(LeasingConfiguration::class);
        self::assertSame('default_factor_id', $configuration->getSingleAssociationJoinColumnName('defaultFactor'));

        $inquiry = $this->loadMetadata(LeasingInquiry::class);
        self::assertSame('product_id', $inquiry->getSingleAssociationJoinColumnName('product'));
        self::assertSame('product_variant_id', $inquiry->getSingleAssociationJoinColumnName('productVariant'));
        self::assertSame('channel_id', $inquiry->getSingleAssociationJoinColumnName('channel'));

        $product = $this->loadMetadata(Product::class);
        $association = $product->getAssociationMapping('leasingFactors');
        self::assertSame('cardnext_product_leasing_factor', $association->joinTable->name);
        self::assertSame('product_id', $association->joinTable->joinColumns[0]->name);
        self::assertSame('leasing_factor_id', $association->joinTable->inverseJoinColumns[0]->name);
    }

    /** @param class-string $class */
    private function loadMetadata(string $class): ClassMetadata
    {
        $metadata = new ClassMetadata($class);
        (new AttributeDriver([\dirname(__DIR__, 2) . '/src/Entity']))->loadMetadataForClass($class, $metadata);

        return $metadata;
    }
}
