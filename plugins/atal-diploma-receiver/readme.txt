=== Atal Diploma Receiver ===
Contributors: atal-institute
Requires at least: 6.9
Requires PHP: 8.1
Stable tag: 0.3.0-dev

DEVELOPMENT build for Task 03. It registers a versioned authenticated receiver,
uses HMAC SHA-256 with timestamp and replay checks, enforces idempotency, validates
the canonical Atal Diploma identity, and persists only receiver-owned drafts.

The Tools health page and acceptance runner are staging-only and never publish a
course article. This build performs no AI, generation, scheduling, or bulk work.
It must not be treated as a final release.
