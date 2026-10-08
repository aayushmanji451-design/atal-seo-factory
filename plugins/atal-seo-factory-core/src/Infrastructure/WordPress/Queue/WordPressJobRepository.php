<?php
/**
 * WordPress option-backed job repository.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Infrastructure\WordPress\Queue;

use Atal\Queue\Contract\JobRepositoryInterface;
use Atal\Queue\Domain\Job;
use Atal\Queue\Domain\JobAttempt;
use Atal\Queue\Domain\JobStage;
use Atal\Queue\Domain\JobState;
use Atal\SeoFactory\Config\Identifiers;

/**
 * Persists job records, leases, and attempt logs in WordPress options.
 */
final class WordPressJobRepository implements JobRepositoryInterface {

	public function enqueue( Job $job ): void {
		$jobs = $this->load_jobs_map();
		foreach ( $jobs as $existing ) {
			if ( $existing['idempotency_key'] === $job->idempotency_key ) {
				return;
			}
		}

		$jobs[ $job->job_key ] = $job->to_array();
		$this->save_jobs_map( $jobs );
	}

	public function find_by_key( string $job_key ): ?Job {
		$jobs = $this->load_jobs_map();
		$raw  = $jobs[ $job_key ] ?? null;
		if ( ! is_array( $raw ) ) {
			return null;
		}

		return Job::from_array( $raw );
	}

	public function find_by_idempotency( string $idempotency_key ): ?Job {
		$jobs = $this->load_jobs_map();
		foreach ( $jobs as $raw ) {
			if ( ( $raw['idempotency_key'] ?? '' ) === $idempotency_key ) {
				return Job::from_array( $raw );
			}
		}

		return null;
	}

	public function find_by_item_and_stage( string $item_key, string $stage ): ?Job {
		$jobs = $this->load_jobs_map();
		foreach ( $jobs as $raw ) {
			if ( ( $raw['item_key'] ?? '' ) === $item_key && ( $raw['stage'] ?? '' ) === $stage ) {
				return Job::from_array( $raw );
			}
		}

		return null;
	}

	public function claim_next( string $worker_id, int $lease_seconds, int $now, ?string $group = null ): ?Job {
		$jobs = $this->load_jobs_map();
		foreach ( $jobs as $key => $raw ) {
			$job = Job::from_array( $raw );
			if ( ! JobState::is_open( $job->state ) ) {
				continue;
			}
			if ( JobState::RUNNING === $job->state && $job->lease_expires_at > $now ) {
				continue;
			}
			if ( $job->available_at > $now ) {
				continue;
			}
			if ( null !== $group && JobStage::group( $job->stage ) !== $group ) {
				continue;
			}

			$claimed = $job->with(
				array(
					'state'            => JobState::RUNNING,
					'lease_owner'      => $worker_id,
					'lease_expires_at' => $now + $lease_seconds,
					'attempts'         => $job->attempts + 1,
					'updated_at'       => $now,
				)
			);

			$jobs[ $key ] = $claimed->to_array();
			$this->save_jobs_map( $jobs );

			return $claimed;
		}

		return null;
	}

	public function heartbeat( string $job_key, string $worker_id, int $new_lease_expires_at ): bool {
		$jobs = $this->load_jobs_map();
		$raw  = $jobs[ $job_key ] ?? null;
		if ( ! is_array( $raw ) ) {
			return false;
		}

		$job = Job::from_array( $raw );
		if ( $job->lease_owner !== $worker_id || JobState::RUNNING !== $job->state ) {
			return false;
		}

		$updated          = $job->with( array( 'lease_expires_at' => $new_lease_expires_at ) );
		$jobs[ $job_key ] = $updated->to_array();
		$this->save_jobs_map( $jobs );

		return true;
	}

	public function complete( string $job_key, string $worker_id, int $now ): void {
		$jobs = $this->load_jobs_map();
		$raw  = $jobs[ $job_key ] ?? null;
		if ( ! is_array( $raw ) ) {
			return;
		}

		$job = Job::from_array( $raw );
		if ( $job->lease_owner !== $worker_id && '' !== $job->lease_owner ) {
			return;
		}

		$updated          = $job->with(
			array(
				'state'            => JobState::SUCCEEDED,
				'lease_owner'      => '',
				'lease_expires_at' => 0,
				'completed_at'     => $now,
				'updated_at'       => $now,
			)
		);
		$jobs[ $job_key ] = $updated->to_array();
		$this->save_jobs_map( $jobs );
	}

