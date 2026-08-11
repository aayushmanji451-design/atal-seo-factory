# Codex Task 04 — One Institute + One Diploma End-to-End Canary

Implement the smallest complete publishing flow.

Required:
- Manual JSON import of exactly one article.
- Fact validation.
- Review state.
- Correct target-site routing.
- Institute post creation with Rank Math adapter.
- Diploma signed forwarding with AIOSEO adapter.
- One mapped featured image per site.
- Media attachment ID verification.
- Audit logs.
- Local rollback and remote rollback contract.
- No bulk jobs yet.

Acceptance on staging:
- One Institute post passes metadata, image, links, and rollback checks.
- One Diploma post passes metadata, image, signed request, idempotency, and rollback checks.
- Title, H1, SEO title, focus keyword, slug, and intent align.
- Meta description is 140–160 characters.
- Conclusion is the last content section.
Create PR: `feat: complete one-post staging canary`.
