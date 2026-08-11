<?php
/**
 * Test-suite health check.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Proves that the bootstrap and supported PHP runtime are wired correctly.
 */
final class HealthTest extends TestCase {

	/**
	 * Verify the minimum supported PHP runtime is active.
	 */
	public function test_bootstrap_loads_supported_php_runtime(): void {
		self::assertGreaterThanOrEqual( 80100, PHP_VERSION_ID );
	}
}
