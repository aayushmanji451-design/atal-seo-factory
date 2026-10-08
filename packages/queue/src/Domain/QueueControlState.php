<?php
/**
 * Queue execution control states.
 *
 * @package AtalQueue
 */

declare(strict_types=1);

namespace Atal\Queue\Domain;

/**
 * Global queue control switches.
 */
final class QueueControlState {

	public const RUNNING        = 'running';
	public const PAUSED         = 'paused';
	public const EMERGENCY_STOP = 'emergency_stop';

	/**
	 * All valid states.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array( self::RUNNING, self::PAUSED, self::EMERGENCY_STOP );
	}

	/**
	 * Check if workers are permitted to process jobs.
	 *
	 * @param string $state Current state.
	 */
	public static function can_execute( string $state ): bool {
		return self::RUNNING === $state;
	}

	/**
	 * Static-only class.
	 */
	private function __construct() {
	}
}
