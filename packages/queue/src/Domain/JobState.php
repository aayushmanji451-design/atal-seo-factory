<?php
/**
 * Job lifecycle states.
 *
 * @package AtalQueue
 */

declare(strict_types=1);

namespace Atal\Queue\Domain;

/**
 * Closed set of persisted job states.
 */
final class JobState {

	public const PENDING       = 'pending';
	public const RUNNING       = 'running';
	public const WAITING_RETRY = 'waiting_retry';
	public const SUCCEEDED     = 'succeeded';
	public const DEAD          = 'dead';
	public const CANCELLED     = 'cancelled';

	/**
	 * Every valid state.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array( self::PENDING, self::RUNNING, self::WAITING_RETRY, self::SUCCEEDED, self::DEAD, self::CANCELLED );
	}

	/**
	 * States a worker may claim.
	 *
	 * @return list<string>
	 */
	public static function claimable(): array {
		return array( self::PENDING, self::WAITING_RETRY );
	}

	/**
	 * Whether a job can still make progress.
	 *
	 * @param string $state Job state.
	 */
	public static function is_open( string $state ): bool {
		return in_array( $state, array( self::PENDING, self::RUNNING, self::WAITING_RETRY ), true );
	}

	/**
	 * Whether a job is in an immutable finished state.
	 *
	 * @param string $state Job state.
	 */
	public static function is_terminal( string $state ): bool {
		return in_array( $state, array( self::SUCCEEDED, self::DEAD, self::CANCELLED ), true );
	}

	/**
	 * Static-only class.
	 */
	private function __construct() {
	}
}
