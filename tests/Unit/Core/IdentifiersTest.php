<?php
/**
 * Core identifier tests.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Unit\Core;

use Atal\SeoFactory\Config\Identifiers;
use Atal\SeoFactory\Infrastructure\Database\TableNames;
use PHPUnit\Framework\TestCase;

/**
 * Prevents accidental reuse of broad or legacy identifiers.
 */
final class IdentifiersTest extends TestCase {

	public function test_core_identifiers_are_new_and_namespaced(): void {
		self::assertSame( 'atal-seo-factory-core', Identifiers::PLUGIN_SLUG );
		self::assertSame( 'atal-seo-factory-core/v1', Identifiers::REST_NAMESPACE );
		self::assertStringStartsWith( 'atal_seo_factory_core_', Identifiers::OPTION_DATABASE_VERSION );
		self::assertStringStartsWith( 'atal_seo_factory_', Identifiers::TABLE_PREFIX );
	}

	public function test_all_seven_table_names_are_unique_and_core_owned(): void {
		$tables = ( new TableNames( 'wp_' ) )->all();

		self::assertCount( 7, $tables );
		self::assertCount( 7, array_unique( $tables ) );
		foreach ( $tables as $table ) {
			self::assertStringStartsWith( 'wp_atal_seo_factory_', $table );
		}
	}
}
