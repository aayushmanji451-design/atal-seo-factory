<?php
/**
 * Canonical knowledge persistence contract.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\SeoFactory\Domain\Storage;

use Atal\SeoFactory\Domain\Knowledge\CourseRecord;
use Atal\SeoFactory\Domain\Knowledge\TopicRecord;

/**
 * Stores canonical course and approved syllabus-topic records only.
 */
interface KnowledgeRepositoryInterface {

	/**
	 * @param string $course_key Canonical course key.
	 */
	public function course_hash( string $course_key ): ?string;

	/**
	 * @param string $topic_key Stable topic key.
	 */
	public function topic_hash( string $topic_key ): ?string;

	public function upsert_course( CourseRecord $course ): void;

	public function upsert_topic( TopicRecord $topic ): void;
}
