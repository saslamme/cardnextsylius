<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\Hasher\LegacyInteraktivShopPasswordHasher;
use PHPUnit\Framework\TestCase;

final class LegacyInteraktivShopPasswordHasherTest extends TestCase
{
    private LegacyInteraktivShopPasswordHasher $hasher;

    protected function setUp(): void
    {
        $this->hasher = new LegacyInteraktivShopPasswordHasher();
    }

    public function testVerifiedRegressionVectorAndUpgradeRequirement(): void
    {
        $hash = '4ba9b4b88f8253e46bf219306d1f5601';
        self::assertTrue($this->hasher->verify($hash, 'cawima86'));
        self::assertTrue($this->hasher->needsRehash($hash));
    }

    public function testWrongPasswordMalformedAndModernHashesFail(): void
    {
        self::assertFalse($this->hasher->verify('4ba9b4b88f8253e46bf219306d1f5601', 'wrong'));
        self::assertFalse($this->hasher->verify('not-a-hash', 'cawima86'));
        self::assertFalse($this->hasher->verify('$argon2id$v=19$m=65536,t=4,p=1$modern', 'cawima86'));
    }

    public function testUppercaseStoredHashIsAcceptedConstantTime(): void
    {
        self::assertTrue($this->hasher->verify('4BA9B4B88F8253E46BF219306D1F5601', 'cawima86'));
    }

    public function testItCannotGenerateWeakHashes(): void
    {
        $this->expectException(\LogicException::class);
        $this->hasher->hash('new-password');
    }
}
