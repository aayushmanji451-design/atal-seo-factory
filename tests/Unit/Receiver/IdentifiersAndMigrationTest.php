<?php
/** Receiver identifier and migration safety tests. @package AtalSeoFactory */
declare(strict_types=1);
namespace Atal\Tests\Unit\Receiver;

use Atal\DiplomaReceiver\Application\Migration\{MigrationInterface, MigrationRunner, ReceiverStateStoreInterface};
use Atal\DiplomaReceiver\Config\Identifiers;
use Atal\DiplomaReceiver\Infrastructure\Database\TableNames;
use PHPUnit\Framework\TestCase;
final class IdentifiersAndMigrationTest extends TestCase {
	public function test_identifiers_are_new_and_receiver_owned(): void {
		self::assertSame( 'atal-diploma-receiver', Identifiers::PLUGIN_SLUG );
		self::assertSame( 'atal-diploma-receiver/v1', Identifiers::REST_NAMESPACE );
		self::assertSame( array( 'wp_atal_diploma_receiver_receipts', 'wp_atal_diploma_receiver_audit' ), ( new TableNames( 'wp_' ) )->all() );
		foreach ( array( Identifiers::OPTION_DATABASE_VERSION, Identifiers::OPTION_PLUGIN_VERSION, Identifiers::OPTION_HMAC_SECRET ) as $identifier ) {
			self::assertStringNotContainsString( 'v3', $identifier );
			self::assertStringNotContainsString( 'v4', $identifier ); } }
	public function test_migration_is_idempotent_and_reversible_in_tests(): void {
		$state     = new ReceiverMigrationState();
		$migration = new ReceiverMigration();
		$runner    = new MigrationRunner( array( $migration ), $state );
		$runner->migrate_to_latest();
		$runner->migrate_to_latest();
		self::assertSame( 1, $migration->ups );
		self::assertSame( 1, $state->database_version() );
		$runner->rollback_to( 0 );
		self::assertSame( 1, $migration->downs );
		self::assertSame( 0, $state->database_version() ); }
}
final class ReceiverMigration implements MigrationInterface {
	public int $ups   = 0;
	public int $downs = 0;
	public function version(): int {
		return 1;
	} public function up(): void {
		++$this->ups;
	} public function down(): void {
		++$this->downs; }
}
final class ReceiverMigrationState implements ReceiverStateStoreInterface {
	private int $version = 0;
	public function database_version(): int {
		return $this->version;
	} public function set_database_version( int $version ): void {
		$this->version = $version;
	} public function record_plugin_version( string $version ): void {
		unset( $version ); } public function ensure_secret(): void {}
}
