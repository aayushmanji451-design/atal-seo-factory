<?php
/**
 * Read-only WordPress runtime environment.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Infrastructure\WordPress;

use Atal\SeoFactory\Application\Health\RuntimeEnvironmentInterface;

/**
 * Reads environment values without changing wp-config.php or site settings.
 */
final class WordPressRuntimeEnvironment implements RuntimeEnvironmentInterface {

	public function site_url(): string {
		return site_url( '/' );
	}

	public function environment_type(): string {
		return wp_get_environment_type();
	}

	public function wordpress_version(): string {
		return get_bloginfo( 'version' );
	}

	public function php_version(): string {
		return PHP_VERSION;
	}

	public function wordpress_memory_limit(): string {
		$value = defined( 'WP_MEMORY_LIMIT' ) ? constant( 'WP_MEMORY_LIMIT' ) : '';

		return is_string( $value ) ? $value : '';
	}

	public function wordpress_max_memory_limit(): string {
		$value = defined( 'WP_MAX_MEMORY_LIMIT' ) ? constant( 'WP_MAX_MEMORY_LIMIT' ) : '';

		return is_string( $value ) ? $value : '';
	}

	public function php_memory_limit(): string {
		return ini_get( 'memory_limit' );
	}

	public function current_memory_usage(): int {
		return memory_get_usage( true );
	}

	public function peak_memory_usage(): int {
		return memory_get_peak_usage( true );
	}

	public function wordpress_admin_can_raise_memory(): bool {
		return function_exists( 'wp_raise_memory_limit' )
			&& $this->memory_bytes( $this->wordpress_max_memory_limit() ) > $this->memory_bytes( $this->wordpress_memory_limit() );
	}

	public function raise_wordpress_admin_memory(): bool {
		if ( ! function_exists( 'wp_raise_memory_limit' ) ) {
			return false;
		}

		return false !== wp_raise_memory_limit( 'admin' );
	}

	private function memory_bytes( string $value ): int {
		$normalized = strtoupper( trim( $value ) );
		if ( '-1' === $normalized ) {
			return PHP_INT_MAX;
		}

		if ( 1 !== preg_match( '/^(\d+)([KMGT]?)B?$/', $normalized, $matches ) ) {
			return 0;
		}

		$amount = (int) $matches[1];
		$power  = array_search( $matches[2], array( '', 'K', 'M', 'G', 'T' ), true );

		return false === $power ? 0 : $amount * ( 1024 ** $power );
	}
}
