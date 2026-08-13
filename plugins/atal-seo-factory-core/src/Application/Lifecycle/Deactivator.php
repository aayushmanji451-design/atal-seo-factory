<?php
/**
 * Non-destructive plugin deactivation.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Application\Lifecycle;

/**
 * Intentionally preserves every table, option, and imported record.
 */
final class Deactivator {

	/**
	 * Deactivate without deleting or rolling back persistent data.
	 */
	public function deactivate(): void {
		// Runtime hooks stop naturally when WordPress deactivates the plugin.
	}
}
