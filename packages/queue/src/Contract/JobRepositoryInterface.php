<?php
/**
 * Contract for background job storage, locking, leases, and attempt history.
 *
 * @package AtalQueue
 */

declare(strict_types=1);

namespace Atal\Queue\Contract;

use Atal\Queue\Domain\Job;
use Atal\Queue\Domain\JobAttempt;

/**
 * Atomic lease management and job lifecycle storage.
 */
interface JobRepositoryInterface {

	/**
	 * Enqueue a new job if its idempotency key is not already registered.
	 *
	 * @param Job $job Job to enqueue.
	 */
	public function enqueue( Job $job ): void;

	/**
	 * Find a job by its unique job key.
	 *
	 * @param string $job_key Unique job key.
	 */
	public function find_by_key( string $job_key ): ?Job;

	/**
	 * Find a job by its idempotency key.
	 *
	 * @param string $idempotency_key Logical uniqueness key.
	 */
	public function find_by_idempotency( string $idempotency_key ): ?Job;

	/**
	 * Find a job by article item key and pipeline stage.
	 *
	 * @param string $item_key Article key.
	 * @param string $stage    Pipeline stage.
	 */
	public function find_by_item_and_stage( string $item_key, string $stage ): ?Job;

	/**
	 * Claim the next available job atomically with an exclusive lease.
	 *
	 * @param string      $worker_id     Worker holding lease.
	 * @param int         $lease_seconds Duration of lease in seconds.
	 * @param int         $now           Current UTC epoch timestamp.
	 * @param string|null $group         Optional stage group filter.
	 */
	public function claim_next( string $worker_id, int $lease_seconds, int $now, ?string $group = null ): ?Job;

	/**
	 * Extend an existing lease while a long unit is running.
	 *
	 * @param string $job_key              Job key.
	 * @param string $worker_id            Lease holder.
	 * @param int    $new_lease_expires_at New lease expiry time.
	 */
	public function heartbeat( string $job_key, string $worker_id, int $new_lease_expires_at ): bool;

	/**
	 * Mark a job as successfully completed and release its lease.
	 *
	 * @param string $job_key   Job key.
	 * @param string $worker_id Lease holder.
	 * @param int    $now       Completion timestamp.
	 */
	public function complete( string $job_key, string $worker_id, int $now ): void;

	/**
	 * Record a transient failure and schedule for retry with exponential backoff.
	 *
	 * @param string   $job_key   Job key.
	 * @param string   $worker_id Lease holder.
	 * @param string   $code      Error code.
	 * @param string   $message   Redacted error message.
	 * @param int|null $retry_at  Next run time, or null if immediate.
	 * @param int      $now       Current timestamp.
	 */
	public function record_failure( string $job_key, string $worker_id, string $code, string $message, ?int $retry_at, int $now ): void;

	/**
	 * Move a job into dead-letter state after max retries or fatal error.
	 *
	 * @param string $job_key   Job key.
	 * @param string $worker_id Lease holder.
	 * @param string $code      Fatal error code.
	 * @param string $message   Redacted error message.
	 * @param int    $now       Current timestamp.
	 */
	public function record_dead_letter( string $job_key, string $worker_id, string $code, string $message, int $now ): void;

	/**
	 * Cancel an open job.
	 *
	 * @param string $job_key Job key.
	 * @param string $reason  Cancellation reason.
	 * @param int    $now     Current timestamp.
	 */
	public function cancel( string $job_key, string $reason, int $now ): void;

	/**
	 * Persist an attempt history entry for auditability.
	 *
	 * @param JobAttempt $attempt Attempt snapshot.
	 */
	public function record_attempt( JobAttempt $attempt ): void;

	/**
	 * Get all attempt records for a specific job.
	 *
	 * @param string $job_key Job key.
	 *
	 * @return list<JobAttempt>
	 */
	public function attempts_for_job( string $job_key ): array;

	/**
	 * Reclaim jobs with expired leases whose workers crashed (safe restart).
	 *
	 * @param int $now Current timestamp.
	 *
	 * @return int Number of reclaimed jobs.
	 */
	public function reclaim_expired_leases( int $now ): int;

	/**
	 * Get all jobs belonging to a specific daily batch.
	 *
	 * @param string $batch_key Batch key.
	 *
	 * @return list<Job>
	 */
	public function jobs_for_batch( string $batch_key ): array;

	/**
	 * Return all jobs.
	 *
	 * @return list<Job>
	 */
	public function all_jobs(): array;

	/**
	 * Return job counts grouped by state.
	 *
	 * @return array<string,int>
	 */
	public function count_by_state(): array;

	/**
	 * Count published posts completed within the time window.
	 *
	 * @param string $target_site        Canonical site.
	 * @param int    $day_start_timestamp Start of window.
	 * @param int    $day_end_timestamp   End of window.
	 */
	public function count_published_today( string $target_site, int $day_start_timestamp, int $day_end_timestamp ): int;

	/**
	 * Count generation jobs completed within the time window.
	 *
	 * @param string $target_site        Canonical site.
	 * @param int    $day_start_timestamp Start of window.
	 * @param int    $day_end_timestamp   End of window.
	 */
	public function count_generated_today( string $target_site, int $day_start_timestamp, int $day_end_timestamp ): int;
}
