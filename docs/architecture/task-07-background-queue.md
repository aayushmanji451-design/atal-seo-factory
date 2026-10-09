# Task 07 Background Queue and Daily Automation Architecture

Task 07 implements the local background queue architecture, daily automation pipelines, and operator controls for the ATAL SEO Factory.

---

## 1. Product Requirements and Volume Targets

### Production Target: Configurable 25 + 25 Posts / Day
- **ATAL Institute**: Default target of 25 posts/day.
- **Atal Diploma**: Default target of 25 posts/day.
- **Total Production Target**: 50 posts/day across both properties.
- **Configurability**: Target limits are fully configurable via `QueueConfig` and the `Task07Panel` admin interface. They are never hardcoded.
- **Staggered Publishing**: Posts are not dumped at once; they are spaced throughout the day (default stagger interval: 900 seconds / 15 minutes).

### Reconciliation: 25+25 Production Target vs 50+50 Load Testing
- **25+25 per day** is the normal, sustainable production requirement.
- **50+50** remains strictly an intensive staging load test milestone to verify throughput and resource limits (e.g. WP_MEMORY_LIMIT, timeout thresholds) before final production go-live.

---

## 2. Automation Modes

### Mode A: Daily Approval Mode (Default)
1. **Preparation**: The system prepares the daily batch for the target date (`batch_YYYYMMDD`).
2. **Pre-Validation**: All 25 + 25 candidate topics run through the complete canonical quality gates:
   - Canonical course identity and syllabus scope
   - Search intent taxonomy compliance
   - Fact verification (fees, durations from master data)
   - Site-specific eligibility rules (omitted for Institute, scoped for Diploma)
   - Blocked claim enforcement (zero doctor/license/government/job guarantees)
   - Primary keyword alignment in title and headings
   - Internal linking rules (same-site only)
   - Structural rules (conclusion-last, no generic filler, depth requirements)
   - Cannibalization, slug collisions, and duplicate prevention
3. **Owner Summary**: A single consolidated summary is generated showing total candidates, valid candidates, and isolated/blocked candidates.
4. **Single Approval**: The site owner reviews the summary and gives **ONE single approval** for the whole day's batch.
5. **Staggered Queueing**: Once approved, all valid items are enqueued with progressive `available_at` timestamps staggered through the day.
6. **Failure Isolation**: Blocked or rejected candidates are isolated; they never block or delay the approved valid items.

### Mode B: Full Auto Mode
1. **Automated Preparation & Queueing**: The daily batch is automatically prepared and validated.
2. **Immediate Staggered Release**: Valid posts that pass all canonical quality gates are automatically enqueued with staggered schedules without waiting for manual owner approval.
3. **Mandatory Safety Gates**: All validation rules, blocked-claim checks, and duplicate protections remain 100% active and non-bypassable.

---

## 3. Background Queue Core Architecture

The background queue package is implemented as an independent domain in `packages/queue/src/`:

```
packages/queue/src/
├── Application/
│   ├── Handler/
│   │   ├── DeliverDraftHandler.php
│   │   ├── PublishHandler.php
│   │   ├── RenderImageHandler.php
│   │   ├── RollbackHandler.php
│   │   ├── SaveSeoHandler.php
│   │   ├── ValidationHandler.php
│   │   └── VerifyHandler.php
│   ├── DailyBatchCoordinator.php
│   ├── JobExecutionSummary.php
│   └── QueueWorker.php
├── Contract/
│   ├── BatchRepositoryInterface.php
│   ├── JobHandlerInterface.php
│   ├── JobRepositoryInterface.php
│   └── QueueControlStoreInterface.php
├── Domain/
│   ├── DailyBatch.php
│   ├── DailyBatchItem.php
│   ├── Job.php
│   ├── JobAttempt.php
│   ├── JobExecutionContext.php
│   ├── JobResult.php
│   ├── JobStage.php
│   ├── JobState.php
│   ├── QueueConfig.php
│   ├── QueueControlState.php
│   └── RetryPolicy.php
├── Infrastructure/
│   └── InMemory/
│       ├── InMemoryBatchRepository.php
│       ├── InMemoryJobRepository.php
│       └── InMemoryQueueControlStore.php
└── Support/
    └── Arr.php
```

