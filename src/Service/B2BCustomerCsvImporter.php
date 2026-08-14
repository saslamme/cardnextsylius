<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Customer\Customer;
use App\Entity\Customer\CustomerB2BProfile;
use App\Entity\Customer\CustomerGroup;
use Doctrine\ORM\EntityManagerInterface;

final readonly class B2BCustomerCsvImporter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{
     *   rows:int,
     *   created:int,
     *   updated:int,
     *   unchanged:int,
     *   warnings:list<string>
     * }
     */
    public function import(string $csvPath, bool $dryRun = false): array
    {
        $handle = fopen($csvPath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('CSV "%s" could not be opened.', $csvPath));
        }

        $result = [
            'rows' => 0,
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'warnings' => [],
        ];

        try {
            // Consume UTF-8 BOM safely before fgetcsv().
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            $header = fgetcsv($handle, 0, ';');
            if ($header === false) {
                throw new \RuntimeException('CSV has no header row.');
            }

            $header = array_map(
                static fn (string $value): string => trim($value),
                $header,
            );

            if (!in_array('email', $header, true)) {
                throw new \RuntimeException('Required CSV column "email" is missing.');
            }

            if ($dryRun) {
                $this->entityManager->beginTransaction();
            }

            $rowNumber = 1;

            while (($values = fgetcsv($handle, 0, ';')) !== false) {
                ++$rowNumber;

                if ($values === [null] || $values === []) {
                    continue;
                }

                $values = array_pad($values, count($header), '');
                $row = array_combine($header, array_slice($values, 0, count($header)));
                if (!is_array($row)) {
                    throw new \RuntimeException(sprintf('Row %d could not be parsed.', $rowNumber));
                }

                $email = mb_strtolower(trim((string) ($row['email'] ?? '')));
                if ($email === '') {
                    throw new \RuntimeException(sprintf('Row %d: email is required.', $rowNumber));
                }

                /** @var Customer|null $customer */
                $customer = $this->entityManager->getRepository(Customer::class)->findOneBy([
                    'emailCanonical' => $email,
                ]);

                if (!$customer instanceof Customer) {
                    $customer = $this->entityManager->getRepository(Customer::class)->findOneBy([
                        'email' => $email,
                    ]);
                }

                if (!$customer instanceof Customer) {
                    throw new \RuntimeException(sprintf(
                        'Row %d: Sylius customer "%s" was not found. Create the customer first.',
                        $rowNumber,
                        $email,
                    ));
                }

                $profile = $customer->getB2bProfile();
                $created = $profile === null;
                $changed = false;

                if (!$profile instanceof CustomerB2BProfile) {
                    $profile = new CustomerB2BProfile();
                    $profile->setCustomer($customer);
                    $this->entityManager->persist($profile);
                }

                $changed = $this->applyString($profile, 'customer_number', $row, $changed);
                $changed = $this->applyString($profile, 'company_name', $row, $changed);
                $changed = $this->applyString($profile, 'vat_number', $row, $changed);
                $changed = $this->applyString($profile, 'erp_customer_number', $row, $changed);
                $changed = $this->applyString($profile, 'contact_person', $row, $changed);
                $changed = $this->applyString($profile, 'notes', $row, $changed);

                if (($row['customer_group_code'] ?? '') !== '') {
                    $groupCode = trim((string) $row['customer_group_code']);

                    /** @var CustomerGroup|null $group */
                    $group = $this->entityManager->getRepository(CustomerGroup::class)->findOneBy([
                        'code' => $groupCode,
                    ]);

                    if (!$group instanceof CustomerGroup) {
                        throw new \RuntimeException(sprintf(
                            'Row %d: customer group "%s" was not found.',
                            $rowNumber,
                            $groupCode,
                        ));
                    }

                    if ($customer->getGroup() !== $group) {
                        $customer->setGroup($group);
                        $changed = true;
                    }
                }

                if (($row['invoice_allowed'] ?? '') !== '') {
                    $value = $this->toBool($row['invoice_allowed']);
                    if ($profile->isInvoiceAllowed() !== $value) {
                        $profile->setInvoiceAllowed($value);
                        $changed = true;
                    }
                }

                if (($row['purchase_order_required'] ?? '') !== '') {
                    $value = $this->toBool($row['purchase_order_required']);
                    if ($profile->isPurchaseOrderRequired() !== $value) {
                        $profile->setPurchaseOrderRequired($value);
                        $changed = true;
                    }
                }

                if (($row['enabled'] ?? '') !== '') {
                    $value = $this->toBool($row['enabled']);
                    if ($profile->isEnabled() !== $value) {
                        $profile->setEnabled($value);
                        $changed = true;
                    }
                }

                if (($row['payment_term_days'] ?? '') !== '') {
                    $value = max(0, (int) $row['payment_term_days']);
                    if ($profile->getPaymentTermDays() !== $value) {
                        $profile->setPaymentTermDays($value);
                        $changed = true;
                    }
                }

                if (($row['credit_limit'] ?? '') !== '') {
                    $value = $this->moneyToMinor((string) $row['credit_limit']);
                    if ($profile->getCreditLimit() !== $value) {
                        $profile->setCreditLimit($value);
                        $changed = true;
                    }
                }

                if (($row['credit_limit_currency'] ?? '') !== '') {
                    $value = strtoupper(trim((string) $row['credit_limit_currency']));
                    if (!preg_match('/^[A-Z]{3}$/', $value)) {
                        throw new \RuntimeException(sprintf(
                            'Row %d: invalid credit_limit_currency "%s".',
                            $rowNumber,
                            $value,
                        ));
                    }

                    if ($profile->getCreditLimitCurrency() !== $value) {
                        $profile->setCreditLimitCurrency($value);
                        $changed = true;
                    }
                }

                ++$result['rows'];

                if ($created) {
                    ++$result['created'];
                } elseif ($changed) {
                    ++$result['updated'];
                } else {
                    ++$result['unchanged'];
                }
            }

            if ($dryRun) {
                $this->entityManager->rollback();
                $this->entityManager->clear();
            } else {
                $this->entityManager->flush();
            }
        } catch (\Throwable $exception) {
            if ($dryRun && $this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }

            $this->entityManager->clear();
            throw $exception;
        } finally {
            fclose($handle);
        }

        return $result;
    }

    /**
     * @param array<string, string> $row
     */
    private function applyString(
        CustomerB2BProfile $profile,
        string $column,
        array $row,
        bool $changed,
    ): bool {
        if (!array_key_exists($column, $row) || trim((string) $row[$column]) === '') {
            return $changed;
        }

        $value = trim((string) $row[$column]);

        $getter = match ($column) {
            'customer_number' => 'getCustomerNumber',
            'company_name' => 'getCompanyName',
            'vat_number' => 'getVatNumber',
            'erp_customer_number' => 'getErpCustomerNumber',
            'contact_person' => 'getContactPerson',
            'notes' => 'getNotes',
            default => throw new \LogicException(sprintf('Unsupported column "%s".', $column)),
        };

        $setter = match ($column) {
            'customer_number' => 'setCustomerNumber',
            'company_name' => 'setCompanyName',
            'vat_number' => 'setVatNumber',
            'erp_customer_number' => 'setErpCustomerNumber',
            'contact_person' => 'setContactPerson',
            'notes' => 'setNotes',
            default => throw new \LogicException(sprintf('Unsupported column "%s".', $column)),
        };

        if ((string) $profile->{$getter}() !== $value) {
            $profile->{$setter}($value);

            return true;
        }

        return $changed;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(
            mb_strtolower(trim((string) $value)),
            ['1', 'true', 'yes', 'ja', 'on'],
            true,
        );
    }

    private function moneyToMinor(string $value): int
    {
        $value = trim(str_replace(['€', ' '], '', $value));

        if (str_contains($value, ',') && str_contains($value, '.')) {
            if (strrpos($value, ',') > strrpos($value, '.')) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }

        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw new \InvalidArgumentException(sprintf('Invalid money value "%s".', $value));
        }

        return (int) round(((float) $value) * 100);
    }
}
