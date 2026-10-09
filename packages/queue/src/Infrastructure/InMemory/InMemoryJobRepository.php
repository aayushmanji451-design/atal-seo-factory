<?php
/**
 * In-memory job repository for fast, reliable unit and contract testing.
 *
 * @package AtalQueue
 */

declare(strict_types=1);

namespace Atal\Queue\Infrastructure\InMemory;

use Atal\Queue\Contract\JobRepositoryInterface;
use Atal\Queue\Domain\Job;
use Atal\Queue\Domain\JobAttempt;
use Atal\Queue\Domain\JobStage;
use Atal\Queue\Domain\JobState;

/**
 * Thread-safe simulated job store with atomic leases and attempt history.
 */
final class InMemoryJobRepository implements JobRepositoryInterface {

	/**
	 * Stored jobs indexed by job_key.
	 *
	 * @var array<string,Job>
	 */
	private array $jobs = array();

	/**
	 * Attempt logs indexed by job_key.
	 *
	 * @var array<string,list<JobAttempt>>
	 */
	private array $attempts = array();

	public function enqueue( Job $job ): void {
		// Idempotency check: if job with same idempotency key already exists, do not overwrite.
		foreach ( $this->jobs as $existing ) {
			if ( $existing->idempotency_key === $job->idempotency_key ) {
				return;
			}
		}

		$this->jobs[ $job->job_key ] = $job;
	}

	public function find_by_key( string $job_key ): ?Job {
		return $this->jobs[ $job_key ] ?? null;
	}

	public function find_by_idempotency( string $idempotency_key ): ?Job {
		foreach ( $this->jobs as $job ) {
			if ( $job->idempotency_key === $idempotency_key ) {
				return $job;
			}
		}

		return null;
	}

	public function find_by_item_and_stage( string $item_key, string $stage ): ?Job {
		foreach ( $this->jobs as $job ) {
			if ( $job->item_key === $item_key && $job->stage === $stage ) {
				return $job;
			}
		}

		return null;
	}

	public function claim_next( string $worker_id, int $lease_seconds, int $now, ?string $group = null ): ?Job {
		foreach ( $this->jobs as $key => $job ) {
			if ( ! JobState::is_open( $job->state ) ) {
				continue;
			}
			if ( JobState::RUNNING === $job->state && $job->lease_expires_at > $now ) {
				// Leased by active worker.
				continue;
			}
			if ( $job->available_at > $now ) {
				// Not yet available (scheduled delay or retry backoff).
				continue;
			}
			if ( null !== $group && JobStage::group( $job->stage ) !== $group ) {
				continue;
			}

			// Atomic claim with exclusive lease.
			$claimed = $job->with(
				array(
					'state'            => JobState::RUNNING,
					'lease_owner'      => $worker_id,
					'lease_expires_at' => $now + $lease_seconds,
					'attempts'         => $job->attempts + 1,
					'updated_at'       => $now,
				)
			);

			$this->jobs[ $key ] = $claimed;

			return $claimed;
		}

		return null;
	}

	public function heartbeat( string $job_key, string $worker_id, int $new_lease_expires_at ): bool {
		$job = $this->jobs[ $job_key ] ?? null;
		if ( null === $job || $job->lease_owner !== $worker_id || JobState::RUNNING !== $job->state ) {
			return false;
		}

		$this->jobs[ $job_key ] = $job->with(
			array(
				'lease_expires_at' => $new_lease_expires_at,
			)
		);

		return true;
	}

	public function complete( string $job_key, string $worker_id, int $now ): void {
		$job = $this->jobs[ $job_key ] ?? null;
		if ( null === $job ) {
			return;
		}

		$this->jobs[ $job_key ] = $job->with(
			array(
				'state'            => JobState::SUCCEEDED,
				'lease_owner'      => '',
				'lease_expires_at' => 0,
				'updated_at'       => $now,
			)
		);
	}

