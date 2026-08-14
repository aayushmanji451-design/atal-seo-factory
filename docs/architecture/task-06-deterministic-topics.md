# Task 06 deterministic topics and quality gates

Task 06 is a Core-only, development-stage capability. It does not register a
publisher, scheduler, AI client, paid API, media generator, or WordPress post
mutation.

## Identity and rotation

The immutable topic identity is the tuple:

`target_site + course_key + intent + normalized primary_keyword + year`

Its `topic_key` is a deterministic SHA-256-derived identifier. Rotation is a
sequential weighted schedule in caller-supplied approved order. Institute and
Diploma cursors are stored independently under new Core-owned option keys.
Preview never advances a cursor. A committed selection advances it to the next
slot, survives service reconstruction, and reports every skipped course and the
canonical reason. When no approved priority weight exists in the master data,
the admin preview uses weight `1`; it does not invent a priority.

## Registry and validation

The existing Core-owned `topics` table remains the source of truth. Payloads
hold the keyword, slug, optional published URL, content fingerprint, and
validation inputs. The unique deterministic key makes retries idempotent;
changed payloads update the same row transactionally. Failed writes roll back.

The validator re-runs the Task 01 package validator and applies explainable
rules for:

- canonical course, target-site, intent, fee, duration, and eligibility;
- the six approved missing-data blocks, scoped only to blocked intents;
- Institute eligibility omission and Diploma course-specific eligibility;
- blocked claims and cross-site links;
- keyword/title/heading alignment and conclusion-last structure;
- repeated filler and thin-content review;
- keyword cannibalization and slug/published-URL collisions;
- title, heading, paragraph, FAQ, and whole-content similarity.

Every finding contains `rule_id`, `status`, `field`, `expected`, `actual`,
`explanation`, and `safe_correction`. Aggregate states are `PASS`,
`NEEDS_REVIEW`, or `REJECTED`. Only `PASS` is accepted by the transactional
registry writer.

## Admin safety

The development panel is limited to users with `manage_options` and requires a
WordPress nonce. Its two operations are bounded rotation preview and local JSON
proposal validation. Neither operation writes topics or WordPress content.
