# ATAL SEO Factory

ATAL SEO Factory is a clean WordPress monorepo foundation for two independently installable plugins. Task 00 contains development tooling and repository structure only; it does not contain plugin business logic, publishing workflows, production credentials, or release packages.

## Requirements

- PHP 8.1, 8.2, or 8.3
- Composer 2

No WordPress installation or production credentials are required for the Task 00 health test.

## Local setup

1. Copy `.env.example` to `.env` only when local environment overrides are needed.
2. Install development dependencies:

   ```console
   composer install
   ```

3. Run the complete local quality suite:

   ```console
   composer validate --strict
   composer phpcs
   composer phpstan
   composer phpunit
   ```

   The three quality tools can also be run together with `composer test`.

## Namespace layout

| Namespace | Source directory |
| --- | --- |
| `Atal\SeoFactory\` | `plugins/atal-seo-factory-core/src/` |
| `Atal\DiplomaReceiver\` | `plugins/atal-diploma-receiver/src/` |
| `Atal\Contracts\` | `packages/contracts/src/` |

## Validation levels

The Task 00 suite provides static validation and a credential-free unit health check. Staging and production validation are intentionally deferred because this bootstrap contains no runtime plugin implementation.

## Continuous integration and releases

The CI workflow defines the same validation suite for PHP 8.1, 8.2, and 8.3. The release workflow is an explicitly disabled skeleton: it has read-only permissions and creates no ZIP or release.
