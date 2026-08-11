# AGENTS.md — ATAL SEO Factory

## Mission
Build a clean, staging-first, Plus-compatible WordPress SEO factory for:
1. atalinstitute.com
2. ataldiploma.com

The system must contain failures, never blind-publish, and remain usable when ChatGPT Pro is replaced by Plus.

## Non-negotiable architecture
- Clean rebuild only.
- Do not copy or extend legacy V3/V4 code into production source.
- Legacy code may exist only under `legacy-evidence/` and must be read-only.
- Two installable plugins:
  - `atal-seo-factory-core` for ATAL Institute.
  - `atal-diploma-receiver` for Atal Diploma.
- Optional API automation is a modular worker inside Core, not a third monolithic plugin.
- Use new namespaces, tables, options, REST routes, cron hooks, and Action Scheduler groups.
- Never reuse legacy tables as the source of truth.
- No single PHP class should become a whole application. Prefer small services with explicit interfaces.
- No browser request may process an entire article batch, image batch, migration, rebuild, or test suite.
- Long-running operations must be idempotent background jobs.
- One failed job must not stop unrelated jobs.

## Security
- Never commit credentials, API keys, WordPress passwords, HMAC secrets, tokens, salts, or production database dumps.
- Provide `.env.example` or `wp-config.php.example` only.
- Use capability checks, nonces, sanitization, escaping, timestamped HMAC requests, replay protection, and idempotency keys.
- Do not expose private student data in logs, images, tests, fixtures, or screenshots.

## Locked content rules
### ATAL Institute
- Normal posts omit eligibility.
- CMS & ED: 2 Years, ₹17,000.
- No disclaimer section.
- No negative wording such as “practice के लिए नहीं”, “clinic नहीं खोल सकते”, or “doctor नहीं बन सकते”.
- No unsupported doctor, licence, government-authority, clinic-authority, or guaranteed-job claims.
- Rank Math adapter.
- Default course hub: https://atalinstitute.com/all-courses/

### Atal Diploma
- Keep University Diploma/PG Diploma separate from Institute courses.
- Basic Health Care and DNYS: 12th Pass.
- First Aid Treatment course fee: ₹25,800.
- Hospital Management course fee: ₹25,000.
- General applicable course fee: ₹30,000.
- Use matching individual course URL when available.
- AIOSEO adapter.
- Never invent a missing syllabus.

## Quality gates
Every publishable article must pass:
- fact validation;
- target-site validation;
- title/H1/SEO/focus-keyword alignment;
- complete 140–160 character meta description;
- internal-link validation;
- semantic duplicate and cannibalization checks;
- featured-image attachment verification;
- prohibited-claim validation;
- conclusion-is-last validation.

## Engineering quality
Every code task must:
- add or update tests;
- run Composer validation;
- run PHPCS/WPCS;
- run PHPStan;
- run PHPUnit;
- run any WordPress integration tests documented in the repo;
- leave the worktree clean;
- document commands and results in the PR.

Do not claim a feature works merely because PHP lint passes.
Differentiate static validation, staging validation, and production validation.

## Git workflow
- One Codex task = one focused pull request.
- Do not combine unrelated phases.
- Do not merge a PR with failing required checks.
- Do not rewrite history or amend previous commits.
- Release ZIPs are created only by the release workflow after acceptance gates pass.

## Test progression
1. Static and unit tests.
2. Staging health.
3. One Institute canary.
4. One Diploma canary.
5. 5 + 5 batch.
6. 10 + 10 batch.
7. 50 + 50 queued jobs.
8. Production release package and rollback rehearsal.

## Stop conditions
Stop and report instead of guessing when:
- a locked fact conflicts;
- a required staging diagnostic is missing;
- a native SEO plugin structure cannot be verified;
- an image asset cannot be safely mapped;
- a migration would overwrite a published post;
- a task requires live credentials.
