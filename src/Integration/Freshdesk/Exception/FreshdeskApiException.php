<?php
declare(strict_types=1);
namespace App\Integration\Freshdesk\Exception;

class FreshdeskApiException extends \RuntimeException
{
    public function __construct(public readonly string $operation, public readonly ?int $httpStatus = null, public readonly ?string $freshdeskCode = null, public readonly ?int $retryAfter = null, ?\Throwable $previous = null)
    { parent::__construct(sprintf('Freshdesk operation "%s" failed%s.', $operation, $httpStatus ? ' with HTTP '.$httpStatus : ''), 0, $previous); }
}
