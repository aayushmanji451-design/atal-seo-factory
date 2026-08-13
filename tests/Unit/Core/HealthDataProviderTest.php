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
 * Verifies bounded, read-only health reporting and non-blocking memory guidance.
 */
final class HealthDataProviderTest extends TestCase {

	public function test_recorded_forty_megabyte_limit_is_a_warning_not_a_blocker(): void {
		$database = new InMemorySchemaDatabase();
		$tables   = new TableNames( $database->table_prefix() );
		$database->seed_tables( $tables->all() );
		$snapshot = ( new HealthDataProvider( $database, new InMemoryCoreStateStore(), $tables, new FakeRuntimeEnvironment() ) )->snapshot();

		self::assertSame( 'WARNING', $snapshot['memory_status'] );
		self::assertSame( '40M', $snapshot['wordpress_memory_limit'] );
		self::assertSame( '2048M', $snapshot['wordpress_max_memory_limit'] );
		self::assertSame( '2048M', $snapshot['php_memory_limit'] );
		self::assertSame( 12582912, $snapshot['current_memory_usage'] );
		self::assertSame( 16777216, $snapshot['peak_memory_usage'] );
		self::assertStringContainsString( 'remains available', $snapshot['memory_message'] );
		self::assertSame( 7, $database->read_calls() );
		self::assertSame( 0, $database->create_calls() );
		self::assertTrue( $snapshot['read_only'] );
	}

	public function test_two_hundred_fifty_six_megabyte_limit_meets_prerequisite(): void {
		$database = new InMemorySchemaDatabase();
		$tables   = new TableNames( $database->table_prefix() );
		$snapshot = ( new HealthDataProvider( $database, new InMemoryCoreStateStore(), $tables, new FakeRuntimeEnvironment( '256M' ) ) )->snapshot();

		self::assertSame( 'PASS', $snapshot['memory_status'] );
	}
}
