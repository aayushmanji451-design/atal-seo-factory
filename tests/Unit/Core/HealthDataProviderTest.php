<?php
/**
 * Staging health data tests.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Unit\Core;

use Atal\SeoFactory\Application\Health\HealthDataProvider;
use Atal\SeoFactory\Infrastructure\Database\TableNames;
use Atal\Tests\Support\FakeRuntimeEnvironment;
use Atal\Tests\Support\InMemoryCoreStateStore;
use Atal\Tests\Support\InMemorySchemaDatabase;
use PHPUnit\Framework\TestCase;

/**
 * Verifies bounded, read-only health reporting against actual runtime capacity.
 */
final class HealthDataProviderTest extends TestCase {

	public function test_recorded_forty_megabyte_limit_is_warning_when_actual_runtime_has_headroom(): void {
		$database = new InMemorySchemaDatabase();
		$tables   = new TableNames( $database->table_prefix() );
		$database->seed_tables( $tables->all() );
		$snapshot = ( new HealthDataProvider( $database, new InMemoryCoreStateStore(), $tables, new FakeRuntimeEnvironment() ) )->snapshot();

		self::assertSame( 'WARNING', $snapshot['memory']['status'] );
		self::assertSame( '40M', $snapshot['memory']['wordpress_memory_limit'] );
		self::assertSame( '2048M', $snapshot['memory']['wordpress_max_memory_limit'] );
		self::assertSame( '2048M', $snapshot['memory']['php_memory_limit'] );
		self::assertSame( 20971520, $snapshot['memory']['current_usage_bytes'] );
		self::assertSame( 25165824, $snapshot['memory']['peak_usage_bytes'] );
		self::assertTrue( $snapshot['memory']['wordpress_admin_can_raise'] );
		self::assertGreaterThan( 2000000000, $snapshot['memory']['actual_available_bytes'] );
		self::assertSame( 7, $database->read_calls() );
		self::assertSame( 0, $database->create_calls() );
		self::assertTrue( $snapshot['read_only'] );
	}

	public function test_two_hundred_fifty_six_megabyte_limit_passes_with_actual_headroom(): void {
		$database = new InMemorySchemaDatabase();
		$tables   = new TableNames( $database->table_prefix() );
		$snapshot = ( new HealthDataProvider( $database, new InMemoryCoreStateStore(), $tables, new FakeRuntimeEnvironment( '256M' ) ) )->snapshot();

		self::assertSame( 'PASS', $snapshot['memory']['status'] );
	}

	public function test_actual_runtime_exhaustion_fails_even_with_high_wordpress_constant(): void {
		$database = new InMemorySchemaDatabase();
		$tables   = new TableNames( $database->table_prefix() );
		$runtime  = new FakeRuntimeEnvironment( '256M', '64M', '256M', 64 * 1024 * 1024, 64 * 1024 * 1024 );
		$snapshot = ( new HealthDataProvider( $database, new InMemoryCoreStateStore(), $tables, $runtime ) )->snapshot();

		self::assertSame( 'FAIL', $snapshot['memory']['status'] );
	}
}
