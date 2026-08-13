<?php
/**
 * In-memory Core state test double.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Support;

use Atal\SeoFactory\Domain\Storage\CoreStateStoreInterface;

/**
 * Captures migration, activation, and import state.
 */
final class InMemoryCoreStateStore implements CoreStateStoreInterface {

	private int $database_version = 0;

	private ?string $plugin_version = null;

	private ?string $knowledge_fingerprint = null;

	private int $activation_sequence = 0;

	private ?int $import_activation_sequence = null;

	public function database_version(): int {
		return $this->database_version;
	}

	public function set_database_version( int $version ): void {
		$this->database_version = $version;
	}

	public function record_plugin_version( string $version ): void {
		$this->plugin_version = $version;
		++$this->activation_sequence;
	}

	public function record_knowledge_import( string $fingerprint ): void {
		$this->knowledge_fingerprint      = $fingerprint;
		$this->import_activation_sequence = $this->activation_sequence;
	}

	public function plugin_version(): ?string {
		return $this->plugin_version;
	}

	public function knowledge_fingerprint(): ?string {
		return $this->knowledge_fingerprint;
	}

	public function post_reactivation_persistence_verified(): bool {
		return null !== $this->import_activation_sequence && $this->activation_sequence > $this->import_activation_sequence;
	}

	public function ensure_reactivation_baseline(): void {
		if ( null !== $this->knowledge_fingerprint && null === $this->import_activation_sequence ) {
			$this->import_activation_sequence = $this->activation_sequence;
		}
	}

	public function seed_legacy_knowledge_fingerprint( string $fingerprint ): void {
		$this->knowledge_fingerprint      = $fingerprint;
		$this->import_activation_sequence = null;
	}
}
