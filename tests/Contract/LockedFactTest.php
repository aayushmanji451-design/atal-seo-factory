<?php
/**
 * Locked fact tests.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Contract;

use Atal\Contracts\Validation\LockedFactValidator;
use Atal\Tests\Fixtures\KnowledgePackageFixture;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the repository-locked Institute and Diploma facts.
 */
final class LockedFactTest extends TestCase {

	/**
	 * Verify the approved master data passes every locked fact.
	 */
	public function test_approved_package_passes_locked_facts(): void {
		self::assertSame( array(), ( new LockedFactValidator() )->validate( KnowledgePackageFixture::package() ) );
	}

	/**
	 * Verify CMS & ED is exactly two years and ₹17,000.
	 */
	public function test_cms_ed_locked_duration_and_fee_are_enforced(): void {
		$duration_package = KnowledgePackageFixture::with_course_section_field( 'institute_courses', 17, 'duration', 'display', '6 Months' );
		$fee_package      = KnowledgePackageFixture::with_course_section_field( 'institute_courses', 17, 'fee', 'amount', 16999 );
		$issues           = array_merge(
			( new LockedFactValidator() )->validate( $duration_package ),
			( new LockedFactValidator() )->validate( $fee_package )
		);
		$codes            = array_map( static fn ( $issue ): string => $issue->code(), $issues );

		self::assertContains( 'locked_duration_mismatch', $codes );
		self::assertContains( 'locked_fee_mismatch', $codes );
	}

	/**
	 * Verify Institute normal-post eligibility must remain omitted.
	 */
	public function test_institute_normal_post_eligibility_must_be_omitted(): void {
		$package = KnowledgePackageFixture::with_course_section_field( 'institute_courses', 0, 'eligibility', 'publication_behavior', 'SHOW' );
		$issues  = ( new LockedFactValidator() )->validate( $package );
		$codes   = array_map( static fn ( $issue ): string => $issue->code(), $issues );

		self::assertContains( 'institute_eligibility_must_be_omitted', $codes );
	}

	/**
	 * Verify Diploma special and general fees and course-level eligibility.
	 */
	public function test_diploma_fee_and_eligibility_rules_are_enforced(): void {
		$fee_package         = KnowledgePackageFixture::with_course_section_field( 'diploma_courses', 0, 'fee', 'amount', 30000 );
		$eligibility_package = KnowledgePackageFixture::with_course_section_field( 'diploma_courses', 1, 'eligibility', 'criteria', array( 'Graduation Pass' ) );
		$issues              = array_merge(
			( new LockedFactValidator() )->validate( $fee_package ),
			( new LockedFactValidator() )->validate( $eligibility_package )
		);
		$codes               = array_map( static fn ( $issue ): string => $issue->code(), $issues );

		self::assertContains( 'locked_fee_mismatch', $codes );
		self::assertContains( 'diploma_eligibility_mismatch', $codes );
	}
}
