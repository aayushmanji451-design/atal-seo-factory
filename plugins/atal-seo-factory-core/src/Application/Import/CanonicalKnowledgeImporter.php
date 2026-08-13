<?php
/**
 * Transactional canonical knowledge importer.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Application\Import;

use Atal\Contracts\Data\KnowledgePackage;
use Atal\Contracts\Validation\KnowledgeValidator;
use Atal\SeoFactory\Domain\Knowledge\RecordSet;
use Atal\SeoFactory\Domain\Storage\CoreStateStoreInterface;
use Atal\SeoFactory\Domain\Storage\KnowledgeRepositoryInterface;
use Atal\SeoFactory\Domain\Storage\TransactionManagerInterface;
use Throwable;

/**
 * Uses Task 01 validation, exposes dry-run, and commits all writes atomically.
 */
final class CanonicalKnowledgeImporter {

	public function __construct(
		private readonly KnowledgeValidator $validator,
		private readonly KnowledgeRecordFactory $record_factory,
		private readonly KnowledgeRepositoryInterface $repository,
		private readonly TransactionManagerInterface $transactions,
		private readonly CoreStateStoreInterface $state_store
	) {
	}

	/**
	 * Validate and report every change without writing.
	 *
	 * @throws InvalidKnowledgeException When Task 01 validation fails.
	 */
	public function dry_run( KnowledgePackage $package, string $schema_directory ): ImportPlan {
		return $this->prepare( $package, $schema_directory )['plan'];
	}

	/**
	 * Recompute the dry-run plan and apply only its writes in one transaction.
	 *
	 * @throws InvalidKnowledgeException When Task 01 validation fails.
	 * @throws Throwable When persistence fails after validation.
	 */
	public function import( KnowledgePackage $package, string $schema_directory ): ImportResult {
		$prepared = $this->prepare( $package, $schema_directory );
		$records  = $prepared['records'];
		$plan     = $prepared['plan'];
		$writes   = 0;
		$step     = 'begin_transaction';
		if ( 0 === $plan->writes() ) {
			return new ImportResult( $plan, 0 );
		}

		$this->transactions->begin();
		try {
			foreach ( $records->courses() as $course ) {
				if ( ImportChange::UNCHANGED === $plan->action_for( 'course', $course->course_key() ) ) {
					continue;
				}
				$step = 'upsert_course:' . $course->course_key();
				$this->repository->upsert_course( $course );
				++$writes;
			}

			foreach ( $records->topics() as $topic ) {
				if ( ImportChange::UNCHANGED === $plan->action_for( 'topic', $topic->topic_key() ) ) {
					continue;
				}
				$step = 'upsert_topic:' . $topic->topic_key();
				$this->repository->upsert_topic( $topic );
				++$writes;
			}

			$step = 'record_knowledge_fingerprint';
			$this->state_store->record_knowledge_import( $records->fingerprint() );
			$step = 'commit_transaction';
			$this->transactions->commit();
		} catch ( Throwable $throwable ) {
			$this->transactions->rollback();
			throw new ImportPersistenceException( $step, $throwable );
		}

		return new ImportResult( $plan, $writes );
	}

	/**
	 * @return array{records:RecordSet,plan:ImportPlan}
	 * @throws InvalidKnowledgeException When Task 01 validation fails.
	 */
	private function prepare( KnowledgePackage $package, string $schema_directory ): array {
		$report = $this->validator->validate( $package, $schema_directory );
		if ( ! $report->is_valid() ) {
			throw new InvalidKnowledgeException( $report );
		}

		$records = $this->record_factory->create( $package );
		$changes = array();

		foreach ( $records->courses() as $course ) {
			$changes[] = new ImportChange( 'course', $course->course_key(), $this->action( $this->repository->course_hash( $course->course_key() ), $course->source_hash() ) );
		}
		foreach ( $records->topics() as $topic ) {
			$changes[] = new ImportChange( 'topic', $topic->topic_key(), $this->action( $this->repository->topic_hash( $topic->topic_key() ), $topic->source_hash() ) );
		}

		return array(
			'records' => $records,
			'plan'    => new ImportPlan( $changes ),
		);
	}

	private function action( ?string $stored_hash, string $source_hash ): string {
		if ( null === $stored_hash ) {
			return ImportChange::INSERT;
		}

		return $stored_hash === $source_hash ? ImportChange::UNCHANGED : ImportChange::UPDATE;
	}
}
