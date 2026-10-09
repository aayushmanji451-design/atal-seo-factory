<?php
/**
 * Persisted queue job.
 *
 * @package AtalQueue
 */

declare(strict_types=1);

namespace Atal\Queue\Domain;

use Atal\Queue\Support\Arr;

/**
 * Immutable snapshot of one job row.
 */
final class Job {

	/**
	 * Create a job snapshot.
	 *
	 * @param string              $job_key            Deterministic job identity.
	 * @param string              $batch_key          Owning daily batch.
	 * @param string              $item_key           Owning article.
	 * @param string              $stage              Pipeline stage.
	 * @param string              $target_site        Canonical target site.
	 * @param string              $state              Job state.
	 * @param int                 $attempts           Attempts started so far.
	 * @param int                 $max_attempts       Attempt ceiling.
	 * @param int                 $available_at       Earliest run time (UTC epoch).
	 * @param string              $lease_owner        Worker holding the lease.
	 * @param int                 $lease_expires_at   Lease expiry (UTC epoch).
	 * @param string              $idempotency_key    Unique logical identity.
	 * @param string              $last_error_code    Last failure code.
	 * @param string              $last_error_message Last redacted failure message.
	 * @param int                 $created_at         Creation time.
	 * @param int                 $updated_at         Last update time.
	 * @param array<string,mixed> $payload            Optional stage context payload.
	 */
	public function __construct(
		public readonly string $job_key,
		public readonly string $batch_key,
		public readonly string $item_key,
		public readonly string $stage,
		public readonly string $target_site,
		public readonly string $state,
		public readonly int $attempts,
		public readonly int $max_attempts,
		public readonly int $available_at,
		public readonly string $lease_owner,
		public readonly int $lease_expires_at,
		public readonly string $idempotency_key,
		public readonly string $last_error_code,
		public readonly string $last_error_message,
		public readonly int $created_at,
		public readonly int $updated_at,
		public readonly array $payload = array()
	) {
	}

	/**
	 * Create a new pending job for one article stage.
	 *
	 * @param string              $item_key     Article identity.
	 * @param string              $batch_key    Daily batch identity.
	 * @param string              $stage        Pipeline stage.
	 * @param string              $target_site  Canonical target site.
	 * @param int                 $available_at Earliest run time.
	 * @param int                 $max_attempts Attempt ceiling.
	 * @param int                 $now          Current time.
	 * @param int                 $generation   Re-queue generation for manual retries.
	 * @param array<string,mixed> $payload      Optional stage context payload.
	 */
	public static function create(
		string $item_key,
		string $batch_key,
		string $stage,
		string $target_site,
		int $available_at,
		int $max_attempts,
		int $now,
		int $generation = 0,
		array $payload = array()
	): self {
		$idempotency = $item_key . ':' . $stage . ':' . $generation;

		return new self(
			'job_' . substr( hash( 'sha256', $idempotency ), 0, 40 ),
			$batch_key,
			$item_key,
			$stage,
			$target_site,
			JobState::PENDING,
			0,
			$max_attempts,
			$available_at,
			'',
			0,
			$idempotency,
			'',
			'',
			$now,
			$now,
			$payload
		);
	}

	/**
	 * Hydrate from a persisted row.
	 *
	 * @param array<mixed,mixed> $row Row.
	 */
	public static function from_array( array $row ): self {
		return new self(
			Arr::string( $row, 'job_key' ),
			Arr::string( $row, 'batch_key' ),
			Arr::string( $row, 'item_key' ),
			Arr::string( $row, 'stage' ),
			Arr::string( $row, 'target_site' ),
			Arr::string( $row, 'state', JobState::PENDING ),
			Arr::int( $row, 'attempts' ),
			Arr::int( $row, 'max_attempts', 5 ),
			Arr::int( $row, 'available_at' ),
			Arr::string( $row, 'lease_owner' ),
			Arr::int( $row, 'lease_expires_at' ),
			Arr::string( $row, 'idempotency_key' ),
			Arr::string( $row, 'last_error_code' ),
			Arr::string( $row, 'last_error_message' ),
			Arr::int( $row, 'created_at' ),
			Arr::int( $row, 'updated_at' ),
			Arr::map( $row, 'payload' )
		);
	}

	/**
	 * Export for persistence and diagnostics.
	 *
	 * @return array<string,int|string|array<string,mixed>>
	 */
	public function to_array(): array {
		return array(
			'job_key'            => $this->job_key,
			'batch_key'          => $this->batch_key,
			'item_key'           => $this->item_key,
			'stage'              => $this->stage,
			'target_site'        => $this->target_site,
			'state'              => $this->state,
			'attempts'           => $this->attempts,
			'max_attempts'       => $this->max_attempts,
			'available_at'       => $this->available_at,
			'lease_owner'        => $this->lease_owner,
			'lease_expires_at'   => $this->lease_expires_at,
			'idempotency_key'    => $this->idempotency_key,
			'last_error_code'    => $this->last_error_code,
			'last_error_message' => $this->last_error_message,
			'created_at'         => $this->created_at,
			'updated_at'         => $this->updated_at,
			'payload'            => $this->payload,
		);
	}

	/**
	 * Return a modified copy.
	 *
	 * @param array<string,mixed> $changes Column overrides.
	 */
	public function with( array $changes ): self {
		return self::from_array( array_merge( $this->to_array(), $changes ) );
	}
}
