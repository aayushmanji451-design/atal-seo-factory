<?php
/**
 * Course repository contract.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Contracts\Repository;

use Atal\Contracts\Value\CourseKey;

/**
 * Read-only access to canonical active course records.
 */
interface CourseRepositoryInterface {

	/**
	 * Return all canonical active courses.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function all(): array;

	/**
	 * Find a canonical course.
	 *
	 * @param CourseKey $course_key Canonical course key.
	 *
	 * @return array<string, mixed>|null
	 */
	public function find( CourseKey $course_key ): ?array;

	/**
	 * Resolve one canonical name or alias without creating another identity.
	 *
	 * @param string $term Canonical name or alias.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function resolve_term( string $term ): array;
}
