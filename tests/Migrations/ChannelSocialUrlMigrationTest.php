<?php

declare(strict_types=1);

namespace App\Tests\Migrations;

use PHPUnit\Framework\TestCase;

final class ChannelSocialUrlMigrationTest extends TestCase
{
    public function testMigrationOnlyAddsAndRemovesNullableChannelUrlColumns(): void
    {
        $migration = file_get_contents(\dirname(__DIR__, 2) . '/migrations/Version20260910120000.php');
        self::assertIsString($migration);
        self::assertSame(2, substr_count($migration, 'ALTER TABLE sylius_channel'));

        foreach (['instagram_url', 'facebook_url', 'linkedin_url', 'youtube_url'] as $column) {
            self::assertStringContainsString("ADD {$column} VARCHAR(512) DEFAULT NULL", $migration);
            self::assertStringContainsString("DROP {$column}", $migration);
        }

        self::assertStringNotContainsString('CREATE TABLE', $migration);
    }
}
