<?php
/**
 * Array-backed course repository.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Contracts\Repository;

use Atal\Contracts\Data\JsonValue;
use Atal\Contracts\Value\CourseKey;

/**
 * Read-only repository over the two canonical course master documents.
 */
final class ArrayCourseRepository implements CourseRepositoryInterface {

	/**
	 * Course records keyed by course key.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $courses_by_key = array();

	/**
	 * Normalized canonical names and aliases mapped to course keys.
	 *
	 * @var array<string, list<string>>
	 */
	private array $term_to_keys = array();

	/**
	 * Create a repository.
	 *
	 * @param array<string,mixed> $institute_master Institute course master.
	 * @param array<string,mixed> $diploma_master   Diploma course master.
	 */
	public function __construct( array $institute_master, array $diploma_master ) {
		$this->add_document( $institute_master );
		$this->add_document( $diploma_master );
	}

	/**
	 * Return all canonical active courses.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function all(): array {
		return array_values( $this->courses_by_key );
	}

	/**
	 * Find a canonical course.
	 *
	 * @param CourseKey $course_key Canonical course key.
	 *
	 * @return array<string, mixed>|null
	 */
	public function find( CourseKey $course_key ): ?array {
		return $this->courses_by_key[ $course_key->value() ] ?? null;
	}

	/**
	 * Resolve one canonical name or alias.
	 *
	 * @param string $term Canonical name or alias.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function resolve_term( string $term ): array {
		$normalized = $this->normalize_term( $term );
		$keys       = array_values( array_unique( $this->term_to_keys[ $normalized ] ?? array() ) );
		$records    = array();

		foreach ( $keys as $key ) {
			if ( isset( $this->courses_by_key[ $key ] ) ) {
				$records[] = $this->courses_by_key[ $key ];
			}
		}

		return $records;
	}

	/**
	 * Add one master document.
	 *
	 * @param array<string,mixed> $document Course master document.
	 */
	private function add_document( array $document ): void {
		$courses = JsonValue::object_list_field( $document, 'courses' );

		foreach ( $courses as $course ) {
			$key                          = JsonValue::string_field( $course, 'course_key' );
			$this->courses_by_key[ $key ] = $course;
			$terms                        = array( JsonValue::string_field( $course, 'canonical_name' ) );
			$aliases                      = JsonValue::string_list_field( $course, 'aliases' );
			$terms                        = array_merge( $terms, $aliases );

			foreach ( $terms as $term ) {
				$normalized = $this->normalize_term( $term );
				if ( ! isset( $this->term_to_keys[ $normalized ] ) ) {
					$this->term_to_keys[ $normalized ] = array();
				}
				$this->term_to_keys[ $normalized ][] = $key;
			}
		}
	}

	/**
	 * Normalize a lookup term.
	 *
	 * @param string $term Raw term.
	 */
	private function normalize_term( string $term ): string {
		$normalized = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', strtolower( trim( $term ) ) );

		return null === $normalized ? '' : trim( $normalized );
	}
}
