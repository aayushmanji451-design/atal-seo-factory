<?php
/**
 * Cross-site identity tests.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Contract;

use Atal\Contracts\Validation\IdentityValidator;
use Atal\Tests\Fixtures\KnowledgePackageFixture;
use PHPUnit\Framework\TestCase;

/**
 * Proves that Institute and Diploma identities never mix.
 */
final class IdentityValidationTest extends TestCase {

	/**
	 * Verify the complete approved identity graph.
	 */
	public function test_approved_identity_graph_has_no_collision_or_cross_site_link(): void {
		self::assertSame( array(), ( new IdentityValidator() )->validate( KnowledgePackageFixture::package() ) );
	}

	/**
	 * Verify a target-site mismatch is rejected.
	 */
	public function test_cross_site_identity_mismatch_is_rejected(): void {
		$package = KnowledgePackageFixture::with_record_field( 'course_urls', 0, 'target_site', 'atal_diploma' );
		$issues  = ( new IdentityValidator() )->validate( $package );
		$codes   = array_map( static fn ( $issue ): string => $issue->code(), $issues );

		self::assertContains( 'course_target_site_mismatch', $codes );
	}

	/**
	 * Verify an alias cannot resolve to two active records.
	 */
	public function test_alias_collision_is_rejected(): void {
		$package = KnowledgePackageFixture::with_course_alias( 'diploma_courses', 0, 'CMS ED' );
		$issues  = ( new IdentityValidator() )->validate( $package );
		$codes   = array_map( static fn ( $issue ): string => $issue->code(), $issues );

		self::assertContains( 'alias_identity_collision', $codes );
	}
}
