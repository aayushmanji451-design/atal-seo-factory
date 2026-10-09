<?php
/**
 * WordPress option-backed queue control state and configuration storage.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Infrastructure\WordPress\Queue;

use Atal\Queue\Contract\QueueControlStoreInterface;
use Atal\Queue\Domain\QueueConfig;
use Atal\Queue\Domain\QueueControlState;
use Atal\SeoFactory\Config\Identifiers;

/**
 * Persists queue control state and configuration into WordPress options.
 */
final class WordPressQueueControlStore implements QueueControlStoreInterface {

	public function get_state(): string {
		$raw = get_option( Identifiers::OPTION_QUEUE_CONTROL_STATE, QueueControlState::RUNNING );

		return is_string( $raw ) && in_array( $raw, QueueControlState::all(), true )
			? $raw
			: QueueControlState::RUNNING;
	}

	public function set_state( string $state, string $reason = '' ): void {
		if ( ! in_array( $state, QueueControlState::all(), true ) ) {
			$state = QueueControlState::RUNNING;
		}
		update_option( Identifiers::OPTION_QUEUE_CONTROL_STATE, $state, false );
	}

	public function get_config(): QueueConfig {
		$raw = get_option( Identifiers::OPTION_QUEUE_CONFIG, null );
		if ( ! is_array( $raw ) ) {
			return new QueueConfig();
		}

		return QueueConfig::from_array( $raw );
	}

	public function set_config( QueueConfig $config ): void {
		update_option( Identifiers::OPTION_QUEUE_CONFIG, $config->to_array(), false );
	}
}
