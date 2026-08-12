<?php
/**
 * Core activation and deactivation tests.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Unit\Core;

use Atal\SeoFactory\Application\Lifecycle\Activator;
use Atal\SeoFactory\Application\Lifecycle\Deactivator;
use Atal\SeoFactory\Application\Migration\CoreTablesMigration;
use Atal\SeoFactory\Application\Migration\MigrationRunner;
use Atal\SeoFactory\Infrastructure\Database\CoreTableDefinitions;
use Atal\SeoFactory\Infrastructure\Database\TableNames;
use Atal\Tests\Support\InMemoryCoreStateStore;
use Atal\Tests\Support\InMemorySchemaDatabase;
use PHPUnit\Framework\TestCase;

/**
 * Verifies idempotency and non-destructive deactivation.
 */
final class ActivationTest extends TestCase {

	public function test_repeated_activation_is_idempotent(): void {
		$database  = new InMemorySchemaDatabase();
		$state     = new InMemoryCoreStateStore();
		$tables    = new TableNames( $database->table_prefix() );
		$runner    = new MigrationRunner( array( new CoreTablesMigration( $database, $tables, new CoreTableDefinitions() ) ), $state );
		$activator = new Activator( $runner, $state, '0.2.0-dev' );

		$activator->activate();
		$activator->activate();

		self::assertSame( 7, $database->table_count() );
		self::assertSame( 7, $database->create_calls() );
		self::assertSame( 1, $state->database_version() );
		self::assertSame( '0.2.0-dev', $state->plugin_version() );
	}

	public function test_deactivation_preserves_tables_and_version_state(): void {
		$database = new InMemorySchemaDatabase();
		$state    = new InMemoryCoreStateStore();
		$tables   = new TableNames( $database->table_prefix() );
		$runner   = new MigrationRunner( array( new CoreTablesMigration( $database, $tables, new CoreTableDefinitions() ) ), $state );
		( new Activator( $runner, $state, '0.2.0-dev' ) )->activate();

		( new Deactivator() )->deactivate();

		self::assertSame( 7, $database->table_count() );
		self::assertSame( 0, $database->drop_calls() );
		self::assertSame( 1, $state->database_version() );
	}
}
