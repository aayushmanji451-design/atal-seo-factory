<?php
/**
 * WordPress rotation cursor storage.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Infrastructure\WordPress\Topics;

use Atal\SeoFactory\Config\Identifiers;
use Atal\Topics\Contract\RotationStateStoreInterface;

/**
 * Persists independent site cursors in new Core-owned options.
 */
final class WordPressRotationStateStore implements RotationStateStoreInterface {

	public function cursor( string $target_site ): int {
		$value = get_option( $this->option_key( $target_site ), 0 );

		return is_numeric( $value ) ? max( 0, (int) $value ) : 0;
	}

	public function set_cursor( string $target_site, int $cursor ): void {
		update_option( $this->option_key( $target_site ), max( 0, $cursor ), false );
	}

	private function option_key( string $target_site ): string {
		return 'atal_diploma' === $target_site ? Identifiers::OPTION_TOPIC_CURSOR_DIPLOMA : Identifiers::OPTION_TOPIC_CURSOR_INSTITUTE;
	}
}
