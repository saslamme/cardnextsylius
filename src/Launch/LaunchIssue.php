<?php

declare(strict_types=1);

namespace App\Launch;

final readonly class LaunchIssue implements \JsonSerializable
{
    /** @param array<string, scalar|null> $context */
    public function __construct(
        public string $severity,
        public string $category,
        public string $code,
        public string $message,
        public array $context = [],
    ) {
        if (!in_array($severity, ['critical', 'warning', 'info'], true)) {
            throw new \InvalidArgumentException(sprintf('Unknown launch issue severity "%s".', $severity));
        }
    }

    /** @return array{severity: string, category: string, code: string, message: string, context: array<string, scalar|null>} */
    public function jsonSerialize(): array
    {
        return [
            'severity' => $this->severity,
            'category' => $this->category,
            'code' => $this->code,
            'message' => $this->message,
            'context' => $this->context,
        ];
    }
}
