<?php
/**
 * Plugin bootstrap test.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Unit\Core;

use Atal\SeoFactory\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the development plugin entry point loads without executing work.
 */
final class PluginBootstrapTest extends TestCase {

	public function test_plugin_entry_point_loads_without_fatal_error(): void {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', dirname( __DIR__, 3 ) . DIRECTORY_SEPARATOR );
		}

		require dirname( __DIR__, 3 ) . '/plugins/atal-seo-factory-core/atal-seo-factory-core.php';

		self::assertTrue( class_exists( Bootstrap::class ) );
	}
}
