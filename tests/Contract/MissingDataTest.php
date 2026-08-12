<?php
/**
 * Missing syllabus and assessment tests.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Contract;

use Atal\Contracts\Validation\MissingDataValidator;
use Atal\Tests\Fixtures\KnowledgePackageFixture;
use PHPUnit\Framework\TestCase;

/**
 * Verifies exactly six genuine blocks and narrow content gating.
 */
final class MissingDataTest extends TestCase {

	/**
	 * Verify the six approved genuine missing-data blocks.
	 */
	public function test_exactly_six_approved_blocks_remain_open(): void {
		$validator = new MissingDataValidator();
		$package   = KnowledgePackageFixture::package();

		self::assertCount( 6, $validator->report( $package ) );
		self::assertSame( array(), $validator->validate( $package ) );
	}

	/**
	 * Verify missing syllabus cannot block overview or the course master.
	 */
	public function test_missing_syllabus_cannot_overblock_course_content(): void {
		$blocked_package = KnowledgePackageFixture::with_record_field( 'syllabus', 17, 'course_master_blocked', true );
		$intent_package  = KnowledgePackageFixture::with_blocked_intent( 17, 'course_overview' );
		$issues          = array_merge(
			( new MissingDataValidator() )->validate( $blocked_package ),
			( new MissingDataValidator() )->validate( $intent_package )
		);
		$codes           = array_map( static fn ( $issue ): string => $issue->code(), $issues );

		self::assertContains( 'missing_syllabus_blocked_course_master', $codes );
		self::assertContains( 'missing_data_overblocks_intent', $codes );
	}
}
