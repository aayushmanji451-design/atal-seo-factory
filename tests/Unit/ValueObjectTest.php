<?php
/**
 * Immutable value object tests.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Unit;

use Atal\Contracts\Value\CourseKey;
use Atal\Contracts\Value\Duration;
use Atal\Contracts\Value\Eligibility;
use Atal\Contracts\Value\Money;
use Atal\Contracts\Value\TargetSite;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Tests runtime-neutral immutable contract values.
 */
final class ValueObjectTest extends TestCase {

	/**
	 * Verify valid values retain their canonical representation.
	 */
	public function test_valid_values_are_exposed_without_mutation(): void {
		$key         = new CourseKey( 'institute_cms_ed' );
		$site        = new TargetSite( TargetSite::INSTITUTE );
		$money       = new Money( 17000, 'INR', '₹17,000' );
		$duration    = new Duration( '2 Years', 24 );
		$eligibility = new Eligibility( Eligibility::OMIT, array() );

		self::assertSame( 'institute_cms_ed', $key->value() );
		self::assertTrue( $site->is_institute() );
		self::assertSame( 17000, $money->amount() );
		self::assertSame( 24, $duration->normalized_months() );
		self::assertSame( array(), $eligibility->criteria() );
	}

	/**
	 * Verify invalid eligibility cannot be constructed.
	 */
	public function test_omit_eligibility_rejects_exposed_criteria(): void {
		$this->expectException( InvalidArgumentException::class );
		new Eligibility( Eligibility::OMIT, array( '12th Pass' ) );
	}
}
