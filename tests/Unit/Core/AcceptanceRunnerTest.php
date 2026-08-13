<?php
/**
 * Task 02 staging acceptance runner tests.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Unit\Core;

use Atal\Contracts\Validation\KnowledgeValidator;
use Atal\SeoFactory\Application\Acceptance\AcceptanceRunner;
use Atal\SeoFactory\Application\Acceptance\KnowledgePackageInspector;
use Atal\SeoFactory\Application\Health\HealthDataProvider;
use Atal\SeoFactory\Application\Import\CanonicalKnowledgeImporter;
use Atal\SeoFactory\Application\Import\KnowledgeRecordFactory;
use Atal\SeoFactory\Application\Migration\CoreTablesMigration;
use Atal\SeoFactory\Application\Migration\MigrationRunner;
use Atal\SeoFactory\Infrastructure\Database\CoreTableDefinitions;
use Atal\SeoFactory\Infrastructure\Database\TableNames;
use Atal\Tests\Support\FakeRuntimeEnvironment;
use Atal\Tests\Support\InMemoryAcceptanceProbe;
use Atal\Tests\Support\InMemoryCoreStateStore;
use Atal\Tests\Support\InMemoryKnowledgeRepository;
use Atal\Tests\Support\InMemorySchemaDatabase;
use Atal\Tests\Support\InMemoryTransactionManager;
use PHPUnit\Framework\TestCase;

/**
 * Verifies bounded execution, second-click idempotency, and readable failures.
 */
final class AcceptanceRunnerTest extends TestCase {

	public function test_acceptance_runs_with_forty_megabyte_warning_and_zero_content_side_effects(): void {
		$fixture = $this->fixture();
		$report  = $fixture['runner']->run()->to_array();

		self::assertSame( 'WARNING', $report['status'] );
		self::assertSame( 'WARNING', $this->check( $report, 'environment_memory_preflight' )['status'] );
		self::assertSame( 'PASS', $this->check( $report, 'canonical_transactional_import' )['status'] );
		self::assertSame( 'PASS', $this->check( $report, 'stored_canonical_summary' )['status'] );
		self::assertSame( 'PASS', $this->check( $report, 'wordpress_content_unchanged' )['status'] );
		self::assertSame( 'PASS', $this->check( $report, 'publishing_jobs_unchanged' )['status'] );
		self::assertSame( 'PASS', $this->check( $report, 'remote_requests' )['status'] );
		self::assertSame( 43, $fixture['repository']->course_count() );
		self::assertSame( 1, $fixture['transactions']->commits() );
	}

	public function test_second_click_is_synchronized_and_performs_zero_writes(): void {
		$fixture = $this->fixture();
		$fixture['runner']->run();
		$writes_after_first = $fixture['repository']->writes();
		$second             = $fixture['runner']->run()->to_array();

		self::assertSame( $writes_after_first, $fixture['repository']->writes() );
		self::assertSame( 'PASS', $this->check( $second, 'second_dry_run_zero_writes' )['status'] );
		self::assertSame( 1, $fixture['transactions']->commits() );
	}

	public function test_import_failure_is_rolled_back_and_reports_exact_step_without_fatal_error(): void {
		$fixture = $this->fixture();
		$fixture['repository']->fail_on_write( 2 );
		$report   = $fixture['runner']->run()->to_array();
		$boundary = $this->check( $report, 'acceptance_boundary' );

		self::assertSame( 'FAIL', $report['status'] );
		self::assertSame( 'FAIL', $boundary['status'] );
		self::assertIsArray( $boundary['actual'] );
		self::assertIsString( $boundary['actual']['failed_step'] );
		self::assertStringStartsWith( 'upsert_course:', $boundary['actual']['failed_step'] );
		self::assertSame( 1, $fixture['transactions']->rollbacks() );
		self::assertSame( 'PASS', $this->check( $report, 'remote_requests' )['status'] );
	}

	/**
	 * @return array{runner:AcceptanceRunner,repository:InMemoryKnowledgeRepository,transactions:InMemoryTransactionManager}
	 */
	private function fixture(): array {
		$database     = new InMemorySchemaDatabase();
		$state        = new InMemoryCoreStateStore();
		$tables       = new TableNames( $database->table_prefix() );
		$migrations   = new MigrationRunner( array( new CoreTablesMigration( $database, $tables, new CoreTableDefinitions() ) ), $state );
		$repository   = new InMemoryKnowledgeRepository();
		$transactions = new InMemoryTransactionManager();
		$transactions->attach_repository( $repository );
		$runtime = new FakeRuntimeEnvironment();
		$migrations->migrate_to_latest();
		$importer = new CanonicalKnowledgeImporter(
			KnowledgeValidator::create_default(),
			new KnowledgeRecordFactory(),
			$repository,
			$transactions,
			$state
		);
		$runner   = new AcceptanceRunner(
			new HealthDataProvider( $database, $state, $tables, $runtime ),
			$runtime,
			$state,
			$tables,
			$migrations,
			$importer,
			new KnowledgePackageInspector(),
			new InMemoryAcceptanceProbe( $repository ),
			dirname( __DIR__, 3 ) . '/data/master',
			dirname( __DIR__, 3 ) . '/data/schemas'
		);

		return array(
			'runner'       => $runner,
			'repository'   => $repository,
			'transactions' => $transactions,
		);
	}

	/**
	 * @param array<string,mixed> $report Report.
	 * @param string              $id     Check identifier.
	 *
	 * @return array{check_id:string,status:string,expected:mixed,actual:mixed,message:string}
	 */
	private function check( array $report, string $id ): array {
		self::assertIsArray( $report['checks'] );
		foreach ( $report['checks'] as $check ) {
			if ( is_array( $check ) && ( $check['check_id'] ?? null ) === $id ) {
				/** @var array{check_id:string,status:string,expected:mixed,actual:mixed,message:string} $check */
				return $check;
			}
		}

		self::fail( 'Missing acceptance check: ' . $id );
	}
}
