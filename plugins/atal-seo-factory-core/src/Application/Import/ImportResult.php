<?php
/**
 * Committed canonical import result.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Application\Import;

/**
 * Couples the displayed dry-run plan with the committed write count.
 */
final class ImportResult {

	public function __construct(
		private readonly ImportPlan $plan,
		private readonly int $writes
	) {
	}

	public function plan(): ImportPlan {
		return $this->plan;
	}

	public function writes(): int {
		return $this->writes;
	}
}
