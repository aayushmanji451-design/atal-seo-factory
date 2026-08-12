<?php
/**
 * WordPress lifecycle bootstrap.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory;

use Atal\SeoFactory\Infrastructure\WordPress\ServiceFactory;

/**
 * Delegates lifecycle hooks to small application services.
 */
final class Bootstrap {

	/**
	 * Run idempotent activation work.
	 */
	public static function activate(): void {
		ServiceFactory::activator()->activate();
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
