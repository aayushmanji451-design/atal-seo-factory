<?php
/**
 * Source reference validator.
 *
 * @package AtalSeoFactory
 */

declare(strict_types=1);

namespace Atal\Contracts\Validation;

use Atal\Contracts\Data\JsonValue;
use Atal\Contracts\Data\KnowledgePackage;

/**
 * Requires resolvable source references for every fee and duration fact.
 */
final class SourceReferenceValidator {

	/**
	 * Validate course- and option-level fee/duration references.
	 *
	 * @param KnowledgePackage $package Canonical package.
	 *
	 * @return list<ValidationIssue>
	 */
	public function validate( KnowledgePackage $package ): array {
		$issues = array();

		foreach ( array( 'institute_courses', 'diploma_courses' ) as $document_name ) {
			$document   = $package->document( $document_name );
			$source_ids = $this->source_ids( $document );
			$courses    = JsonValue::object_list_field( $document, 'courses' );

			foreach ( $courses as $course ) {
				$key    = JsonValue::string_field( $course, 'course_key' );
				$issues = array_merge( $issues, $this->validate_fact( JsonValue::object_field( $course, 'fee' ), $source_ids, $key . '.fee' ) );
				$issues = array_merge( $issues, $this->validate_fact( JsonValue::object_field( $course, 'duration' ), $source_ids, $key . '.duration' ) );

				$options = JsonValue::optional_object_list_field( $course, 'options' );
				foreach ( $options as $option ) {
					$option_path = $key . '.options.' . JsonValue::string_field( $option, 'option_key' );
					$issues      = array_merge( $issues, $this->validate_fact( JsonValue::object_field( $option, 'fee' ), $source_ids, $option_path . '.fee' ) );
					$issues      = array_merge( $issues, $this->validate_fact( JsonValue::object_field( $option, 'duration' ), $source_ids, $option_path . '.duration' ) );
				}
			}
		}

		return $issues;
	}

	/**
	 * Return validation metrics.
	 *
	 * @param KnowledgePackage $package Canonical package.
	 *
	 * @return array{course_facts_checked:int,institute_options_checked:int}
	 */
	public function metrics( KnowledgePackage $package ): array {
		$institute         = $package->document( 'institute_courses' );
		$diploma           = $package->document( 'diploma_courses' );
		$institute_courses = JsonValue::object_list_field( $institute, 'courses' );
		$diploma_courses   = JsonValue::object_list_field( $diploma, 'courses' );
		$option_count      = 0;

		foreach ( $institute_courses as $course ) {
			$options       = JsonValue::object_list_field( $course, 'options' );
			$option_count += count( $options );
		}

		return array(
			'course_facts_checked'      => count( $institute_courses ) + count( $diploma_courses ),
			'institute_options_checked' => $option_count,
		);
	}

	/**
	 * Return declared source IDs.
	 *
	 * @param array<string,mixed> $document Course master.
	 *
	 * @return list<string>
	 */
	private function source_ids( array $document ): array {
		$sources = JsonValue::object_list_field( $document, 'source_register' );
		$result  = array();
		foreach ( $sources as $source ) {
			$result[] = JsonValue::string_field( $source, 'source_id' );
		}

		return $result;
	}

	/**
	 * Validate one sourced fact.
	 *
	 * @param array<string,mixed> $fact       Fact object.
	 * @param list<string>        $source_ids Allowed source IDs.
	 * @param string              $path       Fact path.
	 *
	 * @return list<ValidationIssue>
	 */
	private function validate_fact( array $fact, array $source_ids, string $path ): array {
		if ( ! isset( $fact['source_refs'] ) || ! is_array( $fact['source_refs'] ) || array() === $fact['source_refs'] ) {
			return array( new ValidationIssue( 'required_source_reference_missing', 'Fee and duration facts require non-empty source_refs.', $path ) );
		}

		$issues = array();
		foreach ( JsonValue::string_list_field( $fact, 'source_refs' ) as $source_ref ) {
			if ( ! in_array( $source_ref, $source_ids, true ) ) {
				$issues[] = new ValidationIssue( 'unknown_source_reference', 'Source reference does not resolve in the course master source register.', $path );
			}
		}

		return $issues;
	}
}
