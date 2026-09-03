<?php

declare(strict_types=1);

namespace App\Tests\Cms;

use App\Entity\Cms\CmsBlock;
use App\Entity\Cms\CmsMenuItem;
use App\Entity\Cms\CmsPage;
use App\Entity\Cms\CmsPageTranslation;
use App\Entity\Cms\CmsRedirect;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use PHPUnit\Framework\TestCase;

final class CmsDoctrineMappingTest extends TestCase
{
    private AttributeDriver $driver;

    protected function setUp(): void
    {
        $this->driver = new AttributeDriver([\dirname(__DIR__, 2) . '/src/Entity/Cms']);
    }

    public function testPageChannelJoinTableMatchesTheDeployedSchema(): void
    {
        $association = $this->metadata(CmsPage::class)->getAssociationMapping('channels');

        self::assertSame('cardnext_cms_page_channel', $association->joinTable->name);
        self::assertSame('cms_page_id', $association->joinTable->joinColumns[0]->name);
        self::assertSame('channel_id', $association->joinTable->inverseJoinColumns[0]->name);
    }

    public function testAllCmsAssociationColumnsMatchTheDeployedSchema(): void
    {
        $expected = [
            CmsPage::class => ['layout' => 'layout_id'],
            CmsPageTranslation::class => ['page' => 'page_id'],
            CmsBlock::class => ['page' => 'page_id'],
            CmsMenuItem::class => [
                'menu' => 'menu_id',
                'channel' => 'channel_id',
                'parent' => 'parent_id',
                'page' => 'page_id',
            ],
            CmsRedirect::class => [
                'channel' => 'channel_id',
                'targetPage' => 'target_page_id',
            ],
        ];

        foreach ($expected as $class => $associations) {
            $metadata = $this->metadata($class);
            foreach ($associations as $property => $column) {
                self::assertSame($column, $metadata->getSingleAssociationJoinColumnName($property), $class . '::$' . $property);
            }
        }
    }

    /** @param class-string $class */
    private function metadata(string $class): ClassMetadata
    {
        $metadata = new ClassMetadata($class);
        $this->driver->loadMetadataForClass($class, $metadata);

        return $metadata;
    }
}
