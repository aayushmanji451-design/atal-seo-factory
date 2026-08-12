<?php
/**
 * Safe plugin activation.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Application\Lifecycle;

use Atal\SeoFactory\Application\Migration\MigrationRunner;
use Atal\SeoFactory\Domain\Storage\CoreStateStoreInterface;

/**
 * Applies pending migrations and records the development plugin version.
 */
final class Activator {

	/**
	 * Create the activator.
	 *
	 * @param MigrationRunner         $migration_runner Migration runner.
	 * @param CoreStateStoreInterface $state_store      Core state store.
	 * @param string                  $plugin_version   Current plugin version.
	 */
	public function __construct(
		private readonly MigrationRunner $migration_runner,
		private readonly CoreStateStoreInterface $state_store,
		private readonly string $plugin_version
	) {
	}

	/**
	 * Perform idempotent activation.
	 */
	public function activate(): void {
		$this->migration_runner->migrate_to_latest();
		$this->state_store->record_plugin_version( $this->plugin_version );
	}
}
