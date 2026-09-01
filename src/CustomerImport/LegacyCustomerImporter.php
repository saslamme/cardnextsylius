<?php

declare(strict_types=1);

namespace App\CustomerImport;

use App\Entity\Addressing\Address;
use App\Entity\Customer\Customer;
use App\Entity\Customer\CustomerB2BProfile;
use App\Entity\User\ShopUser;
use App\Security\Hasher\LegacyInteraktivShopPasswordHasher;
use Doctrine\ORM\EntityManagerInterface;

final readonly class LegacyCustomerImporter
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LegacyCustomerParser $parser,
        private LegacyCountryMapper $countryMapper,
        private LegacyInteraktivShopPasswordHasher $legacyHasher,
    ) {
    }

    public function import(string $path, LegacyCustomerImportOptions $options): LegacyCustomerImportResult
    {
        $result = new LegacyCustomerImportResult();
        if ($options->dryRun) {
            $this->entityManager->beginTransaction();
        }

        try {
            foreach ($this->parser->parse($path, $options->encoding) as $row) {
                if ($options->limit !== null && $result->rows >= $options->limit) {
                    break;
                }
                ++$result->rows;
                $this->process($row, $options, $result);

                if (!$options->dryRun && $result->valid % self::BATCH_SIZE === 0) {
                    $this->entityManager->flush();
                }
            }

            if ($options->dryRun) {
                $this->entityManager->rollback();
                $this->entityManager->clear();
            } else {
                $this->entityManager->flush();
            }
        } catch (\UnexpectedValueException $exception) {
            ++$result->encodingErrors;
            $this->rollbackDryRun($options);
        } catch (\Throwable $exception) {
            if ($options->dryRun) {
                $this->entityManager->rollback();
                $this->entityManager->clear();
            }

            throw $exception;
        }

        return $result;
    }

    private function rollbackDryRun(LegacyCustomerImportOptions $options): void
    {
        if (!$options->dryRun) {
            return;
        }

        $this->entityManager->rollback();
        $this->entityManager->clear();
    }

    private function process(LegacyCustomerRow $row, LegacyCustomerImportOptions $options, LegacyCustomerImportResult $result): void
    {
        $email = mb_strtolower($row->get(LegacyCustomerColumns::LOGIN_EMAIL));
        if (!filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            ++$result->invalidEmail;
            $result->issue($row, 'invalid', 'Missing or invalid login email.');

            return;
        }
        $password = $row->get(LegacyCustomerColumns::PASSWORD_HASH);
        if (!$this->legacyHasher->isLegacyHash($password)) {
            ++$result->invalidHash;
            $result->issue($row, 'invalid', 'Malformed legacy password hash.');

            return;
        }
        $countryCode = $this->countryMapper->map($row->get(LegacyCustomerColumns::COUNTRY));
        if ($countryCode === null) {
            ++$result->unknownCountry;
            $result->issue($row, 'invalid', 'Unknown country.');

            return;
        }
        foreach ([LegacyCustomerColumns::STREET, LegacyCustomerColumns::POSTCODE, LegacyCustomerColumns::CITY] as $requiredAddressColumn) {
            if ($row->get($requiredAddressColumn) === '') {
                ++$result->otherErrors;
                $result->issue($row, 'invalid', 'Required address field is empty.');

                return;
            }
        }

        $customerNumber = $row->get(LegacyCustomerColumns::CUSTOMER_NUMBER);
        $erpNumber = $row->get(LegacyCustomerColumns::ERP_CUSTOMER_NUMBER);
        /** @var Customer|null $emailCustomer */
        $emailCustomer = $this->entityManager->getRepository(Customer::class)->findOneBy(['emailCanonical' => $email]);
        /** @var CustomerB2BProfile|null $numberProfile */
        $numberProfile = $customerNumber !== '' ? $this->entityManager->getRepository(CustomerB2BProfile::class)->findOneBy(['customerNumber' => $customerNumber]) : null;
        /** @var CustomerB2BProfile|null $erpProfile */
        $erpProfile = $erpNumber !== '' ? $this->entityManager->getRepository(CustomerB2BProfile::class)->findOneBy(['erpCustomerNumber' => $erpNumber]) : null;
        $identities = array_values(array_filter([$emailCustomer, $numberProfile?->getCustomer(), $erpProfile?->getCustomer()]));
        $identityIds = array_unique(array_map(static fn (Customer $customer): string => (string) ($customer->getId() ?? spl_object_id($customer)), $identities));
        if (count($identityIds) > 1) {
            ++$result->conflicts;
            $result->issue($row, 'conflict', 'Email, customer number, or ERP number belong to different customers.');

            return;
        }

        $customer = $identities[0] ?? null;
        $created = !$customer instanceof Customer;
        if (!$created && $customer->getSalesChannel() !== null && $customer->getSalesChannel() !== $options->channel) {
            ++$result->conflicts;
            $result->issue($row, 'conflict', sprintf('Customer belongs to %s, import targets %s.', $customer->getSalesChannel()->getCode(), $options->channel->getCode()));

            return;
        }
        if (!$created && ($options->skipExisting || !$options->updateExisting)) {
            ++$result->skipped;
            $this->preview($row, $result, 'Vorhanden');

            return;
        }

        if ($created) {
            $customer = new Customer();
            $customer->setEmail($email);
            $customer->setEmailCanonical($email);
            $customer->setSalesChannel($options->channel);
            $user = new ShopUser();
            $user->setUsername($email);
            $user->setUsernameCanonical($email);
            $user->setEnabled(true);
            $user->setPassword(strtolower($password));
            $user->setCustomer($customer);
            $customer->setUser($user);
            $this->entityManager->persist($customer);
            $this->entityManager->persist($user);
        } elseif ($customer->getSalesChannel() === null) {
            $customer->setSalesChannel($options->channel);
        }

        $customer->setFirstName($row->get(LegacyCustomerColumns::FIRST_NAME));
        $customer->setLastName($row->get(LegacyCustomerColumns::LAST_NAME));
        $customer->setPhoneNumber($row->get(LegacyCustomerColumns::PHONE) ?: null);
        $profile = $customer->getB2bProfile() ?? new CustomerB2BProfile();
        if ($customer->getB2bProfile() === null) {
            $profile->setCustomer($customer);
            $this->entityManager->persist($profile);
        }
        $profile->setCustomerNumber($customerNumber ?: null);
        $profile->setErpCustomerNumber($erpNumber ?: null);
        $profile->setCompanyName($row->get(LegacyCustomerColumns::COMPANY) ?: null);
        $profile->setContactPerson(trim($row->get(LegacyCustomerColumns::FIRST_NAME) . ' ' . $row->get(LegacyCustomerColumns::LAST_NAME)) ?: null);
        $profile->setEnabled(true);
        $this->preserveContactEmail($profile, $row->get(LegacyCustomerColumns::CONTACT_EMAIL));
        $this->applyAddress($customer, $row, $countryCode);

        ++$result->valid;
        $created ? ++$result->created : ++$result->updated;
        $this->preview($row, $result, $created ? 'Neu' : 'Aktualisieren');
    }

    private function applyAddress(Customer $customer, LegacyCustomerRow $row, string $countryCode): void
    {
        foreach ($customer->getAddresses() as $address) {
            if ($address->getStreet() === $row->get(LegacyCustomerColumns::STREET) && $address->getPostcode() === $row->get(LegacyCustomerColumns::POSTCODE) && $address->getCity() === $row->get(LegacyCustomerColumns::CITY) && $address->getCountryCode() === $countryCode) {
                return;
            }
        }
        $address = new Address();
        $address->setCompany($row->get(LegacyCustomerColumns::COMPANY) ?: null);
        $address->setFirstName($row->get(LegacyCustomerColumns::FIRST_NAME));
        $address->setLastName($row->get(LegacyCustomerColumns::LAST_NAME));
        $address->setStreet($row->get(LegacyCustomerColumns::STREET));
        $address->setPostcode($row->get(LegacyCustomerColumns::POSTCODE));
        $address->setCity($row->get(LegacyCustomerColumns::CITY));
        $address->setCountryCode($countryCode);
        $address->setPhoneNumber($row->get(LegacyCustomerColumns::PHONE) ?: null);
        $customer->addAddress($address);
        if ($customer->getDefaultAddress() === null) {
            $customer->setDefaultAddress($address);
        }
        $this->entityManager->persist($address);
    }

    private function preserveContactEmail(CustomerB2BProfile $profile, string $email): void
    {
        if ($email === '' || !filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $line = 'Legacy contact email: ' . $email;
        if (!str_contains((string) $profile->getNotes(), $line)) {
            $profile->setNotes(trim((string) $profile->getNotes() . "\n" . $line));
        }
    }

    private function preview(LegacyCustomerRow $row, LegacyCustomerImportResult $result, string $status): void
    {
        if (count($result->preview) >= 25) {
            return;
        }
        $result->preview[] = ['customerNumber' => $row->get(LegacyCustomerColumns::CUSTOMER_NUMBER), 'company' => $row->get(LegacyCustomerColumns::COMPANY), 'contact' => trim($row->get(LegacyCustomerColumns::FIRST_NAME) . ' ' . $row->get(LegacyCustomerColumns::LAST_NAME)), 'email' => $row->get(LegacyCustomerColumns::LOGIN_EMAIL), 'postcode' => $row->get(LegacyCustomerColumns::POSTCODE), 'city' => $row->get(LegacyCustomerColumns::CITY), 'status' => $status];
    }
}
