<?php
/**
 * Capped exponential retry policy.
 *
 * @package AtalQueue
 */

declare(strict_types=1);

namespace Atal\Queue\Domain;

use InvalidArgumentException;

/**
 * Deterministic backoff so retry history is reproducible and testable.
 */
final class RetryPolicy {

	/**
	 * Create the policy.
	 *
	 * @param int $max_attempts  Total attempts allowed, including the first.
	 * @param int $base_seconds  Delay after the first failure.
	 * @param int $cap_seconds   Maximum delay.
	 *
	 * @throws InvalidArgumentException When a value is out of range.
	 */
	public function __construct(
		public readonly int $max_attempts = 5,
		public readonly int $base_seconds = 60,
		public readonly int $cap_seconds = 1800
	) {
		if ( $max_attempts < 1 || $max_attempts > 20 || $base_seconds < 1 || $cap_seconds < $base_seconds ) {
			throw new InvalidArgumentException( 'Invalid retry policy.' );
		}
	}

	/**
	 * Delay before the next attempt, after the given number of failed attempts.
	 *
	 * @param int    $failed_attempts Attempts already made (at least 1).
	 * @param string $job_key         Job identity used for deterministic jitter.
	 */
	public function delay_for( int $failed_attempts, string $job_key ): int {
		$exponent = max( 0, min( 20, $failed_attempts - 1 ) );
		$delay    = min( $this->cap_seconds, $this->base_seconds * ( 2 ** $exponent ) );
		$jitter   = (int) floor( $delay * 0.10 * ( ( crc32( $job_key . ':' . $failed_attempts ) % 1000 ) / 1000 ) );

		return min( $this->cap_seconds, $delay + $jitter );
	}

	/**
	 * Whether another attempt is permitted.
	 *
	 * @param int $attempts_made Attempts already started.
	 */
	public function exhausted( int $attempts_made ): bool {
		return $attempts_made >= $this->max_attempts;
	}
}
