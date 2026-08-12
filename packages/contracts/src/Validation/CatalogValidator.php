<?php
/**
 * Canonical catalog validator.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Contracts\Validation;

use Atal\Contracts\Data\CanonicalCatalog;
use Atal\Contracts\Data\JsonValue;
use Atal\Contracts\Data\KnowledgePackage;

/**
 * Validates completeness against an independent approved catalog allowlist.
 */
final class CatalogValidator {

	/**
	 * Validate catalog cardinality, membership, and option counts.
	 *
	 * @param KnowledgePackage $package Canonical package.
	 *
	 * @return list<ValidationIssue>
	 */
	public function validate( KnowledgePackage $package ): array {
		$issues              = array();
		$institute_document  = $package->document( 'institute_courses' );
		$diploma_document    = $package->document( 'diploma_courses' );
		$institute_courses   = $this->courses( $institute_document );
		$diploma_courses     = $this->courses( $diploma_document );
		$institute_keys      = $this->course_keys( $institute_courses );
		$diploma_keys        = $this->course_keys( $diploma_courses );
		$expected_institute  = CanonicalCatalog::institute_keys();
		$expected_diploma    = CanonicalCatalog::diploma_keys();
		$actual_option_count = 0;

		if ( $this->sorted( $institute_keys ) !== $this->sorted( $expected_institute ) ) {
			$issues[] = new ValidationIssue( 'institute_catalog_mismatch', 'Institute active families do not match the approved 29-key allowlist.', 'institute_courses.courses' );
		}

		if ( $this->sorted( $diploma_keys ) !== $this->sorted( $expected_diploma ) ) {
			$issues[] = new ValidationIssue( 'diploma_catalog_mismatch', 'Diploma active identities do not match the approved 14-key allowlist.', 'diploma_courses.courses' );
		}

		if ( count( array_unique( array_merge( $institute_keys, $diploma_keys ) ) ) !== 43 ) {
			$issues[] = new ValidationIssue( 'active_course_key_collision', 'The active catalog must contain 43 unique course keys.', 'course_masters' );
		}

		foreach ( $institute_courses as $course ) {
			$options               = JsonValue::object_list_field( $course, 'options' );
			$declared_option_count = JsonValue::integer_field( $course, 'family_option_count' );
			$actual_option_count  += count( $options );

			if ( count( $options ) !== $declared_option_count ) {
				$issues[] = new ValidationIssue( 'family_option_count_mismatch', 'Declared family_option_count does not match options.', JsonValue::string_field( $course, 'course_key' ) );
			}

			$option_keys = array();
			foreach ( $options as $option ) {
				$option_keys[] = JsonValue::string_field( $option, 'option_key' );
			}

			if ( ! in_array( JsonValue::string_field( $course, 'primary_option_key' ), $option_keys, true ) ) {
				$issues[] = new ValidationIssue( 'primary_option_missing', 'The primary option must belong to its family.', JsonValue::string_field( $course, 'course_key' ) );
			}
		}

		if ( 49 !== $actual_option_count ) {
			$issues[] = new ValidationIssue( 'institute_option_count_mismatch', 'Institute catalog must contain exactly 49 options.', 'institute_courses.courses' );
		}

		$diploma_count = count(
			array_filter(
				$diploma_courses,
				static fn ( array $course ): bool => 'DIPLOMA' === JsonValue::string_field( $course, 'level' )
			)
		);
		$pg_count      = count( $diploma_courses ) - $diploma_count;

		if ( 9 !== $diploma_count || 5 !== $pg_count ) {
			$issues[] = new ValidationIssue( 'diploma_level_count_mismatch', 'Diploma catalog must contain 9 Diploma and 5 PG Diploma identities.', 'diploma_courses.courses' );
		}

		return $issues;
	}

	/**
	 * Return catalog metrics.
	 *
	 * @param KnowledgePackage $package Canonical package.
	 *
	 * @return array{institute_families:int,institute_options:int,diploma_identities:int,unique_active_keys:int}
	 */
	public function metrics( KnowledgePackage $package ): array {
		$institute_courses = $this->courses( $package->document( 'institute_courses' ) );
		$diploma_courses   = $this->courses( $package->document( 'diploma_courses' ) );
		$option_count      = 0;

		foreach ( $institute_courses as $course ) {
			$options       = JsonValue::object_list_field( $course, 'options' );
			$option_count += count( $options );
		}

		$keys = array_merge( $this->course_keys( $institute_courses ), $this->course_keys( $diploma_courses ) );

		return array(
			'institute_families' => count( $institute_courses ),
			'institute_options'  => $option_count,
			'diploma_identities' => count( $diploma_courses ),
			'unique_active_keys' => count( array_unique( $keys ) ),
		);
	}

	/**
	 * Extract course records.
	 *
	 * @param array<string,mixed> $document Course master.
	 *
	 * @return list<array<string,mixed>>
	 */
	private function courses( array $document ): array {
		return JsonValue::object_list_field( $document, 'courses' );
	}

	/**
	 * Extract course keys.
	 *
	 * @param list<array<string,mixed>> $courses Courses.
	 *
	 * @return list<string>
	 */
	private function course_keys( array $courses ): array {
		$keys = array();
		foreach ( $courses as $course ) {
			$keys[] = JsonValue::string_field( $course, 'course_key' );
		}

		return $keys;
	}

	/**
	 * Return a sorted copy of a string list.
	 *
	 * @param list<string> $values Values.
	 *
	 * @return list<string>
	 */
	private function sorted( array $values ): array {
		sort( $values );

		return $values;
	}
}
