<?php

declare(strict_types=1);

namespace App\CustomerImport;

/** Known pipe-file columns. Unlisted legacy columns intentionally have no meaning. */
final class LegacyCustomerColumns
{
    public const CUSTOMER_NUMBER = 0;

    public const LOGIN_EMAIL = 1;

    public const PASSWORD_HASH = 2;

    public const ERP_CUSTOMER_NUMBER = 3;

    public const COMPANY = 7;

    public const FIRST_NAME = 8;

    public const LAST_NAME = 9;

    public const STREET = 10;

    public const POSTCODE = 11;

    public const CITY = 12;

    public const COUNTRY = 13;

    public const CONTACT_EMAIL = 14;

    public const PHONE = 15;

    private function __construct()
    {
    }
}