### Key Capabilities
- **Bounded Jobs**: Every job processes exactly one discrete unit of work for a single post at a single stage (`validate`, `render_image`, `save_seo`, `deliver_draft`, `verify`, `publish`, `rollback`).
- **Atomic Lease Locking**: Workers claim pending jobs by atomically acquiring a time-bounded lease (`lease_owner`, `lease_expires_at`).
- **Crash Recovery & Safe Restart**: Expired leases from crashed workers are automatically reclaimed (`reclaim_expired_leases`) and requeued without data corruption.
- **Idempotency Keys**: Deterministic idempotency keys (`item_key:stage:generation`) prevent duplicate execution.
- **Exponential Backoff with Jitter**: Failed jobs retry using capped exponential backoff (`base * 2^(attempt-1)` with jitter, capped at `max_backoff_seconds`).
- **Dead-Letter State**: Jobs exceeding `max_job_attempts` (default: 5) transition to terminal `dead` state for inspection.
- **Failure Isolation**: An unhandled exception or failed post is isolated to that specific job; other jobs proceed unhindered.
- **Operator Switches**: Global `running`, `paused`, and `emergency_stop` switches stored in `QueueControlState`. `emergency_stop` halts workers immediately.
- **Compensating Rollback**: Bounded rollback handler reverses partial post mutations if delivery fails.
- **Quota Separation**: Separate daily caps for generation units vs live publishing units to prevent accidental flooding.

---

## 4. WordPress Plugin Integration

- **Core Plugin Autoloading**: Registered `'Atal\Queue\' => __DIR__ . '/dependencies/queue/'` in `atal-seo-factory-core.php`.
- **Infrastructure Stores**:
  - `WordPressJobRepository`: Persists queue jobs in the Core-owned custom table with atomic lease claiming.
  - `WordPressBatchRepository`: Persists daily batch states and item summaries.
  - `WordPressQueueControlStore`: Stores global queue switches and configuration in WordPress options.
- **Admin Dashboard Panel (`Task07Panel`)**:
  - Located on the ATAL SEO Factory Health page (`tools.php?page=atal-seo-factory-health`).
  - Strict capability enforcement (`manage_options`) and nonce verification on all actions.
  - Real-time queue metrics: State, pending, running, waiting retry, succeeded, dead, cancelled counts.
  - Controls: Config save, Institute daily limit, Diploma daily limit, generation limit, publishing limit, stagger interval, Mode toggle, "Prepare Today's Batch", "Approve Today's Batch", "Skip Today's Batch", "Pause", "Resume", "Emergency Stop", "Reset Emergency", and "Run Bounded Worker Step".
- **Service Factory**: Lazy instantiation wired through `ServiceFactory::queue_control_store()`, `job_repository()`, `batch_repository()`, `daily_coordinator()`, `queue_worker()`, and `task07_panel()`.

---

## 5. Local Quality Gates & Verification

All changes pass 100% of local quality checks:
- **PHPUnit**: 126 tests passing, 863 assertions (0 failures, 0 errors).
- **PHPStan**: Level 8 analysis passing ([OK] No errors).
- **PHPCS**: WordPress Coding Standards clean (0 errors, 0 warnings).
- **Composer**: `composer.json` valid.

---

## 6. Staging & Production Guardrails

- **Production Safety**: Zero production files, databases, or DNS records touched. No live publication to production.
- **API Call Safety**: Zero paid external API calls made.
- **Institute Staging Status**: Acceptance testing (5+5 and 10+10 stages) remains deferred until a safe replacement Institute staging environment is confirmed.
