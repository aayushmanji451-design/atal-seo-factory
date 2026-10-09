<?php
/**
 * Configurable queue and daily automation settings.
 *
 * @package AtalQueue
 */

declare(strict_types=1);

namespace Atal\Queue\Domain;

use Atal\Queue\Support\Arr;
use InvalidArgumentException;

/**
 * Immutable configuration defining production targets, caps, and operation mode.
 */
final class QueueConfig {

	public const MODE_DAILY_APPROVAL = 'daily_approval';
	public const MODE_FULL_AUTO      = 'full_auto';

	public const DEFAULT_INSTITUTE_DAILY_LIMIT = 25;
	public const DEFAULT_DIPLOMA_DAILY_LIMIT   = 25;
	public const DEFAULT_GENERATION_LIMIT      = 50;
	public const DEFAULT_PUBLISHING_LIMIT      = 50;
	public const DEFAULT_STAGGER_INTERVAL_SEC  = 900;
	public const DEFAULT_MAX_ATTEMPTS          = 5;
	public const DEFAULT_LEASE_SECONDS         = 300;

	/**
	 * Create configuration.
	 *
	 * @param int    $institute_daily_limit    Daily posts for ATAL Institute.
	 * @param int    $diploma_daily_limit      Daily posts for Atal Diploma.
	 * @param int    $generation_daily_limit   Daily maximum generation units.
	 * @param int    $publishing_daily_limit   Daily maximum live published units.
	 * @param string $mode                     Operational mode: daily_approval or full_auto.
	 * @param int    $stagger_interval_seconds Delay between staggered publishing jobs.
	 * @param int    $max_job_attempts         Maximum retry attempts before dead-letter.
	 * @param int    $worker_lease_seconds     Worker claim lock expiration in seconds.
	 *
	 * @throws InvalidArgumentException When a setting is outside safe bounds.
	 */
	public function __construct(
		public readonly int $institute_daily_limit = self::DEFAULT_INSTITUTE_DAILY_LIMIT,
		public readonly int $diploma_daily_limit = self::DEFAULT_DIPLOMA_DAILY_LIMIT,
		public readonly int $generation_daily_limit = self::DEFAULT_GENERATION_LIMIT,
		public readonly int $publishing_daily_limit = self::DEFAULT_PUBLISHING_LIMIT,
		public readonly string $mode = self::MODE_DAILY_APPROVAL,
		public readonly int $stagger_interval_seconds = self::DEFAULT_STAGGER_INTERVAL_SEC,
		public readonly int $max_job_attempts = self::DEFAULT_MAX_ATTEMPTS,
		public readonly int $worker_lease_seconds = self::DEFAULT_LEASE_SECONDS
	) {
		if ( $this->institute_daily_limit < 1 || $this->institute_daily_limit > 500 ) {
			throw new InvalidArgumentException( 'Institute daily limit must be between 1 and 500.' );
		}
		if ( $this->diploma_daily_limit < 1 || $this->diploma_daily_limit > 500 ) {
			throw new InvalidArgumentException( 'Diploma daily limit must be between 1 and 500.' );
		}
		if ( $this->generation_daily_limit < 1 || $this->generation_daily_limit > 1000 ) {
			throw new InvalidArgumentException( 'Generation limit must be between 1 and 1000.' );
		}
		if ( $this->publishing_daily_limit < 1 || $this->publishing_daily_limit > 1000 ) {
			throw new InvalidArgumentException( 'Publishing limit must be between 1 and 1000.' );
		}
		if ( ! in_array( $this->mode, array( self::MODE_DAILY_APPROVAL, self::MODE_FULL_AUTO ), true ) ) {
			throw new InvalidArgumentException( 'Mode must be daily_approval or full_auto.' );
		}
		if ( $this->stagger_interval_seconds < 1 || $this->stagger_interval_seconds > 86400 ) {
			throw new InvalidArgumentException( 'Stagger interval must be between 1 second and 24 hours.' );
		}
		if ( $this->max_job_attempts < 1 || $this->max_job_attempts > 20 ) {
			throw new InvalidArgumentException( 'Max attempts must be between 1 and 20.' );
		}
		if ( $this->worker_lease_seconds < 10 || $this->worker_lease_seconds > 3600 ) {
			throw new InvalidArgumentException( 'Lease seconds must be between 10 and 3600.' );
		}
	}

	/**
	 * Total configured daily post target.
	 */
	public function total_daily_target(): int {
		return $this->institute_daily_limit + $this->diploma_daily_limit;
	}

	/**
	 * Daily target for the given target site.
	 *
	 * @param string $target_site Canonical target site.
	 */
	public function limit_for_site( string $target_site ): int {
		return 'atal_diploma' === $target_site ? $this->diploma_daily_limit : $this->institute_daily_limit;
	}

	/**
	 * Whether manual approval is required before queuing daily publications.
	 */
	public function requires_daily_approval(): bool {
		return self::MODE_DAILY_APPROVAL === $this->mode;
	}

	/**
	 * Hydrate from configuration array or option store.
	 *
	 * @param array<mixed,mixed> $data Raw settings.
	 */
	public static function from_array( array $data ): self {
		return new self(
			Arr::int( $data, 'institute_daily_limit', self::DEFAULT_INSTITUTE_DAILY_LIMIT ),
			Arr::int( $data, 'diploma_daily_limit', self::DEFAULT_DIPLOMA_DAILY_LIMIT ),
			Arr::int( $data, 'generation_daily_limit', self::DEFAULT_GENERATION_LIMIT ),
			Arr::int( $data, 'publishing_daily_limit', self::DEFAULT_PUBLISHING_LIMIT ),
			Arr::string( $data, 'mode', self::MODE_DAILY_APPROVAL ),
			Arr::int( $data, 'stagger_interval_seconds', self::DEFAULT_STAGGER_INTERVAL_SEC ),
			Arr::int( $data, 'max_job_attempts', self::DEFAULT_MAX_ATTEMPTS ),
			Arr::int( $data, 'worker_lease_seconds', self::DEFAULT_LEASE_SECONDS )
		);
	}

	/**
	 * Export configuration to array.
	 *
	 * @return array{institute_daily_limit:int,diploma_daily_limit:int,generation_daily_limit:int,publishing_daily_limit:int,mode:string,stagger_interval_seconds:int,max_job_attempts:int,worker_lease_seconds:int,total_daily_target:int}
	 */
	public function to_array(): array {
		return array(
			'institute_daily_limit'    => $this->institute_daily_limit,
			'diploma_daily_limit'      => $this->diploma_daily_limit,
			'generation_daily_limit'   => $this->generation_daily_limit,
			'publishing_daily_limit'   => $this->publishing_daily_limit,
			'mode'                     => $this->mode,
			'stagger_interval_seconds' => $this->stagger_interval_seconds,
			'max_job_attempts'         => $this->max_job_attempts,
			'worker_lease_seconds'     => $this->worker_lease_seconds,
			'total_daily_target'       => $this->total_daily_target(),
		);
	}
}
