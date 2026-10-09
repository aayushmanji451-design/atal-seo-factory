<?php
/**
 * Immutable attempt history entry.
 *
 * @package AtalQueue
 */

declare(strict_types=1);

namespace Atal\Queue\Domain;

use Atal\Queue\Support\Arr;

/**
 * Exact record of one execution attempt, retained for retry history.
 */
final class JobAttempt {

	public const SUCCESS       = 'success';
	public const RETRY         = 'retry';
	public const DEAD          = 'dead';
	public const DEFERRED      = 'deferred';
	public const LEASE_EXPIRED = 'lease_expired';
	public const FENCED        = 'fenced';

	/**
	 * Create an attempt record.
	 *
	 * @param string $job_key       Job identity.
	 * @param int    $attempt_no    Attempt number.
	 * @param string $stage         Stage.
	 * @param string $worker        Worker identity.
	 * @param int    $started_at    Start time.
	 * @param int    $finished_at   Finish time.
	 * @param string $outcome       Outcome constant.
	 * @param string $error_code    Failure code.
	 * @param string $error_message Redacted failure message.
	 * @param int    $next_run_at   Scheduled retry time, or zero.
	 */
	public function __construct(
		public readonly string $job_key,
		public readonly int $attempt_no,
		public readonly string $stage,
		public readonly string $worker,
		public readonly int $started_at,
		public readonly int $finished_at,
		public readonly string $outcome,
		public readonly string $error_code = '',
		public readonly string $error_message = '',
		public readonly int $next_run_at = 0
	) {
	}

	/**
	 * Hydrate from a persisted row.
	 *
	 * @param array<mixed,mixed> $row Row.
	 */
	public static function from_array( array $row ): self {
		return new self(
			Arr::string( $row, 'job_key' ),
			Arr::int( $row, 'attempt_no' ),
			Arr::string( $row, 'stage' ),
			Arr::string( $row, 'worker' ),
			Arr::int( $row, 'started_at' ),
			Arr::int( $row, 'finished_at' ),
			Arr::string( $row, 'outcome' ),
			Arr::string( $row, 'error_code' ),
			Arr::string( $row, 'error_message' ),
			Arr::int( $row, 'next_run_at' )
		);
	}

	/**
	 * Export for persistence.
	 *
	 * @return array<string,int|string>
	 */
	public function to_array(): array {
		return array(
			'job_key'       => $this->job_key,
			'attempt_no'    => $this->attempt_no,
			'stage'         => $this->stage,
			'worker'        => $this->worker,
			'started_at'    => $this->started_at,
			'finished_at'   => $this->finished_at,
			'outcome'       => $this->outcome,
			'error_code'    => $this->error_code,
			'error_message' => $this->error_message,
			'next_run_at'   => $this->next_run_at,
		);
	}
}
