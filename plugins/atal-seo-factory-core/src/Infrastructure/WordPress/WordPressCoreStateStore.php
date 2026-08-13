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
	}

	public function record_knowledge_import( string $fingerprint ): void {
		update_option( Identifiers::OPTION_KNOWLEDGE_FINGERPRINT, $fingerprint, false );
		update_option( Identifiers::OPTION_LAST_IMPORT_AT, gmdate( 'c' ), false );
	}

	public function knowledge_fingerprint(): ?string {
		$value = get_option( Identifiers::OPTION_KNOWLEDGE_FINGERPRINT, null );

		return is_string( $value ) && '' !== $value ? $value : null;
	}
}
