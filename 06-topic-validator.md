# Codex Task 06 — Deterministic Topic Registry and Content Gates

Implement:
- Topic identity: site + course_key + intent + primary_keyword + year.
- Sequential weighted course rotation.
- Published keyword/URL registry.
- Title, heading, paragraph, FAQ, conclusion, and semantic similarity checks.
- Cannibalization checks.
- Site-specific fact and claim rules.
- Conclusion-is-last validation.
- Internal-link validation.
- Quality states: PASS, NEEDS_REVIEW, REJECTED.
- Explainable validation findings.

No AI generation yet.

Acceptance:
- Random course selection is impossible.
- Duplicate topic cannot be inserted twice.
- Institute eligibility wording is rejected in normal posts.
- Missing syllabus rejects only syllabus-specific content.
- Generic repeated filler fixtures fail.
Create PR: `feat: add deterministic topics and quality gates`.
