<?php
declare(strict_types=1);
namespace App\Service\Quote;
final class QuoteTaxRateResolver
{
    private const RATES = ['CARDNEXT_DE' => 1900, 'CARDNEXT_AT' => 2000];
    public function resolve(string $channelCode): int { return self::RATES[$channelCode] ?? 0; }
}
