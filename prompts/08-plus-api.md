# Codex Task 08 — Plus JSON Mode and Optional Budget API Mode

Implement two independent modes.

A. Plus Manual JSON Mode
- Schema-validated imports.
- Batch preview.
- Dry-run.
- No API required.
- Imported articles still pass all normal gates.

B. Optional API Mode
- Provider interface.
- OpenAI Batch-compatible worker.
- No API key in database logs or repository.
- Monthly cost ledger.
- ₹1,200 target, ₹1,500 soft, ₹1,800 hard, ₹2,000 absolute stop.
- At hard stop, generation stops but existing publishing continues.
- Manual Plus mode remains active.
- Generation target configurable up to 60+60 drafts/day.
- Publishing remains adaptive and separately configured.

Acceptance:
- API-off mode works fully.
- Hard budget stop is tested.
- Queue publishing continues after API generation pauses.
Create PR: `feat: add Plus import and budget-controlled API worker`.
