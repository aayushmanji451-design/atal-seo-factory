<?php
/**
 * Weighted rotation candidate.
 *
 * @package AtalTopics
 */

declare(strict_types=1);

namespace Atal\Topics\Domain;

use InvalidArgumentException;

/**
 * Represents one approved course priority and optional deterministic skip.
 */
final class RotationCandidate {

	/**
	 * Create an approved rotation candidate.
	 *
	 * @param string      $target_site Canonical target site.
	 * @param string      $course_key  Canonical course key.
	 * @param int         $weight      Approved priority weight.
	 * @param string|null $skip_reason Explicit skip reason.
	 *
	 * @throws InvalidArgumentException When the weight is invalid.
	 */
	public function __construct(
		private readonly string $target_site,
		private readonly string $course_key,
		private readonly int $weight = 1,
		private readonly ?string $skip_reason = null
	) {
		if ( $weight < 1 || $weight > 100 ) {
			throw new InvalidArgumentException( 'Rotation weight must be between 1 and 100.' );
		}
	}

	/** Return the canonical target site. */
	public function target_site(): string {
		return $this->target_site;
	}

	/** Return the canonical course key. */
	public function course_key(): string {
		return $this->course_key;
	}

	/** Return the approved weight. */
	public function weight(): int {
		return $this->weight;
	}

	/** Return the optional skip reason. */
	public function skip_reason(): ?string {
		return $this->skip_reason;
	}
}
