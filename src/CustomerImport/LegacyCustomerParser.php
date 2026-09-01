<?php

declare(strict_types=1);

namespace App\CustomerImport;

final class LegacyCustomerParser
{
    /** @return \Generator<int, LegacyCustomerRow> */
    public function parse(string $path, string $encoding): \Generator
    {
        if (!in_array(strtoupper($encoding), ['ISO-8859-1', 'UTF-8', 'WINDOWS-1252'], true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported encoding "%s".', $encoding));
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Import file "%s" is not readable.', $path));
        }

        try {
            $lineNumber = 0;
            while (($line = fgets($handle)) !== false) {
                ++$lineNumber;
                if (trim($line) === '') {
                    continue;
                }

                $converted = mb_convert_encoding($line, 'UTF-8', $encoding);
                if ($converted === false) {
                    throw new \UnexpectedValueException(sprintf('Row %d: encoding conversion failed.', $lineNumber));
                }
                if (!mb_check_encoding($converted, 'UTF-8')) {
                    throw new \UnexpectedValueException(sprintf('Row %d: encoding conversion failed.', $lineNumber));
                }

                /** @var list<string> $columns */
                $columns = str_getcsv(rtrim($converted, "\r\n"), '|');
                yield new LegacyCustomerRow($lineNumber, $columns);
            }
        } finally {
            fclose($handle);
        }
    }
}
