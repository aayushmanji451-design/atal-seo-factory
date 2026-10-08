<?php
/**
 * In-memory queue control state and configuration store.
 *
 * @package AtalQueue
 */

declare(strict_types=1);

namespace Atal\Queue\Infrastructure\InMemory;

use Atal\Queue\Contract\QueueControlStoreInterface;
use Atal\Queue\Domain\QueueConfig;
use Atal\Queue\Domain\QueueControlState;

/**
 * Transient in-memory store for pause/resume, emergency stop, and limits.
 */
final class InMemoryQueueControlStore implements QueueControlStoreInterface {

	/**
	 * Active queue control state.
	 *
	 * @var string
	 */
	private string $state;

	/**
	 * Current queue configuration.
	 *
	 * @var QueueConfig
	 */
	private QueueConfig $config;

	/**
	 * Create store.
	 *
	 * @param string           $initial_state Initial state.
	 * @param QueueConfig|null $config        Initial config.
	 */
	public function __construct(
		string $initial_state = QueueControlState::RUNNING,
		?QueueConfig $config = null
	) {
		$this->state  = $initial_state;
		$this->config = $config ?? new QueueConfig();
	}

	public function get_state(): string {
		return $this->state;
	}

	public function set_state( string $state, string $reason = '' ): void {
		$this->state = $state;
	}

	public function get_config(): QueueConfig {
		return $this->config;
	}

	public function set_config( QueueConfig $config ): void {
		$this->config = $config;
	}
}
