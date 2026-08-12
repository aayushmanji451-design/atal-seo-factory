<?php
/**
 * Array-backed syllabus repository.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Contracts\Repository;

use Atal\Contracts\Data\JsonValue;
use Atal\Contracts\Value\CourseKey;

/**
 * Read-only repository over the canonical syllabus master.
 */
final class ArraySyllabusRepository implements SyllabusRepositoryInterface {

	/**
	 * Records keyed by course key.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $records_by_key = array();

	/**
	 * Genuine open blocks.
	 *
	 * @var list<array<string, mixed>>
	 */
	private array $open_blocks;

	/**
	 * Create a syllabus repository.
	 *
	 * @param array<string,mixed> $syllabus_master Syllabus master document.
	 */
	public function __construct( array $syllabus_master ) {
		$records = JsonValue::object_list_field( $syllabus_master, 'records' );

		foreach ( $records as $record ) {
			$this->records_by_key[ JsonValue::string_field( $record, 'course_key' ) ] = $record;
		}

		$this->open_blocks = JsonValue::object_list_field( $syllabus_master, 'open_missing_data_blocks' );
	}

	/**
	 * Find a syllabus record.
	 *
	 * @param CourseKey $course_key Canonical course key.
	 *
	 * @return array<string, mixed>|null
	 */
	public function find( CourseKey $course_key ): ?array {
		return $this->records_by_key[ $course_key->value() ] ?? null;
	}

	/**
	 * Return all genuine open blocks.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function open_missing_data_blocks(): array {
		return $this->open_blocks;
	}
}
