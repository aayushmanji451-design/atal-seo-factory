<?php
/**
 * Registry persistence result.
 *
 * @package AtalTopics
 */

declare(strict_types=1);

namespace Atal\Topics\Domain;

/**
 * Reports a deterministic insert, update, or no-op.
 */
final class RegistryResult {

	public const INSERTED = 'INSERTED';

	public const UPDATED = 'UPDATED';

	public const UNCHANGED = 'UNCHANGED';

	/**
	 * Create a persistence result.
	 *
	 * @param string $status    Persistence status.
	 * @param string $topic_key Deterministic topic key.
	 */
	public function __construct( private readonly string $status, private readonly string $topic_key ) {
	}

	/** Return the persistence status. */
	public function status(): string {
		return $this->status;
	}

	/** Return the deterministic topic key. */
	public function topic_key(): string {
		return $this->topic_key;
	}
}
