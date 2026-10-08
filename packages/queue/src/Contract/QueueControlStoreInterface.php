<?php
/**
 * Contract for persisting global queue control state and configuration.
 *
 * @package AtalQueue
 */

declare(strict_types=1);

namespace Atal\Queue\Contract;

use Atal\Queue\Domain\QueueConfig;

/**
 * Accesses and mutates pause/emergency switches and daily targets.
 */
interface QueueControlStoreInterface {

	/**
	 * Get current global queue control state (running, paused, emergency_stop).
	 */
	public function get_state(): string;

	/**
	 * Set global queue control state with an optional audit reason.
	 *
	 * @param string $state  State from QueueControlState constants.
	 * @param string $reason Human-readable reason.
	 */
	public function set_state( string $state, string $reason = '' ): void;

	/**
	 * Get current queue configuration.
	 */
	public function get_config(): QueueConfig;

	/**
	 * Update queue configuration.
	 *
	 * @param QueueConfig $config New configuration.
	 */
	public function set_config( QueueConfig $config ): void;
}
