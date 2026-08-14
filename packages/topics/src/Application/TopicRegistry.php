<?php
/**
 * Safe deterministic registry writer.
 *
 * @package AtalTopics
 */

declare(strict_types=1);

namespace Atal\Topics\Application;

use Atal\Topics\Contract\TopicRegistryInterface;
use Atal\Topics\Domain\PublishedTopic;
use Atal\Topics\Domain\QualityState;
use Atal\Topics\Domain\RegistryResult;
use Atal\Topics\Domain\ValidationReport;
use RuntimeException;
use Throwable;

/**
 * Persists only PASS topics and rolls back every failed write.
 */
final class TopicRegistry {

	/**
	 * Create the safe writer.
	 *
	 * @param TopicRegistryInterface $repository Registry persistence.
	 */
	public function __construct( private readonly TopicRegistryInterface $repository ) {
	}

	/**
	 * Register a validated topic transactionally.
	 *
	 * @param PublishedTopic   $topic  Topic snapshot.
	 * @param ValidationReport $report Validation evidence.
	 *
	 * @throws RuntimeException When the topic has not passed validation.
	 * @throws Throwable When persistence fails.
	 */
	public function register( PublishedTopic $topic, ValidationReport $report ): RegistryResult {
		if ( QualityState::PASS !== $report->state() ) {
			throw new RuntimeException( 'Only PASS topics may enter the canonical registry.' );
		}

		$existing = null;
		foreach ( $this->repository->all() as $record ) {
			if ( $record->proposal()->identity()->key() === $topic->proposal()->identity()->key() ) {
				$existing = $record;
				break;
			}
		}
		if ( null !== $existing && $existing->source_hash() === $topic->source_hash() ) {
			return new RegistryResult( RegistryResult::UNCHANGED, $topic->proposal()->identity()->key() );
		}

		$this->repository->begin();
		try {
			$this->repository->save( $topic );
			$this->repository->commit();
		} catch ( Throwable $throwable ) {
			$this->repository->rollback();
			throw $throwable;
		}

		return new RegistryResult( null === $existing ? RegistryResult::INSERTED : RegistryResult::UPDATED, $topic->proposal()->identity()->key() );
	}
}
