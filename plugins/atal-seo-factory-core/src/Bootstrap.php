<?php
/**
 * WordPress lifecycle bootstrap.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory;

use Atal\SeoFactory\Infrastructure\WordPress\ServiceFactory;
use Throwable;

/**
 * Delegates lifecycle hooks to small application services.
 */
final class Bootstrap {

	/**
	 * Run idempotent activation work.
	 */
	public static function activate(): void {
		try {
			ServiceFactory::activator()->activate();
		} catch ( Throwable $throwable ) {
			wp_die(
				esc_html(
					sprintf(
						'ATAL SEO Factory Core activation failed: %s: %s',
						$throwable::class,
						$throwable->getMessage()
					)
				)
			);
		}
	}

	/**
	 * Deactivate without removing persistent data.
	 */
	public static function deactivate(): void {
		ServiceFactory::deactivator()->deactivate();
	}

	/**
	 * Register lightweight runtime hooks.
	 */
	public static function boot(): void {
		ServiceFactory::plugin()->boot();
	}

	/**
	 * Static entry point only.
	 */
	private function __construct() {
	}
}
