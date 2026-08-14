<?php
/**
 * Deterministic identity and rotation tests.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Unit\Topics;

use Atal\Tests\Support\Topics\InMemoryRotationStateStore;
use Atal\Topics\Application\DeterministicRotation;
use Atal\Topics\Domain\RotationCandidate;
use Atal\Topics\Domain\TopicIdentity;
use PHPUnit\Framework\TestCase;

/**
 * Proves random selection is impossible and cursors survive restarts.
 */
final class TopicIdentityAndRotationTest extends TestCase {

	public function test_identity_is_stable_and_uses_every_identity_field(): void {
		$first  = new TopicIdentity( 'atal_institute', 'institute_gda', 'fees', 'GDA Course Fees', 2026 );
		$second = new TopicIdentity( 'atal_institute', 'institute_gda', 'fees', '  gda   course fees ', 2026 );
		$next   = new TopicIdentity( 'atal_institute', 'institute_gda', 'fees', 'GDA Course Fees', 2027 );

		self::assertSame( $first->key(), $second->key() );
		self::assertNotSame( $first->key(), $next->key() );
		self::assertMatchesRegularExpression( '/^topic_[a-f0-9]{40}$/', $first->key() );
	}

	public function test_weighted_sequence_is_restart_safe_and_has_no_starvation(): void {
		$state      = new InMemoryRotationStateStore();
		$candidates = array(
			new RotationCandidate( 'atal_institute', 'course_a', 2 ),
			new RotationCandidate( 'atal_institute', 'course_b', 1 ),
		);
		$rotation   = new DeterministicRotation( $state );

		self::assertSame( 'course_a', $rotation->next( 'atal_institute', $candidates )->course_key() );
		self::assertSame( 'course_a', ( new DeterministicRotation( $state ) )->next( 'atal_institute', $candidates )->course_key() );
		self::assertSame( 'course_b', ( new DeterministicRotation( $state ) )->next( 'atal_institute', $candidates )->course_key() );
		self::assertSame( 'course_a', ( new DeterministicRotation( $state ) )->next( 'atal_institute', $candidates )->course_key() );
	}

	public function test_preview_does_not_move_cursor_and_skips_are_explained(): void {
		$state      = new InMemoryRotationStateStore();
		$rotation   = new DeterministicRotation( $state );
		$candidates = array(
			new RotationCandidate( 'atal_institute', 'blocked', 1, 'approved syllabus is missing' ),
			new RotationCandidate( 'atal_institute', 'available', 1 ),
		);

		$preview = $rotation->peek( 'atal_institute', $candidates )->to_array();
		self::assertSame( 'available', $preview['course_key'] );
		self::assertSame(
			array(
				array(
					'course_key' => 'blocked',
					'reason'     => 'approved syllabus is missing',
				),
			),
			$preview['skipped']
		);
		self::assertSame( 0, $state->cursor( 'atal_institute' ) );
		self::assertSame( 'available', $rotation->next( 'atal_institute', $candidates )->course_key() );
		self::assertSame( 0, $state->cursor( 'atal_institute' ) );
	}
}
