<?php
/**
 * Core migration tests.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Unit\Core;

use Atal\SeoFactory\Application\Migration\CoreTablesMigration;
use Atal\SeoFactory\Application\Migration\MigrationRunner;
use Atal\SeoFactory\Infrastructure\Database\CoreTableDefinitions;
use Atal\SeoFactory\Infrastructure\Database\TableNames;
use Atal\Tests\Support\InMemoryCoreStateStore;
use Atal\Tests\Support\InMemorySchemaDatabase;
use PHPUnit\Framework\TestCase;

/**
 * Verifies version 1 creation and explicit rollback.
 */
final class MigrationRunnerTest extends TestCase {

	public function test_version_one_creates_all_seven_new_tables(): void {
		$database = new InMemorySchemaDatabase();
		$state    = new InMemoryCoreStateStore();
		$runner   = $this->runner( $database, $state );

		$runner->migrate_to_latest();

		self::assertSame( 1, $state->database_version() );
		self::assertSame( 7, $database->table_count() );
		self::assertSame( 7, $database->create_calls() );
	}

	public function test_applied_migration_can_be_reversed_in_tests(): void {
		$database = new InMemorySchemaDatabase();
		$state    = new InMemoryCoreStateStore();
		$runner   = $this->runner( $database, $state );

		$runner->migrate_to_latest();
		$runner->rollback_to( 0 );

		self::assertSame( 0, $state->database_version() );
		self::assertSame( 0, $database->table_count() );
		self::assertSame( 7, $database->drop_calls() );
	}

	private function runner( InMemorySchemaDatabase $database, InMemoryCoreStateStore $state ): MigrationRunner {
		$tables = new TableNames( $database->table_prefix() );

		return new MigrationRunner(
			array( new CoreTablesMigration( $database, $tables, new CoreTableDefinitions() ) ),
			$state
		);
	}
}
