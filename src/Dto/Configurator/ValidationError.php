<?php

declare(strict_types=1);

namespace App\Dto\Configurator;

final readonly class ValidationError implements \JsonSerializable
{ /** @param array<string,mixed> $metadata */ public function __construct(public ?string $fieldCode, public string $errorCode, public string $message, public array $metadata = [])
{
}

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
