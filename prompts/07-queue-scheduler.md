# Codex Task 07 — Background Queue, Scheduler, Retry, and Logs

Integrate Action Scheduler as a library.

Each action must process one unit:
- validate article;
- render image;
- save SEO;
- create/update post;
- send Diploma request;
- verify response;
- schedule;
- rollback.

Implement:
- Idempotent jobs.
- Job groups.
- Retry with capped exponential backoff.
- Dead-letter/failed state.
- Admin job visibility.
- Emergency pause.
- Hostinger system-cron compatibility.
- Adaptive publishing caps independent from generation count.

Do not process a full batch in one browser request.

Acceptance:
- One failed job does not stop other jobs.
- 5+5 and 10+10 staging queues complete.
- Failures have exact logs and retry history.
- Browser requests remain lightweight.
Create PR: `feat: add traceable background job pipeline`.
