# Legacy interaktiv.shop customer migration

The importer reads the legacy pipe-delimited file line by line through Doctrine. It does not use raw SQL. Known columns are: customer number (0), login email (1), legacy hash (2), ERP number (3), company (7), first and last name (8/9), street/postcode/city/country (10–13), contact email (14), and phone (15). Unknown columns are deliberately ignored.

## Safe workflow

Preview first (nothing is persisted):

```bash
php bin/console cardnext:customers:import /path/customers.txt \
    --channel=CARDNEXT_DE --encoding=ISO-8859-1 --dry-run
```

After reviewing the counts and redacted issue report, run:

```bash
php bin/console cardnext:customers:import /path/customers.txt \
    --channel=CARDNEXT_DE --encoding=ISO-8859-1
```

Options include `--limit`, `--skip-existing`, and `--update-existing`. A channel is never assumed. Existing identities are matched globally by canonical login email, B2B customer number, and ERP customer number. Different matches or a different existing sales channel are conflicts rather than merges. Modern passwords and financial privileges are never overwritten. Repeated imports reuse the customer/profile and identical address.

In Admin, use **Kunden → Kundenimport**: upload the file, explicitly select a channel and encoding, run **Testlauf**, review the preview, then use **Import jetzt durchführen**. A random, server-side staging token binds the exact file and settings to confirmation; files are outside the public root and expire after 24 hours.

ISO-8859-1, Windows-1252, and UTF-8 are supported. Conversion is to validated UTF-8; the importer does not apply speculative character replacements. Country names are explicitly mapped to ISO alpha-2 codes and unknown values are rejected. A primary address and conservative B2B profile are created. Secondary contact email is preserved in notes without replacing the login.

## Transparent passwords

The migration-only verifier calculates `MD5(" " + value + " ")` four times. The regression vector is `cawima86` → `4ba9b4b88f8253e46bf219306d1f5601`. Symfony's supported Argon2id hasher remains primary: after successful legacy verification, the login-success migration listener writes a modern hash immediately (Sylius' username/email provider does not implement `PasswordUpgraderInterface`). Modern hashes never enter the legacy verifier and new/reset/changed passwords continue using only the primary hasher. Monitor progress (counts only) with `php bin/console cardnext:customers:legacy-password-status`.

## Deployment

```bash
composer install --no-dev --optimize-autoloader
APP_ENV=prod APP_DEBUG=0 php bin/console doctrine:migrations:migrate --no-interaction
rm -rf var/cache/prod
APP_ENV=prod APP_DEBUG=0 php bin/console cache:warmup
```

Deployment never starts an import; an administrator must trigger it manually.
