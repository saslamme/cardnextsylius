<?php

declare(strict_types=1);

namespace App\Security\Hasher;

use Symfony\Component\PasswordHasher\PasswordHasherInterface;

/** Migration-only verifier. It must never be used to create a password. */
final class LegacyInteraktivShopPasswordHasher implements PasswordHasherInterface
{
    public function hash(string $plainPassword): string
    {
        throw new \LogicException('Legacy interaktiv.shop hashes must never be generated.');
    }

    public function verify(string $hashedPassword, string $plainPassword): bool
    {
        if (!$this->isLegacyHash($hashedPassword)) {
            return false;
        }

        $candidate = $plainPassword;
        for ($iteration = 0; $iteration < 4; ++$iteration) {
            $candidate = md5(' ' . $candidate . ' ');
        }

        return hash_equals(strtolower($hashedPassword), $candidate);
    }

    public function needsRehash(string $hashedPassword): bool
    {
        return $this->isLegacyHash($hashedPassword);
    }

    public function isLegacyHash(string $hash): bool
    {
        return preg_match('/^[a-fA-F0-9]{32}$/D', $hash) === 1;
    }
}
