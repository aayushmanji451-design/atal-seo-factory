<?php
/**
 * Canonical knowledge importer tests.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Unit\Core;

use Atal\Contracts\Data\KnowledgePackage;
use Atal\Contracts\Validation\KnowledgeValidator;
use Atal\SeoFactory\Application\Import\CanonicalKnowledgeImporter;
use Atal\SeoFactory\Application\Import\InvalidKnowledgeException;
use Atal\SeoFactory\Application\Import\ImportPersistenceException;
use Atal\SeoFactory\Application\Import\KnowledgeRecordFactory;
use Atal\SeoFactory\Domain\Knowledge\CourseRecord;
use Atal\Tests\Fixtures\KnowledgePackageFixture;
use Atal\Tests\Support\InMemoryCoreStateStore;
use Atal\Tests\Support\InMemoryKnowledgeRepository;
use Atal\Tests\Support\InMemoryTransactionManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Verifies dry-run visibility, validation rejection, and transaction safety.
 */
final class KnowledgeImporterTest extends TestCase {

	public function test_dry_run_reports_changes_without_writes(): void {
		$repository   = new InMemoryKnowledgeRepository();
		$transactions = new InMemoryTransactionManager();
		$state        = new InMemoryCoreStateStore();
		$plan         = $this->importer( $repository, $transactions, $state )->dry_run( $this->package(), $this->schema_directory() );
		$course_count = count( array_filter( $plan->changes(), static fn ( $change ): bool => 'course' === $change->entity_type() ) );

		self::assertSame( 43, $course_count );
		self::assertSame( 43, $plan->inserts() );
		self::assertSame( 0, $plan->updates() );
		self::assertSame( 0, $repository->writes() );
		self::assertSame( 0, $transactions->begins() );
	}

	public function test_import_commits_available_canonical_records_then_becomes_idempotent(): void {
		$repository   = new InMemoryKnowledgeRepository();
		$transactions = new InMemoryTransactionManager();
		$state        = new InMemoryCoreStateStore();
		$importer     = $this->importer( $repository, $transactions, $state );
		$result       = $importer->import( $this->package(), $this->schema_directory() );

		self::assertSame( $result->plan()->writes(), $result->writes() );
		self::assertSame( 43, $repository->course_count() );
		self::assertSame( 0, $repository->topic_count() );
		self::assertSame( 1, $transactions->commits() );
		self::assertNotNull( $state->knowledge_fingerprint() );

		$second_plan = $importer->dry_run( $this->package(), $this->schema_directory() );
		self::assertSame( 0, $second_plan->writes() );
		self::assertSame( count( $second_plan->changes() ), $second_plan->unchanged() );
		$second_result = $importer->import( $this->package(), $this->schema_directory() );
		self::assertSame( 0, $second_result->writes() );
		self::assertSame( 1, $transactions->begins() );
		self::assertSame( 1, $transactions->commits() );
	}

	public function test_invalid_canonical_knowledge_is_rejected_before_transaction(): void {
		$repository   = new InMemoryKnowledgeRepository();
		$transactions = new InMemoryTransactionManager();
		$state        = new InMemoryCoreStateStore();
		$invalid      = KnowledgePackageFixture::with_course_section_field( 'institute_courses', 17, 'fee', 'amount', 16999 );

		try {
			$this->importer( $repository, $transactions, $state )->import( $invalid, $this->schema_directory() );
			self::fail( 'Invalid canonical knowledge was not rejected.' );
		} catch ( InvalidKnowledgeException $exception ) {
			self::assertFalse( $exception->report()->is_valid() );
		}

		self::assertSame( 0, $repository->writes() );
		self::assertSame( 0, $transactions->begins() );
	}

	public function test_persistence_failure_rolls_back_transaction(): void {
		$repository   = new InMemoryKnowledgeRepository();
		$transactions = new InMemoryTransactionManager();
		$state        = new InMemoryCoreStateStore();
		$existing     = new CourseRecord( 'existing_valid', 'atal_institute', 'Existing Valid', '{}', hash( 'sha256', '{}' ), '1.0' );
		$repository->upsert_course( $existing );
		$transactions->attach_repository( $repository );
		$repository->fail_on_write( 3 );

		try {
			$this->importer( $repository, $transactions, $state )->import( $this->package(), $this->schema_directory() );
			self::fail( 'Injected persistence failure did not occur.' );
		} catch ( ImportPersistenceException $exception ) {
			self::assertStringStartsWith( 'upsert_course:', $exception->failed_step() );
			self::assertInstanceOf( RuntimeException::class, $exception->getPrevious() );
		}

		self::assertSame( 1, $transactions->begins() );
		self::assertSame( 0, $transactions->commits() );
		self::assertSame( 1, $transactions->rollbacks() );
		self::assertNull( $state->knowledge_fingerprint() );
		self::assertSame( 1, $repository->course_count() );
		self::assertSame( $existing->source_hash(), $repository->course_hash( 'existing_valid' ) );
	}

	private function importer(
		InMemoryKnowledgeRepository $repository,
		InMemoryTransactionManager $transactions,
		InMemoryCoreStateStore $state
	): CanonicalKnowledgeImporter {
		$transactions->attach_repository( $repository );
		return new CanonicalKnowledgeImporter(
			KnowledgeValidator::create_default(),
			new KnowledgeRecordFactory(),
			$repository,
			$transactions,
			$state
		);
	}

	private function package(): KnowledgePackage {
		return KnowledgePackage::from_directory( dirname( __DIR__, 3 ) . '/data/master' );
	}

	private function schema_directory(): string {
		return dirname( __DIR__, 3 ) . '/data/schemas';
	}
}