	public function record_failure( string $job_key, string $worker_id, string $code, string $message, ?int $retry_at, int $now ): void {
		$jobs = $this->load_jobs_map();
		$raw  = $jobs[ $job_key ] ?? null;
		if ( ! is_array( $raw ) ) {
			return;
		}

		$job = Job::from_array( $raw );
		if ( $job->lease_owner !== $worker_id && '' !== $job->lease_owner ) {
			return;
		}

		$next_available = $retry_at ?? ( $now + 30 );
		$updated        = $job->with(
			array(
				'state'            => JobState::WAITING_RETRY,
				'lease_owner'      => '',
				'lease_expires_at' => 0,
				'available_at'     => $next_available,
				'last_error_code'  => $code,
				'last_error_msg'   => $message,
				'updated_at'       => $now,
			)
		);

		$jobs[ $job_key ] = $updated->to_array();
		$this->save_jobs_map( $jobs );
	}

	public function record_dead_letter( string $job_key, string $worker_id, string $code, string $message, int $now ): void {
		$jobs = $this->load_jobs_map();
		$raw  = $jobs[ $job_key ] ?? null;
		if ( ! is_array( $raw ) ) {
			return;
		}

		$job = Job::from_array( $raw );
		if ( $job->lease_owner !== $worker_id && '' !== $job->lease_owner ) {
			return;
		}

		$updated          = $job->with(
			array(
				'state'            => JobState::DEAD,
				'lease_owner'      => '',
				'lease_expires_at' => 0,
				'last_error_code'  => $code,
				'last_error_msg'   => $message,
				'completed_at'     => $now,
				'updated_at'       => $now,
			)
		);
		$jobs[ $job_key ] = $updated->to_array();
		$this->save_jobs_map( $jobs );
	}

	public function cancel( string $job_key, string $reason, int $now ): void {
		$jobs = $this->load_jobs_map();
		$raw  = $jobs[ $job_key ] ?? null;
		if ( ! is_array( $raw ) ) {
			return;
		}

		$job = Job::from_array( $raw );
		if ( JobState::is_terminal( $job->state ) ) {
			return;
		}

		$updated          = $job->with(
			array(
				'state'            => JobState::CANCELLED,
				'lease_owner'      => '',
				'lease_expires_at' => 0,
				'last_error_code'  => 'CANCELLED',
				'last_error_msg'   => $reason,
				'completed_at'     => $now,
				'updated_at'       => $now,
			)
		);
		$jobs[ $job_key ] = $updated->to_array();
		$this->save_jobs_map( $jobs );
	}

	public function record_attempt( JobAttempt $attempt ): void {
		$raw_attempts                      = $this->load_attempts_map();
		$list                              = $raw_attempts[ $attempt->job_key ] ?? array();
		$list[]                            = $attempt->to_array();
		$raw_attempts[ $attempt->job_key ] = $list;
		update_option( Identifiers::OPTION_QUEUE_JOBS . '_attempts', $raw_attempts, false );
	}

	/**
	 * @return list<JobAttempt>
	 */
	public function attempts_for_job( string $job_key ): array {
		$raw_attempts = $this->load_attempts_map();
		$list         = $raw_attempts[ $job_key ] ?? array();
		$results      = array();
		foreach ( $list as $raw ) {
			$results[] = JobAttempt::from_array( $raw );
		}

		return $results;
	}

	public function reclaim_expired_leases( int $now ): int {
		$jobs      = $this->load_jobs_map();
		$reclaimed = 0;

		foreach ( $jobs as $key => $raw ) {
			$job = Job::from_array( $raw );
			if ( JobState::RUNNING === $job->state && $job->lease_expires_at <= $now ) {
				$jobs[ $key ] = $job->with(
					array(
						'state'            => JobState::WAITING_RETRY,
						'lease_owner'      => '',
						'lease_expires_at' => 0,
						'available_at'     => $now,
						'updated_at'       => $now,
					)
				)->to_array();
				++$reclaimed;
			}
		}

		if ( $reclaimed > 0 ) {
			$this->save_jobs_map( $jobs );
		}

		return $reclaimed;
	}

