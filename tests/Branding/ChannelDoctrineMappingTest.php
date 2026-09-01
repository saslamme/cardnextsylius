<?php

declare(strict_types=1);

namespace App\Tests\Branding;

use App\Entity\Channel\Channel;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use PHPUnit\Framework\TestCase;

final class ChannelDoctrineMappingTest extends TestCase
{
    public function testBrandingPropertiesUseMigrationColumnNames(): void
    {
        $metadata = new ClassMetadata(Channel::class);
        (new AttributeDriver([\dirname(__DIR__, 2) . '/src/Entity/Channel']))->loadMetadataForClass(Channel::class, $metadata);

        $expectedColumns = [
            'themeKey' => 'theme_key',
            'brandName' => 'brand_name',
            'logoPath' => 'logo_path',
            'logoDarkPath' => 'logo_dark_path',
            'faviconPath' => 'favicon_path',
            'primaryColor' => 'primary_color',
            'primaryHoverColor' => 'primary_hover_color',
            'primarySoftColor' => 'primary_soft_color',
            'inkColor' => 'ink_color',
            'textColor' => 'text_color',
            'footerColor' => 'footer_color',
        ];

        foreach ($expectedColumns as $property => $column) {
            self::assertSame($column, $metadata->getColumnName($property));
        }
    }
}
