<?php
/**
 * Topic registry persistence.
 *
 * @package AtalTopics
 */

declare(strict_types=1);

namespace Atal\Topics\Contract;

use Atal\Topics\Domain\PublishedTopic;

interface TopicRegistryInterface {

	/**
	 * Return every registry record.
	 *
	 * @return list<PublishedTopic>
	 */
	public function all(): array;

	/**
	 * Insert or update one deterministic record.
	 *
	 * @param PublishedTopic $topic Topic snapshot.
	 */
	public function save( PublishedTopic $topic ): void;

	/** Begin a registry transaction. */
	public function begin(): void;

	/** Commit a registry transaction. */
	public function commit(): void;

	/** Roll back a registry transaction. */
	public function rollback(): void;
}
