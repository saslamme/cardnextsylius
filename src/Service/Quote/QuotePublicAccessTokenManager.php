<?php
declare(strict_types=1);
namespace App\Service\Quote;
use App\Entity\Quote\Quote;
final class QuotePublicAccessTokenManager
{
    public function issue(Quote $quote, ?\DateTimeImmutable $now=null): string
    {
        $token=bin2hex(random_bytes(32));
        $quote->setPublicAccess(hash('sha256',$token),$now??new \DateTimeImmutable());
        return $token;
    }
    public function isValid(Quote $quote, string $token): bool
    {
        $hash=$quote->getAccessTokenHash();
        return $hash!==null && strlen($token)===64 && hash_equals($hash,hash('sha256',$token));
    }
}
