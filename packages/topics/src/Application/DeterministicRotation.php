<?php
/**
 * Sequential weighted rotation.
 *
 * @package AtalTopics
 */

declare(strict_types=1);

namespace Atal\Topics\Application;

use Atal\Topics\Contract\RotationStateStoreInterface;
use Atal\Topics\Domain\RotationCandidate;
use Atal\Topics\Domain\RotationSelection;
use RuntimeException;

/**
 * Produces a restart-safe sequence without random selection.
 */
final class DeterministicRotation {

	/**
	 * Create the rotation service.
	 *
	 * @param RotationStateStoreInterface $state Cursor persistence.
	 */
	public function __construct( private readonly RotationStateStoreInterface $state ) {
	}

	/**
	 * Preview the next selection without moving the cursor.
	 *
	 * @param string $target_site Canonical target site.
	 * @param array  $candidates Approved candidates in approved order.
	 * @phpstan-param list<RotationCandidate> $candidates
	 *
	 * @throws RuntimeException When no candidate is selectable.
	 */
	public function peek( string $target_site, array $candidates ): RotationSelection {
		return $this->select( $target_site, $candidates, false );
	}

	/**
	 * Select the next candidate and persist the cursor.
	 *
	 * @param string $target_site Canonical target site.
	 * @param array  $candidates Approved candidates in approved order.
	 * @phpstan-param list<RotationCandidate> $candidates
	 *
	 * @throws RuntimeException When no candidate is selectable.
	 */
	public function next( string $target_site, array $candidates ): RotationSelection {
		return $this->select( $target_site, $candidates, true );
	}

	/**
	 * Select from the deterministic weighted schedule.
	 *
	 * @param string $target_site Canonical target site.
	 * @param array  $candidates Candidates.
	 * @param bool   $persist    Whether to persist the cursor.
	 * @phpstan-param list<RotationCandidate> $candidates
	 *
	 * @throws RuntimeException When no candidate is selectable.
	 */
	private function select( string $target_site, array $candidates, bool $persist ): RotationSelection {
		$schedule = array();
		foreach ( $candidates as $candidate ) {
			if ( $candidate->target_site() !== $target_site ) {
				continue;
			}
			$weight = $candidate->weight();
			for ( $slot = 0; $slot < $weight; ++$slot ) {
				$schedule[] = $candidate;
			}
		}

		if ( array() === $schedule ) {
			throw new RuntimeException( 'No approved rotation candidates exist for the target site.' );
		}

		$count   = count( $schedule );
		$before  = $this->state->cursor( $target_site ) % $count;
		$skipped = array();
		for ( $offset = 0; $offset < $count; ++$offset ) {
			$index     = ( $before + $offset ) % $count;
			$candidate = $schedule[ $index ];
			$reason    = $candidate->skip_reason();
			if ( null !== $reason ) {
				$skipped[] = array(
					'course_key' => $candidate->course_key(),
					'reason'     => $reason,
				);
				continue;
			}

			$after = ( $index + 1 ) % $count;
			if ( $persist ) {
				$this->state->set_cursor( $target_site, $after );
			}

			return new RotationSelection( $candidate->course_key(), $before, $after, $skipped );
		}

		throw new RuntimeException( 'Every approved candidate was skipped: ' . wp_json_encode( $skipped ) );
	}
}
