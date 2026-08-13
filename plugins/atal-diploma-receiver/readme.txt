=== Atal Diploma Receiver ===
Contributors: atal-institute
Requires at least: 6.9
Requires PHP: 8.1
Stable tag: 0.4.0-dev

DEVELOPMENT build for Task 04. It retains the versioned authenticated receiver,
uses HMAC SHA-256 with timestamp and replay checks, enforces idempotency, validates
the canonical Atal Diploma identity, and persists only receiver-owned drafts.

The receiver accepts only authenticated drafts and returns bounded canary
verification evidence for the approved one-post flow. This build performs no
AI, public publishing, image generation, scheduling, or bulk work.
It must not be treated as a final release.
