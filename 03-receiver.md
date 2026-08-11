# Codex Task 03 — Atal Diploma Receiver

Build the small `atal-diploma-receiver` plugin.

Required:
- HMAC SHA-256 request verification.
- Timestamp tolerance and replay protection.
- Idempotency key storage.
- Capability, validation, sanitization, and escaping.
- Health endpoint and authenticated contract-test endpoint.
- Post create/update service behind an interface.
- AIOSEO adapter behind an interface.
- Featured-image attachment verification behind an interface.
- Exact structured error codes.
- No AI, no heavy dashboard, no content repair system.

Acceptance:
- Contract tests cover valid, expired, tampered, replayed, and duplicate requests.
- Duplicate idempotency key cannot create a second post.
- No secret is logged.
- Receiver installs on Diploma staging without fatal error.
Create PR: `feat: add secure Diploma receiver`.
