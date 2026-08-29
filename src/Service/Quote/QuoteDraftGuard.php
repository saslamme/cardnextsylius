<?php
declare(strict_types=1);
namespace App\Service\Quote;
use App\Entity\Quote\Quote;
use App\Enum\Quote\QuoteStatus;
final class QuoteDraftGuard
{
    public function assertDraft(Quote $quote): void
    {
        if ($quote->getStatus() !== QuoteStatus::Draft) throw new \DomainException('Fertige Angebotsversionen sind unveränderlich.');
    }
}
