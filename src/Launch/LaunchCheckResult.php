<?php

declare(strict_types=1);

namespace App\Launch;

final class LaunchCheckResult implements \JsonSerializable
{
    /** @var list<LaunchIssue> */
    private array $issues = [];

    public function add(LaunchIssue $issue): void
    {
        $this->issues[] = $issue;
    }

    /** @param array<string, scalar|null> $context */
    public function issue(string $severity, string $category, string $code, string $message, array $context = []): void
    {
        $this->add(new LaunchIssue($severity, $category, $code, $message, $context));
    }

    /** @return list<LaunchIssue> */
    public function issues(): array
    {
        return $this->issues;
    }

    public function hasCriticalIssues(): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue->severity === 'critical') {
                return true;
            }
        }

        return false;
    }

    /** @return array{ok: bool, counts: array<string, int>, issues: list<LaunchIssue>} */
    public function jsonSerialize(): array
    {
        $counts = ['critical' => 0, 'warning' => 0, 'info' => 0];
        foreach ($this->issues as $issue) {
            ++$counts[$issue->severity];
        }

        return ['ok' => !$this->hasCriticalIssues(), 'counts' => $counts, 'issues' => $this->issues];
    }
}
