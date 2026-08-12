<?php
/**
 * Source reference validation tests.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Contract;

use Atal\Contracts\Validation\SourceReferenceValidator;
use Atal\Tests\Fixtures\KnowledgePackageFixture;
use PHPUnit\Framework\TestCase;

/**
 * Verifies every fee and duration has resolvable source references.
 */
final class SourceReferenceTest extends TestCase {

	/**
	 * Verify all 43 courses and 49 Institute options are sourced.
	 */
	public function test_all_required_fee_and_duration_facts_have_sources(): void {
		$validator = new SourceReferenceValidator();
		$package   = KnowledgePackageFixture::package();

		self::assertSame( array(), $validator->validate( $package ) );
		self::assertSame(
			array(
				'course_facts_checked'      => 43,
				'institute_options_checked' => 49,
			),
			$validator->metrics( $package )
		);
	}

	/**
	 * Verify an empty or unknown reference is rejected.
	 */
	public function test_missing_and_unknown_source_references_are_rejected(): void {
		$missing_package = KnowledgePackageFixture::with_course_section_field( 'institute_courses', 0, 'fee', 'source_refs', array() );
		$unknown_package = KnowledgePackageFixture::with_course_section_field( 'institute_courses', 0, 'duration', 'source_refs', array( 'src.unknown' ) );
		$issues          = array_merge(
			( new SourceReferenceValidator() )->validate( $missing_package ),
			( new SourceReferenceValidator() )->validate( $unknown_package )
		);
		$codes           = array_map( static fn ( $issue ): string => $issue->code(), $issues );

		self::assertContains( 'required_source_reference_missing', $codes );
		self::assertContains( 'unknown_source_reference', $codes );
	}
}
