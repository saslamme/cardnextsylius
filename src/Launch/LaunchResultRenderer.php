<?php

declare(strict_types=1);

namespace App\Launch;

use Symfony\Component\Console\Style\SymfonyStyle;

final class LaunchResultRenderer
{
    public function render(SymfonyStyle $io, LaunchCheckResult $result, bool $json): void
    {
        if ($json) {
            $io->writeln((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return;
        }

        if ($result->issues() === []) {
            $io->success('No issues found.');

            return;
        }

        $rows = [];
        foreach ($result->issues() as $issue) {
            $context = implode(', ', array_map(
                static fn (string $key, mixed $value): string => sprintf('%s=%s', $key, (string) ($value ?? 'null')),
                array_keys($issue->context),
                array_values($issue->context),
            ));
            $rows[] = [strtoupper($issue->severity), $issue->category, $issue->code, $issue->message, $context];
        }
        $io->table(['Severity', 'Category', 'Code', 'Message', 'Context'], $rows);
        $result->hasCriticalIssues() ? $io->error('Critical launch issues found.') : $io->success('Checks completed without critical issues.');
    }
}
