<?php
/**
 * Validated canonical storage record set.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Domain\Knowledge;

/**
 * Immutable courses, topics, and deterministic package fingerprint.
 */
final class RecordSet {

	/**
	 * @param list<CourseRecord> $courses     Canonical courses.
	 * @param list<TopicRecord>  $topics      Approved topics.
	 * @param string             $fingerprint Package fingerprint.
	 */
	public function __construct(
		private readonly array $courses,
		private readonly array $topics,
		private readonly string $fingerprint
	) {
	}

	/**
	 * @return list<CourseRecord>
	 */
	public function courses(): array {
		return $this->courses;
	}

	/**
	 * @return list<TopicRecord>
	 */
	public function topics(): array {
		return $this->topics;
	}

	public function fingerprint(): string {
		return $this->fingerprint;
	}
}
