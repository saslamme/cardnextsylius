<?php

declare(strict_types=1);

namespace App\Integration\Erp\Maintenance;

final readonly class IdbMasterMaintenanceContractNormalizer
{
    /** @param array<string, string> $fieldMap */
    public function __construct(private array $fieldMap = [])
    {
    }

    /** @param array<mixed> $row */
    public function normalize(array $row): ErpMaintenanceContractData
    {
        $customer = $this->requiredString($this->value($row, 'erpCustomerNumber', ['customer_number', 'customerNumber', 'erp_customer_number', 'customer_id', 'kundennummer', 'kdnr']), 'customer number');
        $serialNumbers = $this->serialNumbers($this->value($row, 'serialNumbers', ['serial_number', 'serialNumbers', 'serial_numbers', 'serialnumber', 'seriennummer']));
        $startsAt = $this->requiredDate($this->value($row, 'startsAt', ['start_date', 'startsAt', 'contract_start', 'contractStart', 'startdatum']), 'start date');
        $endsAt = $this->requiredDate($this->value($row, 'endsAt', ['end_date', 'endsAt', 'contract_end', 'contractEnd', 'enddatum']), 'end date');

        if ($endsAt < $startsAt) {
            throw new \UnexpectedValueException('Contract end date precedes start date.');
        }

        $reference = $this->optionalString($this->value($row, 'contractReference', ['contract_number', 'contractNumber', 'contract_reference', 'referenceNumber', 'vertragsnummer']));
        $externalId = $this->optionalString($this->value($row, 'externalId', ['contract_id', 'contractId', 'service_contract_id', 'id']));
        // Some exports expose the stable contract number but no separate database ID.
        $externalId ??= $reference;
        // Last-resort stable identity; never include optional/descriptive fields.
        $externalId ??= 'generated-' . hash('sha256', implode("\x1f", [$customer, $startsAt->format('Y-m-d'), $endsAt->format('Y-m-d'), ...$serialNumbers]));

        return new ErpMaintenanceContractData(
            $externalId,
            $customer,
            $serialNumbers,
            $startsAt,
            $endsAt,
            $this->optionalString($this->value($row, 'printerModel', ['printer_model', 'printerModel', 'model', 'druckermodell'])),
            $reference,
            $this->optionalDate($this->value($row, 'sourceUpdatedAt', ['updated_at', 'sourceUpdatedAt', 'modified_at', 'last_modified', 'aenderungsdatum'])),
        );
    }

    /**
     * @param array<mixed> $row
     * @param list<string> $defaults
     */
    private function value(array $row, string $canonicalName, array $defaults): mixed
    {
        $configured = $this->fieldMap[$canonicalName] ?? null;
        if (is_string($configured) && $configured !== '') {
            return $row[$configured] ?? null;
        }

        foreach ($defaults as $field) {
            if (array_key_exists($field, $row)) {
                return $row[$field];
            }
        }

        return null;
    }

    private function requiredString(mixed $value, string $name): string
    {
        $value = $this->optionalString($value);
        if ($value === null) {
            throw new \UnexpectedValueException(sprintf('Required %s is missing.', $name));
        }

        return $value;
    }

    private function optionalString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function requiredDate(mixed $value, string $name): \DateTimeImmutable
    {
        $date = $this->parseDate($value);
        if ($date === null) {
            throw new \UnexpectedValueException(sprintf('Required %s is missing or invalid.', $name));
        }

        return $date;
    }

    private function optionalDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = $this->parseDate($value);
        if ($date === null) {
            throw new \UnexpectedValueException('Optional source update date is invalid.');
        }

        return $date;
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        foreach (['!Y-m-d', '!d.m.Y', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date !== false && ($errors === false || (0 === $errors['warning_count'] && 0 === $errors['error_count']))) {
                return $date;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function serialNumbers(mixed $value): array
    {
        if (is_string($value) || is_int($value) || is_float($value)) {
            $value = preg_split('/\s*[,;]\s*/', (string) $value) ?: [];
        }
        if (!is_array($value)) {
            throw new \UnexpectedValueException('Serial numbers are missing or have an invalid type.');
        }

        $serialNumbers = [];
        foreach ($value as $serialNumber) {
            if (!is_scalar($serialNumber)) {
                throw new \UnexpectedValueException('A serial number has an invalid type.');
            }
            $serialNumber = trim((string) $serialNumber);
            if ($serialNumber !== '') {
                $serialNumbers[$serialNumber] = $serialNumber;
            }
        }
        if ($serialNumbers === []) {
            throw new \UnexpectedValueException('Contract requires at least one serial number.');
        }

        return array_values($serialNumbers);
    }
}
