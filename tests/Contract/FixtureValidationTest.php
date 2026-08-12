<?php
/**
 * Approved fixture execution test.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Contract;

use Atal\Contracts\Validation\FixtureValidator;
use Atal\Tests\Fixtures\KnowledgePackageFixture;
use PHPUnit\Framework\TestCase;

/**
 * Executes all 30 Phase 1 approved fixtures.
 */
final class FixtureValidationTest extends TestCase {

	/**
	 * Verify the full fixture suite passes.
	 */
	public function test_all_approved_fixtures_pass(): void {
		$result = ( new FixtureValidator() )->validate( KnowledgePackageFixture::package() );

		self::assertSame( 30, $result['total'] );
		self::assertSame( 30, $result['passed'] );
		self::assertSame( array(), $result['issues'] );
	}
}