	/**
	 * @return list<Job>
	 */
	public function jobs_for_batch( string $batch_key ): array {
		$jobs    = $this->load_jobs_map();
		$results = array();
		foreach ( $jobs as $raw ) {
			if ( ( $raw['batch_key'] ?? '' ) === $batch_key ) {
				$results[] = Job::from_array( $raw );
			}
		}

		return $results;
	}

	/**
	 * @return list<Job>
	 */
	public function all_jobs(): array {
		$jobs    = $this->load_jobs_map();
		$results = array();
		foreach ( $jobs as $raw ) {
			$results[] = Job::from_array( $raw );
		}

		return $results;
	}

	/**
	 * @return array<string,int>
	 */
	public function count_by_state(): array {
		$counts = array(
			JobState::PENDING       => 0,
			JobState::RUNNING       => 0,
			JobState::WAITING_RETRY => 0,
			JobState::SUCCEEDED     => 0,
			JobState::DEAD          => 0,
			JobState::CANCELLED     => 0,
		);

		foreach ( $this->load_jobs_map() as $raw ) {
			$raw_state = $raw['state'] ?? '';
			$state     = is_string( $raw_state ) ? $raw_state : '';
			if ( isset( $counts[ $state ] ) ) {
				++$counts[ $state ];
			}
		}

		return $counts;
	}

	public function count_published_today( string $target_site, int $day_start_timestamp, int $day_end_timestamp ): int {
		$count = 0;
		foreach ( $this->load_jobs_map() as $raw ) {
			$raw_completed = $raw['completed_at'] ?? 0;
			$completed_at  = is_int( $raw_completed ) || is_numeric( $raw_completed ) ? (int) $raw_completed : 0;
			if (
				( $raw['target_site'] ?? '' ) === $target_site
				&& ( $raw['stage'] ?? '' ) === JobStage::PUBLISH
				&& ( $raw['state'] ?? '' ) === JobState::SUCCEEDED
				&& $completed_at >= $day_start_timestamp
				&& $completed_at <= $day_end_timestamp
			) {
				++$count;
			}
		}

		return $count;
	}

	public function count_generated_today( string $target_site, int $day_start_timestamp, int $day_end_timestamp ): int {
		$count = 0;
		foreach ( $this->load_jobs_map() as $raw ) {
			$raw_completed = $raw['completed_at'] ?? 0;
			$completed_at  = is_int( $raw_completed ) || is_numeric( $raw_completed ) ? (int) $raw_completed : 0;
			if (
				( $raw['target_site'] ?? '' ) === $target_site
				&& ( $raw['stage'] ?? '' ) === JobStage::VALIDATE
				&& ( $raw['state'] ?? '' ) === JobState::SUCCEEDED
				&& $completed_at >= $day_start_timestamp
				&& $completed_at <= $day_end_timestamp
			) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private function load_jobs_map(): array {
		$raw = get_option( Identifiers::OPTION_QUEUE_JOBS, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$clean = array();
		foreach ( $raw as $key => $item ) {
			if ( is_string( $key ) && is_array( $item ) ) {
				/** @var array<string,mixed> $item */
				$clean[ $key ] = $item;
			}
		}

		return $clean;
	}

	/**
	 * @param array<string,array<string,mixed>> $jobs Job map.
	 */
	private function save_jobs_map( array $jobs ): void {
		update_option( Identifiers::OPTION_QUEUE_JOBS, $jobs, false );
	}

	/**
	 * @return array<string,list<array<string,mixed>>>
	 */
	private function load_attempts_map(): array {
		$raw = get_option( Identifiers::OPTION_QUEUE_JOBS . '_attempts', array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$clean = array();
		foreach ( $raw as $key => $list ) {
			if ( is_string( $key ) && is_array( $list ) ) {
				$sub = array();
				foreach ( $list as $entry ) {
					if ( is_array( $entry ) ) {
						/** @var array<string,mixed> $entry */
						$sub[] = $entry;
					}
				}
				$clean[ $key ] = $sub;
			}
		}

		return $clean;
	}
}
