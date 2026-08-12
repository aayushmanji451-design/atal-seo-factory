<?php
/**
 * Core option state contract.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Domain\Storage;

/**
 * Persists only new Core-owned version and import option values.
 */
interface CoreStateStoreInterface {

	public function database_version(): int;

	/**
	 * @param int $version Migration version.
	 */
	public function set_database_version( int $version ): void;

	/**
	 * @param string $version Plugin version.
	 */
	public function record_plugin_version( string $version ): void;

	/**
	 * @param string $fingerprint Deterministic package fingerprint.
	 */
	public function record_knowledge_import( string $fingerprint ): void;
}