	public function record_failure( string $job_key, string $worker_id, string $code, string $message, ?int $retry_at, int $now ): void {
		$job = $this->jobs[ $job_key ] ?? null;
		if ( null === $job ) {
			return;
		}

		$this->jobs[ $job_key ] = $job->with(
			array(
				'state'              => JobState::WAITING_RETRY,
				'lease_owner'        => '',
				'lease_expires_at'   => 0,
				'available_at'       => $retry_at ?? $now,
				'last_error_code'    => $code,
				'last_error_message' => $message,
				'updated_at'         => $now,
			)
		);
	}

	public function record_dead_letter( string $job_key, string $worker_id, string $code, string $message, int $now ): void {
		$job = $this->jobs[ $job_key ] ?? null;
		if ( null === $job ) {
			return;
		}

		$this->jobs[ $job_key ] = $job->with(
			array(
				'state'              => JobState::DEAD,
				'lease_owner'        => '',
				'lease_expires_at'   => 0,
				'last_error_code'    => $code,
				'last_error_message' => $message,
				'updated_at'         => $now,
			)
		);
	}

	public function cancel( string $job_key, string $reason, int $now ): void {
		$job = $this->jobs[ $job_key ] ?? null;
		if ( null === $job ) {
			return;
		}

		$this->jobs[ $job_key ] = $job->with(
			array(
				'state'              => JobState::CANCELLED,
				'lease_owner'        => '',
				'lease_expires_at'   => 0,
				'last_error_code'    => 'cancelled',
				'last_error_message' => $reason,
				'updated_at'         => $now,
			)
		);
	}

	public function record_attempt( JobAttempt $attempt ): void {
		$this->attempts[ $attempt->job_key ][] = $attempt;
	}

	public function attempts_for_job( string $job_key ): array {
		return $this->attempts[ $job_key ] ?? array();
	}

	public function reclaim_expired_leases( int $now ): int {
		$reclaimed = 0;
		foreach ( $this->jobs as $key => $job ) {
			if ( JobState::RUNNING === $job->state && $job->lease_expires_at <= $now ) {
				$this->jobs[ $key ] = $job->with(
					array(
						'state'            => JobState::WAITING_RETRY,
						'lease_owner'      => '',
						'lease_expires_at' => 0,
						'available_at'     => $now,
						'updated_at'       => $now,
					)
				);
				++$reclaimed;
			}
		}

		return $reclaimed;
	}

	public function jobs_for_batch( string $batch_key ): array {
		$result = array();
		foreach ( $this->jobs as $job ) {
			if ( $job->batch_key === $batch_key ) {
				$result[] = $job;
			}
		}

		return $result;
	}

	public function all_jobs(): array {
		return array_values( $this->jobs );
	}

	public function count_by_state(): array {
		$counts = array();
		foreach ( JobState::all() as $state ) {
			$counts[ $state ] = 0;
		}
		foreach ( $this->jobs as $job ) {
			if ( isset( $counts[ $job->state ] ) ) {
				++$counts[ $job->state ];
			}
		}

		return $counts;
	}

	public function count_published_today( string $target_site, int $day_start_timestamp, int $day_end_timestamp ): int {
		$count = 0;
		foreach ( $this->jobs as $job ) {
			if (
				JobStage::PUBLISH === $job->stage &&
				JobState::SUCCEEDED === $job->state &&
				$job->target_site === $target_site &&
				$job->updated_at >= $day_start_timestamp &&
				$job->updated_at <= $day_end_timestamp
			) {
				++$count;
			}
		}

		return $count;
	}

	public function count_generated_today( string $target_site, int $day_start_timestamp, int $day_end_timestamp ): int {
		$count = 0;
		foreach ( $this->jobs as $job ) {
			if (
				in_array( $job->stage, array( JobStage::VALIDATE, JobStage::RENDER_IMAGE, JobStage::SAVE_SEO, JobStage::DELIVER ), true ) &&
				JobState::SUCCEEDED === $job->state &&
				$job->target_site === $target_site &&
				$job->updated_at >= $day_start_timestamp &&
				$job->updated_at <= $day_end_timestamp
			) {
				++$count;
			}
		}

		return $count;
	}
}
