# Codex Task 01 — Knowledge Contracts and Validation

Read `AGENTS.md`.
Require all approved Phase-1 master files under `data/master/`.
If any required file or either staging diagnostics JSON is missing, stop with an exact missing-file list.

Implement only:
- JSON Schemas for course, syllabus, URL, image, intent, internal-link, blocked-claim, and test-fixture data.
- Immutable value objects and repository interfaces.
- A CLI validation command.
- Unit tests for every locked rule.
- Cross-site identity checks.
- Conflict/missing-syllabus reporting.
- Source-reference validation for fee and duration facts.

Do not build WordPress plugins yet.

Acceptance:
- CMS & ED validates as 2 Years and ₹17,000.
- Institute normal eligibility behavior validates as OMIT.
- Diploma fees and eligibility rules validate.
- No active Institute/Diploma course identity collision.
- Missing syllabus blocks only syllabus-specific intents.
- All tests pass.
Create PR: `feat: add canonical knowledge contracts and validators`.
