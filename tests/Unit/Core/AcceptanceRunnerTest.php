<?php
/**
 * Browser-oriented Task 02 acceptance runner tests.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Unit\Core;

use Atal\Contracts\Validation\KnowledgeValidator;
use Atal\SeoFactory\Application\Acceptance\AcceptanceCheck;
use Atal\SeoFactory\Application\Acceptance\AcceptanceRunner;
use Atal\SeoFactory\Application\Health\HealthDataProvider;
use Atal\SeoFactory\Application\Import\CanonicalKnowledgeImporter;
use Atal\SeoFactory\Application\Import\KnowledgeRecordFactory;
use Atal\SeoFactory\Application\Migration\CoreTablesMigration;
use Atal\SeoFactory\Application\Migration\MigrationRunner;
use Atal\SeoFactory\Infrastructure\Database\CoreTableDefinitions;
use Atal\SeoFactory\Infrastructure\Database\TableNames;
use Atal\Tests\Support\FakeRuntimeEnvironment;
use Atal\Tests\Support\InMemoryCoreStateStore;
use Atal\Tests\Support\InMemoryKnowledgeRepository;
use Atal\Tests\Support\InMemorySafetyMonitor;
use Atal\Tests\Support\InMemorySchemaDatabase;
use Atal\Tests\Support\InMemoryTransactionManager;
use PHPUnit\Framework\TestCase;

/**
 * Proves 40M is non-blocking, the admin sequence is idempotent, and failures roll back.
 */
final class AcceptanceRunnerTest extends TestCase {

	public function test_forty_megabytes_warns_but_full_acceptance_runs_and_is_idempotent(): void {
		$fixture = $this->fixture();
		$first   = $fixture['runner']->run();

		self::assertSame( 'PASS_WITH_WARNINGS', $first->status() );
		self::assertSame( AcceptanceCheck::WARNING, $this->check_status( $first->to_array(), 'memory_preflight' ) );
		self::assertSame( AcceptanceCheck::PASS, $this->check_status( $first->to_array(), 'second_knowledge_dry_run' ) );
		self::assertSame( 43, $first->first_dry_run()['writes'] );
		self::assertSame( 43, $fixture['repository']->course_count() );
		self::assertSame( 1, $fixture['transactions']->commits() );
		self::assertNotNull( $fixture['state']->knowledge_fingerprint() );

		$second = $fixture['runner']->run();

		self::assertSame( 'PASS_WITH_WARNINGS', $second->status() );
		self::assertSame( 0, $second->first_dry_run()['writes'] );
		self::assertSame( 2, $fixture['transactions']->commits() );
		self::assertSame( 0, $fixture['database']->create_calls() );
		self::assertSame( 2, $fixture['safety']->starts() );
		self::assertSame( 2, $fixture['safety']->stops() );
	}

	public function test_actual_import_failure_is_reported_and_transaction_rolls_back(): void {
		$repository = new InMemoryKnowledgeRepository();
		$repository->fail_on_write( 2 );
		$fixture = $this->fixture( $repository );
		$report  = $fixture['runner']->run();

		self::assertSame( AcceptanceCheck::FAIL, $report->status() );
		self::assertSame( AcceptanceCheck::FAIL, $this->check_status( $report->to_array(), 'acceptance_runtime' ) );
		self::assertSame( 1, $fixture['transactions']->rollbacks() );
		self::assertSame( 1, $fixture['safety']->stops() );
	}

	/**
	 * @param InMemoryKnowledgeRepository|null $repository Optional repository test double.
	 *
	 * @return array{runner:AcceptanceRunner,repository:InMemoryKnowledgeRepository,transactions:InMemoryTransactionManager,state:InMemoryCoreStateStore,database:InMemorySchemaDatabase,safety:InMemorySafetyMonitor}
	 */
	private function fixture( ?InMemoryKnowledgeRepository $repository = null ): array {
		$database = new InMemorySchemaDatabase();
		$tables   = new TableNames( $database->table_prefix() );
		$database->seed_tables( $tables->all() );
		$state = new InMemoryCoreStateStore();
		$state->set_database_version( 1 );
		$repository   = $repository ?? new InMemoryKnowledgeRepository();
		$transactions = new InMemoryTransactionManager();
		$validator    = KnowledgeValidator::create_default();
		$importer     = new CanonicalKnowledgeImporter( $validator, new KnowledgeRecordFactory(), $repository, $transactions, $state );
		$migrations   = new MigrationRunner( array( new CoreTablesMigration( $database, $tables, new CoreTableDefinitions() ) ), $state );
		$health       = new HealthDataProvider( $database, $state, $tables, new FakeRuntimeEnvironment() );
		$safety       = new InMemorySafetyMonitor();
		$root         = dirname( __DIR__, 3 );

		return array(
			'runner'       => new AcceptanceRunner( $migrations, $database, $state, $tables, $validator, $importer, $health, $safety, $root . '/data/master', $root . '/data/schemas' ),
			'repository'   => $repository,
			'transactions' => $transactions,
			'state'        => $state,
			'database'     => $database,
			'safety'       => $safety,
		);
	}

	/**
	 * @param array<string,mixed> $report   Report data.
	 * @param string              $check_id Check identifier.
	 */
	private function check_status( array $report, string $check_id ): string {
		if ( ! isset( $report['checks'] ) || ! is_array( $report['checks'] ) ) {
			self::fail( 'Acceptance report checks are missing.' );
		}

		foreach ( $report['checks'] as $check ) {
			if ( is_array( $check ) && isset( $check['check_id'], $check['status'] ) && $check_id === $check['check_id'] && is_string( $check['status'] ) ) {
				return $check['status'];
			}
		}

		self::fail( 'Acceptance check not found: ' . $check_id );
	}
}
