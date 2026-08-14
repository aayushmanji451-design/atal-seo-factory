<?php
/**
 * In-memory topic registry.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Support\Topics;

use Atal\Topics\Contract\TopicRegistryInterface;
use Atal\Topics\Domain\PublishedTopic;
use RuntimeException;

/**
 * Records transactions and deterministic upserts for isolated tests.
 */
final class InMemoryTopicRegistry implements TopicRegistryInterface {

	/** @var array<string,PublishedTopic> */
	private array $topics = array();

	/** @var list<string> */
	public array $transactions = array();

	public bool $fail_save = false;

	/** {@inheritDoc} */
	public function all(): array {
		return array_values( $this->topics );
	}

	/** {@inheritDoc} */
	public function save( PublishedTopic $topic ): void {
		if ( $this->fail_save ) {
			throw new RuntimeException( 'Synthetic registry failure.' );
		}
		$this->topics[ $topic->proposal()->identity()->key() ] = $topic;
	}

	/** {@inheritDoc} */
	public function begin(): void {
		$this->transactions[] = 'BEGIN';
	}

	/** {@inheritDoc} */
	public function commit(): void {
		$this->transactions[] = 'COMMIT';
	}

	/** {@inheritDoc} */
	public function rollback(): void {
		$this->transactions[] = 'ROLLBACK';
	}
}
