<?php
/**
 * Topic quality states.
 *
 * @package AtalTopics
 */

declare(strict_types=1);

namespace Atal\Topics\Domain;

/**
 * Closed set of deterministic validation outcomes.
 */
final class QualityState {

	public const PASS = 'PASS';

	public const NEEDS_REVIEW = 'NEEDS_REVIEW';

	public const REJECTED = 'REJECTED';

	/** Prevent construction. */
	private function __construct() {
	}
}
