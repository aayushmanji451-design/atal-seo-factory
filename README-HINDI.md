# ATAL SEO Factory — Codex Start Pack

## Codex का काम
Codex इस software factory का code, tests, GitHub pull requests और release process बनाएगा.
Codex को daily SEO writer या WordPress cron की तरह use नहीं करना है.

## Final runtime
- ATAL SEO Factory Core: atalinstitute.com
- Atal Diploma Receiver: ataldiploma.com
- Manual Plus JSON mode: हमेशा उपलब्ध
- Optional API mode: ₹1,800 hard monthly cap
- Local branded image engine: default
- Action Scheduler background jobs
- Hostinger system cron
- Adaptive publishing, blind 50+50 publishing नहीं

## Recommended capacity
- Generate: 60 Institute + 60 Diploma drafts/day
- Initial publish: 15 Institute + 25 Diploma/day
- Then: 25/35 → 35/45 → maximum 50/50 after indexing and failure-rate gates
- Extra approved drafts remain scheduled for later dates

## First preparation
1. Create a private GitHub repository: `atal-seo-factory`.
2. Connect that repository to Codex.
3. Add the 12 approved Phase-1 master knowledge files under `data/master/`.
4. Add both staging diagnostics JSON files under `docs/environment/`.
5. Add safe logos and course-image assets under `assets/source/`.
6. Add this `AGENTS.md` at repository root.
7. Do not add secrets.
8. Do not add legacy plugin code to active source. Put it later in `legacy-evidence/`.

## Required repository layout
See `docs/repository-tree.txt`.

## Execution rule
Run prompts in numeric order.
Each prompt must create one focused PR.
Merge only after tests and review pass.
