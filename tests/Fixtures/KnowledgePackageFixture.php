<?php
/**
 * Canonical knowledge package test fixture.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Tests\Fixtures;

use Atal\Contracts\Data\JsonValue;
use Atal\Contracts\Data\KnowledgePackage;
use RuntimeException;

/**
 * Loads the approved repository master data for contract tests.
 */
final class KnowledgePackageFixture {

	/**
	 * Return the repository root.
	 */
	public static function project_root(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Load the approved Phase 1 package.
	 */
	public static function package(): KnowledgePackage {
		return KnowledgePackage::from_directory( self::project_root() . '/data/master' );
	}

	/**
	 * Replace a nested field in one course record.
	 *
	 * @param string $document_name Course document name.
	 * @param int    $course_index  Course list index.
	 * @param string $section       Nested object field.
	 * @param string $field         Nested value field.
	 * @param mixed  $value         Replacement value.
	 */
	public static function with_course_section_field(
		string $document_name,
		int $course_index,
		string $section,
		string $field,
		mixed $value
	): KnowledgePackage {
		$documents                   = self::package()->documents();
		$document                    = self::document( $documents, $document_name );
		$courses                     = JsonValue::object_list_field( $document, 'courses' );
		$course                      = self::list_item( $courses, $course_index );
		$object                      = JsonValue::object_field( $course, $section );
		$object[ $field ]            = $value;
		$course[ $section ]          = $object;
		$courses[ $course_index ]    = $course;
		$document['courses']         = $courses;
		$documents[ $document_name ] = $document;

		return KnowledgePackage::from_documents( $documents );
	}

	/**
	 * Add an alias to one active course record.
	 *
	 * @param string $document_name Course document name.
	 * @param int    $course_index  Course list index.
	 * @param string $alias         Alias to add.
	 */
	public static function with_course_alias( string $document_name, int $course_index, string $alias ): KnowledgePackage {
		$documents                   = self::package()->documents();
		$document                    = self::document( $documents, $document_name );
		$courses                     = JsonValue::object_list_field( $document, 'courses' );
		$course                      = self::list_item( $courses, $course_index );
		$aliases                     = JsonValue::string_list_field( $course, 'aliases' );
		$aliases[]                   = $alias;
		$course['aliases']           = $aliases;
		$courses[ $course_index ]    = $course;
		$document['courses']         = $courses;
		$documents[ $document_name ] = $document;

		return KnowledgePackage::from_documents( $documents );
	}

	/**
	 * Replace a field in one catalog mapping record.
	 *
	 * @param string $document_name Mapping document name.
	 * @param int    $record_index  Record list index.
	 * @param string $field         Field name.
	 * @param mixed  $value         Replacement value.
	 */
	public static function with_record_field( string $document_name, int $record_index, string $field, mixed $value ): KnowledgePackage {
		$documents                   = self::package()->documents();
		$document                    = self::document( $documents, $document_name );
		$records                     = JsonValue::object_list_field( $document, 'records' );
		$record                      = self::list_item( $records, $record_index );
		$record[ $field ]            = $value;
		$records[ $record_index ]    = $record;
		$document['records']         = $records;
		$documents[ $document_name ] = $document;

		return KnowledgePackage::from_documents( $documents );
	}

	/**
	 * Add a blocked intent to one syllabus record.
	 *
	 * @param int    $record_index Record list index.
	 * @param string $intent       Intent to add.
	 */
	public static function with_blocked_intent( int $record_index, string $intent ): KnowledgePackage {
		$documents                 = self::package()->documents();
		$document                  = self::document( $documents, 'syllabus' );
		$records                   = JsonValue::object_list_field( $document, 'records' );
		$record                    = self::list_item( $records, $record_index );
		$intents                   = JsonValue::string_list_field( $record, 'blocked_intents' );
		$intents[]                 = $intent;
		$record['blocked_intents'] = $intents;
		$records[ $record_index ]  = $record;
		$document['records']       = $records;
		$documents['syllabus']     = $document;

		return KnowledgePackage::from_documents( $documents );
	}

	/**
	 * Return one known document.
	 *
	 * @param array<string,array<string,mixed>> $documents    Documents.
	 * @param string                            $document_name Document name.
	 *
	 * @return array<string,mixed>
	 * @throws RuntimeException When the requested document is unknown.
	 */
	private static function document( array $documents, string $document_name ): array {
		if ( ! isset( $documents[ $document_name ] ) ) {
			throw new RuntimeException( 'Unknown fixture document.' );
		}

		return $documents[ $document_name ];
	}

	/**
	 * Return one object-list item.
	 *
	 * @param list<array<string,mixed>> $items Items.
	 * @param int                       $index Item index.
	 *
	 * @return array<string,mixed>
	 * @throws RuntimeException When the requested list item is unknown.
	 */
	private static function list_item( array $items, int $index ): array {
		if ( ! isset( $items[ $index ] ) ) {
			throw new RuntimeException( 'Unknown fixture list index.' );
		}

		return $items[ $index ];
	}
}
