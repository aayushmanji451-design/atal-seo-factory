# Codex Task 02 — Core Plugin Skeleton and Database Migrations

Build the minimal `atal-seo-factory-core` plugin skeleton only.

Required:
- New plugin slug, namespace, option names, REST namespace, and DB tables.
- Versioned, reversible database migrations.
- Tables for courses, topics, articles, assets, publish jobs, cost ledger, and audit logs.
- Activation/deactivation safety.
- Read-only staging health page.
- Knowledge-file importer with dry-run and transactional import.
- No article generation, no publishing, no receiver call, no bulk repair.

Acceptance:
- Activation/deactivation produces no fatal error.
- Re-activation is idempotent.
- Migration rollback is tested.
- Knowledge dry-run reports changes before import.
- Plugin can be installed on Institute staging.
Create PR: `feat: add Core skeleton and versioned storage`.
