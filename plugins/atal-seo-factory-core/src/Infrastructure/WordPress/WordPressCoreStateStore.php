<?php
/**
 * WordPress option-backed Core state.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Infrastructure\WordPress;

use Atal\SeoFactory\Config\Identifiers;
use Atal\SeoFactory\Domain\Storage\CoreStateStoreInterface;

/**
 * Uses only the new Core option keys.
 */
final class WordPressCoreStateStore implements CoreStateStoreInterface {

	public function database_version(): int {
		$value = get_option( Identifiers::OPTION_DATABASE_VERSION, 0 );

		return is_numeric( $value ) ? (int) $value : 0;
	}

	public function set_database_version( int $version ): void {
		update_option( Identifiers::OPTION_DATABASE_VERSION, $version, false );
	}

	public function record_plugin_version( string $version ): void {
		update_option( Identifiers::OPTION_PLUGIN_VERSION, $version, false );
		$sequence = get_option( Identifiers::OPTION_ACTIVATION_SEQUENCE, 0 );
		update_option( Identifiers::OPTION_ACTIVATION_SEQUENCE, ( is_numeric( $sequence ) ? (int) $sequence : 0 ) + 1, false );
	}

	public function record_knowledge_import( string $fingerprint ): void {
		update_option( Identifiers::OPTION_KNOWLEDGE_FINGERPRINT, $fingerprint, false );
		update_option( Identifiers::OPTION_LAST_IMPORT_AT, gmdate( 'c' ), false );
		update_option( Identifiers::OPTION_IMPORT_ACTIVATION_SEQUENCE, $this->activation_sequence(), false );
	}

	public function knowledge_fingerprint(): ?string {
		$value = get_option( Identifiers::OPTION_KNOWLEDGE_FINGERPRINT, null );

		return is_string( $value ) && '' !== $value ? $value : null;
	}

	public function post_reactivation_persistence_verified(): bool {
		$import_sequence = get_option( Identifiers::OPTION_IMPORT_ACTIVATION_SEQUENCE, null );

		return is_numeric( $import_sequence ) && $this->activation_sequence() > (int) $import_sequence;
	}

	public function ensure_reactivation_baseline(): void {
		$import_sequence = get_option( Identifiers::OPTION_IMPORT_ACTIVATION_SEQUENCE, null );
		if ( null !== $this->knowledge_fingerprint() && ! is_numeric( $import_sequence ) ) {
			update_option( Identifiers::OPTION_IMPORT_ACTIVATION_SEQUENCE, $this->activation_sequence(), false );
		}
	}

	private function activation_sequence(): int {
		$value = get_option( Identifiers::OPTION_ACTIVATION_SEQUENCE, 0 );

		return is_numeric( $value ) ? (int) $value : 0;
	}
}
