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
		$activator = new Activator( $runner, $state, '0.2.1-dev' );

		$activator->activate();
		$activator->activate();

		self::assertSame( 7, $database->table_count() );
		self::assertSame( 7, $database->create_calls() );
		self::assertSame( 1, $state->database_version() );
		self::assertSame( '0.2.1-dev', $state->plugin_version() );
	}

	public function test_deactivation_preserves_tables_and_version_state(): void {
		$database = new InMemorySchemaDatabase();
		$state    = new InMemoryCoreStateStore();
		$tables   = new TableNames( $database->table_prefix() );
		$runner   = new MigrationRunner( array( new CoreTablesMigration( $database, $tables, new CoreTableDefinitions() ) ), $state );
		( new Activator( $runner, $state, '0.2.1-dev' ) )->activate();

		( new Deactivator() )->deactivate();

		self::assertSame( 7, $database->table_count() );
		self::assertSame( 0, $database->drop_calls() );
		self::assertSame( 1, $state->database_version() );
	}

	public function test_manual_reactivation_check_preserves_tables_and_import_fingerprint(): void {
		$database  = new InMemorySchemaDatabase();
		$state     = new InMemoryCoreStateStore();
		$tables    = new TableNames( $database->table_prefix() );
		$runner    = new MigrationRunner( array( new CoreTablesMigration( $database, $tables, new CoreTableDefinitions() ) ), $state );
		$activator = new Activator( $runner, $state, '0.2.1-dev' );
		$activator->activate();
		$state->record_knowledge_import( str_repeat( 'a', 64 ) );
		self::assertFalse( $state->post_reactivation_persistence_verified() );

		( new Deactivator() )->deactivate();
		$activator->activate();

		self::assertSame( 7, $database->table_count() );
		self::assertSame( 7, $database->create_calls() );
		self::assertSame( str_repeat( 'a', 64 ), $state->knowledge_fingerprint() );
		self::assertTrue( $state->post_reactivation_persistence_verified() );
	}

	public function test_legacy_import_establishes_a_one_time_reactivation_baseline(): void {
		$state = new InMemoryCoreStateStore();
		$state->record_plugin_version( '0.2.1-dev' );
		$state->seed_legacy_knowledge_fingerprint( str_repeat( 'b', 64 ) );

		$state->ensure_reactivation_baseline();
		self::assertFalse( $state->post_reactivation_persistence_verified() );

		$state->record_plugin_version( '0.2.1-dev' );
		self::assertTrue( $state->post_reactivation_persistence_verified() );
	}
}
