<?php
/**
 * Canonical storage course record.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Domain\Knowledge;

/**
 * Immutable course representation ready for persistence.
 */
final class CourseRecord {

	public function __construct(
		private readonly string $course_key,
		private readonly string $target_site,
		private readonly string $canonical_name,
		private readonly string $payload_json,
		private readonly string $source_hash,
		private readonly string $contract_version
	) {
	}

	public function course_key(): string {
		return $this->course_key;
	}

	public function target_site(): string {
		return $this->target_site;
	}

	public function canonical_name(): string {
		return $this->canonical_name;
	}

	public function payload_json(): string {
		return $this->payload_json;
	}

	public function source_hash(): string {
		return $this->source_hash;
	}

	public function contract_version(): string {
		return $this->contract_version;
	}
}
