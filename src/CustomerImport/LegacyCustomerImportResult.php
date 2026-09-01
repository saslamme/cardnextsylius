<?php

declare(strict_types=1);

namespace App\CustomerImport;

final class LegacyCustomerImportResult
{
    public int $rows = 0;

    public int $valid = 0;

    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public int $conflicts = 0;

    public int $invalidEmail = 0;

    public int $invalidHash = 0;

    public int $unknownCountry = 0;

    public int $encodingErrors = 0;

    public int $otherErrors = 0;

    /** @var list<array{row:int, customerNumber:string, email:string, status:string, reason:string}> */
    public array $issues = [];

    /** @var list<array{customerNumber:string, company:string, contact:string, email:string, postcode:string, city:string, status:string}> */
    public array $preview = [];

    public function issue(LegacyCustomerRow $row, string $status, string $reason): void
    {
        if (count($this->issues) < 200) {
            $this->issues[] = ['row' => $row->number, 'customerNumber' => $row->get(LegacyCustomerColumns::CUSTOMER_NUMBER), 'email' => $row->get(LegacyCustomerColumns::LOGIN_EMAIL), 'status' => $status, 'reason' => $reason];
        }
    }
}
