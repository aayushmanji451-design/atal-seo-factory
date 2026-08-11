# Codex Task 09 — Legacy Analyzer, Load Tests, Release and Rollback

Do not reuse legacy tables as source of truth.

Implement:
- Read-only legacy analyzer.
- Export-only migration preview.
- Explicit-selection importer.
- Backup requirement before any write.
- Never overwrite a published post without selected ID and confirmation.
- 1+1, 5+5, 10+10, and 50+50 queued-job test plan.
- Staging acceptance report.
- Release ZIP workflow for both plugins.
- SHA-256 checksums.
- Installation, upgrade, rollback, and monitoring docs.
- Production canary checklist.

Acceptance:
- 50+50 jobs queue without one-request processing.
- Release workflow builds reproducible ZIPs.
- Rollback rehearsal passes on staging.
- No production release until all acceptance gates are documented as PASS.
Create PR: `release: prepare validated staging release candidate`.
