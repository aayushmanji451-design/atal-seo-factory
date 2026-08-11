# Codex Task 00 — Repository Bootstrap Only

Read `AGENTS.md` first and obey it.

Create the development foundation for a clean WordPress monorepo named ATAL SEO Factory.

Do not implement business logic, WordPress admin screens, REST publishing, AI generation, migration, or images in this task.

Deliver:
1. Repository directories from `docs/repository-tree.txt`.
2. Composer setup.
3. PSR-4 namespaces:
   - `Atal\\SeoFactory\\`
   - `Atal\\DiplomaReceiver\\`
   - `Atal\\Contracts\\`
4. PHPUnit, PHPCS with WordPress Coding Standards, and PHPStan configuration.
5. A minimal test bootstrap that can run without production credentials.
6. GitHub Actions CI for PHP 8.1, 8.2, and 8.3.
7. A release workflow skeleton that does not release yet.
8. `.gitignore`, `.gitattributes`, `.editorconfig`, `.env.example`.
9. Root README explaining local setup and test commands.
10. A single health test proving CI is wired.

Acceptance:
- `composer validate` passes.
- PHPCS passes.
- PHPStan passes.
- PHPUnit passes.
- No installable plugin ZIP is created.
- No business data is hardcoded.
- Open a focused PR titled: `chore: bootstrap ATAL SEO Factory monorepo`.
Stop after the PR summary.
