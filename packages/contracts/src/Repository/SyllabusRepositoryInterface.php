<?php
/**
 * Syllabus repository contract.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Contracts\Repository;

use Atal\Contracts\Value\CourseKey;

/**
 * Read-only access to syllabus and missing-data contracts.
 */
interface SyllabusRepositoryInterface {

	/**
	 * Find the syllabus record for a canonical course.
	 *
	 * @param CourseKey $course_key Canonical course key.
	 *
	 * @return array<string, mixed>|null
	 */
	public function find( CourseKey $course_key ): ?array;

	/**
	 * Return all genuine open missing-data blocks.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function open_missing_data_blocks(): array;
}
