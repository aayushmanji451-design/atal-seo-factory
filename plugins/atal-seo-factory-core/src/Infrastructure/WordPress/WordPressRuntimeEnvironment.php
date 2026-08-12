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

	public function php_memory_limit(): string {
		return ini_get( 'memory_limit' );
	}
}
