<?php
/**
 * Canonical import dry-run change.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Application\Import;

/**
 * Immutable entity-level change reported before writes.
 */
final class ImportChange {

	public const INSERT = 'insert';

	public const UPDATE = 'update';

	public const UNCHANGED = 'unchanged';

	public function __construct(
		private readonly string $entity_type,
		private readonly string $entity_key,
		private readonly string $action
	) {
	}

	public function entity_type(): string {
		return $this->entity_type;
	}

	public function entity_key(): string {
		return $this->entity_key;
	}

	public function action(): string {
		return $this->action;
	}
}
