# Codex Task 05 — Native SEO Adapters and Local Image Engine

Implement:
- Rank Math adapter for Institute.
- AIOSEO adapter for Diploma.
- Adapter contract tests against staging-compatible structures.
- Local image renderer from mapped safe assets.
- 1200×630 WebP output.
- Separate Institute and Diploma templates.
- SEO filename, ALT text, Open Graph image, attachment verification.
- Duplicate-image hash protection.
- Fallback when a safe course image is missing.

No paid image API.

Acceptance:
- Metadata is visible in the native SEO plugin UI on staging.
- No duplicated or truncated meta description.
- Image is attached, rendered, and logged.
- No certificate/marksheet/student private data used as generic image.
Create PR: `feat: add native SEO and branded image pipeline`.
