<?php
/**
 * Canonical import dry-run plan.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Application\Import;

/**
 * Immutable list of inserts, updates, and unchanged records.
 */
final class ImportPlan {

	/**
	 * @param list<ImportChange> $changes Planned entity changes.
	 */
	public function __construct( private readonly array $changes ) {
	}

	/**
	 * @return list<ImportChange>
	 */
	public function changes(): array {
		return $this->changes;
	}

	public function inserts(): int {
		return $this->count_action( ImportChange::INSERT );
	}

	public function updates(): int {
		return $this->count_action( ImportChange::UPDATE );
	}

	public function unchanged(): int {
		return $this->count_action( ImportChange::UNCHANGED );
	}

	public function writes(): int {
		return $this->inserts() + $this->updates();
	}

	public function action_for( string $entity_type, string $entity_key ): string {
		foreach ( $this->changes as $change ) {
			if ( $change->entity_type() === $entity_type && $change->entity_key() === $entity_key ) {
				return $change->action();
			}
		}

		return ImportChange::UNCHANGED;
	}

	private function count_action( string $action ): int {
		return count(
			array_filter(
				$this->changes,
				static fn ( ImportChange $change ): bool => $change->action() === $action
			)
		);
	}
}
