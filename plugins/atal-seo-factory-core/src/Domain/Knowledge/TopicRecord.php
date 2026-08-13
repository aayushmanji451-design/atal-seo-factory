<?php
/**
 * Canonical storage topic record.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Domain\Knowledge;

/**
 * Immutable approved syllabus-topic representation.
 */
final class TopicRecord {

	public function __construct(
		private readonly string $topic_key,
		private readonly string $course_key,
		private readonly string $target_site,
		private readonly string $title,
		private readonly string $payload_json,
		private readonly string $source_hash,
		private readonly string $contract_version
	) {
	}

	public function topic_key(): string {
		return $this->topic_key;
	}

	public function course_key(): string {
		return $this->course_key;
	}

	public function target_site(): string {
		return $this->target_site;
	}

	public function title(): string {
		return $this->title;
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
