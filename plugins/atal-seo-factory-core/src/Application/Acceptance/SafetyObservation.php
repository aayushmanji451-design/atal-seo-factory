<?php
/**
 * Task 02 acceptance safety observation.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Application\Acceptance;

/**
 * Counts side effects observed only while the bounded acceptance run executes.
 */
final class SafetyObservation {

	public function __construct(
		private readonly int $saved_posts_pages,
		private readonly int $attachment_changes,
		private readonly int $rank_math_changes,
		private readonly int $external_requests,
		private readonly int $publish_job_execution_delta,
		private readonly int $sensitive_log_delta
	) {
	}

	public function saved_posts_pages(): int {
		return $this->saved_posts_pages;
	}

	public function attachment_changes(): int {
		return $this->attachment_changes;
	}

	public function rank_math_changes(): int {
		return $this->rank_math_changes;
	}

	public function external_requests(): int {
		return $this->external_requests;
	}

	public function publish_job_execution_delta(): int {
		return $this->publish_job_execution_delta;
	}

	public function sensitive_log_delta(): int {
		return $this->sensitive_log_delta;
	}
}
